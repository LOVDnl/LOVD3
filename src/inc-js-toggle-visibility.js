/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-09-09
 * Modified    : 2010-09-09
 * For LOVD    : 3.0-pre-09
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

function lovd_toggleVisibility (objElement) {
    if (document.getElementById(objElement).style.display == 'none') {
        document.getElementById(objElement).style.display = '';
        if (document.getElementById(objElement + '_link')) {
            document.getElementById(objElement + '_link').innerHTML = 'Hide';
        }
    } else {
        document.getElementById(objElement).style.display = 'none';
        if (document.getElementById(objElement + '_link')) {
            document.getElementById(objElement + '_link').innerHTML = 'Show';
        }
    }
}
