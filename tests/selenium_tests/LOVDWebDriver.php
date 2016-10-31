<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-10-26
 * Modified    : 2016-10-31
 * For LOVD    : 3.0-18
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

require_once 'RefreshingWebDriverElement.php';

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
        $raw_element = $this->execute(
            DriverCommand::FIND_ELEMENT,
            $params
        );

        // Create a RefreshingWebElement and set resources needed to let the
        // element refresh in the future.
        $element = new RefreshingWebElement($this->getExecuteMethod(), $raw_element['ELEMENT']);
        $element->setLocator($by);
        $element->setWebDriver($this);
        return $element;
    }





    public function scrollToElement (WebDriverElement $element, $bAtTop=true)
    {
        // Let the browser scroll such that the given element is in the
        // viewport. If $bAtTop is true, the element will be located at the top
        // of the viewport, otherwise it will be at the bottom.

        // Note: passing $sAtTop as an argument does not give the expected result.
        $sAtTop = ($bAtTop) ? 'true' : 'false';
        $this->executeScript('arguments[0].scrollIntoView(' . $sAtTop . ');', array($element));
    }
}

