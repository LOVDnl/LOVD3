<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2021-09-21
 * Modified    : 2021-10-01
 * For LOVD    : 3.5-pre-02
 *
 * Copyright   : 2004-2021 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               L. Werkman <L.Werkman@LUMC.nl>
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



class LOVD_GenomeBuild extends LOVD_Object
{
    // This class extends the basic Object class and it handles the Genome Builds.
    var $sObject = 'Genome_Build';
    var $sTable = 'TABLE_GENOME_BUILDS';





    function __construct ()
    {
        // Default constructor.

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'gb.*, u.name AS created_by_';
        $this->aSQLViewEntry['FROM']     = TABLE_GENOME_BUILDS . ' AS gb ' .
            'LEFT OUTER JOIN ' . TABLE_USERS . ' AS u ON (gb.created_by = u.id) ';
        $this->aSQLViewEntry['GROUP_BY'] = 'gb.id';

        // SQL code for viewing a list of entries.
        $this->aSQLViewList['SELECT']   = 'gb.*, u.name AS created_by_';
        $this->aSQLViewList['FROM']     = TABLE_GENOME_BUILDS . ' AS gb LEFT OUTER JOIN ' . TABLE_USERS . ' AS u ' .
                                          'ON (gb.created_by = u.id)';
        $this->aSQLViewList['ORDER_BY'] = 'id';

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry =
            array(
                'id' => 'Genome build ID',
                'name' => 'Genome build name',
                'column_suffix' => 'Column suffix',
                'percentage_mapped' => 'Variants mapped on this build',
                'created_by_' => 'Created by',
                'created_date_' => 'Date created',
            );

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList =
                 array(
                        'id' => array(
                                    'view' => array('Genome build ID', 130),
                                    'db'   => array('gb.id', 'ASC', true)),
                        'name' => array(
                                    'view' => array('Name', 160),
                                    'db'   => array('gb.name', 'ASC', true)),
                         'column_suffix' => array(
                                     'view' => array('Column suffix', 120),
                                     'db'   => array('gb.column_suffix', 'ASC', true)),
                         'created_by_' => array(
                                     'view' => array('Created by', 160),
                                     'db'   => array('u.name', 'ASC', true)),
                        'created_date_' => array(
                                    'view' => array('Date created', 100),
                                    'db'   => array('gb.created_date', 'DESC', true))
                      );
        $this->sSortDefault = 'id';

        parent::__construct();
    }





    function prepareData ($zData = '', $sView = 'list')
    {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.
        global $_DB;

        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        if ($sView == 'list') {
            $zData['created_date_'] = substr($zData['created_date'], 0, 10);

        } elseif ($sView == 'entry') {
            // Check how many variants have been mapped to this genome build, to show its "completeness".
            $sDNAColumn = 'VariantOnGenome/DNA' . (!$zData['column_suffix']? '' : '/') . $zData['column_suffix'];
            $nPercentComplete = (int) $_DB->query(
                'SELECT ROUND(
                   (SELECT COUNT(*) FROM ' . TABLE_VARIANTS . '
                    WHERE `' . $sDNAColumn . '` IS NOT NULL AND
                      `' . $sDNAColumn . '` != "") / (
                    SELECT COUNT(*) FROM ' . TABLE_VARIANTS . ') * 100)')->fetchColumn();

            $zData['percentage_mapped'] =
                '      <TABLE border="0" cellpadding="0" cellspacing="0" width="200">' .
                '        <TR>' .
                '          <TD width="200" style="border : 1px solid black; padding : 0px; height : 10px">' .
                '            <IMG src="gfx/trans.png" alt="" width="' . $nPercentComplete .
                              '%" height="11" style="background : #224488;"></TD>' .
                '          <TD style="font-size : 11px">' . $nPercentComplete . '%</TD>' .
                '        </TR>' .
                '      </TABLE>';
        }
        return $zData;
    }
}
?>