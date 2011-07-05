<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-12-20
 * Modified    : 2011-07-05
 * For LOVD    : 3.0-alpha-02
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





class LOVD_GenomeVariant extends LOVD_Custom {
    // This class extends the basic Object class and it handles the Link object.
    var $sObject = 'Genome_Variant';
    var $sCategory = 'VariantOnGenome';
    var $sTable = 'TABLE_VARIANTS';
    var $bShared = false;





    function LOVD_GenomeVariant ()
    {
        // Default constructor.

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = 'SELECT * ' .
                               'FROM ' . TABLE_VARIANTS . ' ' .
                               'WHERE id = ?';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'vog.*, ' .
                                           'uo.id AS owner, ' . // FIXME; lijkt me niet zinvol; je hebt vog.ownerid al!
                                           'uo.name AS owner_, ' .
                                           'ds.name AS status, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_';
        $this->aSQLViewEntry['FROM']     = TABLE_VARIANTS . ' AS vog ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (vog.ownerid = uo.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS ds ON (vog.statusid = ds.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (vog.created_by = uc.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (vog.edited_by = ue.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 'vog.id';

        // SQL code for viewing the list of variants
        // FIXME: we should implement this in a different way
        $this->aSQLViewList['SELECT']   = 'vog.*, ' .
                                          // FIXME; de , is niet de standaard. We moeten hier standaarden voor zetten en forcen.
                                          'GROUP_CONCAT(s2v.screeningid SEPARATOR ",") AS screeningids, ' .
                                          'uo.name AS owner, ' .
                                          'ds.name AS status';
        $this->aSQLViewList['FROM']     = TABLE_VARIANTS . ' AS vog ' .
                                          'LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid) ' .
                                          'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (vog.ownerid = uo.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS ds ON (vog.statusid = ds.id)';
        $this->aSQLViewList['GROUP_BY'] = 'vog.id';

        parent::LOVD_Custom();
        
        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry = array_merge(
                 $this->buildViewEntry(),
                 array(
                        'allele_' => 'Allele',
                        'pathogenicid' => 'Pathogenicity',
                        'chromosome' => 'Chromosome',
                        'position_g_start' => 'Genomic start position',
                        'position_g_end' => 'Genomic end position',
                        'type' => 'Type',
                        'owner_' => 'Owner',
                        'status' => 'Variant data status',
                        'created_by_' => array('Created by', LEVEL_COLLABORATOR),
                        'created_date_' => array('Date created', LEVEL_COLLABORATOR),
                        'edited_by_' => array('Last edited by', LEVEL_COLLABORATOR),
                        'edited_date_' => array('Date last edited', LEVEL_COLLABORATOR),
                      ));

        // Because the disease information is publicly available, remove some columns for the public.
        $this->unsetColsByAuthLevel();
        
        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList = array_merge(
                 array(
                        'screeningids' => array(
                                    'view' => array('Screening ID', 90),
                                    'db'   => array('screeningids', 'ASC', 'TEXT')),
                        'id' => array(
                                    'view' => array('Variant ID', 90),
                                    'db'   => array('vog.id', 'ASC', true))
                      ),
                 $this->buildViewList(),
                 array(
                        'allele_' => array(
                                    'view' => array('Allele', 100),
                                    'db'   => array('vog.allele', 'ASC', true)),
                        'pathogenicid' => array(
                                    'view' => array('Pathogenicity', 110),
                                    'db'   => array('vog.pathogenicid', 'ASC', true)),
                        'type' => array(
                                    'view' => array('Type', 70),
                                    'db'   => array('vog.type', 'ASC', true)),
                        'owner' => array(
                                    'view' => array('Owner', 300),
                                    'db'   => array('uo.name', 'ASC', true)),
                        'status' => array(
                                    'view' => array('Status', 70),
                                    'db'   => array('ds.name', false, true)),
                      ));
        
        $this->sSortDefault = 'id';
    }




    
    function checkFields ($aData)
    {
        global $_AUTH, $_SETT, $_CONF;

        // Checks fields before submission of data.
        if (ACTION == 'edit') {
            global $zData; // FIXME; this could be done more elegantly.
            
            if ($_AUTH['level'] < LEVEL_CURATOR) {
                // FIXME; ik denk dat je dit andersom bedoeld hebt.
                // FIXME; zullen we deze code in objects_custom doen?
                if ($zData['statusid'] > $aData['statusid']) {
                    lovd_errorAdd('statusid' ,'Not allowed to change \'Status of this data\' from ' . $_SETT['var_status'][$zData['statusid']] . ' to ' . $_SETT['var_status'][$aData['statusid']] . '.');
                }
            }
        }

        // Mandatory fields.
        // FIXME; if empty, just define as an empty array in the class header?
        // IVO: Is al gedaan! Zie objects.php.
        if (ACTION == 'edit') {
            $this->aCheckMandatory[] = 'password';
        }

        parent::checkFields($aData);

        // FIXME; persoonlijk vind ik een in_array sneller herkenbaar dan een isset() in deze situatie.
        if (!isset($_POST['allele']) || !isset($_SETT['var_allele'][$_POST['allele']])) {
            lovd_errorAdd('allele', 'Please select a proper allele from the \'Allele\' selection box.');
        }

        // FIXME; er is een php functie voor in_array(..., array_keys()).
        if (!isset($_POST['chromosome']) || !in_array($_POST['chromosome'], array_keys($_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences']))) {
            lovd_errorAdd('chromosome', 'Please select a proper chromosome from the \'Chromosome\' selection box.');
        }

        if (isset($_POST['ownerid'])) {
            if (!empty($_POST['ownerid']) && $_AUTH['level'] >= LEVEL_CURATOR) {
                // FIXME; volgens mij werkt deze truc niet; $q is volgens mij niet false als de user niet gevonden wordt.
                //  Daarnaast is een select * een beetje overkill als je alleen maar weten wilt of deze user bestaat.
                $q = lovd_queryDB('SELECT * FROM ' . TABLE_USERS . ' WHERE id = ?', array($_POST['ownerid']));
                if (!$q) {
                    // FIXME; clearly they haven't used the selection list, so possibly a different error message needed?
                    // IVO: Ben ik met je eens. Overigens is dat wellicht met de checks hierboven ook zo (behalve dan lege waardes).
                    //   Misschien beter deze cols aan mandatory toe te voegen, en dan de checks hierboven alleen te doen als
                    //   er een waarde is ingevuld, zoals dit stukje dat ook heeft.
                    lovd_errorAdd('ownerid', 'Please select a proper owner from the \'Owner of this individual\' selection box.');
                }
            } elseif (empty($_POST['ownerid']) && $_AUTH['level'] >= LEVEL_CURATOR) {
                // FIXME; vanwaar deze gescheiden IF? En wat gebeurt er met users < LEVEL_CURATOR?
                lovd_errorAdd('ownerid' ,'Please select a proper owner from the \'Owner of this individual\' selection box.');
            }
        } else {
            if (!empty($_POST['ownerid']) && $_AUTH['level'] < LEVEL_CURATOR) {
                // FIXME; this is a hack attempt. We should consider logging this. Or just plainly ignore the value.
                // IVO: I'm pretty sure we are ignoring this value.
                lovd_errorAdd('ownerid' ,'Not allowed to change \'Owner of this individual\'.');
            }
        }

        // FIXME; deze ifs kunnen efficienter.
        if (isset($_POST['statusid'])) {
            // FIXME; vanwaar al deze checks op de user level? Waarom zijn deze foutmeldingen afhankelijk van de user level?
            if (!isset($_SETT['var_status'][$_POST['statusid']]) && $_AUTH['level'] >= LEVEL_CURATOR) {
                lovd_errorAdd('statusid' ,'Please select a proper status from the \'Status of this data\' selection box.');
            } elseif (empty($_POST['ownerid']) && $_AUTH['level'] >= LEVEL_CURATOR) {
                // FIXME; Als het een verplicht veld is, hoef je deze if al niet meer te doen.
                lovd_errorAdd('statusid' ,'Please select a proper status from the \'Status of this data\' selection box.');
            }
        } else {
            // FIXME; wie, lager dan LEVEL_CURATOR, komt er op dit formulier? Alleen de data owner. En die moet de status kunnen aanpassen ;)
            if (!empty($_POST['statusid']) && $_AUTH['level'] < LEVEL_CURATOR) {
                lovd_errorAdd('statusid' ,'Not allowed to change \'Status of this data\'.');
            }
        }

        if (ACTION == 'edit' && (!isset($aData['password']) || !lovd_verifyPassword($aData['password'], $_AUTH['password']))) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        lovd_checkXSS();
    }





    function getForm ()
    {
        // Build the form.
        global $_AUTH, $_SETT, $_CONF;

        $aSelectChromosome = array_combine(array_keys($_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences']), array_keys($_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences']));

        $aSelectOwner = array();
        if ($_AUTH['level'] >= LEVEL_CURATOR) {
            // FIXME; sorteren ergens op? Naam? Of land? Kijk naar hoe dit in LOVD 2.0 geregeld is.
            $q = lovd_queryDB('SELECT id, name FROM ' . TABLE_USERS);
            while ($z = mysql_fetch_assoc($q)) {
                $aSelectOwner[$z['id']] = $z['name'];
            }
            $aFormOwner = array('Owner of this individual', '', 'select', 'ownerid', 1, $aSelectOwner, false, false, false);
            $aFormStatus = array('Status of this data', '', 'select', 'statusid', 1, $_SETT['var_status'], false, false, false);
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
                        array('', '', 'print', '<B>Genomic variant information</B>'),
                        'hr',
                        array('Allele', '', 'select', 'allele', 1, $_SETT['var_allele'], false, false, false),
                        array('', '', 'note', 'If you wish to report an homozygous variant, please select "Both (homozygous)" here.'),
                        array('Chromosome', '', 'select', 'chromosome', 1, $aSelectChromosome, false, false, false),
                      ),
                 $this->buildViewForm(),
                 array(
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

        global $_SETT;

        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        if ($sView == 'list') {
            $zData['row_id'] = $zData['id'];
            $zData['row_link'] = 'variants/' . rawurlencode($zData['id']);
            $zData['id'] = '<A href="' . $zData['row_link'] . '" class="hide">' . $zData['id'] . '</A>';
        } else {
            $zData['owner_'] = '<A href="users/' . $zData['owner'] . '">' . $zData['owner_'] . '</A>';
        }

        $zData['allele_'] = $_SETT['var_allele'][$zData['allele']];

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
