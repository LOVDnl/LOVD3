<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class CreateGenderColumnTest extends LOVDSeleniumBaseTestCase
{
    public function testCreateGenderColumn()
    {
        $this->open(ROOT_URL . "/src/columns/Individual/Gender");
        $this->click("id=viewentryOptionsButton_Columns");
        $this->click("link=Enable column");
        $this->waitForPageToLoad("30000");
        $this->type("name=password", "test1234");
        $this->click("//input[@value='Add/enable custom data column Individual/Gender']");
        $this->waitForPageToLoad("30000");
    }
}
