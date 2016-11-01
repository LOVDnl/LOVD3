<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-09-19
 * Modified    : 2016-10-17
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Msc. Daan Asscheman <D.Asscheman@LUMC.nl>
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
 * along with LOVD.  If not, see <http://www.gnu.org/licenses/>.
 *
 *************/

define('ROOT_PATH', './');
define('TAB_SELECTED', 'setup');
require ROOT_PATH . 'inc-init.php';
ini_set('auto_detect_line_endings', true); // So we can work with Mac files also...
set_time_limit(0); // Disable time limit, parsing may take a long time.
session_write_close(); // Also don't care about the session (in fact, it locks the whole LOVD while this page is running).

// Require at least curator clearance.
lovd_isAuthorized('gene', $_AUTH['curates']); // Any gene will do.
lovd_requireAUTH(LEVEL_CURATOR);
if ($_AUTH['level'] == LEVEL_CURATOR) {
    // If user has level curator, only simulate is allowed.
    $_POST['simulate'] = 1;
}

// FIXME: How do we implement authorization? First parse everything, THEN using the parsed data we check if user has rights to insert this data?

require ROOT_PATH . 'inc-lib-form.php';
// FIXME:
// When importing individuals, the panelid, fatherid and motherid fields are not properly checked, if the reference exists or not. Object::checkFields() checks only the database, so this check should be disabled for imports and enabled here in the file.
// Values in custom columns not in use, are stored anyway. They're not checked, because they don't appear on the form and the objects only check fields that are on the form.
//   The result is that when you enable a column, values may already be in the database, but might completely be wrong (wrong data type, invalid select value, etc).
//   If we decide to toss the value, report?
// Default values of position fields of variant? Default values for ...?
// Numerical field, we insert ""? Will become 0, but should be NULL.
// Does #ignore_for_import do anything?





$aModes =
    array(
        // For now there is a strict separation between insert and update.
        // During an update, no insertion will be done, but non-existing records will generate an error instead.
        'update' => 'Update existing data (in beta)',
        'insert' => 'Add only, treat all data as new',
    );

$aCharSets =
    array(
        'auto' => 'Autodetect',
        'UTF-8' => 'UTF-8 / Unicode',
        'ISO-8859-1' => 'ISO-8859-1 / Latin-1',
    );

$aTypes =
    array(
        'Full data download' => 'Full',
        'Custom column download' => 'Col',
        'Owned data download' => 'Owned',
    );
// Number of columns that may be updated during an update import. If changes in a column should be ignored, edit the
// lovd_calculateFieldDifferences() function and set the ignore value for that column to true.
// For now this value is set to 1; increasing this number will increase the chance that users accidentally update the wrong record.
$nUpdateColumnsAllowed = 1;

// An array with import file types which are recognized but not accepted for import, with the error message.
$aExcludedTypes =
    array(
//        'Owned data download' => 'It is currently not possible to directly import file type "Owned data download" without modifications. Please see the <A href="docs">manual</A> section "Downloading and importing own data set" for details on how to prepare these files for import.',
    );

// Calculate maximum uploadable file size.
$nMaxSizeLOVD = 100*1024*1024; // 100MB LOVD limit.
$nMaxSize = min(
    $nMaxSizeLOVD,
    lovd_convertIniValueToBytes(ini_get('upload_max_filesize')),
    lovd_convertIniValueToBytes(ini_get('post_max_size')));





function lovd_calculateFieldDifferences ($zData, &$aLine)
{
    // Creates an array with changed columns, with values from the database and the values from the import file.
    // By default, the variable 'ignore' is set to false. Meaning that this field is allowed to be updated.
    global $aSection;

    $aDiffs = array();
    foreach ($zData as $sCol => $sValue) {
        // Empty fields in the import file is considered valid. So when a field is filled in the database
        // but is empty in the import file, the field is emptied in the database.
        // When the database columns do not exist in the import file, the columns are not taken into account in this function.
        // Below we take care of fields that exist in the database but not in the import file.
        if (isset($aLine[$sCol])) {
            if (strval($sValue) === $aLine[$sCol]) {
                // Database and import file have the same value, continue to the next field.
                continue;
            }

            // We have to perform an extra check for IDs because the import
            // file and database can have a difference in leading zeros.
            if ($aSection['object']->sObject === 'Gene' &&
                $sCol === 'id') {
                // The ID in section genes is the only ID which is not an integer.
                // Therefore, we don't have to do an extra check on gene ID and we
                // can continue.
                continue;
            }
            if (!empty($sValue) &&
                ctype_digit($sValue) &&
                (int) $sValue === (int) $aLine[$sCol]) {
                // Database and import file have the same value, continue to the next field.
                continue;
            }

            // The field is different in the import file and in the database.
            // Now we check what we will do with this difference:
            // ignore = true; Value in import file is NOT saved in database
            // ignore = false; Value in import file is saved in database
            if (isset($aSection['update_columns_not_allowed'][$sCol]) &&
                $aSection['update_columns_not_allowed'][$sCol]['error_type']) {
                // Changes in these fields are ignored during an update import, because they are not allowed to be modified.
                // But because we might want to set a warning to inform the user, the fields must be included in the $aDiffs array.
                // Whether changes in these columns are soft or hard errors or ignored silently, is defined in $aSection['update_columns_not_allowed'].
                $aDiffs[$sCol] = array('DB' => $sValue, 'file' => $aLine[$sCol], 'ignore' => true);
            } else {
                $aDiffs[$sCol] = array('DB' => $sValue, 'file' => $aLine[$sCol], 'ignore' => false);
            }
        } else {
            // During an update import we do not want to update fields, not present in the import file, with the default values.
            // Therefore we are going to add all the missing columns of the database to $aLine and set 'error_type' on false and 'ignore' on true.
            // In this way, the fields will not be updated and no warning or error is set.
            $aLine[$sCol] = $sValue;
            $aSection['update_columns_not_allowed'][$sCol]['error_type'] = false;
            $aDifferences[$sCol] = array('DB' => $sValue, 'file' => 'Does not exist in import file', 'ignore' => true);
        }
    }
    return $aDiffs;
}





function lovd_endLine ()
{
    // Ends the current line by cleaning up the memory and changing the line number.
    global $aData, $i, $nLine, $_ERROR, $aImportFlags;

    unset($aData[$i]);
    $nLine ++;

    // If we have too many errors, quit here (note that some errors can still flood the page,
    // since they do a continue or break before reaching this part of the code).
    if (count($_ERROR['messages']) >= $aImportFlags['max_errors']) {
        lovd_errorAdd('import', 'Too many errors, stopping file processing.');
        return false;
    }
    return true;
}





/**
 * lovd_setEmptyCheckboxFields checks for all fields in the import file if it is a checkbox type
 * and if it has a valid value (0 or 1). When the field has no value ('') it is set to 0.
 * When it has an invalid value (>1) an error is set.
 **/
function lovd_setEmptyCheckboxFields ($aForm)
{
    global $aLine;

    foreach ($aForm as $aField) {
        if (!is_array($aField)) {
            // 'skip', 'hr', etc...
            continue;
        }
        @list($sHeader, , $sType, $sName) = $aField;
        if ($sType == 'checkbox') {
            // If a checkbox field is left empty in the import file, it is filled with 0.
            // If it does not exist in the import file it should not be added here.
            // Because during update we want to ignore fields that are not available, and during insert it will generate an error when mandatory.
            if (isset($aLine[$sName]) && $aLine[$sName] === '') {
                // All data in $aLine is handled as a string, therefor we set the checkbox variable as string.
                $aLine[$sName] = '0';
            }
            if (isset($aLine[$sName]) && !in_array($aLine[$sName], array('0', '1'))) {
                lovd_errorAdd($sName, 'The field \'' . $sHeader . '\' must contain either a \'0\' or a \'1\'.');
            }
        }
    }
}





function lovd_trimField ($sVal)
{
    // Trims data fields in an intelligent way. We don't just strip the quotes off, as this may effect quotes in the fields.
    // Instead, we check if the field is surrounded by quotes. If so, we take the first and last character off and return the field.

    $sVal = trim($sVal);
    if ($sVal && $sVal{0} == '"' && substr($sVal, -1) == '"') {
        $sVal = substr($sVal, 1, -1); // Just trim the first and last quote off, nothing else!
    }
    return trim($sVal);
}





function utf8_encode_array ($Data)
{
    // Recursively loop array to encode values.

    if (!is_array($Data)) {
        return utf8_encode($Data);
    } else {
        foreach ($Data as $key => $val) {
            $Data[$key] = utf8_encode_array($val);
        }
        return $Data;
    }
}





$nWarnings = 0;
if (POST) {
    // Form sent, first check the file itself.
    lovd_errorClean();

    // If the file does not arrive (too big), it doesn't exist in $_FILES.
    if (empty($_FILES['import']) || ($_FILES['import']['error'] > 0 && $_FILES['import']['error'] < 4)) {
        lovd_errorAdd('import', 'There was a problem with the file transfer. Please try again. The file cannot be larger than ' . round($nMaxSize/pow(1024, 2), 1) . ' MB' . ($nMaxSize == $nMaxSizeLOVD? '' : ', due to restrictions on this server') . '.');

    } elseif ($_FILES['import']['error'] == 4 || !$_FILES['import']['size']) {
        lovd_errorAdd('import', 'Please select a file to upload.');

    } elseif ($_FILES['import']['size'] > $nMaxSize) {
        lovd_errorAdd('import', 'The file cannot be larger than ' . round($nMaxSize/pow(1024, 2), 1) . ' MB' . ($nMaxSize == $nMaxSizeLOVD? '' : ', due to restrictions on this server') . '.');

    } elseif ($_FILES['import']['error']) {
        // Various errors available from 4.3.0 or later.
        lovd_errorAdd('import', 'There was an unknown problem with receiving the file properly, possibly because of the current server settings. If the problem persists, please contact the database administrator.');
    }

    if (!lovd_error()) {
        // Find out the MIME-type of the uploaded file. Sometimes mime_content_type() seems to return False. Don't stop processing if that happens.
        // However, when it does report something different, mention what type was found so we can debug it.
        $sType = '';
        if (function_exists('mime_content_type')) {
            $sType = mime_content_type($_FILES['import']['tmp_name']);
        }
        if ($sType && substr($sType, 0, 5) != 'text/') { // Not all systems report the regular files as "text/plain"; also reported was "text/x-pascal; charset=us-ascii".
            lovd_errorAdd('import', 'The upload file is not a tab-delimited text file and cannot be imported. It seems to be of type "' . htmlspecialchars($sType) . '".');

        } else {
            $fInput = @fopen($_FILES['import']['tmp_name'], 'r');
            if (!$fInput) {
                lovd_errorAdd('import', 'Cannot open file after it was received by the server.');

            } else {
                // Check mode, we take no default if we don't understand the answer.
                if (empty($_POST['mode']) || !isset($aModes[$_POST['mode']])) {
                    lovd_errorAdd('mode', 'Please select the import mode from the list of options.');
                }

                // Open the file using file() to check the line endings, then check the encodings, try to use as little memory as possible.
                // Reading the entire file in memory, because we need to detect the encoding and possibly convert.
                $aData = lovd_php_file($_FILES['import']['tmp_name']);

                // Fix encoding problems.
                if ($_POST['charset'] == 'auto' || !isset($aCharSets[$_POST['charset']])) {
                    // Auto detect charset, it's not given.
                    // FIXME; Should we ever allow more encodings?
                    $sEncoding = mb_detect_encoding(implode("\n", $aData), array('UTF-8', 'ISO-8859-1'), true);
                    if (!$sEncoding) {
                        // Could not be detected.
                        lovd_errorAdd('charset', 'Could not autodetect the file\'s character encoding. Please select the character encoding from from the list of options.');
                    } elseif ($sEncoding != 'UTF-8') {
                        // Is not UTF-8, and for sure has special chars.
                        $aData = utf8_encode_array($aData);
                    }
                } elseif ($_POST['charset'] == 'ISO-8859-1') {
                    $aData = utf8_encode_array($aData);
                }
            }
        }
    }





    if (!lovd_error()) {
        // Prepare, find LOVD version and format type.
        // Index 'update_columns_not_allowed' is already filled with fields that are never allowed to be updated and always should generate a warning.
        // To have changes to a column ignored, see the lovd_calculateFieldDifferences() function.
        // Later, per section 'update_columns_not_allowed' is filled with additional fields which are not allowed to be updated, together with the error message when they are changed and the error type (soft or hard warning).
        $aParsed = array_fill_keys(
            array('Columns', 'Genes', 'Transcripts', 'Diseases', 'Genes_To_Diseases', 'Individuals', 'Individuals_To_Diseases', 'Phenotypes', 'Screenings', 'Screenings_To_Genes', 'Variants_On_Genome', 'Variants_On_Transcripts', 'Screenings_To_Variants'),
            array('allowed_columns' => array(), 'columns' => array(), 'update_columns_not_allowed' => array('edited_by' => array('message' => 'Edited by field is set by LOVD', 'error_type' => 'soft'),
                                                                                                            'edited_date' => array('message' => 'Edited date field is set by LOVD', 'error_type' => 'soft'),
                                                                                                            'created_by' => array('message' => 'Created by field is set by LOVD', 'error_type' => 'soft'),
                                                                                                            'created_date' => array('message' => 'Created date field is set by LOVD', 'error_type' => 'soft')), 'data' => array(), 'ids' => array(), 'nColumns' => 0, 'object' => null, 'required_columns' => array(), 'settings' => array()));
        $aParsed['Genes_To_Diseases'] = $aParsed['Individuals_To_Diseases'] = $aParsed['Screenings_To_Genes'] = $aParsed['Screenings_To_Variants'] = array('allowed_columns' => array(), 'data' => array()); // Just the data, nothing else!
        $aUsers = $_DB->query('SELECT id FROM ' . TABLE_USERS)->fetchAllColumn();
        $aImportFlags = array('max_errors' => 50);
        $sFileVersion = $sFileType = $sCurrentSection = '';
        $bParseColumns = false;
        $nLine = 1;
        $nLines = count($aData);
        $nDataTotal = 0; // To show the second progress bar; how much actual work needs to be done?
        $sMode = $_POST['mode'];
        $sDate = date('Y-m-d H:i:s');
        $aDiseasesAlreadyWarnedFor = array(); // To prevent lots and lots of error messages for each phenotype entry created for the same disease that is not yet inserted into the database.
        $aSectionsAlreadyWarnedFor = array(); // To prevent lots and lots of error messages for a section that cannot be updated in the database.

        foreach ($aData as $i => $sLine) {
            $sLine = trim($sLine);
            if (!$sLine) {
                if (!lovd_endLine()) {
                    break;
                }
                continue;
            }

            if (!$sFileVersion) {
                // Still looking for the LOVD version! We have a line here, so this must be what we're looking for.
                if (!preg_match('/^###\s*LOVD-version\s*([0-9]{4}\-[0-9]{2}[a-z0-9])\s*###\s*([^#]+)\s*###/', ltrim($sLine, '"'), $aRegs)) {
                    lovd_errorAdd('import', 'File format not recognized; the first line of the imported file should contain the LOVD version header (### LOVD-version ' . lovd_calculateVersion($_SETT['system']['version']) . ' ### ... etc).');
                } else {
                    list(, $sFileVersion, $sFileType) = $aRegs;
                    $sFileType = trim($sFileType);

                    if (!isset($aTypes[$sFileType])) {
                        // We did not understand the file type (Full data download, custom columns, genes, etc).
                        lovd_errorAdd('import', 'File type not recognized; type "' . $sFileType . '" unknown.');
                    } else {
                        if (isset($aExcludedTypes[$sFileType])) {
                            // It is currently not possible to import owned data, see manual for details..
                            lovd_errorAdd('import', 'File type not allowed; ' . $aExcludedTypes[$sFileType] . ' ');
                        } else {
                            $sFileType = $aTypes[$sFileType];
                            // Clean $aParsed a bit, depending on the file type.
                            if ($sFileType == 'Col') {
                                $aParsed = array('Columns' => $aParsed['Columns']);
                            }
                        }
                    }
                    if (!lovd_endLine()) {
                        break;
                    }
                }
                break;
            }
        }
    }





    if (!lovd_error()) {
        // Start parsing and put everything in memory.

        // Get at least 128MB memory.
        // FIXME: Increase memory limit based on file size? Use memory_get_usage() to predict running out of memory?
        if (lovd_convertIniValueToBytes(ini_get('memory_limit')) < 128*1024*1024) {
            ini_set('memory_limit', '128M');
        }

        $_T->printHeader();
        $_T->printTitle('Import data in LOVD format');

        // Load progress bar.
        require ROOT_PATH . 'class/progress_bar.php';
        $_BAR = array(new ProgressBar('parser', 'Parsing file...'));
        $_BAR[0]->setMessageVisibility('done', true);
        if (LOVD_plus) {
            // Diagnostics: Find default gene we'll use for all VOTs, since the gene is always the same anyway.
            $sDefaultGene = $_DB->query('SELECT geneid FROM ' . TABLE_TRANSCRIPTS . ' LIMIT 1')->fetchColumn();
        }



        // Now, the actual parsing...
        foreach ($aData as $i => $sLine) {
            $sLine = trim($sLine);
            if (!$sLine) {
                if (!lovd_endLine()) {
                    break;
                }
                continue;
            }



            if (substr(ltrim($sLine, '"'), 0, 1) == '#') {
                if (preg_match('/^#\s*([a-z_]+)\s*=\s*(.+)$/', ltrim($sLine, '"'), $aRegs)) {
                    // Import flag (setting).
                    $aImportFlags[$aRegs[1]] = $aRegs[2];
                } elseif (preg_match('/^##\s*([A-Za-z_]+)\s*##\s*Do not remove/', ltrim($sLine, '"'), $aRegs)) {
                    // New section.
                    // Clean up old section, if available.
                    if ($sCurrentSection) {
                        unset($aSection['columns']);
                        unset($aSection['nColumns']);
                        unset($aSection['required_columns']);
                        unset($aSection['settings']);
                        unset($aSection['update_columns_not_allowed']);
                        if (lovd_error()) {
                            unset($aSection['allowed_columns']);
                            unset($aSection['object']);
                            unset($aSection['objects']);
                        }

                        // If we had at least one unknown column in the previous section, we will mention in the output the number of values gone lost.
                        // The column name has already been written to the output, so we should simply add command to append the number of lost values.
                        if (isset($aUnknownCols) && count($aUnknownCols)) {
                            print('<SCRIPT type="text/javascript">' . "\n" .
                                  '  var sMessage = $("#lovd_parser_progress_message_done").html();' . "\n");
                            foreach ($aLostValues as $sCol => $n) {
                                print('  sMessage = sMessage.replace(/' . preg_quote($sCol, '/') . '/, "' . $sCol . ' (lost ' . $n . ' value' . ($n == 1? '' : 's') . ')");' . "\n");
                            }
                            print('  $("#lovd_parser_progress_message_done").html(sMessage);' . "\n" .
                                  '</SCRIPT>');
                            $aUnknownCols = $aLostValues = array(); // 2013-10-14; 3.0-08; Reset, because it's normally reset when parsing the next section's columns, which might not be there.
                            flush();
                        }
                    }
                    $sCurrentSection = $aRegs[1];
                    $bParseColumns = true;
                    // Section name passed to lovd_isAuthorized().
                    // This doesn't work for linking tables, but they never get an isAuthorized() call.
                    // The variant sections get theirs overwritten to 'variant'.
                    $sCurrentAuthorization = strtolower(substr($sCurrentSection, 0, -1));

                    // So we can use short variables.
                    $aSection = &$aParsed[$sCurrentSection];
                    $aColumns = &$aSection['columns'];
                    $nColumns = &$aSection['nColumns'];

                    // Section-specific settings and definitions.
                    if ($sCurrentSection != 'Genes_To_Diseases') {
                        $aSection['required_columns'][] = 'id';
                    }
                    $sTableName = 'TABLE_' . strtoupper($sCurrentSection);
                    switch ($sCurrentSection) {
                        case 'Columns':
                            $sTableName = $aParsed[$sCurrentSection]['table_name'] = 'TABLE_COLS';
                            require_once ROOT_PATH . 'class/object_columns.php';
                            $aSection['object'] = new LOVD_Column();
                            // The following columns are allowed for update: col_order, width, standard, mandatory, head_column, description_form, description_legend_short, description_legend_full,
                            // mysql_type, form_type, select_options, preg_pattern, public_view, public_add, allow_count_all.
                            $aSection['update_columns_not_allowed'] =
                                array_merge(
                                    $aSection['update_columns_not_allowed'],
                                    array(
                                        'hgvs' => array('message' => 'Not allowed to change the HGVS standard status of any column.', 'error_type' => 'hard'),
                                    )
                                );
                            break;
                        case 'Genes':
                            // The following columns are allowed for update: chrom_band, imprinting, reference, url_homepage, url_external, allow_download, allow_index_wiki, show_hgmd, show_genecards,
                            // show_genetests, note_index, note_listing, refseq, refseq_url, disclaimer, disclaimer_text, header, header_align,
                            // footer, footer_align.
                            if ($sMode == 'update') {
                                // Not allowed to be inserted yet, so we don't want checkFields() to be run like that.
                                require_once ROOT_PATH . 'class/object_genes.php';
                                $aSection['object'] = new LOVD_Gene();
                            }
                            $aSection['update_columns_not_allowed'] =
                                array_merge(
                                    $aSection['update_columns_not_allowed'],
                                    array(
                                        'name' => array('message' => 'Not allowed to change the gene name.', 'error_type' => 'hard'),
                                        'chromosome' => array('message' => 'Not allowed to change the chromosome.', 'error_type' => 'hard'),
                                        'refseq_genomic' => array('message' => '', 'error_type' => false), // Silently ignored, since checkFields() will already complain.
                                        'refseq_UD' => array('message' => 'Not allowed to change the Mutalyzer UD refseq ID.', 'error_type' => 'hard'),
                                        'id_hgnc' => array('message' => 'Not allowed to change the HGNC ID.', 'error_type' => 'hard'),
                                        'id_entrez' => array('message' => 'Not allowed to change the Entrez Gene ID.', 'error_type' => 'hard'),
                                        'id_omim' => array('message' => 'Not allowed to change the OMIM ID.', 'error_type' => 'hard'),
                                        'updated_by' => array('message' => 'Updated by field is set by LOVD.', 'error_type' => 'soft'),
                                        'updated_date' => array('message' => 'Updated date field is set by LOVD.', 'error_type' => 'soft'),
                                    )
                                );
                            break;
                        case 'Transcripts':
                            // The following columns are allowed for update: id_ensembl, id_protein_ensembl, id_protein_uniprot.
                            if ($sMode == 'update') {
                                // Not allowed to be inserted yet, so we don't want checkFields() to be run like that.
                                require_once ROOT_PATH . 'class/object_transcripts.php';
                                $aSection['object'] = new LOVD_Transcript();
                            }
                            $aSection['update_columns_not_allowed'] =
                                array_merge(
                                    $aSection['update_columns_not_allowed'],
                                    array(
                                        'geneid' => array('message' => 'Not allowed to change the gene.', 'error_type' => 'hard'),
                                        'name' => array('message' => 'Not allowed to change the name.', 'error_type' => 'hard'),
                                        'id_mutalyzer' => array('message' => 'Not allowed to change the Mutalyzer ID.', 'error_type' => 'hard'),
                                        'id_ncbi' => array('message' => 'Not allowed to change the NCBI ID.', 'error_type' => 'hard'),
                                        'id_protein_ncbi' => array('message' => 'Not allowed to change the NCBI protein ID.', 'error_type' => 'hard'),
                                        'position_c_mrna_start' => array('message' => 'Not allowed to change the mRNA start position.', 'error_type' => 'hard'),
                                        'position_c_mrna_end' => array('message' => 'Not allowed to change the mRNA end position.', 'error_type' => 'hard'),
                                        'position_c_cds_end' => array('message' => 'Not allowed to change the CDS end position.', 'error_type' => 'hard'),
                                        'position_g_mrna_start' => array('message' => 'Not allowed to change the genomic start position.', 'error_type' => 'hard'),
                                        'position_g_mrna_end' => array('message' => 'Not allowed to change the genomic end position.', 'error_type' => 'hard'),
                                    )
                                );
                            break;
                        case 'Diseases':
                            // The following columns are allowed for update: symbol, name, id_omim.
                            require_once ROOT_PATH . 'class/object_diseases.php';
                            $aSection['object'] = new LOVD_Disease();
                            break;
                        case 'Genes_To_Diseases':
                            require_once ROOT_PATH . 'class/object_basic.php';
                            $sTableName = $aParsed[$sCurrentSection]['table_name'] = 'TABLE_GEN2DIS';
                            $aSection['object'] = new LOVD_Basic($sTableName);
                            break;
                        case 'Individuals':
                            // The following columns are allowed for update: fatherid, motherid, panelid, panel_size, owned_by, statusid.
                            require_once ROOT_PATH . 'class/object_individuals.php';
                            $aSection['object'] = new LOVD_Individual();
                            break;
                        case 'Individuals_To_Diseases':
                            require_once ROOT_PATH . 'class/object_basic.php';
                            $sTableName = $aParsed[$sCurrentSection]['table_name'] = 'TABLE_IND2DIS';
                            $aSection['object'] = new LOVD_Basic($sTableName);
                            break;
                        case 'Phenotypes':
                            // The following columns are allowed for update: owned_by, statusid.
                            $aSection['required_columns'][] = 'diseaseid';
                            $aSection['required_columns'][] = 'individualid';
                            require_once ROOT_PATH . 'class/object_phenotypes.php';
                            $aSection['update_columns_not_allowed'] =
                                array_merge(
                                    $aSection['update_columns_not_allowed'],
                                    array(
                                        'diseaseid' => array('message' => 'Not allowed to change the disease.', 'error_type' => 'hard'),
                                        'individualid' => array('message' => 'Not allowed to change the individual.', 'error_type' => 'hard'),
                                    )
                                );
                            // We don't create an object here, because we need to do that per disease (since different diseases
                            // may have different custom columns). This means the field headers are not checked for
                            // mandatory fields. Mandatory fields are still checked below with a disease-specific
                            // instantiation of LOVD_Phenotype.
                            $aSection['objects'] = array();
                            break;
                        case 'Screenings':
                            // The following columns are allowed for update: variants_found, owned_by.
                            $aSection['required_columns'][] = 'individualid';
                            require_once ROOT_PATH . 'class/object_screenings.php';
                            $aSection['update_columns_not_allowed'] =
                                array_merge(
                                    $aSection['update_columns_not_allowed'],
                                    array(
                                        'individualid' => array('message' => 'Not allowed to change the individual.', 'error_type' => 'hard'),
                                    )
                                );
                            $aSection['object'] = new LOVD_Screening();
                            break;
                        case 'Screenings_To_Genes':
                            require_once ROOT_PATH . 'class/object_basic.php';
                            $sTableName = $aParsed[$sCurrentSection]['table_name'] = 'TABLE_SCR2GENE';
                            $aSection['object'] = new LOVD_Basic($sTableName);
                            break;
                        case 'Variants_On_Genome':
                            // The following columns are allowed for update: effectid, type, mapping_flags, average_frequency.
                            $sCurrentAuthorization = 'variant';
                            $sTableName = $aParsed[$sCurrentSection]['table_name'] = 'TABLE_VARIANTS';
                            require_once ROOT_PATH . 'class/object_genome_variants.php';
                            $aSection['object'] = new LOVD_GenomeVariant();
                            $aSection['update_columns_not_allowed'] =
                                array_merge(
                                    $aSection['update_columns_not_allowed'],
                                    array(
                                        'chromosome' => array('message' => 'Not allowed to change the chromosome.', 'error_type' => 'hard'),
                                        'position_g_start' => array('message' => 'Not allowed to change the genomic start position.', 'error_type' => 'hard'),
                                        'position_g_end' => array('message' => 'Not allowed to change the genomic end position.', 'error_type' => 'hard'),
                                    )
                                );
                            break;
                        case 'Variants_On_Transcripts':
                            // The following columns are allowed for update: effectid.
                            $sCurrentAuthorization = 'variant';
                            $sTableName = $aParsed[$sCurrentSection]['table_name'] = 'TABLE_VARIANTS_ON_TRANSCRIPTS';
                            $aSection['required_columns'][] = 'transcriptid';
                            require_once ROOT_PATH . 'class/object_transcript_variants.php';
                            $aSection['update_columns_not_allowed'] =
                                array_merge(
                                    $aSection['update_columns_not_allowed'],
                                    array(
                                        'transcriptid' => array('message' => 'Not allowed to change the transcript.', 'error_type' => 'hard'),
                                        'position_c_start' => array('message' => 'Not allowed to change the start position.', 'error_type' => 'hard'),
                                        'position_c_start_intron' => array('message' => 'Not allowed to change the intronic start position.', 'error_type' => 'hard'),
                                        'position_c_end' => array('message' => 'Not allowed to change the end position.', 'error_type' => 'hard'),
                                        'position_c_end_intron' => array('message' => 'Not allowed to change the intronic end position.', 'error_type' => 'hard'),
                                    )
                                );
                            // We don't create an object here, because we need to do that per gene (since different genes
                            // may have different custom columns). This means the field headers are not checked for
                            // mandatory fields. Mandatory fields are still checked below with a gene-specific
                            // instantiation of LOVD_TranscriptVariant.
                            $aSection['objects'] = array();
                            break;
                        case 'Screenings_To_Variants':
                            require_once ROOT_PATH . 'class/object_basic.php';
                            $sTableName = $aParsed[$sCurrentSection]['table_name'] = 'TABLE_SCR2VAR';
                            $aSection['object'] = new LOVD_Basic($sTableName);
                            break;
                        default:
                            // Category not recognized!
                            lovd_errorAdd('import', 'Error (line ' . $nLine . '): Unknown section "' . $sCurrentSection . '".');
                            break 2;
                    }
                    if (defined($sTableName)) {
                        // TABLE_GENES, TABLE_TRANSCRIPTS, etc.
                        $sTableName = constant($sTableName);
                        $aSection['allowed_columns'] = lovd_getColumnList($sTableName);

                        if (strpos($sTableName, '2') !== false) {
                            // Linking tables (such as GEN2DIS) require all available columns to be present.
                            $aSection['required_columns'] = $aSection['allowed_columns'];
                        } else {
                            // Normal data table, no data links.
                            if ($sCurrentSection == 'Variants_On_Transcripts' && $aParsed['Variants_On_Genome']['ids']) {
                                // IDs are not unique, and anyways are already parsed in the VOG section.
                                // Note: Making this a reference (=& instead of =) slows down the parsing of a VOT line 3x. Don't understand why.
                                $aSection['ids'] = $aParsed['Variants_On_Genome']['ids'];
                            } else {
                                $aSection['ids'] = $_DB->query('SELECT ' . (in_array($sCurrentSection, array('Columns', 'Genes'))? 'id' : 'CAST(id AS UNSIGNED)') . ', 1 FROM ' . $sTableName)->fetchAllCombine();
                            }
                        }
                    }
                    // For custom objects: all mandatory custom columns will be mandatory here, as well.
                    if (isset($aSection['object']->aColumns) && $sMode != 'update') {
                        foreach ($aSection['object']->aColumns as $sCol => $aCol) {
                            if ($aCol['mandatory']) {
                                $aSection['required_columns'][] = $sCol;
                            }
                        }
                    }
                } // Else, it's just comments we will ignore.
                if (!lovd_endLine()) {
                    break;
                }
                continue;
            }



            if ($bParseColumns) {
                // We are expecting columns now, because we just started a new section.
                if (!preg_match('/^(("\{\{[A-Za-z0-9_\/]+\}\}"|\{\{[A-Za-z0-9_\/]+\}\})\t)+$/', $sLine . "\t")) { // FIXME: Can we make this a simpler regexp?
                    // Columns not found; either we have data without a column header, or a malformed column header. Abort import.
                    lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Expected column header, got this instead:<BR><BLOCKQUOTE>' . htmlspecialchars($sLine) . '</BLOCKQUOTE>');
                    break;
                }

                $aColumns = explode("\t", $sLine);
                $nColumns = count($aColumns);
                $aColumns = array_map('trim', $aColumns, array_fill(0, $nColumns, '"{ }'));

                // Do we have all required columns?
                $aMissingCols = array_diff($aSection['required_columns'], $aColumns);
                if (count($aMissingCols)) {
                    lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Missing required column' . (count($aMissingCols) == 1? '' : 's') . ': ' . implode(', ', $aMissingCols) . '.');
                }
                // Required, custom. columns that we just reported missing, will cause more errors downstream because we have the object checking for them as well.
                // So we'll remove them here again, the checkFields() will take care of it.
                foreach ($aSection['required_columns'] as $nKey => $sCol) {
                    if (strpos($sCol, '/') !== false) {
                        unset($aSection['required_columns'][$nKey]);
                    }
                }

                // Do we have columns we don't know?
                $aUnknownCols = array_diff($aColumns, $aSection['allowed_columns']);
                if (count($aUnknownCols)) {
                    $_BAR[0]->appendMessage('Warning: the following column' . (count($aUnknownCols) == 1? ' has' : 's have') . ' been ignored from the ' . $sCurrentSection . ' data on line ' . $nLine . ', because ' . (count($aUnknownCols) == 1? 'it is' : 'they are') . ' not in the database: ' . implode(', ', $aUnknownCols) . '.<BR>', 'done');
                    $nWarnings ++;
                }
                // Now create array based on $aUnknownCols so we can count how many values are actually lost.
                $aLostValues = array_fill_keys($aUnknownCols, 0);
                // Repeated columns? A mistake by the user, which can cause a lot of confusion.
                $aColumnCounts = array_count_values($aColumns);
                $aDuplicateColumns = array();
                foreach ($aColumnCounts as $sCol => $n) {
                    if ($n > 1) {
                        $aDuplicateColumns[] = $sCol;
                    }
                }
                if (count($aDuplicateColumns)) {
                    lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): The following column' . (count($aDuplicateColumns) == 1? ' is' : 's are') . ' present more than once in the list of fields: ' . implode(', ', $aDuplicateColumns) . '. Please inspect your file and make sure that the column headers contain no duplicates.');
                }

                $bParseColumns = false;
                if (!lovd_endLine()) {
                    break;
                }
                continue; // Continue to the next line.
            }



            if (!$sCurrentSection) {
                // We got here, without passing a section header first.
                lovd_errorAdd('import', 'Error (line ' . $nLine . '): Found data before finding section header.');
                break; // Kill import completely.
            }

            // We've got a line of data here. Isolate the values and check all columns.
            $aLine = explode("\t", $sLine);
            // For any category, the number of columns should be the same as the number of fields.
            // However, less fields may be encountered because the spreadsheet program just put tabs and no quotes in empty fields.
            if (count($aLine) < $nColumns) {
                $aLine = array_pad($aLine, $nColumns, '');
            } elseif (count($aLine) != $nColumns) {
                // More columns found then needed.
                lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Found ' . count($aLine) . ' fields instead of the expected ' . $nColumns . '.');
                if (!lovd_endLine()) {
                    break;
                }
                continue; // Continue to the next line.
            }
            $aLine = array_map('lovd_trimField', $aLine);

            // Tag all fields with their respective column name. Then check data.
            $aLine = array_combine($aColumns, array_values($aLine));

            // Unset unused columns.
            foreach ($aUnknownCols as $sCol) {
                if ($aLine[$sCol] !== '') {
                    $aLostValues[$sCol] ++;
                }
                unset($aLine[$sCol]);
            }

            // Data from LOVD downloads are escaped. Besides tabs, carriage returns and new lines, also quotes (both single and double) and backslashes are escaped.
            foreach ($aLine as $key => $sVal) {
                // First prepare the \t, \r and \n codes, so they won't get lost.
                $sVal = str_replace(array('\r', '\n', '\t'), array('\\\r', '\\\n', '\\\t'), $sVal);
                $sVal = stripslashes($sVal);
                $aLine[$key] = str_replace(array('\r', '\n', '\t'), array("\r", "\n", "\t"), $sVal);
            }

            // When we are updating we don't want to add all allowed columns to the import data ($aLine),
            // because when the column is not in the import file during an import,
            // the user probably doesn't want to change that column.
            // When updating, the function lovd_calculateFieldDifferences() adds all the database fields to $aLine,
            // sets 'error_type' to false and 'ignore' on true.
            // This way, the fields will not be updated and no warning or error is set.
            if ($sMode != 'update') {
                // Create all the standard column's keys in $aLine, so we can safely reference to it.
                foreach ($aSection['allowed_columns'] as $sCol) {
                    if (!isset($aLine[$sCol])) {
                        $aLine[$sCol] = '';
                    }
                }
            }



            // General default values.
            // Owned By.
            if (in_array('owned_by', $aSection['allowed_columns']) && (!isset($aLine['owned_by']) || $aLine['owned_by'] === '') && $sMode != 'update') {
                // Owned_by not filled in, and not set to LOVD (0) either. Set to user.
                $aLine['owned_by'] = $_AUTH['id'];
            }
            // Data status.
            if (in_array('statusid', $aSection['allowed_columns']) && empty($aLine['statusid']) && $sMode != 'update') {
                // Status not filled in. Set to Public.
                // Diagnostics: For LOVD+, the default is STATUS_HIDDEN.
                $aLine['statusid'] = (LOVD_plus? STATUS_HIDDEN : STATUS_OK);
            }

            // General checks: required fields defined by import.
            foreach ($aSection['required_columns'] as $sCol) {
                if (!isset($aLine[$sCol]) || $aLine[$sCol] === '') {
                    lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Missing value for required column "' . htmlspecialchars($sCol) . '".');
                }
            }

            // For shared objects, load the correct object.
            if ($sCurrentSection == 'Phenotypes' && $aLine['diseaseid'] !== '') {
                if (!isset($aSection['objects'][(int) $aLine['diseaseid']])) {
                    $aSection['objects'][(int) $aLine['diseaseid']] = new LOVD_Phenotype($aLine['diseaseid']);
                }
                $aSection['object'] =& $aSection['objects'][(int) $aLine['diseaseid']];
            }
            $sGene = '';
            if ($sCurrentSection == 'Variants_On_Transcripts' && $aLine['transcriptid']) {
                // We have to include some checks here instead of below, because we need to verify that we understand the transcriptID and get to the Gene.
                //   Only then can we open the correct object.
                $bTranscriptInDB = isset($aParsed['Transcripts']['ids'][(int) $aLine['transcriptid']]);
                $bTranscriptInFile = isset($aParsed['Transcripts']['data'][(int) $aLine['transcriptid']]);
                if (!$bTranscriptInFile && !$bTranscriptInDB) {
                    // Transcript does not exist and is not defined in the import file.
                    lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Transcript "' . htmlspecialchars($aLine['transcriptid']) . '" does not exist in the database and is not defined in this import file.');
                    $bGeneInDB = false;
                } elseif ($bTranscriptInFile) {
                    $sGene = $aParsed['Transcripts']['data'][(int) $aLine['transcriptid']]['geneid'];
                    $bGeneInDB = isset($aParsed['Genes']['ids'][$sGene]);
                } else {
                    $sGene = $_DB->query('SELECT geneid FROM ' . TABLE_TRANSCRIPTS . ' WHERE id = ?', array($aLine['transcriptid']))->fetchColumn();
                    $bGeneInDB = true;
                    // Store for the next VOT.
                    $aParsed['Transcripts']['data'][(int) $aLine['transcriptid']] = array('id' => $aLine['transcriptid'], 'geneid' => $sGene, 'todo' => '');
                }
                if (LOVD_plus) {
                    // Diagnostics: Simple method to save a lot of memory and parsing time; since all genes should be equal, I'm creating the VOT object just once.
                    // This will save a lot of memory (each VOT object takes 0.05 MB == 500 MB per 10.000 VOTs, and we often have 40.000 or so) and parsing time.
                    // Selecting a gene that exists, so transcripts and columns will be filled (needed to actually make the checkFields() work.)
                    $sGene = $sDefaultGene;
                }
                // Only instantiate an object when a gene is found for a transcript.
                if ($sGene) {
                    if (!isset($aSection['objects'][$sGene])) {
                        $aSection['objects'][$sGene] = new LOVD_TranscriptVariant($sGene);
                    }
                    $aSection['object'] =& $aSection['objects'][$sGene];
                }
            }

            // Special actions for section Columns.
            if ($sCurrentSection == 'Columns') {
                // For custom columns, we need to split the ID in category and colid.
                list($aLine['category'], $aLine['colid']) = explode('/', $aLine['id'], 2);

                // Calling getForm() from import.php complains that $_POST data does not exist.
                // These globals are needed in getForm().
                $_POST['category'] = $aLine['category'];
                $_POST['width'] = $aLine['width'];
                $_POST['workID'] = '';
            }

            // Build the form, necessary for field-specific actions (currently for checkboxes only).
            // Exclude section Genes, because it is not allowed to import this section, it is not necessary to run the getForm().
            if (isset($aSection['object']) && is_object($aSection['object']) && $sCurrentSection != 'Genes') {
                $aForm = array();
                switch ($sCurrentSection) {
                    case 'Phenotypes':
                        $aForm = $aSection['objects'][(int) $aLine['diseaseid']]->getForm();
                        break;
                    case 'Variants_On_Transcripts':
                        // Only get $aForm when we're sure we've got an object. We might not, which happens if we don't have a valid transcriptid.
                        if (isset($aSection['objects'][$sGene])) {
                            $aForm = $aSection['objects'][$sGene]->getForm();
                        }
                        break;
                    default:
                        $aForm = $aSection['object']->getForm();
                }
                lovd_setEmptyCheckboxFields($aForm);
            }

            // General checks: checkFields().
            $zData = false;
            // If we're updating, get the current info from the database.
            if ($sMode == 'update') {
                switch ($sCurrentSection) {
                    case 'Columns':
                    case 'Genes':
                        if (isset($aSection['ids'][$aLine['id']])) {
                            $zData = $_DB->query('SELECT * FROM ' . $sTableName . ' WHERE id = ?', array($aLine['id']))->fetchAssoc();
                        }
                        break;
                    case 'Transcripts':
                    case 'Diseases':
                    case 'Individuals':
                    case 'Phenotypes':
                    case 'Screenings':
                    case 'Variants_On_Genome':
                        if (isset($aSection['ids'][(int) $aLine['id']])) {
                            $zData = $_DB->query('SELECT * FROM ' . $sTableName . ' WHERE id = ?', array($aLine['id']))->fetchAssoc();
                        }
                        break;
                    case 'Variants_On_Transcripts':
                        if (isset($aSection['ids'][(int) $aLine['id']])) {
                            $zData = $_DB->query('SELECT * FROM ' . $sTableName . ' WHERE id = ? AND transcriptid = ?', array($aLine['id'], $aLine['transcriptid']))->fetchAssoc();
                        }
                        break;
                    case 'Genes_To_Diseases':
                    case 'Individuals_To_Diseases':
                    case 'Screenings_To_Genes':
                    case 'Screenings_To_Variants':
                        reset($aLine);
                        list($sCol1, $nID1) = each($aLine);
                        list($sCol2, $nID2) = each($aLine);
                        if (isset($nID1) && isset($nID2)) {
                            $zData = $_DB->query('SELECT * FROM ' . $sTableName . ' WHERE ' . $sCol1 . ' = ? AND ' . $sCol2 . ' = ?', array($nID1, $nID2))->fetchAssoc();
                        }
                        if (!$zData && !in_array($sCurrentSection, $aSectionsAlreadyWarnedFor)) {
                            $_BAR[0]->appendMessage('Warning: It is currently not possible to do an update on section ' . $sCurrentSection . ' via an import <BR>', 'done');
                            $nWarnings ++;
                            $aSectionsAlreadyWarnedFor[] = $sCurrentSection;
                        }
                        break;
                }

                if ($zData) {
                    // Here we create an array with all columns that are different in the DB and in the file.
                    $aDifferences = lovd_calculateFieldDifferences($zData, $aLine);
                    // Calculate number of differences.
                    // Note: This also filters out any linking tables, because they can't have a difference between $zData and $aLine.
                    $nDifferences = 0;
                    $sFieldsChanged = '';
                    foreach ($aDifferences as $sFieldChanged => $aDifference) {
                        if (!$aDifference['ignore']) {
                            $nDifferences ++;
                            if ($sFieldsChanged) {
                                $sFieldsChanged .= ', ' . $sFieldChanged;
                            } else {
                                $sFieldsChanged = $sFieldChanged;
                            }
                        }
                    }

                    // When ignore is true, we still want to display the errors or warnings.
                    // Therefore use $aDifferences instead of $nDifferences to check if it is required to set warnings and/or errors.
                    if (!empty($aDifferences)) {
                        if (!lovd_isAuthorized($sCurrentAuthorization, $aLine['id'], false)) {
                            // Not allowed to edit at all!
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Access denied for update of ' . $sCurrentAuthorization . ' entry ' . htmlspecialchars($aLine['id']) . '.');
                        } elseif ($nDifferences > $nUpdateColumnsAllowed) {
                            // Difference too big, maybe he's trying to change different data.
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Will not update ' . $sCurrentAuthorization . ' ' . htmlspecialchars($aLine['id']) . ', too many fields are different from the database (' . $sFieldsChanged . '). There is a maximum of ' . $nUpdateColumnsAllowed . ' difference' . ($nUpdateColumnsAllowed == 1? '' : 's') . ' to prevent accidental updates.');
                        }

                        $aUpdatedColumnsNotAllowed = array_intersect(array_keys($aDifferences), array_keys($aSection['update_columns_not_allowed']));
                        if ($aUpdatedColumnsNotAllowed) {
                            // Data is being updated, but user is not allowed to edit this column!
                            foreach ($aUpdatedColumnsNotAllowed as $sSameCol) {
                                if ($aSection['update_columns_not_allowed'][$sSameCol]['error_type'] == 'soft') {
                                    $_BAR[0]->appendMessage(
                                        'Warning (' . $sCurrentSection . ', line ' . $nLine . '): ' . $aSection['update_columns_not_allowed'][$sSameCol]['message'] .
                                        ' Value is currently ' . (isset($aDifferences[$sSameCol]['DB'])? '"' . htmlspecialchars($aDifferences[$sSameCol]['DB']) . '"' : 'empty') .
                                        ' and the value in the import file is ' . (isset($aDifferences[$sSameCol]['file'])? '"' . htmlspecialchars($aDifferences[$sSameCol]['file']) . '"' : 'empty') . '.<BR>', 'done');
                                    $nWarnings ++;
                                } elseif ($aSection['update_columns_not_allowed'][$sSameCol]['error_type'] == 'hard') {
                                    lovd_errorAdd('import',
                                        'Error (' . $sCurrentSection . ', line ' . $nLine . '): Can\'t update ' . $sSameCol . ' for ' . $sCurrentAuthorization . ' entry ' . htmlspecialchars($aLine['id']) . ': ' . $aSection['update_columns_not_allowed'][$sSameCol]['message'] .
                                        ' Value is currently ' . (isset($aDifferences[$sSameCol]['DB'])? '"' . htmlspecialchars($aDifferences[$sSameCol]['DB']) . '"' : 'empty') .
                                        ' and value in the import file is ' . (isset($aDifferences[$sSameCol]['file'])? '"' . htmlspecialchars($aDifferences[$sSameCol]['file']) . '"' : 'empty') . '.');
                                }
                            }
                        }
                    }
                } else {
                    lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): This line refers to a non-existing entry. When the import mode is set to update, no new inserts can be done.');
                    if (!lovd_endLine()) {
                        break;
                    }
                    continue;
                }
            }

            if (isset($aSection['object']) && is_object($aSection['object'])) {
                // Object has been created.
                // We'll need to split the functional consequence field to have checkFields() function normally.
                if ($sCurrentSection == 'Variants_On_Genome' || $sCurrentSection == 'Variants_On_Transcripts') {
                    $aLine['effect_reported'] = substr($_SETT['var_effect_default'], 0, 1); // Default value.
                    $aLine['effect_concluded'] = substr($_SETT['var_effect_default'], -1); // Default value.
                    if (in_array('effectid', $aColumns)) {
                        if (strlen($aLine['effectid']) != 2) {
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Please select a valid entry for the \'effectid\' field.');
                        } else {
                            $aLine['effect_reported'] = $aLine['effectid']{0};
                            $aLine['effect_concluded'] = $aLine['effectid']{1};
                        }
                    }
                }



                // Use the object's checkFields() to have the values checked.
                $nErrors = count($_ERROR['messages']); // We'll need to mark the generated errors.
                $aSection['object']->checkFields($aLine, $zData);
                for ($i = $nErrors; isset($_ERROR['messages'][$i]); $i++) {
                    // When updating, if a error is triggered by a field that is
                    // not in the file, then this error is unrelated to the data
                    // currently being processed so we should ignore the error.
                    if ($sMode == 'update' && !empty($_ERROR['fields'][$i]) && !in_array($_ERROR['fields'][$i], $aColumns)) {
                        // Ignoring error!
                        unset($_ERROR['fields'][$i], $_ERROR['messages'][$i]);
                        continue;
                    }
                    $_ERROR['fields'][$i] = ''; // It wants to highlight a field that's not here right now.
                    $_ERROR['messages'][$i] = 'Error (' . $sCurrentSection . ', line ' . $nLine . '): ' . $_ERROR['messages'][$i];
                }
                // Clean array so that next time we loop it (next data line), we won't see empty spaces.
                $_ERROR['fields'] = array_values($_ERROR['fields']);
                $_ERROR['messages'] = array_values($_ERROR['messages']);
            }

            // General checks: numerical ID, have we seen the ID before, owned_by, created_* and edited_*.
            if (!empty($aLine['id'])) {
                if ($sCurrentSection == 'Columns' || $sCurrentSection == 'Genes') {
                    $ID = $aLine['id'];
                } else {
                    $ID = (int) $aLine['id'];
                }
                if (isset($aSection['data'][$ID])) {
                    // We saw this ID before in this file!
                    lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): ID "' . htmlspecialchars($aLine['id']) . '" already defined at line ' . $aSection['data'][$ID]['nLine'] . '.');
                    if (!lovd_endLine()) {
                        break;
                    }
                    continue; // Skip to next line.
                }
            }
            if (in_array($sCurrentSection, array('Columns', 'Genes', 'Diseases', 'Individuals', 'Phenotypes', 'Screenings', 'Variants_On_Genome'))) {
                foreach (array('created_by', 'edited_by') as $sCol) {
                    // Check is not needed for owned_by, because the form should have a selection list (which is checked separately).
                    if ($zData && $sCol == 'edited_by') {
                        // If zData is set, always set the edited by.
                        $aLine[$sCol] = $_AUTH['id'];
                    } elseif (!$zData || in_array($sCol, $aColumns)) {
                        if ($aLine[$sCol] && !in_array($aLine[$sCol], $aUsers)) {
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): ' . $sCol . ' value "' . htmlspecialchars($aLine[$sCol]) . '" refers to non-existing user.');
                        } elseif (($sCol != 'edited_by' || $aLine['edited_date']) && !$aLine[$sCol]) {
                            // Edited_by is only filled in if empty and edited_date is filled in.
                            $aLine[$sCol] = $_AUTH['id'];
                        }
                    }
                }
                foreach (array('created_date', 'edited_date') as $sCol) {
                    if ($zData && $sCol == 'edited_date') {
                        // If zData is set, always set the edited date.
                        $aLine[$sCol] = $sDate;
                    } elseif (!$zData || in_array($sCol, $aColumns)) {
                        if ($aLine[$sCol] && !lovd_matchDate($aLine[$sCol], true)) {
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): ' . $sCol . ' value "' . htmlspecialchars($aLine[$sCol]) . '" is not a correct date format, use the format YYYY-MM-DD HH:MM:SS.');
                        } elseif (($sCol == 'created_date' || $aLine['edited_by']) && !$aLine[$sCol]) {
                            // Edited_date is only filled in if empty and edited_by is filled in.
                            $aLine[$sCol] = $sDate;
                        }
                    }
                }
                // Can't be edited earlier than created.
                if (isset($aLine['edited_date']) && $aLine['edited_date'] && $aLine['edited_date'] < $aLine['created_date']) {
                    $aLine['edited_date'] = $aLine['created_date'];
                }
                // If you're not manager or higher, there are some restrictions.
                if ($_AUTH['level'] < LEVEL_MANAGER) {
                    $aLine['created_by'] = $_AUTH['id'];
                    $aLine['created_date'] = $sDate;
                    if ($aLine['edited_by']) {
                        $aLine['edited_by'] = $_AUTH['id'];
                    }
                }
            }





            // Per category, verify the data, including precise checks on specific columns.
            switch ($sCurrentSection) {
                case 'Columns':
                    // First check if column exist in database. If exists in the database, this column is not imported but import will continue.
                    if (isset($aSection['ids'][$aLine['id']]) && !$zData) {
                        $_BAR[0]->appendMessage('Warning: There is already a ' . htmlspecialchars($aLine['category']) . ' column with column ID ' . htmlspecialchars($aLine['colid']) . '. This column is not imported! <BR>', 'done');
                        $nWarnings ++;
                        // break: None of the following checks have to be done because column is not imported.
                        break;
                    }
                    // Following checks are not present in checkFields() because they come from the data type wizard. And therefore repeated here.
                    // Col_order; numeric and 0 <= col_order <= 255.
                    if ($aLine['col_order'] === '') {
                        $aLine['col_order'] = 0;
                    } elseif (!ctype_digit($aLine['col_order']) || $aLine['col_order'] < 0 || $aLine['col_order'] > 255) {
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Incorrect value for field \'col_order\', which needs to be numeric, between 0 and 255.');
                    }
                    // All integer columns that are checkboxes on the form, are turned into empty strings by checkFields(), but we'll verify them here.
                    // FIXME: Define this array elsewhere?
                    foreach (array('standard', 'mandatory', 'public_view', 'public_add', 'allow_count_all') as $sCol) {
                        if ($aLine[$sCol] === '') {
                            $aLine[$sCol] = 0;
                        } elseif (!ctype_digit($aLine[$sCol]) || $aLine[$sCol] > 1) {
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Incorrect value for field \'' . $sCol . '\', which should be 0 or 1.');
                        }
                    }
                    // Select_options.
                    if (!empty($aLine['select_options'])) {
                        $aOptions = explode("\r\n", $aLine['select_options']);
                        foreach ($aOptions as $n => $sOption) {
                            if (!preg_match('/^([^=]+|[A-Z0-9 \/\()?._+-]+ *= *[^=]+)$/i', $sOption)) {
                                lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Select option #' . ($n + 1) . ' &quot;' . htmlspecialchars($sOption) . '&quot; not understood.');
                            }
                        }
                    }
                    // Check regexp syntax.
                    if (!empty($aLine['preg_pattern']) && ($aLine['preg_pattern']{0} != '/' || @preg_match($aLine['preg_pattern'], '') === false)) {
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): The \'Regular expression pattern\' field does not seem to contain valid PHP Perl compatible regexp syntax.');
                    }

                    if ($zData) {
                        if ($nDifferences) {
                            $aLine['todo'] = 'update'; // OK, update only when there are differences.
                        }
                    } else {
                        // HGVS, never allowed.
                        if ($aLine['hgvs']) {
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Not allowed to create new HGVS standard columns. Change the value for \'hgvs\' to 0.');
                        } else {
                            $aLine['hgvs'] = 0; // In case it doesn't exist in the file, which creates a query error.
                        }
                        // FIXME: Default values?
                        // Entry might still have thrown an error, but because we want to draw out all errors, we will store this one in case it's referenced to.
                        $aLine['todo'] = 'insert'; // OK, insert.
                    }
                    break;

                case 'Genes':
                    // FIXME: It's not clear why it checks for filetype Genes. For sure, a curator should never be allowed to do this, though.
                    if ($sFileType != 'Genes' && !isset($aSection['ids'][$aLine['id']])) {
                        // Do not allow genes that are not in the database, if we're not importing genes!
//                        $_BAR[0]->appendMessage('Warning: gene "' . htmlspecialchars($aLine['id'] . '" (' . $aLine['name']) . ') does not exist in the database. Currently, it is not possible to import genes into LOVD using this file format.<BR>', 'done');
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Gene "' . htmlspecialchars($aLine['id'] . '" (' . $aLine['name']) . ') does not exist in the database. Currently, it is not possible to import genes into LOVD using this file format.');
                        break;
                    }
                    if ($zData && $nDifferences) {
                        $aLine['todo'] = 'update'; // OK, update only when there are differences.
                    }
                    break;

                case 'Transcripts':
                    // FIXME: It's not clear why it checks for filetype Genes and Transcripts.
                    if ($sFileType != 'Genes' && $sFileType != 'Transcripts') {
                        // Not importing genes or transcripts. Allowed are references to existing transcripts only!!!
//                        $_BAR[0]->appendMessage('Warning: transcript "' . htmlspecialchars($aLine['id'] . '" (' . $aLine['geneid'] . ', ' . $aLine['name']) . ') does not exist in the database. Currently, it is not possible to import transcripts into LOVD using this file format.<BR>', 'done');
                        // FIXME: If we'll allow the creation of transcripts, and we have an object, we can use $zData here.
                        // Transcript has been found in the database, check if NM and gene are the same. The rest we will ignore.
                        $nTranscriptid = $_DB->query('SELECT id FROM ' . TABLE_TRANSCRIPTS . ' WHERE geneid = ? AND id_ncbi = ?', array($aLine['geneid'], $aLine['id_ncbi']))->fetchColumn();
                        if ($nTranscriptid) {
                            $aLine['newID'] = $nTranscriptid;
                            $aLine['todo'] = 'map';
                        } else {
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Transcript "' . htmlspecialchars($aLine['id']) . '" does not match the same gene and/or the same NCBI ID as in the database.');
                            $aLine['todo'] = '';
                        }

                        if ($zData && $nDifferences) {
                            $aLine['todo'] = 'update'; // OK, update only when there are differences.
                        }
                    }
                    break;

                case 'Diseases':
                    // We actually need to perform the same checks that are in the checkFields() to prevent double diseases, here, but then compare to the other diseases in this file.
                    foreach ($aSection['data'] as $nID => $aDisease) {
                        // Two diseases with the same OMIM ID are not allowed.
                        if ($aLine['id_omim'] && $aLine['id_omim'] == $aDisease['id_omim']) {
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Another disease already exists with this OMIM ID at line ' . $aSection['data'][$nID]['nLine'] . '.');
                        }
                        // We don't like two diseases with the exact same name, either.
                        if ($aLine['name'] && $aLine['name'] == $aDisease['name']) {
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Another disease already exists with the same name at line ' . $aSection['data'][$nID]['nLine'] . '.');
                        }
                    }

                    if ($zData) {
                        // Diseases is the only table with a record for ID 0.
                        // This ID is reserved for healthy individual / control and is not allowed to change.
                        // Changes on this record are ignored.
                        if ($nDifferences && (int) $zData['id'] !== 0) {
                            $aLine['todo'] = 'update'; // OK, update only when there are differences.
                        }
                    } else {
                        // Create: Attempt to map the disease in the file to a disease in the database.
                        $rDiseaseIdOmim = $_DB->query('SELECT id, id_omim FROM ' . TABLE_DISEASES . ' WHERE name = ?', array($aLine['name']))->fetchRow();
                        if ($rDiseaseIdOmim && !$rDiseaseIdOmim[1] && $aLine['id_omim']) {
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Import file contains OMIM ID for disease ' . $aLine['name'] . ', while OMIM ID is missing in database.');
                        }
                        if ($rDiseaseIdOmim && (($rDiseaseIdOmim[1] == $aLine['id_omim']) || ($rDiseaseIdOmim[1] && !$aLine['id_omim']) || (!$rDiseaseIdOmim[1] && !$aLine['id_omim']))) {
                            // Some error added in checkFields() should be removed because soft messages are used.
                            $nKey = array_search('Error (' . $sCurrentSection . ', line ' . $nLine . '): Another disease already exists with the same name!', $_ERROR['messages']);
                            // When key is false, no errors are set in checkFields().
                            if ($nKey !== false) {
                                unset($_ERROR['messages'][$nKey]);
                                $_ERROR['messages'] = array_values($_ERROR['messages']);
                            }

                            $nKey = array_search('Error (' . $sCurrentSection . ', line ' . $nLine . '): Another disease already exists with this OMIM ID!', $_ERROR['messages']);
                            // When key is false, no errors are set in checkFields().
                            if ($nKey !== false) {
                                unset($_ERROR['messages'][$nKey]);
                                $_ERROR['messages'] = array_values($_ERROR['messages']);
                            }

                            // Do not set soft warnings when we do an update.
                            $_BAR[0]->appendMessage('Warning (' . $sCurrentSection . ', line ' . $nLine . '): There is already a disease with disease name ' . $aLine['name'] . (empty($aLine['id_omim'])? '' : ' and/or OMIM ID ' . $aLine['id_omim']) . '. This disease is not imported! <BR>', 'done');
                            $nWarnings ++;

                            $aLine['newID'] = $rDiseaseIdOmim[0];
                            $aLine['todo'] = 'map';
                            break;
                        }

                        // We're inserting. Curators at this moment are not allowed to insert diseases.
                        if ($_AUTH['level'] < LEVEL_MANAGER) {
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Access denied, currently manager level is required to import new disease entries.');
                        } else {
                            // Entry might still have thrown an error, but because we want to draw out all errors, we will store this one in case it's referenced to.
                            $aLine['todo'] = 'insert'; // OK, insert.
                        }
                    }
                    break;

                case 'Genes_To_Diseases':
                    // Editing will never be supported. Any change breaks the PK, so which entry would we edit?
                    //  Unless we would give preference over the first key (Genes, in this case), and would replace all entries of the first key with the one(s) in the file.
                    // First check if $zData is filled. If so, break and ignore the rest of this section.
                    if ($zData) {
                        // This section cannot be updated during an import. So ther is no need to do the checks or give warnings or errors.
                        break;
                    }

                    // Create ID, so we can link to the data.
                    $aLine['id'] = $aLine['geneid'] . '|' . (int) $aLine['diseaseid']; // This also means we lack the check for repeated lines!
                    // Manually check for repeated lines, to prevent query errors in case of inserts.
                    if (isset($aSection['data'][$aLine['id']])) {
                        // We saw this ID before in this file!
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): ID "' . htmlspecialchars($aLine['id']) . '" already defined at line ' . $aSection['data'][$aLine['id']]['nLine'] . '.');
                        break; // Stop processing this line.
                    }

                    // Check references.
                    $bGeneInDB = isset($aParsed['Genes']['ids'][$aLine['geneid']]);
                    $bGeneInFile = !$bGeneInDB; // FIXME: Do this properly, when genes are allowed to be imported.
                    if ($aLine['geneid'] && !$bGeneInDB) {
                        // Gene does not exist.
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Gene "' . htmlspecialchars($aLine['geneid']) . '" does not exist in the database.');
                    }
                    $nNewID = (!isset($aParsed['Diseases']['data'][(int) $aLine['diseaseid']]['newID'])? false : $aParsed['Diseases']['data'][(int) $aLine['diseaseid']]['newID']);
                    if ($nNewID !== false) {
                        $bDiseaseInDB = isset($aParsed['Diseases']['ids'][(int) $nNewID]);
                    } else {
                        $bDiseaseInDB = isset($aParsed['Diseases']['ids'][(int) $aLine['diseaseid']]);
                    }
                    $bDiseaseInFile = isset($aParsed['Diseases']['data'][(int) $aLine['diseaseid']]);
                    if ($aLine['diseaseid'] && !$bDiseaseInFile && !$bDiseaseInDB) {
                        // Disease does not exist and is not defined in the import file.
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Disease "' . htmlspecialchars($aLine['diseaseid']) . '" does not exist in the database and is not defined in this import file.');
                    } elseif ($bGeneInDB) {
                        // No problems left, just check now if insert is necessary or not.
                        if (!$bDiseaseInDB || ($sMode == 'insert' && $nNewID === false && $bDiseaseInFile)) {
                            // Disease is in file (will be inserted, or it has generated errors), so flag this to be inserted!
                            $aLine['todo'] = 'insert';
                        } else {
                            $aSQL = array($aLine['geneid']);
                            if (isset($aParsed['Diseases']['data'][(int) $aLine['diseaseid']]['newID'])) {
                                $aSQL[] = $aParsed['Diseases']['data'][(int) $aLine['diseaseid']]['newID'];
                            } else {
                                $aSQL[] = $aLine['diseaseid'];
                            }
                            // Gene & Disease are already in the DB, check if we can't find this combo in the DB, it needs to be inserted. Otherwise, we'll ignore it.
                            $bInDB = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_GEN2DIS . ' WHERE geneid = ? AND diseaseid = ?', $aSQL)->fetchColumn();
                            if (!$bInDB) {
                                $aLine['todo'] = 'insert';
                            }
                        }
                    }

                    if (isset($aLine['todo']) && $aLine['todo'] == 'insert') {
                        // Inserting, check rights, but only if we're handling a gene *not* in the file, but in the database.
                        // Note: file gets preference over database, so we can't just check for $bGeneInDB.
                        if ($_AUTH['level'] < LEVEL_MANAGER && !$bGeneInFile && !lovd_isAuthorized('gene', $aLine['geneid'], false)) {
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Access denied, you are not authorized to connect this gene to this disease.');
                        }
                    }
                    break;

                case 'Individuals':
                    // Panel, Father and Mother IDs are checked.
                    // It's assumed that in the import file, panels and parents are listed before panel individuals and children.
                    if ($aLine['panelid']) {
                        $bPanelInDB = isset($aParsed['Individuals']['ids'][(int) $aLine['panelid']]);
                        $bPanelInFile = isset($aParsed['Individuals']['data'][(int) $aLine['panelid']]);
                        if (!$bPanelInDB && !$bPanelInFile) {
                            // Individual does not exist and is not defined in the import file.
                            // Or the panel to which is referred is not yet parsed.
                            // It's assumed that in the import file panels are listed above individuals referring to them.
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Individual "' . htmlspecialchars($aLine['panelid']) . '" does not exist in the database and is not defined (properly) in this import file.<BR>When referring to panels that are also defined in the import file, make sure they are defined above the individuals referring to them. Therefore, make sure that in the import file individual "' . htmlspecialchars($aLine['panelid']) . '" is defined above individual "' . htmlspecialchars($aLine['id']) . '".');
                        }

                        // It is not allowed to import a record where the panelid and id are the same.
                        if ($aLine['panelid'] == $aLine['id']) {
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): The \'Panel ID\' can not link to itself; this field is used to indicate to which panel this individual belongs.');
                        }

                        // A panel from the import file is preferred, as that describes the new
                        // panel size to which the new records must conform.
                        $nPanel = false;
                        if ($bPanelInFile) {
                            $nPanel = $aParsed['Individuals']['data'][(int) $aLine['panelid']]['panel_size'];
                        } elseif ($bPanelInDB) {
                            $nPanel = $_DB->query('SELECT panel_size FROM ' . TABLE_INDIVIDUALS .
                                                  ' WHERE id = ?', array($aLine['panelid']))->fetchColumn();
                        }

                        if ($nPanel !== false && $nPanel == 1) {
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Panel ID "' . htmlspecialchars($aLine['panelid']) . '" refers to an individual, not a panel (group of individuals). If you want to configure that individual as a panel, set its \'Panel size\' field to a value higher than 1.');
                        } elseif ($nPanel !== false && $nPanel <= $aLine['panel_size']) {
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Panel size of Individual "' . htmlspecialchars($aLine['id']) . '" must be lower than the panel size of Individual "' . htmlspecialchars($aLine['panelid']) . '".');
                        }
                    }

                    foreach (array('fatherid', 'motherid') as $sParentalField) {
                        if ($aLine[$sParentalField]) {
                            $bParentInDB = isset($aParsed['Individuals']['ids'][(int) $aLine[$sParentalField]]);
                            $bParentInFile = isset($aParsed['Individuals']['data'][(int) $aLine[$sParentalField]]);
                            if (!$bParentInDB && !$bParentInFile) {
                                // Individual does not exist and is not defined in the import file.
                                // Or the individual to which is referred is not yet parsed.
                                // It's assumed that in the import file parent are on top of children.
                                lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Individual "' . htmlspecialchars($aLine[$sParentalField]) . '" does not exist in the database and is not defined (properly) in this import file.<BR>When referring to parents that are also defined in the import file, make sure they are defined above the children referring to them. Therefore, make sure that in the import file individual "' . htmlspecialchars($aLine[$sParentalField]) . '" is defined above individual "' . htmlspecialchars($aLine['id']) . '".');
                            }

                            // It is not allowed to import a record where the fatherid or motherid are the same as the individual id.
                            if ($aLine[$sParentalField] == $aLine['id']) {
                                lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): The \'' . $sParentalField . '\' can not link to itself; this field is used to indicate which individual in the database is the parent of the given individual.');
                            }

                            if ($_DB->query('SELECT ac.colid FROM ' . TABLE_ACTIVE_COLS . ' AS ac WHERE ac.colid = ?', array('Individual/Gender'))->fetchColumn()) {
                                $zParentData = array();
                                if ($bParentInDB) {
                                    $zParentData = $_DB->query('SELECT `Individual/Gender`, panel_size FROM ' . TABLE_INDIVIDUALS . ' WHERE id = ?', array($aLine[$sParentalField]))->fetchAssoc();
                                } elseif ($bParentInFile) {
                                    $zParentData = $aParsed['Individuals']['data'][(int) $aLine[$sParentalField]];
                                }

                                if (isset($zParentData['panel_size']) && $zParentData['panel_size'] > 1) {
                                    lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): The ' . $sParentalField . ' "' . htmlspecialchars($aLine[$sParentalField]) . '" refers to an panel (group of individuals), not an individual. If you want to configure that panel as an individual, set its \'Panel size\' field to value 1.');
                                }

                                if (isset($zParentData['Individual/Gender']) && $sParentalField == 'fatherid' && $zParentData['Individual/Gender'] == 'F') {
                                    lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): The ' . $sParentalField . ' "' . htmlspecialchars($aLine[$sParentalField]) . '" you entered does not refer to a male individual.');
                                } elseif (isset($zParentData['Individual/Gender']) && $sParentalField == 'motherid' && $zParentData['Individual/Gender'] == 'M') {
                                    lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): The ' . $sParentalField . ' "' . htmlspecialchars($aLine[$sParentalField]) . '"  you entered does not refer to a female individual.');
                                }
                            }
                        }
                    }

                    if ($zData) {
                        if ($nDifferences) {
                            $aLine['todo'] = 'update'; // OK, update only when there are differences.
                        }
                    } else {
                        // FIXME: Default values of custom columns?
                        // Entry might still have thrown an error, but because we want to draw out all errors, we will store this one in case it's referenced to.
                        $aLine['todo'] = 'insert'; // OK, insert.
                    }
                    break;

                case 'Individuals_To_Diseases':
                    // Editing will never be supported. Any change breaks the PK, so which entry would we edit?
                    //  Unless we would give preference over the first key (Individuals, in this case), and would replace all entries of the first key with the one(s) in the file.
                    // First check if $zData is filled. If so, break and ignore the rest of this section.
                    if ($zData) {
                        // This section cannot be updated during an import. So ther is no need to do the checks or give warnings or errors.
                        break;
                    }

                    // Create ID, so we can link to the data.
                    $aLine['id'] = (int) $aLine['individualid'] . '|' . (int) $aLine['diseaseid']; // This also means we lack the check for repeated lines!
                    // Manually check for repeated lines, to prevent query errors in case of inserts.
                    if (isset($aSection['data'][$aLine['id']])) {
                        // We saw this ID before in this file!
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): ID "' . htmlspecialchars($aLine['id']) . '" already defined at line ' . $aSection['data'][$aLine['id']]['nLine'] . '.');
                        break; // Stop processing this line.
                    }

                    // Check references.
                    $bIndInDB = isset($aParsed['Individuals']['ids'][(int) $aLine['individualid']]);
                    $bIndInFile = isset($aParsed['Individuals']['data'][(int) $aLine['individualid']]);
                    if ($aLine['individualid'] && !$bIndInDB && !$bIndInFile) {
                        // Individual does not exist and is not defined in the import file.
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Individual "' . htmlspecialchars($aLine['individualid']) . '" does not exist in the database and is not defined in this import file.');
                    }
                    $nNewID = (!isset($aParsed['Diseases']['data'][(int) $aLine['diseaseid']]['newID'])? false : $aParsed['Diseases']['data'][(int) $aLine['diseaseid']]['newID']);
                    if ($nNewID !== false) {
                        $bDiseaseInDB = isset($aParsed['Diseases']['ids'][(int) $nNewID]);
                    } else {
                        $bDiseaseInDB = isset($aParsed['Diseases']['ids'][(int) $aLine['diseaseid']]);
                    }
                    $bDiseaseInFile = isset($aParsed['Diseases']['data'][(int) $aLine['diseaseid']]);
                    if ($aLine['diseaseid'] && !$bDiseaseInFile && !$bDiseaseInDB) {
                        // Disease does not exist and is not defined in the import file.
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Disease "' . htmlspecialchars($aLine['diseaseid']) . '" does not exist in the database and is not defined in this import file.');
                    } elseif ($bIndInDB || $bIndInFile) {
                        // No problems left, just check now if insert is necessary or not.
                        if (!$bIndInDB || !$bDiseaseInDB || ($sMode == 'insert' && ($bIndInFile || $bDiseaseInFile))) {
                            // Individual not in database, Disease not in database or we're inserting and one of the two is in the file, so flag this to be inserted!
                            $aLine['todo'] = 'insert';
                        } else {
                            // Individual & Disease are already in the DB, check if we can't find this combo in the DB, it needs to be inserted. Otherwise, we'll ignore it.
                            $bInDB = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_IND2DIS . ' WHERE individualid = ? AND diseaseid = ?', array($aLine['individualid'], $aLine['diseaseid']))->fetchColumn();
                            if (!$bInDB) {
                                $aLine['todo'] = 'insert';
                            }
                        }
                    }

                    if (isset($aLine['todo']) && $aLine['todo'] == 'insert') {
                        // Inserting, check rights, but only if we're handling an individual *not* in the file, but in the database.
                        // Note: file gets preference over database, so we can't just check for $bIndInDB.
                        if ($_AUTH['level'] < LEVEL_MANAGER && !$bIndInFile && !lovd_isAuthorized('individual', $aLine['individualid'], false)) {
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Access denied, you are not authorized to connect this individual to this disease.');
                        }
                    }
                    break;

                case 'Phenotypes':
                    // FIXME: Check references only if we don't have a $zData OR $zData['referenceid'] is different from now?
                    //   Actually, do we allow references to change during an edit?
                    // Check references.
                    $nNewID = (!isset($aParsed['Diseases']['data'][(int) $aLine['diseaseid']]['newID'])? false : $aParsed['Diseases']['data'][(int) $aLine['diseaseid']]['newID']);
                    if ($nNewID !== false) {
                        $bDiseaseInDB = isset($aParsed['Diseases']['ids'][(int) $nNewID]);
                    } else {
                        $bDiseaseInDB = isset($aParsed['Diseases']['ids'][(int) $aLine['diseaseid']]);
                    }
                    $bDiseaseInFile = isset($aParsed['Diseases']['data'][(int) $aLine['diseaseid']]);
                    if ($aLine['diseaseid'] && !$bDiseaseInFile && !$bDiseaseInDB) {
                        // Disease does not exist and is not defined in the import file.
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Disease "' . htmlspecialchars($aLine['diseaseid']) . '" does not exist in the database and is not defined in this import file.');
                    } elseif (!$bDiseaseInDB && $sMode == 'insert' && $bDiseaseInFile && !in_array($aLine['diseaseid'], $aDiseasesAlreadyWarnedFor)) {
                        // We're inserting this disease, so we're not sure about the exact columns that will be active. Issue a warning.
                        $_BAR[0]->appendMessage('Warning (' . $sCurrentSection . ', line ' . $nLine . '): The disease belonging to this phenotype entry is yet to be inserted into the database. Perhaps not all this phenotype entry\'s custom columns will be enabled for this disease!<BR>', 'done');
                        $nWarnings ++;
                        $aDiseasesAlreadyWarnedFor[] = $aLine['diseaseid'];
                    }
                    $bIndInDB = isset($aParsed['Individuals']['ids'][(int) $aLine['individualid']]);
                    $bIndInFile = isset($aParsed['Individuals']['data'][(int) $aLine['individualid']]);
                    if ($aLine['individualid'] && !$bIndInDB && !$bIndInFile) {
                        // Individual does not exist and is not defined in the import file.
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Individual "' . htmlspecialchars($aLine['individualid']) . '" does not exist in the database and is not defined in this import file.');
                    }

                    if ($zData) {
                        if ($nDifferences) {
                            $aLine['todo'] = 'update'; // OK, update only when there are differences.
                        }
                    } else {
                        // FIXME: Default values of custom columns?
                        // Entry might still have thrown an error, but because we want to draw out all errors, we will store this one in case it's referenced to.
                        $aLine['todo'] = 'insert'; // OK, insert.
                    }
                    break;

                case 'Screenings':
                    // FIXME: Check references only if we don't have a $zData OR $zData['referenceid'] is different from now?
                    //   Actually, do we allow references to change during an edit?
                    // Check references.
                    $bIndInDB = isset($aParsed['Individuals']['ids'][(int) $aLine['individualid']]);
                    $bIndInFile = isset($aParsed['Individuals']['data'][(int) $aLine['individualid']]);
                    if ($aLine['individualid'] && !$bIndInDB && !$bIndInFile) {
                        // Individual does not exist and is not defined in the import file.
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Individual "' . htmlspecialchars($aLine['individualid']) . '" does not exist in the database and is not defined in this import file.');
                    }

                    if ($zData) {
                        if ($nDifferences) {
                            $aLine['todo'] = 'update'; // OK, update only when there are differences.
                        }
                    } else {
                        // FIXME: Default values of custom columns?
                        // Entry might still have thrown an error, but because we want to draw out all errors, we will store this one in case it's referenced to.
                        $aLine['todo'] = 'insert'; // OK, insert.
                    }
                    break;

                case 'Screenings_To_Genes':
                    // Editing will never be supported. Any change breaks the PK, so which entry would we edit?
                    //  Unless we would give preference over the first key (Screenings, in this case), and would replace all entries of the first key with the one(s) in the file.
                    // First check if $zData is filled. If so, break and ignore the rest of this section.
                    if ($zData) {
                        // This section cannot be updated during an import. So ther is no need to do the checks or give warnings or errors.
                        break;
                    }

                    // Create ID, so we can link to the data.
                    $aLine['id'] = (int) $aLine['screeningid'] . '|' . $aLine['geneid']; // This also means we lack the check for repeated lines!
                    // Manually check for repeated lines, to prevent query errors in case of inserts.
                    if (isset($aSection['data'][$aLine['id']])) {
                        // We saw this ID before in this file!
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): ID "' . htmlspecialchars($aLine['id']) . '" already defined at line ' . $aSection['data'][$aLine['id']]['nLine'] . '.');
                        break; // Stop processing this line.
                    }

                    // Check references.
                    $bGeneInDB = isset($aParsed['Genes']['ids'][$aLine['geneid']]);
                    if ($aLine['geneid'] && !$bGeneInDB) {
                        // Gene does not exist.
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Gene "' . htmlspecialchars($aLine['geneid']) . '" does not exist in the database.');
                    }
                    $bScreeningInDB = isset($aParsed['Screenings']['ids'][(int) $aLine['screeningid']]);
                    $bScreeningInFile = isset($aParsed['Screenings']['data'][(int) $aLine['screeningid']]);
                    if ($aLine['screeningid'] && !$bScreeningInFile && !$bScreeningInDB) {
                        // Screening does not exist and is not defined in the import file.
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Screening "' . htmlspecialchars($aLine['screeningid']) . '" does not exist in the database and is not defined in this import file.');
                    } elseif ($bGeneInDB) {
                        // No problems left, just check now if insert is necessary or not.
                        if (!$bScreeningInDB || ($sMode == 'insert' && $bScreeningInFile)) {
                            // Screening is in file (will be inserted, or it has generated errors), so flag this to be inserted!
                            $aLine['todo'] = 'insert';
                        } else {
                            // Gene & Screening are already in the DB, check if we can't find this combo in the DB, it needs to be inserted. Otherwise, we'll ignore it.
                            $bInDB = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_SCR2GENE . ' WHERE geneid = ? AND screeningid = ?', array($aLine['geneid'], $aLine['screeningid']))->fetchColumn();
                            if (!$bInDB) {
                                $aLine['todo'] = 'insert';
                            }
                        }
                    }
                    break;

                case 'Variants_On_Genome':
                    if ($zData) {
                        if ($nDifferences) {
                            $aLine['todo'] = 'update'; // OK, update only when there are differences.
                        }
                    } else {
                        if ($aLine['allele'] === '') {
                            $aLine['allele'] = 0;
                        }
                        if ($aLine['mapping_flags'] === '') {
                            $aLine['mapping_flags'] = 0;
                        }
                        // FIXME: Default values of custom columns?
                        // Entry might still have thrown an error, but because we want to draw out all errors, we will store this one in case it's referenced to.
                        $aLine['todo'] = 'insert'; // OK, insert.
                    }
                    break;

                case 'Variants_On_Transcripts':
                    // Create ID, so we can link to the data.
                    $aLine['variantid'] = $aLine['id'];
                    $aLine['id'] = (int) $aLine['id'] . '|' . (int) $aLine['transcriptid'];
                    // Manually check for repeated lines, to prevent query errors in case of inserts.
                    if (isset($aSection['data'][$aLine['id']])) {
                        // We saw this ID before in this file!
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): ID "' . htmlspecialchars($aLine['id']) . '" already defined at line ' . $aSection['data'][$aLine['id']]['nLine'] . '.');
                        break; // Stop processing this line.
                    }

                    // FIXME: Check references only if we don't have a $zData OR $zData['referenceid'] is different from now?
                    //   Actually, do we allow references to change during an edit?
                    // Check references. Don't forget that the references to transcripts have already been checked earlier
                    //   in the code, because we had to initialize the object using the geneid of the given transcript.
// FIXME: Check if combination is already known in the database, since this is partially also a linking table!!!
//   Combi already in DB: then no insert mode allowed, otherwise get $zData? Or do we have that already?
                    $bVariantInDB = isset($aParsed['Variants_On_Genome']['ids'][(int) $aLine['variantid']]);
                    $bVariantInFile = isset($aParsed['Variants_On_Genome']['data'][(int) $aLine['variantid']]);
                    if ($aLine['id'] && !$bVariantInFile && !$bVariantInDB) {
                        // Variant does not exist and is not defined in the import file.
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Genomic Variant "' . htmlspecialchars($aLine['variantid']) . '" does not exist in the database and is not defined in this import file.');
                    }

                    if ($zData) {
                        if ($nDifferences) {
                            $aLine['todo'] = 'update'; // OK, update only when there are differences.
                        }
                    } else {
                        // FIXME: Default values of custom columns?

                        // FIXME: Check if referenced variant is actually on the same chromosome?

                        if (!$bGeneInDB) {
                            // We're inserting this variant, but the gene does not exist yet, so we're not sure about the exact columns that will be active. For variants, this is fatal.
                            //   Actually, this error will always come with the error that the gene mentioned in the file is not yet inserted and that it can't be inserted by this script, right?
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): The gene belonging to this variant entry is yet to be inserted into the database. First create the gene and set up the custom columns, then import the variants.');
                        }

                        // Entry might still have thrown an error, but because we want to draw out all errors, we will store this one in case it's referenced to.
                        if (!$bVariantInDB || !$bTranscriptInDB || ($sMode == 'insert' && ($bVariantInFile || $bTranscriptInFile))) {
                            // Variant and/or Transcript is in file (will be inserted), so flag this to be inserted!
                            $aLine['todo'] = 'insert';
                        } else {
                            // Variant & Transcript are already in the DB, check if we can't find this combo in the DB, it needs to be inserted. Otherwise, we'll ignore it.
                            $bInDB = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' WHERE id = ? AND transcriptid = ?', array($aLine['variantid'], $aLine['transcriptid']))->fetchColumn();
                            if (!$bInDB) {
                                $aLine['todo'] = 'insert';
                            }
                        }
                    }
                    break;

                case 'Screenings_To_Variants':
                    // Editing will never be supported. Any change breaks the PK, so which entry would we edit?
                    //  Unless we would give preference over the first key (Screenings, in this case), and would replace all entries of the first key with the one(s) in the file.
                    // First check if $zData is filled. If so, break and ignore the rest of this section.
                    if ($zData) {
                        // This section cannot be updated during an import. So ther is no need to do the checks or give warnings or errors.
                        break;
                    }

                    // Create ID, so we can link to the data.
                    $aLine['id'] = (int) $aLine['screeningid'] . '|' . (int) $aLine['variantid']; // This also means we lack the check for repeated lines!
                    // Manually check for repeated lines, to prevent query errors in case of inserts.
                    if (isset($aSection['data'][$aLine['id']])) {
                        // We saw this ID before in this file!
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): ID "' . htmlspecialchars($aLine['id']) . '" already defined at line ' . $aSection['data'][$aLine['id']]['nLine'] . '.');
                        break; // Stop processing this line.
                    }

                    // Check references.
                    $bScreeningInDB = isset($aParsed['Screenings']['ids'][(int) $aLine['screeningid']]);
                    $bScreeningInFile = isset($aParsed['Screenings']['data'][(int) $aLine['screeningid']]);
                    if ($aLine['screeningid'] && !$bScreeningInFile && !$bScreeningInDB) {
                        // Screening does not exist and is not defined in the import file.
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Screening "' . htmlspecialchars($aLine['screeningid']) . '" does not exist in the database and is not defined in this import file.');
                    }
                    $bVariantInDB = isset($aParsed['Variants_On_Genome']['ids'][(int) $aLine['variantid']]);
                    $bVariantInFile = isset($aParsed['Variants_On_Genome']['data'][(int) $aLine['variantid']]);
                    if ($aLine['variantid'] && !$bVariantInFile && !$bVariantInDB) {
                        // Variant does not exist and is not defined in the import file.
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Genomic Variant "' . htmlspecialchars($aLine['variantid']) . '" does not exist in the database and is not defined in this import file.');
                    }

                    // No problems left, just check now if insert is necessary or not.
                    if (!$bScreeningInDB || !$bVariantInDB || ($sMode == 'insert' && ($bScreeningInFile || $bVariantInFile))) {
                        // Screening and/or Variant is in file (will be inserted), so flag this to be inserted!
                        $aLine['todo'] = 'insert';
                    } else {
                        // Screening & Variant are already in the DB, check if we can't find this combo in the DB, it needs to be inserted. Otherwise, we'll ignore it.
                        $bInDB = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_SCR2VAR . ' WHERE screeningid = ? AND variantid = ?', array($aLine['screeningid'], $aLine['variantid']))->fetchColumn();
                        if (!$bInDB) {
                            $aLine['todo'] = 'insert';
                        }
                    }
                    break;

                default:
                    // Bug in LOVD. Section allowed, but no data verification programmed.
                    lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Undefined data verification. Please report this bug.');
                    break 2; // Exit parsing.
            }

            // Store line in array, we will run the inserts/updates after parsing the whole file.
            if (isset($aLine['todo'])) {
                $aLine['nLine'] = $nLine;
                $nID = (ctype_digit($aLine['id'])? (int) $aLine['id'] : $aLine['id']);
                $aSection['data'][$nID] = $aLine;
                if ($aLine['todo'] == 'update') {
                    $aSection['data'][$nID]['update_changes'] = $aDifferences;
                }
                if (in_array($aLine['todo'], array('insert', 'update'))) {
                    $nDataTotal ++;
                }
            }

            $_BAR[0]->setProgress(($nLine/$nLines)*100);

            if (!lovd_endLine()) {
                // Too many errors.
                break;
            }
        }

        // Clean up old section, if available.
        if ($sCurrentSection) {
            unset($aSection['columns']);
            unset($aSection['nColumns']);
            unset($aSection['required_columns']);
            unset($aSection['settings']);

            // If we had at least one unknown column in the previous section, we will mention in the output the number of values gone lost.
            // The column name has already been written to the output, so we should simply add command to append the number of lost values.
            if (isset($aUnknownCols) && count($aUnknownCols)) {
                print('<SCRIPT type="text/javascript">' . "\n" .
                      '  var sMessage = $("#lovd_parser_progress_message_done").html();' . "\n");
                foreach ($aLostValues as $sCol => $n) {
                    print('  sMessage = sMessage.replace(/' . preg_quote($sCol, '/') . '/, "' . $sCol . ' (lost ' . $n . ' value' . ($n == 1? '' : 's') . ')");' . "\n");
                }
                print('  $("#lovd_parser_progress_message_done").html(sMessage);' . "\n" .
                      '</SCRIPT>');
                flush();
            }
        }
        unset($aSection); // Unlink reference.
        // Clean up all stored ID lists.
        foreach ($aParsed as $sSection => $aSection) {
            unset($aParsed[$sSection]['ids']);
        }


        // We have to run this after the unset($aSection), else it will mess up the loop.
        if ($sMode == 'update') {
            $sSectionsUpdated = '';
            foreach ($aParsed as $sSection => $aSection) {
                if (strpos($sSection, '_To_') === false) {
                    $bUpdate = false;
                    foreach ($aSection['data'] as $nID => $aData) {
                        // The 'todo' value can be different from 'update', in which case we won't need to do anything.
                        if ($aData['todo'] == 'update' && $aData['update_changes']) {
                            // We only need to update the changed fields that should not be ignored.
                            // So therefore, we need to get rid of the fields with 'ignore' set to true.
                            foreach ($aData['update_changes'] as $sField => $aFieldChanged) {
                                if ($aFieldChanged['ignore'] === true) {
                                    unset($aParsed[$sSection]['data'][$nID]['update_changes'][$sField]);
                                }
                            }
                            $bUpdate = true;
                        }
                    }
                    // The string $sSectionsUpdated is used for a message to inform users which sections were updated.
                    if ($bUpdate) {
                        $sSectionsUpdated .= (!$sSectionsUpdated? '' : ', ') . $sSection;
                    }
                }
            }
        }
        $_BAR[0]->setProgress(100); // To make sure we're at 100% (some errors skip the lovd_endLine()).





        // Intercept simulate (dry run).
        if (!empty($_POST['simulate']) && !lovd_error() && $nDataTotal) {
            // Stop here.
            lovd_errorAdd('', 'Simulation successful: no errors found.');
            if ($sMode == 'update') {
                lovd_errorAdd('', 'The following sections are modified and can be updated: ' . $sSectionsUpdated . '.');
            }
        }





        function lovd_findImportedID ($sSection, $nID)
        {
            // Returns the ID of a certain object as which it was imported in the database.
            // If not found, it will return the given ID.
            global $aParsed;

            if (isset($aParsed[$sSection]['data'][(int) $nID]['newID'])) {
                $nID = $aParsed[$sSection]['data'][(int) $nID]['newID'];
            }
            return $nID;
        }





        // Now we have everything parsed. If there were errors, we are stopping now.
        require ROOT_PATH . 'inc-lib-actions.php';
        if (!lovd_error() && $nDataTotal) {
            define('LOG_EVENT', 'Import');
            print('<BR>');
            $_BAR[] = new ProgressBar('sql', 'Applying changes...');
            $nEntry = 0;
            $bError = false;
            $aDone = array();
            $nDone = 0;
            $_DB->beginTransaction();

            foreach ($aParsed as $sSection => $aSection) {
                $aFields = $aSection['allowed_columns'];
                // We will unset the IDs, and generate new ones. All, but the Column and VOT sections, which don't have an PK AUTO_INCREMENT.
                if (in_array('id', $aFields) && !in_array($sSection, array('Columns', 'Variants_On_Transcripts'))) {
                    unset($aFields[array_search('id', $aFields)]);
                }
                $aDone[$sSection] = 0;

                foreach ($aSection['data'] as $nID => $aData) {
                    if (!$aData['todo'] || !in_array($aData['todo'], array('insert', 'update'))) {
                        continue;
                    }
                    $nEntry++;

                    // Updating?
                    if ($aData['todo'] == 'update') {
                        $aFieldsToUpdate = array_keys($aData['update_changes']);
                        if ($sSection != 'Variants_On_Transcripts') {
                            $aFieldsToUpdate = array_merge($aFieldsToUpdate, array('edited_by', 'edited_date'));
                        }
                        $aSection['object']->updateEntry($nID, $aData, $aFieldsToUpdate);
                        if (isset($aData['statusid']) && $aData['statusid'] >= STATUS_MARKED) {
                            // These updated IDs are used to determine which genes are updated.
                            $aParsed[$sSection]['updatedIDs'][] = $aData['id'];
                        }
                        $aDone[$sSection] ++;
                        $nDone ++;
                        $_BAR[1]->setProgress(($nEntry/$nDataTotal)*100);
                        continue;
                    }

                    // Inserting...
                    switch ($sSection) {
                        case 'Transcripts':
                            $aParsed[$sSection]['data'][$nID] = array('geneid' => $aData['geneid']); // The rest we don't need anymore.
                            break;

                        case 'Columns':
                        case 'Diseases':
                        case 'Individuals':
                        case 'Phenotypes':
                        case 'Screenings':
                        case 'Variants_On_Genome':
                        case 'Variants_On_Transcripts':
                            if (isset($aData['diseaseid'])) {
                                $aData['diseaseid'] = lovd_findImportedID('Diseases', $aData['diseaseid']);
                            }
                            if (isset($aData['individualid'])) {
                                $aData['individualid'] = lovd_findImportedID('Individuals', $aData['individualid']);
                            }
                            if (isset($aData['fatherid'])) {
                                $aData['fatherid'] = lovd_findImportedID('Individuals', $aData['fatherid']);
                            }
                            if (isset($aData['motherid'])) {
                                $aData['motherid'] = lovd_findImportedID('Individuals', $aData['motherid']);
                            }
                            if (isset($aData['panelid'])) {
                                $aData['panelid'] = lovd_findImportedID('Individuals', $aData['panelid']);
                            }
                            if (isset($aData['transcriptid'])) {
                                $aData['transcriptid'] = lovd_findImportedID('Transcripts', $aData['transcriptid']);
                            }
                            if ($sSection == 'Variants_On_Genome') {
                                // We want the DBID to be generated automatically, but it relies on the database contents, so we have to do it just before inserting the data.
                                // In theory, we should be first importing the variants which have their DBID set, since the IDs that will be generated here might conflict
                                // with these, but the chances are slim and we can put the responsibility of not doing this in the hands of the uploaders.
                                if (!$aData['VariantOnGenome/DBID']) {
                                    $aData['VariantOnGenome/DBID'] = lovd_fetchDBID($aData);
                                }
                            }
                            if ($sSection == 'Variants_On_Transcripts') {
                                $aData['id'] = lovd_findImportedID('Variants_On_Genome', $aData['variantid']);
                            }
                            $nNewID = $aSection['object']->insertEntry($aData, $aFields);
                            $aParsed[$sSection]['data'][$nID]['newID'] = $nNewID;
                            if (isset($aData['statusid']) && $aData['statusid'] >= STATUS_MARKED) {
                                // These updated IDs are used to determine which genes are updated.
                                $aParsed[$sSection]['updatedIDs'][] = $nNewID;
                            }

                            if ($sSection == 'Diseases') {
                                // New diseases need to have the default custom columns enabled.
                                lovd_addAllDefaultCustomColumns('disease', $nNewID);
                            }

                            $aDone[$sSection] ++;
                            $nDone ++;
                            break;

                        case 'Genes_To_Diseases':
                        case 'Individuals_To_Diseases':
                        case 'Screenings_To_Genes':
                        case 'Screenings_To_Variants':
                            if (isset($aData['diseaseid'])) {
                                $aData['diseaseid'] = lovd_findImportedID('Diseases', $aData['diseaseid']);
                            }
                            if (isset($aData['individualid'])) {
                                $aData['individualid'] = lovd_findImportedID('Individuals', $aData['individualid']);
                            }
                            if (isset($aData['screeningid'])) {
                                $aData['screeningid'] = lovd_findImportedID('Screenings', $aData['screeningid']);
                            }
                            if (isset($aData['variantid'])) {
                                $aData['variantid'] = lovd_findImportedID('Variants_On_Genome', $aData['variantid']);
                            }
                            if ($sSection == 'Screenings_To_Genes') {
                                // These updated IDs are used to determine which genes are updated. We only need the screeningid to check via s2v-VOG-VOT-transcripts.
                                $aParsed[$sSection]['updatedIDs'][] = $aData['screeningid'];
                            }
                            if ($sSection == 'Screenings_To_Variants') {
                                // These updated IDs are used to determine which genes are updated. We only need the variantid to check via VOG-VOT-transcripts.
                                $aParsed[$sSection]['updatedIDs'][] = $aData['screeningid'];
                            }
                            $sSQL = 'INSERT INTO ' . constant($aSection['table_name']) . ' (';
                            $aSQL = array();
                            foreach ($aSection['allowed_columns'] as $key => $sField) {
                                $sSQL .= (!$key? '' : ', ') . '`' . $sField . '`';
                                $aSQL[] = $aData[$sField];
                            }
                            $sSQL .= ') VALUES (?' . str_repeat(', ?', count($aFields) - 1) . ')';
                            $_DB->query($sSQL, $aSQL, true, true);
                            $nDone ++;
                            break;

                        default:
                            // Somehow we don't catch all sections? Big bug...
                            $bError = true;
                            lovd_displayError('Import', 'Undefined data processing for section "' . htmlspecialchars($sSection) . '". Please report this bug.');
                            $_DB->rollBack();
                            break 3; // Exit data processing.
                    }






// Verify and process all edits.
//   If we die here for some reason, we must be absolutely sure that we can repeat the same import...
//   Curators should also not always be allowed to set the status* field or both pathogenicity fields, it should be based on the individual's data!!!
//     Check during import maybe difficult. If it is too difficult, maybe first import and then update for the not-authorized data?
// Curators are allowed to edit diseases if isAuthorized() returns true.
// In the end, verify if we've been using all of the $aParsed columns. If not, remove some.
// Important note: we're not checking the format of phenotype fields that are not included for a certain disease. That means data may be ignored while importing, if it is in fields that are not in use for the given disease.
//   The same holds for VOT fields.
// Important note: how will we import phenotype data for diseases that we create in the same file? We won't know which fields will be added, thus we can't check anything!
//   Not mandatory yes/no, field lengths, field formats, etc.
/*******************************************************************************

// Needs to be curator for THIS gene.
if (!lovd_isCurator($_SESSION['currdb'])) {
    // NOTE that this does not unset certain links in the top menu. Links are available.
    require ROOT_PATH . 'inc-top.php';
    lovd_showInfoTable((GENE_COUNT? 'You are not allowed access to ' . (GENE_COUNT > 1? 'this gene database' : 'the installed gene database') . '. Please contact your manager or the administrator to grant you access.' : 'There are currently no databases installed.'), 'stop');
    require ROOT_PATH . 'inc-bot.php';
    exit;
}



        if (!lovd_error()) {
            // 2008-09-15; 2.0-12; Added increased execution time to script to help import bigger files.
            if ((int) ini_get('max_execution_time') < 60) {
                set_time_limit(60);
            }

            // Initiate an array to keep track of assigned Variant/DBID numbers
            // use variants already seen in the upload file as keys when you add the number
            $aIDAssigned = array();



            // Read rest of the file.
            // 2010-11-24; 2.0-23; Totally empty lines made the while-loop quit. Moving the rtrim() elsewhere.
            while (!feof($fInput) && $sLine = fgets($fInput, 4096)) {
                foreach ($aLine as $nKey => $sVal) {
                    // Loop data, and verify it.
                    // Check given ID's.
                    switch ($sCol) {
                        case 'ID_sort_':
                            // If empty, will be determined at the end of this line's run.
                            $aLineVar['sort'] = $sVal;
                            break;
                        case 'ID_pathogenic_':
                            if ($sVal !== '') {
                            }
                            $aLinePat2Var['pathogenic'] = $sVal;
                            break;
                    }
                }

                // Not in the database? Then auto-fill the value with a useful default!

                // ID_sort_ column (variant).
                if ($sMutationCol && !empty($aLineVar[$sMutationCol])) {
                    if (empty($aLineVar['sort'])) {
                        if (!array_key_exists($nVariantID, $aVariants)) {
                            // 2009-06-12; 2.0-19; Added exon column for better sort results.
                            // A bit crude; we're not checking if Variant/Exon exists, we just suppress a possible notice.
                            $aLineVar['sort'] = @lovd_sort($aLineVar[$sMutationCol], $aLineVar['Variant/Exon']);
                        }
                    } elseif ($sFormatVersion <= '2000-190' && substr($aLineVar['sort'], 4, 1) != '_') {
                        // 2009-08-28; 2.0-21; in older download files (LOVD version < 2.0-19), the exon number is not added to the sort code yet.
                        if (!empty($aLineVar['Variant/Exon'])) {
                            $aLineVar['sort'] = str_pad(substr(preg_replace('/^[^0-9]*([0-9]+).*$/', "$1", $aLineVar['Variant/Exon']), 0, 4), 4, '0', STR_PAD_LEFT) . '_' . $aLineVar['sort'];
                        } else {
                            $aLineVar['sort'] = '0000_' . $aLineVar['sort'];
                        }
                    }
                }

                // ID_status_ column (pat2var).
                if (!isset($aLinePat2Var['status']) || $aLinePat2Var['status'] === '') {
                    if (!array_key_exists($sPat2VarKey, $aPat2Var)) {
                        $aLinePat2Var['status'] = 1;
                    }
                }
            }
            fclose($fInput);



            if (!lovd_error()) {
                // Start importing from the memory!

                // 2010-08-12; 2.0-29; No imported variants have mapping info, so reset the mapping!
                $_SESSION['mapping']['time_complete'] = 0; // Redo mapping.

                require ROOT_PATH . 'inc-bot.php';
                exit;
            }
*///////////////////////////////////////////////////////////////////////////////
                    $_BAR[1]->setProgress(($nEntry/$nDataTotal)*100);
                }

                // Done with all this section!
                unset($aParsed[$sSection]['allowed_columns']);
                unset($aParsed[$sSection]['object']);
                unset($aParsed[$sSection]['objects']);
                if (!count($aParsed[$sSection]['data'])) {
                    // We have already individually unset all entries, they are not being referenced anymore.
                    unset($aParsed[$sSection]);
                }
                if (!$aDone[$sSection]) {
                    unset($aDone[$sSection]);
                }
            }
            if (!$bError) {
                $_DB->commit();

                // Determine which gene data is effected. The $aGenes array is needed for the function lovd_setUpdatedDate().
                // This function sets the field updated date in genes.
                $aGenes = array();
                foreach ($aParsed as $sSection => $aSection) {
                    $aTempGenes = array();
                    if (isset($aSection['updatedIDs'])) {
                        switch ($sSection) {
                            case 'Phenotypes':
                                $aTempGenes = $_DB->query('SELECT DISTINCT t.geneid FROM ' . TABLE_TRANSCRIPTS . ' AS t ' .
                                                          'INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (vot.transcriptid = t.id) ' .
                                                          'INNER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vog.id = vot.id) ' .
                                                          'INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s2v.variantid = vog.id) ' .
                                                          'INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s.id = s2v.screeningid) ' .
                                                          'INNER JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (i.id = s.individualid) ' .
                                                          'INNER JOIN ' . TABLE_PHENOTYPES . ' AS p ON (p.individualid = i.id) ' .
                                                          'WHERE vog.statusid >= ' . STATUS_MARKED .
                                                          ' AND i.statusid >= ' . STATUS_MARKED .
                                                          ' AND p.id IN (?' . str_repeat(', ?', count($aSection['updatedIDs']) - 1) . ')', $aSection['updatedIDs'])->fetchAllColumn();
                                break;
                            case 'Individuals':
                                $aTempGenes = $_DB->query('SELECT DISTINCT t.geneid FROM ' . TABLE_TRANSCRIPTS . ' AS t ' .
                                                          'INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (vot.transcriptid = t.id) ' .
                                                          'INNER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vog.id = vot.id) ' .
                                                          'INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s2v.variantid = vog.id) ' .
                                                          'INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s.id = s2v.screeningid) ' .
                                                          'WHERE vog.statusid >= ' . STATUS_MARKED . ' ' .
                                                          ' AND s.individualid IN (?' . str_repeat(', ?', count($aSection['updatedIDs']) - 1) . ')', $aSection['updatedIDs'])->fetchAllColumn();
                                break;
                            case 'Screenings_To_Genes':
                            case 'Screenings':
                            case 'Screenings_To_Variants':
                                // FIXME: A change in screening should actually go up to individual (checking its status), and then down to genes.
                                $aTempGenes = $_DB->query('SELECT DISTINCT t.geneid FROM ' . TABLE_TRANSCRIPTS . ' AS t ' .
                                                          'INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (vot.transcriptid = t.id) ' .
                                                          'INNER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vog.id = vot.id) ' .
                                                          'INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s2v.variantid = vog.id) ' .
                                                          'WHERE vog.statusid >= ' . STATUS_MARKED .
                                                          ' AND s2v.screeningid IN (?' . str_repeat(', ?', count($aSection['updatedIDs']) - 1) . ')', $aSection['updatedIDs'])->fetchAllColumn();
                                break;
                            case 'Variants_On_Genome':
                            case 'Variants_On_Transcripts':
                                $aTempGenes = $_DB->query('SELECT DISTINCT t.geneid FROM ' . TABLE_TRANSCRIPTS . ' AS t ' .
                                                          'INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (vot.transcriptid = t.id) ' .
                                                          'INNER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vog.id = vot.id) ' .
                                                          'WHERE vog.statusid >= ' . STATUS_MARKED .
                                                          ' AND vog.id IN (?' . str_repeat(', ?', count($aSection['updatedIDs']) - 1) . ')', $aSection['updatedIDs'])->fetchAllColumn();
                                break;
                            case 'Transcripts':
                                $aTempGenes = $_DB->query('SELECT DISTINCT t.geneid FROM ' . TABLE_TRANSCRIPTS . ' AS t ' .
                                                          'WHERE t.id IN (?' . str_repeat(', ?', count($aSection['updatedIDs']) - 1) . ')', $aSection['updatedIDs'])->fetchAllColumn();
                                break;
                            default:
                                break;
                        }
                        if (!empty($aGenes)) {
                            $aGenes = array_merge($aGenes, $aTempGenes);
                        } else {
                            $aGenes = $aTempGenes;
                        }
                    }
                }

                if ($sMode == 'update') {
                    $_BAR[1]->setMessage('Done importing!<BR>The following sections are modified and updated in the database: ' . $sSectionsUpdated . '.', 'done');
                } else {
                    $_BAR[1]->setMessage('Done importing!', 'done');
                }
                $_BAR[1]->setMessageVisibility('done', true);
                if (count($aDone)) {
                    $sMessage = '';
                    foreach ($aDone as $sSection => $n) {
                        $sMessage .= (!$sMessage ? '' : ', ') . $n . ' ' . $sSection;
                    }
                    $sMessage = preg_replace('/, ([^,]+)/', " and $1", $sMessage);
                } else {
                    $sMessage = 'new links only';
                }
                $aGenes = array_unique($aGenes);
                $nGenes = count($aGenes);
                lovd_writeLog('Event', LOG_EVENT, 'Imported ' . $sMessage . '; ran ' . $nDone . ' queries' . (!$aGenes? '' : ' (' . ($nGenes > 100? $nGenes . ' genes' : implode(', ', $aGenes)) . ')') . (ACTION != 'autoupload_scheduled_file' || !$sFile? '' : ' (' . $sFile . ')') . '.');
                lovd_setUpdatedDate($aGenes); // FIXME; regardless of variant status... oh, well...
            }
            // FIXME: Why is this not empty?
            //var_dump(implode("\n", $aData));
            $_T->printFooter();
            exit;
        }

        // Errors...
        $_BAR[0]->remove();
        $_BAR[0]->setMessageVisibility('', false);
        $_BAR[0]->setMessageVisibility('done', false);

        if (!lovd_error() && !$nDataTotal) {
            if ($sMode == 'update')
                lovd_showInfoTable('No entries found that can be updated via the import file.', 'stop');
            if ($sMode == 'insert') {
                lovd_showInfoTable('No entries found that need to be imported in the database. Either your uploaded file contains no variants, or all entries are already in the database.', 'stop');
            }
            $_T->printFooter();
            exit;
        }
    }

} else {
    // Default values.
    if (in_array(ACTION, array_keys($aModes))) {
        $_POST['mode'] = ACTION;
    }
}





$_T->printHeader();
$_T->printTitle('Import data in LOVD format');

print('      Using this form you can import files in LOVD\'s tab-delimited format. Currently supported imports are custom column, individual, phenotype, screening and variant data.<BR><I>Genomic positions in your data are assumed to be relative to Human Genome build ' . $_CONF['refseq_build'] . '</I>.<BR>' . "\n" .
      '      <BR>' . "\n\n");

if ($_AUTH['level'] == LEVEL_CURATOR) {
    $sManagers = '';
    $zManagers = $_DB->query('SELECT u.name, u.email FROM ' . TABLE_USERS . ' AS u WHERE u.level = ? ORDER BY u.name ASC', array(LEVEL_MANAGER))->fetchAllAssoc();
    if (!$zManagers) {
        // No managers found, then get the database admin.
        $zManagers = $_DB->query('SELECT u.name, u.email FROM ' . TABLE_USERS . ' AS u WHERE u.level = ? ORDER BY u.name ASC', array(LEVEL_ADMIN))->fetchAllAssoc();
    }
    $nManagers = count($zManagers);
    foreach ($zManagers as $i => $z) {
        $i ++;
        $sManagers .= ($sManagers? ($i == $nManagers? ' or ' : ', ') : '') . '<A href="mailto:' . str_replace(array("\r\n", "\r", "\n"), ', ', trim($z['email'])) . '">' . $z['name'] . '</A>';
    }
    lovd_showInfoTable('Your user level is curator, as a curator you can only simulate an import and check your LOVD tab-delimited file.<BR>To actually import the file, you have to contact the database manager(s): ' . $sManagers . '.', 'information', 760);
}

lovd_showInfoTable('If you\'re looking for importing data files containing variant data only, like VCF files and SeattleSeq annotated files, please <A href="submit">start a new submission</A>.', 'information', 760);

// FIXME: Since we can increase the memory limit anyways, maybe we can leave this message out if we nicely handle the memory?
lovd_showInfoTable('In some cases importing big files or importing files into big databases can cause LOVD to run out of available memory. In case this server hides these errors, LOVD would return a blank screen. If this happens, split your import file into smaller chunks or ask your system administrator to allow PHP to use more memory (currently allowed: ' . ini_get('memory_limit') . 'B).', 'warning', 760);

// Warnings were shown in the progress bar, but I'd like to have them here too. They are still in the source, so we can use JS.
if ($nWarnings && FORMAT == 'text/html') {
    lovd_errorAdd('', '<A href="#" onclick="$(\'#warnings\').toggle(); if ($(\'#warnings_action\').html() == \'Show\') { $(\'#warnings_action\').html(\'Hide\'); } else { $(\'#warnings_action\').html(\'Show\') } return false;"><SPAN id="warnings_action">Show</SPAN> ' . $nWarnings . ' warning' . ($nWarnings == 1? '' : 's') . '</A><DIV id="warnings"></DIV><SCRIPT type="text/javascript">$("#warnings").hide();$("#warnings").html($("#lovd_parser_progress_message_done").html());</SCRIPT>');
}

lovd_errorPrint();

// Tooltip JS code.
lovd_includeJS('inc-js-tooltip.php');

print('      <FORM action="' . CURRENT_PATH . '" method="post" enctype="multipart/form-data">' . "\n" .
      '        <INPUT type="hidden" name="MAX_FILE_SIZE" value="' . $nMaxSize . '">' . "\n");

$aForm =
    array(
        array('POST', '', '', '', '40%', '14', '60%'),
        array('', '', 'print', '<B>File selection</B> (LOVD tab-delimited format only!)'),
        'hr',
        array('Select the file to import', '', 'file', 'import', 40),
        array('', 'Current file size limits:<BR>LOVD: ' . ($nMaxSizeLOVD/(1024*1024)) . 'M<BR>PHP (upload_max_filesize): ' . ini_get('upload_max_filesize') . '<BR>PHP (post_max_size): ' . ini_get('post_max_size'), 'note', 'The maximum file size accepted is ' . round($nMaxSize/pow(1024, 2), 1) . ' MB' . ($nMaxSize == $nMaxSizeLOVD? '' : ', due to restrictions on this server. If you wish to have it increased, contact the server\'s system administrator') . '.'),
        'hr',
        'skip',
        array('', '', 'print', '<B>Import options</B>'),
        'hr',
        array('Import mode', 'Available modes:<BR>' .
            '<B>' . $aModes['update'] . '</B>: LOVD will compare all IDs given in the file with the contents of the database. LOVD will search for differences between the file and the database, and update the entries in the database using the data in the file.<BR>' .
            '<B>' . $aModes['insert'] . '</B>: LOVD will use the IDs given in the file only to link the data together. All data in the file will be treated as new, and all data will receive new IDs once imported. The biggest advantage of this mode is that you do not need to know which IDs are free in the database.',
            'select', 'mode', 1, $aModes, true, false, false),
        array('', '', 'note', 'Please select which import mode LOVD should use; <I>' . implode('</I> or <I>', $aModes) . '</I>. For more information on the modes, move your mouse over the ? icon.'),
        array('Character encoding of imported file', 'If your file contains special characters like &egrave;, &ouml; or even just fancy quotes like &ldquo; or &rdquo;, LOVD needs to know the file\'s character encoding to ensure the correct display of the data.', 'select', 'charset', 1, $aCharSets, false, false, false),
        array('', '', 'note', 'Please only change this setting in case you encounter problems with displaying special characters in imported data. Technical information about character encoding can be found <A href="http://en.wikipedia.org/wiki/Character_encoding" target="_blank">on Wikipedia</A>.'),
        array('Simulate (don\'t actually import the data)', 'To check your file for errors, without actually importing anything, select this checkbox. Currently only managers or higher are allowed to do an import. Curators are only allowed to simulate an import.', 'checkbox', 'simulate', 1),
        'skip',
        array('', '', 'submit', 'Import file'));

lovd_viewForm($aForm);

print('</FORM>' . "\n\n");

// If user has level curator, the checkbox is disabled and via $_POST['simulate'] the checkbox is always set to true.
// The help icon shows some extra information to the user.
if ($_AUTH['level'] == LEVEL_CURATOR) {
    print('      <SCRIPT type="text/javascript">' . "\n" .
          '        $(function() {' . "\n" .
          '          document.getElementsByName("simulate")[0].disabled = true;' . "\n" .
          '        });' . "\n" .
          '      </SCRIPT>' . "\n\n");
}

$_T->printFooter();
?>
