<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class AddSummaryVariantLocatedWithinGeneTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testAddSummaryVariantLocatedWithinGene()
    {

        // Wait for page redirect.
        $this->waitUntil(WebDriverExpectedCondition::titleContains("View individual"));

        // Mouse hover over Submit tab, to make 'submit new data' link visible.
        $tabElement = $this->driver->findElement(WebDriverBy::xpath("//img[@id='tab_submit']"));
        $this->driver->getMouse()->mouseMove($tabElement->getCoordinates());

        // Click 'submit new data' link.
        $element = $this->driver->findElement(WebDriverBy::xpath("//ul[@id='menu_tab_submit']/li/a"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*Please reconsider to submit individual data as well, as it makes the data you submit much more valuable![\s\S]*$/', $this->getConfirmation()));
        $this->chooseOkOnNextConfirmation();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//tr[@id='ARSD']/td[2]"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&reference=Transcript&geneid=ARSD$/', $this->driver->getCurrentURL()));
        for ($second = 0; ; $second++) {
            if ($second >= 60) $this->fail("timeout");
            try {
                if ($this->isElementPresent(WebDriverBy::name("ignore_00000002"))) break;
            } catch (Exception $e) {
            }
            sleep(1);
        }
        $this->uncheck(WebDriverBy::name("ignore_00000002"));
        $this->uncheck(WebDriverBy::name("ignore_00000003"));
        $this->enterValue(WebDriverBy::name("00000002_VariantOnTranscript/Exon"), "3");
        $this->enterValue(WebDriverBy::name("00000003_VariantOnTranscript/Exon"), "3");
        $this->enterValue(WebDriverBy::name("00000002_VariantOnTranscript/DNA"), "c.62T>C");
        $element = $this->driver->findElement(WebDriverBy::cssSelector("button.mapVariant"));
        $element->click();
        sleep(3);
        for ($second = 0; ; $second++) {
            if ($second >= 60) $this->fail("timeout");
            try {
                if ($this->isElementPresent(WebDriverBy::cssSelector("img[alt='Prediction OK!']"))) break;
            } catch (Exception $e) {
            }
            sleep(1);
        }
        $RnaChange = $this->driver->executeScript("return window.document.getElementById('variantForm').elements[4].value");
        $this->assertTrue((bool)preg_match('/^r\.\([\s\S]\)$/', $RnaChange));
        $ProteinChange = $this->driver->executeScript("return window.document.getElementById('variantForm').elements[5].value");
        $this->assertEquals("p.(Leu21Pro)", $ProteinChange);
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="00000002_effect_reported"]/option[text()="Probably affects function"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="00000002_effect_concluded"]/option[text()="Probably does not affect function"]'));
        $option->click();
        $RnaChangeTwo = $this->driver->executeScript("return window.document.getElementById('variantForm').elements[4].value");
        $this->assertTrue((bool)preg_match('/^r\.\([\s\S]\)$/', $RnaChangeTwo));
        $ProteinChangeTwo = $this->driver->executeScript("return window.document.getElementById('variantForm').elements[5].value");
        $this->assertEquals("p.(Leu21Pro)", $ProteinChangeTwo);
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="00000003_effect_reported"]/option[text()="Probably affects function"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="00000003_effect_concluded"]/option[text()="Probably does not affect function"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="allele"]/option[text()="Maternal (confirmed)"]'));
        $option->click();
        $GenomicDnaChange = $this->driver->executeScript("return window.document.getElementById('variantForm').elements[19].value");
        $this->assertEquals("g.2843789A>G", $GenomicDnaChange);
        $element = $this->driver->findElement(WebDriverBy::linkText("PubMed"));
        $element->click();
        $this->enterValue(WebDriverBy::name("VariantOnGenome/Reference"), "{PMID:[2011]:[2150333]}");
        $this->enterValue(WebDriverBy::name("VariantOnGenome/Frequency"), "55/18000");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="effect_reported"]/option[text()="Affects function"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="effect_concluded"]/option[text()="Affects function"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="owned_by"]/option[text()="LOVD3 Admin (#00001)"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="statusid"]/option[text()="Public"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create variant entry']"));
        $element->click();

        $this->waitUntil(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector("table[class=info]")));
        $this->assertContains("Successfully processed your submission and sent an email notification to the relevant curator", $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());

        // wait for page redirect
        $this->waitUntil(WebDriverExpectedCondition::titleContains("View genomic variant"));

        $this->assertContains("src/variants/0000000168", $this->driver->getCurrentURL());
    }
}
