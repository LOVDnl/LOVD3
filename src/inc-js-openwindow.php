<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-20
 * Modified    : 2010-07-01
 * For LOVD    : 3.0-pre-08
 *
 * Copyright   : 2004-2010 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 * Last edited : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

header('Content-type: text/javascript; charset=UTF-8');
?>
function lovd_openWindow (var_dest, var_name, var_width, var_height, varPosX, varPosY)
{
    // Load function to open up new windows.
    var_name = 'LOVD_<?php echo time(); ?>_' + var_name;
    if (!var_width) {
        var var_width = screen.width / 2;
    }
    if (!var_height) {
        var var_height = screen.height - 200;
    }
    if (!varPosX) {
        var varPosX = 50;
    }
    if (!varPosY) {
        var varPosY = 50;
    }
    window.open(var_dest, var_name, 'width=' + var_width + ',height=' + var_height + ',left=' + varPosX + ',top=' + varPosY + ',scrollbars=1');
}
