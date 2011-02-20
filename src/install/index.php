<?php
// DMD_SPECIFIC: changes to this file? TEST it!!!
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-19
 * Modified    : 2011-02-20
 * For LOVD    : 3.0-pre-17
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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
                array(60, 'Configuring LOVD system settings'),
                array(95, 'Configuring LOVD modules'),
                array(100, 'Done'),
              );

if (empty($_GET['step']) || !preg_match('/^[0-5]$/', $_GET['step'])) {
    $_GET['step'] = 0;
}



function lovd_printInstallForm ($bPassPost = true)
{
    print(lovd_getInstallForm($bPassPost));
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





if ($_GET['step'] == 0 && defined('_NOT_INSTALLED_')) {
    // Show some intro.
    require 'inc-top.php'; // Install dir's own top include.

    print('      <B>Welcome to the LOVD v.' . $_STAT['tree'] . '-' . $_STAT['build'] . ' installer</B><BR>
      <BR>' . "\n\n");

    // Requirements. This is where we would want to check software versions, PHP modules, etc.

    // Check for PHP, MySQL versions.
    $sPHPVers = str_replace('_', '-', PHP_VERSION) . '-';
    $sPHPVers = substr($sPHPVers, 0, strpos($sPHPVers, '-'));
    $bPHP = ($sPHPVers >= $aRequired['PHP']);
    $sPHP = '<IMG src="gfx/mark_' . (int) $bPHP . '.png" alt="" width="11" height="11">&nbsp;PHP : ' . $sPHPVers . ' (' . $aRequired['PHP'] . ' required)';
    $sMySQLVers = str_replace('_', '-', mysql_get_server_info()) . '-';
    $sMySQLVers = substr($sMySQLVers, 0, strpos($sMySQLVers, '-'));
    $bMySQL = ($sMySQLVers >= $aRequired['MySQL']);
    $sMySQL = '<IMG src="gfx/mark_' . (int) $bMySQL . '.png" alt="" width="11" height="11">&nbsp;MySQL : ' . $sMySQLVers . ' (' . $aRequired['MySQL'] . ' required)';
    // Check for InnoDB support.
    list(,$sInnoDB) = @mysql_fetch_row(lovd_queryDB('SHOW VARIABLES LIKE "have\_innodb"'));
    $bInnoDB = ($sInnoDB == 'YES');
    $sInnoDB = '&nbsp;&nbsp;<IMG src="gfx/mark_' . (int) $bInnoDB . '.png" alt="" width="11" height="11">&nbsp;MySQL InnoDB support ' . ($bInnoDB? 'en' : 'dis') . 'abled (required)';
    if (!$bPHP || !$bMySQL || !$bInnoDB) {
        // Failure!
        lovd_showInfoTable('One or more requirements are not met!<BR>I will now bluntly refuse to install.<BR><BR>' .
                           $sPHP . '<BR>' .
                           $sMySQL . '<BR>' .
                           $sInnoDB, 'stop');
        require 'inc-bot.php';
        exit;
    } else {
        // Success!
        lovd_showInfoTable('System check for requirements all OK!<BR><BR>' .
                           $sPHP . '<BR>' .
                           $sMySQL . '<BR>' .
                           $sInnoDB, 'success');
    }

    print('      The installation of LOVD consists of ' . (count($aInstallSteps) - 1) . ' simple steps.
      This installer will create the LOVD tables in the MySQL database, create the Administrator account and will help you configuring LOVD. Installation and initial configuration of LOVD should be simple for a relatively experienced computer user.<BR>
      <BR>
      The installation progress bar at the top of the screen shows how far you are in the installation process. The installation steps are shown at the left of the screen.<BR>
      <BR>' . "\n\n");

    lovd_printInstallForm();

    require 'inc-bot.php';
    exit;
} elseif ($_GET['step'] == 0) { $_GET['step'] ++; }





if ($_GET['step'] == 1 && defined('_NOT_INSTALLED_')) {
    // Step 1: Administrator account details.
    require 'inc-top.php';
    require ROOT_PATH . 'inc-lib-form.php';

    // Load User class.
    require ROOT_PATH . 'class/object_users.php';
    $_USER = new User();

    print('      <B>Administrator account details</B><BR>' . "\n" .
          '      <BR>' . "\n\n");

    if (isset($_GET['sent'])) {
        lovd_errorClean();

        $_USER->checkFields($_POST);

        if (!lovd_error()) {
            // Gather information and go to next page.

            // Prepare password...
            $_POST['password'] = md5($_POST['password_1']);
            unset($_POST['password_1'], $_POST['password_2']);

            print('      Account details OK. Ready to proceed to the next step.<BR>' . "\n" .
                  '      <BR>' . "\n\n");

            lovd_printInstallForm();

            require 'inc-bot.php';
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
    lovd_includeJS('../inc-js-tooltip.php');

    // Table.
    print('      <FORM action="install/?step=' . $_GET['step'] . '&amp;sent=true" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_USER->getForm(),
                 array(
                        'skip',
                        array('', '', 'submit', 'Continue'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    require 'inc-bot.php';
    exit;
} elseif ($_GET['step'] == 1) { $_GET['step'] ++; }





if ($_GET['step'] == 2 && defined('_NOT_INSTALLED_')) {
    // Step 2: Install database tables.
    if (!isset($_POST['username'])) {
        // Didn't finish previous step correctly.
        header('Location: ' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?step=' . ($_GET['step'] - 1));
        exit;
    }

    // Start session.
    $sSignature = md5($_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . time());
// DMD_SPECIFIC: the signature has been changed.
    $sSignature = 'ifokkema_local_3.0';
    // Set the session name to something unique, to prevent mixing cookies with other LOVDs on the same server.
    $_SETT['cookie_id'] = md5($sSignature);
    session_name('PHPSESSID_' . $_SETT['cookie_id']);

    session_start();

    require 'inc-top.php';

    print('      <B>Installing LOVD...</B><BR>' . "\n" .
          '      <BR>' . "\n\n");

    // List of tables that need to be generated.
    require 'inc-sql-tables.php';
    $nTables = count($_TABLES);

    // Do any of these tables exist yet?
    $aTablesMatched = array();
    $aTablesFound = array();
    $q = mysql_query('SHOW TABLES LIKE "' . TABLEPREFIX . '\_%"');
    while ($r = mysql_fetch_row($q)) {
        $aTablesMatched[] = $r[0];
        if (in_array($r[0], $_TABLES)) {
            $aTablesFound[] = $r[0];
        }
    }
    $nTablesMatched = count($aTablesMatched);
    $nTablesFound = count($aTablesFound);

    if ($nTablesFound) {
        if ($nTablesFound == $nTables) {
            // Maybe an existing LOVD install... Weird, because then we shouldn't have gotten here... Right?
            list($sVersion) = @mysql_fetch_row(lovd_queryDB('SELECT version FROM ' . TABLE_STATUS));
            if ($sVersion) {
                print('      There seems to be an existing LOVD installation (' . $sVersion . ').<BR>' . "\n" .
                      '      <B>Installation of LOVD can not continue using the current database or table prefix.</B><BR>' . "\n" .
                      '      Please change the database settings in the config.ini or remove the existing LOVD install, and re-run the installation.<BR>' . "\n\n");
                require 'inc-bot.php';
                exit;
            }
        }

        print('      There ' . ($nTablesFound == 1? 'is an existing table' : 'are ' . $nTablesFound . ' existing tables') . ' found!<BR>' . "\n" .
              '      <B>Installation of LOVD can not continue if tables exist with the same name I need to create.</B><BR>' . "\n" .
              '      I found:<BR>' . "\n" .
              '      - ' . implode("<BR>\n" . '      - ', $aTablesFound) . "<BR>\n" .
              '      Please remove th' . ($nTablesFound == 1? 'is table' : 'ese tables') . ' and re-run the installation.<BR>' . "\n\n");
        require 'inc-bot.php';
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

    define('_INC_BOT_CLOSE_HTML_', false); // Sounds kind of stupid, but this prevents the inc-bot to actually close the <BODY> and <HTML> tags.
    require 'inc-bot.php';

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


    // (3) Creating administrator.
    $aInstallSQL['Creating LOVD database administrator account...'] =
             array(
                    'INSERT INTO ' . TABLE_USERS . ' VALUES (NULL, "' . mysql_real_escape_string($_POST['name']) . '", "' . mysql_real_escape_string($_POST['institute']) . '", "' . mysql_real_escape_string($_POST['department']) . '", "' . mysql_real_escape_string($_POST['telephone']) . '", "' . mysql_real_escape_string($_POST['address']) . '", "' . mysql_real_escape_string($_POST['city']) . '", "' . mysql_real_escape_string($_POST['countryid']) . '", "' . mysql_real_escape_string($_POST['email']) . '", "' . mysql_real_escape_string($_POST['reference']) . '", "' . mysql_real_escape_string($_POST['username']) . '", "' . mysql_real_escape_string($_POST['password']) . '", "", 0, "' . session_id() . '", "", "", ' . LEVEL_ADMIN . ', "' . mysql_real_escape_string($_POST['allowed_ip']) . '", 0, NOW(), 1, NOW(), NULL, NULL)',
                  );
    $nInstallSQL ++;


    // (4) Registering LOVD variant statuses.
    $nStatuses = count($_SETT['var_status']);
    foreach ($_SETT['var_status'] as $nStatus => $sStatus) {
        $aInstallSQL['Registering LOVD variant statuses...'][] = 'INSERT INTO ' . TABLE_DATA_STATUS . ' VALUES (' . $nStatus . ', "' . $sStatus . '")';
    }
    $nInstallSQL += $nStatuses;


    // (5) Registering LOVD variant pathogenicities.
    $nPathogenicities = count($_SETT['var_pathogenic_short']);
    foreach ($_SETT['var_pathogenic_short'] as $nPath => $sPath) {
        $aInstallSQL['Registering LOVD variant pathogenicities...'][] = 'INSERT INTO ' . TABLE_PATHOGENIC . ' VALUES (' . $nPath . ', "' . $sPath . '")';
    }
    $nInstallSQL += $nPathogenicities;


    // (6) Creating standard LOVD custom columns.
    require 'inc-sql-columns.php';
    $nCols = count($aColSQL);
    $aInstallSQL['Creating LOVD custom columns...'] = $aColSQL;
    $nInstallSQL += $nCols;


/*
    // (7) Adding standard patient columns.
    // Gather info on standard custom patient columns.
    $aColsToCopy = array('colid', 'col_order', 'width', 'mandatory', 'description_form', 'description_legend_short', 'description_legend_full', 'select_options', 'public', 'public_form', 'created_by', 'created_date');
    $qCols = mysql_query('SELECT * FROM ' . TABLE_COLS . ' WHERE (hgvs = 1 OR standard = 1) AND colid LIKE "Patient/%"');
    $nCols = mysql_num_rows($qCols);

    // Create the columns...
    print('Registering LOVD standard patient columns [');
    require ROOT_PATH . 'class/currdb.php';
    $_CURRDB = new CurrDB(false);

    $aColsOrder = array_keys($aColSQL);
    
    while ($z = mysql_fetch_assoc($qCols)) {
        $z['col_order'] = array_search($z['colid'], $aColsOrder);

        // Calculate the standard width of the column based on the maximum number of characters.
        $nHeadLength = strlen($z['head_column']);
        $nColLength = $_CURRDB->getFieldLength($z['colid']) / 2;
        $nColLength = ($nColLength < $nHeadLength? $nHeadLength : $nColLength);
        // Compensate for small/large fields.
        $nColLength = ($nColLength < 5? 5 : ($nColLength > 35? 35 : $nColLength));
        if ($nColLength < 10) {
            $z['width'] = 10*$nColLength;
        } else {
            $z['width'] = 8*$nColLength;
        }
        $z['width'] = ($z['width'] > 200? 200 : $z['width']);

        // Created_* columns...
        $z['created_by'] = 0; // 'LOVD'
        $z['created_date'] = date('Y-m-d H:i:s');

        $sQ = 'INSERT INTO ' . TABLE_PATIENTS_COLS . ' (';
        $aCol = array();
        foreach ($aColsToCopy as $sCol) {
            if (isset($z[$sCol])) {
                $sQ .= (substr($sQ, -1) == '('? '' : ', ') . $sCol;
                $aCol[] = $z[$sCol];
            }
        }
        $sQ .= ') VALUES (';

        foreach ($aCol as $key => $val) {
            $sQ .= ($key? ', ' : '') . '"' . $val . '"';
        }
        $sQ .= ')';

        // Insert default LOVD custom column.
        $q = @mysql_query($sQ);

        // Alter patient table to include column.
        // 2009-02-16; 2.0-16; Added stripslashes to allow receiving quotes. This variable has been checked using regexps, so can be considered safe.
        $sQ = 'ALTER TABLE ' . TABLE_PATIENTS . ' ADD COLUMN `' . $z['colid'] . '` ' . stripslashes($z['mysql_type']) . ' NOT NULL AFTER patientid';
        $q = @mysql_query($sQ);
    }

    // (8) Adding standard phenotype columns.
    // (9) Adding standard screening columns.

                'TABLE_PATIENT_COLS' => TABLEPREFIX . '_patient_columns',
                'TABLE_PHENOTYPE_COLS' => TABLEPREFIX . '_phenotype_columns',
                'TABLE_SCREENING_COLS' => TABLEPREFIX . '_screening_columns',
*/


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
            $q = mysql_query($sSQL); // This means that there is no SQL injection check here. But hey - these are our own queries. DON'T USE lovd_queryDB(). It complains because there are ?s in the queries.
            if (!$q) {
                // Error when running query. We will use the Div for the form now.
                $sMessage = 'Error during install while running query.<BR>I ran:<DIV class="err">' . str_replace(array("\r\n", "\r", "\n"), '<BR>', $sSQL) . '</DIV><BR>I got:<DIV class="err">' . str_replace(array("\r\n", "\r", "\n"), '<BR>', mysql_error()) . '</DIV><BR>' .
                            'A failed installation is most likely caused by a bug in LOVD.<BR>' .
                            'Please <A href="' . $_SETT['upstream_URL'] . 'bugs/" target="_blank">file a bug</A> and include the above messages to help us solve the problem.';
                $_BAR->setMessage($sMessage, 'done');
                $_BAR->setMessageVisibility('done', true);
                // LOVD 2.0's lovd_rollback() has been replaced by a two-line piece of code...
                $aTable = array_reverse($_TABLES);
                lovd_queryDB('DROP TABLE IF EXISTS ' . implode(', ', $aTable));
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
    $_SESSION['auth'] = mysql_fetch_assoc(lovd_queryDB('SELECT * FROM ' . TABLE_USERS . ' WHERE username = ? AND password = ?', array($_POST['username'], $_POST['password'])));
    exit;
} elseif ($_GET['step'] == 2) { $_GET['step'] ++; }





if ($_GET['step'] == 3 && !@mysql_num_rows(mysql_query('SELECT * FROM ' . TABLE_CONFIG))) {
    // Step 3: Configuring general LOVD system settings.
    if (@mysql_num_rows(lovd_queryDB('SHOW TABLES LIKE ?', array(TABLE_CONFIG))) != 1) {
        // Didn't finish previous step correctly.
        header('Location: ' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?step=' . ($_GET['step'] - 1));
        exit;
    }

    require 'inc-top.php';
    require ROOT_PATH . 'inc-lib-form.php';

    // Load System Settings class.
    require ROOT_PATH . 'class/object_system_settings.php';
    $_SYSSETTING = new SystemSetting();

    print('      <B>Configuring LOVD system settings</B><BR>' . "\n" .
          '      <BR>' . "\n\n");

    if (isset($_GET['sent'])) {
        lovd_errorClean();

        $_SYSSETTING->checkFields($_POST);

        if (!lovd_error()) {
            // Store information and go to next page.
            $q = lovd_queryDB('INSERT INTO ' . TABLE_CONFIG . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array($_POST['system_title'], $_POST['institute'], $_POST['location_url'], $_POST['email_address'], $_POST['send_admin_submissions'], $_POST['api_feed_history'], $_POST['refseq_build'], $_POST['logo_uri'], $_POST['send_stats'], $_POST['include_in_listing'], $_POST['lock_users'], $_POST['allow_unlock_accounts'], $_POST['allow_submitter_mods'], $_POST['allow_count_hidden_entries'], $_POST['use_ssl'], $_POST['use_versioning'], $_POST['lock_uninstall']));
            if (!$q) {
                // Error when running query.
                print('      Error during install while storing the settings.<BR>' . "\n" .
                      '      I got:<DIV class="err">' . str_replace(array("\r\n", "\r", "\n"), '<BR>', mysql_error()) . '</DIV><BR><BR>' . "\n" .
                      '      A failed installation is most likely caused by a bug in LOVD.<BR>' . "\n" .
                      '      Please <A href="' . $_SETT['upstream_URL'] . 'bugs/" target="_blank">file a bug</A> and include the above messages to help us solve the problem.<BR>' . "\n\n");
                require 'inc-bot.php';
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

            require 'inc-bot.php';
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
    lovd_includeJS('../inc-js-tooltip.php');
    // Allow checking the database URL.
    lovd_includeJS('inc-js-submit-settings.php');

    // Table.
    print('      <FORM action="install/?step=' . $_GET['step'] . '&amp;sent=true" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_SYSSETTING->getForm(),
                 array(
                        'skip',
                        array('', '', 'submit', 'Continue'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    require 'inc-bot.php';
    exit;
} elseif ($_GET['step'] == 3) { $_GET['step'] ++; }





if ($_GET['step'] == 4 && !@mysql_num_rows(mysql_query('SELECT * FROM ' . TABLE_MODULES))) {
    // Step 4: Configuring LOVD modules.
    if (@mysql_num_rows(mysql_query('SHOW TABLES LIKE "' . TABLE_MODULES . '"')) != 1) {
        // Didn't finish previous step correctly.
        header('Location: ' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?step=' . ($_GET['step'] - 2));
        exit;
    }
    if (!@mysql_fetch_row(mysql_query('SELECT COUNT(*) FROM ' . TABLE_CONFIG))) {
        // Didn't finish previous step correctly.
        header('Location: ' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?step=' . ($_GET['step'] - 1));
        exit;
    }

    require 'inc-top.php';

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

        require 'inc-bot.php';
        exit;
    }

    $aFailed = array();
    $aSuccess = array();
/*
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
        $q = mysql_query($sQ);
        if (!$q) {
            lovd_writeLog('Error', 'ModuleScan', 'Error while scanning for new modules: ' . $sModuleID . '/module.php does not install properly:' . "\n" . 'Query : ' . $sQ . "\n" . 'Error : ' . mysql_error());
            $aFailed[] = $sModuleID;
            continue;
        }

        lovd_writeLog('Install', 'Installation', 'New module installed: ' . $sModuleID . '/module.php returns "' . $aModule['name'] . '"');
        $aSuccess[] = $sModuleID;
    }
*/

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

    require 'inc-bot.php';
    exit;
} elseif ($_GET['step'] == 4) { $_GET['step'] ++; }





if ($_GET['step'] == 5) {
    // Step 5: Done.
    if (!@mysql_fetch_row(mysql_query('SELECT COUNT(*) FROM ' . TABLE_CONFIG))) {
        // Didn't finish previous step correctly.
        header('Location: ' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?step=' . ($_GET['step'] - 2));
        exit;
    }

    require 'inc-top.php';

    lovd_writeLog('Install', 'Installation', 'Installation of LOVD ' . $_STAT['version'] . ' complete');

    print('      <B>Done</B><BR>' . "\n" .
          '      <BR>' . "\n\n" .
          '      The installation of LOVD ' . $_STAT['version'] . ' is now complete.<BR>' . "\n" .
          '      <BR>' . "\n\n" .
          '      <BUTTON onclick="window.location.href=\'setup?newly_installed\';" style="font-weight : bold; font-size : 11px;">Continue to Setup area &gt;&gt;</BUTTON>' . "\n\n");

    require 'inc-bot.php';
    exit;
}
?>
