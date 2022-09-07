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





    public function testInterfaceIsUp ()
    {
        $this->driver->get(ROOT_URL . '/src/scripts/check_HGVS.php');
        $this->assertEquals(
            'HGVS DNA variant description syntax checker',
            $this->driver->findElement(WebDriverBy::tagName('h1'))->getText()
        );
    }





    /**
     * @depends testInterfaceIsUp
     */
    public function testSingleVariant ()
    {
        // Test the single variant interface.

        // Enter variant and submit.
        $this->enterValue('singleVariant', 'c.100del');
        $this->unCheck('singleVariantUseVV');
        $this->clickButton('Validate this variant description');

        // Wait for alert, then check the output.
        $sXPathAlert = '//div[@id="singleVariantResponse"]/div[contains(@class, "alert")]';
        $this->waitForElement(WebDriverBy::xpath($sXPathAlert));
        $this->assertEquals(
            '1 variant validated successfully.',
            $this->driver->findElement(WebDriverBy::xpath($sXPathAlert))->getText()
        );

        // Use our card testing function, to save us from code repetition.
        $this->checkCard(
            '//div[@id="singleVariantResponse"]/div[contains(@class, "card")]',
            array(
                array(
                    'class' => 'bg-success',
                    'icon' => 'bi-check-circle-fill',
                    'variant' => 'c.100del',
                    'items' => array(
                        array(
                            'class' => 'list-group-item-success',
                            'icon' => 'bi-check-circle-fill',
                            'value' => 'This variant description\'s syntax is valid.',
                        ),
                        array(
                            'class' => 'list-group-item-secondary',
                            'icon' => 'bi-exclamation-circle-fill',
                            'value' => 'This variant has not been validated on the sequence level. For sequence-level validation, please select the VariantValidator option.',
                        ),
                        array(
                            'class' => 'list-group-item-secondary',
                            'icon' => 'bi-info-circle-fill',
                            'value' =>
                                'Please note that your variant description is missing a reference sequence.' .
                                ' Although this is not necessary for our syntax check, a variant description does need a reference sequence to be fully informative and HGVS-compliant.',
                        ),
                    ),
                )
            )
        );
    }
}
?>
