<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-02-18
 * Modified    : 2011-08-16
 * For LOVD    : 3.0-alpha-04
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
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

define('ROOT_PATH', '../');
require ROOT_PATH . 'inc-init.php';

if (empty($_GET['viewlistid']) || empty($_GET['object']) || !preg_match('/^[A-Z_]+$/i', $_GET['object'])) {
    die(AJAX_DATA_ERROR);
}

// The required security to load the viewList() depends on the data that is shown.
// To prevent security problems if we forget to set a requirement here, we default to LEVEL_ADMIN.
$aNeededLevel =
         array(
                'Column' => LEVEL_CURATOR,
                'Custom_ViewList' => 0,
                'Disease' => 0,
                'Gene' => 0,
                'Genome_Variant' => 0,
                'Individual' => 0,
                'Link' => LEVEL_MANAGER,
                'Log' => LEVEL_MANAGER,
                'Phenotype' => 0,
                'Screening' => 0,
                'Transcript' => 0,
                'Transcript_Variant' => 0,
                'User' => LEVEL_MANAGER,
                'Variant' => 0, // FIXME; Remove later when object Variant no longer exists.
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

// We assume here that inc-top.php has been included but we can't see that from here.
// Having a double inc-top & bot when a queryerror shows up, is so ugly, so...
define('_INC_TOP_INCLUDED_', 'ajax');


$sObjectID = '';
if (in_array($_GET['object'], array('Phenotype', 'Transcript_Variant', 'Custom_ViewList'))) {
    $sObjectID = $_GET['object_id'];
}
require $sFile;
$_GET['object'] = 'LOVD_' . str_replace('_', '', $_GET['object']); // FIXME; test dit op een windows, test case-insensitivity.
$aColsToSkip = (!empty($_GET['skip'])? $_GET['skip'] : array());
$_DATA = new $_GET['object']($sObjectID);
// Set $bHideNav to false always, since this ajax request could only have been sent if there were navigation buttons.
$_DATA->viewList($_GET['viewlistid'], $aColsToSkip, (!empty($_GET['nohistory'])? true : false), (!empty($_GET['hidenav'])? true : false), (!empty($_GET['only_rows'])? true : false));
?>