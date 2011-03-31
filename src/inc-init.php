<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-19
 * Modified    : 2011-03-30
 * For LOVD    : 3.0-pre-19
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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

// Require library standard functions.
require ROOT_PATH . 'inc-lib-init.php';

// Define module path.
// DMD_SPECIFIC; do we still need this?
define('MODULE_PATH', ROOT_PATH . 'modules/');

// Set error_reporting if necessary. We don't want notices to show. This will do
// fine most of the time.
if (ini_get('error_reporting') == E_ALL) {
    error_reporting(E_ALL ^ E_NOTICE);
}

// DMD_SPECIFIC!!! - Testing purposes only.
if ($_SERVER['HTTP_HOST'] == 'localhost') {
    error_reporting(E_ALL | E_STRICT);
}





// Initiate $_SETT array.
// Find out whether or not we're using SSL.
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' && !empty($_SERVER['SSL_PROTOCOL'])) {
    // We're using SSL!
    define('SSL', true);
    define('SSL_PROTOCOL', $_SERVER['SSL_PROTOCOL']);
    define('PROTOCOL', 'https://');
} else {
    define('SSL', false);
    define('SSL_PROTOCOL', '');
    define('PROTOCOL', 'http://');
}

define('LEVEL_SUBMITTER', 1);    // Also includes collaborators and curators. Authorisation is depending on assignments, not user levels anymore.
define('LEVEL_COLLABORATOR', 3); // THIS IS NOT A VALID USER LEVEL. Just indicates level of authorisation. You can change these numbers, but keep the order!
define('LEVEL_CURATOR', 5);      // THIS IS NOT A VALID USER LEVEL. Just indicates level of authorisation. You can change these numbers, but keep the order!
define('LEVEL_MANAGER', 7);
define('LEVEL_ADMIN', 9);

define('STATUS_HIDDEN', 4);
define('STATUS_MARKED', 7);
define('STATUS_OK', 9);

define('AJAX_FALSE', '0');
define('AJAX_TRUE', '1');
define('AJAX_NO_AUTH', '8');
define('AJAX_DATA_ERROR', '9');

$aRequired =
         array(
                'PHP'   => '4.3.0',
                'MySQL' => '4.1',
              );

$_SETT = array(
                'system' =>
                     array(
                            'version' => '3.0-pre-18',
                          ),
                'user_levels' =>
                     array(
                            LEVEL_ADMIN        => 'Database administrator',
                            LEVEL_MANAGER      => 'LOVD manager',
                            LEVEL_CURATOR      => 'Curator',
                            LEVEL_COLLABORATOR => 'Collaborator',
                            LEVEL_SUBMITTER    => 'Submitter',
                          ),
/*
DMD_SPECIFIC
                'var_allele' =>
                     array(
                            0 => 'Unknown',
                            11 => 'Paternal (confirmed)',
                            10 => 'Paternal (inferred)',
                            21 => 'Maternal (confirmed)',
                            20 => 'Maternal (inferred)',
                            1 => 'Parent #1',
                            2 => 'Parent #2',
                            3 => 'Both (homozygous)',
                          ),
*/
                'var_pathogenic' =>
                     array(
                            5 => 'Unknown',
                            9 => 'Pathogenic',
                            7 => 'Probably pathogenic',
                            3 => 'Probably no pathogenicity',
                            1 => 'No known pathogenicity',
                          ),
                'var_pathogenic_short' =>
                     array(
                            '11' => '-/-',
                            '13' => '-/-?',
                            '15' => '-/?',
                            '17' => '-/+?',
                            '19' => '-/+',
                            '31' => '-?/-',
                            '33' => '-?/-?',
                            '35' => '-?/?',
                            '37' => '-?/+?',
                            '39' => '-?/+',
                            '51' => '?/-',
                            '53' => '?/-?',
                            '55' => '?/?',
                            '57' => '?/+?',
                            '59' => '?/+',
                            '71' => '+?/-',
                            '73' => '+?/-?',
                            '75' => '+?/?',
                            '77' => '+?/+?',
                            '79' => '+?/+',
                            '91' => '+/-',
                            '93' => '+/-?',
                            '95' => '+/?',
                            '97' => '+/+?',
                            '99' => '+/+',
                          ),
                'var_status' =>
                     array(
                            STATUS_HIDDEN => 'Non public',
                            STATUS_MARKED => 'Marked',
                            STATUS_OK => 'Public',
                          ),
                'update_levels' =>
                     array(
                            1 => 'Optional',
                            4 => 'Common',
                            5 => 'Suggested',
                            7 => 'Recommended',
                            8 => '<SPAN style="color:red;">Important</SPAN>',
                            9 => '<SPAN style="color:red;"><B>Critical</B></SPAN>',
                          ),
                'upstream_URL' => 'http://www.LOVD.nl/',
                'upstream_BTS_URL' => 'https://eu.liacs.nl/projects/LOVD3/newticket',
                'wikiprofessional_iprange' => '131.174.88.0-255',
                'list_sizes' =>
                     array(
                            25,
                            50,
                            100,
                            250,
                            500,
                            1000,
                          ),
                'notes_align' =>
                     array(
                            -1 => 'left',
                            0 => 'center',
                            1 => 'right',
                          ),
                'human_builds' =>
                     array(
                            '----' => array('ncbi_name' => 'non-Human'),
                            // This information has been taken from the release notes of the builds;
                            // http://www.ncbi.nlm.nih.gov/genome/guide/human/release_notes.html
                            'hg18' =>
                                     array(
                                            'ncbi_name'      => 'Build 36.1',
                                            'ncbi_sequences' =>
                                                     array(
                                                            '1'  => 'NC_000001.9',
                                                            '2'  => 'NC_000002.10',
                                                            '3'  => 'NC_000003.10',
                                                            '4'  => 'NC_000004.10',
                                                            '5'  => 'NC_000005.8',
                                                            '6'  => 'NC_000006.10',
                                                            '7'  => 'NC_000007.12',
                                                            '8'  => 'NC_000008.9',
                                                            '9'  => 'NC_000009.10',
                                                            '10' => 'NC_000010.9',
                                                            '11' => 'NC_000011.8',
                                                            '12' => 'NC_000012.10',
                                                            '13' => 'NC_000013.9',
                                                            '14' => 'NC_000014.7',
                                                            '15' => 'NC_000015.8',
                                                            '16' => 'NC_000016.8',
                                                            '17' => 'NC_000017.9',
                                                            '18' => 'NC_000018.8',
                                                            '19' => 'NC_000019.8',
                                                            '20' => 'NC_000020.9',
                                                            '21' => 'NC_000021.7',
                                                            '22' => 'NC_000022.9',
                                                            'X'  => 'NC_000023.9',
                                                            'Y'  => 'NC_000024.8',
                                                          ),
                                          ),
                            'hg19' =>
                                     array(
                                            'ncbi_name'      => 'GRCh37',
                                            'ncbi_sequences' =>
                                                     array(
                                                            '1'  => 'NC_000001.10',
                                                            '2'  => 'NC_000002.11',
                                                            '3'  => 'NC_000003.11',
                                                            '4'  => 'NC_000004.11',
                                                            '5'  => 'NC_000005.9',
                                                            '6'  => 'NC_000006.11',
                                                            '7'  => 'NC_000007.13',
                                                            '8'  => 'NC_000008.10',
                                                            '9'  => 'NC_000009.11',
                                                            '10' => 'NC_000010.10',
                                                            '11' => 'NC_000011.9',
                                                            '12' => 'NC_000012.11',
                                                            '13' => 'NC_000013.10',
                                                            '14' => 'NC_000014.8',
                                                            '15' => 'NC_000015.9',
                                                            '16' => 'NC_000016.9',
                                                            '17' => 'NC_000017.10',
                                                            '18' => 'NC_000018.9',
                                                            '19' => 'NC_000019.9',
                                                            '20' => 'NC_000020.10',
                                                            '21' => 'NC_000021.8',
                                                            '22' => 'NC_000022.10',
                                                            'X'  => 'NC_000023.10',
                                                            'Y'  => 'NC_000024.9',
                                                          ),
                                          ),
                          ),
              );

// Complete version info.
list($_SETT['system']['tree'], $_SETT['system']['build']) = explode('-', $_SETT['system']['version'], 2);
$_SETT['update_URL'] = $_SETT['upstream_URL'] . $_SETT['system']['tree'] . '/package_update.php';
$_SETT['check_location_URL'] = $_SETT['upstream_URL'] . $_SETT['system']['tree'] . '/check_location.php';



// We define CONFIG_URI as the location of the config file.
define('CONFIG_URI', ROOT_PATH . 'config.ini.php');

// Config file exists?
if (!file_exists(CONFIG_URI)) {
    lovd_displayError('Init', 'Can\'t find config.ini.php');
}

// Config file readable?
if (!is_readable(CONFIG_URI)) {
    lovd_displayError('Init', 'Can\'t read config.ini.php');
}

// Open config file.
if (!$aConfig = file(CONFIG_URI)) {
    lovd_displayError('Init', 'Can\'t open config.ini.php');
}



// Parse config file.
$_INI = array();
unset($aConfig[0]); // The first line is the PHP code with the exit() call.

$sKey = '';
foreach ($aConfig as $nLine => $sLine) {
    // Go through the file line by line.
    $sLine = trim($sLine);

    // Empty line or comment.
    if (!$sLine || substr($sLine, 0, 1) == '#') {
        continue;
    }

    // New section.
    if (preg_match('/^\[([A-Z][A-Z_ ]+[A-Z])\]$/i', $sLine, $aRegs)) {
        $sKey = $aRegs[1];
        $_INI[$sKey] = array();
        continue;
    }

    // Setting.
    if (preg_match('/^([A-Z_]+) *=(.*)$/i', $sLine, $aRegs)) {
        list(,$sVar, $sVal) = $aRegs;
        $sVal = trim($sVal);

        if (!$sVal) {
            $sVal = false;
        }

        // Set value in array.
        if ($sKey) {
            $_INI[$sKey][$sVar] = $sVal;
        } else {
            $_INI[$sVar] = $sVal;
        }

    } else {
        // Couldn't parse value.
        lovd_displayError('Init', 'Error parsing config file at line ' . ($nLine + 1));
    }
}

// We now have the $_INI variable filled according to the file's contents.
// Check the settings' values to see if they are valid.
$aConfigValues =
         array(
                'database' =>
                         array(
                                'hostname' =>
                                         array(
                                                'required' => true,
                                                'default'  => 'localhost',
                                                // Also include hostname:port and :/path/to/socket values.
                                                'pattern'  => '/^([0-9a-z][-0-9a-z.]*[0-9a-z](:[0-9]+)?|:[-0-9a-z.\/]+)$/i',
                                              ),
                                'username' =>
                                         array(
                                                'required' => true,
                                              ),
                                'password' =>
                                         array(
                                                'required' => true,
                                              ),
                                'database' =>
                                         array(
                                                'required' => true,
                                              ),
                                'table_prefix' =>
                                         array(
                                                'required' => true,
                                                'default'  => 'lovd',
                                                'pattern'  => '/^[A-Z0-9_]+$/i',
                                              ),
                              ),
              );

foreach ($aConfigValues as $sSection => $aVars) {
    foreach ($aVars as $sVar => $aVar) {
        if (!isset($_INI[$sSection][$sVar]) || !$_INI[$sSection][$sVar]) {
            // Nothing filled in...

            if (isset($aVar['default']) && $aVar['default']) {
                // Set default value.
                $_INI[$sSection][$sVar] = $aVar['default'];
            } elseif (isset($aVar['required']) && $aVar['required']) {
                // No default value, required setting not filled in.
                lovd_displayError('Init', 'Error parsing config file: missing required value for setting \'' . $sVar . '\' in section [' . $sSection . ']');
            }

        } else {
            // Value is present in $_INI.
            if (isset($aVar['pattern']) && !preg_match($aVar['pattern'], $_INI[$sSection][$sVar])) {
                // Error: a pattern is available, but it doesn't match the input!
                lovd_displayError('Init', 'Error parsing config file: incorrect value for setting \'' . $sVar . '\' in section [' . $sSection . ']');

            } elseif (isset($aVar['values']) && is_array($aVar['values'])) {
                // Value must be present in list of possible values.
                $_INI[$sSection][$sVar] = strtolower($_INI[$sSection][$sVar]);
                if (!array_key_exists($_INI[$sSection][$sVar], $aVar['values'])) {
                    // Error: a value list is available, but it doesn't match the input!
                    lovd_displayError('Init', 'Error parsing config file: incorrect value for setting \'' . $sVar . '\' in section [' . $sSection . ']');
                } else {
                    // Get correct value loaded.
                    $_INI[$sSection][$sVar] = $aVar['values'][$_INI[$sSection][$sVar]];
                }
            }
        }
    }
}





// Define table names (system-wide).
define('TABLEPREFIX', $_INI['database']['table_prefix']);
$_TABLES =
         array(
                'TABLE_COUNTRIES' => TABLEPREFIX . '_countries',
                'TABLE_USERS' => TABLEPREFIX . '_users',
                'TABLE_GENES' => TABLEPREFIX . '_genes',
                'TABLE_CURATES' => TABLEPREFIX . '_users2genes',
                'TABLE_TRANSCRIPTS' => TABLEPREFIX . '_transcripts',
                'TABLE_DISEASES' => TABLEPREFIX . '_diseases',
                'TABLE_GEN2DIS' => TABLEPREFIX . '_genes2diseases',
                'TABLE_DATA_STATUS' => TABLEPREFIX . '_data_status',
                'TABLE_PATHOGENIC' => TABLEPREFIX . '_variant_pathogenicity',
                'TABLE_PATIENTS' => TABLEPREFIX . '_patients',
                'TABLE_PAT2DIS' => TABLEPREFIX . '_patients2diseases',
                'TABLE_VARIANTS' => TABLEPREFIX . '_variants',
                'TABLE_VARIANTS_ON_TRANSCRIPTS' => TABLEPREFIX . '_variants_on_transcripts',
                'TABLE_PHENOTYPES' => TABLEPREFIX . '_phenotypes',
                'TABLE_SCREENINGS' => TABLEPREFIX . '_screenings',
                'TABLE_SCR2GENE' => TABLEPREFIX . '_screenings2genes',
                'TABLE_SCR2VAR' => TABLEPREFIX . '_screenings2variants',
                'TABLE_COLS' => TABLEPREFIX . '_columns',
                'TABLE_ACTIVE_COLS' => TABLEPREFIX . '_active_columns',
                'TABLE_SHARED_COLS' => TABLEPREFIX . '_shared_columns',
                'TABLE_LINKS' => TABLEPREFIX . '_links',
                'TABLE_COLS2LINKS' => TABLEPREFIX . '_columns2links',
                'TABLE_CONFIG' => TABLEPREFIX . '_config',
                'TABLE_STATUS' => TABLEPREFIX . '_status',
                'TABLE_SOURCES' => TABLEPREFIX . '_external_sources',
                'TABLE_LOGS' => TABLEPREFIX . '_logs',
                'TABLE_MODULES' => TABLEPREFIX . '_modules',
                'TABLE_HITS' => TABLEPREFIX . '_hits',
              );

foreach ($_TABLES as $sConst => $sTable) {
    define($sConst, $sTable);
}

if (!function_exists('mysql_connect')) {
    lovd_displayError('Init', 'This PHP installation does not have MySQL support installed. Without it, LOVD will not function. Please install MySQL support for PHP.');
}

// Initiate Database Connection.
$_DB = @mysql_connect($_INI['database']['hostname'], $_INI['database']['username'], $_INI['database']['password']);
if (!$_DB) {
    // No connection!
    lovd_displayError('Init', 'Error connecting to database - ' . mysql_error());
}

$bSelected = @mysql_select_db($_INI['database']['database']);
if (!$bSelected) {
    // Can't select database.
    lovd_displayError('Init', 'Error selecting database - ' . mysql_error());
}

// Get the character set right.
if (($sCharSet = mysql_client_encoding()) && $sCharSet != 'utf8') {
    // mysql_set_charset() is available only with PHP 5.2.3 and MySQL 5.0.7.
    @lovd_queryDB('SET NAMES utf8');
}

ini_set('default_charset','UTF-8');

// Help prevent cookie theft trough JavaScript; XSS defensive line.
// See: http://nl.php.net/manual/en/session.configuration.php#ini.session.cookie-httponly
@ini_set('session.cookie_httponly', 1); // Available from 5.2.0.

// Read system-wide configuration from the database.
$_CONF = @mysql_fetch_assoc(lovd_queryDB('SELECT * FROM ' . TABLE_CONFIG));

// Read LOVD status from the database.
$_STAT = @mysql_fetch_assoc(lovd_queryDB('SELECT * FROM ' . TABLE_STATUS));

if (!is_array($_CONF) || !count($_CONF) || !is_array($_STAT) || !count($_STAT) || !isset($_STAT['version']) || !preg_match('/^([1-9]\.[0-9](\.[0-9])?)\-([0-9a-z-]{2,11})$/', $_STAT['version'], $aRegsVersion)) {
    // We couldn't get the installation's configuration or status. Are we properly installed, then?

    // Copying information that is required for the includes, but can't be read from the database.
    $_CONF['system_title'] = 'LOVD 3.0 - Leiden Open Variation Database';
    $_STAT['tree'] = $_SETT['system']['tree'];
    $_STAT['build'] = $_SETT['system']['build'];

    // Are we installed properly?
    $aTables = array();
    $q = lovd_queryDB('SHOW TABLES LIKE ?', array(TABLEPREFIX . '\_%'));
    while ($r = mysql_fetch_row($q)) {
        if (in_array($r[0], $_TABLES)) {
            $aTables[] = $r[0];
        }
    }
    if (count($aTables) < count($_TABLES)) {
        // We're not completely installed.
        define('_NOT_INSTALLED_', true);
    }

    // inc-js-submit-settings.php check is necessary because it gets included in the install directory.
    if (dirname(lovd_getProjectFile()) != '/install' && lovd_getProjectFile() != '/inc-js-submit-settings.php') {
        // We're not installing, so throwing an error.

        if (defined('_NOT_INSTALLED_')) {
            // We're not completely installed.
            require ROOT_PATH . 'install/inc-top.php';
            print('      <BR>' . "\n" .
                  '      &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;LOVD was not installed yet. Please <A href="' . ROOT_PATH . 'install">install</A> LOVD first.<BR>' . "\n");
            require ROOT_PATH . 'install/inc-bot.php';
            exit;

        } else {
            // Can't get the configuration for unknown reason. Bail out.
            lovd_displayError('Init', 'Error retrieving LOVD configuration or status information');
        }
    } // This should leave us alone if we're installing, even if we've got all tables, but are not quite done yet.

} else {
    // Store additional version information.
    list(, $_STAT['tree'],, $_STAT['build']) = $aRegsVersion;
}

// Prevent some troubles with the menu when the URL contains double slashes.
$_SERVER['SCRIPT_NAME'] = lovd_cleanDirName($_SERVER['SCRIPT_NAME']);



// Force GPC magic quoting OFF.
if (get_magic_quotes_gpc()) {
    lovd_magicUnquoteAll();
}

// Use of SSL required?
// DMD_SPECIFIC:
//// (SSL not required when exporting data to WikiProfessional because their scripts do not support it)
//// (The UCSC also has issues with retrieving the BED files through SSL...)
//if (!empty($_CONF['use_ssl']) && !SSL && !(lovd_getProjectFile() == '/export_data.php' && !empty($_GET['format']) && $_GET['format'] == 'wiki') && !(substr(lovd_getProjectFile(), 0, 9) == '/api/rest' && !empty($_GET['format']) && $_GET['format'] == 'text/bed')) {
if (!empty($_CONF['use_ssl']) && !SSL) {
    // We were enabled, when SSL was available. So I guess SSL is still available. If not, this line here would be a problem.
    // No, not sending any $_POST values either. Let's just assume no-one is working with LOVD when the ssl setting is activated.
    // FIXME; does not allow for nice URLs.
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}

// Session settings - use cookies.
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
if (ini_get('session.cookie_path') == '/') {
    // Don't share cookies with other systems - set the cookie path!
    ini_set('session.cookie_path', lovd_getInstallURL(false));
}
if (!empty($_STAT['signature'])) {
    // Set the session name to something unique, to prevent mixing cookies with other LOVDs on the same server.
    $_SETT['cookie_id'] = md5($_STAT['signature']);
    session_name('PHPSESSID_' . $_SETT['cookie_id']);

    // Start sessions - use cookies.
    session_start();
}



// The following applies only if the system is fully installed.
if (!defined('_NOT_INSTALLED_')) {
    // Load session data.
    require ROOT_PATH . 'inc-auth.php';

    // Define $_PATH_ELEMENTS and CURRENT_PATH.
    $sPath = preg_replace('/^' . preg_quote(lovd_getInstallURL(false), '/') . '/', '', lovd_cleanDirName($_SERVER['REQUEST_URI'])); // 'login' or 'genes?create' or 'users/00001?edit'
    $aPath = explode('?', $sPath); // Cut ff the Query string, that will be handled later.
    $_PATH_ELEMENTS = explode('/', rtrim($aPath[0], '/')); // array('login') or array('genes') or array('users', '00001')
    // XSS check on the elements.
    foreach ($_PATH_ELEMENTS as $key => $val) {
        if ($val !== strip_tags($val)) {
            $_PATH_ELEMENTS[$key] = '';
        }
    }
    define('CURRENT_PATH', implode('/', $_PATH_ELEMENTS));

    // Define ACTION.
    if ($_SERVER['QUERY_STRING'] && preg_match('/^(\w+)(&.*)?$/', $_SERVER['QUERY_STRING'], $aRegs)) {
        define('ACTION', $aRegs[1]);
    } else {
        define('ACTION', false);
    }

    // STUB; This should be implemented properly later on.
    define('OFFLINE_MODE', false);

    // Define constant for request method.
    define($_SERVER['REQUEST_METHOD'], true);
    @define('GET', false);
    @define('POST', false);
    @define('PUT', false);
    @define('DELETE', false);

    // We really don't need any of this, if we're loaded by the update picture.
    // DMD_SPECIFIC; CHECK THIS BLOCK LATER
    if (!in_array(lovd_getProjectFile(), array('/check_update.php', '/logout.php'))) {
        // Force user to change password.
        if ($_AUTH && $_AUTH['password_force_change'] && !(lovd_getProjectFile() == '/users.php' && in_array(ACTION, array('edit', 'change_password')) && $_PATH_ELEMENTS[1] == $_AUTH['id'])) {
            header('Location: ' . lovd_getInstallURL() . 'users/' . $_AUTH['id'] . '?change_password');
            exit;
        }

        // Load DB admin data; needed by sending messages.
        if ($_AUTH && $_AUTH['level'] == LEVEL_ADMIN) {
            // Saves me quering the database!
            $_SETT['admin'] = array('name' => $_AUTH['name'], 'email' => $_AUTH['email']);
        } else {
            $_SETT['admin'] = array();
            list($_SETT['admin']['name'], $_SETT['admin']['email']) = mysql_fetch_row(lovd_queryDB('SELECT name, email FROM ' . TABLE_USERS . ' WHERE level = ?', array(LEVEL_ADMIN)));
        }

// DMD_SPECIFIC - Don't think we need this anymore.
/*
        // Switch gene.
        if (isset($_GET['select_db']) && !(isset($_SESSION['currdb']) && $_SESSION['currdb'] == $_GET['select_db'])) {
            if (in_array($_GET['select_db'], lovd_getGeneList())) {
                // Change this in de database if user is logged in.
                if ($_AUTH && $_AUTH['current_db'] != $_GET['select_db']) {
                    $sQ = 'UPDATE ' . TABLE_USERS . ' SET current_db = "' . $_GET['select_db'] . '" WHERE id = "' . $_AUTH['id'] . '"';
                    $q = @mysql_query($sQ);
                    if (!$q) {
                        lovd_dbFout('UpdateCurrDB', $sQ, mysql_error(), false);
                    }
                }
                $_SESSION['currdb'] = $_GET['select_db'];
            } else {
                lovd_displayError('Init', 'You provided a non-existing gene database name');
            }
        }
*/

// DMD_SPECIFIC; how are we going to handle this?
        // Load gene data.
        if (!empty($_SESSION['currdb'])) {
            $_SETT['currdb'] = @mysql_fetch_assoc(lovd_queryDB('SELECT * FROM ' . TABLE_GENES . ' WHERE id = ?', array($_SESSION['currdb'])));
            if (!$_SETT['currdb']) {
                $_SESSION['currdb'] = false;
            }
        } else {
            $_SESSION['currdb'] = false;
        }

        // Just often used...
        define('GENE_COUNT', count(lovd_getGeneList()));

}/*
        // Simply so that we can build somewhat correct email headers.
        if (empty($_CONF['location_name'])) {
            $_CONF['location_name'] = $_SERVER['HTTP_HOST'];
        }
        if (empty($_CONF['email_address'])) {
            $_CONF['email_address'] = 'noreply@' . (substr($_SERVER['HTTP_HOST'], 0, 4) == 'www.'? substr($_SERVER['HTTP_HOST'], 4) : $_SERVER['HTTP_HOST']);
        }

        // Determine email header line endings.
        // Define constant to quickly check if we're on Windows, since sending emails on Windows requires yet one more adaptation.
        if (strtoupper(substr(PHP_OS, 0, 3) == 'WIN')) {
                $sEol = "\r\n";
                define('ON_WINDOWS', true);
        } elseif (strtoupper(substr(PHP_OS, 0, 3) == 'MAC')) {
                $sEol = "\r";
                define('ON_WINDOWS', false);
        } else {
                $sEol = "\n";
                define('ON_WINDOWS', false);
        }

        $_SETT['email_headers'] = 'MIME-Version: 1.0' . $sEol .
                            'Content-Type: text/plain; charset=ISO-8859-1' . $sEol .
                            'X-Priority: 3' . $sEol .
                            'X-MSMail-Priority: Normal' . $sEol .
                            'X-Mailer: PHP/' . phpversion() . $sEol .
                            'From: ' . (ON_WINDOWS? '' : '"LOVDv.' . $_SETT['system']['tree'] . ' (' . $_CONF['location_name'] . ')" ') . '<' . $_CONF['email_address'] . '>';
    }

    // Load LOVD modules!
    require ROOT_PATH . 'class/modules.php';
    $_MODULES = new Modules;
*/
} else {
    define('ACTION', false);
}
?>
