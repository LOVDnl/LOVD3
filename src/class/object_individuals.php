<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-02-16
 * Modified    : 2011-04-08
 * For LOVD    : 3.0-pre-19
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





class LOVD_Individual extends LOVD_Custom {
    // This class extends the basic Object class and it handles the Link object.
    var $sObject = 'Individual';
    var $bShared = false;





    function LOVD_Individual ()
    {
        // Default constructor.
        global $_AUTH;

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = 'SELECT i.*, uo.name AS owner ' .
                               'FROM ' . TABLE_INDIVIDUALS . ' AS i ' .
                               'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (i.ownerid = uo.id) ' .
                               'WHERE i.id = ? ' .
                               'GROUP BY i.id';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'i.*, ' .
                                           'uo.id AS owner, ' .
                                           'uo.name AS owner_, ' .
                                           's.name AS status, ' .
                                           'uc.name AS created_by_';
        $this->aSQLViewEntry['FROM']     = TABLE_INDIVIDUALS . ' AS i ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (i.ownerid = uo.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS s ON (i.statusid = s.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (i.created_by = uc.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 'i.id';

        // SQL code for viewing the list of individuals
        $this->aSQLViewList['SELECT']   = 'i.*, ' .
                                          'uo.name AS owner, ' .
                                          's.name AS status';
        $this->aSQLViewList['FROM']     = TABLE_INDIVIDUALS . ' AS i ' .
                                          'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (i.ownerid = uo.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS s ON (i.statusid = s.id)';
        $this->aSQLViewList['GROUP_BY'] = 'i.id';
        
        parent::LOVD_Custom();
        
        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry = array_merge(
                 $this->buildViewEntry(),
                 array(
                        'owner_' => 'Owner name',
                        'status' => 'Individual data status',
                        'created_by_' => 'Created by',
                        'created_date_' => 'Date created',
                        'edited_by_' => 'Last edited by',
                        'valid_from_' => 'Date edited',
                      ));

        // Because the gene information is publicly available, remove some columns for the public.
        if (!$_AUTH || $_AUTH['level'] < LEVEL_COLLABORATOR) {
            unset($this->aColumnsViewEntry['created_by_']);
            unset($this->aColumnsViewEntry['created_date_']);
            unset($this->aColumnsViewEntry['edited_by_']);
            unset($this->aColumnsViewEntry['valid_from_']);
        }

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList = array_merge(
                 array(
                        'id' => array(
                                    'view' => array('Individual ID', 90),
                                    'db'   => array('i.id', 'ASC', true)),
                      ),
                 $this->buildViewList(),
                 array(
                        'owner' => array(
                                    'view' => array('Owner', 300),
                                    'db'   => array('uo.name', 'ASC', true)),
                        'status' => array(
                                    'view' => array('Status', 70),
                                    'db'   => array('s.name', false, true)),
                      ));
        $this->sSortDefault = 'id';
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
            $zData['row_link'] = 'individuals/' . rawurlencode($zData['id']);
            $zData['id'] = '<A href="' . $zData['row_link'] . '" class="hide">' . $zData['id'] . '</A>';
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
            
            $zData['owner_'] = '<A href="users/' . $zData['owner'] . '">' . $zData['owner_'] . '</A>';
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
