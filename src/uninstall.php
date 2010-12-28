<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-01-13
 * Modified    : 2010-05-07
 * For LOVD    : 3.0-pre-07
 *
 * Copyright   : 2004-2010 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 * Last edited : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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
require ROOT_PATH . 'inc-lib-form.php';
require ROOT_PATH . 'inc-init.php';

require ROOT_PATH . 'install/inc-top.php'; // Install dir's own top include.
print('<TABLE border="0" cellpadding="0" cellspacing="0" width="100%" style="padding : 0px 10px;">' . "\n" .
      '  <TR valign="top">' . "\n" .
      '    <TD>' . "\n");
lovd_printHeader('Uninstall LOVD');

// Require DB admin clearance.
lovd_requireAUTH(LEVEL_ADMIN);

// If we've gotten this far, we apparently have/had an config, status and user
// table. This should suffice to uninstall LOVD even if we don't have a working
// installation.

// Uninstall lock set?
if (isset($_CONF['lock_uninstall']) && $_CONF['lock_uninstall']) {
    // Sorry, can't remove LOVD...
    lovd_showInfoTable('Can\'t uninstall LOVD - Uninstall lock in place.', 'warning');
    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_POST)) {
    lovd_errorClean();

    if (!isset($_GET['confirm'])) {
        // Check password.
        if (md5($_POST['password']) != $_AUTH['password']) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }
    }

    if (!lovd_error()) {
        if (isset($_GET['confirm'])) {
            // Check password.
            if (md5($_POST['password']) != $_AUTH['password']) {
                lovd_errorAdd('password', 'Please enter your correct password for authorization.');
            }

            if (!lovd_error()) {
                // OK, uninstall the lot.
                print('      <B>Uninstalling LOVD...</B><BR>' . "\n" .
                      '      <BR>' . "\n\n");

                print('      <TABLE border="0" cellpadding="0" cellspacing="0" width="440">' . "\n" .
                      '        <TR>' . "\n" .
                      '          <TD width="400" style="border : 1px solid black; height : 15px;">' . "\n" .
                      '            <IMG src="gfx/trans.png" alt="" title="0%" width="0%" height="15" id="lovd_install_progress_bar" style="background : #224488;"></TD>' . "\n" .
                      '          <TD width="40" align="right" id="lovd_install_progress_value">0%</TD></TR></TABLE>' . "\n\n" .
                      '      <DIV id="lovd_install_progress_text" style="margin-top : 0px;">' . "\n" .
                      '        Initiating removal of LOVD...' . "\n" .
                      '      </DIV><BR>' . "\n\n\n" .
                      '      <DIV id="install_form" style="visibility : hidden;">' . "\n" .
                      '      </DIV>' . "\n\n" .
                      '      <SCRIPT type="text/javascript">' . "\n" .
                      '        var progress_bar = document.getElementById(\'lovd_install_progress_bar\');' . "\n" .
                      '        var progress_value = document.getElementById(\'lovd_install_progress_value\');' . "\n" .
                      '        var progress_text = document.getElementById(\'lovd_install_progress_text\');' . "\n" .
                      '        var install_form = document.getElementById(\'install_form\');' . "\n" .
                      '      </SCRIPT>' . "\n\n\n");

                define('_INC_BOT_CLOSE_HTML_', false); // Sounds kind of stupid, but this prevents the inc-bot to actually cloes the <BODY> and <HTML> tags.
                require ROOT_PATH . 'install/inc-bot.php';

                flush();

                // Now we're still in the <BODY> so we can add <SCRIPT> tags as much as we want.
                // The reason to invert the tables is to handle all foreign key constraints nicely.
                $aTables = array_reverse($_TABLES);
                $nTables = count($aTables);

                print('<SCRIPT type="text/javascript">progress_text.innerHTML=\'Removing data tables...\';</SCRIPT>' . "\n");

                // Actually run the SQL...
                $nSQLDone = 0;
                $nSQLDonePercentage = 0;
                $nSQLDonePercentagePrev = 0;

                foreach ($aTables as $sTable) {
                    $sSQL = 'DROP TABLE IF EXISTS ' . $sTable;
                    $q = lovd_queryDB($sSQL);
                    if (!$q) {
                        // Error when running query. We will use the Div for the form now.
                        $sMessage = 'Error during uninstallation while running query.<BR>I ran:<DIV class="err">' . str_replace(array("\r\n", "\r", "\n"), '<BR>', $sSQL) . '</DIV><BR>I got:<DIV class="err">' . str_replace(array("\r\n", "\r", "\n"), '<BR>', mysql_error()) . '</DIV><BR><BR>' .
                                    'A failed uninstallation is most likely caused by a bug in LOVD.<BR>' .
                                    'Please <A href="' . $_SETT['upstream_URL'] . 'bugs/" target="_blank">file a bug</A> and include the above messages to help us solve the problem.';
                        print('<SCRIPT type="text/javascript">install_form.innerHTML=\'' . str_replace('\'', '\\\'', $sMessage) . '\'; install_form.style.visibility=\'visible\';</SCRIPT>' . "\n");
                        lovd_queryDB('DROP TABLE IF EXISTS ' . implode(', ', $aTables)); // Try again to remove everything.
                        print('</BODY>' . "\n" .
                              '</HTML>' . "\n");
                        exit;
                    }
                    $nSQLDone ++;

                    $nSQLDonePercentage = round(100*$nSQLDone / $nTables);
                    if ($nSQLDonePercentage == 100 && $nSQLDone != $nTables) {
                        // Don't want to show 100% when an error occurs at 99.5%.
                        $nSQLDonePercentage = 99;
                    }
                    if ($nSQLDonePercentage != $nSQLDonePercentagePrev) {
                        print('<SCRIPT type="text/javascript">progress_bar.style.width = \'' . $nSQLDonePercentage . '%\'; progress_value.innerHTML = \'' . $nSQLDonePercentage . '%\'; </SCRIPT>' . "\n");
                        $nSQLDonePercentagePrev = $nSQLDonePercentage;
                    }
                    flush();
                    usleep(5000);
                }
                usleep(300000);

                // All done!
                print('<SCRIPT type="text/javascript">progress_text.innerHTML=\'LOVD successfully uninstalled!<BR>Thank you for having used LOVD!\'; install_form.style.visibility=\'visible\';</SCRIPT>' . "\n");
                print('</BODY>' . "\n" .
                      '</HTML>' . "\n");
                exit;
            }

        } else {
            // Show some general statistics and warn about loss of data.
            print('      <PRE>' . "\n");

            // Does any of these tables exist yet?
            print('Checking LOVD installation...' . "\n");
            $aTables = array();
            $q = mysql_query('SHOW TABLES LIKE "' . TABLEPREFIX . '\_%"');
            while ($r = mysql_fetch_row($q)) {
                if (in_array($r[0], $_TABLES)) {
                    $aTables[] = $r[0];
                }
            }
            $nTables = count($aTables);
            print('  Found ' . $nTables . '/' . count($_TABLES) . ' tables.' . "\n");

            // General statistics...
            print("\n");
            list($nUsers) = mysql_fetch_row(mysql_query('SELECT COUNT(*) FROM ' . TABLE_USERS));
            list($nPats) = mysql_fetch_row(mysql_query('SELECT COUNT(*) FROM ' . TABLE_PATIENTS));
            list($nScreenings) = mysql_fetch_row(mysql_query('SELECT COUNT(*) FROM ' . TABLE_SCREENINGS));
            list($nVars) = mysql_fetch_row(mysql_query('SELECT COUNT(*) FROM ' . TABLE_VARIANTS));
            $nGenes = GENE_COUNT;
            print('  Found ' . $nUsers . ' user' . ($nUsers == 1? '' : 's') . '.' . "\n" .
                  '  Found ' . $nPats . ' patient' . ($nPats == 1? '' : 's') . '.' . "\n" .
                  '  Found ' . $nScreenings . ' screening' . ($nScreenings == 1? '' : 's') . '.' . "\n" .
                  '  Found ' . $nVars . ' variant' . ($nVars == 1? '' : 's') . '.' . "\n" .
                  '  Found ' . $nGenes . ' gene' . ($nGenes == 1? '' : 's') . '.' . "\n" .
                  '      </PRE>' . "\n");

            if ($nGenes || $nPats || $nVars) {
                lovd_showInfoTable('FINAL WARNING! If you did not download the variation and patient data stored in the LOVD system, everything will be lost!', 'warning');
            }

            print('      Please confirm uninstalling LOVD using your password.<BR>' . "\n" .
                  '      <BR>' . "\n\n");
        }

        lovd_errorPrint();

        print('      <FORM action="uninstall?confirm" method="post">' . "\n" .
              '        <TABLE border="0" cellpadding="0" cellspacing="0" width="300">' . "\n" .
              '          <TR align="right">' . "\n" .
              '            <TD width="125" style="padding-right : 5px;">Password</TD>' . "\n" .
              '            <TD width="175"><INPUT type="password" name="password" size="20"></TD></TR>' . "\n" .
              '          <TR align="right">' . "\n" .
              '            <TD width="125">&nbsp;</TD>' . "\n" .
              '            <TD width="175"><INPUT type="submit" value="Uninstall LOVD" style="font-weight : bold; font-size : 11px; width : 110px;"></TD></TR></TABLE></FORM>' . "\n\n");

        require ROOT_PATH . 'install/inc-bot.php';
        exit;
    }
}

if (empty($_POST)) {
    print('      Welcome to the LOVD uninstaller. Please continue by providing your password.<BR>' . "\n" .
          '      <BR>' . "\n\n");

    lovd_showInfoTable('WARNING! If you did not download your data, you will loose all of it!', 'warning');
}

lovd_errorPrint();

print('      <FORM action="uninstall" method="post">' . "\n" .
      '        <TABLE border="0" cellpadding="0" cellspacing="0" width="300">' . "\n" .
      '          <TR align="right">' . "\n" .
      '            <TD width="125" style="padding-right : 5px;">Password</TD>' . "\n" .
      '            <TD width="175"><INPUT type="password" name="password" size="20"></TD></TR>' . "\n" .
      '          <TR align="right">' . "\n" .
      '            <TD width="125">&nbsp;</TD>' . "\n" .
      '            <TD width="175">' . "\n" .
      '              <TABLE border="0" cellpadding="0" cellspacing="0" width="162">' . "\n" .
      '                <TR>' . "\n" .
      '                  <TD align="left"><INPUT type="button" value="&lt;&lt; Cancel" onclick="window.location.href=\'' . PROTOCOL . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/' . ROOT_PATH . 'setup\';" style="font-weight : bold; font-size : 11px; width : 80px;"></TD>' . "\n" .
      '                  <TD align="right"><INPUT type="submit" value="Next &gt;&gt;" style="font-weight : bold; font-size : 11px; width : 70px;"></TD></TR></TABLE></TD></TR></TABLE></FORM>' . "\n\n");

require ROOT_PATH . 'install/inc-bot.php';
exit;
?>