<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-04-21
 * Modified    : 2016-05-12
 * For LOVD    : 3.0-16
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

require_once 'LOVDSeleniumBaseTestCase.php';

class CreateUsersSubmitterTest extends LOVDSeleniumBaseTestCase
{

    /**
     * @dataProvider userRecords
     */
    public function testCreateUserSubmitter($sName, $sEmail, $sUsername, $sPassword)
    {
        $this->open(ROOT_URL . "/src/users?create&no_orcid");
        $this->type("name=name", $sName);
        $this->type("name=institute", "Leiden University Medical Center");
        $this->type("name=department", "Human Genetics");
        $this->type("name=address", "Einthovenweg 20\n2333 ZC Leiden");
        $this->type("name=email", $sEmail);
        $this->type("name=username", $sUsername);
        $this->type("name=password_1", $sPassword);
        $this->type("name=password_2", $sPassword);
        $this->select("name=countryid", "label=Netherlands");
        $this->type("name=city", "Leiden");
        $this->select("name=level", "Submitter");
        $this->click("name=send_email");
        $this->type("name=password", "test1234");
        $this->click("//input[@value='Create user']");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the user account!", $this->getText("css=table[class=info]"));
    }


    public static function userRecords()
    {
        // Example user information records.
        return array(
            array('Test Submitter1', 'example1@example.com', 'testsubmitter1', 'testsubmitter1'),
            array('Test Submitter2', 'example2@example.com', 'testsubmitter2', 'testsubmitter2'),
            array('Test Submitter3', 'example3@example.com', 'testsubmitter3', 'testsubmitter3')
        );
    }
}
