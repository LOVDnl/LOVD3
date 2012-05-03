<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-05-02
 * Modified    : 2012-05-03
 * For LOVD    : 3.0-beta-05
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





class LOVD_SharedColumn extends LOVD_Object {
    // This class extends the basic Object class and it handles the Column object.
    var $sObject = 'Shared_Column';
    var $sTable  = 'TABLE_SHARED_COLS';





    function __construct ($sObjectID = '', $nID = '')
    {
        // Default constructor.
        global $_DB;

        // SQL code for viewing a list of entries.
        $this->aSQLViewList['SELECT']   = 's.*, ' .
                                          'SUBSTRING(s.colid, LOCATE("/", s.colid)+1) AS colid, ' .
                                          'c.id, ' .
                                          'c.head_column, ' .
                                          'c.form_type, ' .
                                          'u.name AS created_by_';
        $this->aSQLViewList['FROM']     = TABLE_SHARED_COLS . ' AS s ' .
                                          'INNER JOIN ' . TABLE_COLS . ' AS c ON (s.colid = c.id) ' .
                                          'LEFT JOIN ' . TABLE_USERS . ' AS u ON (s.created_by = u.id)';
        // Now restrict viewList to only these related to this gene/disease.
        $this->aSQLViewList['WHERE']    = 's.' . (ctype_digit($sObjectID)? 'diseaseid' : 'geneid') . ' = ' . $_DB->quote($sObjectID);
        $this->aSQLViewList['ORDER_BY'] = 'col_order, colid';



        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList =
                 array(
                        'colid_' => array(
                                    'view' => array('ID', 175),
                                    'db'   => array('SUBSTRING(s.colid, LOCATE("/", s.colid)+1)', 'ASC', true)),
                        'head_column' => array(
                                    'view' => array('Heading', 150),
                                    'db'   => array('c.head_column', 'ASC', true)),
                        'mandatory_' => array(
                                    'view' => array('Mandatory', 60, 'style="text-align : center;"'),
                                    'db'   => array('s.mandatory', 'DESC', true)),
                        'public_view_' => array(
                                    'view' => array('Public', 60, 'style="text-align : center;"'),
                                    'db'   => array('s.public_view', 'DESC', true)),
                        'col_order' => array(
                                    'view' => array('Order&nbsp;', 60, 'style="text-align : right;"'),
                                    'db'   => array('s.col_order', 'ASC')),
                        'form_type_' => array(
                                    'view' => array('Form type', 200)),
                        'created_by_' => array(
                                    'view' => array('Created by', 160),
                                    'db'   => array('u.name', 'DESC', true)),
                      );
        $this->sSortDefault = 'col_order';

        $this->sObjectID = $sObjectID;
        $this->nID = $nID;

        parent::__construct();
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

////////////////////////////// STILL NEEDS REVIEW /////////////////////////////////////// // DMD_SPECIFIC
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
            $zData['description_legend_full'] = html_entity_decode($zData['description_legend_full']);
            $zData['form_type_']       = lovd_describeFormType($zData) . '<BR>' . $zData['form_type'];
            $zData['public_add_']      = '<IMG src="gfx/mark_' . $zData['public_add'] . '.png" alt="" width="11" height="11">';
            $zData['allow_count_all_'] = '<IMG src="gfx/mark_' . $zData['allow_count_all'] . '.png" alt="" width="11" height="11">';
        }
        // FIXME; for titles use tooltips?
        $zData['mandatory_']   = '<IMG src="gfx/mark_' . $zData['mandatory'] . '.png" alt="" width="11" height="11">';
        $zData['public_view_'] = '<IMG src="gfx/mark_' . $zData['public_view'] . '.png" alt="" width="11" height="11">';

        return $zData;
    }
}
?>
