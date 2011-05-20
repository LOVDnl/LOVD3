<?php
// DMD_SPECIFIC; haven't looked at the data much... lots of stuff still needs to be changed.
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-22
 * Modified    : 2011-05-10
 * For LOVD    : 3.0-pre-21
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

// DMD_SPECIFIC; FIXME; We need a Gene/Reference entry here, because of the foreign key on the TABLE_COLS2LINKS and we want PubMed active on Gene/Reference.
// Didn't we fix that some other way?

// DMD_SPECIFIC
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', '../');
    require ROOT_PATH . 'inc-init.php';
}

$aColSQL =
         array(
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("Individual/Lab_ID",                    0,  80, 1, 1, 1, "Lab\'s ID",            "", "The ID given to this individual by its reference.", "The ID given to this individual by its reference, such as a hospital, diagnostic laboratory or a paper.", "VARCHAR(15)", "Lab ID||text|10", "", "", 0, 1, 0, 1, NOW(), NULL, NULL)',
                // There no longer is a submitted: link.
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("Individual/Reference",                 0, 200, 1, 1, 0, "Reference",            "", "Reference describing the individual, &quot;Submitted:&quot; indicating that the mutation was submitted directly to this database.", "Literature reference with possible link to publication in PubMed or other online resource. &quot;Submitted:&quot; indicates that the mutation was submitted directly to this database by the laboratory indicated.", "VARCHAR(200)", "Reference||text|50", "", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("Individual/Remarks",                   0, 200, 0, 1, 0, "Remarks",              "", "", "", "TEXT", "Remarks||textarea|50|3", "", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("Individual/Remarks_Non_Public",        0, 200, 0, 1, 0, "Remarks (non public)", "", "", "", "TEXT", "Remarks (non public)||textarea|50|3", "", "", 0, 0, 0, 1, NOW(), NULL, NULL)',
                // What to do with this?
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("Individual/Times_Reported",            0,  80, 0, 1, 1, "# Reported",           "", "Number of times this case has been reported", "Number of times this case has been reported", "SMALLINT(4) UNSIGNED DEFAULT 1", "Times reported||text|3", "", "", 1, 0, 1, 1, NOW(), NULL, NULL)',
/*
DMD_SPECIFIC
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("",                                  0, 200, 0|1, 0|1, 0|1, "",               "", "", "", "()", "||", "", "//", 0|1, 0|1, 0|1, 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("",                                  0:order, width, 0|1:hgvs, 0|1:standard, 0|1:mandatory, "head", "comments", "short", "long", "()", "||", "select_options", "/preg/", 0|1:public, 0|1:public_form, 0|1:allow_count_all, 1, NOW(), NULL, NULL)',
*/
                // What to do with this?
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("Individual/Occurrence",                0, 200, 0, 0, 0, "Occurrence",           "", "Occurrence", "Occurrence", "VARCHAR(8)", "Occurrence||select|1|Unknown|false|false", "Familial\r\nSporadic", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("Individual/Gender",                    0,  70, 0, 0, 0, "Gender",               "", "Individual gender", "Individual gender", "VARCHAR(7)", "Gender||select|1|--Not specified--|false|false", "Female\r\nMale\r\nUnknown", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                // What to do with this? (2)
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("Individual/Mutation/Origin",           0, 200, 0, 0, 0, "Mut. origin",          "", "Origin of mutation", "Origin of mutation", "VARCHAR(9)", "Origin of mutation||select|1|Unknown|false|false", "De novo\r\nInherited", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("Individual/Mutation/Origin_De_Novo",   0, 200, 0, 0, 0, "De novo origin",       "", "If de novo, origin of mutation", "If de novo, origin of mutation", "VARCHAR(11)", "If de novo, origin of mutation||select|1|true|false|false", "Individual\r\nFather\r\nMother\r\nGrandfather\r\nGrandmother", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("Individual/Origin/Geographic",         0, 200, 0, 0, 0, "Geographic origin",    "", "Geographic origin of individual", "Geographic origin of the individual", "VARCHAR(50)", "Geographic origin||text|30", "", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("Individual/Origin/Ethnic",             0, 200, 0, 0, 0, "Ethnic origin",        "", "Ethnic origin of individual", "Ethnic origin of the individual", "VARCHAR(50)", "Ethnic origin||text|20", "", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("Individual/Origin/Population",         0, 200, 0, 0, 0, "Population",           "", "Individual population", "Additional information on individual population", "VARCHAR(50)", "Individual population||text|30", "", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("Screening/Template",                   0,  80, 1, 1, 1, "Template",             "", "Screening performed on DNA, RNA and/or Protein level.", "Screening performed on DNA, RNA and/or Protein level.", "VARCHAR(20)", "Detection template||select|3|false|true|false", "DNA\r\nRNA\r\nProtein", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("Screening/Technique",                  0, 200, 1, 1, 1, "Technique",            "", "Technique used to detect variants.", "Technique used to reveal the variants reported.", "VARCHAR(150)", "Technique(s) used||select|5|false|true|false", "BESS = Base Excision Sequence Scanning\r\nCMC = Chemical Mismatch Cleavage\r\nDGGE = Denaturing-Gradient Gel-Electrophoresis\r\nDHPLC = Denaturing High-Performance Liquid Chromatography\r\nDOVAM = Detection Of Virtually All Mutations (SSCA variant)\r\nDSCA = Double-Strand DNA Conformation Analysis\r\nHD = HeteroDuplex analysis\r\nIHC = Immuno-Histo-Chemistry\r\nmPCR = multiplex PCR\r\nMAPH = Multiplex Amplifiable Probe Hybridisation\r\nMLPA = Multiplex Ligation-dependent Probe Amplification\r\nNGS = Next Generation Sequencing\r\nPAGE = Poly-Acrylamide Gel-Electrophoresis\r\nPCR = Polymerase Chain Reaction\r\nPTT = Protein Truncation Test\r\nRT-PCR = Reverse Transcription and PCR\r\nSEQ = SEQuencing\r\nSouthern = Southern Blotting\r\nSSCA = Single-Strand DNA Conformation Analysis (SSCP)\r\nWestern = Western Blotting", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("Screening/Tissue",                     0, 100, 0, 0, 1, "Tissue",               "", "Tissue type used for the detection of sequence variants.", "Tissue type used for the detection of sequence variants.", "VARCHAR(25)", "Tissue||text|20", "", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("Screening/Date",                       0,  80, 0, 0, 0, "Date",                 "Format: YYYY-MM-DD.", "Date the detection technique was performed.", "Date the detection technique was performed, in YYYY-MM-DD format.", "DATE", "Date||text|10", "", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("VariantOnGenome/Type",                 0, 200, 0, 0, 1, "Type",                 "", "Type of variant at DNA level.", "Type of variant at DNA level.", "VARCHAR(20)", "Type of variant (DNA level)||select|1|false|false|false", "Substitution\r\nDeletion\r\nDuplication\r\nInsertion\r\nInversion\r\nInsertion/Deletion\r\nTranslocation\r\nOther/Complex", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                // From here on, check all columns thouroughly.
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("VariantOnGenome/DNA",                  2, 200, 1, 1, 1, "DNA change",           "", "Variation at DNA-level. If present, \"Full Details\" will show you the the full-length entry.", "Variation at DNA level.", "VARCHAR(100)", "DNA change (HGVS format)||text|30", "", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("VariantOnGenome/DNA_published",        0, 200, 0, 0, 0, "DNA published",        "What the variant was reported as (e.g. 521delT); listed only when different from \"DNA change\"", "What the variant was reported as.", "What the variant was reported as (e.g. 521delT); listed only when different from \"DNA change\".", "VARCHAR(100)", "DNA published||text|30", "", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("VariantOnGenome/Reference",            0, 200, 1, 1, 0, "Reference",            "", "Reference describing the variant.", "Literature reference with possible link to publication in PubMed, dbSNP entry or other online resource.", "VARCHAR(200)", "Reference||text|50", "", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("VariantOnGenome/Restriction_site",     5,  75, 0, 1, 0, "Re-site",              "", "Variant creates (+) or destroys (-) a restriction enzyme recognition site.", "Variant creates (+) or destroys (-) a restriction enzyme recognition site.", "VARCHAR(15)", "Re-site||text|10", "", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("VariantOnGenome/DBID",                 6, 200, 1, 1, 1, "DB-ID",                "The ID-field should start with the ID; the gene symbol followed by an underscore (_) and the ID code, usually five digits.", "Database IDentifier; When available, links to OMIM ID\'s are provided.", "Database IDentifier; When available, links to OMIM ID\'s are provided.", "VARCHAR(100)", "ID||text|40", "", "/^[A-Z][A-Z0-9]+_([0-9]{5}([a-z]{2})?|(SO|MP|e)[0-9]{1,2}((SO|MP|e)[0-9]{1,2})?b?)\\\\b/", 1, 0, 1, 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("VariantOnGenome/Frequency",            7,  90, 0, 1, 0, "Frequency",            "", "Frequency if variant is non pathogenic.", "Frequency of non pathogenic variant reported listed as number of variant alleles/number of control alleles tested, like 5/132.", "VARCHAR(15)", "Frequency||text|10", "", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                // Add remarks non public? Add remarks columns to VariantOnTranscript???
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("VariantOnGenome/Remarks",              8, 200, 0, 0, 0, "Variant remarks",      "", "Variant remarks", "Variant remarks", "TEXT", "Remarks||textarea|50|3", "", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("VariantOnTranscript/Location",         0, 200, 0, 0, 1, "Location",             "", "Variant location at DNA level.", "Variant location at DNA level.", "VARCHAR(16)", "Location||select|1|true|false|false", "5\' Gene flanking\r\n5\' UTR\r\nExon\r\nIntron\r\n3\' UTR\r\n3\' Gene flanking", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("VariantOnTranscript/Exon",             1,  50, 0, 1, 1, "Exon",                 "", "Exon numbering.", "Exon numbering.", "VARCHAR(5)", "Exon||text|3", "", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("VariantOnTranscript/DNA",              2, 200, 1, 1, 1, "DNA change",           "", "Variation at DNA-level. If present, \"Full Details\" will show you the the full-length entry.", "Variation at DNA level.", "VARCHAR(100)", "DNA change (HGVS format)||text|30", "", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("VariantOnTranscript/DNA_published",    0, 200, 0, 0, 0, "DNA published",        "What the variant was reported as (e.g. 521delT); listed only when different from \"DNA change\"", "What the variant was reported as.", "What the variant was reported as (e.g. 521delT); listed only when different from \"DNA change\".", "VARCHAR(100)", "DNA published||text|30", "", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("VariantOnTranscript/RNA",              3, 200, 1, 1, 0, "RNA change",           "", "Variation at RNA-level, (?) unknown but probably identical to DNA.", "Effect of change on RNA.<BR>\r\n<UL style=\"margin-top : 0px;\">\r\n  <LI>= = RNA change identical to DNA change</LI>\r\n  <LI>? = unknown</LI>\r\n  <LI>(=) = no significant effect expected (but no experimental proof)</LI>\r\n  <LI>(0) = change expected to abolish transcription</LI>\r\n  <LI>(ex4ex5del) = probably deletion of exons 4 to 5</LI>\r\n  <LI>(ex4ex5dup) = probably duplication of exons 4 to 5</LI>\r\n <LI>+cry = activation of cryptic splice site (no sequence published)</LI>\r\n  <LI>spl? = effect on splicing very likely (no experimental proof), examples;</LI>\r\n  <UL>\r\n    <LI>splice donor site change (nucleotides +1 to +5 affected)</LI>\r\n    <LI>splice acceptor site change (nucleotides -2 to -1 affected)</LI>\r\n    <LI>new intronic AG splice acceptor di-nucleotide created close to (within 15 nucleotides) of normal splice acceptor site</LI>\r\n  </UL>\r\n  <LI>(spl?) = might affect splicing (no experimental proof), examples;</LI>\r\n  <UL>\r\n    <LI>change affects first or last nucleotide of exon</LI>\r\n    <LI>change creates strong splice donor or splice acceptor site in exon</LI>\r\n  </UL>\r\n</UL>", "VARCHAR(100)", "RNA change (HGVS format)||text|30", "", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("VariantOnTranscript/Protein",          4, 200, 1, 1, 0, "Protein",              "", "Variation at protein level.", "Predicted effect of change on protein (usually without experimental proof!)<BR>\r\n<UL style=\"margin-top : 0px;\">\r\n  <LI>? = unknown</LI>\r\n  <LI>(0) = change expected to abolish translation</LI>\r\n  <LI>?fs = frame shift, but observed phenotype does not fit with prediction (for instance less severe phenotype (BMD) observed, more severe phenotype (DMD) expected)</LI>\r\n  <LI>?no fs = frame shift, but observed phenotype does not fit with prediction (for instance more severe phenotype (DMD) observed, less severe phenotype (BMD) expected)</LI>\r\n  <LI>del = causes deletion</LI>\r\n  <LI>fs = causes frame shift</LI>\r\n  <LI>fs? = effect on reading frame very likely (no experimental proof)</LI>\r\n  <LI>(fs?) = might affect the reading frame (no experimental proof)</LI>\r\n  <LI>no fs = does not cause frame shift</LI>\r\n  <LI>X = stop codon (nonsense)</LI>\r\n</UL>", "VARCHAR(100)", "Protein change (HGVS format)||text|30", "", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS . ' VALUES ("VariantOnTranscript/DBID",             6, 200, 1, 1, 1, "DB-ID",                "The ID-field should start with the ID; the gene symbol followed by an underscore (_) and the ID code, usually five digits.", "Database IDentifier; When available, links to OMIM ID\'s are provided.", "Database IDentifier; When available, links to OMIM ID\'s are provided.", "VARCHAR(100)", "ID||text|40", "", "/^[A-Z][A-Z0-9]+_([0-9]{5}([a-z]{2})?|(SO|MP|e)[0-9]{1,2}((SO|MP|e)[0-9]{1,2})?b?)\\\\b/", 1, 0, 1, 1, NOW(), NULL, NULL)',
              );

// DMD_SPECIFIC;
if (lovd_getProjectFile() == '/install/inc-sql-columns.php') {
    header('Content-type: text/plain; charset=UTF-8');
    var_dump($aColSQL);
}
?>
