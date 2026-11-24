<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2026-11-21
 * Modified    : 2026-11-24
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





    function checkFields ($aData, $zData = false, $aOptions = array())
    {
        // Checks fields before submission of data.

        // Mandatory fields.
        $this->aCheckMandatory = array(
            'name',
            'level',
        );
        parent::checkFields($aData, $zData, $aOptions);

        if (!empty($aData['name'])) {
            // First, if we're ending with a dot, assume we mean ".*". That will simplify things for us.
            if (str_ends_with($aData['name'], '.')) {
                $_POST['name'] = $aData['name'] .= '*';
            }
            if (!preg_match('/([0-9]+\.){1,3}([0-9]+|\*)$/', $aData['name'])) {
                lovd_errorAdd('name', 'Please double-check the IP format; it does not follow any of the allowed formats.');
            } else {
                $aParts = explode('.', $aData['name']);
                foreach ($aParts as $sPart) {
                    if (!($sPart == '*' || $sPart <= 255)) {
                        lovd_errorAdd('name', 'Please double-check the IP format; it does not follow any of the allowed formats.');
                        break;
                    }
                }
            }
        }

        // XSS attack prevention. Deny input of HTML.
        lovd_checkXSS($aData);
    }





    function getForm ()
    {
        // Build the form.

        // If we've built the form before, simply return it. Especially imports will repeatedly call checkFields(), which calls getForm().
        if (!empty($this->aFormData)) {
            return parent::getForm();
        }

        $aLevels = [
            0 => '0: Whitelist',
            1 => '1: Soft-block (requires login)',
        ];

        // Array which will make up the form table.
        $this->aFormData = array(
            array('POST', '', '', '', '35%', '14', '65%'),
  'name' => array('IP entry', 'Formats: 1.2.3.4, 1.2.3.*, 1.2.*, or 1.*', 'text', 'name', 20),
            array('Level', '', 'select', 'level', 1, $aLevels, false, false, false),
            array('Reason', '', 'text', 'reason', 30),
            array('Annotation', '', 'text', 'annotation', 50),
        );

        return parent::getForm();
    }





    function setDefaultValues ()
    {
        // Sets default values of fields in $_POST.
        $_POST['level'] = 1;
        return true;
    }
}
?>
