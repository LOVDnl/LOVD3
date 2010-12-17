<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-23
 * Modified    : 2010-12-17
 * For LOVD    : 3.0-pre-10
 *
 * Copyright   : 2004-2010 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

// List of external biological sources.
$aSourceSQL =
         array(
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("entrez",       "http://www.ncbi.nlm.nih.gov/entrez/query.fcgi?db=gene&cmd=Retrieve&dopt=full_report&list_uids={{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("genbank",      "http://www.ncbi.nlm.nih.gov/nuccore/{{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("genecards",    "http://www.genecards.org/cgi-bin/carddisp.pl?gene={{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("genetests",    "http://www.ncbi.nlm.nih.gov/sites/GeneTests/lab/gene/{{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("hgmd",         "http://www.hgmd.cf.ac.uk/ac/gene.php?gene={{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("omim",         "http://www.ncbi.nlm.nih.gov/omim/{{ ID }}")',
                'INSERT INTO ' . TABLE_SOURCES . ' VALUES ("uniprot",      "http://www.uniprot.org/uniprot/{{ ID }}")',
              );
?>
