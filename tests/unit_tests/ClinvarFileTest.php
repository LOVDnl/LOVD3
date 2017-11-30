<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2017-08-18
 * Modified    : 2017-11-30
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


require_once 'src/inc-lib-clinvar.php';

// Location of Clinvar test file (100 lines).
define('CLINVAR_TEST_FILE',  'file://' .
    realpath(dirname(__FILE__) . '/../test_data_files/hgvs4variation.100.txt.gz'));

// Size in bytes of chunks to be read from Clinvar file.
define('CLINVAR_CHUNK_SIZE', 8192);

// Estimation of size of decompressed Clinvar file (current value measured at 2017-11-28).
define('CLINVAR_FILE_SIZE', 10421);


class ClinvarFileTest extends PHPUnit_Framework_TestCase
{

    public function testClinvarFile()
    {
        // Test for reader of gzipped Clinvar HGVS file.

        $oFile = new ClinvarFile(CLINVAR_TEST_FILE, false);

        $nCounter = 0;
        while (($aData = $oFile->fetchRecord()) !== false) {
            $nCounter++;

            if ($nCounter == 1) {
                // Test if header line was parsed correctly.
                $this->assertEquals(
                    array(
                        '#Symbol',
                        'GeneID',
                        'VariationID',
                        'AlleleID',
                        'Type',
                        'Assembly',
                        'NucleotideExpression',
                        'NucleotideChange',
                        'ProteinExpression',
                        'ProteinChange',
                        'UsedForNaming',
                        'Submitted',
                        'OnRefSeqGene'
                    ),
                    array_keys($aData)
                );
            }

            if ($nCounter == 34) {
                // Test if fields are correctly mapped to headers.
                $this->assertEquals('16788', $aData['AlleleID']);
                $this->assertEquals('NC_000009.12:g.133635393T=', $aData['NucleotideExpression']);
            }
        }

        // Test total number of records in file.
        $this->assertEquals(84, $nCounter);
    }
}
