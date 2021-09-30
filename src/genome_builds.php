<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2021-09-21
 * Modified    : 2021-09-30
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
    // Enable a new genome build.

    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    define('LOG_EVENT', 'GenomeBuildAdd');

    require ROOT_PATH . 'inc-lib-form.php';
    require ROOT_PATH . 'class/object_genome_builds.php';
    $_DATA = new LOVD_GenomeBuild();

    // Find out which genome builds are, and which organism is, currently active.
    $aActiveBuilds = $_DB->query('SELECT id FROM ' . TABLE_GENOME_BUILDS)->fetchAllColumn();
    // Isolate the organism's prefix (e.g., "hg"); adding other organisms isn't allowed.
    $sPrefix = substr($aActiveBuilds[0], 0, 2);

    // Prepare array for the form.
    $aAddableGenomeBuilds = array();
    foreach ($_SETT['human_builds'] as $sBuild => $aBuild) {
        if (substr($sBuild, 0, 2) == $sPrefix && !in_array($sBuild, $aActiveBuilds)) {
            // Same organism, and the build hasn't been added yet.
            $aAddableGenomeBuilds[$sBuild] = $sBuild . ' / ' . $aBuild['ncbi_name'];
        }
    };

    // Check the input from the form, then apply the changes.
    if (POST) {
        lovd_errorClean();

        if (empty($_POST['id'])) {
            // Raise error if ID is empty (not given).
            lovd_errorAdd('id', 'Please select a genome build.');
        } elseif (!isset($aAddableGenomeBuilds[$_POST['id']])) {
            // Raise error if ID can not be added or is unknown.
            lovd_errorAdd('id', 'Please select a valid genome build.');
        }

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('id', 'name', 'column_suffix', 'created_by', 'created_date');

            // Prepare values.
            $_POST['name'] = $_POST['id'] . ' / ' . $_SETT['human_builds'][$_POST['id']]['ncbi_name'];
            $_POST['column_suffix'] = $_POST['id'];
            $_POST['created_by'] = $_AUTH['id'];
            $_POST['created_date'] = date('Y-m-d H:i:s');

            // The new genome build will be inserted into the genome build table
            //  only AFTER adding the necessary columns into the VOG and
            //  transcripts tables, to avoid leaving the system in a broken
            //  state if any of the below queries fail.

            // Register the new DNA column for the VOG table (TABLE_COLS and TABLE_ACTIVE_COLS).
            // We're using array_slice() here because we only need the first two
            //  queries; the inserts. The alter table calls, we'll do ourselves.
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

            // Prepare an array to more easily add the required columns into the
            //  VOG and transcripts tables.
            $aTablesAndTheirColumns = array(
                TABLE_VARIANTS => array(
                    'VariantOnGenome/DNA/' . $_POST['column_suffix'] => 'VARCHAR(255)',
                    'position_g_start_' . $_POST['column_suffix'] => 'INT(10) UNSIGNED AFTER position_g_end',
                    'position_g_end_' . $_POST['column_suffix'] => 'INT(10) UNSIGNED AFTER position_g_start_' . $_POST['column_suffix'],
                ),
                TABLE_TRANSCRIPTS => array(
                    'position_g_mrna_start_' . $_POST['column_suffix'] => 'INT(10) UNSIGNED AFTER position_g_mrna_end',
                    'position_g_mrna_end_' . $_POST['column_suffix'] => 'INT(10) UNSIGNED AFTER position_g_mrna_start_' . $_POST['column_suffix'],
                ),
            );

            // Add the columns to the VOG and Transcripts tables.
            foreach ($aTablesAndTheirColumns as $sTable => $aTable) {
                $aActiveColumns = $_DB->query('
                    SELECT COLUMN_NAME
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = ?
                      AND COLUMN_NAME IN (' . implode(',', array_fill(0, count($aTable), '?')) . ')',
                    array_merge(array($sTable), array_keys($aTable)))->fetchAllColumn();

                $bToAdd = false;
                $sSQL = 'ALTER TABLE ' . $sTable;

                foreach ($aTable as $sColumn => $sColumnSQL) {
                    if (!in_array($sColumn, $aActiveColumns)) {
                        $bToAdd = true;
                        $sSQL .= ' ADD COLUMN `' . $sColumn . '` ' . $sColumnSQL . ',';
                    }
                }

                if ($bToAdd) {
                    $_DB->query(rtrim($sSQL, ','));
                }
            }

            // Now that all ALTER TABLE calls are done successfully, we're safe
            //  to insert the new genome build into its table.
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
    // Only show the form when there are still genome builds inactive yet available.
    if (!$aAddableGenomeBuilds) {
        lovd_showInfoTable('There is nothing to add. All reference genomes of your organism that are known to the system are already enabled.');
        $_T->printFooter();
        exit;

    } elseif (GET) {
        print('Please select the genome build that you want to enable in the system.<BR>' .
              'Once enabled, the new genome build can be used for database submissions and data queries.<BR><BR>' . "\n\n");
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    $aForm =
        array(
            array('POST', '', '', '', '25%', '14', '75%'),
            array('Genome build to add', '', 'select', 'id', count($aAddableGenomeBuilds), $aAddableGenomeBuilds, false, false, false),
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

    // Show the option to deactivate the genome build if there are more than one active.
    $aAmountOfActiveGBs = count($_DB->query('SELECT id FROM ' . TABLE_GENOME_BUILDS)->fetchAllColumn());

    if ($aAmountOfActiveGBs > 1) {
        $aNavigation = array(
            CURRENT_PATH . '?remove' => array('cross.png', 'Deactivate this genome build', 1),
        );
        lovd_showJGNavigation($aNavigation, 'genome_builds');
    }

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ACTION == 'remove') {
    // URL: /genome_builds/hg19?remove
    // Deactivate one of the active genome builds.

    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    define('LOG_EVENT', 'GenomeBuildRemove');

    require ROOT_PATH . 'inc-lib-form.php';
    require ROOT_PATH . 'class/object_genome_builds.php';


    // Perform initial checks.
    $sReason = '';
    $aActiveBuilds = $_DB->query('SELECT id, column_suffix FROM ' . TABLE_GENOME_BUILDS)->fetchAllCombine();
    $sID = lovd_getCurrentID();

    if (count($aActiveBuilds) < 2) {
        // Check to make sure there would be a genome build left after the removal.
        $sReason = 'there must be at least one reference genome left after the removal.';

    } elseif (!in_array($sID, $aActiveBuilds)) {
        // Check whether the given ID is one of the active IDs in the database.
        $sReason = 'an invalid genome build was given.';

    } else {
        // Check to make sure that all variants are safely stored on the
        //  genome builds that will remain active.
        $sSQL = 'SELECT COUNT(id) FROM ' . TABLE_VARIANTS . ' WHERE 1 = 1';

        foreach(array_diff_key($aActiveBuilds, array($sID => '_')) as $sBuild => $sColumnSuffix) {
            $sColumnSuffix = ($sColumnSuffix) ? '/' . $sColumnSuffix : '';
            $sSQL .= ' AND (`VariantOnGenome/DNA' . $sColumnSuffix . '` IS NULL OR
                            `VariantOnGenome/DNA' . $sColumnSuffix . '` = "")';
        }

        $bVariantsLostAfterRemovingGenomeBuild = (bool) $_DB->query($sSQL)->fetchColumn();

        if ($bVariantsLostAfterRemovingGenomeBuild) {
            $sReason = 'not all variants that are mapped on this reference genome are safely mapped on a second build.';
        }
    }

    // Throw error if any was found.
    if ($sReason) {
        lovd_showInfoTable('The genome build cannot be deactivated, since ' . $sReason, 'warning');
        $_T->printFooter();
        exit;
    }


    if (POST) {
        lovd_errorClean();

        // Check whether user has submitted and confirmed the form/action.
        $bValidPassword = false;

        if (empty($_POST['password'])) {
            lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');

        } elseif (!lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');

        } else {
            $bValidPassword = true;
        }

        // Remove password from default values shown in confirmation form.
        unset($_POST['password']);

        // Accept and realise removal of the genome build after passing the checks.
        if ($bValidPassword) {

            // Remove genome build from database.
            $_DB->query('DELETE FROM ' . TABLE_GENOME_BUILDS .
                        ' WHERE id = ?', array($sID));

            // Prepare a slash and underscore only if needed.
            // The default genome build does not have a column suffix, so
            //  in this case we also do not want a slash and/or underscore.
            $sSlash = ($aActiveBuilds[$sID]) ? '/' : '';
            $sUnderscore = ($aActiveBuilds[$sID]) ? '_' : '';

            // Prepare an array to more easily remove the columns from the
            //  VOG and transcripts tables.
            $aTablesAndTheirColumns = array(
                TABLE_VARIANTS => array(
                    'VariantOnGenome/DNA' . $sSlash . $aActiveBuilds[$sID],
                    'position_g_start' . $sUnderscore . $aActiveBuilds[$sID],
                    'position_g_end' . $sUnderscore . $aActiveBuilds[$sID],
                ),
                TABLE_TRANSCRIPTS => array(
                    'position_g_mrna_start' . $sUnderscore . $aActiveBuilds[$sID],
                    'position_g_mrna_end' . $sUnderscore . $aActiveBuilds[$sID],
                ),
            );

            // Remove the columns to the VOG and Transcripts tables.
            foreach ($aTablesAndTheirColumns as $sTable => $aTable) {
                $aActiveColumns = $_DB->query('
                    SELECT COLUMN_NAME
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = ?
                      AND COLUMN_NAME IN (' . implode(',', array_fill(0, count($aTable), '?')) . ')',
                    array_merge(array($sTable), $aTable))->fetchAllColumn();

                $bToRemove = false;
                $sSQL = 'ALTER TABLE ' . $sTable;

                foreach ($aTable as $sColumn) {
                    if (in_array($sColumn, $aActiveColumns)) {
                        $bToRemove = true;
                        $sSQL .= ' DROP COLUMN `' . $sColumn . '`,';
                    }
                }

                if ($bToRemove) {
                    $_DB->query(rtrim($sSQL, ','));
                }
            }

            // Deactivate custom DNA column.
            // TODO: Colsuffix / dependant
            $_DB->query('DELETE FROM ' . TABLE_ACTIVE_COLS .
                ' WHERE colid = "VariantOnGenome/DNA' . $sSlash . $aActiveBuilds[$sID] . '"');


            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Removed Genome Build ' . $sID);

            // Thank the user, and send them to the page of the currently active GBs.
            header('Refresh: 5; url=' . lovd_getInstallURL() . 'genome_builds');

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully removed the chosen genome build!', 'success');
            $_T->printFooter();
            exit;
        }
    }



    $_T->printHeader();
    $_T->printTitle();

    $sName = $sID . ' / ' . $_SETT['human_builds'][$sID]['ncbi_name'];

    lovd_showInfoTable('This will deactivate genome build ' . $sName . ' from your database.', 'warning');

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    $aForm = array(
        array('POST', '', '', '', '45%', '14', '55%'),
        array('Genome build to deactivate', '', 'print', $sName),
        'skip',
        array('Enter your password for authorization', '', 'password', 'password', 20),
        array('', '', 'submit', 'Remove genome build'),
    );
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");
    $_T->printFooter();
    exit;
}

?>
