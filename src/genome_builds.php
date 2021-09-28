<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2021-09-21
 * Modified    : 2021-09-27
 * For LOVD    : 3.5-pre-02
 *
 * Copyright   : 2004-2021 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               L. Werkman <L.Werkman@LUMC.nl>
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

const ROOT_PATH = './';
const TAB_SELECTED = 'setup';
require ROOT_PATH . 'inc-init.php';

if ($_AUTH) {
    // If authorized, check for updates.
    require ROOT_PATH . 'inc-upgrade.php';
}

lovd_requireAUTH(LEVEL_MANAGER);





if (PATH_COUNT == 1 && !ACTION) {
    // URL: /genome_builds
    // View all genome builds.

    define('PAGE_TITLE', 'Genome builds');

    require ROOT_PATH . 'class/object_genome_builds.php';

    $_T->printHeader();
    $_T->printTitle();

    $_DATA = new LOVD_GenomeBuild();
    $_DATA->viewList('GenomeBuilds', array());

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 1 && ACTION == 'add') {
    // URL: /genome_builds?add
    // Enable a new genome builds.

    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    define('LOG_EVENT', 'GenomeBuildAdd');

    require ROOT_PATH . 'inc-lib-form.php';
    require ROOT_PATH . 'class/object_genome_builds.php';
    $_DATA = new LOVD_GenomeBuild();

    // Find out which genome builds are, and which organism is, currently active.
    $aActiveBuilds = $_DB->query('SELECT id FROM ' . TABLE_GENOME_BUILDS)->fetchAllColumn();
    $sPrefix = substr($aActiveBuilds[0], 0, 2);

    // Prepare array for the form.
    $aAddableGenomeBuilds = array();
    foreach ($_SETT['human_builds'] as $sBuild => $aBuild) {
        if (substr($sBuild, 0, 2) == $sPrefix &&  !in_array($sBuild, $aActiveBuilds)) {
            $aAddableGenomeBuilds[$sBuild] = $sBuild . ' / ' . $aBuild['ncbi_name'];
        }
    };

    // Apply the choices as filled into the form.
    if (POST) {
        lovd_errorClean();

        if (empty($_POST['id'])) {
            // Raise error if ID is empty or not addable.
            lovd_errorAdd('id', 'Please select a genome build.');
        }
        elseif (in_array($_POST['id'], $aAddableGenomeBuilds)) {
            // TODO: Why is this the other way around?
            // Raise error if ID is not addable.
        lovd_errorAdd('id', 'Please select an addable genome build.');
        }

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('id', 'name', 'column_suffix', 'created_by', 'created_date');

            // Prepare values.
            $_POST['name'] = $_POST['id'] . ' / ' . $_SETT['human_builds'][$_POST['id']]['ncbi_name'];
            $_POST['column_suffix'] = $_POST['id'];
            $_POST['created_by'] = $_AUTH['id'];
            $_POST['created_date'] = date('Y-m-d H:i:s');

            // The new genome build will be inserted into the genome build table AFTER inserting the necessary columns
            // into the VOG and transcripts table, to avoid fatal errors when not all actions can be completed.

            // Register the new DNA column for the VOG table (TABLE_COLS and TABLE_ACTIVE_COLS).
            $aQueries = array_map(function ($s) {
                return str_replace(
                    'VariantOnGenome/DNA',
                    'VariantOnGenome/DNA/' . $_POST['column_suffix'],
                    $s
                );
            }, array_slice(lovd_getActivateCustomColumnQuery(array('VariantOnGenome/DNA')), 0, 2));

            foreach($aQueries as $sSQL) {
                $_DB->query($sSQL);
            }

            // Prepare array to easily add necessary columns into VOG and transcripts table.
            $aActiveColumnsVariants = $_DB->query('
                SELECT COLUMN_NAME
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = "' . TABLE_VARIANTS . '"
                  AND COLUMN_NAME IN (?,?,?)',
                array(
                    'VariantOnGenome/DNA/' . $_POST['column_suffix'],
                    'position_g_start_' . $_POST['column_suffix'],
                    'position_g_end_' . $_POST['column_suffix']
                ))->fetchAllColumn();
            $aActiveColumnsTranscripts = $_DB->query('
                SELECT COLUMN_NAME
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = "' . TABLE_TRANSCRIPTS . '"
                  AND COLUMN_NAME IN (?,?)',
                array(
                    'position_g_mrna_start_' . $_POST['column_suffix'],
                    'position_g_mrna_end_' . $_POST['column_suffix']))->fetchAllColumn();

            $aTablesAndTheirColumns = array(
                TABLE_VARIANTS => array(
                    '`VariantOnGenome/DNA/' . $_POST['column_suffix'] . '`' => array(
                        'type' => 'VARCHAR(255)',
                        'active' => in_array(
                            'VariantOnGenome/DNA/' . $_POST['column_suffix'],
                            $aActiveColumnsVariants)
                    ),
                    'position_g_start_' . $_POST['column_suffix'] => array(
                        'type' => 'INT(10) UNSIGNED AFTER position_g_end',
                        'active' => in_array('position_g_start_' . $_POST['column_suffix'], $aActiveColumnsVariants),
                    ),
                    'position_g_end_' . $_POST['column_suffix'] => array(
                        'type' => 'INT(10) UNSIGNED AFTER position_g_start_' . $_POST['column_suffix'],
                        'active' => in_array('position_g_end_' . $_POST['column_suffix'], $aActiveColumnsVariants)
                    ),
                ),
                TABLE_TRANSCRIPTS => array(
                    // TODO: Ask Ivo whether or not the mrna positions should be NOT NULL
                    'position_g_mrna_start_' . $_POST['column_suffix'] => array(
                        'type' => 'INT(10) UNSIGNED AFTER position_g_mrna_start',
                        'active' => in_array('position_g_mrna_start_' . $_POST['column_suffix'], $aActiveColumnsTranscripts)
                    ),
                    'position_g_mrna_end_' . $_POST['column_suffix'] => array(
                        'type' => 'INT(10) UNSIGNED AFTER position_g_mrna_end',
                        'active' => in_array('position_g_mrna_end_' . $_POST['column_suffix'], $aActiveColumnsTranscripts)
                    ),
                ),
            );

            // Add columns to VOG and Transcripts tables.
            foreach (array_keys($aTablesAndTheirColumns) as $sTable) {
                $bToAdd = False;
                $sSQL = 'ALTER TABLE ' . $sTable;

                foreach (array_keys($aTablesAndTheirColumns[$sTable]) as $sColumn) {
                    $aColumn = $aTablesAndTheirColumns[$sTable][$sColumn];
                    if (!$aColumn['active']) {
                        $bToAdd = True;
                        $sSQL .= ' ADD COLUMN ' . $sColumn . ' ' . $aColumn['type'] . ',';
                    }
                }

                if ($bToAdd) {
                    $_DB->query(rtrim($sSQL, ','));
                }
            }

            // The new row is inserted into the genome build table AFTER inserting the necessary columns
            // into the variants and transcripts table, to avoid fatal errors when not all actions can be completed.

            // Add new genome build as new row into GenomeBuilds table
            $_DATA->insertEntry($_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Added new Genome Build ' . $_POST['id']);

            // Thank the user, and send them to the page of the new GB.
            header('Refresh: 5; url=' . lovd_getInstallURL(). CURRENT_PATH . '/' . $_POST['id']);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully added the new genome build!', 'success');

            $_T->printFooter();
            exit;
        }
    }

    $_T->printHeader();
    $_T->printTitle();

    // Check to see if there are any inactive yet available genome builds left.
    if (!$aAddableGenomeBuilds) {
        print('There is nothing to add. All available reference genomes of your organism are loaded into the database.');

        $_T->printFooter();
        exit;

    } else {
        // Only show the form when there are still genome builds inactive yet available.
        if (GET) {
            print('Please select the genome build you want to add to your database.<BR><BR>');
        }
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    $aForm =
        array(
            array('POST', '', '', '', '0%', '14', '100%'),
            array('', '', 'select', 'id', count($aAddableGenomeBuilds), $aAddableGenomeBuilds, false, false, false),
            'skip',
            array('', '', 'submit', 'Add selected genome build'),
        );

    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");
    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && !ACTION) {
    // URL: /genome_builds/hg19
    // View specific genome build.

    define('PAGE_TITLE', lovd_getCurrentPageTitle());

    $sID = lovd_getCurrentID();
    $_T->printHeader();
    $_T->printTitle();

    require ROOT_PATH . 'class/object_genome_builds.php';
    $_DATA = new LOVD_GenomeBuild();
    $zData = $_DATA->viewEntry($sID);

    $_T->printFooter();
    exit;
}
?>
