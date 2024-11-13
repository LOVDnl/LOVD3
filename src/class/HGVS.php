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
                        // Merge their messages with ours.
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

        $this->matched = $bMatching;
        if (!$bMatching) {
            $this->messages['EFAIL'] = 'Failed to recognize a HGVS nomenclature-compliant variant description in your input.';
        }
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
}





class HGVS_ReferenceSequence extends HGVS {
    public array $patterns = [
        [ '/NC_[0-9]{6}\.[0-9]{1,2}/', [] ],
    ];
}
