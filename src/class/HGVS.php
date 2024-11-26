<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2024-11-05
 * Modified    : 2024-11-26
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
    public array $patterns = [
        'full_variant' => [ 'HGVS_ReferenceSequence', ':', 'HGVS_Variant', [] ],
    ];
    public array $corrected_values = [];
    public array $data = [];
    public array $messages = [];
    public array $properties = [];
    public array $regex = [];
    public bool $caseOK = true;
    public bool $matched = false;
    public string $input;
    public string $matched_pattern;
    public string $suffix;
    public string $value;
    public $parent;

    public function __construct ($sValue, $Parent = null)
    {
        $this->input = $sValue;
        $this->parent = $Parent;

        // Loop through all patterns and match them.
        foreach ($this->patterns as $sPatternName => $aPattern) {
            $aMessages = array_pop($aPattern);
            $sInputToParse = $sValue;
            $bMatching = true;

            foreach ($aPattern as $i => $sPattern) {
                // Quick check: do we still have something left?
                if ($sInputToParse === '') {
                    $bMatching = false;
                    break;
                }

                if (substr($sPattern, 0, 5) == 'HGVS_') {
                    // This is a class.
                    $aPattern[$i] = new $sPattern($sInputToParse, $this);
                    if ($aPattern[$i]->hasMatched()) {
                        // This pattern matched. Store what is left, if anything is left.
                        $sInputToParse = $aPattern[$i]->getSuffix();
                        // Merge their data and messages with ours.
                        $this->data = array_merge(
                            $this->data,
                            $aPattern[$i]->getData()
                        );
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
                        $bMatching = false;
                        break;
                    }

                } elseif (strlen($sPattern) >= 3 && substr($sPattern, 0, 1) == '/' && substr($sPattern, -1) == '/') {
                    // Regex. Make sure it matches the start of the string. Make sure it's case-insensitive.
                    $sPattern = '/^' . substr($sPattern, 1) . 'i';
                    if (preg_match($sPattern, $sInputToParse, $aRegs)) {
                        // This pattern matched. Store what is left, if anything is left.
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
                    if (strlen($sInputToParse) >= strlen($sPattern) && substr($sInputToParse, 0, strlen($sPattern)) == $sPattern) {
                        // This pattern matched. Store what is left, if anything is left.
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
                foreach ($this->properties as $sProperty) {
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

            if ($sInputToParse) {
                // We matched everything, but there is a suffix, something left that didn't match.
                // In the main HGVS object, this is a problem. Otherwise, this is what we have to return to the parent.
                $this->value = substr($sValue, 0, -strlen($sInputToParse));
                $this->suffix = $sInputToParse;
                if (!isset($this->parent)) {
                    // This is the main HGVS class. The variant has a suffix that we didn't identify.
                    $this->messages['WSUFFIXGIVEN'] = 'Nothing should follow "' . $this->value . '".';
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
        // This functions is called whenever a var_dump() is called on the object.
        // Because we want to limit the space taken up in the var_dump() output, we'll limit it here.

        $aReturn = [
            '__note' => 'The output of var_dump() is reduced by __debugInfo().'
        ];

        foreach ($this as $sPropertyName => $Property) {
            if (!in_array($sPropertyName, ['parent', 'patterns'])) {
                $aReturn[$sPropertyName] = $Property;
            }
        }

        return $aReturn;
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
                return ($PositionStart->offset < $PositionEnd->offset);
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





    public function isTheCaseOK ()
    {
        return $this->caseOK;
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





class HGVS_DNADel extends HGVS
{
    public array $patterns = [
        [ 'del', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->setCorrectedValue(strtolower($this->value));
        $this->data['type'] = $this->getCorrectedValue();
        $this->caseOK = ($this->value == $this->getCorrectedValue());
    }
}





class HGVS_DNAAlts extends HGVS
{
    public array $patterns = [
        'valid'   => [ '/[ACGTMRWSYKVHDBN]+/', [] ],
        'invalid' => [ '/[A-Z]+/', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->setCorrectedValue(strtoupper($this->value));
        $this->caseOK = ($this->value == $this->getCorrectedValue());

        // Check for invalid nucleotides.
        if ($this->matched_pattern == 'invalid') {
            // List the invalid nucleotides.
            $sUnknownBases = preg_replace($this->patterns['valid'][0], '', $this->getCorrectedValue());
            $this->messages['EINVALIDNUCLEOTIDES'] = 'This variant description contains invalid nucleotides: "' . implode('", "', array_unique(str_split($sUnknownBases))) . '".';
            // Then, replace the 'U's with 'T's.
            $this->setCorrectedValue(str_replace('U', 'T', $this->getCorrectedValue()));
        }
    }
}





class HGVS_DNADelSuffix extends HGVS
{
    public array $patterns = [
        // Since none of these match "ins", a "delAinsC" won't ever pass here.
        [ 'HGVS_Length', [ 'WSUFFIXFORMAT' => 'The part after "del" does not follow HGVS guidelines.' ] ],
        [ '[', 'HGVS_Length', ']', [ 'WSUFFIXFORMAT' => 'The part after "del" does not follow HGVS guidelines.' ] ],
        [ 'HGVS_DNARefs', 'HGVS_Length', [ 'WSUFFIXFORMAT' => 'The part after "del" does not follow HGVS guidelines.' ] ],
        [ 'HGVS_DNARefs', '[', 'HGVS_Length', ']', [] ],
        [ 'HGVS_DNARefs', [] ],
        [ '(', 'HGVS_DNARefs', ')', [ 'WSUFFIXFORMAT' => 'The part after "del" does not follow HGVS guidelines.' ] ],
        [ '(', 'HGVS_DNARefs', 'HGVS_Length', ')', [ 'WSUFFIXFORMAT' => 'The part after "del" does not follow HGVS guidelines.' ] ],
        [ '(', 'HGVS_DNARefs', '[', 'HGVS_Length', '])', [ 'WSUFFIXFORMAT' => 'The part after "del" does not follow HGVS guidelines.' ] ],
        [ '[', 'HGVS_DNARefs', ']', [ 'WSUFFIXFORMAT' => 'The part after "del" does not follow HGVS guidelines.' ] ],
        [ '[', 'HGVS_DNARefs', 'HGVS_Length', ']', [ 'WSUFFIXFORMAT' => 'The part after "del" does not follow HGVS guidelines.' ] ],
        [ '[', 'HGVS_DNARefs', '[', 'HGVS_Length', ']]', [ 'WSUFFIXFORMAT' => 'The part after "del" does not follow HGVS guidelines.' ] ],
    ];

    public function getLengths ()
    {
        // This function calculates the suffix's minimum and maximum length, and returns this into an array.

        if (isset($this->DNARefs)) {
            $nSequenceLength = strlen($this->DNARefs->getValue());
            if (!isset($this->Length)) {
                // Simple sequence is given.
                return [$nSequenceLength, $nSequenceLength];
            } else {
                // Combination of sequence and length given.
                $aLengths = $this->Length->getLengths();
                return [($nSequenceLength * $aLengths[0]), ($nSequenceLength * $aLengths[1])];
            }
        } else {
            // Deliberately not checking for this object's existence, so we break when we made a bug somewhere.
            return $this->Length->getLengths();
        }
    }





    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $Positions = $this->getParent('HGVS_DNAVariantBody')->DNAPositions;
        $aMessages = $Positions->getMessages();

        // Remove any complaints that HGVS_Length may have had, when we already threw a WSUFFIXFORMAT.
        if (isset($this->messages['WSUFFIXFORMAT'])) {
            unset($this->messages['WLENGTHFORMAT']);
        }

        // Don't check anything about the suffix length when there are problems with the positions.
        if (isset($aMessages['EPOSITIONFORMAT'])) {
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
            $bSuffixLengthIsCertain = ($nMinLengthSuffix == $nMaxLengthSuffix);

            // Simplest situation first: certain everything, length matches.
            if ($bPositionLengthIsCertain && $bSuffixLengthIsCertain && $nMinLengthVariant == $nMinLengthSuffix) {
                $this->messages['WSUFFIXGIVEN'] = "The deleted sequence is redundant and should be removed.";

            } elseif ($bPositionLengthIsCertain && !$bSuffixLengthIsCertain && $nMaxLengthSuffix <= $nMaxLengthVariant) {
                // A special case: When the positions are certain but the deletion is uncertain but fits, this is a special class of warning.
                $this->messages['WPOSITIONSCERTAIN'] =
                    "The variant's positions indicate a certain sequence, but the deletion itself indicates the deleted sequence is uncertain." .
                    " This is a conflict; when the deleted sequence is uncertain, make the variant's positions uncertain by adding parentheses.";

            } elseif (!$bPositionLengthIsCertain && $bSuffixLengthIsCertain && $nMaxLengthSuffix == $nMaxLengthVariant) {
                // A special case: When the positions are uncertain but the deletion is certain and fits
                //  the maximum length precisely, this is a special class of warning.
                $this->messages['WPOSITIONSUNCERTAIN'] =
                    "The variant's positions indicate an uncertain sequence, but the deletion itself indicates a deleted sequence that fits the given positions precisely." .
                    " This is a conflict; when the deleted sequence is certain, make the variant's positions certain by removing the parentheses.";

            } else {
                // Universal length checks. These messages are kept universal and slightly simplified.
                // E.g., an ESUFFIXTOOLONG may mean that the deleted sequence CAN BE too long, but isn't always.
                // (e.g., g.(100_200)del(100_300).
                if ($nMinLengthSuffix < $nMinLengthVariant) {
                    $this->messages['ESUFFIXTOOSHORT'] =
                        "The variant's positions indicate a sequence that's longer than the given deleted sequence." .
                        " Please adjust either the variant's positions or the given deleted sequence.";
                }
                if ($nMaxLengthSuffix > $nMaxLengthVariant) {
                    $this->messages['ESUFFIXTOOLONG'] =
                        "The variant's positions indicate a sequence that's shorter than the given deleted sequence." .
                        " Please adjust either the variant's positions or the given deleted sequence.";
                }
            }
        }

        // Store the corrected value.
        if (isset($this->messages['WSUFFIXGIVEN'])) {
            // The suffix should be removed.
            // NOTE: This is not true for delAinsG, but we don't know that here yet.
            $this->setCorrectedValue('');
        } elseif (!isset($this->Length)) {
            $this->corrected_values = $this->DNARefs->getCorrectedValues();
        } else {
            $this->corrected_values = $this->buildCorrectedValues(
                (isset($this->DNARefs)? $this->DNARefs->getCorrectedValues() : 'N'),
                (!$this->Length->getCorrectedValues()? '' :
                    $this->buildCorrectedValues('[', $this->Length->getCorrectedValues(), ']'))
            );
        }
    }
}





class HGVS_DNAIns extends HGVS
{
    public array $patterns = [
        [ 'ins', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->setCorrectedValue(strtolower($this->value));
        $this->data['type'] = (($this->parent->data['type'] ?? '') == 'del'? 'delins' : 'ins');
        $this->caseOK = ($this->value == $this->getCorrectedValue());

        if ($this->data['type'] == 'ins') {
            // Insertions have some specific needs.
            $Positions = $this->getParent('HGVS_DNAVariantBody')->DNAPositions;
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
                $this->messages['WPOSITIONFORMAT'] =
                    'An insertion must have taken place between two neighboring positions.' .
                    ' If the exact location is unknown, please indicate this by placing parentheses around the positions.';
                $Positions->makeUncertain();

            } elseif ($Positions->uncertain && $Positions->getLengths() == [1,2]) {
                // If the exact location of an insertion is unknown, this can be indicated
                //  by placing the positions in the range-format, e.g. c.(1_10)insA. In this
                //  case, the two positions should not be neighbours, since that would imply that
                //  the position is certain.
                $this->messages['WPOSITIONFORMAT'] =
                    'The two positions do not indicate a range longer than two bases.' .
                    ' Please remove the parentheses if the positions are certain.';
                $Positions->makeCertain();
            }
        }
    }
}





class HGVS_DNAInsSuffix extends HGVS
{
    public array $patterns = [
        [ 'HGVS_Length', [ 'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines.' ] ],
        [ '[', 'HGVS_Length', ']', [ 'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines.' ] ],
        [ 'HGVS_DNAAlts', 'HGVS_Length', [ 'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines.' ] ],
        [ 'HGVS_DNAAlts', '[', 'HGVS_Length', ']', [] ],
        [ 'HGVS_DNAAlts', [] ],
        [ '(', 'HGVS_DNAAlts', ')', [ 'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines.' ] ],
        [ '(', 'HGVS_DNAAlts', 'HGVS_Length', ')', [ 'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines.' ] ],
        [ '(', 'HGVS_DNAAlts', '[', 'HGVS_Length', '])', [ 'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines.' ] ],
        [ '[', 'HGVS_DNAAlts', ']', [ 'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines.' ] ],
        [ '[', 'HGVS_DNAAlts', 'HGVS_Length', ']', [ 'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines.' ] ],
        [ '[', 'HGVS_DNAAlts', '[', 'HGVS_Length', ']]', [ 'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines.' ] ],
    ];

    public function getLengths ()
    {
        // This function calculates the suffix's minimum and maximum length, and returns this into an array.

        if (isset($this->DNAAlts)) {
            $nSequenceLength = strlen($this->DNAAlts->getValue());
            if (!isset($this->Length)) {
                // Simple sequence is given.
                return [$nSequenceLength, $nSequenceLength];
            } else {
                // Combination of sequence and length given.
                $aLengths = $this->Length->getLengths();
                return [($nSequenceLength * $aLengths[0]), ($nSequenceLength * $aLengths[1])];
            }
        } else {
            // Deliberately not checking for this object's existence, so we break when we made a bug somewhere.
            return $this->Length->getLengths();
        }
    }





    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        // Remove any complaints that HGVS_Length may have had, when we already threw a WSUFFIXFORMAT.
        if (isset($this->messages['WSUFFIXFORMAT'])) {
            unset($this->messages['WLENGTHFORMAT']);
        }

        // A deletion-insertion of one base to one base, is a substitution.
        if ($this->parent->getData()['type'] == 'delins'
            && $this->getParent('HGVS_DNAVariantBody')->DNAPositions->getLengths() == [1,1]
            && isset($this->DNAAlts)
            && $this->getLengths() == [1,1]) {
            $this->messages['WWRONGTYPE'] =
                'A deletion-insertion of one base to one base should be described as a substitution.';
            // Force the corrected value of the DelSuffix to NOT empty.
            $DelSuffix = ($this->getParent('HGVS_DNAVariantBody')->DNADelSuffix ?? false);
            if ($DelSuffix && $DelSuffix->getMessages()['WSUFFIXGIVEN']) {
                // Undo that change.
                $DelSuffix->setCorrectedValue($DelSuffix->DNARefs->getCorrectedValue());
            }
        }

        // Store the corrected value.
        if (isset($this->DNAAlts) && !isset($this->Length)) {
            $this->corrected_values = $this->DNAAlts->getCorrectedValues();
        } else {
            $this->corrected_values = $this->buildCorrectedValues(
                (isset($this->DNAAlts)? $this->DNAAlts->getCorrectedValues() : 'N'),
                (!$this->Length->getCorrectedValues()? '' :
                    $this->buildCorrectedValues('[', $this->Length->getCorrectedValues(), ']'))
            );
        }
    }
}





class HGVS_DNAPosition extends HGVS
{
    public array $patterns = [
        'unknown'          => [ '?', [] ],
        'unknown_intronic' => [ '/([-*]?([0-9]+))([+-]\?)/', [] ],
        'known'            => [ '/([-*]?([0-9]+))([+-]([0-9]+))?/', [] ], // Note: We're using these sub patterns in the validation.
    ];
    public array $position_limits = [
        'g' => [1, 4294967295, 0, 0], // position min, position max, offset min, offset max.
        'm' => [1, 4294967295, 0, 0],
        'c' => [-8388608, 8388607, -2147483648, 2147483647],
        'n' => [1, 8388607, -2147483648, 2147483647],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->unknown = ($this->matched_pattern == 'unknown');
        $this->unknown_offset = ($this->matched_pattern == 'unknown_intronic');
        $sVariantPrefix = $this->getParent('HGVS_Variant')->DNAPrefix->getValue();
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

        } else {
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
                $this->messages['WPOSITIONFORMAT'] = 'Variant positions should not be prefixed by a 0.';
                $nCorrectionConfidence *= 0.9;
            } elseif ($this->intronic && !$this->unknown_offset) {
                if (!$this->offset) {
                    $this->messages['EPOSITIONFORMAT'] = 'This variant description contains an invalid intronic position: "' . $this->value . '".';
                } elseif ((string) abs($this->offset) != $this->regex[4]) {
                    $this->messages['WPOSITIONFORMAT'] = 'Intronic positions should not be prefixed by a 0.';
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

            // Adjust minimum and maximum values, to be used in further processing.
            $this->position_limits[0] = $this->position_sortable;
            $this->position_limits[1] = $this->position_sortable;
            if (!$this->intronic) {
                $this->position_limits[2] = 0;
                $this->position_limits[3] = 0;
            } elseif ($this->matched_pattern != 'unknown_intronic') {
                $this->position_limits[2] = $this->offset;
                $this->position_limits[3] = $this->offset;
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
                ($this->offset? ($this->offset > 0? '+' : '-') . ($this->unknown_offset? '?' : $this->offset) : '')
            );
        }
    }
}





class HGVS_DNAPositionStart extends HGVS
{
    public array $patterns = [
        'uncertain_range'  => [ '(', 'HGVS_DNAPosition', '_', 'HGVS_DNAPosition', ')', [] ],
        'uncertain_single' => [ '(', 'HGVS_DNAPosition', ')', [ 'WPOSITIONFORMAT' => "The variant's positions contain redundant parentheses." ] ],
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
            foreach (['unknown', 'unknown_offset', 'UTR', 'intronic', 'position', 'position_sortable', 'position_limits', 'offset'] as $variable) {
                $this->$variable = $this->DNAPosition->$variable;
            }

        } else {
            // Copy the booleans first.
            foreach (['unknown', 'unknown_offset', 'UTR', 'intronic'] as $variable) {
                $this->$variable = ($this->DNAPosition[0]->$variable || $this->DNAPosition[1]->$variable);
            }

            // Before we add more errors or warnings, check if we have multiple errors that are the same.
            // We currently don't handle arrays as error messages.
            $sVariantPrefix = $this->getParent('HGVS_Variant')->DNAPrefix->getValue();
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
                    $this->messages['WPOSITIONFORMAT'] = 'This variant description contains two positions that are the same.';
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
                    $this->messages['WPOSITIONFORMAT'] = "The variant's positions are not given in the correct order.";
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

                // Start positions, when a range, internally store the lowest known value.
                // End positions, when a range, internally store the highest known value.
                if (get_class($this) == 'HGVS_DNAPositionStart') {
                    $iPositionToStore = ($this->DNAPosition[0]->unknown? 1 : 0);
                } else {
                    $iPositionToStore = ($this->DNAPosition[1]->unknown? 0 : 1);
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
        'uncertain_single' => [ '(', 'HGVS_DNAPosition', ')', [ 'WPOSITIONFORMAT' => "The variant's position contains redundant parentheses." ] ],
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
            $this->corrected_values[$this->getCorrectedValue()] *= 0.75;
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
            $this->corrected_values[$this->getCorrectedValue()] *= 0.75;
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
        $VariantPrefix = $this->getParent('HGVS_Variant')->DNAPrefix;
        $nCorrectionConfidence = (current($this->corrected_values) ?: 1); // Fetch current one, because this object can be revalidated.

        if (!$this->range) {
            // A single position, just copy everything.
            foreach (['unknown', 'unknown_offset', 'UTR', 'intronic', 'position', 'position_sortable', 'position_limits', 'offset'] as $variable) {
                $this->$variable = $this->DNAPosition->$variable;
            }

        } else {
            // Copy only the booleans; the rest doesn't apply to a range.
            foreach (['unknown', 'unknown_offset', 'UTR', 'intronic'] as $variable) {
                $this->$variable = ($this->DNAPositionStart->$variable || $this->DNAPositionEnd->$variable);
            }

            // Before we add more errors or warnings, check if we have multiple errors that are the same.
            // We currently don't handle arrays as error messages.
            $sVariantPrefix = $VariantPrefix->getValue();
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
                $this->messages['WPOSITIONFORMAT'] = 'This variant description contains two positions that are the same.';
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
                    $this->messages['WPOSITIONFORMAT'] = "The variant's positions are not given in the correct order.";
                    // Due to excessive complexity with ranges and possible solutions and assumptions,
                    //  we'll only swap positions when neither Start nor End is a range.
                    if (!$this->DNAPositionStart->range && !$this->DNAPositionEnd->range) {
                        // Resort the positions.
                        list($this->DNAPositionStart, $this->DNAPositionEnd) = [$this->DNAPositionEnd, $this->DNAPositionStart];
                        $nCorrectionConfidence *= 0.9;
                    }

                } elseif (!$this->arePositionsSorted($PositionB, $PositionC)) {
                    // We can't fix that, so throw an error, not a warning.
                    $this->messages['EPOSITIONFORMAT'] = "The variant's positions overlap but are not the same.";
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

        // Store the positions in the data array.
        $aPositions = ($this->range? [$this->DNAPositionStart, $this->DNAPositionEnd] : [$this->DNAPosition, $this->DNAPosition]);
        $this->data['position_start'] = $aPositions[0]->position_sortable;
        $this->data['position_end'] = $aPositions[1]->position_sortable;
        if ($VariantPrefix && $VariantPrefix->molecule_type == 'transcript') {
            $this->data['position_start_intron'] = $aPositions[0]->offset;
            $this->data['position_end_intron'] = $aPositions[1]->offset;
        }
        $this->data['range'] = $this->range;

        // Now, store the corrected value.
        if ($this->matched_pattern == 'uncertain_range') {
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
    }
}





class HGVS_DNAPrefix extends HGVS
{
    public array $patterns = [
        'coding'     => [ 'c', [] ],
        'genomic'    => [ 'g', [] ],
        'mito'       => [ 'm', [] ],
        'non-coding' => [ 'n', [] ],
        'circular'   => [ 'o', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->molecule_type = (in_array($this->matched_pattern, ['coding', 'non-coding'])? 'transcript' : 'genome');
        $this->setCorrectedValue(strtolower($this->value));
        $this->caseOK = ($this->value == $this->getCorrectedValue());
    }
}





class HGVS_DNARefs extends HGVS
{
    public array $patterns = [
        'valid'   => [ '/[ACGTN]+/', [] ],
        'invalid' => [ '/[A-Z]+/', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->setCorrectedValue(strtoupper($this->value));
        $this->caseOK = ($this->value == $this->getCorrectedValue());

        // Check for invalid nucleotides.
        if ($this->matched_pattern == 'invalid') {
            // This is a special case. We need to prevent that we're matching "ins".
            // If we do, we need to pretend that we never matched at all.
            $nINS = strpos($this->getCorrectedValue(), 'INS');
            if ($nINS !== false) {
                // OK, we can't match this part. We can match anything that came before, though.
                if (!$nINS) {
                    // The string starts with "ins". Pretend that didn't match anything.
                    $this->matched = false;
                    return;
                } else {
                    // Register that we matched up to 'ins'.
                    $this->suffix = substr($this->value, $nINS) . $this->suffix;
                    $this->value = substr($this->value, 0, $nINS);
                    $this->setCorrectedValue(strtoupper($this->value));
                }
            }

            // List the invalid nucleotides.
            $sUnknownBases = preg_replace($this->patterns['valid'][0], '', $this->getCorrectedValue());
            $this->messages['EINVALIDNUCLEOTIDES'] = 'This variant description contains invalid nucleotides: "' . implode('", "', array_unique(str_split($sUnknownBases))) . '".';
            // Then, replace the 'U's with 'T's.
            $this->setCorrectedValue(str_replace('U', 'T', $this->getCorrectedValue()));
        }
    }
}





class HGVS_DNAVariantBody extends HGVS
{
    public array $patterns = [
        'delXins_with_suffix' => [ 'HGVS_DNAPositions', 'HGVS_DNADel', 'HGVS_DNADelSuffix', 'HGVS_DNAIns', 'HGVS_DNAInsSuffix', [] ],
        'delXins'             => [ 'HGVS_DNAPositions', 'HGVS_DNADel', 'HGVS_DNADelSuffix', 'HGVS_DNAIns', [ 'ESUFFIXMISSING' => 'The inserted sequence must be provided for deletion-insertions.' ] ],
        'delins_with_suffix'  => [ 'HGVS_DNAPositions', 'HGVS_DNADel', 'HGVS_DNAIns', 'HGVS_DNAInsSuffix', [] ],
        'delins'              => [ 'HGVS_DNAPositions', 'HGVS_DNADel', 'HGVS_DNAIns', [ 'ESUFFIXMISSING' => 'The inserted sequence must be provided for deletion-insertions.' ] ],
        'del_with_suffix'     => [ 'HGVS_DNAPositions', 'HGVS_DNADel', 'HGVS_DNADelSuffix', [] ],
        'del'                 => [ 'HGVS_DNAPositions', 'HGVS_DNADel', [] ],
        'ins_with_suffix'     => [ 'HGVS_DNAPositions', 'HGVS_DNAIns', 'HGVS_DNAInsSuffix', [] ],
        'ins'                 => [ 'HGVS_DNAPositions', 'HGVS_DNAIns', [ 'ESUFFIXMISSING' => 'The inserted sequence must be provided for insertions.' ] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        if (isset($this->messages['WWRONGTYPE'])) {
            // We need to convert the variant from one type into the next.
            if ($this->data['type'] == 'delins'
                && strpos($this->messages['WWRONGTYPE'], 'substitution') !== false
                && isset($this->DNADelSuffix)) {
                $this->corrected_values = $this->buildCorrectedValues(
                    $this->DNAPositions->getCorrectedValues(),
                    $this->DNADelSuffix->DNARefs->getCorrectedValues(),
                    '>',
                    $this->DNAInsSuffix->getCorrectedValues()
                );
                // Remove the warning that complained about the base after the del.
                unset($this->messages['WSUFFIXGIVEN']);
            }
        }
    }
}





class HGVS_Length extends HGVS
{
    public array $patterns = [
        'range'              => [ '/([0-9]+)_([0-9]+)/', [] ],
        'range_with_parens'  => [ '/\(([0-9]+)_([0-9]+)\)/', [] ],
        'single'             => [ '/([0-9]+)/', [] ],
        'single_with_parens' => [ '/\(([0-9]+)\)/', [] ],
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

        if (in_array($this->matched_pattern, ['range', 'single_with_parens'])) {
            $this->messages['WLENGTHFORMAT'] = 'This variant description contains an invalid sequence length: "' . $this->value . '".';
        }

        // Store the lengths.
        $this->lengths[0] = (int) $this->regex[1];
        if (!$this->range) {
            $this->lengths[1] = $this->lengths[0];
            // A bit of a hack because I put everything in one class instead of using a subclass for a single length.
            $this->regex[2] = $this->regex[1];
        } else {
            $this->lengths[1] = (int) $this->regex[2];
        }

        // Check for values with zeros.
        foreach ($this->lengths as $i => $nLength) {
            if (!$nLength) {
                $this->messages['ELENGTHFORMAT'] = 'This variant description contains an invalid sequence length: "' . $nLength . '".';
            } elseif ((string) $nLength !== $this->regex[$i + 1]) {
                $this->messages['WLENGTHFORMAT'] = 'Sequence lengths should not be prefixed by a 0.';
                // Adjust the confidence, but not twice.
                if (!$i || $this->range) {
                    $nCorrectionConfidence *= 0.9;
                }
            }
        }

        // Check ranges.
        if ($this->range) {
            if ($this->lengths[0] == $this->lengths[1]) {
                // If the lengths are the same, warn and remove one.
                $this->messages['WLENGTHFORMAT'] = 'This variant description contains two sequence lengths that are the same.';
                $nCorrectionConfidence *= 0.9;
                // Discard the other object.
                $this->range = false;

            } elseif ($this->lengths[0] > $this->lengths[1]) {
                // Lengths aren't given in the right order.
                $this->messages['WLENGTHFORMAT'] = 'This variant description contains two sequence lengths that are not given in the correct order.';
                $nCorrectionConfidence *= 0.9;
                // Swap the lengths.
                list($this->lengths[0], $this->lengths[1]) = [$this->lengths[1], $this->lengths[0]];
            }
        }

        // Store the corrected value.
        if (!$this->range) {
            // Actually, when the length is 1, it's redundant, and it shouldn't be given.
            if ($this->lengths[0] == 1) {
                $this->setCorrectedValue('');
            } else {
                $this->corrected_values = $this->buildCorrectedValues(
                    ['' => $nCorrectionConfidence],
                    $this->lengths[0]
                );
            }
        } else {
            $this->corrected_values = $this->buildCorrectedValues(
                ['' => $nCorrectionConfidence],
                '(' . $this->lengths[0] . '_' . $this->lengths[1] . ')'
            );
        }
    }
}





class HGVS_ReferenceSequence extends HGVS
{
    public array $patterns = [
        [ '/NC_[0-9]{6}\.[0-9]{1,2}/', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->setCorrectedValue(strtoupper($this->value));
        $this->caseOK = ($this->value == $this->getCorrectedValue());
    }
}





class HGVS_Variant extends HGVS
{
    public array $patterns = [
        'DNA' => [ 'HGVS_DNAPrefix', '.', 'HGVS_DNAVariantBody', [] ],
    ];
}
