<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class PostFinishAddVariantLocatedWithinTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testPostFinishAddVariantLocatedWithinTest()
    {
        $element = $this->driver->findElement(WebDriverBy::id("tab_screenings"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings\/IVD$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::cssSelector("#0000000002 > td.ordered"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings\/0000000002$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::id("viewentryOptionsButton_Screenings"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::linkText("Add variant to screening"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000002$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//table[2]/tbody/tr[1]/td[2]/b"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::cssSelector("td.ordered"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&reference=Transcript&geneid=IVD&target=0000000002$/', $this->driver->getCurrentURL()));
        $this->uncheck(WebDriverBy::name("ignore_00000001"));
        $this->enterValue(WebDriverBy::name("00000001_VariantOnTranscript/Exon"), "2");
        $this->enterValue(WebDriverBy::name("00000001_VariantOnTranscript/DNA"), "c.345G>T");
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

        $RnaChange = $this->getEval("window.document.getElementById('variantForm').elements[4].value");
        $this->assertTrue((bool)preg_match('/^r\.\([\s\S]\)$/', $this->getExpression($RnaChange)));
        $ProteinChange = $this->getEval("window.document.getElementById('variantForm').elements[5].value");
        $this->assertEquals("p.(Met115Ile)", $this->getExpression($ProteinChange));
        $GenomicDnaChange = $this->getEval("window.document.getElementById('variantForm').elements[10].value");
        $this->assertEquals("g.40702876G>T", $this->getExpression($GenomicDnaChange));
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="00000001_effect_reported"]/option[text()="Effect unknown"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="00000001_effect_concluded"]/option[text()="Effect unknown"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="allele"]/option[text()="Paternal (confirmed)"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::linkText("PubMed"));
        $element->click();
        $this->enterValue(WebDriverBy::name("VariantOnGenome/Reference"), "{PMID:[2011]:[2150333]}");
        $this->enterValue(WebDriverBy::name("VariantOnGenome/Frequency"), "0.05");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="effect_reported"]/option[text()="Effect unknown"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="effect_concluded"]/option[text()="Effect unknown"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="owned_by"]/option[text()="LOVD3 Admin (#00001)"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="statusid"]/option[text()="Public"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create variant entry']"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/', $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText()));
        $element->click();
        $this->assertContains("/src/variants/0000000334", $this->driver->getCurrentURL());
    }
}
