<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-12-20
 * Modified    : 2011-02-21
 * For LOVD    : 3.0-pre-17
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
require_once ROOT_PATH . 'class/objects.php';





class LOVD_Transcript extends Object {
    // This class extends the basic Object class and it handles the Link object.
    var $sObject = 'Transcript';





    function LOVD_Transcript ()
    {
        // Default constructor.
        global $_AUTH;

        // SQL code for loading an entry for an edit form.
        //$this->sSQLLoadEntry = 'SELECT d.*, COUNT(p2v.variantid) AS variants FROM ' . TABLE_DBS . ' AS d LEFT OUTER JOIN ' . TABLE_PAT2VAR . ' AS p2v USING (id)';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 't.*, g.name AS genename, g.chromosome, uc.name AS created_by_, ue.name AS edited_by_, count(DISTINCT vot.id) AS variants';
        $this->aSQLViewEntry['FROM']     = TABLE_TRANSCRIPTS . ' AS t LEFT JOIN ' . TABLE_GENES . ' AS g ON (t.geneid = g.id) LEFT JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) LEFT JOIN ' . TABLE_USERS . ' AS uc ON (t.created_by = uc.id) LEFT JOIN ' . TABLE_USERS . ' AS ue ON (t.edited_by = ue.id)';
//        $this->aSQLViewEntry['GROUP_BY'] = 't.id';

        // SQL code for viewing the list of transcripts
         $this->aSQLViewList['SELECT']   = 't.*, count(DISTINCT vot.id) AS variants';
        $this->aSQLViewList['FROM']     = TABLE_TRANSCRIPTS . ' AS t LEFT JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid)';
        $this->aSQLViewList['GROUP_BY'] = 't.id';

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry =
                 array(
                        'name' => 'Transcript name',
                        'genename_' => 'Gene name',
                        'chromosome' => 'Chromosome',
                        'id_ncbi' => 'Transcript - NCBI ID',
                        'id_ensembl' => 'Transcript - Ensembl ID',
                        'id_protein_ncbi' => 'Protein - NCBI ID',
                        'id_protein_ensembl' => 'Protein - Ensembl ID',
                        'id_protein_uniprot' => 'Protein - Uniprot ID',
                        'created_by_' => 'Created by',
                        'created_date' => 'Date created',
                        'edited_by_' => 'Last edited by',
                        'edited_date_' => 'Date last edited',
                      );

        // Because the disease information is publicly available, remove some columns for the public.
        if ($_AUTH && $_AUTH['level'] < LEVEL_COLLABORATOR) {
            unset($this->aColumnsViewEntry['created_by_']);
            unset($this->aColumnsViewEntry['created_date']);
            unset($this->aColumnsViewEntry['edited_by_']);
            unset($this->aColumnsViewEntry['edited_date']);
        }

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList =
                 array(
                        'id' => array(
                                    'view' => array('ID', 70),
                                    'db'   => array('t.id', 'ASC', true)),
                        'geneid' => array(
                                    'view' => array('Gene ID', 70),
                                    'db'   => array('t.geneid', 'ASC', true)),
                        'name' => array(
                                    'view' => array('Name', 300),
                                    'db'   => array('t.name', 'ASC', true)),
                        'id_ncbi' => array(
                                    'view' => array('NCBI ID', 120),
                                    'db'   => array('t.id_ncbi', 'ASC', true)),
                        'variants' => array(
                                    'view' => array('Variants', 70),
                                    'db'   => array('variants', 'ASC', true)),
                      );
        $this->sSortDefault = 'geneid';

        parent::Object();
    }





    function checkFields ($aData)
    {
        // Checks fields before submission of data.
        global $zData; // FIXME; this could be done more elegantly.

        parent::checkFields($aData);

        // Check if transcripts are in the list, so no data manipulation from user!
        foreach ($aData['active_transcripts'] as $sTranscript) {
            if (!in_array($sTranscript, $zData['transcripts']) || in_array($sTranscript, $zData['transcriptsAdded'])) {
                if ($sTranscript != 'None') {
                    return lovd_errorAdd('active_transcripts' ,'Please select a proper transcriptomic reference from the selection box.');
                }
            }
        }

        // XSS attack prevention. Deny input of HTML.
        lovd_checkXSS();
    }





    function getForm ()
    {
        // Build the form.
        global $zData;

        $atranscriptNames = array();
        $aTranscriptsForm = array();
        if (!empty($zData['transcripts'])) {
            foreach ($zData['transcripts'] as $sTranscript) {
                if (!isset($atranscriptNames[preg_replace('/\.\d+/', '', $sTranscript)])) {
                    $aTranscriptsForm[$sTranscript] = $zData['transcriptNames'][preg_replace('/\.\d+/', '', $sTranscript)] . ' (' . $sTranscript . ')';
                }
            }
            asort($aTranscriptsForm);
        } else {
            $aTranscriptsForm = array('None' => 'No transcripts available');
        }
        
        $nTranscriptsFormSize = (count($aTranscriptsForm) < 10? count($aTranscriptsForm) : 10);
        
        // Array which will make up the form table.
        $this->aFormData =
                 array(
                           array('POST', '', '', '', '40%', '14', '60%'),
           'transcript' => array('Transcriptomic reference sequence(s)', '', 'select', 'active_transcripts', $nTranscriptsFormSize, $aTranscriptsForm, false, true, false),
       'transcriptInfo' => array('', '', 'note', 'Select transcript references (NM accession numbers). You can select multiple transcripts by holding "CTRL or CMD" and clicking all transcripts desired.'),
'transcript_ensembl_id' => array('Transcript Ensembl ID', '', 'text', 'id_ensembl', 10),
   'protein_ensembl_id' => array('Protein Ensembl ID', '', 'text', 'id_protein_ensembl', 10),
   'protein_uniprot_id' => array('Protein Uniprot ID', '', 'text', 'id_protein_uniprot', 10),
                           'skip',
                  );
        if (ACTION == 'edit') {
            unset($this->aFormData['transcript']);
            unset($this->aFormData['transcriptInfo']);
        } elseif (ACTION == 'create') {
            unset($this->aFormData['protein_uniprot_id']);
            unset($this->aFormData['protein_ensembl_id']);
            unset($this->aFormData['transcript_ensembl_id']);
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
            $zData['row_link'] = 'transcripts/' . rawurlencode($zData['id']);
            //$zData['geneid'] = '<A href="' . $zData['row_link'] . '" class="hide">' . $zData['geneid'] . '</A>';
        } else {
            $zData['genename_'] = '<A href="genes/' . $zData['geneid'] . '">' . $zData['geneid'] . '</A> (' . $zData['genename'] . ')';
        }
        
        $zData['edited_date_'] = (!empty($zData['edited_date'])? $zData['edited_date'] : 'N/A');
        $zData['edited_by_'] = (!empty($zData['edited_by'])? $zData['edited_by'] : 'N/A');

        return $zData;
    }

}
?>
