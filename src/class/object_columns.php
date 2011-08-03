<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-03-04
 * Modified    : 2011-08-03
 * For LOVD    : 3.0-alpha-03
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





class LOVD_Column extends LOVD_Object {
    // This class extends the basic Object class and it handles the Column object.
    var $sObject = 'Column';
    var $sTable  = 'TABLE_COLS';





    function LOVD_Column ()
    {
        // Default constructor.

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = 'SELECT c.*, SUBSTRING_INDEX(c.id, "/", 1) AS category, SUBSTRING(c.id, LOCATE("/", c.id)+1) AS colid, (a.created_by > 0) AS active FROM ' . TABLE_COLS . ' AS c LEFT JOIN ' . TABLE_ACTIVE_COLS . ' AS a ON (c.id = a.colid) WHERE c.id = ?';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'c.*, SUBSTRING_INDEX(c.id, "/", 1) AS category, SUBSTRING(c.id, LOCATE("/", c.id)+1) AS colid, (a.created_by > 0) AS active, uc.name AS created_by_, ue.name AS edited_by_';
        $this->aSQLViewEntry['FROM']     = TABLE_COLS . ' AS c LEFT JOIN ' . TABLE_ACTIVE_COLS . ' AS a ON (c.id = a.colid) LEFT JOIN ' . TABLE_USERS . ' AS uc ON (c.created_by = uc.id) LEFT JOIN ' . TABLE_USERS . ' AS ue ON (c.edited_by = ue.id)';

        // SQL code for viewing a list of entries.
        $this->aSQLViewList['SELECT']   = 'c.*, SUBSTRING_INDEX(c.id, "/", 1) AS category, SUBSTRING(c.id, LOCATE("/", c.id)+1) AS colid, IFNULL((a.created_by > 0), 0) AS active, u.name AS created_by_';
        $this->aSQLViewList['FROM']     = TABLE_COLS . ' AS c LEFT JOIN ' . TABLE_ACTIVE_COLS . ' AS a ON (c.id = a.colid) LEFT JOIN ' . TABLE_USERS . ' AS u ON (c.created_by = u.id)';
        $this->aSQLViewList['ORDER_BY'] = 'category, colid';

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry =
                 array(
                        'category' => 'Data category',
                        'colid' => 'Column ID',
                        'head_column' => '(Default) column heading',
                        'active_' => 'Active in LOVD?',
                        'hgvs_' => 'HGVS column',
                        'standard_' => 'Standard',
                        'mandatory_' => 'Mandatory',
                        'description_form' => 'Description on form',
                        'description_legend_short' => 'Description on short legend',
                        'description_legend_full' => 'Description on full legend',
                        'mysql_type' => 'Database type',
                        'form_type_' => 'Form type',
                        'select_options' => 'Select options',
                        'preg_pattern' => 'Regular expression pattern',
                        'public_view_' => 'Show to public',
                        'public_add_' => 'Show on submission form',
                        'allow_count_all_' => 'Include in search form',
                        'created_by_' => 'Created by',
                        'created_date' => 'Date created',
                        'edited_by_' => 'Last edited by',
                        'edited_date' => 'Date last edited',
                      );

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList =
                 array(
                        'category' => array(
                                    'view' => array('Category', 120),
                                    'db'   => array('SUBSTRING_INDEX(c.id, "/", 1)', 'ASC', true)),
                        'colid_' => array(
                                    'view' => array('ID', 175),
                                    'db'   => array('SUBSTRING(c.id, LOCATE("/", c.id)+1)', 'ASC', true)),
                        'head_column' => array(
                                    'view' => array('(Default) heading', 150),
                                    'db'   => array('c.head_column', 'ASC', true)),
                        'active_' => array(
                                    'view' => array('Active', 60, 'align="center"'),
                                    'db'   => array('IFNULL((a.created_by > 0), 0)', 'DESC', true)),
                        'hgvs_' => array(
                                    'view' => array('HGVS', 50, 'align="center"'),
                                    'db'   => array('c.hgvs', 'DESC', true)),
                        'standard_' => array(
                                    'view' => array('Standard', 80, 'align="center"'),
                                    'db'   => array('c.standard', 'DESC', true)),
                        'public_view_' => array(
                                    'view' => array('Public', 60, 'align="center"'),
                                    'db'   => array('c.public_view', 'DESC', true)),
                        'col_order' => array(
                                    'view' => array('Order&nbsp;', 60, 'align="right"'),
                                    'db'   => array('SUBSTRING_INDEX(c.id, "/", 1), col_order', 'ASC')),
                        'form_type_' => array(
                                    'view' => array('Form type', 200)),
                        'created_by_' => array(
                                    'view' => array('Created by', 160),
                                    'db'   => array('u.name', 'DESC', true)),
                      );
        $this->sSortDefault = 'category';

        parent::LOVD_Object();
    }





    function checkFields ($aData)
    {
        // Checks fields before submission of data.
        global $_AUTH;

        // Mandatory fields.
        $this->aCheckMandatory =
                 array(
                        'category',
                        'colid',
                        'head_column',
                        'description_legend_short',
                        'description_legend_full',
                        'mysql_type',
                        'form_type',
                        'password',
                      );
        parent::checkFields($aData);

        // ColID format.
        if ($aData['colid'] && !preg_match('/^[A-Za-z0-9_]+(\/[A-Za-z0-9_]+)*$/', $aData['colid'])) {
            lovd_errorAdd('colid', 'The column ID is not of the correct format. It can contain only letters, numbers and underscores. Subcategories must be devided by a slash (/).');
        }

        // ColID must not exist in the database.
        if (!empty($aData['category']) && !empty($aData['colid'])) {
            list($n) = mysql_fetch_row(lovd_queryDB('SELECT COUNT(*) FROM ' . TABLE_COLS . ' WHERE id = ?', array($aData['category'] . '/' . $aData['colid'])));
            if ($n) {
                lovd_errorAdd('colid', 'There is already a ' . $aData['category'] . ' column with this column ID. Please verify that you\'re not trying to create a column that already exists!');
            }
        }

        // MySQL type format.
        if ($aData['mysql_type'] && !preg_match('/^(TEXT|VARCHAR\([0-9]{1,3}\)|DATE(TIME)?|((TINY|SMALL|MEDIUM|BIG)?INT\([0-9]{1,2}\)|DECIMAL\([0-9]{1,2}\,[0-9]{1,2}\))( UNSIGNED)?)( DEFAULT ([0-9]+|"[^"]+"))?$/i', $aData['mysql_type'])) {
            lovd_errorAdd('mysql_type', 'The MySQL data type is not recognized. Please use the data type wizard to generate a proper MySQL data type.');
        }

        // FIXME; are we just assuming that form_format is OK?

        // XSS attack prevention. Deny input of HTML.
        lovd_checkXSS();
    }





    function getForm ()
    {
        // Build the form.
//        global $_AUTH, $_SETT, $_PATH_ELEMENTS;
        global $_PATH_ELEMENTS;

        // Get links list, to connect column to link.
        $aLinks = array();
        $qLinks = lovd_queryDB('SELECT id, name FROM ' . TABLE_LINKS . ' ORDER BY name');
        $nLinks = mysql_num_rows($qLinks);
        $nLinkSize = ($nLinks < 10? $nLinks : 10);
        while ($r = mysql_fetch_row($qLinks)) {
            $aLinks[$r[0]] = $r[1];
        }

        // Array which will make up the form table.
        $this->aFormData =
                 array(
                        array('POST', '', '', '', '50%', '14', '50%'),
                        array('', '', 'print', '<B>Column name and descriptions</B>'),
//          'category' => array('Category', '', 'select', 'category', 1, $aCategories, true, false, false),
          'category' => array('', '', 'print', '<I>Selected category: ' . $_POST['category'] . '</I>'),
             'colid' => array('Column ID', '', 'text', 'colid', 30),
        'colid_note' => array('', '', 'note', 'This ID must be unique and may contain only letters, numbers and underscores. Subcategories must be divided by a slash (/), such as \'{{ EXAMPLE }}\'.'),
                        array('Column heading', 'This will appear above the column in data tables.', 'text', 'head_column', 30),
                        array('Description on short legend', '', 'textarea', 'description_legend_short', 40, 2),
                        array('Description on full legend', '', 'textarea', 'description_legend_full', 40, 2),
                        'skip',
                        array('', '', 'print', '<B>Data and form settings</B> (Use data type wizard to change values)'),
                        array('', '', 'print', '<BUTTON type="button" onclick="javascript:lovd_openWindow(\'' . $_PATH_ELEMENTS[0] . '?data_type_wizard\', \'DataTypeWizard\', 800, 400); return false;">Start data type wizard</BUTTON>'),
                        array('MySQL data type', '<B>Experts only!</B> Only change this field manually when you know what you\'re doing! Otherwise, use the data type wizard by clicking the button above this field.', 'text', 'mysql_type', 30),
                        array('Form type', '<B>Experts only!</B> Only change this field manually when you know what you\'re doing! Otherwise, use the data type wizard by clicking the button above the MySQL data type field.', 'text', 'form_type', 30),
                        'skip',
                        array('', '', 'print', '<B>Column settings</B>'),
     'settings_note' => array('', '', 'print', '<I>Please note that fields marked with * are merely default values. For each configured {{ DATATYPE }}, these values may be changed at any later time.</I>'),
          'standard' => array('Include this column for newly configured ', '', 'checkbox', 'standard'),
             'width' => array('Column display width in pixels', '', 'text', 'width', 5),
                        array('', '', 'print', '<IMG src="gfx/trans.png" alt="" width="' . (int) $_POST['width'] . '" height="3" style="background : #000000;"><BR><SPAN class="form_note">(This is ' . (int) $_POST['width'] . ' pixels)</SPAN>'),
         'mandatory' => array('Mandatory field', '', 'checkbox', 'mandatory'),
       'public_view' => array('Show contents to public', '', 'checkbox', 'public_view'),
        'public_add' => array('Show field on submission form', '', 'checkbox', 'public_add'),
// DMD_SPECIFIC; implement this later.
//                        array('Include in "hidden entries" search form', '', 'checkbox', 'allow_count_all'),
//                        array('', '', 'print', '<SPAN class="form_note">Selecting this checkbox allows the public to find the number of entries in the database (including hidden entries) matching one or more search terms on this column.</SPAN>'),
                        'skip',
                        array('', '', 'print', '<B>Link settings</B>'),
                        array('Active custom links', '', 'select', 'active_links', $nLinkSize, $aLinks, false, true, true),
                        'skip',
                        array('Enter your password for authorization', '', 'password', 'password', 20));

        // Change some text on the form.
        switch ($_POST['category']) {
            case 'Individual':
                unset($this->aFormData['settings_note']);
                unset($this->aFormData['standard']);
                $this->aFormData['colid_note'][3] = str_replace('{{ EXAMPLE }}', 'Geograpic_origin/Country', $this->aFormData['colid_note'][3]);
                break;
            case 'Phenotype':
                $this->aFormData['settings_note'][3] = str_replace('{{ DATATYPE }}', 'disease', $this->aFormData['settings_note'][3]);
                $this->aFormData['standard'][0] .= 'diseases';
                $this->aFormData['colid_note'][3] = str_replace('{{ EXAMPLE }}', 'Blood_pressure/Systolic', $this->aFormData['colid_note'][3]);
                $this->aFormData['width'][0] .= ' *';
                $this->aFormData['mandatory'][0] .= ' *';
                $this->aFormData['public_view'][0] .= ' *';
                break;
            case 'Screening':
                unset($this->aFormData['settings_note']);
                unset($this->aFormData['standard']);
                $this->aFormData['colid_note'][3] = str_replace('{{ EXAMPLE }}', 'Protocol/Date_updated', $this->aFormData['colid_note'][3]);
                break;
            case 'VariantOnGenome':
                unset($this->aFormData['settings_note']);
                unset($this->aFormData['standard']);
                $this->aFormData['colid_note'][3] = str_replace('{{ EXAMPLE }}', 'Frequency/dbSNP', $this->aFormData['colid_note'][3]); // FIXME; I think this example sucks.
                break;
            case 'VariantOnTranscript':
                $this->aFormData['settings_note'][3] = str_replace('{{ DATATYPE }}', 'gene', $this->aFormData['settings_note'][3]);
                $this->aFormData['standard'][0] .= 'genes';
                $this->aFormData['colid_note'][3] = str_replace('{{ EXAMPLE }}', 'Protein/Codon', $this->aFormData['colid_note'][3]);
                $this->aFormData['width'][0] .= ' *';
                $this->aFormData['mandatory'][0] .= ' *';
                $this->aFormData['public_view'][0] .= ' *';
                break;
        }
/*
// Het hele formulier moet anders met het editen... het display gedeelte moet apart denk ik - "edit display settings"; variant en phenotype cols hebben "set defaults for new genes/diseases", alle hebben "edit data types" ofzo.
        if (ACTION == 'edit') {
        }
*/

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
            $zData['row_link']    = 'columns/' . $zData['id']; // Note: I chose not to use rawurlencode() here!
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
            } else {
                unset($this->aColumnsViewEntry['preg_pattern']);
            }

            $zData['mandatory_']       = '<IMG src="gfx/mark_' . $zData['mandatory'] . '.png" alt="" width="11" height="11">';
            $zData['form_type_']       = lovd_describeFormType($zData) . '<BR>' . $zData['form_type'];
            $zData['public_add_']      = '<IMG src="gfx/mark_' . $zData['public_add'] . '.png" alt="" width="11" height="11">';
            $zData['allow_count_all_'] = '<IMG src="gfx/mark_' . $zData['allow_count_all'] . '.png" alt="" width="11" height="11">';
        }
        $zData['active_']      = '<IMG src="gfx/mark_' . (int) $zData['active'] . '.png" alt="" width="11" height="11">';
        $zData['hgvs_']        = '<IMG src="gfx/mark_' . $zData['hgvs'] . '.png" alt="" width="11" height="11">';
        $zData['standard_']    = '<IMG src="gfx/mark_' . $zData['standard'] . '.png" alt="" width="11" height="11">';
        $zData['public_view_'] = '<IMG src="gfx/mark_' . $zData['public_view'] . '.png" alt="" width="11" height="11">';
        $zData['created_by_']  = ($zData['created_by_']? $zData['created_by_'] : 'LOVD');

        return $zData;
    }





    function setDefaultValues ()
    {
        // Sets default values of fields in $_POST.
        $_POST['width'] = '200';
        $_POST['public_view'] = 1;
        $_POST['public_add'] = 1;
        $_POST['allow_count_all'] = 1;
        $_POST['description_form'] = '';
        $_POST['select_options'] = '';
        $_POST['preg_pattern'] = '';

        // Default data type information, loaded in SESSION.
        $_SESSION['form_type'] =
                 array(
                        'form_type' => '',
                        'name' => '',
                        'size' => '',
                        'maxlength' => '',
                        'scale' => '',
                        'preg_pattern' => '',
                        'unsigned' => '',
                        'default_val' => '',
                        'cols' => '',
                        'rows' => '',
                        'select' => '',
                        'select_options' => '',
                        'select_all' => '',
                      );
        return true;
    }
}
?>
