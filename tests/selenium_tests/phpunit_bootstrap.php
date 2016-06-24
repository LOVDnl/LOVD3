<?php

// Set up global constants and include path for running tests.
define('ROOT_PATH', realpath(__DIR__ . '/../../'));

// Code below to parse the config file is a near copy of that in inc-init.php.
// inc-init.php cannot simply be included here because this code is run from
// the command line. Moving that code to a library is not trivial as it
// produces HTML error messages.
// Fixme: Refactor config file parsing code in inc-init.php to allow usage here
define('CONFIG_URI', ROOT_PATH . '/src/config.ini.php');
if (!$aConfig = file(CONFIG_URI)) {
    throw new Exception('Init', 'Can\'t open config.ini.php');
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
        $sVal = trim($sVal, ' "\'“”');

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
        throw new Exception('Init', 'Error parsing config file at line ' . ($nLine + 1));
    }
}

// Get root URL from config file.
if (!isset($_INI['test']['root_url'])) {
    throw new Exception('Failed to initialize ROOT_URL from ' . CONFIG_URI);
}
define('ROOT_URL', $_INI['test']['root_url']);

// Check if XDebug session should be started.
$bConfigXDebug = isset($_INI['test']['xdebug_enabled']) &&
                 $_INI['test']['xdebug_enabled'] == 'true';
define('XDEBUG_ENABLED', $bConfigXDebug);
$bXDebugStatus = false;

set_include_path(get_include_path() . PATH_SEPARATOR . ROOT_PATH . '/tests/selenium_tests');