<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-01-14
 * Modified    : 2010-12-24
 * For LOVD    : 3.0-pre-10
 *
 * Copyright   : 2004-2010 Leiden University Medical Center; http://www.LUMC.nl/
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
    $_DATA = new User();
    $_DATA->viewList();

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && preg_match('/^[0-9]+$/', $_PATH_ELEMENTS[1]) && !ACTION) {
    // URL: /users/00001
    // View specific entry.

    $nID = $_PATH_ELEMENTS[1];
    define('PAGE_TITLE', 'View user account #' . $nID);
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    // Require manager clearance, if user is not viewing himself.
    if ($nID != $_AUTH['id']) {
        lovd_requireAUTH(LEVEL_MANAGER);
    }

    require ROOT_PATH . 'class/object_users.php';
    $_DATA = new User();
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
    $_DATA = new User();
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





if (!empty($_PATH_ELEMENTS[1]) && preg_match('/^[0-9]+$/', $_PATH_ELEMENTS[1]) && ACTION == 'edit') {
    // URL: /users/00001?edit
    // Edit specific entry.

    $nID = $_PATH_ELEMENTS[1];
    define('PAGE_TITLE', 'Edit user account #' . $nID);
    define('LOG_EVENT', 'UserEdit');

    // Require special clearance, if user is not editing himself.
    if ($nID != $_AUTH['id']) {
        // Neccessary level depends on level of user. Special case.
        list($nLevel) = mysql_fetch_row(lovd_queryDB('SELECT level FROM ' . TABLE_USERS . ' WHERE id = ?', array($nID)));
        // Simple solution: if level is not lower than what you have, you're out.
        if ($nLevel >= $_AUTH['level']) {
            // This is a hack-attempt.
            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_writeLog('Error', 'HackAttempt', 'Tried to edit user ID ' . $nID . ' (' . $_SETT['user_levels'][$nLevel] . ')');
            lovd_showInfoTable('Now allowed to edit this user. This event has been logged.', 'stop');
            require ROOT_PATH . 'inc-bot.php';
            exit;
        }
    }

    require ROOT_PATH . 'class/object_users.php';
    $_DATA = new User();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

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

            $_DATA->updateEntry($zData['id'], $_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited user ' . $nID . ' - ' . $zData['username'] . ' (' . $_POST['name'] . ') - with level ' . $_SETT['user_levels'][(!empty($_POST['level'])? $_POST['level'] : $zData['level'])]);

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . 'users/' . $nID);

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('Successfully edited the user account!', 'success');

            // Change password, if requested.
            if ($zData['id'] == $_AUTH['id'] && !empty($_POST['password_1'])) {
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
/*//////////////////////////////////////////////////////////////////////////////





if ($_GET['action'] == 'boot' && is_numeric($_GET['boot'])) {
    // Throw a user out of the system.

// Require manager clearance.
lovd_requireAUTH(LEVEL_MANAGER);

    $zData = @mysql_fetch_assoc(mysql_query('SELECT t1.phpsessid, t1.level FROM ' . TABLE_USERS . ' AS t1 WHERE t1.id = "' . $_GET['boot'] . '"'));
    if (!$zData || $zData['level'] >= $_AUTH['level']) {
        // Wrong ID, apparently.
        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader('setup_users_manage', 'LOVD Setup - Manage authorized users');

        print('      No such ID!<BR>' . "\n");
        require ROOT_PATH . 'inc-bot.php';
        exit;
    }

    $sFile = ini_get('session.save_path') . '/sess_' . $zData['phpsessid'];
    if (file_exists($sFile)) {
        @unlink($sFile);
    }

    // Write to log...
    lovd_writeLog('Event', 'UserBoot', $_AUTH['username'] . ' (' . mysql_real_escape_string($_AUTH['name']) . ') successfully booted user ' . $_POST['username'] . ' (' . $_POST['name'] . ')');
    
    // Return the user where they came from.
    // FIXME; wasn't this for the menu that was never implemented?
    $sAction = (!empty($_GET['return'])? $_GET['return'] . (isset($_GET[$_GET['return']])? '&' . $_GET['return'] . '=' . $_GET[$_GET['return']] : ''): 'view_all');
    header('Location: ' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?action=' . $sAction);
    exit;
}





if (in_array($_GET['action'], array('lock', 'unlock')) && is_numeric($_GET['lock'])) {
    // Lock / unlock a user.

// Require manager clearance.
lovd_requireAUTH(LEVEL_MANAGER);

    $zData = @mysql_fetch_assoc(mysql_query('SELECT username, name, (login_attempts >= 3) AS locked, level FROM ' . TABLE_USERS . ' WHERE id = "' . $_GET['lock'] . '"'));
    if (!$zData || $zData['level'] >= $_AUTH['level']) {
        // Wrong ID, apparently.
        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader('setup_users_manage', 'LOVD Setup - Manage authorized users');
        lovd_showInfoTable('No such ID!', 'stop');
        require ROOT_PATH . 'inc-bot.php';
        exit;
    }

    // What are we doing?
    $sAction = ($zData['locked']? 'Unl' : 'L') . 'ock';

    // The actual query.
    $sQ = 'UPDATE ' . TABLE_USERS . ' SET login_attempts = ' . ($zData['locked']? 0 : 3) . ' WHERE id = "' . $_GET['lock'] . '"';
    $q = @mysql_query($sQ);
    if (!$q) {
        $sError = mysql_error(); // Save the mysql_error before it disappears.
        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader('setup_users_manage', 'LOVD Setup - Manage authorized users');
        lovd_dbFout('User' . $sAction, $sQ, $sError);
    }

    // Write to log...
    lovd_writeLog('Event', 'User' . $sAction, $_AUTH['username'] . ' (' . mysql_real_escape_string($_AUTH['name']) . ') successfully ' . strtolower($sAction) . 'ed user ' . $zData['username'] . ' (' . $zData['name'] . ')');

    // Return the user where they came from.
    // FIXME; wasn't this for the menu that was never implemented?
    $sAction = (!empty($_GET['return'])? $_GET['return'] . (isset($_GET[$_GET['return']])? '&' . $_GET['return'] . '=' . $_GET[$_GET['return']] : ''): 'view_all');
    header('Location: ' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?action=' . $sAction);
    exit;
}





if ($_GET['action'] == 'delete' && is_numeric($_GET['delete'])) {
    // Delete specific entry.

// Require manager clearance.
lovd_requireAUTH(LEVEL_MANAGER);

    $zData = @mysql_fetch_assoc(mysql_query('SELECT * FROM ' . TABLE_USERS . ' WHERE id = "' . $_GET['drop'] . '"'));
    if (!$zData) {
        // Wrong ID, apparently.
        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader('setup_users_manage', 'LOVD Setup - Manage authorized users');
        print('      No such ID!<BR>' . "\n");
        require ROOT_PATH . 'inc-bot.php';
        exit;
    }

    if ($zData['level'] >= $_AUTH['level']) {
        // This is a hack-attempt.
        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader('setup_users_manage', 'LOVD Setup - Manage authorized users');
        lovd_writeLog('Error', 'HackAttempt', $_AUTH['username'] . ' (' . mysql_real_escape_string($_AUTH['name']) . ') tried to drop ' . $zData['username'] . ' (' . mysql_real_escape_string($zData['name']) . ')');
        print('      Hack Attempt.<BR>' . "\n");
        require ROOT_PATH . 'inc-bot.php';
        exit;
    }

    // 2008-07-16; 2.0-09; Deleting a user makes the current user curator of the deleted user's genes if there is no curator left for them.
    // Find curated genes and see if they're alone.
    $q = mysql_query('SELECT t1.symbol FROM ' . TABLE_CURATES . ' AS t1 LEFT OUTER JOIN ' . TABLE_CURATES . ' AS t2 ON (t1.symbol = t2.symbol AND t1.id != t2.userid) WHERE t1.id = "' . $zData['id'] . '" AND t2.userid IS NULL');
    $aGenesCurate = array();
    while ($r = mysql_fetch_row($q)) {
        // Gene has no curator, and user is going to be deleted!
        $aGenesCurate[] = $r[0];
    }

    // Require form functions.
    require ROOT_PATH . 'inc-lib-form.php';

    if (isset($_GET['sent'])) {
        lovd_errorClean();

        // Mandatory fields.
        $aCheck =
                 array(
                        'password' => 'Enter your password for authorization',
                      );

        foreach ($aCheck as $key => $val) {
            if (empty($_POST[$key])) {
                lovd_errorAdd($key, 'Please fill in the \'' . $val . '\' field.');
            }
        }

        // User had to enter his/her password for authorization.
        if ($_POST['password'] && md5($_POST['password']) != $_AUTH['password']) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            // Query text; clean curator associations first.
            $sQ = 'DELETE FROM ' . TABLE_CURATES . ' WHERE userid = "' . $zData['id'] . '"';
            $q = mysql_query($sQ);
            if (!$q) {
                lovd_dbFout('UserDrop', $sQ, mysql_error(), false);
            }

            // 2008-07-16; 2.0-09; Deleting a user makes the current user curator of the deleted user's genes if there is no curator left for them.
            // Now check if we need to make the current user curator of some gene(s).
            if (count($aGenesCurate)) {
                foreach ($aGenesCurate as $sGene) {
                    $sQ = 'INSERT INTO ' . TABLE_CURATES . ' VALUES ("' . $_AUTH['id'] . '", "' . mysql_real_escape_string($sGene) . '")';
                    $q = mysql_query($sQ);
                    if (!$q) {
                        lovd_dbFout('UserDrop', $sQ, mysql_error(), false);
                    }
                }
            }

            // Query text.
            $sQ = 'UPDATE ' . TABLE_USERS . ' SET deleted = 1 WHERE id = "' . $zData['id'] . '"';

            $q = mysql_query($sQ);
            if (!$q) {
                $sError = mysql_error(); // Save the mysql_error before it disappears.
                require ROOT_PATH . 'inc-top.php';
                lovd_printHeader('setup_users_manage', 'LOVD Setup - Manage authorized users');
                lovd_dbFout('UserDrop', $sQ, $sError);
            }

            // Write to log...
            lovd_writeLog('Event', 'UserDrop', $_AUTH['username'] . ' (' . mysql_real_escape_string($_AUTH['name']) . ') successfully deleted user ' . $zData['username'] . ' (' . mysql_real_escape_string($zData['name']) . ')');

            // Thank the user...
            header('Refresh: 3; url=' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?action=view_all');

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader('setup_users_manage', 'LOVD Setup - Manage authorized users');
            print('      Successfully deleted user ' . $zData['name'] . '!<BR><BR>' . "\n\n");

            require ROOT_PATH . 'inc-bot.php';
            exit;

        } else {
            // Errors, so the whole lot returns to the form.
            lovd_magicUnquoteAll();

            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    }



    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader('setup_users_manage', 'LOVD Setup - Manage authorized users');

    lovd_errorPrint();

    // Table.
    print('      <FORM action="' . $_SERVER['PHP_SELF'] . '?action=' . $_GET['action'] . '&amp;drop=' . $zData['id'] . '&amp;sent=true" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array(
                    array('POST', '', '', '50%', '50%'),
                    array('Deleting user', 'print', $zData['username'] . ' (' . $zData['name'] . ')'),
                  );
    // 2008-07-16; 2.0-09; Deleting a user makes the current user curator of the deleted user's genes if there is no curator left for them.
    if (count($aGenesCurate)) {
        $aForm[] = array('&nbsp;', 'print', '<B>This user is the only curator of ' . count($aGenesCurate) . ' gene' . (count($aGenesCurate) == 1? '' : 's') . ': ' . implode(', ', $aGenesCurate) . '. You will become the curator of ' . (count($aGenesCurate) == 1? 'this gene' : 'these genes') . ' once this user is deleted.</B>');
    }
    $aForm = array_merge(
            $aForm,
             array(
                    'skip',
                    array('Enter your password for authorization', 'password', 'password', 20),
                    array('', 'submit', 'Delete user'),
                  ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}
*///////////////////////////////////////////////////////////////////////////////
?>
