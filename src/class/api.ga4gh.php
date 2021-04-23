<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2021-04-22
 * Modified    : 2021-04-23
 * For LOVD    : 3.0-27
 *
 * Copyright   : 2004-2021 Leiden University Medical Center; http://www.LUMC.nl/
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

// Don't allow direct access.
if (!defined('ROOT_PATH')) {
    exit;
}



class LOVD_API_GA4GH
{
    // This class defines the LOVD API object handling GA4GH Data Connect.

    private $API;                     // The API object.
    private $aURLElements = array();  // The current URL broken in parts.





    function __construct (&$oAPI)
    {
        // Links the API to the private variable.

        if (!is_object($oAPI) || !is_a($oAPI, 'LOVD_API')) {
            return false;
        }

        $this->API = $oAPI;

        return true;
    }





    public function processGET ($aURLElements)
    {
        // Handle GET requests for GA4GH Data Connect.

        $this->aURLElements = array_pad($aURLElements, 3, '');

        // No further elements given, then forward to the table list.
        if (!implode('', $this->aURLElements)) {
            $this->API->nHTTPStatus = 302; // Send 302 Moved Temporarily (302 Found in HTTP 1.1).
            $this->API->aResponse['messages'][] = 'Location: ' . lovd_getInstallURL() . 'api/v' . $this->API->nVersion . '/ga4gh/tables';
            return true;
        }

        // Check URL structure.
        if (count($this->aURLElements) > 3
            || ($this->aURLElements[0] == 'tables' && $aURLElements[1])) {
            $this->API->nHTTPStatus = 400; // Send 400 Bad Request.
            $this->API->aResponse['errors'][] = 'Could not parse requested URL.';
            return false;
        } elseif (($this->aURLElements[0] == 'table' && $this->aURLElements[1] != 'variants')) {
            $this->API->nHTTPStatus = 400; // Send 400 Bad Request.
            $this->API->aResponse['errors'][] = 'Table name not recognized. Be sure to user lowercase letters only.';
            return false;
        }

        // If table is given but no choice is made between info or data, forward to data.
        if ($this->aURLElements[0] == 'table' && !$this->aURLElements[2]) {
            $this->API->nHTTPStatus = 302; // Send 302 Moved Temporarily (302 Found in HTTP 1.1).
            $this->API->aResponse['messages'][] = 'Location: ' . lovd_getInstallURL() . 'api/v' . $this->API->nVersion . '/ga4gh/table/' . $this->aURLElements[1] . '/data';
            return true;
        }

        // Check URL structure some more.
        if ($this->aURLElements[0] == 'table'
            && !in_array($this->aURLElements[2], array('info', 'data'))
            && !preg_match('/^data:hg[0-9]{2}:chr([XYM]|[0-9]{1,2})(:[0-9]+)$/', $this->aURLElements[2])) {
            $this->API->nHTTPStatus = 400; // Send 400 Bad Request.
            $this->API->aResponse['errors'][] = 'Could not parse requested URL.';
            return false;
        }

        // Now actually handle the request.
        if ($aURLElements[0] == 'tables') {
            return $this->showTables();
        } elseif ($aURLElements[0] == 'table' && $aURLElements[2] == 'info') {
            return $this->showTableInfo($aURLElements[1]);
        }

        // If we end up here, we didn't handle the request well.
        return false;
    }





    private function showTableInfo ($sTableName)
    {
        // Shows table info.
        // Let's not make this too hard, the data model is on GitHub.

        $aOutput = array(
            'name' => $sTableName,
            'data_model' => array(
                '$ref' => 'https://github.com/VarioML/VarioML/blob/master/json/schemas/v.2.0/' . $sTableName . '.json',
            ),
        );

        $this->API->aResponse = $aOutput;
        return true;
    }





    private function showTables ()
    {
        // Shows all tables in GA4GH Data Connect.

        $aOutput = array(
            'tables' => array(
                array(
                    'name' => 'variants',
                    'data_model' => array(
                        '$ref' => 'https://github.com/VarioML/VarioML/blob/master/json/schemas/v.2.0/variants.json',
                    ),
                ),
            ),
            'pagination' => array(),
        );

        $this->API->aResponse = $aOutput;
        return true;
    }
}
?>
