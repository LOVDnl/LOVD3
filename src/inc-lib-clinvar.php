<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-10-04
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


class ClinvarFile {

    var $nLinesToSkip = 15; // Number of lines at top of file to ignore.
    var $aHeader = null;    // Column names.
    var $sLinePart = null;  // Temp storage of incomplete records split over file chunks.
    var $nChunkCount = 0;        // File chunk counter.
    var $aBuffer = array(); // Record buffer.
    var $nBufferCursor = 0; // Index of record in buffer currently processing.
    var $bProgressBar;      // Flag denoting if progress bar is shown.
    var $oProgressBar;      // Progress bar object.
    var $oFileHandle;       // Link to Clinvar (gzipped) file object.
    var $nChunksTotal;      // Total number of chunks expected in file.





    function __construct($sLocation, $bProgressBar=false)
    {
        // Setup progress bar.
        $this->bProgressBar = $bProgressBar;
        if ($bProgressBar) {
            // Create a progress bar instance with a pseudo random string as its identifier.
            $sPBID = join('', array_map('chr', array_rand(array_flip(range(65, 90)), 10)));
            $this->oProgressBar = new ProgressBar($sPBID, 'Reading from ' . $sLocation, 'Done.');
        }

        // Open gzipped Clinvar file.
        $this->oFileHandle = gzopen($sLocation, 'r');
        $this->nChunksTotal = intval(CLINVAR_FILE_SIZE / CLINVAR_CHUNK_SIZE);
    }





    private function fillBuffer()
    {
        // Retrieve a chunk of data from source file and fill buffer
        // ($this->aBuffer) with lines. Returns true on success, false on EOF
        // or any other case.

        if (!is_resource($this->oFileHandle)) {
            // Return false if file handle invalid or previously closed.
            return false;
        }

        // Read chunk from gzipped file.
        $sChunk = gzread($this->oFileHandle, CLINVAR_CHUNK_SIZE);
        if ($this->bProgressBar) {
            // Update estimation of progress.
            // Note: it's difficult to get exact progress measures as gztell and ftell
            //       give positions in the compressed file and the size of the
            //       uncompressed file is encoded at the very end of the compressed file.
            $this->oProgressBar->setProgress((++$this->nChunkCount / $this->nChunksTotal) * 100);
            $this->oProgressBar->setMessage('Processing chunk ' . strval($this->nChunkCount) .
                ' of ' . strval($this->nChunksTotal) . ' (estimated)...');
        }

        // Note: gzread() returns an empty string at EOF (instead of false).
        if ($sChunk === '' || $this->nChunkCount++ > 2000) {
            fclose($this->oFileHandle);
            return false;
        }

        $this->aBuffer = explode("\n", $sChunk);
        $this->nBufferCursor = 0;
        return true;
    }






    function fetchRecord()
    {
        // Return next record from Clinvar file. Returns an array with field
        // names as keys and field values as values. Returns false at EOF.

        if ($this->nBufferCursor >= count($this->aBuffer)) {
            // At end of buffer, try to fill buffer with next chunk from file.
            if (!$this->fillBuffer()) {
                // At end of file, return any remaining record or false.
                if (!is_null($this->sLinePart) && !empty($this->sLinePart)) {
                    $sTempLinePart = $this->sLinePart;
                    $this->sLinePart = null;
                    return array_combine($this->aHeader, explode("\t", $sTempLinePart));
                } else {
                    return false;
                }
            }
        }

        while ($this->nBufferCursor < (count($this->aBuffer)-1)) {
            $i = $this->nBufferCursor++;
            if ($this->nLinesToSkip > 0) {
                // Ignore initial lines.
                $this->nLinesToSkip--;
                continue;
            }
            if (is_null($this->aHeader)) {
                // First line is header.
                $this->aHeader = explode("\t", $this->aBuffer[$i]);
                continue;
            } elseif (!is_null($this->sLinePart)) {
                // Prepend leftover from last chunk to first line.
                $this->aBuffer[$i] = $this->sLinePart . $this->aBuffer[$i];
                $this->sLinePart = null;
            }
            return array_combine($this->aHeader, explode("\t", $this->aBuffer[$i]));
        }

        // Save last (incomplete) line of chunk for next chunk.
        $this->sLinePart = $this->aBuffer[$this->nBufferCursor++];

        // Return line from next chunk
        return $this->fetchRecord();
    }
}
