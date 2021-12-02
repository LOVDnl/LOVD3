<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2017-08-18
 * Modified    : 2021-11-29
 * For LOVD    : 3.0-28
 *
 * Copyright   : 2004-2021 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : M. Kroon <m.kroon@lumc.nl>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Loes Werkman <L.Werkman@LUMC.nl>
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

if (PHP_OS == 'WINNT') {
    chdir('C:/Users/loesj/Documents/LUMC/git/LOVD3');
}


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
        $this->assertEquals($aOutput, lovd_getVariantInfo($sInput));
    }





    /**
     * @dataProvider dataProviderGetVariantInfo
     */
    public function testGetVariantInfoHGVS ($sInput, $aOutput)
    {
        // Test lovd_getVariantInfo with data from
        // dataProviderGetVariantInfo(), but only as an HGVS check.
        if (empty($aOutput['errors']) && (empty($aOutput['warnings']) || empty(array_diff(array_keys($aOutput['warnings']),
                        array('WNOTSUPPORTED', 'WPOSITIONSLIMIT', 'WTRANSCRIPTFOUND', 'WDIFFERENTTRANSCRIPT')))
            )) {
            $bHGVS = true;
        } else {
            $bHGVS = false;
        }
        $this->assertEquals($bHGVS, lovd_getVariantInfo($sInput, false, true));
    }





    public static function dataProviderGetVariantInfo ()
    {
        // Data provider for testGetVariantInfo().
        return array(
            // Prefixes.
            array('g.123dup', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => 'dup',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('m.123dup', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => 'dup',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('c.123dup', array('position_start' => 123,
                'position_end' => 123,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => 'dup',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('n.123dup', array(
                'position_start' => 123,
                'position_end' => 123,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => 'dup',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('g.*123dup', array(
                'position_start' => 0,
                'position_end' => 0,
                'type' => 'dup',
                'warnings' => array(),
                'errors' => array(
                    'EFALSEUTR' => 'Only coding variants can describe positions in the UTR. Position "*123" is therefore invalid when using the "g" prefix.',
                ),
            )),
            array('m.123+4_124-20dup', array(
                'position_start' => 123,
                'position_end' => 124,
                'type' => 'dup',
                'warnings' => array(),
                'errors' => array(
                    'EFALSEINTRONIC' => 'Only transcript-based variants (c. or n. prefixes) can describe intronic positions.',
                ),
            )),

            // Substitutions.
            array('g.123A>C', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => 'subst',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('g.123_124A>C', array(
                'position_start' => 123,
                'position_end' => 124,
                'type' => 'subst',
                'warnings' => array(),
                'errors' => array(
                    'EPOSITIONFORMAT' => 'Too many positions are given for variant type substitution.'
                ),
            )),
            array('g.123A>GC', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => 'subst',
                'warnings' => array(
                    'WWRONGTYPE' =>
                        'A substitution should be a change of one base to one base. Did you mean a deletion-insertion?',
                ),
                'errors' => array(),
            )),
            array('g.123A>Ciets', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => 'subst',
                'warnings' => array(
                    'WSUFFIXGIVEN' => 'Nothing should follow "A>C".',
                    'WSUFFIXFORMAT' => 'The inserted/affected sequence does not follow HGVS guidelines.',
                ),
                'errors' => array(),
            )),

            // Duplications.
            array('g.123dup', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => 'dup',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('g.123_170dup', array(
                'position_start' => 123,
                'position_end' => 170,
                'type' => 'dup',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('g.123_125dupACG', array(
                'position_start' => 123,
                'position_end' => 125,
                'type' => 'dup',
                'warnings' => array(
                    'WSUFFIXGIVEN' => 'Nothing should follow "dup".'
                ),
                'errors' => array(),
            )),

            // Deletions.
            array('g.1_300del', array(
                'position_start' => 1,
                'position_end' => 300,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('g.1delA', array(
                'position_start' => 1,
                'position_end' => 1,
                'type' => 'del',
                'warnings' => array(
                    'WSUFFIXGIVEN' => 'Nothing should follow "del".',
                ),
                'errors' => array(),
            )),

            // Insertions.
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
            array('g.1_2ins5_10', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'ins',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('g.1insA', array(
                'position_start' => 1,
                'position_end' => 1,
                'type' => 'ins',
                'warnings' => array(
                    'EPOSITIONMISSING' =>
                        'An insertion must be provided with the two positions between which the insertion has taken place.',
                ),
                'errors' => array(),
            )),
            array('g.1_1insA', array(
                'position_start' => 1,
                'position_end' => 1,
                'type' => 'ins',
                'warnings' => array(),
                'errors' => array(
                    'EPOSITIONFORMAT' => 'The start and end positions of any range should not be the same.',
                ),
            )),
            array('g.1_2ins', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'ins',
                'warnings' => array(),
                'errors' => array(
                    'ESUFFIXMISSING' => 'The inserted sequence must be provided for insertions or deletion-insertions.',
                ),
            )),
            array('g.(1_2)insA', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'ins',
                'warnings' => array(
                    'EPOSITIONFORMAT' =>
                        'The two positions do not indicate a range. Please remove the parentheses if the positions are certain.',
                ),
                'errors' => array(),
                'messages' => array(
                    'IPOSITIONRANGE' => 'The exact position of this variant is uncertain.',
                ),
            )),
            array('g.(1_10)insA', array(
                'position_start' => 1,
                'position_end' => 10,
                'type' => 'ins',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IPOSITIONRANGE' => 'The exact position of this variant is uncertain.',
                ),
            )),
            array('g.1_10insA', array(
                'position_start' => 1,
                'position_end' => 10,
                'type' => 'ins',
                'warnings' => array(
                    'EPOSITIONFORMAT' =>
                        'An insertion must have taken place between two neighboring positions. If the exact ' .
                        'location is unknown, please indicate this by placing parentheses around the positions.',
                ),
                'errors' => array(),
            )),

            // Deletion-insertions.
            array('g.1_5delinsACT', array(
                'position_start' => 1,
                'position_end' => 5,
                'type' => 'delins',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('g.1delinsA', array(
                'position_start' => 1,
                'position_end' => 1,
                'type' => 'delins',
                'warnings' => array(
                    'WWRONGTYPE' => 'A deletion-insertion of one base to one base should be described as a substitution.',
                ),
                'errors' => array(),
            )),

            // Repeat sequences.
            array('g.1_2ACT[20]', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'repeat',
                'warnings' => array(
                    'WNOTSUPPORTED' => 'Repeat variants are currently not supported for mapping and validation.',
                ),
                'errors' => array(),
            )),
            array('c.1_2ACT[20]', array(
                'position_start' => 1,
                'position_end' => 2,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => 'repeat',
                'warnings' => array(
                    'WNOTSUPPORTED' => 'Repeat variants are currently not supported for mapping and validation.',
                ),
                'errors' => array(),
            )),
            array('c.1_2AC[20]', array(
                'position_start' => 1,
                'position_end' => 2,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => 'repeat',
                'warnings' => array(
                    'WNOTSUPPORTED' => 'Repeat variants are currently not supported for mapping and validation.',
                    'WINVALIDREPEATLENGTH' => 'A repeat sequence of coding DNA should always have a length of (a multiple of) 3.',
                ),
                'errors' => array(),
            )),
            array('g.1_2AC[20]', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'repeat',
                'warnings' => array(
                    'WNOTSUPPORTED' => 'Repeat variants are currently not supported for mapping and validation.',
                ),
                'errors' => array(),
            )),

            // Wildtypes.
            array('g.=', array(
                'position_start' => 0,
                'position_end' => 0,
                'type' => '=',
                'warnings' => array(),
                'errors' => array(
                    'EMISSINGPOSITIONS' => 'When using "=", always provide the position(s) that are unchanged.',
                ),
            )),
            array('g.123=', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => '=',
                'warnings' => array(),
                'errors' => array(),
            )),

            // Unknown variants.
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

            // Unsure variants.
            array('g.(1_2ins(50))', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'ins',
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
            array('g.((1_2insA)', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'ins',
                'warnings' => array(
                    'WPARENTHESES' => 'The variant description contains unbalanced parentheses.'
                ),
                'errors' => array(),
            )),

            // Positions with question marks.
            array('g.?del', array(
                'position_start' => 1,
                'position_end' => 4294967295,  // Fixme; Are we sure about this?
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IUNKNOWNPOSITIONS' => 'This variant contains unknown positions.'
                ),
            )),
            array('g.1_?del', array(
                'position_start' => 1,
                'position_end' => 4294967295,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IUNKNOWNPOSITIONS' => 'This variant contains unknown positions.'
                ),
            )),
            array('g.?_100del', array(
                'position_start' => 1,
                'position_end' => 100,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IUNKNOWNPOSITIONS' => 'This variant contains unknown positions.'
                ),
            )),
            array('g.?_?del', array(
                'position_start' => 1,
                'position_end' => 4294967295,
                'type' => 'del',
                'warnings' => array(
                    'WTOOMUCHUNKNOWN' => 'Redundant question marks were found. Please rewrite the positions ?_? to ?.',
                ),
                'errors' => array(),
                'messages' => array(
                    'IUNKNOWNPOSITIONS' => 'This variant contains unknown positions.',
                ),
            )),
            array('g.(?_?)del', array(
                'position_start' => 1,
                'position_end' => 4294967295,
                'type' => 'del',
                'warnings' => array(
                    'WTOOMUCHUNKNOWN' => 'Redundant question marks were found. Please rewrite the positions (?_?) to ?.',
                ),
                'errors' => array(
                    'ESUFFIXMISSING' => 'The length must be provided for variants which took place within a range.',
                ),
                'messages' => array(
                    'IUNKNOWNPOSITIONS' => 'This variant contains unknown positions.',
                    'IPOSITIONRANGE' => 'The exact position of this variant is uncertain.',
                ),
            )),
            array('g.(?_5)_10del', array(
                'position_start' => 5,
                'position_end' => 10,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IUNKNOWNPOSITIONS' => 'This variant contains unknown positions.'
                ),
            )),
            array('g.(5_?)_10del', array(
                'position_start' => 5,
                'position_end' => 10,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IUNKNOWNPOSITIONS' => 'This variant contains unknown positions.'
                ),
            )),
            array('g.(?_?)_10del', array(
                'position_start' => 1,
                'position_end' => 10,
                'type' => 'del',
                'warnings' => array(
                    'WTOOMUCHUNKNOWN' => 'Redundant question marks were found. Please rewrite the positions (?_?)_10 to ?_10.',
                ),
                'errors' => array(),
                'messages' => array(
                    'IUNKNOWNPOSITIONS' => 'This variant contains unknown positions.',
                ),
            )),
            array('g.5_(10_?)del', array(
                'position_start' => 5,
                'position_end' => 10,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IUNKNOWNPOSITIONS' => 'This variant contains unknown positions.'
                ),
            )),
            array('g.5_(?_10)del', array(
                'position_start' => 5,
                'position_end' => 10,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IUNKNOWNPOSITIONS' => 'This variant contains unknown positions.'
                ),
            )),
            array('g.5_(?_?)del', array(
                'position_start' => 5,
                'position_end' => 4294967295,
                'type' => 'del',
                'warnings' => array(
                    'WTOOMUCHUNKNOWN' => 'Redundant question marks were found. Please rewrite the positions 5_(?_?) to 5_?.',
                ),
                'errors' => array(),
                'messages' => array(
                    'IUNKNOWNPOSITIONS' => 'This variant contains unknown positions.',
                ),
            )),
            array('g.(?_5)_(10_?)del', array(
                'position_start' => 5,
                'position_end' => 10,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IUNKNOWNPOSITIONS' => 'This variant contains unknown positions.'
                ),
            )),
            array('g.(5_?)_(?_10)del', array(
                'position_start' => 5,
                'position_end' => 10,
                'type' => 'del',
                'warnings' => array(
                    'WTOOMUCHUNKNOWN' => 'Redundant question marks were found. Please rewrite the positions (5_?)_(?_10) to (5_10).',
                ),
                'errors' => array(),
                'messages' => array(
                    'IUNKNOWNPOSITIONS' => 'This variant contains unknown positions.'
                ),
            )),
            array('g.(?_?)_(?_?)del', array(
                'position_start' => 1,
                'position_end' => 4294967295,
                'type' => 'del',
                'warnings' => array(
                    'WTOOMUCHUNKNOWN' => 'Redundant question marks were found. Please rewrite the positions (?_?)_(?_?) to ?.',
                ),
                'errors' => array(),
                'messages' => array(
                    'IUNKNOWNPOSITIONS' => 'This variant contains unknown positions.'
                ),
            )),

            // Challenging positions.
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
                    'WPOSITIONFORMAT' => 'The start and end positions of any range should not be the same.'
                ),
                'errors' => array(),
            )),
            array('g.2_1del', array (
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'del',
                'warnings' => array(
                    'WPOSITIONFORMAT' => 'The positions are not given in the correct order.'
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

            // Challenging insertions.
            array('g.1_2ins(5_10)', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'ins',
                'warnings' => array(
                    'WSUFFIXFORMAT' => 'The inserted/affected sequence does not follow HGVS guidelines.',
                ),
                'errors' => array(),
            )),
            array('g.1_2ins[A]', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'ins',
                'warnings' => array(
                    'WSUFFIXFORMAT' => 'The inserted/affected sequence does not follow HGVS guidelines.',
                ),
                'errors' => array(),
            )),
            array('g.1_2ins[NC_123456.1:g.1_10;A;123_125;TGCG]', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'ins',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('g.1_2ins[1_2;A]', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'ins',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('g.1_2insNC123456.1:g.1_10', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'ins',
                'warnings' => array(
                    'WSUFFIXFORMAT' => 'The inserted/affected sequence does not follow HGVS guidelines.',
                ),
                'errors' => array(),
            )),
            array('g.1_2ins340', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'ins',
                'warnings' => array(
                    'WSUFFIXFORMAT' => 'The inserted/affected sequence does not follow HGVS guidelines.',
                ),
                'errors' => array(),
            )),
            array('g.1_2ins[123', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'ins',
                'warnings' => array(
                    'WSUFFIXFORMAT' => 'The inserted/affected sequence contains unbalanced square brackets.',
                ),
                'errors' => array(),
            )),

            // Other affected sequences as suffixes.
            array('g.1delA', array(
                'position_start' => 1,
                'position_end' => 1,
                'type' => 'del',
                'warnings' => array(
                    'WSUFFIXGIVEN' => 'Nothing should follow "del".',
                ),
                'errors' => array(),
            )),
            array('g.(1_100)delA', array(
                'position_start' => 1,
                'position_end' => 100,
                'type' => 'del',
                'warnings' => array(
                    'WSUFFIXFORMAT' => 'The length of the variant is not formatted conform the HGVS guidelines.',
                ),
                'errors' => array(),
                'messages' => array(
                    'IPOSITIONRANGE' => 'The exact position of this variant is uncertain.',
                ),
            )),
            array('g.(1_100)del(30)', array(
                'position_start' => 1,
                'position_end' => 100,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IPOSITIONRANGE' => 'The exact position of this variant is uncertain.',
                ),
            )),
            array('g.1inv(30)', array(
                'position_start' => 1,
                'position_end' => 1,
                'type' => 'inv',
                'warnings' => array(
                    'WSUFFIXGIVEN' => 'Nothing should follow "inv".',
                ),
                'errors' => array(),
            )),
            array('g.(1_100)inv(30)', array(
                'position_start' => 1,
                'position_end' => 100,
                'type' => 'inv',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IPOSITIONRANGE' => 'The exact position of this variant is uncertain.',
                ),
            )),
            array('g.1ACT[20]A', array(
                'position_start' => 1,
                'position_end' => 1,
                'type' => 'repeat',
                'warnings' => array(
                    'WNOTSUPPORTED' => 'Repeat variants are currently not supported for mapping and validation.',
                    'WSUFFIXGIVEN' => 'Nothing should follow "ACT[20]".',
                ),
                'errors' => array(),
            )),
            array('g.(1_100)ACT[20]A', array(
                'position_start' => 1,
                'position_end' => 100,
                'type' => 'repeat',
                'warnings' => array(
                    'WNOTSUPPORTED' => 'Repeat variants are currently not supported for mapping and validation.',
                    'WSUFFIXGIVEN' => 'Nothing should follow "ACT[20]".',
                ),
                'errors' => array(),
                'messages' => array(
                    'IPOSITIONRANGE' => 'The exact position of this variant is uncertain.',
                ),
            )),

            // Descriptions that are currently unsupported.
            array('[g.1_qter]del', array(
                'position_start' => 0,
                'position_end' => 0,
                'type' => '',
                'warnings' => array(),
                'errors' => array(
                    'ENOTSUPPORTED' => 'Currently, variants using "qter" are not yet supported.',
                ),
            )),
            array('[g.1_cen]del', array(
                'position_start' => 0,
                'position_end' => 0,
                'type' => '',
                'warnings' => array(),
                'errors' => array(
                    'ENOTSUPPORTED' => 'Currently, variants using "cen" are not yet supported.',
                ),
            )),
            array('[g.1_pter]del', array(
                'position_start' => 0,
                'position_end' => 0,
                'type' => '',
                'warnings' => array(),
                'errors' => array(
                    'ENOTSUPPORTED' => 'Currently, variants using "pter" are not yet supported.',
                ),
            )),
            array('n.5-2::10-3', array(
                'position_start' => 0,
                'position_end' => 0,
                'type' => '',
                'warnings' => array(),
                'errors' => array(
                    'ENOTSUPPORTED' => 'Currently, variants using "::" are not yet supported.',
                ),
            )),
        );
    }
}
?>
