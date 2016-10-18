var caApp = (function (Backbone) {
	var app = {};
    
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
        url: "basket",
        
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
        render: function() {
            var html = this.model.toJSON().frame_style; //in fact from template   & prob. a list
            this.$el.html(html);
            return this;
        }    
        
    
    });
    
   
    //Basket View - gets linked to the Basket Collection which it iterates through to display indiviudal order lines. 
    //of course we really need a Marionette ItemViewCollection
    app.BasketCollectionView  =  Backbone.View.extend({
        el: "#ca_pricing_area", 
        className: "ca_pricing_area" ,
        
        initialize: function() {
                 this.render();
        },
        
        render: function() {
            this.$el.html() ;
            
            this.collection.each(function(orderLine) {
                //TODO - is each one deleted from memory by the assignment?
                var orderLine = new app.OrderLineView({model: orderLine});
                this.$el.append(orderLine.render().$el);

	       }, this);
        
        }
    
    })
    
	return app;
}(Backbone));  
  