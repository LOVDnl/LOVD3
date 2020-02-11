<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-09-09
 * Modified    : 2018-01-19
 * For LOVD    : 3.0-21
 *
 * Copyright   : 2004-2018 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Daan Asscheman <D.Asscheman@LUMC.nl>
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
session_write_close();

$aGenes = lovd_getGeneList();
if (empty($_GET['variant']) || !preg_match('/^([A-Z]{2}_\d{6,9}\.\d{1,2}(\([A-Za-z0-9-]+_v\d{3}\))?:[cn])|(chr.{0,2}:[gm])\..+$/', $_GET['variant']) || empty($_GET['gene']) || !in_array($_GET['gene'], $aGenes)) {
    die(AJAX_DATA_ERROR);
}

// Requires at least LEVEL_SUBMITTER, anything lower has no $_AUTH whatsoever.
if (!$_AUTH) {
    // If not authorized, die with error message.
    die(AJAX_NO_AUTH);
}

$sGene = $_GET['gene'];
// If the gene is defined in the mito_genes_aliases in inc-init.php, use the NCBI gene symbol.
if (isset($_SETT['mito_genes_aliases'][$_GET['gene']])) {
    $sGene = $_SETT['mito_genes_aliases'][$_GET['gene']];
}

$aResponse = lovd_callMutalyzer('numberConversion', array('build' => $_CONF['refseq_build'], 'variant' => $_GET['variant'], 'gene' => $sGene));
if ($aResponse === false) {
    // FIXME: Perhaps indicate an error? Like in the check_hgvs script?
    die(AJAX_FALSE);
}
if ($aResponse) {
    $sVariants = implode(';', $aResponse);
    die($sVariants);
} else {
    die(AJAX_FALSE);
}
?>
