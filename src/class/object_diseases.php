<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-07-28
 * Modified    : 2010-12-20
 * For LOVD    : 3.0-pre-12
 *
 * Copyright   : 2004-2010 Leiden University Medical Center; http://www.LUMC.nl/
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





class Disease extends Object {
    // This class extends the basic Object class and it handles the Link object.
    var $sObject = 'Disease';





    function Disease ()
    {
        // Default constructor.
        global $_AUTH;

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = 'SELECT d.*, GROUP_CONCAT(g2d.geneid ORDER BY g2d.geneid SEPARATOR ";") AS active_genes_ FROM ' . TABLE_DISEASES . ' AS d LEFT JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (d.id = g2d.diseaseid) WHERE d.id = ? GROUP BY d.id';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'd.*, GROUP_CONCAT(g2d.geneid ORDER BY g2d.geneid SEPARATOR ", ") AS genes_, uc.name AS created_by_, ue.name AS edited_by_';
        $this->aSQLViewEntry['FROM']     = TABLE_DISEASES . ' AS d LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (d.id = g2d.diseaseid) LEFT JOIN ' . TABLE_USERS . ' AS uc ON (d.created_by = uc.id) LEFT JOIN ' . TABLE_USERS . ' AS ue ON (d.edited_by = ue.id)';
//        $this->aSQLViewEntry['GROUP_BY'] = 'd.id';

        // SQL code for viewing a list of entries.
        $this->aSQLViewList['SELECT']   = 'd.*, GROUP_CONCAT(g2d.geneid ORDER BY g2d.geneid SEPARATOR ", ") AS genes_';
        $this->aSQLViewList['FROM']     = TABLE_DISEASES . ' AS d LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (d.id = g2d.diseaseid)';
        $this->aSQLViewList['GROUP_BY'] = 'd.id';

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry =
                 array(
                        'TableStart_1' => 'General Information',
                        'id' => 'Disease ID',
                        'symbol' => 'Official abbreviation',
                        'name' => 'Name',
                        'id_omim' => 'OMIM ID',
                        'genes_' => 'Associated with genes',
                        'created_by_' => 'Created by',
                        'created_date' => 'Date created',
                        'edited_by_' => 'Last edited by',
                        'edited_date' => 'Date last edited',
                        'TableEnd_1' => '',
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
                                    'db'   => array('genes_', false, true)),
                      );
        $this->sSortDefault = 'symbol';

        parent::Object();
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

        // Disease symbol must be unique.
        if (!empty($aData['symbol'])) {
            // Enforced in the table, but we want to handle this gracefully.
            $sSQL = 'SELECT id FROM ' . TABLE_DISEASES . ' WHERE symbol = ?';
            $aSQL = array($aData['symbol']);
            if (ACTION == 'edit') {
                $sSQL .= ' AND id != ?';
                $aSQL[] = $zData['id'];
            }
            if (mysql_num_rows(lovd_queryDB($sSQL, $aSQL))) {
                lovd_errorAdd('name', 'There is already a disease entry with this abbreviation. Please choose another one.');
            }
        }

        // XSS attack prevention. Deny input of HTML.
        lovd_checkXSS();
    }





    function getForm ()
    {
        // Build the form.

        // Get list of genes, to connect disease to gene.
        $aData = array();
        $qData = mysql_query('SELECT id, CONCAT(id, " (", name, ")") FROM ' . TABLE_GENES . ' ORDER BY id');
        $nData = mysql_num_rows($qData);
        $nFieldSize = ($nData < 20? $nData : 20);
        while ($r = mysql_fetch_row($qData)) {
            $aData[$r[0]] = $r[1];
        }

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
                        array('This disease has been linked to these genes', '', 'select', 'active_genes', $nFieldSize, $aData, false, true, false),
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
        }

        return $zData;
    }
}
?>