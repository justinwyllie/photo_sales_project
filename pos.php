<?php


//TODOs
// - clear basket when log out                                                                            
//test different currencies
//all text must have one of 3 pos text classes on it


//TEST PLAN
//various currecny figures e.g. whole numbers, decimals etc
//do they store in basket correctly?

/* issue of what happens when owner updates prices and someone is shopping


the xml is updated
the price display window gets the new price
any new additions to the basket take this new price
however - the basket display takes its prices from the basket, so once
an item is in the basket its price is fixed - even if the backend changes
however; if the shopper then updates the qty of that item in the display window
this uses addItem (notUPdate item) and so sets the new price into the basket

long and short; once its in the basket the price is fixed so long as the user doesn't re-add / update it


*/
           
        
session_start();
//var_dump($_SESSION['userId']);

$pos = new pos();


if (isset($_GET['action']) )
{
    $action = $_GET['action'];
    
    if (isset($_SESSION['userId']))  //false if session has been destroyed 
    {
        $pos->$action();
    }
    else      
    {         

        if ($action == 'login')
        {
             $pos->login();
        }        
        else
        {   //case: user is trying to call an action but their session has expired
            $pos->showLoginScreen();
        }           
        
    }

}
else
{
//this is called from a page load - either a refresh or initial load
//either way we need to load the app.
//if they are logged in we can go straight to thumbs, if not the loginscreen

    if (isset($_SESSION['userId']))
    {                             
        $pos->main('showThumbs');
    }
    else
    {
        $pos->main('showLoginScreen');
    }

}




class pos
{

    private $xml;
    private $client;

    public function __construct() {

        if (file_exists('pos.xml')) {

            $xml = simplexml_load_file('pos.xml');

            if (!$xml) {
                $this->err("XML file has errors");
            }
   
        } 
        else 
        {
              $this->err("Missing XML file");
        }


        $this->xml = $xml;


    }

    public function login() {
                                                   
            $client = $this->xml->xpath("clients/client[@password='".$_GET['user_id']."']")   ;
            if (empty($client)) {
                  $this->showLoginScreen("Username not found");
            }  
            else
            {

                if (count($client) > 1) {
                    $this->err("Client identifiers not unique. Check your XML") ;
                }
                $_SESSION['userId'] = $_GET['user_id'];
                $this->setClient($_GET['user_id']);
                $_SESSION['path'] = $this->client->attributes()->directory . '';
                $_SESSION['thumbLongestSide'] = $this->xml->xpath("options/thumbLongestSide") . "";
                $this->showThumbs();
            }
 
    }

    public function logout() {
           
        session_unset();
        $this->showLoginScreen();                                       

    }



    public function main($actionOnLoad = "showLoginScreen") {

        /*
            The XML parser converts the encoded character into its UTF-8 string value
            Here we now convert it to an html entity

        */
        $curr = htmlentities((string) $this->xml->paypal->currencySymbol, ENT_COMPAT , "UTF-8");

             
                                  
$js = <<<EOT
          <script type="text/javascript">
                jQuery(function($) {

                    

                    function hasSessionStorage() {
                        if ( ('sessionStorage' in window) && ( window['sessionStorage'] !== null) ) {
                            return true;
                        }  
                        else {
                            return false;
                        }
                    }
                          

                    $.fn.posFirstSelect = function() {
                        this.each(
                            function() {
                                $(this).removeAttr('selected');
                            }
                        );

                        this.find("option").filter(":first").attr('selected','selected');
                    }


                    function Shop()
                    {
                        this.currencySymbol = "$curr";
                 
                    }


                    function Basket()
                    {
                        this.prints = {};
                        this.framed_prints = {};
                    }

                    Basket.prototype = new Shop();
                    Basket.prototype.constructor = Basket;

  
                    Basket.prototype.addItem = function(itemType, imageFile, item, option, price, optionValue, qty) {
                        console.log(itemType, imageFile, item, price, option, qty);
                        if (typeof(this[itemType][imageFile]) == "undefined") {
                            this[itemType][imageFile] = {};
                        }
                        if (typeof(this[itemType][imageFile][item]) == "undefined") {
                            this[itemType][imageFile][item] = {};
                        }
                        this[itemType][imageFile][item]["price"] = price*1;
                        this[itemType][imageFile][item]["option"] = option;
                        this[itemType][imageFile][item]["qty"] = qty;
                        if (itemType == "prints") {
                            optionValue = optionValue*1;
                        }
                        this[itemType][imageFile][item]["optionValue"] = optionValue;
                          console.log(this);
                    }


                    Basket.prototype.updateItem = function(itemType, imageFile, item, option, qty)
                    {
                        if (typeof(this[itemType][imageFile]) == "undefined") {
                            return;
                        }
                        if (typeof(this[itemType][imageFile][item]) == "undefined") {
                            return;
                        }

                        this[itemType][imageFile][item]["option"] = option;
                        this[itemType][imageFile][item]["qty"] = qty;

                    }


                    Basket.prototype.removeItem = function(itemType, imageFile, item)
                    {
                        if (typeof(this[itemType][imageFile]) == "undefined") {
                            return;
                        }
                        if (typeof(this[itemType][imageFile][item]) == "undefined") {
                            return;
                        }
                        delete this[itemType][imageFile][item]    ;
                        var i = 0;
                        for (var member in this[itemType][imageFile] )  {
                            i++;
                        }
                        if (i < 1) {
                            delete this[itemType][imageFile] ;    
                        }
                         
                        
                    }

                    Basket.prototype.rePopulate = function(savedBasket) {
                        this.prints = savedBasket.prints;
                        this.framed_prints = savedBasket.framed_prints;
                    }


                    Basket.prototype.getPrice = function(itemType, imageFile, item)
                    {
                        if (typeof(this[itemType][imageFile]) == "undefined") {
                            return;
                        }
                        if (typeof(this[itemType][imageFile][item]) == "undefined") {
                            return;
                        }

                        return this[itemType][imageFile][item]["price"] ;

                    }

                    Basket.prototype.getOptionValue = function(itemType, imageFile, item)
                    {
                        if (typeof(this[itemType][imageFile]) == "undefined") {
                            return;
                        }
                        if (typeof(this[itemType][imageFile][item]) == "undefined") {
                            return;
                        }

                        return this[itemType][imageFile][item]["optionValue"] ;

                    }

                    Basket.prototype.removeItemsWithZeroQty = function() {
                        var basket = this;
                        $.each(basket.prints, function(printUnframed, order) {
                            $.each(basket.prints[printUnframed], function(size, details) {   
                                if (details.qty == 0)   {
                                    basket.removeItem("prints", printUnframed, size )  ;
                                }
                            });
                        }  );

                        $.each(basket.framed_prints, function(printFramed, order) {
                            $.each(basket.prints[printFramed], function(size, details) {   
                                if (details.qty == 0)   {
                                    basket.removeItem("framed_prints", printFramed, size )  ;
                                }
                            } );
                        }  );
                    }

                    Basket.prototype.getTotals = function() {

                        var result = {};
                        result.prints = 10;
                        result.framed_prints = 15;
                        result.delivery = 5;
                        result.grand_total = 30;

                        var prints_qty = 0;
                        var framed_prints_qty = 0;

                        $.each(basket.prints, function(printUnframed, order) {
                            $.each(basket.prints[printUnframed], function(size, details) {   
                                prints_qty = prints_qty + (details.qty*1);
                            });
                        });

                        $.each(basket.framed_prints, function(printFramed, order) {
                            $.each(basket.framed_prints[printFramed], function(size, details) {   
                                framed_prints_qty = framed_prints_qty + (details.qty*1);
                            });
                        });

                        result.prints_qty = prints_qty;
                        result.framed_prints_qty = framed_prints_qty;



                        return result;


                    }
                    

                    var basket = new Basket();

                    console.log(basket);



                    function Pos(el) {
                            this.currencySymbol = "$curr";
                            this.currentIndex = 0;
                            this.el =  el ;
                            this.width = this.el.width();
                            this.height = this.el.height();
                            this.top = Math.round(this.el.offset().top);        
                            this.left =   Math.round(this.el.offset().left);
                            this.regions = {};
                            this.regions.menu = this.el.find("#pos_menu_region");
                            this.regions.header = this.el.find("#pos_header_region");
                            this.regions.content = this.el.find("#pos_content_region");
                            this.regions.footer = this.el.find("#pos_footer_region");
                          
                    }

                    Pos.prototype.getHtml = function(data, callback) {
                         that = this;
                         data.width = this.width;
                         data.height = this.height;
                         $.ajax({
                                    url: "pos.php",
                                    type: "GET",
                                    data: data,
                                    dataType: "json",
                                    success: function(result) {
                                            $.each(result,function(region, html) {
                                                that.regions[region].html(html);
                                            });
                                                                
                                            if (typeof(callback) != "undefined")
                                            {
                                                callback();    
                                            }
                                           
                                    },
                                    error: function() {
                                        alert('error TODO');
                                    }
                            })
                    }

                    Pos.prototype.loadOrder = function(basket, imageFile)  {

                        if (typeof(basket.prints[imageFile]) == "object") {
                            $.each(basket.prints[imageFile], function(key, data) {
                                var selector =  'table.pos_print_options tr[data-print_order=\''+key+'\']';
                                var orderLine = $(selector);                            
                                orderLine.find(".pos_qty").val(data.qty); 
                                orderLine.find('select.pos_mounted_select option[data-option="'+data.option+'"]').
                                    attr("selected", "selected");
                                if (data.option=="mounted") {
                                    var basePrice = orderLine.find("td.pos_price").html() * 1;
                                    var mountedAdditionalCost = orderLine.find("td select.pos_mounted_select").data("mounted_additional_cost") * 1;
                                    var displayPrice = Number(basePrice + mountedAdditionalCost).toFixed(2);
                                    orderLine.find("td.pos_price").html(displayPrice)   ;
                                }
                                orderLine.addClass("pos_active_order_line");   
    
                            }) ;
                        }
                        if (typeof(basket.framed_prints[imageFile]) == "object") {
                            $.each(basket.framed_prints[imageFile], function(key, data) {
                                var selector =  'table.pos_frame_options tr[data-framed_print_order=\''+key+'\']';
                                var orderLine = $(selector);                            
                                orderLine.find(".pos_qty").val(data.qty); 
                                orderLine.find('select.pos_frame_style option[data-option="'+data.option+'"]').
                                    attr("selected", "selected");
                                orderLine.addClass("pos_active_order_line");   
    
                            }) ;
                         }

                    }


                    var  pos = new Pos($('#pos_main_area'));

                    //events 

                    $('body').on("click", '#pos_login_action', function() {
                            var userId = $('#pos_userid').val();
                            pos.getHtml({"action": "login", "user_id": userId}, "header");

                    });

                    $('body').on("click", ".pos_logout_action", function() {
                          pos.getHtml({"action": "logout"}); 
                    })

                    $('body').on("click", '.pos_pagination:not(.pos_highlighted_pagination)', function() {
                            var index = $(this).data("index");
                            pos.currentIndex   = index;
                            pos.getHtml({"action": "showThumbs", "index" : index }); 
                            

                    });

                   $('body').on("click", '.pos_thumb_image', function() {
                            var img = $(this).data("image");
                            var controls_prints =  $('<div></div>').attr("id", "pos_controls_prints")
                                .addClass("pos_controls_prints pos_controls pos_small_text")  ;
                            var controls_frames =  $('<div></div>').attr("id", "pos_controls_frames")
                                .addClass("pos_controls_frames  pos_controls  pos_small_text")  ;

                            pos.regions.controls_prints =  controls_prints;
                            pos.regions.controls_frames = controls_frames;
                           

                 
                            var display = $("<div></div>").attr("id", "pos_display").
                                css("width", pos.width + "px").css("height" , pos.height + "px").
                                css("position", "absolute").
                                css("z-index", "100").
                                css("top", pos.top + "px").
                                css("left", pos.left + "px").
                                css("background-color", "#ffffff");

                            var src = $(this).attr("src");
                            src = src.replace("thumb", "main");
                            src = src + "&width=" + pos.width + "&height=" + pos.height;
                            var largePic = $("<img/>").attr("src", src )  ;

                            var largePicHolder = $("<div></div>").addClass("pos_display_image");
                            var containerWidth = Math.floor(pos.width * 0.95); //TODO share with posGetImage
                            var containerHeight =  Math.floor(pos.height * 0.7);
                            largePicHolder.css("width", containerWidth + ".px");
                            largePicHolder.css("height", containerHeight + ".px");

                            controls_prints.css("height", Math.floor((0.3 * pos.height) - 20) + ".px");
                            controls_frames.css("height", Math.floor((0.3 * pos.height) - 20) + ".px");

                            largePicHolder.append(largePic); 
                            
                            var closeBox = $('<div></div>').addClass("pos_close").html("x");

                            var printsTab =  $('<div></div>').attr("id", "pos_prints_tab").addClass("pos_active_tab pos_tabs").html("Prints");
                            var framesTab =  $('<div></div>').attr("id", "pos_frames_tab").addClass("pos_tabs").html("Framed Prints");
                           
                            var tabs = $('<div></div>').addClass('pos_tabs_panel');
                            tabs.append(printsTab);
                            tabs.append(framesTab);

                            display.append(closeBox);
                            display.append(largePicHolder);
                            display.append(tabs);
                            display.append(controls_prints);
                            display.append(controls_frames);

                             pos.getHtml({"action": "frameOptions", "image" : img }, function() {
                                $("body").append(display);
                                pos.loadOrder(basket, img);
                            });
             
                            

                    });

                    $("body").on("click", "#pos_prints_tab", function() {
                        $("#pos_controls_prints").show();
                        $("#pos_controls_frames").hide();
                        $(this).addClass("pos_active_tab");
                        $("#pos_frames_tab").removeClass("pos_active_tab"); 
                    });

                    $("body").on("click", "#pos_frames_tab", function() {
                        $("#pos_controls_frames").show();
                        $("#pos_controls_prints").hide();
                        $(this).addClass("pos_active_tab");
                        $("#pos_prints_tab").removeClass("pos_active_tab"); 

                        $("table.pos_frame_options tr select.pos_frame_style.pos_alert").closest("tr").
                            find(".pos_qty").val("");  
                        $("table.pos_frame_options tr select.pos_frame_style.pos_alert").removeClass("pos_alert");
                        


                    });

                    $("body").on("click", ".pos_close", function() {
                         $("#pos_display").remove();
                                      
                    });


                   $("body").on("click", ".pos_print_update_action", function() {
                        var orderLine = $(this).closest("tr");
                        var orderTable = $(this).closest("table");
                        var imageFile = orderTable.data("file_name");
                        var item = orderLine.data("print_order");
                        var optionSelect = orderLine.find(".pos_mounted_select");
                        var optionChosen = optionSelect.find(":selected");
                        var option = optionChosen.data("option");
                        var price = optionSelect.data("unmounted_price");
                        var qty = orderLine.find(".pos_qty").val();
                        var  optionValue =   Number(optionSelect.data("mounted_additional_cost")).toFixed(2);
          

                        if ((qty == "0") || (qty == "")) {
                            orderLine.removeClass("pos_active_order_line");    
                        }
                        else {
                            orderLine.addClass("pos_active_order_line");  
                        }

                        basket.addItem("prints", imageFile, item, option, price, optionValue, qty);
                          
                    });

                   $("body").on("click", ".pos_framed_print_update_action", function() {
                        var orderLine = $(this).closest("tr");
                        var orderTable = $(this).closest("table");
                        var imageFile = orderTable.data("file_name");
                        var item = orderLine.data("framed_print_order");
                        var optionSelect = orderLine.find(".pos_frame_style");
                        var qty = orderLine.find(".pos_qty").val();
                        var optionChosen = optionSelect.find(":selected");
                        var option = optionChosen.val();
                        if ((option == "--" ) && ((qty != "0") && (qty != "")))  {
                            optionSelect.addClass("pos_alert");
                            return;
                        }

                        var optionValue = optionSelect.find("option").map(function() {
                               return $(this).data("option");

                        }).get();
                             
                        
                        var price = orderLine.data("framed_print_price");

                        if ((qty == "0") || (qty == "")) {
                            orderLine.removeClass("pos_active_order_line");    
                        }
                        else {
                            orderLine.addClass("pos_active_order_line");  
                        }

                        basket.addItem("framed_prints", imageFile, item, option, price, optionValue, qty);


                          
                    });

                    $("body").on("change", ".pos_frame_style", function() {
                            if ($(this).val() != "--" ) {
                                $(this).removeClass("pos_alert"); 
                            }
                    });

                     $("body").on("change", ".pos_framed_print_qty", function() {
                            var orderLine = $(this).closest("tr");
                            var optionSelect = orderLine.find(".pos_frame_style");
                            if (optionSelect.val() == "--" ) {
                                optionSelect.addClass("pos_alert");
                                
                            }
                            if ( ($(this).val() == "") ||  ($(this).val() == "0")   ) {
                                optionSelect.removeClass("pos_alert");
                            }
                    });

                    $("body").on("click", ".pos_print_delete_action", function() {
                        var orderLine = $(this).closest("tr");
                        var orderTable = $(this).closest("table");
                        var imageFile = orderTable.data("file_name");
                        var item = orderLine.data("print_order");
                        basket.removeItem("prints", imageFile, item);
                        orderLine.find(".pos_qty").val("0");
                        var optionSelect = orderLine.find(".pos_mounted_select");
                        optionSelect.posFirstSelect();
                        orderLine.removeClass("pos_active_order_line"); 
                        
                    
                    } );

                  $("body").on("click", ".pos_framed_print_delete_action", function() {
                        var orderLine = $(this).closest("tr");
                        var orderTable = $(this).closest("table");
                        var imageFile = orderTable.data("file_name");
                        var item = orderLine.data("framed_print_order");
                        basket.removeItem("framed_prints", imageFile, item);
                        orderLine.find(".pos_qty").val("0");
                        var optionSelect = orderLine.find(".pos_frame_style");
                        optionSelect.posFirstSelect();
                        orderLine.removeClass("pos_active_order_line"); 
                        
                    } );

                    $('body').on('click', ".pos_basket_close_action", function() {
                        $("div.pos_basket_display").remove();
                        pos.getHtml({"action": "showThumbs", "index" : pos.currentIndex }); 
                        basket.removeItemsWithZeroQty();
                    });

                    $('body').on('click', ".pos_checkout_close_action", function() {
                        $("div.pos_checkout_display").remove();
                        pos.getHtml({"action": "showThumbs", "index" : pos.currentIndex }); 
                     });

                    $('body').on("click", ".pos_delivery_show_action", function() {
                        $('.pos_delivery_message').show();

                    });

                    $('body').on("click", ".pos_delivery_hide_action", function() {
                        $('.pos_delivery_message').hide();

                    });


                    $('body').on("click", ".pos_checkout_action", function() {
                        
                        var checkOutInfo =  $('<div></div>').attr("id", "checkout_info"); 
                        pos.regions.checkout_info = checkOutInfo;
   

                        var totals = basket.getTotals();

                        var totalTable = $("<table></table>").addClass("pos_small_text pos_totals")   ;
                        totalTable.append("<tr><td colspan=\"2\" class=\"pos_align_centre pos_emphasis\">Your order</td></tr>");
                        totalTable.append("<tr><td></td><td class=\"pos_emphasis\">" + pos.currencySymbol + "</td></tr>");
                        totalTable.append("<tr><td class=\"pos_emphasis\">Prints (" + totals.prints_qty + ")</td><td class=\"pos_align_right\">"+ Number(totals.prints).toFixed(2)  + "</td></tr>");
                        totalTable.append("<tr><td class=\"pos_emphasis\">Framed Prints (" + totals.framed_prints_qty + ")</td><td class=\"pos_align_right\">"+ Number(totals.framed_prints).toFixed(2)  + "</td></tr>");
                        totalTable.append("<tr><td class=\"pos_emphasis\">Delivery</td><td class=\"pos_align_right\">"+ Number(totals.delivery).toFixed(2)  + "</td></tr>");
                        totalTable.append("<tr><td class=\"pos_emphasis\">Total</td><td class=\"pos_align_right\">"+ Number(totals.grand_total).toFixed(2)  + "</td></tr>");


                        var checkOutPage = $('<div></div>').addClass("pos_checkout_display").addClass("pos_small_text");

                        var checkOutClose = $('<div></div>').addClass("pos_checkout_close pos_checkout_close_action").
                            html('x');
                        var addressBlock = $('<div></div>').addClass("pos_checkout_address");
                        var totalBlock = $('<div></div>').addClass("pos_checkout_total");
                        totalBlock.append(totalTable, checkOutInfo);
                        var breaker1 =    $('<br/>').addClass("pos_clear_fix");
                        var breaker2 =    $('<br/>').addClass("pos_clear_fix");
                        var buttonsBlock = $('<div></div>').addClass("pos_checkout_buttons"); 
                        checkOutPage.append(checkOutClose, breaker1, addressBlock, totalBlock, breaker2, buttonsBlock)   ;

                        pos.getHtml({"action": "checkOutInfo" }, function() {
                            pos.regions.content.html(checkOutPage);
                            pos.regions.footer.html("");
                        });
                    });

                    $('body').on('click', ".pos_basket_action", function() {
                                                                         
                       var basketPage = $('<div></div>').addClass("pos_basket_display");

                       var basketClose = $('<div></div>').addClass("pos_basket_close pos_basket_close_action").
                            html('x');
                        basketPage.append(basketClose);

                        pos.regions.content.html(basketPage);
                        pos.regions.footer.html("");


                        var printsCount = 0;
                        $.each(basket.prints, function() {
                            printsCount++;
                        });

                        var framesCount = 0;
                        $.each(basket.framed_prints, function() {
                            framesCount++;
                        });
                 
                        
                        if ((printsCount < 1) && (framesCount < 1)) {
                            basketPage.append($('<div></div>').html("Your basket is empty at the moment.").addClass("pos_clear_fix pos_medium_text "));
                            return;    
                        }  else
                        {
                            basketPage.append($('<div></div>').html("Your order").addClass("pos_basket_order_label pos_clear_fix pos_medium_text "));
                               
                        }
                       
                        
                        var table = $('<table></table>').addClass("pos_table pos_basket_table pos_small_text pos_content_container");
                        //var closeHeight  = $(".pos_basket_close").height()    ;
                        var  orderHeight = $(".pos_basket_order_label").height();
                        var tableHeight = $("#pos_content_region").height()  - orderHeight; 
                        table.css("max-height", tableHeight  + "px");
                        var tableBody = $('<tbody></tbody');   

                    
                        if (printsCount > 0) {
                            tableBody.append('<tr class="pos_table_heading pos_underline pos_emphasis"><td colspan="7">Prints</td></tr>')  ;
                            tableBody.append('<tr class="pos_emphasis" ><td></td><td>Size</td><td>Option</td><td>Price</td><td>Quantity</td><td>Action</td><td>Sub-total</td></tr>')  ;
                       
                         }

                        $.each(basket.prints, function(printUnframed, order) {
                            $.each(basket.prints[printUnframed], function(size, details) {
                                    var tr = $('<tr></tr>').data("imageFile", printUnframed).data("imageSize", size);                             //TODO make the script name global
                                    var miniPic = $('<img>').attr("src",'posGetImage.php?action=mini&file=' + printUnframed);
                                    var tdPic = $('<td></td>').css("width", "50px").append(miniPic) ;
                                    var tdSize =  $('<td></td>').html(size)  ;
                                    var tdMounted = $('<td></td>');
                                    var mountedSelect = $('<select class="pos_mounted_select pos_basket_option_action">');
                                    //mountedSelect.data("optionValue", details.optionValue);
                                    mountedSelect.append(
                                        '<option data-option="unmounted" value="unmounted">Unmounted</option>' + 
                                        '<option data-option="mounted" value="mounted">Mounted (+' + pos.currencySymbol +Number(details.optionValue).toFixed(2) + ')</option>' +  
                                        '</select>'                                                                              
                                    )  ;
                                    
                                 
    
                                    mountedSelect.find('option[data-option="' + details.option + '"]').attr('selected','selected');  
                                    tdMounted.append(mountedSelect); 
                                    var displayPrice;
                                    if (details.option=="mounted") {
                                        var basePrice = details.price;
                                        var mountedAdditionalCost = details.optionValue;
                                        var displayPrice = Number(basePrice + mountedAdditionalCost).toFixed(2);
                                        
                                    }   else {
                                        displayPrice = Number(details.price).toFixed(2);
                                    }



                                    var tdPrice = $('<td></td>').addClass("pos_display_price").html(pos.currencySymbol + displayPrice );
                                    var qtyBox =  $('<input></input>').attr("type", "number").attr("min", "0").attr("max", "100").
                                        addClass("pos_qty_box pos_qty").val(details.qty);
                                    var tdQty = $('<td></td>').append(qtyBox);  
                                    tdUpdate = $('<td></td>').append( $('<span></span>').addClass("pos_update pos_basket_update_print_action").html("Update") );    
                                    //     $('<span></span>').addClass("pos_update").html("Remove")
                                    var subTotal;
                                    if (details.option == "unmounted") {
                                        subTotal =  Number(details.qty * details.price).toFixed(2);
                                    }  else
                                    {
                                        subTotal =  Number(details.qty * (details.price + details.optionValue)).toFixed(2);
                                    }
                                    tdSubTotal =  $('<td></td>').addClass("pos_basket_subtotal").html(pos.currencySymbol + subTotal) ;
                                    tr.append(tdPic, tdSize, tdMounted, tdPrice, tdQty , tdUpdate, tdSubTotal);
                                    tableBody.append(tr);
                                       
                            });
                                

                        });

                        if (printsCount > 0) {
                            tableBody.append('<tr><td colspan="7" ></td></tr>');
                        }

                        if (framesCount > 0) {
                            tableBody.append('<tr class="pos_table_heading pos_underline pos_emphasis"><td colspan="7">Framed Prints</td></tr>')  ;
                            tableBody.append('<tr class="pos_emphasis"><td></td><td>Size</td><td>Frame Style</td><td>Price</td><td>Quantity</td><td>Action</td><td>Sub-total</td></tr>')  ;
                       
                         }

                        $.each(basket.framed_prints, function(printFramed, order) {
                            $.each(basket.framed_prints[printFramed], function(size, details) {
                                    var tr = $('<tr></tr>').data("imageFile", printFramed).data("imageSize", size) ;                            //TODO make the script name global
                                    var miniPic = $('<img>').attr("src",'posGetImage.php?action=mini&file=' + printFramed);
                                    var tdPic = $('<td></td>').css("width", "50px").append(miniPic) ;
                                    var tdSize =  $('<td></td>').html(size)  ;
                                    var tdFrameStyle = $('<td></td>');
                                    var frameStyleSelect = $('<select class="pos_frame_style">');
                                    $.each(details["optionValue"], function(idx, style) {
                                        frameStyleSelect.append( $('<option></option>').attr("value", style ).html(style) ) ;   

                                    });
                                    frameStyleSelect.find('option[value="' + details.option + '"]').attr('selected','selected');  
                                    tdFrameStyle.append(frameStyleSelect); 
                                    
                                    var tdPrice = $('<td></td>').html(pos.currencySymbol + Number(details.price).toFixed(2));
                                    var qtyBox =  $('<input></input>').attr("type", "number").attr("min", "0").attr("max", "100").
                                        addClass("pos_qty_box").val(details.qty);
                                    var tdQty = $('<td></td>').append(qtyBox);  
                                    tdUpdate = $('<td></td>').append( $('<span></span>').addClass("pos_update pos_basket_update_frame_action").html("Update"));
                                   //      $('<span></span>').addClass("pos_update").html("Remove") );    
                                    
                                    var subTotal =  Number(details.qty * details.price).toFixed(2);
                                    tdSubTotal =  $('<td></td>').addClass("pos_basket_subtotal").html(pos.currencySymbol + subTotal) ;
                                    tr.append(tdPic, tdSize, tdFrameStyle, tdPrice, tdQty , tdUpdate, tdSubTotal);
                                    tableBody.append(tr);
                                       
                            });
                                

                        });



                        table.append(tableBody);
                        basketPage.append(table) ;

                        


                    });

                    $('body').on("change", "select.pos_basket_option_action", function() {
                        var orderLine = $(this).closest("tr");
                        var imageFile = orderLine.data("imageFile");
                        var item = orderLine.data("imageSize");

                        var qty = orderLine.find(".pos_qty").val(); 
                        var option = $(this).find(":selected").data("option");
                        var price = basket.getPrice("prints", imageFile, item);

                        if (option == "mounted") {
                            optionValue =   basket.getOptionValue("prints", imageFile, item);
                            displayPrice = Number( (price + optionValue) ).toFixed(2);
                            
                        }
                        else {
                            displayPrice = Number( price ).toFixed(2);
                        }
                        
                        orderLine.find("td.pos_display_price").html(pos.currencySymbol + displayPrice);


                    });
                   

                    $('body').on('click', '.pos_basket_update_print_action', function() {
                        var orderLine = $(this).closest("tr");
                        var item = orderLine.data("imageSize");
                        var optionSelect = orderLine.find(".pos_mounted_select");
                        var optionChosen = optionSelect.find(":selected");
                        var option = optionChosen.data("option");
                        var imageFile = orderLine.data("imageFile"); 
                        var price = basket.getPrice("prints", imageFile, item);
                     
                        var qty = orderLine.find(".pos_qty").val(); 
                        var diplayPrice;  

                        if (option == "mounted") {
                            optionValue =   basket.getOptionValue("prints", imageFile, item);
                            displayPrice = Number( (price + optionValue) * qty).toFixed(2);
                            
                        }
                        else {
                            displayPrice = Number( price * qty).toFixed(2);
                        }

                        basket.updateItem("prints", imageFile, item, option, qty);
                       
                        orderLine.find("td.pos_basket_subtotal").html(pos.currencySymbol + displayPrice);
 
                    });

                         

                     $('body').on('click', '.pos_basket_update_frame_action', function() {
                        var orderLine = $(this).closest("tr");
                        var item = orderLine.data("imageSize");
                        var optionSelect = orderLine.find(".pos_frame_style");
                        var optionChosen = optionSelect.find(":selected");
                        var option = optionChosen.val();
                        var imageFile = orderLine.data("imageFile"); 
                        var price = basket.getPrice("framed_prints", imageFile, item);
                        
                        var optionValue;
                        var qty = orderLine.find(".pos_qty").val(); 
                        var displayPrice = Number(price * qty).toFixed(2);

                        basket.updateItem("unframed_prints", imageFile, item, option, qty);
                   

                        orderLine.find("td.pos_basket_subtotal").html(pos.currencySymbol + displayPrice);
 
                    });




                    $("body").on("change", ".pos_mounted_select", function() {
                            if (typeof($(this).data("mounted_price")) != "undefined" ) {
                                var option = $(this).val();  
                                var unmountedPrice = $(this).data("unmounted_price");
                                var mountedPrice = $(this).data("mounted_price");
                                if (option=="mounted") {
                                    $(this).parent("td").next().html(Number(mountedPrice).toFixed(2));    
                                } 
                                else {
                                   $(this).parent("td").next().html(Number(unmountedPrice).toFixed(2)); 
                                }  
                                 
   
                            }
                            else {
                                
                                
                            }

                    });

                    $(window).unload(function() {
                        if (! hasSessionStorage()) {
                            return;
                        }
                        var data = {
                                        prints: basket.prints, 
                                        framed_prints: basket.framed_prints
                                   }
                        sessionStorage.posSavedBasket = JSON.stringify(data);

                    });

                    $(window).load(function() {
                        if (! hasSessionStorage()) {
                            return;
                        }

                        if (typeof(sessionStorage.posSavedBasket) != "undefined") {
                            basket.rePopulate($.parseJSON(sessionStorage.posSavedBasket));
                        }

                    });



                    pos.getHtml({"action": "$actionOnLoad"});

                })
        
            </script>
EOT;
        echo $js;


        $html = <<<EOT
             <div id="pos_main_area" class="pos_main_area">
                <div id="pos_menu_region" class=" pos_small_text">
                    
                 
                </div>

                <div id="pos_header_region" class=" pos_large_text"></div>

                <div id="pos_content_region" class=" pos_medium_text" ></div>

                <div id="pos_footer_region" class=" pos_small_text"> </div>

                
                

            </div>

EOT;
                 
        echo $html;

    }

    public function frameOptions()
    {

         //TODO  why is this different from showLoginScreen above???? cos prints window is open?
        //why not just close that?
        if (!isset($_SESSION['userId']) ) //TODO test this
        {
            $htmlForLogin = $this->loginScreenHtml()  ;
            $res = new StdClass();   
            $res->header =  "";
            $res->content =  $htmlForLogin; 
            $res->controls =  "Sorry. You have been logged out. Please close this window and log in again"; 
            $this->output($res);
        }
        

        if (is_null($this->client))
        {
              $this->setClient($_SESSION['userId']);
        }
        $img = $_GET['image'];  
        $originalImage = $this->client->attributes()->directory . DIRECTORY_SEPARATOR . $img;
        list($w,$h) = getimagesize($originalImage);

        if ($w >= $h)
        {
                $ratio = $w / $h;
        }
        else
        {
            $ratio = $h / $w;
        }
      
        if ($ratio > 1.65)
        {
            $sizing = "widescreen";
        }
        elseif   (($ratio >= 1.375) && ($ratio <= 1.65))
        {
            $sizing = "standard35mm"  ;
        }
        else
        {
            $sizing = "squareFormat"  ;
        }

        $framings = $this->xml->xpath("prices/".$sizing);
        $framing = $framings[0];



        $prints = $framing->xpath("prints");
        $framedPrints = $framing->xpath("framed_prints");


        //TODO test for empty 
        $printsHtml = $this->printsHtml($prints[0], $img);
        $framesHtml = $this->framesHtml($framedPrints[0], $img);
        
        
        $res = new StdClass();
        $res->controls_prints =  $printsHtml; 
        $res->controls_frames =  $framesHtml; 
        $this->output($res);


    }

    public function checkOutInfo() 
    {

        if (!isset($_SESSION['userId']) )  //TODO test
        {
            $this->showLoginScreen();
        }

        $deliveryMessage =  htmlentities((string) $this->xml->delivery_and_terms->delivery_charges_explanation, ENT_COMPAT , "UTF-8");
        $requireAgreement = (boolean) (string) $this->xml->delivery_and_terms->terms_and_conditions->attributes()->requireAgreement;
        $termsAndConditions =  htmlentities((string) $this->xml->delivery_and_terms->terms_and_conditions, ENT_COMPAT , "UTF-8");

        $checkOutHtml = $this->checkOutHtml($deliveryMessage, $termsAndConditions, $requireAgreement );


        $res = new StdClass();
        $res->checkout_info =  $checkOutHtml; 
        $this->output($res);

    }


    public function showThumbs() {


        if (!isset($_SESSION['userId']) )
        {
            $this->showLoginScreen();
        }

        if (is_null($this->client))
        {
              $this->setClient($_SESSION['userId']);
        }


        if (isset($_GET['index']))
        {
            $startIndex = $_GET['index'];
        }
        else
        {
            $startIndex = 0;
        }

        //work out number of thumbs per page:
        $thumbsPerPage = $this->thumbsPerPage($_GET['width'],$_GET['height']);

        //the number of thumbs   altogether
        $dir = $this->client->attributes()->directory . '';
        if (! is_readable($dir))
        {
            $this->err("The directory given in the XML either does not exist or is not readable");
        }
        $numberOfThumbs = $this->getImageCount($dir);

        $pageHtml = $this->pageHtml($startIndex, $thumbsPerPage, $numberOfThumbs  );

        $files = scandir($dir);

        $thumbs=array();
        foreach ($files as $file) 
        {
            if ( ($file != ".")  && ($file != "..") && (is_file($dir . DIRECTORY_SEPARATOR . $file)) )
            {
                        $thumbs[] = $file;
            }
        }                             
        $thumbsForThisPage = array_slice($thumbs, $startIndex, $thumbsPerPage);
                                                   

        $name = $this->client->attributes()->name . '';
        $nameBlock = $this->nameHtml($name);
        $thumbsHtml = $this->thumbsHtml($thumbsForThisPage) ;

        $ecommerce = (boolean) (string) $this->client->attributes()->ecommerce ;
     

        $menuHtml = $this->menuHtml($ecommerce)  ;

        $res = new StdClass();
        $res->header =  $nameBlock;  
        $res->content =  $thumbsHtml;  
        $res->footer =  $pageHtml; 
        $res->menu = $menuHtml;
        $this->output($res);
 
        
    }


    public function showLoginScreen($msg = "") {
           $htmlForLogin = $this->loginScreenHtml($msg);
           $res = new StdClass();
           $res->header =  "";
           $res->menu = ""; 
           $res->content =  $htmlForLogin;  
           $this->output($res);

    }


   

    private function output($output) {      
        header("Content-type: application/json");   
        echo json_encode($output, JSON_FORCE_OBJECT);
        exit();
    }

    private function checkOutHtml($deliveryMessage, $termsAndConditions, $agreementRequired = false )
    {

        $html = "";

        if ($deliveryMessage != "")  
        {
            $html.= "<div class=\"pos_underline pos_delivery_heading pos_delivery_show_action\">How is delivery calculated?</div>";
            $html.= "<div class=\"pos_delivery_message\"><span class=\"pos_checkout_close pos_delivery_hide_action pos_checkout_close_small\">x</span>" 
            .  $deliveryMessage . "</div>";
        }

        if ($termsAndConditions != "")
        {
            $html.= "<div class=\"pos_underline pos_terms_heading\">Terms and Conditions</div>";
            $html.=  "<div class=\"pos_terms_and_conditions\">" .  $termsAndConditions . "</div>";  

            if ($agreementRequired)
            {
                $html.=  "<div class=\"pos_agree\">I agree to these terms. <input type=\"checkbox\" id=\"pos_terms_agree\" ></div>";

            }

        }

        

        return $html;

    }




    private function setClient($userId)
    {
        $client = $this->xml->xpath("clients/client[@password='".$userId."']")   ;
        if (empty($client)) {
              $this->err();
        }  
        else
        {

            if (count($client) > 1) {
                $this->err("Client identifiers not unique. Check your XML") ;
            }
            $this->client = $client[0];
            
         }
    }

    private function thumbsPerPage($areaWidth, $areaHeight)
    {
        $areaHeight = floor( 0.8 * $areaHeight) ;
        $thumbWidth = $this->xml->options->thumbLongestSide . '';
        $areaWidth = $areaWidth - 5;
        $thumbsPerRow = floor( $areaWidth / ($thumbWidth + 10) );
        $rowsPerArea = floor( $areaHeight / ($thumbWidth + 10) );
        return    $thumbsPerRow*$rowsPerArea;

    }

    private function getImageCount($dir)
    {
            $haveIterator = class_exists("FilesystemIteratorx");
            if ($haveIterator)
            {
                $fi = new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS);
                $i = 0;
                foreach  ($fi as $fileOrFolder)
                {
                    if ($fileOrFolder->isFile())
                    {
                        $i++;
                    }
                }             
                return $i;

            }
            else
            {            
                $i = 0;           
                $files = scandir($dir);
                foreach ($files as $file) 
                {
                    if ( ($file != ".")  && ($file != "..") && (is_file($dir . DIRECTORY_SEPARATOR . $file))  )
                    {
                        $i++;
                    }
                }          
                return $i;
            }
    
    }

    private function nameHtml($name)
    {
        return '<div class="pos_client_name">' . $name . '</div>';

    }

    private function thumbsHtml($thumbs)
    {
        $output = '<div class="pos_thumbs_set">';
        $imageScript = "posGetImage.php";
        $thumbWidth = $this->xml->options->thumbLongestSide . ''; 
        foreach ($thumbs as $thumb)
        {

            $path = $this->client->attributes()->directory . DIRECTORY_SEPARATOR . $thumb;
            list($w,$h) = getimagesize($path);

            $class = "";
            if ($w > $h) 
            {
                $class = "landscape";
            }

            $output.= ' <div  class="pos_thumb_container" style="width:'.$thumbWidth.'px;height:'.$thumbWidth.'px">'.
                '<img src="' . $imageScript . '?action=thumb&file='.$thumb . '"' .
                'class="pos_thumb_image ' . $class . '" data-image="' .  $thumb .'" ></div>';
        }
        $output.= '</div>';

        return $output;


    }

    private function menuHtml($ecommerce = false)
    {

        $html = '<div id="pos_logout_action" class="pos_logout_action pos_menu">Logout</div>';

        if ($ecommerce) {
            $html = $html . '<div  class="pos_menu pos_checkout_action">Checkout</div>';
            $html = $html .  '<div class="pos_menu pos_basket_action">Basket</div>';
        }

        return $html;


    }


    private function printsHtml($printsXml, $imageName)
    {

       
        $curr = htmlentities((string) $this->xml->paypal->currencySymbol, ENT_COMPAT , "UTF-8");


        $html =  '<table data-file_name="' .  $imageName   . '"class="pos_print_options pos_small_text pos_table">';
        $html = $html . '<thead><tr><th>Size</th><th>Options</th><th>'.$curr.'</th><th>Qty</th><th></th></tr></thead><tbody>';
        
        $rows="";
        foreach($printsXml as $printNode)
        {
                $row =  '<tr data-print_order=\''  .  $printNode->attributes()->size .'\'>';
                $row = $row   . "<td>" . $printNode->attributes()->size . "</td>"   ;

                $mounted =  $printNode->attributes()->mountedAdditionalCost;
                if (! is_null($mounted))
                {
                    $disabled = "";
                    $mountedAdditionalCost =    number_format((float)(string) $printNode->attributes()->mountedAdditionalCost, 2, '.', '');
                    $mountedPrice =   (float)(string) $printNode->attributes()->mountedAdditionalCost + 
                          (float) (string) $printNode->attributes()->price;
                    $mountedPrice = number_format($mountedPrice, 2, '.', '');
                    $priceData = ' data-mounted_additional_cost=\'' . $mountedAdditionalCost . '\' data-mounted_price=\''. $mountedPrice. '\' data-unmounted_price=\'' . $printNode->attributes()->price . '\'' ;
                
                }
                else
                {
                    $disabled = "disabled";
                    $priceData = "";
                }
                    $options = '<select ' .  $disabled . ' class="pos_mounted_select" ' . $priceData . '  ><option data-option="unmounted" value="unmounted">Unmounted</option>'.
                '<option data-option="mounted" value="mounted">Mounted (+ ' . $curr . $printNode->attributes()->mountedAdditionalCost . ')</option></select>';    
             
              

                $row = $row   . "<td>" . $options . "</td>"   ;

                $row = $row   . "<td class=\"pos_price\">" . $printNode->attributes()->price . "</td>"   ;

                $row = $row . '<td><input class="pos_small_text pos_qty_box pos_qty"  value="0" type="number" min="0" max="100" ></td>';
                $row = $row .  '<td ><span class="pos_update pos_print_update_action">Update</span></td>';
                $row = $row .  '<td ><span class="pos_update  pos_print_delete_action ">Remove</span></td>';

                $row = $row . "</tr>";
                $rows = $rows . $row; 
                
        }

        $html = $html . $rows . "</tbody></table>";

        return $html;

    }

    private function framesHtml($framedXml, $imageName)
    {
          
        $curr = $this->xml->paypal->currencySymbol . '';


        $html = '<table  data-file_name="' .  $imageName   . '" class="pos_frame_options pos_small_text pos_table">';
        $html = $html . '<thead><tr><th>Print Size</th><th>Frame Style</th><th>'.$curr.'</th><th>Qty</th><th></th></tr></thead><tbody>';
        
        $rows="";
        foreach($framedXml as $frameNode)
        {        
                $row =  '<tr data-framed_print_price=\''. $frameNode->attributes()->price . '\' ' .  
                ' data-framed_print_order=\''  .  $frameNode->attributes()->size .'\'>';
                $row = $row   . "<td>" . $frameNode->attributes()->size . "</td>"   ;


                $frameStyles = '<select  class="pos_frame_style"  >'  ;
                   //TODO test if only 1
                $styles =  explode(",", (string) $frameNode->attributes()->frameStyles );
                $options= '<option value="--">Select..</option>';
                foreach ($styles as $frameStyle)
                {
                    $options = $options . '<option data-option=\''.$frameStyle.'\' value="'. $frameStyle . '">' . $frameStyle . '</option>'; 
                }
                $frameStyles = $frameStyles . $options . '</select>';


                $row = $row   . "<td>" . $frameStyles . "</td>"   ;

                $row = $row   . "<td>" . $frameNode->attributes()->price . "</td>"   ;

                $row = $row . '<td><input class="pos_small_text pos_qty_box pos_qty pos_framed_print_qty"  value="0" type="number" min="0" max="100" ></td>';
                $row = $row .  '<td ><span class="pos_update pos_framed_print_update_action">Update</span></td>';
                $row = $row .  '<td ><span class="pos_update  pos_framed_print_delete_action ">Remove</span></td>';
                $row = $row . "</tr>";
                $rows = $rows . $row; 
                
        }

        $html = $html . $rows . "</tbody></table>";

        return $html;
        

    }






    private function pageHtml($startIndex, $thumbsPerPage, $numberOfThumbs)
    {
        $output = '<div class="pos_pages">';
        $numberOfPages = ceil($numberOfThumbs / $thumbsPerPage);

        $index = 0;
        $pages = array();

        for ($i= 1; $i <= $numberOfPages; $i++) 
        {
            $pages[$i] =  $index;
            $index = $index + $thumbsPerPage;
        }

        $currentPage = array_search($startIndex, $pages);

        $length = 11;
        $offset = $currentPage - 6;
        if ($offset < 0) {
            $offset = 0;
        } 
        
        $pagesToShow = array_slice($pages, $offset, $length, $preserve_keys = true);

        foreach($pagesToShow as $pageNumber => $index)
        {
            if ($index == $startIndex) {
                $class = " pos_highlighted_pagination ";
            }
            else
            {
                $class = "";
            }
            $output.= '<span data-index="' . $index . '" class="pos_small_text pos_pagination' . $class . 
                '">' . $pageNumber . '</span>';
         
        }
        

        $output.= '</div>';
        return $output;

    }
   
    private function loginScreenHtml($msg = "") {

         $loginScreen = <<<EOT
            <div class="pos_login_screen">
                   Please enter the username you have been supplied with:<br>
                    <input type="text" id="pos_userid"><br>
                    <button id="pos_login_action">View Gallery</button>
                    <div class="pos_error">$msg</div>
            </div>
EOT;

        return $loginScreen;

    }

      
      
    private function err($err = "") {
        $verbose = 1; 
        if (! is_null($this->xml)) {
            $verbose = $this->xml->system->debug_verbose;
        }

        echo "An error occured. "      ;
        if ($verbose == 1) {
            echo $err;
        }
        exit();
    }


}


?>
