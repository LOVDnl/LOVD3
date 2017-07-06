<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2017-06-27
 * Modified    : 2017-07-06
 * For LOVD    : 3.0-20
 *
 * Copyright   : 2017 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : M. Kroon <m.kroon@lumc.nl>
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

require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class ConvertLOVD2ExportTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testConvertLOVD2Export()
    {
        // Test functionality of conversion script for LOVD2 export to LOVD3 import formats.

        $this->driver->get(ROOT_URL . '/src/scripts/convert_lovd2.php');

        $buttonLocator = WebDriverBy::xpath('//input[@value="Generate LOVD3 import file"]');
        $this->waitUntil(WebDriverExpectedCondition::presenceOfElementLocated($buttonLocator));

        $this->enterValue(WebDriverBy::name('transcriptid'), '1');
        $this->enterValue(WebDriverBy::name('LOVD2_export'), ROOT_PATH .
            '../tests/test_data_files/LOVD2_conversion/test_lovd2export.txt');

        $sSubmitterTransTable = file_get_contents(ROOT_PATH .
            '../tests/test_data_files/LOVD2_conversion/test_submitter_translation.txt');
        $this->enterValue(WebDriverBy::name('submitterid_translation'), $sSubmitterTransTable);

        // Start conversion.
        $this->driver->findElement($buttonLocator)->click();

        $copyButtonLocator = WebDriverBy::id('copybutton');
        $this->waitUntil(WebDriverExpectedCondition::presenceOfElementLocated($copyButtonLocator));

        // Compare generated output with expected output from file. (Skip first line in output)
        $sExpectedOutput = file_get_contents(ROOT_PATH .
            '../tests/test_data_files/LOVD2_conversion/test_output.txt');
        $outputArea = $this->driver->findElement(WebDriverBy::id('conversion_output'));
        $aOutput = explode("\n", $outputArea->getAttribute('value'));
        array_shift($aOutput); // Remove first line (holds LOVD version)
        $sOutputTail = join("\n", $aOutput);
        $this->assertEquals($sExpectedOutput, $sOutputTail);
    }
}