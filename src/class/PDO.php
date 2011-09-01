<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-08-17
 * Modified    : 2011-09-01
 * For LOVD    : 3.0-alpha-04
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
    // This class provides a wrapper around PDO such that query errors are handled automatically by LOVD.
    var $PDO;
    // FIXME; lovd_queryDB() provided the option to pass NULL. Does that work also in LOVD_PDO::prepare()->execute() ?
    // FIXME; lovd_queryDB() provided a $bDebug argument. How to implement that now?
    // FIXME; could we maybe use query() for an interface to PDO::query() AND PDO::prepare()?
    //   Especially if we're never using the (distinct!) optional arguments that these two functions have.

    function LOVD_PDO ($sDSN, $sUsername, $sPassword)
    {
        // Initiate database connection.
        $aOptions = array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'); // This method works also before 5.3.6, when "charset" was introduced in the DSN.
        try {
            $this->PDO = new PDO($sDSN, $sUsername, $sPassword, $aOptions);
            $this->PDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            // No connection or couldn't select database!
            lovd_displayError('Init', 'Error connecting to database: ' . $e->getMessage());
        }
    }





    function beginTransaction ()
    {
        // Wrapper to PDO::beginTransaction(), necessary because we need to link it to the initiated PDO class and not just to parent.
        return $this->PDO->beginTransaction();
    }





    function commit ()
    {
        // Wrapper to PDO::commit(), necessary because we need to link it to the initiated PDO class and not just to parent.
        return $this->PDO->commit();
    }





    function exec ($sSQL, $bHalt = true)
    {
        // Wrapper around PDO::exec().
        try {
            $q = $this->PDO->exec($sSQL);
        } catch (PDOException $e) {
            if ($bHalt) {
                try {
                    @$this->PDO->rollBack(); // In case we were in a transaction. // FIXME; can we know?
                } catch (PDOException $eNoTransaction) {}
                // lovd_queryError() will call lovd_displayError() which will halt the system.
                lovd_queryError((defined('LOG_EVENT')? LOG_EVENT : 'Unknown'), $sSQL, 'Error in PDO::exec() while executing query: ' . $e->getMessage());
            } else {
                return false;
            }
        }
        return $q;
    }





    function prepare ($sSQL, $aSQL = '', $bHalt = true, $aOptions = array())
    {
        // Wrapper around PDO::prepare().
        try {
            $q = $this->PDO->prepare($sSQL, $aOptions);
            // Feature normal PDO does not allow; usually we want the query to be executed right away!
            if (is_array($aSQL) && count($aSQL)) {
                // lovd_queryDB() allowed the passing of arrays in the arguments.
                foreach ($aSQL as $nKey => $Arg) {
                    if (is_array($Arg)) {
                        // We handle arrays gracefully.
                        $aSQL[$nKey] = implode(';', $Arg);
                    }
                }
                $q->execute($aSQL);
            }
        } catch (PDOException $e) {
            if ($bHalt) {
                try {
                    @$this->PDO->rollBack(); // In case we were in a transaction. // FIXME; can we know?
                } catch (PDOException $eNoTransaction) {}
                // lovd_queryError() will call lovd_displayError() which will halt the system.
                lovd_queryError((defined('LOG_EVENT')? LOG_EVENT : 'Unknown'), $sSQL, 'Error in PDO::prepare() while preparing query: ' . $e->getMessage());
            } else {
                return false;
            }
        }
        return $q;
    }





    function query ($sSQL, $bHalt = true, $nMode = 0, $nCol = 0)
    {
        // Wrapper around PDO::query().
        // THIS WRAPPER DOES NOT SUPPORT THE PDO::FETCH_CLASS OR PDO::FETCH_INTO MODE!
        try {
            if ($nMode || $nMode != PDO::FETCH_COLUMN) {
                $q = $this->PDO->query($sSQL);
            } else {
                $q = $this->PDO->query($sSQL, $nMode, $nCol);
            }
        } catch (PDOException $e) {
            if ($bHalt) {
                try {
                    @$this->PDO->rollBack(); // In case we were in a transaction. // FIXME; we can know from PHP >= 5.3.3.
                } catch (PDOException $eNoTransaction) {}
                // lovd_queryError() will call lovd_displayError() which will halt the system.
                lovd_queryError((defined('LOG_EVENT')? LOG_EVENT : 'Unknown'), $sSQL, 'Error in PDO::query() while running query: ' . $e->getMessage());
            } else {
                return false;
            }
        }
        return $q;
    }





    function rollBack ()
    {
        // Wrapper to PDO::rollBack(), necessary because we need to link it to the initiated PDO class and not just to parent.
        return $this->PDO->rollBack();
    }
}
?>
