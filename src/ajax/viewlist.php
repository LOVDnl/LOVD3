<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-02-18
 * Modified    : 2010-07-26
 * For LOVD    : 3.0-pre-08
 *
 * Copyright   : 2004-2010 Leiden University Medical Center; http://www.LUMC.nl/
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

define('ROOT_PATH', '../');
require ROOT_PATH . 'inc-init.php';

// Require manager clearance.
if (!$_AUTH || $_AUTH['level'] < LEVEL_MANAGER) {
    // If not authorized, die with error message.
    die('8'); // 'Not authorized' error.
}

if (empty($_GET['object']) || !preg_match('/^[A-Z]+$/i', $_GET['object'])) {
    die('9');
}

$sFile = ROOT_PATH . 'class/object_' . strtolower($_GET['object']) . 's.php';

if (!file_exists($sFile)) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

// We assume here that inc-top.php has been included but we can't see that from here.
// Having a double inc-top & bot when a queryerror shows up, is so ugly, so...
define('_INC_TOP_INCLUDED_', 'ajax');

require $sFile;
$_GET['object'] = ucwords($_GET['object']);
$_DATA = new $_GET['object']();
$_DATA->viewList((!empty($_GET['only_rows'])? true : false));
?>