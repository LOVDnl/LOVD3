<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-03-04
 * Modified    : 2017-11-16
 * For LOVD    : 3.0-21
 *
 * Copyright   : 2004-2017 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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

function lovd_describeFormType ($zData) {
    // Returns sensible form type information based on form type code.

    if (!is_array($zData) || empty($zData['form_type']) || substr_count($zData['form_type'], '|') < 2) {
        return false;
    }

    $aFormType = explode('|', $zData['form_type']);
    $sFormType = ucfirst($aFormType[2]);
    switch ($aFormType[2]) {
        case 'text':
        case 'password':
            $sFormType .= ' (' . $aFormType[3] . ' chars)';
            break;
        case 'textarea':
            $sFormType .= ' (' . $aFormType[3] . ' cols, ' . $aFormType[4] . ' rows)';
            break;
        case 'select':
            $nOptions = substr_count($zData['select_options'], "\r\n") + 1;
            if ($nOptions) {
                $sFormType .= ' (' . ($aFormType[5] == 'true'? 'multiple; ' : '') . $nOptions . ' option' . ($nOptions == 1? '' : 's') . ')';
            }
            break;
    }

    return $sFormType;
}
?>
