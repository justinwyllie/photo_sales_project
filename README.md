# photo_sales_project
Backbone.js based add-on to enable photographers to display and sell prints on their web site. The s/w contains a proofing gallery and a print sales section. The print sales section supports prints, mounts and frames. Payment can be taken by PayPal. 

The idea is to keep this very simple. Just a few files. No installation routine. No database - the pricing details
are entered into an XML file. 

client_area.php is the file which is called. E.g. mysite.com/client_area.php. This is the 'index' file of the project. It consumes client_area_template.php which is where you can do any HTML or CSS you want so as to  make this project look like your 

There is a directory structure for options (XML files) and for storing the images (thumbs and larger ones). Hopefully it is all clear from the code.

The file client_area.less contains the CSS. (I think - I haven't looked at this in a while). If you don't want to build the less it looks like a simple job just to turn this into CSS yourself. (Just a few variables). 
