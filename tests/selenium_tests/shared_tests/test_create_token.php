<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2017-06-27
 * Modified    : 2017-12-06
 * For LOVD    : 3.0-21
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

class CreateTokenTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testCreateToken()
    {
        // Test successfully creating a API authorization token for the
        // current user.

        // Go to home page and then user account page.
        $this->driver->get(ROOT_URL . '/src');
        $this->driver->findElement(WebDriverBy::xpath('//a/b[text()="Your account"]'))->click();
        $this->driver->findElement(WebDriverBy::xpath('//a[text()="Show / More information"]'))->click();
        $this->driver->findElement(WebDriverBy::xpath('//button/span[text()="Create new token"]'))->click();

        // A more convoluted way to select the next "create new token" button.
        // Check on form in sibling element is needed to discern it from the
        // previous button.
        $sCreateBtnXpath = '//div[div/form]/div/div/button/span[text()="Create new token"]';
        $this->driver->findElement(WebDriverBy::xpath($sCreateBtnXpath))->click();

        $oSuccessMsg = WebDriverBy::xpath('//div[text()="Token created successfully!"]');
        $this->waitUntil(WebDriverExpectedCondition::presenceOfElementLocated($oSuccessMsg));

    }
}