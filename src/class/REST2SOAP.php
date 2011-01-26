<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-01-06
 * Modified    : 2011-01-26
 * For LOVD    : 3.0-pre-15
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

class REST2SOAP {
    // This class provides basic functionality for the communication between REST and SOAP webservices.
    var $sSoapURL = '';
    
    function moduleCall ($sModuleName, $aArgs = array())
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
        // Parse output
        $sOutputSOAP = preg_replace(array('/>\s+/', '/\s+</'), array('>', '<'), implode('', $aOutputSOAP));
        $aOutput = $this->parseOutput($sOutputSOAP);
        // Check output
        return $this->checkOutput($aOutput);
    }



    function generateInputXML ($sModuleName, $aArgs)
    {
        // Generate a XML file to send to the SOAP webservice 
        $sXML = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
                '<SOAP-ENV:Envelope' . "\n" .
                'xmlns:ns0="http://schemas.xmlsoap.org/soap/envelope/"' . "\n" .
                'xmlns:ns1="http://mutalyzer.nl/2.0/services"' . "\n" .
                'xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">' . "\n" .
                '   <SOAP-ENV:Header/>' . "\n" .
                '   <ns0:Body>' . "\n" .
                '      <ns1:' . $sModuleName. '>' . "\n";

        foreach ($aArgs as $key => $value) {  
            if (is_int($value)) {
                $sType = 'int';
            } else {
                $sType = 'string';
            }
            $sArg = '         <ns1:' . $key . '>' . $value . '</ns1:' . $key . '>' . "\n";
            $sXML = $sXML . $sArg;
        }

        $sXML = $sXML . '      </ns1:' . $sModuleName . '>' . "\n" .
                '   </ns0:Body>' . "\n" .
                '</SOAP-ENV:Envelope>';

        return $sXML;
    }



    function parseOutput ($sOutputSOAP)
    {
        // Parse the output XML given by the SOAP webservice.
        preg_match_all('/(>([^<]+)<)+/', $sOutputSOAP, $aMatches);
        $aResult = $aMatches[2];

        return $aResult;
    }



    function checkOutput ($aOutput)
    {
        // Check for empty return array or SOAP error messages and relay them to the user
        // and logging them.
        if (empty($aOutput)) {
            return 'Empty array returned from SOAP';
        } else if ($aOutput[0] == 'senv:EARG' || $aOutput[0] == 'senv:Client' || $aOutput[0] == 'senv:Server') {
            return $aOutput[0] . ' - ' . str_replace("{http://mutalyzer.nl/2.0/services}", "", $aOutput[1]);
        } else {
            return $aOutput;
        }
    }
}
?>