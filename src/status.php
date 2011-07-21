<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-03-03
 * Modified    : 2011-07-21
 * For LOVD    : 3.0-alpha-03
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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
require ROOT_PATH . 'inc-top.php';
lovd_printHeader(PAGE_TITLE);

print('      ' . date('Y/m/d H:i:s T \- l, F jS Y') . '<BR><BR>' . "\n\n");

print('<B>DISABLED FOR REVISION!</B>');

/*print('<TABLE border="0" cellpadding="0" cellspacing="1" width="950" class="data" id="viewlist_table">' . "\n" .
      '  <THEAD>' . "\n" .
      '    <TR>' . "\n" .
      '      <TH valign="top" class="ordered">' . "\n" .
      '        <IMG src="gfx/trans.png" alt="" width="70" height="1" id="viewlist_table_colwidth_id"><BR>' . "\n" .
      '        <DIV>' . "\n" .
      '          Gene Symbol' . "\n" .
      '        </DIV>' . "\n" .
      '      </TH>' . "\n" .
      '      <TH valign="top">' . "\n" .
      '        <IMG src="gfx/trans.png" alt="" width="70" height="1" id="viewlist_table_colwidth_id"><BR>' . "\n" .
      '        <DIV>' . "\n" .
      '          Gene Name' . "\n" .
      '        </DIV>' . "\n" .
      '      </TH>' . "\n" .
      '      <TH align="right" valign="top">' . "\n" .
      '        <IMG src="gfx/trans.png" alt="" width="70" height="1" id="viewlist_table_colwidth_id"><BR>' . "\n" .
      '        <DIV>' . "\n" .
      '          Collaborators' . "\n" .
      '        </DIV>' . "\n" .
      '      </TH>' . "\n" .
      '      <TH align="right" valign="top">' . "\n" .
      '        <IMG src="gfx/trans.png" alt="" width="70" height="1" id="viewlist_table_colwidth_id"><BR>' . "\n" .
      '        <DIV>' . "\n" .
      '          Curators' . "\n" .
      '        </DIV>' . "\n" .
      '      </TH>' . "\n" .
      '      <TH align="right" valign="top">' . "\n" .
      '        <IMG src="gfx/trans.png" alt="" width="70" height="1" id="viewlist_table_colwidth_id"><BR>' . "\n" .
      '        <DIV>' . "\n" .
      '          Date last updated' . "\n" .
      '        </DIV>' . "\n" .
      '      </TH>' . "\n" .
      '    </TR>' . "\n" .
      '  </THEAD>' . "\n" .
      '  <TR>' . "\n");

$nTotalCurators = 0;
$nTotalCollaborators = 0;
$qGenes = 'SELECT g.id, g.name, g.updated_date, COUNT(u2g.userid) AS collaborators, SUM(u2g.allow_edit) AS curators FROM ' . TABLE_GENES . ' AS g LEFT OUTER JOIN ' . TABLE_CURATES . ' AS u2g ON (g.id = u2g.geneid) GROUP BY g.id ORDER BY g.id ASC';
$rGenes = lovd_queryDB($qGenes);
$nGenes = mysql_num_rows($rGenes);
while($aGene = mysql_fetch_assoc($rGenes)) {
    $nCollaborators = ($aGene['collaborators'] - $aGene['curators']);
    print('  <TR class="data" id="' . $aGene['id'] . '" valign="top" style="cursor : pointer;" onclick="window.location.href = \'genes/' . rawurlencode($aGene['id']) . '\';">' . "\n" .
          '    <TD class="ordered"><A href="genes/' . rawurlencode($aGene['id']) . '" class="hide"><B>' . $aGene['id'] . '</B></A></TD>' . "\n" .
          '    <TD>' . $aGene['name'] . '</TD>' . "\n" .
          '    <TD align="right">' . ($nCollaborators? $nCollaborators : NULL) . '</TD>' . "\n" .
          '    <TD align="right">' . ($aGene['curators']? $aGene['curators'] : NULL) . '</TD>' . "\n" .
          '    <TD align="right">' . ($aGene['updated_date']? $aGene['updated_date'] : 'N/A') . '</TD>' . "\n" .
          '  </TR>' . "\n");
    $nTotalCollaborators += ($aGene['collaborators'] - $aGene['curators']);
    $nTotalCurators += $aGene['curators'];
}

print('  <THEAD>' . "\n" .
      '    <TR>' . "\n" .
      '      <TH valign="top">' . "\n" .
      '        <IMG src="gfx/trans.png" alt="" width="70" height="1" id="viewlist_table_colwidth_id"><BR>' . "\n" .
      '        <DIV>' . "\n" .
      '          ' . "\n" .
      '        </DIV>' . "\n" .
      '      </TH>' . "\n" .
      '      <TH align="right" valign="top">' . "\n" .
      '        <IMG src="gfx/trans.png" alt="" width="70" height="1" id="viewlist_table_colwidth_id"><BR>' . "\n" .
      '        <DIV>' . "\n" .
      '          Total of ' . $nGenes . ' genes' . "\n" .
      '        </DIV>' . "\n" .
      '      </TH>' . "\n" .
      '      <TH align="right" valign="top">' . "\n" .
      '        <IMG src="gfx/trans.png" alt="" width="70" height="1" id="viewlist_table_colwidth_id"><BR>' . "\n" .
      '        <DIV>' . "\n" .
      '          ' . $nTotalCollaborators . "\n" .
      '        </DIV>' . "\n" .
      '      </TH>' . "\n" .
      '      <TH align="right" valign="top">' . "\n" .
      '        <IMG src="gfx/trans.png" alt="" width="70" height="1" id="viewlist_table_colwidth_id"><BR>' . "\n" .
      '        <DIV>' . "\n" .
      '          ' . $nTotalCurators . "\n" .
      '        </DIV>' . "\n" .
      '      </TH>' . "\n" .
      '      <TH align="right" valign="top">' . "\n" .
      '        <IMG src="gfx/trans.png" alt="" width="70" height="1" id="viewlist_table_colwidth_id"><BR>' . "\n" .
      '        <DIV>' . "\n" .
      '          ' . "\n" .
      '        </DIV>' . "\n" .
      '      </TH>' . "\n" .
      '    </TR>' . "\n" .
      '  </THEAD>' . "\n" .
      '</TABLE>' . "\n");
*/


require ROOT_PATH . 'inc-bot.php';

?>
