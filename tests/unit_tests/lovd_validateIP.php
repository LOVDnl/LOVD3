<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2019-10-23 or earlier
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

define('FORMAT_ALLOW_TEXTPLAIN', true);
$_GET['format'] = 'text/plain';

require 'inc-lib-tests.php';

define('ROOT_PATH', realpath(__DIR__ . '/../../src') . '/');
require ROOT_PATH . 'inc-init.php';

error_reporting(E_ALL & ~E_DEPRECATED);

assert(lovd_validateIP('127.0.0.1', '127.0.0.1'), 'Full match');

assert(lovd_validateIP('127.0.0.*', '127.0.0.1'), 'Wildcard match 1');
assert(lovd_validateIP('127.0.*.1', '127.0.0.1'), 'Wildcard match 2');
assert(lovd_validateIP('127.*.0.1', '127.0.0.1'), 'Wildcard match 3');
assert(lovd_validateIP(  '*.0.0.1', '127.0.0.1'), 'Wildcard match 4');

assert(lovd_validateIP('127.0.0.1-100', '127.0.0.1'), 'Range match 1');
assert(lovd_validateIP('127.0.0-100.1', '127.0.0.1'), 'Range match 2');
assert(lovd_validateIP('127.0-100.0.1', '127.0.0.1'), 'Range match 3');
assert(lovd_validateIP('120-200.0.0.1', '127.0.0.1'), 'Range match 4');

assert(!lovd_validateIP('127.0.0.1', '127.0.0.2'), 'Negative full match');

assert(!lovd_validateIP('127.0.0.*', '127.0.1.0'), 'Negative wildcard match 1');
assert(!lovd_validateIP('127.0.*.1', '127.0.0.2'), 'Negative wildcard match 2');
assert(!lovd_validateIP('127.*.0.1', '127.0.1.1'), 'Negative wildcard match 3');
assert(!lovd_validateIP(  '*.0.0.1', '127.1.0.1'), 'Negative wildcard match 4');

assert(!lovd_validateIP('127.0.0.1-100', '127.0.0.0'), 'Negative range match 1');
assert(!lovd_validateIP('127.0.0-100.1', '127.0.0.2'), 'Negative range match 2');
assert(!lovd_validateIP('127.0-100.0.1', '127.0.1.1'), 'Negative range match 3');
assert(!lovd_validateIP('120-200.0.0.1', '100.0.0.1'), 'Negative range match 4');

die("Complete, all successful.\n");
?>
