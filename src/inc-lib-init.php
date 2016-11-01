<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-19
 * Modified    : 2016-10-14
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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

    // Only for tables that actually exist.
    if (!in_array($sTable, $_TABLES)) {
        return false;
    }

    if (empty($aTableCols[$sTable])) {
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
    return (!$bFull? '' : PROTOCOL . $_SERVER['HTTP_HOST']) . lovd_cleanDirName(dirname($_SERVER['SCRIPT_NAME']) . '/' . ROOT_PATH);
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
                return 1; // FIXME: We're not supporting $bSetUserLevel at the moment (not required right now, either).
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
        );

    if (LOVD_plus) {
        // Configure data file paths.
        $aConfigValues['paths'] = array(
            'data_files' =>
                array(
                    'required' => true,
                    'path_is_readable' => true,
                    'path_is_writable' => true,
                ),
            'data_files_archive' =>
                array(
                    'required' => false,
                    'path_is_readable' => true,
                    'path_is_writable' => true,
                ),
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
        );

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
    // - Fopen wrappers are on AND we're NOT using POST (because POST doesn't work for now).
    if (substr($sURL, 0, 4) != 'http' || substr($sURL, 0, 5) == 'https' || (ini_get('allow_url_fopen') && !$sPOST)) {
        // Normal file() is fine.
        $aOptions = array(
            'http' => array(
                'method' => ($sPOST? 'POST' : 'GET'),
                'header' => $aAdditionalHeaders,
                'user_agent' => 'LOVDv.' . $_SETT['system']['version'],
            ),
        );
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
    global $_AUTH, $_DB, $_SETT, $_T;

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
            print($sPrefix . '  <LI' . (!$sIMG? '' : ' class="icon"') . '><A ' . (substr($sURL, 0, 11) == 'javascript:'? 'click="' : 'href="' . ($sSubMenu ? '' : lovd_getInstallURL(false))) . ($sSubMenu ? '' : ltrim($sURL, '/')) . '">' .
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





function lovd_soapError ($e, $bHalt = true)
{
    // Formats SOAP errors for the error log, and optionally halts the system.

    if (!is_object($e)) {
        return false;
    }

    // Try to detect if arguments have been passed, and isolate them from the stacktrace.
    $sMethod = '';
    $sArgs = '';
    foreach ($e->getTrace() as $aTrace) {
        if (isset($aTrace['function']) && $aTrace['function'] == '__call') {
            // This is the low level SOAP call. Isolate used method and arguments from here.
            list($sMethod, $aArgs) = $aTrace['args'];
            if ($aArgs && is_array($aArgs) && isset($aArgs[0])) {
                $aArgs = $aArgs[0]; // Not sure why the call's argument are in a sub array, but oh, well.
                foreach ($aArgs as $sArg => $sValue) {
                    $sArgs .= (!$sArgs? '' : "\n") . "\t\t" . $sArg . ':' . $sValue;
                }
            }
            break;
        }
    }

    // Format the error message.
    $sError = preg_replace('/^' . preg_quote(rtrim(lovd_getInstallURL(false), '/'), '/') . '/', '', $_SERVER['REQUEST_URI']) . ' returned error in module \'' . $sMethod . '\'.' . "\n" .
        (!$sArgs? '' : 'Arguments:' . "\n" . $sArgs . "\n") .
        'Error message:' . "\n" .
        str_replace("\n", "\n\t\t", $e->__toString());

    // If the system needs to be halted, send it through to lovd_displayError() who will print it on the screen,
    // write it to the system log, and halt the system. Otherwise, just log it to the database.
    if ($bHalt) {
        return lovd_displayError('SOAP', $sError);
    } else {
        return lovd_writeLog('Error', 'SOAP', $sError);
    }
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
    global $_AUTH, $_DB;

    if (!$_DB) {
        // Don't try to log when we don't have DB connection (such as mysql password incorrect).
        return false;
    }

    // Timestamp, serves as an unique identifier.
    $aTime = explode(' ', microtime());
    $sTime = substr($aTime[0], 2, -2);

    // Insert new line in logs table.
    $q = $_DB->query('INSERT INTO ' . TABLE_LOGS . ' VALUES (?, NOW(), ?, ?, ?, ?)', array($sLog, $sTime, ($_AUTH['id']? $_AUTH['id'] : NULL), $sEvent, $sMessage), false);
    return (bool) $q;
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





function lovd_convertSecondsToTime ($sValue, $nDecimals = 0)
{
    // This function takes a number of seconds and converts it into whole
    // minutes, hours, days, months or years.
    // FIXME; Implement proper checks here? Regexp?

    $nValue = (int) $sValue;
    if (ctype_digit((string) $sValue)) {
        $sValue .= 's';
    }
    $sLast = strtolower(substr($sValue, -1));
    $nDecimals = (int) $nDecimals;

    $aConversion =
        array(
            's' => array(60, 'm'),
            'm' => array(60, 'h'),
            'h' => array(24, 'd'),
            'd' => array(265, 'y'),
        );

    foreach ($aConversion as $sUnit => $aConvert) {
        list($nFactor, $sNextUnit) = $aConvert;
        if ($sLast == $sUnit && $nValue > $nFactor) {
            $nValue /= $nFactor;
            $sLast = $sNextUnit;
        }
    }

    return round($nValue, $nDecimals) . $sLast;
}
?>
