<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-07-25
 * Modified    : 2013-10-16
 * For LOVD    : 3.0-08
 *
 * Copyright   : 2004-2013 Leiden University Medical Center; http://www.LUMC.nl/
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

assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_WARNING, 0);
assert_options(ASSERT_BAIL, 1);
assert_options(ASSERT_QUIET_EVAL, 0);
assert_options(ASSERT_CALLBACK, 'lovd_assertFailed');

function lovd_assertFailed ($sFile, $nLine, $sCode)
{
    print('Assertion Failed!' . "\n" .
//          '  File: ' . $sFile . "\n" .
          '  Line: ' . $nLine . "\n" .
          '  Code: ' . $sCode . "\n\n");
}

define('ROOT_PATH', '../../src/');
require ROOT_PATH . 'inc-init.php';


// Assertions for DATABASE ADMINISTRATOR.
$_AUTH = $_DB->query('SELECT * FROM ' . TABLE_USERS . ' WHERE id = 1')->fetchAssoc();
assert('!empty($_AUTH)');
assert("lovd_isAuthorized('gene', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('disease', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('transcript', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('variant', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('individual', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('phenotype', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('screening', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('asdfasdf', 'ASDFASDFASDF', false)");

// Assertions for MANAGER.
$_AUTH = $_DB->query('SELECT * FROM ' . TABLE_USERS . ' WHERE id = 2')->fetchAssoc();
assert('!empty($_AUTH)');
assert("lovd_isAuthorized('gene', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('disease', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('transcript', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('variant', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('individual', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('phenotype', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('screening', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('asdfasdf', 'ASDFASDFASDF', false)");

// Assertions for CURATORS.
$_AUTH = $_DB->query('SELECT * FROM ' . TABLE_USERS . ' WHERE id = 3')->fetchAssoc();
assert('!empty($_AUTH)');
$_AUTH['curates'] = $_DB->query('SELECT geneid FROM ' . TABLE_CURATES . ' WHERE userid = 3 AND allow_edit = 1')->fetchAssoc();
$_AUTH['collaborates'] = array();
assert('!empty($_AUTH["curates"])');
assert("lovd_isAuthorized('gene', 'IVD', false) === 1");
assert("lovd_isAuthorized('disease', '1', false) === 1");
assert("lovd_isAuthorized('transcript', '1', false) === 1");
assert("lovd_isAuthorized('variant', '1', false) === 1");
assert("lovd_isAuthorized('individual', '1', false) === 1");
assert("lovd_isAuthorized('phenotype', '1', false) === 1");
assert("lovd_isAuthorized('screening', '1', false) === 1");

// Assertions for COLLABORATOR.
$_AUTH = $_DB->query('SELECT * FROM ' . TABLE_USERS . ' WHERE id = 4')->fetchAssoc();
assert('!empty($_AUTH)');
$_AUTH['collaborates'] = $_DB->query('SELECT geneid FROM ' . TABLE_CURATES . ' WHERE userid = 4 AND allow_edit = 0')->fetchAssoc();
$_AUTH['curates'] = array();
assert('!empty($_AUTH["collaborates"])');
assert("lovd_isAuthorized('gene', 'IVD', false) === 0");
assert("lovd_isAuthorized('disease', '1', false) === 0");
assert("lovd_isAuthorized('transcript', '1', false) === 0");
assert("lovd_isAuthorized('individual', '1', false) === 0");
assert("lovd_isAuthorized('phenotype', '1', false) === 0");
assert("lovd_isAuthorized('variant', '1', false) === 0");
assert("lovd_isAuthorized('screening', '1', false) === 0");

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

die('Complete, all successful');
?>
