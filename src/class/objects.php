<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-21
 * Modified    : 2010-12-21
 * For LOVD    : 3.0-pre-11
 *
 * Copyright   : 2004-2010 Leiden University Medical Center; http://www.LUMC.nl/
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





class Object {
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
                    'ORDER_BY' => '',
                    'LIMIT' => '',
                  );
    var $aColumnsViewEntry = array();
    var $aColumnsViewList = array();
    var $sSortDefault = '';
    var $nCount = 0;





    function Object ()
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
    }





    function checkFields ($aData)
    {
        // Checks fields before submission of data.
        $aForm = $this->getForm();
        unset($aForm[0]);

        // Validate form by looking at the form itself, and check what's needed.
        foreach ($aForm as $key => $aField) {
            if (!is_array($aField)) {
                // 'skip', 'hr', etc...
                continue;
            }
            @list($sHeader, $sHelp, $sType, $sName) = $aField;

            // Mandatory fields, as defined by child object.
            if (in_array($sName, $this->aCheckMandatory) && empty($aData[$sName])) {
                lovd_errorAdd($sName, 'Please fill in the \'' . $sHeader . '\' field.');
            }

            // Checking free text fields for max length, data types, etc.
            if (in_array($sType, array('text', 'textarea')) && $sMySQLType = lovd_getColumnType(constant($this->sTable), $sName)) {
                // FIXME; we're assuming here, that $sName equals the database name. Which is true is probably most/every case, but even so...

                // Check max length.
                $nMaxLength = lovd_getColumnLength(constant($this->sTable), $sName);
                if (!empty($aData[$sName]) && strlen($aData[$sName]) > $nMaxLength) {
                    lovd_errorAdd($sName, 'The \'' . $sHeader . '\' field is limited to ' . $nMaxLength . ' characters, you entered ' . strlen($aData[$sName]) . '.');
                }

                // Check data type.
                switch ($sMySQLType) {
                    case 'DATE':
                        if (!lovd_matchDate($aData[$sName])) {
                            lovd_errorAdd($sName, 'The field \'' . $sHeader . '\' must contain a date.');
                        }
                        break;
                    case 'DATETIME':
                        if (!preg_match('/^[0-9]{4}[.\/-][0-9]{2}[.\/-][0-9]{2}( [0-9]{2}\:[0-9]{2}\:[0-9]{2})?$/', $aData[$sName])) {
                            lovd_errorAdd($sName, 'The field \'' . $sHeader . '\' must contain a date, possibly including a time.');
                        }
                        break;
                    case 'DEC':
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

            } elseif ($sType == 'select' && !empty($aField[7])) {
                // The browser fails to send value if selection list w/ multiple selection options is left empty.
                // This is causing notices in the code.
                // FIXME; is it also with selection lists with a size > 1? Then you should change the check above.
                if (!isset($aData[$sName])) {
                    $_POST[$sName] = array(); // Assuming we need $_POST here. FIXME; can be determined from $aForm[0].
                }

            } elseif ($sType == 'checkbox') {
                // The browser fails to send value if checkbox is left empty.
                // This is causing problems sometimes with MySQL, since INT
                // columns can't receive an empty string if STRICT is on.
                if (!isset($aData[$sName])) {
                    $_POST[$sName] = 0; // Assuming we need $_POST here. FIXME; can be determined from $aForm[0].
                }
            }
        }
    }





    function getCount ($nID = false)
    {
        // Returns the number of entries in the database table.
        if ($nID) {
            list($nCount) = mysql_fetch_row(lovd_queryDB('SELECT COUNT(*) FROM ' . constant($this->sTable) . ' WHERE id = ?', array($nID)));
        } else {
            if ($this->nCount) {
                return $this->nCount;
            }
            list($nCount) = mysql_fetch_row(lovd_queryDB('SELECT COUNT(*) FROM ' . constant($this->sTable)));
            $this->nCount = $nCount;
        }
        return $nCount;
    }





    function getForm ()
    {
        // Returns the $this->aFormData variable, to build a form.
        return $this->aFormData;
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
            $sSQL .= (!$key? '' : ', ') . $sField;
            $aSQL[] = $aData[$sField];
        }
        $sSQL .= ') VALUES (?' . str_repeat(', ?', count($aFields) - 1) . ')';

        $q = lovd_queryDB($sSQL, $aSQL);
        if (!$q) {
            lovd_queryError((defined(LOG_EVENT)? LOG_EVENT : $this->sObject . '::insertEntry()'), $sSQL, mysql_error());
        }

        $nID = mysql_insert_id();
        if (function_exists('lovd_getColumnType') && function_exists('lovd_getColumnLength')) { // Should be true when inc-lib-forms.php has been included.
            if (substr(lovd_getColumnType(constant($this->sTable), 'id'), 0, 3) == 'INT') {
                $nID = str_pad($nID, lovd_getColumnLength(constant($this->sTable), 'id'), '0', STR_PAD_LEFT);
            }
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
        $zData = @mysql_fetch_assoc(lovd_queryDB($sSQL, array($nID)));
        if (!$zData) {
            global $_CONF, $_SETT, $_STAT, $_AUTH;

            $sError = '';
            if (mysql_error()) {
                $sError = mysql_error(); // Save the mysql_error before it disappears.
            }

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
        return $zData;
    }





    function prepareData ($zData = '', $sView = 'list')
    {
        // Stub; this thing is just here to prevent an error message when a child class has not defined this function.
        if (!is_array($zData)) {
            $zData = array();
        }

        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Quote special characters, disallowing HTML and other trics.
        reset($zData);
        while (list($key, $val) = each($zData)) {
            $zData[$key] = htmlspecialchars($val);
        }

        return $zData;
    }





    function setDefaultValues ()
    {
        // Stub; this thing is just here to prevent an error message when a child class has not defined this function.
        return true;
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
            $sSQL .= (!$key? '' : ', ') . $sField . ' = ?';
            $aSQL[] = $aData[$sField];
        }
        $sSQL .= ' WHERE id = ?';
        $aSQL[] = $nID;

        $q = lovd_queryDB($sSQL, $aSQL);
        if (!$q) {
            lovd_queryError((defined(LOG_EVENT)? LOG_EVENT : $this->sObject . '::updateEntry()'), $sSQL, mysql_error());
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
        $this->aSQLViewEntry['WHERE'] = $sIDColumn . ' = ?' . (!$this->aSQLViewEntry['WHERE']? '' : ' ' . $this->aSQLViewEntry['WHERE']);
        $sSQL = 'SELECT ' . $this->aSQLViewEntry['SELECT'] .
               ' FROM ' . $this->aSQLViewEntry['FROM'] .
               ' WHERE ' . $this->aSQLViewEntry['WHERE'] .
              (!$this->aSQLViewEntry['GROUP_BY']? '' :
               ' GROUP BY ' . $this->aSQLViewEntry['GROUP_BY']);

        // Run the actual query.
        $zData = mysql_fetch_assoc(lovd_queryDB($sSQL, array($nID)));
        if (!$zData) {
            lovd_queryError((defined(LOG_EVENT)? LOG_EVENT : $this->sObject . '::viewEntry()'), $sSQL, mysql_error());
        }

        $zData = $this->prepareData($zData, 'entry');
        
        
        print('      <TABLE border="0" cellpadding="0" cellspacing="1" width="600" class="data">');
        // Print the data.
        foreach ($this->aColumnsViewEntry as $sField => $sHeader) {
            if (preg_match("/TableStart/", $sField)) {
                print('      <TABLE border="0" cellpadding="0" cellspacing="1" width="600" class="data">');
            } else if (preg_match("/TableHeader/", $sField)) {
                print('         <TH colspan="2" class="S15" valign="top">' . $sHeader . '</TH>');
            } else if (preg_match("/TableEnd/", $sField)) {
                print('</TABLE>' . "\n\n");
            } else if (preg_match("/HR/", $sField)) {
                print('<hr>');
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





    function viewList ($bOnlyRows = false)
    {
        // Views list of entries in the database, allowing search.

        require ROOT_PATH . 'inc-lib-viewlist.php';

        // First, check if entries are in the database at all.
        list($nTotal) = $this->getCount();
        if (!$nTotal) {
            $sMessage = 'No entries in the database yet!';
            if ($bOnlyRows) {
                die('0'); // Silent error.
            }
            lovd_showInfoTable($sMessage, 'stop');
            return true;
        }

        // SEARCH: Implement XSS check on search terms.
        foreach ($_GET as $key => $val) {
            if (!is_array($val) && $val !== strip_tags($val)) {
                $_GET[$key] = '';
            }
        }

        // SEARCH: Advanced text search.
        $sWHERE = '';
        $aArguments = array();
        foreach ($this->aColumnsViewList as $sColumn => $aCol) {
            if (!empty($aCol['db'][2]) && !empty($_GET['search_' . $sColumn]) && trim($_GET['search_' . $sColumn])) {
                // Allow for searches where the order of words is forced by enclosing the values with double quotes;
                // Replace spaces in sentences between double quotes so they don't get exploded.
                $sSearch = preg_replace_callback('/"([^"]+)"/', create_function('$aRegs', 'return str_replace(\' \', \'{{SPACE}}\', $aRegs[1]);'), trim($_GET['search_' . $sColumn]));
                $aWords = explode(' ', $sSearch);
                foreach ($aWords as $sWord) {
                    if ($sWord) {
                        if (substr_count($sWord, '|') && preg_match('/^[^|]+(\|[^|]+)+$/', $sWord)) {
                            // OR.
                            $aOR = explode('|', $sWord);
                            $sWHERE .= ($sWHERE? ' AND ' : '') . '(';
                            foreach ($aOR as $nTerm => $sTerm) {
                                // 2009-03-03; 2.0-17; Advanced searching did not allow to combine NOT and OR searches.
                                if (substr($sTerm, 0, 1) == '!') {
                                    // NOT.
                                    $sWHERE .= ($nTerm? ' OR ' : '') . $aCol['db'][0] . ' NOT LIKE ?';
                                    $aArguments[] = '%' . lovd_escapeSearchTerm(substr($sTerm, 1)) . '%';
                                } else {
                                    // Common search term.
                                    $sWHERE .= ($nTerm? ' OR ' : '') . $aCol['db'][0] . ' LIKE ?';
                                    $aArguments[] = '%' . lovd_escapeSearchTerm($sTerm) . '%';
                                }
                            }
                            $sWHERE .= ')';
                        } elseif (substr($sWord, 0, 1) == '!') {
                            // NOT.
                            $sWHERE .= ($sWHERE? ' AND ' : '') .  $aCol['db'][0] . ' NOT LIKE ?';
                            $aArguments[] = '%' . lovd_escapeSearchTerm(substr($sWord, 1)) . '%';
                        } else {
                            // Common search term.
                            $sWHERE .= ($sWHERE? ' AND ' : '') .  $aCol['db'][0] . ' LIKE ?';
                            $aArguments[] = '%' . lovd_escapeSearchTerm($sWord) . '%';
                        }
                    }
                }
            }
        }
        if ($sWHERE) {
            $this->aSQLViewList['WHERE'] .= ($this->aSQLViewList['WHERE']? ' AND ' : '') . $sWHERE;
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
        if (substr(lovd_getProjectFile(), 0, 6) != '/ajax/') {
            // Keep the URL clean; disable any fields that are not used.
            lovd_includeJS('inc-js-viewlist.php');
        
            // Print form; required for sorting and searching.
            // Because we don't want the form to submit itself while we are waiting for the Ajax response, we need to kill the native submit() functionality.
            print('      <FORM action="' . CURRENT_PATH . '" method="get" id="viewlist_form" style="margin : 0px;" onsubmit="return false;">' . "\n" .
                  '        <INPUT type="hidden" name="object" value="' . $this->sObject . '">' . "\n" .
// FIXME; do we ever use ACTION in a ViewList? Wait until we've made variants.php to know for sure.
                  (!ACTION? '' :
                  '        <INPUT type="hidden" name="' . ACTION . '" value="">' . "\n") .
                  '        <INPUT type="hidden" name="order" value="' . implode(',', $aOrder) . '">' . "\n\n");
        }

        // Manipulate SELECT to include SQL_CALC_FOUND_ROWS.
        $this->aSQLViewList['SELECT'] = 'SQL_CALC_FOUND_ROWS ' . $this->aSQLViewList['SELECT'];
        $sSQL = 'SELECT ' . $this->aSQLViewList['SELECT'] .
               ' FROM ' . $this->aSQLViewList['FROM'] .
              (!$this->aSQLViewList['WHERE']? '' :
               ' WHERE ' . $this->aSQLViewList['WHERE']) .
              (!$this->aSQLViewList['GROUP_BY']? '' :
               ' GROUP BY ' . $this->aSQLViewList['GROUP_BY']) .
               ' ORDER BY ' . $this->aSQLViewList['ORDER_BY'];

        // Implement LIMIT.
        // We have a problem here, because we don't know how many hits there are,
        // because we're using SQL_CALC_FOUND_ROWS which only gives us the number
        // of hits AFTER we run the whole query. This means we should just assume
        // the page number is possible.
        $sSQL .= ' LIMIT ' . lovd_pagesplitInit(); // Function requires variable names $_GET['page'] and $_GET['page_size'].

        // Using the SQL_CALC_FOUND_ROWS technique to find the amount of hits in one go.
        // There is talk about a possible race condition using this technique on the mysql_num_rows man page, but I could find no evidence of it's existence on InnoDB tables.
        // Just to be sure, I'm implementing a serializable transaction, which should lock the table between the two SELECT queries to ensure proper results.
        // Last checked 2010-01-25, by Ivo Fokkema.
        lovd_queryDB('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        lovd_queryDB('BEGIN TRANSACTION');

        // Run the actual query.
        $q = lovd_queryDB($sSQL, $aArguments);
        if (!$q) {
// FIXME; what if using AJAX? Probably we should generate a number here, indicating the system to try once more. If that fails also, the JS should throw a general error, maybe.
            lovd_queryError((defined(LOG_EVENT)? LOG_EVENT : $this->sObject . '::viewList()'), $sSQL, mysql_error());
        }

        // Now, get the total number of hits if no LIMIT was used. Note that $nTotal gets overwritten here.
        list($nTotal) = mysql_fetch_row(lovd_queryDB('SELECT FOUND_ROWS()'));
        lovd_queryDB('COMMIT'); // To end the transaction and the locks that come with it.

        // It is possible, when increasing the page size from a page > 1, that you're ending up in an invalid page with no results.
        // Catching this error, by redirecting from here. Only Ajax handles this correctly, because in normal requests inc-top.php already executed.
        // NOTE: if we ever decide to have a page_size change reset page to 1, we can drop this code.
        if (!mysql_num_rows($q) && $nTotal && !headers_sent()) {
            // No results retrieved, but there are definitely hits to this query. Limit was wrong!
            header('Location: ' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?' . preg_replace('/page=[^&]+/', 'page=1', $_SERVER['QUERY_STRING']));
            exit;
        }

        // Only print stuff if we're not just loading one entry right now.
        if (!$bOnlyRows) {
            print('      <DIV id="viewlist_div">' . "\n"); // These contents will be replaced by Ajax.

            lovd_pagesplitShowNav($nTotal);

            // Table and search headers (if applicable).
            print('      <TABLE border="0" cellpadding="0" cellspacing="1" class="data" id="viewlist_table">' . "\n" .
                  '        <THEAD>' . "\n" .
                  '        <TR>');

            foreach ($this->aColumnsViewList as $sField => $aCol) {
                $bSortable   = !empty($aCol['db'][1]);
                $bSearchable = !empty($aCol['db'][2]);
                $sImg = '';
                $sAlt = '';
                if ($bSortable && $aOrder[0] == $sField) {
                    $sImg = ($aOrder[1] == 'DESC'? '_desc' : '_asc');
                    $sAlt = ($aOrder[1] == 'DESC'? 'Descending' : 'Ascending');
                }
                print("\n" . '          <TH valign="top"' . (!empty($aCol['view'][2])? ' ' . $aCol['view'][2] : '') . ($bSortable? ' class="order' . ($aOrder[0] == $sField? 'ed' : '') . '"' : '') . '>' . "\n" .
                             '            <IMG src="gfx/trans.png" alt="" width="' . $aCol['view'][1] . '" height="1" id="viewlist_table_colwidth_' . $sField . '"><BR>' .
                        (!$bSortable? str_replace(' ', '&nbsp;', $aCol['view'][0]) . '<BR>' :
                             "\n" .
                             '            <DIV onclick="document.forms[\'viewlist_form\'].order.value=\'' . $sField . ',' . ($aOrder[0] == $sField? ($aOrder[1] == 'ASC'? 'DESC' : 'ASC') : $aCol['db'][1]) . '\';lovd_submitList();">' . "\n" .
                             '              <IMG src="gfx/order_arrow' . $sImg . '.png" alt="' . $sAlt . '" title="' . $sAlt . '" width="13" height="12" style="float : right; margin-top : 2px;">' . str_replace(' ', '&nbsp;', $aCol['view'][0]) . '</DIV>') .
                        (!$bSearchable? '' :
                             "\n" .
                             // SetTimeOut() is necessary because if the function gets executed right away, selecting a previously used value from a *browser-generated* list in one of the fields, gets aborted and it just sends whatever is typed in at that moment.
                             '            <INPUT type="text" name="search_' . $sField . '" value="' . (!isset($_GET['search_' . $sField])? '' : htmlspecialchars(stripslashes($_GET['search_' . $sField]))) . '" title="' . $aCol['view'][0] . ' field should contain..." style="width : ' . ($aCol['view'][1] - 6) . 'px; font-weight : normal;" onkeydown="if (event.keyCode == 13) { document.forms[\'viewlist_form\'].page.value=1; setTimeout(\'lovd_submitList()\', 0); }">') .
                      '</TH>');
            }
            print('</TR></THEAD>');
        }

        // If no results are found, quit here.
        if (!$nTotal) {
            // Searched, but no results.
            $sMessage = 'No results have been found that match your criteria.';
            if ($bOnlyRows) {
                die('0'); // Silent error.
            }
            print('</TABLE>' . "\n" .
                  '        <INPUT type="hidden" name="total" value="' . $nTotal . '" disabled>' . "\n" .
                  '        <INPUT type="hidden" name="page_size" value="' . $_GET['page_size'] . '">' . "\n" .
                  '        <INPUT type="hidden" name="page" value="' . $_GET['page'] . '">' . "\n" .
                  '      </FORM><BR>' . "\n\n");
            lovd_showInfoTable($sMessage, 'stop');
            return true;
        }

        while ($zData = mysql_fetch_assoc($q)) {
            $zData = $this->prepareData($zData);

            print("\n" .
                  '        <TR class="' . (empty($zData['class_name'])? 'data' : $zData['class_name']) . '"' . (empty($zData['row_id'])? '' : ' id="' . $zData['row_id'] . '"') . ' valign="top"' . (empty($zData['id'])? '' : ' style="cursor : pointer;"') . (empty($zData['row_link'])? '' : ' onclick="window.location.href = \'' . $zData['row_link'] . '\';"') . '>');
            foreach ($this->aColumnsViewList as $sField => $aCol) {
                print("\n" . '          <TD' . (!empty($aCol['view'][2])? ' ' . $aCol['view'][2] : '') . ($aOrder[0] == $sField? ' class="ordered"' : '') . '>' . ($zData[$sField] === ''? '-' : $zData[$sField]) . '</TD>');
            }
            print('</TR>');
        }

        // Only print stuff if we're not just loading one entry right now.
        if (!$bOnlyRows) {
            print('</TABLE>' . "\n" .
                  '        <INPUT type="hidden" name="total" value="' . $nTotal . '" disabled>' . "\n" .
                  '        <INPUT type="hidden" name="page_size" value="' . $_GET['page_size'] . '">' . "\n" .
                  '        <INPUT type="hidden" name="page" value="' . $_GET['page'] . '">' . "\n" .
                  '      </FORM>' . "\n\n");

            lovd_pagesplitShowNav($nTotal);
            print('      </DIV>' . "\n"); // These contents will be replaced by Ajax.
        }
        return true;
    }
}
?>
