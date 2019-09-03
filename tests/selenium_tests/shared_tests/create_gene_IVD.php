<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class CreateGeneIVDTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testCreateGeneIVD()
    {
        $this->driver->get(ROOT_URL . "/src/genes?create");
        // We get too many random failures here, waiting for the HGNC ID field to appear. The waitUntil() just fails.
        // $this->waitUntil(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::name("hgnc_id")));
        // Facebook\WebDriver\Exception\NoSuchElementException: no such element: Unable to locate element: {"method":"name","selector":"hgnc_id"}
        // This causes everything else to fail as well, and the whole Travis run is then useless.

        // Try this instead. We do this in more places; probably we want to build a function around it.
        for ($second = 0; ; $second++) {
            if ($second >= 60) {
                $this->fail('Timeout waiting for element to exist after ' . $second . ' seconds.');
            }
            try {
                if ($this->isElementPresent(WebDriverBy::name('hgnc_id'))) {
                    break;
                }
            } catch (Exception $e) {
            }
            sleep(1);
        }
        $this->enterValue(WebDriverBy::name("hgnc_id"), "IVD");
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Continue »']"));
        $element->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="active_transcripts[]"]/option[text()="transcript variant 1 (NM_002225.3)"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::name("show_hgmd"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::name("show_genecards"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::name("show_genetests"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create gene information entry']"));
        $element->click();
        $this->assertEquals("Successfully created the gene information entry!", $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());
    }
}
