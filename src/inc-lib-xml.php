<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-02-15
 * Modified    : 2012-05-29
 * For LOVD    : 3.0-beta-06
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Jerry Hoogenboom <J.Hoogenboom@LUMC.nl>
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

if (preg_match('/^(\d+)([KM])/', ini_get('memory_limit'), $aMatches) && ($aMatches[2] == 'K' || $aMatches[1] < 48)) {
    ini_set('memory_limit', '48M');
}

function lovd_xml2array ($sXml = '', $nSkipTags = 0, $sPrefixSeperator = '')
{
    /*
        Converts an XML file into an array

        Arguments:
            -$xml                   A string containing the xml, can be with or without newlines/whitespaces
            -$nSkipTags             A number that specifies how many tags at the beginning of the xml you want to skip
            -$sPrefixSeperator      An array of arguments that specifies if and how you want to skip leading characters from each tag name
                -String                 All characters until this character will be skipped(Example: ':', will change <s0:Animal> into <Animal>)
                -Integer                Amount of characters that will be skipped from the beginning(Example: 5, will change <helloWorld> into <World>) 

        Input:
            ###Example###   lovd_xml2array($sXml, $nSkipTags = 0, $sPrefixSeperator = ':')
            <s0:Animal legs="4" type="mammal">
                <s0:cat>
                    <s0:hairLength>20 mm</s0:hairLength>
                    <s0:favoriteToy>Yarn</s0:favoriteToy>
                    <s0:favoriteToy>Toy Mouse</s0:favoriteToy>
                </s0:cat>
            </s0:Animal>
            ###Example###
        
        Output:
            ###Example###  c: children, a: attributes, v: value
            array(
                  ["Animal"] => array(
                              ["0"] => array(
                                     ["c"] => array(                                        
                                            ["Cat"] => array(
                                                     ["0"] => array(
                                                            ["c"] => array(
                                                                   ["hairLength"] => array(
                                                                                  ["0"] => array (
                                                                                          ["c"] => array(),
                                                                                          ["a"] => array(),
                                                                                          ["v"] => "20 mm"
                                                                                  )
                                                                   ),
                                                                   ["favoriteToy"] => array(
                                                                                  ["0"] => array(
                                                                                          ["c"] => array(),
                                                                                          ["a"] => array(),
                                                                                          ["v"] => "Yarn"
                                                                                  ),
                                                                                  ["1"] => array(
                                                                                          ["c"] => array(),
                                                                                          ["a"] => array(),
                                                                                          ["v"] => "Toy Mouse"
                                                                                  )
                                                                   )
                                                            ),
                                                            ["a"] => array(),
                                                            ["v"] => ""
                                                     )
                                            )
                                     ),
                                     ["a"] => array(
                                            ["legs"] => "4",
                                            ["type"] => "mammal"
                                     ),
                                     ["v"] => ""
                              )
                  )
            )
            ###Example###
    */
    if (!is_string($sXml) || empty($sXml)) {
        echo 'Please provide valid arguments, $sXml should be a string and should not be empty!';
        exit;
    } elseif (!is_int($nSkipTags)) {
        echo 'Please provide valid arguments, $nSkipTags should be an integer!';
        exit;
    }

    // Find out the input character encoding. The pattern matches any XML tag, with or without attributes, with attributes in any order (in $aMatches[0]).
    // Extracts encodings specified like: [ encoding=UTF-8], [ encoding="UTF-8"] or [ encoding='UTF-8'] (excluding the braces; always in $aMatches[1]).
    // We'll try to parse the file in the specified encoding, if it fails (because the source specified the wrong encoding) we try UTF-8, then ISO-8859-15.
    $aEncodings = array('UTF-8', 'ISO-8859-15');
    if (preg_match("/<\?xml(?:.*?(?: encoding=(?:\"([^\"]+)\"|'([^']+)'|(\S+)).*?)?)?\?>/i", $sXml, $aMatches) && (!empty($aMatches[1]) || !empty($aMatches[2]) || !empty($aMatches[3]))) {
        // We don't want to try the same encoding twice, so if it's ISO-8859-X or UTF-8, don't retry that one.
        $sEncoding = strtoupper(implode('', array_slice($aMatches, 1)));
        if ($sEncoding == 'UTF-8') {
            array_shift($aEncodings);
        } elseif (preg_match('/^ISO-8859-(?:[1-9]|1[013456])$/', $sEncoding)) {
            array_pop($aEncodings);
        }
    }
    
    // If no encoding is specified, we should specify it now!
    else {
        // We'll try UTF-8 first. If UTF-8 fails, retry with ISO-8859-15.
        $sXml = preg_replace("/(?<=<\?xml)(?=.*?\?>)/", ' encoding="' . array_shift($aEncodings) . '"', $sXml);
    }
    
    // Trying at most 2 times (once with the original encoding, once with mb_detect_encoding()'s suggestion).
    $i = 2;
    do {
        $rParser = xml_parser_create();
        xml_parser_set_option($rParser, XML_OPTION_CASE_FOLDING, 0);                             // Don't use case-folding
        xml_parser_set_option($rParser, XML_OPTION_SKIP_WHITE, 0);                               // Don't skip tags that contain only whitespaces
        if ($sPrefixSeperator != '') {
            if (is_int($sPrefixSeperator)) {
                xml_parser_set_option($rParser, XML_OPTION_SKIP_TAGSTART, $sPrefixSeperator);    // Skips the first n characters from the tag names
            } elseif (is_string($sPrefixSeperator)) {
                $sXml = preg_replace('/(<\/?)\w+\\' . $sPrefixSeperator . '/', '$1', $sXml);     // Skips all characters until the prefix seperator
            } else{
                echo 'Please provide valid arguments, $sPrefixSeperator should be either an integer or a string!';
                exit;
            }
        }
        xml_parse_into_struct($rParser, $sXml, $aTags);
        $nParserErrorCode = xml_get_error_code($rParser);
        xml_parser_free($rParser);

        // Check if parsing succeeded with this character encoding.
        if (!$nParserErrorCode) {
            break;
        }
        
        // Parsing failed. Detect alternative encoding.
        if (!$sEncoding = mb_detect_encoding($sXml, $aEncodings)) {
            exit('The provided XML document cannot be parsed because it contains invalid data.');
        }
        $sXml = preg_replace("/(?<= encoding=)(?:\"[^\"]+\"|'[^']+'|\S+)(?=.*?\?>)/i", '"' . $sEncoding . '"', $sXml);
    } while (-- $i);

    $aStructure = array();
    $aStack = array();
    $nIndex = 0;
    // Skips the first $skip amount of tags found
    for ($i = 0; $i < $nSkipTags; $i++) {
        array_shift($aTags);
        array_pop($aTags);
    }

    foreach ($aTags as $aTag)
    {
        if ($aTag['type'] == "complete" || $aTag['type'] == "open")
        {
            // Check if the tag already exists in this level, if so the next item is appended to the array on the next index ($nIndex)
            if (isset($aStructure[$aTag['tag']])) {
                $nIndex = count($aStructure[$aTag['tag']]);
            } else {
                $nIndex = 0;
            }

            $aStructure[$aTag['tag']][$nIndex] = array('c' => array(), 'a' => array(), 'v' => '');
            (isset($aTag['attributes'])? $aStructure[$aTag['tag']][$nIndex]['a'] = $aTag['attributes'] : false);

            if ($aTag['type'] == "open")
            {
                # Push new element into the array
                $aStack[count($aStack)] = &$aStructure;
                $aStructure = &$aStructure[$aTag['tag']][$nIndex]['c'];
            } elseif ($aTag['type'] == "complete" && isset($aTag['value'])) {
                $aStructure[$aTag['tag']][$nIndex]['v'] = $aTag['value'];
            }
        }

        if ($aTag['type'] == "close")
        {
            # Pop last element from the array
            $aStructure = &$aStack[count($aStack) - 1];
            unset($aStack[count($aStack) - 1]);
        }
    }
    return $aStructure;
}





function lovd_getElementFromArray ($sPath = '', $aArray = array(), $sType = '')
{
    // Designed to easily parse the array returned by lovd_xml2array() 
    // Example: "Animal/Cat/favoriteToy[1]" will result in "$aXML['Animal'][0]['c']['Cat'][0]['c']['favoriteToy'][1]"

    if (empty($aArray) || !is_array($aArray)) {
        return false;
    }
    $aStructure = $aArray;

    if (is_string($sPath) && strlen($sPath) > 0) {
        $aPath = explode("/", trim($sPath, '/'));
    
        foreach ($aPath as $sElement) {
            $nIndex = 0;
            $sName  = $sElement;
            if (preg_match("/\[(\d+)\]/", $sElement, $aMatches)) {
                $nIndex = intVal($aMatches[1]);
                $sName  = str_replace("[" . $nIndex . "]", "", $sElement);
            }

            if (!isset($aStructure[$sName][$nIndex]['c'])) {
                return false;
            }

            if ($sElement == end($aPath)) {
                if ($sType == '') {
                    return ($nIndex? $aStructure[$sName][$nIndex] : $aStructure[$sName]);
                } else {
                    return $aStructure[$sName][$nIndex][$sType];
                }
            } else {
                $aStructure = &$aStructure[$sName][$nIndex]['c'];
            }

        }
    } else {
        return false;
    }
    return false;
}





function lovd_getAllValuesFromArray ($sPath = '', $aArray = array())
{
    // Designed to easily parse the array returned by lovd_xml2array() 
    // Will loop through all elements(only current level of specified path) in $aArray and return their value if it is set
    
    if (!empty($sPath)) {
        $aArray = lovd_getElementFromArray($sPath, $aArray, 'c');
    } else { 
        if (empty($aArray) || !is_array($aArray)) {
            return false;
        }
    }
    $aValues = array();
    if ($aArray) {
        foreach ($aArray as $entity => $index) {
            foreach ($index as $elements) {
                $aValues[$entity][] = $elements['v'];
            } 
            if (count($aValues[$entity]) == 1) {
                $aValues[$entity] = $aValues[$entity][0];
            }
        }
        return $aValues;
    } else {
        return array();
    }
}





function lovd_getAllValuesFromSingleElement ($sPath = '', $aArray = array())
{
    // Designed to easily parse the array returned by lovd_xml2array() 
    // Will loop through the specified element in $aArray and return its values if they are set
    
    if (!empty($sPath)) {
        $aArray = lovd_getElementFromArray($sPath, $aArray, '');
    } else { 
        if (empty($aArray) || !is_array($aArray)) {
            return false;
        }
    }
    $aValues = array();
    if ($aArray) {
        foreach ($aArray as $index => $elements) {
            $aValues[$index] = $elements['v'];
        }
        return $aValues;
    } else {
        return array();
    }
}





function lovd_getValueFromElement ($sPath = '', $aArray = array())
{
    // Basically a redirect to lovd_getElementFromArray($sPath, $aArray, 'v'), but with less arguments
    
    if (!empty($sPath)) {
        return lovd_getElementFromArray($sPath, $aArray, 'v');
    } else { 
        if (empty($aArray) || !is_array($aArray)) {
            return false;
        }
    }
    return $aArray['v'];
}





function lovd_getAttributesFromElement ($sPath = '', $aArray = array())
{
    // Basically a redirect to lovd_getElementFromArray($sPath, $aArray, 'a'), but with less arguments
    
    if (!empty($sPath)) {
        return lovd_getElementFromArray($sPath, $aArray, 'a');
    } else { 
        if (empty($aArray) || !is_array($aArray)) {
            return false;
        }
    }
    return $aArray['a'];
}





function lovd_getChildFromElement ($sPath = '', $aArray = array())
{
    // Basically a redirect to lovd_getElementFromArray($sPath, $aArray, 'c'), but with less arguments
    
    if (!empty($sPath)) {
        return lovd_getElementFromArray($sPath, $aArray, 'c');
    } else { 
        if (empty($aArray) || !is_array($aArray)) {
            return false;
        }
    }
    return $aArray['c'];
}
?>
