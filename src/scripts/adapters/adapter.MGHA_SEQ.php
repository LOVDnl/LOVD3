#!/usr/bin/php
<?php

/*******************************************************************************
 * CREATE META DATA FILES FOR MGHA
 * Created: 2016-08-29
 * Programmer: Juny Kesumadewi
 *************/

// We are using a symlink to include this file so any further includes relative to this file needs to use the symlink path instead of the actual files path.
define('ROOT_PATH', realpath(dirname($_SERVER["SCRIPT_FILENAME"])) . '/../../');
define('FORMAT_ALLOW_TEXTPLAIN', true);

define('MISSING_COL_INDEX', -1);
define('MAX_NUM_INDIVIDUALS', 1);
define('COMMENT_START', '##');
define('HEADER_START', '#');
define('BATCH_FOLDER_PREFIX', 'batch');
define('BATCH_FOLDER_DELIMITER', '_');

define('ERROR_OPEN_FILES', 51);
define('ERROR_MISSING_FILES', 52);
define('ERROR_INCORRECT_FORMAT', 53);
define('ERROR_INVALID_METADATA', 54);


$_GET['format'] = 'text/plain';

// To prevent notices when running inc-init.php.
$_SERVER = array_merge($_SERVER, array(
    'HTTP_HOST' => 'localhost',
    'REQUEST_URI' => 'scripts/adapter.php',
    'QUERY_STRING' => '',
    'REQUEST_METHOD' => 'GET',
));

require_once ROOT_PATH . 'inc-init.php';
require_once ROOT_PATH . 'inc-lib-genes.php';
require_once ROOT_PATH . 'scripts/adapters/adapter.lib.MGHA_SEQ.php';

ini_set('memory_limit', '4294967296');


if ($argc != 1 && in_array($argv[1], array('--help', '-help', '-h', '-?'))) {
    printHelp();
    exit();
}

// Fix any trailing slashes in the path to the data files.
$_INI['paths']['data_files'] = rtrim($_INI['paths']['data_files'], "\\/") . "/";

$sMetaFile = validateMetaDataFile($_INI['paths']['data_files']);
print("> Required metadata file exists\n");

// Now validate the metadata VALUES.
$aAllMetadata = getMetaData($sMetaFile);
print("> Metadata values validated\n");

// Validate if we have all the required batch folders specified in the metadata.
$aBatchFolders = validateBatchFolders($aAllMetadata);
print("> Batch folders names validated\n");

// Loop through each batch folder to be processed
foreach($aBatchFolders as $sBatchFolderName => $aBatchData) {
    print("> Processing " . $sBatchFolderName . "\n");

    $aMetadata = $aAllMetadata[$aBatchData['metadataKey']];
    $sBatchNumber = $aBatchData['batchNumber'];
    $sIndividualID = $aBatchData['sampleId'];
    $sRunID = $aBatchData['runId'];
    $sBatchFolderPath = $_INI['paths']['data_files'] . $sBatchFolderName;

    // Check if all the required tsv files exist.
    $aVariantFiles = validateVariantFiles($sBatchFolderPath, $sIndividualID);
    print("> Required variant files exist\n");

    // Get database ID of the individual to be processed in this batch.
    $sIndDBID = getIndividualDBID($aMetadata);

    // Create output files
    foreach (getVariantFileTypes() as $sType => $sPrefix) {
        createMetaFile($sType, $sBatchNumber, $sIndividualID, $sRunID, $aMetadata, $sIndDBID);
        print("> $sType meta file created\n");

        reformatVariantFile($aVariantFiles[$sIndividualID][$sType], $sType, $sBatchNumber, $sIndividualID, $sRunID);
        print("> $sType variant file created\n");
    }

    archiveBatchFolder($sBatchFolderPath);
    print("> batch folder archived\n");
}

archiveMetadataFile($sMetaFile);
print("> sample metadata file archived\n");





function getVariantFileTypes() {
    // All variant file types and their filename prefixes.
    $aFileTypes = array(

        // Leave only tumour--normal_combined on for now as requested.
        'tnc' => 'tumour--normal_combined',
        //'tnm' => 'tumour_normal_merged',
        //'t' => 'tumour_HAP',
        //'n' => 'normal_HAP'
    );

    return $aFileTypes;
}





function getColumnMappings() {
    // Mapping vep columns to lovd columns

    $aColumnMappings = array(
        'Sample_ID' => 'Individual/Sample_ID',
        'Tumour_Sample_ID' => 'Screening/Tumour/Sample_ID',
        'Normal_Sample_ID' => 'Screening/Normal/Sample_ID',

        'Sex' => 'Individual/Gender',
        'Tumour_Fastq_Files' => 'Screening/Tumour/FastQ_files',
        'Normal_Fastq_Files' => 'Screening/Normal/FastQ_files',
        'Notes' => 'Screening/Notes',
        'pipeline_path' => 'Screening/Pipeline/Path',
        'pipeline_run_id' => 'Screening/Pipeline/Run_ID',

        'DNA_Tube_ID' => 'Screening/DNA/Tube_ID',
        'DNA_Concentration' => 'Screening/DNA/Concentration',
        'DNA_Volume' => 'Screening/DNA/Volume',
        'DNA_Quantity' => 'Screening/DNA/Quantity',
        'DNA_Quality' => 'Screening/DNA/Quality',
        'DNA_Date' => 'Screening/DNA/Date',
        'Cohort' => 'Individual/Cohort',
        'Sample_Type' => 'Screening/Sample/Type',
        'Prioritised_Genes' => 'Screening/Prioritised_genes',
        'Consanguinity' => 'Individual/Consanguinity',
        'Variants_File' => 'Screening/Variants_file',
        'Pedigree_File' => 'Screening/Pedigree_file',
        'Ethnicity' => 'Individual/Origin/Ethnic',
        'VariantCall_Group' => 'Screening/Variant_call_group',
        'Capture_Date' => 'Screening/Capture_date',
        'Sequencing_Date' => 'Screening/Sequencing_date',
        'Mean_Coverage' => 'Screening/Mean_coverage',
        'Duplicate_Percentage' => 'Screening/Duplicate_percentage',
        'Machine_ID' => 'Screening/Machine_ID',
        'DNA_Extraction_Lab' => 'Screening/DNA_extraction_lab',
        'Sequencing_Lab' => 'Screening/Sequencing_lab',
        'Exome_Capture' => 'Screening/Exome_capture',
        'Library_Preparation' => 'Screening/Library_preparation',
        'Barcode_Pool_Size' => 'Screening/Barcode_pool_size',
        'Read_Type' => 'Screening/Read_type',
        'Machine_Type' => 'Screening/Machine_type',
        'Sequencing_Chemistry' => 'Screening/Sequencing_chemistry',
        'Sequencing_Software' => 'Screening/Sequencing_software',
        'Demultiplex_Software' => 'Screening/Demultiplex_software',
        'Hospital_Centre' => 'Individual/Hospital_centre',
        'Sequencing_Contact' => 'Screening/Sequencing_contact',
        'Pipeline_Contact' => 'Screening/Pipeline_contact',
        'Pipeline_Notes' => 'Screening/Pipeline/Notes',
        'Analysis_Type' => 'Screening/Analysis_type'
    );

    return $aColumnMappings;
}





function reformatVariantFile($sVariantFile, $sType, $sBatch, $sIndividual, $sRunId) {
    // Create a new copy of the variant file with the following changes:
    // - Remove all comment lines that start with '##'.
    // - Remove '#' from the start of header line.
    // - Rename the file to batchNumber_IndividualID.tsvType.directvep.data.lovd.

    global $_INI;

    $sNewVariantFileName = $_INI['paths']['data_files'] . $sBatch . "_" . $sIndividual . "_" . $sRunId . "." . $sType . '.directvep.data.lovd';
    $fOutput = fopen($sNewVariantFileName, 'w');

    if (empty($fOutput)) {
        print('ERROR: failed to create new variant file ' . $sNewVariantFileName);
        exit(ERROR_OPEN_FILES);
    }

    $fInput = fopen($sVariantFile, 'r');

    while (($sLine = fgets($fInput)) !== false) {
        $sLine = trim($sLine, " \n");

        // Skip empty lines.
        if (empty($sLine)) {
            continue;
        }

        // Skip commented out lines.
        if (strpos($sLine, COMMENT_START) === 0) {
            continue;
        }

        // Remove # (and any extra spaces that follow) from header start of line.
        $sLine = ltrim($sLine, " " . HEADER_START);

        // Print all non-comments line in the new reformatted variant file.
        fputs($fOutput, $sLine . "\n");
    }

    fclose($fInput);
    fclose($fOutput);
}





function createMetaFile($sType, $sBatch, $sIndividual, $sRunID, $aMetadata, $sIndDBID) {
    // Create meta file for each variant file.

    global $_INI;

    $sNewMetaFileName = $_INI['paths']['data_files'] . $sBatch . "_" . $sIndividual . "_" . $sRunID . "." . $sType . '.meta.lovd';

    // Build 'Individual' columns.
    $aColumnsForIndividual = array (
        'panel_size' => 1,
        'id' => $sIndDBID
    );
    $aColumnsForIndividual = $aColumnsForIndividual + getCustomColumnsData('Individual/', $aMetadata);

    $aBamFiles = array(
        't' => 'tumour_merged.markdups.bam',
        'n' => 'normal_merged.markdups.bam',
        'tnc' => 'tumour_normal_merged.tmp.realign.recal.bam',
        'tnm' => 'tumour_normal_merged.tmp.realign.recal.bam'
    );

    // Build 'Screening' columns.
    $aColumnsForScreening = array(
        'individualid' => $sIndDBID,
        'variants_found' => '1',
        'id' => '1',
        'id_sample' => '0',
        'Screening/Pipeline/Path' => $sType,
        'Screening/Pipeline/Run_ID' => $sBatch . '_' . $sRunID,
    );

    $aColumnsForScreening = $aColumnsForScreening + getCustomColumnsData('Screening/', $aMetadata);

    $sOutputData =
        '### LOVD-version 3000-080 ### Full data download ### To import, do not remove or alter this header ###' . "\r\n" .
        '# charset = UTF-8' . "\r\n\r\n" .
        '## Diseases ## Do not remove or alter this header ##' . "\r\n\r\n" .
        '## Individuals ## Do not remove or alter this header ##' . "\r\n" .
        '"{{' . implode("}}\"\t\"{{", array_keys($aColumnsForIndividual)) . '}}"' . "\r\n" .
        '# "' . implode("\"\t\"", array_values($aColumnsForIndividual)) . '"' . "\r\n\r\n" .
        '## Individuals_To_Diseases ## Do not remove or alter this header ##' . "\r\n\r\n" .
        '## Screenings ## Do not remove or alter this header ##' . "\r\n" .
        '"{{' . implode("}}\"\t\"{{", array_keys($aColumnsForScreening)) . '}}"' . "\r\n" .
        '"' . implode("\"\t\"", array_values($aColumnsForScreening)) . '"' . "\r\n";

    $fOutput = fopen($sNewMetaFileName, 'w');
    fputs($fOutput, $sOutputData);
    fclose($fOutput);
}





function getCustomColumnsData($sColPrefix, $aMetadata) {
    // A helper function to get the custom columns listed on columnMappings list and their data.

    $aColumns = array();
    foreach (getColumnMappings() as $sPipelineColumn => $sLOVDColumn) {
        if (substr($sLOVDColumn, 0, strlen($sColPrefix)) === $sColPrefix) {
            $aColumns[$sLOVDColumn] = (empty($aMetadata[$sPipelineColumn]) || $aMetadata[$sPipelineColumn] == '.' ? '' : $aMetadata[$sPipelineColumn]);
        }
    }

    return $aColumns;
}





function archiveBatchFolder($sBatchPath) {
    global $_INI;

    $sArchivePath = $_INI['paths']['data_files'] . 'archives';
    if (!file_exists($sArchivePath)) {
        mkdir($sArchivePath);
    }

    $sBatchFolderName = basename($sBatchPath);
    if (!rename($sBatchPath, $sArchivePath . '/archived_' . time() . '_' . $sBatchFolderName)) {
        print("ERROR: failed to archive batch folder " . $sBatchPath);
        exit(ERROR_OPEN_FILES);
    }

    return true;
}





function archiveMetadataFile($sMetadataFile) {
    if (!rename($sMetadataFile, $sMetadataFile . '.' . time() . '.ARK')) {
        print("ERROR: failed to archive sample metadata file " . $sMetadataFile);
        exit(ERROR_OPEN_FILES);
    }

    return true;
}





function getIndividualDBID($aMetadata) {
    // Get database ID of the given individual ID provided in the metadata file.
    // If the individual has been inserted in the database in the past, then, simply retrieve the database ID.
    // If the individual does not already exist in the database, then create this new individual in the database an returns this new database ID.

    global $_DB;

    $aMetadata['individual_exists'] = false;
    $sIndividualID = $aMetadata['Sample_ID'];
    $sIndDBID = $_DB->query('SELECT `id` FROM ' . TABLE_INDIVIDUALS . ' WHERE `Individual/Sample_ID` = ?', array($sIndividualID))->fetchColumn();

    // If the individual does not already exist in the database, then create it.
    if (!$sIndDBID) {
        // Prepare the columns for inserting the individual record.
        $aIndFields = array(
            'panel_size' => 1,
            'owned_by' => 0,
            'custom_panel' => '',
            'statusid' => 4,
            'created_by' => 0,
            'created_date' => date('Y-m-d H:i:s'),
        );

        // Add in any custom columns for the individual.
        $aIndFields = $aIndFields + getCustomColumnsData('Individual/', $aMetadata);
        $_DB->query('INSERT INTO ' . TABLE_INDIVIDUALS . ' (`' . implode('`, `', array_keys($aIndFields)) . '`) VALUES (?' . str_repeat(', ?', count($aIndFields) - 1) . ')', array_values($aIndFields));
        $sIndDBID = sprintf('%08d', $_DB->lastInsertId());
    }

    return $sIndDBID;
}





function getMetaData($sMetaDataFilename) {
    // Validate if the metadata provided is in the correct format and returns the metadata if everything is valid.
    // It will print error and stop the script if it is invalid.
    // It does the following validations:
    // - Whether all expected columns are in the file (Order does not matter. Extra columns are also allowed).
    // - Whether the file only has one row of data (One individual only).
    // - Whether the Batch ID in the metadata matches the Batch ID of the processed folder name.
    // - Whether the Individual ID in the metadata matches the Individual ID of the processed folder name.
    //
    // When we pass all validations, we reformat the metadata to a format that LOVD understands
    // and returned them as an array keyed by their column names.


    if (!($fMetaData = fopen($sMetaDataFilename, 'r'))) {
        print("ERROR: failed to open file " . $sMetaDataFilename . "\n");
        exit(ERROR_OPEN_FILES);
    }

    $sDelimiter = "\t";
    $aExpectedColumns = array(
        'Batch' => MISSING_COL_INDEX,
        'Sample_ID' => MISSING_COL_INDEX,
        'Sex' => MISSING_COL_INDEX,
        'Tumour_Fastq_Files' => MISSING_COL_INDEX,
        'Normal_Fastq_Files' => MISSING_COL_INDEX,
        'Notes' => MISSING_COL_INDEX,

        'DNA_Tube_ID' => MISSING_COL_INDEX,
        'DNA_Concentration' => MISSING_COL_INDEX,
        'DNA_Volume' => MISSING_COL_INDEX,
        'DNA_Quantity' => MISSING_COL_INDEX,
        'DNA_Quality' => MISSING_COL_INDEX,
        'DNA_Date' => MISSING_COL_INDEX,
        'Cohort' => MISSING_COL_INDEX,
        'Sample_Type' => MISSING_COL_INDEX,
        'Prioritised_Genes' => MISSING_COL_INDEX,
        'Consanguinity' => MISSING_COL_INDEX,
        'Variants_File' => MISSING_COL_INDEX,
        'Pedigree_File' => MISSING_COL_INDEX,
        'Ethnicity' => MISSING_COL_INDEX,
        'VariantCall_Group' => MISSING_COL_INDEX,
        'Capture_Date' => MISSING_COL_INDEX,
        'Sequencing_Date' => MISSING_COL_INDEX,
        'Mean_Coverage' => MISSING_COL_INDEX,
        'Duplicate_Percentage' => MISSING_COL_INDEX,
        'Machine_ID' => MISSING_COL_INDEX,
        'DNA_Extraction_Lab' => MISSING_COL_INDEX,
        'Sequencing_Lab' => MISSING_COL_INDEX,
        'Exome_Capture' => MISSING_COL_INDEX,
        'Library_Preparation' => MISSING_COL_INDEX,
        'Barcode_Pool_Size' => MISSING_COL_INDEX,
        'Read_Type' => MISSING_COL_INDEX,
        'Machine_Type' => MISSING_COL_INDEX,
        'Sequencing_Chemistry' => MISSING_COL_INDEX,
        'Sequencing_Software' => MISSING_COL_INDEX,
        'Demultiplex_Software' => MISSING_COL_INDEX,
        'Hospital_Centre' => MISSING_COL_INDEX,
        'Sequencing_Contact' => MISSING_COL_INDEX,
        'Pipeline_Contact' => MISSING_COL_INDEX,
        'Pipeline_Notes' => MISSING_COL_INDEX,
        'Analysis_Type' => MISSING_COL_INDEX
    );

    $bHeaderRead = false;
    $aAllMetadata = array();
    while (($sLine = fgets($fMetaData)) !== false) {
        $sLine = trim($sLine, " \n");
        if (empty($sLine)) {
            continue;
        }

        // Process header.
        if (!$bHeaderRead) {
            $aHeader = explode($sDelimiter, $sLine);
            foreach ($aHeader as $nIndex => $sColumn) {
                $aExpectedColumns[$sColumn] = $nIndex;
            }

            // Check if we get all the required columns.
            if (in_array(MISSING_COL_INDEX, $aExpectedColumns)) {
                print("Metadata file is missing the following columns:\n");
                foreach ($aExpectedColumns as $sColumn => $nIndex) {
                    if ($nIndex === MISSING_COL_INDEX) {
                        print($sColumn . "\n");
                    }
                }
                exit(ERROR_INCORRECT_FORMAT);
            }

            $bHeaderRead = true;
            continue;
        }


        // Process data.
        $aLine = explode($sDelimiter, $sLine);

        $aMetadata = array();
        foreach ($aExpectedColumns as $sColName => $nIndex) {
            $aMetadata[$sColName] = formatMetadataValue($sColName, $aLine[$nIndex]);
        }

        // Additional metadata columns that does not already exist in the samples metadata file provided.
        $aMetadata = appendMetadata($aMetadata);

        $sBatch = $aMetadata['Batch'];
        $sIndividual = $aMetadata['Sample_ID'];
        $sKey = implode(BATCH_FOLDER_DELIMITER, array($sBatch, $sIndividual));
        $aAllMetadata[$sKey] = $aMetadata;
    }

    fclose($fMetaData);
    return $aAllMetadata;
}





function formatMetadataValue($sColName, $sRawValue) {
    // Reformat a column of metadata to a format that LOVD understands.

    switch ($sColName) {
        case 'Sex':
            $aGenderMaps = array(
                'female' => 'F',
                'f' => 'F',
                'male' => 'M',
                'm' => 'M'
            );
            $sRawValue = strtolower($sRawValue);
            return (empty($aGenderMaps[$sRawValue]) ? '?' : $aGenderMaps[$sRawValue]);
        default:
            return $sRawValue;
    }

}





function appendMetadata($aMetadata) {
    // Add metadata columns that are not originally in the samples metadata file.
    $aSamples = array('Normal', 'Tumour');
    foreach ($aSamples as $sSample) {
        if (!isset($aMetadata[$sSample . '_Sample_ID'])) {
            $aMetadata[$sSample . '_Sample_ID'] = '';
            if (!empty($aMetadata[$sSample . '_Fastq_Files'])) {
                $sFastqFiles = trim($aMetadata[$sSample . '_Fastq_Files']);

                // There might be multiple fastq files separated by comma.
                // But, it does not matter here. We only need the first prefix of the first fastq file.
                $aParts = explode('_', $sFastqFiles);
                if (count($aParts) >= 1) {
                    $aMetadata[$sSample . '_Sample_ID'] = $aParts[0];
                }
            }
        }
    }

    return $aMetadata;
}





function validateBatchFolders($aAllMetadata)
{
    global $_INI;

    $aExpectedParts = array(
        BATCH_FOLDER_PREFIX => '',
        '[BATCH NUMBER]' => 'Batch number must not contain ' . BATCH_FOLDER_DELIMITER,
        '[SAMPLE ID]' => 'SAMPLE ID must not contain ' . BATCH_FOLDER_DELIMITER,
        '[RUN ID]' => 'Run ID must not contain ' . BATCH_FOLDER_DELIMITER,
    );

    $aBatchFolders = array();
    $bBatchMissing = false;

    foreach ($aAllMetadata as $sBatchName => $aMetadata) {
        $sValidBatchFolderName = $_INI['paths']['data_files'] . BATCH_FOLDER_PREFIX . "_" . $sBatchName . "_*";
        $aFoundFolders = glob($sValidBatchFolderName);

        // If at least one batch in metadata is not found, we do NOT want to proceed.
        // But, we want to print out all missing folders.
        if (empty($aFoundFolders)) {
            print("ERROR: batch folder " . $sValidBatchFolderName . " does not exist\n");
            $bBatchMissing = true;
        }

        foreach ($aFoundFolders as $sBatchFolderName) {
            // Validate if batch folder name is in the correct format.
            $sBatchFolderName = basename($sBatchFolderName);
            $parts = explode(BATCH_FOLDER_DELIMITER, $sBatchFolderName);
            if (count($parts) !== count($aExpectedParts)) {
                print("ERROR: Invalid batch folder name " . $sBatchFolderName . "\n");
                $bBatchMissing = true;
            } else {
                $aBatchFolders[$sBatchFolderName] = array(
                    'metadataKey' => $sBatchName,
                    'batchNumber' => $parts[1],
                    'sampleId' => $parts[2],
                    'runId' => $parts[3],
                );
            }
        }
    }

    if ($bBatchMissing) {
        print("ERROR: batch folder name must follow this pattern " . implode(BATCH_FOLDER_DELIMITER, array_keys($aExpectedParts)) . "\n");
        foreach ($aExpectedParts as $sPart => $sMessage) {
            if (!empty($sMessage)) {
                print($sPart . ": " . $sMessage . "\n");
            }
        }
        exit(ERROR_MISSING_FILES);
    }

    // Returns an array keyed by batch folder name.
    // Each item is an array that contains the batch details.
    return $aBatchFolders;
}





function validateMetaDataFile($sPath) {
    // Validate if metadata file EXISTS in this batch folder.

    $aMetadataFiles = glob($sPath . '*.meta');
    if (count($aMetadataFiles) > 1) {
        print("ERROR: More than one metadata file found\nPlease keep only one correct metadata file in the batch folder\n");
        exit(ERROR_MISSING_FILES);
    }

    foreach($aMetadataFiles as $sFileName) {
        return $sFileName;
    }

    print("ERROR: Missing metadata file\n");
    exit();
}





function validateVariantFiles($sPath, $sIndividual) {
    // Get all the variant file names listed in getVariantFileTypes().

    $aVariantFiles = array();
    $aMissingFiles = array();

    foreach (getVariantFileTypes() as $sType => $sPrefix) {
        $aFiles = glob($sPath . '/' . $sPrefix . '*.tsv');
        if (!empty($aFiles[0])) {
            $aVariantFiles[$sIndividual][$sType] = $aFiles[0];
        } else {
            $aMissingFiles[] = $sPrefix;
        }
    }

    // If one or more variant file is not found, exit
    if (!empty($aMissingFiles)) {
        print("ERROR: Missing variant files with prefix:\n");
        foreach ($aMissingFiles as $sMissingFile) {
            print($sMissingFile . "\n");
        }
        exit(ERROR_MISSING_FILES);
    }

    return $aVariantFiles;
}





function printHelp() {
    ?>
    This is a command line PHP script which creates meta data files for MGHA Seqliner that are compatible with LOVD
    After this script is executed the convert_and_merge_data_files.php script should be run to merge
    the meta data files with the variant files. The final merged file should then be imported into LOVD

    Error Handling
    <?php echo ERROR_OPEN_FILES ?> - Error opening or renaming files or directories
    <?php echo ERROR_MISSING_FILES ?> - Required files are missing. Check sample meta data file or variant files
    <?php echo ERROR_INCORRECT_FORMAT ?> - File does not conform to expected format. Check sample meta data file.
    <?php echo ERROR_INVALID_METADATA ?> - Unexpected metadata values.
    <?php
}