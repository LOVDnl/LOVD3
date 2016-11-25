<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-10-04
 * Modified    : 2016-11-25
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : M. Kroon <m.kroon@lumc.nl>
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

define('ROOT_PATH', '../');
require_once ROOT_PATH . 'inc-init.php';
require_once ROOT_PATH . 'inc-lib-form.php';
require_once ROOT_PATH . 'class/object_transcripts.php';
require_once ROOT_PATH . 'class/progress_bar.php';
require_once ROOT_PATH . 'class/soap_client.php';

// Links between LOVD2-LOVD3 fields, with optional conversion function. Format:
// LOVD2_field => array(LOVD3_section, LOVD3_field, Conversion_function)
// Where LOVD2_field is an LOVD2 field name as it occurs in the input file,
// LOVD3_section is a output section name as defined as key in the
// $aImportSections variable, LOVD3_field is an LOVD3 field name and
// Conversion_function is an optional name of a function taking a LOVD2 field
// value as a string as argument and returning LOVD3 field value as a string.
// TODO: add restricitonSite
$aFieldLinks = array(
    'Variant/DNA' =>                    array('vot',        'VariantOnTranscript/DNA'),
    'Variant/RNA' =>                    array('vot',        'VariantOnTranscript/RNA'),
    'Variant/Protein' =>                array('vot',        'VariantOnTranscript/Protein'),
    'Variant/DBID' =>                   array('vog',        'VariantOnGenome/DBID',         'lovd_convertDBID'),
    'Variant/Detection/Template' =>     array('scrrening',  'Screening/Template'),
    'Variant/Detection/Technique' =>    array('screening',  'Screening/Technique'),
    'Variant/Exon' =>                   array('vot',        'VariantOnTranscript/Exon'),
    'Patient/Patient_ID' =>             array('individual', 'Individual/Lab_ID',            'lovd_convertLab_ID'),
    'Patient/Reference' =>              array('individual', 'Individual/Reference',         'lovd_convertReference'),
    'Patient/Times_Reported' =>         array('individual', 'panel_size'),
    'Patient/Phenotype_2' =>            array('phenotype',  'Phenotype/Additional'),
    'Patient/Occurrence' =>             array('phenotype',  'Phenotype/Inheritance'),
    'Patient/Mutation/Origin' =>        array('vog',        'VariantOnGenome/Genetic_origin'),
    'ID_pathogenic_' =>                 array('vot',        'effectid'),
    'ID_status_' =>                     array('vog',        'statusid'),
    'ID_variant_created_by_' =>         array('vog',        'created_by',                   'lovd_convertUserID'),
    'variant_created_date_' =>          array('vog',        'created_date'),
    'ID_variant_edited_by_' =>          array('vog',        'edited_by',                    'lovd_convertUserID'),
    'variant_edited_date_' =>           array('vog',        'edited_date'),
    'ID_patient_created_by_' =>         array('individual', 'created_by',                   'lovd_convertUserID'),
    'patient_created_date_' =>          array('individual', 'created_date'),
    'ID_patient_edited_by_' =>          array('individual', 'edited_by',                    'lovd_convertUserID'),
    'patient_edited_date_' =>           array('individual', 'edited_date'),
    'ID_patientid_' =>                  array('individual', 'id',                           'lovd_autoIncIndividualID'),
    'ID_variantid_' =>                  array('vog',        'id',                           'lovd_autoIncVariantID'),
    'ID_allele_' =>                     array('vog',        'allele'),
);


// Defaults for prefixed custom column fields not mentioned in $aFieldLinks.
// (e.g. 'Patient' => array('individual', 'Individual') will cause field
// 'Patient/Origin/Population' to be linked to 'Individual/Origin/Population'
// in the individual section). Note that the prefixes higher up in the array
// will be preferred, so be careful to place more generic prefixes at the
// bottom.
$aCustomColLinks = array(
    'Variant' =>        array('vot', 'VariantOnTranscript'),
    'Patient/Phenotype' =>  array('phenotype', 'Phenotype'),
    'Patient' =>        array('individual', 'Individual')
);


// Output section information describing the LOVD3 import format. Each section
// is defined by a key and one or more settings, where only the 'output_header'
// setting is mandatory. The following settings are available:
// output_header:       Title of section in output.
// customcol_prefix:    Prefix for custom columns in this section.
// mandatory_fields:    Array of mandatory fields as keys, and default values
//                      as values.
// table:               Database table corresponding to the section.
$aImportSections = array(
    'column' =>     array(
        'output_header' =>          'Columns'),
    'gene' =>       array(
        'output_header' =>          'Genes'),
    'transcript' => array(
        'output_header' =>          'Transcripts'),
    'disease' =>    array(
        'output_header' =>          'Diseases',
        'mandatory_fields' =>       array('id' => '0', 'name' => '-', 'symbol' => '-')),
    'g2d' =>        array(
        'output_header' =>          'Genes_To_Diseases'),
    'individual' => array(
        'output_header' =>          'Individuals',
        'customcol_prefix' =>       'Individual',
        'mandatory_fields' =>       array('id' => '0', 'panel_size' => '1')),
    'i2d' =>        array(
        'output_header' =>          'Individuals_To_Diseases',
        'mandatory_fields' =>       array('individualid' => '0', 'diseaseid' => '0')),
    'phenotype' =>  array(
        'output_header' =>          'Phenotypes',
        'customcol_prefix' =>       'Phenotype',
        'mandatory_fields' =>       array('id' => '0', 'diseaseid' => '0', 'individualid' => '0')),
    'screening' =>  array(
        'output_header' =>          'Screenings',
        'customcol_prefix' =>       'Screening',
        'mandatory_fields' =>       array('id' => '0', 'individualid' => '0',
            'Screening/Template' => '?', 'Screening/Technique' => '?')),
    's2g' =>        array(
        'output_header' =>          'Screenings_To_Genes',
        'mandatory_fields' =>       array('screeningid' => '0', 'geneid' => '0')),
    'vog' =>        array(
        'output_header' =>          'Variants_On_Genome',
        'customcol_prefix' =>       'VariantOnGenome',
        'mandatory_fields' =>       array('id' => '0', 'allele' => '0', 'chromosome' => '0',
            'position_g_start' => '0', 'position_g_end' => '0', 'type' => '?',
            'VariantOnGenome/DNA' => 'g.?')),
    'vot' =>        array(
        'output_header' =>          'Variants_On_Transcripts',
        'customcol_prefix' =>       'VariantOnTranscript',
        'mandatory_fields' =>       array('id' => '0', 'transcriptid' => '0',
            'position_c_start' => '0', 'position_c_start_intron' => '0', 'position_c_end' => '0',
            'position_c_end_intron' => '0', 'VariantOnTranscript/Exon' => '?',
            'VariantOnTranscript/RNA' => 'r.?')),
    's2v' =>        array(
        'output_header' =>          'Screenings_To_Variants',
        'mandatory_fields' =>       array('screeningid' => '0', 'variantid' => '0')),
);


// Default user ID with which to overwrite user IDs in the input file. Used by
// lovd_convertUserID().
$sFixedUserID = null;

// Translation array of LOVD2 user IDs to LOVD3 user IDs. Used by
// lovd_convertUserID().
$aUserTranslationTable = array();



function lovd_autoIncIndividualID ($_LOVD2PatientID)
{
    // ID generator for individuals.
    return lovd_getInc('lovd_autoIncIndividualID');
}





function lovd_autoIncPhenotypeID ()
{
    // ID generator for phenotypes.
    return lovd_getInc('lovd_autoIncPhenotypeID');
}






function lovd_autoIncVariantID ($_LOVD2PatientID)
{
    // ID generator for variants.
    return lovd_getInc('lovd_autoIncVariantID');
}





function lovd_autoIncScreeningID ()
{
    // ID generator for screenings.
    return lovd_getInc('lovd_autoIncScreeningID');
}





function lovd_callJSONService ($sURL)
{
    // Call $sURL using lovd_php_file() and return the decoded JSON output.
    $sResponse = @join('', lovd_php_file($sURL));
    if ($sResponse) {
        return json_decode($sResponse);
    }
    return false;
}




function lovd_convertDBID ($LOVD2DBID)
{
    // Returns an LOVD3-formatted DBID for the given $LOVD2DBID by padding
    // the number with an extra '0'.
    // TODO: fix for extra underscores in DBID, and if $snumber is not a number, leave it as is.
    list($sGene, $sNumber) = explode('_', $LOVD2DBID, 2);
    if (ctype_digit($sNumber)) {
        return $sGene . '_0' . $sNumber;
    }
    return ;
}





function lovd_convertLab_ID ($LOVD2Lab_ID)
{
    // Returns the last 15 characters of the given lab ID. LOVD3 max length
    // for Lab_ID is 15. We assume the identifying part of the ID is at the end
    // of the value, which we aim to preserve.
    // TODO: remove this, let import fail if too long
    return substr($LOVD2Lab_ID, -15);
}





function lovd_convertReference ($LOVD2Reference)
{
    // Convert LOVD2-style reference to LOVD3-style. E.g.:
    // {PMID21228398:Bell 2011} => {PMID:Bell 2011:21228398}
    return preg_replace('/{PMID(\d+):(\w+)}/', '{PMID:\\2:\\1}', $LOVD2Reference);
}





function lovd_convertUserID ($LOVD2UserID)
{
    // Returns user ID for given LOVD2 user ID. Return value is based on
    // settings for fixed (default) user ID and ID translation table, both
    // are defined in the upload form.
    global $sFixedUserID, $aUserTranslationTable;

    $LOVD2UserIDClean= lovd_trim($LOVD2UserID);
    if (isset($aUserTranslationTable[$LOVD2UserIDClean])) {
        // Found match in translation table.
        return $aUserTranslationTable[$LOVD2UserIDClean];
    }
    if (!is_null($sFixedUserID)) {
        // Default to fixed user ID.
        return $sFixedUserID;
    }
    // Last resort is to return the original ID.
    return $LOVD2UserID;
}





function lovd_empty ($value)
{
    // Returns true if given LOVD2 input field value ($value) is considered
    // empty (i.e. an empty string, null or a string with just opening and
    // closing quotation marks.
    return is_null($value) || $value === '' || $value === '""' || $value === "''";
}






function lovd_getInc ($sCounterName='default')
{
    // Static automatic incrementor. Returns incrementing integers accross
    // consecutive function calls (starting at 1). $sCounterName allows one to
    // use multiple incrementors simultaneously.
    static $aCounters;
    if (!isset($aCounters)) {
        $aCounters = array();
    }
    if (!isset($aCounters[$sCounterName])) {
        $aCounters[$sCounterName] = 1;
    } else {
        $aCounters[$sCounterName]++;
    }
    return $aCounters[$sCounterName];
}




function lovd_getRecordForHeaders ($aOutputHeaders, $aRecord, $aSection=null)
{
    // Given output headers $aOutputHeaders with integer keys linked to fields
    // in the input record $aRecord, generate an array with the fields filled
    // with values for the corresponding links. E.g. given $aOutputHeaders =
    // array(0 => 'field1', 1 => 'field2', 'dummy' => 'field3') and $aRecord =
    // array('v1', 'v2'), this function would return array('field1' => 'v1',
    // field2 => 'v2', 'dummy' => null).
    $aNewRecord = array();
    foreach ($aOutputHeaders as $nInputIdx => $sHeader) {
        if (is_int($nInputIdx) || ctype_digit($nInputIdx)) {
            // Numeric key defines link to field in input record $aRecord.
            $aNewRecord[$sHeader] = $aRecord[$nInputIdx];
        } else {
            // Leave non-linked fields empty for now. These are probably
            // mandatory fields not provided directly in the input.
            $aNewRecord[$sHeader] = null;
        }
        if (isset($aSection['mandatory_fields']) &&
            array_key_exists($sHeader, $aSection['mandatory_fields']) &&
            lovd_empty($aNewRecord[$sHeader])) {
            // Set default value for mandatory field.
            $aNewRecord[$sHeader] = $aSection['mandatory_fields'][$sHeader];
        }
    }
    return $aNewRecord;
}





function lovd_getHeaders ($aData, $aFieldLinks, $aSections, $aCustomColLinks)
{
    // Returns three arrays, the first two are headers for the input and output
    // records respectively, the third array contains warning messages. The
    // first array contains the names as defined in the header of the input
    // file. The second array contains per section field names for the output
    // file (the LOVD3 import format).
    // Returns false for both header arrays if header cannot be either found or
    // parsed.

    global $_DB;

    if (!is_array($aData)) {
        lovd_errorAdd('LOVD2_export', 'Invalid input.');
        return array(false, false, array());
    }

    $aWarnings = array();

    // Walk through lines until header is found.
    foreach ($aData as $i => $sLine) {
        $sLine = trim($sLine);
        if (empty($sLine) || $sLine[0] == "#") {
            // Ignore blank lines and comments.
            continue;
        }

        $matches = array();
        preg_match_all('/"{{\s*([^ }]+)\s*}}"/', $sLine, $matches);

        if (empty($matches[0]) || empty($matches[1])) {
            // Cannot find header in first non-empty, non-comment line in file. Show an error.
            break;
        }

        // Initialize output array and get field names from database per
        // section.
        $aOutputHeaders = array();
        foreach ($aSections as $sSection => $aImportSection) {
            if (isset($aImportSection['customcol_prefix']) &&
                ($aTable = lovd_getTableInfoByCategory($aImportSection['customcol_prefix']))
                !== false) {
                $aSections[$sSection]['db_fields'] =
                    $_DB->query('DESCRIBE ' . $aTable['table_sql'])->fetchAllColumn();
            } else {
                $aSections[$sSection]['db_fields'] = array();
            }
            $aOutputHeaders[$sSection] = array();
        }

        // Loop over input headers and link them to output headers, such tat
        // $aOutputHeaders[section][i] = outHeader, where section is the output
        // section as defined in $aImportSections, i is the index of the input
        // header and outHeader is the name of the column in the output.
        for ($i = 0; $i < count($matches[1]); $i++) {

            $aSectionIDs = array_keys($aSections);

            // Check if field is manually linked in $aFieldLinks.
            $sHeader = $matches[1][$i];
            if (isset($aFieldLinks[$sHeader])) {
                // Use output header linked in $aFieldLinks.
                list($sSection, $sHeaderOut) = $aFieldLinks[$sHeader];
                $aOutputHeaders[$sSection][$i] = $sHeaderOut;
                continue;
            }

            // Check if header occurs as a literal DB field.
            foreach ($aSectionIDs as $sSection) {
                if (isset($aSections[$sSection]['db_fields'])  &&
                    array_search($sHeader, $aSections[$sSection]['db_fields']) !== false) {
                    $aOutputHeaders[$sSection][$i] = $sHeader;
                    continue 2;
                }
            }

            // Try to link custom columns.
            if (strpos($sHeader, '/') !== false) {
                list(, $sFieldname) = explode('/', $sHeader, 2);
                foreach ($aSectionIDs as $sSection) {
                    $aSection = $aSections[$sSection];
                    if (isset($aSection['customcol_prefix']) &&
                        array_search($sFieldname, $aSection['db_fields']) !== false) {
                        // Set output header to with new LOVD3 prefix (e.g. Individual).
                        $aOutputHeaders[$sSection][$i] = $aSection['customcol_prefix'] . '/' .
                            $sFieldname;
                        continue 2;
                    }
                }

                // Try to find default custom column translation.
                foreach ($aCustomColLinks as $sPrefix => $aCustomColDefault) {
                    if (strpos($sHeader, $sPrefix) === 0) {
                        list($sSection, $sPrefixOut) = $aCustomColDefault;
                        $sHeaderOut = str_replace($sPrefix, $sPrefixOut, $sHeader);
                        $aOutputHeaders[$sSection][$i] = $sHeaderOut;
                        $aWarnings[] = 'Warning: linked "' . $sHeader . '" to "' . $sHeaderOut . '"';
                        continue 2;
                    }
                }
            }

            // Could not link input header intelligently.
            $aWarnings[] = 'Warning: could not link field "' . $sHeader . '"';
        }

        // Add mandatory fields and sort everything.
        foreach ($aSections as $sSection => $aImportSection) {
            if (isset($aImportSection['mandatory_fields'])) {
                $aMandatory = array_diff(array_keys($aImportSection['mandatory_fields']),
                    $aOutputHeaders[$sSection]);
                $aMandatory = array_combine($aMandatory, $aMandatory);
                $aOutputHeaders[$sSection] += $aMandatory;
            }

            // Sort alphabetically, but set 'id' (if present) as first header.
            uasort($aOutputHeaders[$sSection], function ($a, $b) {
                if ($a == 'id') {
                    return -1;
                } elseif ($b == 'id') {
                    return 1;
                }
                return strcasecmp($a, $b);
            });
        }

        return array($matches[1], $aOutputHeaders, $aWarnings);
    }

    lovd_errorAdd('LOVD2_export', 'Cannot find header in file.');
    return array(false, false, array());
}





function lovd_getSectionOutput($aImportSection, $aOutputHeaders, $aRecords)
{
    // Generate LOVD3 import data format from converted LOVD2 records.

    $sOutput = "\n" . '## ' . $aImportSection['output_header'];
    $sOutput .= ' ## Do not remove or alter this header ##' . "\n";

    $aSectionHeaders = array();
    foreach ($aOutputHeaders as $sHeader) {
        // Add header to output without potential section prefix (e.g. 'vog:').
        $aSectionHeaders[] = '{{' . preg_replace('/^[^:]*:/', '', $sHeader) . '}}';
    }
    $sOutput .= join("\t", $aSectionHeaders) . "\n";

    foreach ($aRecords as $aRecord) {
        // Put record fields in same order as headers.
        $aOutRecord = array();
        foreach ($aOutputHeaders as $sHeader) {
            $aOutRecord[] = $aRecord[$sHeader];
        }
        $sOutput .= join("\t", $aOutRecord) . "\n";
    }
    $sOutput .= "\n";
    return $sOutput;
}





function lovd_getDiseaseID ($sDiseaseName)
{
    // Get the ID from the database searching the name and synmbol fields for
    // given disease $sDiseaseName. If it is not present in the database,
    // generate and return an automatic incrementing ID. Displays an error if
    // there are multiple hits in the database.
    // Returns array with disease ID (or false if an error occurred) and a
    // boolean flag stating whether a new disease record for this ID should be
    // created. Returns false if multiple matching diseases are found in the
    // DB.
    global $_DB;
    static $aKnownDiseases;

    $bNewDisease = false;
    $sDiseaseNameClean = lovd_trim($sDiseaseName);
    if (!isset($aKnownDiseases[$sDiseaseNameClean])) {
        $zDiseases = $_DB->query('SELECT id FROM ' . TABLE_DISEASES . ' WHERE name=? OR symbol=?',
            array($sDiseaseNameClean, $sDiseaseNameClean));
        $aDiseases = $zDiseases->fetchAllAssoc();
        if (count($aDiseases) < 1) {
            // Not in database: create new unique disease ID.
            $aKnownDiseases[$sDiseaseNameClean] = lovd_getInc('Diseases');
            $bNewDisease = true;
        } else if(count($aDiseases) > 1) {
            // Multiple hits in database.
            lovd_errorAdd('LOVD2_export', 'Error: disease name "' . $sDiseaseNameClean .
                '" ambiguous, it matches more than one disease in the database.');
            return array(false, false);
        } else {
            // Exactly one hit in database.
            $aKnownDiseases[$sDiseaseNameClean] = $aDiseases[0]['id'];
        }
    }
    return array($aKnownDiseases[$sDiseaseNameClean], $bNewDisease);
}





function lovd_log ($sMessage)
{
    print($sMessage . "\n");
}





function lovd_openLOVD2ExportFile($aRequest, $aFiles)
{
    // Returns an array with the contents of the uploaded LOVD2 export file,
    // returns false when something went wrong with opening or decoding the
    // file.

    // Find out the MIME-type of the uploaded file. Sometimes
    // mime_content_type() seems to return False. Don't stop processing if
    // that happens.
    // However, when it does report something different, mention what type was
    // found so we can debug it.
    $sType = '';
    if (function_exists('mime_content_type')) {
        $sType = mime_content_type($aFiles['LOVD2_export']['tmp_name']);
    }
    if ($sType && substr($sType, 0, 5) != 'text/') {
        // Not all systems report the regular files as "text/plain"; also
        // reported was "text/x-pascal; charset=us-ascii".
        lovd_errorAdd('LOVD2_export', 'The upload file is not a tab-delimited text file and cannot be ' .
            'imported. It seems to be of type "' . htmlspecialchars($sType) . '".');

    } else {
        $fInput = @fopen($aFiles['LOVD2_export']['tmp_name'], 'r');
        if (!$fInput) {
            lovd_errorAdd('LOVD2_export', 'Cannot open file after it was received by the server.');
        } else {
            // Check mode, we take no default if we don't understand the answer.
            if (empty($aRequest['mode']) || !isset($aModes[$aRequest['mode']])) {
                lovd_errorAdd('mode', 'Please select the import mode from the list of options.');
            }

            // Open the file using file() to check the line endings, then check the encodings, try
            // to use as little memory as possible.
            // Reading the entire file in memory, because we need to detect the encoding and
            // possibly convert.
            $aData = lovd_php_file($aFiles['LOVD2_export']['tmp_name']);

            // Fix encoding problems.
            if ($aRequest['charset'] == 'auto' || !isset($aCharSets[$aRequest['charset']])) {
                // Auto detect charset, it's not given.
                // FIXME; Should we ever allow more encodings?
                $sEncoding = mb_detect_encoding(implode("\n", $aData), array('UTF-8', 'ISO-8859-1'), true);
                if (!$sEncoding) {
                    // Could not be detected.
                    lovd_errorAdd('charset', 'Could not autodetect the file\'s character ' .
                        'encoding. Please select the character encoding from from the list of ' .
                        'options.');
                } elseif ($sEncoding != 'UTF-8') {
                    // Is not UTF-8, and for sure has special chars.
                    return utf8_encode_array($aData);
                }
            } elseif ($aRequest['charset'] == 'ISO-8859-1') {
                return utf8_encode_array($aData);
            }
            return $aData;
        }
    }

    return false;
}





function lovd_parseData ($aData, $zTranscript, $aFieldLinks, $aInputHeaders, $aOutputHeaders,
                         $aSections, $oProgressBar)
{
    // Parse contents of input file. Return output data per section.

    global $_SETT, $_CONF;
    // Free up the session for other requests when parsing the input file.
    session_write_close();
    @set_time_limit(0);

    $nNumLines = count($aData);

    // Arrays for storing converted data per output section.
    $aVOGRecords = array();
    $aVOTRecords = array();
    $aDiseases = array();
    $aIndividuals = array();
    $aIndividuals2Diseases = array();
    $aScreenings = array();
    $aScreening2Genes = array();
    $aScreening2Variants = array();
    $aPhenotypes = array();

    // Array for storing unique variant_id/patient_id combinations as key. This
    // is used to find homozygous variants as they will occur twice in the
    // LOVD2 export file with identical variant_id/patient_id.
    $aUniqueRecords = array();

    $nCounter = 0;
    foreach ($aData as $i => $sLine) {
        // Set progress bar (leave 1 percent for output generation).
        $oProgressBar->setProgress((++$nCounter / $nNumLines) * 99);
        $oProgressBar->setMessage('Converting record ' . strval($nCounter) . ' of ' .
            strval($nNumLines) . '...');

        $sLine = trim($sLine);
        if (empty($sLine) || $sLine[0] == "#" || substr($sLine, 0, 3) == '"{{') {
            // Ignore blank lines, comments and the header line.
            continue;
        }

        // Loop over fields in record and convert values according to $aFieldLinks.
        $aInputRecord = explode("\t", $sLine);
        $aRecord = array();
        for ($i = 0; $i < count($aInputRecord); $i++) {
            $sFieldName = $aInputHeaders[$i];
            $sFieldValue = $aInputRecord[$i];
            if (isset($aFieldLinks[$sFieldName])) {
                if (count($aFieldLinks[$sFieldName]) == 2) {
                    $aRecord[] = $sFieldValue;
                } else {
                    $aRecord[] = call_user_func($aFieldLinks[$sFieldName][2], $sFieldValue);
                }
            } else {
                // Copy field as is.
                $aRecord[] = $sFieldValue;
            }
        }

        // Handle multiple observations for single variant in one patient
        // (homozygous).
        $sRecordID = $aInputRecord[array_search('ID_variantid_', $aInputHeaders)] . '_' .
                     $aInputRecord[array_search('ID_patientid_', $aInputHeaders)];
        if (array_key_exists($sRecordID, $aUniqueRecords)) {
            // Combination variant_id/patient_id already seen, set previous
            // record allele field to 3 (homozygous). Skip this record.
            $aVOGRecords[$sRecordID]['allele'] = 3;
            continue;
        } else {
            // Store current variant/patient combination for future reference.
            $aUniqueRecords[$sRecordID] = null;
        }

        // Create new disease if necessary.
        $sDiseaseInputHeader = 'Patient/Phenotype/Disease';
        $sDiseaseID = null;
        if (($i = array_search($sDiseaseInputHeader, $aInputHeaders)) !== false) {
            list($sDiseaseID, $bCreateNewDisease) = lovd_getDiseaseID($aRecord[$i]);
            if ($sDiseaseID === false) {
                // Something went wrong determining the disease, stop parsing.
                break;
            }
            if ($bCreateNewDisease) {
                // New disease, create an output record for it.
                $aDiseases[$sDiseaseID] = array('id' => $sDiseaseID, 'name' => $aRecord[$i],
                    'symbol' => null);
            }
        }

        // Handle individual-specific data (individual, screening, phenotype, etc.).
        $sIndividualInputHeader = 'ID_patientid_';
        if (($i = array_search($sIndividualInputHeader, $aInputHeaders)) !== false) {
            $sLOVD2IndividualID = $aInputRecord[$i];
            if (!isset($aIndividuals[$sLOVD2IndividualID])) {
                // New individual, create an output record for it.
                $aIndividual = lovd_getRecordForHeaders($aOutputHeaders['individual'], $aRecord,
                    $aSections['individual']);
                $aIndividuals[$sLOVD2IndividualID] = $aIndividual;

                // Create screening record.
                $aScreening = lovd_getRecordForHeaders($aOutputHeaders['screening'], $aRecord,
                    $aSections['screening']);
                $nScreeningID = lovd_autoIncScreeningID();
                $aScreening['id'] = $nScreeningID;
                $aScreening['individualid'] = $aIndividual['id'];
                $aScreenings[$sLOVD2IndividualID] = $aScreening;

                // Create screening2gene record.
                $aScreening2Genes[] = array('screeningid' => $nScreeningID,
                                            'geneid' => $zTranscript['geneid']);

                // Create phenotype record.
                $aPhenotype = lovd_getRecordForHeaders($aOutputHeaders['phenotype'], $aRecord,
                    $aSections['phenotype']);
                $aPhenotype['id'] = lovd_autoIncPhenotypeID();
                $aPhenotype['diseaseid'] = $sDiseaseID;
                $aPhenotype['individualid'] = $aIndividual['id'];
                $aPhenotypes[$sLOVD2IndividualID] = $aPhenotype;

                // Create individuals2diseases record.
                $aIndividuals2Disease = lovd_getRecordForHeaders($aOutputHeaders['i2d'], $aRecord);
                $aIndividuals2Disease['individualid'] = $aIndividual['id'];
                $aIndividuals2Disease['diseaseid'] = $sDiseaseID;
                $aIndividuals2Diseases[] = $aIndividuals2Disease;
            }

            // Create screening2variant record.
            $aScreening2Variants[] = array(
                'screeningid' => $aScreenings[$sLOVD2IndividualID]['id'],
                'variantid' => $aRecord[array_search('ID_variantid_', $aInputHeaders)]);
        }

        // Create VOG/VOT records
        $aVOGRecord = lovd_getRecordForHeaders($aOutputHeaders['vog'], $aRecord,
            $aSections['vog']);
        $aVOGRecord['chromosome'] = $zTranscript['chromosome'];

        $aVOTRecord = lovd_getRecordForHeaders($aOutputHeaders['vot'], $aRecord,
            $aSections['vot']);
        $aVOTRecord['id'] = $aVOGRecord['id'];
        $aVOTRecord['transcriptid'] = $zTranscript['id'];

        // Get positions on transcript/chromosome from mutalyzer for variant.
        $nHGVSIdx = array_search('Variant/DNA', $aInputHeaders);
        $sVariant = lovd_trim($aRecord[$nHGVSIdx]);
        $aMappingInfoArgs = array(
            'LOVD_ver' => $_SETT['system']['version'],
            'build' => 'hg19',
            'accNo' => $zTranscript['id_ncbi'],
            'variant' => $sVariant);
        $sMappingURL = str_replace('/services', '', $_CONF['mutalyzer_soap_url']);
        $sMappingURL .= '/json/mappingInfo?' . http_build_query($aMappingInfoArgs);
        $oResponse = lovd_callJSONService($sMappingURL);
        if ($oResponse && !isset($oResponse->errorcode) && !isset($oResponse->faultcode)) {
            $aVOGRecord['position_g_start'] =   min($oResponse->start_g, $oResponse->end_g);
            $aVOGRecord['position_g_end'] =     max($oResponse->start_g, $oResponse->end_g);
            $aVOGRecord['type'] =               $oResponse->mutationType;
            $aVOTRecord['position_c_start'] =   $oResponse->startmain;
            $aVOTRecord['position_c_start_intron'] =    $oResponse->startoffset;
            $aVOTRecord['position_c_end'] =             $oResponse->endmain;
            $aVOTRecord['position_c_end_intron'] =      $oResponse->endoffset;
        }

        // Call mutalyzer's numberConversion to get VariantOnGenome/DNA
        $aNumberConvArgs = array(
            'build' => $_CONF['refseq_build'],
            'gene' => $zTranscript['geneid'],
            'variant' => $zTranscript['id_ncbi'] . ':' . $sVariant);
        $sNumberConvURL = str_replace('/services', '', $_CONF['mutalyzer_soap_url']);
        $sNumberConvURL .= '/json/numberConversion?' . http_build_query($aNumberConvArgs);
        $oResponse = lovd_callJSONService($sNumberConvURL);
        if ($oResponse && !isset($oResponse->errorcode) && !isset($oResponse->faultcode) &&
            count($oResponse) > 0 && !empty($oResponse[0])) {
            $oResponseFields = explode(':', $oResponse[0], 2);
            $aVOGRecord['VariantOnGenome/DNA'] = $oResponseFields[1];
        }

        $aVOGRecords[$sRecordID] = $aVOGRecord;
        $aVOTRecords[$sRecordID] = $aVOTRecord;
    }
    return array(
        $aVOGRecords,
        $aVOTRecords,
        $aDiseases,
        $aIndividuals,
        $aIndividuals2Diseases,
        $aScreenings,
        $aScreening2Genes,
        $aScreening2Variants,
        $aPhenotypes);
}





function lovd_setUserIDSettings ($sFixedUserIDInput, $sUserTranslationTableInput)
{
    // Validate form settings for handling user IDs. Calls lovd_errorAdd() when
    // something went wrong. User ID settings are interpreted as follows:
    // $sFixedUserIDInput:          Default user ID, should be integer
    //                              referring to existing user.
    // $sUserTranslationTableInput: Textual translation table, where every line
    //                              contains two integers separated by
    //                              whitespace. The first int is the LOVD2 user
    //                              ID value to translate, the second int is
    //                              the LOVD3 user ID value to which is being
    //                              translated.

    global $sFixedUserID, $aUserTranslationTable;

    if (isset($sFixedUserIDInput)) {
        if (ctype_digit($sFixedUserIDInput)) {
            $sFixedUserID = $sFixedUserIDInput;
        } else {
            lovd_errorAdd('userid_fixed', 'Error: Fixed user ID must be numeric.');
        }
    }

    if (!empty($sUserTranslationTableInput)) {
        foreach (explode("\n", $sUserTranslationTableInput) as $sLine) {
            $sLineClean = trim($sLine);
            if (!empty($sLineClean)) {
                preg_match('/^\s*(\d+)\s+(\d+)\s*$/', $sLine, $m);
                if (count($m) != 3 || !ctype_digit($m[1]) || !ctype_digit($m[2])) {
                    lovd_errorAdd('userid_translation',
                        'Error: Malformed translation table for user IDs.');
                    break;
                }
                $aUserTranslationTable[$m[1]] = $m[2];
            }
        }
    }
}









function lovd_showConversionForm ($nMaxSizeLOVD, $nMaxSize)
{
    // Print HTML for the form specifying input to be converted.
    // Returns nothing.

    // Show viewlist for searching and selecting a transcript.
    $_DATA = new LOVD_Transcript();
    $_DATA->setRowLink('Transcripts', 'javascript: $("input[name=\'transcriptid\']").val({{ID}}); return false;');
    $_GET['page_size'] = 10;
    $_DATA->viewList('Transcripts', array('ID', 'variants'));

    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION .
        '" method="post" enctype="multipart/form-data">' . "\n");
    lovd_errorPrint();

    $aCharSets = array(
        'auto' => 'Autodetect',
        'UTF-8' => 'UTF-8 / Unicode',
        'ISO-8859-1' => 'ISO-8859-1 / Latin-1');

    // Array which will make up the form table.
    $aForm = array(
        array('POST', '', '', '', '35%', '14', '65%'),
        array('Transcript ID (click in table above)', 'Transcript to which generated import data' .
            ' will be linked.', 'text', 'transcriptid', 10),
        array('', '', 'note', 'Click the transcript in the table above to copy its ID here.'),
        'skip',
        array('Select LOVD2 export file to convert', '', 'file', 'LOVD2_export', 50),
        array('', 'Current file size limits:<BR>LOVD: ' . ($nMaxSizeLOVD/(1024*1024)) .
            'M<BR>PHP (upload_max_filesize): ' . ini_get('upload_max_filesize') .
            '<BR>PHP (post_max_size): ' .
            ini_get('post_max_size'), 'note', 'The maximum file size accepted is ' .
            round($nMaxSize/pow(1024, 2), 1) . ' MB' . ($nMaxSize == $nMaxSizeLOVD? '' :
                ', due to restrictions on this server. If you wish to have it increased, contact' .
                ' the server\'s system administrator') . '.'),
        array('Character encoding of imported file', 'If your file contains special characters ' .
            'like &egrave;, &ouml; or even just fancy quotes like &ldquo; or &rdquo;, LOVD needs ' .
            'to know the file\'s character encoding to ensure the correct display of the data.',
            'select', 'charset', 1, $aCharSets, false, false, false),
        array('', '', 'note', 'Please only change this setting in case you encounter problems ' .
            'with displaying special characters in imported data. Technical information about ' .
            'character encoding can be found <A ' .
            'href="http://en.wikipedia.org/wiki/Character_encoding" target="_blank">on Wikipedia' .
            '</A>.'),
        'skip',
        array('', '', 'print', 'User IDs in the selected LOVD2 export file are usually different' .
            ' from those in the LOVD3 application. Below one can define a "Fixed user ID" to ' .
            'set all user IDs in the file to a single value. One can also specify a translation ' .
            'between LOVD2 and LOVD3 IDs. The translation has precedence over the fixed value. ' .
            'If both fields are left empty, the user IDs are left untouched.'),
        array('Fixed user ID', 'All user IDs in the imported file will be replaced with this ' .
            'value. (E.g. all IDs in the edited_by, created_by fields)', 'text', 'userid_fixed', 10),
        array('', '', 'note', 'All user IDs in the imported file will be replaced with this ' .
            'value. (E.g. all IDs in the edited_by, created_by fields)'),
        array('User ID translation table', '', 'textarea', 'userid_translation', 20, 6),
        array('', '', 'note', 'Translation table for user IDs. On every line an LOVD2 user ID ' .
            'is expected, followed by an LOVD3 user ID, separated by whitespace. Any user IDs ' .
            'present in the selected file will translated according to this table.'),
        'hr',
        array('', '', 'submit', 'Generate LOVD3 import file'),
    );
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");
}





function lovd_trim($sValue) {
    return trim($sValue, '"\' ');
}




function lovd_validateConversionForm ($zTranscript, $nMaxSize, $nMaxSizeLOVD)
{
    // Validate fields submitted by form generated in lovd_showConversionForm().
    // Returns true if there were no errors.

    if (empty($_POST['transcriptid'])) {
        lovd_errorAdd('transcriptid', 'Error: No transcript selected.');
    } elseif (empty($zTranscript)) {
        lovd_errorAdd('transcriptid', 'Error: Unknown transcript.');
    }

    if (empty($_FILES['LOVD2_export']) || ($_FILES['LOVD2_export']['error'] > 0 &&
            $_FILES['LOVD2_export']['error'] < 4)) {
        lovd_errorAdd('LOVD2_export', 'There was a problem with the file transfer. Please try ' .
            'again. The file cannot be larger than ' . round($nMaxSize/pow(1024, 2), 1) . ' MB' .
            ($nMaxSize == $nMaxSizeLOVD? '' : ', due to restrictions on this server') . '.');

    } elseif ($_FILES['LOVD2_export']['error'] == 4 || !$_FILES['LOVD2_export']['size']) {
        lovd_errorAdd('LOVD2_export', 'Error: Please select a file to upload.');

    } elseif ($_FILES['LOVD2_export']['size'] > $nMaxSize) {
        lovd_errorAdd('LOVD2_export', 'Error: The file cannot be larger than ' .
            round($nMaxSize/pow(1024, 2), 1) . ' MB' . ($nMaxSize == $nMaxSizeLOVD? '' :
                ', due to restrictions on this server') . '.');

    } elseif ($_FILES['LOVD2_export']['error']) {
        // Various errors available from 4.3.0 or later.
        lovd_errorAdd('LOVD2_export', 'Error: There was an unknown problem with receiving the ' .
            'file properly, possibly because of the current server settings. If the problem ' .
            'persists, please contact the database administrator.');
    }

    // Parse and store fields with preferences regarding user ID handling.
    lovd_setUserIDSettings($_POST['userid_fixed'], $_POST['userid_translation']);

    return !lovd_error();
}





function main ($aFieldLinks, $aSections, $aCustomColLinks)
{
    global $_DB, $_T, $_SETT;

    // Only allow managers or higher to perform conversion.
    lovd_requireAUTH(LEVEL_MANAGER);

    // Selected transcript, will be created from request arguments.
    $zTranscript = null;

    // Determine max file upload size in bytes.
    $nMaxSizeLOVD = 100*1024*1024; // 100MB LOVD limit. Note: value copied from import.php
    $nMaxSize = min($nMaxSizeLOVD,
        lovd_convertIniValueToBytes(ini_get('upload_max_filesize')),
        lovd_convertIniValueToBytes(ini_get('post_max_size')));

    // Print header
    define('PAGE_TITLE', 'Convert LOVD2 export to LOVD3 import');
    $_T->printHeader(false);
    $_T->printTitle();

    if (!POST) {
        // Show file upload form.
        lovd_showConversionForm($nMaxSizeLOVD, $nMaxSize);
        return;
    } else {
        if (!empty($_POST['transcriptid'])) {
            $qTranscript = $_DB->query('SELECT t.*, chromosome FROM ' . TABLE_TRANSCRIPTS .
                ' AS t LEFT JOIN ' . TABLE_GENES . ' AS g ON (g.id = t.geneid)  WHERE t.id=?',
                array($_POST['transcriptid']));
            $zTranscript = $qTranscript->fetchAssoc();
        }
        if (!lovd_validateConversionForm($zTranscript, $nMaxSize,
            $nMaxSizeLOVD)) {
            lovd_showConversionForm($nMaxSizeLOVD, $nMaxSize);
            return;
        }
    }

    $oProgressBar = new ProgressBar('', 'Parsing file...', 'Done.');

    $aData = lovd_openLOVD2ExportFile($_POST, $_FILES);

    // Get headers for input (as defined in file) and output (per section).
    list($aInputHeaders, $aOutputHeaders, $aHeaderWarnings) = lovd_getHeaders($aData, $aFieldLinks,
        $aSections, $aCustomColLinks);
    print('<H3>Field linking log:</H3>
        <TEXTAREA id="header_log" cols="100" rows="10" style="font-family: monospace; 
            white-space: nowrap; overflow: scroll;">' .
        join("\n", $aHeaderWarnings) .
        '</TEXTAREA><BR><BR>');

    if ($aData === false || $aInputHeaders === false) {
        lovd_showConversionForm($nMaxSizeLOVD, $nMaxSize);
        return;
    }

    // Parse data and get output records per section.
    list($aOut['vog'],
        $aOut['vot'],
        $aOut['disease'],
        $aOut['individual'],
        $aOut['i2d'],
        $aOut['screening'],
        $aOut['s2g'],
        $aOut['s2v'],
        $aOut['phenotype']) = lovd_parseData($aData, $zTranscript, $aFieldLinks, $aInputHeaders,
            $aOutputHeaders, $aSections, $oProgressBar);

    $sOutput = '### LOVD-version ' . lovd_calculateVersion($_SETT['system']['version']) .
        " ### Full data download ### To import, do not remove or alter this header ###\n" .
        '## Filter: (gene = ' . $zTranscript['geneid'] . ")\n# charset = UTF-8\n";

    foreach (array_keys($aSections) as $sSection) {
        $sOutput .= lovd_getSectionOutput($aSections[$sSection],
            isset($aOutputHeaders[$sSection])? $aOutputHeaders[$sSection] : array(),
            isset($aOut[$sSection])? $aOut[$sSection] : array());
    }

    print('<H3>LOVD3 import data:</H3>
<TEXTAREA id="conversion_output" cols="100" rows="20" style="font-family: monospace; 
    white-space: nowrap; overflow: scroll;">' .
$sOutput .
'</TEXTAREA>
<BUTTON id="copybutton">Copy content to clipboard</BUTTON>
<SCRIPT language="JavaScript">
    $("#copybutton").on("click", function () {
        $("#conversion_output").select();
        document.execCommand("copy");
    });
</SCRIPT>');

    $oProgressBar->setProgress(100);
    $oProgressBar->setMessage('Done.');

    $_T->printFooter();
}





// Call main function with setting variables.
main($aFieldLinks, $aImportSections, $aCustomColLinks);


