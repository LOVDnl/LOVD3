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

// Checking SNI_server_name / peer_name.
print("
================================================================================
Contacting LOVD server over HTTPS, using our file() wrapper, testing SNI_server_name vs peer_name settings, should " . (!$bFopenWrappers? 'fail since fopen wrappers are off' : 'return a large positive number') . ":
");
var_dump(strlen(implode("\n", lovd_php_file('https://grenada.lumc.nl/'))));

// Now checking LOVD's Mutalyzer's SOAP implementation.
$bSOAPWSDLCacheEnabled = ini_get('soap.wsdl_cache_enabled');
$bSOAPWSDLCacheTTL = ini_get('soap.wsdl_cache_ttl');
print("
================================================================================
                                   SOAP TESTS
================================================================================
WSDLCacheEnabled : $bSOAPWSDLCacheEnabled
WSDLCacheTTL     : $bSOAPWSDLCacheTTL");

// The original class (with minor modifications for PHP 5.6.0.).
class LOVD_SoapClientOri extends SoapClient {
    // This class provides a wrapper around SoapClient such that the proxy settings
    // are respected and SoapClient options are handled in just one place.

    function __construct ()
    {
        // Initiate Soap Client.
        global $_CONF;

        $sHostname = parse_url($_CONF['mutalyzer_soap_url'], PHP_URL_HOST);
        // Mutalyzer's Apache server doesn't like SSL requests coming in through a proxy, if these settings are not configured.
        // The new Mutalyzer server (scheduled to be released in September, 2014) does not have this issue, but still works with these settings enabled.
        $oContext = stream_context_create(array('ssl' => array('allow_self_signed' => 1, 'SNI_enabled' => 1, (PHP_VERSION_ID >= 50600? 'peer_name' : 'SNI_server_name') => $sHostname)));
        $aOptions =
            array(
                'features' => SOAP_SINGLE_ELEMENT_ARRAYS, // Makes sure we ALWAYS get an array back, even if there is just one element returned (saves us a lot is_array() checks).
                'stream_context' => $oContext,
            );
        if ($_CONF['proxy_host']) {
            $aOptions = array_merge($aOptions, array(
                'proxy_host'     => $_CONF['proxy_host'],
                'proxy_port'     => $_CONF['proxy_port'],
                'proxy_login'    => $_CONF['proxy_username'],
                'proxy_password' => $_CONF['proxy_password'],
            ));
        }

        return parent::__construct($_CONF['mutalyzer_soap_url'] . '?wsdl', $aOptions);
    }
}

class LOVD_SoapClientNoVerify extends SoapClient {
    // This class provides a wrapper around SoapClient such that the proxy settings
    // are respected and SoapClient options are handled in just one place.

    function __construct ()
    {
        // Initiate Soap Client.
        global $_CONF;

        $sHostname = parse_url($_CONF['mutalyzer_soap_url'], PHP_URL_HOST);
        // Mutalyzer's Apache server doesn't like SSL requests coming in through a proxy, if these settings are not configured.
        // The new Mutalyzer server (scheduled to be released in September, 2014) does not have this issue, but still works with these settings enabled.
        $oContext = stream_context_create(array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false)));
        $aOptions =
            array(
                'features' => SOAP_SINGLE_ELEMENT_ARRAYS, // Makes sure we ALWAYS get an array back, even if there is just one element returned (saves us a lot is_array() checks).
                'stream_context' => $oContext,
            );
        if ($_CONF['proxy_host']) {
            $aOptions = array_merge($aOptions, array(
                'proxy_host'     => $_CONF['proxy_host'],
                'proxy_port'     => $_CONF['proxy_port'],
                'proxy_login'    => $_CONF['proxy_username'],
                'proxy_password' => $_CONF['proxy_password'],
            ));
        }

        return parent::__construct($_CONF['mutalyzer_soap_url'] . '?wsdl', $aOptions);
    }
}

class LOVD_SoapClientNoVerifyWSDLReconfirm extends SoapClient {
    // This class provides a wrapper around SoapClient such that the proxy settings
    // are respected and SoapClient options are handled in just one place.

    function __construct ()
    {
        // Initiate Soap Client.
        global $_CONF;

        $sHostname = parse_url($_CONF['mutalyzer_soap_url'], PHP_URL_HOST);
        // Mutalyzer's Apache server doesn't like SSL requests coming in through a proxy, if these settings are not configured.
        // The new Mutalyzer server (scheduled to be released in September, 2014) does not have this issue, but still works with these settings enabled.
        $oContext = stream_context_create(array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false)));
        $aOptions =
            array(
                'features' => SOAP_SINGLE_ELEMENT_ARRAYS, // Makes sure we ALWAYS get an array back, even if there is just one element returned (saves us a lot is_array() checks).
                'stream_context' => $oContext,
            );
        if ($_CONF['proxy_host']) {
            $aOptions = array_merge($aOptions, array(
                'proxy_host'     => $_CONF['proxy_host'],
                'proxy_port'     => $_CONF['proxy_port'],
                'proxy_login'    => $_CONF['proxy_username'],
                'proxy_password' => $_CONF['proxy_password'],
            ));
        }

        parent::__construct($_CONF['mutalyzer_soap_url'] . '?wsdl', $aOptions);
        // 2016-09-01; 3.0-17; Some PHP versions need this additional call, because somehow they start failing otherwise.
        // Also see the ini_set() calls above.
        $this->__setLocation($_CONF['mutalyzer_soap_url'] . '?wsdl');
        return true;
    }
}



print("
================================================================================
Requesting IVD mapping data, no modifications:
");

$_Mutalyzer = new LOVD_SoapClientOri();
var_dump($_Mutalyzer->getGeneLocation(array('build' => $_CONF['refseq_build'], 'gene' => 'IVD'))->getGeneLocationResult);



print("
================================================================================
Requesting IVD mapping data, disabled peer verification:
");

$_Mutalyzer = new LOVD_SoapClientNoVerify();
var_dump($_Mutalyzer->getGeneLocation(array('build' => $_CONF['refseq_build'], 'gene' => 'IVD'))->getGeneLocationResult);



print("
================================================================================
Requesting IVD mapping data, disabled peer verification, reconfirming WSDL:
");

$_Mutalyzer = new LOVD_SoapClientNoVerifyWSDLReconfirm();
var_dump($_Mutalyzer->getGeneLocation(array('build' => $_CONF['refseq_build'], 'gene' => 'IVD'))->getGeneLocationResult);



// Now checking with different settings.
ini_set('soap.wsdl_cache_enabled', 0);
ini_set('soap.wsdl_cache_ttl', 0);
$bSOAPWSDLCacheEnabled = ini_get('soap.wsdl_cache_enabled');
$bSOAPWSDLCacheTTL = ini_get('soap.wsdl_cache_ttl');
print("
================================================================================
                              DISABLING WSDL CACHE
================================================================================
WSDLCacheEnabled : $bSOAPWSDLCacheEnabled
WSDLCacheTTL     : $bSOAPWSDLCacheTTL
================================================================================
Requesting IVD mapping data, no modifications:
");

$_Mutalyzer = new LOVD_SoapClientOri();
var_dump($_Mutalyzer->getGeneLocation(array('build' => $_CONF['refseq_build'], 'gene' => 'IVD'))->getGeneLocationResult);



print("
================================================================================
Requesting IVD mapping data, disabled peer verification:
");

$_Mutalyzer = new LOVD_SoapClientNoVerify();
var_dump($_Mutalyzer->getGeneLocation(array('build' => $_CONF['refseq_build'], 'gene' => 'IVD'))->getGeneLocationResult);



print("
================================================================================
Requesting IVD mapping data, disabled peer verification, reconfirming WSDL:
");

$_Mutalyzer = new LOVD_SoapClientNoVerifyWSDLReconfirm();
var_dump($_Mutalyzer->getGeneLocation(array('build' => $_CONF['refseq_build'], 'gene' => 'IVD'))->getGeneLocationResult);



// Now checking the different call that seems to fail still.
print("
================================================================================
                 REQUESTING  TRANSCRIPT INFORMATION  FROM  GENE
================================================================================
WSDLCacheEnabled : $bSOAPWSDLCacheEnabled
WSDLCacheTTL     : $bSOAPWSDLCacheTTL
================================================================================
Requesting IVD mapping data, no modifications:
");

$_Mutalyzer = new LOVD_SoapClientOri();
$aResults = $_Mutalyzer->getTranscriptsAndInfo(array('genomicReference' => 'UD_145628011486', 'geneName' => 'LAMA2'))->getTranscriptsAndInfoResult->TranscriptInfo;
if (is_array($aResults)) {
    foreach ($aResults as $nKey => $aResult) {
        if (isset($aResult->id)) {
            $aResults[$nKey] = $aResult->id;
        }
    }
}
var_dump($aResults);



print("
================================================================================
Requesting IVD mapping data, disabled peer verification:
");

$_Mutalyzer = new LOVD_SoapClientNoVerify();
$aResults = $_Mutalyzer->getTranscriptsAndInfo(array('genomicReference' => 'UD_145628011486', 'geneName' => 'LAMA2'))->getTranscriptsAndInfoResult->TranscriptInfo;
if (is_array($aResults)) {
    foreach ($aResults as $nKey => $aResult) {
        if (isset($aResult->id)) {
            $aResults[$nKey] = $aResult->id;
        }
    }
}
var_dump($aResults);



print("
================================================================================
Requesting IVD mapping data, disabled peer verification, reconfirming WSDL:
");

$_Mutalyzer = new LOVD_SoapClientNoVerifyWSDLReconfirm();
$aResults = $_Mutalyzer->getTranscriptsAndInfo(array('genomicReference' => 'UD_145628011486', 'geneName' => 'LAMA2'))->getTranscriptsAndInfoResult->TranscriptInfo;
if (is_array($aResults)) {
    foreach ($aResults as $nKey => $aResult) {
        if (isset($aResult->id)) {
            $aResults[$nKey] = $aResult->id;
        }
    }
}
var_dump($aResults);
?>
