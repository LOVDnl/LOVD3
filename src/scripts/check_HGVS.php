<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2021-12-03
 * Modified    : 2022-08-30
 * For LOVD    : 3.0-29
 *
 * Copyright   : 2004-2022 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               L. Werkman <L.Werkman@LUMC.nl>
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
require ROOT_PATH . 'inc-init.php';

if (ACTION || PATH_COUNT > 2) {
    // !URL: /scripts/check_HGVS.php
    // This is not the basic URL. We have an ACTION or additional stuff behind the URL.
    header('Location: ' . lovd_getInstallURL() . $_PE[0] . '/' . $_PE[1]);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <!-- Bootstrap Font Icon CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet">

    <title>HGVS DNA variant description syntax checker</title>
    <BASE href="<?php echo lovd_getInstallURL(); ?>">
</head>
<body class="bg-light">

<div class="container">
    <main>
        <div class="py-5 text-center">
            <h1>HGVS DNA variant description syntax checker</h1>
            <p class="lead">
                Validate the syntax of DNA variant descriptions using this form.
                Our tool checks your description and, when invalid, tries to correct your description into a valid HGVS-compliant description.
                To also validate your variant description on the sequence level, please select the VariantValidator option below the input field.
                This feature requires you to include a reference sequence in your descriptions.
            </p>
        </div>

        <ul class="nav nav-tabs" id="hgvsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="single-variant" data-bs-toggle="tab" data-bs-target="#single" type="button" role="tab" aria-controls="single" aria-selected="true">
                    Check a single variant
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="mutiple-variants" data-bs-toggle="tab" data-bs-target="#multiple" type="button" role="tab" aria-controls="multiple" aria-selected="false">
                    Check a list of variants
                </button>
            </li>
        </ul>

        <div class="tab-content" id="hgvsTabsContent">
                <div class="py-3 tab-pane fade show active" id="single" role="tabpanel">
                    <FORM onsubmit="showResponse('singleVariant'); return false;" action="">
                        <div class="py-2">
                            <input type="text" class="form-control" id="singleVariant" placeholder="NM_002225.3:c.157C>T" value="">
                        </div>
                        <div class="py-2">
                            <input type="checkbox" class="form-check-input" id="singleVariantUseVV">
                            <label class="form-check-label" for="singleVariantUseVV">Besides checking the syntax, also use VariantValidator.org to validate this variant on the sequence level (slower)</label>
                        </div>
                        <div class="py-2">
                            <button class="btn btn-primary" type="submit">Validate this variant description</button>
                        </div>
                    </FORM>
                    <DIV id="py-2 singleVariantResponse"></DIV>
                </div>
                <div class="py-3 tab-pane fade" id="multiple" role="tabpanel">
                    <FORM onsubmit="showResponse('multipleVariants'); return false;" action="">
                        <div class="py-2">
                            <textarea class="form-control" id="multipleVariants" placeholder="NM_002225.3:c.157C>T
NC_000015.9:g.40699840C>T" rows="3"></textarea>
                        </div>
                        <div class="py-2">
                            <input type="checkbox" class="form-check-input" id="multipleVariantsUseVV">
                            <label class="form-check-label" for="multipleVariantsUseVV">Besides checking the syntax, also use VariantValidator.org to validate these variants on the sequence level (slower)</label>
                        </div>
                        <div class="py-2">
                            <button class="btn btn-primary" type="submit">Validate these variant descriptions</button>
                        </div>
                    </FORM>
                    <DIV id="py-2 multipleVariantsResponse"></DIV>
                </div>
        </div>
    </main>
</div>

<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>

<SCRIPT type="text/javascript">
    function showResponse(sMethod)
    {
        $.get("ajax/check_HGVS.php?var=" + encodeURIComponent($("#variant").val()) + "&method=" + sMethod + "&callVV=" + $("#callVV").is(":checked"))
        .fail(function(){alert("Error checking variant, please try again later.");})
        ;
    }



    function downloadResponse()
    {
        var fileContent = "data:text/tab-seperated-values;charset=utf-8,";

        for(var i=0; i<$("#responseTable tr").length; i++){
            row = $("#responseTable tr").eq(i);
            fileContent += encodeURI(row.children().eq(0).text()) + "\t" // variant
                         + encodeURI(row.children().eq(1).children().prop("alt")) + "\t" // isHGVS
                         + encodeURI(row.children().eq(2).text()) + "\t" // fixedVariant
                         + encodeURI(row.children().eq(3).text())        // warnings and errors
                         + (!$("#callVV").is(":checked")? "" :           // result of VariantValidator
                             "\t" + encodeURI(row.children().eq(4).text()))
                         + "\r\n";
        }

        var link = document.createElement("a");
        link.setAttribute("href", fileContent);
        var d = new Date();
        // Offset the timezone.
        d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
        link.setAttribute("download", "LOVD_HGVSCheck_" + d.toISOString().slice(0, 19) + ".txt");
        document.body.appendChild(link);

        link.click();
    }
</SCRIPT>

</body>
</html>
