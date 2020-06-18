<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2017-06-27
 * Modified    : 2020-06-18
 * For LOVD    : 3.0-24
 *
 * Copyright   : 2004-2020 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : M. Kroon <m.kroon@lumc.nl>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

class ConvertLOVD2ExportIVDTest extends LOVDSeleniumWebdriverBaseTestCase
{
    protected function setUp ()
    {
        parent::setUp();
        $this->driver->get(ROOT_URL . '/src/genes/IVD');
        $sBody = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();
        if (preg_match('/LOVD was not installed yet/', $sBody)) {
            $this->markTestSkipped('LOVD was not installed yet.');
        }
        if (preg_match('/No such ID!/', $sBody)) {
            $this->markTestSkipped('Gene does not exist yet.');
        }
    }





    public function test ()
    {
        // Test functionality of conversion script for LOVD2 export to LOVD3 import formats.
        $this->driver->get(ROOT_URL . '/src/scripts/convert_lovd2.php');
        $this->driver->findElement(WebDriverBy::xpath(
            '//table[@class="data"]//tr[td[text()="IVD"]]/td[contains(text(), "NM_002225.")]'))->click();
        // If IVD's NM_002225 is not going to be transcript 1 anymore, the verification of the data file will fail anyway.
        // If we want to cater for that possibility, we'll need to be more dynamic about verifying the contents of the output file.
        $this->assertEquals('00000001', $this->driver->findElement(WebDriverBy::name('transcriptid'))->getAttribute('value'));
        $this->enterValue('LOVD2_export', ROOT_PATH .
            '../tests/test_data_files/LOVD2_conversion/test_lovd2export.txt');
        $this->enterValue('submitterid_translation', file_get_contents(ROOT_PATH .
            '../tests/test_data_files/LOVD2_conversion/test_submitter_translation.txt'));

        // Start conversion.
        $this->driver->findElement(WebDriverBy::xpath(
            '//input[@value="Generate LOVD3 import file"]'))->click();
        $this->waitForElement(WebDriverBy::id('copybutton'));

        // Compare results.
        $this->assertEquals(
            rtrim(file_get_contents(ROOT_PATH . '../tests/test_data_files/LOVD2_conversion/result_conversion_log.txt')),
            $this->driver->findElement(WebDriverBy::id('header_log'))->getText()
        );

        // Skip first line in output, which holds the LOVD version.
        $aOutput = explode("\n", $this->driver->findElement(
            WebDriverBy::id('conversion_output'))->getAttribute('value'));
        array_shift($aOutput);
        $sOutputTail = implode("\n", $aOutput);
        $this->assertEquals(file_get_contents(ROOT_PATH .
            '../tests/test_data_files/LOVD2_conversion/result_output.txt'), $sOutputTail);
    }
}
?>
