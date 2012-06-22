<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-04-19
 * Modified    : 2012-06-21
 * For LOVD    : 3.0-beta-06
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
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





if (PATH_COUNT == 1 && !ACTION) {
    // URL: /links
    // View all entries.

    define('PAGE_TITLE', 'View custom links');
    $_T->printHeader();
    $_T->printTitle();

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_links.php';
    $_DATA = new LOVD_Link();
    $_DATA->viewList('Links');

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && !ACTION) {
    // URL: /links/001
    // View specific entry.

    $nID = sprintf('%03d', $_PE[1]);
    define('PAGE_TITLE', 'View custom link #' . $nID);
    $_T->printHeader();
    $_T->printTitle();

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_links.php';
    $_DATA = new LOVD_Link();
    $zData = $_DATA->viewEntry($nID);

    $sNavigation = '';
    // Authorized user (admin or manager) is logged in. Provide tools.
    $sNavigation = '<A href="' . CURRENT_PATH . '?edit">Edit custom link</A>';
    $sNavigation .= ' | <A href="' . CURRENT_PATH . '?delete">Delete custom link</A>';

    if ($sNavigation) {
        print('      <IMG src="gfx/trans.png" alt="" width="1" height="5"><BR>' . "\n");
        lovd_showNavigation($sNavigation);
    }

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 1 && ACTION == 'create') {
    // URL: /links?create
    // Create a new entry.

    define('PAGE_TITLE', 'Create a new custom link');
    define('LOG_EVENT', 'LinkCreate');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_links.php';
    $_DATA = new LOVD_Link();
    require ROOT_PATH . 'inc-lib-form.php';

    if (!empty($_POST)) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('name', 'pattern_text', 'replace_text', 'description', 'created_by', 'created_date');

            // Prepare values.
            $_POST['created_by'] = $_AUTH['id'];
            $_POST['created_date'] = date('Y-m-d H:i:s');

            $nID = $_DATA->insertEntry($_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Created custom link ' . $nID . ' - ' . $_POST['name'] . ' (' . $_POST['pattern_text'] . ')');

            // Add column.
            $aSuccess = array();
            foreach ($_POST['active_columns'] as $sCol) {
                if (!substr_count($sCol, '/')) {
                    // Skip the category lines in the selection list.
                    continue;
                }
                // Add custom link to column.
                $q = $_DB->query('INSERT INTO ' . TABLE_COLS2LINKS . ' VALUES (?, ?)', array($sCol, $nID), false);
                if (!$q) {
                    // Silent error.
                    lovd_writeLog('Error', LOG_EVENT, 'Custom link ' . $nID . ' - ' . $_POST['name'] . ' - could not be added to column ' . $sCol);
                } else {
                    $aSuccess[] = $sCol;
                }
            }
            if (count($aSuccess)) {
                lovd_writeLog('Event', LOG_EVENT, 'Custom link ' . $nID . ' - ' . $_POST['name'] . ' - successfully added to column(s) ' . implode(', ', $aSuccess));
            }

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PE[0] . '/' . $nID);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully created the custom link!', 'success');

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
        print('      To create a new custom link, please fill out the form below.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        array('', '', 'submit', 'Create custom link'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'edit') {
    // URL: /links/001?edit
    // Edit specific entry.

    $nID = sprintf('%03d', $_PE[1]);
    define('PAGE_TITLE', 'Edit custom link #' . $nID);
    define('LOG_EVENT', 'LinkEdit');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_links.php';
    $_DATA = new LOVD_Link();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    if (!empty($_POST)) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('name', 'pattern_text', 'replace_text', 'description', 'edited_by', 'edited_date');

            // Prepare values.
            $_POST['edited_by'] = $_AUTH['id'];
            $_POST['edited_date'] = date('Y-m-d H:i:s');

            $_DATA->updateEntry($nID, $_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited custom link ' . $nID . ' - ' . $_POST['name'] . ' (' . $_POST['pattern_text'] . ')');

            // Change active columns?
            // Columns the link is currently active for.
            $aCols = explode(';', $zData['active_columns_']);

            // Remove column.
            $aSuccess = array();
            foreach ($aCols AS $sCol) {
                if ($sCol && !in_array($sCol, $_POST['active_columns'])) {
                    // User has requested removal...
                    $q = $_DB->query('DELETE FROM ' . TABLE_COLS2LINKS . ' WHERE colid = ? AND linkid = ?', array($sCol, $nID), false);
                    if (!$q) {
                        // Silent error.
                        lovd_writeLog('Error', LOG_EVENT, 'Custom link ' . $nID . ' - ' . $_POST['name'] . ' - could not be removed from column ' . $sCol);
                    } else {
                        $aSuccess[] = $sCol;
                    }
                }
            }
            if (count($aSuccess)) {
                lovd_writeLog('Event', LOG_EVENT, 'Custom link ' . $nID . ' - ' . $_POST['name'] . ' - successfully removed from column(s) ' . implode(', ', $aSuccess));
            }

            // Add column.
            $aSuccess = array();
            foreach ($_POST['active_columns'] AS $sCol) {
                if (!substr_count($sCol, '/')) {
                    // Skip the category lines in the selection list.
                    continue;
                }
                if (!in_array($sCol, $aCols)) {
                    // Add custom link to column.
                    $q = $_DB->query('INSERT INTO ' . TABLE_COLS2LINKS . ' VALUES (?, ?)', array($sCol, $nID), false);
                    if (!$q) {
                        // Silent error.
                        lovd_writeLog('Error', LOG_EVENT, 'Custom link ' . $nID . ' - ' . $_POST['name'] . ' - could not be added to column ' . $sCol);
                    } else {
                        $aSuccess[] = $sCol;
                    }
                }
            }
            if (count($aSuccess)) {
                lovd_writeLog('Event', LOG_EVENT, 'Custom link ' . $nID . ' - ' . $_POST['name'] . ' - successfully added to column(s) ' . implode(', ', $aSuccess));
            }

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully edited the custom link!', 'success');

            $_T->printFooter();
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
        // Load connected columns.
        $_POST['active_columns'] = explode(';', $_POST['active_columns_']);
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
                        array('', '', 'submit', 'Edit custom link'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'delete') {
    // URL: /links/001?delete
    // Delete specific entry.

    $nID = sprintf('%03d', $_PE[1]);
    define('PAGE_TITLE', 'Delete custom link #' . $nID);
    define('LOG_EVENT', 'LinkDelete');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_links.php';
    $_DATA = new LOVD_Link();
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
            // This also deletes the entries in cols2links.
            $_DATA->deleteEntry($nID);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Deleted custom link ' . $nID . ' - ' . $zData['name'] . ' (' . $zData['pattern_text'] . ')');

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PE[0]);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully deleted the custom link!', 'success');

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
                    array('Deleting custom link', '', 'print', $zData['name'] . ' (' . $zData['pattern_text'] . ')'),
                    'skip',
                    array('Enter your password for authorization', '', 'password', 'password', 20),
                    array('', '', 'submit', 'Delete custom link'),
                  );
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}
?>
