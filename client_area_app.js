/**
 * lp
 * i. had to handle tracking and removing child views so as to unbind/unlisten to
 * ii. needed a region contoller
 *  iii. looking for memory leaks with : chrome task mamager; profiles; and the timeline        https://developers.google.com/web/tools/chrome-devtools/memory-problems/
 *  iv. watch out for collections built using models from other collections: the models maintain a reference to the first collection
 */

var caApp = (function (Backbone, $) {                                                                                                                                                                                                                                                      
	var app = {};
    //TODO - don't need to attach things to app. unless they are being exported.... and nothing is
    
    //shims
    app.isInt = function(n) {
        return parseInt(n) == n;
    }
    
    app.isArray = function(obj) {
        if (! Array.isArray) {
            return Object.prototype.toString.call(obj) === "[object Array]";
        } else {
            return Array.isArray(obj);
        }
    }
    
    
    app.isSessionAlive = function() {
        return $.ajax(
            {
                url: '/api/v1/sessionStatus',
                method: 'GET',
                dataType: 'json'
        });
        
    }    

         
    
    app.init = function() {
    
        //A few variables    TODO put somewhere else. options?
        app.labelHeight = 10; //this sets the height of the lables underneath the thunb images. units: pixels. 
        app.maxPopupWidth = 1600; //the maximum width of the popup which displays the main images. (In general this should not be larger than the typical width of your landscape orientation images.  units: pixels)
        app.lightBoxWidthFraction = 0.95; //effects prints mode only. the width of the image popup if not constrained by the above setting. units: percentage. you probably don't need to change this. 
    
        //TODO this should be immutable
        app.pricingModel = new app.PricingModel();
        
                                                                                                                                      
        app.basketCollection = new BasketCollection();
        app.proofsBasketCollection = new ProofsBasketCollection();
        app.printsThumbsCollection = new PrintsThumbsCollection();
        app.proofsThumbsCollection = new ProofsThumbsCollection();
        app.langStrings = new LangStrings();
        app.layout = new Layout();
        app.appData = {};
        
        
        runRoutes = function(data) {
            var AppRouter = Backbone.Router.extend({
            routes: {    "": "home",
                        "thanks": "paypalThanks",
                        "cancel": "paypalCancel",
                        "choose": "chooseMode",
                       '*notFound': 'notFound'
                    },
                    
                    home: function() {
                        if (data.loggedIn) {
                            var modeChoiceView = new ModeChoiceView();
                            app.layout.renderViewIntoRegion(modeChoiceView, 'main');
                        } else {
                            var loginView = new LoginView({message: ''});
                            app.layout.renderViewIntoRegion(loginView, 'main');
                        }
                    }, 
                    chooseMode: function() {
                        var modeChoiceView = new ModeChoiceView();
                        app.layout.renderViewIntoRegion(modeChoiceView, 'main'); 
                    },                 
                    paypalThanks: function() {
                    
                        this.paypalHandler('thanks');       
                         
                    },
                    paypalCancel: function() {
                        this.paypalHandler('cancel');     
                    },

                    paypalHandler: function(mode) {
                    
                        if (data.loggedIn) {
                            if (mode == 'thanks') {
                                var paypalView = new PaypalThanksView();
                                var logoutMenu = new LogoutMenuView();
                                app.layout.renderViewIntoRegion(logoutMenu, 'menu');
                               app.layout.renderViewIntoRegion(paypalView, 'main');
                            }  else {
                                 
                                var thumbsPerPage = parseInt(app.appData.thumbsPerPage); 
                                var xhrPricingModel = app.pricingModel.fetch({reset: true});
                                var xhrBasketCollection = app.basketCollection.fetch({reset: true})  ;
                                var xhrPrintsThumbsCollection = app.printsThumbsCollection.fetch({reset: true});
               
                                $.when(xhrPricingModel, xhrBasketCollection, xhrPrintsThumbsCollection).then(function() {
                                        var menuView = new PrintsMenuView({totalThumbs: app.printsThumbsCollection.length, thumbsPerPage: thumbsPerPage, active: 1});
                                        app.layout.renderViewIntoRegion(menuView, 'menu');
                                        var paypalView = new PaypalCancelView();
                                        app.layout.renderViewIntoRegion(paypalView, 'main');
                                    },
                                    function() {
                                        var errorView = new ErrorView();   //TODO test this
                                        app.layout.renderViewIntoRegion(errorView, 'main');
                                }); 
                                
                            } 
                        
                        } else {
                            var loginView = new LoginView({message: ''});
                            app.layout.renderViewIntoRegion(loginView, 'main');
                        }
                    },  
                    
                    notFound:function() {
                        console.log("not_found TODO");
                    }        
             });
             
            app.router = new AppRouter();
            //TODO - this meanst that the app has to be run in the web root?
            //e.g. domain.com/something/client_area/thanks won't match because the root won't work. should this be dynamic?
            Backbone.history.start({pushState:true, root: "/client_area"});
        } 
         
            
         

        //https://api.jquery.com/jquery.when/
        var xhrLangStrings = app.langStrings.fetch();    
        var xhrSessionStatus = $.ajax(
                    {
                        url: '/api/v1/sessionStatus',
                        method: 'GET',
                        dataType: 'json'   
                    }
                );
        
        
        $.when(xhrLangStrings, xhrSessionStatus).then(
            function(result1, result2) {
                if (result2[0].status == 'success') {
                    app.appData = result2[0].appData;
                    runRoutes({loggedIn: true});
                } else {
                    runRoutes({loggedIn: false});           
                }
            },
            function() {
                var errorView = new ErrorView();   //TODO test this
                app.layout.renderViewIntoRegion(errorView, 'main');    
            }
        )
        


    };
    
   
    
    //MODELS
    //used for the delivery: the actual basket is in the session (and local storage)
    OrderModel =  Backbone.Model.extend({
        initialize: function() {
    
        },
        defaults: {
                clientName: '',
                address1: '',
                address2: '',
                city: '',
                zip: '',
                country: '',
                deliveryCharges: 0,
                totalItems: 0,
                grandTotal: 0,
                address_type: null
        },
        
        resetAddressToOnFile: function() {
            this.set({
                clientName: this.defaults.clientName,
                address1: this.defaults.address1,
                address2: this.defaults.address2,
                city: this.defaults.city,
                zip: this.defaults.zip,
                country: this.defaults.country ,
                address_type: 'address_on_file' 
            });        

        },
        
        validate: function(attrs, options) {
            var valid = true;
            
            if (attrs.address_type == "address_entered") {
                if (attrs.clientName == '')  {
                    valid = false;
                }
                
                if (attrs.address1 == '')  {
                    valid = false;
                }
                
                if (attrs.city == '')  {
                    valid = false;
                }
                
                if (attrs.zip == '')  {
                    valid = false;
                }
                
                if (attrs.country == '')  {
                    valid = false;
                }
            
            } else if (attrs.address_type == "address_on_file") {
                valid = true;
            } else {
                valid = false;
            }
            
            if (!valid) {
                return app.langStrings.get("selectOrEnterAddress");
            }
        }
    })
    
    //PricingModel Model   
    //this gets calculated data from the backend so that the backend calculations and front-end are the same                                                                                                                                                                                        
    app.PricingModel = Backbone.Model.extend({
        url: "/api/v1/pricing",
        defaults: {
          cache: {}   
       },
       

       initialize: function() {
            var cacheStructure = {
            
            };
            cacheStructure.sizesForRatio = {};
            this.set("cache", cacheStructure);
       },
       //TODO - this needs to cache the promises. on first request we make the same call multiple times
       //because of the way the methods in this class use each other as well as being used directly.
       proxyRequest: function(call, params)
       {
           params.call = call; 
           return $.ajax({
                    url: '/api/v1/pricingCalculationData',
                    data: params,
                    dataType: 'json',
                    method: 'POST'
                });
       },

       //back-end call
       getSizesForRatio: function (imageRatio) {
            var cache =  this.get("cache");
            var that = this;
            if (cache.sizesForRatio.hasOwnProperty(imageRatio)) {
                var data = cache.sizesForRatio[imageRatio];
                var def = $.Deferred();
                def.resolve(data);
                return def.promise();
            }  else {
            
                var xhr = this.proxyRequest('getSizesForRatio', {imageRatio: imageRatio});
                xhr.then(
                    function(result)
                    {
                        cache.sizesForRatio[imageRatio] = result;
                        that.set("cache", cache);
                     },
                    function() {
                        var errorView = new ErrorView();
                        app.layout.renderViewIntoRegion(errorView, 'main'); 
                    }
                );
                return xhr; 
            }          
       },
       
       //back-end call
       getCalculateApplicableDevliveryChargesAndTotals: function() {
            return this.proxyRequest('getCalculateApplicableDevliveryChargesAndTotals', {});
        },
    

        //calculation
        getPrintPriceAndMountPriceForSize: function(sizeBlock, printSize) {
         
            _.each(sizeBlock, function(size) {
                if (size.value == printSize) {
                    sizeBlock = size;   
                }
             }); 
             
             var  ret = {};
             ret.mountPrice =  sizeBlock.mountPrice;
             ret.printPrice =  sizeBlock.printPrice;

            return ret;
        },

       //calculation
        getFramePriceMatrixForSize: function(sizeBlock, printSize) {

             var framePricesObj = {};
            _.each(sizeBlock, function(size) {
                if (size.value == printSize) {
                    sizeBlock = size;   
                }
             }); 
             
            _.each(sizeBlock.framePrices.framePrice, function(framePrice) {
               framePricesObj[framePrice.style] = framePrice.price;
            });

            return  framePricesObj;
       }, 
   
       //data from model
       getFrameDisplayNamesCodesLookup: function() {
        
             var pictureFrames = this.get("frames");
             var framesCodeToDisplay = {};
             _.each(pictureFrames.frame, function(frame) {
                framesCodeToDisplay[frame.value] = frame.display;   
            });
            return framesCodeToDisplay;
       }

    
    });
    
    //Language String model
    LangStrings = Backbone.Model.extend({
        url: "/api/v1/langStrings",    
    
    }); 
    
    ProofOrderLineModel = Backbone.Model.extend({
        idAttribute: 'file_ref',
        defaults: {
            "file_ref": null,
            "image_ref": null
            }  ,
            
        initialize: function() {
        
        
        }    
            
    });
    
    //OrderLine model
    OrderLineModel = Backbone.Model.extend({
        defaults: {
            "id": null,
            "image_ref": null,
            "image_ratio": null,
            "print_size":null,
            "mount_style":null,
            "frame_style":null,
            "frame_display_name": null,
            "print_price": 0,
            "mount_price":0,
            "frame_price":0,
            "qty":1,
            "total_price": "0.00",
            "edit_mode": "edit",
            "path": null
        },
        
        initialize: function() {
            this.on("change:print_size", function() {
                var printSize = this.get("print_size");
                if (printSize === null) {
                    this.completeResetOrderLine();
                }  
            }); 
            this.on("change:print_price", function() {
                this.setTotalPrice(); 
            }); 
            this.on("change:mount_price", function() {
                this.setTotalPrice(); 
            });
            this.on("change:frame_price", function() {
                this.setTotalPrice(); 
            });
            this.on("change:qty", function() {
                this.setTotalPrice();    
            });
        },
        
        completeResetOrderLine: function() {
            this.set( 
                {
       
                    "mount_style":null, 
                    "frame_style":null, 
                    "print_price": 0, 
                    "mount_price":0,
                    "frame_price":0,
                    "total_price":"0.00",
                    "qty": 0,
                    "edit_mode": "edit" 
                     
                });
        },
        
        setTotalPrice: function() {
           var printPrice =  this.get("print_price");
           var mountPrice =  this.get("mount_price");
           var framePrice =  this.get("frame_price");
           var totalPrice = 0;
           var qty = this.get("qty");
           totalPrice = qty * printPrice;
           if (mountPrice !== null) {
                totalPrice = totalPrice + (qty * mountPrice);     
           }
           if (framePrice !== null) {
                totalPrice = totalPrice + (qty * framePrice);     
           }
           totalPrice = totalPrice.toFixed(2);
           this.set({"total_price": totalPrice}); 
  
        },

        validate: function(attrs, options) {
            var errState = false;
            var errData = {};
            errData.errString = "";
            errData.fields = [];
            
            if (attrs.print_size == null) {
                errState = true;
                errData.errString =  errData.errString + app.langStrings.get("sizeFeedback") + " ";
            } 
            
            if (isNaN(attrs.qty) || (attrs.qty === "") || (attrs.qty == 0) || (!app.isInt(attrs.qty)))  {
                errState = true;
                errData.errString = errData.errString + app.langStrings.get("qtyFeedback") ;
                errData.fields.push('qty');   
            }
            
            if (errState) {
                errData.errString = app.langStrings.get("invalid") + " " + errData.errString;
                return errData;
            }
        
        }
    
    });
    

    ThumbModel = Backbone.Model.extend({    
    
        defaults: {
                main_image_dimensions: null,
                image_ratio: null,
                file: "",
                path: ""
        }

    });
    
    //COLLECTIONS
    
    //Basket - collection of OrderLine Models
    //calling fetch on a collection with an array of objects populates the collection with the models - one model with one element in the array
    BasketCollection =  Backbone.Collection.extend({
        model: OrderLineModel,
        url: "/api/v1/basket",
        
        initialize: function(options) {
            this.on("add", function(newModel) {
                newModel.set("edit_mode", "save"); 
                //var clientAreaStorage = new ClientAreaStorage(app.appData.username, _);  TODO aren't we deleteing all of     ClientAreaStorage
            });    
        },

        byImage: function (ref) {
          filtered = this.filter(function (orderLine) {
              return orderLine.get("image_ref") === ref;
          });
          return new Backbone.Collection(filtered);
        },
    
    });
    
   ProofsBasketCollection =  Backbone.Collection.extend({
        model: ProofOrderLineModel,
        url: "/api/v1/proofsBasket",
        
        initialize: function(options) {
            
        },
   
    });
    
    
    
    
    PrintsThumbsCollection =  Backbone.Collection.extend({
        model: ThumbModel,    
        url: "/api/v1/Thumbs/prints",
        
        initialize: function() {
            this.on("reset", function() {
               this.setMaxHeight();    
            });
        },
        
        /*
        * this ensures that the rows are of equal height when the actual heights of the thumbs differ
        */
        setMaxHeight: function() {
            this.labelHeight = app.labelHeight;
            this.maxHeight = app.appData.thumbMaxHeight + (2 * this.labelHeight); 
            this.thumbImageMaxHeight =  app.appData.thumbMaxHeight;
        },
        
        //TODO put on one shared class but see http://www.erichynds.com/blog/backbone-and-inheritance
        /*
        * return the JSON of the given page to use to create a new instance of this collection for the page
        */
        pagination: function(perPage, page) {
            var start = (page - 1) * perPage;
            var end = (perPage * page);
            
            var pageModels =  this.slice(start, end);
            
            //see http://stackoverflow.com/questions/41771742/do-i-need-to-destroy-a-backbone-collection for why we do this. esp. my last comment
            var jsonPages = _.map(pageModels, function(model) {
               return model.toJSON();
            });
            
            return jsonPages;  
        }
        
       

    });
    
    ProofsThumbsCollection =  Backbone.Collection.extend({
        model: ThumbModel,    
        url: "/api/v1/Thumbs/proofs", 
        
       //TODO same as  PrintsThumbsCollection
       initialize: function() {
            this.on("reset", function() {
               this.setMaxHeight();    
            });
        },
       //TODO put on one shared class but see http://www.erichynds.com/blog/backbone-and-inheritance 
       pagination: function(perPage, page) {
            var start = (page - 1) * perPage;
            var end = (perPage * page);
            
            var pageModels =  this.slice(start, end);
            
            //see http://stackoverflow.com/questions/41771742/do-i-need-to-destroy-a-backbone-collection for why we do this. esp. my last comment
            var jsonPages = _.map(pageModels, function(model) {
               return model.toJSON();
            });
            
            return jsonPages;  
        },
        
        setMaxHeight: function() {
            this.labelHeight = app.labelHeight;
            this.maxHeight = app.appData.thumbMaxHeight + (2 * this.labelHeight); 
            this.thumbImageMaxHeight =  app.appData.thumbMaxHeight;
        }
    });
    
    //VIEWS

    //render a given view into a given region, removing any view currently in that region
    Layout = Backbone.View.extend({
        el: "#ca_content_area",
        
        regions: {
            menu: {el: '#ca_menu', view: null},
            main: {el: '#ca_main', view: null},
            body1: {el: 'body', view: null},
            body2: {el: 'body', view: null},
        
        },
  
        
        initialize: function(options) {
            //var loginView = new LoginView({message: ''});
            //this.renderViewIntoRegion(loginView, 'main');
        },

        renderViewIntoRegion: function(view, region)   {
        
            if (this.regions[region].view !== null) {
                this.regions[region].view.remove(); //TODO override remove in views which have to clean up child views    
            }
            
            if (view !== null) {
                var classNameHandle = "viewContainer zone_" + region;
                $(this.regions[region].el).append('<div class="' + classNameHandle + '"></div>');
                view.setElement($(this.regions[region].el).children('.viewContainer.zone_' + region));
                view.render();
                this.regions[region].view = view;    
            }
        
        }
    
    });
    
    BasketView =   Backbone.View.extend({
        initialize: function(options) {
            this.childViews = new Array();
            this.options = options;
            var template = $('#ca_basket').html(); 
            this.tmpl =  _.template(template);
            var headersTemplate = $('#ca_order_line_row_head_tmpl').html();
            this.headersTmpl = _.template(headersTemplate);
        },    
        render: function() {
            var data = {};
            data.langStrings = app.langStrings.toJSON();
            data.show_thumb = true;
            data.row_headers = this.headersTmpl(data);
            this.$el.html(this.tmpl(data));
            var that = this;
            
            var pricing = {};
            pricing.printPrice = null; 
            pricing.mountPrice = null;  
            pricing.framePrices = null;
            pricing.frameStylesToDisplay = null;
            pricing.applicableSizesGroup = null;
            pricing.currency = app.pricingModel.toJSON().currency; 
            
            
            
            this.collection.each(function(orderLine) {
                
                pricing.printPrice = null; 
                pricing.mountPrice = null;  
                pricing.framePrices = null;
                pricing.frameStylesToDisplay = null;
                pricing.applicableSizesGroup = null; 
                var printSize = orderLine.get("print_size");
                var ratio = orderLine.get("image_ratio");
                
                var xhrGetSizesForRatio = app.pricingModel.getSizesForRatio(ratio);
                    
                    xhrGetSizesForRatio.then(
                    
                        function(result) {
                            var printAndMountPriceForPrintSize =  app.pricingModel.getPrintPriceAndMountPriceForSize(result.data, printSize);
                            pricing.printPrice =   printAndMountPriceForPrintSize.printPrice;
                            pricing.mountPrice = printAndMountPriceForPrintSize.mountPrice;
                            var framePriceMatrixForGivenSize  = app.pricingModel.getFramePriceMatrixForSize(result.data, printSize);
                            pricing.framePrices = framePriceMatrixForGivenSize; 
                            pricing.frameStylesToDisplay = app.pricingModel.getFrameDisplayNamesCodesLookup();
                            pricing.applicableSizesGroup = result.data;
                            pricing.mounts = app.pricingModel.toJSON().mounts;
                            var orderLineView = new OrderLineView({model: orderLine, mode: 'update', showThumb: true, pricing: pricing});
                            that.childViews.push(orderLineView);
                            that.$el.find("#ca_basket_order_lines_container").append(orderLineView.render().$el);
                        },
                        function() {
                            var errorView = new ErrorView();   
                            app.layout.renderViewIntoRegion(errorView, 'main');  
                        }
                    ) 
  
	       }, this); 

        },
        //TODO put on one shared class but see http://www.erichynds.com/blog/backbone-and-inheritance
        cleanUp: function() {
            _.invoke(this.childViews, 'remove');
            this.childViews = [];    
        },
         remove: function() {
            this.cleanUp();
            Backbone.View.prototype.remove.call(this);
        }
    });
    

    
    
   CheckoutView =   Backbone.View.extend({
   
        initialize: function(options) {
            var screen1Template = $("#ca_checkout_screen1").html();
            this.screen1Tmpl = _.template(screen1Template);
            var breadcrumbsTemplate =  $("#ca_breadcrumbs").html();
            this.breadcrumbsTmpl = _.template(breadcrumbsTemplate);
            var screen2Template = $("#ca_checkout_screen2").html();
            this.screen2Tmpl =  _.template(screen2Template);
            var messageBarTemple = $("#ca_message_bar").html();
            this.messageBarTmpl = _.template(messageBarTemple);
            var paypalTemplate = $("#ca_paypal_standard").html();
            this.paypalTmpl = _.template(paypalTemplate);
            this.errorState = false;
            this.errorMessage = '';

        },
        
        events: {
            'click #ca_checkout_next1': 'validateAddressScreen',   
            'click #ca_complete_order': 'completeOrder',
            'click span.ca_breadcrumb_active': 'breadCrumbNav',
            'click #ca_address_selector_1': 'resetAddressToOnFile'
        
        },
        
        breadCrumbNav: function() {
            this.render();    
        },
        
        resetAddressToOnFile: function() {
            this.model.resetAddressToOnFile();
            this.errorState = false;
            this.errorMessage = ''; 
            this.render();
        },
        
        validateAddressScreen: function() {
            //check that one address is selected - if custom check fields are populated
            //if error display in error_message area. end.
            
            //if proceed  add to OderModel and - showChargesScreen
            
            var addressType = this.$el.find('input[name="ca_address_selector"]:checked').val();
            if  (typeof(addressType) == "undefined") {
                this.errorState = true;
                this.errorMessage = app.langStrings.get("selectOrEnterAddress");
                this.render();
            } else {
                if (addressType == 'address_on_file') {
                    
                    /*
                    var clientAddress =  $.parseJSON(app.appData.client_address);
                    clientName: clientAddress.clientName,
                        address1: clientAddress.address1,
                        address2: clientAddress.address2,
                        city: clientAddress.city,
                        zip: clientAddress.zip,
                        country: clientAddress.country,
                    */    
                    this.model.set({
                        address_type: 'address_on_file'        
                     }); 
                     //the address has come from site owner config: can't tell user it is invalid so don't validate.
                     this.showChargesScreen();

                }  else {
                        this.model.set({
                            clientName: this.$el.find('#ca_address_name').val(),
                            address1: this.$el.find('#ca_address1').val(),
                            address2: this.$el.find('#ca_address2').val(),
                            city: this.$el.find('#ca_city').val(),
                            zip: this.$el.find('#ca_zip').val(),
                            country: this.$el.find('#ca_country').val(),
                            address_type: 'address_entered'
                         }); 
                         if (!this.model.isValid()) {
                            this.errorState = true;
                            this.errorMessage = this.model.validationError;   
                            this.render();   
                         }  else {
                                that.errorState = true;
                                that.errorMessage = '';  
                                that.showChargesScreen();     
                         }
                 }
            }
        },
        
        completeOrder: function() {
           if (app.appData.enableOnlinePayments && (app.appData.paymentGateway !== false)) {
                var func = app.appData.paymentGateway;
                this[func].call(this); 
           }  else {
                this.manualOrder();
           }
        },
        
        manualOrder: function() {
            //TODO
            //a view which explains what will happen - or simply display the message here next to the button
        
        },

        googleWallet: function(mode) {
        //TODO     - probably show a screen with information on how to make the payment and a link to the site.
        
        } ,
      
        paypalStandard: function() {
           var order = this.model.toJSON(); 
           delete order.deliveryCharges;
           delete order.totalItems;
           delete order.grandTotal;
           var xhr = $.ajax(
                {
                    url: '/api/v1/paypalStandard',
                    method: 'POST',
                    data: {mode: 'online_payment', order: order},
                    dataType: 'json'
                }
            );
            
            var that = this;
            
            xhr.then(
                function(result) {
                    
                        console.log("proceedd to paypal form");
                        var data = {};
                        if (app.appData.mode == "prod") {
                            data.action = 'https://www.paypal.com/cgi-bin/webscr';    
                            data.paypal_email = app.appData.paypalAccountEmail;
                        } else {
                            data.action = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
                            data.paypal_email = app.appData.paypalSandboxAccountEmail;
                        }
                        
                        var loc = document.location;
                        
                        data.payment_title = app.appData.paypalPaymentDescription;
                        data.amount = that.model.get("grandTotal");
                        data.paypal_code = app.pricingModel.get("currency").payPayCode;
                        data.thanks_url =   loc.href + '/thanks';
                        data.charset = 'UTF-8';
                        data.cancel_url =   loc.href + '/cancel';
                        if (app.appData.paypalIPNSSL) {
                            var protocol = 'https://';    
                        }  else {
                            var protocol = 'http://';  
                        }
                        if (app.appData.paypalIPNHandler != '') {//TODO test
                            data.notify_url = protocol + loc.host +  app.appData.paypalIPNHandler;  //TODO handle case of no leading slash on  app.appData.paypalIPNHandler
                        } else {
                            data.notify_url = '';    
                        }
                        data.custom = result.orderRef;
                        data.item_name = app.appData.eventName;
                        console.log(data);
                        var html = that.paypalTmpl(data);
                        that.$el.find('#ca_paypal_form').remove();
                        console.log(html);
                        that.$el.append(html);
                        that.$el.find('#ca_paypal_form').submit();

                   
                
                },
                function() {
                    var errorView = new ErrorView();
                    app.layout.renderViewIntoRegion(errorView, 'main'); 
                }
            );
        
        },
        
        showChargesScreen: function() {
            var data = {}; 
            
            var breadcrumbs = {};
            breadcrumbs.nodes = [{txt: app.langStrings.get("enterAddress"), class: 'ca_breadcrumb_in_chain ca_breadcrumb_active'}, {txt: app.langStrings.get("confirmOrder"), class: 'ca_breadcrumb_in_chain', method: ''}];
            data.breadcrumbs = this.breadcrumbsTmpl(breadcrumbs);
            
            var messageBar = {};
            
      
            messageBar.message = app.appData.printsModeMessage;   
                   
            messageBar.errorState = this.errorState;
            messageBar.errorMessage = this.errorMessage;
            data.message = this.messageBarTmpl(messageBar);
  
            data.enableOnlinePayments = app.appData.enableOnlinePayments;
            data.deliveryChargesEnabled = app.appData.deliveryChargesEnabled;  
            data.langStrings = app.langStrings.toJSON();
            if (app.appData.enableOnlinePayments && (app.appData.paymentGateway !== false)) {
                var field = app.appData.paymentGateway + 'ButtonText';
                data.payButtonText = data.langStrings[field];    
            } else {
                data.payButtonText = data.langStrings['order'];
            }
            
            data.currSymbol = app.pricingModel.get("currency").symbol;
            
            var showScreen = function(totalsData) {
                this.model.set({
                    "deliveryCharges":totalsData.deliveryCharges,
                    "totalItems": totalsData.totalItems,
                    "grandTotal": totalsData.grandTotal
                    });
                tmplData = _.extend(totalsData, data);    
                this.$el.html(this.screen2Tmpl(tmplData)); 
            };
            
            
            if  (data.deliveryChargesEnabled) {
                var xhrDeliveryChargesAndTotals =  app.pricingModel.getCalculateApplicableDevliveryChargesAndTotals();
                var that = this;
                xhrDeliveryChargesAndTotals.then(
                    function(result) {
                        showScreen.call(that, result.data) ;
                    },
                    function() {
                         var errorView = new ErrorView();   //TODO test this
                        app.layout.renderViewIntoRegion(errorView, 'main');    
                    }
                )
            } else {
               var deliveryCharges = 0; 
               showScreen.call(this, data,  totalItems, deliveryCharges, grandTotal);
            }
       
         },
    
        render: function() {
            var data = {};
            var breadcrumbs = {};
            breadcrumbs.nodes = [{txt: app.langStrings.get("enterAddress"), class: 'ca_breadcrumb_in_chain', method: ''}, {txt: app.langStrings.get("confirmOrder"), class: '', method: ''}];
            data.breadcrumbs = this.breadcrumbsTmpl(breadcrumbs);
            var messageBar = {};
            messageBar.message = app.appData.printsModeMessage;    
      
            messageBar.errorState = this.errorState;
            messageBar.errorMessage = this.errorMessage;
            data.message = this.messageBarTmpl(messageBar);
            data.fileClientAddress = $.parseJSON(app.appData.client_address);
            var address_type = this.model.get("address_type");
            data.address_entered_checked = '';
            data.address_on_file_checked = '';
            if (address_type === 'address_entered') {
                data.address_entered_checked = 'checked';    
            }
            if (address_type === 'address_on_file') {
                data.address_on_file_checked = 'checked';    
            }
            data.clientName = this.model.get("clientName");
            data.address1 = this.model.get("address1");
            data.address2 = this.model.get("address2");
            data.city = this.model.get("city");
            data.zip = this.model.get("zip");
            data.country = this.model.get("country");
            data.langStrings = app.langStrings.toJSON();
            this.$el.html(this.screen1Tmpl(data));    
        }
    
    });
    
    
    
    
    ErrorView =   Backbone.View.extend({
        //either called with message on the options in which case display that or with nothing (from a 500) in which case we need a standard message)
        render: function() {
            this.$el.html('error TODO');    
        }
    
    });  
    
    PaypalThanksView =   Backbone.View.extend({
        initialize: function() {
            var template =  $('#ca_paypal_thanks').html(); 
            this.tmpl = _.template(template);
        
        },
        render: function() {
            data = {};
            data.langStrings = app.langStrings.toJSON();
            var html = this.tmpl(data);
            this.$el.html(html);     
        }
    
    });  
   
   
    PaypalCancelView =   Backbone.View.extend({
        
        initialize: function() {
            var template =  $('#ca_paypal_cancel').html(); 
            this.tmpl = _.template(template);
        
        },
        render: function() {
            data = {};
            data.langStrings = app.langStrings.toJSON();
            var html = this.tmpl(data);
            this.$el.html(html);     
        }
    
    });  
     
    LogoutView =   Backbone.View.extend({
    
        render: function() {
            this.$el.html('logout');    
        }
    
    });
    
   
    PrintsMenuView =  Backbone.View.extend({
    
        initialize: function(options) {
            this.options = options;
            var buttonsTemplate =  $('#ca_pagination_buttons').html(); 
            this.buttonsTmpl = _.template(buttonsTemplate);
            var menuTemplate = $('#ca_prints_menu').html(); 
            this.menuTmpl =  _.template(menuTemplate);
        },    
       
        render: function() {
            var buttonData = {};
            buttonData.total_pages = Math.ceil(this.options.totalThumbs / this.options.thumbsPerPage);
            buttonData.active = this.options.active;
            buttons = this.buttonsTmpl(buttonData);
            var data = {};
            data.active =  this.options.active;
            data.basket_label = app.langStrings.get("basketButtonText");
            if (app.appData.enableOnlinePayments) {
                data.checkout_label = app.langStrings.get("checkoutButtonText");     
            } else {
               data.checkout_label = app.langStrings.get("order"); 
            }
            
            data.langStrings = app.langStrings.toJSON();
            var menu = this.menuTmpl(data)
            this.$el.html(menu);
        },
        
        events: {
            'click .ca_page_number_event': 'changePage',
            'click .ca_basket_event': 'showBasket',
            'click .ca_checkout_event': 'showCheckout',
            'click .ca_logout_event': 'showLogout'
        
        },
        
        showBasket: function() {
            this.options.active = 'basket';
            var that = this;
            //getting it again in case its been cleared by the PayPal IPN
            app.basketCollection.fetch({reset: true}).then( 
                function() {
                    var basketView =  new BasketView({collection: app.basketCollection});
                    app.layout.renderViewIntoRegion(basketView, 'main'); 
                    that.render();
                },
                function() {
                    var errorView = new ErrorView();   //TODO test this
                    app.layout.renderViewIntoRegion(errorView, 'main');
                }
            );
        },
        
       showLogout: function() {
            var logoutView =  new LogoutView();
            app.layout.renderViewIntoRegion(logoutView, 'main'); 
        } ,
            
        showCheckout: function() {
            var view = new CheckoutView({model: new OrderModel()});
            app.layout.renderViewIntoRegion(view, 'main'); 
        },
        
        changePage: function(evt) {
            var targetPage = $(evt.currentTarget).data('index');
            var thumbsPerPage = parseInt(app.appData.thumbsPerPage);
            var pageModelsJSON = app.printsThumbsCollection.pagination(thumbsPerPage, targetPage);
            var pagedCollection = new  Backbone.Collection(pageModelsJSON);
            var thumbsView = new ThumbsView({collection: pagedCollection, mode: 'prints', maxHeight: app.printsThumbsCollection.maxHeight, thumbImageMaxHeight: app.printsThumbsCollection.thumbImageMaxHeight, labelHeight: app.printsThumbsCollection.labelHeight });
            app.layout.renderViewIntoRegion(thumbsView, 'main');
            this.options.active = targetPage;
            this.render();
        } 
    
    })
    
    
    ProofsMenuView =  Backbone.View.extend({
    
        initialize: function(options) {
            this.options = options;
            var buttonsTemplate =  $('#ca_pagination_buttons').html(); 
            this.buttonsTmpl = _.template(buttonsTemplate);
            var menuTemplate = $('#ca_proofs_menu').html(); 
            this.menuTmpl =  _.template(menuTemplate);
        },    
       
        render: function() {
            var buttonData = {};
            buttonData.total_pages = Math.ceil(this.options.totalThumbs / this.options.thumbsPerPage);
            buttonData.active = this.options.active;
            buttons = this.buttonsTmpl(buttonData);
            var data = {};
            data.active =  this.options.active;
            data.langStrings = app.langStrings.toJSON();
            var menu = this.menuTmpl(data)
            this.$el.html(menu);
        },
        
        events: {
            'click .ca_page_number_event': 'changePage',
            'click .ca_proof_event': 'showDone',
            'click .ca_logout_event': 'showLogout'
        
        },
        
       showLogout: function() {
            var logoutView =  new LogoutView();
            app.layout.renderViewIntoRegion(logoutView, 'main'); 
        } ,
            
        showDone: function() {
            console.log("showDone");
        },
        
        changePage: function(evt) {
            console.log("called1");
            var targetPage = $(evt.currentTarget).data('index');
            var thumbsPerPage = parseInt(app.appData.thumbsPerPage);
            var pageModelsJSON = app.proofsThumbsCollection.pagination(thumbsPerPage, targetPage);
            var pagedCollection = new  Backbone.Collection(pageModelsJSON);
            console.log("check", app.proofsThumbsCollection.maxHeight, app.proofsThumbsCollection.thumbImageMaxHeight, app.proofsThumbsCollection.labelHeight )   ;
            var thumbsView = new ThumbsView({collection: pagedCollection, mode: 'proofs', maxHeight: app.proofsThumbsCollection.maxHeight, thumbImageMaxHeight: app.proofsThumbsCollection.thumbImageMaxHeight, labelHeight: app.proofsThumbsCollection.labelHeight });
            app.layout.renderViewIntoRegion(thumbsView, 'main');
            this.options.active = targetPage;
            this.render();
        } 
    
    })
    
    //in Marionette this would be an ItemViewCollection
    ThumbsView =  Backbone.View.extend({
        initialize: function(options) {
            console.log("here1");
            this.options = options;
            this.childViews = new Array();
            
        },
        
        render: function()
        {
            //loop through collection and display the page.
            //first take - just display them all
             var mode = this.options.mode;
             this.collection.each(function(thumb) {
                if (mode == 'prints') {
                    var thumbView = new PrintThumbView({model: thumb, maxHeight: this.options.maxHeight, thumbImageMaxHeight: this.options.thumbImageMaxHeight, labelHeight: this.options.labelHeight}) ;
                }  else {
                    console.log("here thumb", thumb);
                    var thumbView = new ProofsThumbView({model: thumb, maxHeight: this.options.maxHeight, thumbImageMaxHeight: this.options.thumbImageMaxHeight, labelHeight: this.options.labelHeight}) ;    
                }
                
                this.$el.append(thumbView.render().$el);        //TODO height row equalisation  
                this.childViews.push(thumbView);//TODO consider all the places we need to cleanly remove this view
	       }, this);
        } ,
        
        cleanUp: function() {
            _.invoke(this.childViews, 'remove');
            //this.childViews = [];    
            this.childViews.length = 0;   
        },
        
        remove: function() {
            this.cleanUp();
            Backbone.View.prototype.remove.call(this);
        }
    });     
    
    PrintPopUpView = Backbone.View.extend({
       
        initialize: function(options) {
            this.options = options;
            this.listenTo(this.collection, "add", this.renderOrderLines);
            this.childViews = new Array();
            var containerTemplate =  $('#ca_print_popup_tmpl').html(); 
            this.containerTmpl = _.template(containerTemplate);
            var tmplRoWHead =  $('#ca_order_line_row_head_tmpl').html(); 
            this.tmplRoWHead = _.template(tmplRoWHead);
        },
        
        events: {
            'click .ca_lightbox_close_event': 'close'
        },
       
        close: function() {
            app.layout.renderViewIntoRegion(null, 'body1');
            app.layout.renderViewIntoRegion(null, 'body2');     
        },    
        
        renderOrderLines: function() {  
            this.cleanUp();
            var pricing = {};
            pricing.printPrice = null; 
            pricing.mountPrice = null;  
            pricing.framePrices = null;
            pricing.frameStylesToDisplay = null;
            pricing.applicableSizesGroup = null;
            pricing.currency = app.pricingModel.toJSON().currency; 
            var that = this;

            renderNewFreshOrderLine = function() {
                var orderLine = new OrderLineModel();
                orderLine.set("image_ref", that.options.file);
                var ratio =  that.options.ratio;
                orderLine.set("image_ratio", ratio);
                orderLine.set("path", that.options.path);
                var xhrGetSizesForRatio = app.pricingModel.getSizesForRatio(ratio);
                    
                xhrGetSizesForRatio.then(
                    function(result) {
                        pricing.applicableSizesGroup = result.data;
                        pricing.frameStylesToDisplay = app.pricingModel.getFrameDisplayNamesCodesLookup();
                        pricing.mounts = app.pricingModel.toJSON().mounts;
                        var orderLineView = new OrderLineView({model: orderLine, mode: 'new', showThumb: false, pricing: pricing});  
                        that.childViews.push(orderLineView);
                        that.$el.find("#ca_order_lines_container").append(orderLineView.render().$el); 
                       },
                       function() { 
                            var errorView = new ErrorView();   
                            app.layout.renderViewIntoRegion(errorView, 'main'); 
                       }     
                );
            }
          var itemsInBasketForThisImage = this.collection.byImage(this.options.file).length;   
          if (itemsInBasketForThisImage < 1) {
                renderNewFreshOrderLine();  
          }  

         //1. render existing order lines
          
        var i = 0;
        this.collection.each(function(orderLine) {
        
            if (orderLine.get("image_ref") == this.options.file) {   //TODO get the filtered basket to work  not just to get the length!
            
                    var printSize = orderLine.get("print_size");
                    var ratio = orderLine.get("image_ratio");
                        
                    var xhrGetSizesForRatio = app.pricingModel.getSizesForRatio(ratio);
                    
                    xhrGetSizesForRatio.then(
                    
                        function(result) {
                            pricing.mounts = app.pricingModel.toJSON().mounts;
                            pricing.applicableSizesGroup = result.data;
                            //TODO the problem is we now pass data returned from this model back into the model for processing
                            //problem is not resolved.
                            var printAndMountPriceForPrintSize =  app.pricingModel.getPrintPriceAndMountPriceForSize(result.data, printSize);
                            pricing.printPrice = printAndMountPriceForPrintSize.printPrice;
                            pricing.mountPrice = printAndMountPriceForPrintSize.mountPrice;
                            
                            var framePriceMatrixForGivenSize  = app.pricingModel.getFramePriceMatrixForSize(result.data, printSize);
                            pricing.framePrices = framePriceMatrixForGivenSize; 
                            
                            pricing.frameStylesToDisplay = app.pricingModel.getFrameDisplayNamesCodesLookup()
                        
                            var orderLineView = new OrderLineView({model: orderLine, mode: 'update', showThumb: false, pricing: pricing});
                            that.childViews.push(orderLineView);
                            that.$el.find("#ca_order_lines_container").append(orderLineView.render().$el);
                            i++; 
                            if (i == itemsInBasketForThisImage) {
                                renderNewFreshOrderLine();
                            }
                        },
                        function() {
                            var errorView = new ErrorView();   
                            app.layout.renderViewIntoRegion(errorView, 'main');  
                        }
                    )    
                           
               }             
    	    }, this); 
        },  

        render: function() {
            var data = {};
            data.path = this.options.path.replace("thumbs", "main");
            data.show_thumb = false;
            data.langStrings = app.langStrings.toJSON();
            data.row_headers = this.tmplRoWHead(data);
            
            //set the overlay element hmm... needs to be apenned to the body as well... body1 and body2????
            var overlay = new OverlayView();
            app.layout.renderViewIntoRegion(overlay, 'body2');  
            
            var html = this.containerTmpl(data);
            this.$el.html(html); 
            
            //deal with sizing.... 
            var actualImageWidth = this.options.mainWidth;
            var actualImageHeight  = this.options.mainHeight;
            var maxPopupWidth = app.maxPopupWidth;
            var lightBoxWidthFraction = app.lightBoxWidthFraction;
            var lightBoxWidthPercent = (100*lightBoxWidthFraction)  + '%';
            var lightBoxWidth = maxPopupWidth + 'px'; 
            var safeImageHeight = $(window).height() - 100;
            this.$el.css({'max-width': lightBoxWidth ,'width': lightBoxWidthPercent}); 
            
            if (actualImageHeight > (safeImageHeight)) //height is too big (portrait images on landscape orientation phones)
            {
                var heightExceedsRatio = actualImageHeight /  safeImageHeight;
                var modifiedWidth = actualImageWidth /  heightExceedsRatio;
                this.$el.find("img").css({"max-width": Math.floor(modifiedWidth) + "px", "width": "100%"});   
            }
            else
            {
                this.$el.find("img").css({"max-width": Math.floor(actualImageWidth) + "px", "width": "100%", "height": "auto"});
            }
            this.renderOrderLines();
      
        },
        
        
        cleanUp: function() {
            _.invoke(this.childViews, 'remove');
            this.childViews = [];    
        },
        
         remove: function() {
            this.cleanUp();
            Backbone.View.prototype.remove.call(this);
        }
    });
    
    OverlayView = Backbone.View.extend({
        
        render: function() {
            this.$el.html('<div class="ca_lightbox_overlay"></div>');
        }
        
    
    })
    
    ProofsThumbView =  Backbone.View.extend({
        tag: 'div',
   
        initialize: function(options) {
                this.options = options;   
                var template =  $('#ca_proofs_thumb_tmpl').html(); 
                this.tmpl = _.template(template);
                this.proofsBasket = app.proofsBasketCollection;
                console.log("this.proofsBasket", this.proofsBasket)    ;
        },
        
        events: {
            'click .ca_proofs_thumb_pic_event': 'showPopUp'
        
        },

        showPopUp: function() {
            var file = this.model.get("file");
            var path = this.model.get("path");
            
            var ratio;
            var mainWidth; 
            var mainHeight; 
            
            var showPopUp = function() {
            
                var view = new PrintPopUpView({
                    file: file, 
                    path: path, 
                    ratio: ratio,
                    pricingModel: app.pricingModel, 
                    collection: app.basketCollection, 
                    mainWidth: mainWidth, 
                    mainHeight: mainHeight
                });
                app.layout.renderViewIntoRegion(view, 'body1');
            
            } 
            
            var imageRatio = this.model.get("image_ratio");
            var mainImageDimensions = this.model.get("main_image_dimensions");
            
            if ((imageRatio == null) || (mainImageDimensions == null) )
            {
                var xhrGetImageDimensions  =  $.ajax(
                        {
                            url: '/api/v1/imageDimensions/'+file+'/main/prints',
                            method: 'GET',
                            dataType: 'json'
                        }
                ); 
                var that = this;
                xhrGetImageDimensions.then(
                    function(result) {
                        ratio = result.ratio;
                        mainWidth = result.dimensions.width;
                        mainHeight =  result.dimensions.height;
                        that.model.set("image_ratio", ratio);     
                        that.model.set("main_image_dimensions", result.dimensions);
                        showPopUp();
                    
                    }, 
                    function() {
                        var errorView = new ErrorView();   //TODO test this
                        app.layout.renderViewIntoRegion(errorView, 'main'); 
                    }
               ); 
            
            }   else {
                ratio = imageRatio;
                mainWidth =  mainImageDimensions.width;
                mainHeight = mainImageDimensions.height;
                showPopUp();
            }
        
        },
        
        render: function() {
            console.log("rendering ProofsThumbView");
            var data = this.model.toJSON();
            data.thumbStyle = "height: " + this.options.maxHeight + "px";
            data.thumbImageMaxHeight =  "max-height: " + this.options.thumbImageMaxHeight + "px";
            data.labelStyle = "font-size: " +  this.options.labelHeight + "px";
            data.alt_text = "";
            data.label = data.file;
            console.log("data", data);
            
          
            var thumbInProofsBasket = this.proofsBasket.find(function(model) {return model.get('file_ref') == data.file});
     
            if (thumbInProofsBasket != undefined) {
                data.checked = true;     
            }     else {
                data.checked = false;
            }
            
            var html = this.tmpl(data);
            this.$el.html(html);
            return this; 
        },

    })  
    
    PrintThumbView =  Backbone.View.extend({
        tag: 'div',
   
        initialize: function(options) {
                this.options = options;   
                var template =  $('#ca_print_thumb_tmpl').html(); 
                this.tmpl = _.template(template);
        },
        
        events: {
            'click .ca_print_thumb_pic_event': 'showPopUp'
        
        },

        showPopUp: function() {
            var file = this.model.get("file");
            var path = this.model.get("path");
            
            var ratio;
            var mainWidth; 
            var mainHeight; 
            
            var showPopUp = function() {
            
                var view = new PrintPopUpView({
                    file: file, 
                    path: path, 
                    ratio: ratio,
                    pricingModel: app.pricingModel, 
                    collection: app.basketCollection, 
                    mainWidth: mainWidth, 
                    mainHeight: mainHeight
                });
                app.layout.renderViewIntoRegion(view, 'body1');
            
            } 
            
            var imageRatio = this.model.get("image_ratio");
            var mainImageDimensions = this.model.get("main_image_dimensions");
            
            if ((imageRatio == null) || (mainImageDimensions == null) )
            {
                var xhrGetImageDimensions  =  $.ajax(
                        {
                            url: '/api/v1/imageDimensions/'+file+'/main/prints',
                            method: 'GET',
                            dataType: 'json'
                        }
                ); 
                var that = this;
                xhrGetImageDimensions.then(
                    function(result) {
                        ratio = result.ratio;
                        mainWidth = result.dimensions.width;
                        mainHeight =  result.dimensions.height;
                        that.model.set("image_ratio", ratio);     
                        that.model.set("main_image_dimensions", result.dimensions);
                        showPopUp();
                    
                    }, 
                    function() {
                        var errorView = new ErrorView();   //TODO test this
                        app.layout.renderViewIntoRegion(errorView, 'main'); 
                    }
               ); 
            
            }   else {
                ratio = imageRatio;
                mainWidth =  mainImageDimensions.width;
                mainHeight = mainImageDimensions.height;
                showPopUp();
            }
        
        },
        
        render: function() {
            var data = this.model.toJSON();
            data.in_basket_class = "";         //TODO - we don't know unless we look at the   app.basketCollection
            data.thumbStyle = "height: " + this.options.maxHeight + "px";
            data.thumbImageMaxHeight =  "max-height: " + this.options.thumbImageMaxHeight + "px";
            data.labelStyle = "font-size: " +  this.options.labelHeight + "px";
            data.alt_text = "";
            data.label = data.file;
            var html = this.tmpl(data);
            this.$el.html(html);
            return this; 
        },
        
        remove: function() {
            this.basketItemsForImage = null;           //TDODO check this logic
            Backbone.View.prototype.remove.call(this);    
        } 
    
    })   
    
    
    TitleView = Backbone.View.extend({
    
        tagName: 'div',
        
        initialize: function(options) {
            this.options = options;
            var tmpl =  $('#ca_thumb_title').html();
            this.tmplFunc = _.template(tmpl);
        },
        
        render: function() {
            var data = {};
            data.image_ref = this.options.image_ref;
            var html = this.tmplFunc(data);
            this.$el.append(html);
            return this;
        }
        
    })

    
    //OrderLine View - a view to display a single order line
    OrderLineView = Backbone.View.extend({
        tagName: 'div',
        className: 'ca_row', 
        template: null,
         initialize: function(options) {
            this.listenTo(this.model, "invalid", this.displayModelErrors);
            //this.listenTo(this.model, "change:total_price", this.render);
            this.listenTo(this.model, "sync", this.modelSynced);
            this.listenTo(this.model, "destroy", this.removeMe);
            var tmpl =  $('#ca_order_line_tmpl').html();
            this.template = _.template(tmpl);
            this.options = options;
            this.pricing = options.pricing;

        },
        
        events: {
           'click .ca_add_event': 'onAdd',
           'click .ca_update_event': 'onUpdate',
           'click .ca_remove_event': 'onRemove',
           'change .ca_print_size_event': 'onChangePrintSize' ,
           'change .ca_mount_event': 'onChangeMount',
           'change .ca_frame_event': 'onChangeFrame',
           'mouseover .ca_basket_thumb_hover_event': 'showFileName',
           'mouseout .ca_basket_thumb_hover_event': 'hideFileName',
           'touchstart .ca_basket_thumb_hover_event': 'showFileName',
           'touchend .ca_basket_thumb_hover_event': 'hideFileName',
           'touchcancel .ca_basket_thumb_hover_event': 'hideFileName',
           'input .ca_qty_event': 'onChangeQty',     /* input is better but IE9 does not support backspace or delete. keyup doesn't capture backspace. keydown is too soon - fires before change. keypress is deprecated in favour of input */
           'change .ca_qty_event': 'onChangeQty' /* fully support IE9 at the cost of an unnessary update */
        }, 
        
        showFileName: function(evt) {
            evt.preventDefault();
            this.titleView = new TitleView({image_ref: this.model.get("image_ref")});
            this.$el.find(".ca_basket_thumb").append($('<div>').addClass("ca_title_holder"));
            this.titleView.setElement(this.$el.find('.ca_title_holder'));
            this.titleView.render();
        },
        
        hideFileName: function(evt) {
           evt.preventDefault();
           this.titleView.remove();
        },

        displayModelErrors: function() {
           var that = this;
            _.each(this.model.validationError.fields, function(field) {
                that.$el.find('.ca_' + field + '_group').addClass("has-error");
            });
            this.$el.find(".ca_order_info").html(this.model.validationError.errString);     
        },
        
        clearErrors: function(field) {
            var that = this;
            if (typeof(field) !== "undefined") {
                that.$el.find('.ca_' + field + '_group').removeClass("has-error");    
            }  else {
                that.$el.find('.has-error').removeClass("has-error");
            }
            this.$el.find(".ca_order_info").html("");     
        },
        
        processSelects: function(val) {
        
            if (val == "--") {
                val = null;
            }
            return val;
        
        },
        
        onChangePrintSize: function(evt) {
            var that = this;
            var printSize = evt.currentTarget.value;
            this.model.set({'print_size': this.processSelects(printSize), "edit_mode": "edit"});
            var imageRatio = this.model.get("image_ratio");
            
            var xhrGetSizesForRatio = app.pricingModel.getSizesForRatio(imageRatio);  
            xhrGetSizesForRatio.then(
            
                function(result) {
                    var printAndMountPriceForPrintSize =  app.pricingModel.getPrintPriceAndMountPriceForSize(result.data, printSize);
                    var framePriceMatrixForGivenSize  = app.pricingModel.getFramePriceMatrixForSize(result.data, printSize);
                    that.model.set("print_price", printAndMountPriceForPrintSize.printPrice);
                    that.pricing.mountPrice = printAndMountPriceForPrintSize.mountPrice;
                    that.pricing.framePrices =   framePriceMatrixForGivenSize; 
                    //handle case that before changing the print size the user has selected mount and/or frame options
                    //we could not set the model pricing at that point because we didn't know the print size
                    that.$el.find(".ca_mount_event").trigger("change");
                    that.$el.find(".ca_frame_event").trigger("change");
                    
                },
                function() {
                    //TODO actually this is more complicated we MAY BE in the popup - when we show the errorView always close the popup and hide the menu?
                    var errorView = new ErrorView();
                    app.layout.renderViewIntoRegion(errorView, 'main'); 
                } 
            );
 
        },     

        onChangeMount: function(evt) {
            var mount = evt.currentTarget.value;
            var mountValue = this.processSelects(mount);
            this.model.set({'mount_style': mountValue, "edit_mode": "edit"});
            var  printSize =   this.model.get("print_size");
            var ratio = this.model.get("image_ratio");
            if ((mountValue !== null) && (mountValue != "no_mount") && (printSize !== null)) {
                var that = this;
                var xhrGetSizesForRatio = app.pricingModel.getSizesForRatio(ratio);  
                xhrGetSizesForRatio.then(
                    function(result) {
                        var printAndMountPriceForPrintSize =  app.pricingModel.getPrintPriceAndMountPriceForSize(result.data, printSize);
                        that.model.set({'mount_price': printAndMountPriceForPrintSize.mountPrice, "mount_style": mountValue});
                        that.render();  
                    },
                    function() {
                        //TODO actually this is more complicated we MAY BE in the popup - when we show the errorView always close the popup and hide the menu?
                        var errorView = new ErrorView();
                        app.layout.renderViewIntoRegion(errorView, 'main'); 
                    }
                );
                
            } else {
                this.model.set({'mount_price': 0, "mount_style": mountValue});
                this.render();   
            }
        },
        
        onChangeFrame: function(evt) {
            var frame = evt.currentTarget.value;
            var frameValue = this.processSelects(frame);
            this.model.set({'frame_style': frameValue, "edit_mode": "edit"});
            var  printSize =   this.model.get("print_size");
            var ratio = this.model.get("image_ratio");
            
            if ((frameValue !== null) && (frameValue != "no_frame") && (printSize !== null)) {
                var that = this;
                var xhrGetSizesForRatio = app.pricingModel.getSizesForRatio(ratio);  
                xhrGetSizesForRatio.then(
                    function(result) {
                        var framePriceMatrixForGivenSize  = app.pricingModel.getFramePriceMatrixForSize(result.data, printSize);
                        that.model.set({'frame_price': framePriceMatrixForGivenSize[frameValue], "frame_style": frameValue, "frame_display_name": that.pricing.frameStylesToDisplay[frameValue]});    //TODO does this last one have to be passed around? can't we just get it here
                        that.render();  
                    },
                    function() {
                        //TODO actually this is more complicated we MAY BE in the popup - when we show the errorView always close the popup and hide the menu?
                        var errorView = new ErrorView();
                        app.layout.renderViewIntoRegion(errorView, 'main'); 
                    }
                );
       
                
            } else {
                this.model.set({'frame_price': 0, "frame_style": frameValue});   
                this.render();  
            }
        },
        
       onChangeQty: function(evt) {
            var qty = evt.currentTarget.value;
            this.model.set({"edit_mode": "edit", 'qty': qty});
            this.clearErrors('qty');
            this.render(); 
      },

        onAdd: function() {
            if (this.model.isValid()) {
                this.clearErrors();
                var newOrderLine =  this.model.toJSON();
                app.basketCollection.create(newOrderLine, {wait: true});
           }
        },
        
        modelSynced: function(model) {
            this.render();
        },
        
        updateSuccess: function(model) {
            model.set("edit_mode", "save");
        },
        
       updateError: function(model) {  //TDOD
        },
        
        onUpdate: function() {
            if (this.model.isValid()) {
                this.clearErrors();
                this.model.save(null, {wait: true, success: this.updateSuccess, error: this.updateError});
           }
        },
        
        //with wait: true, the destroy event triggered by model.destroy is only fired after a successful response from the server. 
        onRemove: function() {
            this.model.destroy({wait: true});
        }, 
      
        removeMe: function() {
              this.remove();
        },
                    
        render: function() {
            var data = {};
            data.order = this.model.toJSON();

            var editMode = this.model.get("edit_mode");
            data.editStateIcon = "fa-" +  editMode; 

            data.mode = this.options.mode;
            
            data.langStrings = {};
            data.langStrings = app.langStrings.toJSON();
            data.alt_text = this.model.get("image_ref");
            data.mountPrice = null;
            data.framePrices = null;
            data.show_thumb = this.options.showThumb;             
            data.printPrice = this.model.get("print_price");      
            data.framePrice = this.model.get("frame_price"); 
            
            data.mountPrice = this.pricing.mountPrice;    
            data.framePrices =  this.pricing.framePrices;    
            data.frameStylesToDisplay = this.pricing.frameStylesToDisplay;  
            data.applicableSizesGroup = this.pricing.applicableSizesGroup;
            data.mounts = this.pricing.mounts;
            data.currency = this.pricing.currency;
            data.path = this.model.get("path");
            var html = this.template(data);
            this.$el.html(html);
            return this;
        }    
    });
    
    ModeChoiceView =  Backbone.View.extend({
    
        initialize: function(options) {
            var modeChoiceTmpl =  $('#ca_mode_choice_tmpl').html();
            this.modeChoiceTmpl = _.template(modeChoiceTmpl);
        }, 
        
        events: {
            'click .ca_choose_activity_event': 'doModeChoice'
        },  
        //TODO a spinner
        doModeChoice: function() {
            var mode = this.$el.find("#ca_activity_choice").val();
            if (mode != "--") {
                var thumbsPerPage = parseInt(app.appData.thumbsPerPage);
                
                var xhrCreateBasket = $.ajax(
                        {
                            url: '/api/v1/createBasket/'+mode,
                            method: 'GET',
                            dataType: 'json'
                        }
              );
                
                
                if (mode == 'prints') {
                    //reset true in case there are 2 mode choices in session. 
                    
                    
                    xhrCreateBasket.then(
                        function() {
                            var xhrPricingModel = app.pricingModel.fetch({reset: true});
                            var xhrBasketCollection = app.basketCollection.fetch({reset: true})  ;
                            var xhrPrintsThumbsCollection = app.printsThumbsCollection.fetch({reset: true});
                   
                            $.when(xhrPricingModel, xhrBasketCollection, xhrPrintsThumbsCollection).then(
                                function(result1, result2, result3) {
                                    
                                    var pageModelsJSON = app.printsThumbsCollection.pagination(thumbsPerPage, 1);
                                    var pagedCollection = new  Backbone.Collection(pageModelsJSON);
                
                                    var thumbsView = new ThumbsView({collection: pagedCollection, mode: mode, maxHeight: app.printsThumbsCollection.maxHeight, thumbImageMaxHeight: app.printsThumbsCollection.thumbImageMaxHeight, labelHeight: app.printsThumbsCollection.labelHeight });
                                    app.layout.renderViewIntoRegion(thumbsView, 'main');
                                    var menuView = new PrintsMenuView({totalThumbs: app.printsThumbsCollection.length, thumbsPerPage: thumbsPerPage, active: 1});
                                    app.layout.renderViewIntoRegion(menuView, 'menu');
                                
                                },
                                function() {
                                    var errorView = new ErrorView();   //TODO test this
                                    app.layout.renderViewIntoRegion(errorView, 'main');  
                                }    
                            );
                        },
                        function()
                        {
                            var errorView = new ErrorView();   //TODO test this
                            app.layout.renderViewIntoRegion(errorView, 'main'); 
                        }
                    
                    
                    )
                                                                              
                    
                } else {
                    xhrCreateBasket.then(
                        function()
                        {
                               
                            var xhrProofsThumbsCollection = app.proofsThumbsCollection.fetch({reset: true}) ; 
                            var xhrProofsBasketCollection = app.proofsBasketCollection.fetch({reset: true});
                            $.when(xhrProofsThumbsCollection, xhrProofsBasketCollection).then(
                            function(result1, result2) {
                                                          
                                var pageModelsJSON = app.proofsThumbsCollection.pagination(thumbsPerPage, 1);
                                var pagedCollection = new  Backbone.Collection(pageModelsJSON);
            
                                var thumbsView = new ThumbsView({collection: pagedCollection, mode: mode, maxHeight: app.proofsThumbsCollection.maxHeight, thumbImageMaxHeight: app.proofsThumbsCollection.thumbImageMaxHeight, labelHeight: app.proofsThumbsCollection.labelHeight });
                                app.layout.renderViewIntoRegion(thumbsView, 'main');
                                var menuView = new ProofsMenuView({totalThumbs: app.proofsThumbsCollection.length, thumbsPerPage: thumbsPerPage, active: 1});
                                app.layout.renderViewIntoRegion(menuView, 'menu');
                            },
                            function() {
                                var errorView = new ErrorView();   //TODO test this
                                app.layout.renderViewIntoRegion(errorView, 'main');
                            }
                        );   
                        },
                        function()
                        {
                            var errorView = new ErrorView();   //TODO test this
                            app.layout.renderViewIntoRegion(errorView, 'main'); 
                        }
                    )
 
                }
            }
        }, 
        
        render: function() {
            data = {};
            data.langStrings = app.langStrings.toJSON(); //TODO do this in other places
             
            if (!app.appData["proofs_on"]) {
                data.proofsOn = "disabled";  
            } else {
                data.proofsOn = "";     
            }
            
            if (!app.appData["prints_on"]) {
                data.printsOn = "disabled";  
            } else {
                data.printsOn = "";     
            }
            
            data.human_name = app.appData["human_name"];
            
            var html = this.modeChoiceTmpl(data);
            this.$el.html(html); 
        }
    
    });
    
    
    
    LogoutMenuView = Backbone.View.extend({
    
         initialize: function() {
            var template =  $('#ca_logout_menu').html();
            this.tmpl = _.template(template);
        },
        
        events: {
            'click .ca_logout_event': 'showLogout'
        },
        
       showLogout: function() {
            var logoutView =  new LogoutView();
            app.layout.renderViewIntoRegion(logoutView, 'main'); 
        },
        
        render: function() {
            data = {};
            data.langStrings = app.langStrings.toJSON();
            var html = this.tmpl(data);
            this.$el.html(html);  
        }
  
    }); 
    
    LoginView = Backbone.View.extend({
          
        initialize: function(options) {
            var loginTmpl =  $('#ca_login_tmpl').html();
            this.loginTemplate = _.template(loginTmpl);
            this.options = options;
        
        },
        
        events: {
            'click .ca_login_button': 'doLogin',
        
        },
        
        doLogin: function() {
            //TODO - any preliminary validation
            var user = this.$el.find('#ca_login_name').val();
            var password = this.$el.find('#ca_login_password').val();
            
            var data = {};
            data.name = user;
            data.password = password;

            var clientAreaStorage = new ClientAreaStorage(user);
            //TODO are we using any of this?
            if (clientAreaStorage.supported) {
                data.restoredProofs = clientAreaStorage.getValueAsString("ca_proofs")
                data.restoredProofsPagesVisited = clientAreaStorage.getValueAsString("ca_proofs_pages_visited");
                data.restoredPrintsPagesVisited =  clientAreaStorage.getValueAsString("ca_prints_pages_visited"); 
            }     

            var p = $.ajax({
                url: '/api/v1/login',
                dataType: 'json',
                data: data,
                method: 'POST'
            });
        
            var that = this;
            p.then(function(result) {
                if (result.status == "success") {
                    app.appData = result.appData;
                    var modeChoiceView = new ModeChoiceView();
                    app.layout.renderViewIntoRegion(modeChoiceView, 'main');
                 } else {
                    that.setMessage(result.message);
                }
            });

        },
        
        setMessage: function(msg) {
            this.$el.find('.ca_login_error').html(msg);
        },
        

        
        render: function() {
            data = {};
            data.langStrings = app.langStrings.toJSON();
            data.msg = this.options.message;
            var html = this.loginTemplate(data);
            this.$el.html(html);         
        }
        

    });
    
    
    //UTILITIES
    
    ClientAreaStorage = function(username, _) {
      
         this.username = username;
         this._ = _;
  
         if ( (JSON && typeof JSON.parse === 'function') && (typeof(Storage) !== "undefined") &&
             (typeof(Array.prototype.indexOf) === "function")) {
             this.supported = true;
         } else {
             this.supported = false;
        }
  
      }
  
      //TODO make the private methods private?
      //http://www.crockford.com/javascript/private.html
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
      
      
      /**
       * add or update an element in the values array for the given key 
       * @value the new or updated object/value
       * @key the name of the key in storage which is being updated
       */
      ClientAreaStorage.prototype.updateStorage = function(key, value) {
  
          if (!this.supported) {
              return;
          }

          var storedData = this.getValueAsArray(key);
            
          //it is empty. first one. just pop it in
          if (storedData.length == 0) {
            storedData.push(value);        
          } else {
            
                //is the first one a scalar or object. we assume we haven't messed up and got mixed values
                if (this._.isObject(storedData[0])) {
                    //object add/update
                    storedData = this._.without(storedData, this._.findWhere(storedData, {id: value.id})); 
                    storedData.push(value);          
                } else {
                    //scalar add/update - simple
                    if (!storedData.indexOf(value) >= 0) {
                        storedData.push(value);
                    } 
                }
         } 
         this.setValueFromArray(key, storedData);
      }
      
      /**
       * reset the entire storage array for given key
       * @key the name of the storage item being replaced
       * @values an array to put into it
       */
      ClientAreaStorage.prototype.resetStorage = function(key, values) {
            this.setValueFromArray(key, values);  
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
  
  
    
	return app;
}(Backbone, jQuery));  
  