<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class PostFinishAddVariantOnlyDescribedOnGenomicLevelToIVAIndividualTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testPostFinishAddVariantOnlyDescribedOnGenomicLevelToIVAIndividual()
    {
        $this->driver->get(ROOT_URL . "/src");
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
        $element = $this->driver->findElement(WebDriverBy::xpath("//table[2]/tbody/tr[2]/td[2]/b"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&reference=Genome&target=0000000002$/', $this->driver->getCurrentURL()));
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="allele"]/option[text()="Paternal (confirmed)"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="chromosome"]/option[text()="15"]'));
        $option->click();
        $this->enterValue(WebDriverBy::name("VariantOnGenome/DNA"), "g.40702876G>T");
        $element = $this->driver->findElement(WebDriverBy::linkText("PubMed"));
        $element->click();
        $this->enterValue(WebDriverBy::name("VariantOnGenome/Reference"), "{PMID:[2011]:[21520333]}");
        $this->enterValue(WebDriverBy::name("VariantOnGenome/Frequency"), "11/10000");
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
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/0000000333$/', $this->driver->getCurrentURL()));
    }
}
