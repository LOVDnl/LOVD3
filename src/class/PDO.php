<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-08-17
 * Modified    : 2011-10-31
 * For LOVD    : 3.0-alpha-06
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
        if ($sBackend == 'mysql') {
            // This method for setting the charset works also before 5.3.6, when "charset" was introduced in the DSN.
            $aOptions = array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8', PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => TRUE);
        } else {
            $aOptions = array();
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





    function query ($sSQL, $aSQL = '', $bHalt = true)
    {
        // Wrapper around PDO::query() or PDO::prepare()->execute(), if arguments are passed.
        // THIS WRAPPER DOES NOT SUPPORT ANY OF THE MODES!
        //   PDO::FETCH_COLUMN is quite useless (you have ->fetchColumn() for that) and PDO::FETCH_CLASS and PDO::FETCH_INTO MODE are not used in LOVD.

        if (is_array($aSQL)) {
            // We'll do an prepare() and execute(), not a query()!
            $q = $this->prepare($sSQL, $bHalt); // Error handling by our own PDO class.
            if ($q) {
                $q->execute($aSQL, $bHalt); // Error handling by our own PDOStatement class.
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
}





class LOVD_PDOStatement extends PDOStatement {
    // This class provides a wrapper around PDOStatement such that database errors are handled automatically by LOVD and LOVD can use fetch() features more easily.
    // FIXME; apparently we don't need to call parent::__construct()? I can't get that to work, and this wrapper seems to work without it anyway...

    function execute ($aSQL = array(), $bHalt = true) // Somebody tell me why I need the "= array()" to prevent a strict error?
    {
        // Wrapper around PDOStatement::execute().
        global $_DB;

        try {
            if (is_array($aSQL)) {
                // lovd_queryDB() allowed the passing of arrays in the arguments.
                foreach ($aSQL as $nKey => $Arg) {
                    if (is_array($Arg)) {
                        // We handle arrays gracefully.
                        $aSQL[$nKey] = implode(';', $Arg);
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
