<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2021-07-14
 * Modified    : 2021-07-16
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
        // To prevent a Risky test, we have to do at least one assertion.
        $this->assertEquals('', '');
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





    /**
     * @depends testAPIRoot
     */
    public function testServiceInfo ()
    {
        $sResult = file_get_contents(
            ROOT_URL . '/src/api/ga4gh/service-info', false, stream_context_create(
            array(
                'http' => array(
                    'method' => 'GET',
                    'user_agent' => 'LOVD/phpunit',
                    'follow_location' => 0,
                ))));
        $aResult = json_decode($sResult, true);

        $this->assertRegExp('/^HTTP\/1\.. 200 OK$/', $http_response_header[0]);
        $this->assertStringStartsWith('nl.lovd.ga4gh.', $aResult['id']);
        $this->assertStringStartsWith('GA4GH Data Connect API', $aResult['name']);
        $this->assertEquals('org.ga4gh', $aResult['type']['group']);
        $this->assertEquals('service-registry', $aResult['type']['artifact']);
        $this->assertEquals(array(
            'name' => 'Leiden Open Variation Database (LOVD)',
            'url' => 'https://lovd.nl/',
        ), $aResult['organization']);
    }





    /**
     * @depends testServiceInfo
     */
    public function testTables ()
    {
        $sResult = file_get_contents(
            ROOT_URL . '/src/api/ga4gh/tables', false, stream_context_create(
            array(
                'http' => array(
                    'method' => 'GET',
                    'user_agent' => 'LOVD/phpunit',
                    'follow_location' => 0,
                ))));
        $aResult = json_decode($sResult, true);

        $this->assertRegExp('/^HTTP\/1\.. 200 OK$/', $http_response_header[0]);
        $this->assertArrayHasKey('tables', $aResult);
        $this->assertCount(1, $aResult['tables']);
        $this->assertEquals('variants', $aResult['tables'][0]['name']);
        $this->assertArrayHasKey('data_model', $aResult['tables'][0]);
        $this->assertArrayHasKey('$ref', $aResult['tables'][0]['data_model']);
        $sDataModel = @file_get_contents($aResult['tables'][0]['data_model']['$ref']);
        $this->assertStringStartsWith('{', $sDataModel);
        $aDataModel = @json_decode($sDataModel, true);
        $this->assertTrue(is_array($aDataModel));
        $this->assertArrayHasKey('$schema', $aDataModel);
        $this->assertArrayHasKey('title', $aDataModel);
        $this->assertEquals('Variant', $aDataModel['title']);
    }





    /**
     * @depends testTables
     */
    public function testTableVariants ()
    {
        $sResult = file_get_contents(
            ROOT_URL . '/src/api/ga4gh/table/variants', false, stream_context_create(
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
        $this->assertRegExp('/^Location: ' . preg_quote(ROOT_URL, '/') . '\/src\/api\/v[0-9]\/ga4gh\/table\/variants\/data$/',
            $aResult['messages'][0]);
    }





    /**
     * @depends testTableVariants
     */
    public function testTableVariantsInfo ()
    {
        $sResult = file_get_contents(
            ROOT_URL . '/src/api/ga4gh/table/variants/info', false, stream_context_create(
            array(
                'http' => array(
                    'method' => 'GET',
                    'user_agent' => 'LOVD/phpunit',
                    'follow_location' => 0,
                ))));
        $aResult = json_decode($sResult, true);

        $this->assertRegExp('/^HTTP\/1\.. 200 OK$/', $http_response_header[0]);
        $this->assertArrayHasKey('name', $aResult);
        $this->assertEquals('variants', $aResult['name']);
        $this->assertArrayHasKey('data_model', $aResult);
    }





    /**
     * @depends testTableVariantsInfo
     */
    public function testTableVariantsData ()
    {
        $sResult = file_get_contents(
            ROOT_URL . '/src/api/ga4gh/table/variants/data', false, stream_context_create(
            array(
                'http' => array(
                    'method' => 'GET',
                    'user_agent' => 'LOVD/phpunit',
                    'follow_location' => 0,
                ))));
        $aResult = json_decode($sResult, true);

        $this->assertRegExp('/^HTTP\/1\.. 200 OK$/', $http_response_header[0]);
        $this->assertArrayHasKey('data_model', $aResult);
        $this->assertArrayHasKey('data', $aResult);
        $this->assertCount(0, $aResult['data']);
        $this->assertArrayHasKey('pagination', $aResult);
        $this->assertArrayHasKey('next_page_url', $aResult['pagination']);
        $this->assertCount(1, $aResult['pagination']);
        $this->assertRegExp('/^' . preg_quote(ROOT_URL, '/') . '\/src\/api\/v[0-9]\/ga4gh\/table\/variants\/data%3Ahg[0-9]{2}%3Achr1$/',
            $aResult['pagination']['next_page_url']);
    }





    /**
     * @depends testTableVariants
     */
    public function testTableVariantsDataChr15 ()
    {
        $sResult = file_get_contents(
            ROOT_URL . '/src/api/ga4gh/table/variants/data:hg19:chr15', false, stream_context_create(
            array(
                'http' => array(
                    'method' => 'GET',
                    'user_agent' => 'LOVD/phpunit',
                    'follow_location' => 0,
                ))));
        $aResult = json_decode($sResult, true);

        $this->assertRegExp('/^HTTP\/1\.. 200 OK$/', $http_response_header[0]);
        $this->assertArrayHasKey('data_model', $aResult);
        $this->assertArrayHasKey('data', $aResult);
        $this->assertCount(2, $aResult['data']);
        $this->assertArrayHasKey('pagination', $aResult);
        $this->assertArrayHasKey('next_page_url', $aResult['pagination']);
        $this->assertCount(1, $aResult['pagination']);
        $this->assertRegExp('/^' . preg_quote(ROOT_URL, '/') . '\/src\/api\/v[0-9]\/ga4gh\/table\/variants\/data%3Ahg[0-9]{2}%3Achr16%3A1$/',
            $aResult['pagination']['next_page_url']);

        // Now compare the two files.
        // Removing \/git from the path of next_page_url to make sure
        //  that local tests and remote tests have the same string.
        $this->assertEquals(
            trim(file_get_contents(ROOT_PATH . '../tests/test_data_files/AdminTestSuiteResult-GA4GH.txt')),
            str_replace('\/git', '',
                preg_replace('/\b[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\+[0-9]{2}:[0-9]{2}\b/', '0000-00-00T00:00:00+00:00', trim($sResult)))
        );
    }
}
?>
