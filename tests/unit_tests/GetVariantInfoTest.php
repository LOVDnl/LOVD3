<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2017-08-18
 * Modified    : 2022-02-24
 * For LOVD    : 3.0-28
 *
 * Copyright   : 2004-2022 Leiden University Medical Center; http://www.LUMC.nl/
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

require_once 'src/inc-lib-init.php';

class GetVariantInfoTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider dataProviderGetVariantInfo
     */
    public function testGetVariantInfo ($sInput, $aOutput)
    {
        // Test lovd_getVariantInfo() with data from
        // dataProviderGetVariantInfo().
        $this->assertEquals($aOutput, lovd_getVariantInfo($sInput, false));
    }





    /**
     * @dataProvider dataProviderGetVariantInfo
     */
    public function testGetVariantInfoHGVS ($sInput, $aOutput)
    {
        // Test lovd_getVariantInfo() with data from
        // dataProviderGetVariantInfo(), but only as an HGVS check.
        if (empty($aOutput['errors'])
            && (empty($aOutput['warnings'])
                || empty(array_diff(
                        array_keys($aOutput['warnings']),
                        array('WNOTSUPPORTED', 'WPOSITIONSLIMIT', 'WTRANSCRIPTFOUND', 'WDIFFERENTREFSEQ')))
            )) {
            $bHGVS = true;
        } else {
            $bHGVS = false;
        }
        $this->assertEquals($bHGVS, lovd_getVariantInfo($sInput, false, true));
    }





    public function testGetVariantInfoWithTranscript ()
    {
        // Test lovd_getVariantInfo() with given transcripts. We won't test the
        //  whole list, since that is not necessary.

        // This uses array dereferencing that is only compatible with PHP 5.4.0.
        // It's just so much easier and the tests aren't run on PHP 5.3.0.
        $this->assertArrayHasKey('WTRANSCRIPTFOUND', lovd_getVariantInfo(
            'NM_123456.1:c.100del', 'NM_123456.1')['warnings']);
        $this->assertArrayHasKey('WTRANSCRIPTVERSION', lovd_getVariantInfo(
            'NM_123456.2:c.100del', 'NM_123456.1')['warnings']);
        $this->assertArrayHasKey('WDIFFERENTREFSEQ', lovd_getVariantInfo(
            'NM_123457.2:c.100del', 'NM_123456.1')['warnings']);
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
            array('c.123dup', array(
                'position_start' => 123,
                'position_end' => 123,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
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
            array('n.123dup', array(
                'position_start' => 123,
                'position_end' => 123,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => 'dup',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('g.-123dup', array(
                'position_start' => 0,
                'position_end' => 0,
                'type' => 'dup',
                'warnings' => array(),
                'errors' => array(
                    'EFALSEUTR' => 'Only coding transcripts (c. prefix) have a UTR region. Therefore, position "-123" which describes a position in the 5\' UTR, is invalid when using the "g" prefix.',
                ),
            )),
            array('g.*123dup', array(
                'position_start' => 0,
                'position_end' => 0,
                'type' => 'dup',
                'warnings' => array(),
                'errors' => array(
                    'EFALSEUTR' => 'Only coding transcripts (c. prefix) have a UTR region. Therefore, position "*123" which describes a position in the 3\' UTR, is invalid when using the "g" prefix.',
                ),
            )),
            array('m.123+4_124-20dup', array(
                'position_start' => 123,
                'position_end' => 124,
                'position_start_intron' => 4,
                'position_end_intron' => -20,
                'type' => 'dup',
                'warnings' => array(),
                'errors' => array(
                    'EFALSEINTRONIC' => 'Only transcripts (c. or n. prefixes) have introns. Therefore, this variant description with a position in an intron is invalid when using the "m" prefix.',
                ),
            )),
            array('g.123000-125000dup', array(
                'position_start' => 123000,
                'position_end' => 123000,
                'position_start_intron' => -125000,
                'position_end_intron' => -125000,
                'type' => 'dup',
                'warnings' => array(),
                'errors' => array(
                    'EFALSEINTRONIC' => 'Only transcripts (c. or n. prefixes) have introns. Therefore, this variant description with a position in an intron is invalid when using the "g" prefix. Did you perhaps try to indicate a range? If so, please use an underscore (_) to indicate a range.',
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
            array('g.123.>.', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => 'subst',
                'warnings' => array(),
                'errors' => array(
                    'EWRONGTYPE' => 'This substitution does not seem to contain any data. Please provide bases that were replaced.',
                ),
            )),
            array('g.123_124A>C', array(
                'position_start' => 123,
                'position_end' => 124,
                'type' => 'subst',
                'warnings' => array(),
                'errors' => array(
                    'ETOOMANYPOSITIONS' => 'Too many positions are given; a substitution is used to only indicate single-base changes and therefore should have only one position.'
                ),
            )),
            array('g.123A>GC', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => 'subst',
                'warnings' => array(
                    'WWRONGTYPE' =>
                        'A substitution should be a change of one base to one base. Did you mean to describe a deletion-insertion?',
                ),
                'errors' => array(),
            )),
            array('g.123.>C', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => 'subst',
                'warnings' => array(),
                'errors' => array(
                    'EWRONGTYPE' =>
                        'A substitution should be a change of one base to one base. Did you mean to describe an insertion?',
                ),
            )),
            array('g.123AA>G', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => 'subst',
                'warnings' => array(
                    'WWRONGTYPE' =>
                        'A substitution should be a change of one base to one base. Did you mean to describe a deletion-insertion?',
                ),
                'errors' => array(),
            )),
            array('g.123A>.', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => 'subst',
                'warnings' => array(
                    'WWRONGTYPE' =>
                        'A substitution should be a change of one base to one base. Did you mean to describe a deletion?',
                ),
                'errors' => array(),
            )),
            array('g.123_124AA>GC', array(
                'position_start' => 123,
                'position_end' => 124,
                'type' => 'subst',
                'warnings' => array(
                    'WWRONGTYPE' =>
                        'A substitution should be a change of one base to one base. Did you mean to describe a deletion-insertion?',
                ),
                'errors' => array(
                    'ETOOMANYPOSITIONS' => 'Too many positions are given; a substitution is used to only indicate single-base changes and therefore should have only one position.'
                ),
            )),
            array('g.123_124AAA>GC', array(
                'position_start' => 123,
                'position_end' => 124,
                'type' => 'subst',
                'warnings' => array(
                    'WWRONGTYPE' =>
                        'A substitution should be a change of one base to one base. Did you mean to describe a deletion-insertion?',
                ),
                'errors' => array(
                    'ETOOMANYPOSITIONS' => 'Too many positions are given; a substitution is used to only indicate single-base changes and therefore should have only one position.'
                ),
            )),
            array('g.123A>Ciets', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => 'subst',
                'warnings' => array(
                    'WSUFFIXGIVEN' => 'Nothing should follow "A>C".',
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
            array('g.1_2insN', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'ins',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('g.1_2insN[10]', array(
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
                'warnings' => array(
                    'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines.',
                ),
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
                'warnings' => array(),
                'errors' => array(
                    'EPOSITIONMISSING' =>
                        'An insertion must be provided with the two positions between which the insertion has taken place.',
                ),
            )),
            array('g.1_1insA', array(
                'position_start' => 1,
                'position_end' => 1,
                'type' => 'ins',
                'warnings' => array(),
                'errors' => array(
                    'EPOSITIONFORMAT' => 'This variant description contains two positions that are the same. Please verify your description and try again.',
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
                'warnings' => array(),
                'errors' => array(
                    'EPOSITIONFORMAT' =>
                        'The two positions do not indicate a range longer than two bases. Please remove the parentheses if the positions are certain.',
                ),
                'messages' => array(
                    'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
                ),
            )),
            array('c.123+10_123+11insA', array(
                'position_start' => 123,
                'position_end' => 123,
                'position_start_intron' => 10,
                'position_end_intron' => 11,
                'type' => 'ins',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('c.(123+10_123+11)insA', array(
                'position_start' => 123,
                'position_end' => 123,
                'position_start_intron' => 10,
                'position_end_intron' => 11,
                'type' => 'ins',
                'warnings' => array(),
                'errors' => array(
                    'EPOSITIONFORMAT' =>
                        'The two positions do not indicate a range longer than two bases. Please remove the parentheses if the positions are certain.',
                ),
                'messages' => array(
                    'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
                ),
            )),
            array('g.(1_10)insA', array(
                'position_start' => 1,
                'position_end' => 10,
                'type' => 'ins',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
                ),
            )),
            array('c.123+10_123+20insA', array(
                'position_start' => 123,
                'position_end' => 123,
                'position_start_intron' => 10,
                'position_end_intron' => 20,
                'type' => 'ins',
                'warnings' => array(),
                'errors' => array(
                    'EPOSITIONFORMAT' =>
                        'An insertion must have taken place between two neighboring positions. If the exact ' .
                        'location is unknown, please indicate this by placing parentheses around the positions.',
                ),
            )),
            array('c.(123+10_123+20)insA', array(
                'position_start' => 123,
                'position_end' => 123,
                'position_start_intron' => 10,
                'position_end_intron' => 20,
                'type' => 'ins',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
                ),
            )),
            array('g.(1_10)_20insA', array(
                'position_start' => 10,
                'position_end' => 20,
                'type' => 'ins',
                'warnings' => array(),
                'errors' => array(
                    'EPOSITIONFORMAT' => 'Insertions should not be given more than two positions.',
                ),
            )),
            array('g.1_10insA', array(
                'position_start' => 1,
                'position_end' => 10,
                'type' => 'ins',
                'warnings' => array(),
                'errors' => array(
                    'EPOSITIONFORMAT' =>
                        'An insertion must have taken place between two neighboring positions. If the exact ' .
                        'location is unknown, please indicate this by placing parentheses around the positions.',
                ),
            )),
            array('c.123+1_124-1insA', array(
                'position_start' => 123,
                'position_end' => 124,
                'position_start_intron' => 1,
                'position_end_intron' => -1,
                'type' => 'ins',
                'warnings' => array(),
                'errors' => array(
                    'EPOSITIONFORMAT' =>
                        'An insertion must have taken place between two neighboring positions. If the exact ' .
                        'location is unknown, please indicate this by placing parentheses around the positions.',
                ),
            )),
            array('c.(123+1_124-1)insA', array(
                'position_start' => 123,
                'position_end' => 124,
                'position_start_intron' => 1,
                'position_end_intron' => -1,
                'type' => 'ins',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
                ),
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
            array('g.1_5delins10_20', array(
                'position_start' => 1,
                'position_end' => 5,
                'type' => 'delins',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('g.1_5delins20_10', array(
                'position_start' => 1,
                'position_end' => 5,
                'type' => 'delins',
                'warnings' => array(
                    'WSUFFIXFORMAT' => 'The part after "delins" does not follow HGVS guidelines.',
                ),
                'errors' => array(),
            )),
            array('g.100_200delins[NC_000001.10:g.100_200]', array(
                'position_start' => 100,
                'position_end' => 200,
                'type' => 'delins',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('c.100_200delins[NG_000123.1:g.100_200]', array(
                'position_start' => 100,
                'position_end' => 200,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => 'delins',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('c.100_200delins[LRG_123:g.100_200inv]', array(
                'position_start' => 100,
                'position_end' => 200,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => 'delins',
                'warnings' => array(),
                'errors' => array(),
            )),

            // Repeat sequences.
            array('g.1ACT[20]', array(
                'position_start' => 1,
                'position_end' => 1,
                'type' => 'repeat',
                'warnings' => array(
                    'WNOTSUPPORTED' => 'Although this variant is a valid HGVS description, this syntax is currently not supported for mapping and validation.',
                ),
                'errors' => array(),
            )),
            array('c.1ACT[20]', array(
                'position_start' => 1,
                'position_end' => 1,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => 'repeat',
                'warnings' => array(
                    'WNOTSUPPORTED' => 'Although this variant is a valid HGVS description, this syntax is currently not supported for mapping and validation.',
                ),
                'errors' => array(),
            )),
            array('c.1AC[20]', array(
                'position_start' => 1,
                'position_end' => 1,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => 'repeat',
                'warnings' => array(
                    'WNOTSUPPORTED' => 'Although this variant is a valid HGVS description, this syntax is currently not supported for mapping and validation.',
                    'WINVALIDREPEATLENGTH' => 'A repeat sequence of coding DNA should always have a length of (a multiple of) 3.',
                ),
                'errors' => array(),
            )),
            array('g.1AC[20]', array(
                'position_start' => 1,
                'position_end' => 1,
                'type' => 'repeat',
                'warnings' => array(
                    'WNOTSUPPORTED' => 'Although this variant is a valid HGVS description, this syntax is currently not supported for mapping and validation.',
                ),
                'errors' => array(),
            )),
            array('g.1AC[20]GT[10]', array(
                'position_start' => 1,
                'position_end' => 1,
                'type' => 'repeat',
                'warnings' => array(
                    'WNOTSUPPORTED' => 'Although this variant is a valid HGVS description, this syntax is currently not supported for mapping and validation.',
                ),
                'errors' => array(),
            )),

            // Mosaicism and chimerism.
            array('g.123=/A>G', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => 'mosaic',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('g.123=//A>G', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => 'chimeric',
                'warnings' => array(),
                'errors' => array(),
            )),

            // Wild type sequence (no changes).
            array('g.=', array(
                'position_start' => 0,
                'position_end' => 0,
                'type' => '=',
                'warnings' => array(),
                'errors' => array(
                    'EMISSINGPOSITIONS' => 'When using "=", please provide the position(s) that are unchanged.',
                ),
            )),
            array('g.123=', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => '=',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('g.123A=', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => '=',
                'warnings' => array(
                    'WBASESGIVEN' => 'When using "=", please remove the original sequence before the "=".',
                ),
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
            array('c.(123A>T)', array(
                'position_start' => 123,
                'position_end' => 123,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => 'subst',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('g.(1_2insN[(50_60)])', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'ins',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('g.((1_5)insN[(50_60)])', array(
                'position_start' => 1,
                'position_end' => 5,
                'type' => 'ins',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
                ),
            )),
            array('c.(123+1_124-1)insN[(50_60)]', array(
                'position_start' => 123,
                'position_end' => 124,
                'position_start_intron' => 1,
                'position_end_intron' => -1,
                'type' => 'ins',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
                ),
            )),
            array('g.((1_2insA)', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'ins',
                'warnings' => array(
                    'WUNBALANCEDPARENTHESES' => 'The variant description contains unbalanced parentheses.'
                ),
                'errors' => array(),
            )),

            // Positions with question marks.
            array('g.?del', array(
                'position_start' => 1,
                'position_end' => 4294967295,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.'
                ),
            )),
            array('g.1_?del', array(
                'position_start' => 1,
                'position_end' => 4294967295,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.'
                ),
            )),
            array('g.?_100del', array(
                'position_start' => 1,
                'position_end' => 100,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.'
                ),
            )),
            array('g.?_?del', array(
                'position_start' => 1,
                'position_end' => 4294967295,
                'type' => 'del',
                'warnings' => array(
                    'WTOOMUCHUNKNOWN' => 'This variant description contains redundant question marks. Please rewrite the positions ?_? to ?.',
                ),
                'errors' => array(),
                'messages' => array(
                    'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.',
                ),
            )),
            array('g.(?_?)del', array(
                'position_start' => 1,
                'position_end' => 4294967295,
                'type' => 'del',
                'warnings' => array(
                    'WTOOMUCHUNKNOWN' => 'This variant description contains redundant question marks. Please rewrite the positions (?_?) to ?.',
                ),
                'errors' => array(
                    'ESUFFIXMISSING' => 'The length must be provided for variants which took place within an uncertain range.',
                ),
                'messages' => array(
                    'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.',
                    'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
                ),
            )),
            array('g.(?_5)_10del', array(
                'position_start' => 5,
                'position_end' => 10,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.'
                ),
            )),
            array('g.(5_?)_10del', array(
                'position_start' => 5,
                'position_end' => 10,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.'
                ),
            )),
            array('g.(?_5)_?del', array(
                'position_start' => 5,
                'position_end' => 4294967295,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.'
                ),
            )),
            array('g.(5_?)_?del', array(
                'position_start' => 5,
                'position_end' => 4294967295,
                'type' => 'del',
                'warnings' => array(
                    'WTOOMUCHUNKNOWN' => 'This variant description contains redundant question marks. Please rewrite the positions (5_?)_? to (5_?).',
                ),
                'errors' => array(),
                'messages' => array(
                    'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.'
                ),
            )),
            array('g.(?_?)_10del', array(
                'position_start' => 1,
                'position_end' => 10,
                'type' => 'del',
                'warnings' => array(
                    'WTOOMUCHUNKNOWN' => 'This variant description contains redundant question marks. Please rewrite the positions (?_?)_10 to ?_10.',
                ),
                'errors' => array(),
                'messages' => array(
                    'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.',
                ),
            )),
            array('g.(?_?)_(10_?)del', array(
                'position_start' => 1,
                'position_end' => 10,
                'type' => 'del',
                'warnings' => array(
                    'WTOOMUCHUNKNOWN' => 'This variant description contains redundant question marks. Please rewrite the positions (?_?)_(10_?) to ?_(10_?).',
                ),
                'errors' => array(),
                'messages' => array(
                    'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.',
                ),
            )),
            array('g.(?_?)_(?_10)del', array(
                'position_start' => 1,
                'position_end' => 10,
                'type' => 'del',
                'warnings' => array(
                    'WTOOMUCHUNKNOWN' => 'This variant description contains redundant question marks. Please rewrite the positions (?_?)_(?_10) to (?_10).',
                ),
                'errors' => array(),
                'messages' => array(
                    'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.',
                ),
            )),
            array('g.5_(10_?)del', array(
                'position_start' => 5,
                'position_end' => 10,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.'
                ),
            )),
            array('g.5_(?_10)del', array(
                'position_start' => 5,
                'position_end' => 10,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.'
                ),
            )),
            array('g.?_(10_?)del', array(
                'position_start' => 1,
                'position_end' => 10,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.'
                ),
            )),
            array('g.?_(?_10)del', array(
                'position_start' => 1,
                'position_end' => 10,
                'type' => 'del',
                'warnings' => array(
                    'WTOOMUCHUNKNOWN' => 'This variant description contains redundant question marks. Please rewrite the positions ?_(?_10) to (?_10).',
                ),
                'errors' => array(),
                'messages' => array(
                    'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.'
                ),
            )),
            array('g.5_(?_?)del', array(
                'position_start' => 5,
                'position_end' => 4294967295,
                'type' => 'del',
                'warnings' => array(
                    'WTOOMUCHUNKNOWN' => 'This variant description contains redundant question marks. Please rewrite the positions 5_(?_?) to 5_?.',
                ),
                'errors' => array(),
                'messages' => array(
                    'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.',
                ),
            )),
            array('g.(5_?)_(?_?)del', array(
                'position_start' => 5,
                'position_end' => 4294967295,
                'type' => 'del',
                'warnings' => array(
                    'WTOOMUCHUNKNOWN' => 'This variant description contains redundant question marks. Please rewrite the positions (5_?)_(?_?) to (5_?).',
                ),
                'errors' => array(),
                'messages' => array(
                    'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.',
                ),
            )),
            array('g.(?_5)_(?_?)del', array(
                'position_start' => 5,
                'position_end' => 4294967295,
                'type' => 'del',
                'warnings' => array(
                    'WTOOMUCHUNKNOWN' => 'This variant description contains redundant question marks. Please rewrite the positions (?_5)_(?_?) to (?_5)_?.',
                ),
                'errors' => array(),
                'messages' => array(
                    'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.',
                ),
            )),
            array('g.(?_5)_(10_?)del', array(
                'position_start' => 5,
                'position_end' => 10,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.'
                ),
            )),
            array('g.(5_?)_(?_10)del', array(
                'position_start' => 5,
                'position_end' => 10,
                'type' => 'del',
                'warnings' => array(
                    'WTOOMUCHUNKNOWN' => 'This variant description contains redundant question marks. Please rewrite the positions (5_?)_(?_10) to (5_10).',
                ),
                'errors' => array(),
                'messages' => array(
                    'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.'
                ),
            )),
            array('g.(?_?)_(?_?)del', array(
                'position_start' => 1,
                'position_end' => 4294967295,
                'type' => 'del',
                'warnings' => array(
                    'WTOOMUCHUNKNOWN' => 'This variant description contains redundant question marks. Please rewrite the positions (?_?)_(?_?) to ?.',
                ),
                'errors' => array(),
                'messages' => array(
                    'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.'
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
            array('g.(100_200)_(200_500)del', array(
                'position_start' => 200,
                'position_end' => 200,
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
                    'WPOSITIONFORMAT' => 'This variant description contains two positions that are the same. Please verify your description and try again.'
                ),
                'errors' => array(),
            )),
            array('g.2_1del', array (
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'del',
                'warnings' => array(
                    'WPOSITIONFORMAT' => 'The positions are not given in the correct order. Please verify your description and try again.'
                ),
                'errors' => array(),
            )),
            array('c.*2_1del', array (
                'position_start' => 1,
                'position_end' => 1000002,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => 'del',
                'warnings' => array(
                    'WPOSITIONFORMAT' => 'The positions are not given in the correct order. Please verify your description and try again.'
                ),
                'errors' => array(),
            )),
            array('c.(*50_500)_(100_1)del', array (
                'position_start' => 100,
                'position_end' => 1000050,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => 'del',
                'warnings' => array(
                    'WPOSITIONFORMAT' => 'The positions are not given in the correct order. Please verify your description and try again.'
                ),
                'errors' => array(),
            )),
            array('c.(500_*50)_(1_100)del', array (
                'position_start' => 100,
                'position_end' => 1000050,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => 'del',
                'warnings' => array(
                    'WPOSITIONFORMAT' => 'The positions are not given in the correct order. Please verify your description and try again.'
                ),
                'errors' => array(),
            )),
            array('c.123-5_123-10del', array (
                'position_start' => 123,
                'position_end' => 123,
                'position_start_intron' => -10,
                'position_end_intron' => -5,
                'type' => 'del',
                'warnings' => array(
                    'WPOSITIONFORMAT' => 'The intronic positions are not given in the correct order. Please verify your description and try again.'
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
                    'WPOSITIONSLIMIT' => 'Positions are beyond the possible limits of their type: start, end.',
                ),
                'errors' => array(),
            )),
            array('c.10000000+10000000000_10000001-10000000000del', array(
                'position_start' => 8388607,
                'position_end' => 8388607,
                'position_start_intron' => 2147483647,
                'position_end_intron' => -2147483648,
                'type' => 'del',
                'warnings' => array(
                    'WPOSITIONSLIMIT' => 'Positions are beyond the possible limits of their type: start, start in intron, end, end in intron.',
                ),
                'errors' => array(),
            )),

            // Challenging insertions.
            array('g.1_2ins(5_10)', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'ins',
                'warnings' => array(
                    'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines.',
                ),
                'errors' => array(),
            )),
            array('g.1_2ins[A]', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'ins',
                'warnings' => array(
                    'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines.',
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
                    'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines.',
                ),
                'errors' => array(),
            )),
            array('g.1_2ins340', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'ins',
                'warnings' => array(
                    'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines.',
                ),
                'errors' => array(),
            )),
            array('g.1_2ins[123', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'ins',
                'warnings' => array(
                    'WSUFFIXFORMAT' => 'The part after "ins" contains unbalanced square brackets.',
                ),
                'errors' => array(),
            )),
            array('g.1_2ins[A[20];TGAAG[35];N[10]]', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'ins',
                'warnings' => array(),
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
                    'WSUFFIXFORMAT' => 'The length of the variant is not formatted following the HGVS guidelines. When indicating an uncertain position like this, the length of the variant must be provided between parentheses.',
                ),
                'errors' => array(),
                'messages' => array(
                    'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
                ),
            )),
            array('g.(1_100)del50', array(
                'position_start' => 1,
                'position_end' => 100,
                'type' => 'del',
                'warnings' => array(
                    'WSUFFIXFORMAT' => 'The length of the variant is not formatted following the HGVS guidelines. When indicating an uncertain position like this, the length of the variant must be provided between parentheses.',
                ),
                'errors' => array(),
                'messages' => array(
                    'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
                ),
            )),
            array('g.(1_100)del', array(
                'position_start' => 1,
                'position_end' => 100,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(
                    'ESUFFIXMISSING' => 'The length must be provided for variants which took place within an uncertain range.',
                ),
                'messages' => array(
                    'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
                ),
            )),
            array('g.(1_100)del(30)', array(
                'position_start' => 1,
                'position_end' => 100,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
                'messages' => array(
                    'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
                ),
            )),
            array('g.1inv(30)', array(
                'position_start' => 1,
                'position_end' => 1,
                'type' => 'inv',
                'warnings' => array(
                    'WSUFFIXGIVEN' => 'Nothing should follow "inv".',
                ),
                'errors' => array(
                    'EPOSITIONFORMAT' => 'Inversions require a length of at least two bases.',
                ),
            )),
            array('g.1_100inv(30)', array(
                'position_start' => 1,
                'position_end' => 100,
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
                    'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
                ),
            )),
            array('g.(1_2)inv(30)', array(
                'position_start' => 1,
                'position_end' => 2,
                'type' => 'inv',
                'warnings' => array(),
                'errors' => array(
                    'EPOSITIONFORMAT' =>
                        'The two positions do not indicate a range longer than two bases. Please remove the parentheses if the positions are certain.',
                ),
                'messages' => array(
                    'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
                ),
            )),
            array('g.1ACT[20]A', array(
                'position_start' => 1,
                'position_end' => 1,
                'type' => 'repeat',
                'warnings' => array(
                    'WNOTSUPPORTED' => 'Although this variant is a valid HGVS description, this syntax is currently not supported for mapping and validation.',
                    'WSUFFIXGIVEN' => 'Nothing should follow "ACT[20]".',
                ),
                'errors' => array(),
            )),
            array('g.(1_100)ACT[20]A', array(
                'position_start' => 1,
                'position_end' => 100,
                'type' => 'repeat',
                'warnings' => array(
                    'WNOTSUPPORTED' => 'Although this variant is a valid HGVS description, this syntax is currently not supported for mapping and validation.',
                    'WSUFFIXGIVEN' => 'Nothing should follow "ACT[20]".',
                ),
                'errors' => array(),
                'messages' => array(
                    'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
                ),
            )),

            // Methylation-related changes.
            array('g.123|met=', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => 'met',
                'warnings' => array(
                    'WNOTSUPPORTED' => 'Although this variant is a valid HGVS description, this syntax is currently not supported for mapping and validation.',
                ),
                'errors' => array(),
            )),
            array('g.123|gom', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => 'met',
                'warnings' => array(
                    'WNOTSUPPORTED' => 'Although this variant is a valid HGVS description, this syntax is currently not supported for mapping and validation.',
                ),
                'errors' => array(),
            )),
            array('g.123|lom', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => 'met',
                'warnings' => array(
                    'WNOTSUPPORTED' => 'Although this variant is a valid HGVS description, this syntax is currently not supported for mapping and validation.',
                ),
                'errors' => array(),
            )),
            array('g.123lom', array(
                'position_start' => 0,
                'position_end' => 0,
                'type' => '',
                'warnings' => array(),
                'errors' => array(
                    'EPIPEMISSING' => 'Please place a "|" between the positions and the variant type (lom).',
                ),
            )),
            array('g.123||bsrC', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => 'met',
                'warnings' => array(),
                'errors' => array(
                    'ENOTSUPPORTED' => 'This not a valid HGVS description, please verify your input after "|".',
                ),
            )),

            // Descriptions that are currently unsupported.
            array('g.123^124A>C', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => '^',
                'warnings' => array(),
                'errors' => array(
                    'ENOTSUPPORTED' =>
                        'Currently, variant descriptions using "^" are not yet supported. This does not necessarily mean the description is not valid HGVS.',
                ),
            )),
            array('g.123A>C^124G>C', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => 'subst',
                'warnings' => array(
                    'WSUFFIXGIVEN' =>
                        'Nothing should follow "A>C".'
                ),
                'errors' => array(),
            )),
            array('g.123A>C;124A>C', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => 'subst',
                'warnings' => array(
                    'WSUFFIXGIVEN' =>
                        'Nothing should follow "A>C".'
                ),
                'errors' => array(),
            )),
            array('g.[123A>C;124A>C]', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => ';',
                'warnings' => array(),
                'errors' => array(
                    'ENOTSUPPORTED' => 'Currently, variant descriptions of combined variants are not yet supported. This does not necessarily mean the description is not valid HGVS. Please submit your variants separately.',
                ),
            )),
            array('g.[123A>C(;)124A>C]', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => ';',
                'warnings' => array(),
                'errors' => array(
                    'ENOTSUPPORTED' => 'Currently, variant descriptions of combined variants are not yet supported. This does not necessarily mean the description is not valid HGVS. Please submit your variants separately.',
                ),
            )),
            array('g.[123A>C];[124A>C]', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => ';',
                'warnings' => array(),
                'errors' => array(
                    'ENOTSUPPORTED' => 'Currently, variant descriptions of combined variants are not yet supported. This does not necessarily mean the description is not valid HGVS. Please submit your variants separately.',
                ),
            )),
            array('g.1_qterdel', array(
                'position_start' => 0,
                'position_end' => 0,
                'type' => '',
                'warnings' => array(),
                'errors' => array(
                    'ENOTSUPPORTED' => 'Currently, variant descriptions using "qter" are not yet supported. This does not necessarily mean the description is not valid HGVS.',
                ),
            )),
            array('g.1_cendel', array(
                'position_start' => 0,
                'position_end' => 0,
                'type' => '',
                'warnings' => array(),
                'errors' => array(
                    'ENOTSUPPORTED' => 'Currently, variant descriptions using "cen" are not yet supported. This does not necessarily mean the description is not valid HGVS.',
                ),
            )),
            array('g.pter_1000000del', array(
                'position_start' => 0,
                'position_end' => 0,
                'type' => '',
                'warnings' => array(),
                'errors' => array(
                    'ENOTSUPPORTED' => 'Currently, variant descriptions using "pter" are not yet supported. This does not necessarily mean the description is not valid HGVS.',
                ),
            )),
            array('n.5-2::10-3', array(
                'position_start' => 0,
                'position_end' => 0,
                'type' => '',
                'warnings' => array(),
                'errors' => array(
                    'ENOTSUPPORTED' => 'Currently, variant descriptions using "::" are not yet supported. This does not necessarily mean the description is not valid HGVS.',
                ),
            )),
            array('g.123|bsrC', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => 'met',
                'warnings' => array(),
                'errors' => array(
                    'ENOTSUPPORTED' => 'This not a valid HGVS description, please verify your input after "|".',
                ),
            )),

            // Descriptions holding reference sequences.
            array('NM_123456.1:c.1del', array(
                'position_start' => 1,
                'position_end' => 1,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('NM_123456.1:c.1-1del', array(
                'position_start' => 1,
                'position_end' => 1,
                'position_start_intron' => -1,
                'position_end_intron' => -1,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(
                    'EWRONGREFERENCE' =>
                        'The variant is missing a genomic reference sequence required to verify the intronic positions.',
                ),
            )),
            array('NC_123456.1(NM_123456.1):g.1del', array(
                'position_start' => 1,
                'position_end' => 1,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(
                    'EWRONGREFERENCE' => 'The given reference sequence (NC_123456.1(NM_123456.1)) does not match the DNA type (g). For g. variants, please use a genomic reference sequence.',
                ),
            )),
            array('NC_123456.1(NM_123456.1):c.1-1del', array(
                'position_start' => 1,
                'position_end' => 1,
                'position_start_intron' => -1,
                'position_end_intron' => -1,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('ENST12345678911.1:c.1del', array(
                'position_start' => 1,
                'position_end' => 1,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('LRG_123:g.1del', array(
                'position_start' => 1,
                'position_end' => 1,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('LRG_123t1:g.1del', array(
                'position_start' => 1,
                'position_end' => 1,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(
                    'EWRONGREFERENCE' => 'The given reference sequence (LRG_123t1) does not match the DNA type (g). For g. variants, please use a genomic reference sequence.',
                ),
            )),
            array('LRG_123t1:c.1del', array(
                'position_start' => 1,
                'position_end' => 1,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('LRG_123t1:n.1del', array(
                'position_start' => 1,
                'position_end' => 1,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('LRG_123:c.1del', array(
                'position_start' => 1,
                'position_end' => 1,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(
                    'EWRONGREFERENCE' => 'The given reference sequence (LRG_123) does not match the DNA type (c). For c. variants, please use a coding transcript reference sequence.',
                ),
            )),
            array('NR_123456.1:n.1del', array(
                'position_start' => 1,
                'position_end' => 1,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('NM_123456.1:n.1del', array(
                'position_start' => 1,
                'position_end' => 1,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(
                    'EWRONGREFERENCE' => 'The given reference sequence (NM_123456.1) does not match the DNA type (n). For n. variants, please use a non-coding transcript reference sequence.',
                ),
            )),

            array('NM_123456.1:g.1del', array(
                'position_start' => 1,
                'position_end' => 1,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(
                    'EWRONGREFERENCE' => 'The given reference sequence (NM_123456.1) does not match the DNA type (g). For g. variants, please use a genomic reference sequence.',
                ),
            )),
            array('NC_123456.1:g.1del', array(
                'position_start' => 1,
                'position_end' => 1,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
            )),
            array('ENSG12345678911.1:g.1del', array(
                'position_start' => 1,
                'position_end' => 1,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(),
            )),

            array('NC_12345.1:g.1del', array(
                'position_start' => 1,
                'position_end' => 1,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(
                    'EREFERENCEFORMAT' => 'The reference sequence could not be recognised. Supported reference sequence IDs are from NCBI Refseq, Ensembl, and LRG.',
                ),
            )),
            array('NC_123456:g.1del', array(
                'position_start' => 1,
                'position_end' => 1,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(
                    'EREFERENCEFORMAT' => 'The reference sequence used is missing the required version number. NCBI RefSeq and Ensembl IDs require version numbers when used in variant descriptions.',
                ),
            )),
            array('LRG:g.1del', array(
                'position_start' => 1,
                'position_end' => 1,
                'type' => 'del',
                'warnings' => array(),
                'errors' => array(
                    'EREFERENCEFORMAT' => 'The reference sequence could not be recognised. Supported reference sequence IDs are from NCBI Refseq, Ensembl, and LRG.',
                ),
            )),

            // Other errors or problems.
            array('G.123dup', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => 'dup',
                'warnings' => array(
                    'WWRONGCASE' => 'This not a valid HGVS description, due to characters being in the wrong case. Please check the use of upper- and lowercase characters.',
                ),
                'errors' => array(),
            )),
            array('g. 123_124insA', array(
                'position_start' => 123,
                'position_end' => 124,
                'type' => 'ins',
                'warnings' => array(
                    'WWHITESPACE' => 'This variant description contains one or more whitespace characters (spaces, tabs, etc). Please remove these.',
                ),
                'errors' => array(),
            )),
            array(' g.123del', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => 'del',
                'warnings' => array(
                    'WWHITESPACE' => 'This variant description contains one or more whitespace characters (spaces, tabs, etc). Please remove these.',
                ),
                'errors' => array(),
            )),
        );
    }
}
?>
