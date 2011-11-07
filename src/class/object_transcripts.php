<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-12-20
 * Modified    : 2011-11-07
 * For LOVD    : 3.0-alpha-06
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





class LOVD_Transcript extends LOVD_Object {
    // This class extends the basic Object class and it handles the Link object.
    var $sObject = 'Transcript';





    function __construct ()
    {
        // Default constructor.

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = 'SELECT t.* ' .
                               'FROM ' . TABLE_TRANSCRIPTS . ' AS t ' .
                               'WHERE id = ?';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 't.*, ' .
                                           'g.name AS gene_name, ' .
                                           'g.chromosome, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_, ' .
                                           'COUNT(DISTINCT vot.id) AS variants';
        $this->aSQLViewEntry['FROM']     = TABLE_TRANSCRIPTS . ' AS t ' .
                                           'LEFT OUTER JOIN ' . TABLE_GENES . ' AS g ON (t.geneid = g.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (t.created_by = uc.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (t.edited_by = ue.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 't.id';

        // SQL code for viewing the list of transcripts
        $this->aSQLViewList['SELECT']   = 't.*, ' .
                                          'COUNT(DISTINCT vot.id) AS variants';
        $this->aSQLViewList['FROM']     = TABLE_TRANSCRIPTS . ' AS t ' .
                                          'LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid)';
        $this->aSQLViewList['GROUP_BY'] = 't.id';

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry =
                 array(
                        'name' => 'Transcript name',
                        'gene_name_' => 'Gene name',
                        'chromosome' => 'Chromosome',
                        'id_ncbi' => 'Transcript - NCBI ID',
                        'id_ensembl' => 'Transcript - Ensembl ID',
                        'id_protein_ncbi' => 'Protein - NCBI ID',
                        'id_protein_ensembl' => 'Protein - Ensembl ID',
                        'id_protein_uniprot' => 'Protein - Uniprot ID',
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
                        'id_' => array(
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
                        'id_protein_ncbi' => array(
                                    'view' => array('NCBI Protein ID', 120),
                                    'db'   => array('t.id_protein_ncbi', 'ASC', true)),
                        'variants' => array(
                                    'view' => array('Variants', 70),
                                    'db'   => array('variants', 'DESC', 'INT_UNSIGNED')),
                      );
        $this->sSortDefault = 'geneid';

        parent::__construct();
    }





    function checkFields ($aData)
    {
        // Checks fields before submission of data.

        parent::checkFields($aData);

        // XSS attack prevention. Deny input of HTML.
        lovd_checkXSS();
    }





    function getForm ()
    {
        // Build the form.
        
        // Array which will make up the form table.
        $this->aFormData =
                 array(
                           array('POST', '', '', '', '40%', '14', '60%'),
                           array('Transcript Ensembl ID', '', 'text', 'id_ensembl', 10),
                           array('Protein Ensembl ID', '', 'text', 'id_protein_ensembl', 10),
                           array('Protein Uniprot ID', '', 'text', 'id_protein_uniprot', 10),
                           'skip',
                  );
        
        return parent::getForm();
    }





    function prepareData ($zData = '', $sView = 'list')
    {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.

        global $_PATH_ELEMENTS;

        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        if ($sView == 'list') {
            $zData['id_'] = '<A href="' . str_replace('{{ID}}', $zData['id'], $this->sRowLink) . '" class="hide">' . $zData['id'] . '</A>';
        } else {
            $zData['gene_name_'] = '<A href="genes/' . rawurlencode($zData['geneid']) . '">' . $zData['geneid'] . '</A> (' . $zData['gene_name'] . ')';
        }

        return $zData;
    }

}
?>
