<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-21
 * Modified    : 2016-10-14
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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

// Don't allow direct access.
if (!defined('ROOT_PATH')) {
    exit;
}
// Require parent class definition.
require_once ROOT_PATH . 'class/objects.php';





class LOVD_User extends LOVD_Object {
    // This class extends the basic Object class and it handles the User object.
    var $sObject = 'User';





    function __construct ()
    {
        // Default constructor.
        global $_SETT;

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = 'SELECT *, (login_attempts >= 3) AS locked ' .
                               'FROM ' . TABLE_USERS . ' ' .
                               'WHERE id = ? AND id > 0';

        // SQL code to insert the level names into the database output, so it can be searched on.
        $sLevelQuery = '';
        $aLevels = $_SETT['user_levels'];
        unset($aLevels[LEVEL_OWNER]);
        foreach ($_SETT['user_levels'] as $nLevel => $sLevel) {
            $sLevelQuery .= ' WHEN "' . $nLevel . '" THEN "' . $nLevel . $sLevel . '"';
        }

        // SQL code for preparing view entry query.
        // Increase DB limits to allow concatenation of large number of gene IDs.
        $this->sSQLPreViewEntry = 'SET group_concat_max_len = 200000';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'u.*, ' .
                                           '(u.login_attempts >= 3) AS locked, ' .
                                           'GROUP_CONCAT(DISTINCT CASE u2g.allow_edit WHEN "1" THEN u2g.geneid END ORDER BY u2g.geneid SEPARATOR ";") AS _curates, ' .
                                           'GROUP_CONCAT(DISTINCT CASE u2g.allow_edit WHEN "0" THEN u2g.geneid END ORDER BY u2g.geneid SEPARATOR ";") AS _collaborates, ' .
                                           'GROUP_CONCAT(DISTINCT col.userid_to, ";", ucol.name SEPARATOR ";;") AS __colleagues,' .
                                           'c.name AS country_, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_, ' .
                                           'GREATEST(u.level, IFNULL(CASE MAX(u2g.allow_edit) WHEN 1 THEN ' . LEVEL_CURATOR . ' WHEN 0 THEN ' . LEVEL_COLLABORATOR . ' END, ' . LEVEL_SUBMITTER . ')) AS level';
        $this->aSQLViewEntry['FROM']     = TABLE_USERS . ' AS u ' .
                                           'LEFT OUTER JOIN ' . TABLE_CURATES . ' AS u2g ON (u.id = u2g.userid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_COUNTRIES . ' AS c ON (u.countryid = c.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (u.created_by = uc.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (u.edited_by = ue.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_COLLEAGUES . ' AS col ON (u.id = col.userid_from) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ucol ON (col.userid_to = ucol.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 'u.id';

        // SQL code for viewing a list of users.
        $this->aSQLViewList['SELECT']   = 'u.*, (u.login_attempts >= 3) AS locked, ' .
                                          'COUNT(CASE u2g.allow_edit WHEN 1 THEN u2g.geneid END) AS curates, ' .
                                          'c.name AS country_, ' .
                                          'GREATEST(u.level, IFNULL(CASE MAX(u2g.allow_edit) WHEN 1 THEN ' . LEVEL_CURATOR . ' WHEN 0 THEN ' . LEVEL_COLLABORATOR . ' END, ' . LEVEL_SUBMITTER . ')) AS level, ' .
                                          'CASE GREATEST(u.level, IFNULL(CASE MAX(u2g.allow_edit) WHEN 1 THEN ' . LEVEL_CURATOR . ' WHEN 0 THEN ' . LEVEL_COLLABORATOR . ' END, ' . LEVEL_SUBMITTER . '))' . $sLevelQuery . ' END AS level_';
        $this->aSQLViewList['FROM']     = TABLE_USERS . ' AS u ' .
                                          'LEFT OUTER JOIN ' . TABLE_CURATES . ' AS u2g ON (u.id = u2g.userid) ' .
                                          'LEFT OUTER JOIN ' . TABLE_COUNTRIES . ' AS c ON (u.countryid = c.id)';
        $this->aSQLViewList['WHERE']    = 'u.id > 0';
        $this->aSQLViewList['GROUP_BY'] = 'u.id';
        $this->aSQLViewList['ORDER_BY'] = 'level DESC, u.name ASC';

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry =
                 array(
                        'id' => 'User ID',
                        'orcid_id_' => 'ORCID ID',
                        'name' => 'Name',
                        'institute' => 'Institute',
                        'department' => 'Department',
                        'telephone' => array('Telephone', LEVEL_CURATOR),
                        'address' => array('Address', LEVEL_CURATOR),
                        'city' => 'City',
                        'country_' => 'Country',
                        'email' => array('Email address', LEVEL_CURATOR),
                        'reference' => 'Reference',
                        'username' => array('Username', LEVEL_MANAGER),
                        'password_force_change_' => array('Force change password', LEVEL_MANAGER),
                        'phpsessid' => array('Session ID', LEVEL_MANAGER),
                        'saved_work_' => array('Saved work', LEVEL_MANAGER),
                        'curates_' => 'Curator for',
                        'collaborates_' => array('Collaborator for', LEVEL_CURATOR),
                        'ownes_' => 'Data owner for', // Will be unset if user is not authorized on this user (i.e., not himself or manager or up).
                        'colleagues_' => '', // Other users that may access this user's data.
                        'level_' => array('User level', LEVEL_CURATOR),
                        'allowed_ip_' => array('Allowed IP address list', LEVEL_MANAGER),
                        'status_' => array('Status', LEVEL_MANAGER),
                        'locked_' => array('Locked', LEVEL_MANAGER),
                        'last_login' => array('Last login', LEVEL_MANAGER),
                        'created_by_' => array('Created by', LEVEL_CURATOR),
                        'created_date' => array('Date created', LEVEL_CURATOR),
                        'edited_by_' => array('Last edited by', LEVEL_MANAGER),
                        'edited_date_' => array('Date last edited', LEVEL_MANAGER),
                      );

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList =
                 array(
                        'userid' => array(
                                    'view' => false,
                                    'db'   => array('u.id', 'ASC', true)),
                        'id' => array(
                                    'view' => array('ID', 45),
                                    'db'   => array('u.id', 'ASC', true)),
                        'orcid_id_' => array(
                                    'view' => array('ORCiD', 60, 'style="text-align : center;"'),
                                    'db'   => array('(u.orcid_id is not null)', 'DESC', true),
                                    'legend' => array('The user\'s ORCID ID, a unique digital identifier.', 'The user\'s ORCID ID, a persistent digital identifier that distinguishes you from every other researcher and supports automated linkages between you and your professional activities ensuring that your work is recognized.')),
                        'name' => array(
                                    'view' => array('Name', 160),
                                    'db'   => array('u.name', 'ASC', true)),
                        'username' => array(
                                    'view' => array('Username', 80),
                                    'db'   => array('u.username', 'ASC', true)),
                        'institute' => array(
                                    'view' => array('Institute', 225),
                                    'db'   => array('u.institute', 'ASC', true)),
                        'country_' => array(
                                    'view' => array('Country', 200),
                                    'db'   => array('c.name', 'ASC', true)),
                        'curates' => array(
                                    'view' => array('Curated DBs', 100, 'style="text-align : right;"'),
                                    'db'   => array('curates', 'DESC', 'INT_UNSIGNED'),
                                    'legend' => array('Shows how many gene databases have this user assigned as curator.')),
/*
                        'submits' => array(
                                    'view' => array('Submits', 75, 'style="text-align : right;"'),
                                    'db'   => array('submits', 'DESC', 'INT_UNSIGNED')),
*/
                        'status_' => array(
                                    'view' => array('Status', 50, 'style="text-align : center;"'),
                                    'legend' => array('Shows whether this user is online (computer screen icon), locked (forbidden entry icon), or offline (no icon).')),
                        'last_login_' => array(
                                    'view' => array('Last login', 80),
                                    'db'   => array('u.last_login', 'DESC', true)),
                        'created_date_' => array(
                                    'view' => array('Started', 80),
                                    'db'   => array('u.created_date', 'ASC', true)),
                        'level_' => array(
                                    'view' => array('Level', 150),
                                    'db'   => array('level_', 'DESC', 'TEXT')),
                      );
        // In safe mode, the status check doesn't work anyway, because we're not allowed to access the session file.
        if (ini_get('safe_mode')) {
            unset($this->aColumnsViewEntry['status_']);
        }
        $this->sSortDefault = 'level_';

        // Because the user information is publicly available, remove some columns for the public.
        // FIXME; Dit moet eigenlijk per user anders; curatoren mogen deze info wel van submitters zien.
        // Dus eigenlijk if ($_AUTH['level'] <= $zData['level']) maar we hebben hier geen $zData...
        $this->unsetColsByAuthLevel();

        parent::__construct();
    }





    function checkFields ($aData, $zData = false)
    {
        // Checks fields before submission of data.
        global $_AUTH, $_DB, $_PE, $_SETT;

        // Mandatory fields.
        $this->aCheckMandatory =
                 array(
                        'name',
                        'institute',
                        'address',
                        'city',
                        'countryid',
                        'email',
                        'username', // LOVD will not complain if a mandatory column has not been added to the form.
                      );

        // These password fields are only not mandatory when we're editing.
        if (ACTION != 'edit') {
            $this->aCheckMandatory[] = 'password_1';
            $this->aCheckMandatory[] = 'password_2';
        }
        parent::checkFields($aData);

        // Email address.
        if (!empty($aData['email'])) {
            $aEmail = explode("\r\n", $aData['email']);
            foreach ($aEmail as $sEmail) {
                if (!lovd_matchEmail($sEmail)) {
                    lovd_errorAdd('email', 'Email "' . htmlspecialchars($sEmail) . '" is not a correct email address' . (($sEmail && $sEmail == trim($sEmail))? '' : '. Make sure there are no spaces or empty lines left in the email field') . '.');
                }
            }
        }

        if (lovd_getProjectFile() == '/install/index.php' || ACTION == 'create') {
            // Check username format.
            if ($aData['username'] && !lovd_matchUsername($aData['username'])) {
                lovd_errorAdd('username', 'Please fill in a correct username; 4 to 20 characters and starting with a letter followed by letters, numbers, dots, underscores and dashes only.');
            }
        }

        if (in_array(ACTION, array('create', 'register'))) {
            // Does the username exist already?
            if ($aData['username']) {
                if ($_DB->query('SELECT COUNT(*) FROM ' . TABLE_USERS . ' WHERE username = ?', array($aData['username']))->fetchColumn()) {
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
        if (!empty($aData['allowed_ip'])) {
            // This function will throw an error itself (second argument).
            $bIP = lovd_matchIPRange($aData['allowed_ip'], 'allowed_ip');

            if (lovd_getProjectFile() == '/install/index.php' || (ACTION == 'edit' && $_PE[1] == $_AUTH['id'])) {
                // Check given security IP range.
                if ($bIP && !lovd_validateIP($aData['allowed_ip'], $_SERVER['REMOTE_ADDR'])) {
                    // This IP range is not allowing the current IP to connect. This ain't right.
                    // If IP address is actually IPv6, then complain that we can't restrict at all.
                    // Otherwise, be clear the current setting just doesn't match.
                    if (strpos($_SERVER['REMOTE_ADDR'], ':') !== false) {
                        // IPv6...
                        lovd_errorAdd('allowed_ip', 'Your current IP address is IPv6 (' . $_SERVER['REMOTE_ADDR'] . '), which is not supported by LOVD to restrict access to your account.');
                    } else {
                        lovd_errorAdd('allowed_ip', 'Your current IP address is not matched by the given IP range. This would mean you would not be able to get access to LOVD with this IP range.');
                    }
                }
            }

        } else {
            // We're not sure if $aData == $_POST. But we'll just do this. It can't harm I guess.
            $_POST['allowed_ip'] = '*';
        }

        // Level can't be higher or equal than the current user.
        if (!empty($aData['level']) && $aData['level'] >= $_AUTH['level']) {
            lovd_writeLog('Error', 'HackAttempt', 'Tried to upgrade user ID ' . $_PE[1] . ' to level ' . $_SETT['user_levels'][$aData['level']] . ')');
            lovd_errorAdd('level', 'User level is not permitted. Hack attempt.');
        }

        // XSS attack prevention. Deny input of HTML.
        lovd_checkXSS();
    }





    function getForm ()
    {
        // Build the form.

        // If we've built the form before, simply return it. Especially imports will repeatedly call checkFields(), which calls getForm().
        if (!empty($this->aFormData)) {
            return parent::getForm();
        }

        global $_AUTH, $_DB, $_SETT, $_PE;

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
            $aCountryList = $_DB->query('SELECT id, name FROM ' . TABLE_COUNTRIES . ' ORDER BY name')->fetchAllCombine();

            if ($_AUTH) {
                // Remove user levels that are higher than or equal to the current user's level IF you are logged in.
                unset($aUserLevels[LEVEL_COLLABORATOR], $aUserLevels[LEVEL_OWNER], $aUserLevels[LEVEL_CURATOR]); // Aren't real user levels.
                for ($i = LEVEL_ADMIN; $i >= $_AUTH['level']; $i --) {
                    if (isset($aUserLevels[$i])) {
                        unset($aUserLevels[$i]);
                    }
                }
            }
        }

        // FIXME; this is a mess...!!!
        // Array which will make up the form table.
        $this->aFormData =
                 array(
                        array('POST', '', '', '', '35%', '14', '65%'),
                        array('', '', 'print', '<B>User details</B>'),
                        'hr',
                        array('Name', '', 'text', 'name', 30),
                        array('Institute', '', 'text', 'institute', 40),
                        array('Department (optional)', '', 'text', 'department', 40),
                        array('Postal address', '', 'textarea', 'address', 35, 3),
                        array('Email address(es), one per line', '', 'textarea', 'email', 30, 3),
                        array('Telephone (optional)', '', 'text', 'telephone', 20),
          'username' => array('Username', '', 'text', 'username', 20),
            'passwd' => array('Password', 'A proper password is at least 4 characters long and contains at least one number or special character.', 'password', 'password_1', 20, true),
    'passwd_confirm' => array('Password (confirm)', '', 'password', 'password_2', 20, true),
     'passwd_change' => array('Must change password at next logon', '', 'checkbox', 'password_force_change'),
                        'hr',
                        'skip',
                        array('', '', 'print', '<B>Referencing the lab</B>'),
                        'hr',
                        array('Country', '', 'select', 'countryid', 1, $aCountryList, true, false, false),
                        array('City', 'Please enter your city, even if it\'s included in your postal address, for sorting purposes.', 'text', 'city', 30),
                        array('Reference (optional)', 'Your submissions will contain a reference to you in the format "Country:City" by default. You may change this to your preferred reference here.', 'text', 'reference', 30),
                        'hr',
                        'skip',
                        array('', '', 'print', '<B>Security</B>'),
                        'hr',
             'level' => array('Level', ($_AUTH['level'] != LEVEL_ADMIN? '' : '<B>Managers</B> basically have the same rights as you, but can\'t uninstall LOVD nor can they create or edit other Manager accounts.<BR>') . '<B>Submitters</B> can submit, but not publish information in the database. Submitters can also create their own accounts, you don\'t need to do this for them.<BR><BR>In LOVD 3.0, <B>Curators</B> are Submitter-level users with Curator rights on certain genes. To create a Curator account, you need to create a Submitter and then grant this user rights on the necessary genes.', 'select', 'level', 1, $aUserLevels, false, false, false),
                        array('Allowed IP address list (optional)', 'To help prevent others to try and guess the username/password combination, you can restrict access to the account to a number of IP addresses or ranges.', 'text', 'allowed_ip', 20),
                        array('', '', 'note', 'Default value: *<BR>' . (strpos($_SERVER['REMOTE_ADDR'], ':') !== false? '' : '<I>Your current IP address: ' . $_SERVER['REMOTE_ADDR'] . '</I><BR>') . '<B>Please be extremely careful using this setting.</B> Using this setting too strictly, can deny the user access to LOVD, even if the correct credentials have been provided.<BR>Set to \'*\' to allow all IP addresses, use \'-\' to specify a range and use \';\' to separate addresses or ranges.'),
            'locked' => array('Locked', '', 'checkbox', 'locked'),
                        'hr',
'authorization_skip' => 'skip',
        'send_email' => array('Send email with account details to user', '', 'checkbox', 'send_email'),
     'authorization' => array('Enter your password for authorization', '', 'password', 'password', 20),
                      );

        if ($bInstall || ACTION != 'create') {
            unset($this->aFormData['send_email']);
        }
        if ($bInstall || ACTION == 'register') {
            // No need to ask for the user's password when the user is not created yet.
            unset($this->aFormData['authorization_skip'], $this->aFormData['authorization']);
        }
        if ($bInstall || (!empty($_PE[1]) && $_PE[1] == $_AUTH['id']) || ACTION == 'register') {
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
                        array('New password', '', 'password', 'password_1', 20, true),
                        array('New password (confirm)', '', 'password', 'password_2', 20, true),
                        'skip',
      'change_other' => array('Enter your password for authorization', '', 'password', 'password', 20));
            if ($_PE[1] == $_AUTH['id']) {
                unset($this->aFormData['change_other']);
            } else {
                unset($this->aFormData['change_self']);
            }
        }
        if (LOVD_plus && isset($this->aFormData['level'])) {
            $this->aFormData['level'][1] = ($_AUTH['level'] != LEVEL_ADMIN? '' : '<B>Managers</B> basically have the same rights as you, but can\'t uninstall LOVD nor can they create or edit other Manager accounts.<BR>') . '<B>Analyzers</B> can analyze individuals that are not analyzed yet by somebody else, but can not send variants for confirmation.<BR><B>Read-only</B> users can only see existing data in LOVD+, but can not start or edit any analyses or data.';
        }
        return parent::getForm();
    }





    function prepareData ($zData = '', $sView = 'list')
    {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.
        global $_DB, $_SETT;

        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        $zData['active'] = file_exists(session_save_path() . '/sess_' . $zData['phpsessid']);
        if ($sView == 'list') {
            $zData['orcid_id_'] = (!$zData['orcid_id']? '&nbsp;' : '<IMG src="gfx/check.png" alt="' . $zData['orcid_id'] . '" title="' . $zData['orcid_id'] . '">');
            $zData['name'] = '<A href="' . $zData['row_link'] . '" class="hide">' . $zData['name'] . '</A>';
            $sAlt = ($zData['active']? 'Online' : ($zData['locked']? 'Locked' : 'Offline'));
            $zData['status_'] = ($zData['locked'] || $zData['active']? '<IMG src="gfx/' . ($zData['locked']? 'status_locked' : 'status_online') . '.png" alt="' . $sAlt . '" title="' . $sAlt . '" width="14" height="14">' : '');
            $zData['last_login_'] = substr($zData['last_login'], 0, 10);
            $zData['created_date_'] = substr($zData['created_date'], 0, 10);
            $zData['level_'] = substr($zData['level_'], 1);

        } else {
            $zData['orcid_id_'] = '';
            if ($zData['orcid_id']) {
                $zData['orcid_id_'] = '<A href="http://orcid.org/' . $zData['orcid_id'] . '" target="_blank">' . $zData['orcid_id'] . '</A>';
            }
            $zData['password_force_change_'] = ($zData['password_force_change']? '<IMG src="gfx/mark_1.png" alt="" width="11" height="11"> Yes' : 'No');
            if (!empty($zData['saved_work'])) {
                $zData['saved_work'] = unserialize(htmlspecialchars_decode($zData['saved_work']));
                if (!empty($zData['saved_work']['submissions']['individual']) || !empty($zData['saved_work']['submissions']['screening'])) {
                    $zData['saved_work_'] = '<A href="users/' . $zData['id'] . '?submissions">Unfinished submissions</A>';
                } else {
                    $zData['saved_work_'] = 'N/A';
                }
            } else {
                $zData['saved_work_'] = 'N/A';
            }
            // Provide links to gene symbols this user is curator and collaborator for. Easy access to one's own genes.
            $this->aColumnsViewEntry['curates_'] .= ' ' . count($zData['curates']) . ' gene' . (count($zData['curates']) == 1? '' : 's');
            if (isset($this->aColumnsViewEntry['collaborates_'])) {
                // This is only visible for Curators, so we don't want to mess around with aColumnsViewEntry when this field is no longer there.
                $this->aColumnsViewEntry['collaborates_'][0] .= ' ' . count($zData['collaborates']) . ' gene' . (count($zData['collaborates']) == 1? '' : 's');
            }

            // Get HTML links for genes curated by current user.
            $zData['curates_'] = $this->lovd_getObjectLinksHTML($zData['curates'], 'genes/%s');

            $zData['collaborates_'] = '';
            foreach ($zData['collaborates'] as $key => $sGene) {
                $zData['collaborates_'] .= (!$key? '' : ', ') . '<A href="genes/' . $sGene . '">' . $sGene . '</A>';
            }

            // Submissions...
            if (lovd_isAuthorized('user', $zData['id']) === false) {
                // Not authorized to view hidden data for this user; so we're not manager and we're not viewing ourselves. Nevermind then.
                unset($this->aColumnsViewEntry['ownes_']);
            } else {
                // Either we're viewing ourselves, or we're manager or up. Like this is easy, because now we don't need to check for the data status of the data.
                $nOwnes = 0;
                $sOwnes = '';

                // FIXME: Phenotypes is not included, because we don't have a phenotypes overview to link to (must be disease-specific).
                foreach (array('individuals', 'screenings', 'variants') as $sDataType) {
                    $n = $_DB->query('SELECT COUNT(*) FROM ' . constant('TABLE_' . strtoupper($sDataType)) . ' WHERE owned_by = ?', array($zData['id']))->fetchColumn();
                    if ($n) {
                        $nOwnes += $n;
                        $sOwnes .= (!$sOwnes? '' : ', ') . '<A href="' . $sDataType . '?search_owned_by_=%3D%22' . rawurlencode(html_entity_decode($zData['name'])) . '%22">' . $n . ' ' . ($n == 1? substr($sDataType, 0, -1) : $sDataType) . '</A>';
                    }
                }

                $this->aColumnsViewEntry['ownes_'] .= ' ' . $nOwnes . ' data entr' . ($nOwnes == 1? 'y' : 'ies');
                $zData['ownes_'] = $sOwnes;
            }

            $this->aColumnsViewEntry['colleagues_'] = 'Shares access with ' . count($zData['colleagues']) . ' user' . (count($zData['colleagues']) == 1? '' : 's');
            $zData['colleagues_'] = $this->lovd_getObjectLinksHTML($zData['colleagues'], 'users/%s');

            $zData['allowed_ip_'] = preg_replace('/[;,]+/', '<BR>', $zData['allowed_ip']);
            $zData['status_'] = ($zData['active']? '<IMG src="gfx/status_online.png" alt="Online" title="Online" width="14" height="14" align="top"> Online' : 'Offline');
            $zData['locked_'] = ($zData['locked']? '<IMG src="gfx/status_locked.png" alt="Locked" title="Locked" width="14" height="14" align="top"> Locked' : 'No');
            $zData['level_'] = $_SETT['user_levels'][$zData['level']];
        }
        return $zData;
    }





    function setDefaultValues ()
    {
        // Sets default values of fields in $_POST.
        $_POST['allowed_ip'] = '*';
        $_POST['send_email'] = 1;
        return true;
    }
}
?>
