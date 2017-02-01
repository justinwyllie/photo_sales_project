/**
 * lp
 * i. had to handle tracking and removing child views so as to unbind/unlisten to
 * ii. needed a region contoller
 *  iii. looking for memory leaks with : chrome task mamager; profiles; and the timeline        https://developers.google.com/web/tools/chrome-devtools/memory-problems/
 *  iv. watch out for collections built using models from other collections: the models maintain a reference to the first collection
 */

var caApp = (function (Backbone, $) {
	var app = {};
    //TODO - don't need to attach things to app. unless they are being exported.... 
    
    //shims
    app.isInt = function(n) {
        return parseInt(n) == n;
    }
    
         
    
    app.init = function() {
    
        //A few variables
        app.labelHeight = 10; //this sets the height of the lables underneath the thunb images. units: pixels. 
        app.maxPopupWidth = 1600; //the maximum width of the popup which displays the main images. (In general this should not be larger than the typical width of your landscape orientation images.  units: pixels)
        app.lightBoxWidthFraction = 0.95; //effects prints mode only. the width of the image popup if not constrained by the above setting. units: percentage. you probably don't need to change this. 
    
        //TODO this should be immutable
        app.pricingModel = new app.PricingModel();
        
        app.pricingModel.fetch();  //TODO if they are quick this isn't done                                                                                                                                           f
        app.basketCollection = new BasketCollection();
        app.printThumbsCollection = new PrintThumbsCollection();
        app.proofsThumbsCollection = new ProofsThumbsCollection();
        
        app.appData = {};

        app.langStrings = new app.LangStrings;
        app.langStrings.fetch().then(
            function() {
                app.layout = new Layout();
            }
        );
    };
    
   
    
    //APP methods          - these will be moving to view for displaying thumbs... 
    
    //TODO if the user deletes the dom element for this view and all its order views
    //dthis means that the listenTo bound events are left hanging around? attached to the target - even though the listener - the view - no longer exists/cares
    //this would be solved if the view was removed by backbones remove view method which removes the listenTo's as well
    //currently the lightbox popup is closed by jquery in the jquery app. which is bad as it is outside the app. 
    //but here we expose the basketCollectionView so we could remove it properly in jQuery. TODO
    //hmm. does thie cascade remove the listenTo's set up in the order line views?       TODO
    app.showPrintPopUp = function(ref, ratio) {
        //reset the basket to what the server believes in case we have got out of sync
        //TOOO - are we getting out of sync? we should know. 
        //app.basketCollection.fetch({reset: true}).then(function() {
            app.basketCollectionView.cleanUp();
            app.basketCollectionView.remove();     
            app.basketCollectionView = new BasketCollectionView({collection: app.basketCollection, ref: ref, ratio: ratio, pricingModel: app.pricingModel});    
        //});
        
    }
    
    //when you call remove() on a view backbone calls stopListening on that view
    //this removes any callbacks registered on a target with listenTo - typically a view listenTo's a model (as we do)
    //however - if we remove a 'parent' view there is no automatic removal of its 'child views' - ones which it created in its render method
    //and .remove is not called on those child views. each time we render the basketCollectionView we create a new orderLineView per row
    //but it is the same model each time. each new view (created on each render) binds callbacks to the model.
    //thus without this management of child views the models just acculumate bound callbacks - for views which no longer exist.
    //the point is Backbone does not itself have a concept of views and child views and having parent views manage child views
    //i *think* that this is something that Marionette does with its ItemViewCollection.
    app.closePrintPopUp = function() {
        app.basketCollectionView.cleanUp();
        app.basketCollectionView.remove();
    }
    
        
    app.showPrintPopUp = function(ref, ratio) {
        //reset the basket to what the server believes in case we have got out of sync
        //TOOO - are we getting out of sync? we should know. 
        //app.basketCollection.fetch({reset: true}).then(function() {
            app.basketCollectionView.cleanUp();
            app.basketCollectionView.remove();     
            app.basketCollectionView = new BasketCollectionView({collection: app.basketCollection, ref: ref, ratio: ratio, pricingModel: app.pricingModel});    
        //});
        
    }
    
    
    app.checkSessionAndRun = function(fnc)
    {
        var p = $.ajax({
            url: '/api/v1/sessionStatus',
            dataType: 'json',
        });
        
        p.then(function(result) {
            if (result.status == "success") {
                fnc();
            } else {
                app.renderLoginScreen({message: result.message});
            }
        });

    }
    
    //TODO - this is used as a bailout at the moment.
    //if something goes wrong we show the login screen with an error message
    //prob. need to display an error page and also are we notifying the site admin? this could be done in the server app.
    app.renderLoginScreen = function(msg)
    {
        var loginView = new LoginView({message: msg});
        app.layout.renderViewIntoRegion(loginView , 'main');
    }
    
    
    
    //MODELS
    
    //PricingModel Model   
    //TODO make immutable
    //TODO use xpath instead of loops where possible      
    //TODO there is some duplication here                                                                                                                                                                                               
    app.PricingModel = Backbone.Model.extend({
        url: "/api/v1/pricing",
        defaults: {
          cache: {}
       },
       
       initialize: function() {
            var cacheStructure = {
            
            };
            cacheStructure.sizesForRatio = {};
            cacheStructure.sizeGroupForRatioAndSize = {};
            cacheStructure.printPriceAndMountPriceForRatioAndSize = {};
            cacheStructure.framePriceMatrixForGivenRatioAndSize = {};
            cacheStructure.frameDisplayNamesCodesLookups  = {};
            this.set("cache", cacheStructure);
       
       },
       
       /*
       *  returns array of size blocks
        */
       getSizesForRatio: function (imageRatio) {
            var cache =  this.get("cache");
            if (cache.sizesForRatio.hasOwnProperty(imageRatio)) {
                return cache.sizesForRatio[imageRatio];
            }  else {
                var printSizes = this.get("printSizes");
                var sizeGroupForRatio = null;
                _.each(printSizes.sizeGroup, function(sizeGroup) {
                        if (sizeGroup.ratio == imageRatio) {
                            sizeGroupForRatio = sizeGroup.sizes.size;    
                        }
                });
                
                cache.sizesForRatio[imageRatio] = sizeGroupForRatio;
                this.set("cache", cache);
                return sizeGroupForRatio;    
            }          
       
       },
       
       /*
       * returns an individual size block as object
       */
       getSizeGroupForRatioAndSize: function(imageRatio, printSize) {
            var cache =  this.get("cache");
            if ((cache.sizeGroupForRatioAndSize.hasOwnProperty(imageRatio)) && (cache.sizeGroupForRatioAndSize[imageRatio].hasOwnProperty(printSize))) {
                return cache.sizeGroupForRatioAndSize[imageRatio][printSize];
            }   else {
                var sizeBlock = null;
                var sizeGroups = this.getSizesForRatio(imageRatio);
                _.each(sizeGroups, function(size) {
                    if (size.value == printSize) {
                        sizeBlock = size;   
                    }
                }); 
            
            if (!cache.sizeGroupForRatioAndSize.hasOwnProperty(imageRatio)) {
                cache.sizeGroupForRatioAndSize[imageRatio] = {};    
            }
            cache.sizeGroupForRatioAndSize[imageRatio][printSize] = sizeBlock;
            this.set("cache", cache);   
            return sizeBlock;  
           
           } 
       
       }, 
       
       /*
       * returns object
       */
        getPrintPriceAndMountPriceForRatioAndSize: function(imageRatio, printSize) {
            var cache =  this.get("cache");
            if ((cache.printPriceAndMountPriceForRatioAndSize.hasOwnProperty(imageRatio)) && (cache.printPriceAndMountPriceForRatioAndSize[imageRatio].hasOwnProperty(printSize))) {
                return cache.printPriceAndMountPriceForRatioAndSize[imageRatio][printSize];
            } else {
                var mountPrice = null;
                var printPrice = null;
                var sizeGroup = this.getSizeGroupForRatioAndSize(imageRatio, printSize);                  
                var ret = {mountPrice: sizeGroup.mountPrice, printPrice: sizeGroup.printPrice};
                if (!cache.printPriceAndMountPriceForRatioAndSize.hasOwnProperty(imageRatio)) {
                    cache.printPriceAndMountPriceForRatioAndSize[imageRatio] = {};    
                }
                cache.printPriceAndMountPriceForRatioAndSize[imageRatio][printSize] = ret;
                this.set("cache", cache); 
                return ret ;    
            
            } 
       },
       
       /*
       * returns object
       */
       getFramePriceMatrixForGivenRatioAndSize: function(imageRatio, printSize) {
            var cache =  this.get("cache");
            if ((cache.framePriceMatrixForGivenRatioAndSize.hasOwnProperty(imageRatio)) && (cache.framePriceMatrixForGivenRatioAndSize[imageRatio].hasOwnProperty(printSize))) {
                return  cache.framePriceMatrixForGivenRatioAndSize[imageRatio][printSize];
            } else {
                var size = this.getSizeGroupForRatioAndSize(imageRatio, printSize);
                var framePricesObj = {};
                _.each(size.framePrices.framePrice, function(framePrice) {
                   framePricesObj[framePrice.style] = framePrice.price;
                });
                if (!cache.framePriceMatrixForGivenRatioAndSize.hasOwnProperty(imageRatio)) {
                    cache.framePriceMatrixForGivenRatioAndSize[imageRatio] = {};    
                }
                
                cache.framePriceMatrixForGivenRatioAndSize[imageRatio][printSize] =  framePricesObj;
                this.set("cache", cache); 
                return framePricesObj;
            }
       
       },

       /*
       * returns object
       */        
       getFrameDisplayNamesCodesLookup: function() {
            var cache =  this.get("cache");
            if (cache.frameDisplayNamesCodesLookup != undefined) {
                return cache.frameDisplayNamesCodesLookup; 
            } else  {
                 var pictureFrames = this.get("frames");
                 var framesCodeToDisplay = {};
                 _.each(pictureFrames.frame, function(frame) {
                    framesCodeToDisplay[frame.value] = frame.display;   
                });
                cache.frameDisplayNamesCodesLookup =  framesCodeToDisplay;
                this.set("cache", cache);
                return framesCodeToDisplay;
            }
       }
       
       
   
    });
    
    //Language String model
    app.LangStrings = Backbone.Model.extend({
        url: "/api/v1/langStrings",    
    
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
            "edit_mode": ""
        },
        
        initialize: function() {
            this.on("change:print_size", function() {
                var printSize = this.get("print_size");
                if (printSize === null) {
                    this.completeResetOrderLine();
                }  else {
                    this.setPrices();
                }
            }); 
            this.on("change:mount_style", function() {
                this.setPrices();    
            });
            this.on("change:frame_style", function() {
                this.setPrices();    
            });
            this.on("change:qty", function() {
                this.setPrices();    
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
        
        setPrices: function() {
            var printSize = this.get("print_size");
            var imageRatio = this.get("image_ratio");
            var mountStyle =  this.get("mount_style"); 
            var frameStyle =  this.get("frame_style"); 
            var qty = this.get("qty"); 
            var totalPrice = 0;

            if (printSize !== null) {
                var printPrice = app.pricingModel.getPrintPriceAndMountPriceForRatioAndSize(imageRatio, printSize).printPrice;
                this.set("print_price", printPrice);
                totalPrice = qty * printPrice;
            }
               
             if ((mountStyle !== null) && (mountStyle !== "no_mount")) {
                var mountPrice = app.pricingModel.getPrintPriceAndMountPriceForRatioAndSize(imageRatio, printSize).mountPrice;  
                this.set("mount_price", printPrice);
                totalPrice = totalPrice + (qty * mountPrice);
            }
                
            if ((frameStyle !== null) && (frameStyle !== "no_frame")) {
                var framePrices = app.pricingModel.getFramePriceMatrixForGivenRatioAndSize(imageRatio, printSize);    
                var applicableFramePrice =   framePrices[frameStyle];
                var applicableFrameDisplayName = app.pricingModel.getFrameDisplayNamesCodesLookup()[frameStyle];
                this.set({"frame_price": applicableFramePrice, "frame_display_name": applicableFrameDisplayName});
                totalPrice = totalPrice + (qty * applicableFramePrice);
            }                                                                                                                                                                                                                                                                     
                
            totalPrice = totalPrice.toFixed(2);
            this.set({"total_price": totalPrice, "edit_mode": "edit"});
             
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
     
        initialize: function() {
                var width = this.get("width");
                var height = this.get("height");
                var ratio = ((Math.max(width, height)) / (Math.min(width, height))).toFixed(2);
                this.set("ratio", ratio);
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
                var clientAreaStorage = new ClientAreaStorage(app.appData.username, _);  
                if (clientAreaStorage.supported) {
                    var storedPrintBasketItems = clientAreaStorage.updateStorage("ca_prints", newModel.toJSON());        
                }
            });    
        },
        
     
        
        byImage: function (ref) {
          filtered = this.filter(function (orderLine) {
              return orderLine.get("image") === ref;
          });
          return new Backbone.Collection(filtered);
        } 
    
    });
    
    PrintThumbsCollection =  Backbone.Collection.extend({
        model: ThumbModel,    
        url: "/api/v1/printThumbs",
        
        initialize: function() {
            this.on("reset", function() {
               this.setMaxHeight();    
            });
        },
        
        /*
        * this ensures that the rows are of equal height when the actual heights of the thumbs differ
        */
        setMaxHeight: function() {
            var maxHeight = 0;
            _.each(this.models, function(thumb) {
                var width = thumb.get('width');
                if ( width >= maxHeight) {
                    maxHeight = width;    
                }    
            })
            this.labelHeight = app.labelHeight;
            this.maxHeight = (maxHeight + this.labelHeight);
        },
        
        //TODO share this with  ProofsThumbsCollection
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
        url: "/api/v1/proofsThumbs", 
        
       //TODO same as  PrintThumbsCollection
       initialize: function() {
            this.on("reset", function() {
               this.setMaxHeight();    
            });
        },
        
        setMaxHeight: function() {
            this.maxHeight = '120';
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
            var loginView = new LoginView({message: ''});
            this.renderViewIntoRegion(loginView, 'main');
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
        },    
        render: function() {
            this.$el.html(this.tmpl());
            this.collection.each(function(orderLine) {
                   var orderLineView = new OrderLineView({model: orderLine, mode: 'update', pricingModel: app.pricingModel});
                   this.childViews.push(orderLineView);
                   this.$el.find("#ca_basket_order_lines_container").append(orderLineView.render().$el);
                    
	       }, this); 
        },
        //TDOO instead of copying this code around can we make an ItemView and extend that?  and the childViews from the constructor
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
    
        render: function() {
            this.$el.html('checkout');    
        }
    
    });
    
    OrderView =   Backbone.View.extend({
    
        render: function() {
            this.$el.html('order');    
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
            data.basket_label = app.langStrings.get("basketButtonText");
            if (app.appData.enablePaypal) {
                data.checkout_label = app.langStrings.get("checkoutButtonText");     
            } else {
               data.checkout_label = app.langStrings.get("order"); 
            }
            
            data.logout_label = app.langStrings.get("logout");
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
            var basketView =  new BasketView({collection: app.basketCollection});
            app.layout.renderViewIntoRegion(basketView, 'main'); 
        } ,
        
       showLogout: function() {
            var logoutView =  new LogoutView();
            app.layout.renderViewIntoRegion(logoutView, 'main'); 
        } ,
            
        showCheckout: function() {
          if (app.appData.enablePaypal) {
            var view = new CheckoutView();
          }  else {
            var view = new OrderView();        
          }
          app.layout.renderViewIntoRegion(view, 'main'); 
        
        },
        
        changePage: function(evt) {
            var targetPage = $(evt.currentTarget).data('index');
            var pageModelsJSON = app[coll].pagination(45, targetPage);
            var pagedCollection = new  Backbone.Collection(pageModelsJSON);
            var thumbsView = new ThumbsView({collection: pagedCollection, mode: 'prints', maxHeight: app[coll].maxHeight, labelHeight: app[coll].labelHeight });
            app.layout.renderViewIntoRegion(thumbsView, 'main');
            this.options.active = targetPage;
            this.render();
        } 

        
    
    })
    
    //in Marionette this would be an ItemViewCollection
    ThumbsView =  Backbone.View.extend({
        initialize: function(options) {
            this.options = options;
            this.childViews = new Array();
            this.render();
        
        },
        
        render: function()
        {
            //loop through collection and display the page.
            //first take - just display them all
             var mode = this.options.mode;
             this.collection.each(function(thumb) {
                if (mode == 'prints') {
                    var thumbView = new PrintThumbView({model: thumb, maxHeight: this.options.maxHeight, labelHeight: this.options.labelHeight}) ;
                }  else {
                    var thumbView = new ProofsThumbView({model: thumb, maxHeight: this.options.maxHeight, labelHeight: this.options.labelHeight}) ;    
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
           //1. render existing order lines
           this.collection.each(function(orderLine) {
                if (orderLine.get('image_ref') == (this.options.file)) {
                    var orderLineView = new OrderLineView({model: orderLine, mode: 'update', pricingModel: this.options.pricingModel});
                    this.childViews.push(orderLineView);
                    
                    this.$el.find("#ca_order_lines_container").append(orderLineView.render().$el);
                }    
                    
	       }, this);
           //2. render a fresh blank order line
          var orderLine = new OrderLineModel();
          orderLine.set("image_ref", this.options.file);
          orderLine.set("image_ratio", this.options.ratio);
          var orderLineView = new OrderLineView({collection: this.collection, model: orderLine, ref: this.options.file, 
                    mode: 'new', pricingModel: this.options.pricingModel});
          this.childViews.push(orderLineView);          
          this.$el.find("#ca_order_lines_container").append(orderLineView.render().$el); 
        
        },  
        
       
        render: function() {
            var data = {};
            data.path = this.options.path;
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
            var ratio = this.model.get("ratio");
            path = path.replace("thumbs", "main") ;
            this.basketItemsForImage = app.basketCollection;// .byImage(this.options.file);
            var width =  this.model.get("width");
            var height =  this.model.get("height") ;
            var mainWidth = this.model.get("mainWidth"); 
            var mainHeight = this.model.get("mainHeight"); 
            var view = new PrintPopUpView({
                file: file, 
                path: path, 
                ratio: ratio,
                pricingModel: app.pricingModel, 
                collection: this.basketItemsForImage, 
                mainWidth: mainWidth, 
                mainHeight: mainHeight
            });
            app.layout.renderViewIntoRegion(view, 'body1');   
        
        },
        
        render: function() {
            var data = this.model.toJSON();
            data.in_basket_class = "";
            data.thumbStyle = "height: " + this.options.maxHeight + "px";
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

    
    //OrderLine View - a view to display a single order line
    OrderLineView = Backbone.View.extend({
        tagName: 'div',
        className: 'row', 
        template: null,
        initialize: function(options) {
            this.listenTo(this.model, "invalid", this.displayModelErrors);
            this.listenTo(this.model, "change:total_price", this.render);
            this.listenTo(this.model, "sync", this.modelSynced);
            this.listenTo(this.model, "destroy", this.removeMe);
            var tmpl =  $('#ca_order_line_tmpl').html();
            this.template = _.template(tmpl);
            this.options = options;
            this.options.pricingModelJSON = this.options.pricingModel.toJSON();

        },
        
        events: {
           'click .ca_add_event': 'onAdd',
           'click .ca_update_event': 'onUpdate',
           'click .ca_remove_event': 'onRemove',
           'change .ca_print_size_event': 'onChangePrintSize' ,
           'change .ca_mount_event': 'onChangeMount',
           'change .ca_frame_event': 'onChangeFrame',
           'input .ca_qty_event': 'onChangeQty',     /* input is better but IE9 does not support backspace or delete. keyup doesn't capture backspace. keydown is too soon - fires before change. keypress is deprecated in favour of input */
           'change .ca_qty_event': 'onChangeQty' /* fully support IE9 at the cost of an unnessary update */
        }, 
        
        
        displayModelErrors: function() {
            that = this;
            _.each(this.model.validationError.fields, function(field) {
                that.$el.find('.ca_' + field + '_group').addClass("has-error");
            });
            this.$el.find(".ca_order_info").html(this.model.validationError.errString);     
        },
        
        clearErrors: function(field) {
            that = this;
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
            var sizeSelected = evt.currentTarget.value;
            this.model.set('print_size', this.processSelects(sizeSelected));
         },

        onChangeMount: function(evt) {
            var mount = evt.currentTarget.value;
            this.model.set('mount_style', this.processSelects(mount));
        },
        
        
        onChangeFrame: function(evt) {
            var frame = evt.currentTarget.value;
            this.model.set('frame_style', this.processSelects(frame));
        },
        
       onChangeQty: function(evt) {
            this.clearErrors('qty');
            var qty = evt.currentTarget.value;
            this.model.set({'qty': qty});
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
            var clientAreaStorage = new ClientAreaStorage(app.appData.username, _);  
            if (clientAreaStorage.supported) {
                var storedPrintBasketItems = clientAreaStorage.updateStorage("ca_prints", model.toJSON());        
            }
        },
        
       updateError: function(model) {
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
            var ratio = this.model.get("image_ratio"); 
            var data = {};
            var pricingModel = this.options.pricingModelJSON;
            data = app.pricingModel.toJSON();
            data.applicableSizesGroup = app.pricingModel.getSizesForRatio(ratio);
            data.order = this.model.toJSON();

            var editMode = this.model.get("edit_mode");
            if (editMode == "") {
                data.editStateIcon = "";    
            }  else {
                data.editStateIcon = "fa-" +    editMode; 
            }
            
            data.mode = this.options.mode;
            
            data.langStrings = {};
            data.langStrings = app.langStrings.toJSON();
                 
            var printSize = this.model.get("print_size");  
            data.mountPrice = null;
            data.framePrices = null;
            
            if (printSize !== null) {
                data.mountPrice = app.pricingModel.getPrintPriceAndMountPriceForRatioAndSize(ratio, printSize).mountPrice;
                data.framePrices =  app.pricingModel.getFramePriceMatrixForGivenRatioAndSize(ratio, printSize);
                data.frameStylesToDisplay = app.pricingModel.getFrameDisplayNamesCodesLookup();
            }
            
            var html = this.template(data);
            this.$el.html(html);
            return this;    
        }    
    });
    
    
    
    LoginView = Backbone.View.extend({
        el: "#ca_content_area #ca_content",
        
        initialize: function(options) {
            var loginTmpl =  $('#ca_login_tmpl').html();
            this.loginTemplate = _.template(loginTmpl);
            var modeChoiceTmpl =  $('#ca_mode_choice_tmpl').html();
            this.modeChoiceTmpl = _.template(modeChoiceTmpl);
            this.options = options;
        
        },
        
        events: {
            'click .ca_login_button': 'doLogin',
            'click .ca_choose_activity_event': 'doModeChoice'
        
        },
        
        doLogin: function() {
            //TODO - any preliminary validation
            var user = this.$el.find('#ca_login_name').val();
            var password = this.$el.find('#ca_login_password').val();
            
            var data = {};
            data.name = user;
            data.password = password;

            var clientAreaStorage = new ClientAreaStorage(user);
            if (clientAreaStorage.supported) {
                data.restoredProofs = clientAreaStorage.getValueAsString("ca_proofs")
                data.restoredProofsPagesVisited = clientAreaStorage.getValueAsString("ca_proofs_pages_visited");
                data.restoredPrints =  clientAreaStorage.getValueAsString("ca_prints");
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
                    that.renderModeChoice();
                } else {
                    that.setMessage(result.message);
                }
            });

        },
        
        setMessage: function(msg) {
            this.$el.find('.ca_login_error').html(msg);
        },
        
        doModeChoice: function() {
            var mode = this.$el.find("#ca_activity_choice").val();
            if (mode != "--") {
            
                var data = {};
                data.mode = mode;
                //TODO unnecessary call
                var p = $.ajax({
                    url: '/api/v1/modeChoice',
                    dataType: 'json',
                    data: data,
                    method: 'GET'
                });    
            
               var that = this;
                p.then(function(result) {
                    if (result.status == "success") {
                    
                        if (mode == 'prints') {
                            coll =  'printThumbsCollection' ;
                        } else {
                            coll =  'proofsThumbsCollection' ;
                        }
                        
                       //reset true in case there are 2 mode choices in session. TODO is this right? 
                       app.basketCollection.fetch({reset: true}).then(  
                            function() {
                                var clientAreaStorage = new ClientAreaStorage(app.appData.username, _);  
                                if (clientAreaStorage.supported) {
                                    clientAreaStorage.resetStorage("ca_prints", app.basketCollection.toJSON());        
                                }
                                
                                //reset true in case there are 2 mode choices in session. TODO is this right?
                                app[coll].fetch({reset: true}).then(
                                function() {
                              
                                    var thumbsPerPage = parseInt(app.appData.thumbsPerPage) ;
                                    var pageModelsJSON = app[coll].pagination(thumbsPerPage, 1);
                                    var pagedCollection = new  Backbone.Collection(pageModelsJSON);
                                    
                                
                                    var thumbsView = new ThumbsView({collection: pagedCollection, mode: mode, maxHeight: app[coll].maxHeight, labelHeight: app[coll].labelHeight });
                                    app.layout.renderViewIntoRegion(thumbsView, 'main');
                                    if (mode == 'prints') {
                                        var menuView = new PrintsMenuView({totalThumbs: app[coll].length, thumbsPerPage: thumbsPerPage, active: 1});
                                    } else {
                                        var menuView = new ProofsMenuView();
                                    }
                                    app.layout.renderViewIntoRegion(menuView, 'menu');
                            },
                               function() {
                                    console.log("TODO - error unknownError - 500");
                                }
                            )
                                  
                            }
                        );  
                        
                        
                     } else {
                        that.options.message = result.message;       //this appears to render the login screen with a message. TODO - don't we have an error page to send them to?
                        that.render();
                    }
                },
                function() {
                    console.log("TODO - error unknownError - 500");    
                }
                
                
                )
            }
        },
        
        render: function() {
            data = {};
            data.langStrings = app.langStrings.toJSON();
            data.msg = this.options.message;
            var html = this.loginTemplate(data);
            this.$el.html(html);         
        },
        
        renderModeChoice: function() {
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
  