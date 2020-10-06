<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2015-02-17
 * Modified    : 2020-10-06
 * For LOVD    : 3.0-25
 *
 * Copyright   : 2004-2020 Leiden University Medical Center; http://www.LUMC.nl/
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

require_once 'inc-lib-test.php';
require_once 'RefreshingWebDriverElement.php';

use \Facebook\WebDriver\Exception\NoAlertOpenException;
use \Facebook\WebDriver\Exception\NoSuchElementException;
use \Facebook\WebDriver\Exception\WebDriverException;
use \Facebook\WebDriver\Remote\LocalFileDetector;
use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;
use \Facebook\WebDriver\WebDriverKeys;





abstract class LOVDSeleniumWebdriverBaseTestCase extends PHPUnit_Framework_TestCase
{
    // Base class for all Selenium tests.

    // public webdriver instance.
    public $driver;





    protected function assertValue ($sValue, $locator)
    {
        // Convenience function to easily check an element's value.
        // For even more convenience, $locator can also just be a string,
        //  in which case we assume it's an element name.
        if (is_string($locator)) {
            $locator = WebDriverBy::name($locator);
        }

        $element = $this->driver->findElement($locator);
        $this->assertEquals($sValue, $element->getAttribute('value'));
    }





    protected function check ($locator)
    {
        $this->setCheckBoxValue($locator, true);
    }





    protected function chooseOkOnNextConfirmation ()
    {
        $this->waitUntil(WebDriverExpectedCondition::alertIsPresent());
        $this->driver->switchTo()->alert()->accept();
    }





    protected function clickButton ($sButtonValue)
    {
        // Convenience function to make easier use of clicking certain buttons.

        // Trigger an NoElementException when the button doesn't exist.
        $element = $this->driver->findElement(WebDriverBy::xpath('//button[contains(text(), "' . $sButtonValue . '")]'));
        $element->click();

        return true;
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





    protected function enterValue ($locator, $sText)
    {
        // Convenience function to let the webdriver type text $text in an
        // element specified by $locator.
        // For even more convenience, $locator can also just be a string,
        //  in which case we assume it's an element name.
        if (is_string($locator)) {
            $locator = WebDriverBy::name($locator);
        }

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
        $this->waitUntil(function () use ($element, $sText) {
            $element->clear();
            $element->sendKeys($sText);
            // If we have sent an Enter, this is not found back in the field,
            //  and we get StaleElement Exceptions. Compensate with rtrim().
            // WebDriverKeys::ENTER == U+E007; mb_ord(57351).
            return ($element->getAttribute('value') == rtrim($sText, WebDriverKeys::ENTER));
        });
    }





    protected function getAllSessionIDs ()
    {
        return array_filter(array_map(
            function ($oCookie)
            {
                list($sName, $sID) = explode('_', $oCookie->getName());
                if ($sName == 'PHPSESSID') {
                    return $sID;
                } else {
                    return false;
                }
            }, $this->driver->manage()->getCookies()));
    }





    protected function getConfirmation ()
    {
        // Return text displayed by confirmation dialog box.
        return $this->driver->switchTo()->alert()->getText();
    }





    protected function isAlertPresent ()
    {
        try {
            $this->driver->switchTo()->alert()->getText();
            return true;
        } catch (NoAlertOpenException $e) {
            return false;
        }
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





    protected function login ($sUsername, $sPassword)
    {
        // Logs user in, using the given username and password. When already
        //  logged in, this function will log you out. This may be done more
        //  intelligently later, having this function check if you're already
        //  the requested user.

        $this->driver->get(ROOT_URL . '/src/login');
        // When already logged in, we will be sent to a different URL by LOVD.
        $sCurrentURL = $this->driver->getCurrentURL();
        if (substr($sCurrentURL, -10) != '/src/login') {
            // Already logged in, log out first!
            $this->logout();
            // If we get the situation where the login form no longer forwards you
            //  if you're already logged in, we'll end up in an endless loop here.
            return $this->login($sUsername, $sPassword);
        }

        // We're now at the login form.
        $this->enterValue('username', $sUsername);
        $this->enterValue('password', $sPassword);
        $element = $this->driver->findElement(WebDriverBy::xpath('//input[@value="Log in"]'));
        usleep(100000); // If not waiting at all, sometimes you're just not logged in, for some reason.
        $element->click();

        // Check...
        // Sometimes the session is somehow lost after logging in.
        // Since we rely already on the login form to forward logged in users,
        //  we'll just retry this function until we've gotten in.
        if (substr($this->driver->getCurrentURL(), -10) != '/src/login') {
            // OK, we're no longer at the login page. Forward back there.
            $this->driver->get(ROOT_URL . '/src/login');

            // Will LOVD forward us again?
            if (substr($this->driver->getCurrentURL(), -10) == '/src/login') {
                // Hmmm... We lost the session somehow. Try again.
                // Warning! This has the potential of an endless loop.
                print("\n" .
                      'Session lost, trying again...');
                return $this->login($sUsername, $sPassword);
            }
        }

        // We're seeing a small number of failed logins, reasons unknown. Since
        //  we rely already on the login form to forward logged in users, we'll
        //  just retry this function until we've gotten in.
        $nSleepMax = 2000000; // Wait 2 seconds second in total.
        $nSlept = 0;
        $nSleepStep = 500000; // Sleep for half a second, each time.
        while (substr($this->driver->getCurrentURL(), -10) == '/src/login') {
            usleep($nSleepStep);
            $nSlept += $nSleepStep;
            if ($nSlept >= $nSleepMax && substr($this->driver->getCurrentURL(), -10) == '/src/login') {
                // Failed log in, let's try again.
                // Warning! This has the potential of an endless loop.
                print("\n" .
                      'Failed log in attempt, trying again...');
                return $this->login($sUsername, $sPassword);
            }
        }
    }





    protected function logout ()
    {
        // Logs user out, usually because we need to log in as somebody else.

        $this->driver->get(ROOT_URL . '/src/logout');

        // Test for the "Log in" link to be shown.
        // findElement() will make the test fail if the element is missing.
        $this->driver->findElement(WebDriverBy::xpath('//a/b[text()="Log in"]'));
    }





    protected function removeAttribute ($element, $attrName)
    {
        // Remove attribute in current DOM. For element $element, remove
        // attribute with name $attrName.
        $this->driver->executeScript('arguments[0].removeAttribute(arguments[1]);',
            array($element, $attrName));
    }





    protected function selectValue ($sName, $sValue)
    {
        // Convenience function to make easier use of selection lists.

        // Trigger an NoElementException when the selection list doesn't exist.
        $this->driver->findElement(WebDriverBy::xpath('//select[@name="' . $sName . '"]'));

        // Check if $sValue is an index or a text value.
        if ($this->isElementPresent(WebDriverBy::xpath('//select[@name="' . $sName . '"]/option[@value="' . $sValue . '"]'))) {
            $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="' . $sName . '"]/option[@value="' . $sValue . '"]'));
        } else {
            $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="' . $sName . '"]/option[text()="' . $sValue . '"]'));
        }
        $option->click();

        return true;
    }





    protected function setCheckBoxValue ($locator, $bSetChecked)
    {
        // Set checkbox specified by $locator to 'checked' if $bSetChecked or
        // not 'checked' otherwise.
        // For even more convenience, $locator can also just be a string,
        //  in which case we assume it's an element name.
        if (is_string($locator)) {
            $locator = WebDriverBy::name($locator);
        }

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





    protected function setUp ()
    {
        // This method is called before every test invocation.
        $this->driver = getWebDriverInstance();
    }





    protected function submitForm ($sButtonValue)
    {
        // Convenience function to make easier use of submitting forms
        //  by pressing certain buttons.

        // Trigger an NoElementException when the submit button doesn't exist.
        $element = $this->driver->findElement(WebDriverBy::xpath('//input[@type="submit" and contains(@value, "' . $sButtonValue . '")]'));
        $this->clickNoTimeout($element);

        return true;
    }





    protected function unCheck ($locator)
    {
        $this->setCheckBoxValue($locator, false);
    }





    protected function waitForElement ($oElement, $nTimeOut = WEBDRIVER_MAX_WAIT_DEFAULT)
    {
        // Convenience function to let the webdriver wait for a standard amount
        //  of time for the given element.
        try {
            return $this->waitUntil(
                WebDriverExpectedCondition::presenceOfElementLocated($oElement), $nTimeOut);
        } catch (NoSuchElementException $e) {
            // Now and then we get this.
            // I'm not sure if WebDriver was waiting or not, but the screenshots
            //  show the element is there, and running this seems to help.
            print(PHP_EOL . 'waitUntil() returned NoSuchElementException. Extending wait...' . PHP_EOL);
            for ($nSeconds = 0; ; $nSeconds ++) {
                if ($nSeconds >= $nTimeOut) {
                    print('Timeout waiting for element to exist after ' . $nSeconds . ' seconds.' . PHP_EOL);
                    throw $e;
                }
                if ($this->isElementPresent($oElement)) {
                    break;
                }
                sleep(1);
            }
        }
    }





    protected function waitForURLContains ($sValue, $nTimeOut = WEBDRIVER_MAX_WAIT_DEFAULT)
    {
        // Convenience function to let the webdriver wait for a standard amount
        //  of time for the URL to contain a certain value.
        return $this->waitUntil(
            WebDriverExpectedCondition::urlContains($sValue), $nTimeOut);
    }





    protected function waitForURLEndsWith ($sValue, $nTimeOut = WEBDRIVER_MAX_WAIT_DEFAULT)
    {
        // Convenience function to let the webdriver wait for a standard amount
        //  of time for the URL to end with a certain value.
        return $this->waitUntil(
            WebDriverExpectedCondition::urlMatches('/' . preg_quote($sValue, '/') . '$/'), $nTimeOut);
    }





    protected function waitForValueContains ($oElement, $sValue, $nTimeOut = WEBDRIVER_MAX_WAIT_DEFAULT)
    {
        // Convenience function to let the webdriver wait for a standard amount
        //  of time for an expected value in the given element.
        if (is_string($oElement)) {
            $oElement = WebDriverBy::xpath('//input[@name="' . $oElement . '"]');
        }

        return $this->waitUntil(
            WebDriverExpectedCondition::elementValueContains($oElement, $sValue), $nTimeOut);
    }





    protected function waitUntil ($condition, $nTimeOut = WEBDRIVER_MAX_WAIT_DEFAULT)
    {
        // Convenience function to let the webdriver wait for a standard amount
        // of time on the given condition.
        return $this->driver->wait($nTimeOut, WEBDRIVER_POLL_INTERVAL_DEFAULT)
                            ->until($condition);
    }
}
?>
