<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-09-27
 * Modified    : 2020-06-12
 * For LOVD    : 3.0-24
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


use \Facebook\WebDriver\Exception\StaleElementReferenceException;
use \Facebook\WebDriver\Exception\UnknownServerException;
use \Facebook\WebDriver\Remote\RemoteWebElement;
use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriver;


class RefreshingWebElement extends RemoteWebElement {
    /**
     * RefreshingWebElement keeps track of the locator that was used to
     * retrieve it from the web page. Whenever a StaleElementReference
     * exception is thrown, it tries to reload the element using the
     * locator.
     *
     * This strategy tries to overcome stale elements resulting from unforeseen
     * re-rendering of the DOM instead of expected element staleness for
     * example due to page reloading.
     */

    // WebDriver instance used for refreshing the element.
    protected $driver;

    // Locator used to generate $element (of WebDriverBy type).
    protected $locator;


    public function clear ()
    {
        return $this->tryWithRefresh('clear');
    }


    public function click ()
    {
        try {
            // This sometimes fails with a "UnrecognizedExceptionException", even with a catch.
            // When that happens, scroll into view first (see check_HGVS_interface.php).
            return $this->tryWithRefresh('click');
        } catch (UnknownServerException $e) {
            if (strpos($e->getMessage(), 'Element is not clickable at point') !== false) {
                // Try to scroll the element into view at bottom of viewport
                // and click it again.
                fwrite(STDERR, 'Scrolling element into view, locator = "' .
                    $this->locator->getValue() . '" (' . $this->locator->getMechanism() . ')' .
                    PHP_EOL);
                // "false" indicates we will scroll to have this element
                //  at the *bottom* of the page, not the top.
                $this->driver->scrollToElement($this, false);
                return $this->tryWithRefresh('click');
            }
            // Otherwise rethrow the unknown exception.
            throw $e;
        }
    }


    public function getAttribute ($sAttribute)
    {
        return $this->tryWithRefresh('getAttribute', array($sAttribute));
    }


    public function getText ()
    {
        return $this->tryWithRefresh('getText');
    }


    private function refresh ()
    {
        // Refresh this element by re-running findElement() using the locator.

        if (isset($this->locator) && isset($this->driver)) {
            $newElement = $this->driver->findElement($this->locator);

            // Overwrite ID of current element with new one.
            $this->id = $newElement->id;
            return true;
        }

        // Cannot refresh element without locator or driver.
        return false;
    }


    public function sendKeys ($value)
    {
        return $this->tryWithRefresh('sendKeys', array($value));
    }


    public function setWebDriver (WebDriver $driver)
    {
        // Set webdriver instance to be used for refreshing element.
        $this->driver = $driver;
    }


    public function setLocator (WebDriverBy $locator)
    {
        // Set locator to be used for refreshing element.
        $this->locator = $locator;
    }


    private function tryWithRefresh ($sParentMethod, $args=array())
    {
        // Call method of the parent class with method name $sParentMethod and
        // contents of array $args as arguments. If the method call results in
        // a stale element reference exception, it will try a number of times
        // to refresh the element and call the method again.
        $aFunction = array(get_parent_class($this), $sParentMethod);
        $e = null;
        for ($i = 0; $i < MAX_TRIES_STALE_REFRESH; $i++) {
            try {
                return call_user_func_array($aFunction, $args);
            } catch (StaleElementReferenceException $e) {
                // Refresh the element so we can try again.
                if (!$this->refresh()) {
                    // Refresh failed, can't do anything better than to re-throw the exception.
                    throw $e;
                }
            }
        }
        // Too many tries, rethrow the exception.
        throw $e;
    }
}


