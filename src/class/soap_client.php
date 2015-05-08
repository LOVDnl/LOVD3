<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2014-07-25
 * Modified    : 2014-08-06
 * For LOVD    : 3.0-11
 *
 * Copyright   : 2004-2014 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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





class LOVD_SoapClient extends SoapClient {
    // This class provides a wrapper around SoapClient such that the proxy settings
    // are respected and SoapClient options are handled in just one place.

    function __construct ()
    {
        // Initiate Soap Client.
        global $_CONF;

        $sHostname = parse_url($_CONF['mutalyzer_soap_url'], PHP_URL_HOST);
        // Mutalyzer's Apache server doesn't like SSL requests coming in through a proxy, if these settings are not configured.
        // The new Mutalyzer server (scheduled to be released in September, 2014) does not have this issue, but still works with these settings enabled.
        $oContext = stream_context_create(array('ssl' => array('allow_self_signed' => 1, 'SNI_enabled' => 1, 'SNI_server_name' => $sHostname)));
        $aOptions =
            array(
                'features' => SOAP_SINGLE_ELEMENT_ARRAYS, // Makes sure we ALWAYS get an array back, even if there is just one element returned (saves us a lot is_array() checks).
                'stream_context' => $oContext,
            );
        if ($_CONF['proxy_host']) {
            $aOptions = array_merge($aOptions, array(
                'proxy_host'     => $_CONF['proxy_host'],
                'proxy_port'     => $_CONF['proxy_port'],
                'proxy_login'    => $_CONF['proxy_username'],
                'proxy_password' => $_CONF['proxy_password'],
            ));
        }

        return parent::__construct($_CONF['mutalyzer_soap_url'] . '?wsdl', $aOptions);
    }
}
?>
