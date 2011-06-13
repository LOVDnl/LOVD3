<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-05-12
 * Modified    : 2011-06-09
 * For LOVD    : 3.0-alpha-02
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





    function LOVD_TranscriptVariant ($sObjectID = '')
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
        $this->aSQLViewList['SELECT']   = 'vot.*';
        $this->aSQLViewList['FROM']     = TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot';

        $this->sObjectID = $sObjectID;
        parent::LOVD_Custom();
        
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
                        'id' => array(
                                    'view' => array('Variant ID', 90),
                                    'db'   => array('vot.id', 'ASC', true)),
                      ),
                 $this->buildViewList(),
                 array(
                      ));
        
        $this->sSortDefault = 'id';
    
        parent::LOVD_Object();
    }




    
    function checkFields ($aData)
    {
        // STUB
        lovd_checkXSS();
    }





    function getForm ()
    {
        $this->aFormData = array_merge(
                 array(
                        array('', '', 'print', '<B>Variant information (regarding the selected transcript)</B>'),
                        'hr',
                        array('', '', 'note', 'TEMPORARILY UNAVAILABLE.'),
                      ),
                 $this->buildViewForm(),
                 array(
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
            $zData['row_link'] = 'variants/' . rawurlencode($zData['id']);
            $zData['id'] = '<A href="' . $zData['row_link'] . '" class="hide">' . $zData['id'] . '</A>';
        }
        
        return $zData;
    }

}
?>
