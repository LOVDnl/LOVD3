<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-05-25
 * Modified    : 2016-06-24
 * For LOVD    : 3.0-16
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Msc. Daan Asscheman <D.Asscheman@LUMC.nl>
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
require ROOT_PATH . 'inc-lib-variants.php';
session_write_close();

// For protein prediction a transcript identifier and variant description
// are mandatory. Either a mitochondrial gene or a reference sequence
// identifier is also required.
if (empty($_GET['transcript']) || empty($_GET['variant']) ||
    (empty($_GET['gene']) && empty($_GET['reference']))) {
    die(json_encode(AJAX_DATA_ERROR));
}

// This check must be done after a possible check for mitochondrial genes.
// Else we might check for a gene name with a mitochondrial gene alias name.
$aGenes = lovd_getGeneList();
if (!in_array($_GET['gene'], $aGenes)) {
    die(json_encode(AJAX_DATA_ERROR));
}

// Requires at least LEVEL_SUBMITTER, anything lower has no $_AUTH whatsoever.
if (!$_AUTH) {
    // If not authorized, die with error message.
    die(json_encode(AJAX_NO_AUTH));
}

$aResult = lovd_getRNAProteinPrediction($_GET['reference'], $_GET['gene'], $_GET['transcript'],
                                        $_GET['variant']);
print(json_encode($aResult));
?>
