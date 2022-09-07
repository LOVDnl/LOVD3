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





    /**
     * @depends testSingleVariant
     */
    public function testSingleVariantDownload ()
    {
        // Test the single variant download.

        // The download location is set to "/tmp"
        //  in getWebDriverInstance() @ inc-lib-test.php.
        $aFilesBefore = scandir('/tmp');
        $this->clickButton('Download this result');
        $this->waitUntil(function () use ($aFilesBefore) {
            // Let's hope nothing gets deleted now,
            //  and no new files get added that aren't the download file.
            return (count(scandir('/tmp')) > count($aFilesBefore));
        });
        $aPossibleDownloadFiles = array_diff(scandir('/tmp'), $aFilesBefore);
        $this->assertGreaterThanOrEqual(1, count($aPossibleDownloadFiles));

        if (count($aPossibleDownloadFiles) == 1) {
            $sDownloadFile = current($aPossibleDownloadFiles);
        } else {
            foreach ($aPossibleDownloadFiles as $sFile) {
                // Just assume the first match.
                if (preg_match('/^LOVD_checkHGVS_[0-9T_-]+\.txt$/', $sFile)) {
                    $sDownloadFile = $sFile;
                    break;
                }
            }
        }

        // Now compare the two files.
        $this->assertEquals(
            array(
                array(
                    '"Input"',
                    '"Status"',
                    '"Suggested correction"',
                    '"Messages"',
                ),
                array(
                    '"c.100del"',
                    '"success"',
                    '""',
                    '"OK: This variant description\'s syntax is valid. Note: This variant has not been validated on the sequence level. For sequence-level validation, please select the VariantValidator option. Note: Please note that your variant description is missing a reference sequence. Although this is not necessary for our syntax check, a variant description does need a reference sequence to be fully informative and HGVS-compliant."',
                )
            ),
            array_map(
                function ($sVal)
                {
                    return explode("\t", $sVal);
                }, explode(
                    "\r\n",
                    rtrim(
                        file_get_contents('/tmp/' . $sDownloadFile)
                    )
                )
            )
        );
    }





    /**
     * @depends testSingleVariantDownload
     */
    public function testMultipleVariants ()
    {
        // Test the multiple variants interface.

        // Switch interfaces.
        $this->clickButton('Check a list of variants');

        // Enter variants and submit.
        $this->enterValue('multipleVariants', '
c.100delA
c.100del
c.100
c.1ATG[2]
g.qter_cendel
NM_004006.3:100del
NM_004006.3:c.100del');
        $this->check('multipleVariantsUseVV');
        $this->clickButton('Validate these variant descriptions');

        // Wait for alert, then check the output.
        $sXPathAlert = '//div[@id="multipleVariantsResponse"]/div[contains(@class, "alert")]';
        $this->waitForElement(WebDriverBy::xpath($sXPathAlert));
        $this->assertEquals(
            '7 variants received. 1 variant validated successfully. 1 variant is not supported. 3 variants can be fixed. 2 variants failed to validate.',
            str_replace("\n", ' ', $this->driver->findElement(WebDriverBy::xpath($sXPathAlert))->getText())
        );

        // Use our card testing function, to save us from code repetition.
        $this->checkCard(
            '//div[@id="multipleVariantsResponse"]/div[contains(@class, "card")]',
            array(
                array(
                    'class' => 'bg-secondary',
                    'icon' => 'bi-x-circle-fill',
                    'variant' => 'c.100delA',
                    'items' => array(
                        array(
                            'class' => 'list-group-item-secondary',
                            'icon' => 'bi-x-circle-fill',
                            'value' => 'This variant description is invalid.',
                        ),
                        array(
                            'class' => 'list-group-item-secondary',
                            'icon' => 'bi-x-circle-fill',
                            'value' => 'Nothing should follow "del".',
                        ),
                        array(
                            'class' => 'list-group-item-danger',
                            'icon' => 'bi-dash-circle-fill',
                            'value' => 'Please first correct the variant description to run VariantValidator.',
                        ),
                        array(
                            'class' => 'list-group-item-warning',
                            'icon' => 'bi-arrow-right-circle-fill',
                            'value' => 'We suggest that perhaps the correct variant description is c.100del.',
                        ),
                    ),
                ),
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
                            'class' => 'list-group-item-danger',
                            'icon' => 'bi-dash-circle-fill',
                            'value' => 'Please provide a reference sequence to run VariantValidator.',
                        ),
                    ),
                ),
                array(
                    'class' => 'bg-danger',
                    'icon' => 'bi-exclamation-circle-fill',
                    'variant' => 'c.100',
                    'items' => array(
                        array(
                            'class' => 'list-group-item-danger',
                            'icon' => 'bi-exclamation-circle-fill',
                            'value' => 'Failed to recognize a variant description in your input.',
                        ),
                        array(
                            'class' => 'list-group-item-danger',
                            'icon' => 'bi-dash-circle-fill',
                            'value' => 'Please first correct the variant description to run VariantValidator.',
                        ),
                    ),
                ),
                array(
                    'class' => 'bg-success',
                    'icon' => 'bi-check-circle-fill',
                    'variant' => 'c.1ATG[2]',
                    'items' => array(
                        array(
                            'class' => 'list-group-item-success',
                            'icon' => 'bi-check-circle-fill',
                            'value' => 'This variant description\'s syntax is valid.',
                        ),
                        array(
                            'class' => 'list-group-item-secondary',
                            'icon' => 'bi-info-circle-fill',
                            'value' => 'This variant description is not currently supported by VariantValidator.',
                        ),
                    ),
                ),
                array(
                    'class' => 'bg-secondary',
                    'icon' => 'bi-question-circle-fill',
                    'variant' => 'g.qter_cendel',
                    'items' => array(
                        array(
                            'class' => 'list-group-item-secondary',
                            'icon' => 'bi-exclamation-circle-fill',
                            'value' =>
                                'This variant description contains unsupported syntax.' .
                                ' Although we aim to support all of the HGVS nomenclature rules, some complex variants are not fully implemented yet in our syntax checker.',
                        ),
                    ),
                ),
                array(
                    'class' => 'bg-secondary',
                    'icon' => 'bi-x-circle-fill',
                    'variant' => 'NM_004006.3:100del',
                    'items' => array(
                        array(
                            'class' => 'list-group-item-danger',
                            'icon' => 'bi-exclamation-circle-fill',
                            'value' => 'Failed to recognize a variant description in your input.',
                        ),
                        array(
                            'class' => 'list-group-item-danger',
                            'icon' => 'bi-dash-circle-fill',
                            'value' => 'Please first correct the variant description to run VariantValidator.',
                        ),
                        array(
                            'class' => 'list-group-item-warning',
                            'icon' => 'bi-arrow-right-circle-fill',
                            'value' => 'Maybe you meant to describe the variant as NM_004006.3:c.100del.',
                        ),
                    ),
                ),
                array(
                    'class' => 'bg-secondary',
                    'icon' => 'bi-x-circle-fill',
                    'variant' => 'NM_004006.3:c.100del',
                    'items' => array(
                        array(
                            'class' => 'list-group-item-secondary',
                            'icon' => 'bi-check-circle-fill',
                            'value' => 'This variant description\'s syntax is valid.',
                        ),
                        array(
                            'class' => 'list-group-item-warning',
                            'icon' => 'bi-arrow-right-circle-fill',
                            'value' => 'VariantValidator automatically corrected the variant description to NM_004006.3:c.101del.',
                        ),
                    ),
                ),
            )
        );
    }
}
?>
