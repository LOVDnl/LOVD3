<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-22
 * Modified    : 2010-04-19
 * For LOVD    : 3.0-pre-06
 *
 * Copyright   : 2004-2010 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 * Last edited : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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
                'PubMed' => 'INSERT INTO ' . TABLE_LINKS . ' VALUES (001, "PubMed", "{PMID:[1]:[2]}", "<A href=\"http://www.ncbi.nlm.nih.gov/pubmed/[2]\" target=\"_blank\">[1]</A>", "Links to abstracts in the PubMed database.\r\n[1] = The name of the author(s).\r\n[2] = The PubMed ID.", 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS2LINKS . ' VALUES ("Patient/Reference", 001)',
// FIXME; foreign key constraint fails here.
//                'INSERT INTO ' . TABLE_COLS2LINKS . ' VALUES ("Gene/Reference", 001)',
                'DbSNP' => 'INSERT INTO ' . TABLE_LINKS . ' VALUES (002, "DbSNP", "{dbSNP:[1]}", "<A href=\"http://www.ncbi.nlm.nih.gov/SNP/snp_ref.cgi?type=rs&amp;rs=rs[1]\" target=\"_blank\">dbSNP</A>", "Links to the DbSNP database.\r\n[1] = The DbSNP ID.", 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS2LINKS . ' VALUES ("VariantOnGenome/Reference", 002)',
                'GenBank' => 'INSERT INTO ' . TABLE_LINKS . ' VALUES (003, "GenBank", "{GenBank:[1]}", "<A href=\"http://www.ncbi.nlm.nih.gov/entrez/viewer.fcgi?cmd=Retrieve&amp;db=nucleotide&amp;dopt=GenBank&amp;list_uids=[1]\" target=\"_blank\">GenBank</A>", "Links to GenBank sequences.\r\n[1] = The GenBank ID.", 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS2LINKS . ' VALUES ("VariantOnGenome/Reference", 003)',
                'OMIM' => 'INSERT INTO ' . TABLE_LINKS . ' VALUES (004, "OMIM", "{OMIM:[1]:[2]}", "<A href=\"http://www.ncbi.nlm.nih.gov/entrez/dispomim.cgi?id=[1]&amp;a=[1]_AllelicVariant[2]\" target=\"_blank\">(OMIM [2])</A>", "Links to an allelic variant on the gene\'s OMIM page.\r\n[1] = The OMIM gene ID.\r\n[2] = The number of the OMIM allelic variant on that page.", 1, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_COLS2LINKS . ' VALUES ("VariantOnGenome/Reference", 004)',
              );
?>