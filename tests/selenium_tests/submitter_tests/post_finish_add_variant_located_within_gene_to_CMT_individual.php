<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class PostFinishAddVariantLocatedWithinGeneToCMTIndividualTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testPostFinishAddVariantLocatedWithinGeneToCMTIndividual()
    {
        // Wait for redirect
        $this->waitUntil(WebDriverExpectedCondition::titleContains("View genomic variant"));

        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/0000000003$/', $this->driver->getCurrentURL()));

        // Move mouse to Screenings tab and click 'view all screenings' option.
        $tabElement = $this->driver->findElement(WebDriverBy::id("tab_screenings"));
        $this->driver->getMouse()->mouseMove($tabElement->getCoordinates());
        $allScreeningsLink = $this->driver->findElement(WebDriverBy::partialLinkText('View all screenings'));
        $allScreeningsLink->click();
        
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings$/', $this->driver->getCurrentURL()));
//        $element = $this->driver->findElement(WebDriverBy::cssSelector("#0000000002 > td.ordered"));
        $element = $this->driver->findElement(WebDriverBy::xpath("//td[text()='0000000002']"));
        $element->click();
        
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings\/0000000002$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::id("viewentryOptionsButton_Screenings"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::linkText("Add variant to screening"));
        $element->click();
        
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000002$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//table[2]/tbody/tr/td[2]/b"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::cssSelector("td.ordered"));
        $element->click();
        
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&reference=Transcript&geneid=GJB1&target=0000000002$/', $this->driver->getCurrentURL()));
        $this->uncheck(WebDriverBy::name("ignore_00000001"));
        $this->enterValue(WebDriverBy::name("00000001_VariantOnTranscript/Exon"), "2");
        $this->enterValue(WebDriverBy::name("00000001_VariantOnTranscript/DNA"), "c.251T>A");
        $element = $this->driver->findElement(WebDriverBy::cssSelector("button.mapVariant"));
        $element->click();
        sleep(6);
        $RnaChange = $this->driver->executeScript("return window.document.getElementById('variantForm').elements[4].value");
        $this->assertTrue((bool)preg_match('/^r\.\([\s\S]\)$/', $RnaChange));
        $ProteinChange = $this->driver->executeScript("return window.document.getElementById('variantForm').elements[5].value");
        $this->assertEquals("p.(Val84Asp)", $ProteinChange);
        $GenomicDnaChange = $this->driver->executeScript("return window.document.getElementById('variantForm').elements[10].value");
        $this->assertEquals("g.70443808T>A", $GenomicDnaChange);
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="00000001_effect_reported"]/option[text()="Effect unknown"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="allele"]/option[text()="Paternal (confirmed)"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::linkText("PubMed"));
        $element->click();

        // Move mouse to let browser hide tooltip of pubmed link (needed for chrome)
        $this->driver->getMouse()->mouseMove(null, 200, 200);

        $this->enterValue(WebDriverBy::name("VariantOnGenome/Reference"), "{PMID:[2011]:[2150333]}");
        $this->enterValue(WebDriverBy::name("VariantOnGenome/Frequency"), "0.09");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="effect_reported"]/option[text()="Effect unknown"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create variant entry']"));
        $element->click();
        
        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/',
            $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText()));
        
    }
}
