<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-19
 * Modified    : 2011-12-13
 * For LOVD    : 3.0-alpha-07
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
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

function lovd_calculateVersion ($sVersion)
{
    // Builds version formatted for string-comparing LOVD versions to determine
    // correct version order.

    // Slightly different preg_match pattern.
    if (preg_match('/^([1-9]\.[0-9](\.[0-9])?)(\-([0-9a-z-]{2,11}))?$/', $sVersion, $aVersion)) {
        $sReturn = sprintf('%-04s', str_replace('.', '', $aVersion[1]));
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





function lovd_createPasswordHash ($sPassword, $sSalt = '')
{
    // Creates a password hash like how it's stored in the database. If no salt
    // is given, it will generate a new salt. If a salt has been given, it's not
    // checked if it is an appropiate salt.

    if (!$sPassword) {
        return false;
    }
    if (!$sSalt) {
        $sSalt = substr(sha1(time() . mt_rand()), 0, 8);
    }
    $sPasswordHash = sha1($sPassword . ':' . $sSalt);
    return substr($sPasswordHash, 0, 32) . ':' . $sSalt . ':' . substr($sPasswordHash, -8);
}





function lovd_displayError ($sError, $sMessage, $sLogFile = 'Error')
{
    // Function kindly provided by Ileos.nl in the interest of Open Source.
    // Writes an error message to the errorlog and displays the same message on
    // screen for the user. This function halts PHP processing in all cases.
    global $_AUTH, $_DB, $_SETT, $_CONF, $_STAT;

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

    // Write to log file... if we're not here because we don't have MySQL.
    if (function_exists('mysql_connect')) {
        $bLog = @lovd_writeLog($sLogFile, $sError, $sMessage);
    } else {
        $bLog = false;
    }

    if (defined('_INC_BOT_CLOSE_HTML_') && _INC_BOT_CLOSE_HTML_ === false) {
        print('<BR>' . "\n\n");
    }
    $sMessage = htmlspecialchars($sMessage);

    // A LOVD-Lib or Query error is always an LOVD bug! (unless MySQL went down)
    if ($sError == 'LOVD-Lib' || ($sError == 'Query' && strpos($sMessage, 'You have an error in your SQL syntax'))) {
        $sMessage .= "\n\n" .
                     'A failed query is usually an LOVD bug. Please report this bug by copying the above text and send it to us by opening a new ticket in our <A href="' . $_SETT['upstream_BTS_URL_new_ticket'] . '" target="_blank">bug tracking system</A>.';
    }

    // Display error.
    print("\n" . '
      <TABLE border="0" cellpadding="0" cellspacing="0" align="center" width="900" class="error">
        <TR>
          <TH>Error: ' . $sError . ($bLog? ' (Logged)' : '') . '</TH></TR>
        <TR>
          <TD>' . str_replace(array("\n", "\t"), array('<BR>', '&nbsp;&nbsp;&nbsp;&nbsp;'), $sMessage) . '</TD></TR></TABLE>' . "\n\n");

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





function lovd_generateRandomID ($l = 10)
{
    // Generates random ID with $l length.

    $l = (int) $l;
    if ($l > 32) {
        $l = 32;
    } elseif ($l < 6) {
        $l = 6;
    }
    $nStart = mt_rand(0, 32-$l);
    return substr(md5(microtime()), $nStart, $l);
}





function lovd_getColumnData ($sTable)
{
    // Gets and returns the column data for a certain table.
    global $_TABLES;
    static $aTableCols = array();

    // Only for tables that actually exist.
    if (!in_array($sTable, $_TABLES)) {
        return false;
    }

    if (empty($aTableCols[$sTable])) {
        $q = lovd_queryDB_Old('SHOW COLUMNS FROM ' . mysql_real_escape_string($sTable));
        if (!$q) {
            // Should never happen!
            return false;
        }
        $aTableCols[$sTable] = array();
        while ($z = @mysql_fetch_assoc($q)) {
            $aTableCols[$sTable][$z['Field']] =
                     array(
                            'type' => $z['Type'],
                            'null' => $z['Null'],
                            'default' => $z['Default'],
                          );
        }
    }
    
    return $aTableCols[$sTable];
}





function lovd_getColumnLength ($sTable, $sCol)
{
    // Determines the column lengths for a given table and column.
    $aTableCols = lovd_getColumnData($sTable);

    if (!empty($aTableCols[$sCol])) {
        // Table && col exist.
        $sColType = $aTableCols[$sCol]['type'];

        if (preg_match('/(CHAR|INT)\(([0-9]+)\)/i', $sColType, $aRegs)) {
            return (int) $aRegs[2];

        } elseif (preg_match('/^DATE(TIME)?/i', $sColType, $aRegs)) {
            return (10 + (empty($aRegs[1])? 0 : 9));

        } elseif (preg_match('/^DECIMAL\(([0-9]+),([0-9]+)\)/i', $sColType, $aRegs)) {
            return ($aRegs[1] - $aRegs[2]);

        } elseif (preg_match('/^(TINY|MEDIUM|LONG)?(TEXT|BLOB)/i', $sColType, $aRegs)) {
            switch ($aRegs[1]) { // Key [1] must exist, because $aRegs[2] exists.
                case 'TINY':
                    return 255;
                case 'MEDIUM':
                    return 16777215;
                case 'LONG':
                    return 4294967295;
                default:
                    return 65535;
            }
        }
    }

    return 0;
}





/*
function lovd_getColumnMaxValue ($sTable, $sCol)
{
    // Determines the column's maximum value for numeric columns.
    $aTableCols = lovd_getColumnData($sTable);

    if (!empty($aTableCols[$sCol])) {
        // Table && col exist.
        $sColType = $aTableCols[$sCol]['type'];

        if (preg_match('/^DECIMAL\(([0-9]+),([0-9]+)\)/i', $sColType, $aRegs)) {
            return (float) (str_repeat('9', $aRegs[1] - $aRegs[2]) . '.' . str_repeat('9', $aRegs[2]));

        } elseif (preg_match('/^(TINY|SMALL|MEDIUM|BIG)?(INT)/i', $sColType, $aRegs)) {
            switch ($aRegs[1]) { // Key [1] must exist, because $aRegs[2] exists.
                case 'TINY':
                    return 255; // 2^8; 1 byte
                case 'SMALL':
                    return 65535; // 2^16; 2 bytes
                case 'MEDIUM':
                    return 16777215; // 2^24; 3 bytes
                case 'BIG':
                    return 18446744073709551615; // 2^64; 8 bytes
                default:
                    return 4294967295; // 2^32; 4 bytes
            }
        }
    }

    return 0;
}
*/





function lovd_getColumnType ($sTable, $sCol)
{
    // Determines the column type for a given (table and) column.

    if ($sTable) {
        $aTableCols = lovd_getColumnData($sTable);
        if (!empty($aTableCols[$sCol])) {
            // Table && col exist.
            $sColType = $aTableCols[$sCol]['type'];
        }
    } else {
        // Custom column's MySQL type given, use that.
        $sColType = $sCol;
    }

    if (!empty($sColType)) {
        if (preg_match('/^(TINY|MEDIUM|LONG)?(BLOB)/i', $sColType)) {
            return 'BLOB';
        } elseif (preg_match('/^DATETIME/i', $sColType)) {
            return 'DATETIME';
        } elseif (preg_match('/^DATE/i', $sColType)) {
            return 'DATE';
        } elseif (preg_match('/^DEC|DECIMAL\([0-9]+,[0-9]+\) UNSIGNED/i', $sColType)) {
            return 'DECIMAL_UNSIGNED';
        } elseif (preg_match('/^DEC|DECIMAL\([0-9]+,[0-9]+\)/i', $sColType)) {
            return 'DECIMAL';
        } elseif (preg_match('/^((VAR)?CHAR|(TINY|MEDIUM|LONG)?TEXT)/i', $sColType)) {
            return 'TEXT';
        } elseif (preg_match('/^(TINY|SMALL|MEDIUM|BIG)?INT\([0-9]+\) UNSIGNED/i', $sColType)) {
            return 'INT_UNSIGNED';
        } elseif (preg_match('/^(TINY|SMALL|MEDIUM|BIG)?INT\([0-9]+\)/i', $sColType)) {
            return 'INT';
        }
    }
    return false;
}





function lovd_getExternalSource ($sSource, $nID = false, $bHTML = false)
{
    // Retrieves URL for external source and returns it, including the ID.
    static $aSources = array();
    if (!count($aSources)) {
        $q = lovd_queryDB_Old('SELECT * FROM ' . TABLE_SOURCES);
        while ($z = mysql_fetch_assoc($q)) {
            $aSources[$z['id']] = $z['url'];
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
    // Gets the list of genes (ids only), to prevent repeated queries.
    global $_DB;

    static $aGenes = array();
    if (!count($aGenes)) {
        $aGenes = $_DB->query('SELECT id FROM ' . TABLE_GENES . ' ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
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

    // Remove '?argument' that may be at the end of the file name.
    $aFile = explode('?', $sFile, 2);
    $aFile[] = false;
    list($sFile, $sArg) = $aFile;

    if (substr($sFile, 0, 4) != 'http' && !is_readable(ROOT_PATH . $sFile)) {
        return false;
    }

    static $aIncludedFiles = array();
    // Include a file just once!
    if (in_array($sFile, $aIncludedFiles)) {
        return true;
    } else {
        $aIncludedFiles[] = $sFile;
    }

    $sPrefix = str_repeat('  ', $nPrefix);
    print($sPrefix . '<SCRIPT type="text/javascript" src="' . $sFile . (empty($sArg)? '' : '?' . $sArg) . '"></SCRIPT>' . "\n");
    return true;
}





function lovd_isAuthorized ($sType, $Data, $bSetUserLevel = true)
{
    // Checks whether a user is allowed to view or edit a certain data type.
    // $Data may be a (list of) IDs.
    // If $bSetUserLevel is true, the $_AUTH['level'] field will be edited
    // according to the result of this function.
    // Returns false, 0 or 1, depending on the authorization level of the user.
    // False: not allowed to view hidden data, not allowed to edit.
    // 0    : allowed to view hidden data, not allowed to edit (LEVEL_COLLABORATOR).
    // 1    : allowed to view hidden data, allowed to edit (LEVEL_CURATOR).
    // Returns 1 by default for any user with level LEVEL_MANAGER or higher.
    global $_AUTH, $_DB, $_CONF;

    if (!$_AUTH) {
        return false;
    } elseif ($_AUTH['level'] >= LEVEL_MANAGER) {
        return 1;
    }

    // Check data type.
    if (!$Data || !in_array($sType, array('gene', 'disease', 'transcript', 'variant', 'individual', 'phenotype', 'screening'))) {
        return false;
    }

    if ($sType == 'gene') {
        // Base authorization on (max of) $_AUTH['curates'] and/or $_AUTH['collaborates'].
        if (is_array($Data)) {
            // Gets authorization if one gene matches.
            $AuthMax = false;
            foreach ($Data as $sID) {
                $Auth = lovd_isAuthorized('gene', $sID, $bSetUserLevel);
                if ($Auth !== false) {
                    $AuthMax = $Auth;
                    if ($AuthMax == 1) {
                        return 1; // Level, if needed, has been set by the recursive call.
                    }
                }
            }
            return $AuthMax; // Level, if needed, has been set by the recursive call.

        } else {
            // These arrays are built up in inc-auth.php for users with level < LEVEL_MANAGER.
            $Auth = (in_array($Data, $_AUTH['curates'])? 1 : (in_array($Data, $_AUTH['collaborates'])? 0 : false));
            if ($Auth !== false && $bSetUserLevel) {
                $_AUTH['level'] = ($Auth? LEVEL_CURATOR : LEVEL_COLLABORATOR);
            }
            return $Auth;
        }
    }

    // Makes it easier to check the data.
    if (!is_array($Data)) {
        $Data = array($Data);
    }

    switch($sType) {
        // Queries for every data type.
        case 'transcript':
            $sGenes = $_DB->query('SELECT GROUP_CONCAT(DISTINCT geneid SEPARATOR ";") FROM ' . TABLE_TRANSCRIPTS . ' WHERE id IN (?' . str_repeat(', ?', count($Data)-1) . ')', $Data)->fetchColumn();
            return lovd_isAuthorized('gene', explode(';', $sGenes), $bSetUserLevel);
        case 'disease':
            $sGenes = $_DB->query('SELECT GROUP_CONCAT(DISTINCT geneid SEPARATOR ";") FROM ' . TABLE_GEN2DIS . ' WHERE diseaseid IN (?' . str_repeat(', ?', count($Data)-1) . ')', $Data)->fetchColumn();
            return lovd_isAuthorized('gene', explode(';', $sGenes), $bSetUserLevel);
        case 'variant':
            $sGenes = $_DB->query('SELECT GROUP_CONCAT(DISTINCT t.geneid SEPARATOR ";") FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE vot.id IN (?' . str_repeat(', ?', count($Data)-1) . ')', $Data)->fetchColumn();
            $Auth = lovd_isAuthorized('gene', explode(';', $sGenes), $bSetUserLevel);
            $bOwner = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_VARIANTS . ' WHERE id IN (?' . str_repeat(', ?', count($Data)-1) . ') AND (owned_by = ? OR created_by = ?)', array_merge($Data, array($_AUTH['id'], $_AUTH['id'])))->fetchColumn();
        case 'individual':
            $sGenes = $_DB->query('SELECT GROUP_CONCAT(DISTINCT t.geneid SEPARATOR ";") FROM ' . TABLE_TRANSCRIPTS . ' AS t LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (vot.transcriptid = t.id) LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vot.id = s2v.variantid) LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id) WHERE s.individualid IN (?' . str_repeat(', ?', count($Data)-1) . ')', $Data)->fetchColumn();
            $Auth = lovd_isAuthorized('gene', explode(';', $sGenes), $bSetUserLevel);
            $bOwner = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_INDIVIDUALS . ' WHERE id IN (?' . str_repeat(', ?', count($Data)-1) . ') AND (owned_by = ? OR created_by = ?)', array_merge($Data, array($_AUTH['id'], $_AUTH['id'])))->fetchColumn();
        case 'phenotype':
            $sGenes = $_DB->query('SELECT GROUP_CONCAT(DISTINCT t.geneid SEPARATOR ";") FROM ' . TABLE_TRANSCRIPTS . ' AS t LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (vot.transcriptid = t.id) LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vot.id = s2v.variantid) LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id) LEFT OUTER JOIN ' . TABLE_PHENOTYPES . ' AS p ON (s.individualid = p.individualid) WHERE p.id IN (?' . str_repeat(', ?', count($Data)-1) . ')', $Data)->fetchColumn();
            $Auth = lovd_isAuthorized('gene', explode(';', $sGenes), $bSetUserLevel);
            $bOwner = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_PHENOTYPES . ' WHERE id IN (?' . str_repeat(', ?', count($Data)-1) . ') AND (owned_by = ? OR created_by = ?)', array_merge($Data, array($_AUTH['id'], $_AUTH['id'])))->fetchColumn();
        case 'screening':
            $sGenes = $_DB->query('SELECT GROUP_CONCAT(DISTINCT t.geneid SEPARATOR ";") FROM ' . TABLE_TRANSCRIPTS . ' AS t LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (vot.transcriptid = t.id) LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vot.id = s2v.variantid) WHERE s2v.screeningid IN (?' . str_repeat(', ?', count($Data)-1) . ')', $Data)->fetchColumn();
            $Auth = lovd_isAuthorized('gene', explode(';', $sGenes), $bSetUserLevel);
            $bOwner = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_SCREENINGS . ' WHERE id IN (?' . str_repeat(', ?', count($Data)-1) . ') AND (owned_by = ? OR created_by = ?)', array_merge($Data, array($_AUTH['id'], $_AUTH['id'])))->fetchColumn();
        default:
            return false;
    }

    // Run the authorization on genes.
    $Auth = lovd_isAuthorized('gene', explode(';', $sGenes), $bSetUserLevel);
    if ($Auth) {
        // Level has already been set by recursive call.
        return 1;
    }
    // Check for ownership.
    if ($bOwner && $_CONF['allow_submitter_mods']) {
        if ($bSetUserLevel) {
            $_AUTH['level'] = LEVEL_OWNER;
        }
        return 1;
    }
    // Collaborator OR Owner, but not allowed to edit own entries.
    if ($Auth === 0 || $bOwner) {
        if ($bSetUserLevel) {
            $_AUTH['level'] = LEVEL_COLLABORATOR;
        }
        return 0;
    }
    return false;
}





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
    global $_SETT;

    if (substr($sURL, 0, 4) != 'http' || (ini_get('allow_url_fopen') && !$sPOST)) {
        // Normal file() is fine.
        // Unfortunately, FILE_IGNORE_NEW_LINES does not work PHP < 5.0.0.
        if (substr(phpversion(), 0, 1) == '4') {
            $aFile = @file($sURL);
            if (is_array($aFile)) {
                foreach ($aFile as $key => $val) {
                    $aFile[$key] = rtrim($val, "\r\n");
                }
            }
            return $aFile;
        } else {
            return @file($sURL, FILE_IGNORE_NEW_LINES);
        }
    }

    $aHeaders = array();
    $aOutput = array();
    $aURL = parse_url($sURL);
    if ($aURL['host']) {
        $f = @fsockopen($aURL['host'], 80); // Doesn't support SSL without OpenSSL.
        if ($f === false) {
            // No use continuing - it will only cause errors.
            return false;
        }
        $sRequest = ($sPOST? 'POST ' : 'GET ') . $aURL['path'] . (empty($aURL['query'])? '' : '?' . $aURL['query']) . ' HTTP/1.0' . "\r\n" .
                    'Host: ' . $aURL['host'] . "\r\n" .
                    'User-Agent: LOVDv.' . $_SETT['system']['version'] . "\r\n" .
                    (!$sPOST? '' :
                    'Content-length: ' . strlen($sPOST) . "\r\n" .
                    'Content-Type: application/x-www-form-urlencoded' . "\r\n") .
                    'Connection: Close' . "\r\n\r\n" .
                    (!$sPOST? '' :
                    $sPOST . "\r\n");
        fputs($f, $sRequest);
        $bListen = false; // We want to start capturing the output AFTER the headers have ended.
        while (!feof($f)) {
            $s = rtrim(fgets($f), "\r\n");
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





function lovd_php_htmlspecialchars ($Var)
{
    // Recursively run htmlspecialchars(), even with unknown depth.

    if (is_array($Var)) {
        return array_map('lovd_php_htmlspecialchars', $Var);
    } else {
        return htmlspecialchars($Var);
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





function lovd_printHeader ($sTitle, $sStyle = 'H2')
{
    // Prints the page's header.
    $aStyles = array('H2', 'H3', 'H4');
    if (!in_array($sStyle, $aStyles)) {
        $sStyle = $aStyles[0];
    }
    print('      <' . $sStyle . ' class="LOVD">' . $sTitle . '</' . $sStyle . '>' . "\n\n");
}





function lovd_queryDB_Old ($sQuery, $aArgs = array(), $bHalt = false, $bDebug = false)
{
    // Queries the database and protects against SQL injection with
    // mysql_real_escape_string. No code in LOVD should query the database
    // directly!

    if (!is_array($aArgs)) {
        lovd_displayError('LOVD-Lib', 'lovd_queryDB_Old() - Received a wrong type of $aArgs argument from ' . $_SERVER['REQUEST_URI'] . "\n" . 'Query: ' . $sQuery);
    }

    // Explode so we can glue the pieces back together. A simple replace will mess up with more than one argument and one of the replaced values itself contains questionmarks.
    $aQuery = explode('?', $sQuery);
    $sSQL = $aQuery[0]; // So queries without arguments work, too :)
    $aArgs = array_values($aArgs); // Make sure there are continuous numeric keys only.

    // A mismatch between the number of ? in the query and the number of items
    // in $aArgs indicates a bug in LOVD.
    $nSlots = count($aQuery) - 1;
    $nArgs = count($aArgs);
    if ($nSlots != $nArgs) {
        lovd_displayError('LOVD-Lib', 'lovd_queryDB_Old() - ' . $nArgs . ' argument' . ($nArgs == 1? ' does' : 's do') . ' not fit in ' . $nSlots . ' slot' . ($nSlots == 1? '' : 's') . ' from ' . $_SERVER['REQUEST_URI'] . "\n" . 'Query: ' . $sQuery);
    }

    // If they're arguments, go through them and put them into the query.
    foreach ($aArgs as $nKey => $sArg) {
        if ($sArg === NULL) {
            $sSQL .= 'NULL';
        } elseif (is_array($sArg)) {
            // We handle arrays gracefully.
            $sSQL .= '\'' . mysql_real_escape_string(implode(';', $sArg)) . '\'';
        } else {
            // Numeric arguments WILL ALSO BE QUOTED because MySQL handles '01' very different from 01 for VARCHAR columns.
            $sSQL .= '\'' . mysql_real_escape_string($sArg) . '\'';
        }
        $sSQL .= $aQuery[$nKey + 1];
    }
    if ($bDebug) {
        echo htmlspecialchars($sSQL);
    }
    $q = mysql_query($sSQL);
    if (!$q && $bHalt) {
        $sError = mysql_error(); // Save the mysql_error before it disappears.
        lovd_queryDB_Old('ROLLBACK'); // In case we were in a transaction.
        // lovd_queryError() will call lovd_displayError() which will halt the system.
        lovd_queryError((defined('LOG_EVENT')? LOG_EVENT : 'Unknown'), $sSQL, $sError);
    }
    return $q;
}





function lovd_queryError ($sErrorCode, $sSQL, $sSQLError, $bHalt = true)
{
    // Function kindly provided by Ileos.nl in the interest of Open Source.
    // Formats query errors for the error log, and optionally halts the system.
    // Used to be called lovd_dbFout() in LOVD 2.0.

    // Format the error message.
    $sError = preg_replace('/^' . preg_quote(rtrim(lovd_getInstallURL(false), '/'), '/') . '/', '', $_SERVER['REQUEST_URI']) . ' returned error in code block ' . $sErrorCode . '.' . "\n" .
              'Query : ' . $sSQL . "\n" .
              'Error : ' . $sSQLError;

    // If the system needs to be halted, send it through to lovd_displayError() who will print it on the screen,
    // write it to the system log, and halt the system. Otherwise, just log it to the database.
    if ($bHalt) {
        return lovd_displayError('Query', $sError);
    } else {
        return lovd_writeLog('Error', 'Query', $sError);
    }
}





function lovd_requireAUTH ($nLevel = 0)
{
    // Creates friendly output message if $_AUTH does not exist (or level too
    // low), and exits.
    // $_AUTH is for authorization; $_SETT is needed for the user levels;
    // $_CONF and $_STAT are for the top and bottom includes.
    global $_AUTH, $_DB, $_SETT, $_CONF, $_STAT;

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
    // FIXME; Should take into account any ( characters in the string and should close them after shortening them.
    // FIXME; Should be able to shorten from the left as well, useful with for example transcript names.
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





function lovd_verifyPassword ($sPassword, $sOriHash)
{
    // Verifies a password given a certain hash. This hash is usually taken from
    // the database and can be generated using both the "old" LOVD 1.1.0/2.0 md5
    // method and the new LOVD 3.0 sha1 method with salt.

    if (strlen($sOriHash) == 50) {
        // New (3.0-alpha-02) method of storing the password.
        list($sOriPassHash1, $sSalt, $sOriPassHash2) = preg_split('/:/', $sOriHash);
        $sOriHash = $sOriPassHash1 . $sOriPassHash2;
        $sPasswordHash = sha1($sPassword . ':' . $sSalt);
    } else {
        // Simple, older (LOVD 1.1.0/2.0) method of storing the password.
        $sPasswordHash = md5($sPassword);
    }

    return ($sPasswordHash == $sOriHash);
}





function lovd_writeLog ($sLog, $sEvent, $sMessage)
{
    // Based on a function provided by Ileos.nl in the interest of Open Source.
    // Writes timestamps and messages to given log in the database.
    global $_AUTH;

    // Timestamp, serves as an unique identifier.
    $aTime = explode(' ', microtime());
    $sTime = substr($aTime[0], 2, -2);

    // Insert new line in logs table.
    $q = lovd_queryDB_Old('INSERT INTO ' . TABLE_LOGS . ' VALUES (?, NOW(), ?, ?, ?, ?)', array($sLog, $sTime, ($_AUTH['id']? $_AUTH['id'] : NULL), $sEvent, $sMessage));
    return (bool) $q;
}
?>
