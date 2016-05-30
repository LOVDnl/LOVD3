<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-05-30
 * Modified    : 2016-06-01
 * For LOVD    : 3.0-15
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

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class InstallLOVDTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testInstallLOVD()
    {
        // Go to install page
        $this->driver->get(ROOT_URL . "/src/install");
        $bodyElement = $this->driver->findElement(WebDriverBy::tagName('body'));
        $this->assertContains("install", $bodyElement->getText());

        // Start installation procedure
        $startButton = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Start >>']"));
        $startButton->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/install\/[\s\S]step=1$/', $this->driver->getCurrentURL()));

        // Fill out form
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

        // Click through next steps
        $continueButton = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Continue »']"));
        $continueButton->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/install\/[\s\S]step=1&sent=true$/', $this->driver->getCurrentURL()));
        $nextButton = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Next >>']"));
        $nextButton->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/install\/[\s\S]step=2$/', $this->driver->getCurrentURL()));
        $nextButton = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Next >>']"));
        $nextButton->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/install\/[\s\S]step=3$/', $this->driver->getCurrentURL()));

        // Another form
        $this->enterValue(WebDriverBy::name("institute"), "Leiden University Medical Center");
        $this->enterValue(WebDriverBy::name("email_address"), "noreply@LOVD.nl");
        $countryOption = $this->driver->findElement(WebDriverBy::xpath('//select[@name="refseq_build"]/option[text()="hg19 / GRCh37"]'));
        $countryOption->click();
        $sendstatsButton = $this->driver->findElement(WebDriverBy::name("send_stats"));
        $sendstatsButton->click();
        $includeButton = $this->driver->findElement(WebDriverBy::name("include_in_listing"));
        $includeButton->click();
        $lockCheckBox = $this->driver->findElement(WebDriverBy::name("lock_uninstall"));
        $lockCheckBox->click();

        $continueButton = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Continue »']"));
        $continueButton->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/install\/[\s\S]step=3&sent=true$/', $this->driver->getCurrentURL()));
        $nextButton = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Next >>']"));
        $nextButton->click();

        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/install\/[\s\S]step=4$/', $this->driver->getCurrentURL()));
        $button = $this->driver->findElement(WebDriverBy::cssSelector("button"));
        $button->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/setup[\s\S]newly_installed$/', $this->driver->getCurrentURL()));
    }
}
