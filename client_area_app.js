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
            this.pricingModel = options.pricingModel.get("pricingModel");
            console.log("pricingModel", this.pricingModel);
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
        initialize: function() {
            tmpl =  $('#ca_order_line_tmpl').html();
            console.log("template is", tmpl);
            this.template = _.template(tmpl);
        },
        render: function() {
            //var html = this.model.get('id') + this.model.get('image') + this.model.get('frame_style')   ; //in fact from template   & prob. a list
            
            //we need the priceModel we have it pricingModel
            //drop-down of print sizes showing selected | drop-down of mount styles showing selected or 'Select..' | drop-down of frame styles showing style or 'Select..' | qty - box | update button | delete button
            var data = {}
            data.pricingModel = pricingModel;
            var html = this.template(data);
            this.$el.html(html);
            return this;
        }    
        
    
    });
    
   
    //Basket View - gets linked to the Basket Collection which it iterates through to display indiviudal order lines. 
    //of course we really need a Marionette ItemViewCollection
    app.BasketCollectionView  =  Backbone.View.extend({
        el: "#ca_pricing_area", 
        className: "ca_pricing_area" ,
        
        initialize: function(options) {
                 this.options = options;   
                 this.render();
        },
        
        render: function() {
            this.$el.html() ;
            
            this.collection.each(function(orderLine) {
               if (orderLine.get('image') == this.options.ref) {
                  //TODO - is each one deleted from memory by the assignment?
                  var orderLine = new app.OrderLineView({model: orderLine});
                  this.$el.append(orderLine.render().$el);
                }

	       }, this);
        
        }
    
    })
    
	return app;
}(Backbone));  
  