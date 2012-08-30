<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-12-20
 * Modified    : 2012-08-30
 * For LOVD    : 3.0-beta-08
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
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





    function __construct ()
    {
        // Default constructor.
        global $_AUTH;

        // SQL code for loading an entry for an edit form.
        // FIXME; change owner to owned_by_ in the load entry query below.
        $this->sSQLLoadEntry = 'SELECT vog.*, ' .
                               'uo.name AS owner ' .
                               'FROM ' . TABLE_VARIANTS . ' AS vog ' .
                               'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (vog.owned_by = uo.id) ' .
                               'WHERE vog.id = ?';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'vog.*, ' .
                                           'a.name AS allele_, ' .
                                           'GROUP_CONCAT(DISTINCT s.individualid SEPARATOR ";") AS _individualids, ' .
                                           'GROUP_CONCAT(s2v.screeningid SEPARATOR "|") AS screeningids, ' .
                                           'uo.name AS owned_by_, ' .
                                           'ds.name AS status, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_';
        $this->aSQLViewEntry['FROM']     = TABLE_VARIANTS . ' AS vog ' .
                                           'LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s.id = s2v.screeningid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_ALLELES . ' AS a ON (vog.allele = a.id) ' .
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
                                          'a.name AS allele_, ' .
                                          'e.name AS effect, ' .
                                          'uo.name AS owned_by_, ' .
                                ($_AUTH['level'] >= LEVEL_COLLABORATOR?
                                          'CASE ds.id WHEN ' . STATUS_MARKED . ' THEN "marked" WHEN ' . STATUS_HIDDEN .' THEN "del" END AS class_name,'
                                        : '') .
                                          'ds.name AS status';
        $this->aSQLViewList['FROM']     = TABLE_VARIANTS . ' AS vog ' .
                                // Added so that Curators and Collaborators can view the variants for which they have viewing rights in the genomic variant viewlist.
                                ($_AUTH['level'] == LEVEL_SUBMITTER? 
                                          'LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (vog.id = vot.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) '
                                        : '') .
                                          'LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid) ' .
                                          'LEFT OUTER JOIN ' . TABLE_ALLELES . ' AS a ON (vog.allele = a.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_EFFECT . ' AS e ON (vog.effectid = e.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (vog.owned_by = uo.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS ds ON (vog.statusid = ds.id)';
        $this->aSQLViewList['GROUP_BY'] = 'vog.id';

        parent::__construct();

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry = array_merge(
                 array(
                        'individualid_' => 'Individual ID',
                        'chromosome' => 'Chromosome',
                        'allele_' => 'Allele',
                        'effect_reported' => 'Affects function (reported)',
                        'effect_concluded' => 'Affects function (concluded)',
                      ),
                 $this->buildViewEntry(),
                 array(
                        'mapping_flags_' => array('Automatic mapping', LEVEL_COLLABORATOR),
                        'owned_by_' => 'Owner',
                        'status' => array('Variant data status', LEVEL_COLLABORATOR),
                        'created_by_' => array('Created by', LEVEL_COLLABORATOR),
                        'created_date_' => array('Date created', LEVEL_COLLABORATOR),
                        'edited_by_' => array('Last edited by', LEVEL_COLLABORATOR),
                        'edited_date_' => array('Date last edited', LEVEL_COLLABORATOR),
                      ));

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList = array_merge(
                 array(
                        'screeningids' => array(
                                    'view' => false,
                                    'db'   => array('screeningids', 'ASC', 'TEXT')),
                        'id_' => array(
                                    'view' => array('Variant ID', 90),
                                    'db'   => array('vog.id', 'ASC', true)),
                        'effect' => array(
                                    'view' => array('Effect', 70),
                                    'db'   => array('e.name', 'ASC', true)),
                        'chromosome' => array(
                                    'view' => array('Chr', 50),
                                    'db'   => array('vog.chromosome', 'ASC', true)),
                      ),
                 $this->buildViewList(),
                 array(
                        'allele_' => array(
                                    'view' => array('Allele', 120),
                                    'db'   => array('a.name', 'ASC', true)),
                        'owned_by_' => array(
                                    'view' => array('Owner', 160),
                                    'db'   => array('uo.name', 'ASC', true)),
                        'status' => array(
                                    'view' => array('Status', 70),
                                    'db'   => array('ds.name', false, true),
                                    'auth' => LEVEL_COLLABORATOR),
                        'created_by' => array(
                                    'view' => false,
                                    'db'   => array('vog.created_by', false, true)),
                        'created_date' => array(
                                    'view' => false,
                                    'db'   => array('vog.created_date', 'ASC', true)),
                      ));

        $this->sSortDefault = 'VariantOnGenome/DNA';

        // Because the information is publicly available, remove some columns for the public.
        $this->unsetColsByAuthLevel();

        $this->sRowLink = 'variants/{{ID}}';
    }





    function checkFields ($aData)
    {
        global $_AUTH, $_CONF, $_DB, $_SETT;

        // Mandatory fields.
        $this->aCheckMandatory =
                 array(
                        'chromosome',
                        'effect_reported',
                        'owned_by',
                        'statusid',
                      );

        if ($_AUTH['level'] >= LEVEL_CURATOR) {
            $this->aCheckMandatory[] = 'effect_concluded';
        }

        // Do this before running checkFields so that we have time to predict the DBID and fill it in.
        if (!empty($aData['VariantOnGenome/DNA']) && isset($this->aColumns['VariantOnGenome/DBID']) && ($this->aColumns['VariantOnGenome/DBID']['public_add'] || $_AUTH['level'] >= LEVEL_CURATOR)) {
            // VOGs with at least one VOT, which still have a chr* DBID, will get an error. So we'll empty the DBID field, allowing the new VOT value to be autofilled in.
            if (!empty($aData['aTranscripts']) && !empty($aData['VariantOnGenome/DBID']) && strpos($aData['VariantOnGenome/DBID'], 'chr' . $aData['chromosome'] . '_') !== false) {
                $aData['VariantOnGenome/DBID'] = '';
            }
            if (empty($aData['VariantOnGenome/DBID'])) {
                $aData['VariantOnGenome/DBID'] = $_POST['VariantOnGenome/DBID'] = lovd_fetchDBID($aData);
            } elseif (!lovd_checkDBID($aData)) {
                lovd_errorAdd('VariantOnGenome/DBID', 'Please enter a valid ID in the \'ID\' field or leave it blank and LOVD will predict it.');
            }
        }

        parent::checkFields($aData);

        // Checks fields before submission of data.
        $aAlleles = $_DB->query('SELECT id FROM ' . TABLE_ALLELES)->fetchAllColumn();
        if (!isset($aData['allele']) || !in_array($aData['allele'], $aAlleles)) {
            lovd_errorAdd('allele', 'Please select a proper allele from the \'Allele\' selection box.');
        }

        if (isset($aData['effect_reported']) && !array_key_exists($aData['effect_reported'], $_SETT['var_effect'])) {
            lovd_errorAdd('effect_reported', 'Please select a proper functional effect from the \'Affects function (reported)\' selection box.');
        }

        if (isset($aData['effect_concluded']) && !array_key_exists($aData['effect_concluded'], $_SETT['var_effect'])) {
            lovd_errorAdd('effect_concluded', 'Please select a proper functional effect from the \'Affects function (concluded)\' selection box.');
        }

        if (!empty($aData['chromosome']) && !array_key_exists($aData['chromosome'], $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'])) {
            lovd_errorAdd('chromosome', 'Please select a proper chromosome from the \'Chromosome\' selection box.');
        }

        lovd_checkXSS();
    }





    function getForm ()
    {
        // Build the form.
        global $_AUTH, $_CONF, $_DB, $_SETT, $zData, $_DATA;

        $aSelectAllele = $_DB->query('SELECT id, name FROM ' . TABLE_ALLELES . ' ORDER BY display_order')->fetchAllCombine();

        if (!empty($_GET['geneid'])) {
            $aFormChromosome = array('Chromosome', '', 'print', $_POST['chromosome']);
        } elseif (ACTION == 'edit') {
            $aFormChromosome = array('Chromosome', '', 'print', $zData['chromosome']);
        } else {
            $aChromosomes = array_keys($_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences']);
            $aSelectChromosome = array_combine($aChromosomes, $aChromosomes);
            $aFormChromosome = array('Chromosome', '', 'select', 'chromosome', 1, $aSelectChromosome, false, false, false);
        }

        $aSelectOwner = array();
        if ($_AUTH['level'] >= LEVEL_CURATOR) {
            $aSelectOwner = $_DB->query('SELECT id, name FROM ' . TABLE_USERS . ' WHERE id > 0 ORDER BY name')->fetchAllCombine();
            $aFormOwner = array('Owner of this data', '', 'select', 'owned_by', 1, $aSelectOwner, false, false, false);
            $aSelectStatus = $_SETT['data_status'];
            unset($aSelectStatus[STATUS_PENDING], $aSelectStatus[STATUS_IN_PROGRESS]);
            $aFormStatus = array('Status of this data', '', 'select', 'statusid', 1, $aSelectStatus, false, false, false);
        } else {
            $aFormOwner = array();
            $aFormStatus = array();
        }

        $aTranscriptsForm = array();
        if (!empty($_DATA['Transcript'])) {
            $aTranscriptObject = reset($_DATA['Transcript']);
            $aTranscriptsForm = $aTranscriptObject->getForm();
        }

        // Add '(hg19)' to VOG/DNA field. NOTE: If you choose to remove this, make sure the additional fix after the aFormData array creation is also removed.
        $this->aColumns['VariantOnGenome/DNA']['description_form'] = '<B>Relative to ' . $_CONF['refseq_build'] . ' / ' . $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_name'] . '.</B>' .
            (!$this->aColumns['VariantOnGenome/DNA']['description_form']? '' : '<BR>' . $this->aColumns['VariantOnGenome/DNA']['description_form']);

        // FIXME; right now two blocks in this array are put in, and optionally removed later. However, the if() above can build an entire block, such that one of the two big unset()s can be removed.
        // A similar if() to create the "authorization" block, or possibly an if() in the building of this form array, is easier to understand and more efficient.
        // Array which will make up the form table.
        $this->aFormData = array_merge(
                 array(
                        array('POST', '', '', '', '50%', '14', '50%'),
                      ),
                $aTranscriptsForm,
                array(
                        array('', '', 'print', '<B>Genomic variant information</B>'),
                        'hr',
                        array('Allele', '', 'select', 'allele', 1, $aSelectAllele, false, false, false),
                        array('', '', 'note', 'If you wish to report an homozygous variant, please select "Both (homozygous)" here.'),
                        $aFormChromosome,
                      ),
                 $this->buildForm(),
                 array(
                        array('Affects function (reported)', '', 'select', 'effect_reported', 1, $_SETT['var_effect'], false, false, false),
            'effect' => array('Affects function (concluded)', '', 'select', 'effect_concluded', 1, $_SETT['var_effect'], false, false, false),
                        'hr',
      'general_skip' => 'skip',
           'general' => array('', '', 'print', '<B>General information</B>'),
       'general_hr1' => 'hr',
             'owner' => $aFormOwner,
            'status' => $aFormStatus,
       'general_hr2' => 'hr',
                        'skip',
     'authorization' => array('Enter your password for authorization', '', 'password', 'password', 20),
                      ));

        if (ACTION != 'edit') {
            unset($this->aFormData['authorization']);
        }
        if ($_AUTH['level'] < LEVEL_CURATOR) {
            unset($this->aFormData['effect'], $this->aFormData['general_skip'], $this->aFormData['general'], $this->aFormData['general_hr1'], $this->aFormData['owner'], $this->aFormData['status'], $this->aFormData['general_hr2']);
        }
        // Reset VOG/DNA field to normal, because getForm() can be called twice per page load (checkFields && normal call).
        // NOTE: Bastardly annoying preg pattern, very hard to make it not eat everything away. Maybe just put the hg reference in the field's name?
        $this->aColumns['VariantOnGenome/DNA']['description_form'] = preg_replace('/^<B>[^<]+<\/B>(<BR>)?/', '', $this->aColumns['VariantOnGenome/DNA']['description_form']);

        return parent::getForm();
    }




    function prepareData ($zData = '', $sView = 'list')
    {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.

        global $_SETT, $_AUTH;

        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        if ($sView == 'entry') {
            $zData['individualid_'] = '';
            // While in principle a variant should only be connected to one patient, due to database model limitations, through several screenings, one could link a variant to more individuals.
            foreach ($zData['individualids'] as $nID) {
                if (lovd_isAuthorized('individual', $nID, false)) {
                    $zData['individualid_'] .= ($zData['individualid_']? ', ' : '') . '<A href="individuals/' . $nID . '">' . $nID . '</A>';
                }
            }
            if (empty($zData['individualid_'])) {
                unset($this->aColumnsViewEntry['individualid_']);
            }
            $zData['effect_reported'] = $_SETT['var_effect'][$zData['effectid']{0}];
            $zData['effect_concluded'] = $_SETT['var_effect'][$zData['effectid']{1}];

            if ($zData['mapping_flags'] & MAPPING_ALLOW) {
                $sMappingLinkText  = '';
                $sMappingLinkTitle = '';
                if ($zData['mapping_flags'] & MAPPING_NOT_RECOGNIZED) {
                    $zData['mapping_flags_'] = 'Variant not recognized';
                    if ($zData['mapping_flags'] & MAPPING_ALLOW_CREATE_GENES) {
                        $zData['mapping_flags_'] .= ' (would have created genes as needed)';
                    }
                    $sMappingLinkText = 'Retry';
                } elseif ($zData['mapping_flags'] & MAPPING_DONE) {
                    $zData['mapping_flags_'] = 'Done';
                    if ($zData['mapping_flags'] & MAPPING_ALLOW_CREATE_GENES) {
                        $zData['mapping_flags_'] .= ' (created genes as needed)';
                    }
                    $sMappingLinkText  = 'Map again';
                    $sMappingLinkTitle = 'If new transcripts have been added to LOVD, this will try to map this variant to them.';
                } else {
                    $zData['mapping_flags_'] = 'Scheduled';
                    if ($zData['mapping_flags'] & MAPPING_ALLOW_CREATE_GENES) {
                        $zData['mapping_flags_'] .= ', creating genes as needed';
                    }
                    if ($zData['mapping_flags'] & MAPPING_ERROR) {
                        $zData['mapping_flags_'] .= ' (encountered a problem on the last attempt)';
                    }
                    $sMappingLinkText = 'Map now';
                }
                if ($_AUTH['level'] >= LEVEL_OWNER) {
                    $zData['mapping_flags_'] .= ' <SPAN style="float: right" id="mapOnRequest"><A href="#" onclick="return lovd_mapOnRequest();"' . (!$sMappingLinkTitle? '' : ' title="' . $sMappingLinkTitle . '"') . '>' . $sMappingLinkText . '</A></SPAN>';
                }
            } else {
                $zData['mapping_flags_'] = 'Off';
            }
        }

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
