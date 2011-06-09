<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-05-20
 * Modified    : 2011-06-09
 * For LOVD    : 3.0-alpha-01
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *
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
 
if (!$_AUTH && $_CONF['allow_unlock_accounts']) {
    // User forgot password - replace.
    
    define('PAGE_TITLE', 'User - Reset password');
    define('LOG_EVENT', 'ResetPasswdError')

    // Require form functions.
    require ROOT_PATH . 'inc-lib-form.php';

    if (POST && !empty($_POST['username'])) {
        lovd_errorClean();

        // Find account.
        $zData = mysql_fetch_assoc(lovd_queryDB('SELECT * FROM ' . TABLE_USERS . ' WHERE username = ?', array($_POST['username'])));
        if (!$zData) {
            lovd_errorAdd('This username does not exist.');
            // FIXME, change to 3.0 way of doing this.
            // Wat bedoel je hier exact mee?
            lovd_writeLog('Auth', LOG_EVENT, $_SERVER['REMOTE_ADDR'] . ' (' . gethostbyaddr($_SERVER['REMOTE_ADDR']) . ') tried to reset password for inexistent/denied account ' . $_POST['username']);
        }

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
            $q = @lovd_queryDB('UPDATE ' . TABLE_USERS . ' SET password_autogen = MD5(?) WHERE username = ?', array($sPasswd, $_POST['username']), true);

            lovd_writeLog('Auth', LOG_EVENT, $_SERVER['REMOTE_ADDR'] . ' (' . gethostbyaddr($_SERVER['REMOTE_ADDR']) . ') successfully reset password for account ' . $_POST['username']);

            // Send email confirmation.
            $_POST['password_autogen'] = $sPasswd;

            $sBody = 'LOVD ' . $_SETT['system']['version'] . ' @ ' . $_CONF['institute'] . "\n\n" .
                     'Dear ' . $zData['name'] . ',' . "\n\n" .
                     'Your password from your LOVD account has been reset, as requested. Your new, randomly generated, password can be found below. Please log in to LOVD and choose a new password.' . "\n\n" .
                     'Below is a copy of your updated account information.' . "\n\n" .
                     'If you did not request a new password, you can disregard this message. Your old password will continue to function normally. However, you may then want to report this email to the LOVD manager, who can investigate possible misuse of the system.' . "\n\n";
            // Add the location of the database, so that the user can just click the link.
            if ($_CONF['location_url']) {
                $sBody .= 'To log in to LOVD, click this link:' . "\n" .
                          $_CONF['location_url'] . 'login.php' . "\n\n";
            }
            $sBody .= str_repeat('-', 80) . "\n";

            // Array met data.
            $aMail = array(
                            'password_autogen' => 'New password / unlocking code',
                           );

            // Padding to...
            $lPad = 0;
            foreach ($aMail as $val) {
                $l = strlen($val);
                if ($l > $lPad) {
                    $lPad = $l;
                }
            }

            foreach ($aMail as $key => $val) {
                $sBody .= str_pad($val, $lPad) . ' : ' . str_replace("\n", "\n" . str_repeat(' ', $lPad + 3), lovd_wrapText($_POST[$key], 80 - $lPad - 3)) . "\n";
            }
            $sBody .= str_repeat('-', 80) . "\n\n" .
                      'Regards,' . "\n" .
                      '    LOVD system at ' . $_CONF['institute'] . "\n\n";
            $sSubject = 'LOVD password reset';

            // For submitters, we need to take the FIRST email address only.
            if (isset($zData['submitterid'])) {
                $aEmail = preg_split('/(\r\n|\r|\n)+/', trim($zData['email']));
                $zData['email'] = $aEmail[0];
            }

            // Send mail.
            // Sending emails on Windows requires removal of names from the email addresses.
            $bMail = @mail((ON_WINDOWS? '' : '"' . $zData['name'] . '" ') . '<' . str_replace(array("\r\n", "\r", "\n"), '>, <', trim($zData['email'])) . '>',
                           $sSubject,
                           lovd_wrapText($sBody),
                           $_SETT['email_headers']);

            // Thank the user...
            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);

            if ($bMail) {
                print('      Successfully reset your password.<BR>' . "\n" .
                      '      We\'ve sent you an email containing your new password. With this new password, you can <A href="' . ROOT_PATH . 'login.php">unlock your account</A> and choose a new password.<BR><BR>' . "\n\n");
            } else {
                // Couldn't send confirmation...
                lovd_writeLog('Error', LOG_EVENT, $_SERVER['PHP_SELF'] . ' returned ResetPasswdErrorNotify error for account ' . $_AUTH['username'] . ' (' . mysql_real_escape_string($zData['name']) . ')');
                print('      Due to an error, we couldn\'t send you an email containing your new password. Our apologies for the inconvenience.<BR><BR>' . "\n\n");
            }

            require ROOT_PATH . 'inc-bot.php';
            exit;

        } else {
            lovd_writeLog('Auth', LOG_EVENT, $_SERVER['REMOTE_ADDR'] . ' (' . gethostbyaddr($_SERVER['REMOTE_ADDR']) . ') tried to reset password for denied account ' . $_POST['username']);
        }
    }



    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    print('      If you forgot your password, please fill in your username here. A new random password will be generated and emailed to the known email address. You need this new password to unlock your account and choose a new password.<BR>' . "\n" .
          '      <BR>' . "\n\n");

    lovd_errorPrint();

    // Table.
    print('      <FORM action="' . $_PATH_ELEMENTS[0] . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array(
                    array('POST', '', '', '', '30%', '20', '70%'),
                    array('Username', '', 'text', 'username', 20),
                    'skip',
                    array('', '', 'submit', 'Reset password'),
                  );
    lovd_viewForm($aForm);
    print('</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}
?>
