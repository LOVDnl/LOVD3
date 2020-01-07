<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-01-30
 * Modified    : 2017-12-01
 * For LOVD    : 3.0-21
 *
 * Copyright   : 2004-2017 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

$aChromosomeSQL =
         array(
                'INSERT INTO ' . TABLE_CHROMOSOMES . ' VALUES("1",  1,  "NC_000001.9",  "NC_000001.10", "NC_006583.3"),
                                                             ("2",  2,  "NC_000002.10", "NC_000002.11", "NC_006584.3"),
                                                             ("3",  3,  "NC_000003.10", "NC_000003.11", "NC_006585.3"),
                                                             ("4",  4,  "NC_000004.10", "NC_000004.11", "NC_006586.3"),
                                                             ("5",  5,  "NC_000005.8",  "NC_000005.9",  "NC_006587.3"),
                                                             ("6",  6,  "NC_000006.10", "NC_000006.11", "NC_006588.3"),
                                                             ("7",  7,  "NC_000007.12", "NC_000007.13", "NC_006589.3"),
                                                             ("8",  8,  "NC_000008.9",  "NC_000008.10", "NC_006590.3"),
                                                             ("9",  9,  "NC_000009.10", "NC_000009.11", "NC_006591.3"),
                                                             ("10", 10, "NC_000010.9",  "NC_000010.10", "NC_006592.3"),
                                                             ("11", 11, "NC_000011.8",  "NC_000011.9",  "NC_006593.3"),
                                                             ("12", 12, "NC_000012.10", "NC_000012.11", "NC_006594.3"),
                                                             ("13", 13, "NC_000013.9",  "NC_000013.10", "NC_006595.3"),
                                                             ("14", 14, "NC_000014.7",  "NC_000014.8", "NC_006596.3"),
                                                             ("15", 15, "NC_000015.8",  "NC_000015.9", "NC_006597.3"),
                                                             ("16", 16, "NC_000016.8",  "NC_000016.9", "NC_006598.3"),
                                                             ("17", 17, "NC_000017.9",  "NC_000017.10", "NC_006599.3"),
                                                             ("18", 18, "NC_000018.8",  "NC_000018.9", "NC_006600.3"),
                                                             ("19", 19, "NC_000019.8",  "NC_000019.9", "NC_006601.3"),
                                                             ("20", 20, "NC_000020.9",  "NC_000020.10", "NC_006602.3"),
                                                             ("21", 21, "NC_000021.7",  "NC_000021.8", "NC_006603.3"),
                                                             ("22", 22, "NC_000022.9",  "NC_000022.10", "NC_006604.3"),
                                                             ("23", 23, "-", "-", "NC_006605.3"),
                                                             ("24", 24, "-", "-", "NC_006606.3"),
                                                             ("25", 25, "-", "-", "NC_006607.3"),
                                                             ("26", 26, "-", "-", "NC_006608.3"),
                                                             ("27", 27, "-", "-", "NC_006609.3"),
                                                             ("28", 28, "-", "-", "NC_006610.3"),
                                                             ("29", 29, "-", "-", "NC_006611.3"),
                                                             ("30", 30, "-", "-", "NC_006612.3"),
                                                             ("31", 31, "-", "-", "NC_006613.3"),
                                                             ("32", 32, "-", "-", "NC_006614.3"),
                                                             ("33", 33, "-", "-", "NC_006615.3"),
                                                             ("34", 34, "-", "-", "NC_006616.3"),
                                                             ("35", 35, "-", "-", "NC_006617.3"),
                                                             ("36", 36, "-", "-", "NC_006618.3"),
                                                             ("37", 37, "-", "-", "NC_006619.3"),
                                                             ("38", 38, "-", "-", "NC_006620.3"),
                                                             ("X",  39, "NC_000023.9",  "NC_000023.10", "NC_006621.3"),
                                                             ("Y",  40, "NC_000024.8",  "NC_000024.9", "-"),
                                                             ("M",  41, "NC_001807.4",  "NC_012920.1", "NC_002008.4")',
              );
?>
