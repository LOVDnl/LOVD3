<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2015-02-17
 * Modified    : 2020-06-17
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

class CreateUserManagerTest extends LOVDSeleniumWebdriverBaseTestCase
{
    protected function setUp (): void
    {
        // Test if we have what we need for this test. If not, skip this test.
        parent::setUp();
        // Manager is user ID 2.
        $this->driver->get(ROOT_URL . '/src/users/00002');
        $sBody = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();
        if (preg_match('/LOVD was not installed yet/', $sBody)) {
            $this->markTestSkipped('LOVD was not installed yet.');
        }
        if (preg_match('/To access this area/', $sBody)) {
            $this->markTestSkipped('User was not authorized.');
        }
        if (!preg_match('/No such ID!/', $sBody)) {
            $this->markTestSkipped('User was already created.');
        }
    }





    public function test ()
    {
        $this->driver->get(ROOT_URL . "/src/users?create&no_orcid");
        $this->enterValue('name', 'Test Manager');
        $this->enterValue('institute', 'Leiden University Medical Center');
        $this->enterValue('department', 'Human Genetics');
        $this->enterValue('address', "Einthovenweg 20\n2333 ZC Leiden");
        $this->enterValue('email', 'manager@lovd.nl');
        $this->enterValue('username', 'manager');
        $this->enterValue('password_1', 'test1234');
        $this->enterValue('password_2', 'test1234');
        $this->selectValue('countryid', 'Netherlands');
        $this->enterValue('city', 'Leiden');
        $this->selectValue('level', 'Manager');
        $this->unCheck('send_email');
        $this->enterValue('password', 'test1234');
        $this->submitForm('Create user');
        $this->assertEquals("Successfully created the user account!",
            $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());
    }
}
?>
