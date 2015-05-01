<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-06-10
 * Modified    : 2015-05-01
 * For LOVD    : 3.0-14
 *
 * Copyright   : 2004-2015 Leiden University Medical Center; http://www.LUMC.nl/
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





if (($_PE[1] == 'all' && (empty($_PE[2]) || in_array($_PE[2], array('gene', 'mine', 'user')))) ||
    ($_PE[1] == 'columns' && PATH_COUNT <= 3)) {
    // URL: /download/all
    // URL: /download/all/gene/IVD
    // URL: /download/all/mine
    // URL: /download/all/user/00001
    // URL: /download/columns
    // URL: /download/columns/(VariantOnGenome|VariantOnTranscript|Individual|...)
    // Download data from the database, so that we can import it elsewhere.

    $sFileName = '';
    $sHeader = '';
    $sFilter = '';
    $ID = '';
    if ($_PE[1] == 'all' && empty($_PE[2])) {
        // Download all.
        $sFileName = 'full_download';
        $sHeader = 'Full data';
        lovd_requireAuth(LEVEL_MANAGER);
    } elseif ($_PE[1] == 'all' && $_PE[2] == 'gene'  && PATH_COUNT == 4 && preg_match('/^[a-z][a-z0-9#@-]+$/i', rawurldecode($_PE[3]))) {
        // Gene database contents.
        $sFileName = 'full_download_' . $_PE[3];
        $sHeader = 'Full data';
        $sFilter = 'gene';
        $ID = $_PE[3];
        lovd_isAuthorized('gene', $_PE[3]);
        lovd_requireAuth(LEVEL_CURATOR);
    } elseif ($_PE[1] == 'all' && $_PE[2] == 'mine' && PATH_COUNT == 3) {
        // Own data.
        $sFileName = 'owned_data';
        $sHeader = 'Owned data';
        $sFilter = 'owner';
        $ID = $_AUTH['id'];
        lovd_requireAuth();
    } elseif ($_PE[1] == 'all' && $_PE[2] == 'user' && PATH_COUNT == 4 && ctype_digit($_PE[3])) {
        // Data owned by other.
        $sFileName = 'owned_data';
        $sHeader = 'Owned data';
        $sFilter = 'owner';
        $ID = $_PE[3];
        lovd_requireAuth(LEVEL_MANAGER);

    } elseif ($_PE[1] == 'columns' && empty($_PE[2])) {
        // Download all.
        $sFileName = 'custom_columns';
        $sHeader = 'Custom column';
        lovd_requireAuth(LEVEL_MANAGER);
    } elseif ($_PE[1] == 'columns' && in_array($_PE[2], array('Individual', 'Phenotype', 'Screening', 'VariantOnGenome', 'VariantOnTranscript'))) {
        // FIXME; Is there a better way checking if it's a valid category?
        // Category given.
        $sFileName = 'custom_columns_' . $_PE[2];
        $sHeader = 'Custom column';
        $sFilter = 'category';
        $ID = $_PE[2];
        lovd_requireAuth(LEVEL_MANAGER);
    } else {
        exit;
    }

    // If we get here, we can print the header already.
    header('Content-Disposition: attachment; filename="LOVD_' . $sFileName . '_' . date('Y-m-d_H.i.s') . '.txt"');
    header('Pragma: public');
    print('### LOVD-version ' . lovd_calculateVersion($_SETT['system']['version']) . ' ### ' . $sHeader . ' download ### To import, do not remove or alter this header ###' . "\r\n");
    if ($sFilter == 'owner') {
        print('## Filter: (created_by = ' . $ID . ' || owned_by = ' . $ID . ')' . "\r\n");
    } elseif ($sFilter == 'gene') {
        print('## Filter: (gene = ' . $ID . ')' . "\r\n");
    } elseif ($sFilter == 'category') {
        print('## Filter: (category = ' . $ID . ')' . "\r\n");
    }
    print('# charset = UTF-8' . "\r\n\r\n");



    // Prepare file creation by defining headers, columns and filters.
    // All data types have same settings: optional ownership filter, no columns hidden.
    $aDataTypeSettings =
        array(
            'comments' => array(),     // Comments to be added to the data block, such as 'For reference only, not part of the selected data set'.
            'data' => array(),         // Here the data will go, either put there by prefetch or when the data is actually printed.
            'filters' => array(),      // This is where the filters go for this object, in format: "column" => "value", "filter_name" => "value", or "column" => array("possible", "values").
            'filter_other' => array(), // This is where the filters go for other objects, implies prefetch=true; in format: "other_object" => array("column_other_object" => "column_this_object").
            'hide_columns' => array(), // Allows for certain columns to be hidden from the output, if this data block is for reference only anyways.
            'prefetch' => false,       // Whether or not to prefetch the data, to allow the 'filter_other' settings to be processed.
            'settings' => array(),     // Settings in the format "setting" => "value" will be output in the data block, for import.
        );
    if ($sFilter == 'owner') {
        // We need to prefetch the filtered data to be able to filter other objects (genes etc).
        // Note: This will make all objects to be prefetched, which could be a bit of overkill here.
        $aDataTypeSettings['prefetch'] = true;
    }

    if ($_PE[1] == 'all') {
        $aObjects =
            array(
                'Columns' => $aDataTypeSettings,
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
        if ($sFilter == 'owner') {
            // In user-specific download, we don't care about the relationship between genes and diseases.
            // (Genes will be shown if its transcripts are shown, Diseases will be shown if its individuals are shown)
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
            unset($aObjects['Columns']); // Custom columns don't matter when it's about somebody's data only.
            $aObjects['Individuals']['filters']['owner'] = $ID;
            $aObjects['Individuals']['filter_other']['Ind2Dis']['individualid'] = 'id';
            $aObjects['Ind2Dis']['filter_other']['Diseases']['id'] = 'diseaseid';
            $aObjects['Diseases']['comments'][] = 'For reference only, not part of the selected data set';
            $aObjects['Diseases']['settings'][] = 'ignore_for_import';
            $aObjects['Diseases']['hide_columns'] =
                array(
                    'created_by', 'created_date', 'edited_by', 'edited_date',
                );
            $aObjects['Phenotypes']['filters']['owner'] = $ID;
            $aObjects['Screenings']['filters']['owner'] = $ID;
            $aObjects['Screenings']['filter_other']['Scr2Gene']['screeningid'] = 'id';
            $aObjects['Screenings']['filter_other']['Scr2Var']['screeningid'] = 'id';
            $aObjects['Scr2Gene']['filter_other']['Genes']['id'] = 'geneid';
            $aObjects['Variants']['filters']['owner'] = $ID;
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

        } elseif ($sFilter == 'gene') {
            // Gene-specific download. Can be downloaded by Curator.
            // Change the order of filtering just a bit, so we can filter the VOGs based on the VOTs, the screenings based on the VOGs and the Individuals based on their Screenings.
            $aObjectsToBeFiltered =
                array(
                    'Genes',
                    'Transcripts',
                    'Gen2Dis',
                    'Variants_On_Transcripts',
                    'Variants',    // Will not get applied filters directly, but needs to be defined here because the VOTs are going to filter these.
                    'Scr2Var',
                    'Screenings',
                    'Scr2Gene',    // Will not get applied filters directly, but needs to be defined here because the Scr2Vars are going to filter these.
                    'Individuals', // Will not get applied filters directly, but needs to be defined here because the Screenings are going to filter these.
                    'Ind2Dis',
                    'Diseases',    // Will not get applied filters directly, but needs to be defined here because the Gen2Dis' and Ind2Dis' are going to filter these.
                    'Phenotypes',  // Will not get applied filters directly, but needs to be defined here because the Screenings are going to filter these.
                );

            unset($aObjects['Columns']); // Custom columns don't matter (yet) when it's about one gene's data only. Although shared_cols would be useful now...
            $aObjects['Genes']['filters']['id'] = $ID;
            // Gen2Dis' need to be prefetched because we need their Disease IDs to filter the Diseases (more possible values added to those later, from Ind2Dis).
            $aObjects['Gen2Dis']['filters']['geneid'] = $ID;
            $aObjects['Gen2Dis']['prefetch'] = true;
            $aObjects['Gen2Dis']['filter_other']['Diseases']['id'] = 'diseaseid'; // More values added later, from Ind2Dis!
            // Transcripts need to be prefetched because we need their Transcript IDs to filter the VOTs.
            $aObjects['Transcripts']['filters']['geneid'] = $ID;
            $aObjects['Transcripts']['prefetch'] = true;
            $aObjects['Transcripts']['filter_other']['Variants_On_Transcripts']['transcriptid'] = 'id';
            // VOTs have to be prefetched, because the VOGs and Scr2Vars need to be filtered on the Variant IDs.
            $aObjects['Variants_On_Transcripts']['prefetch'] = true;
            $aObjects['Variants_On_Transcripts']['filter_other']['Variants']['id'] = 'id';
            $aObjects['Variants_On_Transcripts']['filter_other']['Scr2Var']['variantid'] = 'id';
            $aObjects['Variants_On_Transcripts']['comments'][] = 'Please note that not necessarily all variants found in the given individuals are shown. This output is restricted to variants in the selected gene.';
            $aObjects['Variants']['comments'][] = 'Please note that not necessarily all variants found in the given individuals are shown. This output is restricted to variants in the selected gene.';
            // Scr2Vars have to be prefetched, because the Screenings and Scr2Genes need to be filtered on the Screening IDs.
            $aObjects['Scr2Var']['prefetch'] = true;
            $aObjects['Scr2Var']['filter_other']['Screenings']['id'] = 'screeningid';
            $aObjects['Scr2Var']['filter_other']['Scr2Gene']['screeningid'] = 'screeningid';
            // Screenings have to be prefetched, because the Individuals, Ind2Dis' and Phenotypes need to be filtered on the Individual IDs.
            $aObjects['Screenings']['prefetch'] = true;
            $aObjects['Screenings']['filter_other']['Individuals']['id'] = 'individualid';
            $aObjects['Screenings']['filter_other']['Ind2Dis']['individualid'] = 'individualid';
            $aObjects['Screenings']['filter_other']['Phenotypes']['individualid'] = 'individualid';
            // Ind2Dis' have to be prefetched, because the Diseases need to be filtered on their IDs.
            $aObjects['Ind2Dis']['prefetch'] = true;
            $aObjects['Ind2Dis']['filter_other']['Diseases']['id'] = 'diseaseid'; // More values were already in, from Gen2Dis!
        }

    } elseif ($_PE[1] == 'columns') {
        $aObjects =
            array(
                'Columns' => $aDataTypeSettings,
            );

        // Apply filters and filter order, for user-specific download.
        if ($sFilter == 'category') {
            $aObjects['Columns']['filters']['category'] = $ID;
        }
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
                case 'category':
                    // Custom column category must be taken from the start of the column id.
                    $sWHERE .= 'id LIKE ?';
                    $aArgs[] = $Value . '/%';
                    break;
                case 'owner':
                    // Data ownership is defined by the created_by and owned_by fields.
                    $sWHERE .= '(created_by = ? OR owned_by = ?)';
                    $aArgs[] = $Value;
                    $aArgs[] = $Value;
                    break;
                default:
                    // By default, we'll assume that this filter is a certain column.
                    // However, if an empty array of possible values was given to filter on, we must simply return no results.
                    if (is_array($Value) && !count($Value)) {
                        // No hits, filter all out.
                        $sWHERE .= '0=1';
                    } else {
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
    }

    // Build the query.
    // Ugly hack: we will change $sTable for the VOT to a string that joins VOG such that we can apply filters.
    if ($sObject == 'Variants_On_Transcripts') {
        $sTable = TABLE_VARIANTS_ON_TRANSCRIPTS . ' INNER JOIN ' . TABLE_VARIANTS . ' USING (id)';
    } elseif ($sObject == 'Columns') {
        $sTable = TABLE_COLS;
    } else {
        $sTable = @constant('TABLE_' . strtoupper($sObject));
        if (!$sTable) {
            die('Error: Could not find data table for object ' . $sObject . "\r\n");
        }
    }
    // Store in data array.
    $aObjects[$sObject]['query'] = 'SELECT * FROM ' . $sTable . (!$sWHERE? '' : ' WHERE ' . $sWHERE) . ' ORDER BY ' . (empty($aSettings['order_by'])? 'id' : $aSettings['order_by']);
    $aObjects[$sObject]['args']  = $aArgs;

    // If prefetch is requested, request data right here. We will then loop through the results to create the filters for the other objects.
    if ($aSettings['prefetch'] || count($aSettings['filter_other'])) {
        $aObjects[$sObject]['data'] = $_DB->query($aObjects[$sObject]['query'], $aObjects[$sObject]['args'])->fetchAllAssoc();

        // Check if we, now that we have the data fetched, need to apply other filters,
        foreach ($aSettings['filter_other'] as $sObjectToFilter => $aFiltersToRun) {
            // Check if object that needs to be filtered actually exists or not.
            if (isset($aObjects[$sObjectToFilter])) {
                // Make sure this object has a functional 'filters' array.
                if (!isset($aObjects[$sObjectToFilter]['filters']) || !is_array($aObjects[$sObjectToFilter]['filters'])) {
                    $aObjects[$sObjectToFilter]['filters'] = array();
                }
                // Now loop the list of filters to apply.
                foreach ($aFiltersToRun as $sColumnToFilter => $sColToMatch) {
                    // Make sure this object has a functional 'filters' array for this column.
                    if (!isset($aObjects[$sObjectToFilter]['filters'][$sColumnToFilter]) || !is_array($aObjects[$sObjectToFilter]['filters'][$sColumnToFilter])) {
                        $aObjects[$sObjectToFilter]['filters'][$sColumnToFilter] = array();
                    }
                    // Now loop the data to collect all the $sValueToFilter data.
                    $aValuesToFilter = array();
                    foreach ($aObjects[$sObject]['data'] as $zData) {
                        $aValuesToFilter[] = $zData[$sColToMatch];
                    }
                    // 2015-05-01; 3.0-14; Do not overwrite previous filters on this column! Merge them, instead.
                    $aObjects[$sObjectToFilter]['filters'][$sColumnToFilter] = array_unique(array_merge($aObjects[$sObjectToFilter]['filters'][$sColumnToFilter], $aValuesToFilter));
                }
            }
        }
    }
}





// Now, query the database and print, or just print the data (if already prefetched).
foreach ($aObjects as $sObject => $aSettings) {
    print('## ' . (empty($aSettings['label'])? $sObject : $aSettings['label']) . ' ## Do not remove or alter this header ##' . "\r\n");

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
    if ($sObject == 'Variants_On_Transcripts') {
        // Since we joined to the VOG table to enable filtering, we've got all those columns, too.
        // Just for VOT, do a describe to find out which columns are VOT.
        $aColumns = $_DB->query('DESCRIBE ' . TABLE_VARIANTS_ON_TRANSCRIPTS)->fetchAllColumn();
    } else {
        $aColumns = array_keys($aSettings['data'][0]);
    }

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

    // Empty data, to free up memory.
    unset($aSettings['data']);
}
?>
