<?php
// DMD_SPECIFIC: REMEMBER. If you add code that adds SQL for all genes, you MUST add the key first to the large array. Otherwise, the order in which upgrades are done is WRONG!!!
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-01-14
 * Modified    : 2012-04-02
 * For LOVD    : 3.0-beta-04
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.NL>
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

// Don't allow direct access.
if (!defined('ROOT_PATH')) {
    exit;
}

// How are the versions related?
$sCalcVersionFiles = lovd_calculateVersion($_SETT['system']['version']);
$sCalcVersionDB = lovd_calculateVersion($_STAT['version']);

if ($sCalcVersionFiles != $sCalcVersionDB) {
    // Version of files are not equal to version of database backend.

    // Increased execution time to help perform large upgrades.
    if ((int) ini_get('max_execution_time') < 60) {
        @set_time_limit(60);
    }

    // DB version greater than file version... then we have a problem.
    if ($sCalcVersionFiles < $sCalcVersionDB) {
        lovd_displayError('UpgradeError', 'Database version ' . $_STAT['version'] . ' found newer than file version ' . $_SETT['system']['version']);
    }

    define('PAGE_TITLE', 'Upgrading LOVD...');
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    print('      Please wait while LOVD is upgrading the database backend from ' . $_STAT['version'] . ' to ' . $_SETT['system']['version'] . '.<BR><BR>' . "\n");

    // Array of changes.
    $aUpdates =
             array(
                    '3.0-pre-21' =>
                         array(
                                'UPGRADING TO 3.0-pre-21 IS NOT SUPPORTED. UNINSTALL LOVD 3.0 AND REINSTALL TO GET THE LATEST.',
                              ),
                    '3.0-alpha-01' =>
                         array(
                                'ALTER TABLE ' . TABLE_INDIVIDUALS . ' DROP COLUMN edited_date',
                                'ALTER TABLE ' . TABLE_INDIVIDUALS . ' ADD COLUMN edited_date DATETIME AFTER edited_by',
                                'ALTER TABLE ' . TABLE_VARIANTS . ' DROP COLUMN edited_date',
                                'ALTER TABLE ' . TABLE_VARIANTS . ' ADD COLUMN edited_date DATETIME AFTER edited_by',
                                'ALTER TABLE ' . TABLE_PHENOTYPES . ' DROP COLUMN edited_date',
                                'ALTER TABLE ' . TABLE_PHENOTYPES . ' ADD COLUMN edited_date DATETIME AFTER edited_by',
                                'ALTER TABLE ' . TABLE_SCREENINGS . ' DROP COLUMN edited_date',
                                'ALTER TABLE ' . TABLE_SCREENINGS . ' ADD COLUMN edited_date DATETIME AFTER edited_by',
/////////////////// DMD_SPECIFIC: I would expect these to fail if I don't remove the FKs first. But they don't.
                                'ALTER TABLE ' . TABLE_GENES . ' MODIFY COLUMN id VARCHAR(20) NOT NULL',
                                'ALTER TABLE ' . TABLE_CURATES . ' MODIFY COLUMN geneid VARCHAR(20) NOT NULL',
                                'ALTER TABLE ' . TABLE_TRANSCRIPTS . ' MODIFY COLUMN geneid VARCHAR(20) NOT NULL',
                                'ALTER TABLE ' . TABLE_GEN2DIS . ' MODIFY COLUMN geneid VARCHAR(20) NOT NULL',
                                'ALTER TABLE ' . TABLE_SCR2GENE . ' MODIFY COLUMN geneid VARCHAR(20) NOT NULL',
                                'ALTER TABLE ' . TABLE_SHARED_COLS . ' MODIFY COLUMN geneid VARCHAR(20)',
                                'ALTER TABLE ' . TABLE_HITS . ' MODIFY COLUMN geneid VARCHAR(20) NOT NULL',
                              ),
                    '3.0-alpha-02' =>
                         array(
                                'UPDATE ' . TABLE_COLS . ' SET select_options = "Unknown\r\nBESS = Base Excision Sequence Scanning\r\nCMC = Chemical Mismatch Cleavage\r\nCSCE = Conformation sensitive capillary electrophoresis\r\nDGGE = Denaturing-Gradient Gel-Electrophoresis\r\nDHPLC = Denaturing High-Performance Liquid Chromatography\r\nDOVAM = Detection Of Virtually All Mutations (SSCA variant)\r\nDSCA = Double-Strand DNA Conformation Analysis\r\nHD = HeteroDuplex analysis\r\nIHC = Immuno-Histo-Chemistry\r\nmPCR = multiplex PCR\r\nMAPH = Multiplex Amplifiable Probe Hybridisation\r\nMLPA = Multiplex Ligation-dependent Probe Amplification\r\nNGS = Next Generation Sequencing\r\nPAGE = Poly-Acrylamide Gel-Electrophoresis\r\nPCR = Polymerase Chain Reaction\r\nPTT = Protein Truncation Test\r\nRT-PCR = Reverse Transcription and PCR\r\nSEQ = SEQuencing\r\nSouthern = Southern Blotting\r\nSSCA = Single-Strand DNA Conformation Analysis (SSCP)\r\nWestern = Western Blotting" WHERE id = "Screening/Technique"',
                                'UPDATE ' . TABLE_COLS . ' SET mandatory = 1 WHERE id IN ("VariantOnTranscript/RNA", "VariantOnTranscript/Protein")',
                                'UPDATE ' . TABLE_SHARED_COLS . ' SET mandatory = 1 WHERE colid IN ("VariantOnTranscript/RNA", "VariantOnTranscript/Protein")',
                                'ALTER TABLE ' . TABLE_INDIVIDUALS . ' ADD COLUMN panelid MEDIUMINT(8) UNSIGNED ZEROFILL AFTER id',
                                'ALTER TABLE ' . TABLE_INDIVIDUALS . ' ADD CONSTRAINT ' . TABLE_INDIVIDUALS . '_fk_panelid FOREIGN KEY (panelid) REFERENCES ' . TABLE_INDIVIDUALS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                'ALTER TABLE ' . TABLE_INDIVIDUALS . ' ADD COLUMN panel_size MEDIUMINT UNSIGNED NOT NULL DEFAULT 1 AFTER panelid',
                                'DELETE FROM ' . TABLE_COLS . ' WHERE id = "VariantOnTranscript/DBID"',
                                'UPDATE ' . TABLE_COLS . ' SET description_form = "This ID is used to group multiple instances of the same variant together. The ID starts with the gene symbol of the transcript most influenced by the variant or otherwise the closest gene, followed by an underscore (_) and the ID code, usually six digits.", preg_pattern = "/^[A-Z][A-Z0-9]+_[0-9]{6}\\\\b/" WHERE id = "VariantOnGenome/DBID"',
                                'ALTER TABLE ' . TABLE_USERS . ' MODIFY COLUMN password CHAR(50) NOT NULL',
                                'ALTER TABLE ' . TABLE_USERS . ' MODIFY COLUMN password_autogen CHAR(50)',
                                'ALTER TABLE ' . TABLE_USERS . ' DROP COLUMN current_db',
                              ),
                    '3.0-alpha-03' =>
                         array(
                                'UPDATE ' . TABLE_SOURCES . ' SET url = "http://www.omim.org/entry/{{ ID }}" WHERE id = "omim" AND url = "http://www.ncbi.nlm.nih.gov/omim/{{ ID }}"',
                                'UPDATE ' . TABLE_LINKS . ' SET replace_text = "<A href=\"http://www.omim.org/entry/[1]#[2]\" target=\"_blank\">(OMIM [2])</A>" WHERE id = 4 AND replace_text = "<A href=\"http://www.ncbi.nlm.nih.gov/omim/[1]#[1]Variants[2]\" target=\"_blank\">(OMIM [2])</A>"',
                                'ALTER TABLE ' . TABLE_PHENOTYPES . ' ADD COLUMN statusid TINYINT(1) UNSIGNED AFTER ownerid',
                                'ALTER TABLE ' . TABLE_PHENOTYPES . ' ADD INDEX (statusid)',
                                'ALTER TABLE ' . TABLE_PHENOTYPES . ' ADD CONSTRAINT ' . TABLE_PHENOTYPES . '_fk_statusid FOREIGN KEY (statusid) REFERENCES ' . TABLE_DATA_STATUS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                'ALTER TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' DROP COLUMN edited_date',
                                'UPDATE ' . TABLE_COLS . ' SET form_type = "ID||text|15" WHERE id = "VariantOnGenome/DBID" AND form_type = "ID||text|40"',
                              ),
                    '3.0-alpha-04' =>
                         array(
                                'DELETE FROM ' . TABLE_DATA_STATUS . ' WHERE id IN (' . STATUS_IN_PROGRESS . ', ' . STATUS_PENDING . ')',
                                'INSERT INTO ' . TABLE_DATA_STATUS . ' VALUES (' . STATUS_IN_PROGRESS . ', "In progress")',
                                'INSERT INTO ' . TABLE_DATA_STATUS . ' VALUES (' . STATUS_PENDING . ', "Pending")',
                                'UPDATE ' . TABLE_COLS . ' SET description_form = "This ID is used to group multiple instances of the same variant together. The ID starts with the gene symbol of the transcript most influenced by the variant or otherwise the closest gene, followed by an underscore (_) and the ID code, which consists of six digits." WHERE id = "VariantOnGenome/DBID" AND description_form = "This ID is used to group multiple instances of the same variant together. The ID starts with the gene symbol of the transcript most influenced by the variant or otherwise the closest gene, followed by an underscore (_) and the ID code, usually six digits."',
                              ),
                    '3.0-alpha-05' =>
                         array(
                                'ALTER TABLE ' . TABLE_INDIVIDUALS . ' DROP FOREIGN KEY ' . TABLE_INDIVIDUALS . '_fk_ownerid',
                                'ALTER TABLE ' . TABLE_INDIVIDUALS . ' DROP KEY ownerid',
                                'ALTER TABLE ' . TABLE_INDIVIDUALS . ' CHANGE ownerid owned_by SMALLINT(5) UNSIGNED ZEROFILL',
                                'ALTER TABLE ' . TABLE_INDIVIDUALS . ' ADD INDEX (owned_by)',
                                'ALTER TABLE ' . TABLE_INDIVIDUALS . ' ADD CONSTRAINT ' . TABLE_INDIVIDUALS . '_fk_owned_by FOREIGN KEY (owned_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',

                                'ALTER TABLE ' . TABLE_VARIANTS . ' DROP FOREIGN KEY ' . TABLE_VARIANTS . '_fk_ownerid',
                                'ALTER TABLE ' . TABLE_VARIANTS . ' DROP KEY ownerid',
                                'ALTER TABLE ' . TABLE_VARIANTS . ' CHANGE ownerid owned_by SMALLINT(5) UNSIGNED ZEROFILL',
                                'ALTER TABLE ' . TABLE_VARIANTS . ' ADD INDEX (owned_by)',
                                'ALTER TABLE ' . TABLE_VARIANTS . ' ADD CONSTRAINT ' . TABLE_VARIANTS . '_fk_owned_by FOREIGN KEY (owned_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',

                                'ALTER TABLE ' . TABLE_PHENOTYPES . ' DROP FOREIGN KEY ' . TABLE_PHENOTYPES . '_fk_ownerid',
                                'ALTER TABLE ' . TABLE_PHENOTYPES . ' DROP KEY ownerid',
                                'ALTER TABLE ' . TABLE_PHENOTYPES . ' CHANGE ownerid owned_by SMALLINT(5) UNSIGNED ZEROFILL',
                                'ALTER TABLE ' . TABLE_PHENOTYPES . ' ADD INDEX (owned_by)',
                                'ALTER TABLE ' . TABLE_PHENOTYPES . ' ADD CONSTRAINT ' . TABLE_PHENOTYPES . '_fk_owned_by FOREIGN KEY (owned_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',

                                'ALTER TABLE ' . TABLE_SCREENINGS . ' DROP FOREIGN KEY ' . TABLE_SCREENINGS . '_fk_ownerid',
                                'ALTER TABLE ' . TABLE_SCREENINGS . ' DROP KEY ownerid',
                                'ALTER TABLE ' . TABLE_SCREENINGS . ' CHANGE ownerid owned_by SMALLINT(5) UNSIGNED ZEROFILL',
                                'ALTER TABLE ' . TABLE_SCREENINGS . ' ADD INDEX (owned_by)',
                                'ALTER TABLE ' . TABLE_SCREENINGS . ' ADD CONSTRAINT ' . TABLE_SCREENINGS . '_fk_owned_by FOREIGN KEY (owned_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                              ),
                    '3.0-alpha-07' =>
                        array(
                                'ALTER TABLE ' . TABLE_TRANSCRIPTS . ' ADD COLUMN id_mutalyzer TINYINT(3) UNSIGNED ZEROFILL AFTER name',
                                'ALTER TABLE ' . TABLE_SCREENINGS . ' ADD COLUMN variants_found BOOLEAN NOT NULL DEFAULT 1 AFTER individualid',

                                'ALTER TABLE ' . TABLE_VARIANTS . ' DROP FOREIGN KEY ' . TABLE_VARIANTS . '_fk_pathogenicid',
                                'ALTER TABLE ' . TABLE_VARIANTS . ' DROP KEY pathogenicid',
                                'ALTER TABLE ' . TABLE_VARIANTS . ' CHANGE pathogenicid effectid TINYINT(2) UNSIGNED ZEROFILL',
                                'ALTER TABLE ' . TABLE_VARIANTS . ' ADD INDEX (effectid)',

                                'ALTER TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' DROP FOREIGN KEY ' . TABLE_VARIANTS_ON_TRANSCRIPTS . '_fk_pathogenicid',
                                'ALTER TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' DROP KEY pathogenicid',
                                'ALTER TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' CHANGE pathogenicid effectid TINYINT(2) UNSIGNED ZEROFILL',
                                'ALTER TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' ADD INDEX (effectid)',

                                'RENAME TABLE ' . TABLE_PATHOGENIC . ' TO ' . TABLE_EFFECT,
                                'ALTER TABLE ' . TABLE_VARIANTS . ' ADD CONSTRAINT ' . TABLE_VARIANTS . '_fk_effectid FOREIGN KEY (effectid) REFERENCES ' . TABLE_EFFECT . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                'ALTER TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' ADD CONSTRAINT ' . TABLE_VARIANTS_ON_TRANSCRIPTS . '_fk_effectid FOREIGN KEY (effectid) REFERENCES ' . TABLE_EFFECT . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                'UPDATE ' . TABLE_VARIANTS . ' SET effectid = 55 WHERE effectid < 11 OR effectid IS NULL',
                                'UPDATE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' SET effectid = 55 WHERE effectid < 11 OR effectid IS NULL',

                                'UPDATE ' . TABLE_LINKS . ' SET replace_text = "<A href=\"http://www.ncbi.nlm.nih.gov/SNP/snp_ref.cgi?rs=[1]\" target=\"_blank\">dbSNP</A>" WHERE id = 2 AND replace_text = "<A href=\"http://www.ncbi.nlm.nih.gov/SNP/snp_ref.cgi?type=rs&amp;rs=rs[1]\" target=\"_blank\">dbSNP</A>"',
                             ),
                    '3.0-alpha-07b' =>
                        array(
                                'UPDATE ' . TABLE_COLS . ' SET form_type = "ID|This ID is used to group multiple instances of the same variant together. The ID starts with the gene symbol of the transcript most influenced by the variant or otherwise the closest gene, followed by an underscore (_) and the 6 digit ID code.|text|20" WHERE id = "VariantOnGenome/DBID"',
                                'UPDATE ' . TABLE_COLS . ' SET description_form = "NOTE: This field will be predicted and filled in by LOVD, if left empty." WHERE id = "VariantOnGenome/DBID"',
                                'UPDATE ' . TABLE_COLS . ' SET preg_pattern = "/^(chr(\\\\d{1,2}|[XYM])|(C(\\\\d{1,2}|[XYM])orf\\\\d+-|[A-Z][A-Z0-9]+-)?(C(\\\\d{1,2}|[XYM])orf\\\\d+|[A-Z][A-Z0-9]+))_[0-9]{6}\\\\b/" WHERE id = "VariantOnGenome/DBID"',
                                'UPDATE ' . TABLE_COLS . ' SET description_legend_short = REPLACE(description_legend_short, "Database", "DataBase"), description_legend_full = REPLACE(description_legend_full, "Database", "DataBase") WHERE id = "VariantOnGenome/DBID"',
                                'INSERT INTO ' . TABLE_USERS . '(name, created_date) VALUES("LOVD", NOW())',
                                'UPDATE ' . TABLE_USERS . ' SET id = 0, created_by = 0 WHERE username = ""',
                             ),
                    '3.0-alpha-07c' =>
                        array(
                                'ALTER TABLE ' . TABLE_VARIANTS . ' ADD COLUMN mapping_flags TINYINT(3) UNSIGNED NOT NULL AFTER type',
                                'ALTER TABLE ' . TABLE_USERS . ' AUTO_INCREMENT = 1',
                                'UPDATE ' . TABLE_COLS . ' SET edited_by = 0 WHERE id = "VariantOnGenome/DBID"',
                                'UPDATE ' . TABLE_COLS . ' SET width = 80 WHERE id = "VariantOnGenome/DBID"',
                             ),
                    '3.0-alpha-07d' =>
                        array(
                                'ALTER TABLE ' . TABLE_GENES . ' MODIFY COLUMN chromosome VARCHAR(2)',
                                'ALTER TABLE ' . TABLE_GENES . ' ADD INDEX (chromosome)',
                                'ALTER TABLE ' . TABLE_VARIANTS . ' MODIFY COLUMN chromosome VARCHAR(2)',
                                'CREATE TABLE ' . TABLE_CHROMOSOMES . ' (name VARCHAR(2) NOT NULL, sort_id TINYINT(3) UNSIGNED NOT NULL, hg18_id_ncbi VARCHAR(20) NOT NULL, hg19_id_ncbi VARCHAR(20) NOT NULL, PRIMARY KEY (name)) ENGINE=InnoDB, DEFAULT CHARACTER SET utf8',
                'chr_values' => 'Reserved for the insert query of the new chromosome table. This will be added later in this script.',
                                'ALTER TABLE ' . TABLE_GENES . ' ADD CONSTRAINT ' . TABLE_GENES . '_fk_chromosome FOREIGN KEY (chromosome) REFERENCES ' . TABLE_CHROMOSOMES . ' (name) ON DELETE SET NULL ON UPDATE CASCADE',
                                'ALTER TABLE ' . TABLE_VARIANTS . ' ADD CONSTRAINT ' . TABLE_VARIANTS . '_fk_chromosome FOREIGN KEY (chromosome) REFERENCES ' . TABLE_CHROMOSOMES . ' (name) ON DELETE SET NULL ON UPDATE CASCADE',
                             ),
                    '3.0-beta-02' =>
                        array(
                                'UPDATE ' . TABLE_COLS . ' SET form_type = "Frequency||text|10" WHERE id = "VariantOnGenome/Frequency" AND form_type = "Frequency||text|15"',
                                'ALTER TABLE ' . TABLE_TRANSCRIPTS . ' ADD UNIQUE (id_ncbi)',
                             ),
                    '3.0-beta-02b' =>
                        array(
                                'ALTER TABLE ' . TABLE_CONFIG . ' ADD COLUMN proxy_host VARCHAR(255) NOT NULL AFTER refseq_build',
                                'ALTER TABLE ' . TABLE_CONFIG . ' ADD COLUMN proxy_port SMALLINT(5) UNSIGNED AFTER proxy_host',
                             ),
                    '3.0-beta-02c' =>
                        array(
                                'ALTER TABLE ' . TABLE_GENES . ' ADD COLUMN imprinting VARCHAR(10) NOT NULL DEFAULT "unknown" AFTER chrom_band',
                             ),
                    '3.0-beta-02d' =>
                        array(
                                'INSERT INTO ' . TABLE_COLS . ' VALUES ("VariantOnGenome/Conservation_score/GERP",      4, 100, 0, 0, 0, "GERP conservation",    "", "Conservation score as calculated by GERP.", "The Conservation score as calculated by GERP.", "DECIMAL(5,3)", "GERP conservation score||text|6", "", "", 1, 1, 1, 0, NOW(), NULL, NULL)',
                                'INSERT INTO ' . TABLE_COLS . ' VALUES ("VariantOnGenome/dbSNP",                        8, 120, 0, 0, 0, "dbSNP ID",             "", "The dbSNP ID.", "The dbSNP ID.", "VARCHAR(15)", "dbSNP ID|If available, please fill in the dbSNP ID, such as rs12345678.|text|10", "", "/^[rs]s\d+$/", 1, 1, 1, 0, NOW(), NULL, NULL)',
                                'INSERT INTO ' . TABLE_COLS . ' VALUES ("VariantOnTranscript/Distance_to_splice_site", 10, 150, 0, 0, 0, "Splice distance",      "", "The distance to the nearest splice site.", "The distance to the nearest splice site.", "MEDIUMINT(8) UNSIGNED", "Distance to splice site||text|8", "", "", 1, 1, 1, 0, NOW(), NULL, NULL)',
                                'INSERT INTO ' . TABLE_COLS . ' VALUES ("VariantOnTranscript/GVS/Function",             9, 200, 0, 0, 0, "GVS function",         "", "Functional annotation of this position by GVS.", "The functional annotation of this position from the Genome Variation Server.", "VARCHAR(30)", "GVS function|Whether the variant is missense, nonsense, in an intron, UTR, etc.|text|30", "", "", 1, 1, 1, 0, NOW(), NULL, NULL)',
                                'INSERT INTO ' . TABLE_COLS . ' VALUES ("VariantOnTranscript/PolyPhen",                 8, 200, 0, 0, 0, "PolyPhen prediction",  "", "The effect predicted by PolyPhen.", "The effect predicted by PolyPhen.", "VARCHAR(20)", "PolyPhen prediction||select|1|true|false|false", "benign = Benign\r\npossiblyDamaging = Possably damaging\r\nprobablyDamaging = Probably damaging\r\nnoPrediction = No prediction", "", 1, 1, 1, 0, NOW(), NULL, NULL)',
                                'INSERT INTO ' . TABLE_COLS . ' VALUES ("VariantOnTranscript/Position",                 5, 100, 0, 0, 0, "Position",             "", "Position in cDNA sequence.", "The position of this variant in the cDNA sequence.", "MEDIUMINT(5)", "cDNA Position||text|5", "", "", 1, 1, 1, 0, NOW(), NULL, NULL)',
                             ),
                    '3.0-beta-03b' =>
                        array(
                                'UPDATE ' . TABLE_LINKS . ' SET description = CONCAT(description, "\r\n\r\nExamples:\r\n{PMID:Fokkema et. al.:15977173}\r\n{PMID:Fokkema et. al.:21520333}") WHERE name = "PubMed" AND description = "Links to abstracts in the PubMed database.\r\n[1] = The name of the author(s).\r\n[2] = The PubMed ID."',
                                'UPDATE ' . TABLE_LINKS . ' SET description = CONCAT(description, "\r\n\r\nExamples:\r\n{dbSNP:rs193143796}\r\n{dbSNP:193143796}") WHERE name = "DbSNP" AND description = "Links to the DbSNP database.\r\n[1] = The DbSNP ID."',
                                'UPDATE ' . TABLE_LINKS . ' SET description = CONCAT(description, "\r\n\r\nExamples:\r\n{GenBank:NG_012232.1}\r\n{GenBank:NC_000001.10}") WHERE name = "GenBank" AND description = "Links to GenBank sequences.\r\n[1] = The GenBank ID."',
                                'UPDATE ' . TABLE_LINKS . ' SET description = CONCAT(description, "\r\n\r\nExamples:\r\n{OMIM:300377:0021}\r\n{OMIM:188840:0003}") WHERE name = "OMIM" AND description = "Links to an allelic variant on the gene\'s OMIM page.\r\n[1] = The OMIM gene ID.\r\n[2] = The number of the OMIM allelic variant on that page."',
                                // This should cascade to ACTIVE_COLS and SHARED_COLS.
                                'UPDATE ' . TABLE_COLS . ' SET id = "VariantOnGenome/Published_as", head_column = "Published as", form_type = REPLACE(form_type, "DNA published", "Published as") WHERE id = "VariantOnGenome/DNA_published"',
                                'UPDATE ' . TABLE_COLS . ' SET id = "VariantOnTranscript/Published_as", head_column = "Published as", form_type = REPLACE(form_type, "DNA published", "Published as") WHERE id = "VariantOnTranscript/DNA_published"',
                             ),
                  );

    if ($sCalcVersionDB < lovd_calculateVersion('3.0-alpha-01')) {
        // Simply reload all custom columns.
        require ROOT_PATH . 'install/inc-sql-columns.php';
        $aUpdates['3.0-alpha-01'][] = 'DELETE FROM ' . TABLE_COLS . ' WHERE col_order < 255';
        $aUpdates['3.0-alpha-01'] = array_merge($aUpdates['3.0-alpha-01'], $aColSQL);
    }

    if ($sCalcVersionDB < lovd_calculateVersion('3.0-alpha-07')) {
        // DROP VariantOnTranscript/DBID if it exists.
        $aColumns = $_DB->query('DESCRIBE ' . TABLE_VARIANTS_ON_TRANSCRIPTS)->fetchAllColumn();
        if (in_array('VariantOnTranscript/DBID', $aColumns)) {
            $aUpdates['3.0-alpha-07'][] = 'ALTER TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' DROP COLUMN `VariantOnTranscript/DBID`';
        }
    }

    if ($sCalcVersionDB < lovd_calculateVersion('3.0-alpha-07b')) {
        // DROP Individual/Times_Reported if it exists and copy its data to panel_size.
        $aColumns = $_DB->query('DESCRIBE ' . TABLE_INDIVIDUALS)->fetchAllColumn();
        if (in_array('Individual/Times_Reported', $aColumns)) {
            $aUpdates['3.0-alpha-07b'][] = 'UPDATE ' . TABLE_INDIVIDUALS . ' SET panel_size = `Individual/Times_Reported`';
            $aUpdates['3.0-alpha-07b'][] = 'ALTER TABLE ' . TABLE_INDIVIDUALS . ' DROP COLUMN `Individual/Times_Reported`';
        }
        $aUpdates['3.0-alpha-07b'][] = 'DELETE FROM ' . TABLE_COLS . ' WHERE id = "Individual/Times_Reported"';
    }

    if ($sCalcVersionDB < lovd_calculateVersion('3.0-alpha-07c')) {
        // SET all standard custom columns and custom links to created_by new LOVD user.
        $aColumns = array(
                            'Individual/Lab_ID', 'Individual/Reference', 'Individual/Remarks', 'Individual/Remarks_Non_Public', 'Individual/Gender', 'Individual/Mutation/Origin',
                            'Individual/Origin/Geographic', 'Individual/Origin/Ethnic', 'Individual/Origin/Population', 'Screening/Date', 'Screening/Technique', 'Screening/Template',
                            'Screening/Tissue', 'VariantOnGenome/DBID', 'VariantOnGenome/DNA', 'VariantOnGenome/DNA_published', 'VariantOnGenome/Frequency', 'VariantOnGenome/Reference',
                            'VariantOnGenome/Remarks', 'VariantOnGenome/Restriction_site', 'VariantOnGenome/Type', 'VariantOnTranscript/DNA', 'VariantOnTranscript/DNA_published',
                            'VariantOnTranscript/Exon', 'VariantOnTranscript/Location', 'VariantOnTranscript/Protein', 'VariantOnTranscript/RNA'
                         );
        $aUpdates['3.0-alpha-07c'][] = 'UPDATE ' . TABLE_COLS . ' SET created_by = 0 WHERE id IN ("'. implode('", "', $aColumns) . '")';
        $aUpdates['3.0-alpha-07c'][] = 'UPDATE ' . TABLE_LINKS . ' SET created_by = 0 WHERE id <= 4';
    }

    if ($sCalcVersionDB < lovd_calculateVersion('3.0-alpha-07d')) {
        // INSERT chromosome in the new TABLE_CHROMOSOMES.
        require ROOT_PATH . 'install/inc-sql-chromosomes.php';
        $aUpdates['3.0-alpha-07d']['chr_values'] = $aChromosomeSQL[0];
    }

    if ($sCalcVersionDB < lovd_calculateVersion('3.0-beta-03b')) {
        // CHANGE DNA_published to Published as in TABLE_VARIANTS & TABLE_VARIANTS_ON_TRANSCRIPTS if exists.
        $aColumns = $_DB->query('DESCRIBE ' . TABLE_VARIANTS)->fetchAllColumn();
        if (in_array('VariantOnGenome/DNA_published', $aColumns)) {
            $aUpdates['3.0-beta-03b'][] = 'ALTER TABLE ' . TABLE_VARIANTS . ' CHANGE `VariantOnGenome/DNA_Published` `VariantOnGenome/Published_as` VARCHAR(100)';
        }
        $aColumns = $_DB->query('DESCRIBE ' . TABLE_VARIANTS_ON_TRANSCRIPTS)->fetchAllColumn();
        if (in_array('VariantOnTranscript/DNA_published', $aColumns)) {
            $aUpdates['3.0-beta-03b'][] = 'ALTER TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' CHANGE `VariantOnTranscript/DNA_Published` `VariantOnTranscript/Published_as` VARCHAR(100)';
        }
    }






    // To make sure we upgrade the database correctly, we add the current version to the list...
    if (!isset($aUpdates[$_SETT['system']['version']])) {
        $aUpdates[$_SETT['system']['version']] = array();
    }

    require ROOT_PATH . 'class/progress_bar.php';
    // FIXME; if we're not in post right now, don't send the form in POST either! (GET variables then should be put in input fields then)
    $sFormNextPage = '<FORM action="' . $_SERVER['REQUEST_URI'] . '" method="post" id="upgrade_form">' . "\n";
    foreach ($_POST as $key => $val) {
        // Added htmlspecialchars to prevent XSS and allow values to include quotes.
        if (is_array($val)) {
            foreach ($val as $value) {
                $sFormNextPage .= '          <INPUT type="hidden" name="' . $key . '[]" value="' . htmlspecialchars($value) . '">' . "\n";
            }
        } else {
            $sFormNextPage .= '          <INPUT type="hidden" name="' . $key . '" value="' . htmlspecialchars($val) . '">' . "\n";
        }
    }
    $sFormNextPage .= '          <INPUT type="submit" id="submit" value="Proceed &raquo;">' . "\n" .
                      '        </FORM>';
    // This already puts the progress bar on the screen.
    $_BAR = new ProgressBar('', 'Checking upgrade lock...', $sFormNextPage);

    define('_INC_BOT_CLOSE_HTML_', false); // Sounds kind of stupid, but this prevents the inc-bot to actually close the <BODY> and <HTML> tags.
    require ROOT_PATH . 'inc-bot.php';



    // Now we're still in the <BODY> so the progress bar can add <SCRIPT> tags as much as it wants.
    flush();



    // Try to update the upgrade lock.
    $sQ = 'UPDATE ' . TABLE_STATUS . ' SET lock_update = 1 WHERE lock_update = 0';
    $nMax = 3; // FIXME; Should be higher, this value is for dev only
    for ($i = 0; $i < $nMax; $i ++) {
        $bLocked = !$_DB->exec($sQ);
        if (!$bLocked) {
            break;
        }

        // No update means that someone else is updating the system.
        $_BAR->setMessage('Update lock is in place, so someone else is already upgrading the database.<BR>Waiting for other user to finish... (' . ($nMax - $i) . ')');
        flush();
        sleep(1);
    }

    if ($bLocked) {
        // Other user is taking ages! Or somethings wrong...
        $_BAR->setMessage('Other user upgrading the database is still not finished.<BR>' . (isset($_GET['force_lock'])? 'Forcing upgrade as requested...' : 'This may indicate something went wrong during upgrade.'));
        if (isset($_GET['force_lock'])) {
            $bLocked = false;
        }
    } else {
        $_BAR->setMessage('Upgrading database backend...');
    }
    flush();





    if (!$bLocked) {
        // There we go...

        // This recursive count returns a higher count then we would seem to want at first glance,
        // because each version's array of queries count as one as well.
        // However, because we will run one additional query per version, this number will be correct anyway.
        // 2012-02-02; 3.0-beta-02; But of course we should exclude the older versions...
        foreach ($aUpdates as $sVersion => $aSQL) {
            if (lovd_calculateVersion($sVersion) <= $sCalcVersionDB || lovd_calculateVersion($sVersion) > $sCalcVersionFiles) {
                unset($aUpdates[$sVersion]);
            }
        }
        $nSQL = count($aUpdates, true);

        // Actually run the SQL...
        $nSQLDone = 0;
        $nSQLDonePercentage = 0;
        $nSQLDonePercentagePrev = 0;
        $nSQLFailed = 0;
        $sSQLFailed = '';

        foreach ($aUpdates as $sVersion => $aSQL) {
            $_BAR->setMessage('To ' . $sVersion . '...');

            $aSQL[] = 'UPDATE ' . TABLE_STATUS . ' SET version = "' . $sVersion . '", updated_date = NOW()';

            // Loop needed queries...
            foreach ($aSQL as $i => $sSQL) {
                $i ++;
                if (!$nSQLFailed) {
                    $q = $_DB->query($sSQL, false, false); // This means that there is no SQL injection check here. But hey - these are our own queries.
                    if (!$q) {
                        $nSQLFailed ++;
                        // Error when running query.
                        $sError = $_DB->formatError();
                        lovd_queryError('RunUpgradeSQL', $sSQL, $sError, false);
                        $sSQLFailed = 'Error!<BR><BR>\n\n' .
                                      'Error while executing query ' . $i . ':\n' .
                                      '<PRE style="background : #F0F0F0;">' . htmlspecialchars($sError) . '</PRE><BR>\n\n' .
                                      'This implies these MySQL queries need to be executed manually:<BR>\n' .
                                      '<PRE style="background : #F0F0F0;">\n<SPAN style="background : #C0C0C0;">' . sprintf('%' . strlen(count($aSQL)) . 'd', $i) . '</SPAN> ' . htmlspecialchars($sSQL) . ';\n';

                    } else {
                        $nSQLDone ++;

                        $nSQLDonePercentage = floor(100*$nSQLDone / $nSQL); // Don't want to show 100% when an error occurs at 99.5%.
                        if ($nSQLDonePercentage != $nSQLDonePercentagePrev) {
                            $_BAR->setProgress($nSQLDonePercentage);
                            $nSQLDonePercentagePrev = $nSQLDonePercentage;
                        }

                        flush();
                        usleep(1000);
                    }

                } else {
                    // Something went wrong, so we need to print out the remaining queries...
                    $nSQLFailed ++;
                    $sSQLFailed .= '<SPAN style="background : #C0C0C0;">' . sprintf('%' . strlen(count($aSQL)) . 'd', $i) . '</SPAN> ' . htmlspecialchars($sSQL) . ';\n';
                }
            }

            if ($nSQLFailed) {
                $sSQLFailed .= '</PRE>';
                $_BAR->setMessage($sSQLFailed);
                $_BAR->setMessage('After executing th' . ($nSQLFailed == 1? 'is query' : 'ese queries') . ', please try again.', 'done');
                $_BAR->setMessageVisibility('done', true);
                break;
            }
            usleep(300000);
        }

        if (!$nSQLFailed) {
            // Upgrade complete, all OK!
            lovd_writeLog('Install', 'Upgrade', 'Successfully upgraded LOVD from ' . $_STAT['version'] . ' to ' . $_SETT['system']['version'] . ', executing ' . $nSQLDone . ' quer' . ($nSQLDone == 1? 'y' : 'ies'));
            $_BAR->setProgress(100);
            $_BAR->setMessage('Successfully upgraded to ' . $_SETT['system']['version'] . '!<BR>Executed ' . $nSQLDone . ' database quer' . ($nSQLDone == 1? 'y' : 'ies') . '.');
        } else {
            // Bye bye, they should not see the form!
            print('</BODY>' . "\n" .
                  '</HTML>' . "\n");
            exit;
        }

        // Remove update lock.
        $_DB->query('UPDATE ' . TABLE_STATUS . ' SET lock_update = 0');
    }

    // Now that this is over, let the user proceed to whereever they were going!
    if ($bLocked) {
        // Have to force upgrade...
        $_SERVER['REQUEST_URI'] .= ($_SERVER['QUERY_STRING']? '&' : '?') . 'force_lock';
    } else {
        // Remove the force_lock thing again... (might not be there, but who cares!)
        $_SERVER['REQUEST_URI'] = preg_replace('/[?&]force_lock$/', '', $_SERVER['REQUEST_URI']);
    }

    print('<SCRIPT type="text/javascript">document.forms[\'upgrade_form\'].action=\'' . str_replace('\'', '\\\'', $_SERVER['REQUEST_URI']) . '\';</SCRIPT>' . "\n");
    if ($bLocked) {
        print('<SCRIPT type="text/javascript">document.forms[\'upgrade_form\'].submit.value = document.forms[\'upgrade_form\'].submit.value.replace(\'Proceed\', \'Force upgrade\');</SCRIPT>' . "\n");
    }
    $_BAR->setMessageVisibility('done', true);
    print('</BODY>' . "\n" .
          '</HTML>' . "\n");
    exit;
}
?>
