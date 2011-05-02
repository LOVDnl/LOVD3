<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-03-18
 * Modified    : 2011-04-29
 * For LOVD    : 3.0-pre-20
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





class LOVD_Screening extends LOVD_Custom {
    // This class extends the basic Object class and it handles the Link object.
    var $sObject = 'Screening';
    var $bShared = false;





    function LOVD_Screening ()
    {
        // Default constructor.
        global $_AUTH;

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = 'SELECT s.*, ' .
        // FIXME; kijkend hiernaar realiseer ik me, dat ik misschien "ownerid" beter "owned_by" had kunnen noemen... Wat denk jij?
                               'uo.name AS owner ' .
                               'FROM ' . TABLE_SCREENINGS . ' AS s ' .
                               'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (s.ownerid = uo.id) ' .
                               'WHERE s.id = ? ' .
                               'GROUP BY s.id';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 's.*, ' .
        // FIXME; Waarom deze? Je hebt toch s.ownerid?
                                           'uo.id AS owner, ' .
        // FIXME; Note: wat hier "owner_" heet, heet in de loadEntry() en de viewList() nu "owner". Beter om dat consistent te houden.
                                           'uo.name AS owner_, ' .
                                           'uc.name AS created_by_';
        $this->aSQLViewEntry['FROM']     = TABLE_SCREENINGS . ' AS s ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (s.ownerid = uo.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (s.created_by = uc.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 's.id';

        // SQL code for viewing the list of screenings
        $this->aSQLViewList['SELECT']   = 's.*, ' .
                                          'uo.name AS owner';
        $this->aSQLViewList['FROM']     = TABLE_SCREENINGS . ' AS s ' .
                                          'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (s.ownerid = uo.id)';
        // FIXME; Deze GROUP BY is niet schadelijk, maar wel overbodig.
        $this->aSQLViewList['GROUP_BY'] = 's.id';

        parent::LOVD_Custom();
        
        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry = array_merge(
                 array(
                        'individualid_' => 'Individual ID',
                      ),
                 $this->buildViewEntry(),
                 array(
                        'owner_' => 'Owner name',
                        'created_by_' => 'Created by',
                        'created_date' => 'Date created',
                        'edited_by_' => 'Last edited by',
                        'valid_from_' => 'Date edited',
                      ));

        // Because the gene information is publicly available, remove some columns for the public.
        if (!$_AUTH || $_AUTH['level'] < LEVEL_COLLABORATOR) {
            // FIXME; Misschien een functie van maken? $this->unsetCol('created_by_', 'created_date_', 'edited_by_', 'edited_date_') o.i.d?
            unset($this->aColumnsViewEntry['created_by_']);
            unset($this->aColumnsViewEntry['created_date_']);
            unset($this->aColumnsViewEntry['edited_by_']);
            unset($this->aColumnsViewEntry['valid_from_']);
        }

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList = array_merge(
                 array(
                        'id' => array(
                                    'view' => array('Screening ID', 110),
                                    'db'   => array('s.id', 'ASC', true)),
                      ),
                 $this->buildViewList(),
                 array(
                        'individualid' => array(
                                    'view' => array('Individual ID', 110),
                                    'db'   => array('s.individualid', 'ASC', true)),
                        'owner' => array(
                                    'view' => array('Owner', 200),
                                    'db'   => array('uo.name', 'ASC', true)),
                        'created_date' => array(
                                    'view' => array('Date created', 130),
                                    'db'   => array('s.created_date', 'ASC', true)),
                        'valid_from' => array(
                                    'view' => array('Date edited', 130),
                                    'db'   => array('s.valid_from', 'ASC', true)),
                      ));
        $this->sSortDefault = 'id';
        parent::LOVD_Object();
        
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
            $zData['row_link'] = 'screenings/' . rawurlencode($zData['id']);
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
            
            // FIXME; ik bedenk me nu, dat deze aanpassingen zo klein zijn, dat ze ook in MySQL al gedaan kunnen worden. Wat denk jij?
            $zData['individualid_'] = '<A href="individuals/' . $zData['individualid'] . '">' . $zData['individualid'] . '</A>';
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
