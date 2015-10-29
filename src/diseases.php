<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-07-27
 * Modified    : 2015-10-28
 * For LOVD    : 3.0-15
 *
 * Copyright   : 2004-2015 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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
    // URL: /diseases
    // View all entries.

    define('PAGE_TITLE', 'View diseases');
    $_T->printHeader();
    $_T->printTitle();

    require ROOT_PATH . 'class/object_diseases.php';
    $_DATA = new LOVD_Disease();
    // If the list of diseases is loaded from the individual's data entry form, don't use the VL links.
    if (isset($_GET['no_links'])) {
        $_DATA->setRowLink('Diseases', '');
    }
    $_DATA->viewList('Diseases', array(), false, false, (bool) ($_AUTH['level'] >= LEVEL_MANAGER));

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && !ACTION) {
    // URL: /diseases/00001
    // View specific entry.

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'View disease #' . $nID);
    $_T->printHeader();
    $_T->printTitle();

    if ($nID == '00000') {
        $nID = -1;
    }

    // Load appropriate user level for this disease.
    lovd_isAuthorized('disease', $nID); // This call will make database queries if necessary.

    require ROOT_PATH . 'class/object_diseases.php';
    $_DATA = new LOVD_Disease();
    // Increase the max group_concat() length, so that diseases linked to many many genes still have all genes mentioned here.
    $_DB->query('SET group_concat_max_len = 150000');
    $zData = $_DATA->viewEntry($nID);

    $aNavigation = array();
    if ($_AUTH && $_AUTH['level'] >= LEVEL_CURATOR) {
        $aNavigation[CURRENT_PATH . '?edit']      = array('menu_edit.png', 'Edit disease information', 1);
        if ($_AUTH['level'] == LEVEL_CURATOR) {
            $bDelete = true;
            foreach ($zData['genes'] as $sGene) {
                if (!in_array($sGene, $_AUTH['curates'])) {
                    $bDelete = false;
                    break;
                }
            }
        }
        if ($_AUTH['level'] >= LEVEL_MANAGER || $bDelete) {
            $aNavigation[CURRENT_PATH . '?delete']    = array('cross.png', 'Delete disease entry', 1);
        }
        $aNavigation[CURRENT_PATH . '/columns']       = array('menu_columns.png', 'View enabled phenotype columns', 1);
        $aNavigation[CURRENT_PATH . '/columns?order'] = array('menu_columns.png', 'Re-order enabled phenotype columns', 1);
        $aNavigation['columns/Phenotype'] = array('menu_columns.png', 'View all available phenotype columns', 1);
        $aNavigation['phenotypes/disease/' . $nID] = array('menu_magnifying_glass.png', 'View all phenotype entries for this disease', 1);
    }
    lovd_showJGNavigation($aNavigation, 'Diseases');

    if ($zData['individuals']) {
        $_GET['search_diseaseids'] = $nID;
        print('<BR><BR>' . "\n\n");
        $_T->printTitle('Individuals', 'H4');
        require ROOT_PATH . 'class/object_individuals.php';
        $_DATA = new LOVD_Individual();
        $_DATA->setSortDefault('id');
        $_DATA->viewList('Individuals_for_D_VE', array('panelid', 'diseaseids'), true, false, (bool) ($_AUTH['level'] >= LEVEL_MANAGER));
    }

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && !ctype_digit($_PE[1]) && !ACTION) {
    // URL: /diseases/DMD
    // Try to find a disease by its abbreviation and forward.
    // When we have multiple hits, refer to listView.

    $sID = rawurldecode($_PE[1]);
    $aDiseases = $_DB->query('SELECT id FROM ' . TABLE_DISEASES . ' WHERE symbol = ?', array($sID))->fetchAllColumn();
    $n = count($aDiseases);
    if (!$n) {
        define('PAGE_TITLE', 'View disease');
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('No such ID!', 'stop');
        $_T->printFooter();
    } elseif ($n == 1) {
        header('Location: ' . lovd_getInstallURL() . $_PE[0] . '/' . $aDiseases[0]);
    } else {
        // Multiple hits. Forward to exact match search.
        header('Location: ' . lovd_getInstallURL() . $_PE[0] . '?search_symbol=%3D%22' . rawurlencode($sID) . '%22');
    }
    exit;
}





if (PATH_COUNT == 1 && ACTION == 'create') {
    // URL: /diseases?create
    // Create a new entry.

    define('PAGE_TITLE', 'Create a new disease information entry');
    define('LOG_EVENT', 'DiseaseCreate');

    // Require curator clearance.
    lovd_isAuthorized('gene', $_AUTH['curates']);
    lovd_requireAUTH(LEVEL_CURATOR);

    require ROOT_PATH . 'inc-lib-actions.php';
    require ROOT_PATH . 'class/object_diseases.php';
    $_DATA = new LOVD_Disease();
    require ROOT_PATH . 'inc-lib-form.php';

    if (!empty($_POST)) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('symbol', 'name', 'id_omim', 'created_by', 'created_date');

            // Prepare values.
            $_POST['created_by'] = $_AUTH['id'];
            $_POST['created_date'] = date('Y-m-d H:i:s');

            $nID = $_DATA->insertEntry($_POST, $aFields);

            // Add all standard custom columns to this new disease.
            lovd_addAllDefaultCustomColumns('disease', $nID);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Created disease information entry ' . $nID . ' - ' . $_POST['symbol'] . ' (' . $_POST['name'] . ')');

            // Add genes.
            $aSuccess = array();
            if (!empty($_POST['genes'])) {
                foreach ($_POST['genes'] as $sGene) {
                    // Add gene to disease.
                    // FIXME; Nu dat PDO beschikbaar is, doe dit in een prepared statement met multiple executes.
                    $q = $_DB->query('INSERT INTO ' . TABLE_GEN2DIS . ' VALUES (?, ?)', array($sGene, $nID), false);
                    if (!$q) {
                        // Silent error.
                        lovd_writeLog('Error', LOG_EVENT, 'Disease information entry ' . $nID . ' - ' . $_POST['symbol'] . ' - could not be added to gene ' . $sGene);
                    } else {
                        $aSuccess[] = $sGene;
                    }
                }
            }
            if (count($aSuccess)) {
                lovd_writeLog('Event', LOG_EVENT, 'Disease information entry ' . $nID . ' - ' . $_POST['symbol'] . ' - successfully added to gene(s) ' . implode(', ', $aSuccess));
            }

            // Thank the user...
            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully created the disease information entry!', 'success');

            if (isset($_GET['in_window'])) {
                // We're in a new window, refresh opener en close window.
                print('      <SCRIPT type="text/javascript">
                                 $(opener.document.forms[0][\'active_diseases[]\']).append(\'<OPTION value="' . $nID . '">' . lovd_shortenString($_POST['symbol'] . ' (' . $_POST['name'] . ')', 75) . '</OPTION>\');
                                 if ($(opener.document.forms[0][\'active_diseases[]\']).attr(\'size\') < 15) {
                                     $(opener.document.forms[0][\'active_diseases[]\']).attr(\'size\', eval($(opener.document.forms[0][\'active_diseases[]\']).attr(\'size\')) + 1);
                                 }
                                 if (opener.document.location.href.match(/\/(individuals\/' . (empty($_POST['genes'])? '' : '|genes\/(' . implode('|', $_POST['genes']) . ')\?') . ')/)) {
                                     $(opener.document.forms[0][\'active_diseases[]\']).children(\'option:last\').attr(\'selected\', 1);
                                 }
                                 setTimeout(\'self.close();\', 1000);</SCRIPT>' . "\n\n");
            } else {
                print('      <SCRIPT type="text/javascript">setTimeout(\'window.location.href=\\\'' . lovd_getInstallURL() . CURRENT_PATH . '/' . $nID . '\\\';\', 3000);</SCRIPT>' . "\n\n");
            }

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']); // Currently does not have an effect here.
        }

    } else {
        // Default values.
        $_DATA->setDefaultValues();
    }



    $_T->printHeader();
    $_T->printTitle();

    if (GET) {
        print('      To create a new disease information entry, please fill out the form below.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . (isset($_GET['in_window'])? '&amp;in_window' : '') . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        array('', '', 'submit', 'Create disease information entry'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'edit') {
    // URL: /diseases/00001?edit
    // Edit a specific entry.

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'Edit disease information entry #' . $nID);
    define('LOG_EVENT', 'DiseaseEdit');

    if ($nID == '00000') {
        $nID = -1;
    }

    // Load appropriate user level for this disease.
    lovd_isAuthorized('disease', $nID); // This call will make database queries if necessary.
    lovd_requireAUTH(LEVEL_CURATOR);

    require ROOT_PATH . 'class/object_diseases.php';
    $_DATA = new LOVD_Disease();
    // Increase the max group_concat() length, so that diseases linked to many many genes still have all genes mentioned here.
    $_DB->query('SET group_concat_max_len = 150000');
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    if (!empty($_POST)) {
        lovd_errorClean();

        $_DATA->checkFields($_POST, $zData);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('symbol', 'name', 'id_omim', 'edited_by', 'edited_date');

            // Prepare values.
            $_POST['edited_by'] = $_AUTH['id'];
            $_POST['edited_date'] = date('Y-m-d H:i:s');

            $_DATA->updateEntry($nID, $_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited disease information entry ' . $nID . ' - ' . $_POST['symbol'] . ' (' . $_POST['name'] . ')');

            // Change linked genes?
            // Remove genes.
            $aToRemove = array();
            foreach ($zData['genes'] as $sGene) {
                if ($sGene && !in_array($sGene, $_POST['genes'])) {
                    // User has requested removal...
                    $aToRemove[] = $sGene;
                }
            }
            if ($aToRemove) {
                $q = $_DB->query('DELETE FROM ' . TABLE_GEN2DIS . ' WHERE diseaseid = ? AND geneid IN (?' . str_repeat(', ?', count($aToRemove) - 1) . ')', array_merge(array($nID), $aToRemove), false);
                if (!$q) {
                    // Silent error.
                    // FIXME; deze log entries zijn precies andersom dan bij create (wat wordt aan wat toegevoegd/verwijderd). Dat moeten we standaardiseren, maar wellicht even overleggen over LOVD-breed.
                    lovd_writeLog('Error', LOG_EVENT, 'Gene information entr' . (count($aToRemove) == 1? 'y' : 'ies') . ' ' . implode(', ', $aToRemove) . ' could not be removed from disease ' . $nID);
                } else {
                    lovd_writeLog('Event', LOG_EVENT, 'Gene information entr' . (count($aToRemove) == 1? 'y' : 'ies') . ' ' . implode(', ', $aToRemove) . ' successfully removed from disease ' . $nID);
                }
            }

            // Add genes.
            $aSuccess = array();
            $aFailed = array();
            $q = $_DB->prepare('INSERT IGNORE INTO ' . TABLE_GEN2DIS . ' VALUES (?, ?)');
            foreach ($_POST['genes'] as $sGene) {
                if (!in_array($sGene, $zData['genes']) && $sGene != 'None') {
                    // Add disease to gene.
                    $b = $q->execute(array($sGene, $nID), false);
                    if (!$q) {
                        $aFailed[] = $sGene;
                    } else {
                        $aSuccess[] = $sGene;
                    }
                }
            }
            if ($aFailed) {
                // Silent error.
                lovd_writeLog('Error', LOG_EVENT, 'Gene information entr' . (count($aFailed) == 1? 'y' : 'ies') . ' ' . implode(', ', $aFailed) . ' could not be added to disease ' . $nID);
            }
            if ($aSuccess) {
                lovd_writeLog('Event', LOG_EVENT, 'Gene information entr' . (count($aSuccess) == 1? 'y' : 'ies') . ' ' . implode(', ', $aSuccess) . ' successfully added to disease ' . $nID);
            }

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully edited the disease information entry!', 'success');

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']); // Currently does not have an effect here.
        }

    } else {
        // Load current values.
        $_POST = array_merge($_POST, $zData);
    }



    $_T->printHeader();
    $_T->printTitle();

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        array('', '', 'submit', 'Edit disease information entry'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'delete') {
    // URL: /diseases/00001?delete
    // Delete specific entry.

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'Delete disease information entry #' . $nID);
    define('LOG_EVENT', 'DiseaseDelete');

    if ($nID == '00000') {
        $nID = -1;
    }

    lovd_isAuthorized('disease', $nID);

    require ROOT_PATH . 'class/object_diseases.php';
    $_DATA = new LOVD_Disease();
    $zData = $_DATA->loadEntry($nID);

    if ($_AUTH['level'] == LEVEL_CURATOR) {
        $bDelete = true;
        foreach ($zData['genes'] as $sGene) {
            if (!in_array($sGene, $_AUTH['curates'])) {
                $bDelete = false;
                break;
            }
        }

        if (!$bDelete) {
            // Curator has no delete rights, throw him out.
            lovd_requireAUTH(LEVEL_MANAGER);
        }
    } else {
        // Require manager clearance.
        lovd_requireAUTH(LEVEL_MANAGER);
    }



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
            // This also deletes the entries in gen2dis.
            $_DATA->deleteEntry($nID);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Deleted disease information entry ' . $nID . ' - ' . $zData['symbol'] . ' (' . $zData['name'] . ')');

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . 'diseases');

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully deleted the disease information entry!', 'success');

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
    $aForm = array(
                    array('POST', '', '', '', '50%', '14', '50%'),
                    array('Deleting disease information entry', '', 'print', $zData['symbol'] . ' (' . $zData['name'] . ')'),
                    'skip',
                    array('Enter your password for authorization', '', 'password', 'password', 20),
                    array('', '', 'submit', 'Delete disease information entry'),
                  );
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 3 && ctype_digit($_PE[1]) && $_PE[2] == 'columns' && !ACTION) {
    // URL: /diseases/00001/columns
    // View enabled columns for this disease.

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'View enabled custom data columns for disease #' . $nID);
    $_T->printHeader();
    $_T->printTitle();

    // Load appropriate user level for this disease.
    lovd_isAuthorized('disease', $nID); // This call will make database queries if necessary.
    lovd_requireAUTH(LEVEL_CURATOR);

    require ROOT_PATH . 'class/object_shared_columns.php';
    $_DATA = new LOVD_SharedColumn($nID);
    $n = $_DATA->viewList('Columns');

    if ($n) {
        lovd_showJGNavigation(array('javascript:lovd_openWindow(\'' . lovd_getInstallURL() . CURRENT_PATH . '?order&amp;in_window\', \'ColumnSort' . $nID . '\', 800, 350);' =>
            array('', 'Change order of columns', 1)), 'Columns');
    }

    $_T->printFooter();
    exit;
}





if (PATH_COUNT > 3 && ctype_digit($_PE[1]) && $_PE[2] == 'columns' && !ACTION) {
    // URL: /diseases/00001/columns/IQ
    // URL: /diseases/00001/columns/Blood_pressure/Systolic
    // View specific enabled column for this disease.

    $sUnit = 'disease';
    $sCategory = 'Phenotype';

    $sParentID = $_PE[1];
    $aCol = $_PE;
    unset($aCol[0], $aCol[1], $aCol[2]); // 'diseases/00001/columns';
    $sColumnID = implode('/', $aCol);
    define('PAGE_TITLE', 'View settings for custom data column ' . $sColumnID . ' for ' . $sUnit . ' #' . $sParentID);
    $_T->printHeader();
    $_T->printTitle();

    // Load appropriate user level for this gene.
    lovd_isAuthorized($sUnit, $sParentID);
    lovd_requireAUTH(LEVEL_CURATOR); // Will also stop user if gene given is fake.

    require ROOT_PATH . 'class/object_shared_columns.php';
    $_DATA = new LOVD_SharedColumn($sParentID, $sCategory . '/' . $sColumnID);
    $zData = $_DATA->viewEntry($sCategory . '/' . $sColumnID);

    $aNavigation =
         array(
                CURRENT_PATH . '?edit' => array('menu_edit.png', 'Edit settings for this ' . $sUnit . ' only', 1),
                // FIXME; Can we redirect inmediately to the correct page? And in a new window!
                'columns/' . $sCategory . '/' . $sColumnID . '?remove&amp;target=' . $sParentID => array('cross.png', 'Remove column from this ' . $sUnit, (!$zData['hgvs'])),
              );
    lovd_showJGNavigation($aNavigation, 'ColumnEdit');

    $_T->printFooter();
    exit;
}





if (PATH_COUNT > 3 && ctype_digit($_PE[1]) && $_PE[2] == 'columns' && ACTION == 'edit') {
    // URL: /diseases/00001/columns/IQ?edit
    // URL: /diseases/00001/columns/Blood_pressure/Systolic?edit
    // View specific enabled column for this disease.

    $sUnit = 'disease';
    $sCategory = 'Phenotype';

    $sParentID = rawurldecode($_PE[1]);
    $aCol = $_PE;
    unset($aCol[0], $aCol[1], $aCol[2]); // 'diseases/00001/columns';
    $sColumnID = implode('/', $aCol);
    define('PAGE_TITLE', 'Edit settings for custom data column ' . $sColumnID . ' for ' . $sUnit . ' #' . $sParentID);
    define('LOG_EVENT', 'SharedColEdit');

    // Load appropriate user level for this gene.
    lovd_isAuthorized($sUnit, $sParentID);
    lovd_requireAUTH(LEVEL_CURATOR); // Will also stop user if gene given is fake.

    require ROOT_PATH . 'class/object_shared_columns.php';
    $_DATA = new LOVD_SharedColumn($sParentID, $sCategory . '/' . $sColumnID);
    $zData = $_DATA->loadEntry($sCategory . '/' . $sColumnID);
    // Remove columns based on form type?
    $aFormType = explode('|', $zData['form_type']);

    // Require form functions.
    require ROOT_PATH . 'inc-lib-form.php';

    if (POST) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('width', 'mandatory', 'description_form', 'description_legend_short', 'description_legend_full', 'public_view', 'public_add', 'edited_by', 'edited_date');
            if ($aFormType[2] == 'select') {
                $aFields[] = 'select_options';
            }

            // Prepare values.
            $_POST['edited_by'] = $_AUTH['id'];
            $_POST['edited_date'] = date('Y-m-d H:i:s');

            // Update entry.
            $_DATA->updateEntry($sCategory . '/' . $sColumnID, $_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited column ' . $sColumnID . ' for ' . $sUnit . ' ' . $sParentID);

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully edited column "' . $sColumnID . '" for ' . $sUnit . ' ' . $sParentID . '!', 'success');

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }

    } else {
        // Default values.
        $_POST = array_merge($_POST, $zData);
    }



    $_T->printHeader();
    $_T->printTitle();

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');
    lovd_includeJS('inc-js-columns.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        array('', '', 'submit', 'Edit custom data column'),
                      ));
    if ($aFormType[2] != 'select') {
        unset($aForm['options'], $aForm['options_note']);
    }
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 3 && ctype_digit($_PE[1]) && $_PE[2] == 'columns' && ACTION == 'order') {
    // URL: /diseases/00001/columns?order
    // Change order of enabled columns for this disease.

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'Change order of enabled custom data columns for disease #' . $nID);
    define('LOG_EVENT', 'ColumnOrder');
    $_T->printHeader();
    $_T->printTitle();

    // Load appropriate user level for this disease.
    lovd_isAuthorized('disease', $nID); // This call will make database queries if necessary.
    lovd_requireAUTH(LEVEL_CURATOR);

    $sUnit = 'disease';
    $sCategory = 'Phenotype';

    if (POST) {
        $_DB->beginTransaction();
        foreach ($_POST['columns'] as $nOrder => $sColID) {
            $nOrder ++; // Since 0 is the first key in the array.
            $_DB->query('UPDATE ' . TABLE_SHARED_COLS . ' SET col_order = ? WHERE ' . $sUnit . 'id = ? AND colid = ?', array($nOrder, $nID, $sCategory . '/' . $sColID));
        }
        $_DB->commit();

        // Write to log...
        lovd_writeLog('Event', LOG_EVENT, 'Updated the column order for ' . $sUnit . ' ' . $nID);

        // Thank the user...
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('Successfully updated the column order for ' . $sUnit . ' ' . $nID . '!', 'success');

        if (isset($_GET['in_window'])) {
            // We're in a new window, refresh opener en close window.
            print('      <SCRIPT type="text/javascript">setTimeout(\'opener.location.reload();self.close();\', 1000);</SCRIPT>' . "\n\n");
        } else {
            print('      <SCRIPT type="text/javascript">setTimeout(\'window.location.href=\\\'' . lovd_getInstallURL() . $_PE[0] . '/' . $nID . '\\\';\', 1000);</SCRIPT>' . "\n\n");
        }

        $_T->printFooter();
        exit;
    }

    $_T->printHeader();
    $_T->printTitle();

    // Retrieve column IDs in current order.
    $aColumns = $_DB->query('SELECT SUBSTRING(colid, LOCATE("/", colid)+1) FROM ' . TABLE_SHARED_COLS . ' WHERE ' . $sUnit . 'id = ? ORDER BY col_order ASC', array($nID))->fetchAllColumn();

    if (!count($aColumns)) {
        lovd_showInfoTable('No columns found!', 'stop');
        $_T->printFooter();
        exit;
    }

    lovd_showInfoTable('Below is a sorting list of all active columns. By clicking &amp; dragging the arrow next to the column up and down you can rearrange the columns. Re-ordering them will affect listings, detailed views and data entry forms in the same way.', 'information');

    // Form & table.
    print('      <TABLE cellpadding="0" cellspacing="0" class="sortable_head" style="width : 302px;"><TR><TH width="20">&nbsp;</TH><TH>Column ID</TH></TR></TABLE>' . "\n" .
          '      <FORM action="' . CURRENT_PATH . '?' . ACTION . (isset($_GET['in_window'])? '&amp;in_window' : '') . '" method="post">' . "\n" .
          '        <UL id="column_list" class="sortable" style="width : 300px; margin-top : 0px;">' . "\n");

    // Now loop the items in the order given.
    foreach ($aColumns as $sID) {
        print('        <LI><INPUT type="hidden" name="columns[]" value="' . $sID . '"><TABLE width="100%"><TR><TD class="handle" width="13" align="center"><IMG src="gfx/drag_vertical.png" alt="" title="Click and drag to sort" width="5" height="13"></TD><TD>' . $sID . '</TD></TR></TABLE></LI>' . "\n");
    }

    print('        </UL>' . "\n" .
          '        <INPUT type="submit" value="Save">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Cancel" onclick="' . (isset($_GET['in_window'])? 'self.close(); return false;' : 'window.location.href=\'' . lovd_getInstallURL() . $_PE[0] . '/' . $_PE[1] . '\'; return false;') . '" style="border : 1px solid #FF4422;">' . "\n" .
          '      </FORM>' . "\n\n");

?>
      <SCRIPT type='text/javascript'>
        $(function() {
          $('#column_list').sortable({
            containment: 'parent',
            tolerance: 'pointer',
            handle: 'TD.handle'
          });
          $('#column_list').disableSelection();
        });
      </SCRIPT>
<?php

    $_T->printFooter();
    exit;
}
?>
