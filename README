## Dolibarr ExtDirect ##

Dolibarr Module to connect the Dolibarr ERP system to Sencha Touch, a high-performance HTML5 mobile application framework or Sencha Ext JS, a business-grade web application development framework.

Ext Direct allows for seamless communication between the client-side and server-side, uses raw HTTP posts with little overhead using low data bandwidth and improved performance.

The module provides a set of Ext Direct compatible php classes. 

**CRUD methods you can seamless integrate in your client-side data stores:**

PHP class:

    class ExtDirectProduct extends Product
    {
    	public function __construct() {
    		...
    	}
    	public function readProduct(stdClass $param) {
    		...
    	}
    	public function createProduct($params) {
    		...
    	}
    	public function updateProduct($params) {
    		...
    	}
    	public function destroyProduct($params) {
    		...
    	}
    }

Client CRUD calls:
	
    
	Ext.ns("Ext.app.REMOTING_API");
    Ext.app.REMOTING_API = {
    	"id":"dolibarr_connector",
    	"url":"http://localhost/dolibarr/htdocs/extdirect/router.php",
    	"type":"remoting",
    	"actions":{
    		"ExtDirectProduct":[
    		     {"name":"readProduct","len":1},
    		     {"name":"createProduct","len":1},
    		     {"name":"updateProduct","len":1},
    		     {"name":"destroyProduct","len":1},
    		     {"name":"readProductList","len":1}
    		],
			...
		},
    		"total":2200
    };
    Ext.Direct.addProvider(Ext.app.REMOTING_API);
    
	Ext.getStore('product').setProxy({
		type: 'direct',
		api: {
			create: ExtDirectProduct.createProduct,
			read: ExtDirectProduct.readProduct,
			update: ExtDirectProduct.updateProduct,
			destroy: ExtDirectProduct.destroyProduct
		}
	});
	...
	var products = Ext.getStore('product');
    var productData = {
    	ref: 'CT0001',
    	label: 'product 1',	
    	type: 0,
    	description: 'product description',
    	...
    	barcode: '1234567890005',
    	barcode_type: 2	
    };
    var product = Ext.create('MyApp.model.Product',productData);
    
    products.add(product);					
    products.sync();
    products.clearFilter();
    products.filter(Ext.create('Ext.util.Filter',{property:"ref",value:'CT0001'}));
    products.load({
    	callback: function (records) {
    		Ext.Array.each(records,function (record) {
    			record.set('description','updated product');
    		});
    		products.sync();
    	}
    });
    ...
    products.removeAt(products.find('ref','CT0001'));
    products.sync();

**Currently provided Classes:**

- Authentication
- Translation
- Products
- Customer orders
- Customer shipments
- Activity tracking
- Customers/Suppliers
- Contacts
- Categories
- Agenda
- Supplier orders


> New classes will be added.

**Authentication system:**

The module uses an authentication system different from the usual login/password authentication. Instead authentication is done by sending a key for each app installation to the server. In the module setup page the system administrator can assign a user to the installed application and the module will return a unique access key to the app. You can also enable auto user assignment in the module setup page, advised to only use this for testing or demo purposes.

**Activity tracking system:**

Can be used to track when client app is started, when the app started editing or doing an action on a certain object, Following items are tracked: user, user alias, app name, time, activity name, activity status, object id.

**Usage:**

For more details on how to use the Classes in your client side code, you can examine the provided jasmine unit tests in file extdirect/testing/test.js. You can run the test with URL "http://your.server.net/.../htdocs/extdirect/testing/", enable auto superAdmin assignment for this and set admin language to en_US.
At least three warehouses, 2 multiprice indexes and one customer-supplier with rowid 1 must be available to succeed the test.
Enable barcode module with at least EAN13 activated.
Enable agenda module.
Enable customer and supplier order modules with stock increase/decrease enabled.

**Supported Dolibarr Versions:**

- Min version:	3.3
- Max version:	3.9 RC2