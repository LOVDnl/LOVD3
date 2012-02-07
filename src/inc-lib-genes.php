<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-01-25
 * Modified    : 2012-02-07
 * For LOVD    : 3.0-beta-02
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
    // If $bRecursion == true, this function automatically handles deprecated HGNC entries.
    // On error, this function calls lovd_errorAdd if inc-lib-form.php was included. It always returns false on failure.
    
    // Process columns. We need the gd_app_name column to check for withdrawn or deprecated entries.
    $sColumns = '';
    $nGdAppNameKey = -1;
    $bReturnGdAppName = false;
    foreach (array_values($aCols) as $nKey => $sColumn) {
        $sColumns .= 'col=' . $sColumn . '&';
        if ($sColumn == 'gd_app_name') {
            $nGdAppNameKey = $nKey;
            $bReturnGdAppName = true;
        }
    }
    if ($nGdAppNameKey == -1) {
        $sColumns .= 'col=gd_app_name&';
        $nGdAppNameKey = $nKey + 1;
    }
    
    // Request the data.
    if (ctype_digit($sHgncId)) {
        $sWhere = 'gd_hgnc_id%3D' . $sHgncId;
    } else {
        $sWhere = 'gd_app_sym%3D%22' . $sHgncId . '%22';
    }
    $aHgncFile = lovd_php_file('http://www.genenames.org/cgi-bin/hgnc_downloads.cgi?' . $sColumns . 'status_opt=2&where=' . $sWhere . '&order_by=gd_app_sym_sort&limit=&format=text&submit=submit');
    
    // If the HGNC is having database problems, we get an HTML page.
    if (empty($aHgncFile) || stripos(implode("\n", $aHgncFile), '</html>') !== FALSE) {
        if (function_exists('lovd_errorAdd')) {
            lovd_errorAdd('', 'Couldn\'t get gene information, probably because the HGNC is having database problems.');
        }
        return false;
    }
    
    if (isset($aHgncFile['1'])) {
        // Looks like we've got valid data here.
        $aHgncFile['1'] = explode("\t", $aHgncFile['1']);
        if ($aHgncFile['1'][$nGdAppNameKey] == 'entry withdrawn') {
            if (function_exists('lovd_errorAdd')) {
                lovd_errorAdd('', 'Entry ' . $sHgncId . ' no longer exists in the HGNC database.');
            }
            return false;
        } elseif (preg_match('/^symbol withdrawn, see (.+)$/', $aHgncFile['1'][$nGdAppNameKey], $aRegs)) {
            if ($bRecursion) {
                return getGeneInfoFromHgnc($aRegs[1], $aCols);
            }
            elseif (function_exists('lovd_errorAdd')) {
                lovd_errorAdd('', 'Entry ' . $sHgncId . ' is deprecated, please use ' . $aRegs[1]);
            }
            return false;
        } elseif (in_array('gd_pub_chrom_map', $aCols) && $aCols[array_search('gd_pub_chrom_map', $aCols)] == 'reserved') {
            if (function_exists('lovd_errorAdd')) {
                lovd_errorAdd('hgnc_id', 'Entry ' . $_POST['hgnc_id'] . ' does not yet have a public association with a chromosomal location');
            }
            return false;
        }
        
        // Unset the gd_app_name column if the caller has not asked for it.
        if (!$bReturnGdAppName) {
            unset($aHgncFile['1'][$nGdAppNameKey]);
        }
        
        return $aHgncFile['1'];
    } elseif (function_exists('lovd_errorAdd')) {
        lovd_errorAdd('', 'Entry was not found in the HGNC database.');
    }
    return false;
}





function lovd_addAllDefaultCustomColumnsForGene ($sGene, $sUser = 'USE_AUTH')
{
    // This function enables all custom columns that are standard or HGVS required for the given gene.
    // The given user will be the creator of the entries in TABLE_SHARED COLS and (if needed) in TABLE_ACTIVE_COLS.
    
    // Use $_AUTH['id'] as the user by default. This still allows the caller to explicitly use $sUser = null when needed.
    global $_AUTH, $_DB;
    if ($sUser == 'USE_AUTH') {
        $sUser = $_AUTH['id'];
    }
    
    // Get a list of the columns in TABLE_VARIANTS_ON_TRANSCRIPTS.
    $qAddedCustomCols = $_DB->query('DESCRIBE ' . TABLE_VARIANTS_ON_TRANSCRIPTS);
    while ($aCol = $qAddedCustomCols->fetchAssoc()) {
        $aAdded[] = $aCol['Field'];
    }
    
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





////////////////////////////////////////////////////////////////////////////////
// FIXME; The following two functions (getNgMapping and getGeneMetaByGeneSymbol) are only used by upload.php. They may be removed when upload.php is cleant up.

function getNgMapping()
{
	$aLines = lovd_php_file('http://www.lovd.nl/mirrors/ncbi/NG_list.txt');
	$zNgMapping = array();
	foreach($aLines as $line)
	{
		if (preg_match('/(\w+)\s+(NG_\d+\.\d+)/', $line, $aMatches))
	    {
	    	$zNgMapping[$aMatches[1]] = $aMatches[2];
	    }
	}
	
	return $zNgMapping;
}





function getGeneMetaByGeneSymbol($sGeneSymbol, $sAccession='')
{
	// get gene infor from www.genenames.org
    if (ctype_digit($sGeneSymbol)) 
    {
    	$sWhere = 'gd_hgnc_id%3D' . $sGeneSymbol;
    } 
    else 
    {
    	$sWhere = 'gd_app_sym%3D%22' . $sGeneSymbol . '%22';
    }
    
    $aHgncFile = lovd_php_file('http://www.genenames.org/cgi-bin/hgnc_downloads.cgi?col=gd_hgnc_id&col=gd_app_sym&col=gd_app_name&col=gd_pub_chrom_map&col=gd_pub_eg_id&col=md_mim_id&status_opt=2&where=' . $sWhere . '&order_by=gd_app_sym_sort&limit=&format=text&submit=submit', false, false);
    
    if (!isset($aHgncFile['1']) && $sAccession) 
    {
        // call mutalyzer for gene name based on accesssion id.
        global $_MutalyzerWS;
        $sNewGeneSymbol = $_MutalyzerWS->moduleCall(
            'getGeneName', 
            array('accno' => $sAccession, 'build' => 'hg19'));
        
        if (!empty($sNewGeneSymbol) && $sNewGeneSymbol != $sGeneSymbol)
        {
        	print("Found a different gene symbol from Mutalyzer based on accession number. New = '$sNewGeneSymbol'. Old = '$sGeneSymbol'.<br/>");
            $sWhere = 'gd_app_sym%3D%22' . $sNewGeneSymbol . '%22';
            $aHgncFile = lovd_php_file('http://www.genenames.org/cgi-bin/hgnc_downloads.cgi?col=gd_hgnc_id&col=gd_app_sym&col=gd_app_name&col=gd_pub_chrom_map&col=gd_pub_eg_id&col=md_mim_id&status_opt=2&where=' . $sWhere . '&order_by=gd_app_sym_sort&limit=&format=text&submit=submit', false, false);
        }
    }
    
    if (isset($aHgncFile['1'])) 
    {
    	list($sHgncID, $sSymbol, $sGeneName, $sChromLocation, $sEntrez, $sOmim) = explode("\t", $aHgncFile['1']);
    	list($sEntrez, $sOmim) = array_map('trim', array($sEntrez, $sOmim));
                        
        if (preg_match('/^(\d{1,2}|[XY])(.*)$/', $sChromLocation, $aMatches))
        {
        	$sChromosome = $aMatches[1];
            $sChromBand = $aMatches[2];
        }
        else
        {
        	$sChromosome = $sChromLocation;
            $sChromBand = $sChromLocation;
        }
                
        if ($sGeneName == 'entry withdrawn') 
        {
            throw new Exception("Gene \"$sGeneSymbol\" no longer exists in the HGNC database.");
        } 
        else if (preg_match('/^symbol withdrawn, see (.+)$/', $sGeneName, $aRegs)) 
        {
            throw new Exception("Gene \"$sGeneSymbol\" is deprecated, please use " . $aRegs[1] );
        }
    }
    else
    {
        throw new Exception("Gene \"$sGeneSymbol\" was not found in the HGNC database.");
    }
    
    return array(
        'id' => $sSymbol,
        'name' => $sGeneName,
        'chromosome' => $sChromosome,
        'chrom_band' => $sChromBand,
        'id_hgnc' => $sHgncID,
        'id_entrez' => $sEntrez,
        'id_omim' => $sOmim
    );
}
?>