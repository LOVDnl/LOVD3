<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-01-14
 * Modified    : 2012-09-19
 * For LOVD    : 3.0-beta-09
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
    // URL: /users
    // View all entries.

    // Managers are allowed to download this list...
    if ($_AUTH['level'] >= LEVEL_MANAGER) {
        define('FORMAT_ALLOW_TEXTPLAIN', true);
    }

    define('PAGE_TITLE', 'View user accounts');
    $_T->printHeader();
    $_T->printTitle();

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_users.php';
    $_DATA = new LOVD_User();
    $_DATA->viewList('Users', array(), false, false, (bool) ($_AUTH['level'] >= LEVEL_MANAGER));

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && !ACTION) {
    // URL: /users/00001
    // View specific entry.

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'View user account #' . $nID);
    $_T->printHeader();
    $_T->printTitle();

    // FIXME; we need to think about this. To create a public submitters list, will we have a modified viewList() without viewEntry() or what?
    // Allow everybody to see certain details, but only managers to view all? Hide username, certain info from others?
    // Require valid user.
    lovd_requireAUTH();

    lovd_isAuthorized('gene', $_AUTH['curates']); // Enables LEVEL_COLLABORATOR and LEVEL_CURATOR for object_users.php.

    if ($nID == '00000') {
        $nID = -1;
    } elseif ($nID != $_AUTH['id']) {
        // Require manager clearance, if user is not viewing himself.
        lovd_requireAUTH(LEVEL_MANAGER);
    }

    require ROOT_PATH . 'class/object_users.php';
    $_DATA = new LOVD_User();
    // Increase the max group_concat() length, so that curators of many many genes still have all genes mentioned here.
    $_DB->query('SET group_concat_max_len = 50000');
    $zData = $_DATA->viewEntry($nID);

    $aNavigation = array();
    // This all assumes we are LEVEL_MANAGER already.
    if ($_AUTH['level'] > $zData['level']) {
        // Authorized user is logged in. Provide tools.
        $aNavigation[CURRENT_PATH . '?edit'] = array('menu_edit.png', 'Edit user', 1);
        if ($zData['active']) {
            $aNavigation[CURRENT_PATH . '?boot'] = array('', 'Force user log out', 1);
        }
        if ($zData['locked']) {
            $aNavigation[CURRENT_PATH . '?unlock'] = array('check.png', 'Unlock user', 1);
        } else {
            $aNavigation[CURRENT_PATH . '?lock'] = array('status_locked.png', 'Lock user', 1); // FIXME; this image is actually too small but it doesn't look too bad.
        }
        $aNavigation[CURRENT_PATH . '?delete'] = array('cross.png', 'Delete user', 1);
    } elseif ($_AUTH['id'] == $nID) {
        // Viewing himself!
        $aNavigation[CURRENT_PATH . '?edit'] = array('menu_edit.png', 'Update your registration', 1);
        $aNavigation['download/all/mine']    = array('menu_save.png', 'Download all my data', 1);
    }
    lovd_showJGNavigation($aNavigation, 'Users');



    if ($_AUTH['level'] >= LEVEL_MANAGER) {
        print('<BR><BR>' . "\n\n");
        $_T->printTitle('Log entries by this user', 'H4');

        require ROOT_PATH . 'class/object_logs.php';
        $_DATA = new LOVD_Log();
        $_GET['page_size'] = 10;
        $_GET['search_userid'] = $nID;
        $_DATA->viewList('Logs_for_Users_VE', array('user_', 'del'), true);
    }

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 1 && ACTION == 'create') {
    // URL: /users?create
    // Create a new entry.

    define('PAGE_TITLE', 'Create a new user account');
    define('LOG_EVENT', 'UserCreate');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_users.php';
    $_DATA = new LOVD_User();
    require ROOT_PATH . 'inc-lib-form.php';

    if (!empty($_POST)) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('name', 'institute', 'department', 'telephone', 'address', 'city', 'countryid', 'email', 'reference', 'username', 'password', 'password_force_change', 'level', 'allowed_ip', 'login_attempts', 'created_by', 'created_date');

            // Prepare values.
            $_POST['password'] = lovd_createPasswordHash($_POST['password_1']);
            $_POST['login_attempts'] = ($_POST['locked']? 3 : 0);
            $_POST['created_by'] = $_AUTH['id'];
            $_POST['created_date'] = date('Y-m-d H:i:s');

            $nID = $_DATA->insertEntry($_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Created user ' . $nID . ' - ' . $_POST['username'] . ' (' . $_POST['name'] . ') - with level ' . $_SETT['user_levels'][$_POST['level']]);

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PE[0] . '/' . $nID);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully created the user account!', 'success');

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password'], $_POST['password_1'], $_POST['password_2']);
        }

    } else {
        // Default values.
        $_DATA->setDefaultValues();
    }



    $_T->printHeader();
    $_T->printTitle();

    if (GET) {
        print('      To create a new user, please fill out the form below.<BR>' . "\n" .
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
                        array('', '', 'submit', 'Create user'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'edit') {
    // URL: /users/00001?edit
    // Edit specific entry.

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'Edit user account #' . $nID);
    define('LOG_EVENT', 'UserEdit');

    // Require valid user.
    lovd_requireAUTH();

    require ROOT_PATH . 'class/object_users.php';
    $_DATA = new LOVD_User();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    // Require special clearance, if user is not editing himself.
    // Neccessary level depends on level of user. Special case.
    if ($nID != $_AUTH['id'] && $zData['level'] >= $_AUTH['level']) {
        // Simple solution: if level is not lower than what you have, you're out.
        // This is a hack-attempt.
        $_T->printHeader();
        $_T->printTitle();
        lovd_writeLog('Error', 'HackAttempt', 'Tried to edit user ID ' . $nID . ' (' . $_SETT['user_levels'][$zData['level']] . ')');
        lovd_showInfoTable('Not allowed to edit this user. This event has been logged.', 'stop');
        $_T->printFooter();
        exit;
    }

    if (!empty($_POST)) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('name', 'institute', 'department', 'telephone', 'address', 'city', 'countryid', 'email', 'reference', 'password_force_change', 'level', 'allowed_ip', 'login_attempts', 'edited_by', 'edited_date');

            // Prepare values.
            // In case the password is getting changed...
            if ($_POST['password_1']) {
                $_POST['password'] = lovd_createPasswordHash($_POST['password_1']);
                $aFields[] = 'password';
            }
            $_POST['login_attempts'] = (!empty($_POST['locked'])? 3 : 0);
            $_POST['edited_by'] = $_AUTH['id'];
            $_POST['edited_date'] = date('Y-m-d H:i:s');
            // Editing yourself...
            if ($nID == $_AUTH['id']) {
                unset($aFields[array_search('password_force_change', $aFields)], $aFields[array_search('level', $aFields)], $aFields[array_search('login_attempts', $aFields)]);
            }

            $_DATA->updateEntry($nID, $_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited user ' . $nID . ' - ' . $zData['username'] . ' (' . $_POST['name'] . ') - with level ' . $_SETT['user_levels'][(!empty($_POST['level'])? $_POST['level'] : $zData['level'])]);

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully edited the user account!', 'success');

            // Change password, if requested.
            if ($nID == $_AUTH['id'] && !empty($_POST['password_1'])) {
                // Was already hashed!
                $_SESSION['auth']['password'] = $_POST['password'];
            }

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password'], $_POST['password_1'], $_POST['password_2']);
        }

    } else {
        // Load current values.
        $_POST = array_merge($_POST, $zData);
        $_POST['password'] = '';
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
                        array('', '', 'submit', 'Edit user'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'change_password') {
    // URL: /users/00001?change_password
    // Change a user's password.

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'Change password for user account #' . $nID);
    define('LOG_EVENT', 'UserResetPassword');

    // Require valid user.
    lovd_requireAUTH();

    require ROOT_PATH . 'class/object_users.php';
    $_DATA = new LOVD_User();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    // Require special clearance, if user is not editing himself.
    // Neccessary level depends on level of user. Special case.
    if ($nID != $_AUTH['id'] && $zData['level'] >= $_AUTH['level']) {
        // Simple solution: if level is not lower than what you have, you're out.
        // This is a hack-attempt.
        $_T->printHeader();
        $_T->printTitle();
        lovd_writeLog('Error', 'HackAttempt', 'Tried to edit user ID ' . $nID . ' (' . $_SETT['user_levels'][$zData['level']] . ')');
        lovd_showInfoTable('Not allowed to edit this user. This event has been logged.', 'stop');
        $_T->printFooter();
        exit;
    }

    if (!empty($_POST)) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('password', 'password_autogen', 'password_force_change', 'edited_by', 'edited_date');

            // Prepare values.
            $_POST['password'] = lovd_createPasswordHash($_POST['password_1']);
            $_POST['password_autogen'] = '';
            $_POST['password_force_change'] = 0;
            $_POST['edited_by'] = $_AUTH['id'];
            $_POST['edited_date'] = date('Y-m-d H:i:s');

            $_DATA->updateEntry($nID, $_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Changed password for user ' . $nID . ' - ' . $zData['username'] . ' (' . $zData['name'] . ') - with level ' . $_SETT['user_levels'][$zData['level']]);

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully changed the password!', 'success');

            // Change password, if requested.
            if ($nID == $_AUTH['id']) {
                // Was already hashed!
                $_SESSION['auth']['password'] = $_POST['password'];
            }

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password'], $_POST['password_1'], $_POST['password_2']);
        }
    }



    $_T->printHeader();
    $_T->printTitle();

    lovd_errorPrint();

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                    array('', '', 'submit', 'Change password'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'delete') {
    // URL: /users/00001?delete
    // Delete a specific user.

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'Delete user account #' . $nID);
    define('LOG_EVENT', 'UserDelete');

    // Require valid user.
    lovd_requireAUTH();

    require ROOT_PATH . 'class/object_users.php';
    $_DATA = new LOVD_User();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    // Require special clearance, user must be of higher level (and therefore automatically cannot delete himself).
    if ($zData['level'] >= $_AUTH['level']) {
        // Simple solution: if level is not lower than what you have, you're out.
        // This is a hack-attempt.
        $_T->printHeader();
        $_T->printTitle();
        lovd_writeLog('Error', 'HackAttempt', 'Tried to delete user ID ' . $nID . ' (' . $_SETT['user_levels'][$zData['level']] . ')');
        lovd_showInfoTable('Not allowed to delete this user. This event has been logged.', 'stop');
        $_T->printFooter();
        exit;
    }

    // Deleting a user makes the current user curator of the deleted user's genes if there is no curator left for them.
    // Find curated genes and see if they're alone.
    $aCuratedGenes = $_DB->query('SELECT DISTINCT geneid FROM ' . TABLE_CURATES . ' WHERE geneid NOT IN (SELECT DISTINCT geneid FROM ' . TABLE_CURATES . ' WHERE userid != ? AND allow_edit = 1)', array($nID))->fetchAllColumn();

    // Define this here, since it's repeated.
    // Array which will make up the form table.
    $aForm = array(
                    array('POST', '', '', '', '40%', '14', '60%'),
                    array('Deleting user', '', 'print', '<SPAN style="font-family: monospace;"><I>' . $zData['username'] . '</I></SPAN>, ' . $zData['name'] . ' (' . $_SETT['user_levels'][$zData['level']] . ')'),
                    // Deleting a user makes the current user curator of the deleted user's genes if there is no curator left for them.
                    (!count($aCuratedGenes)? false :
                    array('&nbsp;', '', 'print', '<B>This user is the only curator of ' . count($aCuratedGenes) . ' gene' . (count($aCuratedGenes) == 1? '' : 's') . ': ' . implode(', ', $aCuratedGenes) . '. You will become the curator of ' . (count($aCuratedGenes) == 1? 'this gene' : 'these genes') . ' once this user is deleted.</B>')),
                    'skip',
                    array('Enter your password for authorization', '', 'password', 'password', 20),
                    array('', '', 'submit', 'Delete user'),
                  );



    if (!empty($_POST)) {
        lovd_errorClean();

        // Mandatory fields.
        if (!isset($_GET['confirm'])) {
            // User had to enter his/her password for authorization.
            if (!lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
                lovd_errorAdd('password', 'Please enter your correct password for authorization.');
            }
        }

        if (!lovd_error()) {
            if (isset($_GET['confirm'])) {
                // User had to enter his/her password for authorization.
                if (!lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
                    lovd_errorAdd('password', 'Please enter your correct password for authorization.');
                }

                if (!lovd_error()) {
                    // First, make the current user curator for the genes about to be abandoned by this user.
                    $_DB->beginTransaction();
                    if ($aCuratedGenes) {
                        $_DB->query('UPDATE ' . TABLE_CURATES . ' SET userid = ? WHERE userid = ? AND geneid IN (?' . str_repeat(', ?', count($aCuratedGenes) - 1) . ')', array_merge(array($_AUTH['id'], $nID), $aCuratedGenes));
                    }

                    // Query text.
                    // This also deletes the entries in TABLE_CURATES.
                    $_DATA->deleteEntry($nID);
                    $_DB->commit();

                    // Write to log...
                    lovd_writeLog('Event', LOG_EVENT, 'Deleted user ' . $nID . ' - ' . $zData['username'] . ' (' . $zData['name'] . ') - with level ' . $_SETT['user_levels'][$zData['level']]);

                    // Thank the user...
                    header('Refresh: 3; url=' . lovd_getInstallURL() . $_PE[0]);

                    $_T->printHeader();
                    $_T->printTitle();
                    lovd_showInfoTable('Successfully deleted the user account!', 'success');

                    $_T->printFooter();
                    exit;

                } else {
                    // Because we're sending the data back to the form, I need to unset the password fields!
                    unset($_POST['password']);
                }
            } else {
                // Because we're sending the data back to the form, I need to unset the password fields!
                unset($_POST['password']);
            }



            $_T->printHeader();
            $_T->printTitle();

            // FIXME; extend this later.
            $nLogs = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_LOGS . ' WHERE userid = ?', array($nID))->fetchColumn();
            $nCurates = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_CURATES . ' WHERE userid = ?', array($nID))->fetchColumn();
            $nIndividuals = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_INDIVIDUALS . ' WHERE owned_by = ? OR created_by = ? OR edited_by = ?', array($nID, $nID, $nID))->fetchColumn();
            $nScreenings = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_SCREENINGS . ' WHERE owned_by = ? OR created_by = ? OR edited_by = ?', array($nID, $nID, $nID))->fetchColumn();
            $nVars = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_VARIANTS . ' WHERE owned_by = ? OR created_by = ? OR edited_by = ?', array($nID, $nID, $nID))->fetchColumn();
            $nGenes = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_GENES . ' WHERE created_by = ? OR edited_by = ?', array($nID, $nID))->fetchColumn();

            lovd_showInfoTable('<B>The user you are about to delete has the following references to data in this installation:</B><BR>' .
                               $nLogs . ' log entr' . ($nLogs == 1? 'y' : 'ies') . ' will be deleted,<BR>' .
                               $nCurates . ' gene' . ($nCurates == 1? '' : 's') . ' will have this user removed as curator,<BR>' .
                               $nIndividuals . ' individual' . ($nIndividuals == 1? '' : 's') . ' are owned, created by or last edited by this user (you will no longer be able to see that),<BR>' .
                               $nScreenings . ' screening' . ($nScreenings == 1? '' : 's') . ' are owned, created by or last edited by this user (you will no longer be able to see that),<BR>' .
                               $nVars . ' variant' . ($nVars == 1? '' : 's') . ' are owned, created by or last edited by this user (you will no longer be able to see that),<BR>' .
                               $nGenes . ' gene' . ($nGenes == 1? '' : 's') . ' are created by or last edited by this user (you will no longer be able to see that).', 'information');

            if ($nCurates || $nIndividuals || $nScreenings || $nVars || $nGenes) {
                lovd_showInfoTable('<B>Final warning!</B> If you delete this user, all log entries related to this person will be deleted and all references to this person in the data will be removed!', 'warning');
            }

            lovd_errorPrint();

            // Tooltip JS code.
            lovd_includeJS('inc-js-tooltip.php');

            // Table.
            print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '&confirm" method="post">' . "\n");

            // $aForm is repeated, and therefore defined in the beginning of this code block.
            lovd_viewForm($aForm);

            print('</FORM>' . "\n\n");

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    }



    $_T->printHeader();
    $_T->printTitle();

    lovd_showInfoTable('<B>Warning!</B> If you delete this user, all log entries related to this person will be deleted and all references to this person in the data will be removed! Such references include data ownership and information about who created or edited certain content.', 'warning');
    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // $aForm is repeated, and therefore defined in the beginning of this code block.
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'boot') {
    // users/00001?boot
    // Throw a user out of the system.

    $nID = sprintf('%05d', $_PE[1]);
    define('LOG_EVENT', 'UserBoot');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    if ($nID == '00000') {
        $nID = -1;
    }

    $zData = $_DB->query('SELECT name, username, phpsessid, level FROM ' . TABLE_USERS . ' WHERE id = ?', array($nID))->fetchAssoc();
    if (!$zData || $zData['level'] >= $_AUTH['level']) {
        // Wrong ID, apparently.
        $_T->printHeader();
        $_T->printTitle('Boot user #' . $nID);

        lovd_showInfoTable('No such ID!', 'stop');
        $_T->printFooter();
        exit;
    }

    $sFile = ini_get('session.save_path') . '/sess_' . $zData['phpsessid'];
    if (file_exists($sFile)) {
        @unlink($sFile);
    }

    // Write to log...
    lovd_writeLog('Event', LOG_EVENT, 'Booted user ' . $nID . ' - ' . $zData['username'] . ' (' . $zData['name'] . ') - with level ' . $_SETT['user_levels'][$zData['level']]);

    // Return the user where they came from.
    header('Refresh: 0; url=' . lovd_getInstallURL() . CURRENT_PATH);
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && in_array(ACTION, array('lock', 'unlock'))) {
    // users/00001?lock || users/00001?unlock
    // Lock / unlock a user.

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', ucfirst(ACTION) . ' user account #' . $nID);
    define('LOG_EVENT', 'User' . ucfirst(ACTION));

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    if ($nID == '00000') {
        $nID = -1;
    }

    $zData = $_DB->query('SELECT username, name, (login_attempts >= 3) AS locked, level FROM ' . TABLE_USERS . ' WHERE id = ?', array($nID))->fetchAssoc();
    if (!$zData || $zData['level'] >= $_AUTH['level']) {
        // Wrong ID, apparently.
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('No such ID!', 'stop');
        $_T->printFooter();
        exit;

    } elseif (($zData['locked'] && ACTION == 'lock') || (!$zData['locked'] && ACTION == 'unlock')) {
        // Can't unlock someone that is not locked or lock someone that is already locked.
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('User is already ' . ACTION . 'ed!', 'stop');
        $_T->printFooter();
        exit;
    }

    // The actual query.
    $_DB->query('UPDATE ' . TABLE_USERS . ' SET login_attempts = ' . ($zData['locked']? 0 : 3) . ' WHERE id = ?', array($nID));

    // Write to log...
    lovd_writeLog('Event', LOG_EVENT, ucfirst(ACTION) . 'ed user ' . $nID . ' - ' . $zData['username'] . ' (' . $zData['name'] . ') - with level ' . $_SETT['user_levels'][$zData['level']]);

    // Return the user where they came from.
    header('Refresh: 0; url=' . lovd_getInstallURL() . CURRENT_PATH);
    exit;
}





if (PATH_COUNT == 1 && ACTION == 'register') {
    // URL: users?register
    // Register a new submitter

    define('PAGE_TITLE', 'Register as new submitter');
    define('LOG_EVENT', 'UserRegister');

    if ($_AUTH) {
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('You are already a registered user.', 'stop');
        $_T->printFooter();
        exit;
    }

    require ROOT_PATH . 'class/object_users.php';
    $_DATA = new LOVD_User();
    require ROOT_PATH . 'inc-lib-form.php';
    require ROOT_PATH . 'lib/reCAPTCHA/inc-lib-recaptcha.php';
    $sCAPTCHAerror = '';

    if (!empty($_POST)) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        // Adding CAPTCHA check on registration form.
        // If no response has been filled in, we need to complain. Otherwise, we should check the answer.
        if (empty($_POST['recaptcha_response_field'])) {
            lovd_errorAdd('', 'Please fill in the two words that you see in the image at the bottom of the form.');
        } else {
            // Check answer!
            $response = recaptcha_check_answer('6Le0JQQAAAAAAB-iLSVi81tR5s8zTajluFFxkTPL', $_SERVER['REMOTE_ADDR'], $_POST['recaptcha_challenge_field'], $_POST['recaptcha_response_field']);
            if (!($response->is_valid)) {
                lovd_errorAdd('', 'Registration authentication failed. Please try again by filling in the two words that you see in the image at the bottom of the form.');
                $sCAPTCHAerror = $response->error;
            }
        }

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('name', 'institute', 'department', 'telephone', 'address', 'city', 'countryid', 'email', 'reference', 'username', 'password', 'password_force_change', 'level', 'allowed_ip', 'login_attempts', 'last_login', 'created_date');

            // Prepare values.
            $_POST['password_force_change'] = 0;
            $_POST['password'] = lovd_createPasswordHash($_POST['password_1']);
            $_POST['level'] = LEVEL_SUBMITTER;
            $_POST['login_attempts'] = 0;
            $_POST['last_login'] = $_POST['created_date'] = date('Y-m-d H:i:s');

            $nID = $_DATA->insertEntry($_POST, $aFields);
            $_DB->query('UPDATE ' . TABLE_USERS . ' SET created_by = id WHERE id = ?', array($nID));

            $_SESSION['auth'] = $_DB->query('SELECT * FROM ' . TABLE_USERS . ' WHERE id = ?', array($nID))->fetchAssoc();
            $_AUTH =& $_SESSION['auth'];
            // To prevent notices in the header for instance...
            $_AUTH['curates']      = array();
            $_AUTH['collaborates'] = array();

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, $_SERVER['REMOTE_ADDR'] . ' (' . gethostbyaddr($_SERVER['REMOTE_ADDR']) . ') successfully created own submitter account with ID ' . $nID);

            $aTo = array(array($_POST['name'], $_POST['email']));

            $sMessage = 'Dear ' . $_POST['name'] . ',' . "\n\n" .
                        'You have registered as a submitter of sequence variations for this LOVD system.' . "\n" .
                        'Below is a copy of your registration information.' . "\n\n";

            if ($_CONF['location_url']) {
                $sMessage .= 'To log in to LOVD, click this link:' . "\n" .
                             $_CONF['location_url'] . 'login' . "\n\n" .
                             'You can also go straight to your account using the following link:' . "\n" .
                             $_CONF['location_url'] . $_PE[0] . '/' . $_AUTH['id'] . "\n\n";
            }
            $sMessage .= 'Regards,' . "\n" .
                         '    LOVD system at ' . $_CONF['institute'] . "\n\n";

            // Array containing the submitter fields.
            $_POST['id'] = $nID;
            $_POST['country_'] = $_DB->query('SELECT name FROM ' . TABLE_COUNTRIES . ' WHERE id = ?', array($_POST['countryid']))->fetchColumn();
            $aMailFields =
                     array(
                            '_POST',
                            'id' => 'User ID',
                            'name' => 'Name',
                            'institute' => 'Institute',
                            'department' => 'Department',
                            'address' => 'Address',
                            'city' => 'City',
                            'country_' => 'Country',
                            'email' => 'Email address',
                            'telephone' => 'Telephone',
                            'reference' => 'Reference',
                            'username' => 'Username',
                            'password_1' => 'Password',
                          );

            $aBody = array($sMessage, 'submitter_details' => $aMailFields);

            $sBody = lovd_formatMail($aBody);

            // Set proper subject.
            $sSubject = 'LOVD registration'; // Don't just change this; lovd_sendMail() is parsing it.

            // Send mail.
            $bMail = lovd_sendMail($aTo, $sSubject, $sBody, $_SETT['email_headers']);

            // Thank the user...
            header('Refresh: ' . ($bMail? '3' : '5') . '; url=' . lovd_getInstallURL() . 'genes');

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Your account has successfully been created!<BR>' . "\n" .
                               ($bMail? 'We\'ve sent you an email containing your account information.' :
                               'Due to an error, we couldn\'t send you an email containing your account information. Our apologies for the inconvenience.'),
                               ($bMail? 'success' : 'information'));

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password'], $_POST['password_1'], $_POST['password_2']);
        }

    } else {
        // Default values.
        $_DATA->setDefaultValues();
    }



    $_T->printHeader();
    $_T->printTitle();

    if (GET) {
        print('      To register as a new submitter, please fill out the form below.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_showInfoTable('Please note that you do <B>NOT</B> need to register to view the data available at these pages. You only need an account for submitting new variants.', 'warning');
    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        'skip',
                        array('', '', 'print', '<B>Registration authentication</B>'),
                        'hr',
                        array('Please fill in the two words that you see in the image', '', 'print', recaptcha_get_html('6Le0JQQAAAAAAPQ55JT0m0_AVX5RqgSnHBplWHxZ', $sCAPTCHAerror, SSL)),
                        'hr',
                        'skip',
                        array('', '', 'submit', 'Register'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'submissions') {
    // URL: users/00001?submissions
    // Manage unfinished submissions

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'Manage unfinished submissions for user #' . $nID);

    $_T->printHeader();
    $_T->printTitle();

    if ($_AUTH && $_AUTH['id'] == $nID) {
        // Require submitter clearance.
        lovd_requireAUTH();

        lovd_showInfoTable('Below are lists of your unfinished submissions', 'information');
    } else {
        // Require manager clearance.
        lovd_requireAUTH(LEVEL_MANAGER);

        lovd_showInfoTable('Below are lists of this user\'s unfinished submissions', 'information');
    }

    $zData = $_DB->query('SELECT saved_work FROM ' . TABLE_USERS . ' WHERE id = ?', array($nID), false)->fetchAssoc();
    if (!empty($zData['saved_work'])) {
        $zData['saved_work'] = unserialize($zData['saved_work']);
    } else {
        $zData['saved_work'] = array();
    }

    $_T->printTitle('Individuals', 'H4');
    $aUnfinished = (!empty($zData['saved_work']['submissions']['individual'])? array_keys($zData['saved_work']['submissions']['individual']) : array());
    if (!empty($aUnfinished)) {
        require ROOT_PATH . 'class/object_individuals.php';
        $_DATA = new LOVD_Individual();
        $_GET['search_individualid'] = implode('|', $aUnfinished);
        $_GET['page_size'] = '10';
        $_DATA->setRowLink('Individuals_submissions', ($_AUTH['id'] == $nID? 'submit/individual/' . $_DATA->sRowID : ''));
        $_DATA->viewList('Individuals_submissions', array('individualid', 'diseaseids', 'owned_by_', 'status'), false, false, true);
        unset($_GET['search_individualid']);
    } else {
        lovd_showInfoTable('No submissions of individuals found!', 'stop');
    }

    $_T->printTitle('Screenings', 'H4');
    $aUnfinished = (!empty($zData['saved_work']['submissions']['screening'])? array_keys($zData['saved_work']['submissions']['screening']) : array());
    if (!empty($aUnfinished)) {
        require ROOT_PATH . 'class/object_screenings.php';
        $_DATA = new LOVD_Screening();
        $_GET['search_screeningid'] = implode('|', $aUnfinished);
        $_GET['page_size'] = '10';
        $_DATA->setRowLink('Screenings_submissions', ($_AUTH['id'] == $nID? 'submit/screening/' . $_DATA->sRowID : ''));
        $_DATA->viewList('Screenings_submissions', array('owned_by_', 'created_date', 'edited_date'), false, false, true);
    } else {
        lovd_showInfoTable('No submissions of variant screenings found!', 'stop');
    }

    $_T->printFooter();
    exit;
}
?>
