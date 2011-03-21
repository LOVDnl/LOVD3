<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-12-20
 * Modified    : 2011-03-18
 * For LOVD    : 3.0-pre-19
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





class LOVD_Variant extends LOVD_Object {
    // This class extends the basic Object class and it handles the Link object.
    var $sObject = 'Variant';





    function LOVD_Variant ()
    {
        // Default constructor.
        global $_AUTH, $_PATH_ELEMENTS;

        $sPage = $_PATH_ELEMENTS[0];
        // SQL code for loading an entry for an edit form.
        //$this->sSQLLoadEntry = 'SELECT d.*, COUNT(p2v.variantid) AS variants FROM ' . TABLE_DBS . ' AS d LEFT OUTER JOIN ' . TABLE_PAT2VAR . ' AS p2v USING (id)';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'v.*, uc.name AS created_by_, ue.name AS edited_by_, count(vot.transcriptid) AS transcripts';
        $this->aSQLViewEntry['FROM']     = TABLE_VARIANTS . ' AS v LEFT JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) LEFT JOIN ' . TABLE_USERS . ' AS uc ON (v.created_by = uc.id) LEFT JOIN ' . TABLE_USERS . ' AS ue ON (v.edited_by = ue.id)';
//        $this->aSQLViewEntry['GROUP_BY'] = 'v.id';

        // SQL code for viewing the list of variants
        if ($sPage == 'variants' || $sPage == 'transcripts') {
            $this->aSQLViewList['SELECT']   = 'v.*, vot.transcriptid';
            $this->aSQLViewList['FROM']     = TABLE_VARIANTS . ' AS v LEFT JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (v.id = vot.id)';
            $this->aSQLViewList['GROUP_BY'] = 'v.id';
        } elseif ($sPage == 'screenings') {
			$this->aSQLViewList['SELECT']   = 'v.*, s.id';
            $this->aSQLViewList['FROM']     = TABLE_VARIANTS . ' AS v LEFT JOIN ' . TABLE_SCR2VAR . ' AS sv ON (v.id = sv.variantid) LEFT JOIN ' . TABLE_SCREENINGS . ' AS s ON (sv.screeningid = s.id)';
            $this->aSQLViewList['GROUP_BY'] = 'v.id';
		} elseif ($sPage == 'patients') {
            $this->aSQLViewList['SELECT']   = 'v.*, s.patientid';
            $this->aSQLViewList['FROM']     = TABLE_VARIANTS . ' AS v LEFT JOIN ' . TABLE_SCR2VAR . ' AS sv ON (v.id = sv.variantid) LEFT JOIN ' . TABLE_SCREENINGS . ' AS s ON (sv.screeningid = s.id)';
            $this->aSQLViewList['GROUP_BY'] = 'v.id';
        }
        

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry =
                 array(
                        'patientid' => 'Patient ID',
                        'allele' => 'Allele',
                        'pathogenicid' => 'Pathogenicity',
                        'chromosome' => 'Chromosome',
                        'position_g_start' => 'Genomic start position',
                        'position_g_end' => 'Genomic end position',
                        'type' => 'Type',
                        'statusid' => 'Status',
                        'created_by_' => 'Created by',
                        'created_date' => 'Date created',
                        'edited_by_' => 'Last edited by',
                        'valid_from' => 'Date last edited',
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
                        'patientid' => array(
                                    'view' => array('Patient ID', 80),
                                    'db'   => array('s.patientid', 'ASC', true)),
                        'transcriptid' => array(
                                    'view' => array('Transcript ID', 80),
                                    'db'   => array('vot.transcriptid', false, true)),
                        'screeningid' => array(
                                    'view' => array('Screening ID', 100),
                                    'db'   => array('s.id', 'ASC', true)),
                        'id' => array(
                                    'view' => array('Variant ID', 90),
                                    'db'   => array('v.id', 'ASC', true)),
                        'allele' => array(
                                    'view' => array('Allele', 100),
                                    'db'   => array('v.allele', 'ASC', true)),
                        'pathogenicid' => array(
                                    'view' => array('Pathogenicity', 100),
                                    'db'   => array('v.pathogenicid', 'ASC', true)),
                        'type' => array(
                                    'view' => array('Type', 70),
                                    'db'   => array('v.type', 'ASC', true)),
                      );
        
        if ($sPage == 'variants' || $sPage == 'transcripts') {
            unset($this->aColumnsViewList['patientid']);
            unset($this->aColumnsViewList['screeningid']);
        } elseif ($sPage == 'patients') {
            unset($this->aColumnsViewList['transcriptid']);
            unset($this->aColumnsViewList['screeningid']);
        } elseif ($sPage == 'screenings') {
			unset($this->aColumnsViewList['patientid']);
			unset($this->aColumnsViewList['transcriptid']);
		}
        $this->sSortDefault = 'id';

        parent::LOVD_Object();
    }




    /*
    function checkFields ($aData)
    {
        // Checks fields before submission of data.
        if (ACTION == 'edit') {
            global $zData; // FIXME; this could be done more elegantly.
        }

        // Mandatory fields.
        $this->aCheckMandatory =
                 array(
                        'symbol',
                        'name',
                      );
        parent::checkFields($aData);

        // Disease symbol must be unique.
        if (!empty($aData['symbol'])) {
            // Enforced in the table, but we want to handle this gracefully.
            $sSQL = 'SELECT id FROM ' . TABLE_GENES . ' WHERE symbol = ?';
            $aSQL = array($aData['symbol']);
            if (ACTION == 'edit') {
                $sSQL .= ' AND id != ?';
                $aSQL[] = $zData['id'];
            }
            if (mysql_num_rows(lovd_queryDB($sSQL, $aSQL))) {
                lovd_errorAdd('name', 'There is already a gene entry with this abbreviation. Please choose another one.');
            }
        }

        // XSS attack prevention. Deny input of HTML.
        lovd_checkXSS();
    }
    */



    /*
    function getForm ()
    {
        // Build the form.

        // Get list of diseases, to connect gene to disease.
        $aData = array();
        $qData = lovd_queryDB('SELECT id, CONCAT(id, " (", name, ")") FROM ' . TABLE_DISEASES . ' ORDER BY id');
        $nData = mysql_num_rows($qData);
        $nFieldSize = ($nData < 20? $nData : 20);
        while ($r = mysql_fetch_row($qData)) {
            $aData[$r[0]] = $r[1];
        }

        // Array which will make up the form table.
        $this->aFormData =
                 array(
                        array('POST', '', '', '', '50%', '14', '50%'),
                        array('', '', 'print', '<B>Gene information</B>'),
                        array('Gene abbreviation', '', 'text', 'symbol', 15),
                        array('Gene name', '', 'text', 'name', 40),
                        array('OMIM ID', '', 'text', 'id_omim', 10),
                        'skip',
                        array('', '', 'print', '<B>Relation to diseases</B>'),
                        array('This gene has been linked to these diseases', '', 'select', 'active_genes', $nFieldSize, $aData, false, true, false),
                  );

        return parent::getForm();
    }
    */




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
            //$zData['geneid'] = '<A href="' . $zData['row_link'] . '" class="hide">' . $zData['geneid'] . '</A>';
        }

        return $zData;
    }

}
?>
