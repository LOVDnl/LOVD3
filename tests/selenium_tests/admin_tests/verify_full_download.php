<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-05-21
 * Modified    : 2024-05-24
 * For LOVD    : 3.0-30
 *
 * Copyright   : 2004-2024 Leiden University Medical Center; http://www.LUMC.nl/
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

class VerifyFullDownloadTest extends LOVDSeleniumWebdriverBaseTestCase
{
    protected function setUp (): void
    {
        parent::setUp();
        $this->driver->get(ROOT_URL . '/src/setup');
        $sBody = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();
        if (preg_match('/LOVD was not installed yet/', $sBody)) {
            $this->markTestSkipped('LOVD was not installed yet.');
        }
        if (preg_match('/To access this area, you need at least/', $sBody)) {
            $this->markTestSkipped('User was not authorized.');
        }
    }





    public function test ()
    {
        // Calling the URL directly sometimes lets FF wait forever until a
        //  timeout, while clicking on the link seems to work better.
        // $this->driver->get(ROOT_URL . '/src/download/all');
        $this->driver->get(ROOT_URL . '/src/setup');

        // Basic verifications. We don't add these to setUp(), because we want
        //  to actually trigger a failure.
        $sStatistics = $this->driver->findElement(
            WebDriverBy::xpath('//table[@class="setup"][1]'))->getText();
        $this->assertStringContainsString('Individuals : 3', $sStatistics);
        $this->assertStringContainsString('Total : 30', $sStatistics);

        // The download location is set to "/tmp"
        //  in getWebDriverInstance() @ inc-lib-test.php.
        $sTempDir = '/tmp/';
        $aFilesBefore = scandir($sTempDir);
        $this->driver->findElement(WebDriverBy::xpath(
            '//table[@class="setup"]//td[contains(text(), "Download all data")]'))->click();
        $this->waitUntil(function () use ($aFilesBefore, $sTempDir) {
            // Let's hope nothing gets deleted now,
            //  and no new files get added that aren't the download file.
            return (count(scandir($sTempDir)) > count($aFilesBefore));
        });
        $aPossibleDownloadFiles = array_diff(scandir($sTempDir), $aFilesBefore);
        $this->assertGreaterThanOrEqual(1, count($aPossibleDownloadFiles));

        if (count($aPossibleDownloadFiles) == 1) {
            $sDownloadFile = current($aPossibleDownloadFiles);
        } else {
            foreach ($aPossibleDownloadFiles as $sFile) {
                // Just assume the first match.
                if (preg_match('/^LOVD_full_download_[0-9_.-]+\.txt$/', $sFile)) {
                    $sDownloadFile = $sFile;
                    break;
                }
            }
        }

        // Now compare the two files.
        $this->assertEquals(
            file_get_contents(ROOT_PATH . '../tests/test_data_files/AdminTestSuiteResult.txt'),
            preg_replace('/^### LOVD-version [0-9]{4}-[0-9a-z]{3} /', '### LOVD-version ????-??? ',
                preg_replace('/\b[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\b/', '0000-00-00 00:00:00', file_get_contents($sTempDir . $sDownloadFile)))
        );
    }
}
?>
