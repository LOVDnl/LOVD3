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

$aVariantEffectSQL =
         array(
                'INSERT INTO ' . TABLE_EFFECT . ' VALUES("11", "-/-"),
                                                        ("13", "-/-?"),
                                                        ("15", "-/?"),
                                                        ("17", "-/+?"),
                                                        ("19", "-/+"),
                                                        ("31", "-?/-"),
                                                        ("33", "-?/-?"),
                                                        ("35", "-?/?"),
                                                        ("37", "-?/+?"),
                                                        ("39", "-?/+"),
                                                        ("51", "?/-"),
                                                        ("53", "?/-?"),
                                                        ("55", "?/?"),
                                                        ("57", "?/+?"),
                                                        ("59", "?/+"),
                                                        ("71", "+?/-"),
                                                        ("73", "+?/-?"),
                                                        ("75", "+?/?"),
                                                        ("77", "+?/+?"),
                                                        ("79", "+?/+"),
                                                        ("91", "+/-"),
                                                        ("93", "+/-?"),
                                                        ("95", "+/?"),
                                                        ("97", "+/+?"),
                                                        ("99", "+/+")',
              );
?>
