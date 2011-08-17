<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-05-23
 * Modified    : 2011-08-17
 * For LOVD    : 3.0-alpha-04
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
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





if (empty($_PATH_ELEMENTS[1]) && !ACTION) {
    // URL: /phenotypes
    // View all entries.

    define('PAGE_TITLE', 'View phenotypes');
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    require ROOT_PATH . 'class/object_phenotypes.php';

    $q = lovd_queryDB_Old('SELECT * FROM ' . TABLE_DISEASES);
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

    // Load appropiate user level for this phenotype entry.
    lovd_isAuthorized('phenotype', $nID);

    require ROOT_PATH . 'class/object_phenotypes.php';
    $_DATA = new LOVD_Phenotype();
    $zData = $_DATA->viewEntry($nID);

    $sNavigation = '';
    if ($_AUTH && $_AUTH['level'] >= LEVEL_OWNER) {
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

    lovd_requireAUTH();
    
    if (isset($_GET['target']) && ctype_digit($_GET['target'])) {
        $_GET['target'] = str_pad($_GET['target'], 8, "0", STR_PAD_LEFT);
        if (mysql_num_rows(lovd_queryDB_Old('SELECT * FROM ' . TABLE_INDIVIDUALS . ' WHERE id=?', array($_GET['target'])))) {
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

    require ROOT_PATH . 'inc-lib-form.php';
    lovd_errorClean();

    if (isset($_GET['diseaseid'])) {
        $_POST['diseaseid'] = $_GET['diseaseid'];
    }
    if (isset($_POST['diseaseid'])) {
        $_POST['diseaseid'] = str_pad($_POST['diseaseid'], 5, "0", STR_PAD_LEFT);
        if (!ctype_digit($_POST['diseaseid']) || !mysql_num_rows(lovd_queryDB_Old('SELECT i2d.diseaseid FROM ' . TABLE_IND2DIS . ' AS i2d INNER JOIN ' . TABLE_SHARED_COLS . ' AS sc USING(diseaseid) WHERE i2d.individualid = ? AND i2d.diseaseid = ?', array($_POST['individualid'], $_POST['diseaseid'])))) {
            lovd_errorAdd('diseaseid', htmlspecialchars($_POST['diseaseid']) . ' is not a valid disease');
        }
    }

    require ROOT_PATH . 'class/object_phenotypes.php';
    if (isset($_POST['diseaseid'])) {
        $_DATA = new LOVD_Phenotype($_POST['diseaseid']);
    } else {
        $_DATA = new LOVD_Phenotype();
    }

    if (empty($_POST['diseaseid']) || lovd_error()) {
        $sSQL = 'SELECT d.id, d.name, d.symbol FROM ' . TABLE_DISEASES . ' AS d INNER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (d.id = i2d.diseaseid) INNER JOIN ' . TABLE_SHARED_COLS . ' AS sc ON (d.id = sc.diseaseid) WHERE i2d.individualid = ? GROUP BY d.id ORDER BY d.name';
        $q = lovd_queryDB_Old($sSQL, array($_POST['individualid']));
        $aSelectDiseases = array();
        if (mysql_num_rows($q)) {
            while ($aDisease = mysql_fetch_assoc($q)) {
                $aSelectDiseases[$aDisease['id']] = $aDisease['name'] . ' (' . $aDisease['symbol'] . ')';
            }
        } else {
            // Only possible if the user types in a wrong individualid in the URL.
            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('The individual #' . $_POST['individualid'] . ' does not have any disease entries yet, please go <A href="individuals/' . $_POST['individualid'] . '?edit">here</A> and add the disease(s) first.', 'warning');
            require ROOT_PATH . 'inc-bot.php';
            exit;
        }

        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader(PAGE_TITLE);

        if (!lovd_error()) {
            print('      Please select the disease to which the phenotype information is related.<BR>' . "\n" .
                  '      <BR>' . "\n\n");
        }

        lovd_errorPrint();

        // Table.
        print('      <FORM id="phenotypeCreate" action="' . CURRENT_PATH . '?create&amp;target=' . $_POST['individualid'] . '" method="post">' . "\n");

        // Array which will make up the form table.
        $aForm = array(
                        array('POST', '', '', '', '50%', '14', '50%'),
                        array('Select the disease', '', 'select', 'diseaseid', 1, $aSelectDiseases, '--Select--', false, false),
                        array('', '', 'submit', 'Continue &raquo;'),
                      );
        lovd_viewForm($aForm);

        print('</FORM>' . "\n\n" .
              '<SCRIPT>' . "\n" .
              '  if ($(\'#phenotypeCreate option\').size() == 2) { $(\'#phenotypeCreate select\')[0].selectedIndex = 1; $(\'#phenotypeCreate\').submit(); }' . "\n" .
              '</SCRIPT>' . "\n\n");

        require ROOT_PATH . 'inc-bot.php';
        exit;
    }

    if (count($_POST) > 2) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array_merge(
                            array('diseaseid', 'individualid', 'ownerid', 'statusid', 'created_by', 'created_date'),
                            $_DATA->buildFields());

            // Prepare values.
            $_POST['ownerid'] = ($_AUTH['level'] >= LEVEL_CURATOR? $_POST['ownerid'] : $_AUTH['id']);
            $_POST['statusid'] = ($_AUTH['level'] >= LEVEL_CURATOR? $_POST['statusid'] : STATUS_HIDDEN);
            $_POST['created_by'] = $_AUTH['id'];
            $_POST['created_date'] = date('Y-m-d H:i:s');

            $nID = $_DATA->insertEntry($_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Created phenotype information entry ' . $nID . ' for individual ' . $_POST['individualid'] . ' related to disease ' . $_POST['diseaseid']);

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
                        array('', '', 'submit', 'Create phenotype information entry'),
                      ));
    lovd_viewForm($aForm);

    print("\n" .
          '        <INPUT type="hidden" name="diseaseid" value="' . $_POST['diseaseid'] . '">' . "\n" .
          '      </FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && ctype_digit($_PATH_ELEMENTS[1]) && ACTION == 'edit') {
    // URL: /phenotypes/0000000001?edit
    // Edit an entry.

    $nID = str_pad($_PATH_ELEMENTS[1], 10, '0', STR_PAD_LEFT);
    define('PAGE_TITLE', 'Edit an phenotype information entry');
    define('LOG_EVENT', 'PhenotypeEdit');

    // Require manager clearance.
    require ROOT_PATH . 'inc-lib-columns.php';
    $aTableInfo = lovd_getTableInfoByCategory('Phenotype');

    if (!$aTableInfo) {
        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader('Re-order columns');
        lovd_showInfoTable('The specified category does not exist!', 'stop');
        require ROOT_PATH . 'inc-bot.php';
        exit;
    }

    // Load appropiate user level for this phenotype entry.
    lovd_isAuthorized('phenotype', $nID);
    lovd_requireAUTH(LEVEL_OWNER);

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
            // Ivar: checkFields hoort de data niet aan te passen, maar alleen te checken, dus ben het niet helemaal met je eens.
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
    lovd_includeJS('inc-js-custom_links.php');

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
