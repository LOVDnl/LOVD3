<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-02-16
 * Modified    : 2011-03-02
 * For LOVD    : 3.0-pre-18
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *
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
require_once ROOT_PATH . 'class/object_custom.php';





class LOVD_Patient extends LOVD_Custom {
    // This class extends the basic Object class and it handles the Link object.
    var $sObject = 'Patient';





    function LOVD_Patient ($nID = '')
    {
        

        // Default constructor.
        global $_AUTH;

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = 'SELECT p.*, uo.name AS owner FROM ' . TABLE_PATIENTS . ' AS p LEFT JOIN ' . TABLE_USERS . ' AS uo ON (p.ownerid = uo.id) WHERE p.id = ? GROUP BY p.id';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'p.*, uo.name AS owner, s.name AS status, uc.name AS created_by';
        $this->aSQLViewEntry['FROM']     = TABLE_PATIENTS . ' AS p LEFT JOIN ' . TABLE_USERS . ' AS uo ON (p.ownerid = uo.id) LEFT JOIN ' . TABLE_DATA_STATUS . ' AS s ON (p.statusid = s.id) LEFT JOIN ' . TABLE_USERS . ' AS uc ON (p.created_by = uc.id)';
//        $this->aSQLViewEntry['GROUP_BY'] = 'p.id';

        // SQL code for viewing the list of genes
        $this->aSQLViewList['SELECT']   = 'p.*, uo.name AS owner, s.name AS status';
        $this->aSQLViewList['FROM']     = TABLE_PATIENTS . ' AS p LEFT JOIN ' . TABLE_USERS . ' AS uo ON (p.ownerid = uo.id) LEFT JOIN ' . TABLE_DATA_STATUS . ' AS s ON (p.statusid = s.id)';
        //$this->aSQLViewList['GROUP_BY'] = 'p.id';

        
        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry =
                 array(
                        'TableHeader_General' => 'Patient ID (#' . $nID . ')',
                        'id' => 'Patient ID',
                        'owner' => 'Owner name',
                        'status' => 'Patient data status',
                        'created_by_' => 'Created by',
                        'created_date_' => 'Date created',
                        'edited_by_' => 'Last edited by',
                        'valid_from_' => 'Valid from',
                        'valid_to_' => 'Valid until',
                        'TableEnd_General' => '',
                        'HR_1' => '',
                        'TableStart_Additional' => '',
                        'TableHeader_Additional' => 'Additional information',
                        'TableEnd_Additional' => '',
                        'HR_2' => '',
                        'TableStart_Links' => '',
                        'TableHeader_Links' => 'Links to other resources',
                      );

        // Because the gene information is publicly available, remove some columns for the public.
        if ($_AUTH && $_AUTH['level'] < LEVEL_COLLABORATOR) {
            unset($this->aColumnsViewEntry['created_by_']);
            unset($this->aColumnsViewEntry['created_date_']);
            unset($this->aColumnsViewEntry['edited_by_']);
            unset($this->aColumnsViewEntry['valid_from_']);
            unset($this->aColumnsViewEntry['valid_to_']);
        }

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList =
                 array(
                        'id' => array(
                                    'view' => array('Patient ID', 70),
                                    'db'   => array('p.id', 'ASC', true)),
                        'owner' => array(
                                    'view' => array('Owner', 300),
                                    'db'   => array('uo.name', 'ASC', true)),
                        'status' => array(
                                    'view' => array('Status', 70),
                                    'db'   => array('s.name', false, true)),
                      );
        $this->sSortDefault = 'id';
        parent::LOVD_Custom();
        
    }





    function checkFields ($aData)
    {
        // STUB
        lovd_checkXSS();
    }





    function getForm ()
    {
        // STUB
        parent::getForm();
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
            $zData['row_link'] = 'patients/' . rawurlencode($zData['id']);
            //$zData['owner_link'] = 'users/' . rawurlencode($zData['owner']);
            $zData['id'] = '<A href="' . $zData['row_link'] . '" class="hide">' . $zData['id'] . '</A>';
            //$zData['owner'] = '<A href="' . $zData['owner_link'] . '" class="hide">' . $zData['owner'] . '</A>';
        } else {
            /*$zData['diseases_'] = $zData['disease_omim_'] = '';
            if (!empty($zData['diseases'])) {
                $aDiseases = explode(';;', $zData['diseases']);
                foreach ($aDiseases as $sDisease) {
                    list($nID, $nOMIMID, $sSymbol, $sName) = explode(';', $sDisease);
                    $zData['diseases_'] .= (!$zData['diseases_']? '' : ', ') . '<A href="diseases/' . $nID . '">' . $sSymbol . '</A>';
                    $zData['disease_omim_'] .= (!$zData['disease_omim_']? '' : '<BR>') . '<A href="' . lovd_getExternalSource('omim', $nOMIMID, true) . '" target="_blank">' . $sName . ' (' . $sSymbol . ')</A>';
                }
            }*/
            
            $zData['created_date_'] = substr($zData['created_date'], 0, 10);
            $zData['created_by_'] = (!empty($zData['created_by'])? $zData['created_by'] : 'N/A');
            $zData['valid_from_'] = (!empty($zData['valid_from'])? $zData['valid_from'] : 'N/A');
            $zData['valid_to_'] = (!empty($zData['valid_to'])? $zData['valid_to'] : 'N/A');
            $zData['edited_by_'] = (!empty($zData['edited_by'])? $zData['edited_by'] : 'N/A');
        }

        return $zData;
    }

    function setDefaultValues ()
    {
        // STUB
        return false;
    }
}
?>
