<?php
/*******************************************************************************
 * CREATE MAPPINGS AND PROCESS VARIANT FILE FOR MGHA
 * Created: 2016-06-01
 * Programmer: Candice McGregor
 *************/

$_INSTANCE_CONFIG['columns'] = array(
    'lab_id' => 'Individual/Sample_ID',
    'family' => array(
        'mother' => 'Screening/Mother/Sample_ID',
        'father' => 'Screening/Father/Sample_ID'
    )
);

$_INSTANCE_CONFIG['viewlists']['restrict_downloads'] = false;
$_INSTANCE_CONFIG['viewlists']['Screenings_for_I_VE']['cols_to_show'] = array(
    // Invisible.
    'individualid',

    // Visible.
    'id',
    'Screening/Father/Sample_ID',
    'Screening/Mother/Sample_ID',
    'Screening/Mean_coverage',
    'Screening/Library_preparation',
    'Screening/Tag',
    'Screening/Batch',
    'Screening/Pipeline/Run_ID',
    'variants_found_',
    'analysis_status'
);
$_INSTANCE_CONFIG['viewlists']['CustomVL_AnalysisRunResults_for_I_VE'] = array(
    'default_sort' => 'VariantOnGenome/Variant_priority',
    'cols_to_show' => array(
        // Invisible.
        'runid',
        'curation_statusid',
        'variantid',

        // Visible.
        'curation_status_',
        'vog_effect',
        'VariantOnGenome/Variant_priority',
        'chromosome',
        'VariantOnGenome/DNA',
        'VariantOnTranscript/DNA',
        'VariantOnTranscript/Protein',
        'VariantOnGenome/Sequencing/Depth/Total',
        'VariantOnGenome/Sequencing/Quality',
        'zygosity_', // 'VariantOnGenome/Sequencing/Allele/Frequency'
        'var_frac_', // 'VariantOnGenome/Sequencing/Depth/Alt/Fraction'
        'gene_OMIM_',
        'gene_disease_names',
        'VariantOnTranscript/Clinical_Significance',
        'allele_',
        'VariantOnTranscript/Consequence_Impact',
        'VariantOnTranscript/Consequence_Type',
        'VariantOnTranscript/Prediction/CADD_Raw',
        'VariantOnGenome/ExAC/Frequency/Adjusted',
        'VariantOnGenome/1000Gp3/Frequency',
        'obs_genepanel',
        'obs_var_gp_ind_ratio',
        'gene_panels',
        'VariantOnGenome/Remarks'
    )
);
$_INSTANCE_CONFIG['viewlists']['CustomVL_ObsCounts']['cols_to_show'] = array(
    // Invisible.
    'variantid',
    'VariantOnGenome/DBID',

    // Visible.
    'vog_effect',
    'allele_',
    'Individual/Sample_ID',
    'Individual/Clinical_indication',
    'Screening/Library_preparation',
    'Screening/Sequencing_chemistry',
    'Screening/Pipeline/Run_ID',

    'VariantOnGenome/Curation/Classification',
    'VariantOnGenome/Sequencing/IGV',
    'VariantOnGenome/Reference',
    'VariantOnTranscript/DNA',
    'VariantOnTranscript/Protein',
    'symbol',
    'gene_OMIM_'
);

$_INSTANCE_CONFIG['viewlists']['CustomVL_DBID']['cols_to_show'] = array(
    // Invisible.
    'variantid',
    'VariantOnGenome/DBID',

    'id_',
    'vog_effect',
    'allele_',
    'Individual/Sample_ID',
    'Individual/Clinical_indication',
    'Screening/Library_preparation',
    'Screening/Sequencing_chemistry',
    'Screening/Pipeline/Run_ID',
    'VariantOnGenome/DNA',
    'VariantOnGenome/Curation/Classification',
    'VariantOnGenome/Sequencing/IGV',
    'VariantOnGenome/Reference',
    'VariantOnTranscript/DNA',
    'VariantOnTranscript/Protein',
    'gene_OMIM_',
    'gene_disease_names'
);


$_INSTANCE_CONFIG['attachments'] = array(
        'igv' => array(
            'linked_to' => 'variant',
            'label' => 'IGV screenshot'),
        'ucsc' => array(
            'linked_to' => 'summary_annotation',  // This file is stored using the Summary Annotation Record DBID.
            'label' => 'UCSC screenshot (Summary Annotation)'),
        'confirmation' => array(
            'linked_to' => 'variant',
            'label' => 'Confirmation screenshot'),
        'workfile' => array(
            'linked_to' => 'variant',
            'label' => 'Excel file')
);

$_INSTANCE_CONFIG['conversion'] = array(
    'suffixes' => array(
        'meta' => 'meta.lovd',
        'vep' => 'directvep.data.lovd',
        'total.tmp' => 'total.data.tmp',
        'total' => 'total.data.lovd',
        'error' => 'error',
    ),
    'annotation_error_max_allowed' => 20,
    'annotation_error_exits' => false,
    'annotation_error_drops_line' => false,
    'create_genes_and_transcripts' => false,
    'create_meta_file_if_missing' => false,
    'enforce_hgnc_gene' => false,
    'check_indel_description' => false,
    'use_hgnc' => false,
    'verbosity_cron' => 7, // How verbose should we be when running through cron? (default: 5; currently supported: 0,3,5,7,9)
    'verbosity_other' => 7, // How verbose should we be otherwise? (default: 7; currently supported: 0,3,5,7,9)
);

$_INSTANCE_CONFIG['cross_screenings'] = array(
    'format_screening_name' => function($zScreening) {
        // role: Individual/Sample_ID - Individual/Affected (Screening/Pipeline/Run_ID_Screening/Batch) [Screening/Tag]

        $sText = $zScreening['Individual/Sample_ID'];
        if (!empty($zScreening['role'])) {
            $sText = $zScreening['role'] . ': ' . $sText;
        }
        if (!empty($zScreening['Individual/Affected'])) {
            $sText .= ' - ' . $zScreening['Individual/Affected'];
        }
        if (!empty($zScreening['Screening/Pipeline/Run_ID']) && !empty($zScreening['Screening/Batch'])) {
            $sText .= ' (' . $zScreening['Screening/Pipeline/Run_ID'] . '_' . $zScreening['Screening/Batch'] . ') ';
        }
        if (!empty($zScreening['Screening/Tag'])) {
            $sText .= ' [' . $zScreening['Screening/Tag'] . ']';
        }

        return $sText;
    }
);

$_INSTANCE_CONFIG['observation_counts'] = array(
    // If we want to display genepanel observation counts using default config,
    // then simply add 'genepanel' => array()
    'genepanel' => array(
        // if columns is empty, use default columns list
        'columns' => array(
            'value' => 'Gene Panel',
            'total_individuals' => 'Total # Individuals',
            'num_affected' => '# of Affected Individuals',
            'num_not_affected' => '# of Unaffected Individuals',
            'percentage' => 'Percentage (%)'
        ),

        // if categories is empty, use default categories list
        'categories' => array(),
        'show_decimals' => 0,
    ),

    // If we want to display general categories observation counts using default config,
    // then simply add 'general' => array()
    'general' => array(
        // if columns is empty, use default columns list
        'columns' => array(
            'label' => 'Category',
            'value' => 'Value',
            'threshold' => 'Percentage'
        ),
        // if categories is empty, use default categories list
        'categories' => array(),
        'show_decimals' => 0,
        'min_population_size' => 100
    )
);

class LOVD_MghaDataConverter extends LOVD_DefaultDataConverter {
    // Contains the overloaded functions that we want different from the default.

    function cleanGenoType ($sGenoType)
    {
        // Returns a "cleaned" genotype (GT) field, given the VCF's GT field.
        // VCFs can contain many different GT values that should be cleaned/simplified into fewer options.

        static $aGenotypes = array(
            './.' => '0/1', // No coverage taken as heterozygous variant.
            './0' => '0/1', // REF + no coverage taken as heterozygous variant.
            '0/.' => '0/1', // REF + no coverage taken as heterozygous variant.
            '0/0' => '0/1', // REF taken as heterozygous variant.

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
                // FIXME: This is Leiden's old method of setting the paternal and maternal allele.
                //  Right now, they use the parents GT columns. It's better that MGHA also switches to this method,
                //  but it requires some coding changes, because default LOVD+ expects GT columns to be in the
                //  "0/1" format and not the "C/T" format. MGHA already has some code to convert these.
                // Discuss this with Ivo because he'd like to, by default, have LOVD+ accept nucleotide GT values, too.
                if (isset($aVariant['VariantOnGenome/Sequencing/Father/VarPresent']) && isset($aVariant['VariantOnGenome/Sequencing/Mother/VarPresent'])) {
                    if ($aVariant['VariantOnGenome/Sequencing/Father/VarPresent'] >= 5 && $aVariant['VariantOnGenome/Sequencing/Mother/VarPresent'] <= 3) {
                        // From father, inferred.
                        $aVariant['allele'] = 10;
                    } elseif ($aVariant['VariantOnGenome/Sequencing/Mother/VarPresent'] >= 5 && $aVariant['VariantOnGenome/Sequencing/Father/VarPresent'] <= 3) {
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





    function prepareMappings()
    {

        // Updates the $aColumnMapping array with site specific mappings.

        $aColumnMappings = array(
            // Mappings for fields used to process other fields but not imported into the database.
            'SYMBOL' => 'symbol',
            'REF' => 'ref',
            'vog_ref' => 'VariantOnGenome/Ref',
            'ALT' => 'alt',
            'vog_alt' => 'VariantOnGenome/Alt',
            'Existing_variation' => 'existing_variation',
            'Feature' => 'transcriptid',
            // VariantOnGenome/DNA - constructed by the lovd_getVariantDescription function later on.
            'CHROM' => 'chromosome',
            'POS' => 'position', // lovd_getVariantDescription() needs this.
            'vog_pos' => 'VariantOnGenome/Position',
            'ID' => 'VariantOnGenome/dbSNP',
            'QUAL' => 'VariantOnGenome/Sequencing/Quality',
            'FILTER' => 'VariantOnGenome/Sequencing/Filter',
            'ABHet' => 'VariantOnGenome/Sequencing/Allele/Balance_Het',
            'ABHom' => 'VariantOnGenome/Sequencing/Allele/Balance_Homo',
            'AC' => 'VariantOnGenome/Sequencing/Allele/Count',
            'AF' => 'VariantOnGenome/Sequencing/Allele/Frequency',
            'AN' => 'VariantOnGenome/Sequencing/Allele/Total',
            'BaseQRankSum' => 'VariantOnGenome/Sequencing/Base_Qualities_Score',
            'DB' => 'VariantOnGenome/Sequencing/dbSNP_Membership',
            'DP' => 'VariantOnGenome/Sequencing/Depth/Unfiltered_All',
            'ExcessHet' => 'VariantOnGenome/Sequencing/Excess_Heterozygosity',
            'FS' => 'VariantOnGenome/Sequencing/Fisher_Strand_Bias',
            'GQ_MEAN' => 'VariantOnGenome/Sequencing/Genotype/Quality/Mean',
            'LikelihoodRankSum' => 'VariantOnGenome/Sequencing/Haplotype_Likelihood_Score',
            'MLEAC' => 'VariantOnGenome/Sequencing/Max_Likelihood_Exp_Allele_Count',
            'MLEAF' => 'VariantOnGenome/Sequencing/Max_Likelihood_Exp_Allele_Freq',
            'MQ' => 'VariantOnGenome/Sequencing/Mapping_Quality',
            'MQRankSum' => 'VariantOnGenome/Sequencing/Mapping_Quality_Score',
            'OND' => 'VariantOnGenome/Sequencing/Non_diploid_Ratio',
            'PG' => 'VariantOnGenome/Sequencing/Genotype_Likelihood_Prior',
            'QD' => 'VariantOnGenome/Sequencing/Quality_by_depth',
            'ReadPosRankSum' => 'VariantOnGenome/Sequencing/Read_Position_Bias_Score',
            'SOR' => 'VariantOnGenome/Sequencing/Symmetric_Odds_Ratio',
            'VariantType' => 'VariantOnGenome/Sequencing/Variant_Type',
            'hiConfDeNovo' => 'VariantOnGenome/Sequencing/High_Confidence_DeNovo',
            'loConfDeNovo' => 'VariantOnGenome/Sequencing/Low_Confidence_DeNovo',
            'set' => 'VariantOnGenome/Sequencing/Source_VCF',
            'Allele' => 'VariantOnTranscript/Consequence_Variant_Allele',
            'Consequence' => 'VariantOnTranscript/Consequence_Type',
            'IMPACT' => 'VariantOnTranscript/Consequence_Impact',
            'Gene' => 'VariantOnTranscript/Emsembl_Stable_ID',
            'Feature_type' => 'VariantOnTranscript/Feature_Type',
            'BIOTYPE' => 'VariantOnTranscript/Biotype',
            'EXON' => 'VariantOnTranscript/Exon',
            'INTRON' => 'VariantOnTranscript/Intron',
            'HGVSc' => 'VariantOnTranscript/DNA',
            'HGVSp' => 'VariantOnTranscript/Protein',
            'cDNA_position' => 'VariantOnTranscript/cDNA_Position',
            'CDS_position' => 'VariantOnTranscript/Position',
            'Protein_position' => 'VariantOnTranscript/Protein_Position',
            'Amino_acids' => 'VariantOnTranscript/Amino_Acids',
            'Codons' => 'VariantOnTranscript/Alternative_Codons',
            'STRAND' => 'VariantOnTranscript/DNA_Strand',
            'CANONICAL' => 'VariantOnTranscript/Canonical_Transcript',
            'ENSP' => 'VariantOnTranscript/Embsembl_Protein_Identifier',
            'HGVS_OFFSET' => 'VariantOnTranscript/HGVS_Offset',

            'CLIN_SIG' => 'VariantOnTranscript/Clinical_Significance',
            'SOMATIC' => 'VariantOnTranscript/Somatic_Status',
            'PHENO' => 'VariantOnTranscript/Phenotype',
            'PUBMED' => 'VariantOnTranscript/Pubmed',
            'Condel' => 'VariantOnTranscript/Prediction/Condel_Score',

            'CADD_phred' => 'VariantOnTranscript/Prediction/CADD_Phredlike',
            'CADD_raw' => 'VariantOnTranscript/Prediction/CADD_Raw',
            'CADD_raw_rankscore' => 'VariantOnTranscript/Prediction/CADD_Raw_Ranked',

            'ESP6500_AA_AF' => 'VariantOnGenome/Frequency/ESP6500/American',
            'ESP6500_EA_AF' => 'VariantOnGenome/Frequency/ESP6500/European_American',

            'EA_MAF' => 'VariantOnGenome/Frequency/EVS/VEP/European_American',
            'AA_MAF' => 'VariantOnGenome/Frequency/EVS/VEP/African_American',


            'clinvar_clnsig' => 'VariantOnTranscript/dbNSFP/ClinVar/Clinical_Significance',
            'clinvar_rs' => 'VariantOnTranscript/dbNSFP/ClinVar/rs',
            'clinvar_trait' => 'VariantOnTranscript/dbNSFP/ClinVar/Trait',
            'COSMIC_CNT' => 'VariantOnTranscript/dbNSFP/COSMIC/Number_Of_Samples',
            'COSMIC_ID' => 'VariantOnTranscript/dbNSFP/COSMIC/ID',

            'cpipe_1000Gp3_AF' => 'VariantOnGenome/1000Gp3/Frequency',
            'cpipe_1000Gp3_AN' => 'VariantOnGenome/1000Gp3/Allele/Total',
            'cpipe_1000Gp3_AC' => 'VariantOnGenome/1000Gp3/Allele/Count',
            'cpipe_1000Gp3_AF_AFR' => 'VariantOnGenome/1000Gp3/Frequency/African',
            'cpipe_1000Gp3_AF_AMR' => 'VariantOnGenome/1000Gp3/Frequency/American',
            'cpipe_1000Gp3_AF_EAS' => 'VariantOnGenome/1000Gp3/Frequency/East_Asian',
            'cpipe_1000Gp3_AF_EUR' => 'VariantOnGenome/1000Gp3/Frequency/European',
            'cpipe_1000Gp3_AF_SAS' => 'VariantOnGenome/1000Gp3/Frequency/South_Asian',


            'cpipe_ExAC_AN_Adj' => 'VariantOnGenome/ExAC/Frequency/Allele/Total/Adjusted',
            'cpipe_ExAC_AC_Adj' => 'VariantOnGenome/ExAC/Frequency/Allele/Count/Adjusted',

            // To be calculated
            'cpipe_ExAC_AF_Adj' => 'VariantOnGenome/ExAC/Frequency/Adjusted',
            'cpipe_ExAC_AF_AFR' => 'VariantOnGenome/ExAC/Frequency/African',
            'cpipe_ExAC_AF_AMR' => 'VariantOnGenome/ExAC/Frequency/American',
            'cpipe_ExAC_AF_CONSANGUINEOUS' => 'VariantOnGenome/ExAC/Frequency/Consanguineous',
            'cpipe_ExAC_AF_EAS' => 'VariantOnGenome/ExAC/Frequency/East_Asian',
            'cpipe_ExAC_AF_FEMALE' => 'VariantOnGenome/ExAC/Frequency/Female',
            'cpipe_ExAC_AF_FIN' => 'VariantOnGenome/ExAC/Frequency/Finnish',
            'cpipe_ExAC_AF_MALE' => 'VariantOnGenome/ExAC/Frequency/Male',
            'cpipe_ExAC_AF_NFE' => 'VariantOnGenome/ExAC/Frequency/Non_Finnish',
            'cpipe_ExAC_AF_OTH' => 'VariantOnGenome/ExAC/Frequency/Other',
            'cpipe_ExAC_AF_SAS' => 'VariantOnGenome/ExAC/Frequency/South_Asian',


            'FATHMM_pred' => 'VariantOnTranscript/Prediction/FATHMM',
            'FATHMM_rankscore' => 'VariantOnTranscript/Prediction/FATHMM_Ranked_Score',
            'FATHMM_score' => 'VariantOnTranscript/Prediction/FATHMM_Score',
            'GERP++_NR' => 'VariantOnTranscript/Prediction/GERP_Neutral_Rate',
            'GERP++_RS' => 'VariantOnTranscript/Prediction/GERP_Score',
            'GERP++_RS_rankscore' => 'VariantOnTranscript/Prediction/GERP_Ranked_Score',
            'LRT_Omega' => 'VariantOnTranscript/Prediction/LRT_Omega',
            'LRT_converted_rankscore' => 'VariantOnTranscript/Prediction/LRT_Ranked_Score',
            'LRT_pred' => 'VariantOnTranscript/Prediction/LRT',
            'LRT_score' => 'VariantOnTranscript/Prediction/LRT_Score',
            'MetaLR_pred' => 'VariantOnTranscript/Prediction/MetaLR',
            'MetaLR_rankscore' => 'VariantOnTranscript/Prediction/MetaLR_Ranked_Score',
            'MetaLR_score' => 'VariantOnTranscript/Prediction/MetaLR_Score',
            'MetaSVM_pred' => 'VariantOnTranscript/Prediction/MetaSVM',
            'MetaSVM_rankscore' => 'VariantOnTranscript/Prediction/MetaSVM_Ranked_Score',
            'MetaSVM_score' => 'VariantOnTranscript/Prediction/MetaSVM_Score',
            'MutationAssessor_pred' => 'VariantOnTranscript/Prediction/MutationAssessor',
            'MutationAssessor_rankscore' => 'VariantOnTranscript/Prediction/MutationAssessor_Ranked_Score',
            'MutationAssessor_score' => 'VariantOnTranscript/Prediction/MutationAssessor_Score',
            'MutationTaster_converted_rankscore' => 'VariantOnTranscript/Prediction/MutationTaster_Ranked_Score',
            'MutationTaster_pred' => 'VariantOnTranscript/Prediction/MutationTaster',
            'MutationTaster_score' => 'VariantOnTranscript/Prediction/MutationTaster_Score',
            'PROVEAN_converted_rankscore' => 'VariantOnTranscript/Prediction/PROVEAN_Ranked_Score',
            'PROVEAN_pred' => 'VariantOnTranscript/Prediction/PROVEAN',
            'PROVEAN_score' => 'VariantOnTranscript/Prediction/PROVEAN_Score',
            'Polyphen2_HDIV_pred' => 'VariantOnTranscript/Prediction/Polyphen2_HDIV',
            'Polyphen2_HDIV_rankscore' => 'VariantOnTranscript/Prediction/Polyphen2_HDIV_Ranked_Score',
            'Polyphen2_HDIV_score' => 'VariantOnTranscript/Prediction/Polyphen2_HDIV_Score',
            'Polyphen2_HVAR_pred' => 'VariantOnTranscript/Prediction/Polyphen2_HVAR',
            'Polyphen2_HVAR_rankscore' => 'VariantOnTranscript/Prediction/Polyphen2_HVAR_Ranked_Score',
            'Polyphen2_HVAR_score' => 'VariantOnTranscript/Prediction/Polyphen2_HVAR_Score',
            'Reliability_index' => 'VariantOnTranscript/Prediction/MetaSVM_MetaLR_Reliability_Index',
            'SIFT_pred' => 'VariantOnTranscript/Prediction/SIFT_dbNSFP',
            'SiPhy_29way_logOdds' => 'VariantOnTranscript/Prediction/SiPhy29way_Score',
            'SiPhy_29way_logOdds_rankscore' => 'VariantOnTranscript/Prediction/SiPhy29way_Ranked_Score',
            'SiPhy_29way_pi' => 'VariantOnTranscript/Prediction/SiPhy29way_Distribution',
            'UniSNP_ids' => 'VariantOnTranscript/UniSNP_IDs',
            'VEST3_rankscore' => 'VariantOnTranscript/Prediction/VEST3_Ranked_Score',
            'VEST3_score' => 'VariantOnTranscript/Prediction/VEST3_Score',
            'phastCons100way_vertebrate' => 'VariantOnTranscript/Prediction/phastCons100way_Vert_Score',
            'phastCons100way_vertebrate_rankscore' => 'VariantOnTranscript/Prediction/phastCons100way_Vert_Ranked_Score',
            'phastCons46way_placental' => 'VariantOnTranscript/Prediction/phastCons46way_Plac_Score',
            'phastCons46way_placental_rankscore' => 'VariantOnTranscript/Prediction/phastCons46way_Plac_Ranked_Score',
            'phastCons46way_primate' => 'VariantOnTranscript/Prediction/phastCons46way_Prim_Score',
            'phastCons46way_primate_rankscore' => 'VariantOnTranscript/Prediction/phastCons46way_Prim_Ranked_Score',
            'phyloP100way_vertebrate' => 'VariantOnTranscript/Prediction/phyloP100way_Vert_Score',
            'phyloP100way_vertebrate_rankscore' => 'VariantOnTranscript/Prediction/phyloP100way_Vert_Ranked_Score',
            'phyloP46way_placental' => 'VariantOnTranscript/Prediction/phyloP46way_Plac_Score',
            'phyloP46way_placental_rankscore' => 'VariantOnTranscript/Prediction/phyloP46way_Plac_Ranked_Score',
            'phyloP46way_primate' => 'VariantOnTranscript/Prediction/phyloP46way_Prim_Score',
            'phyloP46way_primate_rankscore' => 'VariantOnTranscript/Prediction/phyloP46way_Prim_Ranked_Score',
            'Grantham' => 'VariantOnTranscript/Prediction/Grantham',
            'CPIPE_BED' => 'VariantOnTranscript/Pipeline_V6_bed_file',

            // Child/Singleton fields.
            'Child_DP' => 'VariantOnGenome/Sequencing/Depth/Total',
            'Child_GQ' => 'VariantOnGenome/Sequencing/Genotype/Quality',
            'Child_GT' => 'allele', // this is in the form of A/A, A/T etc. This is converted to 0/0, 1/0 later on
            'Child_JL' => 'VariantOnGenome/Sequencing/Phredscaled_Joint_Likelihood',
            'Child_JP' => 'VariantOnGenome/Sequencing/Phredscaled_Joint_Probability',
            'Child_PID' => 'VariantOnGenome/Sequencing/Physical_Phasing_ID',
            'Child_PL' => 'VariantOnGenome/Sequencing/Phredscaled_Likelihoods',
            'Child_PP' => 'VariantOnGenome/Sequencing/Phredscaled_Probabilities',

            // Father fields.
            'Father_DP' => 'VariantOnGenome/Sequencing/Father/Depth/Total',// We actually do not receive a value for depth in this column, we need to calculate this using AD & PL.
            'Father_GQ' => 'VariantOnGenome/Sequencing/Father/Genotype/Quality',
            'Father_GT' => 'VariantOnGenome/Sequencing/Father/GenoType',
            'Father_JL' => 'VariantOnGenome/Sequencing/Father/Phredscaled_Joint_Likelihood',
            'Father_JP' => 'VariantOnGenome/Sequencing/Father/Phredscaled_Joint_Probability',
            'Father_PID' => 'VariantOnGenome/Sequencing/Father/Physical_Phasing_ID',
            'Father_PL' => 'VariantOnGenome/Sequencing/Father/Phredscaled_Likelihoods',// Used to calculate the allele value.
            'Father_PP' => 'VariantOnGenome/Sequencing/Father/Phredscaled_Probabilities',

            // Mother fields.
            'Mother_DP' => 'VariantOnGenome/Sequencing/Mother/Depth/Total',// We actually do not receive a value for depth in this column, we need to calculate this using AD & PL.
            'Mother_GQ' => 'VariantOnGenome/Sequencing/Mother/Genotype/Quality',
            'Mother_GT' => 'VariantOnGenome/Sequencing/Mother/GenoType',
            'Mother_JL' => 'VariantOnGenome/Sequencing/Mother/Phredscaled_Joint_Likelihood',
            'Mother_JP' => 'VariantOnGenome/Sequencing/Mother/Phredscaled_Joint_Probability',
            'Mother_PID' => 'VariantOnGenome/Sequencing/Mother/Physical_Phasing_ID',
            'Mother_PL' => 'VariantOnGenome/Sequencing/Mother/Phredscaled_Likelihoods',// Used to calculate the allele value.
            'Mother_PP' => 'VariantOnGenome/Sequencing/Mother/Phredscaled_Probabilities',

            // Columns that are created when processing data in lovd_prepareVariantData function.
            'Child_Depth_Ref' => 'VariantOnGenome/Sequencing/Depth/Ref', // Derived from Child_AD.
            'Child_Depth_Alt' => 'VariantOnGenome/Sequencing/Depth/Alt', // Derived from Child_AD.
            'Child_Alt_Percentage' => 'VariantOnGenome/Sequencing/Depth/Alt/Fraction', // Derived from Child_AD.
            'Father_Depth_Ref' => 'VariantOnGenome/Sequencing/Father/Depth/Ref', // Derived from Father_AD.
            'Father_Depth_Alt' => 'VariantOnGenome/Sequencing/Father/Depth/Alt', // Derived from Father_AD.
            'Father_Alt_Percentage' => 'VariantOnGenome/Sequencing/Father/Depth/Alt/Fraction', // Derived from Father_AD.
            'Father_VarPresent' => 'VariantOnGenome/Sequencing/Father/VarPresent',
            'Mother_Depth_Ref' => 'VariantOnGenome/Sequencing/Mother/Depth/Ref', // Derived from Mother_AD.
            'Mother_Depth_Alt' => 'VariantOnGenome/Sequencing/Mother/Depth/Alt', // Derived from Mother_AD.
            'Mother_Alt_Percentage' => 'VariantOnGenome/Sequencing/Mother/Depth/Alt/Fraction', // Derived from Mother_AD.
            'Mother_VarPresent' => 'VariantOnGenome/Sequencing/Mother/VarPresent',
            'PolyPhen_Text' => 'VariantOnTranscript/Prediction/PolyPhen_VEP',
            'PolyPhen_Value' => 'VariantOnTranscript/Prediction/PolyPhen_Score_VEP',
            'SIFT_Text' => 'VariantOnTranscript/Prediction/SIFT_VEP',
            'SIFT_Value' => 'VariantOnTranscript/Prediction/SIFT_Score_VEP',
            'Variant_Priority' => 'VariantOnGenome/Variant_priority',

            // Extra column for us to store data related to dropped transcripts
            'Variant_Remarks' => 'VariantOnGenome/Remarks',
            'IGV_Link' => 'VariantOnGenome/Sequencing/IGV'

        );

        return $aColumnMappings;
    }





    function prepareVariantData (&$aLine)
    {
        global $_INSTANCE_CONFIG;
        // Processes the variant data file for MGHA.
        // Cleans up data in existing columns and splits some columns out to two columns.

        // Make a copy of some columns so that they can be mapped to more than one columns
        // Here, we only import one copy of these columns.
        // We just don't want to modify existing mappings because they are used by the convert_and_merge script.
        $aLine['IGV_Link'] = '';
        $aLine['vog_alt'] = $aLine['ALT'];
        $aLine['vog_ref'] = $aLine['REF'];
        $aLine['vog_pos'] = $aLine['POS'];

        // Move transcripts that are to be dropped into VariantOnGenome/Remarks
        $aLine['Variant_Remarks'] = '';
        // Handle genes that start with 'LOC'.
        // Handle genes that are not found in our database.
        // Handle transcripts that are not found in our database.
        $bDropTranscript = false;
        if (!empty($aLine['SYMBOL']) && strpos(strtolower($aLine['SYMBOL']), 'loc') === 0) {
            $bDropTranscript = true;
        } elseif (!empty($aLine['SYMBOL']) && empty($this->aScriptVars['aGenes'][$aLine['SYMBOL']])) {
            $aLine['Variant_Remarks'] = "UNKNOWN GENE\n";
            if (!$_INSTANCE_CONFIG['conversion']['create_genes_and_transcripts']) {
                // IFokkema: Unfortunately, the MGHA adapter applies this filter in a bad location for the conversion
                //   script, as it hasn't had the chance yet to try and create the gene or transcript. It would be good
                //   to move this code downstream, perhaps generalizing it as the feature could be of use to others.
                $bDropTranscript = true;
            }
        } elseif (!empty($aLine['Feature']) && empty($this->aScriptVars['aTranscripts'][$aLine['Feature']])) {
            $aLine['Variant_Remarks'] = "UNKNOWN TRANSCRIPT\n";
            if (!$_INSTANCE_CONFIG['conversion']['create_genes_and_transcripts']) {
                // IFokkema: Unfortunately, the MGHA adapter applies this filter in a bad location for the conversion
                //   script, as it hasn't had the chance yet to try and create the gene or transcript. It would be good
                //   to move this code downstream, perhaps generalizing it as the feature could be of use to others.
                $bDropTranscript = true;
            }
        } elseif (!empty($aLine['HGVSc']) && strpos($aLine['HGVSc'], '*-') !== false) {
            $aLine['Variant_Remarks'] = "UNKNOWN CCHANGE NOMENCLATURE\n";
            $bDropTranscript = true;
        }

        if ($bDropTranscript) {
            $aLine['Variant_Remarks'] .= "SYMBOL: " . (!empty($aLine['SYMBOL'])? $aLine['SYMBOL'] : '') . "\n";
            $aLine['Variant_Remarks'] .= "HGVSc: " . (!empty($aLine['HGVSc'])? $aLine['HGVSc'] : '') . "\n";
            $aLine['Variant_Remarks'] .= "HGVSp: " . (!empty($aLine['HGVSp'])? $aLine['HGVSp'] : '') . "\n";
            $aLine['Variant_Remarks'] .= "Consequence: " . (!empty($aLine['Consequence'])? $aLine['Consequence'] : '')  . "\n";
            $aLine['Variant_Remarks'] .= "IMPACT: " . (!empty($aLine['IMPACT'])? $aLine['IMPACT'] : '')  . "\n";
            $aLine['Feature'] = static::NO_TRANSCRIPT;
        }

        if (isset($aLine['CPIPE_BED'])) {
            if (!empty($aLine['CPIPE_BED'])) {
                $aLine['CPIPE_BED'] = 1;
            }
        }

        $aColsWithAmpersands = array(
            'CLIN_SIG',
            'clinvar_clnsig',
            'clinvar_rs'
        );

        foreach ($aColsWithAmpersands as $sCol) {
            // Split clinical significance data into a string separated by comma.
            if (!empty($aLine[$sCol])) {
                $aLine[$sCol] = str_replace('&', ', ', $aLine[$sCol]);
            }
        }

        // clinvar_trait require further processing:
        // replace \x2c (HEX for comma) with comma
        // replace _ with space
        if (!empty($aLine['clinvar_trait'])) {
            // enclose each item separated by & with quote.
            $aLine['clinvar_trait'] = str_replace('&', '", "', $aLine['clinvar_trait']);
            $aLine['clinvar_trait'] = '"' . $aLine['clinvar_trait'] . '"';

            $aLine['clinvar_trait'] = str_replace('\x2c', ',', $aLine['clinvar_trait']);
            $aLine['clinvar_trait'] = str_replace('_', ' ', $aLine['clinvar_trait']);
        }

        // For MGHA the allele column is in the format A/A, C/T etc. Leiden have converted this to 1/1, 0/1, etc.
        // MGHA also need to calculate the VarPresent for Father and Mother as this is required later on when assigning a value to allele
        if (isset($aLine['Child_GT'])) {
            $aChildGenotypes = explode('/', $aLine['Child_GT']);
            if ($aLine['Child_GT'] == './.') {
                // We set it to '' as this is what Leiden do.
                $aLine['Child_GT'] = '';
            } elseif ($aChildGenotypes[0] !== $aChildGenotypes[1]) {
                // Het.
                $aLine['Child_GT'] = '0/1';
            } elseif ($aChildGenotypes[0] == $aChildGenotypes[1] && $aChildGenotypes[0] == $aLine['ALT']) {
                // Homo alt.
                $aLine['Child_GT'] = '1/1';
            } elseif ($aChildGenotypes[0] == $aChildGenotypes[1] && $aChildGenotypes[0] == $aLine['REF']) {
                // Homo ref.
                $aLine['Child_GT'] = '0/0';
            }
        }
        // Calculate the Childs allele depths and fraction.
        if (isset($aLine['Child_AD'])) {
            // Child_AD(x,y)
            // Calculate the alt depth as fraction (/100).
            $aChildAllelicDepths = explode(',', $aLine['Child_AD']);
            // Set the ref and alt values in $aLine.
            $aLine['Child_Depth_Ref'] = $aChildAllelicDepths[0];
            $aLine['Child_Depth_Alt'] = $aChildAllelicDepths[1];
            if ($aChildAllelicDepths[1] == 0) {
                $aLine['Child_Alt_Percentage'] = 0;
            } else {
                $aLine['Child_Alt_Percentage'] = $aChildAllelicDepths[1] / ($aChildAllelicDepths[0] + $aChildAllelicDepths[1]);
            }
        }
        if (!empty($aLine['Mother_GT']) || !empty($aLine['Father_GT'])){
            // Check whether the mother or father's genotype is present.
            // If so we are dealing with a trio and we need to calculate the following.
            foreach (array('Father','Mother') as $sParent) {
                // Get the genotypes for the parents and compare them to each other.
                // Data is separated by a / or a |.
                if (strpos($aLine[$sParent . '_GT'], '|') !== false) {
                    $aParentGenotypes = explode('|', $aLine[$sParent . '_GT']);
                } elseif (strpos($aLine[$sParent . '_GT'], '/') !== false) {
                    $aParentGenotypes = explode('/', $aLine[$sParent . '_GT']);
                } else {
                    die('Unexpected delimiter in ' . $sParent . '_GT column. We cannot process the file as values from this column are required to calculate the allele.' . ".\n");
                }
                // Calculate the VarPresent for the mother and the father using the allelic depths (Parent_AD) and Phred-scaled Likelihoods (Parent_PL)
                // Parent_AD(x,y)   Parent_PL(a,b,c)
                // Calculate the alt depth as fraction (/100).
                $aParentAllelicDepths = explode(',', $aLine[$sParent . '_AD']);
                // Set the ref and alt values in $aLine.
                $aLine[$sParent . '_Depth_Ref'] = $aParentAllelicDepths[0];
                $aLine[$sParent . '_Depth_Alt'] = $aParentAllelicDepths[1];
                if ($aParentAllelicDepths[1] == 0) {
                    $sParentAltPercentage = 0;
                } else {
                    // alt percentage = Parent_AD(y) / (Parent.AD(x) + Parent.AD(y))
                    $sParentAltPercentage = $aParentAllelicDepths[1] / ($aParentAllelicDepths[0] + $aParentAllelicDepths[1]);
                }
                // Set the alt percentage in $aLine.
                $aLine[$sParent . '_Alt_Percentage'] = $sParentAltPercentage;
                if ($aLine[$sParent . '_PL'] == '' || $aLine[$sParent . '_PL'] == 'unknown') {
                    $sParentPLAlt = 'unknown';
                } else {
                    $aParentPL = explode(',', $aLine[$sParent . '_PL']);
                    $sParentPLAlt = $aParentPL[1]; // Parent PLAlt = Parent_PL(b)
                }
                if ($aParentGenotypes[0] == $aParentGenotypes[1] && $aParentGenotypes[0] == $aLine['ALT']) {
                    // Homo alt.
                    $aLine[$sParent . '_GT'] = '1/1';
                    $aLine[$sParent . '_VarPresent'] = 6;
                } elseif ($aParentGenotypes[0] !== $aParentGenotypes[1]) {
                    // Het.
                    $aLine[$sParent . '_GT'] = '0/1';
                    $aLine[$sParent . '_VarPresent'] = 6;
                } else {
                    if ($aParentGenotypes[0] == $aParentGenotypes[1] && $aParentGenotypes[0] == $aLine['REF']) {
                        // Homo ref.
                        $aLine[$sParent . '_GT'] = '0/0';
                    }
                    if ($aLine[$sParent . '_GT'] == './.') {
                        // We set it to '' as this is what Leiden do.
                        $aLine[$sParent . '_GT'] = '';
                    }
                    if ($sParentAltPercentage > 10) {
                        $aLine[$sParent . '_VarPresent'] = 5;
                    } elseif ($sParentAltPercentage > 0 && $sParentAltPercentage <= 10) {
                        $aLine[$sParent . '_VarPresent'] = 4;
                    } elseif ($sParentPLAlt < 30 || $sParentPLAlt == 'unknown') {
                        $aLine[$sParent . '_VarPresent'] = 3;
                    } elseif ($sParentPLAlt >= 30 && $sParentPLAlt < 60) {
                        $aLine[$sParent . '_VarPresent'] = 2;
                    } else {
                        $aLine[$sParent . '_VarPresent'] = 1;
                    }
                }
            }
        }
        // Split up PolyPhen to extract text and value.
        if (preg_match('/(\D+)\((.+)\)/',$aLine['PolyPhen'],$aPoly)){
            $aLine['PolyPhen_Text'] = $aPoly[1];
            $aLine['PolyPhen_Value'] = $aPoly[2];
        }
        // Split up SIFT to extract text and value.
        if (preg_match('/(\D+)\((.+)\)/',$aLine['SIFT'],$aSIFT)){
            $aLine['SIFT_Text'] = $aSIFT[1];
            $aLine['SIFT_Value'] = $aSIFT[2];
        }
        // FREQUENCIES
        // Make all bases uppercase.
        $sRef = strtoupper($aLine['REF']);
        $sAlt = strtoupper($aLine['ALT']);
        // 'Eat' letters from either end - first left, then right - to isolate the difference.
        while (strlen($sRef) > 0 && strlen($sAlt) > 0 && $sRef{0} == $sAlt{0}) {
            $sRef = substr($sRef, 1);
            $sAlt = substr($sAlt, 1);
        }
        while (strlen($sRef) > 0 && strlen($sAlt) > 0 && $sRef[strlen($sRef) - 1] == $sAlt[strlen($sAlt) - 1]) {
            $sRef = substr($sRef, 0, -1);
            $sAlt = substr($sAlt, 0, -1);
        }
        // Insertions/duplications, deletions, inversions, indels.
        // We do not want to display the frequencies for these, set frequency columns to empty.
        if (strlen($sRef) != 1 || strlen($sAlt) != 1) {
            $sAlt = '';
        }

        // Some frequency columns comes in the format of alt1:freq1&alt2:freq2.
        // We need to match the value with the corresponding value in ALT column.
        $aAltFreqColumns = array(
            'EA_MAF',
            'AA_MAF'
        );

        foreach($aAltFreqColumns as $sFreqColumn) {
            if (!isset($aLine[$sFreqColumn]) || $aLine[$sFreqColumn] == 'unknown' || $aLine[$sFreqColumn] == '' || $sAlt == '' || empty($sAlt) || strlen($sAlt) == 0) {
                $aLine[$sFreqColumn] = '';
            } else {
                $aFreqArr = explode("&", $aLine[$sFreqColumn]);
                $aFreqValArray = array();
                foreach ($aFreqArr as $freqData) {
                    if (preg_match('/^(\D+)\:(.+)$/', $freqData, $freqCalls)) {
                        $sFreqPrefix = $freqCalls[1];
                        if ($sFreqPrefix == $sAlt && is_numeric($freqCalls[2])){
                            array_push($aFreqValArray, $freqCalls[2]);
                        }
                    }
                }
                // Check there are values in the array before taking max.
                $sFreqCheck = array_filter($aFreqValArray);
                if (!empty($sFreqCheck)){
                    $aLine[$sFreqColumn] = max($aFreqValArray);
                } else {
                    $aLine[$sFreqColumn] = '';
                }
            }
        }

        // We need to calculate AF_Adj using AC_Adj and AN_Adj
        // All population specific columns are already adjusted (only include those with DP >= 10 & GQ >= 20)
        $aFreqColsToCalculate = array(
            'cpipe_ExAC_' => array(
                'Adj', // general population adjusted
                'AFR',
                'AMR',
                'CONSANGUINEOUS',
                'EAS',
                'FEMALE',
                'FIN',
                'MALE',
                'NFE',
                'OTH',
                'SAS'
            ),

            // Other examples if we have other frequency data in the future.
            //'cpipe_1000Gp3_' => array(
            //
            //)
        );


        foreach ($aFreqColsToCalculate as $sPrefix => $aColsPopulation) {
            foreach ($aColsPopulation as $sPopulation) {
                $aLine[$sPrefix . 'AF_' . $sPopulation] = '';

                // If coloumns exist, we can process them.
                if (isset($aLine[$sPrefix . 'AN_' . $sPopulation]) && isset($aLine[$sPrefix . 'AC_' . $sPopulation])) {
                    $sAC = $aLine[$sPrefix . 'AC_' . $sPopulation];
                    $sAN = $aLine[$sPrefix . 'AN_' . $sPopulation];

                    // If NOT numeric, we leave it as an empty string (defined above).
                    // If numeric, initialise with 0.
                    if (is_numeric($sAC) && is_numeric($sAN)) {
                        $aLine[$sPrefix . 'AF_' . $sPopulation] = 0;

                        // If they are not zero, then calculate frequency.
                        if (!empty($sAC) && !empty($sAN)) {
                            $aLine[$sPrefix . 'AF_' . $sPopulation] = (float) $sAC/ (float) $sAN;
                        }
                    }
                }

            }
        }


        // Calculate the max frequency so that we can use it for variant priority calculation.
        $aFreqCalcColumns = array(
            'cpipe_ExAC_AF_Adj',
            'cpipe_1000Gp3_AF'
        );

        $sMaxFreq = '';
        foreach ($aFreqCalcColumns as $sFreqCol) {
            if (isset($aLine[$sFreqCol])) {

                // If no value has been assigned previously, just assign the first frequency (could be '' or numeric).
                if ($sMaxFreq === '') {
                    $sMaxFreq = $aLine[$sFreqCol];
                } else {
                    if ($aLine[$sFreqCol] > $sMaxFreq) {
                        $sMaxFreq = $aLine[$sFreqCol];
                    }
                }

            }
        }


        // Variant Priority.
        if (!empty($aLine['CPIPE_BED'])) {
            $aLine['Variant_Priority'] = 6;
        } else {
            if ($aLine['IMPACT'] == 'HIGH') {
                if ($sMaxFreq === '') {
                    // If novel - SNP138 ($aLine['ID']) is = '.' or '' and there is no frequency.
                    $aLine['Variant_Priority'] = 5;
                } elseif ($sMaxFreq <= 0.0005) {
                    $aLine['Variant_Priority'] = 4;
                } elseif ($sMaxFreq <= 0.01) {
                    $aLine['Variant_Priority'] = 3;
                } else {
                    $aLine['Variant_Priority'] = 1;
                }
            } elseif ($aLine['IMPACT'] == 'MODERATE') {
                if ($sMaxFreq <= 0.01) {
                    // Check if it is rare.
                    if ($sMaxFreq === '' || $sMaxFreq <= 0.0005) {
                        // check if novel - SNP138 ($aLine['ID']) is = '.' or '' and there is no frequency OR if very rare (<0.0005).
                        if ($aLine['Condel'] >= 0.07) {
                            // Check if it is conserved - condel >= 0.07.
                            $aLine['Variant_Priority'] = 4;
                        } else {
                            $aLine['Variant_Priority'] = 3;
                        }
                    } else {
                        $aLine['Variant_Priority'] = 2;
                    }
                } else {
                    $aLine['Variant_Priority'] = 1;
                }
            } elseif ($aLine['IMPACT'] == 'LOW') {
                $aLine['Variant_Priority'] = 0;
            } elseif ($aLine['IMPACT'] == 'MODIFIER') {
                $aLine['Variant_Priority'] = 0;
            } else {
                $aLine['Variant_Priority'] = 0;
            }
        }
        return $aLine;
    }





    function formatEmptyColumn($aLine, $sVEPColumn)
    {
        // Returns how we want to represent empty data in $aVariant array given a LOVD column name.
        if (isset($aLine[$sVEPColumn]) && ($aLine[$sVEPColumn] === 0 || $aLine[$sVEPColumn] === '0')) {
            return 0;
        } else {
            return '';
        }
    }






    function postValueAssignmentUpdate($sKey, &$aVariant, &$aData)
    {
        // Update $aData if there is any aggregated data that we need to update after each input line is read.
        // 0 index in  $aData[$sKey] is where we store the VOG data

        if ($aVariant['VariantOnGenome/Variant_priority'] > $aData[$sKey][0]['VariantOnGenome/Variant_priority']){
            // update the VOG record to have the higher variant priority
            $aData[$sKey][0]['VariantOnGenome/Variant_priority'] = $aVariant['VariantOnGenome/Variant_priority'];
        }

        // Create IGV links
        $aLinkTypes = array('bhc', 'rec');
        if (!empty($this->aMetadata['Individuals']['Individual/Sample_ID']) &&
            !empty($this->aMetadata['Screenings']['Screening/Pipeline/Run_ID']) &&
            !empty($aVariant['chromosome']) &&
            !empty($aVariant['position_g_start']) &&
            !empty($aVariant['position_g_end'])
        ) {
            $aData[$sKey][0]['VariantOnGenome/Sequencing/IGV'] = '';
            $aLinks = array();
            foreach ($aLinkTypes as $sLinkPrefix) {
                $aLinks[] = '{' . $sLinkPrefix . ':' .
                            implode(':', array($this->aMetadata['Individuals']['Individual/Sample_ID'],
                                               $this->aMetadata['Screenings']['Screening/Pipeline/Run_ID'],
                                               $aVariant['chromosome'],
                                               $aVariant['position_g_start'],
                                               $aVariant['position_g_end']))
                            . '}';
            }

            $aVariant['VariantOnGenome/Sequencing/IGV'] = $aData[$sKey][0]['VariantOnGenome/Sequencing/IGV'] = implode(' ', $aLinks);
        }

    }





    function getRequiredHeaderColumns ()
    {
        // Returns an array of required input variant file column headers.
        // The order of these columns does NOT matter.

        return array(
            'CHROM',
            'POS',
            'ID',
            'REF',
            'ALT',
            'QUAL',
            'FILTER',
            'Child_GT',
        );
    }
}
