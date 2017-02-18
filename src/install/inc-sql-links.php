<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-22
 * Modified    : 2016-10-17
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
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

$aLinkSQL =
         array(
                'PubMed' => 'INSERT INTO ' . TABLE_LINKS . ' VALUES (001, "PubMed", "{PMID:[1]:[2]}", "<A href=\"http://www.ncbi.nlm.nih.gov/pubmed/[2]\" target=\"_blank\">[1]</A>", "Links to abstracts in the PubMed database.\r\n[1] = The name of the author(s), possibly followed by the year of publication.\r\n[2] = The PubMed ID.\r\n\r\nExample:\r\n{PMID:Fokkema et al. (2011):21520333}", 0, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS2LINKS . ' VALUES ("Individual/Reference", 001)',
                'INSERT INTO ' . TABLE_COLS2LINKS . ' VALUES ("VariantOnGenome/Reference", 001)',
                'DbSNP' => 'INSERT INTO ' . TABLE_LINKS . ' VALUES (002, "DbSNP", "{dbSNP:[1]}", "<A href=\"http://www.ncbi.nlm.nih.gov/SNP/snp_ref.cgi?rs=[1]\" target=\"_blank\">dbSNP</A>", "Links to the DbSNP database.\r\n[1] = The DbSNP ID.\r\n\r\nExamples:\r\n{dbSNP:rs193143796}\r\n{dbSNP:193143796}", 0, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS2LINKS . ' VALUES ("VariantOnGenome/Reference", 002)',
                'GenBank' => 'INSERT INTO ' . TABLE_LINKS . ' VALUES (003, "GenBank", "{GenBank:[1]}", "<A href=\"http://www.ncbi.nlm.nih.gov/entrez/viewer.fcgi?cmd=Retrieve&amp;db=nucleotide&amp;dopt=GenBank&amp;list_uids=[1]\" target=\"_blank\">GenBank</A>", "Links to GenBank sequences.\r\n[1] = The GenBank ID.\r\n\r\nExamples:\r\n{GenBank:NG_012232.1}\r\n{GenBank:NC_000001.10}", 0, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS2LINKS . ' VALUES ("VariantOnGenome/Reference", 003)',
                'OMIM' => 'INSERT INTO ' . TABLE_LINKS . ' VALUES (004, "OMIM", "{OMIM:[1]:[2]}", "<A href=\"http://www.omim.org/entry/[1]#[2]\" target=\"_blank\">(OMIM [2])</A>", "Links to an allelic variant on the gene\'s OMIM page.\r\n[1] = The OMIM gene ID.\r\n[2] = The number of the OMIM allelic variant on that page.\r\n\r\nExamples:\r\n{OMIM:300377:0021}\r\n{OMIM:188840:0003}", 0, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS2LINKS . ' VALUES ("VariantOnGenome/Reference", 004)',
                'DOI' => 'INSERT INTO ' . TABLE_LINKS . ' VALUES (005, "DOI", "{DOI:[1]:[2]}", "<A href=\"http://dx.doi.org/[2]\" target=\"_blank\">[1]</A>", "Links directly to an article using the DOI.\r\n[1] = The name of the author(s), possibly followed by the year of publication.\r\n[2] = The DOI.\r\n\r\nExample:\r\n{DOI:Fokkema et al. (2011):10.1002/humu.21438}", 0, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS2LINKS . ' VALUES ("Individual/Reference", 005)',
                'Alamut' => 'INSERT INTO ' . TABLE_LINKS . ' VALUES (006, "Alamut", "{Alamut:[1]:[2]}", "<A href=\"http://127.0.0.1:10000/show?request=[1]:[2]\" target=\"_blank\">Alamut</A>", "Links directly to the variant in the Alamut software.\r\n[1] = The chromosome letter or number.\r\n[2] = The genetic change on genome level.\r\n\r\nExample:\r\n{Alamut:16:21854780G>A}", 0, NOW(), NULL, NULL)',
         );

if (LOVD_plus) {
    // Extra links just for LOVD+.
    $aLinkSQL = array_merge($aLinkSQL,
        array(
                'INSERT INTO ' . TABLE_COLS2LINKS . ' VALUES ("VariantOnGenome/Alamut", 006)',
        ));
}
?>
