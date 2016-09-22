<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-03-02
 * Modified    : 2016-09-22
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


require_once 'inc-lib-test.php';

use \Facebook\WebDriver\WebDriverExpectedCondition;
use \Facebook\WebDriver\Exception\NoSuchElementException;
use \Facebook\WebDriver\Exception\WebDriverException;
use \Facebook\WebDriver\Remote\LocalFileDetector;



abstract class LOVDSeleniumWebdriverBaseTestCase extends PHPUnit_Framework_TestCase
{
    // Base class for all Selenium tests.

    // public webdriver instance.
    public $driver;

    protected function setUp ()
    {
        // This method is called before every test invocation.
        $this->driver = getWebDriverInstance();
    }


    protected function waitUntil ($condition)
    {
        // Convenience function to let the webdriver wait for a standard amount
        // of time on the given condition.
        return $this->driver->wait(WEBDRIVER_MAX_WAIT_DEFAULT, WEBDRIVER_POLL_INTERVAL_DEFAULT)
                            ->until($condition);
    }


    protected function enterValue ($locator, $sText)
    {
        // Convenience function to let the webdriver type text $text in an
        // element specified by $locator.
        $element = $this->driver->findElement($locator);

        if ($element->getAttribute('type') == 'file') {
            // Separate handling of file input elements, as they need a file
            // detector (see: https://github.com/facebook/php-webdriver/wiki/Upload-a-file)
            // Also, calling click() on the file input element would open a
            // dialog on some platforms, and getting the value of the file
            // input element is also problematic (at least in firefox).
            $element->setFileDetector(new LocalFileDetector());
            $element->sendKeys($sText);
            return;
        }

        if (!is_null($element->getAttribute('readonly'))) {
            // Try to remove the readonly attribute from the field (usually password fields).
            $this->removeAttribute($element, 'readonly');
        }

        // Make sure we get focus on the element.
        $element->click();

        // Try to clear field and set value for a time period. This is needed
        // for some fields that are set read-only until they get focus, then
        // the first sendKeys() calls may be ignored as javascript has to be
        // executed.
        $this->waitUntil(function ($driver) use ($element, $sText) {
            $element->clear();
            $element->sendKeys($sText);
            return $element->getAttribute('value') == $sText;
        });
    }


    protected function setCheckBoxValue ($locator, $bSetChecked)
    {
        // Set checkbox specified by $locator to 'checked' if $bSetChecked or
        // not 'checked' otherwise.
        $element = $this->driver->findElement($locator);
        $nCount = 0;

        while (($bSetChecked && is_null($element->getAttribute('checked'))) ||
               (!$bSetChecked && !is_null($element->getAttribute('checked')))) {
            if ($nCount > 0) {
                fwrite(STDERR, 'Failed attempt setting checkbox (' . $locator->getMechanism() .
                               '="' . $locator->getValue() . '")"');
                if ($nCount >= MAX_TRIES_CHECKING_BOX) {
                    fwrite(STDERR, 'Failed setting checkbox ' . MAX_TRIES_CHECKING_BOX .
                                   ' times, skipping!');
                    break;
                }
            }
            $nCount += 1;
            $element->click();
        }
    }


    protected function check ($locator)
    {
        $this->setCheckBoxValue($locator, true);
    }


    protected  function unCheck ($locator)
    {
        $this->setCheckBoxValue($locator, false);
    }


    protected function isElementPresent ($locator)
    {
        try {
            $this->driver->findElement($locator);
            return true;
        } catch (NoSuchElementException $e) {
            return false;
        }
    }


    protected function chooseOkOnNextConfirmation ()
    {
        $this->waitUntil(WebDriverExpectedCondition::alertIsPresent());
        $this->driver->switchTo()->alert()->accept();
    }


    protected function getConfirmation ()
    {
        // Return text displayed by confirmation dialog box.
        return $this->driver->switchTo()->alert()->getText();
    }


    protected function removeAttribute ($element, $attrName)
    {
        // Remove attribute in current DOM. For element $element, remove
        // attribute with name $attrName.
        $this->driver->executeScript('arguments[0].removeAttribute(arguments[1]);',
                                     array($element, $attrName));
    }


    protected function clickNoTimeout ($element)
    {
        // Invoke click() on $element, but ignore any potential timeout. This
        // can be used for long page loads where one may want to set an
        // explicit wait limit later in the code.
        try {
            $element->click();
        } catch (WebDriverException $e) {
            if (strpos($e->getMessage(), 'Operation timed out') === false) {
                // Not a timeout, but a different reason for failing, rethrow
                // the exception.
                throw $e;
            }
        }
    }
}
