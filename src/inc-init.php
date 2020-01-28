<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-19
 * Modified    : 2020-01-16
 * For LOVD    : 3.0-22
 *
 * Copyright   : 2004-2020 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Daan Asscheman <D.Asscheman@LUMC.nl>
 *               M. Kroon <m.kroon@lumc.nl>
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

// Don't allow direct access.
if (!defined('ROOT_PATH')) {
    exit;
}

// Require library standard functions.
require_once ROOT_PATH . 'inc-lib-init.php';

// Define module path.
// FIXME; do we still need this?
define('MODULE_PATH', ROOT_PATH . 'modules/');

// Set error_reporting if necessary. We don't want notices to show. This will do
// fine most of the time.
if (ini_get('error_reporting') == E_ALL) {
    error_reporting(E_ALL ^ E_NOTICE);
}

// DMD_SPECIFIC!!! - Testing purposes only.
if ($_SERVER['HTTP_HOST'] == 'localhost') {
    error_reporting(E_ALL | E_STRICT);
}





// Define constants needed throughout LOVD.
// Find out whether or not we're using SSL.
if ((!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || !empty($_SERVER['SSL_PROTOCOL'])) {
    // We're using SSL!
    define('SSL', true);
    define('PROTOCOL', 'https://');
} else {
    define('SSL', false);
    define('PROTOCOL', 'http://');
}

// Prevent some troubles with the menu or lovd_getProjectFile() when the URL contains double slashes or backslashes.
$_SERVER['SCRIPT_NAME'] = lovd_cleanDirName(str_replace('\\', '/', $_SERVER['SCRIPT_NAME']));

// Our output formats: text/html by default.
$aFormats = array('text/html', 'text/plain'); // Key [0] is default. Other values may not always be allowed. It is checked in the Template class' printHeader() and in Objects::viewList().
if (lovd_getProjectFile() == '/api.php') {
    $aFormats[] = 'application/json';
    $aFormats[] = 'text/bed';
} elseif (lovd_getProjectFile() == '/import.php' && substr($_SERVER['QUERY_STRING'], 0, 25) == 'autoupload_scheduled_file') {
    // Set format to text/plain only when none is requested.
    if (empty($_GET['format']) || !in_array($_GET['format'], $aFormats)) {
        $_GET['format'] = 'text/plain';
    }
}
if (!empty($_GET['format']) && in_array($_GET['format'], $aFormats)) {
    define('FORMAT', $_GET['format']);
} else {
    define('FORMAT', $aFormats[0]);
}
// @ is to suppress errors in Travis test.
@header('Content-type: ' . FORMAT . '; charset=UTF-8');

define('COLLEAGUE_CAN_EDIT', 1);    // Colleagues that have edit permissions.
define('COLLEAGUE_CANNOT_EDIT', 2); // Colleagues that have no edit permissions.
define('COLLEAGUE_ALL', 3);         // All colleagues.

define('LEVEL_SUBMITTER', 1);    // Also includes collaborators and curators. Authorization is depending on assignments, not user levels anymore.
define('LEVEL_ANALYZER', 2);     // Diagnostics: LOVD+ introduced this user level.
define('LEVEL_COLLABORATOR', 3); // THIS IS NOT A VALID USER LEVEL. Just indicates level of authorization. You can change these numbers, but keep the order!
define('LEVEL_OWNER', 4);        // THIS IS NOT A VALID USER LEVEL. Just indicates level of authorization. You can change these numbers, but keep the order!
define('LEVEL_CURATOR', 5);      // THIS IS NOT A VALID USER LEVEL. Just indicates level of authorization. You can change these numbers, but keep the order!
define('LEVEL_MANAGER', 7);
define('LEVEL_ADMIN', 9);

define('STATUS_IN_PROGRESS', 1); // Submission not yet completed.
define('STATUS_PENDING', 2);     // Submission completed and curator notified, but awaiting curation.
define('STATUS_HIDDEN', 4);
define('STATUS_MARKED', 7);
define('STATUS_OK', 9);

define('AJAX_FALSE', '0');
define('AJAX_TRUE', '1');
define('AJAX_UNKNOWN_RESPONSE', '6');
define('AJAX_CONNECTION_ERROR', '7');
define('AJAX_NO_AUTH', '8');
define('AJAX_DATA_ERROR', '9');

define('MAPPING_ALLOW', 1);
define('MAPPING_ALLOW_CREATE_GENES', 2);
define('MAPPING_IN_PROGRESS', 4);
define('MAPPING_NOT_RECOGNIZED', 8);    // FIXME; Create a button in Setup which clears all NOT_RECOGNIZED flags in the database to retry them.
define('MAPPING_ERROR', 16);            // FIXME; Create a button in Setup which clears all ERROR flags in the database to retry them.
define('MAPPING_DONE', 32);             // FIXME; Create a button in Setup which clears all DONE flags in the database to retry them.

// Define constant to quickly check if we're on Windows, since sending emails on Windows requires different settings.
define('ON_WINDOWS', (strtoupper(substr(PHP_OS, 0, 3) == 'WIN')));

// Diagnostics: To make it easier to share certain code between
// LOVD and LOVD+, simply define if we're active or not.
@define('LOVD_plus', false);

// For the installation process (and possibly later somewhere else, too).
$aRequired =
         array(
                'PHP'   => '5.3.0',
                'PHP_functions' =>
                     array(
                            'mb_detect_encoding',
                            'openssl_seal',      // We could also look for openssl constants?
                          ),
                'PHP_classes' =>
                     array(
                          ),
                'MySQL' => '4.1.2',
              );

// Initiate $_SETT array.
$_SETT = array(
                'system' =>
                     array(
                            'version' => '3.0-22',
                          ),
                'user_levels' =>
                     array(
                            LEVEL_ADMIN        => 'Database administrator',
                            LEVEL_MANAGER      => 'Manager',
                            LEVEL_CURATOR      => 'Curator',
                            LEVEL_OWNER        => 'Submitter (data owner)',
                            LEVEL_COLLABORATOR => 'Collaborator',
                            LEVEL_SUBMITTER    => 'Submitter',
                          ),
                'user_level_settings' =>
                array(
                    // Checking for LEVEL_COLLABORATOR assumes lovd_isAuthorized()
                    // has already been called for gene-specific overviews.
                    'delete_individual' => (LOVD_plus? LEVEL_ADMIN : LEVEL_CURATOR),
                    'delete_variant' => (LOVD_plus? LEVEL_ADMIN : LEVEL_CURATOR),
                    // The see_nonpublic_data setting currently also defines the visibility
                    //  of the status, created* and edited* fields.
                    'see_nonpublic_data' => (LOVD_plus? LEVEL_SUBMITTER : LEVEL_COLLABORATOR),
                    'set_concluded_effect' => (LOVD_plus? LEVEL_MANAGER : LEVEL_CURATOR),
                    'submit_new_data' => (LOVD_plus? LEVEL_MANAGER : LEVEL_SUBMITTER),
                ),
                'gene_imprinting' =>
                     array(
                            'unknown'  => 'Unknown',
                            'no'       => 'Not imprinted',
                            'maternal' => 'Imprinted, maternal',
                            'paternal' => 'Imprinted, paternal'
                          ),
                'var_effect' =>
                (LOVD_plus?
                     array(
                            0 => 'Not curated',
                            5 => 'VUS',
                            9 => 'Pathogenic',
                            7 => 'Likely pathogenic',
                            3 => 'Likely benign',
                            1 => 'Benign',
                          ) :
                    array(
                            0 => 'Not classified', // Submitter cannot select this.
                            9 => 'Affects function',
                            8 => 'Affects function, not associated with individual\'s disease phenotype',
                            6 => 'Affects function, not associated with any known disease phenotype',
                            7 => 'Probably affects function',
                            3 => 'Probably does not affect function',
                            1 => 'Does not affect function',
                            5 => 'Effect unknown',
                          )),
                'var_effect_api' =>
                     array(
                            // The API requires different, concise but clear, values.
                            0 => 'notClassified',
                            9 => 'functionAffected',
                            8 => 'notThisDisease',
                            6 => 'notAnyDisease',
                            7 => 'functionProbablyAffected',
                            3 => 'functionProbablyNotAffected',
                            1 => 'functionNotAffected',
                            5 => 'unknown',
                          ),
                'var_effect_short' =>
                    array(
                        0 => '.',   // Not classified
                        1 => '-',   // Does not affect function
                        3 => '-?',  // Probably does not affect function
                        5 => '?',   // Effect unknown
                        6 => '#',   // Variant affects function but was not associated with any known disease phenotype
                        7 => '+?',  // Probably affects function
                        8 => '+*',  // Variant affects function but was not associated with this individual's disease phenotype
                        9 => '+',   // Affects function
                    ),
                'var_effect_default' => '00',
                'diseases_inheritance' =>
                    array(
                        'AD' => 'Autosomal dominant',
                        'PI' => 'Autosomal dominant with paternal imprinting',
                        'MI' => 'Autosomal dominant with maternal imprinting',
                        'AR' => 'Autosomal recessive',
                        'DD' => 'Digenic dominant',
                        'DR' => 'Digenic recessive',
                        'IC' => 'Isolated Cases (Sporadic)',
                        'Mi' => 'Mitochondrial',
                        'Mu' => 'Multifactorial',
                        'SMo' => 'Somatic mosaicism',
                        'SMu' => 'Somatic mutation',
                        'OG' => 'Oligogenic (3 genes)',
                        'PG' => 'Polygenic (>3 genes)',
                        'XL' => 'X-linked',
                        'XLD' => 'X-linked dominant',
                        'XLR' => 'X-linked recessive',
                        'YL' => 'Y-linked',
                    ),
                'data_status' =>
                     array(
                            STATUS_IN_PROGRESS => 'In progress',
                            STATUS_PENDING => 'Pending',
                            STATUS_HIDDEN => 'Non public',
                            STATUS_MARKED => 'Marked',
                            STATUS_OK => 'Public',
                          ),
                'import_priorities' =>
                    array(
                        0 => 'Default',
                        4 => 'Medium priority',
                        9 => 'High priority',
                    ),
                'update_levels' =>
                     array(
                            1 => 'Optional',
                            4 => 'Common',
                            5 => 'Suggested',
                            7 => 'Recommended',
                            8 => '<SPAN style="color:red;">Important</SPAN>',
                            9 => '<SPAN style="color:red;"><B>Critical</B></SPAN>',
                          ),
                'upstream_URL' => 'http://www.LOVD.nl/',
                'upstream_BTS_URL' => 'https://github.com/LOVDnl/LOVD3/issues/',
                'upstream_BTS_URL_new_ticket' => 'https://github.com/LOVDnl/LOVD3/issues/new',
                'wikiprofessional_iprange' => '131.174.88.0-255',
                'list_sizes' =>
                     array(
                            10,
                            25,
                            50,
                            100,
                            250,
                            500,
                            1000,
                          ),
                'lists' =>
                    array(
                        'max_sortable_rows' => 250000,
                    ),
                'notes_align' =>
                     array(
                            -1 => 'left',
                            0  => 'center',
                            1  => 'right',
                          ),
                'unique_view_max_string_length' => 100,
                'objectid_length' =>
                    array(
                        'analyses' => 3,
                        'analysisruns' => 5,
                        'diseases' => 5,
                        'individuals' => 8,
                        'links' => 3,
                        'phenotypes' => 10,
                        'screenings' => 10,
                        // Warning! Length of transcript IDs also configured in inc-js-variants.php.
                        'transcripts' => 8,
                        'users' => 5,
                        'variants' => 10,
                    ),
                'human_builds' =>
                     array(
                            '----' => array('ncbi_name' => 'non-Human'),
                            // This information has been taken from the release notes of the builds;
                            // http://www.ncbi.nlm.nih.gov/genome/guide/human/release_notes.html
                            'hg18' =>
                                     array(
                                            'ncbi_name'      => 'Build 36.1',
                                         // FIXME: This information is also stored in the chromosomes table.
                                         // Remove it from here?
                                            'ncbi_sequences' =>
                                                     array(
                                                            '1'  => 'NC_000001.9',
                                                            '2'  => 'NC_000002.10',
                                                            '3'  => 'NC_000003.10',
                                                            '4'  => 'NC_000004.10',
                                                            '5'  => 'NC_000005.8',
                                                            '6'  => 'NC_000006.10',
                                                            '7'  => 'NC_000007.12',
                                                            '8'  => 'NC_000008.9',
                                                            '9'  => 'NC_000009.10',
                                                            '10' => 'NC_000010.9',
                                                            '11' => 'NC_000011.8',
                                                            '12' => 'NC_000012.10',
                                                            '13' => 'NC_000013.9',
                                                            '14' => 'NC_000014.7',
                                                            '15' => 'NC_000015.8',
                                                            '16' => 'NC_000016.8',
                                                            '17' => 'NC_000017.9',
                                                            '18' => 'NC_000018.8',
                                                            '19' => 'NC_000019.8',
                                                            '20' => 'NC_000020.9',
                                                            '21' => 'NC_000021.7',
                                                            '22' => 'NC_000022.9',
                                                            'X'  => 'NC_000023.9',
                                                            'Y'  => 'NC_000024.8',
                                                            'M'  => 'NC_001807.4',
                                                          ),
                                          ),
                            'hg19' =>
                                     array(
                                            'ncbi_name'      => 'GRCh37',
                                         // FIXME: This information is also stored in the chromosomes table.
                                         // Remove it from here?
                                            'ncbi_sequences' =>
                                                     array(
                                                            '1'  => 'NC_000001.10',
                                                            '2'  => 'NC_000002.11',
                                                            '3'  => 'NC_000003.11',
                                                            '4'  => 'NC_000004.11',
                                                            '5'  => 'NC_000005.9',
                                                            '6'  => 'NC_000006.11',
                                                            '7'  => 'NC_000007.13',
                                                            '8'  => 'NC_000008.10',
                                                            '9'  => 'NC_000009.11',
                                                            '10' => 'NC_000010.10',
                                                            '11' => 'NC_000011.9',
                                                            '12' => 'NC_000012.11',
                                                            '13' => 'NC_000013.10',
                                                            '14' => 'NC_000014.8',
                                                            '15' => 'NC_000015.9',
                                                            '16' => 'NC_000016.9',
                                                            '17' => 'NC_000017.10',
                                                            '18' => 'NC_000018.9',
                                                            '19' => 'NC_000019.9',
                                                            '20' => 'NC_000020.10',
                                                            '21' => 'NC_000021.8',
                                                            '22' => 'NC_000022.10',
                                                            'X'  => 'NC_000023.10',
                                                            'Y'  => 'NC_000024.9',
                                                            'M'  => 'NC_012920.1',
                                                          ),
                                          ),
                            // http://www.ncbi.nlm.nih.gov/projects/genome/assembly/grc/human/data/
                            'hg38' =>
                                     array(
                                            'ncbi_name'      => 'GRCh38',
                                         // FIXME: This information is also stored in the chromosomes table.
                                         // Remove it from here?
                                            'ncbi_sequences' =>
                                                     array(
                                                            '1'  => 'NC_000001.11',
                                                            '2'  => 'NC_000002.12',
                                                            '3'  => 'NC_000003.12',
                                                            '4'  => 'NC_000004.12',
                                                            '5'  => 'NC_000005.10',
                                                            '6'  => 'NC_000006.12',
                                                            '7'  => 'NC_000007.14',
                                                            '8'  => 'NC_000008.11',
                                                            '9'  => 'NC_000009.12',
                                                            '10' => 'NC_000010.11',
                                                            '11' => 'NC_000011.10',
                                                            '12' => 'NC_000012.12',
                                                            '13' => 'NC_000013.11',
                                                            '14' => 'NC_000014.9',
                                                            '15' => 'NC_000015.10',
                                                            '16' => 'NC_000016.10',
                                                            '17' => 'NC_000017.11',
                                                            '18' => 'NC_000018.10',
                                                            '19' => 'NC_000019.10',
                                                            '20' => 'NC_000020.11',
                                                            '21' => 'NC_000021.9',
                                                            '22' => 'NC_000022.11',
                                                            'X'  => 'NC_000023.11',
                                                            'Y'  => 'NC_000024.10',
                                                            'M'  => 'NC_012920.1',
                                                          ),
                                          ),
                    ),
                // Mitochondrial aliases. The key is the gene symbol used by HGNC, the value is the gene symbol used by NCBI.
                'mito_genes_aliases' =>
                    array(
                            'MT-TF' => 'TRNF',
                            'MT-RNR1' => 'RNR1',
                            'MT-TV' => 'TRNV',
                            'MT-RNR2' => 'RNR2',
                            'MT-TL1' => 'TRNL1',
                            'MT-ND1' => 'ND1',
                            'MT-TI' => 'TRNI',
                            'MT-TM' => 'TRNM',
                            'MT-ND2' => 'ND2',
                            'MT-TW' => 'TRNW',
                            'MT-CO1' => 'COX1',
                            'MT-TD' => 'TRND',
                            'MT-CO2' => 'COX2',
                            'MT-TK' => 'TRNK',
                            'MT-ATP8' => 'ATP8',
                            'MT-ATP6' => 'ATP6',
                            'MT-CO3' => 'COX3',
                            'MT-TG' => 'TRNG',
                            'MT-ND3' => 'ND3',
                            'MT-TR' => 'TRNR',
                            'MT-ND4L' => 'ND4L',
                            'MT-ND4' => 'ND4',
                            'MT-TH' => 'TRNH',
                            'MT-TS2' => 'TRNS2',
                            'MT-TL2' => 'TRNL2',
                            'MT-ND5' => 'ND5',
                            'MT-CYB' => 'CYTB',
                            'MT-TT' => 'TRNT',
                    ),
            );

// Complete version info.
list($_SETT['system']['tree'], $_SETT['system']['build']) = explode('-', $_SETT['system']['version'], 2);
$_SETT['update_URL'] = $_SETT['upstream_URL'] . $_SETT['system']['tree'] . '/package_update.php';
$_SETT['check_location_URL'] = $_SETT['upstream_URL'] . $_SETT['system']['tree'] . '/check_location.php';

// Additions for Diagnostics.
if (LOVD_plus) {
    define('ANALYSIS_STATUS_WAIT', 0);              // Individual and screening imported, variants not yet (doesn't happen anymore).
    define('ANALYSIS_STATUS_READY', 1);             // All data imported, waiting for somebody to start analysis.
    define('ANALYSIS_STATUS_IN_PROGRESS', 2);       // Analysis started.
    define('ANALYSIS_STATUS_CLOSED', 4);            // Analysis complete, needs to be approved by staff.
    define('ANALYSIS_STATUS_WAIT_CONFIRMATION', 6); // Staff has checked, now waiting for confirmation of variants in the lab.
    define('ANALYSIS_STATUS_CONFIRMED', 7);         // Variants were confirmed, analysis is completely done now.
    define('ANALYSIS_STATUS_ARCHIVED', 9);          // Variants have been taken offline.

    define('CON_STATUS_NOT_PERFORMED', 0); // The variant has not yet been assessed.
    define('CON_STATUS_NOT_REQUIRED', 1);  // A curator has determined that this variant does not require confirmation so curation can begin immediately.
    define('CON_STATUS_REQUIRED', 3);      // A curator has determined that this variant requires confirmation.
    define('CON_STATUS_PASSED', 5);        // This variant has been confirmed to be real and passes the confirmation process.
    define('CON_STATUS_FAILED', 7);        // This variant has been confirmed to not be real and fails the confirmation process.

    define('CUR_STATUS_VARIANT_OF_INTEREST', 10);    // A curator has determined that this variant requires curation. We still need a second person to confirm this before any curation takes place.
    define('CUR_STATUS_FOR_CURATION', 20);           // A second curator has confirmed that this variant should be curated so the curation can begin.
    define('CUR_STATUS_REQUIRES_CONFIRMATION', 30);  // Needs additional labwork to confirm the variant is correct, e.g. sanger.
    define('CUR_STATUS_CONFIRMED', 40);              // Additional labwork has been completed and the variant is confirmed to be correct. (We don't have a status if the variant turns out to be incorrect, maybe we need one?)
    define('CUR_STATUS_PROPOSED', 50);               // A curator has completed the curation process and proposed a classification. It will be discussed at a meeting before a final classification is decided.
    define('CUR_STATUS_CURATED_REPORTABLE', 70);     // A final classification has been determined and this variant is to appear on a report. The curation process is now finished.
    define('CUR_STATUS_CURATED_NOT_REPORTABLE', 80); // A final classification has been determined but this variant is not to appear on a report. The curation process is now finished.
    define('CUR_STATUS_NOT_FOR_CURATION', 90);       // A curator has determined that this variant does not require curation and no further action will be taken on this variant.
    define('CUR_STATUS_ARTEFACT', 91);               // A curator has determined that this variant does not exist as a result of a sequencing error.

    $_SETT['analysis_status'] =
        array(
            ANALYSIS_STATUS_WAIT => 'Waiting for data upload',
            ANALYSIS_STATUS_READY => 'Ready for analysis',
            ANALYSIS_STATUS_IN_PROGRESS => 'In progress',
            ANALYSIS_STATUS_CLOSED => 'Closed',
            ANALYSIS_STATUS_WAIT_CONFIRMATION => 'Awaiting confirmation',
            ANALYSIS_STATUS_CONFIRMED => 'Confirmed',
            ANALYSIS_STATUS_ARCHIVED => 'Archived',
        );
    $_SETT['confirmation_status'] =
        array(
            CON_STATUS_NOT_PERFORMED => 'Not performed',
            CON_STATUS_NOT_REQUIRED => 'Not required',
            CON_STATUS_REQUIRED => 'Required',
            CON_STATUS_PASSED => 'Passed',
            CON_STATUS_FAILED => 'Failed',
        );
    $_SETT['curation_status'] =
        array(
            CUR_STATUS_VARIANT_OF_INTEREST => 'Variant of Interest',
            CUR_STATUS_NOT_FOR_CURATION => 'Not for Curation',
            CUR_STATUS_ARTEFACT => 'Artefact',
            CUR_STATUS_FOR_CURATION => 'For Curation',
            CUR_STATUS_REQUIRES_CONFIRMATION => 'Requires Confirmation',
            CUR_STATUS_CONFIRMED => 'Confirmed',
            CUR_STATUS_PROPOSED => 'Proposed Classification',
            CUR_STATUS_CURATED_REPORTABLE => 'Curated & Reportable',
            CUR_STATUS_CURATED_NOT_REPORTABLE => 'Curated & Not Reportable',
        );
    $_SETT['clinvar_var_effect'] =
        array(
            '2' => 'Benign',
            '3' => 'Likely benign',
            '4' => 'Likely pathogenic',
            '5' => 'Pathogenic',
            '6' => 'Drug response',
            '7' => 'histocompatibility'
        );
    $_SETT['filter_cross_screenings'] = array(
        'condition_list' => array(
            'NOT IN' => 'not found in',
            'NOT Homozygous IN' => 'not homozygous in',
            'IN' => 'found in',
            'Homozygous IN' => 'homozygous in',
            'Heterozygous IN' => 'heterozygous in',
        ),
        'grouping_list' => array(
            'AND' => 'all of',
            'OR' => 'one or more of',
        ),
    );

    if (lovd_verifyInstance('mgha_seq') || lovd_verifyInstance('mgha_cpipe_lymphoma')) {
        $_SETT['var_effect'] = array(
            0 => 'Not curated',
            9 => 'Variants of strong clinical significance',
            7 => 'Variants of potential clinical significance',
            5 => 'VUS',
            3 => 'Likely benign',
            1 => 'Benign',
        );

        $_SETT['curation_status'] = array(
            CUR_STATUS_VARIANT_OF_INTEREST => 'Variant of Interest',
            CUR_STATUS_NOT_FOR_CURATION => 'Not for Curation',
            CUR_STATUS_ARTEFACT => 'Artefact',
            CUR_STATUS_LIKELY_ARTEFACT => 'Likely Artefact',
            CUR_STATUS_FOR_CURATION => 'For Curation',
            CUR_STATUS_REQUIRES_CONFIRMATION => 'Requires Confirmation',
            CUR_STATUS_CONFIRMED => 'Confirmed',
            CUR_STATUS_PROPOSED => 'Proposed Classification',
            CUR_STATUS_CURATED_REPORTABLE => 'Curated & Reportable',
            CUR_STATUS_CURATED_NOT_REPORTABLE => 'Curated & Not Reportable',
        );
    }

    // Diagnostics: Added one level, and changed the submitter level's name.
    unset($_SETT['user_levels'][LEVEL_SUBMITTER]); // To make space, we need to rename it anyway.
    $_SETT['user_levels'][LEVEL_ANALYZER]  = 'Analyzer';
    $_SETT['user_levels'][LEVEL_SUBMITTER] = 'Read-only';
}

// Before we have any output, initiate the template class which takes care of headers and such.
require ROOT_PATH . 'class/template.php';
$_T = new LOVD_Template();



// We define CONFIG_URI as the location of the config file.
define('CONFIG_URI', ROOT_PATH . 'config.ini.php');
$_INI = lovd_parseConfigFile(CONFIG_URI);



// Define table names (system-wide).
// WARNING: The order of tables *MUST* be the same as the order in which the
// tables are defined in the installer (meaning, respecting foreign keys),
// because uninstalling LOVD will use this table, reverse it, and try to remove
// the tables in the right order.
// FIXME: TABLE_SCR2GENE => TABLE_SCRS2GENES etc. etc.?
define('TABLEPREFIX', $_INI['database']['table_prefix']);
$_TABLES =
         array(
                'TABLE_COUNTRIES' => TABLEPREFIX . '_countries',
                'TABLE_USERS' => TABLEPREFIX . '_users',
                'TABLE_COLLEAGUES' => TABLEPREFIX . '_colleagues',
                'TABLE_CHROMOSOMES' => TABLEPREFIX . '_chromosomes',
                'TABLE_GENES' => TABLEPREFIX . '_genes',
                'TABLE_CURATES' => TABLEPREFIX . '_users2genes',
                'TABLE_TRANSCRIPTS' => TABLEPREFIX . '_transcripts',
                'TABLE_DISEASES' => TABLEPREFIX . '_diseases',
                'TABLE_GEN2DIS' => TABLEPREFIX . '_genes2diseases',
                'TABLE_DATA_STATUS' => TABLEPREFIX . '_data_status',
                'TABLE_ALLELES' => TABLEPREFIX . '_alleles',
                'TABLE_EFFECT' => TABLEPREFIX . '_variant_effect',
                'TABLE_INDIVIDUALS' => TABLEPREFIX . '_individuals',
                //'TABLE_INDIVIDUALS_REV' => TABLEPREFIX . '_individuals_revisions',
                'TABLE_IND2DIS' => TABLEPREFIX . '_individuals2diseases',
                'TABLE_VARIANTS' => TABLEPREFIX . '_variants',
                //'TABLE_VARIANTS_REV' => TABLEPREFIX . '_variants_revisions',
                'TABLE_VARIANTS_ON_TRANSCRIPTS' => TABLEPREFIX . '_variants_on_transcripts',
                //'TABLE_VARIANTS_ON_TRANSCRIPTS_REV' => TABLEPREFIX . '_variants_on_transcripts_revisions',
                'TABLE_PHENOTYPES' => TABLEPREFIX . '_phenotypes',
                //'TABLE_PHENOTYPES_REV' => TABLEPREFIX . '_phenotypes_revisions',
                'TABLE_SCREENINGS' => TABLEPREFIX . '_screenings',
                //'TABLE_SCREENINGS_REV' => TABLEPREFIX . '_screenings_revisions',
                'TABLE_SCR2GENE' => TABLEPREFIX . '_screenings2genes',
                'TABLE_SCR2VAR' => TABLEPREFIX . '_screenings2variants',
                'TABLE_COLS' => TABLEPREFIX . '_columns',
                'TABLE_ACTIVE_COLS' => TABLEPREFIX . '_active_columns',
                'TABLE_SHARED_COLS' => TABLEPREFIX . '_shared_columns',
                'TABLE_LINKS' => TABLEPREFIX . '_links',
                'TABLE_COLS2LINKS' => TABLEPREFIX . '_columns2links',
                'TABLE_CONFIG' => TABLEPREFIX . '_config',
                'TABLE_STATUS' => TABLEPREFIX . '_status',
                'TABLE_ANNOUNCEMENTS' => TABLEPREFIX . '_announcements',
                'TABLE_SOURCES' => TABLEPREFIX . '_external_sources',
                'TABLE_LOGS' => TABLEPREFIX . '_logs',
                'TABLE_MODULES' => TABLEPREFIX . '_modules',
                'TABLE_SCHEDULED_IMPORTS' => TABLEPREFIX . '_scheduled_imports',
              );

foreach ($_TABLES as $sConst => $sTable) {
    define($sConst, $sTable);
}

// Check PDO existence.
if (!class_exists('PDO')) {
    $sError = 'This PHP installation does not have PDO support installed, available from PHP 5. Without it, LOVD will not function.';
    if (substr(phpversion(), 0, 1) < 5) {
        $sError .= ' Please upgrade your PHP version to at least PHP 5.0.0 and install PDO support (built in from 5.1.0).';
    } else {
        $sError .= ' Please install PDO support (built in from 5.1.0).';
    }
    lovd_displayError('Init', $sError);

} else {
    // PDO available, check if we have the requested database driver.
    if (!in_array($_INI['database']['driver'], PDO::getAvailableDrivers())) {
        $sDriverName = $_INI['database']['driver']; // We used to be able to get to the formatted name, but no more.
        lovd_displayError('Init', 'This PHP installation does not have ' . $sDriverName . ' support for PDO installed. Without it, LOVD will not function. Please install ' . $sDriverName . ' support for PHP PDO.');
    }
}



// Initiate Database Connection.
require ROOT_PATH . 'class/PDO.php';
if ($_INI['database']['driver'] == 'mysql') {
    $_DB = new LOVD_PDO($_INI['database']['driver'], 'host=' . $_INI['database']['hostname'] . ';dbname=' . $_INI['database']['database'], $_INI['database']['username'], $_INI['database']['password']);
} elseif ($_INI['database']['driver'] == 'sqlite') {
    // SQLite.
    $_DB = new LOVD_PDO($_INI['database']['driver'], $_INI['database']['database']);
} else {
    // Can't happen.
    exit;
}



ini_set('default_charset','UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

// Help prevent cookie theft through JavaScript; XSS defensive line.
// See: http://nl.php.net/manual/en/session.configuration.php#ini.session.cookie-httponly
@ini_set('session.cookie_httponly', 1); // Available from 5.2.0.

// Read system-wide configuration from the database.
if ($_CONF = $_DB->query('SELECT * FROM ' . TABLE_CONFIG, false, false)) {
    // Must be two-step, since $_CONF can be false and therefore does not have ->fetchAssoc().
    $_CONF = $_CONF->fetchAssoc();
    if ($_CONF) {
        // See if the database is read-only at the moment, due to an announcement with the configuration.
        // Ignore errors, in case the table doesn't exist yet.
        $qAnnouncements = @$_DB->query('SELECT COUNT(*) FROM ' . TABLE_ANNOUNCEMENTS . ' WHERE start_date <= NOW() AND end_date >= NOW() AND lovd_read_only = 1', array(), false);
        $_CONF['lovd_read_only'] = ($qAnnouncements && $qAnnouncements->fetchColumn());
    }
}
if (!$_CONF) {
    // Basic configuration, in case we're not installed properly.
    define('MISSING_CONF', true);
    $_CONF =
         array(
                'system_title' => (LOVD_plus? 'Leiden Open Variation Database for diagnostics' : 'LOVD 3.0 - Leiden Open Variation Database'),
                'logo_uri' => 'gfx/LOVD' . (LOVD_plus? '_plus' : '3') . '_logo145x50.jpg',
                'lovd_read_only' => false,
              );
}

// Read LOVD status from the database.
if ($_STAT = $_DB->query('SELECT * FROM ' . TABLE_STATUS, false, false)) {
    // Must be two-step, since $_STAT can be false and therefore does not have ->fetchAssoc().
    $_STAT = $_STAT->fetchAssoc();
}
if (!$_STAT) {
    // Basic status, in case we're not installed properly.
    define('MISSING_STAT', true);
    $_STAT =
         array(
                'version' => $_SETT['system']['version'],
              );
}



if (defined('MISSING_CONF') || defined('MISSING_STAT') || !preg_match('/^([1-9]\.[0-9](\.[0-9])?)\-([0-9a-z-]{2,11})$/', $_STAT['version'], $aRegsVersion)) {
    // We couldn't get the installation's configuration or status. Are we properly installed, then?

    // Copying information that is required for the includes, but can't be read from the database.
    $_STAT['tree'] = $_SETT['system']['tree'];
    $_STAT['build'] = $_SETT['system']['build'];

    // Are we installed properly?
    $aTables = array();
    // We can't put TABLE_PREFIX in an argument, since SHOW ... can't be prepared by PDO in some PHP versions.
    // Generated errors on 5.1.6, works fine on 5.3.3.
    $q = $_DB->query('SHOW TABLES LIKE "' . TABLEPREFIX . '\_%"');
    while ($sCol = $q->fetchColumn()) {
        if (in_array($sCol, $_TABLES)) {
            $aTables[] = $sCol;
        }
    }
    if (count($aTables) < (count($_TABLES))) {
        // We're not completely installed.
        define('NOT_INSTALLED', true);
    }

    // inc-js-submit-settings.php check is necessary because it gets included in the install directory.
    if (dirname(lovd_getProjectFile()) != '/install' && lovd_getProjectFile() != '/inc-js-submit-settings.php') {
        // We're not installing, so throwing an error.

        if (defined('NOT_INSTALLED')) {
            // We're not completely installed.
            $_T->printHeader();
            print('      <BR>' . "\n" .
                  '      &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;LOVD was not installed yet. Please <A href="' . ROOT_PATH . 'install">install</A> LOVD first.<BR>' . "\n");
            $_T->printFooter();
            exit;

        } elseif (lovd_getProjectFile() != '/uninstall.php') {
            // Can't get the configuration for unknown reason. Bail out.
            lovd_displayError('Init', 'Error retrieving LOVD configuration or status information');
        }
    } // This should leave us alone if we're installing, even if we've got all tables, but are not quite done yet.

} else {
    // Store additional version information.
    list(, $_STAT['tree'],, $_STAT['build']) = $aRegsVersion;
}



// Force GPC magic quoting OFF.
if (get_magic_quotes_gpc()) {
    lovd_magicUnquoteAll();
}

// Use of SSL required?
// FIXME:
//// (SSL not required when exporting data to WikiProfessional because their scripts do not support it)
//// (The UCSC also has issues with retrieving the BED files through SSL...)
//if (!empty($_CONF['use_ssl']) && !SSL && !(lovd_getProjectFile() == '/export_data.php' && !empty($_GET['format']) && $_GET['format'] == 'wiki') && !(substr(lovd_getProjectFile(), 0, 9) == '/api/rest' && !empty($_GET['format']) && $_GET['format'] == 'text/bed')) {
if (!empty($_CONF['use_ssl']) && !SSL && !(lovd_getProjectFile() == '/api.php' && !empty($_GET['format']) && $_GET['format'] == 'text/bed')) {
    // We were enabled, when SSL was available. So I guess SSL is still available. If not, this line here would be a problem.
    // No, not sending any $_POST values either. Let's just assume no-one is working with LOVD when the ssl setting is activated.
    // FIXME; does not allow for nice URLs.
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}

// Session settings - use cookies.
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
if (ini_get('session.cookie_path') == '/') {
    // Don't share cookies with other systems - set the cookie path!
    ini_set('session.cookie_path', lovd_getInstallURL(false));
}
if (!empty($_STAT['signature'])) {
    // Set the session name to something unique, to prevent mixing cookies with other LOVDs on the same server.
    $_SETT['cookie_id'] = md5($_STAT['signature']);
} else {
    $_SETT['cookie_id'] = md5($_INI['database']['database'] . $_INI['database']['table_prefix']);
}
session_name('PHPSESSID_' . $_SETT['cookie_id']);

// Start sessions - use cookies.
@session_start(); // On some Ubuntu distributions this can cause a distribution-specific error message when session cleanup is triggered.
// @ is to suppress errors in Travis test.
@header('X-LOVD-version: ' . $_SETT['system']['version'] . (empty($_STAT['version']) || $_STAT['version'] == $_SETT['system']['version']? '' : ' (DB @ ' . $_STAT['version'] . ')'));



// The following applies only if the system is fully installed.
if (!defined('NOT_INSTALLED')) {
    // Load session data.
    require ROOT_PATH . 'inc-auth.php';

    // If we're read only, log user out when lower than Manager.
    if ($_AUTH && $_CONF['lovd_read_only'] && $_AUTH['level'] < LEVEL_MANAGER) {
        // We won't let the user know, because this usually only happens when a
        //  new message has appeared that sets the installation to read-only.
        // So let's hope the new message on the screen attracks attention and
        //  that it speaks for itself.
        // (In principle, it can also happen when an existing message is edited
        //   to lock the installation.)
        $_AUTH = false;
    }

    // Define $_PE ($_PATH_ELEMENTS) and CURRENT_PATH.
    // FIXME: Running lovd_cleanDirName() on the entire URI causes it to run also on the arguments.
    //  If there are arguments with ../ in there, this will take effect and arguments or even the path itself is eaten.
    $sPath = preg_replace('/^' . preg_quote(lovd_getInstallURL(false), '/') . '/', '', lovd_cleanDirName(rawurldecode($_SERVER['REQUEST_URI']))); // 'login' or 'genes?create' or 'users/00001?edit'
    $sPath = strip_tags($sPath); // XSS tag removal on entire string (and no longer on individual parts).
    $aPath = explode('?', $sPath); // Cut off the Query string, that will be handled later.
    $_PE = explode('/', rtrim($aPath[0], '/')); // array('login') or array('genes') or array('users', '00001')

    if (isset($_SETT['objectid_length'][$_PE[0]]) && isset($_PE[1]) && ctype_digit($_PE[1])) {
        $_PE[1] = sprintf('%0' . $_SETT['objectid_length'][$_PE[0]] . 'd', $_PE[1]);
    }
    define('CURRENT_PATH', implode('/', $_PE));
    define('PATH_COUNT', count($_PE)); // So you don't need !empty($_PE[1]) && ...

    // Define ACTION.
    if ($_SERVER['QUERY_STRING'] && preg_match('/^([\w-]+)(&.*)?$/', $_SERVER['QUERY_STRING'], $aRegs)) {
        define('ACTION', $aRegs[1]);
    } else {
        define('ACTION', false);
    }

    // STUB; This should be implemented properly later on.
    define('OFFLINE_MODE', false);

    // Define constant for request method.
    define($_SERVER['REQUEST_METHOD'], true);
    @define('GET', false);
    @define('POST', false);
    @define('PUT', false);
    @define('DELETE', false);

    // We really don't need any of this, if we're loaded by the update picture.
    // FIXME; double check all of this block.
    if (!in_array(lovd_getProjectFile(), array('/check_update.php', '/logout.php'))) {
        // Force user to change password.
        if ($_AUTH && $_AUTH['password_force_change'] && !(lovd_getProjectFile() == '/users.php' && in_array(ACTION, array('edit', 'change_password')) && $_PE[1] == $_AUTH['id'])) {
            header('Location: ' . lovd_getInstallURL() . 'users/' . $_AUTH['id'] . '?change_password');
            exit;
        }

        // Load DB admin data; needed by sending messages.
        if ($_AUTH && $_AUTH['level'] == LEVEL_ADMIN) {
            // Saves me quering the database!
            $_SETT['admin'] = array('name' => $_AUTH['name'], 'email' => $_AUTH['email']);
        } else {
            $_SETT['admin'] = array('name' => '', 'email' => ''); // We must define the keys first, or the order of the keys will not be correct.
            list($_SETT['admin']['name'], $_SETT['admin']['email']) = $_DB->query('SELECT name, email FROM ' . TABLE_USERS . ' WHERE level = ? AND id > 0 ORDER BY id ASC', array(LEVEL_ADMIN))->fetchRow();
            // Add a cleaned email address, because perhaps the admin has multiple addresses.
            $_SETT['admin']['address_formatted'] = $_SETT['admin']['name'] . ' <' . str_replace(array("\r\n", "\r", "\n"), '>, <', trim($_SETT['admin']['email'])) . '>';
        }

        // Switch gene.
        // Gene switch will occur automatically at certain pages. They can be accessed by following links in LOVD itself, or possibly from outer sources.
        if (preg_match('/^(configuration|genes|transcripts|variants|individuals|screenings|view)\/([^\/]+)/', CURRENT_PATH, $aRegs)) {
            // We'll check this value further down in this code.
            if (!in_array($aRegs[2], array('in_gene', 'upload')) && !ctype_digit($aRegs[2])) {
                $_SESSION['currdb'] = $aRegs[2]; // Not checking capitalization here yet.
            }
        }

        // Simply so that we can build somewhat correct email headers.
        if (empty($_CONF['institute'])) {
            $_CONF['institute'] = $_SERVER['HTTP_HOST'];
        }
        if (empty($_CONF['email_address'])) {
            $_CONF['email_address'] = 'noreply@' . (substr($_SERVER['HTTP_HOST'], 0, 4) == 'www.'? substr($_SERVER['HTTP_HOST'], 4) : $_SERVER['HTTP_HOST']);
        }

        // Set email headers.
        $_SETT['email_mime_boundary'] = md5('PHP_MIME');
        $_SETT['email_headers'] = 'MIME-Version: 1.0' . PHP_EOL .
                                  'Content-Type: text/plain; charset=UTF-8' . PHP_EOL .
                                  'X-Priority: 3' . PHP_EOL .
                                  'X-Mailer: PHP/' . phpversion() . PHP_EOL .
                                  'From: ' . (ON_WINDOWS? '' : '"LOVD (' . lovd_shortenString($_CONF['system_title'], 50) . ')" ') . '<' . $_CONF['email_address'] . '>';
        $_SETT['email_mime_headers'] =
             preg_replace('/^Content-Type.+$/m',
                          'Content-Type: multipart/mixed; boundary="' . $_SETT['email_mime_boundary'] . '"' . PHP_EOL .
                          'Content-Transfer-Encoding: 7bit', $_SETT['email_headers']);
    }





    if (!in_array(lovd_getProjectFile(), array('/check_update.php'))) {
        // Load gene data.
        if (!LOVD_plus && !empty($_SESSION['currdb'])) {
            $_SETT['currdb'] = @$_DB->query('SELECT * FROM ' . TABLE_GENES . ' WHERE id = ?', array($_SESSION['currdb']))->fetchAssoc();
            if (!$_SETT['currdb']) {
                $_SESSION['currdb'] = false;
            } else {
                // Replace with what we have in the database, so we won't run into issues on other pages when CurrDB is used for navigation to other tabs.
                $_SESSION['currdb'] = $_SETT['currdb']['id'];
            }
        } else {
            $_SESSION['currdb'] = false;
        }
    }

/*
    // Load LOVD modules!
    require ROOT_PATH . 'class/modules.php';
    $_MODULES = new Modules;
*/
} else {
    define('ACTION', false);
}
?>
