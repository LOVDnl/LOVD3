<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-07-28
 * Modified    : 2011-05-04
 * For LOVD    : 3.0-pre-20
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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





class LOVD_Disease extends LOVD_Object {
    // This class extends the basic Object class and it handles the Link object.
    var $sObject = 'Disease';





    function LOVD_Disease ()
    {
        // Default constructor.

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = 'SELECT d.*, ' .
                               'GROUP_CONCAT(g2d.geneid ORDER BY g2d.geneid SEPARATOR ";") AS active_genes_ ' .
                               'FROM ' . TABLE_DISEASES . ' AS d ' .
                               'LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (d.id = g2d.diseaseid) ' .
                               'WHERE d.id = ? ' .
                               'GROUP BY d.id';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'd.*, ' .
                                           'GROUP_CONCAT(DISTINCT g.id, ";", g.id_omim, ";", g.name ORDER BY g.id SEPARATOR ";;") AS genes, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_';
        $this->aSQLViewEntry['FROM']     = TABLE_DISEASES . ' AS d ' .
                                           'LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (d.id = g2d.diseaseid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_GENES . ' AS g ON (g.id = g2d.geneid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (d.created_by = uc.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (d.edited_by = ue.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 'd.id';

        // SQL code for viewing a list of entries.
        $this->aSQLViewList['SELECT']   = 'd.*, ' .
                                          'd.id AS diseaseid, ' .
                                          'GROUP_CONCAT(g2d.geneid ORDER BY g2d.geneid SEPARATOR ", ") AS genes_';
        $this->aSQLViewList['FROM']     = TABLE_DISEASES . ' AS d ' .
                                          'LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (d.id = g2d.diseaseid)';
        $this->aSQLViewList['GROUP_BY'] = 'd.id';

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry =
                 array(
                        'symbol' => 'Official abbreviation',
                        'name' => 'Name',
                        'id_omim' => 'OMIM ID',
                        'genes_' => 'Associated with genes',
                        'created_by_' => array('Created by', LEVEL_COLLABORATOR),
                        'created_date_' => array('Date created', LEVEL_COLLABORATOR),
                        'edited_by_' => array('Last edited by', LEVEL_COLLABORATOR),
                        'edited_date_' => array('Date last edited', LEVEL_COLLABORATOR),
                      );

        // Because the disease information is publicly available, remove some columns for the public.
        $this->unsetColsByAuthLevel();

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList =
                 array(
                        'diseaseid' => array(
                                    'view' => array('ID', 45),
                                    'db'   => array('d.id', 'ASC', true)),
                        'symbol' => array(
                                    'view' => array('Abbreviation', 110),
                                    'db'   => array('d.symbol', 'ASC', true)),
                        'name' => array(
                                    'view' => array('Name', 300),
                                    'db'   => array('d.name', 'ASC', true)),
                        'id_omim' => array(
                                    'view' => array('OMIM ID', 75),
                                    'db'   => array('d.id_omim', 'ASC', true)),
                        'genes_' => array(
                                    'view' => array('Associated with genes', 200),
                                    'db'   => array('genes_', false, 'TEXT')),
                      );
        $this->sSortDefault = 'symbol';

        parent::LOVD_Object();
    }





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

        // FIXME; eerst een concat om daarna te exploden???
        $qGenes = lovd_queryDB('SELECT GROUP_CONCAT(DISTINCT id) AS genes FROM ' . TABLE_GENES);
        $aGenes = mysql_fetch_row($qGenes);
        $aGenes = explode(',', $aGenes[0]);
        // FIXME; ik denk dat de query naar binnen deze if moet.
        // FIXME; misschien heb je geen query nodig en kun je via de getForm() data ook bij de lijst komen.
        //   De parent checkFields vraagt de getForm() namelijk al op.
        if (isset($aData['active_genes'])) {
            // FIXME; zou er een check op moeten, of dit wel een array is?
            foreach ($aData['active_genes'] as $sGene) {
                if (!in_array($sGene, $aGenes)) {
                    // FIXME; kunnen we van deze None af?
                    if ($sGene != 'None') {
                        // FIXME; een if binnen een if kan ook in één if.
                        // FIXME; ik stel voor hiervan te maken "value ' . htmlspecialchars($sGene) . ' does not exist" of zoiets.
                        // FIXME; probeer de naam van het veld via het formulier te achterhalen.
                        lovd_errorAdd('active_genes', 'Please select a proper gene in the \'This disease has been linked to these genes\' selection box');
                    }
                }
            }
        }

        // XSS attack prevention. Deny input of HTML.
        lovd_checkXSS();
    }





    function getForm ()
    {
        // Build the form.

        // Get list of genes, to connect disease to gene.
        $aGenesForm = array();
        $qData = lovd_queryDB('SELECT id, CONCAT(id, " (", name, ")") FROM ' . TABLE_GENES . ' ORDER BY id');
        $nData = mysql_num_rows($qData);
        // FIXME; aangezien $aGenesForm leeg zal zijn als $nData 0 is, stel ik voor deze while buiten de if te doen,
        //   dan de if om te draaien. Dan heb je geen else nodig.
        if ($nData) {
            while ($r = mysql_fetch_row($qData)) {
                $aGenesForm[$r[0]] = $r[1];
            }
        } else {
            // FIXME; is het niet makkelijker om hier geen value op te geven ipv "None"? Het is toch geen verplicht veld, dus als ie geselecteerd wordt,
            // wordt de waarde automatisch genegeerd. Nu moest je een uitzondering plaatsen in checkFields() en genes.php.
            $aGenesForm = array('None' => 'No gene entries available');
        }
        $nFieldSize = (count($aGenesForm) < 20? count($aGenesForm) : 20);

        // Array which will make up the form table.
        $this->aFormData =
                 array(
                        array('POST', '', '', '', '50%', '14', '50%'),
                        array('', '', 'print', '<B>Disease information</B>'),
                        array('Disease abbreviation', '', 'text', 'symbol', 15),
                        array('Disease name', '', 'text', 'name', 40),
                        array('OMIM ID', '', 'text', 'id_omim', 10),
                        'skip',
                        array('', '', 'print', '<B>Relation to genes</B>'),
                        array('This disease has been linked to these genes', '', 'select', 'active_genes', $nFieldSize, $aGenesForm, false, true, false),
                  );

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
            $zData['row_link'] = 'diseases/' . rawurlencode($zData['id']);
            $zData['symbol'] = '<A href="' . $zData['row_link'] . '" class="hide">' . $zData['symbol'] . '</A>';
        } else {
            if (!empty($zData['id_omim'])) {
                $zData['id_omim'] = '<A href="' . lovd_getExternalSource('omim', $zData['id_omim'], true) . '" target="_blank">' . $zData['id_omim'] . '</A>';
            }
            $zData['genes_'] = '';
            $zData['genes_omim_'] = '';
            if (!empty($zData['genes'])) {
                $aGenes = explode(';;', $zData['genes']);
                foreach ($aGenes as $sGene) {
                    list($sID, $nOMIMID, $sName) = explode(';', $sGene);
                    $zData['genes_'] .= (!$zData['genes_']? '' : ', ') . '<A href="genes/' . $sID . '">' . $sID . '</A>';
                    $zData['genes_omim_'] .= (!$zData['genes_omim_']? '' : '<BR>') . '<A href="' . lovd_getExternalSource('omim', $nOMIMID, true) . '" target="_blank">' . $sName . ' (' . $sID . ')</A>';
                }
            }
        }

        return $zData;
    }
}
?>
