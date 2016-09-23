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

use \Facebook\WebDriver\Exception\NoSuchElementException;
use \Facebook\WebDriver\Exception\WebDriverException;
use \Facebook\WebDriver\Remote\LocalFileDetector;
use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;



abstract class LOVDSeleniumWebdriverBaseTestCase extends PHPUnit_Framework_TestCase
{
    // Base class for all Selenium tests.

    // public webdriver instance.
    public $driver;



    protected function check ($locator)
    {
        $this->setCheckBoxValue($locator, true);
    }



    protected function chooseOkOnNextConfirmation ()
    {
        $this->waitUntil(WebDriverExpectedCondition::alertIsPresent());
        $this->driver->switchTo()->alert()->accept();
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



    protected function getConfirmation ()
    {
        // Return text displayed by confirmation dialog box.
        return $this->driver->switchTo()->alert()->getText();
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
        $this->enterValue(WebDriverBy::name('username'), $sUsername);
        $this->enterValue(WebDriverBy::name('password'), $sPassword);
        $element = $this->driver->findElement(WebDriverBy::xpath('//input[@value="Log in"]'));
        usleep(100000); // If not waiting at all, sometimes you're just not logged in, for some reason.
        $element->click();

        // We're seeing a small number of failed logins, reasons unknown. Since
        //  we rely already on the login form to forward logged in users, we'll
        //  just retry this function untill we've gotten in.
        $nSleepMax = 2000000; // Wait 2 seconds second in total.
        $nSlept = 0;
        $nSleepStep = 500000; // Sleep for half a second, each time.
        while (substr($this->driver->getCurrentURL(), -10) == '/src/login') {
            usleep($nSleepStep);
            $nSlept += $nSleepStep;
            if ($nSlept >= $nSleepMax && substr($this->driver->getCurrentURL(), -10) == '/src/login') {
                // Failed log in, let's try again.
                print('Failed log in attempt, trying again...' . "\n");
                return $this->login($sUsername, $sPassword);
            }
        }
//        // To make sure we've left the login form, check the URL.
//        // Wait a maximum of 5 seconds with intervals of 500ms, until our test is true.
//        $this->driver->wait(5, 500)->until(function ($driver) {
//            return substr($driver->getCurrentURL(), -10) != '/src/login';
//        });
    }



    protected function logout ()
    {
        // Logs user in, using the given username and password. When already
        //  logged in, this function will log you out. This may be done more
        //  intelligently later, having this function check if you're already
        //  the requested user.

        $this->driver->get(ROOT_URL . '/src/logout');
        // Test for the "Log in" link to be shown. This link below
        //  will already throw an exception if it fails.
        $this->driver->findElement(WebDriverBy::xpath('//a/b[text()="Log in"]'));
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



    protected function removeAttribute ($element, $attrName)
    {
        // Remove attribute in current DOM. For element $element, remove
        // attribute with name $attrName.
        $this->driver->executeScript('arguments[0].removeAttribute(arguments[1]);',
            array($element, $attrName));
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



    protected function setUp ()
    {
        // This method is called before every test invocation.
        $this->driver = getWebDriverInstance();
    }



    protected  function unCheck ($locator)
    {
        $this->setCheckBoxValue($locator, false);
    }



    protected function waitUntil ($condition)
    {
        // Convenience function to let the webdriver wait for a standard amount
        // of time on the given condition.
        return $this->driver->wait(WEBDRIVER_MAX_WAIT_DEFAULT, WEBDRIVER_POLL_INTERVAL_DEFAULT)
                            ->until($condition);
    }
}
