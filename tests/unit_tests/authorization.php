<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-07-25
 * Modified    : 2012-06-22
 * For LOVD    : 3.0-beta-06
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
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
//$_AUTH_old = $_AUTH;





// Assertions for DATABASE ADMINISTRATOR.
$_AUTH = $_DB->query('SELECT * FROM ' . TABLE_USERS . ' WHERE level = ?', array(LEVEL_ADMIN))->fetchAssoc();
assert("lovd_isAuthorized('gene', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('disease', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('transcript', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('variant', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('individual', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('phenotype', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('screening', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('asdfasdf', 'ASDFASDFASDF', false)");





// Assertions for MANAGER.
$_AUTH = $_DB->query('SELECT * FROM ' . TABLE_USERS . ' WHERE level = ?', array(LEVEL_MANAGER))->fetchAssoc();
assert("lovd_isAuthorized('gene', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('disease', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('transcript', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('variant', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('individual', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('phenotype', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('screening', 'ASDFASDFASDF', false)");
assert("lovd_isAuthorized('asdfasdf', 'ASDFASDFASDF', false)");





// Assertions for CURATORS.
$_AUTH = $_DB->query('SELECT u.*,
                        (SELECT GROUP_CONCAT(c.geneid SEPARATOR ";") FROM ' . TABLE_CURATES . ' AS c WHERE c.userid = u.id AND c.allow_edit = 1) AS _curates,
                        (SELECT GROUP_CONCAT(c.geneid SEPARATOR ";") FROM ' . TABLE_CURATES . ' AS c WHERE c.userid = u.id AND c.allow_edit = 0) AS _collaborates,
                        (SELECT GROUP_CONCAT(id SEPARATOR ";") FROM ' . TABLE_GENES . ' WHERE id NOT IN (SELECT geneid FROM ' . TABLE_CURATES . ' WHERE userid = u.id)) AS _submits
                      FROM ' . TABLE_USERS . ' AS u
                        LEFT JOIN ' . TABLE_CURATES . ' AS c_3 ON (u.id = c_3.userid)
                      WHERE u.level = ?
                      HAVING _curates IS NOT NULL AND _collaborates IS NOT NULL', array(LEVEL_SUBMITTER), false)->fetchAssoc();
// For this assumption to be true, you MUST have a user in the system that is a curator,
// a collaborator, and a submitter (= no authorization) for all different genes.
assert('!empty($_AUTH)');
print('Testing curator, collaborator and submitter rights with user: ' . $_AUTH['id'] . "\n");
$_AUTH['curates'] = explode(';', $_AUTH['_curates']);
$_AUTH['collaborates'] = explode(';', $_AUTH['_collaborates']);
$_AUTH['submits'] = explode(';', $_AUTH['_submits']);

// GENES.
assert("lovd_isAuthorized('gene', 'ASDFASDFASDF', false) === false");
assert("lovd_isAuthorized('gene', '" . $_AUTH['curates'][0] . "', false) === 1");
assert("lovd_isAuthorized('gene', '" . $_AUTH['collaborates'][0] . "', false) === 0");
assert("lovd_isAuthorized('gene', '" . $_AUTH['submits'][0] . "', false) === false");

// DISEASES.
assert("lovd_isAuthorized('disease', 'ASDFASDFASDF', false) === false");
$nIDCurator      = $_DB->query('SELECT d.id FROM ' . TABLE_DISEASES . ' AS d LEFT JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (d.id = g2d.diseaseid) WHERE g2d.geneid IN (?' . str_repeat(', ?', count($_AUTH['curates'])-1) . ') LIMIT 1', $_AUTH['curates'], false)->fetchColumn();
$nIDCollaborator = $_DB->query('SELECT d.id FROM ' . TABLE_DISEASES . ' AS d LEFT JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (d.id = g2d.diseaseid) WHERE g2d.geneid IN (?' . str_repeat(', ?', count($_AUTH['collaborates'])-1) . ') LIMIT 1', $_AUTH['collaborates'], false)->fetchColumn();
$nIDSubmitter    = $_DB->query('SELECT d.id FROM ' . TABLE_DISEASES . ' AS d LEFT JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (d.id = g2d.diseaseid) WHERE g2d.geneid IN (?' . str_repeat(', ?', count($_AUTH['submits'])-1) . ') LIMIT 1', $_AUTH['submits'], false)->fetchColumn();
// Don't remove quotes, zerofill will cause issues.
assert("lovd_isAuthorized('disease', '" . $nIDCurator . "', false) === 1");
assert("lovd_isAuthorized('disease', '" . $nIDCollaborator . "', false) === 0");
assert("lovd_isAuthorized('disease', '" . $nIDSubmitter . "', false) === false");

// TRANSCRIPTS.
assert("lovd_isAuthorized('transcript', 'ASDFASDFASDF', false) === false");
$nIDCurator      = $_DB->query('SELECT id FROM ' . TABLE_TRANSCRIPTS . ' WHERE geneid = ? LIMIT 1', array($_AUTH['curates'][0]), false)->fetchColumn();
$nIDCollaborator = $_DB->query('SELECT id FROM ' . TABLE_TRANSCRIPTS . ' WHERE geneid = ? LIMIT 1', array($_AUTH['collaborates'][0]), false)->fetchColumn();
$nIDSubmitter    = $_DB->query('SELECT id FROM ' . TABLE_TRANSCRIPTS . ' WHERE geneid = ? LIMIT 1', array($_AUTH['submits'][0]), false)->fetchColumn();
// Don't remove quotes, zerofill will cause issues.
assert("lovd_isAuthorized('transcript', '" . $nIDCurator . "', false) === 1");
assert("lovd_isAuthorized('transcript', '" . $nIDCollaborator . "', false) === 0");
assert("lovd_isAuthorized('transcript', '" . $nIDSubmitter . "', false) === false");

// VARIANTS.
assert("lovd_isAuthorized('variant', 'ASDFASDFASDF', false) === false");
$nIDCurator      = $_DB->query('SELECT v.id FROM ' . TABLE_VARIANTS . ' AS v INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (v.id = vot.id) INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE v.created_by != ? AND v.owned_by != ? AND t.geneid = ? LIMIT 1', array($_AUTH['id'], $_AUTH['id'], $_AUTH['curates'][0]), false)->fetchColumn();
$nIDOwner        = $_DB->query('SELECT v.id FROM ' . TABLE_VARIANTS . ' AS v LEFT JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (v.id = vot.id) LEFT JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE (v.created_by = ? OR v.owned_by = ?) AND t.geneid NOT IN (?' . str_repeat(', ?', count($_AUTH['curates'])-1) . ') LIMIT 1', array_merge(array($_AUTH['id'], $_AUTH['id']), $_AUTH['curates']), false)->fetchColumn();
$nIDCollaborator = $_DB->query('SELECT v.id FROM ' . TABLE_VARIANTS . ' AS v INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (v.id = vot.id) INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE v.created_by != ? AND v.owned_by != ? AND t.geneid = ? LIMIT 1', array($_AUTH['id'], $_AUTH['id'], $_AUTH['collaborates'][0]), false)->fetchColumn();
$nIDSubmitter    = $_DB->query('SELECT v.id FROM ' . TABLE_VARIANTS . ' AS v INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (v.id = vot.id) INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE v.created_by != ? AND v.owned_by != ? AND t.geneid = ? LIMIT 1', array($_AUTH['id'], $_AUTH['id'], $_AUTH['submits'][0]), false)->fetchColumn();
// Don't remove quotes, zerofill will cause issues.
assert("lovd_isAuthorized('variant', '" . $nIDCurator . "', false) === 1");
$_CONF['allow_submitter_mods'] = 1;
assert("lovd_isAuthorized('variant', '" . $nIDOwner . "', false) === 1");
$_CONF['allow_submitter_mods'] = 0;
assert("lovd_isAuthorized('variant', '" . $nIDOwner . "', false) === 0");
assert("lovd_isAuthorized('variant', '" . $nIDCollaborator . "', false) === 0");
assert("lovd_isAuthorized('variant', '" . $nIDSubmitter . "', false) === false");



///////////////////// WORK IN PROGRESS /////////////////////////////////////////
// INDIVIDUALS.
// PHENOTYPES.
// SCREENINGS.






//$_AUTH = $_AUTH_old;
die('Complete, all successful');
?>
