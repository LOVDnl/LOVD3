<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-02-01
 * Modified    : 2011-08-12
 * For LOVD    : 3.0-alpha-04
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
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
    $aDel = explode(',', $_GET['id']);
    if (count($aDel) == 3) {
        lovd_queryDB_Old('DELETE FROM ' . TABLE_LOGS . ' WHERE name = ? AND date = ? AND mtime = ?', $aDel);
        die((string) mysql_affected_rows());
    }
}
?>