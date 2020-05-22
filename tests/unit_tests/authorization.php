<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-07-25
 * Modified    : 2016-02-10
 * For LOVD    : 3.0-15
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
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

define('FORMAT_ALLOW_TEXTPLAIN', true);
$_GET['format'] = 'text/plain';

require 'inc-lib-tests.php';

define('ROOT_PATH', '../../src/');
require ROOT_PATH . 'inc-init.php';

// FIXME: This code depends on certain contents of the database.
//   This function needs to be rewritten to match the contents of the database
//   in the selenium tests. Then, then can be included in the selenium tests.

// Assertions for SUBMITTER
$_AUTH = $_DB->query('SELECT * FROM ' . TABLE_USERS . ' WHERE id = 5')->fetchAssoc();
$_AUTH['curates'] = array();
$_AUTH['collaborates'] = array();
assert('!empty($_AUTH)');
assert("lovd_isAuthorized('gene', 'IVD', false) === false");
assert("lovd_isAuthorized('disease', '1', false) === false");
assert("lovd_isAuthorized('transcript', '1', false) === false");
assert("lovd_isAuthorized('individual', '1', false) === false");
assert("lovd_isAuthorized('phenotype', '1', false) === false");
assert("lovd_isAuthorized('variant', '1', false) === false");
assert("lovd_isAuthorized('screening', '1', false) === false");

// Assertions for OWNER
$_AUTH = $_DB->query('SELECT * FROM ' . TABLE_USERS . ' WHERE id = 6')->fetchAssoc();
$_CONF['allow_submitter_mods'] = 1;
$_AUTH['curates'] = array();
$_AUTH['collaborates'] = array();
assert("lovd_isAuthorized('individual', '1', false) === 1");
assert("lovd_isAuthorized('phenotype', '1', false) === 1");
assert("lovd_isAuthorized('variant', '1', false) === 1");
assert("lovd_isAuthorized('screening', '1', false) === 1");

$_CONF['allow_submitter_mods'] = 0;
assert("lovd_isAuthorized('individual', '1', false) === 0");
assert("lovd_isAuthorized('phenotype', '1', false) === 0");
assert("lovd_isAuthorized('variant', '1', false) === 0");
assert("lovd_isAuthorized('screening', '1', false) === 0");

// Assertions for false input
assert("lovd_isAuthorized('gene', 'ASDFASDFASDF', false) === false");
assert("lovd_isAuthorized('disease', 'ASDFASDFASDF', false) === false");
assert("lovd_isAuthorized('transcript', 'ASDFASDFASDF', false) === false");
assert("lovd_isAuthorized('individual', 'ASDFASDFASDF', false) === false");
assert("lovd_isAuthorized('phenotype', 'ASDFASDFASDF', false) === false");
assert("lovd_isAuthorized('screening', 'ASDFASDFASDF', false) === false");
assert("lovd_isAuthorized('variant', 'ASDFASDFASDF', false) === false");
assert("lovd_isAuthorized('asdfasdf', '1', false) === false");

die('Complete, all successful.');
?>
