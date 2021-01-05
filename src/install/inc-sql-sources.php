<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-23
 * Modified    : 2021-01-05
 * For LOVD    : 3.0-26
 *
 * Copyright   : 2004-2021 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               M. Kroon <m.kroon@lumc.nl>
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

// List of external biological sources.
$aSourceSQL =
         array(
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("entrez",       "https://www.ncbi.nlm.nih.gov/gene?cmd=Retrieve&dopt=full_report&list_uids={{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("genbank",      "https://www.ncbi.nlm.nih.gov/nuccore/{{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("genecards",    "http://www.genecards.org/cgi-bin/carddisp.pl?gene={{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("genetests",    "https://www.ncbi.nlm.nih.gov/gtr/genes/{{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("hgmd",         "http://www.hgmd.cf.ac.uk/ac/gene.php?gene={{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("hgnc",         "https://www.genenames.org/data/gene-symbol-report/#!/hgnc_id/HGNC:{{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("hpo_disease",  "http://compbio.charite.de/hpoweb/showterm?disease=OMIM:{{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("omim",         "http://www.omim.org/entry/{{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("orphanet",     "https://www.orpha.net/consor/cgi-bin/OC_Exp.php?lng=en&Gene={{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("pubmed_gene",  "https://www.ncbi.nlm.nih.gov/pubmed?LinkName=gene_pubmed&from_uid={{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("pubmed_article", "https://www.ncbi.nlm.nih.gov/pubmed/{{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("uniprot",      "http://www.uniprot.org/uniprot/{{ ID }}")',
              );
?>
