<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-01-14
 * Modified    : 2011-06-09
 * For LOVD    : 3.0-alpha-02
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
    // URL: /users
    // View all entries.

    define('PAGE_TITLE', 'View user accounts');
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_users.php';
    $_DATA = new LOVD_User();
    $_DATA->viewList();

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && ctype_digit($_PATH_ELEMENTS[1]) && !ACTION) {
    // URL: /users/00001
    // View specific entry.

    $nID = str_pad($_PATH_ELEMENTS[1], 5, '0', STR_PAD_LEFT);
    define('PAGE_TITLE', 'View user account #' . $nID);
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    // FIXME; we need to think about this. To create a public submitters list, will we have a modified viewList() without viewEntry() or what?
    // Allow everybody to see certain details, but only managers to view all? Hide username, certain info from others?
    // Require valid user.
    lovd_requireAUTH();
    
    // Require manager clearance, if user is not viewing himself.
    if ($nID != $_AUTH['id']) {
        lovd_requireAUTH(LEVEL_MANAGER);
    }

    require ROOT_PATH . 'class/object_users.php';
    $_DATA = new LOVD_User();
    $zData = $_DATA->viewEntry($nID);

    $sNavigation = '';
    if ($_AUTH['level'] > $zData['level']) {
        // Authorized user (admin or manager) is logged in. Provide tools.
        $sNavigation = '<A href="users/' . $nID . '?edit">Edit user</A>';
        if ($zData['active']) {
            $sNavigation .= ' | <A href="users/' . $nID . '?boot">Force user log out</A>';
        }
        $sNavigation .= ' | <A href="users/' . $nID . '?' . ($zData['locked']? 'un' : '') . 'lock">' . ($zData['locked']? 'Unl' : 'L') . 'ock user</A>';
        $sNavigation .= ' | <A href="users/' . $nID . '?delete">Delete user</A>';
    } elseif ($_AUTH['id'] == $nID) {
        // Viewing himself!
        $sNavigation = '<A href="users/' . $nID . '?edit">Update your registration</A>';
    }

    if ($sNavigation) {
        print('      <IMG src="gfx/trans.png" alt="" width="1" height="5"><BR>' . "\n");
        lovd_showNavigation($sNavigation);
    }

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (empty($_PATH_ELEMENTS[1]) && ACTION == 'create') {
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
            $_POST['password'] = md5($_POST['password_1']);
            $_POST['login_attempts'] = ($_POST['locked']? 3 : 0);
            $_POST['created_by'] = $_AUTH['id'];
            $_POST['created_date'] = date('Y-m-d H:i:s');

            $nID = $_DATA->insertEntry($_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Created user ' . $nID . ' - ' . $_POST['username'] . ' (' . $_POST['name'] . ') - with level ' . $_SETT['user_levels'][$_POST['level']]);

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PATH_ELEMENTS[0] . '/' . $nID);

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('Successfully created the user account!', 'success');

            require ROOT_PATH . 'inc-bot.php';
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password'], $_POST['password_1'], $_POST['password_2']);
        }

    } else {
        // Default values.
        $_DATA->setDefaultValues();
    }



    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    if (GET) {
        print('      To create a new user, please fill out the form below.<BR>' . "\n" .
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
                        array('', '', 'submit', 'Create user'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && ctype_digit($_PATH_ELEMENTS[1]) && ACTION == 'edit') {
    // URL: /users/00001?edit
    // Edit specific entry.

    $nID = str_pad($_PATH_ELEMENTS[1], 5, '0', STR_PAD_LEFT);
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
        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader(PAGE_TITLE);
        lovd_writeLog('Error', 'HackAttempt', 'Tried to edit user ID ' . $nID . ' (' . $_SETT['user_levels'][$zData['level']] . ')');
        lovd_showInfoTable('Not allowed to edit this user. This event has been logged.', 'stop');
        require ROOT_PATH . 'inc-bot.php';
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
                $_POST['password'] = md5($_POST['password_1']);
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
            header('Refresh: 3; url=' . lovd_getInstallURL() . 'users/' . $nID);

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('Successfully edited the user account!', 'success');

            // Change password, if requested.
            if ($nID == $_AUTH['id'] && !empty($_POST['password_1'])) {
                // Was already md5'ed!
                $_SESSION['auth']['password'] = $_POST['password'];
            }

            require ROOT_PATH . 'inc-bot.php';
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password'], $_POST['password_1'], $_POST['password_2']);
        }

    } else {
        // Default values.
        foreach ($zData as $key => $val) {
            $_POST[$key] = $val;
        }
        $_POST['password'] = '';
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
                        array('', '', 'submit', 'Edit user'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && ctype_digit($_PATH_ELEMENTS[1]) && ACTION == 'change_password') {
    // URL: /users/00001?change_password
    // Change a user's password.

    $nID = str_pad($_PATH_ELEMENTS[1], 5, '0', STR_PAD_LEFT);
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
        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader(PAGE_TITLE);
        lovd_writeLog('Error', 'HackAttempt', 'Tried to edit user ID ' . $nID . ' (' . $_SETT['user_levels'][$zData['level']] . ')');
        lovd_showInfoTable('Not allowed to edit this user. This event has been logged.', 'stop');
        require ROOT_PATH . 'inc-bot.php';
        exit;
    }

    if (!empty($_POST)) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('password', 'password_autogen', 'password_force_change', 'edited_by', 'edited_date');

            // Prepare values.
            $_POST['password'] = md5($_POST['password_1']);
            $_POST['password_autogen'] = '';
            $_POST['password_force_change'] = 0;
            $_POST['edited_by'] = $_AUTH['id'];
            $_POST['edited_date'] = date('Y-m-d H:i:s');

            $_DATA->updateEntry($nID, $_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Changed password for user ' . $nID . ' - ' . $zData['username'] . ' (' . $zData['name'] . ') - with level ' . $_SETT['user_levels'][$zData['level']]);

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . 'users/' . $nID);

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('Successfully changed the password!', 'success');

            // Change password, if requested.
            if ($nID == $_AUTH['id']) {
                // Was already md5'ed!
                $_SESSION['auth']['password'] = $_POST['password'];
            }

            require ROOT_PATH . 'inc-bot.php';
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password'], $_POST['password_1'], $_POST['password_2']);
        }
    }



    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    lovd_errorPrint();

    // Table.
    print('      <FORM action="' . $_PATH_ELEMENTS[0] . '/' . $nID . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                    array('', '', 'submit', 'Change password'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && ctype_digit($_PATH_ELEMENTS[1]) && ACTION == 'delete') {
    // URL: /users/00001?delete
    // Delete a specific user.

    $nID = str_pad($_PATH_ELEMENTS[1], 5, '0', STR_PAD_LEFT);
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
        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader(PAGE_TITLE);
        lovd_writeLog('Error', 'HackAttempt', 'Tried to delete user ID ' . $nID . ' (' . $_SETT['user_levels'][$zData['level']] . ')');
        lovd_showInfoTable('Not allowed to delete this user. This event has been logged.', 'stop');
        require ROOT_PATH . 'inc-bot.php';
        exit;
    }

    // Deleting a user makes the current user curator of the deleted user's genes if there is no curator left for them.
    // Find curated genes and see if they're alone.
    $q = lovd_queryDB('SELECT DISTINCT geneid FROM lovd_v3_users2genes WHERE geneid NOT IN (SELECT DISTINCT geneid FROM lovd_v3_users2genes WHERE userid != ? AND allow_edit = 1)', array($nID), true);
    $aCuratedGenes = array();
    while ($r = mysql_fetch_row($q)) {
        // Gene has no curator, and user is going to be deleted!
        $aCuratedGenes[] = $r[0];
    }

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
            if (md5($_POST['password']) != $_AUTH['password']) {
                lovd_errorAdd('password', 'Please enter your correct password for authorization.');
            }
        }

        if (!lovd_error()) {
            if (isset($_GET['confirm'])) {
                // User had to enter his/her password for authorization.
                if (md5($_POST['password']) != $_AUTH['password']) {
                    lovd_errorAdd('password', 'Please enter your correct password for authorization.');
                }

                if (!lovd_error()) {
                    // First, make the current user curator for the genes about to be abandoned by this user.
                    lovd_queryDB('START TRANSACTION');
                    if ($aCuratedGenes) {
                        lovd_queryDB('UPDATE ' . TABLE_CURATES . ' SET userid = ? WHERE userid = ? AND geneid IN (?' . str_repeat(', ?', count($aCuratedGenes) - 1) . ')', array_merge(array($_AUTH['id'], $nID), $aCuratedGenes), true);
                    }

                    // Query text.
                    // This also deletes the entries in TABLE_CURATES.
                    $_DATA->deleteEntry($nID);
                    lovd_queryDB('COMMIT');

                    // Write to log...
                    lovd_writeLog('Event', LOG_EVENT, 'Deleted user ' . $nID . ' - ' . $zData['username'] . ' (' . $zData['name'] . ') - with level ' . $_SETT['user_levels'][$zData['level']]);

                    // Thank the user...
                    header('Refresh: 3; url=' . lovd_getInstallURL() . 'users');

                    require ROOT_PATH . 'inc-top.php';
                    lovd_printHeader(PAGE_TITLE);
                    lovd_showInfoTable('Successfully deleted the user account!', 'success');

                    require ROOT_PATH . 'inc-bot.php';
                    exit;

                } else {
                    // Because we're sending the data back to the form, I need to unset the password fields!
                    unset($_POST['password']);
                }
            } else {
                // Because we're sending the data back to the form, I need to unset the password fields!
                unset($_POST['password']);
            }



            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);

            // FIXME; extend this later.
            list($nLogs) = mysql_fetch_row(lovd_queryDB('SELECT COUNT(*) FROM ' . TABLE_LOGS . ' WHERE userid = ?', array($nID)));
            list($nCurates) = mysql_fetch_row(lovd_queryDB('SELECT COUNT(*) FROM ' . TABLE_CURATES . ' WHERE userid = ?', array($nID)));
            list($nIndividuals) = mysql_fetch_row(lovd_queryDB('SELECT COUNT(*) FROM ' . TABLE_INDIVIDUALS . ' WHERE ownerid = ? OR created_by = ? OR edited_by = ?', array($nID, $nID, $nID)));
            list($nScreenings) = mysql_fetch_row(lovd_queryDB('SELECT COUNT(*) FROM ' . TABLE_SCREENINGS . ' WHERE ownerid = ? OR created_by = ? OR edited_by = ?', array($nID, $nID, $nID)));
            list($nVars) = mysql_fetch_row(lovd_queryDB('SELECT COUNT(*) FROM ' . TABLE_VARIANTS . ' WHERE ownerid = ? OR created_by = ? OR edited_by = ?', array($nID, $nID, $nID)));
            list($nGenes) = mysql_fetch_row(lovd_queryDB('SELECT COUNT(*) FROM ' . TABLE_GENES . ' WHERE created_by = ? OR edited_by = ?', array($nID, $nID)));

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
            print('      <FORM action="' . $_PATH_ELEMENTS[0] . '/' . $nID . '?' . ACTION . '&confirm" method="post">' . "\n");

            // $aForm is repeated, and therefore defined in the beginning of this code block.
            lovd_viewForm($aForm);

            print('</FORM>' . "\n\n");

            require ROOT_PATH . 'inc-bot.php';
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    }



    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    lovd_showInfoTable('<B>Warning!</B> If you delete this user, all log entries related to this person will be deleted and all references to this person in the data will be removed! Such references include data ownership and information about who created or edited certain content.', 'warning');
    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    // Table.
    print('      <FORM action="' . $_PATH_ELEMENTS[0] . '/' . $nID . '?' . ACTION . '" method="post">' . "\n");

    // $aForm is repeated, and therefore defined in the beginning of this code block.
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && ctype_digit($_PATH_ELEMENTS[1]) && ACTION == 'delete') {
    // URL: /users/00001?delete
    // Remove a user from the system
    
    $nID = str_pad($_PATH_ELEMENTS[1], 5, '0', STR_PAD_LEFT);
    define('PAGE_TITLE', 'Delete user account #' . $nID);
    define('LOG_EVENT', 'UserDelete');

    // Require valid user.
    lovd_requireAUTH();
    
    if (GET) {
        $_POST['workID'] = lovd_generateRandomID();
        $_SESSION['work'][$_POST['workID']] = array(
                                                    'action' => 'users/' . $nID . '?delete',
                                                    'step' => '1',
                                                   );
    }
    
    if ($_SESSION['work'][$_POST['workID']]['step'] == '1') {
        if ($nID != $_AUTH['id']) {
            // Neccessary level depends on level of user. Special case.
            list($nLevel) = mysql_fetch_row(lovd_queryDB('SELECT level FROM ' . TABLE_USERS . ' WHERE id = ?', array($nID)));
            // Simple solution: if level is not lower than what you have, you're out.
            if ($nLevel >= $_AUTH['level']) {
                // This is a hack-attempt.
                require ROOT_PATH . 'inc-top.php';
                lovd_printHeader(PAGE_TITLE);
                lovd_writeLog('Error', 'HackAttempt', 'Tried to delete user ID ' . $nID . ' (' . $_SETT['user_levels'][$nLevel] . ')');
                lovd_showInfoTable('Not allowed to delete this user. This event has been logged.', 'stop');
                require ROOT_PATH . 'inc-bot.php';
                exit;
            }
        }
        else {
            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('Not allowed to delete yourself.', 'stop');
            require ROOT_PATH . 'inc-bot.php';
            exit;
        }
    
        require ROOT_PATH . 'inc-lib-form.php';

        if (count($_POST) > 1) {
            lovd_errorClean();

            if (empty($_POST['password_1'])) {
                lovd_errorAdd('password_1', 'Please fill in the \'Enter your password for authorization\' field.');
            }
            
            if ($_POST['password_1'] && md5($_POST['password_1']) != $_AUTH['password']) {
                lovd_errorAdd('password_1', 'Please enter your correct password for authorization.');
            }
            
            if (!lovd_error()) {
                $_SESSION['work'][$_POST['workID']]['step'] = '2';
                
                // FIXME!!! I have no clue, why i can't use the path without '../'. The other pages don't seem to need it.
                print('<FORM action="../' . $_PATH_ELEMENTS[0] . '/' . $nID . '?' . ACTION .'" id="confirmDelete" method="post">' . "\n" .
                      '    <TABLE border="0" cellpadding="0" cellspacing="1" width="760">'. "\n" .
                      '        <INPUT type="hidden" name="workID" value="' . $_POST['workID'] . '">' . "\n" .
                      '    </TABLE>' . "\n" .
                      '</FORM>' . "\n\n" .
                      '<SCRIPT type="text/javascript">' . "\n" .
                      '  document.forms[\'confirmDelete\'].submit();' . "\n" .
                      '</SCRIPT>' . "\n\n");
                exit;
            } else {
                // Because we're sending the data back to the form, I need to unset the password fields!
                unset($_POST['password_1']);
            }
        }
    }
    
    
    
    
    
    if ($_SESSION['work'][$_POST['workID']]['step'] == '2') {
        require ROOT_PATH . 'inc-lib-form.php';
        
        if (count($_POST) > 1) {
            lovd_errorClean();
            
            if (empty($_POST['password_2']) || empty($_POST['password_3'])) {
                lovd_errorAdd('password_2', 'Please fill in both the \'Enter your password for authorization\' and \'Password (confirm)\' fields.');
                lovd_errorAdd('password_3', '');
            }
            
            if ($_POST['password_2'] != $_POST['password_3']) {
                lovd_errorAdd('password_2', 'The entered passwords did not match!');
                lovd_errorAdd('password_3', '');
            }

            // User had to enter his/her password for authorization.
            if ($_POST['password_2'] && md5($_POST['password_2']) != $_AUTH['password']) {
                lovd_errorAdd('password_2', 'Please enter your correct password for authorization in both fields.');
                lovd_errorAdd('password_3', '');
            }
            
            if (!lovd_error()) {
                require ROOT_PATH . 'class/object_users.php';
                $_DATA = new LOVD_User();
                $zData = $_DATA->loadEntry($nID);
                
                // Query text.
                // This also deletes the entries in variants??????.
                // FIXME; implement deleteEntry()
                $sSQL = 'DELETE FROM ' . TABLE_USERS . ' WHERE id = ?';
                $aSQL = array($nID);
                $q = lovd_queryDB($sSQL, $aSQL);
                if (!$q) {
                    lovd_queryError(LOG_EVENT, $sSQL, mysql_error());
                }

                // Write to log...
                lovd_writeLog('Event', LOG_EVENT, 'Deleted user entry ' . $nID . ' - ' . $zData['name'] . ' (' . $zData['level'] . ')');

                // Thank the user...
                header('Refresh: 3; url=' . lovd_getInstallURL() . 'users');

                require ROOT_PATH . 'inc-top.php';
                lovd_printHeader(PAGE_TITLE);
                lovd_showInfoTable('Successfully deleted the user entry!', 'success');

                require ROOT_PATH . 'inc-bot.php';
                exit;
            } else {
                unset($_POST['password_2'], $_POST['password_3']);
            }
        }
            
        require ROOT_PATH . 'inc-top.php';
        require ROOT_PATH . 'class/object_users.php';
        $_DATA = new LOVD_User();
        $zData = $_DATA->loadEntry($nID);
        
        lovd_printHeader(PAGE_TITLE);

        lovd_errorPrint();
        
        list($nIndividuals) = mysql_fetch_row(lovd_queryDB('SELECT COUNT(*) FROM ' . TABLE_INDIVIDUALS . ' WHERE ownerid=?', array($nID)));
        list($nScreenings) = mysql_fetch_row(lovd_queryDB('SELECT COUNT(*) FROM ' . TABLE_SCREENINGS . ' WHERE ownerid=?', array($nID)));
        list($nVars) = mysql_fetch_row(lovd_queryDB('SELECT COUNT(*) FROM ' . TABLE_VARIANTS . ' WHERE ownerid=?', array($nID)));
        list($nGenes) = mysql_fetch_row(lovd_queryDB('SELECT COUNT(*) FROM ' . TABLE_GENES . ' WHERE edited_by=?', array($nID)));
        
        print('      <PRE>' . "\n" .
              '  <b>The user you are about to delete has the following references to data in this installation:</b>' . "\n" .
              '  Found ' . $nIndividuals . ' individual' . ($nIndividuals == 1? '' : 's') . '.' . "\n" .
              '  Found ' . $nScreenings . ' screening' . ($nScreenings == 1? '' : 's') . '.' . "\n" .
              '  Found ' . $nVars . ' variant' . ($nVars == 1? '' : 's') . '.' . "\n" .
              '  Found ' . $nGenes . ' gene' . ($nGenes == 1? '' : 's') . '.' . "\n" .
              '      </PRE>' . "\n");
              
        if ($nGenes || $nIndividuals || $nVars) {
            lovd_showInfoTable('FINAL WARNING! If you delete this user, you will lose all the references to this person in the data!', 'warning');
        }
        
        // Table.
        print('<FORM action="' . $_PATH_ELEMENTS[0] . '/' . $nID . '?' . ACTION . '" method="post">' . "\n");
        
        // Array which will make up the form table.
        $aForm = array_merge(
                     array(
                            array('POST', '', '', '', '40%', '14', '60%'),
                            array('Deleting user information entry', '', 'print', $nID . ' - ' . $zData['name'] . ' (' . $_SETT['user_levels'][$zData['level']] . ')'),
                            'skip',
                            array('Enter your password for authorization', '', 'password', 'password_2', 20),
                            array('Password (confirm)', '', 'password', 'password_3', 20),
                            array('', '', 'submit', 'Delete user entry'),
                          ));
        lovd_viewForm($aForm);
        
        print('        <INPUT type="hidden" name="workID" value="' . $_POST['workID'] . '">' . "\n" .
              '    </TABLE>' . "\n" .
              '</FORM>' . "\n\n");
        
        require ROOT_PATH . 'inc-bot.php';
        exit;
    }




    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    lovd_errorPrint();

    lovd_showInfoTable('WARNING! If you delete this user, you will loose all the references to this person in the data!', 'warning');

    // Table.
    print('      <FORM action="' . $_PATH_ELEMENTS[0] . '/' . $nID . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                     array(
                     array('POST', '', '', '', '40%', '14', '60%'),
                     array('Enter your password for authorization', '', 'password', 'password_1', 20),
                     array('', '', 'submit', 'Delete user entry'),
                   ));
        lovd_viewForm($aForm);
    
    print('    <INPUT name="workID" type="hidden" value="' . $_POST['workID'] . '">');
    print('</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && ctype_digit($_PATH_ELEMENTS[1]) && ACTION == 'boot') {
    // users/00001?boot
    // Throw a user out of the system.

    $nID = str_pad($_PATH_ELEMENTS[1], 5, '0', STR_PAD_LEFT);
    
    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    $zData = @mysql_fetch_assoc(lovd_queryDB('SELECT phpsessid, level FROM ' . TABLE_USERS . ' WHERE id = ?', array($nID)));
    if (!$zData || $zData['level'] >= $_AUTH['level']) {
        // Wrong ID, apparently.
        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader('Boot user #' . $nID);

        lovd_showInfoTable('No such ID!', 'stop');
        require ROOT_PATH . 'inc-bot.php';
        exit;
    }

    $sFile = ini_get('session.save_path') . '/sess_' . $zData['phpsessid'];
    if (file_exists($sFile)) {
        @unlink($sFile);
    }

    // Write to log...
    // FIXME; LOVD 3.0 standard, please.
    lovd_writeLog('Event', 'UserBoot', $_AUTH['username'] . ' (' . mysql_real_escape_string($_AUTH['name']) . ') successfully booted user ' . $_POST['username'] . ' (' . $_POST['name'] . ')');

    // Return the user where they came from.
    header('Refresh: 0; url=' . lovd_getInstallURL() . 'users/' . $nID);
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && ctype_digit($_PATH_ELEMENTS[1]) && in_array(ACTION, array('lock', 'unlock'))) {
    // users/00001?lock || users/00001?unlock
    // Lock / unlock a user.

    $nID = str_pad($_PATH_ELEMENTS[1], 5, '0', STR_PAD_LEFT);
    define('PAGE_TITLE', ucfirst(ACTION) . ' user account #' . $nID);
    define('LOG_EVENT', 'User' . ucfirst(ACTION));

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    $zData = @mysql_fetch_assoc(lovd_queryDB('SELECT username, name, (login_attempts >= 3) AS locked, level FROM ' . TABLE_USERS . ' WHERE id = ?', array($nID)));
    if (!$zData || $zData['level'] >= $_AUTH['level']) {
        // Wrong ID, apparently.
        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader(PAGE_TITLE);
        lovd_showInfoTable('No such ID!', 'stop');
        require ROOT_PATH . 'inc-bot.php';
        exit;
    }

    // What are we doing?
    // FIXME; the original code was better (before commit #55). Do you see why?
    $sAction = ucfirst(ACTION);

    // The actual query.
    lovd_queryDB('UPDATE ' . TABLE_USERS . ' SET login_attempts = ' . ($zData['locked']? 0 : 3) . ' WHERE id = ?', array($nID), true);

    // Write to log...
    // FIXME; LOVD 3.0 standard please!
    lovd_writeLog('Event', 'User' . $sAction, $_AUTH['username'] . ' (' . mysql_real_escape_string($_AUTH['name']) . ') successfully ' . strtolower($sAction) . 'ed user ' . $zData['username'] . ' (' . $zData['name'] . ')');

    // Return the user where they came from.
    header('Refresh: 0; url=' . lovd_getInstallURL() . 'users/' . $nID);
    exit;
}
?>
