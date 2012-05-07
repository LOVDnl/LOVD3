<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-02-01
 * Modified    : 2012-05-07
 * For LOVD    : 3.0-beta-05
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

define('ROOT_PATH', '../');
require ROOT_PATH . 'inc-init.php';

// Require manager clearance.
if (!$_AUTH || $_AUTH['level'] < LEVEL_MANAGER) {
    // If not authorized, die with error message.
    die(AJAX_NO_AUTH);
}

// Delete log entry, if available.
if (!empty($_GET['id'])) {
    // The easiest thing to do is just run the query, and check if there is an effect.
    if ($_GET['id'] == 'selected') {
        $aIDs = $_SESSION['viewlists']['Logs']['checked'];
    } else {
        $aIDs = array($_GET['id']);
    }
    $nDeleted = 0;
    foreach ($aIDs as $key => $sID) {
        $aDel = explode(',', $sID);
        if (count($aDel) == 3) {
            $q = $_DB->query('DELETE FROM ' . TABLE_LOGS . ' WHERE name = ? AND date = ? AND mtime = ?', $aDel, false);
            if ($q && $q->rowCount()) {
                $nDeleted ++;
                if ($_GET['id'] == 'selected') {
                    unset($_SESSION['viewlists']['Logs']['checked'][$key]); // To clean up.
                }
            }
        }
    }
    die((string) ($nDeleted > 0) . ' ' . $nDeleted);
}
?>
