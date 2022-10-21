<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-19
 * Modified    : 2022-10-21
 * For LOVD    : 3.0-29
 *
 * Copyright   : 2004-2022 Leiden University Medical Center; http://www.LUMC.nl/
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

// A place to store values used by multiple functions. It doesn't really make
//  sense to define a function simply to store information. But we can't put
//  this in $_SETT because tests need it (and don't include inc-init.php).
$_LIBRARIES = array(
    'regex_patterns' => array(
        'refseq' => array(
            'basic' => '/^[A-Z_.t0-9()]+$/',
            'strict'  =>
                '/^([NX][CGMRTW]_[0-9]{6}\.[0-9]+' .
                '|[NX][MR]_[0-9]{9}\.[0-9]+' .
                '|N[CGTW]_[0-9]{6}\.[0-9]+\([NX][MR]_[0-9]{6,9}\.[0-9]+\)' .
                '|ENS[TG][0-9]{11}\.[0-9]+' .
                '|LRG_[0-9]+(t[0-9]+)?' .
                ')$/',
        ),
        'refseq_to_DNA_type' => array(
            '/[NX]M_/'                    => array('c'),
            '/[NX]R_/'                    => array('n'),
            '/^(ENST|LRG_[0-9]+t[0-9]+)/' => array('c', 'n'),
            '/^ENSG/'                     => array('g', 'm'),
            '/^NC_(001807\.|012920\.).$/' => array('m'),
            '/^(N[CGTW]_[0-9]+\.[0-9]+$|LRG_[0-9]+$)/' => array('g'),
        ),
    ),
);





function lovd_arrayInsertAfter ($sKey, &$a, $sKeyToInsert, $ValueToInsert)
{
    // Insert $sKeyToInsert having $ValueToInsert,
    //  after entry $sKey in array $aOri.
    // Based on code by Brad Erickson (http://eosrei.net/comment/287).
    // MIT licensed code, compatible with GPL.
    if (array_key_exists($sKey, $a)) {
        $aNew = array();
        foreach ($a as $k => $value) {
            $aNew[$k] = $value;
            if ($k === $sKey) {
                $aNew[$sKeyToInsert] = $ValueToInsert;
            }
        }
        $a = $aNew;
        return true;
    }
    return false;
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
    if (FORMAT == 'text/html') {
        $sMessage = htmlspecialchars($sMessage);
    }

    // A LOVD-Lib or Query error is always an LOVD bug! (unless MySQL went down)
    if ($sError == 'LOVD-Lib' || ($sError == 'Query' && strpos($sMessage, 'You have an error in your SQL syntax'))) {
        $sMessage .= "\n\n" .
                     'A failed query is usually an LOVD bug. Please report this bug by copying the above text and send it to us by opening a new ticket in our <A href="' . $_SETT['upstream_BTS_URL_new_ticket'] . '" target="_blank">bug tracking system</A>.';
    }

    // Display error.
    switch (FORMAT) {
        case 'application/json':
            print(
                json_encode(
                    array(
                        'version' => '',
                        'messages' => array(),
                        'warnings' => array(),
                        'errors' => array(
                            'Error: ' . $sError . ($bLog? ' (Logged)' : '') . "\n" . $sMessage,
                        ),
                        'data' => array(),
                    )));
            break;
        case 'text/plain':
            print('Error: ' . $sError . ($bLog? ' (Logged)' : '') . "\n" . $sMessage . "\n");
            break;
        case 'text/html':
        default:
            print("\n" . '
      <TABLE border="0" cellpadding="0" cellspacing="0" align="center" width="900" class="error">
        <TR>
          <TH>Error: ' . $sError . ($bLog? ' (Logged)' : '') . '</TH></TR>
        <TR>
          <TD>' . str_replace(array("\n", "\t"), array('<BR>', '&nbsp;&nbsp;&nbsp;&nbsp;'), $sMessage) . '</TD></TR></TABLE>' . "\n\n");
            break;
    }

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

        } elseif (preg_match('/^(TINY|SMALL|MEDIUM|LONG)?(TEXT|BLOB|INT UNSIGNED)/i', $sColType, $aRegs)) {
            // We're matching *INT UNSIGNED as well here. MySQL 8.0.22 leaves
            //  out the length of the field when defining unsigned fields
            //  *without* zerofill. Signed fields or fields with zerofill all
            //  have their length in the column definition. It doesn't make much
            //  sense, but we need this column to work.
            if (substr($aRegs[0], 0, 3) == 'INT') {
                $aRegs[1] = 'LONG';
            }
            switch ($aRegs[1]) { // Key [1] must exist, because $aRegs[2] exists.
                case 'TINY':
                    $nBytes = 255;
                    break;
                case 'SMALL':
                    // This is for INTs only.
                    $nBytes = 65535;
                    break;
                case 'MEDIUM':
                    $nBytes = 16777215;
                    break;
                case 'LONG':
                    $nBytes = 4294967295;
                    break;
                default:
                    // TEXT|BLOB only.
                    $nBytes = 65535;
            }
            if (substr($aRegs[2], 0, 3) == 'INT') {
                return strlen((string) $nBytes);
            } else {
                return $nBytes;
            }
        }
    }

    return 0;
}





function lovd_getColumnMinMax ($sTable, $sCol)
{
    // Determines the column's minimum and maximum values
    //  for a given table and column.
    static $aBytes = array(
        'TINY' => 1,
        'SMALL' => 2,
        'MEDIUM' => 3,
        '' => 4,
        'BIG' => 8,
    );

    $aTableCols = lovd_getColumnData($sTable);

    if (!empty($aTableCols[$sCol])) {
        // Table && col exist.
        $sColType = $aTableCols[$sCol]['type'];

        if (preg_match('/^(TINY|SMALL|MEDIUM|BIG)?INT(\([0-9]+\))?( UNSIGNED)?/i', $sColType, $aRegs)) {
            list(,$sType,, $bUnsigned) = array_pad($aRegs, 4, false);
            $sType = strtoupper($sType);
            $nOptions = pow(2, (8*$aBytes[$sType]))-1;
            if (!$bUnsigned) {
                // Signed columns.
                $nMin = -ceil($nOptions/2);
                $nMax = floor($nOptions/2);
            } else {
                $nMin = 0;
                $nMax = $nOptions;
            }

        } elseif (preg_match('/^DECIMAL\(([0-9]+),([0-9]+)\)/i', $sColType, $aRegs)) {
            $nMax = (int) str_repeat('9', ($aRegs[1] - $aRegs[2])) . '.' . str_repeat('9', $aRegs[2]);
            $nMin = -$nMax;

        } else {
            return false;
        }
    } else {
        return false;
    }

    return array($nMin, $nMax);
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

    if (PATH_COUNT == 3 && $_PE[0] == 'phenotypes' && $_PE[1] == 'disease') {
        // Disease-specific list of phenotypes; /phenotypes/disease/00001.
        return $_PE[2];
    } elseif (PATH_COUNT >= 3 && $_PE[0] == 'ajax') {
        // Ajax scripts often have IDs in the URLs.
        if (in_array($_PE[1], array('api_settings.php', 'auth_token.php'))) {
            return sprintf('%0' . $_SETT['objectid_length']['users'] . 'd', $_PE[2]);
        } elseif (PATH_COUNT == 4 && in_array($_PE[2], array('individual', 'user'))) {
            return sprintf('%0' . $_SETT['objectid_length'][$_PE[2] . 's'] . 'd', $_PE[3]);
        }

    } elseif (PATH_COUNT >= 2 && in_array($_PE[0], array('columns', 'references'))) {
        // For columns and references, the ID al all of $_PE.
        $sID = implode('/', array_slice($_PE, 1));
        if ($_PE[0] == 'references') {
            $sID = preg_replace('/\/image$/', '', $sID);
        }
        return $sID;
    } elseif (PATH_COUNT >= 2) {
        return $_PE[1]; // 0-padding has already been done in inc-init.php.
    }
    return false;
}





function lovd_getCurrentPageTitle ()
{
    // Generates the current page's title, fetching more information from the
    //  database, if necessary.
    global $_CONF, $_DB, $_PE;

    $ID = lovd_getCurrentID();
    $sObject = $_PE[0];

    // Start with the action, if any exists.
    $sTitle = ltrim(ACTION . ' ');
    if (!ACTION && PATH_COUNT == 1) {
        $sTitle = 'All ';
    } elseif (ACTION == 'add') {
        $sTitle = 'Add/enable ';
    } elseif (ACTION == 'authorize') {
        $sTitle = 'Authorize curators for ';
    } elseif (ACTION == 'confirmVariants') {
        $sTitle = 'Confirm variant entries with ';
    } elseif (ACTION == 'create') {
        $sTitle .= 'a new ';
    } elseif (ACTION == 'order') {
        $sTitle = 'Change order of ';
    } elseif (ACTION == 'search_global') {
        $sTitle = 'Search other public LOVDs for ';
    } elseif (ACTION == 'removeVariants') {
        $sTitle = 'Remove variant entries from ';
    } elseif (ACTION == 'sortCurators') {
        // FIXME: If this were "sort_curators", the code one block down
        //  would have handled it perfectly well.
        $sTitle = 'Sort curators for ';
    } elseif (ACTION == 'submissions') {
        $sTitle = 'Manage unfinished submissions for ';
    } elseif (strpos(ACTION, '_') !== false) {
        $sTitle = str_replace('_', ' ', $sTitle) . (!$ID? '' : 'for ');
    }

    // Custom column settings for genes and diseases.
    if (in_array($sObject, array('diseases', 'genes')) && PATH_COUNT >= 3 && $_PE[2] == 'columns') {
        if (PATH_COUNT == 3) {
            // View or resort column list.
            $sTitle .= 'custom data columns enabled for ';
        } else {
            $sColumnID = implode('/', array_slice($_PE, 3));
            $sTitle .= 'settings for the &quot;' . $sColumnID . '&quot; custom data column enabled for ';
        }
    }
    // Object name changes.
    if ($sObject == 'announcements') {
        $sTitle .= 'system ';
    } elseif ($sObject == 'columns') {
        $sTitle .= 'custom data ';
    } elseif ($sObject == 'links') {
        $sTitle .= 'custom ';
    } elseif ($sObject == 'logs') {
        $sTitle .= 'system ';
    } elseif ($sObject == 'references') {
        $sTitle .= 'data for ';
    }

    if (substr($sTitle, 0, 4) == 'All ') {
        $sTitle .= $sObject;
    } else {
        // Capitalize the first letter, trim off the last 's' from the data object.
        $sTitle = ucfirst($sTitle . substr($sObject, 0, -1));
    }

    if ($sObject == 'users' && ACTION != 'boot') {
        // This handles both "user" as well as "users", case-insensitively.
        $sTitle = str_replace('ser', 'ser account', $sTitle);
    } elseif (ACTION == 'create') {
        if ($sObject != 'announcements') {
            $sTitle .= ' entry';
        }
        // For a target?
        if (isset($_GET['target'])) {
            // $_GET['target'] should be checked already when we get here,
            //  but we take no chances.
            $ID = htmlspecialchars($_GET['target']);
            switch ($sObject) {
                case 'phenotypes':
                case 'screenings':
                    $sObject = 'individuals';
                    $sTitle .= ' for individual';
                    break;
                case 'variants':
                    $sObject = 'screenings';
                    $sTitle .= ' for screening';
                    break;
            }
        }
    }

    // Phenotype listings for diseases.
    if ($sObject == 'phenotypes' && PATH_COUNT == 3 && $_PE[1] == 'disease') {
        $sTitle .= 's for disease ';
        $sObject = 'diseases';
    }

    if ($ID) {
        // We're accessing just one entry.
        if ($sObject == 'genes') {
            if (!ACTION) {
                $sTitle = 'The ' . $ID . ' gene homepage';
            } else {
                $sTitle = preg_replace('/gene$/', ' the ' . $ID . ' gene', $sTitle);
            }
        } elseif (!ctype_digit($ID)) {
            if ($sObject == 'diseases') {
                $sTitle = 'Diseases with abbreviation ' . $ID;
                return $sTitle; // Stop further processing.
            } elseif ($sObject == 'individuals') {
                $sTitle = 'All ' . $sObject . ' with variants in gene ' . $ID;
            } elseif ($sObject == 'screenings') {
                $sTitle = 'All ' . $sObject . ' for gene ' . $ID;
            } elseif ($sObject == 'transcripts') {
                $sTitle = 'All ' . $sObject . ' active for the ' . $ID . ' gene';
            } else {
                $sTitle .= ' ' . $ID;
            }
        } else {
            $sTitle .= ' #' . $ID;
        }
    }

    // More annotation based on $_GET variables.
    if (!ACTION && !empty($_GET['search_created_by']) && ctype_digit($_GET['search_created_by'])) {
        $sObject = 'users';
        $ID = $_GET['search_created_by'];
        $sTitle .= ' created by user account #' . $ID;
    }

    if (!$ID) {
        return $sTitle;
    }

    // Add details, if available.
    switch ($sObject) {
        case 'announcements':
            $sPreview = lovd_shortenString($_DB->query('SELECT REPLACE(announcement, "\r\n", " ") FROM ' . TABLE_ANNOUNCEMENTS . '
                WHERE id = ?', array($ID))->fetchColumn(), 50);
            $sTitle .= ' ("' . $sPreview . '")';
            break;
        case 'columns':
            $sHeader = $_DB->query('SELECT head_column FROM ' . TABLE_COLS . '
                WHERE id = ?', array($ID))->fetchColumn();
            $sTitle .= ' (' . $sHeader . ')';
            break;
        case 'diseases':
            list($sName, $nOMIM) = $_DB->query('
                SELECT IF(CASE symbol WHEN "-" THEN "" ELSE symbol END = "", name, CONCAT(symbol, " (", name, ")")), id_omim
                FROM ' . TABLE_DISEASES . '
                WHERE id = ?', array($ID))->fetchRow();
            if ($sName) {
                $sTitle .= ' (' . $sName .
                    (!$nOMIM? '' : ', OMIM:' . $nOMIM) . ')';
            }
            break;
        case 'links':
            $sName = $_DB->query('SELECT name FROM ' . TABLE_LINKS . '
                WHERE id = ?', array($ID))->fetchColumn();
            $sTitle .= ' (' . $sName . ')';
            break;
        case 'transcripts':
            list($sNCBI, $sGene) =
                $_DB->query('
                    SELECT id_ncbi, geneid
                    FROM ' . TABLE_TRANSCRIPTS . '
                    WHERE id = ?', array($ID))->fetchRow();
            if ($sNCBI) {
                $sTitle .= ' (' . $sNCBI . ', ' . $sGene . ' gene)';
            }
            break;
        case 'users':
            // We have to take the user's level into account, so that we won't
            //  disclose information when people try random IDs!
            // lovd_isAuthorized() can produce false, 0 or 1. Accept 0 or 1.
            $bIsAuthorized = (lovd_isAuthorized('user', $ID, false) !== false);
            if ($bIsAuthorized) {
                list($sName, $sCity, $sCountry) =
                    $_DB->query('
                    SELECT u.name, u.city, c.name
                    FROM ' . TABLE_USERS . ' AS u
                      LEFT OUTER JOIN ' . TABLE_COUNTRIES . ' AS c ON (u.countryid = c.id)
                    WHERE u.id = ?',
                        array($ID))->fetchRow();
                if ($sName) {
                    $sTitle .= ' (' . $sName . ', ' . $sCity . (!$sCountry? '' : ', ' . $sCountry) . ')';
                }
            }
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
                      LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot_count ON (t.id = vot_count.transcriptid)
                    WHERE vog.id = ? AND (? = 1 OR vog.statusid >= ?)
                    GROUP BY vog.id, vot.transcriptid
                    ORDER BY COUNT(vot_count.id) DESC, t.id ASC',
                        array($ID, $bIsAuthorized, STATUS_MARKED))->fetchRow();
            if ($sVOG) {
                $sTitle .= ' (' . $sVOG . (!$sVOT? '' : ', ' . $sVOT) . ')';
            }
            break;
    }

    return htmlspecialchars($sTitle);
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
        if ($sFile[0] == '.') {
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
    // Parses the variant, and returns the position fields (2 for genomic
    //  variants, 4 for cDNA variants) in an associative array. If no
    //  positions can be found, the function returns false.
    // $sVariant stores the HGVS description of the variant.
    // $sTranscriptID stores the internal ID or NCBI ID of the transcript,
    //  as needed to process 3' UTR variants (such as c.*10del).
    // $bCheckHGVS holds the boolean which, if set to true, will change
    //  the functionality to return either true or false, depending on
    //  whether the variant matches the syntax of HGVS nomenclature.
    // If $bCheckHGVS is set to false, and any HGVS syntax issues are found,
    //  this information will be added to the response array in the form
    //  of warnings (if not fatal) or errors (when the syntax issues are
    //  such that they make the variant ambiguous or implausible).
    global $_DB, $_LIBRARIES;

    static $aTranscriptOffsets = array();
    $aResponse = array(
        // This array will store all the information which we will
        //  return to the user later on in this function.
        'position_start' => 0,
        'position_end'   => 0,
        'type'           => '',
        'range'          => false,
        'warnings'       => array(),
        'errors'         => array(),
    );

    // Trim the variant and remove whitespaces.
    $nLength = strlen($sVariant);
    $sVariant = preg_replace('/\s+/', '', $sVariant);
    if (strlen($sVariant) != $nLength) {
        // Whitespace was removed. Warn.
        if ($bCheckHGVS) {
            return false;
        }
        $aResponse['warnings']['WWHITESPACE'] =
            'This variant description contains one or more whitespace characters (spaces, tabs, etc). Please remove these.';
    }


    // Match the reference sequence if one was given.
    $sReferenceSequence = '';
    if (lovd_variantHasRefSeq($sVariant)) {
        // The user seems to have written down a reference sequence.
        // Let's see if it matches the expected format.
        list($sReferenceSequence, $sVariant) = explode(':', $sVariant, 2);

        if (lovd_isValidRefSeq($sReferenceSequence)) {
            // Check if the reference sequence matches one of
            //  the possible formats.
            if ($sTranscriptID) {
                // A transcript ID has been passed to this function.
                // We should check if it matches the transcript in the DNA field.
                $sField = (substr($sReferenceSequence, 0, 3) == 'ENS'? 'id_ensembl' : 'id_ncbi');
                if (is_numeric($sTranscriptID)) {
                    $sRefSeqID = $_DB->query('SELECT `' . $sField . '` FROM ' . TABLE_TRANSCRIPTS . ' WHERE id = ?',
                        array($sTranscriptID))->fetchColumn();
                } else {
                    $sRefSeqID = $sTranscriptID;
                }

                if (preg_match('/\b' . preg_quote($sRefSeqID) . '\b/', $sReferenceSequence)) {
                    // The transcript given in the DNA description is also the
                    //  transcript that we're using in LOVD for this variant.
                    $aResponse['warnings']['WTRANSCRIPTFOUND'] =
                        'A transcript reference sequence has been found in the DNA description. Please remove it.';
                } elseif (strpos($sRefSeqID, '.') && preg_match('/\b' . preg_quote(strstr($sRefSeqID, '.', true)) . '\.[0-9]+\b/', $sReferenceSequence)) {
                    // The transcript given in the DNA description is also the
                    //  transcript that we're using in LOVD for this variant,
                    //  but the version number is different.
                    $aResponse['warnings']['WTRANSCRIPTVERSION'] =
                        'The transcript reference sequence found in the DNA description is a different version from the configured transcript.' .
                        ' Please adapt the DNA description to the configured transcript and then remove the reference sequence from the DNA field.';
                } else {
                    // This is an actual problem; the submitter used a different
                    //  refseq than the transcript configured in LOVD.
                    $aResponse['warnings']['WDIFFERENTREFSEQ'] =
                        'The reference sequence found in the DNA description does not match the configured transcript.' .
                        ' Please adapt the DNA description to the configured transcript and then remove the reference sequence from the DNA field.';
                }
            }

            $aPrefixesByRefSeq = lovd_getVariantPrefixesByRefSeq($sReferenceSequence);
            if (!in_array($sVariant[0], $aPrefixesByRefSeq)) {
                // Check whether the DNA type of the variant matches the DNA type of the reference sequence.
                if ($bCheckHGVS) {
                    return false;
                }
                $aResponse['errors']['EWRONGREFERENCE'] =
                    'The given reference sequence (' . $sReferenceSequence . ') does not match the DNA type (' . $sVariant[0] . ').' .
                    ' For variants on ' . $sReferenceSequence . ', please use the ' . implode('. or ', $aPrefixesByRefSeq) . '. prefix.';
                switch ($sVariant[0]) {
                    case 'c':
                    case 'n':
                        $aResponse['errors']['EWRONGREFERENCE'] .=
                            ' For ' . $sVariant[0] . '. variants, please use a ' . ($sVariant[0] == 'c'? '' : 'non-') . 'coding transcript reference sequence.';
                        break;
                    case 'g':
                    case 'm':
                        $aResponse['errors']['EWRONGREFERENCE'] .=
                            ' For ' . $sVariant[0] . '. variants, please use a ' . ($sVariant[0] == 'g'? 'genomic' : 'mitochondrial') . ' reference sequence.';
                        break;
                }

            } elseif (!preg_match('/^(N[CGTW]|LRG)/', $sReferenceSequence)
                && (preg_match('/[0-9]+[-+]([0-9]+|\?)/', $sVariant))) {
                // If a variant has intronic positions, it must have a
                //  reference that contains those positions.
                if ($bCheckHGVS) {
                    return false;
                }
                $aResponse['errors']['EWRONGREFERENCE'] =
                    'The variant is missing a genomic reference sequence required to verify the intronic positions.';
            }

        } else {
            // The user seems to have tried to add a reference sequence, but it
            //  was not formatted correctly. We will return errors or warnings accordingly.
            if ($bCheckHGVS) {
                return false;
            }
            // Check for missing version. We don't want to yet define another pattern.
            // Just check if it helps to add a version number.
            if (lovd_isValidRefSeq(preg_replace('/([0-9]{6})([()]|$)/', '$1.1$2', $sReferenceSequence))) {
                // OK, adding a .1 helped. So, version is missing.
                $aResponse['errors']['EREFERENCEFORMAT'] =
                    'The reference sequence is missing the required version number.' .
                    ' NCBI RefSeq and Ensembl IDs require version numbers when used in variant descriptions.';

            } elseif (preg_match('/^([NX][MR]_[0-9]{6,9}\.[0-9]+)\((N[CGTW]_[0-9]{6}\.[0-9]+)\)$/', $sReferenceSequence, $aRegs)) {
                $aResponse['warnings']['WREFERENCEFORMAT'] =
                    'The genomic and transcript reference sequences have been swapped.' .
                    ' Please rewrite "' . $aRegs[0] . '" to "' . $aRegs[2] . '(' . $aRegs[1] . ')".';

            } elseif (preg_match('/^([NX][CGMRTW])([0-9]+)/', $sReferenceSequence, $aRegs)) {
                // The user forgot the underscore.
                $aResponse['warnings']['WREFERENCEFORMAT'] =
                    'NCBI reference sequences require an underscore between the prefix and the numeric ID.' .
                    ' Please rewrite "' . $aRegs[0] . '" to "' . $aRegs[1] . '_' . $aRegs[2] . '".';

            } else {
                $aResponse['errors']['EREFERENCEFORMAT'] =
                    'The reference sequence could not be recognised.' .
                    ' Supported reference sequence IDs are from NCBI Refseq, Ensembl, and LRG.';
            }
        }
    }
    // Preliminary determination of a range. This can have false positives, like g.1delins100_200,
    //  but once we have split off the suffix, we'll try again.
    $aResponse['range'] = (strpos($sVariant, '_') !== false);


    // All information of interest will be placed into an associative array.
    // Note: For now, the regular expression only works for c., g., n., and m. variants.
    preg_match(
        '/^([cgmn])\.' .                         // 1.  Prefix.

        '([?=]$|(' .                             // 2. '?' or '=' (e.g. c.=).
        '(\({1,2})?' .              // 4=(       // 4.  Opening parentheses.
        '([-*]?[0-9]+|\?)' .                     // 5.  (Earliest) start position.
        '([-+]([0-9]+|\?))?' .                   // 6.  (Earliest) intronic start position.
        '(?(4)(_' .
            '([-*]?[0-9]+|\?)' .                 // 9. Latest start position.
            '([-+]([0-9]+|\?))?' .               // 10. Latest intronic start position.
        '\))?)' .

        '(_' .
            '(\()?' .               // 13=(
            '([-*]?[0-9]+|\?)' .                 // 14. (Earliest) end position.
            '([-+]([0-9]+|\?))?' .               // 15. (Earliest) intronic end position.
            '(?(13)_' .
                '([-*]?[0-9]+|\?)' .             // 17. Latest end position.
                '([-+]([0-9]+|\?))?' .           // 18. Latest intronic end position.
        '\)))?' .

        '((?:[ACGTU]+|\.)>(?:[ACGTRYSWKMBDHUVN]+|\.)' .      //  | (substitution)
        '|([ACGTU]+\[[0-9]+])+' .                            //  | (repeat sequence)
        '|[ACGTU]*=(\/{1,2}[ACGTU]*>[ACGTRYSWKMBDHUVN]+)?' . //  | (wild types, mosaics, or chimerics)
        '|ins|dup|con|delins|del|inv|sup|\?' .               //  V
        '|\|(gom|lom|met=|.+))' .                            // 20. Type of variant.

        '(.*)))/i',                                          // 24. Suffix.

        $sVariant, $aMatches);

    $aVariant = (!isset($aMatches[0])? array() : array(
        // All information of the variant is stored into this associative array.
        // Notes: -If the information was not found, the positions are cast to 0
        //         and the variant type, parentheses, and suffix, are cast to an
        //         empty string. (e.g. c.?)
        //        -If an intronic position is given a question mark, its position
        //         is cast to 1 in case of +? and -1 for -?. (e.g. c.10-?del)
        'complete'                => $aMatches[0],
        'prefix'                  => (!isset($aMatches[1])?  '' : strtolower($aMatches[1])),
        'positions'               => (!isset($aMatches[3])?  '' : $aMatches[3]),
        'starting_parentheses'    => (!isset($aMatches[4])?  '' : $aMatches[4]), // The parentheses are given to make additional checks later on in the function easier.
        'earliest_start'          => (!isset($aMatches[5])?   0 : $aMatches[5]), // These are not cast to integers, since they can still hold an informative '*'.
        'earliest_intronic_start' => (!isset($aMatches[6])?   0 : (int) str_replace('?', '1', $aMatches[6])),
        'latest_start'            => (!isset($aMatches[9])?   0 : $aMatches[9]),
        'latest_intronic_start'   => (!isset($aMatches[10])?  0 : (int) str_replace('?', '1', $aMatches[10])),
        'earliest_end'            => (!isset($aMatches[14])?  0 : $aMatches[14]),
        'earliest_intronic_end'   => (!isset($aMatches[15])?  0 : (int) str_replace('?', '1', $aMatches[15])),
        'latest_end'              => (!isset($aMatches[17])?  0 : $aMatches[17]),
        'latest_intronic_end'     => (!isset($aMatches[18])?  0 : (int) str_replace('?', '1', $aMatches[18])),
        'type'                    => (!isset($aMatches[20])? '' :
            (preg_match('/(^[ACTG]*=|[>\[])/i', $aMatches[20])? strtoupper($aMatches[20]) : strtolower($aMatches[20]))),
        'suffix'                  => (!isset($aMatches[24])? '' : $aMatches[24]),
    ));

    // Doing this here, to show we use $aMatches and that this code should be updated if the regexp is updated.
    // Check for "0" in positions. We need to do this on $aMatches, because no type casting has taken place there.
    $aZeroValues = array('0', '-0', '+0');
    foreach (array(5, 6, 9, 10, 14, 15, 17, 18) as $i) {
        if (isset($aMatches[$i])) {
            if (in_array($aMatches[$i], $aZeroValues)) {
                $aResponse['errors']['EPOSITIONFORMAT'] =
                    'This variant description contains an invalid position: "0". Please verify your description and try again.';
                break;
            } else {
                foreach ($aZeroValues as $sZeroValue) {
                    if (substr($aMatches[$i], 0, strlen($sZeroValue)) === $sZeroValue) {
                        // Stack warnings, so all problems are highlighted.
                        $aResponse['warnings']['WPOSITIONFORMAT'] =
                            (isset($aResponse['warnings']['WPOSITIONFORMAT'])?
                                $aResponse['warnings']['WPOSITIONFORMAT'] : 'Variant positions should not be prefixed by a 0.') .
                            ' Please rewrite "' . $aMatches[$i] . '" to "' .
                            ($sZeroValue[0] == '+'? '+' : '') . (int) $aMatches[$i] . '".';
                    }
                }
            }
        }
    }
    if ($bCheckHGVS
        && (isset($aResponse['errors']['EPOSITIONFORMAT']) || isset($aResponse['warnings']['WPOSITIONFORMAT']))) {
        return false;
    }

    if (!isset($aVariant['complete']) || $aVariant['complete'] != $sVariant) {
        // If the complete match is not set or does not equal the given variant,
        //  then the variant is not HGVS-compliant, and we cannot extract any
        //  information.
        if ($bCheckHGVS) {
            return false;
        }

        // Before we just return false when people request more information;
        //  check for some currently unsupported syntax that we do recognize.

        // 1) "Or" syntax using a ^.
        if (strpos($sVariant, '^') !== false) {
            // This is a stub, but it's better than nothing.
            // We replace the ^ and everything that follows with a =, and
            //  process the variant like this. Then we overwrite the type, and
            //  we return what we have.
            // Note that variants like g.123A>C^124G>C don't reach us; they are
            //  matched and caught elsewhere.
            $aVariant = lovd_getVariantInfo(strstr($sVariant, '^', true) . '=');
            if ($aVariant !== false) {
                $aVariant['type'] = '^';
                // We have to throw an ENOTSUPPORTED, although we're returning
                //  positions. We currently cannot claim these are HGVS or not,
                //  so an WNOTSUPPORTED isn't appropriate.
                $aVariant['errors']['ENOTSUPPORTED'] =
                    'Currently, variant descriptions using "^" are not yet supported.' .
                    ' This does not necessarily mean the description is not valid HGVS.';
                return $aVariant;
            }
        }

        // 2) Combined variants that should be split.
        if (preg_match('/\[.+;.+\]/', $sVariant)) {
            // Although insertions can have this pattern as well, they don't end
            //  up here; so we're left with combined variants.
            // Try to send in the first one.
            $aVariant = lovd_getVariantInfo(
                str_replace(array('[', ']'), '', strstr($sVariant, ';', true)));
            if ($aVariant !== false) {
                $aVariant['type'] = ';';
                // We have to throw an ENOTSUPPORTED, although we're returning
                //  positions. We currently cannot claim these are HGVS or not,
                //  so an WNOTSUPPORTED isn't appropriate.
                $aVariant['errors']['ENOTSUPPORTED'] =
                    'Currently, variant descriptions of combined variants are not yet supported.' .
                    ' This does not necessarily mean the description is not valid HGVS.' .
                    ' Please submit your variants separately.';
                // Some descriptions throw some warnings.
                $aVariant['warnings'] = array();
                return $aVariant;
            }
        }

        // 3) qter/pter/cen-based positions, translocations, fusions.
        foreach (array('qter', 'pter', 'cen', '::') as $sUnsupported) {
            if (strpos($sVariant, $sUnsupported)) {
                $aResponse['errors']['ENOTSUPPORTED'] =
                    'Currently, variant descriptions using "' . $sUnsupported . '" are not yet supported.' .
                    ' This does not necessarily mean the description is not valid HGVS.';

                // We do have one requirement; chromosomal reference sequence.
                if ($sReferenceSequence && substr($sReferenceSequence, 0, 2) != 'NC') {
                    $aResponse['errors']['EWRONGREFERENCE'] =
                        'The variant is missing a chromosomal reference sequence required for pter, cen, or qter positions.';
                }
                return $aResponse;
            }
        }

        // 4) Methylation-related variants without a pipe.
        // We'll check for methylation-related variants here, that sometimes
        //  lack a pipe character. Since we currently can't parse positions
        //  anymore, we'll have to throw an error. If we can identify the user's
        //  mistake, we can ask the user or lovd_fixHGVS() to correct it.
        if (preg_match('/[0-9](gom|lom|met=|bsrC?)$/', $sVariant, $aRegs)) {
            // Variant ends in a methylation-related suffix, but without a pipe.
            // We can guess here that this can be fixed.
            $aResponse['errors']['EPIPEMISSING'] =
                'Please place a "|" between the positions and the variant type (' . $aRegs[1] . ').';
            return $aResponse;
        }
        return false;
    }

    // Clean position string. We'll use it for reporting later on.
    if ($aVariant['positions']) {
        $aVariant['positions'] = strstr($aVariant['positions'], $aVariant['type'], true);
        // And now with more precision.
        $aResponse['range'] = (strpos($aVariant['positions'], '_') !== false);
    }

    // Check the variant's case.
    // First, handle an annoying exception.
    if (substr($aVariant['type'], -4) == 'bsrc') {
        $aVariant['type'] = str_replace('bsrc', 'bsrC', $aVariant['type']);
    }
    // Now check.
    if ((isset($aMatches[1]) && $aVariant['prefix'] != $aMatches[1])
        || (isset($aMatches[20]) && $aVariant['type'] != $aMatches[20])) {
        // There's a case problem.
        if ($bCheckHGVS) {
            return false;
        }
        $aResponse['warnings']['WWRONGCASE'] =
            'This is not a valid HGVS description, due to characters being in the wrong case.' .
            ' Please check the use of upper- and lowercase characters.';
    }

    // Storing the variant type.
    if (!$aVariant['type']) {
        // If no type was matched, we can be sure that the variant is either
        //  a full wild type or a full unknown variant; so either g.= or g.? .
        // In this case, we do not need to go over all tests, since there is
        //  simply a lot less information to test. We will do a few tests
        //  and add all necessary information, and then return our response
        //  right away.
        if (in_array($aVariant['prefix'], array('c', 'n'))) {
            // Initialize intronic positions, set to zero for .? or .= variants.
            $aResponse['position_end_intron'] = 0;
            $aResponse['position_start_intron'] = 0;
        }
        // For unknown variants (c.?), the type is set to NULL.
        $aResponse['type'] = (substr($aVariant['complete'], -1) == '='? '=' : NULL);

        if ($aResponse['type'] == '=') {
            // HGVS requires unchanged sequence ("=") to always give positions.
            $aResponse['errors']['EMISSINGPOSITIONS'] =
                'When using "=", please provide the position(s) that are unchanged.';
            return ($bCheckHGVS? false : $aResponse);
        }
        return ($bCheckHGVS? true : $aResponse);

    } elseif ($aVariant['type'][0] == '|') {
        // There might be variant types which some users would like to see
        //  being added to HGVS, but are not yet, e.g. the "bsr" and "per" types.
        // We want lovd_getVariantInfo() to still make an effort to read these
        //  variants, so we can extract as much information from them as
        //  possible (such as the positions and other warnings that might
        //  have occurred). This is an error, not a warning, since it means
        //  that the variant is theoretically incorrect and not fixable.
        if (in_array($aVariant['type'], array('|gom', '|lom', '|met='))) {
            $aResponse['warnings']['WNOTSUPPORTED'] =
                'Although this variant is a valid HGVS description, this syntax is currently not supported for mapping and validation.';
        } else {
            if ($bCheckHGVS) {
                return false;
            }
            $aResponse['errors']['ENOTSUPPORTED'] = 'This is not a valid HGVS description, please verify your input after "|".';
        }
        $aResponse['type'] = 'met';

    } elseif (strpos($aVariant['type'], '=') !== false) {
        if (substr_count($sVariant, '/') == 1) {
            $aResponse['type'] = 'mosaic';
        } elseif (substr_count($sVariant, '/') == 2) {
            $aResponse['type'] = 'chimeric';
        } else {
            $aResponse['type'] = '=';
        }

    } elseif (strpos($aVariant['type'], '>')) {
        $aResponse['type'] = 'subst';

    } elseif ($aVariant['type'] == 'con') {
        if ($bCheckHGVS) {
            return false;
        }
        $aResponse['type'] = 'delins';
        $aResponse['warnings']['WWRONGTYPE'] =
            'A conversion should be described as a deletion-insertion. Please rewrite "con" to "delins".';

    } elseif (substr($aVariant['type'], -1) == ']') {
        $aResponse['type'] = 'repeat';
        $aResponse['warnings']['WNOTSUPPORTED'] =
            'Although this variant is a valid HGVS description, this syntax is currently not supported for mapping and validation.';

    } elseif ($aVariant['type'] == '?') {
        $aResponse['type'] = NULL;

    } else {
        $aResponse['type'] = $aVariant['type'];
    }



    // If given, check if we already know this transcript.
    if ($sTranscriptID === false || !$_DB) {
        // If the transcript ID is passed as false, we are asked to ignore not
        //  having the transcript. Pick some random number, high enough to not
        //  be smaller than position_start if that's not in the UTR.
        // Also, we take this default when we're unit testing and thus don't
        //  have a database connection.
        $aTranscriptOffsets[$sTranscriptID] = 1000000;

    } elseif ($sTranscriptID && !isset($aTranscriptOffsets[$sTranscriptID])) {
        $aTranscriptOffsets[$sTranscriptID] = $_DB->query('SELECT position_c_cds_end FROM ' . TABLE_TRANSCRIPTS . ' WHERE (id = ? OR id_ncbi = ?)',
            array($sTranscriptID, $sTranscriptID))->fetchColumn();
        if (!$aTranscriptOffsets[$sTranscriptID]) {
            // The transcript is not configured correctly. We will treat this transcript as unknown.
            $sTranscriptID = '';
        }
    }



    // Converting 3' UTR notations ('*' in the position fields) to normal notations,
    //  checking for '?', and disallowing negative positions for prefixes other than c.
    foreach (array('earliest_start', 'latest_start', 'earliest_end', 'latest_end') as $sPosition) {
        if (substr($aVariant[$sPosition], 0, 1) == '*') {
            if ($aVariant['prefix'] != 'c') {
                //  If the '*' is given, the DNA must be of type coding (prefix = c).
                if ($bCheckHGVS) {
                    return false;
                }
                $aResponse['errors']['EFALSEUTR'] =
                    'Only coding transcripts (c. prefix) have a UTR region. Therefore, position "' . $aVariant[$sPosition] .
                    '" which describes a position in the 3\' UTR, is invalid when using the "' . $aVariant['prefix'] . '" prefix.';
                return $aResponse;
            }
            if ($sTranscriptID === '') {
                // If the '*' symbol is given, we must also have a transcript.
                // This mistake does not lie with the user, but with LOVD as the function should not have
                //  been called with a transcriptID or transcriptID=false.
                return false;
            }
            // We add the length of the transcript to the position if a '*' has been found.
            $aVariant[$sPosition] = substr($aVariant[$sPosition], 1) + $aTranscriptOffsets[$sTranscriptID];

        } elseif ($aVariant[$sPosition] == '?') {
            $aResponse['messages']['IUNCERTAINPOSITIONS'] = 'This variant description contains uncertain positions.';

        } else {
            // When no '*' or '?' is found, we can safely cast the position to integer.
            $aVariant[$sPosition] = (int) $aVariant[$sPosition];

            if ($aVariant[$sPosition] < 0 && $aVariant['prefix'] != 'c') {
                if ($bCheckHGVS) {
                    return false;
                }
                $aResponse['errors']['EFALSEUTR'] =
                    'Only coding transcripts (c. prefix) have a UTR region. Therefore, position "' . $aVariant[$sPosition] .
                    '" which describes a position in the 5\' UTR, is invalid when using the "' . $aVariant['prefix'] . '" prefix.';
                return $aResponse;
            }
        }
    }



    // Making sure that all early positions are bigger than the later positions
    //  and that all start positions are bigger than the end positions.
    foreach (
        array(
             array('earliest_start',   'latest_start'),
             array('earliest_end',     'latest_end'),
             array('earliest_start',   'earliest_end'),
             array('latest_start',     'earliest_end'),
             array('latest_start',     'latest_end')
        ) as $aFirstAndLast) {

        if ($aVariant[$aFirstAndLast[0]] && $aVariant[$aFirstAndLast[1]]
            && $aVariant[$aFirstAndLast[0]] != '?'
            && $aVariant[$aFirstAndLast[1]] != '?') {
            // We only check the positions if neither are unknown.
            list($sFirst, $sLast) = $aFirstAndLast;
            $sIntronicFirst = str_replace('_', '_intronic_', $sFirst);
            $sIntronicLast  = str_replace('_', '_intronic_', $sLast);

            if ($aVariant[$sFirst] > $aVariant[$sLast]) {
                // Switch positions.
                list($aVariant[$sFirst], $aVariant[$sIntronicFirst], $aVariant[$sLast], $aVariant[$sIntronicLast]) =
                    array($aVariant[$sLast], $aVariant[$sIntronicLast], $aVariant[$sFirst], $aVariant[$sIntronicFirst]);
                $sPositionWarning = 'The positions are not given in the correct order.';

            } elseif ($aVariant[$sFirst] == $aVariant[$sLast]) {
                // Positions are the same. Now compare intronic positions.
                // Intronic position fields are always defined, so we can safely
                //  compare them.
                if ($aVariant[$sIntronicFirst] > $aVariant[$sIntronicLast]) {
                    list($aVariant[$sIntronicFirst], $aVariant[$sIntronicLast]) = array($aVariant[$sIntronicLast], $aVariant[$sIntronicFirst]);
                    $sPositionWarning = 'The intronic positions are not given in the correct order.';

                } elseif ($aVariant[$sIntronicFirst] == $aVariant[$sIntronicLast]
                    && !(
                        $aVariant['earliest_start'] && $aVariant['earliest_start']
                        && $aVariant['earliest_start'] && $aVariant['earliest_start']
                        && $sFirst == 'latest_start' && $sLast == 'earliest_end'
                    )) {
                    // The intronic offset is also the same (or both 0).
                    // There is an exception; variants with four positions can
                    //  have the same middle position. This should be allowed.
                    if ($bCheckHGVS) {
                        return false;
                    }
                    $sPositionWarning = 'This variant description contains two positions that are the same.';
                    if ($aVariant['type'] == 'ins') {
                        // Insertions must receive the two neighboring positions
                        //  between which they have taken place.
                        // If both positions are the same, this makes the variant
                        //  unclear to the extent that it cannot be interpreted.
                        $aResponse['errors']['EPOSITIONFORMAT'] = $sPositionWarning .
                            ' Please verify your description and try again.';
                        break;
                    }
                }
            }

            if (isset($sPositionWarning)) {
                if ($bCheckHGVS) {
                    return false;
                }
                // NOTE: This overwrites any previous warnings. Both warnings generated in the beginning (positions that
                //  are, or are prefixed with, 0) and warnings generated in the code directly above.
                $aResponse['warnings']['WPOSITIONFORMAT'] = $sPositionWarning .
                    ' Please verify your description and try again.';
            }
        }
    }



    // Storing the positions.
    // After discussing the issue, it is decided to use to inner positions in cases where the positions are
    //  unknown. This means that e.g. c.(1_2)_(5_6)del will be returned as having a position_start of 2, and
    //  a position_end of 5. However, if we find a variant such as c.(1_?)_(?_6)del, we will save the outer
    //  positions (so a position_start of 1 and a position_end of 6).
    // Remember: When there are no parentheses, only earliest_start and earliest_end are set.
    //           Not having an earliest_end, means there was only one position set or one range with parentheses.
    //           Having one range with parentheses, sets the earliest and latest start positions.
    $aResponse['position_start'] =
        (!$aVariant['latest_start'] || $aVariant['latest_start'] == '?' || !$aVariant['earliest_end']?
            $aVariant['earliest_start'] : $aVariant['latest_start']);

    if (!$aVariant['earliest_end']) {
        if ($aVariant['latest_start']) {
            // Not having an end, but having a latest start happens for variants like c.(100_200)del(10).
            $aResponse['position_end'] = $aVariant['latest_start'];
        } else {
            // Single-position variants.
            $aResponse['position_end'] = $aResponse['position_start'];
        }
    } elseif ($aVariant['earliest_end'] != '?' || !$aVariant['latest_end']) {
        // Earliest end is not unknown, or simply the only choice we have.
        $aResponse['position_end'] = $aVariant['earliest_end'];
    } else {
        $aResponse['position_end'] = $aVariant['latest_end'];
    }

    if (in_array($aVariant['prefix'], array('n', 'c'))) {
        $aResponse['position_start_intron'] = ($aVariant['latest_start']? $aVariant['latest_intronic_start'] : $aVariant['earliest_intronic_start']);
        $aResponse['position_end_intron']   = ($aVariant['earliest_end']? $aVariant['earliest_intronic_end'] : $aResponse['position_start_intron']);
    }

    if (!$aVariant['earliest_end'] && $aVariant['latest_start']) {
        // We now know we are dealing with a case such as g.(1_5)ins. This means
        //  that the positions are uncertain, but somewhere within the range as
        //  given within the parentheses. We add a message to make sure users
        //  know our interpretation and can make sure they meant it as such.
        // Note that IPOSITIONRANGE, IUNCERTAINPOSITIONS, and IUNCERTAINRANGE all send
        //  the same message to the user about uncertain positions. However, internally,
        //  this notice is used to determine whether the variant needs a suffix
        //  because the variant's position is a single, uncertain range.
        $aResponse['messages']['IPOSITIONRANGE'] = 'This variant description contains uncertain positions.';

        if (in_array($aVariant['prefix'], array('n', 'c'))) {
            $aResponse['position_start_intron'] = $aVariant['earliest_intronic_start'];
        }

    } elseif ($aVariant['earliest_end']
        && ($aVariant['latest_start'] || $aVariant['latest_end'])) {
        // Another class of unknown positions;
        // g.(1_5)_(10_15) OR g.5_(10_15) OR g.(1_5)_10.
        // We'll store the inner positions, but it's good to know that there is
        //  uncertainty.
        $aResponse['messages']['IUNCERTAINRANGE'] = 'This variant description contains uncertain positions.';
    }





    // Now check the syntax of the variant in detail.

    // Making sure intronic positions are only given for variants which can hold them.
    if (($aVariant['earliest_intronic_start'] || $aVariant['latest_intronic_start']
        || $aVariant['earliest_intronic_end'] || $aVariant['latest_intronic_end'])
        && !in_array($aVariant['prefix'], array('c', 'n'))) {
        if ($bCheckHGVS) {
            return false;
        }
        $aResponse['errors']['EFALSEINTRONIC'] =
            'Only transcripts (c. or n. prefixes) have introns.' .
            ' Therefore, this variant description with a position in an intron' .
            ' is invalid when using the "' . $aVariant['prefix'] . '" prefix.';
        if (strpos($sVariant, '-') && !strpos($sVariant, '_')) {
            $aResponse['errors']['EFALSEINTRONIC'] .=
                ' Did you perhaps try to indicate a range?' .
                ' If so, please use an underscore (_) to indicate a range.';
        }
        // Before we return this, also add the intronic positions. This'll
        //  allow us to make some guesstimate on whether or not this may
        //  have been a typo.
        $aResponse['position_start_intron'] = ($aVariant['latest_start']? $aVariant['latest_intronic_start'] : $aVariant['earliest_intronic_start']);
        $aResponse['position_end_intron']   = ($aVariant['earliest_end']? $aVariant['earliest_intronic_end'] : $aResponse['position_start_intron']);
        if (!$aVariant['earliest_end'] && $aVariant['latest_start']) {
            $aResponse['position_start_intron'] = $aVariant['earliest_intronic_start'];
        }
        return $aResponse;
    }

    // Making sure wild type descriptions don't provide nucleotides
    // (e.g. c.123A=, which should be c.123=).
    if ($aResponse['type'] == '=' && preg_match('/[ACGT]/', $sVariant)) {
        if ($bCheckHGVS) {
            return false;
        }
        $aResponse['warnings']['WBASESGIVEN'] = 'When using "=", please remove the original sequence before the "=".';
    }

    // Making sure no redundant '?'s are given as positions.
    if (strpos($aVariant['positions'], '?') !== false) {
        // Let's try to keep this simple. There's so many combinations,
        //  why not just work on strings?
        $sFixedPosition = str_replace(
            array(
                '(?_?)',
                '_?)_(?_',
                '?_?',
                '?)_?',
                '?_(?',
            ),
            array(
                '?',
                '_',
                '?',
                '?)',
                '(?',
            ),
            $aVariant['positions']
        );
        // Exception; ?_? should be allowed for ins variants (g.?_?ins[...]).
        if ($sFixedPosition == '?' && $aVariant['type'] == 'ins') {
            $sFixedPosition = '?_?';
        }
        if ($aVariant['positions'] != $sFixedPosition) {
            $sQuestionMarkWarning =
                'Please rewrite the positions ' . $aVariant['positions'] .
                ' to ' . $sFixedPosition . '.';

            if ($bCheckHGVS) {
                return false;
            }
            $aResponse['warnings']['WTOOMUCHUNKNOWN'] =
                'This variant description contains redundant question marks. ' .
                $sQuestionMarkWarning;
        }
    }



    // Checking all type-specific format requirements.
    if ($aVariant['type'] == 'delins' && strlen($aVariant['suffix']) == 1
        && !$aVariant['earliest_end'] && lovd_getVariantLength($aResponse) == 1) {
        // If an insertion/deletion deletes one base and replaces it by one, it
        //  should be called and formatted as a substitution.
        if ($bCheckHGVS) {
            return false;
        }
        $aResponse['warnings']['WWRONGTYPE'] =
            'A deletion-insertion of one base to one base should be described as a substitution.';

    } elseif ($aVariant['type'] == 'ins') {
        if (!($aVariant['earliest_start'] == '?' || $aVariant['latest_start'] || $aVariant['earliest_end'])) {
            // An insertion must always hold two positions: so it must have an earliest end
            // (c.1_2insA) or a latest start (c.(1_5)insA). That is: except if the variant
            // was given as c.?insA.
            if ($bCheckHGVS) {
                return false;
            }
            $aResponse['errors']['EPOSITIONMISSING'] =
                'An insertion must be provided with the two positions between which the insertion has taken place.';

        } elseif ($aVariant['latest_end'] || ($aVariant['latest_start'] && $aVariant['earliest_end'])) {
            // An insertion should not get more than two positions: so it should not
            //  have a latest end (c.1_(2_5)insA) or a latest start and earliest end
            //  (c.(1_5)_6insA.
            if ($bCheckHGVS) {
                return false;
            }
            $aResponse['errors']['EPOSITIONFORMAT'] = 'Insertions should not be given more than two positions.';

        } elseif ($aVariant['earliest_start'] && $aVariant['earliest_end']
            && $aVariant['earliest_start'] != '?' && $aVariant['earliest_end'] != '?') {
            // An insertion must always get two positions which are next to each other,
            //  since the inserted nucleotides will be placed in the middle of those.
            // Calculate the length of the variant properly, including intronic positions.
            $nLength = lovd_getVariantLength($aResponse);
            if (!$nLength || $nLength > 2) {
                if ($bCheckHGVS) {
                    return false;
                }
                $aResponse['errors']['EPOSITIONFORMAT'] =
                    'An insertion must have taken place between two neighboring positions. ' .
                    'If the exact location is unknown, please indicate this by placing parentheses around the positions.';
            }

        } elseif (isset($aResponse['messages']['IPOSITIONRANGE'])
            && $aVariant['earliest_start'] != '?' && $aVariant['latest_start'] != '?') {
            // If the exact location of an insertion is unknown, this can be indicated
            //  by placing the positions in the range-format (e.g. c.(1_10)insA). In this
            //  case, the two positions should not be neighbours, since that would imply that
            //  the position is certain.
            // Calculate the length of the variant properly, including intronic positions.
            $nLength = lovd_getVariantLength($aResponse);
            if ($nLength == 2) {
                if ($bCheckHGVS) {
                    return false;
                }
                $aResponse['errors']['EPOSITIONFORMAT'] =
                    'The two positions do not indicate a range longer than two bases.' .
                    ' Please remove the parentheses if the positions are certain.';
            }
        }

    } elseif ($aVariant['type'] == 'inv') {
        if (!isset($aResponse['messages']['IUNCERTAINPOSITIONS'])
            && !($aVariant['latest_start'] && $aVariant['earliest_end'])
            && lovd_getVariantLength($aResponse) == 1) {
            // An inversion must always have a length of more than one, unless
            //  an uncertain range has been provided; then the calculated length
            //  could be one while in reality, it's unknown. The exact
            //  combination of a latest start and an earliest end is therefore
            //  excluded; these are g.(A_B)_(C_D)inv variants.
            if ($bCheckHGVS) {
                return false;
            }
            $aResponse['errors']['EPOSITIONFORMAT'] =
                'Inversions require a length of at least two bases.';

        } elseif (isset($aResponse['messages']['IPOSITIONRANGE'])
            && !isset($aResponse['messages']['IUNCERTAINPOSITIONS'])
            && lovd_getVariantLength($aResponse) == 2) {
            // If the exact location of an inversion is unknown, this can be
            //  indicated by placing the positions in the range-format (e.g.
            //  c.(1_10)inv). In this case, the two positions should not be
            //  neighbours, since that would imply that the position is certain.
            if ($bCheckHGVS) {
                return false;
            }
            $aResponse['errors']['EPOSITIONFORMAT'] =
                'The two positions do not indicate a range longer than two bases.' .
                ' Please remove the parentheses if the positions are certain.';
        }

    } elseif ($aResponse['type'] == 'subst') {
        $aSubstitution = explode('>', $aVariant['type']);
        if ($aSubstitution[0] == '.' && $aSubstitution[1] == '.') {
            if ($bCheckHGVS) {
                return false;
            }
            $aResponse['errors']['EWRONGTYPE'] =
                'This substitution does not seem to contain any data. Please provide bases that were replaced.';

        } elseif ($aSubstitution[0] == '.') {
            if ($bCheckHGVS) {
                return false;
            }
            $aResponse['errors']['EWRONGTYPE'] =
                'A substitution should be a change of one base to one base. Did you mean to describe an insertion?';

        } elseif ($aSubstitution[1] == '.') {
            if ($bCheckHGVS) {
                return false;
            }
            $aResponse['warnings']['WWRONGTYPE'] =
                'A substitution should be a change of one base to one base. Did you mean to describe a deletion?';

        } elseif (strlen($aSubstitution[0]) > 1 || strlen($aSubstitution[1]) > 1) {
            // A substitution should be a change of one base to one base. If this
            //  is not the case, we will let the user know that it should have been
            //  a delins.
            if ($bCheckHGVS) {
                return false;
            }
            $aResponse['warnings']['WWRONGTYPE'] =
                'A substitution should be a change of one base to one base. Did you mean to describe a deletion-insertion?';
        }
        if ($aVariant['earliest_end']) {
            // As substitutions are always a one-base change, they should
            //  only receive one positions (so the end position should be empty).
            if ($bCheckHGVS) {
                return false;
            }
            if ($aVariant['earliest_start'] != $aVariant['earliest_end']) {
                // If the two positions are not the same, the variant is not fixable.
                $aResponse['errors']['ETOOMANYPOSITIONS'] =
                    'Too many positions are given; a substitution is used to only indicate single-base changes and therefore should have only one position.';
            }
        }
        if (isset($aResponse['messages']['IPOSITIONRANGE'])) {
            // VV won't support this... although we'll allow c.(100_101)A>G.
            $aResponse['warnings']['WNOTSUPPORTED'] =
                'Although this variant is a valid HGVS description, this syntax is currently not supported for mapping and validation.';
        }

    } elseif ($aResponse['type'] == 'repeat' && $aVariant['prefix'] == 'c') {
        foreach(explode('[', $aVariant['type']) as $sRepeat) {
            if (ctype_alpha($sRepeat) && strlen($sRepeat) % 3) {
                // Repeat variants on coding DNA should always have
                //  a length of a multiple of three bases.
                $aResponse['warnings']['WINVALIDREPEATLENGTH'] =
                    'A repeat sequence of coding DNA should always have a length of (a multiple of) 3.';
                if ($bCheckHGVS) {
                    return false;
                }
                break;
            }
        }
    }



    // Making sure the parentheses are placed correctly, and are removed from the suffix when they do not belong to it.
    if (substr_count($aVariant['complete'], '(') != substr_count($aVariant['complete'], ')')) {
        // If there are more opening parentheses than there are parentheses closed (or vice versa),
        //  the variant is not HGVS.
        if ($bCheckHGVS) {
            return false;
        }
        $aResponse['warnings']['WUNBALANCEDPARENTHESES'] = 'The variant description contains unbalanced parentheses.';
    }

    if (substr_count($aVariant['suffix'], '(') < substr_count($aVariant['suffix'], ')')) {
        // The suffix of variant c.(1_2ins(50)) is saved as (50)). We want to remove all parentheses
        //  which are not part of the actual suffix, and that is what we do here.
        $aVariant['suffix'] = substr($aVariant['suffix'], 0, -1);
    }



    // Finding out if the suffix is appropriately placed and
    //  is formatted as it should.
    if (!$aVariant['suffix']
        && (in_array($aVariant['type'], array('ins', 'delins'))
            || isset($aResponse['messages']['IPOSITIONRANGE']))
        && $aResponse['type'] != 'subst') {
        // Variants of type ins and delins need a suffix showing what has been
        //  inserted and variants which took place within a range need a suffix
        //  showing the length of the variant.
        // This is not required for substitutions with an IPOSITIONRANGE,
        //  as their length is always 1.
        if ($bCheckHGVS) {
            return false;
        }
        if (in_array($aVariant['type'], array('ins', 'delins'))) {
            $aResponse['errors']['ESUFFIXMISSING'] =
                'The inserted sequence must be provided for insertions or deletion-insertions.';
        } else {
            $aResponse['errors']['ESUFFIXMISSING'] =
                'The length must be provided for variants which took place within an uncertain range.';
        }

    } elseif ($aVariant['suffix']) {
        // Check the suffix for each type of variant.
        // First, exclude something that we don't support.
        if (strpos($sVariant, '^') !== false) {
            // "Or" syntax using a ^.
            if ($bCheckHGVS) {
                return false;
            }
            $aResponse['type'] = '^';
            // We have to throw an ENOTSUPPORTED, although we're returning
            //  positions. We currently cannot claim these are HGVS or not,
            //  so an WNOTSUPPORTED isn't appropriate.
            $aResponse['errors']['ENOTSUPPORTED'] =
                'Currently, variant descriptions using "^" are not yet supported.' .
                ' This does not necessarily mean the description is not valid HGVS.';
            return $aResponse;

        } elseif ($aResponse['type'] == 'repeat') {
            // Repeats should never be given a suffix.
            if ($bCheckHGVS) {
                return false;
            }
            $aResponse['warnings']['WSUFFIXGIVEN'] = 'Nothing should follow "' . $aVariant['type'] . '".';

        } elseif (in_array($aResponse['type'], array('ins', 'delins'))) {
            // Note: Using $aResponse's type here, because 'con' is changed to 'delins' there.
            // For insertions and deletion-insertions, the suffix can be quite complex. Also, it doesn't depend on the
            //  variant's length, so all checks are different. Check all possibilities.
            // Case problems are not checked here. Although it would perhaps help to provide a better warning,
            //  lovd_fixHGVS() already takes care of all issues, so we don't really need to check here.
            if (substr_count($aVariant['suffix'], '[') != substr_count($aVariant['suffix'], ']')) {
                if ($bCheckHGVS) {
                    return false;
                }
                $aResponse['warnings']['WSUFFIXFORMAT'] =
                    'The part after "' . $aVariant['type'] . '" contains unbalanced square brackets.';

            } else {
                $bSuffixIsSurroundedByBrackets = ($aVariant['suffix'][0] == '[' && substr($aVariant['suffix'], -1) == ']');
                $bMultipleInsertionsInSuffix = strpos($aVariant['suffix'], ';');

                foreach (explode(';', (!$bSuffixIsSurroundedByBrackets? $aVariant['suffix'] :
                        substr($aVariant['suffix'], 1, -1))) as $sInsertion) {
                    // Looping through all possible variants.
                    // Some have specific errors, so we handle these first.
                    if (preg_match('/^[ACGTN]+\[([0-9]+|\?)_([0-9]+|\?)\]$/', $sInsertion, $aRegs)) {
                        // c.1_2insN[10_20].
                        if ($bCheckHGVS) {
                            return false;
                        }
                        list(, $nSuffixMinLength, $nSuffixMaxLength) = $aRegs;
                        $aResponse['warnings']['WSUFFIXFORMAT'] =
                            'The part after "' . $aVariant['type'] . '" does not follow HGVS guidelines.' .
                            ' Please rewrite "' . $sInsertion . '" to "N[' .
                            ($nSuffixMinLength == $nSuffixMaxLength?
                                $nSuffixMinLength :
                                '(' . (strpos($sInsertion, '?') !== false || $nSuffixMinLength < $nSuffixMaxLength?
                                    $nSuffixMinLength . '_' . $nSuffixMaxLength :
                                    min($nSuffixMinLength, $nSuffixMaxLength) . '_' . max($nSuffixMinLength, $nSuffixMaxLength)) . ')') . ']".';

                    } elseif (preg_match('/^[ACGTN]+\[(([0-9]+|\?)|\(([0-9]+|\?)_([0-9]+|\?)\))\]$/', $sInsertion, $aRegs)) {
                        // c.1_2insN[40] or ..N[(1_2)].
                        if (isset($aRegs[3])) {
                            // Range was given.
                            list(, $nSuffixLength,, $nSuffixMinLength, $nSuffixMaxLength) = $aRegs;
                            if (strpos($nSuffixLength, '?') === false && $nSuffixMinLength >= $nSuffixMaxLength) {
                                if ($bCheckHGVS) {
                                    return false;
                                }
                                list($nSuffixMinLength, $nSuffixMaxLength) = array($nSuffixMaxLength, $nSuffixMinLength);
                                $aResponse['warnings']['WSUFFIXFORMAT'] =
                                    'The part after "' . $aVariant['type'] . '" does not follow HGVS guidelines.' .
                                    ' Please rewrite "' . $sInsertion . '" to "N[' .
                                    ($nSuffixMinLength == $nSuffixMaxLength?
                                        $nSuffixMinLength :
                                        '(' . $nSuffixMinLength . '_' . $nSuffixMaxLength . ')') . ']".';
                            }
                        }

                    } elseif (!(
                        (!(!$bMultipleInsertionsInSuffix && $bSuffixIsSurroundedByBrackets)                            // so no c.1_2ins[A]
                            && (preg_match('/^[ACGTN]+$/', $sInsertion)                                                // c.1_2insATG
                                || (preg_match(                                                                        // c.1_2ins15+1_16-1
                                    '/^([-*]?[0-9]+([-+][0-9]+)?)_([-*]?[0-9]+([-+]([0-9]+))?)(inv)?$/', $sInsertion, $aRegs)
                                    && !(ctype_digit($aRegs[1]) && ctype_digit($aRegs[3]) && $aRegs[1] > $aRegs[3])))) // if positions are simple, is A < B?
                        ||
                        ($bSuffixIsSurroundedByBrackets && strpos($sInsertion, ':')
                            && ( // If we have brackets and we find a colon, we expect a full position or inversion.
                                (substr($sInsertion, -3) == 'inv' && lovd_getVariantInfo($sInsertion, false, true))
                                || lovd_getVariantInfo($sInsertion . 'del', false, true)
                            )
                        ))) {
                        if ($bCheckHGVS) {
                            return false;
                        }
                        $aResponse['warnings']['WSUFFIXFORMAT'] =
                            'The part after "' . $aVariant['type'] . '" does not follow HGVS guidelines.';
                    }
                }
            }

        } elseif (strpos($aVariant['suffix'], ';') !== false) {
            // Combined variants that should be split.
            if ($bCheckHGVS) {
                return false;
            }
            $aResponse['type'] = ';';
            // We have to throw an ENOTSUPPORTED, although we're returning
            //  positions. We currently cannot claim these are HGVS or not,
            //  so an WNOTSUPPORTED isn't appropriate.
            $aResponse['errors']['ENOTSUPPORTED'] =
                'Currently, variant descriptions of combined variants are not yet supported.' .
                ' This does not necessarily mean the description is not valid HGVS.' .
                ' Please submit your variants separately.';
            // Some descriptions throw some warnings.
            $aResponse['warnings'] = array();
            return $aResponse;

        } else {
            // All other variants should get their suffix checked first, before
            //  we warn that it shouldn't be there. Because if it contains a
            //  different type of error, we should report that first.
            // Case problems are not checked yet. So it's important to do that here.
            $bCaseOK = true;

            // First check all length issues. Can we parse the suffix into a
            //  simple length?
            $nSuffixMinLength = $nSuffixMaxLength = 0;

            if (ctype_digit($aVariant['suffix'])) {
                // g.123_124del2.
                $nSuffixMinLength = $aVariant['suffix'];
                $aResponse['warnings']['WSUFFIXFORMAT'] =
                    'The length of the variant is not formatted following the HGVS guidelines.' .
                    ' Please rewrite "' . $aVariant['suffix'] . '" to "N[' . $nSuffixMinLength . ']".';

            } elseif (preg_match('/^[ACGTNU]+$/i', $aVariant['suffix'])) {
                // g.123_124delAA.
                $bCaseOK = ($aVariant['suffix'] == strtoupper($aVariant['suffix']));
                if (strpos(strtoupper($aVariant['suffix']), 'U') !== false) {
                    $aResponse['warnings']['WSUFFIXFORMAT'] =
                        'The part after "' . $aVariant['type'] . '" does not follow HGVS guidelines.' .
                        ' Please rewrite "' . $aVariant['type'] . $aVariant['suffix'] . '" to "' . $aVariant['type'] . str_replace('U', 'T', strtoupper($aVariant['suffix'])) . '".';
                }
                $nSuffixMinLength = strlen($aVariant['suffix']);

            } elseif (preg_match('/^\(([0-9]+)(?:_([0-9]+))?\)$/', $aVariant['suffix'], $aRegs)) {
                // g.123_124del(2), g.(100_200)del(50_60).
                list(, $nSuffixMinLength, $nSuffixMaxLength) = array_pad($aRegs, 3, '');
                if ($nSuffixMaxLength && $nSuffixMinLength > $nSuffixMaxLength) {
                    list($nSuffixMinLength, $nSuffixMaxLength) = array($nSuffixMaxLength, $nSuffixMinLength);
                }
                $aResponse['warnings']['WSUFFIXFORMAT'] =
                    'The length of the variant is not formatted following the HGVS guidelines.' .
                    ' Please rewrite "' . $aVariant['suffix'] . '" to "N[' .
                    (!$nSuffixMaxLength || $nSuffixMinLength == $nSuffixMaxLength?
                        $nSuffixMinLength :
                        '(' . $nSuffixMinLength . '_' . $nSuffixMaxLength . ')') . ']".';

            } elseif (preg_match('/^N\[([0-9]+)_([0-9]+)\]$/i', $aVariant['suffix'], $aRegs)) {
                // g.(100_200)delN[50_60].
                $bCaseOK = (substr($aVariant['suffix'], 0, 1) == 'N');
                list(, $nSuffixMinLength, $nSuffixMaxLength) = $aRegs;
                if ($nSuffixMinLength > $nSuffixMaxLength) {
                    list($nSuffixMinLength, $nSuffixMaxLength) = array($nSuffixMaxLength, $nSuffixMinLength);
                }
                $aResponse['warnings']['WSUFFIXFORMAT'] =
                    'The length of the variant is not formatted following the HGVS guidelines.' .
                    ' Please rewrite "' . $aVariant['suffix'] . '" to "N[' .
                    ($nSuffixMinLength == $nSuffixMaxLength?
                        $nSuffixMinLength :
                        '(' . $nSuffixMinLength . '_' . $nSuffixMaxLength . ')') . ']".';

            } elseif (preg_match('/^N\[([0-9]+|\(([0-9]+)_([0-9]+)\))\]$/i', $aVariant['suffix'], $aRegs)) {
                // g.123_124delN[2], g.(100_200)delN[(50_60)].
                $bCaseOK = (substr($aVariant['suffix'], 0, 1) == 'N');
                if (count($aRegs) == 2) {
                    list(, $nSuffixMinLength) = $aRegs;
                } else {
                    list(,, $nSuffixMinLength, $nSuffixMaxLength) = $aRegs;

                    if ($nSuffixMinLength > $nSuffixMaxLength || $nSuffixMinLength == $nSuffixMaxLength) {
                        $aResponse['warnings']['WSUFFIXFORMAT'] =
                            'The length of the variant is not formatted following the HGVS guidelines.' .
                            ' Please rewrite "' . $aVariant['suffix'] . '" to "N[' .
                            ($nSuffixMinLength == $nSuffixMaxLength?
                                $nSuffixMinLength :
                                '(' . $nSuffixMaxLength . '_' . $nSuffixMinLength . ')') . ']".';
                        list($nSuffixMinLength, $nSuffixMaxLength) = array($nSuffixMaxLength, $nSuffixMinLength);
                    }
                }
            }
            if (!$bCaseOK) {
                if (!isset($aResponse['warnings']['WSUFFIXFORMAT'])) {
                    // Wrong case only, no U-characters detected.
                    $aResponse['warnings']['WWRONGCASE'] =
                        'This is not a valid HGVS description, due to characters being in the wrong case.' .
                        ' Please rewrite "' . $aVariant['type'] . $aVariant['suffix'] . '" to "' . $aVariant['type'] . strtoupper($aVariant['suffix']) . '".';
                } else {
                    // There's already a detailed warning on what to replace. Throw a general warning only.
                    $aResponse['warnings']['WWRONGCASE'] =
                        'This is not a valid HGVS description, due to characters being in the wrong case.' .
                        ' Please check the use of upper- and lowercase characters after "' . $aVariant['type'] . '".';
                }
            }
            if ($bCheckHGVS
                && (isset($aResponse['warnings']['WSUFFIXFORMAT']) || isset($aResponse['warnings']['WWRONGCASE']))) {
                return false;
            }

            if ($nSuffixMinLength && !isset($aResponse['messages']['IUNCERTAINPOSITIONS'])) {
                // Length given; check sizes and if this matches the variant's length.
                // We can not check this with question marks in the positions (IUNCERTAINPOSITION); there might not be
                //  a maximum variant size and we won't know whether we have the inner or outer positions stored.
                $nVariantLength = lovd_getVariantLength($aResponse);
                if (!$nSuffixMaxLength) {
                    $nSuffixMaxLength = $nSuffixMinLength;
                }

                if (isset($aResponse['messages']['IUNCERTAINRANGE'])) {
                    // Variants with three or more positions. We have the inner positions stored; we know nothing about
                    //  the outer range currently, so we can not check this.
                    if ($nVariantLength == $nSuffixMinLength && $nSuffixMinLength == $nSuffixMaxLength) {
                        $aResponse['warnings']['WSUFFIXINVALIDLENGTH'] =
                            'The positions indicate a range equally long as the given length of the variant.' .
                            ' Please remove the variant length and position uncertainty if the positions are certain, or adjust the positions or variant length.';
                    } elseif ($nVariantLength > $nSuffixMinLength) {
                        $aResponse['warnings']['WSUFFIXINVALIDLENGTH'] =
                            'The positions indicate a range longer than the given length of the variant.' .
                            ' Please adjust the positions if the variant length is certain, or adjust the variant length.';
                    }

                } elseif (isset($aResponse['messages']['IPOSITIONRANGE'])) {
                    // Variants like c.(1_2)del(5).
                    if ($nVariantLength < $nSuffixMaxLength) {
                        $aResponse['warnings']['WSUFFIXINVALIDLENGTH'] =
                            'The positions indicate a range smaller than the given length of the variant.' .
                            ' Please adjust the positions or variant length.';
                    } elseif ($nVariantLength == $nSuffixMinLength) {
                        $aResponse['warnings']['WSUFFIXINVALIDLENGTH'] =
                            'The positions indicate a range equally long as the given length of the variant.' .
                            ' Please remove the variant length and parentheses if the positions are certain, or adjust the positions or variant length.';
                    }

                } else {
                    // Simple variants with one or two known positions, no uncertainties.
                    if ($nVariantLength < $nSuffixMaxLength) {
                        // The positions are smaller than the max length, so the length is at least partially larger.
                        $aResponse['warnings']['WSUFFIXINVALIDLENGTH'] =
                            'The positions indicate a range shorter than the given length of the variant.' .
                            ' Please adjust the positions if the variant length is certain, or remove the variant length.';
                    } elseif ($nVariantLength > $nSuffixMinLength) {
                        // The positions are bigger than the min length, so the length is at least partially smaller.
                        $aResponse['warnings']['WSUFFIXINVALIDLENGTH'] =
                            'The positions indicate a range longer than the given length of the variant.' .
                            ' Please adjust the positions if the variant length is certain, or remove the variant length.';
                    } elseif (!isset($aResponse['warnings']['WWRONGCASE'])) {
                        // Length is not (partially) larger, is not (partially) smaller, so must be equal.
                        // This is where the suffix becomes unnecessary.
                        $aResponse['warnings']['WSUFFIXGIVEN'] = 'Nothing should follow "' . $aVariant['type'] . '".';
                    }
                }

                if ($bCheckHGVS
                    && (isset($aResponse['warnings']['WSUFFIXINVALIDLENGTH']) || isset($aResponse['warnings']['WSUFFIXGIVEN']))) {
                    return false;
                }

            } elseif (!$nSuffixMinLength) {
                // We couldn't parse the suffix.
                if (isset($aResponse['messages']['IUNCERTAINRANGE'])) {
                    // Variants with three or more positions. The suffix isn't required.
                    $aResponse['warnings']['WSUFFIXFORMAT'] =
                        'The length of the variant is not formatted following the HGVS guidelines.' .
                        ' If you didn\'t mean to specify a variant length, please remove the part after "' . $aVariant['type'] . '".';
                } elseif (isset($aResponse['messages']['IPOSITIONRANGE'])) {
                    // Variants like c.(1_2)del(5). The suffix is mandatory.
                    $aResponse['warnings']['WSUFFIXFORMAT'] =
                        'The length of the variant is not formatted following the HGVS guidelines.' .
                        ' When indicating an uncertain position like this, the length or sequence of the variant must be provided.';
                } elseif ($aVariant['type'] == 'del' && strpos(strtolower($aVariant['suffix']), 'ins')) {
                    // A very special case; deletions where the suffix contains "ins". This is usually a delNinsN case.
                    // We can have this rewritten, but only when the length matches. We'll use a recursive call to find
                    //  out if that's OK. Based on that, we'll devise our answer.
                    list($sDeleted, $sInserted) = array_map('strtoupper', explode('ins', str_replace('u', 't', strtolower($aVariant['suffix'])), 2));
                    $aDeletion = lovd_getVariantInfo(str_replace($aVariant['suffix'], $sDeleted, $sVariant), $sTranscriptID);
                    // If the suffix matches the variant's length, or the suffix is unparseable, then we'll get a WSUFFIXGIVEN.
                    if (count($aDeletion['warnings']) == 1 && isset($aDeletion['warnings']['WSUFFIXGIVEN'])) {
                        $aResponse['type'] = 'delins';
                        $bCaseOK = ($aVariant['suffix'] == $sDeleted . 'ins' . $sInserted);
                        if (!$bCaseOK) {
                            $aResponse['warnings']['WWRONGCASE'] =
                                'This is not a valid HGVS description, due to characters being in the wrong case.' .
                                ' Please check the use of upper- and lowercase characters after "' . $aVariant['type'] . '".';
                        }
                        if (strlen($sDeleted) == 1 && strlen($sInserted) == 1 && preg_match('/^[ACGTN]$/', $sDeleted)) {
                            // Another special case; a delins that should have been a substitution.
                            $aResponse['warnings']['WWRONGTYPE'] =
                                'A deletion-insertion of one base to one base should be described as a substitution.' .
                                ' Please rewrite "' . $aVariant['type'] . $aVariant['suffix'] . '" to "' . $sDeleted . '>' . $sInserted . '".';
                        } else {
                            // We're not going to check here if this is a delAinsAT here that should be a shifted ins
                            //  or even check for insertions that should be dups. VV will handle that if we need it.
                            // Simply tell them to rewrite it.
                            $aResponse['warnings']['WSUFFIXFORMAT'] =
                                'The part after "' . $aVariant['type'] . '" does not follow HGVS guidelines.' .
                                ' Please rewrite "' . $aVariant['type'] . $aVariant['suffix'] . '" to "delins' . $sInserted . '".';
                        }

                    } elseif (count($aDeletion['warnings']) == 1 && isset($aDeletion['warnings']['WSUFFIXINVALIDLENGTH'])) {
                        // Length mismatched. Just pass it on.
                        $aResponse['type'] = 'delins';
                        $aResponse['warnings'] = $aDeletion['warnings'];

                    } else {
                        // We got other warnings. Maybe the format is wrong? Just throw an error.
                        $aResponse['warnings']['WSUFFIXFORMAT'] =
                            'The part after "' . $aVariant['type'] . '" does not follow HGVS guidelines.';
                    }

                } else {
                    // Simple variants with one or two known positions, no uncertainties. The suffix is forbidden.
                    // Still, make a difference between "suffix sometimes allowed but not understood"
                    //  and "suffix never allowed".
                    if ($aResponse['type'] == 'subst') {
                        $aResponse['warnings']['WSUFFIXGIVEN'] = 'Nothing should follow "' . $aVariant['type'] . '".';
                    } else {
                        $aResponse['warnings']['WSUFFIXFORMAT'] =
                            'The part after "' . $aVariant['type'] . '" does not follow HGVS guidelines.';
                    }
                }

                if ($bCheckHGVS
                    && (isset($aResponse['warnings']['WSUFFIXFORMAT'])
                        || isset($aResponse['warnings']['WSUFFIXGIVEN'])
                        || isset($aResponse['warnings']['WWRONGCASE'])
                        || isset($aResponse['warnings']['WWRONGTYPE']))) {
                    return false;
                }
            }
        }
    }

    // At this point, we can be certain that our variant fully matched the HGVS nomenclature.
    if ($bCheckHGVS) {
        return true;
    }

    // Done checking the syntax of the variant.





    // When strict SQL mode is enabled, we'll get errors when we try and
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

    if (isset($aMinMaxValues[$aVariant['prefix']])) {
        // If the min and max values are defined for this prefix, check the fields.

        foreach ($aMinMaxValues[$aVariant['prefix']] as $sField => $aMinMaxValue) {
            if ($aResponse[$sField] === '?') {
                $aResponse[$sField] = (substr($sField, -5) == 'start'? $aMinMaxValue[0] : $aMinMaxValue[1]);

            } else {
                $nOriValue = $aResponse[$sField];
                $aResponse[$sField] = max($aResponse[$sField], $aMinMaxValue[0]);
                $aResponse[$sField] = min($aResponse[$sField], $aMinMaxValue[1]);

                if ($nOriValue != $aResponse[$sField]) {
                    $sFieldName = str_replace('position_', '', $sField);
                    if (strpos($sField, 'intron')) {
                        $sFieldName = str_replace('_intron', ' in intron', $sFieldName);
                    }

                    if (!isset($aResponse['warnings']['WPOSITIONLIMIT'])) {
                        $aResponse['warnings']['WPOSITIONLIMIT'] = 'Position is beyond the possible limits of its type: ' . $sFieldName . '.';
                    } else {
                        // Append.
                        $aResponse['warnings']['WPOSITIONLIMIT'] =
                            str_replace(array('Position is ', ' its '), array('Positions are ', ' their '), rtrim($aResponse['warnings']['WPOSITIONLIMIT'], '.')) . ', ' . $sFieldName . '.';
                    }
                }
            }
        }
    }

    return $aResponse;
}





function lovd_getVariantLength ($aVariant)
{
    // This function receives an array in the format as given by
    //  lovd_getVariantInfo() and calculates the length of the variant.
    // This length will only include intronic positions if the input contains
    //  these. When the length cannot be determined due to crossing the center
    //  of an intron, this function will return false.

    if (!isset($aVariant['position_start']) || !isset($aVariant['position_end'])
        || $aVariant['position_start'] == '?' || $aVariant['position_end'] == '?') {
        return false;
    }

    $nBasicLength = $aVariant['position_end'] - $aVariant['position_start'] + 1;
    if (empty($aVariant['position_start_intron'])
        && empty($aVariant['position_end_intron'])) {
        // Simple case; genomic variant or simply no introns involved.
        return ($nBasicLength);

    } elseif (empty($aVariant['position_start_intron'])) {
        // So we have an intronic end, but not an intronic start.
        // If the intronic end is negative, this means we're crossing the
        //  center of an intron, and the length cannot be determined.
        if ($aVariant['position_end_intron'] < 0) {
            return false;
        }
        return ($nBasicLength + $aVariant['position_end_intron']);

    } elseif (empty($aVariant['position_end_intron'])) {
        // So we have an intronic start, but not an intronic end.
        // If the intronic start is positive, this means we're crossing the
        //  center of an intron, and the length cannot be determined.
        if ($aVariant['position_start_intron'] > 0) {
            return false;
        }
        return ($nBasicLength + abs($aVariant['position_start_intron']));
    }

    // Else, we have intronic positions both for the start and the end.
    if ($aVariant['position_start'] == $aVariant['position_end']) {
        // Same side of the intron. Just take the max minus the min.
        // NOTE: $nBasicLength is already 1 even though no length has been
        //  calculated yet. So we don't have to add that 1 here.
        return (
            $nBasicLength +
            max(
                $aVariant['position_start_intron'],
                $aVariant['position_end_intron']
            ) -
            min(
                $aVariant['position_start_intron'],
                $aVariant['position_end_intron']
            )
        );

    } elseif ($aVariant['position_start_intron'] > 0
        || $aVariant['position_end_intron'] < 0) {
        // Still nope.
        return false;
    }

    // OK, just add the lengths.
    return (
        $nBasicLength
        + abs($aVariant['position_start_intron'])
        + $aVariant['position_end_intron']);
}





function lovd_getVariantPrefixesByRefSeq ($s)
{
    // Returns all the DNA type prefixes which fit a given reference sequence.
    // The variable $s could be a full variant description, or it might
    //  just be a reference sequence.
    global $_LIBRARIES;

    // Get matching DNA type prefixes.
    foreach($_LIBRARIES['regex_patterns']['refseq_to_DNA_type'] as $sPattern => $aDNATypes) {
        if (preg_match($sPattern, $s)) {
            return $aDNATypes;
        }
    }

    // No matches found.
    return array();
}





function lovd_getVariantRefSeq ($sVariant)
{
    // This function isolates and returns the reference sequence from a variant description, if there is any.

    if (!lovd_variantHasRefSeq($sVariant)) {
        return false;
    }

    return strstr($sVariant, ':', true);
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
                    'shared' => !(LOVD_plus || LOVD_light),
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
                    'shared' => !(LOVD_plus || LOVD_light),
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
        // Base authorization on own level and other's level,
        //  if not requesting authorization on themself.
        if (is_array($Data)) {
            // Not supported on this data type.
            return false;
        } else {
            // If user is viewing themself, always get authorization.
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

    global $_AUTH, $_DB;

    if (!$_AUTH || !in_array($sType, array('individual', 'phenotype', 'screening', 'variant'))) {
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





function lovd_isValidRefSeq ($sRefSeq)
{
    // This function checks if the given string is a valid reference sequence description.
    global $_LIBRARIES;

    return (bool) (
        is_string($sRefSeq)
        &&
        preg_match($_LIBRARIES['regex_patterns']['refseq']['strict'], $sRefSeq)
    );
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
        if (!empty($_CONF['proxy_host'])) {
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





function lovd_showInfoTable ($sMessage, $sType = 'information', $sWidth = '100%', $sHref = '', $bBR = true, $bTitle = true)
{
    global $_T;

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
            // Print the template header and title, in case it hasn't been done yet.
            $_T->printHeader(); // Already makes sure that it doesn't get repeated.
            if (defined('PAGE_TITLE') && $bTitle) {
                $_T->printTitle(); // The same title will never be printed twice.
            }

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





function lovd_variantHasRefSeq ($sVariant)
{
    // This function returns whether the general pattern of a reference sequence was found in a variant description.
    global $_LIBRARIES;

    return (
        is_string($sVariant)
        &&
        strpos($sVariant, ':') !== false
        &&
        preg_match($_LIBRARIES['regex_patterns']['refseq']['basic'], strstr($sVariant, ':', true))
    );
}





function lovd_variantRemoveRefSeq ($sVariant)
{
    // This function removes the reference sequence from a variant description.

    if (!lovd_variantHasRefSeq($sVariant)) {
        return $sVariant;
    }

    return substr(strstr($sVariant, ':'), 1);
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
        array($sLog, $sTime, ($nAuthID?: ($_AUTH? $_AUTH['id'] : NULL)), $sEvent, $sMessage), false);
    return (bool) $q;
}
?>
