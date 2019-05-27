#!/usr/bin/php
<?php
/*******************************************************************************
 * CREATE META DATA FILES FOR MGHA
 * Created: 2016-04-22
 * Programmer: Candice McGregor
 *************/

// IMPORTANT: This file is also used MGHA_CPIPE_LYMPHOMA instance.
// Changes on this file will affect adapter.MGHA_CPIPE_LYMPHOMA.php.

// We are using a symlink to include this file so any further includes relative to this file needs to use the symlink path instead of the actual files path.
define('ROOT_PATH', str_replace('\\', '/', realpath(dirname($_SERVER["SCRIPT_FILENAME"])) . '/../../'));
define('FORMAT_ALLOW_TEXTPLAIN', true);
$_GET['format'] = 'text/plain';

// To prevent notices when running inc-init.php.
$_SERVER = array_merge($_SERVER, array(
    'HTTP_HOST' => 'localhost',
    'REQUEST_URI' => 'scripts/adapter.php',
    'QUERY_STRING' => '',
    'REQUEST_METHOD' => 'GET',
));

require ROOT_PATH . 'inc-init.php';
require ROOT_PATH . 'inc-lib-genes.php';
ini_set('memory_limit', '4294967296');


if ($argc != 1 && in_array($argv[1], array('--help', '-help', '-h', '-?'))) {
    ?>

    This is a command line PHP script which creates meta data files for MGHA that are compatible with LOVD
    After this script is executed the convert_and_merge_data_files.php script should be run to merge
    the meta data files with the variant files. The final merged file should then be imported into LOVD

    Error Handling
    51 - Error opening or renaming files or directories
    52 - Required files are missing. Check sample meta data file or variant files
    53 - File does not conform to expected format. Check sample meta data file.
    54 - Unexpected gender for parent. Either two females, two males or gender other than male or female
    <?php
} else {

    /*******************************************************************************/
    // Set variables.
    $aVariantFiles = array();
    $sMetaFile = '';

    // Create mapping arrays for singleton/child record, mother and father.
    $aColumnMappings = array(
        'Pipeline_Run_ID' => 'Screening/Pipeline/Run_ID',
        'Batch' => 'Screening/Batch',
        'Sample_ID' => 'Individual/Sample_ID',
        'DNA_Tube_ID' => 'Screening/DNA/Tube_ID',
        'Sex' => 'Individual/Gender',
        'DNA_Concentration' => 'Screening/DNA/Concentration',
        'DNA_Volume' => 'Screening/DNA/Volume',
        'DNA_Quantity' => 'Screening/DNA/Quantity',
        'DNA_Quality' => 'Screening/DNA/Quality',
        'DNA_Date' => 'Screening/DNA/Date',
        'Cohort' => 'Individual/Cohort',
        'Sample_Type' => 'Screening/Sample/Type',
        'Fastq_Files' => 'Screening/FastQ_files',
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
        'Notes' => 'Screening/Notes',
        'Pipeline_Notes' => 'Screening/Pipeline/Notes',
        'Analysis_Type' => 'Screening/Analysis_type'

    );

    // These are the columns for the mother and father.
    // As they are the same columns for both,
    // we will loop through and replace "Parent" with "Father" and "Mother" before writing out the data.
    $aParentColumnMappings = array(
        'Sample_ID' => 'Screening/Parent/Sample_ID',
        'Ethnicity' => 'Screening/Parent/Origin/Ethnic',
        'DNA_Tube_ID' => 'Screening/Parent/DNA/Tube_ID',
        'Notes' => 'Screening/Parent/Notes'
    );

    // Individual default values if injecting record into the database for trios.
    $aIndDefaultValues = array(
        'custom_panel' => ''
    );

    // Screening default values.
    $aDefaultValues = array(
        'variants_found' => 1,
        'id' => 1,
        'id_sample' => 0
    );

    // Open the data files folder and process files.
    $h = opendir($_INI['paths']['data_files']);

    if (!$h) {
        print('Can\'t open directory.' . "\n");
        die(51);
    }

    // Fix any trailing slashes in the path to the data files.
    $_INI['paths']['data_files'] = rtrim($_INI['paths']['data_files'], "\\/") . "/";

    // Find the sample meta data file (SMDF).
    // All processed SMDF should be renamed to .ARK.
    while (($aFiles = readdir($h)) !== false) {
        if ($aFiles{0} == '.') {
            // Current dir, parent dir, and hidden files.
            continue;
        }

        // Get the SMDF, it is possible there could be more than one, but we are only going to take the first one.
        // We have discussed that this means there is the potential that any subsequent SMDF will not be processed
        // until any issues with the first one are addressed, at this stage we are not concerned with this.

        if (preg_match('/^.+?\.meta$/', $aFiles)) {

            $sMetaFile = $_INI['paths']['data_files'] . $aFiles;
            $sArchiveMetaFile = $aFiles . '.ARK';
        }


        if (preg_match('/^(.+?)\.tsv$/', $aFiles, $aRegs)) {
            // Get all the variant files into an array.
            $sVariantFilePrefix = explode('_', $aRegs[1]);

            if (count($sVariantFilePrefix) !== 4) {
                // Invalid number of expected underscores in file name.
                print('Invalid number of underscores used in variant file name for file ' . $aFiles . "\n" .
                    'Format should be site_n.n.n_batchnumber_sampleID.individual|trio.lovd.tsv');
                die(52);

            } else {
                $aFileSampleIDs = explode('.', $sVariantFilePrefix[3]);
                if (count($aFileSampleIDs) !== 3) {
                    // Invalid number of periods in file name.
                    print('Invalid number of periods used in variant file name for file ' . $aFiles . "\n" .
                        'Format should be site_n.n.n_batchnumber_sampleID.individual|trio.lovd.tsv');
                    die(52);
                } else {
                    $sID = $aFileSampleIDs[0];
                    $sFileType = $aFileSampleIDs[1];
                    $aVariantFiles[$sID][$sFileType] = $aFiles;
                }
            }
        }

    }


    if (!$sMetaFile && !empty($aVariantFiles)) {
        // No SMDF found and tsv variant files are found, so we do not continue.
        print('Variant files found without a sample meta data file' . ".\n");
        die(52);

    } elseif (!$sMetaFile) {
        return;
    }

    // Set arrays.
    $aDataArr = array();
    $aParentArr = array();

    // Open the file, get first line as string to check headers match expected output.
    $fInput = fopen($sMetaFile, 'r');
    if ($fInput === false) {
        print('Error opening file: ' . $sMetaFile . ".\n");
        die(51);
    }

    $sHeaders = fgets($fInput);

    if (substr($sHeaders, 0, 76) != "Pipeline_Run_ID\tBatch\tSample_ID\tDNA_Tube_ID\tSex\tDNA_Concentration\tDNA_Volume") {
        // SMDF does not conform to expected format.
        print('Sample meta file ' . $sMetaFile . ' does not conform to expected format.' . ".\n");
        print('Format expected is: Pipeline_Run_ID Batch Sample_ID DNA_Tube_ID Sex DNA_Concentration DNA_Volume' . ".\n");
        die(53);
    }

    fclose($fInput);

    // Open the sample meta data file into an array.
    $sFile = file($sMetaFile, FILE_IGNORE_NEW_LINES);

    // Create an array of headers from the first line.
    $sHeader = explode("\t", $sFile[0]);

    foreach ($sFile as $nKey => $sValue) {
        // Loop through each line and add some columns to the array.

        if ($nKey > 0) {
            // Skips the first line.
            $sValues = explode("\t", $sValue);
            $sValues = array_combine($sHeader, $sValues);
            $sValues['trio'] = null;
            $sValues['parent'] = null;
            $sValues['mother_id'] = null;
            $sValues['father_id'] = null;
            $aDataArr[$sValues['Sample_ID']] = $sValues;

            // Get the pipeline run ID.
            $sPipelineRunID = $sValues['Pipeline_Run_ID'];
        }
    }

    // Array of expected values for Pedigree File when the sample is not a pro band.
    $aPedigreeArray = array('ind', 'imp', 'exc');
    $sExpectedValuesStr = 'Expected values are individual, exclude, import or the family ID listing the parent IDs i.e (trio001=F20492801,F20445601)';

    // Loop through each sample in the SMDF.
    foreach ($aDataArr as $aSamples) {

        $sDataSample = $aSamples['Sample_ID'];

        if (preg_match('/^(.+?)\=(.+)/', trim($aSamples['Pedigree_File']), $aPedigree)) {
            // Check the pedigree file column to ensure it is not empty and is
            // (import or exclude) for parents, individual for singletons or
            // contains a family id with parent sample IDs.

            $aParents = explode(",", $aPedigree[2]);
            $nFatherCount = 0;
            $nMotherCount = 0;

            // Loop through parent sample IDs and check gender.
            foreach ($aParents as $sParentID) {

                $sParentGender = $aDataArr[$sParentID]['Sex'];

                if (trim(strtoupper(substr($sParentGender, 0, 1)) == 'M')) {
                    // Gender is male so this is the father.
                    $sFatherID = $sParentID;
                    $sParent = 'Father';
                    $nFatherCount++;
                    if ($nFatherCount > 1) {
                        // Father count is > 1 therefore we have an issue.
                        print('We have 2 parent IDs with the gender Male for sample ID ' . $sDataSample . ".\n");
                        die(54);
                    }

                } elseif (trim(strtoupper(substr($sParentGender, 0, 1)) == 'F')) {
                    // Gender is female so this is the mother.
                    $sMotherID = $sParentID;
                    $sParent = 'Mother';
                    $nMotherCount++;
                    if ($nMotherCount > 1) {
                        // Mother count is > 1 therefore we have an issue.
                        print('We have 2 parent IDs with the gender Female for sample ID ' . $sDataSample . ".\n");
                        die(54);
                    }

                } else {
                    // We have an unexpected gender.
                    print('Unexpected Gender: ' . $sParentGender . ' for Sample' . $sParentID . ".\n");
                    die(54);
                }

                // Loop through the parent columns and add them to $aParentArr.
                foreach ($aParentColumnMappings as $sPipelineCol => $sLOVDCol) {
                    $sParentLOVDColumn = str_replace('Parent', $sParent, $sLOVDCol);
                    $aParentArr[$sDataSample][$sParentLOVDColumn] = $aDataArr[$sParentID][$sPipelineCol];

                }
            }


            // Update the father ID and mother ID on the child's record.
            $aDataArr[$sDataSample]['father_id'] = $sFatherID;
            $aDataArr[$sDataSample]['mother_id'] = $sMotherID;

            // Update the trio flag on the child record.
            $aDataArr[$sDataSample]['trio'] = "T";


        } elseif (in_array(trim(strtolower(substr($aSamples['Pedigree_File'],0,3))), $aPedigreeArray)) {
            // Check if the value of pedigree_file meets expected values.
            $aDataArr[$sDataSample]['parent'] = strtolower($aSamples['Pedigree_File']);

        } elseif (trim(empty($aSamples['Pedigree_File']))) {
            // We have an empty value, assume that this sample is to be imported.
            $aDataArr[$sDataSample]['parent'] = 'import';

        } else {
            // Value does not match expected input, alert user and display list of expected values.
            print('Invalid entry in the Pedigree_File column of sample meta file for sample ' . $sDataSample . "\n" . $sExpectedValuesStr);
            die(53);
        }

        if (trim(strtoupper($aSamples['Consanguinity'])) == 'UNKNOWN' || $aSamples['Consanguinity'] == '') {
            // Convert unknown to ? for Consanguinity otherwise will not import into LOVD.
            $aDataArr[$sDataSample]['Consanguinity'] = '?';
        } else {
            $aDataArr[$sDataSample]['Consanguinity'] = strtolower($aDataArr[$sDataSample]['Consanguinity']);
        }


    }

    // Remove any tsv files that are not for a sample listed in the SMDF.
    foreach ($aVariantFiles as $sFileSampleID => $sVariantFileName) {

        If (!in_array($sFileSampleID, array_keys($aDataArr))) {
            unset($aVariantFiles[$sFileSampleID]);
            continue;
        }
    }


    // Check we have variant files for all the relevant samples
    foreach ($aDataArr as $aDataSamples) {
        $sID = trim($aDataSamples['Sample_ID']);
        $sParent = trim($aDataSamples['parent']);
        $sTrio = trim($aDataSamples['trio']);


        if (trim(strtoupper(substr($aDataSamples['Sex'], 0, 1)) == 'U')){
            // Update the gender to only store the first character.
            // M = Male  F = Female, Unknown = ?
            $aDataArr[$sID]['Sex'] = '?';

        }else{
            $aDataArr[$sID]['Sex'] = trim(strtoupper(substr($aDataSamples['Sex'], 0, 1)));
        }



        if (substr(strtolower(trim($sParent)),0,3) != 'exc') {
            if (!in_array($sID, array_keys($aVariantFiles))) {
                print('There is no variant file for Sample ID ' . $sID . "\n");
                die(52);

            } else {
                if ($sTrio == 'T') {

                    if (!in_array('trio', array_keys($aVariantFiles[$sID]))) {
                        print('There is no trio variant file for Sample ID ' . $sID . "\n");
                        die(52);

                    } else {
                        // Use preg_replace to update the column headers using child, father and mother sample IDs.
                        $sVariantFile = $_INI['paths']['data_files'] . $aVariantFiles[$sID]['trio'];
                        $aVariantFileArr = file($sVariantFile, FILE_IGNORE_NEW_LINES);
                        $sVariantHeader = preg_replace("/" . $sID . "\./", "Child_", $aVariantFileArr[0]);


                        if (!empty($aDataSamples['mother_id'])) {
                            $sVariantHeader = preg_replace("/" . $aDataSamples['mother_id'] . "\./", "Mother_", $sVariantHeader);
                        }


                        if (!empty($aDataSamples['father_id'])) {
                            $sVariantHeader = preg_replace("/" . $aDataSamples['father_id'] . "\./", "Father_", $sVariantHeader);
                        }

                        $aVariantFileArr[0] = $sVariantHeader;
                        file_put_contents($sVariantFile, implode(PHP_EOL, $aVariantFileArr));
                        // ********** error handling to check the contents were updated
                    }
                }



                if (!in_array('individual', array_keys($aVariantFiles[$sID]))) {
                    // We should have a tsv file for the singleton (individual).
                    print('There is no individual variant file for Sample ID ' . $sID . "\n");
                    die(52);

                } else {
                    // Use preg_replace to update the column headers using the child sample ID.
                    $sVariantFile = $_INI['paths']['data_files'] . $aVariantFiles[$sID]['individual'];
                    $aVariantFileArr = file($sVariantFile, FILE_IGNORE_NEW_LINES);
                    $sVariantHeader = preg_replace("/" . $sID . "\./", "Child_", $aVariantFileArr[0]);

                    $aVariantFileArr[0] = $sVariantHeader;
                    file_put_contents($sVariantFile, implode(PHP_EOL, $aVariantFileArr));
                    // ********** error handling to check the contents were updated
                }

            }

        }
    }


    // Loop through all the samples in the SMDF and include individual and screening columns.
    foreach ($aDataArr as $sKey => $sVal) {
        $aColumnsForScreening = array();
        $aColumnsForIndividual = array();

        if (trim($sVal['trio']) == 'T') {
            // Sample is part of a trio.
            $bTrio = true;
            $aColumnsForIndividual['panel_size'] = 3;
        }else{
            $bTrio = false;
            $aColumnsForIndividual['panel_size'] = 1;
        }


        if ($sIndDBID = $_DB->query('SELECT `id` FROM ' . TABLE_INDIVIDUALS . ' WHERE `Individual/Sample_ID` = ?', array($sVal['Sample_ID']))->fetchColumn()) {
            // Since we are generating one meta data file per child/singleton,
            // there will only ever be 1 individual record and screening.
            // Look up sample ID in the database to check if it exists.
            // If it does, then use the database ID as the individualid and id to link the new screening information

            $bIndExists = true;

            if ($bTrio && $sVal['Cohort'] == 'CN') {
                // Individual trio files for CN patients go into a separate LOVD+ database,
                // since we do not know that specific database ID we cannot continue and need to rename this file and alert user.
                print('Cannot process individual variant file for sample ' . $sVal['Sample_ID'] . ' as the sample is for cohort CN and has already been imported into the database. Please handle manually' . "\n");

            } else {
                $aColumnsForScreening['individualid'] = $sIndDBID;
                $aColumnsForIndividual['id'] = $sIndDBID;
            }

        } elseif ($bTrio && trim($sVal['Cohort']) !== 'CN') {
            // Need to insert a database record so we can get the database ID for the meta data files.
            // We only do this if the cohort is not CN, as we need the individual info
            // to be created during import as the file is imported into another database.
            // If column id was previously set we need to unset to create the record.

            // Prepare the columns for inserting the individual record.
            $aIndFields = array(
                'panel_size' => $aColumnsForIndividual['panel_size'],
                'owned_by' => 0,
                'custom_panel' => '',
                'statusid' => 4,
                'created_by' => 0,
                'created_date' => date('Y-m-d H:i:s'),
            );

            // Add in any custom columns for the individual.
            foreach ($aColumnMappings as $sPipelineColumn => $sLOVDColumn) {
                if (substr($sLOVDColumn, 0, 11) == 'Individual/') {
                    $aIndFields[$sLOVDColumn] = (empty($sVal[$sPipelineColumn]) || $sVal[$sPipelineColumn] == '.' ? '' : $sVal[$sPipelineColumn]);
                }
            }

            // Insert the individual record and return the new individual record ID.
            $_DB->query('INSERT INTO ' . TABLE_INDIVIDUALS . ' (`' . implode('`, `', array_keys($aIndFields)) . '`) VALUES (?' . str_repeat(', ?', count($aIndFields) - 1) . ')', array_values($aIndFields));
            $sIndividualID = sprintf('%08d', $_DB->lastInsertId());
            $aColumnsForScreening['individualid'] = $sIndividualID;
            $aColumnsForIndividual['id'] = $sIndividualID;
            $bIndExists = true;

        } else {
            $bIndExists = false;
            $aColumnsForScreening['individualid'] = 1;
            $aColumnsForIndividual['id'] = 1; // We should only ever be importing one individual at a time so it should be safe to hard code this to 1.
        }

        // If trio then we always create an individual and trio SMDF otherwise just individual.
        $aTypes = ($bTrio ? array('individual','trio') : array('individual'));

        if (substr(strtolower(trim($sVal['parent'])), 0, 3) != 'exc') {

            foreach ($aTypes as $sType) {

                // Add default values, we currently only have them for screening, if we get some for individual we need to handle this below.
                foreach ($aDefaultValues as $sDefaultKey => $sDefaultVal) {
                    $aColumnsForScreening[$sDefaultKey] = $sDefaultVal;
                }

                // Create the custom link data for the pipeline files for singleton.
                $aColumnsForScreening['Screening/Pipeline_files'] = '{gap:' . $sVal['Pipeline_Run_ID'] . '_' . $sVal['Sample_ID'] . '.gap.csv} {prov:' . $sVal['Pipeline_Run_ID'] . '_' . $sVal['Sample_ID'] . '.provenance.pdf} {summary:' . $sVal['Pipeline_Run_ID'] . '_' . $sVal['Sample_ID'] . '.summary.htm}';

                // Map pipeline columns to LOVD columns.
                foreach ($aColumnMappings as $pipelineColumn => $sLOVDColumn) {

                    if (empty($sVal[$pipelineColumn]) || $sVal[$pipelineColumn] == '.') {
                        $sVal[$pipelineColumn] = '';
                    }

                    if (isset($sVal['Fastq_Files'])) {
                        $aFastqFiles = explode(",", $sVal['Fastq_Files']);
                        $aBasenames = array();
                        foreach ($aFastqFiles as $fastqFile) {
                            $aBasenames[] = basename($fastqFile);
                        }
                        $sVal['Fastq_Files'] = implode(", ", $aBasenames);
                    }

                    if (substr($sLOVDColumn, 0, 11) == 'Individual/') {
                        $aColumnsForIndividual[$sLOVDColumn] = $sVal[$pipelineColumn];

                    } elseif (substr($sLOVDColumn, 0, 10) == 'Screening/') {
                        $aColumnsForScreening[$sLOVDColumn] = $sVal[$pipelineColumn];
                    }

                }

                if ($sType == 'trio') {
                    // Add trio specific columns.

                    foreach ($aParentArr as $sParentKey => $sParentVal) {
                        if ($sParentKey == $sKey) {
                            $aColumnsForScreening = array_merge($aColumnsForScreening, $sParentVal);
                            // Need to add code here to check if column name is Individual or Screening to make it more robust.
                        }
                    }
                    // Create the custom link data for the pipeline files for trio.
                    $aColumnsForScreening['Screening/Pipeline_files'] = '{gap:' . $sVal['Pipeline_Run_ID'] . '_' . $sVal['Sample_ID'] . '.gap.csv} {prov:' . $sVal['Pipeline_Run_ID'] . '_' . $sVal['Sample_ID'] . '.provenance.pdf} {summary:' . $sVal['Pipeline_Run_ID'] . '_' . $sVal['Sample_ID'] . '.trio.summary.htm}';
                }

                // Create the meta data file.
                //
                $sFileNamePrefix = $sVal['Pipeline_Run_ID'] . '_' . $sVal['Sample_ID'] . '.' . $sType; // Lets use the full file name here so as we don't get duplicates when importing the same sample again in the future.
                $sFileNameSuffix = (!$bTrio || $sVal['Cohort'] != 'CN' || $sType != 'individual' ? '' : '.CN' . (!$bIndExists ? '' : 'REPLACEID')); // Handle when to add the CN suffix and what type to add. For trios we need a meta file for individual and trio.

                $sFileNameSMDFTemp = $_INI['paths']['data_files'] . $sFileNamePrefix . '.meta.lovd' . $sFileNameSuffix . '.tmp'; // Set the temp file name. We use this while we write out the records.
                $sFileNameSMDF = $_INI['paths']['data_files'] . $sFileNamePrefix . '.meta.lovd' . $sFileNameSuffix;
                $sFileNameTSV = $_INI['paths']['data_files'] . $sFileNamePrefix . '.directvep.data.lovd' . $sFileNameSuffix;


                // Open the temporary file for writing.
                $fOutput = fopen($sFileNameSMDFTemp, 'w');
                if ($fOutput === false) {
                    print('Error opening the temporary output file: ' . $sFileNameSMDFTemp . ".\n");
                    die(51);
                }

                // Write the output data to a variable.
                $sOutputData =
                    '### LOVD-version 3000-080 ### Full data download ### To import, do not remove or alter this header ###' . "\r\n" .
                    '# charset = UTF-8' . "\r\n\r\n" .
                    '## Diseases ## Do not remove or alter this header ##' . "\r\n\r\n" .
                    '## Individuals ## Do not remove or alter this header ##' . "\r\n" .
                    '"{{' . implode("}}\"\t\"{{", array_keys($aColumnsForIndividual)) . '}}"' . "\r\n" .
                    ($bIndExists || ($bTrio && $sVal['Cohort'] != 'CN') ? '# ' : '') . // If the individual exists or its a trio and not CN then we will comment out the individual record.
                    '"' . implode("\"\t\"", array_values($aColumnsForIndividual)) . '"' . "\r\n\r\n" .
                    '## Individuals_To_Diseases ## Do not remove or alter this header ##' . "\r\n\r\n" .
                    '## Screenings ## Do not remove or alter this header ##' . "\r\n" .
                    '"{{' . implode("}}\"\t\"{{", array_keys($aColumnsForScreening)) . '}}"' . "\r\n" .
                    '"' . implode("\"\t\"", array_values($aColumnsForScreening)) . '"' . "\r\n";

                // Write out the heading information for the meta data file.
                fputs($fOutput, $sOutputData);

                fclose($fOutput);

                // Now rename the tmp to the final file, and close this loop.
                if (!rename($sFileNameSMDFTemp, $sFileNameSMDF)) {
                    print('Error renaming temp file to target: ' . $sFileNameSMDF . ".\n");
                    die(51);
                }

                // Rename the corresponding variant file.
                $sFileNameTSVOld = $_INI['paths']['data_files'] . $aVariantFiles[$sVal['Sample_ID']][$sType];

                if (!rename($sFileNameTSVOld, $sFileNameTSV)) {
                    print('Error renaming variant file: ' . $sFileNameTSVOld . ".\n");
                    die(51);
                }
            }
        }
    }

    // Now rename the SMDF to .ARK.
    $sArchiveMetaFile = $_INI['paths']['data_files'] . $sPipelineRunID . '_' . $sArchiveMetaFile;

    if (!rename($sMetaFile, $sArchiveMetaFile)) {
        print('Error archiving SMDF to: ' . $sArchiveMetaFile . ".\n");
        die(51);
    }


    print('Adapter Process Complete' . "\n" . 'Current time: ' . date('Y-m-d H:i:s') . ".\n\n");
}
?>