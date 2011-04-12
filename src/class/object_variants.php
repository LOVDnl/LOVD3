<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-12-20
 * Modified    : 2011-04-08
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
require_once ROOT_PATH . 'class/object_custom.php';





class LOVD_Variant extends LOVD_Custom {
    // This class extends the basic Object class and it handles the Link object.
    var $sObject = 'Variant';
    var $bShared = false;





    function LOVD_Variant ()
    {
        // Default constructor.
        global $_AUTH;

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = 'SELECT v.*, ' .
                               'FROM ' . TABLE_VARIANTS . ' AS v ' .
                               'WHERE id=? ' .
                               'GROUP BY=v.id';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'v.*, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_, ' .
                                           'count(vot.transcriptid) AS transcripts';
        $this->aSQLViewEntry['FROM']     = TABLE_VARIANTS . ' AS v ' .
                                           'LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (v.created_by = uc.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (v.edited_by = ue.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 'v.id';

        // SQL code for viewing the list of variants
        // FIXME: we should implement this in a different way
        $this->aSQLViewList['SELECT']   = 'v.*, ' .
                                          'vot.transcriptid';
        $this->aSQLViewList['FROM']     = TABLE_VARIANTS . ' AS v ' .
                                          'LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id)';
        $this->aSQLViewList['GROUP_BY'] = 'v.id';

        parent::LOVD_Custom();
        
        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry = array_merge(
                 $this->buildViewEntry(),
                 array(
                        'individualid' => 'Individual ID',
                        'allele' => 'Allele',
                        'pathogenicid' => 'Pathogenicity',
                        'chromosome' => 'Chromosome',
                        'position_g_start' => 'Genomic start position',
                        'position_g_end' => 'Genomic end position',
                        'type' => 'Type',
                        'statusid' => 'Status',
                        'created_by_' => 'Created by',
                        'created_date_' => 'Date created',
                        'edited_by_' => 'Last edited by',
                        'valid_from_' => 'Date edited',
                      ));

        // Because the disease information is publicly available, remove some columns for the public.
        if (!$_AUTH || $_AUTH['level'] < LEVEL_COLLABORATOR) {
            unset($this->aColumnsViewEntry['created_by_']);
            unset($this->aColumnsViewEntry['created_date_']);
            unset($this->aColumnsViewEntry['edited_by_']);
            unset($this->aColumnsViewEntry['valid_from_']);
        }
        
        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewList = array_merge(
                 $this->buildViewList(),
                 array(
                        'transcriptid' => array(
                                    'view' => array('Transcript ID', 90),
                                    'db'   => array('vot.transcriptid', 'ASC', true)),
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
        }
        
        return $zData;
    }

}
?>
