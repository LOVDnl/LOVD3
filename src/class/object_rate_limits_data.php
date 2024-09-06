<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2024-09-03
 * Modified    : 2024-09-05
 * For LOVD    : 3.0-31
 *
 * Copyright   : 2004-2024 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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





class LOVD_RateLimitData extends LOVD_Object
{
    // This class extends the basic Object class, and it handles the Rate Limits.
    var $sObject = 'Rate_Limits_Data';
    var $sTable = 'TABLE_RATE_LIMITS_DATA';





    function __construct ()
    {
        // Default constructor.

        // List of columns and order for viewing a list of entries.
        // This is ridiculous. If I don't create an alias for the table and add it to the VL column list definitions,
        //  the query builder assumes I need HAVING rather than WHERE and the query optimizer breaks.
        // As a result, I get NO results AT ALL in the VL when searching for ratelimitid (which is how this VL works).
        $this->aSQLViewList['FROM'] = TABLE_RATE_LIMITS_DATA . ' AS rld';
        $this->aSQLViewList['ORDER_BY'] = 'rld.ratelimitid ASC, rld.hit_date DESC';
        $this->aColumnsViewList =
            array(
                'ratelimitid' => array(
                    'view' => false, // We only use this for filtering.
                    'db'   => array('rld.ratelimitid', 'ASC', true)),
                'ips' => array(
                    'view' => array('IPs', 80),
                    'db'   => array('rld.ips')),
                'user_agents' => array(
                    'view' => array('User agents', 350),
                    'db'   => array('rld.user_agents')),
                'urls' => array(
                    'view' => array('URLs', 150),
                    'db'   => array('rld.urls')),
                'hit_date' => array(
                    'view' => array('Time', 175),
                    'db'   => array('rld.hit_date')),
                'hit_count' => array(
                    'view' => array('Hits', 40, 'style="text-align: right;"'),
                    'db'   => array('rld.hit_count')),
                'reject_count' => array(
                    'view' => array('Rejects', 50, 'style="text-align: right;"'),
                    'db'   => array('rld.reject_count')),
        );
        $this->sSortDefault = 'ratelimitid'; // This still needs to be defined for the query to work... Sigh...

        parent::__construct();
    }





    function prepareData ($zData = '', $sView = 'list')
    {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        $zData['ips'] = implode('<BR>', json_decode(html_entity_decode($zData['ips']), true));
        $zData['user_agents'] = implode('<BR>', json_decode(html_entity_decode($zData['user_agents']), true));
        $zData['urls'] = implode('<BR>', json_decode(html_entity_decode($zData['urls']), true));
        $zData['hit_date'] = date('Y-m-d H:i P (T)', strtotime($zData['hit_date']));

        return $zData;
    }
}
?>
