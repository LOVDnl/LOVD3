<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-04-13
 * Modified    : 2019-08-28
 * For LOVD    : 3.0-22
 *
 * Copyright   : 2004-2019 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               M. Kroon <m.kroon@lumc.nl>
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

// List symbols denoting variant effect values as listed in $_SETT['var_effect'].
$aEffectSymbols = $_SETT['var_effect_short'];

// Create symbols for binary combinations of variant effect symbols (i.e. reported effect and concluded effect).
$aEffectNames = array();
foreach ($aEffectSymbols as $k1 => $v1) {
    foreach ($aEffectSymbols as $k2 => $v2) {
        $aEffectNames[(string) $k1 . (string) $k2] = $v1 . '/' . $v2;
    }
}

// Generate string of variant effect symbols to be used as part of SQL insert statement.
$sEffectValuesSQL = join(', ', array_map(
    function ($sID, $sName) {
        return '("' . $sID . '", "' . $sName . '")';
    },
    array_keys($aEffectNames), $aEffectNames)
);

$aVariantEffectSQL =
         array(
                'INSERT INTO ' . TABLE_EFFECT . ' VALUES ' . $sEffectValuesSQL,
              );
?>
