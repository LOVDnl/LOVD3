<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-04-19
 * Modified    : 2011-11-17
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
// Require parent class definition.
require_once ROOT_PATH . 'class/objects.php';





class LOVD_Link extends LOVD_Object {
    // This class extends the basic Object class and it handles the Link object.
    var $sObject = 'Link';





    function __construct ()
    {
        // Default constructor.

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = 'SELECT l.*, GROUP_CONCAT(c2l.colid ORDER BY c2l.colid SEPARATOR ";") AS active_columns_ FROM ' . TABLE_LINKS . ' AS l LEFT JOIN ' . TABLE_COLS2LINKS . ' AS c2l ON (l.id = c2l.linkid) WHERE l.id = ? GROUP BY l.id';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'l.*, COUNT(c2l.colid) AS active_columns, GROUP_CONCAT(c2l.colid ORDER BY c2l.colid SEPARATOR ", ") AS active_columns_, uc.name AS created_by_, ue.name AS edited_by_';
        $this->aSQLViewEntry['FROM']     = TABLE_LINKS . ' AS l LEFT JOIN ' . TABLE_COLS2LINKS . ' AS c2l ON (l.id = c2l.linkid) LEFT JOIN ' . TABLE_USERS . ' AS uc ON (l.created_by = uc.id) LEFT JOIN ' . TABLE_USERS . ' AS ue ON (l.edited_by = ue.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 'l.id';

        // SQL code for viewing a list of entries.
        $this->aSQLViewList['SELECT']   = 'l.*, COUNT(c2l.colid) AS active_columns';
        $this->aSQLViewList['FROM']     = TABLE_LINKS . ' AS l LEFT OUTER JOIN ' . TABLE_COLS2LINKS . ' AS c2l ON (l.id = c2l.linkid)';
        $this->aSQLViewList['GROUP_BY'] = 'l.id';
        $this->aSQLViewList['ORDER_BY'] = 'l.name ASC';

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry =
                 array(
                        'id' => 'Link ID',
                        'name' => 'Link name',
                        'pattern_text' => 'Pattern text',
                        'replace_text' => 'Replace text',
                        'description' => 'Description',
                        'active_columns' => '# active columns',
                        'active_columns_' => 'Active columns',
                        'created_by_' => 'Created by',
                        'created_date' => 'Date created',
                        'edited_by_' => 'Last edited by',
                        'edited_date' => 'Date last edited',
                      );

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList =
                 array(
                        'id' => array(
                                    'view' => array('ID', 45),
                                    'db'   => array('l.id', 'ASC', true)),
                        'name' => array(
                                    'view' => array('Name', 100),
                                    'db'   => array('l.name', 'ASC', true)),
                        'pattern_text' => array(
                                    'view' => array('Pattern', 130),
                                    'db'   => array('l.pattern_text', 'ASC', true)),
                        'replace_text' => array(
                                    'view' => array('Replacement', 630),
                                    'db'   => array('l.replace_text', 'ASC', true)),
                        'active_columns' => array(
                                    'view' => array('# cols', 60, 'style="text-align : right;"'),
                                    'db'   => array('active_columns', 'DESC', 'INT_UNSIGNED')),
                      );
        $this->sSortDefault = 'name';

        parent::__construct();
    }





    function checkFields ($aData)
    {
        // Checks fields before submission of data.
        if (ACTION == 'edit') {
            global $zData; // FIXME; this could be done more elegantly.
        }

        // Mandatory fields.
        $this->aCheckMandatory =
                 array(
                        'name',
                        'pattern_text',
                        'replace_text',
                        'description',
                      );
        parent::checkFields($aData);

        // Link name must be unique.
        if (!empty($aData['name'])) {
            // Enforced in the table, but we want to handle this gracefully.
            $sSQL = 'SELECT id FROM ' . TABLE_LINKS . ' WHERE name = ?';
            $aSQL = array($aData['name']);
            if (ACTION == 'edit') {
                $sSQL .= ' AND id != ?';
                $aSQL[] = $zData['id'];
            }
            if (mysql_num_rows(lovd_queryDB_Old($sSQL, $aSQL))) {
                lovd_errorAdd('name', 'There is already a custom link with this link name. Please choose another one.');
            }
        }

        if (!isset($aData['active_columns'])) {
            $_POST['active_columns'] = array();
        } elseif (!empty($aData['active_columns'])) {
            // Check if columns are text columns, since others cannot even hold the custom link's pattern text.
            // FIXME; eerst een group_concat, daarna een explode()? 
            $sSQL = 'SELECT GROUP_CONCAT(id) FROM ' . TABLE_COLS . ' WHERE mysql_type LIKE \'VARCHAR%\' OR mysql_type LIKE \'TEXT%\'';
            list($sColumns) = mysql_fetch_row(lovd_queryDB_Old($sSQL));
            $aColumns = explode(',', $sColumns);
            foreach($aData['active_columns'] as $sCol) {
                if (substr_count($sCol, '/') && !in_array($sCol, $aColumns)) {
                    // Columns without slashes are the category headers, that could be selected.
                    lovd_errorAdd('active_columns', 'Please select a valid custom column from the \'Active for columns\' selection box.');
                }
            }
        }

        // On the pattern text.
        if (!empty($aData['pattern_text'])) {
            // Pattern text must be unique.
            // Enforced in the table, but we want to handle this gracefully.
            $sSQL = 'SELECT id FROM ' . TABLE_LINKS . ' WHERE pattern_text = ?';
            $aSQL = array($aData['pattern_text']);
            if (ACTION == 'edit') {
                $sSQL .= ' AND id != ?';
                $aSQL[] = $zData['id'];
            }
            if (mysql_num_rows(lovd_queryDB_Old($sSQL, $aSQL))) {
                lovd_errorAdd('pattern_text', 'There is already a custom link with this pattern. Please choose another one.');

            } else {
                // Check the pattern of the pattern text.
                if (!preg_match('/^\{([A-Z0-9 :;,_-]|\[[0-9]\])+\}$/i', $aData['pattern_text'])) {
                    lovd_errorAdd('pattern_text', 'The link pattern is found to be incorrect. It must start with \'{\', end with \'}\' and can contain letters, numbers, spaces, some special characters (:;,_-) and references ([1] to [9]) and must be 3-25 characters long.');
                }

                // References shouldn't follow each other directly, because LOVD wouldn't know the separation character.
                if (preg_match('/(\[[0-9]\]){2,}/', $aData['pattern_text'])) {
                    lovd_errorAdd('pattern_text', 'The link pattern is found to be incorrect. Two or more references directly after each other must be separated by at least one character to keep the two apart.');
                }
            }

            // Check references in the pattern and replacement texts.
            if (!empty($aData['replace_text'])) {
                // Isolate reference numbers.
                $aPattern = explode(']', $aData['pattern_text']);
                $aPatternRefs = array();
                foreach ($aPattern as $val) {
                    if (substr_count($val, '[')) {
                        $aPatternRefs[] = substr(strrchr($val, '['), 1);
                    }
                }

                // Isolate reference numbers.
                $aReplace = explode(']', $aData['replace_text']);
                $aReplaceRefs = array();
                foreach ($aReplace as $val) {
                    if (substr_count($val, '[')) {
                        $aReplaceRefs[] = substr(strrchr($val, '['), 1);
                    }
                }

                // Check for reference order and/or references missing from the replacement text.
                reset($aPatternRefs);
                for ($i = 1; list(,$nRef) = each($aPatternRefs); $i ++) {
                    if ($nRef != $i) {
                        lovd_errorAdd('pattern_text', 'The link pattern is found to be incorrect. Expected reference [' . $i . '] ' . ($i == 1? 'first' : 'after [' . ($i - 1) . ']') . ', got [' . $nRef . '].');
                    }
                }

                foreach ($aReplaceRefs as $nRef) {
                    if (!in_array($nRef, $aPatternRefs)) {
                        lovd_errorAdd('replace_text', 'The link replacement text is found to be incorrect. Could not find used reference [' . $nRef . '] in link pattern.');
                    }
                }
            }
        }

        // NO XSS attack prevention, because the replacement NEEDS HTML.
        // lovd_checkXSS();
    }





    function getForm ()
    {
        // Build the form.

        // Get column list, to connect link to column.
        $aData = array();
        $sLastCategory = '';
        $qData = lovd_queryDB_Old('SELECT id, CONCAT(id, " (", head_column, ")") FROM ' . TABLE_COLS . ' WHERE mysql_type LIKE \'VARCHAR%\' OR mysql_type LIKE \'TEXT%\' ORDER BY id');
        $nData = mysql_num_rows($qData);
        $nFieldSize = ($nData < 20? $nData : 20);

        // Print active columns list ourselves, because we want to apply styling in the selection box.
        $sSelect = '<SELECT name="active_columns[]" size="' . $nFieldSize . '" multiple>';
        while ($r = mysql_fetch_row($qData)) {
            $sCategory = substr($r[0], 0, strpos($r[0], '/'));
            if ($sCategory != $sLastCategory) {
                // Weird trick; we need to work around the safety measures in lovd_viewForm() to do this;
                $aData[$sCategory . '" style="font-weight : bold; color : #FFFFFF; background : #224488; text-align : center;'] = ucfirst($sCategory) . ' columns';
                $sLastCategory = $sCategory;
                $aData[$r[0]] = $r[1];
            } else {
                // Implement the safety measures normally present in lovd_viewForm().
                $aData[htmlspecialchars($r[0])] = htmlspecialchars($r[1]);
            }
        }
        foreach ($aData as $key => $val) {
            $sSelect .= "\n" . 
                        '              <OPTION value="' . $key . '"' . (!empty($_POST['active_columns']) && in_array($key, $_POST['active_columns'])? ' selected' : '') . '>' . $val . '</OPTION>';
        }
        $sSelect .= '</SELECT>';

        // Array which will make up the form table.
        $this->aFormData =
                 array(
                        array('POST', '', '', '', '50%', '14', '50%'),
                        array('', '', 'print', '<B>Link details</B>'),
                        array('Link name', '', 'text', 'name', 30),
                        array('Pattern', '', 'text', 'pattern_text', 30),
                        array('', '', 'note', 'The pattern is bound to some rules:<UL style="margin : 0px; padding-left : 1.5em;"><LI>It must start with \'{\' and end with \'}\'.</LI><LI>It can contain letters, numbers, spaces, some special characters (:;,_-) and references ([1] to [9]).</LI><LI>It must be 3-25 characters long.</LI><LI>Two or more references directly after each other must be separated by at least one character to keep the two apart.</LI></UL>'),
                        array('Replacement text', '', 'textarea', 'replace_text', 40, 3),
                        array('', '', 'note', 'Make sure you use all references from the pattern in the replacement text.'),
                        array('Link description', 'To aid other users in using your custom link, please provide some information on what the link is for and how to use the references.', 'textarea', 'description', 40, 3),
                        'skip',
                        array('', '', 'print', '<B>Link settings</B>'),
                        array('Active for columns', '', 'print', $sSelect),
                        'skip',
     'authorization' => array('Enter your password for authorization', '', 'password', 'password', 20),
                  );

        if (ACTION != 'edit') {
            unset($this->aFormData['authorization']);
        }

        return parent::getForm();
    }





    function prepareData ($zData = '', $sView = 'list')
    {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.

        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        if ($sView == 'list') {
            $zData['row_id'] = $zData['id'];
            $zData['row_link'] = 'links/' . rawurlencode($zData['id']);
            $zData['name'] = '<A href="' . $zData['row_link'] . '" class="hide">' . $zData['name'] . '</A>';
            $zData['replace_text'] = lovd_shortenString($zData['replace_text'], 98);
        }

        return $zData;
    }
}
?>