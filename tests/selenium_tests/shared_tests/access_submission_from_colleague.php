<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-06-17
 * Modified    : 2020-06-17
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

class AccessSubmissionFromColleagueTest extends LOVDSeleniumWebdriverBaseTestCase
{
    protected function setUp ()
    {
        parent::setUp();
        $this->driver->get(ROOT_URL . '/src/individuals');
        $sBody = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();
        if (preg_match('/LOVD was not installed yet/', $sBody)) {
            $this->markTestSkipped('LOVD was not installed yet.');
        }
        if (!$this->isElementPresent(WebDriverBy::xpath('//a[contains(@href, "users/0000")]/b[text()="Your account"]'))) {
            $this->markTestSkipped('User was not authorized.');
        }
        if ($this->isElementPresent(WebDriverBy::id('tab_configuration'))) {
            $this->markTestSkipped('User level is too high for this test.');
        }
    }





    public function test ()
    {
        $this->driver->get(ROOT_URL . '/src/individuals');
        // In our tests, user "Owner" has set a colleague.
        $this->driver->findElement(WebDriverBy::xpath('//table[@class="data"]//tr[td[contains(., "Test Owner")]]/td[1]'))->click();

        // Menus should not exist (except for variants, they always have a menu),
        //  but I should be able to see non-public fields.
        // To return here.
        $sSubmissionURL = $this->driver->getCurrentURL();

        // Individual.
        $this->assertContains('/src/individuals/0000', $this->driver->getCurrentURL());
        $this->assertFalse($this->isElementPresent(WebDriverBy::id('viewentryOptionsButton_Diseases')));
        $this->driver->findElement(WebDriverBy::xpath('//table[@class="data"]//td/span[text()="Pending"]'));
        $this->driver->findElement(WebDriverBy::xpath('//div[contains(@id, "viewlistDiv_Phenotypes_for_I_VE_0000")]//td[text()="Pending"]'))->click();

        // Phenotype.
        $this->assertContains('/src/phenotypes/0000', $this->driver->getCurrentURL());
        $this->assertFalse($this->isElementPresent(WebDriverBy::id('viewentryOptionsButton_Phenotypes')));
        $this->driver->findElement(WebDriverBy::xpath('//table[@class="data"]//td/span[text()="Pending"]'));

        $this->driver->get($sSubmissionURL);
        $this->driver->findElement(WebDriverBy::xpath('//div[@id="viewlistDiv_Screenings_for_I_VE"]//td'))->click();

        // Screening.
        $this->assertContains('/src/screenings/0000', $this->driver->getCurrentURL());
        $this->assertFalse($this->isElementPresent(WebDriverBy::id('viewentryOptionsButton_Screenings')));
        $this->driver->findElement(WebDriverBy::xpath('//table[@class="data"]//td/span[text()="(Pending)"]'));
        $this->driver->findElement(WebDriverBy::xpath('//div[@id="viewlistDiv_CustomVL_VOT_for_S_VE"]//td[text()="Pending"]'))->click();

        // Variant, through Screening.
        $this->assertContains('/src/variants/0000', $this->driver->getCurrentURL());
        $this->driver->findElement(WebDriverBy::xpath('//table[@class="data"]//td/span[text()="(Pending)"]'));
        $this->driver->findElement(WebDriverBy::xpath('//table[@class="data"]//td/span[text()="Pending"]'));

        $this->driver->get($sSubmissionURL);
        $this->driver->findElement(WebDriverBy::xpath('//div[@id="viewlistDiv_CustomVL_VOT_for_I_VE"]//td[text()="Pending"]'))->click();

        // Variant, through Individual.
        $this->assertContains('/src/variants/0000', $this->driver->getCurrentURL());
        $this->driver->findElement(WebDriverBy::xpath('//table[@class="data"]//td/span[text()="(Pending)"]'));
        $this->driver->findElement(WebDriverBy::xpath('//table[@class="data"]//td/span[text()="Pending"]'));
    }
}
?>
