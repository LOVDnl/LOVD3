<?php
/*
Test data import script, it will load some genes, diseases, transcripts and variants.
If entries already exist, it will not overwrite them.
*/

define('ROOT_PATH', '../src/');
require ROOT_PATH . 'inc-init.php';

header('Content-type: text/plain; charset=UTF-8');

// 1 Gene.
mysql_query('INSERT IGNORE INTO ' . TABLE_GENES . ' VALUES ("DMD", "DMD", "Duchenne Muscular Dystrophy", "Xp21.2", "", "hg19", "", "", 0, 0, 2928, 1756, 300377, "", 1, 1, 1, "", "", 0, "", "", "", 0, "", "", 0, "", 0, "00001", NOW(), "00001", NOW(), "00001", NOW())');
mysql_query('INSERT IGNORE INTO ' . TABLE_GENES . ' VALUES ("TTN", "TTN", "Titin", "2q32", "", "hg19", "", "", 0, 0, 2928, 1756, 300377, "", 1, 1, 1, "", "", 0, "", "", "", 0, "", "", 0, "", 0, "00001", NOW(), "00001", NOW(), "00001", NOW())');

// Three diseases, all linked to this one gene.
mysql_query('INSERT IGNORE INTO ' . TABLE_DISEASES . ' VALUES ("00001", "DMD", "Duchenne muscular dystrophy", 310200, 1, NOW(), NULL, NULL)');
mysql_query('INSERT IGNORE INTO ' . TABLE_GEN2DIS . ' VALUES ("DMD", "00001")');
mysql_query('INSERT IGNORE INTO ' . TABLE_DISEASES . ' VALUES ("00002", "BMD", "Becker muscular dystrophy", 300376, 1, NOW(), NULL, NULL)');
mysql_query('INSERT IGNORE INTO ' . TABLE_GEN2DIS . ' VALUES ("DMD", "00002")');
mysql_query('INSERT IGNORE INTO ' . TABLE_DISEASES . ' VALUES ("00003", "CMD-3B", "X-linked dilated cardiomyopathy", 302045, 1, NOW(), NULL, NULL)');
mysql_query('INSERT IGNORE INTO ' . TABLE_GEN2DIS . ' VALUES ("DMD", "00003")');

// First of two transcripts, linked to this gene.
mysql_query('INSERT IGNORE INTO ' . TABLE_TRANSCRIPTS . ' VALUES ("00001", "DMD", "Dystrophin Dp427m isoform", "NM_004006.2", NULL, NULL, NULL, NULL, "X", -244, 13749, 11058, 33229673, 31137345, 1, NOW(), NULL, NULL)');

// One variant in this gene, mapped on the first transcript.
mysql_query('INSERT IGNORE INTO ' . TABLE_VARIANTS . ' VALUES ("00000001", NULL, 0, NULL, 33229400, 33229400, "del", 9, "00001", NOW(), "00001", NOW(), "9999-12-31", 0, "00001")');
mysql_query('INSERT IGNORE INTO ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' VALUES ("00000001", "00001", NULL, "X", 30, 0, 30, 0, NOW())');

// Second of two transcripts, linked to this gene.
mysql_query('INSERT IGNORE INTO ' . TABLE_TRANSCRIPTS . ' VALUES ("00002", "DMD", "Dystrophin Dp427c isoform", "NM_000109.3", NULL, NULL, NULL, NULL, "X", -344, 13749, 11034, 33357726, 31137345, 1, NOW(), NULL, NULL)');

// One variant in this gene, mapped on the second transcript.
mysql_query('INSERT IGNORE INTO ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' VALUES ("00000001", "00002", NULL, "X", 7, 127976, 7, 127976, NOW())');

//One variant in this gene, mapped on the first transcript
mysql_query('INSERT IGNORE INTO ' . TABLE_VARIANTS . ' VALUES ("00000002", NULL, 0, NULL, NULL, NULL, "ins", 9, "00001", NOW(), "00001", NOW(), "9999-12-31", 0, "00001")');
mysql_query('INSERT IGNORE INTO ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' VALUES ("00000002", "00001", NULL, "X", 30, 0, 30, 0, NOW())');

mysql_query('INSERT IGNORE INTO ' . TABLE_TRANSCRIPTS . ' VALUES ("00003", "TTN", "Titin variant N2-A", "NM_133378.2", NULL, NULL, NULL, NULL, "2", -224, 108864, 107841, 179672150, 179781238, 1, NOW(), NULL, NULL)');
echo "Done!";
?>