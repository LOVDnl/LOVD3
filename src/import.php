<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-09-19
 * Modified    : 2012-09-21
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





$aModes =
    array(
        'update' => 'Update existing data & add new data',
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

function lovd_endLine ()
{
    // Ends the current line by cleaning up the memory and changing the line number.
    global $aData, $i, $nLine;

    unset($aData[$i]);
    $nLine ++;
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

                // Open the file, check line endings and encodings, try to use as little memory as possible.
                ini_set('auto_detect_line_endings', true); // Also detect Mac line endings.
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
                'Genes', 'Transcripts', 'Diseases', 'Individuals', 'Phenotypes', 'Screenings', 'Variants_On_Genome', 'Variants_On_Transcripts'
            ), array('allowed_columns' => array(), 'columns' => array(), 'data' => array(), 'ids' => array(), 'nColumns' => 0, 'object' => null, 'required_columns' => array(), 'settings' => array()));
        $aUsers = $_DB->query('SELECT id FROM ' . TABLE_USERS)->fetchAllColumn();
        $aImportFlags = array();
        $sFileVersion = $sFileType = $sCurrentSection = '';
        $bParseColumns = false;
        $nLine = 1;
        $nLines = count($aData);
        $nDataTotal = 0; // To show the second progress bar; how much actual work needs to be done?

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
                    $sCurrentSection = $aRegs[1];
                    $bParseColumns = true;

                    // Section-specific settings and definitions.
                    if (!in_array($sCurrentSection, array())) {
                        $aParsed[$sCurrentSection]['required_columns'][] = 'id';
                    }
                    if (defined('TABLE_' . strtoupper($sCurrentSection))) {
                        // TABLE_GENES, TABLE_TRANSCRIPTS, etc.
                        $aParsed[$sCurrentSection]['allowed_columns'] = lovd_getColumnList(constant('TABLE_' . strtoupper($sCurrentSection)));
                        $aParsed[$sCurrentSection]['ids'] = $_DB->query('SELECT id FROM ' . constant('TABLE_' . strtoupper($sCurrentSection)))->fetchAllColumn();
                    }
                    switch ($sCurrentSection) {
                        case 'Genes':
                            break;
                        case 'Transcripts':
                            break;
                        case 'Diseases':
                            require_once ROOT_PATH . 'class/object_diseases.php';
                            // FIXME: If we end up never referencing to the object of a different section, then just call this $Obj and remove object from aParsed array.
                            $aParsed[$sCurrentSection]['object'] = new LOVD_Disease();
                            break;
                        default:
                            // Category not recognized!
                            lovd_errorAdd('import', 'Error (line ' . $nLine . '): unknown section "' . $sCurrentSection . '".');
                            break 2;
                    }

                } // Else, it's just comments we will ignore.
                lovd_endLine();
                continue;
            }



            if ($bParseColumns) {
                // We are expecting columns now, because we just started a new section.
                if (!preg_match('/^(("\{\{[A-Za-z_\/]+\}\}"|\{\{[A-Za-z_\/]+\}\})\t)+$/', $sLine . "\t")) { // FIXME: Can we make this a simpler regexp?
                    // Columns not found; either we have data without a column header, or a malformed column header. Abort import.
                    lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): expected column header, got this instead:<BR><BLOCKQUOTE>' . htmlspecialchars($sLine) . '</BLOCKQUOTE>');
                    break;
                }

                // So we can use short variables.
                $aSection = &$aParsed[$sCurrentSection];
                $aColumns = &$aSection['columns'];
                $nColumns = &$aSection['nColumns'];

                $aColumns = explode("\t", $sLine);
                $nColumns = count($aColumns);
                $aColumns = array_map('trim', $aColumns, array_fill(0, $nColumns, '"{ }'));

                // Do we have all required columns?
                $aMissingCols = array_diff($aSection['required_columns'], $aColumns);
                if (count($aMissingCols)) {
                    lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): missing required column' . (count($aMissingCols) == 1? '' : 's') . ': ' . implode(', ', $aMissingCols) . '.');
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
                    lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): the following column' . (count($aDuplicateColumns) == 1? ' is' : 's are') . ' present more than once in the list of fields: ' . implode(', ', $aDuplicateColumns) . '. Please inspect your file and make sure that the column headers contain no duplicates.');
                }

                $bParseColumns = false;
                lovd_endLine();
                continue;
            }



            // We've got a line of data here. Isolate the values and check all columns.
            $aLine = explode("\t", $sLine);
            // For any category, the number of columns should be the same as the number of fields.
            if (count($aLine) != $nColumns) {
                lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): found ' . count($aLine) . ' fields instead of the expected ' . $nColumns . '.');
                lovd_endLine();
                continue; // Continue to the next line.
            }
            $aLine = array_map('trim', $aLine, array_fill(0, $nColumns, '" '));

            // Tag all fields with their respective column name. Then check data.
            $aLine = array_combine($aColumns, array_values($aLine));

            // Unset unused columns.
            foreach ($aUnknownCols as $sCol) {
                unset($aLine[$sCol]);
            }



            // General checks: checkFields().
            // FIXME: Not all checks are performed correctly, because checkFields() may depend on ACTION, which is not set right now.
            //    Example: the OMIM ID of a disease is not checked if it exists in the database already, when we're adding.
            //    We should also create an $zData if we're editing.
            if (is_object($aParsed[$sCurrentSection]['object'])) {
                // Object has been created. Use the object's checkFields() to have the values checked.
                // FIXME: Highly inefficient, checkFields() calls getForm() which actually runs queries in the database.
                //   checkFields() is not designed for being used in imports! We still need to improve its efficiency for this type of use.
                $nErrors = count($_ERROR['messages']); // We'll need to mark the generated errors.
                $aParsed[$sCurrentSection]['object']->checkFields($aLine);
                for ($i = $nErrors; isset($_ERROR['messages'][$i]); $i++) {
                    $_ERROR['fields'][$i] = ''; // It wants to highlight a field that's not here right now.
                    $_ERROR['messages'][$i] = 'Error (line ' . $nLine . '): ' . $_ERROR['messages'][$i];
                }
            }

            // General checks: numerical ID, owned_by, created_* and edited_*.
            if ($sCurrentSection != 'Genes' && in_array('id', $aColumns) && !ctype_digit($aLine['id'])) {
                lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): id value "' . htmlspecialchars($aLine['id']) . '" is not a numerical value.');
            }
            foreach (array('owned_by', 'created_by', 'edited_by') as $sCol) {
                if (in_array($sCol, $aColumns) && $aLine[$sCol] && !in_array($aLine[$sCol], $aUsers)) {
                    lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): ' . $sCol . ' value "' . htmlspecialchars($aLine[$sCol]) . '" refers to non-existing user.');
                }
            }
            foreach (array('created_date', 'edited_date') as $sCol) {
                if (in_array($sCol, $aColumns) && $aLine[$sCol] && !lovd_matchDate($aLine[$sCol], true)) {
                    lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): ' . $sCol . ' value "' . htmlspecialchars($aLine[$sCol]) . '" is not a correct date format, use the format YYYY-MM-DD HH:MM:SS.');
                }
            }




            // Per category, verify the data, including precise checks on specific columns.
            switch ($sCurrentSection) {
                case 'Genes':
                    // FIXME: right now just assuming we're doing a full import, not a gene import. Disallow genes that do not exist.
                    if (!in_array($aLine['id'], $aSection['ids'])) {
                        $_BAR[0]->appendMessage('Warning: gene "' . htmlspecialchars($aLine['id'] . '" (' . $aLine['name']) . ') does not exist in the database. Currently, it is not possible to import genes into LOVD using this file format.<BR>', 'done');
                        break; // Continue to next line.
                    }
                    break;

                case 'Transcripts':
                    // FIXME: right now just assuming we're doing a full import, not a transcript import. Disallow transcripts that do not exist.
                    if (!in_array($aLine['id'], $aSection['ids'])) {
                        $_BAR[0]->appendMessage('Warning: transcript "' . htmlspecialchars($aLine['id'] . '" (' . $aLine['geneid'] . ', ' . $aLine['name']) . ') does not exist in the database. Currently, it is not possible to import transcripts into LOVD using this file format.<BR>', 'done');
                        break; // Continue to next line.
                    }
                    break;

                case 'Diseases':
////////////////////////////////////////////////////////////////////////////////
// How to properly tell checkFields() what we're doing?
// Make sure proper checks are done on edits, or for now ignore edits?
// How to make sure this user is allowed to do what he's trying to do???
// OMIM alread exists? "{{id_omim}}"
// "{{created_date}}"	"{{edited_by}}"	"{{edited_date}}"
                    if (!in_array($aLine['id'], $aSection['ids'])) {
                        $_BAR[0]->appendMessage('Warning: disease "' . htmlspecialchars($aLine['id'] . '" (' . $aLine['symbol'] . ', ' . $aLine['name']) . ') does not exist in the database. Currently, it is not possible to import diseases into LOVD using this file format.<BR>', 'done');
                        break; // Continue to next line.
                    }
                    break;








                default:
                    // Bug in LOVD. Section allowed, but no data verification programmed.
                    lovd_errorAdd('import', 'Error (' . $sCurrentSection . ', line ' . $nLine . '): undefined data verification. Please report this bug.');
                    break 2; // Exit parsing.
            }

            // Store line in array, we will run the inserts/updates after parsing the whole file.
            // FIXME; If the entry already exists, and we're not doing an update, we can just kill this.
            $aSection['data'][] = $aLine;
            $nDataTotal ++;

            $_BAR[0]->setProgress(($nLine/$nLines)*100);
            lovd_endLine();

            // If we have too many errors, quit here (note that some errors can still flood the page,
            // since they do a continue or break before reaching this part of the code).
            if (count($_ERROR['messages']) >= 50) {
                lovd_errorAdd('import', 'Too many errors, stopping file processing.');
                break;
            }
        }





        // Now we have everything parsed. If there were errors, we are stopping now.
        if (!lovd_error()) {
            foreach ($aParsed as $sCategory => $aCategory) {



break;
//   Keep system wide setting for new IDs or keep per record (+1: ID = 1, but is addition).
//   A very very important thing here is, that if the file is only partially imported, we can't easily re=import the file if we were adding stuff.
//     We simply wouldn't know anymore which temporary IDs are now in the database already. The checks need to be perfect.
// Then, check if we understand everything (genes exist, transcripts exist, diseases exist).
//   Somehow, the system must be able to in a later time parse diseases etc, but for now we should understand that the disease is not in the file and not in the system, so we don't understand everything.
//   Also, we must be sure to check if all the keys are unqiue, otherwise we will run into query errors.
// Then, load all new data.
// Then, verify and process all edits.
//   If we die here for some reason, we must be absolutely sure that we can repeat the same import...
/*******************************************************************************

// Needs to be curator for THIS gene.
if (!lovd_isCurator($_SESSION['currdb'])) {
    // NOTE that this does not unset certain links in the top menu. Links are available.
    require ROOT_PATH . 'inc-top.php';
    lovd_showInfoTable((GENE_COUNT? 'You are not allowed access to ' . (GENE_COUNT > 1? 'this gene database' : 'the installed gene database') . '. Please contact your manager or the administrator to grant you access.' : 'There are currently no databases installed.'), 'stop');
    require ROOT_PATH . 'inc-bot.php';
    exit;
}







        // Mandatory cols missing?
        // Directly accessing $aColList, since the checkMandatory is not going to help me here.
        foreach ($_CURRDB->aColList as $sCol => $aCol) {
            if ($aCol['mandatory'] && !in_array($sCol, $aColumns)) {
                // Mandatory column is missing from the file.
                lovd_errorAdd('Bluntly refusing to import this file, as it lacks the mandatory "' . $sCol . '" (' . $aCol['head_column'] . ') column.');
            }
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

            // 2008-02-12; 2.0-04
            // Fixed memory consumption problem of import script occuring in large databases when loading all variant data into the memory.
            // Full variant data will be loaded later on in this script, only when needed, one by one.
            // $qVar = mysql_query('SELECT * FROM ' . TABLE_CURRDB_VARS);
            $qVar = mysql_query('SELECT variantid FROM ' . TABLE_CURRDB_VARS);
            while ($z = mysql_fetch_assoc($qVar)) {
                $z['indb'] = true;
                $aVariants[(int) $z['variantid']] = $z;
            }

            // 2007-06-14; 2.0-beta-04
            // Fixed problem when importing new entries when already having entries in other gene databases.
            // $qPat = mysql_query('SELECT p.* FROM ' . TABLE_PATIENTS . ' AS p LEFT JOIN ' . TABLE_PAT2VAR . ' AS p2v USING (patientid) WHERE p2v.symbol = "' . $_SESSION['currdb'] . '"');
            // 2007-09-21; 2.0-beta-09
            // Fixed memory consumption problem of import script occuring in large databases when loading all patient data into the memory.
            // Full patient data will be loaded later on in this script, only when needed, one by one.
            // $qPat = mysql_query('SELECT * FROM ' . TABLE_PATIENTS);
            $qPat = mysql_query('SELECT patientid FROM ' . TABLE_PATIENTS);
            while ($z = mysql_fetch_assoc($qPat)) {
                $z['indb'] = true;
                $aPatients[(int) $z['patientid']] = $z;
            }

            // 2008-02-12; 2.0-04
            // Fixed memory consumption problem of import script occuring in large databases when loading all patient2variant data into the memory.
            // Full patient2variant data will be loaded later on in this script, only when needed, one by one.
            // $qPat2Var = mysql_query('SELECT * FROM ' . TABLE_PAT2VAR . ' WHERE symbol = "' . $_SESSION['currdb'] . '"');
            $qPat2Var = mysql_query('SELECT variantid, patientid, allele FROM ' . TABLE_PAT2VAR . ' WHERE symbol = "' . $_SESSION['currdb'] . '"');
            while ($z = mysql_fetch_assoc($qPat2Var)) {
                $z['indb'] = true;
                $aPat2Var[(int) $z['variantid'] . '|' . (int) $z['patientid'] . '|' . $z['allele']] = $z;
            }

            // 2008-02-29; 2.0-04; Changed the whole pathogenicity ID code list.
            $aTransformPathogenicity = array('0' => '1', '1' => '9', '9' => '5');

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

                // Clean first.
                foreach ($aLine as $nKey => $sVal) {
                    // 2009-07-14; 2.0-20; XSS attack prevention.
                    if (preg_match('/<.*>/', $sVal)) {
                        lovd_errorAdd('Disallowed tag found in column "' . $aColumns[$nKey] . '", on line ' . $nLine . '.');
                    }

                    // 2010-04-13; 2.0-26; Fix problem with getting quoted data in the database, by unquoting what we have here.
                    // Data from text files is not actually quoted, but when downloading data, LOVD quotes the data.
                    // LOVD was quoting database contents further on in this file to compare it with the imported data; of course this is removed.
                    // Since we're using mysql_real_escape_string() later, unquote the data from the text file!
                    // 2011-09-21; 2.0-33; But this breaks \r, \n, and \t codes in the data!
                    $sVal = str_replace(array('\r', '\n', '\t'), array('\\\r', '\\\n', '\\\t'), $sVal);
                    $aLine[$nKey] = stripslashes($sVal);
                }

                foreach ($aLine as $nKey => $sVal) {
                    // Loop data, and verify it.
                    if (!isset($aColumns[$nKey])) {
                        // We're ignoring this column.
                        continue;
                    }
                    $sCol = $aColumns[$nKey];

                    // Check given ID's.
                    switch ($sCol) {
                        case 'variant_created_date_':
                            if ($sVal && !preg_match('/^[0-9]{4}[.\/-][0-9]{2}[.\/-][0-9]{2}( [0-9]{2}\:[0-9]{2}(\:[0-9]{2})?)?$/', $sVal)) {
                                lovd_errorAdd('Error in line ' . $nLine . ': value "' . htmlspecialchars($sVal) . '" in "' . $sCol . '" column is not a date I understand.');
                            }
                            $aLinePat2Var['created_date'] = $sVal;
                            break;
                        case 'patient_created_date_':
                            if ($sVal && !preg_match('/^[0-9]{4}[.\/-][0-9]{2}[.\/-][0-9]{2}( [0-9]{2}\:[0-9]{2}(\:[0-9]{2})?)?$/', $sVal)) {
                                lovd_errorAdd('Error in line ' . $nLine . ': value "' . htmlspecialchars($sVal) . '" in "' . $sCol . '" column is not a date I understand.');
                            }
                            $aLinePat['created_date'] = $sVal;
                            break;
                        case 'variant_edited_date_':
                            // FIXME; check for edited id?
                            if ($sVal && !preg_match('/^[0-9]{4}[.\/-][0-9]{2}[.\/-][0-9]{2}( [0-9]{2}\:[0-9]{2}(\:[0-9]{2})?)?$/', $sVal)) {
                                lovd_errorAdd('Error in line ' . $nLine . ': value "' . htmlspecialchars($sVal) . '" in "' . $sCol . '" column is not a date I understand.');
                            }
                            $aLinePat2Var['edited_date'] = $sVal;
                            break;
                        case 'patient_edited_date_':
                            // FIXME; check for edited id?
                            if ($sVal && !preg_match('/^[0-9]{4}[.\/-][0-9]{2}[.\/-][0-9]{2}( [0-9]{2}\:[0-9]{2}(\:[0-9]{2})?)?$/', $sVal)) {
                                lovd_errorAdd('Error in line ' . $nLine . ': value "' . htmlspecialchars($sVal) . '" in "' . $sCol . '" column is not a date I understand.');
                            }
                            $aLinePat['edited_date'] = $sVal;
                            break;
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
                        case 'ID_status_':
                            if ($sVal !== '') {
                                if (!array_key_exists($sVal, $_SETT['var_status'])) {
                                    lovd_errorAdd('Error in line ' . $nLine . ': value "' . htmlspecialchars($sVal) . '" in "' . $sCol . '" column is not a valid status ID.');
                                }
                            }
                            $aLinePat2Var['status'] = $sVal;
                            break;
                        case 'ID_submitterid_':
                            settype($sVal, 'int');
                            if (!in_array($sVal, $aIDsSubs)) {
                                lovd_errorAdd('Error in line ' . $nLine . ': value "' . htmlspecialchars($sVal) . '" in "' . $sCol . '" column is not a valid submitter ID.');
                            }
                            $aLinePat['submitterid'] = $sVal;
                            break;
                        case 'ID_variant_created_by_':
                            settype($sVal, 'int');
                            if (!in_array($sVal, $aIDsUsers)) {
                                lovd_errorAdd('Error in line ' . $nLine . ': value "' . htmlspecialchars($sVal) . '" in "' . $sCol . '" column is not a valid user ID.');
                            }
                            $aLinePat2Var['created_by'] = $sVal;
                            break;
                        case 'ID_patient_created_by_':
                            settype($sVal, 'int');
                            if (!in_array($sVal, $aIDsUsers)) {
                                lovd_errorAdd('Error in line ' . $nLine . ': value "' . htmlspecialchars($sVal) . '" in "' . $sCol . '" column is not a valid user ID.');
                            }
                            $aLinePat['created_by'] = $sVal;
                            break;
                        case 'ID_variant_edited_by_':
                            // FIXME; check for edited date?
                            settype($sVal, 'int');
                            if ($sVal && !in_array($sVal, $aIDsUsers)) {
                                lovd_errorAdd('Error in line ' . $nLine . ': value "' . htmlspecialchars($sVal) . '" in "' . $sCol . '" column is not a valid user ID.');
                            }
                            $aLinePat2Var['edited_by'] = $sVal;
                        case 'ID_patient_edited_by_':
                            // FIXME; check for edited date?
                            settype($sVal, 'int');
                            if ($sVal && !in_array($sVal, $aIDsUsers)) {
                                lovd_errorAdd('Error in line ' . $nLine . ': value "' . htmlspecialchars($sVal) . '" in "' . $sCol . '" column is not a valid user ID.');
                            }
                            $aLinePat['edited_by'] = $sVal;
                            break;
                        case 'ID_variantid_':
                            // 2008-12-23; 2.0-15; adapted by Gerard to allow for empty ID_variantid_ fields
                            if ($sVal && (!preg_match('/^[0-9]+$/', $sVal) || $sVal < 1)) {
                                lovd_errorAdd('Error in line ' . $nLine . ': value "' . htmlspecialchars($sVal) . '" in "' . $sCol . '" column is not an integer.');
                            }
                            settype($sVal, 'int');
                            $aLineVar['variantid'] = $sVal;
                            $aLinePat2Var['variantid'] = $sVal;
                            $nVariantID = $sVal;
                            break;
                        case 'ID_patientid_':
                            if (!preg_match('/^[0-9]+$/', $sVal) || $sVal < 1) {
                                lovd_errorAdd('Error in line ' . $nLine . ': value "' . htmlspecialchars($sVal) . '" in "' . $sCol . '" column is not an integer.');
                            }
                            settype($sVal, 'int');
                            $aLinePat['patientid'] = $sVal;
                            $aLinePat2Var['patientid'] = $sVal;
                            $nPatientID = $sVal;
                            break;
                        default:
                            // Variant or Patient column. Additional checking.
                            // Directly accessing $aColList, since the $_CURRDB methods are not going to help me here.

                            // Mandatory fields.
                            if ($_CURRDB->aColList[$sCol]['mandatory'] && !$sVal) {
                                // Mandatory col not filled in. Did we see this line before? If so, just ignore. Empty values are allowed for repeated data.
                                // 2008-11-05; 2.0-14; && $sCol != 'Variant/DBID' added by Gerard. That column is allowed to be empty and will be generated
                                // 2009-08-27; 2.0-21; Variant fields are mandatory only if published.
                                if (substr($sCol, 0, 8) == 'Variant/' && $aLine[array_search('ID_status_', $aColumns)] >= STATUS_MARKED && $sCol != 'Variant/DBID' && !array_key_exists((int) $aLine[array_search('ID_variantid_', $aColumns)], $aVariants)) {
                                    lovd_errorAdd('Error in line ' . $nLine . ': missing value in mandatory "' . $sCol . '" column.');
                                }
                                // 2009-08-27; 2.0-21; Patient fields are always mandatory.
                                if (substr($sCol, 0, 8) == 'Patient/' && !array_key_exists((int) $aLine[array_search('ID_patientid_', $aColumns)], $aPatients)) {
                                    lovd_errorAdd('Error in line ' . $nLine . ': missing value in mandatory "' . $sCol . '" column.');
                                }
                            }

                            // Field lengths.
                            $nMaxLength = $_CURRDB->getFieldLength($sCol);
                            $nLength = strlen($sVal);
                            if ($nMaxLength < $nLength) {
                                lovd_errorAdd('Error in line ' . $nLine . ': value of ' . $nLength . ' characters is too long for the "' . $sCol . '" column, which allows ' . $nMaxLength . ' characters.');
                            }

                            if ($sVal) {
                                // Field types.
                                $sType = $_CURRDB->getFieldType($sCol);
                                switch ($sType) {
                                    case 'INT':
                                        if (!preg_match('/^[0-9]+$/', $sVal)) {
                                            lovd_errorAdd('Error in line ' . $nLine . ': value "' . htmlspecialchars($sVal) . '" in "' . $sCol . '" column is not an integer.');
                                        }
                                        break;
                                    case 'DEC':
                                        if (!is_numeric($sVal)) {
                                            lovd_errorAdd('Error in line ' . $nLine . ': value "' . htmlspecialchars($sVal) . '" in "' . $sCol . '" column is not numeric.');
                                        }
                                        break;
                                    case 'DATETIME':
                                        if (!preg_match('/^[0-9]{4}[.\/-][0-9]{2}[.\/-][0-9]{2}( [0-9]{2}\:[0-9]{2}\:[0-9]{2})?$/', $sVal)) {
                                            lovd_errorAdd('Error in line ' . $nLine . ': value "' . htmlspecialchars($sVal) . '" in "' . $sCol . '" column is not a date I understand.');
                                        }
                                        break;
                                    case 'DATE':
                                        if (!lovd_matchDate($sVal)) {
                                            lovd_errorAdd('Error in line ' . $nLine . ': value "' . htmlspecialchars($sVal) . '" in "' . $sCol . '" column is not a date I understand.');
                                        }
                                        break;
                                }

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

                // 2008-02-12; 2.0-04
                // Now load the variant, if necessary. This will keep the $aVariants array small.
                if (array_key_exists($nVariantID, $aVariants) && $aVariants[$nVariantID]['indb'] && !isset($aVariants[$nVariantID]['sort'])) {
                    // I could have picked any other standard Variant column basically.
                    // Variant data is in the database, but not fetched yet. Do this now.
                    $z = mysql_fetch_assoc(mysql_query('SELECT * FROM ' . TABLE_CURRDB_VARS . ' WHERE variantid = "' . $nVariantID . '"'));
                    // 2011-09-20; 2.0-33; Also tabs are replaced, to prevent problems while importing them (they should not be there but oh well).
                    $z = str_replace(array("\r", "\n", "\t"), array('\r', '\n', '\t'), $z);
                    $z['indb'] = true;
                    $aVariants[$nVariantID] = $z;
                }

                // 2007-09-21; 2.0-beta-09
                // Now load the patient, if necessary. This will keep the $aPatients array small.
                if (array_key_exists($nPatientID, $aPatients) && $aPatients[$nPatientID]['indb'] && !isset($aPatients[$nPatientID]['created_by'])) {
                    // I could have picked any other standard Patient column basically.
                    // Patient data is in the database, but not fetched yet. Do this now.
                    $z = mysql_fetch_assoc(mysql_query('SELECT * FROM ' . TABLE_PATIENTS . ' WHERE patientid = "' . $nPatientID . '"'));
                    // 2011-09-20; 2.0-33; Also tabs are replaced, to prevent problems while importing them (they should not be there but oh well).
                    $z = str_replace(array("\r", "\n", "\t"), array('\r', '\n', '\t'), $z);
                    $z['indb'] = true;
                    $aPatients[$nPatientID] = $z;
                }

                // 2008-02-12; 2.0-04
                // Now load the patient2variant entry, if necessary. This will keep the $aPat2Var array small.
                if (array_key_exists($sPat2VarKey, $aPat2Var) && $aPat2Var[$sPat2VarKey]['indb'] && !isset($aPat2Var[$sPat2VarKey]['created_by'])) {
                    // I could have picked any other standard Pat2Var column basically.
                    // Pat2Var data is in the database, but not fetched yet. Do this now.
                    $z = mysql_fetch_assoc(mysql_query('SELECT * FROM ' . TABLE_PAT2VAR . ' WHERE symbol = "' . $_SESSION['currdb'] . '" AND variantid = "' . $nVariantID . '" AND patientid = "' . $nPatientID . '" AND allele = "' . $aLinePat2Var['allele'] . '"'));
                    // 2011-09-20; 2.0-33; Also tabs are replaced, to prevent problems while importing them (they should not be there but oh well).
                    $z = str_replace(array("\r", "\n", "\t"), array('\r', '\n', '\t'), $z);
                    $z['indb'] = true;
                    $aPat2Var[$sPat2VarKey] = $z;
                }

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
                // 2009-03-18; 2.0-18; Tell the user which columns have been ignored.
                if ($aIgnoredColumns) {
                    // 2009-07-14; 2.0-20; Added htmlspecialchars() to prevent XSS attack through column names.
                    // 2009-08-27; 2.0-21; Funny, that just turned <BR> into &lt;BR&gt;. Fixed that.
                    print('      The following columns have been ignored, because they are not in the database:<BR>' . "\n" . nl2br(htmlspecialchars(implode($aIgnoredColumns, "\n"))) . "\n\n");
                }
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
}





$_T->printHeader();
$_T->printTitle('Import data in LOVD format');

print('      Using this form you can import files in LOVD\'s tab-delimited format. Currently supported imports are individual, phenotype, screening and variant data.<BR><I>Genomic positions in your data are assumed to be relative to Human Genome build ' . $_CONF['refseq_build'] . '</I>.<BR>' . "\n" .
      '      <BR>' . "\n\n");

// FIXME: Since we can increase the memory limit anyways, maybe we can leave this message out if we nicely handle the memory?
lovd_showInfoTable('In some cases importing big files can cause LOVD to run out of available memory. In case these errors are hidden, LOVD would return a blank screen. If this happens, split your import file into smaller chunks or ask your system administrator to allow PHP to use more memory (currently allowed: ' . ini_get('memory_limit') . 'B).<BR><A href="http://www.lovd.nl/bugs/?do=details&id=17" target="_blank">More information</A>.', 'warning', 760);

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
            '<B>' . $aModes['insert'] . '</B>: LOVD will use the IDs given in the file only to link the data together. All data in the file will be treated as new, and all data will receive new IDs once imported. The biggest advantage of this mode is that you do not need to know which IDs are free.', 'select', 'mode', 1, $aModes, false, false, false),
        array('', '', 'note', 'Please select which import mode LOVD should use; <I>' . implode('</I> or <I>', $aModes) . '</I>. For more information on the modes, move your mouse over the ? icon.'),
        array('Character encoding of imported file', 'If your file contains special characters like &egrave;, &ouml; or even just fancy quotes like &ldquo; or &rdquo;, LOVD needs to know the file\'s character encoding to ensure the correct display of the data.', 'select', 'charset', 1, $aCharSets, false, false, false),
        array('', '', 'note', 'Please only change this setting in case you encounter problems with displaying special characters in imported data. Technical information about character encoding can be found <A href="http://en.wikipedia.org/wiki/Character_encoding" target="_blank">on Wikipedia</A>.'),
        'skip',
        array('', '', 'submit', 'Import file'));

lovd_viewForm($aForm);

print('</FORM>' . "\n\n");

$_T->printFooter();
?>
