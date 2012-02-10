<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-01-26
 * Modified    : 2012-02-10
 * For LOVD    : 3.0-beta-03
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
require ROOT_PATH . 'inc-top.php';
lovd_printHeader(PAGE_TITLE);
//require ROOT_PATH . 'inc-lib-form.php';
require ROOT_PATH . 'class/object_logs.php';



/*
// AJAX???
// Delete, if necessary.
if (isset($_GET['delete'])) {
    // One entry, or a whole lot?
    if (substr_count($_GET['delete'], ',')) {
        // Just one, clicked on a link.
    } else {
        // More entries...
        $sQ = '';
        if (isset($_GET['delete_all']) && $_GET['delete_all']) {
            $sQ = 'DELETE FROM ' . TABLE_LOGS . ' WHERE logname = "' . $_GET['log'] . '"';
        } else {
            if (isset($_GET['del_days']) && is_numeric($_GET['del_days']) && $_GET['del_days'] >= 0) {
                $sDate = date('Y-m-d H:i:s', mktime(date('H'), date('i'), date('s'), date('m'), date('d') - $_GET['del_days'], date('Y')));
                $sQ    = 'DELETE FROM ' . TABLE_LOGS . ' WHERE logname = "' . $_GET['log'] . '" AND date < "' . $sDate . '"';
            }
        }

        if ($sQ) {
            $q = mysql_query($sQ);
            if (!$q) {
                // Non-fatal.
                lovd_dbFout('LogDel', $sQ, mysql_error(), false);
            }
        }
    }
}
*/

/*
// Table for deleting the data.
print('      <TABLE border="0" cellpadding="0" cellspacing="1" width="400" class="data_red">' . "\n" .
      '        <TR>' . "\n" .
      '          <TH colspan="2">Delete log entries</TH></TR>');
// Array which will make up the form table.
$aForm = array(
                array('GET', 'header', 'data', '60%', '40%'),
                array('Delete entries older than', 'print', '<INPUT type="text" name="del_days" size="3" value="' . (isset($_GET['del_days'])? $_GET['del_days'] : '') . '">&nbsp;day(s)'),
                array('Delete all entries in log', 'checkbox', 'delete_all'),
                array('', 'submit', 'Delete', 'delete'),
              );
$_MODULES->processForm('SetupLogsDelete', $aForm);
lovd_viewForm($aForm, "\n" . '        <TR>' . "\n" . '          <TH valign="top" width="{{ WIDTH }}" style="padding-top : 3px;">', '</TH>');
print('</TABLE>' . "\n" .
      '      <SPAN class="S11">Please note that delete commands affect all entries in the ' . $_GET['log'] . ' log, not only the search results.</SPAN><BR>' . "\n\n");
*/

lovd_includeJS('inc-js-logs.php');

$_DATA = new LOVD_Log();
$_DATA->viewList('Logs'); // Setting known viewListID, such that the log's prepareData() can refer to itself.
// FIXME; is there another solution for this?

require ROOT_PATH . 'inc-bot.php';
?>
