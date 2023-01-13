<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-11-22
 * Modified    : 2023-01-13
 * For LOVD    : 3.0-29
 *
 * Copyright   : 2004-2023 Leiden University Medical Center; http://www.LUMC.nl/
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

// Don't allow direct access.
if (!defined('ROOT_PATH')) {
    exit;
}



class LOVD_API
{
    // This class defines the LOVD API object, handling URL parsing and general
    //  handling of headers.

    public $nVersion = 2;     // The API version. 0 for the LOVD2-style API. Higher versions are for the LOVD3-style REST API.
    public $sMethod = '';     // The used method (GET, POST, PUT, DELETE).
    public $sResource = '';   // The requested resource.
    public $nID = '';         // The ID of the requested resource.
    public $sGene = '';       // The LOVD2-style API often has the gene symbol in the URL, since it's gene-specific.

    protected $sFormatOutput = '';        // The output format, may be a decision based on the request.

    public $aResponse = array( // The standard response body.
        'version' => '',
        'messages' => array(),
        'warnings' => array(),
        'errors' => array(),
        'data' => array(),
    );
    public $nHTTPStatus = 0;   // The HTTP status that should be send back to the user.
    public $aHTTPHeaders = array(); // The HTTP response headers to send.

    // Currently supported resources (resource => array(methods)):
    private $aResourcesSupported = array(
        'ga4gh' => array('GET', 'HEAD'),
        'submissions' => array('POST'),
    );

    // Currently supported output formats that can be requested:
    private $aFormatsAccepted = array(
        'application/json',
        'application/*',
        'text/plain',
        'text/bed',
        'text/*',
        '*/*',
    );





    function __construct ()
    {
        // Initiates the API. Parses the URL, defines the variables, stores the allowed methods, etc.
        global $_PE;

        // Add version to the response. This can be overwritten later, if the
        //  URL of the request indicates so.
        $this->aResponse['version'] = $this->nVersion;

        // We'll start by processing the headers, to see what our input and output formats will be.
        $sAcceptsRaw = (empty($_SERVER['HTTP_ACCEPT'])? '' : $_SERVER['HTTP_ACCEPT']);
        $aAcceptsRaw = explode(',', $sAcceptsRaw);
        $aAccepts = array();

        foreach ($aAcceptsRaw as $sAcceptRaw) {
            // Split the optional quality separator off. We're currently
            //  ignoring it; if it's not present, this is a preferred output
            //  format, if it is present and lower than 1, it's less preferred.
            // Also see https://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html.
            // We are deciding for the client.
            $aAccept = explode(';', $sAcceptRaw);
            if (in_array($aAccept[0], $this->aFormatsAccepted)) {
                $aAccepts[] = $aAccept[0];
            }
        }

        if (!$aAccepts && $sAcceptsRaw) {
            // Client requested a format, but all formats requested are rejected
            //  and client didn't add */* as an option. So, we complain.
            $this->sFormatOutput = $this->aFormatsAccepted[0]; // Pick our default output.
            $this->aResponse['errors'][] = 'The format you requested is not available. Pick from ' . implode(', ', $this->aFormatsAccepted) . '.';
            $this->sendHeader(406, true); // Send 406 Not Acceptable, print response, and quit.

        } elseif ($aAccepts) {
            // Client provided request, and we can match.
            // We'll loop through our preferred formats, and pick what works best with the request.
            if (in_array('*/*', $aAccepts)) {
                // Client is OK with everything.
                $this->sFormatOutput = $this->aFormatsAccepted[0]; // Pick our default output.
            } else {
                foreach ($this->aFormatsAccepted as $sFormat) {
                    // Take the prefix, as we'll accept 'type/*' as well.
                    $sType = substr($sFormat, 0, strpos($sFormat . '/', '/'));
                    if (in_array($sFormat, $aAccepts) || in_array($sType . '/*', $aAccepts)) {
                        // Direct match or match on type.
                        $this->sFormatOutput = $sFormat;
                        break;
                    }
                }
            }

        } else {
            // Client didn't bother to request anything.
            $this->sFormatOutput = $this->aFormatsAccepted[0]; // Pick our default output.
        }





        // Parse the URL, to see what we're requested to do.
        // To prevent notices.
        $_PE = array_pad($_PE, 5, '');

        // $_PE[0] should always be API.
        if (strtolower($_PE[0]) != 'api') {
            $this->aResponse['errors'][] = 'Could not parse requested URL.';
            $this->sendHeader(400, true); // Send 400 Bad Request, print response, and quit.
        }

        // Check if we're using the old style LOVD2-style API or not.
        if (in_array($_PE[1], array('rest', 'rest.php'))) {
            // Yes, we are...
            $this->aResponse['version'] = 0;
            // This API also ignores the Accept header.
            $this->sFormatOutput = 'text/plain';

            // Parse URL to see what we need to do.
            list(,,
                $this->sResource, // 2
                $this->sGene,     // 3
                $this->nID) = $_PE;

            // We allow GET or HEAD, and POST only in case we're fetching frequencies.
            if (!GET && !HEAD && !(POST && $this->sResource == 'get_frequencies')) {
                // Will only allow GET and HEAD.
                // HEAD is necessary for the NCBI sequence viewer, which uses
                //  HEAD to first check if the BED file is available.
                // $this->aResponse['errors'][] = 'Method not allowed here.';
                // $this->sendHeader(405, true); // Send 405 Method Not Allowed, print response, and quit.
                // This API is LOVD2-style and shouldn't have their output changed now that we're more advanced.
                header('HTTP/1.0 501 Not Implemented');
                exit;
            }

            if (!$this->sResource) { // No data type given.
                header('HTTP/1.0 400 Bad Request');
                die('Too few parameters.');
            } elseif (!in_array($this->sResource, array('variants', 'genes', 'get_frequencies'))) { // Wrong data type given.
                header('HTTP/1.0 400 Bad Request');
                die('Requested data type not known.');
            } elseif ($this->sResource == 'variants' && !$this->sGene) { // Variants, but no gene selected.
                header('HTTP/1.0 400 Bad Request');
                die('Too few parameters.');
            }

        } else {
            // This is the new LOVD3-style API.
            // URLs can be with or without version.
            $aURLElements = $_PE;
            array_shift($aURLElements); // We take "api" off.

            if (preg_match('/^v([0-9]+)$/', $aURLElements[0], $aRegs)) {
                // We received version in URL.
                // Version must be larger than zero, and no greater than the currently configured version.
                if (!$aRegs[1] || $aRegs[1] > $this->nVersion) {
                    $this->aResponse['errors'][] = 'Requested version is unavailable.';
                    $this->sendHeader(400, true); // Send 400 Bad Request, print response, and quit.
                } else {
                    $this->nVersion = (int) $aRegs[1];
                }
                array_shift($aURLElements); // Take the version off.
            }
            $this->aResponse['version'] = $this->nVersion;

            // Next, should be resource.
            $this->sResource = array_shift($aURLElements);
            if (!isset($this->aResourcesSupported[$this->sResource])) {
                $this->aResponse['errors'][] = 'Requested resource is unknown.';
                $this->sendHeader(400, true); // Send 400 Bad Request, print response, and quit.
            }

            // Additional requirement; request APIs don't allow text/plain.
            if ($this->sResource != 'submissions' && $this->sFormatOutput == 'text/plain') {
                $this->aFormatsAccepted = array_filter($this->aFormatsAccepted, function ($sValue) {
                    return (preg_match('/^(application|\*)\//', $sValue));
                });
                $this->aResponse['errors'][] = 'The format you requested is not available for this resource. Pick from ' . implode(', ', $this->aFormatsAccepted) . '.';
                $this->sendHeader(406, true); // Send 406 Not Acceptable, print response, and quit.
            }

            if ($this->sResource == 'submissions') {
                // From here, it's optional.
                $this->nID = array_shift($aURLElements);

                // Rest of the URL should be empty at this point.
                if (implode('', $aURLElements)) {
                    // URL still had more data. At this point, that can't be right.
                    $this->aResponse['errors'][] = 'Could not parse requested URL.';
                    $this->sendHeader(400, true); // Send 400 Bad Request, print response, and quit.
                }

            } elseif ($this->sResource == 'ga4gh') {
                // GA4GH only available from v2.
                if ($this->nVersion < 2) {
                    $this->aResponse['errors'][] = 'GA4GH data connect is available only from LOVD API version 2 and up.' . "\n" .
                        'Please repeat your call, requesting a higher API version.';
                    $this->sendHeader(400, true); // Send 400 Bad Request, print response, and quit.
                }
            }

            // Verify method. This depends on the resource.
            if (!in_array($_SERVER['REQUEST_METHOD'], $this->aResourcesSupported[$this->sResource])) {
                $this->aResponse['errors'][] = 'Method not allowed here. Options: ' . implode(', ', $this->aResourcesSupported[$this->sResource]) . '.';
                $this->sendHeader(405, true); // Send 405 Method Not Allowed, print response, and quit.
            }

            // The combination of POST with an ID can't work.
            if (POST && $this->nID !== '') {
                // Remove POST from options, before we mention it.
                // FIXME: Yes, this currently means there are no methods left...
                unset($this->aResourcesSupported[$this->sResource][array_search('POST', $this->aResourcesSupported[$this->sResource])]);
                $this->aResponse['errors'][] = 'Method not allowed here. Options: ' . implode(', ', $this->aResourcesSupported[$this->sResource]) . '.';
                $this->sendHeader(405, true); // Send 405 Method Not Allowed, print response, and quit.
            }

            // If we're here, the API regarded the call acceptable, and assigned
            //  an API version higher than 0 (the LOVD2-style API).
            // If we're at version 1 or higher, let this new API handle it.
            // Since each method requires very specific code, the methods are
            //  handled separately.
            $bReturn = null;
            if (GET) {
                $bReturn = $this->processGET($aURLElements);
            } elseif (HEAD) {
                $bReturn = $this->processHEAD($aURLElements);
            } elseif (POST) {
                $bReturn = $this->processPOST();
            }

            // $bReturn is false on failure, true on success, or void otherwise.
            if ($bReturn === false) {
                // Some failure.
                if (!$this->nHTTPStatus || !$this->aResponse['errors']) {
                    // We somehow didn't receive a status, or no error message.
                    // This is failure on our side, and we will return a HTTP 500.
                    $this->aResponse['errors'][] = 'Request not handled well by any handler.';
                    $this->sendHeader(500, true); // Send 500 Internal Server Error, print response, and quit.
                } else {
                    // Failure, error message already defined by handler.
                    $this->sendHeader($this->nHTTPStatus, true); // Send HTTP status code, print response, and quit.
                }

            } elseif ($bReturn) {
                // Success!
                // If we have not HTTP status, we'll give 200.
                if (!$this->nHTTPStatus) {
                    $this->nHTTPStatus = 200;
                }
                $this->sendHeader($this->nHTTPStatus, true); // Send HTTP status code, print response, and quit.

            } else {
                // Failure, but not false. Error in handler.
                $this->aResponse['errors'][] = 'Request not picked up by any handler.';
                $this->sendHeader(500, true); // Send 500 Internal Server Error, print response, and quit.
            }
        }
    }





    protected function formatReponse ()
    {
        // Formats the response according to the currently configured output
        //  format. Currently supported are text/plain and application/json.

        $sResponse = '';
        if ($this->sFormatOutput == 'text/plain') {
            foreach ($this->aResponse as $sKey => $Value) {
                if ($Value) {
                    $sResponse .= $sKey . ': ';
                    if (is_array($Value)) {
                        foreach ($Value as $Item) {
                            // We don't support further layers (that may exist in data).
                            if (is_array($Item)) {
                                $Item = 'Array';
                            }
                            $sResponse .= "\n" . '  ' . $Item;
                        }
                    } else {
                        $sResponse .= $Value;
                    }
                    $sResponse .= "\n";
                }
            }
        } else {
            // Default: application/json.
            $bPrettyPrint = (PHP_VERSION_ID >= 50400
                && memory_get_usage() < 10000000
                && (empty($this->aResponse['data']) || count($this->aResponse['data']) <= 10));
            $sResponse = json_encode($this->aResponse, ($bPrettyPrint? JSON_PRETTY_PRINT : 0));
        }

        return $sResponse;
    }





    public function jsonDecode ($sInput)
    {
        // Attempts to decode the given JSON string, and handles any error.
        // Returns the array if successfully decoded, but throws any errors
        //  directly to the output.

        $aJSONErrors = array(
            JSON_ERROR_DEPTH => 'The maximum stack depth has been exceeded',
            JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON',
            JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded',
            JSON_ERROR_SYNTAX => 'Syntax error',
        );
        if (PHP_VERSION_ID >= 50303) {
            $aJSONErrors[JSON_ERROR_UTF8] = 'Malformed UTF-8 characters, possibly incorrectly encoded';
            if (PHP_VERSION_ID >= 50500) {
                $aJSONErrors[JSON_ERROR_RECURSION] = 'One or more recursive references in the value to be encoded';
                $aJSONErrors[JSON_ERROR_INF_OR_NAN] = 'One or more NAN or INF values in the value to be encoded';
                $aJSONErrors[JSON_ERROR_UNSUPPORTED_TYPE] = 'A value of a type that cannot be encoded was given';
            } else {
                // This makes sure they can be referenced, but can never occur.
                define('JSON_ERROR_RECURSION', 0);
                define('JSON_ERROR_INF_OR_NAN', 0);
                define('JSON_ERROR_UNSUPPORTED_TYPE', 0);
            }
        } else {
            // This makes sure they can be referenced, but can never occur.
            define('JSON_ERROR_UTF8', 0);
        }

        // Attempt to decode.
        $aInput = json_decode($sInput, true);

        // If not successful, try if a non-UTF8 string is the error.
        if ($aInput === NULL && json_last_error() == JSON_ERROR_UTF8) {
            // Encode to UTF8, and try again.
            $aInput = json_decode(utf8_encode($sInput), true);
        }

        if ($aInput === NULL) {
            // Handle errors.
            $this->aResponse['errors'][] = 'Error parsing JSON input. Error: ' . $aJSONErrors[json_last_error()] . '.';
            $this->nHTTPStatus = 400; // Send 400 Bad Request.
            return false;
        }

        // If we're still here, we have properly decoded data.
        return $aInput;
    }





    private function processGET ($aURLElements, $bReturnBody = true)
    {
        // Processes the GET calls to the API.

        // Currently only handling the 'ga4gh' resource, for GA4GH Data Connect.
        if ($this->sResource == 'ga4gh') {
            require_once 'class/api.ga4gh.php';
            $o = new LOVD_API_GA4GH($this);
            // This should process the request, return false on failure,
            //  true on success, and void otherwise (bugs).
            return $o->processGET($aURLElements, $bReturnBody);
        }
    }





    private function processHEAD ($aURLElements)
    {
        // Processes the HEAD calls to the API.
        // Even though HEAD is often not implemented, it should return the same
        //  headers as GET does. So basically, it should do all checks.

        return $this->processGET($aURLElements, false);
    }





    private function processPOST ()
    {
        // Processes the POST calls to the API.

        // Currently only handling the 'submission' resource, which receives a
        //  VarioML JSON file.
        if ($this->sResource == 'submissions') {
            require_once 'class/api.submissions.php';
            $o = new LOVD_API_Submissions($this);
            // This should process the request, return false on failure,
            //  true on success, and void otherwise (bugs).
            return $o->processPOST();
        }
    }





    public function sendHeader ($nStatus, $bHalt = false)
    {
        // Sends the HTTP header as requested, and optionally halts. If it does,
        //  it will send the response as well if we're not using HEAD.
        global $_SETT;

        // Response header...
        header('HTTP/1.0 ' . $nStatus, true, $nStatus);
        // Add the Location header, if needed.
        if ($nStatus == 302 && substr($this->aResponse['messages'][0], 0, 9) == 'Location:') {
            header($this->aResponse['messages'][0]);
        }
        // Add the WWW-Authenticate header, if needed.
        if ($nStatus == 401) {
            header('WWW-Authenticate: ' .
                ($this->sResource == 'submissions'? 'LOVDAuthToken' : 'Bearer') .
                ' realm="LOVD ' . $_SETT['system']['version'] . ' API. See the LOVD documentation on how to get access."' .
                // We're guessing here that we got an invalid token if
                //  we throw a 401 with an Authorization already sent.
                // It would be a better solution to have these headers set by the API?
                ($this->sResource == 'submissions' || !in_array('Authorization', array_keys(getallheaders()))? '' : ', error="invalid_token"')
            );
        }
        // Add the Allow header, if needed.
        if ($nStatus == 405 && $this->sResource && isset($this->aResourcesSupported[$this->sResource])) {
            header('Allow: ' . implode(', ', $this->aResourcesSupported[$this->sResource]));
        }
        // Content type...
        header('Content-type: ' . $this->sFormatOutput . '; charset=UTF-8');
        // Other headers...
        foreach ($this->aHTTPHeaders as $sHeader => $sContent) {
            header($sHeader . ': ' . $sContent);
        }
        if ($bHalt) {
            if (!HEAD) {
                print($this->formatReponse() . "\n");
            }
            exit;
        }

        return true;
    }
}
?>
