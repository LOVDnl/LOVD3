<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-09-19
 * Modified    : 2012-09-28
 * For LOVD    : 3.0-beta-09
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

define('ROOT_PATH', './');
require ROOT_PATH . 'inc-init.php';
ini_set('auto_detect_line_endings', true); // So we can work with Mac files also...

// Require curator clearance.
lovd_isAuthorized('gene', $_AUTH['curates']); // Any gene will do.
lovd_requireAUTH(LEVEL_CURATOR);

require ROOT_PATH . 'inc-lib-form.php';
// FIXME:
// When importing individuals, the panelid field is not properly checked. Object::checkFields() checks only the database, so this check should be disabled and enabled here in the file.





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





if (POST) {
    // Form sent, first check the file itself.
    lovd_errorClean();

    // If the file does not arrive (too big), it doesn't exist in $_FILES.
    if (empty($_FILES['import']) || ($_FILES['import']['error'] > 0 && $_FILES['import']['error'] < 4)) {
        lovd_errorAdd('import', 'There was a problem with the file transfer. Please try again. The file cannot be larger than ' . round($nMaxSize/pow(1024, 2), 1) . ' MB.');

    } else if ($_FILES['import']['error'] == 4 || !$_FILES['import']['size']) {
        lovd_errorAdd('import', 'Please select a file to upload.');

    } else if ($_FILES['import']['size'] > $nMaxSize) {
        lovd_errorAdd('import', 'The file cannot be larger than ' . round($nMaxSize/pow(1024, 2), 1) . ' MB.');

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
        $sType = mime_content_type($_FILES['import']['tmp_name']);
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
                'Genes', 'Transcripts', 'Diseases', 'Genes_To_Diseases', 'Individuals', 'Phenotypes', 'Screenings', 'Variants_On_Genome', 'Variants_On_Transcripts'
            ), array('allowed_columns' => array(), 'columns' => array(), 'data' => array(), 'ids' => array(), 'nColumns' => 0, 'object' => null, 'required_columns' => array(), 'settings' => array()));
        $aUsers = $_DB->query('SELECT id FROM ' . TABLE_USERS)->fetchAllColumn();
        $aImportFlags = array();
        $sFileVersion = $sFileType = $sCurrentSection = '';
        $bParseColumns = false;
        $nLine = 1;
        $nLines = count($aData);
        $nDataTotal = 0; // To show the second progress bar; how much actual work needs to be done?
        $sMode = $_POST['mode'];
        $sDate = date('Y-m-d H:i:s');

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
                    }
                    $sFileType = $aTypes[$sFileType];
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



            if ($sLine{0} == '#') {
                if (preg_match('/^#\s*([a-z_]+)\s*=\s*(.+)$/', $sLine, $aRegs)) {
                    // Import flag (setting).
                    $aImportFlags[$aRegs[1]] = $aRegs[2];
                } elseif (preg_match('/^##\s*([A-Za-z_]+)\s*##\s*Do not remove/', $sLine, $aRegs)) {
                    // New section.
                    // Clean up old section, if available.
                    if ($sCurrentSection) {
                        unset($aSection['allowed_columns']);
                        unset($aSection['columns']);
                        unset($aSection['nColumns']);
                        unset($aSection['object']);
                        unset($aSection['required_columns']);
                        unset($aSection['settings']);
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
                        case 'Genes':
                            break;
                        case 'Transcripts':
                            break;
                        case 'Diseases':
                            require_once ROOT_PATH . 'class/object_diseases.php';
                            // FIXME: If we end up never referencing to the object from a different section, then just call this $Obj and remove object from aParsed array.
                            $aSection['object'] = new LOVD_Disease();
                            break;
                        case 'Genes_To_Diseases':
                            $sTableName = 'TABLE_GEN2DIS';
                            break;
                        case 'Individuals':
                            require_once ROOT_PATH . 'class/object_individuals.php';
                            // FIXME: If we end up never referencing to the object from a different section, then just call this $Obj and remove object from aParsed array.
                            $aSection['object'] = new LOVD_Individual();
                            break;
                        case 'Individuals_To_Diseases':
                            $sTableName = 'TABLE_IND2DIS';
                            break;
                        case 'Phenotypes':
                            require_once ROOT_PATH . 'class/object_phenotypes.php';
                            // FIXME: If we end up never referencing to the object from a different section, then just call this $Obj and remove object from aParsed array.
                            // We don't create an object here, because we need to do that per disease. This means we don't have a general check for mandatory columns, which is not so much a problem I think.
                            $aSection['objects'] = array();
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
                if (!preg_match('/^(("\{\{[A-Za-z_\/]+\}\}"|\{\{[A-Za-z_\/]+\}\})\t)+$/', $sLine . "\t")) { // FIXME: Can we make this a simpler regexp?
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
                }
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



            // We've got a line of data here. Isolate the values and check all columns.
            $aLine = explode("\t", $sLine);
            // For any category, the number of columns should be the same as the number of fields.
            if (count($aLine) != $nColumns) {
                lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Found ' . count($aLine) . ' fields instead of the expected ' . $nColumns . '.');
                if (!lovd_endLine()) {
                    break;
                }
                continue; // Continue to the next line.
            }
            $aLine = array_map('trim', $aLine, array_fill(0, $nColumns, '" '));

            // Tag all fields with their respective column name. Then check data.
            $aLine = array_combine($aColumns, array_values($aLine));

            // Unset unused columns.
            foreach ($aUnknownCols as $sCol) {
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
            if ($sCurrentSection == 'Phenotypes') {
                if ($aLine['diseaseid']) {
                    if (!isset($aSection['objects'][(int) $aLine['diseaseid']])) {
                        $aSection['objects'][(int) $aLine['diseaseid']] = new LOVD_Phenotype($aLine['diseaseid']);
                    }
                    $aSection['object'] =& $aSection['objects'][(int) $aLine['diseaseid']];
                }
            }

            // General checks: checkFields().
            $zData = false;
            if (isset($aSection['object']) && is_object($aSection['object'])) {
                // Object has been created. If we're updating, get the current info from the database.
                if ($sMode == 'update') {
                    // FIXME: First check in 'ids' to predict if it exists or not?
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
                            $_BAR[0]->appendMessage('Warning: Will not edit ' . rtrim($sCurrentSection, 's') . ' "' . htmlspecialchars($aLine['id']) . '", the data in the database differs too much from the data in the import file, this looks like an unintended edit!<BR>', 'done');
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
                if ($sCurrentSection != 'Genes' && !ctype_digit($aLine['id'])) {
                    lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): ID "' . htmlspecialchars($aLine['id']) . '" is not a numerical value.');
                }
                if (isset($aSection['data'][$aLine['id']])) {
                    // We saw this ID before in this file!
                    lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): ID "' . htmlspecialchars($aLine['id']) . '" already defined at line ' . $aSection['data'][$aLine['id']]['nLine'] . '.');
                    continue; // Skip to next line.
                }
            }
            if (in_array($sCurrentSection, array('Diseases', 'Individuals', 'Phenotypes', 'Screenings', 'Variants_On_Genome'))) {
                foreach (array('owned_by', 'created_by', 'edited_by') as $sCol) {
                    if (in_array($sCol, $aColumns)) {
                        if ($aLine[$sCol] && !in_array($aLine[$sCol], $aUsers)) {
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): ' . $sCol . ' value "' . htmlspecialchars($aLine[$sCol]) . '" refers to non-existing user.');
                        } elseif (($sCol != 'edited_by' || $aLine['edited_date']) && !$aLine[$sCol]) {
                            // Edited_by is only filled in if empty and edited_date is filled in.
                            $aLine[$sCol] = $_AUTH['id'];
                        }
                    }
                }
                foreach (array('created_date', 'edited_date') as $sCol) {
                    if (in_array($sCol, $aColumns)) {
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
                    $bDiseaseInFile = in_array($aLine['diseaseid'], array_keys($aParsed['Diseases']['data']));
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
                        $aLine['todo'] = 'insert'; // OK, insert.
                    }
                    break;

                case 'Individuals_To_Diseases':
                    // Editing will never be supported. Any change breaks the PK, so which entry would we edit?
                    // Create ID, so we can link to the data.
                    $aLine['id'] = $aLine['individualid'] . '|' . (int) $aLine['diseaseid']; // This also means we lack the check for repeated lines!
                    // Manually check for repeated lines, to prevent query errors in case of inserts.
                    if (isset($aSection['data'][$aLine['id']])) {
                        // We saw this ID before in this file!
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): ID "' . htmlspecialchars($aLine['id']) . '" already defined at line ' . $aSection['data'][$aLine['id']]['nLine'] . '.');
                        break; // Stop processing this line.
                    }

                    // Check references.
                    $bIndInDB = in_array($aLine['individualid'], $aParsed['Individuals']['ids']);
                    $bIndInFile = in_array($aLine['individualid'], array_keys($aParsed['Individuals']['data']));
                    if ($aLine['individualid'] && !$bIndInDB && !$bIndInFile) {
                        // Individual does not exist and is not defined in the import file.
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Individual "' . htmlspecialchars($aLine['individualid']) . '" does not exist in the database and is not defined in this import file.');
                    }
                    $bDiseaseInDB = in_array($aLine['diseaseid'], $aParsed['Diseases']['ids']);
                    $bDiseaseInFile = in_array($aLine['diseaseid'], array_keys($aParsed['Diseases']['data']));
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
                    // Check references.
                    $bDiseaseInDB = in_array($aLine['diseaseid'], $aParsed['Diseases']['ids']);
                    $bDiseaseInFile = in_array($aLine['diseaseid'], array_keys($aParsed['Diseases']['data']));
                    if ($aLine['diseaseid'] && !$bDiseaseInFile && !$bDiseaseInDB) {
                        // Disease does not exist and is not defined in the import file.
                        lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Disease "' . htmlspecialchars($aLine['diseaseid']) . '" does not exist in the database and is not defined in this import file.');
                    } elseif (!$bDiseaseInDB || ($sMode == 'insert' && $bDiseaseInFile)) {
                        // We're inserting this disease, so we're not sure about the exact columns that will be active. Issue a warning.
                        $_BAR[0]->appendMessage('Warning (' . $sCurrentSection . ', line ' . $nLine . '): The disease belonging to this phenotype entry is yet to be inserted into the database. Perhaps not all this phenotype entry\'s custom columns will be enabled for this disease!<BR>', 'done');
                    }

                    if ($zData) {
                        if ($nDiffScore && !lovd_isAuthorized('phenotype', $aLine['id'], false)) {
                            // Data is being updated, but user is not allowed to edit this entry!
                            lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): Access denied for update on Phenotype "' . htmlspecialchars($aLine['id']) . '".');
                        }
                    } else {
                        // FIXME: Default values of custom columns?
                        $aLine['todo'] = 'insert'; // OK, insert.
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
                $aSection['data'][$aLine['id']] = $aLine;
                $nDataTotal ++;
            }

            $_BAR[0]->setProgress(($nLine/$nLines)*100);

            if (!lovd_endLine()) {
                // Too many errors.
                break;
            }
        }





        // Now we have everything parsed. If there were errors, we are stopping now.
        if (!lovd_error()) {
            foreach ($aParsed as $sSection => $aSection) {



break;
// Check if we understand all references (genes, transcripts, diseases).
//   Somehow, the system must be able to in a later time parse diseases etc, but for now we should understand that the disease is not in the file and not in the system, so we don't understand everything.
// Then, load all new data.
// Then, verify and process all edits.
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
            // Loop lines.
            $nLine = 2; // Header = line 2.
            // 2008-09-15; 2.0-12; Added increased execution time to script to help import bigger files.
            if ((int) ini_get('max_execution_time') < 60) {
                set_time_limit(60);
            }

            // This can be a quite intensive process, but we need to check and verify all the data.
            $aVariants = array();
            $aPatients = array();
            $aPat2Var  = array();
            $nVariants = 0;
            $nPatients = 0;
            $nPat2Var  = 0;

            // 2008-11-13; 2.0-14 added by Gerard
            // Initiate an array to keep track of assigned Variant/DBID numbers
            // use variants already seen in the upload file as keys when you add the number
            $aIDAssigned = array();
            // 2009-01-27; 2.0-15; Keep track of the maximum variantid in use.
            $nMaxVariantID = 0;



            // Read rest of the file.
            // 2010-11-24; 2.0-23; Totally empty lines made the while-loop quit. Moving the rtrim() elsewhere.
            while (!feof($fInput) && $sLine = fgets($fInput, 4096)) {
                $nLine ++;
                $aLine = explode("\t", $sLine);
                // 2010-07-21; 2.0-28; Add empty fields to $aLine if necessary, to prevent notices.
                $aLine = array_pad($aLine, $nColumns, '');
                $aLineVar = array();
                $aLinePat = array();
                $aLinePat2Var = array();

                foreach ($aLine as $nKey => $sVal) {
                    // Loop data, and verify it.
                    // Check given ID's.
                    switch ($sCol) {
                        case 'ID_sort_':
                            // If empty, will be determined at the end of this line's run.
                            $aLineVar['sort'] = $sVal;
                            break;
                        case 'ID_allele_':
                            if ($sVal !== '') {
                                if (!array_key_exists($sVal, $_SETT['var_allele'])) {
                                    lovd_errorAdd('Error in line ' . $nLine . ': value "' . htmlspecialchars($sVal) . '" in "' . $sCol . '" column is not a valid allele ID.');
                                }
                            }
                            $aLinePat2Var['allele'] = $sVal;
                            break;
                        case 'ID_pathogenic_':
                            if ($sVal !== '') {
                                if ($sFormatVersion < '2000-040' && in_array($sVal, array('00', '01', '09', '10', '11', '19', '90', '91', '99'))) {
                                    // 2008-02-29; 2.0-04; Changed the whole pathogenicity ID code list.
                                    $sVal = $aTransformPathogenicity[$sVal{0}] . $aTransformPathogenicity[$sVal{1}];
                                }
                                // Empty value may not default to '00'!
                                $sVal = str_pad($sVal, 2, '0', STR_PAD_LEFT);
                                if (!array_key_exists($sVal, $_SETT['var_pathogenic_short'])) {
                                    lovd_errorAdd('Error in line ' . $nLine . ': value "' . htmlspecialchars($sVal) . '" in "' . $sCol . '" column is not a valid pathogenicity ID.');
                                }
                            }
                            $aLinePat2Var['pathogenic'] = $sVal;
                            break;
                        default:
                            // Variant or Patient column. Additional checking.
                            // Directly accessing $aColList, since the $_CURRDB methods are not going to help me here.

                            if ($sVal) {
                                // Regular expressions.
                                if ($_CURRDB->aColList[$sCol]['preg_pattern'] && !preg_match($_CURRDB->aColList[$sCol]['preg_pattern'], $sVal)) {
                                    lovd_errorAdd('Error in line ' . $nLine . ': value "' . htmlspecialchars($sVal) . '" in "' . $sCol . '" column does not correspond to the required input pattern.');
                                }
                            }

                            // Store column value.
                            if (substr($sCol, 0, 7) == 'Variant') {
                                $aLineVar[$sCol] = $sVal;
                            } else {
                                $aLinePat[$sCol] = $sVal;
                            }
                    }

                    if (substr_count($_ERROR, "\n") >= $nMaxErrors) {
                        // Too many errors. Quit now.
                        lovd_errorAdd('Too many errors, stopping file processing.');
                        break 2;
                    }
                }

                // 2009-03-02; 2.0-16; ID_allele_ of course needs a default value.
                // ID_allele_ (pat2var).
                if (empty($aLinePat2Var['allele'])) {
                    $aLinePat2Var['allele'] = 0;
                }

                // 2009-02-09; 2.0-16; Move this up to prevent notices and failure to import variants.
                // 2009-01-27; 2.0-15; Generates a new variantid for each variant with an empty field.
                if (empty($aLineVar['variantid'])) {
                    // The ID_variantid_ field in the upload file is empty so we need to generate a variantid.
                    if (!$nMaxVariantID) {
                        list($nMaxVariantID) = mysql_fetch_row(mysql_query('SELECT MAX(variantid) FROM ' . TABLE_CURRDB_VARS));
                    }
                    $nVariantID = ++ $nMaxVariantID;
                    $aLineVar['variantid'] = $nVariantID;
                    $aLinePat2Var['variantid'] = $nVariantID;
                    $sPat2VarKey = $nVariantID . '|' . $nPatientID . '|' . $aLinePat2Var['allele'];
                }

                // Auto values!
                $sPat2VarKey = $nVariantID . '|' . $nPatientID . '|' . $aLinePat2Var['allele'];

                // If this entry is already in the database, we don't have to do anything 'cause only the first instance
                // will be loaded into the database. The check for consistency (below) will only complain about an
                // unequal value if the second value is not empty.
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

                // variant_created_by_ (pat2var).
                if (empty($aLinePat2Var['created_by'])) {
                    if (!array_key_exists($sPat2VarKey, $aPat2Var)) {
                        $aLinePat2Var['created_by'] = (!empty($aLinePat['submitterid'])? 0 : $_AUTH['userid']);
                    }
                }

                // variant_created_date_ (pat2var).
                if (empty($aLinePat2Var['created_date'])) {
                    if (!array_key_exists($sPat2VarKey, $aPat2Var)) {
                        $aLinePat2Var['created_date'] = date('Y-m-d H:i:s');
                    }
                }

                // patient_created_by_ (patient).
                if (empty($aLinePat['created_by'])) {
                    if (!array_key_exists($nPatientID, $aPatients)) {
                        $aLinePat['created_by'] = (!empty($aLinePat['submitterid'])? 0 : $_AUTH['userid']);
                    }
                }

                // patient_created_date_ (patient).
                if (empty($aLinePat['created_date'])) {
                    if (!array_key_exists($nPatientID, $aPatients)) {
                        $aLinePat['created_date'] = date('Y-m-d H:i:s');
                    }
                }

                // 2008-11-13; 2.0-14 Added by Gerard. Generates a Variant/DBID.
                $sVariant = str_replace(array('(', ')', '?'), '', $aLineVar[$sMutationCol]);
                if (empty($aLineVar['Variant/DBID'])) {
                    // The Variant/DBID field in the upload file is empty so we need to generate it.
                    $sIDGenerated = lovd_fetchDBID($_SESSION['currdb'], $sVariant, $sMutationCol); // Finds an existing number or generates a new one BUT EACH TIME THE SAME

                    // If the variant is already in the database (ID_variantid_), then the other part of the code will check the DBID.
                    if (!array_key_exists($nVariantID, $aVariants)) {
                        // Not in the database yet.
                        if (!array_key_exists($sVariant, $aIDAssigned)) {
                            // This variant (DNA level) has not been found earlier in this imported file.
                            if (!in_array($sIDGenerated, $aIDAssigned)) {
                                // The generated DBID (IDgenerated) was not already used, so we can use it.
                                $aIDAssigned[$sVariant] = $sIDGenerated;
                            } else {
                                // The IDgenerated (Variant/DBID number) is already in use by a different variant, so we need a new IDGenerated
                                // This newly generated number is already in use, so add 1 to the highest value
                                $sIDGenerated = max($aIDAssigned);
                                $sIDGenerated ++;
                                $aIDAssigned[$sVariant] = $sIDGenerated;
                            }
                        } else {
                            // We've seen this variant (DNA level) before in the file. Se we know an ID already!
                            $sIDGenerated = $aIDAssigned[$sVariant];
                        }
                        $aLineVar['Variant/DBID'] = $sIDGenerated;
                    }

                } elseif (isset($aLineVar['Variant/DBID']) && preg_match('/^' . $_SESSION['currsymb'] . '_[0-9]{5}/', $aLineVar['Variant/DBID'])) {
                    // User has filled in an own DBID value that is correct. Let's use it.
                    $aIDAssigned[$sVariant] = substr($aLineVar['Variant/DBID'], 0, strlen($_SESSION['currsymb']) + 6);
                }

                // This information is not taken from the database.
                $aLineVar['indb'] = false;
                $aLinePat['indb'] = false;
                $aLinePat2Var['indb'] = false;
                $aLinePat2Var['symbol'] = $_SESSION['currdb'];

                // Save line number.
                $aLineVar['line'] = $nLine;
                $aLinePat['line'] = $nLine;
                $aLinePat2Var['line'] = $nLine;

                // Seen variant before? Check data.
                if (array_key_exists($nVariantID, $aVariants)) {
                    foreach ($aLineVar as $sCol => $sVal) {
                        // 2009-04-16; 2.0-18; Compare values trim()ed to make sure spaces are not a problem anymore.
                        if (!in_array($sCol, array('indb', 'line')) && $sVal && trim($sVal) != trim($aVariants[$nVariantID][$sCol])) {
                            // Col should not be indb or line; value should not be empty (otherwise, difference allowed); if value is different: throw error!
                            lovd_errorAdd('Error in line ' . $nLine . ': value "' . htmlspecialchars($sVal) . '" in "' . $sCol . '" column does not match value "' . $aVariants[$nVariantID][$sCol] . '" from same variant entry ' . ($aVariants[$nVariantID]['indb']? 'in the database' : 'on line ' . $aVariants[$nVariantID]['line']) . '.');
                        }
                    }
                } else {
                    // Store information.
                    $aVariants[$nVariantID] = $aLineVar;
                    $nVariants ++;
                }

                // Seen patient before? Check data.
                if (array_key_exists($nPatientID, $aPatients)) {
                    foreach ($aLinePat as $sCol => $sVal) {
                        // 2009-04-16; 2.0-18; Compare values trim()ed to make sure spaces are not a problem anymore.
                        if (!in_array($sCol, array('indb', 'line')) && $sVal && trim($sVal) != trim($aPatients[$nPatientID][$sCol])) {
                            // Col should not be indb or line; value should not be empty (otherwise, difference allowed); if value is different: throw error!
                            lovd_errorAdd('Error in line ' . $nLine . ': value "' . htmlspecialchars($sVal) . '" in "' . $sCol . '" column does not match value "' . $aPatients[$nPatientID][$sCol] . '" from same patient entry ' . ($aPatients[$nPatientID]['indb']? 'in the database' : 'on line ' . $aPatients[$nPatientID]['line']) . '.');
                        }
                    }
                } else {
                    // Store information.
                    $aPatients[$nPatientID] = $aLinePat;
                    $nPatients ++;
                }

                // Seen pat2var before? Check data.
                if (array_key_exists($sPat2VarKey, $aPat2Var)) {
                    if ($aPat2Var[$sPat2VarKey]['indb'] == false) {
                        // 2010-07-20; 2.0-28; This information was seen before in the import file
                        lovd_errorAdd('Error in line ' . $nLine . ': the exact combination of this variant, patient and allele was already seen before in this import file. Note that for expressing homozygous mutations, you need to select a different allele for both variants!');
                    }
                    foreach ($aLinePat2Var as $sCol => $sVal) {
                        // 2009-04-16; 2.0-18; Compare values trim()ed to make sure spaces are not a problem anymore.
                        if (!in_array($sCol, array('indb', 'line')) && $sVal && trim($sVal) != trim($aPat2Var[$sPat2VarKey][$sCol])) {
                            // Col should not be indb or line; value should not be empty (otherwise, difference allowed); if value is different: throw error!
                            lovd_errorAdd('Error in line ' . $nLine . ': value "' . htmlspecialchars($sVal) . '" in "' . $sCol . '" column does not match value "' . $aPat2Var[$sPat2VarKey][$sCol] . '" from same patients2variants entry ' . ($aPat2Var[$sPat2VarKey]['indb']? 'in the database' : 'on line ' . $aPat2Var[$sPat2VarKey]['line']) . '.');
                        }
                    }
                } else {
                    // Store information.
                    $aPat2Var[(int) $nVariantID . '|' . (int) $nPatientID . '|' . $aLinePat2Var['allele']] = $aLinePat2Var;
                    $nPat2Var ++;
                }
            }
            fclose($fInput);

            // 2008-02-12; 2.0-04
            // Save even more memory. Unset all the unnecessary stuff.
            reset($aVariants);
            reset($aPatients);
            reset($aPat2Var);
            while (list($sKey, $aVal) = each($aVariants)) {
                if (!isset($aVal['sort'])) {
                    unset($aVariants[$sKey]);
                }
            }
            while (list($sKey, $aVal) = each($aPatients)) {
                if (!isset($aVal['created_by'])) {
                    unset($aPatients[$sKey]);
                }
            }
            while (list($sKey, $aVal) = each($aPat2Var)) {
                if (!isset($aVal['created_by'])) {
                    unset($aPat2Var[$sKey]);
                }
            }



            if (!lovd_error()) {
                // Start importing from the memory!
                require ROOT_PATH . 'inc-top.php';
                lovd_printHeader('config', 'LOVD Configuration area');

                $nTotal = $nVariants + $nPatients + $nPat2Var;
                if (!$nTotal) {
                    print('      No entries found that need to be imported in the database. Either your uploaded file contains no variants, or all entries are already in the database.<BR>' . "\n\n");
                    require ROOT_PATH . 'inc-bot.php';
                    exit;
                }

                // Progress bar.
                print('      Input file verified. Importing entries...<BR><BR>' . "\n" .
                      '      <TABLE border="0" cellpadding="0" cellspacing="0" class="S11" width="250">' . "\n" .
                      '        <TR style="height : 15px;">' . "\n" .
                      '          <TD width="100%" style="border : 1px solid black;">' . "\n" .
                      '            <IMG src="' . ROOT_PATH . 'gfx/trans.png" alt="" title="" width="0%" height="15" id="lovd_progress_import" style="background : #AFC8FA;"></TD>' . "\n" .
                      '          <TD align="right" width="25"><INPUT type="text" id="lovd_progress_import_value" size="3" value="0%" style="border : 0px; margin : 0px; padding : 0px; text-align : right;"></TD></TR></TABLE><BR>' . "\n\n" .
                      '      <SCRIPT type="text/javascript">var progressImport = document.getElementById(\'lovd_progress_import\'); var progressImportValue = document.getElementById(\'lovd_progress_import_value\');</SCRIPT>' . "\n");

                // Import...
                $nProgressPrev = '0%';
                $n = 0;

                // If using transactional tables; begin transaction.
                if ($_INI['database']['engine'] == 'InnoDB') {
                    // FIXME; It's better to use 'START TRANSACTION', but that's only available from 4.0.11.
                    //   This works from the introduction of InnoDB in 3.23.
                    @mysql_query('BEGIN WORK');
                }



                // Variants.
                foreach ($aVariants as $nKey => $aVal) {
                    if ($aVal['indb']) {
                        continue;
                    }

                    // 2011-09-21; 2.0-33; Fixed bug; carriage returns, new lines, and tabs were not decoded before importing!
                    $aVal = str_replace(array('\r', '\n', '\t'), array("\r", "\n", "\t"), $aVal);

                    $n++;
                    $sQ = 'INSERT INTO ' . TABLE_CURRDB_VARS . ' (';
                    $sCols = '';
                    $sVals = '';
                    foreach ($aVal as $sCol => $sVal) {
                        if (in_array($sCol, array('indb', 'line'))) {
                            continue;
                        }
                        $sCols .= ($sCols? ', ' : '') . '`' . $sCol . '`';
                        // 2009-06-11; 2.0-19; Added mysql_real_escape_string() to prevent SQL injection.
                        $sVals .= ($sVals? ', ' : '') . '"' . mysql_real_escape_string($sVal) . '"';
                    }
                    $sQ .= $sCols . ') VALUES (' . $sVals . ')';
                    $q = mysql_query($sQ);
                    if (!$q) {
                        // Save the mysql_error before it disappears.
                        $sError = mysql_error();
                        if ($_INI['database']['engine'] == 'InnoDB') {
                            @mysql_query('ROLLBACK');
                        }
                        lovd_dbFout('VariantImportA', $sQ, $sError);
                    }

                    $nProgress = round(($n*100)/$nTotal) . '%';
                    if ($nProgress != $nProgressPrev) {
                        print('      <SCRIPT type="text/javascript">progressImport.style.width = \'' . $nProgress . '\'; progressImportValue.value = \'' . $nProgress . '\';</SCRIPT>' . "\n");
                    }

                    flush();
                    usleep(1000);
                    $nProgressPrev = $nProgress;
                }



                // Patients.
                foreach ($aPatients as $nKey => $aVal) {
                    if ($aVal['indb']) {
                        continue;
                    }

                    // 2011-09-21; 2.0-33; Fixed bug; carriage returns, new lines, and tabs were not decoded before importing!
                    $aVal = str_replace(array('\r', '\n', '\t'), array("\r", "\n", "\t"), $aVal);

                    $n++;
                    $sQ = 'INSERT INTO ' . TABLE_PATIENTS . ' (';
                    $sCols = '';
                    $sVals = '';
                    foreach ($aVal as $sCol => $sVal) {
                        if (in_array($sCol, array('indb', 'line'))) {
                            continue;
                        }
                        $sCols .= ($sCols? ', ' : '') . '`' . $sCol . '`';
                        // Quick fix; change edited* fields "" to NULL.
                        // 2009-06-11; 2.0-19; Added mysql_real_escape_string() to prevent SQL injection.
                        $sVals .= ($sVals? ', ' : '') . (substr($sCol, 0, 7) == 'edited_' && !$sVal? 'NULL' : '"' . mysql_real_escape_string($sVal) . '"');
                    }
                    $sQ .= $sCols . ') VALUES (' . $sVals . ')';
                    $q = mysql_query($sQ);
                    if (!$q) {
                        // Save the mysql_error before it disappears.
                        $sError = mysql_error();
                        if ($_INI['database']['engine'] == 'InnoDB') {
                            @mysql_query('ROLLBACK');
                        }
                        lovd_dbFout('VariantImportB', $sQ, $sError);
                    }

                    $nProgress = round(($n*100)/$nTotal) . '%';
                    if ($nProgress != $nProgressPrev) {
                        print('      <SCRIPT type="text/javascript">progressImport.style.width = \'' . $nProgress . '\'; progressImportValue.value = \'' . $nProgress . '\';</SCRIPT>' . "\n");
                    }

                    flush();
                    usleep(1000);
                    $nProgressPrev = $nProgress;
                }



                // Pat2Var data.
                foreach ($aPat2Var as $nKey => $aVal) {
                    if ($aVal['indb']) {
                        continue;
                    }

                    $n++;
                    $sQ = 'INSERT INTO ' . TABLE_PAT2VAR . ' (';
                    $sCols = '';
                    $sVals = '';
                    foreach ($aVal as $sCol => $sVal) {
                        if (in_array($sCol, array('indb', 'line'))) {
                            continue;
                        }
                        $sCols .= ($sCols? ', ' : '') . '`' . $sCol . '`';
                        // Quick fix; change edited* fields "" to NULL.
                        // 2009-06-11; 2.0-19; Added mysql_real_escape_string() to prevent SQL injection.
                        $sVals .= ($sVals? ', ' : '') . (substr($sCol, 0, 7) == 'edited_' && !$sVal? 'NULL' : '"' . mysql_real_escape_string($sVal) . '"');
                    }
                    $sQ .= $sCols . ') VALUES (' . $sVals . ')';
                    $q = mysql_query($sQ);
                    if (!$q) {
                        // Save the mysql_error before it disappears.
                        $sError = mysql_error();
                        if ($_INI['database']['engine'] == 'InnoDB') {
                            @mysql_query('ROLLBACK');
                        }
                        lovd_dbFout('VariantImportC', $sQ, $sError);
                    }

                    $nProgress = round(($n*100)/$nTotal) . '%';
                    if ($nProgress != $nProgressPrev) {
                        print('      <SCRIPT type="text/javascript">progressImport.style.width = \'' . $nProgress . '\'; progressImportValue.value = \'' . $nProgress . '\';</SCRIPT>' . "\n");
                    }

                    flush();
                    usleep(1000);
                    $nProgressPrev = $nProgress;
                }



                // If we don't do this, we haven't got anything in the DB... duh!
                if ($_INI['database']['engine'] == 'InnoDB') {
                    // Could this actually fail?!!??
                    @mysql_query('COMMIT');
                }

                // 2007-12-05; 2.0-02; Fixed bug #20 - Gene's "Last update" field not updated.
                lovd_setUpdatedDate($_SESSION['currdb']);

                // 2010-08-12; 2.0-29; No imported variants have mapping info, so reset the mapping!
                $_SESSION['mapping']['time_complete'] = 0; // Redo mapping.

                // Done!!!
                // 2009-04-16; 2.0-18; Used $nPat2Var instead of $nVariants to count *all* variants, not just the new ones.
                lovd_writeLog('MySQL:Event', 'VariantImport', $_AUTH['username'] . ' (' . mysql_real_escape_string($_AUTH['name']) . ') successfully imported ' . $nPat2Var . ' variant' . ($nVariants == 1? '' : 's') . ' and ' . $nPatients . ' patient' . ($nPatients == 1? '' : 's') . ' (' . $_SESSION['currdb'] . ')');

                print('      Done importing!<BR><BR>' . "\n\n");
                print('      <SCRIPT type="text/javascript">' . "\n" .
                      '        <!--' . "\n" .
                      '        setTimeout("window.location.href = \'' . PROTOCOL . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/config.php' . lovd_showSID() . '\'", ' . (3000 + (count($aIgnoredColumns) * 2000)) . ');' . "\n" .
                      '        // -->' . "\n" .
                      '      </SCRIPT>' . "\n\n");

                require ROOT_PATH . 'inc-bot.php';
                exit;
            }
*///////////////////////////////////////////////////////////////////////////////
                $_BAR[0]->setProgress(($nLine/$nLines)*100);
            }
            print('<BR><BR><BR>');
            var_dump($sFileType);
            var_dump($aImportFlags);
            var_dump($sCurrentSection);
            print('<BR>');
            var_dump(implode("\n", $aData));
            $_T->printFooter();
            exit;
        }

        // Errors...
//        $_BAR[0]->remove();
//        $_BAR[0]->setMessageVisibility('', false);
//        $_BAR[0]->setMessageVisibility('done', false);
    }
} else {
    // Default values.
//    $_POST['charset'] = 'utf8';
    $_POST['mode'] = 'insert';
}





$_T->printHeader();
$_T->printTitle('Import data in LOVD format');

print('      Using this form you can import files in LOVD\'s tab-delimited format. Currently supported imports are individual, phenotype, screening and variant data.<BR><I>Genomic positions in your data are assumed to be relative to Human Genome build ' . $_CONF['refseq_build'] . '</I>.<BR>' . "\n" .
      '      <BR>' . "\n\n");

// FIXME: Since we can increase the memory limit anyways, maybe we can leave this message out if we nicely handle the memory?
lovd_showInfoTable('In some cases importing big files can cause LOVD to run out of available memory. In case this server hides these errors, LOVD would return a blank screen. If this happens, split your import file into smaller chunks or ask your system administrator to allow PHP to use more memory (currently allowed: ' . ini_get('memory_limit') . 'B).', 'warning', 760);

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
        array('', '', 'note', 'The maximum file size accepted is ' . round($nMaxSize/pow(1024, 2), 1) . ' MB' . ($nMaxSize == $nMaxSizeLOVD? '' : ', due to restrictions on this server. If you wish to have it increased, contact the server\'s system administrator') . '.'),
        'hr',
        'skip',
        array('', '', 'print', '<B>Import options</B>'),
        'hr',
        array('Import mode', 'Available modes:<BR>' .
            '<B>' . $aModes['update'] . '</B>: LOVD will compare all IDs given in the file with the contents of the database. LOVD will try and update entries already found in the database using the data in the file, and LOVD will add entries that exist in the file, but not in the database.<BR>' .
            '<B>' . $aModes['insert'] . '</B>: LOVD will use the IDs given in the file only to link the data together. All data in the file will be treated as new, and all data will receive new IDs once imported. The biggest advantage of this mode is that you do not need to know which IDs are free.',
            'select', 'mode', 1, $aModes, false, false, false),
        array('', '', 'note', 'Please select which import mode LOVD should use; <I>' . implode('</I> or <I>', $aModes) . '</I>. For more information on the modes, move your mouse over the ? icon.'),
        array('Character encoding of imported file', 'If your file contains special characters like &egrave;, &ouml; or even just fancy quotes like &ldquo; or &rdquo;, LOVD needs to know the file\'s character encoding to ensure the correct display of the data.', 'select', 'charset', 1, $aCharSets, false, false, false),
        array('', '', 'note', 'Please only change this setting in case you encounter problems with displaying special characters in imported data. Technical information about character encoding can be found <A href="http://en.wikipedia.org/wiki/Character_encoding" target="_blank">on Wikipedia</A>.'),
        'skip',
        array('', '', 'submit', 'Import file'));

lovd_viewForm($aForm);

print('</FORM>' . "\n\n");

$_T->printFooter();
//var_dump($aParsed);
?>
