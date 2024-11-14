<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2024-11-05
 * Modified    : 2024-11-12
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





class HGVS {
    public array $patterns = [
        'full_variant' => [ 'HGVS_ReferenceSequence', ':', 'HGVS_Variant', [] ],
    ];
    public array $data = [];
    public array $messages = [];
    public array $properties = [];
    public array $regex = [];
    public bool $matched = false;
    public string $input;
    public string $matched_pattern;
    public string $suffix;
    public string $value;
    public $parent;

    public function __construct($sValue, $Parent = null) {
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
                    // Regex. Make sure it matches the start of the string.
                    $sPattern = '/^' . substr($sPattern, 1);
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
                continue;
            } else {
                $this->matched_pattern = $sPatternName;
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
            }

            // Add the messages.
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





    public function getData ()
    {
        return ($this->data ?? []);
    }





    public function getMessages ()
    {
        return ($this->messages ?? []);
    }





    public function getSuffix ()
    {
        return ($this->suffix ?? '');
    }





    public function hasMatched ()
    {
        return ($this->matched ?? false);
    }





    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
    }
}





class HGVS_DNADel extends HGVS {
    public array $patterns = [
        [ 'del', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->data['type'] = 'del';
    }
}





class HGVS_DNAPosition extends HGVS {
    public array $patterns = [
        'unknown'          => [ '?', [] ],
        'unknown_intronic' => [ '/([-*]?([0-9]+))([+-]?)/', [] ],
        'known'            => [ '/([-*]?([0-9]+))([+-]([0-9]+))?/', [] ], // Note: We're using these sub patterns in the validation.
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->unknown = ($this->matched_pattern != 'known');
        if ($this->matched_pattern == 'unknown') {
            $this->UTR = false;
            $this->intronic = false;
            $this->position = $this->value;
            $this->position_sortable = null; // This depends on how this position is used.
            $this->offset = 0;

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
            } elseif ((string) $this->position != $this->regex[1]) {
                $this->messages['WPOSITIONFORMAT'] = 'Variant positions should not be prefixed by a 0.';
            } elseif ($this->intronic && !$this->unknown) {
                if (!$this->offset) {
                    $this->messages['EPOSITIONFORMAT'] = 'This variant description contains an invalid intronic position: "' . $this->value . '".';
                } elseif ((string) abs($this->offset) != $this->regex[4]) {
                    $this->messages['WPOSITIONFORMAT'] = 'Intronic positions should not be prefixed by a 0.';
                }
            }
        }
    }
}





class HGVS_DNAPositionStart extends HGVS {
    public array $patterns = [
        'uncertain_range' => [ '(', 'HGVS_DNAPosition', '_', 'HGVS_DNAPosition', ')', [] ],
        'single'          => [ 'HGVS_DNAPosition', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->range = is_array($this->DNAPosition); // This will fail if we don't have this property, which is good, because that shouldn't happen.
        $this->uncertain = ($this->matched_pattern == 'uncertain_range');
    }
}
class HGVS_DNAPositionEnd extends HGVS_DNAPositionStart {}





class HGVS_DNAPositions extends HGVS {
    public array $patterns = [
        'uncertain_range' => [ '(', 'HGVS_DNAPositionStart', '_', 'HGVS_DNAPositionEnd', ')', [] ],
        'range'           => [ 'HGVS_DNAPositionStart', '_', 'HGVS_DNAPositionEnd', [] ],
        'single'          => [ 'HGVS_DNAPosition', [] ],
    ];

    public function validate ()
    {
        // Provide additional rules for validation, and stores values for the variant info if needed.
        $this->range = ($this->matched_pattern != 'single');
    }
}





class HGVS_DNAPrefix extends HGVS {
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
    }
}





class HGVS_DNAVariantBody extends HGVS {
    public array $patterns = [
        [ 'HGVS_DNAPositions', 'HGVS_DNADel', 'HGVS_DNADelSuffix', [] ],
        [ 'HGVS_DNAPositions', 'HGVS_DNADel', [] ],
    ];
}





class HGVS_ReferenceSequence extends HGVS {
    public array $patterns = [
        [ '/NC_[0-9]{6}\.[0-9]{1,2}/', [] ],
    ];
}





class HGVS_Variant extends HGVS {
    public array $patterns = [
        'DNA' => [ 'HGVS_DNAPrefix', '.', 'HGVS_DNAVariantBody', [] ],
    ];
}
