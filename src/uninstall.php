<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-01-13
 * Modified    : 2012-06-19
 * For LOVD    : 3.0-beta-06
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
require ROOT_PATH . 'inc-lib-form.php';

$_T->printHeader();
$_T->printTitle('Uninstall LOVD');

// Require DB admin clearance.
lovd_requireAUTH(LEVEL_ADMIN);

// If we've gotten this far, we apparently have/had an config, status and user
// table. This should suffice to uninstall LOVD even if we don't have a working
// installation.

// Uninstall lock set?
if (isset($_CONF['lock_uninstall']) && $_CONF['lock_uninstall']) {
    // Sorry, can't remove LOVD...
    lovd_showInfoTable('Can\'t uninstall LOVD - Uninstall lock in place.', 'warning');
    $_T->printFooter();
    exit;
}





if (!empty($_POST)) {
    lovd_errorClean();

    if (!isset($_GET['confirm'])) {
        // Check password.
        if (!lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }
    }

    if (!lovd_error()) {
        if (isset($_GET['confirm'])) {
            // Check password.
            if (!lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
                lovd_errorAdd('password', 'Please enter your correct password for authorization.');
            }

            if (!lovd_error()) {
                // OK, uninstall the lot.
                print('      <B>Uninstalling LOVD...</B><BR>' . "\n" .
                      '      <BR>' . "\n\n");

                require ROOT_PATH . 'class/progress_bar.php';
                // This already puts the progress bar on the screen.
                $_BAR = new ProgressBar('', 'Initiating removal of LOVD...');

                $_T->printFooter(false); // The false prevents the footer to actually close the <BODY> and <HTML> tags.

                // Now we're still in the <BODY> so the progress bar can add <SCRIPT> tags as much as it wants.
                flush();

                // The reason to invert the tables is to handle all foreign key constraints nicely.
                $aTables = array_reverse($_TABLES);
                $nTables = count($aTables);

                $_BAR->setMessage('Removing data tables...');

                // Actually run the SQL...
                $nSQLDone = 0;
                $nSQLDonePercentage = 0;
                $nSQLDonePercentagePrev = 0;

                foreach ($aTables as $sTable) {
                    $sSQL = 'DROP TABLE IF EXISTS ' . $sTable;
                    $q = $_DB->query($sSQL);
                    if (!$q) {
                        // Error when running query. We will use the Div for the form now.
                        $sMessage = 'Error during uninstallation while running query.<BR>I ran:<DIV class="err">' . str_replace(array("\r\n", "\r", "\n"), '<BR>', $sSQL) . '</DIV><BR>I got:<DIV class="err">' . str_replace(array("\r\n", "\r", "\n"), '<BR>', $_DB->formatError()) . '</DIV><BR><BR>' .
                                    'A failed uninstallation is most likely caused by a bug in LOVD.<BR>' .
                                    'Please <A href="' . $_SETT['upstream_URL'] . 'bugs/" target="_blank">file a bug</A> and include the above messages to help us solve the problem.';
                        $_BAR->setMessage($sMessage, 'done');
                        $_BAR->setMessageVisibility('done', true);
                        $_DB->query('DROP TABLE IF EXISTS ' . implode(', ', $aTables)); // Try again to remove everything.
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
                        $_BAR->setProgress($nSQLDonePercentage);
                        $nSQLDonePercentagePrev = $nSQLDonePercentage;
                    }
                    flush();
                    usleep(5000);
                }
                usleep(300000);

                // All done!
                $_BAR->setMessage('LOVD successfully uninstalled!<BR>Thank you for having used LOVD!');
                $_BAR->setMessageVisibility('done', true);
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
            $qTables = $_DB->query('SHOW TABLES LIKE "' . TABLEPREFIX . '\_%"');
            while ($sTable = $qTables->fetchRow()) {
                if (in_array($sTable, $_TABLES)) {
                    $aTables[] = $sTable;
                }
            }
            $nTables = count($aTables);
            // FIXME. remove later when TABLE_PATHOGENIC is exterminated in all LOVD installations.
            //print('  Found ' . $nTables . '/' . count($_TABLES) . ' tables.' . "\n");
            $_TABLES_cleaned = $_TABLES;
            unset($_TABLES_cleaned['TABLE_PATHOGENIC']);
            print('  Found ' . $nTables . '/' . count($_TABLES_cleaned) . ' tables.' . "\n");

            // FIXME; add more later.
            // General statistics...
            print("\n");
            // 2012-02-01; 3.0-beta-02; Exclude "LOVD" system user.
            $nUsers = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_USERS . ' WHERE id > 0')->fetchColumn();
            $nIndividuals = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_INDIVIDUALS)->fetchColumn();
            $nScreenings = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_SCREENINGS)->fetchColumn();
            $nVars = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_VARIANTS)->fetchColumn();
            $nGenes = count(lovd_getGeneList());
            print('  Found ' . $nUsers . ' user' . ($nUsers == 1? '' : 's') . '.' . "\n" .
                  '  Found ' . $nIndividuals . ' individual' . ($nIndividuals == 1? '' : 's') . '.' . "\n" .
                  '  Found ' . $nScreenings . ' screening' . ($nScreenings == 1? '' : 's') . '.' . "\n" .
                  '  Found ' . $nVars . ' variant' . ($nVars == 1? '' : 's') . '.' . "\n" .
                  '  Found ' . $nGenes . ' gene' . ($nGenes == 1? '' : 's') . '.' . "\n" .
                  '      </PRE>' . "\n");

            if ($nGenes || $nIndividuals || $nVars) {
                lovd_showInfoTable('FINAL WARNING! If you did not download the variation and individual data stored in the LOVD system, everything will be lost!', 'warning');
            }

            print('      Please confirm uninstalling LOVD using your password.<BR>' . "\n" .
                  '      <BR>' . "\n\n");
        }

        lovd_errorPrint();

        print('      <FORM action="' . $_PE[0] . '?confirm" method="post">' . "\n" .
              '        <TABLE border="0" cellpadding="0" cellspacing="0" width="300">' . "\n" .
              '          <TR align="right">' . "\n" .
              '            <TD width="125" style="padding-right : 5px;">Password</TD>' . "\n" .
              '            <TD width="175"><INPUT type="password" name="password" size="20"></TD></TR>' . "\n" .
              '          <TR align="right">' . "\n" .
              '            <TD width="125">&nbsp;</TD>' . "\n" .
              '            <TD width="175"><INPUT type="submit" value="Uninstall LOVD" style="font-weight : bold; font-size : 11px; width : 110px;"></TD></TR></TABLE></FORM>' . "\n\n");

        $_T->printFooter();
        exit;
    }
}

if (empty($_POST)) {
    print('      Welcome to the LOVD uninstaller. Please continue by providing your password.<BR>' . "\n" .
          '      <BR>' . "\n\n");

    lovd_showInfoTable('WARNING! If you did not download your data, you will lose all of it!', 'warning');
}

lovd_errorPrint();

print('      <FORM action="' . $_PE[0] . '" method="post">' . "\n" .
      '        <TABLE border="0" cellpadding="0" cellspacing="0" width="300">' . "\n" .
      '          <TR align="right">' . "\n" .
      '            <TD width="125" style="padding-right : 5px;">Password</TD>' . "\n" .
      '            <TD width="175"><INPUT type="password" name="password" size="20"></TD></TR>' . "\n" .
      '          <TR align="right">' . "\n" .
      '            <TD width="125">&nbsp;</TD>' . "\n" .
      '            <TD width="175">' . "\n" .
      '              <TABLE border="0" cellpadding="0" cellspacing="0" width="162">' . "\n" .
      '                <TR>' . "\n" .
      '                  <TD align="left"><INPUT type="button" value="&lt;&lt; Cancel" onclick="window.location.href=\'' . lovd_getInstallURL() . 'setup\';" style="font-weight : bold; font-size : 11px; width : 80px;"></TD>' . "\n" .
      '                  <TD align="right"><INPUT type="submit" value="Next &gt;&gt;" style="font-weight : bold; font-size : 11px; width : 70px;"></TD></TR></TABLE></TD></TR></TABLE></FORM>' . "\n\n");

$_T->printFooter();
exit;
?>