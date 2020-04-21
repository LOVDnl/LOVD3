<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-01-22
 * Modified    : 2020-04-21
 * For LOVD    : 3.0-24
 *
 * Copyright   : 2004-2020 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Daan Asscheman <D.Asscheman@LUMC.nl>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               M. Kroon <m.kroon@lumc.nl>
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





function lovd_fixHGVS ($sVariant, $sType = 'g')
{
    // This function tries to recognize common errors in the HGVS nomenclature,
    //  and fix the variants in such a way, that they will be recognizable and
    //  usable.

    if (!in_array($sType, array('g', 'c'))) {
        $sType = 'g';
    }

    // Do a quick HGVS check.
    if (lovd_getVariantInfo($sVariant, '', true)) {
        // All good!
        return $sVariant;
    }

    // Make sure we can use ctype_digit().
    $sVariant = (string) $sVariant;

    // Forgot a prefix?
    if (ctype_digit($sVariant{0})) {
        // Variant starts with a number. Try including a prefix.
        return lovd_fixHGVS($sType . '.' . $sVariant, $sType);
    }

    // Delins variants that should be conversions.
    if (preg_match('/^' . $sType . '\.([0-9]+_[0-9]+)delins([0-9]+_[0-9]+)$/', $sVariant, $aRegs)) {
        // Return as a conversion.
        return lovd_fixHGVS($sType . '.' . $aRegs[1] . 'con' . $aRegs[2]);
    }

    return $sVariant;
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
