<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-12-17
 * Modified    : 2011-01-04
 * For LOVD    : 3.0-pre-13
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

header('Content-type: text/plain; charset=UTF-8');

// DMD gene.
mysql_query('INSERT IGNORE INTO ' . TABLE_GENES . ' VALUES ("DMD", "DMD", "Duchenne Muscular Dystrophy", "X", "Xp21.2", "", "hg19", "", "", 0, 0, 2928, 1756, 300377, 1, 1, 1, "", "", "", "", 0, "", "", 0, "", 0, "00001", NOW(), "00001", NOW(), "00001", NOW())');

// Three diseases, all linked to this one gene.
$b = mysql_query('INSERT IGNORE INTO ' . TABLE_DISEASES . ' VALUES (NULL, "DMD", "Duchenne muscular dystrophy", 310200, 1, NOW(), NULL, NULL)');
if ($b) {
    mysql_query('INSERT IGNORE INTO ' . TABLE_GEN2DIS . ' VALUES ("DMD", ' . mysql_insert_id() . ')');
}
$b = mysql_query('INSERT IGNORE INTO ' . TABLE_DISEASES . ' VALUES (NULL, "BMD", "Becker muscular dystrophy", 300376, 1, NOW(), NULL, NULL)');
if ($b) {
    mysql_query('INSERT IGNORE INTO ' . TABLE_GEN2DIS . ' VALUES ("DMD", ' . mysql_insert_id() . ')');
}
$b = mysql_query('INSERT IGNORE INTO ' . TABLE_DISEASES . ' VALUES (NULL, "CMD-3B", "X-linked dilated cardiomyopathy", 302045, 1, NOW(), NULL, NULL)');
if ($b) {
    mysql_query('INSERT IGNORE INTO ' . TABLE_GEN2DIS . ' VALUES ("DMD", ' . mysql_insert_id() . ')');
}

// First variant in this gene, mapping to both transcripts of this gene.
list($nVarID) = mysql_fetch_row(mysql_query('SELECT MAX(id) FROM ' . TABLE_VARIANTS));
$nVarID ++;
$b = mysql_query('INSERT IGNORE INTO ' . TABLE_VARIANTS . ' VALUES (' . $nVarID . ', NULL, 0, NULL, "X", 33229400, 33229400, "del", 9, "00001", NOW(), "00001", NOW(), "9999-12-31", 0, "00001")');

// First of two transcripts, having one variant.
$b = mysql_query('INSERT IGNORE INTO ' . TABLE_TRANSCRIPTS . ' VALUES (NULL, "DMD", "Dystrophin Dp427m isoform", "NM_004006.2", NULL, NULL, NULL, NULL, -244, 13749, 11058, 33229673, 31137345, 1, NOW(), NULL, NULL)');
if ($b) {
    $nFirstTranscriptID = mysql_insert_id();
    mysql_query('INSERT IGNORE INTO ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' VALUES (' . $nVarID . ', ' . $nFirstTranscriptID . ', NULL, 30, 0, 30, 0, NOW())');
}

// Second of two transcripts, having one variant.
$b = mysql_query('INSERT IGNORE INTO ' . TABLE_TRANSCRIPTS . ' VALUES (NULL, "DMD", "Dystrophin Dp427c isoform", "NM_000109.3", NULL, NULL, NULL, NULL, -344, 13749, 11034, 33357726, 31137345, 1, NOW(), NULL, NULL)');
if ($b) {
    $nSecondTranscriptID = mysql_insert_id();
    mysql_query('INSERT IGNORE INTO ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' VALUES (' . $nVarID . ', ' . $nSecondTranscriptID . ', NULL, 7, 127976, 7, 127976, NOW())');
}

// Second variant in this gene, mapped only to the first transcript.
$nVarID ++;
// FIXME; provide proper genomic locations.
$b = mysql_query('INSERT IGNORE INTO ' . TABLE_VARIANTS . ' VALUES (' . $nVarID . ', NULL, 0, NULL, "X", NULL, NULL, "ins", 9, "00001", NOW(), "00001", NOW(), "9999-12-31", 0, "00001")');
if ($b) {
    mysql_query('INSERT IGNORE INTO ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' VALUES (' . $nVarID . ', ' . $nFirstTranscriptID . ', NULL, 30, 0, 30, 0, NOW())');
}





// TTN gene.
mysql_query('INSERT IGNORE INTO ' . TABLE_GENES . ' VALUES ("TTN", "TTN", "Titin", "2", "2q32", "", "hg19", "", "", 0, 0, 2928, 1756, 300377, 1, 1, 1, "", "", "", "", 0, "", "", 0, "", 0, "00001", NOW(), "00001", NOW(), "00001", NOW())');

// One transcript, having one variant.
$b = mysql_query('INSERT IGNORE INTO ' . TABLE_TRANSCRIPTS . ' VALUES (NULL, "TTN", "Titin variant N2-A", "NM_133378.2", NULL, NULL, NULL, NULL, -224, 108864, 107841, 179672150, 179781238, 1, NOW(), NULL, NULL)');
if ($b) {
    $nTranscriptID = mysql_insert_id();
    $nVarID ++;
    // FIXME; provide proper genomic locations.
    mysql_query('INSERT IGNORE INTO ' . TABLE_VARIANTS . ' VALUES (' . $nVarID . ', NULL, 0, NULL, "X", NULL, NULL, "dup", 9, "00001", NOW(), "00001", NOW(), "9999-12-31", 0, "00001")');
    mysql_query('INSERT IGNORE INTO ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' VALUES (' . $nVarID . ', ' . $nTranscriptID . ', NULL, 30, 0, 30, 0, NOW())');
}





// Fourth variant, completely unbound.
$nVarID ++;
// FIXME; provide proper genomic locations.
mysql_query('INSERT IGNORE INTO ' . TABLE_VARIANTS . ' VALUES (' . $nVarID . ', NULL, 0, NULL, "X", NULL, NULL, "subst", 9, "00001", NOW(), "00001", NOW(), "9999-12-31", 0, "00001")');

echo "Done!";
?>
