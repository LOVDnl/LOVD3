<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-10-26
 * Modified    : 2016-10-26
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

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\Remote\DriverCommand;
use \Facebook\WebDriver\Remote\RemoteWebDriver;

class LOVDWebDriver extends RemoteWebDriver {
    /**
     * Custom wrapper for RemoteWebDriver. Overloading its findElement method
     * to make use of RefreshingWebDriverElement.
     */

    public function findElement(WebDriverBy $by)
    {
        // This method is similar to RemoteWebDriver::findElement() but
        // returns an instance of RefreshingWebElement.
        $params = array('using' => $by->getMechanism(), 'value' => $by->getValue());
        $raw_element = $this->execute(
            DriverCommand::FIND_ELEMENT,
            $params
        );

        $element = new RefreshingWebElement($this->getExecuteMethod(), $raw_element['ELEMENT']);
        $element->setLocator($by);
        $element->setWebDriver($this);
        return $element;
    }
}

