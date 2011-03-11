<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-03-03
 * Modified    : 2011-03-11
 * For LOVD    : 3.0-pre-18
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

define('PAGE_TITLE', 'LOVD - Current system status');
require ROOT_PATH . 'inc-top.php';
lovd_printHeader(PAGE_TITLE);

print('      ' . date('Y/m/d H:i:s T \- l, F jS Y') . '<BR><BR>' . "\n\n");

print('<B>THESE VALUES ARE NOT YET ACTUAL DATA!</B><BR><BR>' . "\n\n");

print('<TABLE border="0" cellpadding="0" cellspacing="1" width="950" class="data" id="viewlist_table">' . "\n" .
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
      '          Total Variants' . "\n" .
      '        </DIV>' . "\n" .
      '      </TH>' . "\n" .
      '      <TH align="right" valign="top">' . "\n" .
      '        <IMG src="gfx/trans.png" alt="" width="70" height="1" id="viewlist_table_colwidth_id"><BR>' . "\n" .
      '        <DIV>' . "\n" .
      '          Unique Variants' . "\n" .
      '        </DIV>' . "\n" .
      '      </TH>' . "\n" .
      '    </TR>' . "\n" .
      '  </THEAD>' . "\n" .
      '  <TR>' . "\n");

//$_DATA = new LOVD_Status();
//$_DATA->viewList();
$nVariantsTotal = 0;
$nVariantsUnique = 0;
$qGenes = 'SELECT g.id, g.name FROM ' . TABLE_GENES . ' AS g ORDER BY g.id ASC';
$rGenes = lovd_queryDB($qGenes);
$nGenes = mysql_num_rows($rGenes);
while($aGene = mysql_fetch_assoc($rGenes)) {
    $aGene[2] = 12;
    $aGene[3] = 6;
    print('  <TR class="data" id="' . $aGene['id'] . '" valign="top" style="cursor : pointer;" onclick="window.location.href = \'genes/' . $aGene['id'] . '\';">' . "\n" .
          '    <TD class="ordered"><A href="genes/' . $aGene['id'] . '" class="hide"><B>' . $aGene['id'] . '</B></A></TD>' . "\n" .
          '    <TD>' . $aGene['name'] . '</TD>' . "\n" .
          '    <TD align="right">' . $aGene[2] . '</TD>' . "\n" .
          '    <TD align="right">' . $aGene[3] . '</TD>' . "\n" .
          '  </TR>' . "\n");
    $nVariantsTotal += $aGene[2];
    $nVariantsUnique += $aGene[3];
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
      '          ' . $nVariantsTotal . "\n" .
      '        </DIV>' . "\n" .
      '      </TH>' . "\n" .
      '      <TH align="right" valign="top">' . "\n" .
      '        <IMG src="gfx/trans.png" alt="" width="70" height="1" id="viewlist_table_colwidth_id"><BR>' . "\n" .
      '        <DIV>' . "\n" .
      '          ' . $nVariantsUnique . "\n" .
      '        </DIV>' . "\n" .
      '      </TH>' . "\n" .
      '    </TR>' . "\n" .
      '  </THEAD>' . "\n" .
      '</TABLE>' . "\n");
          


require ROOT_PATH . 'inc-bot.php';

?>