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
            // > VARIANTS WHICH DON'T NEED FIXING.
            array('g.123dup', 'g.123dup'),
            array('g.1_300del', array(
                'position_start' => 1,
                'position_end' => 300,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('g.1_2insA', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'ins',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('g.1_2ins(50)', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'ins',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('g.1_2ins5_10', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'ins',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('g.1_2ins[NC_123456.1:g.1_10]', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'ins',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('g.1_5delinsACT', array(
                'position_start' => 1,
                'position_end' => 5,
                'type' => 'delins',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('g.1_2ACT[20]', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'repeat',
                'warnings' => array(
                    'WNOTSUPPORTED' => 'Repeat variants are currently not supported for mapping and validation.',
                ),
                'errors' => array(),
            )),
            array('g.=', array(
                'position_start' => 0,
                'position_end' => 0,
                'type' => '=',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('g.123=', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => '=',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('c.?', array(
                'position_start' => 0,
                'position_end' => 0,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => NULL,
                'warnings' => array(),
                'errors' => array(),
            )),
            array('c.123?', array(
                'position_start' => 123,
                'position_end' => 123,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => NULL,
                'warnings' => array(),
                'errors' => array(),
            )),
            array('g.((1_5)ins(50))', array(
                'position_start' => 1,
                'position_end' => 5,
                'type' => 'ins',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IPOSITIONRANGE' => 'The exact position of this variant is uncertain.',
                ),
            )),
            
            
            // > FIXABLE VARIANTS.
            
            // Missing prefixes
            array('123dup', 'g.123dup'),
            array('.123dup', 'c.123dup'),
            array('123-5dup', 'c.123-5dup'),
            
            // Wrong prefixes
            array('g.123-5dup', 'c.123-5dup'),
            array('m.123-5dup', 'n.123-5dup'),
            array('m.*1_*2del', 'n.*1_*2del'),
            
            // Added white spaces
            array('g. 123_124insA', 'g.123_124insA'),
            array(' g.123del', 'g.123del'),
            
            // Lowercase nucleotides
            array('g.123insactg', 'g.123insACTG'),
            array('g.123a>g', 'g.123A>G'),
            
            // U given instead of T
            array('g.123insAUG', 'g.123insATG'),

            // Conversions and substitutions which should be delins variants.
            array('g.123conACTG', 'g.123delinsACTG'),
            array('g.123A>C', 'g.123A>G'),
            array('g.123A>GC', 'g.123delinsGC'),
            
            // Added bases for wildtype
            array('c.123T=', 'c.123='),
            array('c.123_124TG=', 'c.123_124='),
            
            // Floating parentheses
            array('c.((123_125)insA', 'c.(123_125)insA'),
            array('(c.(123_125)insA', 'c.(123_125)insA'),
            
            // Misplaced parentheses
            array('(c.(123_125)insA)', 'c.((123_125)insA)'),
            
            // Redundant parentheses
            array('c.(1_2)insA', 'c.1_2insA'),
            array('c.1_2ins(A)', 'c.1_2insA'),
            array('c.1_2ins[A]', 'c.1_2insA'),

            // Missing parentheses
            array('c.1_100insA', 'c.(1_100)insA'),
            array('c.1_100del(10)', 'c.(1_100)del(10)'),

            // Wrongly placed suffixes
            array('c.123delA', 'c.123del'),
            array('c.(1_100)del(20)', 'c.(1_100)del(20)'),

            // Redundant question marks
            array('g.?del', 'g.?del'),
            array('g.1_?del', 'g.1_?del'),
            array('g.?_100del', 'g.?_100del'),  // Fixme; have another look.
            array('g.?_?del', 'g.?del'),
            array('g.(?_?)del', 'g.?del'),
            array('g.(?_5)_10del', 'g.(?_5)_10'),
            array('g.(5_?)_10del', 'g.(5_?)_10del'), // Fixme; have another look.
            array('g.(?_?)_10del', 'g.?_10del'),
            array('g.5_(10_?)del', 'g.5_(10_?)del'),
            array('g.5_(?_10)del', 'g.5_(?_10)del'), // Fixme; have another look.
            array('g.5_(?_?)del', 'g.5_?del'),
            array('g.(?_5)_(10_?)del', 'g.(?_5)_(10_?)del'),
            array('g.(5_?)_(?_10)del', 'g.(5_10)del'),
            array('g.(?_?)_(?_?)del', 'g.?del'),

            // Challenging positions
            array('g.(100_200)_(400_500)del', array(
                'position_start' => 200,
                'position_end' => 400,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('g.100_(400_500)del', array(
                'position_start' => 100,
                'position_end' => 400,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('g.(100_200)_500del', array(
                'position_start' => 200,
                'position_end' => 500,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('g.1_1del', array (
                'position_start' => 1,
                'position_end' => 1,
                'type' => 'del',
                'warnings' => array(
                    'WPOSITIONFORMAT' => 'Two of the positions are the same.'
                ),
                'errors' => array(),
            )),
            array('g.2_1del', array (
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'del',
                'warnings' => array(
                    'WPOSITIONFORMAT' => 'The position fields were not sorted properly.'
                ),
                'errors' => array(),
            )),
            array('c.10000000_10000001del', array(
                'position_start' => 8388607,
                'position_end' => 8388607,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => 'del',
                'warnings' => array(
                    'WPOSITIONSLIMIT' => 'Positions are beyond the possible limits of their type: position_start, position_end.',
                ),
                'errors' => array(),
            )),

            // Swaps positions when needed.
            array('g.2_1dup', 'g.1_2dup'),
            array('g.(5_1)_6dup', 'g.(1_5)_6dup'),
            array('g.1_(7_5)dup', 'g.1_(5_7)dup'),
            array('g.(7_5)_1dup', 'g.1_(5_7)dup'),
            array('c.5+1_5-1dup', 'g.5-1_5+1dup'),
            

            // > UNFIXABLE VARIANTS
            array('g.1delinsA', false), // Fixme; take another look.
            array('c.1_2AC[20]', false),
            array('c.1_2A>G', false),
            array('c.1insA', false),
            array('c.1_2ins', false),
            array('c.1_10insA', false),
            array('c.1_20insBLA', false),
        );
    }
}
