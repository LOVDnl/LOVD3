<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-12-20
 * Modified    : 2011-05-17
 * For LOVD    : 3.0-pre-21
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
require_once ROOT_PATH . 'class/object_custom.php';





class LOVD_GenomeVariant extends LOVD_Custom {
    // This class extends the basic Object class and it handles the Link object.
    var $sObject = 'Genome_Variant';
    var $sCategory = 'VariantOnGenome';
    var $sTable = 'TABLE_VARIANTS';
    var $bShared = false;





    function LOVD_GenomeVariant ()
    {
        // Default constructor.

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = 'SELECT vog.*, ' .
                               'FROM ' . TABLE_VARIANTS . ' AS vog ' .
                               'WHERE vog.id=? ' .
                               'GROUP BY=vog.id';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'vog.*, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_';
        $this->aSQLViewEntry['FROM']     = TABLE_VARIANTS . ' AS vog ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (vog.created_by = uc.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (vog.edited_by = ue.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 'vog.id';

        // SQL code for viewing the list of variants
        // FIXME: we should implement this in a different way
        $this->aSQLViewList['SELECT']   = 'vog.*, ' .
                                          'GROUP_CONCAT(s2v.screeningid SEPARATOR ",") AS screeningids';
        $this->aSQLViewList['FROM']     = TABLE_VARIANTS . ' AS vog ' .
                                          'LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid)';
        $this->aSQLViewList['GROUP_BY'] = 'vog.id';

        parent::LOVD_Custom();
        
        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry = array_merge(
                 $this->buildViewEntry(),
                 array(
                        'allele' => 'Allele',
                        'pathogenicid' => 'Pathogenicity',
                        'chromosome' => 'Chromosome',
                        'position_g_start' => 'Genomic start position',
                        'position_g_end' => 'Genomic end position',
                        'type' => 'Type',
                        'statusid' => 'Status',
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
                        'screeningids' => array(
                                    'view' => array('Screening ID', 90),
                                    'db'   => array('screeningids', 'ASC', 'TEXT')),
                        'id' => array(
                                    'view' => array('Variant ID', 90),
                                    'db'   => array('vog.id', 'ASC', true))
                      ),
                 $this->buildViewList(),
                 array(
                        'allele' => array(
                                    'view' => array('Allele', 100),
                                    'db'   => array('vog.allele', 'ASC', true)),
                        'pathogenicid' => array(
                                    'view' => array('Pathogenicity', 110),
                                    'db'   => array('vog.pathogenicid', 'ASC', true)),
                        'type' => array(
                                    'view' => array('Type', 70),
                                    'db'   => array('vog.type', 'ASC', true)),
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
        // STUB
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
