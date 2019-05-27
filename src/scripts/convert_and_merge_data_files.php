<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2014-11-28
 * Modified    : 2018-12-06
 * For LOVD+   : 3.0-18
 *
 * Copyright   : 2004-2018 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Anthony Marty <anthony.marty@unimelb.edu.au>
 *               Juny Kesumadewi <juny.kesumadewi@unimelb.edu.au>
 *
 *************/

//define('ROOT_PATH', '../');
define('ROOT_PATH', str_replace('\\', '/', dirname(__FILE__) . '/../'));
define('FORMAT_ALLOW_TEXTPLAIN', true);

$_GET['format'] = 'text/plain';
// To prevent notices when running inc-init.php.
$_SERVER = array_merge($_SERVER, array(
    'HTTP_HOST' => 'localhost',
    'REQUEST_URI' => __FILE__,
    'QUERY_STRING' => '',
    'REQUEST_METHOD' => 'GET',
));

require ROOT_PATH . 'inc-init.php';
require ROOT_PATH . 'inc-lib-genes.php';
// 128MB was not enough for a 100MB file. We're already no longer using file(), now we're using fgets().
// But still, loading all the gene and transcript data, uses too much memory. After some 18000 lines, the thing dies.
// Setting to 4GB, but still maybe we'll run into problems.
ini_set('memory_limit', '4294967296'); // Put in bytes to avoid some issues with some environments.

// But we don't care about your session (in fact, it locks the whole LOVD if we keep this page running).
session_write_close();
set_time_limit(0);
ignore_user_abort(true);

// Define and verify settings.
$bCron = (empty($_SERVER['REMOTE_ADDR']) && empty($_SERVER['TERM']));
define('VERBOSITY', $_INSTANCE_CONFIG['conversion']['verbosity_' . ($bCron? 'cron' : 'other')]);





// Initialize curl connection.
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Set proxy.
if ($_CONF['proxy_host']) {
    curl_setopt($ch, CURLOPT_PROXY, 'https://' . $_CONF['proxy_host'] . ':' . $_CONF['proxy_port']);
}
if (!empty($_CONF['proxy_username']) && !empty($_CONF['proxy_password'])) {
    curl_setopt($ch, CURLOPT_PROXYUSERPWD, $_CONF['proxy_username'] . ':' . $_CONF['proxy_password']);
}

function mutalyzer_getTranscriptsAndInfo ($ref, $gene)
{
    global $ch, $_CONF;

    $sUrl = str_replace('/services', '', $_CONF['mutalyzer_soap_url']) . '/json/getTranscriptsAndInfo?genomicReference=' . $ref . '&geneName=' . $gene;
    curl_setopt($ch, CURLOPT_URL, $sUrl);

    return curl_exec($ch);
}

function mutalyzer_numberConversion ($build, $variant)
{
    global $ch, $_CONF;

    $sUrl = str_replace('/services', '', $_CONF['mutalyzer_soap_url']) . '/json/numberConversion?build=' . $build . '&variant=' . $variant;
    curl_setopt($ch, CURLOPT_URL, $sUrl);

    return curl_exec($ch);
}

function mutalyzer_runMutalyzer ($variant)
{
    global $ch, $_CONF;

    $sUrl = str_replace('/services', '', $_CONF['mutalyzer_soap_url']) . '/json/runMutalyzerLight?variant=' . $variant;
    curl_setopt($ch, CURLOPT_URL, $sUrl);

    return curl_exec($ch);
}





// This script will be called from localhost by a cron job.

// Define the array of suffixes for the files names expected.
$aSuffixes = $_INSTANCE_CONFIG['conversion']['suffixes'];

// Define list of genes to ignore, because they can't be found by the HGNC.
// LOC* genes are always ignored, because they never work (HGNC doesn't know them).
$aGenesToIgnore = $_ADAPTER->prepareGenesToIgnore();

// Define list of gene aliases. Genes not mentioned in here, are searched for in the database. If not found,
// HGNC will be queried and gene will be added. If the symbols don't match, we'll get a duplicate key error.
// Insert those genes here.
$aGeneAliases = $_ADAPTER->prepareGeneAliases();





// Define list of columns that we are recognizing.
$aColumnMappings = $_ADAPTER->prepareMappings();
$aFrequencyColumns = array(); // Which columns handle frequencies and need to be checked for non-float values?
foreach ($aColumnMappings as $sCol) {
    if (strpos($sCol, '/Frequency') !== false) {
        $aFrequencyColumns[] = $sCol;
    }
}

// These columns will be taken out of $aVariant and stored as the VOG data.
// This array is also used to build the LOVD file.
$aColumnsForVOG = array(
    'id',
    'allele',
    'effectid',
    'chromosome',
    'position_g_start',
    'position_g_end',
    'type',
    'mapping_flags',
    'average_frequency',
    'owned_by',
    'statusid',
    'created_by',
    'VariantOnGenome/DBID',
);
// These columns will be taken out of $aVariant and stored as the VOT data.
// This array is also used to build the LOVD file.
$aColumnsForVOT = array(
    'id',
    'transcriptid',
    'effectid',
    'position_c_start',
    'position_c_start_intron',
    'position_c_end',
    'position_c_end_intron',
);

// Default values.
$aDefaultValues = array(
    'effectid' => $_SETT['var_effect_default'],
    'mapping_flags' => '0',
//    'owned_by' => 0, // '0' is not a valid value, because "LOVD" is removed from the selection list. When left empty, it will default to the user running LOVD, though.
    'statusid' => STATUS_HIDDEN,
    'created_by' => 0,
);







$nMutalyzerRetries = 5; // The number of times we retry the Mutalyzer API call if the connection fails.
$nFilesBeingMerged = 0; // We're counting how many files are being merged at the time, because we don't want to stress the system too much.
$nMaxFilesBeingMerged = 5; // We're allowing only five processes working concurrently on merging files (or so many failed attempts that have not been cleaned up).
$aFiles = array(); // array(ID => array(files), ...);





function lovd_handleAnnotationError (&$aVariant, $sErrorMsg)
{
    // Function that prints error messages to the screen if they occur, and optionally halts.
    global $fError, $nAnnotationErrors, $nLine, $sFileError, $_INSTANCE_CONFIG;

    $nAnnotationErrors++;

    $sLineErrorMsg = 'LINE ' . $nLine . ' - VariantOnTranscript data ' .
        ($_INSTANCE_CONFIG['conversion']['annotation_error_drops_line']? 'dropped' : 'error') . ': ' . $sErrorMsg . "\n";
    if ($fError) {
        fwrite($fError, $sLineErrorMsg);
    }
    lovd_printIfVerbose(VERBOSITY_LOW, $sLineErrorMsg);

    $bExitOnError = $_INSTANCE_CONFIG['conversion']['annotation_error_exits'];
    if ($bExitOnError) {
        lovd_printIfVerbose(VERBOSITY_LOW, "ERROR: Please update your data and re-run this script.\n");
        exit;
    }

    // We want to stop the script if there are too many lines of data with annotations issues.
    // We want users to check their data before they continue.
    if ($nAnnotationErrors >= $_INSTANCE_CONFIG['conversion']['annotation_error_max_allowed']) {
        $sFileMessage = (filesize($sFileError) === 0? '' : 'Please check details of ' .
            ($_INSTANCE_CONFIG['conversion']['annotation_error_drops_line']? 'dropped' : 'errors in') . ' annotation data in ' . $sFileError . "\n");
        lovd_printIfVerbose(VERBOSITY_LOW, "ERROR: Script cannot continue because this file has too many lines of annotation data that this script cannot handle.\n"
            . $nAnnotationErrors . " lines of transcripts data was dropped.\nPlease update your data and re-run this script.\n"
            . $sFileMessage);
        exit;
    }

    // Otherwise, keep the VariantOnGenome data only, and add some data in Remarks.
    if (isset($aVariant['VariantOnGenome/Remarks'])) {
        $aVariant['VariantOnGenome/Remarks'] .= (!$aVariant['VariantOnGenome/Remarks']? '' : "\n") . $sErrorMsg;
    }

    return $nAnnotationErrors;
}





function lovd_getVariantDescription (&$aVariant, $sRef, $sAlt)
{
    // Constructs a variant description from $sRef and $sAlt and adds it to $aVariant in a new 'VariantOnGenome/DNA' key.
    // The 'position_g_start' and 'position_g_end' keys in $aVariant are adjusted accordingly and a 'type' key is added too.
    // The numbering scheme is either g. or m. and depends on the 'chromosome' key in $aVariant.
    // Requires:
    //   $aVariant['chromosome']
    //   $aVariant['position']
    // Adds:
    //   $aVariant['position_g_start']
    //   $aVariant['position_g_end']
    //   $aVariant['type']
    //   $aVariant['VariantOnGenome/DNA']

    // Make all bases uppercase.
    $sRef = strtoupper($sRef);
    $sAlt = strtoupper($sAlt);

    // Clear out empty REF and ALTs. This is not allowed in the VCF specs,
    //  but some tools create them nonetheless.
    foreach (array('sRef', 'sAlt') as $var) {
        if (in_array($$var, array('.', '-'))) {
            $$var = '';
        }
    }

    // Use the right prefix for the numbering scheme.
    $sHGVSPrefix = 'g.';
    if ($aVariant['chromosome'] == 'M') {
        $sHGVSPrefix = 'm.';
    }

    // Even substitutions are sometimes mentioned as longer Refs and Alts, so we'll always need to isolate the actual difference.
    $aVariant['position_g_start'] = $aVariant['position'];
    $aVariant['position_g_end'] = $aVariant['position'] + strlen($sRef) - 1;

    // Save original values before we edit them.
    $sRefOriginal = $sRef;
    $sAltOriginal = $sAlt;

    // 'Eat' letters from either end - first left, then right - to isolate the difference.
    while (strlen($sRef) > 0 && strlen($sAlt) > 0 && $sRef{0} == $sAlt{0}) {
        $sRef = substr($sRef, 1);
        $sAlt = substr($sAlt, 1);
        $aVariant['position_g_start'] ++;
    }
    while (strlen($sRef) > 0 && strlen($sAlt) > 0 && $sRef[strlen($sRef) - 1] == $sAlt[strlen($sAlt) - 1]) {
        $sRef = substr($sRef, 0, -1);
        $sAlt = substr($sAlt, 0, -1);
        $aVariant['position_g_end'] --;
    }

    // Substitution, or something else?
    if (strlen($sRef) == 1 && strlen($sAlt) == 1) {
        // Substitutions.
        $aVariant['type'] = 'subst';
        $aVariant['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position_g_start'] . $sRef . '>' . $sAlt;
    } else {
        // Insertions/duplications, deletions, inversions, indels.

        // Now find out the variant type.
        if (strlen($sRef) > 0 && strlen($sAlt) == 0) {
            // Deletion.
            $aVariant['type'] = 'del';
            if ($aVariant['position_g_start'] == $aVariant['position_g_end']) {
                $aVariant['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position_g_start'] . 'del';
            } else {
                $aVariant['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position_g_start'] . '_' . $aVariant['position_g_end'] . 'del';
            }
        } elseif (strlen($sAlt) > 0 && strlen($sRef) == 0) {
            // Something has been added... could be an insertion or a duplication.
            if ($sRefOriginal && substr($sAltOriginal, strrpos($sAltOriginal, $sAlt) - strlen($sAlt), strlen($sAlt)) == $sAlt) {
                // Duplicaton (not allowed when REF was empty from the start).
                $aVariant['type'] = 'dup';
                $aVariant['position_g_start'] -= strlen($sAlt);
                if ($aVariant['position_g_start'] == $aVariant['position_g_end']) {
                    $aVariant['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position_g_start'] . 'dup';
                } else {
                    $aVariant['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position_g_start'] . '_' . $aVariant['position_g_end'] . 'dup';
                }
            } else {
                // Insertion.
                $aVariant['type'] = 'ins';
                // Exchange g_start and g_end; after the 'letter eating' we did, start is actually end + 1!
                $aVariant['position_g_start'] --;
                $aVariant['position_g_end'] ++;
                $aVariant['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position_g_start'] . '_' . $aVariant['position_g_end'] . 'ins' . $sAlt;
            }
        } elseif ($sRef == strrev(str_replace(array('a', 'c', 'g', 't'), array('T', 'G', 'C', 'A'), strtolower($sAlt)))) {
            // Inversion.
            $aVariant['type'] = 'inv';
            $aVariant['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position_g_start'] . '_' . $aVariant['position_g_end'] . 'inv';
        } else {
            // Deletion/insertion.
            $aVariant['type'] = 'delins';
            if ($aVariant['position_g_start'] == $aVariant['position_g_end']) {
                $aVariant['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position_g_start'] . 'delins' . $sAlt;
            } else {
                $aVariant['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position_g_start'] . '_' . $aVariant['position_g_end'] . 'delins' . $sAlt;
            }
        }
    }
}





// FIXME: Replace by lovd_getVariantInfo()?
function lovd_getVariantPosition ($sVariant, $aTranscript = array())
{
    // Constructs an array with the position fields 'start', 'start_intron', 'end', 'end_intron', from the variant description.
    // Whether the input is chromosomal or transcriptome positions, doesn't matter.

    $aReturn = array(
        'start' => 0,
        'start_intron' => 0,
        'end' => 0,
        'end_intron' => 0,
    );

    if (preg_match('/^[cgmn]\.((?:\-|\*)?\d+)([-+]\d+)?(?:[ACGT]>[ACGT]|(?:_((?:\-|\*)?\d+)([-+]\d+)?)?(?:d(?:el(?:ins)?|up)|inv|ins)(?:[ACGT])*|\[[0-9]+\](?:[ACGT]+)?)$/', $sVariant, $aRegs)) {
        foreach (array(1, 3) as $i) {
            if (isset($aRegs[$i]) && $aRegs[$i]{0} == '*') {
                // Position in 3'UTR. Add CDS offset.
                if ($aTranscript && isset($aTranscript['position_c_cds_end'])) {
                    $aRegs[$i] = (int) substr($aRegs[$i], 1) + $aTranscript['position_c_cds_end'];
                } else {
                    // Whatever we'll do, it will be wrong anyway.
                    return $aReturn;
                }
            }
        }

        $aReturn['start'] = (int) $aRegs[1];
        if (isset($aRegs[2]) && $aRegs[2]) {
            $aReturn['start_intron'] = (int) $aRegs[2]; // (int) to get rid of the '+' if it's there.
        }
        if (isset($aRegs[4]) && $aRegs[4]) {
            $aReturn['end_intron'] = (int) $aRegs[4]; // (int) to get rid of the '+' if it's there.
        }
        if (isset($aRegs[3])) {
            $aReturn['end'] = (int) $aRegs[3];
        } else {
            $aReturn['end'] = $aReturn['start'];
            $aReturn['end_intron'] = $aReturn['start_intron'];
        }
    }

    return $aReturn;
}





// Run the "adapter" script for this instance, that will run actions that are meant to be run before anything else is done.
$sInstanceName = strtoupper($_INI['instance']['name']);
$sAdaptersDir = $_ADAPTER->sAdapterPath;
if (!file_exists($sAdaptersDir . 'adapter.' . $sInstanceName . '.php')) {
    $sInstanceName = 'DEFAULT';
}
lovd_printIfVerbose(VERBOSITY_HIGH, '> Running ' . $sInstanceName . ' adapter...' . "\n");
$sCmd = 'php ' . $_ADAPTER->sAdapterPath . '/adapter.' . $sInstanceName . '.php';
passthru($sCmd, $nAdapterResult);
if ($nAdapterResult == EXIT_WARNINGS_OCCURRED) {
    lovd_printIfVerbose(VERBOSITY_LOW, "Adapter completed with warnings.\n");
} elseif ($nAdapterResult !== EXIT_OK) {
    lovd_printIfVerbose(VERBOSITY_LOW, "Adapter Failed\n");
    exit;
}

// Loop through the files in the dir and try and find a meta and data file, that match but have no total data file.
$aFiles = lovd_getFilesFromDir(
    $_INI['paths']['data_files'],
    $_ADAPTER->getInputFilePrefixPattern(),
    array_map('preg_quote', array_values($aSuffixes))
);

if ($aFiles === false) {
    lovd_printIfVerbose(VERBOSITY_LOW, 'Can\'t open directory.' . "\n");
    exit;
}

// Die here, if we have nothing to work with.
if (!$aFiles) {
    lovd_printIfVerbose(VERBOSITY_MEDIUM, 'No files found.' . "\n");
    exit;
}

// Filter the list of files, to see which ones are already complete.
foreach ($aFiles as $sID => $aFileTypes) {
    if (in_array($aSuffixes['total'], $aFileTypes)) {
        // Already merged.
        unset($aFiles[$sID]);
        continue;
    }
}

// Die here, if we have nothing to do anymore.
if (!$aFiles) {
    lovd_printIfVerbose(VERBOSITY_HIGH, 'No files found available for merging.' . "\n");
    exit;
}

// Report incomplete data sets; meta data without variant data, for instance, and data sets still running (maybe split that, if this happens more often).
foreach ($aFiles as $sID => $aFileTypes) {
    if (!in_array($aSuffixes['meta'], $aFileTypes)) {
        // No meta data.
        unset($aFiles[$sID]);
        lovd_printIfVerbose(VERBOSITY_MEDIUM, 'Meta data missing: ' . $sID . "\n");
    }
    if (!in_array($aSuffixes['vep'], $aFileTypes)) {
        // No variant data.
        unset($aFiles[$sID]);
        lovd_printIfVerbose(VERBOSITY_MEDIUM, 'VEP data missing: ' . $sID . "\n");
    }
    if (in_array($aSuffixes['total.tmp'], $aFileTypes)) {
        // Already working on a merge. We count these, because we don't want too many processes in parallel.
        // FIXME: Should we check the timestamp on the file? Remove really old files, so we can continue?
        $nFilesBeingMerged ++;
        unset($aFiles[$sID]);
        lovd_printIfVerbose(VERBOSITY_MEDIUM, 'Already being merged: ' . $sID . "\n");
    }
}

// Report what we have left.
$nFiles = count($aFiles);
if (!$nFiles) {
    lovd_printIfVerbose(VERBOSITY_HIGH, 'No files left to merge.' . "\n");
    exit;
} else {
    lovd_printIfVerbose(VERBOSITY_MEDIUM, str_repeat('-', 60) . "\n" . $nFiles . ' patient' . ($nFiles == 1? '' : 's') . ' with data files ready to be merged.' . "\n");
}

// But don't run, if too many are still active...
if ($nFilesBeingMerged >= $nMaxFilesBeingMerged) {
    lovd_printIfVerbose(VERBOSITY_MEDIUM, 'Too many files being merged at the same time, stopping here.' . "\n");
    exit;
}





// We're simply taking the first one, with the lowest ID (or actually, alphabetically the lowest ID, since we have the file's prefix).
// To make sure that we don't hang if one file is messed up, we'll start parsing them one by one, and the first one with an OK header, we take.
$aFiles = array_keys($aFiles);
sort($aFiles);
define('LOG_EVENT', 'ConvertVEPToLOVD');
require ROOT_PATH . 'inc-lib-actions.php';
flush();
@ob_end_flush(); // Can generate errors on the screen if no buffer found.
foreach ($aFiles as $sFileID) {
    // Try and open the file, check the first line if it conforms to the standard, and start converting.
    lovd_printIfVerbose(VERBOSITY_LOW, 'Working on: ' . $sFileID . "...\n");
    flush();
    $sFileToConvert = $_INI['paths']['data_files'] . '/' . $sFileID . '.' . $aSuffixes['vep'];
    $sFileMeta = $_INI['paths']['data_files'] . '/' . $sFileID . '.' . $aSuffixes['meta'];
    $sFileTmp = $_INI['paths']['data_files'] . '/' . $sFileID . '.' . $aSuffixes['total.tmp'];
    $sFileDone = $_INI['paths']['data_files'] . '/' . $sFileID . '.' . $aSuffixes['total'];
    $sFileError = $_INI['paths']['data_files'] . '/' . $sFileID . '.' . $aSuffixes['error'];

    $fInput = fopen($sFileToConvert, 'r');
    if ($fInput === false) {
        lovd_printIfVerbose(VERBOSITY_LOW, 'Error opening file: ' . $sFileToConvert . ".\n");
        exit;
    }

    // Get the meta data. Prepare creating the output file, based on the meta file.
    // We just add the analysis_status, so the analysis can start directly after importing.
    // T.S: Changed ANALYSIS_STATUS_READY to ANALYSIS_STATUS_READY'
    $aMetaData = file($sFileMeta, FILE_IGNORE_NEW_LINES);
    if (!$aMetaData) {
        lovd_printIfVerbose(VERBOSITY_LOW, 'Error reading out meta data file: ' . $sFileMeta . ".\n");
        continue; // Continue to try the next file.
    }
    foreach ($aMetaData as $nLine => $sLine) {
        if (strpos($sLine, '{{Screening/') !== false) {
            $aMetaData[$nLine]   .= "\t\"{{analysis_statusid}}\"";
            $aMetaData[$nLine+1] .= "\t\"" . 'ANALYSIS_STATUS_READY' . '"';
            break;
        }
    }

    // Isolate the used Screening ID, so we'll connect the variants to the right ID.
    // It could just be 1 always, but this is not a requirement.
    // FIXME: This is quite a lot of code, for something simple as that... Can't we do this in an easier way? More assumptions, less checks?
    $nScreeningID = 0;
    $nMiracleID = 0;

    $_ADAPTER->readMetadata($aMetaData);
    $nScreeningID = $_ADAPTER->aMetadata['Screenings']['id'];

    if (lovd_verifyInstance('leiden')) {
        $nMiracleID = $_ADAPTER->aMetadata['Individuals']['id_miracle'];
        if (!$nScreeningID || !$nMiracleID) {
            lovd_printIfVerbose(VERBOSITY_LOW, 'Error while parsing meta file: Unable to find the Screening ID and/or Miracle ID.' . "\n");
            // Here, we won't try and remove the temp file. We need it for diagnostics, and it will save us from running into the same error over and over again.
            continue; // Continue to try the next file.
        }
    }

    $_ADAPTER->setScriptVars(compact('nScreeningID', 'nMiracleID'));
    $nScreeningID = sprintf('%010d', $nScreeningID);
    lovd_printIfVerbose(VERBOSITY_FULL, 'Isolated Screening ID: ' . $nScreeningID . "...\n");
    flush();





    $sHeaders = fgets($fInput);
    $aHeaders = explode("\t", rtrim($sHeaders, "\r\n"));
    // First line should be headers.
    // $aHeaders = array_map('trim', $aHeaders, array_fill(0, count($aHeaders), '"')); // In case we ever need to trim off quotes.
    $aHeaders = $_ADAPTER->cleanHeaders($aHeaders);
    $nHeaders = count($aHeaders);

    // Check for mandatory headers (needs to be run after the headers have been cleaned).
    foreach ($_ADAPTER->getRequiredHeaderColumns() as $sColumn) {
       if (!in_array($sColumn, $aHeaders, true)) {
            lovd_printIfVerbose(VERBOSITY_MEDIUM, 'Ignoring file, does not conform to format: ' . $sFileToConvert . '. Missing column: ' . $sColumn . ".\n");
            continue 2; // Continue to try the next file.
        }
    }

    // Input headers are OK, so we can start with the output file.
    $fOutput = @fopen($sFileTmp, 'w');
    if (!$fOutput || !fputs($fOutput, implode("\r\n", $aMetaData))) {
        lovd_printIfVerbose(VERBOSITY_LOW, 'Error copying meta file to target: ' . $sFileTmp . ".\n");
        fclose($fOutput);
        continue; // Continue to try the next file.
    }
    fclose($fOutput);

    $fError = @fopen($sFileError, 'w');





    // Now start parsing the file, reading it out line by line, building up the variant data in $aData.
    $dStart = time();
    $aMutalyzerCalls = array(
        'getTranscriptsAndInfo' => 0,
        'numberConversion' => 0,
        'runMutalyzer' => 0,
    );
    $tMutalyzerCalls = 0; // Time spent doing Mutalyzer calls.
    $aData = array(); // 'chr1:1234567C>G' => array(array(genomic_data), array(transcript1), array(transcript2), ...)
    lovd_printIfVerbose(VERBOSITY_LOW, 'Parsing file. Current time: ' . date('Y-m-d H:i:s') . ".\n");
    flush();

    $nLine = 0;
    $sLastVariant = '';
    $aVOT = array();
    $aGenes = array(); // GENE => array(<gene_info_from_database>)
    $aGenesHGNC = array(); // HGNC ID => GENE (used only if we're receiving HGNC IDs).
    $aTranscripts = array(); // NM_000001.1 => array(<transcript_info>)
    $nHGNC = 0; // Count the number of times HGNC is called.
    $tHGNCCalls = 0; // Time spent doing HGNC calls.
    $nMutalyzer = 0; // Count the number of times Mutalyzer is called.
    $nAnnotationErrors = 0; // Count the number of lines we cannot import.

    // Get all the existing genes in one database call.
    $aGenes = $_DB->query('SELECT id, id, name FROM ' . TABLE_GENES)->fetchAllGroupAssoc();

    // If we're receiving the HGNC ID, we'll collect all genes for their HGNC IDs as well. This will be used to help
    //  LOVD+ to handle changed gene symbols.
    if (in_array('id_hgnc', $aColumnMappings)) {
        $aGenesHGNC = $_DB->query('SELECT id_hgnc, id FROM ' . TABLE_GENES . ' WHERE id_hgnc IS NOT NULL')->fetchAllCombine();
    }

    // Get all the existing transcript data in one database call.
    $aTranscripts = $_DB->query('SELECT id_ncbi, id, geneid, id_ncbi, position_c_cds_end, position_g_mrna_start, position_g_mrna_end FROM ' . TABLE_TRANSCRIPTS . ' ORDER BY id_ncbi DESC, id DESC')->fetchAllGroupAssoc();



    // This copies rather large arrays (used to be smaller, per chromosome) into the object.
    // We don't need it, perhaps just store it in the object from the start then?
    $_ADAPTER->setScriptVars(compact('aGenes', 'aTranscripts'));

    // It's usually a big file, and we don't want to use too much memory... so using fgets().
    while ($sLine = fgets($fInput)) {
        $nLine ++;
        $bDropTranscriptData = false;
        if (!trim($sLine) || substr(ltrim($sLine), 0, 1) == '#') {
            continue;
        }

        // We've got a line of data here. Isolate the values.
        $aLine = explode("\t", rtrim($sLine, "\r\n"));
        // The number of columns should be the same as the number of fields.
        // However, less fields may be encountered, if the last fields were empty.
        if (count($aLine) < $nHeaders) {
            $aLine = array_pad($aLine, $nHeaders, '');
        }
        $aLine = array_combine($aHeaders, $aLine);
        $aVariant = array(); // Will contain the mapped, possibly modified, data.
        // $aLine = array_map('trim', $aLine, array_fill(0, count($aLine), '"')); // In case we ever need to trim off quotes.

        // Reformat variant data if extra modification required by different instance of LOVD.
        $aLine = $_ADAPTER->prepareVariantData($aLine);

        // Map VEP columns to LOVD columns.
        $aColumnMappings['lovd_ignore_variant'] = 'lovd_ignore_variant'; // Make sure we take this if we have it.
        foreach ($aColumnMappings as $sVEPColumn => $sLOVDColumn) {
            // But don't let columns overwrite each other! We might have double mappings; two VEP columns pointing to the same LOVD column.
            if (!isset($aLine[$sVEPColumn]) && isset($aVariant[$sLOVDColumn])) {
                // VEP column doesn't actually exist in the file, but we do already have created the column in the $aVariant array...
                // Never mind then!
                continue;
            }

            if (empty($aLine[$sVEPColumn]) || $aLine[$sVEPColumn] == 'unknown' || $aLine[$sVEPColumn] == '.') {
                $aVariant[$sLOVDColumn] = $_ADAPTER->formatEmptyColumn($aLine, $sVEPColumn);
            } else {
                $aVariant[$sLOVDColumn] = $aLine[$sVEPColumn];
            }
        }

        // Verify the GT (allele) column. VCFs might have many interesting values (mostly for multisample VCFs).
        // This function converts the GT values to proper LOVD-style allele values.
        // It can also instruct LOVD+ to ignore the variant (for instance, when we have a "0/0" GT).
        // To allow this function to use all fields and to set the 'lovd_ignore_variant' field, we must pass everything.
        $aVariant = $_ADAPTER->convertGenoTypeToAllele($aVariant);
        //print_r($aVariant. 'variant');
        // If the prepareVariantData() or convertGenoTypeToAllele() methods above determine that this variant line is
        //  not to be imported then they add an 'lovd_ignore_variant' key to the $aLine/$aVariant arrays and set it to
        //  something non-false. For possible values, see the methods in the default adapter.
        // We will process "silent" and "log" here. There will be no record within LOVD of this variant being ignored.
        // Once we'll support "separate", we'll need to process that here, too.
        //if (!empty($aVariant['lovd_ignore_variant'])) {
        //    if ($aVariant['lovd_ignore_variant'] != 'silent') {
        //        lovd_printIfVerbose(VERBOSITY_MEDIUM, 'Line ' . $nLine . ' is being ignored due to rules setup in the adapter library. This line will not be imported into LOVD.' . "\n");
        //    }
        //    continue;
        //}

        // VCF 4.2 can contain lines with an ALT allele of "*", indicating the allele is
        //  not WT at this position, but affected by an earlier mentioned variant instead.
        // Because these are not actually variants, we ignore them.
        if ($aVariant['alt'] == '*') {
            lovd_printIfVerbose(VERBOSITY_HIGH, 'Line ' . $nLine . ' is being ignored because the allele is not wild type but is affected by an earlier mentioned variant. This line will not be imported into LOVD.' . "\n");
            continue;
        }

        // Now "fix" certain values.
        // First, VOG fields.
        // Chromosome.
        $aVariant['chromosome'] = substr($aVariant['chromosome'], 3); // chr1 -> 1
        // VOG/DNA and the position fields.
        lovd_getVariantDescription($aVariant, $aVariant['ref'], $aVariant['alt']);
        // dbSNP.
        if (!empty($aVariant['VariantOnGenome/dbSNP']) && strpos($aVariant['VariantOnGenome/dbSNP'], ';') !== false) {
            // Sometimes we get two dbSNP IDs. Store the first one, only.
            $aDbSNP = explode(';', $aVariant['VariantOnGenome/dbSNP']);
            $aVariant['VariantOnGenome/dbSNP'] = $aDbSNP[0];
        } elseif (empty($aVariant['VariantOnGenome/dbSNP']) && !empty($aVariant['existing_variation']) && $aVariant['existing_variation'] != 'unknown') {
            $aIDs = explode('&', $aVariant['existing_variation']);
            foreach ($aIDs as $sID) {
                if (substr($sID, 0, 2) == 'rs') {
                    $aVariant['VariantOnGenome/dbSNP'] = $sID;
                    break;
                }
            }
        }
        // Fixing some other VOG fields.
        foreach (array('VariantOnGenome/Sequencing/Father/GenoType', 'VariantOnGenome/Sequencing/Father/GenoType/Quality', 'VariantOnGenome/Sequencing/Mother/GenoType', 'VariantOnGenome/Sequencing/Mother/GenoType/Quality') as $sCol) {
            if (!empty($aVariant[$sCol]) && $aVariant[$sCol] == 'None') {
                $aVariant[$sCol] = '';
            }
        }

        // Cleaning and translating VEPs consequences, but only if mapped to the GVS column.
        if (!empty($aLine['Consequence']) && isset($aColumnMappings['Consequence'])
            && $aColumnMappings['Consequence'] == 'VariantOnTranscript/GVS/Function') {
            $aVariant['VariantOnTranscript/GVS/Function'] =
                $_ADAPTER->translateVEPConsequencesToGVS($aVariant['VariantOnTranscript/GVS/Function']);
        }

        // Fix "4.944e-05"-like notations in frequency fields.
        foreach ($aFrequencyColumns as $sFrequencyColumn) {
            if (!empty($aVariant[$sFrequencyColumn])
                && is_numeric($aVariant[$sFrequencyColumn])
                && strpos($aVariant[$sFrequencyColumn], 'e-') !== false) {
                $aVariant[$sFrequencyColumn] = number_format($aVariant[$sFrequencyColumn], 5);
            }
        }

        if (lovd_verifyInstance('leiden')) {
            // Some percentages we get need to be turned into decimals before it can be stored.
            // 2015-10-28; Because of the double column mappings, we ended up with values divided twice.
            // Flipping the array makes sure we get rid of double mappings.
            foreach (array_flip($aColumnMappings) as $sLOVDColumn => $sVEPColumn) {
                if ($sVEPColumn == 'AFESP5400' || $sVEPColumn == 'ALTPERC' || strpos($sVEPColumn, 'ALTPERC_') === 0) {
                    $aVariant[$sLOVDColumn] /= 100;
                }
            }
        } else {
            // Calculate ALTPERC cols, if we can.
            foreach (array('', '/Father', '/Mother') as $sColPart) {
                if (isset($aVariant['VariantOnGenome/Sequencing' . $sColPart . '/Depth/Alt/Fraction'])
                    && $aVariant['VariantOnGenome/Sequencing' . $sColPart . '/Depth/Alt/Fraction'] === ''
                    && isset($aVariant['VariantOnGenome/Sequencing' . $sColPart . '/Depth/Ref'])
                    && isset($aVariant['VariantOnGenome/Sequencing' . $sColPart . '/Depth/Alt'])) {
                    // Calculate the ALT fraction, that can not fail even if there are no reads.
                    // Still, to prevent warnings, we'll have to init these values to 0 if missing.
                    if ($aVariant['VariantOnGenome/Sequencing' . $sColPart . '/Depth/Ref'] === '') {
                        $aVariant['VariantOnGenome/Sequencing' . $sColPart . '/Depth/Ref'] = 0;
                    }
                    if ($aVariant['VariantOnGenome/Sequencing' . $sColPart . '/Depth/Alt'] === '') {
                        $aVariant['VariantOnGenome/Sequencing' . $sColPart . '/Depth/Alt'] = 0;
                    }
                    $aVariant['VariantOnGenome/Sequencing' . $sColPart . '/Depth/Alt/Fraction'] =
                        round(
                            $aVariant['VariantOnGenome/Sequencing' . $sColPart . '/Depth/Alt']
                            / ($aVariant['VariantOnGenome/Sequencing' . $sColPart . '/Depth/Ref']
                                + $aVariant['VariantOnGenome/Sequencing' . $sColPart . '/Depth/Alt']
                                + 0.0000000001)
                            , 3);
                }
            }
        }

        // When seeing a new variant, reset these variables. We don't want them too big; it's useless and takes up a lot of memory.
        if ($sLastVariant != $aVariant['chromosome'] . ':' . $aVariant['VariantOnGenome/DNA']) {
            $sLastVariant = $aVariant['chromosome'] . ':' . $aVariant['VariantOnGenome/DNA'];
            $aMappings = array(); // array(NM_000001.1 => 'c.123del', ...); // To prevent us from running numberConversion too many times.
        }

        // Now, VOT fields.
        // Find gene && transcript in database. When not found, try to create it (if requested). Otherwise, throw a fatal error.
        // Trusting the gene symbol information from VEP is by far the easiest method, and the fastest. This can fail, therefore we also created an alias list.
        // Use the alias only however, if we don't have the gene already in the gene database. We have run into problems when an incorrect alias caused problems.
        $aVariant['symbol_vep'] = $aVariant['symbol']; // But always keep the original one.
        if (!isset($aGenes[$aVariant['symbol']])) {
            if (isset($aGeneAliases[$aVariant['symbol']])) {
                $aVariant['symbol'] = $aGeneAliases[$aVariant['symbol']];
            } elseif (!empty($aVariant['id_hgnc']) && isset($aGenesHGNC[$aVariant['id_hgnc']])) {
                // VEP has a newer gene symbol. Better warn about this.
                $aVariant['symbol'] = $aGenesHGNC[$aVariant['id_hgnc']];
                lovd_printIfVerbose(VERBOSITY_MEDIUM, 'Gene stored as \'' . $aVariant['symbol'] . '\' is given to us as \'' . $aVariant['symbol_vep'] . '\'; using our gene symbol.' . "\n");
                // Store this for the next line.
                $aGeneAliases[$aVariant['symbol_vep']] = $aVariant['symbol'];
            }
        }
        // Verify gene exists, and create it if needed.
        // LOC* genes always fail here, so those we don't try unless we don't care about the HGNC.
        // Also, don't do anything if we're ignoring the transcript - what good will it do?
        if (!empty($aVariant['symbol']) && !isset($aGenes[$aVariant['symbol']]) && !in_array($aVariant['symbol'], $aGenesToIgnore) && !$_ADAPTER->ignoreTranscript($aVariant['transcriptid'])
            && (!preg_match('/^LOC[0-9]+$/', $aVariant['symbol']) || empty($_INSTANCE_CONFIG['conversion']['use_hgnc']) || empty($_INSTANCE_CONFIG['conversion']['enforce_hgnc_gene']))) {
            // First try to get this gene from the database, perhaps conversions run in parallel have created it now.
            // FIXME: This is duplicated code. Make it into a function, perhaps?
            if ($aGene = $_DB->query('SELECT g.id, g.name FROM ' . TABLE_GENES . ' AS g WHERE g.id = ?', array($aVariant['symbol']))->fetchAssoc()) {
                // We've got it in the database.
                $aGenes[$aVariant['symbol']] = $aGene;

            } elseif (!empty($_INSTANCE_CONFIG['conversion']['create_genes_and_transcripts'])) {
                // Gene doesn't exist, and we're configured to create genes.
                // Try to find it at the HGNC. Getting all gene information from the HGNC takes a few seconds.
                $aGeneInfo = array();
                if (!empty($_INSTANCE_CONFIG['conversion']['use_hgnc'])) {
                    lovd_printIfVerbose(VERBOSITY_HIGH, 'Loading gene information for ' . $aVariant['symbol'] . '...' . "\n");
                    $tHGNCStart = microtime(true);
                    $aGeneInfo = lovd_getGeneInfoFromHGNC($aVariant['symbol'], true);
                    $tHGNCCalls += (microtime(true) - $tHGNCStart);
                    $nHGNC++;
                    if (!$aGeneInfo) {
                        // We couldn't find the gene at the HGNC.
                        $sMessage = 'Gene ' . $aVariant['symbol'] . ' can\'t be identified by the HGNC.';
                        lovd_printIfVerbose(VERBOSITY_LOW, $sMessage . "\n");
                        if (!empty($_INSTANCE_CONFIG['conversion']['enforce_hgnc_gene'])) {
                            // This is a problem, only when we enforce using the HGNC.
                            lovd_handleAnnotationError($aVariant, $sMessage);
                        }

                    } elseif (!empty($aGenesHGNC[$aGeneInfo['hgnc_id']])) {
                        // Gene already found in the database under a different symbol.
                        // We already checked the HGNC ID received from VEP, but we apparently didn't catch it there.
                        $aVariant['symbol'] = $aGenesHGNC[$aGeneInfo['hgnc_id']];
                        lovd_printIfVerbose(VERBOSITY_MEDIUM, 'Gene stored as \'' . $aVariant['symbol'] . '\' is given to us as \'' . $aVariant['symbol_vep'] . '\'; using our gene symbol.' . "\n");
                        // Store this for the next line.
                        $aGeneAliases[$aVariant['symbol_vep']] = $aVariant['symbol'];

                    } elseif ($aVariant['symbol'] != $aGeneInfo['symbol']) {
                        // Gene found, under a different symbol.
                        // Detect alias, and store these for next run.
                        lovd_printIfVerbose(VERBOSITY_MEDIUM, '\'' . $aVariant['symbol'] . '\' => \'' . $aGeneInfo['symbol'] . '\',' . "\n");
                        // FIXME: This is duplicated code. Make it into a function, perhaps?
                        if ($aGene = $_DB->query('SELECT g.id, g.name FROM ' . TABLE_GENES . ' AS g WHERE g.id = ?', array($aGeneInfo['symbol']))->fetchAssoc()) {
                            // We've got the alias already in the database; store it under the symbol we're using so that we'll find it back easily.
                            $aGenes[$aVariant['symbol']] = $aGene;
                        }
                    }
                }

                // Create the gene, if the gene's possible alias isn't in the database already,
                //  and the HGNC knows it or if we don't enforce using the HGNC.
                if (!isset($aGenes[$aVariant['symbol']])
                    && ($aGeneInfo || empty($_INSTANCE_CONFIG['conversion']['enforce_hgnc_gene']))) {
                    // If we didn't find the gene in the HGNC but we're not enforcing that, prepare some data.
                    if (!$aGeneInfo) {
                        $aGeneInfo = array(
                            'symbol' => $aVariant['symbol'],
                            'name' => $aVariant['symbol'] . ' (automatically created gene)',
                            'chromosome' => $aVariant['chromosome'],
                            'chrom_band' => '',
                            'hgnc_id' => NULL,
                            'entrez_id' => NULL,
                            'omim_id' => NULL,
                        );
                    }

                    // Create the gene, with whatever info we have.
                    if (!$_DB->query('INSERT INTO ' . TABLE_GENES . '
                         (id, name, chromosome, chrom_band, refseq_genomic, refseq_UD, reference, url_homepage, url_external, allow_download, allow_index_wiki, id_hgnc, id_entrez, id_omim, show_hgmd, show_genecards, show_genetests, note_index, note_listing, refseq, refseq_url, disclaimer, disclaimer_text, header, header_align, footer, footer_align, created_by, created_date, updated_by, updated_date)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())',
                        array($aGeneInfo['symbol'], $aGeneInfo['name'], $aGeneInfo['chromosome'], $aGeneInfo['chrom_band'], $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$aGeneInfo['chromosome']], '', '', '', '', 0, 0, $aGeneInfo['hgnc_id'], $aGeneInfo['entrez_id'], (!$aGeneInfo['omim_id']? NULL : $aGeneInfo['omim_id']), 0, 0, 0, '', '', '', '', 0, '', '', 0, '', 0, 0, 0))
                    ) {
                        $sMessage = 'Can\'t create gene ' . $aVariant['symbol'] . '.';
                        lovd_printIfVerbose(VERBOSITY_LOW, $sMessage . "\n");
                        lovd_handleAnnotationError($aVariant, $sMessage);
                    }

                    // Add the default custom columns to this gene.
                    lovd_addAllDefaultCustomColumns('gene', $aGeneInfo['symbol']);

                    // Write to log...
                    lovd_writeLog('Event', LOG_EVENT, 'Created gene information entry ' . $aGeneInfo['symbol'] . ' (' . $aGeneInfo['name'] . ')');
                    lovd_printIfVerbose(VERBOSITY_MEDIUM, 'Created gene ' . $aGeneInfo['symbol'] . ".\n");
                    flush();

                    // Store this gene, again under the original symbol, so we can easily find it back.
                    $aGenes[$aVariant['symbol']] = array('id' => $aGeneInfo['symbol'], 'name' => $aGeneInfo['name']);
                }
            }
        }
        // We created the gene if possible, but we might still not have it.
        // $aVariant['symbol_vep']            // How we received the symbol from VEP.
        // $aVariant['symbol']                // In case we had a hard coded alias stored, we have it here.
        // $aGenes[$aVariant['symbol']]['id'] // In case the HGNC knows an alias, that's here. This is what's in the database.



        // Store transcript ID without version, we'll use it plenty of times.
        // FIXME: Using 'transcriptid' for the NCBI ID is confusing. Better map it to 'id_ncbi'? (check everywhere)
        $aLine['transcript_noversion'] = substr($aVariant['transcriptid'], 0, strpos($aVariant['transcriptid'] . '.', '.')+1);
        if (empty($aVariant['symbol']) || !isset($aGenes[$aVariant['symbol']]) || !$aGenes[$aVariant['symbol']]) {
            // We really couldn't do anything with this gene (now, or last time).
            $aGenes[$aVariant['symbol']] = false;

        } elseif (!empty($aVariant['transcriptid']) && !isset($aTranscripts[$aVariant['transcriptid']])) {
            // Gene found, transcript given but not yet seen before. Get transcript information.
            // We could loop through $aTranscripts to look for the NCBI ID with a different version, but since a
            //  different process might have created this transcript and therefore we prefer checking the database,
            //  we might as well rely on that completely.
            // Try to get this transcript from the database, ignoring (but preferring) version.
            // When not having a match on the version, we prefer the transcript most recently created.
            if ($aTranscript = $_DB->query('SELECT id, geneid, id_ncbi, position_c_cds_end, position_g_mrna_start, position_g_mrna_end FROM ' . TABLE_TRANSCRIPTS . ' WHERE id_ncbi LIKE ? ORDER BY (id_ncbi = ?) DESC, id DESC LIMIT 1', array($aLine['transcript_noversion'] . '%', $aVariant['transcriptid']))->fetchAssoc()) {
                // We've got it in the database.
                $aTranscripts[$aVariant['transcriptid']] = $aTranscript;

            } elseif (!empty($_INSTANCE_CONFIG['conversion']['create_genes_and_transcripts'])) {
                // To prevent us from having to check the available transcripts all the time, we store the available transcripts, but only insert those we need.
                if (isset($aGenes[$aVariant['symbol']]['transcripts_in_NC'])) {
                    $aTranscriptInfo = $aGenes[$aVariant['symbol']]['transcripts_in_NC'];

                } else {
                    $aTranscriptInfo = array();
                    lovd_printIfVerbose(VERBOSITY_HIGH, 'Loading transcript information for ' . $aGenes[$aVariant['symbol']]['id'] . '...' . "\n");
                    $nSleepTime = 2;
                    // Since we're using the NC as a source now, try multiple gene symbols to use.
                    // Genes can change, and we're not 100% sure about which gene symbol is in the NC.
                    // Start with the one we have in the database, then our alias, then the VEP one.
                    // (note that we could retrieve the entire chromosome's list of transcripts, but that'll be slow)
                    $aGenesToTry = array_unique(array($aGenes[$aVariant['symbol']]['id'], $aVariant['symbol'], $aVariant['symbol_vep']));
                    foreach ($aGenesToTry as $sGeneToTry) {
                        // Retry Mutalyzer call several times until successful.
                        $sJSONResponse = false;
                        for ($i = 0; $i <= $nMutalyzerRetries; $i++) {
                            $aMutalyzerCalls['getTranscriptsAndInfo']++;
                            $tMutalyzerStart = microtime(true);
                            $sJSONResponse = mutalyzer_getTranscriptsAndInfo($_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$aVariant['chromosome']], $sGeneToTry);
                            $tMutalyzerCalls += (microtime(true) - $tMutalyzerStart);
                            $nMutalyzer++;
                            if ($sJSONResponse === false) {
                                // The Mutalyzer call has failed.
                                sleep($nSleepTime); // Sleep for some time.
                                $nSleepTime = $nSleepTime * 2; // Double the amount of time that we sleep each time.
                            } else {
                                break;
                            }
                        }
                        if ($sJSONResponse === false) {
                            lovd_printIfVerbose(VERBOSITY_LOW, '>>>>> Attempted to call Mutalyzer ' . $nMutalyzerRetries . ' times to getTranscriptsAndInfo and failed on line ' . $nLine . '.' . "\n");

                        } elseif ($aResponse = json_decode($sJSONResponse, true)) {
                            // Before we had to go two layers deep; through the result, then read out the info.
                            // But now apparently this service just returns the string with quotes (the latter are removed by json_decode()).
                            $aTranscriptInfo = $aResponse;

                            if (empty($aTranscriptInfo) || !is_array($aTranscriptInfo) || !empty($aTranscriptInfo['faultcode'])) {
                                if (!empty($aTranscriptInfo['faultcode'])) {
                                    // Something went wrong. Let the user know.
                                    lovd_printIfVerbose(VERBOSITY_MEDIUM, 'Error while retrieving transcripts for gene ' . $aGenes[$aVariant['symbol']]['id'] . ' (' . $aTranscriptInfo['faultcode'] . '): ' . $aTranscriptInfo['faultstring'] . '.' . "\n");
                                } else {
                                    // Usually this is the case. Not always an error.
                                    lovd_printIfVerbose(VERBOSITY_MEDIUM, 'No available transcripts for gene ' . $aGenes[$aVariant['symbol']]['id'] . ' found.' . "\n");
                                }
                                $aTranscripts[$aVariant['transcriptid']] = false; // Ignore transcript.
                                $aTranscriptInfo = array(array('id' => 'NO_TRANSCRIPTS')); // Basically, any text will do. Just stop searching for other transcripts for this gene.
                            } else {
                                // Found transcripts. Don't look for any other gene. Use the $aTranscriptInfo that we have.
                                break;
                            }
                        }
                    }

                    // Store for next time.
                    $aGenes[$aVariant['symbol']]['transcripts_in_NC'] = $aTranscriptInfo;
                }

                // Loop transcript options, add the one we need.
                foreach($aTranscriptInfo as $aTranscript) {
                    // Comparison is made without looking at version numbers!
                    if (substr($aTranscript['id'], 0, strpos($aTranscript['id'] . '.', '.')+1) == $aLine['transcript_noversion']) {
                        // Store in database, prepare values.
                        $sTranscriptName = str_replace($aGenes[$aVariant['symbol']]['name'] . ', ', '', $aTranscript['product']);
                        // 2018-06-13; The getTranscriptsAndInfo() feature on NCs has a bug that the product field is empty.
                        if (!$sTranscriptName) {
                            $sTranscriptName = $aTranscript['id'];
                        }
                        $aTranscript['id_ncbi'] = $aTranscript['id'];
                        $sTranscriptProtein = (!isset($aTranscript['proteinTranscript']['id'])? '' : $aTranscript['proteinTranscript']['id']);
                        $aTranscript['position_c_cds_end'] = $aTranscript['cCDSStop']; // To calculate VOT variant position, if in 3'UTR.
                        // 2018-06-13; The getTranscriptsAndInfo() feature on NCs has a bug that chrom* fields are not available.
                        if (!isset($aTranscript['chromTransStart']) || !isset($aTranscript['chromTransEnd'])) {
                            $aTranscript['chromTransStart'] = $aTranscript['gTransStart'];
                            $aTranscript['chromTransEnd'] = $aTranscript['gTransEnd'];
                        }

                        // Add transcript to gene.
                        if (!$_DB->query('INSERT INTO ' . TABLE_TRANSCRIPTS . '
                             (id, geneid, name, id_ncbi, id_ensembl, id_protein_ncbi, id_protein_ensembl, id_protein_uniprot, remarks, position_c_mrna_start, position_c_mrna_end, position_c_cds_end, position_g_mrna_start, position_g_mrna_end, created_date, created_by)
                            VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)',
                            array($aGenes[$aVariant['symbol']]['id'], $sTranscriptName, $aTranscript['id_ncbi'], '', $sTranscriptProtein, '', '', '', $aTranscript['cTransStart'], $aTranscript['sortableTransEnd'], $aTranscript['cCDSStop'], $aTranscript['chromTransStart'], $aTranscript['chromTransEnd'], 0))) {
                            $sMessage = 'Can\'t create transcript ' . $aTranscript['id_ncbi'] . ' for gene ' . $aVariant['symbol'] . '.';
                            lovd_printIfVerbose(VERBOSITY_LOW, $sMessage . "\n");
                            lovd_handleAnnotationError($aVariant, $sMessage);
                        }

                        // Save the ID before the writeLog deletes it...
                        $nTranscriptID = str_pad($_DB->lastInsertId(), $_SETT['objectid_length']['transcripts'], '0', STR_PAD_LEFT);

                        // Write to log...
                        lovd_writeLog('Event', LOG_EVENT, 'Transcript entry successfully added to gene ' . $aGenes[$aVariant['symbol']]['id'] . ' - ' . $sTranscriptName);
                        lovd_printIfVerbose(VERBOSITY_MEDIUM, 'Created transcript ' . $aTranscript['id'] . ".\n");
                        flush();

                        // Store in memory.
                        $aTranscripts[$aVariant['transcriptid']] = array_merge($aTranscript, array('id' => $nTranscriptID)); // Contains a lot more info than needed, but whatever.
                    }
                }

                if (!isset($aTranscripts[$aVariant['transcriptid']])) {
                    // We don't have it, we can't get it... Stop looking for it, please!
                    $aTranscripts[$aVariant['transcriptid']] = false;
                }
            }
        }
        // We created the transcript if possible, but we might still not have it.
        // $aVariant['transcriptid']                           // How we received the transcript from VEP.
        // $aTranscripts[$aVariant['transcriptid']]['id_ncbi'] // The NCBI ID of the transcript in the database (can be different version).

        // Now check, if we managed to get the transcript ID. If not, then we'll have to continue without it.
        if (empty($aVariant['transcriptid']) || $_ADAPTER->ignoreTranscript($aVariant['transcriptid']) || empty($aTranscripts[$aVariant['transcriptid']])) {
            // When the transcript still doesn't exist, or it evaluates to false (we don't have it, we can't get it), then skip it.
            $aVariant['transcriptid'] = '';
        } else {
            // Handle the rest of the VOT columns.
            // First, take off the transcript name, so we can easily check for a del/ins checking for an underscore.
            $aVariant['VariantOnTranscript/DNA'] = substr($aVariant['VariantOnTranscript/DNA'], strpos($aVariant['VariantOnTranscript/DNA'], ':')+1); // NM_000000.1:c.1del -> c.1del

            // Decide if we need to call Mutalyzer's position converter to generate the VOT/DNA.
            // For sure, we need to do so, when there is no VOT/DNA from VEP.
            $bCallMutalyzer = (!$aVariant['VariantOnTranscript/DNA']);
            // If VEP did come up with something, check if this LOVD+ instance trusts VEP's output for indels.
            if ($_INSTANCE_CONFIG['conversion']['check_indel_description']) {
                // This LOVD+ instance chooses to ignore VEP's predictions looking like indels.
                // VEP doesn't understand that when the gene is on reverse, they have to switch the positions.
                // Also, sometimes a delins is simply a substitution, when the VCF file was complicated (ACGT to ACCT for example).
                // We've also seen cases where VEP's intronic positions in the DNA field were simply wrong.
                // Call Mutalyzer to fix these errors.
                $bCallMutalyzer = ($bCallMutalyzer || (strpos($aVariant['VariantOnTranscript/DNA'], '_') !== false));
            }

            // We still need the original later.
            $aVariant['VariantOnTranscript/DNA/VEP'] = $aVariant['VariantOnTranscript/DNA'];
            if ($bCallMutalyzer) {
                // We don't have a DNA field from VEP, or we don't trust it (see above).
                // Call Mutalyzer, but first check if I did that before already.
                if (empty($aMappings)) {
                    $aMappings = array();
                    lovd_printIfVerbose(VERBOSITY_FULL, 'Running position converter, DNA was: "' . $aVariant['VariantOnTranscript/DNA'] . '"' . "\n");

                    $nSleepTime = 2;
                    // Retry Mutalyzer call several times until successful.
                    $sJSONResponse = false;
                    for ($i=0; $i <= $nMutalyzerRetries; $i++) {
                        $aMutalyzerCalls['numberConversion'] ++;
                        $tMutalyzerStart = microtime(true);
                        $sJSONResponse = mutalyzer_numberConversion($_CONF['refseq_build'], $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$aVariant['chromosome']] . ':' . $aVariant['VariantOnGenome/DNA']);
                        $tMutalyzerCalls += (microtime(true) - $tMutalyzerStart);
                        $nMutalyzer++;
                        if ($sJSONResponse === false) {
                            // The Mutalyzer call has failed.
                            sleep($nSleepTime); // Sleep for some time.
                            $nSleepTime = $nSleepTime * 2; // Double the amount of time that we sleep each time.
                        } else {
                            break;
                        }
                    }

                    if ($sJSONResponse === false) {
                        lovd_printIfVerbose(VERBOSITY_LOW, '>>>>> Attempted to call Mutalyzer ' . $nMutalyzerRetries . ' times for numberConversion and failed on line ' . $nLine . '.' . "\n");
                    }

                    if ($sJSONResponse && $aResponse = json_decode($sJSONResponse, true)) {
                        // Before we had to go two layers deep; through the result, then read out the string.
                        // But now apparently this service just returns the string with quotes (the latter are removed by json_decode()).
                        foreach ($aResponse as $sResponse) {
                            list($sRef, $sDNA) = explode(':', $sResponse, 2);
                            $aMappings[$sRef] = $sDNA;
                        }
                    }
                }

                // Find mapping of variant on the currently handled transcript.
                if (isset($aMappings[$aTranscripts[$aVariant['transcriptid']]['id_ncbi']])) {
                    // Successfully mapped on the transcript version that we have in the database.
                    $aVariant['VariantOnTranscript/DNA'] = $aMappings[$aTranscripts[$aVariant['transcriptid']]['id_ncbi']];
                } elseif (isset($aMappings[$aVariant['transcriptid']])) {
                    // Successfully mapped on the transcript version received by VEP.
                    $aVariant['VariantOnTranscript/DNA'] = $aMappings[$aVariant['transcriptid']];
                } else {
                    // Somehow, we can't find the transcript in the mapping info.
                    // This can only happen either when the NC has a different transcript than the one we have in the
                    //  position converter database,
                    //  or when VEP says the variant maps, but Mutalyzer disagrees (variant may be outside of gene).
                    // Try finding the transcript for other versions. Just take first one you find.
                    $aAlternativeVersions = array();
                    foreach ($aMappings as $sRef => $sDNA) {
                        if (strpos($sRef, $aLine['transcript_noversion']) === 0) {
                            $aAlternativeVersions[] = $sRef;
                        }
                    }
                    if ($aAlternativeVersions) {
                        lovd_printIfVerbose(VERBOSITY_FULL, 'Found alternative by searching: ' . $aVariant['transcriptid'] . ' [' . implode(', ', $aAlternativeVersions) . ']' . "\n");
                        $aVariant['VariantOnTranscript/DNA'] = $aMappings[$aAlternativeVersions[0]];
                    } else {
                        // This happens when VEP says we can map on a known transcript, but doesn't provide us a valid mapping,
                        // *and* Mutalyzer at the same time doesn't seem to be able to map to this transcript at all.
                        // This happens sometimes with variants outside of genes, that VEP apparently considers close enough,
                        //  or differences between the position converter database versus de NC-based database.

                        // Still no mapping. If we did have DNA from VEP, we'll just accept that. Otherwise, we call it an error.
                        $sErrorMsg = 'Can\'t map variant ' .
                            $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$aVariant['chromosome']] .
                            ':' . $aVariant['VariantOnGenome/DNA'] .
                            ' (' . $aVariant['chromosome'] . ':' . $aVariant['position'] . $aVariant['ref'] . '>' . $aVariant['alt'] . ') ' .
                            'onto transcript ' . $aLine['transcript_noversion'] . '*.';
                        if ($aVariant['VariantOnTranscript/DNA']) {
                            $sErrorMsg .= "\n" .
                                          'Falling back to VEP\'s DNA description!' . "\n";
                            lovd_printIfVerbose(VERBOSITY_FULL, $sErrorMsg);
                        } else {
                            // We have one more solution - call the name checker and try to find the mapping there.
                            // This is a very slow procedure and will hopefully not be used often, but due to recent
                            //  developments, Mutalyzer's position converter database diverged from the maintained database.

                            // FIXME: This is a lot of repeated code again. Better clean it up.
                            lovd_printIfVerbose(VERBOSITY_FULL, 'Mutalyzer\'s position converter doesn\'t know transcript, falling back to the name checker instead!' . "\n");
                            $nSleepTime = 2;
                            // Retry Mutalyzer call several times until successful.
                            $sJSONResponse = false;
                            for ($i=0; $i <= $nMutalyzerRetries; $i++) {
                                $aMutalyzerCalls['runMutalyzer'] ++;
                                $tMutalyzerStart = microtime(true);
                                $sJSONResponse = mutalyzer_runMutalyzer(rawurlencode($_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$aVariant['chromosome']] . ':' . $aVariant['VariantOnGenome/DNA']));
                                $tMutalyzerCalls += (microtime(true) - $tMutalyzerStart);
                                $nMutalyzer++;
                                if ($sJSONResponse === false) {
                                    // The Mutalyzer call has failed.
                                    sleep($nSleepTime); // Sleep for some time.
                                    $nSleepTime = $nSleepTime * 2; // Double the amount of time that we sleep each time.
                                } else {
                                    break;
                                }
                            }
                            if ($sJSONResponse === false) {
                                lovd_printIfVerbose(VERBOSITY_LOW, '>>>>> Attempted to call Mutalyzer ' . $nMutalyzerRetries . ' times to runMutalyzer and failed on line ' . $nLine . '.' . "\n");
                            }

                            if ($sJSONResponse && $aResponse = json_decode($sJSONResponse, true)) {
                                // Find DNA mapping in mutalyzer output.
                                if (!empty($aResponse['legend']) && !empty($aResponse['transcriptDescriptions'])) {
                                    // Store the *versions* of the wanted transcript. Only versions, so it sorts nicely.
                                    // Store the transcript names (v-numbers) that we find.
                                    $aMutalyzerMappings = array(); // array("1" => PRAMEF22_v001).

                                    // Loop over legend records to find transcript name (v-number).
                                    // Mutalyzer can provide both the wanted transcript and other versions here,
                                    //  sometimes both at the same time, e.g. with NC_000001.10:g.13183634G>A.
                                    foreach ($aResponse['legend'] as $aRecord) {
                                        if (isset($aRecord['id']) && strpos($aRecord['id'], $aLine['transcript_noversion']) === 0) {
                                            $aMutalyzerMappings[substr($aRecord['id'], strlen($aLine['transcript_noversion']))] = $aRecord['name'];
                                        }
                                    }
                                    // Sort the found transcripts on their version, descending.
                                    krsort($aMutalyzerMappings);
                                    $sTranscriptName = '';
                                    // First check if we have the exact right version for it.
                                    if (isset($aMutalyzerMappings[substr(strrchr($aVariant['transcriptid'], '.'), 1)])) {
                                        $sTranscriptName = $aMutalyzerMappings[substr($aVariant['transcriptid'], strlen($aLine['transcript_noversion']))];
                                    } else {
                                        $sTranscriptName = current($aMutalyzerMappings);
                                    }

                                    if ($sTranscriptName) {
                                        // Select DNA mapping based on the found v-number.
                                        foreach ($aResponse['transcriptDescriptions'] as $sMutalyzerMapping) {
                                            if (strpos($sMutalyzerMapping, $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$aVariant['chromosome']] . '(' . $sTranscriptName . '):') === 0) {
                                                // Match on v-number in given mappings.
                                                $aVariant['VariantOnTranscript/DNA'] = substr(strchr($sMutalyzerMapping, ':'), 1);
                                                break;
                                            }
                                        }
                                    }
                                }
                            }

                            if (!$aVariant['VariantOnTranscript/DNA']) {
                                // This fallback also failed :(
                                $nAnnotationErrors = lovd_handleAnnotationError($aVariant, $sErrorMsg);
                                $bDropTranscriptData = $_INSTANCE_CONFIG['conversion']['annotation_error_drops_line'];
                            }
                        }
                    }
                }
            }

            // For the position fields, VEP can generate data (CDS_position), but it's hardly usable. Calculate ourselves.
            list($aVariant['position_c_start'], $aVariant['position_c_start_intron'], $aVariant['position_c_end'], $aVariant['position_c_end_intron']) = array_values(lovd_getVariantPosition($aVariant['VariantOnTranscript/DNA'], $aTranscripts[$aVariant['transcriptid']]));

            // VariantOnTranscript/Position is an integer column; so just copy the c_start.
            $aVariant['VariantOnTranscript/Position'] = $aVariant['position_c_start'];
            $aVariant['VariantOnTranscript/Distance_to_splice_site'] = ((bool) $aVariant['position_c_start_intron'] == (bool) $aVariant['position_c_end_intron']? min(abs($aVariant['position_c_start_intron']), abs($aVariant['position_c_end_intron'])) : ($aVariant['position_c_start_intron']? abs($aVariant['position_c_start_intron']) : abs($aVariant['position_c_end_intron'])));

            // VariantOnTranscript/RNA && VariantOnTranscript/Protein.
            // Try to do as much as possible by ourselves.
            $aVariant['VariantOnTranscript/RNA'] = '';
            // Convert VEP's (p.%3D) to (p.=).
            $aVariant['VariantOnTranscript/Protein'] = urldecode($aVariant['VariantOnTranscript/Protein']);
            if ($aVariant['VariantOnTranscript/Protein']) {
                // VEP came up with something...
                $aVariant['VariantOnTranscript/RNA'] = 'r.(?)';
                $aVariant['VariantOnTranscript/Protein'] = substr($aVariant['VariantOnTranscript/Protein'], strpos($aVariant['VariantOnTranscript/Protein'], ':')+1); // NP_000000.1:p.Met1? -> p.Met1?
                if ($aVariant['VariantOnTranscript/Protein'] == $aVariant['VariantOnTranscript/DNA/VEP'] . '(p.=)'
                    || preg_match('/^p\.([A-Z][a-z]{2})+([0-9]+)=$/', $aVariant['VariantOnTranscript/Protein'])) {
                    // But sometimes VEP messes up; DNA: c.4482G>A; Prot: c.4482G>A(p.=) or
                    //  Prot: p.ValSerThrAspHisAlaThrSerLeuProValThrIleProSerAlaAla1225=
                    $aVariant['VariantOnTranscript/Protein'] = 'p.(=)';
                } else {
                    $aVariant['VariantOnTranscript/Protein'] = str_replace('p.', 'p.(', $aVariant['VariantOnTranscript/Protein'] . ')');
                }
            } elseif (in_array(substr($aTranscripts[$aVariant['transcriptid']]['id_ncbi'], 0, 2), array('NR', 'XR'))) {
                // Non coding transcript, no wonder we didn't get a protein field.
                $aVariant['VariantOnTranscript/RNA'] = 'r.(?)';
                $aVariant['VariantOnTranscript/Protein'] = '-';
            } elseif (($aVariant['position_c_start'] < 0 && $aVariant['position_c_end'] < 0)
                || ($aVariant['position_c_start'] > $aTranscripts[$aVariant['transcriptid']]['position_c_cds_end'] && $aVariant['position_c_end'] > $aTranscripts[$aVariant['transcriptid']]['position_c_cds_end'])
                || ($aVariant['position_c_start_intron'] && $aVariant['position_c_end_intron'] && min(abs($aVariant['position_c_start_intron']), abs($aVariant['position_c_end_intron'])) > 5
                    && ($aVariant['position_c_start'] == $aVariant['position_c_end'] || ($aVariant['position_c_start'] == ($aVariant['position_c_end']-1) && $aVariant['position_c_start_intron'] > 0 && $aVariant['position_c_end_intron'] < 0)))) {
                // 5'UTR, 3'UTR, fully intronic in one intron only (at least 5 bases away from exon border).
                $aVariant['VariantOnTranscript/RNA'] = 'r.(=)';
                $aVariant['VariantOnTranscript/Protein'] = 'p.(=)';
            } elseif (($aVariant['position_c_start_intron'] && (!$aVariant['position_c_end_intron'] || abs($aVariant['position_c_start_intron']) <= 5))
                || ($aVariant['position_c_end_intron'] && (!$aVariant['position_c_start_intron'] || abs($aVariant['position_c_end_intron']) <= 5))) {
                // Partially intronic, or variants spanning multiple introns, or within first/last 5 bases of an intron.
                $aVariant['VariantOnTranscript/RNA'] = 'r.spl?';
                $aVariant['VariantOnTranscript/Protein'] = 'p.?';
            } elseif (!$bDropTranscriptData && $aVariant['VariantOnTranscript/DNA']) {
                // OK, too bad, we need to run Mutalyzer anyway (only if we're using this VOT line).
                lovd_printIfVerbose(VERBOSITY_MEDIUM, 'Running mutalyzer to predict protein change for ' .
                    $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$aVariant['chromosome']] .
                    ':' . $aVariant['VariantOnGenome/DNA'] .
                    ' (' . $aVariant['chromosome'] . ':' . $aVariant['position'] . $aVariant['ref'] . '>' . $aVariant['alt'] .
                    ' @ ' . $aVariant['transcriptid'] . ")\n");
                $nSleepTime = 2;
                // Retry Mutalyzer call several times until successful.
                $sJSONResponse = false;
                for ($i=0; $i <= $nMutalyzerRetries; $i++) {
                    $aMutalyzerCalls['runMutalyzer'] ++;
                    $tMutalyzerStart = microtime(true);
                    $sJSONResponse = mutalyzer_runMutalyzer(rawurlencode($_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$aVariant['chromosome']] . ':' . $aVariant['VariantOnGenome/DNA']));
                    $tMutalyzerCalls += (microtime(true) - $tMutalyzerStart);
                    $nMutalyzer++;
                    if ($sJSONResponse === false) {
                        // The Mutalyzer call has failed.
                        sleep($nSleepTime); // Sleep for some time.
                        $nSleepTime = $nSleepTime * 2; // Double the amount of time that we sleep each time.
                    } else {
                        break;
                    }
                }
                if ($sJSONResponse === false) {
                    lovd_printIfVerbose(VERBOSITY_LOW, '>>>>> Attempted to call Mutalyzer ' . $nMutalyzerRetries . ' times to runMutalyzer and failed on line ' . $nLine . '.' . "\n");
                }

                if ($sJSONResponse && $aResponse = json_decode($sJSONResponse, true)) {
                    // Predict RNA && Protein change.
                    // 'Intelligent' error handling.
                    // FIXME: Implement lovd_getRNAProteinPrediction() here.
                    // LOVD3's version is CURL-ready and uses JSON.
                    foreach ($aResponse['messages'] as $aError) {
                        // Pass other errors on to the users?
                        // FIXME: This is implemented as well in inc-lib-variants.php (LOVD3.0-15).
                        //  When we update LOVD+ to LOVD 3.0-15, use this lib so we don't duplicate code...
                        if (isset($aError['errorcode']) && $aError['errorcode'] == 'ERANGE') {
                            // Ignore 'ERANGE' as an actual error, because we can always interpret this as p.(=), p.? or p.0.
                            $aVariantRange = explode('_', $aVariant['VariantOnTranscript/DNA']);
                            // Check what the variant looks like and act accordingly.
                            if (count($aVariantRange) === 2 && preg_match('/-\d+/', $aVariantRange[0]) && preg_match('/-\d+/', $aVariantRange[1])) {
                                // Variant has 2 positions. Variant has both the start and end positions upstream of the transcript, we can assume that the product will not be affected.
                                $sPredictR = 'r.(=)';
                                $sPredictP = 'p.(=)';
                            } elseif (count($aVariantRange) === 2 && preg_match('/-\d+/', $aVariantRange[0]) && preg_match('/\*\d+/', $aVariantRange[1])) {
                                // Variant has 2 positions. Variant has an upstream start position and a downstream end position, we can assume that the product will not be expressed.
                                $sPredictR = 'r.0?';
                                $sPredictP = 'p.0?';
                            } elseif (count($aVariantRange) == 2 && preg_match('/\*\d+/', $aVariantRange[0]) && preg_match('/\*\d+/', $aVariantRange[1])) {
                                // Variant has 2 positions. Variant has both the start and end positions downstream of the transcript, we can assume that the product will not be affected.
                                $sPredictR = 'r.(=)';
                                $sPredictP = 'p.(=)';
                            } elseif (count($aVariantRange) == 1 && preg_match('/-\d+/', $aVariantRange[0]) || preg_match('/\*\d+/', $aVariantRange[0])) {
                                // Variant has 1 position and is either upstream or downstream from the transcript, we can assume that the product will not be affected.
                                $sPredictR = 'r.(=)';
                                $sPredictP = 'p.(=)';
                            } else {
                                // One of the positions of the variant falls within the transcript, so we can not make any assumptions based on that.
                                $sPredictR = 'r.?';
                                $sPredictP = 'p.?';
                            }
                            // Fill in our assumption to forge that this information came from Mutalyzer.
                            $aVariant['VariantOnTranscript/RNA'] = $sPredictR;
                            $aVariant['VariantOnTranscript/Protein'] = $sPredictP;
                            break;
                        } elseif (isset($aError['errorcode']) && $aError['errorcode'] == 'WSPLICE') {
                            $aVariant['VariantOnTranscript/RNA'] = 'r.spl?';
                            $aVariant['VariantOnTranscript/Protein'] = 'p.?';
                            break;
                        } elseif (isset($aError['errorcode']) && $aError['errorcode'] == 'EREF') {
                            // This can happen, because we have UDs from hg38, but the alignment and variant calling is done on hg19... :(  Sequence can be different.
                            $aVariant['VariantOnTranscript/RNA'] = 'r.(?)';
                            $aVariant['VariantOnTranscript/Protein'] = 'p.?';
                            lovd_printIfVerbose(VERBOSITY_MEDIUM, 'Mutalyzer returned EREF error, hg19/hg38 error?' . "\n");
                            // We don't break here, because if there is also a WSPLICE we rather go with that one.
                        }
                    }

                    // Find protein prediction in mutalyzer output.
                    if (!$aVariant['VariantOnTranscript/Protein'] && !empty($aResponse['legend']) && !empty($aResponse['proteinDescriptions'])) {
                        // Store the *versions* of the wanted transcript. Only versions, so it sorts nicely.
                        // Store the transcript names (v-numbers) that we find.
                        $aMutalyzerMappings = array(); // array("1" => PRAMEF22_v001).

                        // Loop over legend records to find transcript name (v-number).
                        // Mutalyzer can provide both the wanted transcript and other versions here,
                        //  sometimes both at the same time, e.g. with NC_000001.10:g.13183634G>A.
                        foreach ($aResponse['legend'] as $aRecord) {
                            if (isset($aRecord['id']) && strpos($aRecord['id'], $aLine['transcript_noversion']) === 0
                                && substr($aRecord['name'], -4, 1) == 'v') {
                                $aMutalyzerMappings[substr($aRecord['id'], strlen($aLine['transcript_noversion']))] = $aRecord['name'];
                            }
                        }
                        // Sort the found transcripts on their version, descending.
                        krsort($aMutalyzerMappings);
                        $sTranscriptName = '';

                        // First check if we have the exact right version for it.
                        if (isset($aMutalyzerMappings[substr(strrchr($aVariant['transcriptid'], '.'), 1)])) {
                            $sTranscriptName = $aMutalyzerMappings[substr($aVariant['transcriptid'], strlen($aLine['transcript_noversion']))];
                        } else {
                            $sTranscriptName = current($aMutalyzerMappings);
                        }

                        if ($sTranscriptName) {
                            // Generate protein isoform name (i-number) from transcript name (v-number).
                            $sProteinName = str_replace('_v', '_i', $sTranscriptName);

                            // Select protein description based on protein isoform (i-number).
                            foreach ($aResponse['proteinDescriptions'] as $sMutalyzerMapping) {
                                if (strpos($sMutalyzerMapping, $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$aVariant['chromosome']] . '(' . $sProteinName . '):') === 0) {
                                    // Match on i-number in given mappings.
                                    $aVariant['VariantOnTranscript/Protein'] = substr(strchr($sMutalyzerMapping, ':'), 1);
                                    if ($aVariant['VariantOnTranscript/Protein'] == 'p.?') {
                                        $aVariant['VariantOnTranscript/RNA'] = 'r.?';
                                    } elseif ($aVariant['VariantOnTranscript/Protein'] == 'p.(=)') {
                                        // FIXME: Not correct in case of substitutions e.g. in the third position of the codon, not leading to a protein change.
                                        $aVariant['VariantOnTranscript/RNA'] = 'r.(=)';
                                    } else {
                                        // RNA will default to r.(?).
                                        $aVariant['VariantOnTranscript/RNA'] = 'r.(?)';
                                    }
                                    break;
                                }
                            }
                        }
                    }
                }
                // Any errors related to the prediction of Exon, RNA or Protein are silently ignored.
            }

            if (!$bDropTranscriptData && $aVariant['VariantOnTranscript/DNA'] && !$aVariant['VariantOnTranscript/RNA']) {
                $sErrorMsg = 'Missing VariantOnTranscript/RNA for ' .
                    $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$aVariant['chromosome']] .
                    ':' . $aVariant['VariantOnGenome/DNA'] .
                    ' (' . $aVariant['chromosome'] . ':' . $aVariant['position'] . $aVariant['ref'] . '>' . $aVariant['alt'] .
                    ' @ ' . $aVariant['transcriptid'] . ').';
                $nAnnotationErrors = lovd_handleAnnotationError($aVariant, $sErrorMsg);
                $bDropTranscriptData = $_INSTANCE_CONFIG['conversion']['annotation_error_drops_line'];
            }
        }

        // DNA fields and protein field can be super long with long inserts.
        // For the DNA fields, shorten insAAAAAA to ins(6), for DNA descriptions >100 characters.
        // FIXME: Better make this dependent on the field length; there are LOVDs out there that allow more data, and they should get it.
        foreach (array('VariantOnGenome/DNA', 'VariantOnTranscript/DNA') as $sField) {
            if (isset($aVariant[$sField]) && strlen($aVariant[$sField]) > 100 && preg_match('/ins([ACTG]+)$/', $aVariant[$sField], $aRegs)) {
                $aVariant[$sField] = str_replace('ins' . $aRegs[1], 'ins(' . strlen($aRegs[1]) . ')', $aVariant[$sField]);
            }
        }
        // Don't put this in the output file.
        unset($aVariant['VariantOnTranscript/DNA/VEP']);

        // For the protein field, protein descriptions >100 characters can be shortened.
        // FIXME: Better make this dependent on the field length; there are LOVDs out there that allow more data, and they should get it.
        $sField = 'VariantOnTranscript/Protein';
        if (isset($aVariant[$sField]) && strlen($aVariant[$sField]) > 100) {
            // For the protein field, shorten insArgArgArg to ins(3), for protein descriptions >100 characters.
            if (preg_match('/ins(([A-Z][a-z]{2})+)\)$/', $aVariant[$sField], $aRegs)) {
                $aVariant[$sField] = str_replace('ins' . $aRegs[1], 'ins(' . strlen($aRegs[1]) . ')', $aVariant[$sField]);
            }
            // Vep produces interestingly long deletions and duplications as well.
            // p.TerSerProProGlyLysProGlnGlyProProProGlnGlyGlyAsnGlnProGlnGlyProProProProProGlyLysProGlnGlyProProProGlnGlyGlyLysLysProGlnGlyProProProProGlyLysProGlnGlyProProProGlnGlyAspLysSerArgSerSer152del -> p.Ter152_Ser212del
            // We might want to fix these either way, as they are wrong either way?
            if (preg_match('/^p.\(([A-Z][a-z]{2})((?:[A-Z][a-z]{2})+)([A-Z][a-z]{2})([0-9]+)(del|dup)\)$/', $aVariant[$sField], $aRegs)) {
                //                 1              2                   3              4       5
                $aVariant[$sField] = 'p.(' . $aRegs[1] . $aRegs[4] . '_' . $aRegs[3] . ($aRegs[4] + (strlen($aRegs[2])/3)+1) . $aRegs[5] . ')';
            }
        }

        // Replace the ncbi ID with the transcripts LOVD database ID to be used when creating the VOT record.
        // This used to be done at the start of this else statement but since we have switched from using the headers in the file
        // to using the column mappings (much more robust) we no longer had the ncbi ID available as it was overwritten.
        // By moving this code down here we retain the ncbi ID for use and then overwrite at the last step.
        $aVariant['transcriptid'] = (!isset($aTranscripts[$aVariant['transcriptid']]['id'])? '' : $aTranscripts[$aVariant['transcriptid']]['id']);



        // Now store the variants, first the genomic stuff, then the VOT stuff.
        // If the VOG data has already been stored, we will *not* overwrite it.
        // Build the key.
        $sKey = $aVariant['chromosome'] . ':' . $aVariant['position'] . $aVariant['ref'] . '>' . $aVariant['alt'];

        if (!isset($aData[$sKey])) {
            // Create key, put in VOG data.
            $aVOG = array();
            foreach ($aVariant as $sCol => $sVal) {
                if (in_array($sCol, $aColumnsForVOG) || substr($sCol, 0, 16) == 'VariantOnGenome/') {
                    $aVOG[$sCol] = $sVal;
                }
            }
            $aData[$sKey] = array($aVOG);
        }

        // Perform any postprocessing, for instance based all this VOG's VOTs.
        $_ADAPTER->postValueAssignmentUpdate($sKey, $aVariant, $aData);

        // Now, store VOT data. Because I had received test files with repeated lines, and allowing repeated lines will break import, also here we will check for the key.
        // Also check for a set transcriptid, because it can be empty (transcript could not be created).
        if (!$bDropTranscriptData && !isset($aData[$sKey][$aVariant['transcriptid']]) && $aVariant['transcriptid']) {
            $aVOT = array();
            foreach ($aVariant as $sCol => $sVal) {
                if (in_array($sCol, $aColumnsForVOT) || substr($sCol, 0, 20) == 'VariantOnTranscript/') {
                    $aVOT[$sCol] = $sVal;
                }
            }

            $aData[$sKey][$aVariant['transcriptid']] = $aVOT;
        }

        // Some reporting of where we are... as long as we're being verbose.
        // Info lines show less frequently for low and medium verbosity.
        $aLinesToReport = array(
            VERBOSITY_LOW => 10000,
            VERBOSITY_MEDIUM => 1000,
            VERBOSITY_HIGH => 100,
            VERBOSITY_FULL => 100,
        );
        if (VERBOSITY > VERBOSITY_NONE && !($nLine % $aLinesToReport[VERBOSITY])) {
            lovd_printIfVerbose(VERBOSITY_LOW, '------- Line ' . $nLine . ' -------' . str_repeat(' ', 7 - strlen($nLine)) . date('Y-m-d H:i:s') . "\n");
            flush();
        }
    }
    fclose($fInput); // Close input file.

    lovd_printIfVerbose(VERBOSITY_MEDIUM, 'Done parsing file. Current time: ' . date('Y-m-d H:i:s') . ".\n");
    // Show the number of times HGNC and Mutalyzer were called.
    lovd_printIfVerbose(VERBOSITY_MEDIUM,
        'Number of times HGNC called: ' . $nHGNC . (!$nHGNC? '' :
            ', taking ' . round($tHGNCCalls/60) . ' minutes, ' . round($tHGNCCalls/$nHGNC, 2) . ' sec/call') . ".\n" .
        'Number of times Mutalyzer called: ' . $nMutalyzer . (!$nMutalyzer? '' :
            ', taking ' . round($tMutalyzerCalls/60) . ' minutes, ' . round($tMutalyzerCalls/$nMutalyzer, 2) . ' sec/call') . ".\n");
    foreach ($aMutalyzerCalls as $sFunction => $nCalls) {
        lovd_printIfVerbose(VERBOSITY_MEDIUM, '  ' . $sFunction . ': ' . $nCalls . "\n");
    }
    lovd_printIfVerbose(VERBOSITY_MEDIUM, 'Parsing took ' . round((time() - $dStart)/60) . ' minutes in total.' . "\n" .
        'Number of lines with annotation error: ' . $nAnnotationErrors . ".\n");
    if (filesize($sFileError) > 0) {
        lovd_printIfVerbose(VERBOSITY_LOW, "ERROR FILE: Please check details of dropped annotation data in " . $sFileError . "\n");
    } else {
        $sFileMessage = '';
        fclose($fError);
        unlink($sFileError);
    }

    if (!$aData) {
        // No variants!
        lovd_printIfVerbose(VERBOSITY_LOW, 'No variants found to import.' . "\n");
        // Here, we won't try and remove the temp file. It will save us from running into the same error over and over again.
        continue; // Try the next file.
    }
    lovd_printIfVerbose(VERBOSITY_HIGH, 'Now creating output...' . "\n");





    // Prepare VOG and VOT column arrays, include the found columns.
    // $aVOG should still exist. Take VOG columns from there.
    foreach (array_keys($aVOG) as $sCol) {
        if (substr($sCol, 0, 16) == 'VariantOnGenome/') {
            $aColumnsForVOG[] = $sCol;
        }
    }

    // Take VOT columns from the last time we encountered an VOT.
    foreach (array_keys($aVOT) as $sCol) {
        if (substr($sCol, 0, 20) == 'VariantOnTranscript/') {
            $aColumnsForVOT[] = $sCol;
        }
    }



    // Start storing the data into the total data file.
    $fOutput = fopen($sFileTmp, 'a');
    if ($fOutput === false) {
        lovd_printIfVerbose(VERBOSITY_LOW, 'Error opening file for appending: ' . $sFileTmp . ".\n");
        exit;
    }



    // VOG data.
    $nVOGs = count($aData);
    fputs($fOutput, "\r\n" .
        '## Genes ## Do not remove or alter this header ##' . "\r\n" . // Needed to load the existing genes, otherwise we'll only have errors.
        '## Transcripts ## Do not remove or alter this header ##' . "\r\n" . // Needed to load the existing transcripts, otherwise we'll only have errors.
        '## Variants_On_Genome ## Do not remove or alter this header ##' . "\r\n" .
        '## Count = ' . $nVOGs . "\r\n" .
        '{{' . implode("}}\t{{", $aColumnsForVOG) . '}}' . "\r\n");
    $nVariant = 0;
    $nVOTs = 0;
    foreach ($aData as $sKey => $aVariant) {
        $nVariant ++;
        $nVOTs += count($aVariant) - 1;
        $nID = sprintf('%010d', $nVariant);
        $aData[$sKey][0]['id'] = $aVariant[0]['id'] = $nID;
        foreach ($aDefaultValues as $sCol => $sValue) {
            if (empty($aVariant[0][$sCol])) {
                $aVariant[0][$sCol] = $sValue;
            }
        }
        foreach ($aColumnsForVOG as $nKey => $sCol) {
            fputs($fOutput, (!$nKey? '' : "\t") . '"' . (!isset($aVariant[0][$sCol])? '' : str_replace(array("\r\n", "\r", "\n"), array('\r\n', '\r', '\n'), addslashes($aVariant[0][$sCol]))) . '"');
        }
        fputs($fOutput, "\r\n");
    }

    // Show number of Variants on Genome data created.
    lovd_printIfVerbose(VERBOSITY_MEDIUM, 'Number of Variants On Genome rows created: ' . $nVOGs . "\n");



    // VOT data.
    fputs($fOutput, "\r\n\r\n" .
        '## Variants_On_Transcripts ## Do not remove or alter this header ##' . "\r\n" .
        '## Count = ' . $nVOTs . "\r\n" .
        '{{' . implode("}}\t{{", $aColumnsForVOT) . '}}' . "\r\n");
    foreach ($aData as $aVariant) {
        $nID = $aVariant[0]['id'];
        unset($aVariant[0]);
        foreach ($aVariant as $aVOT) {
            // Loop through all VOTs.
            $aVOT['id'] = $nID;
            foreach ($aDefaultValues as $sCol => $sValue) {
                if (empty($aVOT[$sCol])) {
                    $aVOT[$sCol] = $sValue;
                }
            }
            foreach ($aColumnsForVOT as $nKey => $sCol) {
                fputs($fOutput, (!$nKey? '' : "\t") . '"' . (!isset($aVOT[$sCol])? '' : str_replace(array("\r\n", "\r", "\n"), array('\r\n', '\r', '\n'), addslashes($aVOT[$sCol]))) . '"');
            }
            fputs($fOutput, "\r\n");
        }
    }

    // Show number of Variants on Transcripts data created.
    lovd_printIfVerbose(VERBOSITY_MEDIUM, 'Number of Variants On Transcripts rows created: ' . $nVOTs . "\n");



    // Link all variants to the screening.
    fputs($fOutput, "\r\n" .
        '## Screenings_To_Variants ## Do not remove or alter this header ##' . "\r\n" .
        '## Count = ' . count($aData) . "\r\n" .
        '{{screeningid}}' . "\t" . '{{variantid}}' . "\r\n");
    for ($nVariant = 1; $nVariant <= count($aData); $nVariant ++) {
        $nID = sprintf('%010d', $nVariant);
        fputs($fOutput, '"' . $nScreeningID . "\"\t\"" . $nID . "\"\r\n");
    }



    fclose($fOutput); // Close output file.
    // Now move the tmp to the final file, and close this loop.
    if (!rename($sFileTmp, $sFileDone)) {
        // Fatal error, because we're all done actually!
        lovd_printIfVerbose(VERBOSITY_LOW, 'Error moving temp file to target: ' . $sFileDone . ".\n");
        exit;
    }

    // OK, so file is done, and can be scheduled now. Just auto-schedule it, overwriting any possible errored entry.
    // FIXME: This can also be done with one INSERT ON DUPLICATE KEY UPDATE query.
    if ($_DB->query('INSERT IGNORE INTO ' . TABLE_SCHEDULED_IMPORTS . ' (filename, scheduled_by, scheduled_date) VALUES (?, 0, NOW())', array(basename($sFileDone)))->rowCount()) {
        lovd_printIfVerbose(VERBOSITY_MEDIUM, 'File scheduled for import.' . "\n");
    } elseif ($_DB->query('UPDATE ' . TABLE_SCHEDULED_IMPORTS . ' SET in_progress = 0, scheduled_by = 0, scheduled_date = NOW(), process_errors = NULL, processed_by = NULL, processed_date = NULL WHERE filename = ?', array(basename($sFileDone)))->rowCount()) {
        lovd_printIfVerbose(VERBOSITY_MEDIUM, 'File rescheduled for import.' . "\n");
    } else {
        lovd_printIfVerbose(VERBOSITY_LOW, 'Error scheduling file for import!' . "\n");
    }

    lovd_printIfVerbose(VERBOSITY_LOW, 'All done, ' . $sFileDone . ' ready for import.' . "\n" . 'Current time: ' . date('Y-m-d H:i:s') . "\n" .
          '  Took ' . round((time() - $dStart)/60) . ' minutes.' . "\n");
    lovd_printIfVerbose(VERBOSITY_LOW, "\n");
    break;// Keep this break in the loop, so we will only continue the loop to the next file when there is a continue;
}
?>
