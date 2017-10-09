<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class AddSummaryVariantLocatedWithinGeneTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testAddSummaryVariantLocatedWithinGene()
    {

        // Wait for page redirect.
        $this->waitUntil(WebDriverExpectedCondition::titleContains("Individual"));

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
        $this->enterValue(WebDriverBy::name("00000004_VariantOnTranscript/Exon"), "3");
        $this->enterValue(WebDriverBy::name("00000005_VariantOnTranscript/Exon"), "3");
        $this->enterValue(WebDriverBy::name("00000002_VariantOnTranscript/DNA"), "c.62T>C");
        $element = $this->driver->findElement(WebDriverBy::cssSelector("button.mapVariant"));
        $element->click();

        // Wait until the first two RNA-change input fields contain data.
        $firstRNAInputSelector = '(//input[contains(@name, "VariantOnTranscript/RNA")])[1]';
        $secondRNAInputSelector = '(//input[contains(@name, "VariantOnTranscript/RNA")])[2]';
        $this->waitUntil(function ($driver) use ($firstRNAInputSelector, $secondRNAInputSelector) {
            $firstRNAInput = $driver->findElement(WebDriverBy::xpath($firstRNAInputSelector));
            $firstRNAValue = $firstRNAInput->getAttribute('value');
            $secondRNAInput = $driver->findElement(WebDriverBy::xpath($secondRNAInputSelector));
            $secondRNAValue = $secondRNAInput->getAttribute('value');
            return !empty($firstRNAValue) && !empty($secondRNAValue);
        });

        $firstRNAInput = $this->driver->findElement(WebDriverBy::xpath($firstRNAInputSelector));
        $firstRNAValue = $firstRNAInput->getAttribute('value');
        $this->assertTrue((bool)preg_match('/^r\.\([\s\S]\)$/', $firstRNAValue));

        $firstProteinInputSelector = '(//input[contains(@name, "VariantOnTranscript/Protein")])[1]';
        $firstProteinInput = $this->driver->findElement(WebDriverBy::xpath($firstProteinInputSelector));
        $firstProteinValue = $firstProteinInput->getAttribute('value');
        $this->assertEquals("p.(Leu21Pro)", $firstProteinValue);

        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="00000002_effect_reported"]/option[text()="Probably affects function"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="00000002_effect_concluded"]/option[text()="Probably does not affect function"]'));
        $option->click();

        $secondRNAInput = $this->driver->findElement(WebDriverBy::xpath($secondRNAInputSelector));
        $secondRNAValue = $secondRNAInput->getAttribute('value');
        $this->assertTrue((bool)preg_match('/^r\.\([\s\S]\)$/', $secondRNAValue));

        $secondProteinInputSelector = '(//input[contains(@name, "VariantOnTranscript/Protein")])[2]';
        $secondProteinInput = $this->driver->findElement(WebDriverBy::xpath($secondProteinInputSelector));
        $secondProteinValue = $secondProteinInput->getAttribute('value');
        $this->assertEquals("p.(Leu21Pro)", $secondProteinValue);

        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="00000003_effect_reported"]/option[text()="Probably affects function"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="00000003_effect_concluded"]/option[text()="Probably does not affect function"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="allele"]/option[text()="Maternal (confirmed)"]'));
        $option->click();

        $GenomicDNAChange = $this->driver->findElement(WebDriverBy::name('VariantOnGenome/DNA'));
        $this->assertEquals("g.2843789A>G", $GenomicDNAChange->getAttribute('value'));
        $element = $this->driver->findElement(WebDriverBy::linkText("PubMed"));
        $element->click();
        $this->enterValue(WebDriverBy::name("VariantOnGenome/Reference"), "{PMID:Fokkema et al (2011):21520333}");
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
        $this->waitUntil(WebDriverExpectedCondition::titleContains("Genomic variant"));

        $this->assertRegExp("/src\/variants\/\d{10}/", $this->driver->getCurrentURL());
    }
}
