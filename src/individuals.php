<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-02-16
 * Modified    : 2023-06-28
 * For LOVD    : 3.0-30
 *
 * Copyright   : 2004-2023 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

define('ROOT_PATH', './');
require ROOT_PATH . 'inc-init.php';

if ($_AUTH) {
    // If authorized, check for updates.
    require ROOT_PATH . 'inc-upgrade.php';
}





if ((PATH_COUNT == 1 || (!empty($_PE[1]) && !ctype_digit($_PE[1]))) && !ACTION) {
    // URL: /individuals
    // URL: /individuals/DMD
    // View all entries.

    if (!empty($_PE[1])) {
        $sGene = $_DB->q('SELECT id FROM ' . TABLE_GENES . ' WHERE id = ?', array($_PE[1]))->fetchColumn();
        if ($sGene) {
            lovd_isAuthorized('gene', $sGene); // To show non-public entries.
            $_GET['search_genes_searched'] = '="' . $sGene . '"';
        } else {
            // Command or gene not understood.
            // FIXME; perhaps a HTTP/1.0 501 Not Implemented? If so, provide proper output (gene not found) and
            //   test if browsers show that output or their own error page. Also, then, use the same method at
            //   the bottom of all files, as a last resort if command/URL is not understood. Do all of this LATER.
            exit;
        }
    }

    // Managers and authorized curators are allowed to download this list...
    if ($_AUTH && $_AUTH['level'] >= LEVEL_CURATOR) {
        define('FORMAT_ALLOW_TEXTPLAIN', true);
    }

    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    $_T->printHeader();
    $_T->printTitle();

    $aColsToHide = array('panelid', 'diseaseids');
    if (isset($sGene)) {
        $aColsToHide[] = 'genes_screened_';
        $aColsToHide[] = 'variants_in_genes_';
    }

    require ROOT_PATH . 'class/object_individuals.php';
    $_DATA = new LOVD_Individual();
    $aVLOptions = array(
        'cols_to_skip' => $aColsToHide,
        'show_options' => ($_AUTH && $_AUTH['level'] >= LEVEL_CURATOR),
        'find_and_replace' => true,
        'curate_set' => true,
        'merge_set' => true,
    );
    $_DATA->viewList('Individuals', $aVLOptions);

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && !ACTION) {
    // URL: /individuals/00000001
    // View specific entry.

    $nID = lovd_getCurrentID();
    define('PAGE_TITLE', lovd_getCurrentPageTitle());

    // Before we start the template here, do a quick check if this entry exists.
    // This is non-standard, we normally rely on viewEntry() to sort that out.
    // But this individual may have been merged, after sending the submission
    //  confirmation email. Check the logs if that is the case, and forward.
    if (!$_DB->q('SELECT COUNT(*) FROM ' . TABLE_INDIVIDUALS . ' WHERE id = ?',
            array($nID))->fetchColumn()) {
        // Entry doesn't exist. Can we find a merge log entry?
        $nNewID = $_DB->q('
            SELECT RIGHT(log, ' . $_SETT['objectid_length'][$_PE[0]] . ')
            FROM ' . TABLE_LOGS . '
            WHERE name = ? AND event = ? AND log LIKE ?',
                array('Event', 'MergeEntries', 'Merged individual entry #' . $nID . ' into %'))->fetchColumn();
        if ($nNewID) {
            // HTTP 301 Moved Permanently.
            header('Location: ' . lovd_getInstallURL() . $_PE[0] . '/' . $nNewID . '?&redirected_after_merge', true, 301);
            exit;
        }
    }

    $_T->printHeader();
    $_T->printTitle();

    if (isset($_GET['redirected_after_merge'])) {
        // We got redirected after using an ID that got merged into this entry.
        lovd_showInfoTable(
            'Please note that you got redirected after using an Individual ID that no longer exists,' .
            ' because that entry got merged into this entry.', 'warning');
    }

    // Load appropriate user level for this individual.
    lovd_isAuthorized('individual', $nID);

    require ROOT_PATH . 'class/object_individuals.php';
    $_DATA = new LOVD_Individual($nID);

    // Added the DIV to allow us reloading the VE using JS.
    print('      <DIV id="viewentryDiv">' . "\n");
    $zData = $_DATA->viewEntry($nID);
    print('      </DIV>' . "\n\n");

    $aNavigation = array();
    if ($_AUTH && $_AUTH['level'] >= LEVEL_OWNER) {
        $aNavigation[CURRENT_PATH . '?edit']                     = array('menu_edit.png', 'Edit individual entry', 1);
        if ($_AUTH['level'] >= LEVEL_CURATOR) {
            if ($zData['statusid'] < STATUS_OK) {
                $aNavigation[CURRENT_PATH . '?publish']          = array('check.png', ($zData['statusid'] == STATUS_MARKED? 'Remove mark from' : 'Publish (curate)') . ' individual entry', 1);
            }
            $aNavigation['javascript:$.get(\'ajax/curate_set.php?bySubmission&id=' . $nID . '\').fail(function(){alert(\'Request failed. Please try again.\');});'] = array('check.png', 'Publish (curate) entire submission', 1);
        }
        // You can only add phenotype information to this individual, when there are phenotype columns enabled.
        if ($_DB->q('SELECT COUNT(*) FROM ' . TABLE_IND2DIS . ' AS i2d INNER JOIN ' . TABLE_SHARED_COLS . ' AS sc USING(diseaseid) WHERE i2d.individualid = ?', array($nID))->fetchColumn()) {
            $aNavigation['phenotypes?create&amp;target=' . $nID] = array('menu_plus.png', 'Add phenotype information to individual', 1);
        }
        $aNavigation['screenings?create&amp;target=' . $nID]     = array('menu_plus.png', 'Add screening to individual', 1);
        $aNavigation['download/all/submission/' . $nID]          = array('menu_save.png', 'Download submission in LOVD3 format', 1);
        if ($_AUTH['level'] >= $_SETT['user_level_settings']['delete_individual']) {
            $aNavigation[CURRENT_PATH . '?delete']               = array('cross.png', 'Delete individual entry', 1);
        }
    }
    lovd_showJGNavigation($aNavigation, 'Individuals');

    print('<BR><BR>' . "\n\n");


    if (!empty($zData['phenotypes'])) {
        // List of phenotype entries associated with this person, per disease.
        $_GET['search_individualid'] = $nID;
        $_T->printTitle('Phenotypes', 'H4');
        // Repeat searching for diseases, since this individual might have a
        //  phenotype entry for a disease they don't have.
        $zData['diseases'] = $_DB->q('SELECT id, symbol, name FROM ' . TABLE_DISEASES . ' WHERE id IN (?' . str_repeat(', ?', count($zData['phenotypes'])-1) . ')', $zData['phenotypes'])->fetchAllRow();
        require ROOT_PATH . 'class/object_phenotypes.php';
        foreach ($zData['diseases'] as $aDisease) {
            list($nDiseaseID, $sSymbol, $sName) = $aDisease;
            if (in_array($nDiseaseID, $zData['phenotypes'])) {
                $_GET['search_diseaseid'] = $nDiseaseID;
                $_DATA = new LOVD_Phenotype($nDiseaseID);
                print('<B>' . $sName . ' (<A href="diseases/' . $nDiseaseID . '">' . $sSymbol . '</A>)</B>&nbsp;&nbsp;<A href="phenotypes?create&amp;target=' . $nID . '&amp;diseaseid=' . $nDiseaseID . '"><IMG src="gfx/plus.png"></A> Add phenotype for this disease');
                $aVLOptions = array(
                    'cols_to_skip' => array('phenotypeid', 'individualid', 'diseaseid'),
                    'track_history' => false,
                    'show_navigation' => false,
                );
                $_DATA->viewList('Phenotypes_for_I_VE_' . $nDiseaseID, $aVLOptions);
            }
        }
        unset($_GET['search_individualid']);
        unset($_GET['search_diseaseid']);
    } else {
        lovd_showInfoTable('No phenotypes found for this individual!', 'stop');
    }

    if (count($zData['screeningids'])) {
        $_GET['search_individualid'] = $nID;
        print('<BR><BR>' . "\n\n");
        $_T->printTitle('Screenings', 'H4');
        require ROOT_PATH . 'class/object_screenings.php';
        $_DATA = new LOVD_Screening();
        $_DATA->setSortDefault('id');
        $aScreeningVLOptions = array(
            'cols_to_skip' => array('screeningid', 'individualid', 'created_date', 'edited_date'),
            'track_history' => false,
            'show_navigation' => false,
            'show_options' => ($_AUTH && $_AUTH['level'] >= LEVEL_CURATOR),
            'merge_set' => true,
        );
        // This ViewList ID is checked in ajax/viewlist.php. Don't just change it.
        $_DATA->viewList('Screenings_for_I_VE', $aScreeningVLOptions);
        unset($_GET['search_individualid']);

        $_GET['search_screeningid'] = implode('|', $zData['screeningids']);
        print('<BR><BR>' . "\n\n");
        $_T->printTitle('Variants', 'H4');

        require ROOT_PATH . 'class/object_custom_viewlists.php';
        // VOG needs to be first, so it groups by the VOG ID.
        $_DATA = new LOVD_CustomViewList(array('VariantOnGenome', 'Scr2Var', 'VariantOnTranscript'));
        $aVariantVLOptions = array(
            'show_options' => ($_AUTH && $_AUTH['level'] >= LEVEL_CURATOR),
        );
        $_DATA->viewList('CustomVL_VOT_for_I_VE', $aVariantVLOptions);
    }

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 1 && ACTION == 'create') {
    // URL: /individuals?create
    // Create a new entry.

    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    define('LOG_EVENT', 'IndividualCreate');

    if ($_AUTH) {
        lovd_isAuthorized('gene', $_AUTH['curates']);
    }
    lovd_requireAUTH($_SETT['user_level_settings']['submit_new_data']);

    require ROOT_PATH . 'class/object_individuals.php';
    $_DATA = new LOVD_Individual();
    require ROOT_PATH . 'inc-lib-form.php';

    if (!empty($_POST)) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array_merge(
                            array('panelid', 'panel_size', 'owned_by', 'statusid', 'created_by', 'created_date'),
                            $_DATA->buildFields());

            // Prepare values.
            $_POST['owned_by'] = ($_AUTH['level'] >= LEVEL_CURATOR? $_POST['owned_by'] : $_AUTH['id']);
            $_POST['statusid'] = ($_AUTH['level'] >= LEVEL_CURATOR? $_POST['statusid'] : STATUS_IN_PROGRESS);
            $_POST['created_by'] = $_AUTH['id'];
            $_POST['created_date'] = date('Y-m-d H:i:s');

            $nID = $_DATA->insertEntry($_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Created individual information entry ' . $nID);

            // Add diseases.
            $aSuccessDiseases = array();
            if (!empty($_POST['active_diseases']) && is_array($_POST['active_diseases'])) {
                foreach ($_POST['active_diseases'] as $nDisease) {
                    // Add disease to gene.
                    if ($nDisease) {
                        $q = $_DB->q('INSERT INTO ' . TABLE_IND2DIS . ' VALUES (?, ?)', array($nID, $nDisease), false);
                        if (!$q) {
                            // Silent error.
                            lovd_writeLog('Error', LOG_EVENT, 'Disease information entry ' . $nDisease . ' - could not be added to individual ' . $nID);
                        } else {
                            $aSuccessDiseases[] = $nDisease;
                        }
                    }
                }
            }

            if (count($aSuccessDiseases)) {
                lovd_writeLog('Event', LOG_EVENT, 'Disease entr' . (count($aSuccessDiseases) > 1? 'ies' : 'y') . ' successfully added to individual ' . $nID);
            }

            $_AUTH['saved_work']['submissions']['individual'][$nID] = array('id' => $nID, 'panel_size' => $_POST['panel_size']);
            lovd_saveWork();

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . 'submit/individual/' . $nID);

            $_T->printHeader();
            $_T->printTitle();

            lovd_showInfoTable('Successfully created the individual information entry!', 'success');

            $_T->printFooter();
            exit;
        }

    } else {
        // Default values.
        $_DATA->setDefaultValues();
    }



    $_T->printHeader();
    $_T->printTitle();

    if (GET) {
        print('      To create a new individual information entry, please fill out the form below.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');
    lovd_includeJS('inc-js-custom_links.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        array('', '', 'submit', 'Create individual information entry'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && in_array(ACTION, array('edit', 'publish'))) {
    // URL: /individuals/00000001?edit
    // URL: /individuals/00000001?publish
    // Edit an entry.

    $nID = lovd_getCurrentID();
    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    define('LOG_EVENT', 'IndividualEdit');

    // Load appropriate user level for this individual.
    lovd_isAuthorized('individual', $nID);
    if (ACTION == 'publish') {
        lovd_requireAUTH(LEVEL_CURATOR);
    } else {
        lovd_requireAUTH(LEVEL_OWNER);
    }

    require ROOT_PATH . 'class/object_individuals.php';
    $_DATA = new LOVD_Individual();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    $bSubmit = (isset($_AUTH['saved_work']['submissions']['individual'][$nID]));

    // If we're publishing... pretend the form has been sent with a different status.
    if (GET && ACTION == 'publish') {
        $_POST = $zData;
        $_POST['statusid'] = STATUS_OK;
    }

    if (POST || ACTION == 'publish') {
        lovd_errorClean();

        $_DATA->checkFields($_POST, $zData);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array_merge(
                            array('panelid', 'panel_size'),
                            (!$bSubmit || !empty($zData['edited_by'])? array('edited_by', 'edited_date') : array()),
                            $_DATA->buildFields());

            // Prepare values.
            if ($_AUTH['level'] >= LEVEL_CURATOR) {
                $aFields[] = 'owned_by';
                $aFields[] = 'statusid';
            } elseif ($zData['statusid'] > STATUS_MARKED) {
                $aFields[] = 'statusid';
                $_POST['statusid'] = STATUS_MARKED;
            }
            // Only actually committed to the database if we're not in a submission, or when they are already filled in.
            $_POST['edited_by'] = $_AUTH['id'];
            $_POST['edited_date'] = date('Y-m-d H:i:s');

            if (!$bSubmit && !(GET && ACTION == 'publish')) {
                // Put $zData with the old values in $_SESSION for mailing.
                $_SESSION['work']['edits']['individual'][$nID] = $zData;
            }

            // FIXME: implement versioning in updateEntry!
            $_DATA->updateEntry($nID, $_POST, $aFields);

            // Get genes which are modified only when individual and variant status is marked or public.
            if ($zData['statusid'] >= STATUS_MARKED || (isset($_POST['statusid']) && $_POST['statusid'] >= STATUS_MARKED)) {
                $aGenes = $_DB->q('SELECT DISTINCT t.geneid FROM ' . TABLE_TRANSCRIPTS . ' AS t ' .
                                      'INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (vot.transcriptid = t.id) ' .
                                      'INNER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vog.id = vot.id) ' .
                                      'INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s2v.variantid = vog.id) ' .
                                      'INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s.id = s2v.screeningid) ' .
                                      'WHERE vog.statusid >= ? AND s.individualid = ?', array(STATUS_MARKED, $nID))->fetchAllColumn();
                if ($aGenes) {
                    // Change updated date for genes.
                    lovd_setUpdatedDate($aGenes);
                }
            }

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited individual information entry ' . $nID);

            // Change linked diseases?
            // Remove diseases.
            $aToRemove = array();
            foreach ($zData['active_diseases'] as $nDisease) {
                if ($nDisease && !in_array($nDisease, $_POST['active_diseases'])) {
                    // User has requested removal...
                    $aToRemove[] = $nDisease;
                }
            }
            if ($aToRemove) {
                $q = $_DB->q('DELETE FROM ' . TABLE_IND2DIS . ' WHERE individualid = ? AND diseaseid IN (?' . str_repeat(', ?', count($aToRemove) - 1) . ')', array_merge(array($nID), $aToRemove), false);
                if (!$q) {
                    // Silent error.
                    lovd_writeLog('Error', LOG_EVENT, 'Disease information entr' . (count($aToRemove) == 1? 'y' : 'ies') . ' ' . implode(', ', $aToRemove) . ' could not be removed from individual ' . $nID);
                } else {
                    lovd_writeLog('Event', LOG_EVENT, 'Disease entr' . (count($aToRemove) > 1? 'ies' : 'y') . ' successfully removed from individual ' . $nID);
                }
            }

            // Add diseases.
            $aSuccess = array();
            $aFailed = array();
            foreach ($_POST['active_diseases'] as $nDisease) {
                if (!in_array($nDisease, $zData['active_diseases'])) {
                    // Add disease to gene.
                    $q = $_DB->q('INSERT IGNORE INTO ' . TABLE_IND2DIS . ' VALUES (?, ?)', array($nID, $nDisease), false);
                    if (!$q) {
                        $aFailed[] = $nDisease;
                    } else {
                        $aSuccess[] = $nDisease;
                    }
                }
            }
            if ($aFailed) {
                // Silent error.
                lovd_writeLog('Error', LOG_EVENT, 'Disease information entr' . (count($aFailed) == 1? 'y' : 'ies') . ' ' . implode(', ', $aFailed) . ' could not be added to individual ' . $nID);
            }
            if (count($aSuccess)) {
                lovd_writeLog('Event', LOG_EVENT, 'Disease entr' . (count($aSuccess) > 1? 'ies' : 'y') . ' successfully added to individual ' . $nID);
            }

            // Thank the user...
            if ($bSubmit) {
                header('Refresh: 3; url=' . lovd_getInstallURL() . 'submit/individual/' . $nID);

                $_T->printHeader();
                $_T->printTitle();
                lovd_showInfoTable('Successfully edited the individual information entry!', 'success');

                $_T->printFooter();
            } elseif (GET && ACTION == 'publish') {
                // We'll skip the mailing. But of course only if we're sure no other changes were sent (therefore check GET).
                header('Location: ' . lovd_getInstallURL() . CURRENT_PATH);
            } else {
                header('Location: ' . lovd_getInstallURL() . 'submit/finish/individual/' . $nID . '?edit');
            }

            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password field!
            unset($_POST['password']);
        }

    } else {
        // Load current values.
        $_POST = array_merge($_POST, $zData);
        if ($zData['statusid'] < STATUS_HIDDEN) {
            $_POST['statusid'] = STATUS_OK;
        }
    }



    $_T->printHeader();
    $_T->printTitle();

    // If we're not the creator nor the owner, warn.
    if ($zData['created_by'] != $_AUTH['id'] && $zData['owned_by'] != $_AUTH['id']) {
        lovd_showInfoTable('Warning: You are editing data not created or owned by you. You are free to correct errors such as data inserted into the wrong field or typographical errors, but make sure that all other edits are made in consultation with the submitter. If you disagree with the submitter\'s findings, add a remark rather than removing or overwriting data.', 'warning', 760);
    }

    if (GET) {
        print('      To edit an individual information entry, please fill out the form below.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');
    lovd_includeJS('inc-js-custom_links.php');

    // Hardcoded ACTION because when we're publishing, but we get the form on screen (i.e., something is wrong), we want this to be handled as a normal edit.
    print('      <FORM action="' . CURRENT_PATH . '?edit" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        array('', '', 'print', '<INPUT type="submit" value="Edit individual information entry">' . ($bSubmit? '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Cancel" onclick="window.location.href=\'' . lovd_getInstallURL() . 'submit/individual/' . $nID . '\'; return false;" style="border : 1px solid #FF4422;">' : '')),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'delete') {
    // URL: /individuals/00000001?delete
    // Drop specific entry.

    $nID = lovd_getCurrentID();
    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    define('LOG_EVENT', 'IndividualDelete');

    // FIXME: What if individual also contains other user's data?
    lovd_isAuthorized('individual', $nID);
    lovd_requireAUTH($_SETT['user_level_settings']['delete_individual']);

    require ROOT_PATH . 'class/object_individuals.php';
    $_DATA = new LOVD_Individual();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    $aVariantsRemovable = $_DB->q('
        SELECT s2v.variantid, MAX(s2.individualid)
        FROM ' . TABLE_SCREENINGS . ' AS s
          INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid)
          LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v2 ON (s2v.variantid = s2v2.variantid AND s2v.screeningid != s2v2.screeningid)
          LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s2 ON (s2v2.screeningid = s2.id AND s2.individualid != ?)
        WHERE s.individualid = ?
        GROUP BY s2v.variantid
        HAVING MAX(s2.individualid) IS NULL', array($nID, $nID))->fetchAllColumn();
    $nVariantsRemovable = count($aVariantsRemovable);

    if (!empty($_POST)) {
        lovd_errorClean();

        // Mandatory fields.
        if (empty($_POST['password'])) {
            lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');
        }

        // User had to enter their password for authorization.
        if ($_POST['password'] && !lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            // Query text.
            $_DB->beginTransaction();

            // Search for effected genes before the deletion, else we can't find the link.
            // Get genes which are modified only when individual and variant status is marked or public.
            if ($zData['statusid'] >= STATUS_MARKED) {
                $aGenes = $_DB->q('SELECT DISTINCT t.geneid FROM ' . TABLE_TRANSCRIPTS . ' AS t ' .
                    'INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (vot.transcriptid = t.id) ' .
                    'INNER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vog.id = vot.id) ' .
                    'INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s2v.variantid = vog.id) ' .
                    'INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s.id = s2v.screeningid) ' .
                    'WHERE vog.statusid >= ? AND s.individualid = ?', array(STATUS_MARKED, $nID))->fetchAllColumn();
            }

            if (isset($_POST['remove_variants']) && $_POST['remove_variants'] == 'remove') {
                // This also deletes the entries in TABLE_SCR2VAR.
                $_DB->q('DELETE FROM ' . TABLE_VARIANTS . ' WHERE id IN (?' . str_repeat(', ?', count($aVariantsRemovable) - 1) . ')', $aVariantsRemovable);
            }

            // This also deletes the entries in TABLE_PHENOTYPES && TABLE_SCREENINGS && TABLE_SCR2VAR && TABLE_SCR2GENE.
            $_DATA->deleteEntry($nID);

            if ($zData['statusid'] >= STATUS_MARKED && $aGenes) {
                // Change updated date for genes.
                lovd_setUpdatedDate($aGenes);
            }

            $_DB->commit();

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Deleted individual information entry ' . $nID . ' (Owner: ' . $zData['owned_by_'] . ')');

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PE[0]);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully deleted the individual information entry!', 'success');

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password field!
            unset($_POST['password']);
        }
    }



    $_T->printHeader();
    $_T->printTitle();

    lovd_errorPrint();

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    $nVariants = $_DB->q('SELECT COUNT(DISTINCT s2v.variantid) FROM ' . TABLE_SCREENINGS . ' AS s LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) WHERE s.individualid = ? GROUP BY s.individualid', array($nID))->fetchColumn();
    $aOptions = array('remove' => 'Yes, remove ' . ($nVariantsRemovable == 1? 'this variant' : 'these variants') . ' attached to only this individual', 'keep' => 'No, keep ' . ($nVariantsRemovable == 1? 'this variant' : 'these variants') . ' as separate entries');

    // Array which will make up the form table.
    $aForm = array_merge(
                 array(
                        array('POST', '', '', '', '40%', '14', '60%'),
                        array('Deleting individual information entry', '', 'print', '<B>' . $nID . ' (Owner: ' . htmlspecialchars($zData['owned_by_']) . ')</B>'),
                        'skip',
                        array('', '', 'print', 'This individual entry has ' . ($nVariants?: 0) . ' variant' . ($nVariants == 1? '' : 's') . ' attached.'),
'variants_removable' => array('', '', 'print', (!$nVariantsRemovable? 'No variants will be removed.' : '<B>' . $nVariantsRemovable . ' variant' . ($nVariantsRemovable == 1? '' : 's') . ' will be removed, because ' . ($nVariantsRemovable == 1? 'it is' : 'these are'). ' not attached to other individuals!!!</B>')),
          'variants' => array('Should LOVD remove ' . ($nVariantsRemovable == 1? 'this variant' : 'these ' . $nVariantsRemovable . ' variants') . '?', '', 'select', 'remove_variants', 1, $aOptions, false, false, false),
                        array('', '', 'note', '<B>All phenotypes and screenings attached to this individual will be automatically removed' . ($nVariants? ' regardless' : '') . '!!!</B>'),
     'variants_skip' => 'skip',
                        array('Enter your password for authorization', '', 'password', 'password', 20),
                        array('', '', 'submit', 'Delete individual information entry'),
                      ));
    if (!$nVariantsRemovable) {
        if (!$nVariants) {
            unset($aForm['variants_removable']);
        }
        unset($aForm['variants'], $aForm['variants_skip']);
    }

    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}
?>
