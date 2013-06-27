<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-09-19
 * Modified    : 2013-06-27
 * For LOVD    : 3.0-06
 *
 * Copyright   : 2004-2013 Leiden University Medical Center; http://www.LUMC.nl/
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

define('ROOT_PATH', './');
define('TAB_SELECTED', 'setup');
require ROOT_PATH . 'inc-init.php';
ini_set('auto_detect_line_endings', true); // So we can work with Mac files also...

// Require curator clearance.
//lovd_isAuthorized('gene', $_AUTH['curates']); // Any gene will do.
//lovd_requireAUTH(LEVEL_CURATOR);
lovd_requireAUTH(LEVEL_MANAGER);
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
        'update' => 'Update existing data & add new data (not yet implemented)',
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
    );

// Calculate maximum uploadable file size.
$nMaxSizeLOVD = 100*1024*1024; // 100MB LOVD limit.
$nMaxSize = min(
    $nMaxSizeLOVD,
    lovd_convertIniValueToBytes(ini_get('upload_max_filesize')),
    lovd_convertIniValueToBytes(ini_get('post_max_size')));

function lovd_calculateDiffScore ($zData, $aLine)
{
    // Calculates the difference between the data.
    $nDiffs = 0;
    $nFields = count($zData);
    foreach ($zData as $sCol => $sValue) {
        if ($aLine[$sCol] && !in_array($sCol, array('edited_by', 'edited_date')) && $sValue != $aLine[$sCol]) {
            // Ignoring empty data in the import file.
            $nDiffs ++;
        }
    }
    return round(100*($nDiffs / $nFields));
}

function lovd_endLine ()
{
    // Ends the current line by cleaning up the memory and changing the line number.
    global $aData, $i, $nLine, $_ERROR;

    unset($aData[$i]);
    $nLine ++;

    // If we have too many errors, quit here (note that some errors can still flood the page,
    // since they do a continue or break before reaching this part of the code).
    if (count($_ERROR['messages']) >= 50) {
        lovd_errorAdd('import', 'Too many errors, stopping file processing.');
        return false;
    }
    return true;
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
        lovd_errorAdd('import', 'There was a problem with the file transfer. Please try again. The file cannot be larger than ' . round($nMaxSize/pow(1024, 2), 1) . ' MB' . ($nMaxSize == $nMaxSizeLOVD? '' : ', due to restrictions on this server.') . '.');

    } else if ($_FILES['import']['error'] == 4 || !$_FILES['import']['size']) {
        lovd_errorAdd('import', 'Please select a file to upload.');

    } else if ($_FILES['import']['size'] > $nMaxSize) {
        lovd_errorAdd('import', 'The file cannot be larger than ' . round($nMaxSize/pow(1024, 2), 1) . ' MB' . ($nMaxSize == $nMaxSizeLOVD? '' : ', due to restrictions on this server.') . '.');

    } elseif ($_FILES['import']['error']) {
        // Various errors available from 4.3.0 or later.
        lovd_errorAdd('import', 'There was an unknown problem with receiving the file properly, possibly because of the current server settings. If the problem persists, please contact the database administrator.');
    }

    if ($_POST['mode'] == 'update') {
        lovd_errorAdd('mode', 'The "Update & Add" mode is not yet implemented!');
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
        $aParsed = array_fill_keys(
            array(
                'Columns', 'Genes', 'Transcripts', 'Diseases', 'Genes_To_Diseases', 'Individuals', 'Individuals_To_Diseases', 'Phenotypes', 'Screenings', 'Screenings_To_Genes', 'Variants_On_Genome', 'Variants_On_Transcripts', 'Screenings_To_Variants'
            ), array('allowed_columns' => array(), 'columns' => array(), 'data' => array(), 'ids' => array(), 'nColumns' => 0, 'object' => null, 'required_columns' => array(), 'settings' => array()));
        $aParsed['Genes_To_Diseases'] = $aParsed['Individuals_To_Diseases'] = $aParsed['Screenings_To_Genes'] = $aParsed['Screenings_To_Variants'] = array('allowed_columns' => array(), 'data' => array()); // Just the data, nothing else!
        $aUsers = $_DB->query('SELECT id FROM ' . TABLE_USERS)->fetchAllColumn();
        $aImportFlags = array();
        $sFileVersion = $sFileType = $sCurrentSection = '';
        $bParseColumns = false;
        $nLine = 1;
        $nLines = count($aData);
        $nDataTotal = 0; // To show the second progress bar; how much actual work needs to be done?
        $sMode = $_POST['mode'];
        $sDate = date('Y-m-d H:i:s');
        $aDiseasesAlreadyWarnedFor = array(); // To prevent lots and lots of error messages for each phenotype entry created for the same disease that is not yet inserted into the database.

        foreach ($aData as $i => $sLine) {
            $sLine = trim($sLine);
            if (!$sLine) {
                lovd_endLine();
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
                        $sFileType = $aTypes[$sFileType];
                        // Clean $aParsed a bit, depending on the file type.
                        if ($sFileType == 'Col') {
                            $aParsed = array('Columns' => $aParsed['Columns']);
                        }
                    }
                    lovd_endLine();
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



        // Now, the actual parsing...
        foreach ($aData as $i => $sLine) {
            $sLine = trim($sLine);
            if (!$sLine) {
                lovd_endLine();
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
                            flush();
                        }
                    }
                    $sCurrentSection = $aRegs[1];
                    $bParseColumns = true;

                    // So we can use short variables.
                    $aSection = &$aParsed[$sCurrentSection];
                    $aColumns = &$aSection['columns'];
                    $nColumns = &$aSection['nColumns'];

                    // Section-specific settings and definitions.
                    if (!in_array($sCurrentSection, array('Genes_To_Diseases'))) {
                        $aSection['required_columns'][] = 'id';
                    }
                    $sTableName = 'TABLE_' . strtoupper($sCurrentSection);
                    switch ($sCurrentSection) {
                        case 'Columns':
                            $sTableName = $aParsed[$sCurrentSection]['table_name'] = 'TABLE_COLS';
                            require_once ROOT_PATH . 'class/object_columns.php';
                            $aSection['object'] = new LOVD_Column();
                            break;
                        case 'Genes':
                            break;
                        case 'Transcripts':
                            break;
                        case 'Diseases':
                            require_once ROOT_PATH . 'class/object_diseases.php';
                            $aSection['object'] = new LOVD_Disease();
                            break;
                        case 'Genes_To_Diseases':
                            $sTableName = $aParsed[$sCurrentSection]['table_name'] = 'TABLE_GEN2DIS';
                            break;
                        case 'Individuals':
                            require_once ROOT_PATH . 'class/object_individuals.php';
                            $aSection['object'] = new LOVD_Individual();
                            break;
                        case 'Individuals_To_Diseases':
                            $sTableName = $aParsed[$sCurrentSection]['table_name'] = 'TABLE_IND2DIS';
                            break;
                        case 'Phenotypes':
                            $aSection['required_columns'][] = 'diseaseid';
                            require_once ROOT_PATH . 'class/object_phenotypes.php';
                            // We don't create an object here, because we need to do that per disease. This means we don't have a general check for mandatory columns, which is not so much a problem I think.
                            $aSection['objects'] = array();
                            break;
                        case 'Screenings':
                            require_once ROOT_PATH . 'class/object_screenings.php';
                            $aSection['object'] = new LOVD_Screening();
                            break;
                        case 'Screenings_To_Genes':
                            $sTableName = $aParsed[$sCurrentSection]['table_name'] = 'TABLE_SCR2GENE';
                            break;
                        case 'Variants_On_Genome':
                            $sTableName = $aParsed[$sCurrentSection]['table_name'] = 'TABLE_VARIANTS';
                            require_once ROOT_PATH . 'class/object_genome_variants.php';
                            $aSection['object'] = new LOVD_GenomeVariant();
                            break;
                        case 'Variants_On_Transcripts':
                            $sTableName = $aParsed[$sCurrentSection]['table_name'] = 'TABLE_VARIANTS_ON_TRANSCRIPTS';
                            $aSection['required_columns'][] = 'transcriptid';
                            require_once ROOT_PATH . 'class/object_transcript_variants.php';
                            // We don't create an object here, because we need to do that per gene. This means we don't have a general check for mandatory columns, which is not so much a problem I think.
                            $aSection['objects'] = array();
                            break;
                        case 'Screenings_To_Variants':
                            $sTableName = $aParsed[$sCurrentSection]['table_name'] = 'TABLE_SCR2VAR';
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
                            $aSection['ids'] = $_DB->query('SELECT id FROM ' . $sTableName)->fetchAllColumn();
                        }
                    }
                    // For custom objects: all mandatory custom columns will be mandatory here, as well.
                    if (isset($aSection['object']->aColumns)) {
                        foreach ($aSection['object']->aColumns as $sCol => $aCol) {
                            if ($aCol['mandatory']) {
                                $aSection['required_columns'][] = $sCol;
                            }
                        }
                    }
                } // Else, it's just comments we will ignore.
                lovd_endLine();
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

            // Create all the standard column's keys in $aLine, so we can safely reference to it.
            foreach ($aSection['allowed_columns'] as $sCol) {
                if (!isset($aLine[$sCol])) {
                    $aLine[$sCol] = '';
                }
            }



            // General checks: required fields defined by import.
            foreach ($aSection['required_columns'] as $sCol) {
                if (empty($aLine[$sCol])) {
                    lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Missing value for required column "' . htmlspecialchars($sCol) . '".');
                }
            }

            // For shared objects, load the correct object.
            if ($sCurrentSection == 'Phenotypes' && $aLine['diseaseid']) {
                if (!isset($aSection['objects'][(int) $aLine['diseaseid']])) {
                    $aSection['objects'][(int) $aLine['diseaseid']] = new LOVD_Phenotype($aLine['diseaseid']);
                }
                $aSection['object'] =& $aSection['objects'][(int) $aLine['diseaseid']];
            }
            $sGene = '';
            if ($sCurrentSection == 'Variants_On_Transcripts' && $aLine['transcriptid']) {
                // We have to include some checks here instead of below, because we need to verify that we understand the transcriptID and get to the Gene.
                //   Only then can we open the correct object.
                $bTranscriptInDB = in_array($aLine['transcriptid'], $aParsed['Transcripts']['ids']);
                $bTranscriptInFile = isset($aParsed['Transcripts']['data'][(int) $aLine['transcriptid']]);
                if (!$bTranscriptInFile && !$bTranscriptInDB) {
                    // Transcript does not exist and is not defined in the import file.
                    lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Transcript "' . htmlspecialchars($aLine['transcriptid']) . '" does not exist in the database and is not defined in this import file.');
                    $bGeneInDB = false;
                } elseif ($bTranscriptInFile) {
                    $sGene = $aParsed['Transcripts']['data'][(int) $aLine['transcriptid']]['geneid'];
                    $bGeneInDB = in_array($sGene, $aParsed['Genes']['ids']);
                } else {
                    $sGene = $_DB->query('SELECT geneid FROM ' . TABLE_TRANSCRIPTS . ' WHERE id = ?', array($aLine['transcriptid']))->fetchColumn();
                    $bGeneInDB = true;
                    // Store for the next VOT.
                    $aParsed['Transcripts']['data'][(int) $aLine['transcriptid']] = array('id' => $aLine['transcriptid'], 'geneid' => $sGene, 'todo' => '');
                    $nDataTotal ++; // Otherwise it's messing up our statistics.
                }
                if (!isset($aSection['objects'][$sGene])) {
                    $aSection['objects'][$sGene] = new LOVD_TranscriptVariant($sGene);
                }
                $aSection['object'] =& $aSection['objects'][$sGene];
            }

            // General checks: checkFields().
            $zData = false;
            if (isset($aSection['object']) && is_object($aSection['object'])) {
                // Object has been created. If we're updating, get the current info from the database.
                if ($sMode == 'update') {
                    // FIXME: First check in 'ids' to predict if it exists or not?
                    // FIXME: Doesn't currently work for VOT because its objects are in an array, and it needs the transcriptid in a separate argument.
                    $zData = $_DB->query('SELECT * FROM ' . $sTableName . ' WHERE id = ?', array($aLine['id']))->fetchAssoc();
                    if ($zData) {
                        // Calculate difference score, and check authorization.
                        $nDiffScore = lovd_calculateDiffScore($zData, $aLine);
                        if ($nDiffScore > 0 && !lovd_isAuthorized(strtolower(rtrim($sCurrentSection, 's')), $aLine['id'], false)) {
                            // Not allowed to edit at all!
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): ' . $sCol . ' value "' . htmlspecialchars($aLine[$sCol]) . '" refers to non-existing user.');
                        } elseif ($nDiffScore > 0) {
                            // FIXME: TEMPORARY: Deny changes, just like in LOVD 2.0.
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Data is not equal to data in database (diffscore: ' . $nDiffScore . '), editing through import is unfortunately not allowed yet (coming soon).');
                        } elseif ($nDiffScore > 50) {
                            // Difference too big, maybe he's trying to change different data.
                            // FIXME: Shouldn't this be a hard error?
                            $_BAR[0]->appendMessage('Warning: Will not edit ' . rtrim($sCurrentSection, 's') . ' "' . htmlspecialchars($aLine['id']) . '", the data in the database differs too much from the data in the import file, this looks like an unintended edit!<BR>', 'done');
                        }
                    }
                }

                // For custom columns, we need to split the ID in category and colid.
                if ($sCurrentSection == 'Columns') {
                    list($aLine['category'], $aLine['colid']) = explode('/', $aLine['id'], 2);
                }

                // We'll need to split the functional consequence field to have checkFields() function normally.
                if (in_array($sCurrentSection, array('Variants_On_Genome', 'Variants_On_Transcripts'))) {
                    $aLine['effect_reported'] = 5; // Default value.
                    $aLine['effect_concluded'] = 5; // Default value.
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
                    $_ERROR['fields'][$i] = ''; // It wants to highlight a field that's not here right now.
                    $_ERROR['messages'][$i] = 'Error (' . $sCurrentSection . ', line ' . $nLine . '): ' . $_ERROR['messages'][$i];
                }
            }

            // General checks: numerical ID, have we seen the ID before, owned_by, created_* and edited_*.
            if (!empty($aLine['id'])) {
                if (in_array($sCurrentSection, array('Columns', 'Genes'))) {
                    $ID = $aLine['id'];
                } else {
                    if (!ctype_digit($aLine['id'])) {
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): ID "' . htmlspecialchars($aLine['id']) . '" is not a numerical value.');
                    }
                    $ID = (int) $aLine['id'];
                }
                if (isset($aSection['data'][$ID])) {
                    // We saw this ID before in this file!
                    lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): ID "' . htmlspecialchars($aLine['id']) . '" already defined at line ' . $aSection['data'][$ID]['nLine'] . '.');
                    continue; // Skip to next line.
                }
            }
            if (in_array($sCurrentSection, array('Columns', 'Diseases', 'Individuals', 'Phenotypes', 'Screenings', 'Variants_On_Genome'))) {
                foreach (array('created_by', 'edited_by') as $sCol) {
                    // Check is not needed for owned_by, because the form should have a selection list (which is checked separately).
                    if (!$zData || in_array($sCol, $aColumns)) {
                        if ($aLine[$sCol] && !in_array($aLine[$sCol], $aUsers)) {
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): ' . $sCol . ' value "' . htmlspecialchars($aLine[$sCol]) . '" refers to non-existing user.');
                        } elseif (($sCol != 'edited_by' || $aLine['edited_date']) && !$aLine[$sCol]) {
                            // Edited_by is only filled in if empty and edited_date is filled in.
                            $aLine[$sCol] = $_AUTH['id'];
                        }
                    }
                }
                foreach (array('created_date', 'edited_date') as $sCol) {
                    if (!$zData || in_array($sCol, $aColumns)) {
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
                    // Columns normally not on the form, are not checked properly...
                    // Col_order; numeric and 0 <= col_order <= 255.
                    if ($aLine['col_order'] === '') {
                        $aLine['col_order'] = 0;
                    } elseif (!ctype_digit($aLine['col_order']) || $aLine['col_order'] < 0 || $aLine['col_order'] > 255) {
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Incorrect value for field \'col_order\', which needs to be numeric, between 0 and 255.');
                    }
                    // All integer columns that are checkboxes on the form, are turned into empty strings by checkFields, but we'll verify them here.
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
                        $aOptions = explode('\r\n', $aLine['select_options']);
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
                        if ($nDiffScore && $_AUTH['level'] < LEVEL_MANAGER) {
                            // Data is being updated, but user is not allowed to edit this entry!
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Access denied for update on Column "' . htmlspecialchars($aLine['id']) . '".');
                        }
                        // Now allowed to change HGVS status.
                        if ($aLine['hgvs'] != $zData['hgvs']) {
                            // FIXME: Perhaps the DBA should be allowed to do this?
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Not allowed to change the HGVS standard status of any column.');
                        }
                    } else {
                        // HGVS, never allowed when not editing.
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
                    if ($sFileType != 'Genes' && !in_array($aLine['id'], $aSection['ids'])) {
                        // Do not allow genes that are not in the database, if we're not importing genes!
//                        $_BAR[0]->appendMessage('Warning: gene "' . htmlspecialchars($aLine['id'] . '" (' . $aLine['name']) . ') does not exist in the database. Currently, it is not possible to import genes into LOVD using this file format.<BR>', 'done');
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Gene "' . htmlspecialchars($aLine['id'] . '" (' . $aLine['name']) . ') does not exist in the database. Currently, it is not possible to import genes into LOVD using this file format.');
                    }
                    break;

                case 'Transcripts':
                    if (!in_array($sFileType, array('Genes', 'Transcripts'))) {
                        // Not importing genes or transcripts. Allowed are references to existing transcripts only!!!
                        if (!in_array($aLine['id'], $aSection['ids'])) {
                            // Do not allow transcripts that are not in the database, if we're not importing genes or transcripts!
//                            $_BAR[0]->appendMessage('Warning: transcript "' . htmlspecialchars($aLine['id'] . '" (' . $aLine['geneid'] . ', ' . $aLine['name']) . ') does not exist in the database. Currently, it is not possible to import transcripts into LOVD using this file format.<BR>', 'done');
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Transcript "' . htmlspecialchars($aLine['id'] . '" (' . $aLine['geneid'] . ', ' . $aLine['name']) . ') does not exist in the database. Currently, it is not possible to import transcripts into LOVD using this file format.');
                        } else {
                            // FIXME: If we'll allow the creation of transcripts, and we have an object, we can use $zData here.
                            // Transcript has been found in the database, check if NM and gene are the same. The rest we will ignore.
                            $bExists = ($_DB->query('SELECT COUNT(*) FROM ' . TABLE_TRANSCRIPTS . ' WHERE id = ? AND geneid = ? AND id_ncbi = ?', array($aLine['id'], $aLine['geneid'], $aLine['id_ncbi']))->fetchColumn() > 0);
                            if (!$bExists) {
                                lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Transcript "' . htmlspecialchars($aLine['id']) . '" does not match the same gene and/or the same NCBI ID as in the database.');
                            }
                        }
                        // Just store the data in the $aParsed array, such that VOTs can reference to it.
                        $aLine['todo'] = '';
                    }
                    break;

                case 'Diseases':
                    if ($zData) {
                        if ($nDiffScore && !lovd_isAuthorized('disease', $aLine['id'], false)) {
                            // Data is being updated, but user is not allowed to edit this entry!
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Access denied for update on Disease "' . htmlspecialchars($aLine['id']) . '".');
                        }
                    } else {
                        // We're inserting. Curators at this moment are not allowed to insert diseases.
                        if ($_AUTH['level'] < LEVEL_MANAGER) {
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Access denied, currently manager level is required to import new disease entries.');
                        } else {
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

                            // Entry might still have thrown an error, but because we want to draw out all errors, we will store this one in case it's referenced to.
                            $aLine['todo'] = 'insert'; // OK, insert.
                        }
                    }
                    break;

                case 'Genes_To_Diseases':
                    // Editing will never be supported. Any change breaks the PK, so which entry would we edit?
                    // Create ID, so we can link to the data.
                    $aLine['id'] = $aLine['geneid'] . '|' . (int) $aLine['diseaseid']; // This also means we lack the check for repeated lines!
                    // Manually check for repeated lines, to prevent query errors in case of inserts.
                    if (isset($aSection['data'][$aLine['id']])) {
                        // We saw this ID before in this file!
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): ID "' . htmlspecialchars($aLine['id']) . '" already defined at line ' . $aSection['data'][$aLine['id']]['nLine'] . '.');
                        break; // Stop processing this line.
                    }

                    // Check references.
                    $bGeneInDB = in_array($aLine['geneid'], $aParsed['Genes']['ids']);
                    if ($aLine['geneid'] && !$bGeneInDB) {
                        // Gene does not exist.
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Gene "' . htmlspecialchars($aLine['geneid']) . '" does not exist in the database.');
                    }
                    $bDiseaseInDB = in_array($aLine['diseaseid'], $aParsed['Diseases']['ids']);
                    $bDiseaseInFile = isset($aParsed['Diseases']['data'][(int) $aLine['diseaseid']]);
                    if ($aLine['diseaseid'] && !$bDiseaseInFile && !$bDiseaseInDB) {
                        // Disease does not exist and is not defined in the import file.
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Disease "' . htmlspecialchars($aLine['diseaseid']) . '" does not exist in the database and is not defined in this import file.');
                    } elseif ($bGeneInDB) {
                        // No problems left, just check now if insert is necessary or not.
                        if (!$bDiseaseInDB || ($sMode == 'insert' && $bDiseaseInFile)) {
                            // Disease is in file (will be inserted, or it has generated errors), so flag this to be inserted!
                            $aLine['todo'] = 'insert';
                        } else {
                            // Gene & Disease are already in the DB, check if we can't find this combo in the DB, it needs to be inserted. Otherwise, we'll ignore it.
                            $bInDB = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_GEN2DIS . ' WHERE geneid = ? AND diseaseid = ?', array($aLine['geneid'], $aLine['diseaseid']))->fetchColumn();
                            if (!$bInDB) {
                                $aLine['todo'] = 'insert';
                            }
                        }
                    }

                    if (isset($aLine['todo']) && $aLine['todo'] == 'insert') {
                        // Inserting, check rights.
                        if ($_AUTH['level'] < LEVEL_MANAGER && !lovd_isAuthorized('gene', $aLine['geneid'])) {
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Access denied, you are not authorized to connect this gene to this disease.');
                        }
                    }
                    break;

                case 'Individuals':
                    if ($zData) {
                        if ($nDiffScore && !lovd_isAuthorized('individual', $aLine['id'], false)) {
                            // Data is being updated, but user is not allowed to edit this entry!
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Access denied for update on Individual "' . htmlspecialchars($aLine['id']) . '".');
                        }
                    } else {
                        // FIXME: Default values of custom columns?
                        // Entry might still have thrown an error, but because we want to draw out all errors, we will store this one in case it's referenced to.
                        $aLine['todo'] = 'insert'; // OK, insert.
                    }
                    break;

                case 'Individuals_To_Diseases':
                    // Editing will never be supported. Any change breaks the PK, so which entry would we edit?
                    // Create ID, so we can link to the data.
                    $aLine['id'] = (int) $aLine['individualid'] . '|' . (int) $aLine['diseaseid']; // This also means we lack the check for repeated lines!
                    // Manually check for repeated lines, to prevent query errors in case of inserts.
                    if (isset($aSection['data'][$aLine['id']])) {
                        // We saw this ID before in this file!
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): ID "' . htmlspecialchars($aLine['id']) . '" already defined at line ' . $aSection['data'][$aLine['id']]['nLine'] . '.');
                        break; // Stop processing this line.
                    }

                    // Check references.
                    $bIndInDB = in_array($aLine['individualid'], $aParsed['Individuals']['ids']);
                    $bIndInFile = isset($aParsed['Individuals']['data'][(int) $aLine['individualid']]);
                    if ($aLine['individualid'] && !$bIndInDB && !$bIndInFile) {
                        // Individual does not exist and is not defined in the import file.
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Individual "' . htmlspecialchars($aLine['individualid']) . '" does not exist in the database and is not defined in this import file.');
                    }
                    $bDiseaseInDB = in_array($aLine['diseaseid'], $aParsed['Diseases']['ids']);
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
                        // Inserting, check rights.
                        if ($_AUTH['level'] < LEVEL_MANAGER && !lovd_isAuthorized('individual', $aLine['individualid'])) {
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Access denied, you are not authorized to connect this individual to this disease.');
                        }
                    }
                    break;

                case 'Phenotypes':
                    // FIXME: Check references only if we don't have a $zData OR $zData['referenceid'] is different from now?
                    //   Actually, do we allow references to change during an edit?
                    // Check references.
                    $bDiseaseInDB = in_array($aLine['diseaseid'], $aParsed['Diseases']['ids']);
                    $bDiseaseInFile = isset($aParsed['Diseases']['data'][(int) $aLine['diseaseid']]);
                    if ($aLine['diseaseid'] && !$bDiseaseInFile && !$bDiseaseInDB) {
                        // Disease does not exist and is not defined in the import file.
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Disease "' . htmlspecialchars($aLine['diseaseid']) . '" does not exist in the database and is not defined in this import file.');
                    } elseif ((!$bDiseaseInDB || ($sMode == 'insert' && $bDiseaseInFile)) && !in_array($aLine['diseaseid'], $aDiseasesAlreadyWarnedFor)) {
                        // We're inserting this disease, so we're not sure about the exact columns that will be active. Issue a warning.
                        $_BAR[0]->appendMessage('Warning (' . $sCurrentSection . ', line ' . $nLine . '): The disease belonging to this phenotype entry is yet to be inserted into the database. Perhaps not all this phenotype entry\'s custom columns will be enabled for this disease!<BR>', 'done');
                        $nWarnings ++;
                        $aDiseasesAlreadyWarnedFor[] = $aLine['diseaseid'];
                    }
                    $bIndInDB = in_array($aLine['individualid'], $aParsed['Individuals']['ids']);
                    $bIndInFile = isset($aParsed['Individuals']['data'][(int) $aLine['individualid']]);
                    if ($aLine['individualid'] && !$bIndInDB && !$bIndInFile) {
                        // Individual does not exist and is not defined in the import file.
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Individual "' . htmlspecialchars($aLine['individualid']) . '" does not exist in the database and is not defined in this import file.');
                    }

                    if ($zData) {
                        if ($nDiffScore && !lovd_isAuthorized('phenotype', $aLine['id'], false)) {
                            // Data is being updated, but user is not allowed to edit this entry!
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Access denied for update on Phenotype "' . htmlspecialchars($aLine['id']) . '".');
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
                    $bIndInDB = in_array($aLine['individualid'], $aParsed['Individuals']['ids']);
                    $bIndInFile = isset($aParsed['Individuals']['data'][(int) $aLine['individualid']]);
                    if ($aLine['individualid'] && !$bIndInDB && !$bIndInFile) {
                        // Individual does not exist and is not defined in the import file.
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Individual "' . htmlspecialchars($aLine['individualid']) . '" does not exist in the database and is not defined in this import file.');
                    }

                    if ($zData) {
                        if ($nDiffScore && !lovd_isAuthorized('screening', $aLine['id'], false)) {
                            // Data is being updated, but user is not allowed to edit this entry!
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Access denied for update on Screening "' . htmlspecialchars($aLine['id']) . '".');
                        }
                    } else {
                        // FIXME: Default values of custom columns?
                        // Entry might still have thrown an error, but because we want to draw out all errors, we will store this one in case it's referenced to.
                        $aLine['todo'] = 'insert'; // OK, insert.
                    }
                    break;

                case 'Screenings_To_Genes':
                    // Editing will never be supported. Any change breaks the PK, so which entry would we edit?
                    // Create ID, so we can link to the data.
                    $aLine['id'] = (int) $aLine['screeningid'] . '|' . $aLine['geneid']; // This also means we lack the check for repeated lines!
                    // Manually check for repeated lines, to prevent query errors in case of inserts.
                    if (isset($aSection['data'][$aLine['id']])) {
                        // We saw this ID before in this file!
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): ID "' . htmlspecialchars($aLine['id']) . '" already defined at line ' . $aSection['data'][$aLine['id']]['nLine'] . '.');
                        break; // Stop processing this line.
                    }

                    // Check references.
                    $bGeneInDB = in_array($aLine['geneid'], $aParsed['Genes']['ids']);
                    if ($aLine['geneid'] && !$bGeneInDB) {
                        // Gene does not exist.
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Gene "' . htmlspecialchars($aLine['geneid']) . '" does not exist in the database.');
                    }
                    $bScreeningInDB = in_array($aLine['screeningid'], $aParsed['Screenings']['ids']);
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
                    foreach (array('position_g_start', 'position_g_end', 'mapping_flags') as $sCol) {
                        if ($aLine[$sCol] && !ctype_digit($aLine[$sCol])) {
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Invalid value in the \'' . $sCol . '\' field: "' . htmlspecialchars($aLine[$sCol]) . '" is not a numerical value.');
                        }
                    }

                    if ($zData) {
                        if ($nDiffScore && !lovd_isAuthorized('variant', $aLine['id'], false)) {
                            // Data is being updated, but user is not allowed to edit this entry!
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Access denied for update on Variant "' . htmlspecialchars($aLine['id']) . '".');
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
                    $bVariantInDB = in_array($aLine['variantid'], $aParsed['Variants_On_Genome']['ids']);
                    $bVariantInFile = isset($aParsed['Variants_On_Genome']['data'][(int) $aLine['variantid']]);
                    if ($aLine['id'] && !$bVariantInFile && !$bVariantInDB) {
                        // Variant does not exist and is not defined in the import file.
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Genomic Variant "' . htmlspecialchars($aLine['variantid']) . '" does not exist in the database and is not defined in this import file.');
                    }

                    foreach (array('position_c_start', 'position_c_start_intron', 'position_c_end', 'position_c_end_intron') as $sCol) {
                        if ($aLine[$sCol] && !is_numeric($aLine[$sCol])) {
                            // No ctype_digit() here, because that doesn't match negative numbers.
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Invalid value in the \'' . $sCol . '\' field: "' . htmlspecialchars($aLine[$sCol]) . '" is not a numerical value.');
                        }
                    }

                    if ($zData) {
                        if ($nDiffScore && !lovd_isAuthorized('variant', $aLine['id'], false)) {
                            // Data is being updated, but user is not allowed to edit this entry!
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Access denied for update on Variant "' . htmlspecialchars($aLine['id']) . '".');
                        }
                    } else {
                        // FIXME: Default values of custom columns?

                        // FIXME: Check if referenced variant is actually on the same chromosome?

                        if (!$bGeneInDB) {
                            // We're inserting this variant, but the gene does not exist yet, so we're not sure about the exact columns that will be active. For variants, this is fatal.
                            //   Actually, this error will always come with the error that the gene mentioned in the file is not yet inserted and that it can't be inserted by this script, right?
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): The gene this belonging to this variant entry is yet to be inserted into the database. First create the gene and set up the custom columns, then import the variants.');
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
                    // Create ID, so we can link to the data.
                    $aLine['id'] = (int) $aLine['screeningid'] . '|' . $aLine['variantid']; // This also means we lack the check for repeated lines!
                    // Manually check for repeated lines, to prevent query errors in case of inserts.
                    if (isset($aSection['data'][$aLine['id']])) {
                        // We saw this ID before in this file!
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): ID "' . htmlspecialchars($aLine['id']) . '" already defined at line ' . $aSection['data'][$aLine['id']]['nLine'] . '.');
                        break; // Stop processing this line.
                    }

                    // Check references.
                    $bScreeningInDB = in_array($aLine['screeningid'], $aParsed['Screenings']['ids']);
                    $bScreeningInFile = isset($aParsed['Screenings']['data'][(int) $aLine['screeningid']]);
                    if ($aLine['screeningid'] && !$bScreeningInFile && !$bScreeningInDB) {
                        // Screening does not exist and is not defined in the import file.
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Screening "' . htmlspecialchars($aLine['screeningid']) . '" does not exist in the database and is not defined in this import file.');
                    }
                    $bVariantInDB = in_array($aLine['variantid'], $aParsed['Variants_On_Genome']['ids']);
                    $bVariantInFile = isset($aParsed['Variants_On_Genome']['data'][(int) $aLine['variantid']]);
                    if ($aLine['variantid'] && !$bVariantInFile && !$bVariantInDB) {
                        // Variant does not exist and is not defined in the import file.
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Genomic Variant "' . htmlspecialchars($aLine['variantid']) . '" does not exist in the database and is not defined in this import file.');
                    }

                    if (!lovd_error()) {
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
                $nDataTotal ++;
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
            if (count($aUnknownCols)) {
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
        $_BAR[0]->setProgress(100); // To make sure we're at 100% (some errors skip the lovd_endLine()).





        // Intercept simulate (dry run).
        if (!empty($_POST['simulate']) && !lovd_error()) {
            // Stop here.
            lovd_errorAdd('', 'Simulation successful: no errors found.');
        }





        function lovd_findImportedID ($sSection, $nID)
        {
            // Returns the ID of a certain object as which it was imported in the database.
            // If not found, it will return the given ID.
            global $aParsed;

            if (isset($aParsed[$sSection]['data'][(int) $nID])) {
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
            $aGenes = array();
            $_DB->beginTransaction();

            foreach ($aParsed as $sSection => $aSection) {
                $aFields = $aSection['allowed_columns'];
                // We will unset the IDs, and generate new ones. All, but the Column and VOT sections, which don't have an PK AUTO_INCREMENT.
                if (in_array('id', $aFields) && !in_array($sSection, array('Columns', 'Variants_On_Transcripts'))) {
                    unset($aFields[array_search('id', $aFields)]);
                }
                $aDone[$sSection] = 0;

                foreach ($aSection['data'] as $nID => $aData) {
                    $nEntry++;
                    if (!$aData['todo'] || !in_array($aData['todo'], array('insert', 'update'))) {
                        continue;
                    }

                    // Data from LOVD downloads are escaped. Besides tabs, carriage returns and new lines, also quotes (both single and double) and backslashes are escaped.
                    foreach ($aData as $key => $sVal) {
                        // First prepare the \t, \r and \n codes, so they won't get lost.
                        $sVal = str_replace(array('\r', '\n', '\t'), array('\\\r', '\\\n', '\\\t'), $sVal);
                        $sVal = stripslashes($sVal);
                        $aData[$key] = str_replace(array('\r', '\n', '\t'), array("\r", "\n", "\t"), $sVal);
                    }

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
                                $aGenes[] = $aParsed['Transcripts']['data'][(int) $aData['transcriptid']]['geneid'];
                            }
                            $nNewID = $aSection['object']->insertEntry($aData, $aFields);
                            $aParsed[$sSection]['data'][$nID]['newID'] = $nNewID;

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

                // ID_pathogenic_ column (pat2var).
                if (!isset($aLinePat2Var['pathogenic']) || $aLinePat2Var['pathogenic'] === '') {
                    if (!array_key_exists($sPat2VarKey, $aPat2Var)) {
                        // 2008-05-28; 2.0-07; Changed default value from 99 to 55 (unknown).
                        $aLinePat2Var['pathogenic'] = '55';
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
                $_BAR[1]->setMessage('Done importing!', 'done');
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
                lovd_writeLog('Event', LOG_EVENT, 'Imported ' . $sMessage . '; ran ' . $nDone . ' queries (' . implode(', ', $aGenes) . ').');
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
            lovd_showInfoTable('No entries found that need to be imported in the database. Either your uploaded file contains no variants, or all entries are already in the database.', 'stop');
            $_T->printFooter();
            exit;
        }
    }

} else {
    // Default values.
//    $_POST['charset'] = 'utf8';
    $_POST['mode'] = 'insert';
}





$_T->printHeader();
$_T->printTitle('Import data in LOVD format');

print('      Using this form you can import files in LOVD\'s tab-delimited format. Currently supported imports are custom column, individual, phenotype, screening and variant data.<BR><I>Genomic positions in your data are assumed to be relative to Human Genome build ' . $_CONF['refseq_build'] . '</I>.<BR>' . "\n" .
      '      <BR>' . "\n\n");

lovd_showInfoTable('If you\'re looking for importing data files containing variant data only, like VCF files and SeattleSeq annotated files, please <A href="submit">start a new submission</A>.', 'information', 760);

// FIXME: Since we can increase the memory limit anyways, maybe we can leave this message out if we nicely handle the memory?
lovd_showInfoTable('In some cases importing big files or importing files into big databases can cause LOVD to run out of available memory. In case this server hides these errors, LOVD would return a blank screen. If this happens, split your import file into smaller chunks or ask your system administrator to allow PHP to use more memory (currently allowed: ' . ini_get('memory_limit') . 'B).', 'warning', 760);

// Warnings were shown in the progress bar, but I'd like to have them here too. They are still in the source, so we can use JS.
if ($nWarnings) {
    lovd_errorAdd('', '<A href="#" onclick="$(\'#warnings\').toggle(); if ($(\'#warnings_action\').html() == \'Show\') { $(\'#warnings_action\').html(\'Hide\'); } else { $(\'#warnings_action\').html(\'Show\') } return false;"><SPAN id="warnings_action">Show</SPAN> ' . $nWarnings . ' warning' . ($nWarnings == 1? '' : 's') . '</A><DIV id="warnings"></DIV><SCRIPT type="text/javascript">$("#warnings").hide();$("#warnings").html($("#lovd_parser_progress_message_done").html());</SCRIPT>');
}

lovd_errorPrint();

// Tooltip JS code.
lovd_includeJS('inc-js-tooltip.php');

print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post" enctype="multipart/form-data">' . "\n" .
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
            '<B>' . $aModes['update'] . '</B>: LOVD will compare all IDs given in the file with the contents of the database. LOVD will try and update entries already found in the database using the data in the file, and LOVD will add entries that exist in the file, but not in the database.<BR>' .
            '<B>' . $aModes['insert'] . '</B>: LOVD will use the IDs given in the file only to link the data together. All data in the file will be treated as new, and all data will receive new IDs once imported. The biggest advantage of this mode is that you do not need to know which IDs are free in the database.',
            'select', 'mode', 1, $aModes, false, false, false),
        array('', '', 'note', 'Please select which import mode LOVD should use; <I>' . implode('</I> or <I>', $aModes) . '</I>. For more information on the modes, move your mouse over the ? icon.'),
        array('Character encoding of imported file', 'If your file contains special characters like &egrave;, &ouml; or even just fancy quotes like &ldquo; or &rdquo;, LOVD needs to know the file\'s character encoding to ensure the correct display of the data.', 'select', 'charset', 1, $aCharSets, false, false, false),
        array('', '', 'note', 'Please only change this setting in case you encounter problems with displaying special characters in imported data. Technical information about character encoding can be found <A href="http://en.wikipedia.org/wiki/Character_encoding" target="_blank">on Wikipedia</A>.'),
        array('Simulate (don\'t actually import the data)', 'To check your file for errors, without actually importing anything, select this checkbox.', 'checkbox', 'simulate', 1),
        'skip',
        array('', '', 'submit', 'Import file'));

lovd_viewForm($aForm);

print('</FORM>' . "\n\n");

$_T->printFooter();
?>
