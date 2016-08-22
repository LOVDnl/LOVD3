<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-08-03
 * Modified    : 2016-08-22
 * For LOVD    : 3.0-17
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
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

// List of tissues selectable for bein affected by a disease.
// Note: this should be treated as a constant! Although we cannot
// declare an array as a constant in PHP <5.6.
$DISEASE_TISSUES = array(

    'optgroup_brain' => 'Brain',
    'brain' => 'brain',
    'lateral ventricle' => 'lateral ventricle',
    'cerebral cortex' => 'cerebral cortex',
    'cerebellum' => 'cerebellum',
    'hippocampus' => 'hippocampus',

    'optgroup_face' => 'Face',
    'face' => 'face',
    'eyes' => 'eyes',
    'ears' => 'ears',
    'nose' => 'nose',
    'mouth' => 'mouth',

    'optgroup_head_internal' => 'Head (internal)',
    'nasopharynx' => 'nasopharynx',
    'oral mucosa' => 'oral mucosa',
    'salivary gland' => 'salivary gland',
    'tonsil' => 'tonsil',
    'thyroid gland' => 'thyroid gland',
    'parathyroid gland' => 'parathyroid gland',

    'optgroup_limbs' => 'Limbs',
    'shoulders' => 'shoulders',
    'arms' => 'arms',
    'elbows' => 'elbows',
    'wrist' => 'wrist',
    'hands' => 'hands',
    'fingers' => 'fingers',
    'legs' => 'legs',
    'knees' => 'knees',
    'ankles' => 'ankles',
    'feet' => 'feet',
    'toes' => 'toes',

    'optgroup_trunk_internal' => 'Trunk (internal)',
    'spine' => 'spine',
    'hips' => 'hips',
    'bronchus' => 'bronchus',
    'lung' => 'lung',
    'esophagus' => 'esophagus',
    'heart muscle' => 'heart muscle',
    'lymph node' => 'lymph node',
    'stomach' => 'stomach',
    'liver' => 'liver',
    'adrenal gland' => 'adrenal gland',
    'spleen' => 'spleen',
    'kidney' => 'kidney',
    'gallbladder' => 'gallbladder',
    'pancreas' => 'pancreas',
    'duodenum' => 'duodenum',
    'small intestine' => 'small intestine',
    'colon' => 'colon',
    'appendix' => 'appendix',
    'urinary bladder' => 'urinary bladder',
    'rectum' => 'rectum',

    'optgroup_feminine' => 'Feminine tissues',
    'breast' => 'breast',
    'placenta' => 'placenta',
    'fallopian tube' => 'fallopian tube',
    'ovary' => 'ovary',
    'uterus' => 'uterus',
    'cervix, uterine' => 'cervix, uterine',
    'vagina' => 'vagina',

    'optgroup_masculine' => 'Masculine tissues',
    'seminal vesicle' => 'seminal vesicle',
    'prostate' => 'prostate',
    'epididymis' => 'epididymis',
    'testis' => 'testis',

    'optgroup_other' => 'Other',
    'skeletal muscle' => 'skeletal muscle',
    'smooth muscle' => 'smooth muscle',
    'hair' => 'hair',
    'skin' => 'skin',
    'bone marrow' => 'bone marrow',
    'bones (skeleton)' => 'bones (skeleton)',
    'soft tissue' => 'soft tissue',
);

