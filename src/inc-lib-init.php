<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-19
 * Modified    : 2020-08-25
 * For LOVD    : 3.0-25
 *
 * Copyright   : 2004-2020 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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

function lovd_arrayInsertAfter ($key, array &$array, $new_key, $new_value)
{
    // Insert $new_key, $new_value pair after entry $key in array $array.
    // Courtesy of http://eosrei.net/
    if (array_key_exists($key, $array)) {
        $new = array();
        foreach ($array as $k => $value) {
            $new[$k] = $value;
            if ($k === $key) {
                $new[$new_key] = $new_value;
            }
        }
        return $new;
    }
    return FALSE;
}





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





function lovd_callMutalyzer ($sMethod, $aArgs = array())
{
    // Wrapper function to call Mutalyzer's REST+JSON webservice.
    // Because we have a wrapper, we can implement CURL, which is much faster on repeated calls.
    global $_CONF;

    // Build URL, regardless of how we'll connect to it.
    $sURL = str_replace('/services', '', $_CONF['mutalyzer_soap_url']) . '/json/' . $sMethod;
    if ($aArgs) {
        $i = 0;
        foreach ($aArgs as $sVariable => $sValue) {
            $sURL .= ($i? '&' : '?');
            $i++;
            $sURL .= $sVariable . '=' . rawurlencode($sValue);
        }
    }
    $sJSONResponse = '';

    if (function_exists('curl_init')) {
        // Initialize curl connection.
        static $hCurl;

        if (!$hCurl) {
            $hCurl = curl_init();
            curl_setopt($hCurl, CURLOPT_RETURNTRANSFER, true); // Return the result as a string.

            // Set proxy.
            if ($_CONF['proxy_host']) {
                curl_setopt($hCurl, CURLOPT_PROXY, $_CONF['proxy_host'] . ':' . $_CONF['proxy_port']);
                if (!empty($_CONF['proxy_username']) || !empty($_CONF['proxy_password'])) {
                    curl_setopt($hCurl, CURLOPT_PROXYUSERPWD, $_CONF['proxy_username'] . ':' . $_CONF['proxy_password']);
                }
            }
        }

        curl_setopt($hCurl, CURLOPT_URL, $sURL);
        $sJSONResponse = curl_exec($hCurl);

    } else {
        // Backup method, no curl installed. Too bad, we'll do it the "slow" way.
        $aJSONResponse = lovd_php_file($sURL);
        if ($aJSONResponse !== false) {
            $sJSONResponse = implode("\n", $aJSONResponse);
        }
    }



    if ($sJSONResponse) {
        $aJSONResponse = json_decode($sJSONResponse, true);
        if ($aJSONResponse !== false) {
            return $aJSONResponse;
        }
    }
    // Something went wrong...
    return false;
}





function lovd_cleanDirName ($s)
{
    // Cleans a given path by resolving a relative path.
    if (!is_string($s)) {
        // No input.
        return false;
    }

    // Clean up the pwd; remove '\' (some PHP versions under Windows seem to escape the slashes with backslashes???)
    $s = stripslashes($s);
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





function lovd_convertBytesToHRSize ($nValue)
{
    // This function takes integers and converts it to sizes like "128M".

    if (!ctype_digit($nValue) && !is_int($nValue)) {
        return false;
    }

    $aSizes = array(
        ' bytes', 'K', 'M', 'G', 'T', 'P',
    );
    $nKey = 0; // bytes.

    while ($nValue >= 1024 && $nKey < count($aSizes)) {
        $nValue /= 1024;
        $nKey ++;
    }

    // Precision makes no sense with three digits.
    if ($nValue >= 100 || !$nKey) {
        // Return an integer.
        return round($nValue) . $aSizes[$nKey];
    } else {
        return number_format($nValue, 1) . $aSizes[$nKey];
    }
}





function lovd_convertIniValueToBytes ($sValue)
{
    // This function takes output from PHP's ini_get() function like "128M" or
    // "256k" and converts it to an integer, measured in bytes.
    // Implementation taken from the example on php.net.
    // FIXME; Implement proper checks here? Regexp?

    $nValue = (int) $sValue;
    $sLast = strtolower(substr($sValue, -1));
    switch ($sLast) {
        case 'g':
            $nValue *= 1024;
        case 'm':
            $nValue *= 1024;
        case 'k':
            $nValue *= 1024;
    }

    return $nValue;
}





function lovd_convertSecondsToTime ($sValue, $nDecimals = 0, $bVerbose = false)
{
    // This function takes a number of seconds and converts it into whole
    // minutes, hours, days, months or years.
    // $nDecimals indicates the number of decimals to use in the returned value.
    // $bVerbose defines whether to use short notation (s, m, h, d, y) or long notation
    //   (seconds, minutes, hours, days, years).
    // FIXME; Implement proper checks here? Regexp?

    $nValue = (int) $sValue;
    if (ctype_digit((string) $sValue)) {
        $sValue .= 's';
    }
    $sLast = strtolower(substr($sValue, -1));
    $nDecimals = (int) $nDecimals;

    $aConversion =
        array(
            's' => array(60, 'm', 'second'),
            'm' => array(60, 'h', 'minute'),
            'h' => array(24, 'd', 'hour'),
            'd' => array(265, 'y', 'day'),
            'y' => array(100, 'c', 'year'),
            'c' => array(100, '', 'century'), // Above is not supported.
        );

    foreach ($aConversion as $sUnit => $aConvert) {
        list($nFactor, $sNextUnit) = $aConvert;
        if ($sLast == $sUnit && $nValue > $nFactor) {
            $nValue /= $nFactor;
            $sLast = $sNextUnit;
        }
    }

    $nValue = round($nValue, $nDecimals);
    if ($bVerbose) {
        // Make it "3 years" instead of "3y".
        return $nValue . ' ' . $aConversion[$sLast][2] . ($nValue == 1? '' : 's');
    } else {
        return $nValue . $sLast;
    }
}





function lovd_createPasswordHash ($sPassword, $sSalt = '')
{
    // Creates a password hash like how it's stored in the database. If no salt
    // is given, it will generate a new salt. If a salt has been given, it's not
    // checked if it is an appropriate salt.

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
    global $_DB, $_SETT, $_T;

    $_T->printHeader(!($sError == 'Init'));
    if (defined('PAGE_TITLE')) {
        $_T->printTitle();
    }

    // Write to log file... if we're not here because we don't have MySQL.
    if (!empty($_DB) && class_exists('PDO') && in_array('mysql', PDO::getAvailableDrivers())) {
        // lovd_displayError() always halts LOVD. If we're in a transaction, any log we'll write
        // to the DB will be gone since PHP will rollBack() any transaction that is still open.
        // So we'd better rollBack() ourselves first!
        try {
            @$_DB->rollBack(); // In case we were in a transaction. // FIXME; we can know from PHP >= 5.3.3.
        } catch (PDOException $eNoTransaction) {}
        $bLog = lovd_writeLog($sLogFile, $sError, $sMessage);
    } else {
        $bLog = false;
    }

    if ($_T->bBotIncluded) {
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
    if ($_T->bBotIncluded) {
        die('</BODY>' . "\n" . '</HTML>' . "\n\n");
    } else {
        $_T->printFooter();
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





function lovd_getActivateCustomColumnQuery ($aColumns = array(), $bActivate = true)
{
    // Create custom columns based on the columns listed in inc-sql-columns.php file.
    global $_INI; // $_INI is needed for inc-sql-columns.php.

    // This defines $aColSQL.
    require_once ROOT_PATH . 'install/inc-sql-columns.php';

    // Make sure the first argument, defining which columns to create, is an array.
    // When empty, all columns are created.
    if (!is_array($aColumns)) {
        $aColumns = array($aColumns);
    }

    // Define how many columns we need to create.
    $nColsLeft = (empty($aColumns)? count($aColSQL) : count($aColumns));

    $aSQL = array();
    foreach ($aColSQL as $sInsertSQL) {
        // Find the beginning of field values of an SQL INSERT query
        // INSERT INTO table_name VALUES(...)
        $nIndex = strpos($sInsertSQL, '(');
        if ($nIndex !== false) {
            // Get the string inside brackets VALUES(...)
            $sInsertFields = rtrim(substr($sInsertSQL, $nIndex+1), ')');

            // Split the string into an array.
            $aValues = str_getcsv($sInsertFields);

            // If column is requested, process it. When no columns are specified, process all columns.
            if (empty($aColumns) || in_array($aValues[0], $aColumns)) {
                $aSQL[] = str_replace('INSERT INTO', 'INSERT IGNORE INTO', $sInsertSQL);

                // Only activate column if they are an HGVS or standard column.
                if ($bActivate && ($aValues[3] == '1' || $aValues[4] == '1')) {
                    $sColID = $aValues[0];
                    $sColType = $aValues[10];

                    list($sCategory) = explode('/', $sColID);
                    $aTableInfo = lovd_getTableInfoByCategory($sCategory);

                    $sAlterTable = 'ALTER TABLE ' . $aTableInfo['table_sql'] . ' ADD COLUMN `' . $sColID . '` ' . $sColType;
                    $aSQL = array_merge($aSQL, array(
                        'INSERT IGNORE INTO ' . TABLE_ACTIVE_COLS . ' VALUES ("' . $sColID . '", "00000", NOW())',
                        'SET @bExists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "' . $aTableInfo['table_sql'] . '" AND COLUMN_NAME = "' . $sColID . '")',
                        'SET @sSQL := IF(@bExists > 0, \'SELECT "INFO: Column already exists."\', "' . $sAlterTable . '")',
                        'PREPARE Statement FROM @sSQL',
                        'EXECUTE Statement',
                    ));

                    if (!empty($aTableInfo['table_sql_rev'])) {
                        $sAlterRevTable = 'ALTER TABLE ' . $aTableInfo['table_sql_rev'] . ' ADD COLUMN `' . $sColID . '` ' . $sColType;
                        $aSQL = array_merge($aSQL, array(
                            'SET @bExists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "' . $aTableInfo['table_sql_rev'] . '" AND COLUMN_NAME = "' . $sColID . '")',
                            'SET @sSQL := IF(@bExists > 0, \'SELECT "INFO: Column already exists."\', "' . $sAlterRevTable . '")',
                            'PREPARE Statement FROM @sSQL',
                            'EXECUTE Statement',
                        ));
                    }
                }

                $nColsLeft--;
                // Make sure we stop looping once we have processed all columns listed in $aColumns.
                if ($nColsLeft === 0) {
                    break;
                }
            }
        }
    }

    return $aSQL;
}





function lovd_getColleagues ($nType = COLLEAGUE_ALL)
{
    // Return IDs of the users that share their data with the user currently
    //  logged in.
    global $_AUTH;

    $aOut = array();

    if (!isset($_AUTH) || !isset($_AUTH['colleagues_from'])) {
        return $aOut;
    }

    // If we're looking for the entire list, don't bother looping it.
    if ($nType == COLLEAGUE_ALL) {
        return array_keys($_AUTH['colleagues_from']);
    }

    foreach ($_AUTH['colleagues_from'] as $nID => $bAllowEdit) {
        if (($nType & COLLEAGUE_CAN_EDIT) && $bAllowEdit) {
            $aOut[] = $nID;
        } elseif ($nType & COLLEAGUE_CANNOT_EDIT) {
            $aOut[] = $nID;
        }
    }
    return $aOut;
}






function lovd_getColumnData ($sTable)
{
    // Gets and returns the column data for a certain table.
    global $_DB, $_TABLES;
    static $aTableCols = array();

    if (empty($aTableCols[$sTable])) {
        // Only for tables that actually exist.
        if (!in_array($sTable, $_TABLES)) {
            return false;
        }

        $q = $_DB->query('SHOW COLUMNS FROM ' . $sTable, false, false); // Safe, since $sTable is already checked with $_TABLES.
        if (!$q) {
            // Can happen when table does not exist yet (i.e. during install).
            return false;
        }
        $aTableCols[$sTable] = array();
        while ($z = $q->fetchAssoc()) {
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





function lovd_getColumnList ($sTable)
{
    // Returns the list of columns for a certain table.
    return array_keys(lovd_getColumnData($sTable));
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
        if (preg_match('/^((VAR)?CHAR|(TINY|MEDIUM|LONG)?TEXT)/i', $sColType)) {
            return 'TEXT';
        } elseif (preg_match('/^(TINY|SMALL|MEDIUM|BIG)?INT(\([0-9]+\))?( UNSIGNED)?/i', $sColType, $aMatches)) {
            return 'INT' . (isset($aMatches[3])? '_UNSIGNED' : '');
        } elseif (preg_match('/^(FLOAT|DOUBLE)(\([0-9]+\))?( UNSIGNED)?/i', $sColType, $aMatches)) {
            // Currently not supported by LOVD custom columns, but in use in some custom LOVD builds.
            return 'FLOAT' . (isset($aMatches[3])? '_UNSIGNED' : '');
        } elseif (preg_match('/^(DEC|DECIMAL)\([0-9]+,[0-9]+\)( UNSIGNED)?/i', $sColType, $aMatches)) {
            return 'DECIMAL' . (isset($aMatches[2])? '_UNSIGNED' : '');
        } elseif (preg_match('/^DATE(TIME)?/i', $sColType, $aMatches)) {
            return 'DATE' . (isset($aMatches[1])? 'TIME' : '');
        } elseif (preg_match('/^(TINY|MEDIUM|LONG)?(BLOB)/i', $sColType)) {
            return 'BLOB';
        }
    }
    return false;
}





function lovd_getCurrentID ()
{
    // Gets the ID for the current page, formats it, and returns it.
    // E.g. /individuals/1 => 00000001.
    global $_PE, $_SETT;

    if (PATH_COUNT >= 2) {
        if (isset($_SETT['objectid_length'][$_PE[0]]) && ctype_digit($_PE[1])) {
            return sprintf('%0' . $_SETT['objectid_length'][$_PE[0]] . 'd', $_PE[1]);
        } elseif ($_PE[0] == 'genes') {
            return $_PE[1];
        }
    }
    return false;
}





function lovd_getCurrentPageTitle ()
{
    // Generates the current page's title, fetching more information from the
    //  database, if necessary.
    global $_CONF, $_DB, $_PE;

    $ID = lovd_getCurrentID();

    // Start with the action, if any exists.
    $sTitle = ltrim(ACTION . ' ');
    if (ACTION == 'authorize') {
        $sTitle = 'Authorize curators for ';
    } elseif (ACTION == 'create') {
        $sTitle .= 'a new ';
    } elseif (ACTION == 'order') {
        $sTitle = 'Change order of ';
    } elseif (ACTION == 'search_global') {
        $sTitle = 'Search other public LOVDs for ';
    } elseif (ACTION == 'sortCurators') {
        // FIXME: If this were "sort_curators", the code one block down
        //  would have handled it perfectly well.
        $sTitle = 'Sort curators for ';
    } elseif (strpos(ACTION, '_') !== false) {
        $sTitle = str_replace('_', ' ', $sTitle) . (!$ID? '' : 'for ');
    }

    // Custom column settings for genes and diseases.
    if (in_array($_PE[0], array('diseases', 'genes')) && PATH_COUNT >= 3 && $_PE[2] == 'columns') {
        if (PATH_COUNT == 3) {
            // View or resort column list.
            $sTitle .= 'custom data columns enabled for ';
        } else {
            $sColumnID = implode('/', array_slice($_PE, 3));
            $sTitle .= 'settings for the &quot;' . $sColumnID . '&quot; custom data column enabled for ';
        }
    }

    // Capitalize the first letter, trim off the last 's' from the data object.
    $sTitle = ucfirst($sTitle . substr($_PE[0], 0, -1));
    if (ACTION == 'create') {
        $sTitle .= ' entry';
    }

    if ($ID) {
        // We're accessing just one entry.
        if ($_PE[0] == 'genes') {
            $sTitle = preg_replace('/gene$/', ' the ' . $ID . ' gene', $sTitle);
        } else {
            $sTitle .= ' #' . $ID;
        }
    } else {
        return $sTitle;
    }

    // Add details, if available.
    switch ($_PE[0]) {
        case 'diseases':
            list($sName, $nOMIM) = $_DB->query('
                SELECT IF(CASE symbol WHEN "-" THEN "" ELSE symbol END = "", name, CONCAT(symbol, " (", name, ")")), id_omim
                FROM ' . TABLE_DISEASES . '
                WHERE id = ?', array($ID))->fetchRow();
            $sTitle .= ' (' . $sName .
                (!$nOMIM? '' : ', OMIM:' . $nOMIM) . ')';
            break;
        case 'transcripts':
            list($sNCBI, $sGene) =
                $_DB->query('
                    SELECT id_ncbi, geneid
                    FROM ' . TABLE_TRANSCRIPTS . '
                    WHERE id = ?', array($ID))->fetchRow();
            $sTitle .= ' (' . $sNCBI . ', ' . $sGene . ' gene)';
            break;
        case 'variants':
            // Get VOG description and VOT description on the most used transcript.
            // We have to take the status into account, so that we won't disclose
            //  information when people try random IDs!
            // lovd_isAuthorized() can produce false, 0 or 1. Accept 0 or 1.
            $bIsAuthorized = (lovd_isAuthorized('variant', $ID, false) !== false);
            list($sVOG, $sVOT) =
                $_DB->query('
                    SELECT CONCAT(c.`' . $_CONF['refseq_build'] . '_id_ncbi`, ":", vog.`VariantOnGenome/DNA`) AS VOG_DNA,
                        CONCAT(t.geneid, "(", t.id_ncbi, "):", vot.`VariantOnTranscript/DNA`) AS VOT_DNA
                    FROM ' . TABLE_VARIANTS . ' AS vog
                      INNER JOIN ' . TABLE_CHROMOSOMES . ' AS c ON (vog.chromosome = c.name)
                      LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (vog.id = vot.id)
                      LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id)
                      LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot_count ON (t.id = vot.transcriptid)
                    WHERE vog.id = ? AND (? = 1 OR vog.statusid >= ?)
                    GROUP BY vog.id, vot.transcriptid
                    ORDER BY COUNT(vot_count.id) DESC, t.id ASC',
                        array($ID, $bIsAuthorized, STATUS_MARKED))->fetchRow();
            if ($sVOG) {
                $sTitle .= ' (' . $sVOG . (!$sVOT? '' : ', ' . $sVOT) . ')';
            }
            break;
    }

    return $sTitle;
}





function lovd_getExternalSource ($sSource, $nID = false, $bHTML = false)
{
    // Retrieves URL for external source and returns it, including the ID.
    global $_DB;

    static $aSources = array();
    if (!count($aSources)) {
        $aSources = $_DB->query('SELECT id, url FROM ' . TABLE_SOURCES)->fetchAllCombine();
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





function lovd_getFilesFromDir ($sPath = '', $sPrefix = '', $aSuffixes = array())
{
    // Reads out the given path (defaults to the root path), collects all files and sorts them by the prefix.
    // Returns an array with prefixes and their suffixes in a sub array.
    // $aFiles =
    //     array(
    //         prefix =>
    //             array(
    //                 suffix,
    //                 suffix,
    //             ),
    //     );

    $sPath = ($sPath?: ROOT_PATH);
    $sPrefix = ($sPrefix?: '.+');
    if (!is_array($aSuffixes) || !$aSuffixes) {
        $aSuffixes = array('.+');
    }

    $aFiles = array();
    // Loop through the files in the dir and try and find a meta and data file, that match but have no total data file.
    $h = opendir($sPath);
    if (!$h) {
        return false;
    }
    while (($sFile = readdir($h)) !== false) {
        if ($sFile{0} == '.') {
            // Current dir, parent dir, and hidden files.
            continue;
        }
        if (preg_match('/^(' . $sPrefix . ')\.(' . implode('|', array_values($aSuffixes)) . ')$/', $sFile, $aRegs)) {
            //             1                                               2
            // Files matching the pattern.
            list(, $sFilePrefix, $sFileType) = $aRegs;
            if (!isset($aFiles[$sFilePrefix])) {
                $aFiles[$sFilePrefix] = array();
            }
            $aFiles[$sFilePrefix][] = $sFileType;
        }
    }

    return $aFiles;
}





function lovd_getGeneList ()
{
    // Gets the list of genes (ids only), to prevent repeated queries.
    global $_DB;

    static $aGenes = array();
    if (!count($aGenes)) {
        $aGenes = $_DB->query('SELECT id FROM ' . TABLE_GENES . ' ORDER BY id')->fetchAllColumn();
    }

    return $aGenes;
}





function lovd_getInstallURL ($bFull = true)
{
    // Returns URL that can be used in URLs or redirects.
    // ROOT_PATH can be relative or absolute.
    return (!$bFull? '' : PROTOCOL . $_SERVER['HTTP_HOST']) .
        lovd_cleanDirName(substr(ROOT_PATH, 0, 1) == '/'? ROOT_PATH : dirname($_SERVER['SCRIPT_NAME']) . '/' . ROOT_PATH);
}





function lovd_getVariantInfo ($sVariant, $sTranscriptID = '', $bCheckHGVS = false)
{
    // Parses the variant, and returns position fields (2 for genomic variants,
    //  4 for cDNA variants) and variant type.
    // This function is basically a local method that's trying to replace the
    //  MappingInfo feature of Mutalyzer.
    // $sVariant contains the HGVS nomenclature of the variant.
    // $sTranscriptID contains the internal ID or NCBI ID of the transcript that
    //  this variant is on, and is only needed for processing 3' UTR variants,
    //  like c.*10del, since we'll need to have the CDS stop value for that.
    // $bCheckHGVS contains a boolean that allows for using only part of this
    //  function to just check if the variant is HGVS or not. It will in this
    //  case be more stringent than the function normally is, checking the
    //  variant further in details, but it will only return a boolean value.
    global $_DB;

    static $aTranscriptOffsets = array();
    $aResponse = array(
        'position_start' => 0,
        'position_end' => 0,
        'type' => '',
        'warnings' => array(),
    );

    // If given, check if we already know this transcript.
    if ($sTranscriptID && !isset($aTranscriptOffsets[$sTranscriptID])) {
        $aTranscriptOffsets[$sTranscriptID] = $_DB->query('SELECT position_c_cds_end FROM ' . TABLE_TRANSCRIPTS . ' WHERE (id = ? OR id_ncbi = ?)',
            array($sTranscriptID, $sTranscriptID))->fetchColumn();
        if (!$aTranscriptOffsets[$sTranscriptID]) {
            // Transcript not configured correctly.
            // Don't die here; we might not even need these positions. We'll die later if we do.
            $sTranscriptID = '';
        }
    } elseif ($sTranscriptID === false) {
        // If the transcript ID is passed as false, we are asked to ignore not having the transcript.
        // Some random number, high enough to not be smaller than position_start if that's not in the UTR.
        $aTranscriptOffsets[0] = 1000000;
    }

    // Isolate the position(s) from the variant. We don't support combined variants.
    // We're not super picky, and would therefore approve of c.1_2A>C; we also
    //  don't check for the end of the variant, it may contain bases, or not.
    if (preg_match('/^([cgmn])\.(\()?([\-\*]?\d+)([-+](?:\d+|\?))?(?:_([\-\*]?\d+)([-+](?:\d+|\?))?)?([ACGT]>[ACGT]|con|del(?:ins)?|dup|inv|ins|\|(?:gom|lom|met=)|=)(.*)(?(2)\))/', $sVariant, $aRegs)) {
        //             1 = Prefix; indicates what kind of positions we can expect, and what we'll output.
        //                       2 = Do we have an opening parenthesis?
        //                            3 = Start position, might be negative or in the 3' UTR.
        //                                        4 = Start position intronic offset, if available.
        //                                                             5 = End position, might be negative or in the 3' UTR.
        //                                                                         6 = End position intronic offset, if available.
        //                                                                                            7 = The variant, which we'll use to determine the type.
        //                                                                                                                                 8 = The suffix.
        list(, $sPrefix,, $sStartPosition, $sStartPositionIntron, $sEndPosition, $sEndPositionIntron, $sVariant, $sSuffix) = $aRegs;

        if ($bCheckHGVS) {
            // This was quite a lossy check, sufficient to get positions and type, but we need a HGVS check now.
            if (strpos($sVariant, '>') !== false && $sEndPosition) {
                // Substitutions are not allowed to have a range.
                return false;
            }

            if ($sSuffix) {
                // Suffix not allowed in some cases.
                if (strpos($sVariant, '>') !== false || $sVariant == 'inv' || substr($sVariant, 0, 1) == '|' || $sVariant == '=') {
                    // No suffix allowed for substitutions, inversions, methylation or WT calls.
                    return false;
                } elseif ($sVariant == 'con' && !preg_match('/^([NX][CMR]_[0-9]{6}\.[0-9]+:)?([0-9]+|[0-9]+[+-][0-9]+)_([0-9]+|[0-9]+[+-][0-9]+)$/', $sSuffix)) {
                    // Gene conversions require position fields.
                    return false;
                } elseif (in_array($sVariant, array('del', 'dup')) && !preg_match('/^[ACTG]+$/', $sSuffix)) {
                    // Only allow bases as suffix for deletions and duplications.
                    return false;
                } elseif ($sVariant == 'delins' && !preg_match('/^([ACTG]+|\([0-9]+\))$/', $sSuffix)) {
                    // Only allow bases or length as suffix for deletion-insertion events.
                    // Position ranges for deletion-insertions are actually conversions.
                    return false;
                } elseif ($sVariant == 'ins' && !preg_match('/^([ACTG]+|\([0-9]+\)|[0-9]+_[0-9]+|\[NC_[0-9]{6}\.[0-9]+:[0-9]+_[0-9]+\])$/', $sSuffix)) {
                    // Supported are insertions with bases, length, or with position fields.
                    return false;
                }
            } else {
                // Suffix is required in some cases.
                if (in_array($sVariant, array('con', 'delins', 'ins'))) {
                    return false;
                }
            }
        }

        if ($sPrefix != 'c' && $sPrefix != 'n') {
            if ($sStartPositionIntron || $sEndPositionIntron) {
                // Anything not c. or n. is regarded genomic, having a max of 2 positions.
                // Found more positions? Return false.
                return false;
            } elseif (!ctype_digit($sStartPosition) || ($sEndPosition && !ctype_digit($sEndPosition))) {
                // Non-numeric first character of the main positions is also impossible for genomic variants.
                return false;
            }
        }

        // Convert 3' UTR notations into normal notations.
        if ($sStartPosition{0} == '*' || ($sEndPosition && $sEndPosition{0} == '*')) {
            // Check if a transcript ID has been provided.
            if ($sTranscriptID === '') {
                // No, but we'll need it.
                return false;
            }

            // Translate positions.
            if ($sStartPosition{0} == '*') {
                $sStartPosition = substr($sStartPosition, 1) + $aTranscriptOffsets[$sTranscriptID];
            }
            if ($sEndPosition && $sEndPosition{0} == '*') {
                $sEndPosition = substr($sEndPosition, 1) + $aTranscriptOffsets[$sTranscriptID];
            }
        }

        // Store positions.
        $aResponse['position_start'] = $sStartPosition;
        $aResponse['position_end'] = ($sEndPosition? $sEndPosition : $sStartPosition);

        // And intronic, if needed.
        if ($sPrefix == 'c' || $sPrefix == 'n') {
            // Interpret ? positions as intronic position 1, which is more correct than 0,
            // which makes them look like exonic variants and may also result in different variants.
            // Simplest to just do a str_replace().
            $sStartPositionIntron = str_replace('?', '1', $sStartPositionIntron);
            $sEndPositionIntron = str_replace('?', '1', $sEndPositionIntron);

            // (int) to get rid of the '+' if it's there.
            $aResponse['position_start_intron'] = (int) $sStartPositionIntron;
            $aResponse['position_end_intron'] = (int) ($sEndPosition? $sEndPositionIntron : $sStartPositionIntron);
        }



    // If that didn't work, try matching variants with uncertain positions.
    // We're not super picky, and don't check the end of the variant.
    } elseif (preg_match('/^([cgmn])\.(\()?([\-\*]?\d+|\?)([-+](?:\d+|\?))?(?(2)_([\-\*]?\d+|\?)([-+](?:\d+|\?))?\))_(\()?([\-\*]?\d+|\?)([-+](?:\d+|\?))?(?(7)_([\-\*]?\d+|\?)([-+](?:\d+|\?))?\))(con|del(?:ins)?|dup|inv|ins|\|(?:gom|lom|met=))(.*)/', $sVariant, $aRegs)) {
        //                   1 = Prefix; indicates what kind of positions we can expect, and what we'll output.
        //                             2 = Check for opening parenthesis in start position (which triggers it to be a range).
        //                                  3 = Earliest start position, might be a question mark.
        //                                                 4 = Earlier start position intronic offset, if available.
        //                                                                        5 = Latest start position, might be a question mark.
        //                                                                                       6 = Latest start position intronic offset, if available.
        //                                                                                                            7 = Check for opening parenthesis in end position (which triggers it to be a range).
        //                                                                                                                 8 = Earliest end position, might be a question mark.
        //                                                                                                                                9 = Earliest end position intronic offset, if available.
        //                                                                                                                                                       10 = Latest end position, might be a question mark.
        //                                                                                                                                                                      11 = Latest end position intronic offset, if available.
        //                                                                                                                                                                                          12 = The variant, which we'll use to determine the type.
        //                                                                                                                                                                                                                       13 = The suffix.
        list(, $sPrefix,, $sStartPositionEarly, $sStartPositionEarlyIntron, $sStartPositionLate, $sStartPositionLateIntron,, $sEndPositionEarly, $sEndPositionEarlyIntron, $sEndPositionLate, $sEndPositionLateIntron, $sVariant, $sSuffix) = $aRegs;

        if ($bCheckHGVS) {
            // This was quite a lossy check, sufficient to get positions and type, but we need a HGVS check now.
            if ($sSuffix) {
                // Suffix not allowed in some cases.
                if (in_array($sVariant, array('del', 'dup', 'inv')) || substr($sVariant, 0, 1) == '|') {
                    // No suffix allowed for uncertain deletions, duplications, or inversions.
                    return false;
                } elseif ($sVariant == 'con' && !preg_match('/^([NX][CMR]_[0-9]{6}\.[0-9]+:)?[0-9]+_[0-9]+$/', $sSuffix)) {
                    // Gene conversions require position fields.
                    return false;
                } elseif ($sVariant == 'delins' && !preg_match('/^(\([0-9]+\))$/', $sSuffix)) {
                    // Only allow length as suffix for deletion-insertion events.
                    // Position ranges for deletion-insertions are actually conversions.
                    return false;
                } elseif ($sVariant == 'ins' && !preg_match('/^(\([0-9]+\)|[0-9]+_[0-9]+|\[NC_[0-9]{6}\.[0-9]+:[0-9]+_[0-9]+\])$/', $sSuffix)) {
                    // Supported are insertions with length or with position fields.
                    return false;
                }
            } else {
                // Suffix is required in some cases.
                if (in_array($sVariant, array('con', 'delins', 'ins'))) {
                    return false;
                }
            }
        }

        // Always at least create the intron fields for c. and n. variants.
        if ($sPrefix == 'c' || $sPrefix == 'n') {
            $aResponse['position_start_intron'] = $aResponse['position_end_intron'] = 0;
        } else {
            // Genomic coordinates.
            if ($sStartPositionEarlyIntron || $sStartPositionLateIntron || $sEndPositionEarlyIntron || $sEndPositionLateIntron) {
                // Anything not c. or n. is regarded genomic, having a max of 2 positions.
                // Found more positions? Return false.
                return false;
            } elseif (
                (!ctype_digit($sStartPositionEarly) && $sStartPositionEarly != '?' ) ||
                (!ctype_digit($sStartPositionLate) && $sStartPositionLate != '?' && $sStartPositionLate) ||
                (!ctype_digit($sEndPositionEarly) && $sEndPositionEarly != '?') ||
                (!ctype_digit($sEndPositionLate) && $sEndPositionLate != '?' && $sEndPositionLate)) {
                // Non-numeric first character of the positions (- or *) is also impossible for genomic variants.
                return false;
            }
        }

        // Convert 3' UTR notations into normal notations.
        if ($sStartPositionEarly{0} == '*' || ($sStartPositionLate && $sStartPositionLate{0} == '*')
            || $sEndPositionEarly{0} == '*' || ($sEndPositionLate && $sEndPositionLate{0} == '*')) {
            // Check if a transcript ID has been provided.
            if ($sTranscriptID === '') {
                // No, but we'll need it.
                return false;
            }

            // Translate positions.
            foreach (array('sStartPositionEarly', 'sStartPositionLate', 'sEndPositionEarly', 'sEndPositionLate') as $sPosition) {
                if (substr($$sPosition, 0, 1) == '*') {
                    $$sPosition = substr($$sPosition, 1) + $aTranscriptOffsets[$sTranscriptID];
                }
            }
        }

        // Store positions.
        // FIXME: The handling of the start and end positions are so similar,
        //  we can group the code with a loop, if we rewrite all variable names.
        // If each position (start, end) has two numeric positions, we choose the
        //  average of the two positions. Otherwise, we pick the numeric one.
        // We do require at least one numeric start position and one numeric end position.
        if (is_numeric($sStartPositionEarly) && is_numeric($sStartPositionLate)) {
            // Calculate the average...
            if ($sStartPositionEarly == $sStartPositionLate) {
                // Positions are equal, so average the intronic positions if they're there.
                $aResponse['position_start'] = $sStartPositionEarly;
                if ($sStartPositionEarlyIntron || $sStartPositionLateIntron) {
                    $aResponse['position_start_intron'] = (string) round(($sStartPositionEarlyIntron + $sStartPositionLateIntron) / 2);
                }
            } else {
                $aResponse['position_start'] = (string) round(($sStartPositionEarly + $sStartPositionLate) / 2);
                // In the unlikely case the average would be 0, pick 1.
                if (!$aResponse['position_start']) {
                    $aResponse['position_start'] = '1';
                }
            }
        } elseif (is_numeric($sStartPositionEarly)) {
            $aResponse['position_start'] = $sStartPositionEarly;
            if ($sStartPositionEarlyIntron) {
                $aResponse['position_start_intron'] = $sStartPositionEarlyIntron;
            }
        } elseif (is_numeric($sStartPositionLate)) {
            $aResponse['position_start'] = $sStartPositionLate;
            if ($sStartPositionLateIntron) {
                $aResponse['position_start_intron'] = $sStartPositionLateIntron;
            }
        } else {
            // Two non-numeric positions. Reject this variant.
            return false;
        }
        if (is_numeric($sEndPositionEarly) && is_numeric($sEndPositionLate)) {
            // Calculate the average...
            if ($sEndPositionEarly == $sEndPositionLate) {
                // Positions are equal, so average the intronic positions if they're there.
                $aResponse['position_end'] = $sEndPositionEarly;
                if ($sEndPositionEarlyIntron || $sEndPositionLateIntron) {
                    $aResponse['position_end_intron'] = (string) round(($sEndPositionEarlyIntron + $sEndPositionLateIntron) / 2);
                }
            } else {
                $aResponse['position_end'] = (string) round(($sEndPositionEarly + $sEndPositionLate) / 2);
                // In the unlikely case the average would be 0, pick 1.
                if (!$aResponse['position_end']) {
                    $aResponse['position_end'] = '1';
                }
            }
        } elseif (is_numeric($sEndPositionEarly)) {
            $aResponse['position_end'] = $sEndPositionEarly;
            if ($sEndPositionEarlyIntron) {
                $aResponse['position_end_intron'] = $sEndPositionEarlyIntron;
            }
        } elseif (is_numeric($sEndPositionLate)) {
            $aResponse['position_end'] = $sEndPositionLate;
            if ($sEndPositionLateIntron) {
                $aResponse['position_end_intron'] = $sEndPositionLateIntron;
            }
        } else {
            // Two non-numeric positions. Reject this variant.
            return false;
        }

    } else {
        return false;
    }



    // If a variant is described poorly with a start > end, then we'll swap the positions so we will store them correctly.
    if ($aResponse['position_start'] > $aResponse['position_end']) {
        // Don't do this if we're checking the HGVS.
        if ($bCheckHGVS) {
            return false;
        } else {
            $aResponse['warnings']['WPOSITIONSSWAPPED'] = 'Variant end position is higher than variant start position.';
        }

        // There's many ways of doing this, but this method is the simplest to read.
        $nTmp = $aResponse['position_start'];
        $aResponse['position_start'] = $aResponse['position_end'];
        $aResponse['position_end'] = $nTmp;

        // And intronic, if needed.
        if ($sPrefix == 'c' || $sPrefix == 'n') {
            $nTmp = $aResponse['position_start_intron'];
            $aResponse['position_start_intron'] = $aResponse['position_end_intron'];
            $aResponse['position_end_intron'] = $nTmp;
        }
    }

    // End of all checks. If we only wanted to know about the HGVS, then quit.
    if ($bCheckHGVS) {
        return true;
    }

    // Variant type.
    if (preg_match('/^[ACGT]>[ACGT]$/', $sVariant)) {
        $aResponse['type'] = 'subst';
    } elseif (substr($sVariant, 0, 1) == '|') {
        $aResponse['type'] = 'met';
    } else {
        $aResponse['type'] = $sVariant;
    }

    // When strict SQL mode is enabled, we'll get errors when we'll try and
    //  insert large numbers in the position fields.
    // Check the positions we extracted; the variant could be described badly,
    //  and this could cause a query error.
    // Rather, fix the position fields to their respective maximum values.
    static $aMinMaxValues = array(
        'g' => array(
            'position_start' => array(1, 4294967295),
            'position_end' => array(1, 4294967295),
        ),
        'm' => array(
            'position_start' => array(1, 4294967295),
            'position_end' => array(1, 4294967295),
        ),
        'c' => array(
            'position_start' => array(-8388608, 8388607),
            'position_start_intron' => array(-2147483648, 2147483647),
            'position_end' => array(-8388608, 8388607),
            'position_end_intron' => array(-2147483648, 2147483647),
        ),
        'n' => array(
            'position_start' => array(1, 8388607),
            'position_start_intron' => array(-2147483648, 2147483647),
            'position_end' => array(1, 8388607),
            'position_end_intron' => array(-2147483648, 2147483647),
        ),
    );

    if (isset($aMinMaxValues[$sPrefix])) {
        // If the min and max values are defined for this prefix, check the fields.
        foreach ($aMinMaxValues[$sPrefix] as $sField => $aMinMaxValue) {
            $nOriValue = $aResponse[$sField];
            $aResponse[$sField] = max($aResponse[$sField], $aMinMaxValue[0]);
            $aResponse[$sField] = min($aResponse[$sField], $aMinMaxValue[1]);
            if ($nOriValue != $aResponse[$sField]) {
                if (!isset($aResponse['warnings']['WPOSITIONSLIMIT'])) {
                    $aResponse['warnings']['WPOSITIONSLIMIT'] = 'Position is beyond the possible limits of its type: ' . $sField . '.';
                } else {
                    // Append.
                    $aResponse['warnings']['WPOSITIONSLIMIT'] =
                        str_replace(array('Position is ', ' its '), array('Positions are ', ' their '), rtrim($aResponse['warnings']['WPOSITIONSLIMIT'], '.')) . ', ' . $sField . '.';
                }
            }
        }
    }

    return $aResponse;
}





function lovd_getProjectFile ()
{
    // Gets project file name (file name including possible project subdirectory).
    // 2015-03-05; 3.0-13; When running an import, this function is called very often, so let's cache this function's results.
    static $sProjectFile;
    if ($sProjectFile) {
        return $sProjectFile;
    }

    $sDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/'; // /LOVDv.3.0/install/ or /
    $sProjectDir = lovd_cleanDirName($sDir . ROOT_PATH);        // /LOVDv.3.0/         or /
    $sDir = substr($sDir, strlen($sProjectDir) - 1);            // /install/           or /
    // You need to use SCRIPT_FILENAME here, because SCRIPT_NAME can lose the .php extension.
    $sProjectFile = $sDir . basename($_SERVER['SCRIPT_FILENAME']); // /install/index.php  or /variants.php
    return $sProjectFile;
}





function lovd_getTableInfoByCategory ($sCategory)
{
    // Returns information on the LOVD table that holds the data for this given
    // custom column category.

    $aTables =
        array(
            'Individual' =>
                array(
                    'table_sql' => TABLE_INDIVIDUALS,
                    'table_name' => 'Individual',
                    'table_alias' => 'i',
                    'shared' => false,
                    'unit' => '',
                ),
            'Phenotype' =>
                array(
                    'table_sql' => TABLE_PHENOTYPES,
                    'table_name' => 'Phenotype',
                    'table_alias' => 'p',
                    'shared' => !LOVD_plus, // True for LOVD, false for LOVD+.
                    'unit' => 'disease', // Is also used to determine the key (diseaseid).
                ),
            'Screening' =>
                array(
                    'table_sql' => TABLE_SCREENINGS,
                    'table_name' => 'Screening',
                    'table_alias' => 's',
                    'shared' => false,
                    'unit' => '',
                ),
            'VariantOnGenome' =>
                array(
                    'table_sql' => TABLE_VARIANTS,
                    'table_name' => 'Genomic Variant',
                    'table_alias' => 'vog',
                    'shared' => false,
                    'unit' => '',
                ),
            'VariantOnTranscript' =>
                array(
                    'table_sql' => TABLE_VARIANTS_ON_TRANSCRIPTS,
                    'table_name' => 'Transcript Variant',
                    'table_alias' => 'vot',
                    'shared' => !LOVD_plus, // True for LOVD, false for LOVD+.
                    'unit' => 'gene', // Is also used to determine the key (geneid).
                ),
        );
    if (!array_key_exists($sCategory, $aTables)) {
        return false;
    }
    return $aTables[$sCategory];
}





function lovd_hideEmail ($s)
{
    // Function kindly provided by Ileos.nl in the interest of Open Source.
    // Obscure email addresses from spambots.

    $a_replace = array(45 => '-', '.',
        48 => '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
        64 => '@', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
        95 => '_',
        97 => 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z',
    );

    $s_return = '';
    for ($i = 0; $i < strlen($s); $i ++) {
        $s_sub = substr($s, $i, 1);
        if ($key = array_search($s_sub, $a_replace)) {
            $s_return .= '&#' . str_pad($key, 3, '0', STR_PAD_LEFT) . ';';
        } else {
            $s_return .= $s_sub;
        }
    }

    return $s_return;
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
    print($sPrefix . '<SCRIPT type="text/javascript" src="' . $sFile . (empty($sArg)? '' : '?' . $sArg) . '"> </SCRIPT>' . "\n");
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
    // 1    : allowed to view hidden data, allowed to edit (LEVEL_OWNER || LEVEL_CURATOR).
    // Returns 1 by default for any user with level LEVEL_MANAGER or higher for non-user based authorization requests.
    global $_AUTH, $_DB, $_CONF;

    if (!$_AUTH) {
        return false;
    } elseif ($sType != 'user' && $_AUTH['level'] >= LEVEL_MANAGER) {
        return 1;
    }

    // Check data type.
    if (!$Data) {
        return false;
    } elseif (!in_array($sType, array('analysisrun', 'user', 'gene', 'disease', 'transcript', 'variant', 'individual', 'phenotype', 'screening', 'screening_analysis'))) {
        lovd_writeLog('Error', 'LOVD-Lib', 'lovd_isAuthorized() - Function didn\'t receive a valid datatype (' . $sType . ').');
        return false;
    }

    if ($sType == 'user') {
        // Base authorization on own level and other's level, if not requesting authorization on himself.
        if (is_array($Data)) {
            // Not supported on this data type.
            return false;
        } else {
            // If viewing himself, always get authorization.
            if ($Data == $_AUTH['id']) {
                if ($bSetUserLevel && $_AUTH['level'] < LEVEL_OWNER) {
                    $_AUTH['level'] = LEVEL_OWNER;
                }
                return 1;
            } elseif ($_AUTH['level'] < LEVEL_MANAGER) {
                // Lower than managers never get access to hidden data of other users.
                return false;
            } else {
                $nLevelData = $_DB->query('SELECT level FROM ' . TABLE_USERS . ' WHERE id = ?', array($Data))->fetchColumn();
                return (int) ($_AUTH['level'] > $nLevelData);
            }
        }
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

    if (LOVD_plus && $sType == 'analysisrun') {
        // Authorization based on person who started the analysis run (note, not necessarily the whole analysis).
        if (is_array($Data)) {
            // Not supported on this data type.
            return false;
        } else {
            $nCreatorID = $_DB->query('SELECT created_by FROM ' . TABLE_ANALYSES_RUN . ' WHERE id = ?', array($Data))->fetchColumn();
            if ($_AUTH['level'] >= LEVEL_ANALYZER && $nCreatorID == $_AUTH['id']) {
                // At least Analyzer (Managers don't get to this point).
                if ($bSetUserLevel) {
                    $_AUTH['level'] = LEVEL_OWNER;
                }
                return 1;
            }
        }
        return false;

    } elseif (LOVD_plus && $sType == 'screening_analysis') {
        // Authorization based on not being analyzed, or analysis being started by $_AUTH['id'].
        if (is_array($Data)) {
            // Not supported on this data type.
            return false;
        } else {
            $z = $_DB->query('SELECT analysis_statusid, analysis_by FROM ' . TABLE_SCREENINGS . ' WHERE id = ?', array($Data))->fetchAssoc();
            if ($_AUTH['level'] >= LEVEL_ANALYZER) {
                // At least Analyzer (Managers don't get to this point).
                if ($z['analysis_by'] == $_AUTH['id'] ||
                    ($z['analysis_by'] === NULL && $z['analysis_statusid'] == ANALYSIS_STATUS_READY)) {
                    if ($bSetUserLevel) {
                        $_AUTH['level'] = LEVEL_OWNER;
                    }
                    return 1;
                }
            }
        }
        return false;
    }

    // Makes it easier to check the data.
    if (!is_array($Data)) {
        $Data = array($Data);
    }

    switch ($sType) {
        // Queries for every data type.
        case 'transcript':
            $aGenes = $_DB->query('SELECT DISTINCT geneid FROM ' . TABLE_TRANSCRIPTS . ' WHERE id IN (?' . str_repeat(', ?', count($Data)-1) . ')', $Data)->fetchAllColumn();
            return lovd_isAuthorized('gene', $aGenes, $bSetUserLevel);
        case 'disease':
            $aGenes = $_DB->query('SELECT DISTINCT geneid FROM ' . TABLE_GEN2DIS . ' WHERE diseaseid IN (?' . str_repeat(', ?', count($Data)-1) . ')', $Data)->fetchAllColumn();
            return lovd_isAuthorized('gene', $aGenes, $bSetUserLevel);
        case 'variant':
            $aGenes = $_DB->query('SELECT DISTINCT t.geneid FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE vot.id IN (?' . str_repeat(', ?', count($Data)-1) . ')', $Data)->fetchAllColumn();
            break;
        case 'individual':
            $aGenes = $_DB->query('SELECT DISTINCT t.geneid FROM ' . TABLE_TRANSCRIPTS . ' AS t LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (vot.transcriptid = t.id) LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vot.id = s2v.variantid) LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id) WHERE s.individualid IN (?' . str_repeat(', ?', count($Data)-1) . ')', $Data)->fetchAllColumn();
            break;
        case 'phenotype':
            $aGenes = $_DB->query('SELECT DISTINCT t.geneid FROM ' . TABLE_TRANSCRIPTS . ' AS t LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (vot.transcriptid = t.id) LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vot.id = s2v.variantid) LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id) LEFT OUTER JOIN ' . TABLE_PHENOTYPES . ' AS p ON (s.individualid = p.individualid) WHERE p.id IN (?' . str_repeat(', ?', count($Data)-1) . ')', $Data)->fetchAllColumn();
            break;
        case 'screening':
            $aGenes = $_DB->query('SELECT DISTINCT t.geneid FROM ' . TABLE_TRANSCRIPTS . ' AS t LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (vot.transcriptid = t.id) LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vot.id = s2v.variantid) WHERE s2v.screeningid IN (?' . str_repeat(', ?', count($Data)-1) . ')', $Data)->fetchAllColumn();
            break;
        default:
            return false;
    }

    // Run the authorization on genes.
    $Auth = lovd_isAuthorized('gene', $aGenes, $bSetUserLevel);
    if ($Auth) {
        // Level has already been set by recursive call.
        return 1;
    }

    if (LOVD_plus && $sType == 'variant') {
        // LOVD+ allows LEVEL_OWNER authorization based on ownership of the screening analysis.
        // We dump in multiple variants, but we should really only get one Screening back.
        $aScreeningIDs = $_DB->query('SELECT DISTINCT screeningid FROM ' . TABLE_SCR2VAR . ' WHERE variantid IN (?' . str_repeat(', ?', count($Data) - 1) . ')', $Data)->fetchAllColumn();
        $bOwner = lovd_isAuthorized('screening_analysis', $aScreeningIDs[0], false);
    } else {
        $bOwner = lovd_isOwner($sType, $Data);
    }
    if (($bOwner || lovd_isColleagueOfOwner($sType, $Data, true)) && $_CONF['allow_submitter_mods']) {
        if ($bSetUserLevel) {
            $_AUTH['level'] = LEVEL_OWNER;
        }
        return 1;
    }
    // Collaborator OR Owner, but not allowed to edit own entries.
    if ($Auth === 0 || $bOwner || lovd_isColleagueOfOwner($sType, $Data, false)) {
        if ($bSetUserLevel) {
            $_AUTH['level'] = LEVEL_COLLABORATOR;
        }
        return 0;
    }
    if ($bSetUserLevel) {
        $_AUTH['level'] = LEVEL_SUBMITTER;
    }
    return false;
}





function lovd_isColleagueOfOwner ($sType, $Data, $bMustHaveEditPermission = true)
{
    // Checks if the current user (specified by global $_AUTH) is owner of the
    // data objects.
    // Params:
    // $sType       Type of the data object to check (string)
    // $Data        ID of object (string) or array of IDs of multiple objects
    //              (array of strings). Returns true if user is a colleague of
    //              some owner for ALL of the objects.
    // $bMustHaveEditPermission
    //              Flag, if true this function returns only true if the
    //              current user is a colleague of the owner of $Data with
    //              explicit edit permission as defined by field 'allow_edit'
    //              in TABLE_COLLEAGUES.
    // Return: True if all of the objects of type $sType with an ID in $Data
    //         are owned or created by a colleague of the current user.

    global $_DB;

    if (!in_array($sType, array('individual', 'phenotype', 'screening', 'variant'))) {
        // Unknown data type, return false by default.
        return false;
    }

    if (!is_array($Data)) {
        $Data = array($Data);
    }

    $colleagueTypeFlag = ($bMustHaveEditPermission? COLLEAGUE_CAN_EDIT : COLLEAGUE_ALL);
    $aOwnerIDs = lovd_getColleagues($colleagueTypeFlag);
    if (!$aOwnerIDs) {
        // No colleagues that give this user the enough permissions.
        return false;
    }
    $sColleaguePlaceholders = '(?' . str_repeat(', ?', count($aOwnerIDs) - 1) . ')';
    $sDataPlaceholders = '(?' . str_repeat(', ?', count($Data) - 1) . ')';

    $sQ = 'SELECT COUNT(*) FROM ' . constant('TABLE_' . strtoupper($sType) . 'S') . ' WHERE id IN ' .
        $sDataPlaceholders . ' AND (owned_by IN ' . $sColleaguePlaceholders . ')';
    $q = $_DB->query($sQ, array_merge($Data, $aOwnerIDs));

    return ($q !== false && intval($q->fetchColumn()) == count($Data));
}





function lovd_isOwner ($sType, $Data)
{
    // Checks if the current user (specified by global $_AUTH) is owner of the
    // data objects.
    // Params:
    // $sType       Type of the data object to check (string)
    // $Data        ID of object (string) or array of IDs of multiple objects
    //              (array of strings). Returns true if user is owner for ALL
    //              of the objects.
    // Return: True if all of the objects of type $sType with an ID in $Data
    //         are owned or created by current user.
    global $_AUTH, $_DB;

    if (!isset($_AUTH) || !$_AUTH) {
        // No authentication -- cannot be owner of anything.
        return false;
    }

    if (!in_array($sType, array('individual', 'phenotype', 'screening', 'variant'))) {
        // Unknown data type, return false by default.
        return false;
    }

    if (!is_array($Data)) {
        $Data = array($Data);
    }

    $sDataPlaceholders = '(?' . str_repeat(', ?', count($Data) - 1) . ')';

    $sQ = 'SELECT COUNT(*) FROM ' . constant('TABLE_' . strtoupper($sType) . 'S') . ' WHERE id IN ' .
             $sDataPlaceholders . ' AND (owned_by = ? OR created_by = ?)';
    $q = $_DB->query($sQ, array_merge($Data, array($_AUTH['id'], $_AUTH['id'])));

    return ($q !== false && intval($q->fetchColumn()) == count($Data));
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





function lovd_mapCodeToDescription ($aCodes, $aMaps)
{
    // Takes an array $aCodes and maps all values using the $aMaps array.
    // Values not found in $aMaps are not changed in $aCodes.

    if (is_array($aCodes) && !empty($aCodes)) {
        foreach ($aCodes as $nKey => $sCode) {
            if (isset($aMaps[$sCode])) {
                $aCodes[$nKey] = $aMaps[$sCode];
            }
        }
    }

    return $aCodes;
}





function lovd_parseConfigFile($sConfigFile)
{
    // Parses the given config file, checks all values, and returns array with parsed settings.

    // Config file exists?
    if (!file_exists($sConfigFile)) {
        lovd_displayError('Init', 'Can\'t find config.ini.php');
    }

    // Config file readable?
    if (!is_readable($sConfigFile)) {
        lovd_displayError('Init', 'Can\'t read config.ini.php');
    }

    // Open config file.
    if (!$aConfig = file($sConfigFile)) {
        lovd_displayError('Init', 'Can\'t open config.ini.php');
    }



    // Parse config file.
    $_INI = array();
    unset($aConfig[0]); // The first line is the PHP code with the exit() call.

    $sKey = '';
    foreach ($aConfig as $nLine => $sLine) {
        // Go through the file line by line.
        $sLine = trim($sLine);

        // Empty line or comment.
        if (!$sLine || substr($sLine, 0, 1) == '#') {
            continue;
        }

        // New section.
        if (preg_match('/^\[([A-Z][A-Z_ ]+[A-Z])\]$/i', $sLine, $aRegs)) {
            $sKey = $aRegs[1];
            $_INI[$sKey] = array();
            continue;
        }

        // Setting.
        if (preg_match('/^([A-Z_]+) *=(.*)$/i', $sLine, $aRegs)) {
            list(, $sVar, $sVal) = $aRegs;
            $sVal = trim($sVal, ' "\'“”');

            if (!$sVal) {
                $sVal = false;
            }

            // Set value in array.
            if ($sKey) {
                $_INI[$sKey][$sVar] = $sVal;
            } else {
                $_INI[$sVar] = $sVal;
            }

        } else {
            // Couldn't parse value.
            lovd_displayError('Init', 'Error parsing config file at line ' . ($nLine + 1));
        }
    }

    // We now have the $_INI variable filled according to the file's contents.
    // Check the settings' values to see if they are valid.
    $aConfigValues =
        array(
            'database' =>
                array(
                    'driver' =>
                        array(
                            'required' => true,
                            'default'  => 'mysql',
                            'pattern'  => '/^[a-z]+$/',
                            'values' => array('mysql' => 'MySQL', 'sqlite' => 'SQLite'),
                        ),
                    'hostname' =>
                        array(
                            'required' => true,
                            'default'  => 'localhost',
                            // Also include hostname:port and :/path/to/socket values.
                            'pattern'  => '/^([0-9a-z][-0-9a-z.]*[0-9a-z](:[0-9]+)?|:[-0-9a-z.\/]+)$/i',
                        ),
                    'username' =>
                        array(
                            'required' => true,
                        ),
                    'password' =>
                        array(
                            'required' => false, // XAMPP and other systems have 'root' without password as default!
                        ),
                    'database' =>
                        array(
                            'required' => true,
                        ),
                    'table_prefix' =>
                        array(
                            'required' => true,
                            'default'  => 'lovd',
                            'pattern'  => '/^[A-Z0-9_]+$/i',
                        ),
                ),
            'paths' =>
                array(
                    'data_files' =>
                        array(
                            'required' => LOVD_plus,
                            'path_is_readable' => true,
                            'path_is_writable' => true,
                        ),
                    'data_files_archive' =>
                        array(
                            'required' => false,
                            'path_is_readable' => true,
                            'path_is_writable' => true,
                        ),
                ),
        );

    if (LOVD_plus) {
        // Configure data file paths.
        $aConfigValues['paths'] = array_merge(
            $aConfigValues['paths'], array(
            'alternative_ids' =>
                array(
                    'required' => false,
                    'path_is_readable' => true,
                    'path_is_writable' => false,
                ),
            'confirm_variants' =>
                array(
                    'required' => false,
                    'path_is_readable' => true,
                    'path_is_writable' => true,
                ),
        ));

        // Configure instance details.
        $aConfigValues['instance'] = array(
            'name' =>
                array(
                    'required' => false,
                    'default'  => '',
                ),
        );
    }

    // SQLite doesn't need an username and password...
    if (isset($_INI['database']['driver']) && $_INI['database']['driver'] == 'sqlite') {
        unset($aConfigValues['database']['username']);
        unset($aConfigValues['database']['password']);
    }

    foreach ($aConfigValues as $sSection => $aVars) {
        foreach ($aVars as $sVar => $aVar) {
            if (!isset($_INI[$sSection][$sVar]) || !$_INI[$sSection][$sVar]) {
                // Nothing filled in...

                if (isset($aVar['default']) && $aVar['default']) {
                    // Set default value.
                    $_INI[$sSection][$sVar] = $aVar['default'];
                } elseif (isset($aVar['required']) && $aVar['required']) {
                    // No default value, required setting not filled in.
                    lovd_displayError('Init', 'Error parsing config file: missing required value for setting \'' . $sVar . '\' in section [' . $sSection . ']');
                } elseif (!isset($_INI[$sSection][$sVar])){
                    // Add the setting to the $_INI array to avoid notices.
                    $_INI[$sSection][$sVar] = false;
                }

            } else {
                // Value is present in $_INI.
                if (isset($aVar['pattern']) && !preg_match($aVar['pattern'], $_INI[$sSection][$sVar])) {
                    // Error: a pattern is available, but it doesn't match the input!
                    lovd_displayError('Init', 'Error parsing config file: incorrect value for setting \'' . $sVar . '\' in section [' . $sSection . ']');

                } elseif (isset($aVar['values']) && is_array($aVar['values'])) {
                    // Value must be present in list of possible values.
                    $_INI[$sSection][$sVar] = strtolower($_INI[$sSection][$sVar]);
                    if (!array_key_exists($_INI[$sSection][$sVar], $aVar['values'])) {
                        // Error: a value list is available, but it doesn't match the input!
                        lovd_displayError('Init', 'Error parsing config file: incorrect value for setting \'' . $sVar . '\' in section [' . $sSection . ']');
                    }
                }

                // For paths, check readability or writability.
                if (!empty($aVar['path_is_readable']) && !is_readable($_INI[$sSection][$sVar])) {
                    // Error: The path should be readable, but it's not!
                    lovd_displayError('Init', 'Error parsing config file: path for \'' . $sVar . '\' in section [' . $sSection . '] is not readable.');
                }
                if (!empty($aVar['path_is_writable']) && !is_writable($_INI[$sSection][$sVar])) {
                    // Error: The path should be writable, but it's not!
                    lovd_displayError('Init', 'Error parsing config file: path for \'' . $sVar . '\' in section [' . $sSection . '] is not writable.');
                }
            }
        }
    }

    return $_INI;
}






function lovd_php_file ($sURL, $bHeaders = false, $sPOST = false, $aAdditionalHeaders = array()) {
    // LOVD's alternative to file(), not dependent on the fopen wrappers, and can do POST requests.
    global $_CONF, $_SETT;

    // Check additional headers.
    if (!is_array($aAdditionalHeaders)) {
        $aAdditionalHeaders = array($aAdditionalHeaders);
    }

    // Prepare proxy authorization header.
    if (!empty($_CONF['proxy_username']) && !empty($_CONF['proxy_password'])) {
        $aAdditionalHeaders[] = 'Proxy-Authorization: Basic ' . base64_encode($_CONF['proxy_username'] . ':' . $_CONF['proxy_password']);
    }

    $aAdditionalHeaders[] = ''; // To make sure we end with a \r\n.

    // Use the simple file() method, only if:
    // - We're working with local files, OR:
    // - We're using HTTPS (because our fsockopen() currently doesn't support that, let's hope allow_url_fopen is on), OR:
    // - Fopen wrappers are on.
    if (substr($sURL, 0, 4) != 'http' || substr($sURL, 0, 5) == 'https' || ini_get('allow_url_fopen')) {
        // Normal file() is fine.
        $aOptions = array(
            'http' => array(
                'method' => ($sPOST? 'POST' : 'GET'),
                'header' => $aAdditionalHeaders,
                'user_agent' => 'LOVDv.' . $_SETT['system']['version'],
            ),
        );

        if ($sPOST) {
            // Add POST content to HTTP options and headers.
            $aOptions['http']['content'] = $sPOST;
            array_unshift($aOptions['http']['header'], 'Content-Type: application/x-www-form-urlencoded');
        }

        // If we're connecting through a proxy, we need to set some additional information.
        if ($_CONF['proxy_host']) {
            $aOptions['http']['proxy'] = 'tcp://' . $_CONF['proxy_host'] . ':' . $_CONF['proxy_port'];
            $aOptions['http']['request_fulluri'] = true;
        }
        if (substr($sURL, 0, 5) == 'https') {
            $aOptions['ssl'] = array('allow_self_signed' => 1, 'SNI_enabled' => 1, (PHP_VERSION_ID >= 50600? 'peer_name' : 'SNI_server_name') => parse_url($sURL, PHP_URL_HOST));
            $aOptions['http']['request_fulluri'] = false; // Somehow this breaks when testing through squid3 and using HTTPS.
        }

        return @file($sURL, FILE_IGNORE_NEW_LINES, stream_context_create($aOptions));
    }

    $aHeaders = array();
    $aOutput = array();
    $aURL = parse_url($sURL);
    if ($aURL['host']) {
        // fsockopen() can only connect to an HTTPS (proxy or host), when using "ssl" as the scheme, and having OpenSSL installed.
        $f = @fsockopen((!empty($_CONF['proxy_host'])? $_CONF['proxy_host'] : $aURL['host']), (!empty($_CONF['proxy_port'])? $_CONF['proxy_port'] : 80));
        if ($f === false) {
            // No use continuing - it will only cause errors.
            return false;
        }
        $sRequest = ($sPOST? 'POST ' : 'GET ') . (!empty($_CONF['proxy_host'])? $sURL : $aURL['path'] . (empty($aURL['query'])? '' : '?' . $aURL['query'])) . ' HTTP/1.0' . "\r\n" .
                    'Host: ' . $aURL['host'] . "\r\n" .
                    'User-Agent: LOVDv.' . $_SETT['system']['version'] . "\r\n" .
                    (!$sPOST? '' :
                    'Content-length: ' . strlen($sPOST) . "\r\n" .
                    'Content-Type: application/x-www-form-urlencoded' . "\r\n") .
            implode("\r\n", $aAdditionalHeaders) .
                    'Connection: Close' . "\r\n\r\n" .
                    (!$sPOST? '' :
                    $sPOST . "\r\n");
        fputs($f, $sRequest);
        $bListen = false; // We want to start capturing the output AFTER the headers have ended.
        while (!feof($f)) {
            $s = fgets($f);
            if ($s === false) {
                // This mysteriously may happen at the first fgets() call???
                continue;
            }
            $s = rtrim($s, "\r\n");
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

        // On some status codes we return false.
        if (isset($aHeaders[0]) && preg_match('/^HTTP\/1\.. (\d{3}) /', $aHeaders[0], $aRegs)) {
            if ($aRegs[1] == '404') {
                return false;
            }
        }
    }

    if (!$bHeaders) {
        return($aOutput);
    } else {
        return(array($aHeaders, $aOutput));
    }
}





function lovd_php_gethostbyaddr ($sIP)
{
    // LOVD's gethostbyaddr implementation, that easily turns off all DNS lookups if offline.
    if (!defined('OFFLINE_MODE') && OFFLINE_MODE) {
        // We're offline. Don't do lookups.
        return $sIP;
    }

    // Else, do a lookup.
    return gethostbyaddr($sIP);
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
    // $_AUTH is for authorization; $_SETT is needed for the user levels.
    global $_AUTH, $_SETT, $_T;

    $aKeys = array_keys($_SETT['user_levels']);
    if ($nLevel !== 0 && !in_array($nLevel, $aKeys)) {
        $nLevel = max($aKeys);
    }

    // $nLevel is now 0 (just existence of $_AUTH required) or taken from the levels list.
    if (!$_AUTH || ($nLevel && $_AUTH['level'] < $nLevel)) {
        $_T->printHeader();

        if (defined('PAGE_TITLE')) {
            $_T->printTitle();
        }

        $sMessage = 'To access this area, you need ' . (!$nLevel? 'to <A href="login">log in</A>.' : ($nLevel == max($aKeys)? '' : 'at least ') . $_SETT['user_levels'][$nLevel] . ' clearance.');
        // FIXME; extend this list?
        if (lovd_getProjectFile() == '/submit.php') {
            $sMessage .= '<BR>If you are not registered as a submitter, please <A href="users?register">do so here</A>.';
        }
        lovd_showInfoTable($sMessage, 'stop');

        $_T->printFooter();
        exit;
    }
}





function lovd_saveWork ()
{
    // Save the changes made in $_AUTH['saved_work'] by inserting the changed array back into the database.
    global $_AUTH, $_DB;

    if ($_AUTH && isset($_AUTH['saved_work'])) {
        // FIXME; Later when we add a decent json_encode library, we will switch to that.
        $_DB->query('UPDATE ' . TABLE_USERS . ' SET saved_work = ? WHERE id = ?', array(serialize($_AUTH['saved_work']), $_AUTH['id']));
        return true;
    } else {
        return false;
    }
}





function lovd_shortenString ($s, $l = 50)
{
    // Based on a function provided by Ileos.nl in the interest of Open Source.
    // Shortens string nicely to a given length.
    // FIXME; Should be able to shorten from the left as well, useful with for example transcript names.
    if (strlen($s) > $l) {
        $s = rtrim(substr($s, 0, $l - 3), '(');
        // Also make sure the parentheses are balanced. It assumes they were balanced before shorting the string.
        $nClosingParenthesis = 0;
        while (substr_count($s, '(') > (substr_count($s, ')') + $nClosingParenthesis)) {
            $s = rtrim(substr($s, 0, ($l - 3 - ++$nClosingParenthesis)), '('); // Usually eats off one, but we may have started with a shorter string because of the rtrim().
        }
        $s .= '...' . str_repeat(')', $nClosingParenthesis);
    }
    return $s;
}





function lovd_showDialog ($sID, $sTitle, $sMessage, $sType = 'information', $aSettings = array())
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

    // Other settings.
    $aSettingDefaults =
        array(
            'modal' => 'false',
            'position' => '', // Center of dialog on center of screen.
            'buttons' => '',
        );

    if (!is_array($aSettings)) {
        $aSettings = array();
    }
    foreach ($aSettings as $sKey => $sVal) {
        if (!isset($aSettingDefaults[$sKey])) {
            // Setting does not exist (= has no default).
            unset($aSettings[$sKey]);
            continue;
        }
        // Overwrite default settings.
        $aSettingDefaults[$sKey] = $sVal;
    }

    print('      <DIV id="' . $sID . '" title="' . $sTitle . '" style="display : none;">' . "\n" .
          '        <TABLE border="0" cellpadding="0" cellspacing="0" width="100%">' . "\n" .
          '          <TR>' . "\n" .
          '            <TD valign="top" align="left" width="50"><IMG src="gfx/lovd_' . $sType . '.png" alt="' . $aTypes[$sType] . '" title="' . $aTypes[$sType] . '" width="32" height="32" style="margin : 4px;"></TD>' . "\n" .
          '            <TD valign="middle">' . $sMessage . '</TD></TR></TABLE></DIV>' . "\n" .
          '      <SCRIPT type="text/javascript">$("#' . $sID . '").dialog({draggable:false,resizable:false,minWidth:400,show:"fade",closeOnEscape:true,hide:"fade"');
    // Add settings.
    foreach ($aSettingDefaults as $sKey => $sVal) {
        if ($sVal) {
            print(',' . $sKey . ':' . $sVal);
        }
    }
    print('});</SCRIPT>' . "\n\n");
}





function lovd_showInfoTable ($sMessage, $sType = 'information', $sWidth = '100%', $sHref = '', $bBR = true)
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

    switch (FORMAT) {
        case 'text/plain':
            // We're ignoring the $sWidth here.
            $nWidth = 100;
            $sSeparatorLine = '+' . str_repeat('-', $nWidth - 2) . '+';
            $aMessage = explode("\n", wordwrap($sMessage, $nWidth - 4));
            $aMessage = array_map('str_pad', $aMessage, array_fill(0, count($aMessage), $nWidth - 4));
            print($sSeparatorLine . "\n" .
                  '| ' . str_pad($aTypes[$sType], $nWidth - 4, ' ') . ' |' . "\n" .
                  $sSeparatorLine . "\n" .
                  '| ' . implode(" |\n| ", $aMessage) . ' |' . "\n" .
                  $sSeparatorLine . (!$bBR? '' : "\n") . "\n");
            break;
        default:
            print('      <TABLE border="0" cellpadding="2" cellspacing="0" width="' . $sWidth . '" class="info"' . (!empty($sHref)? ' style="cursor : pointer;" onclick="' . (preg_match('/[ ;"\'=()]/', $sHref)? $sHref : 'window.location.href=\'' . $sHref . '\';') . '"': '') . '>' . "\n" .
                  '        <TR>' . "\n" .
                  '          <TD valign="top" align="center" width="40"><IMG src="gfx/lovd_' . $sType . '.png" alt="' . $aTypes[$sType] . '" title="' . $aTypes[$sType] . '" width="32" height="32" style="margin : 4px;"></TD>' . "\n" .
                  '          <TD valign="middle">' . $sMessage . '</TD></TR></TABLE>' . (!$bBR? '' : '<BR>') . "\n\n");
    }
}





function lovd_showJGNavigation ($aOptions, $sID, $nPrefix = 3)
{
    // Prints a navigation dropdown menu to the screen with given contents.

    if (!is_array($aOptions) || !count($aOptions)) {
        return false;
    }

    // Spaces prepended to HTML code for proper alignment.
    $sPrefix = str_repeat('  ', $nPrefix);

    print($sPrefix . '<IMG src="gfx/options_button.png" alt="Options" width="82" height="20" id="viewentryOptionsButton_' . $sID . '" style="margin-top : 5px; cursor : pointer;"><BR>' . "\n" .
          $sPrefix . '<UL id="viewentryMenu_' . $sID . '" class="jeegoocontext jeegooviewlist">' . "\n");
    foreach ($aOptions as $sURL => $aLink) {
        list($sIMG, $sName, $bShown) = $aLink;
        $sSubMenu = '';
        if (!empty($aLink['sub_menu'])) {
            // Allow for one level of sub menus.
            $sSubMenu = "\n" . $sPrefix . '    <UL>' . "\n";
            foreach ($aLink['sub_menu'] as $sSubURL => $aSubMenu) {
                list($sSubIMG, $sSubName) = $aSubMenu;
                $sSubMenu .= $sPrefix . '      <LI' . (!$sSubIMG? '' : ' class="icon"') . '><A ' . (substr($sSubURL, 0, 11) == 'javascript:'? 'click="' : 'href="' . lovd_getInstallURL(false)) . ltrim($sSubURL, '/') . '">' .
                    (!$sIMG? '' : '<SPAN class="icon" style="background-image: url(gfx/' . $sSubIMG . ');"></SPAN>') . $sSubName .
                    '</A></LI>' . "\n";
            }
            $sSubMenu .= $sPrefix . '    </UL>' . "\n  " . $sPrefix;
        }
        if ($bShown) {
            // IE (who else) refuses to respect the BASE href tag when using JS. So we have no other option than to include the full path here.
            print($sPrefix . '  <LI' . (!$sIMG? '' : ' class="icon"') . '><A ' . (substr($sURL, 0, 11) == 'javascript:'? 'click="' : 'href="' . ($sSubMenu? '' : lovd_getInstallURL(false))) . ($sSubMenu? '' : ltrim($sURL, '/')) . '">' .
                                (!$sIMG? '' : '<SPAN class="icon" style="background-image: url(gfx/' . $sIMG . ');"></SPAN>') . $sName .
                                '</A>' . $sSubMenu . '</LI>' . "\n");
        } else {
            print($sPrefix . '  <LI class="disabled' . (!$sIMG? '' : ' icon') . '">' . (!$sIMG? '' : '<SPAN class="icon" style="background-image: url(gfx/' . preg_replace('/(\.[a-z]+)$/', '_disabled' . "$1", $sIMG) . ');"></SPAN>') . $sName . '</LI>' . "\n");
        }
    }
    print($sPrefix . '</UL>' . "\n\n" .
          $sPrefix . '<SCRIPT type="text/javascript">' . "\n" .
          $sPrefix . '  $(function() {' . "\n" .
          $sPrefix . '    var aMenuOptions = {' . "\n" .
          $sPrefix . '      event: "click",' . "\n" .
          $sPrefix . '      openBelowContext: true,' . "\n" .
          $sPrefix . '      autoHide: true,' . "\n" .
          $sPrefix . '      delay: 100,' . "\n" .
          $sPrefix . '      onSelect: function(e, context) {' . "\n" .
          $sPrefix . '        if ($(this).hasClass("disabled")) {' . "\n" .
          $sPrefix . '          return false;' . "\n" .
          $sPrefix . '        } else if ($(this).find(\'a\').attr(\'href\') != undefined && $(this).find(\'a\').attr(\'href\') != \'\') {' . "\n" .
          $sPrefix . '          window.location = $(this).find(\'a\').attr(\'href\');' . "\n" .
          $sPrefix . '          return false; // False doesn\'t close the menu, but at least it prevents double hits on the page we\'re going to.' . "\n" .
          $sPrefix . '        } else if ($(this).find(\'a\').attr(\'click\') != undefined) {' . "\n" .
          $sPrefix . '          eval($(this).find(\'a\').attr(\'click\'));' . "\n" .
          $sPrefix . '          return true; // True closes the menu.' . "\n" .
          $sPrefix . '        } else {' . "\n" .
          $sPrefix . '          return false;' . "\n" .
          $sPrefix . '        }' . "\n" .
          $sPrefix . '      }' . "\n" .
          $sPrefix . '    };' . "\n" .
          $sPrefix . '    // Add menu to options icon.' . "\n" .
          $sPrefix . '    $(\'#viewentryOptionsButton_' . $sID . '\').jeegoocontext(\'viewentryMenu_' . $sID . '\', aMenuOptions);' . "\n" .
          $sPrefix . '  });' . "\n" .
          $sPrefix . '</SCRIPT>' . "\n\n");
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





function lovd_verifyInstance ($sName, $bExact = true)
{
    // Check if this instance belongs to $sName instance group (LOVD+ feature).
    // If $bExact is set to true, it will match the exact instance name instead
    //  of matching just the prefix.

    global $_INI;

    // Only LOVD+ can have the instance name in the config file.
    if (!LOVD_plus || empty($_INI['instance']['name'])) {
        return false;
    }

    if (strtolower($_INI['instance']['name']) == strtolower($sName)) {
        return true;
    }

    if (!$bExact) {
        if (strpos(strtolower($_INI['instance']['name']), strtolower($sName)) === 0) {
            return true;
        }
    }

    return false;
}





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





function lovd_writeLog ($sLog, $sEvent, $sMessage, $nAuthID = 0)
{
    // Based on a function provided by Ileos.nl in the interest of Open Source.
    // Writes timestamps and messages to given log in the database.
    global $_AUTH, $_DB;

    if (!$_DB) {
        // Don't try to log when we don't have DB connection (such as mysql password incorrect).
        return false;
    }

    // Timestamp, serves as an unique identifier.
    $aTime = explode(' ', microtime());
    $sTime = substr($aTime[0], 2, -2);

    // Insert new line in logs table.
    $q = $_DB->query('INSERT INTO ' . TABLE_LOGS . ' VALUES (?, NOW(), ?, ?, ?, ?)',
        array($sLog, $sTime, ($nAuthID? $nAuthID : ($_AUTH['id']? $_AUTH['id'] : NULL)), $sEvent, $sMessage), false);
    return (bool) $q;
}
?>
