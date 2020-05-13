<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-05-13
 * Modified    : 2020-05-13
 * For LOVD    : 3.0-24
 *
 * Copyright   : 2004-2020 Leiden University Medical Center; http://www.LUMC.nl/
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

class CheckPHPVersionTest extends LOVDSeleniumWebdriverBaseTestCase
{
    protected function setUp()
    {
        // Test if we have what we need for this test. If not, skip this test.
        // NOTE: Do NOT use getLOVDGlobals() before LOVD is installed!
        parent::setUp();
        $this->driver->get(ROOT_URL . '/src/install');
        $bodyElement = $this->driver->findElement(WebDriverBy::tagName('body'));
        if (preg_match('/This installer will create/', $bodyElement->getText())) {
            // Not installed already, all good!
            return true;
        }

        // We're installed already!
        $this->markTestSkipped('LOVD was installed already.');
    }





    public function test ()
    {
        // Travis' PHP version may not be our PHP version.
        // Apache is configured to use the PHP module installed with APT,
        //  which may be something completely different than
        //  the cli version which currently powers PHPUnit.
        // Also, it's good to just have the MySQL version.
        $this->driver->get(ROOT_URL . '/src/install');
        $aSystemInformation = explode("\n",
            $infoBox = $this->driver->findElement(WebDriverBy::xpath('//table[@class="info"]/tbody/tr/td[@valign="middle"]'))->getText());
        // Just print PHP and MySQL version, I don't care about the rest.
        print(PHP_EOL . implode(PHP_EOL,
                array(
                    $aSystemInformation[2],
                    $aSystemInformation[5],
                )) . PHP_EOL);
    }
}
