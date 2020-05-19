<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2017-06-27
 * Modified    : 2020-05-19
 * For LOVD    : 3.0-24
 *
 * Copyright   : 2017-2020 Leiden University Medical Center; http://www.LUMC.nl/
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
use \Facebook\WebDriver\WebDriverExpectedCondition;

define('SUBMISSION_API_REQUEST_FILE', ROOT_PATH . '../tests/test_data_files/submission_api_request_content.json');


class SubmissionApiSuccessTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testSubmissionApiSuccess()
    {
        // Test performing a submission through the API. This test assumes a
        // user is currently logged in and its account has an authentication
        // token associated with it. E.g. by having run CreateTokenTest and
        // LoginAsAdminTest earlier.

        // Go to home page and then user account page.
        $this->driver->get(ROOT_URL . '/src');
        $this->driver->findElement(WebDriverBy::xpath('//a/b[text()="Your account"]'))->click();

        // Retrieve user ID from URL.
        $sURL = $this->driver->getCurrentURL();
        $aURLItems = explode('/', $sURL);
        $sUserID = $aURLItems[count($aURLItems)-1];

        // Retrieve authentication token from page.
        $this->driver->findElement(WebDriverBy::xpath('//a[text()="Show / More information"]'))->click();
        $oTokenLocator = WebDriverBy::xpath('//div[@id="auth_token_dialog"]/pre');
        $this->waitForElement($oTokenLocator);
        $sToken = $this->driver->findElement($oTokenLocator)->getText();

        // Require inc-init.php to get global settings array.
        list(,$aStatus) = getLOVDGlobals();
        $sSubmissionFile = ROOT_PATH . '../tests/test_data_files/submission_api_request_content.json';
        $aSub = json_decode(file_get_contents($sSubmissionFile), true);

        // Set some fields in the request body to make it valid.
        $aSub['lsdb']['@id'] = md5($aStatus['signature']);
        foreach ($aSub['lsdb']['source']['contact']['db_xref'] as $k => $xref) {
            if ($xref['@source'] == 'lovd_auth_token') {
                // Set dummy token.
                $aSub['lsdb']['source']['contact']['db_xref'][$k]['@accession'] = $sToken;
            } elseif ($xref['@source'] == 'lovd') {
                // Set user id of admin user.
                $aSub['lsdb']['source']['contact']['db_xref'][$k]['@accession'] = $sUserID;
            }
        }

        // Send post request with submission.
        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => json_encode($aSub),
                'ignore_errors' => true,
            )
        );
        $context  = stream_context_create($options);
        $sResult = file_get_contents(ROOT_URL . '/src/api/submissions', false, $context);
        $aResult = json_decode($sResult, true);

        // Check for errors, there should be none.
        $this->assertEquals('', implode(';', $aResult['errors']));
        // Check for confirmation.
        $this->assertContains('Data successfully scheduled', $aResult['messages'][0]);

        // Check for correct HTTP response code in returned headers.
        // Note: $http_response_header is magically filled by
        // file_get_contents().
        $this->assertContains('202 Accepted', $http_response_header[0]);


        // Go to import page to schedule submitted submission for import.
        $this->driver->get(ROOT_URL . '/src/import?schedule');
        $sFileTD = '//table[@class="data"][tbody/tr/th/text()="Files to be processed"]/tbody/tr[last()]/td';
        $this->driver->findElement(WebDriverBy::xpath($sFileTD))->click();
        $this->driver->findElement(WebDriverBy::xpath('//input[@type="submit"]'))->click();
        $oMsgLocator = WebDriverBy::xpath('//td[text()="Successfully scheduled 1 file for import."]');
        $this->waitUntil(WebDriverExpectedCondition::presenceOfElementLocated($oMsgLocator));

        // Trigger automatic import of scheduled files.
        $this->driver->get(ROOT_URL . '/src/import?autoupload_scheduled_file');
        $bodyText = $this->driver->findElement(WebDriverBy::tagName("body"))->getText();
        $this->assertContains('Success!', $bodyText);

        // Check if variant from submission can be found in interface.
        $this->driver->get(ROOT_URL . '/src/variants/IVD');
        $oVarLocator = WebDriverBy::xpath('//a[text()="c.465+1G>A"]');
        $this->waitUntil(WebDriverExpectedCondition::presenceOfElementLocated($oVarLocator));
    }
}
