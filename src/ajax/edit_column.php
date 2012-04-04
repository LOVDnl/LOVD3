<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-03-01
 * Modified    : 2012-04-02
 * For LOVD    : 3.0-beta-04
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Jerry Hoogenboom <J.Hoogenboom@LUMC.nl>
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

define('ROOT_PATH', '../');
require_once ROOT_PATH . 'inc-init.php';

if ($_AUTH['level'] < LEVEL_MANAGER) {
    exit(AJAX_NO_AUTH);
} elseif (ACTION == 'set_standard' && !empty($_POST['colid']) && $_DB->query('UPDATE ' . TABLE_COLS . ' SET standard = 1, edited_by = ?, edited_date = NOW() WHERE id = ?', array($_AUTH['id'], $_POST['colid']))->rowCount()) {
    exit(AJAX_TRUE);
} else {
    exit(AJAX_FALSE);
}
?>
