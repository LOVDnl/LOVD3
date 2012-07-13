<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-06-10
 * Modified    : 2012-07-10
 * For LOVD    : 3.0-beta-07
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

$_GET['format'] = 'text/plain'; // To make sure all possible error functions output text.
define('FORMAT_ALLOW_TEXTPLAIN', true);
define('ROOT_PATH', './');
require ROOT_PATH . 'inc-init.php';

//header('Content-type: text/plain; charset=UTF-8');





// None of the URLs accept an ACTION, all require at least $_PE[1].
if (ACTION || PATH_COUNT < 2) {
    exit;
}





if ($_PE[1] == 'all' && (empty($_PE[2]) || $_PE[2] == 'mine')) {
    // URL: /download/all
    // URL: /download/all/mine
    // Download all data from the database, possibly restricted by ownership.

    if (empty($_PE[2])) {
        $nID = 0;
        lovd_requireAuth(LEVEL_MANAGER);
    } else {
        $nID = $_AUTH['id'];
        lovd_requireAuth();
    }

    // If we get here, we can print the header already.
    header('Content-Disposition: attachment; filename="LOVD_' . ($nID? 'owned_data' : 'full_download') . '_' . date('Y-m-d_H.i.s') . '.txt"');
    header('Pragma: public');
    print('### LOVD-version ' . lovd_calculateVersion($_SETT['system']['version']) . ' ### ' . ($nID? 'Owned' : 'Full') . ' data download ### To import, do not remove this header ###' . "\r\n");
    if ($nID) {
        print('## Filter: (created_by = ' . $nID . ' || owned_by = ' . $nID . ')' . "\r\n");
    }
    print('# charset = UTF-8' . "\r\n\r\n");



    // Prepare file creation by defining headers, columns and filters.
    // All data types have same settings: optional ownership filter, no columns hidden.
    $aDataTypeSettings =
        array(
            'comments' => array(),
            'data' => array(),
            'filters' => array(),
            'hide_columns' => array(),
            'prefetch' => false,
            'settings' => array(),
        );
    if ($nID) {
        // We need to prefetch the data to be able to filter genes etc, which are just for reference for this type of download.
        $aDataTypeSettings['prefetch'] = true;
    }
    $aObjects =
         array(
             'Genes' => $aDataTypeSettings,
             'Transcripts' => $aDataTypeSettings,
             'Diseases' => $aDataTypeSettings,
             'Gen2Dis' => array_merge($aDataTypeSettings, array('label' => 'Genes_To_Diseases', 'order_by' => 'geneid, diseaseid')),
             'Individuals' => $aDataTypeSettings,
             'Ind2Dis' => array_merge($aDataTypeSettings, array('label' => 'Individuals_To_Diseases', 'order_by' => 'individualid, diseaseid')),
             'Phenotypes'  => $aDataTypeSettings,
             'Screenings' => $aDataTypeSettings,
             'Scr2Gene' => array_merge($aDataTypeSettings, array('label' => 'Screenings_To_Genes', 'order_by' => 'screeningid, geneid')),
             'Variants' => array_merge($aDataTypeSettings, array('label' => 'Variants_On_Genome')),
             'Variants_On_Transcripts' => $aDataTypeSettings,
             'Scr2Var' => array_merge($aDataTypeSettings, array('label' => 'Screenings_To_Variants', 'order_by' => 'screeningid, variantid')),
         );
    // Apply filters and filter order, for user-specific download.
    if ($nID) {
        // In user-specific download, we don't care about the relationship between genes and diseases.
        unset($aObjects['Gen2Dis']);
        // Change the order of filtering, so we can filter the data that's just for reference last.
        $aObjectsToBeFiltered =
            array(
                'Individuals',
                'Ind2Dis',
                'Diseases',
                'Phenotypes',
                'Screenings',
                'Scr2Gene',
                'Variants',
                'Variants_On_Transcripts',
                'Transcripts',
                'Genes',
                'Scr2Var',
            );
        $aObjects['Individuals']['filters']['owner'] = $nID;
        $aObjects['Individuals']['filter_other']['Ind2Dis']['individualid'] = 'id';
        $aObjects['Ind2Dis']['filter_other']['Diseases']['id'] = 'diseaseid';
        $aObjects['Diseases']['comments'][] = 'For reference only, not part of the selected data set';
        $aObjects['Diseases']['settings'][] = 'ignore_for_import';
        $aObjects['Diseases']['hide_columns'] =
            array(
                'created_by', 'created_date', 'edited_by', 'edited_date',
            );
        $aObjects['Phenotypes']['filters']['owner'] = $nID;
        $aObjects['Screenings']['filters']['owner'] = $nID;
        $aObjects['Screenings']['filter_other']['Scr2Gene']['screeningid'] = 'id';
        $aObjects['Screenings']['filter_other']['Scr2Var']['screeningid'] = 'id';
        $aObjects['Scr2Gene']['filter_other']['Genes']['id'] = 'geneid';
        $aObjects['Variants']['filters']['owner'] = $nID;
        $aObjects['Variants']['filter_other']['Variants_On_Transcripts']['id'] = 'id';
        $aObjects['Variants']['filter_other']['Scr2Var']['variantid'] = 'id';
        $aObjects['Variants_On_Transcripts']['filter_other']['Transcripts']['id'] = 'transcriptid';
        $aObjects['Transcripts']['comments'][] = 'For reference only, not part of the selected data set';
        $aObjects['Transcripts']['settings'][] = 'ignore_for_import';
        $aObjects['Transcripts']['filter_other']['Genes']['id'] = 'geneid';
        $aObjects['Transcripts']['hide_columns'] =
            array(
                'id_mutalyzer', 'position_c_mrna_start', 'position_c_mrna_end', 'position_c_cds_end',
                'position_g_mrna_start', 'position_g_mrna_end', 'created_by', 'created_date', 'edited_by',
                'edited_date',
            );
        $aObjects['Genes']['comments'][] = 'For reference only, not part of the selected data set';
        $aObjects['Genes']['settings'][] = 'ignore_for_import';
        $aObjects['Genes']['hide_columns'] =
            array(
                'imprinting', 'refseq_genomic', 'refseq_UD', 'reference', 'url_homepage', 'url_external',
                'allow_download', 'allow_index_wiki', 'show_hgmd', 'show_genecards', 'show_genetests',
                'note_index', 'note_listing', 'refseq', 'refseq_url', 'disclaimer', 'disclaimer_text',
                'header', 'header_align', 'footer', 'footer_align', 'created_by', 'created_date',
                'edited_by', 'edited_date', 'updated_by', 'updated_date',
            );
    }
}





if (empty($aObjects) || !is_array($aObjects)) {
    // Objects and file format has not been defined, exit.
    exit;
}





// Now loop through the filters, and run queries if caching necessary.
if (empty($aObjectsToBeFiltered) || count($aObjectsToBeFiltered) != count($aObjects)) {
    // If there is no different filtering order specified, we will use the default order.
    $aObjectsToBeFiltered = array_keys($aObjects);
}
foreach ($aObjectsToBeFiltered as $sObject) {
    $aSettings = $aObjects[$sObject];
    $sWHERE = '';
    $aArgs = array();
    if ($aSettings['filters']) {
        // The fact that we have a filter, may affect other data types.
        $i = 0;
        foreach ($aSettings['filters'] as $sFilter => $Value) {
            $sWHERE .= (!$i++? '' : ' AND ');
            switch ($sFilter) {
                case 'owner':
                    // Data ownership is defined by the created_by and owned_by fields.
                    $sWHERE .= '(created_by = ? OR owned_by = ?)';
                    $aArgs[] = $Value;
                    $aArgs[] = $Value;
                    break;
                default:
                    // By default, we'll assume that this filter is a certain column.
                    $sWHERE .= '`' . $sFilter . '` ';
                    if (is_array($Value)) {
                        $sWHERE .= 'IN (?' . str_repeat(', ?', count($Value) - 1) . ')';
                        $aArgs = array_merge($aArgs, $Value);
                    } else {
                        $sWHERE .= '= ?';
                        $aArgs[] = $Value;
                    }
            }
        }
    }

    // Build the query.
    // Ugly hack: we will change $sTable for the VOT to a string that joins VOG such that we can apply filters.
    if ($sObject == 'Variants_On_Transcripts') {
        $sTable = TABLE_VARIANTS_ON_TRANSCRIPTS . ' INNER JOIN ' . TABLE_VARIANTS . ' USING (id)';
    } else {
        $sTable = @constant('TABLE_' . strtoupper($sObject));
        if (!$sTable) {
            die('Error: Could not find data table for object ' . $sObject . "\r\n");
        }
    }
    // Store in data array.
    $aObjects[$sObject]['query'] = 'SELECT * FROM ' . $sTable . (!$sWHERE? '' : ' WHERE ' . $sWHERE) . ' ORDER BY ' . (empty($aSettings['order_by'])? 'id' : $aSettings['order_by']);
    $aObjects[$sObject]['args']  = $aArgs;

    // If prefetch is requested, request data right here.
    if ($aSettings['prefetch'] || isset($aSettings['filter_other'])) {
        $aObjects[$sObject]['data'] = $_DB->query($aObjects[$sObject]['query'], $aObjects[$sObject]['args'])->fetchAllAssoc();

        // Check if we, now that we have the data fetched, need to apply other filters,
        if (isset($aSettings['filter_other']) && is_array($aSettings['filter_other'])) {
            foreach ($aSettings['filter_other'] as $sObjectToFilter => $aFiltersToRun) {
                // Check if object that needs to be filtered actually exists or not.
                if (isset($aObjects[$sObjectToFilter])) {
                    // Make sure this object has a functional 'filters' array.
                    if (!isset($aObjects[$sObjectToFilter]['filters']) || !is_array($aObjects[$sObjectToFilter]['filters'])) {
                        $aObjects[$sObjectToFilter]['filters'] = array();
                    }
                    // Now loop the list of filters to apply.
                    foreach ($aFiltersToRun as $sColumnToFilter => $sColToMatch) {
                        // Now loop the data to collect all the $sValueToFilter data.
                        $aValuesToFilter = array();
                        foreach ($aObjects[$sObject]['data'] as $zData) {
                            $aValuesToFilter[] = $zData[$sColToMatch];
                        }
                        $aObjects[$sObjectToFilter]['filters'][$sColumnToFilter] = array_unique($aValuesToFilter);
                    }
                }
            }
        }
    }
//    $aObjects[$sObject]['ids'] = array();
//    while ($z = $q->fetchAssoc()) {
//        $aObjects[$sObject]['ids'][] = $z['id'];
}





// Now, query the database and print, or just print the data (if already prefetched).
foreach ($aObjects as $sObject => $aSettings) {
    print('## ' . (empty($aSettings['label'])? $sObject : $aSettings['label']) . ' ## Do not remove this header ##' . "\r\n");

    // If not prefetched, download the data here. If we do a fetchAll() we can easily get the count and we don't need to do a describe.
    // So saving two queries, and it's easier code, at the cost of additional memory usage.
    if (empty($aSettings['data'])) {
        $aSettings['data'] = $_DB->query($aObjects[$sObject]['query'], $aObjects[$sObject]['args'])->fetchAllAssoc();
    }

    // Print comments.
    if (!empty($aSettings['comments'])) {
        print('## ' . implode("\n" . '## ', $aSettings['comments']) . "\n");
    }

    // First, print counts. This is as information for the user, but also it shows easily when there is no data to show.
    $nCount = count($aSettings['data']);
    print('## Count = ' . $nCount . "\r\n");
//    // Indicates this data has been printed.
//    $aObjects[$sObject]['count'] = $nCount;

    // Print settings.
    if (!empty($aSettings['settings'])) {
        print('# ' . implode("\n" . '# ', $aSettings['settings']) . "\n");
    }

    if (!$nCount) {
        // Nothing to print...
        print("\r\n\r\n");
        continue;
    }





    // Get used columns, so we can print the headers.
    // FIXME; Apply some sorting mechanism. Based on average order?
    $aColumns = array_keys($aSettings['data'][0]);

    // Print headers.
    foreach ($aColumns as $key => $sCol) {
        if (!in_array($sCol, $aSettings['hide_columns'])) {
            print((!$key? '' : "\t") . '"{{' . $sCol . '}}"');
        }
    }
    print("\r\n");

    // Fetch and print the data.
    foreach ($aSettings['data'] as $z) {
        // Quote data.
        $z = array_map('addslashes', $z);

        foreach ($aColumns as $key => $sCol) {
            if (!in_array($sCol, $aSettings['hide_columns'])) {
                // Replace line endings and tabs (they should not be there but oh well), so they don't cause problems with importing.
                print(($key? "\t" : '') . '"' . str_replace(array("\r\n", "\r", "\n", "\t"), array('\r\n', '\r', '\n', '\t'), $z[$sCol]) . '"');
            }
        }
        print("\r\n");
    }
    print("\r\n\r\n");
}
?>
