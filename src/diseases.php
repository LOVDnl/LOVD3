<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-07-27
 * Modified    : 2011-02-22
 * For LOVD    : 3.0-pre-17
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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





if (empty($_PATH_ELEMENTS[1]) && !ACTION) {
    // URL: /diseases
    // View all entries.

    define('PAGE_TITLE', 'View diseases');
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    require ROOT_PATH . 'class/object_diseases.php';
    $_DATA = new LOVD_Disease();
    $_DATA->viewList();

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && preg_match('/^[0-9]+$/', $_PATH_ELEMENTS[1]) && !ACTION) {
    // URL: /diseases/00001
    // View specific entry.

    $nID = $_PATH_ELEMENTS[1];
    define('PAGE_TITLE', 'View disease #' . $nID);
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    require ROOT_PATH . 'class/object_diseases.php';
    $_DATA = new LOVD_Disease();
    $zData = $_DATA->viewEntry($nID);

    $sNavigation = '';
    if ($_AUTH && $_AUTH['level'] >= LEVEL_MANAGER) {
        // Authorized user (admin or manager) is logged in. Provide tools.
        $sNavigation = '<A href="diseases/' . $nID . '?edit">Edit disease information</A>';
        $sNavigation .= ' | <A href="diseases/' . $nID . '?delete">Delete disease entry</A>';
    }

    if ($sNavigation) {
        print('      <IMG src="gfx/trans.png" alt="" width="1" height="5"><BR>' . "\n");
        lovd_showNavigation($sNavigation);
    }

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && !preg_match('/^[0-9]+$/', $_PATH_ELEMENTS[1]) && !ACTION) {
    // URL: /diseases/DMD
    // Try to find a disease by its abbreviation and forward.
    // When we have multiple hits, refer to listView.

    $sID = $_PATH_ELEMENTS[1];
    $q = lovd_queryDB('SELECT id FROM ' . TABLE_DISEASES . ' WHERE symbol = ?', array($sID), true);
    $n = mysql_num_rows($q);
    @list($nID) = mysql_fetch_row($q);
    if (!$n) {
        define('PAGE_TITLE', 'View disease');
        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader(PAGE_TITLE);
        lovd_showInfoTable('No such ID!', 'stop');
        require ROOT_PATH . 'inc-bot.php';
    } elseif ($n == 1) {
        header('Location: ' . lovd_getInstallURL() . 'diseases/' . $nID);
    } else {
        // Multiple hits. This forward would allow for even more hits,
        // because this search method below works on partial matches.
        header('Location: ' . lovd_getInstallURL() . 'diseases?search_symbol=' . rawurlencode($sID));
    }
    exit;
}





if (empty($_PATH_ELEMENTS[1]) && ACTION == 'create') {
    // URL: /diseases?create
    // Create a new entry.

    define('PAGE_TITLE', 'Create a new disease information entry');
    define('LOG_EVENT', 'DiseaseCreate');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

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

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Created disease information entry ' . $nID . ' - ' . $_POST['symbol'] . ' (' . $_POST['name'] . ')');

            // Add genes.
            $aSuccess = array();
            foreach ($_POST['active_genes'] as $sGene) {
                // Add gene to disease.
                $q = lovd_queryDB('INSERT INTO ' . TABLE_GEN2DIS . ' VALUES (?, ?)', array($sGene, $nID));
                if (!$q) {
                    // Silent error.
                    lovd_writeLog('Error', LOG_EVENT, 'Disease information entry ' . $nID . ' - ' . $_POST['symbol'] . ' - could not be added to gene ' . $sGene);
                } else {
                    $aSuccess[] = $sGene;
                }
            }
            if (count($aSuccess)) {
                lovd_writeLog('Event', LOG_EVENT, 'Disease information entry ' . $nID . ' - ' . $_POST['symbol'] . ' - successfully added to gene(s) ' . implode(', ', $aSuccess));
            }

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PATH_ELEMENTS[0] . '/' . $nID);

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('Successfully created the disease information entry!', 'success');

            require ROOT_PATH . 'inc-bot.php';
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']); // Currently does not have an effect here.
        }

    } else {
        // Default values.
        $_DATA->setDefaultValues();
    }



    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    if (GET) {
        print('      To create a new disease information entry, please fill out the form below.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    // Table.
    print('      <FORM action="' . $_PATH_ELEMENTS[0] . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        array('', '', 'submit', 'Create disease information entry'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && preg_match('/^[0-9]+$/', $_PATH_ELEMENTS[1]) && ACTION == 'edit') {
    // URL: /diseases/00001?edit
    // Edit a specific entry.

    $nID = $_PATH_ELEMENTS[1];
    define('PAGE_TITLE', 'Edit disease information entry #' . $nID);
    define('LOG_EVENT', 'DiseaseEdit');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_diseases.php';
    $_DATA = new LOVD_Disease();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    if (!empty($_POST)) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('symbol', 'name', 'id_omim', 'edited_by', 'edited_date');

            // Prepare values.
            $_POST['edited_by'] = $_AUTH['id'];
            $_POST['edited_date'] = date('Y-m-d H:i:s');

            $_DATA->updateEntry($zData['id'], $_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited disease information entry ' . $nID . ' - ' . $_POST['symbol'] . ' (' . $_POST['name'] . ')');

            // Change linked genes?
            // Genes the disease is currently linked to.
            $aGenes = explode(';', $zData['active_genes_']);

            // Remove genes.
            $aSuccess = array();
            foreach ($aGenes AS $sGene) {
                if ($sGene && !in_array($sGene, $_POST['active_genes'])) {
                    // User has requested removal...
                    $q = lovd_queryDB('DELETE FROM ' . TABLE_GEN2DIS . ' WHERE geneid = ? AND diseaseid = ?', array($sGene, $zData['id']));
                    if (!$q) {
                        // Silent error.
                        lovd_writeLog('Error', LOG_EVENT, 'Disease information entry ' . $nID . ' - ' . $_POST['symbol'] . ' - could not be removed from gene ' . $sGene);
                    } else {
                        $aSuccess[] = $sGene;
                    }
                }
            }
            if (count($aSuccess)) {
                lovd_writeLog('Event', LOG_EVENT, 'Disease information entry ' . $nID . ' - ' . $_POST['symbol'] . ' - successfully removed from gene(s) ' . implode(', ', $aSuccess));
            }

            // Add genes.
            $aSuccess = array();
            foreach ($_POST['active_genes'] as $sGene) {
                if (!in_array($sGene, $aGenes)) {
                    // Add gene to disease.
                    $q = lovd_queryDB('INSERT INTO ' . TABLE_GEN2DIS . ' VALUES (?, ?)', array($sGene, $nID));
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
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PATH_ELEMENTS[0] . '/' . $nID);

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('Successfully edited the disease information entry!', 'success');

            require ROOT_PATH . 'inc-bot.php';
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']); // Currently does not have an effect here.
        }

    } else {
        // Default values.
        foreach ($zData as $key => $val) {
            $_POST[$key] = $val;
        }
        // Load connected genes.
        $_POST['active_genes'] = explode(';', $_POST['active_genes_']);
    }



    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    // Table.
    print('      <FORM action="' . $_PATH_ELEMENTS[0] . '/' . $nID . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        array('', '', 'submit', 'Edit disease information entry'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && preg_match('/^[0-9]+$/', $_PATH_ELEMENTS[1]) && ACTION == 'delete') {
    // URL: /diseases/00001?delete
    // Drop specific entry.

    $nID = $_PATH_ELEMENTS[1];
    define('PAGE_TITLE', 'Delete disease information entry #' . $nID);
    define('LOG_EVENT', 'DiseaseDelete');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_diseases.php';
    $_DATA = new LOVD_Disease();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    if (!empty($_POST)) {
        lovd_errorClean();

        // Mandatory fields.
        if (empty($_POST['password'])) {
            lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');
        }

        // User had to enter his/her password for authorization.
        if ($_POST['password'] && md5($_POST['password']) != $_AUTH['password']) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            // Query text.
            // This also deletes the entries in gen2dis.
            // FIXME; implement deleteEntry()
            $sSQL = 'DELETE FROM ' . TABLE_DISEASES . ' WHERE id = ?';
            $aSQL = array($zData['id']);
            $q = lovd_queryDB($sSQL, $aSQL, true);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Deleted disease information entry ' . $nID . ' - ' . $zData['symbol'] . ' (' . $zData['name'] . ')');

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . 'diseases');

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('Successfully deleted the disease information entry!', 'success');

            require ROOT_PATH . 'inc-bot.php';
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    }



    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    lovd_errorPrint();

    // Table.
    print('      <FORM action="' . $_PATH_ELEMENTS[0] . '/' . $nID . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 array(
                        array('POST', '', '', '', '50%', '14', '50%'),
                        array('Deleting disease information entry', '', 'print', $zData['symbol'] . ' (' . $zData['name'] . ')'),
                        'skip',
                        array('Enter your password for authorization', '', 'password', 'password', 20),
                        array('', '', 'submit', 'Delete disease information entry'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}
?>
