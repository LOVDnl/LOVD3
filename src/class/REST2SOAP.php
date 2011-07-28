<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-01-06
 * Modified    : 2011-04-14
 * For LOVD    : 3.0-pre-20
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

require_once ROOT_PATH . 'inc-lib-xml.php';


class REST2SOAP {
    // This class provides basic functionality for the communication between REST and SOAP webservices.
    var $sSoapURL = '';



    function REST2SOAP ($sURL)
    {
        $this->sSoapURL = $sURL;
    }





    function checkOutput ($sModuleName, $aOutput)
    {
        // Check for empty return array or SOAP error messages and relay them to the user
        // and logging them.
        if (isset($aOutput['Fault'])) {
            $aError = $aOutput['Fault'][0]['c'];
            return $aError['faultcode'][0]['v'] . ' - ' . $aError['faultstring'][0]['v'] . ($aError['faultactor'][0]['v'] != ''? ' - ' . $aError['faultactor'][0]['v'] : '');
        } elseif (isset($aOutput['html'])) {
            $aError = $aOutput['html'][0]['c']['body'][0]['c'];
            return $aError['h1'][0]['v'] . ' - ' . $aError['p'][0]['v'];
        } else {
            if (isset($aOutput[$sModuleName . 'Result'][0])) {
                $aOutput = $aOutput[$sModuleName . 'Result'][0];
                return (empty($aOutput['c'])? $aOutput['v'] : $aOutput['c']);
            } else {
                return $aOutput;
            }
        }
    }





    function generateInputXML ($sModuleName, $aArgs)
    {
        // Generate a XML file to send to the SOAP webservice 
        $sXML = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
                '<SOAP-ENV:Envelope' . "\n" .
                ' xmlns:ns0="http://schemas.xmlsoap.org/soap/envelope/"' . "\n" .
                ' xmlns:ns1="http://mutalyzer.nl/2.0/services"' . "\n" .
                ' xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">' . "\n" .
                '  <SOAP-ENV:Header/>' . "\n" .
                '  <ns0:Body>' . "\n" .
                '    <ns1:' . $sModuleName. '>' . "\n";

        foreach ($aArgs as $key => $value) {
            $sArg = '      <ns1:' . $key . '>' . trim($value) . '</ns1:' . $key . '>' . "\n";
            $sXML = $sXML . $sArg;
        }

        $sXML = $sXML . '    </ns1:' . $sModuleName . '>' . "\n" .
                '  </ns0:Body>' . "\n" .
                '</SOAP-ENV:Envelope>';

        return $sXML;
    }





    function moduleCall ($sModuleName, $aArgs = array(), $bDebug = false)
    {
        // Basic function for calling the SOAP webservice. This function calls all the other functions
        // sequentially to get the result from SOAP.
        if (!is_array($aArgs)) {
            return 'Arguments not an array';
        }
        // Generate XML
        $sInputXML = $this->generateInputXML($sModuleName, $aArgs);
        // Send XML to SOAP
        $aOutputSOAP = lovd_php_file($this->sSoapURL, false, $sInputXML);
        // Output debug values
        if ($bDebug) {
            return array('inputXML' => $sInputXML, 'outputXML' => implode("\n", $aOutputSOAP));
        }
        // Parse output
        $aOutput = $this->parseOutput($sModuleName, implode("\n", $aOutputSOAP));
        // Check output
        return $aOutput;
    }





    function parseOutput ($sModuleName, $sOutputSOAP)
    {
        // Parse the output XML given by the SOAP webservice.
        if (preg_match('/Fault/', $sOutputSOAP)) {
            $nSkipTags = 2;
        } else {
            if (preg_match('/Internal Server Error/', $sOutputSOAP)) {
                $nSkipTags = 0;
            } else {
                $nSkipTags = 3;
            }
        }
        $aOutput = lovd_xml2array($sOutputSOAP, $nSkipTags, $sPrefixSeperator = ':');
        if (!empty($aOutput)) {
            $aOutput = $this->checkOutput($sModuleName, $aOutput);
        }

        return $aOutput;
    }





    function soapError ($sModuleName, $aArgs, $sSOAPError, $bHalt = true)
    {
        // Provides a wrapper for the error message that is returned by SOAP
        // Derived from the lovd_queryError function in 'inc-lib-init.php'
        global $_AUTH;
        
        $sArgs = '';
        foreach ($aArgs as $key => $value) {
            $sArgs = $sArgs . "\t\t" . $key . " = \"" . $value . "\"\n";
        }
        
        // Format the error message.
        $sError = preg_replace('/^' . preg_quote(rtrim(lovd_getInstallURL(false), '/'), '/') . '/', '', $_SERVER['REQUEST_URI']) . ' returned error in module \'' . $sModuleName . '\'.' . "\n\n" .
                  'Arguments :' . "\n" .
                  $sArgs . "\n" .
                  'SOAP response :' . "\n" .
                  "\t\t" . str_replace("\n", "\n\t\t", $sSOAPError);

        // If the system needs to be halted, send it through to lovd_displayError() who will print it on the screen,
        // write it to the system log, and halt the system. Otherwise, just log it to the database.
        if ($bHalt) {
            return lovd_displayError('SOAP', $sError);
        } else {
            return lovd_writeLog('Error', 'SOAP', $sError);
        }
    }
}
?>