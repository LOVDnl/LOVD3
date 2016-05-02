<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-04-29
 * Modified    : 2016-04-29
 * For LOVD    : 3.0-15
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


require_once ROOT_PATH . 'class/objects.php';



class LOVD_Basic extends LOVD_Object
{
    // "Basic" LOVD object to be used for entities within the LOVD system that
    // do not have user interface related metadata, but which provides access
    // to useful methods such as checkFields().


    function __construct($sTable)
    {
        // Construct an object from a given database table name.
        $this->sTable = $sTable;
        parent::__construct();
    }
}

