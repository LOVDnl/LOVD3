<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-11-22
 * Modified    : 2016-11-22
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
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



class LOVD_API {
    // This class defines the LOVD API object, handling URL parsing and general
    //  handling of headers.

    protected $nVersion = 1;  // The API version. 0 for the LOVD2-style API. Higher versions are for the LOVD3-style REST API.
    public $sMethod = '';     // The used method (GET, POST, PUT, DELETE).
    public $sResource = '';   // The requested resource.
    public $nID = '';         // The ID of the requested resource.
    public $sGene = '';       // The LOVD2-style API often has the gene symbol in the URL, since it's gene-specific.

    protected $aAcceptedOutput = array(); // Parsed array of accepted outputs, taken from the Accept header.
    protected $sFormatInput = '';         // The input format.
    protected $sFormatOutput = '';        // The output format, may be a decision based on the request.

    protected $aResponse = array( // The standard response body.
        'version' => '',
        'messages' => array(),
        'warnings' => array(),
        'errors' => array(),
        'data' => array(),
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

        // These formats are interesting:
        $aFormatsAccepted = array(
            'application/json',
            'application/*',
            'text/plain',
            'text/bed',
            'text/*',
            '*/*',
        );

        foreach ($aAcceptsRaw as $nKey => $sAcceptRaw) {
            // Split the optional quality separator off. We're currently
            //  ignoring it; if it's not present, this is a preferred output
            //  format, if it is present and lower than 1, it's less preferred.
            // Also see https://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html.
            // We are deciding for the client.
            $aAccept = explode(';', $sAcceptRaw);
            if (in_array($aAccept[0], $aFormatsAccepted)) {
                $aAccepts[] = $aAccept[0];
            }
        }

        if (!$aAccepts && $sAcceptsRaw) {
            // Client requested a format, but all formats requested are rejected
            //  and client didn't add */* as an option. So, we complain.
            $this->sFormatOutput = $aFormatsAccepted[0]; // Pick our default output.
            $this->aResponse['errors'][] = 'The format you requested is not available. Pick from ' . implode(', ', $aFormatsAccepted);
            $this->sendHeader(406, true); // Send 406 Not Acceptable, print response, and quit.

        } elseif ($aAccepts) {
            // Client provided request, and we can match.
            // We'll loop through our preferred formats, and pick what works best with the request.
            if (in_array('*/*', $aAccepts)) {
                // Client is OK with everything.
                $this->sFormatOutput = $aFormatsAccepted[0]; // Pick our default output.
            } else {
                foreach ($aFormatsAccepted as $sFormat) {
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
            $this->sFormatOutput = $aFormatsAccepted[0]; // Pick our default output.
        }





        // Parse the URL, to see what we're requested to do.
        // To prevent notices.
        $_PE = array_pad($_PE, 5, '');

        // $_PE[0] should always be API.
        if (strtolower($_PE[0]) != 'api') {
            $this->aResponse['errors'][] = 'Could not parse requested URL.';
            $this->sendHeader(400, true); // Send 400 Bad Request, print response, and quit.
        }

        // Check if we're using the old style LOVD2-API or not.
        if (in_array($_PE[1], array('rest', 'rest.php'))) {
            // Yes, we are...
            $this->aResponse['version'] = 0;
            // This API also ignores the Accept header.
            $this->sFormatOutput = 'text/plain';
            // And, we only allow GET.
            if ($_SERVER['REQUEST_METHOD'] != 'GET') {
                // Will only allow GET.
                // $this->aResponse['errors'][] = 'Method not allowed.';
                // $this->sendHeader(501, true); // Send 501 Not Implemented, print response, and quit.
                // This API is LOVD2-style and shouldn't have their output changed now that we're more advanced.
                header('HTTP/1.0 501 Not Implemented');
                exit;
            }

            // Parse URL to see what we need to do.
            list(,,
                $this->sResource, // 2
                $this->sGene,     // 3
                $this->nID) = $_PE;

            if (!$this->sResource) { // No data type given.
                header('HTTP/1.0 400 Bad Request');
                die('Too few parameters.');
            } elseif (!in_array($this->sResource, array('variants', 'genes'))) { // Wrong data type given.
                header('HTTP/1.0 400 Bad Request');
                die('Requested data type not known.');
            } elseif ($this->sResource == 'variants' && !$this->sGene) { // Variants, but no gene selected.
                header('HTTP/1.0 400 Bad Request');
                die('Too few parameters.');
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
            $sResponse = json_encode($this->aResponse, JSON_PRETTY_PRINT);
        }

        return $sResponse;
    }





    public function sendHeader ($nStatus, $bHalt = false)
    {
        // Sends the HTTP header as requested, and optionally halts. If it does,
        //  it will send the response as well.

        // Response header...
        header('HTTP/1.0 ' . $nStatus, true, $nStatus);
        // Content type...
        header('Content-type: ' . $this->sFormatOutput . '; charset=UTF-8');
        if ($bHalt) {
            print($this->formatReponse() . "\n");
            exit;
        }

        return true;
    }
}
?>
