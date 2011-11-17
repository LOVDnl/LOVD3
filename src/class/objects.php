<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-21
 * Modified    : 2011-11-16
 * For LOVD    : 3.0-alpha-06
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
    var $sRowID = ''; // FIXME; needs getter and setter?
    var $sRowLink = ''; // FIXME; needs getter and setter?
    var $nCount = 0;





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





    function checkFields ($aData)
    {
        // Checks fields before submission of data.
        global $_AUTH;
        $aForm = $this->getForm();
        $aFormInfo = $aForm[0];
        unset($aForm[0]);

        // Validate form by looking at the form itself, and check what's needed.
        foreach ($aForm as $key => $aField) {
            if (!is_array($aField)) {
                // 'skip', 'hr', etc...
                continue;
            }
            @list($sHeader, $sHelp, $sType, $sName) = $aField;
            $sNameClean = preg_replace('/^\d{5}_/', '', $sName); // Remove prefix (transcriptid) that LOVD_TranscriptVariants puts there.

            // Mandatory fields, as defined by child object.
            $this->aCheckMandatory[] = 'password';
            if (in_array($sName, $this->aCheckMandatory) && empty($aData[$sName])) {
                lovd_errorAdd($sName, 'Please fill in the \'' . $sHeader . '\' field.');
            }

            // Checking free text fields for max length, data types, etc.
            if (in_array($sType, array('text', 'textarea')) && $sMySQLType = lovd_getColumnType(constant($this->sTable), $sNameClean)) {
                // FIXME; we're assuming here, that $sName equals the database name. Which is true in probably most/every case, but even so...

                // Check max length.
                $nMaxLength = lovd_getColumnLength(constant($this->sTable), $sNameClean);
                if (!empty($aData[$sName]) && strlen($aData[$sName]) > $nMaxLength) {
                    lovd_errorAdd($sName, 'The \'' . $sHeader . '\' field is limited to ' . $nMaxLength . ' characters, you entered ' . strlen($aData[$sName]) . '.');
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
                            if (!is_numeric($aData[$sName])) {
                                lovd_errorAdd($sName, 'The field \'' . $sHeader . '\' must contain a number.');
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

            } elseif ($sType == 'select' && !empty($aField[7])) {
                // The browser fails to send value if selection list w/ multiple selection options is left empty.
                // This is causing notices in the code.
                // FIXME; is it also with selection lists with a size > 1? Then you should change the check above.
                if (!isset($aData[$sName])) {
                    $GLOBALS['_' . $aFormInfo[0]][$sName] = array();
                }

            } elseif ($sType == 'checkbox') {
                // The browser fails to send value if checkbox is left empty.
                // This is causing problems sometimes with MySQL, since INT
                // columns can't receive an empty string if STRICT is on.
                if (!isset($aData[$sName])) {
                    $GLOBALS['_' . $aFormInfo[0]][$sName] = 0;
                }
            }
        }

        if ($sName == 'password') {
            // Password is in the form, it must be checked. Assuming here that it is also considered mandatory.
            if (!empty($aData['password']) && !lovd_verifyPassword($aData['password'], $_AUTH['password'])) {
                lovd_errorAdd('password', 'Please enter your correct password for authorization.');
            }
        }
    }





    function deleteEntry ($nID = false)
    {
        // Delete an entry from the database.
        if (!$nID) {
            // We were called, but the class wasn't initiated with an ID. Fail.
            lovd_displayError('LOVD-Lib', 'Objects::(' . $this->sObject . ')::deleteEntry() - Method didn\'t receive ID');
        } else {
            if ($this->getCount($nID)) {
                $sSQL = 'DELETE FROM ' . constant($this->sTable) . ' WHERE id = ?';
                $q = lovd_queryDB_Old($sSQL, array($nID));
                if (!$q) {
                    lovd_queryError((defined('LOG_EVENT')? LOG_EVENT : $this->sObject . '::deleteEntry()'), $sSQL, mysql_error());
                }
                return true;
            } else {
                return false;
            }
        }            
    }





    function getCount ($nID = false)
    {
        // Returns the number of entries in the database table.
        if ($nID) {
            list($nCount) = mysql_fetch_row(lovd_queryDB_Old('SELECT COUNT(*) FROM ' . constant($this->sTable) . ' WHERE id = ?', array($nID)));
        } else {
            if ($this->nCount) {
                return $this->nCount;
            }
            list($nCount) = mysql_fetch_row(lovd_queryDB_Old('SELECT COUNT(*) FROM ' . constant($this->sTable)));
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

        if (!is_array($aData) || !count($aData)) {
            lovd_displayError('LOVD-Lib', 'Objects::(' . $this->sObject . ')::insertEntry() - Method didn\'t receive data array');
        } elseif (!is_array($aFields) || !count($aFields)) {
            $aFields = array_keys($aData);
        }

        // Query text.
        $sSQL = 'INSERT INTO ' . constant($this->sTable) . ' (';
        $aSQL = array();
        foreach ($aFields as $key => $sField) {
            $sSQL .= (!$key? '' : ', ') . '`' . $sField . '`';
            if (substr(lovd_getColumnType(constant($this->sTable), $sField), 0, 3) == 'INT' && $aData[$sField] === '') {
                $aData[$sField] = NULL;
            }
            $aSQL[] = $aData[$sField];
        }
        $sSQL .= ') VALUES (?' . str_repeat(', ?', count($aFields) - 1) . ')';

        $q = lovd_queryDB_Old($sSQL, $aSQL);
        if (!$q) {
            lovd_queryError((defined('LOG_EVENT')? LOG_EVENT : $this->sObject . '::insertEntry()'), $sSQL, mysql_error());
        }

        $nID = mysql_insert_id();
        if (substr(lovd_getColumnType(constant($this->sTable), 'id'), 0, 3) == 'INT') {
            $nID = sprintf('%0' . lovd_getColumnLength(constant($this->sTable), 'id') . 'd', $nID);
        }
        return $nID;
    }





    function loadEntry ($nID = false)
    {
        // Loads and returns an entry from the database.

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
        $zData = @mysql_fetch_assoc(lovd_queryDB_Old($sSQL, array($nID)));
        // FIXME; check if $zData['status'] exists, if so, check status versus lovd_isAuthorized().
        // Set $zData to false if user should not see this entry.
        if (!$zData) {
            global $_CONF, $_SETT, $_STAT, $_AUTH;

            $sError = mysql_error(); // Save the mysql_error before it disappears.

            // Check if, and which, top include has been used.
            if (!defined('_INC_TOP_INCLUDED_') && !defined('_INC_TOP_CLEAN_INCLUDED_')) {
                if (is_readable(ROOT_PATH . 'inc-top.php')) {
                    require ROOT_PATH . 'inc-top.php';
                } else {
                    require ROOT_PATH . 'inc-top-clean.php';
                }
            }

            if (defined('PAGE_TITLE') && defined('_INC_TOP_INCLUDED_')) {
                lovd_printHeader(PAGE_TITLE);
            }

            if ($sError) {
                lovd_queryError($this->sObject . '::loadEntry()', $sSQL, $sError);
            }

            lovd_showInfoTable('No such ID!', 'stop');

            if (defined('_INC_TOP_INCLUDED_')) {
                require ROOT_PATH . 'inc-bot.php';
            } elseif (defined('_INC_TOP_CLEAN_INCLUDED_')) {
                require ROOT_PATH . 'inc-bot-clean.php';
            }
            exit;
        }

        $zData = $this->autoExplode($zData);

        return $zData;
    }





    function prepareData ($zData = '', $sView = 'list')
    {
        if (!is_array($zData)) {
            $zData = array();
        }

        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Quote special characters, disallowing HTML and other tricks.
        $zData = lovd_php_htmlspecialchars($zData);
        $aUserColumns = array('created_by', 'edited_by', 'updated_by', 'deleted_by');
        foreach($aUserColumns as $sUserColumn) {
            // FIXME; ik krijg hoofdpijn van deze lange regel... wordt dit wel in een viewList toegepast? Links in een viewList verstoren nu de boel. De code kan simpeler. Ook moet er wat commentaar bij..
            (isset($zData[$sUserColumn])? $zData[$sUserColumn . ($sView == 'list'? '' : '_')] = (!empty($zData[$sUserColumn])? '<A href="users/' . $zData[$sUserColumn] . '">' . $zData[$sUserColumn . ($sView == 'list'? '' : '_')] . '</A>' : 'N/A') : false);
        }

        $aDateColumns = array('created_date', 'edited_date', 'updated_date', 'valid_from', 'valid_to');
        foreach($aDateColumns as $sDateColumn) {
            // Ook deze code kan m.i. simpeler..
            (isset($zData[$sDateColumn])? $zData[$sDateColumn . ($sView == 'list'? '' : '_')] = (!empty($zData[$sDateColumn])? $zData[$sDateColumn] : 'N/A') : false);
        }

        // FIXME; hier mist commentaar..
        if (isset($zData['edited_by_']) && $zData['edited_by_'] == 'N/A') {
            $zData['edited_date' . ($sView == 'list'? '' : '_')] = 'N/A';
        }

        if ($sView == 'list') {
            // By default, we put an anchor in the id_ field, if present.
            if ($zData['row_link'] && array_key_exists('id_', $this->aColumnsViewList) && $zData['id']) {
                $zData['id_'] = '<A href="' . $zData['row_link'] . '" class="hide">' . $zData['id'] . '</A>';
            }
        }

        return $zData;
    }





    function setDefaultValues ()
    {
        // Stub; this thing is just here to prevent an error message when a child class has not defined this function.
        return true;
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
    }





    function updateEntry ($nID, $aData, $aFields = array())
    {
        // Updates entry $nID with data from $aData in the database, changing only fields defined in $aFields.

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
            if (substr(lovd_getColumnType(constant($this->sTable), $sField), 0, 3) == 'INT' && $aData[$sField] === '') {
                $aData[$sField] = NULL;
            }
            $aSQL[] = $aData[$sField];
        }
        $sSQL .= ' WHERE id = ?';
        $aSQL[] = $nID;

        $q = lovd_queryDB_Old($sSQL, $aSQL);
        if (!$q) {
            lovd_queryError((defined('LOG_EVENT')? LOG_EVENT : $this->sObject . '::updateEntry()'), $sSQL, mysql_error());
        }

        return mysql_affected_rows();
    }





    function viewEntry ($nID = false)
    {
        // Views just one entry from the database.

        if (empty($nID)) {
            // We were called, but the class wasn't initiated with an ID. Fail.
            lovd_displayError('LOVD-Lib', 'Objects::(' . $this->sObject . ')::viewEntry() - Method didn\'t receive ID');
        }

        // Check existence of entry.
        list($n) = $this->getCount($nID);
        if (!$n) {
            global $_SETT, $_STAT, $_AUTH;
            lovd_showInfoTable('No such ID!', 'stop');
            if (defined('_INC_TOP_INCLUDED_')) {
                require ROOT_PATH . 'inc-bot.php';
            } elseif (defined('_INC_TOP_CLEAN_INCLUDED_')) {
                require ROOT_PATH . 'inc-bot-clean.php';
            }
            exit;
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
        $zData = mysql_fetch_assoc(lovd_queryDB_Old($sSQL, array($nID)));

        if (!$zData) {
            lovd_queryError((defined('LOG_EVENT')? LOG_EVENT : $this->sObject . '::viewEntry()'), $sSQL, mysql_error());
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





    function viewList ($sViewListID = false, $aColsToSkip = array(), $bNoHistory = false, $bHideNav = false, $bOnlyRows = false)
    {
        global $_PATH_ELEMENTS, $_DB;

        if (!defined('LOG_EVENT')) {
           define('LOG_EVENT', $this->sObject . '::viewList()');
        }

        // Views list of entries in the database, allowing search.
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

        require_once ROOT_PATH . 'inc-lib-viewlist.php';

        // First, check if entries are in the database at all.
        $nTotal = $this->getCount();
        if (!$nTotal) {
            $sMessage = 'No entries in the database yet!';
            if ($bOnlyRows) {
                die('0'); // Silent error.
            }
            lovd_showInfoTable($sMessage, 'stop');
            return true;
        }

        // SEARCH: Advanced text search.
        $WHERE = '';
        $HAVING = '';
        $CLAUSE = '';
        $aArguments = array(
                        'WHERE' => array(),
                        'HAVING' => array()
                           );
        $aBadSyntaxColumns = array();
        foreach ($this->aColumnsViewList as $sColumn => $aCol) {
            if (!empty($aCol['db'][2]) && isset($_GET['search_' . $sColumn]) && trim($_GET['search_' . $sColumn]) !== '') {
                $CLAUSE = (strpos($aCol['db'][0], '.') === false? 'HAVING' : 'WHERE');
                if ($aCol['db'][2] !== true) {
                    // Column type of an alias is given by LOVD.
                    $sColType = $aCol['db'][2];
                } else {
                    $sColType = lovd_getColumnType(constant($this->sTable), rtrim($sColumn, '_'));
                }
                // Allow for searches where the order of words is forced by enclosing the values with double quotes; 
                // Replace spaces in sentences between double quotes so they don't get exploded.
                $sSearch = preg_replace_callback('/("[^"]+")/', create_function('$aRegs', 'return str_replace(\' \', \'{{SPACE}}\', $aRegs[1]);'), trim($_GET['search_' . $sColumn]));
                $aWords = explode(' ', $sSearch);
                foreach ($aWords as $sWord) {
                    if ($sWord !== '') {
                        $aOR = (preg_match('/^[^|]+(\|[^|]+)+$/', $sWord)? explode('|', $sWord) : array($sWord));
                        $$CLAUSE .= ($$CLAUSE? ' AND ' : '') . (!empty($aOR[1])? '(' : '');
                        foreach ($aOR as $nTerm => $sTerm) {
                            $$CLAUSE .= ($nTerm? ' OR ' : '');
                            switch ($sColType) {
                                case 'DECIMAL':
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
                                        $$CLAUSE .= $aCol['db'][0] . ' ' . $sOperator . ' ?';
                                        $aArguments[$CLAUSE][] = lovd_escapeSearchTerm($sTerm);
                                    } elseif (preg_match('/^!?=""$/', $sTerm)) {
                                        // INT fields cannot be empty, they are NULL. So searching for ="" must return all NULL values.
                                        $$CLAUSE .= $aCol['db'][0] . ' IS ' . (substr($sTerm, 0, 1) == '!'? 'NOT ' : '') . 'NULL';
                                    } else {
                                        $aBadSyntaxColumns[] = $aCol['view'][0];
                                    }
                                    break;
                                case 'DATE':
                                case 'DATETIME':
                                    if (preg_match('/^([><]=?|!)?(\d{4})(-\d{2})?(-\d{2})?$/', $sTerm, $aMatches)) {
                                        if (!checkdate((isset($aMatches[3])? substr($aMatches[3], 1) : '01'), (isset($aMatches[4])? substr($aMatches[4], 1) : '01'), $aMatches[2])) {
                                            $aBadSyntaxColumns[] = $aCol['view'][0];
                                        }
                                        $sOperator = $aMatches[1];
                                        $sTerm = $aMatches[2] . (isset($aMatches[3])? $aMatches[3] : '-01') . (isset($aMatches[4])? $aMatches[4] : '-01');
                                        switch ($sOperator) {
                                            case '>':
                                            case '<=':
                                                $sTerm = $aMatches[2] . (isset($aMatches[3])? $aMatches[3] : '-12') . (isset($aMatches[4])? $aMatches[4] : '-31') . ' 23:59:59';
                                                break;
                                            case '<':
                                            case '>=':
                                                $sTerm .= ' 00:00:00';
                                                break;
                                            case '!':
                                            default:
                                                $sOperator = ($sOperator == '!'? 'NOT ' : '') . 'LIKE';
                                                $sTerm = $aMatches[2] . (!isset($aMatches[3])? '' : $aMatches[3] . (!isset($aMatches[4])? '' : $aMatches[4]));
                                                break;
                                        }
                                        $$CLAUSE .= $aCol['db'][0] . ' ' . $sOperator . ' ?';
                                        $aArguments[$CLAUSE][] = lovd_escapeSearchTerm($sTerm) . (substr($sOperator, -4) == 'LIKE'? '%' : '');
                                    } elseif (preg_match('/^!?=""$/', $sTerm)) {
                                        // DATE(TIME) fields cannot be empty, they are NULL. So searching for ="" must return all NULL values.
                                        $$CLAUSE .= $aCol['db'][0] . ' IS ' . (substr($sTerm, 0, 1) == '!'? 'NOT ' : '') . 'NULL';
                                    } else {
                                        $aBadSyntaxColumns[] = $aCol['view'][0];
                                    }
                                    break;
                                default:
                                    if (preg_match('/^!?"?([^"]+)"?$/', $sTerm, $aMatches)) {
                                        $sTerm = trim($sTerm, '"');
                                        $sOperator = (substr($sTerm, 0, 1) == '!'? 'NOT ' : '') . 'LIKE';
                                        $$CLAUSE .= $aCol['db'][0] . ' ' . $sOperator . ' ?';
                                        $aArguments[$CLAUSE][] = '%' . lovd_escapeSearchTerm($aMatches[1]) . '%';
                                    } elseif (preg_match('/^!?=""$/', $sTerm)) {
                                        $bNot = (substr($sTerm, 0, 1) == '!');
                                        $$CLAUSE .= '(' . $aCol['db'][0] . ' ' . ($bNot? '!' : '') . '= "" OR ' . $aCol['db'][0] . ' IS ' . ($bNot? 'NOT ' : '') . 'NULL)';
                                    } elseif (preg_match('/^!?="([^"]*)"$/', $sTerm, $aMatches)) {
                                        $sOperator = (substr($sTerm, 0, 1) == '!'? '!=' : '=');
                                        $$CLAUSE .= $aCol['db'][0] . ' ' . $sOperator . ' ?';
                                        $aArguments[$CLAUSE][] = lovd_escapeSearchTerm($aMatches[1]);
                                    } else {
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

        $this->aSQLViewList['ORDER_BY'] = $this->aColumnsViewList[$aOrder[0]]['db'][0] . ' ' . $aOrder[1] . (empty($this->aSQLViewList['ORDER_BY'])? '' : ', ' . $this->aSQLViewList['ORDER_BY']);



        // Only print stuff if we're not in Ajax right now.
        if (!$bAjax) {
            // Keep the URL clean; disable any fields that are not used.
            lovd_includeJS('inc-js-viewlist.php' . (!$bNoHistory? '' : '?nohistory'));
            lovd_includeJS('inc-js-tooltip.php');

            // Print form; required for sorting and searching.
            // Because we don't want the form to submit itself while we are waiting for the Ajax response, we need to kill the native submit() functionality.
            print('      <FORM action="' . CURRENT_PATH . '" method="get" id="viewlistForm_' . $sViewListID . '" style="margin : 0px;" onsubmit="return false;">' . "\n" .
                  '        <INPUT type="hidden" name="viewlistid" value="' . $sViewListID . '">' . "\n" .
                  '        <INPUT type="hidden" name="object" value="' . $this->sObject . '">' . "\n" .
                  '        <INPUT type="hidden" name="nid" value="' . (isset($this->nID)? $this->nID : '') . '">' . "\n" .
                  '        <INPUT type="hidden" name="object_id" value="' . (isset($this->sObjectID)? $this->sObjectID : '') . '">' . "\n" .
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
                    if (!empty($_GET['search_' . $sCol])) {
                        print('        <INPUT type="hidden" name="search_' . $sCol . '" value="' . htmlspecialchars($_GET['search_' . $sCol]) . '">' . "\n");
                    }
                }
            }
            if ($bHideNav) {
                print('        <INPUT type="hidden" name="hidenav" value="true">' . "\n");
            }
            print("\n");
        }

        // Manipulate SELECT to include SQL_CALC_FOUND_ROWS.
        $this->aSQLViewList['SELECT'] = 'SQL_CALC_FOUND_ROWS ' . $this->aSQLViewList['SELECT'];
        $sSQL = 'SELECT ' . $this->aSQLViewList['SELECT'] .
               ' FROM ' . $this->aSQLViewList['FROM'] .
            (!$this->aSQLViewList['WHERE']? '' :
               ' WHERE ' . $this->aSQLViewList['WHERE']) .
            (!$this->aSQLViewList['GROUP_BY']? '' :
               ' GROUP BY ' . $this->aSQLViewList['GROUP_BY']) .
            (!$this->aSQLViewList['HAVING']? '' :
               ' HAVING ' . $this->aSQLViewList['HAVING']) .
               ' ORDER BY ' . $this->aSQLViewList['ORDER_BY'];

        if (!$bHideNav) {
            // Implement LIMIT only if navigation is not hidden.
            // We have a problem here, because we don't know how many hits there are,
            // because we're using SQL_CALC_FOUND_ROWS which only gives us the number
            // of hits AFTER we run the whole query. This means we should just assume
            // the page number is possible.
            $sSQL .= ' LIMIT ' . lovd_pagesplitInit(); // Function requires variable names $_GET['page'] and $_GET['page_size'].
        }

        $nTotal = 0;
        if (!count($aBadSyntaxColumns)) {
            // Using the SQL_CALC_FOUND_ROWS technique to find the amount of hits in one go.
            // There is talk about a possible race condition using this technique on the mysql_num_rows man page, but I could find no evidence of it's existence on InnoDB tables.
            // Just to be sure, I'm implementing a serializable transaction, which should lock the table between the two SELECT queries to ensure proper results.
            // Last checked 2010-01-25, by Ivo Fokkema.
            $_DB->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
            $_DB->beginTransaction();

            // Run the actual query.
            $aArgs = array();
            foreach ($aArguments['WHERE'] as $aArg) {
                $aArgs[] = $aArg;
            }
            foreach($aArguments['HAVING'] as $aArg) {
                $aArgs[] = $aArg;
            }

            // FIXME; what if using AJAX? Probably we should generate a number here, if this query fails, telling the system to try once more. If that fails also, the JS should throw a general error, maybe.
            $q = $_DB->query($sSQL, $aArgs);

            // Now, get the total number of hits if no LIMIT was used. Note that $nTotal gets overwritten here.
            $nTotal = $_DB->query('SELECT FOUND_ROWS()')->fetchColumn();
            $_DB->commit(); // To end the transaction and the locks that come with it.

            // It is possible, when increasing the page size from a page > 1, that you're ending up in an invalid page with no results.
            // Catching this error, by redirecting from here. Only Ajax handles this correctly, because in normal requests inc-top.php already executed.
            // NOTE: if we ever decide to have a page_size change reset page to 1, we can drop this code.
            if (!$q->rowCount() && $nTotal && !headers_sent()) {
                // No results retrieved, but there are definitely hits to this query. Limit was wrong!
                header('Location: ' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?' . preg_replace('/page=[^&]+/', 'page=1', $_SERVER['QUERY_STRING']));
                exit;
            }
        }

        // If no results are found, quit here.
        if (!$nTotal) {
            $bSearched = false;
            foreach ($_GET as $key => $value) {
                if (substr($key, 0, 7) == 'search_') {
                    $sColumn = substr($key, 7);
                    if (!in_array($sColumn, $aColsToSkip)) {
                        $bSearched = true;
                        break;
                    }
                }
            }
        }

        // FIXME; this is a temporary hack just to get the genes?authorize working when all users have been selected.
        //   There is no longer a viewList when all users have been selected, but we need one for the JS execution.
        //   Possibly, this code can be standardized a bit and, if necessary for other viewLists as well, can be kept here.
        if (!$nTotal && $this->sObject == 'User' && !$bSearched && !empty($_GET['search_id'])) {
            // FIXME; Maybe check for JS contents of the rowlink?
            // There has been searched, but apparently the ID column is forced hidden. This must be the authorize page.
            $bSearched = true; // This will trigger the creation of the viewList table.
        }

        if ($nTotal || $bSearched) {
            // Only print stuff if we're not just loading one entry right now.
            if (!$bOnlyRows) {
                if (!$bAjax) {
                    print('      <DIV id="viewlistDiv_' . $sViewListID . '">' . "\n"); // These contents will be replaced by Ajax.
                }

                if (!$bHideNav) {
                    lovd_pagesplitShowNav($sViewListID, $nTotal);
                }

                // Table and search headers (if applicable).
                print('      <TABLE border="0" cellpadding="0" cellspacing="1" class="data" id="viewlistTable_' . $sViewListID . '">' . "\n" .
                      '        <THEAD>' . "\n" .
                      '        <TR>');

                foreach ($this->aColumnsViewList as $sField => $aCol) {
                    if (in_array($sField, $aColsToSkip)) {
                        continue;
                    }

                    $bSortable   = !empty($aCol['db'][1]);
                    $bSearchable = !empty($aCol['db'][2]);
                    $sImg = '';
                    $sAlt = '';
                    if ($bSortable && $aOrder[0] == $sField) {
                        $sImg = ($aOrder[1] == 'DESC'? '_desc' : '_asc');
                        $sAlt = ($aOrder[1] == 'DESC'? 'Descending' : 'Ascending');
                    }
                    print("\n" . '          <TH valign="top"' . (!empty($aCol['view'][2])? ' ' . $aCol['view'][2] : '') . ($bSortable? ' class="order' . ($aOrder[0] == $sField? 'ed' : '') . '"' : '') . '>' . "\n" .
                                 '            <IMG src="gfx/trans.png" alt="" width="' . $aCol['view'][1] . '" height="1" id="viewlistTable_' . $sViewListID . '_colwidth_' . $sField . '"><BR>' .
                            (!$bSortable? str_replace(' ', '&nbsp;', $aCol['view'][0]) . '<BR>' :
                                 "\n" .
                                 '            <DIV onclick="document.forms[\'viewlistForm_' . $sViewListID . '\'].order.value=\'' . $sField . ',' . ($aOrder[0] == $sField? ($aOrder[1] == 'ASC'? 'DESC' : 'ASC') : $aCol['db'][1]) . '\';lovd_AJAX_viewListSubmit(\'' . $sViewListID . '\');" style="position : relative;">' . "\n" .
                                 '              <IMG src="gfx/order_arrow' . $sImg . '.png" alt="' . $sAlt . '" title="' . $sAlt . '" width="13" height="12" style="position : absolute; top : 2px; right : 0px;">' . str_replace(' ', '&nbsp;', $aCol['view'][0]) . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</DIV>') .
                            (!$bSearchable? '' :
                                 "\n" .
                                 // SetTimeOut() is necessary because if the function gets executed right away, selecting a previously used value from a *browser-generated* list in one of the fields, gets aborted and it just sends whatever is typed in at that moment.
                                 '            <INPUT type="text" name="search_' . $sField . '" value="' . (!isset($_GET['search_' . $sField])? '' : htmlspecialchars($_GET['search_' . $sField])) . '" title="' . $aCol['view'][0] . ' field should contain..." style="width : ' . ($aCol['view'][1] - 6) . 'px; font-weight : normal;" onkeydown="if (event.keyCode == 13) { if (document.forms[\'viewlistForm_' . $sViewListID . '\'].page) { document.forms[\'viewlistForm_' . $sViewListID . '\'].page.value=1; } setTimeout(\'lovd_AJAX_viewListSubmit(\\\'' . $sViewListID . '\\\')\', 0); }">') .
                          '</TH>');
                }
                print('</TR></THEAD>');
            }
        }

        if (!$nTotal) {
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
                print('</TABLE>' . "\n");
                if (!$bHideNav) {
                    print('        <INPUT type="hidden" name="total" value="' . $nTotal . '" disabled>' . "\n" .
                          '        <INPUT type="hidden" name="page_size" value="' . $_GET['page_size'] . '">' . "\n" .
                          '        <INPUT type="hidden" name="page" value="' . $_GET['page'] . '">' . "\n");
                }
                print('      </DIV></FORM><BR>' . "\n\n");
                lovd_showInfoTable($sMessage, 'stop');
                return true;
            } else {
                if ($bOnlyRows) {
                    die('0'); // Silent error.
                }

                print('      </FORM>' . "\n\n");
                lovd_showInfoTable('No entries found for this ' . substr($_PATH_ELEMENTS[0], 0, -1) . '!', 'stop');

                return true;
            }
        }

        // To make row ids persist when the viewList is refreshed, we must store the row id in $_SESSION.
        if (!empty($_SESSION['viewlists'][$sViewListID]['row_id'])) {
            // FIXME; code can no longer overwrite the viewList, the first used value always overrides. Create setter!
            $this->sRowID = $_SESSION['viewlists'][$sViewListID]['row_id'];
        } else {
            // FIXME; incorporate garbage collection?
            $_SESSION['viewlists'][$sViewListID]['row_id'] = $this->sRowID; // Implies array creation.
            //$_SESSION['viewlists'][$sViewListID]['last_used'] = time(); // For garbage collection (not yet implemented).
            // ALTERNATIVE: create JS function lovd_restoreRowLink_XXX() (XXX == viewListID) that restores the rowLink, also after an Ajax Call.
        }

        // To make row links persist when the viewList is refreshed, we must store the row link in $_SESSION.
        if (!empty($_SESSION['viewlists'][$sViewListID]['row_link'])) {
            // FIXME; code can no longer overwrite the viewList, the first used value always overrides. Create setter!
            $this->sRowLink = $_SESSION['viewlists'][$sViewListID]['row_link'];
        } else {
            // FIXME; incorporate garbage collection?
            $_SESSION['viewlists'][$sViewListID]['row_link'] = $this->sRowLink; // Implies array creation.
            //$_SESSION['viewlists'][$sViewListID]['last_used'] = time(); // For garbage collection (not yet implemented).
            // ALTERNATIVE: create JS function lovd_restoreRowLink_XXX() (XXX == viewListID) that restores the rowLink, also after an Ajax Call.
        }

        while ($zData = $q->fetchAssoc()) {
            // If row_id is not given by the database, but it should be created according to some format ($this->sRowID), put the data's ID in this format.
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

            $zData = $this->prepareData($zData);

            // FIXME; rawurldecode() in the line below should have a better solution.
            print("\n" .
                  '        <TR class="' . (empty($zData['class_name'])? 'data' : $zData['class_name']) . '"' . (!$zData['row_id']? '' : ' id="' . $zData['row_id'] . '"') . ' valign="top"' . (!$zData['row_link']? '' : ' style="cursor : pointer;"') . (!$zData['row_link']? '' : ' onclick="' . (substr($zData['row_link'], 0, 11) == 'javascript:'? rawurldecode(substr($zData['row_link'], 11)) : 'window.location.href = \'' . $zData['row_link'] . '\';') . '"') . '>');
            foreach ($this->aColumnsViewList as $sField => $aCol) {
                if (in_array($sField, $aColsToSkip)) {
                    continue;
                }
                print("\n" . '          <TD' . (!empty($aCol['view'][2])? ' ' . $aCol['view'][2] : '') . ($aOrder[0] == $sField? ' class="ordered"' : '') . '>' . ($zData[$sField] === ''? '-' : $zData[$sField]) . '</TD>');
            }
            print('</TR>');
        }

        // Only print stuff if we're not just loading one entry right now.
        if (!$bOnlyRows) {
            print('</TABLE>' . "\n");
            if (!$bHideNav) {
                print('        <INPUT type="hidden" name="total" value="' . $nTotal . '" disabled>' . "\n" .
                      '        <INPUT type="hidden" name="page_size" value="' . $_GET['page_size'] . '">' . "\n" .
                      '        <INPUT type="hidden" name="page" value="' . $_GET['page'] . '">' . "\n\n");

                lovd_pagesplitShowNav($sViewListID, $nTotal);
            }
            if (!$bAjax) {
                print('      </DIV></FORM><BR>' . "\n"); // These contents will be replaced by Ajax.
            }
        }

        print('<SCRIPT type="text/javascript">lovd_stretchInputs(\'' . $sViewListID . '\');</SCRIPT>');

        return true;
    }
}
?>
