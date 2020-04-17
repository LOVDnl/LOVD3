<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-05-12
 * Modified    : 2020-04-17
 * For LOVD    : 3.0-24
 *
 * Copyright   : 2004-2020 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Daan Asscheman <D.Asscheman@LUMC.nl>
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





class LOVD_TranscriptVariant extends LOVD_Custom
{
    // This class extends the Custom class and it handles the Variants On Transcripts.
    var $sObject = 'Transcript_Variant';
    var $sCategory = 'VariantOnTranscript';
    var $sTable = 'TABLE_VARIANTS_ON_TRANSCRIPTS';
    var $aTranscripts = array();
    // Flag to give transcript-specific fields used in getForm() and checkFields() a prefix to separate them.
    var $bPrefixTranscriptFields = true;





    function __construct ($sObjectID = '', $nID = '', $bLoadAllTranscripts = true)
    {
        // Default constructor.
        global $_DB, $_SETT;

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = '
            SELECT vot.*
            FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot
                LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id)
            WHERE vot.id = ?' .
            (!$sObjectID? '' : ' AND t.geneid = ?');

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'vot.*, ' .
                                           't.geneid, t.id_ncbi, vog.chromosome'; // MGHA needs chromosome to get the Genomizer link to work.
        $this->aSQLViewEntry['FROM']     = TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ' .
                                          'INNER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id) ' . // Only done so that the vog.statusid can be checked.
                                           'LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 'vot.id';

        // SQL code for viewing the list of variants
        // FIXME: we should implement this in a different way
        $this->aSQLViewList['SELECT']   = 'vot.*, ' .
                                          't.geneid, t.id_ncbi, ' .
                                          'e.name AS effect, ' .
                                          'ds.name AS status';
        // LOVD+ adds the check if this transcript is a preferred transcript.
        if (LOVD_plus) {
            $this->aSQLViewList['SELECT'] .= ', gp2g.genepanelid';
        }
        $this->aSQLViewList['FROM']     = TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ' .
                                          'INNER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_EFFECT . ' AS e ON (vot.effectid = e.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS ds ON (vog.statusid = ds.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (t.id = vot.transcriptid)';
        // LOVD+ adds the check if this transcript is a preferred transcript in any gene panel.
        if (LOVD_plus) {
            $this->aSQLViewList['FROM'] .= ' LEFT OUTER JOIN ' . TABLE_GP2GENE . ' AS gp2g ON (vot.transcriptid = gp2g.transcriptid)';
        }

        $this->sObjectID = $sObjectID;
        $this->nID = $nID;
        parent::__construct();

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry = array_merge(
                 array(
                        'geneid_' => 'Gene',
                        'id_ncbi_' => 'Transcript ID',
                        'effect_reported' => 'Affects function (as reported)',
                        'effect_concluded' => 'Affects function (by curator)',
                      ),
                 (!LOVD_plus || !lovd_verifyInstance('mgha', false)? array() :
                     // MGHA entry for the Genomizer link in the VOT ViewEntry.
                     array(
                         'genomizer_url_' => 'Genomizer',
                         'clinvar_' => "ClinVar Description (dbNSFP)"
                     )),
                 $this->buildViewEntry());
        if (LOVD_plus) {
            unset($this->aColumnsViewEntry['effect_reported']);
            unset($this->aColumnsViewEntry['effect_concluded']);
            if (lovd_verifyInstance('mgha', false) && !isset($this->aColumnsViewEntry['VariantOnTranscript/dbNSFP/ClinVar/Clinical_Significance'])) {
                unset($this->aColumnsViewEntry['clinvar_']);
            }
        }

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList = array_merge(
                 array(
                        'geneid' => array(
                                    'view' => array('Gene', 70),
                                    'db'   => array('t.geneid', 'ASC', true)),
                        'transcriptid' => array(
                                    'view' => array('Transcript ID', 90),
                                    'db'   => array('vot.transcriptid', 'ASC', true)),
                        'id_ncbi' => array(
                                    'view' => array('Transcript', 120),
                                    'db'   => array('t.id_ncbi', 'ASC', true)),
                        'id_' => array(
                                    'view' => array('Variant ID', 90),
                                    'db'   => array('vot.id', 'ASC', true)),
                        'effect' => array(
                                    'view' => array('Affects function', 70),
                                    'db'   => array('e.name', 'ASC', true),
                                    'legend' => array('The variant\'s effect on the protein\'s function, in the format \'R/C\' where R is the value ' . (LOVD_plus? 'initially reported and C is the value finally concluded' : 'reported by the source and C is the value concluded by the curator') . '; values ranging from \'+\' (variant affects function) to \'-\' (does not affect function).',
                                        'The variant\'s effect on the protein\'s function, in the format \'R/C\' where R is the value ' . (LOVD_plus? 'initially reported and C is the value finally concluded' : 'reported by the source and C is the value concluded by the curator') . '; \'+\' indicating the variant affects function, \'+?\' probably affects function, \'+*\' affects function, not associated with individual\'s disease phenotype, \'#\' affects function, not associated with any known disease phenotype, \'-\' does not affect function, \'-?\' probably does not affect function, \'?\' effect unknown, \'.\' effect not classified.')),
                      ),
                 $this->buildViewList(),
                 array(
                        'status' => array(
                                    'view' => array('Status', 70),
                                    'db'   => array('ds.name', false, true),
                                    'auth' => $_SETT['user_level_settings']['see_nonpublic_data']),
                      ));
        if (LOVD_plus) {
            unset($this->aColumnsViewList['effect']);
        }

        $this->sSortDefault = 'id_ncbi';

        // Because the disease information is publicly available, remove some columns for the public.
        $this->unsetColsByAuthLevel();


        if (!empty($this->nID)) {
            // Known variant ID, load all transcripts for existing variant.
            $aTranscripts = $_DB->query('SELECT t.id, t.id_ncbi, t.geneid, t.id_mutalyzer FROM ' .
                TABLE_TRANSCRIPTS . ' AS t LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS .
                ' AS vot ON (t.id = vot.transcriptid) WHERE vot.id = ? ORDER BY t.geneid, ' .
                't.id_ncbi', array($this->nID))->fetchAllRow();
        } else {
            // Unknown variant, but then we must have a gene symbol ($sObjectID).
            // Get list of transcript(s) available for this gene for getForm(), checkFields(), etc...
            $aTranscripts = $_DB->query('SELECT id, id_ncbi, geneid, id_mutalyzer FROM ' .
                TABLE_TRANSCRIPTS . ' WHERE geneid IN (?' .
                str_repeat(', ?', substr_count($sObjectID, ',')) . ') ' .
                (!$bLoadAllTranscripts? 'LIMIT 1' : 'ORDER BY id_ncbi'),
                explode(',', $sObjectID))->fetchAllRow();

            if (!$bLoadAllTranscripts) {
                // Only a single transcript, don't use field prefixes for getForm() and
                // checkFields()
                $this->bPrefixTranscriptFields = false;
            }
        }
        foreach ($aTranscripts as $aTranscript) {
            $this->aTranscripts[$aTranscript[0]] = array($aTranscript[1], $aTranscript[2], $aTranscript[3]);
        }

        $this->sRowLink = 'variants/{{ID}}';
    }





    function buildForm ($sPrefix = '')
    {
        $aForm = parent::buildForm($sPrefix);
        // Link to HVS for nomenclature.
        if (isset($aForm[$sPrefix . 'VariantOnTranscript/DNA'])) {
            $aForm[$sPrefix . 'VariantOnTranscript/DNA'][0] = str_replace('(HGVS format)', '(<A href="http://varnomen.hgvs.org/recommendations/DNA" target="_blank">HGVS format</A>)', $aForm[$sPrefix . 'VariantOnTranscript/DNA'][0]);
        }
        if (isset($aForm[$sPrefix . 'VariantOnTranscript/RNA'])) {
            $aForm[$sPrefix . 'VariantOnTranscript/RNA'][0] = str_replace('(HGVS format)', '(<A href="http://varnomen.hgvs.org/recommendations/RNA" target="_blank">HGVS format</A>)', $aForm[$sPrefix . 'VariantOnTranscript/RNA'][0]);
        }
        if (isset($aForm[$sPrefix . 'VariantOnTranscript/Protein'])) {
            $aForm[$sPrefix . 'VariantOnTranscript/Protein'][0] = str_replace('(HGVS format)', '(<A href="http://varnomen.hgvs.org/recommendations/protein" target="_blank">HGVS format</A>)', $aForm[$sPrefix . 'VariantOnTranscript/Protein'][0]);
        }
        return $aForm;
    }





    function checkFields ($aData, $zData = false, $aOptions = array())
    {
        // Checks fields before submission of data.
        // Loop through all transcripts to have each transcript's set of columns checked.
        global $_AUTH;

        // Reset mandatory fields, because import.php calls checkFields() multiple times
        // and we don't want this list to grow forever.
        $this->aCheckMandatory = array();

        foreach (array_keys($this->aTranscripts) as $nTranscriptID) {
            if (!empty($aData['ignore_' . $nTranscriptID])) {
                continue;
            }
            $sPrefix = (!$this->bPrefixTranscriptFields? '' : $nTranscriptID . '_');
            foreach ($this->aColumns as $sCol => $aCol) {
                if (!$aCol['public_add'] && $_AUTH['level'] < LEVEL_CURATOR) {
                    continue;
                }
                $sCol = $sPrefix . $sCol;
                if ($aCol['mandatory']) {
                    $this->aCheckMandatory[] = $sCol;
                }

                if (!(LOVD_plus && lovd_getProjectFile() == '/import.php') && isset($aData[$sCol])) {
                    // These checks are disabled in LOVD+ to speed up the import.
                    $this->checkInputRegExp($sCol, $aData[$sCol]);
                    $this->checkSelectedInput($sCol, $aData[$sCol]);
                }
            }
            $this->aCheckMandatory[] = $sPrefix . 'effect_reported';
            if ($_AUTH['level'] >= LEVEL_CURATOR) {
                $this->aCheckMandatory[] = $sPrefix . 'effect_concluded';
            } elseif (isset($aData[$sPrefix . 'effect_reported']) && $aData[$sPrefix . 'effect_reported'] === '0') {
                // Submitters must fill in the variant effect field; '0' is not allowed for them.
                unset($aData[$sPrefix . 'effect_reported']);
            }
        }

        // Bypass LOVD_Custom::checkFields(), since it's functionality has been copied above.
        LOVD_Object::checkFields($aData, $zData, $aOptions);

        lovd_checkXSS();
    }





    function getForm ()
    {
        // Build the form.

        // If we've built the form before, simply return it. Especially imports will repeatedly call checkFields(), which calls getForm().
        if (!empty($this->aFormData)) {
            return parent::getForm();
        }

        global $_SETT, $_AUTH;

        // Create form per gene.
        foreach ($this->aTranscripts as $nTranscriptID => $aTranscript) {
            list($sTranscriptNM, $sGene) = $aTranscript;
            if ($sGene != $this->sObjectID) {
                continue;
            }
            $sPrefix = (!$this->bPrefixTranscriptFields? '' : $nTranscriptID . '_');
            $aEffectForm = array(array('Affects function (as reported)', '', 'select', $sPrefix . 'effect_reported', 1, $_SETT['var_effect'], false, false, false));
            if ($_AUTH['level'] >= LEVEL_CURATOR) {
                $aEffectForm[] = array('Affects function (by curator)', '', 'select', $sPrefix . 'effect_concluded', 1, $_SETT['var_effect'], false, false, false);
            }
            $this->aFormData = array_merge(
                                            $this->aFormData,
                                            array(
                                                    array('', '', 'print', '<B class="transcript" transcriptid="' . $nTranscriptID . '">Transcript variant on ' . $sTranscriptNM . ' (' . $sGene . ')</B>'),
                                                    'hr',
                                                  ),
                                            $this->buildForm($sPrefix),
                                            $aEffectForm,
                                            array(
                                                    'hr',
                                                    'skip',
                                                 )
                                         );
        }

        return parent::getForm();
    }





    function insertAll ($aData, $aFields = array())
    {
        global $_AUTH, $_SETT;

        foreach (array_keys($this->aTranscripts) as $nTranscriptID) {
            if (empty($aData['ignore_' . $nTranscriptID])) {
                foreach ($aFields as $sField) {
                    if (strpos($sField, '/')) {
                        $aData[$sField] = $aData[$nTranscriptID . '_' . $sField];
                    }
                }
                $aData['transcriptid'] = $nTranscriptID;
                $aData['effectid'] = $aData[$nTranscriptID . '_effect_reported'] . ($_AUTH['level'] >= LEVEL_CURATOR? $aData[$nTranscriptID . '_effect_concluded'] : substr($_SETT['var_effect_default'], -1));
                $aData['position_c_start'] = $aData[$nTranscriptID . '_position_c_start'];
                $aData['position_c_start_intron'] = $aData[$nTranscriptID . '_position_c_start_intron'];
                $aData['position_c_end'] = $aData[$nTranscriptID . '_position_c_end'];
                $aData['position_c_end_intron'] = $aData[$nTranscriptID . '_position_c_end_intron'];
                LOVD_Object::insertEntry($aData, $aFields);
            }
        }
        return $this->aTranscripts;
    }





    function loadAll ($nID = false)
    {
        // Loads all variantOnTranscript entries from the database.
        global $_DB, $_T;

        if (empty($nID)) {
            // We were called, but the class wasn't initiated with an ID. Fail.
            lovd_displayError('LOVD-Lib', 'Objects::(' . $this->sObject . ')::loadEntry() - Method didn\'t receive ID');
        }

        $q = $_DB->query($this->sSQLLoadEntry, array_merge(
            array($nID),
            (!$this->sObjectID? array() : array($this->sObjectID))), false);
        if ($q) {
            $z = $q->fetchAllAssoc();
        }
        if (!$q || !$z) {
            $sError = $_DB->formatError(); // Save the PDO error before it disappears.

            $_T->printHeader();
            if (defined('PAGE_TITLE')) {
                $_T->printTitle();
            }

            if ($sError) {
                lovd_queryError($this->sObject . '::loadEntry()', $sSQL, $sError);
            }

            lovd_showInfoTable('No such ID!', 'stop');

            $_T->printFooter();
            exit;
        }

        $zData = array();
        foreach ($z as $aVariantOnTranscript) {
            $aVariantOnTranscript = $this->autoExplode($aVariantOnTranscript);
            foreach ($this->aColumns as $sColClean => $aCol) {
                $sCol = $aVariantOnTranscript['transcriptid'] . '_' . $sColClean;
                if ($aCol['form_type'][2] == 'select' && $aCol['form_type'][3] > 1) {
                    $zData[$sCol] = explode(';', $aVariantOnTranscript[$sColClean]);
                } else {
                    $zData[$sCol] = $aVariantOnTranscript[$sColClean];
                }
            }
            $zData[$aVariantOnTranscript['transcriptid'] . '_effectid'] = $aVariantOnTranscript['effectid'];
            $zData[$aVariantOnTranscript['transcriptid'] . '_position_c_start'] = $aVariantOnTranscript['position_c_start'];
            $zData[$aVariantOnTranscript['transcriptid'] . '_position_c_start_intron'] = $aVariantOnTranscript['position_c_start_intron'];
            $zData[$aVariantOnTranscript['transcriptid'] . '_position_c_end'] = $aVariantOnTranscript['position_c_end'];
            $zData[$aVariantOnTranscript['transcriptid'] . '_position_c_end_intron'] = $aVariantOnTranscript['position_c_end_intron'];
        }
        return $zData;
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

        if ($sView == 'entry') {
            $zData['geneid_'] = '<A href="genes/' . $zData['geneid'] . '">' . $zData['geneid'] . '</A>';
            $zData['id_ncbi_'] = '<A href="transcripts/' . $zData['transcriptid'] . '">' . $zData['id_ncbi'] . '</A>';
            $zData['effect_reported'] = $_SETT['var_effect'][$zData['effectid']{0}];
            $zData['effect_concluded'] = $_SETT['var_effect'][$zData['effectid']{1}];
            if (LOVD_plus && lovd_verifyInstance('mgha', false)) { // Display the Genomizer URL in the VOT ViewEntry. TODO Once the ref and alt are separated we need to add it into this URL. Should we add this to the links table so as it can be used elsewhere?
                $zData['genomizer_url_'] = '<A href="http://genomizer.com/?chr=' . $zData['chromosome'] . '&gene=' . $zData['geneid'] . '&ref_seq=' . $zData['id_ncbi'] . '&variant=' . $zData['VariantOnTranscript/DNA'] . '" target="_blank">Genomizer Link</A>';
                if (isset($zData['VariantOnTranscript/dbNSFP/ClinVar/Clinical_Significance'])) {
                    $zData['clinvar_'] = implode(', ', lovd_mapCodeToDescription(explode(',', $zData['VariantOnTranscript/dbNSFP/ClinVar/Clinical_Significance']), $_SETT['clinvar_var_effect']));
                }
            }
        }

        return $zData;
    }





    function setAllDefaultValues ()
    {
        // Initiate default values of fields in $_POST.
        foreach (array_keys($this->aTranscripts) as $nTranscriptID) {
            foreach (array_keys($this->aColumns) as $sColClean) {
                $sCol = $nTranscriptID . '_' . $sColClean;
                // Fill $_POST with the column's default value.
                $_POST[$sCol] = $this->getDefaultValue($sColClean);
            }
        }
    }





    function updateEntry ($sID, $aData, $aFields = array())
    {
        // Updates entry $nID with data from $aData in the database, changing only fields defined in $aFields.
        global $_DB;

        list($nID, $nTranscriptID) = explode('|', $sID);
        if (!trim($nID) || !trim($nTranscriptID)) {
            lovd_displayError('LOVD-Lib', 'Objects::(' . $this->sObject . ')::updateEntry() - Method didn\'t receive ID');
        } elseif (!is_array($aData) || !count($aData)) {
            lovd_displayError('LOVD-Lib', 'Objects::(' . $this->sObject . ')::updateEntry() - Method didn\'t receive data array');
        } elseif (!is_array($aFields) || !count($aFields)) {
            $aFields = array_keys($aData);
        }

        // Query text.
        $sSQL = 'UPDATE ' . constant($this->sTable) . ' SET ';
        $aSQL = array();
        foreach ($aFields as $key => $sField) {
            $sSQL .= (!$key? '' : ', ') . '`' . $sField . '` = ?';
            if (!isset($aData[$sField])) {
                // Field may be not set, make sure it is (happens in very rare cases).
                $aData[$sField] = '';
            }
            if ($aData[$sField] === '' && in_array(substr(lovd_getColumnType(constant($this->sTable), $sField), 0, 3), array('INT', 'DAT', 'DEC', 'FLO'))) {
                $aData[$sField] = NULL;
            }
            $aSQL[] = $aData[$sField];
        }
        $sSQL .= ' WHERE id = ? AND transcriptid = ?';
        $aSQL[] = $nID;
        $aSQL[] = $nTranscriptID;

        if (!defined('LOG_EVENT')) {
            define('LOG_EVENT', $this->sObject . '::updateEntry()');
        }
        $q = $_DB->query($sSQL, $aSQL, true, true);

        return $q->rowCount();
    }





    function updateAll ($nID, $aData, $aGeneFields = array())
    {
        // Edit all VariantOnTranscript entries.
        // FIXME; We need a cleaner solution than globalizing zData.
        global $zData, $_AUTH, $_DB;

        // Updates entry $nID with data from $aData in the database, changing only fields defined in $aFields.
        if (!trim($nID)) {
            lovd_displayError('LOVD-Lib', 'Objects::(' . $this->sObject . ')::updateEntry() - Method didn\'t receive ID');
        } elseif (!is_array($aData) || !count($aData)) {
            lovd_displayError('LOVD-Lib', 'Objects::(' . $this->sObject . ')::updateEntry() - Method didn\'t receive data array');
        }

        $nAffected = 0;
        foreach ($this->aTranscripts as $nTranscriptID => $aTranscript) {
            // Loop through transcripts, running the update one by one.
            $sGene = $aTranscript[1];

            if (empty($aGeneFields[$sGene]) || !is_array($aGeneFields[$sGene])) {
                // Just update everything.
                $aGeneFields[$sGene] = array_unique(array_map(function ($sField) {
                    // Clean transcript ID off of the field.
                    if (preg_match('/^(?:[0-9]+)_(.+)$/', $sField, $aRegs)) {
                        $sField = $aRegs[1];
                    }
                    return $sField;
                }, array_keys($aData)));
            }

            // Each gene has different fields of course.
            foreach ($aGeneFields[$sGene] as $sField) {
                if (strpos($sField, '/')) {
                    $aData[$sField] = $aData[$nTranscriptID . '_' . $sField];
                }
            }

            // Although these fields should of course exist, this method should not assume they do.
            if (in_array('effectid', $aGeneFields[$sGene])) {
                $aData['effectid'] = $aData[$nTranscriptID . '_effect_reported'] . ($_AUTH['level'] >= LEVEL_CURATOR? $aData[$nTranscriptID . '_effect_concluded'] : $zData[$nTranscriptID . '_effectid']{1});
            }
            if (in_array('position_c_start', $aGeneFields[$sGene])) {
                $aData['position_c_start'] = $aData[$nTranscriptID . '_position_c_start'];
                $aData['position_c_start_intron'] = $aData[$nTranscriptID . '_position_c_start_intron'];
                $aData['position_c_end'] = $aData[$nTranscriptID . '_position_c_end'];
                $aData['position_c_end_intron'] = $aData[$nTranscriptID . '_position_c_end_intron'];
            }

            // Query text.
            $sSQL = 'UPDATE ' . constant($this->sTable) . ' SET ';
            $aSQL = array();
            foreach ($aGeneFields[$sGene] as $key => $sField) {
                $sSQL .= (!$key? '' : ', ') . '`' . $sField . '` = ?';
                if (substr(lovd_getColumnType(constant($this->sTable), $sField), 0, 3) == 'INT' && $aData[$sField] === '') {
                    $aData[$sField] = NULL;
                }
                $aSQL[] = $aData[$sField];
            }
            $sSQL .= ' WHERE id = ? AND transcriptid = ?';
            $aSQL[] = $nID;
            $aSQL[] = $nTranscriptID;

            if (!defined('LOG_EVENT')) {
                define('LOG_EVENT', $this->sObject . '::updateEntry()');
            }

            $q = $_DB->query($sSQL, $aSQL, true, true);
            $nAffected += $q->rowCount();
        }
        return $nAffected;
    }





    function viewEntry ($nID = false) {
        global $_DB;

        list($nID, $nTranscriptID) = explode(',', $nID);
        $this->aSQLViewEntry['WHERE'] .= (empty($this->aSQLViewEntry['WHERE'])? '' : ' AND ') . 'vot.transcriptid = \'' . $nTranscriptID . '\'';

        // Before passing this on to parent::viewEntry(), perform a standard count() check on the transcript ID,
        // to make sure that we won't get a query error when the combination of VariantID/TranscriptID does not yield
        // any results. Easiest is then to fake a wrong $nID such that parent::viewEntry() will complain.
        if (!$_DB->query('SELECT COUNT(*) FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' WHERE id = ? AND transcriptid = ?', array($nID, $nTranscriptID))->fetchColumn()) {
            $nID = -1;
        }
        parent::viewEntry($nID);
    }
}
?>
