<?php
//require_once 'PHPUnit/Extensions/SeleniumTestCase.php';

class second_tests extends PHPUnit_Extensions_SeleniumTestCase
{
    protected function setUp()
    {
        $this->setBrowser('*firefox');
        $this->setBrowserUrl('http://localhost/');
        $this->shareSession(true);
    }

    public function testLoadPageAgain()
    {
        $this->open('http://localhost/LOVD3/tests/travis/simpleTest.html');
        $this->waitForPageToLoad ( "30000" );
        $this->assertTitle('phpunit selenium test');
    }
}
?>