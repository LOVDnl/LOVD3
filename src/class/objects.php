<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-21
 * Modified    : 2015-09-21
 * For LOVD    : 3.0-14
 *
 * Copyright   : 2004-2015 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Msc. Daan Asscheman <D.Asscheman@LUMC.nl>
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





class LOVD_Object {
    // This class is the base class which is inherited by other object classes.
    // It provides basic functionality for setting up forms and showing data.
    var $sObject = '';
    var $sTable = '';
    var $aFormData = array();
    var $aCheckMandatory = array();
    var $sSQLLoadEntry = '';
    var $aSQLViewEntry =
             array(
                    'SELECT' => '',
                    'FROM' => '',
                    'WHERE' => '',
                    'GROUP_BY' => '',
                  );
    var $aSQLViewList =
             array(
                    'SELECT' => '',
                    'FROM' => '',
                    'WHERE' => '',
                    'GROUP_BY' => '',
                    'HAVING' => '',
                    'ORDER_BY' => '',
                    'LIMIT' => '',
                  );
    var $aColumnsViewEntry = array();
    var $aColumnsViewList = array();
    var $sSortDefault = '';
    var $nID = 0;
    var $sRowID = ''; // FIXME; needs getter and setter?
    var $sRowLink = ''; // FIXME; needs getter and setter?
    var $nCount = '';





    function __construct()
    {
        // Default constructor.
        if (!$this->sTable) {
            $this->sTable = 'TABLE_' . strtoupper($this->sObject) . 'S';
        }
        if (!defined($this->sTable)) {
            lovd_displayError('ObjectError', 'Object::' . $this->sObject . ' requested with non-existing table \'' . $this->sTable . '\'.');
        }

        // Default query.
        if (!$this->aSQLViewList['SELECT']) { $this->aSQLViewList['SELECT'] = '*'; }
        if (!$this->aSQLViewList['FROM']) { $this->aSQLViewList['FROM'] = constant($this->sTable); }

        // Set default row ID and link for viewList().
//        $this->sRowID = strtolower($this->sObject) . '_{{ID}}';
        $this->sRowID = '{{ID}}'; // FIXME; having the object in front of it seems better, but then we need to isolate the ID using JS if we need it.
        // Default link example: users/00001.
        $this->sRowLink = strtolower($this->sObject) . 's/{{ID}}';
    }





    function autoExplode ($zData)
    {
        // Automatically explode GROUP_CONCAT values based on their name.
        foreach ($zData as $key => $val) {
            if ($key{0} == '_') {
                unset($zData[$key]);
                if (!empty($val)) {
                    if ($key{1} == '_') {
                        // Explode GROUP_CONCAT nested array
                        $aValues = explode(';;', $val);
                        $zData[ltrim($key, '_')] = array_map('explode', array_fill(0, count($aValues), ';'), $aValues);
                    } else {
                        // Explode GROUP_CONCAT array
                        $zData[ltrim($key, '_')] = explode(';', $val);
                    }
                } else {
                    $zData[ltrim($key, '_')] = array();
                }
            }
        }
        return $zData;
    }





    function checkFields ($aData, $zData = false)
    {
        // Checks fields before submission of data.
        global $_AUTH;
        $aForm = $this->getForm();
        if (!$aForm) {
            return false;
        }
        $aFormInfo = $aForm[0];
        if (!in_array($aFormInfo[0], array('GET', 'POST'))) {
            // We're not working on a full form array, possibly an incomplete VOT form.
            $aFormInfo = array('POST');
        } else {
            unset($aForm[0]);
        }

        if (lovd_getProjectFile() != '/import.php') {
            // Always mandatory... unless importing.
            $this->aCheckMandatory[] = 'password';
        }

        // Validate form by looking at the form itself, and check what's needed.
        foreach ($aForm as $aField) {
            if (!is_array($aField)) {
                // 'skip', 'hr', etc...
                continue;
            }
            @list($sHeader, $sHelp, $sType, $sName) = $aField;
            $sNameClean = preg_replace('/^\d{5}_/', '', $sName); // Remove prefix (transcriptid) that LOVD_TranscriptVariants puts there.
            if (lovd_getProjectFile() == '/import.php') {
                // During import, we don't mention the field names how they appear on screen, but using their IDs which are used in the file.
                $sHeader = $sName;
            }

            // Trim() all fields. We don't want those spaces in the database anyway.
            if (lovd_getProjectFile() != '/import.php' && isset($aData[$sName]) && !is_array($aData[$sName])) {
                $GLOBALS['_' . $aFormInfo[0]][$sName] = trim($GLOBALS['_' . $aFormInfo[0]][$sName]);
                $aData[$sName] = trim($aData[$sName]);
            }

            // Mandatory fields, as defined by child object.
            if (in_array($sName, $this->aCheckMandatory) && (!isset($aData[$sName]) || $aData[$sName] === '')) {
                lovd_errorAdd($sName, 'Please fill in the \'' . $sHeader . '\' field.');
            }

            // Checking free text fields for max length, data types, etc.
            if (in_array($sType, array('text', 'textarea')) && $sMySQLType = lovd_getColumnType(constant($this->sTable), $sNameClean)) {
                // FIXME; we're assuming here, that $sName equals the database name. Which is true in probably most/every case, but even so...
                // FIXME; select fields might also benefit from having this check (especially for import).

                // Check max length.
                $nMaxLength = lovd_getColumnLength(constant($this->sTable), $sNameClean);
                if (!empty($aData[$sName])) {
                    // For numerical columns, maxlength works differently!
                    if (in_array($sMySQLType, array('DECIMAL', 'DECIMAL_UNSIGNED', 'FLOAT', 'FLOAT_UNSIGNED', 'INT', 'INT_UNSIGNED'))) {
                        // SIGNED cols: negative values.
                        if (in_array($sMySQLType, array('DECIMAL', 'INT')) && (int) $aData[$sName] < (int)('-' . str_repeat('9', $nMaxLength))) {
                            lovd_errorAdd($sName, 'The \'' . $sHeader . '\' field is limited to numbers no lower than -' . str_repeat('9', $nMaxLength) . '.');
                        }
                        // ALL numerical cols (except floats): positive values.
                        if (substr($sMySQLType, 0, 5) != 'FLOAT' && (int) $aData[$sName] > (int) str_repeat('9', $nMaxLength)) {
                            lovd_errorAdd($sName, 'The \'' . $sHeader . '\' field is limited to numbers no higher than ' . str_repeat('9', $nMaxLength) . '.');
                        }
                    } elseif (strlen($aData[$sName]) > $nMaxLength) {
                        lovd_errorAdd($sName, 'The \'' . $sHeader . '\' field is limited to ' . $nMaxLength . ' characters, you entered ' . strlen($aData[$sName]) . '.');
                    }
                }

                // Check data type.
                if (!empty($aData[$sName])) {
                    switch ($sMySQLType) {
                        case 'DATE':
                            if (!lovd_matchDate($aData[$sName])) {
                                lovd_errorAdd($sName, 'The field \'' . $sHeader . '\' must contain a date in the format YYYY-MM-DD.');
                            }
                            break;
                        case 'DATETIME':
                            if (!preg_match('/^[0-9]{4}[.\/-][0-9]{2}[.\/-][0-9]{2}( [0-9]{2}\:[0-9]{2}\:[0-9]{2})?$/', $aData[$sName])) {
                                lovd_errorAdd($sName, 'The field \'' . $sHeader . '\' must contain a date, possibly including a time, in the format YYYY-MM-DD HH:MM:SS.');
                            }
                            break;
                        case 'DECIMAL':
                        case 'DECIMAL_UNSIGNED':
                        case 'FLOAT':
                        case 'FLOAT_UNSIGNED':
                            if (!is_numeric($aData[$sName]) || (substr($sMySQLType, -8) == 'UNSIGNED' && $aData[$sName] < 0)) {
                                lovd_errorAdd($sName, 'The field \'' . $sHeader . '\' must contain a' . (substr($sMySQLType, -8) != 'UNSIGNED'? '' : ' positive') . ' number.');
                            }
                            break;
                        case 'INT':
                        case 'INT_UNSIGNED':
                            if (!preg_match('/^' . ($sMySQLType != 'INT'? '' : '\-?') . '[0-9]*$/', $aData[$sName])) {
                                lovd_errorAdd($sName, 'The field \'' . $sHeader . '\' must contain a' . ($sMySQLType == 'INT'? 'n' : ' positive') . ' integer.');
                            }
                            break;
                    }
                }

            } elseif ($sType == 'select') {
                if (!empty($aField[7])) {
                    // The browser fails to send value if selection list w/ multiple selection options is left empty.
                    // This is causing notices in the code.
                    if (!isset($aData[$sName])) {
                        $GLOBALS['_' . $aFormInfo[0]][$sName] = array();
                        $aData[$sName] = array();
                    }
                }
                // Simple check on non-custom columns (custom columns have their own function for this) to see if the given value is actually allowed.
                // 0 is a valid entry for the check for mandatory fields, so we should also check if 0 is a valid entry in the selection list!
                if (strpos($sName, '/') === false && isset($aData[$sName]) && $aData[$sName] !== '') {
                    $Val = $aData[$sName];
                    $aOptions = array_keys($aField[5]);
                    if (lovd_getProjectFile() == '/import.php' && !is_array($Val)) {
                        $Val = explode(';', $Val); // Normally the form sends an array, but from the import I need to create an array.
                    } elseif (!is_array($Val)) {
                        $Val = array($Val);
                    }
                    foreach ($Val as $sValue) {
                        $sValue = trim($sValue); // Trim whitespace from $sValue to ensure match independent of whitespace.
                        if (!in_array($sValue, $aOptions)) {
                            if (lovd_getProjectFile() == '/import.php') {
                                lovd_errorAdd($sName, 'Please select a valid entry from the \'' . $sHeader . '\' selection box, \'' . strip_tags($sValue) . '\' is not a valid value. Please choose from these options: \'' . implode('\', \'', $aOptions) . '\'.');
                            } else {
                                lovd_errorAdd($sName, 'Please select a valid entry from the \'' . $sHeader . '\' selection box, \'' . strip_tags($sValue) . '\' is not a valid value.');
                            }
                        }
                    }
                }

            } elseif ($sType == 'checkbox') {
                // The browser fails to send value if checkbox is left empty.
                // This is causing problems sometimes with MySQL, since INT
                // columns can't receive an empty string if STRICT is on.
                if (!isset($aData[$sName])) {
                    $GLOBALS['_' . $aFormInfo[0]][$sName] = 0;
                    $aData[$sName] = 0;
                } elseif (!in_array($aData[$sName], array('0', '1'))) {
                    lovd_errorAdd($sName, 'The field \'' . $sHeader . '\' must contain either a \'0\' or a \'1\'.');
                }
            }

            if ($sName == 'password') {
                // Password is in the form, it must be checked. Assuming here that it is also considered mandatory.
                if (!empty($aData['password']) && !lovd_verifyPassword($aData['password'], $_AUTH['password'])) {
                    lovd_errorAdd('password', 'Please enter your correct password for authorization.');
                }
            }
        }
        return $aData;
    }





    function deleteEntry ($nID = false)
    {
        // Delete an entry from the database.
        global $_DB;

        if (!$nID) {
            // We were called, but the class wasn't initiated with an ID. Fail.
            lovd_displayError('LOVD-Lib', 'Objects::(' . $this->sObject . ')::deleteEntry() - Method didn\'t receive ID');
        } else {
            if ($this->getCount($nID)) {
                $sSQL = 'DELETE FROM ' . constant($this->sTable) . ' WHERE id = ?';
                if (!defined('LOG_EVENT')) {
                    define('LOG_EVENT', $this->sObject . '::deleteEntry()');
                }
                $q = $_DB->query($sSQL, array($nID));
                return true;
            } else {
                return false;
            }
        }
    }





    function generateRowID ($zData = false)
    {
        // Generates the row_id for the viewList rows.
        if ($zData && is_array($zData) && !empty($zData)) {
            if (!isset($zData['row_id'])) {
                if (isset($zData['id'])) {
                    if ($this->sRowID !== '') {
                        $zData['row_id'] = str_replace('{{ID}}', rawurlencode($zData['id']), $this->sRowID);
                        foreach ($zData as $key => $val) {
                            $zData['row_id'] = preg_replace('/\{\{' . preg_quote($key, '/') . '\}\}/', rawurlencode($val), $zData['row_id']);
                        }
                    } else {
                        $zData['row_id'] = $zData['id'];
                    }
                } else {
                    $zData['row_id'] = '';
                }
            }
        }
        return $zData;
    }





    function getCount ($nID = false)
    {
        // Returns the number of entries in the database table.
        // ViewEntry() and ViewList() call this function to see if data exists at all, and actually don't require a precise number.
        global $_DB;

        if ($nID) {
            $nCount = $_DB->query('SELECT COUNT(*) FROM ' . constant($this->sTable) . ' WHERE id = ?', array($nID))->fetchColumn();
        } else {
            if ($this->nCount !== '') {
                return $this->nCount;
            }
            $nCount = $_DB->query('SELECT COUNT(*) FROM ' . constant($this->sTable))->fetchColumn();
            $this->nCount = $nCount;
        }
        return $nCount;
    }





    function getForm ()
    {
        // Returns the $this->aFormData variable, to build a form.
        return $this->aFormData;
    }





    function getSortDefault ()
    {
        return $this->sSortDefault;
    }





    function insertEntry ($aData, $aFields = array())
    {
        // Inserts data in $aData into the database, using only fields defined in $aFields.
        global $_DB;

        if (!is_array($aData) || !count($aData)) {
            lovd_displayError('LOVD-Lib', 'Objects::(' . $this->sObject . ')::insertEntry() - Method didn\'t receive data array');
        } elseif (!is_array($aFields) || !count($aFields)) {
            $aFields = array_keys($aData);
        } else {
            // Non-numerical keys or a missing key 0 messes up the SQL creation.
            $aFields = array_values($aFields);
        }

        // Query text.
        $sSQL = 'INSERT INTO ' . constant($this->sTable) . ' (';
        $aSQL = array();
        foreach ($aFields as $key => $sField) {
            $sSQL .= (!$key? '' : ', ') . '`' . $sField . '`';
            if (!isset($aData[$sField])) {
                // Field may be not set, make sure it is (happens in very rare cases).
                $aData[$sField] = '';
            }
            if ($aData[$sField] === '' && in_array(substr(lovd_getColumnType(constant($this->sTable), $sField), 0, 3), array('INT', 'DAT', 'DEC', 'FLO'))) {
                $aData[$sField] = NULL;
            }
            $aSQL[] = $aData[$sField];
        }
        $sSQL .= ') VALUES (?' . str_repeat(', ?', count($aFields) - 1) . ')';

        if (!defined('LOG_EVENT')) {
            define('LOG_EVENT', $this->sObject . '::insertEntry()');
        }
        $q = $_DB->query($sSQL, $aSQL, true, true);

        $nID = $_DB->lastInsertId();
        if (substr(lovd_getColumnType(constant($this->sTable), 'id'), 0, 3) == 'INT') {
            $nID = sprintf('%0' . lovd_getColumnLength(constant($this->sTable), 'id') . 'd', $nID);
        }
        return $nID;
    }





    function loadEntry ($nID = false)
    {
        // Loads and returns an entry from the database.
        global $_DB, $_T;

        if (empty($nID)) {
            // We were called, but the class wasn't initiated with an ID. Fail.
            lovd_displayError('LOVD-Lib', 'Objects::(' . $this->sObject . ')::loadEntry() - Method didn\'t receive ID');
        }

        // Build query.
        if ($this->sSQLLoadEntry) {
            $sSQL = $this->sSQLLoadEntry;
        } else {
            $sSQL = 'SELECT * FROM ' . constant($this->sTable) . ' WHERE id = ?';
        }
        $q = $_DB->query($sSQL, array($nID), false);
        if ($q) {
            $zData = $q->fetchAssoc();
        }
        if (!$q || !$zData) {
            $sError = $_DB->formatError(); // Save the PDO error before it disappears.

            $_T->printHeader();
            if (defined('PAGE_TITLE')) {
                $_T->printTitle();
            }

            if ($sError) {
                lovd_queryError($this->sObject . '::loadEntry()', $sSQL, $sError);
            }

            lovd_showInfoTable('No such ID!', 'stop');

            $_T->printFooter();
            exit;

        } else {
            $this->nID = $nID;
        }

        $zData = $this->autoExplode($zData);

        return $zData;
    }





    function prepareData ($zData = '', $sView = 'list')
    {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.
        // Also quotes all data with htmlspecialchars(), to prevent XSS.
        global $_AUTH;

        if (!is_array($zData)) {
            $zData = array();
        }

        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Quote special characters, disallowing HTML and other tricks.
        $zData = lovd_php_htmlspecialchars($zData);

        $aDateColumns = array('created_date', 'edited_date', 'updated_date', 'valid_from', 'valid_to');
        foreach($aDateColumns as $sDateColumn) {
            $zData[$sDateColumn . ($sView == 'list'? '' : '_')] = (!empty($zData[$sDateColumn])? $zData[$sDateColumn] : 'N/A');
        }

        if ($sView == 'list') {
            // By default, we put an anchor in the id_ field, if present.
            if ($zData['row_link'] && array_key_exists('id_', $this->aColumnsViewList) && $zData['id']) {
                $zData['id_'] = '<A href="' . $zData['row_link'] . '" class="hide">' . $zData['id'] . '</A>';
            }
            // If we find an owned_by_ field, and an owner array, we set up the popups as well (but not for the "LOVD" user).
            if (isset($zData['owned_by']) && (int) $zData['owned_by'] && !empty($zData['owner'])) {
                if(!is_array($zData['owner'][0])) {
                    $zData['owner'] = array($zData['owner']);
                }
                // We are going to overwrite the 'owned_by_' field.
                $zData['owned_by_'] = '';
                foreach($zData['owner'] as $aLinkData) {
                    if (count($aLinkData) >= 6) {
                        list($nID, $sName, $sEmail, $sInstitute, $sDepartment, $sCountryID) = $aLinkData;
                        // Call the tooltip function with a request to move the tooltip left, because "Owner" is often the last column in the table, and we don't want it to run off the page. I have found no way of moving the tooltip left whenever it's enlarging the document size.
                        $zData['owned_by_'] .= (!$zData['owned_by_']? '' : ', ') . '<SPAN class="custom_link" onmouseover="lovd_showToolTip(\'<TABLE border=0 cellpadding=0 cellspacing=0 width=350 class=S11><TR><TH valign=top>User&nbsp;ID</TH><TD>' . ($_AUTH['level'] < LEVEL_MANAGER? $nID : '<A href=users/' . $nID . '>' . $nID . '</A>') . '</TD></TR><TR><TH valign=top>Name</TH><TD>' . $sName . '</TD></TR><TR><TH valign=top>Email&nbsp;address</TH><TD>' . str_replace("\r\n", '<BR>', lovd_hideEmail($sEmail)) . '</TD></TR><TR><TH valign=top>Institute</TH><TD>' . $sInstitute . '</TD></TR><TR><TH valign=top>Department</TH><TD>' . $sDepartment . '</TD></TR><TR><TH valign=top>Country</TH><TD>' . $sCountryID . '</TD></TR></TABLE>\', this, [-200, 0]);">' . $sName . '</SPAN>';
                    }
                }
            }

        } else {
            // Add links to users from *_by fields.
            $aUserColumns = array('owned_by', 'created_by', 'edited_by', 'updated_by', 'deleted_by');
            foreach($aUserColumns as $sUserColumn) {
                if (empty($zData[$sUserColumn])) {
                    $zData[$sUserColumn . '_'] = 'N/A';
                } elseif ($_AUTH && $zData[$sUserColumn] != '00000') {
                    $zData[$sUserColumn . '_'] = '<A href="users/' . $zData[$sUserColumn] . '">' . $zData[$sUserColumn . '_'] . '</A>';
                }
            }
        }

        return $zData;
    }





    function setDefaultValues ()
    {
        // Stub; this thing is just here to prevent an error message when a child class has not defined this function.
        return true;
    }





    function setRowID ($sViewListID, $sRowID)
    {
        // Set the RowID for the specified viewList & set the $_SESSION variable so that it will not keep reloading the previous ID.
        $this->sRowID = $sRowID;
        $sViewListID = str_replace('viewlistForm_', '', $sViewListID);
        if (isset($_SESSION['viewlists'][$sViewListID]['row_id'])) {
            $_SESSION['viewlists'][$sViewListID]['row_id'] = $sRowID;
        }
    }





    function setRowLink ($sViewListID, $sRowLink)
    {
        // Set the RowLink for the specified viewList & set the $_SESSION variable so that it will not keep reloading the previous link.
        $this->sRowLink = $sRowLink;
        $sViewListID = str_replace('viewlistForm_', '', $sViewListID);
        if (isset($_SESSION['viewlists'][$sViewListID]['row_link'])) {
            $_SESSION['viewlists'][$sViewListID]['row_link'] = $sRowLink;
        }
    }





    function setSortDefault ($sCol)
    {
        if (!empty($sCol) && array_key_exists($sCol, $this->aColumnsViewList)) {
            $this->sSortDefault = $sCol;
            return true;
        } else {
            return false;
        }
    }





    function unsetColsByAuthLevel ()
    {
        // Unset columns not allowed to be visible for the current user level.
        global $_AUTH;

        foreach($this->aColumnsViewEntry as $sCol => $Col) {
            if (is_array($Col) && (!$_AUTH || $_AUTH['level'] < $Col[1])) {
                unset($this->aColumnsViewEntry[$sCol]);
            }
        }

        foreach($this->aColumnsViewList as $sCol => $aCol) {
            if (isset($aCol['auth']) && (!$_AUTH || $_AUTH['level'] < $aCol['auth'])) {
                unset($this->aColumnsViewList[$sCol]);
            }
        }
    }





    function updateEntry ($nID, $aData, $aFields = array())
    {
        // Updates entry $nID with data from $aData in the database, changing only fields defined in $aFields.
        global $_DB;

        if (!trim($nID)) {
            lovd_displayError('LOVD-Lib', 'Objects::(' . $this->sObject . ')::updateEntry() - Method didn\'t receive ID');
        } elseif (!is_array($aData) || !count($aData)) {
            lovd_displayError('LOVD-Lib', 'Objects::(' . $this->sObject . ')::updateEntry() - Method didn\'t receive data array');
        } elseif (!is_array($aFields) || !count($aFields)) {
            $aFields = array_keys($aData);
        }

        // Query text.
        $sSQL = 'UPDATE ' . constant($this->sTable) . ' SET ';
        $aSQL = array();
        foreach ($aFields as $key => $sField) {
            $sSQL .= (!$key? '' : ', ') . '`' . $sField . '` = ?';
            if (!isset($aData[$sField])) {
                // Field may be not set, make sure it is (happens in very rare cases).
                $aData[$sField] = '';
            }
            if ($aData[$sField] === '' && in_array(substr(lovd_getColumnType(constant($this->sTable), $sField), 0, 3), array('INT', 'DAT', 'DEC', 'FLO'))) {
                $aData[$sField] = NULL;
            }
            $aSQL[] = $aData[$sField];
        }
        $sSQL .= ' WHERE id = ?';
        $aSQL[] = $nID;

        if (!defined('LOG_EVENT')) {
            define('LOG_EVENT', $this->sObject . '::updateEntry()');
        }
        $q = $_DB->query($sSQL, $aSQL, true, true);

        return $q->rowCount();
    }





    function viewEntry ($nID = false)
    {
        // Views just one entry from the database.
        global $_DB, $_T;

        if (empty($nID)) {
            // We were called, but the class wasn't initiated with an ID. Fail.
            lovd_displayError('LOVD-Lib', 'Objects::(' . $this->sObject . ')::viewEntry() - Method didn\'t receive ID');
        }

        $bAjax = (substr(lovd_getProjectFile(), 0, 6) == '/ajax/');

        // Check existence of entry.
        $n = $this->getCount($nID);
        if (!$n) {
            global $_SETT, $_STAT, $_AUTH;
            lovd_showInfoTable('No such ID!', 'stop');
            if (!$bAjax) {
                $_T->printFooter();
            }
            exit;
        }

        if (!defined('LOG_EVENT')) {
            define('LOG_EVENT', $this->sObject . '::viewEntry()');
        }

        // Manipulate WHERE to include ID, and build query.
        $sTableName = constant($this->sTable);
        // Try to get the name of the ID column in MySQL. I'd rather not do it this way, but even worse would be to have yet another variable.
        if (!empty($this->aColumnsViewList['id']['db'][0])) {
            $sIDColumn = $this->aColumnsViewList['id']['db'][0];
        } else {
            if (preg_match('/' . constant($this->sTable) . ' AS ([a-z]+)( .+)?$/', $this->aSQLViewEntry['FROM'], $aRegs)) {
                // An alias was defined. Use it.
                $sIDColumn = $aRegs[1] . '.id';
            } else {
                // Use the normal table name.
                $sIDColumn = constant($this->sTable) . '.id';
            }
        }
        $this->aSQLViewEntry['WHERE'] = $sIDColumn . ' = ?' . (!$this->aSQLViewEntry['WHERE']? '' : ' AND ' . $this->aSQLViewEntry['WHERE']);
        $sSQL = 'SELECT ' . $this->aSQLViewEntry['SELECT'] .
               ' FROM ' . $this->aSQLViewEntry['FROM'] .
               ' WHERE ' . $this->aSQLViewEntry['WHERE'] .
              (!$this->aSQLViewEntry['GROUP_BY']? '' :
               ' GROUP BY ' . $this->aSQLViewEntry['GROUP_BY']);

        // Run the actual query.
        $zData = $_DB->query($sSQL, array($nID))->fetchAssoc();
        // If the user has no rights based on the statusid column, we don't have a $zData.
        if (!$zData) {
            // Don't give away information about the ID: just pretend the entry does not exist.
            global $_SETT, $_STAT, $_AUTH;
            lovd_showInfoTable('No such ID!', 'stop');
            $_T->printFooter();
            exit;
        }

        $zData = $this->autoExplode($zData);

        $zData = $this->prepareData($zData, 'entry');

        // Print the data.
        print('      <TABLE border="0" cellpadding="0" cellspacing="1" width="600" class="data">');
        foreach ($this->aColumnsViewEntry as $sField => $header) {
            $sHeader = (is_array($header)? $header[0] : $header);
            if (preg_match("/TableStart/", $sField)) {
                print('      <TABLE border="0" cellpadding="0" cellspacing="1" width="600" class="data">');
            } elseif (preg_match("/TableHeader/", $sField)) {
                print("\n" .
                      '        <TR>' . "\n" .
                      '          <TH colspan="2" class="S15" valign="top">' . $sHeader . '</TH></TR>');
            } elseif (preg_match("/TableEnd/", $sField)) {
                print('</TABLE>' . "\n\n");
            } elseif (preg_match("/HR/", $sField)) {
                print("\n" .
                      '      <HR>' . "\n");
            } else {
                print("\n" .
                      '        <TR>' . "\n" .
                      '          <TH valign="top">' . str_replace(' ', '&nbsp;', $sHeader) . '</TH>' . "\n" .
                      '          <TD>' . ($zData[$sField] === ''? '-' : str_replace(array("\r\n", "\r", "\n"), '<BR>', $zData[$sField])) . '</TD></TR>');
            }
        }
        print('</TABLE>' . "\n\n");
        return $zData;
    }





    function viewList ($sViewListID = false, $aColsToSkip = array(), $bNoHistory = false, $bHideNav = false, $bOptions = false, $bOnlyRows = false)
    {
        // Views list of entries in the database, allowing search.
        global $_DB, $_INI, $_SETT;

        if (!defined('LOG_EVENT')) {
           define('LOG_EVENT', $this->sObject . '::viewList()');
        }
        if (FORMAT == 'text/plain' && !defined('FORMAT_ALLOW_TEXTPLAIN')) {
            die('text/plain not allowed here');
        }

        $bAjax = (substr(lovd_getProjectFile(), 0, 6) == '/ajax/');

        // ViewLists need an ID to identify the specific viewList, in case there are a few in one document.
        if (!$sViewListID || !is_string($sViewListID)) {
            $sViewListID = lovd_generateRandomID();
        } else {
            $sViewListID = preg_replace('/[^A-Z0-9._-]+/i', '', $sViewListID);
        }

        if (!is_array($aColsToSkip)) {
            $aColsToSkip = array($aColsToSkip);
        }
        foreach ($this->aColumnsViewList as $sCol => $aCol) {
            if (!$aCol['view'] && !in_array($sCol, $aColsToSkip)) {
                $aColsToSkip[] = $sCol;
            }
        }

        require_once ROOT_PATH . 'inc-lib-viewlist.php';

        // First, check if entries are in the database at all.
        $nTotal = $this->getCount();
        if (!$nTotal && FORMAT == 'text/html') {
            if ($bOnlyRows) {
                die('0'); // Silent error.
            }
            lovd_showInfoTable('No entries in the database yet!', 'stop');
            return 0;
        }

        // SEARCH: Advanced text search.
        $WHERE = '';
        $HAVING = '';
        $aArguments = array(
                        'WHERE' => array(),
                        'HAVING' => array()
                           );
        $aBadSyntaxColumns = array();
        $aColTypes = array(); // For describing the search expressions in the mouseover of the input field.
        foreach ($this->aColumnsViewList as $sColumn => $aCol) {
            if (!empty($aCol['db'][2]) && isset($_GET['search_' . $sColumn]) && trim($_GET['search_' . $sColumn]) !== '') {
                $CLAUSE = (strpos($aCol['db'][0], '.') === false && strpos($aCol['db'][0], '/') === false? 'HAVING' : 'WHERE');
                if ($aCol['db'][2] !== true) {
                    // Column type of an alias is given by LOVD.
                    $sColType = $aCol['db'][2];
                } else {
                    if (preg_match('/^[a-z0-9]{1,3}\.[a-z_]+$/i', $aCol['db'][0])) {
                        list($sAlias, $sColName) = explode('.', $aCol['db'][0]);
                        if (preg_match('/(' . TABLEPREFIX . '_[a-z0-9_]+) AS ' . $sAlias . '\b/', $this->aSQLViewList['FROM'], $aMatches)) {
                            $sTable = $aMatches[1];
                        } else {
                            // Alias was not valid, default col type to TEXT.
                            $sTable = '';
                        }
                    } else {
                        $sColName = trim($aCol['db'][0], '`');
                        $sTable = constant($this->sTable);
                    }
                    $sColType = lovd_getColumnType($sTable, $sColName);
                }
                $aColTypes[$sColumn] = $sColType;
                // Allow for searches where the order of words is forced by enclosing the values with double quotes;
                // Replace spaces in sentences between double quotes so they don't get exploded.
                if ($sColType == 'DATETIME') {
                    $sSearch = preg_replace('/ (\d)/', "{{SPACE}}$1", trim($_GET['search_' . $sColumn]));
                } else {
                    $sSearch = preg_replace_callback('/("[^"]+")/', create_function('$aRegs', 'return str_replace(\' \', \'{{SPACE}}\', $aRegs[1]);'), trim($_GET['search_' . $sColumn]));
                }
                $aWords = explode(' ', $sSearch);
                foreach ($aWords as $sWord) {
                    if ($sWord !== '') {
                        $sWord = lovd_escapeSearchTerm($sWord);
                        $aOR = (preg_match('/^[^|]+(\|[^|]+)+$/', $sWord)? explode('|', $sWord) : array($sWord));
                        $$CLAUSE .= ($$CLAUSE? ' AND ' : '') . (!empty($aOR[1])? '(' : '');
                        foreach ($aOR as $nTerm => $sTerm) {
                            $$CLAUSE .= ($nTerm? ' OR ' : '');
                            switch ($sColType) {
                                case 'DECIMAL_UNSIGNED':
                                case 'DECIMAL':
                                case 'FLOAT_UNSIGNED':
                                case 'FLOAT':
                                case 'INT_UNSIGNED':
                                case 'INT':
                                    if (preg_match('/^([><]=?|!)?(-?\d+(\.\d+)?)$/', $sTerm, $aMatches)) {
                                        $sOperator = $aMatches[1];
                                        $sTerm = $aMatches[2];
                                        if ($sOperator) {
                                            $sOperator = (substr($sOperator, 0, 1) == '!'? '!=' : $sOperator);
                                        } else {
                                            $sOperator = '=';
                                        }
                                        $$CLAUSE .= '(' . $aCol['db'][0] . ' ' . $sOperator . ' ' . ($_INI['database']['driver'] != 'sqlite'? '?' : 'CAST(? AS NUMERIC)') . ($sOperator == '!='? ' OR ' . $aCol['db'][0] . ' IS NULL)' : ')');
                                        $aArguments[$CLAUSE][] = $sTerm;
                                    } elseif (preg_match('/^!?=""$/', $sTerm)) {
                                        // Numeric fields cannot be empty, they are NULL. So searching for ="" must return all NULL values.
                                        $$CLAUSE .= $aCol['db'][0] . ' IS ' . (substr($sTerm, 0, 1) == '!'? 'NOT ' : '') . 'NULL';
                                    } elseif ($aCol['view']) {
                                        $aBadSyntaxColumns[] = $aCol['view'][0];
                                    }
                                    break;
                                case 'DATE':
                                case 'DATETIME':
                                    if (preg_match('/^([><]=?|!)?(\d{4})(?:(-\d{2})' . ($sColType == 'DATETIME'? '(?:(-\d{2})(?:( \d{2})(?:(:\d{2})(:\d{2})?)?)?)?)?' : '(-\d{2})?)?') . '$/', $sTerm, $aMatches)) {
                                        @list(, $sOperator, $nYear, $nMonth, $nDay, $nHour, $nMinute, $nSecond) = $aMatches;
                                        if (!checkdate(($nMonth? substr($nMonth, 1) : '01'), ($nDay? substr($nDay, 1) : '01'), $nYear) && $aCol['view']) {
                                            $aBadSyntaxColumns[] = $aCol['view'][0];
                                        }
                                        if (((isset($nHour) && ($nHour < 0 || $nHour > 23)) || (isset($nMinute) && ($nMinute < 0 || $nMinute > 59)) || (isset($nSecond) && ($nSecond < 0 || $nSecond > 59))) && $aCol['view']) {
                                            $aBadSyntaxColumns[] = $aCol['view'][0];
                                        }
                                        // Create $aTerms arrays, pre-filled with date components. These components will be overwritten later by $aMatches, if they are given by the user.
                                        switch ($sOperator) {
                                            case '>':
                                            case '<=':
                                                $aTerms = array(3 => '-12', '-31', ' 23', ':59', ':59'); // FIXME; some databases may not like this on DATE columns.
                                                break;
                                            case '<':
                                            case '>=':
                                                $aTerms = array(3 => '-01', '-01', ' 00', ':00', ':00'); // FIXME; some databases may not like this on DATE columns.
                                                break;
                                            case '!':
                                            default:
                                                if (($sColType == 'DATE' && isset($nDay)) || ($sColType == 'DATETIME' && isset($nSecond))) {
                                                    $sOperator .= '='; // != or =
                                                } else {
                                                    $sOperator = ($sOperator == '!'? 'NOT ' : '') . 'LIKE';
                                                }
                                                $aTerms = array(3 => '', '', '', '', '');
                                                break;
                                        }
                                        unset($aMatches[0], $aMatches[1]);
                                        // Replace our default date components by the ones given by the user.
                                        $aTerms = $aMatches + $aTerms;
                                        ksort($aTerms);
                                        $sTerms = implode($aTerms);
                                        $$CLAUSE .= '(' . $aCol['db'][0] . ' ' . $sOperator . ' ?' . ($sOperator == 'NOT LIKE'? ' OR ' . $aCol['db'][0] . ' IS NULL)' : ')');
                                        $aArguments[$CLAUSE][] = $sTerms . (substr($sOperator, -4) == 'LIKE'? '%' : '');
                                    } elseif (preg_match('/^!?=""$/', $sTerm)) {
                                        // DATE(TIME) fields cannot be empty, they are NULL. So searching for ="" must return all NULL values.
                                        $$CLAUSE .= $aCol['db'][0] . ' IS ' . (substr($sTerm, 0, 1) == '!'? 'NOT ' : '') . 'NULL';
                                    } elseif ($aCol['view']) {
                                        $aBadSyntaxColumns[] = $aCol['view'][0];
                                    }
                                    break;
                                default:
                                    if (preg_match('/^!?"?([^"]+)"?$/', $sTerm, $aMatches)) {
                                        $sOperator = (substr($sTerm, 0, 1) == '!'? 'NOT ' : '') . 'LIKE';
                                        $$CLAUSE .= '(' . $aCol['db'][0] . ' ' . $sOperator . ' ?' . ($sOperator == 'NOT LIKE'? ' OR ' . $aCol['db'][0] . ' IS NULL)' : ')');
                                        $aArguments[$CLAUSE][] = '%' . $aMatches[1] . '%';
                                    } elseif (preg_match('/^!?=""$/', $sTerm)) {
                                        $bNot = (substr($sTerm, 0, 1) == '!');
                                        if ($bNot) {
                                            $$CLAUSE .= '(' . $aCol['db'][0] . ' != "" AND ' . $aCol['db'][0] . ' IS NOT NULL)';
                                        } else {
                                            $$CLAUSE .= '(' . $aCol['db'][0] . ' = "" OR ' . $aCol['db'][0] . ' IS NULL)';
                                        }
                                    } elseif (preg_match('/^!?="([^"]*)"$/', $sTerm, $aMatches)) {
                                        $sOperator = (substr($sTerm, 0, 1) == '!'? '!=' : '=');
                                        $$CLAUSE .= '(' . $aCol['db'][0] . ' ' . $sOperator . ' ?' . ($sOperator == '!='? ' OR ' . $aCol['db'][0] . ' IS NULL)' : ')');
                                        // 2013-07-25; 3.0-07; When not using LIKE, undo escaping done by lovd_escapeSearchTerm().
                                        $aArguments[$CLAUSE][] = str_replace(array('\%', '\_'), array('%', '_'), $aMatches[1]);
                                    } elseif ($aCol['view']) {
                                        $aBadSyntaxColumns[] = $aCol['view'][0];
                                    }
                                    break;
                            }
                        }
                        $$CLAUSE .= (!empty($aOR[1])? ')' : '');
                    }
                }
            }
        }

        if ($WHERE) {
            $this->aSQLViewList['WHERE'] .= ($this->aSQLViewList['WHERE']? ' AND ' : '') . $WHERE;
        }
        if ($HAVING) {
            $this->aSQLViewList['HAVING'] .= ($this->aSQLViewList['HAVING']? ' AND ' : '') . $HAVING;
        }
        // SORT: Current settings, also implementing XSS check.
        if (!empty($_GET['order']) && $_GET['order'] === strip_tags($_GET['order'])) {
            $aOrder = explode(',', $_GET['order']);
        } else {
            $aOrder = array('', '');
        }

        // SORT: Verify request and set default.
        if (empty($this->aColumnsViewList[$aOrder[0]]['db'][1])) {
            $aOrder[0] = $this->sSortDefault;
        }
        if ($aOrder[1] != 'ASC' && $aOrder[1] != 'DESC') {
            $aOrder[1] = $this->aColumnsViewList[$aOrder[0]]['db'][1];
        }

        $sSQLOrderBy = $this->aColumnsViewList[$aOrder[0]]['db'][0] . ' ' . $aOrder[1];
        if (in_array($aOrder[0], array('chromosome','VariantOnGenome/DNA'))) {
            // 2014-03-07; 3.0-10; We need to find the table alias of the VOG or genes table, because otherwise MySQL fails here ('chromosome' is ambiguous) if both are joined.
            // 2014-04-28; 3.0-10; Prefer the genes table, since it joins to VOG as well, but may not have results which messes up the order.
            $sAlias = '';
            if (preg_match('/' . TABLE_GENES . ' AS ([a-z]+)/i', $this->aSQLViewList['FROM'], $aRegs)) {
                $sAlias = $aRegs[1];
            } elseif (preg_match('/' . TABLE_VARIANTS . ' AS ([a-z]+)/i', $this->aSQLViewList['FROM'], $aRegs)) {
                $sAlias = $aRegs[1];
            }
            $this->aSQLViewList['FROM'] .= ' LEFT OUTER JOIN ' . TABLE_CHROMOSOMES . ' AS chr ON (' . (!$sAlias? '' : $sAlias . '.') . 'chromosome = chr.name)';
            $sSQLOrderBy = 'chr.sort_id ' . $aOrder[1];
            if ($aOrder[0] == 'VariantOnGenome/DNA') {
                $sSQLOrderBy .= ', position_g_start ' . $aOrder[1] . ', position_g_end ' . $aOrder[1] . ', `VariantOnGenome/DNA` ' . $aOrder[1];
            }
        } elseif ($aOrder[0] == 'VariantOnTranscript/DNA') {
            $sSQLOrderBy = 'position_c_start ' . $aOrder[1] . ', position_c_start_intron ' . $aOrder[1] . ', position_c_end ' . $aOrder[1] . ', position_c_end_intron ' . $aOrder[1] . ', `VariantOnTranscript/DNA` ' . $aOrder[1];
        }
        $this->aSQLViewList['ORDER_BY'] = $sSQLOrderBy . (empty($this->aSQLViewList['ORDER_BY'])? '' : ', ' . $this->aSQLViewList['ORDER_BY']);



        // Only print stuff if we're not in Ajax right now.
        if (!$bAjax && FORMAT == 'text/html') {
            // Keep the URL clean; disable any fields that are not used.
            lovd_includeJS('inc-js-viewlist.php' . (!$bNoHistory? '' : '?nohistory'));
            lovd_includeJS('inc-js-tooltip.php');

            // Print form; required for sorting and searching.
            // Because we don't want the form to submit itself while we are waiting for the Ajax response, we need to kill the native submit() functionality.
            print('      <FORM action="' . CURRENT_PATH . '" method="get" id="viewlistForm_' . $sViewListID . '" style="margin : 0px;" onsubmit="return false;">' . "\n" .
                  '        <INPUT type="hidden" name="viewlistid" value="' . $sViewListID . '">' . "\n" .
                  '        <INPUT type="hidden" name="object" value="' . $this->sObject . '">' . "\n" .
                  (!isset($this->sObjectID)? '' :
                  '        <INPUT type="hidden" name="object_id" value="' . $this->sObjectID . '">' . "\n") . // The ID of the gene for VOT viewLists, the ID of the disease for phenotype viewLists, the object list for custom viewLists.
                  (!isset($this->nID)? '' :
                  '        <INPUT type="hidden" name="id" value="' . $this->nID . '">' . "\n") . // The ID of the VOG for VOT viewLists, the ID of the individual for phenotype viewLists, the (optional) gene for custom viewLists.
// FIXME; do we ever use ACTION in a ViewList? Wait until we've made variants.php to know for sure.
// FIXME; if we do need to send action, we can't do it this way... URL?action=&bla=bla does not get ACTION recognized.
                  (!ACTION? '' :
                  '        <INPUT type="hidden" name="' . ACTION . '" value="">' . "\n") .
                  '        <INPUT type="hidden" name="order" value="' . implode(',', $aOrder) . '">' . "\n");
            // Skipping (permanently hiding) columns.
            foreach ($aColsToSkip as $sCol) {
                if (array_key_exists($sCol, $this->aColumnsViewList)) {
                    // Internet Explorer refuses to submit input with equal names. If names are different, everything works fine.
                    // Somebody please tell me it's a bug and nobody's logical thinking. Had to include $sCol to make it work.
                    print('        <INPUT type="hidden" name="skip[' . $sCol . ']" value="' . $sCol . '">' . "\n");
                    // Check if we're skipping columns, that do have a search value. If so, it needs to be sent on like this.
                    if (isset($_GET['search_' . $sCol])) {
                        print('        <INPUT type="hidden" name="search_' . $sCol . '" value="' . htmlspecialchars($_GET['search_' . $sCol]) . '">' . "\n");
                    }
                }
            }
            if ($bHideNav) {
                print('        <INPUT type="hidden" name="hidenav" value="true">' . "\n");
            }
            if ($bOptions) {
                print('        <INPUT type="hidden" name="options" value="true">' . "\n");
            }
            print("\n");
        }

        // Make a reference variable of the session for cleaner code.
        $aSessionViewList =& $_SESSION['viewlists'][$sViewListID];

        // To make row ids persist when the viewList is refreshed, we must store the row id in $_SESSION.
        if (!empty($aSessionViewList['row_id'])) {
            $this->sRowID = $aSessionViewList['row_id'];
        } else {
            $aSessionViewList['row_id'] = $this->sRowID; // Implies array creation.
        }

        // To make row links persist when the viewList is refreshed, we must store the row link in $_SESSION.
        if (!empty($aSessionViewList['row_link'])) {
            $this->sRowLink = $aSessionViewList['row_link'];
        } else {
            $aSessionViewList['row_link'] = $this->sRowLink; // Implies array creation.
        }

        $nTotal = 0;
        if (!count($aBadSyntaxColumns)) {
            // Using the SQL_CALC_FOUND_ROWS technique to find the amount of hits in one go.
            // There is talk about a possible race condition using this technique on the mysql_num_rows man page, but I could find no evidence of it's existence on InnoDB tables.
            // Just to be sure, I'm implementing a serializable transaction, which should lock the table between the two SELECT queries to ensure proper results.
            // Last checked 2010-01-25, by Ivo Fokkema.
            $_DB->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
            $_DB->beginTransaction();

            // Build argument list.
            $aArgs = array();
            foreach ($aArguments['WHERE'] as $aArg) {
                $aArgs[] = $aArg;
            }
            foreach($aArguments['HAVING'] as $aArg) {
                $aArgs[] = $aArg;
            }

            // For ALL viewlists, we store the number of hits that we get, including the current filters.
            // For large tables, using SQL_CALC_FOUND_ROWS takes a lot of time, also still quite a lot for smaller result sets, since the entire table needs to be read out.
            //   Unfortunately, we can't automatically get us an SQL_CALC_FOUND_ROWS which leaves out unnecessary joins. Is there a way to do this?
            // ORDER BY is absolutely killing on large result sets, but when used you might as well use SQL_CALC_FOUND_ROWS, since it needs to read the entire table anyways.
            // So, long time to retrieve count (>1s) => no SQL_CALC_FOUND_ROWS and no sort.
            // Count OK (<=1s), but big result set (250K) => no sort. ($_SETT['lists']['max_sortable_rows'])

            // 1) If we don't have a count in memory, request count separately, using SQL_CALC_FOUND_ROWS, since it handles all complex queries.
            // Also if last count was >30min ago, request again.
            $bTrueCount = false; // Indicates whether or not we are sure about the number of results.
            $sFilterMD5 = md5($WHERE . '||' . $HAVING . '||' . implode('|', $aArgs)); // A signature for the filters, NOTE that this depends on the column order!
            if (!isset($aSessionViewList['counts'][$sFilterMD5]['n'])) {
                $t = microtime(true);
                if ($_INI['database']['driver'] == 'mysql') {
                    $_DB->query('SELECT SQL_CALC_FOUND_ROWS ' . $this->aSQLViewList['SELECT'] .
                        ' FROM ' . $this->aSQLViewList['FROM'] .
                        (!$this->aSQLViewList['WHERE']? '' :
                            ' WHERE ' . $this->aSQLViewList['WHERE']) .
                        (!$this->aSQLViewList['GROUP_BY']? '' :
                            ' GROUP BY ' . $this->aSQLViewList['GROUP_BY']) .
                        (!$this->aSQLViewList['HAVING']? '' :
                            ' HAVING ' . $this->aSQLViewList['HAVING']) .
                        ' LIMIT 0', $aArgs);
                    // Now, get the total number of hits if no LIMIT was used. Note that $nTotal gets overwritten here.
                    $nTotal = $_DB->query('SELECT FOUND_ROWS()')->fetchColumn();
                } else {
                    // Super inefficient, only for low-volume (sqlite) databases!
                    $nTotal = count($_DB->query('SELECT ' . $this->aSQLViewList['SELECT'] .
                        ' FROM ' . $this->aSQLViewList['FROM'] .
                        (!$this->aSQLViewList['WHERE']? '' :
                            ' WHERE ' . $this->aSQLViewList['WHERE']) .
                        (!$this->aSQLViewList['GROUP_BY']? '' :
                            ' GROUP BY ' . $this->aSQLViewList['GROUP_BY']) .
                        (!$this->aSQLViewList['HAVING']? '' :
                            ' HAVING ' . $this->aSQLViewList['HAVING']), $aArgs)->fetchAllColumn());
                }
                $tQ = microtime(true) - $t;
                $aSessionViewList['counts'][$sFilterMD5]['n'] = $nTotal;
                $aSessionViewList['counts'][$sFilterMD5]['t'] = $tQ;
                $aSessionViewList['counts'][$sFilterMD5]['d'] = time();
                $bTrueCount = true;
            }



            // Manipulate SELECT to include SQL_CALC_FOUND_ROWS.
            $bSQLCALCFOUNDROWS = false;
            if (!$bTrueCount && $_INI['database']['driver'] == 'mysql' && ($aSessionViewList['counts'][$sFilterMD5]['t'] < 1 || $aSessionViewList['counts'][$sFilterMD5]['d'] < (time() - (60*15)))) {
                // But only if we're using MySQL and it takes less than a second to get the correct number of results, or it's been more than 15 minutes since the last check!
                $this->aSQLViewList['SELECT'] = 'SQL_CALC_FOUND_ROWS ' . $this->aSQLViewList['SELECT'];
                $bSQLCALCFOUNDROWS = true;
            }
            $sSQL = 'SELECT ' . $this->aSQLViewList['SELECT'] .
                   ' FROM ' . $this->aSQLViewList['FROM'] .
                (!$this->aSQLViewList['WHERE']? '' :
                   ' WHERE ' . $this->aSQLViewList['WHERE']) .
                (!$this->aSQLViewList['GROUP_BY']? '' :
                   ' GROUP BY ' . $this->aSQLViewList['GROUP_BY']) .
                (!$this->aSQLViewList['HAVING']? '' :
                   ' HAVING ' . $this->aSQLViewList['HAVING']);

            if ($bOptions) {
                // If the session variable does not exist, create it!
                if (!isset($aSessionViewList['checked'])) {
                    $aSessionViewList['checked'] = array();
                }

                if (isset($_GET['ids_changed'])) {
                    if ($_GET['ids_changed'] == 'all') {
                        // If the select all button was clicked, fetch all entries and mark them as 'checked' in session.
                        // This query is the same as the viewList query, but without the ORDER BY and LIMIT, so that we can get the full result
                        // of the query.
                        $q = $_DB->query($sSQL, $aArgs);
                        while ($zData = $q->fetchAssoc()) {
                            $zData = $this->generateRowID($zData);
                            // We only need the row_id here for knowing which ones we need to check.
                            // 2015-09-18; 3.0-14; We need to run rawurldecode() or else Columns are not selectable this way.
                            $aSessionViewList['checked'][] = rawurldecode($zData['row_id']);
                        }
                    } elseif ($_GET['ids_changed'] == 'none') {
                        // If the unselect all button was clicked, reset the 'checked' array.
                        $aSessionViewList['checked'] = array();
                    } else {
                        // Get the changed ids and remove them from or add them to the session.
                        $aIDsChanged = explode(';', $_GET['ids_changed']);
                        // Flip the keys & values, so that we can do a simple isset() to see if the id is already present.
                        $aSessionViewList['checked'] = array_flip($aSessionViewList['checked']);
                        // Determine the highest key number, so we can use that later when adding new values to the array.
                        $nIndex = (count($aSessionViewList['checked'])? max($aSessionViewList['checked']) + 1 : 0);
                        foreach ($aIDsChanged as $nID) {
                            if (isset($aSessionViewList['checked'][$nID])) {
                                // ID is found in the array, but is also in the 'ids_changed' array, so remove it!
                                unset($aSessionViewList['checked'][$nID]);
                            } else {
                                // ID is not found in the array, but IS in the 'ids_changed' array, so add it using the $nIndex as value we determined earlier.
                                // Also add 1 to the $nIndex so that the next id that needs to be added will not overwrite this one.
                                $aSessionViewList['checked'][$nID] = ++$nIndex;
                            }
                        }
                        // Flip the array back to its original state.
                        $aSessionViewList['checked'] = array_flip($aSessionViewList['checked']);
                    }
                }
            }

            // ORDER BY will only occur when we estimate we have time for it.
            if ($aSessionViewList['counts'][$sFilterMD5]['t'] < 1 && $aSessionViewList['counts'][$sFilterMD5]['n'] <= $_SETT['lists']['max_sortable_rows']) {
                $sSQL .= ' ORDER BY ' . $this->aSQLViewList['ORDER_BY'];
                $bSortableVL = true;
            } else {
                // Not sortable, indicate this on the VL...
                $aOrder = array('', '');
                $bSortableVL = false;
                // 2013-07-03; 3.0-07; However, we do try and sort because in principle, the order is random and this may cause confusion while paginating.
                //   So, as a result we'll try and sort on the PK. We attempt to determine this from the GROUP BY or ID col in the VL columns list.
                $sCol= '';
                if (isset($this->aSQLViewList['GROUP_BY'])) {
                    $sCol = $this->aSQLViewList['GROUP_BY'];
                } elseif ($this->aColumnsViewList['id']) {
                    $sCol = $this->aColumnsViewList['id']['db'][0];
                } elseif ($this->aColumnsViewList['id_']) {
                    $sCol = $this->aColumnsViewList['id_']['db'][0];
                }
                if ($sCol) {
                    $sSQL .= ' ORDER BY ' . $sCol;
                }
            }

            if (!$bHideNav && FORMAT == 'text/html') {
                // Implement LIMIT only if navigation is not hidden.
                // We have a problem here, because we don't know how many hits there are,
                // because we're using SQL_CALC_FOUND_ROWS which only gives us the number
                // of hits AFTER we run the whole query. This means we should just assume
                // the page number is possible.
                $sSQL .= ' LIMIT ' . lovd_pagesplitInit(); // Function requires variable names $_GET['page'] and $_GET['page_size'].
            }

            // Run the viewList query.
            // FIXME; what if using AJAX? Probably we should generate a number here, if this query fails, telling the system to try once more. If that fails also, the JS should throw a general error, maybe.
            $q = $_DB->query($sSQL, $aArgs);

            // Now, get the total number of hits as if no LIMIT was used (when we have used the proper SELECT syntax). Note that $nTotal gets overwritten here.
            if ($bSQLCALCFOUNDROWS) {
                // FIXME: 't' needs to be recalculated as well!
                $nTotal = $_DB->query('SELECT FOUND_ROWS()')->fetchColumn();
                $aSessionViewList['counts'][$sFilterMD5]['n'] = $nTotal;
                $aSessionViewList['counts'][$sFilterMD5]['d'] = time();
                $bTrueCount = true;
            } else {
                // Estimate the number of results!
                $nTotal = $aSessionViewList['counts'][$sFilterMD5]['n'];
            }
            $_DB->commit(); // To end the transaction and the locks that come with it.
        } else {
            // Set certain values that are needed for hiding notices, applicable for the "incorrect syntax" error message.
            $bTrueCount = true; // Yes, we're sure we have 0 results.
            $bSortableVL = false; // Sorting makes no sense when you have no results.
        }

        // If no results are found, try to figure out if it was because of the user's searching or not.
        if (!$nTotal) {
            $bSearched = false;
            $aHiddenSearch = array();
            foreach ($_GET as $key => $value) {
                if (substr($key, 0, 7) == 'search_') {
                    $sColumn = substr($key, 7);
                    if (!in_array($sColumn, $aColsToSkip)) {
                        $bSearched = true;
                    } elseif ($this->aColumnsViewList[$sColumn]['view']) {
                        $sColHeader = $this->aColumnsViewList[$sColumn]['view'][0];
                        // Make sure all hidden ID columns have "ID" in the header, so we can recognize them.
                        if (substr(rtrim($sColumn, '_'), -2) == 'id' && substr($sColHeader, -3) != ' ID') {
                            $sColHeader .= ' ID';
                        }
                        $aHiddenSearch[$sColHeader] = $value;
                    }
                }
            }
        }

        // FIXME; this is a temporary hack just to get the genes?authorize working when all users have been selected.
        //   There is no longer a viewList when all users have been selected, but we need one for the JS execution.
        //   Possibly, this code can be standardized a bit and, if necessary for other viewLists as well, can be kept here.
        if (!$nTotal && !$bSearched && (($this->sObject == 'User' && !empty($_GET['search_id'])))) {
            // FIXME; Maybe check for JS contents of the rowlink?
            // There has been searched, but apparently the ID column is forced hidden. This must be the authorize page.
            $bSearched = true; // This will trigger the creation of the viewList table.
        }

        if (FORMAT == 'text/html' && ($nTotal || $bSearched)) {
            // Only print stuff if we're not just loading one entry right now.
            if (!$bOnlyRows) {
                if (!$bAjax) {
                    print('      <DIV id="viewlistDiv_' . $sViewListID . '">' . "\n"); // These contents will be replaced by Ajax.
                }

                // If we have a legend, create a hidden DIV that will be used for the full legend.
                print('      <DIV id="viewlistLegend_' . $sViewListID . '" title="Legend" style="display : none;">' . "\n" .
                      '        <H2 class="LOVD">Legend</H2>' . "\n\n" .
                      '        <I class="S11">Please note that a short description of a certain column can be displayed when you move your mouse cursor over the column\'s header and hold it still. Below, a more detailed description is shown per column.</I><BR><BR>' . "\n\n");
                $bLegend = false; // We need to check if we have a legend at all.
                foreach ($this->aColumnsViewList as $sField => $aCol) {
                    if (!empty($aCol['legend'])) {
                        $bLegend = true;
                        if (empty($aCol['legend'][1])) {
                            $aCol['legend'][1] = $aCol['legend'][0];
                        }
                        print('        <B>' . $aCol['view'][0] . '</B>: ' . $aCol['legend'][1]);
                        if (substr($aCol['legend'][1], -5) == '</UL>') {
                            // No additional breaks, no possible listing of selection options. Column has its own UL already.
                            print("\n\n");
                            continue;
                        }
                        if (isset($this->aColumns[$sField]) && $this->aColumns[$sField]['form_type'][2] == 'select') {
                            // This is a custom column and it has a selection list with options. List the options below.
                            print('<BR>' . "\n" .
                                  '        All options:' . "\n" .
                                  '        <UL style="margin-top : 0px;">' . "\n");
                            foreach ($this->aColumns[$sField]['select_options'] as $sOption) {
                                print('          <LI>' . $sOption . '</LI>' . "\n");
                            }
                            print('      </UL>' . "\n\n");
                        } else {
                            print('<BR><BR>' . "\n\n");
                        }
                    }
                }
                print('      </DIV>' . "\n\n");

                if (!$bHideNav) {
                    lovd_pagesplitShowNav($sViewListID, $nTotal, $bTrueCount, $bSortableVL, $bLegend);
                }

                // Table and search headers (if applicable).
                print('      <TABLE border="0" cellpadding="0" cellspacing="1" class="data" id="viewlistTable_' . $sViewListID . '">' . "\n" .
                      '        <THEAD>' . "\n" .
                      '        <TR>' .
   ($bOptions? "\n" . '          <TH valign="center" style="text-align:center;">' . "\n" .
                      '            <IMG id="viewlistOptionsButton_' . $sViewListID . '" src="gfx/options.png" width="16" height="16" style="cursor : pointer;"></TH>' : ''));

                foreach ($this->aColumnsViewList as $sField => $aCol) {
                    if (in_array($sField, $aColsToSkip)) {
                        continue;
                    }

                    $bSortable   = !empty($aCol['db'][1]) && $bSortableVL; // If we can't sort at all, nothing is sortable.
                    $bSearchable = !empty($aCol['db'][2]);
                    $sImg = '';
                    $sAlt = '';
                    if ($bSortable && $aOrder[0] == $sField) {
                        $sImg = ($aOrder[1] == 'DESC'? '_desc' : '_asc');
                        $sAlt = ($aOrder[1] == 'DESC'? 'Descending' : 'Ascending');
                    }
                    print("\n" . '          <TH valign="top"' . (!empty($aCol['view'][2])? ' ' . $aCol['view'][2] : '') . ($bSortable? ' class="order' . ($aOrder[0] == $sField? 'ed' : '') . '"' : '') . (empty($aCol['legend'][0])? '' : ' title="' . htmlspecialchars($aCol['legend'][0]) . '"') . '>' . "\n" .
                                 '            <IMG src="gfx/trans.png" alt="" width="' . $aCol['view'][1] . '" height="1" id="viewlistTable_' . $sViewListID . '_colwidth_' . $sField . '"><BR>' .
                            (!$bSortable? str_replace(' ', '&nbsp;', $aCol['view'][0]) . '<BR>' :
                                 "\n" .
                    // 2012-02-01; 3.0-beta-02; When resorting the ViewList, reset page to 1.
                                 '            <DIV onclick="document.forms[\'viewlistForm_' . $sViewListID . '\'].order.value=\'' . $sField . ',' . ($aOrder[0] == $sField? ($aOrder[1] == 'ASC'? 'DESC' : 'ASC') : $aCol['db'][1]) . '\'; if (document.forms[\'viewlistForm_' . $sViewListID . '\'].page) { document.forms[\'viewlistForm_' . $sViewListID . '\'].page.value=1; } lovd_AJAX_viewListSubmit(\'' . $sViewListID . '\');" style="position : relative;">' . "\n" .
                                 '              <IMG src="gfx/order_arrow' . $sImg . '.png" alt="' . $sAlt . '" title="' . $sAlt . '" width="13" height="12" style="position : absolute; top : 2px; right : 0px;">' . str_replace(' ', '&nbsp;', $aCol['view'][0]) . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</DIV>') .
                            (!$bSearchable? '' :
                                 "\n" .
                                 // SetTimeOut() is necessary because if the function gets executed right away, selecting a previously used value from a *browser-generated* list in one of the fields, gets aborted and it just sends whatever is typed in at that moment.
                                 '            <INPUT type="text" name="search_' . $sField . '" value="' . (!isset($_GET['search_' . $sField])? '' : htmlspecialchars($_GET['search_' . $sField])) . '" title="' . $aCol['view'][0] . ' field should contain...' . (!empty($_GET['search_' . $sField])? "\nCurrent search:\n\n" . htmlspecialchars(lovd_formatSearchExpression($_GET['search_' . $sField], $aColTypes[$sField])) : '') .'" style="width : ' . ($aCol['view'][1] - 6) . 'px; font-weight : normal;" onkeydown="if (event.keyCode == 13) { if (document.forms[\'viewlistForm_' . $sViewListID . '\'].page) { document.forms[\'viewlistForm_' . $sViewListID . '\'].page.value=1; } setTimeout(\'lovd_AJAX_viewListSubmit(\\\'' . $sViewListID . '\\\')\', 0); }">') .
                          '</TH>');
                }
                print('</TR></THEAD>');
            }

        } elseif (FORMAT == 'text/plain') {
            // Download format: show headers.
            $sObject = ($this->sObject == 'Custom_ViewList'? $this->sObjectID : $this->sObject . 's');
            header('Content-type: text/plain; charset=UTF-8');
            header('Content-Disposition: attachment; filename="LOVD_' . $sObject . '_' . date('Y-m-d_H.i.s') . '.txt"');
            header('Pragma: public');
            print('### LOVD-version ' . lovd_calculateVersion($_SETT['system']['version']) . ' ### ' . $sObject . ' Quick Download format ### This file can not be imported ###' . "\r\n");
            // FIXME: this has to be done better, we can't see what we're filtering for, because it's in the arguments!
            $sFilter = $WHERE . ($WHERE && $HAVING? ' AND ' : '') . $HAVING;
            $aArgs = array_merge($aArguments['WHERE'], $aArguments['HAVING']);
            if ($sFilter) {
                if (count($aArgs) == substr_count($sFilter, '?')) {
                    foreach ($aArgs as $sArg) {
                        $sFilter = preg_replace('/\?/', (ctype_digit($sArg)? $sArg : '"' . $sArg . '"'), $sFilter, 1);
                    }
                }
                print('## Filter: ' . $sFilter . "\r\n");
            }
            if (ACTION == 'downloadSelected') {
                print('## Filter: selected = ' . implode(',', $aSessionViewList['checked']) . "\r\n");
            }
            print('# charset=UTF-8' . "\r\n");
            $i = 0;
            foreach ($this->aColumnsViewList as $sField => $aCol) {
                if (in_array($sField, $aColsToSkip)) {
                    continue;
                }

                print(($i ++? "\t" : '') . '"{{' . $sField . '}}"');
            }
            print("\r\n");
        }

        if (!$nTotal && FORMAT == 'text/html') {
            if ($bSearched) {
                // Searched, but no results. FIXME: link to the proper documentation entry about search expressions
                $sBadSyntaxColumns = implode(', ', array_unique($aBadSyntaxColumns));
                // FIXME; use an IF here.
                $sMessageNormal = 'No results have been found that match your criteria.<BR>Please redefine your search criteria.';
                $sMessageBadSyntax = 'Your search column' . (count($aBadSyntaxColumns) > 1? 's contain' : ' contains') . ' incorrect search expression syntax at: ' . $sBadSyntaxColumns . '.';
                $sMessage = (empty($aBadSyntaxColumns)? $sMessageNormal : $sMessageBadSyntax);
                if ($bOnlyRows) {
                    die('0'); // Silent error.
                }
                // FIXME; This code is sort of duplicated, some 100 lines below we also print this, *if* results are found.
                print('</TABLE><BR>' . "\n"); // <BR> is necessary to keep the InfoTable apart from the data headers.
                if (!$bHideNav) {
                    print('        <INPUT type="hidden" name="total" value="' . $nTotal . '" disabled>' . "\n" .
                          '        <INPUT type="hidden" name="page_size" value="' . $_GET['page_size'] . '">' . "\n" .
                          '        <INPUT type="hidden" name="page" value="' . $_GET['page'] . '">' . "\n");
                }
                lovd_showInfoTable($sMessage, 'stop');
                print('      </DIV></FORM>' . "\n\n");

            } else {
                if ($bOnlyRows) {
                    die('0'); // Silent error.
                }

                print('      <DIV id="viewlistDiv_' . $sViewListID . '">' . "\n"); // These contents will be replaced by Ajax.

                if (substr($this->sObject, -7) == 'Variant') {
                    $sUnit = 'variants' . (substr($this->sObject, 0, 10) == 'Transcript'? ' on transcripts' : '');
                } elseif ($this->sObject == 'Custom_Viewlist') {
                    $sUnit = 'entries';
                } elseif ($this->sObject == 'Shared_Column') {
                    $sUnit = 'active columns';
                } else {
                    $sUnit = strtolower($this->sObject) . 's';
                }
                $sMessage = 'No ' . $sUnit . ' found';
                if (!empty($aHiddenSearch)) {
                    $sWhere = '';
                    foreach ($aHiddenSearch as $sCol => $sValue) {
                        // If the hidden column has "ID" in its name, it is the primary filter column.
                        if (substr($sCol, -3) == ' ID') {
                            $sWhere .= ($sWhere? ' and ' : ' ') . 'for this ' . strtolower(substr($sCol, 0, -3));
                        } else {
                            $sWhere .= ($sWhere? ' and ' : ' where ') . strtolower($sCol) . ' is "' . str_replace('|', '" or "', trim($sValue, '="') . '"');
                        }
                    }
                    $sMessage .= $sWhere;
                }
                lovd_showInfoTable($sMessage . '!', 'stop');

                print('      </DIV></FORM>' . "\n\n");

                return 0;
            }
        }

        // Now loop through the data and print. But check for $q to be set; if we had a bad search syntax, we end up here as well, but without an $q.
        while (isset($q) && $nTotal && $zData = $q->fetchAssoc()) {
            // If row_id is not given by the database, but it should be created according to some format ($this->sRowID), put the data's ID in this format.
            $zData = $this->generateRowID($zData);
            // If row_link is not given by the database, but it should be created according to some format ($this->sRowLink), put the data's ID and the viewList's ID in this format.
            if (!isset($zData['row_link'])) {
                if ($this->sRowLink !== '' && $zData['row_id']) {
                    $zData['row_link'] = str_replace(array('{{ID}}', '{{ViewListID}}'), array(rawurlencode($zData['row_id']), $sViewListID), $this->sRowLink);
                    //$zData['row_link'] = preg_replace('/\{\{zData_(\w)+\}\}/', rawurlencode("$1"), $zData['row_link']);
                    //$zData['row_link'] = preg_replace_callback('/\{\{zData_(\w+)\}\}/', create_function('$aRegs', 'global $zData; return rawurlencode($zData[$aRegs[1]]);'), $zData['row_link']);
                    // FIXME; sorry, couldn't figure out how to do this in one line. Suggestions are welcome.
                    foreach ($zData as $key => $val) {
                        // Also allow data from $zData to be put into the row link & row id.
                        // FIXME; This is a temporary ugly solution, so we need to fix this later!!!!
                        $zData['row_link'] = preg_replace('/\{\{' . preg_quote($key, '/') . '\}\}/', rawurlencode($val), $zData['row_link']);
                        $zData['row_link'] = preg_replace('/\{\{zData_' . preg_quote($key, '/') . '\}\}/', rawurlencode($val), $zData['row_link']);
                    }
                } else {
                    $zData['row_link'] = '';
                }
            }

            $zData = $this->autoExplode($zData);

            // Only the CustomViewList object has this 3rd argument, but other objects' prepareDate()
            // don't complain when called with this 3 argument they didn't define.
            $zData = $this->prepareData($zData, 'list', $sViewListID);

            if (FORMAT == 'text/html') {
                // FIXME; rawurldecode() in the line below should have a better solution.
                // IE (who else) refuses to respect the BASE href tag when using JS. So we have no other option than to include the full path here.
                print("\n" .
                      '        <TR class="' . (empty($zData['class_name'])? 'data' : $zData['class_name']) . '"' . (!$zData['row_id']? '' : ' id="' . $zData['row_id'] . '"') . ' valign="top"' . (!$zData['row_link']? '' : ' style="cursor : pointer;"') . (!$zData['row_link']? '' : ' onclick="' . (substr($zData['row_link'], 0, 11) == 'javascript:'? rawurldecode(substr($zData['row_link'], 11)) : 'window.location.href = \'' . lovd_getInstallURL(false) . $zData['row_link'] . '\';') . '"') . '>');
                if ($bOptions) {
                    print("\n" . '          <TD align="center" class="checkbox" onclick="cancelParentEvent(event);"><INPUT id="check_' . $zData['row_id'] . '" class="checkbox" type="checkbox" name="check_' . $zData['row_id'] . '" onclick="lovd_recordCheckChanges(this, \'' . $sViewListID . '\');"' . (in_array($zData['row_id'], $aSessionViewList['checked'])? ' checked' : '') . '></TD>');
                }
                foreach ($this->aColumnsViewList as $sField => $aCol) {
                    if (in_array($sField, $aColsToSkip)) {
                        continue;
                    }
                    print("\n" . '          <TD' . (!empty($aCol['view'][2])? ' ' . $aCol['view'][2] : '') . ($aOrder[0] == $sField? ' class="ordered"' : '') . '>' . ($zData[$sField] === ''? '-' : $zData[$sField]) . '</TD>');
                }
                print('</TR>');

            } elseif (FORMAT == 'text/plain') {
                // Download format: print contents.
                if (ACTION == 'downloadSelected' && !in_array($zData['row_id'], $aSessionViewList['checked'])) {
                    // Only selected entries should be downloaded. And this one is not selected.
                    continue;
                }

                $i = 0;
                foreach ($this->aColumnsViewList as $sField => $aCol) {
                    if (in_array($sField, $aColsToSkip)) {
                        continue;
                    }
                    print(($i ++? "\t" : '') . '"' . str_replace(array("\r\n", "\r", "\n"), array('\r\n', '\r', '\n'), addslashes(html_entity_decode(strip_tags($zData[$sField])))) . '"');
                }
                print("\r\n");
            }
        }

        // Only print stuff if we're not just loading one entry right now.
        if ($nTotal && !$bOnlyRows && FORMAT == 'text/html') {
            print('</TABLE>' . "\n");
            if (!$bHideNav) {
                print('        <INPUT type="hidden" name="total" value="' . $nTotal . '" disabled>' . "\n" .
                      '        <INPUT type="hidden" name="page_size" value="' . $_GET['page_size'] . '">' . "\n" .
                      '        <INPUT type="hidden" name="page" value="' . $_GET['page'] . '">' . "\n\n");

                lovd_pagesplitShowNav($sViewListID, $nTotal, $bTrueCount, $bSortableVL, $bLegend);
            }
            if (!$bAjax) {
                print('      </DIV></FORM><BR>' . "\n"); // These contents will be replaced by Ajax.
            }
        }

        if (!$bAjax && FORMAT == 'text/html') {
            // If sent using Ajax, the browser is not going to evaluate this code, anyways.
            print('      <SCRIPT type="text/javascript">' . "\n" .
                  '        // This has to be run when the document has finished loading everything, because only then can it get the proper width from IE7 and lower!' . "\n" .
                  '        $( function () {lovd_stretchInputs(\'' . $sViewListID . '\');});' . "\n");
            if ($bOptions) {
                print("\n" .
                      '        // If menu\'s UL doesn\'t exist yet, create it.' . "\n" .
                      '        if ($(\'#viewlistMenu_' . $sViewListID . '\').attr(\'id\') == undefined) {' . "\n" .
                      '          var oUL = window.document.createElement(\'ul\');' . "\n" .
                      '          oUL.setAttribute(\'id\', \'viewlistMenu_' . $sViewListID . '\');' . "\n" .
                      '          oUL.className = \'jeegoocontext jeegooviewlist\';' . "\n" .
                      '          window.document.body.appendChild(oUL);' . "\n" .
                      '        }' . "\n" .
                      '        // Fix the top border that could not be set through jeegoo\'s style.css.' . "\n" .
                      '        $(\'#viewlistMenu_' . $sViewListID . '\').attr(\'style\', \'border-top : 1px solid #000;\');' . "\n" .
                      '        $(\'#viewlistMenu_' . $sViewListID . '\').prepend(\'<LI class="icon"><A click="check_list[\\\'' . $sViewListID . '\\\'] = \\\'all\\\'; lovd_AJAX_viewListSubmit(\\\'' . $sViewListID . '\\\');"><SPAN class="icon" style="background-image: url(gfx/check.png);"></SPAN>Select all <SPAN>entries</SPAN></A></LI><LI class="icon"><A click="check_list[\\\'' . $sViewListID . '\\\'] = \\\'none\\\'; lovd_AJAX_viewListSubmit(\\\'' . $sViewListID . '\\\');"><SPAN class="icon" style="background-image: url(gfx/cross.png);"></SPAN>Unselect all</A></LI>\');' . "\n" .
                      '        $(\'#viewlistMenu_' . $sViewListID . '\').append(\'<LI class="icon"><A click="lovd_AJAX_viewListSubmit(\\\'' . $sViewListID . '\\\', function(){lovd_AJAX_viewListDownload(\\\'' . $sViewListID . '\\\', true);});"><SPAN class="icon" style="background-image: url(gfx/menu_save.png);"></SPAN>Download all entries (summary data)</A></LI><LI class="icon"><A click="lovd_AJAX_viewListSubmit(\\\'' . $sViewListID . '\\\', function(){lovd_AJAX_viewListDownload(\\\'' . $sViewListID . '\\\', false);});"><SPAN class="icon" style="background-image: url(gfx/menu_save.png);"></SPAN>Download selected entries (summary data)</A></LI>\');' . "\n" .
                      '        lovd_activateMenu(\'' . $sViewListID . '\');' . "\n\n");
            }
            print('        check_list[\'' . $sViewListID . '\'] = [];' . "\n" .
                  '      </SCRIPT>' . "\n\n");
        }

        return $nTotal;
    }
}
?>
