<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2021-09-21
 * Modified    : 2021-09-22
 * For LOVD    : 3.5-pre-01
 *
 * Copyright   : 2004-2021 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               L. Werkman <l.werkman@lumc.nl>
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
    // This class extends the basic Object class and it handles the GenomeBuilds.
    var $sObject = 'Genome_Build';
    var $sTable = 'TABLE_GENOME_BUILDS';


    function __construct ()
    {
        // Default constructor.

        // SQL code for viewing a list of entries.
        $this->aSQLViewList['SELECT']   = 'gb.*, u.name AS created_by_';
        $this->aSQLViewList['FROM']     = TABLE_GENOME_BUILDS . ' AS gb LEFT OUTER JOIN ' . TABLE_USERS . ' AS u ' .
                                          'ON (gb.created_by = u.id)';
        $this->aSQLViewList['ORDER_BY'] = 'id';

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList =
                 array(
                        'id' => array(
                                    'view' => array('ID', 50),
                                    'db'   => array('gb.id', 'ASC', true)),
                        'name' => array(
                                    'view' => array('name', 50),
                                    'db'   => array('gb.name', 'ASC', true)),
                        'created_date' => array(
                                    'view' => array('created on', 130),
                                    'db'   => array('gb.created_by', 'DESC', 'DATETIME')),
                        'created_by_' => array(
                                    'view' => array('User', 160),
                                    'db'   => array('u.name', 'ASC', true)),
                        'column_suffix' => array(
                                    'view' => array('Column suffix', 100),
                                    'db'   => array('gb.column_suffix', 'ASC', true))
                      );

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'gb.*, u.name AS created_by_';

        $this->aSQLViewEntry['FROM']     = TABLE_GENOME_BUILDS . ' AS gb ' .
            'LEFT OUTER JOIN ' . TABLE_USERS . ' AS u ON (gb.created_by = u.id) ';
        $this->aSQLViewEntry['GROUP_BY'] = 'gb.id';

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry =
            array(
                'TableHeader_General' => 'General information',
                'id' => 'Genome build ID',
                'name' => 'Genome build name',
                'column_suffix' => 'Column suffix',
                'created_by' => 'Created by',
                'created_date' => 'Date created'
            );

        $this->sSortDefault = 'id';

        parent::__construct();
    }
}
?>
