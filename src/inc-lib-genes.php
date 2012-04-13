<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-01-25
 * Modified    : 2012-04-10
 * For LOVD    : 3.0-beta-04
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Jerry Hoogenboom <J.Hoogenboom@LUMC.nl>
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
    preg_match('/' . $sGeneSymbol . '\s+(NG_\d+\.\d+)/', implode(' ', lovd_php_file('http://www.lovd.nl/mirrors/ncbi/NG_list.txt')), $aMatches);
    if (!empty($aMatches)) {
        return $aMatches[1];
    }
    return false;
}





function lovd_getGeneInfoFromHgnc ($sHgncId, $aCols, $bRecursion = false)
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
            $sWhere = 'gd_app_sym%20IN%28%22' . $sHgncId . '%22%2C%22' . $sHgncId . '%7Ewithdrawn%22%29';
        }

        // We also surely need gd_app_name to check for and handle withdrawn or deprecated entries.
        if (!in_array('gd_app_name', $aCols)) {
            $sColumns .= 'col=gd_app_name&';
            $aColumns[] = 'gd_app_name';
        }
    }
    $aHgncFile = lovd_php_file('http://www.genenames.org/cgi-bin/hgnc_downloads.cgi?' . $sColumns . 'status_opt=2&where=' . $sWhere . '&order_by=gd_app_sym_sort&limit=&format=text&submit=submit');

    // If the HGNC is having database problems, we get an HTML page.
    if (empty($aHgncFile) || stripos(implode($aHgncFile), '<html') !== FALSE) {
        if (function_exists('lovd_errorAdd')) {
            lovd_errorAdd('', 'Couldn\'t get gene information, probably because the HGNC is having database problems.');
        }
        return false;
    }
    
    if ($sHgncId === true) {
        // Got bulk data.

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
        if ($aGene['gd_app_name'] == 'entry withdrawn') {
            if (function_exists('lovd_errorAdd')) {
                lovd_errorAdd('', 'Entry ' . htmlspecialchars($sHgncId) . ' no longer exists in the HGNC database.');
            }
            return false;
        } elseif (preg_match('/^symbol withdrawn, see (.+)$/', $aGene['gd_app_name'], $aRegs)) {
            if ($bRecursion) {
                return lovd_getGeneInfoFromHgnc($aRegs[1], $aCols);
            } elseif (function_exists('lovd_errorAdd')) {
                lovd_errorAdd('', 'Entry ' . htmlspecialchars($sHgncId) . ' is deprecated, please use ' . $aRegs[1] . '.');
            }
            return false;
        } elseif (in_array('gd_pub_chrom_map', $aCols) && $aGene['gd_pub_chrom_map'] == 'reserved') {
            if (function_exists('lovd_errorAdd')) {
                lovd_errorAdd('hgnc_id', 'Entry ' . htmlspecialchars($sHgncId) . ' does not yet have a public association with a chromosomal location');
            }
            return false;
        }
        
        foreach (array_diff($aColumns, $aCols) as $sUnwantedColumn) {
            // Don't return columns the caller hasn't asked for.
            unset($aGene[$sUnwantedColumn]);
        }
        
        return $aGene;
    } elseif (function_exists('lovd_errorAdd')) {
        lovd_errorAdd('', 'Entry ' . htmlspecialchars($sHgncId) . ' was not found in the HGNC database.');
    }
    return false;
}





function lovd_addAllDefaultCustomColumnsForGene ($sGene, $bUseAuthUser = true)
{
    // This function enables all custom columns that are standard or HGVS required for the given gene.
    // If bUseAuthUser is set to false, user 0 ("LOVD") will be used for the created_by fields in TABLE_SHARED COLS and (if needed) in TABLE_ACTIVE_COLS.
    
    global $_AUTH, $_DB;
    if ($bUseAuthUser) {
        $sUser = $_AUTH['id'];
    } else {
        $sUser = 0;
    }
    
    // Get a list of the columns in TABLE_VARIANTS_ON_TRANSCRIPTS.
    $aAdded = $_DB->query('DESCRIBE ' . TABLE_VARIANTS_ON_TRANSCRIPTS)->fetchAllColumn();
    
    // Get a list of all columns that are standard or HGVS required.
    $qStandardCustomCols = $_DB->query('SELECT * FROM ' . TABLE_COLS . ' WHERE id LIKE "VariantOnTranscript/%" AND (standard = 1 OR hgvs = 1)');
    while ($aStandard = $qStandardCustomCols->fetchAssoc()) {
        if (!in_array($aStandard['id'], $aAdded)) {
            // The standard column is not present in TABLE_VARIANTS_ON_TRANSCRIPTS. Add it.
            $_DB->query('ALTER TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' ADD COLUMN `' . $aStandard['id'] . '` ' . stripslashes($aStandard['mysql_type']));
            $_DB->query('INSERT INTO ' . TABLE_ACTIVE_COLS . ' VALUES(?, ?, NOW())', array($aStandard['id'], $sUser));
        }
        
        // Add the standard column to the gene.
        $_DB->query('INSERT INTO ' . TABLE_SHARED_COLS . ' VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NULL, NULL)', array($sGene, $aStandard['id'], $aStandard['col_order'], $aStandard['width'], $aStandard['mandatory'], $aStandard['description_form'], $aStandard['description_legend_short'], $aStandard['description_legend_full'], $aStandard['select_options'], $aStandard['public_view'], $aStandard['public_add'], $sUser));
    }
}
?>
