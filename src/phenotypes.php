<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-05-23
 * Modified    : 2015-07-01
 * For LOVD    : 3.0-14
 *
 * Copyright   : 2004-2015 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Msc. Daan Asscheman <D.Asscheman@LUMC.nl>
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





if (PATH_COUNT == 1 && !ACTION) {
    // URL: /phenotypes
    // Not supported, forward user to disease-specific overview.
    header('Location: ' . lovd_getInstallURL() . $_PE[0] . '/disease');
    exit;
}





if (PATH_COUNT == 2 && $_PE[1] == 'disease' && !ACTION) {
    // URL: /phenotypes/disease
    // Present users the list of diseases to choose from, to view the phenotype entries for this disease.

    define('PAGE_TITLE', 'Select a disease to view all phenotype entries');
    $_T->printHeader();
    $_T->printTitle();

    require ROOT_PATH . 'class/object_diseases.php';
    $_DATA = new LOVD_Disease();
    $sViewListID = 'Diseases_for_Phenotype_VL';
    $_GET['search_phenotypes'] = '!0';
    $_DATA->setRowLink($sViewListID, CURRENT_PATH . '/' . $_DATA->sRowID);
    $_DATA->viewList($sViewListID);

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 3 && $_PE[1] == 'disease' && ctype_digit($_PE[2]) && !ACTION) {
    // URL: /phenotypes/disease/00001
    // View all phenotype entries for a certain disease.

    $nDiseaseID = sprintf('%05d', $_PE[2]);
    define('PAGE_TITLE', 'View phenotypes for disease #' . $nDiseaseID);
    $_T->printHeader();
    $_T->printTitle();

    require ROOT_PATH . 'class/object_phenotypes.php';

    $_DATA = new LOVD_Phenotype($nDiseaseID);
    $_GET['search_diseaseid'] = $nDiseaseID;
    $_DATA->viewList('Phenotypes_for_Disease_' . $nDiseaseID, array('diseaseid'), false, false, (bool) ($_AUTH['level'] >= LEVEL_MANAGER));

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && !ACTION) {
    // URL: /phenotypes/0000000001
    // View specific entry.

    $nID = sprintf('%010d', $_PE[1]);
    define('PAGE_TITLE', 'View phenotype #' . $nID);
    $_T->printHeader();
    $_T->printTitle();

    // Load appropiate user level for this phenotype entry.
    lovd_isAuthorized('phenotype', $nID);

    require ROOT_PATH . 'class/object_phenotypes.php';
    $_DATA = new LOVD_Phenotype('', $nID);
    $zData = $_DATA->viewEntry($nID);

    $aNavigation = array();
    if ($_AUTH && $_AUTH['level'] >= LEVEL_OWNER) {
        $aNavigation[CURRENT_PATH . '?edit']   = array('menu_edit.png', 'Edit phenotype information', 1);
        if ($zData['statusid'] < STATUS_OK && $_AUTH['level'] >= LEVEL_CURATOR) {
            $aNavigation[CURRENT_PATH . '?publish'] = array('check.png', ($zData['statusid'] == STATUS_MARKED ? 'Remove mark from' : 'Publish (curate)') . ' phenotype entry', 1);
        }
        if ($_AUTH['level'] >= LEVEL_CURATOR) {
            $aNavigation[CURRENT_PATH . '?delete'] = array('cross.png', 'Delete phenotype entry', 1);
        }
    }

    lovd_showJGNavigation($aNavigation, 'Phenotypes');

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 1 && ACTION == 'create' && !empty($_GET['target']) && ctype_digit($_GET['target'])) {
    // URL: /phenotypes?create
    // Create a new entry.

    // FIXME; ik vind nog steeds dat vooral het begin van deze code nog enigszins rommelig is.
    //   De structuur van de code voor de controle van de individual ID en het invullen er van,
    //   is goed af te leiden van transcripts?create.
    define('LOG_EVENT', 'PhenotypeCreate');

    lovd_requireAUTH(LEVEL_SUBMITTER);

    $_GET['target'] = sprintf('%08d', $_GET['target']);
    $z = $_DB->query('SELECT id FROM ' . TABLE_INDIVIDUALS . ' WHERE id = ?', array($_GET['target']))->fetchAssoc();
    if (!$z) {
        define('PAGE_TITLE', 'Create a new phenotype entry');
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('The individual ID given is not valid, please go to the desired individual entry and click on the "Add phenotype" button.', 'stop');
        $_T->printFooter();
        exit;
    } elseif (!lovd_isAuthorized('individual', $_GET['target'], true)) {
        lovd_requireAUTH(LEVEL_OWNER);
    }
    $_POST['individualid'] = $_GET['target'];
    define('PAGE_TITLE', 'Create a new phenotype information entry for individual #' . $_GET['target']);

    require ROOT_PATH . 'inc-lib-form.php';
    lovd_errorClean();

    if (!empty($_GET['diseaseid'])) {
        if (ctype_digit($_GET['diseaseid'])) {
            $_POST['diseaseid'] = sprintf('%05d', $_GET['diseaseid']);
            // Check if there are phenotype columns enabled for this disease & check if the $_POST['diseaseid'] is actually linked to this individual.
            if (!$_DB->query('SELECT COUNT(*) FROM ' . TABLE_IND2DIS . ' AS i2d INNER JOIN ' . TABLE_SHARED_COLS . ' AS sc USING(diseaseid) WHERE i2d.individualid = ? AND i2d.diseaseid = ?', array($_POST['individualid'], $_POST['diseaseid']))->fetchColumn()) {
                lovd_errorAdd('diseaseid', htmlspecialchars($_POST['diseaseid']) . ' is not a valid disease id or no phenotype columns have been enabled for this disease.');
            }
        } else {
            lovd_errorAdd('diseaseid', htmlspecialchars($_GET['diseaseid']) . ' is not a valid disease id.');
        }
    }

    lovd_isAuthorized('gene', $_AUTH['curates']);
    require ROOT_PATH . 'class/object_phenotypes.php';
    if (!empty($_POST['diseaseid'])) {
        $_DATA = new LOVD_Phenotype($_POST['diseaseid']);
    }

    $bSubmit = (isset($_AUTH['saved_work']['submissions']['individual'][$_POST['individualid']]));

    if (empty($_POST['diseaseid']) || lovd_error()) {
        // FIXME; Once we're sure there are no longer individuals with Healthy and something else, we can remove (d.id > 0) from the ORDER BY.
        $sSQL = 'SELECT d.id, CONCAT(d.symbol, " (", d.name, ")") FROM ' . TABLE_DISEASES . ' AS d INNER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (d.id = i2d.diseaseid) INNER JOIN ' . TABLE_SHARED_COLS . ' AS sc ON (d.id = sc.diseaseid) WHERE i2d.individualid = ? GROUP BY d.id ORDER BY (d.id > 0), d.symbol, d.name';
        $aSelectDiseases = $_DB->query($sSQL, array($_POST['individualid']))->fetchAllCombine();
        if (!count($aSelectDiseases)) {
            // Wrong individual ID, individual without diseases, or diseases without phenotype columns.
            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('The individual #' . $_POST['individualid'] . ' does not have any disease entries yet, or none of the diseases have data fields enabled. Please go <A href="individuals/' . $_POST['individualid'] . '?edit">here</A> and add the disease(s) first' . ($_AUTH['level'] < LEVEL_CURATOR? '.' : ' or <A href="columns/Phenotype">here</A> and enable phenotype columns.'), 'warning');
            $_T->printFooter();
            exit;
        }

        $_T->printHeader();
        $_T->printTitle();

        if (!lovd_error()) {
            print('      Please select the disease to which the phenotype information is related.<BR>' . "\n" .
                  '      <BR>' . "\n\n");
        }

        lovd_errorPrint();

        // Table.
        print('      <FORM id="phenotypeCreate" action="' . CURRENT_PATH . '?' . ACTION . '&amp;target=' . $_POST['individualid'] . '" method="post">' . "\n");

        // Array which will make up the form table.
        $aForm = array(
                        array('POST', '', '', '', '50%', '14', '50%'),
                        array('Select the disease', '', 'select', 'diseaseid', 1, $aSelectDiseases, '--Select--', false, false),
                        array('', '', 'print', '<INPUT type="submit" value="Continue &raquo;">' . ($bSubmit? '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Cancel" onclick="window.location.href=\'' . lovd_getInstallURL() . 'submit/individual/' . $_POST['individualid'] . '\'; return false;" style="border : 1px solid #FF4422;">' : '')),
                      );
        lovd_viewForm($aForm);

        print('</FORM>' . "\n\n" .
              '<SCRIPT>' . "\n" .
              '  if ($(\'#phenotypeCreate option\').size() == 2) { $(\'#phenotypeCreate select\')[0].selectedIndex = 1; $(\'#phenotypeCreate\').submit(); }' . "\n" .
              '</SCRIPT>' . "\n\n");

        $_T->printFooter();
        exit;
    }

    if (count($_POST) > 2) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array_merge(
                            array('diseaseid', 'individualid', 'owned_by', 'statusid', 'created_by', 'created_date'),
                            $_DATA->buildFields());

            // Prepare values.
            $_POST['owned_by'] = ($_AUTH['level'] >= LEVEL_CURATOR? $_POST['owned_by'] : $_AUTH['id']);
            $_POST['statusid'] = ($_AUTH['level'] >= LEVEL_CURATOR? $_POST['statusid'] : STATUS_IN_PROGRESS);
            $_POST['created_by'] = $_AUTH['id'];
            $_POST['created_date'] = date('Y-m-d H:i:s');

            $nID = $_DATA->insertEntry($_POST, $aFields);

            // Get genes which are modified only when phenotype, individual and variant are marked or public.
            if ($_POST['statusid']>=STATUS_MARKED){
                $aGenes = $_DB->query('SELECT DISTINCT t.geneid FROM ' . TABLE_TRANSCRIPTS . ' AS t ' .
                                    'INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (vot.transcriptid = t.id) ' .
                                    'INNER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vog.id = vot.id) ' .
                                    'INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s2v.variantid = vog.id) ' .
                                    'INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s.id = s2v.screeningid) ' .
                                    'INNER JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (i.id = s.individualid) ' .
                                    'INNER JOIN ' . TABLE_PHENOTYPES . ' AS p ON (p.individualid = i.id) ' .
                                    'WHERE vog.statusid >= ? ' .
                                    'AND i.statusid >= ? ' .
                                    'AND p.id = ?', array(STATUS_MARKED, STATUS_MARKED, $nID))->fetchAllColumn();
                if ($aGenes) {
                    $aGenes = array_unique($aGenes);
                    // Change updated date for genes
                    lovd_setUpdatedDate($aGenes);
                }
            }

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Created phenotype information entry ' . $nID . ' for individual ' . $_POST['individualid'] . ' related to disease ' . $_POST['diseaseid']);

            if ($bSubmit) {
                // Full submission, continue to rest of questions.
                if (!isset($_AUTH['saved_work']['submissions']['individual'][$_POST['individualid']]['phenotypes'])) {
                    $_AUTH['saved_work']['submissions']['individual'][$_POST['individualid']]['phenotypes'] = array();
                }
                $_AUTH['saved_work']['submissions']['individual'][$_POST['individualid']]['phenotypes'][] = $nID;
                lovd_saveWork();

                header('Refresh: 3; url=' . lovd_getInstallURL() . 'submit/individual/' . $_POST['individualid']);

                $_T->printHeader();
                $_T->printTitle();

                lovd_showInfoTable('Successfully created the phenotype entry!', 'success');

                $_T->printFooter();

            } else {
                // Just added this entry, continue to send an email.
                if (!isset($_SESSION['work']['submits']['phenotype'])) {
                    $_SESSION['work']['submits']['phenotype'] = array();
                }

                while (count($_SESSION['work']['submits']['phenotype']) >= 10) {
                    unset($_SESSION['work']['submits']['phenotype'][min(array_keys($_SESSION['work']['submits']['phenotype']))]);
                }

                $_SESSION['work']['submits']['phenotype'][$nID] = $nID;

                header('Location: ' . lovd_getInstallURL() . 'submit/finish/phenotype/' . $nID);
            }

            exit;
        }

    } else {
        // Default values.
        $_DATA->setDefaultValues();
    }



    $_T->printHeader();
    $_T->printTitle();

    if (GET) {
        print('      To create a new phenotype information entry, please fill out the form below.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');
    lovd_includeJS('inc-js-custom_links.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?create&amp;target=' . $_GET['target'] . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        array('', '', 'print', '<INPUT type="submit" value="Create phenotype information entry">' . ($bSubmit? '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Cancel" onclick="window.location.href=\'' . lovd_getInstallURL() . 'submit/individual/' . $_POST['individualid'] . '\'; return false;" style="border : 1px solid #FF4422;">' : '')),
                      ));
    lovd_viewForm($aForm);

    print("\n" .
          '        <INPUT type="hidden" name="diseaseid" value="' . $_POST['diseaseid'] . '">' . "\n" .
          '      </FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && in_array(ACTION, array('edit', 'publish'))) {
    // URL: /phenotypes/0000000001?edit
    // URL: /phenotypes/0000000001?publish
    // Edit an entry.

    $nID = sprintf('%010d', $_PE[1]);
    define('PAGE_TITLE', 'Edit phenotype #' . $nID);
    define('LOG_EVENT', 'PhenotypeEdit');

    // Load appropiate user level for this phenotype entry.
    lovd_isAuthorized('phenotype', $nID);
    if (ACTION == 'publish') {
        lovd_requireAUTH(LEVEL_CURATOR);
    } else {
        lovd_requireAUTH(LEVEL_OWNER);
    }

    require ROOT_PATH . 'class/object_phenotypes.php';
    $_DATA = new LOVD_Phenotype('', $nID);
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    $bSubmit = (isset($_AUTH['saved_work']['submissions']['individual'][$zData['individualid']]));

    // If we're publishing... pretend the form has been sent with a different status.
    if (GET && ACTION == 'publish') {
        $_POST = $zData;
        $_POST['statusid'] = STATUS_OK;
    }

    if (POST || ACTION == 'publish') {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array_merge(
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
                // FIXME; change owner to owned_by_ in the load entry query of object_phenotypes.php.
                $zData['owned_by_'] = $zData['owner'];
                $zData['diseaseid_'] = $_DB->query('SELECT name FROM ' . TABLE_DISEASES . ' WHERE id = ?', array($zData['diseaseid']))->fetchColumn();
                $_SESSION['work']['edits']['phenotype'][$nID] = $zData;
            }

            // FIXME: implement versioning in updateEntry!
            $_DATA->updateEntry($nID, $_POST, $aFields);

            // Get genes which are modified only when phenotype, individual and variant are marked or public.
            if ($zData['statusid']>=STATUS_MARKED || $_POST['statusid']>=STATUS_MARKED){
                $aGenes = $_DB->query('SELECT DISTINCT t.geneid FROM ' . TABLE_TRANSCRIPTS . ' AS t ' .
                                    'INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (vot.transcriptid = t.id) ' .
                                    'INNER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vog.id = vot.id) ' .
                                    'INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s2v.variantid = vog.id) ' .
                                    'INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s.id = s2v.screeningid) ' .
                                    'INNER JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (i.id = s.individualid) ' .
                                    'INNER JOIN ' . TABLE_PHENOTYPES . ' AS p ON (p.individualid = i.id) ' .
                                    'WHERE vog.statusid >= ? ' .
                                    'AND i.statusid >= ? ' .
                                    'AND p.id = ?', array(STATUS_MARKED, STATUS_MARKED, $nID))->fetchAllColumn();
                if ($aGenes) {
                    $aGenes = array_unique($aGenes);
                    // Change updated date for genes
                    lovd_setUpdatedDate($aGenes);
                }
            }

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited phenotype information entry ' . $nID);

            // Thank the user...
            if ($bSubmit) {
                header('Refresh: 3; url=' . lovd_getInstallURL() . 'submit/individual/' . $zData['individualid']);

                $_T->printHeader();
                $_T->printTitle();
                lovd_showInfoTable('Successfully edited the phenotype information entry!', 'success');

                $_T->printFooter();
            } elseif (GET && ACTION == 'publish') {
                // We'll skip the mailing. But of course only if we're sure no other changes were sent (therefore check GET).
                header('Location: ' . lovd_getInstallURL() . CURRENT_PATH);
            } else {
                header('Location: ' . lovd_getInstallURL() . 'submit/finish/phenotype/' . $nID . '?edit');
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

    if (GET) {
        print('      To edit an phenotype information entry, please fill out the form below.<BR>' . "\n" .
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
                        array('', '', 'print', '<INPUT type="submit" value="Edit phenotype information entry">' . ($bSubmit? '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Cancel" onclick="window.location.href=\'' . lovd_getInstallURL() . 'submit/individual/' . $zData['individualid'] . '\'; return false;" style="border : 1px solid #FF4422;">' : '')),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'delete') {
    // URL: /phenotypes/0000000001?delete
    // Drop specific entry.

    $nID = sprintf('%010d', $_PE[1]);
    define('PAGE_TITLE', 'Delete phenotype #' . $nID);
    define('LOG_EVENT', 'PhenotypeDelete');

    // FIXME; hier moet een goede controle komen, wanneer lager is toegestaan.
    // Load appropiate user level for this phenotype entry.
    lovd_isAuthorized('phenotype', $nID);
    lovd_requireAUTH(LEVEL_CURATOR);

    require ROOT_PATH . 'class/object_phenotypes.php';
    $_DATA = new LOVD_Phenotype('', $nID);
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    if (!empty($_POST)) {
        lovd_errorClean();

        // Mandatory fields.
        if (empty($_POST['password'])) {
            lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');
        }

        // User had to enter his/her password for authorization.
        if ($_POST['password'] && !lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            // Get genes which are modified before we delete the entry.
            // Only when phenotype, individual and variant are marked or public.
            if ($zData['statusid']>=STATUS_MARKED){
                $aGenes = $_DB->query('SELECT DISTINCT t.geneid FROM ' . TABLE_TRANSCRIPTS . ' AS t ' .
                                    'INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (vot.transcriptid = t.id) ' .
                                    'INNER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vog.id = vot.id) ' .
                                    'INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s2v.variantid = vog.id) ' .
                                    'INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s.id = s2v.screeningid) ' .
                                    'INNER JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (i.id = s.individualid) ' .
                                    'INNER JOIN ' . TABLE_PHENOTYPES . ' AS p ON (p.individualid = i.id) ' .
                                    'WHERE vog.statusid >= ? ' .
                                    'AND i.statusid >= ? ' .
                                    'AND p.id = ?', array(STATUS_MARKED, STATUS_MARKED, $nID))->fetchAllColumn();
            }

            $_DATA->deleteEntry($nID);

            if ($zData['statusid']>=STATUS_MARKED && $aGenes) {
                $aGenes = array_unique($aGenes);
                // Change updated date for genes
                lovd_setUpdatedDate($aGenes);
            }

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Deleted phenotype information entry ' . $nID . ' (Owner: ' . $zData['owner'] . ')');

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . 'individuals/' . $zData['individualid']);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully deleted the phenotype information entry!', 'success');

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    }



    $_T->printHeader();
    $_T->printTitle();

    lovd_errorPrint();

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 array(
                        array('POST', '', '', '', '40%', '14', '60%'),
                        array('Deleting phenotype information entry', '', 'print', $nID . ' (Owner: ' . $zData['owner'] . ')'),
                        'skip',
                        array('Enter your password for authorization', '', 'password', 'password', 20),
                        array('', '', 'submit', 'Delete phenotype information entry'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}
?>
