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
//        $element = $this->driver->findElement(WebDriverBy::cssSelector("#0000000002 > td.ordered"));
        $element = $this->driver->findElement(WebDriverBy::xpath("//td[text()='0000000002']"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings\/0000000002$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::id("viewentryOptionsButton_Screenings"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::linkText("Add variant to screening"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000002$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath('//b[contains(., "A variant that is located within a gene")]'));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::cssSelector("td.ordered"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&reference=Transcript&geneid=IVD&target=0000000002$/', $this->driver->getCurrentURL()));
        $this->uncheck(WebDriverBy::name("ignore_00000001"));
        $this->enterValue(WebDriverBy::name("00000001_VariantOnTranscript/Exon"), "2");
        $this->enterValue(WebDriverBy::name("00000001_VariantOnTranscript/DNA"), "c.345G>T");
        $element = $this->driver->findElement(WebDriverBy::cssSelector("button.mapVariant"));
        $element->click();

        // Wait until RNA description field is filled after AJAX request.
        $firstRNAInputSelector = '(//input[contains(@name, "VariantOnTranscript/RNA")])[1]';
        $this->waitUntil(function ($driver) use ($firstRNAInputSelector) {
            $firstRNAInput = $driver->findElement(WebDriverBy::xpath($firstRNAInputSelector));
            $firstRNAValue = $firstRNAInput->getAttribute('value');
            return !empty($firstRNAValue);
        });

        // Check RNA description for first transcript.
        $firstRNAInput = $this->driver->findElement(WebDriverBy::xpath($firstRNAInputSelector));
        $firstRNAValue = $firstRNAInput->getAttribute('value');
        $this->assertTrue((bool)preg_match('/^r\.\([\s\S]\)$/', $firstRNAValue));

        // Check protein description for first transcript.
        $firstProteinInputSelector = '(//input[contains(@name, "VariantOnTranscript/Protein")])[1]';
        $firstProteinInput = $this->driver->findElement(WebDriverBy::xpath($firstProteinInputSelector));
        $firstProteinValue = $firstProteinInput->getAttribute('value');
        $this->assertEquals("p.(Met115Ile)", $firstProteinValue);

        $GenomicDNAChange = $this->driver->findElement(WebDriverBy::name('VariantOnGenome/DNA'));
        $this->assertEquals("g.40702876G>T", $GenomicDNAChange->getAttribute('value'));

        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="00000001_effect_reported"]/option[text()="Effect unknown"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="00000001_effect_concluded"]/option[text()="Effect unknown"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="allele"]/option[text()="Paternal (confirmed)"]'));
        $option->click();
        $this->enterValue(WebDriverBy::name("VariantOnGenome/Reference"), "{PMID:Fokkema et al (2011):21520333}");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="owned_by"]/option[text()="LOVD3 Admin (#00001)"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="statusid"]/option[text()="Public"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create variant entry']"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/', $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText()));

        // Wait for page redirect.
        $this->waitUntil(WebDriverExpectedCondition::titleContains("Genomic variant"));

        $this->assertContains("/src/variants/0000000334", $this->driver->getCurrentURL());
    }
}
