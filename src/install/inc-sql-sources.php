<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-23
 * Modified    : 2024-04-16
 * For LOVD    : 3.0-30
 *
 * Copyright   : 2004-2024 Leiden University Medical Center; http://www.LUMC.nl/
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
// FIXME: This is difficult to maintain. Better define all these in $_SETT['external_sources'], like already done for dbSNP.
$aSourceSQL =
         array(
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("entrez",       "https://www.ncbi.nlm.nih.gov/gene?cmd=Retrieve&dopt=full_report&list_uids={{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("genbank",      "https://www.ncbi.nlm.nih.gov/nuccore/{{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("genecards",    "http://www.genecards.org/cgi-bin/carddisp.pl?gene={{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("genetests",    "https://www.ncbi.nlm.nih.gov/gtr/genes/{{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("hgmd",         "http://www.hgmd.cf.ac.uk/ac/gene.php?gene={{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("hgnc",         "https://www.genenames.org/data/gene-symbol-report/#!/hgnc_id/HGNC:{{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("hpo_disease",  "https://hpo.jax.org/app/browse/disease/OMIM:{{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("omim",         "http://www.omim.org/entry/{{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("orphanet",     "https://www.orpha.net/en/disease/gene/{{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("pubmed_gene",  "https://www.ncbi.nlm.nih.gov/pubmed?LinkName=gene_pubmed&from_uid={{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("pubmed_article", "https://www.ncbi.nlm.nih.gov/pubmed/{{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("uniprot",      "http://www.uniprot.org/uniprot/{{ ID }}")',
              );
?>
