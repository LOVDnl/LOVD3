<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-05-07
 * Modified    : 2020-11-17
 * For LOVD    : 3.0-26
 *
 * Copyright   : 2004-2020 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

require_once 'src/inc-lib-init.php'; // For the dependency on lovd_getVariantInfo().
require_once 'src/inc-lib-variants.php'; // For lovd_fixHGVS().

class FixHGVSTest extends PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider dataProviderFixHGVS
     */
    public function testFixHGVS ($sInput, $sOutput)
    {
        // Test lovd_fixHGVS() with data from
        // dataProviderFixHGVS().
        $this->assertEquals(lovd_fixHGVS($sInput), $sOutput);
    }


    public static function dataProviderFixHGVS ()
    {
        // Data provider for testFixHGVS().
        return array(
            // HGVS OK, returns input.
            array('g.123A>C', 'g.123A>C'),
            array('g.123del', 'g.123del'),
            array('g.123dup', 'g.123dup'),
            // Add prefix when missing.
            array('123del', 'g.123del'),
            // Conversions that should be delins variants.
            array('g.100_200con400_500', 'g.100_200delins400_500'),
            // Unneeded parentheses.
            array('g.(100_200)del', 'g.100_200del'),
            // Swaps positions when needed.
            array('g.200_100dup', 'g.100_200dup'),
            array('g.500_(100_200)del', 'g.(100_200)_500del'),
            array('g.(400_500)_100del', 'g.100_(400_500)del'),
            // Correct RNA-like descriptions.
            array('c.4780delinsgagagauu', 'c.4780delinsGAGAGATT'),
        );
    }
}
