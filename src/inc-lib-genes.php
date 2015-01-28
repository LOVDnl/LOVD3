<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-01-25
 * Modified    : 2014-12-23
 * For LOVD    : 3.0-13
 *
 * Copyright   : 2004-2014 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Jerry Hoogenboom <J.Hoogenboom@LUMC.nl>
 *               Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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
 * along with LOVD.  If not, see <http://www.gnu.org/licenses/>.
 *
 *************/

// Don't allow direct access.
if (!defined('ROOT_PATH')) {
    exit;
}





function lovd_getLRGbyGeneSymbol ($sGeneSymbol)
{
    // Get LRG reference sequence
    preg_match('/(LRG_\d+)\s+' . $sGeneSymbol . '/', implode(' ', lovd_php_file('http://www.lovd.nl/mirrors/lrg/LRG_list.txt')), $aMatches);
    if(!empty($aMatches)) {
        return $aMatches[1];
    }
    return false;
}





function lovd_getNGbyGeneSymbol ($sGeneSymbol)
{
    preg_match('/^' . $sGeneSymbol . '\s+(NG_\d+\.\d+)/m', implode("\n", lovd_php_file('http://www.lovd.nl/mirrors/ncbi/NG_list.txt')), $aMatches);
    if (!empty($aMatches)) {
        return $aMatches[1];
    }
    return false;
}





function lovd_getGeneInfoFromHGNC ($sHgncId, $bRecursion = false)
{
    // Uses the HGNC API to search and retrieve gene information from the HGNC
    // website. The first argument can be an HGNC accession number or an HGNC
    // approved gene symbol. The results will be returned as an associative
    // array. If $bRecursion == true, this function automatically handles
    // deprecated HGNC entries and returns the information with the correct gene
    // symbol. On error, this function calls lovd_errorAdd if inc-lib-form.php
    // was included. It always returns false on failure.

    // 2014-01-13; 3.0-09; Tired of problems when their URL change and they don't announce it properly; rewrite to use their new REST service.
    $sURL = 'http://rest.genenames.org/search/';
    if (ctype_digit($sHgncId)) {
        $sURL .= 'hgnc_id/';
    } else {
        $sURL .= 'symbol/';
    }
    $sURL .= $sHgncId;
    $nHGNCID = 0;
    $aOutput = lovd_php_file($sURL, false, '', 'Accept: application/json');
    if ($aOutput && $aOutput = json_decode(implode('', $aOutput), true)) {
        if (!empty($aOutput['response']['numFound'])) {
            // 2014-08-06; 3.0-11; HGNC *again* changed their output, and once again we need to adapt quickly.
            $nHGNCID = preg_replace('/[^0-9]+/', '', $aOutput['response']['docs'][0]['hgnc_id']);
        } else {
            // Not found, previous symbol of...?
            $sURL = str_replace('/symbol/', '/prev_symbol/', $sURL);
            $aOutput = lovd_php_file($sURL, false, '', 'Accept: application/json');
            if ($aOutput && $aOutput = json_decode(implode('', $aOutput), true)) {
                if (!empty($aOutput['response']['numFound'])) {
                    if ($aOutput['response']['numFound'] == 1 && $bRecursion) {
                        // 2014-08-06; 3.0-11; HGNC *again* changed their output, and once again we need to adapt quickly.
                        $nHGNCID = preg_replace('/[^0-9]+/', '', $aOutput['response']['docs'][0]['hgnc_id']);
                        return lovd_getGeneInfoFromHGNC ($nHGNCID, $bRecursion);
                    } elseif (function_exists('lovd_errorAdd')) {
                        $sSymbols = '';
                        for ($i = 0; $i < $aOutput['response']['numFound']; $i ++) {
                            $sSymbols .= (!$i? '' : ($i == ($aOutput['response']['numFound'] - 1)? ' or ' : ', ')) . $aOutput['response']['docs'][$i]['symbol'];
                        }
                        lovd_errorAdd('hgnc_id', 'Entry ' . htmlspecialchars($sHgncId) . ' is deprecated according to the HGNC, please use ' . $sSymbols . '.');
                    }
                    return false;
                } else {
                    // Not found, maybe it's an alias?
                    $sURL = str_replace('/prev_symbol/', '/alias_symbol/', $sURL);
                    $aOutput = lovd_php_file($sURL, false, '', 'Accept: application/json');
                    if ($aOutput && $aOutput = json_decode(implode('', $aOutput), true)) {
                        if (!empty($aOutput['response']['numFound'])) {
                            if ($aOutput['response']['numFound'] == 1 && $bRecursion) {
                                // 2014-08-06; 3.0-11; HGNC *again* changed their output, and once again we need to adapt quickly.
                                $nHGNCID = preg_replace('/[^0-9]+/', '', $aOutput['response']['docs'][0]['hgnc_id']);
                                return lovd_getGeneInfoFromHGNC ($nHGNCID, $bRecursion);
                            } elseif (function_exists('lovd_errorAdd')) {
                                $sSymbols = '';
                                for ($i = 0; $i < $aOutput['response']['numFound']; $i ++) {
                                    $sSymbols .= (!$i? '' : ($i == ($aOutput['response']['numFound'] - 1)? ' or ' : ', ')) . $aOutput['response']['docs'][$i]['symbol'];
                                }
                                lovd_errorAdd('hgnc_id', 'Entry ' . htmlspecialchars($sHgncId) . ' was not found, perhaps you are referring to ' . $sSymbols . '.');
                            }
                            return false;
                        }
                    }
                }
            }
        }
    } else {
        if (function_exists('lovd_errorAdd')) {
            lovd_errorAdd('', 'Couldn\'t search for gene, probably because the HGNC is having website or database problems. Please try again later.');
        }
        return false;
    }

    if (!$nHGNCID) {
        if (function_exists('lovd_errorAdd')) {
            lovd_errorAdd('hgnc_id', 'Entry ' . htmlspecialchars($sHgncId) . ' was not found in the HGNC database.');
        }
        return false;
    }



    // Now that we have an ID, fetch the data. Use HGNC's fetch API.
    $aOutput = lovd_php_file('http://rest.genenames.org/fetch/hgnc_id/' . $nHGNCID, false, '', 'Accept: application/json');
    if ($aOutput && $aOutput = json_decode(implode('', $aOutput), true)) {
        if (!empty($aOutput['response']['numFound'])) {
            $aGene = $aOutput['response']['docs'][0];
        } else {
            if (function_exists('lovd_errorAdd')) {
                lovd_errorAdd('hgnc_id', 'Couldn\'t fetch gene information, even though search results were returned. This might be a problem at the side of the HGNC, or a bug in LOVD. Please try again later.');
            }
            return false;
        }
    } else {
        if (function_exists('lovd_errorAdd')) {
            lovd_errorAdd('', 'Couldn\'t get gene information, probably because the HGNC is having website or database problems. Please try again later.');
        }
        return false;
    }




    // Check returned entry.
    // We ignore genes from the following locus groups:
    $aBadLocusGroups =
        array(
            'phenotype', // No transcripts.
            'withdrawn', // Do not exist anymore.
        );

    // We ignore genes from the following locus types (most of these are in group "other"):
    $aBadLocusTypes =
        array(
            'endogenous retrovirus',  // From group "other", none of them work (verified).
            'fragile site',           // From group "other", none of them work (verified).
            'immunoglobulin gene',    // From group "other", none of them work (verified).
            'region',                 // From group "other", none of them work (verified).
            'transposable element',   // From group "other", none of them work (verified).
            'unknown',                // From group "other", none of them work (verified).
            'virus integration site', // From group "other", none of them work (verified).
            'immunoglobulin pseudogene', // From group "pseudogene", none of them work (verified).
        );

    if ($aGene['status'] != 'Approved') {
        if (function_exists('lovd_errorAdd')) {
            lovd_errorAdd('hgnc_id', 'Entry ' . htmlspecialchars($sHgncId) . ' is not an approved gene symbol according to the HGNC database.');
        }
        return false;
    } elseif ($aGene['location'] == 'reserved') {
        if (function_exists('lovd_errorAdd')) {
            lovd_errorAdd('hgnc_id', 'Entry ' . htmlspecialchars($sHgncId) . ' does not yet have a public association with a chromosomal location.');
        }
        return false;
    } elseif (in_array($aGene['locus_group'], $aBadLocusGroups)) {
        if (function_exists('lovd_errorAdd')) {
            lovd_errorAdd('hgnc_id', 'LOVD cannot process this type of gene entry ' . htmlspecialchars($sHgncId) . ' (Locus Group: ' . $aGene['locus_group'] . ').');
        }
        return false;
    } elseif (in_array($aGene['locus_type'], $aBadLocusTypes)) {
        if (function_exists('lovd_errorAdd')) {
            lovd_errorAdd('hgnc_id', 'LOVD cannot process this type of gene entry ' . htmlspecialchars($sHgncId) . ' (Locus Type: ' . $aGene['locus_type'] . ').');
        }
        return false;
    }

    foreach (array('omim_id') as $sCol) {
        // Columns presented as arrays (new?), but should contain just one value.
        // 2014-12-23; 3.0-13; Can also not be defined.
        if (!isset($aGene[$sCol])) {
            $aGene[$sCol] = '';
        } elseif (is_array($aGene[$sCol]) && count($aGene[$sCol]) == 1) {
            $aGene[$sCol] = $aGene[$sCol][0];
        }
    }

    // 2014-08-06; 3.0-11; HGNC ID suddenly got a prefix, removing the prefix.
    $aGene['hgnc_id'] = preg_replace('/[^0-9]+/', '', $aGene['hgnc_id']);

    // 2014-12-23; 3.0-13; Split "location" to "chromosome" and "chrom_band", which makes it easier to create this gene in the database.
    if ($aGene['location'] == 'mitochondria') {
        $aGene['chromosome'] = 'M';
        $aGene['chrom_band'] = '';
    } else {
        preg_match('/^(\d{1,2}|[XY])(.*)$/', $aGene['location'], $aMatches);
        $aGene['chromosome'] = $aMatches[1];
        $aGene['chrom_band'] = $aMatches[2];
    }

    return $aGene;
}





function lovd_getGeneInfoFromHgncOld ($sHgncId, $aCols, $bRecursion = false)
{
    // Downloads gene information from the HGNC website. The specified columns will be retrieved.
    // The first argument can be an HGNC accession number, an HGNC approved gene symbol, or boolean true to retrieve ALL genes.
    // The results will be returned as an associative array; in case all genes have been loaded an array of arrays is returned with gene symbols as keys.
    // If $bRecursion == true, this function automatically handles deprecated HGNC entries.
    // On error, this function calls lovd_errorAdd if inc-lib-form.php was included. It always returns false on failure.

    // Process columns.
    $aColumns = $aCols; // $aColumns will be extended with more information, whereas $aCols is used for the return value and as such should not be changed.
    $sColumns = '';
    foreach ($aCols as $sColumn) {
        $sColumns .= 'col=' . $sColumn . '&';
    }

    // Make sure we request the right data.
    if ($sHgncId === true) {
        // Boolean true; return bulk data.
        $sWhere = '';

        // Using approved symbols as array keys, so we need to get them from the HGNC.
        if (!in_array('gd_app_sym', $aCols)) {
            $sColumns .= 'col=gd_app_sym&';
            $aColumns[] = 'gd_app_sym';
        }
    } else {
        if (ctype_digit($sHgncId)) {
            // HGNC database ID.
            $sWhere = 'gd_hgnc_id%3D' . $sHgncId;
        } else {
            // FIXME; implement proper check on gene symbol.
            // Gene symbol; also match SYMBOL~withdrawn to be able to use a deprecated symbol as search key.
            $sWhere = rawurlencode('gd_app_sym IN ("' . $sHgncId . '", "' . $sHgncId . '~withdrawn")');
        }

        // We also surely need gd_app_name to check for and handle withdrawn or deprecated entries.
        if (!in_array('gd_app_name', $aCols)) {
            $sColumns .= 'col=gd_app_name&';
            $aColumns[] = 'gd_app_name';
        }
    }
    $aHgncFile = lovd_php_file('http://www.genenames.org/cgi-bin/download?' . $sColumns . 'status_opt=2&where=' . $sWhere . '&order_by=gd_app_sym_sort&limit=&format=text&submit=submit');

    // If the HGNC is having database problems, we get an HTML page.
    if (empty($aHgncFile) || stripos(implode($aHgncFile), '<html') !== false) {
        if (function_exists('lovd_errorAdd')) {
            lovd_errorAdd('', 'Couldn\'t get gene information, probably because the HGNC is having database problems.');
        }
        return false;
    }

    if ($sHgncId === true) {
        // Got bulk data.

        $aHGNCgenes = array();
        array_shift($aHgncFile);
        foreach ($aHgncFile as $sGene) {
            $aGene = array_combine($aColumns, explode("\t", $sGene));
            $sSymbol = str_replace('~withdrawn', '', $aGene['gd_app_sym']);
            if (!empty($aHGNCgenes[$sSymbol]) && $sSymbol != $aGene['gd_app_sym']) {
                // Symbol has been deprecated and then reassigned to another gene, don't overwrite that one.
                continue;
            }
            $aHGNCgenes[$sSymbol] = $aGene;
            foreach (array_diff($aColumns, $aCols) as $sUnwantedColumn) {
                // Don't return columns the caller hasn't asked for.
                unset($aHGNCgenes[$sSymbol][$sUnwantedColumn]);
            }
        }
        return $aHGNCgenes;
    }

    // Requested single entry.
    if (isset($aHgncFile[1])) {
        // Looks like we've got valid data here.
        $aGene = array_combine($aColumns, explode("\t", $aHgncFile[1]));
        // We might encorporate one or more of these locus types excludes later, so that we can throw an error without first calling mutalyzer a number of times.
        //$aBadLocusTypes = array('RNA, cluster', 'RNA, transfer', 'RNA, ribosomal', 'transposable element', 'virus integration site', 'phenotype only', 'unknown', 'region', 'complex locus constituent', 'endogenous retrovirus', 'fragile site', 'T cell receptor gene', 'T cell receptor pseudogene');
        $aBadLocusTypes = array('phenotype only');
        if ($aGene['gd_app_name'] == 'entry withdrawn') {
            if (function_exists('lovd_errorAdd')) {
                lovd_errorAdd('hgnc_id', 'Entry ' . htmlspecialchars($sHgncId) . ' no longer exists in the HGNC database.');
            }
            return false;
        } elseif (preg_match('/^symbol withdrawn, see (.+)$/', $aGene['gd_app_name'], $aRegs)) {
            if ($bRecursion) {
                return lovd_getGeneInfoFromHgncOld($aRegs[1], $aCols);
            } elseif (function_exists('lovd_errorAdd')) {
                lovd_errorAdd('hgnc_id', 'Entry ' . htmlspecialchars($sHgncId) . ' is deprecated according to the HGNC, please use ' . $aRegs[1] . '.');
            }
            return false;
        } elseif (in_array('gd_pub_chrom_map', $aCols) && $aGene['gd_pub_chrom_map'] == 'reserved') {
            if (function_exists('lovd_errorAdd')) {
                lovd_errorAdd('hgnc_id', 'Entry ' . htmlspecialchars($sHgncId) . ' does not yet have a public association with a chromosomal location');
            }
            return false;
        } elseif (in_array('gd_locus_type', $aCols) && in_array($aGene['gd_locus_type'], $aBadLocusTypes)) {
            if (function_exists('lovd_errorAdd')) {
                lovd_errorAdd('hgnc_id', 'LOVD cannot process this type of gene entry ' . htmlspecialchars($sHgncId) . ' (Locus Type: ' . $aGene['gd_locus_type'] . ').');
            }
            return false;
        }

        foreach (array_diff($aColumns, $aCols) as $sUnwantedColumn) {
            // Don't return columns the caller hasn't asked for.
            unset($aGene[$sUnwantedColumn]);
        }

        return $aGene;

    } elseif (function_exists('lovd_errorAdd')) {
        // No math found, start looking for alias. We could have included an OR in the original search, but I am not
        // sure if that would maybe have other genes pop up while the official gene is then ignored.
        // We only do this search, if we can report if of course (hence the check for lovd_errorAdd()).
        // Replace WHERE.
        $sWhere = rawurlencode('CONCAT(" ", gd_aliases, ",") LIKE "% ' . $sHgncId . ',%"');
        $aHgncFile = lovd_php_file('http://www.genenames.org/cgi-bin/download?' . $sColumns . 'status_opt=2&where=' . $sWhere . '&order_by=gd_app_sym_sort&limit=&format=text&submit=submit');

        // Just quick check if we have a match now...
        if (!empty($aHgncFile) && stripos(implode($aHgncFile), '<html') === false) {
            unset($aHgncFile[0]);
            $sSymbolList = '';
            foreach ($aHgncFile as $sLine) {
                $aGene = array_combine($aColumns, explode("\t", $sLine));
                $sSymbolList .= (!$sSymbolList? '' : ', ') . $aGene['gd_app_sym'];
            }
            if ($sSymbolList) {
                // "Prettify" the output by replacing the last , by an "or".
                $sSymbolList = preg_replace('/, ([^ ]+)$/', " or $1", $sSymbolList);
                lovd_errorAdd('hgnc_id', 'Entry ' . htmlspecialchars($sHgncId) . ' was not found, perhaps you are referring to ' . $sSymbolList . '?');
            } else {
                lovd_errorAdd('hgnc_id', 'Entry ' . htmlspecialchars($sHgncId) . ' was not found in the HGNC database.');
            }
        }
    }
    return false;
}
?>
