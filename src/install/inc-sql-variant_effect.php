<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-04-13
 * Modified    : 2014-06-16
 * For LOVD    : 3.0-11
 *
 * Copyright   : 2004-2014 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

$aVariantEffectSQL =
         array(
                'INSERT INTO ' . TABLE_EFFECT . ' VALUES("00", "./."),
                                                        ("01", "./-"),
                                                        ("03", "./-?"),
                                                        ("05", "./?"),
                                                        ("07", "./+?"),
                                                        ("09", "./+"),
                                                        ("10", "-/."),
                                                        ("11", "-/-"),
                                                        ("13", "-/-?"),
                                                        ("15", "-/?"),
                                                        ("17", "-/+?"),
                                                        ("19", "-/+"),
                                                        ("30", "-?/."),
                                                        ("31", "-?/-"),
                                                        ("33", "-?/-?"),
                                                        ("35", "-?/?"),
                                                        ("37", "-?/+?"),
                                                        ("39", "-?/+"),
                                                        ("50", "?/."),
                                                        ("51", "?/-"),
                                                        ("53", "?/-?"),
                                                        ("55", "?/?"),
                                                        ("57", "?/+?"),
                                                        ("59", "?/+"),
                                                        ("70", "+?/."),
                                                        ("71", "+?/-"),
                                                        ("73", "+?/-?"),
                                                        ("75", "+?/?"),
                                                        ("77", "+?/+?"),
                                                        ("79", "+?/+"),
                                                        ("90", "+/."),
                                                        ("91", "+/-"),
                                                        ("93", "+/-?"),
                                                        ("95", "+/?"),
                                                        ("97", "+/+?"),
                                                        ("99", "+/+")',
              );
?>
