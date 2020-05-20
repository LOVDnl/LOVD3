<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-05-20
 * Modified    : 2020-05-20
 * For LOVD    : 3.0-24
 *
 * Copyright   : 2004-2020 Leiden University Medical Center; http://www.LUMC.nl/
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

require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;

class SubmissionAPITest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testSetUp ()
    {
        // A normal setUp() runs for every test in this file. We only need this once,
        //  so we disguise this setUp() as a test that we depend on just once.
        $this->driver->get(ROOT_URL . '/src/genes/IVD');
        $sBody = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();
        if (preg_match('/LOVD was not installed yet/', $sBody)) {
            $this->markTestSkipped('LOVD was not installed yet.');
        }
        if (preg_match('/No such ID!/', $sBody)) {
            $this->markTestSkipped('Gene does not exist yet.');
        }
        if (!$this->isElementPresent(WebDriverBy::xpath('//a[contains(@href, "users/0000")]/b[text()="Your account"]'))) {
            $this->markTestSkipped('User was not authorized.');
        }
    }





    /**
     * @depends testSetUp
     */
    public function testFailSubmitWrongLSDBID ()
    {
        $sResult = file_get_contents(
            ROOT_URL . '/src/api/submissions', false, stream_context_create(
                array(
                    'http' => array(
                        'method'  => 'POST',
                        'header'  => 'Content-type: application/x-www-form-urlencoded',
                        'content' => file_get_contents(
                            ROOT_PATH . '../tests/test_data_files/submission_api_request_content.json'
                        ),
                        'ignore_errors' => true,
                    ))));
        $aResult = json_decode($sResult, true);

        $this->assertContains('422 Unprocessable Entity', $http_response_header[0]);
        $this->assertEquals(array(), $aResult['messages']);
        $this->assertContains('VarioML error: LSDB ID in file does not match this LSDB.',
            implode(';', $aResult['errors']));
    }





    /**
     * @depends testSetUp
     */
    public function testCreateToken ()
    {
        $this->driver->findElement(WebDriverBy::linkText('Your account'))->click();
        $this->driver->findElement(WebDriverBy::linkText('Show / More information'))->click();
        $this->waitForElement(WebDriverBy::xpath('//button[.="Create new token"]'));
        $this->driver->findElement(WebDriverBy::xpath('//button[.="Create new token"]'))->click();

        $this->waitForElement(WebDriverBy::name('auth_token_expires'));
        $this->driver->findElement(WebDriverBy::xpath('//button[.="Create new token"]'))->click();

        $this->waitForElement(WebDriverBy::xpath('//div[text()="Token created successfully!"]'));
    }
}
?>
