<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2026-11-21
 * Modified    : 2026-11-23
 * For LOVD    : 3.0-31
 *
 * Copyright   : 2004-2026 Leiden University Medical Center; http://www.LUMC.nl/
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





class LOVD_IP extends LOVD_Object
{
    // This class extends the basic Object class, and it handles the IPs.
    var $sObject = 'IP';





    function __construct ()
    {
        // Default constructor.

        // SQL code for viewing a list of entries.
        $this->aSQLViewList['SELECT']   = '*, ip_start AS row_id, CONCAT("ips/", ip_start, "?edit") AS row_link, IF(ip_start = ip_end, INET_NTOA(ip_start), CONCAT(INET_NTOA(ip_start), " - ", INET_NTOA(ip_end))) AS ip_range';
        $this->aSQLViewList['FROM']     = TABLE_IPS;
        $this->aSQLViewList['ORDER_BY'] = 'ip_start ASC';

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList =
                 array(
                        'ip_start' => array(
                                    'view' => array('ID', 70),
                                    'db'   => array('ip_start', 'ASC', true)),
                        'ip_range' => array(
                                    'view' => array('IP or range', 150),
                                    'db'   => array('ip_range', false, true)),
                        'level' => array(
                                    'view' => array('Level', 60),
                                    'db'   => array('level', 'ASC', true)),
                        'reason' => array(
                                    'view' => array('Reason', 100),
                                    'db'   => array('reason', 'ASC', true)),
                        'annotation' => array(
                                    'view' => array('Annotation', 700),
                                    'db'   => array('annotation', false, true)),
                      );
        $this->sSortDefault = 'ip_start';

        parent::__construct();
    }
}
?>
