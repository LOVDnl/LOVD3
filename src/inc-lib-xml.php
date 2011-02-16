<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-02-15
 * Modified    : 2011-02-16
 * For LOVD    : 3.0-pre-17
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               
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

function lovd_xml2array($sXml = '', $nSkipTags = 0, $sPrefixSeperator = '')
{
    /*
        Converts a XML file into an array

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
                    <s0:favoriteToy>Yarn</s0:FavoriteToy>
                    <s0:favoriteToy>Toy Mouse</s0:FavoriteToy>
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
    $parser = xml_parser_create();
    xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);                             // Don't use case-folding
    xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 0);                               // Don't skip tags that contain only whitespaces
    if ($sPrefixSeperator != '') {
        if (is_int($sPrefixSeperator)) {
            xml_parser_set_option($parser, XML_OPTION_SKIP_TAGSTART, $sPrefixSeperator);    // Skips the first n characters from the tag names
        } elseif (is_string($sPrefixSeperator)) {
            $sXml = preg_replace('/(<\/?)\w+\\' . $sPrefixSeperator . '/', '$1', $sXml);    // Skips all characters until the prefix seperator
        } else{
            echo 'Please provide valid arguments, $sPrefixSeperator should be either and integer or a string!';
            exit;
        }
    }
    xml_parse_into_struct($parser, $sXml, $tags);
    xml_parser_free($parser);
   
    $elements = array();
    $stack = array();
    $nLevel = 0;
    // Skips the first $skip amount of tags found
    for ($i = 0; $i < $nSkipTags; $i++) {
        array_shift($tags);
        array_pop($tags);
    }

    foreach ($tags as $tag)
    {
        if ($tag['type'] == "complete" || $tag['type'] == "open")
        {
            // Check if the tag already exists in this level, if so the next item is appended to the array on the next index ($nLevel)
            if (isset($elements[$tag['tag']])) {
                $nLevel = count($elements[$tag['tag']]);
            } else {
                $nLevel = 0;
            }

            $elements[$tag['tag']][$nLevel] = array('c' => array(), 'a' => array(), 'v' => '');
            (isset($tag['attributes'])? $elements[$tag['tag']][$nLevel]['a'] = $tag['attributes'] : false);

            if ($tag['type'] == "open")
            {
                # Push new element into the array
                $stack[count($stack)] = &$elements;
                $elements = &$elements[$tag['tag']][$nLevel]['c'];
            } elseif ($tag['type'] == "complete" && isset($tag['value'])) {
                $elements[$tag['tag']][$nLevel]['v'] = $tag['value'];
            }
        }

        if ($tag['type'] == "close")
        {
            # Pop last element from the array
            $elements = &$stack[count($stack) - 1];
            unset($stack[count($stack) - 1]);
        }
    }
    return $elements;
}

?>
