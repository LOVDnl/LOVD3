<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-02-16
 * Modified    : 2011-08-04
 * For LOVD    : 3.0-alpha-04
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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
                                           // FIXME; TABLE_PHENOTYPES heeft een individual ID, dus je kunt een gewone count(*) opvragen, je hebt geen lijst phenotype IDs nodig.
                                           'GROUP_CONCAT(DISTINCT p.id, ";", p.diseaseid SEPARATOR ";;") AS phenotypes, ' .
                                           // FIXME; een niet-standaard separator is misschien niet zo handig voor de standaardisatie.
                                           'GROUP_CONCAT(DISTINCT s.id SEPARATOR "|") AS screeningids, ' .
                                           'uo.id AS owner, ' .
                                           'uo.name AS owner_, ' .
                                           'ds.name AS status, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_';
        $this->aSQLViewEntry['FROM']     = TABLE_INDIVIDUALS . ' AS i ' .
                                           'LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s ON (i.id = s.individualid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (i.id = i2d.individualid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (i2d.diseaseid = d.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_PHENOTYPES . ' AS p ON (i.id = p.individualid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (i.ownerid = uo.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS ds ON (i.statusid = ds.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (i.created_by = uc.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (i.edited_by = ue.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 'i.id';

        // SQL code for viewing the list of individuals
        $this->aSQLViewList['SELECT']   = 'i.*, ' .
                                          'i.id AS individualid, ' .
                                          'GROUP_CONCAT(DISTINCT d.id) AS diseaseids, ' .
                                          'GROUP_CONCAT(DISTINCT d.symbol ORDER BY d.symbol SEPARATOR ", ") AS diseases_, ' .
                                          'GROUP_CONCAT(DISTINCT s2g.geneid ORDER BY s2g.geneid SEPARATOR ", ") AS genes_screened_, ' .
                                          '(SELECT COUNT(*) FROM ' . TABLE_SCR2VAR . ' WHERE screeningid = s.id) AS variants_, ' . 
                                          'uo.name AS owner, ' .
                                          'ds.name AS status';
        $this->aSQLViewList['FROM']     = TABLE_INDIVIDUALS . ' AS i ' .
                                          'LEFT OUTER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (i.id = i2d.individualid) ' .
                                          'LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (i2d.diseaseid = d.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s ON (i.id = s.individualid) ' .
                                          'LEFT OUTER JOIN ' . TABLE_SCR2GENE . ' AS s2g ON (s.id = s2g.screeningid) ' .
                                          'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (i.ownerid = uo.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS ds ON (i.statusid = ds.id)';
        $this->aSQLViewList['GROUP_BY'] = 'i.id';

        // Run parent constructor to find out about the custom columns.
        parent::LOVD_Custom();
        
        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry = array_merge(
                 $this->buildViewEntry(),
                 array(
                        'owner_' => 'Owner name',
                        'status' => 'Individual data status',
                        'created_by_' => array('Created by', LEVEL_COLLABORATOR),
                        'created_date_' => array('Date created', LEVEL_COLLABORATOR),
                        'edited_by_' => array('Last edited by', LEVEL_COLLABORATOR),
                        'edited_date_' => array('Date last edited', LEVEL_COLLABORATOR),
                      ));

        // Because the gene information is publicly available, remove some columns for the public.
        $this->unsetColsByAuthLevel();

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList = array_merge(
                 array(
                        'individualid_' => array(
                                    'view' => array('Individual ID', 110),
                                    'db'   => array('i.id', 'ASC', true)),
                      ),
                 $this->buildViewList(),
                 array(
                        'diseaseids' => array(
                                    'view' => array('Disease ID', 0),
                                    'db'   => array('diseaseids', false, true)),
                        'diseases_' => array(
                                    'view' => array('Disease', 175),
                                    'db'   => array('diseases_', false, true)),
                        'genes_screened_' => array(
                                    'view' => array('Genes screened', 175),
                                    'db'   => array('genes_screened_', false, true)),
                        'variants_' => array(
                                    'view' => array('Variants', 75),
                                    'db'   => array('variants_', 'ASC', 'INT_UNSIGNED')),
                        'owner' => array(
                                    'view' => array('Owner', 160),
                                    'db'   => array('uo.name', 'ASC', true)),
                        'status' => array(
                                    'view' => array('Status', 70),
                                    'db'   => array('ds.name', false, true)),
                      ));
        $this->sSortDefault = 'individualid_';
    }





    function checkFields ($aData)
    {
        global $_AUTH, $_SETT;

        // Checks fields before submission of data.
        if (ACTION == 'edit') {
            global $zData; // FIXME; this could be done more elegantly.
            
            // Mandatory fields.
            $this->aCheckMandatory[] = 'password';

            if (!empty($aData['statusid']) && $_AUTH['level'] < LEVEL_CURATOR) {
                lovd_errorAdd('statusid', 'Not allowed to change \'Status of this data\'.');
            }
        }

        if ($_AUTH['level'] >= LEVEL_CURATOR) {
            // Mandatory fields.
            $this->aCheckMandatory[] = 'ownerid';
            $this->aCheckMandatory[] = 'statusid';
        }

        parent::checkFields($aData);

        // FIXME; eerst een concat om daarna te exploden???
        $qDiseases = lovd_queryDB('SELECT GROUP_CONCAT(DISTINCT id) AS diseases FROM ' . TABLE_DISEASES, array());
        $aDiseases = mysql_fetch_row($qDiseases);
        $aDiseases = explode(',', $aDiseases[0]);
        // FIXME; ik denk dat de query naar binnen deze if moet.
        // FIXME; misschien heb je geen query nodig en kun je via de getForm() data ook bij de lijst komen.
        //   De parent checkFields vraagt de getForm() namelijk al op.
        // FIXME; parent::checkFields() heeft er toch al voor gezorgd dat $aData['active_diseases'] bestaat en een array is, of niet?
        if (isset($aData['active_diseases'])) {
            foreach ($aData['active_diseases'] as $sDisease) {
                if (!in_array($sDisease, $aDiseases)) {
                    if ($sDisease != 'None') {
                        // FIXME; een if binnen een if kan ook in één if.
                        // FIXME; ik stel voor hiervan te maken "value ' . htmlspecialchars($sDisease) . ' is not a valid disease" of zoiets.
                        // Overigens is het volgens mij $nDisease.
                        lovd_errorAdd('active_diseases', 'Please select a proper disease in the \'This gene has been linked to these diseases\' selection box');
                    }
                }
            }
        }

        // FIXME; move to object_custom.php.
        if (!empty($_POST['ownerid'])) {
            if ($_AUTH['level'] >= LEVEL_CURATOR) {
                $q = lovd_queryDB('SELECT id FROM ' . TABLE_USERS . ' WHERE id = ?', array($_POST['ownerid']));
                if (!mysql_num_rows($q)) {
                    // FIXME; clearly they haven't used the selection list, so possibly a different error message needed?
                    lovd_errorAdd('ownerid', 'Please select a proper owner from the \'Owner of this individual\' selection box.');
                }
            } else {
                // FIXME; this is a hack attempt. We should consider logging this. Or just plainly ignore the value.
                lovd_errorAdd('ownerid', 'Not allowed to change \'Owner of this individual\'.');
            }
        }

        if (!empty($_POST['statusid'])) {
            if ($_AUTH['level'] >= LEVEL_CURATOR && !array_key_exists($_POST['statusid'], $_SETT['data_status'])) {
                lovd_errorAdd('statusid', 'Please select a proper status from the \'Status of this data\' selection box.');
            } elseif ($_AUTH['level'] < LEVEL_CURATOR) {
                // FIXME; wie, lager dan LEVEL_CURATOR, komt er op dit formulier? Alleen de data owner. Moet die de status kunnen aanpassen?
                lovd_errorAdd('statusid', 'Not allowed to set \'Status of this data\'.');
            }
        }
       
        lovd_checkXSS();
    }





    function getForm ()
    {
        // Build the form.
        global $_AUTH, $_SETT;
        
        // Get list of diseases
        $aDiseasesForm = array();
        $qData = lovd_queryDB('SELECT id, CONCAT(symbol, " (", name, ")") FROM ' . TABLE_DISEASES . ' ORDER BY id');
        $nData = mysql_num_rows($qData);
        // FIXME; aangezien $aDiseasesForm leeg zal zijn als $nData 0 is, stel ik voor deze while buiten de if te doen,
        // dan de if om te draaien. Dan heb je geen else nodig.
        if ($nData) {
            while ($r = mysql_fetch_row($qData)) {
                $aDiseasesForm[$r[0]] = $r[1];
            }
        } else {
            // FIXME; is het niet makkelijker om hier geen value op te geven ipv "None"? Het is toch geen verplicht veld, dus als ie geselecteerd wordt,
            // wordt de waarde automatisch genegeerd. Nu moest je een uitzondering plaatsen in checkFields() en individuals.php.
            $aDiseasesForm = array('None' => 'No disease entries available');
        }

        $nFieldSize = (count($aDiseasesForm) < 20? count($aDiseasesForm) : 20);
        $aSelectOwner = array();

        if ($_AUTH['level'] >= LEVEL_CURATOR) {
            // FIXME; sorteren ergens op? Naam? Of land? Kijk naar hoe dit in LOVD 2.0 geregeld is.
            $q = lovd_queryDB('SELECT id, name FROM ' . TABLE_USERS);
            while ($z = mysql_fetch_assoc($q)) {
                $aSelectOwner[$z['id']] = $z['name'];
            }
            $aFormOwner = array('Owner of this individual', '', 'select', 'ownerid', 1, $aSelectOwner, false, false, false);
            $aFormStatus = array('Status of this data', '', 'select', 'statusid', 1, $_SETT['data_status'], false, false, false);
        } else {
            // FIXME; dit moet dan dus de owner zijn, mag die de status niet aanpassen (niet publiek -> wel publiek) of een publieke entry bewerken?
            // Overigens, in jouw code mogen alleen managers hier komen... Dit moet even goed worden uitgedacht.
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
'authorization_skip' => 'skip',
 'authorization_hr1' => 'hr',
     'authorization' => array('Enter your password for authorization', '', 'password', 'password', 20),
 'authorization_hr2' => 'hr',
                        'skip',
                      ));
                      
        if (ACTION != 'edit') {
            unset($this->aFormData['authorization_skip'], $this->aFormData['authorization_hr1'], $this->aFormData['authorization'], $this->aFormData['authorization_hr2']);
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
            $zData['row_link'] = 'individuals/' . rawurlencode($zData['id']);
            $zData['individualid_'] = '<A href="' . $zData['row_link'] . '" class="hide">' . $zData['id'] . '</A>';
        } else {
            $zData['owner_'] = '<A href="users/' . $zData['owner'] . '">' . $zData['owner_'] . '</A>';
        }

        return $zData;
    }





    function setDefaultValues ()
    {
        global $_AUTH;
        
        $_POST['statusid'] = STATUS_OK;
        $_POST['ownerid'] = $_AUTH['id'];
        $this->initDefaultValues();
    }
}
?>
