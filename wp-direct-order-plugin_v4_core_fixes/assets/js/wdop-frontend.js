
jQuery(document).ready(function($) {
    // Function to update commune options
    function updateCommunes(wilayaCode, communeSelect) {
        const selectCommuneText = wdop_params.select_commune_text || "Select Commune";
        const loadingText = wdop_params.loading_text || "Loading...";
        const noCommunesFoundText = wdop_params.no_communes_found_text || "No communes found";
        const errorLoadingText = wdop_params.error_loading_text || "Error loading communes";
        const selectStateFirstText = wdop_params.select_state_first_text || "Select State First";

        if (!wilayaCode) {
            communeSelect.html("<option value=\"\">" + selectStateFirstText + "</option>");
            communeSelect.prop("disabled", true);
            return;
        }

        communeSelect.prop("disabled", true);
        communeSelect.html("<option value=\"\">" + loadingText + "</option>");

        $.ajax({
            url: wdop_params.ajax_url,
            type: "POST",
            data: {
                action: "wdop_get_communes",
                nonce: wdop_params.nonce,
                wilaya_code: wilayaCode
            },
            dataType: "json",
            success: function(response) {
                communeSelect.empty();
                if (response.success) {
                    communeSelect.append("<option value=\"\">" + selectCommuneText + "</option>");

                    if (response.data && response.data.communes && Object.keys(response.data.communes).length > 0) {
                        $.each(response.data.communes, function(key, value) {
                            communeSelect.append(
                                $("<option></option>").attr("value", key).text(value)
                            );
                        });
                        communeSelect.prop("disabled", false);
                    } else {
                        communeSelect.append("<option value=\"\">" + noCommunesFoundText + "</option>");
                        communeSelect.prop("disabled", true);
                    }
                } else {
                    console.error("Error fetching communes:", response.data ? response.data.message : "Unknown error");
                    communeSelect.html("<option value=\"\">" + errorLoadingText + "</option>");
                    communeSelect.prop("disabled", true);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX error:", textStatus, errorThrown);
                communeSelect.html("<option value=\"\">" + errorLoadingText + "</option>");
                communeSelect.prop("disabled", true);
            }
        });
    }

    // Event listener for Wilaya select change
    $("body").on("change", "#wdop_wilaya", function() {
        var selectedWilaya = $(this).val();
        var communeSelect = $("#wdop_commune");

        if (communeSelect.length) {
            updateCommunes(selectedWilaya, communeSelect);
        }
    });

    // Initial state for commune dropdown
    var communeSelectInitial = $("#wdop_commune");
    if (communeSelectInitial.length && !$("#wdop_wilaya").val()) {
        communeSelectInitial.prop("disabled", true);
        const selectStateFirstText = wdop_params.select_state_first_text || "Select State First";
        communeSelectInitial.html("<option value=\"\">" + selectStateFirstText + "</option>");
    }

    // Form validation for shipping method
    $("body").on("submit", ".wdop-order-form", function() {
        const selectShippingText = wdop_params.select_shipping_text || "Please select a shipping method.";
        var shippingList = $("#wdop-shipping-method-list");

        if (shippingList.length && shippingList.find("input.shipping_method[required]").length > 0) {
            if (shippingList.find("input[name=\"wdop_shipping_method\"]:checked").length === 0) {
                alert(selectShippingText);
                return false;
            }
        }
    });
});