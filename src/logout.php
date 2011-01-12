<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-03-19
 * Modified    : 2011-01-06
 * For LOVD    : 3.0-pre-13
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 * Last edited : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

// Log out of the system.
if (!$_AUTH) {
    header('Location: ' . lovd_getInstallURL());
    exit;
}

@mysql_query('UPDATE ' . TABLE_USERS . ' SET phpsessid = "" WHERE id = "' . $_AUTH['id'] . '"');
$nSec = time() - strtotime($_AUTH['last_login']);
// DMD_SPECIFIC; FIXME; we still need to decide how to store this information.
$sCurrDB = $_SESSION['currdb']; // Temp storage.
$_SESSION = array(); // Delete variables both from $_SESSION and from session file.
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 172800); // 'Delete' the cookie.
}
session_destroy();   // Destroy session, delete the session file.
$_AUTH = false;

// FIXME; Somehow this doesn't work...
session_start(); // Reinitiate... Otherwise the next line will do nothing.
// DMD_SPECIFIC; FIXME; we still need to decide how to store this information.
$_SESSION['currdb'] = $sCurrDB; // Put it back.
header('Refresh: 5; url=' . lovd_getInstallURL());
define('PAGE_TITLE', 'Log out');
require ROOT_PATH . 'inc-top.php';
lovd_printHeader(PAGE_TITLE);

print('      You have been logged out successfully.<BR>' . "\n");

$aTimes =
         array(
                array( 1, 'sec', 'sec'),
                array(60, 'min', 'min'),
                array(60, 'hr', 'hrs'),
                array(24, 'day', 'days'),
              );

foreach ($aTimes as $n => $aTime) {
    if ($n) {
        $aTimes[$n][0] = $aTime[0] * $aTimes[$n-1][0];
    }
}
$aTimes = array_reverse($aTimes);

$sPrint = '';
foreach ($aTimes as $n => $aTime) {
    if ($nSec >= $aTime[0]) {
        $nAmount = floor($nSec / $aTime[0]);
        $nSec = $nSec % $aTime[0];
        $sPrint .= ($sPrint? ', ' : '') . $nAmount . ' ' . ($nAmount == 1? $aTime[1] : $aTime[2]);
    }
}

print('      You\'ve been online for ' . $sPrint . '.' . "\n\n");

require ROOT_PATH . 'inc-bot.php';
?>