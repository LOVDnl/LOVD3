<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-19
 * Modified    : 2010-12-17
 * For LOVD    : 3.0-pre-10
 *
 * Copyright   : 2004-2010 Leiden University Medical Center; http://www.LUMC.nl/
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

/*
// DMD_SPECIFIC
if (!function_exists('array_combine')) {
    // array_combine is available from PHP 5.0.0. Redefine if necessary.
    function array_combine ($a1, $a2)
    {
        // Creates an array by using one array for keys and another for its values.
        if (!is_array($a1) || !is_array($a2) || !count($a1) || !count($a2) || count($a1) != count($a2)) {
            return false;
        }
        
        $aReturn = array();
        foreach ($a1 as $key => $val) {
            $aReturn[$val] = $a2[$key];
        }
        
        return $aReturn;
    }
}
*/





/*
// DMD_SPECIFIC
function lovd_buildLinks (& $zData)
{
    // Builds the custom links in the $zData array.
    static $aLinks = array();

    if (!count($aLinks)) {
        // Retrieve Custom Link information from the database.
        // Backwards compatible with MySQL 4.0 and earlier; GROUP_CONCAT is available since MySQL 4.1.
        $qLinks = mysql_query('SELECT l.pattern_text, l.replace_text, c2l.colid FROM ' . TABLE_LINKS . ' AS l LEFT JOIN ' . TABLE_COLS2LINKS . ' AS c2l USING (linkid) WHERE l.active = 1 AND c2l.colid IS NOT NULL');

        // Loop through and build array.
        while ($r = mysql_fetch_row($qLinks)) {
            list($sPattern, $sReplace, $sColID) = $r;

            if (!isset($aLinks[$sColID])) {
                $aLinks[$sColID] = array();
            }

            // LOVD v.1.1.0 code:
            // $a_link[$val][]['patt'] = "/" . preg_replace("/\[[0-9]+\]/", "([A-Za-zÀ-ÖØ-öø-ÿ0-9 :;,._-]*)", $z_link['patt']) . "/";
            // $a_link[$val][count($a_link[$val])-1]['repl'] = ereg_replace("\[([0-9]+)\]","\\\\1",$z_link['repl']);
            // Replace [1] and others to the patterns to match these references.
            // 2007-04-04; 2.0-alpha-10
            // Added 'U' modifier to the pattern to prevent multiple patterns to collide.
            $sPattern = '/' . preg_replace('/\\\[[0-9]+\\\]/', '(.*)', preg_quote($sPattern, '/')) . '/U';
            $sReplace = preg_replace('/\[([0-9]+)\]/', "\$$1", $sReplace);

            $aLinks[$sColID][$sPattern] = $sReplace;
        }
    }



    // Actually replace the Custom Link patterns.
    $aCols = array_keys($zData);
    foreach ($aCols as $sCol) {
        if (isset($aLinks[$sCol])) {
            foreach ($aLinks[$sCol] as $sPattern => $sReplace) {
                $zData[$sCol] = preg_replace($sPattern, $sReplace, $zData[$sCol]);
            }
        }
    }
}
*/





function lovd_calculateVersion ($sVersion)
{
    // Builds version formatted for string-comparing LOVD versions to determine
    // correct version order.

    // Slightly different preg_match pattern.
    if (preg_match('/^([1-9]\.[0-9](\.[0-9])?)(\-([0-9a-z-]{2,11}))?$/', $sVersion, $aVersion)) {
        $sReturn = str_pad(str_replace('.', '', $aVersion[1]), 4, '0');
        if (isset($aVersion[3])) {
            if (preg_match('/^(pre|dev)(\-([0-9]{2})([a-z])?)?$/', $aVersion[4], $aSub)) {
                $sReturn -= 3;
                if (isset($aSub[2])) {
                    // 2.0-dev-01 => 1997-010 (2.0-dev-01a => 1997-01a)
                    return $sReturn . '-' . $aSub[3] . (isset($aSub[4])? $aSub[4] : '0');
                } else {
                    // 2.0-dev => 1997-000
                    return $sReturn . '-000';
                }

            } elseif (preg_match('/^alpha(\-([0-9]{2})([a-z])?)?$/', $aVersion[4], $aSub)) {
                $sReturn -= 2;
                if (isset($aSub[1])) {
                    // 2.0-alpha-01 => 1998-010 (2.0-alpha-01a => 1998-01a)
                    return $sReturn . '-' . $aSub[2] . (isset($aSub[3])? $aSub[3] : '0');
                } else {
                    // 2.0-alpha => 1998-000
                    return $sReturn . '-000';
                }

            } elseif (preg_match('/^beta(\-([0-9]{2})([a-z])?)?$/', $aVersion[4], $aSub)) {
                $sReturn -= 1;
                if (isset($aSub[1])) {
                    // 2.0-beta-01 => 1999-010 (2.0-beta-01a => 1999-01a)
                    return $sReturn . '-' . $aSub[2] . (isset($aSub[3])? $aSub[3] : '0');
                } else {
                    // 2.0-beta => 1999-000
                    return $sReturn . '-000';
                }

            } elseif (preg_match('/^([0-9]{2})([a-z])?$/', $aVersion[4], $aSub)) {
                if (isset($aSub[2])) {
                    // 2.0-01a => 2000-01a
                    return $sReturn . '-' . $aSub[1] . $aSub[2];
                } else {
                    // 2.0-01 => 2000-010
                    return $sReturn . '-' . $aSub[1] . '0';
                }
            }
        }

        // 2.0 => 2000-000
        return $sReturn . '-000';

    } else {
        return false;
    }
}





function lovd_cleanDirName ($s)
{
    // Cleans a given path by resolving a relative path.
    if (!is_string($s)) {
        // No input.
        return false;
    }

    // Clean up the pwd; remove '//'
    $s = preg_replace('/\/+/', '/', $s);
    // Clean up the pwd; remove '/./'
    $s = preg_replace('/\/\.\//', '/', $s);
    // Clean up the pwd; remove '/dir/../'
    $s = preg_replace('/\/[^\/]+\/\.\.\//', '/', $s);

    if (preg_match('/\/(\.)?\.\//', $s)) {
        // Still not clean... Pff...
        $s = lovd_cleanDirName($s);
    }

    return $s;
}





function lovd_displayError ($sError, $sMessage, $sLogFile = 'Error')
{
    // Function kindly provided by Ileos.nl in the interest of Open Source.
    // Writes an error message to the errorlog and displays the same message on
    // screen for the user. This function halts PHP processing in all cases.
    global $_AUTH, $_SETT, $_CONF, $_STAT;

    // Check if, and which, top include has been used.
    if (!defined('_INC_TOP_INCLUDED_') && !defined('_INC_TOP_CLEAN_INCLUDED_')) {
        if ($sError == 'Init') {
            // We can't load the normal inc-top.php and inc-bot.php now, because that will result in more errors on the screen.
            $sFile = ROOT_PATH . 'install/inc-top.php';
        } elseif (is_readable('inc-top.php')) {
            $sFile = 'inc-top.php';
        } else {
            $sFile = ROOT_PATH . 'inc-top.php';
        }
        require $sFile;
        if ($sFile == ROOT_PATH . 'install/inc-top.php') {
            print('<BR>' . "\n");
        }

        if (defined('PAGE_TITLE')) {
            lovd_printHeader(PAGE_TITLE);
        }
    }

    // Write to log file.
    $bLog = @lovd_writeLog($sLogFile, $sError, $sMessage);

    if (defined('_INC_BOT_CLOSE_HTML_') && _INC_BOT_CLOSE_HTML_ === false) {
        print('<BR>' . "\n\n");
    }

    // Display error.
    print("\n" . '
      <TABLE border="0" cellpadding="0" cellspacing="0" align="center" width="900" class="error">
        <TR>
          <TH>Error: ' . $sError . ($bLog? ' (Logged)' : '') . '</TH></TR>
        <TR>
          <TD>' . str_replace("\n", '<BR>', $sMessage) . '</TD></TR></TABLE>' . "\n\n");

    // If fatal, get bottom and exit.
    if (defined('_INC_BOT_CLOSE_HTML_') && _INC_BOT_CLOSE_HTML_ === false) {
        die('</BODY>' . "\n" . '</HTML>' . "\n\n");
    } elseif (defined('_INC_TOP_INCLUDED_') && _INC_TOP_INCLUDED_ === true) {
        if ($sError == 'Init') {
            // We can't load the normal inc-top.php and inc-bot.php now, because that will result in more errors on the screen.
            require ROOT_PATH . 'install/inc-bot.php';
        } else {
            require ROOT_PATH . 'inc-bot.php';
        }
    } elseif (defined('_INC_TOP_CLEAN_INCLUDED_')) {
        require ROOT_PATH . 'inc-bot-clean.php';
    }
    exit;
}





function lovd_getExternalSource ($sSource, $nID = false, $bHTML = false)
{
    // Retrieves URL for external source and returns it, including the ID.
    static $aSources = array();
    if (!count($aSources)) {
        $q = lovd_queryDB('SELECT * FROM ' . TABLE_SOURCES);
        while ($z = mysql_fetch_assoc($q)) {
            $aSources[$z['source']] = $z['url'];
        }
    }
    
    if (array_key_exists($sSource, $aSources)) {
        $s = $aSources[$sSource];
        if ($bHTML) {
            $s = str_replace('&', '&amp;', $s);
        }
        if ($nID !== false) {
            // ID provided; include it in the URL.
            $s = str_replace('{{ ID }}', $nID, $s);
        }
        return $s;
    }

    return false;
}





function lovd_getGeneList ()
{
    // Gets the list of genes (ids and symbols only), to prevent repeated queries.
    static $aGenes = array();
    if (!count($aGenes)) {
        $q = lovd_queryDB('SELECT id, symbol FROM ' . TABLE_GENES . ' ORDER BY symbol');
        while ($r = mysql_fetch_row($q)) {
            $aGenes[$r[0]] = $r[1];
        }
    }

    return $aGenes;
}





function lovd_getInstallURL ($bFull = true)
{
    // Returns URL that can be used in URLs or redirects.
    return (!$bFull? '' : PROTOCOL . $_SERVER['HTTP_HOST']) . lovd_cleanDirName(dirname($_SERVER['SCRIPT_NAME']) . '/' . ROOT_PATH);
}





function lovd_getProjectFile ()
{
    // Gets project file name (file name including possible project subdirectory).
    $sDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/'; // /LOVDv.3.0/install/ or /
    $sProjectDir = lovd_cleanDirName($sDir . ROOT_PATH);        // /LOVDv.3.0/         or /
    $sDir = substr($sDir, strlen($sProjectDir) - 1);            // /install/           or /
    // You need to use SCRIPT_FILENAME here, because SCRIPT_NAME can lose the .php extension.
    return $sDir . basename($_SERVER['SCRIPT_FILENAME']);       // /install/index.php  or /variants.php
}





function lovd_includeJS ($sFile, $nPrefix = 3)
{
    // Searches for and includes a .js include file.
    
    if (substr($sFile, 0, 4) != 'http' && (!file_exists($sFile) || !is_readable($sFile))) {
        return false;
    }
    
    $sPrefix = str_repeat('  ', $nPrefix);
    // This basename() implementation is necessary because in HTML the BASE header indicate the relative href, but PHP does not know this.
    // Therefore, simply sending {ROOT_PATH . $sFile} will not work with ROOT_PATH other than ./ because the browser won't find the file. 
    print($sPrefix . '<SCRIPT type="text/javascript" src="' . (substr($sFile, 0, 4) == 'http'? $sFile : basename($sFile)) . '"></SCRIPT>' . "\n");
    return true;
}





/*
DMD_SPECIFIC
function lovd_isCurator ($sGene = '')
{
    // Returns true if current user is allowed to act as a curator for the given
    // gene. Returns true by default for any user higher than LEVEL_CURATOR.
    global $_AUTH;

    if (!HAS_AUTH) {
        return false;
    }

    if (!$sGene || !is_string($sGene)) {
        return ($_AUTH['level'] >= LEVEL_MANAGER);
    }

    if ($_AUTH['level'] >= LEVEL_MANAGER || in_array($sGene, $_AUTH['curates'])) {
        return true;
    } else {
        return false;
    }
}
*/





function lovd_magicUnquote (&$var)
{
    // Counterpart of the magicQuote() function. Basically for printing correct
    // values on screen or in email notifications.

    if (is_array($var)) {
        foreach ($var as $key => $val) {
            if (is_array($val)) {
                lovd_magicUnquote($var[$key]);
            } else {
                $var[$key] = stripslashes($val);
            }
        }
    } else {
        $var = stripslashes($var);
    }
}





function lovd_magicUnquoteAll ()
{
    // Calls lovd_magicUnquote() on all needed variables.

    lovd_magicUnquote($_GET);
    lovd_magicUnquote($_POST);
    lovd_magicUnquote($_COOKIE);
}





function lovd_php_file ($sURL, $bHeaders = false, $sPOST = false) {
    // LOVD's alternative to file(), in case the fopenwrappers are off...
    // 2010-06-24; 2.0-27; Adapted to allow POST submission as well.

    if (substr($sURL, 0, 4) != 'http' || (ini_get('allow_url_fopen') && !$sPOST)) {
        // Normal file() is fine.
        return @file($sURL);
    }

    $aHeaders = array();
    $aOutput = array();
    $aURL = parse_url($sURL);
    if ($aURL['host']) {
        $f = @fsockopen($aURL['host'], 80); // Doesn't support SSL right now.
        if ($f === false) {
            // No use continuing - it will only cause errors.
            return false;
        }
        $sRequest = ($sPOST? 'POST ' : 'GET ') . $aURL['path'] . '?' . $aURL['query'] . ' HTTP/1.0' . "\r\n" .
                    'Host: ' . $aURL['host'] . "\r\n" .
                    (!$sPOST? '' :
                    'Content-length: ' . strlen($sPOST) . "\r\n" .
                    'Content-Type: application/x-www-form-urlencoded' . "\r\n") .
                    'Connection: Close' . "\r\n\r\n" .
                    (!$sPOST? '' :
                    $sPOST . "\r\n");
        fputs($f, $sRequest);
        $bListen = false; // We want to start capturing the output AFTER the headers have ended.
        while (!feof($f)) {
            // FIXME; actually we should check here, if $s ends in "\r\n"; if not, we're simply not done yet with a looong response line and we're breaking it off.
            $s = rtrim(fgets($f, 20480), "\r\n");
            if ($bListen) {
                $aOutput[] = $s;
            } else {
                if (!$s) {
                    $bListen = true;
                } else {
                    $aHeaders[] = $s;
                }
            }
        }
        fclose($f);
    }
    if (!$bHeaders) {
        return($aOutput);
    } else {
        return(array($aHeaders, $aOutput));
    }
}





/*
DMD_SPECIFIC
function lovd_printGeneFooter ()
{
    // Prints the current gene's footer, if any is stored.
    global $_SETT;
    if (!empty($_SESSION['currdb']) && !empty($_SETT['currdb']['footer'])) {
        print('      <DIV style="text-align : ' . $_SETT['notes_align'][$_SETT['currdb']['footer_align']] . ';">' . $_SETT['currdb']['footer'] . '</DIV>' . "\n\n");
    }
}





function lovd_printGeneHeader ()
{
    // Prints the current gene's header, if any is stored.
    global $_SETT;
    if (!empty($_SESSION['currdb']) && !empty($_SETT['currdb']['header'])) {
        print('      <DIV style="text-align : ' . $_SETT['notes_align'][$_SETT['currdb']['header_align']] . ';">' . $_SETT['currdb']['header'] . '</DIV>' . "\n\n");
    }
}
*/





function lovd_printHeader ($sTitle)
{
    // Prints the page's header.
    print('      <H2 class="LOVD">' . $sTitle . '</H2>' . "\n\n");
}





function lovd_queryDB ($sQuery, $aArgs = array(), $bDebug = false)
{
    // Queries the database and protects against SQL injection with
    // mysql_real_escape_string. No code in LOVD should query the database
    // directly!

    if (!is_array($aArgs)) {
        lovd_displayError('LOVD-Lib', 'lovd_queryDB() - Received a wrong type of $aArgs argument from ' . $_SERVER['REQUEST_URI'] . "\n" . 'Query: ' . $sQuery);
    }

    // Explode so we can glue the pieces back together. A simple replace will mess up with more than one argument and one of the replaced values itself contains questionmarks.
    $aQuery = explode('?', $sQuery);
    $aArgs = array_values($aArgs); // Make sure there are continuous numeric keys only.
    $sQuery = $aQuery[0]; // So queries without arguments work, too :)

    // A mismatch between the number of ? in the query and the number of items
    // in $aArgs indicates a bug in LOVD.
    $nSlots = count($aQuery);
    $nArgs = count($aArgs);
    if (($nSlots - 1) != $nArgs) {
        lovd_displayError('LOVD-Lib', 'lovd_queryDB() - ' . $nArgs . ' argument' . ($nArgs == 1? ' does' : 's do') . ' not fit in ' . $nSlots . ' slot' . ($nSlots == 1? '' : 's') . ' from ' . $_SERVER['REQUEST_URI'] . "\n" . 'Query: ' . $sQuery);
    }

    // If they're arguments, go through them and put them into the query.
    foreach ($aArgs as $nKey => $sArg) {
        if ($sArg === NULL) {
            $sQuery .= 'NULL';
        } else {
            $sQuery .= (is_numeric($sArg)? $sArg : '\'' . mysql_real_escape_string($sArg) . '\'');
        }
        $sQuery .= $aQuery[$nKey + 1];
    }
    if ($bDebug) {
        echo htmlspecialchars($sQuery);
    }
    return mysql_query($sQuery);
}





function lovd_queryError ($sErrorCode, $sSQL, $sSQLError, $bHalt = true)
{
    // Function kindly provided by Ileos.nl in the interest of Open Source.
    // Formats query errors for the error log, and optionally halts the system.
    // Used to be called lovd_dbFout() in LOVD 2.0.
    global $_AUTH;

    // Format the error message.
    $sError = preg_replace('/^' . preg_quote(rtrim(lovd_getInstallURL(false), '/'), '/') . '/', '', $_SERVER['REQUEST_URI']) . ' returned error in code block ' . $sErrorCode . '.' . "\n" .
              'Query : ' . $sSQL . "\n" .
              'Error : ' . $sSQLError;

    // If the system needs to be halted, send it through to lovd_displayError() who will print it on the screen,
    // write it to the system log, and halt the system. Otherwise, just log it to the database.
    if ($bHalt) {
        lovd_displayError('Query', $sError);
    } else {
        lovd_writeLog('Error', 'Query', $sError);
    }
}





function lovd_requireAUTH ($nLevel = 0)
{
    // Creates friendly output message if $_AUTH does not exist (or level too
    // low), and exits.
    // $_AUTH is for authorization; $_SETT is needed for the user levels;
    // $_CONF and $_STAT are for the top and bottom includes.
    global $_AUTH, $_SETT, $_CONF, $_STAT;

    $aKeys = array_keys($_SETT['user_levels']);
    if ($nLevel !== 0 && !in_array($nLevel, $aKeys)) {
        $nLevel = max($aKeys);
    }

    // $nLevel is now 0 (just existence of $_AUTH required) or taken from the levels list.
    if ((!$nLevel && !$_AUTH) || ($nLevel && (!$_AUTH || $_AUTH['level'] < $nLevel))) {
        if (!defined('_INC_TOP_INCLUDED_')) {
            if (is_readable('inc-top.php')) {
                require 'inc-top.php';
            } else {
                require ROOT_PATH . 'inc-top.php';
            }
            if (defined('PAGE_TITLE')) {
                lovd_printHeader(PAGE_TITLE);
            }
        }

        $sMessage = 'To access this area, you need ' . (!$nLevel? 'to <A href="login">log in</A>.' : ($nLevel == max($aKeys)? '' : 'at least ') . $_SETT['user_levels'][$nLevel] . ' clearance.');
        if (lovd_getProjectFile() == '/submit.php') {
            $sMessage .= '<BR>If you are not registered as a submitter, please <A href="users?register">do so here</A>.';
        }
        lovd_showInfoTable($sMessage, 'stop');

        if (defined('_INC_TOP_INCLUDED_')) {
            if (is_readable('inc-bot.php')) {
                require 'inc-bot.php';
            } else {
                require ROOT_PATH . 'inc-bot.php';
            }
        } elseif (defined('_INC_TOP_CLEAN_INCLUDED_')) {
            if (is_readable('inc-bot-clean.php')) {
                require 'inc-bot-clean.php';
            } else {
                require ROOT_PATH . 'inc-bot-clean.php';
            }
        }
        exit;
    }
}





function lovd_shortenString ($s, $l)
{
    // Function kindly provided by Ileos.nl in the interest of Open Source.
    // Shortens string nicely to a given length.
    if (strlen($s) > $l) {
        $s = substr($s, 0, $l - 3) . '...';
    }
    return $s;
}





function lovd_showInfoTable ($sMessage, $sType = 'information', $sWidth = '100%', $sHref = '')
{
    $aTypes =
             array(
                    'information' => 'Information',
                    'question' => 'Question',
                    'save' => 'Save',
                    'stop' => 'Stop!',
                    'success' => 'Success!',
                    'warning' => 'Warning',
                  );

    if (!array_key_exists($sType, $aTypes)) {
        $sType = 'information';
    }

    if (!preg_match('/^\d+%?$/', $sWidth)) {
        $sWidth = '100%';
    }

    print('      <TABLE border="0" cellpadding="2" cellspacing="0" width="' . $sWidth . '" class="info"' . (!empty($sHref)? ' style="cursor : pointer;" onclick="window.location.href=\'' . $sHref . '\';"' : '') . '>' . "\n" .
          '        <TR>' . "\n" .
          '          <TD valign="top" align="center" width="40"><IMG src="gfx/lovd_' . $sType . '.png" alt="' . $aTypes[$sType] . '" title="' . $aTypes[$sType] . '" width="32" height="32" style="margin : 4px;"></TD>' . "\n" .
          '          <TD valign="middle">' . $sMessage . '</TD></TR></TABLE><BR>' . "\n\n");
}





function lovd_showNavigation ($sBody, $nPrefix = 3)
{
    // Function kindly provided by Ileos.nl in the interest of Open Source.
    // Displays navigation table to the screen with given contents, accepting a
    // number of settings.

    // Spaces prepended to HTML code for proper alignment.
    $sPrefix = str_repeat('  ', $nPrefix);

    print($sPrefix . '<TABLE border="0" cellpadding="0" cellspacing="0" class="navigation">' . "\n" .
          $sPrefix . '  <TR align="center">' . "\n" .
          $sPrefix . '    <TD>' . $sBody . '</TD></TR></TABLE>'. "\n\n");
}





/*
DMD_SPECIFIC
function lovd_switchDB ()
{
    // Outputs the HTML to allow user to switch genes.
    // $_AUTH is for authorization; $_SETT, $_CONF and $_STAT are for the top
    // and bottom includes.
    global $_AUTH, $_SETT, $_CONF, $_STAT;

    $qGenes = mysql_query('SELECT symbol, gene FROM ' . TABLE_DBS . ' ORDER BY symbol');
    $nGenes = mysql_num_rows($qGenes);

    if (!defined('_INC_TOP_INCLUDED_') && $nGenes == 1) {
        // Just one gene, redirect to the gene's homepage.
        list($_SESSION['currdb']) = mysql_fetch_row($qGenes);
        // IF THIS IS IMPORTED IN 3.0, you'll need to check this properly. Probably don't want to use SCRIPT_NAME here.
        header('Location: ' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . '?select_db=' . $_SESSION['currdb']);
        exit;
    }

    // Because we want the right menu item to be selected.
    $_GET['action'] = 'switch_db';

    // 2009-09-29; 2.0-22; Also check for clean top include.
    if (!defined('_INC_TOP_INCLUDED_') && !defined('_INC_TOP_CLEAN_INCLUDED_')) {
        if (is_readable('inc-top.php')) {
            require 'inc-top.php';
        } else {
            require ROOT_PATH . 'inc-top.php';
        }
        lovd_printHeader('home_select', 'Home - Select gene database');
    }

    if (!$nGenes) {
        print('      There is currently no gene configured in LOVD yet.<BR>' . "\n\n");
        if (HAS_AUTH && $_AUTH['level'] >= LEVEL_MANAGER) {
            print('      <BR>' . "\n\n");
            lovd_showInfoTable('Go here to <A href="setup_genes.php?action=create">create new genes in LOVD</A>.');
        }
        require ROOT_PATH . 'inc-bot.php';
        exit;
    }

    print('      Please select a gene database:<BR>' . "\n" .
    // IF THIS IS IMPORTED IN 3.0, you'll need to check this properly. Probably don't want to use SCRIPT_NAME here.
          '      <FORM action="' . $_SERVER['SCRIPT_NAME'] . '" id="SelectGeneDB" method="get">' . "\n" .
          '        <SELECT name="select_db" onchange="document.getElementById(\'SelectGeneDB\').submit();">' . "\n");
    while ($zGenes = mysql_fetch_assoc($qGenes)) {
        print('          <OPTION value="' . $zGenes['symbol'] . '"' . ($_SESSION['currdb'] == $zGenes['symbol']? ' selected' : '') . '>' . $zGenes['symbol'] . ' (' . $zGenes['gene'] . ')</OPTION>' . "\n");
    }
    print('        </SELECT><BR>' . "\n" .
          '        <INPUT type="submit" value="Select gene database">' . "\n" .
          '      </FORM>' . "\n\n");

    if ($nGenes == 1) {
        // Just one gene, redirect to the gene's homepage.
        print('      <SCRIPT type="text/javascript">' . "\n" .
              '        <!--' . "\n" .
              '        document.forms[0].submit();' . "\n" .
              '        // -->' . "\n" .
              '      </SCRIPT>' . "\n\n");
    }

    // 2009-09-29; 2.0-22; If the clean top include has been used, use the clean bottom include.
    if (defined('_INC_TOP_CLEAN_INCLUDED_')) {
        require ROOT_PATH . 'inc-bot-clean.php';
    } else {
        require ROOT_PATH . 'inc-bot.php';
    }
    exit;
}
*/





function lovd_validateIP ($sRange, $sIP)
{
    // Checks if a given IP address matches a given IP range.
    $aRange = preg_split('/[;,]/', $sRange);
    $b = false;
    foreach ($aRange as $val) {
        if ($val == '*' || $val == $sIP) {
            $b = true;
            break;
        }

        // Break pattern apart.
        $aIPRef = explode('.', $val);
        $aIP    = explode('.', $sIP);

        $bPart = true;
        foreach ($aIPRef as $nSub => $sSub) {
            if ($sSub == '*' || $sSub == $aIP[$nSub]) {
                // So far, so good.
                continue;
            }

            if (preg_match('/^([0-9]{1,3})\-([0-9]{1,3})$/', $sSub, $aRegs)) {
                // A range is specified.
                $bPart = ($aIP[$nSub] >= $aRegs[1] && $aIP[$nSub] <= $aRegs[2]);
                if (!$bPart) {
                    break;
                }

            } else {
                $bPart = false;
                break;
            }            
        }
        $b = $bPart;
    }
    return $b;
}





/*
DMD_SPECIFIC
function lovd_variantToPosition ($sVariant)
{
    // 2009-09-28; 2.0-22; Added function for API.
    // Calculates the variant's position based on the variant description.
    // Outputs c. positions with c. variants and g. positions with g.variants.

    // Remove first character(s) after c./g. which are: [(?
    $sPosition = preg_replace('/^(c\.|g\.)([[(?]*)/', "$1", $sVariant);
    $sPosition = preg_replace('/^((c\.|g\.)(\*|\-)?[0-9]+([-+][0-9?]+)?(_(\*|\-)?[0-9]+([-+][0-9?]+)?)?).*//*', "$1", $sPosition); ///////// CHANGED TEMPORARILY ADDED /*

    // Final check; does it conform to our output?
    if (!preg_match('/^(c\.|g\.)(\*|\-)?[0-9]+([-+][0-9?]+)?(_(\*|\-)?[0-9]+([-+][0-9?]+)?)?$/', $sPosition)) {
        $sPosition = '';
    }

    return $sPosition;
}
*/





function lovd_writeLog ($sLog, $sEvent, $sMessage)
{
    // Based on a function provided by Ileos.nl in the interest of Open Source.
    // Writes timestamps and messages to given log in the database.
    global $_AUTH;

    // Timestamp, serves as an unique identifier.
    $aTime = explode(' ', microtime());
    $sTime = substr($aTime[0], 2, -2);

    // Insert new line in logs table.
    $q = lovd_queryDB('INSERT INTO ' . TABLE_LOGS . ' VALUES (?, NOW(), ?, ?, ?, ?)', array($sLog, $sTime, ($_AUTH['id']? $_AUTH['id'] : NULL), $sEvent, $sMessage));
    return (bool) $q;
}
?>
