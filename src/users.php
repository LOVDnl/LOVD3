<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-01-14
 * Modified    : 2015-10-30
 * For LOVD    : 3.0-15
 *
 * Copyright   : 2004-2015 Leiden University Medical Center; http://www.LUMC.nl/
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

    // FIXME; we need to think about this. To create a public submitters list, will we have a modified viewList()?
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

    // Require valid user.
    // If not viewing himself, the user may see very little information (low level) or all data (high level).
    lovd_requireAUTH();

    lovd_isAuthorized('gene', $_AUTH['curates']); // Enables LEVEL_COLLABORATOR and LEVEL_CURATOR for object_users.php.

    if ($nID == '00000') {
        $nID = -1;
    } elseif ($nID == $_AUTH['id'] && $_AUTH['level'] == LEVEL_SUBMITTER && isset($_GET['new_submitter'])) {
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
    // Increase the max group_concat() length, so that curators of many many genes still have all genes mentioned here.
    $_DB->query('SET group_concat_max_len = 150000');
    $zData = $_DATA->viewEntry($nID);

    $aNavigation = array();
    // Since we're faking the user's level to show some more columns when the user is viewing himself, we must put the check on the ID here.
    if ($_AUTH['id'] != $nID && $_AUTH['level'] > $zData['level']) {
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





if (PATH_COUNT == 1 && in_array(ACTION, array('create', 'register'))) {
    // URL: /users?create
    // URL: users?register
    // Create a new user, or self-register a new submitter.

    define('LOG_EVENT', 'User' . ucfirst(ACTION));
    if (ACTION == 'create') {
        define('PAGE_TITLE', 'Create a new user account');

        // Require manager clearance.
        lovd_requireAUTH(LEVEL_MANAGER);

    } else {
        define('PAGE_TITLE', 'Register as new submitter');

        if ($_AUTH) {
            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('You are already a registered user.', 'stop');
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
                    // 2014-05-09; 3.0-11; ORCID changed their API... but at least they understood including a version might help. Not changing to the new one.
                    $aOutput = lovd_php_file('http://pub.orcid.org/v1.2/' . $_POST['orcid'], false, '', 'Accept: application/orcid+json');
                    if (!$aOutput) {
                        lovd_errorAdd('orcid', 'The given ORCID ID can not be found at ORCID.org.');
                    } else {
                        $aORCID = array(
                            'orcid-identifier' => array('path' => ''),
                            'orcid-bio' => array(
                                'personal-details' => array(
                                    'family-name' => array('value' => ''),
                                    'given-names' => array('value' => ''),
                                    'credit-name' => array('value' => ''),
                                ),
                                'contact-details' => array(
                                    'email' => array('value' => ''),
                                    'address' => array(
                                        'country' => array('value' => ''),
                                    ),
                                ),
                            ),
                            'orcid-history' => array(
                                'verified-email' => array('value' => ''),
                            ),
                        );

                        $aOutput = json_decode(implode('', $aOutput), true);
                        $aORCID = array_replace_recursive($aORCID, $aOutput['orcid-profile']);
                        $nID = $aORCID['orcid-identifier']['path'];
                        $sNameComposed = $aORCID['orcid-bio']['personal-details']['family-name']['value'] . ', ' . $aORCID['orcid-bio']['personal-details']['given-names']['value'];
                        $sNameDisplay = $aORCID['orcid-bio']['personal-details']['credit-name']['value'];
                        if (!$sNameDisplay) {
                            $sNameDisplay = $aORCID['orcid-bio']['personal-details']['given-names']['value'] . ' ' . $aORCID['orcid-bio']['personal-details']['family-name']['value'];
                        }
                        $sEmail = $aORCID['orcid-bio']['contact-details']['email']['value'];
                        $bEmailVerified = $aORCID['orcid-history']['verified-email']['value'];
                        $sCountryCode = $aORCID['orcid-bio']['contact-details']['address']['country']['value'];
                        if ($sCountryCode) {
                            $sCountry = $_DB->query('SELECT name FROM ' . TABLE_COUNTRIES . ' WHERE id = ?', array($sCountryCode))->fetchColumn();
                        } else {
                            $sCountry = '';
                        }
                        $_SESSION['orcid_data']['name'] = $sNameDisplay;
                        $_SESSION['orcid_data']['email'] = $sEmail;
                        $_SESSION['orcid_data']['countryid'] = $sCountryCode;

                        // Report found ID, and have user confirm or deny.
                        $_T->printHeader();
                        $_T->printTitle();

                        $sMessage = 'We have retrieved the following information from ORCID. Please verify if this information is correct:<BR>' .
                            '<B>' . $nID . '</B><BR>' .
                            $sNameComposed . ' (' . $sNameDisplay . ')<BR>' .
                            $sCountry . '<BR>' .
                            $sEmail;
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
        require ROOT_PATH . 'lib/reCAPTCHA/inc-lib-recaptcha.php';
        $sCAPTCHAerror = '';
    }

    if (count($_POST) > 1) { // 'orcid_id' will always be defined here.
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (ACTION == 'register') {
            // Checking the CAPTCHA response...
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
                            array(
                                'appears' => 0,
                                'lastseen' => '',
                                'frequency' => 0,
                                'confidence' => 0,
                            )
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
                            array(
                                'appears' => 0,
                                'lastseen' => '',
                                'frequency' => 0,
                                'confidence' => 0,
                            )
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
                        $nFrequency = $aStopSpamResponse['ip'][0]['frequency'] + $aStopSpamResponse['username'][0]['frequency'];
                        $nConfidence = max($aStopSpamResponse['ip'][0]['confidence'], $aStopSpamResponse['username'][0]['confidence']);
                        foreach ($aStopSpamResponse['email'] as $aEmail) {
                            $nFrequency += $aEmail['frequency'];
                            $nConfidence = max($nConfidence, $aEmail['confidence']);
                        }
                        if ($nFrequency >= 10 || $nConfidence >= 75) {
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
            $aFields = array('name', 'institute', 'department', 'telephone', 'address', 'city', 'countryid', 'email', 'reference', 'username', 'password', 'password_force_change', 'level', 'allowed_ip', 'login_attempts', 'created_date');

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
                lovd_writeLog('Event', LOG_EVENT, $_SERVER['REMOTE_ADDR'] . ' (' . gethostbyaddr($_SERVER['REMOTE_ADDR']) . ') successfully created own submitter account with ID ' . $nID);

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
                        'reference' => 'Reference',
                        'username' => 'Username',
                        'password_1' => 'Password',
                    );
                if ($_POST['orcid_id'] == 'none') {
                    unset($aMailFields['orcid_id']);
                }

                $aBody = array($sMessage, 'submitter_details' => $aMailFields);

                $sBody = lovd_formatMail($aBody);

                // Set proper subject.
                $sSubject = 'LOVD account registration'; // Don't just change this; lovd_sendMail() is parsing it.

                // Send mail.
                $bMail = lovd_sendMail($aTo, $sSubject, $sBody, $_SETT['email_headers'], $_CONF['send_admin_submissions'], $aCc);
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
            $_POST['email'] = $_SESSION['orcid_data']['email'];
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

    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n" .
          '        <INPUT type="hidden" name="orcid_id" value="' . $_POST['orcid_id'] . '">' . "\n");

    // Array which will make up the form table.
    if (ACTION == 'create') {
        $aFormBottom = array(
            array('', '', 'submit', (ACTION == 'create'? 'Create user' : 'Register')),
        );
    } else {
        $aFormBottom = array(
            'skip',
            array('', '', 'print', '<B>Registration authentication</B>'),
            'hr',
            array('Please fill in the word, words, or numbers that you see in the image', '', 'print', recaptcha_get_html('6Le0JQQAAAAAAPQ55JT0m0_AVX5RqgSnHBplWHxZ', $sCAPTCHAerror, SSL)),
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

    lovd_requireAUTH(LEVEL_MANAGER);

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
        $nID = -1; // Block access to the LOVD account with ID = 0.
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
    // users/00001?lock
    // users/00001?unlock
    // Lock / unlock a user.

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', ucfirst(ACTION) . ' user account #' . $nID);
    define('LOG_EVENT', 'User' . ucfirst(ACTION));

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    if ($nID == '00000') {
        $nID = -1; // Block access to the LOVD account with ID = 0.
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
