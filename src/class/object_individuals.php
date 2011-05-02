<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-02-16
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





class LOVD_Individual extends LOVD_Custom {
    // This class extends the basic Object class and it handles the Link object.
    var $sObject = 'Individual';
    var $bShared = false;





    function LOVD_Individual ()
    {
        // Default constructor.
        global $_AUTH;

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = 'SELECT i.*, uo.name AS owner, ' .
                               'GROUP_CONCAT(DISTINCT i2d.diseaseid ORDER BY i2d.diseaseid SEPARATOR ";") AS active_diseases_ ' .
                               'FROM ' . TABLE_INDIVIDUALS . ' AS i ' .
                               'LEFT OUTER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (i.id = i2d.individualid) ' .
                               'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (i.ownerid = uo.id) ' .
                               'WHERE i.id = ? ' .
                               'GROUP BY i.id';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'i.*, ' .
                                           'GROUP_CONCAT(DISTINCT d.id) AS diseaseids, ' .
                                           'GROUP_CONCAT(DISTINCT d.id, ";", d.symbol, ";", d.name ORDER BY d.symbol SEPARATOR ";;") AS diseases, ' .
                                           'GROUP_CONCAT(DISTINCT p.id) AS phenotypeids, ' .
                                           'uo.id AS owner, ' .
                                           'uo.name AS owner_, ' .
                                           's.name AS status, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_';
        $this->aSQLViewEntry['FROM']     = TABLE_INDIVIDUALS . ' AS i ' .
                                           'LEFT OUTER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (i.id = i2d.individualid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (i2d.diseaseid = d.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_PHENOTYPES . ' AS p ON (i.id = p.individualid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (i.ownerid = uo.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS s ON (i.statusid = s.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (i.created_by = uc.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (i.edited_by = ue.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 'i.id';

        // SQL code for viewing the list of individuals
        $this->aSQLViewList['SELECT']   = 'i.*, ' .
                                          'i.id AS individualid, ' .
                                          'GROUP_CONCAT(DISTINCT d.id) AS diseaseids, ' .
                                          'GROUP_CONCAT(DISTINCT d.symbol ORDER BY d.symbol SEPARATOR ", ") AS diseases, ' .
                                          'uo.name AS owner, ' .
                                          's.name AS status';
        $this->aSQLViewList['FROM']     = TABLE_INDIVIDUALS . ' AS i ' .
                                          'LEFT OUTER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (i.id = i2d.individualid) ' .
                                          'LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (i2d.diseaseid = d.id) ' .
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
                        'individualid' => array(
                                    'view' => array('Individual ID', 110),
                                    'db'   => array('individualid', 'ASC', 'INT_UNSIGNED')),
                      ),
                 $this->buildViewList(),
                 array(
                        // FIXME; added this to fix bug selecting users for disease viewEntry, but this doesn't look pretty in the viewList.
                        'diseaseids' => array(
                                    'view' => array('Disease ID', 0),
                                    'db'   => array('diseaseids', false, true)),
                        'diseases' => array(
                                    'view' => array('Disease', 200),
                                    'db'   => array('diseases', false, true)),
                        'owner' => array(
                                    'view' => array('Owner', 300),
                                    'db'   => array('uo.name', 'ASC', true)),
                        'status' => array(
                                    'view' => array('Status', 70),
                                    'db'   => array('s.name', false, true)),
                      ));
        $this->sSortDefault = 'individualid';
    }





    function checkFields ($aData)
    {
        global $_AUTH;
        
        // Checks fields before submission of data.
        if (ACTION == 'edit') {
            global $zData; // FIXME; this could be done more elegantly.
        }
        
        // Mandatory fields.
        $this->aCheckMandatory =
                 array(

                      );
        parent::checkFields($aData);

        if (isset($_POST['ownerid'])) {
            if (!empty($_POST['ownerid']) && $_AUTH['level'] >= LEVEL_CURATOR) {
                $q = lovd_queryDB('SELECT * FROM ' . TABLE_USERS . ' WHERE id=?', array($_POST['ownerid']));
                if (!$q) {
                    lovd_errorAdd('ownerid' ,'Please select a proper owner from the \'Owner of this individual\' selection box.');
                }
            } elseif (empty($_POST['ownerid']) && $_AUTH['level'] >= LEVEL_CURATOR) {
                lovd_errorAdd('ownerid' ,'Please select a proper owner from the \'Owner of this individual\' selection box.');
            }
        } else {
            if (!empty($_POST['ownerid']) && $_AUTH['level'] < LEVEL_CURATOR) {
                lovd_errorAdd('ownerid' ,'Not allowed to change \'Owner of this individual\'.');
            }
        }
        
        $qDiseases = lovd_queryDB('SELECT GROUP_CONCAT(DISTINCT id) AS diseases FROM ' . TABLE_DISEASES, array());
        $aDiseases = mysql_fetch_row($qDiseases);
        $aDiseases = explode(',', $aDiseases[0]);
        if (isset($aData['active_diseases'])) {
            foreach ($aData['active_diseases'] as $sDisease) {
                if (!in_array($sDisease, $aDiseases)) {
                    if ($sDisease != 'None') {
                        lovd_errorAdd('active_diseases', 'Please select a proper disease in the \'This gene has been linked to these diseases\' selection box');
                    }
                }
            }
        }

        $aStatus = array('4', '7', '9');
        if (isset($_POST['statusid'])) {
            if (!in_array($_POST['statusid'], $aStatus) && $_AUTH['level'] >= LEVEL_CURATOR) {
                lovd_errorAdd('statusid' ,'Please select a proper status from the \'Status of this data\' selection box.');
            } elseif (empty($_POST['ownerid']) && $_AUTH['level'] >= LEVEL_CURATOR) {
                lovd_errorAdd('statusid' ,'Please select a proper status from the \'Status of this data\' selection box.');
            }
        } else {
            if (!empty($_POST['statusid']) && $_AUTH['level'] < LEVEL_CURATOR) {
                lovd_errorAdd('statusid' ,'Not allowed to change \'Status of this data\'.');
            }
        }
        
        lovd_checkXSS();
    }





    function getForm ()
    {
        // Build the form.
        global $_AUTH;
        
        // Get list of diseases
        $aDiseasesForm = array();
        $qData = lovd_queryDB('SELECT id, CONCAT(symbol, " (", name, ")") FROM ' . TABLE_DISEASES . ' ORDER BY id');
        $nData = mysql_num_rows($qData);
        if ($nData) {
            while ($r = mysql_fetch_row($qData)) {
                $aDiseasesForm[$r[0]] = $r[1];
            }
        } else {
            $aDiseasesForm = array('None' => 'No disease entries available');
        }
        
        $nFieldSize = (count($aDiseasesForm) < 20? count($aDiseasesForm) : 20);
        
        $aSelectOwner = array();
        $aSelectStatus = array(
                            '4' => 'Non public',
                            '7' => 'Marked',
                            '9' => 'Public',
                              );

        if ($_AUTH['level'] >= LEVEL_CURATOR) {
            $q = lovd_queryDB('SELECT * FROM ' . TABLE_USERS, array());
            while ($z = mysql_fetch_assoc($q)) {
                $aSelectOwner[$z['id']] = $z['name'];
            }
            $aFormOwner = array('Owner of this individual', '', 'select', 'ownerid', 1, $aSelectOwner, false, false, false);
            $aFormStatus = array('Status of this data', '', 'select', 'statusid', 1, $aSelectStatus, false, false, false);
        } else {
            $aFormOwner = array('Owner of this individual', '', 'print', '<B>' . $_AUTH['name'] . '</B>');
            $aFormStatus = array('Status of this data', '', 'print', '<B>Non public</B>');
        }

        // Array which will make up the form table.
        $this->aFormData = array_merge(
                 array(
                        array('POST', '', '', '', '50%', '14', '50%'),
                        array('', '', 'print', '<B>Individual information</B>'),
                        'hr',
                      ),
                 $this->buildViewForm(),
                 array(
                        'hr',
                        'skip',
                        array('', '', 'print', '<B>Relation to diseases</B>'),
                        'hr',
                        array('This individual has been diagnosed with these diseases', '', 'select', 'active_diseases', $nFieldSize, $aDiseasesForm, false, true, false),
                        'hr',
                        'skip',
                        array('', '', 'print', '<B>General information</B>'),
                        'hr',
                        $aFormOwner,
                        $aFormStatus,
                        'hr',
                        'skip',
                      ));

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
        global $_AUTH;
        
        $_POST['statusid'] = '9';
        $_POST['ownerid'] = $_AUTH['id'];
        $this->initDefaultValues();
    }
}
?>
