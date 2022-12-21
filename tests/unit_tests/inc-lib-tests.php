<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-02-10
 * Modified    : 2022-12-21
 * For LOVD    : 3.0-29
 *
 * Copyright   : 2004-2022 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

// In PHP8, you need to use ini_set instead of assert_options().
if (ini_get('zend.assertions') == '0') {
    ini_set('zend.assertions', 1);
}
if (ini_get('zend.assertions') != '1') {
    die("Assertions are turned off; please enable them, so we can test.\n");
}
ini_set('assert.warning', 0);
ini_set('assert.bail', 1);
ini_set('assert.callback', 'lovd_assertFailed');

function lovd_assertFailed ($sFile, $nLine, $sCode, $sDescription = '')
{
    print('Assertion Failed!' . "\n" .
          '  File: ' . $sFile . "\n" .
          '  Line: ' . $nLine . "\n" .
          '  Code: ' . $sCode . "\n" .
          '  Description: ' . $sDescription . "\n\n");
}
?>
