<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-05-20
 * Modified    : 2021-07-13
 * For LOVD    : 3.0-27
 *
 * Copyright   : 2004-2021 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

// Already logged in to the system.
if ($_AUTH) {
    // Send user to the index, that file will figure out where to go from there.
    header('Location: ' . lovd_getInstallURL());
    exit;
}

if (!$_AUTH && $_CONF['allow_unlock_accounts']) {
    // User forgot password - replace.

    define('PAGE_TITLE', 'Reset password');
    define('LOG_EVENT', 'ResetPassword');

    // Require form functions.
    require ROOT_PATH . 'inc-lib-form.php';

    if (POST && !empty($_POST['username'])) {
        lovd_errorClean();

        // Sleep a second to prevent this script from being run
        //  too many times in sequence. Run it here so people can't see the
        //  difference between a successful attempt or a failure.
        sleep(1);

        // Find account.
        $zData = array($_DB->query('SELECT * FROM ' . TABLE_USERS . ' WHERE username = ?',
            array($_POST['username']))->fetchAssoc());
        if ($zData == array(false)) {
            $zData = $_DB->query('SELECT * FROM ' . TABLE_USERS . ' WHERE email = ?',
                array($_POST['username']))->fetchAllAssoc();
            if (!$zData) {
                $zData = $_DB->query('SELECT * FROM ' . TABLE_USERS . ' WHERE email REGEXP ?',
                    array("(^|\r\n)" . $_POST['username'] . "(\r\n|$)"))->fetchAllAssoc();
            }
        }
        if (!$zData || $zData == array(false)) {
            // If username does not exist, we don't want to let the user know. So this message is entirely incorrect.
            $_T->printHeader();
            $_T->printTitle();
            lovd_writeLog('Auth', LOG_EVENT, $_SERVER['REMOTE_ADDR'] . ' (' . lovd_php_gethostbyaddr($_SERVER['REMOTE_ADDR']) . ') tried to reset password for non-existent account ' . $_POST['username']);
            print('      If you entered the username or email address correctly, we have successfully reset your password and we have sent you an email containing your new password.' . "\n" .
                  '      With this new password, you can <A href="' . ROOT_PATH . 'login">unlock your account</A> and choose a new password.<BR><BR>' . "\n" .
                  '      If you don\'t receive this email, it is possible that the username or email address that you entered was not correct. In that case, please double-check it. Another possibility is that you registered at a different LOVD installation. Accounts are not shared between different LOVD installations, so please double-check where you are registered.<BR><BR>' . "\n\n");
            $_T->printFooter();
            exit;

        } elseif (count($zData) > 1) {
            // If email address given links to multiple account, we don't want to unlock them all.
            lovd_writeLog('Auth', LOG_EVENT, $_SERVER['REMOTE_ADDR'] . ' (' . lovd_php_gethostbyaddr($_SERVER['REMOTE_ADDR']) . ') tried to reset password with email address matching multiple accounts: ' . $_POST['username']);
            lovd_errorAdd('username', 'This email address links to multiple accounts. Please provide the username of the account you wish to reset.');
        }
        $zData = $zData[0];

        if (!lovd_error()) {
            // Found account... unlock and generate new passwd.

            $aChars =
                     array(
                            'A' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
                            '0' => '0123456789',
                            '!' => '!@#$%^&*()',
                          );
            $sFormat = 'AA!00!AA!00!';
            $nLength = strlen($sFormat);
            $sPasswd = '';
            for ($i = 0; $i < $nLength; $i ++) {
                $sType = $sFormat{$i};
                $sPasswd .= $aChars[$sType]{mt_rand(0, strlen($aChars[$sType]) - 1)};
            }

            // Update database.
            $_DB->query('UPDATE ' . TABLE_USERS . ' SET password_autogen = MD5(?) WHERE username = ?', array($sPasswd, $zData['username']));

            lovd_writeLog('Auth', LOG_EVENT, $_SERVER['REMOTE_ADDR'] . ' (' . lovd_php_gethostbyaddr($_SERVER['REMOTE_ADDR']) . ') successfully reset password for account ' .
                $_POST['username'] . ($_POST['username'] == $zData['username']? '' : ' (' . $zData['username'] . ')'));

            // Send email confirmation.
            $aTo = array(array($zData['name'], $zData['email']));

            $sMessage = 'Dear ' . $zData['name'] . ',' . "\n\n" .
                        'Your password from your LOVD account has been reset, as requested. ' .
                        'Your username and your new, randomly generated, password can be found below. ' .
                        'Please log in to LOVD and choose a new password.' . "\n\n" .
                        'If you did not request a new password, you can disregard this message. ' .
                        'Your old password will continue to function normally. ' .
                        'However, you may then want to report this email to the Database administrator ' .
                            $_SETT['admin']['name'] . ', email: ' . str_replace(array("\r\n", "\r", "\n"),
                                ' or ', trim($_SETT['admin']['email'])) .
                            ', who can investigate possible misuse of the system.' . "\n\n";

            // Add the location of the database, so that the user can just click the link.
            if ($_CONF['location_url']) {
                $sMessage .= 'To log in to LOVD, click this link:' . "\n" .
                          $_CONF['location_url'] . 'login' . "\n\n";
            }
            $sMessage .= 'Regards,' . "\n" .
                         '    LOVD ' . $_SETT['system']['version'] . ' system at ' . $_CONF['institute'] . "\n\n";

            // Array containing the unlock code field.
            $zData['password_autogen'] = $sPasswd;
            $aMailFields = array(
                            'zData',
                            'username' => 'Your username',
                            'password_autogen' => 'New password / unlocking code',
                           );


            $aBody = array($sMessage, 'restore_password' => $aMailFields);

            $sBody = lovd_formatMail($aBody);

            $sSubject = 'LOVD password reset'; // Don't just change this; lovd_sendMail() is parsing it.

            // Send mail.
            $bMail = lovd_sendMail($aTo, $sSubject, $sBody, $_SETT['email_headers'], true, $_CONF['send_admin_submissions']);

            // Thank the user...
            $_T->printHeader();
            $_T->printTitle();

            if ($bMail) {
                print('      If you entered the username or email address correctly, we have successfully reset your password and we have sent you an email containing your new password.' . "\n" .
                      '      With this new password, you can <A href="' . ROOT_PATH . 'login">unlock your account</A> and choose a new password.<BR><BR>' . "\n" .
                      '      If you don\'t receive this email, it is possible that the username or email address that you entered was not correct. In that case, please double-check it. Another possibility is that you registered at a different LOVD installation. Accounts are not shared between different LOVD installations, so please double-check where you are registered.<BR><BR>' . "\n\n");
            } else {
                // Couldn't send confirmation...
                lovd_writeLog('Error', LOG_EVENT, 'Error sending email for account ' . $_AUTH['username'] . ' (' . $zData['name'] . ')');
                print('      Due to an error, we couldn\'t send you an email containing your new password. Our apologies for the inconvenience.<BR><BR>' . "\n\n");
            }

            $_T->printFooter();
            exit;

        } else {
            unset($_POST['username']);
        }
    }



    $_T->printHeader();
    $_T->printTitle();

    print('      If you forgot your password, please fill in your username or email address here. If an account exists that matches this information, a new random password will be generated and emailed to the known email address. You need this new password to unlock your account and choose a new password.<BR>' . "\n" .
          '      <BR>' . "\n\n");

    lovd_errorPrint();

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array(
                    array('POST', '', '', '', '30%', '20', '70%'),
                    array('Username', '', 'text', 'username', 20),
                    array('', '', 'submit', 'Reset password'),
                  );
    lovd_viewForm($aForm);
    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}
?>
