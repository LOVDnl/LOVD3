<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-04-21
 * Modified    : 2016-05-12
 * For LOVD    : 3.0-16
 *
 * Copyright   : 2016 Leiden University Medical Center; http://www.LUMC.nl/
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
require_once 'shared_tests/create_users_submitter.php';

class AccessSharingSubmitterTest extends LOVDSeleniumBaseTestCase
{

    public function testAccessSharingSubmitter()
    {

        $aUserRecords = CreateUsersSubmitterTest::userRecords();

        if (count($aUserRecords) < 2) {
            $this->fail('Cannot run test because CreateUsersSubmitterTest::userRecords() returned
                         too few records.');
        }

        // Example record: array('Test Submitter1', 'example1@example.com', 'testsubmitter1',
        //                       'testsubmitter1')
        list($sSubName1, $sSubEmail1, $sSubUsername1, $sSubPass1) = $aUserRecords[0];
        list($sSubName2, $sSubEmail2, $sSubUsername2, $sSubPass2) = $aUserRecords[1];

        // Login as first submitter.
        $this->open(ROOT_URL . "/src/logout");
        $this->open(ROOT_URL . "/src/login");
        $this->type("name=username", $sSubUsername1);
        $this->type("name=password", $sSubPass1);
        $this->click("//input[@value='Log in']");
        $this->waitForPageToLoad("30000");

        // Create individual as first submitter.
        $this->open(ROOT_URL . "/src/individuals?create");
        $this->type("name=Individual/Lab_ID", "dummy");
        $this->click("//input[@value='Create individual information entry']");
        // Wait for a redirect to the submissions page.
        sleep(5);
        $sSubmitHeader = $this->getText('//h2[@class="LOVD"]');
        // Assume last 8 characters constitute the ID of the individual just added.
        $sIndividualID = substr($sSubmitHeader, -8);

        // Login as second submitter.
        $this->open(ROOT_URL . "/src/logout");
        $this->open(ROOT_URL . "/src/login");
        $this->type("name=username", $sSubUsername2);
        $this->type("name=password", $sSubPass2);
        $this->click("//input[@value='Log in']");
        $this->waitForPageToLoad("30000");

        // Try (unsuccessfully) to access individual.
        $this->open(ROOT_URL . '/src/individuals/' . $sIndividualID);
        $this->assertEquals($this->getText('//table[@class="info"]/tbody/tr/td[@valign="middle"]'), 'No such ID!');

        // Login as first submitter.
        $this->open(ROOT_URL . "/src/logout");
        $this->open(ROOT_URL . "/src/login");
        $this->type("name=username", $sSubUsername1);
        $this->type("name=password", $sSubPass1);
        $this->click("//input[@value='Log in']");
        $this->waitForPageToLoad("30000");

        // Share access with other submitters.
        $this->click('//a/b[text()="Your account"]');
        $sMenuSelector = '//img[@id="viewentryOptionsButton_Users"]';
        $this->waitForElementPresent($sMenuSelector, '8000');
        $this->click($sMenuSelector);
        $sMenuItemSelector = '//a[text()="Share access to your entries with other users"]';
        $this->waitForElementPresent($sMenuItemSelector, '8000');
        $this->click($sMenuItemSelector);

        for ($i=0; $i < count($aUserRecords); $i++) {
            $sUserSelector = '//td[text()="' . $aUserRecords[$i][2] . '"]';
            $this->waitForElementPresent($sUserSelector, '8000');
            $this->click($sUserSelector);
        }

        $this->click('//input[@value="Save"]');

        // Login as second submitter.
        $this->open(ROOT_URL . "/src/logout");
        $this->open(ROOT_URL . "/src/login");
        $this->waitForPageToLoad("30000");
        $this->type("name=username", $sSubUsername2);
        $this->type("name=password", $sSubPass2);
        $this->click("//input[@value='Log in']");
        $this->waitForPageToLoad("30000");

        // Try (successfully) to access individual.
        $this->open(ROOT_URL . '/src/individuals/' . $sIndividualID);
        $this->waitForPageToLoad("30000");
        $this->assertEquals($this->getText('//h2[@class="LOVD"]'), 'View individual #' . $sIndividualID);
    }
}
