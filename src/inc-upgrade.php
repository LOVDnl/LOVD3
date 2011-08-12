<?php
// DMD_SPECIFIC: REMEMBER. If you add code that adds SQL for all genes, you MUST add the key first to the large array. Otherwise, the order in which upgrades are done is WRONG!!!
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-01-14
 * Modified    : 2011-08-12
 * For LOVD    : 3.0-alpha-04
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
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

// 2009-07-17; 2.0-20; Added increased execution time to help perform large upgrades.
if ((int) ini_get('max_execution_time') < 60) {
    set_time_limit(60);
}

// How are the versions related?
$sCalcVersionFiles = lovd_calculateVersion($_SETT['system']['version']);
$sCalcVersionDB = lovd_calculateVersion($_STAT['version']);

if ($sCalcVersionFiles != $sCalcVersionDB) {
    // Version of files are not equal to version of database backend.

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
                    '3.0-pre-15' =>
                             array(
                                    'UPGRADING TO 3.0-pre-15 IS NOT SUPPORTED. UNINSTALL LOVD 3.0 AND REINSTALL TO GET THE LATEST.',
                                  ),
                    '3.0-pre-16' =>
                             array(
                                    'ALTER TABLE ' . TABLE_GENES . ' CHANGE COLUMN chrom_location chrom_band VARCHAR(20) NULL',
                                    'ALTER TABLE ' . TABLE_GENES . ' MODIFY COLUMN updated_date DATETIME NULL',
                                  ),
                    '3.0-pre-17' =>
                             array(
                                    'ALTER TABLE ' . TABLE_CURATES . ' MODIFY COLUMN userid SMALLINT(5) UNSIGNED ZEROFILL NOT NULL',
                                    'ALTER TABLE ' . TABLE_LOGS . ' MODIFY COLUMN userid SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_CONFIG . ' ADD COLUMN logo_uri VARCHAR(100) NOT NULL DEFAULT "gfx/LOVD_logo130x50.jpg" AFTER refseq_build',
                                  ),
                    '3.0-pre-18' =>
                             array(
                                    'ALTER TABLE ' . TABLE_SHARED_COLS . ' MODIFY COLUMN geneid VARCHAR(12)',
                                    'ALTER TABLE ' . TABLE_CONFIG . ' ADD COLUMN mutalyzer_soap_url VARCHAR(100) NOT NULL DEFAULT "http://www.mutalyzer.nl/2.0/services" AFTER logo_uri',
                                  ),
                    '3.0-pre-19' =>
                             array(
                                    'DELETE FROM ' . TABLE_COLS . ' WHERE id IN ("Patient/Patient_ID", "Screening/Template", "Screening/Technique", "Screening/Tissue", "Patient/Phenotype/Disease", "Patient/Reference", "Patient/Remarks", "Patient/Remarks_Non_Public", "Patient/Times_Reported", "Patient/Occurrence", "Patient/Gender", "Patient/Mutation/Origin", "Patient/Mutation/Origin_De_Novo", "Patient/Origin/Geographic", "Patient/Origin/Ethnic", "Patient/Origin/Population")',
                                    'INSERT INTO ' . TABLE_COLS . ' VALUES ("Individual/Lab_ID",                    0,  80, 1, 1, 1, "Lab\'s ID",            "", "The ID given to this individual by its reference.", "The ID given to this individual by its reference, such as a hospital, diagnostic laboratory or a paper.", "VARCHAR(15)", "Lab ID||text|10", "", "", 0, 1, 0, 1, NOW(), NULL, NULL)',
                                    'INSERT INTO ' . TABLE_COLS . ' VALUES ("Screening/Template",                   0,  80, 1, 1, 1, "Template",             "", "Screening performed on DNA, RNA and/or Protein level.", "Screening performed on DNA, RNA and/or Protein level.", "VARCHAR(20)", "Detection template||select|3|false|true|false", "DNA\r\nRNA\r\nProtein", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                                    'INSERT INTO ' . TABLE_COLS . ' VALUES ("Screening/Technique",                  0, 200, 1, 1, 1, "Technique",            "", "Technique used to detect variants.", "Technique used to reveal the variants reported.", "VARCHAR(150)", "Technique(s) used||select|5|false|true|false", "BESS = Base Excision Sequence Scanning\r\nCMC = Chemical Mismatch Cleavage\r\nDGGE = Denaturing-Gradient Gel-Electrophoresis\r\nDHPLC = Denaturing High-Performance Liquid Chromatography\r\nDOVAM = Detection Of Virtually All Mutations (SSCA variant)\r\nDSCA = Double-Strand DNA Conformation Analysis\r\nHD = HeteroDuplex analysis\r\nIHC = Immuno-Histo-Chemistry\r\nmPCR = multiplex PCR\r\nMAPH = Multiplex Amplifiable Probe Hybridisation\r\nMLPA = Multiplex Ligation-dependent Probe Amplification\r\nNGS = Next Generation Sequencing\r\nPAGE = Poly-Acrylamide Gel-Electrophoresis\r\nPCR = Polymerase Chain Reaction\r\nPTT = Protein Truncation Test\r\nRT-PCR = Reverse Transcription and PCR\r\nSEQ = SEQuencing\r\nSouthern = Southern Blotting\r\nSSCA = Single-Strand DNA Conformation Analysis (SSCP)\r\nWestern = Western Blotting", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                                    'INSERT INTO ' . TABLE_COLS . ' VALUES ("Screening/Tissue",                     0, 100, 0, 0, 1, "Tissue",               "", "Tissue type used for the detection of sequence variants.", "Tissue type used for the detection of sequence variants.", "VARCHAR(25)", "Tissue||text|20", "", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                                    'INSERT INTO ' . TABLE_COLS . ' VALUES ("Screening/Date",                       0,  80, 0, 0, 0, "Date",                 "Format: YYYY-MM-DD.", "Date the detection technique was performed.", "Date the detection technique was performed, in YYYY-MM-DD format.", "DATE", "Date||text|10", "", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                                    'INSERT INTO ' . TABLE_COLS . ' VALUES ("VariantOnTranscript/DBID",             6, 200, 1, 1, 1, "DB-ID",                "The ID-field should start with the ID; the gene symbol followed by an underscore (_) and the ID code, usually five digits.", "Database IDentifier; When available, links to OMIM ID\'s are provided.", "Database IDentifier; When available, links to OMIM ID\'s are provided.", "VARCHAR(100)", "ID||text|40", "", "/^[A-Z][A-Z0-9]+_([0-9]{5}([a-z]{2})?|(SO|MP|e)[0-9]{1,2}((SO|MP|e)[0-9]{1,2})?b?)\\\\b/", 1, 0, 1, 1, NOW(), NULL, NULL)',
                                    'INSERT INTO ' . TABLE_COLS . ' VALUES ("Individual/Phenotype/Disease",         0, 200, 1, 1, 0, "Disease",              "", "Disease phenotype, as reported in paper/by submitter, unless modified by the curator.", "Disease phenotype of the individual(s).", "VARCHAR(50)", "Disease||select|4|false|true|false", "", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                                    'INSERT INTO ' . TABLE_COLS . ' VALUES ("Individual/Reference",                 0, 200, 1, 1, 0, "Reference",            "", "Reference describing the individual, &quot;Submitted:&quot; indicating that the mutation was submitted directly to this database.", "Literature reference with possible link to publication in PubMed or other online resource. &quot;Submitted:&quot; indicates that the mutation was submitted directly to this database by the laboratory indicated.", "VARCHAR(200)", "Reference||text|50", "", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                                    'INSERT INTO ' . TABLE_COLS . ' VALUES ("Individual/Remarks",                   0, 200, 0, 1, 0, "Remarks",              "", "", "", "TEXT", "Remarks||textarea|50|3", "", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                                    'INSERT INTO ' . TABLE_COLS . ' VALUES ("Individual/Remarks_Non_Public",        0, 200, 0, 1, 0, "Remarks (non public)", "", "", "", "TEXT", "Remarks (non public)||textarea|50|3", "", "", 0, 0, 0, 1, NOW(), NULL, NULL)',
                                    'INSERT INTO ' . TABLE_COLS . ' VALUES ("Individual/Times_Reported",            0,  80, 0, 1, 1, "# Reported",           "", "Number of times this case has been reported", "Number of times this case has been reported", "SMALLINT(4) UNSIGNED DEFAULT 1", "Times reported||text|3", "", "", 1, 0, 1, 1, NOW(), NULL, NULL)',
                                    'INSERT INTO ' . TABLE_COLS . ' VALUES ("Individual/Occurrence",                0, 200, 0, 0, 0, "Occurrence",           "", "Occurrence", "Occurrence", "VARCHAR(8)", "Occurrence||select|1|Unknown|false|false", "Familial\r\nSporadic", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                                    'INSERT INTO ' . TABLE_COLS . ' VALUES ("Individual/Gender",                    0,  60, 0, 0, 0, "Gender",               "", "Individual gender", "Individual gender", "VARCHAR(6)", "Gender||select|1|Unknown|false|false", "Female\r\nMale", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                                    'INSERT INTO ' . TABLE_COLS . ' VALUES ("Individual/Mutation/Origin",           0, 200, 0, 0, 0, "Mut. origin",          "", "Origin of mutation", "Origin of mutation", "VARCHAR(9)", "Origin of mutation||select|1|Unknown|false|false", "De novo\r\nInherited", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                                    'INSERT INTO ' . TABLE_COLS . ' VALUES ("Individual/Mutation/Origin_De_Novo",   0, 200, 0, 0, 0, "De novo origin",       "", "If de novo, origin of mutation", "If de novo, origin of mutation", "VARCHAR(11)", "If de novo, origin of mutation||select|1|true|false|false", "Individual\r\nFather\r\nMother\r\nGrandfather\r\nGrandmother", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                                    'INSERT INTO ' . TABLE_COLS . ' VALUES ("Individual/Origin/Geographic",         0, 200, 0, 0, 0, "Geographic origin",    "", "Geographic origin of individual", "Geographic origin of the individual", "VARCHAR(50)", "Geographic origin||text|30", "", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                                    'INSERT INTO ' . TABLE_COLS . ' VALUES ("Individual/Origin/Ethnic",             0, 200, 0, 0, 0, "Ethnic origin",        "", "Ethnic origin of individual", "Ethnic origin of the individual", "VARCHAR(50)", "Ethnic origin||text|20", "", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                                    'INSERT INTO ' . TABLE_COLS . ' VALUES ("Individual/Origin/Population",         0, 200, 0, 0, 0, "Population",           "", "Individual population", "Additional information on individual population", "VARCHAR(50)", "Individual population||text|30", "", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                                    'UPDATE ' . TABLE_COLS . ' SET id=REPLACE(`id`, "Patient/", "Individual/")',
                                    'UPDATE ' . TABLE_COLS . ' SET description_legend_short=REPLACE(`description_legend_short`, "patient", "individual")',
                                    'UPDATE ' . TABLE_COLS . ' SET description_legend_full=REPLACE(`description_legend_full`, "patient", "individual")',
                                    'ALTER TABLE ' . TABLE_DISEASES . ' ADD UNIQUE (id_omim)',
                                    'ALTER TABLE ' . TABLE_USERS . ' MODIFY COLUMN created_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_USERS . ' MODIFY COLUMN edited_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_GENES . ' MODIFY COLUMN created_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_GENES . ' MODIFY COLUMN edited_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_GENES . ' MODIFY COLUMN updated_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_TRANSCRIPTS . ' MODIFY COLUMN created_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_TRANSCRIPTS . ' MODIFY COLUMN edited_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_DISEASES . ' MODIFY COLUMN created_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_DISEASES . ' MODIFY COLUMN edited_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_PATIENTS . ' MODIFY COLUMN created_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_PATIENTS . ' MODIFY COLUMN edited_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_PATIENTS . ' MODIFY COLUMN deleted_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' MODIFY COLUMN created_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' MODIFY COLUMN edited_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' MODIFY COLUMN deleted_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' MODIFY COLUMN created_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' MODIFY COLUMN edited_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' MODIFY COLUMN deleted_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' MODIFY COLUMN created_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' MODIFY COLUMN edited_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' MODIFY COLUMN deleted_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_COLS . ' MODIFY COLUMN created_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_COLS . ' MODIFY COLUMN edited_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_ACTIVE_COLS . ' MODIFY COLUMN created_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_SHARED_COLS . ' MODIFY COLUMN created_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_SHARED_COLS . ' MODIFY COLUMN edited_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_LINKS . ' MODIFY COLUMN created_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_LINKS . ' MODIFY COLUMN edited_by SMALLINT(5) UNSIGNED ZEROFILL',
                                    'ALTER TABLE ' . TABLE_STATUS . ' MODIFY COLUMN update_description TEXT',
                                    'CREATE TABLE ' . TABLE_INDIVIDUALS . ' LIKE ' . TABLE_PATIENTS,
                                    'INSERT INTO ' . TABLE_INDIVIDUALS . ' SELECT * FROM ' . TABLE_PATIENTS,
                                    'ALTER TABLE ' . TABLE_INDIVIDUALS . ' ADD FOREIGN KEY (ownerid) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_INDIVIDUALS . ' ADD FOREIGN KEY (statusid) REFERENCES ' . TABLE_DATA_STATUS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_INDIVIDUALS . ' ADD FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_INDIVIDUALS . ' ADD FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_INDIVIDUALS . ' ADD FOREIGN KEY (deleted_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'CREATE TABLE ' . TABLE_IND2DIS . ' (individualid MEDIUMINT(8) UNSIGNED ZEROFILL NOT NULL, diseaseid SMALLINT(5) UNSIGNED ZEROFILL NOT NULL, PRIMARY KEY (individualid, diseaseid), INDEX (diseaseid), FOREIGN KEY (individualid) REFERENCES ' . TABLE_INDIVIDUALS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE, FOREIGN KEY (diseaseid) REFERENCES ' . TABLE_DISEASES . ' (id) ON DELETE CASCADE ON UPDATE CASCADE) ENGINE=InnoDB, DEFAULT CHARACTER SET utf8',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' DROP FOREIGN KEY ' . TABLE_SCREENINGS . '_ibfk_1',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' DROP KEY patientid',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' CHANGE COLUMN patientid individualid MEDIUMINT(8) UNSIGNED ZEROFILL NOT NULL',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' ADD INDEX (individualid)',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' ADD CONSTRAINT ' . TABLE_SCREENINGS . '_ibfk_1 FOREIGN KEY (individualid) REFERENCES ' . TABLE_INDIVIDUALS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' DROP FOREIGN KEY ' . TABLE_PHENOTYPES . '_ibfk_2',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' DROP KEY patientid',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' CHANGE COLUMN patientid individualid MEDIUMINT(8) UNSIGNED ZEROFILL NOT NULL',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' ADD INDEX (individualid)',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' ADD CONSTRAINT ' . TABLE_PHENOTYPES . '_ibfk_2 FOREIGN KEY (individualid) REFERENCES ' . TABLE_INDIVIDUALS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' DROP FOREIGN KEY ' . TABLE_VARIANTS . '_ibfk_1',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' DROP KEY patientid',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' DROP COLUMN patientid',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' DROP FOREIGN KEY ' . TABLE_VARIANTS . '_ibfk_2',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' DROP FOREIGN KEY ' . TABLE_VARIANTS . '_ibfk_3',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' DROP FOREIGN KEY ' . TABLE_VARIANTS . '_ibfk_4',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' DROP FOREIGN KEY ' . TABLE_VARIANTS . '_ibfk_5',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' DROP FOREIGN KEY ' . TABLE_VARIANTS . '_ibfk_6',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' DROP FOREIGN KEY ' . TABLE_VARIANTS . '_ibfk_7',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' ADD CONSTRAINT ' . TABLE_VARIANTS . '_ibfk_1 FOREIGN KEY (pathogenicid) REFERENCES ' . TABLE_PATHOGENIC . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' ADD CONSTRAINT ' . TABLE_VARIANTS . '_ibfk_2 FOREIGN KEY (ownerid) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' ADD CONSTRAINT ' . TABLE_VARIANTS . '_ibfk_3 FOREIGN KEY (statusid) REFERENCES ' . TABLE_DATA_STATUS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' ADD CONSTRAINT ' . TABLE_VARIANTS . '_ibfk_4 FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' ADD CONSTRAINT ' . TABLE_VARIANTS . '_ibfk_5 FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' ADD CONSTRAINT ' . TABLE_VARIANTS . '_ibfk_6 FOREIGN KEY (deleted_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'DROP TABLE ' . TABLE_PATIENTS,
                                  ),
                       '3.0-pre-20' =>
                             array( 
                                    'ALTER TABLE ' . TABLE_COLS2LINKS . ' MODIFY COLUMN linkid TINYINT(3) UNSIGNED ZEROFILL NOT NULL',
                                    'UPDATE ' . TABLE_COLS . ' SET form_type="Gender||select|1|--Not specified--|false|false" WHERE id="Individual/Gender"',
                                    'UPDATE ' . TABLE_COLS . ' SET width=70 WHERE id="Individual/Gender"',
                                    'UPDATE ' . TABLE_COLS . ' SET select_options="Female\r\nMale\r\nUnknown" WHERE id="Individual/Gender"',
                                    'UPDATE ' . TABLE_COLS . ' SET mysql_type=\'VARCHAR(7) NOT NULL\' WHERE id="Individual/Gender"',
                                    'ALTER TABLE ' . TABLE_INDIVIDUALS . ' MODIFY COLUMN id MEDIUMINT(8) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' MODIFY COLUMN id MEDIUMINT(8) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' MODIFY COLUMN id INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' MODIFY COLUMN id INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT',
                                    'ALTER TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' DROP FOREIGN KEY ' . TABLE_VARIANTS_ON_TRANSCRIPTS . '_ibfk_1',
                                    'ALTER TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' DROP FOREIGN KEY ' . TABLE_VARIANTS_ON_TRANSCRIPTS . '_ibfk_2',
                                    'ALTER TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' DROP FOREIGN KEY ' . TABLE_VARIANTS_ON_TRANSCRIPTS . '_ibfk_3',
                                    'ALTER TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' MODIFY COLUMN id INT(10) UNSIGNED ZEROFILL NOT NULL',
                                    'ALTER TABLE ' . TABLE_SCR2VAR . ' DROP FOREIGN KEY ' . TABLE_SCR2VAR . '_ibfk_1',
                                    'ALTER TABLE ' . TABLE_SCR2VAR . ' DROP FOREIGN KEY ' . TABLE_SCR2VAR . '_ibfk_2',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' MODIFY COLUMN id INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT',
                                    'ALTER TABLE ' . TABLE_SCR2VAR . ' MODIFY COLUMN variantid INT(10) UNSIGNED ZEROFILL NOT NULL',
                                    'ALTER TABLE ' . TABLE_SCR2VAR . ' ADD CONSTRAINT ' . TABLE_SCR2VAR . '_fk_screeningid FOREIGN KEY (screeningid) REFERENCES ' . TABLE_SCREENINGS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_SCR2VAR . ' ADD CONSTRAINT ' . TABLE_SCR2VAR . '_fk_variantid FOREIGN KEY (variantid) REFERENCES ' . TABLE_VARIANTS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' ADD CONSTRAINT ' . TABLE_VARIANTS_ON_TRANSCRIPTS . '_fk_id FOREIGN KEY (id) REFERENCES ' . TABLE_VARIANTS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' ADD CONSTRAINT ' . TABLE_VARIANTS_ON_TRANSCRIPTS . '_fk_transcriptid FOREIGN KEY (transcriptid) REFERENCES ' . TABLE_TRANSCRIPTS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' ADD CONSTRAINT ' . TABLE_VARIANTS_ON_TRANSCRIPTS . '_fk_pathogenicid FOREIGN KEY (pathogenicid) REFERENCES ' . TABLE_PATHOGENIC . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_GENES . ' MODIFY COLUMN chrom_band VARCHAR(20) NOT NULL',
                                    'ALTER TABLE ' . TABLE_GENES . ' MODIFY COLUMN id_entrez INT(10) UNSIGNED',
                                    'ALTER TABLE ' . TABLE_GENES . ' MODIFY COLUMN id_omim INT(10) UNSIGNED',
                                    'ALTER TABLE ' . TABLE_DISEASES . ' MODIFY COLUMN id_omim INT(10) UNSIGNED',
                                    'UPDATE ' . TABLE_COLS . ' SET col_order = 255 WHERE col_order > 255',
                                    'ALTER TABLE ' . TABLE_COLS . ' MODIFY COLUMN col_order TINYINT(3) UNSIGNED NOT NULL',

                                    // DROP OLD FOREIGN KEYS
                                    'ALTER TABLE ' . TABLE_USERS . ' DROP FOREIGN KEY ' . TABLE_USERS . '_ibfk_1',
                                    'ALTER TABLE ' . TABLE_USERS . ' DROP FOREIGN KEY ' . TABLE_USERS . '_ibfk_2',
                                    'ALTER TABLE ' . TABLE_USERS . ' DROP FOREIGN KEY ' . TABLE_USERS . '_ibfk_3',
                                    'ALTER TABLE ' . TABLE_GENES . ' DROP FOREIGN KEY ' . TABLE_GENES . '_ibfk_1',
                                    'ALTER TABLE ' . TABLE_GENES . ' DROP FOREIGN KEY ' . TABLE_GENES . '_ibfk_2',
                                    'ALTER TABLE ' . TABLE_GENES . ' DROP FOREIGN KEY ' . TABLE_GENES . '_ibfk_3',
                                    'ALTER TABLE ' . TABLE_CURATES . ' DROP FOREIGN KEY ' . TABLE_CURATES . '_ibfk_1',
                                    'ALTER TABLE ' . TABLE_CURATES . ' DROP FOREIGN KEY ' . TABLE_CURATES . '_ibfk_2',
                                    'ALTER TABLE ' . TABLE_TRANSCRIPTS . ' DROP FOREIGN KEY ' . TABLE_TRANSCRIPTS . '_ibfk_1',
                                    'ALTER TABLE ' . TABLE_TRANSCRIPTS . ' DROP FOREIGN KEY ' . TABLE_TRANSCRIPTS . '_ibfk_2',
                                    'ALTER TABLE ' . TABLE_TRANSCRIPTS . ' DROP FOREIGN KEY ' . TABLE_TRANSCRIPTS . '_ibfk_3',
                                    'ALTER TABLE ' . TABLE_DISEASES . ' DROP FOREIGN KEY ' . TABLE_DISEASES . '_ibfk_1',
                                    'ALTER TABLE ' . TABLE_DISEASES . ' DROP FOREIGN KEY ' . TABLE_DISEASES . '_ibfk_2',
                                    'ALTER TABLE ' . TABLE_GEN2DIS . ' DROP FOREIGN KEY ' . TABLE_GEN2DIS . '_ibfk_1',
                                    'ALTER TABLE ' . TABLE_GEN2DIS . ' DROP FOREIGN KEY ' . TABLE_GEN2DIS . '_ibfk_2',
                                    'ALTER TABLE ' . TABLE_INDIVIDUALS . ' DROP FOREIGN KEY ' . TABLE_INDIVIDUALS . '_ibfk_1',
                                    'ALTER TABLE ' . TABLE_INDIVIDUALS . ' DROP FOREIGN KEY ' . TABLE_INDIVIDUALS . '_ibfk_2',
                                    'ALTER TABLE ' . TABLE_INDIVIDUALS . ' DROP FOREIGN KEY ' . TABLE_INDIVIDUALS . '_ibfk_3',
                                    'ALTER TABLE ' . TABLE_INDIVIDUALS . ' DROP FOREIGN KEY ' . TABLE_INDIVIDUALS . '_ibfk_4',
                                    'ALTER TABLE ' . TABLE_INDIVIDUALS . ' DROP FOREIGN KEY ' . TABLE_INDIVIDUALS . '_ibfk_5',
                                    'ALTER TABLE ' . TABLE_IND2DIS . ' DROP FOREIGN KEY ' . TABLE_IND2DIS . '_ibfk_1',
                                    'ALTER TABLE ' . TABLE_IND2DIS . ' DROP FOREIGN KEY ' . TABLE_IND2DIS . '_ibfk_2',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' DROP FOREIGN KEY ' . TABLE_VARIANTS . '_ibfk_1',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' DROP FOREIGN KEY ' . TABLE_VARIANTS . '_ibfk_2',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' DROP FOREIGN KEY ' . TABLE_VARIANTS . '_ibfk_3',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' DROP FOREIGN KEY ' . TABLE_VARIANTS . '_ibfk_4',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' DROP FOREIGN KEY ' . TABLE_VARIANTS . '_ibfk_5',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' DROP FOREIGN KEY ' . TABLE_VARIANTS . '_ibfk_6',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' DROP FOREIGN KEY ' . TABLE_PHENOTYPES . '_ibfk_1',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' DROP FOREIGN KEY ' . TABLE_PHENOTYPES . '_ibfk_2',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' DROP FOREIGN KEY ' . TABLE_PHENOTYPES . '_ibfk_3',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' DROP FOREIGN KEY ' . TABLE_PHENOTYPES . '_ibfk_4',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' DROP FOREIGN KEY ' . TABLE_PHENOTYPES . '_ibfk_5',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' DROP FOREIGN KEY ' . TABLE_PHENOTYPES . '_ibfk_6',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' DROP FOREIGN KEY ' . TABLE_SCREENINGS . '_ibfk_1',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' DROP FOREIGN KEY ' . TABLE_SCREENINGS . '_ibfk_2',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' DROP FOREIGN KEY ' . TABLE_SCREENINGS . '_ibfk_3',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' DROP FOREIGN KEY ' . TABLE_SCREENINGS . '_ibfk_4',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' DROP FOREIGN KEY ' . TABLE_SCREENINGS . '_ibfk_5',
                                    'ALTER TABLE ' . TABLE_SCR2GENE . ' DROP FOREIGN KEY ' . TABLE_SCR2GENE . '_ibfk_1',
                                    'ALTER TABLE ' . TABLE_SCR2GENE . ' DROP FOREIGN KEY ' . TABLE_SCR2GENE . '_ibfk_2',
                                    'ALTER TABLE ' . TABLE_COLS . ' DROP FOREIGN KEY ' . TABLE_COLS . '_ibfk_1',
                                    'ALTER TABLE ' . TABLE_COLS . ' DROP FOREIGN KEY ' . TABLE_COLS . '_ibfk_2',
                                    'ALTER TABLE ' . TABLE_ACTIVE_COLS . ' DROP FOREIGN KEY ' . TABLE_ACTIVE_COLS . '_ibfk_1',
                                    'ALTER TABLE ' . TABLE_ACTIVE_COLS . ' DROP FOREIGN KEY ' . TABLE_ACTIVE_COLS . '_ibfk_2',
                                    'ALTER TABLE ' . TABLE_SHARED_COLS . ' DROP FOREIGN KEY ' . TABLE_SHARED_COLS . '_ibfk_1',
                                    'ALTER TABLE ' . TABLE_SHARED_COLS . ' DROP FOREIGN KEY ' . TABLE_SHARED_COLS . '_ibfk_2',
                                    'ALTER TABLE ' . TABLE_SHARED_COLS . ' DROP FOREIGN KEY ' . TABLE_SHARED_COLS . '_ibfk_3',
                                    'ALTER TABLE ' . TABLE_SHARED_COLS . ' DROP FOREIGN KEY ' . TABLE_SHARED_COLS . '_ibfk_4',
                                    'ALTER TABLE ' . TABLE_SHARED_COLS . ' DROP FOREIGN KEY ' . TABLE_SHARED_COLS . '_ibfk_5',
                                    'ALTER TABLE ' . TABLE_LINKS . ' DROP FOREIGN KEY ' . TABLE_LINKS . '_ibfk_1',
                                    'ALTER TABLE ' . TABLE_LINKS . ' DROP FOREIGN KEY ' . TABLE_LINKS . '_ibfk_2',
                                    'ALTER TABLE ' . TABLE_COLS2LINKS . ' DROP FOREIGN KEY ' . TABLE_COLS2LINKS . '_ibfk_1',
                                    'ALTER TABLE ' . TABLE_COLS2LINKS . ' DROP FOREIGN KEY ' . TABLE_COLS2LINKS . '_ibfk_2',
                                    'ALTER TABLE ' . TABLE_LOGS . ' DROP FOREIGN KEY ' . TABLE_LOGS . '_ibfk_1',
                                    'ALTER TABLE ' . TABLE_HITS . ' DROP FOREIGN KEY ' . TABLE_HITS . '_ibfk_1',

                                    // ADD NEW RENAMED FOREIGN KEYS
                                    'ALTER TABLE ' . TABLE_USERS . ' ADD CONSTRAINT ' . TABLE_USERS . '_fk_countryid FOREIGN KEY (countryid) REFERENCES ' . TABLE_COUNTRIES . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_USERS . ' ADD CONSTRAINT ' . TABLE_USERS . '_fk_created_by FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_USERS . ' ADD CONSTRAINT ' . TABLE_USERS . '_fk_edited_by FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_GENES . ' ADD CONSTRAINT ' . TABLE_GENES . '_fk_created_by FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_GENES . ' ADD CONSTRAINT ' . TABLE_GENES . '_fk_edited_by FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_GENES . ' ADD CONSTRAINT ' . TABLE_GENES . '_fk_updated_by FOREIGN KEY (updated_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_CURATES . ' ADD CONSTRAINT ' . TABLE_CURATES . '_fk_userid FOREIGN KEY (userid) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_CURATES . ' ADD CONSTRAINT ' . TABLE_CURATES . '_fk_geneid FOREIGN KEY (geneid) REFERENCES ' . TABLE_GENES . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_TRANSCRIPTS . ' ADD CONSTRAINT ' . TABLE_TRANSCRIPTS . '_fk_geneid FOREIGN KEY (geneid) REFERENCES ' . TABLE_GENES . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_TRANSCRIPTS . ' ADD CONSTRAINT ' . TABLE_TRANSCRIPTS . '_fk_created_by FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_TRANSCRIPTS . ' ADD CONSTRAINT ' . TABLE_TRANSCRIPTS . '_fk_edited_by FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_DISEASES . ' ADD CONSTRAINT ' . TABLE_DISEASES . '_fk_created_by FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_DISEASES . ' ADD CONSTRAINT ' . TABLE_DISEASES . '_fk_edited_by FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_GEN2DIS . ' ADD CONSTRAINT ' . TABLE_GEN2DIS . '_fk_geneid FOREIGN KEY (geneid) REFERENCES ' . TABLE_GENES . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_GEN2DIS . ' ADD CONSTRAINT ' . TABLE_GEN2DIS . '_fk_diseaseid FOREIGN KEY (diseaseid) REFERENCES ' . TABLE_DISEASES . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_INDIVIDUALS . ' ADD CONSTRAINT ' . TABLE_INDIVIDUALS . '_fk_ownerid FOREIGN KEY (ownerid) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_INDIVIDUALS . ' ADD CONSTRAINT ' . TABLE_INDIVIDUALS . '_fk_statusid FOREIGN KEY (statusid) REFERENCES ' . TABLE_DATA_STATUS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_INDIVIDUALS . ' ADD CONSTRAINT ' . TABLE_INDIVIDUALS . '_fk_created_by FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_INDIVIDUALS . ' ADD CONSTRAINT ' . TABLE_INDIVIDUALS . '_fk_edited_by FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_INDIVIDUALS . ' ADD CONSTRAINT ' . TABLE_INDIVIDUALS . '_fk_deleted_by FOREIGN KEY (deleted_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_IND2DIS . ' ADD CONSTRAINT ' . TABLE_IND2DIS . '_fk_individualid FOREIGN KEY (individualid) REFERENCES ' . TABLE_INDIVIDUALS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_IND2DIS . ' ADD CONSTRAINT ' . TABLE_IND2DIS . '_fk_diseaseid FOREIGN KEY (diseaseid) REFERENCES ' . TABLE_DISEASES . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' ADD CONSTRAINT ' . TABLE_VARIANTS . '_fk_pathogenicid FOREIGN KEY (pathogenicid) REFERENCES ' . TABLE_PATHOGENIC . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' ADD CONSTRAINT ' . TABLE_VARIANTS . '_fk_ownerid FOREIGN KEY (ownerid) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' ADD CONSTRAINT ' . TABLE_VARIANTS . '_fk_statusid FOREIGN KEY (statusid) REFERENCES ' . TABLE_DATA_STATUS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' ADD CONSTRAINT ' . TABLE_VARIANTS . '_fk_created_by FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' ADD CONSTRAINT ' . TABLE_VARIANTS . '_fk_edited_by FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' ADD CONSTRAINT ' . TABLE_VARIANTS . '_fk_deleted_by FOREIGN KEY (deleted_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' ADD CONSTRAINT ' . TABLE_PHENOTYPES . '_fk_diseaseid FOREIGN KEY (diseaseid) REFERENCES ' . TABLE_DISEASES . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' ADD CONSTRAINT ' . TABLE_PHENOTYPES . '_fk_individualid FOREIGN KEY (individualid) REFERENCES ' . TABLE_INDIVIDUALS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' ADD CONSTRAINT ' . TABLE_PHENOTYPES . '_fk_ownerid FOREIGN KEY (ownerid) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' ADD CONSTRAINT ' . TABLE_PHENOTYPES . '_fk_created_by FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' ADD CONSTRAINT ' . TABLE_PHENOTYPES . '_fk_edited_by FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' ADD CONSTRAINT ' . TABLE_PHENOTYPES . '_fk_deleted_by FOREIGN KEY (deleted_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' ADD CONSTRAINT ' . TABLE_SCREENINGS . '_fk_individualid FOREIGN KEY (individualid) REFERENCES ' . TABLE_INDIVIDUALS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' ADD CONSTRAINT ' . TABLE_SCREENINGS . '_fk_ownerid FOREIGN KEY (ownerid) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' ADD CONSTRAINT ' . TABLE_SCREENINGS . '_fk_created_by FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' ADD CONSTRAINT ' . TABLE_SCREENINGS . '_fk_edited_by FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' ADD CONSTRAINT ' . TABLE_SCREENINGS . '_fk_deleted_by FOREIGN KEY (deleted_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_SCR2GENE . ' ADD CONSTRAINT ' . TABLE_SCR2GENE . '_fk_screeningid FOREIGN KEY (screeningid) REFERENCES ' . TABLE_SCREENINGS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_SCR2GENE . ' ADD CONSTRAINT ' . TABLE_SCR2GENE . '_fk_geneid FOREIGN KEY (geneid) REFERENCES ' . TABLE_GENES . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_COLS . ' ADD CONSTRAINT ' . TABLE_COLS . '_fk_created_by FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_COLS . ' ADD CONSTRAINT ' . TABLE_COLS . '_fk_edited_by FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_ACTIVE_COLS . ' ADD CONSTRAINT ' . TABLE_ACTIVE_COLS . '_fk_colid FOREIGN KEY (colid) REFERENCES ' . TABLE_COLS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_ACTIVE_COLS . ' ADD CONSTRAINT ' . TABLE_ACTIVE_COLS . '_fk_created_by FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_SHARED_COLS . ' ADD CONSTRAINT ' . TABLE_SHARED_COLS . '_fk_geneid FOREIGN KEY (geneid) REFERENCES ' . TABLE_GENES . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_SHARED_COLS . ' ADD CONSTRAINT ' . TABLE_SHARED_COLS . '_fk_diseaseid FOREIGN KEY (diseaseid) REFERENCES ' . TABLE_DISEASES . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_SHARED_COLS . ' ADD CONSTRAINT ' . TABLE_SHARED_COLS . '_fk_colid FOREIGN KEY (colid) REFERENCES ' . TABLE_ACTIVE_COLS . ' (colid) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_SHARED_COLS . ' ADD CONSTRAINT ' . TABLE_SHARED_COLS . '_fk_created_by FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_SHARED_COLS . ' ADD CONSTRAINT ' . TABLE_SHARED_COLS . '_fk_edited_by FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_LINKS . ' ADD CONSTRAINT ' . TABLE_LINKS . '_fk_created_by FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_LINKS . ' ADD CONSTRAINT ' . TABLE_LINKS . '_fk_edited_by FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_COLS2LINKS . ' ADD CONSTRAINT ' . TABLE_COLS2LINKS . '_fk_colid FOREIGN KEY (colid) REFERENCES ' . TABLE_COLS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_COLS2LINKS . ' ADD CONSTRAINT ' . TABLE_COLS2LINKS . '_fk_linkid FOREIGN KEY (linkid) REFERENCES ' . TABLE_LINKS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_LOGS . ' ADD CONSTRAINT ' . TABLE_LOGS . '_fk_userid FOREIGN KEY (userid) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_HITS . ' ADD CONSTRAINT ' . TABLE_HITS . '_fk_geneid FOREIGN KEY (geneid) REFERENCES ' . TABLE_GENES . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                  ),
                       '3.0-pre-21' =>
                             array(
                                    'ALTER TABLE ' . TABLE_GENES . ' MODIFY COLUMN chrom_band VARCHAR(20) NOT NULL',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' DROP KEY `position_g_start`',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' ADD INDEX (chromosome, position_g_start, position_g_end)',
                                    'DELETE FROM ' . TABLE_COLS . ' WHERE id="Individual/Phenotype/Disease"',

                                    // VARIANTS
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' MODIFY COLUMN id INT(10) UNSIGNED ZEROFILL NOT NULL',
                                    'ALTER TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' DROP FOREIGN KEY ' . TABLE_VARIANTS_ON_TRANSCRIPTS . '_fk_id',
                                    'ALTER TABLE ' . TABLE_SCR2VAR . ' DROP FOREIGN KEY ' . TABLE_SCR2VAR . '_fk_variantid',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' DROP PRIMARY KEY',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' CHANGE COLUMN valid_from edited_date DATETIME NOT NULL',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' ADD PRIMARY KEY (id, edited_date)',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' MODIFY COLUMN id INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT',
                                    'ALTER TABLE ' . TABLE_SCR2VAR . ' ADD CONSTRAINT ' . TABLE_SCR2VAR . '_fk_variantid FOREIGN KEY (variantid) REFERENCES ' . TABLE_VARIANTS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' ADD CONSTRAINT ' . TABLE_VARIANTS_ON_TRANSCRIPTS . '_fk_id FOREIGN KEY (id) REFERENCES ' . TABLE_VARIANTS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' DROP COLUMN valid_to',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' DROP FOREIGN KEY ' . TABLE_VARIANTS . '_fk_deleted_by',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' DROP COLUMN deleted_by',
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' DROP COLUMN deleted',

                                    // VARIANTS_ON_TRANSCRIPTS
                                    'ALTER TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' DROP FOREIGN KEY ' . TABLE_VARIANTS_ON_TRANSCRIPTS . '_fk_id',
                                    'ALTER TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' DROP FOREIGN KEY ' . TABLE_VARIANTS_ON_TRANSCRIPTS . '_fk_transcriptid',
                                    'ALTER TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' DROP PRIMARY KEY',
                                    'ALTER TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' CHANGE COLUMN valid_from edited_date DATETIME NOT NULL',
                                    'ALTER TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' ADD PRIMARY KEY (id, edited_date, transcriptid)',
                                    'ALTER TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' ADD CONSTRAINT ' . TABLE_VARIANTS_ON_TRANSCRIPTS . '_fk_id FOREIGN KEY (id) REFERENCES ' . TABLE_VARIANTS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' ADD CONSTRAINT ' . TABLE_VARIANTS_ON_TRANSCRIPTS . '_fk_transcriptid FOREIGN KEY (transcriptid) REFERENCES ' . TABLE_TRANSCRIPTS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',

                                    // INDIVIDUALS
                                    'ALTER TABLE ' . TABLE_INDIVIDUALS . ' MODIFY COLUMN id MEDIUMINT(8) UNSIGNED ZEROFILL NOT NULL',
                                    'ALTER TABLE ' . TABLE_IND2DIS . ' DROP FOREIGN KEY ' . TABLE_IND2DIS . '_fk_individualid',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' DROP FOREIGN KEY ' . TABLE_PHENOTYPES . '_fk_individualid',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' DROP FOREIGN KEY ' . TABLE_SCREENINGS . '_fk_individualid',
                                    'ALTER TABLE ' . TABLE_INDIVIDUALS . ' DROP PRIMARY KEY',
                                    'ALTER TABLE ' . TABLE_INDIVIDUALS . ' CHANGE COLUMN valid_from edited_date DATETIME NOT NULL',
                                    'ALTER TABLE ' . TABLE_INDIVIDUALS . ' ADD PRIMARY KEY (id, edited_date)',
                                    'ALTER TABLE ' . TABLE_INDIVIDUALS . ' MODIFY COLUMN id MEDIUMINT(8) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT',
                                    'ALTER TABLE ' . TABLE_IND2DIS . ' ADD CONSTRAINT ' . TABLE_IND2DIS . '_fk_individualid FOREIGN KEY (individualid) REFERENCES ' . TABLE_INDIVIDUALS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' ADD CONSTRAINT ' . TABLE_PHENOTYPES . '_fk_individualid FOREIGN KEY (individualid) REFERENCES ' . TABLE_INDIVIDUALS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' ADD CONSTRAINT ' . TABLE_SCREENINGS . '_fk_individualid FOREIGN KEY (individualid) REFERENCES ' . TABLE_INDIVIDUALS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_INDIVIDUALS . ' DROP COLUMN valid_to',
                                    'ALTER TABLE ' . TABLE_INDIVIDUALS . ' DROP FOREIGN KEY ' . TABLE_INDIVIDUALS . '_fk_deleted_by',
                                    'ALTER TABLE ' . TABLE_INDIVIDUALS . ' DROP COLUMN deleted_by',
                                    'ALTER TABLE ' . TABLE_INDIVIDUALS . ' DROP COLUMN deleted',

                                    // PHENOTYPES
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' MODIFY COLUMN id INT(10) UNSIGNED ZEROFILL NOT NULL',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' DROP PRIMARY KEY',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' CHANGE COLUMN valid_from edited_date DATETIME NOT NULL',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' ADD PRIMARY KEY (id, edited_date)',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' MODIFY COLUMN id INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' DROP COLUMN valid_to',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' DROP FOREIGN KEY ' . TABLE_PHENOTYPES . '_fk_deleted_by',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' DROP COLUMN deleted_by',
                                    'ALTER TABLE ' . TABLE_PHENOTYPES . ' DROP COLUMN deleted',

                                    // SCREENINGS
                                    'ALTER TABLE ' . TABLE_SCR2GENE . ' DROP FOREIGN KEY ' . TABLE_SCR2GENE . '_fk_screeningid',
                                    'ALTER TABLE ' . TABLE_SCR2VAR . ' DROP FOREIGN KEY ' . TABLE_SCR2VAR . '_fk_screeningid',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' DROP COLUMN valid_from',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' ADD COLUMN edited_date DATETIME NOT NULL AFTER edited_by',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' MODIFY COLUMN id INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT',
                                    'ALTER TABLE ' . TABLE_SCR2GENE . ' ADD CONSTRAINT ' . TABLE_SCR2GENE . '_fk_screeningid FOREIGN KEY (screeningid) REFERENCES ' . TABLE_SCREENINGS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_SCR2VAR . ' ADD CONSTRAINT ' . TABLE_SCR2VAR . '_fk_screeningid FOREIGN KEY (screeningid) REFERENCES ' . TABLE_SCREENINGS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' DROP COLUMN valid_to',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' DROP FOREIGN KEY ' . TABLE_SCREENINGS . '_fk_deleted_by',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' DROP COLUMN deleted_by',
                                    'ALTER TABLE ' . TABLE_SCREENINGS . ' DROP COLUMN deleted',
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
                  );

    // Addition for upgrade to LOVD v.3.0-pre-07.
    if ($sCalcVersionDB < lovd_calculateVersion('3.0-pre-07')) {
        // Simply reload all custom columns.
        require ROOT_PATH . 'install/inc-sql-columns.php';
        $aUpdates['3.0-pre-07'] = array_merge($aUpdates['3.0-pre-07'], $aColSQL);
    }

    if ($sCalcVersionDB < lovd_calculateVersion('3.0-pre-19')) {
        $q = lovd_queryDB_Old('DESCRIBE ' . TABLE_PAT2DIS);
        if ($q) {
            // User has installed his LOVD *before* 3.0-pre-19 officially came out, but *after* some files had already been put in the SVN repository.
            $aUpdates['3.0-pre-19'][] = 'INSERT INTO ' . TABLE_IND2DIS . '(individualid, diseaseid) SELECT * FROM ' . TABLE_PAT2DIS;
            $aUpdates['3.0-pre-19'][] = 'DROP TABLE ' . TABLE_PAT2DIS;
        }
        $aUpdates['3.0-pre-19'][] = 'DELETE FROM ' . TABLE_ACTIVE_COLS . ' WHERE colid LIKE "Patient/%"';
        $q = lovd_queryDB_Old('DESCRIBE ' . TABLE_INDIVIDUALS);
        if ($q) {
            // FIXME; this can never be true???
            while($aColumn = mysql_fetch_assoc($q)) {
                if (substr($aColumn['Field'], 0, 8) == 'Patient/') {
                    $aUpdates['3.0-pre-19'][] = 'ALTER TABLE ' . TABLE_INDIVIDUALS . ' CHANGE `' . $aColumn['Field'] . '` `' . str_replace('Patient/', 'Individual/', $aColumn['Field']) . '` ' . strtoupper($aColumn['Type']) . ' ' . ($aColumn['Null'] == 'NO'? 'NOT NULL' : 'NULL') . (empty($aColumn['Default'])? '' : ' DEFAULT ' . $aColumn['Default']);
                    $aUpdates['3.0-pre-19'][] = 'INSERT INTO ' . TABLE_ACTIVE_COLS . ' VALUES("' . str_replace('Patient/', 'Individual/', $aColumn['Field']) . '", 1, NOW())';
                }
                if ($aColumn['Field'] == 'Patient/Patient_ID') {
                    $aUpdates['3.0-pre-19'][] = 'ALTER TABLE ' . TABLE_INDIVIDUALS . ' CHANGE `' .str_replace('Patient/', 'Individual/', $aColumn['Field']) . '` `Individual/Lab_ID` VARCHAR(15) NOT NULL';
                }
            }
        }
        $q = lovd_queryDB_Old('DESCRIBE ' . TABLE_SCREENINGS);
        if ($q) {
            // FIXME; this should never be false???
            while($aColumn = mysql_fetch_assoc($q)) {
                if (substr($aColumn['Field'], 0, 10) == 'Screening/') {
                    $aUpdates['3.0-pre-19'][] = 'INSERT INTO ' . TABLE_ACTIVE_COLS . ' VALUES("' . $aColumn['Field'] . '", 1, NOW())';
                }
            }
        }
    }

    if ($sCalcVersionDB < lovd_calculateVersion('3.0-pre-20')) {
        $q = lovd_queryDB_Old('DESCRIBE ' . TABLE_INDIVIDUALS);
        if ($q) {
            while($aColumn = mysql_fetch_assoc($q)) {
                if ($aColumn['Field'] == 'Individual/Gender') {
                    $aUpdates['3.0-pre-20'][] = 'ALTER TABLE ' . TABLE_INDIVIDUALS . ' MODIFY COLUMN `Individual/Gender` VARCHAR(7) NOT NULL';
                }
            }
        }
    }

    if ($sCalcVersionDB < lovd_calculateVersion('3.0-pre-21')) {
        $q = lovd_queryDB_Old('DESCRIBE ' . TABLE_INDIVIDUALS);
        if ($q) {
            while($aColumn = mysql_fetch_assoc($q)) {
                if ($aColumn['Field'] == 'Individual/Phenotype/Disease') {
                    $aUpdates['3.0-pre-21'][] = 'ALTER TABLE ' . TABLE_INDIVIDUALS . ' DROP COLUMN `Individual/Phenotype/Disease`';
                }
            }
        }
    }
    
    if ($sCalcVersionDB < lovd_calculateVersion('3.0-alpha-01')) {
        // Simply reload all custom columns.
        require ROOT_PATH . 'install/inc-sql-columns.php';
        $aUpdates['3.0-alpha-01'][] = 'DELETE FROM ' . TABLE_COLS . ' WHERE col_order < 255';
        $aUpdates['3.0-alpha-01'] = array_merge($aUpdates['3.0-alpha-01'], $aColSQL);
    }



    // To make sure we upgrade the database correctly, we add the current version to the list...
    if (!isset($aUpdates[$_SETT['system']['version']])) {
        $aUpdates[$_SETT['system']['version']] = array();
    }

    require ROOT_PATH . 'class/progress_bar.php';
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
    $sFormNextPage .= '          <INPUT type="submit" id="submit" value="Proceed &gt;&gt;">' . "\n" .
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
        lovd_queryDB_Old($sQ);
        $bLocked = !mysql_affected_rows();
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
        $nSQL = count($aUpdates, true);

        // Actually run the SQL...
        $nSQLDone = 0;
        $nSQLDonePercentage = 0;
        $nSQLDonePercentagePrev = 0;
        $nSQLFailed = 0;
        $sSQLFailed = '';

        foreach ($aUpdates as $sVersion => $aSQL) {
            if (lovd_calculateVersion($sVersion) <= $sCalcVersionDB || lovd_calculateVersion($sVersion) > $sCalcVersionFiles) {
                continue;
            }
            $_BAR->setMessage('To ' . $sVersion . '...');

            $aSQL[] = 'UPDATE ' . TABLE_STATUS . ' SET version = "' . $sVersion . '", updated_date = NOW()';

            // Loop needed queries...
            foreach ($aSQL as $i => $sSQL) {
                $i ++;
                if (!$nSQLFailed) {
                    $q = mysql_query($sSQL); // This means that there is no SQL injection check here. But hey - these are our own queries. DON'T USE lovd_queryDB_Old(). It complains because there are ?s in the queries.
                    if (!$q) {
                        $nSQLFailed ++;
                        // Error when running query.
                        $sError = mysql_error();
                        lovd_queryError('RunUpgradeSQL', $sSQL, $sError, false);
                        $sSQLFailed = 'Error!<BR><BR>\n\n' .
                                      'Error while executing query ' . $i . ':\n' .
                                      '<PRE style="background : #F0F0F0;">' . htmlspecialchars($sError) . '</PRE><BR>\n\n' .
                                      'This implies these MySQL queries need to be executed manually:<BR>\n' .
                                      '<PRE style="background : #F0F0F0;">\n<SPAN style="background : #C0C0C0;">' . str_pad($i, strlen(count($aSQL)), ' ', STR_PAD_LEFT) . '</SPAN> ' . htmlspecialchars($sSQL) . ';\n';

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
                    $sSQLFailed .= '<SPAN style="background : #C0C0C0;">' . str_pad($i, strlen(count($aSQL)), ' ', STR_PAD_LEFT) . '</SPAN> ' . htmlspecialchars($sSQL) . ';\n';
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
        $q = lovd_queryDB_Old('UPDATE ' . TABLE_STATUS . ' SET lock_update = 0');
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
