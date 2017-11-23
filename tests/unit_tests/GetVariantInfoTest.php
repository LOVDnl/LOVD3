<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2017-08-18
 * Modified    : 2017-11-23
 * For LOVD    : 3.0-21
 *
 * Copyright   : 2017 Leiden University Medical Center; http://www.LUMC.nl/
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


require_once 'src/inc-lib-init.php';

class GetVariantInfoTest extends PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider dataProviderGetVariantInfo
     */
    public function testGetVariantInfo ($sInput, $aOutput)
    {
        // Test lovd_getVariantInfo with data from
        // dataProviderGetVariantInfo().
        $this->assertEquals(lovd_getVariantInfo($sInput), $aOutput);
    }


    public static function dataProviderGetVariantInfo ()
    {
        // Data provider for testGetVariantInfo().
        // Fixme: extend below with more complex variant descriptions.
        return array(
            array('g.123dup', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => 'dup'
            )),
            array('c.123dup', array(
                'position_start' => 123,
                'position_end' => 123,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => 'dup'
            )),
            array('m.123dup', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => 'dup'
            )),
            array('n.123dup', array(
                'position_start' => 123,
                'position_end' => 123,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => 'dup'
            )),
            array('g.(?_112043201)_(112181937_?)del', array(
                'position_start' => 112043201,
                'position_end' => 112181937,
                'type' => 'del'
            )),
        );
    }
}
