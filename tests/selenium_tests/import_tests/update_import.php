<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016
 * Modified    : 2016-07-13
 * For LOVD    : 3.0-17
 *
 * Copyright   : 2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : M. Kroon <m.kroon@lumc.nl>
 *
 *
 * This file is part of LOVD.
 *
 * LOVD is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * LOVD is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with LOVD.  If not, see <http://www.gnu.org/licenses/>.
 *
 *************/

require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class UpdatetImportTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testUpdatetImport()
    {
        $this->driver->get(ROOT_URL . "/src/import");
        $this->enterValue(WebDriverBy::name("import"), ROOT_PATH . "../tests/test_data_files/UpdateImport.txt");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="mode"]/option[text()="Update existing data (in beta)"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Import file']"));
        $element->click();
        
        $this->assertTrue((bool)preg_match('/^[\s\S]*The following sections are modified and updated in the database: Columns, Diseases, Individuals, Phenotypes, Screenings, Variants_On_Genome, Variants_On_Transcripts\.$/', $this->driver->findElement(WebDriverBy::id("lovd_sql_progress_message_done"))->getText()));
    }
}
