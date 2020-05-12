<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-03-04
 * Modified    : 2020-05-12
 * For LOVD    : 3.0-24
 *
 * Copyright   : 2016-2020 Leiden University Medical Center; http://www.LUMC.nl/
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

class InstallLOVDTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testIsLOVDUninstalled ()
    {
        // Checks if we're currently installed. If so, uninstalls LOVD first.
        // This is needed because we kill testing if Travis fails somewhere, to
        //  prevent whole avalanches of failures. However, LOVD still needs to
        //  be uninstalled for the next test suite.
        $this->driver->get(ROOT_URL . '/src/install');
        if (!$this->isElementPresent(WebDriverBy::xpath("//input[@value='Start >>']"))) {
            // Hmm... strange. Are we installed already?
            $bodyElement = $this->driver->findElement(WebDriverBy::tagName('body'));
            if (preg_match('/is now complete/', $bodyElement->getText())) {
                // OK, we're installed already. Uninstall first.
                require_once dirname(__FILE__) . '/uninstall_LOVD.php';
                $Test = new UninstallLOVDTest();
                $Test->setUp(); // Make sure it gets the driver info.
                return $Test->testUninstallLOVDTest();
            }
        }
    }





    /**
     * @depends testIsLOVDUninstalled
     */
    public function testInstallLOVD ()
    {
        // Check if an XDebug session needs to be started, and if so, add the
        // XDebug get parameter.
        // Note: this has to be done once per session. Starting XDebug in
        //       setUp() is not possible as the session may not have
        //       initialized yet. The current method is the common starting
        //       point for most selenium tests.
        global $bXDebugStatus;
        if (XDEBUG_ENABLED && isset($bXDebugStatus) && !$bXDebugStatus) {
            $this->driver->get(ROOT_URL . '/src/install?XDEBUG_SESSION_START=test');
            $bXDebugStatus = true;
        } else {
            $this->driver->get(ROOT_URL . '/src/install');
        }

        $bodyElement = $this->driver->findElement(WebDriverBy::tagName('body'));
        $this->assertContains('This installer will create', $bodyElement->getText());

        // Travis' PHP version may not be our PHP version.
        // Apache may be configured to use something completely different than
        //  the cli version which currently powers PHPUnit.
        // Also, it's good to just have the MySQL version.
        // To check, take note of what LOVD just printed out.
        $infoBox = $this->driver->findElement(WebDriverBy::xpath('//table[@class="info"]/tbody/tr/td[@valign="middle"]'));
        print($infoBox->getText());

        // Start installation procedure.
        $startButton = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Start >>']"));
        $startButton->click();

        // Fill out Administrator form.
        $this->assertContains('/src/install/?step=1', $this->driver->getCurrentURL());
        $this->enterValue(WebDriverBy::name("name"), "LOVD3 Admin");
        $this->enterValue(WebDriverBy::name("institute"), "Leiden University Medical Center");
        $this->enterValue(WebDriverBy::name("department"), "Human Genetics");
        $this->enterValue(WebDriverBy::name("address"), "Einthovenweg 20\n2333 ZC Leiden");
        $this->enterValue(WebDriverBy::name("email"), "test@lovd.nl");
        $this->enterValue(WebDriverBy::name("telephone"), "+31 (0)71 526 9438");
        $this->enterValue(WebDriverBy::name("username"), "admin");
        $this->enterValue(WebDriverBy::name("password_1"), "test1234");
        $this->enterValue(WebDriverBy::name("password_2"), "test1234");

        $countryOption = $this->driver->findElement(WebDriverBy::xpath('//select[@name="countryid"]/option[text()="Netherlands"]'));
        $countryOption->click();
        $this->enterValue(WebDriverBy::name("city"), "Leiden");
        $continueButton = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Continue »']"));
        $continueButton->click();

        // Confirmation of account information, installing...
        $this->assertContains('/src/install/?step=1&sent=true', $this->driver->getCurrentURL());
        $nextButton = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Next >>']"));
        $this->clickNoTimeout($nextButton);

        // Installation complete.
        $this->assertContains('/src/install/?step=2', $this->driver->getCurrentURL());
        $nextButton = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Next >>']"));
        $nextButton->click();

        // Fill out System Settings form.
        $this->assertContains('/src/install/?step=3', $this->driver->getCurrentURL());
        $this->enterValue(WebDriverBy::name("institute"), "Leiden University Medical Center");
        $this->enterValue(WebDriverBy::name("email_address"), "noreply@LOVD.nl");
        $selectOption = $this->driver->findElement(WebDriverBy::xpath('//select[@name="refseq_build"]/option[text()="hg19 / GRCh37"]'));
        $selectOption->click();
        $sendstatsButton = $this->driver->findElement(WebDriverBy::name("send_stats"));
        $sendstatsButton->click();
        $includeButton = $this->driver->findElement(WebDriverBy::name("include_in_listing"));
        $includeButton->click();
        $lockCheckBox = $this->driver->findElement(WebDriverBy::name("lock_uninstall"));
        $lockCheckBox->click();
        $continueButton = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Continue »']"));
        $continueButton->click();

        // Settings stored.
        $this->assertContains('/src/install/?step=3&sent=true', $this->driver->getCurrentURL());
        $nextButton = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Next >>']"));
        $nextButton->click();

        // Done!
        $this->assertContains('/src/install/?step=4', $this->driver->getCurrentURL());
        $button = $this->driver->findElement(WebDriverBy::cssSelector("button"));
        $button->click();

        // LOVD Setup Area.
        $this->waitUntil(WebDriverExpectedCondition::titleContains("LOVD Setup"));
        $this->assertContains('/src/setup?newly_installed', $this->driver->getCurrentURL());
    }





    public static function tearDownAfterClass()
    {
        // Set the Mutalyzer service URL that will be used in the rest of the
        // test suite.
        setMutalyzerServiceURL('https://test.mutalyzer.nl/services');
    }
}
