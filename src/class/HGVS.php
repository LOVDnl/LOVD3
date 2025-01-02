<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2024-11-05
 * Modified    : 2025-01-02
 * For LOVD    : 3.0-31
 *
 * Copyright   : 2004-2024 Leiden University Medical Center; http://www.LUMC.nl/
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





#[AllowDynamicProperties]
class HGVS
{
    // NOTE: Regular expressions should only be used as a single value on a row. When used as part of a row,
    //        you should create an object. The reason for this is that we can't deduce from a regular expression what it
    //        matched. An object holds its value, a string has a fixed value by itself, but a regex can't store a value.
    public array $patterns = [
        'full_variant'       => [ 'HGVS_ReferenceSequence', ':', 'HGVS_Variant', [] ],
        'variant'            => [ 'HGVS_Variant', ['EREFSEQMISSING' => 'This variant is missing a reference sequence.'] ],
        'VCF'                => [ 'HGVS_VCF', ['WVCF' => 'Recognized a VCF-like format; converting this format to HGVS nomenclature.'] ],
        'reference_sequence' => [ 'HGVS_ReferenceSequence', [] ],
        'genome_build'       => [ 'HGVS_Genome', [] ],
    ];
    public array $corrected_values = [];
    public array $data = [];
    public array $info = [];
    public array $memory = [];
    public array $messages = [];
    public array $properties = [];
    public array $regex = [];
    public bool $caseOK = true;
    public bool $matched = false;
    public bool $possibly_incomplete = false;
    public bool $tainted = false;
    public int $patterns_matched = 0;
    public string $input;
    public string $current_pattern;
    public string $matched_pattern;
    public string $suffix;
    public string $value;
    public $parent;

    public function __construct ($sValue, $Parent = null, $bDebugging = false)
    {
        $this->input = $sValue;
        $this->parent = $Parent;

        // Loop through all patterns and match them.
        foreach ($this->patterns as $sPatternName => $aPattern) {
            $this->current_pattern = $sPatternName; // So that children can check what we're doing.
            $aMessages = array_pop($aPattern);
            $sInputToParse = $sValue;
            $bMatching = true;

            // For debugging purposes, only.
            if ($bDebugging) {
                $sClassString = '[' . get_class($this) . "($sPatternName)]";
                $o = $this;
                while ($o->parent) {
                    $sClassString = '[' . get_class($o->parent) . '(' . $o->parent->current_pattern . ') -> ' . $sClassString;
                    $o = $o->parent;
                }
            }

            // Make sure we don't keep anything from any last runs.
            $this->caseOK = true;
            $this->data = [];
            $this->messages = [];

            foreach ($aPattern as $i => $sPattern) {
                // Check for whitespace. This way, we'll nicely handle whitespace between elements,
                //  but not within elements. That is fine; we haven't seen spaces within elements yet.
                if (preg_match('/^\s/', $sInputToParse)) {
                    $sInputToParse = ltrim($sInputToParse);
                    $this->messages['WWHITESPACE'] = 'This variant description contains one or more whitespace characters (spaces, tabs, etc).';
                }

                // Quick check: do we still have something left?
                if ($sInputToParse === '') {
                    if ($bDebugging) {
                        print("$sClassString('$sInputToParse') ran out of input, but expecting more. aborting.\n");
                    }
                    $bMatching = false;
                    // This can be a sign that a variant wasn't submitted completely, and we should try to get more input.
                    $this->possibly_incomplete = true;
                    break;
                }

                if (substr($sPattern, 0, 5) == 'HGVS_') {
                    // This is a class.
                    // Have we seen this before? Ran it already? But not modified it afterward?
                    if (isset($this->memory[$sPattern][$sInputToParse]) && !$this->memory[$sPattern][$sInputToParse]->isTainted()) {
                        if ($bDebugging) {
                            print("$sClassString('$sInputToParse') rule $sPatternName, pattern $sPattern, reusing previous result.\n");
                        }
                        $aPattern[$i] = $this->memory[$sPattern][$sInputToParse];
                    } else {
                        if ($bDebugging) {
                            if (isset($this->memory[$sPattern][$sInputToParse])) {
                                print("$sClassString('$sInputToParse') rule $sPatternName, pattern $sPattern, previous result is tainted, discarding.\n");
                            }
                            print("$sClassString('$sInputToParse') rule $sPatternName, pattern $sPattern, result is pending.\n");
                        }
                        $aPattern[$i] = new $sPattern($sInputToParse, $this, $bDebugging);
                        // Store for later, if needed.
                        $this->memory[$sPattern][$sInputToParse] = $aPattern[$i];
                    }

                    if ($aPattern[$i]->hasMatched()) {
                        // This pattern matched. Store what is left, if anything is left.
                        if ($bDebugging) {
                            print("$sClassString('$sInputToParse') rule $sPatternName, pattern $sPattern, success.\n");
                        }
                        $sInputToParse = $aPattern[$i]->getSuffix();
                        // Merge their data and messages with ours.
                        $this->patterns_matched += $aPattern[$i]->getPatternsMatched();
                        $this->data = array_merge(
                            $this->data,
                            $aPattern[$i]->getData()
                        );
                        if ($bDebugging && $aPattern[$i]->getData()) {
                            print("$sClassString merging data.\n");
                            var_dump($aPattern[$i]->getData());
                        }
                        $this->messages = array_merge(
                            $this->messages,
                            $aPattern[$i]->getMessages()
                        );
                        $this->caseOK &= $aPattern[$i]->isTheCaseOK();

                        // Also store the properties already. Later objects may want to refer to these already
                        //  (e.g., the positions want to check the used prefix).
                        // Get the name, cut "HGVS_" off.
                        $sName = substr($sPattern, 5);
                        // Sometimes we have multiple values. E.g., positions. Store them in an array.
                        if (isset($this->$sName)) {
                            if (!is_array($this->$sName)) {
                                $this->$sName = [ $this->$sName ];
                            }
                            $this->$sName[] = $aPattern[$i];
                        } else {
                            $this->$sName = $aPattern[$i];
                            // Also store this property's name, so we can later unset it if this whole rule didn't match after all.
                            $this->properties[] = $sName;
                        }

                    } else {
                        // Didn't match.
                        if ($bDebugging) {
                            print("$sClassString('$sInputToParse') rule $sPatternName, pattern $sPattern, failed.\n");
                        }
                        $bMatching = false;
                        // We still need to store whether any patterns were matched.
                        $this->patterns_matched += $aPattern[$i]->getPatternsMatched();
                        $this->possibly_incomplete = ($this->possibly_incomplete || $aPattern[$i]->isPossiblyIncomplete());
                        break;
                    }

                } elseif (strlen($sPattern) >= 3 && substr($sPattern, 0, 1) == '/') {
                    // Regex. Make sure it matches the start of the string. Make sure it's case-insensitive.
                    $sPattern = '/^' . substr($sPattern, 1) . 'i';
                    if ($bDebugging) {
                        print("$sClassString('$sInputToParse') rule $sPatternName, pattern $sPattern, and this returned " . (int) preg_match($sPattern, $sInputToParse) . "\n");
                    }
                    if (preg_match($sPattern, $sInputToParse, $aRegs)) {
                        // This pattern matched.
                        $this->patterns_matched ++;
                        // Store what is left, if anything is left.
                        // Note that regexes should not be part of a pattern array, but only get their own pattern line. E.g., this object is all about this regex, or we messed up.
                        $sInputToParse = substr($sInputToParse, strlen($aRegs[0]));
                        // Store the regex values for further processing, if needed.
                        $this->regex = $aRegs;
                    } else {
                        // Didn't match.
                        $bMatching = false;
                        break;
                    }

                } else {
                    // Assume a simple string match.
                    if ($bDebugging) {
                        print("$sClassString('$sInputToParse') rule $sPatternName, pattern $sPattern, and this returned " . (int) (strlen($sInputToParse) >= strlen($sPattern) && substr($sInputToParse, 0, strlen($sPattern)) == $sPattern) . "\n");
                    }
                    if (strlen($sInputToParse) >= strlen($sPattern) && substr($sInputToParse, 0, strlen($sPattern)) == $sPattern) {
                        // This pattern matched.
                        $this->patterns_matched ++;
                        // Store what is left, if anything is left.
                        $sInputToParse = substr($sInputToParse, strlen($sPattern));
                    } else {
                        // Didn't match.
                        $bMatching = false;
                        break;
                    }
                }
            }

            if (!$bMatching) {
                // The rule didn't match, unset any properties that we may have set.
                // Also, when an object is tainted, mark any following objects as well, so they will always be re-run.
                $bTainted = false;
                foreach ($this->properties as $sProperty) {
                    foreach ((is_array($this->$sProperty)? $this->$sProperty : [$this->$sProperty]) as $Component) {
                        if ($Component->isTainted()) {
                            $bTainted = true;
                        } elseif ($bTainted) {
                            $Component->tainted = true;
                        }
                    }
                    unset($this->$sProperty);
                }
                $this->properties = []; // Reset the array, too.
                continue;
            } else {
                $this->matched_pattern = $sPatternName;
                // Permanently store the objects, useful for rebuilding the corrected value later.
                // Restore the messages just in case.
                $this->patterns[$sPatternName] = array_merge($aPattern, [$aMessages]);
            }

            if ($sInputToParse !== '') {
                // We matched everything, but there is a suffix, something left that didn't match.
                // In the main HGVS object, this is a problem. Otherwise, this is what we have to return to the parent.
                $this->value = substr($sValue, 0, -strlen($sInputToParse));
                $this->suffix = $sInputToParse;
                if (!isset($this->parent)) {
                    // This is the main HGVS class. The variant has a suffix that we didn't identify.
                    // If this is just whitespace, this is acceptable, we'll just throw a WWHITESPACE.
                    if (trim($sInputToParse) === '') {
                        $this->messages['WWHITESPACE'] = 'This variant description contains one or more whitespace characters (spaces, tabs, etc).';
                    } else {
                        $this->messages['WINPUTLEFT'] = 'We stopped reading past "' . $this->value . '".';
                    }
                }

            } else {
                // Nothing left at all. We're done!
                $this->value = $sValue;
                $this->suffix = '';
            }

            // Add the message(s) from this specific rule.
            $this->messages = array_merge(
                $this->messages,
                $aMessages
            );

            break;
        }

        // If we have matched, make sure we run additional checks.
        $this->matched = $bMatching;
        if ($bMatching) {
            $this->validate();
        } else {
            $this->messages['EFAIL'] = 'Failed to recognize a HGVS nomenclature-compliant variant description in your input.';
        }
    }





    public function __debugInfo ()
    {
        // This function is called whenever a var_dump() is called on the object.
        // Because we want to limit the space taken up in the var_dump() output, we'll limit it here.

        $aReturn = [
            '__note' => 'The output of var_dump() is reduced by __debugInfo().'
        ];

        foreach ($this as $sPropertyName => $Property) {
            if (!in_array($sPropertyName, ['memory', 'parent', 'patterns'])) {
                $aReturn[$sPropertyName] = $Property;
            }
        }

        return $aReturn;
    }





    public function addCorrectedValue ($sValue, $nConfidence = 1)
    {
        // Conveniently adds the corrected value for us.
        $this->corrected_values[$sValue] = $nConfidence;

        return true;
    }





    public function allowMissingReferenceSequence ()
    {
        // Remove any error message about not having a reference sequence.
        // Apparently, in this context, we're OK not having one.

        if (isset($this->messages['EREFSEQMISSING'])) {
            $this->messages['IREFSEQMISSING'] = $this->messages['EREFSEQMISSING'];
            unset($this->messages['EREFSEQMISSING']);
            // Rebuild the info just in case.
            $this->buildInfo();
        }

        return true;
    }





    public function appendCorrectedValue ($sValue, $nConfidence = 1)
    {
        // Append to any existing corrected value(s), using the given confidence.
        $this->corrected_values = $this->buildCorrectedValues($this->corrected_values, [$sValue => $nConfidence]);

        return true;
    }





    public function arePositionsSorted ($PositionStart, $PositionEnd)
    {
        // This function compares two positions and returns true when $PositionStart is smaller than $PositionEnd,
        //  when there is an unknown value involved, or when the two positions are equal.

        // We can't compare an unknown position with anything, and if the positions are equal, we return true as well.
        if ($PositionStart->unknown || $PositionEnd->unknown
            || $PositionStart->getCorrectedValue() == $PositionEnd->getCorrectedValue()) {
            return true;
        }

        // When the positions are equal, the offsets must be different.
        if ($PositionStart->position == $PositionEnd->position) {
            // We still have the possibility of unknown offsets,
            //  but they can't both be unknown because equal positions have been handled.
            // We decide hereby that unknown offsets should be on the "inside" of the intron (away from the exon).
            // So, we decide 100-?_100 is OK and 100_100+? is OK.
            if ($PositionStart->unknown_offset) {
                return ($PositionStart->offset == -1);
            } elseif ($PositionEnd->unknown_offset) {
                return ($PositionEnd->offset == 1);
            } else {
                // No unknowns left, only numeric offsets.
                return ($PositionStart->offset <= $PositionEnd->offset);
            }

        } else {
            return ($PositionStart->position_sortable < $PositionEnd->position_sortable);
        }
    }





    public function buildCorrectedValues (...$aParts)
    {
        // Since this object can provide multiple choices for corrected values and also produces confidence values,
        //  we need to be a bit intelligent about how to build our values.
        $aCorrectedValues = ['' => 1];

        foreach ($aParts as $Part) {
            if (!is_array($Part)) {
                // Simple addition, full confidence.
                foreach ($aCorrectedValues as $sCorrectedValue => $nConfidence) {
                    unset($aCorrectedValues[$sCorrectedValue]);
                    $aCorrectedValues[$sCorrectedValue . $Part] = $nConfidence;
                }
            } else {
                // Parts and their confidence.
                foreach ($aCorrectedValues as $sCorrectedValue => $nConfidence) {
                    unset($aCorrectedValues[$sCorrectedValue]);
                    foreach ($Part as $sPart => $nPartConfidence) {
                        $aCorrectedValues[$sCorrectedValue . $sPart] = ($nConfidence * $nPartConfidence);
                    }
                }
            }
        }

        return $aCorrectedValues;
    }





    public function buildInfo ()
    {
        // Builds the info array.
        $this->info = array_merge(
            $this->data,
            [
                'messages' => [],
                'warnings' => [],
                'errors' => [],
            ]
        );
        foreach ($this->messages as $sCode => $sMessage) {
            switch (substr($sCode, 0, 1)) {
                case 'E':
                    $this->info['errors'][$sCode] = $sMessage;
                    break;
                case 'W':
                    $this->info['warnings'][$sCode] = $sMessage;
                    break;
                case 'I':
                default:
                    $this->info['messages'][$sCode] = $sMessage;
                    break;
            }
        }

        return $this->info;
    }





    public function discardSuffix ()
    {
        // This function discards the suffix. This is used in text parsing, when
        //  suffixes are very common (periods, commas, closing parentheses).

        $this->input = substr($this->input, 0, -strlen($this->suffix));
        $this->suffix = '';
        unset($this->messages['WINPUTLEFT']);
        // Also reset the info variable, so that we'll have to rebuild it.
        $this->info = [];

        return true;
    }





    public function getCorrectedValue ($nKey = 0)
    {
        // This function gets the first corrected value and returns the string.
        return (array_keys($this->getCorrectedValues())[$nKey] ?? '');
    }





    public function getCorrectedValues ()
    {
        // This function returns the corrected values, possibly building them first.
        if ($this->corrected_values) {
            return $this->corrected_values;
        }

        if (isset($this->getMessages()['EFAIL'])) {
            return [];
        }

        // We didn't correct the value ourselves, which usually means that we are
        //  a high-level class that doesn't do any fixes, or the value is always fine.
        // Let's check.
        $aCorrectedValues = ['' => 1]; // Initialize the array.

        // However, in the presence of errors, lower the confidence.
        // We check for the parent to make sure the confidence isn't lowered too much by stacking.
        if (empty($this->parent)
            && array_filter(array_keys($this->messages), function ($sKey) { return ($sKey[0] == 'E' && $sKey != 'EREFSEQMISSING'); })) {
            $aCorrectedValues = ['' => 0.10];
        }

        foreach (array_slice($this->patterns[$this->matched_pattern], 0, -1) as $Part) {
            if (is_object($Part)) {
                $aCorrectedValues = $this->buildCorrectedValues($aCorrectedValues, $Part->getCorrectedValues());
            } else {
                // String or regex?
                if ($Part[0] == '/') {
                    // A regex. This requires the fix to be manually created because of the case check.
                    // If this is not set, then we will default to the input value with a confidence of 50%.
                    $this->corrected_values = [$this->value => 0.5];
                    return $this->corrected_values;
                }
                $aCorrectedValues = $this->buildCorrectedValues($aCorrectedValues, $Part);
            }
        }

        // If we end up here, we have built something.
        $this->corrected_values = $aCorrectedValues;
        return $this->corrected_values;
    }





    public function getData ()
    {
        return ($this->data ?? []);
    }





    public function getInfo ()
    {
        return ($this->info ?: $this->buildInfo());
    }





    public function getInput ()
    {
        return ($this->input ?? '');
    }





    public function getMatchedPattern ()
    {
        return ($this->matched_pattern ?? false);
    }





    public function getMatchedPatternFormatted ()
    {
        return str_replace('_', ' ', ($this->matched_pattern ?? ''));
    }





    public function getMessages ()
    {
        return ($this->messages ?? []);
    }





    public function getParent ($sClassName = null)
    {
        // Gets the current parent, or, when a class name has been given, returns that specific parent.
        if (!$this->parent) {
            return false;
        } elseif (is_null($sClassName)) {
            return $this->parent;
        } else {
            $o = $this->parent;
            // Let's keep the code simple by using recursion.
            if (get_class($o) == $sClassName) {
                return $o;
            } else {
                return $o->getParent($sClassName);
            }
        }
    }





    public function getParentProperty ($sPropertyName)
    {
        // Finds a certain property in the first parent that it finds that has this property.
        // Useful especially in nested variants; for complex insertions,
        //  our reference sequence may be in the insertion or all the way up to the HGVS object.
        if (!$this->parent) {
            return false;
        } else {
            $o = $this->parent;
            // Let's keep the code simple by using recursion.
            if ($o->hasProperty($sPropertyName)) {
                return $o->$sPropertyName;
            } else {
                return $o->getParentProperty($sPropertyName);
            }
        }
    }





    public function getPatternsMatched ()
    {
        return $this->patterns_matched;
    }





    public function getProperties ()
    {
        return ($this->properties ?? []);
    }





    public function getSuffix ()
    {
        return ($this->suffix ?? '');
    }





    public function getValue ()
    {
        return ($this->value ?? '');
    }





    public function hasMatched ()
    {
        return ($this->matched ?? false);
    }





    public function hasProperty ($sClassName)
    {
        // This function checks if this class has a property called $sClassName.
        // A property is a matched object, stored in the $this->properties array.
        return ($this->properties && is_array($this->properties) && in_array($sClassName, $this->properties));
    }





    public function isTheCaseOK ()
    {
        return $this->caseOK;
    }





    public function isPossiblyIncomplete ()
    {
        return $this->possibly_incomplete;
    }





    public function isTainted ()
    {
        // This returns whether we're tainted. We are, when we've been edited by a different class.
        // E.g., positions edited by variant classes. We then indicate that we need to be rebuilt and not reused.

        if (!$this->tainted && $this->hasMatched()) {
            foreach ($this->patterns[$this->matched_pattern] as $Component) {
                if (is_object($Component) && $Component->isTainted()) {
                    $this->tainted = true; // Make sure we are never re-used.
                    break;
                }
            }
        }
        return $this->tainted;
    }





    public function isValid ()
    {
        // Checks whether this is a valid HGVS-compliant variant description.
        // Class should have matched. If so, build the info if needed, and check whether errors or warnings were given.
        if (!$this->hasMatched()) {
            return false;

        } elseif (empty($this->info)) {
            $this->buildInfo();
        }

        return (
            empty($this->info['errors'])
            && empty(array_diff_key($this->info['warnings'], array_flip(['WNOTSUPPORTED']))));
    }





    public function requireMissingReferenceSequence ()
    {
        // Flips the requirement for a reference sequence.
        // Instead of complaining where there is none, complain when we do have one.

        // We could simply check for EREFSEQMISSING, but that means calling this function twice will result in issues.
        // We are assuming that we're the root class.
        if ($this->hasProperty('ReferenceSequence')) {
            $this->messages['WREFSEQGIVEN'] = 'In this field, a reference sequence should not be provided.';
            // FIXME: And what about the corrected values?

        } else {
            // Unset the error in case we had it.
            unset($this->messages['EREFSEQMISSING']);
        }
        // Rebuild the info just in case.
        $this->buildInfo();

        return true;
    }





    public function setCorrectedValue ($sValue, $nConfidence = 1)
    {
        // Conveniently sets the corrected value for us.
        $this->corrected_values = [$sValue => $nConfidence];
    }





    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        if (!$this->parent) {
            // Top-level validation.
            if (!$this->caseOK) {
                $this->messages['WWRONGCASE'] = 'This is not a valid HGVS description, due to characters being in the wrong case.';
            }
        }
    }
}





class HGVS_DNAAllele extends HGVS
{
    public array $components = [];
    public array $patterns = [
        'multiple_cis'     => [ 'HGVS_DNAVariantBody', ';', 'HGVS_DNAAllele', [] ],
        'multiple_comma'   => [ 'HGVS_DNAVariantBody', ',', 'HGVS_DNAAllele', [ 'WALLELEFORMAT' => 'The allele syntax uses semicolons (;) to separate variants, not commas.' ] ],
        'single'           => [ 'HGVS_DNAVariantBody', [] ],
    ];

    public function getComponents ()
    {
        // This function collects all components stored in this class and puts them in an array.
        if (count($this->components) > 0) {
            return $this->components;
        }

        foreach ($this->patterns[$this->matched_pattern] as $Pattern) {
            if (is_object($Pattern)) {
                if (get_class($Pattern) == 'HGVS_DNAVariantBody') {
                    $this->components[] = $Pattern;
                } else {
                    // Another complex with one or more components.
                    $this->components = array_merge(
                        $this->components,
                        $Pattern->getComponents()
                    );
                }
            }
        }

        return $this->components;
    }





    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        if ($this->matched_pattern == 'multiple_comma') {
            // Fix the separator. Set a slightly lower confidence, because we don't know if this is cis or unknown.
            $this->corrected_values = $this->buildCorrectedValues(
                ['' => 0.9],
                $this->DNAVariantBody->getCorrectedValues(),
                ';',
                $this->DNAAllele->getCorrectedValues()
            );
        }
    }
}





class HGVS_Chr extends HGVS
{
    public array $patterns = [
        [ '/chr/', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->setCorrectedValue(strtolower($this->value));
        $this->caseOK = ($this->value == $this->getCorrectedValue());
    }
}





class HGVS_Chromosome extends HGVS
{
    public array $patterns = [
        'with_prefix'    => [ 'HGVS_Chr', 'HGVS_ChromosomeNumber', [] ],
        'without_prefix' => [ 'HGVS_ChromosomeNumber', [] ],
    ];
    public array $refseqs = [
        'hg18' => [
            '1'  => 'NC_000001.9',
            '2'  => 'NC_000002.10',
            '3'  => 'NC_000003.10',
            '4'  => 'NC_000004.10',
            '5'  => 'NC_000005.8',
            '6'  => 'NC_000006.10',
            '7'  => 'NC_000007.12',
            '8'  => 'NC_000008.9',
            '9'  => 'NC_000009.10',
            '10' => 'NC_000010.9',
            '11' => 'NC_000011.8',
            '12' => 'NC_000012.10',
            '13' => 'NC_000013.9',
            '14' => 'NC_000014.7',
            '15' => 'NC_000015.8',
            '16' => 'NC_000016.8',
            '17' => 'NC_000017.9',
            '18' => 'NC_000018.8',
            '19' => 'NC_000019.8',
            '20' => 'NC_000020.9',
            '21' => 'NC_000021.7',
            '22' => 'NC_000022.9',
            'X'  => 'NC_000023.9',
            'Y'  => 'NC_000024.8',
            'M'  => 'NC_001807.4',
        ],
        'hg19' => [
            '1'  => 'NC_000001.10',
            '2'  => 'NC_000002.11',
            '3'  => 'NC_000003.11',
            '4'  => 'NC_000004.11',
            '5'  => 'NC_000005.9',
            '6'  => 'NC_000006.11',
            '7'  => 'NC_000007.13',
            '8'  => 'NC_000008.10',
            '9'  => 'NC_000009.11',
            '10' => 'NC_000010.10',
            '11' => 'NC_000011.9',
            '12' => 'NC_000012.11',
            '13' => 'NC_000013.10',
            '14' => 'NC_000014.8',
            '15' => 'NC_000015.9',
            '16' => 'NC_000016.9',
            '17' => 'NC_000017.10',
            '18' => 'NC_000018.9',
            '19' => 'NC_000019.9',
            '20' => 'NC_000020.10',
            '21' => 'NC_000021.8',
            '22' => 'NC_000022.10',
            'X'  => 'NC_000023.10',
            'Y'  => 'NC_000024.9',
            'M'  => 'NC_012920.1', // GRCh37; Note that hg19 actually uses NC_001807.4!
        ],
        'hg38' => [
            '1'  => 'NC_000001.11',
            '2'  => 'NC_000002.12',
            '3'  => 'NC_000003.12',
            '4'  => 'NC_000004.12',
            '5'  => 'NC_000005.10',
            '6'  => 'NC_000006.12',
            '7'  => 'NC_000007.14',
            '8'  => 'NC_000008.11',
            '9'  => 'NC_000009.12',
            '10' => 'NC_000010.11',
            '11' => 'NC_000011.10',
            '12' => 'NC_000012.12',
            '13' => 'NC_000013.11',
            '14' => 'NC_000014.9',
            '15' => 'NC_000015.10',
            '16' => 'NC_000016.10',
            '17' => 'NC_000017.11',
            '18' => 'NC_000018.10',
            '19' => 'NC_000019.10',
            '20' => 'NC_000020.11',
            '21' => 'NC_000021.9',
            '22' => 'NC_000022.11',
            'X'  => 'NC_000023.11',
            'Y'  => 'NC_000024.10',
            'M'  => 'NC_012920.1',
        ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        // Our corrected value is a genomic reference sequence.
        // If the parent has a build, use that. Otherwise, use all possible builds.
        $sChr = $this->ChromosomeNumber->getCorrectedValue();
        if (!$this->ChromosomeNumber->isValid()) {
            // We received an invalid chromosome number that we won't be able to handle.
            $this->setCorrectedValue('chr' . $sChr);
        } elseif ($this->getParentProperty('Genome')) {
            // We received a genome build, choose the right NC.
            $this->setCorrectedValue($this->refseqs[$this->getParentProperty('Genome')->getCorrectedValue()][$sChr]);
        } else {
            // We didn't receive a genome build. We'll suggest them all.
            // Note that we don't have very reliable information about how much data each genome build has.
            // The given confidence values are estimations.
            $this->setCorrectedValue($this->refseqs['hg38'][$sChr], 0.5);
            $this->addCorrectedValue($this->refseqs['hg19'][$sChr], 0.45);
            $this->addCorrectedValue($this->refseqs['hg18'][$sChr], 0.05);
        }
    }
}





class HGVS_ChromosomeNumber extends HGVS
{
    public array $patterns = [
        'number' => [ '/[0-9]{1,2}/', [] ],
        'X'      => [ '/X/', [] ],
        'Y'      => [ '/Y/', [] ],
        'M'      => [ '/M/', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->setCorrectedValue(strtoupper($this->value));
        // Assuming use for humans.
        if ($this->matched_pattern == 'number') {
            $this->setCorrectedValue((int) $this->value);
            if (!$this->getCorrectedValue() || $this->getCorrectedValue() > 22) {
                $this->messages['EINVALIDCHROMOSOME'] = 'This variant description contains an invalid chromosome number: "' . $this->value . '".';
            }
        }
    }
}





class HGVS_DNAAlts extends HGVS
{
    public array $patterns = [
        'invalid' => [ '/[A-Z]+/', [] ],
        'valid'   => [ '/[ACGTMRWSYKVHDBN]+/', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $caseCorrection = (get_class($this) == 'HGVS_RNAAlts'? 'strtolower' : 'strtoupper');
        $this->setCorrectedValue($caseCorrection($this->value));
        $this->caseOK = ($this->value == $this->getCorrectedValue());

        // If we had checked the 'valid' rule first, we would not support recognizing invalid nucleotides after valid
        //  nucleotides. The valid ones would match, and we would return the invalid nucleotides as a suffix. That's a
        //  problem, so we're first just matching everything.

        // Check for invalid nucleotides.
        $sUnknownBases = preg_replace($this->patterns['valid'][0] . 'i', '', $this->getCorrectedValue());
        if ($sUnknownBases) {
            $this->messages['EINVALIDNUCLEOTIDES'] = 'This variant description contains invalid nucleotides: "' . implode('", "', array_unique(str_split($sUnknownBases))) . '".';
            // Then, replace the 'U's with 'T's or the other way around.
            if (get_class($this) == 'HGVS_RNAAlts') {
                $this->setCorrectedValue(str_replace('t', 'u', $this->getCorrectedValue()));
            } else {
                $this->setCorrectedValue(str_replace('U', 'T', $this->getCorrectedValue()));
            }
        }
    }
}





class HGVS_DNACon extends HGVS
{
    public array $patterns = [
        [ '/con/', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->setCorrectedValue('delins');
        $this->data['type'] = $this->getCorrectedValue();
        $this->messages['WWRONGTYPE'] = 'A conversion should be described as a deletion-insertion.';
    }
}





class HGVS_DNADel extends HGVS
{
    public array $patterns = [
        [ '/del/', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->setCorrectedValue(strtolower($this->value));
        $this->data['type'] = $this->getCorrectedValue();
        $this->caseOK = ($this->value == $this->getCorrectedValue());
    }
}





class HGVS_DNADelSuffix extends HGVS
{
    // NOTE: This class is used for deletion, duplication, and inversion suffixes. By default, all messages speak of
    //       deletions. When handling other variants, the code will fix the messages. This keeps the code very simple.
    use HGVS_DNASequence; // Gets us getSequence() and getLengths().
    public array $patterns = [
        // Since none of these match "ins", a "delAinsC" won't ever pass here.
        [ 'HGVS_Lengths', [ 'WSUFFIXFORMAT' => 'The part after "del" does not follow HGVS guidelines.' ] ],
        [ '[', 'HGVS_Lengths', ']', [ 'WSUFFIXFORMAT' => 'The part after "del" does not follow HGVS guidelines.' ] ],
        [ 'HGVS_DNARefs', 'HGVS_Lengths', [ 'WSUFFIXFORMAT' => 'The part after "del" does not follow HGVS guidelines.' ] ],
        [ 'HGVS_DNARefs', '[', 'HGVS_Lengths', ']', [] ],
        [ 'HGVS_DNARefs', [] ],
        [ '(', 'HGVS_DNARefs', ')', [ 'WSUFFIXFORMAT' => 'The part after "del" does not follow HGVS guidelines.' ] ],
        [ '(', 'HGVS_DNARefs', 'HGVS_Lengths', ')', [ 'WSUFFIXFORMAT' => 'The part after "del" does not follow HGVS guidelines.' ] ],
        [ '(', 'HGVS_DNARefs', '[', 'HGVS_Lengths', '])', [ 'WSUFFIXFORMAT' => 'The part after "del" does not follow HGVS guidelines.' ] ],
        [ '[', 'HGVS_DNARefs', ']', [ 'WSUFFIXFORMAT' => 'The part after "del" does not follow HGVS guidelines.' ] ],
        [ '[', 'HGVS_DNARefs', 'HGVS_Lengths', ']', [ 'WSUFFIXFORMAT' => 'The part after "del" does not follow HGVS guidelines.' ] ],
        [ '[', 'HGVS_DNARefs', '[', 'HGVS_Lengths', ']]', [ 'WSUFFIXFORMAT' => 'The part after "del" does not follow HGVS guidelines.' ] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $Positions = $this->getParentProperty('DNAPositions');
        $aMessages = $Positions->getMessages();

        // Remove any complaints that HGVS_Lengths may have had, when we already threw a WSUFFIXFORMAT.
        if (isset($this->messages['WSUFFIXFORMAT'])) {
            unset($this->messages['WLENGTHFORMAT'], $this->messages['WLENGTHORDER'], $this->messages['WSAMELENGTHS'], $this->messages['WTOOMANYPARENS']);
        }

        // Don't check anything about the suffix length when there are problems with the positions.
        if (isset($aMessages['EPOSITIONFORMAT']) || isset($aMessages['EPOSITIONLIMIT'])) {
            $this->messages['ISUFFIXNOTVALIDATED'] = "Due to the invalid variant position, the variant's suffix couldn't be fully validated.";
        } else {
            // Check all length requirements.
            // The suffix should not have been used only when the variant length matches the length given in the suffix.
            // Then, also the positions should not be uncertain, if they are.
            // Furthermore, the suffix can never be shorter than the minimum length given by the positions,
            //  and the suffix can never be bigger than the maximum length given by the positions.
            list($nMinLengthVariant, $nMaxLengthVariant) = $Positions->getLengths();
            $bPositionLengthIsCertain = ($nMinLengthVariant == $nMaxLengthVariant);
            list($nMinLengthSuffix, $nMaxLengthSuffix) = $this->getLengths();
            $bSuffixLengthIsUnknown = ($this->hasProperty('Lengths') && $this->Lengths->unknown);
            $bSuffixLengthIsCertain = ($nMinLengthSuffix == $nMaxLengthSuffix && !$bSuffixLengthIsUnknown);

            // Simplest situation first: certain everything, length matches.
            if ($bPositionLengthIsCertain && $bSuffixLengthIsCertain && $nMinLengthVariant == $nMinLengthSuffix) {
                // Throwing this warning will delete the suffix as well.
                $this->messages['WSUFFIXGIVEN'] = "The deleted sequence is redundant and should be removed.";

            } elseif ($bPositionLengthIsCertain && !$bSuffixLengthIsCertain && $nMaxLengthSuffix <= $nMaxLengthVariant) {
                // A special case: When the positions are certain but the deletion is uncertain but fits, this is a special class of warning.
                $this->messages['WPOSITIONSCERTAIN'] =
                    "The variant's positions indicate a certain sequence, but the deletion itself indicates the deleted sequence is uncertain." .
                    " This is a conflict; when the deleted sequence is uncertain, make the variant's positions uncertain by adding parentheses.";
                $Positions->makeUncertain();

            } elseif (!$bPositionLengthIsCertain && $bSuffixLengthIsCertain && $nMaxLengthSuffix == $nMaxLengthVariant) {
                // A special case: When the positions are uncertain but the deletion is certain and fits
                //  the maximum length precisely, this is a special class of warning.
                // Throwing this warning will delete the suffix as well.
                $this->messages['WPOSITIONSUNCERTAIN'] =
                    "The variant's positions indicate an uncertain sequence, but the deletion itself indicates a deleted sequence that fits the given positions precisely." .
                    " This is a conflict; when the deleted sequence is certain, make the variant's positions certain by removing the parentheses and remove the deleted sequence from the variant description.";
                $Positions->makeCertain();

            } elseif (!$bSuffixLengthIsUnknown && !isset($this->messages['EINVALIDNUCLEOTIDES'])) {
                // Universal length checks. These messages are kept universal and slightly simplified.
                // E.g., an ESUFFIXTOOLONG may mean that the deleted sequence CAN BE too long, but isn't always.
                // (e.g., g.(100_200)del(100_300).
                if ($nMinLengthSuffix && $nMinLengthSuffix < $nMinLengthVariant) {
                    $this->messages['ESUFFIXTOOSHORT'] =
                        "The variant's positions indicate a sequence that's longer than the given deleted sequence." .
                        " Please adjust either the variant's positions or the given deleted sequence.";
                }
                if ($nMaxLengthVariant && $nMaxLengthSuffix > $nMaxLengthVariant) {
                    $this->messages['ESUFFIXTOOLONG'] =
                        "The variant's positions indicate a sequence that's shorter than the given deleted sequence." .
                        " Please adjust either the variant's positions or the given deleted sequence.";
                }
            }
        }

        // In case of any error, remove WSUFFIXFORMAT.
        if (isset($this->messages['ELENGTHFORMAT'])) {
            unset($this->messages['WSUFFIXFORMAT']);
        }

        // Store the corrected value.
        if (isset($this->messages['WSUFFIXGIVEN']) || isset($this->messages['WPOSITIONSUNCERTAIN'])) {
            // The suffix should be removed.
            // NOTE: This is not true for delAinsG, but we don't know that here yet.
            $this->setCorrectedValue('');
        } elseif (!isset($this->Lengths)) {
            $this->corrected_values = $this->DNARefs->getCorrectedValues();
        } else {
            $this->corrected_values = $this->buildCorrectedValues(
                (isset($this->DNARefs)? $this->DNARefs->getCorrectedValues() : 'N'),
                (!$this->Lengths->getCorrectedValues()? '' :
                    $this->buildCorrectedValues('[', $this->Lengths->getCorrectedValues(), ']'))
            );
        }
    }
}





class HGVS_DNADup extends HGVS_DNADel
{
    public array $patterns = [
        [ '/dup/', [] ],
    ];
}





class HGVS_DNADupSuffix extends HGVS_DNADelSuffix
{
    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        parent::validate();

        // It's much more efficient to handle deletion suffixes and duplication suffixes in just one class.
        // Therefore, we extend the HGVS_DNADelSuffix class, and inherit all patterns, checks, and validations.
        // However, all warnings and errors are now talking about deletions. Fix this by simply replacing the words.
        foreach ($this->messages as $sCode => $sMessage) {
            $this->messages[$sCode] = str_replace(
                [
                    '"del"',
                    'deletion',
                    'deleted',
                ], [
                    '"dup"',
                    'duplication',
                    'duplicated',
                ],
                $sMessage
            );
        }
    }
}





class HGVS_DNAIns extends HGVS
{
    public array $patterns = [
        [ '/ins/', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->setCorrectedValue(strtolower($this->value));
        $this->data['type'] = (($this->parent->data['type'] ?? '') == 'del'? 'delins' : 'ins');
        $this->caseOK = ($this->value == $this->getCorrectedValue());

        if ($this->data['type'] == 'ins') {
            // Insertions have some specific needs.
            $Positions = $this->getParentProperty('DNAPositions');
            // If one position is given, this is a problem. Only if it's a question mark, can we fix it.
            if (!$Positions->range) {
                if ($Positions->unknown) {
                    // We can correct this. In this case, I think it's better to correct the Positions object than
                    //  to just fix the corrected_value. It's also kinda hard to change the corrected value of some
                    //  other object than our current one. If other changes are needed for whatever reason,
                    //  our sent corrected value may disappear. However, this has a side effect.
                    //  It'll change the variant's getInfo() output.
                    $Positions->addPosition('?');
                    $sCode = 'WPOSITIONMISSING';
                } else {
                    $sCode = 'EPOSITIONMISSING';
                }
                $this->messages[$sCode] =
                    'An insertion must be provided with the two positions between which the insertion has taken place.';

            } elseif ($Positions->DNAPositionStart->range || $Positions->DNAPositionEnd->range) {
                // An insertion should not be defined using more than two positions.
                $this->messages['EPOSITIONFORMAT'] =
                    'An insertion must be provided with the two positions between which the insertion has taken place.';

            } elseif (!$Positions->uncertain && $Positions->getLengths() != [2,2]) {
                // An insertion must always get two positions which are next to each other,
                //  since the inserted nucleotides will be placed in the middle of those.
                $this->messages['WPOSITIONSNOTFORINS'] =
                    'An insertion must have taken place between two neighboring positions.' .
                    ' If the exact location is unknown, please indicate this by placing parentheses around the positions.';
                $Positions->makeUncertain();

            } elseif ($Positions->uncertain && $Positions->getLengths() == [1,2]) {
                // If the exact location of an insertion is unknown, this can be indicated
                //  by placing the positions in the range-format, e.g. c.(1_10)insA. In this
                //  case, the two positions should not be neighbours, since that would imply that
                //  the position is certain.
                $this->messages['WPOSITIONSNOTFORINS'] =
                    'The two positions do not indicate a range longer than two bases.' .
                    ' Please remove the parentheses if the positions are certain.';
                $Positions->makeCertain();
            }
        }
    }
}





class HGVS_DNAInsSuffix extends HGVS
{
    use HGVS_DNASequence; // Gets us getSequence() and getLengths().
    public array $patterns = [
        'complex_in_brackets'              => [ '[', 'HGVS_DNAInsSuffixComplex', ']', [] ],
        'positions_inverted'               => [ 'HGVS_DNAPositions', 'HGVS_DNAInv', [] ],
        'positions'                        => [ 'HGVS_DNAPositions', [] ],
        'length_in_brackets'               => [ '[', 'HGVS_Lengths', ']', [ 'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines.' ] ],
        'sequence_with_number'             => [ 'HGVS_DNAAlts', 'HGVS_Lengths', [ 'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines.' ] ],
        'sequence_with_length'             => [ 'HGVS_DNAAlts', '[', 'HGVS_Lengths', ']', [] ],
        'sequence'                         => [ 'HGVS_DNAAlts', [] ],
        'sequence_in_parens'               => [ '(', 'HGVS_DNAAlts', ')', [ 'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines.' ] ],
        'sequence_with_number_in_parens'   => [ '(', 'HGVS_DNAAlts', 'HGVS_Lengths', ')', [ 'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines.' ] ],
        'sequence_with_length_in_parens'   => [ '(', 'HGVS_DNAAlts', '[', 'HGVS_Lengths', '])', [ 'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines.' ] ],
        'sequence_in_brackets'             => [ '[', 'HGVS_DNAAlts', ']', [ 'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines.' ] ],
        'sequence_with_number_in_brackets' => [ '[', 'HGVS_DNAAlts', 'HGVS_Lengths', ']', [ 'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines.' ] ],
        'sequence_with_length_in_brackets' => [ '[', 'HGVS_DNAAlts', '[', 'HGVS_Lengths', ']]', [ 'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines.' ] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        // Remove any complaints that HGVS_Lengths may have had, when we already threw a WSUFFIXFORMAT.
        if (isset($this->messages['WSUFFIXFORMAT'])) {
            unset($this->messages['WLENGTHFORMAT'], $this->messages['WLENGTHORDER'], $this->messages['WSAMELENGTHS'], $this->messages['WTOOMANYPARENS']);
        }

        // A deletion-insertion of one base to one base, is a substitution.
        // This check is purely done on the position, and any delXins variant is ignored; they will be handled later.
        if ($this->parent->getData()['type'] == 'delins'
            && $this->getParentProperty('DNAPositions')->getLengths() == [1,1]
            && !$this->getParentProperty('DNADelSuffix')
            && isset($this->DNAAlts)
            && $this->getLengths() == [1,1]) {
            $this->messages['WWRONGTYPE'] =
                'A deletion-insertion of one base to one base should be described as a substitution.';
        }

        // Store the corrected value.
        if (isset($this->DNAPositions)) {
            // However, some additional checks are needed.
            // Unknown single positions aren't allowed.
            // Numeric single positions are assumed to be lengths.
            // Uncertain positions can be lengths or positions.
            if ($this->DNAPositions->unknown && !$this->DNAPositions->range) {
                // E.g., ins? or ins(?).
                $this->setCorrectedValue('N[?]', 0.8); // We're not really sure that was what's meant.
                $this->messages['WSUFFIXFORMAT'] = 'The part after "' . $this->parent->getData()['type'] . '" does not follow HGVS guidelines.' .
                    ' To report an insertion of an unknown number of nucleotides, use "' . $this->parent->getData()['type'] . $this->getCorrectedValue() . '".';
                // Also remove the possible warning given by the Positions object. It doesn't like "(?)".
                unset($this->messages['WTOOMANYPARENS']);

            } elseif (!$this->DNAPositions->intronic && !$this->DNAPositions->UTR && !$this->DNAPositions->range) {
                // E.g., ins10 or ins(10). We will only interpret this as a length.
                $this->setCorrectedValue('N[' . $this->DNAPositions->getCorrectedValue() . ']');
                $this->messages['WSUFFIXFORMAT'] = 'The part after "' . $this->parent->getData()['type'] . '" does not follow HGVS guidelines.';
                // Also remove the possible warning given by the Positions object. It doesn't like "(10)".
                unset($this->messages['WTOOMANYPARENS']);

            } elseif ($this->DNAPositions->uncertain && !$this->DNAPositions->intronic && !$this->DNAPositions->UTR
                && !$this->DNAPositions->DNAPositionStart->range && !$this->DNAPositions->DNAPositionEnd->range) {
                // E.g., ins(10_20). Can be lengths (60% certainty) or positions (40% certainty).
                $this->setCorrectedValue('N[' . $this->DNAPositions->getCorrectedValue() . ']', 0.6);
                $this->messages['WSUFFIXFORMAT'] = 'The part after "' . $this->parent->getData()['type'] . '" does not follow HGVS guidelines.' .
                    ' To report an insertion of an uncertain number of nucleotides, use "' . $this->parent->getData()['type'] . $this->getCorrectedValue() . '".';
                $this->DNAPositions->makeCertain();
                $this->addCorrectedValue($this->DNAPositions->getCorrectedValue(), 0.4);
                $this->messages['WSUFFIXFORMAT'] .= ' To refer to the inserted sequence using an uncertain range of positions, use "' . $this->parent->getData()['type'] . array_keys($this->getCorrectedValues())[1] . '".';
                // Also remove the possible warning given by the Positions object. It doesn't like "((10)_(20))".
                unset($this->messages['WTOOMANYPARENS']);

            } else {
                // Anything else, we'll interpret as positions.
                $this->corrected_values = $this->DNAPositions->getCorrectedValues();
            }

            if ($this->matched_pattern == 'positions_inverted') {
                $this->appendCorrectedValue($this->DNAInv->getCorrectedValue());
            }

        } elseif (isset($this->DNAAlts) && !isset($this->Lengths)) {
            $this->corrected_values = $this->DNAAlts->getCorrectedValues();

        } elseif (isset($this->Lengths)) {
            $this->corrected_values = $this->buildCorrectedValues(
                (isset($this->DNAAlts)? $this->DNAAlts->getCorrectedValues() : 'N'),
                (!$this->Lengths->getCorrectedValues()? '' :
                    $this->buildCorrectedValues('[', $this->Lengths->getCorrectedValues(), ']'))
            );
        } else {
            // Complex insertions. The DNAInsSuffixComplex object should have filtered the components already.
            // The square brackets should go when there is only one child and there are no reference sequences involved.
            $aComponents = $this->DNAInsSuffixComplex->getComponents();
            $nComponents = count($aComponents);
            if ($this->matched_pattern == 'complex_in_brackets'
                && $nComponents == 1
                && !$aComponents[0]->hasProperty('ReferenceSequence')) {
                // The brackets should go.
                $this->messages['WSUFFIXFORMATNOTCOMPLEX'] = 'The part after "ins" does not follow HGVS guidelines. Only use square brackets for complex insertions.';
                $this->corrected_values = $this->DNAInsSuffixComplex->getCorrectedValues();

            } else {
                $this->corrected_values = $this->buildCorrectedValues(
                    '[', $this->DNAInsSuffixComplex->getCorrectedValues(), ']'
                );
            }
        }

        // In case of any error, remove WSUFFIXFORMAT.
        if (array_filter(array_keys($this->messages), function ($sKey) { return ($sKey[0] == 'E'); })) {
            unset($this->messages['WSUFFIXFORMAT']);
        }
    }
}





class HGVS_DNAInsSuffixComplex extends HGVS
{
    public array $components = [];
    public array $patterns = [
        'multiple' => [ 'HGVS_DNAInsSuffixComplexComponent', ';', 'HGVS_DNAInsSuffixComplex', [] ],
        'single'   => [ 'HGVS_DNAInsSuffixComplexComponent', [] ],
    ];

    public function getComponents ()
    {
        // This function collects all components stored in this class and puts them in an array.
        if (count($this->components) > 0) {
            return $this->components;
        }

        foreach ($this->patterns[$this->matched_pattern] as $Pattern) {
            if (is_object($Pattern)) {
                if (get_class($Pattern) == 'HGVS_DNAInsSuffixComplexComponent') {
                    $this->components[] = $Pattern;
                } else {
                    // Another complex with one or more components.
                    $this->components = array_merge(
                        $this->components,
                        $Pattern->getComponents()
                    );
                }
            }
        }

        // Loop through it again, filtering out components that should have been one.
        // Sequences should be merged if they follow each other.
        $nComponents = count($this->components);
        for ($nKey = 1; $nKey < $nComponents; $nKey ++) {
            if ($this->components[$nKey - 1]->getProperties() == ['DNAAlts']
                && $this->components[$nKey]->getProperties() == ['DNAAlts']) {
                // Two sequential sequence components (simple or repeat syntax); merge them.
                // The overhead is very small, so let's just build something new.
                // To be consistent, we'll create a new ComplexComponent.
                $sSequenceA = $this->components[$nKey - 1]->getCorrectedValue();
                $sSequenceB = $this->components[$nKey]->getCorrectedValue();
                array_splice(
                    $this->components,
                    ($nKey - 1),
                    2,
                    [new HGVS_DNAInsSuffixComplexComponent($sSequenceA . $sSequenceB)]
                );
                $this->messages['WSUFFIXFORMATCOMPLEXINS'] = 'The part after "ins" does not follow HGVS guidelines. Inserted sequences "' . $sSequenceA . '" and "' . $sSequenceB . '" should be merged.';
                $nComponents --;
                $nKey --;
            }
        }

        return $this->components;
    }





    public function getCorrectedValues ()
    {
        // This function returns the corrected values, possibly building them first.
        // This function had to be overloaded because I may have modified the components and I can't use the patterns.
        if ($this->corrected_values) {
            return $this->corrected_values;
        }

        $aCorrectedValues = [];
        foreach ($this->getComponents() as $nKey => $Component) {
            if ($nKey) {
                $aCorrectedValues[] = ';'; // To separate the components.
            }
            $aCorrectedValues[] = $Component->getCorrectedValues();
        }

        // Now, build the whole array.
        $this->corrected_values = $this->buildCorrectedValues(...$aCorrectedValues);
        return $this->corrected_values;
    }





    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        // This triggers additional validations, so run it here.
        $this->corrected_values = $this->getCorrectedValues();
    }
}





class HGVS_DNAInsSuffixComplexComponent extends HGVS
{
    public array $patterns = [
        'positions_with_refseq_inv' => [ 'HGVS_ReferenceSequence', ':', 'HGVS_DNAPrefix', 'HGVS_Dot', 'HGVS_DNAPositions', 'HGVS_DNAInv', [] ],
        'positions_with_refseq'     => [ 'HGVS_ReferenceSequence', ':', 'HGVS_DNAPrefix', 'HGVS_Dot', 'HGVS_DNAPositions', [] ],
        'sequence_with_length'      => [ 'HGVS_DNAAlts', '[', 'HGVS_Lengths', ']', [] ],
        'sequence'                  => [ 'HGVS_DNAAlts', [] ],
        'positions_inverted'        => [ 'HGVS_DNAPositions', 'HGVS_DNAInv', [] ],
        'positions'                 => [ 'HGVS_DNAPositions', [] ],
    ];
}





class HGVS_DNAInv extends HGVS
{
    public array $patterns = [
        [ '/inv/', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->setCorrectedValue(strtolower($this->value));
        if (!$this->getParent('HGVS_DNAInsSuffix')) {
            // We are *not* in an insertion, set the variant type.
            $this->data['type'] = $this->getCorrectedValue();
        }
        $this->caseOK = ($this->value == $this->getCorrectedValue());

        // Inversions have some specific needs.
        $Positions = $this->getParentProperty('DNAPositions');
        // If one position is given, this is a problem. Only if it's a question mark, can we fix it.
        if (!$Positions->range) {
            if ($Positions->unknown) {
                // We can correct this. In this case, I think it's better to correct the Positions object than
                //  to just fix the corrected_value. It's also kinda hard to change the corrected value of some
                //  other object than our current one. If other changes are needed for whatever reason,
                //  our sent corrected value may disappear. However, this has a side effect.
                //  It'll change the variant's getInfo() output.
                $Positions->addPosition('?');
                $sCode = 'WPOSITIONMISSING';
            } else {
                $sCode = 'EPOSITIONMISSING';
            }
            $this->messages[$sCode] =
                'Inversions require a length of at least two bases.';

        } elseif ($Positions->uncertain && $Positions->getLengths() == [1,2]) {
            // If the exact location of an inversion is unknown, this can be indicated
            //  by placing the positions in the range-format, e.g. c.(1_10)inv. In this
            //  case, the two positions should not be neighbours, since that would imply that
            //  the position is certain.
            $this->messages['WPOSITIONSNOTFORINV'] =
                'The two positions do not indicate a range longer than two bases.' .
                ' Please remove the parentheses if the positions are certain.';
            $Positions->makeCertain();
        }
    }
}





class HGVS_DNAInvSuffix extends HGVS_DNADelSuffix
{
    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        parent::validate();

        // It's much more efficient to handle deletion suffixes and inversion suffixes in just one class.
        // Therefore, we extend the HGVS_DNADelSuffix class, and inherit all patterns, checks, and validations.
        // However, all warnings and errors are now talking about deletions. Fix this by simply replacing the words.
        foreach ($this->messages as $sCode => $sMessage) {
            $this->messages[$sCode] = str_replace(
                [
                    '"del"',
                    'deletion',
                    'deleted',
                ], [
                    '"inv"',
                    'inversion',
                    'inverted',
                ],
                $sMessage
            );
        }
    }
}





class HGVS_DNANull extends HGVS
{
    public array $patterns = [
        'predicted' => [ '0?', [] ],
        'observed'  => [ '0', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        // We're a bit special. We don't allow input to be left that may be a position.
        // The reason for this is that we don't want to match DNAPositions starting with a zero.
        // However, if we would go last in line, the DNAPositions + DNAUnknown would pick c.0? up.
        if ($this->suffix !== '' && preg_match('/^[0-9_*+-]/', $this->suffix)) {
            // There is more left that could be position. We're not an actual DNANull.
            $this->matched = false;
            return;
        }

        $this->data['type'] = substr($this->getCorrectedValue(), 0, 1);
        $this->predicted = ($this->matched_pattern == 'predicted');

        $Prefix = $this->getParentProperty('DNAPrefix');
        if ($Prefix && in_array($Prefix->getCorrectedValue(), ['g', 'm'])) {
            // This is only allowed for transcript-based reference sequences, as it's a consequence of a genomic change
            //  (a deletion or so). It indicates the lack of expression of the transcript.
            $this->messages['EWRONGTYPE'] = 'The 0-allele is used to indicate there is no expression of a given transcript. This can not be used for genomic variants.';
        }
    }
}





class HGVS_DNAPipe extends HGVS
{
    use HGVS_CheckBasesGiven; // Gives us checkBasesGiven().
    public array $patterns = [
        'pipe(s)' => [ '/\|+/', [] ],
        'nothing' => [ 'HGVS_DNAPipeSuffix', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->setCorrectedValue('|');
        $this->data['type'] = 'met'; // Generalized to methylation-related variants.

        // Check any possible Refs compared to the positions. If they match, complain about the given Refs.
        $this->checkBasesGiven();

        if ($this->matched_pattern == 'nothing') {
            // This description doesn't use a pipe, but should.
            // Reset the suffix to make sure we can match it again.
            $this->suffix = $this->input;
            $this->messages['WPIPEMISSING'] = 'Please place a "|" between the positions and the variant type.';

        } elseif ($this->value != $this->getCorrectedValue()) {
            $this->messages['WTOOMANYPIPES'] = 'One single pipe character is used to indicate a methylation-related variant.';
        }
    }
}





class HGVS_DNAPipeSuffix extends HGVS
{
    public array $patterns = [
        'met=' => [ '/met=/', [] ],
        'met'  => [ '/met/', [] ],
        'gom'  => [ '/gom/', [] ],
        'lom'  => [ '/lom/', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->setCorrectedValue(strtolower($this->value));
        $this->caseOK = ($this->value == $this->getCorrectedValue());
        if ($this->matched_pattern == 'met') {
            $this->appendCorrectedValue('=');
            $this->messages['WMETFORMAT'] = 'To report normal methylation, use "met=".';
        }
    }
}





class HGVS_DNAPosition extends HGVS
{
    public array $patterns = [
        'unknown'          => [ '?', [] ],
        'unknown_intronic' => [ '/([-‐*]?([0-9]+))([+‐-]\?)/u', [] ],
        'known'            => [ '/([-‐*]?([0-9]+))([+‐-]([0-9]+))?/u', [] ], // Note: We're using these sub patterns in the validation.
        'pter'             => [ '/pter/', [] ],
        'qter'             => [ '/qter/', [] ],
    ];
    public array $position_limits = [
        'g' => [1, 4294967295, 0, 0], // position min, position max, offset min, offset max.
        'm' => [1, 4294967295, 0, 0],
        'o' => [1, 4294967295, 0, 0],
        'c' => [-8388608, 8388607, -2147483648, 2147483647],
        'n' => [1, 8388607, -2147483648, 2147483647],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->unknown = ($this->matched_pattern == 'unknown');
        $this->unknown_offset = ($this->matched_pattern == 'unknown_intronic');
        $VariantPrefix = $this->getParentProperty('DNAPrefix');
        $sVariantPrefix = ($VariantPrefix? $VariantPrefix->getCorrectedValue() : 'g'); // VCFs usually don't have a prefix.
        $this->ISCN = false;
        $this->position_limits = $this->position_limits[$sVariantPrefix];
        $nCorrectionConfidence = 1;

        if ($this->matched_pattern == 'unknown') {
            $this->UTR = false;
            $this->intronic = false;
            $this->position = $this->value;
            $this->position_sortable = null; // This depends on how this position is used; start or end?
            $this->offset = 0;
            // Set the intronic range to 0.
            $this->position_limits[2] = 0;
            $this->position_limits[3] = 0;

        } elseif (in_array($this->matched_pattern, ['pter', 'qter'])) {
            $RefSeq = $this->getParentProperty('ReferenceSequence');
            if ($RefSeq && $RefSeq->molecule_type != 'chromosome') {
                $this->messages['EWRONGREFERENCE'] =
                    'A chromosomal reference sequence is required for pter, cen, or qter positions.';
            }
            $this->setCorrectedValue(strtolower($this->value));
            $this->caseOK = ($this->value == $this->getCorrectedValue());
            $this->UTR = false;
            $this->intronic = false;
            $this->offset = 0;
            if ($this->matched_pattern == 'pter') {
                $this->position = $this->position_limits[0];
                $this->position_limits[1] = $this->position;
            } else {
                $this->position = $this->position_limits[1];
                $this->position_limits[0] = $this->position;
            }
            $this->position_sortable = $this->position;
            $this->position_limits[2] = 0;
            $this->position_limits[3] = 0;
            $this->ISCN = true;

        } else {
            // We've seen input from papers that don't use a hyphen-minus (-) but a non-breaking hyphen (‐).
            // Since the user can't really see the difference, it's not really an error, but we do need to fix it.
            if (strpos($this->value, '‐') !== false) {
                array_walk($this->regex, function (&$sValue) {
                    $sValue = str_replace('‐', '-', $sValue);
                });
                $this->messages['WPOSITIONFORMAT'] = 'Invalid character "‐" found in variant position; only regular hyphens are allowed to be used in the HGVS nomenclature.';
            }

            $this->UTR = !ctype_digit($this->value[0]);
            $this->intronic = isset($this->regex[3]);

            // Store the position and sortable position separately.
            if ($this->value[0] == '*') {
                // 3' UTR. Force the number to an int, to remove 0-prefixed values.
                $this->position = '*' . (int) $this->regex[2];
                $this->position_sortable = 1000000 + (int) $this->regex[2];
            } else {
                $this->position = (int) $this->regex[1];
                $this->position_sortable = $this->position;
            }

            // For intronic positions, split the value in position and offset.
            if (!$this->intronic) {
                $this->offset = 0;
            } else {
                if ($this->matched_pattern == 'unknown_intronic') {
                    // +? == +1, -? == -1.
                    $this->offset = (int) ($this->regex[3][0] . '1');
                } else {
                    $this->offset = (int) $this->regex[3];
                }
            }

            // Check for values with zeros.
            if (!$this->position || $this->position == '*0') {
                $this->messages['EPOSITIONFORMAT'] = 'This variant description contains an invalid position: "' . $this->value . '".';
            } elseif ((string) $this->position !== $this->regex[1]) {
                $this->messages['WPOSITIONWITHZERO'] = 'Variant positions should not be prefixed by a 0.';
                $nCorrectionConfidence *= 0.9;
            }
            if ($this->intronic && !$this->unknown_offset) {
                if (!$this->offset) {
                    $this->messages['EPOSITIONFORMAT'] = 'This variant description contains an invalid intronic position: "' . $this->value . '".';
                    // Automatically, the corrected value will simply drop the intronic offset.
                    // That's a very inconfident change, but throwing an error already reduces the confidence immensely.
                    $nCorrectionConfidence *= 0.75;
                } elseif ((string) abs($this->offset) !== $this->regex[4]) {
                    $this->messages['WINTRONICPOSITIONWITHZERO'] = 'Intronic positions should not be prefixed by a 0.';
                    $nCorrectionConfidence *= 0.9;
                }
            }

            // Check minimum and maximum values.
            // E.g., disallow negative values for genomic sequences, etc.
            if ($this->position_limits[0] == 1 && $this->UTR) {
                $this->messages['EFALSEUTR'] = 'Only coding transcripts (c. prefix) have a UTR region. Therefore, position "' . $this->value . '" which describes a position in the UTR, is invalid when using the "' . $sVariantPrefix . '" prefix.';
            } elseif ($this->position_sortable < $this->position_limits[0] || $this->position_sortable > $this->position_limits[1]) {
                $this->messages['EPOSITIONLIMIT'] = 'Position is beyond the possible limits of its type: "' . $this->value . '".';
            } elseif ($this->intronic) {
                if ($this->position_limits[2] == 0) {
                    $this->messages['EFALSEINTRONIC'] = 'Only transcripts (c. or n. prefixes) have introns. Therefore, position "' . $this->value . '" which describes a position in the intron, is invalid when using the "' . $sVariantPrefix . '" prefix.';
                } elseif ($this->offset < $this->position_limits[2] || $this->offset > $this->position_limits[3]) {
                    $this->messages['EPOSITIONLIMIT'] = 'Position is beyond the possible limits of its type: "' . $this->value . '".';
                }
            }

            // Intronic positions require a "genome_transcript" type of reference sequence.
            if ($this->intronic) {
                $RefSeq = $this->getParentProperty('ReferenceSequence');
                if ($RefSeq && $RefSeq->molecule_type != 'genome_transcript') {
                    $this->messages['EWRONGREFERENCE'] =
                        'A genomic transcript reference sequence is required to verify intronic positions.';
                }
            }

            // Adjust minimum and maximum values, to be used in further processing, but keep within limits.
            if ($this->position_sortable < $this->position_limits[0]) {
                $this->position_limits[1] = $this->position_limits[0];
            } elseif ($this->position_sortable > $this->position_limits[1]) {
                $this->position_limits[0] = $this->position_limits[1];
            } else {
                $this->position_limits[0] = $this->position_sortable;
                $this->position_limits[1] = $this->position_sortable;
            }
            if (!$this->intronic) {
                $this->position_limits[2] = 0;
                $this->position_limits[3] = 0;
            } elseif ($this->matched_pattern != 'unknown_intronic') {
                if ($this->offset < $this->position_limits[2]) {
                    $this->position_limits[3] = $this->position_limits[2];
                } elseif ($this->offset > $this->position_limits[3]) {
                    $this->position_limits[2] = $this->position_limits[3];
                } else {
                    $this->position_limits[2] = $this->offset;
                    $this->position_limits[3] = $this->offset;
                }
            } elseif ($this->offset > 0) {
                // +?, minimum is 1.
                $this->position_limits[2] = $this->offset;
            } else {
                // -?, maximum is -1.
                $this->position_limits[3] = $this->offset;
            }

            // Store the corrected value.
            $this->corrected_values = $this->buildCorrectedValues(
                ['' => $nCorrectionConfidence],
                $this->position .
                ($this->offset? ($this->offset > 0? '+' : '-') . ($this->unknown_offset? '?' : abs($this->offset)) : '')
            );
        }
    }
}





class HGVS_DNAPositionStart extends HGVS
{
    public array $patterns = [
        'uncertain_range'  => [ '(', 'HGVS_DNAPosition', '_', 'HGVS_DNAPosition', ')', [] ],
        'uncertain_single' => [ '(', 'HGVS_DNAPosition', ')', [ 'WTOOMANYPARENS' => "The variant's positions contain redundant parentheses." ] ],
        'single'           => [ 'HGVS_DNAPosition', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->range = is_array($this->DNAPosition); // This will fail if we don't have this property, which is good, because that shouldn't happen.
        $this->uncertain = ($this->matched_pattern == 'uncertain_range');
        $nCorrectionConfidence = (current($this->corrected_values) ?: 1); // Fetch current one, because this object can be revalidated.

        if (!$this->range) {
            // A single position, just copy everything.
            foreach (['unknown', 'unknown_offset', 'UTR', 'intronic', 'position', 'position_sortable', 'position_limits', 'offset', 'ISCN'] as $variable) {
                $this->$variable = $this->DNAPosition->$variable;
            }

        } else {
            // Copy the booleans first.
            foreach (['unknown', 'unknown_offset', 'UTR', 'intronic', 'ISCN'] as $variable) {
                $this->$variable = ($this->DNAPosition[0]->$variable || $this->DNAPosition[1]->$variable);
            }

            // Before we add more errors or warnings, check if we have multiple errors that are the same.
            // We currently don't handle arrays as error messages.
            $VariantPrefix = $this->getParentProperty('DNAPrefix');
            $sVariantPrefix = ($VariantPrefix? $VariantPrefix->getCorrectedValue() : 'g');
            // Get new messages for errors that occurred twice.
            $aDoubleMessages = array_intersect_key(
                [
                    'EFALSEUTR' => 'Only coding transcripts (c. prefix) have a UTR region. Multiple positions given describe a position in the UTR and are invalid when using the "' . $sVariantPrefix . '" prefix.',
                    'EPOSITIONLIMIT' => 'Multiple position given are beyond the possible limits of its type.',
                    'EFALSEINTRONIC' => 'Only transcripts (c. or n. prefixes) have introns. Multiple positions given describe a position in the intron and are invalid when using the "' . $sVariantPrefix . '" prefix.',
                ],
                $this->DNAPosition[0]->getMessages(),
                $this->DNAPosition[1]->getMessages()
            );
            $this->messages = array_merge($this->messages, $aDoubleMessages);

            // If the positions are the same, warn and remove one.
            if ($this->DNAPosition[0]->position == $this->DNAPosition[1]->position) {
                if ($this->DNAPosition[0]->getCorrectedValue() == $this->DNAPosition[1]->getCorrectedValue()) {
                    $this->messages['WSAMEPOSITIONS'] = 'This variant description contains two positions that are the same.';
                    $nCorrectionConfidence *= 0.9;
                    // Discard the other object.
                    $this->DNAPosition = $this->DNAPosition[0];

                } elseif (($this->DNAPosition[0]->offset < 0 && $this->DNAPosition[1]->offset > 0)
                    || ($this->DNAPosition[0]->offset > 0 && $this->DNAPosition[1]->offset < 0)) {
                    // The offsets are not on the same side of the intron. That is an error.
                    $this->messages['EPOSITIONFORMAT'] = 'This variant description contains an invalid position: "' . $this->value . '".';
                }

            } elseif (get_class($this) == 'HGVS_DNAPositionStart' && $this->DNAPosition[1]->unknown) {
                // The inner positions cannot be unknown. E.g., g.(100_?)_(?_200) should become g.(100_200).
                $this->messages['WTOOMUCHUNKNOWN'] = 'This variant description contains redundant unknown positions.';
                // Copy the maximum limit from this unknown position to the remaining position. It's not precise.
                $this->DNAPosition[0]->position_limits[1] = $this->DNAPosition[1]->position_limits[1];
                $this->DNAPosition = $this->DNAPosition[0];
                $this->DNAPosition->uncertain = true;
                $this->unknown = false;

            } elseif (get_class($this) == 'HGVS_DNAPositionEnd' && $this->DNAPosition[0]->unknown) {
                // The inner positions cannot be unknown. E.g., g.(100_?)_(?_200) should become g.(100_200).
                $this->messages['WTOOMUCHUNKNOWN'] = 'This variant description contains redundant unknown positions.';
                // Copy the minimum limit from this unknown position to the remaining position. It's not precise.
                $this->DNAPosition[1]->position_limits[0] = $this->DNAPosition[0]->position_limits[0];
                $this->DNAPosition = $this->DNAPosition[1];
                $this->DNAPosition->uncertain = true;
                $this->unknown = false;
            }



            // Check if the positions are given in the right order and store values.
            if (!is_array($this->DNAPosition)) {
                foreach (['position', 'position_sortable', 'position_limits', 'offset'] as $variable) {
                    $this->$variable = $this->DNAPosition->$variable;
                }
                $this->range = false;

            } else {
                // OK, we're still a range. Check the variant's order.
                if (!$this->arePositionsSorted($this->DNAPosition[0], $this->DNAPosition[1])) {
                    $this->messages['WPOSITIONORDER'] = "The variant's positions are not given in the correct order.";
                    $nCorrectionConfidence *= 0.9;
                    // Swap the positions.
                    $this->DNAPosition = [$this->DNAPosition[1], $this->DNAPosition[0]];
                }

                // Give unknown positions a sortable position (which is currently set to null).
                if ($this->DNAPosition[0]->unknown) {
                    // Position starts with "?_", store the smallest possible value.
                    $this->DNAPosition[0]->position_sortable = $this->DNAPosition[0]->position_limits[0];
                } elseif ($this->DNAPosition[1]->unknown) {
                    // Position ends with "_?", store the highest possible value.
                    $this->DNAPosition[1]->position_sortable = $this->DNAPosition[1]->position_limits[1];
                }

                // Storing the positions.
                // After discussing the issue, it is decided to use to inner positions in cases where the positions are
                //  uncertain. This means that e.g. c.(1_2)_(5_6)del will be returned as having a position_start of 2
                //  and a position_end of 5. However, if we find a variant such as c.(1_?)_(?_6)del, we will save the
                //  outer positions (so a position_start of 1 and a position_end of 6).
                if (get_class($this) == 'HGVS_DNAPositionStart') {
                    $iPositionToStore = ($this->DNAPosition[1]->unknown? 0 : 1);
                } else {
                    $iPositionToStore = ($this->DNAPosition[0]->unknown? 1 : 0);
                }
                foreach (['position', 'position_sortable', 'offset'] as $variable) {
                    $this->$variable = $this->DNAPosition[$iPositionToStore]->$variable;
                }

                // For the limits of this range, store the start position minimum values,
                //  and the end position's maximum values. That does change the meaning of the values a bit.
                // Normally, either the position range is fixed or the offset range is fixed. Now, both can be a range.
                // The minimum values for position and offset together form the minimum position.
                // The maximum values for position and offset together form the maximum position.
                $this->position_limits = [
                    $this->DNAPosition[0]->position_limits[0],
                    $this->DNAPosition[1]->position_limits[1],
                    $this->DNAPosition[0]->position_limits[2],
                    $this->DNAPosition[1]->position_limits[3],
                ];
            }
        }

        // Now, store the corrected value.
        if ($this->range) {
            $this->corrected_values = $this->buildCorrectedValues(
                ['' => $nCorrectionConfidence],
                '(', $this->DNAPosition[0]->getCorrectedValues(), '_', $this->DNAPosition[1]->getCorrectedValues(), ')'
            );
        } else {
            $this->corrected_values = $this->buildCorrectedValues(
                ['' => $nCorrectionConfidence],
                $this->DNAPosition->getCorrectedValues()
            );
        }
    }
}
class HGVS_DNAPositionEnd extends HGVS_DNAPositionStart {}





class HGVS_DNAPositions extends HGVS
{
    public array $patterns = [
        'range'            => [ 'HGVS_DNAPositionStart', '_', 'HGVS_DNAPositionEnd', [] ],
        'uncertain_range'  => [ '(', 'HGVS_DNAPositionStart', '_', 'HGVS_DNAPositionEnd', ')', [] ],
        'uncertain_single' => [ '(', 'HGVS_DNAPosition', ')', [ 'WTOOMANYPARENS' => "This variant description contains a position with redundant parentheses." ] ],
        'single'           => [ 'HGVS_DNAPosition', [] ],
    ];
    public array $lengths = [];

    public function addPosition ($sValue)
    {
        // This function adds a position to the current position, making this position a range.
        if (!$this->range) {
            $NewPosition = new HGVS_DNAPositionEnd($sValue, $this);
            if ($NewPosition->hasMatched() && !$NewPosition->getSuffix()) {
                // All seems well. We'll have to create a new object for the Start as well to prevent errors.
                $this->DNAPositionStart = new HGVS_DNAPositionStart($this->DNAPosition->getCorrectedValue(), $this);
                $this->DNAPositionEnd = $NewPosition;
                unset($this->DNAPosition);
                // Re-run the entire validation, so that all internal values will be set correctly.
                // This may cause issues with errors that don't reflect the user's input.
                // Trick validate() into thinking we matched a different pattern.
                $this->matched_pattern = str_replace('single', 'range', $this->matched_pattern);
                // Also unset the length, so it will be re-calculated.
                $this->lengths = [];
                $this->validate();
                $this->tainted = true; // Make sure we are never re-used.
                return true;
            }
        }

        // We already have two positions, or something is wrong with the given value.
        return false;
    }





    public function getLengths ()
    {
        // This function calculates the minimum and maximum lengths of these positions, and returns them in an array.
        // This function isn't very precise. The given lengths will be incorrect when:
        // - introns are included in the range (applies to c. variants);
        // - the stop codon is included in the range (applies to c. variants).
        // The given lengths can not be determined at all when:
        // - breakpoints are unknown (not uncertain), e.g., g.?_100del;
        // - intronic positions are used and the center of the intron is passed (e.g., c.100+123_101-456del).
        // When a distance can't be determined at all, this function may choose to return false for that length.
        // Note that the distance between the Start and End is not the variant length.
        // E.g., c.1_2 has a distance of 1 and a length of 2. The minimum distance is 0, but the minimum length is 1.
        $aReturn = [0,0];

        // An array with the positions to check for the minimum (key: 0) and maximum (key: 1) lengths.
        $aPositionsToCheck = [];

        // If this were called repeatedly, cache the results.
        if ($this->lengths) {
            return $this->lengths;
        }

        if (!$this->range) {
            return [1,1];
        }

        // Store the positions that we'll use to determine the length.
        // The maximum length can always be determined by our own limits.
        $aPositionsToCheck[1] = [
            [ // position, offset (leftmost position)
                $this->position_limits[0], $this->position_limits[2]
            ],
            [ // position, offset (rightmost position)
                $this->position_limits[1], $this->position_limits[3]
            ],
        ];

        // If either Start or End is a single unknown position, we'll have a minimum length of 1.
        if ($this->DNAPositionStart->getCorrectedValue() == '?'
            || $this->DNAPositionEnd->getCorrectedValue() == '?') {
            $aReturn[0] = 1;

        } elseif (!$this->uncertain && !$this->unknown_offset) {
            // The minimum distance is the maximum distance.
            $aPositionsToCheck[0] = $aPositionsToCheck[1];

        } else {
            // We have a single Start and End within uncertainty or unknown offset,
            //  or Start and/or End are ranges, causing uncertainty.
            // There are no single unknown positions here.
            // There may be outer unknown positions, but those don't matter here.
            if (!$this->DNAPositionStart->range && !$this->DNAPositionEnd->range && !$this->unknown_offset) {
                // The uncertainty is indicated by the user. E.g., g.(100_200)del. Min length is 1.
                $aReturn[0] = 1;
            } else {
                // Take the inner ranges.
                $aPositionsToCheck[0] = [
                    [
                        $this->DNAPositionStart->position_limits[1],
                        $this->DNAPositionStart->position_limits[3]
                    ],
                    [
                        $this->DNAPositionEnd->position_limits[0],
                        $this->DNAPositionEnd->position_limits[2]
                    ],
                ];
            }
        }

        // Now that we collected the positions to compare, calculate the distance.
        foreach ($aPositionsToCheck as $i => $aPositions) {
            list($PosStart, $PosEnd) = $aPositions;
            $nBasicLength = $PosEnd[0] - $PosStart[0] + 1;

            // For the minimum distance, we're doing a simple trick to handle crossing intron centers.
            // E.g., we can't determine the length of c.100+10_200-10del. We'll change it to c.101-10_199+10del to at least have something.
            if (!$i && $nBasicLength > 2) {
                if ($PosStart[1] > 0) {
                    $PosStart[0] ++;
                    $PosStart[1] *= -1;
                    $nBasicLength --;
                }
                if ($PosEnd[1] < 0) {
                    $PosEnd[0] --;
                    $PosEnd[1] *= -1;
                    $nBasicLength --;
                }
            }

            if (!$PosStart[1] && !$PosEnd[1]) {
                // Simple case; genomic variant or simply no introns involved.
                $aReturn[$i] = $nBasicLength;

            } elseif ($PosStart[0] < $PosEnd[0] && ($PosStart[1] > 0 || $PosEnd[1] < 0)) {
                // Exonic positions are not the same, and we're crossing the center of an intron.
                // We implemented a trick for the minimum distance, but it can still happen for c.100+10_101-10del,
                //  because I have no clue how to nicely handle that.
                // All maximum distances crossing intron centers also end up here.
                $aReturn[$i] = false;

            } else{
                // We know we're not crossing intron centers. This calculation works for variants
                //  inside the same intron as well as variants in different introns.
                $aReturn[$i] = $nBasicLength + $PosEnd[1] - $PosStart[1];
            }
        }

        $this->lengths = $aReturn; // Cache it for next time.
        return $aReturn;
    }





    public function makeCertain ()
    {
        // This function makes the current Positions certain, if possible.
        if ($this->range && $this->uncertain
            && !$this->DNAPositionStart->uncertain && !$this->DNAPositionEnd->uncertain) {
            // Trick validate() into thinking we matched a different pattern.
            $this->matched_pattern = 'range';
            // Also unset the length, so it will be re-calculated.
            $this->lengths = [];
            // Re-run the entire validation, so that all internal values will be set correctly.
            // This may cause issues with errors that don't reflect the user's input.
            $this->validate();
            // We're not super confident about this.
            $this->appendCorrectedValue('', 0.75);
            $this->tainted = true; // Make sure we are never re-used.
            return true;
        }

        // We're not a range, we were already certain,
        //  or I can't make us certain because the Start or End are uncertain.
        return false;
    }





    public function makeUncertain ()
    {
        // This function makes the current Positions uncertain.
        if ($this->range && !$this->uncertain) {
            // Trick validate() into thinking we matched a different pattern.
            $this->matched_pattern = 'uncertain_range';
            // Also unset the length, so it will be re-calculated.
            $this->lengths = [];
            // Re-run the entire validation, so that all internal values will be set correctly.
            // This may cause issues with errors that don't reflect the user's input.
            $this->validate();
            // We're not super confident about this.
            $this->appendCorrectedValue('', 0.75);
            $this->tainted = true; // Make sure we are never re-used.
            return true;
        }

        // We're not a range, or we were already uncertain.
        return false;
    }





    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->range = (in_array($this->matched_pattern, ['range', 'uncertain_range']));
        $this->uncertain = (
            $this->matched_pattern == 'uncertain_range'
            || ($this->matched_pattern == 'range'
                && ($this->DNAPositionStart->uncertain || $this->DNAPositionEnd->uncertain))
        );
        $VariantPrefix = ($this->getParentProperty('DNAPrefix') ?: new HGVS_DNAPrefix('g')); // VCFs usually don't have a prefix.
        $nCorrectionConfidence = (current($this->corrected_values) ?: 1); // Fetch current one, because this object can be revalidated.

        if (!$this->range) {
            // A single position, just copy everything.
            foreach (['unknown', 'unknown_offset', 'UTR', 'intronic', 'position', 'position_sortable', 'position_limits', 'offset', 'ISCN'] as $variable) {
                $this->$variable = $this->DNAPosition->$variable;
            }

        } else {
            // Copy only the booleans; the rest doesn't apply to a range.
            foreach (['unknown', 'unknown_offset', 'UTR', 'intronic', 'ISCN'] as $variable) {
                $this->$variable = ($this->DNAPositionStart->$variable || $this->DNAPositionEnd->$variable);
            }

            // Before we add more errors or warnings, check if we have multiple errors that are the same.
            // We currently don't handle arrays as error messages.
            $sVariantPrefix = $VariantPrefix->getCorrectedValue();
            // Get new messages for errors that occurred twice.
            $aDoubleMessages = array_intersect_key(
                [
                    'EFALSEUTR' => 'Only coding transcripts (c. prefix) have a UTR region. Multiple positions given describe a position in the UTR and are invalid when using the "' . $sVariantPrefix . '" prefix.',
                    'EPOSITIONLIMIT' => 'Multiple position given are beyond the possible limits of its type.',
                    'EFALSEINTRONIC' => 'Only transcripts (c. or n. prefixes) have introns. Multiple positions given describe a position in the intron and are invalid when using the "' . $sVariantPrefix . '" prefix.',
                ],
                $this->DNAPositionStart->getMessages(),
                $this->DNAPositionEnd->getMessages()
            );
            $this->messages = array_merge($this->messages, $aDoubleMessages);

            // If the positions are the same, warn and remove one.
            if ($this->DNAPositionStart->getCorrectedValue() == $this->DNAPositionEnd->getCorrectedValue()
                && !$this->DNAPositionStart->unknown) {
                // Exception: Start and End _can_ be both unknown, e.g., g.?_?ins[...].
                $this->messages['WSAMEPOSITIONS'] = 'This variant description contains two positions that are the same.';
                $nCorrectionConfidence *= 0.9;
                // Discard the other object.
                $this->DNAPosition = $this->DNAPositionStart;
                $this->range = false;
                foreach (['position', 'position_sortable', 'position_limits', 'offset'] as $variable) {
                    $this->$variable = $this->DNAPosition->$variable;
                }
            }



            // Check if the positions are given in the right order and store values.
            if ($this->range) {
                // Checking the positions is a bit more complex now, because start and end _can_ be ranges, too.
                // It's unclear whether the HGVS nomenclature allows for, e.g., g.100_(100_200)del.
                // However, I see no other clear way of saying "a deletion starting at 100, and possibly extending up to 200".
                // g.(99_100)_(100_200)del does not seem to be a good alternative to me,
                //  especially considering the ambiguity in its interpretation about whether A and D are included in the deletion.
                // Therefore, we will allow B and C to be equal, regardless of whether Start and End are ranges or not.

                // In (A_B)_(C_D), the positions are in the wrong order when D<A, they overlap when C<B, and they're OK when B>C.
                $PositionA = $this->DNAPositionStart; // Will anyway be B if A == ?.
                $PositionB = ($this->DNAPositionStart->range? $this->DNAPositionStart->DNAPosition[1] : $this->DNAPositionStart);
                $PositionC = ($this->DNAPositionEnd->range? $this->DNAPositionEnd->DNAPosition[0] : $this->DNAPositionEnd);
                $PositionD = $this->DNAPositionEnd; // Will anyway be C if D == ?.

                if (!$this->arePositionsSorted($PositionA, $PositionD)) {
                    $this->messages['WPOSITIONORDER'] = "This variant description contains positions not given in the correct order.";
                    // Due to excessive complexity with ranges and possible solutions and assumptions,
                    //  we'll only swap positions when neither Start nor End is a range.
                    if (!$this->DNAPositionStart->range && !$this->DNAPositionEnd->range) {
                        // Resort the positions.
                        list($this->DNAPositionStart, $this->DNAPositionEnd) = [$this->DNAPositionEnd, $this->DNAPositionStart];
                        $nCorrectionConfidence *= 0.9;

                    } elseif (!$this->DNAPositionStart->unknown && !$this->DNAPositionEnd->unknown) {
                        // OK, actually, we can also swap the positions when they are ranges but there are no unknowns.
                        // We do need to do this differently, though. Swapping the variables like above will have side
                        //  effects since DNAPositionStart and DNAPositionEnd handle positions slightly differently.
                        $DNAPositionStart = new HGVS_DNAPositionStart($this->DNAPositionEnd->getCorrectedValue(), $this);
                        $DNAPositionEnd = new HGVS_DNAPositionEnd($this->DNAPositionStart->getCorrectedValue(), $this);
                        list($this->DNAPositionStart, $this->DNAPositionEnd) = [$DNAPositionStart, $DNAPositionEnd];
                        $nCorrectionConfidence *= 0.8;
                    }

                } elseif (!$this->arePositionsSorted($PositionB, $PositionC)) {
                    // We can't fix that, so throw an error, not a warning.
                    $this->messages['EPOSITIONFORMAT'] = "This variant description contains positions that overlap but that are not the same.";

                } elseif ($this->DNAPositionStart->range && $this->DNAPositionStart->unknown
                    && !$this->DNAPositionEnd->range && $this->DNAPositionEnd->unknown) {
                    // g.(?_A)_?del. Should be g.(?_A)_(A_?)del.
                    $this->messages['WPOSITIONFORMAT'] = "This variant description contains uncertain positions described using an incorrect format.";
                    // It's easier to just rebuild the whole thing.
                    $this->DNAPositionEnd = new HGVS_DNAPositionEnd('(' . $this->DNAPositionStart->DNAPosition[1]->getCorrectedValue() . '_?)', $this);

                } elseif (!$this->DNAPositionStart->range && $this->DNAPositionStart->unknown
                    && $this->DNAPositionEnd->range && $this->DNAPositionEnd->unknown) {
                    // g.?_(A_?)del. Should be g.(?_A)_(A_?)del.
                    $this->messages['WPOSITIONFORMAT'] = "This variant description contains uncertain positions described using an incorrect format.";
                    // It's easier to just rebuild the whole thing.
                    $this->DNAPositionStart = new HGVS_DNAPositionStart('(?_' . $this->DNAPositionEnd->DNAPosition[0]->getCorrectedValue() . ')', $this);
                }

                // I earlier removed internal uncertainty, e.g., g.(100_?)_(?_200) to g.(100_200).
                // I then set the position_limits of Start and End to those of "?".
                // Now that we both have a Start and an End, fix this.
                if (!$this->DNAPositionStart->range && $this->DNAPositionStart->uncertain
                    && ($this->DNAPositionEnd->range || !$this->DNAPositionEnd->unknown)) {
                    $this->DNAPositionStart->position_limits[1] = $PositionC->position_sortable;
                    $this->DNAPositionStart->position_limits[3] = $PositionC->offset;
                }
                if (!$this->DNAPositionEnd->range && $this->DNAPositionEnd->uncertain
                    && ($this->DNAPositionStart->range || !$this->DNAPositionStart->unknown)) {
                    $this->DNAPositionEnd->position_limits[0] = $PositionB->position_sortable;
                    $this->DNAPositionEnd->position_limits[2] = $PositionB->offset;
                }

                // For the limits of this range, store the start position minimum values,
                //  and the end position's maximum values. That does change the meaning of the values a bit.
                // Normally, either the position range is fixed or the offset range is fixed. Now, both can be a range.
                // The minimum values for position and offset together form the minimum position.
                // The maximum values for position and offset together form the maximum position.
                $this->position_limits = [
                    $this->DNAPositionStart->position_limits[0],
                    $this->DNAPositionEnd->position_limits[1],
                    $this->DNAPositionStart->position_limits[2],
                    $this->DNAPositionEnd->position_limits[3],
                ];
            }
        }

        // Store the positions in the data array, but only if we're Positions directly under HGVS_DNAVariantBody.
        if (get_class($this->parent) == 'HGVS_DNAVariantBody') {
            $aPositions = ($this->range? [$this->DNAPositionStart, $this->DNAPositionEnd] : [$this->DNAPosition, $this->DNAPosition]);
            // Unknown positions have no sortable position and store their extremes.
            if (!isset($aPositions[0]->position_sortable)
                || $aPositions[0]->position_sortable < $aPositions[0]->position_limits[0]) {
                $this->data['position_start'] = $aPositions[0]->position_limits[0];
            } elseif ($aPositions[0]->position_sortable > $aPositions[0]->position_limits[1]) {
                $this->data['position_start'] = $aPositions[0]->position_limits[1];
            } else {
                $this->data['position_start'] = $aPositions[0]->position_sortable;
            }
            if (!isset($aPositions[1]->position_sortable)
                || $aPositions[1]->position_sortable > $aPositions[1]->position_limits[1]) {
                $this->data['position_end'] = $aPositions[1]->position_limits[1];
            } elseif ($aPositions[1]->position_sortable < $aPositions[1]->position_limits[0]) {
                $this->data['position_end'] = $aPositions[1]->position_limits[0];
            } else {
                $this->data['position_end'] = $aPositions[1]->position_sortable;
            }
            if ($VariantPrefix && $VariantPrefix->molecule_type == 'transcript') {
                if ($aPositions[0]->offset < $aPositions[0]->position_limits[2]) {
                    $this->data['position_start_intron'] = $aPositions[0]->position_limits[2];
                } elseif ($aPositions[0]->offset > $aPositions[0]->position_limits[3]) {
                    $this->data['position_start_intron'] = $aPositions[0]->position_limits[3];
                } else {
                    $this->data['position_start_intron'] = $aPositions[0]->offset;
                }
                if ($aPositions[1]->offset > $aPositions[1]->position_limits[3]) {
                    $this->data['position_end_intron'] = $aPositions[1]->position_limits[3];
                } elseif ($aPositions[1]->offset < $aPositions[1]->position_limits[2]) {
                    $this->data['position_end_intron'] = $aPositions[1]->position_limits[2];
                } else {
                    $this->data['position_end_intron'] = $aPositions[1]->offset;
                }
            }
            $this->data['range'] = $this->range;

            // Since we know we're the positions of this variant (although we could still be just part of an allele),
            //  add some additional checks related to positions and prefixes or reference sequences.
            if (isset($this->messages['EFALSEUTR'])) {
                // This warning has been triggered by the variant's prefix. Now, check the reference sequence, too.
                // If we use a genomic reference sequence, there isn't much to do; the error must be in the position.
                // But when there isn't a reference sequence, have a closer look at the given positions; if they are
                //  very small, this won't be a genomic variant; the prefix is probably an error.
                // First, fix the positions.
                $this->data['position_start'] = 0;
                $this->data['position_end'] = 0;
                $RefSeq = $this->getParentProperty('ReferenceSequence');
                if (($RefSeq && in_array($RefSeq->molecule_type, ['genome_transcript', 'transcript']))
                    || !array_diff_key($this->messages, array_flip(['EFALSEUTR', 'EFALSEINTRONIC']))) {
                    // Option 1: Both the reference sequence and the positions indicate this is a transcript.
                    //           The prefix already threw an EWRONGREFERENCE, but that didn't suggest a fix.
                    // Option 2: We only have EFALSEUTR, the rest looks good.
                    // Just suggest using c, the only prefix with an UTR.
                    $VariantPrefix->setCorrectedValue('c');
                }
            }
        }

        // Now, store the corrected value.
        if ($this->range && $this->uncertain
            && !$this->DNAPositionStart->range && !$this->DNAPositionEnd->range) {
            $this->corrected_values = $this->buildCorrectedValues(
                ['' => $nCorrectionConfidence],
                '(', $this->DNAPositionStart->getCorrectedValues(), '_', $this->DNAPositionEnd->getCorrectedValues(), ')'
            );
        } elseif ($this->range) {
            $this->corrected_values = $this->buildCorrectedValues(
                ['' => $nCorrectionConfidence],
                $this->DNAPositionStart->getCorrectedValues(), '_', $this->DNAPositionEnd->getCorrectedValues()
            );
        } else {
            $this->corrected_values = $this->buildCorrectedValues(
                ['' => $nCorrectionConfidence],
                $this->DNAPosition->getCorrectedValues()
            );
        }

        // Do a final check, if perhaps a hyphen should have been an underscore.
        if (isset($this->messages['EFALSEINTRONIC']) && strpos($this->getValue(), '-') !== false) {
            // Sometimes users use a hyphen where they mean an underscore. Do a simple check for this.
            // I don't think we can have multiple corrected values here, but just in case.
            $aCorrections = $this->getCorrectedValues();
            $aNewCorrections = [];
            $aNewData = [];
            foreach ($aCorrections as $sPositions => $nConfidence) {
                // First, check if we haven't already done something to solve the problem.
                if ($sPositions == $this->getValue()) {
                    // Our current suggestion is unchanged, and therefore, invalid. Try to replace the hyphen.
                    $PositionsCorrected = new HGVS_DNAPositions(str_replace('-', '_', $sPositions), $this->parent);
                    if ($PositionsCorrected->isValid() && !$PositionsCorrected->getSuffix()) {
                        // This is a better option then what we have. Replace this.
                        // Add a higher confidence since the error will reduce the confidence with a factor 0.1.
                        $aNewCorrections[$PositionsCorrected->getCorrectedValue()] = ($nConfidence * 10);
                        if (!$aNewData) {
                            //  Also, collect the new data.
                            $aNewData = $PositionsCorrected->getData();
                        }
                        continue;
                    }
                }
                // If we get here, the fix failed. Don't change a thing.
                $aNewCorrections[$sPositions] = $nConfidence;
            }

            // Overwrite everything, when needed.
            if ($aCorrections != $aNewCorrections) {
                $this->corrected_values = $aNewCorrections;
                $this->data = array_merge(
                    $this->getData(),
                    $aNewData
                );
            }
        }
    }
}





class HGVS_DNAPrefix extends HGVS
{
    public array $patterns = [
        'coding'        => [ '/c/', [] ],
        'genomic'       => [ '/g/', [] ],
        'mitochondrial' => [ '/m/', [] ],
        'non-coding'    => [ '/n/', [] ],
        'circular'      => [ '/o/', [] ],
        'nothing'       => [ 'HGVS_Dot', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->molecule_type = (in_array($this->matched_pattern, ['coding', 'non-coding'])? 'transcript' : 'genome');
        $this->setCorrectedValue(strtolower($this->value));
        $this->caseOK = ($this->value == $this->getCorrectedValue());

        // If we have seen a reference sequence, check if we match that.
        $RefSeq = $this->getParentProperty('ReferenceSequence');
        if ($RefSeq && !empty($RefSeq->allowed_prefixes)) {
            if ($this->matched_pattern == 'nothing') {
                // Simple assumption based on the reference sequence.
                $this->molecule_type = ($RefSeq->molecule_type == 'genome_transcript'? 'transcript' :
                    ($RefSeq->molecule_type == 'chromosome'? 'genome' : $RefSeq->molecule_type));
                $nConfidence = (1 / count($RefSeq->allowed_prefixes));
                $this->corrected_values = array_combine(
                    $RefSeq->allowed_prefixes,
                    array_fill(0, count($RefSeq->allowed_prefixes), $nConfidence)
                );
                $this->suffix = $this->input; // Reset the suffix in case HGVS_Dot took something.
                $this->messages['WPREFIXMISSING'] = 'This variant description seems incomplete. Variant descriptions should start with a molecule type (e.g., "' . $this->getCorrectedValue() . '.").';

            } elseif (!in_array($this->getCorrectedValue(), $RefSeq->allowed_prefixes)) {
                $this->messages['EWRONGREFERENCE'] =
                    'The given reference sequence (' . $RefSeq->getCorrectedValue() . ') does not match the DNA type (' . $this->getCorrectedValue() . ').' .
                    ' For variants on ' . $RefSeq->getCorrectedValue() . ', please use the ' . implode('. or ', $RefSeq->allowed_prefixes) . '. prefix.' .
                    ' For ' . $this->getCorrectedValue() . '. variants, please use a ' . $this->matched_pattern .
                    ($this->matched_pattern == 'genomic'? '' : ' ' . $this->molecule_type) . ' reference sequence.';
            }

        } elseif ($this->matched_pattern == 'nothing') {
            // We can't assume based on a reference sequence, so we'll just do a wild guess and throw an error.
            $this->molecule_type = 'transcript';
            // If we've seen a prefix before (like, we're in an insertion), take that.
            $PreviousPrefix = $this->getParentProperty('HGVS_DNAPrefix');
            if ($PreviousPrefix) {
                $this->corrected_values = $PreviousPrefix->getCorrectedValues();
            } else {
                $this->corrected_values = [
                    'c' => 0.25,
                    'g' => 0.25,
                    'm' => 0.25,
                    'n' => 0.25
                ];
            }
            $this->suffix = $this->input; // Reset the suffix in case HGVS_Dot took something.
            $this->messages['EPREFIXMISSING'] = 'This variant description seems incomplete. Variant descriptions should start with a molecule type (e.g., "' . $this->getCorrectedValue() . '.").';
        }
    }
}





class HGVS_DNARefs extends HGVS
{
    public array $patterns = [
        'invalid' => [ '/[A-Z]+/', [] ],
        'valid'   => [ '/[ACGTN]+/', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $caseCorrection = (get_class($this) == 'HGVS_RNARefs'? 'strtolower' : 'strtoupper');
        $this->setCorrectedValue($caseCorrection($this->value));
        $this->caseOK = ($this->value == $this->getCorrectedValue());

        // If we had checked the 'valid' rule first, we would not support recognizing invalid nucleotides after valid
        //  nucleotides. The valid ones would match, and we would return the invalid nucleotides as a suffix. That's a
        //  problem, so we're first just matching everything.

        // First, we need to prevent that we're matching HGVS reserved terms, like "ins".
        // If we do, we need to pretend that we never matched that part and what follows.
        $nReservedWord = false;
        foreach (['con', 'del', 'dup', 'ins', 'inv'] as $sKeyword) {
            $n = strpos($this->getCorrectedValue(), $caseCorrection($sKeyword));
            if ($n !== false && ($nReservedWord === false || $n < $nReservedWord)) {
                $nReservedWord = $n;
            }
        }
        if ($nReservedWord !== false) {
            // OK, we can't match this part. We can match anything that came before, though.
            if (!$nReservedWord) {
                // The string starts with a reserved keyword. Pretend that didn't match anything.
                $this->matched = false;
                return;
            } else {
                // Register that we matched up to the reserved keyword.
                $this->suffix = substr($this->value, $nReservedWord) . $this->suffix;
                $this->value = substr($this->value, 0, $nReservedWord);
                $this->setCorrectedValue($caseCorrection($this->value));
                $this->caseOK = ($this->value == $this->getCorrectedValue());
            }
        }

        // OK, with that out of the way, we can check for invalid nucleotides.
        $sUnknownBases = preg_replace($this->patterns['valid'][0] . 'i', '', $this->getCorrectedValue());
        if ($sUnknownBases) {
            $this->messages['EINVALIDNUCLEOTIDES'] = 'This variant description contains invalid nucleotides: "' . implode('", "', array_unique(str_split($sUnknownBases))) . '".';
            // Then, replace the 'U's with 'T's or the other way around.
            if (get_class($this) == 'HGVS_RNARefs') {
                $this->setCorrectedValue(str_replace('t', 'u', $this->getCorrectedValue()));
            } else {
                $this->setCorrectedValue(str_replace('U', 'T', $this->getCorrectedValue()));
            }
        }
    }
}





class HGVS_DNARepeat extends HGVS
{
    public array $components = [];
    public array $patterns = [
        'multiple' => [ 'HGVS_DNARepeatComponent', 'HGVS_DNARepeat', [] ],
        'single'   => [ 'HGVS_DNARepeatComponent', [] ],
    ];

    public function getComponents ()
    {
        // This function collects all components stored in this class and puts them in an array.
        if (count($this->components) > 0) {
            return $this->components;
        }

        foreach ($this->patterns[$this->matched_pattern] as $Pattern) {
            if (is_object($Pattern)) {
                if (get_class($Pattern) == 'HGVS_DNARepeatComponent') {
                    $this->components[] = $Pattern;
                } else {
                    // Another complex with one or more components.
                    $this->components = array_merge(
                        $this->components,
                        $Pattern->getComponents()
                    );
                }
            }
        }

        return $this->components;
    }





    public function getCorrectedValues ()
    {
        // This function returns the corrected values, possibly building them first.
        // This function had to be overloaded because I may have modified the components and I can't use the patterns.
        if ($this->corrected_values) {
            return $this->corrected_values;
        }

        $aCorrectedValues = [];
        foreach ($this->getComponents() as $Component) {
            $aCorrectedValues[] = $Component->getCorrectedValues();
        }

        // Now, build the whole array.
        $this->corrected_values = $this->buildCorrectedValues(...$aCorrectedValues);
        return $this->corrected_values;
    }





    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->data['type'] = 'repeat';
        $this->corrected_values = $this->getCorrectedValues();

        // Run a full validation, but only when we're the "main" repeat class.
        if ($this->parent && get_class($this->parent) == 'HGVS_DNAVariantType') {
            $aRepeatUnits = $this->getComponents();

            // Repeats can't have uncertain positions.
            $Positions = $this->getParentProperty('DNAPositions');
            if ($Positions && ($Positions->uncertain || $Positions->unknown)) {
                $this->messages['EWRONGTYPE'] = 'The repeat syntax can not be used with uncertain positions. Rewrite your variant description as a deletion or insertion, depending on whether the repeat contracted or expanded.';

            } else {
                // Full validation of the repeat.
                $Prefix = $this->getParentProperty('DNAPrefix');
                $sPrefix = ($Prefix? $Prefix->getCorrectedValue() : 'g');
                if ($sPrefix == 'c') {
                    foreach ($aRepeatUnits as $Component) {
                        list($nMinLength, $nMaxLength) = $Component->getLengths();
                        if ($nMinLength == $nMaxLength && ($nMinLength % 3)) {
                            // Repeat variants on coding DNA should always have a length of a multiple of three bases.
                            $this->messages['EINVALIDREPEATLENGTH'] =
                                'A repeat sequence of coding DNA should always have a length of (a multiple of) 3.';
                            break;
                        }
                    }
                }

                if (empty($this->messages['EINVALIDREPEATLENGTH']) && $Positions && $Positions->range) {
                    // Do a rudimentary length check. Take all given bases, and compare it to the positions.
                    // We don't know the number of repeats that the reference has, but at least the bases should fit.
                    $nPositionsLength = $Positions->getLengths()[0];
                    // This is the simplest way, not going through all objects.
                    $sSequence = preg_replace('/\[[^\]]+\]/', '', $this->getValue());
                    $nSequenceLength = strlen($sSequence);

                    if ($nSequenceLength > $nPositionsLength) {
                        $this->messages['EINVALIDREPEATLENGTH'] =
                            'The sequence ' . $sSequence . ' does not fit in the given positions ' . $Positions->getCorrectedValue() . '. Adjust your positions or the given sequences.';

                    } elseif (count($aRepeatUnits) == 1 && ($nPositionsLength % $nSequenceLength)) {
                        $this->messages['EINVALIDREPEATLENGTH'] =
                            'The given repeat unit (' . $sSequence . ') does not fit in the given positions ' . $Positions->getCorrectedValue() . '. Adjust your positions or the given sequences.';

                    } else {
                        // OK, this one is complex. We have multiple repeats (e.g., g.1_9AC[20]GT[10])
                        //  and we need to check if any combination of units fits the given positions.
                        $bInvalidLength = true;

                        // Collect all sequence lengths.
                        $aRepeatUnitCounts = array_map(
                            function ($Component) {
                                return [strlen($Component->DNAAlts->getCorrectedValue()), 1];
                            }, $aRepeatUnits
                        );

                        // Now, loop through all possible sequence combinations to make sure that I know that a certain
                        //  combination of repeats fits the given sequence perfectly (the assumed wild-type sequence).
                        while (true) {
                            $nTotalLength = array_reduce(
                                $aRepeatUnitCounts,
                                function ($nCurrentLength, $aRepeatUnit) {
                                    return $nCurrentLength + ($aRepeatUnit[0] * $aRepeatUnit[1]);
                                }
                            );
                            if ($nTotalLength == $nPositionsLength) {
                                // Fits perfectly!
                                $bInvalidLength = false;
                                break;

                            } elseif ($nTotalLength > $nPositionsLength) {
                                // See if we can continue somehow.
                                for ($i = array_key_last($aRepeatUnitCounts); $i >= 0; $i--) {
                                    if ($aRepeatUnitCounts[$i][1] == 1) {
                                        // This unit is present just once, continue up the list.
                                        continue;
                                    }
                                    // This is the first non-1 value in the list. If it's the first repeat, we're done.
                                    if (!$i) {
                                        // Nope, it doesn't fit.
                                        break 2;
                                    } else {
                                        // Reset this unit and try to increase the previous one.
                                        $aRepeatUnitCounts[$i][1] = 1;
                                        $aRepeatUnitCounts[$i - 1][1]++;
                                        break;
                                    }
                                }

                            } else {
                                // We're not there yet.
                                $aRepeatUnitCounts[array_key_last($aRepeatUnitCounts)][1]++;
                            }
                        }

                        if ($bInvalidLength) {
                            $this->messages['EINVALIDREPEATLENGTH'] =
                                'The given repeat units (' . implode(', ', array_map(function ($Component) { return $Component->DNAAlts->getCorrectedValue(); }, $aRepeatUnits)) . ') do not fit in the given positions ' . $Positions->getCorrectedValue() . '. Adjust your positions or the given sequences.';
                        }
                    }
                }
            }

            // If there is a suffix, check for sequence without a length. We assume they forgot a "[1]".
            if ($this->suffix !== '') {
                $Suffix = new HGVS_DNAAlts($this->suffix, $this);
                if ($Suffix && $Suffix->isValid()) {
                    $this->messages['WSUFFIXFORMAT'] =
                        'The part after "' . $aRepeatUnits[array_key_last($aRepeatUnits)]->getValue() . '" does not follow HGVS guidelines.' .
                        ' When describing repeats, each unit needs a length.';
                    // Add the sequence to the repeats and try again.
                    $this->components[] = new HGVS_DNARepeatComponent($this->suffix . '[1]');
                    $this->corrected_values = [];
                    $this->suffix = '';
                    unset($this->messages['EINVALIDREPEATLENGTH']);
                    return $this->validate();
                }
            }
        }
    }
}





class HGVS_DNARepeatComponent extends HGVS
{
    use HGVS_DNASequence;
    public array $patterns = [
        // NOTE: We're using DNAAlts, because mixed repeats can be described using IUPAC codes other than A, C, G, or T.
        'sequence_with_length'      => [ 'HGVS_DNAAlts', '[', 'HGVS_Lengths', ']', [] ],
    ];
}





class HGVS_DNASomatic extends HGVS
{
    public array $patterns = [
        [ '/\/+/', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->setCorrectedValue(substr($this->value, 0, 2)); // Maximum number of slashes: 2.
        $nLength = strlen($this->value);
        if ($nLength == 1) {
            $this->data['type'] = 'mosaic';
        } elseif ($nLength == 2) {
            $this->data['type'] = 'chimeric';
        } else {
            $this->data['type'] = 'chimeric';
            $this->messages['WSOMATICFORMAT'] = 'Somatic variants are reported using one or two slashes; one slash for mosaicism, two for chimerism.';
        }
    }
}





class HGVS_DNASomaticVariant extends HGVS
{
    public array $patterns = [
        [ 'HGVS_DNASomatic', 'HGVS_DNAVariantType', [] ],
    ];
}





class HGVS_DNASub extends HGVS
{
    public array $patterns = [
        [ '>', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->setCorrectedValue($this->value);
        $this->data['type'] = $this->getCorrectedValue();
    }
}





class HGVS_DNAUnknown extends HGVS
{
    use HGVS_CheckBasesGiven; // Gives us checkBasesGiven().
    public array $patterns = [
        [ '?', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->data['type'] = $this->getCorrectedValue();
        // Check any possible Refs compared to the positions. If they match, complain about the given Refs.
        $this->checkBasesGiven();
    }
}





class HGVS_DNAVariantBody extends HGVS
{
    public array $patterns = [
        // NOTE: The allele syntax with unknown phasing ("variant(;)variant") is handled outside of these patterns.
        //       Otherwise, many patterns will need to be repeated here as we don't support optional patterns (yet).
        'null'                => [ 'HGVS_DNANull', [] ],
        'allele_trans'        => [ '[', 'HGVS_DNAAllele', '];[', 'HGVS_DNAAllele', ']', [] ],
        'allele_cis'          => [ '[', 'HGVS_DNAAllele', ']', [] ],
        'somatic'             => [ 'HGVS_DNAPositions', 'HGVS_DNAVariantType', 'HGVS_DNASomaticVariant', [] ],
        'other'               => [ 'HGVS_DNAPositions', 'HGVS_DNAVariantType', [] ],
        'unknown'             => [ 'HGVS_DNAUnknown', [] ],
        'wildtype'            => [ 'HGVS_DNAWildType', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        if ($this->matched_pattern == 'null') {
            $this->predicted = $this->DNANull->predicted;
        } else {
            $this->predicted = false;
        }

        // We need to handle alleles a bit differently to make sure we have the data set correctly.
        if ($this->matched_pattern == 'allele_trans') {
            // Overwrite the data fields with the data from the first component.
            $this->data = array_merge(
                $this->data,
                $this->DNAAllele[0]->DNAVariantBody->getData()
            );
            // But, always set the type to that of the allele syntax.
            $this->data['type'] = ';';

        } elseif ($this->matched_pattern == 'allele_cis') {
            // Overwrite the data fields with the data from the first component.
            $this->data = array_merge(
                $this->data,
                $this->DNAAllele->DNAVariantBody->getData()
            );

            // This syntax should have more than one child.
            if (count($this->DNAAllele->getComponents()) == 1) {
                // This could be an error (c.[100A>G]), but it can also happen with (;) used within square brackets.
                if (!isset($this->DNAAllele->getMessages()['WALLELEUNKNOWNPHASING'])) {
                    $this->messages['WWRONGTYPE'] = 'The allele syntax with square brackets is meant for multiple variants.';
                    $this->corrected_values = $this->DNAAllele->getCorrectedValues();
                } else {
                    // The VariantBody that found the (;) suggested changing this to ; or leaving it. The suggestion of
                    //  leaving it should have its square brackets removed, but only if we're not nested.
                    if ($this->getParent('HGVS_DNAAllele')) {
                        // We're nested. Remove the suggestion that kept the (;).
                        foreach (array_keys($this->DNAAllele->getCorrectedValues()) as $sValue) {
                            if (strpos($sValue, '(;)') !== false) {
                                unset($this->DNAAllele->corrected_values[$sValue]);
                            }
                        }

                    } else {
                        // Not nested. Update our own corrected values.
                        $aCorrectedValues = [];
                        foreach ($this->getCorrectedValues() as $sValue => $nConfidence) {
                            if (strpos($sValue, '(;)') !== false) {
                                // Remove the square brackets.
                                $aCorrectedValues[substr($sValue, 1, -1)] = $nConfidence;
                            } else {
                                // Just copy it.
                                $aCorrectedValues[$sValue] = $nConfidence;
                            }
                        }
                        $this->corrected_values = $aCorrectedValues;
                    }
                }
            } else {
                // OK, set the type to that of the allele syntax.
                $this->data['type'] = ';';
            }

        } elseif (!$this->hasProperty('DNAPositions')) {
            // No allele, but no positions, either. Store something anyway.
            $Prefix = $this->getParentProperty('DNAPrefix');
            $this->data['position_start'] = 0;
            $this->data['position_end'] = 0;
            if ($Prefix && in_array($Prefix->getCorrectedValue(), ['c', 'n'])) {
                $this->data['position_start_intron'] = 0;
                $this->data['position_end_intron'] = 0;
            }
            $this->data['range'] = false;
        }

        // Handle somatic variants here. It's way easier for us that way.
        if ($this->matched_pattern == 'somatic') {
            // Firstly, the second part should be something different.
            $PartA = $this->DNAVariantType;
            $PartB = $this->DNASomaticVariant->DNAVariantType;
            if ($PartA->getCorrectedValue() == $PartB->getCorrectedValue()) {
                // Throw a warning, and remove everything after the slash.
                $this->messages['WSOMATICEQUAL'] = 'The somatic variant contains two equal variant descriptions.';
                $this->DNASomaticVariant->setCorrectedValue('');

            } else {
                // If the second part is wild-type, it should have gone first.
                if ($PartA->getInfo()['type'] != '=' && $PartB->getInfo()['type'] == '=') {
                    // Swap out the two parts.
                    // I could simply set the corrected values, but it's better to actually swap the objects themselves.
                    $ThisPattern = &$this->patterns[$this->matched_pattern];
                    // Swap the DNAVariantType objects.
                    $ThisPattern[1] = $PartB;
                    array_splice($ThisPattern, 2, 1, [$ThisPattern[2]->DNASomatic, $PartA]);
                    // Then throw a warning.
                    $this->messages['WSOMATICFORMAT'] = 'Somatic variants should first describe the normal sequence and then the changed sequence.';
                }

                $this->data['type'] = $this->DNASomaticVariant->DNASomatic->getInfo()['type'];
            }
        }

        if ($this->matched_pattern == 'wildtype') {
            $this->messages['IALLWILDTYPE'] =
                'Using the "=" symbol without providing positions indicates that the entire reference sequence has been sequenced and found not to be changed.' .
                ' If this is not what was intended, provide the positions that were found not to be changed.';
        }

        // Special case: handling of unknown phasing of the allele syntax. This doesn't make sense with every variant.
        // E.g., a Null followed by an unknown phasing allele makes no sense because they can't be in cis.
        // There is no real guidance on this, but I'm going for a strict set of variants that can have unknown alleles.
        if (in_array($this->matched_pattern, ['allele_trans', 'allele_cis', 'other'])
            && strlen($this->suffix) > 3 && substr($this->suffix, 0, 3) == '(;)') {
            $this->data['type'] = ';';
            $this->DNAAlleleUnknownPhase = new HGVS_DNAVariantBody(substr($this->suffix, 3), $this);
            $this->messages = array_merge(
                $this->messages,
                $this->DNAAlleleUnknownPhase->getMessages()
            );
            $this->suffix = $this->DNAAlleleUnknownPhase->getSuffix();

            // Since we handled the "(;)" here, the allele object will never see it. As such, when (;) is used within
            //  square brackets, the Allele object will see only one VariantBody. We need to handle that intelligently.
            // If we're given within an Allele, complain.
            $Allele = $this->getParent('HGVS_DNAAllele');
            if ($Allele) {
                // Unknown phasing shouldn't have used square brackets.
                $this->messages['WALLELEUNKNOWNPHASING'] = 'For unknown phasing indicated with parentheses around the semicolon, like "(;)", the allele syntax does not use square brackets.';
                // There are two possible fixes. Either we meant to have unknown phasing but the square brackets
                //  were the problem, or we meant cis phasing. We are not in the position here to remove square brackets
                //  as we haven't processed them all yet. So we'll do that later.
                $this->corrected_values = $this->buildCorrectedValues(
                    $this->getCorrectedValues(),
                    [
                        '(;)' => 0.6, // We'll handle removal of the square brackets later.
                        ';' => 0.4,
                    ],
                    $this->DNAAlleleUnknownPhase->getCorrectedValues()
                );

            } else {
                // We're not within an allele, so just append the corrected values.
                $this->corrected_values = $this->buildCorrectedValues(
                    $this->getCorrectedValues(),
                    '(;)',
                    $this->DNAAlleleUnknownPhase->getCorrectedValues()
                );
            }
        }
    }
}





class HGVS_DNAVariantType extends HGVS
{
    public array $patterns = [
        'substitution'        => [ 'HGVS_DNARefs', 'HGVS_DNASub', 'HGVS_DNAAlts', [] ],
        'substitution_VCF'    => [ 'HGVS_VCFRefs', 'HGVS_DNASub', 'HGVS_VCFAlts', [] ],
        'delXins_with_suffix' => [ 'HGVS_DNADel', 'HGVS_DNADelSuffix', 'HGVS_DNAIns', 'HGVS_DNAInsSuffix', [] ],
        'delXins'             => [ 'HGVS_DNADel', 'HGVS_DNADelSuffix', 'HGVS_DNAIns', [ 'ESUFFIXMISSING' => 'The inserted sequence must be provided for deletion-insertions.' ] ],
        'delins_with_suffix'  => [ 'HGVS_DNADel', 'HGVS_DNAIns', 'HGVS_DNAInsSuffix', [] ],
        'delins'              => [ 'HGVS_DNADel', 'HGVS_DNAIns', [ 'ESUFFIXMISSING' => 'The inserted sequence must be provided for deletion-insertions.' ] ],
        'del_with_suffix'     => [ 'HGVS_DNADel', 'HGVS_DNADelSuffix', [] ],
        'del'                 => [ 'HGVS_DNADel', [] ],
        'ins_with_suffix'     => [ 'HGVS_DNAIns', 'HGVS_DNAInsSuffix', [] ],
        'ins'                 => [ 'HGVS_DNAIns', [ 'ESUFFIXMISSING' => 'The inserted sequence must be provided for insertions.' ] ],
        'dup_with_suffix'     => [ 'HGVS_DNADup', 'HGVS_DNADupSuffix', [] ],
        'dup'                 => [ 'HGVS_DNADup', [] ],
        'inv_with_suffix'     => [ 'HGVS_DNAInv', 'HGVS_DNAInvSuffix', [] ],
        'inv'                 => [ 'HGVS_DNAInv', [] ],
        'con_with_suffix'     => [ 'HGVS_DNACon', 'HGVS_DNAInsSuffix', [] ],
        'con'                 => [ 'HGVS_DNACon', [ 'ESUFFIXMISSING' => 'The inserted sequence must be provided for deletion-insertions.' ] ],
        'repeat'              => [ 'HGVS_DNARepeat', [] ],
        'pipe_with_refs'      => [ 'HGVS_DNARefs', 'HGVS_DNAPipe', 'HGVS_DNAPipeSuffix', [] ],
        'pipe'                => [ 'HGVS_DNAPipe', 'HGVS_DNAPipeSuffix', [] ],
        'unknown_with_refs'   => [ 'HGVS_DNARefs', 'HGVS_DNAUnknown', [ 'EINVALID' => 'This variant description seems incomplete.' ] ],
        'unknown'             => [ 'HGVS_DNAUnknown', [ 'EINVALID' => 'This variant description seems incomplete.' ] ],
        'wildtype_with_refs'  => [ 'HGVS_DNARefs', 'HGVS_DNAWildType', [] ],
        'wildtype'            => [ 'HGVS_DNAWildType', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $Positions = $this->getParentProperty('DNAPositions');

        // Substitutions deserve some additional attention.
        // Since this is the only class where we'll have all the data, all substitution checks need to be done here.
        if (in_array($this->matched_pattern, ['substitution', 'substitution_VCF'])) {
            if ($this->matched_pattern == 'substitution') {
                $sREF = $this->DNARefs->getCorrectedValue();
                $sALT = $this->DNAAlts->getCorrectedValue();
                if ($sREF == $sALT) {
                    $this->messages['WWRONGTYPE'] =
                        'A substitution should be a change of one base to one base. Did you mean to describe an unchanged ' .
                        ($Positions->range? 'range' : 'position') . '?';
                } elseif (strlen($sREF) > 1 || strlen($sALT) > 1) {
                    $this->messages['WWRONGTYPE'] =
                        'A substitution should be a change of one base to one base. Did you mean to describe a deletion-insertion?';
                }

            } else {
                // Either the REF or the ALT is a period.
                $sREF = $this->VCFRefs->getCorrectedValue();
                $sALT = $this->VCFAlts->getCorrectedValue();
                if ($sREF == '.' && $sALT == '.') {
                    $this->messages['EWRONGTYPE'] =
                        'This substitution does not seem to contain any data. Please provide bases that were replaced.';
                } elseif ($sREF == '.') {
                    $this->messages['WWRONGTYPE'] =
                        'A substitution should be a change of one base to one base. Did you mean to describe an insertion?';
                } elseif ($sALT == '.') {
                    $this->messages['WWRONGTYPE'] =
                        'A substitution should be a change of one base to one base. Did you mean to describe a deletion?';
                }
                // An else should not be possible.
            }

            // Positions for substitutions should, of course, normally just be single positions,
            //  but uncertain ranges are also possible. Certain ranges are normally not allowed, but we'll throw a
            //  warning only when the REF length matches the positions length.
            // Don't check anything about the REF length when there are problems with the positions.
            if (isset($this->messages['EPOSITIONFORMAT'])) {
                $this->messages['ISUBNOTVALIDATED'] = "Due to the invalid variant position, the substitution syntax couldn't be fully validated.";

            } elseif ($Positions->range) {
                // Check the position/REF lengths, but only if we have a range.
                // When REF is as long as the position length, throw a WTOOMANYPOSITIONS.
                // Then, also the positions should not be uncertain, if they are.
                // Furthermore, the REF can never be shorter than the minimum length given by the positions,
                //  and the REF can never be bigger than the maximum length given by the positions.
                list($nMinLengthVariant, $nMaxLengthVariant) = $Positions->getLengths();
                $bPositionLengthIsCertain = ($nMinLengthVariant == $nMaxLengthVariant);
                $nREFLength = strlen(trim($sREF, '.'));

                // Simplest situation first: a certain range. The REF needs to match perfectly for a warning.
                // Otherwise, throw an error.
                if ($bPositionLengthIsCertain) {
                    if ($nMinLengthVariant == $nREFLength) {
                        $this->messages['WTOOMANYPOSITIONS'] =
                            'Too many positions are given; a substitution is used to only indicate single-base changes and therefore should have only one position.';
                    } else {
                        // We can't fix this.
                        $this->messages['ETOOMANYPOSITIONS'] =
                            'Too many positions are given; a substitution is used to only indicate single-base changes and therefore should have only one position.';
                    }

                } elseif ($nREFLength == $nMaxLengthVariant) {
                    // When the positions are uncertain but the REF length fits the maximum length precisely,
                    //  we'll just throw a WTOOMANYPOSITIONS, and try to make the positions certain.
                    $this->messages['WTOOMANYPOSITIONS'] =
                        'Too many positions are given; a substitution is used to only indicate single-base changes and therefore should have only one position.';
                    $Positions->makeCertain();
                }
                // In all other cases, we'll allow substitutions on uncertain ranges.
            }

            // Calculate the corrected value, based on a VCF parser.
            // Based on the REF and ALT info, we may need to shift the variant or change it to a different type.
            if (!$Positions->unknown && !$Positions->uncertain
                && (isset($this->messages['WWRONGTYPE']) || isset($this->messages['WTOOMANYPOSITIONS']) || isset($Positions->messages['WTOOMANYPARENS']))
                && !array_filter(
                    array_keys($this->messages),
                    function ($sKey)
                    {
                        return ($sKey[0] == 'E' && $sKey != 'EINVALIDNUCLEOTIDES');
                    })) {
                // Calculate the corrected value. Toss it all in a VCF parser.
                $this->VCF = new HGVS_VCFBody(
                    ($Positions->DNAPosition ?? $Positions->DNAPositionStart)->getCorrectedValue() .
                    ':' . $sREF . ':' . $sALT,
                    $this
                );
                // Lower the confidence of our prediction when the position was single but the REF was not.
                // (e.g., c.100AAA>G).
                $nCorrectionConfidence = (!$Positions->range && strlen($sREF) > 1? 0.6 : 1);
                $this->parent->corrected_values = $this->buildCorrectedValues(
                    ['' => $nCorrectionConfidence],
                    $this->VCF->getCorrectedValues()
                );
                // Another check: for variants like c.(100)A>G, we're not sure whether we mean c.100A>G or perhaps c.(100A>G).
                if (isset($Positions->messages['WTOOMANYPARENS']) && !$Positions->range
                    && (!$this->getParent('HGVS_Variant') || $this->getParent('HGVS_Variant')->current_pattern != 'DNA_predicted')) {
                    // Reduce the current prediction(s) with 50%.
                    $this->parent->corrected_values = $this->buildCorrectedValues(
                        ['' => 0.5],
                        $this->parent->getCorrectedValues()
                    );
                    // Then add the c.(100A>G) suggestion. It's easier to build it manually.
                    foreach ($this->buildCorrectedValues(
                        ['' => $nCorrectionConfidence * 0.5],
                        '(',
                        $this->VCF->getCorrectedValues(),
                        ')'
                    ) as $sCorrectedValue => $nConfidence) {
                        $this->parent->addCorrectedValue($sCorrectedValue, $nConfidence);
                    }
                }
            }
        }

        // Delins variants with a REF deserve some additional attention, too.
        // Based on the REF and ALT info, we may need to shift the variant or change it to a different type.
        if ($this->matched_pattern == 'delXins_with_suffix'
            && !$Positions->unknown && !$Positions->uncertain
            && count(array_unique($this->DNADelSuffix->getLengths())) == 1
            && count(array_unique($this->DNAInsSuffix->getLengths())) == 1
            && !array_filter(
                array_keys($this->messages),
                function ($sKey)
                {
                    return ($sKey[0] == 'E' && $sKey != 'EINVALIDNUCLEOTIDES');
                })) {
            // Positions are known; REF and ALT are known. Toss it all in a VCF parser.
            $this->VCF = new HGVS_VCFBody(
                ($Positions->DNAPosition ?? $Positions->DNAPositionStart)->getCorrectedValue() . ':' .
                $this->DNADelSuffix->getSequence() . ':' .
                $this->DNAInsSuffix->getSequence(),
                $this
            );
            $this->parent->corrected_values = $this->VCF->getCorrectedValues();

            // The VCF object stores the new variant type, so we can easily see if it's changed.
            // If we still have a delins, we may not have changed anything at all, or we still could have made a shift.
            // E.g., c.100_101delAAinsATT is still a delins, but should still throw an additional warning.
            $sNewType = $this->VCF->getInfo()['type'];
            if ($sNewType == 'delins') {
                // Still a delins. Did it get updated to a different description?
                if ($this->VCF->REF != $this->DNADelSuffix->getSequence()) {
                    // Remove the WSUFFIXGIVEN that complained about about the bases following "del".
                    unset($this->messages['WSUFFIXGIVEN']);
                    // Then throw a proper warning. The positions MUST have been changed, as the REF got changed.
                    $this->messages['WPOSITIONSCORRECTED'] = "The variant's positions have been corrected.";
                }

            } else {
                // This delins is no longer a delins. We'll throw a WWRONGTYPE here.
                // Remove the WSUFFIXGIVEN that complained about about the bases following "del".
                unset($this->messages['WSUFFIXGIVEN']);
                if ($sNewType == '=') {
                    $this->messages['WWRONGTYPE'] = "This deletion-insertion doesn't change the given sequence.";
                } else {
                    $this->messages['WWRONGTYPE'] = 'Based on the given sequences, this deletion-insertion should be described as ' .
                        ($sNewType == '>'? 'a substitution.' :
                            ($sNewType == 'del'? 'a deletion.' :
                                ($sNewType == 'dup'? 'a duplication.' :
                                    ($sNewType == 'ins'? 'an insertion.' : 'an inversion.'))));
                }
            }
        }
    }
}





class HGVS_DNAWildType extends HGVS
{
    use HGVS_CheckBasesGiven; // Gives us checkBasesGiven().
    public array $patterns = [
        [ '=', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->data['type'] = $this->getCorrectedValue();
        // Check any possible Refs compared to the positions. If they match, complain about the given Refs.
        $this->checkBasesGiven();
    }
}





class HGVS_Dot extends HGVS
{
    public array $patterns = [
        'something' => [ '/[:.,]+/', [] ],
        'nothing'   => [ '/(?=[(A-Z0-9*-])/', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->setCorrectedValue('.');
        if ($this->value != $this->getCorrectedValue()) {
            $Prefix = ($this->getParentProperty('DNAPrefix') ?: ($this->getParentProperty('RNAPrefix') ?: $this->getParentProperty('ProteinPrefix')));
            $sPrefix = ($Prefix? $Prefix->getCorrectedValue() : 'g');
            $this->messages['WPREFIXFORMAT'] = 'Molecule types in variant descriptions should be followed by a period (e.g., "' . $sPrefix . '.").';
        }
    }
}





class HGVS_Genome extends HGVS
{
    public array $patterns = [
        'ucsc' => [ '/hg(18|19|38)/', [] ],
        'ncbi' => [ '/GRCh3(6|7|8)/', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        if ($this->matched_pattern == 'ucsc') {
            $this->setCorrectedValue(strtolower($this->value));
        } else {
            $sUCSC = [
                'grch36' => 'hg18',
                'grch37' => 'hg19',
                'grch38' => 'hg38',
            ][strtolower($this->value)];
            $this->setCorrectedValue($sUCSC);
        }
    }
}





class HGVS_Length extends HGVS
{
    public array $patterns = [
        'unknown' => [ '?', [] ],
        'known'   => [ '/([0-9]+)/', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->unknown = ($this->matched_pattern == 'unknown');
        $nCorrectionConfidence = 1;

        if ($this->matched_pattern == 'unknown') {
            $this->length = $this->value;

        } else {
            $this->length = (int) $this->value;

            // Check for values with zeros.
            if (!$this->length) {
                $this->messages['ELENGTHFORMAT'] = 'This variant description contains an invalid sequence length: "' . $this->value . '".';
            } elseif ((string) $this->length !== $this->regex[1]) {
                $this->messages['WLENGTHWITHZERO'] = 'Sequence lengths should not be prefixed by a 0.';
                $nCorrectionConfidence *= 0.9;
            }
        }

        // Store the corrected value.
        $this->corrected_values = $this->buildCorrectedValues(
            ['' => $nCorrectionConfidence],
            $this->length
        );
    }
}





class HGVS_Lengths extends HGVS
{
    public array $patterns = [
        'range'              => [ 'HGVS_Length', '_', 'HGVS_Length', [] ],
        'range_with_parens'  => [ '(', 'HGVS_Length', '_', 'HGVS_Length', ')', [] ],
        'single'             => [ 'HGVS_Length', [] ],
        'single_with_parens' => [ '(', 'HGVS_Length', ')', [ 'WTOOMANYPARENS' => 'This variant description contains a sequence length with redundant parentheses.' ] ],
    ];
    public array $lengths = [];

    public function getLengths ()
    {
        return ($this->lengths ?? [0,0]);
    }





    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->range = (substr($this->matched_pattern, 0, 5) == 'range');
        $nCorrectionConfidence = 1;

        if ($this->matched_pattern == 'range') {
            $this->messages['WLENGTHFORMAT'] = 'This variant description contains an invalid sequence length: "' . $this->value . '".' .
                ' When the sequence length is uncertain, add parentheses.';
        }

        if (!$this->range) {
            // A single length, just copy everything.
            $this->unknown = $this->Length->unknown;
            $this->lengths = [$this->Length->length, $this->Length->length];

        } else {
            $this->unknown = ($this->Length[0]->unknown || $this->Length[1]->unknown);
            $this->lengths = [$this->Length[0]->length, $this->Length[1]->length];

            // If the lengths are the same, warn and remove one.
            if ($this->Length[0]->length == $this->Length[1]->length) {
                $this->messages['WSAMELENGTHS'] = 'This variant description contains two sequence lengths that are the same.';
                $nCorrectionConfidence *= 0.9;
                // Discard the other object.
                $this->Length = $this->Length[0];
                $this->range = false;

            } elseif (!$this->unknown && $this->Length[0]->length > $this->Length[1]->length) {
                // Lengths aren't given in the right order.
                $this->messages['WLENGTHORDER'] = 'This variant description contains two sequence lengths that are not given in the correct order.';
                $nCorrectionConfidence *= 0.9;
                // Swap the lengths.
                list($this->Length[0], $this->Length[1]) = [$this->Length[1], $this->Length[0]];
                list($this->lengths[0], $this->lengths[1]) = [$this->lengths[1], $this->lengths[0]];
            }
        }

        // Store the corrected value.
        if ($this->range) {
            $this->corrected_values = $this->buildCorrectedValues(
                ['' => $nCorrectionConfidence],
                '(', $this->Length[0]->getCorrectedValues(), '_', $this->Length[1]->getCorrectedValues(), ')'
            );
        } elseif ($this->lengths[0] == 1 && !$this->getParent('HGVS_DNARepeatComponent')) {
            // Actually, when the length is 1, and we're not a repeat, it's redundant and it shouldn't be given.
            $this->setCorrectedValue('');
        } else {
            $this->corrected_values = $this->buildCorrectedValues(
                ['' => $nCorrectionConfidence],
                $this->Length->getCorrectedValues()
            );
        }

        // Handle unknown lengths.
        if ($this->lengths[0] == '?') {
            // It's considered to be seen at least once, if it's provided.
            $this->lengths[0] = 1;
        }
        if ($this->lengths[1] == '?') {
            // Picking a random large number gives very awkward results downstream.
            // Instead, try to measure the variant length based on the positions.
            $Positions = $this->getParentProperty('DNAPositions');
            if ($Positions) {
                $nMaxLength = $Positions->getLengths()[1];
                $this->lengths[1] = $nMaxLength;
            } else {
                // Default to the minimum size, if we can't find positions.
                $this->lengths[1] = $this->lengths[0];
            }
        }
    }
}





class HGVS_ReferenceSequence extends HGVS
{
    public array $patterns = [
        'refseq_genomic_coding'       => [ '/(N[CG])([_-])?([0-9]+)(\.[0-9]+)?\(([NX]M)([_-]?)([0-9]+)(\.[0-9]+)?\)/', [] ],
        'refseq_genomic_non-coding'   => [ '/(N[CG])([_-])?([0-9]+)(\.[0-9]+)?\(([NX]R)([_-]?)([0-9]+)(\.[0-9]+)?\)/', [] ],
        'refseq_genomic'              => [ '/(N[CG])([_-])?([0-9]+)(\.[0-9]+)?/', [] ],
        'refseq_coding_genomic'       => [ '/([NX]M)([_-]?)([0-9]+)(\.[0-9]+)?\((N[CG])([_-])?([0-9]+)(\.[0-9]+)?\)/', [] ],
        'refseq_coding_with_gene'     => [ '/([NX]M)([_-]?)([0-9]+)(\.[0-9]+)?\(([A-Z][A-Za-z0-9#@-]*(_v[0-9]+)?)\)/', [] ],
        'refseq_coding'               => [ '/([NX]M)([_-]?)([0-9]+)(\.[0-9]+)?/', [] ],
        'refseq_non-coding_genomic'   => [ '/([NX]R)([_-]?)([0-9]+)(\.[0-9]+)?\((N[CG])([_-])?([0-9]+)(\.[0-9]+)?\)/', [] ],
        'refseq_non-coding_with_gene' => [ '/([NX]R)([_-]?)([0-9]+)(\.[0-9]+)?\(([A-Z][A-Za-z0-9#@-]*(_v[0-9]+)?)\)/', [] ],
        'refseq_non-coding'           => [ '/([NX]R)([_-]?)([0-9]+)(\.[0-9]+)?/', [] ],
        'refseq_gene_with_coding'     => [ '/(?:[A-Z][A-Za-z0-9#@-]*)\(([NX]M)([_-]?)([0-9]+)(\.[0-9]+)?\)/', [] ],
        'refseq_gene_with_non-coding' => [ '/(?:[A-Z][A-Za-z0-9#@-]*)\(([NX]R)([_-]?)([0-9]+)(\.[0-9]+)?\)/', [] ],
        'refseq_protein'              => [ '/([NX]P)([_-]?)([0-9]+)(\.[0-9]+)?/', [] ],
        'refseq_other'                => [ '/^(N[TW]_([0-9]{6})|[A-Z][0-9]{5}|[A-Z]{2}[0-9]{6})(\.[0-9]+)/', [] ],
        'ensembl_genomic'             => [ '/(ENSG)([_-])?([0-9]+)(\.[0-9]+)?/', [] ],
        'ensembl_transcript'          => [ '/(ENST)([_-])?([0-9]+)(\.[0-9]+)?/', [] ],
        'LRG_transcript'              => [ '/(LRG)([_-]?)([0-9]+)(t)([0-9]+)/', [] ],
        'LRG_genomic'                 => [ '/(LRG)([_-]?)([0-9]+)/', [] ],
        // Because I do actually want to match something so we can validate the variant itself, match anything.
        'other'                       => [ '/[^:\[\]]{2,}(?=:)/', ['EREFERENCEFORMAT' => 'The reference sequence could not be recognised. Supported reference sequence IDs are from NCBI Refseq, Ensembl, and LRG.'] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        switch ($this->matched_pattern) {
            case 'refseq_genomic_coding':
            case 'refseq_genomic_non-coding':
            case 'refseq_coding_genomic':
            case 'refseq_non-coding_genomic':
                $this->molecule_type = 'genome_transcript';
                $this->allowed_prefixes = [(strpos($this->matched_pattern, 'non-coding') !== false? 'n' : 'c')];
                // If the transcript and the genomic refseq are switched, fix all of that and log it.
                if (substr($this->matched_pattern, -7) == 'genomic') {
                    $this->messages['WREFERENCEFORMAT'] =
                        'The genomic and transcript reference sequence IDs have been swapped.';
                    // Now, swap the regexes so the reconstruction will work well.
                    $this->regex = [
                        $this->regex[0],
                        $this->regex[5],
                        $this->regex[6],
                        $this->regex[7],
                        ($this->regex[8] ?? NULL),
                        $this->regex[1],
                        $this->regex[2],
                        $this->regex[3],
                        ($this->regex[4] ?? NULL),
                    ];
                }

                $this->setCorrectedValue(
                    strtoupper($this->regex[1]) .
                    '_' .
                    str_pad((int) $this->regex[3], 6, '0', STR_PAD_LEFT) .
                    (!isset($this->regex[4])? '' : '.' . (int) substr($this->regex[4], 1)) .
                    '(' .
                    strtoupper($this->regex[5]) .
                    '_' .
                    str_pad((int) $this->regex[7], (strlen((int) $this->regex[7]) > 6? 9 : 6), '0', STR_PAD_LEFT) .
                    (!isset($this->regex[8])? '' : '.' . (int) substr($this->regex[8], 1)) .
                    ')'
                );
                $this->caseOK = ($this->value == strtoupper($this->value));

                if (($this->regex[2] ?? '') != '_' || ($this->regex[6] ?? '') != '_') {
                    $this->messages['WREFERENCEFORMAT'] =
                        'NCBI reference sequence IDs require an underscore between the prefix and the numeric ID.';
                } elseif (strlen((int) $this->regex[3]) > 6) {
                    $this->messages['EREFERENCEFORMAT'] =
                        'NCBI genomic reference sequence IDs consist of six digits.';
                } elseif (strlen($this->regex[3]) != 6) {
                    $this->messages['WREFERENCEFORMAT'] =
                        'NCBI genomic reference sequence IDs consist of six digits.';
                } elseif (strlen((int) $this->regex[7]) > 9) {
                    $this->messages['EREFERENCEFORMAT'] =
                        'NCBI transcript reference sequence IDs consist of six or nine digits.';
                } elseif (!in_array(strlen($this->regex[7]), [6, 9])) {
                    $this->messages['WREFERENCEFORMAT'] =
                        'NCBI transcript reference sequence IDs consist of six or nine digits.';
                } elseif (empty($this->regex[4]) || empty($this->regex[8])) {
                    $this->messages['EREFERENCEFORMAT'] =
                        'The reference sequence ID is missing the required version number.' .
                        ' NCBI RefSeq and Ensembl IDs require version numbers when used in variant descriptions.';
                }
                break;

            case 'refseq_genomic':
                $this->molecule_type = (strtoupper($this->regex[1]) == 'NC'? 'chromosome' : 'genome');
                $this->allowed_prefixes = [(strtoupper($this->regex[1]) == 'NC' && in_array((int) $this->regex[3], ['1807', '12920'])? 'm' : 'g')];
                $this->setCorrectedValue(
                    strtoupper($this->regex[1]) .
                    '_' .
                    str_pad((int) $this->regex[3], 6, '0', STR_PAD_LEFT) .
                    (!isset($this->regex[4])? '' : '.' . (int) substr($this->regex[4], 1))
                );
                $this->caseOK = ($this->value == strtoupper($this->value));

                if (($this->regex[2] ?? '') != '_') {
                    $this->messages['WREFERENCEFORMAT'] =
                        'NCBI reference sequence IDs require an underscore between the prefix and the numeric ID.';
                } elseif (strlen((int) $this->regex[3]) > 6) {
                    $this->messages['EREFERENCEFORMAT'] =
                        'NCBI genomic reference sequence IDs consist of six digits.';
                } elseif (strlen($this->regex[3]) != 6) {
                    $this->messages['WREFERENCEFORMAT'] =
                        'NCBI genomic reference sequence IDs consist of six digits.';
                } elseif (empty($this->regex[4])) {
                    $this->messages['EREFERENCEFORMAT'] =
                        'The reference sequence ID is missing the required version number.' .
                        ' NCBI RefSeq and Ensembl IDs require version numbers when used in variant descriptions.';
                }
                break;

            case 'refseq_coding_with_gene':
            case 'refseq_coding':
            case 'refseq_non-coding_with_gene':
            case 'refseq_non-coding':
            case 'refseq_gene_with_coding':
            case 'refseq_gene_with_non-coding':
            case 'refseq_protein':
                $this->molecule_type = ($this->matched_pattern == 'refseq_protein'? 'protein' : 'transcript');
                $this->allowed_prefixes = [(strpos($this->matched_pattern, 'non-coding') !== false? 'n' : ($this->matched_pattern == 'refseq_protein'? 'p' : 'c'))];
                $this->setCorrectedValue(
                    strtoupper($this->regex[1]) .
                    '_' .
                    str_pad((int) $this->regex[3], (strlen((int) $this->regex[3]) > 6? 9 : 6), '0', STR_PAD_LEFT) .
                    (!isset($this->regex[4])? '' : '.' . (int) substr($this->regex[4], 1))
                );
                $this->caseOK = ($this->regex[1] == strtoupper($this->regex[1]));

                if (($this->regex[2] ?? '') != '_') {
                    $this->messages['WREFERENCEFORMAT'] =
                        'NCBI reference sequence IDs require an underscore between the prefix and the numeric ID.';
                } elseif (strlen((int) $this->regex[3]) > 9) {
                    $this->messages['EREFERENCEFORMAT'] =
                        'NCBI ' . $this->molecule_type . ' reference sequence IDs consist of six or nine digits.';
                } elseif (!in_array(strlen($this->regex[3]), [6, 9])) {
                    $this->messages['WREFERENCEFORMAT'] =
                        'NCBI ' . $this->molecule_type . ' reference sequence IDs consist of six or nine digits.';
                } elseif (empty($this->regex[4])) {
                    $this->messages['EREFERENCEFORMAT'] =
                        'The reference sequence ID is missing the required version number.' .
                        ' NCBI RefSeq and Ensembl IDs require version numbers when used in variant descriptions.';
                } elseif (!in_array($this->matched_pattern, ['refseq_coding', 'refseq_non-coding', 'refseq_protein'])) {
                    $this->messages['WREFERENCEFORMAT'] =
                        'The reference sequence ID should not include a gene symbol.';
                }
                break;

            case 'refseq_other':
                $this->molecule_type = 'genome';
                $this->allowed_prefixes = ['g', 'o'];
                // We won't attempt to fix things. We don't actually know if anything like this is valid.
                $this->setCorrectedValue(strtoupper($this->regex[0]));
                $this->caseOK = ($this->regex[1] == strtoupper($this->regex[1]));

                // This isn't really a warning, as in, we can't fix it.
                // But I don't want to throw an error, either. It could still be valid HGVS nomenclature.
                $this->messages['WREFERENCENOTSUPPORTED'] =
                    'Currently, variant descriptions using "' . $this->value . '" are not yet supported.' .
                    ' This does not necessarily mean the description is not valid HGVS.' .
                    ' Supported reference sequence IDs are from NCBI Refseq, Ensembl, and LRG.';
                break;

            case 'ensembl_genomic':
            case 'ensembl_transcript':
                if ($this->matched_pattern == 'ensembl_genomic') {
                    $this->molecule_type = 'genome';
                    $this->allowed_prefixes = ['g', 'm', 'o'];
                } else {
                    $this->molecule_type = 'transcript';
                    $this->allowed_prefixes = ['c', 'n'];
                }
                $this->setCorrectedValue(
                    strtoupper($this->regex[1]) .
                    str_pad((int) $this->regex[3], 11, '0', STR_PAD_LEFT) .
                    (!isset($this->regex[4])? '' : '.' . (int) substr($this->regex[4], 1))
                );
                $this->caseOK = ($this->value == strtoupper($this->value));

                if (!empty($this->regex[2])) {
                    $this->messages['WREFERENCEFORMAT'] =
                        'Ensembl reference sequence IDs don\'t allow a divider between the prefix and the numeric ID.';
                } elseif (strlen((int) $this->regex[3]) > 11) {
                    $this->messages['EREFERENCEFORMAT'] =
                        'Ensembl reference sequence IDs require 11 digits.';
                } elseif (strlen($this->regex[3]) != 11) {
                    $this->messages['WREFERENCEFORMAT'] =
                        'Ensembl reference sequence IDs require 11 digits.';
                } elseif (empty($this->regex[4])) {
                    $this->messages['EREFERENCEFORMAT'] =
                        'The reference sequence ID is missing the required version number.' .
                        ' NCBI RefSeq and Ensembl IDs require version numbers when used in variant descriptions.';
                }
                break;

            case 'LRG_transcript':
                $this->molecule_type = 'genome_transcript';
                $this->allowed_prefixes = ['c', 'n'];
                $this->setCorrectedValue(
                    strtoupper($this->regex[1]) .
                    '_' .
                    (int) $this->regex[3] .
                    strtolower($this->regex[4]) .
                    (int) $this->regex[5]
                );
                $this->caseOK = ($this->regex[1] == strtoupper($this->regex[1])
                    && $this->regex[4] == strtolower($this->regex[4]));

                if (($this->regex[2] ?? '') != '_') {
                    $this->messages['WREFERENCEFORMAT'] =
                        'LRG reference sequence IDs require an underscore between the prefix and the numeric ID.';
                }
                break;

            case 'LRG_genomic':
                $this->molecule_type = 'genome';
                $this->allowed_prefixes = ['g'];
                $this->setCorrectedValue(
                    strtoupper($this->regex[1]) .
                    '_' .
                    (int) $this->regex[3]
                );
                $this->caseOK = ($this->regex[1] == strtoupper($this->regex[1]));

                if (($this->regex[2] ?? '') != '_') {
                    $this->messages['WREFERENCEFORMAT'] =
                        'LRG reference sequence IDs require an underscore between the prefix and the numeric ID.';
                }
                break;

            case 'other':
                $this->molecule_type = 'unknown';
                $this->allowed_prefixes = [];

                // Some black listing is needed, though.
                if (in_array(strtolower($this->value), ['http', 'https'])) {
                    $this->matched = false;
                    return;
                }

                break;
        }
    }
}





class HGVS_RNAAlts extends HGVS_DNAAlts
{
    public array $patterns = [
        'invalid' => [ '/[A-Z]+/', [] ],
        'valid'   => [ '/[ACGUMRWSYKVHDBN]+/', [] ],
    ];
}





class HGVS_RNAPrefix extends HGVS
{
    public array $patterns = [
        'RNA'     => [ '/r/', [] ],
        'nothing' => [ 'HGVS_Dot', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->molecule_type = 'transcript';
        $this->setCorrectedValue(strtolower($this->value));
        $this->caseOK = ($this->value == $this->getCorrectedValue());

        if ($this->matched_pattern == 'nothing') {
            $this->setCorrectedValue('r');
            $this->suffix = $this->input; // Reset the suffix in case HGVS_Dot took something.
            $this->messages['WPREFIXMISSING'] = 'This variant description seems incomplete. Variant descriptions should start with a molecule type (e.g., "' . $this->getCorrectedValue() . '.").';
        }

        // If we have seen a reference sequence, check if we match that.
        $RefSeq = $this->getParentProperty('ReferenceSequence');
        if ($RefSeq && $RefSeq->molecule_type != $this->molecule_type && $RefSeq->molecule_type != 'genome_transcript') {
            $this->messages['EWRONGREFERENCE'] =
                'The given reference sequence (' . $RefSeq->getCorrectedValue() . ') does not match the RNA type (' . $this->getCorrectedValue() . ').' .
                ' For ' . $this->getCorrectedValue() . '. variants, please use a ' . $this->molecule_type . ' reference sequence.';
        }
    }
}





class HGVS_RNARefs extends HGVS_DNARefs
{
    public array $patterns = [
        'invalid' => [ '/[A-Z]+/', [] ],
        'valid'   => [ '/[ACGUN]+/', [] ],
    ];
}





class HGVS_ProteinPosition extends HGVS
{
    public array $patterns = [
        // NOTE: The HGVS nomenclature doesn't state that unknown protein positions can't be used in a description.
        //       However, the nomenclature doesn't explain how to use it, and therefore, we will not define it.
        [ 'HGVS_ProteinRef', 'HGVS_ProteinPositionPosition', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        foreach (['position', 'position_sortable', 'position_limits'] as $variable) {
            $this->$variable = $this->ProteinPositionPosition->$variable;
        }
    }
}





class HGVS_ProteinPositionPosition extends HGVS
{
    public array $patterns = [
        [ '/([0-9]+)/', [] ],
    ];
    public array $position_limits = [
        'p' => [1, 65535], // position min, position max.
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $VariantPrefix = $this->getParentProperty('ProteinPrefix');
        $sVariantPrefix = ($VariantPrefix? $VariantPrefix->getCorrectedValue() : 'p');
        $this->position_limits = $this->position_limits[$sVariantPrefix];
        $nCorrectionConfidence = 1;

        // Store the position and sortable position separately.
        $this->position = (int) $this->regex[1];
        $this->position_sortable = $this->position;

        // Check for values with zeros.
        if (!$this->position) {
            $this->messages['EPOSITIONFORMAT'] = 'This variant description contains an invalid position: "' . $this->value . '".';
        } elseif ((string) $this->position !== $this->regex[1]) {
            $this->messages['WPOSITIONWITHZERO'] = 'Variant positions should not be prefixed by a 0.';
            $nCorrectionConfidence *= 0.9;
        }

        // Check minimum and maximum values.
        if ($this->position_sortable > $this->position_limits[1]) {
            $this->messages['EPOSITIONLIMIT'] = 'Position is beyond the possible limits of its type: "' . $this->value . '".';
        }

        // Adjust minimum and maximum values, to be used in further processing, but keep within limits.
        if ($this->position_sortable < $this->position_limits[0]) {
            $this->position_limits[1] = $this->position_limits[0];
        } elseif ($this->position_sortable > $this->position_limits[1]) {
            $this->position_limits[0] = $this->position_limits[1];
        } else {
            $this->position_limits[0] = $this->position_sortable;
            $this->position_limits[1] = $this->position_sortable;
        }

        // Store the corrected value.
        $this->corrected_values = $this->buildCorrectedValues(
            ['' => $nCorrectionConfidence],
            $this->position
        );
    }
}





class HGVS_ProteinPrefix extends HGVS
{
    public array $patterns = [
        'protein' => [ '/p/', [] ],
        'nothing' => [ 'HGVS_Dot', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->molecule_type = 'protein';
        $this->setCorrectedValue(strtolower($this->value));
        $this->caseOK = ($this->value == $this->getCorrectedValue());

        if ($this->matched_pattern == 'nothing') {
            $this->setCorrectedValue('p');
            $this->suffix = $this->input; // Reset the suffix in case HGVS_Dot took something.
            $this->messages['WPREFIXMISSING'] = 'This variant description seems incomplete. Variant descriptions should start with a molecule type (e.g., "' . $this->getCorrectedValue() . '.").';
        }

        // If we have seen a reference sequence, check if we match that.
        $RefSeq = $this->getParentProperty('ReferenceSequence');
        if ($RefSeq && $RefSeq->molecule_type != $this->molecule_type) {
            $this->messages['EWRONGREFERENCE'] =
                'The given reference sequence (' . $RefSeq->getCorrectedValue() . ') does not match the protein type (' . $this->getCorrectedValue() . ').' .
                ' For ' . $this->getCorrectedValue() . '. variants, please use a ' . $this->molecule_type . ' reference sequence.';
        }
    }
}





class HGVS_ProteinRef extends HGVS
{
    public array $patterns = [
        'valid_long'   => [ '/(Ala|Cys|Asp|Glu|Phe|Gly|His|Ile|Lys|Leu|Met|Asn|Pro|Gln|Arg|Ser|Thr|Sec|Val|Trp|Xaa|Tyr|Ter)/', [] ],
        'invalid_long' => [ '/[A-Z][a-z]{2}/', [] ],
        'valid_short'  => [ '/[AC-IK-NP-Y*]/', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        if ($this->matched_pattern == 'valid_short') {
            $this->setCorrectedValue(strtoupper($this->value));
        } else {
            $this->setCorrectedValue(strtoupper($this->value[0]) . strtolower(substr($this->value, 1)));
        }
        $this->caseOK = ($this->value == $this->getCorrectedValue());

        if ($this->matched_pattern == 'invalid_long') {
            // Check if we can predict which one they meant.
            $aValidCodes = array_flip(explode('|', substr($this->patterns['valid_long'][0], 2, -2)));
            foreach (array_keys($aValidCodes) as $sCode) {
                $aValidCodes[$sCode] = similar_text($this->getCorrectedValue(), $sCode);
            }
            // Filter the array; only values of 2 will be accepted (3 is full overlap).
            $aSuggestions = array_filter($aValidCodes, function ($nSimilarity) { return ($nSimilarity >= 2); });
            $nSuggestions = count($aSuggestions);

            // If we end up with something that at least had two characters overlapping, suggest those.
            if ($nSuggestions) {
                // Set the confidence to a proper percentage.
                $aSuggestions = array_combine(
                    array_keys($aSuggestions),
                    array_fill(0, $nSuggestions, (1/$nSuggestions))
                );

                ksort($aSuggestions);
                $this->corrected_values = $this->buildCorrectedValues($aSuggestions);
                $this->messages['WINVALIDAMINOACIDS'] = 'This variant description contains invalid amino acids: "' . $this->value . '".';
            } else {
                $this->messages['EINVALIDAMINOACIDS'] = 'This variant description contains invalid amino acids: "' . $this->value . '".';
            }
        }
    }
}





class HGVS_Variant extends HGVS
{
    public array $patterns = [
        'DNA_predicted' => [ 'HGVS_DNAPrefix', 'HGVS_Dot', '(', 'HGVS_DNAVariantBody', ')', [] ],
        'DNA'           => [ 'HGVS_DNAPrefix', 'HGVS_Dot', 'HGVS_DNAVariantBody', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->predicted = (substr($this->matched_pattern, -9) == 'predicted'
            || !empty($this->DNAVariantBody->predicted)); // NOTE: This is due to c.0? being predicted.

        // Clean up the messages a bit.
        if (isset($this->messages['EPREFIXMISSING']) || isset($this->messages['WPREFIXMISSING'])) {
            unset($this->messages['WPREFIXFORMAT']);
        }

        // Some variant types aren't supported for validation and mapping.
        // But I want the message to say whether it was a valid HGVS description,
        //  so I need to do that here, once I know whether it was correct or not.
        if ($this->predicted
            || (isset($this->DNAVariantBody->DNAPositions)
                && ($this->DNAVariantBody->DNAPositions->uncertain || $this->DNAVariantBody->DNAPositions->unknown || $this->DNAVariantBody->DNAPositions->ISCN))
            || in_array($this->data['type'] ?? '', ['0', '?', ';', 'met', 'repeat'])
            || $this->DNAVariantBody->getCorrectedValue() == '=') {
            if ($this->caseOK
                && !array_filter(array_keys($this->messages), function ($sKey) { return in_array($sKey[0], ['E','W']); })) {
                $this->messages['WNOTSUPPORTED'] = 'Although this variant is a valid HGVS description, this syntax is currently not supported for mapping and validation.';
            } else {
                $this->messages['WNOTSUPPORTED'] = 'This syntax is currently not supported for mapping and validation.';
            }
            if (($this->data['type'] ?? '') == ';') {
                $this->messages['WNOTSUPPORTED'] .= ' Please submit your variants separately.';
            }
        }
    }
}





class HGVS_VCF extends HGVS
{
    public array $patterns = [
        'with_build' => [ 'HGVS_Genome', 'HGVS_VCFSeparator', 'HGVS_Chromosome', 'HGVS_VCFSeparator', 'HGVS_VCFBody', [] ],
        'with_chr'   => [ 'HGVS_Chromosome', 'HGVS_VCFSeparator', 'HGVS_VCFBody', ['WREFSEQMISSING' => 'This VCF variant is missing a genome build, which is required to determine the reference sequence used.'] ],
        'basic'      => [ 'HGVS_VCFBody', ['EREFSEQMISSING' => 'This VCF variant is missing a genome build and chromosome, which is required to determine the reference sequence used.'] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        if ($this->matched_pattern == 'basic') {
            $this->corrected_values = $this->buildCorrectedValues('g.', $this->VCFBody->getCorrectedValues());
        } else {
            // The build is not needed; the Chromosome object has used it already.
            $this->corrected_values = $this->buildCorrectedValues(
                $this->Chromosome->getCorrectedValues(),
                ':g.',
                $this->VCFBody->getCorrectedValues()
            );
        }

        // We also need to store the data fields. Yes, this is duplicated work.
        // However, it's much simpler to do it here; everything the VCFBody does is string-based.
        $HGVSVariant = new HGVS_Variant('g.' . $this->VCFBody->getCorrectedValue());
        $this->data = $HGVSVariant->getInfo();

        // We could have triggered a whitespace warning, but that's normal for us.
        unset($this->messages['WWHITESPACE']);
    }
}





class HGVS_VCFAlts extends HGVS_DNAAlts
{
    public array $patterns = [
        'invalid' => [ '/[A-Z]+/', [] ],
        'valid'   => [ '/(\.|[ACGTMRWSYKVHDBN]+)/', [] ],
    ];
}





class HGVS_VCFBody extends HGVS
{
    public array $patterns = [
        [ 'HGVS_VCFPosition', 'HGVS_VCFSeparator', 'HGVS_VCFRefs', 'HGVS_VCFSeparator', 'HGVS_VCFAlts', [] ],
    ];

    function getPositionString ($sPosition, $nIntronOffset, $nOffset, $nLength = 1)
    {
        // Takes the start position from the $sPosition and $nIntronOffset inputs (g. based or c. based),
        //  shifts it using the given $nOffset, adds the given length,
        //  and returns the new position string.
        // NOTE: This function does not detect moving from the CDS into the 3' UTR.

        // Check all input. $nOffset can be negative, sometimes we need to move one base backward.
        if (!preg_match('/^[-*]?[0-9]+$/', $sPosition)
            || (!is_int($nIntronOffset) && !ctype_digit($nIntronOffset))
            || (!is_int($nOffset) && !ctype_digit($nOffset))
            || (!is_int($nLength) && !ctype_digit($nLength))
            || $nLength < 1) {
            return false;
        }

        $aPositionsToAdjust = [
            [(string) $sPosition, $nIntronOffset, $nOffset],
            [(string) $sPosition, $nIntronOffset, $nOffset + ($nLength - 1)],
        ];
        foreach ($aPositionsToAdjust as $nKey => list($sPosition, $nIntronOffset, $nOffset)) {
            // If we're in the UTRs, mark this and remove the * for now, we're making calculations.
            $b3UTR = $b5UTR = false;
            if ($sPosition[0] == '*') {
                $b3UTR = true;
                $nPosition = (int) substr($sPosition, 1);
            } else {
                $nPosition = (int) $sPosition;
                if ($nPosition < 0) {
                    $b5UTR = true;
                }
            }

            if ($nIntronOffset) {
                $nPositionIntron = $nIntronOffset + $nOffset;
                // Compensate for the possibility that we just left the intron.
                if (($nIntronOffset > 0 && $nPositionIntron < 0)
                    || ($nIntronOffset < 0 && $nPositionIntron > 0)) {
                    $nPosition += $nPositionIntron;
                    $nPositionIntron = 0;
                }
                $nIntronOffset = $nPositionIntron;
            } else {
                $nPosition += $nOffset;
            }

            // Compensate for the possibility that we just entered or left the 5' UTR.
            if (!$b5UTR && $nPosition <= 0) {
                $nPosition --;
            } elseif ($b5UTR && $nPosition >= 0) {
                $nPosition ++;
            }
            $aPositionsToAdjust[$nKey] = [(!$b3UTR? '' : '*') . $nPosition, $nIntronOffset];
        }

        return (
            $aPositionsToAdjust[0][0] .
            (!$aPositionsToAdjust[0][1]? '' : ($aPositionsToAdjust[0][1] < 0? '' : '+') . $aPositionsToAdjust[0][1]) .
            ($aPositionsToAdjust[0] == $aPositionsToAdjust[1]? '' : '_' . $aPositionsToAdjust[1][0] .
                (!$aPositionsToAdjust[1][1]? '' : ($aPositionsToAdjust[1][1] < 0? '' : '+') . $aPositionsToAdjust[1][1])));
    }





    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.

        // Loop through the REF and ALT to isolate where they are different.
        // Recognize deletions, insertions, duplications, and more.
        // (ANNOVAR does something else than most other VCF generators)
        // Either way, VCF doesn't actually allow empty REFs or ALTs, so this will result in a warning.
        $sPosition = $this->VCFPosition->DNAPosition->position;
        $nIntronOffset = $this->VCFPosition->DNAPosition->offset;
        $sREF = rtrim($this->VCFRefs->getCorrectedValue(), '.'); // Change . into an empty string.
        $sALT = rtrim($this->VCFAlts->getCorrectedValue(), '.'); // Change . into an empty string.
        // Save original values before we edit them.
        $sOriREF = $sREF;
        $sOriALT = $sALT;
        $nOffset = 0;

        // Shift variant if REF and ALT are similar.
        // 'Eat' letters from either end - first left, then right - to isolate the difference.
        while (strlen($sREF) > 0 && strlen($sALT) > 0 && $sREF[0] == $sALT[0]) {
            $sREF = substr($sREF, 1);
            $sALT = substr($sALT, 1);
            $nOffset ++;
        }
        while (strlen($sREF) > 0 && strlen($sALT) > 0 && substr($sREF, -1) == substr($sALT, -1)) {
            $sREF = substr($sREF, 0, -1);
            $sALT = substr($sALT, 0, -1);
        }
        $nREF = strlen($sREF);
        $nALT = strlen($sALT);

        // Now determine the actual variant type.
        if ($nREF == 0 && $nALT == 0) {
            // Nothing left. Take the original range and add '='.
            $this->setCorrectedValue($this->getPositionString($sPosition, $nIntronOffset, 0, $nOffset) . '=');
            $this->data['type'] = '=';

        } elseif ($nREF == 1 && $nALT == 1) {
            // Substitution.
            // Recalculate the position always; we might have started with a
            //  range, but ended with just a single position.
            $this->setCorrectedValue($this->getPositionString($sPosition, $nIntronOffset, $nOffset) . $sREF . '>' . $sALT);
            $this->data['type'] = '>';

        } elseif ($nALT == 0) {
            // Deletion.
            $this->setCorrectedValue($this->getPositionString($sPosition, $nIntronOffset, $nOffset, $nREF) . 'del');
            $this->data['type'] = 'del';

        } elseif ($nREF == 0) {
            // Something has been added... could be an insertion or a duplication.
            if ($sALT != $sOriALT && substr($sOriALT, strrpos($sOriALT, $sALT) - $nALT, $nALT) == $sALT) {
                // Duplication. Note that the start position might be quite
                //  far from the actual insert.
                $this->setCorrectedValue($this->getPositionString($sPosition, $nIntronOffset, ($nOffset - $nALT), $nALT) . 'dup');
                $this->data['type'] = 'dup';

            } else {
                // Insertion. We should check if we're sure about where the insertion should go.
                // If the $sREF was '.' from the beginning, we can't be sure.
                $this->setCorrectedValue($this->getPositionString($sPosition, $nIntronOffset, ($nOffset - 1), 2) . 'ins' . $sALT, (!$sOriREF? 0.7 : 1));
                $this->data['type'] = 'ins';
                if (!$sOriREF) {
                    // ADD (not replace) this suggestion to the list.
                    $this->addCorrectedValue($this->getPositionString($sPosition, $nIntronOffset, $nOffset, 2) . 'ins' . $sALT, 0.3);
                    // Add a warning that we're not sure.
                    $this->messages['WVCFDOTREF'] = "The VCF standard doesn't allow empty REF or ALT fields. Since this variant had no REF field, we're not sure how to translate this variant to HGVS. Two options are given, but it depends on the software which generated these VCF values which option is correct.";
                }
            }

        } else {
            // Inversion or deletion-insertion. Both REF and ALT are >1.
            if ($sREF == strrev(str_replace(array('A', 'C', 'G', 'T'), array('T', 'G', 'C', 'A'), strtoupper($sALT)))) {
                // Inversion.
                $this->setCorrectedValue($this->getPositionString($sPosition, $nIntronOffset, $nOffset, $nREF) . 'inv');
                $this->data['type'] = 'inv';
            } else {
                // Deletion-insertion. Both REF and ALT are >1.
                $this->setCorrectedValue($this->getPositionString($sPosition, $nIntronOffset, $nOffset, $nREF) . 'delins' . $sALT);
                $this->data['type'] = 'delins';
            }
        }

        // Store REF and ALT so we can check these values from outside of this class.
        $this->REF = $sREF;
        $this->ALT = $sALT;
    }
}





class HGVS_VCFPosition extends HGVS_DNAPositions
{
    // We use VCFPosition to enforce a single position
    //  while at the same time inheriting the helper methods from DNAPositions.
    public array $patterns = [
        'single' => [ 'HGVS_DNAPosition', [] ],
    ];
}





class HGVS_VCFRefs extends HGVS_DNARefs
{
    public array $patterns = [
        'invalid' => [ '/[A-Z]+/', [] ],
        'valid'   => [ '/(\.|[ACGTN]+)/', [] ],
    ];
}





class HGVS_VCFSeparator extends HGVS
{
    public array $patterns = [
        [ '/[: -]?/', [] ],
    ];
}





trait HGVS_CheckBasesGiven
{
    // Enables a WBASESGIVEN check, where the given REFs are compared to the given position length.
    // This is meant for variants that don't need those bases, like wild-type variants or methylation-related variants.
    public function checkBasesGiven ()
    {
        // Check the given bases (if any) and compare them to the position length.
        $Positions = $this->getParentProperty('DNAPositions');
        $Refs = $this->getParentProperty('DNARefs');
        if ($Positions && $Refs && !$Positions->uncertain && !$Positions->unknown) {
            // We're not implementing all checks that, e.g., DNADelSuffix implements.
            // These variants don't happen often. If I would like to implement all of that,
            //  then move the code over to this trait and use this trait in DNADelSuffix.

            // The suffix should not have been used only when the variant length matches the length given in the suffix.
            $nVariantLength = $Positions->getLengths()[0];
            $nSuffixLength = strlen($Refs->getCorrectedValue());

            // Simplest situation first: length matches.
            if ($nVariantLength == $nSuffixLength) {
                $this->messages['WBASESGIVEN'] = 'The given sequence is redundant and should be removed.';
                $Refs->setCorrectedValue('');

            } elseif (!isset($Refs->messages['EINVALIDNUCLEOTIDES'])) {
                // Universal length checks. These messages are kept universal and slightly simplified.
                if ($nSuffixLength < $nVariantLength) {
                    $this->messages['EBASESTOOSHORT'] =
                        "The variant's positions indicate a sequence that's longer than the given sequence." .
                        " Please adjust either the variant's positions or the given sequence.";
                } else {
                    $this->messages['EBASESTOOLONG'] =
                        "The variant's positions indicate a sequence that's shorter than the given sequence." .
                        " Please adjust either the variant's positions or the given sequence.";
                }
            }
        }
    }
}





trait HGVS_DNASequence
{
    // Useful for suffix classes; defining getSequence(), getSequences(), and getLengths().
    public array $sequences = [];

    public function getLengths ()
    {
        // This function calculates the sequence's minimum and maximum length, and returns this into an array.
        if ($this->getSequences()) {
            $nLengthMin = strlen($this->getSequences()[0]);
            $nLengthMax = strlen($this->getSequences()[1]);
            return [$nLengthMin, $nLengthMax];

        } else {
            return false;
        }
    }





    public function getSequence ()
    {
        // This function returns the sequence, as long as the length is certain.
        $aSequences = $this->getSequences();
        if ($aSequences[0] == $aSequences[1]) {
            return $aSequences[0];
        } else {
            return false;
        }
    }





    public function getSequences ()
    {
        // This function gets the entire sequence.
        if (!empty($this->sequences)) {
            return $this->sequences;
        }

        // Create arrays with chunks of the sequence. I need chunks because lengths should modify ONE sequence chunk.
        $aSequencesMin = [];
        $aSequencesMax = [];

        foreach ($this->patterns[$this->matched_pattern] as $Pattern) {
            if (is_object($Pattern)) {
                if (in_array(get_class($Pattern), ['HGVS_DNARefs', 'HGVS_DNAAlts'])) {
                    $aSequencesMin[] = $Pattern->getCorrectedValue();
                    $aSequencesMax[] = $Pattern->getCorrectedValue();
                } elseif (get_class($Pattern) == 'HGVS_Lengths') {
                    $aLengths = $Pattern->getLengths();
                    $nLastKey = array_key_last($aSequencesMin);
                    if (!isset($nLastKey)) {
                        // This sequence starts with a length.
                        $aSequencesMin[] = 'N';
                        $aSequencesMax[] = 'N';
                        $nLastKey = 0;
                    }

                    $aSequencesMin[$nLastKey] = str_repeat($aSequencesMin[$nLastKey], $aLengths[0]);
                    $aSequencesMax[$nLastKey] = str_repeat($aSequencesMax[$nLastKey], $aLengths[1]);
                }
            }
            // Other patterns are ignored (strings and the message array).
        }

        $this->sequences = [
            implode($aSequencesMin),
            implode($aSequencesMax),
        ];
        return $this->sequences;
    }
}
