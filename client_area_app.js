var caApp = (function (Backbone) {
	var app = {};
    
    app.init = function() {
    
        app.langStrings = new app.LangStrings;
        app.langStrings.fetch();
        //TODO this should be immutable
        app.pricingModel = new app.PricingModel();
        app.pricingModel.fetch();
        app.basketCollection = new app.BasketCollection();
        app.basketCollection.fetch();   
    
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
    app.OrderLineModel = Backbone.Model.extend({
        defaults: {
            "id": null,
            "image_ref": null,
            "image_ratio": null,
            "print_price": null,
            "print_size":null,
            "mount_price":null,
            "mount_style":null,
            "frame_style":null,
            "frame_price":null,
            "frame_display_name": null,
             "qty":0
        },
        
        initialize: function() {
            this.on("change:frame_style", function() {
                this.setFramePriceAndDisplayName();    
            });   
            this.on("change:print_size", function() {
                this.setPrintPrice(); 
                this.setMountPrice();   
            });  
        },
        
        setFramePriceAndDisplayName: function() {
            var printSize = this.get("print_size");
            var frameStyle =  this.get("frame_style");
            var imageRatio = this.get("image_ratio");
            var applicableFramePrice = null;
            var applicableFrameDisplayName = null;
            if ((printSize !== null) && (frameStyle !== null) && (imageRatio !== null)) {
                var framePrices = app.pricingModel.getFramePriceMatrixForGivenRatioAndSize(imageRatio, printSize);    
                applicableFramePrice =   framePrices[frameStyle];
                applicableFrameDisplayName = app.pricingModel.getFrameDisplayNamesCodesLookup()[frameStyle];
                
            }
            this.set({"frame_price": applicableFramePrice, "frame_display_name": applicableFrameDisplayName});
        },
        
        setPrintPrice: function() {
            var printSize = this.get("print_size");
            var imageRatio = this.get("image_ratio");
            var printPrice = null;
            if ((printSize !== null) && (imageRatio !== null)) {
                var printPrice = app.pricingModel.getPrintPriceAndMountPriceForRatioAndSize(imageRatio, printSize).printPrice;  
                
            }
            this.set("print_price", printPrice); 
           
        },
        
        setMountPrice: function() {
            var printSize = this.get("print_size");
            var imageRatio = this.get("image_ratio");
            var mountPrice = null;
            if ((printSize !== null) && (imageRatio !== null)) {
                var mountPrice = app.pricingModel.getPrintPriceAndMountPriceForRatioAndSize(imageRatio, printSize).mountPrice;      
            }
           this.set("mount_price", mountPrice); 
        },

        validate: function(attrs, options) {
            var errState = false;
            var errData = {};
            errData.errString = "";
            errData.fields = [];
            if ((attrs.print_size == '--') || (attrs.print_size == null)) {
                errState = true;
                errData.errString =  errData.errString + app.langStrings.get("sizeFeedback") + " ";
            } 
            
            if (isNaN(attrs.qty) || (attrs.qty === "") || (attrs.qty == 0) || !Number.isInteger(parseInt(attrs.qty)))  {
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
    
    //COLLECTIONS
    
    //Basket - collection of OrderLine Models
    //calling fetch on a collection with an array of objects populates the collection with the models - one model with one element in the array
    app.BasketCollection =  Backbone.Collection.extend({
        model: app.OrderLineModel,
        url: "/api/v1/basket",
        
        initialize: function(options) {
            this.on("add", function() {
                console.log("collection", this);
            });    
        },
        
     
        
        byImage: function (ref) {
          filtered = this.filter(function (orderLine) {
              return orderLine.get("image") === ref;
          });
          return new app.BasketCollection(filtered);
        } 
    
    });
    
    //VIEWS
    
    //OrderLine View - a view to display a single order line
    app.OrderLineView = Backbone.View.extend({
        tagName: 'div',
        className: 'row', 
        template: null,
        initialize: function(options) {
            this.listenTo(this.model, "invalid", this.displayModelErrors);
            var tmpl =  $('#ca_order_line_tmpl').html();
            this.template = _.template(tmpl);
            this.options = options;
            this.model.set("image_ref", this.options.ref);
            this.model.set("image_ratio", this.options.ratio);
            this.options.pricingModelJSON = this.options.pricingModel.toJSON();

        },
        
        events: {
           'click .ca_add': 'onAdd',
           'change .ca_print_size_event': 'onChangePrintSize' ,
           'change .ca_mount_event': 'onChangeMount',
           'change .ca_frame_event': 'onChangeFrame',
           'change .ca_qty_event': 'onChangeQty'     /* input is better but is not fully supported in IE9. keyup doesn't capture backspace. keydown is too soon - fires before change */
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
        
        onChangePrintSize: function(evt) {
            var sizeSelected = evt.currentTarget.value;
            if (sizeSelected != '--')
            {
                this.model.set('print_size', sizeSelected);
                this.model.set('mount_style', null);
                this.model.set('frame_style', null); 
                this.model.set('frame_price', null); 
                this.model.set('qty', 0);
             }
            else    //no size selected
            {
                this.resetOrderValues();
            }
            
            this.render();

        },
        
        //reset the order but not the id or the image_ref - so an existing order is still the same one
        resetOrderValues: function() {
            this.model.set( 
                {
                    "print_price": null, 
                    "print_size":null, 
                    "mount_price":null, 
                    "mount_style":null, 
                    "frame_style":null, 
                    "frame_price":null, 
                     "qty":0
                });
        },
        
        
        onChangeMount: function(evt) {
            var mount = evt.currentTarget.value;
            this.model.set('mount_style', mount);
        },
        
        
        onChangeFrame: function(evt) {
            var frame = evt.currentTarget.value;
            this.model.set('frame_style', frame);
        },
        
       onChangeQty: function(evt) {
            this.clearErrors('qty');
            var qty = evt.currentTarget.value;
            this.model.set({'qty': qty});
            console.log("m", this.model.get("qty"));
        },
 
        
        onAdd: function() {

           if (this.model.isValid()) {
                this.clearErrors();
                app.basketCollection.create(this.model.toJSON(), {wait: true});
           }

           
        },
        
        render: function() {
            var data = {};
            var pricingModel = this.options.pricingModelJSON;
            data = app.pricingModel.toJSON();
            data.applicableSizesGroup = app.pricingModel.getSizesForRatio(this.options.ratio);
            data.order = this.model.toJSON();
            data.mode = this.options.mode;
            
            data.langStrings = {};
            data.langStrings.select = app.langStrings.get("select");
            data.langStrings.noFrame = app.langStrings.get("noFrame");
            data.langStrings.noMount = app.langStrings.get("noMount");
            
            var printSize = this.model.get("print_size");  
            data.mountPrice = null;
            data.framePrices = null;
            
            if (printSize !== null) {
                data.mountPrice = app.pricingModel.getPrintPriceAndMountPriceForRatioAndSize(this.options.ratio, printSize).mountPrice;
                data.framePrices =  app.pricingModel.getFramePriceMatrixForGivenRatioAndSize(this.options.ratio, printSize);
                data.frameStylesToDisplay = app.pricingModel.getFrameDisplayNamesCodesLookup();
            }
            
            var html = this.template(data);
            this.$el.html(html);
            return this;    
        }    
    });
    
   
    //Basket View - gets linked to the Basket Collection which it iterates through to display indiviudal order lines. 
    //of course we really need a Marionette ItemViewCollection
    app.BasketCollectionView  =  Backbone.View.extend({
        el: "#ca_pricing_area", 
        
        initialize: function(options) {
                this.listenTo(this.collection, "add", this.render);
                this.options = options;  
                var tmplRoWHead =  $('#ca_order_line_row_head_tmpl').html(); 
                this.tmplRoWHead = _.template(tmplRoWHead);
                this.render();
        },
        
        render: function() {
            this.$el.html() ;
            //0. render the headings
            this.$el.html(this.tmplRoWHead());
            //1. render existing orders
            this.collection.each(function(orderLine) {
                if (orderLine.get('image_ref') == (this.options.ref)) {   //the being rendered image is in the basketColletion i.e. it already has an order line 
                  //TODO - is each one deleted from memory by the assignment?
                  //orderLine is an item in the order e.g. a print with its size, mount, frame etc.
                  var orderLineView = new app.OrderLineView({model: orderLine, ref: this.options.ref, 
                    mode: 'update', ratio: this.options.ratio, pricingModel: this.options.pricingModel});
                  this.$el.append(orderLineView.render().$el);
                }

	       }, this);
           //2. render a fresh blank order line
          var orderLine = new app.OrderLineModel();
          var orderLineView = new app.OrderLineView({model: orderLine, ref: this.options.ref, 
                    mode: 'new', ratio: this.options.ratio, pricingModel: this.options.pricingModel});
          this.$el.append(orderLineView.render().$el);
           
        
        }
    
    })
    
	return app;
}(Backbone));  
  