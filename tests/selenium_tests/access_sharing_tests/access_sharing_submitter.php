<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-04-21
 * Modified    : 2016-08-11
 * For LOVD    : 3.0-17
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

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class AccessSharingSubmitterTest extends LOVDSeleniumWebdriverBaseTestCase
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

        $this->logout();
        $this->login($sSubUsername1, $sSubPass1);

        // Create individual as first submitter.
        $this->driver->get(ROOT_URL . "/src/individuals?create");
        $this->enterValue(WebDriverBy::name("Individual/Lab_ID"), "dummy");
        $createButton = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create individual information entry']"));
        $createButton->click();

        $this->waitUntil(WebDriverExpectedCondition::titleContains('Submission of'));
        $header = $this->driver->findElement(WebDriverBy::xpath('//h2[@class="LOVD"]'));
        $sIndividualID = substr($header->getText(), -8);

        $this->logout();
        $this->login($sSubUsername2, $sSubPass2);

        // Try (unsuccessfully) to access individual.
        $this->driver->get(ROOT_URL . '/src/individuals/' . $sIndividualID);
        $infoBox = $this->driver->findElement(WebDriverBy::xpath('//table[@class="info"]/tbody/tr/td[@valign="middle"]'));
        $this->assertEquals($infoBox->getText(), 'No such ID!');

        $this->logout();
        $this->login($sSubUsername1, $sSubPass1);

        // Open access sharing page
        $yourAccountLink = $this->driver->findElement(WebDriverBy::xpath('//a/b[text()="Your account"]'));
        $yourAccountLink->click();
        $sMenuSelector = '//img[@id="viewentryOptionsButton_Users"]';
        $this->waitUntil(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::xpath($sMenuSelector)));
        $optionsMenu = $this->driver->findElement(WebDriverBy::xpath($sMenuSelector));
        $optionsMenu->click();
        $sMenuItemSelector = '//a[text()="Share access to your entries with other users"]';
        $this->waitUntil(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::xpath($sMenuItemSelector)));
        $shareAccessItem = $this->driver->findElement(WebDriverBy::xpath($sMenuItemSelector));
        $shareAccessItem->click();

        // Share access with other submitter.
        $sUserSelector = '//a[text()="' . $sSubName2 . '"]';
        $this->waitUntil(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::xpath($sUserSelector)));
        $userRow = $this->driver->findElement(WebDriverBy::xpath($sUserSelector));
        $userRow->click();
        $this->enterValue(WebDriverBy::xpath('//td/input[@type="password"]'), $sSubPass1);

        $saveButton = $this->driver->findElement(WebDriverBy::xpath('//input[@value="Save access permissions"]'));
        $saveButton->click();

        $this->logout();
        $this->login($sSubUsername2, $sSubPass2);

        // Try (successfully) to access individual.
        $this->driver->get(ROOT_URL . '/src/individuals/' . $sIndividualID);
        $header = $this->driver->findElement(WebDriverBy::xpath('//h2[@class="LOVD"]'));
        $this->assertEquals($header->getText(), 'View individual #' . $sIndividualID);
        $nonpubFieldHead = $this->driver->findElement(WebDriverBy::xpath('//table[@class="data"]/tbody/tr[4]/th'));
        $this->assertEquals($nonpubFieldHead->getText(), 'Remarks (non public)');
    }
}
