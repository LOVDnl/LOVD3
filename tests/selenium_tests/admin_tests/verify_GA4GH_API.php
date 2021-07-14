<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2021-07-14
 * Modified    : 2021-07-14
 * For LOVD    : 3.0-27
 *
 * Copyright   : 2004-2021 Leiden University Medical Center; http://www.LUMC.nl/
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

class VerifyGA4GHAPITest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testSetUp ()
    {
        // A normal setUp() runs for every test in this file. We only need this once,
        //  so we disguise this setUp() as a test that we depend on just once.
        $this->driver->get(ROOT_URL . '/src/genes/IVD');
        $sBody = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();
        if (preg_match('/LOVD was not installed yet/', $sBody)) {
            $this->markTestSkipped('LOVD was not installed yet.');
        }
        if (preg_match('/No such ID!/', $sBody)) {
            $this->markTestSkipped('Gene does not exist yet.');
        }
    }





    /**
     * @depends testSetUp
     */
    public function testAPIRoot ()
    {
        $sResult = file_get_contents(
            ROOT_URL . '/src/api/ga4gh', false, stream_context_create(
                array(
                    'http' => array(
                        'method' => 'GET',
                        'user_agent' => 'LOVD/phpunit',
                        'follow_location' => 0,
                    ))));
        $aResult = json_decode($sResult, true);

        $this->assertRegExp('/^HTTP\/1\.. 302 (Moved Temporarily|Found)$/', $http_response_header[0]);
        $this->assertEquals(array(), $aResult['warnings']);
        $this->assertEquals(array(), $aResult['errors']);
        $this->assertEquals(array(), $aResult['data']);
        $this->assertRegExp('/^Location: ' . preg_quote(ROOT_URL, '/') . '\/src\/api\/v[0-9]\/ga4gh\/service-info$/',
            $aResult['messages'][0]);
    }
}
?>
