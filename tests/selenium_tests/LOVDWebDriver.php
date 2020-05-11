<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-10-26
 * Modified    : 2020-05-11
 * For LOVD    : 3.0-24
 *
 * Copyright   : 2016-2020 Leiden University Medical Center; http://www.LUMC.nl/
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

require_once 'RefreshingWebDriverElement.php';

use \Facebook\WebDriver\Exception\NoSuchElementException;
use \Facebook\WebDriver\Remote\DriverCommand;
use \Facebook\WebDriver\Remote\RemoteWebDriver;
use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverElement;


class LOVDWebDriver extends RemoteWebDriver {
    /**
     * Sub class of RemoteWebDriver. Overloading its findElement method
     * to make use of RefreshingWebDriverElement.
     */

    public function findElement (WebDriverBy $by)
    {
        // This method is similar to RemoteWebDriver::findElement() but
        // returns an instance of RefreshingWebElement.
        $params = array('using' => $by->getMechanism(), 'value' => $by->getValue());

        // Try up to 1 second to find the element.
        $t = microtime(true);
        while ((microtime(true) - $t) < 1) {
            try {
                $raw_element = $this->execute(
                    DriverCommand::FIND_ELEMENT,
                    $params
                );
                break;
            } catch (NoSuchElementException $e) {
                usleep(100000);
            }
        }
        if (!isset($raw_element)) {
            throw $e;
        }

        // Create a RefreshingWebElement and set resources needed to let the
        // element refresh in the future.
        $element = new RefreshingWebElement($this->getExecuteMethod(), current($raw_element));
        $element->setLocator($by);
        $element->setWebDriver($this);
        return $element;
    }





    public function findElements (WebDriverBy $by)
    {
        // This method is similar to RemoteWebDriver::findElements() but
        //  it fixes the RemoteWebDriver's bug of using the wrong index.
        // Undefined index: ELEMENT.
        // Caused by using $raw_element['ELEMENT'], which doesn't exist.
        $params = array('using' => $by->getMechanism(), 'value' => $by->getValue());

        $raw_elements = $this->execute(
            DriverCommand::FIND_ELEMENTS,
            $params
        );

        $elements = array();
        foreach ($raw_elements as $raw_element) {
            $elements[] = $this->newElement(current($raw_element));
        }

        return $elements;
    }





    public function scrollToElement (WebDriverElement $element, $bAtTop = true)
    {
        // Let the browser scroll such that the given element is in the
        // viewport. If $bAtTop is true, the element will be located at the top
        // of the viewport, otherwise it will be at the bottom.

        // Note: Passing $sAtTop as an argument to executeScript() doesn't seem to do what is expected.
        $sAtTop = ($bAtTop? 'true' : 'false');
        $this->executeScript('arguments[0].scrollIntoView(' . $sAtTop . ');', array($element));
    }
}

