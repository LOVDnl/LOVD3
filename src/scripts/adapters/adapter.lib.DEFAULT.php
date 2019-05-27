<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-09-02
 * Modified    : 2018-12-20
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2018 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Juny Kesumadewi <juny.kesumadewi@unimelb.edu.au>
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

// These are the default instance-specific settings.
// Create a new file, change the "DEFAULT" in the name to your uppercased
//  instance name, and add the settings you'd like to override.
// Optionally, copy this file completely and change the settings in your copy.
// Change the settings to your liking in your own instance-specific adapter file.

// Default settings.
$_INSTANCE_CONFIG = array();

// This allows for attachment uploads. If you do not want this feature, disable this code.
// These are some example default file types and settings.
// FIXME: Allow for one file type to be linked to multiple objects.
$_INSTANCE_CONFIG['attachments'] = array(
    'screenshot' => array(
        'linked_to' => 'variant',
        'label' => 'Screenshot'),
    'sa_screenshot' => array(
        'linked_to' => 'summary_annotation', // This file is stored using the Summary Annotation Record DBID.
        'label' => 'Screenshot (Summary Annotation)'),
    'document' => array(
        'linked_to' => 'variant',
        'label' => 'Document'),
    'sa_document' => array(
        'linked_to' => 'summary_annotation', // This file is stored using the Summary Annotation Record DBID.
        'label' => 'Document (Summary Annotation)'),
);

$_INSTANCE_CONFIG['columns'] = array(
    'lab_id' => 'Individual/Lab_ID',
    'family' => array(
        // Insert columns here that define a certain family role.
        // For instance, if the Individual/MotherID column contains the Lab ID
        //  of the mother of the current Individual, define this as:
        // 'mother' => 'Individual/MotherID',
        // Note that the value in the column needs to match the value of
        //  the other Individual's column defined in the 'lab_id' setting.
    ),
);

$_INSTANCE_CONFIG['cross_screenings'] = array(
    'format_screening_name' => function($zScreening)
    {
        // This function formats the label for screenings to use in the cross screening filter.
        // It can use any Individual or Screening column to format the label.
        // Default is: "Individual/Lab_ID (role)".
        global $_INSTANCE_CONFIG;

        $sReturn = $zScreening[$_INSTANCE_CONFIG['columns']['lab_id']];
        if (!empty($zScreening['role'])) {
            $sReturn .= ' (' . $zScreening['role'] . ')';
        }

        return $sReturn;
    }
);

$_INSTANCE_CONFIG['viewlists'] = array(
    // If set to true, ViewLists are not allowed to be downloaded, except specifically
    //  enabled as 'allow_download_from_level' in the ViewLists's settings below.
    'restrict_downloads' => true,

    // The screenings data listing on the individual's detailed view.
    'Screenings_for_I_VE' => array(
        'cols_to_show' => array(
            // Select these columns for the screenings listing on the individual's page.
            // Note, that you also need to define the hidden columns that
            //  are to be active, since LOVD+ might be filtering on them.
            // You can change the order of columns to any order you like.
            'id',
            'individualid', // Hidden, but needed for search.
            'curation_progress_',
            'variants_found_',
            'analysis_status',
            'analysis_by_',
            'analysis_date_',
            'analysis_approved_by_',
            'analysis_approved_date_',
        )
    ),
    // The data analysis results data listing.
    'CustomVL_AnalysisRunResults_for_I_VE' => array(
        // Even when downloading ViewLists is restricted, allow downloading from LEVEL_MANAGER.
        'allow_download_from_level' => LEVEL_MANAGER,
        'cols_to_show' => array(
            // Select these columns for the analysis results table.
            // Note, that you also need to define the hidden columns that
            //  are to be active, since LOVD+ might be filtering on them.
            // By default, these columns are sorted by object type, but you can change the order to any order you like.
            'curation_status_',
            'curation_statusid',
            'variantid',
            'vog_effect',
            'chromosome',
            'allele_',
            'VariantOnGenome/DNA',
            'VariantOnGenome/Sequencing/Depth/Alt/Fraction',
            'VariantOnGenome/Sequencing/Quality',
            'obs_variant',
            'obs_var_ind_ratio',
            'obs_disease',
            'obs_var_dis_ind_ratio',

            'gene_disease_names',
            'VariantOnTranscript/DNA',
            'VariantOnTranscript/Protein',
            'VariantOnTranscript/GVS/Function',
            'gene_OMIM_',

            'runid',

            'gene_panels',
        )
    )
);

$_INSTANCE_CONFIG['conversion'] = array(
    'suffixes' => array(
        'meta' => 'meta.lovd',
        'vep' => 'vep.data.lovd',
        'total.tmp' => 'total.data.tmp',
        'total' => 'total.data.lovd',
        'error' => 'error',
    ),
    'annotation_error_drops_line' => false, // Should we discard the variant's mapping on this transcript on annotation errors?
    'annotation_error_exits' => false, // Whether to halt on the first annotation error.
    'annotation_error_max_allowed' => 50, // Maximum number of errors with VOTs before the script dies anyway.
    'create_genes_and_transcripts' => true, // Allow automatic creation of genes, allow automatic creation of transcripts.
    'create_meta_file_if_missing' => true, // Should LOVD+ just create a meta file with default settings if it's missing?
    'check_indel_description' => true, // Should we check all indels using Mutalyzer? Vep usually does a bad job at them.
    'enforce_hgnc_gene' => true, // Enforce gene to exist in the HGNC (requires use_hgnc = true).
    'use_hgnc' => true, // Use the HGNC to collect gene information, and detect gene aliases (requires create_genes_and_transcripts = true).
    'verbosity_cron' => 5, // How verbose should we be when running through cron? (default: 5; currently supported: 0,3,5,7,9)
    'verbosity_other' => 7, // How verbose should we be otherwise? (default: 7; currently supported: 0,3,5,7,9)
);

// This is the default configuration of the observation count feature.
// To disable this feature completely, set 'observation_counts' to an empty
//  array in your instance-specific settings.
// FIXME: Make the columns configurable like the categories; just let the
//  instances select which columns they want; the values are defined elsewhere.
//  Now, every instance has to redefine the labels, but never does actually
//  change them.
$_INSTANCE_CONFIG['observation_counts'] = array(
    // If you want to display the gene panel observation counts using the default
    //  configuration, you can also simply write: 'genepanel' => array(),
    'genepanel' => array(
        // These are the columns to choose from. If you'd like to display all
        //  default columns, you can also simply write:
        //  'columns' => array(),
        'columns' => array(
            'value' => 'Gene Panel',
            'total_individuals' => 'Total # Individuals',
            'num_affected' => '# of Affected Individuals',
            'num_not_affected' => '# of Unaffected Individuals',
            'percentage' => 'Percentage (%)'
        ),
        // These are the categories to choose from. If you'd like to use all
        //  default categories, you can also also simply write:
        //  'categories' => array(),
        'categories' => array(
            'all',
            'gender',
            'ethnic',
        ),
        // Round calculated percentages to what amount of decimals? (0-3)
        'show_decimals' => 1,
    ),

    // If you want to display the general observation counts using the default
    //  configuration, you can also simply write: 'general' => array(),
    'general' => array(
        // These are the columns to choose from. If you'd like to display all
        //  default columns, you can also simply write:
        //  'columns' => array(),
        'columns' => array(
            'label' => 'Category',
            'value' => 'Value',
            'threshold' => 'Percentage'
        ),
        // These are the categories to choose from. If you'd like to use all
        //  default categories, you can also also simply write:
        //  'categories' => array(),
        'categories' => array(
            'all',
            'Individual/Gender',
            'Individual/Origin/Ethnic',
            'Screening/Sample/Type',
            'Screening/Library_preparation',
            'Screening/Sequencing_software',
            'Screening/Analysis_type',
            'Screening/Library_preparation&Screening/Sequencing_software',
            'Screening/Library_preparation&Screening/Sequencing_software&Screening/Analysis_type',
        ),
        // This is the minimal population size that is required for the
        //  general observation counts to be calculated.
        'min_population_size' => 100,
        // Round calculated percentages to what amount of decimals? (0-3)
        'show_decimals' => 1,
    ),
);





// Define settings, if not defined before.
@define('VERBOSITY_NONE', 0); // No output whatsoever.
@define('VERBOSITY_LOW', 3); // Low output, only the really important messages.
@define('VERBOSITY_MEDIUM', 5); // Medium output. No output if there is nothing to do. Useful for when using cron.
@define('VERBOSITY_HIGH', 7); // High output. The default.
@define('VERBOSITY_FULL', 9); // Full output, including debug statements.

// Exit codes.
// See http://tldp.org/LDP/abs/html/exitcodes.html for recommendations, in particular:
// "[I propose] restricting user-defined exit codes to the range 64 - 113 (...), to conform with the C/C++ standard."
define('EXIT_OK', 0);
define('EXIT_WARNINGS_OCCURRED', 64);

function lovd_printIfVerbose ($nVerbosity, $sMessage)
{
    // This function only prints the given message when the current verbosity is set to a level high enough.

    // If no verbosity is currently defined, just print everything.
    if (!defined('VERBOSITY')) {
        define('VERBOSITY', 9);
    }

    if (VERBOSITY >= $nVerbosity) {
        print($sMessage);
    }
    return true;
}





// FIXME: This class should not be mixed with the above settings, I reckon? Split it?
// FIXME: Some methods are never overloaded and aren't meant to be, better put those elsewhere to prevent confusion.
class LOVD_DefaultDataConverter {
    // Class with methods and variables for convert_and_merge_data_files.php.

    var $sAdapterPath;
    var $aScriptVars = array();
    var $aMetadata; // Contains the meta data file, parsed.
    const NO_TRANSCRIPT = '-----'; // Transcripts with this value will be ignored.

    public function __construct ($sAdapterPath)
    {
        $this->sAdapterPath = $sAdapterPath;
    }





    function cleanGenoType ($sGenoType)
    {
        // Returns a "cleaned" genotype (GT) field, given the VCF's GT field.
        // VCFs can contain many different GT values that should be cleaned/simplified into fewer options.

        static $aGenotypes = array(
            './.' => '0/0', // No coverage taken as homozygous REF.
            './0' => '0/0', // REF + no coverage taken as homozygous REF.
            '0/.' => '0/0', // REF + no coverage taken as homozygous REF.

            './1' => '0/1', // ALT + no GT due to multi allelic SNP taken as heterozygous ALT.
            '1/.' => '0/1', // ALT + no GT due to multi allelic SNP taken as heterozygous ALT.

            '1/0' => '0/1', // Just making sure we only have one way to describe HET calls.
        );

        if (isset($aGenotypes[$sGenoType])) {
            return $aGenotypes[$sGenoType];
        } else {
            return $sGenoType;
        }
    }





    function cleanHeaders ($aHeaders)
    {
        // Return the headers, cleaned up if needed.
        // You can add code here that will clean the headers, directly after reading.

        // Analyze headers, find samples, identify parents.
        $aSamples = array();
        foreach ($aHeaders as $sHeader) {
            // Find sample.GT headers, and store sample names.
            if (substr($sHeader, -3) == '.GT') {
                $aSamples[substr($sHeader, 0, -3)] = '';
            }
        }
        $nSamples = count($aSamples);
        if ($nSamples > 1) {
            // More than one individual. Currently, LOVD+ supports single individual and trio data.
            if ($nSamples != 3) {
                // Unsupported number of samples.
                die('Fatal: Unsupported number of samples. LOVD+ currently supports single individual and trio (3 sample) data. Found ' . $nSamples . ' samples.' . "\n");
            }

            // FIXME: Until we have some way of configuring this, we'll assume the file provided the samples in the order Child, Father, Mother (simple alphabetical order).
            list(, $sFather, $sMother) = array_keys($aSamples);
            $aSamples[$sFather] = 'Father_';
            $aSamples[$sMother] = 'Mother_';
        }

        // Rename the headers.
        foreach ($aHeaders as $nKey => $sHeader) {
            if (preg_match('/^(' . implode('|', array_map('preg_quote', array_keys($aSamples))) . ')\./', $sHeader, $aRegs)) {
                // Sample header.
                $sSample = $aRegs[1];
                $aHeaders[$nKey] = str_replace($sSample . '.', $aSamples[$sSample], $sHeader);
            }
        }

        return $aHeaders;
    }





    function convertGenoTypeToAllele ($aVariant)
    {
        // Converts the GenoType data (already stored in the 'allele' field) to an LOVD-style allele value.
        // To stop variants from being imported, set $aVariant['lovd_ignore_variant'] to something non-false.
        // Possible values:
        // 'silent' - for silently ignoring the variant.
        // 'log' - for ignoring the variant and logging the line number.
        // 'separate' - for storing the variant in a separate screening (not implemented yet).
        // When set to something else, 'log' is assumed.
        // Note that when verbosity is set to low (3) or none (0), then no logging will occur.

        // First verify the GT (allele) column. VCFs might have many interesting values (mostly for multisample VCFs).
        // Clean the value a bit (will result in "0/." calls to be converted to "0/0", for instance).
        if (!isset($aVariant['allele'])) {
            $aVariant['allele'] = '';
        }
        $aVariant['allele'] = $this->cleanGenoType($aVariant['allele']);

        // Then, convert the GT values to proper LOVD-style allele values.
        switch ($aVariant['allele']) {
            case '0/0':
                // Homozygous REF; not a variant. Skip this line silently.
                $aVariant['lovd_ignore_variant'] = 'silent';
                break;
            case '0/1':
                // Heterozygous.
                if (!empty($aVariant['VariantOnGenome/Sequencing/Father/GenoType']) && !empty($aVariant['VariantOnGenome/Sequencing/Mother/GenoType'])) {
                    if (strpos($aVariant['VariantOnGenome/Sequencing/Father/GenoType'], '1') !== false && strpos($aVariant['VariantOnGenome/Sequencing/Mother/GenoType'], '1') === false) {
                        // From father, inferred.
                        $aVariant['allele'] = 10;
                    } elseif (strpos($aVariant['VariantOnGenome/Sequencing/Mother/GenoType'], '1') !== false && strpos($aVariant['VariantOnGenome/Sequencing/Father/GenoType'], '1') === false) {
                        // From mother, inferred.
                        $aVariant['allele'] = 20;
                    } else {
                        $aVariant['allele'] = 0;
                    }
                } else {
                    $aVariant['allele'] = 0;
                }
                break;
            case '1/1':
                // Homozygous.
                $aVariant['allele'] = 3;
                break;
            default:
                // Unexpected value (empty string?). Ignore the variant, log.
                $aVariant['lovd_ignore_variant'] = 'log';
        }

        return $aVariant;
    }





    function formatEmptyColumn ($aLine, $sVEPColumn)
    {
        // Returns how we want to represent empty data in the $aVariant array.
        // Fields that evaluate true with empty() or set to "." or "unknown" are sent here.
        // The default is to set them to an empty string.
        // You can overload this function to include different functionality,
        //  such as returning 0 in some cases.

        /*
        if (isset($aLine[$sVEPColumn]) && ($aLine[$sVEPColumn] === 0 || $aLine[$sVEPColumn] === '0')) {
            return 0;
        } else {
            return '';
        }
        */
        return '';
    }





    function getInputFilePrefixPattern ()
    {
        // Returns the regex pattern of the prefix of variant input file names.
        // The prefix is often the sample ID or individual ID, and can be formatted to your liking.
        // Data files must be named "prefix.suffix", using the suffixes as defined in the conversion's settings array.

        // If using sub patterns, make sure they are not counted, like so:
        //  (?:subpattern)
        return '.+';
    }





    function getRequiredHeaderColumns ()
    {
        // Returns an array of required variant input file column headers.
        // The order of these columns does NOT matter.
        // T.S: Block GT

        return array(
            '#CHROM',
            'POS',
            'REF',
            'ALT',
            'QUAL',
            'Consequence',
            'SYMBOL',
            'Feature',
            'GT',
        );
    }





    function ignoreTranscript ($sTranscriptID)
    {
        // Returns true for transcripts whose annotation should be ignored.
        // You can overload this function to define which transcripts to ignore;
        //  you can use lists, prefixes or other rules.

        if ($sTranscriptID === static::NO_TRANSCRIPT) {
            return true;
        }

        // Here, set any patterns of transcripts that you'd like ignored, like '^NR_'.
        $aTranscriptPatternsToIgnore = array(
            '^[0-9]$', // Numeric transcripts for transfer RNAs, source unknown.
            '_dupl',   // VEP produces duplicated transcripts.
            '^ENS',    // Ensembl transcripts, that we don't support.
            '^NC_',    // More strange transfer RNA transcripts in the format NC_000001.10:TRNAE-UUC:u_t_1.
            // '^NR_',    // Non-coding transcripts, otherwise perfectly valid.
            // '^XM_',    // Computer-predicted coding transcripts, yet to be validated to actually exist.
            // '^XR_',    // Computer-predicted non-coding transcripts, yet to be validates to actually exist.
        );

        foreach ($aTranscriptPatternsToIgnore as $sPattern) {
            if (preg_match('/' . $sPattern . '/', $sTranscriptID)) {
                return true;
            }
        }

        return false;
    }





    function postValueAssignmentUpdate ($sKey, &$aVariant, &$aData)
    {
        // This function is run after every line has been read;
        // $aData[$sKey] contains the parsed and stored data of the genomic variant.
        // $aVariant contains all the data of the line just read,
        //  including the transcript-specific data.
        // You can overload this function if you need to generate aggregated
        //  data over the different transcripts mapped to one variant.

        return $aData;
    }





    function prepareGeneAliases ()
    {
        // Return an array of gene aliases, with the gene symbol as given by VEP
        //  as the key, and the symbol as known by LOVD/HGNC as the value.
        // Example:
        // return array(
        //     'C4orf40' => 'PRR27',
        // );

        return array(
        );
    }





    function prepareGenesToIgnore ()
    {
        // Return an array of gene symbols of genes you wish to ignore.
        // These could be genes that you know can't be imported/created in LOVD,
        //  or genes whose annotation you wish to ignore for a different reason.
        // Example:
        // return array(
        //     'FLJ12825',
        //     'FLJ27354',
        //     'FLJ37453',
        // );

        return array(
        );
    }





    // FIXME: This function does not have a clearly matching name.
    function prepareMappings ()
    {
        // Returns an array that map VEP columns to LOVD columns.

        $aColumnMappings = array(
            '#CHROM' => 'chromosome',
            'POS' => 'position', // lovd_getVariantDescription() needs this.
            'REF' => 'ref',      // lovd_getVariantDescription() needs this.
            'ALT' => 'alt',      // lovd_getVariantDescription() needs this.
            'QUAL' => 'VariantOnGenome/Sequencing/Quality',
            'FILTER' => 'VariantOnGenome/Sequencing/Filter',
            'Consequence' => 'VariantOnTranscript/GVS/Function', // Will be translated.
            'SYMBOL' => 'symbol',
            'Feature' => 'transcriptid',
            'HGVSc' => 'VariantOnTranscript/DNA',
            'HGVSp' => 'VariantOnTranscript/Protein',
            'Existing_variation' => 'existing_variation', // This is where we'll find the dbSNP data.
            'dbSNP' => 'VariantOnGenome/dbSNP', // VEP doesn't have this. We'll fill it in, in case we find it.
            'HGNC_ID' => 'id_hgnc',
            'SIFT' => 'VariantOnTranscript/Prediction/SIFT',
            'PolyPhen' => 'VariantOnTranscript/PolyPhen',
            'AF' => 'VariantOnGenome/Frequency/1000G',
            'gnomAD_AF' => 'VariantOnGenome/Frequency/GnomAD', // FIXME: Not defined yet.
            'PUBMED' => 'VariantOnGenome/Reference', // FIXME: Translation of values needed.
            'DP' => 'VariantOnGenome/Sequencing/Depth/Total',
            'GQ' => 'VariantOnGenome/Sequencing/GenoType/Quality',
            'GT' => 'allele',

            'DPREF' => 'VariantOnGenome/Sequencing/Depth/Ref',
            'DPALT' => 'VariantOnGenome/Sequencing/Depth/Alt',
            'ALTPERC' => 'VariantOnGenome/Sequencing/Depth/Alt/Fraction', // VEP doesn't have this, we will calculate.

            'Father_DP' => 'VariantOnGenome/Sequencing/Father/Depth/Total',
            'Father_GQ' => 'VariantOnGenome/Sequencing/Father/GenoType/Quality',
            'Father_GT' => 'VariantOnGenome/Sequencing/Father/GenoType',
            // These two don't exist but can be used to fill in VariantOnGenome/Sequencing/Father/Depth/Alt/Fraction.
            'Father_DPREF' => 'VariantOnGenome/Sequencing/Father/Depth/Ref',
            'Father_DPALT' => 'VariantOnGenome/Sequencing/Father/Depth/Alt',
            'Father_ALTPERC' => 'VariantOnGenome/Sequencing/Father/Depth/Alt/Fraction',

            'Mother_DP' => 'VariantOnGenome/Sequencing/Mother/Depth/Total',
            'Mother_GQ' => 'VariantOnGenome/Sequencing/Mother/GenoType/Quality',
            'Mother_GT' => 'VariantOnGenome/Sequencing/Mother/GenoType',
            // These two don't exist but can be used to fill in VariantOnGenome/Sequencing/Mother/Depth/Alt/Fraction.
            'Mother_DPREF' => 'VariantOnGenome/Sequencing/Mother/Depth/Ref',
            'Mother_DPALT' => 'VariantOnGenome/Sequencing/Mother/Depth/Alt',
            'Mother_ALTPERC' => 'VariantOnGenome/Sequencing/Mother/Depth/Alt/Fraction',
        );

        return $aColumnMappings;
    }





    // FIXME: This function does not have a clearly matching name.
    function prepareVariantData (&$aLine)
    {
        // Reformat a line of raw variant data into the format that works for this instance.
        // To stop certain variants being imported add some logic to check for these variants
        //  and then set $aLine['lovd_ignore_variant'] to something non-false.
        // Possible values:
        // 'silent' - for silently ignoring the variant.
        // 'log' - for ignoring the variant and logging the line number.
        // 'separate' - for storing the variant in a separate screening (not implemented yet).
        // When set to something else, 'log' is assumed.
        // Note that when verbosity is set to low (3) or none (0), then no logging will occur.

        return $aLine;
    }





    function readMetadata ($aMetaDataLines)
    {
        // Read array of lines from .meta.lovd file of each .directvep.lovd file.
        // Return an array of metadata keyed by column names.

        $aKeyedMetadata = array(); // The array we're building up.
        $aColNamesByPos = array(); // The list of columns in the section, temp variable.
        $bHeaderPrevRow = false;   // Boolean indicating whether we just saw the header row or not.
        $sSection = '';            // In which section are we?
        foreach ($aMetaDataLines as $sLine) {
            $sLine = trim($sLine);
            if (empty($sLine)) {
                continue;
            }

            if ($bHeaderPrevRow) {
                // Assuming we always only have 1 row of data after each header.

                // Some lines are commented out so that they can be skipped during import.
                // But, this metadata is still valid and we want this data.
                // FIXME: Does somebody really need this functionality? It's not really in line with LOVD normally handles reading files.
                $sLine = trim($sLine, "# ");
                $aDataRow = explode("\t", $sLine);
                $aDataRow = array_map(function($sData) {
                    return trim($sData, '"');
                }, $aDataRow);

                foreach ($aColNamesByPos as $nPos => $sColName) {
                    // Read data.
                    $aKeyedMetadata[$sSection][$sColName] = $aDataRow[$nPos];
                }

                $bHeaderPrevRow = false;
            }

            if (preg_match('/^##\s*([A-Za-z_]+)\s*##\s*Do not remove/', ltrim($sLine, '"'), $aRegs)) {
                // New section. Store variables per section, so they don't get overwritten.
                $sSection = $aRegs[1];
                $aKeyedMetadata[$sSection] = array();
                continue;
            } elseif (substr($sLine, 0) == '#') {
                continue;
            } elseif (substr($sLine, 0, 3) == '"{{') {
                // Read header.
                $aColNamesByPos = array();
                $aCols = explode("\t", $sLine);
                foreach ($aCols as $sColName) {
                    $sColName = trim($sColName, '"{}');
                    $aColNamesByPos[] = $sColName;
                }
                $bHeaderPrevRow = true;
            }
        }

        $this->aMetadata = $aKeyedMetadata;
        return $this->aMetadata;
    }





    // FIXME: This function is not overwritten anywhere, and should perhaps not be defined here. Maybe remove and move the functionality?
    // FIXME: What is this for?
    function setScriptVars ($aVars = array())
    {
        // Keep track of the values of some variables defined in the script that calls this adapter object.

        // Newly set vars overwrites existing vars.
        $this->aScriptVars = $aVars + $this->aScriptVars;
    }





    function translateVEPConsequencesToGVS ($sVEPConsequences)
    {
        // This function translates the VEP consequence values to the corresponding GVS value(s).
        // Because of historical reasons, LOVD+ uses the GVS values internally, for filtering and variant coloring.
        // VEP uses a long list of features, that need to be translated into the GVS values.
        // FIXME: VEPs consequences are based on SO and therefore, probably more consistent, constant, and better.
        // FIXME:    However, then they'll need to be cleaned still, as VEP provides multiple effects per variant.

        // VEP values:
        // https://www.ensembl.org/info/genome/variation/prediction/predicted_data.html        (based on SO, so better?)
        // ===========
        // MODIFIERS:
        // intergenic_variant
        // TF_binding_site_variant    - not encountered yet
        // TFBS_ablation              - not encountered yet
        // downstream_gene_variant
        // upstream_gene_variant
        // non_coding_transcript_variant
        // NMD_transcript_variant     - we remove this, it just complicates things.
        // intron_variant
        // non_coding_transcript_exon_variant
        // 3_prime_UTR_variant
        // 5_prime_UTR_variant
        // coding_sequence_variant

        // LOW IMPACT:
        // synonymous_variant
        // stop_retained_variant
        // start_retained_variant
        // incomplete_terminal_codon_variant
        // splice_region_variant

        // MODERATE IMPACT:
        // regulatory_region_ablation - not encountered yet
        // protein_altering_variant
        // missense_variant
        // inframe_deletion
        // inframe_insertion

        // HIGH IMPACT:
        // transcript_amplification   - not encountered yet
        // start_lost
        // stop_lost
        // frameshift_variant
        // stop_gained
        // splice_donor_variant
        // splice_acceptor_variant
        // transcript_ablation        - not encountered yet

        // GVS values:
        // http://snp.gs.washington.edu/SeattleSeqAnnotation151/HelpInputFiles.jsp     (has slightly different list now)
        // ===========
        // intergenic
        // near-gene-5 (nowadays: upstream-gene)
        // utr-5
        // start-lost (CREATED THIS OURSELVES)
        // coding (nowadays: coding-unknown?)
        // coding-near-splice (nowadays: coding-unknown-near-splice?)
        // coding-synonymous (nowadays: synonymous)
        // coding-synonymous-near-splice (nowadays: synonymous-near-splice)
        // codingComplex (nowadays no longer exists?)
        // codingComplex-near-splice (nowadays no longer exists?)
        // frameshift (nowadays no longer exists?)
        // frameshift-near-splice (nowadays no longer exists?)
        // missense
        // missense-near-splice
        // splice-5 (nowadays: splice-donor)
        // splice (nowadays: intron-near-splice)
        // intron
        // splice-3 (nowadays: splice-acceptor)
        // stop-gained
        // stop-gained-near-splice
        // stop-lost
        // stop-lost-near-splice
        // utr-3
        // near-gene-3 (nowadays: downstream-gene)
        // non-coding-exon
        // non-coding-exon-near-splice
        // non-coding-intron-near-splice (CREATE THIS OURSELVES)

        // This function loops quite a lot, and it would be more efficient if we'd store the results of the output.
        // Caching it will prevent lots of lookups, especially in big files.
        static $aCache = array();
        if (isset($aCache[$sVEPConsequences])) {
            return $aCache[$sVEPConsequences];
        }
        $sConsequences = $sVEPConsequences; // Because we'll edit it.



        // To make it easier on ourselves, we will trust that the VEP consequences are *sorted*.
        // The output seems to always have a certain order. We rely on this order to look up the specific combination
        //  of values. This makes this code a lot easier, as we don't need to pull the terms apart and make lots of
        //  loops to find the right terms and combinations of terms.
        // However, we can not create an extensive list of possible combinations, still sometimes we just want certain
        //  values to take preference.
        static $aValuesToClean = array(
            '&NMD_transcript_variant' => '', // Variant in a transcript that is the target of NMD. Uh, OK.
            'start_lost&start_retained_variant' => 'start_retained_variant', // Start not actually lost.
            'stop_lost&stop_retained_variant' => 'stop_retained_variant', // Stop not actually lost.
            '&coding_sequence_variant&intron_variant' => '', // Will also report to be on a splice site.
            'splice_donor_variant&intron_variant' => 'splice_donor_variant', // Will just affect the splicing.
            'splice_acceptor_variant&intron_variant' => 'splice_acceptor_variant', // Will just affect the splicing.
            'incomplete_terminal_codon_variant&' => '', // This one is unclear. Used nowhere near the terminal codon.
            'coding_sequence_variant&intron_variant' => 'intron_variant', // This exact combo used inside introns only.
        );

        foreach ($aValuesToClean as $sKey => $sVal) {
            // Just keep replacing, it's simplest.
            $sConsequences = str_replace($sKey, $sVal, $sConsequences);
        }



        // Array of values that gain preference over any other values included.
        static $aMappings = array(
            'start_lost' => 'start-lost',
            'stop_gained' => 'stop-gained',
            'stop_lost' => 'stop-lost',
            'coding_sequence_variant&3_prime_UTR_variant' => 'codingComplex', // vep2lovd defined this, only found in ENSG.
            'coding_sequence_variant&5_prime_UTR_variant' => 'codingComplex', // vep2lovd defined this, only found in ENSG.
            '5_prime_UTR_variant' => 'utr-5',
            '3_prime_UTR_variant' => 'utr-3',
            'upstream_gene_variant' => 'utr-5',
            'downstream_gene_variant' => 'utr-3',
            'intergenic_variant' => 'intergenic',
            'splice_donor_variant&non_coding_transcript_variant' => 'non-coding-intron-near-splice', // Not really consistent with coding transcripts.
            'splice_donor_variant&non_coding_transcript_exon_variant' => 'non-coding-exon-near-splice', // Not really consistent with coding transcripts.
            'splice_acceptor_variant&non_coding_transcript_variant' => 'non-coding-intron-near-splice', // Not really consistent with coding transcripts.
            'splice_acceptor_variant&non_coding_transcript_exon_variant' => 'non-coding-exon-near-splice', // Not really consistent with coding transcripts.
            'splice_region_variant&intron_variant&non_coding_transcript_variant' => 'non-coding-intron-near-splice',
            'splice_region_variant&non_coding_transcript_variant' => 'non-coding-intron-near-splice', // Not necessarily intronic.
            'splice_region_variant&non_coding_transcript_exon_variant' => 'non-coding-exon-near-splice',
            'non_coding_transcript_exon_variant&intron_variant' => 'non-coding-exon-near-splice', // Strange that the splice variant isn't mentioned.
            'intron_variant&non_coding_transcript_variant' => 'intron', // Or, if we'll create it, non-coding-intron.
            'non_coding_transcript_exon_variant' => 'non-coding-exon',
            'splice_donor_variant' => 'splice-5',
            'splice_acceptor_variant' => 'splice-3',
            'splice_region_variant&start_retained_variant' => 'coding-synonymous-near-splice',
            'splice_region_variant&stop_retained_variant' => 'coding-synonymous-near-splice',
            'splice_region_variant&synonymous_variant' => 'coding-synonymous-near-splice',
            'frameshift_variant&splice_region_variant' => 'frameshift-near-splice',
            'missense_variant&splice_region_variant' => 'missense-near-splice',
            'splice_region_variant&intron_variant' => 'splice',
            'splice_region_variant' => 'coding-near-splice',
            'frameshift_variant' => 'frameshift',
            '_retained_variant' => 'coding-synonymous',
            'protein_altering_variant' => 'coding',
            'inframe_deletion' => 'coding',
            'inframe_insertion' => 'coding',
            'missense_variant' => 'missense',
            'coding_sequence_variant' => 'coding',
            'synonymous_variant' => 'coding-synonymous',
            'intron_variant' => 'intron',
        );

        foreach ($aMappings as $sKey => $sVal) {
            if (strpos($sConsequences, $sKey) !== false) {
                // Found it, return it, don't continue looping.
                $aCache[$sVEPConsequences] = $sVal;
                return $sVal;
            }
        }

        // If all of this has failed, just return what we got.
        $aCache[$sVEPConsequences] = $sConsequences;
        return $sConsequences; // Might be edited a bit.
    }
}
