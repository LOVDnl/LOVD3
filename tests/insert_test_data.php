<?php
/*
Test data import script, it will load some genes, diseases, transcripts and variants.
If entries already exist, it will not overwrite them.
*/

define('ROOT_PATH', '../src/');
require ROOT_PATH . 'inc-init.php';

header('Content-type: text/plain; charset=UTF-8');

// 1 Gene.
mysql_query('INSERT IGNORE INTO ' . TABLE_GENES . ' VALUES ("DMD", "DMD", "Dystrophin", "Xp21.2", "", "", "hg19", 0, 0, 0, 0, 0, "", "", "", 0, 0, 2928, 1756, 300377, "", 1, 1, 1, "", "", 0, "", 0, "", 0, "", "", 0, "", 0, 1, NOW(), NULL, NULL, 1, NOW())');

// Three diseases, all linked to this one gene.
mysql_query('INSERT IGNORE INTO ' . TABLE_DISEASES . ' VALUES (NULL, "DMD", "Duchenne muscular dystrophy", 310200, 1, NOW(), NULL, NULL)');
mysql_query('INSERT IGNORE INTO ' . TABLE_GEN2DIS . ' VALUES ("DMD", mysql_insert_id())');
mysql_query('INSERT IGNORE INTO ' . TABLE_DISEASES . ' VALUES (NULL, "BMD", "Becker muscular dystrophy", 300376, 1, NOW(), NULL, NULL)');
mysql_query('INSERT IGNORE INTO ' . TABLE_GEN2DIS . ' VALUES ("DMD", mysql_insert_id())');
mysql_query('INSERT IGNORE INTO ' . TABLE_DISEASES . ' VALUES (NULL, "CMD-3B", "X-linked dilated cardiomyopathy", 302045, 1, NOW(), NULL, NULL)');
mysql_query('INSERT IGNORE INTO ' . TABLE_GEN2DIS . ' VALUES ("DMD", mysql_insert_id())');

// First of two transcripts, linked to this gene.
mysql_query('INSERT IGNORE INTO ' . TABLE_TRANSCRIPTS . ' VALUES (NULL, "DMD", "Dystrophin Dp427m isoform", "NM_004006.2", NULL, NULL, NULL, "X", -244, 13749, 11058, 33229673, 31137345, 1, NOW(), NULL, NULL)');
$nTranscriptID = mysql_insert_id();

// One variant in this gene, mapped on first transcript.
list($nID) = mysql_fetch_row(mysql_query('SELECT MAX(id) FROM ' . TABLE_VARIANTS));
mysql_query('INSERT IGNORE INTO ' . TABLE_VARIANTS . ' VALUES (' . $nID . ', NULL, 0, NULL, 33229400, 33229400, "del", 9, 1, NOW(), NULL, NOW(), "9999-12-31", 0, NULL)');
mysql_query('INSERT IGNORE INTO ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' VALUES (' . $nID . ', ' . $nTranscriptID . ', NULL, "X", 30, 0, 30, 0, NOW())');

// Second of two transcripts, linked to this gene.
//mysql_query('INSERT IGNORE INTO ' . TABLE_TRANSCRIPTS . ' VALUES (NULL, "DMD", "Dystrophin Dp427c isoform", "NM_000109.3", NULL, NULL, NULL, "X", -344, 13749, 11034, 33357726, 31137345, 1, NOW(), NULL, NULL)');
$nTranscriptID = mysql_insert_id();

// One variant in this gene, mapped on first transcript.
mysql_query('INSERT IGNORE INTO ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' VALUES (' . $nID . ', ' . $nTranscriptID . ', NULL, "X", 7, 127976, 7, 127976, NOW())');
?>