var caApp = (function (Backbone) {
	var app = {};
    
    app.init = function() {
    
        app.langStrings = new app.LangStrings;
        app.langStrings.fetch();
    
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
            "size":null,
            "mount_price":null,
            "mount_style":null,
            "frame_style":null,
            "frame_price":"0.00",
            "qty":0
        }
    
    });
    
    //COLLECTIONS
    
    //Basket - collection of OrderLine Models
    //calling fetch on a collection with an array of objects populates the collection with the models - one model with one element in the array
    app.BasketCollection =  Backbone.Collection.extend({
        model: app.OrderLine,
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
            var tmpl =  $('#ca_order_line_tmpl').html();
            this.template = _.template(tmpl);
            this.options = options;
        },
        
        events: {
           'click .ca_add': 'onAdd',
           'change .ca_print_size_event': 'onChangePrintSize' ,
           'change .ca_mount_event': 'onChangeMount',
           'change .ca_frame_event': 'onChangeFrame',
           'click .ca_qty_event': 'onChangeQty'
        }, 
        
        onChangePrintSize: function(evt) {
            var size = evt.currentTarget.value;
            if (size != '--')
            {
                this.model.set('size', size);
            }
        },
        
        onChangeMount: function(evt) {
            var mount = evt.currentTarget.value;
            if ((mount != '--') && (mount != 'no_mount'))
            {
                this.model.set('mount', mount);
            }
        },
        
        
        onChangeFrame: function(evt) {
            var frame = evt.currentTarget.value;
            if ((frame != '--') && (frame != 'no_frame'))
            {
                this.model.set('frame', frame);
            }
        },
        
       onChangeQty: function(evt) {
            var qty = parseInt(evt.currentTarget.value);
            if (!isNaN(qty) && Number.isInteger(qty))
            {
                this.model.set('qty', qty);
            }
        },
 
        
        onAdd: function() {
            //minimum validation
            var size = this.model.get("size");
            var qty =  this.model.get("qty");
            var errString = app.langStrings.get("invalid") + " ";
            var errState = false;
            
            if (size === null) {
                this.$el.find('.ca_print_size_group').addClass("has-error");
                this.$el.find('.ca_print_size_group select').addClass("form-control-danger");
                errString = errString + app.langStrings.get("sizeFeedback") + " ";
                errState = true;
            }
            
           if (qty == 0) {
                this.$el.find('.ca_qty_group').addClass("has-error");
                this.$el.find('.ca_qty_group input').addClass("form-control-danger");
                errString = errString + app.langStrings.get("qtyFeedback");
                errState = true;
            }
            
            if (errState) {
                this.$el.find(".ca_order_info").html(errString);    
            }
            
            
            
        },
        
        render: function() {
            var data = {};
            var pricingModel = this.options.pricingModel.toJSON();
            //pull out only the size we want from the pricingModel
            var applicableSizeGroup = null;
            that = this;
            _.each(pricingModel.printSizes.sizeGroup, function(sizeGroup) {
                if (sizeGroup.ratio == that.options.ratio) {
                    applicableSizeGroup = sizeGroup;    
                }
            });
            //TODO what is applicableSizeGroup is null i.e. no match? it means the pricing xml file has not been set up for the ratio of this image. 
            //what to do? ideally notify owneer by an ajax call to send an email - we can't display anything
            console.log("applicableSizeGroup", applicableSizeGroup);
            
            if (applicableSizeGroup === null) 
            {
                
                //this works because the first time the user wld see this -if not match to image ratio in prcingModel -  and would not be able to order anything so we would not see this repeated in multiple order line rows. not very elegant. TDOO
                var html = app.langStrings.get("configError");
                this.$el.html(html);
                return this;
            }  
            else 
            {
                pricingModel.printSizes.applicableSizeGroup = applicableSizeGroup;
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
                 console.log('the options are', options);
                 this.render();
        },
        
        render: function() {
            this.$el.html() ;
            //0. render the headings
            this.$el.html(this.tmplRoWHead());
            //1. render existing orders
            this.collection.each(function(orderLine) {
               console.log('orderLine is', this, orderLine)  ;
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
  