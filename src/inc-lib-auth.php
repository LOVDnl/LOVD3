<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-19
 * Modified    : 2016-05-11
 * For LOVD    : 3.0-16
 *
 * Copyright   : 2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : M. Kroon <m.kroon@lumc.nl>
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


Class LOVDColleagueType {

    // Colleagues that have edit permissions.
    const CAN_EDIT = 1;

    // Colleagues that have no edit permissions.
    const CANNOT_EDIT = 2;

    // All colleagues.
    const ALL = 3;
}


function lovd_getColleagues($nType=0) {
    global $_AUTH;

    $aOut = array();

    if (!isset($_AUTH) || !isset($_AUTH['colleagues_from'])) {
        return $aOut;
    }

    foreach ($_AUTH['colleagues_from'] as $sID => $sAllowEdit) {
        if (($nType & LOVDColleagueType::CAN_EDIT) && $sAllowEdit == '1') {
            $aOut[] = $sID;
        } elseif ($nType & LOVDColleagueType::CANNOT_EDIT) {
            $aOut[] = $sID;
        }
    }
    return $aOut;
}


