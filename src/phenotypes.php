<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-05-23
 * Modified    : 2011-08-03
 * For LOVD    : 3.0-alpha-03
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *
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
    // URL: /phenotypes
    // View all entries.

    define('PAGE_TITLE', 'View phenotypes');
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    require ROOT_PATH . 'class/object_phenotypes.php';

    $q = lovd_queryDB('SELECT * FROM ' . TABLE_DISEASES);
    if ($q) {
        while($aDisease = mysql_fetch_assoc($q)) {
            $_GET['search_diseaseid'] = $aDisease['id'];
            $_DATA = new LOVD_Phenotype($aDisease['id']);
            $_DATA->setSortDefault('phenotypeid');
            print('<B>' . $aDisease['name'] . ' (<A href="diseases/' . $aDisease['id'] . '">' . $aDisease['symbol'] . '</A>)</B>');
            $_DATA->viewList(false, array('phenotypeid', 'individualid', 'diseaseid'), true, true);
        }
    } else {
        print('<BR>' . "\n");
        lovd_showInfoTable('No disease entries found.', 'stop');
    }

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && ctype_digit($_PATH_ELEMENTS[1]) && !ACTION) {
    // URL: /phenotypes/0000000001
    // View specific entry.

    $nID = str_pad($_PATH_ELEMENTS[1], 10, '0', STR_PAD_LEFT);
    define('PAGE_TITLE', 'View phenotype #' . $nID);
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    require ROOT_PATH . 'class/object_phenotypes.php';
    $_DATA = new LOVD_Phenotype();
    $zData = $_DATA->viewEntry($nID);

    $sNavigation = '';
    if ($_AUTH && $_AUTH['level'] >= LEVEL_MANAGER) {
        // Authorized user (admin or manager) is logged in. Provide tools.
        $sNavigation = '<A href="phenotypes/' . $nID . '?edit">Edit phenotype information</A>';
        $sNavigation .= ' | <A href="phenotypes/' . $nID . '?delete">Delete phenotype entry</A>';
    }

    if ($sNavigation) {
        print('      <IMG src="gfx/trans.png" alt="" width="1" height="5"><BR>' . "\n");
        lovd_showNavigation($sNavigation);
    }

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (empty($_PATH_ELEMENTS[1]) && ACTION == 'create') {
    // URL: /phenotypes?create
    // Create a new entry.

    define('LOG_EVENT', 'PhenotypeCreate');

    lovd_requireAUTH(LEVEL_SUBMITTER);
    
    if (isset($_GET['target']) && ctype_digit($_GET['target'])) {
        $_GET['target'] = str_pad($_GET['target'], 8, "0", STR_PAD_LEFT);
        if (mysql_num_rows(lovd_queryDB('SELECT * FROM ' . TABLE_INDIVIDUALS . ' WHERE id=?', array($_GET['target'])))) {
            $_POST['individualid'] = $_GET['target'];
            define('PAGE_TITLE', 'Create a new phenotype information entry for individual #' . $_GET['target']);
        } else {
            define('PAGE_TITLE', 'Create a new phenotype information entry');
            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('The individual ID given is not valid, please go to the desired individual entry and click on the "Add phenotype" button.', 'warning');
            require ROOT_PATH . 'inc-bot.php';
            exit;
        }
    } else {
        exit;
    }

    require ROOT_PATH . 'class/object_phenotypes.php';
    if (isset($_POST['diseaseid'])) {
        $_DATA = new LOVD_Phenotype($_POST['diseaseid']);
    } else {
        $_DATA = new LOVD_Phenotype();
    }
    require ROOT_PATH . 'inc-lib-form.php';

    if (count($_POST) > 2) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array_merge(
                            array('diseaseid', 'individualid', 'ownerid', 'statusid', 'created_by', 'created_date'),
                            $_DATA->buildFields());

            // Prepare values.
            $_POST['individualid'] = $_GET['target'];
            $_POST['ownerid'] = ($_AUTH['level'] >= LEVEL_CURATOR? $_POST['ownerid'] : $_AUTH['id']);
            $_POST['statusid'] = ($_AUTH['level'] >= LEVEL_CURATOR? $_POST['statusid'] : STATUS_HIDDEN);
            $_POST['created_by'] = $_AUTH['id'];
            $_POST['created_date'] = date('Y-m-d H:i:s');

            $nID = $_DATA->insertEntry($_POST, $aFields);

            // Write to log...
            // FIXME; meer info?
            lovd_writeLog('Event', LOG_EVENT, 'Created phenotype information entry ' . $nID);

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PATH_ELEMENTS[0] . '/' . $nID);

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('Successfully created the phenotype information entry!', 'success');

            require ROOT_PATH . 'inc-bot.php';
            exit;
        }

    } else {
        // Default values.
        $_DATA->setDefaultValues();
    }



    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    // FIXME; volgens mij staat dit op de verkeerde plek. Moet dit niet bovenaan staan, nog voor de controle op $_POST??? Zo gebeurt het overal voor formulieren die een eerste stap nodig hebben.
    if (!isset($_POST['diseaseid'])) {
        // FIXME; select * is overdreven.
        $sSQL = 'SELECT * FROM ' . TABLE_DISEASES . ' AS d INNER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (d.id = i2d.diseaseid) WHERE i2d.individualid = ?';
        $q = lovd_queryDB($sSQL, array($_GET['target']));
        $aSelectDiseases = array();
        if ($q) {
            while ($aDisease = mysql_fetch_assoc($q)) {
                $aSelectDiseases[$aDisease['id']] = $aDisease['name'] . ' (' . $aDisease['symbol'] . ')';
            }
        } else {
            // FIXME; dit is raar voor de gebruiker, een halve pagina zonder foutmelding???
            //  Gebruik hier de mogelijkheden van lovd_queryDB() meer.
            exit;
        }

        // FIXME; ik heb aan dit stuk wat dingen zitten wijzigen, gebaseerd op hoe 't gedaan is in transcript?create. Probeer dingen standaard te houden!!! Anders moeten we straks weer alles gelijk gaan trekken.
        if (GET) {
            print('      Please select the disease to which the phenotype information is related.<BR>' . "\n" .
                  '      <BR>' . "\n\n");
        }

        // Table.
        print('      <FORM action="' . CURRENT_PATH . '?create&amp;target=' . $_GET['target'] . '" method="post">' . "\n");

        // Array which will make up the form table.
        $aForm = array(
                        array('POST', '', '', '', '50%', '14', '50%'),
                        array('Select the disease', '', 'select', 'diseaseid', 1, $aSelectDiseases, '--Select--', false, false),
                        array('', '', 'submit', 'Continue &raquo;'),
                      );
        lovd_viewForm($aForm);

        print('</FORM>' . "\n\n");

        require ROOT_PATH . 'inc-bot.php';
        exit;
    }

    if (GET) {
        print('      To create a new phenotype information entry, please fill out the form below.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');
    // FIXME; ik suggereer 'm inc-js-custom_links.php te noemen.
    lovd_includeJS('inc-js-insert-custom-links.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?create&amp;target=' . $_GET['target'] . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        array('', '', 'submit', 'Create phenotype information entry'),
                      ));
    lovd_viewForm($aForm);
    // FIXME; dit ziet er in de broncode heel raar uit. Ik ben nog steeds voorstander van hidden inputs bovenaan plaatsen.
    print('<INPUT type="hidden" name="diseaseid" value="' . $_POST['diseaseid'] . '">' . "\n" .
          '</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && ctype_digit($_PATH_ELEMENTS[1]) && ACTION == 'edit') {
    // URL: /phenotypes/0000000001?edit
    // Edit an entry.

    $nID = str_pad($_PATH_ELEMENTS[1], 10, '0', STR_PAD_LEFT);
    define('PAGE_TITLE', 'Edit an phenotype information entry');
    define('LOG_EVENT', 'PhenotypeEdit');

    // FIXME; hier moet een goede controle komen, wanneer lager is toegestaan.
    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_phenotypes.php';
    $_DATA = new LOVD_Phenotype();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    if (!empty($_POST)) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array_merge(
                            array('ownerid', 'statusid', 'edited_by', 'edited_date'),
                            $_DATA->buildFields());

            // Prepare values.
            // FIXME; ik ben er voor om zoiets in checkFields() te doen en het hier dan schoon te houden.
            $_POST['ownerid'] = ($_AUTH['level'] >= LEVEL_CURATOR? $_POST['ownerid'] : $_AUTH['id']);
            $_POST['statusid'] = ($_AUTH['level'] >= LEVEL_CURATOR? $_POST['statusid'] : STATUS_HIDDEN);
            $_POST['edited_by'] = $_AUTH['id'];
            $_POST['edited_date'] = date('Y-m-d H:i:s');

            // FIXME: implement versioning in updateEntry!
            $_DATA->updateEntry($nID, $_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited phenotype information entry ' . $nID);

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PATH_ELEMENTS[0] . '/' . $nID);

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('Successfully edited the phenotype information entry!', 'success');

            require ROOT_PATH . 'inc-bot.php';
            exit;
        } else {
            // Because we're sending the data back to the form, I need to unset the password field!
            unset($_POST['password']);
        }

    } else {
        // Load current values.
        $_POST = array_merge($_POST, $zData);
    }



    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    if (GET) {
        print('      To edit an phenotype information entry, please fill out the form below.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');
    lovd_includeJS('inc-js-insert-custom-links.php');

    // Table.
    print('      <FORM action="' . $_PATH_ELEMENTS[0] . '/' . $nID . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        array('', '', 'submit', 'Edit phenotype information entry'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && ctype_digit($_PATH_ELEMENTS[1]) && ACTION == 'delete') {
    // URL: /phenotypes/0000000001?delete
    // Drop specific entry.

    $nID = str_pad($_PATH_ELEMENTS[1], 10, '0', STR_PAD_LEFT);
    define('PAGE_TITLE', 'Delete phenotype information entry ' . $nID);
    define('LOG_EVENT', 'PhenotypeDelete');

    // FIXME; hier moet een goede controle komen, wanneer lager is toegestaan.
    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_phenotypes.php';
    $_DATA = new LOVD_Phenotype();
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
            $_DATA->deleteEntry($nID);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Deleted phenotype information entry ' . $nID . ' (Owner: ' . $zData['owner'] . ')');

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . 'phenotypes');

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('Successfully deleted the phenotype information entry!', 'success');

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
                        array('POST', '', '', '', '40%', '14', '60%'),
                        array('Deleting phenotype information entry', '', 'print', $nID . ' (Owner: ' . $zData['owner'] . ')'),
                        'skip',
                        array('Enter your password for authorization', '', 'password', 'password', 20),
                        array('', '', 'submit', 'Delete phenotype information entry'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}
?>
