<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-05-25
 * Modified    : 2016-02-02
 * For LOVD    : 3.0-15
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Msc. Daan Asscheman <D.Asscheman@LUMC.nl>
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

$aGenes = lovd_getGeneList();

// First check if $_GET is filled, to avoid errors and notices.
if (empty($_GET['variant']) || empty($_GET['gene']) || empty($_GET['DNAChange'])) {
    die(AJAX_DATA_ERROR);
}

$sGene = $_GET['gene'];
$sVariant = $_GET['variant'];
$sDNAChange = $_GET['DNAChange'];
// If gene is defined in the mito_genes_aliases in file inc-init.php use the NCBI gene symbol.
if (isset($_SETT['mito_genes_aliases'][$_GET['gene']])) {
    $sGene = $_SETT['mito_genes_aliases'][$_GET['gene']];
    $sVariant = str_replace($_GET['gene'], $sGene, $_GET['variant']);
}

// This check must be done after a possible check for mitochondrial genes. 
// Else we might check for a gene name with a mitochondrial gene alias name.
if (!in_array($_GET['gene'], $aGenes)) {
    die(AJAX_DATA_ERROR);
}

// Requires at least LEVEL_SUBMITTER, anything lower has no $_AUTH whatsoever.
if (!$_AUTH) {
    // If not authorized, die with error message.
    die(AJAX_NO_AUTH);
}

$result = lovd_getRNAProteinPrediction($sVariant, $sGene, $sDNAChange);
print(json_encode($result));
?>
