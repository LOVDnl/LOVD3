<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-05-12
 * Modified    : 2011-11-07
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





    function __construct ($sObjectID = '')
    {
        // Default constructor.

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = 'SELECT vot.*, ' .
                               'FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ' .
                               'WHERE vot.id=? ' .
                               'GROUP BY=vot.id';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'vot.*, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_';
        $this->aSQLViewEntry['FROM']     = TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (vot.created_by = uc.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (vot.edited_by = ue.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 'vot.id';

        // SQL code for viewing the list of variants
        // FIXME: we should implement this in a different way
        $this->aSQLViewList['SELECT']   = 'vot.*, ' . 
                                          't.id_ncbi';
        $this->aSQLViewList['FROM']     = TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ' .
                                          'LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (t.id=vot.transcriptid)';

        $this->sObjectID = $sObjectID;
        parent::__construct();

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry = array_merge(
                 array(
                        'transcriptid' => 'Transcript ID',
                      ),
                 $this->buildViewEntry(),
                 array(
                        'created_by_' => array('Created by', LEVEL_COLLABORATOR),
                        'created_date_' => array('Date created', LEVEL_COLLABORATOR),
                        'edited_by_' => array('Last edited by', LEVEL_COLLABORATOR),
                        'edited_date_' => array('Date edited', LEVEL_COLLABORATOR),
                      ));

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
                      ));

        $this->sSortDefault = 'id_ncbi';
        $qTranscripts = lovd_queryDB_Old('SELECT id, id_ncbi FROM ' . TABLE_TRANSCRIPTS . ' WHERE geneid=? ORDER BY id_ncbi', array($sObjectID));
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
        unset($this->aFormData[max(array_keys($this->aFormData))]);
        
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
}
?>
