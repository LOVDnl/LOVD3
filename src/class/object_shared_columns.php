<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-05-02
 * Modified    : 2012-09-24
 * For LOVD    : 3.0-beta-09
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
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
// Require parent class definition.
require_once ROOT_PATH . 'class/objects.php';
require_once ROOT_PATH . 'inc-lib-columns.php';





class LOVD_SharedColumn extends LOVD_Object {
    // This class extends the basic Object class and it handles the Column object.
    var $sObject = 'Shared_Column';
    var $sTable  = 'TABLE_SHARED_COLS';
    var $aTableInfo = array(); // Info about the type of custom column (VOT or Phenotype).





    function __construct ($sObjectID = '', $nID = '')
    {
        // Default constructor.
        // $nID is not really correct here, it's always $sID.
        global $_DB;

        if (!$sObjectID && !$nID) {
            lovd_displayError('ObjectError', 'SharedColumn::__construct() not called with valid Parent or Column ID.');
        }
        $this->sObjectID = $sObjectID; // ID of parent gene or disease.
        $this->nID = $nID; // ID of the column itself.
        if ($nID) {
            $sCategory = substr($nID, 0, strpos($nID . '/', '/')); // Isolate the category from the ID.
        } else {
            $sCategory = (ctype_digit($sObjectID)? 'Phenotype' : 'VariantOnTranscript');
        }
        $this->aTableInfo = lovd_getTableInfoByCategory($sCategory); // Gather info on the type of column.

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = 'SELECT sc.*, c.form_type ' .
                               'FROM ' . TABLE_SHARED_COLS . ' AS sc ' .
                                 'INNER JOIN ' . TABLE_COLS . ' AS c ON (sc.colid = c.id) ' .
                               'WHERE sc.colid = ? AND sc.' . $this->aTableInfo['unit'] . 'id = "' . $sObjectID . '"'; // Variable has been checked elsewhere, before this query is run.

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'sc.*, ' .
                                           'c.form_type, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_';
        $this->aSQLViewEntry['FROM']     = TABLE_SHARED_COLS . ' AS sc ' .
                                           'INNER JOIN ' . TABLE_COLS . ' AS c ON (sc.colid = c.id) ' .
                                           'LEFT JOIN ' . TABLE_USERS . ' AS uc ON (sc.created_by = uc.id) ' .
                                           'LEFT JOIN ' . TABLE_USERS . ' AS ue ON (sc.edited_by = ue.id)';
        $this->aSQLViewEntry['WHERE']    = 'sc.' . $this->aTableInfo['unit'] . 'id = "' . $sObjectID . '"'; // Variable has been checked elsewhere, before this query is run.

        // SQL code for viewing a list of entries.
        $this->aSQLViewList['SELECT']   = 'sc.*, ' .
                                          'SUBSTRING(sc.colid, LOCATE("/", sc.colid)+1) AS colid, ' .
                                          'c.id, ' .
                                          'c.head_column, ' .
                                          'c.form_type, ' .
                                          'u.name AS created_by_';
        $this->aSQLViewList['FROM']     = TABLE_SHARED_COLS . ' AS sc ' .
                                          'INNER JOIN ' . TABLE_COLS . ' AS c ON (sc.colid = c.id) ' .
                                          'LEFT JOIN ' . TABLE_USERS . ' AS u ON (sc.created_by = u.id)';
        // Now restrict viewList to only these related to this gene/disease.
        $this->aSQLViewList['WHERE']    = 'sc.' . $this->aTableInfo['unit'] . 'id = ' . $_DB->quote($sObjectID);
        $this->aSQLViewList['ORDER_BY'] = 'col_order, colid';



        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry =
                 array(
                        'colid' => 'Column ID',
                        'width' => 'Displayed width in pixels',
                        'mandatory_' => 'Mandatory',
                        'description_form' => 'Description on form',
                        'description_legend_short' => 'Description on short legend',
                        'description_legend_full' => 'Description on full legend',
                        'select_options' => 'Select options',
                        'public_view_' => 'Show to public',
                        'public_add_' => 'Show on submission form',
                        'created_by_' => 'Created by',
                        'created_date' => 'Date created',
                        'edited_by_' => 'Last edited by',
                        'edited_date' => 'Date last edited',
                      );

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList =
                 array(
                        'id' => array(
                                    'view' => false, // Only meant to let Objects::viewEntry() understand we have no id column.
                                    'db'   => array('sc.colid', 'ASC', true)),
                        'colid_' => array(
                                    'view' => array('ID', 175),
                                    'db'   => array('SUBSTRING(sc.colid, LOCATE("/", sc.colid)+1)', 'ASC', true)),
                        'head_column' => array(
                                    'view' => array('Heading', 150),
                                    'db'   => array('c.head_column', 'ASC', true)),
                        'width' => array(
                                    'view' => array('Width in px', 100),
                                    'db'   => array('sc.width', 'ASC', true)),
                        'mandatory_' => array(
                                    'view' => array('Mandatory', 60, 'style="text-align : center;"'),
                                    'db'   => array('sc.mandatory', 'DESC', true)),
                        'public_view_' => array(
                                    'view' => array('Public', 60, 'style="text-align : center;"'),
                                    'db'   => array('sc.public_view', 'DESC', true)),
                        'col_order' => array(
                                    'view' => array('Order&nbsp;', 60, 'style="text-align : right;"'),
                                    'db'   => array('sc.col_order', 'ASC')),
                        'form_type_' => array(
                                    'view' => array('Form type', 200)),
                        'created_by_' => array(
                                    'view' => array('Created by', 160),
                                    'db'   => array('u.name', 'DESC', true)),
                      );
        $this->sSortDefault = 'col_order';

        parent::__construct();
    }





    function checkFields ($aData, $zData = false)
    {
        // Checks fields before submission of data.

        // Mandatory fields.
        $this->aCheckMandatory =
                 array(
                        'description_legend_short',
                        'description_legend_full',
                        'width',
                      );

        parent::checkFields($aData);

        // Width can not be more than 3 digits.
        if (!empty($aData['width']) && $aData['width'] > 999) {
            $aData['width'] = 999;
            lovd_errorAdd('width', 'The width can not be more than 3 digits!');
        }

        // XSS attack prevention. Deny input of HTML.
        // Ignore the 'Description on short legend' and 'Description on full legend' fields.
        unset($aData['description_legend_short'], $aData['description_legend_full']);
        lovd_checkXSS($aData);
    }





    function getCount ($nID = false)
    {
        // Returns the number of entries in the database table.
        // Redefine here since we don't have an id column, and we also need to select on the parent object.
        // Note that $nID is actually wrong here, it's always $sID for us.
        global $_DB;

        if ($nID) {
            $nCount = $_DB->query('SELECT COUNT(*) FROM ' . constant($this->sTable) . ' WHERE ' . $this->aTableInfo['unit'] . 'id = ? AND colid = ?', array($this->sObjectID, $nID))->fetchColumn();
        } else {
            if ($this->nCount !== '') {
                return $this->nCount;
            }
            $nCount = $_DB->query('SELECT COUNT(*) FROM ' . constant($this->sTable) . ' WHERE ' . $this->aTableInfo['unit'] . 'id = ?', array($this->sObjectID))->fetchColumn();
            $this->nCount = $nCount;
        }
        return $nCount;
    }





    function getForm ()
    {
        // Build the form.

        // Array which will make up the form table.
        $this->aFormData =
                 array(
                        array('POST', '', '', '', '40%', '14', '60%'),
                        array('', '', 'print', '<B>Column descriptions</B>'),
                        'hr',
                        array('Description on short legend<BR>(HTML enabled)', '', 'textarea', 'description_legend_short', 50, 2),
                        array('Description on full legend<BR>(HTML enabled)', '', 'textarea', 'description_legend_full', 50, 3),
                        'hr',
                        'skip',
                        array('', '', 'print', '<B>Form settings</B>'),
                        array('Notes on form (optional)', '', 'textarea', 'description_form', 50, 2),
           'options' => array('List of possible options', '', 'textarea', 'select_options', 50, 5),
      'options_note' => array('', '', 'note', 'This is used to build the available options for the selection list.<BR>One option per line.<BR>If you want to use abbreviations, use: Abbreviation = Long name<BR>Example: &quot;DMD = Duchenne Muscular Dystrophy&quot;'),
                        'hr',
                        'skip',
                        array('', '', 'print', '<B>Column settings</B>'),
                        'hr',
             'width' => array('Column display width in pixels', '', 'text', 'width', 5),
                        array('', '', 'print', '<IMG src="gfx/trans.png" alt="" width="' . (int) $_POST['width'] . '" height="3" style="background : #000000;"><BR><SPAN class="form_note">(This is ' . (int) $_POST['width'] . ' pixels)</SPAN>'),
         'mandatory' => array('Mandatory field', '', 'checkbox', 'mandatory'),
       'public_view' => array('Show contents to public', '', 'checkbox', 'public_view'),
        'public_add' => array('Show field on submission form', '', 'checkbox', 'public_add'),
                        'hr',
                        'skip',
                        array('Enter your password for authorization', '', 'password', 'password', 20));

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

        if (!function_exists('lovd_describeFormType')) {
            require ROOT_PATH . 'inc-lib-columns.php';
        }

        if ($sView == 'list') {
            $zData['row_id']      = $zData['id'];
            $zData['row_link']    = CURRENT_PATH . '/' . $zData['colid']; // Note: I chose not to use rawurlencode() here!
            $zData['colid_'] = '<A href="' . $zData['row_link'] . '" class="hide">' . $zData['colid'] . '</A>';
            $zData['form_type_']  = lovd_describeFormType($zData);
        } else {
            // Remove unnecessary columns.
            if ($zData['edited_by'] == NULL) {
                // Never been edited.
                unset($this->aColumnsViewEntry['edited_by_'], $this->aColumnsViewEntry['edited_date']);
            }

            // Remove columns based on form type?
            $aFormType = explode('|', $zData['form_type']);
            if ($aFormType[2] != 'select') {
                unset($this->aColumnsViewEntry['select_options']);
            }

            $zData['description_legend_short'] = html_entity_decode(str_replace(array("\r", "\n"), ' ', $zData['description_legend_short']));
            $zData['description_legend_full'] = html_entity_decode(str_replace(array("\r", "\n"), ' ', $zData['description_legend_full']));
            $zData['public_add_']      = '<IMG src="gfx/mark_' . $zData['public_add'] . '.png" alt="" width="11" height="11">';
        }
        // FIXME; for titles use tooltips?
        $zData['mandatory_']   = '<IMG src="gfx/mark_' . $zData['mandatory'] . '.png" alt="" width="11" height="11">';
        $zData['public_view_'] = '<IMG src="gfx/mark_' . $zData['public_view'] . '.png" alt="" width="11" height="11">';

        return $zData;
    }





    function updateEntry ($sID, $aData, $aFields = array())
    {
        // Updates entry $sID with data from $aData in the database, changing only fields defined in $aFields.
        // Redefine here since we don't have an id column, and we also need to select on the parent object.
        global $_DB;

        if (!trim($sID)) {
            lovd_displayError('LOVD-Lib', $this->sObject . '::updateEntry() - Method didn\'t receive ID');
        } elseif (!is_array($aData) || !count($aData)) {
            lovd_displayError('LOVD-Lib', $this->sObject . '::updateEntry() - Method didn\'t receive data array');
        } elseif (!is_array($aFields) || !count($aFields)) {
            $aFields = array_keys($aData);
        }

        // Query text.
        $sSQL = 'UPDATE ' . constant($this->sTable) . ' SET ';
        $aSQL = array();
        foreach ($aFields as $key => $sField) {
            $sSQL .= (!$key? '' : ', ') . '`' . $sField . '` = ?';
            if ($aData[$sField] === '' && (substr(lovd_getColumnType(constant($this->sTable), $sField), 0, 3) == 'INT' || substr(lovd_getColumnType(constant($this->sTable), $sField), 0, 4) == 'DATE')) {
                $aData[$sField] = NULL;
            }
            $aSQL[] = $aData[$sField];
        }
        $sSQL .= ' WHERE ' . $this->aTableInfo['unit'] . 'id = ? AND colid = ?';
        $aSQL[] = $this->sObjectID;
        $aSQL[] = $sID;

        if (!defined('LOG_EVENT')) {
            define('LOG_EVENT', $this->sObject . '::updateEntry()');
        }
        $q = $_DB->query($sSQL, $aSQL, true, true);

        return $q->rowCount();
    }
}
?>
