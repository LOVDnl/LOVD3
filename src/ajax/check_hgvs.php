<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-09-06
 * Modified    : 2018-01-19
 * For LOVD    : 3.0-21
 *
 * Copyright   : 2004-2018 Leiden University Medical Center; http://www.LUMC.nl/
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

define('ROOT_PATH', '../');
session_cache_limiter('public'); // Stops the session from sending any cache or no-cache headers. Alternative: ini_set() session.cache_limiter.
require ROOT_PATH . 'inc-init.php';
header('Expires: ' . date('r', time()+(24*60*60))); // HGVS syntax check result expires in a day.
session_write_close();

if (empty($_GET['variant']) || !preg_match('/^(c:[cn]|g:[mg])\..+$/', $_GET['variant'])) {
    die(AJAX_DATA_ERROR);
}

// Take the c. or g. off.
$_GET['variant'] = substr($_GET['variant'], 2);

// Requires at least LEVEL_SUBMITTER, anything lower has no $_AUTH whatsoever.
if (!$_AUTH) {
    // If not authorized, die with error message.
    die(AJAX_NO_AUTH);
}

$aResponse = lovd_callMutalyzer('checkSyntax', array('variant' => $_GET['variant']));
if ($aResponse === false) {
    die(AJAX_UNKNOWN_RESPONSE);
}

if (!empty($aResponse['valid'])) {
    die(AJAX_TRUE);
} else {
    die(AJAX_FALSE);
}
?>
