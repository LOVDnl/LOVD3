<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-01-18
 * Modified    : 2011-02-16
 * For LOVD    : 3.0-pre-17
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

if (!ACTION || count($_GET) <= 1 ) {
    echo 'Insufficient arguments given.';
    exit;
}

require ROOT_PATH . '/class/REST2SOAP.php';

$_MutalyzerWS = new REST2SOAP();
$_MutalyzerWS->sSoapURL = 'http://mutalyzer.martijn/services';

$aArgs = array();
foreach ($_GET as $key => $value) {
    if ($value) {
        $aArgs[$key] = $value;
    }
}

$aOutput = $_MutalyzerWS->moduleCall(ACTION, $aArgs);

if (!is_array($aOutput)) {
    echo '"' . str_replace('"', '\"', $aOutput) . '"';
} else {
    echo '["' . implode('","', $aOutput) . '"]';
}
?>