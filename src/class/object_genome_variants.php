<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-12-20
 * Modified    : 2011-10-11
 * For LOVD    : 3.0-alpha-05
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
                                           'GROUP_CONCAT(s2v.screeningid SEPARATOR "|") AS screeningids, ' .
                                           'uo.name AS owner_, ' .
                                           'ds.name AS status, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_';
        $this->aSQLViewEntry['FROM']     = TABLE_VARIANTS . ' AS vog ' .
                                           'LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (vog.owned_by = uo.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS ds ON (vog.statusid = ds.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (vog.created_by = uc.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (vog.edited_by = ue.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 'vog.id';

        // SQL code for viewing the list of variants
        // FIXME: we should implement this in a different way
        $this->aSQLViewList['SELECT']   = 'vog.*, ' .
                                          // FIXME; de , is niet de standaard.
                                          'GROUP_CONCAT(s2v.screeningid SEPARATOR ",") AS screeningids, ' .
                                          'uo.name AS owner, ' .
                                          'ds.name AS status';
        $this->aSQLViewList['FROM']     = TABLE_VARIANTS . ' AS vog ' .
                                          'LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid) ' .
                                          'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (vog.owned_by = uo.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS ds ON (vog.statusid = ds.id)';
        $this->aSQLViewList['GROUP_BY'] = 'vog.id';

        parent::LOVD_Custom();

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry = array_merge(
                 array(
                        'chromosome' => 'Chromosome',
                        'allele_' => 'Allele',
                        'pathogenicid' => 'Pathogenicity',
                      ),
                 $this->buildViewEntry(),
                 array(
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
                        'id_' => array(
                                    'view' => array('Variant ID', 90),
                                    'db'   => array('vog.id', 'ASC', true)),
                        'chromosome' => array(
                                    'view' => array('Chr', 50),
                                    'db'   => array('vog.chromosome', 'ASC', true)),
                      ),
                 $this->buildViewList(),
                 array(
                        'allele_' => array(
                                    'view' => array('Allele', 110),
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
        
        $this->sSortDefault = 'id_';

        $this->sRowLink = 'variants/{{ID}}';
    }





    function checkFields ($aData)
    {
        global $_AUTH, $_SETT, $_CONF;

        // Mandatory fields.
        $this->aCheckMandatory = array('chromosome');

        // Checks fields before submission of data.
        if (ACTION == 'edit') {
            global $zData; // FIXME; this could be done more elegantly.
            
            if ($_AUTH['level'] < LEVEL_CURATOR && $aData['statusid'] > $zData['statusid']) {
                // FIXME; zullen we deze code in objects_custom doen? 
                lovd_errorAdd('statusid', 'Not allowed to change \'Status of this data\' from ' . $_SETT['data_status'][$zData['statusid']] . ' to ' . $_SETT['data_status'][$aData['statusid']] . '.');
            }

            $this->aCheckMandatory[] = 'password';
        }

        if ($_AUTH['level'] >= LEVEL_CURATOR) {
            $this->aCheckMandatory[] = 'owned_by';
            $this->aCheckMandatory[] = 'statusid';
        }

        parent::checkFields($aData);

        if (!isset($aData['allele']) || !array_key_exists($aData['allele'], $_SETT['var_allele'])) {
            lovd_errorAdd('allele', 'Please select a proper allele from the \'Allele\' selection box.');
        }

        if (!empty($aData['chromosome']) && !array_key_exists($aData['chromosome'], $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'])) {
            lovd_errorAdd('chromosome', 'Please select a proper chromosome from the \'Chromosome\' selection box.');
        }

        if (!empty($aData['owned_by'])) {
            if ($_AUTH['level'] >= LEVEL_CURATOR) {
                $q = lovd_queryDB_Old('SELECT id FROM ' . TABLE_USERS . ' WHERE id = ?', array($aData['owned_by']));
                if (!mysql_num_rows($q)) {
                    lovd_errorAdd('owned_by', 'Please select a proper owner from the \'Owner of this variant\' selection box.');
                }
            } else {
                lovd_errorAdd('owned_by', 'Not allowed to change \'Owner of this variant\'.');
            }
        }

        if (!empty($aData['statusid'])) {
            if ($_AUTH['level'] >= LEVEL_CURATOR && ($aData['statusid'] < STATUS_HIDDEN || !array_key_exists($aData['statusid'], $_SETT['data_status']))) {
                lovd_errorAdd('statusid', 'Please select a proper status from the \'Status of this data\' selection box.');
            } elseif ($_AUTH['level'] < LEVEL_CURATOR) {
                // FIXME; wie, lager dan LEVEL_CURATOR, komt er op dit formulier? Alleen de data owner. Moet die de status kunnen aanpassen?
                lovd_errorAdd('statusid', 'Not allowed to set \'Status of this data\'.');
            }
        }

        lovd_checkXSS();
    }





    function getForm ($aTranscriptsForm = array())
    {
        // Build the form.
        global $_AUTH, $_SETT, $_CONF;

        if (!empty($_GET['geneid'])) {
            // Setting chromosome to $_POST so that insertEntry() will get the correct chromosome value as well. checkFields() will run getForm(), so it will always be available.
            list($_POST['chromosome']) = list($sChromosome) = mysql_fetch_row(lovd_queryDB_Old('SELECT chromosome FROM ' . TABLE_GENES . ' WHERE id=?', array($_GET['geneid'])));
            $aFormChromosome = array('Chromosome', '', 'print', $sChromosome);
        } else {
            $aChromosomes = array_keys($_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences']);
            $aSelectChromosome = array_combine($aChromosomes, $aChromosomes);
            $aFormChromosome = array('Chromosome', '', 'select', 'chromosome', 1, $aSelectChromosome, false, false, false);
        }

        $aSelectOwner = array();
        if ($_AUTH['level'] >= LEVEL_CURATOR) {
            $q = lovd_queryDB_Old('SELECT id, name FROM ' . TABLE_USERS . ' ORDER BY name');
            while ($z = mysql_fetch_assoc($q)) {
                $aSelectOwner[$z['id']] = $z['name'];
            }
            $aSelectStatus = $_SETT['data_status'];
            unset($aSelectStatus[STATUS_PENDING], $aSelectStatus[STATUS_IN_PROGRESS]);
            $aFormOwner = array('Owner of this variant', '', 'select', 'owned_by', 1, $aSelectOwner, false, false, false);
            $aFormStatus = array('Status of this data', '', 'select', 'statusid', 1, $aSelectStatus, false, false, false);
        } else {
            $aFormOwner = array();
            $aFormStatus = array();
        }

        // FIXME; right now two blocks in this array are put in, and optionally removed later. However, the if() above can build an entire block, such that one of the two big unset()s can be removed.
        // A similar if() to create the "authorization" block, or possibly an if() in the building of this form array, is easier to understand and more efficient.
        // Array which will make up the form table.
        $this->aFormData = array_merge(
                 array(
                        array('POST', '', '', '', '50%', '14', '50%'),
                        array('', '', 'print', '<B>Genomic variant information</B>'),
                        'hr',
                        array('Allele', '', 'select', 'allele', 1, $_SETT['var_allele'], false, false, false),
                        array('', '', 'note', 'If you wish to report an homozygous variant, please select "Both (homozygous)" here.'),
                        $aFormChromosome,
                      ),
                 $this->buildForm(),
                 array(
                        'hr'
                      ),
                 $aTranscriptsForm,
                 array(
      'general_skip' => 'skip',
           'general' => array('', '', 'print', '<B>General information</B>'),
       'general_hr1' => 'hr',
             'owner' => $aFormOwner,
            'status' => $aFormStatus,
       'general_hr2' => 'hr',
'authorization_skip' => 'skip',
     'authorization' => array('Enter your password for authorization', '', 'password', 'password', 20),
                      ));
                      
        if (ACTION != 'edit') {
            unset($this->aFormData['authorization_skip'], $this->aFormData['authorization']);
        }
        if ($_AUTH['level'] < LEVEL_CURATOR) {
            unset($this->aFormData['general_skip'], $this->aFormData['general'], $this->aFormData['general_hr1'], $this->aFormData['owner'], $this->aFormData['status'], $this->aFormData['general_hr2']);
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
            // STUB
        } else {
            $zData['owner_'] = '<A href="users/' . $zData['owned_by'] . '">' . $zData['owner_'] . '</A>';
        }

        $zData['allele_'] = $_SETT['var_allele'][$zData['allele']];

        return $zData;
    }





    function setDefaultValues ()
    {
        global $_AUTH;
        
        $_POST['statusid'] = STATUS_OK;
        $_POST['owned_by'] = $_AUTH['id'];
        $this->initDefaultValues();
    }
}
?>
