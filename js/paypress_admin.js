jQuery(document).ready(function($) {
    //dropdown
    if (!$(".paypress_paid").is(":checked")) {
        $(".paypress_options").css("display", "none");
    }
    $(".paypress_paid").click(function() {
        if ($(".paypress_paid").is(':checked')) {
            $(".paypress_options").show("slow");
        } else {
            $(".paypress_options").hide("2000");
        }
    });
    //settings dropdown
    if ($(".paypress_settings_custom_page").attr("checked") != "checked") {
        $("select[name=paypress_custom_page_selection]").attr("disabled", true);
    }
    $("input[name=paypress_thank_you_page]:radio").click(function () {
        if ($(".paypress_settings_custom_page").attr("checked") == "checked") {
            $("select[name=paypress_custom_page_selection]").removeAttr("disabled");
        } else {
            $("select[name=paypress_custom_page_selection]").attr("disabled", true);
        }
    });
    //bulk enable change
    $(".paypress_bulk_settings input[type=checkbox]").attr("disabled", true);
    $(".paypress_bulk_settings input[type=radio]").attr("disabled", true);
    $(".paypress_bulk_settings select").attr("disabled", true);
    
    $(".paypress_enable_change").click(function (){
        $(".paypress_bulk_settings input[name=paypress_bulk_enabled]").attr("value", "true");
        $(".paypress_bulk_settings input[type=checkbox]").attr("disabled", false);
        $(".paypress_bulk_settings input[type=radio]").attr("disabled", false);
        $(".paypress_bulk_settings select").attr("disabled", false);
    });
    
});