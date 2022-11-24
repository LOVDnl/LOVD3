<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2015-11-27
 * Modified    : 2022-11-22
 * For LOVD    : 3.0-29
 *
 * Copyright   : 2004-2022 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Msc. Daan Asscheman <D.Asscheman@LUMC.nl>
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
$nMaxDropDown = 10;

$qGenes = $_DB->q('SELECT id AS value, CONCAT(id, " (", name, ")") AS label FROM ' . TABLE_GENES . ' ORDER BY id');
$zGenes = $qGenes->fetchAllAssoc();

if (empty($zGenes)) {
    die(json_encode(AJAX_DATA_ERROR));
}

foreach ($zGenes as $key => $aValues) {
    // This will shorten the gene names nicely, to prevent long gene names from messing up the form.
    $zGenes[$key]['label'] = lovd_shortenString($aValues['label'], 75);
}

if (count($zGenes) < $nMaxDropDown) {
    // Create the option elements.
    // Try to determine the currently selected gene, so we can pre-select that one,
    // making it easier to select genes close alphabetically, and also ensuring the
    // onChange() to run if the first gene from the list is selected.
    // This code is similar to inc-init.php's parsing to find CurrDB.
    $sCurrDB = '';
    if (!empty($_SERVER['HTTP_REFERER']) && preg_match('/^' . preg_quote(lovd_getInstallURL(), '/') . '(configuration|genes|transcripts|variants|individuals|view)\/([^\/]+)/', $_SERVER['HTTP_REFERER'], $aRegs)) {
        if (!in_array($aRegs[2], array('in_gene', 'upload')) && !ctype_digit($aRegs[2])) {
            $sCurrDB = strtoupper($aRegs[2]); // Not checking capitalization here yet.
        }
    }

    $sOptions = '';
    foreach ($zGenes as $aGene) {
        $sOptions .= '<OPTION value="' . $aGene['value'] . '"' . (!$sCurrDB || $sCurrDB != strtoupper($aGene['value'])? '' : ' selected') . '>' . $aGene['label'] . ' </OPTION>' . "\n";
    }
    die(json_encode(array(
        'switchType' => 'dropdown',
        'html' =>
            '<FORM action="" id="SelectGeneDBInline" method="get" style="margin : 0px;" onsubmit="lovd_changeURL(); return false;">' . "\n" .
            '  <DIV id="div_gene_dropdown">' . "\n" .
            '    <SELECT name="select_db" id="select_gene_dropdown" onchange="$(this).parent().parent().submit();">' . "\n" .
                   $sOptions .
            '    </SELECT>' . "\n" .
            '    <INPUT type="submit" value="Switch" id="select_gene_switch">' . "\n" .
            '  </DIV>' . "\n" .
            '</FORM>')));
} else {
    die(json_encode(array(
        'switchType' => 'autocomplete',
        'html' =>
            '<FORM action="" id="SelectGeneDBInline" method="get" style="margin : 0px;" onsubmit="lovd_changeURL(); return false;">' . "\n" .
            '  <DIV id="div_gene_autocomplete">' . "\n" .
            '    <INPUT name="select_db" id="select_gene_autocomplete" style="width : 75ex;">' . "\n" .
            '    <INPUT type="submit" value="Switch" id="select_gene_switch">' . "\n" .
            '  </DIV>' . "\n" .
            '</FORM>',
        'data' => $zGenes)));
}
?>
