<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-01-06
 * Modified    : 2011-01-06
 * For LOVD    : 3.0-pre-13
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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
 
function mutalyzer_SOAP_module_call($sModuleName, $aArgs) {
	
	$sInputXML = generate_input_XML($sModuleName, $aArgs);
	$sOutputXML = feed_input_to_SOAP($sInputXML);
	$aOutput = parse_output($sOutputXML);
	return $aOutput;
}

function generate_input_XML($sModuleName, $aArgs) {

	$sXML = '<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope
  SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"
  xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"
  xmlns:xsi="http://www.w3.org/1999/XMLSchema-instance"
  xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
  xmlns:xsd="http://www.w3.org/1999/XMLSchema">
  <SOAP-ENV:Body>
    <' . $sModuleName. ' SOAP-ENC:root="1">
';

foreach ($aArgs as $key => $value) {  
	
	if (is_int($value)) {
		$sType = "int";
	} else {
		$sType = "string";
	}
	$sArg = '      <' . $key . ' xsi:type="xsd:' . $sType . '">' . $value . '</' . $key . '>
';
	$sXML = $sXML . $sArg;
}

$sXML = $sXML . '    </' . $sModuleName . '>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>';

return $sXML;
}

function feed_input_to_SOAP($sInputXML) {
	
	//$aOutputSOAP = lovd_php_file("http://www.mutalyzer.nl/2.0/services", false, $sInputXML);
	$aOutputSOAP = lovd_php_file("http://10.160.8.105/mutalyzer2/services", false, $sInputXML);
	
	return $aOutputSOAP[0];
}

function parse_output($sOutputXML) {
	
	preg_match_all('/(>([^<]+)<)+/', $sOutputXML, $aMatches);
	$aResult = $aMatches[2];
	
	return $aResult;
}

?>