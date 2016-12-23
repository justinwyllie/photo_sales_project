var caApp = (function (Backbone) {
	var app = {};
    
    //PricingModel Model
    app.PricingModel = Backbone.Model.extend({
       url: "/api/v1/pricing",
       
    
   
    });
    
    
    //OrderLine model
    app.OrderLine = Backbone.Model.extend({
       defaults: {
            "id": null,
            "image": "",
            "print_price": "0.00",
            "size":"",
            "mount_price":"",
            "mount_style":"",
            "frame_style":"",
            "frame_price":"0.00",
            "qty":0
        }
    
    });
    
    //Basket - collection of OrderLines
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
    
    //OrderLine View - a view to display a single order line
    app.OrderLineView = Backbone.View.extend({
        tagName: 'div',
        template: null,
        initialize: function(options) {
            tmpl =  $('#ca_order_line_tmpl').html();
            this.template = _.template(tmpl);
            this.options = options;
        },
        
        render: function() {
            var data = {};
            var pricingModel = this.options.pricingModel.toJSON();
            //pull out only the size we want
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
                //TODO front-end language strings
                //this works because the first time the user wld see this -if not match to image ratio in prcingModel -  and would not be able to order anything so we would not see this repeated. not very elegant. TDOO
                var html = "Sorry. A configuration error has occured. Please contact the site owner." ;
                this.$el.html(html);
                return this;
            }  
            else 
            {
                pricingModel.printSizes.applicableSizeGroup = applicableSizeGroup;
                data = pricingModel;
                if (this.model !== null) {
                    data.order = this.model.toJSON();
                }
                console.log("DATA", data);
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
        className: "ca_pricing_area" ,
        
        initialize: function(options) {
                 this.options = options;   
                 console.log('the options are', options);
                 this.render();
        },
        
        render: function() {
            this.$el.html() ;
            //1. render existing orders
            this.collection.each(function(orderLine) {
               console.log('orderLine is', this, orderLine)  ;
               if (orderLine.get('image') == (this.options.ref + 'sss')) {   //the being rendered image is in the basketColletion i.e. it already has an order line 
                  //TODO - is each one deleted from memory by the assignment?
                  //orderLine is an item in the order e.g. a print with its size, mount, frame etc.
                  var orderLineView = new app.OrderLineView({model: orderLine, ref: this.options.ref, 
                    ratio: this.options.ratio, pricingModel: this.options.pricingModel});
                  this.$el.append(orderLineView.render().$el);
                }

	       }, this);
           //2. render a fresh blank order line
          var orderLineView = new app.OrderLineView({model: null, ref: this.options.ref, 
                    ratio: this.options.ratio, pricingModel: this.options.pricingModel});
          this.$el.append(orderLineView.render().$el);
           
        
        }
    
    })
    
	return app;
}(Backbone));  
  