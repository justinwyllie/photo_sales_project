(function (jQuery, app) {


  jQuery(function($) {
  
      //TODO don't really need to use delegated events in most cases... except for when i've created
      //elements dynamically - and then you could bind when the element is created?
  
      var criticalErrorMessage = $(".ca_menu_bar").data("critical-error-message") ;
  
  
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
  
  
      $("#ca_content_area").on("click", ".ca_choose_activity_eventOLD", function() {
          var val = $("#ca_activity_choice").val();
          if (val !== "0") {
              $("#ca_action_form #ca_action_field").val(val);
              $("#ca_action_form").get(0).submit();
          }
      })
  
      $("#ca_content_area").on("click", ".ca_login_button_eventOLD", function() {
  
          var username = $("#ca_login_name").val();
          var clientAreaStorage = new ClientAreaStorage(username);
          if (clientAreaStorage.supported) {
              var storedDataProofs = clientAreaStorage.getValueAsString("ca_proofs");
              $("#restoredProofs").val(storedDataProofs);
              var storedDataPrints = clientAreaStorage.getValueAsString("ca_prints");
              $("#restoredPrints").val(storedDataPrints);
              var storedDataProofsPagesVisited = clientAreaStorage.getValueAsString("ca_proofs_pages_visited");
              $("#restoredProofsPagesVisited").val(storedDataProofsPagesVisited);
              var storedDataPrintsPagesVisited = clientAreaStorage.getValueAsString("ca_prints_pages_visited");
              $("#restoredPrintsPagesVisited").val(storedDataPrintsPagesVisited);
          }
          $("#ca_action_field").val("login");
          $("#ca_action_form").get(0).submit();
  
      })
  
   
      $("#ca_content_area").on("click", ".ca_page_number_event", function() {
          var idx = $(this).data("index");
          var mode = $(this).parents('#ca_content_area').find('.ca_menu_bar').data('mode');  //example of the problem with jQuery apps. our data is dependent on our html structure
          if (mode === 'proofs') {
            var sendMode = 'showProofsScreen';
          } else
          {
            var sendMode = 'showPrintsScreen';
          }
          $("#ca_action_field").val(sendMode);
          $("#ca_index_field").val(idx);
          $("#ca_action_form").get(0).submit();
  
      })
  
  
      $("#ca_content_areaXXX").on("click", ".ca_thumb_pic img", function() {  
          var mode =  $(".ca_menu_bar").data("mode");
          if (mode == "prints") {  //TODO temporary hack pending port of proofs to app
              var that = this;  
              app.checkSessionAndRun(function() {
                thumbSelected.call(that, mode);  
              });
          }  else {
                thumbSelected.call(this, mode);
          }
          
       });   
  
  
      $("body").on("click", ".ca_lightbox_close_eventXXX", function() {
          //TODO this is all a bit hacky until we have a single app. (and everything is managed by views in regions by an app region manager)
          var mode =  $(".ca_menu_bar").data("mode");
          if ((mode == "prints") && (typeof(app.basketCollectionView) !== "undefined")) {
                app.closePrintPopUp();
          }
          $(this).closest(".ca_lightbox").remove();
          $("#ca_lightbox_overlay").remove();
      })
  
      $("#ca_content_area").on("click", ".ca_logout_confirm_event", function(evt) {
          var text = $(".ca_menu_bar").data("confirm-logout-message");
          var okText = $(".ca_menu_bar").data("ok-text");
          var cancelText = $(".ca_menu_bar").data("cancel-text");
          var popupContainer = $('<div></div>').addClass("ca_message_pop_up");
          var top = evt.pageY;
          var left = evt.pageX;
          left = left - 200;
          popupContainer.css("top", top + "px");
          popupContainer.css("left", left + "px");
  
          popupContainer.append(  $('<div><button class="ca_popup_close ca_popup_close_event">x</button></div>').
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
  
  
      $("body").on("click", ".ca_logout_eventXXX", function(evt) {
          $(this).closest(".ca_message_pop_up").remove();
          $("#ca_action_field").val("logout");
          $("#ca_action_form").get(0).submit();
  
      });
  
      $("#ca_content_area").on("click", ".ca_proof_cancel_event", function(evt) {
          var idx = $(".ca_menu_bar").data("last-page-visited-index");
          if ( (typeof(idx) === "undefined") || (idx === "")) {
              idx = 0;
          }
          $("#ca_index_field").val(idx);
          $("#ca_action_field").val("showProofsScreen");
          $("#ca_action_form").get(0).submit();
  
  
      });
  
      $("#ca_content_area").on("click", ".ca_prints_checkout_event", function(evt) {
         var allPagesVisited = $(".ca_menu_bar").data("all-prints-pages-visited");
     
      })
  
      $("#ca_content_area").on("click", ".ca_proof_event", function(evt) {
          return;
          var allPagesVisited = $(".ca_menu_bar").data("all-proofs-pages-visited");
          var okText = $(".ca_menu_bar").data("ok-text");
  
          if (allPagesVisited === "yes") {
              var idx = $(".ca_page_info button.ca_highlighted_pagination").data("index");
              $("#ca_index_field").val(idx);
              $("#ca_action_field").val("processProofsConfirm");
              $("#ca_action_form").get(0).submit();
          } else {
              var text = $(".ca_menu_bar").data("check-all-message");
              var popupContainer = $('<div></div>').addClass("ca_message_pop_up");
              var top = evt.pageY;
              var left = evt.pageX;
              left = left - 200;
              popupContainer.css("top", top + "px");
              popupContainer.css("left", left + "px");
  
              popupContainer.append(  $('<div><button class="ca_popup_close ca_popup_close_event">x</button></div>').
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
  
  
      $("body").on("click", ".ca_proof_lightbox_checkbox_event", function() {
          //proofSelected.call(this);
      });
  
      $("#ca_content_area").on("click", ".ca_proof_checkbox_event", function() {
          //proofSelected.call(this);
      });
      
  
                                            
      var thumbSelected = function(mode) {
      
          var ref = $(this).data("file-ref");         
          var picPath = $(".ca_menu_bar").data("url-for-mains") + '&file=' + ref;
          
          var lightBox = $('<div></div>').addClass("ca_lightbox " + "ca_" + mode);
          lightBox.append(  $('<div><button class="ca_popup_close ca_lightbox_close_event">x</button></div>').
              addClass("ca_popup_close_bar")  );
  
          lightBox.append( $('<div></div>').addClass("ca_lightbox_image").
              append( $("<img>").attr("src", picPath)  ));
                                                      
          if (mode === 'proofs') {
            var cb = $(this).parent(".ca_thumb_pic").find('input[type="checkbox"]');
            var ref = cb.val();
            var checkedState = cb.prop("checked");
            var popUpCheckbox = $("<input>").addClass("ca_proof_lightbox_checkbox_event").attr("type", "checkbox").val(ref);
            if (checkedState) {
                popUpCheckbox.prop("checked", true);
            }
            lightBox.append( $('<div></div>').addClass("ca_lightbox_controls").
                append( popUpCheckbox  ));
          }
  
  
          var overLay = $('<div></div>').attr("id", "ca_lightbox_overlay");
  
          var actualImageWidth = $(this).data("image-width");
          var actualImageHeight = $(this).data("image-height"); 
          //TODO - what if same?  
          var ratio = (Math.max(actualImageWidth, actualImageHeight)) / (Math.min(actualImageWidth, actualImageHeight)).toFixed(2);
          ratio = ratio.toFixed(2);
         
    
  
          var viewportWidth = $(window).width();
          var viewportHeight = $(window).height();
          
          var safeImageWidth = viewportWidth - 100;
          var safeImageHeight = viewportHeight - 100;
          var heightAdjuster = 100;      

          var usedImageWidth;
          
          if (mode=="proofs") {   
          
            if ((actualImageWidth > safeImageWidth) && (actualImageHeight <= safeImageHeight)) {
                lightBox.find("img").css({"width": safeImageWidth + ".px", "height": "auto"});
                usedImageWidth = safeImageWidth; 
            } else if  ( (actualImageWidth <= safeImageWidth)  && (actualImageHeight > safeImageHeight)) {
                 lightBox.find("img").css({"height": safeImageHeight + ".px", "width": "auto"});
                 var resizedProportion = safeImageHeight / actualImageHeight;
                 usedImageWidth = Math.round(actualImageWidth *  resizedProportion);
            } else if ((actualImageWidth > safeImageWidth)  && (actualImageHeight > safeImageHeight)) {    
                var widthExceedsRatio = actualImageWidth /  safeImageWidth;
                var heightExceedsRatio = actualImageHeight /  safeImageHeight;
                if (widthExceedsRatio >  heightExceedsRatio) {
                     lightBox.find("img").css({"width": safeImageWidth + ".px", "height": "auto"});
                     usedImageWidth = safeImageWidth; 
                }  else  {
                    lightBox.find("img").css({"height": safeImageHeight + ".px", "width": "auto"});
                    var resizedProportion = safeImageHeight / actualImageHeight;
                    usedImageWidth = Math.round(actualImageWidth *  resizedProportion);
                }
                
            } else {
                 lightBox.find("img").css({"width": actualImageWidth + ".px", "height": "auto"});
                 usedImageWidth = actualImageWidth; 
            }
            
            lightBox.css('min-width', usedImageWidth + 'px'); 
            var offset = Math.floor(usedImageWidth / 2);
            lightBox.css("margin-left", "-" + offset + "px"); 
          
          } 
          else //TODO formatting is messed up   need to fix to use 4 spaces per indent not 2 - or 6
        {    //in this scheme we set an overall width for the popup which is centered - a max of 1600 or 95% on smaller screens; the image is sized to fit inside the pop-up and left-aligned but without interpolating; height is always auto which mean be vertical scrolling  
            var maxPopupWidth = 1600;
            var lightBoxWidthFraction = 0.95;
            var lightBoxWidthPercent = (100*lightBoxWidthFraction)  + '%';
            var lightBoxWidth = maxPopupWidth + 'px'; 
           
            
            lightBox.css({'max-width': lightBoxWidth ,'width': lightBoxWidthPercent}); 
            
            if (actualImageHeight > (safeImageHeight)) //height is too big (portrait images on landscape orientation phones)
            {
                //problem is that we can't set img width auto in case it > than width of containing div 
                //so we have to calculate a new width and set that 
                var heightExceedsRatio = actualImageHeight /  safeImageHeight;
                var modifiedWidth = actualImageWidth /  heightExceedsRatio;
                lightBox.find("img").css({"max-width": Math.floor(modifiedWidth) + "px", "width": "100%"});   
            }
            else
            {
                lightBox.find("img").css({"max-width": Math.floor(actualImageWidth) + "px", "width": "100%", "height": "auto"});
            }

        }
          
          
          
             
          
  
          if (mode === 'prints') {
              lightBox.append('<div id="ca_pricing_area" class="container-fluid"></div>');
          }
                     
          $(".ca_message_pop_up").remove();
          $("body").append(overLay);
          $("body").append(lightBox);
          
          if (mode == 'prints') {                                                             
              renderPricingArea(ref, ratio);
          }
          
          
  
          
          $("body").scrollTop(0);
  
          var labelsOption = $(".ca_menu_bar").data("labels-option");
          if (labelsOption === "on") {
              $(".ca_lightbox_controls").append( $('<div></div>').addClass("ca_lightbox_label").html(ref) );
          }
  
      }
  
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
              if ($(that).hasClass("ca_proof_lightbox_checkbox_event")) {
                  $('.ca_proof_checkbox_event[value="' + fileRef + '"]').prop("checked", checkState);
              }
  
              $(".ca_counter").html(res.numberOfProofs);
              var ca_proofs_pages_visited = $(".ca_menu_bar").data("username");
              var clientAreaStorage = new ClientAreaStorage(username);
  
              if (clientAreaStorage.supported) {
                  if (checkState) {
                      clientAreaStorage.addToStorage("ca_proofs", res.fileRef);
                  } else {
                      clientAreaStorage.removeFromStorage("ca_proofs", res.fileRef);
                  }
              }
  
          }, "json").done(function() {
  
          }).fail(function() {
              criticalError();
          }).always(function() {
  
          });
  
      }
  
     var renderPricingArea = function(ref, ratio) {
          app.showPrintPopUp(ref, ratio);  
     }
  
      var ClientAreaStorage = function(username) {
      
         this.username = username;
  
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
                 
      //onpageload
  
      var pageOn = $("button.ca_thumbs_page");
      var mode = $('.ca_menu_bar').data('mode');
      if (pageOn.length >= 1) {
      
          var username = $(".ca_menu_bar").data("username");
          var clientAreaStorage = new ClientAreaStorage(username);
  
          if (clientAreaStorage.supported) {
              var pageIndex = $(pageOn).filter(".ca_highlighted_pagination").data("index");
              clientAreaStorage.addToStorage("ca_" + mode + "_pages_visited", pageIndex);
          }
  
      }
      
 

    app.init();

  
  });

}(jQuery, caApp));