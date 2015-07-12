jQuery(function($) {

    $("#ca_content_area").on("click", ".ca_confirm_switch_event", function() {
        var call = $(this).data("call");
        $("#ca_action_form #ca_action_field").val(call);
        $("#ca_action_form").get(0).submit();

    })


    $("#ca_content_area").on("click", ".ca_choose_activity_event", function() {
        var val = $("#ca_activity_choice").val();
        if (val !== "0") {
            $("#ca_action_form #ca_action_field").val(val);
            $("#ca_action_form").get(0).submit();
        }
    })

    $("#ca_content_area").on("click", ".ca_page_number", function() {
        var idx = $(this).data("index");
        $("#ca_action_form #ca_index_field").val(idx);
        $("#ca_action_form").get(0).submit();

    })




});