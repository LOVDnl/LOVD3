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
                            <label class="form-check-label mx-2" for="singleVariantUseVV">Besides checking the syntax, also use VariantValidator.org to validate this variant on the sequence level (slower)</label>
                        </div>
                        <div class="py-2">
                            <button class="btn btn-primary" type="submit" id="singleVariantButton">Validate this variant description</button>
                        </div>
                    </FORM>
                    <DIV class="py-2" id="singleVariantResponse"></DIV>
                </div>
                <div class="py-3 tab-pane fade" id="multiple" role="tabpanel">
                    <FORM onsubmit="showResponse('multipleVariants'); return false;" action="">
                        <div class="py-2">
                            <textarea class="form-control" id="multipleVariants" placeholder="NM_002225.3:c.157C>T
NC_000015.9:g.40699840C>T" rows="3"></textarea>
                        </div>
                        <div class="py-2">
                            <input type="checkbox" class="form-check-input" id="multipleVariantsUseVV">
                            <label class="form-check-label mx-2" for="multipleVariantsUseVV">Besides checking the syntax, also use VariantValidator.org to validate these variants on the sequence level (slower)</label>
                        </div>
                        <div class="py-2">
                            <button class="btn btn-primary" type="submit" id="multipleVariantsButton">Validate these variant descriptions</button>
                        </div>
                    </FORM>
                    <DIV class="py-2" id="multipleVariantsResponse"></DIV>
                </div>
        </div>
    </main>
</div>

<script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha384-tsQFqpEReu7ZLhBV2VZlAu7zcOV+rXbYlF2cqB8txI/8aZajjp4Bqd+V6D5IgvKT" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>

<SCRIPT type="text/javascript">
    // Disable buttons when there is nothing to submit.
    $("#hgvsTabsContent").find(
        "input[type=text], textarea"
    ).keyup(
        function ()
        {
            if ($(this).val() == '') {
                $(this).parents('form').find('button').prop('disabled', true);
            } else {
                $(this).parents('form').find('button').prop('disabled', false);
            }
        }
    ).keyup();

    // Disable buttons when clicked and indicate the process is loading.
    $("#hgvsTabsContent").find("button").click(
        function ()
        {
            $(this).parents("form").submit();
            $(this).prop('disabled', true).append('\n&nbsp;\n<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
            return true;
        }
    );



    function showResponse(sMethod)
    {
        // This function sends the data over to the ajax script, formats, and displays the response.
        if (sMethod == undefined || $("#" + sMethod) == null) {
            alert("showResponse() called with an incorrect method.");
            return false;
        }

        var sInput = $("#" + sMethod).val();
        var bCallVV = $("#" + sMethod + "UseVV").is(":checked");
        $.getJSON(
            "ajax/check_HGVS.php?var=" + encodeURIComponent(sInput) + "&callVV=" + bCallVV,
            function (data)
            {
                // If we get here, the JSON was already parsed, and we know it was successful.
                // We should have received an object with variants as keys, and their results as the value.

                // Empty previous result.
                $("#" + sMethod + "Response").html("");

                // Loop through the results.
                $.each(
                    data,
                    function (sVariant, aVariant)
                    {
                        // Style used, icon used?
                        var sStyle = (aVariant.color == 'green'? 'success' : aVariant.color == 'orange'? 'warning' : 'danger');
                        var sIcon = (aVariant.is_hgvs == null? 'question' : aVariant.is_hgvs? 'check' : 'x') + '-circle-fill';

                        // What's in the body?
                        var sBody = '<ul class="list-group list-group-flush">';
                        if (aVariant.is_hgvs == null) {
                            sBody +=
                                '<li class="list-group-item list-group-item-' + sStyle + ' d-flex"><i class="bi bi-' + sIcon + ' me-2"></i><div>' +
                                ' This variant description contains unsupported syntax.' +
                                ' Although we aim to support all of the HGVS nomenclature rules,' +
                                ' some complex variants are not fully implemented yet in our syntax checker.</div></li>\n';
                        } else if (!aVariant.is_hgvs) {
                            sBody +=
                                '<li class="list-group-item list-group-item-' + sStyle + ' d-flex"><i class="bi bi-' + sIcon + ' me-2"></i><div>' +
                                ' This variant description is invalid.</div></li>\n';
                        } else {
                            sBody +=
                                '<li class="list-group-item list-group-item-' + sStyle + ' d-flex"><i class="bi bi-' + sIcon + ' me-2"></i><div>' +
                                ' This variant description\'s syntax is valid.</div></li>\n';
                            if (!bCallVV) {
                                sBody +=
                                    '<li class="list-group-item list-group-item-warning d-flex"><i class="bi bi-exclamation-circle-fill me-2"></i><div>' +
                                    ' This variant has not been validated on the sequence level.' +
                                    ('WNOTSUPPORTED' in aVariant.variant_info.warnings?
                                        ' This variant description is not currently supported for sequence-level validation.' :
                                        ' For sequence-level validation, please select the VariantValidator option.') +
                                    '</div></li>\n';
                            }
                        }
                        sBody += '</ul>';

                        $("#" + sMethod + "Response").append(
                            '\n' +
                            '<div class="card w-100 mb-3 border-' + sStyle + '">\n' +
                              '<div class="card-header border-' + sStyle + ' bg-' + sStyle + ' text-white">\n' +
                                '<h5 class="card-title mb-0"><i class="bi bi-' + sIcon + '"></i> ' + sVariant + '</h5>\n' +
                              '</div>\n'
                              + sBody + '\n' +
                            '</div>'
                        );
                    }
                );

                // Reset button.
                $("#" + sMethod + "Button").html(
                    $("#" + sMethod + "Button").html().replace("&nbsp;", "").trim()
                ).prop("disabled", false).find("span").remove();

                return true;
            }
        ).fail(
            function()
            {
                alert("Error checking variant, please try again later.");
            }
        );
        return false;
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
