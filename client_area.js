jQuery(function($) {

    $("#ca_content_area").on("click", ".ca_confirm_switch_event", function() {
        var call = $(this).data("call");
        $("#ca_action_form #ca_action_field").val(call);
        $("#ca_action_form").get(0).submit();

    })

});