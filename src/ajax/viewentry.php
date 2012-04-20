<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-11-09
 * Modified    : 2011-11-15
 * For LOVD    : 3.0-alpha-06
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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

if (empty($_GET['id']) || empty($_GET['object']) || !preg_match('/^[A-Z_]+$/i', $_GET['object'])) {
    die(AJAX_DATA_ERROR);
}

// FIXME; lovd_isAuthorized() has to be implemented somewhere around here when authorization checks are built into viewEntry()

// The required security to load the viewEntry() depends on the data that is shown.
// To prevent security problems if we forget to set a requirement here, we default to LEVEL_ADMIN.
$aNeededLevel =
         array(
                'Transcript_Variant' => 0,
              );

if (isset($aNeededLevel[$_GET['object']])) {
    $nNeededLevel = $aNeededLevel[$_GET['object']];
} else {
    $nNeededLevel = LEVEL_ADMIN;
}

// Require special clearance?
if ($nNeededLevel && (!$_AUTH || $_AUTH['level'] < $nNeededLevel)) {
    // If not authorized, die with error message.
    die(AJAX_NO_AUTH);
}

$sFile = ROOT_PATH . 'class/object_' . strtolower($_GET['object']) . 's.php';

if (!file_exists($sFile)) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

// We assume here that header has been included but we can't see that from here.
// Having a double header & footer when a queryerror shows up, is so ugly, so...
define('_INC_TOP_INCLUDED_', 'ajax');


$sObjectID = '';
$nID = '';
if (in_array($_GET['object'], array('Phenotype', 'Transcript_Variant', 'Custom_ViewList'))) {
    if (isset($_GET['object_id'])) {
        $sObjectID = $_GET['object_id'];
    }
    if (isset($_GET['id'])) {
        $nID = $_GET['id'];
    }

    // Exception for VOT viewEntry, we need to isolate the gene from the ID to correctly pass this to the data object.
    if ($_GET['object'] == 'Transcript_Variant') {
        list($nVariantID, $nTranscriptID) = explode(',', $nID);
        $sObjectID = $_DB->query('SELECT geneid FROM ' . TABLE_TRANSCRIPTS . ' WHERE id = ?', array($nTranscriptID))->fetchColumn();
    }
}
require $sFile;
$_GET['object'] = 'LOVD_' . str_replace('_', '', $_GET['object']); // FIXME; test dit op een windows, test case-insensitivity.
$_DATA = new $_GET['object']($sObjectID);
$_DATA->viewEntry($nID);
?>