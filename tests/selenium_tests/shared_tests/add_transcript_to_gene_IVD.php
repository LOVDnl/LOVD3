<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2024-05-23
 * Modified    : 2024-05-23
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

class AddTranscriptToGeneIVDTest extends LOVDSeleniumWebdriverBaseTestCase
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
        if (!$this->isElementPresent(WebDriverBy::xpath('//a[contains(@href, "users/0000")]/b[text()="Your account"]'))) {
            $this->markTestSkipped('User was not authorized.');
        }
        $this->assertStringNotContainsString('NM_001159508.1', $sBody);
    }





    /**
     * @depends testSetUp
     */
    public function testAddTranscript ()
    {
        $this->driver->findElement(WebDriverBy::id('viewentryOptionsButton_Genes'))->click();
        $this->driver->findElement(WebDriverBy::linkText('Add transcript(s) to gene'))->click();
        $eUL = $this->waitforElement(WebDriverBy::name('active_transcripts[]'));
        $this->assertStringContainsString('NM_001159508.1', $eUL->getText());

        $this->driver->findElement(WebDriverBy::xpath('//option[@value="NM_001159508.1"]'))->click();
        $this->submitForm('Add transcript(s) to gene');
        $this->assertEquals('Successfully added the transcript(s) to gene IVD',
            $this->driver->findElement(WebDriverBy::cssSelector('table[class=info]'))->getText());
        $this->waitForURLEndsWith('/src/genes/IVD');
        $this->assertStringContainsString('NM_001159508.1', $this->driver->findElement(WebDriverBy::xpath('//table[@id="viewlistTable_Transcripts_for_G_VE"]/tbody'))->getText());
    }
}
?>
