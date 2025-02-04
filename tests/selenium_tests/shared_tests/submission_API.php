<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-05-20
 * Modified    : 2024-05-23
 * For LOVD    : 3.0-30
 *
 * Copyright   : 2004-2024 Leiden University Medical Center; http://www.LUMC.nl/
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
        // To prevent a Risky test, we have to do at least one assertion.
        $this->assertEquals('', '');
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

        $this->assertEquals(array(), $aResult['messages']);
        $this->assertStringContainsString('VarioML error: LSDB ID in file does not match this LSDB.',
            implode(';', $aResult['errors']));
        $this->assertStringEndsWith ('422 Unprocessable Entity', $http_response_header[0]);
    }





    /**
     * @depends testSetUp
     */
    public function testCreateToken ()
    {
        // To prevent a Risky test, we have to do at least one assertion.
        $this->assertTrue($this->isElementPresent(WebDriverBy::linkText('Your account')));
        $this->driver->findElement(WebDriverBy::linkText('Your account'))->click();
        $this->driver->findElement(WebDriverBy::linkText('Show / More information'))->click();
        $this->waitForElement(WebDriverBy::xpath('//button[.="Create new token"]'));
        $this->driver->findElement(WebDriverBy::xpath('//button[.="Create new token"]'))->click();

        // Handle dialog, in case this test is repeated several times during testing.
        if ($this->isAlertPresent()) {
            $this->assertEquals('Are you sure you want to create a new token, invalidating the current token?',
                $this->getConfirmation());
            $this->chooseOkOnNextConfirmation();
        }

        $this->waitForElement(WebDriverBy::name('auth_token_expires'));
        $this->driver->findElement(WebDriverBy::xpath('//button[.="Create new token"]'))->click();

        $this->waitForElement(WebDriverBy::xpath('//div[text()="Token created successfully!"]'));
        $this->driver->findElement(WebDriverBy::xpath('//button[.="Back"]'))->click();

        // Fetch and store the LOVD ID and the user's Token.
        $sToken = $this->driver->findElement(WebDriverBy::xpath(
            '//div[@id="auth_token_dialog"]/pre'))->getText();
        // The LOVD ID is shown only to the Admin. To make this test work
        //  for any submitter, filter the ID out of the cookie data.
        // This requires some more effort, because we have a mix of old and new
        //  cookies. The difference shows when we log out and in again.
        $aCurrentSessionIDs = $this->getAllSessionIDs();
        if (count(array_unique($aCurrentSessionIDs)) == 1) {
            // Life is simple, there is only one session ID...
            $sLSDBID = current($aCurrentSessionIDs);
        } else {
            // Trigger new cookie to be added.
            $sCurrentUsername = $this->driver->findElement(WebDriverBy::xpath(
                '//table[@class="data"]//tr[th[text()="Username"]]/td'))->getText();
            $this->logout();
            $this->login($sCurrentUsername, 'test1234');
            $aSessionIDs = $this->getAllSessionIDs();
            fwrite(STDERR, PHP_EOL .
                'DEBUG:' . PHP_EOL .
                'IDs pre: ' . implode(';', $aCurrentSessionIDs) . PHP_EOL .
                'IDs post: ' . implode(';', $aSessionIDs) . PHP_EOL);
            // array_diff() doesn't work here, since values will match. The number
            //  of times we see these values, will not match.
            foreach ($aCurrentSessionIDs as $sValue) {
                unset($aSessionIDs[array_search($sValue, $aSessionIDs)]);
            }
            $sLSDBID = current($aSessionIDs);
        }

        // This is the only way in which data can be shared between tests.
        return array($sLSDBID, $sToken);
    }





    /**
     * @depends testCreateToken
     */
    public function testFailSubmitAuthenticationDenied ($aVariables)
    {
        list($sLSDBID,) = $aVariables;

        $aSubmission = json_decode(file_get_contents(
            ROOT_PATH . '../tests/test_data_files/submission_api_request_content.json'), true);
        $aSubmission['lsdb']['@id'] = $sLSDBID;

        $sResult = file_get_contents(
            ROOT_URL . '/src/api/submissions', false, stream_context_create(
            array(
                'http' => array(
                    'method'  => 'POST',
                    'header'  => 'Content-type: application/x-www-form-urlencoded',
                    'content' => json_encode($aSubmission),
                    'ignore_errors' => true,
                ))));
        $aResult = json_decode($sResult, true);

        $this->assertEquals(array(), $aResult['messages']);
        $this->assertStringContainsString('VarioML error: Authentication denied.',
            implode(';', $aResult['errors']));
        $this->assertStringEndsWith('401 Unauthorized', $http_response_header[0]);
    }





    /**
     * @depends testCreateToken
     */
    public function testSubmitSuccessfully ($aVariables)
    {
        list($sLSDBID, $sToken) = $aVariables;

        $sHref = $this->driver->findElement(WebDriverBy::xpath('//a[.="Your account"]'))->getAttribute('href');
        $aHref = explode('/', $sHref);
        $sUserID = array_pop($aHref);

        $aSubmission = json_decode(file_get_contents(
            ROOT_PATH . '../tests/test_data_files/submission_api_request_content.json'), true);
        $aSubmission['lsdb']['@id'] = $sLSDBID;
        foreach ($aSubmission['lsdb']['source']['contact']['db_xref'] as $nKey => $aDBXref) {
            if ($aDBXref['@source'] == 'lovd') {
                $aSubmission['lsdb']['source']['contact']['db_xref'][$nKey]['@accession'] = $sUserID;
            } elseif ($aDBXref['@source'] == 'lovd_auth_token') {
                $aSubmission['lsdb']['source']['contact']['db_xref'][$nKey]['@accession'] = $sToken;
            }
        }

        $sResult = file_get_contents(
            ROOT_URL . '/src/api/submissions', false, stream_context_create(
            array(
                'http' => array(
                    'method'  => 'POST',
                    'header'  => 'Content-type: application/x-www-form-urlencoded',
                    'content' => json_encode($aSubmission),
                    'ignore_errors' => true,
                ))));
        $aResult = json_decode($sResult, true);

        $this->assertEquals(array(), $aResult['errors']);
        $this->assertStringContainsString('Data successfully stored for import.',
            implode(';', $aResult['messages']));
        $this->assertStringEndsWith ('202 Accepted', $http_response_header[0]);
    }





    /**
     * @depends testSubmitSuccessfully
     */
    public function testScheduleSubmission ()
    {
        $this->driver->get(ROOT_URL . '/src/import?schedule');
        $this->driver->findElement(WebDriverBy::xpath(
            '//table[@class="data"][tbody/tr/th/text()="Files to be processed"]/tbody/tr[last()]/td'))->click();
        $this->submitForm('Schedule for import');

        // There can be multiple tables, so select the right one.
        $this->assertEquals('Successfully scheduled 1 file for import.',
            $this->driver->findElement(WebDriverBy::xpath('//table[@class="info"]//td[contains(text(), "Successfully")]'))->getText());
    }





    /**
     * @depends testScheduleSubmission
     */
    public function testImportSubmission ()
    {
        $this->driver->get(ROOT_URL . '/src/import?autoupload_scheduled_file');
        $this->assertStringContainsString('Success!', $this->driver->getPageSource());
    }





    /**
     * @depends testImportSubmission
     */
    public function testVerifySubmission ()
    {
        $this->driver->get(ROOT_URL . '/src/variants/IVD');
        $this->assertTrue($this->isElementPresent(WebDriverBy::xpath('//td[.="c.465+1G>A"]')));
    }
}
?>
