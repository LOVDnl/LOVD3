<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-12-17
 * Modified    : 2011-03-18
 * For LOVD    : 3.0-pre-19
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
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

// Test data import script, it will load some genes, diseases, transcripts and variants.
// If entries already exist, it will not overwrite them.

define('ROOT_PATH', '../src/');
require ROOT_PATH . 'inc-init.php';

// DMD gene.
mysql_query('INSERT IGNORE INTO ' . TABLE_GENES . ' VALUES ("DMD", "dystrophin", "X", "p21.2", "NG_012232.1", "UD_127955523176", "", "", "", 0, 0, 2928, 1756, 300377, 1, 1, 1, "", "", "", "", 0, "", "", 0, "", 0, "00001", NOW(), "00001", NOW(), "00001", NOW())');

// Three diseases, all linked to this one gene.
$b = mysql_query('INSERT IGNORE INTO ' . TABLE_DISEASES . ' VALUES (NULL, "DMD", "Duchenne muscular dystrophy", 310200, 1, NOW(), NULL, NULL)');
if ($b) {
    mysql_query('INSERT IGNORE INTO ' . TABLE_GEN2DIS . ' VALUES ("DMD", LAST_INSERT_ID())');
}
$b = mysql_query('INSERT IGNORE INTO ' . TABLE_DISEASES . ' VALUES (NULL, "BMD", "Becker muscular dystrophy", 300376, 1, NOW(), NULL, NULL)');
if ($b) {
    mysql_query('INSERT IGNORE INTO ' . TABLE_GEN2DIS . ' VALUES ("DMD", LAST_INSERT_ID())');
}
$b = mysql_query('INSERT IGNORE INTO ' . TABLE_DISEASES . ' VALUES (NULL, "CMD-3B", "X-linked dilated cardiomyopathy", 302045, 1, NOW(), NULL, NULL)');
if ($b) {
    mysql_query('INSERT IGNORE INTO ' . TABLE_GEN2DIS . ' VALUES ("DMD", LAST_INSERT_ID())');
}

// First variant in this gene, mapping to both transcripts of this gene.
$b = mysql_query('INSERT IGNORE INTO ' . TABLE_VARIANTS . ' VALUES (NULL, NULL, 0, NULL, "X", 33229400, 33229400, "del", 1, 9, "00001", NOW(), "00001", NOW(), "9999-12-31", 0, "00001")');
$nVarID = mysql_insert_id();

// First of two transcripts, having one variant.
$b = mysql_query('INSERT IGNORE INTO ' . TABLE_TRANSCRIPTS . ' VALUES (NULL, "DMD", "transcript variant Dp427m", "NM_004006.2", NULL, NULL, NULL, NULL, -244, 13749, 11058, 33229673, 31137345, 1, NOW(), NULL, NULL)');
if ($b) {
    $nFirstTranscriptID = mysql_insert_id();
    mysql_query('INSERT IGNORE INTO ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' VALUES (' . $nVarID . ', LAST_INSERT_ID(), NULL, 30, 0, 30, 0, NOW())');
}

// Second of two transcripts, having one variant.
$b = mysql_query('INSERT IGNORE INTO ' . TABLE_TRANSCRIPTS . ' VALUES (NULL, "DMD", "transcript variant Dp427c", "NM_000109.3", NULL, NULL, NULL, NULL, -344, 13749, 11034, 33357726, 31137345, 1, NOW(), NULL, NULL)');
if ($b) {
    mysql_query('INSERT IGNORE INTO ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' VALUES (' . $nVarID . ', LAST_INSERT_ID(), NULL, 7, 127976, 7, 127976, NOW())');
}

// Second variant in this gene, mapped only to the first transcript.
// FIXME; provide proper genomic locations.
$b = mysql_query('INSERT IGNORE INTO ' . TABLE_VARIANTS . ' VALUES (NULL, NULL, 0, NULL, "X", NULL, NULL, "ins", 1, 9, "00001", NOW(), "00001", NOW(), "9999-12-31", 0, "00001")');
if ($b) {
    mysql_query('INSERT IGNORE INTO ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' VALUES (LAST_INSERT_ID(), ' . $nFirstTranscriptID . ', NULL, 30, 0, 30, 0, NOW())');
}





// TTN gene.
mysql_query('INSERT IGNORE INTO ' . TABLE_GENES . ' VALUES ("TTN", "titin", "2", "q32", "NG_011618.2", "UD_129502170264", "", "", "", 0, 0, 12403, 7273, 188840, 1, 1, 1, "", "", "", "", 0, "", "", 0, "", 0, "00001", NOW(), "00001", NOW(), "00001", NOW())');

// One transcript, having one variant.
$b = mysql_query('INSERT IGNORE INTO ' . TABLE_TRANSCRIPTS . ' VALUES (NULL, "TTN", "transcript variant N2-A", "NM_133378.2", NULL, NULL, NULL, NULL, -224, 108864, 107841, 179672150, 179781238, 1, NOW(), NULL, NULL)');
if ($b) {
    $nTranscriptID = mysql_insert_id();
    // FIXME; provide proper genomic locations.
    mysql_query('INSERT IGNORE INTO ' . TABLE_VARIANTS . ' VALUES (NULL, NULL, 0, NULL, "X", NULL, NULL, "dup", 1, 9, "00001", NOW(), "00001", NOW(), "9999-12-31", 0, "00001")');
    mysql_query('INSERT IGNORE INTO ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' VALUES (LAST_INSERT_ID(), ' . $nTranscriptID . ', NULL, 30, 0, 30, 0, NOW())');
}





// Fourth variant, completely unbound.
// FIXME; provide proper genomic locations.
mysql_query('INSERT IGNORE INTO ' . TABLE_VARIANTS . ' VALUES (NULL, NULL, 0, NULL, "X", NULL, NULL, "subst", 1, 9, "00001", NOW(), "00001", NOW(), "9999-12-31", 0, "00001")');







// First patient, having been screened
mysql_query('INSERT INTO ' . TABLE_PATIENTS . ' VALUES (NULL, "00001", 9, "00001", NOW(), NULL, NOW(), "9999-12-31 00:00:00", 0, NULL)');
mysql_query('INSERT INTO ' . TABLE_SCREENINGS . ' VALUES(NULL, LAST_INSERT_ID(), "00001", "00001", NOW(), NULL, NOW(), "9999-12-31 00:00:00", 0, NULL)');

// First Variant found with first screening
mysql_query('INSERT IGNORE INTO ' . TABLE_SCR2VAR . ' VALUES(LAST_INSERT_ID(), ' . $nVarID . ')');

?>

<HTML>
  <BODY>
    Done!<BR>
	Click <A href="../src/genes">here</A> to go back to LOVD3!
  </BODY>
</HTML>