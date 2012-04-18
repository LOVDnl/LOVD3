<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-08-17
 * Modified    : 2012-04-16
 * For LOVD    : 3.0-beta-04
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
 
// Don't allow direct access.
if (!defined('ROOT_PATH')) {
    exit;
}





class LOVD_PDO extends PDO {
    // This class provides a wrapper around PDO such that database errors are handled automatically by LOVD.
    // FIXME; lovd_queryDB() provided a $bDebug argument. How to implement that now?

    function __construct ($sBackend, $sDSN, $sUsername, $sPassword)
    {
        // Initiate database connection.

        $sDSN = $sBackend . ':' . $sDSN;
        $aOptions = array();
        if ($sBackend == 'mysql') {
            // This method for setting the charset works also before 5.3.6, when "charset" was introduced in the DSN.
            // Fix #4; Implement fix for PHP 5.3.0 on Windows, where PDO::MYSQL_ATTR_INIT_COMMAND by accident is not available.
            // https://bugs.php.net/bug.php?id=47224                  (other constants were also lost, but we don't use them)
            // Can't define a class' constant, so I'll have to use this one. This can be removed (and MYSQL_ATTR_INIT_COMMAND
            // below restored to PDO::MYSQL_ATTR_INIT_COMMAND) once we're sure they're no other 5.3.0 users left.
            if (!defined('MYSQL_ATTR_INIT_COMMAND')) {
                // Still needs check though, in case two PDO connections are opened.
                define('MYSQL_ATTR_INIT_COMMAND', 1002);
            }
            $aOptions = array(MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8', PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => TRUE);
        }
        try {
            parent::__construct($sDSN, $sUsername, $sPassword, $aOptions);
            $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, array('LOVD_PDOStatement'));
        } catch (PDOException $e) {
            // No connection or couldn't select database!
            lovd_displayError('Init', 'Error connecting to database: ' . $e->getMessage());
        }
    }





    function exec ($sSQL, $bHalt = true)
    {
        // Wrapper around PDO::exec().

        try {
            $q = parent::exec($sSQL);
        } catch (PDOException $e) {
            if ($bHalt) {
                try {
                    @$this->rollBack(); // In case we were in a transaction. // FIXME; we can know from PHP >= 5.3.3.
                } catch (PDOException $eNoTransaction) {}

                // lovd_queryError() will call lovd_displayError() which will halt the system.
                lovd_queryError((defined('LOG_EVENT')? LOG_EVENT : 'Unknown'), $sSQL, 'Error in PDO::exec() while executing query: ' . $e->getMessage());
            } else {
                return false;
            }
        }
        return $q;
    }





    function formatError ()
    {
        // Formats the error message from PDO::errorInfo() such that is resembles the error message from the Exception Handler.

        $a = $this->errorInfo();
        if (is_array($a) && !empty($a[2])) {
            return 'SQLSTATE[' . $a[0] . ']: Syntax error or access violation: ' . $a[1] . ' ' . $a[2];
        }
        return '';
    }





    function prepare ($sSQL, $bHalt = true, $aOptions = array())
    {
        // Wrapper around PDO::prepare().

        try {
            $q = parent::prepare($sSQL, $aOptions);
        } catch (PDOException $e) {
            // Incorrect SQL does not get here???
            if ($bHalt) {
                try {
                    @$this->rollBack(); // In case we were in a transaction. // FIXME; we can know from PHP >= 5.3.3.
                } catch (PDOException $eNoTransaction) {}

                // lovd_queryError() will call lovd_displayError() which will halt the system.
                lovd_queryError((defined('LOG_EVENT')? LOG_EVENT : 'Unknown'), $sSQL, 'Error in PDO::prepare() while preparing query: ' . $e->getMessage());
            } else {
                return false;
            }
        }
        return $q;
    }





    function query ($sSQL, $aSQL = '', $bHalt = true, $bTrim = false)
    {
        // Wrapper around PDO::query() or PDO::prepare()->execute(), if arguments are passed.
        // THIS WRAPPER DOES NOT SUPPORT ANY OF THE MODES!
        //   PDO::FETCH_COLUMN is quite useless (you have ->fetchColumn() for that) and PDO::FETCH_CLASS and PDO::FETCH_INTO MODE are not used in LOVD.

        if (is_array($aSQL)) {
            // We'll do an prepare() and execute(), not a query()!
            $q = $this->prepare($sSQL, $bHalt); // Error handling by our own PDO class.
            if ($q) {
                $b = $q->execute($aSQL, $bHalt, $bTrim); // Error handling by our own PDOStatement class.
                if (!$b) {
                    // We should actually return true||false now, but the user of this function probably wants to do a
                    // fetch() if the execute was successful, so return the PDOStatement object just like PDO::query().
                    return false;
                }
            }

        } else {
            // Actual PDO::query().
            try {
                $q = parent::query($sSQL);
            } catch (PDOException $e) {
                if ($bHalt) {
                    try {
                        @$this->rollBack(); // In case we were in a transaction. // FIXME; we can know from PHP >= 5.3.3.
                    } catch (PDOException $eNoTransaction) {}

                    // lovd_queryError() will call lovd_displayError() which will halt the system.
                    lovd_queryError((defined('LOG_EVENT')? LOG_EVENT : 'Unknown'), $sSQL, 'Error in PDO::query() while running query: ' . $e->getMessage());
                } else {
                    return false;
                }
            }
        }
        return $q;
    }





    function getServerInfo ()
    {
        // Command replacing the old mysql_get_server_info().
        return $this->getAttribute(PDO::ATTR_SERVER_VERSION);
    }
}





class LOVD_PDOStatement extends PDOStatement {
    // This class provides a wrapper around PDOStatement such that database errors are handled automatically by LOVD and LOVD can use fetch() features more easily.
    // FIXME; apparently we don't need to call parent::__construct()? I can't get that to work, and this wrapper seems to work without it anyway...

    function execute ($aSQL = array(), $bHalt = true, $bTrim = false) // Needs first argument as optional because the original function has it as optional.
    {
        // Wrapper around PDOStatement::execute().
        global $_DB;

        try {
            if (is_array($aSQL)) {
                // lovd_queryDB() allowed the passing of arrays in the arguments.
                foreach ($aSQL as $nKey => $Arg) {
                    if (is_array($Arg)) {
                        // We handle arrays gracefully.
                        $aSQL[$nKey] = implode(';', ($bTrim? array_map('trim', $Arg) : $Arg));
                    } elseif ($Arg === NULL) {
                        $this->bindValue($nKey + 1, $Arg, PDO::PARAM_INT);
                    } else {
                        $aSQL[$nKey] = ($bTrim? trim($aSQL[$nKey]) : $aSQL[$nKey]);
                    }
                }
            } // There is no else, we will catch the exception thrown by parent::execute().
            parent::execute($aSQL);

        } catch (PDOException $e) {
            // Incorrect SQL, too few parameters, ...
            if ($bHalt) {
                try {
                    @$_DB->rollBack(); // In case we were in a transaction. // FIXME; we can know from PHP >= 5.3.3.
                } catch (PDOException $eNoTransaction) {}

                // lovd_queryError() will call lovd_displayError() which will halt the system.
                lovd_queryError((defined('LOG_EVENT')? LOG_EVENT : 'Unknown'), $this->queryString, 'Error in PDOStatement::execute() while executing prepared query: ' . $e->getMessage());
            } else {
                return false;
            }
        }
        return true;
    }





    function fetchAllAssoc ()
    {
        // Wrapper around PDOStatement::fetchAll(PDO::FETCH_ASSOC).
        // THIS WRAPPER DOES NOT SUPPORT ANY OF THE PDOStatement::fetchAll() ARGUMENTS!
        return $this->fetchAll(PDO::FETCH_ASSOC);
    }





    function fetchAllColumn ($nCol = 0)
    {
        // Wrapper around PDOStatement::fetchAll(PDO::FETCH_COLUMN).
        // THIS WRAPPER ONLY SUPPORTS THE col number PDOStatement::fetchAll() ARGUMENT!
        if (!ctype_digit($nCol)) {
            $nCol = 0;
        }
        return $this->fetchAll(PDO::FETCH_COLUMN, $nCol);
    }





    function fetchAllCombine ($nCol1 = 0, $nCol2 = 1)
    {
        // Wrapper around PDOStatement::fetchAll() that creates an array with one field's results as the keys and the other field's results as values.
        if (!ctype_digit($nCol1) && !is_int($nCol1)) {
            $nCol1 = 0;
        }
        if (!ctype_digit($nCol2) && !is_int($nCol2)) {
            $nCol2 = 1;
        }
        $a = array();
        while ($r = $this->fetchRow()) {
            $a[$r[$nCol1]] = $r[$nCol2];
        }
        return $a;
    }





    function fetchAllRow ()
    {
        // Wrapper around PDOStatement::fetchAll(PDO::FETCH_NUM).
        // THIS WRAPPER DOES NOT SUPPORT ANY OF THE PDOStatement::fetchAll() ARGUMENTS!
        return $this->fetchAll(PDO::FETCH_NUM);
    }





    function fetchAssoc ()
    {
        // Wrapper around PDOStatement::fetch(PDO::FETCH_ASSOC).
        // THIS WRAPPER DOES NOT SUPPORT ANY OF THE PDOStatement::fetch() ARGUMENTS!
        return $this->fetch(PDO::FETCH_ASSOC);
    }





    function fetchRow ()
    {
        // Wrapper around PDOStatement::fetch(PDO::FETCH_NUM).
        // THIS WRAPPER DOES NOT SUPPORT THE cursor_orientation OR offset ARGUMENTS!
        return $this->fetch(PDO::FETCH_NUM);
    }
}
?>
