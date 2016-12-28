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
    app.PricingModel = Backbone.Model.extend({
        url: "/api/v1/pricing",
        defaults: {
            sizesForRatio: null,
            printPriceAndMountPriceForRatioAndSize: null,
            framePriceMatrixForGivenRatioAndSize: null,
            frameDisplayNamesCodesLookup: null,
            sizeGroupForRatioAndSize: null

       },
       
       /*
       *  returns array of size blocks
        */
       getSizesForRatio: function (imageRatio) {
            that = this;    
            var sizesForRatio = this.get("sizesForRatio"); 
            if (sizesForRatio !== null) {
                return  sizesForRatio;
            }  else {
                var printSizes = this.get("printSizes");
                var sizeGroupForRatio = null;
                _.each(printSizes.sizeGroup, function(sizeGroup) {
                        if (sizeGroup.ratio == imageRatio) {
                            sizeGroupForRatio = sizeGroup.sizes.size;    
                        }
                });
                
                this.set("sizesForRatio", sizeGroupForRatio);
                return sizeGroupForRatio;    
            }          
       
       },
       
       /*
       * returns an individual size block as object
       */
       getSizeGroupForRatioAndSize: function(imageRatio, printSize) {
            that = this;
            var sizeBlock = null;
            var sizeGroupForRatioAndSize = this.get("sizeGroupForRatioAndSize");
            if (sizeGroupForRatioAndSize !== null) {
                return sizeGroupForRatioAndSize;
            }    else {
                var sizeGroups = this.getSizesForRatio(imageRatio);
                _.each(sizeGroups, function(size) {
                    if (size.value == printSize) {
                        sizeBlock = size;   
                    }
                }); 
            
            this.set("sizeGroupForRatioAndSize", sizeBlock);    
            return sizeBlock;  
           
           } 
       
       }, 
       
        getPrintPriceAndMountPriceForRatioAndSize: function(imageRatio, printSize) {
            that = this;
            var printPriceAndMountPriceForRatioAndSize = this.get("printPriceAndMountPriceForRatioAndSize");
            if (printPriceAndMountPriceForRatioAndSize !== null) {
                return printPriceAndMountPriceForRatioAndSize;
            } else {
                var mountPrice = null;
                var printPrice = null;
                var sizeGroup = this.getSizeGroupForRatioAndSize(imageRatio, printSize);
                
                var ret = {mountPrice: sizeGroup.mountPrice, printPrice: sizeGroup.printPrice};
                this.set("printPriceAndMountPriceForRatioAndSize",  ret);
                return ret ;    
            
            } 
       },
       
       getFramePriceMatrixForGivenRatioAndSize: function(imageRatio, printSize) {
            var framePriceMatrixForGivenRatioAndSize = this.get("framePriceMatrixForGivenRatioAndSize");
                if (framePriceMatrixForGivenRatioAndSize !== null) {
                return  framePriceMatrixForGivenRatioAndSize;
            } else {
                var framePrices = null;
                var size = this.getSizeGroupForRatioAndSize(imageRatio, printSize);
            
                this.set("framePriceMatrixForGivenRatioAndSize", size.framePrices);
                return size.framePrices;
            }
       
       },
       
       getFrameDisplayNamesCodesLookup: function() {
            var  frameDisplayNamesCodesLookup = this.get("frameDisplayNamesCodesLookup");
            if (frameDisplayNamesCodesLookup !== null) {
                return frameDisplayNamesCodesLookup; 
            } else  {
                 var pictureFrames = this.get("frames");
                 var framesCodeToDisplay = {};
                 _.each(pictureFrames.frame, function(frame) {
                    framesCodeToDisplay[frame.value] = frame.display;   
                });
                
                this.set("frameDisplayNamesCodesLookup", framesCodeToDisplay);
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
             "qty":0
        },
        
        initialize: function() {
            this.on("change:frame_style", function() {
                this.setFramePrice();    
            });   
            this.on("change:print_size", function() {
                this.setPrintPrice(); 
                this.setMountPrice();   
            });  
        
        },
        
        setFramePrice: function() {
            var printSize = this.get("print_size");
            var frameStyle =  this.get("frame_style");
            var imageRatio = this.get("image_ratio");
            var applicableFramePrice = null;
            if ((printSize !== null) && (frameStyle !== null) && (imageRatio !== null)) {
                var pricingModel = app.pricingModel.toJSON();
                that = this;
                _.each(pricingModel.printSizes.sizeGroup, function(sizeGroup) {
                    if (sizeGroup.ratio == imageRatio) {
                        _.each(sizeGroup.sizes.size, function(size) {
                            if (size.value == printSize) {
                                _.each(size.framePrices.framePrice, function(framePrice) {
                                    if (framePrice.style == frameStyle) {
                                        applicableFramePrice = framePrice.price;
                                    }
                                });
                            }
                       });
                    }
                });    
            }
            
            this.set("frame_price", applicableFramePrice);
        },
        
        setPrintPrice: function() {
            var printSize = this.get("print_size");
            var imageRatio = this.get("image_ratio");
            var printPrice = null;
            //TODO do something about all these repeated loops!  - maybe a method on the pricingModel that cahces them there so we only need to run these once after all the pricing model is immutable... 
            if ((printSize !== null) && (imageRatio !== null)) {
                var pricingModel = app.pricingModel.toJSON();
                that = this;
                _.each(pricingModel.printSizes.sizeGroup, function(sizeGroup) {
                    if (sizeGroup.ratio == imageRatio) {
                        _.each(sizeGroup.sizes.size, function(size) {
                            if (size.value == printSize) {
                                printPrice = size.printPrice;
                            }
                       });
                    }
                });    
            }
            
           this.set("print_price", printPrice); 
        },
        
        setMountPrice: function() {
            var printSize = this.get("print_size");
            var imageRatio = this.get("image_ratio");
            var mountPrice = null;
            //TODO do something about all these repeated loops!  - maybe a method on the pricingModel that cahces them there so we only need to run these once after all the pricing model is immutable... 
            if ((printSize !== null) && (imageRatio !== null)) {
                var pricingModel = app.pricingModel.toJSON();
                that = this;
                _.each(pricingModel.printSizes.sizeGroup, function(sizeGroup) {
                    if (sizeGroup.ratio == imageRatio) {
                        _.each(sizeGroup.sizes.size, function(size) {
                            if (size.value == printSize) {
                                mountPrice = size.mountPrice;
                            }
                       });
                    }
                });    
            }
            
           this.set("mount_price", mountPrice); 

        },
        
        //NB - if you add an error field make sure there is a matching form group in the view. ca_modelAttributeName_group. 
        //displayModelErrors in the view will add an error class to this field
        validate: function(attrs, options) {
            var errState = false;
            var errData = {};
            errData.errString = "";
            errData.fields = [];
            
            if (attrs.print_size == '--') {
                errState = true;
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
           'change .ca_qty_event': 'onChangeQty'
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
            this.model.set({'qty': qty}, {validate: true});
        },
 
        
        onAdd: function() {
            this.clearErrors();
            //what to do?
            //check model is in valid state.
            //do we save the model or add it to the collection and save that?
            //it has to end up in the collection.
           //..then of course some redrawing which should see the just added on appear as an updatable row and a new fresh row underneath that.... 
           console.log("ORDER!", this.model)   ;
           
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
                if (orderLine.get('image') == (this.options.ref + 'sss')) {   //the being rendered image is in the basketColletion i.e. it already has an order line 
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
  