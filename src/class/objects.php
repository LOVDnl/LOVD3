<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-21
 * Modified    : 2016-09-09
 * For LOVD    : 3.0-17
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Msc. Daan Asscheman <D.Asscheman@LUMC.nl>
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

// Don't allow direct access.
if (!defined('ROOT_PATH')) {
    exit;
}

require_once ROOT_PATH . 'inc-lib-columns.php';




class LOVD_Object {
    // This class is the base class which is inherited by other object classes.
    // It provides basic functionality for setting up forms and showing data.
    var $sObject = '';
    var $sTable = '';
    var $aFormData = array();
    var $aCheckMandatory = array();
    var $sSQLPreLoadEntry = '';     // Query to be executed before $sSQLLoadEntry (as preparation)
    var $sSQLLoadEntry = '';
    var $sSQLPreViewEntry = '';     // Query to be executed before $aSQLViewEntry (as preparation)
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




    public function applyColumnFindAndReplace ($sFRFieldname, $sFRSearchValue, $sFRReplaceValue,
                                                $aArgs, $aOptions) {
        // Perform a find and replace action for given field name (column).
        // Return false if update query fails.

        global $_DB, $_AUTH;

        // Determine field name from select query.
        list(, $sFieldname) = $this->getTableAndFieldNameFromViewListCols($sFRFieldname);

        // Construct replace statement using viewlist's select query, without ORDER BY and LIMIT.
        $sSelectSQL = $this->buildSQL(array(
            'SELECT' => $this->aSQLViewList['SELECT'],
            'FROM' => $this->aSQLViewList['FROM'],
            'WHERE' => $this->aSQLViewList['WHERE'],
            'GROUP_BY' => $this->aSQLViewList['GROUP_BY'],
            'HAVING' => $this->aSQLViewList['HAVING'],
        ));
        $sSubqueryAlias = 'subq';
        $sReplaceStmt = $this->generateViewListFRReplaceStatement($sSubqueryAlias,
            $sFieldname, $sFRSearchValue, $sFRReplaceValue, $aOptions);

        // FIXME: This check should be done earlier, not just when running it.
        // Check user authorization needed to perform find and replace action.
        // Fixme: check if authorization level is correctly set for viewlist data.
        if ($_AUTH['level'] < LEVEL_CURATOR) {
            $sErr = 'You do not have authorization to perform this action.';
            lovd_displayError('FindAndReplace', $sErr);
            return false;
        }

        // ID field to connect rows from the original viewlist select query with rows in the
        // update query.
        // Note: this is hard-coded for now, meaning that each table must have this as its
        // ID field and each viewlist select query must include it. A more involved approach
        // would be to get the primary key in a separate query and include that in both the
        // update query and select subquery.
        $sIDField = $sSubqueryIDField = 'id';

        // Get tablename for update query.
        $sTablename = null;

        if ($this instanceof LOVD_CustomViewList) {
            $sCat = lovd_getCategoryCustomColFromName($sFRFieldname);
            $aTableInfo = lovd_getTableInfoByCategory($sCat);
            if ($aTableInfo !== false) {
                $sTablename = $aTableInfo['table_sql'];

                // Assume standard naming of id fields in custom viewlists:
                // table alias + "id" (e.g. "votid" or "sid").
                $sSubqueryIDField = $aTableInfo['table_alias'] . 'id';
            }
        } elseif (isset($this->sTable) && defined($this->sTable)) {
            $sTablename = constant($this->sTable);
        }

        if (is_null($sTablename)) {
            // No table is defined for this object, it is unclear what table to
            // perform find and replace on.
            $sErr = 'Cannot run update query for object with unknown table (object=' .
                get_class($this) . ', fieldname=' . $sFRFieldname . ').';
            lovd_displayError('FindAndReplace', $sErr);
            return false;
        }

        // Apply find & replace search condition so that only changed records will be updated.
        $sFRSearchCondition = $this->generateFRSearchCondition($sFRSearchValue, 'subq',
                                                               $sFRFieldname, $aOptions);

        // Construct and apply update query.
        $sUpdateSQL = 'UPDATE ' . $sTablename .  ', (' . $sSelectSQL . ') AS ' .
            $sSubqueryAlias . ' SET ' . $sTablename . '.`' . $sFieldname .
            '`=' . $sReplaceStmt . ', ' . $sTablename . '.edited_by=?, ' . $sTablename .
            '.edited_date=? WHERE ' . $sFRSearchCondition . ' AND ' . $sTablename . '.' .
            $sIDField . ' = ' . $sSubqueryAlias . '.' . $sSubqueryIDField;

        if ($sTablename == TABLE_VARIANTS_ON_TRANSCRIPTS) {
            // Update edited_by/-date fields of variant on genome table if query changes values on
            // variant on transcript.
            $sUpdateSQL = 'UPDATE ' . TABLE_VARIANTS .  ' vog INNER JOIN ' .
                          TABLE_VARIANTS_ON_TRANSCRIPTS . ' vot ON vog.id = vot.id, (' .
                          $sSelectSQL . ') AS ' . $sSubqueryAlias . ' SET vot.`' . $sFieldname .
                          '`=' . $sReplaceStmt . ', vog.edited_by=?, vog.edited_date=? WHERE ' .
                          $sFRSearchCondition . ' AND vot.' . $sIDField . ' = ' . $sSubqueryAlias .
                          '.' . $sSubqueryIDField;
        }

        // Add edit fields to SQL arguments.
        $aArgs[] = $_AUTH['id'];
        $aArgs[] = date('Y-m-d H:i:s');

        return (bool) $_DB->query($sUpdateSQL, $aArgs);
    }





    function buildSQL ($aSQL)
    {
        // Takes an $aSQL as commonly used in LOVD objects, and turns it into a normal SQL string.
        $sSQLOut = '';
        foreach ($aSQL as $sClause => $sValue) {
            if ($sValue !== '') {
                $sSQLOut .= (!$sSQLOut? '' : ' ') . str_replace('_', ' ', $sClause) . ' ' . $sValue;
            }
        }
        return $sSQLOut;
    }





    function checkFields ($aData, $zData = false)
    {
        // Checks fields before submission of data.
        global $_AUTH, $_SETT;

        $aForm = $this->getForm();
        $aFormInfo = array();
        if ($aForm) {
            $aFormInfo = $aForm[0];
            if (!in_array($aFormInfo[0], array('GET', 'POST'))) {
                // We're not working on a full form array, possibly an incomplete VOT form.
                $aFormInfo = array('POST');
            } else {
                unset($aForm[0]);
            }
        } else {
            // No form information available.
            $aForm = array();
        }

        if (lovd_getProjectFile() != '/import.php') {
            // Always mandatory... unless importing.
            $this->aCheckMandatory[] = 'password';
        }

        $aHeaders = array();

        // Validate form by looking at the form itself, and check what's needed.
        foreach ($aForm as $aField) {
            if (!is_array($aField)) {
                // 'skip', 'hr', etc...
                continue;
            }
            @list($sHeader, $sHelp, $sType, $sName) = $aField;
            if (lovd_getProjectFile() == '/import.php') {
                // During import, we don't mention the field names how they appear on screen, but using their IDs which are used in the file.
                $sHeader = $sName;
            }
            $aHeaders[$sName] = $sHeader;

            // Trim() all fields. We don't want those spaces in the database anyway.
            if (lovd_getProjectFile() != '/import.php' && isset($aData[$sName]) && !is_array($aData[$sName])) {
                $GLOBALS['_' . $aFormInfo[0]][$sName] = trim($GLOBALS['_' . $aFormInfo[0]][$sName]);
                $aData[$sName] = trim($aData[$sName]);
            }

            // Mandatory fields, as defined by child object.
            if (in_array($sName, $this->aCheckMandatory) && (!isset($aData[$sName]) || $aData[$sName] === '')) {
                lovd_errorAdd($sName, 'Please fill in the \'' . $sHeader . '\' field.');
            }

            if ($sType == 'select') {
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

        // Check all fields that we receive for data type and maximum length.
        // No longer to this through $aForm, because when importing,
        //  we do have data to check but no $aForm entry linked to it.
        foreach ($aData as $sFieldname => $sFieldvalue) {

            if (!is_string($sFieldvalue)) {
                // Checks below currently do not handle non-string values.
                continue;
            }

            $sNameClean = preg_replace('/^\d{' . $_SETT['objectid_length']['transcripts'] . '}_/', '', $sFieldname); // Remove prefix (transcriptid) that LOVD_TranscriptVariants puts there.
            if (isset($aHeaders[$sFieldname])) {
                $sHeader = $aHeaders[$sFieldname];
            } else {
                $sHeader = $sFieldname;
            }

            // Checking free text fields for max length, data types, etc.
            if ($sMySQLType = lovd_getColumnType(constant($this->sTable), $sNameClean)) {
                // FIXME; we're assuming here, that $sName equals the database name. Which is true in probably most/every case, but even so...
                // FIXME; select fields might also benefit from having this check (especially for import).

                // Check max length.
                $nMaxLength = lovd_getColumnLength(constant($this->sTable), $sNameClean);
                if (!empty($sFieldvalue)) {
                    // For numerical columns, maxlength works differently!
                    if (in_array($sMySQLType, array('DECIMAL', 'DECIMAL_UNSIGNED', 'FLOAT', 'FLOAT_UNSIGNED', 'INT', 'INT_UNSIGNED'))) {
                        // SIGNED cols: negative values.
                        if (in_array($sMySQLType, array('DECIMAL', 'INT')) && (int)$sFieldvalue < (int)('-' . str_repeat('9', $nMaxLength))) {
                            lovd_errorAdd($sFieldname, 'The \'' . $sHeader . '\' field is limited to numbers no lower than -' . str_repeat('9', $nMaxLength) . '.');
                        }
                        // ALL numerical cols (except floats): positive values.
                        if (substr($sMySQLType, 0, 5) != 'FLOAT' && (int)$sFieldvalue > (int)str_repeat('9', $nMaxLength)) {
                            lovd_errorAdd($sFieldname, 'The \'' . $sHeader . '\' field is limited to numbers no higher than ' . str_repeat('9', $nMaxLength) . '.');
                        }
                    } elseif (strlen($sFieldvalue) > $nMaxLength) {
                        lovd_errorAdd($sFieldname, 'The \'' . $sHeader . '\' field is limited to ' . $nMaxLength . ' characters, you entered ' . strlen($sFieldvalue) . '.');
                    }
                }

                // Check data type.
                if (!empty($sFieldvalue)) {
                    switch ($sMySQLType) {
                        case 'DATE':
                            if (!lovd_matchDate($sFieldvalue)) {
                                lovd_errorAdd($sFieldname, 'The field \'' . $sHeader . '\' must contain a date in the format YYYY-MM-DD, "' . htmlspecialchars($sFieldvalue) . '" does not match.');
                            }
                            break;
                        case 'DATETIME':
                            if (!preg_match('/^[0-9]{4}[.\/-][0-9]{2}[.\/-][0-9]{2}( [0-9]{2}\:[0-9]{2}\:[0-9]{2})?$/', $sFieldvalue)) {
                                lovd_errorAdd($sFieldname, 'The field \'' . $sHeader . '\' must contain a date, possibly including a time, in the format YYYY-MM-DD HH:MM:SS, "' . htmlspecialchars($sFieldvalue) . '" does not match.');
                            }
                            break;
                        case 'DECIMAL':
                        case 'DECIMAL_UNSIGNED':
                        case 'FLOAT':
                        case 'FLOAT_UNSIGNED':
                            if (!is_numeric($sFieldvalue) || (substr($sMySQLType, -8) == 'UNSIGNED' && $sFieldvalue < 0)) {
                                lovd_errorAdd($sFieldname, 'The field \'' . $sHeader . '\' must contain a' . (substr($sMySQLType, -8) != 'UNSIGNED' ? '' : ' positive') . ' number, "' . htmlspecialchars($sFieldvalue) . '" does not match.');
                            }
                            break;
                        case 'INT':
                        case 'INT_UNSIGNED':
                            if (!preg_match('/^' . ($sMySQLType != 'INT' ? '' : '\-?') . '[0-9]*$/', $sFieldvalue)) {
                                lovd_errorAdd($sFieldname, 'The field \'' . $sHeader . '\' must contain a' . ($sMySQLType == 'INT' ? 'n' : ' positive') . ' integer, "' . htmlspecialchars($sFieldvalue) . '" does not match.');
                            }
                            break;
                    }
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





    private function generateFRSearchCondition ($sFRSearchValue, $sTablename, $sFieldname,
                                                $aOptions)
    {
        // Return an SQL search condition for given search string, field name and match options.
        // Default is to match the search string anywhere in the field.

        $sCompositeFieldname = (!$sTablename? '' : $sTablename . '.') . '`' . $sFieldname . '`';
        if ($sFRSearchValue == '') {
            // When searching an empty string, match NULL field as well.
            $sCompositeFieldname = 'CAST(IFNULL(' . $sCompositeFieldname . ', "") AS CHAR)';
        }
        if ($sFRSearchValue == '' && (isset($aOptions['sFRMatchType']) && $aOptions['sFRMatchType'] == '1')) {
            // Searching for nothing anywhere in the field, means field must be empty.
            $sFRSearchCondition = $sCompositeFieldname . ' = ""';
        } elseif (isset($aOptions['sFRMatchType']) && $aOptions['sFRMatchType'] == '2') {
            // Match search string at beginning of field.
            $sFRSearchCondition = 'SUBSTRING(' . $sCompositeFieldname . ', 1, ' .
                                  strlen($sFRSearchValue) . ') = "' . $sFRSearchValue . '"';
        } elseif (isset($aOptions['sFRMatchType']) && $aOptions['sFRMatchType'] == '3') {
            // Match search string at end of field.
            $sFRSearchCondition = 'SUBSTRING(' . $sCompositeFieldname . ', -' .
                                  strlen($sFRSearchValue) . ') = "' . $sFRSearchValue . '"';
        } else {
            $sFRSearchCondition = $sCompositeFieldname . ' LIKE "%' . $sFRSearchValue . '%"';
        }
        return $sFRSearchCondition;
    }





    private function generateViewListFRReplaceStatement ($sTablename, $sFieldname, $sFRSearchValue,
                                                         $sFRReplaceValue, $aOptions)
    {
        // Return a SQL REPLACE statement for given field name and options.
        // Params:
        // - $sTableName        Name of the table.
        // - $sFieldname        Name of the table's field on which replace will be called.
        // - $sFRSearchValue    Find & replace search value.
        // - $sFRReplaceValue   Find & replace replace value.
        // - $aOptions          Array with options on how to perform replace.

        $nSearchStrLen = strlen($sFRSearchValue);
        $sCompositeFieldname = (!$sTablename? '' : $sTablename . '.') . '`' . $sFieldname . '`';
        $sReplacement = $sFRReplaceValue;

        if ($sFRSearchValue == '' && ((!isset($aOptions['sFRMatchType']) ||
                                       $aOptions['sFRMatchType'] == '1'))) {
            // When searching on empty string anywhere, we can assume we're replacing the whole
            // field.
            $sReplacement = '"' . $sFRReplaceValue . '"';
        } elseif ((!isset($aOptions['sFRMatchType']) || $aOptions['sFRMatchType'] == '1') &&
                  (!isset($aOptions['bFRReplaceAll']) || !$aOptions['bFRReplaceAll'])) {
            // Default is to replace occurrences anywhere in the field.
            return 'REPLACE(' . $sCompositeFieldname . ', "' . $sFRSearchValue . '", "' .
                   $sFRReplaceValue . '")';
        } elseif (isset($aOptions['bFRReplaceAll']) && $aOptions['bFRReplaceAll']) {
            // Whole field is replaced with a single value.
            $sReplacement = '"' . $sFRReplaceValue . '"';
        } elseif (isset($aOptions['sFRMatchType']) && $aOptions['sFRMatchType'] == '2') {
            // Replace search string at beginning of field.
            // E.g.:
            // CASE WHEN SUBSTRING(table.`field`, 1, 6) = "search"
            // THEN CONCAT("replace", SUBSTRING(table.`field`, 6))
            // ELSE table.`field` END
            $sReplacement = 'CONCAT("' . $sFRReplaceValue . '", SUBSTRING(' .
                            $sCompositeFieldname . ', ' . strval($nSearchStrLen + 1) . '))';

        } elseif (isset($aOptions['sFRMatchType']) && $aOptions['sFRMatchType'] == '3') {
            // Replace search string at end of field.
            // E.g.:
            // CASE WHEN SUBSTRING(table.`field`, - 6) = "search"
            // THEN CONCAT(SUBSTRING(table.`field`, 1, CHAR_LENGTH(table.`field`) - 6), "replace")
            // ELSE table.`field` END
            $sReplacement = 'CONCAT(' . 'SUBSTRING(' . $sCompositeFieldname . ', 1, CHAR_LENGTH(' .
                            $sCompositeFieldname . ') - ' . strval($nSearchStrLen) . '), "' .
                            $sFRReplaceValue . '")';
        }

        $sFRSearchCondition = $this->generateFRSearchCondition($sFRSearchValue, $sTablename,
                                                               $sFieldname, $aOptions);

        // Return replace statement.
        return 'CASE WHEN ' . $sFRSearchCondition . ' THEN ' . $sReplacement . ' ELSE ' .
               $sCompositeFieldname . ' END ';
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





    function getRowCountForViewList ($aSQL, $aArgs = array(), $bDebug = false)
    {
        // Attempt to speed up the "counting" part of the VL queries.
        // ViewList queries are counting the number of total hits using the
        // MySQL extension SQL_CALC_FOUND_ROWS. This works well for queries
        // sorted on non-indexed fields, where the query itself also requires a
        // full scan through the results. However,for queries that are normally
        // fast when LIMITed, this slows down the query a lot.
        // This function here will attempt to reduce the given query to a simple
        // SELECT COUNT(*) statement with as few joins as needed, resulting in
        // an as fast query as possible.
        // The $bDebug argument lets this function just return the SQL that is produced.
        global $_DB, $_INI;

        // If we don't have a HAVING clause, we can simply drop the SELECT information.
        $aColumnsNeeded = array();
        $aTablesNeeded = array();
        if (!$aSQL['GROUP_BY'] && !$aSQL['HAVING'] && !$aSQL['ORDER_BY']) {
            $aSQL['SELECT'] = '';
        } else {
            if ($aSQL['GROUP_BY']) {
                // We do have GROUP BY... We'll need to keep only the columns in the SELECT that are aliases,
                // but non-alias columns that are used for grouping must also be kept in the JOIN!
                // Parse GROUP BY! Can be a mix of real columns and aliases.
                if (preg_match_all('/\b(?:(\w+)\.)?(\w+)\b/', $aSQL['GROUP_BY'], $aRegs)) {
                    // This code is the same as for the ORDER BY parsing.
                    for ($i = 0; $i < count($aRegs[0]); $i ++) {
                        // 1: table referred to (real columns without alias only);
                        // 2: alias, or column name in given table.
                        if ($aRegs[1][$i]) {
                            // Real table. We don't need this in the SELECT unless it's also in the HAVING, but we definitely need this in the JOIN.
                            $aTablesNeeded[] = $aRegs[1][$i];
                        } elseif ($aRegs[2][$i]) {
                            // Alias only. Keep this column for the SELECT. When parsing the SELECT, we'll find out from which table(s) it is.
                            $aColumnsNeeded[] = $aRegs[2][$i];
                        }
                    }
                }
            }
            if ($aSQL['HAVING']) {
                // We do have HAVING, so now we'll have to see what we need to keep, the rest we toss out.
                // Parse HAVING! These are no fields directly from tables, but all aliases, so this parsing is different from parsing WHERE.
                // We don't care about AND/OR or anything... we just want the aliases.
                if (preg_match_all('/\b(\w+)\s(?:[!><=]+|IS (?:NOT )?NULL|LIKE )/', $aSQL['HAVING'], $aRegs)) {
                    $aColumnsNeeded = array_merge($aColumnsNeeded, $aRegs[1]);
                }
            }
            if ($aSQL['ORDER_BY']) {
                // We do have ORDER BY... We'll need to keep only the columns in the SELECT that are aliases,
                // but non-alias columns that are used for sorting must also be kept in the JOIN!
                // Parse ORDER BY! Can be a mix of real columns and aliases.
                // Adding a comma in the end, so we can use a simpler pattern that always ends with one.
                // FIXME: Wait, why are we parsing the ORDER_BY??? We can just drop it... and drop the cols which it uses... right?
                if (false && preg_match_all('/\b(?:(\w+)\.)?(\w+)(?:\s(?:ASC|DESC))?,/', $aSQL['ORDER_BY'] . ',', $aRegs)) {
                    // This code is the same as for the GROUP BY parsing.
                    for ($i = 0; $i < count($aRegs[0]); $i ++) {
                        // 1: table referred to (real columns without alias only);
                        // 2: alias, or column name in given table.
                        if ($aRegs[1][$i]) {
                            // Real table. We don't need this in the SELECT unless it's also in the HAVING, but we definitely need this in the JOIN.
                            $aTablesNeeded[] = $aRegs[1][$i];
                        } elseif ($aRegs[2][$i]) {
                            // Alias only. Keep this column for the SELECT. When parsing the SELECT, we'll find out from which table it is.
                            $aColumnsNeeded[] = $aRegs[2][$i];
                        }
                    }
                }
                // We never need an ORDER BY to get the number of results, so...
                $aSQL['ORDER_BY'] = '';
            }
        }
        $aColumnsNeeded = array_unique($aColumnsNeeded);
        if (!$aColumnsNeeded) {
            $aSQL['SELECT'] = '';
        }



        // Now that we know which columns we should keep, we can parse the SELECT clause to see what we can remove.
        $aColumnsUsed = array(); // Will contain limited information on the columns defined in the SELECT syntax.
        if ($aSQL['SELECT'] && $aColumnsNeeded) {
            // Analyzing the SELECT. This is quite difficult as we can have simple SELECTs but also really complicated ones,
            // such as GROUP_CONCAT() or subselects. These should all be parsed and needed tables should be identified.
            //                    t.* || t.col                    [t.col || "value" || (t.col ... val) || FUNCTION() || CASE ... END] AS alias
            if (preg_match_all('/(([a-z0-9_]+)\.(?:\*|[a-z0-9_]+)|(?:(?:([a-z0-9_]+)\.[a-z0-9_]+|".*"|[A-Z_]*\(.+\)|CASE .+ END) AS +([a-z0-9_]+|`[A-Za-z0-9_\/]+`)))(?:,|$)/U', $aSQL['SELECT'], $aRegs)) {
                for ($i = 0; $i < count($aRegs[0]); $i ++) {
                    // First we'll store the column information, later we'll loop though it to see which tables they refer to.
                    // 1: entire SELECT string incl. possible alias;
                    // 2: table referred to (fields without alias only);
                    // 3: table referred to (simple fields with alias only);
                    // 4: alias, if present.
                    // Try to see which table(s) is/are used here.
                    $aTables = array();
                    $sTable = ($aRegs[2][$i]? $aRegs[2][$i] : $aRegs[3][$i]);
                    if ($sTable) {
                        $aTables[] = $sTable;
                    } else {
                        // OK, this was no simple SELECT string. This was GROUP_CONCAT, COUNT() or similar.
                        // Especially (GROUP_)CONCAT can contain quite some different columns and even tables.
                        // Analyzing the field definition... We don't care about its structure or anything... we just want tables.
                        // There should *always* be table aliases, so it's going to be easy.
                        // With subqueries however, this will fail a bit. It will find table aliases that may be of tables in the subquery.
                        //  However, in the worst case scenario it will keep tables that are not necessary to be kept.
                        if (preg_match_all('/\b(\w+)\.(?:`|[A-Za-z]|\*)/', $aRegs[1][$i], $aRegsTables)) {
                            $aTables = array_unique($aRegsTables[1]);
                        }
                    }
                    // Key: alias or, when not available, the SELECT statement (table.col).
                    $aColumnsUsed[($aRegs[4][$i]? $aRegs[4][$i] : $aRegs[1][$i])] = array(
                        'SQL' => $aRegs[1][$i],
                        'tables' => $aTables,
                    );
                    // We don't need more info anyway.
                }
            }

            // Now, loop the parsed columns, check which fields are needed, rebuild the SELECT statement, and store which tables will be needed.
            $aSQL['SELECT'] = '';
            foreach ($aColumnsUsed as $sCol => $aCol) {
                if (in_array($sCol, $aColumnsNeeded)) {
                    $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . $aCol['SQL'];
                    $aTablesNeeded = array_merge($aTablesNeeded, $aCol['tables']);
                }
            }
        }



        // Analyzing the WHERE... We don't care about AND/OR or anything... we just want tables.
        // WHERE clauses *always* contain the table aliases, so it's going to be easy.
        if (preg_match_all('/\b(\w+)\.(?:`|[A-Za-z])/', $aSQL['WHERE'], $aRegs)) {
            $aTablesNeeded = array_merge($aTablesNeeded, $aRegs[1]);
        }

        // When we're running filters on the custom columns, we never use a table alias,
        // because we don't know where the column comes from.
        // To solve this, we must parse the column and fetch the used alias from the query.
        // We're specifically looking for custom columns *not* prefixed by a table alias.
        if (preg_match_all('/[^.](?:`(\w+)\/[A-Za-z0-9_\/]+`)/', $aSQL['WHERE'], $aRegs)) {
            // To not reproduce code, we'll use lovd_getTableInfoByCategory().
            if (!function_exists('lovd_getTableInfoByCategory')) {
                // FIXME: Yes, this is messy. This function is getting used in a decent number of places,
                //  and therefore perhaps we should pull it into inc-lib-init.php?
                require ROOT_PATH . 'inc-lib-columns.php';
            }
            // Loop columns and find tables.
            foreach ($aRegs[1] as $sCategory) {
                $aTableInfo = lovd_getTableInfoByCategory($sCategory);
                if (isset($aTableInfo['table_sql']) && preg_match_all('/' . $aTableInfo['table_sql'] . ' AS (\w+)\b/i', $aSQL['FROM'], $aRegsTables)) {
                    $aTablesNeeded = array_merge($aTablesNeeded, $aRegsTables[1]);
                } else {
                    // OK, this really shouldn't happen. Either the column wasn't a
                    // category we recognized, or the SQL was too complicated?
                    // Let's log this.
                    lovd_writeLog('Error', 'LOVD-Lib', 'LOVD_Object::getRowCountForViewList() - Function identified custom column category ' . $sCategory . ', but couldn\'t find corresponding table alias in query.' . "\n" . 'URL: ' . preg_replace('/^' . preg_quote(rtrim(lovd_getInstallURL(false), '/'), '/') . '/', '', $_SERVER['REQUEST_URI']) . "\n" . 'From: ' . $aSQL['FROM']);
                }
            }
        }
        $aTablesNeeded = array_unique($aTablesNeeded);



        // Now, SELECT should be as small as possible. What's left in the SELECT is needed.
        // See which tables we can't remove from the JOIN because they're in SELECT, or because they're in the WHERE.
        // (INNER JOINs will never be removed).
        // Now shorten the JOIN as much as possible!
        // Tables *always* use aliases so we'll just search for those.
        // While matching, we add a space before the FROM so that we can match the first table as well, but it won't have a JOIN statement captured.
        $aTablesUsed = array();
        if (preg_match_all('/\s?((?:LEFT(?: OUTER)?|INNER) JOIN)?\s(' . preg_quote(TABLEPREFIX, '/') . '_[a-z0-9_]+) AS ([a-z0-9]+)\s/', ' ' . $aSQL['FROM'], $aRegs)) {
            for ($i = 0; $i < count($aRegs[0]); $i ++) {
                // 1: JOIN syntax;
                // 2: full table name;
                // 3: table alias.
                $aTablesUsed[$aRegs[3][$i]] = array(
                    'name' => $aRegs[2][$i], // We don't actually use the name, but well...
                    'join' => $aRegs[1][$i],
                );
            }
        }

        // Loop these tables in reverse, and remove JOINs as much as possible!
        foreach (array_reverse(array_keys($aTablesUsed)) as $sTableAlias) {
            if (!$aTablesUsed[$sTableAlias]['join'] || in_array($sTableAlias, $aTablesNeeded)) {
                // We've reached a table that we need, abort now.
                break;
                // FIXME: Actually, it's possible that more tables can be left out, although in most cases we're really done now.
                //   To find out, we'd actually need to analyze which tables we're joining together.
            }
            // OK, this table is not needed. Get rid of it.
            if ($aTablesUsed[$sTableAlias]['join'] != 'INNER JOIN' && ($nPosition = strrpos($aSQL['FROM'], $aTablesUsed[$sTableAlias]['join'])) !== false) {
                $aSQL['FROM'] = rtrim(substr($aSQL['FROM'], 0, $nPosition));
                unset($aTablesUsed[$sTableAlias]);
            }
        }



        // If we have no SELECT left, we can surely do a simple SELECT COUNT(*) FROM ... or
        // a SELECT COUNT(*) FROM (SELECT ...)A. We can't do a simple SELECT COUNT(*) if
        // we have a GROUP_BY, because it will separate the counts.
        // In case we still have a SELECT, and we create a subquery while the
        // SELECT has double columns (happens rarely), we get a query error. In
        // that case we could drop the first column's declaration, or otherwise
        // keep using the SQL_CALC_FOUND_ROWS().
        // For now, we'll just take our chances. If this query will fail, LOVD
        // will fall back on the original SQL_CALC_FOUND_ROWS() method.
        $bInSubQuery = false;
        if (!$aSQL['SELECT']) {
            // If we just have one table left, we might be able to drop the GROUP BY.
            // If so, we can use a simple COUNT(*) query instead of a nested one.
            // In 99%, if not all, of the cases we can just drop the GROUP BY since
            // we "always" put it on the first table's ID, but just to be sure:
            if (count($aTablesUsed) == 1 && $aSQL['GROUP_BY'] == current(array_keys($aTablesUsed)) . '.id') {
                // Using one table, and grouping on its ID.
                $aSQL['GROUP_BY'] = '';
            }

            if (!$aSQL['GROUP_BY']) {
                // Simple SELECT COUNT(*) FROM ...
                $aSQL['SELECT'] = 'COUNT(*)';
            } else {
                // We'll have to create a bigger query around this...
                // We'll build that query in the end.
                $bInSubQuery = true;
                $aSQL['SELECT'] = '1';
            }
        } else {
            // SELECT is left (meaning we had a HAVING), we have to use a subquery!
            $bInSubQuery = true;
        }

        // Delete LIMIT, we don't want that anymore...
        $aSQL['LIMIT'] = '';



        $sSQLOut = $this->buildSQL($aSQL);
        // Now, build the subquery if we need it.
        if ($bInSubQuery) {
            $sSQLOut = 'SELECT COUNT(*) FROM (' . $sSQLOut . ')A';
        }

        if ($bDebug) {
            return $sSQLOut;
        }

        // Run the query, fetch the result and return.
        // We'll return false when we failed.
        $nCount = false;
        $qCount = $_DB->query($sSQLOut, $aArgs, false);
        if ($qCount !== false) {
            $nCount = $qCount->fetchColumn();
        }

        if ($nCount === false) {
            // We failed, log this. Actually, why aren't query errors logged if they're not fatal?
            lovd_queryError('QueryOptimizer', $sSQLOut, 'Error in ' . __FUNCTION__ . '() while executing optimized query.', false);

            // As a fallback, use SQL_CALC_FOUND_ROWS() for MySQL instances, or
            // a count() on a full result set otherwise. The latter is super
            // inefficient, and only meant for small SQLite databases.
            if ($_INI['database']['driver'] == 'mysql') {
                $this->aSQLViewList['SELECT'] = 'SQL_CALC_FOUND_ROWS ' . $this->aSQLViewList['SELECT'];
                $this->aSQLViewList['LIMIT'] = '0';
                $_DB->query($this->buildSQL($this->aSQLViewList), $aArgs);
                $nCount = $_DB->query('SELECT FOUND_ROWS()')->fetchColumn();
            } else {
                // Super inefficient, only for low-volume (sqlite) databases!
                $nCount = count($_DB->query($this->buildSQL($this->aSQLViewList), $aArgs)->fetchAllColumn());
            }
        }

        return $nCount;
    }








    function getSortDefault ()
    {
        return $this->sSortDefault;
    }





    private function getTableAndFieldNameFromViewListCols ($sFRFieldname)
    {
        // Try to translate UI field name to fieldname and tablename in the database based on
        // the SQL query definitions. (note that a field name returned by the interface (returned
        // by the select query) may be different from the fieldname in the table due to aliases).

        // All columns for Find & Replace *must* be defined in the column's list.
        // Check if column exists there. If not, display an error.
        if (!isset($this->aColumnsViewList[$sFRFieldname])) {
            lovd_displayError('FindAndReplace', 'Find and Replace requested on undefined field name "' . $sFRFieldname . '".');
        }

        // Column should be configured to allow Find & Replace.
        if (empty($this->aColumnsViewList[$sFRFieldname]['allowfnr'])) {
            lovd_displayError('FindAndReplace', 'Find and Replace requested on field "' . $sFRFieldname . '", which does not have that feature enabled.');
        }

        // Column name in the database may be a function, but
        // those columns should not have 'allowfnr' set to true.
        $sTableName = '';
        $sFieldName = $this->aColumnsViewList[$sFRFieldname]['db'][0];
        if (preg_match('/^([A-Za-z0-9_`]+)\.([A-Za-z0-9_`\/]+)$/', $sFieldName, $aRegs)) {
            $sTableName = $aRegs[1];
            $sFieldName = $aRegs[2];
        }

        // Because we will append the name of the column with something to
        // create the preview column, we need to trim any backticks off.
        $sFieldName = trim($sFieldName, '`');

        // Note: tablename may be an alias.
        return array($sTableName, $sFieldName);
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

        if ($this->sSQLPreLoadEntry !== '') {
            // $sSQLPreLoadEntry is defined, execute it.
            $_DB->query($this->sSQLPreLoadEntry);
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





    public static function lovd_getObjectLinksHTML ($aIDs, $sURLFormat)
    {
        // Returns a list of object links in HTML format.
        // Parameter $aIDs is an array with object IDs, and optionally, values.
        // Parameter $sURLFormat designates the target of the links, where any
        //   sprintf() recognized format (like %s) will be substituted with the
        //   object ID, e.g. "genes/%s".
        // For more information on formats to use, see:
        //   http://php.net/manual/en/function.sprintf.php

        $sShortDescription = '';
        $sHTMLoutput = '';
        $i = 0;
        foreach ($aIDs as $key => $val) {
            if (is_array($val)) {
                $sObjectID = $val[0];
                $sObjectValue = $val[1];
            } else {
                $sObjectID = $sObjectValue = $val;
            }

            $sHTMLoutput .= (!$key ? '' : ', ') . '<A href="' . sprintf($sURLFormat, $sObjectID) .
                '">' . $sObjectValue . '</A>';
            if ($i < 20) {
                $sShortDescription .= (!$key ? '' : ', ') . '<A href="' .
                    sprintf($sURLFormat, $sObjectID) . '">' . $sObjectValue . '</A>';
                $i++;
            }
        }
        if (count($aIDs) > 22) {
            // Replace long gene list by shorter one, allowing expand.
            $sHTMLoutput = '<SPAN>' . $sShortDescription . ', <A href="#" onclick="$(this).parent().hide(); $(this).parent().next().show(); return false;">' . (count($aIDs) - $i) . ' more...</A></SPAN><SPAN style="display : none;">' . $sHTMLoutput . '</SPAN>';
        }
        return $sHTMLoutput;
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
            // By default, we put anchors in the id_ and DNA fields, if present.
            if ($zData['row_link']) {
                if (isset($this->aColumnsViewList['id_']) && $zData['id']) {
                    $zData['id_'] = '<A href="' . $zData['row_link'] . '" class="hide">' . $zData['id'] . '</A>';
                }
                foreach (array('VariantOnGenome/DNA', 'VariantOnTranscript/DNA') as $sCol) {
                    if (isset($this->aColumnsViewList[$sCol])) {
                        $zData[$sCol] = '<A href="' . $zData['row_link'] . '" class="hide">' . $zData[$sCol] . '</A>';
                    }
                }
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
                        $zData['owned_by_'] .= (!$zData['owned_by_']? '' : ', ') .
                            '<SPAN class="custom_link" onmouseover="lovd_showToolTip(\'' .
                            addslashes('<TABLE border=0 cellpadding=0 cellspacing=0 width=350 class=S11><TR><TH valign=top>User&nbsp;ID</TH><TD>' . ($_AUTH['level'] < LEVEL_MANAGER? $nID : '<A href=users/' . $nID . '>' . $nID . '</A>') . '</TD></TR><TR><TH valign=top>Name</TH><TD>' . $sName . '</TD></TR><TR><TH valign=top>Email&nbsp;address</TH><TD>' . str_replace("\r\n", '<BR>', lovd_hideEmail($sEmail)) . '</TD></TR><TR><TH valign=top>Institute</TH><TD>' . $sInstitute . '</TD></TR><TR><TH valign=top>Department</TH><TD>' . $sDepartment . '</TD></TR><TR><TH valign=top>Country</TH><TD>' . $sCountryID . '</TD></TR></TABLE>') .
                            '\', this, [-200, 0]);">' . $sName . '</SPAN>';
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




    private function previewColumnFindAndReplace ($sFRFieldname, $sFRFieldDisplayname,
                                                  $sFRSearchValue, $sFRReplaceValue, $aArgs, $aOptions)
    {
        // Append a field to the viewlist showing a preview of changes for a
        // find and replace (F&R) action. Returns the number of rows that will
        // be affected by the F&R.
        // Params:
        // sFRFieldname         Name of field on which F&R to preview.
        // sFRFieldDisplayname  Display name of field for F&R.
        // sFRSearchValue       Search string.
        // sFRReplaceValue      Replace value.
        // aOptions             F&R options (e.g. match type)

        global $_DB;

        // Try to discover the tablename and fieldname, as $sFRFieldname may be
        // an alias.
        list($sTablename, $sFieldname) = $this->getTableAndFieldNameFromViewListCols($sFRFieldname);

        // Run query with search field to compute number of affected rows, skipping ORDER BY and LIMIT.
        $sSelectSQL = $this->buildSQL(array(
            'SELECT' => $this->aSQLViewList['SELECT'],
            'FROM' => $this->aSQLViewList['FROM'],
            'WHERE' => $this->aSQLViewList['WHERE'],
            'GROUP_BY' => $this->aSQLViewList['GROUP_BY'],
            'HAVING' => $this->aSQLViewList['HAVING'],
        ));
        $sFRSearchCondition = $this->generateFRSearchCondition($sFRSearchValue, 'subq',
                                                               $sFRFieldname, $aOptions);
        $oResult = $_DB->query('SELECT count(*) FROM (' . $sSelectSQL . ') AS subq WHERE ' .
                               $sFRSearchCondition, $aArgs);
        $nAffectedRows = intval($oResult->fetchColumn());

        // Construct replace statement.
        $sReplaceStmt = $this->generateViewListFRReplaceStatement($sTablename, $sFieldname,
            $sFRSearchValue, $sFRReplaceValue, $aOptions);

        // Set names for preview column.
        $sPreviewFieldname = $sFRFieldname . '_FR';
        $sPreviewFieldDisplayname = $sFRFieldDisplayname . ' (PREVIEW)';

        // Edit sql in $this->aSQLViewList to include an F&R column.
        $this->aSQLViewList['SELECT'] .= ",\n";
        $this->aSQLViewList['SELECT'] .= $sReplaceStmt . ' AS `' . $sPreviewFieldname . '`';

        // Add description of preview-field in $this->aColumnsViewList based on original field.
        $aFRColValues = $this->aColumnsViewList[$sFRFieldname];
        if (!isset($aFRColValues['view'])) {
            $aFRColValues['view'] = array($sPreviewFieldDisplayname, 160, 'class="FRPreview"');
        } else {
            $aFRColValues['view'][0] = $sPreviewFieldDisplayname;
        }
        $aFRColValues['db'] = array($sFRFieldname);

        // Place preview column just behind column where F&R is performed on.
        $this->aColumnsViewList = lovd_arrayInsertAfter($sFRFieldname, $this->aColumnsViewList,
            $sPreviewFieldname, $aFRColValues);

        return $nAffectedRows;
    }





    public function processViewListSearchArgs ($aRequest)
    {
        // Generate WHERE and HAVING statements for search field content in viewlist.
        // Returns an array with:
        // - $WHERE         Array of strings to be added to the WHERE clause of the viewlist query.
        // - $HAVING        Array of strings to be added to the HAVING clause of the query.
        // - $aArguments    Array of arrays with arguments for placeholders in WHERE and HAVING
        //                  clauses.
        // - $aBadSyntaxColumns ?
        // - $aColTypes     ?

        global $_INI;

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
            if (!empty($aCol['db'][2]) && isset($aRequest['search_' . $sColumn]) && trim($aRequest['search_' . $sColumn]) !== '') {
                $CLAUSE = (strpos($aCol['db'][0], '.') === false && strpos($aCol['db'][0], '/') === false ? 'HAVING' : 'WHERE');
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
                    $sSearch = preg_replace('/ (\d)/', "{{SPACE}}$1", trim($aRequest['search_' . $sColumn]));
                } else {
                    $sSearch = preg_replace_callback('/("[^"]*")/', create_function('$aRegs', 'return str_replace(\' \', \'{{SPACE}}\', $aRegs[1]);'), trim($aRequest['search_' . $sColumn]));
                }
                $aWords = explode(' ', $sSearch);
                foreach ($aWords as $sWord) {
                    if ($sWord !== '') {
                        $sWord = lovd_escapeSearchTerm($sWord);
                        $aOR = (preg_match('/^[^|]+(\|[^|]+)+$/', $sWord) ? explode('|', $sWord) : array($sWord));
                        $$CLAUSE .= ($$CLAUSE ? ' AND ' : '') . (!empty($aOR[1]) ? '(' : '');
                        foreach ($aOR as $nTerm => $sTerm) {
                            $$CLAUSE .= ($nTerm ? ' OR ' : '');
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
                                            $sOperator = (substr($sOperator, 0, 1) == '!' ? '!=' : $sOperator);
                                        } else {
                                            $sOperator = '=';
                                        }
                                        $$CLAUSE .= '(' . $aCol['db'][0] . ' ' . $sOperator . ' ' . ($_INI['database']['driver'] != 'sqlite' ? '?' : 'CAST(? AS NUMERIC)') . ($sOperator == '!=' ? ' OR ' . $aCol['db'][0] . ' IS NULL)' : ')');
                                        $aArguments[$CLAUSE][] = $sTerm;
                                    } elseif (preg_match('/^!?=""$/', $sTerm)) {
                                        // Numeric fields cannot be empty, they are NULL. So searching for ="" must return all NULL values.
                                        $$CLAUSE .= $aCol['db'][0] . ' IS ' . (substr($sTerm, 0, 1) == '!' ? 'NOT ' : '') . 'NULL';
                                    } else {
                                        // Bad syntax! Report. LOVD doesn't actually complain about bad syntax columns
                                        // when they're not viewed, but we do need that var to be filled.
                                        $aBadSyntaxColumns[] = $aCol[($aCol['view']? 'view' : 'db')][0];
                                    }
                                    break;
                                case 'DATE':
                                case 'DATETIME':
                                    if (preg_match('/^([><]=?|!)?(\d{4})(?:(-\d{2})' . ($sColType == 'DATETIME' ? '(?:(-\d{2})(?:( \d{2})(?:(:\d{2})(:\d{2})?)?)?)?)?' : '(-\d{2})?)?') . '$/', $sTerm, $aMatches)) {
                                        @list(, $sOperator, $nYear, $nMonth, $nDay, $nHour, $nMinute, $nSecond) = $aMatches;
                                        if (!checkdate(($nMonth ? substr($nMonth, 1) : '01'), ($nDay ? substr($nDay, 1) : '01'), $nYear) && $aCol['view']) {
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
                                                    $sOperator = ($sOperator == '!' ? 'NOT ' : '') . 'LIKE';
                                                }
                                                $aTerms = array(3 => '', '', '', '', '');
                                                break;
                                        }
                                        unset($aMatches[0], $aMatches[1]);
                                        // Replace our default date components by the ones given by the user.
                                        $aTerms = $aMatches + $aTerms;
                                        ksort($aTerms);
                                        $sTerms = implode($aTerms);
                                        $$CLAUSE .= '(' . $aCol['db'][0] . ' ' . $sOperator . ' ?' . ($sOperator == 'NOT LIKE' ? ' OR ' . $aCol['db'][0] . ' IS NULL)' : ')');
                                        $aArguments[$CLAUSE][] = $sTerms . (substr($sOperator, -4) == 'LIKE' ? '%' : '');
                                    } elseif (preg_match('/^!?=""$/', $sTerm)) {
                                        // DATE(TIME) fields cannot be empty, they are NULL. So searching for ="" must return all NULL values.
                                        $$CLAUSE .= $aCol['db'][0] . ' IS ' . (substr($sTerm, 0, 1) == '!' ? 'NOT ' : '') . 'NULL';
                                    } else {
                                        // Bad syntax! Report. LOVD doesn't actually complain about bad syntax columns
                                        // when they're not viewed, but we do need that var to be filled.
                                        $aBadSyntaxColumns[] = $aCol[($aCol['view']? 'view' : 'db')][0];
                                    }
                                    break;
                                default:
                                    if (preg_match('/^!?"?([^"]+)"?$/', $sTerm, $aMatches)) {
                                        $sOperator = (substr($sTerm, 0, 1) == '!' ? 'NOT ' : '') . 'LIKE';
                                        $$CLAUSE .= '(' . $aCol['db'][0] . ' ' . $sOperator . ' ?' . ($sOperator == 'NOT LIKE' ? ' OR ' . $aCol['db'][0] . ' IS NULL)' : ')');
                                        $aArguments[$CLAUSE][] = '%' . $aMatches[1] . '%';
                                    } elseif (preg_match('/^!?=""$/', $sTerm)) {
                                        $bNot = (substr($sTerm, 0, 1) == '!');
                                        if ($bNot) {
                                            $$CLAUSE .= '(' . $aCol['db'][0] . ' != "" AND ' . $aCol['db'][0] . ' IS NOT NULL)';
                                        } else {
                                            $$CLAUSE .= '(' . $aCol['db'][0] . ' = "" OR ' . $aCol['db'][0] . ' IS NULL)';
                                        }
                                    } elseif (preg_match('/^!?="([^"]*)"$/', $sTerm, $aMatches)) {
                                        $sOperator = (substr($sTerm, 0, 1) == '!' ? '!=' : '=');
                                        $$CLAUSE .= '(' . $aCol['db'][0] . ' ' . $sOperator . ' ?' . ($sOperator == '!=' ? ' OR ' . $aCol['db'][0] . ' IS NULL)' : ')');
                                        // 2013-07-25; 3.0-07; When not using LIKE, undo escaping done by lovd_escapeSearchTerm().
                                        $aArguments[$CLAUSE][] = str_replace(array('\%', '\_'), array('%', '_'), $aMatches[1]);
                                    } else {
                                        // Bad syntax! Report. LOVD doesn't actually complain about bad syntax columns
                                        // when they're not viewed, but we do need that var to be filled.
                                        $aBadSyntaxColumns[] = $aCol[($aCol['view']? 'view' : 'db')][0];
                                    }
                                    break;
                            }
                        }
                        $$CLAUSE .= (!empty($aOR[1]) ? ')' : '');
                    }
                }
            }
        }
        return array($WHERE, $HAVING, $aArguments, $aBadSyntaxColumns, $aColTypes);
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

        if ($this->sSQLPreViewEntry !== '') {
            $_DB->query($this->sSQLPreViewEntry);
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





    function viewList ($sViewListID = false, $aColsToSkip = array(), $bNoHistory = false,
                       $bHideNav = false, $bOptions = false, $bOnlyRows = false,
                       $bFindReplace = false)
    {
        // Show a viewlist for the current object.
        // Params:
        // bFindReplace     if true, find & replace option is shown in viewlist options menu.

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

        // Process search fields (i.e. $_GET['search_...'] values) for viewlist.
        list($WHERE, $HAVING, $aArguments, $aBadSyntaxColumns, $aColTypes) = $this->processViewListSearchArgs($_GET);
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
        // At this point, we're not sure if we'll actually use the ORDER BY at all.
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

        // Process input values regarding find & replace.
        // User clicked preview.
        $bFRPreview =           isset($_GET['FRPreviewClicked_' . $sViewListID]) &&
                                $_GET['FRPreviewClicked_' . $sViewListID] == '1';
        // Selected field name for replace.
        $sFRFieldname =         isset($_GET['FRFieldname_' . $sViewListID])?
                                $_GET['FRFieldname_' . $sViewListID] : null;
        // Display name of selected field.
        $sFRFieldDisplayname =  isset($_GET['FRFieldDisplayname_' . $sViewListID])?
                                $_GET['FRFieldDisplayname_' . $sViewListID] : null;
        // Search query for find & replace.
        $sFRSearchValue =       isset($_GET['FRSearch_' . $sViewListID])?
                                $_GET['FRSearch_' . $sViewListID] : null;
        // Replace value for find & replace.
        $sFRReplaceValue =      isset($_GET['FRReplace_' . $sViewListID])?
                                $_GET['FRReplace_' . $sViewListID] : null;
        // Type of matching.
        $sFRMatchType =         isset($_GET['FRMatchType_' . $sViewListID])?
                                $_GET['FRMatchType_' . $sViewListID] : null;
        // Flag stating whether all field content sould be replaced.
        $bFRReplaceAll =        isset($_GET['FRReplaceAll_' . $sViewListID]) &&
                                $_GET['FRReplaceAll_' . $sViewListID] == '1';
        // Predicted affected row count.
        $nFRRowsAffected = null;
        // Find & replace options parameter.
        $aFROptions = array(
            'sFRMatchType' =>   $sFRMatchType,
            'bFRReplaceAll' =>  $bFRReplaceAll
        );

        $nTotal = 0;
        if (!count($aBadSyntaxColumns)) {
            // Build argument list.
            $aArgs = array_merge($aArguments['WHERE'], $aArguments['HAVING']);

            if ($bFRPreview) {
                // User clicked 'preview' in Find&Replace form, add F&R changes as a separate
                // column in the query.
                $nFRRowsAffected = $this->previewColumnFindAndReplace($sFRFieldname,
                    $sFRFieldDisplayname, $sFRSearchValue, $sFRReplaceValue, $aArgs, $aFROptions);
            }


            // Using the SQL_CALC_FOUND_ROWS technique to find the amount of hits in one go.
            // First find the amount of rows returned. We can use the SQL_CALC_FOUND_ROWS()
            // function, but we'll try to avoid that due to extreme slowness in some cases.
            // getRowCountForViewList() will take care of that.
            // There is talk about a possible race condition using this technique on the mysql_num_rows man page, but I could find no evidence of it's existence on InnoDB tables.
            // Just to be sure, I'm implementing a serializable transaction, which should lock the table between the two SELECT queries to ensure proper results.
            // Last checked 2010-01-25, by Ivo Fokkema.
            $_DB->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
            $_DB->beginTransaction();

            // For ALL viewlists, we store the number of hits that we get, including the current filters.
            // For large tables, getting a count can take a long time (especially when using SQL_CALC_FOUND_ROWS).
            // ORDER BY is absolutely killing on large result sets.
            // So, long time to retrieve count (>1s) => don't count again, and no sort.
            // Count OK (<=1s), but big result set (250K) => no sort. ($_SETT['lists']['max_sortable_rows'])

            // 1) If we don't have a count in memory, request count separately.
            // Also if last count was >15min ago, request again.
            $bTrueCount = false; // Indicates whether or not we are sure about the number of results.
            $sFilterMD5 = md5($WHERE . '||' . $HAVING . '||' . implode('|', $aArgs)); // A signature for the filters, NOTE that this depends on the column order!
            // FIXME: If this count takes longer than 1s, we don't estimate anymore like we used to (see line 1543).
            if (true || !isset($aSessionViewList['counts'][$sFilterMD5]['n'])) {
                $t = microtime(true);
                // Now, get the total number of hits if no LIMIT was used. Note that $nTotal gets overwritten here.
                $nTotal = $this->getRowCountForViewList($this->aSQLViewList, $aArgs);
                $tQ = microtime(true) - $t;
                $aSessionViewList['counts'][$sFilterMD5]['n'] = $nTotal;
                $aSessionViewList['counts'][$sFilterMD5]['t'] = $tQ;
                $aSessionViewList['counts'][$sFilterMD5]['d'] = time();
                $bTrueCount = true;
            }



            // Manipulate SELECT to include SQL_CALC_FOUND_ROWS.
            $bSQLCALCFOUNDROWS = false;
            // TODO: Remove this block. For now, this will be bypassed because $bTrueCount will always be true.
            if (!$bTrueCount && $_INI['database']['driver'] == 'mysql' && ($aSessionViewList['counts'][$sFilterMD5]['t'] < 1 || $aSessionViewList['counts'][$sFilterMD5]['d'] < (time() - (60*15)))) {
                // But only if we're using MySQL and it takes less than a second to get the correct number of results, or it's been more than 15 minutes since the last check!
                $this->aSQLViewList['SELECT'] = 'SQL_CALC_FOUND_ROWS ' . $this->aSQLViewList['SELECT'];
                $bSQLCALCFOUNDROWS = true;
            }


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
                        $sSQL = $this->buildSQL(array(
                            'SELECT' => $this->aSQLViewList['SELECT'],
                            'FROM' => $this->aSQLViewList['FROM'],
                            'WHERE' => $this->aSQLViewList['WHERE'],
                            'GROUP_BY' => $this->aSQLViewList['GROUP_BY'],
                            'HAVING' => $this->aSQLViewList['HAVING'],
                        ));
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
                $bSortableVL = true;
            } else {
                // Not sortable, indicate this on the VL...
                $aOrder = array('', '');
                $bSortableVL = false;
                // 2013-07-03; 3.0-07; However, we do try and sort because in principle, the order is random and this may cause confusion while paginating.
                //   So, as a result we'll try and sort on the PK. We attempt to determine this from the GROUP BY or ID col in the VL columns list.
                $sCol = '';
                if (isset($this->aSQLViewList['GROUP_BY'])) {
                    $sCol = $this->aSQLViewList['GROUP_BY'];
                } elseif ($this->aColumnsViewList['id']) {
                    $sCol = $this->aColumnsViewList['id']['db'][0];
                } elseif ($this->aColumnsViewList['id_']) {
                    $sCol = $this->aColumnsViewList['id_']['db'][0];
                }
                $this->aSQLViewList['ORDER_BY'] = $sCol;
            }

            if (!$bHideNav && FORMAT == 'text/html') {
                // Implement LIMIT only if navigation is not hidden.
                // We have a problem here, because we don't know how many hits there are,
                // because we're using SQL_CALC_FOUND_ROWS which only gives us the number
                // of hits AFTER we run the whole query. This means we should just assume
                // the page number is possible.
                $this->aSQLViewList['LIMIT'] = lovd_pagesplitInit(); // Function requires variable names $_GET['page'] and $_GET['page_size'].
            }

            $sSQL = $this->buildSQL($this->aSQLViewList);

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

                // 'checked' attribute values for find & replace menu options.
                $sFRMatchtypeCheck1 = (!isset($sFRMatchType) || $sFRMatchType == '1')? 'checked' : '';
                $sFRMatchtypeCheck2 = ($sFRMatchType == '2')? 'checked' : '';
                $sFRMatchtypeCheck3 = ($sFRMatchType == '3')? 'checked' : '';
                $sFRReplaceAllCheck = $bFRReplaceAll? 'checked' : '';
                $sFRRowsAffected = (!is_null($nFRRowsAffected))? strval($nFRRowsAffected) : '';

                // Print options menu for find & replace (hidden by default).
                print(<<<FROptions
<DIV id="viewlistFRFormContainer_$sViewListID" class="optionsmenu" style="display: none;">
    <SPAN><B style="color: red">Note that find &amp; replace is still in BETA. Changes made using this feature are not checked for errors, therefore using find &amp; replace may have destructive consequences.<BR>Make a download or backup of the data you're about to edit. If uncertain, use the edit form of the data entries instead.</B><BR>
        Find &amp; replace for column
        <B id="viewlistFRColDisplay_$sViewListID">$sFRFieldname</B>
        <INPUT id="FRFieldname_$sViewListID" type="hidden" name="FRFieldname_$sViewListID"
               value="$sFRFieldname" />
        <INPUT id="FRFieldDisplayname_$sViewListID" type="hidden"
               name="FRFieldDisplayname_$sViewListID" value="$sFRFieldDisplayname" />
        <INPUT id="FRRowsAffected_$sViewListID" type="hidden" value="$sFRRowsAffected" />
    </SPAN>
    <BR />
    <TABLE>
        <TR>
            <TD>Text to find</TD>
            <TD>
                <INPUT type="text" name="FRSearch_$sViewListID" value="$sFRSearchValue"
                       style="width: 110px" />
            </TD>
            <TD>
                <INPUT type="radio" name="FRMatchType_$sViewListID" value="1" $sFRMatchtypeCheck1 />Match anywhere
                <INPUT type="radio" name="FRMatchType_$sViewListID" value="2" $sFRMatchtypeCheck2 />Match at beginning of field
                <INPUT type="radio" name="FRMatchType_$sViewListID" value="3" $sFRMatchtypeCheck3 />Match at end of field
            </TD>
        </TR>
        <TR>
            <TD>Replace with</TD>
            <TD>
                <INPUT type="text" name="FRReplace_$sViewListID" value="$sFRReplaceValue"
                       style="width: 110px" />
            </TD>
            <TD>
                <INPUT type="checkbox" name="FRReplaceAll_$sViewListID" value="1" $sFRReplaceAllCheck />Replace everything in field
            </TD>
        </TR>
    </TABLE>
    <INPUT id="FRPreview_$sViewListID" type="button" value="preview" />
    <INPUT id="FRCancel_$sViewListID" type="button" value="cancel" />
    <DIV id="FRSubmitDiv_$sViewListID">
        <BR>
        Enter your password to apply find and replace:<BR> 
        <INPUT type="password" name="password" size="20" />
        <INPUT id="FRSubmit_$sViewListID" type="submit" value="submit" />
    </DIV>
</DIV>
FROptions
                );

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
                    $nAllowFindAndReplace = (int) !empty($aCol['allowfnr']); // Later allow other columns as well, such as owned_by or statusid or so.
                    $sImg = '';
                    $sAlt = '';
                    if ($bSortable && $aOrder[0] == $sField) {
                        $sImg = ($aOrder[1] == 'DESC'? '_desc' : '_asc');
                        $sAlt = ($aOrder[1] == 'DESC'? 'Descending' : 'Ascending');
                    }
                    print("\n" . '          <TH valign="top"' . ($bSortable? ' class="order' . ($aOrder[0] == $sField? 'ed' : '') . '"' : '') . (empty($aCol['legend'][0])? '' : ' title="' . htmlspecialchars($aCol['legend'][0]) . '"') .
                                 ' data-allowfnr="' . $nAllowFindAndReplace . '" data-fieldname="' . $sField . '">' . "\n" .
                                 '            <IMG src="gfx/trans.png" alt="" width="' . $aCol['view'][1] . '" height="1" id="viewlistTable_' . $sViewListID . '_colwidth_' . $sField . '"><BR>' .
                            (!$bSortable? str_replace(' ', '&nbsp;', $aCol['view'][0]) . '<BR>' :
                                 "\n" .
                    // 2012-02-01; 3.0-beta-02; When resorting the ViewList, reset page to 1.
                                 '            <DIV onclick="document.forms[\'viewlistForm_' . $sViewListID . '\'].order.value=\'' . $sField . ',' . ($aOrder[0] == $sField? ($aOrder[1] == 'ASC'? 'DESC' : 'ASC') : $aCol['db'][1]) . '\'; if (document.forms[\'viewlistForm_' . $sViewListID . '\'].page) { document.forms[\'viewlistForm_' . $sViewListID . '\'].page.value=1; } lovd_AJAX_viewListSubmit(\'' . $sViewListID . '\');" style="position : relative;">' . "\n" .
                                 '              <IMG src="gfx/order_arrow' . $sImg . '.png" alt="' . $sAlt . '" title="' . $sAlt . '" width="13" height="12" style="position : absolute; top : 2px; right : 0px;">' . str_replace(' ', '&nbsp;', $aCol['view'][0]) . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</DIV>') .
                            (!$bSearchable? '' :
                                 "\n" .
                                 // SetTimeOut() is necessary because if the function gets executed right away, selecting a previously used value from a *browser-generated* list in one of the fields, gets aborted and it just sends whatever is typed in at that moment.
                                 '            <INPUT type="text" name="search_' . $sField . '" value="' . (!isset($_GET['search_' . $sField])? '' : htmlspecialchars($_GET['search_' . $sField])) . '" title="' . $aCol['view'][0] . ' field should contain...' . (!empty($_GET['search_' . $sField])? "\nCurrent search:\n\n" . htmlspecialchars(lovd_formatSearchExpression($_GET['search_' . $sField], $aColTypes[$sField])) : '') .'" style="width : ' . ($aCol['view'][1] - 6) . 'px; font-weight : normal;" onkeydown="if (event.keyCode == 13) { if (document.forms[\'viewlistForm_' . $sViewListID . '\'].page) { document.forms[\'viewlistForm_' . $sViewListID . '\'].page.value=1; } setTimeout(\'lovd_AJAX_viewListSubmit(\\\'' . $sViewListID . '\\\')\', 0); return false;}">') .
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

                $sFRMenuOption = '';
                if ($bFindReplace) {
                    // Add find & replace menu item to viewlist options menu.
                    $sFRMenuOption = <<<FRITEM
'            <LI class="icon">' +
'                <A click="lovd_FRColumnSelector(\'$sViewListID\');">' +
'                    <SPAN class="icon" style=""></SPAN>' +
'                    Find and replace text in column' +
'                </A>' +
'            </LI>' +
FRITEM;
                }

                print(<<<OPMENU
        // If menu's UL doesn't exist yet, create it.
        if ($('#viewlistMenu_$sViewListID').attr('id') == undefined) {
          var oUL = window.document.createElement('ul');
          oUL.setAttribute('id', 'viewlistMenu_$sViewListID');
          oUL.className = 'jeegoocontext jeegooviewlist';
          window.document.body.appendChild(oUL);
        }
        // Fix the top border that could not be set through jeegoo's style.css.
        $('#viewlistMenu_$sViewListID').attr('style', 'border-top : 1px solid #000;');
        $('#viewlistMenu_$sViewListID').prepend(
'            <LI class="icon">' +
'                <A click="check_list[\'$sViewListID\'] = \'all\'; lovd_AJAX_viewListSubmit(\'$sViewListID\');">' +
'                    <SPAN class="icon" style="background-image: url(gfx/check.png);"></SPAN>' +
'                    Select all <SPAN>entries</SPAN>' +
'                </A>' +
'            </LI>' +
'            <LI class="icon">' +
'                <A click="check_list[\'$sViewListID\'] = \'none\'; lovd_AJAX_viewListSubmit(\'$sViewListID\');">' +
'                    <SPAN class="icon" style="background-image: url(gfx/cross.png);"></SPAN>' +
'                    Unselect all' +
'                </A>' +
'            </LI>' +
$sFRMenuOption
'            ');
        $('#viewlistMenu_$sViewListID').append(
'            <LI class="icon">' +
'                <A click="lovd_AJAX_viewListSubmit(\'$sViewListID\', function(){lovd_AJAX_viewListDownload(\'$sViewListID\', true);});">' +
'                    <SPAN class="icon" style="background-image: url(gfx/menu_save.png);"></SPAN>' +
'                    Download all entries (summary data)' +
'                </A>' +
'            </LI>' +
'            <LI class="icon">' +
'                <A click="lovd_AJAX_viewListSubmit(\'$sViewListID\', function(){lovd_AJAX_viewListDownload(\'$sViewListID\', false);});">' +
'                    <SPAN class="icon" style="background-image: url(gfx/menu_save.png);"></SPAN>' +
'                    Download selected entries (summary data)' +
'                </A>' +
'            </LI>');
        lovd_activateMenu('$sViewListID');
OPMENU
);

            }
            print('        check_list[\'' . $sViewListID . '\'] = [];' . "\n" .
                  '      </SCRIPT>' . "\n\n");
        }

        return $nTotal;
    }
}
?>
