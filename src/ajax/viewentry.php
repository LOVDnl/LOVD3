<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-11-09
 * Modified    : 2016-11-18
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               M. Kroon <m.kroon@lumc.nl>
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

// Get viewentry-identifying arguments from request and check their validity.
$sObject = (isset($_REQUEST['object'])? $_REQUEST['object'] : '');
$sObjectID = (isset($_REQUEST['object_id'])? $_REQUEST['object_id'] : '');
$nID = (isset($_REQUEST['id'])? $_REQUEST['id'] : '');

if (empty($nID) || empty($sObject) || !preg_match('/^[A-Z_]+$/i', $sObject)) {
    die(AJAX_DATA_ERROR);
}

// The required security to load the viewEntry() depends on the data that is shown.
// To prevent security problems if we forget to set a requirement here, we default to LEVEL_ADMIN.
$aNeededLevel =
         array(
                'Transcript_Variant' => 0,
                'User' => LEVEL_OWNER,
              );

if (isset($aNeededLevel[$sObject])) {
    $nNeededLevel = $aNeededLevel[$sObject];
} else {
    $nNeededLevel = LEVEL_ADMIN;
}

// Call isAuthorized() on the object. NB: isAuthorized() modifies the global
// $_AUTH for curators, owners and colleagues.
if ($sObject == 'Transcript_Variant') {
    list($nVariantID, $nTranscriptID) = explode(',', $nID);
    lovd_isAuthorized('variant', $nVariantID);
} elseif ($sObject == 'User') {
    lovd_isAuthorized(strtolower($sObject), $nID);
    // Users viewing their own profile should see a lot more...
    if ($_AUTH['id'] == $nID && $_AUTH['level'] < LEVEL_CURATOR) {
        $_AUTH['level'] = LEVEL_CURATOR;
    }
}
// FIXME; other lovd_isAuthorized() calls?

// Require special clearance?
if ($nNeededLevel && (!$_AUTH || $_AUTH['level'] < $nNeededLevel)) {
    // If not authorized, die with error message.
    die(AJAX_NO_AUTH);
}

if (FORMAT == 'text/plain' && !defined('FORMAT_ALLOW_TEXTPLAIN')) {
    die(AJAX_NO_AUTH);
}

$sFile = ROOT_PATH . 'class/object_' . strtolower($sObject) . 's.php';

if (!file_exists($sFile)) {
    header('HTTP/1.0 404 Not Found');
    exit;
}



if (in_array($sObject, array('Phenotype', 'Transcript_Variant', 'Custom_ViewList'))) {
    // Exception for VOT viewEntry, we need to isolate the gene from the ID to correctly pass this to the data object.
    if ($sObject == 'Transcript_Variant') {
        // This line below is redundant as long as it's also called at the lovd_isAuthorized() call. Remove it here maybe...?
        list($nVariantID, $nTranscriptID) = explode(',', $nID);
        $sObjectID = $_DB->query('SELECT geneid FROM ' . TABLE_TRANSCRIPTS . ' WHERE id = ?', array($nTranscriptID))->fetchColumn();
    }
}
require $sFile;
$sObjectClassName = 'LOVD_' . str_replace('_', '', $sObject);
$_DATA = new $sObjectClassName($sObjectID);
$_DATA->viewEntry($nID);
?>
