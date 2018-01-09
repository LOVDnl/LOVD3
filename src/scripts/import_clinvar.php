<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-10-04
 * Modified    : 2018-01-09
 * For LOVD    : 3.0-21
 *
 * Copyright   : 2017-2018 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : M. Kroon <m.kroon@lumc.nl>

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
require_once ROOT_PATH . 'inc-lib-clinvar.php';
require_once ROOT_PATH . 'inc-lib-form.php';
require_once ROOT_PATH . 'inc-lib-init.php';
require_once ROOT_PATH . 'class/object_genome_variants.php';
require_once ROOT_PATH . 'class/object_transcript_variants.php';
require_once ROOT_PATH . 'class/object_users.php';
require_once ROOT_PATH . 'class/progress_bar.php';

// Page title
define('PAGE_TITLE', 'Import ClinVar variants');

// Default HGVS source file
//define('CLINVAR_URL_HGVS', 'ftp://ftp.ncbi.nlm.nih.gov/pub/clinvar/tab_delimited/hgvs4variation.txt.gz');
define('CLINVAR_URL_HGVS', 'file:///home/mkroon/LOVD/data/clinvar/hgvs4variation.txt.gz');

// Default variant summary source file
define('CLINVAR_URL_SUMMARY', 'file:///home/mkroon/LOVD/data/clinvar/variant_summary.txt.gz');

// Name used in custom link to denote link to Clinvar with AlleleID.
define('CLINVAR_ALLELE_LINK_NAME', 'ClinVarAlleleID');

define('LOG_FILENAME', 'clinvar_unparsable_variants.txt');


// Translation from Clinvar clinical significance to LOVD's variant effect. In
// the form of an array where the key is an perl-compatible regex pattern
// matching the Clinvar description in a string and the value is the matching
// variant effect code as described in `$_SETT['var_effect']`.
$aClassTranslations = array(
    '/Pathogenic(?!\/)/'                => 9, // Affects function
    '/Pathogenic\/Likely pathogenic/'   => 7, // Probably affects function
    '/Likely pathogenic/'               => 7, // Probably affects function
    '/drug response/'                   => 8, // Affects function, not ...individual\'s disease...
    '/Affects/'                         => 6, // Affects function, not ...any disease...
    '/Likely benign/'                   => 3, // Probably does not affect function
    '/Benign\/Likely benign/'           => 3, // Probably does not affect function
    '/Benign/'                          => 1, // Does not affect function
    '/Uncertain significance/'          => 5, // Effect unknown
    '/Conflicting interpretations of /' => 5, // Effect unknown
    '/conflicting data from submitters/'=> 5, // Effect unknown
    '/other/'                           => 5, // Effect unknown
    '/risk factor/'                     => 5, // Effect unknown
    '/protective/'                      => 5, // Effect unknown
    '/association/'                     => 5, // Effect unknown
    '/not provided/'                    => 0, // Not classified
    '/-/'                               => 0, // Not classified
);


function getChromosomeForReference ($sReference, $sBuild)
{
    // Return an LOVD chromosome identifier (e.g. "4" or "Y") for the given
    // reference accession and genome build. Return false if it can't be found.

    global $_SETT;

    // Get mapping from NCBI sequences (e.g. "NC_001") to chromosome name (e.g. "X").
    static $aChromosomeMap;
    if (!isset($aChromosomeMap)) {
        if (!isset($_SETT['human_builds'][$sBuild])) {
            // No mapping known for this build.
            return false;
        }
        $aChromosomeMap = array_flip($_SETT['human_builds'][$sBuild]['ncbi_sequences']);
    }

    return isset($aChromosomeMap[$sReference])? $aChromosomeMap[$sReference] : false;
}





function getLOVDVariantsByAlleleID(&$aVarAlleleMap, $nClinvarUserID)
{
    // Return current state of Clinvar variants in LOVD as an associative
    // array with Clinvar's AlleleID as key and an array of several fields of
    // LOVD's VOG and VOT tables as value. Specifically:
    // $aVarAlleleMap[AlleleID] = array(
    //     'id' => vog.id
    //     'dna' => vog.`VariantOnGenome/DNA`
    //     'reference' => vog.`VariantOnGenome/Reference`
    //     'cds' => array(
    //         vot.transcriptid => vot.`VariantOnTranscript/DNA`,
    //         vot.transcriptid => vot.`VariantOnTranscript/DNA`,
    //         ...
    //     )
    // )
    // Note: the array is accessed by reference and modified in-place.

    global $_DB;
    $aVarAlleleMap = array();

    // Query all records linked to a special ClinVar user.
    $sLOVDClinVarQuery = <<<QUERY
SELECT
  vog.id,
  vog.effectid,
  vog.`VariantOnGenome/Reference` AS reference,
  vog.`VariantOnGenome/DNA` AS dna,
  GROUP_CONCAT(vot.transcriptid SEPARATOR ';') AS transcripts,
  GROUP_CONCAT(vot.`VariantOnTranscript/DNA` SEPARATOR '|') AS cds_descriptions
FROM %s AS vog
  LEFT OUTER JOIN %s AS vot ON vog.id = vot.id
WHERE vog.owned_by = '%s'
GROUP BY vog.id;
QUERY;
    $oResult = $_DB->query(sprintf($sLOVDClinVarQuery, TABLE_VARIANTS,
        TABLE_VARIANTS_ON_TRANSCRIPTS, $nClinvarUserID));

    // Store query result indexed by ClinVar AlleleID.
    // Fixme: decide when to save memory by using numerical indexed arrays.
    while (($aRow = $oResult->fetchAssoc()) !== false) {
        if (preg_match('/{' . CLINVAR_ALLELE_LINK_NAME . ':([0-9]+)}/', $aRow['reference'],
            $aMatches)) {
            if ($aRow['transcripts'] != '') {
                $aRow['cds'] = array_combine(explode(';', $aRow['transcripts']),
                                             explode('|', $aRow['cds_descriptions']));
            }
            unset($aRow['cds_descriptions'], $aRow['transcripts']);
            $aVarAlleleMap[$aMatches[1]] = $aRow;
        }
        // Fixme: handle situation where variant is owned by ClinVar user, but has no
        //        AlleleID in its reference field.
    }
}





function main ()
{
    // Main entry point to Clinvar import script. This function guides the
    // process of importing variants from Clinvar.
    global $_T, $aClassTranslations;

    lovd_requireAUTH(LEVEL_MANAGER);

    if (ACTION == 'download_unparsable') {
        $sFilePath = join('/', array(sys_get_temp_dir(), LOG_FILENAME));

        if (!is_readable($sFilePath)) {
            $_T->printHeader();
            lovd_showInfoTable('Log file unavailable: ' . $sFilePath, 'stop');
            $_T->printFooter();
            exit;
        }

        header('Content-Disposition: attachment; filename="' . LOG_FILENAME . '"');
        header('Pragma: public');
        die(file_get_contents($sFilePath));
    }

    // Print HTML header/title and submission form.
    $_T->printHeader(true);
    $_T->printTitle();
    $nClinvarUserID = !isset($_REQUEST['user']) || $_REQUEST['user'] == ''? '' :
        intval($_REQUEST['user']);
    $bDryRun = isset($_REQUEST['dry_run']) || ACTION != 'import';
    $sHGVSFileURL = isset($_REQUEST['url_hgvs'])? $_REQUEST['url_hgvs'] : CLINVAR_URL_HGVS;
    $sSummaryFileURL = isset($_REQUEST['url_summary'])? $_REQUEST['url_summary'] :
        CLINVAR_URL_SUMMARY;
    $bError = handleClinvarImportForm($nClinvarUserID, $sHGVSFileURL, $sSummaryFileURL, $bDryRun);


    if (!ACTION || $bError) {
        // Form is not submitted or submitted with invalid values, do not
        // start processing.
        $_T->printFooter();
        return;
    }

    // Note: building $aVarAlleleMap takes a lot of space.
    ini_set('memory_limit', '512M');
    set_time_limit(0);

    // Get current state of Clinvar variants in LOVD database.
    getLOVDVariantsByAlleleID($aVarAlleleMap, $nClinvarUserID);

    // Initialize stats.
    $aStats = array(
        'existing_clinvar_vars' => count($aVarAlleleMap),
        'removed_seq' => 0,
        'get_variant_info_fail' => 0,
        'vog_new' => 0,
        'vog_updated' => 0,
        'vog_no_update_needed' => 0,
        'vog_unknown_reference' => 0,
        'vot_new' => 0,
        'vot_updated' => 0,
        'vot_no_update_needed' => 0,
        'vot_unknown_allele' => 0,
        'vot_unknown_transcript' => 0,
        'get_variant_info_fail_records' => array(),
    );

    // Loop through Clinvar HGVS file to find genomic variant descriptions.
    print('<HR><H3>Genomic variants</H3>');
    $oHGVSFileGenome = new ClinvarFile($sHGVSFileURL, true, 15, 7.27);
    while (($aData = $oHGVSFileGenome->fetchRecord()) !== false) {
        if ($aData['Assembly'] != 'GRCh37' || $aData['AlleleID'] == '-1') {
            // Cannot handle record.
            continue;
        }

        // Split and normalize full variant description.
        list($sReference,
             $sDescription,
             $aPositions) = readClinvarVariant($aData['NucleotideExpression'], $aStats);
        $sChrom = getChromosomeForReference($sReference, 'hg19');
        if ($sChrom !== false) {
            // Try to store current genomic variant.
            processVOGRecord($aData['AlleleID'], $sChrom, $sDescription, $aPositions,
                $nClinvarUserID, $aVarAlleleMap, $aStats, $bDryRun);
        } else {
            $aStats['vog_unknown_reference'] += 1;
        }
    }

    // Refresh state of Clinvar variants in LOVD database to include those
    // added in the first pass of the Clinvar file.
    getLOVDVariantsByAlleleID($aVarAlleleMap, $nClinvarUserID);

    // Loop a second time through the CLinvar file to find transcript variant
    // descriptions.
    print('<HR><H3>Transcript variants</H3>');
    $oHGVSFileTranscript = new ClinvarFile($sHGVSFileURL, true, 15, 7.27);
    while (($aData = $oHGVSFileTranscript->fetchRecord()) !== false) {
        if (strpos($aData['NucleotideChange'], 'c.') === false || $aData['AlleleID'] == '-1') {
            // Cannot handle record.
            continue;
        }

        // Split and normalize full variant description.
        list($sReference,
            $sDescription,
            $aPositions) = readClinvarVariant($aData['NucleotideExpression'], $aStats);
        // Try to store current transcript variant.
        processVOTRecord($aData['AlleleID'], $sReference, $sDescription, $aPositions,
            $aVarAlleleMap, $aStats, $bDryRun);
    }

    // Refresh state of Clinvar variants in LOVD database to include those
    // added in the first pass of the Clinvar file.
    getLOVDVariantsByAlleleID($aVarAlleleMap, $nClinvarUserID);

    print('<HR><H3>Variant effects</H3>');
    $oSummaryFile = new ClinvarFile($sSummaryFileURL, true);
    while (($aData = $oSummaryFile->fetchRecord()) !== false) {
        if ($aData['Assembly'] != 'GRCh37' || $aData['#AlleleID'] == '-1') {
            // Cannot handle record.
            continue;
        }

        $bProcessed = false;
        foreach ($aClassTranslations as $sPattern => $nVarEffect) {
            if (preg_match($sPattern, $aData['ClinicalSignificance'])) {
                processSummaryRecord($aData['#AlleleID'], $nVarEffect,
                    $aData['ClinicalSignificance'], $aVarAlleleMap, $bDryRun);
                $bProcessed = true;
                continue;
            }
        }

        if (!$bProcessed) {
            // Could not determine Clinvar's classification, store as "not
            // classified".
            processSummaryRecord($aData['#AlleleID'], 0, $aData['ClinicalSignificance'],
                $aVarAlleleMap, $bDryRun);
        }
    }

    // Print processing stats and quit.
    print('<HR><H3>Import statistics</H3>');
    print_stats($aStats);
    print('<BR><A href="' . $_SERVER['PHP_SELF'] .
        '?download_unparsable">Download list of unparsable variants</A>');
    $_T->printFooter();
}





function handleClinvarImportForm($nClinvarUserID, $sHGVSURL, $sSummaryURL, $bDryRun)
{
    // Print HTML for form, containing adjustable parameters for:
    // $nClinvarUserID  User ID used for owned_by field of genomic variants.
    // $sHGVSURL        URL of Clinvar HGVS file.
    // $sSummaryURL     URL of Clinvar variant summary file.
    // $bDryRun         Boolean flag for preventing changes to the database.
    // Returns false if form was submitted with invalid values.
    global $_DB;

    $bError = false;
    $sUserQuery = 'SELECT count(id) FROM ' . TABLE_USERS . ' WHERE id=?';
    if (ACTION && (empty($nClinvarUserID) ||
            !$_DB->query($sUserQuery, array($nClinvarUserID))->fetchColumn())) {
        // Form submitted, but invalid user ID.
        lovd_errorAdd('user', 'Invalid user ID provided.');
        lovd_errorPrint();

        // Remember error manually, as lovd_errorPrint() removes it.
        $bError = true;
    }

    $_DATA = new LOVD_User();
    $_DATA->setRowLink('Users_clinvar_import',
        'javascript: $("input[name=\'user\']").val("{{ID}}"); return false;');
    $aOptions = array('cols_to_skip' => array('orcid_id_', 'country_', 'curates', 'status_',
        'last_login_', 'created_date_', 'level_'));
    $_GET['page_size'] = 10;
    $_GET['search_username'] = 'clinvar';

    print('<B>Click on a username below to select it</B>');
    $_DATA->viewList('Users_clinvar_import', $aOptions);
?>
    <FORM action="<?=CURRENT_PATH?>?import" method="POST">
        <TABLE>
            <TR><TD valign="top"><B>Import as user:</B></TD>
                <TD><input type="text" name="user" size="10" value="<?php echo strval($nClinvarUserID); ?>" /></TD>
            </TR>
            <TR><TD><B>Clinvar URL (gzipped hgvs4variation file):</B></TD>
                <TD><input type="text" name="url_hgvs" size="50" value="<?php echo $sHGVSURL; ?>" /></TD>
            </TR>
            <TR><TD><B>Clinvar URL (gzipped variant summary file):</B></TD>
                <TD><input type="text" name="url_summary" size="50" value="<?php echo $sSummaryURL; ?>" /></TD>
            </TR>
            <TR><TD><B>Genome build:</B></TD>
                <TD>hg19</TD>
            </TR>
            <TR><TD><B>Dry run (no changes to database):</B></TD>
                <TD><input type="checkbox" name="dry_run" <?php echo ($bDryRun)? 'checked' : ''; ?> /></TD>
            </TR>
            <TR><TD><input type="submit" value="import" /></TD>
                <TD></TD>
            </TR>
        </TABLE>
    </FORM>
<?php
    return $bError;
}





function readClinvarVariant($sVar, &$aStats)
{
    // Given full variant description, return the reference accession, a
    // normalized DNA description and positions returned from
    // lovd_getVariantInfo(). The positions are false when $sVar is not
    // valid HGVS.

    // Split variant into reference name and genomic description.
    if ((count($aParts = explode(':', $sVar)) == 2)) {
        list($sReference, $sDescription) = $aParts;
    } else {
        return array($sVar, null, false);
    }

    $sNCBITranscriptID = '';
    if (in_array(substr($sReference, 0, 3), array('NM_', 'NR_', 'XM_', 'XR_'))) {
        $sNCBITranscriptID = $sReference;
    }

    // Check HGVS description.
    $aPositions = lovd_getVariantInfo($sDescription, $sNCBITranscriptID);
    if ($aPositions === false) {
        $aStats['get_variant_info_fail'] += 1;
        $aStats['get_variant_info_fail_records'][] = $sVar;
    }

    // Normalize by removing mentions of deleted or duplicated sequences (e.g.
    // convert "...delX..." descriptions to "...del...").
    $sDescriptionNorm = $sDescription;
    if (preg_match('/^(.+(del|dup))([ACGT]+|[0-9]+)(.*)$/', $sDescription, $aMatches)) {
        $sDescriptionNorm = $aMatches[1] . $aMatches[4];
        $aStats['removed_seq'] += 1;
    }

    return array($sReference, $sDescriptionNorm, $aPositions);
}




function print_stats(&$aStats) {
    // Print HMTL table with statistics gathered during parsing.
    $aStatDescriptions = array(
        'Pre-existing Clinvar variants in LOVD' => $aStats['existing_clinvar_vars'],
        'Removed deleted or duplicated sequence from HGVS' => $aStats['removed_seq'],
        'Failed to parse HGVS' => $aStats['get_variant_info_fail'],
        'Genomic variants newly added' => $aStats['vog_new'],
        'Genomic variants already known (updated)' => $aStats['vog_updated'],
        'Genomic variants already known (no update needed)' => $aStats['vog_no_update_needed'],
        'Genomic variants with unknown reference' => $aStats['vog_unknown_reference'],
        'Transcript variants newly added' => $aStats['vot_new'],
        'Transcript variants already known (updated)' => $aStats['vot_updated'],
        'Transcript variants already known (no update needed)' => $aStats['vot_no_update_needed'],
        'Transcript variants unlinkable to genomic variant' => $aStats['vot_unknown_allele'],
        'Transcript variants for unknown transcript' => $aStats['vot_unknown_transcript'],
    );

    $sStatsHTML = '<TABLE>' . "\n";
    foreach ($aStatDescriptions as $sLabel => $nValue) {
        $sStatsHTML .= '<TR><TD><B>' . $sLabel . '</B></TD><TD>' . strval($nValue) .
            '</TD></TR>' . "\n";
    }
    print($sStatsHTML . '</TABLE>' . "\n");

    $sLogfile = join('/', array(sys_get_temp_dir(), LOG_FILENAME));
    $oLogFile = fopen($sLogfile, 'w');
    foreach ($aStats['get_variant_info_fail_records'] as $sVar) {
        fwrite($oLogFile, $sVar . PHP_EOL);
    }
    fclose($oLogFile);
}





function processSummaryRecord($sAlleleID, $nVarEffect, $sCVEffect, &$aVarAlleleMap, $bDryRun)
{
    // Translate Clinvar's clinical significance values to LOVD's variant
    // effect and store them. Clinvar's original values are stored in
    // `VariantOnTranscript/Classification`.
    // Note: reported effect is always '0' (i.e. 'Not classified') meaning the
    //       data from Clinvar is assumed to be curated.

    static $oVar, $oVOT;
    if (!isset($oVar)) {
        // Create objects to run updateEntry() on.
        $oVar = new LOVD_GenomeVariant();
        $oVOT = new LOVD_TranscriptVariant();
    }

    $sEffectCodes = '0' . strval($nVarEffect);
    if (!$bDryRun && isset($aVarAlleleMap[$sAlleleID]) &&
        $aVarAlleleMap[$sAlleleID]['effectid'] != $sEffectCodes) {
        // Overwrite effectID in database.
        $oVar->updateEntry($aVarAlleleMap[$sAlleleID]['id'], array('effectid' => $sEffectCodes));

        if (isset($aVarAlleleMap[$sAlleleID]['cds'])) {
            // Store Clinvar's original clinical significance value in
            // `VariantOnTranscript/Classification`.
            $aData = array('VariantOnTranscript/Classification' => 'CV: ' . $sCVEffect);
            foreach (array_keys($aVarAlleleMap[$sAlleleID]['cds']) as $nTranscript) {
                $oVOT->updateEntry($aVarAlleleMap[$sAlleleID]['id'] . '|' . $nTranscript, $aData);
            }
        }
    }
}





function processVOGRecord($sAlleleID, $sChrom, $sDescription, $aPositions, $nClinvarUserID,
                          &$aVarAlleleMap, &$aStats, $bDryRun)
{
    // Try to store or update a genomic variant in the database.

    if ($aPositions === false && !preg_match('/\[.*;.*\]/', $sDescription)) {
        // Non-parsable description and not describing a series of changes.
        // Note: since LOVD cannot parse descriptions with a series of changes,
        // allow anything that resembles variants like "g.[change1;change2]".
        return;
    } elseif ($aPositions === false) {
        // Set postions to 0 for unparsable series variants like "g.[change1;change2]".
        $aPositions = array(
            'position_start' => 0,
            'position_end' => 0
        );
    }

    static $oVar, $aVarDefaults;
    if (!isset($oVar)) {
        // Create object to run insertEntry() on.
        $oVar = new LOVD_GenomeVariant();

        // Default field values for new genomic variants.
        $aCustomFields = $oVar->buildFields();
        $aVarDefaults = array_merge(
            array(
                'allele' => '0',
                'effectid' => '0',
                'type' => '',
                'owned_by' => $nClinvarUserID,
                'statusid' => STATUS_OK,
                'created_by' => $nClinvarUserID,
                'created_date' => date('Y-m-d H:i:s')
            ),
            // Add custom fields with empty values.
            array_combine($aCustomFields, array_pad(array(), count($aCustomFields), ''))
        );
    }

    // Field values specific to current variant.
    $aVarValues = array(
        // Fixme: log truncated descriptions (over 150 chars)?
        'VariantOnGenome/DNA' => substr($sDescription, 0, 150),
        'chromosome' => $sChrom,
        'position_g_start' => $aPositions['position_start'],
        'position_g_end' => $aPositions['position_end'],
    );

    if (isset($aVarAlleleMap[$sAlleleID])) {
        if ($sDescription != $aVarAlleleMap[$sAlleleID]['dna']) {
            // Variant exists in DB, but with different description: update it.
            if (!$bDryRun) {
                $oVar->updateEntry($aVarAlleleMap[$sAlleleID]['id'], $aVarValues);
            }
            $aStats['vog_updated'] += 1;
        } else {
            // AlleleID exists in DB with correct description. No changes needed.
            $aStats['vog_no_update_needed'] += 1;
        }
    } else {
        // Unseen AlleleID. Insert new record.

        // Set Clinvar's AlleleID in reference field and omplete missing field
        // values with defaults.
        $aVarValues['VariantOnGenome/Reference'] = '{' . CLINVAR_ALLELE_LINK_NAME . ':' .
            $sAlleleID . '}';
        $aVarValues = array_merge($aVarDefaults, $aVarValues);
        if (!$bDryRun) {
            $oVar->insertEntry($aVarValues);
        }
        $aStats['vog_new'] += 1;
    }
}






function processVOTRecord($sAlleleID, $sReference, $sDescription, $aPositions, &$aVarAlleleMap,
                          &$aStats, $bDryRun)
{
    // Try to store or update a transcript variant in the database.

    global $_DB;

    if (!isset($aVarAlleleMap[$sAlleleID])) {
        // Unkown ClinVar AlleleID and no VOG record available.
        $aStats['vot_unknown_allele'] += 1;
        return;
    }

    // Setup cache for looking up transcripts.
    static $aTranscripts;
    if (!isset($aTranscripts)) {
        $sTranscriptQuery = 'SELECT id_ncbi, id FROM ' . TABLE_TRANSCRIPTS . ';';
        $aTranscripts = $_DB->query($sTranscriptQuery)->fetchAllCombine();
    }

    if (!isset($aTranscripts[$sReference])) {
        // Transcript not known to LOVD.
        $aStats['vot_unknown_transcript'] += 1;
        return;
    }

    if ($aPositions === false && !preg_match('/\[.*;.*\]/', $sDescription)) {
        // Non-parsable description and not describing a series of changes.
        // Note: since LOVD cannot parse descriptions with a series of changes,
        // allow anything that resembles variants like "c.[change1;change2]".
        return;
    } elseif ($aPositions === false) {
        // Set postions to 0 for unparsable series variants like "c.[change1;change2]".
        $aPositions = array(
            'position_start' => 0,
            'position_end' => 0,
            'position_start_intron' => 0,
            'position_end_intron' => 0
        );
    }

    static $oVOT, $aVarDefaults;
    if (!isset($oVOT)) {
        // Initialize object to run insertEntry() or updateEntry() on.
        $oVOT = new LOVD_TranscriptVariant();

        // Set default values for new entries.
        $aTableFields = lovd_getColumnList(TABLE_VARIANTS_ON_TRANSCRIPTS);
        $aVarDefaults = array_merge(
            // Add table fields with empty values.
            array_combine($aTableFields, array_pad(array(), count($aTableFields), '')),
            array(
                'effectid' => '0'
            )
        );
    }

    // Field values specific to current variant.
    $aVarValues = array(
        // Fixme: log truncated descriptions (over 100 chars)?
        'VariantOnTranscript/DNA' => substr($sDescription, 0, 100),
        'id' => $aVarAlleleMap[$sAlleleID]['id'],
        'transcriptid' => $aTranscripts[$sReference],
        'position_c_start' => $aPositions['position_start'],
        'position_c_end' => $aPositions['position_end'],
        'position_c_start_intron' => $aPositions['position_start_intron'],
        'position_c_end_intron' => $aPositions['position_end_intron'],
    );

    if (isset($aVarAlleleMap[$sAlleleID]['cds'][$aTranscripts[$sReference]])) {
        if ($sDescription != $aVarAlleleMap[$sAlleleID]['cds'][$aTranscripts[$sReference]]) {
            // Variant exists in DB, but with different description: update it.
            if (!$bDryRun) {
                $oVOT->updateEntry($aVarValues['id'] . '|' . $aVarValues['transcriptid'],
                                   $aVarValues);
            }
            $aStats['vot_updated'] += 1;
        } else {
            // AlleleID exists in DB with correct description. No changes needed.
            $aStats['vot_no_update_needed'] += 1;
        }
    } else {
        // Unseen VOT record. Insert as new.
        $aVarValues = array_merge($aVarDefaults, $aVarValues);
        if (!$bDryRun) {
            $oVOT->insertEntry($aVarValues, array());
        }

        // Add new description to variant map, as some AlleleID-transcriptid
        // combinations may occur multiple times.
        $aVarAlleleMap[$sAlleleID]['cds'][$aTranscripts[$sReference]] = $sDescription;
        $aStats['vot_new'] += 1;
    }
}





main();

?>

    </BODY>
</HTML>
