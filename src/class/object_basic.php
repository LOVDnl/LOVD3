<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-04-29
 * Modified    : 2016-04-29
 * For LOVD    : 3.0-15
 *
 * Copyright   : 2016-2019 Leiden University Medical Center; http://www.LUMC.nl/
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
    // This class extends the basic Object class and it gives quick access to the Object class' methods,
    // such as insertEntry(). In practice, this method is only used in import.php for the linking objects
    // (Genes_To_Diseases, Individuals_To_Diseases, Screenings_To_Genes, Screenings_To_Variants)
    // to get them inserted into the database.





    function __construct($sTable)
    {
        // Construct an object from a given database table name.
        $this->sTable = $sTable;
        parent::__construct();
    }
}

