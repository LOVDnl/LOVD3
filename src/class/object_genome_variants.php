<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-12-20
 * Modified    : 2016-10-14
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Msc. Daan Asscheman <D.Asscheman@LUMC.nl>
 *               M. Kroon <m.kroon@lumc.nl>
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
        global $_AUTH, $_CONF, $_SETT;

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
                                           'GROUP_CONCAT(DISTINCT i.id, ";", i.statusid SEPARATOR ";;") AS __individuals, ' .
                                           'GROUP_CONCAT(s2v.screeningid SEPARATOR "|") AS screeningids, ' .
                                           'uo.name AS owned_by_, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_';
        if (LOVD_plus) {
            // Add curation status and confirmation status.
            $this->aSQLViewEntry['SELECT'] .= ', ' .
                                           'curs.name AS curation_status_, ' .
                                           'cons.name AS confirmation_status_';
        }
        $this->aSQLViewEntry['FROM']     = TABLE_VARIANTS . ' AS vog ' .
                                           'LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s.id = s2v.screeningid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_ALLELES . ' AS a ON (vog.allele = a.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (vog.owned_by = uo.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (vog.created_by = uc.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (vog.edited_by = ue.id)';
        if (LOVD_plus) {
            // Add curation status and confirmation status.
            $this->aSQLViewEntry['FROM'] .= ' ' .
                                           'LEFT OUTER JOIN ' . TABLE_CURATION_STATUS . ' AS curs ON (vog.curation_statusid = curs.id)' .
                                           'LEFT OUTER JOIN ' . TABLE_CONFIRMATION_STATUS . ' AS cons ON (vog.confirmation_statusid = cons.id)';
        }
        $this->aSQLViewEntry['GROUP_BY'] = 'vog.id';

        // SQL code for viewing the list of variants
        // FIXME: we should implement this in a different way
        $this->aSQLViewList['SELECT']   = 'vog.*, ' .
                                          // FIXME; de , is niet de standaard.
                                          'GROUP_CONCAT(s2v.screeningid SEPARATOR ",") AS screeningids, ' .
                                          'a.name AS allele_, ' .
                                          'e.name AS effect, ' .
                                          'uo.name AS owned_by_, ' .
                                          'CONCAT_WS(";", uo.id, uo.name, uo.email, uo.institute, uo.department, IFNULL(uo.countryid, "")) AS _owner, ' .
                                ($_AUTH['level'] >= LEVEL_COLLABORATOR?
                                          'CASE vog.statusid WHEN ' . STATUS_MARKED . ' THEN "marked" WHEN ' . STATUS_HIDDEN .' THEN "del" WHEN ' . STATUS_PENDING .' THEN "del" END AS class_name,'
                                        : '') .
                                          'ds.name AS status';
        $this->aSQLViewList['FROM']     = TABLE_VARIANTS . ' AS vog ' .
                                // Added so that Curators and Collaborators can view the variants for which they have viewing rights in the genomic variant viewlist.
                                ($_AUTH['level'] == LEVEL_SUBMITTER && (count($_AUTH['curates']) || count($_AUTH['collaborates']))?
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
                        'curation_status_' => 'Curation status',
                        'confirmation_status_' => 'Confirmation status',
                      ),
                 $this->buildViewEntry(),
                 array(
                        'mapping_flags_' => array('Automatic mapping', LEVEL_COLLABORATOR),
                        'average_frequency_' => 'Average frequency (large NGS studies)',
                        'owned_by_' => 'Owner',
                        'status' => array('Variant data status', LEVEL_COLLABORATOR),
                        'created_by_' => array('Created by', LEVEL_COLLABORATOR),
                        'created_date_' => array('Date created', LEVEL_COLLABORATOR),
                        'edited_by_' => array('Last edited by', LEVEL_COLLABORATOR),
                        'edited_date_' => array('Date last edited', LEVEL_COLLABORATOR),
                      ));
        if (!LOVD_plus) {
            unset($this->aColumnsViewEntry['curation_status_']);
            unset($this->aColumnsViewEntry['confirmation_status_']);
        }

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList = array_merge(
                 array(
                        'screeningids' => array(
                                    'view' => false,
                                    'db'   => array('screeningids', 'ASC', 'TEXT')),
                        'id_' => array(
                                    'view' => array('Variant ID', 90, 'style="text-align : right;"'),
                                    'db'   => array('vog.id', 'ASC', true)),
                        'effect' => array(
                                    'view' => array('Effect', 70),
                                    'db'   => array('e.name', 'ASC', true),
                                    'legend' => array('The variant\'s effect on a protein\'s function, in the format Reported/Curator concluded; ranging from \'+\' (variant affects function) to \'-\' (does not affect function).',
                                                      'The variant\'s effect on a protein\'s function, in the format Reported/Curator concluded; \'+\' indicating the variant affects function, \'+?\' probably affects function, \'-\' does not affect function, \'-?\' probably does not affect function, \'?\' effect unknown, \'.\' effect not classified.')),
                        'allele_' => array(
                                    'view' => array('Allele', 120),
                                    'db'   => array('a.name', 'ASC', true),
                                    'legend' => array('On which allele is the variant located? Does not necessarily imply inheritance!',
                                                      'On which allele is the variant located? Does not necessarily imply inheritance! \'Paternal\' (confirmed or inferred), \'Maternal\' (confirmed or inferred), \'Parent #1\' or #2 for compound heterozygosity without having screened the parents, \'Unknown\' for heterozygosity without having screened the parents, \'Both\' for homozygozity.')),
                        'chromosome' => array(
                                    'view' => array('Chr', 50),
                                    'db'   => array('vog.chromosome', 'ASC', true)),
                      ),
                 $this->buildViewList(),
                 array(
                        'owned_by_' => array(
                                    'view' => array('Owner', 160),
                                    'db'   => array('uo.name', 'ASC', true)),
                        'owner_countryid' => array(
                                    'view' => false,
                                    'db'   => array('uo.countryid', 'ASC', true)),
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

        // 2015-10-09; 3.0-14; Add genome build name to the VOG/DNA field.
        $this->aColumnsViewEntry['VariantOnGenome/DNA'] .= ' (Relative to ' . $_CONF['refseq_build'] . ' / ' . $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_name'] . ')';
        $this->aColumnsViewList['VariantOnGenome/DNA']['view'][0] .= ' (' . $_CONF['refseq_build'] . ')';

        // Because the information is publicly available, remove some columns for the public.
        $this->unsetColsByAuthLevel();

        $this->sRowLink = 'variants/{{ID}}';
    }





    function buildForm ($sPrefix = '')
    {
        $aForm = parent::buildForm($sPrefix);
        // Link to HVS for nomenclature.
        if (isset($aForm[$sPrefix . 'VariantOnGenome/DNA'])) {
            $aForm[$sPrefix . 'VariantOnGenome/DNA'][0] = str_replace('(HGVS format)', '(<A href="http://www.hgvs.org/mutnomen/recs-DNA.html" target="_blank">HGVS format</A>)', $aForm[$sPrefix . 'VariantOnGenome/DNA'][0]);
        }
        return $aForm;
    }





    function checkFields ($aData, $zData = false)
    {
        global $_AUTH, $_CONF, $_SETT;

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
        } elseif (isset($aData['effect_reported']) && $aData['effect_reported'] === '0') {
            // Submitters must fill in the variant effect field; '0' is not allowed for them.
            unset($aData['effect_reported']);
        }

        // Do this before running checkFields so that we have time to predict the DBID and fill it in.
        if (!empty($aData['VariantOnGenome/DNA']) && isset($this->aColumns['VariantOnGenome/DBID']) && ($this->aColumns['VariantOnGenome/DBID']['public_add'] || $_AUTH['level'] >= LEVEL_CURATOR)) {
            // VOGs with at least one VOT, which still have a chr* DBID, will get an error. So we'll empty the DBID field, allowing the new VOT value to be autofilled in.
            if (!empty($aData['aTranscripts']) && !empty($aData['VariantOnGenome/DBID']) && strpos($aData['VariantOnGenome/DBID'], 'chr' . $aData['chromosome'] . '_') !== false) {
                $aData['VariantOnGenome/DBID'] = '';
            }
            if (empty($aData['VariantOnGenome/DBID'])) {
                if (lovd_getProjectFile() != '/import.php') {
                    // Only predict an DBID, if we're actually going to use it (which doesn't happen when we're importing).
                    $aData['VariantOnGenome/DBID'] = $_POST['VariantOnGenome/DBID'] = lovd_fetchDBID($aData);
                }
            } elseif (!lovd_checkDBID($aData)) {
                lovd_errorAdd('VariantOnGenome/DBID', 'Please enter a valid ID in the ' . (lovd_getProjectFile() == '/import.php'? 'VariantOnGenome/DBID' : '\'ID\'') . ' field or leave it blank and LOVD will predict it. Incorrect ID: "' . htmlspecialchars($aData['VariantOnGenome/DBID']) . '".');
            }
        }

        parent::checkFields($aData);

        // Checks fields before submission of data.
        if (isset($aData['effect_reported']) && !isset($_SETT['var_effect'][$aData['effect_reported']])) {
            lovd_errorAdd('effect_reported', 'Please select a proper functional effect from the \'Affects function (reported)\' selection box.');
        }

        if (isset($aData['effect_concluded']) && !isset($_SETT['var_effect'][$aData['effect_concluded']])) {
            lovd_errorAdd('effect_concluded', 'Please select a proper functional effect from the \'Affects function (concluded)\' selection box.');
        }

        if (!empty($aData['chromosome']) && !isset($_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$aData['chromosome']])) {
            lovd_errorAdd('chromosome', 'Please select a proper chromosome from the \'Chromosome\' selection box.');
        }

        lovd_checkXSS();
    }





    function getForm ()
    {
        // Build the form.

        // If we've built the form before, simply return it. Especially imports will repeatedly call checkFields(), which calls getForm().
        if (!empty($this->aFormData)) {
            return parent::getForm();
        }

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

        if ($_AUTH['level'] >= LEVEL_CURATOR) {
            $aSelectOwner = $_DB->query('SELECT id, CONCAT(name, " (#", id, ")") as name_id FROM ' . TABLE_USERS .
                ' ORDER BY name')->fetchAllCombine();
            $aFormOwner = array('Owner of this data', '', 'select', 'owned_by', 1, $aSelectOwner, false, false, false);
            $aSelectStatus = $_SETT['data_status'];
            if (lovd_getProjectFile() == '/import.php') {
                // During an import the status pending is allowed, therefore only status in progress is unset.
                unset($aSelectStatus[STATUS_IN_PROGRESS]);
            } else {
                unset($aSelectStatus[STATUS_PENDING], $aSelectStatus[STATUS_IN_PROGRESS]);
            }
            $aFormStatus = array('Status of this data', '', 'select', 'statusid', 1, $aSelectStatus, false, false, false);
        } else {
            $aFormOwner = array();
            $aFormStatus = array();
        }

        $aTranscriptsForm = array();
        if (!empty($_DATA['Transcript'])) {
            foreach (array_keys($_DATA['Transcript']) as $sGene) {
                $aTranscriptsForm = array_merge($aTranscriptsForm, $_DATA['Transcript'][$sGene]->getForm());
            }
        }

        // Add genome build name to VOG/DNA field.
        $this->aColumns['VariantOnGenome/DNA']['description_form'] = '<B>Relative to ' . $_CONF['refseq_build'] . ' / ' . $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_name'] . '.</B>' .
            (!$this->aColumns['VariantOnGenome/DNA']['description_form']? '' : '<BR>' . $this->aColumns['VariantOnGenome/DNA']['description_form']);

        // FIXME; right now two blocks in this array are put in, and optionally removed later. However, the if() above can build an entire block, such that one of the two big unset()s can be removed.
        // A similar if() to create the "authorization" block, or possibly an if() in the building of this form array, is easier to understand and more efficient.
        // Array which will make up the form table.
        $this->aFormData = array_merge(
                 array(
                        array('POST', '', '', '', '35%', '14', '65%'),
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

        if (ACTION == 'create' || (ACTION == 'publish' && GET)) {
            // When creating, or when publishing without any changes, unset the authorization.
            unset($this->aFormData['authorization']);
        }
        if ($_AUTH['level'] < LEVEL_CURATOR) {
            unset($this->aFormData['effect'], $this->aFormData['general_skip'], $this->aFormData['general'], $this->aFormData['general_hr1'], $this->aFormData['owner'], $this->aFormData['status'], $this->aFormData['general_hr2']);
        }

        return parent::getForm();
    }




    function prepareData ($zData = '', $sView = 'list')
    {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.

        global $_AUTH, $_DB, $_SETT;

        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        if ($sView == 'entry') {
            $zData['individualid_'] = '';
            // While in principle a variant should only be connected to one patient, due to database model limitations, through several screenings, one could link a variant to more individuals.
            foreach ($zData['individuals'] as $aIndividual) {
                list($nID, $nStatusID) = $aIndividual;
                $zData['individualid_'] .= ($zData['individualid_']? ', ' : '') . '<A href="individuals/' . $nID . '">' . $nID . '</A>';
                if ($_AUTH['level'] >= LEVEL_COLLABORATOR) {
                    $zData['individualid_'] .= ' <SPAN style="color : #' . $this->getStatusColor($nStatusID) . '">(' . $_SETT['data_status'][$nStatusID] . ')</SPAN>';
                }
            }
            if (empty($zData['individualid_'])) {
                unset($this->aColumnsViewEntry['individualid_']);
            }
            $zData['effect_reported'] = $_SETT['var_effect'][$zData['effectid']{0}];
            $zData['effect_concluded'] = $_SETT['var_effect'][$zData['effectid']{1}];

            if (!empty($zData['VariantOnGenome/DBID'])) {
                // Allow linking to view of all these variants.
                $sQ = 'SELECT COUNT(*) FROM ' . TABLE_VARIANTS . ' WHERE chromosome = ? AND `VariantOnGenome/DBID` = ?';
                $aArgs = array($zData['chromosome'], $zData['VariantOnGenome/DBID']);
                if ($_AUTH['level'] < LEVEL_CURATOR) {
                    $sQ .= ' AND statusid >= ?';
                    $aArgs[] = STATUS_MARKED;
                }
                $n = $_DB->query($sQ, $aArgs)->fetchColumn();
                if ($n > 1) {
                    list($sPrefix,) = explode('_', $zData['VariantOnGenome/DBID'], 2);
                    $sLink = '<A href="' . (substr($sPrefix, 0, 3) == 'chr'? 'variants' : 'view/' . $sPrefix) . '?search_VariantOnGenome%2FDBID=%3D%22' . $zData['VariantOnGenome/DBID'] . '%22">See all ' . $n . ' reported entries</A>';
                    // This is against our coding policy of never modifying actual contents of values (we always create a copy with _ appended), but now I simply can't without
                    // modifying the column list manually. If only array_splice() would work on associative arrays... I'm not going to create a workaround here.
                    $zData['VariantOnGenome/DBID'] .= ' <SPAN style="float:right">' . $sLink . '</SPAN>';
                }
            }

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

            // 2013-09-27; 3.0-08; Frequences retrieved from the LOVD WGS install.
            if (!$zData['chromosome'] || !$zData['position_g_start'] || !$zData['position_g_end']) {
                $zData['average_frequency_'] = 'Genomic location of variant could not be determined';
            } elseif ($zData['average_frequency'] === '') {
                $zData['average_frequency_'] = '<A href="#" onclick="lovd_openWindow(\'' . ROOT_PATH . 'scripts/fetch_frequencies.php\', \'FetchFrequencies\', \'500\', \'150\'); return false;">Retrieve</A>';
            } elseif ($zData['average_frequency'] === '0') {
                $zData['average_frequency_'] = 'Variant not found in online data sets';
            } else {
                $zData['average_frequency_'] = round($zData['average_frequency'], 5) . ' <SPAN style="float: right"><A href="http://databases.lovd.nl/whole_genome/variants/chr' . $zData['chromosome'] . '?search_VariantOnGenome/DNA=' . $zData['VariantOnGenome/DNA'] . '" title="" target="_blank">View details</A></SPAN>';
            }
            if (LOVD_plus && !empty($zData['curation_status_'])) {
                // Add a link to the curation status to show the curation status history for this variant.
                $zData['curation_status_'] .= '<SPAN style="float: right"><A href="#" onclick="lovd_openWindow(\'' . lovd_getInstallURL(). 'variants/' . $zData['id'] . '?curation_status_log&in_window\', \'curationStatusHistory\', 1050, 450);return false;">View History</A></SPAN>';
            }
            if (LOVD_plus && !empty($zData['confirmation_status_'])) {
                // Add a link to the confirmation status to show the confirmation status history for this variant.
                $zData['confirmation_status_'] .= '<SPAN style="float: right"><A href="#" onclick="lovd_openWindow(\'' . lovd_getInstallURL(). 'variants/' . $zData['id'] . '?confirmation_status_log&in_window\', \'confirmationStatusHistory\', 1050, 450);return false;">View History</A></SPAN>';
            }
        }
        // Replace rs numbers with dbSNP links.
        if (!empty($zData['VariantOnGenome/dbSNP'])) {
            $zData['VariantOnGenome/dbSNP'] = preg_replace('/(rs\d+)/', '<SPAN' . ($sView != 'list'? '' : ' onclick="cancelParentEvent(event);"') . '><A href="http://www.ncbi.nlm.nih.gov/SNP/snp_ref.cgi?rs=' . "$1" . '" target="_blank">' . "$1" . '</A></SPAN>', $zData['VariantOnGenome/dbSNP']);
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
