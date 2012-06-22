<?php
// DMD_SPECIFIC: changes to this file? TEST it!!!
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-19
 * Modified    : 2012-06-07
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

define('ROOT_PATH', '../');
require ROOT_PATH . 'inc-init.php';

// Array containing install steps.
$aInstallSteps =
         array(
                array(0, 'Introduction'),
                array(0, 'Administrator account details'),
                array(30, 'Installing database tables'),
                array(90, 'Configuring LOVD system settings'),
                //array(95, 'Configuring LOVD modules'),
                array(100, 'Done'),
              );

if (empty($_GET['step']) || !preg_match('/^[0-5]$/', $_GET['step'])) {
    $_GET['step'] = 0;
}



function lovd_getInstallForm ($bPassPost = true)
{
    // Prints FORM tag providing the 'Next' button.
    $s = '      <FORM action="install/?step=' . ($_GET['step'] + 1) . '" method="post">' . "\n";
    if ($bPassPost) {
        foreach ($_POST as $key => $val) {
            // Added htmlspecialchars to prevent XSS and allow values to include quotes.
            $s .= '        <INPUT type="hidden" name="' . $key . '" value="' . htmlspecialchars($val) . '">' . "\n";
        }
    }
    $s .= '        <INPUT type="submit" value="' . ($_GET['step']? 'Next' : 'Start') . ' &gt;&gt;" style="font-weight : bold; font-size : 11px;">' . "\n" .
          '      </FORM>' . "\n\n";
    return $s;
}





function lovd_printInstallForm ($bPassPost = true)
{
    print(lovd_getInstallForm($bPassPost));
}





function lovd_printSideBar ()
{
    // Shows sidebar with installation steps and the installation progress bar.
    // Sidebar with the steps laid out.
    global $aInstallSteps;

    if (ROOT_PATH == '../') { // Basically, when installing!
        print('<TABLE border="0" cellpadding="0" cellspacing="0" width="100%">' . "\n" .
              '  <TR valign="top">' . "\n" .
              '    <TD width="190">' . "\n" .
              '      <TABLE border="0" cellpadding="5" cellspacing="0" align="left" width="100%" class="S11">' . "\n" .
              '        <TR align="center" class="S13">' . "\n" .
              '          <TH style="background : #224488; color : #FFFFFF; height : 20px; border : 1px solid #002266;"><IMG src="gfx/trans.png" alt="" width="178" height="1"><BR><I>Installation steps</I></TH></TR>');

        foreach ($aInstallSteps as $nStep => $aStep) {
            // Loop through install steps.
            print("\n" .
                  '        <TR align="center">' . "\n" .
                  '          <TD style="height : 60px; border : 1px solid #002266; border-top : 0px; background : #' . ($nStep == $_GET['step']? 'CCE0FF; font-weight : bold' : ($nStep < $_GET['step']? 'F0F0F0; color : #666666' : 'FFFFFF')) . ';">' . $aStep[1] . '</TD></TR>');
        }

        // Close table.
        print('</TABLE></TD>' . "\n" .
              '    <TD style="padding-left : 10px;">' . "\n\n");



        // Top progress bar.
        print("\n" .
              '      <TABLE border="0" cellpadding="0" cellspacing="0" class="S11" width="100%">' . "\n" .
              '        <TR>' . "\n" .
              '          <TD colspan="2">Installation progress</TD></TR>' . "\n" .
              '        <TR>' . "\n" .
              '          <TD colspan="2" style="border : 1px solid black; height : 5px;">' . "\n" .
              '            <IMG src="gfx/trans.png" alt="" title="' . $aInstallSteps[$_GET['step']][0] . '%" width="' . $aInstallSteps[$_GET['step']][0] . '%" height="5" id="lovd_install_bar" style="background : #99EE66;"></TD></TR>' . "\n" .
              '        <TR>' . "\n" .
              '          <TD align="left">0%</TD>' . "\n" .
              '          <TD align="right">100%</TD></TR></TABLE><BR>' . "\n\n");
    }
}





if ($_GET['step'] == 0 && defined('NOT_INSTALLED')) {
    // Show some intro.
    $_T->printHeader();
    lovd_printSideBar();

    print('      <B>Welcome to the LOVD v.' . $_STAT['tree'] . '-' . $_STAT['build'] . ' installer</B><BR>
      <BR>' . "\n\n");

    // Requirements. This is where we would want to check software versions, PHP modules, etc.

    // Check for PHP version, PHP functions, MySQL version.
    $sPHPVers = str_replace('_', '-', PHP_VERSION) . '-';
    $sPHPVers = substr($sPHPVers, 0, strpos($sPHPVers, '-'));
    $bPHP = ($sPHPVers >= $aRequired['PHP']);
    $sPHP = '<IMG src="gfx/mark_' . (int) $bPHP . '.png" alt="" width="11" height="11">&nbsp;PHP : ' . $sPHPVers . ' (' . $aRequired['PHP'] . ' required)';

    // 2012-02-01; 3.0-beta-02; Added check for xml_parser_create(), easily allowing other PHP functions to be checked also.
    $bPHPFunctions = true;
    $sPHPFunctions = '';
    foreach ($aRequired['PHP_functions'] as $sFunction) {
        $bFunction = function_exists($sFunction);
        if (!$bFunction) {
            $bPHPFunctions = false;
        }
        $sPHPFunctions .= '&nbsp;&nbsp;<IMG src="gfx/mark_' . (int) $bFunction . '.png" alt="" width="11" height="11">&nbsp;PHP function : ' . $sFunction . '()<BR>';
    }

    $sMySQLVers = str_replace('_', '-', $_DB->getServerInfo()) . '-';
    $sMySQLVers = substr($sMySQLVers, 0, strpos($sMySQLVers, '-'));
    $bMySQL = ($sMySQLVers >= $aRequired['MySQL']);
    $sMySQL = '<IMG src="gfx/mark_' . (int) $bMySQL . '.png" alt="" width="11" height="11">&nbsp;MySQL : ' . $sMySQLVers . ' (' . $aRequired['MySQL'] . ' required)';

    // Check for InnoDB support.
    $sInnoDB = $_DB->query('SHOW VARIABLES LIKE "have\_innodb"')->fetchColumn(1);
    $bInnoDB = ($sInnoDB == 'YES');
    $sInnoDB = '&nbsp;&nbsp;<IMG src="gfx/mark_' . (int) $bInnoDB . '.png" alt="" width="11" height="11">&nbsp;MySQL InnoDB support ' . ($bInnoDB? 'en' : 'dis') . 'abled (required)';

    // 2012-02-01; 3.0-beta-02; Check for "MultiViews" or Apache's mod_rewrite, or anything some other webserver may have that does the same.
    $aResultNoExt = @lovd_php_file(lovd_getInstallURL() . 'setup');
    $aResultExt   = @lovd_php_file(lovd_getInstallURL() . 'setup.php');
    $bMultiViews  = !(!$aResultNoExt && $aResultExt);
    $sMultiViews  = '<IMG src="gfx/mark_' . (int) $bMultiViews . '.png" alt="" width="11" height="11">&nbsp;MultiViews, mod_rewrite or equivalent : ' . ($bMultiViews? 'en' : 'dis') . 'abled (required)';
    // FIXME; link to manual?

    if (!$bPHP || !$bPHPFunctions || !$bMySQL || !$bInnoDB || !$bMultiViews) {
        // Failure!
        lovd_showInfoTable('One or more requirements are not met!<BR>I will now bluntly refuse to install.<BR><BR>' .
                           $sPHP . '<BR>' .
                           $sPHPFunctions .
                           $sMySQL . '<BR>' .
                           $sInnoDB . '<BR>' .
                           $sMultiViews, 'stop');
        $_T->printFooter();
        exit;
    } else {
        // Success!
        lovd_showInfoTable('System check for requirements all OK!<BR><BR>' .
                           $sPHP . '<BR>' .
                           $sPHPFunctions .
                           $sMySQL . '<BR>' .
                           $sInnoDB . '<BR>' .
                           $sMultiViews, 'success');
    }

    print('      The installation of LOVD consists of ' . (count($aInstallSteps) - 1) . ' simple steps.
      This installer will create the LOVD tables in the MySQL database, create the Administrator account and will help you configure LOVD. Installation and initial configuration of LOVD should be simple for a relatively experienced computer user.<BR>
      <BR>
      The installation progress bar at the top of the screen shows how far you are in the installation process. The installation steps are shown at the left of the screen.<BR>
      <BR>' . "\n\n");

    lovd_printInstallForm();

    $_T->printFooter();
    exit;
} elseif ($_GET['step'] == 0) { $_GET['step'] ++; }





if ($_GET['step'] == 1 && defined('NOT_INSTALLED')) {
    // Step 1: Administrator account details.
    if ($_DB->query('SHOW TABLES LIKE "' . TABLE_USERS . '"')->fetchColumn() && $_DB->query('SELECT COUNT(*) FROM ' . TABLE_USERS)->fetchColumn()) {
        // We already have a database user!
        header('Location: ' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . '?step=' . ($_GET['step'] + 2));
        exit;
    }

    $_T->printHeader();
    lovd_printSideBar();
    require ROOT_PATH . 'inc-lib-form.php';

    // Load User class.
    require ROOT_PATH . 'class/object_users.php';
    $_USER = new LOVD_User();

    print('      <B>Administrator account details</B><BR>' . "\n" .
          '      <BR>' . "\n\n");

    if (isset($_GET['sent'])) {
        lovd_errorClean();

        $_USER->checkFields($_POST);

        if (!lovd_error()) {
            // Gather information and go to next page.

            // Prepare password...
            $_POST['password'] = lovd_createPasswordHash($_POST['password_1']);
            unset($_POST['password_1'], $_POST['password_2']);

            print('      Account details OK. Ready to proceed to the next step.<BR>' . "\n" .
                  '      <BR>' . "\n\n");

            lovd_printInstallForm();

            $_T->printFooter();
            exit;

        } else {
            // Errors, thus we must return to the form. Remove the password fields!
            unset($_POST['password_1'], $_POST['password_2']);
        }

    } else {
        // Default values.
        $_USER->setDefaultValues();
    }

    if (!isset($_GET['sent'])) {
        print('      Please fill in the Administrator\'s account details and press \'Continue\' to continue the installation.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    // Table.
    print('      <FORM action="install/?step=' . $_GET['step'] . '&amp;sent=true" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_USER->getForm(),
                 array(
                        'skip',
                        array('', '', 'submit', 'Continue &raquo;'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
} elseif ($_GET['step'] == 1) { $_GET['step'] ++; }





if ($_GET['step'] == 2 && defined('NOT_INSTALLED')) {
    // Step 2: Install database tables.
    if ($_DB->query('SHOW TABLES LIKE "' . TABLE_CONFIG . '"')->fetchColumn() && !$_DB->query('SELECT COUNT(*) FROM ' . TABLE_CONFIG)->fetchColumn()) {
        // Installed, but not configured yet.
        header('Location: ' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . '?step=' . ($_GET['step'] + 1));
        exit;
    } elseif (!isset($_POST['username'])) {
        // Didn't finish previous step correctly.
        header('Location: ' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . '?step=' . ($_GET['step'] - 1));
        exit;
    }

    @set_time_limit(0); // We don't want the installation to time out in the middle of table creation.

    // Start session.
    $sSignature = md5($_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . time());
    // Set the session name to something unique, to prevent mixing cookies with other LOVDs on the same server.
    $_SETT['cookie_id'] = md5($sSignature);
    session_name('PHPSESSID_' . $_SETT['cookie_id']);

    session_start();

    $_T->printHeader();
    lovd_printSideBar();

    print('      <B>Installing LOVD...</B><BR>' . "\n" .
          '      <BR>' . "\n\n");

    // List of tables that need to be generated.
    require 'inc-sql-tables.php';
    $nTables = count($_TABLES);

    // Do any of these tables exist yet?
    $aTablesMatched = array();
    $aTablesFound = array();
    $q = $_DB->query('SHOW TABLES LIKE "' . TABLEPREFIX . '\_%"');
    while ($sTable = $q->fetchColumn()) {
        $aTablesMatched[] = $sTable;
        if (in_array($sTable, $_TABLES)) {
            $aTablesFound[] = $sTable;
        }
    }
    $nTablesMatched = count($aTablesMatched);
    $nTablesFound = count($aTablesFound);

    if ($nTablesFound) {
        if ($nTablesFound == $nTables) {
            // Maybe an existing LOVD install... Weird, because then we shouldn't have gotten here... Right?
            $sVersion = $_DB->query('SELECT version FROM ' . TABLE_STATUS, false, false)->fetchColumn();
            if ($sVersion) {
                print('      There seems to be an existing LOVD installation (' . $sVersion . ').<BR>' . "\n" .
                      '      <B>Installation of LOVD can not continue using the current database or table prefix.</B><BR>' . "\n" .
                      '      Please change the database settings in the config.ini or remove the existing LOVD install, and re-run the installation.<BR>' . "\n\n");
                $_T->printFooter();
                exit;
            }
        }

        print('      There ' . ($nTablesFound == 1? 'is an existing table' : 'are ' . $nTablesFound . ' existing tables') . ' found!<BR>' . "\n" .
              '      <B>Installation of LOVD can not continue if tables exist with the same name I need to create.</B><BR>' . "\n" .
              '      I found:<BR>' . "\n" .
              '      - ' . implode("<BR>\n" . '      - ', $aTablesFound) . "<BR>\n" .
              '      Please remove th' . ($nTablesFound == 1? 'is table' : 'ese tables') . ' and re-run the installation.<BR>' . "\n\n");
        $_T->printFooter();
        exit;
    }

    if ($nTablesMatched) {
        // Please note that in LOVD 2.0 this risk is more significant, since LOVD 2.0 creates new tables for every new gene (variants, columns). LOVD 3.0 does not do that.
        // Therefore, this risk is quite minimal and can only occur when upgrading LOVD 3.0 to a new build with new functionality and more database tables.
        print('      There ' . ($nTablesMatched == 1? 'is a possibly interfering table' : 'are ' . $nTablesMatched . ' possibly interfering tables') . ' found!<BR>' . "\n" .
              '      <B>Tables with names starting with the same prefix as the LOVD tables may interfere with LOVD at a later stage, if LOVD whishes to create a table with that name.</B><BR>' . "\n" .
              '      I found:<BR>' . "\n" .
              '      - ' . implode("<BR>\n" . '      - ', $aTablesMatched) . "<BR>\n" .
              '      You may want to consider (re)moving th' . ($nTablesMatched == 1? 'is table' : 'ese tables') . '.<BR>' . "\n\n");
    }

    require ROOT_PATH . 'class/progress_bar.php';
    // This already puts the progress bar on the screen.
    $_BAR = new ProgressBar('', 'Initiating installation...', lovd_getInstallForm(false));
    print('      <SCRIPT type="text/javascript">' . "\n" .
          '        var bar = document.getElementById(\'lovd_install_bar\');' . "\n" .
          '      </SCRIPT>' . "\n\n\n");

    $_T->printFooter(false); // The false prevents the footer to actually close the <BODY> and <HTML> tags.

    // Now we're still in the <BODY> so the progress bar can add <SCRIPT> tags as much as it wants.
    flush();

    // OK, we need to gather all SQL, so we know how many steps we need to make. Then we can loop through it.
    $aInstallSQL = array();
    $nInstallSQL = 0;


    // (1) LOVD data tables.
    $aInstallSQL['Creating ' . $nTables . ' LOVD data tables...'] = $aTableSQL;
    $nInstallSQL += $nTables;


    // (2) Creating user country list.
    require 'inc-sql-countries.php';
    $nCountries = count($aCountrySQL);
    $aInstallSQL['Creating user country list...'] = $aCountrySQL;
    $nInstallSQL += $nCountries;


    // (3) Creating LOVD user & administrator.
    $aInstallSQL['Creating LOVD account &amp; LOVD database administrator account...'] =
             array(
                    'INSERT INTO ' . TABLE_USERS . '(name, created_date) VALUES ("LOVD", NOW())',
                    'UPDATE ' . TABLE_USERS . ' SET id = 0, created_by = 0',
                    'INSERT INTO ' . TABLE_USERS . ' VALUES ("00001", ' . $_DB->quote($_POST['name']) . ', ' . $_DB->quote($_POST['institute']) . ', ' . $_DB->quote($_POST['department']) . ', ' . $_DB->quote($_POST['telephone']) . ', ' . $_DB->quote($_POST['address']) . ', ' . $_DB->quote($_POST['city']) . ', ' . $_DB->quote($_POST['countryid']) . ', ' . $_DB->quote($_POST['email']) . ', ' . $_DB->quote($_POST['reference']) . ', ' . $_DB->quote($_POST['username']) . ', ' . $_DB->quote($_POST['password']) . ', "", 0, "' . session_id() . '", "", ' . LEVEL_ADMIN . ', ' . $_DB->quote($_POST['allowed_ip']) . ', 0, NOW(), 1, NOW(), NULL, NULL)',
                  );
    $nInstallSQL ++;


    // (4) Registering chromosome references
    require ROOT_PATH . 'install/inc-sql-chromosomes.php';
    $aInstallSQL['Registering chromosome references...'][] = $aChromosomeSQL[0];
    $nInstallSQL ++;


    // (5) Registering LOVD variant statuses.
    $nStatuses = count($_SETT['data_status']);
    foreach ($_SETT['data_status'] as $nStatus => $sStatus) {
        $aInstallSQL['Registering LOVD variant statuses...'][] = 'INSERT INTO ' . TABLE_DATA_STATUS . ' VALUES (' . $nStatus . ', "' . $sStatus . '")';
    }
    $nInstallSQL += $nStatuses;


    // (6) Registering LOVD allele values.
    require ROOT_PATH . 'install/inc-sql-alleles.php';
    $aInstallSQL['Registering LOVD allele values...'][] = $aAlleleSQL[0];
    $nInstallSQL ++;


    // (7) Registering LOVD variant functional effects.
    require ROOT_PATH . 'install/inc-sql-variant_effect.php';
    $aInstallSQL['Registering LOVD variant functional effects...'][] = $aVariantEffectSQL[0];
    $nInstallSQL ++;


    // (8) Creating standard LOVD custom columns.
    require 'inc-sql-columns.php';
    $nCols = count($aColSQL);
    $aInstallSQL['Creating LOVD custom columns...'] = $aColSQL;
    $nInstallSQL += $nCols;


    // (9) Activating standard custom columns.
    foreach ($aColSQL as $sCol) {
        $sCol = str_replace('INSERT INTO ' . TABLE_COLS . ' VALUES ', '', $sCol);
        // FIXME; add some comments here, I can't follow this code.
        preg_match_all("/(\"(?:.*[^\\\\])?\"|\d+|NULL|NOW\(\)),\s+/U", trim($sCol, '()') . ', ', $aCol);
        // FIXME; misschien een list() hier?
        $aCol = array_map('preg_replace', array_fill(0, count($aCol[1]), '/^"(.*)"$/'), array_fill(0, count($aCol[1]), '$1'), $aCol[1]);
        if ($aCol[3] == '1' || $aCol[4] == '1') {
            $sCategory = preg_replace('/\/.*$/', '', $aCol[0]);
            if ($sCategory == 'VariantOnGenome') {
                $sTable = 'TABLE_VARIANTS';
            } elseif ($sCategory == 'VariantOnTranscript') {
                $sTable = 'TABLE_VARIANTS_ON_TRANSCRIPTS';
            } else {
                $sTable = 'TABLE_' . strtoupper($sCategory) . 'S';
            }

            $aInstallSQL['Activating LOVD standard custom columns'][] = 'ALTER TABLE ' . constant($sTable) . ' ADD COLUMN `' . $aCol[0] . '` ' . stripslashes($aCol[10]);
            $aInstallSQL['Activating LOVD standard custom columns'][] = 'INSERT INTO ' . TABLE_ACTIVE_COLS . ' VALUES("' . $aCol[0] . '", "00000", NOW())';
        }
    }


    // (10) Creating standard custom links.
    require 'inc-sql-links.php';
    $nLinks = count($aLinkSQL);
    $aInstallSQL['Creating LOVD custom links...'] = $aLinkSQL;
    $nInstallSQL += $nLinks;


    // (11) Creating LOVD status.
    $aInstallSQL['Registering LOVD system status...'] =
             array(
                    'INSERT INTO ' . TABLE_STATUS . ' VALUES (0, "' . $_SETT['system']['version'] . '", "' . $sSignature . '", NULL, NULL, NULL, NULL, NULL, NOW(), NULL)');
    $nInstallSQL ++;


    // (12) Creating standard external sources.
    require 'inc-sql-sources.php';
    $nSources = count($aSourceSQL);
    $aInstallSQL['Creating external sources...'] = $aSourceSQL;
    $nInstallSQL += $nSources;





    // Actually run the SQL...
    $nSQLDone = 0;
    $nSQLDonePercentage = 0;
    $nSQLDonePercentagePrev = 0;
    foreach ($aInstallSQL as $sMessage => $aSQL) {
        $_BAR->setMessage($sMessage);

        foreach ($aSQL as $sSQL) {
            $q = $_DB->query($sSQL, false, false, true); // This means that there is no SQL injection check here. But hey - these are our own queries.
            if (!$q) {
                // Error when running query. We will use the Div for the form now.
                $sMessage = 'Error during install while running query.<BR>I ran:<DIV class="err">' . str_replace(array("\r\n", "\r", "\n"), '<BR>', $sSQL) . '</DIV><BR>I got:<DIV class="err">' . str_replace(array("\r\n", "\r", "\n"), '<BR>', '[' . implode('] [', $_DB->errorInfo()) . ']') . '</DIV><BR>' .
                            'A failed installation is most likely caused by a bug in LOVD.<BR>' .
                            'Please <A href="' . $_SETT['upstream_BTS_URL_new_ticket'] . '" target="_blank">file a bug</A> and include the above messages to help us solve the problem.';
                $_BAR->setMessage($sMessage, 'done');
                $_BAR->setMessageVisibility('done', true);
                // LOVD 2.0's lovd_rollback() has been replaced by a two-line piece of code...
                $aTable = array_reverse($_TABLES);
                $_DB->query('DROP TABLE IF EXISTS ' . implode(', ', $aTable), false, false);
                print('</BODY>' . "\n" .
                      '</HTML>' . "\n");
                exit;
            }
            $nSQLDone ++;

            $nSQLDonePercentage = round(100*$nSQLDone / $nInstallSQL);
            if ($nSQLDonePercentage == 100 && $nSQLDone != $nInstallSQL) {
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
    }

    $_BAR->setProgress(100);
    $_BAR->setMessage('Installation of data tables complete!');
    $_BAR->setMessageVisibility('done', true);
    print('</BODY>' . "\n" .
          '</HTML>' . "\n");

    // Log user in.
    $_SESSION['auth'] = $_DB->query('SELECT * FROM ' . TABLE_USERS . ' WHERE username = ? AND password = ?', array($_POST['username'], $_POST['password']))->fetchAssoc();
    exit;
} elseif ($_GET['step'] == 2) { $_GET['step'] ++; }





if ($_GET['step'] == 3 && !($_DB->query('SHOW TABLES LIKE "' . TABLE_CONFIG . '"')->fetchColumn() && $_DB->query('SELECT COUNT(*) FROM ' . TABLE_CONFIG)->fetchColumn())) {
    // Step 3: Configuring general LOVD system settings.
    if (!$_DB->query('SHOW TABLES LIKE "' . TABLE_CONFIG . '"')->fetchColumn()) {
        // Didn't finish previous step correctly.
        header('Location: ' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . '?step=' . ($_GET['step'] - 1));
        exit;
    }

    $_T->printHeader();
    lovd_printSideBar();
    require ROOT_PATH . 'inc-lib-form.php';

    // Load System Settings class.
    require ROOT_PATH . 'class/object_system_settings.php';
    $_SYSSETTING = new LOVD_SystemSetting();

    print('      <B>Configuring LOVD system settings</B><BR>' . "\n" .
          '      <BR>' . "\n\n");

    if (isset($_GET['sent'])) {
        lovd_errorClean();

        $_SYSSETTING->checkFields($_POST);

        if (!lovd_error()) {
            // Store information and go to next page.
            // FIXME; use object::insertEntry()
            if (empty($_POST['proxy_port'])) {
                // Empty port number, insert NULL instead of 0.
                $_POST['proxy_port'] = NULL;
            }
            $q = $_DB->query('INSERT INTO ' . TABLE_CONFIG . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array($_POST['system_title'], $_POST['institute'], $_POST['location_url'], $_POST['email_address'], $_POST['send_admin_submissions'], $_POST['api_feed_history'], $_POST['refseq_build'], $_POST['proxy_host'], $_POST['proxy_port'], $_POST['logo_uri'], $_POST['mutalyzer_soap_url'], $_POST['send_stats'], $_POST['include_in_listing'], $_POST['lock_users'], $_POST['allow_unlock_accounts'], $_POST['allow_submitter_mods'], $_POST['allow_count_hidden_entries'], $_POST['use_ssl'], $_POST['use_versioning'], $_POST['lock_uninstall']), false, true);
            if (!$q) {
                // Error when running query.
                print('      Error during install while storing the settings.<BR>' . "\n" .
                      '      I got:<DIV class="err">' . str_replace(array("\r\n", "\r", "\n"), '<BR>', '[' . implode('] [', $_DB->errorInfo()) . ']') . '</DIV><BR><BR>' . "\n" .
                      '      A failed installation is most likely caused by a bug in LOVD.<BR>' . "\n" .
                      '      Please <A href="' . $_SETT['upstream_BTS_URL_new_ticket'] . 'bugs/" target="_blank">file a bug</A> and include the above messages to help us solve the problem.<BR>' . "\n\n");
                $_T->printFooter();
                exit;
            }

            // Already advance the install progress bar.
            print('      <SCRIPT type="text/javascript">' . "\n" .
                  '        var bar = document.getElementById(\'lovd_install_bar\');' . "\n" .
                  '        bar.style.width = \'' . $aInstallSteps[$_GET['step'] + 1][0] . '%\'; bar.title = \'' . $aInstallSteps[$_GET['step'] + 1][0] . '%\';' . "\n" .
                  '      </SCRIPT>' . "\n\n");

            print('      Settings stored. Ready to proceed to the next step.<BR>' . "\n" .
                  '      <BR>' . "\n\n");

            lovd_printInstallForm(false);

            $_T->printFooter();
            exit;
        }

    } else {
        // Default values.
        $_SYSSETTING->setDefaultValues();
    }

    if (!isset($_GET['sent'])) {
        print('      Please complete the form below and press \'Continue\' to continue the installation. We\'re almost done!<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');
    // Allow checking the database URL.
    lovd_includeJS('inc-js-submit-settings.php');

    // Table.
    print('      <FORM action="install/?step=' . $_GET['step'] . '&amp;sent=true" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_SYSSETTING->getForm(),
                 array(
                        'skip',
                        array('', '', 'submit', 'Continue &raquo;'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
} elseif ($_GET['step'] == 3) { $_GET['step'] ++; }





/*if ($_GET['step'] == 4 && !$_DB->query('SELECT COUNT(*) FROM ' . TABLE_MODULES, array(), false)->fetchColumn()) {
    // Step 4: Configuring LOVD modules.
    if (@count($_DB->query('SHOW TABLES LIKE ?', array(TABLE_MODULES))->fetchAllRow())) != 1) {
        // Didn't finish previous step correctly.
        header('Location: ' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . '?step=' . ($_GET['step'] - 2));
        exit;
    }
    if (!$_DB->query('SELECT COUNT(*) FROM ' . TABLE_CONFIG, array(), false)->fetchColumn()) {
        // Didn't finish previous step correctly.
        header('Location: ' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . '?step=' . ($_GET['step'] - 1));
        exit;
    }

    $_T->printHeader();
    lovd_printSideBar();

    print('      <B>Configuring LOVD modules</B><BR>' . "\n" .
          '      <BR>' . "\n\n" .
          '      Please wait while the installer is trying to detect the available LOVD modules...<BR>' . "\n" .
          '      <BR>' . "\n\n" .
          '      <PRE>' . "\n");

    // Read out modules directory...
    $hDir = @opendir(MODULE_PATH);
    if (!$hDir) {
        print('Failed to open modules directory.' . "\n" .
              '      </PRE>' . "\n");

        // FIXME; TEMPORARY CODE:
        // Yes, we know, the module directory currently does not exist!!!
        // Already advance the install progress bar.
        print('      <SCRIPT type="text/javascript">' . "\n" .
              '        var bar = document.getElementById(\'lovd_install_bar\');' . "\n" .
              '        bar.style.width = \'' . $aInstallSteps[$_GET['step'] + 1][0] . '%\'; bar.title = \'' . $aInstallSteps[$_GET['step'] + 1][0] . '%\';' . "\n" .
              '      </SCRIPT>' . "\n\n");
        lovd_printInstallForm(false);
        // FIXME; TEMPORARY CODE.

        $_T->printFooter();
        exit;
    }

    $aFailed = array();
    $aSuccess = array();
*//*
// DMD_SPECIFIC
    while (($sModuleID = readdir($hDir)) !== false) {
        if ($_MODULES->isInstalled($sModuleID) || substr($sModuleID, 0, 1) == '.' || !is_dir(MODULE_PATH . $sModuleID) || !is_readable(MODULE_PATH . $sModuleID . '/module.php')) {
            // Already installed module, ignored file, not a directory, or not a modules directory.
            continue;
        }

        // Try and load the module. THIS WILL EXECUTE THE MODULE CODE!!!
        $sFile = file_get_contents(MODULE_PATH . $sModuleID . '/module.php');

        // 'Cause you never know who tries to mess with us...
        $sModuleID = mysql_real_escape_string($sModuleID);

        $b = @eval($sFile);
        if ($b === false) {
            // Apparently, malformed module code (parse error or such).
            lovd_writeLog('Error', 'ModuleScan', 'Error while scanning for new modules: ' . $sModuleID . '/module.php returns error');
            $aFailed[] = $sModuleID;
            continue;
        } elseif (!class_exists($sModuleID)) {
            // Apparently, not defined.
            lovd_writeLog('Error', 'ModuleScan', 'Error while scanning for new modules: ' . $sModuleID . '/module.php does not define module class');
            $aFailed[] = $sModuleID;
            continue;
        } else {
            // Load module!
            $Tmp = new $sModuleID;

            if (!method_exists($Tmp, 'getInfo')) {
                // Apparently, not defined getInfo() method.
                lovd_writeLog('Error', 'ModuleScan', 'Error while scanning for new modules: ' . $sModuleID . '/module.php does not have getInfo() method');
                $aFailed[] = $sModuleID;
                continue;
            }
        }

        $aModule = $Tmp->getInfo();
        if (!is_array($aModule) || empty($aModule['name']) || empty($aModule['version']) || empty($aModule['description'])) {
            // Apparently, missing information from getInfo() method.
            lovd_writeLog('Error', 'ModuleScan', 'Error while scanning for new modules: ' . $sModuleID . '/module.php does not return expected array using getInfo()');
            $aFailed[] = $sModuleID;
            continue;
        }

        // 'Cause you never know who tries to mess with us...
        $aModule['settings'] = serialize($aModule['settings']);
        lovd_magicQuote($aModule);

        // All seems to be OK... install module, but do not activate right now.
        $sQ = 'INSERT INTO ' . TABLE_MODULES . ' VALUES ("' . $sModuleID . '", "' . $aModule['name'] . '", "' . $aModule['version'] . '", "' . $aModule['description'] . '", 0, "' . $aModule['settings'] . '", NOW(), NULL)';
        $q = $_DB->query($sQ);
        if (!$q) {
            lovd_writeLog('Error', 'ModuleScan', 'Error while scanning for new modules: ' . $sModuleID . '/module.php does not install properly:' . "\n" . 'Query : ' . $sQ . "\n" . 'Error : ' . $_DB->formatError());
            $aFailed[] = $sModuleID;
            continue;
        }

        lovd_writeLog('Install', 'Installation', 'New module installed: ' . $sModuleID . '/module.php returns "' . $aModule['name'] . '"');
        $aSuccess[] = $sModuleID;
    }
*//*

    // Print result of module scan to screen!
    $sFailed = '';
    $sSuccess = '';
    $nSuccess = count($aSuccess);

    if (count($aFailed)) {
        $sFailed = 'Failed installing "' . implode('"' . "\n" . 'Failed installing "', $aFailed) . '"' . "\n";
    }
    if ($nSuccess) {
        $sSuccess = 'Successfully installed "' . implode('"' . "\n" . 'Successfully installed "', $aSuccess) . '"' . "\n";
    }

    if ($sSuccess) {
        print('Successfully installed new module' . ($nSuccess == 1? '' : 's') . '!' . "\n" . $sSuccess .
              ($sFailed? "\n" . 'Not installed due to errors (more info in the logs):' . "\n" . $sFailed : ''));
    } elseif (!$sFailed) {
        print('No new modules found.' . "\n");
    } else {
        print('Failed installing new modules! More information can be found in the error logs.' . "\n" . $sFailed);
    }

    print('      </PRE>' . "\n\n" .
          '      Ready to proceed to the next step.<BR>' . "\n" .
          '      <BR>' . "\n\n");

    // Already advance the install progress bar.
    print('      <SCRIPT type="text/javascript">' . "\n" .
          '        var bar = document.getElementById(\'lovd_install_bar\');' . "\n" .
          '        bar.style.width = \'' . $aInstallSteps[$_GET['step'] + 1][0] . '%\'; bar.title = \'' . $aInstallSteps[$_GET['step'] + 1][0] . '%\';' . "\n" .
          '      </SCRIPT>' . "\n\n");

    lovd_printInstallForm(false);

    $_T->printFooter();
    exit;
} elseif ($_GET['step'] == 4) { $_GET['step'] ++; }*/





//if ($_GET['step'] == 5) {
if ($_GET['step'] == 4) {
    // Step 5: Done.
    if (!($_DB->query('SHOW TABLES LIKE "' . TABLE_CONFIG . '"')->fetchColumn() && $_DB->query('SELECT COUNT(*) FROM ' . TABLE_CONFIG)->fetchColumn())) {
        // Didn't finish previous step correctly.
        //header('Location: ' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . '?step=' . ($_GET['step'] - 2));
        header('Location: ' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . '?step=' . ($_GET['step'] - 1));
        exit;
    }

    $_T->printHeader();
    lovd_printSideBar();

    lovd_writeLog('Install', 'Installation', 'Installation of LOVD ' . $_STAT['version'] . ' complete');

    print('      <B>Done</B><BR>' . "\n" .
          '      <BR>' . "\n\n" .
          '      The installation of LOVD ' . $_STAT['version'] . ' is now complete.<BR>' . "\n" .
          '      <BR>' . "\n\n" .
          '      <BUTTON onclick="window.location.href=\'' . lovd_getInstallURL() . 'setup?newly_installed\';" style="font-weight : bold; font-size : 11px;">Continue to Setup area &gt;&gt;</BUTTON>' . "\n\n");

    $_T->printFooter();
    exit;
}
?>
