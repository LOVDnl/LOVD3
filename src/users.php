<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-01-14
 * Modified    : 2020-08-27
 * For LOVD    : 3.0-25
 *
 * Copyright   : 2004-2020 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Daan Asscheman <D.Asscheman@LUMC.nl>
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
require ROOT_PATH . 'inc-init.php';
require ROOT_PATH . 'inc-lib-users.php';

if ($_AUTH) {
    // If authorized, check for updates.
    require ROOT_PATH . 'inc-upgrade.php';
}

if (PATH_COUNT >= 2 && $_PE[1] == '00000') {
    exit; // Block access to the LOVD account with ID = 0.
}





if (PATH_COUNT == 1 && !ACTION) {
    // URL: /users
    // View all entries.

    // Managers are allowed to download this list...
    if ($_AUTH['level'] >= LEVEL_MANAGER) {
        define('FORMAT_ALLOW_TEXTPLAIN', true);
    }

    define('PAGE_TITLE', 'User accounts');
    $_T->printHeader();
    $_T->printTitle();

    // FIXME; we need to think about this. To create a public submitters list, will we have a modified viewList()?
    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_users.php';
    $_DATA = new LOVD_User();
    $_DATA->viewList('Users', array('show_options' => ($_AUTH['level'] >= LEVEL_MANAGER)));

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && !ACTION) {
    // URL: /users/00001
    // View specific entry.

    $nID = lovd_getCurrentID();
    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    $_T->printHeader();
    $_T->printTitle();

    // Require valid user.
    // If not viewing himself, the user may see very little information (low level) or all data (high level).
    lovd_requireAUTH();

    // Enable LEVEL_COLLABORATOR and LEVEL_CURATOR for object_users.php.
    // Those levels will see more fields of the given user.
    lovd_isAuthorized('gene', $_AUTH['curates']);

    if ($nID == $_AUTH['id'] && $_AUTH['level'] == LEVEL_SUBMITTER && isset($_GET['new_submitter'])) {
        // Newly registered? Explain where to submit.
        lovd_showDialog('dialog_new_submitter', 'Now that you\'ve registered', 'Now that you\'ve registered, you can submit new variant data to this database.<BR>You can do so, by clicking the Submit menu tab just above this message.',
            'information', array('position' => '{my:"left top",at:"left bottom",of:"#tab_submit"}', 'buttons' => '{"Go there now":function(){window.location.href="' . lovd_getInstallURL() . 'submit";},"Close":function(){$(this).dialog("close");}}'));
    }

    // 2014-03-13; 3.0-10; Users viewing their own profile should see a lot more...
    if ($_AUTH['id'] == $nID && $_AUTH['level'] < LEVEL_CURATOR) {
        $_AUTH['level'] = LEVEL_CURATOR;
    }

    require ROOT_PATH . 'class/object_users.php';
    $_DATA = new LOVD_User();

    print('      <DIV id="viewentryDiv">' . "\n");
    $zData = $_DATA->viewEntry($nID);
    print('      </DIV>' . "\n\n");

    $aNavigation = array();
    // Since we're faking the user's level to show some more columns when the user is viewing himself, we must put the check on the ID here.
    if ($_AUTH['id'] != $nID && $_AUTH['level'] >= LEVEL_MANAGER && $_AUTH['level'] > $zData['level']) {
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
        $aNavigation['download/all/user/' . $nID]    = array('menu_save.png', 'Download all this user\'s data', 1);
    } elseif ($_AUTH['id'] == $nID) {
        // Viewing himself!
        $aNavigation[CURRENT_PATH . '?edit'] = array('menu_edit.png', 'Update your registration', 1);
        $aNavigation['download/all/mine']    = array('menu_save.png', 'Download all my data', 1);
    } elseif ($_AUTH['level'] >= LEVEL_MANAGER) {
        // Managers and up, not viewing own account, not higher level than other user.
        $aNavigation['download/all/user/' . $nID]    = array('menu_save.png', 'Download all this user\'s data', 1);
    }

    if ($_AUTH['id'] == $nID || ($_AUTH['level'] >= LEVEL_MANAGER && $_AUTH['level'] > $zData['level'])) {
        $aNavigation[CURRENT_PATH . '?share_access'] = array('', 'Share access to ' . ($_AUTH['id'] == $nID? 'your' : 'user\'s') . ' entries with other users', 1);
    }

    lovd_showJGNavigation($aNavigation, 'Users');



    if ($_AUTH['level'] >= LEVEL_MANAGER) {
        print('<BR><BR>' . "\n\n");
        $_T->printTitle('Log entries by this user', 'H4');

        require ROOT_PATH . 'class/object_logs.php';
        $_DATA = new LOVD_Log();
        $_GET['page_size'] = 10;
        $_GET['search_userid'] = $nID;
        $aVLOptions = array(
            'cols_to_skip' => array('user_', 'del'),
            'track_history' => false,
        );
        $_DATA->viewList('Logs_for_Users_VE', $aVLOptions);
    }

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 1 && in_array(ACTION, array('create', 'register'))) {
    // URL: /users?create
    // URL: /users?register
    // Create a new user, or self-register a new submitter.

    define('LOG_EVENT', 'User' . ucfirst(ACTION));
    if (ACTION == 'create') {
        define('PAGE_TITLE', lovd_getCurrentPageTitle());

        // Require manager clearance.
        lovd_requireAUTH(LEVEL_MANAGER);

    } else {
        define('PAGE_TITLE', 'Register as new submitter');

        if (LOVD_plus || $_AUTH || !$_CONF['allow_submitter_registration']) {
            $_T->printHeader();
            $_T->printTitle();
            if (!$_CONF['allow_submitter_registration']) {
                $sGeneSymbol = ($_SESSION['currdb']? $_SESSION['currdb'] : 'DMD'); // Used as an example gene symbol, use the current gene symbol if possible.
                $sMessage = 'Submitter registration is not active in this LOVD installation. If you wish to submit data, please check the list of gene variant databases in our <A href="http://www.LOVD.nl/LSDBs" target="_blank">list of LSDBs</A>.<BR>Our LSDB list can also be reached by typing <I>GENESYMBOL.lovd.nl</I> in your browser address bar, like <I><A href="http://' . $sGeneSymbol . '.lovd.nl" target="_blank">' . $sGeneSymbol . '.lovd.nl</A></I>.';
            } elseif (LOVD_plus) {
                // LOVD+ doesn't allow for submitter registrations, because submitters already achieve rights.
                $sMessage = 'You can not register as a submitter at this LOVD+ installation. Ask the Manager or Administrator for an account.';
            } else {
                $sMessage = 'You are already a registered user.';
            }
            lovd_showInfoTable($sMessage, 'stop');
            $_T->printFooter();
            exit;
        }
    }

    require ROOT_PATH . 'inc-lib-form.php';



    if (!POST || empty($_POST['orcid_id'])) {
        if (isset($_GET['no_orcid'])) {
            $_POST['orcid_id'] = 'none';
            $_SESSION['orcid_data'] = array();
        } else {
            // Ask the user if he has an ORCID ID. If not, suggest him to register.
            if (POST) {
                lovd_errorClean();

                // Check format of ID.
                if (!preg_match('/^([0-9]{4}-?){3}[0-9]{3}[0-9X]$/', $_POST['orcid'])) {
                    lovd_errorAdd('orcid', 'The given ORCID ID does not match the ORCID ID format.');
                } elseif (!lovd_checkORCIDChecksum($_POST['orcid'])) {
                    // Checksum not valid!
                    lovd_errorAdd('orcid', 'The given ORCID ID is not valid.');
                } elseif ($_DB->query('SELECT COUNT(*) FROM ' . TABLE_USERS . ' WHERE orcid_id = ?', array($_POST['orcid']))->fetchColumn()) {
                    // ID is not unique!
                    lovd_errorAdd('orcid', 'There is already an account registered with this ORCID ID.' . (!$_CONF['allow_unlock_accounts']? '' : ' Did you <A href="reset_password">forget your password</A>?'));
                } else {
                    // Contact ORCID to retrieve public info.
                    // Fix ORCID API to a certain version, but note that ORCID's API can drop the version if they feel like it.
                    $aOutput = lovd_php_file('https://pub.orcid.org/v2.1/' . $_POST['orcid'], false, '', 'Accept: application/orcid+json');
                    if (!$aOutput) {
                        lovd_errorAdd('orcid', 'The given ORCID ID can not be found at ORCID.org.');
                    } else {
                        $aORCID = array(
                            'orcid-identifier' => array('path' => ''),
                            'person' => array(
                                'name' => array(
                                    'family-name' => array('value' => ''),
                                    'given-names' => array('value' => ''),
                                    'credit-name' => array('value' => ''),
                                ),
                                'emails' => array(
                                    'email' => array(
                                        0 => array('email' => ''),
                                    ),
                                ),
                                'addresses' => array(
                                    'address' => array(
                                        0 => array(
                                            'country' => array('value' => ''),
                                        ),
                                    ),
                                ),
                            ),
                            'activities-summary' => array(
                                'employments' => array(
                                    'employment-summary' => array(
                                        0 => array(
                                            'department-name' => '',
                                            'role-title' => '',
                                            'start-date' => array(
                                                'year' => array('value' => ''),
                                                'month' => array('value' => ''),
                                                'day' => array('value' => ''),
                                            ),
                                            'end-date' => null,
                                            'organization' => array(
                                                'name' => '',
                                                'address' => array(
                                                    'city' => '',
                                                    'country' => '',
                                                ),
                                            ),
                                        ),
                                    ),
                                ),
                            ),
                            'history' => array(
                                'verified-email' => false,
                                'verified-primary-email' => false,
                            ),
                        );

                        $aOutput = json_decode(implode('', $aOutput), true);
                        $aORCID = array_replace_recursive($aORCID, $aOutput);
                        $nID = $aORCID['orcid-identifier']['path'];
                        $sNameComposed = $aORCID['person']['name']['family-name']['value'] . ', ' . $aORCID['person']['name']['given-names']['value'];
                        $sNameDisplay = $aORCID['person']['name']['credit-name']['value'];
                        if (!$sNameDisplay) {
                            $sNameDisplay = $aORCID['person']['name']['given-names']['value'] . ' ' . $aORCID['person']['name']['family-name']['value'];
                        }
                        $sInstitute = '';
                        $sDepartment = '';
                        $sCountryCode = '';
                        // Just loop all employments, and choose the first without end date.
                        foreach ($aORCID['activities-summary']['employments']['employment-summary'] as $aEmployment) {
                            if (empty($aEmployment['end-date'])) {
                                $sInstitute = $aEmployment['organization']['name'];
                                $sDepartment = $aEmployment['department-name'];
                                $sCountryCode = $aEmployment['organization']['address']['country'];
                                break;
                            }
                        }
                        $aEmails = array();
                        foreach ($aORCID['person']['emails']['email'] as $aEmail) {
                            $aEmails[] = $aEmail['email'];
                        }
                        $sEmailDisplay = implode(', ', $aEmails);
                        // FIXME: We can also receive this from the email settings itself, if public.
                        $bEmailVerified = ($aORCID['history']['verified-email'] || $aORCID['history']['verified-primary-email']);
                        // If we didn't get a country from the affiliation, take it from the addresses.
                        // FIXME: Do we need to loop through the addresses?
                        $sCountryCode = ($sCountryCode?: $aORCID['person']['addresses']['address'][0]['country']['value']);
                        if ($sCountryCode) {
                            $sCountry = $_DB->query('SELECT name FROM ' . TABLE_COUNTRIES . ' WHERE id = ?', array($sCountryCode))->fetchColumn();
                        } else {
                            $sCountry = '';
                        }
                        $_SESSION['orcid_data']['name'] = $sNameDisplay;
                        $_SESSION['orcid_data']['institute'] = $sInstitute;
                        $_SESSION['orcid_data']['department'] = $sDepartment;
                        $_SESSION['orcid_data']['email'] = $aEmails;
                        $_SESSION['orcid_data']['countryid'] = $sCountryCode;

                        // Report found ID, and have user confirm or deny.
                        $_T->printHeader();
                        $_T->printTitle();

                        $sMessage = 'We have retrieved the following information from ORCID. Please verify if this information is correct:<BR>' .
                            '<B>' . $nID . '</B><BR>' .
                            $sNameComposed . ' (' . $sNameDisplay . ')<BR>' .
                            (!$sInstitute? '' : $sInstitute . (!$sDepartment? '' : ' (' . $sDepartment . ')') . '<BR>') .
                            $sCountry . '<BR>' .
                            $sEmailDisplay;
                        lovd_showInfoTable($sMessage, 'question');

                        print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n" .
                              '        <INPUT type="hidden" name="orcid_id" value="' . $nID . '">' . "\n" .
                              '        <INPUT type="submit" value="&laquo; No, this is not correct" onclick="window.location.href=\'' . lovd_getInstallURL() . CURRENT_PATH . '?' . ACTION . '\'; return false;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Yes, this is correct &raquo;">' . "\n" .
                              '      </FORM>' . "\n\n");

                        $_T->printFooter();
                        exit;
                    }
                }
            }

            $_T->printHeader();
            $_T->printTitle();

            print(      '<A href="http://about.orcid.org/" target="_blank">ORCID</A> provides a persistent digital identifier that distinguishes you from every other researcher and, through integration in key research workflows such as manuscript and grant submission, supports automated linkages between you and your professional activities ensuring that your work is recognized. <A href="http://about.orcid.org/" target="_blank">Find out more.</A><BR>' . "\n" .
                'Don\'t have an ORCID ID yet? Please consider to <A href="https://orcid.org/register" target="_blank">get one</A>, it only takes a minute.<BR><BR>' . "\n\n");

            lovd_errorPrint();

            print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

            // Array which will make up the form table.
            $aForm = array(
                array('POST', '', '', '', '40%', '14', '60%'),
                'hr',
                array('Please enter ' . (ACTION == 'create'? 'this user\'s' : 'your') . ' ORCID ID', '', 'text', 'orcid', 20),
                array('', '', 'print', '<INPUT type="submit" value="Continue &raquo;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="' . (ACTION == 'create'? 'This user doesn\'t' : 'I don\'t') . ' have an ORCID ID &raquo;" onclick="if(window.confirm(\'Are you sure you don\\\'t want to register for an ORCID ID first? Press \\\'OK\\\' to continue without ORCID ID.\')){window.location.href=\'' . lovd_getInstallURL() . CURRENT_PATH . '?' . ACTION . '&amp;no_orcid\';} return false;">'),
            );
            lovd_viewForm($aForm);

            print('</FORM>' . "\n\n");

            $_T->printFooter();
            exit;
        }
    }



    require ROOT_PATH . 'class/object_users.php';
    $_DATA = new LOVD_User();
    if (ACTION == 'register') {
        $sCAPTCHAerror = '';
    }

    if (count($_POST) > 1) { // 'orcid_id' will always be defined here.
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (ACTION == 'register') {
            // Checking the CAPTCHA response...
            // If no response has been filled in, we need to complain. Otherwise, we should check the answer.
            if (empty($_POST['g-recaptcha-response'])) {
                lovd_errorAdd('', 'Please check the checkmark and follow the instructions at "Please verify that you are not a robot".');
            } else {
                // Check answer!
                if (!lovd_recaptchaV2_verify($_POST['g-recaptcha-response'])) {
                    lovd_errorAdd('', 'Registration authentication failed. Please try again by checking the checkmark and following the instructions at "Please verify that you are not a robot" at the bottom of the form.');
                }
            }

            // This function checks the given data for use by spammers.
            if (!lovd_error()) {
                $sRequest = 'http://www.stopforumspam.com/api?ip=' . $_SERVER['REMOTE_ADDR'];
                foreach (explode("\r\n", $_POST['email']) as $sEmail) {
                    $sRequest .= '&email[]=' . $sEmail;
                }
                $sRequest .= '&username=' . $_POST['username'] . '&f=json';
                $aOutput = lovd_php_file($sRequest);
                if ($aOutput) {
                    $aStopSpamResponse = array(
                        'success' => 0,
                        'ip' => array(
                            'appears' => 0,
                            'lastseen' => '',
                            'frequency' => 0,
                            'confidence' => 0,
                        ),
                        'email' => array(
                            array(
                                'appears' => 0,
                                'lastseen' => '',
                                'frequency' => 0,
                                'confidence' => 0,
                            )
                        ),
                        'username' => array(
                            'appears' => 0,
                            'lastseen' => '',
                            'frequency' => 0,
                            'confidence' => 0,
                        ),
                    );
                    $aStopSpamEmailTemplate = $aStopSpamResponse['email'][0];

                    $aOutput = json_decode(implode('', $aOutput), true);
                    $aStopSpamResponse = array_replace_recursive($aStopSpamResponse, $aOutput);
                    // Since we might have sent multiple email addresses, we need to apply the array_replace() on each email result.
                    foreach ($aStopSpamResponse['email'] as $nKey => $aEmail) {
                        if ($nKey) {
                            // Key [0] has already been replaced, of course.
                            $aStopSpamResponse['email'][$nKey] = array_replace($aStopSpamEmailTemplate, $aEmail);
                        }
                    }
                    if ($aStopSpamResponse['success']) {
                        $nFrequency = $aStopSpamResponse['ip']['frequency'] + $aStopSpamResponse['username']['frequency'];
                        $nConfidence = max($aStopSpamResponse['ip']['confidence'], $aStopSpamResponse['username']['confidence']);
                        foreach ($aStopSpamResponse['email'] as $aEmail) {
                            $nFrequency += $aEmail['frequency'];
                            $nConfidence = max($nConfidence, $aEmail['confidence']);
                        }
                        // If we only have this score because of the username, remove the scores.
                        if ($nFrequency == $aStopSpamResponse['username']['frequency']
                            && $nConfidence == $aStopSpamResponse['username']['confidence']) {
                            $nFrequency = $nConfidence = 0;
                        }
                        if ($nFrequency >= 25 || $nConfidence >= 75) {
                            lovd_writeLog('Event', LOG_EVENT, 'User registration blocked based on frequency (' . $nFrequency . ') and confidence (' . $nConfidence . ') in spam database: ' . $_SERVER['REMOTE_ADDR'] . ', ' . str_replace("\r\n", ';', $_POST['email']) . ', ' . $_POST['username']);
                            lovd_errorAdd('', 'Your registration has been blocked based on suspicion of spamming. If you feel this is an error, please contact us.');
                            $_POST = array('orcid' => 'none'); // Empty all fields (except for the orcid_id, to prevent notices).
                        }
                    }
                }
            }
        }

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('name', 'institute', 'department', 'telephone', 'address', 'city', 'countryid', 'email', 'username', 'password', 'password_force_change', 'level', 'allowed_ip', 'login_attempts', 'created_date');

            // Prepare values.
            if ($_POST['orcid_id'] != 'none') {
                $aFields[] = 'orcid_id';
            }
            $_POST['password'] = lovd_createPasswordHash($_POST['password_1']);
            if (ACTION == 'register') {
                $_POST['password_force_change'] = 0;
                $_POST['level'] = LEVEL_SUBMITTER;
                $_POST['login_attempts'] = 0;
                $aFields[] = 'last_login';
                $_POST['last_login'] = $_POST['created_date'] = date('Y-m-d H:i:s');
            }
            if (ACTION == 'create') {
                $_POST['login_attempts'] = ($_POST['locked']? 3 : 0);
                $aFields[] = 'created_by';
                $_POST['created_by'] = $_AUTH['id'];
            }
            $_POST['created_date'] = date('Y-m-d H:i:s');

            $nID = $_DATA->insertEntry($_POST, $aFields);
            if (ACTION == 'register') {
                // Store that user has been created by himself.
                $_DB->query('UPDATE ' . TABLE_USERS . ' SET created_by = id WHERE id = ?', array($nID));

                // Load authorization.
                $_SESSION['auth'] = $_DB->query('SELECT * FROM ' . TABLE_USERS . ' WHERE id = ?', array($nID))->fetchAssoc();
                $_AUTH =& $_SESSION['auth'];
                // To prevent notices in the header for instance...
                $_AUTH['curates']      = array();
                $_AUTH['collaborates'] = array();

                // Write to log...
                lovd_writeLog('Event', LOG_EVENT, $_SERVER['REMOTE_ADDR'] . ' (' . lovd_php_gethostbyaddr($_SERVER['REMOTE_ADDR']) . ') successfully created own submitter account with ID ' . $nID);

            } else {
                // Write to log...
                lovd_writeLog('Event', LOG_EVENT, 'Created user ' . $nID . ' - ' . $_POST['username'] . ' (' . $_POST['name'] . ') - with level ' . $_SETT['user_levels'][$_POST['level']]);
            }



            // Mail new user only if we're registering, or an email has been requested.
            if (ACTION == 'register' || !empty($_POST['send_email'])) {
                $aTo = array(array($_POST['name'], $_POST['email']));
                $aCc = array();
                if (ACTION == 'create') {
                    $aCc[] = array($_AUTH['name'], $_AUTH['email']);
                }

                $sMessage = 'Dear ' . $_POST['name'] . ',' . "\n\n" .
                    (ACTION == 'create'? 'An account for this LOVD system has been created for you by ' . $_AUTH['name'] . '.' :
                                         'You have registered as a data submitter for this LOVD system.') . "\n" .
                    'Below is a copy of your registration information.' . "\n\n";

                if ($_CONF['location_url']) {
                    $sMessage .= 'To log in to LOVD, click this link:' . "\n" .
                        $_CONF['location_url'] . 'login' . "\n\n" .
                        'You can also go straight to your account using the following link:' . "\n" .
                        $_CONF['location_url'] . $_PE[0] . '/' . $nID . "\n\n";
                }

                // If the user has a specific IP address allow list, warn this user.
                if (!empty($_POST['allowed_ip']) && $_POST['allowed_ip'] != '*') {
                    // A certain set of IPs has been specified by the person creating the account.
                    $sMessage .= 'Note that a restriction has been placed on this account by limiting access to this account from only the IP address(es) specified at the bottom of this email. ' .
                                 'This might cause you to be unable to log in, even if you have the correct username and password. ' .
                                 'If this restriction was an error, please log into your account and empty the IP address list field. ' .
                                 'If you have problems logging in, please contact the LOVD\'s manager.' . "\n\n";
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
                        'orcid_id' => 'ORCID ID',
                        'name' => 'Name',
                        'institute' => 'Institute',
                        'department' => 'Department',
                        'address' => 'Address',
                        'city' => 'City',
                        'country_' => 'Country',
                        'email' => 'Email address',
                        'telephone' => 'Telephone',
                        'username' => 'Username',
                        'password_1' => 'Password',
                        'allowed_ip' => 'Allowed IPs',
                    );
                if ($_POST['orcid_id'] == 'none') {
                    unset($aMailFields['orcid_id']);
                }

                $aBody = array($sMessage, 'submitter_details' => $aMailFields);

                $sBody = lovd_formatMail($aBody);

                // Set proper subject.
                $sSubject = 'LOVD account registration'; // Don't just change this; lovd_sendMail() is parsing it.

                // Send mail.
                $bMail = lovd_sendMail($aTo, $sSubject, $sBody, $_SETT['email_headers'], true, $_CONF['send_admin_submissions'], $aCc);
            } else {
                $bMail = 0; // Does not evaluate to True (mention we've sent the email), but doesn't equal False either (mention we failed to send the email).
            }

            if ($bMail !== false) {
                // Forward the user if we didn't fail to send the email (or we may not have tried to send it).
                header('Refresh: 3; url=' . lovd_getInstallURL() . $_PE[0] . '/' . $nID . '?&new_submitter');
            }

            // Thank the user...
            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully created '  . (ACTION == 'create'? 'the user' : 'your') . ' account!' .
                    (!$bMail? '' : '<BR>We\'ve sent '  . (ACTION == 'create'? 'the user' : 'you') . ' an email containing '  . (ACTION == 'create'? 'the' : 'your') . ' account information.'), 'success');
            if ($bMail === false) {
                lovd_showInfoTable('Due to an error, we couldn\'t send an email containing the account information. Our apologies for the inconvenience.', 'stop');
            }

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password'], $_POST['password_1'], $_POST['password_2']);
        }

    } else {
        // Default values.
        $_DATA->setDefaultValues();

        // ORCID DATA?
        if ($_POST['orcid_id'] != 'none') {
            $_POST['name'] = $_SESSION['orcid_data']['name'];
            $_POST['institute'] = $_SESSION['orcid_data']['institute'];
            $_POST['department'] = $_SESSION['orcid_data']['department'];
            $_POST['email'] = implode("\r\n", $_SESSION['orcid_data']['email']);
            $_POST['countryid'] = $_SESSION['orcid_data']['countryid'];
        }
    }



    $_T->printHeader();
    $_T->printTitle();

    if (GET) {
        print('      To ' . (ACTION == 'create'? 'create a new user' : 'register as a new submitter') . ', please fill out the form below.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    if (ACTION == 'register') {
        lovd_showInfoTable('Please note that you do <B>NOT</B> need to register to view the data available at these pages. You only need an account for submitting new variants.', 'warning');
    }
    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');
    // Check form (IP address allow list).
    lovd_includeJS('inc-js-submit-userform.php');

    if (ACTION == 'register') {
        lovd_includeJS('https://www.google.com/recaptcha/api.js');
    }

    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post" onsubmit="return lovd_checkForm();">' . "\n" .
          '        <INPUT type="hidden" name="orcid_id" value="' . $_POST['orcid_id'] . '">' . "\n");

    // Array which will make up the form table.
    if (ACTION == 'create') {
        $aFormBottom = array(
            array('', '', 'submit', (ACTION == 'create'? 'Create user' : 'Register')),
        );
    } else {
        $aFormBottom = array(
            'skip',
            array('Please verify that you are not a robot', '',
                  'print', '<DIV class="g-recaptcha" data-sitekey="6Lf_XBsUAAAAAC4J4fMs3GP_se-qNk8REDYX40P5"></DIV>'),
            'hr',
            'skip',
            array('', '', 'submit', 'Register'),
        );
    }
    $aForm = array_merge(
        $_DATA->getForm(),
        $aFormBottom);
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'edit') {
    // URL: /users/00001?edit
    // Edit specific entry.

    $nID = lovd_getCurrentID();
    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    define('LOG_EVENT', 'UserEdit');

    // Require valid user.
    lovd_requireAUTH();

    require ROOT_PATH . 'class/object_users.php';
    $_DATA = new LOVD_User();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    // Require special clearance, if user is not editing himself.
    // Necessary level depends on level of user. Special case.
    if ($nID != $_AUTH['id'] && $zData['level'] >= $_AUTH['level']) {
        // Simple solution: if level is not lower than what you have, you're out.
        // This is a hack-attempt.
        // FIXME: This function and its use is a bit messy.
        lovd_showPageAccessDenied('Tried to edit user ID ' . $nID . ' (' .
                                  $_SETT['user_levels'][$zData['level']] . ')',
            PAGE_TITLE,
            'Not allowed to edit this user. This event has been logged.');
        exit;
    }

    if (!empty($_POST)) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('name', 'institute', 'department', 'telephone', 'address', 'city', 'countryid', 'email', 'password_force_change', 'level', 'allowed_ip', 'login_attempts', 'edited_by', 'edited_date');

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
    // Check form (IP address allow list).
    lovd_includeJS('inc-js-submit-userform.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post" onsubmit="return lovd_checkForm();">' . "\n");

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
    // URL: /users/00001?change_password
    // Change a user's password.

    $nID = lovd_getCurrentID();
    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    define('LOG_EVENT', 'UserResetPassword');

    // Require valid user.
    lovd_requireAUTH();

    require ROOT_PATH . 'class/object_users.php';
    $_DATA = new LOVD_User();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    // Require special clearance, if user is not editing himself.
    // Necessary level depends on level of user. Special case.
    if ($nID != $_AUTH['id'] && $zData['level'] >= $_AUTH['level']) {
        // Simple solution: if level is not lower than what you have, you're out.
        // This is a hack-attempt.
        // FIXME: This function and its use is a bit messy.
        lovd_showPageAccessDenied('Tried to edit user ID ' . $nID . ' (' . $_SETT['user_levels'][$zData['level']] . ')',
            PAGE_TITLE,
            'Not allowed to edit this user. This event has been logged.');
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
    // URL: /users/00001?delete
    // Delete a specific user.

    $nID = lovd_getCurrentID();
    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    define('LOG_EVENT', 'UserDelete');

    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_users.php';
    $_DATA = new LOVD_User();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    // Require special clearance, user must be of higher level (and therefore automatically cannot delete himself).
    if ($zData['level'] >= $_AUTH['level']) {
        // Simple solution: if level is not lower than what you have, you're out.
        // This is a hack-attempt.
        // FIXME: This function and its use is a bit messy.
        lovd_showPageAccessDenied('Tried to delete user ID ' . $nID . ' (' . $_SETT['user_levels'][$zData['level']] . ')',
            PAGE_TITLE,
            'Not allowed to delete this user. This event has been logged.');
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
    // URL: /users/00001?boot
    // Throw a user out of the system.

    $nID = lovd_getCurrentID();
    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    define('LOG_EVENT', 'UserBoot');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    $zData = $_DB->query('SELECT name, username, phpsessid, level FROM ' . TABLE_USERS . ' WHERE id = ?', array($nID))->fetchAssoc();
    if (!$zData || $zData['level'] >= $_AUTH['level']) {
        // Wrong ID, apparently.
        // FIXME: This function and its use is a bit messy.
        lovd_showPageAccessDenied(null, PAGE_TITLE, 'No such ID!');
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
    // URL: /users/00001?lock
    // URL: /users/00001?unlock
    // Lock / unlock a user.

    $nID = lovd_getCurrentID();
    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    define('LOG_EVENT', 'User' . ucfirst(ACTION));

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    $zData = $_DB->query('SELECT username, name, (login_attempts >= 3) AS locked, level FROM ' . TABLE_USERS . ' WHERE id = ?', array($nID))->fetchAssoc();
    if (!$zData || $zData['level'] >= $_AUTH['level']) {
        // Wrong ID, apparently.
        // FIXME: This function and its use is a bit messy.
        lovd_showPageAccessDenied(null, PAGE_TITLE, 'No such ID!');
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





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'submissions') {
    // URL: /users/00001?submissions
    // Manage unfinished submissions

    $nID = lovd_getCurrentID();
    define('PAGE_TITLE', lovd_getCurrentPageTitle());

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
        if ($_AUTH['id'] == $nID) {
            $_DATA->setRowLink('Individuals_submissions', 'submit/individual/' . $_DATA->sRowID);
        } else {
            $_DATA->setRowLink('Individuals_submissions', 'individuals/' . $_DATA->sRowID);
        }
        $aVLOptions = array(
            'cols_to_skip' => array('individualid', 'diseaseids', 'owned_by_', 'status'),
            'show_options' => true,
            'find_and_replace' => true,
        );
        $_DATA->viewList('Individuals_submissions', $aVLOptions);
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
        if ($_AUTH['id'] == $nID) {
            $_DATA->setRowLink('Screenings_submissions', 'submit/screening/' . $_DATA->sRowID);
        } else {
            $_DATA->setRowLink('Individuals_submissions', 'screenings/' . $_DATA->sRowID);
        }
        $aVLOptions = array(
            'cols_to_skip' => array('owned_by_', 'created_date', 'edited_date'),
            'show_options' => true,
            'find_and_replace' => true,
        );
        $_DATA->viewList('Screenings_submissions', $aVLOptions);
    } else {
        lovd_showInfoTable('No submissions of variant screenings found!', 'stop');
    }

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'share_access') {
    // URL: /users/00001?share_access
    // Let the user share access to his objects to other users.

    $nID = lovd_getCurrentID();
    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    define('LOG_EVENT', 'ShareAccess');

    require_once ROOT_PATH . 'class/object_users.php';
    require ROOT_PATH . 'inc-lib-form.php';

    // Boolean flag setting whether users can give edit-permissions to their colleagues.
    $bAllowGrantEdit = true;
    $sUserListID = 'user_share_access_' . $nID;

    // Get the current user's full name to use in interface/e-mail.
    $sNameQuery = 'SELECT
                     u.name,
                     u.level,
                     u.institute,
                     u.email
                   FROM ' . TABLE_USERS . ' AS u
                   WHERE u.id = ?';
    $zData = $_DB->query($sNameQuery, array($nID))->fetchAssoc();

    // Require special clearance, if user is not editing himself.
    // Necessary level depends on level of user. Special case.
    if ($nID != $_AUTH['id'] && $zData['level'] >= $_AUTH['level']) {
        // This is a hack-attempt.
        // FIXME: This function and its use is a bit messy.
        lovd_showPageAccessDenied('Tried to share access of user ID ' . $nID . ' (' .
            $_SETT['user_levels'][$zData['level']] . ')',
            PAGE_TITLE,
            'Not allowed to edit this user. This event has been logged.');
        exit;
    }

    $aColleagues = null;

    if (POST) {
        lovd_errorClean();

        if (!isset($_POST['colleagues']) || !is_array($_POST['colleagues'])) {
            $_POST['colleagues'] = array();
        }
        if (!isset($_POST['colleague_name']) || !is_array($_POST['colleague_name'])) {
            $_POST['colleague_name'] = array();
        }
        if (!isset($_POST['allow_edit']) || !is_array($_POST['allow_edit'])) {
            $_POST['allow_edit'] = array();
        }

        // Remove duplicates and combine with edit permissions.
        $aColleagueIDs = array_unique($_POST['colleagues']);
        $nColleagueCount = count($aColleagueIDs);
        $aColleagues = array();
        for ($i = 0; $i < $nColleagueCount; $i++) {
            $bAllowEdit = in_array($aColleagueIDs[$i], $_POST['allow_edit']);
            $aColleagues[] = array('id' => $aColleagueIDs[$i],
                                   'name' => $_POST['colleague_name'][$i],
                                   'allow_edit' => $bAllowEdit);
        }

        if (empty($_POST['password'])) {
            lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');
        } elseif ($_POST['password'] && !lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            // User had to enter his/her password for authorization.
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            lovd_setColleagues($nID, $zData['name'], $zData['institute'], $zData['email'],
                $aColleagues, $bAllowGrantEdit);

            // Confirmation page and redirect.
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PE[0] . '/' . $nID);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully updated sharing permissions!', 'success');

            $_T->printFooter();
            exit;
        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    }

    list($aColleagues, $sColTable) = lovd_colleagueTableHTML($nID, $sUserListID, $aColleagues,
                                                             $bAllowGrantEdit);

    $_T->printHeader();
    $_T->printTitle();

    lovd_errorPrint();

    lovd_showInfoTable('To share access with other users, find the user in the list below, click on
                       the user to add him to the selection. Then click <B>save</B> to save the
                       changes.', 'information');

    // Set number of items per page for viewlist.
    $_GET['page_size'] = 10;

    // Set filter for viewlist to hide current colleagues and the user being viewed.
    $_GET['search_userid'] = '!' . $nID;
    foreach ($aColleagues as $aColleague) {
        $_GET['search_userid'] .= ' !' . $aColleague['id'];
    }

    // Show viewlist to select new users to share access with.
    $_DATA = new LOVD_User();
    $_DATA->setRowLink('users_share_access',
        'javascript:lovd_passAndRemoveViewListRow("{{ViewListID}}", "{{ID}}", {id: "{{ID}}", name: "{{zData_name}}"}, lovd_addUserShareAccess); return false;');
    // The columns hidden here are also specified (enforced) in ajax/viewlist.php to make sure Submitters can't hack their way into the users table.
    $aVLOptions = array(
        'cols_to_skip' => array('username', 'status_', 'last_login_', 'created_date_', 'curates', 'level_'),
        'track_history' => false,
    );
    $_DATA->viewList($sUserListID, $aVLOptions);

    lovd_showInfoTable('<B>' . $zData['name'] . ' (' . $nID . ')</B> shares access to all
                       data owned by him with the users listed below.', 'information');

    print('<FORM action="users/' . $nID . '?share_access" method="post">' . "\n");
    // Array which will make up the form table.
    print($sColTable . "\n");
    $aForm = array(
        array('POST', '', '', '', '0%', '0', '100%'),
        array('', '', 'print', 'Enter your password for authorization'),
        array('', '', 'password', 'password', 20),
        array('', '', 'print', '<INPUT type="submit" value="Save access permissions">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Cancel" onclick="window.location.href=\'' . lovd_getInstallURL() . $_PE[0] . '/' . $nID . '\'; return false;" style="border : 1px solid #FF4422;">'),
    );
    lovd_viewForm($aForm);
    print('</FORM>');

    $_T->printFooter();
    exit;
}
?>
