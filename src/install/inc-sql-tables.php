<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-22
 * Modified    : 2017-04-24
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2017 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               M. Kroon <m.kroon@lumc.nl>
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

// IDs:
// WARNING: If editing any of these, also edit $_SETT['objectid_length']!
// userid SMALLINT(5) UNSIGNED (65K)
// geneid VARCHAR(25) (25 characters)
// transcriptid MEDIUMINT(8) UNSIGNED (16M)
// diseaseid SMALLINT(5) UNSIGNED (65K)
// individualid MEDIUMINT(8) UNSIGNED (16M)
// variantid INT(10) UNSIGNED (4294M)
// phenotypeid INT(10) UNSIGNED (4294M)
// screeningid INT(10) UNSIGNED (4294M)
// colid VARCHAR(100) (100 characters)
// linkid TINYINT(3) UNSIGNED (255)

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', '../');
    require ROOT_PATH . 'inc-init.php';
    lovd_requireAUTH(LEVEL_MANAGER);
}

$sSettings = 'ENGINE=InnoDB,
    DEFAULT CHARACTER SET utf8';

$aTableSQL =
         array('TABLE_COUNTRIES' =>
   'CREATE TABLE ' . TABLE_COUNTRIES . ' (
    id CHAR(2) NOT NULL,
    name VARCHAR(255) NOT NULL,
    PRIMARY KEY (id))
    ' . $sSettings

         , 'TABLE_USERS' =>
   'CREATE TABLE ' . TABLE_USERS . ' (
    id SMALLINT(5) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    orcid_id CHAR(19),
    orcid_confirmed BOOLEAN NOT NULL DEFAULT 0,
    name VARCHAR(75) NOT NULL,
    institute VARCHAR(255) NOT NULL,
    department VARCHAR(255) NOT NULL DEFAULT \'\',
    telephone VARCHAR(50) NOT NULL DEFAULT \'\',
    address TEXT NOT NULL,
    city VARCHAR(255) NOT NULL,
    countryid CHAR(2),
    email TEXT NOT NULL,
    email_confirmed BOOLEAN NOT NULL DEFAULT 0,
    reference VARCHAR(50) NOT NULL DEFAULT \'\',
    username VARCHAR(20) NOT NULL,
    password CHAR(50) NOT NULL,
    password_autogen CHAR(50),
    password_force_change BOOLEAN NOT NULL DEFAULT 0,
    auth_token CHAR(32),
    auth_token_expires DATETIME,
    phpsessid CHAR(32),
    saved_work TEXT,
    level TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    allowed_ip VARCHAR(255) NOT NULL DEFAULT \'*\',
    login_attempts TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    last_login DATETIME,
    created_by SMALLINT(5) UNSIGNED ZEROFILL,
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    edited_by SMALLINT(5) UNSIGNED ZEROFILL,
    edited_date DATETIME,
    PRIMARY KEY (id),
    UNIQUE (orcid_id),
    INDEX (countryid),
    UNIQUE (username),
    INDEX (created_by),
    INDEX (edited_by),
    CONSTRAINT ' . TABLE_USERS . '_fk_countryid FOREIGN KEY (countryid) REFERENCES ' . TABLE_COUNTRIES . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_USERS . '_fk_created_by FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_USERS . '_fk_edited_by FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE)
    ' . $sSettings

         , 'TABLE_COLLEAGUES' =>
             'CREATE TABLE ' . TABLE_COLLEAGUES . '(
    userid_from SMALLINT(5) UNSIGNED ZEROFILL NOT NULL,
    userid_to   SMALLINT(5) UNSIGNED ZEROFILL NOT NULL,
    allow_edit  BOOLEAN NOT NULL DEFAULT 0,
    PRIMARY KEY (userid_from, userid_to),
    INDEX (userid_to),
    CONSTRAINT ' . TABLE_COLLEAGUES .  '_fk_userid_from FOREIGN KEY (userid_from) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_COLLEAGUES . '_fk_userid_to FOREIGN KEY (userid_to) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE)
    ' . $sSettings

        , 'TABLE_CHROMOSOMES' =>
   'CREATE TABLE ' . TABLE_CHROMOSOMES . ' (
    name VARCHAR(2) NOT NULL,
    sort_id TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
    hg18_id_ncbi VARCHAR(20) NOT NULL,
    hg19_id_ncbi VARCHAR(20) NOT NULL,
    PRIMARY KEY (name))
    ' . $sSettings

         , 'TABLE_GENES' =>
   'CREATE TABLE ' . TABLE_GENES . ' (
    id VARCHAR(25) NOT NULL,
    name VARCHAR(255) NOT NULL,
    chromosome VARCHAR(2),
    chrom_band VARCHAR(20) NOT NULL DEFAULT \'\',
    imprinting VARCHAR(10) NOT NULL DEFAULT "unknown",
    refseq_genomic VARCHAR(15) NOT NULL DEFAULT \'\',
    refseq_UD VARCHAR(25) NOT NULL DEFAULT \'\',
    reference VARCHAR(255) NOT NULL DEFAULT \'\',
    url_homepage VARCHAR(255) NOT NULL DEFAULT \'\',
    url_external TEXT,
    allow_download BOOLEAN NOT NULL DEFAULT 0,
    allow_index_wiki BOOLEAN NOT NULL DEFAULT 0,
    id_hgnc INT(10) UNSIGNED NOT NULL,
    id_entrez INT(10) UNSIGNED,
    id_omim INT(10) UNSIGNED,
    show_hgmd BOOLEAN NOT NULL DEFAULT 0,
    show_genecards BOOLEAN NOT NULL DEFAULT 0,
    show_genetests BOOLEAN NOT NULL DEFAULT 0,
    note_index TEXT,
    note_listing TEXT,
    refseq VARCHAR(1) NOT NULL DEFAULT \'\',
    refseq_url VARCHAR(255) NOT NULL DEFAULT \'\',
    disclaimer TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    disclaimer_text TEXT,
    header TEXT,
    header_align TINYINT(1) NOT NULL DEFAULT -1,
    footer TEXT,
    footer_align TINYINT(1) NOT NULL DEFAULT -1,
    created_by SMALLINT(5) UNSIGNED ZEROFILL,
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    edited_by SMALLINT(5) UNSIGNED ZEROFILL,
    edited_date DATETIME,
    updated_by SMALLINT(5) UNSIGNED ZEROFILL,
    updated_date DATETIME,
    PRIMARY KEY (id),
    INDEX (chromosome),
    UNIQUE (id_hgnc),
    INDEX (created_by),
    INDEX (edited_by),
    INDEX (updated_by),
    CONSTRAINT ' . TABLE_GENES . '_fk_chromosome FOREIGN KEY (chromosome) REFERENCES ' . TABLE_CHROMOSOMES . ' (name) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_GENES . '_fk_created_by FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_GENES . '_fk_edited_by FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_GENES . '_fk_updated_by FOREIGN KEY (updated_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE)
    ' . $sSettings

         , 'TABLE_CURATES' =>
   'CREATE TABLE ' . TABLE_CURATES . ' (
    userid SMALLINT(5) UNSIGNED ZEROFILL NOT NULL,
    geneid VARCHAR(25) NOT NULL,
    allow_edit BOOLEAN NOT NULL DEFAULT 0,
    show_order TINYINT(2) UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (userid, geneid),
    INDEX (geneid),
    CONSTRAINT ' . TABLE_CURATES . '_fk_userid FOREIGN KEY (userid) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_CURATES . '_fk_geneid FOREIGN KEY (geneid) REFERENCES ' . TABLE_GENES . ' (id) ON DELETE CASCADE ON UPDATE CASCADE)
    ' . $sSettings

         , 'TABLE_TRANSCRIPTS' =>
   'CREATE TABLE ' . TABLE_TRANSCRIPTS . ' (
    id MEDIUMINT(8) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    geneid VARCHAR(25) NOT NULL,
    name VARCHAR(255) NOT NULL,
    id_mutalyzer TINYINT(3) UNSIGNED ZEROFILL,
    id_ncbi VARCHAR(255) NOT NULL DEFAULT \'\',
    id_ensembl VARCHAR(255) NOT NULL DEFAULT \'\',
    id_protein_ncbi VARCHAR(255) NOT NULL DEFAULT \'\',
    id_protein_ensembl VARCHAR(255) NOT NULL DEFAULT \'\',
    id_protein_uniprot VARCHAR(8) NOT NULL DEFAULT \'\',
    remarks TEXT,
    position_c_mrna_start SMALLINT(5) NOT NULL,
    position_c_mrna_end MEDIUMINT(8) UNSIGNED NOT NULL,
    position_c_cds_end MEDIUMINT(8) UNSIGNED NOT NULL,
    position_g_mrna_start INT(10) UNSIGNED NOT NULL,
    position_g_mrna_end INT(10) UNSIGNED NOT NULL,
    created_by SMALLINT(5) UNSIGNED ZEROFILL,
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    edited_by SMALLINT(5) UNSIGNED ZEROFILL,
    edited_date DATETIME,
    PRIMARY KEY (id),
    INDEX (geneid),
    UNIQUE (id_ncbi),
    INDEX (created_by),
    INDEX (edited_by),
    CONSTRAINT ' . TABLE_TRANSCRIPTS . '_fk_geneid FOREIGN KEY (geneid) REFERENCES ' . TABLE_GENES . ' (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_TRANSCRIPTS . '_fk_created_by FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_TRANSCRIPTS . '_fk_edited_by FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE)
    ' . $sSettings

         , 'TABLE_DISEASES' =>
   'CREATE TABLE ' . TABLE_DISEASES . ' (
    id SMALLINT(5) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    symbol VARCHAR(25) NOT NULL DEFAULT \'\',
    name VARCHAR(255) NOT NULL,
    id_omim INT(10) UNSIGNED,
    tissues TEXT,
    features TEXT,
    remarks TEXT,
    created_by SMALLINT(5) UNSIGNED ZEROFILL,
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    edited_by SMALLINT(5) UNSIGNED ZEROFILL,
    edited_date DATETIME,
    PRIMARY KEY (id),
    UNIQUE(id_omim),
    INDEX (created_by),
    INDEX (edited_by),
    CONSTRAINT ' . TABLE_DISEASES . '_fk_created_by FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_DISEASES . '_fk_edited_by FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE)
    ' . $sSettings

         , 'TABLE_GEN2DIS' =>
   'CREATE TABLE ' . TABLE_GEN2DIS . ' (
    geneid VARCHAR(25) NOT NULL,
    diseaseid SMALLINT(5) UNSIGNED ZEROFILL NOT NULL,
    PRIMARY KEY (geneid, diseaseid),
    INDEX (diseaseid),
    CONSTRAINT ' . TABLE_GEN2DIS . '_fk_geneid FOREIGN KEY (geneid) REFERENCES ' . TABLE_GENES . ' (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_GEN2DIS . '_fk_diseaseid FOREIGN KEY (diseaseid) REFERENCES ' . TABLE_DISEASES . ' (id) ON DELETE CASCADE ON UPDATE CASCADE)
    ' . $sSettings

         , 'TABLE_DATA_STATUS' =>
   'CREATE TABLE ' . TABLE_DATA_STATUS . ' (
    id TINYINT(1) UNSIGNED NOT NULL,
    name VARCHAR(15) NOT NULL,
    PRIMARY KEY (id))
    ' . $sSettings

             , 'TABLE_ALLELES' =>
   'CREATE TABLE ' . TABLE_ALLELES . ' (
    id TINYINT(2) UNSIGNED NOT NULL,
    name VARCHAR(20) NOT NULL,
    display_order TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id))
    ' . $sSettings

         , 'TABLE_EFFECT' =>
   'CREATE TABLE ' . TABLE_EFFECT . ' (
    id TINYINT(2) UNSIGNED ZEROFILL NOT NULL,
    name VARCHAR(5) NOT NULL,
    PRIMARY KEY (id))
    ' . $sSettings

          , 'TABLE_INDIVIDUALS' =>
   'CREATE TABLE ' . TABLE_INDIVIDUALS . ' (
    id MEDIUMINT(8) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    fatherid MEDIUMINT(8) UNSIGNED ZEROFILL,
    motherid MEDIUMINT(8) UNSIGNED ZEROFILL,
    panelid MEDIUMINT(8) UNSIGNED ZEROFILL,
    panel_size MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT 1,
    owned_by SMALLINT(5) UNSIGNED ZEROFILL,
    statusid TINYINT(1) UNSIGNED,
    created_by SMALLINT(5) UNSIGNED ZEROFILL,
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    edited_by SMALLINT(5) UNSIGNED ZEROFILL,
    edited_date DATETIME,
    PRIMARY KEY (id),
    INDEX (fatherid),
    INDEX (motherid),
    INDEX (owned_by),
    INDEX (statusid),
    INDEX (created_by),
    INDEX (edited_by),
    CONSTRAINT ' . TABLE_INDIVIDUALS . '_fk_fatherid FOREIGN KEY (fatherid) REFERENCES ' . TABLE_INDIVIDUALS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_INDIVIDUALS . '_fk_motherid FOREIGN KEY (motherid) REFERENCES ' . TABLE_INDIVIDUALS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_INDIVIDUALS . '_fk_panelid FOREIGN KEY (panelid) REFERENCES ' . TABLE_INDIVIDUALS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_INDIVIDUALS . '_fk_owned_by FOREIGN KEY (owned_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_INDIVIDUALS . '_fk_statusid FOREIGN KEY (statusid) REFERENCES ' . TABLE_DATA_STATUS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_INDIVIDUALS . '_fk_created_by FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_INDIVIDUALS . '_fk_edited_by FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE)
    ' . $sSettings

//          , 'TABLE_INDIVIDUALS_REV' =>
//   'CREATE TABLE ' . TABLE_INDIVIDUALS_REV . ' (
//    id MEDIUMINT(8) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
//    owned_by SMALLINT(5) UNSIGNED ZEROFILL,
//    statusid TINYINT(1) UNSIGNED,
//    edited_by SMALLINT(5) UNSIGNED ZEROFILL,
//    valid_from DATETIME NOT NULL,
//    valid_to DATETIME NOT NULL DEFAULT "9999-12-31",
//    deleted BOOLEAN NOT NULL,
//    deleted_by SMALLINT(5) UNSIGNED ZEROFILL,
//    PRIMARY KEY (id, valid_from),
//    INDEX (valid_to),
//    INDEX (owned_by),
//    INDEX (statusid),
//    INDEX (edited_by),
//    INDEX (deleted_by),
//    CONSTRAINT ' . TABLE_INDIVIDUALS . '_fk_owned_by FOREIGN KEY (owned_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
//    CONSTRAINT ' . TABLE_INDIVIDUALS . '_fk_statusid FOREIGN KEY (statusid) REFERENCES ' . TABLE_DATA_STATUS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
//    CONSTRAINT ' . TABLE_INDIVIDUALS . '_fk_edited_by FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
//    CONSTRAINT ' . TABLE_INDIVIDUALS . '_fk_deleted_by FOREIGN KEY (deleted_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE)
//    ' . $sSettings

         , 'TABLE_IND2DIS' =>
   'CREATE TABLE ' . TABLE_IND2DIS . ' (
    individualid MEDIUMINT(8) UNSIGNED ZEROFILL NOT NULL,
    diseaseid SMALLINT(5) UNSIGNED ZEROFILL NOT NULL,
    PRIMARY KEY (individualid, diseaseid),
    INDEX (diseaseid),
    CONSTRAINT ' . TABLE_IND2DIS . '_fk_individualid FOREIGN KEY (individualid) REFERENCES ' . TABLE_INDIVIDUALS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_IND2DIS . '_fk_diseaseid FOREIGN KEY (diseaseid) REFERENCES ' . TABLE_DISEASES . ' (id) ON DELETE CASCADE ON UPDATE CASCADE)
    ' . $sSettings

         , 'TABLE_VARIANTS' =>
   'CREATE TABLE ' . TABLE_VARIANTS . ' (
    id INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    allele TINYINT(2) UNSIGNED NOT NULL DEFAULT 0,
    effectid TINYINT(2) UNSIGNED ZEROFILL,
    chromosome VARCHAR(2),
    position_g_start INT(10) UNSIGNED,
    position_g_end INT(10) UNSIGNED,
    type VARCHAR(10),
    mapping_flags TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
    average_frequency FLOAT UNSIGNED,
    owned_by SMALLINT(5) UNSIGNED ZEROFILL,
    statusid TINYINT(1) UNSIGNED,
    created_by SMALLINT(5) UNSIGNED ZEROFILL,
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    edited_by SMALLINT(5) UNSIGNED ZEROFILL,
    edited_date DATETIME,
    PRIMARY KEY (id),
    INDEX (allele),
    INDEX (effectid),
    INDEX (chromosome, position_g_start, position_g_end),
    INDEX (average_frequency),
    INDEX (owned_by),
    INDEX (statusid),
    INDEX (created_by),
    INDEX (edited_by),
    CONSTRAINT ' . TABLE_VARIANTS . '_fk_allele FOREIGN KEY (allele) REFERENCES ' . TABLE_ALLELES . ' (id) ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_VARIANTS . '_fk_effectid FOREIGN KEY (effectid) REFERENCES ' . TABLE_EFFECT . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_VARIANTS . '_fk_chromosome FOREIGN KEY (chromosome) REFERENCES ' . TABLE_CHROMOSOMES . ' (name) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_VARIANTS . '_fk_owned_by FOREIGN KEY (owned_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_VARIANTS . '_fk_statusid FOREIGN KEY (statusid) REFERENCES ' . TABLE_DATA_STATUS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_VARIANTS . '_fk_created_by FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_VARIANTS . '_fk_edited_by FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE)
    ' . $sSettings

//         , 'TABLE_VARIANTS_REV' =>
//   'CREATE TABLE ' . TABLE_VARIANTS_REV . ' (
//    id INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
//    allele TINYINT(2) UNSIGNED NOT NULL,
//    effectid TINYINT(2) UNSIGNED ZEROFILL,
//    chromosome VARCHAR(2) NOT NULL,
//    position_g_start INT(10) UNSIGNED,
//    position_g_end INT(10) UNSIGNED,
//    type VARCHAR(10),
//    owned_by SMALLINT(5) UNSIGNED ZEROFILL,
//    statusid TINYINT(1) UNSIGNED,
//    edited_by SMALLINT(5) UNSIGNED ZEROFILL,
//    valid_from DATETIME NOT NULL,
//    valid_to DATETIME NOT NULL DEFAULT "9999-12-31",
//    deleted BOOLEAN NOT NULL,
//    deleted_by SMALLINT(5) UNSIGNED ZEROFILL,
//    PRIMARY KEY (id, valid_from),
//    INDEX (valid_to),
//    INDEX (allele),
//    INDEX (effectid),
//    INDEX (chromosome, position_g_start, position_g_end),
//    INDEX (owned_by),
//    INDEX (statusid),
//    INDEX (edited_by),
//    INDEX (deleted_by),
//    CONSTRAINT ' . TABLE_VARIANTS . '_fk_effectid FOREIGN KEY (effectid) REFERENCES ' . TABLE_ALLELES . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
//    CONSTRAINT ' . TABLE_VARIANTS . '_fk_owned_by FOREIGN KEY (owned_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
//    CONSTRAINT ' . TABLE_VARIANTS . '_fk_statusid FOREIGN KEY (statusid) REFERENCES ' . TABLE_DATA_STATUS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
//    CONSTRAINT ' . TABLE_VARIANTS . '_fk_edited_by FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
//    CONSTRAINT ' . TABLE_VARIANTS . '_fk_deleted_by FOREIGN KEY (deleted_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE)
//    ' . $sSettings

         , 'TABLE_VARIANTS_ON_TRANSCRIPTS' =>
   'CREATE TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' (
    id INT(10) UNSIGNED ZEROFILL NOT NULL,
    transcriptid MEDIUMINT(8) UNSIGNED ZEROFILL NOT NULL,
    effectid TINYINT(2) UNSIGNED ZEROFILL,
    position_c_start MEDIUMINT,
    position_c_start_intron INT,
    position_c_end MEDIUMINT,
    position_c_end_intron INT,
    PRIMARY KEY (id, transcriptid),
    INDEX (transcriptid),
    INDEX (effectid),
    INDEX (position_c_start, position_c_end),
    INDEX (position_c_start, position_c_start_intron, position_c_end, position_c_end_intron),
    CONSTRAINT ' . TABLE_VARIANTS_ON_TRANSCRIPTS . '_fk_id FOREIGN KEY (id) REFERENCES ' . TABLE_VARIANTS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_VARIANTS_ON_TRANSCRIPTS . '_fk_transcriptid FOREIGN KEY (transcriptid) REFERENCES ' . TABLE_TRANSCRIPTS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_VARIANTS_ON_TRANSCRIPTS . '_fk_effectid FOREIGN KEY (effectid) REFERENCES ' . TABLE_EFFECT . ' (id) ON DELETE SET NULL ON UPDATE CASCADE)
    ' . $sSettings

//         , 'TABLE_VARIANTS_ON_TRANSCRIPTS_REV' =>
//   'CREATE TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS_REV . ' (
//    id INT(10) UNSIGNED ZEROFILL NOT NULL,
//    transcriptid SMALLINT(5) UNSIGNED ZEROFILL NOT NULL,
//    effectid TINYINT(2) UNSIGNED ZEROFILL,
//    position_c_start MEDIUMINT(8),
//    position_c_start_intron INT(10),
//    position_c_end MEDIUMINT(8),
//    position_c_end_intron INT(10),
//    valid_from DATETIME NOT NULL,
//    PRIMARY KEY (id, valid_from, transcriptid),
//    INDEX (transcriptid),
//    INDEX (effectid),
//    INDEX (position_c_start, position_c_end),
//    INDEX (position_c_start, position_c_start_intron, position_c_end, position_c_end_intron),
//    CONSTRAINT ' . TABLE_VARIANTS_ON_TRANSCRIPTS . '_fk_id FOREIGN KEY (id) REFERENCES ' . TABLE_VARIANTS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE,
//    CONSTRAINT ' . TABLE_VARIANTS_ON_TRANSCRIPTS . '_fk_transcriptid FOREIGN KEY (transcriptid) REFERENCES ' . TABLE_TRANSCRIPTS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE,
//    CONSTRAINT ' . TABLE_VARIANTS_ON_TRANSCRIPTS . '_fk_effectid FOREIGN KEY (effectid) REFERENCES ' . TABLE_ALLELES . ' (id) ON DELETE SET NULL ON UPDATE CASCADE)
//    ' . $sSettings

         , 'TABLE_PHENOTYPES' =>
   'CREATE TABLE ' . TABLE_PHENOTYPES . ' (
    id INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    diseaseid SMALLINT(5) UNSIGNED ZEROFILL NOT NULL,
    individualid MEDIUMINT(8) UNSIGNED ZEROFILL NOT NULL,
    owned_by SMALLINT(5) UNSIGNED ZEROFILL,
    statusid TINYINT(1) UNSIGNED,
    created_by SMALLINT(5) UNSIGNED ZEROFILL,
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    edited_by SMALLINT(5) UNSIGNED ZEROFILL,
    edited_date DATETIME,
    PRIMARY KEY (id),
    INDEX (diseaseid),
    INDEX (individualid),
    INDEX (owned_by),
    INDEX (statusid),
    INDEX (created_by),
    INDEX (edited_by),
    CONSTRAINT ' . TABLE_PHENOTYPES . '_fk_diseaseid FOREIGN KEY (diseaseid) REFERENCES ' . TABLE_DISEASES . ' (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_PHENOTYPES . '_fk_individualid FOREIGN KEY (individualid) REFERENCES ' . TABLE_INDIVIDUALS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_PHENOTYPES . '_fk_owned_by FOREIGN KEY (owned_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_PHENOTYPES . '_fk_statusid FOREIGN KEY (statusid) REFERENCES ' . TABLE_DATA_STATUS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_PHENOTYPES . '_fk_created_by FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_PHENOTYPES . '_fk_edited_by FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE)
    ' . $sSettings

//         , 'TABLE_PHENOTYPES_REV' =>
//   'CREATE TABLE ' . TABLE_PHENOTYPES_REV . ' (
//    id INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
//    diseaseid SMALLINT(5) UNSIGNED ZEROFILL NOT NULL,
//    individualid MEDIUMINT(8) UNSIGNED ZEROFILL NOT NULL,
//    owned_by SMALLINT(5) UNSIGNED ZEROFILL,
//    edited_by SMALLINT(5) UNSIGNED ZEROFILL,
//    valid_from DATETIME NOT NULL,
//    valid_to DATETIME NOT NULL DEFAULT "9999-12-31",
//    deleted BOOLEAN NOT NULL,
//    deleted_by SMALLINT(5) UNSIGNED ZEROFILL,
//    PRIMARY KEY (id, valid_from),
//    INDEX (valid_to),
//    INDEX (diseaseid),
//    INDEX (individualid),
//    INDEX (owned_by),
//    INDEX (edited_by),
//    INDEX (deleted_by),
//    CONSTRAINT ' . TABLE_PHENOTYPES . '_fk_diseaseid FOREIGN KEY (diseaseid) REFERENCES ' . TABLE_DISEASES . ' (id) ON DELETE CASCADE ON UPDATE CASCADE,
//    CONSTRAINT ' . TABLE_PHENOTYPES . '_fk_individualid FOREIGN KEY (individualid) REFERENCES ' . TABLE_INDIVIDUALS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE,
//    CONSTRAINT ' . TABLE_PHENOTYPES . '_fk_owned_by FOREIGN KEY (owned_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
//    CONSTRAINT ' . TABLE_PHENOTYPES . '_fk_edited_by FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
//    CONSTRAINT ' . TABLE_PHENOTYPES . '_fk_deleted_by FOREIGN KEY (deleted_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE)
//    ' . $sSettings

         , 'TABLE_SCREENINGS' =>
   'CREATE TABLE ' . TABLE_SCREENINGS . ' (
    id INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    individualid MEDIUMINT(8) UNSIGNED ZEROFILL NOT NULL,
    variants_found BOOLEAN NOT NULL DEFAULT 1,
    owned_by SMALLINT(5) UNSIGNED ZEROFILL,
    created_by SMALLINT(5) UNSIGNED ZEROFILL,
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    edited_by SMALLINT(5) UNSIGNED ZEROFILL,
    edited_date DATETIME,
    PRIMARY KEY (id),
    INDEX (individualid),
    INDEX (owned_by),
    INDEX (created_by),
    INDEX (edited_by),
    CONSTRAINT ' . TABLE_SCREENINGS . '_fk_individualid FOREIGN KEY (individualid) REFERENCES ' . TABLE_INDIVIDUALS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_SCREENINGS . '_fk_owned_by FOREIGN KEY (owned_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_SCREENINGS . '_fk_created_by FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_SCREENINGS . '_fk_edited_by FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE)
    ' . $sSettings

//         , 'TABLE_SCREENINGS_REV' =>
//   'CREATE TABLE ' . TABLE_SCREENINGS_REV . ' (
//    id INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
//    individualid MEDIUMINT(8) UNSIGNED ZEROFILL NOT NULL,
//    owned_by SMALLINT(5) UNSIGNED ZEROFILL,
//    edited_by SMALLINT(5) UNSIGNED ZEROFILL,
//    valid_from DATETIME NOT NULL,
//    valid_to DATETIME NOT NULL DEFAULT "9999-12-31",
//    deleted BOOLEAN NOT NULL,
//    deleted_by SMALLINT(5) UNSIGNED ZEROFILL,
//    PRIMARY KEY (id, valid_from),
//    INDEX (valid_to),
//    INDEX (individualid),
//    INDEX (owned_by),
//    INDEX (edited_by),
//    INDEX (deleted_by),
//    CONSTRAINT ' . TABLE_SCREENINGS . '_fk_individualid FOREIGN KEY (individualid) REFERENCES ' . TABLE_INDIVIDUALS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE,
//    CONSTRAINT ' . TABLE_SCREENINGS . '_fk_owned_by FOREIGN KEY (owned_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
//    CONSTRAINT ' . TABLE_SCREENINGS . '_fk_edited_by FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
//    CONSTRAINT ' . TABLE_SCREENINGS . '_fk_deleted_by FOREIGN KEY (deleted_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE)
//    ' . $sSettings

         , 'TABLE_SCR2GENE' =>
   'CREATE TABLE ' . TABLE_SCR2GENE . ' (
    screeningid INT(10) UNSIGNED ZEROFILL NOT NULL,
    geneid VARCHAR(25) NOT NULL,
    PRIMARY KEY (screeningid, geneid),
    INDEX (screeningid),
    INDEX (geneid),
    CONSTRAINT ' . TABLE_SCR2GENE . '_fk_screeningid FOREIGN KEY (screeningid) REFERENCES ' . TABLE_SCREENINGS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_SCR2GENE . '_fk_geneid FOREIGN KEY (geneid) REFERENCES ' . TABLE_GENES . ' (id) ON DELETE CASCADE ON UPDATE CASCADE)
    ' . $sSettings

         , 'TABLE_SCR2VAR' =>
   'CREATE TABLE ' . TABLE_SCR2VAR . ' (
    screeningid INT(10) UNSIGNED ZEROFILL NOT NULL,
    variantid INT(10) UNSIGNED ZEROFILL NOT NULL,
    PRIMARY KEY (screeningid, variantid),
    INDEX (screeningid),
    INDEX (variantid),
    CONSTRAINT ' . TABLE_SCR2VAR . '_fk_screeningid FOREIGN KEY (screeningid) REFERENCES ' . TABLE_SCREENINGS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_SCR2VAR . '_fk_variantid FOREIGN KEY (variantid) REFERENCES ' . TABLE_VARIANTS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE)
    ' . $sSettings

         , 'TABLE_COLS' =>
   'CREATE TABLE ' . TABLE_COLS . ' (
    id VARCHAR(100) NOT NULL,
    col_order TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
    width SMALLINT(5) UNSIGNED NOT NULL DEFAULT 50,
    hgvs BOOLEAN NOT NULL DEFAULT 0,
    standard BOOLEAN NOT NULL DEFAULT 0,
    mandatory BOOLEAN NOT NULL DEFAULT 0,
    head_column VARCHAR(50) NOT NULL DEFAULT \'\',
    description_form TEXT,
    description_legend_short TEXT,
    description_legend_full TEXT,
    mysql_type VARCHAR(255) NOT NULL DEFAULT \'TEXT\',
    form_type TEXT,
    select_options TEXT,
    preg_pattern VARCHAR(255) NOT NULL DEFAULT \'\',
    public_view BOOLEAN NOT NULL DEFAULT 0,
    public_add BOOLEAN NOT NULL DEFAULT 0,
    allow_count_all BOOLEAN NOT NULL DEFAULT 0,
    created_by SMALLINT(5) UNSIGNED ZEROFILL,
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    edited_by SMALLINT(5) UNSIGNED ZEROFILL,
    edited_date DATETIME,
    PRIMARY KEY (id),
    INDEX (created_by),
    INDEX (edited_by),
    CONSTRAINT ' . TABLE_COLS . '_fk_created_by FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_COLS . '_fk_edited_by FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE)
    ' . $sSettings

         , 'TABLE_ACTIVE_COLS' =>
   'CREATE TABLE ' . TABLE_ACTIVE_COLS . ' (
    colid VARCHAR(100) NOT NULL,
    created_by SMALLINT(5) UNSIGNED ZEROFILL,
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (colid),
    INDEX (created_by),
    CONSTRAINT ' . TABLE_ACTIVE_COLS . '_fk_colid FOREIGN KEY (colid) REFERENCES ' . TABLE_COLS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_ACTIVE_COLS . '_fk_created_by FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE)
    ' . $sSettings

         , 'TABLE_SHARED_COLS' =>
   'CREATE TABLE ' . TABLE_SHARED_COLS . ' (
    geneid VARCHAR(25),
    diseaseid SMALLINT(5) UNSIGNED ZEROFILL,
    colid VARCHAR(100) NOT NULL,
    col_order TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
    width SMALLINT(5) UNSIGNED NOT NULL DEFAULT 50,
    mandatory BOOLEAN NOT NULL DEFAULT 0,
    description_form TEXT,
    description_legend_short TEXT,
    description_legend_full TEXT,
    select_options TEXT,
    public_view BOOLEAN NOT NULL DEFAULT 0,
    public_add BOOLEAN NOT NULL DEFAULT 0,
    created_by SMALLINT(5) UNSIGNED ZEROFILL,
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    edited_by SMALLINT(5) UNSIGNED ZEROFILL,
    edited_date DATETIME,
    UNIQUE (geneid, colid),
    UNIQUE (diseaseid, colid),
    INDEX (colid),
    INDEX (created_by),
    INDEX (edited_by),
    CONSTRAINT ' . TABLE_SHARED_COLS . '_fk_geneid FOREIGN KEY (geneid) REFERENCES ' . TABLE_GENES . ' (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_SHARED_COLS . '_fk_diseaseid FOREIGN KEY (diseaseid) REFERENCES ' . TABLE_DISEASES . ' (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_SHARED_COLS . '_fk_colid FOREIGN KEY (colid) REFERENCES ' . TABLE_ACTIVE_COLS . ' (colid) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_SHARED_COLS . '_fk_created_by FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_SHARED_COLS . '_fk_edited_by FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE)
    ' . $sSettings

         , 'TABLE_LINKS' =>
   'CREATE TABLE ' . TABLE_LINKS . ' (
    id TINYINT(3) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    pattern_text VARCHAR(25) NOT NULL,
    replace_text TEXT NOT NULL,
    description TEXT,
    created_by SMALLINT(5) UNSIGNED ZEROFILL,
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    edited_by SMALLINT(5) UNSIGNED ZEROFILL,
    edited_date DATETIME,
    PRIMARY KEY (id),
    UNIQUE (name),
    UNIQUE (pattern_text),
    INDEX (created_by),
    INDEX (edited_by),
    CONSTRAINT ' . TABLE_LINKS . '_fk_created_by FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_LINKS . '_fk_edited_by FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE)
    ' . $sSettings

         , 'TABLE_COLS2LINKS' =>
   'CREATE TABLE ' . TABLE_COLS2LINKS . ' (
    colid VARCHAR(100) NOT NULL,
    linkid TINYINT(3) UNSIGNED ZEROFILL NOT NULL,
    PRIMARY KEY (colid, linkid),
    INDEX (linkid),
    CONSTRAINT ' . TABLE_COLS2LINKS . '_fk_colid FOREIGN KEY (colid) REFERENCES ' . TABLE_COLS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_COLS2LINKS . '_fk_linkid FOREIGN KEY (linkid) REFERENCES ' . TABLE_LINKS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE)
    ' . $sSettings

         , 'TABLE_CONFIG' =>
   'CREATE TABLE ' . TABLE_CONFIG . ' (
    system_title VARCHAR(255) NOT NULL DEFAULT \'\',
    institute VARCHAR(255) NOT NULL DEFAULT \'\',
    location_url VARCHAR(255) NOT NULL DEFAULT \'\',
    email_address VARCHAR(75) NOT NULL DEFAULT \'\',
    send_admin_submissions BOOLEAN NOT NULL DEFAULT 0,
    api_feed_history TINYINT(2) UNSIGNED NOT NULL DEFAULT 0,
    refseq_build VARCHAR(4) NOT NULL DEFAULT \'hg38\',
    proxy_host VARCHAR(255) NOT NULL DEFAULT \'\',
    proxy_port SMALLINT(5) UNSIGNED,
    proxy_username VARCHAR(255) NOT NULL DEFAULT \'\',
    proxy_password VARCHAR(255) NOT NULL DEFAULT \'\',
    logo_uri VARCHAR(100) NOT NULL DEFAULT "gfx/' . (LOVD_plus? 'LOVD_plus_logo200x50' : 'LOVD3_logo145x50') . '.jpg",
    mutalyzer_soap_url VARCHAR(100) NOT NULL DEFAULT "https://mutalyzer.nl/services",
    omim_apikey VARCHAR(40) NOT NULL DEFAULT \'\',
    send_stats BOOLEAN NOT NULL DEFAULT 0,
    include_in_listing BOOLEAN NOT NULL DEFAULT 0,
    allow_submitter_registration BOOLEAN NOT NULL DEFAULT ' . (int) (!LOVD_plus) . ',
    lock_users BOOLEAN NOT NULL DEFAULT 0,
    allow_unlock_accounts BOOLEAN NOT NULL DEFAULT 0,
    allow_submitter_mods BOOLEAN NOT NULL DEFAULT 0,
    allow_count_hidden_entries BOOLEAN NOT NULL DEFAULT 0,
    use_ssl BOOLEAN NOT NULL DEFAULT 0,
    use_versioning BOOLEAN NOT NULL DEFAULT 0,
    lock_uninstall BOOLEAN NOT NULL DEFAULT 0)
    ' . $sSettings

         , 'TABLE_STATUS' =>
   'CREATE TABLE ' . TABLE_STATUS . ' (
    lock_update BOOLEAN NOT NULL DEFAULT 0,
    version VARCHAR(15) NOT NULL DEFAULT \'' .  $_SETT['system']['version'] . '\',
    signature CHAR(32) NOT NULL DEFAULT \'\',
    update_checked_date DATETIME,
    update_version VARCHAR(15),
    update_level TINYINT(1) UNSIGNED,
    update_description TEXT,
    update_released_date DATE,
    installed_date DATE NOT NULL DEFAULT \'1000-01-01\',
    updated_date DATE)
    ' . $sSettings

         , 'TABLE_ANNOUNCEMENTS' =>
   'CREATE TABLE ' . TABLE_ANNOUNCEMENTS . ' (
    id SMALLINT(5) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    type VARCHAR(15) NOT NULL DEFAULT "information",
    announcement TEXT NOT NULL,
    start_date DATETIME NOT NULL DEFAULT \'1000-01-01 00:00:00\',
    end_date DATETIME NOT NULL DEFAULT \'9999-12-31 23:59:59\',
    lovd_read_only BOOLEAN NOT NULL DEFAULT 0,
    created_by SMALLINT(5) UNSIGNED ZEROFILL,
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    edited_by SMALLINT(5) UNSIGNED ZEROFILL,
    edited_date DATETIME,
    PRIMARY KEY (id),
    INDEX (created_by),
    INDEX (edited_by),
    CONSTRAINT ' . TABLE_ANNOUNCEMENTS . '_fk_created_by FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ' . TABLE_ANNOUNCEMENTS . '_fk_edited_by FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL ON UPDATE CASCADE)
    ' . $sSettings

         , 'TABLE_SOURCES' =>
   'CREATE TABLE ' . TABLE_SOURCES . ' (
    id VARCHAR(15) NOT NULL,
    url VARCHAR(255) NOT NULL,
    PRIMARY KEY (id))
    ' . $sSettings

         , 'TABLE_LOGS' =>
   'CREATE TABLE ' . TABLE_LOGS . ' (
    name VARCHAR(10) NOT NULL,
    date DATETIME NOT NULL,
    mtime MEDIUMINT(6) UNSIGNED ZEROFILL NOT NULL,
    userid SMALLINT(5) UNSIGNED ZEROFILL,
    event VARCHAR(20) NOT NULL,
    log TEXT NOT NULL,
    PRIMARY KEY (name, date, mtime),
    INDEX (userid),
    CONSTRAINT ' . TABLE_LOGS . '_fk_userid FOREIGN KEY (userid) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE CASCADE ON UPDATE CASCADE)
    ' . $sSettings

         , 'TABLE_MODULES' =>
   'CREATE TABLE ' . TABLE_MODULES . ' (
    id VARCHAR(15) NOT NULL,
    name VARCHAR(50) NOT NULL,
    version VARCHAR(15) NOT NULL DEFAULT \'\',
    description VARCHAR(255) NOT NULL DEFAULT \'\',
    active BOOLEAN NOT NULL DEFAULT 0,
    settings TEXT,
    installed_date DATE NOT NULL DEFAULT \'1000-01-01\',
    updated_date DATE,
    PRIMARY KEY (id))
    ' . $sSettings
          );

if (lovd_getProjectFile() == '/install/inc-sql-tables.php') {
    header('Content-type: text/plain; charset=UTF-8');
    var_dump($aTableSQL);
}
?>
