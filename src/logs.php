<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-01-26
 * Modified    : 2012-05-07
 * For LOVD    : 3.0-beta-05
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
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

define('ROOT_PATH', './');
require ROOT_PATH . 'inc-init.php';

if ($_AUTH) {
    // If authorized, check for updates.
    require ROOT_PATH . 'inc-upgrade.php';
}

// URL: /logs
// View all log entries.

// Require manager clearance.
lovd_requireAUTH(LEVEL_MANAGER);

define('PAGE_TITLE', 'View system log entries');
$_T->printHeader();
$_T->printTitle();





require ROOT_PATH . 'class/object_logs.php';
lovd_includeJS('inc-js-logs.php');
$_DATA = new LOVD_Log();

// Define menu, to delete multiple logs in one go.
print('      <UL id="viewlistMenu_Logs" class="jeegoocontext jeegooviewlist">' . "\n" .
      '        <LI class="icon"><A click="lovd_AJAX_viewListSubmit(\'Logs\', function(){$.get(\'ajax/delete_log.php?id=selected\', function(sResponse){if(sResponse.substring(0,1) == \'1\'){alert(\'Successfully deleted \' + sResponse.substring(2) + \' log entries.\');lovd_AJAX_viewListSubmit(\'Logs\');}}).error(function(){alert(\'Log entries could not be deleted.\');});});"><SPAN class="icon" style="background-image: url(gfx/cross.png);"></SPAN>Delete selected entries</A></LI>' . "\n" .
      '      </UL>' . "\n\n");
$_DATA->viewList('Logs', array(), false, false, true); // Don't change viewListID, the log's prepareData() and ajax/delete_log.php are referring to it.

$_T->printFooter();
?>
