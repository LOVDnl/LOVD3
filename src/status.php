<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-03-03
 * Modified    : 2012-06-26
 * For LOVD    : 3.0-beta-07
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
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

define('ROOT_PATH', './');
require ROOT_PATH . 'inc-init.php';

if ($_AUTH) {
    // If authorized, check for updates.
    require ROOT_PATH . 'inc-upgrade.php';
}

define('PAGE_TITLE', 'Current system status');
$_T->printHeader();
$_T->printTitle();

print('      <I>Current time: ' . date('r') . '</I><BR><BR>' . "\n\n");





require ROOT_PATH . 'class/graphs.php';
$_G = new LOVD_Graphs();
lovd_includeJS('lib/flot/jquery.flot.min.js');
lovd_includeJS('lib/flot/jquery.flot.pie.min.js');
print('      <!--[if lte IE 8]><SCRIPT type="text/javascript" src="lib/flot/excanvas.min.js"></SCRIPT><![endif]-->' . "\n\n");

// Statistics about genes:
$nGenes = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_GENES)->fetchColumn();
// Genes, how many variants found? || Genes, how many diseases linked?
print('      <H5>Genes (' . $nGenes . ')</H5>' . "\n" .
      '      <TABLE border="0" cellpadding="2" cellspacing="0" width="900" style="height : 300px; border-bottom : 3px double #CCC;">' . "\n" .
      '        <TR valign="top">' . "\n" .
      '          <TD width="50%">' . "\n" .
      '            <B>' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? 'V' : 'Public v') . 'ariants per gene</B><BR>'. "\n" .
      '            <DIV id="genesNumberOfVariants" style="width : 325px; height : 250px;"><IMG src="gfx/lovd_loading.gif" alt="Loading..."></DIV><DIV id="genesNumberOfVariants_hover">&nbsp;</DIV></TD>' . "\n" .
      '          <TD width="50%">' . "\n" .
      '            <B>Linked diseases per gene</B><BR>'. "\n" .
      '            <DIV id="genesLinkedDiseases" style="width : 325px; height : 250px;"><IMG src="gfx/lovd_loading.gif" alt="Loading..."></DIV><DIV id="genesLinkedDiseases_hover">&nbsp;</DIV></TD></TR></TABLE><BR>' . "\n\n");





// Variant types (DNA level), whole database.
print('      <H5>Variant type (DNA level)</H5>' . "\n" .
      '      <TABLE border="0" cellpadding="2" cellspacing="0" width="900" style="height : 320px;">' . "\n" .
      '        <TR valign="top">' . "\n" .
      '          <TD width="50%">' . "\n" .
      '            <B>All ' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' : 'public ') . ' variants</B><BR>'. "\n" .
      '            <DIV id="variantsTypeDNA_all" style="width : 325px; height : 250px;"><IMG src="gfx/lovd_loading.gif" alt="Loading..."></DIV><DIV id="variantsTypeDNA_all_hover">&nbsp;</DIV></TD>' . "\n" .
      '          <TD width="50%">' . "\n" .
      '            <B>Unique ' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' : 'public ') . 'variants</B><BR>'. "\n" .
      '            <DIV id="variantsTypeDNA_unique" style="width : 325px; height : 250px;"><IMG src="gfx/lovd_loading.gif" alt="Loading..."></DIV><DIV id="variantsTypeDNA_unique_hover">&nbsp;</DIV></TD></TR></TABLE>' . "\n\n");





$_T->printFooter(false);

$_G->genesNumberOfVariants('genesNumberOfVariants', '*', ($_AUTH['level'] >= LEVEL_COLLABORATOR));
$_G->genesLinkedDiseases('genesLinkedDiseases', '*');
$_G->variantsTypeDNA('variantsTypeDNA_all', '*', ($_AUTH['level'] >= LEVEL_COLLABORATOR), false);
$_G->variantsTypeDNA('variantsTypeDNA_unique', '*', ($_AUTH['level'] >= LEVEL_COLLABORATOR), true);





// Number of genes, number of variants in total, number of unique variants, number of diseases, etc? Not in graphs...
// Total screenings, total phenotypes, total individuals.
// Total number of curators, submitters? (percentage of users that is curator)




/*
$nTotalCurators = 0;
$nTotalCollaborators = 0;
$sSQL = 'SELECT g.id, g.name, g.updated_date, COUNT(u2g.userid) AS collaborators, SUM(u2g.allow_edit) AS curators FROM ' . TABLE_GENES . ' AS g LEFT OUTER JOIN ' . TABLE_CURATES . ' AS u2g ON (g.id = u2g.geneid) GROUP BY g.id ORDER BY g.id ASC';
$zGenes = $_DB->query($sSQL)->fetchAllAssoc();
$nGenes = count($zGenes);
foreach ($zGenes as $aGene) {
    $nCollaborators = ($aGene['collaborators'] - $aGene['curators']);
    print('  <TR class="data" id="' . $aGene['id'] . '" valign="top" style="cursor : pointer;" onclick="window.location.href=\'' . lovd_getInstallURL() . 'genes/' . rawurlencode($aGene['id']) . '\';">' . "\n" .
          '    <TD class="ordered"><A href="genes/' . rawurlencode($aGene['id']) . '" class="hide"><B>' . $aGene['id'] . '</B></A></TD>' . "\n" .
          '    <TD>' . $aGene['name'] . '</TD>' . "\n" .
          '    <TD align="right">' . ($nCollaborators? $nCollaborators : NULL) . '</TD>' . "\n" .
          '    <TD align="right">' . ($aGene['curators']? $aGene['curators'] : NULL) . '</TD>' . "\n" .
          '    <TD align="right">' . ($aGene['updated_date']? $aGene['updated_date'] : 'N/A') . '</TD>' . "\n" .
          '  </TR>' . "\n");
    $nTotalCollaborators += ($aGene['collaborators'] - $aGene['curators']);
    $nTotalCurators += $aGene['curators'];
}
*/

print('</BODY>' . "\n" .
      '</HTML>' . "\n");
?>
