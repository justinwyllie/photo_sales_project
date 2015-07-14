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

    $("#ca_content_area").on("click", ".ca_thumb_pic img", function() {
        var ref = $(this).parent(".ca_thumb_pic").find('input[type="checkbox"]').val();
        var picPath = $(".ca_proofs_bar").data("url-for-mains") + ref;


        var lightBox = $('<div></div>').addClass("ca_lightbox");
        lightBox.append(  $('<div><span class="ca_lightbox_close ca_lightbox_close_event">x</span></span></div>').
            addClass("ca_lightbox_close_bar")  );

        lightBox.append( $('<div></div>').addClass("ca_lightbox_image").
            append( $("<img>").attr("src", picPath)  ));

        lightBox.append( $('<div></div>').addClass("ca_lightbox_controls").
            append( $("<input>").addClass("ca_lightbox_checkbox_event").attr("type", "checkbox").val(ref)  ));


        var overLay = $('<div></div>').attr("id", "ca_lightbox_overlay");

        var actualImageWidth = $(this).data("image-width");
        if (actualImageWidth !== "") {

            var offset = Math.floor(actualImageWidth / 2);
            lightBox.css("margin-left", "-" + offset + "px");
        } else {
            lightBox.css("top", "0px");
            lightBox.css("left", "0px");
        }

        $(".ca_message_pop_up").remove();
        $("body").append(overLay);
        $("body").append(lightBox);

        var labelsOption = $(".ca_proofs_bar").data("labels-option");
        if (labelsOption === "on") {
            $(".ca_lightbox_controls").append( $('<div></div>').addClass("ca_lightbox_label").html(ref) );
        }

        var w = $(".ca_lightbox").width();
        var h = $(".ca_lightbox").height();




    })


    $("body").on("click", ".ca_lightbox_close_event", function() {
        $(this).closest(".ca_lightbox").remove();
        $("#ca_lightbox_overlay").remove();
    })


    $("#ca_content_area").on("click", ".ca_logout_confirm_event", function(evt) {


        var text = $(".ca_proofs_bar").data("confirm-logout-message");
        var okText = $(".ca_proofs_bar").data("okText");
        var cancelText = $(".ca_proofs_bar").data("cancelText");
        var popupContainer = $('<div></div>').addClass("ca_message_pop_up");
        var top = evt.pageY;
        var left = evt.pageX;
        left = left - 200;
        popupContainer.css("top", top + "px");
        popupContainer.css("left", left + "px");

        popupContainer.append(  $('<div><span class="ca_popup_close ca_popup_close_event">x</span></span></div>').
            addClass("ca_popup_close_bar")  );
        popupContainer.append(   $('<div class="ca_popup_text"></div>').html(text) );
        popupContainer.append(   $('<button class="ca_logout_event ca_button_left">' + okText + '</button>') );
        popupContainer.append(   $('<button class="ca_popup_close_event">' + cancelText + '</button>') );

        $(".ca_message_pop_up").remove();
        $("body").append(popupContainer);


    })

    $("body").on("click", ".ca_popup_close_event", function() {
        $(this).closest(".ca_message_pop_up").remove();
    })


    $("body").on("click", ".ca_logout_event", function(evt) {
        $(this).closest(".ca_message_pop_up").remove();
        $("#ca_action_form #ca_action_field").val("logout");
        $("#ca_action_form").get(0).submit();

    });


    $("#ca_content_area").on("click", ".ca_proof_event", function(evt) {

        var allPagesVisited = $(".ca_proofs_bar").data("all-pages-visited");
        var okText = $(".ca_proofs_bar").data("ok-text");

        if (allPagesVisited === "yes") {
            $("#ca_action_form #ca_action_field").val("processProofs");
            $("#ca_action_form").get(0).submit();
        } else {
            var text = $(".ca_proofs_bar").data("check-all-message");
            var popupContainer = $('<div></div>').addClass("ca_message_pop_up");
            var top = evt.pageY;
            var left = evt.pageX;
            left = left - 200;
            popupContainer.css("top", top + "px");
            popupContainer.css("left", left + "px");

            popupContainer.append(  $('<div><span class="ca_popup_close ca_popup_close_event">x</span></span></div>').
                addClass("ca_popup_close_bar")  );
            popupContainer.append(   $('<div class="ca_popup_text"></div>').html(text) );
            popupContainer.append(   $('<button class="ca_popup_close_event">' + okText + '</button>') );
            $(".ca_message_pop_up").remove();
            $("body").append(popupContainer);

        }

    })

    $("body").on("click", ".ca_popup_close_event", function() {
        $(this).closest(".ca_message_pop_up").remove();
    })



    $("#ca_content_area").on("click", ".ca_proof_checkbox_event", function() {
        var fileRef = $(this).val();
        var status = $(this).is(":checked");
        if (status) {
            var fileAction = "add";
        } else {
            var fileAction = "remove";
        }

        var data = {
            action: "ajaxAddRemoveProofImage",
            fileRef : fileRef,
            fileAction : fileAction
        }

        var postUrl = $("#ca_action_form").attr("action");


        var jqxhr = $.post(postUrl, data, function() {
            alert( "success" );
        }).done(function() {
                alert( "second success" );
        }).fail(function() {
                alert( "error" );
        }).always(function() {
                alert( "finished" );
        });

        console.log(jqxhr);

    })




});