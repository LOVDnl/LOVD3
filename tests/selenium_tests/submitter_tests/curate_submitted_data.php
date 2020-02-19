<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class CurateSubmittedDataTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testCurateSubmittedData()
    {
        $element = $this->driver->findElement(WebDriverBy::id("tab_variants"));
        $element->click();

        // Move mouse to Variants tab and click 'view all genomic variants' option.
        $tabElement = $this->driver->findElement(WebDriverBy::id("tab_variants"));
        $this->driver->getMouse()->mouseMove($tabElement->getCoordinates());
        $allVariantsLink = $this->driver->findElement(WebDriverBy::partialLinkText('View all genomic variants'));
        $allVariantsLink->click();


        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::linkText("0000000001"));
        $element->click();

        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/0000000001($|#)/', $this->driver->getCurrentURL()));
        $this->assertEquals("Pending", $this->driver->findElement(WebDriverBy::xpath("//tr[12]/td/span"))->getText());
        $element = $this->driver->findElement(WebDriverBy::id("viewentryOptionsButton_Variants"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::linkText("Publish (curate) variant entry"));
        $element->click();

        $this->assertEquals("Public", $this->driver->findElement(WebDriverBy::xpath("//tr[12]/td/span"))->getText());
        $element = $this->driver->findElement(WebDriverBy::linkText("00000001"));
        $element->click();

        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals\/00000001$/', $this->driver->getCurrentURL()));
        $this->assertEquals("Pending", $this->driver->findElement(WebDriverBy::xpath("//tr[8]/td"))->getText());
        $element = $this->driver->findElement(WebDriverBy::id("viewentryOptionsButton_Individuals"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::linkText("Publish (curate) individual entry"));
        $element->click();

        $this->assertEquals("Public", $this->driver->findElement(WebDriverBy::xpath("//tr[8]/td"))->getText());

        // Move mouse to Variants tab and click 'view all genomic variants' option.
        $tabElement = $this->driver->findElement(WebDriverBy::id("tab_variants"));
        $this->driver->getMouse()->mouseMove($tabElement->getCoordinates());
        $allVariantsLink = $this->driver->findElement(WebDriverBy::partialLinkText('View all genomic variants'));
        $allVariantsLink->click();

        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::linkText("0000000002"));
        $element->click();

        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/0000000002($|#)/', $this->driver->getCurrentURL()));
        $this->assertEquals("Pending", $this->driver->findElement(WebDriverBy::xpath("//tr[12]/td/span"))->getText());
        $element = $this->driver->findElement(WebDriverBy::id("viewentryOptionsButton_Variants"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::linkText("Publish (curate) variant entry"));
        $element->click();

        $this->assertEquals("Public", $this->driver->findElement(WebDriverBy::xpath("//tr[12]/td/span"))->getText());
    }
}
