<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2022-09-06
 * Modified    : 2022-09-07
 * For LOVD    : 3.0-29
 *
 * Copyright   : 2004-2022 Leiden University Medical Center; http://www.LUMC.nl/
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

class CheckHGVSInterfaceTest extends LOVDSeleniumWebdriverBaseTestCase
{
    private function checkCard ($sXPathCards, $aCards = array())
    {
        // This function helps prevent repeated code by testing cards.
        // This function is not named test*, so it won't be called by phpunit.
        // (a private function testCard() was generating a warning)

        $this->assertTrue(is_array($aCards));
        $this->assertNotEmpty($aCards);

        // First check if we have cards at all.
        $this->assertTrue($this->isElementPresent(WebDriverBy::xpath($sXPathCards)));

        // Check count of cards.
        $this->assertEquals(
            count($aCards),
            count($this->driver->findElements(WebDriverBy::xpath($sXPathCards)))
        );

        // Loop through cards, and check.
        foreach ($aCards as $nKey => $aCard) {
            $nKey ++; // XPath keys start at one.
            $this->assertTrue($this->isElementPresent(WebDriverBy::xpath($sXPathCards . '[' . $nKey . '][contains(@class, "' . $aCard['class'] . '")]')));
            $this->assertTrue($this->isElementPresent(WebDriverBy::xpath($sXPathCards . '[' . $nKey . ']/div/div[1]/h5/i[contains(@class, "' . $aCard['icon'] . '")]')));
            $this->assertEquals(
                $aCard['variant'],
                $this->driver->findElement(WebDriverBy::xpath($sXPathCards . '[' . $nKey . ']/div/div[1]'))->getText()
            );

            // Check count of list items.
            $this->assertEquals(
                count($aCard['items']),
                count($this->driver->findElements(WebDriverBy::xpath($sXPathCards . '[' . $nKey . ']/ul/li')))
            );

            foreach ($aCard['items'] as $nItem => $aItem) {
                $nItem ++; // XPath keys start at one.
                $this->assertTrue($this->isElementPresent(WebDriverBy::xpath($sXPathCards . '[' . $nKey . ']/ul/li[' . $nItem . '][contains(@class, "' . $aItem['class'] . '")]')));
                $this->assertTrue($this->isElementPresent(WebDriverBy::xpath($sXPathCards . '[' . $nKey . ']/ul/li[' . $nItem . ']/i[contains(@class, "' . $aItem['icon'] . '")]')));
                $this->assertEquals(
                    $aItem['value'],
                    $this->driver->findElement(WebDriverBy::xpath($sXPathCards . '[' . $nKey . ']/ul/li[' . $nItem . ']'))->getText()
                );
            }

            // Close card. Scroll into view first, because we keep getting Exceptions otherwise.
            $this->assertTrue($this->isElementPresent(WebDriverBy::xpath($sXPathCards . '[' . $nKey . ']/div[1]/div[2]/i')));
            $this->driver->findElement(WebDriverBy::xpath($sXPathCards . '[' . $nKey . ']/div[1]/div[2]/i'))->getLocationOnScreenOnceScrolledIntoView();
            $this->driver->findElement(WebDriverBy::xpath($sXPathCards . '[' . $nKey . ']/div[1]/div[2]/i'))->click();
            $this->assertFalse(
                $this->driver->findElement(WebDriverBy::xpath($sXPathCards . '[' . $nKey . ']/ul'))->isDisplayed());
        }
    }
}
?>
