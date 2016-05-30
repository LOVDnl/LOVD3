<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-03-02
 * Modified    : 2016-05-30
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


use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;



abstract class LOVDSeleniumWebdriverBaseTestCase extends PHPUnit_Framework_TestCase
{
    // Base class for all Selenium tests.

    // public webdriver instance
    public $driver;

    protected function setUp()
    {
        // This method is called before every test invocation.

        $this->driver = getWebDriverInstance();
    }


    protected function waitUntil($condition)
    {
        // Convenience function to let the webdriver wait for a standard amount
        // of time on the given condition.
        return $this->driver->wait(WEBDRIVER_MAX_WAIT_DEFAULT, WEBDRIVER_POLL_INTERVAL_DEFAULT)
                            ->until($condition);
    }


    protected function enterValue($locator, $sText)
    {
        // Convenience function to let the webdriver type text $text in an
        // element specified by $locator.
        $element = $this->driver->findElement($locator);
        $element->click();

        // Try to clear field and set value for a time period. This is needed
        // for some fields that are set read-only until they get focus, then
        // the first sendKeys() calls may be ignored as javascript has to be
        // executed.
        $this->waitUntil(function ($driver) use ($element, $sText) {

            if (!is_null($element->getAttribute('readonly'))) {
                return false;
            }
            $element->clear();
            $element->sendKeys($sText);
            return $element->getAttribute('value') == $sText;
        });
    }


    protected function setCheckBoxValue($locator, $bSetChecked) {
        $element = $this->driver->findElement($locator);

        if (($bSetChecked && is_null($element->getAttribute('checked'))) ||
            (!$bSetChecked && !is_null($element->getAttribute('checked')))) {
            $element->click();
        }
    }


    protected function check($locator)
    {
        $this->setCheckBoxValue($locator, true);
    }


    protected  function unCheck($locator)
    {
        $this->setCheckBoxValue($locator, false);
    }
}
