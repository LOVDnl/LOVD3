<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-08-30
 * Modified    : 2020-06-04
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
use \Facebook\WebDriver\WebDriverExpectedCondition;

class CreateAnnouncementMakingLOVDReadOnlyTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testSetUp ()
    {
        $this->driver->get(ROOT_URL . '/src/announcements?create');
        $sBody = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();
        if (preg_match('/LOVD was not installed yet/', $sBody)) {
            $this->markTestSkipped('LOVD was not installed yet.');
        }
        if (preg_match('/To access this area, you need at least/', $sBody)) {
            $this->markTestSkipped('User was not authorized.');
        }
    }





    /**
     * @depends testSetUp
     */
    public function testCreateAnnouncementMakingLOVDReadOnly ()
    {
        // Create an announcement, that switches LOVD into the read-only state.
        // This test assumes you're logged in as manager or admin.
        $this->driver->get(ROOT_URL . '/src/announcements?create');
        $sAnnouncement = 'This is a test announcement. LOVD will be closed for registrations, and lower level users can not log in.';
        $this->selectValue('type', 'Warning');
        $this->enterValue('announcement', $sAnnouncement);
        $this->enterValue('start_date', ''); // No value, means active from now().
        $this->enterValue('end_date', ''); // No value, means active until '9999-12-31 23:59:59'.
        $this->check('lovd_read_only');
        $this->enterValue('password', 'test1234');
        $this->submitForm('Create announcement');

        $this->assertEquals('Successfully created the announcement!',
            $this->driver->findElement(WebDriverBy::xpath('//table[@class="info" and contains(., "Success")]'))->getText());
        $this->assertEquals($sAnnouncement, $this->driver->findElement(
            WebDriverBy::cssSelector('table[class=info]'))->getText());
        $this->waitUntil(WebDriverExpectedCondition::urlContains('/src/announcements/0000'));
    }
}
