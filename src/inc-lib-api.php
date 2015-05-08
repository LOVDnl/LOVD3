<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-11-09
 * Modified    : 2012-11-09
 * For LOVD    : 3.0-beta-10
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
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

function lovd_convertDNAPositionToDB ($nPositionMRNAStart, $nPositionMRNAEnd, $nPositionCDSEnd, $sPosition)
{
    // This function will convert human readable notations to how it is stored in the DataBase, such as:
    // 100      to  100,   0
    // -150     to -100, -50
    // -100-u50 to -100, -50
    // 550      to  500, +50
    // *100     to  500, +50
    // 500+d50  to  500, +50
    // *50+d50  to  500, +50
    // (Examples are valid when $nPositionMRNAStart = -100, $nPositionMRNAEnd = 500, $nPositionCDSEnd = 450)
    // Its antagonist is lovd_convertDNAPositionToHR().
    $aReturn = array(0, 0);

    if (preg_match('/^([*-]?[0-9]+)([+-][du]?([0-9]+))?$/', $sPosition, $aRegs)) {
        // $aRegs      1            2         3
        if (empty($aRegs[3])) {
            $aRegs[3] = 0;
        } else {
            $aRegs[3] = intval($aRegs[2]{0} . $aRegs[3]);
        }

        // Change c.*50 notation to c.500.
        if ($aRegs[1]{0} == '*') {
            $aRegs[1] = substr($aRegs[1], 1) + $nPositionCDSEnd;
        }

        // Fix c.-150 (before transcript) that should have been c.-100-u50.
        if ($aRegs[1] < $nPositionMRNAStart) {
            $aRegs[3] += ($aRegs[1] - $nPositionMRNAStart);
            $aRegs[1] = $nPositionMRNAStart;
        }

        // Fix c.550 (after transcript) that should have been c.500+d50 (or actually c.*50+d50).
        if ($aRegs[1] > $nPositionMRNAEnd) {
            $aRegs[3] += ($aRegs[1] - $nPositionMRNAEnd);
            $aRegs[1] = $nPositionMRNAEnd;
        }

        $aReturn = array($aRegs[1], $aRegs[3]);
    }

    return $aReturn;
}





function lovd_convertDNAPositionToHR ($nPositionMRNAStart, $nPositionMRNAEnd, $nPositionCDSEnd, $nPosition, $nPositionIntron = 0)
{
    // This function will convert notations how it is stored in the database to Human Readable format, such as:
    //  100,   0 to 100
    // -100, -50 to -100-u50
    //  500, +50 to *50+d50
    // (Examples are valid when $nPositionMRNAStart = -100, $nPositionMRNAEnd = 500, $nPositionCDSEnd = 450)
    // It's antagonist is lovd_convertDNAPositionToDB().

    $sPosition = '';
    if ($nPosition == $nPositionMRNAStart) {
        // Upstream position.
        $sPosition = $nPosition . ($nPositionIntron? '-u' . ($nPositionIntron * -1) : '');
    } elseif ($nPosition > $nPositionCDSEnd) {
        // Position after the stop codon.
        $sPosition = '*' . ($nPosition - $nPositionCDSEnd);
        if ($nPosition == $nPositionMRNAEnd && $nPositionIntron) {
            $sPosition .= '+d' . $nPositionIntron;
        }
    } else {
        $sPosition = $nPosition . ($nPositionIntron? ($nPositionIntron < 0? '' : '+') . $nPositionIntron : '');
    }

    return $sPosition;
}





function lovd_variantToPosition ($sVariant)
{
    // 2009-09-28; 2.0-22; Added function for API.
    // Calculates the variant's position based on the variant description.
    // Outputs c. positions with c. variants and g. positions with g.variants.

    // Remove first character(s) after c./g. which are: [(?
    $sPosition = preg_replace('/^(c\.|g\.)([[(?]*)/', "$1", $sVariant);
    $sPosition = preg_replace('/^((c\.|g\.)(\*|\-)?[0-9]+([-+][0-9?]+)?(_(\*|\-)?[0-9]+([-+][0-9?]+)?)?).*/', "$1", $sPosition);

    // Final check; does it conform to our output?
    if (!preg_match('/^(c\.|g\.)(\*|\-)?[0-9]+([-+][0-9?]+)?(_(\*|\-)?[0-9]+([-+][0-9?]+)?)?$/', $sPosition)) {
        $sPosition = '';
    }

    return $sPosition;
}
?>
