<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-03-04
 * Modified    : 2020-05-13
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

require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;

class UninstallLOVDTest extends LOVDSeleniumWebdriverBaseTestCase
{
    protected function setUp ()
    {
        // Test if we have what we need for this test. If not, skip this test.
        parent::setUp();
        $this->driver->get(ROOT_URL . '/src/uninstall');
        $sBody = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();
        if (preg_match('/LOVD was not installed yet/', $sBody)) {
            $this->markTestSkipped('LOVD was not installed yet.');
        }
        if (preg_match('/To access this area/', $sBody)) {
            // We're not admin nor manager.
            $this->logout();
            $this->login('admin', 'test1234');
            print(PHP_EOL . 'Logged in as Admin to complete ' . get_class() . PHP_EOL);
        }
    }





    public function test ()
    {
        $this->driver->get(ROOT_URL . '/src/uninstall');
        $this->enterValue(WebDriverBy::name("password"), "test1234");
        $this->submitForm('Next');

        $this->enterValue(WebDriverBy::name("password"), "test1234");
        $this->submitForm('Uninstall LOVD');
        $this->assertEquals("LOVD successfully uninstalled!\nThank you for having used LOVD!",
            $this->driver->findElement(WebDriverBy::cssSelector("div[id=lovd__progress_message]"))->getText());
    }
}
