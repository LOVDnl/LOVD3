<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-07-25
 * Modified    : 2011-07-26
 * For LOVD    : 3.0-alpha-03
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
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

header('Content-type: text/plain; charset=UTF-8');

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

define('ROOT_PATH', '../src/');
require ROOT_PATH . 'inc-init.php';





// Assertions for DATABASE ADMINISTRATOR.
$_AUTH = mysql_fetch_assoc(lovd_queryDB('SELECT * FROM ' . TABLE_USERS . ' WHERE level = ?', array(LEVEL_ADMIN), true));
assert("lovd_isAuthorized('gene', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('transcript', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('variant', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('asdfasdf', 'ASDFASDFASDF', false)");





// Assertions for MANAGER.
$_AUTH = mysql_fetch_assoc(lovd_queryDB('SELECT * FROM ' . TABLE_USERS . ' WHERE level = ?', array(LEVEL_MANAGER), true));
assert("lovd_isAuthorized('gene', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('transcript', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('variant', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('asdfasdf', 'ASDFASDFASDF', false)");





// Assertions for CURATORS.
$_AUTH = mysql_fetch_assoc(lovd_queryDB('SELECT u.*,
                                           (SELECT GROUP_CONCAT(c.geneid SEPARATOR ";") FROM ' . TABLE_CURATES . ' AS c WHERE c.userid = u.id AND c.allow_edit = 1) AS _curates,
                                           (SELECT GROUP_CONCAT(c.geneid SEPARATOR ";") FROM ' . TABLE_CURATES . ' AS c WHERE c.userid = u.id AND c.allow_edit = 0) AS _collaborates,
                                           (SELECT GROUP_CONCAT(id SEPARATOR ";") FROM ' . TABLE_GENES . ' WHERE id NOT IN (SELECT geneid FROM ' . TABLE_CURATES . ' WHERE userid = u.id)) AS _submits
                                         FROM ' . TABLE_USERS . ' AS u
                                           LEFT JOIN ' . TABLE_CURATES . ' AS c_3 ON (u.id = c_3.userid)
                                         WHERE u.level = ?
                                         HAVING _curates IS NOT NULL AND _collaborates IS NOT NULL', array(LEVEL_SUBMITTER), false));
// For this assumption to be true, you MUST have a user in the system that is a curator,
// a collaborator, and a submitter (= no authorization) for all different genes.
assert('!empty($_AUTH)');
$_AUTH['curates'] = explode(';', $_AUTH['_curates']);
$_AUTH['collaborates'] = explode(';', $_AUTH['_collaborates']);
$_AUTH['submits'] = explode(';', $_AUTH['_submits']);

// GENES.
assert("lovd_isAuthorized('gene', 'ASDFASDFASDF', false) === false");
assert("lovd_isAuthorized('gene', '" . $_AUTH['curates'][0] . "', false) === 1");
assert("lovd_isAuthorized('gene', '" . $_AUTH['collaborates'][0] . "', false) === 0");
assert("lovd_isAuthorized('gene', '" . $_AUTH['submits'][0] . "', false) === false");

// TRANSCRIPTS.
assert("lovd_isAuthorized('transcript', 'ASDFASDFASDF', false) === false");
list($nIDCurator)      = mysql_fetch_row(lovd_queryDB('SELECT id FROM ' . TABLE_TRANSCRIPTS . ' WHERE geneid = ? LIMIT 1', array($_AUTH['curates'][0]), false));
list($nIDCollaborator) = mysql_fetch_row(lovd_queryDB('SELECT id FROM ' . TABLE_TRANSCRIPTS . ' WHERE geneid = ? LIMIT 1', array($_AUTH['collaborates'][0]), false));
list($nIDSubmitter)    = mysql_fetch_row(lovd_queryDB('SELECT id FROM ' . TABLE_TRANSCRIPTS . ' WHERE geneid = ? LIMIT 1', array($_AUTH['submits'][0]), false));
assert("lovd_isAuthorized('transcript', '" . $nIDCurator . "', false) === 1");
assert("lovd_isAuthorized('transcript', '" . $nIDCollaborator . "', false) === 0");
assert("lovd_isAuthorized('transcript', '" . $nIDSubmitter . "', false) === false");

// DISEASES.
assert("lovd_isAuthorized('disease', 'ASDFASDFASDF', false) === false");
list($nIDCurator)      = mysql_fetch_row(lovd_queryDB('SELECT d.id FROM ' . TABLE_DISEASES . ' AS d LEFT JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (d.id = g2d.diseaseid) WHERE g2d.geneid IN (?' . str_repeat(', ?', count($_AUTH['curates'])-1) . ') LIMIT 1', array($_AUTH['curates']), false));
list($nIDCollaborator) = mysql_fetch_row(lovd_queryDB('SELECT d.id FROM ' . TABLE_DISEASES . ' AS d LEFT JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (d.id = g2d.diseaseid) WHERE g2d.geneid IN (?' . str_repeat(', ?', count($_AUTH['collaborates'])-1) . ') LIMIT 1', array($_AUTH['collaborates']), false));
list($nIDSubmitter)    = mysql_fetch_row(lovd_queryDB('SELECT d.id FROM ' . TABLE_DISEASES . ' AS d LEFT JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (d.id = g2d.diseaseid) WHERE g2d.geneid IN (?' . str_repeat(', ?', count($_AUTH['submits'])-1) . ') LIMIT 1', array($_AUTH['submits']), false));
assert("lovd_isAuthorized('disease', '" . $nIDCurator . "', false) === 1");
assert("lovd_isAuthorized('disease', '" . $nIDCollaborator . "', false) === 0");
assert("lovd_isAuthorized('disease', '" . $nIDSubmitter . "', false) === false");

///////////////////// WORK IN PROGRESS /////////////////////////////////////////
// VARIANTS.
assert("lovd_isAuthorized('variant', 'ASDFASDFASDF', false) === false");





?>