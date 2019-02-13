<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-02-10
 * Modified    : 2019-02-12
 * For LOVD    : 3.0-15
 *
 * Copyright   : 2004-2019 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

define('FORMAT_ALLOW_TEXTPLAIN', true);
$_GET['format'] = 'text/plain';

require 'inc-lib-tests.php';

define('ROOT_PATH', '../../src/');
require ROOT_PATH . 'inc-init.php';

error_reporting(E_ALL & ~E_DEPRECATED);

// This unit test checks the outcome of the query optimizer. The resulting
// query should be matching the expected outcome, and the speed should be
// better than the original. Finally, the number of results should be the same.

$aSQL =
    array(
        // Note: All queries should still have the SQL_CALC_FOUND_ROWS() added.
        'SELECT SQL_CALC_FOUND_ROWS d.*, d.id AS diseaseid, (SELECT COUNT(DISTINCT i.id) FROM ' . TABLE_IND2DIS . ' AS i2d LEFT OUTER JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (i2d.individualid = i.id AND i.statusid >= 7) WHERE i2d.diseaseid = d.id) AS individuals, (SELECT COUNT(*) FROM ' . TABLE_PHENOTYPES . ' AS p WHERE p.diseaseid = d.id AND p.statusid >= 7) AS phenotypes, COUNT(g2d.geneid) AS gene_count, GROUP_CONCAT(DISTINCT g2d.geneid ORDER BY g2d.geneid SEPARATOR ";") AS _genes FROM ' . TABLE_DISEASES . ' AS d LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (d.id = g2d.diseaseid) WHERE d.id > 0 GROUP BY d.id LIMIT 0' => 'SELECT COUNT(*) FROM ' . TABLE_DISEASES . ' AS d WHERE d.id > 0',
        'SELECT SQL_CALC_FOUND_ROWS d.*, d.id AS diseaseid, (SELECT COUNT(DISTINCT i.id) FROM ' . TABLE_IND2DIS . ' AS i2d LEFT OUTER JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (i2d.individualid = i.id AND i.statusid >= 7) WHERE i2d.diseaseid = d.id) AS individuals, (SELECT COUNT(*) FROM ' . TABLE_PHENOTYPES . ' AS p WHERE p.diseaseid = d.id AND p.statusid >= 7) AS phenotypes, COUNT(g2d.geneid) AS gene_count, GROUP_CONCAT(DISTINCT g2d.geneid ORDER BY g2d.geneid SEPARATOR ";") AS _genes FROM ' . TABLE_DISEASES . ' AS d LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (d.id = g2d.diseaseid) WHERE d.id > 0 GROUP BY d.id HAVING (_genes != "" AND _genes IS NOT NULL) LIMIT 0' => 'SELECT COUNT(*) FROM (SELECT GROUP_CONCAT(DISTINCT g2d.geneid ORDER BY g2d.geneid SEPARATOR ";") AS _genes FROM ' . TABLE_DISEASES . ' AS d LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (d.id = g2d.diseaseid) WHERE d.id > 0 GROUP BY d.id HAVING (_genes != "" AND _genes IS NOT NULL))A',
        'SELECT SQL_CALC_FOUND_ROWS d.*, d.id AS diseaseid, (SELECT COUNT(DISTINCT i.id) FROM ' . TABLE_IND2DIS . ' AS i2d LEFT OUTER JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (i2d.individualid = i.id AND i.statusid >= 7) WHERE i2d.diseaseid = d.id) AS individuals, (SELECT COUNT(*) FROM ' . TABLE_PHENOTYPES . ' AS p WHERE p.diseaseid = d.id AND p.statusid >= 7) AS phenotypes, COUNT(g2d.geneid) AS gene_count, GROUP_CONCAT(DISTINCT g2d.geneid ORDER BY g2d.geneid SEPARATOR ";") AS _genes FROM ' . TABLE_DISEASES . ' AS d LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (d.id = g2d.diseaseid) WHERE d.id > 0 GROUP BY d.id HAVING (_genes LIKE "%ACE2%") LIMIT 0' => 'SELECT COUNT(*) FROM (SELECT GROUP_CONCAT(DISTINCT g2d.geneid ORDER BY g2d.geneid SEPARATOR ";") AS _genes FROM ' . TABLE_DISEASES . ' AS d LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (d.id = g2d.diseaseid) WHERE d.id > 0 GROUP BY d.id HAVING (_genes LIKE "%ACE2%"))A',

        'SELECT SQL_CALC_FOUND_ROWS g.*, g.id AS geneid, GROUP_CONCAT(DISTINCT IF(CASE d.symbol WHEN "-" THEN "" ELSE d.symbol END = "", d.name, d.symbol) ORDER BY (d.symbol != "" AND d.symbol != "-") DESC, d.symbol, d.name SEPARATOR ", ") AS diseases_, COUNT(DISTINCT t.id) AS transcripts, COUNT(DISTINCT vog.id) AS variants, COUNT(DISTINCT vog.`VariantOnGenome/DBID`) AS uniq_variants FROM ' . TABLE_GENES . ' AS g LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (g.id = g2d.geneid) LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (g.id = t.geneid) LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id AND vog.statusid >= 7) LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (g2d.diseaseid = d.id) GROUP BY g.id LIMIT 0' => 'SELECT COUNT(*) FROM ' . TABLE_GENES . ' AS g',
        'SELECT SQL_CALC_FOUND_ROWS g.*, g.id AS geneid, GROUP_CONCAT(DISTINCT IF(CASE d.symbol WHEN "-" THEN "" ELSE d.symbol END = "", d.name, d.symbol) ORDER BY (d.symbol != "" AND d.symbol != "-") DESC, d.symbol, d.name SEPARATOR ", ") AS diseases_, COUNT(DISTINCT t.id) AS transcripts, COUNT(DISTINCT vog.id) AS variants, COUNT(DISTINCT vog.`VariantOnGenome/DBID`) AS uniq_variants FROM ' . TABLE_GENES . ' AS g LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (g.id = g2d.geneid) LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (g.id = t.geneid) LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id AND vog.statusid >= 7) LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (g2d.diseaseid = d.id) GROUP BY g.id ORDER BY g.id ASC LIMIT 100 OFFSET 0' => 'SELECT COUNT(*) FROM ' . TABLE_GENES . ' AS g',
        'SELECT SQL_CALC_FOUND_ROWS g.*, g.id AS geneid, GROUP_CONCAT(DISTINCT IF(CASE d.symbol WHEN "-" THEN "" ELSE d.symbol END = "", d.name, d.symbol) ORDER BY (d.symbol != "" AND d.symbol != "-") DESC, d.symbol, d.name SEPARATOR ", ") AS diseases_, COUNT(DISTINCT t.id) AS transcripts, COUNT(DISTINCT vog.id) AS variants, COUNT(DISTINCT vog.`VariantOnGenome/DBID`) AS uniq_variants FROM ' . TABLE_GENES . ' AS g LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (g.id = g2d.geneid) LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (g.id = t.geneid) LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id AND vog.statusid >= 7) LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (g2d.diseaseid = d.id) GROUP BY g.id HAVING (variants > "10") LIMIT 0' => 'SELECT COUNT(*) FROM (SELECT COUNT(DISTINCT vog.id) AS variants FROM ' . TABLE_GENES . ' AS g LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (g.id = t.geneid) LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id AND vog.statusid >= 7) GROUP BY g.id HAVING (variants > "10"))A',
        'SELECT SQL_CALC_FOUND_ROWS g.*, g.id AS geneid, GROUP_CONCAT(DISTINCT IF(CASE d.symbol WHEN "-" THEN "" ELSE d.symbol END = "", d.name, d.symbol) ORDER BY (d.symbol != "" AND d.symbol != "-") DESC, d.symbol, d.name SEPARATOR ", ") AS diseases_, COUNT(DISTINCT t.id) AS transcripts, COUNT(DISTINCT vog.id) AS variants, COUNT(DISTINCT vog.`VariantOnGenome/DBID`) AS uniq_variants FROM ' . TABLE_GENES . ' AS g LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (g.id = g2d.geneid) LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (g.id = t.geneid) LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id AND vog.statusid >= 7) LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (g2d.diseaseid = d.id) GROUP BY g.id HAVING (variants > "10") ORDER BY uniq_variants DESC LIMIT 100 OFFSET 0' => 'SELECT COUNT(*) FROM (SELECT COUNT(DISTINCT vog.id) AS variants FROM ' . TABLE_GENES . ' AS g LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (g.id = t.geneid) LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id AND vog.statusid >= 7) GROUP BY g.id HAVING (variants > "10"))A',
        'SELECT SQL_CALC_FOUND_ROWS g.*, g.id AS geneid, GROUP_CONCAT(DISTINCT IF(CASE d.symbol WHEN "-" THEN "" ELSE d.symbol END = "", d.name, d.symbol) ORDER BY (d.symbol != "" AND d.symbol != "-") DESC, d.symbol, d.name SEPARATOR ", ") AS diseases_, COUNT(DISTINCT t.id) AS transcripts, COUNT(DISTINCT vog.id) AS variants, COUNT(DISTINCT vog.`VariantOnGenome/DBID`) AS uniq_variants FROM ' . TABLE_GENES . ' AS g LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (g.id = g2d.geneid) LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (g.id = t.geneid) LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id AND vog.statusid >= 7) LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (g2d.diseaseid = d.id) GROUP BY g.id HAVING (variants > "10") AND (diseases_ != "" AND diseases_ IS NOT NULL) LIMIT 0' => 'SELECT COUNT(*) FROM (SELECT GROUP_CONCAT(DISTINCT IF(CASE d.symbol WHEN "-" THEN "" ELSE d.symbol END = "", d.name, d.symbol) ORDER BY (d.symbol != "" AND d.symbol != "-") DESC, d.symbol, d.name SEPARATOR ", ") AS diseases_, COUNT(DISTINCT vog.id) AS variants FROM ' . TABLE_GENES . ' AS g LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (g.id = g2d.geneid) LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (g.id = t.geneid) LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id AND vog.statusid >= 7) LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (g2d.diseaseid = d.id) GROUP BY g.id HAVING (variants > "10") AND (diseases_ != "" AND diseases_ IS NOT NULL))A',

        'SELECT SQL_CALC_FOUND_ROWS i.*, i.id AS individualid, GROUP_CONCAT(DISTINCT d.id) AS diseaseids, GROUP_CONCAT(DISTINCT IF(CASE d.symbol WHEN "-" THEN "" ELSE d.symbol END = "", d.name, d.symbol) ORDER BY (d.symbol != "" AND d.symbol != "-") DESC, d.symbol, d.name SEPARATOR ", ") AS diseases_, GROUP_CONCAT(DISTINCT s2g.geneid ORDER BY s2g.geneid SEPARATOR ", ") AS genes_screened_, COUNT(DISTINCT vog.id) AS variants_, uo.name AS owned_by_, CONCAT_WS(";", uo.id, uo.name, uo.email, uo.institute, uo.department, IFNULL(uo.countryid, "")) AS _owner, ds.name AS status FROM ' . TABLE_INDIVIDUALS . ' AS i LEFT OUTER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (i.id = i2d.individualid) LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (i2d.diseaseid = d.id) LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s ON (i.id = s.individualid) LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s2v.screeningid = s.id) LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog ON (s2v.variantid = vog.id AND (vog.statusid >= 7)) LEFT OUTER JOIN ' . TABLE_SCR2GENE . ' AS s2g ON (s.id = s2g.screeningid) LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (i.owned_by = uo.id) LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS ds ON (i.statusid = ds.id) WHERE (i.statusid >= 7) GROUP BY i.id LIMIT 0' => 'SELECT COUNT(*) FROM ' . TABLE_INDIVIDUALS . ' AS i WHERE (i.statusid >= 7)',
        'SELECT SQL_CALC_FOUND_ROWS i.*, i.id AS individualid, GROUP_CONCAT(DISTINCT d.id) AS diseaseids, GROUP_CONCAT(DISTINCT IF(CASE d.symbol WHEN "-" THEN "" ELSE d.symbol END = "", d.name, d.symbol) ORDER BY (d.symbol != "" AND d.symbol != "-") DESC, d.symbol, d.name SEPARATOR ", ") AS diseases_, GROUP_CONCAT(DISTINCT s2g.geneid ORDER BY s2g.geneid SEPARATOR ", ") AS genes_screened_, COUNT(DISTINCT vog.id) AS variants_, uo.name AS owned_by_, CONCAT_WS(";", uo.id, uo.name, uo.email, uo.institute, uo.department, IFNULL(uo.countryid, "")) AS _owner, ds.name AS status FROM ' . TABLE_INDIVIDUALS . ' AS i LEFT OUTER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (i.id = i2d.individualid) LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (i2d.diseaseid = d.id) LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s ON (i.id = s.individualid) LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s2v.screeningid = s.id) LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog ON (s2v.variantid = vog.id AND (vog.statusid >= 7)) LEFT OUTER JOIN ' . TABLE_SCR2GENE . ' AS s2g ON (s.id = s2g.screeningid) LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (i.owned_by = uo.id) LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS ds ON (i.statusid = ds.id) WHERE (i.statusid >= 7) AND (s2g.geneid = "ACE2") GROUP BY i.id LIMIT 0' => 'SELECT COUNT(*) FROM (SELECT 1 FROM ' . TABLE_INDIVIDUALS . ' AS i LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s ON (i.id = s.individualid) LEFT OUTER JOIN ' . TABLE_SCR2GENE . ' AS s2g ON (s.id = s2g.screeningid) WHERE (i.statusid >= 7) AND (s2g.geneid = "ACE2") GROUP BY i.id)A',

        'SELECT SQL_CALC_FOUND_ROWS s.*, s.id AS screeningid, IF(s.variants_found = 1 AND COUNT(s2v.variantid) = 0, -1, COUNT(DISTINCT s2v.variantid)) AS variants_found_, GROUP_CONCAT(DISTINCT s2g.geneid SEPARATOR ", ") AS genes, CASE i.statusid WHEN 7 THEN "marked" WHEN 4 THEN "del" END AS class_name, uo.name AS owned_by_, CONCAT_WS(";", uo.id, uo.name, uo.email, uo.institute, uo.department, IFNULL(uo.countryid, "")) AS _owner FROM ' . TABLE_SCREENINGS . ' AS s LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) LEFT OUTER JOIN ' . TABLE_SCR2GENE . ' AS s2g ON (s.id = s2g.screeningid) LEFT OUTER JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id) LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (s.owned_by = uo.id) GROUP BY s.id HAVING (genes LIKE "%ACE2%") ORDER BY s.id ASC LIMIT 100 OFFSET 0' => 'SELECT COUNT(*) FROM (SELECT GROUP_CONCAT(DISTINCT s2g.geneid SEPARATOR ", ") AS genes FROM ' . TABLE_SCREENINGS . ' AS s LEFT OUTER JOIN ' . TABLE_SCR2GENE . ' AS s2g ON (s.id = s2g.screeningid) GROUP BY s.id HAVING (genes LIKE "%ACE2%"))A',
        'SELECT SQL_CALC_FOUND_ROWS s.*, s.id AS screeningid, IF(s.variants_found = 1 AND COUNT(s2v.variantid) = 0, -1, COUNT(DISTINCT vog.id)) AS variants_found_, GROUP_CONCAT(DISTINCT s2g.geneid SEPARATOR ", ") AS genes, uo.name AS owned_by_, CONCAT_WS(";", uo.id, uo.name, uo.email, uo.institute, uo.department, IFNULL(uo.countryid, "")) AS _owner FROM ' . TABLE_SCREENINGS . ' AS s LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog ON (s2v.variantid = vog.id AND (vog.statusid >= 7)) LEFT OUTER JOIN ' . TABLE_SCR2GENE . ' AS s2g ON (s.id = s2g.screeningid) LEFT OUTER JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id) LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (s.owned_by = uo.id) WHERE (i.statusid >= 7) GROUP BY s.id LIMIT 0' => 'SELECT COUNT(*) FROM (SELECT 1 FROM ' . TABLE_SCREENINGS . ' AS s LEFT OUTER JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id) WHERE (i.statusid >= 7) GROUP BY s.id)A',
        'SELECT SQL_CALC_FOUND_ROWS s.*, s.id AS screeningid, IF(s.variants_found = 1 AND COUNT(s2v.variantid) = 0, -1, COUNT(DISTINCT vog.id)) AS variants_found_, GROUP_CONCAT(DISTINCT s2g.geneid SEPARATOR ", ") AS genes, uo.name AS owned_by_, CONCAT_WS(";", uo.id, uo.name, uo.email, uo.institute, uo.department, IFNULL(uo.countryid, "")) AS _owner FROM ' . TABLE_SCREENINGS . ' AS s LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog ON (s2v.variantid = vog.id AND (vog.statusid >= 7)) LEFT OUTER JOIN ' . TABLE_SCR2GENE . ' AS s2g ON (s.id = s2g.screeningid) LEFT OUTER JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id) LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (s.owned_by = uo.id) WHERE (i.statusid >= 7) GROUP BY s.id HAVING (genes LIKE "%ACE2%") LIMIT 0' => 'SELECT COUNT(*) FROM (SELECT GROUP_CONCAT(DISTINCT s2g.geneid SEPARATOR ", ") AS genes FROM ' . TABLE_SCREENINGS . ' AS s LEFT OUTER JOIN ' . TABLE_SCR2GENE . ' AS s2g ON (s.id = s2g.screeningid) LEFT OUTER JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id) WHERE (i.statusid >= 7) GROUP BY s.id HAVING (genes LIKE "%ACE2%"))A',

        'SELECT SQL_CALC_FOUND_ROWS t.*, g.chromosome, COUNT(DISTINCT vot.id) AS variants FROM ' . TABLE_TRANSCRIPTS . ' AS t LEFT OUTER JOIN ' . TABLE_GENES . ' AS g ON (t.geneid = g.id) LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) WHERE (t.geneid = "ACE2") GROUP BY t.id LIMIT 0' => 'SELECT COUNT(*) FROM ' . TABLE_TRANSCRIPTS . ' AS t WHERE (t.geneid = "ACE2")',
        'SELECT SQL_CALC_FOUND_ROWS t.*, vot.*, et.name as vot_effect, vog.*, a.name AS allele_, uo.name AS owned_by_, CONCAT_WS(";", uo.id, uo.name, uo.email, uo.institute, uo.department, IFNULL(uo.countryid, "")) AS _owner, dsg.id AS var_statusid, dsg.name AS var_status FROM ' . TABLE_TRANSCRIPTS . ' AS t LEFT JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) LEFT OUTER JOIN ' . TABLE_EFFECT . ' AS et ON (vot.effectid = et.id) LEFT JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id) LEFT OUTER JOIN ' . TABLE_ALLELES . ' AS a ON (vog.allele = a.id) LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (vog.owned_by = uo.id) LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS dsg ON (vog.statusid = dsg.id) WHERE vot.id IS NOT NULL AND (vog.statusid >= 7) LIMIT 0' => 'SELECT COUNT(*) FROM ' . TABLE_TRANSCRIPTS . ' AS t LEFT JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) LEFT JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id) WHERE vot.id IS NOT NULL AND (vog.statusid >= 7)',

        'SELECT SQL_CALC_FOUND_ROWS u.*, (u.login_attempts >= 3) AS locked, COUNT(CASE u2g.allow_edit WHEN 1 THEN u2g.geneid END) AS curates, c.name AS country_, GREATEST(u.level, IFNULL(CASE MAX(u2g.allow_edit) WHEN 1 THEN 5 WHEN 0 THEN 3 END, 1)) AS level, CASE GREATEST(u.level, IFNULL(CASE MAX(u2g.allow_edit) WHEN 1 THEN 5 WHEN 0 THEN 3 END, 1)) WHEN "9" THEN "9Database administrator" WHEN "7" THEN "7Manager" WHEN "5" THEN "5Curator" WHEN "4" THEN "4Submitter (data owner)" WHEN "3" THEN "3Collaborator" WHEN "1" THEN "1Submitter" END AS level_ FROM ' . TABLE_USERS . ' AS u LEFT OUTER JOIN ' . TABLE_CURATES . ' AS u2g ON (u.id = u2g.userid) LEFT OUTER JOIN ' . TABLE_COUNTRIES . ' AS c ON (u.countryid = c.id) WHERE u.id > 0 GROUP BY u.id LIMIT 0' => 'SELECT COUNT(*) FROM ' . TABLE_USERS . ' AS u WHERE u.id > 0',
        'SELECT SQL_CALC_FOUND_ROWS u.*, (u.login_attempts >= 3) AS locked, COUNT(CASE u2g.allow_edit WHEN 1 THEN u2g.geneid END) AS curates, c.name AS country_, GREATEST(u.level, IFNULL(CASE MAX(u2g.allow_edit) WHEN 1 THEN 5 WHEN 0 THEN 3 END, 1)) AS level, CASE GREATEST(u.level, IFNULL(CASE MAX(u2g.allow_edit) WHEN 1 THEN 5 WHEN 0 THEN 3 END, 1)) WHEN "9" THEN "9Database administrator" WHEN "7" THEN "7Manager" WHEN "5" THEN "5Curator" WHEN "4" THEN "4Submitter (data owner)" WHEN "3" THEN "3Collaborator" WHEN "1" THEN "1Submitter" END AS level_ FROM ' . TABLE_USERS . ' AS u LEFT OUTER JOIN ' . TABLE_CURATES . ' AS u2g ON (u.id = u2g.userid) LEFT OUTER JOIN ' . TABLE_COUNTRIES . ' AS c ON (u.countryid = c.id) WHERE u.id > 0 GROUP BY u.id HAVING (curates > 0) LIMIT 0' => 'SELECT COUNT(*) FROM (SELECT COUNT(CASE u2g.allow_edit WHEN 1 THEN u2g.geneid END) AS curates FROM ' . TABLE_USERS . ' AS u LEFT OUTER JOIN ' . TABLE_CURATES . ' AS u2g ON (u.id = u2g.userid) WHERE u.id > 0 GROUP BY u.id HAVING (curates > 0))A',
        'SELECT SQL_CALC_FOUND_ROWS u.*, (u.login_attempts >= 3) AS locked, COUNT(CASE u2g.allow_edit WHEN 1 THEN u2g.geneid END) AS curates, c.name AS country_, GREATEST(u.level, IFNULL(CASE MAX(u2g.allow_edit) WHEN 1 THEN 5 WHEN 0 THEN 3 END, 1)) AS level, CASE GREATEST(u.level, IFNULL(CASE MAX(u2g.allow_edit) WHEN 1 THEN 5 WHEN 0 THEN 3 END, 1)) WHEN "9" THEN "9Database administrator" WHEN "7" THEN "7Manager" WHEN "5" THEN "5Curator" WHEN "4" THEN "4Submitter (data owner)" WHEN "3" THEN "3Collaborator" WHEN "1" THEN "1Submitter" END AS level_ FROM ' . TABLE_USERS . ' AS u LEFT OUTER JOIN ' . TABLE_CURATES . ' AS u2g ON (u.id = u2g.userid) LEFT OUTER JOIN ' . TABLE_COUNTRIES . ' AS c ON (u.countryid = c.id) WHERE u.id > 0 GROUP BY u.id HAVING (curates > 0) AND (level_ LIKE "%Curator%") LIMIT 0' => 'SELECT COUNT(*) FROM (SELECT COUNT(CASE u2g.allow_edit WHEN 1 THEN u2g.geneid END) AS curates, CASE GREATEST(u.level, IFNULL(CASE MAX(u2g.allow_edit) WHEN 1 THEN 5 WHEN 0 THEN 3 END, 1)) WHEN "9" THEN "9Database administrator" WHEN "7" THEN "7Manager" WHEN "5" THEN "5Curator" WHEN "4" THEN "4Submitter (data owner)" WHEN "3" THEN "3Collaborator" WHEN "1" THEN "1Submitter" END AS level_ FROM ' . TABLE_USERS . ' AS u LEFT OUTER JOIN ' . TABLE_CURATES . ' AS u2g ON (u.id = u2g.userid) WHERE u.id > 0 GROUP BY u.id HAVING (curates > 0) AND (level_ LIKE "%Curator%"))A',

        'SELECT SQL_CALC_FOUND_ROWS vog.*, GROUP_CONCAT(s2v.screeningid SEPARATOR ",") AS screeningids, a.name AS allele_, e.name AS effect, uo.name AS owned_by_, CONCAT_WS(";", uo.id, uo.name, uo.email, uo.institute, uo.department, IFNULL(uo.countryid, "")) AS _owner, ds.name AS status FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid) LEFT OUTER JOIN ' . TABLE_ALLELES . ' AS a ON (vog.allele = a.id) LEFT OUTER JOIN ' . TABLE_EFFECT . ' AS e ON (vog.effectid = e.id) LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (vog.owned_by = uo.id) LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS ds ON (vog.statusid = ds.id) LEFT OUTER JOIN ' . TABLE_CHROMOSOMES . ' AS chr ON (vog.chromosome = chr.name) WHERE (vog.statusid >= 7) GROUP BY vog.id LIMIT 0' => 'SELECT COUNT(*) FROM ' . TABLE_VARIANTS . ' AS vog WHERE (vog.statusid >= 7)',

        'SELECT SQL_CALC_FOUND_ROWS vot.*, et.name as vot_effect, vot.id AS row_id, vog.*, a.name AS allele_, dsg.id AS var_statusid, dsg.name AS var_status, GROUP_CONCAT(DISTINCT `Screening/Template` SEPARATOR ";") AS `Screening/Template`, GROUP_CONCAT(DISTINCT `Screening/Technique` SEPARATOR ";") AS `Screening/Technique`, i.*, GROUP_CONCAT(DISTINCT IF(CASE d.symbol WHEN "-" THEN "" ELSE d.symbol END = "", d.name, d.symbol) ORDER BY (d.symbol != "" AND d.symbol != "-") DESC, d.symbol, d.name SEPARATOR ", ") AS diseases_, uo.name AS owned_by_, CONCAT_WS(";", uo.id, uo.name, uo.email, uo.institute, uo.department, IFNULL(uo.countryid, "")) AS _owner, dsi.id AS ind_statusid, dsi.name AS ind_status FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot LEFT OUTER JOIN ' . TABLE_EFFECT . ' AS et ON (vot.effectid = et.id) LEFT JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id) LEFT OUTER JOIN ' . TABLE_ALLELES . ' AS a ON (vog.allele = a.id) LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS dsg ON (vog.statusid = dsg.id) LEFT JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid) LEFT JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id) LEFT JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id AND (i.statusid >= 7)) LEFT OUTER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (i.id = i2d.individualid) LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (i2d.diseaseid = d.id) LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (i.owned_by = uo.id) LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS dsi ON (i.statusid = dsi.id) WHERE (vog.statusid >= 7) AND (vot.transcriptid = "00608") GROUP BY vot.id LIMIT 0' => 'SELECT COUNT(*) FROM (SELECT 1 FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot LEFT JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id) WHERE (vog.statusid >= 7) AND (vot.transcriptid = "00608") GROUP BY vot.id)A',
        'SELECT SQL_CALC_FOUND_ROWS vot.*, et.name as vot_effect, vot.id AS row_id, vog.*, a.name AS allele_, dsg.id AS var_statusid, dsg.name AS var_status, GROUP_CONCAT(DISTINCT `Screening/Template` SEPARATOR ";") AS `Screening/Template`, GROUP_CONCAT(DISTINCT `Screening/Technique` SEPARATOR ";") AS `Screening/Technique`, i.*, GROUP_CONCAT(DISTINCT IF(CASE d.symbol WHEN "-" THEN "" ELSE d.symbol END = "", d.name, d.symbol) ORDER BY (d.symbol != "" AND d.symbol != "-") DESC, d.symbol, d.name SEPARATOR ", ") AS diseases_, uo.name AS owned_by_, CONCAT_WS(";", uo.id, uo.name, uo.email, uo.institute, uo.department, IFNULL(uo.countryid, "")) AS _owner, dsi.id AS ind_statusid, dsi.name AS ind_status FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot LEFT OUTER JOIN ' . TABLE_EFFECT . ' AS et ON (vot.effectid = et.id) LEFT JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id) LEFT OUTER JOIN ' . TABLE_ALLELES . ' AS a ON (vog.allele = a.id) LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS dsg ON (vog.statusid = dsg.id) LEFT JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid) LEFT JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id) LEFT JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id) LEFT OUTER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (i.id = i2d.individualid) LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (i2d.diseaseid = d.id) LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (i.owned_by = uo.id) LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS dsi ON (i.statusid = dsi.id) WHERE (vot.transcriptid = "00608") GROUP BY vot.id ORDER BY position_c_start ASC, position_c_start_intron ASC, position_c_end ASC, position_c_end_intron ASC, `VariantOnTranscript/DNA` ASC LIMIT 100 OFFSET 0' => 'SELECT COUNT(*) FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot WHERE (vot.transcriptid = "00608")',
        'SELECT SQL_CALC_FOUND_ROWS vot.*, et.name as vot_effect, vot.id AS row_id, vog.*, a.name AS allele_, uo.name AS owned_by_, CONCAT_WS(";", uo.id, uo.name, uo.email, uo.institute, uo.department, IFNULL(uo.countryid, "")) AS _owner, dsg.id AS var_statusid, dsg.name AS var_status FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot LEFT OUTER JOIN ' . TABLE_EFFECT . ' AS et ON (vot.effectid = et.id) LEFT JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id) LEFT OUTER JOIN ' . TABLE_ALLELES . ' AS a ON (vog.allele = a.id) LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (vog.owned_by = uo.id) LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS dsg ON (vog.statusid = dsg.id) WHERE (vog.statusid >= 7) AND (vot.transcriptid = "00608") AND (vot.position_c_start = "584") AND (vot.position_c_start_intron = "-71") AND (vot.position_c_end = "584") AND (vot.position_c_end_intron = "-71") AND (TRIM(BOTH "?" FROM TRIM(LEADING "c." FROM REPLACE(REPLACE(`VariantOnTranscript/DNA`, ")", ""), "(", ""))) = "584-71A>G") GROUP BY vot.id LIMIT 0' => 'SELECT COUNT(*) FROM (SELECT 1 FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot LEFT JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id) WHERE (vog.statusid >= 7) AND (vot.transcriptid = "00608") AND (vot.position_c_start = "584") AND (vot.position_c_start_intron = "-71") AND (vot.position_c_end = "584") AND (vot.position_c_end_intron = "-71") AND (TRIM(BOTH "?" FROM TRIM(LEADING "c." FROM REPLACE(REPLACE(`VariantOnTranscript/DNA`, ")", ""), "(", ""))) = "584-71A>G") GROUP BY vot.id)A',
        'SELECT SQL_CALC_FOUND_ROWS vot.*, t.geneid, t.id_ncbi, e.name AS effect, ds.name AS status FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot INNER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id) LEFT OUTER JOIN ' . TABLE_EFFECT . ' AS e ON (vot.effectid = e.id) LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS ds ON (vog.statusid = ds.id) LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (t.id = vot.transcriptid) WHERE (vog.statusid >= 7) AND (vot.id = "0000000804") LIMIT 0' => 'SELECT COUNT(*) FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot INNER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id) WHERE (vog.statusid >= 7) AND (vot.id = "0000000804")',
        'SELECT SQL_CALC_FOUND_ROWS vot.*, vot.id AS row_id, TRIM(BOTH "?" FROM TRIM(LEADING "c." FROM REPLACE(REPLACE(`VariantOnTranscript/DNA`, ")", ""), "(", ""))) AS vot_clean_dna_change, GROUP_CONCAT(DISTINCT et.name SEPARATOR ", ") AS vot_effect, GROUP_CONCAT(DISTINCT NULLIF(uo.name, "") SEPARATOR ", ") AS owned_by_, GROUP_CONCAT(DISTINCT CONCAT_WS(";", uo.id, uo.name, uo.email, uo.institute, uo.department, IFNULL(uo.countryid, "")) SEPARATOR ";;") AS __owner, GROUP_CONCAT(DISTINCT NULLIF(dsg.id, "") ORDER BY dsg.id ASC SEPARATOR ", ") AS var_statusid, GROUP_CONCAT(DISTINCT NULLIF(dsg.name, "") SEPARATOR ", ") AS var_status, COUNT(`VariantOnTranscript/DNA`) AS vot_reported, GROUP_CONCAT(DISTINCT NULLIF(`VariantOnTranscript/Exon`, "") SEPARATOR ";;") AS `VariantOnTranscript/Exon`, GROUP_CONCAT(DISTINCT NULLIF(`VariantOnTranscript/DNA`, "") SEPARATOR ";;") AS `VariantOnTranscript/DNA`, GROUP_CONCAT(DISTINCT NULLIF(`VariantOnTranscript/RNA`, "") SEPARATOR ";;") AS `VariantOnTranscript/RNA`, GROUP_CONCAT(DISTINCT NULLIF(`VariantOnTranscript/Protein`, "") SEPARATOR ";;") AS `VariantOnTranscript/Protein`, vog.*, a.name AS allele_, eg.name AS vog_effect, GROUP_CONCAT(DISTINCT NULLIF(`VariantOnGenome/DNA`, "") SEPARATOR ";;") AS `VariantOnGenome/DNA`, GROUP_CONCAT(DISTINCT NULLIF(`VariantOnGenome/Reference`, "") SEPARATOR ";;") AS `VariantOnGenome/Reference`, GROUP_CONCAT(DISTINCT NULLIF(`VariantOnGenome/Frequency`, "") SEPARATOR ";;") AS `VariantOnGenome/Frequency` FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot LEFT OUTER JOIN ' . TABLE_EFFECT . ' AS et ON (vot.effectid = et.id) LEFT JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id) LEFT OUTER JOIN ' . TABLE_ALLELES . ' AS a ON (vog.allele = a.id) LEFT OUTER JOIN ' . TABLE_EFFECT . ' AS eg ON (vog.effectid = eg.id) LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (vog.owned_by = uo.id) LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS dsg ON (vog.statusid = dsg.id) WHERE (vog.statusid >= 7) AND (vot.transcriptid = "00608") GROUP BY `position_c_start`, `position_c_start_intron`, `position_c_end`, `position_c_end_intron`, vot_clean_dna_change LIMIT 0' => 'SELECT COUNT(*) FROM (SELECT TRIM(BOTH "?" FROM TRIM(LEADING "c." FROM REPLACE(REPLACE(`VariantOnTranscript/DNA`, ")", ""), "(", ""))) AS vot_clean_dna_change FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot LEFT JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id) WHERE (vog.statusid >= 7) AND (vot.transcriptid = "00608") GROUP BY `position_c_start`, `position_c_start_intron`, `position_c_end`, `position_c_end_intron`, vot_clean_dna_change)A',

        // Some queries can be perfected... (more JOINS can be cleaned up, but not from the right, so we'll need to check the JOIN conditions).
    );

function lovd_splitSQL ($sSQL)
{
    // Function splits the given SQL in the structure that LOVD uses in the
    // objects; an array with SELECT, FROM, WHERE, GROUP_BY, HAVING, ORDER_BY
    // and a LIMIT key.

    $aSQL = array(
        'SELECT' => '',
        'FROM' => '',
        'WHERE' => '',
        'GROUP_BY' => '',
        'HAVING' => '',
        'ORDER_BY' => '',
        'LIMIT' => '',
    );

    foreach (array_reverse(array_keys($aSQL)) as $sClause) {
        if (($nPosition = strrpos($sSQL, str_replace('_', ' ', $sClause))) !== false) {
            $sPart = ltrim(substr($sSQL, $nPosition+strlen($sClause)));
            // Subqueries can messing up the parsing.
            if ($sClause == 'ORDER_BY' && strpos($sPart, ' FROM ') !== false) {
                // We've matched the wrong one...
                $aSQL[$sClause] = '';
                continue;
            }
            $aSQL[$sClause] = $sPart;
            $sSQL = rtrim(substr($sSQL, 0, $nPosition));
        }
    }

    return $aSQL;
}





// Loop through the queries. Run the optimizer, and compare the resulting query
//  with what we expect. Also run both queries and test the time needed to run
//  each; the modified query should be faster. Also the number of results should
//  be the same.
// We need the object, but we really don't care which one.
require ROOT_PATH . 'class/object_users.php';
$o = new LOVD_User();
$i = 0;
foreach ($aSQL as $sSQLInput => $sSQLExpectedOutput) {
    // Query counter, starting at 1.
    $i ++;

    // Check if the input query indeed still has SQL_CALC_FOUND_ROWS, otherwise
    //  we can't test.
    assert("strpos('$sSQLInput', 'SQL_CALC_FOUND_ROWS') !== false");

    // Check if outcome is as expected.
    $sSQLOutput = $o->getRowCountForViewList(lovd_splitSQL($sSQLInput), array(), true);
    assert("'$sSQLOutput' == '$sSQLExpectedOutput'");

    // If we're here, the output was as expected. Now run both queries, and time
    //  them. Because timing may vary, we'll run it a maximum of 5 times if it's
    //  not faster than the original. If after 5 tries it's still not, then
    //  we'll bail out.
    $nTries = 0;
    do {
        $nTries ++; // Starts at 1.
        $t = microtime(true);
        $_DB->query(preg_replace('/^SELECT /', 'SELECT SQL_NO_CACHE ', $sSQLInput));
        $nFoundInput = $_DB->query('SELECT FOUND_ROWS()')->fetchColumn();
        $tSQLInput = microtime(true) - $t;

        $t = microtime(true);
        if (strpos($sSQLOutput, 'SQL_CALC_FOUND_ROWS') !== false) {
            // We still had to use SQL_CALC_FOUND_ROWS()...
            $_DB->query(preg_replace('/^SELECT /', 'SELECT SQL_NO_CACHE ', $sSQLOutput));
            $nFoundOutput = $_DB->query('SELECT FOUND_ROWS()')->fetchColumn();
        } else {
            $nFoundOutput = $_DB->query(preg_replace('/^SELECT /', 'SELECT SQL_NO_CACHE ', $sSQLOutput))->fetchColumn();
        }
        $tSQLOutput = microtime(true) - $t;
    } while ($tSQLOutput > $tSQLInput || $nTries >= 5);

    printf('Query %02d. Size remaining %4d/%4d bytes. Run time: %3d%% (%.5f/%.5f) = %.5f s in %d tries. Rows: %5d.' . "\n",
        $i, strlen($sSQLOutput), strlen($sSQLInput), ($tSQLOutput/$tSQLInput)*100, $tSQLOutput, $tSQLInput, ($tSQLOutput-$tSQLInput), $nTries, $nFoundOutput);

    // Run the assertions on query time and results.
    assert("$tSQLOutput < $tSQLInput");
    assert("$nFoundInput == $nFoundOutput");
    flush();
}
die('Complete, all successful.');
?>
