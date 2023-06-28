<?php
define('ROOT_PATH', '../');
define('ALLOW_TEXT_PLAIN', true);
$_GET['format'] = 'text/plain';
require ROOT_PATH . 'inc-init.php';

// Turn all errors on.
error_reporting(E_ALL);
ini_set('display_errors', 1);

$bFopenWrappers = ini_get('allow_url_fopen');
$bProxy = (bool) $_CONF['proxy_host'];
$bCurl = function_exists('curl_init');

print('PHP VERSION : ' . PHP_VERSION_ID);

// Testing connection to local file.
print("
================================================================================
Opening local file, using fopen(), should return large positive number:
");
var_dump(strlen(implode("\n", lovd_php_file(__FILE__))));

// Check for fopen wrapper settings.
print("
================================================================================
Checking for fopen wrapper setting, should return 1:
");
var_dump($bFopenWrappers);

// Check proxy.
print("
================================================================================
" . ($bProxy? "Proxy server configured. Checking if it can be bypassed." : "No proxy server configured.") . "
");

if ($bProxy) {
    // Dump proxy settings.
    var_dump($_CONF['proxy_host'], $_CONF['proxy_port'], $_CONF['proxy_username'], str_repeat('*', strlen($_CONF['proxy_password'])));
    // Check if proxy can be bypassed.
    $bProxyCanBeBypassed = (bool) @file('http://www.lovd.nl/mirrors/lrg/LRG_list.txt');
    print("
Proxy server " . ($bProxyCanBeBypassed? 'CAN' : 'CAN NOT') . " be bypassed.
");
}

// Check Curl.
print("
================================================================================
" . ($bCurl? "Curl available." : "Curl not available.") . "
");

// Testing connection to file on LOVD.nl.
print("
================================================================================
Opening remote file over HTTP, using " . ($bFopenWrappers? 'our file() since wrapper is enabled' : 'fsockopen() fallback since wrapper is disabled') . ", should return large positive number:
");
var_dump(strlen(implode("\n", lovd_php_file('http://www.lovd.nl/mirrors/lrg/LRG_list.txt'))));

// Testing connection to HGNC.
print("
================================================================================
Contacting HGNC over HTTP, using " . ($bFopenWrappers? 'our file() since wrapper is enabled' : 'fsockopen() fallback since wrapper is disabled') . ", should return IVD gene data:
");
var_dump(lovd_php_file('http://rest.genenames.org/search/symbol/IVD', false, '', 'Accept: application/json'));

// Testing connection to Mutalyzer.
$sURL = str_replace('/services', '', $_CONF['mutalyzer_soap_url']) . '/json/getGeneLocation?build=' . $_CONF['refseq_build'] . '&gene=IVD';
print("
================================================================================
Contacting Mutalyzer over HTTPS, using fsockopen() fallback, should return IVD mapping data:
");
var_dump(lovd_php_file($sURL));
print("
================================================================================
Contacting Mutalyzer over HTTPS, using non-context file() call, should " . (!$bFopenWrappers? 'fail since fopen wrappers are off' : ($bProxy && !$bProxyCanBeBypassed? 'fail since the proxy is ignored' : 'return IVD mapping data')) . ":
");
var_dump(file($sURL, FILE_IGNORE_NEW_LINES));
if ($bCurl) {
    print("
================================================================================
Contacting Mutalyzer at ${_CONF['mutalyzer_soap_url']} over Curl, should return IVD mapping data:
");
    var_dump(lovd_callMutalyzer('getGeneLocation', array('build' => $_CONF['refseq_build'], 'gene' => 'IVD')));
}

// Checking SNI_server_name / peer_name.
print("
================================================================================
Contacting LOVD server over HTTPS, using our file() wrapper, testing SNI_server_name vs peer_name settings, should " . (!$bFopenWrappers? 'fail since fopen wrappers are off' : 'return a large positive number') . ":
");
var_dump(strlen(implode("\n", lovd_php_file('https://grenada.lumc.nl/'))));
?>
