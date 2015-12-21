<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2015-11-27
 * Modified    : 2015-12-21
 * For LOVD    : 3.0-15
 *
 * Copyright   : 2004-2015 Leiden University Medical Center; http://www.LUMC.nl/
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

$qGenes = $_DB->query('SELECT id, id as value, CONCAT(id, " (", name, ")") AS label FROM ' . TABLE_GENES . ' ORDER BY id');
//$qGenes = $_DB->query('SELECT id, id as value, CONCAT(id, " (", name, ")") AS label FROM ' . TABLE_GENES . ' WHERE id = ?', array('ARSE'));
$zGenes = $qGenes->fetchAllAssoc();

if (empty($zGenes)) {
    die(json_encode(AJAX_DATA_ERROR));
}

foreach ($zGenes as $key => $value) {
    //This will shorten the gene names nicely, to prevent long gene names from messing up the form.
    $zGenes[$key]['label'] = lovd_shortenString($zGenes[$key]['label'], 75);
}

if (count($zGenes) < $nMaxDropDown) {
    // Create the option elements.
    $options = '';
    foreach ($zGenes as $aGene) { 
        $options .= '<OPTION value=' . $aGene['id'] . '>' . $aGene['label'] . ' </OPTION>' . "\n";
    }
    die(json_encode(array(
        'switchType' => 'dropdown',
        'html' => 
            '<FORM action="" id="SelectGeneDBInline" method="get" style="margin : 0px;" onsubmit="lovd_changeURL(); return false;">' . "\n" .
            '   <DIV id="div_gene_dropdown">' . "\n" .
            '        <SELECT name="select_db" id="select_gene_dropdown" onchange="$(this).parent().submit();">' . "\n" .
                        $options .
            '       </SELECT>' . "\n" .
            '       <INPUT type="submit" value="Switch" id="select_gene_switch">' . "\n" .
            '    </DIV>' . "\n" .
            '</FORM>')));
} else {
    die(json_encode(array(
        'switchType' => 'autocomplete',
        'html' => 
            '<FORM action="" id="SelectGeneDBInline" method="get" style="margin : 0px;" onsubmit="lovd_changeURL(); return false;">' . "\n" .
            '   <DIV id="div_gene_autocomplete">' . "\n" .
            '       <INPUT name="select_db" id="select_gene_autocomplete" onchange="$(this).parent().submit();">' . "\n" .
            '       <INPUT type="submit" value="Switch" id="select_gene_switch">' . "\n" .
            '   </DIV>' . "\n" .
            '</FORM>', 
        'data' => $zGenes)));
}
?>