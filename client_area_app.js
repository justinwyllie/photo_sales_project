var caApp = (function (Backbone) {
	var app = {};
    
    app.init = function() {
    
        app.langStrings = new app.LangStrings;
        app.langStrings.fetch();
        app.pricingModel = new app.PricingModel();
        app.pricingModel.fetch();
        app.basketCollection = new app.BasketCollection();
        app.basketCollection.fetch();   
    
    }
    
    //MODELS
    
    //PricingModel Model                                                                                                                                                                                                       
    app.PricingModel = Backbone.Model.extend({
       url: "/api/v1/pricing",
       
    
   
    });
    
    //Language String model
    app.LangStrings = Backbone.Model.extend({
        url: "/api/v1/langStrings",    
    
    }); 
    
    //OrderLine model
    app.OrderLineModel = Backbone.Model.extend({
        defaults: {
            "id": null,
            "image": null,
            "print_price": "0.00",
            "print_size":null,
            "mount_price":null,
            "mount_style":null,
            "frame_style":null,
            "frame_price":null,
            "qty":0
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
            this.listenTo(this.model, "change:print_size", this.updateDropDowns);
            this.listenTo(this.model, "invalid", this.displayModelErrors);
            var tmpl =  $('#ca_order_line_tmpl').html();
            this.template = _.template(tmpl);
            this.options = options;
            //TODO - pricingModelJSON should really be immuatable 
            this.options.pricingModelJSON = this.options.pricingModel.toJSON();
           
            var applicableSizeGroup = null;
            that = this;
            _.each(this.options.pricingModelJSON.printSizes.sizeGroup, function(sizeGroup) {
                if (sizeGroup.ratio == that.options.ratio) {
                    applicableSizeGroup = sizeGroup;    
                }
            });
            //TODO what is applicableSizeGroup is null i.e. no match? it means the pricing xml file has not been set up for the ratio of this image. 
            //what to do? ideally notify owneer by an ajax call to send an email - we can't display anything
            this.options.pricingModelJSON.printSizes.applicableSizeGroup = applicableSizeGroup;
        },
        
        events: {
           'click .ca_add': 'onAdd',
           'change .ca_print_size_event': 'onChangePrintSize' ,
           'change .ca_mount_event': 'onChangeMount',
           'change .ca_frame_event': 'onChangeFrame',
           'change .ca_qty_event': 'onChangeQty'
        }, 
        
        updateDropDowns: function() {
            this.createFrameDropDown();
            this.createMountDropDown();
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
                _.each(that.model.validationError.fields, function(field) {
                    that.$el.find('.ca_' + field + '_group').removeClass("has-error");
                });
            }
            this.$el.find(".ca_order_info").html("");     
        },
        
        
        createMountDropDown: function() {
            var container = this.$el.find('#ca_mount_group select');
            var tmpl =  $('#ca_order_line_mount_select_options').html();
            var template = _.template(tmpl);
            var data = {};
            data.langStrings = {};
            data.langStrings.select = app.langStrings.get("select");
            data.langStrings.noMount = app.langStrings.get("noMount");
            data.pricingModel = this.options.pricingModelJSON;
            data.mountPrice = this.model.get("mount_price");
            var html = template(data);
            container.prop("disabled", false);
            container.find('option').remove().end().append(html);
          },
          
         createFrameDropDown: function() {
            var container = this.$el.find('#ca_frame_group select');
            var tmpl =  $('#ca_order_line_frame_select_options').html();
            var template = _.template(tmpl);
            var data = {};
            data.pricingModel = this.options.pricingModelJSON;
            data.langStrings = {};
            data.langStrings.select = app.langStrings.get("select");
            data.langStrings.noFrame = app.langStrings.get("noFrame");
            //pull out the frame prices for the curent print size
            var printSize = this.model.get("print_size");
            data.framePrices = null;
            _.each(data.pricingModel.printSizes.applicableSizeGroup.sizes.size, function(size) {
                if (printSize == size.value) {
                    data.framePrices = size.framePrices;    
                }     
            });
            
            var framesCodeToDisplay = [];
            _.each(data.pricingModel.frames.frame, function(frame) {
                framesCodeToDisplay[frame.value] = frame.display;   
            })
            _.each(data.framePrices.framePrice, function(framePrice) {
                framePrice.displayName =   framesCodeToDisplay[framePrice.style];  
            })
            
            var html = template(data);
            container.prop("disabled", false);
            container.find('option').remove().end().append(html);
          },
        
        onChangePrintSize: function(evt) {
            var sizeSelected = evt.currentTarget.value;
            if (sizeSelected != '--')
            {
                //set mount pricing for this print size
                var pricingModel = this.options.pricingModelJSON;
                var that = this;
                _.each(this.options.pricingModelJSON.printSizes.applicableSizeGroup.sizes.size, function(size) {
                    if (size.value == sizeSelected) {
                        that.model.set('print_size', size.value);
                        that.model.set('mount_price', size.mountedPrice);
                     }
                });
            }
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
           //..then of course some redrawing which should see the just added on appear as an updatable row

            
        },
        
        render: function() {
            var data = {};
            var pricingModel = this.options.pricingModelJSON;
              
            if (this.options.applicableSizeGroup === null) 
            {
                //this works because the first time the user wld see this -if not match to image ratio in prcingModel -  and would not be able to order anything so we would not see this repeated in multiple order line rows. not very elegant. TDOO
                var html = app.langStrings.get("configError");
                this.$el.html(html);
                return this;
            }  
            else 
            {
                data = pricingModel;
                data.order = this.model.toJSON();
                data.mode = this.options.mode;
                var html = this.template(data);
                this.$el.html(html);
                return this;    
            }
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
  