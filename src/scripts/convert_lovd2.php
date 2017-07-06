<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-10-04
 * Modified    : 2017-06-27
 * For LOVD    : 3.0-19
 *
 * Copyright   : 2014-2017 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : M. Kroon <m.kroon@lumc.nl>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

// Global for storing warning messages during conversion.
$_WARNINGS = array();

// Array of field names to ignore in input.
$aIgnoredFields = array_flip(array('ID_sort_'));

// Links between LOVD2-LOVD3 fields, with optional conversion function. Format:
// LOVD2_field => array(LOVD3_section, LOVD3_field, Conversion_function)
// Where LOVD2_field is an LOVD2 field name as it occurs in the input file,
// LOVD3_section is a output section name as defined as key in the
// $aImportSections variable, LOVD3_field is an LOVD3 field name and
// Conversion_function is an optional name of a function taking a LOVD2 field
// value as a string as argument and returning LOVD3 field value as a string.
$aFieldLinks = array(
    // This field maps to either VOT/Published_as or VOG/Published_as (handled later specifically).
    'Variant/DNA_published' =>          array('vot',        'VariantOnTranscript/Published_as'),
    'Variant/DBID' =>                   array('vog',        'VariantOnGenome/DBID',         'lovd_convertDBID'),
    'Variant/Restriction_site' =>       array('vog',        'VariantOnGenome/Restriction_site'),
    'Variant/Remarks' =>                array('vog',        'VariantOnGenome/Remarks'),
    'Variant/Genetic_origin' =>         array('vog',        'VariantOnGenome/Genetic_origin'),
    'Variant/Detection/Technique' =>    array('screening',  'Screening/Technique',          'lovd_convertScrTech'),
    'Variant/Reference' =>              array('vog',        'VariantOnGenome/Reference','lovd_convertReference'),
    'Patient/Patient_ID' =>             array('individual', 'Individual/Lab_ID'),
    'Patient/Reference' =>              array('individual', 'Individual/Reference',         'lovd_convertReference'),
    'Patient/Gender' =>                 array('individual', 'Individual/Gender',            'lovd_convertGender'),
    'Patient/Times_Reported' =>         array('individual', 'panel_size'),
    'Patient/Phenotype_2' =>            array('phenotype',  'Phenotype/Additional'),
    // Note that 'Patient/Phenotype/Inheritance' automatically maps to Phenotype/Inheritance as well.
    'Patient/Occurrence' =>             array('phenotype',  'Phenotype/Inheritance',        'lovd_convertInheritance'),
    'Patient/Mutation/Origin' =>        array('vog',        'VariantOnGenome/Genetic_origin',   'lovd_convertOrigin'),
    'ID_pathogenic_' =>                 array('vog',        'effectid'),
    'ID_status_' =>                     array('vog',        'statusid',                     'lovd_convertStatus'),
    'ID_variant_created_by_' =>         array('vog',        'created_by',                   'lovd_convertCuratorID'),
    'variant_created_date_' =>          array('vog',        'created_date'),
    'ID_variant_edited_by_' =>          array('vog',        'edited_by',                    'lovd_convertEditedByID'),
    'variant_edited_date_' =>           array('vog',        'edited_date'),
    'ID_patient_created_by_' =>         array('individual', 'created_by',                   'lovd_convertCuratorID'),
    'patient_created_date_' =>          array('individual', 'created_date'),
    'ID_patient_edited_by_' =>          array('individual', 'edited_by',                    'lovd_convertEditedByID'),
    'patient_edited_date_' =>           array('individual', 'edited_date'),
    'ID_patientid_' =>                  array('individual', 'id',                           'lovd_autoIncIndividualID'),
    'ID_variantid_' =>                  array('vog',        'id',                           'lovd_autoIncVariantID'),
    'ID_allele_' =>                     array('vog',        'allele'),
    'Patient/Phenotype/Disease' =>      array('disease',    'name'),
);



// Defaults for prefixed custom column fields not mentioned in $aFieldLinks.
// (e.g. 'Patient' => array('individual', 'Individual') will cause field
// 'Patient/Origin/Population' to be linked to 'Individual/Origin/Population'
// in the individual section). Note that the prefixes higher up in the array
// will be preferred, so be careful to place more generic prefixes at the
// bottom.
$aCustomColLinks = array(
    'Variant/Detection' =>  array('screening', 'Screening'),
    'Variant' =>            array('vot', 'VariantOnTranscript'),
    'Patient/Phenotype' =>  array('phenotype', 'Phenotype'),
    'Patient' =>            array('individual', 'Individual')
);



// Output section information describing the LOVD3 import format. Each section
// is defined by a key and one or more settings, where only the 'output_header'
// setting is mandatory. The following settings are available:
// output_header:       Title of section in output.
// customcol_prefix:    Prefix for custom columns in this section.
// mandatory_fields:    Array of mandatory fields as keys, and default values
//                      as values.
// table:               Database table corresponding to the section.
// comments:            Comments to be stored in the import file.
$aImportSections = array(
    'column' =>     array(
        'output_header' =>          'Columns'),
    'gene' =>       array(
        'output_header' =>          'Genes'),
    'transcript' => array(
        'output_header' =>          'Transcripts'),
    'disease' =>    array(
        'output_header' =>          'Diseases',
        'mandatory_fields' =>       array('id' => '1', 'name' => '-', 'symbol' => ''),
        'comments' =>               array('Diseases listed here were not found in the database ' .
                                    '(in either name or symbol field).',
                                    'If this is a mistake, please edit the disease below to reflect the database contents, ' .
                                    'or edit the disease in the database to match this file, ' .
                                    'in order to avoid duplication of diseases in the database.')),
    'g2d' =>        array(
        'output_header' =>          'Genes_To_Diseases'),
    'individual' => array(
        'output_header' =>          'Individuals',
        'customcol_prefix' =>       'Individual',
        'mandatory_fields' =>       array('id' => '0', 'panel_size' => '1', 'statusid' => '',
            'owned_by' => '0', 'created_by' => '0', 'created_date' => '', 'edited_by' => '',
            'edited_date' => '')),
    'i2d' =>        array(
        'output_header' =>          'Individuals_To_Diseases',
        'mandatory_fields' =>       array('individualid' => '0', 'diseaseid' => '0')),
    'phenotype' =>  array(
        'output_header' =>          'Phenotypes',
        'customcol_prefix' =>       'Phenotype',
        'mandatory_fields' =>       array('id' => '0', 'diseaseid' => '0', 'individualid' => '0',
            'statusid' => '', 'owned_by' => '0', 'created_by' => '0', 'created_date' => '',
            'edited_by' => '', 'edited_date' => '')),
    'screening' =>  array(
        'output_header' =>          'Screenings',
        'customcol_prefix' =>       'Screening',
        'mandatory_fields' =>       array('id' => '0', 'individualid' => '0',
            'Screening/Template' => '?', 'Screening/Technique' => '?', 'owned_by' => '0',
            'created_by' => '0', 'created_date' => '', 'edited_by' => '', 'edited_date' => '')),
    's2g' =>        array(
        'output_header' =>          'Screenings_To_Genes',
        'mandatory_fields' =>       array('screeningid' => '0', 'geneid' => '')),
    'vog' =>        array(
        'output_header' =>          'Variants_On_Genome',
        'customcol_prefix' =>       'VariantOnGenome',
        'mandatory_fields' =>       array('id' => '0', 'allele' => '0', 'chromosome' => '0',
            'position_g_start' => '0', 'position_g_end' => '0', 'type' => '?',
            'VariantOnGenome/DNA' => 'g.?', 'statusid' => '', 'owned_by' => '0',
            'created_by' => '0', 'created_date' => '', 'edited_by' => '', 'edited_date' => '')),
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
// lovd_convertSubmitterID() and lovd_convertCuratorID().
$sFixedSubmitterID = null;
$sFixedCuratorID = null;

// Translation array of LOVD2 user IDs to LOVD3 user IDs. Used by
// lovd_convertSubmitterID() and lovd_convertCuratorID().
$aSubmitterTranslationTable = array();
$aCuratorTranslationTable = array();





function lovd_autoIncIndividualID ($LOVD2PatientID)
{
    // ID generator for individuals.
    return lovd_getInc('lovd_autoIncIndividualID');
}





function lovd_autoIncPhenotypeID ()
{
    // ID generator for phenotypes.
    return lovd_getInc('lovd_autoIncPhenotypeID');
}





function lovd_autoIncVariantID ($LOVD2PatientID)
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





function lovd_convertCuratorID ($nLOVD2UserID)
{
    // Returns curator ID for given LOVD2 user ID. Return value is based on
    // settings for fixed (default) user ID and ID translation table, both
    // are defined in the upload form. Returns false if $nLOVD2UserID is
    // equal to zero, indicating that the submitterID should be used.
    global $sFixedCuratorID, $aCuratorTranslationTable, $_WARNINGS;

    // Convert curator ID.
    if ($nLOVD2UserID === '0') {
        // '0' in LOVD2 export means the submitter ID should be used.
        // Return false.
        return false;
    }
    if (ctype_digit($nLOVD2UserID) && isset($aCuratorTranslationTable[(int) $nLOVD2UserID])) {
        // Found match in translation table.
        return $aCuratorTranslationTable[(int) $nLOVD2UserID];
    }
    if (!is_null($sFixedCuratorID)) {
        // Default to fixed user ID.
        return $sFixedCuratorID;
    }
    // Last resort is to return the original ID.
    $_WARNINGS[] = 'Warning: unknown user ID for curator: "' . $nLOVD2UserID . '" (no ' .
        'fixed ID set and ID is not in translation table)';
    return $nLOVD2UserID;
}





function lovd_convertDBID ($sLOVD2DBID)
{
    // Returns an LOVD3-formatted DBID for the given $sLOVD2DBID by padding
    // the number with an extra '0'.

    $aChunks = explode('_', $sLOVD2DBID);
    $nParts = count($aChunks);
    if ($nParts > 1 && ctype_digit($aChunks[$nParts-1])) {
        $aChunks[$nParts-1] = '0' . $aChunks[$nParts-1];
        return join('_', $aChunks);
    }
    return $sLOVD2DBID;
}





function lovd_convertEditedByID ($sLOVD2EditedBy)
{
    // Convert edited_by fields from LOVD2 to LOVD3. This is a wrapper for
    // lovd_convertCuratorID() with the difference that if the input is empty,
    // it will not fall back on the fixed user ID specified in the submission
    // form.
    if ($sLOVD2EditedBy != '') {
        return lovd_convertCuratorID($sLOVD2EditedBy);
    }
    return $sLOVD2EditedBy;
}




function lovd_convertGender ($sLOVD2Gender)
{
    // Returns LOVD3 gender value given LOVD2 gender value.
    if (strcasecmp($sLOVD2Gender, 'Female') === 0) {
        return 'F';
    } elseif (strcasecmp($sLOVD2Gender, 'Male') === 0) {
        return 'M';
    }
    // Don't lose data. If it's something we don't recognize, just return the
    //  original value.
    return $sLOVD2Gender;
}





function lovd_convertInheritance ($sLOVD2Occurrence)
{
    // Convert values from LOVD2's 'Patient/Occurrence' to LOVD3's
    // Individual/Inheritance.
    if (strcasecmp($sLOVD2Occurrence, 'Sporadic') === 0) {
        return 'Isolated (sporadic)';
    }
    // Don't lose data. If it's something we don't recognize, just return the
    //  original value.
    return $sLOVD2Occurrence;
}





function lovd_convertOrigin ($sLOVD2MutationOrigin)
{
    // Convert LOVD2's 'Patient/Mutation/Origin' to LOVD3's
    // 'Individual/Genetic_origin'.
    if (strcasecmp($sLOVD2MutationOrigin, 'Inherited') === 0) {
        return 'Germline';
    }
    // Don't lose data. If it's something we don't recognize, just return the
    //  original value.
    return $sLOVD2MutationOrigin;
}





function lovd_convertReference ($LOVD2Reference)
{
    // Convert LOVD2-style reference to LOVD3-style. E.g.:
    // {PMID21228398:Bell 2011} => {PMID:Bell 2011:21228398}
    return preg_replace('/{PMID(\d+):([^}]+)}/', '{PMID:\\2:\\1}', $LOVD2Reference);
}





function lovd_convertScrTech ($sLOVD2ScreeningTechniques)
{
    // Convert LOVD2's 'Patient/Detection/Technique' to LOVD3's
    // 'Screening/Technique'.

    $aTechniques = array_map(function ($sTechnique) {
        if ($sTechnique == 'mPCR') {
            return 'PCRm';
        }
        // Don't lose data. If it's something we don't recognize, just return the
        //  original value.
        return $sTechnique;
    }, explode(';', $sLOVD2ScreeningTechniques));

    return join(';', $aTechniques);
}





function lovd_convertStatus ($nLOVD2Status)
{
    // Convert LOVD2's STATUS_NEW to LOVD3's STATUS_PENDING.
    if (ctype_digit($nLOVD2Status) && intval($nLOVD2Status) === 1) {
        return STATUS_PENDING;
    }
    return $nLOVD2Status;
}





function lovd_convertSubmitterID ($nLOVD2UserID)
{
    // Returns submitter ID for given LOVD2 user ID. Return value is based on
    // settings for fixed (default) user ID and ID translation table, both
    // are defined in the upload form.
    global $sFixedSubmitterID, $aSubmitterTranslationTable, $_WARNINGS;

    // Convert curator ID.
    if (ctype_digit($nLOVD2UserID) && isset($aSubmitterTranslationTable[(int) $nLOVD2UserID])) {
        // Found match in translation table.
        return $aSubmitterTranslationTable[(int) $nLOVD2UserID];
    }
    if (!is_null($sFixedSubmitterID)) {
        // Default to fixed user ID.
        return $sFixedSubmitterID;
    }
    // Last resort is to return the original ID.
    $_WARNINGS[] = 'Warning: unknown user ID for submitter: "' . $nLOVD2UserID . '" (no ' .
        'fixed ID set and ID is not in translation table)';
    return $nLOVD2UserID;
}





function lovd_getDiseaseID ($sDiseaseName)
{
    // Get the ID from the database, searching the name and symbol fields for
    // given disease $sDiseaseName. If it is not present in the database,
    // generate and return an automatic incrementing ID. Displays an error if
    // there are multiple hits in the database.
    // Returns array with disease ID (or false if an error occurred) and a
    // boolean flag stating whether a new disease record for this ID should be
    // created. Returns array(false, false) if multiple matching diseases are
    // found in the DB.
    global $_DB;
    static $aKnownDiseases;

    $bNewDisease = false;
    if (!isset($aKnownDiseases[$sDiseaseName])) {
        $qDiseases = $_DB->query('SELECT id FROM ' . TABLE_DISEASES . ' WHERE name = ? OR symbol = ?',
            array($sDiseaseName, $sDiseaseName));
        $zDiseases = $qDiseases->fetchAllAssoc();
        if (!$zDiseases) {
            // Not in database: create new unique disease ID.
            $aKnownDiseases[$sDiseaseName] = lovd_getInc('Diseases');
            $bNewDisease = true;
        } elseif (count($zDiseases) > 1) {
            // Multiple hits in database.
            lovd_errorAdd('LOVD2_export', 'Error: disease name "' . $sDiseaseName .
                '" is ambiguous, it matches name or symbol for more than one disease in the ' .
                'database.');
            return array(false, false);
        } else {
            // Exactly one hit in database.
            $aKnownDiseases[$sDiseaseName] = $zDiseases[0]['id'];
        }
    }
    return array($aKnownDiseases[$sDiseaseName], $bNewDisease);
}





function lovd_getHeaders ($aData, $aFieldLinks, $aSections, $aCustomColLinks)
{
    // Parse the file, read the headers, map columns to its LOVD3 counterparts,
    //  add mandatory fields and prepare output columns.
    // Returns an array with two array values; the input and output records
    //  respectively. Warning messages are stored in $_WARNINGS. The
    // first array contains the names as defined in the header of the input
    // file. The second array contains per section field names for the output
    // file (the LOVD3 import format).
    // Returns false for both header arrays if header cannot be either found or
    // parsed.

    global $_DB, $_WARNINGS, $aIgnoredFields;

    if (!is_array($aData)) {
        lovd_errorAdd('LOVD2_export', 'Invalid input.');
        return array(false, false);
    }

    // Walk through lines until header is found, then parse the header.
    foreach ($aData as $i => $sLine) {
        $sLine = trim($sLine);
        if (empty($sLine) || $sLine{0} == '#') {
            // Ignore blank lines and comments.
            continue;
        }

        $aMatches = array();
        preg_match_all('/"?{{\s*([^ }]+)\s*}}"?/', $sLine, $aMatches);

        if (empty($aMatches[0]) || empty($aMatches[1])) {
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

        $aSectionIDs = array_keys($aSections);
        // Loop over input headers and link them to output headers, such that
        // $aOutputHeaders[section][i] = outHeader, where section is the output
        // section as defined in $aImportSections, i is the index of the input
        // header and outHeader is the name of the column in the output.
        for ($i = 0; $i < count($aMatches[1]); $i++) {
            $sHeader = $aMatches[1][$i];

            // Skip fields listed to ignore.
            if (key_exists($sHeader, $aIgnoredFields)) {
                continue;
            }

            // Special consideration for Variant/DNA_published, as it can be linked to two
            // fields: VariantOnTranscript/Published_as and VariantOnGenome/Published_as,
            // but the former is preferred.
            if ($sHeader == 'Variant/DNA_published') {
                if (!in_array('VariantOnTranscript/Published_as', $aSections['vot']['db_fields']) &&
                    in_array('VariantOnGenome/Published_as', $aSections['vog']['db_fields'])) {
                    // Field available on VOG and not on VOT.
                    $aOutputHeaders['vog'][$i] = 'VariantOnGenome/Published_as';
                    continue;
                } else {
                    // By default put the published_as field on VOT.
                    $aOutputHeaders['vot'][$i] = 'VariantOnTranscript/Published_as';
                    continue;
                }
            }

            // Check if field is manually linked in $aFieldLinks.
            if (isset($aFieldLinks[$sHeader])) {
                // Use output header linked in $aFieldLinks.
                list($sSection, $sHeaderOut) = $aFieldLinks[$sHeader];
                $aOutputHeaders[$sSection][$i] = $sHeaderOut;
                continue;
            }

            // Check if header occurs as a literal DB field.
            foreach ($aSectionIDs as $sSection) {
                if (isset($aSections[$sSection]['db_fields'])  &&
                    in_array($sHeader, $aSections[$sSection]['db_fields'])) {
                    $aOutputHeaders[$sSection][$i] = $sHeader;
                    continue 2;
                }
            }

            // Try to link custom columns.
            if (strpos($sHeader, '/') !== false) {
                // Try to find default custom column translation.
                foreach ($aCustomColLinks as $sPrefix => $aCustomColDefault) {
                    if (strpos($sHeader, $sPrefix) === 0) {
                        list($sSection, $sPrefixOut) = $aCustomColDefault;
                        $sHeaderOut = str_replace($sPrefix, $sPrefixOut, $sHeader);
                        $aOutputHeaders[$sSection][$i] = $sHeaderOut;
                        if (!in_array($sHeaderOut, $aSections[$sSection]['db_fields'])) {
                            $_WARNINGS[] = 'Warning: linked "' . $sHeader . '" to non-existing ' .
                                           'column "' . $sHeaderOut . '" in output section "' .
                                           $aSections[$sSection]['output_header'] . '"';
                        }
                        continue 2;
                    }
                }

                // Try to find a DB field name with corresponding suffix.
                list(, $sFieldname) = explode('/', $sHeader, 2);
                foreach ($aSectionIDs as $sSection) {
                    $aSection = $aSections[$sSection];
                    if (isset($aSection['customcol_prefix'])) {
                        $sNewFieldName = $aSection['customcol_prefix'] . '/' . $sFieldname;
                        if (in_array($sNewFieldName, $aSection['db_fields'])) {
                            // Set output header with new LOVD3 prefix (e.g. Individual).
                            $aOutputHeaders[$sSection][$i] = $sNewFieldName;
                            continue 2;
                        }
                    }
                }
            }

            // Could not link input header intelligently.
            $_WARNINGS[] = 'Warning: could not link field "' . $sHeader . '"';
        }

        // Handle special case effectid: if this field exists for VOG, also
        // add it to VOT.
        if (in_array('effectid', $aOutputHeaders['vog'])) {
            $aOutputHeaders['vot']['effectid'] = 'effectid';
        }

        // Output header post processing.
        foreach ($aSections as $sSection => $aImportSection) {
            // Add mandatory fields.
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

            // Find if fields are linked more than once in this section. (outer call to
            // array_unique() is needed for when more than 2 inputs link to the same output.
            $aDuplicates = array_unique(array_diff_key($aOutputHeaders[$sSection],
                array_unique($aOutputHeaders[$sSection])));
            foreach ($aDuplicates as $sDupHeader) {
                // We get here when field $sDupHeader appears more than once.
                $aDupKeys = array_keys($aOutputHeaders[$sSection], $sDupHeader);
                $prevKey = null;
                foreach ($aDupKeys as $sKey) {
                    if (!is_null($prevKey)) {
                        $sPrevField = is_int($prevKey)? $aMatches[1][$prevKey] : $prevKey;
                        $sCurrField = is_int($sKey)? $aMatches[1][$sKey] : $sKey;
                        $_WARNINGS[] = 'Warning: output field ' . $sSection . ':' . $sDupHeader
                                       . ' is linked to both ' . $sPrevField . ' and ' .
                                       $sCurrField . '. Values for ' . $sCurrField . ' may ' .
                                       'get lost when ' . $sPrevField . ' is non-empty';
                    }
                    $prevKey = $sKey;
                }
            }
        }

        return array($aMatches[1], $aOutputHeaders);
    }

    lovd_errorAdd('LOVD2_export', 'Cannot find header in file.');
    return array(false, false);
}





function lovd_getInc ($sCounterName = 'default')
{
    // Static automatic incrementor. Returns incrementing integers across
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





function lovd_getRecordForHeaders ($aOutputHeaders, $aRecord, $aSection = null)
{
    // Given output headers $aOutputHeaders with integer keys linked to fields
    // in the input record $aRecord, generate an array with the fields filled
    // with values for the corresponding links. E.g. given $aOutputHeaders =
    // array(0 => 'field1', 1 => 'field2', 'dummy' => 'field3') and $aRecord =
    // array('v1', 'v2'), this function would return array('field1' => 'v1',
    // field2 => 'v2', 'dummy' => null).
    global $_WARNINGS;
    $aNewRecord = array();
    foreach ($aOutputHeaders as $nInputIdx => $sHeader) {
        if (is_int($nInputIdx)) {
            // Numeric key $nInputIdx defines link to field in input record $aRecord.

            if (!isset($aNewRecord[$sHeader]) || $aNewRecord[$sHeader] === '') {
                $aNewRecord[$sHeader] = $aRecord[$nInputIdx];
            } else if ($aRecord[$nInputIdx] !== '') {
                $_WARNINGS[] = 'Warning: doubly-linked field already has a value "' .
                               $aNewRecord[$sHeader] . '", alternate value will get lost: "' .
                               $aRecord[$nInputIdx] . '"';
            }
        } else {
            // Leave non-linked fields empty for now. These are probably
            // mandatory fields not provided directly in the input.
            $aNewRecord[$sHeader] = null;
        }
    }

    if (!is_null($aSection) && isset($aSection['mandatory_fields'])) {
        // Set default value for mandatory fields.
        foreach ($aSection['mandatory_fields'] as $sHeader => $sDefault) {
            if (!isset($aNewRecord[$sHeader]) || $aNewRecord[$sHeader] === '') {
                $aNewRecord[$sHeader] = $sDefault;
            }
        }
    }
    return $aNewRecord;
}





function lovd_getSectionOutput ($aImportSection, $aOutputHeaders, $aRecords)
{
    // Generate LOVD3 import data format from converted LOVD2 records.

    $sOutput = "\n" . '## ' . $aImportSection['output_header'] .
               ' ## Do not remove or alter this header ##' . "\n" .
               '## Count = ' . strval(count($aRecords)) . "\n";

    if (isset($aImportSection['comments'])) {
        foreach ($aImportSection['comments'] as $sComment) {
            $sOutput .= '# ' . $sComment . "\n";
        }
    }

    // Get output for header line. array_unique() is called because headers
    // will be duplicated when multiple input fields link to the same output.
    $aUniqueHeaders = array_unique($aOutputHeaders);
    $sOutput .= implode("\t", array_map(function ($sHeader) {
        return '"{{' . $sHeader . '}}"';
    }, $aUniqueHeaders)) . "\n";

    foreach ($aRecords as $aRecord) {
        // Put record fields in same order as headers.
        $aOutRecord = array();
        foreach ($aUniqueHeaders as $sHeader) {
            $aOutRecord[] = '"' . $aRecord[$sHeader] . '"';
        }
        $sOutput .= implode("\t", $aOutRecord) . "\n";
    }
    $sOutput .= "\n";
    return $sOutput;
}





function lovd_openLOVD2ExportFile ($aRequest, $aFiles)
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

    $nCounter = 0;
    foreach ($aData as $i => $sLine) {
        // Set progress bar (leave 1 percent for output generation).
        $oProgressBar->setProgress((++$nCounter / $nNumLines) * 99);
        $oProgressBar->setMessage('Converting record ' . strval($nCounter) . ' of ' .
            strval($nNumLines) . '...');

        if (empty($sLine) || $sLine{0} == '#' || preg_match('/^"?{{.*/', $sLine)) {
            // Ignore blank lines, comments and the header line.
            continue;
        }

        // Loop over fields in record and convert values according to $aFieldLinks.
        $aInputRecord = explode("\t", $sLine);

        if (count($aInputRecord) != count($aInputHeaders)) {
            lovd_errorAdd('', 'Incorrect number of columns for record ' . $i .
                ' (see below), check if the number of fields corresponds with the number of ' .
                'column headings or if a field contains a tab character?<br>' . $sLine);
            return null;
        }

        $aRecord = array();
        for ($i = 0; $i < count($aInputRecord); $i++) {
            $sFieldName = $aInputHeaders[$i];
            $sFieldValue = lovd_trimField($aInputRecord[$i]);
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
        // Check if we already know this variant_id/patient_id combination. This
        //  is done to find homozygous variants as they will occur twice in the
        //  LOVD2 export file with identical variant_id/patient_id.
        if (isset($aVOGRecords[$sRecordID])) {
            // Combination variant_id/patient_id already seen, set previous
            // record allele field to 3 (homozygous). Skip this record.
            $aVOGRecords[$sRecordID]['allele'] = 3;
            continue;
        }

        // Get submitter ID.
        $sSubmitterID = null;
        if (($i = array_search('ID_submitterid_', $aInputHeaders)) !== false) {
            $sSubmitterID = lovd_convertSubmitterID($aRecord[$i]);
        }

        // Create new disease if necessary.
        $aDisease = lovd_getRecordForHeaders($aOutputHeaders['disease'], $aRecord,
            $aSections['disease']);
        if (!empty($aDisease['name'])) {
            list($aDisease['id'], $bCreateNewDisease) = lovd_getDiseaseID($aDisease['name']);
            if ($bCreateNewDisease && $aDisease['id'] !== false) {
                // New disease, create an output record for it.
                $aDiseases[] = $aDisease;
            }
        }

        // Handle individual-specific data (individual, screening, phenotype, etc.).
        if (($i = array_search('ID_patientid_', $aInputHeaders)) !== false) {
            $sLOVD2IndividualID = $aInputRecord[$i];
            if (!isset($aIndividuals[$sLOVD2IndividualID])) {
                // New individual, create an output record for it.
                $aIndividual = lovd_getRecordForHeaders($aOutputHeaders['individual'], $aRecord,
                    $aSections['individual']);
                if ($aIndividual['created_by'] === false) {
                    // No curator ID was available, set submitter ID.
                    $aIndividual['created_by'] = $sSubmitterID;
                }
                if ($aIndividual['edited_by'] === false) {
                    // No curator ID was available, set submitter ID.
                    $aIndividual['edited_by'] = $sSubmitterID;
                }
                // Use the translated submitter ID as owner, fall back to value in created_by.
                $aIndividual['owned_by'] = $sSubmitterID != ''? $sSubmitterID :
                    $aIndividual['created_by'];
                if (($nStatusIdx = array_search('ID_status_', $aInputHeaders)) !== false) {
                    $aIndividual['statusid'] = $aRecord[$nStatusIdx];
                }
                $aIndividuals[$sLOVD2IndividualID] = $aIndividual;

                // Create screening record.
                $aScreening = lovd_getRecordForHeaders($aOutputHeaders['screening'], $aRecord,
                    $aSections['screening']);
                $nScreeningID = lovd_autoIncScreeningID();
                $aScreening['id'] = $nScreeningID;
                $aScreening['individualid'] = $aIndividual['id'];
                $aScreening['owned_by'] = $aIndividual['owned_by'];
                $aScreening['created_by'] = $aIndividual['created_by'];
                $aScreening['created_date'] = $aIndividual['created_date'];
                $aScreening['edited_by'] = $aIndividual['edited_by'];
                $aScreening['edited_date'] = $aIndividual['edited_date'];
                $aScreenings[$sLOVD2IndividualID] = $aScreening;

                // Create screening2gene record.
                $aScreening2Genes[] = array('screeningid' => $nScreeningID,
                                            'geneid' => $zTranscript['geneid']);

                // Create phenotype record.
                $aPhenotype = lovd_getRecordForHeaders($aOutputHeaders['phenotype'], $aRecord,
                    $aSections['phenotype']);
                // Skip phenotype when there is no data in phenotype record except for ID fields.
                $bEmptyPhenotype = true;
                foreach ($aPhenotype as $sField => $sValue) {
                    if ($sValue !== '' &&
                        (!key_exists($sField, $aSections['phenotype']['mandatory_fields']) ||
                         $sValue !== $aSections['phenotype']['mandatory_fields'][$sField])) {
                        $bEmptyPhenotype = false;
                        break;
                    }
                }
                if (!$bEmptyPhenotype) {
                    $aPhenotype['id'] = lovd_autoIncPhenotypeID();
                    $aPhenotype['diseaseid'] = $aDisease['id'];
                    $aPhenotype['individualid'] = $aIndividual['id'];
                    $aPhenotype['statusid'] = $aIndividual['statusid'];
                    $aPhenotype['owned_by'] = $aIndividual['owned_by'];
                    $aPhenotype['created_by'] = $aIndividual['created_by'];
                    $aPhenotype['created_date'] = $aIndividual['created_date'];
                    $aPhenotype['edited_by'] = $aIndividual['edited_by'];
                    $aPhenotype['edited_date'] = $aIndividual['edited_date'];
                    $aPhenotypes[$sLOVD2IndividualID] = $aPhenotype;
                }

                // Create individuals2diseases record.
                $aIndividuals2Disease = lovd_getRecordForHeaders($aOutputHeaders['i2d'], $aRecord);
                $aIndividuals2Disease['individualid'] = $aIndividual['id'];
                $aIndividuals2Disease['diseaseid'] = $aDisease['id'];
                $aIndividuals2Diseases[] = $aIndividuals2Disease;
            }

            // Create screening2variant record.
            $aScreening2Variants[] = array(
                'screeningid' => $aScreenings[$sLOVD2IndividualID]['id'],
                'variantid' => $aRecord[array_search('ID_variantid_', $aInputHeaders)]);
        }

        // Create VOG/VOT records.
        $aVOGRecord = lovd_getRecordForHeaders($aOutputHeaders['vog'], $aRecord,
            $aSections['vog']);
        $aVOGRecord['chromosome'] = $zTranscript['chromosome'];
        if ($aVOGRecord['created_by'] === false) {
            // No curator ID was available, set submitter ID.
            $aVOGRecord['created_by'] = $sSubmitterID;
        }
        if ($aVOGRecord['edited_by'] === false) {
            // No curator ID was available, set submitter ID.
            $aVOGRecord['edited_by'] = $sSubmitterID;
        }
        // Use the translated submitter ID as owner, fall back to value in created_by.
        $aVOGRecord['owned_by'] = $sSubmitterID != ''? $sSubmitterID : $aVOGRecord['created_by'];

        $aVOTRecord = lovd_getRecordForHeaders($aOutputHeaders['vot'], $aRecord,
            $aSections['vot']);
        $aVOTRecord['id'] = $aVOGRecord['id'];
        if (isset($aVOGRecord['effectid'])) {
            $aVOTRecord['effectid'] = $aVOGRecord['effectid'];
        }
        $aVOTRecord['transcriptid'] = $zTranscript['id'];

        // Get positions on transcript/chromosome from mutalyzer for variant.
        // FIXME: Using the new lovd_getVariantInfo() function, you can skip
        //  this webservice call.
        $nHGVSIdx = array_search('Variant/DNA', $aInputHeaders);
        $sVariant = $aRecord[$nHGVSIdx];
        $aMappingInfoArgs = array(
            'LOVD_ver' => $_SETT['system']['version'],
            'build' => $_CONF['refseq_build'],
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

        // Call mutalyzer's numberConversion to get VariantOnGenome/DNA.
        $aNumberConvArgs = array(
            'build' => $_CONF['refseq_build'],
            'gene' => $zTranscript['geneid'],
            'variant' => $zTranscript['id_ncbi'] . ':' . $sVariant);
        $sNumberConvURL = str_replace('/services', '', $_CONF['mutalyzer_soap_url']) .
                          '/json/numberConversion?' . http_build_query($aNumberConvArgs);
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





function lovd_setUserIDSettings ($sFixedSubmitterIDInput, $sSubmitterTranslationTableInput,
                                 $sFixedCuratorIDInput, $sCuratorTranslationTableInput)
{
    // Validate form settings for handling user IDs. Calls lovd_errorAdd() when
    // something went wrong. User ID settings are interpreted as follows:
    // $sFixedSubmitterIDInput:          Default submitter ID, should be integer
    //                                   referring to existing user.
    // $sSubmitterTranslationTableInput: Textual translation table, where every line
    //                                   contains two integers separated by
    //                                   whitespace. The first int is the LOVD2 submitter
    //                                   ID value to translate, the second int is
    //                                   the LOVD3 user ID value to which is being
    //                                   translated.
    // $sFixedCuratorIDInput:          Default user ID, should be integer
    //                                 referring to existing user.
    // $sCuratorTranslationTableInput: Textual translation table, where every line
    //                                 contains two integers separated by
    //                                 whitespace. The first int is the LOVD2 user
    //                                 ID value to translate, the second int is
    //                                 the LOVD3 user ID value to which is being
    //                                 translated.

    $aFixedUserInfos = array(
        array('submitter', 'submitterid_fixed', 'sFixedSubmitterID', $sFixedSubmitterIDInput),
        array('curator', 'curatorid_fixed', 'sFixedCuratorID', $sFixedCuratorIDInput),
    );

    foreach ($aFixedUserInfos as $aFixedUserInfo) {
        list($sIDtype, $sFormField, $sGlobalVar, $sInput) = $aFixedUserInfo;
        if (ctype_digit($sInput)) {
            $GLOBALS[$sGlobalVar] = $sInput;
        } elseif (!empty($sInput)) {
            lovd_errorAdd($sFormField, 'Error: Fixed ' . $sIDtype . ' ID must be numeric.');
        }
    }

    $aTranslationInfos = array(
        array('submitter', 'submitterid_translation', 'aSubmitterTranslationTable',
            $sSubmitterTranslationTableInput),
        array('curator', 'curatorid_translation', 'aCuratorTranslationTable',
            $sCuratorTranslationTableInput),
    );

    foreach ($aTranslationInfos as $aTranslationInfo) {
        list($sIDtype, $sFormField, $sGlobalVar, $sInput) = $aTranslationInfo;
        foreach (explode("\n", $sInput) as $sLine) {
            $sLineClean = trim($sLine);
            if (!empty($sLineClean)) {
                // Parse line as text output from MySQL CLI client. E.g.:
                // | 000123 | 000234 |
                preg_match('/^[\s|]*(\d+)[\s|]+(\d+)[\s|]*$/', $sLineClean, $m);
                if (count($m) != 3 || !ctype_digit($m[1]) || !ctype_digit($m[2])) {
                    // Line not parsable as translation, ignore it.
                    lovd_errorAdd($sFormField, 'Error: ' . $sIDtype . ' ID translation table contains unparsable line: ' . htmlspecialchars($sLine) . '.');
                    continue;
                }
                $GLOBALS[$sGlobalVar][intval($m[1])] = $m[2];
            }
        }
    }
}





function lovd_showConversionForm ($nMaxSizeLOVD, $nMaxSize)
{
    // Print HTML for the form specifying input to be converted.
    // Returns nothing.

    // Show viewlist for searching and selecting a transcript.
    print('<H2>Select transcript</H2>');
    $_DATA = new LOVD_Transcript();
    $_DATA->setRowLink('Transcripts', 'javascript: $("input[name=\'transcriptid\']").val("{{ID}}"); return false;');
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
            ' from those in the LOVD3 application. Below one can define a fixed user ID to ' .
            'set all user IDs in the file to a single value. One can also specify a translation ' .
            'between LOVD2 and LOVD3 IDs. The general strategy for determining the value of user ' .
            'ID fields in the output (created_by, owned_by, edited_by) is as following:<br>' .
            '1. Non-zero value translated by "Curator ID translation table".<br>' .
            '2. Non-zero value replaced by given value in "Fixed curator ID".<br>' .
            '3. Untouched value from corresponding input field.<br>' .
            '4. Zero-valued fields ("0") are treated similarly as in steps 1-3, but using the ' .
            '"Submitter ID translation table" and "Fixed submitter ID" form fields instead.<br>' .
            'Exceptions to above steps are that the "edited_by" fields will not default to the ' .
            'fixed curator ID field value when empty, and that the "owned_by" field is first ' .
            'tried to be filled with the submitter ID and will default to whatever value was ' .
            'determined for the "created_by" field.'),
        array('Fixed submitter ID', '', 'text',
            'submitterid_fixed', 10),
        array('', '', 'note', 'Fixed value for submitter ID (field "ID_submitterid_" in ' .
            'import file). All fields in output that represent a user ID and are zero "0" are ' .
            'given this fixed value instead, if there is no corresponding value in the ' .
            'translation table below.'),
        array('Submitter ID translation table', '', 'textarea', 'submitterid_translation', 20, 6),
        array('', '', 'note', 'Translation table for user IDs. On every line an LOVD2 user ID ' .
            'is expected, followed by an LOVD3 user ID, separated by whitespace. Any fields in ' .
            'the output file will be given a value based on this table, before defaulting to the ' .
            'fixed submitter ID defined above or the value of "ID_submitterid_" in the input ' .
            'file.'),
        array('Fixed curator ID', '', 'text', 'curatorid_fixed', 10),
        array('', '', 'note', 'Fixed value for non-zero user ID fields. All fields in the output ' .
            'that represent a non-submitter user ID will be given this value if they cannot be ' .
            'translated with the table defined below.'),
        array('Curator ID translation table', '', 'textarea', 'curatorid_translation', 20, 6),
        array('', '', 'note', 'Translation table for curator user IDs. On every line an LOVD2 user ID ' .
            'is expected, followed by an LOVD3 user ID, separated by whitespace. Any fields in ' .
            'the output file will be given a value based on this table, before defaulting to the ' .
            'fixed curator ID defined above.'),
        'hr',
        array('', '', 'submit', 'Generate LOVD3 import file'),
    );
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");
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
    lovd_setUserIDSettings($_POST['submitterid_fixed'], $_POST['submitterid_translation'],
        $_POST['curatorid_fixed'], $_POST['curatorid_translation']);

    return !lovd_error();
}





function main ($aFieldLinks, $aSections, $aCustomColLinks)
{
    global $_DB, $_T, $_SETT, $_WARNINGS;

    // Only allow managers or higher to perform conversion.
    lovd_requireAUTH(LEVEL_SUBMITTER);

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
                ' AS t LEFT JOIN ' . TABLE_GENES . ' AS g ON (g.id = t.geneid)  WHERE t.id = ?',
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
    list($aInputHeaders, $aOutputHeaders) = lovd_getHeaders($aData, $aFieldLinks,
        $aSections, $aCustomColLinks);

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

    print('<H3>Conversion log:</H3>
    <TEXTAREA id="header_log" cols="100" rows="10" style="font-family: monospace; 
        white-space: nowrap; overflow: scroll;">' .
        implode("\n", $_WARNINGS) .
    '</TEXTAREA><BR><BR>');

    if (lovd_error()) {
        print('<B>There were fatal errors during conversion:</B>');
        lovd_errorPrint();
    }

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
        '</TEXTAREA><BR>
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
?>
