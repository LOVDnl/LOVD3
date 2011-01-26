<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-01-25
 * Modified    : 2011-01-26
 * For LOVD    : 3.0-pre-15
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
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

// Don't allow direct access.
if (!defined('ROOT_PATH')) {
    exit;
}

function getLrgByGeneSymbol($sGeneSymbol)
{
    preg_match('/(LRG_\d+)\s+' . $sGeneSymbol . '/', implode(' ', lovd_php_file('http://www.lovd.nl/mirrors/lrg/LRG_list.txt')), $aMatches);
    if(!empty($aMatches)) {
        return $aMatches[1];
    }
    return false;
}

function getNgByGeneSymbol($sGeneSymbol)
{
    preg_match('/' . $sGeneSymbol . '\s+(NG_\d+\.\d+)/', implode(' ', lovd_php_file('http://www.lovd.nl/mirrors/ncbi/NG_list.txt')), $aMatches);
    if (!empty($aMatches)) {
        return $aMatches[1];
    }
    return false;
}
?>