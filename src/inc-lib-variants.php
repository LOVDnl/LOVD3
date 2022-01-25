<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-01-22
 * Modified    : 2022-01-25
 * For LOVD    : 3.0-28
 *
 * Copyright   : 2004-2022 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Daan Asscheman <D.Asscheman@LUMC.nl>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               M. Kroon <m.kroon@lumc.nl>
 *               Loes Werkman <L.Werkman@LUMC.nl>
 *
 *
 * This file is part of LOVD.
 *
 * LOVD is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * LOVD is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with LOVD. If not, see <http://www.gnu.org/licenses/>.
 *
 *************/

// Don't allow direct access.
if (!defined('ROOT_PATH')) {
    exit;
}





function lovd_fixHGVS ($sVariant, $sType = '')
{
    // This function tries to recognize common errors in the HGVS nomenclature,
    //  and fix the variants in such a way, that they will be recognizable and
    //  usable.
    // $sType stores the DNA type (c, g, m, or n) to allow for this function to
    //  fully validate the variant and, optionally, its reference sequence.

    if (!in_array($sType, array('g', 'm', 'c', 'n'))) {
        // If type is not given, default to something.
        // We usually just default to 'g'. But when it's obviously something
        //  else, pick that other thing.
        if (in_array($sVariant[0], array('c', 'g', 'm', 'n'))) {
            $sType = $sVariant[0];
        } else {
            if (preg_match('/[0-9][+-][0-9]/', $sVariant)) {
                // Variant doesn't have a prefix either, *and* there seems to be an
                //  intronic position mentioned.
                $sType = 'c';
            } else {
                // Fine, we default to 'g'.
                $sType = 'g';
            }
        }
    }

    // Trim the variant and remove whitespace.
    $sVariant = preg_replace('/\s+/', '', $sVariant);

    // Replace special – (hyphen, minus, en dash, em dash) with a simple - (hyphen-minus).
    $sVariant = str_replace(array('‐', '−', '–', '—'), '-', $sVariant);

    // Do a quick HGVS check.
    if (lovd_getVariantInfo($sVariant, false, true)) {
        // All good!
        return $sVariant;
    }

    // We currently don't support OR variants (^). In fact, if we don't return
    //  it here, we'll mutilate it.
    if (strpos($sVariant, '^') !== false) {
        return $sVariant;
    }

    // Check for a reference sequence. We won't check it here, so we won't be
    //  very strict.
    if (preg_match('/^(ENS[GT]|LRG_|[NX][CGMRTW]_)[0-9]+/', $sVariant)) {
        // Something that looks like a reference sequence is prefixing the
        //  variant. Cut it off and store it separately. We'll return it, but
        //  this way we can actually check the variant itself.
        list($sReference, $sVariant) = explode(':', $sVariant, 2);
        $sReference .= ':'; // To simplify the concatenation later on.
    } else {
        // No reference was found.
        $sReference = '';
    }

    // Move or remove wrongly placed parentheses.
    $nOpening = substr_count($sVariant, '(');
    $nClosing = substr_count($sVariant, ')');
    if ($nOpening != $nClosing) {
        // There are more parentheses opening than closing.
        // We won't be looking all the way into the variant, since that is
        //  simply too much effort for the reward. However, we will take a look
        //  at the simplest and most common mistakes.
        if (strpos($sVariant, '((') !== false && ($nOpening - $nClosing) == 1) {
            // e.g., g.((123_234)_(345_456)del.
            return lovd_fixHGVS($sReference . str_replace('((', '(', $sVariant), $sType);

        } elseif (($nClosing - $nOpening) == 1 && strpos($sVariant, '))')) {
            // e.g. g.(123_234)_(345_456))del.
            return lovd_fixHGVS($sReference . str_replace('))', ')', $sVariant), $sType);

        } else {
            // The parentheses are formatted in a more difficult way than
            //  is worth handling. We will return the variant, which is sadly
            //  still not HGVS.
            return $sReference . $sVariant; // Not HGVS.
        }

    } elseif ($sVariant[0] == '(') {
        // All opening parentheses are closed, but the description starts with
        //  one, which isn't an option. Don't just assume a prefix is there or
        //  not, check.
        if (!preg_match('/\b[cgmn]\./', $sVariant)) {
            // No prefix found. Add one.
            return lovd_fixHGVS(
                $sReference . $sType . '.(' . substr($sVariant, 1), $sType);
        } elseif (preg_match('/^\(([cgmn]\.)/', $sVariant, $aRegs)) {
            // The variant is written as (c.1_2insA). We will rewrite this as c.(1_2insA).
            return lovd_fixHGVS(
                $sReference . $aRegs[1] . '(' . substr($sVariant, 3), $sType);
        }

    } elseif (strpos($sVariant, '((') !== false || strpos($sVariant, '))') !== false) {
        if (preg_match('/\(\([0-9_]+\)\)/', $sVariant)) {
            // c.((1_5))insA or c.100_500del((10))
            return lovd_fixHGVS($sReference . str_replace(array('((', '))'), array('(', ')'), $sVariant), $sType);
        }
    }

    // Add prefix in case it is missing.
    if (!in_array($sVariant[0], array('c', 'g', 'm', 'n'))) {
        return lovd_fixHGVS($sReference . $sType . ($sVariant[0] == '.'? '' : '.') . $sVariant, $sType);
    }

    // Replace the outdated "con" type with "delins".
    // This used to check also if the delins needed square brackets around the
    //  insertion, but we moved that code to generalize it.
    if (strpos($sVariant, 'con') !== false) {
        return lovd_fixHGVS($sReference . str_replace('con', 'delins', $sVariant), $sType);
    }

    // Remove redundant prefixes due to copy/paste errors (g.12_g.23del to g.12_23del).
    if (substr_count($sVariant, $sType . '.') > 1) {
        return lovd_fixHGVS($sReference . $sType . '.' . str_replace($sType . '.', '', $sVariant), $sType);
    }

    // We also don't like bases in lowercase.
    if (preg_match('/^(.+)([a-z]>[a-z])$/', $sVariant, $aRegs)
        || preg_match('/^(.+ins)([a-z]+)$/', $sVariant, $aRegs)) {
        return lovd_fixHGVS($sReference . $aRegs[1] . strtoupper($aRegs[2]));
    }

    // Replace uracil with thymine (RNA -> DNA description).
    if ((preg_match('/^(.+)([A-Z]>[A-Z])$/', $sVariant, $aRegs)
            || preg_match('/^(.+ins)([A-Z]+)$/', $sVariant, $aRegs))
        && strpos($aRegs[2], 'U') !== false) {
        // Also convert U to T, since lowercase bases may mean an RNA-based description.
        return lovd_fixHGVS($sReference . $aRegs[1] . str_replace('U', 'T', $aRegs[2]));
    }

    // Make sure no unnecessary bases are given for wild types (c.123A= -> c.123=).
    if (preg_match('/[0-9]([ACGTN]+=)/', $sVariant, $aRegs)) {
        return lovd_fixHGVS($sReference . str_replace($aRegs[1], '=', $sVariant), $sType);
    }



    // The basic steps have all been taken. From this point forward, we
    //  can use the warning and error messages of lovd_getVariantInfo() to check
    //  and fix the variant.
    $aVariant = lovd_getVariantInfo($sVariant, false);
    if ($aVariant === false) {
        return $sReference . $sVariant; // Not HGVS.

    } elseif (isset($aVariant['errors']['EPIPEMISSING'])) {
        // This looked like a methylation-related variant that was missing a
        //  pipe, failing lovd_getVariantInfo()'s entire regexp.
        return lovd_fixHGVS($sReference . preg_replace('/(gom|lom|met=|bsrC?)$/', '|$1', $sVariant), $sType);

    } elseif (isset($aVariant['errors']['ENOTSUPPORTED'])
        && $aVariant['type'] == 'met' && strpos($sVariant, '||')) {
        // Whatever is after the pipe wasn't recognized, but we also found more
        //  pipes. Try removing them.
        return lovd_fixHGVS($sReference . preg_replace('/\|{2,}/', '|', $sVariant), $sType);

    } elseif (isset($aVariant['errors']['EFALSEUTR']) || isset($aVariant['errors']['EFALSEINTRONIC'])) {
        // The wrong prefix was given. In other words: intronic positions or UTR
        //  notations were found for genomic DNA.
        if ($sVariant[0] == $sType) {
            if (isset($aVariant['errors']['EFALSEINTRONIC'])
                && ($aVariant['position_start'] >= 250000 || $aVariant['position_start_intron'] >= 250000)) {
                // If variants hold false intronic positions, it might be that
                //  the user accidentally wrote down '-' while meaning '_'.
                // We will fix this only if we can be really sure this is the case,
                //  which is if the variant contains a position too big to
                //  be of a transcript.
                return lovd_fixHGVS($sReference . str_replace('-', '_', $sVariant), $sType);

            } else {
                // The user likely put the input in the wrong field.
                // We cannot fix this variant with certainty.
                return $sReference . $sVariant; // Not HGVS.
            }

        } else {
            // If the prefix does not equal the expected type, we can be sure
            //  to try and add in the type instead. Perhaps the user accidentally
            //  wrote down a 'g.' in the transcript field.
            return lovd_fixHGVS($sReference . $sType . substr($sVariant, 1), $sType);
        }

    } elseif (!empty($aVariant['errors']
        && !isset($aVariant['errors']['ESUFFIXMISSING'])
        && isset($aVariant['warnings']['WTOOMUCHUNKNOWN']))) {
        return $sReference . $sVariant; // Not HGVS.
    }

    // Change the variant type (if possible) if the wrong type was chosen.
    if (isset($aVariant['warnings']['WWRONGTYPE'])) {
        if ($aVariant['type'] == 'subst') {
            // Change positions based on length REF part of the substitution;
            //  e.g. N>N or NN>N.
            preg_match('/([A-Z]+)>([A-Z]+)$/', $sVariant, $aRegs);
            $nLength = strlen($aRegs[1]);
            if ($nLength > 1 && !isset($aVariant['errors']['ETOOMANYPOSITIONS'])) {
                // Only when we have more than one base before the > and there
                //  is currently just one position, do we calculate an end
                //  position.
                if (isset($aVariant['position_start_intron'])) {
                    $aVariant['position_end_intron'] += $nLength - 1;
                    // Compensate for the possibility where we just left the intron.
                    if ($aVariant['position_start_intron'] < 0 && $aVariant['position_end_intron'] > 0) {
                        $aVariant['position_end'] += $aVariant['position_end_intron'];
                        $aVariant['position_end_intron'] = 0;
                    }
                } else {
                    $aVariant['position_end'] += $nLength - 1;
                }
                $sEndPosition = $aVariant['position_end'] .
                    (empty($aVariant['position_end_intron'])? '' :
                        ($aVariant['position_end_intron'] < 1? $aVariant['position_end_intron'] : '+' . $aVariant['position_end_intron']));
                return lovd_fixHGVS($sReference . str_replace($aRegs[0], '_' . $sEndPosition . 'delins' . $aRegs[2], $sVariant), $sType);

            } elseif ($nLength > 1) {
                // Variant already has a range as position, check the length.
                $nPositionLength = lovd_getVariantLength($aVariant);
                if ($nPositionLength != $nLength) {
                    // e.g., c.100_102AA>C
                    // This is an error we cannot fix. We don't know if the
                    //  error is in the positions or the given sequence.
                    return $sReference . $sVariant;
                }
            }
            return lovd_fixHGVS($sReference . str_replace($aRegs[0], 'delins' . $aRegs[2], $sVariant), $sType);
        } elseif ($aVariant['type'] == 'delins') {
            return $sReference . $sVariant; // Not HGVS, and not fixable by us (unless we use VV).
        }
    }

    // Remove the suffix if it is given to a variant type which should not hold one.
    if (isset($aVariant['warnings']['WSUFFIXGIVEN']) && !isset($aVariant['warnings']['WTOOMUCHUNKNOWN'])) {
        // The warning message indicates where the unwanted suffix starts.
        // We take this string by isolating the part between the double quotes.
        list(,$sVariantType) = explode('"', $aVariant['warnings']['WSUFFIXGIVEN']);
        if (!preg_match('/[0-9]+_[0-9]+' . $sVariantType . '\([0-9]+(_[0-9]+)?\)/', $sVariant)) {
            // If the suffix is formatted such as '([0-9]+)', it indicated the
            //  length of the variant. If we find this in variants of which the
            //  positions are '[0-9]+_[0-9]+', it might be that the user forgot
            //  to use brackets around the positions (indicating an uncertain
            //  location, which would mean the suffix IS necessary). We cannot
            //  be sure we may remove it, so we have to let this be.
            list($sBeforeType,$sSuffix) = explode($sVariantType, $sVariant, 2);
            // For normal dels and dups, don't remove the suffix if it doesn't
            //  match the length.
            if (in_array($aVariant['type'], array('del', 'dup'))
                && strlen(trim($sSuffix, '()')) != lovd_getVariantLength($aVariant)) {
                // Don't mess with it.
                return $sReference . $sVariant;
            }
            return lovd_fixHGVS(
                $sReference . $sBeforeType . $sVariantType .
                str_repeat(')', (substr_count($sSuffix, ')') - substr_count($sSuffix, '('))), $sType);
        }
    }



    // Reformat wrongly described suffixes.
    if (isset($aVariant['warnings']['WSUFFIXFORMAT'])) {
        list($sBeforeSuffix, $sSuffix) = explode($aVariant['type'], $sVariant, 2);

        if (ctype_digit($sSuffix)) {
            // Add parentheses in case they were forgotten.
            return lovd_fixHGVS($sReference .
                $sBeforeSuffix . $aVariant['type'] . '(' . $sSuffix . ')', $sType);
        }

        if (in_array($aVariant['type'], array('ins', 'delins'))) {
            // Extra format checks which only apply to ins or delins types.

            if (preg_match('/^\([ACTG]+\)$/', $sSuffix) || preg_match('/^N\[\([0-9]+\)\]/', $sSuffix)) {
                // Remove redundant parentheses, e.g. ins(A) or insN[(20)].
                return lovd_fixHGVS($sReference .
                    $sBeforeSuffix . $aVariant['type'] . str_replace(array('(', ')'), '', $sSuffix), $sType);

            } elseif (preg_match('/^\[.*\]$/', $sSuffix)
                && !preg_match('/[:;]/', $sSuffix)
                && !preg_match('/^\[[NX][CGMRTW]_[0-9]+/', $sSuffix)) {
                // Remove redundant square brackets surrounding the suffix,
                //  these are only needed when RefSeqs or combined variants are given.
                return lovd_fixHGVS($sReference .
                    $sBeforeSuffix . $aVariant['type'] . trim($sSuffix, '[]'), $sType);

            } elseif (preg_match('/^[NX][CGMRTW]_[0-9]+/', $sSuffix)
                || (strpos($sSuffix, ';') && strpos($sSuffix, '[') === false)) {
                // Square brackets were forgotten, RefSeqs or combined variants are given.
                return lovd_fixHGVS($sReference .
                    $sBeforeSuffix . $aVariant['type'] . '[' . $sSuffix . ']', $sType);

            } elseif (preg_match('/(^|.)\([0-9]+(?:_[0-9]+)?\)(.|$)/', $sSuffix, $aRegs)) {
                // The length of a variant was formatted as 'ins(length)'
                //  instead of 'insN[length]' or 'ins(min_max)' instead
                //  of 'insN[(min_max)]'.
                // HOWEVER; Since we're not matching the whole suffix on purpose
                //  to support combined variants like g.1_2ins[ACT;(20)], we
                //  might also be dealing with a simply miss-formatted suffix
                //  where we *did* already place insN[] around the length.
                // Also, we might be dealing with a reference-sequence based
                //  suffix with unsure positions. So, check!
                if (!($aRegs[1] == '[' && $aRegs[2] == ']')
                    && !preg_match('/^\[[NX][CGMRTW]_[0-9]+/', $sSuffix)) {
                    return lovd_fixHGVS($sReference . $sBeforeSuffix . $aVariant['type'] . preg_replace(
                            array('/\(([0-9]+)\)/', '/\(([0-9]+_[0-9]+)\)/'),
                            array('N[${1}]', 'N[(${1})]'), $sSuffix), $sType);
                }
            }
        }
    }

    // Fix problems with too many questionmarks.
    if (isset($aVariant['warnings']['WTOOMUCHUNKNOWN'])) {
        // This means that question marks where given to the variant in
        //  places where they do not bring any additional value. We'll let
        //  lovd_getVariantInfo() do all the work!
        if (preg_match('/Please rewrite the positions ([()0-9_?+-]+) to ([()0-9_?+-]+)\.$/',
            $aVariant['warnings']['WTOOMUCHUNKNOWN'], $aRegs)) {
            list(, $sOldPosition, $sNewPosition) = $aRegs;
            return lovd_fixHGVS($sReference . str_replace($sOldPosition, $sNewPosition, $sVariant), $sType);
        }
    }

    // Swap positions if necessary.
    if (isset($aVariant['warnings']['WPOSITIONFORMAT'])) {
        $aPositions = array();

        preg_match('/([cgmn]\.)(\()?' .
            '(([*-+]?([0-9]+|\?))([-+][?0-9]+)?)' .
            '(?(2)_(([*-+]?([0-9]+|\?))([-+][?0-9]+)?)\))(_' .
            '(\()?(([*-+]?([0-9]+|\?))([-+][?0-9]+)?)' .
            '(?(12)_(([*-+]?([0-9]+|\?))([-+][?0-9]+)?)\)))?([A-Za-z|]+.*)/',
            $sVariant, $aMatches);
        // c.(1_2)_(3_4)del == c.(A_B)_(C_D)del
        // c.1_2del         == c.A_Cdel
        // c.(1_2)del       == c.(A_B)del
        $sBefore  = $aMatches[1];
        $sAfter   = $aMatches[21];

        $aPositions['A']       = $aMatches[4];
        $aPositions['AIntron'] = $aMatches[6];
        $aPositions['B']       = $aMatches[8];
        $aPositions['BIntron'] = $aMatches[10];
        $aPositions['C']       = $aMatches[14];
        $aPositions['CIntron'] = $aMatches[16];
        $aPositions['D']       = $aMatches[18];
        $aPositions['DIntron'] = $aMatches[20];

        if ($aPositions['C']
            && max($aPositions['A'], $aPositions['B']) > max($aPositions['C'], $aPositions['D'])) {
            // If this is the case, the positions are swapped in groups,
            //  i.e., c.(6_10)_(1_5)del.
            list($aPositions['A'], $aPositions['AIntron'], $aPositions['B'], $aPositions['BIntron'],
                $aPositions['C'], $aPositions['CIntron'], $aPositions['D'], $aPositions['DIntron']) =
                array($aPositions['C'], $aPositions['CIntron'], $aPositions['D'], $aPositions['DIntron'],
                    $aPositions['A'], $aPositions['AIntron'], $aPositions['B'], $aPositions['BIntron']);

        } else {
            // If the above is not the case, the positions are swapped more
            //  intricately. This will be checked and fixed one by one.
            foreach (array(array('A', 'B'), array('C', 'D'), array('A', 'C'), array('B', 'D')) as $aFirstAndLast) {
                list($sFirst, $sLast) = $aFirstAndLast;

                if ($aPositions[$sFirst] && $aPositions[$sLast]
                    && $aPositions[$sFirst] != '?' && $aPositions[$sLast] != '?') {
                    // We only check the positions if the first and last value are
                    //  not empty strings or question marks.
                    $sIntronicFirst = $sFirst . 'Intron';
                    $sIntronicLast = $sLast . 'Intron';

                    if ($aPositions[$sFirst] > $aPositions[$sLast]) {
                        list($aPositions[$sFirst], $aPositions[$sIntronicFirst], $aPositions[$sLast], $aPositions[$sIntronicLast]) =
                            array($aPositions[$sLast], $aPositions[$sIntronicLast], $aPositions[$sFirst], $aPositions[$sIntronicFirst]);

                    } elseif ($aPositions[$sFirst] == $aPositions[$sLast]) {
                        if ($aPositions[$sIntronicFirst] > $aPositions[$sIntronicLast]) {
                            list($aPositions[$sIntronicFirst], $aPositions[$sIntronicLast]) =
                                array($aPositions[$sIntronicLast], $aPositions[$sIntronicFirst]);

                        } elseif ($sFirst . $sLast == 'AB' || $sFirst . $sLast == 'CD'
                            || ($sFirst . $sLast == 'AC' && !$aPositions['B'] && !$aPositions['D'])) {
                            // If the first and last positions are the same, we can
                            //  only remove the last one if the positions are
                            //  grouped together (e.g. c.1_1del, or c.(1_1)_10del).
                            $aPositions[$sLast] = '';
                            $aPositions[$sIntronicLast] = '';
                        }
                    }
                }
            }
        }

        $sNewVariant = $sBefore .
            ($aPositions['A'] && $aPositions['B'] ? '(' : '') . $aPositions['A'] . $aPositions['AIntron'] .
            ($aPositions['B'] ? '_' . $aPositions['B'] . $aPositions['BIntron'] . ')' : '') .
            ($aPositions['C'] ? '_' . ($aPositions['D'] ? '(' : '') . $aPositions['C'] . $aPositions['CIntron'] : '') .
            ($aPositions['D'] ? '_' . $aPositions['D'] . $aPositions['DIntron'] . ')' : '') .
            $sAfter;

        if ($sNewVariant != $sVariant) {
            return lovd_fixHGVS($sReference . $sNewVariant, $sType);
        }
    }



    // We're out of things that we can do.
    return $sReference . $sVariant; // Not HGVS.
}





/**
 * Predict a protein description of a variant and given transcript using the
 * Mutalyzer webservice.
 * @param $sReference
 * @param string $sGene
 * @param $sNCBITranscriptID
 * @param string $sVariant
 * @return array $aMutalyzerData
 */
function lovd_getRNAProteinPrediction ($sReference, $sGene, $sNCBITranscriptID, $sVariant)
{
    global $_CONF, $_SETT;

    $aMutalyzerData = array();

    // Regex pattern to match a reference accession number in variant description.
    $sRefseqPattern = '(UD_\d{12}|N(?:C|G)_\d{6,}\.\d{1,2})';

    if (isset($sGene) && isset($_SETT['mito_genes_aliases'][$sGene])) {
        // This is a mitochondrial gene.
        if (empty($sNCBITranscriptID) || empty($sVariant)) {
            $aMutalyzerData['mutalyzer_error'] = 'No valid transcript ID or variant specified.';
            return $aMutalyzerData;
        }
        // Gene is defined in the mito_genes_aliases in file inc-init.php: use the NCBI gene symbol.
        $sNCBITranscriptID = str_replace($sGene, $_SETT['mito_genes_aliases'][$sGene],
                                         $sNCBITranscriptID);

        // For mitochondrial genes, Mutalyzer specifies the NCBI transcript ID actually
        // as an NC_ accession number with NCBI gene alias (e.g. 'NC_012920.1(TRNF_v001)')
        // We can use that directly as a reference in the variant description.
        $sFullVariantDescription = $sNCBITranscriptID . ':' . $sVariant;
    } else {
        // Non-mitochondrial gene, use normal reference, transcript ID and variant.
        if (empty($sReference) || empty($sNCBITranscriptID) || empty($sVariant) ||
            !preg_match('/^' . $sRefseqPattern . '$/', $sReference)) {

            $aMutalyzerData['mutalyzer_error'] = 'No valid input given for reference, transcript id or variant.';
            return $aMutalyzerData;
        }

        $sFullVariantDescription = $sReference . '(' . $sNCBITranscriptID . '):' . $sVariant;
    }



    // Build URL for protein prediction to be shown in interface.
    $aMutalyzerData['mutalyzer_url'] = str_replace('services', 'check', $_CONF['mutalyzer_soap_url']) .
        '?name=' . urlencode($sFullVariantDescription) . '&standalone=1';

    // Make call to mutalyzer to check variant description.
    $aResponse = lovd_callMutalyzer('runMutalyzer', array('variant' => $sFullVariantDescription));
    if ($aResponse === false) {
        $aMutalyzerData['mutalyzer_error'] = 'Unexpected response from Mutalyzer. Please try again later.';
        return $aMutalyzerData;
    }

    // When transcript is not found, attempt fallback to newer version of transcript.
    foreach (getMutalyzerMessages($aResponse) as $aMessage) {
        if ($aMessage['errorcode'] === 'EINVALIDTRANSVAR') {
            // Invalid transcript variant.

            if (!empty($aResponse['legend'])) {
                // Check if a newer version of the transcript is available from the legend.
                list($sAccession, $sVersion) = explode('.', $sNCBITranscriptID);

                foreach ($aResponse['legend'] as $aRecord) {
                    $aRecordFields = explode('.', $aRecord['id']);
                    if (count($aRecordFields) != 2) {
                        continue;
                    }
                    list($sAltAccession, $sAltVersion) = $aRecordFields;

                    if ($sAccession == $sAltAccession &&
                        intval($sAltVersion) > intval($sVersion)) {
                        // Found a newer version of the transcript. Try to do protein
                        //  prediction using that record instead.
                        $aAltMutalyzerOutput = lovd_getRNAProteinPrediction($sReference, $sGene,
                            $aRecord['id'], $sVariant);
                        if (!isset($aAltMutalyzerOutput['mutalyzer_error']) &&
                            !isset($aAltMutalyzerOutput['error']) &&
                            !empty($aAltMutalyzerOutput['predict'])) {
                            // Prediction with alternative transcript record went well, return it
                            // with an added warning.
                            $aAltMutalyzerOutput['warning']['DEPRECATED TRANSCRIPT'] =
                                'The provided transcript is outdated, the given prediction is ' .
                                'based on the latest version of the transcript: ' .
                                $sAltAccession . '.' . $sAltVersion;
                            return $aAltMutalyzerOutput;
                        }
                    }
                }

                // Could not find a newer version of the transcript.
                $aMutalyzerData['error'][$aMessage['errorcode']] = trim($aMessage['message']);
                return $aMutalyzerData;
            }
        }
    }

    // Find protein prediction in mutalyzer output.
    if (!empty($aResponse['legend']) && !empty($aResponse['proteinDescriptions'])) {
        $sMutProteinName = null;

        // Loop over legend records to find transcript name (v-number).
        foreach ($aResponse['legend'] as $aRecord) {
            if (isset($aRecord['id']) && $aRecord['id'] == $sNCBITranscriptID &&
                substr($aRecord['name'], -4, 1) == 'v') {
                // Generate protein isoform name (i-number) from transcript name (v-number)
                $sMutProteinName = str_replace('_v', '_i', $aRecord['name']);
                break;
            }
        }

        if (isset($sMutProteinName)) {
            // Select protein description based on protein isoform (i-number).
            $sProteinDescriptions = implode('|', $aResponse['proteinDescriptions']);
            preg_match('/' . $sRefseqPattern . '\(' . preg_quote($sMutProteinName) .
                       '\):(p\..+?)(\||$)/', $sProteinDescriptions, $aProteinMatches);
            if (isset($aProteinMatches[2])) {
                $aMutalyzerData['predict']['protein'] = $aProteinMatches[2];
            }
        }
    }



    foreach (getMutalyzerMessages($aResponse) as $aMessage) {
        if ($aMessage['errorcode'] === 'ERANGE') {
            // Ignore 'ERANGE' as an actual error, because we can always interpret this as p.(=), p.? or p.0.
            $sDNAChange = substr($sVariant, strpos($sVariant, ':') + 1);
            $aVariantRange = explode('_', $sDNAChange);
            // Check what the variant looks like and act accordingly.
            if (count($aVariantRange) === 2 && preg_match('/-\d+/', $aVariantRange[0]) && preg_match('/-\d+/', $aVariantRange[1])) {
                // Variant has 2 positions. Variant has both the start and end positions upstream of the transcript, we can assume that the product will not be affected.
                $sPredictR = 'r.(=)';
                $sPredictP = 'p.(=)';
            } elseif (count($aVariantRange) === 2 && preg_match('/-\d+/', $aVariantRange[0]) && preg_match('/\*\d+/', $aVariantRange[1])) {
                // Variant has 2 positions. Variant has an upstream start position and a downstream end position, we can assume that the product will not be expressed.
                $sPredictR = 'r.0?';
                $sPredictP = 'p.0?';
            } elseif (count($aVariantRange) == 2 && preg_match('/\*\d+/', $aVariantRange[0]) && preg_match('/\*\d+/', $aVariantRange[1])) {
                // Variant has 2 positions. Variant has both the start and end positions downstream of the transcript, we can assume that the product will not be affected.
                $sPredictR = 'r.(=)';
                $sPredictP = 'p.(=)';
            } elseif (count($aVariantRange) == 1 && preg_match('/-\d+/', $aVariantRange[0]) || preg_match('/\*\d+/', $aVariantRange[0])) {
                // Variant has 1 position and is either upstream or downstream from the transcript, we can assume that the product will not be affected.
                $sPredictR = 'r.(=)';
                $sPredictP = 'p.(=)';
            } else {
                // One of the positions of the variant falls within the transcript, so we can not make any assumptions based on that.
                $sPredictR = 'r.?';
                $sPredictP = 'p.?';
            }
            // Fill in our assumption in aData to forge that this information came from Mutalyzer.
            $aMutalyzerData['predict']['protein'] = $sPredictP;
            $aMutalyzerData['predict']['RNA'] = $sPredictR;
            continue;
        } elseif ($aMessage['errorcode'] === 'WSPLICE') {
            // Mutalyzer now (2012-12-07) returns a WSPLICE for <= 5 nucleotides from the site,
            // even though there internally is a difference between variants in splice sites,
            // and variants close to splice sites.
            // Most likely, they will include two different types of errors in the future.
            $aMutalyzerData['predict']['protein'] = 'p.?';
            $aMutalyzerData['predict']['RNA'] = 'r.spl?';
        }

        if (isset($aMessage['errorcode']) && substr($aMessage['errorcode'], 0, 1) === 'E') {
            $aMutalyzerData['error'][trim($aMessage['errorcode'])] =  trim($aMessage['message']);
        } elseif (isset($aMessage['errorcode'])) {
            $aMutalyzerData['warning'][trim($aMessage['errorcode'])] = trim($aMessage['message']);
        }
    }

    if ($aResponse['errors'] === 0 && empty($aMutalyzerData['predict']['RNA'])) {
        // RNA not filled in yet.
        if (!isset($aMutalyzerData['predict']['protein'])) {
            // Non-coding transcript, Mutalyzer does not return a protein field, but also no error.
            // FIXME: Check for intronic variants here, that do not span over an exon, and give them r.(=).
            $aMutalyzerData['predict']['RNA'] = 'r.(?)';
            $aMutalyzerData['predict']['protein'] = '-';
        } elseif ($aMutalyzerData['predict']['protein'] == 'p.?') {
            $aMutalyzerData['predict']['RNA'] = 'r.?';
        } elseif ($aMutalyzerData['predict']['protein'] == 'p.(=)') {
            // FIXME: Not correct in case of substitutions e.g. in the third position of the codon, not leading to a protein change.
            $aMutalyzerData['predict']['RNA'] = 'r.(=)';
        } else {
            // RNA will default to r.(?).
            $aMutalyzerData['predict']['RNA'] = 'r.(?)';
        }
    }

    return $aMutalyzerData;
}





function lovd_getVariantLength ($aVariant)
{
    // This function receives an array in the format as given by
    //  lovd_getVariantInfo() and calculates the length of the variant.
    // This length will only include intronic positions if the input contains
    //  these. When the length cannot be determined due to crossing the center
    //  of an intron, this function will return false.

    $nBasicLength = $aVariant['position_end'] - $aVariant['position_start'] + 1;
    if (empty($aVariant['position_start_intron'])
        && empty($aVariant['position_end_intron'])) {
        // Simple case; genomic variant or simply no introns involved.
        return ($nBasicLength);

    } elseif (empty($aVariant['position_start_intron'])) {
        // So we have an intronic end, but not an intronic start.
        // If the intronic end is negative, this means we're crossing the
        //  center of an intron, and the length cannot be determined.
        if ($aVariant['position_end_intron'] < 0) {
            return false;
        }
        return ($nBasicLength + $aVariant['position_end_intron']);

    } elseif (empty($aVariant['position_end_intron'])) {
        // So we have an intronic start, but not an intronic end.
        // If the intronic start is positive, this means we're crossing the
        //  center of an intron, and the length cannot be determined.
        if ($aVariant['position_start_intron'] > 0) {
            return false;
        }
        return ($nBasicLength + abs($aVariant['position_start_intron']));
    }

    // Else, we have intronic positions both for the start and the end.
    if ($aVariant['position_start'] == $aVariant['position_end']) {
        // Same side of the intron. Just take the max minus the min.
        // NOTE: $nBasicLength is already 1 even though no length has been
        //  calculated yet. So we don't have to add that 1 here.
        return (
            $nBasicLength +
            max(
                $aVariant['position_start_intron'],
                $aVariant['position_end_intron']
            ) -
            min(
                $aVariant['position_start_intron'],
                $aVariant['position_end_intron']
            )
        );

    } elseif ($aVariant['position_start_intron'] > 0
        || $aVariant['position_end_intron'] < 0) {
        // Still nope.
        return false;
    }

    // OK, just add the lengths.
    return (
        $nBasicLength
        + abs($aVariant['position_start_intron'])
        + $aVariant['position_end_intron']);
}





function getMutalyzerMessages ($aOutput)
{
    // Return an array of messages from mutalyzer's API output. Only messages
    // related to the gene in the original request are returned.

    $aMessages = array();

    if (isset($aOutput['messages'])) {
        foreach ($aOutput['messages'] as $aMessage) {
            if (preg_match('/_OTHER$/', $aMessage['errorcode']) !== 0) {
                // Whatever error it is, it's not about this gene!
                continue;
            }
            $aMessages[] = $aMessage;
        }
    }
    return $aMessages;
}
?>
