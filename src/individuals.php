<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-02-16
 * Modified    : 2012-06-07
 * For LOVD    : 3.0-beta-06
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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
    // URL: /individuals
    // View all entries.

    define('PAGE_TITLE', 'View individuals');
    $_T->printHeader();
    $_T->printTitle();

    require ROOT_PATH . 'class/object_individuals.php';
    $_DATA = new LOVD_Individual();
    $_DATA->viewList('Individuals', array('panelid', 'diseaseids'), false, false, (bool) ($_AUTH['level'] >= LEVEL_MANAGER));

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && !ACTION) {
    // URL: /individuals/00000001
    // View specific entry.

    $nID = sprintf('%08d', $_PE[1]);
    define('PAGE_TITLE', 'View individual #' . $nID);
    $_T->printHeader();
    $_T->printTitle();

    // Load appropiate user level for this individual.
    lovd_isAuthorized('individual', $nID);

    require ROOT_PATH . 'class/object_individuals.php';
    $_DATA = new LOVD_Individual($nID);
    $zData = $_DATA->viewEntry($nID);

    $sNavigation = '';
    if ($_AUTH) {
        if ($_AUTH['level'] >= LEVEL_OWNER) {
            $sNavigation = '<A href="' . CURRENT_PATH . '?edit">Edit individual information</A>';
            $sNavigation .= ' | <A href="screenings?create&amp;target=' . $nID . '">Add screening to individual</A>';
            // You can only add phenotype information to this individual, when there are phenotype columns enabled.
            if ($_DB->query('SELECT COUNT(*) FROM ' . TABLE_IND2DIS . ' AS i2d INNER JOIN ' . TABLE_SHARED_COLS . ' AS sc USING(diseaseid) WHERE i2d.individualid = ?', array($nID))->fetchColumn()) {
                $sNavigation .= ' | <A href="phenotypes?create&amp;target=' . $nID . '">Add phenotype information to individual</A>';
            }
            if ($_AUTH['level'] >= LEVEL_CURATOR) {
                $sNavigation .= ' | <A href="' . CURRENT_PATH . '?delete">Delete individual entry</A>';
            }
        }
    }

    if ($sNavigation) {
        print('      <IMG src="gfx/trans.png" alt="" width="1" height="5"><BR>' . "\n");
        lovd_showNavigation($sNavigation);
    }

    print('<BR><BR>' . "\n\n");
    $_T->printTitle('Diseases', 'H4');

    if (!empty($zData['diseases'])) {
        // List of diseases associated with this person.
        $_GET['search_diseaseid'] = implode('|', $zData['diseaseids']);
        require ROOT_PATH . 'class/object_diseases.php';
        $_DATA = new LOVD_Disease();
        $_DATA->viewList('Diseases_for_I_VE', 'diseaseid', true, true);
        print('<BR><BR>' . "\n\n");

        // List of phenotype entries associated with this person, per disease.
        $_GET['search_individualid'] = $nID;
        $_T->printTitle('Phenotypes', 'H4');
        if (!empty($zData['phenotypes'])) {
            require ROOT_PATH . 'class/object_phenotypes.php';
            foreach($zData['diseases'] as $aDisease) {
                list($nDiseaseID, $sSymbol, $sName) = $aDisease;
                if (in_array($nDiseaseID, $zData['phenotypes'])) {
                    $_GET['search_diseaseid'] = $nDiseaseID;
                    $_DATA = new LOVD_Phenotype($nDiseaseID);
                    print('<B>' . $sName . ' (<A href="diseases/' . $nDiseaseID . '">' . $sSymbol . '</A>)</B>&nbsp;&nbsp;<A href="phenotypes?create&amp;target=' . $nID . '&amp;diseaseid=' . $nDiseaseID . '"><IMG src="gfx/plus.png"></A> Add phenotype for this disease');
                    $_DATA->viewList('Phenotypes_for_I_VE_' . $nDiseaseID, array('phenotypeid', 'individualid', 'diseaseid'), true, true);
                }
            }
        } else {
            lovd_showInfoTable('No phenotypes found for this individual!', 'stop');
        }
        unset($_GET['search_individualid']);
        unset($_GET['search_diseaseid']);
    } else {
        lovd_showInfoTable('No diseases found for this individual!', 'stop');
    }

    $_GET['search_individualid'] = $nID;
    print('<BR><BR>' . "\n\n");
    $_T->printTitle('Screenings', 'H4');
    require ROOT_PATH . 'class/object_screenings.php';
    $_DATA = new LOVD_Screening();
    $_DATA->setSortDefault('id');
    $_DATA->viewList('Screenings_for_I_VE', array('screeningid', 'individualid', 'created_date', 'edited_date'), true, true);
    unset($_GET['search_individualid']);

    if (!empty($zData['screeningids'])) {
        $_GET['search_screeningids'] = $zData['screeningids'];
        print('<BR><BR>' . "\n\n");
        $_T->printTitle('Variants', 'H4');
        require ROOT_PATH . 'class/object_genome_variants.php';
        $_DATA = new LOVD_GenomeVariant();
        $_DATA->setSortDefault('id');
        $_DATA->viewList('VOG_for_I_VE', 'screeningids', true);
        unset($_GET['search_screeningids']);
    }

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 1 && ACTION == 'create') {
    // URL: /individuals?create
    // Create a new entry.

    define('PAGE_TITLE', 'Create a new individual information entry');
    define('LOG_EVENT', 'IndividualCreate');

    lovd_isAuthorized('gene', $_AUTH['curates']);
    lovd_requireAUTH(LEVEL_SUBMITTER);

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
                        $q = $_DB->query('INSERT INTO ' . TABLE_IND2DIS . ' VALUES (?, ?)', array($nID, $nDisease), false);
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
                $nDiseases = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_SHARED_COLS . ' WHERE diseaseid IN (?' . str_repeat(', ?', count($_POST['active_diseases']) - 1) . ') GROUP BY diseaseid', $_POST['active_diseases'])->fetchColumn();
            } else {
                $nDiseases = 0;
            }

            // Save the individualid in $_SESSION so that other create forms know we are in the middle of a submission.
            if (!isset($_SESSION['work']['submits']['individual'])) {
                $_SESSION['work']['submits']['individual'] = array();
            }
            while (count($_SESSION['work']['submits']['individual']) >= 10) {
                unset($_SESSION['work']['submits']['individual'][min(array_keys($_SESSION['work']['submits']['individual']))]);
            }
            $_SESSION['work']['submits']['individual'][$nID] = array('id' => $nID, 'panel_size' => $_POST['panel_size']);
            
            $sPersons = ($_POST['panel_size'] > 1? 'this group of individuals' : 'this individual');
            $sMessage = (empty($_POST['active_diseases'])? 'No diseases were selected for ' . $sPersons . '.\nThe phenotype information that can be submitted depends on the selected diseases.' : 'The disease' . (count($_POST['active_diseases']) > 1? 's' : '') . ' added to ' . $sPersons . ' do' . (count($_POST['active_diseases']) > 1? '' : 'es') . ' not have phenotype columns enabled yet.');

            $_T->printHeader();
            $_T->printTitle();
            print('      Do you have any phenotype information available for ' . $sPersons . '?<BR><BR>' . "\n\n");
            
            $aOptionsList = array();
            if (!$nDiseases) {
                $aOptionsList['options'][0]['disabled'] = true;
                $aOptionsList['options'][0]['onclick']  = 'javascript:alert(\'' . $sMessage . '\');';
            } else {
                $aOptionsList['options'][0]['onclick'] = 'phenotypes?create&amp;target=' . $nID;
            }
            $aOptionsList['options'][0]['option_text'] = '<B>Yes, I want to submit phenotype information on ' . $sPersons . '</B>';

            $aOptionsList['options'][1]['onclick']     = 'screenings?create&amp;target=' . $nID;
            $aOptionsList['options'][1]['option_text'] = '<B>No, I want to submit mutation screening information instead</B>';
            // FIXME; Once we have code to allow the user (and remind them) to continue the unfinished submission, we can enable this part again
            // (although it would be nice to put a warning here, also).
            if (true) {
                $aOptionsList['options'][2]['disabled'] = true;
                $aOptionsList['options'][2]['onclick']  = 'javascript:alert(\'You cannot finish your submission, because no screenings have been added to ' . $sPersons . ' yet!\')';
            } else {
                $aOptionsList['options'][2]['onclick'] = 'submit/finish/individual/' . $nID;
            }
            $aOptionsList['options'][2]['option_text'] = '<B>No, I have finished my submission</B>';

            print(lovd_buildOptionTable($aOptionsList));

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





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'edit') {
    // URL: /individuals/00000001?edit
    // Edit an entry.

    $nID = sprintf('%08d', $_PE[1]);
    define('PAGE_TITLE', 'Edit individual #' . $nID);
    define('LOG_EVENT', 'IndividualEdit');

    // Load appropiate user level for this individual.
    lovd_isAuthorized('individual', $nID);
    lovd_requireAUTH(LEVEL_OWNER);

    require ROOT_PATH . 'class/object_individuals.php';
    $_DATA = new LOVD_Individual();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    if (!empty($_POST)) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array_merge(
                            array('panelid', 'panel_size', 'edited_by', 'edited_date'),
                            $_DATA->buildFields());

            // Prepare values.
            if ($_AUTH['level'] >= LEVEL_CURATOR) {
                $aFields[] = 'owned_by';
                $aFields[] = 'statusid';
            } elseif ($zData['statusid'] > STATUS_MARKED) {
                $aFields[] = 'statusid';
                $_POST['statusid'] = STATUS_MARKED;
            }
            $_POST['edited_by'] = $_AUTH['id'];
            $_POST['edited_date'] = date('Y-m-d H:i:s');

            // FIXME: implement versioning in updateEntry!
            $_DATA->updateEntry($nID, $_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited individual information entry ' . $nID);

            // Change linked diseases?
            // Diseases the gene is currently linked to.
            // FIXME; we moeten afspraken op papier zetten over de naamgeving van velden, ik zou hier namelijk geen _ achter plaatsen.
            //   Een idee zou namelijk zijn om loadEntry()/viewEntry() automatisch velden te laten exploden afhankelijk van hun naam. Is dat wat?
            $aDiseases = explode(';', $zData['active_diseases_']);

            // Remove diseases.
            $aToRemove = array();
            foreach ($aDiseases as $nDisease) {
                if ($nDisease && !in_array($nDisease, $_POST['active_diseases'])) {
                    // User has requested removal...
                    $aToRemove[] = $nDisease;
                }
            }
            if ($aToRemove) {
                $q = $_DB->query('DELETE FROM ' . TABLE_IND2DIS . ' WHERE individualid = ? AND diseaseid IN (?' . str_repeat(', ?', count($aToRemove) - 1) . ')', array_merge(array($nID), $aToRemove), false);
                if (!$q) {
                    // Silent error.
                    lovd_writeLog('Error', LOG_EVENT, 'Disease information entr' . (count($aToRemove) == 1? 'y' : 'ies') . ' ' . implode(', ', $aToRemove) . ' could not be removed from individual ' . $nID);
                }
            }

            // Add diseases.
            $aSuccess = array();
            $aFailed = array();
            foreach ($_POST['active_diseases'] as $nDisease) {
                if (!in_array($nDisease, $aDiseases)) {
                    // Add disease to gene.
                    $q = $_DB->query('INSERT IGNORE INTO ' . TABLE_IND2DIS . ' VALUES (?, ?)', array($nID, $nDisease), false);
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

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully edited the individual information entry!', 'success');

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password field!
            unset($_POST['password']);
        }

    } else {
        // Load current values.
        $_POST = array_merge($_POST, $zData);
        // Load connected diseases.
        $_POST['active_diseases'] = explode(';', $_POST['active_diseases_']);
    }



    $_T->printHeader();
    $_T->printTitle();

    if (GET) {
        print('      To edit an individual information entry, please fill out the form below.<BR>' . "\n" .
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
                        array('', '', 'submit', 'Edit individual information entry'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'delete') {
    // URL: /individuals/00000001?delete
    // Drop specific entry.

    $nID = sprintf('%08d', $_PE[1]);
    define('PAGE_TITLE', 'Delete individual information entry ' . $nID);
    define('LOG_EVENT', 'IndividualDelete');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_individuals.php';
    $_DATA = new LOVD_Individual();
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
            // Query text.
            // This also deletes the entries in TABLE_PHENOTYPES && TABLE_SCREENINGS && TABLE_SCR2VAR && TABLE_SCR2GENES.
            $_DB->beginTransaction();
            if (isset($_POST['remove_variants']) && $_POST['remove_variants'] == 'remove') {
                $aOutput = $_DB->query('SELECT id FROM ' . TABLE_SCREENINGS . ' WHERE individualid = ?', array($nID))->fetchAllColumn();
                if (count($aOutput)) {
                    $_DB->query('DELETE vog FROM ' . TABLE_VARIANTS . ' AS vog INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid) WHERE s2v.screeningid IN (?' . str_repeat(', ?', count($aOutput) - 1) . ')', $aOutput);
                }
            }

            $_DATA->deleteEntry($nID);

            $_DB->commit();

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Deleted individual information entry ' . $nID . ' (Owner: ' . $zData['owner'] . ')');

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

    $nVariants = $_DB->query('SELECT COUNT(DISTINCT s2v.variantid) FROM ' . TABLE_SCREENINGS . ' AS s LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) WHERE s.individualid = ? GROUP BY s.individualid', array($nID))->fetchColumn();
    $aOptions = array('remove' => 'Also remove all variants attached to this individual', 'keep' => 'Keep all attached variants as separate entries');

    // Array which will make up the form table.
    $aForm = array_merge(
                 array(
                        array('POST', '', '', '', '45%', '14', '55%'),
                        array('Deleting individual information entry', '', 'print', '<B>' . $nID . ' (Owner: ' . $zData['owner'] . ')</B>'),
                        'skip',
                        array('', '', 'print', 'This individual entry has ' . ($nVariants? $nVariants : 0) . ' variant' . ($nVariants == 1? '' : 's') . ' attached.'),
          'variants' => array('What should LOVD do with the attached variants?', '', 'select', 'remove_variants', 1, $aOptions, false, false, false),
                        array('', '', 'note', '<B>All phenotypes and screenings attached to this individual will be automatically removed' . ($nVariants? ' regardless' : '') . '!!!</B>'),
     'variants_skip' => 'skip',
                        array('Enter your password for authorization', '', 'password', 'password', 20),
                        array('', '', 'submit', 'Delete individual information entry'),
                      ));
    if (!$nVariants) {
        unset($aForm['variants'], $aForm['variants_skip']);
    }

    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}
?>
