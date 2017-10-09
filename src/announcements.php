<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-08-26
 * Modified    : 2017-08-09
 * For LOVD    : 3.0-20
 *
 * Copyright   : 2004-2017 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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
define('TAB_SELECTED', 'setup');
require ROOT_PATH . 'inc-init.php';

if ($_AUTH) {
    // If authorized, check for updates.
    require ROOT_PATH . 'inc-upgrade.php';
}





if (PATH_COUNT == 1 && !ACTION) {
    // URL: /announcements
    // View all entries.

    define('PAGE_TITLE', 'System announcements');
    $_T->printHeader();
    $_T->printTitle();

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_announcements.php';
    $_DATA = new LOVD_Announcement();
    $_DATA->viewList('Announcements');

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && !ACTION) {
    // URL: /announcements/00001
    // View specific entry.

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'Announcement #' . $nID);
    $_T->printHeader();
    $_T->printTitle();

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_announcements.php';
    $_DATA = new LOVD_Announcement();

    $zData = $_DATA->viewEntry($nID);

    $aNavigation = array();
    // Authorized user is logged in. Provide tools.
    $aNavigation[CURRENT_PATH . '?edit'] = array('menu_edit.png', 'Edit announcement', 1);
    $aNavigation[CURRENT_PATH . '?delete'] = array('cross.png', 'Delete announcement', 1);

    lovd_showJGNavigation($aNavigation, 'Announcements');

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 1 && ACTION == 'create') {
    // URL: /announcements?create
    // URL: /announcements?register
    // Create a new announcement.

    define('LOG_EVENT', 'AnnouncementCreate');
    define('PAGE_TITLE', 'Create a new announcement');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_announcements.php';
    $_DATA = new LOVD_Announcement();
    require ROOT_PATH . 'inc-lib-form.php';

    if (!empty($_POST)) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('type', 'announcement', 'start_date', 'end_date', 'lovd_read_only', 'created_by', 'created_date');

            // Prepare values.
            if (empty($_POST['start_date'])) {
                $_POST['start_date'] = date('Y-m-d H:i:s');
            }
            if (empty($_POST['end_date'])) {
                $_POST['end_date'] = '9999-12-31 23:59:59';
            }
            $_POST['created_by'] = $_AUTH['id'];
            $_POST['created_date'] = date('Y-m-d H:i:s');

            $nID = $_DATA->insertEntry($_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Created announcement ' . $nID . ' (' . $_POST['type'] . ') - ' . lovd_shortenString(strip_tags($_POST['announcement']), 150));

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PE[0] . '/' . $nID);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully created the announcement!', 'success');

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
        print('      To create a new announcement, please fill out the form below. Announcements will appear on top of the screen for all users and visitors of this LOVD installation.<BR>' . "\n" .
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
            array('', '', 'submit', 'Create announcement'),
        ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'edit') {
    // URL: /announcements/00001?edit
    // Edit specific entry.

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'Edit announcement #' . $nID);
    define('LOG_EVENT', 'AnnouncementEdit');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_announcements.php';
    $_DATA = new LOVD_Announcement();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    if (!empty($_POST)) {
        lovd_errorClean();

        $_DATA->checkFields($_POST, $zData);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('type', 'announcement', 'start_date', 'end_date', 'lovd_read_only', 'edited_by', 'edited_date');

            // Prepare values.
            if (empty($_POST['start_date'])) {
                $_POST['start_date'] = date('Y-m-d H:i:s');
            }
            if (empty($_POST['end_date'])) {
                $_POST['end_date'] = '9999-12-31 23:59:59';
            }
            $_POST['edited_by'] = $_AUTH['id'];
            $_POST['edited_date'] = date('Y-m-d H:i:s');

            $_DATA->updateEntry($nID, $_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited announcement ' . $nID . ' (' . $_POST['type'] . ') - ' . lovd_shortenString(strip_tags($_POST['announcement']), 150));

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully edited the announcement!', 'success');

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
                        array('', '', 'submit', 'Edit announcement'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'delete') {
    // URL: /announcements/00001?delete
    // Delete a specific announcement.

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'Delete announcement #' . $nID);
    define('LOG_EVENT', 'AnnouncementDelete');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_announcements.php';
    $_DATA = new LOVD_Announcement();
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
            lovd_writeLog('Event', LOG_EVENT, 'Deleted announcement ' . $nID . ' (' . $zData['type'] . ') - ' . lovd_shortenString(strip_tags($zData['announcement']), 150));

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PE[0]);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully deleted the announcement!', 'success');

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
        array('Deleting announcement', '', 'print', lovd_shortenString(htmlspecialchars($zData['announcement']), 150)),
        'skip',
        array('Enter your password for authorization', '', 'password', 'password', 20),
        array('', '', 'submit', 'Delete announcement'),
    );
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}
?>
