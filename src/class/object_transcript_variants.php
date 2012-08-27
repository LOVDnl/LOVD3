<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-05-12
 * Modified    : 2012-07-23
 * For LOVD    : 3.0-beta-07
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





class LOVD_TranscriptVariant extends LOVD_Custom {
    // This class extends the basic Object class and it handles the Link object.
    var $sObject = 'Transcript_Variant';
    var $sCategory = 'VariantOnTranscript';
    var $sTable = 'TABLE_VARIANTS_ON_TRANSCRIPTS';
    var $bShared = true;
    var $aTranscripts = array();





    function __construct ($sObjectID = '', $nID = '')
    {
        // Default constructor.
        global $_DB;

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = 'SELECT vot.* ' .
                               'FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ' .
                               'LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) ' .
                               'WHERE vot.id = ? ' .
                               'AND t.geneid = ?';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'vot.*, ' .
                                           't.geneid, t.id_ncbi';
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
        $this->aSQLViewList['FROM']     = TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ' .
                                          'INNER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_EFFECT . ' AS e ON (vot.effectid = e.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS ds ON (vog.statusid = ds.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (t.id = vot.transcriptid)';

        $this->sObjectID = $sObjectID;
        $this->nID = $nID;
        parent::__construct();

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry = array_merge(
                 array(
                        'geneid_' => 'Gene',
                        'id_ncbi_' => 'Transcript ID',
                        'effect_reported' => 'Affects function (reported)',
                        'effect_concluded' => 'Affects function (concluded)',
                      ),
                 $this->buildViewEntry());

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
                                    'db'   => array('e.name', 'ASC', true)),
                      ),
                 $this->buildViewList(),
                 array(
                        'status' => array(
                                    'view' => array('Status', 70),
                                    'db'   => array('ds.name', false, true),
                                    'auth' => LEVEL_COLLABORATOR),
                      ));

        $this->sSortDefault = 'id_ncbi';

        // Because the disease information is publicly available, remove some columns for the public.
        $this->unsetColsByAuthLevel();

        // Set list of transcripts available for this variant for getForm(), checkFields(), etc...
        if (ACTION == 'create') {
            $aTranscripts = $_DB->query('SELECT id, id_ncbi, geneid, id_mutalyzer FROM ' . TABLE_TRANSCRIPTS . ' WHERE geneid IN (?' . str_repeat(', ?', substr_count(',', $sObjectID)) . ') ORDER BY id_ncbi', array($sObjectID))->fetchAllRow();
        } else {
            $aTranscripts = $_DB->query('SELECT t.id, t.id_ncbi, t.geneid, t.id_mutalyzer FROM ' . TABLE_TRANSCRIPTS . ' AS t LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) WHERE vot.id = ? ORDER BY t.geneid, t.id_ncbi', array($this->nID))->fetchAllRow();
        }
        foreach ($aTranscripts as $aTranscript) {
            $this->aTranscripts[$aTranscript[0]] = array($aTranscript[1], $aTranscript[2], $aTranscript[3]);
        }

        $this->sRowLink = 'variants/{{ID}}';
    }





    function buildForm ($sPrefix = '')
    {
        return parent::buildForm($sPrefix);
    }





    function checkFields ($aData)
    {
        // Checks fields before submission of data.
        // Loop through all transcripts to have each transcript's set of columns checked.
        global $_AUTH, $_SETT;

        foreach (array_keys($this->aTranscripts) as $nTranscriptID) {
            if (!empty($aData['ignore_' . $nTranscriptID])) {
                continue;
            }
            foreach ($this->aColumns as $sCol => $aCol) {
                if (!$aCol['public_add'] && $_AUTH['level'] < LEVEL_CURATOR) {
                    continue;
                }
                $sCol = $nTranscriptID . '_' . $sCol;
                if ($aCol['mandatory']) {
                    $this->aCheckMandatory[] = $sCol;
                }
                if (isset($aData[$sCol])) {
                    $this->checkInputRegExp($sCol, $aData[$sCol]);
                    $this->checkSelectedInput($sCol, $aData[$sCol]);
                }
            }
            $this->aCheckMandatory[] = $nTranscriptID . '_effect_reported';
            if ($_AUTH['level'] >= LEVEL_CURATOR) {
                $this->aCheckMandatory[] = $nTranscriptID . '_effect_concluded';
            }
            if (isset($aData[$nTranscriptID . '_effect_reported']) && !array_key_exists($aData[$nTranscriptID . '_effect_reported'], $_SETT['var_effect'])) {
                lovd_errorAdd($nTranscriptID . '_effect_reported', 'Please select a proper functional effect from the \'Affects function (reported)\' selection box.');
            }

            if (isset($aData[$nTranscriptID . '_effect_concluded']) && !array_key_exists($aData[$nTranscriptID . '_effect_concluded'], $_SETT['var_effect'])) {
                lovd_errorAdd($nTranscriptID . '_effect_concluded', 'Please select a proper functional effect from the \'Affects function (concluded)\' selection box.');
            }
        }

        // Bypass LOVD_Custom::checkFields(), since it's functionality has been copied above.
        LOVD_Object::checkFields($aData);

        lovd_checkXSS();
    }





    function getForm ()
    {
        global $_DATA, $_SETT, $_AUTH;

        $this->aFormData = array();

        foreach ($this->aTranscripts as $nTranscriptID => $aTranscript) {
            list($sTranscriptNM, $sGene) = $aTranscript;
            $aEffectForm = array(array('Affects function (reported)', '', 'select', $nTranscriptID . '_effect_reported', 1, $_SETT['var_effect'], false, false, false));
            if ($_AUTH['level'] >= LEVEL_CURATOR) {
                $aEffectForm[] = array('Affects function (concluded)', '', 'select', $nTranscriptID . '_effect_concluded', 1, $_SETT['var_effect'], false, false, false);
            }
            $this->aFormData = array_merge(
                                            $this->aFormData,
                                            array(
                                                    array('', '', 'print', '<B class="transcript" transcriptid="' . $nTranscriptID . '">Transcript variant on ' . $sTranscriptNM . ' (' . $sGene . ')</B>'),
                                                    'hr',
                                                  ),
                                            $_DATA['Transcript'][$sGene]->buildForm($nTranscriptID . '_'),
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
        global $_AUTH;

        foreach (array_keys($this->aTranscripts) as $nTranscriptID) {
            if (empty($aData['ignore_' . $nTranscriptID])) {
                foreach ($aFields as $sField) {
                    if (strpos($sField, '/')) {
                        $aData[$sField] = $aData[$nTranscriptID . '_' . $sField];
                    }
                }
                $aData['transcriptid'] = $nTranscriptID;
                $aData['effectid'] = $aData[$nTranscriptID . '_effect_reported'] . ($_AUTH['level'] >= LEVEL_CURATOR? $aData[$nTranscriptID . '_effect_concluded'] : '5');
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

        $q = $_DB->query($this->sSQLLoadEntry, array($nID, $this->sObjectID), false);
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





    function updateAll ($nID, $aData, $aGeneFields = array())
    {
        // Edit all VariantOnTranscript entries.
        // FIXME; We need a cleaner solution than globalizing zData.
        global $zData, $_AUTH, $_DB;

        $nAffected = 0;
        foreach ($this->aTranscripts as $nTranscriptID => $aTranscript) {
            // Each gene has different fields of course.
            foreach ($aGeneFields[$aTranscript[1]] as $sField) {
                if (strpos($sField, '/')) {
                    $aData[$sField] = $aData[$nTranscriptID . '_' . $sField];
                }
            }
            $aData['effectid'] = $aData[$nTranscriptID . '_effect_reported'] . ($_AUTH['level'] >= LEVEL_CURATOR? $aData[$nTranscriptID . '_effect_concluded'] : $zData[$nTranscriptID . '_effectid']{1});
            $aData['position_c_start'] = $aData[$nTranscriptID . '_position_c_start'];
            $aData['position_c_start_intron'] = $aData[$nTranscriptID . '_position_c_start_intron'];
            $aData['position_c_end'] = $aData[$nTranscriptID . '_position_c_end'];
            $aData['position_c_end_intron'] = $aData[$nTranscriptID . '_position_c_end_intron'];

            // Updates entry $nID with data from $aData in the database, changing only fields defined in $aFields.
            if (!trim($nID)) {
                lovd_displayError('LOVD-Lib', 'Objects::(' . $this->sObject . ')::updateEntry() - Method didn\'t receive ID');
            } elseif (!is_array($aData) || !count($aData)) {
                lovd_displayError('LOVD-Lib', 'Objects::(' . $this->sObject . ')::updateEntry() - Method didn\'t receive data array');
            } elseif (!is_array($aGeneFields[$aTranscript[1]]) || !count($aGeneFields[$aTranscript[1]])) {
                $aGeneFields[$aTranscript[1]] = array_keys($aData);
            }

            // Query text.
            $sSQL = 'UPDATE ' . constant($this->sTable) . ' SET ';
            $aSQL = array();
            foreach ($aGeneFields[$aTranscript[1]] as $key => $sField) {
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

        // Before passing this on to parent::viewEntry(), perform a standard getCount() check on the transcript ID,
        // to make sure that we won't get a query error when the combination of VariantID/TranscriptID does not yield
        // any results. Easiest is then to fake a wrong $nID such that parent::viewEntry() will complain.
        if (!$_DB->query('SELECT COUNT(*) FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' WHERE id = ? AND transcriptid = ?', array($nID, $nTranscriptID))->fetchColumn()) {
            $nID = -1;
        }
        parent::viewEntry($nID);
    }
}
?>
