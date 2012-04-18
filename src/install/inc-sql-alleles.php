<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-04-13
 * Modified    : 2012-04-13
 * For LOVD    : 3.0-beta-04
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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

$aAlleleSQL =
         array(
                'INSERT INTO ' . TABLE_ALLELES . ' VALUES(0,  "Unknown", 1),
                                                         (11, "Paternal (confirmed)", 2),
                                                         (10, "Paternal (inferred)", 3),
                                                         (21, "Maternal (confirmed)", 4),
                                                         (20, "Maternal (inferred)", 5),
                                                         (1,  "Parent #1", 6),
                                                         (2,  "Parent #2", 7),
                                                         (3,  "Both (homozygous)", 8)',
              );
?>
