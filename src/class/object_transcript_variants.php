<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-05-12
 * Modified    : 2011-11-11
 * For LOVD    : 3.0-alpha-06
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = 'SELECT vot.* ' .
                               'FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ' .
                               'LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) ' .
                               'WHERE vot.id = ? ' .
                               'AND t.geneid = ?';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'vot.*, ' .
                                           't.id_ncbi';
        $this->aSQLViewEntry['FROM']     = TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ' .
                                           'LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 'vot.id';

        // SQL code for viewing the list of variants
        // FIXME: we should implement this in a different way
        $this->aSQLViewList['SELECT']   = 'vot.*, ' .
                                          't.id_ncbi, ' .
                                          'ds.name AS status';
        $this->aSQLViewList['FROM']     = TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ' .
                                          'LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS ds ON (vog.statusid = ds.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (t.id = vot.transcriptid)';

        $this->sObjectID = $sObjectID;
        $this->nID = $nID;
        parent::__construct();

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry = array_merge(
                 array(
                        'id_ncbi' => 'Transcript ID',
                      ),
                 $this->buildViewEntry());

        // Because the disease information is publicly available, remove some columns for the public.
        $this->unsetColsByAuthLevel();

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList = array_merge(
                 array(
                        'transcriptid' => array(
                                    'view' => array('Transcript ID', 90),
                                    'db'   => array('vot.transcriptid', 'ASC', true)),
                        'id_ncbi' => array(
                                    'view' => array('Transcript', 120),
                                    'db'   => array('t.id_ncbi', 'ASC', true)),
                        'id_' => array(
                                    'view' => array('Variant ID', 90),
                                    'db'   => array('vot.id', 'ASC', true)),
                      ),
                 $this->buildViewList(),
                 array(
                        'status' => array(
                                    'view' => array('Status', 70),
                                    'db'   => array('ds.name', false, true)),
                      ));

        $this->sSortDefault = 'id_ncbi';
        $qTranscripts = lovd_queryDB_Old('SELECT id, id_ncbi FROM ' . TABLE_TRANSCRIPTS . ' WHERE geneid = ? ORDER BY id_ncbi', array($sObjectID));
        While($r = mysql_fetch_row($qTranscripts)) {
            $this->aTranscripts[$r[0]] = $r[1];
        }

        $this->sRowLink = 'variants/{{ID}}';
    }





    function buildForm ($sPrefix = '') {
        return parent::buildForm($sPrefix);
    }





    function checkFields ($aData)
    {
        // Checks fields before submission of data.
        // Loop through all transcripts to have each transcript's set of columns checked.
        foreach($this->aTranscripts as $nTranscriptID => $sTranscriptNM) {
            foreach ($this->aColumns as $sCol => $aCol) {
                $sCol = $nTranscriptID . '_' . $sCol;
                if ($aCol['mandatory']) {
                    $this->aCheckMandatory[] = $sCol;
                }
                if (isset($aData[$sCol])) {
                    $this->checkInputRegExp($sCol, $aData[$sCol]);
                    $this->checkSelectedInput($sCol, $aData[$sCol]);
                }
            }
        }

        // Bypass LOVD_Custom::checkFields(), since it's functionality has been copied above.
        LOVD_Object::checkFields($aData);

        lovd_checkXSS();
    }





    function getForm ()
    {
        $this->aFormData = array();
        $this->aFormData[] = 'skip';
        foreach($this->aTranscripts as $nTranscriptID => $sTranscriptNM) {
            $this->aFormData = array_merge($this->aFormData, array(array('', '', 'print', '<B>Transcript variant on ' . $sTranscriptNM . '</B>')), array('hr'), $this->buildForm($nTranscriptID . '_'), array('hr'), array('skip'));
        }
        array_pop($this->aFormData);

        return parent::getForm();
    }




 
    function insertAll ($aData, $aFields = array())
    {
        foreach($this->aTranscripts as $nTranscriptID => $sTranscriptNM) {
            foreach($aFields as $sField) {
                if (strpos($sField, '/')) {
                    $aData[$sField] = $aData[$nTranscriptID . '_' . $sField];
                }
            }
            $aData['transcriptid'] = $nTranscriptID;
            LOVD_Object::insertEntry($aData, $aFields);
        }
        return $this->aTranscripts;
    }





    function loadAll ($nID = false)
    {
        // Loads all variantOnTranscript entries from the database.
        if (empty($nID)) {
            // We were called, but the class wasn't initiated with an ID. Fail.
            lovd_displayError('LOVD-Lib', 'Objects::(' . $this->sObject . ')::loadEntry() - Method didn\'t receive ID');
        }

        global $_DB;

        $z = @$_DB->query($this->sSQLLoadEntry, array($nID, $this->sObjectID))->fetchAllAssoc();
        // FIXME; check if $zData['status'] exists, if so, check status versus lovd_isAuthorized().
        // Set $zData to false if user should not see this entry.
        if (!$z) {
            global $_CONF, $_SETT, $_STAT, $_AUTH;

            $sError = mysql_error(); // Save the mysql_error before it disappears.

            // Check if, and which, top include has been used.
            if (!defined('_INC_TOP_INCLUDED_') && !defined('_INC_TOP_CLEAN_INCLUDED_')) {
                if (is_readable(ROOT_PATH . 'inc-top.php')) {
                    require ROOT_PATH . 'inc-top.php';
                } else {
                    require ROOT_PATH . 'inc-top-clean.php';
                }
            }

            if (defined('PAGE_TITLE') && defined('_INC_TOP_INCLUDED_')) {
                lovd_printHeader(PAGE_TITLE);
            }

            if ($sError) {
                lovd_queryError($this->sObject . '::loadEntry()', $sSQL, $sError);
            }

            lovd_showInfoTable('No such ID!', 'stop');

            if (defined('_INC_TOP_INCLUDED_')) {
                require ROOT_PATH . 'inc-bot.php';
            } elseif (defined('_INC_TOP_CLEAN_INCLUDED_')) {
                require ROOT_PATH . 'inc-bot-clean.php';
            }
            exit;
        }

        $zData = array();
        foreach($z as $aVariantOnTranscript) {
            $aVariantOnTranscript = $this->autoExplode($aVariantOnTranscript);
            foreach ($this->aColumns as $sColClean => $aCol) {
                $sCol = $aVariantOnTranscript['transcriptid'] . '_' . $sColClean;
                if ($aCol['form_type'][2] == 'select' && $aCol['form_type'][3] > 1) {
                    $zData[$sCol] = explode(';', $aVariantOnTranscript[$sColClean]);
                } else {
                    $zData[$sCol] = $aVariantOnTranscript[$sColClean];
                }
            }
        }
        return $zData;
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
            // STUB
        }
        
        return $zData;
    }
    
    
    
    
    
    function setDefaultValues ()
    {
        // Initiate default values of fields in $_POST.
        foreach($this->aTranscripts as $nTranscriptID => $sTranscriptNM) {
            foreach ($this->aColumns as $sColClean => $aCol) {
                $sCol = $nTranscriptID . '_' . $sColClean;
                // Fill $_POST with the column's default value.
                $_POST[$sCol] = $this->getDefaultValue($sColClean);
            }
        }
    }
    
    
    
    
    
    function updateAll ($nID, $aData, $aFields = array())
    {
        // Edit all VariantOnTranscript entries.
        foreach($this->aTranscripts as $nTranscriptID => $sTranscriptNM) {
            foreach($aFields as $sField) {
                if (strpos($sField, '/')) {
                    $aData[$sField] = $aData[$nTranscriptID . '_' . $sField];
                }
            }

            // Updates entry $nID with data from $aData in the database, changing only fields defined in $aFields.
            if (!trim($nID)) {
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
                if (substr(lovd_getColumnType(constant($this->sTable), $sField), 0, 3) == 'INT' && $aData[$sField] === '') {
                    $aData[$sField] = NULL;
                }
                $aSQL[] = $aData[$sField];
            }
            $sSQL .= ' WHERE id = ? AND transcriptid = ?';
            $aSQL[] = $nID;
            $aSQL[] = $nTranscriptID;

            $q = lovd_queryDB_Old($sSQL, $aSQL);
            if (!$q) {
                lovd_queryError((defined('LOG_EVENT')? LOG_EVENT : $this->sObject . '::updateEntry()'), $sSQL, mysql_error());
            }

            return mysql_affected_rows();
        }
    }





    function viewEntry ($nID = false) {
        list($nID, $nTranscriptID) = explode(',', $nID);
        $this->aSQLViewEntry['WHERE'] = 'vot.transcriptid = \'' . $nTranscriptID . '\'' . (!empty($this->aSQLViewEntry['WHERE'])? ' AND ' . $this->aSQLViewEntry['WHERE'] : '');
        parent::viewEntry($nID);
    }
}
?>
