<?php
// Leiden specific adapter settings.
$_INSTANCE_CONFIG['attachments'] = false;
$_INSTANCE_CONFIG['conversion']['suffixes']['vep'] = 'directvep.data.lovd';
$_INSTANCE_CONFIG['conversion']['create_meta_file_if_missing'] = false;
$_INSTANCE_CONFIG['conversion']['enforce_hgnc_gene'] = false;
$_INSTANCE_CONFIG['conversion']['verbosity_other'] = 9;

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
            'Screening/Panel_coverage/Fraction',
            'Screening/Father/Panel_coverage/Fraction',
            'Screening/Mother/Panel_coverage/Fraction',
            'curation_progress_',
            'variants_found_',
            'analysis_status',
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
            'VariantOnGenome/Alamut',
            'VariantOnGenome/Conservation_score/PhyloP',
            'VariantOnGenome/HGMD/Association',
            'VariantOnGenome/Sequencing/Depth/Alt/Fraction',
            'VariantOnGenome/Sequencing/Quality',
            'VariantOnGenome/Sequencing/GATKcaller',
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

$_INSTANCE_CONFIG['observation_counts'] = array(
    'genepanel' => array(
        'columns' => array(
            'value' => 'Gene Panel',
            'total_individuals' => 'Total # Individuals',
            'percentage' => 'Percentage (%)'
        ),
        'categories' => array(
            'all',
            'gender',
        ),
        'show_decimals' => 1,
    ),
    'general' => array(
        // if columns is empty, use default columns list
        'columns' => array(
            'label' => 'Category',
            'value' => 'Value',
            'percentage' => 'Percentage (%)'
        ),
        'categories' => array(
            'all',
            'Individual/Gender',
        ),
        'show_decimals' => 1,
        'min_population_size' => 100,
    ),
);





class LOVD_LeidenDataConverter extends LOVD_DefaultDataConverter {
    // Contains the overloaded functions that we want different from the default.

    function cleanHeaders ($aHeaders)
    {
        // Leiden's headers can be appended by the Miracle ID.
        // Clean this off, and verify the identity of this file.
        // Check the child's Miracle ID with that we have in the meta data file, and die if there is a mismatch.
        foreach ($aHeaders as $key => $sHeader) {
            if (preg_match('/(Child|Patient|Father|Mother)_(\d+)$/', $sHeader, $aRegs)) {
                // If Child, check ID.
                if (!empty($this->aScriptVars['nMiracleID']) && in_array($aRegs[1], array('Child', 'Patient')) && $aRegs[2] != $this->aScriptVars['nMiracleID']) {
                    // Here, we won't try and remove the temp file. We need it for diagnostics, and it will save us from running into the same error over and over again.
                    die('Fatal: Miracle ID of ' . $aRegs[1] . ' (' . $aRegs[2] . ') does not match that from the meta file (' . $this->aScriptVars['nMiracleID'] . ')' . "\n");
                }
                // Clean ID from column.
                $aHeaders[$key] = substr($sHeader, 0, -(strlen($aRegs[2]) + 1));
                // Also clean "Child" and "Patient" off.
                $aHeaders[$key] = preg_replace('/_(Child|Patient)$/', '', $aHeaders[$key]);
            }
        }

        return $aHeaders;
    }





    function getInputFilePrefixPattern ()
    {
        // Returns the regex pattern of the prefix of variant input file names.
        // The prefix is often the sample ID or individual ID, and can be formatted to your liking.
        // Data files must be named "prefix.suffix", using the suffixes as defined in the conversion script.

        // If using sub patterns, make sure they are not counted, like so:
        //  (?:subpattern)
        return '(?:Child|Patient)_(?:\d+)';
    }





    function getRequiredHeaderColumns ()
    {
        // Returns an array of required variant input file column headers.
        // The order of these columns does NOT matter.

        return array(
            'chromosome',
            'position',
            'REF',
            'ALT',
            'QUAL',
            'FILTERvcf',
            'GATKCaller',
            'GT',
            'SYMBOL',
            'Feature',
        );
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
            // This list needs to be replaced now and then.
            // Added 2018-02-20, expire 2019-02-20.
            'CPSF3L' => 'INTS11',
            'GLTPD1' => 'CPTP',
            'C1orf233' => 'FNDC10',
            'KIAA1751' => 'CFAP74',
            'C1orf86' => 'FAAP20',
            'APITD1-CORT' => 'CENPS-CORT',
            'APITD1' => 'CENPS',
            'PTCHD2' => 'DISP3',
            'PRAMEF23' => 'PRAMEF5',
            'HNRNPCP5' => 'HNRNPCL2',

            // Added 2018-08-14, expire 2019-08-14.
            'C10orf137' => 'EDRF1',
            'C11orf93' => 'COLCA2',
            'C12orf52' => 'RITA1',
            'C13orf45' => 'LMO7DN',
            'C19orf82' => 'ZNF561-AS1',
            'C1orf63' => 'RSRP1',
            'C20orf201' => 'LKAAEAR1',
            'C2orf62' => 'CATIP',
            'C3orf37' => 'HMCES',
            'C3orf43' => 'SMCO1',
            'C3orf83' => 'MKRN2OS',
            'C6orf229' => 'ARMH2',
            'C6orf70' => 'ERMARD',
            'C7orf41' => 'MTURN',
            'C9orf123' => 'DMAC1',
            'CCDC111' => 'PRIMPOL',
            'CNIH' => 'CNIH1',
            'CXorf48' => 'CT55',
            'CXorf61' => 'CT83',
            'CXXC11' => 'RTP5',
            'GPER' => 'GPER1',
            'KIAA1704' => 'GPALPP1',
            'KIAA1984' => 'CCDC183',
            'LINC01660' => 'FAM230J',
            'LINC01662' => 'FAM230E',
            'MST4' => 'STK26',
            'PHF15' => 'JADE2',
            'PHF16' => 'JADE3',
            'PLAC1L' => 'OOSP2',
            'PNMA6C' => 'PNMA6A',
            'PRAC' => 'PRAC1',
            'RPS17L' => 'RPS17',
            'SCXB' => 'SCX',
            'SELRC1' => 'COA7',
            'SGK196' => 'POMK',
            'SMCR7' => 'MIEF2',
            'SPANXB2' => 'SPANXB1',
            'SPATA31A2' => 'SPATA31A1',
            'UQCC' => 'UQCC1',
            'WTH3DI' => 'RAB6D',
            'ZFP112' => 'ZNF112',
        );
    }





    // FIXME: This function does not have a clearly matching name.
    function prepareMappings ()
    {
        // Returns an array that map VEP columns to LOVD columns.

        $aColumnMappings = array(
            'chromosome' => 'chromosome',
            'position' => 'position', // lovd_getVariantDescription() needs this.
            'QUAL' => 'VariantOnGenome/Sequencing/Quality',
            'FILTERvcf' => 'VariantOnGenome/Sequencing/Filter',
            'GATKCaller' => 'VariantOnGenome/Sequencing/GATKcaller',
            'Feature' => 'transcriptid',
            'GVS' => 'VariantOnTranscript/GVS/Function',
            'CDS_position' => 'VariantOnTranscript/Position',
            'HGVSc' => 'VariantOnTranscript/DNA',
            'HGVSp' => 'VariantOnTranscript/Protein',
            'Grantham' => 'VariantOnTranscript/Prediction/Grantham',
            'INDB_COUNT_UG' => 'VariantOnGenome/InhouseDB/Count/UG',
            'INDB_COUNT_HC' => 'VariantOnGenome/InhouseDB/Count/HC',
            'GLOBAL_VN' => 'VariantOnGenome/InhouseDB/Position/Global/Samples_w_coverage',
            'GLOBAL_VF_HET' => 'VariantOnGenome/InhouseDB/Count/Global/Heterozygotes',
            'GLOBAL_VF_HOM' => 'VariantOnGenome/InhouseDB/Count/Global/Homozygotes',
            'WITHIN_PANEL_VN' => 'VariantOnGenome/InhouseDB/Position/InPanel/Samples_w_coverage',
            'WITHIN_PANEL_VF_HET' => 'VariantOnGenome/InhouseDB/Count/InPanel/Heterozygotes',
            'WITHIN_PANEL_VF_HOM' => 'VariantOnGenome/InhouseDB/Count/InPanel/Homozygotes',
            'OUTSIDE_PANEL_VN' => 'VariantOnGenome/InhouseDB/Position/OutOfPanel/Samples_w_coverage',
            'OUTSIDE_PANEL_VF_HET' => 'VariantOnGenome/InhouseDB/Count/OutOfPanel/Heterozygotes',
            'OUTSIDE_PANEL_VF_HOM' => 'VariantOnGenome/InhouseDB/Count/OutOfPanel/Homozygotes',
            'AF1000G' => 'VariantOnGenome/Frequency/1000G',
            'rsID' => 'VariantOnGenome/dbSNP',
            'AFESP5400' => 'VariantOnGenome/Frequency/EVS', // Will be divided by 100 later.
            'CALC_GONL_AF' => 'VariantOnGenome/Frequency/GoNL',
            'AFGONL' => 'VariantOnGenome/Frequency/GoNL_old',
            'EXAC_AF' => 'VariantOnGenome/Frequency/ExAC',
            'MutationTaster_pred' => 'VariantOnTranscript/Prediction/MutationTaster',
            'MutationTaster_score' => 'VariantOnTranscript/Prediction/MutationTaster/Score',
            'Polyphen2_HDIV_score' => 'VariantOnTranscript/PolyPhen/HDIV',
            'Polyphen2_HVAR_score' => 'VariantOnTranscript/PolyPhen/HVAR',
            'SIFT_score' => 'VariantOnTranscript/Prediction/SIFT',
            'CADD_raw' => 'VariantOnGenome/CADD/Raw',
            'CADD_phred' => 'VariantOnGenome/CADD/Phred',
            'HGMD_association' => 'VariantOnGenome/HGMD/Association',
            'HGMD_reference' => 'VariantOnGenome/HGMD/Reference',
            'phyloP' => 'VariantOnGenome/Conservation_score/PhyloP',
            'scorePhastCons' => 'VariantOnGenome/Conservation_score/Phast',
            'GT' => 'allele',
            'GQ' => 'VariantOnGenome/Sequencing/GenoType/Quality',
            'DP' => 'VariantOnGenome/Sequencing/Depth/Total',
            'DPREF' => 'VariantOnGenome/Sequencing/Depth/Ref',
            'DPALT' => 'VariantOnGenome/Sequencing/Depth/Alt',
            'ALTPERC' => 'VariantOnGenome/Sequencing/Depth/Alt/Fraction', // Will be divided by 100 later.
            'GT_Father' => 'VariantOnGenome/Sequencing/Father/GenoType',
            'GQ_Father' => 'VariantOnGenome/Sequencing/Father/GenoType/Quality',
            'DP_Father' => 'VariantOnGenome/Sequencing/Father/Depth/Total',
            'ALTPERC_Father' => 'VariantOnGenome/Sequencing/Father/Depth/Alt/Fraction', // Will be divided by 100 later.
            'ISPRESENT_Father' => 'VariantOnGenome/Sequencing/Father/VarPresent',
            'GT_Mother' => 'VariantOnGenome/Sequencing/Mother/GenoType',
            'GQ_Mother' => 'VariantOnGenome/Sequencing/Mother/GenoType/Quality',
            'DP_Mother' => 'VariantOnGenome/Sequencing/Mother/Depth/Total',
            'ALTPERC_Mother' => 'VariantOnGenome/Sequencing/Mother/Depth/Alt/Fraction', // Will be divided by 100 later.
            'ISPRESENT_Mother' => 'VariantOnGenome/Sequencing/Mother/VarPresent',

            // Mappings for fields used to process other fields but not imported into the database.
            'SYMBOL' => 'symbol',
            'HGNC_ID' => 'id_hgnc',
            'REF' => 'ref',
            'ALT' => 'alt',
            'Existing_variation' => 'existing_variation'
        );

        return $aColumnMappings;
    }
}
