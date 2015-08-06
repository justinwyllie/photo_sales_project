jQuery(function($) {

    //load dependencies


    //TODO don't really need to use delegated events in most cases... except for when i've created
    //elements dynamically - and then you could bind when the element is created?

    var criticalErrorMessage = $(".ca_proofs_bar").data("critical-error-message") ;


    var redirectToLoginScreen = function() {
        //this repeats the last page action whether it was a post or get
        location.reload(true);
    }

    var criticalError = function() {
        $(".ca_message_area").prepend( $("<span></span>").addClass("ca-error").html(criticalErrorMessage)    );
    }

    $("#ca_content_area").on("click", ".ca_confirm_switch_event", function() {
        var call = $(this).data("call");
        $("#ca_action_field").val(call);
        $("#ca_action_form").get(0).submit();

    })


    $("#ca_content_area").on("click", ".ca_choose_activity_event", function() {
        var val = $("#ca_activity_choice").val();
        if (val !== "0") {
            $("#ca_action_form #ca_action_field").val(val);
            $("#ca_action_form").get(0).submit();
        }
    })

    $("#ca_content_area").on("click", ".ca_login_button_event", function() {

        var username = $("#ca_login_name").val();
        var clientAreaStorageProofs = new ClientAreaStorageProofs(username);
        if (clientAreaStorageProofs.supported) {
            var storedDataProofs = clientAreaStorageProofs.getValueAsString("ca_proofs");
            $("#restoredProofs").val(storedDataProofs);
            var storedDataProofsPagesVisited = clientAreaStorageProofs.getValueAsString("ca_proofs_pages_visited");
            var storedDataPrintsPagesVisited = clientAreaStorageProofs.getValueAsString("ca_prints_pages_visited");
            $("#restoredProofsPagesVisited").val(storedDataProofsPagesVisited);
            $("#restoredPrintsPagesVisited").val(storedDataPrintsPagesVisited);
        }
        $("#ca_action_field").val("login");
        $("#ca_action_form").get(0).submit();

    })




    $("#ca_content_area").on("click", ".ca_page_number_event", function() {
        var idx = $(this).data("index");
        $("#ca_action_field").val("showProofsScreen");
        $("#ca_index_field").val(idx);
        $("#ca_action_form").get(0).submit();

    })


    $("#ca_content_area").on("click", ".ca_thumb_pic img", function() {
        var cb = $(this).parent(".ca_thumb_pic").find('input[type="checkbox"]');
        var ref = cb.val();
        var checkedState = cb.prop("checked");

        var picPath = $(".ca_proofs_bar").data("url-for-mains") + ref;


        var lightBox = $('<div></div>').addClass("ca_lightbox");
        lightBox.append(  $('<div><span class="ca_lightbox_close ca_lightbox_close_event">x</span></span></div>').
            addClass("ca_lightbox_close_bar")  );

        lightBox.append( $('<div></div>').addClass("ca_lightbox_image").
            append( $("<img>").attr("src", picPath)  ));

        var popUpCheckbox = $("<input>").addClass("ca_lightbox_checkbox_event").attr("type", "checkbox").val(ref);

        if (checkedState) {
            popUpCheckbox.prop("checked", true);
        }

        lightBox.append( $('<div></div>').addClass("ca_lightbox_controls").
            append( popUpCheckbox  ));


        var overLay = $('<div></div>').attr("id", "ca_lightbox_overlay");

        var actualImageWidth = $(this).data("image-width");

        var viewportWidth = $(window).width();
        var safeImageWidth = viewportWidth - 100;

        if ((actualImageWidth === "") || (actualImageWidth > safeImageWidth) ) {
            lightBox.find("img").css({"width": safeImageWidth + ".px", "height": "auto"});
            var usedImageWidth = safeImageWidth;
        } else {
            var usedImageWidth = actualImageWidth;
        }

        if (viewportWidth <= 767) {
            lightBox.css("top", "0px");
            lightBox.css("left", "0px");
        } else {
            var offset = Math.floor(usedImageWidth / 2);
            lightBox.css("margin-left", "-" + offset + "px");
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
        $("#ca_action_field").val("logout");
        $("#ca_action_form").get(0).submit();

    });

    $("#ca_content_area").on("click", ".ca_proof_cancel_event", function(evt) {
        var idx = $(".ca_proofs_bar").data("last-page-visited-index");
        if ( (typeof(idx) === "undefined") || (idx === "")) {
            idx = 0;
        }
        $("#ca_index_field").val(idx);
        $("#ca_action_field").val("showProofsScreen");
        $("#ca_action_form").get(0).submit();


    });



    $("#ca_content_area").on("click", ".ca_proof_event", function(evt) {

        var allPagesVisited = $(".ca_proofs_bar").data("all-pages-visited");
        var okText = $(".ca_proofs_bar").data("ok-text");

        if (allPagesVisited === "yes") {
            var idx = $(".ca_page_info span.ca_highlighted_pagination").data("index");
            $("#ca_index_field").val(idx);
            $("#ca_action_field").val("processProofsConfirm");
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
    });


    $("body").on("click", ".ca_lightbox_checkbox_event", function() {
        proofSelected.call(this);
    });

    $("#ca_content_area").on("click", ".ca_proof_checkbox_event", function() {
        proofSelected.call(this);
    });



    var proofSelected =  function() {
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
        var that = this;


        var jqxhr = $.post(postUrl, data, function(res) {
            if ((typeof(res.redirect)) !== "undefined" && (res.redirect)) {
                redirectToLoginScreen();
                return;
            }

            var checkState = res.checkboxOn;

            $(that).prop("checked", checkState);
            if ($(that).hasClass("ca_lightbox_checkbox_event")) {
                $('.ca_proof_checkbox_event[value="' + fileRef + '"]').prop("checked", checkState);
            }

            $(".ca_counter").html(res.numberOfProofs);
            var clientAreaStorageProofs = new ClientAreaStorageProofs(username);

            if (clientAreaStorageProofs.supported) {
                if (checkState) {
                    clientAreaStorageProofs.addToStorage("ca_proofs", res.fileRef);
                } else {
                    clientAreaStorageProofs.removeFromStorage("ca_proofs", res.fileRef);
                }
            }

        }, "json").done(function() {

        }).fail(function() {
            criticalError();
        }).always(function() {

        });


    }





    //TODO can you make this abstract?!
    var ClientAreaStorage = function() {

        if ( (JSON && typeof JSON.parse === 'function') && (typeof(Storage) !== "undefined") &&
            (typeof(Array.prototype.indexOf) === "function")) {
            this.supported = true;
        } else {
            this.supported = false;
        }

    }

    ClientAreaStorage.prototype.getUserKey = function(key)
    {
        return this.username  + "_" + key;
    }

    ClientAreaStorage.prototype.getValueAsArray = function(key) {
        var storedString = localStorage.getItem(this.getUserKey(key));
        if ((storedString === null) || (storedString === "")) {
            return new Array();
        }
        var storedArray = $.parseJSON(storedString);
        return storedArray;
    }

    ClientAreaStorage.prototype.getValueAsString = function(key) {
        var storedString = localStorage.getItem(this.getUserKey(key));
        return storedString;
    }

    ClientAreaStorage.prototype.setValueFromArray = function(key, data) {
        var dataAsString = JSON.stringify(data);
        localStorage.setItem(this.getUserKey(key), dataAsString);
    }

    ClientAreaStorage.prototype.addToStorage = function(key, value) {

        if (!this.supported) {
            return;
        }

        var storedData = this.getValueAsArray(key);
        if (storedData.indexOf(value) >= 0) {
            return;
        } else {
            storedData.push(value);
        }
        this.setValueFromArray(key, storedData);

    }

    ClientAreaStorage.prototype.removeFromStorage = function(key, value) {

        if (!this.supported) {
            return;
        }

        var storedData = this.getValueAsArray(key);

        var pos = storedData.indexOf(value);
        if (pos >= 0) {
            storedData.splice(pos, 1);
        }
        this.setValueFromArray(key, storedData);

    }

    if (typeof Object.create !== 'function') {
        Object.create = function (o) {
            function F() {}
            F.prototype = o;
            return new F();
        };
    }

    //ca_proofs, ca_proofs_pages_visited
    var ClientAreaStorageProofs = function(username) {
        this.username = username;
        ClientAreaStorage.call(this);
    }

    ClientAreaStorageProofs.prototype = Object.create(ClientAreaStorage.prototype);
    ClientAreaStorageProofs.prototype.constructor = ClientAreaStorageProofs;


    //onpageload TODO

    var pageOn = $("span.ca_proofs_page");
    if (pageOn.length >= 1) {

        var username = $(".ca_proofs_bar").data("username");
        var mode = $(".ca_proofs_bar").data("mode");
        var clientAreaStorageProofs = new ClientAreaStorageProofs(username);

        if (clientAreaStorageProofs.supported) {
            var pageIndex = $(pageOn).filter(".ca_highlighted_pagination").data("index");
            clientAreaStorageProofs.addToStorage("ca_" + mode + "_pages_visited", pageIndex);
        }

    }

    //prints:

    if ($(".ca_proofs_bar").data("mode") === "prints" ) {
        alert("make an ajax call to get the thumbs");
    }


});
