<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-21
 * Modified    : 2011-03-31
 * For LOVD    : 3.0-pre-18
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

// Don't allow direct access.
if (!defined('ROOT_PATH')) {
    exit;
}
// Require parent class definition.
require_once ROOT_PATH . 'class/objects.php';





class LOVD_User extends LOVD_Object {
    // This class extends the basic Object class and it handles the User object.
    var $sObject = 'User';





    function LOVD_User ()
    {
        // Default constructor.

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = 'SELECT *, (login_attempts >= 3) AS locked FROM ' . TABLE_USERS . ' WHERE id = ?';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'u.*, (u.login_attempts >= 3) AS locked, GROUP_CONCAT(u2g.geneid ORDER BY u2g.geneid SEPARATOR ", ") AS curates_, c.name AS country_, uc.name AS created_by_, ue.name AS edited_by_';
        $this->aSQLViewEntry['FROM']     = TABLE_USERS . ' AS u LEFT JOIN ' . TABLE_CURATES . ' AS u2g ON (u.id = u2g.userid) LEFT JOIN ' . TABLE_COUNTRIES . ' AS c ON (u.countryid = c.id) LEFT JOIN ' . TABLE_USERS . ' AS uc ON (u.created_by = uc.id) LEFT JOIN ' . TABLE_USERS . ' AS ue ON (u.edited_by = ue.id)';
//        $this->aSQLViewEntry['GROUP_BY'] = 'u.id';

        // SQL code for viewing a list of entries.
        $this->aSQLViewList['SELECT']   = 'u.*, (u.login_attempts >= 3) AS locked, COUNT(u2g.geneid) AS curates, c.name AS country_';
        $this->aSQLViewList['FROM']     = TABLE_USERS . ' AS u LEFT JOIN ' . TABLE_CURATES . ' AS u2g ON (u.id = u2g.userid) LEFT JOIN ' . TABLE_COUNTRIES . ' AS c ON (u.countryid = c.id)';
        $this->aSQLViewList['GROUP_BY'] = 'u.id';
        $this->aSQLViewList['ORDER_BY'] = 'u.level DESC, u.name ASC';

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry =
                 array(
                        'id' => 'User ID',
                        'name' => 'Name',
                        'institute' => 'Institute',
                        'department' => 'Department',
                        'telephone' => 'Telephone',
                        'address' => 'Address',
                        'city' => 'City',
                        'country_' => 'Country',
                        'email' => 'Email address',
                        'reference' => 'Reference',
                        'username' => 'Username',
                        'password_force_change_' => 'Force change password',
                        'phpsessid' => 'Session ID',
                        'current_db' => 'Current gene',
                        'saved_work_' => 'Saved work',
                        'curates_' => 'Curator for',
//                        'submits' => 'Submits',
                        'level_' => 'User level',
                        'allowed_ip_' => 'Allowed IP address list',
                        'status_' => 'Status',
                        'locked_' => 'Locked',
                        'last_login' => 'Last login',
                        'created_by_' => 'Created by',
                        'created_date' => 'Date created',
                        'edited_by_' => 'Last edited by',
                        'edited_date' => 'Date last edited',
                      );

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList =
                 array(
                        'id' => array(
                                    'view' => array('ID', 45),
                                    'db'   => array('u.id', 'ASC', true)),
                        'name' => array(
                                    'view' => array('Name', 160),
                                    'db'   => array('u.name', 'ASC', true)),
                        'username' => array(
                                    'view' => array('Username', 80),
                                    'db'   => array('u.username', false, true)),
                        'institute' => array(
                                    'view' => array('Institute', 225),
                                    'db'   => array('u.institute', 'ASC', true)),
                        'country_' => array(
                                    'view' => array('Country', 200),
                                    'db'   => array('c.name', 'ASC', true)),
                        'curates' => array(
                                    'view' => array('Curated DBs', 100),
                                    'db'   => array('curates', 'DESC', 'INT_UNSIGNED')),
/*
                        'submits' => array(
                                    'view' => array('Submits', 75, 'align="right"'),
                                    'db'   => array('submits', 'DESC')),
*/
                        'status_' => array(
                                    'view' => array('Status', 50, 'align="center"')),
                        'last_login' => array(
                                    'view' => array('Last login', 80),
                                    'db'   => array('u.last_login', 'DESC', true)),
                        'created_date' => array(
                                    'view' => array('Started', 80),
                                    'db'   => array('u.created_date', 'ASC', true)),
                        'level' => array(
                                    'view' => array('Level', 150),
                                    'db'   => array('u.level', 'DESC', true)),
                      );
        $this->sSortDefault = 'level';

        parent::LOVD_Object();
    }





    function checkFields ($aData)
    {
        // Checks fields before submission of data.
        global $_AUTH, $_PATH_ELEMENTS, $_SETT;

        // Mandatory fields.
        $this->aCheckMandatory =
                 array(
                        'name',
                        'institute',
                        'address',
                        'city',
                        'countryid',
                        'email',
                      );
        if (lovd_getProjectFile() == '/install/index.php' || ACTION == 'create') {
            $this->aCheckMandatory = array_merge($this->aCheckMandatory,
                     array(
                            'username',
                            'password_1',
                            'password_2',
                          ));
        }
        if (lovd_getProjectFile() != '/install/index.php') {
            $this->aCheckMandatory[] = 'password';
        }
        if (lovd_getProjectFile() == '/users.php' && ACTION == 'change_password') {
            $this->aCheckMandatory =
                     array(
                            'password',
                            'password_1',
                            'password_2',
                          );
        }
        parent::checkFields($aData);

        // Email address.
        if (!empty($aData['email'])) {
            $aEmail = explode("\r\n", trim($aData['email']));
            foreach ($aEmail as $sEmail) {
                if (!lovd_matchEmail($sEmail)) {
                    lovd_errorAdd('email', 'Email "' . htmlspecialchars($sEmail) . '" is not a correct email address.');
                }
            }
        }

        if (lovd_getProjectFile() == '/install/index.php' || ACTION == 'create') {
            // Check username format.
            if ($aData['username'] && !lovd_matchUsername($aData['username'])) {
                lovd_errorAdd('username', 'Please fill in a correct username; 4 to 20 characters and starting with a letter followed by letters, numbers, dots, underscores and dashes only.');
            }
        }

        if (ACTION == 'create') {
            // Does the username exist already?
            if ($aData['username']) {
                if (mysql_num_rows(lovd_queryDB('SELECT id FROM ' . TABLE_USERS . ' WHERE username = ?', array($aData['username'])))) {
                    lovd_errorAdd('username', 'There is already a user with this username. Please choose another one.');
                }
            }
        }

        // One of two password fields entered... check 'em.
        if ($aData['password_1'] || $aData['password_2']) {
            if ($aData['password_1'] && $aData['password_2']) {
                // Both entered.
                if ($aData['password_1'] != $aData['password_2']) {
                    lovd_errorAdd('password_2', 'The \'' . (in_array(ACTION, array('edit', 'change_password'))? 'New p' : 'P') . 'assword\' fields are not equal. Please try again.');
                } else {
                    // Password quality.
                    if (!lovd_matchPassword($aData['password_1'])) {
                        lovd_errorAdd('password_1', 'Your password is found too weak. Please fill in a proper password; at least 4 characters long and containing at least one number or special character.');
                    }
                }
            } else {
                if (in_array(ACTION, array('edit', 'change_password'))) {
                    lovd_errorAdd('password_2', 'If you want to change the current password, please fill in both \'New password\' fields.');
                } else {
                    lovd_errorAdd('password_2', 'Please fill in both \'Password\' fields.');
                }
            }
        }

        // Check given security IP range.
        if (!empty($aData['allowed_ip']) && trim($aData['allowed_ip'])) {
            // This function will throw an error itself (second argument).
            $bIP = lovd_matchIPRange($aData['allowed_ip'], 'allowed_ip');

            if (lovd_getProjectFile() == '/install/index.php' || (ACTION == 'edit' && $_PATH_ELEMENTS[1] == $_AUTH['id'])) {
                // Check given security IP range.
                if ($bIP && !lovd_validateIP($aData['allowed_ip'], $_SERVER['REMOTE_ADDR'])) {
                    // This IP range is not allowing the current IP to connect. This ain't right.
                    lovd_errorAdd('allowed_ip', 'Your current IP address is not matched by the given IP range. This would mean you would not be able to get access to LOVD with this IP range.');
                }
            }

        } else {
            // We're not sure if $aData == $_POST. But we'll just do this. It can't harm I guess.
            $_POST['allowed_ip'] = '*';
        }

        // Level can't be higher or equal than the current user.
        if (!empty($aData['level']) && $aData['level'] >= $_AUTH['level']) {
            lovd_writeLog('Error', 'HackAttempt', 'Tried to upgrade user ID ' . $_PATH_ELEMENTS[1] . ' to level ' . $_SETT['user_levels'][$aData['level']] . ')');
            lovd_errorAdd('level', 'User level is not permitted. Hack attempt.');
        }

        if (lovd_getProjectFile() != '/install/index.php') {
            // User had to enter his/her password for authorization.
            if ($aData['password'] && md5($aData['password']) != $_AUTH['password']) {
                lovd_errorAdd('password', 'Please enter your correct password for authorization.');
            }
        }

        // XSS attack prevention. Deny input of HTML.
        lovd_checkXSS();
    }





    function getForm ()
    {
        // Build the form.
        global $_AUTH, $_SETT, $_PATH_ELEMENTS;

        $aUserLevels = $_SETT['user_levels'];

        $bInstall = (lovd_getProjectFile() == '/install/index.php');
        if ($bInstall) {
            // Very special case, we can't take it from the database, because it ain't there yet.
            require ROOT_PATH . 'install/inc-sql-countries.php';
            $aCountryList = array();
            foreach ($aCountrySQL as $sQ) {
                $aCountryList[substr($sQ, 22 + strlen(TABLE_COUNTRIES), 2)] = substr($sQ, 28 + strlen(TABLE_COUNTRIES), -2);
            }

        } else {
            // "Normal" user form; create user, edit user.
            $qCountryList = lovd_queryDB('SELECT id, name FROM ' . TABLE_COUNTRIES . ' ORDER BY name');

            // Remove user levels that are higher than or equal to the current user's level.
            unset($aUserLevels[3], $aUserLevels[5]); // Aren't real user levels.
            for ($i = 9; $i >= $_AUTH['level']; $i --) {
                if (isset($aUserLevels[$i])) {
                    unset($aUserLevels[$i]);
                }
            }

            // Get gene list, to select user as curator.
            $qGenes = lovd_queryDB('SELECT id, CONCAT(id, " (", name, ")") AS name FROM ' . TABLE_GENES . ' ORDER BY id');
            $nGenes = mysql_num_rows($qGenes);
            $nGeneSize = ($nGenes < 5? $nGenes : 5);
        }

        // Array which will make up the form table.
        $this->aFormData =
                 array(
                        array('POST', '', '', '', '50%', '14', '50%'),
                        array('', '', 'print', '<B>User details</B>'),
                        array('Name', '', 'text', 'name', 30),
                        array('Institute', '', 'text', 'institute', 40),
                        array('Department (optional)', '', 'text', 'department', 40),
                        array('Postal address', '', 'textarea', 'address', 35, 3),
                        array('Email address(es), one per line', '', 'textarea', 'email', 30, 3),
                        array('Telephone (optional)', '', 'text', 'telephone', 20),
          'username' => array('Username', '', 'text', 'username', 20),
            'passwd' => array('Password', '', 'password', 'password_1', 20),
    'passwd_confirm' => array('Password (confirm)', '', 'password', 'password_2', 20),
     'passwd_change' => array('Must change password at next logon', '', 'checkbox', 'password_force_change'),
                        'skip',
                        array('', '', 'print', '<B>Referencing the lab</B>'),
                        array('Country', '', 'select', 'countryid', 1, ($bInstall? $aCountryList : $qCountryList), true, false, false),
                        array('City', 'Please enter your city, even if it\'s included in your postal address, for sorting purposes.', 'text', 'city', 30),
                        array('Reference (optional)', 'Your submissions will contain a reference to you in the format "Country:City" by default. You may change this to your preferred reference here.', 'text', 'reference', 30),
                        'skip',
                        array('', '', 'print', '<B>Security</B>'),
             'level' => array('Level', '', 'select', 'level', 1, $aUserLevels, false, false, false),
                        array('Allowed IP address list', 'To help prevent others to try and guess the username/password combination, you can restrict access to the account to a number of IP addresses or ranges.', 'text', 'allowed_ip', 20),
                        array('', '', 'note', '<I>Your current IP address: ' . $_SERVER['REMOTE_ADDR'] . '</I><BR><B>Please be extremely careful using this setting.</B> Using this setting too strictly, can deny the user access to LOVD, even if the correct credentials have been provided.<BR>Set to \'*\' to allow all IP addresses, use \'-\' to specify a range and use \';\' to separate addresses or ranges.'),
            'locked' => array('Locked', '', 'checkbox', 'locked'),
'authorization_skip' => 'skip',
     'authorization' => array('Enter your password for authorization', '', 'password', 'password', 20));

        if ($bInstall) {
            // No need to ask for the user's password when the user is not created yet.
            unset($this->aFormData['authorization_skip'], $this->aFormData['authorization']);
        }
        if ($bInstall || (!empty($_PATH_ELEMENTS[1]) && $_PATH_ELEMENTS[1] == $_AUTH['id'])) {
            // Some fields not allowed when creating/editing your own account.
            unset($this->aFormData['passwd_change'], $this->aFormData['level'], $this->aFormData['locked']);
        }
        if (ACTION == 'edit') {
            unset($this->aFormData['username']);
            $this->aFormData['passwd'] = str_replace('Password', 'New password (optional)', $this->aFormData['passwd']);
            $this->aFormData['passwd_confirm'] = str_replace('Password (confirm)', 'New password (confirm, optional)', $this->aFormData['passwd_confirm']);
        } elseif (ACTION == 'change_password' && !$bInstall) {
            // Sorry, seems easier to just redefine the whole thing.
            $this->aFormData =
                 array(
                        array('POST', '', '', '', '50%', '14', '50%'),
       'change_self' => array('Current password', '', 'password', 'password', 20),
                        array('New password', '', 'password', 'password_1', 20),
                        array('New password (confirm)', '', 'password', 'password_2', 20),
                        'skip',
      'change_other' => array('Enter your password for authorization', '', 'password', 'password', 20));
            if ($_PATH_ELEMENTS[1] == $_AUTH['id']) {
                unset($this->aFormData['change_other']);
            } else {
                unset($this->aFormData['change_self']);
            }
        }

        return parent::getForm();
    }





    function prepareData ($zData = '', $sView = 'list')
    {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.
        global $_SETT;

        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        $zData['active'] = file_exists(session_save_path() . '/sess_' . $zData['phpsessid']);
        if ($sView == 'list') {
            $zData['row_id'] = $zData['id'];
            $zData['row_link'] = 'users/' . rawurlencode($zData['id']);
            $zData['name'] = '<A href="' . $zData['row_link'] . '" class="hide">' . $zData['name'] . '</A>';
            $sAlt = ($zData['active']? 'Online' : ($zData['locked']? 'Locked' : 'Offline'));
            $zData['status_'] = ($zData['locked'] || $zData['active']? '<IMG src="gfx/' . ($zData['locked']? 'status_locked' : 'status_online') . '.png" alt="' . $sAlt . '" title="' . $sAlt . '" width="14" height="14">' : '');
            $zData['last_login'] = substr($zData['last_login'], 0, 10);
            $zData['created_date'] = substr($zData['created_date'], 0, 10);
            $zData['level'] = str_replace(' ', '&nbsp;', $_SETT['user_levels'][$zData['level']]);

        } else {
            $zData['password_force_change_'] = ($zData['password_force_change']? '<IMG src="gfx/mark_1.png" alt="" width="11" height="11"> Yes' : 'No');
            if ($zData['saved_work']) {
                // Do something later.
            } else {
                $zData['saved_work_'] = 'N/A';
            }
            $zData['level_'] = $_SETT['user_levels'][$zData['level']];
            $zData['allowed_ip_'] = preg_replace('/[;,]+/', '<BR>', $zData['allowed_ip']);
            $zData['status_'] = ($zData['active']? '<IMG src="gfx/status_online.png" alt="Online" title="Online" width="14" height="14" align="top"> Online' : 'Offline');
            $zData['locked_'] = ($zData['locked']? '<IMG src="gfx/status_locked.png" alt="Locked" title="Locked" width="14" height="14" align="top"> Locked' : 'No');
/*
    $zData['submits_'] = $zData['submits'] . ($zData['submits']? ' (<A href="' . ROOT_PATH . 'submitters_variants.php?submitterid=' . $zData['submitterid'] . '&all_genes">view</A>)' : '');
*/
        }
        return $zData;
    }





    function setDefaultValues ()
    {
        // Sets default values of fields in $_POST.
        $_POST['allowed_ip'] = '*';
        return true;
    }
}
?>
