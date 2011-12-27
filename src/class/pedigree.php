<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-12-22
 * Modified    : 2011-12-27
 * For LOVD    : 3.0-beta-01
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
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





class Pedigree {
    // Creates a pedigree tree around the given individual, calculates withs, and prints it on the screen.
    private $individuals = array();
    private $tree = array();

    function __construct ($nID, $nGenerations = 5)
    {
        // Default constructor.
        global $_DB;

        if (is_int($nID)) {
            // We don't handle integers well, change to string.
            $nID = sprintf('%08d', $nID);
        }

        if (!ctype_digit($nID)) {
            lovd_displayError('ObjectError', 'Pedigree::__construct() called with non-valid Individual ID.');
        }

        if (!is_int($nGenerations) && !ctype_digit($nGenerations)) {
            lovd_displayError('ObjectError', 'Pedigree::__construct() called with non-valid number of generations.');
        }

        // Make sure the individual actually exists.
        if (!$_DB->query('SELECT COUNT(*) FROM ' . TABLE_INDIVIDUALS . ' WHERE id = ?', array($nID))->fetchColumn()) {
            lovd_displayError('ObjectError', 'Pedigree::__construct() called with non-existing Individual ID ' . $nID . '.');
        }

        // Build up the tree.
        // Current limitations: the system can not build a tree when starting from a patient with multiple trunks.
        // In other words, we can follow only one lineage up to a certain pair of (grand)*parents.
        // So, I should end up with exactly two IDs when I walk up the line.
        $aParentIDs = array($nID);
        do {
            // I don't think I can do a prepared statement, because the IN () range can change over time.
            $sFROM = 'AS parent FROM ' . TABLE_INDIVIDUALS . ' WHERE id IN (?' . str_repeat(', ?', count($aParentIDs)-1) . ') HAVING parent IS NOT NULL';
            $q = $_DB->query('SELECT DISTINCT `Individual/Parents/Father` ' . $sFROM . ' UNION ' .
                             'SELECT DISTINCT `Individual/Parents/Mother` ' . $sFROM, array_merge($aParentIDs, $aParentIDs));
            $aIDs = $q->fetchAllColumn();
            if ($aIDs) {
                // Only overwrite parent IDs when we have found the parents of these IDs.
                $aParentIDs = $aIDs;
            }
        } while ($aIDs); // FIXME; restrict to certain number of generations!

        // Now, if we have more than two parent IDs, something is wrong.
        // We could have one ID only if the $nID from the constructor has no
        // parents (and is therefore the start of the tree).
        if (count($aParentIDs) > 1) {
            lovd_displayError('ObjectError', 'Pedigree::__construct() failed to construct a pedigree tree: ended up with ' . count($aParentIDs) . ' founding parents for individual ID ' . $nID . '.');
        }

        // Alright, build the tree.
        $this->tree = $this->buildTree($aParentIDs);
        return true;
    }





    function buildTree ($aIDs)
    {
        // Recursively build the family tree, starting at one person or a pair of spouses.
        global $_DB;

        // FIXME; We actually always get an ID here, not an array! Any array passed is immediately regarded as spouses.
        // But we never send spouses, we actually always need to find them! It will be a lot more efficient
        // (now for every individual, we query the database a couple of times) to send children as arrays.

        if (count($aIDs) == 1) {
            // Try and find a spouse.
            $nID = $aIDs[0];
            // Unfortunately, we don't know the gender yet!
            $aSpouseIDs = array_unique($_DB->query('SELECT DISTINCT `Individual/Parents/Father` FROM ' . TABLE_INDIVIDUALS . ' WHERE `Individual/Parents/Mother` = ? UNION
                                                    SELECT DISTINCT `Individual/Parents/Mother` FROM ' . TABLE_INDIVIDUALS . ' WHERE `Individual/Parents/Father` = ?', array($nID, $nID))->fetchAllColumn());
            // Because this is a fake column, I need to str_pad the IDs.
            foreach ($aSpouseIDs as $key => $val) {
                $aSpouseIDs[$key] = sprintf('%08d', $val);
            }
            $aIDs = array_merge($aIDs, $aSpouseIDs); // Should of course only be one spouse.
        }
        
        // Get information about the individual(s) itself.
        $q = $_DB->query('SELECT i.id, i.`Individual/Name` AS name, CASE i.`Individual/Gender` WHEN "Male" THEN "m" WHEN "Female" THEN "f" END AS gender, i.`Individual/Parents/Father` AS father, i.`Individual/Parents/Mother` AS mother, GROUP_CONCAT(i2d.diseaseid SEPARATOR ";") AS _diseases FROM ' . TABLE_INDIVIDUALS . ' AS i LEFT OUTER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (i.id = i2d.individualid) WHERE i.id IN (?' . str_repeat(', ?', count($aIDs)-1) . ') GROUP BY i.id', $aIDs);
        while ($z = $q->fetchAssoc()) {
            $this->individuals[$z['id']] =
                 array(
                        'name'   => $z['name'],
                        'gender' => $z['gender'],
                        'father' => ($z['father']? sprintf('%08d', $z['father']) : NULL),
                        'mother' => ($z['mother']? sprintf('%08d', $z['mother']) : NULL),
                        'diseases' => ($z['_diseases']? explode(';', $z['_diseases']) : array()),
                      );
        }

        // Now that we do have the gender, correctly sort the IDs.
        if (count($aIDs) > 1 && $this->individuals[$aIDs[0]]['gender'] == 'f') {
            $aIDs = array_reverse($aIDs);
        }

        // Try and locate children.
        $sSQLChildren = 'SELECT id FROM ' . TABLE_INDIVIDUALS . ' WHERE';
        foreach ($aIDs as $nKey => $nID) {
            $sSQLChildren .= ($nKey? ' AND' : '') . ' `Individual/Parents/' . ($this->individuals[$nID]['gender'] == 'm'? 'Father' : 'Mother') . '` = ?';
        }
        $aChildren = $_DB->query($sSQLChildren, $aIDs)->fetchAllColumn();
        $aChildrenTree = array();
        if ($aChildren) {
            foreach ($aChildren as $nChildID) {
                $aChildrenTree = array_merge($aChildrenTree, $this->buildTree(array($nChildID)));
            }
        }

        // Return the array.
        return array(
             array(
                    'ids' => $aIDs,
                    'children' => $aChildrenTree,
                  ));
    }





    function calculateWidths (&$a)
    {
        // Adds "tree_width" and "own_width" fields to all entries, such that we can print the tree starting from the top.
        // The tree_width is the sum of the children's tree width plus the number of children (spouses == 1!!) minus one.

        foreach ($a as $nKey => $aIndividual) {
            $nWidth = (count($aIndividual['ids']) * 2) - 1;
            $nWidthChildren = count($aIndividual['children']) - 1;
            $this->calculateWidths($a[$nKey]['children']);
            $aIndividual = $a[$nKey]; // To reload the newly added values.
            foreach ($aIndividual['children'] as $aChild) {
                $nWidthChildren += max($aChild['tree_width'], $aChild['own_width']);
            }

            $a[$nKey]['tree_width'] = ($nWidthChildren < 0? 0 : $nWidthChildren);
            $a[$nKey]['own_width']  = $nWidth;
        }
    }





    function drawHTML ()
    {
        // Prints the current tree in HTML format.

        if (!$this->tree) {
            return false;
        }

        // Check if all widths have been calculated already, which is needed for the HTML print.
        if (!isset($this->tree[0]['tree_width'])) {
            $this->calculateWidths($this->tree);
        }

        // Print the tree!
        $a = $this->tree;
        $aNextTree = array(); // The next drawn line are just branches, no individuals.
        print('<TABLE border="0" cellspacing="0" cellpadding="0">' . "\n");
        while ($a) {
            print('  <TR>' . "\n"); // Open generation line.

            if ($aNextTree) {
                // The previous line that was drawn is a line of individuals. This line will be just connecting lines.
                foreach ($aNextTree as $sVal) {
                    if (!$sVal) {
                        // Empty set, to make space.
                        print('    <TD>&nbsp;</TD>' . "\n");
                    } else {
                        print('    <TD><IMG src="gfx/pedigree/l' . $sVal . '.png"></TD>' . "\n");
                    }
                }
                print('  </TR>' . "\n");
                $aNextTree = array();
                continue;
            }

            $aNextGeneration = array(); // For the next generation.

            foreach ($a as $i => $aIndividual) {
                // One individual, or one set of spouses.
                if (!$aIndividual) {
                    // FIXME; when does this really happen?
                    // Empty set, to make space.
                    print('    <TD>&nbsp;</TD>' . "\n");
                    continue;
                } else {
                    // We'll always put one space on the left of each individual, such that we will
                    // automatically create the "padding" we're looking for.
                    if ($i) {
                        $aNextTree[] = '';
                        print('    <TD>&nbsp;</TD>' . "\n");
                    }

                    // Space at left?
                    $nSpace = ($aIndividual['tree_width'] < $aIndividual['own_width']? 0 : $aIndividual['tree_width'] - $aIndividual['own_width']); // 0 or more.
                    for ($i = ($nSpace/2); $i > 0; $i --) {
                        print('    <TD>&nbsp;</TD>' . "\n");
                    }

                    // Print individual itself.
                    foreach ($aIndividual['ids'] as $nKey => $nID) {
                        $aI = $this->individuals[$nID]; 
                        if ($nKey) {
                            // Not the first.
                            print('    <TD><IMG src="gfx/pedigree/l14.png"></TD>' . "\n");
                        }

                        // What kind of line should this individual have in the background?
                        $nLine = 0; // No line by default.
                        if ($aI['father'] || $aI['mother']) {
                            $nLine += 1; // Line to top.
                        }
                        if (!$nKey && count($aIndividual['ids']) > 1) {
                            $nLine += 2; // The first, but not alone, add line to right.
                        } elseif ($nKey) {
                            $nLine += 8; // Not the first, add line to left.
                        }

                        // And what do we have to say about this person?
                        $sDescription = '<IMG src="gfx/individuals/' . (!is_readable(ROOT_PATH . 'gfx/individuals/' . $nID . '.jpg')? 'unknown_' . $aI['gender'] : $nID) . '.jpg" alt="" height="250" align="left" style="margin-right : 10px;">' .
                                        '<B>' . $aI['name'] . '</B><BR>';
                        print('    <TD><A href="#" onmouseover="lovd_showToolTip(\'' . str_replace('"', '\\\'', $sDescription) . '\');" onmouseout="lovd_hideToolTip();" onclick="return false;"><IMG src="gfx/pedigree/u' . $aI['gender'] . ($aI['diseases']? 'a' : 'u') . '.png" style="background : url(\'gfx/pedigree/l0' . $nLine . '.png\');"></A></TD>' . "\n");
                    }

                    // Space at right?
                    for ($i = ($nSpace/2); $i > 0; $i --) {
                        print('    <TD>&nbsp;</TD>' . "\n");
                    }
                }



                // Prepare the data of the children, if present.
                if (!$aIndividual['children']) {
                    // We don't know if maybe the next line is still needed or not.
                    // So we'll prepare it none the less.
                    for ($i = $aIndividual['own_width']; $i > 0; $i --) {
                        $aNextTree[] = '';
                        $aNextGeneration[] = '';
                    }
                    $aNextGeneration[] = ''; // We need one for the padding anyway, of course.

                } else {
                    // The children need space around them, if the parents are wider than the children
                    // (thus, one child, no spouse).
                    if ($aIndividual['tree_width'] < $aIndividual['own_width']) {
                        $aNextTree[] = '';
                        $aNextTree[] = '05';
                        $aNextTree[] = '';
                        $aNextGeneration[] = '';

                    } else {
                        // To be absolutely sure we draw everything correctly, we need to loop through the children.
                        $nChildren = count($aIndividual['children']);
                        $aNextTreeChildren = array(); // To be merged to $aNextTree.
                        foreach ($aIndividual['children'] as $nChild => $aChild) {
                            $bSpouse = ($aChild['own_width'] > 1);
                            $nPosition = ($this->individuals[$aChild['ids'][0]]['father']? 0 : 2);
                            // Space at left?
                            $nSpace = ($aChild['tree_width'] < $aChild['own_width']? 0 : $aChild['tree_width'] - $aChild['own_width']); // 0 or more.
                            for ($i = (($nSpace/2) + $nPosition + (int) ($nChild > 0)); $i > 0; $i --) {
                                $aNextTreeChildren[] = (!$nChild? '' : '10');
                            }
                            // The icon connecting the child with the parent.
                            $aNextTreeChildren[] = (!$nChild? '06' : ($nChild == $nChildren - 1? '12' : '14'));
                            // If there is a spouse on the right, fill the gap.
                            if ($bSpouse && !$nPosition) {
                                $s = ($nChild == $nChildren - 1? '' : '10');
                                $aNextTreeChildren[] = $s;
                                $aNextTreeChildren[] = $s;
                            }
                            // Space at right?
                            for ($i = ($nSpace/2); $i > 0; $i --) {
                                $aNextTreeChildren[] = ($nChild == $nChildren - 1? '' : '10');
                            }
                        }

                        // Now, add the line upwards to the parents, exactly in the middle.
                        $nMiddle = floor($aIndividual['tree_width']/2);
                        $aNextTreeChildren[$nMiddle] = sprintf('%02d', ($aNextTreeChildren[$nMiddle] + 1));
                        $aNextTree = array_merge($aNextTree, $aNextTreeChildren);
                    }

                    $aNextGeneration = array_merge($aNextGeneration, $aIndividual['children']);

                    // The children need space around them, if the parents are wider than the children (thus, one child).
                    if ($aIndividual['tree_width'] < $aIndividual['own_width']) {
                        $aNextGeneration[] = '';
                    }
                }
            }
            print('  </TR>' . "\n");

            // Check if we have something to do.
            $bDone = true;
            foreach ($aNextGeneration as $sVal) {
                if ($sVal) {
                    $bDone = false;
                    break;
                }
            }
            if ($bDone) {
                break;
            }

            $a = $aNextGeneration;
        }
        print('</TABLE>' . "\n");
    }
}







/*
 array(
        # => // Key is irrelevant
         array(
                'ids' => array(001, 002), // Spouses are regarded one unit.
                'children' =>
                 array(
                        # =>
                         array(
                                'ids' => array(003),
                                'children' => array(),
                              ),
                        # =>
                         array(
                                'ids' => array(004),
                                'children' => array(),
                              ),
                        # =>
                         array(
                                'ids' => array(005, 007),
                                'children' =>
                                 array(
                                        # =>
                                         array(
                                                'ids' => array(008),
                                                'children' => array(),
                                              ),
                                        # =>
                                         array(
                                                'ids' => array(009),
                                                'children' => array(),
                                              ),
                                      ),
                              ),
                      ),
              ),
      );
*/
?>
