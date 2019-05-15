<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class ConfirmVariantToIVAIndividualTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testConfirmVariantToIVAIndividual()
    {
        // wait for page redirect
        $this->waitUntil(WebDriverExpectedCondition::titleContains("Submission of"));

        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000002$/', $this->driver->getCurrentURL()));

        $element = $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*Are you sure you are done with submitting the variants found with this screening[\s\S]*$/', $this->getConfirmation()));
        $this->chooseOkOnNextConfirmation();

        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/individual\/00000002$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings[\s\S]create&target=00000002$/', $this->driver->getCurrentURL()));
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="Screening/Template[]"]/option[text()="RNA (cDNA)"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="Screening/Template[]"]/option[text()="Protein"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="Screening/Technique[]"]/option[text()="Single Base Extension"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="Screening/Technique[]"]/option[text()="Single-Strand DNA Conformation polymorphism Analysis (SSCP)"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="Screening/Technique[]"]/option[text()="SSCA, fluorescent (SSCP)"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="genes[]"]/option[text()="IVD (isovaleryl-CoA dehydrogenase)"]'));
        $option->click();
        $this->check(WebDriverBy::name("variants_found"));
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="owned_by"]/option[text()="LOVD3 Admin (#00001)"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create screening information entry']"));
        $element->click();
        $this->assertEquals("Successfully created the screening entry!", $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());

        // wait for page redirect
        $this->waitUntil(WebDriverExpectedCondition::titleContains("Submission of"));

        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000003$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000003$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings\/0000000003[\s\S]confirmVariants$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::id("check_0000000141"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Save variant list']"));
        $element->click();

        $this->waitUntil(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::xpath('//td[text()="Successfully confirmed the variant entry!"]')));
    }
}
