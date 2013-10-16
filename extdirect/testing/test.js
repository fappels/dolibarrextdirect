/**
 * jasmine unit tests for extdirect connector
 */

var appUuid = null,
	warehouseIds = [],
	priceIndex = null,
	orderId = null,
	shipmentId = null,
	productIds = [];

var TIMEOUT = 5000;
	
describe("Authentication", function () {
	// enable autoasignment of superadmin in connector
	var testresult = null,
		acknowledgeId = null,
		flag = false;
	
	beforeEach(function() {
		appUuid = 'test1234';
		testresult = null;
	});
	
	it("create Authentication", function() {
		runs(function() {
			flag = false;
			var authentication = Ext.create('ConnectorTest.model.Authentication', {
				requestid: 		"AuthenticationTest",
				app_id: 		appUuid,
				app_name: 		"ConnectorTest",
				dev_platform: 	Ext.os.name + ' ' + Ext.os.version,
				dev_type:		Ext.os.deviceType				
			});
			Ext.getStore('authentication').setData([authentication]);
			Ext.getStore('authentication').sync();
			Ext.getStore('authentication').clearFilter();
			Ext.getStore('authentication').filter(Ext.create('Ext.util.Filter',{property:"app_id",value:appUuid}));
			Ext.getStore('authentication').load({
				callback: function(records) {
					Ext.Array.each(records,function (record) {
						testresult = record;
						acknowledgeId = testresult.get('ack_id');
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresult).not.toBeLessThan(0);
			expect(testresult).not.toBe(null);
			expect(testresult.get('ack_id')).toBeDefined();
			expect(testresult.get('username')).toBe('SuperAdmin');
		});
	});
	
	it("hack Authentication 1", function() {
		runs(function() {
			flag = false;
			Ext.getStore('authentication').clearFilter();
			Ext.getStore('authentication').filter(Ext.create('Ext.util.Filter',{property:"app_id",value:acknowledgeId}));
			Ext.getStore('authentication').load({
				callback: function(records) {
					Ext.Array.each(records,function (record) {
						testresult = record;
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresult).not.toBeLessThan(0);
			expect(testresult).not.toBe(null);
			expect(testresult.get('ack_id')).not.toBeDefined();
			expect(testresult.get('requestid')).not.toBe('AuthenticationTested');
		});
	});	
	
	it("hack Authentication 2", function() {
		runs(function() {
			flag = false;
			Ext.getStore('authentication').clearFilter();
			Ext.getStore('authentication').filter(Ext.create('Ext.util.Filter',{property:"ack_id",value:appUuid}));
			Ext.getStore('authentication').load({
				callback: function(records) {
					Ext.Array.each(records,function (record) {
						testresult = record;
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresult).not.toBeLessThan(0);
			expect(testresult).not.toBe(null);
			expect(testresult.get('ack_id')).not.toBeDefined();
			expect(testresult.get('requestid')).not.toBe('AuthenticationTested');
		});
	});
	
	it("read (login) Authentication", function() {
		runs(function() {
			flag = false;
			Ext.getStore('authentication').clearFilter();
			Ext.getStore('authentication').filter(Ext.create('Ext.util.Filter',{property:"ack_id",value:acknowledgeId}));
			Ext.getStore('authentication').load({
				callback: function(records) {
					Ext.Array.each(records,function (record) {
						testresult = record;
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresult).not.toBeLessThan(0);
			expect(testresult).not.toBe(null);
			expect(testresult.get('ack_id')).toBeDefined();
			expect(testresult.get('requestid')).toBe('AuthenticationTest');
		});
	});	
});

describe("remote language translations", function () {
	var flag = false,
		testresult = null;
		
	it("load language", function() {
		runs(function() {
			flag = false;
			Ext.getStore('lang').clearFilter();
			Ext.getStore('lang').filter([Ext.create('Ext.util.Filter',{property:"domain",value:'extdirect'}),
			                             Ext.create('Ext.util.Filter',{property:"dir",value:'extdirect'})]);
			Ext.getStore('lang').load({
				callback: function(records) {
					Ext.Array.each(records, function (record) {
						if (record.get('name') == 'DirectConnect') {
							testresult = record.get('value');
						}
					});
					flag = true;
				}
    		}); 
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresult).toBe("Ext.direct connector");
		});
	});
});

describe("warehouse", function () {
	var flag = false,
		testresults = [];
		
	it("load warehouse", function() {
		runs(function() {
			flag = false;
			Ext.getStore("warehouse").load({
				callback: function(records) {
					Ext.Array.each(records, function (record,index) {
						testresults[index] = record.get('label');
						warehouseIds[index] = record.getId();
					});
					flag = true;
				}
    		}); 
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			Ext.Array.each(testresults, function (testresult,index) {
				// label must contain 1 or more characters
				expect(testresult).toMatch(/^.*$/);
			});
			
		});
	});
});

describe("priceindex", function () {
	var flag = false,
		testresults = [];
		
	it("load priceindex", function() {
		runs(function() {
			flag = false;
			Ext.getStore("priceindex").load({
				callback: function(records) {
					Ext.Array.each(records, function (record,index) {
						testresults[index] = record.get('name');
						if (index == 0) {
							priceIndex = record.getId();
						}
					});
					flag = true;
				}
    		}); 
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			Ext.Array.each(testresults, function (testresult,index) {
				// label must contain 1 or more characters
				expect(testresult).toMatch(/^.*$/);
				expect(priceIndex).toBe(0);
			});
			
		});
	});
});

describe("products", function () {
	var flag = false,			
		testresults = [],
		testresult = null,
		productRefs = [],
		productBarcodes = [];
		
	beforeEach(function() {
		testresults = [];
		testresult = null;
	});
		
	it("create products", function() {
		runs(function() {
			// add 2 products
			var productData,i,products = [];

			flag = false;
			productData = {
				ref: 'CT0001',								// product ref number
				label: 'connectortest',						// product name
				type: 0,									// product type (0 = product, 1 = service)
				description: 'connectortest test product',	// product detailed description
				warehouse_id: warehouseIds[1],				// product location
				status: true,								// product to sell or not
				status_buy: 1,								// make or buy product, 0 is make, 1 is buy
				finished: 1,								// product finished (1) or raw material (0)
				correct_stock_nbpiece: 10,					// product stock amount
				correct_stock_movement: 0,					// add (0) or remove(1) from stock 
				correct_stock_label: 'new test product',	// stock movement reason
				correct_stock_price: '10',					// stock buy price
				barcode: '1234567890005',					// product barcode
				barcode_type: 2								// barcode type 1 = EAN8, 2 = EAN13, 3 = UPC, 4 = ISBN, 5 = C39, 6 = C128
			};
			for (i=0;i<3;i++) {
				switch (i) {
					case 1:
						productData.ref = 'CT0002';
						productData.barcode = '1234567890012';
						break;
						
					case 2:
						productData.ref = 'CT0003';
						productData.barcode = '1234567890029';
						break;
	
					default:
						break;
				}
				products[i] = Ext.create('ConnectorTest.model.Product',productData);
			}
			Ext.getStore('product').add(products);					
			Ext.getStore('product').sync();
			Ext.getStore('product').clearFilter();
			Ext.getStore('product').filter(Ext.create('Ext.util.Filter',{property:"ref",value:'CT0001'}));
			Ext.getStore('product').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresults[0] = record.get('label');
					});
				}
			});
			Ext.getStore('product').clearFilter();
			Ext.getStore('product').filter(Ext.create('Ext.util.Filter',{property:"ref",value:'CT0002'}));
			Ext.getStore('product').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresults[1] = record.get('label');
					});
				}
			});
			Ext.getStore('product').clearFilter();
			Ext.getStore('product').filter(Ext.create('Ext.util.Filter',{property:"ref",value:'CT0003'}));
			Ext.getStore('product').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresults[2] = record.get('label');
					});
					delete products;
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			Ext.Array.each(testresults,function (result) {
				expect(result).toBe('connectortest');
			});
		});
	});
	
	it("read productlist", function() {
	
		runs(function() {
			var i=0;
			
			flag = false;
			Ext.getStore('productlist').clearFilter();
			Ext.getStore('productlist').filter(Ext.create('Ext.util.Filter',{property:"warehouse_id",value:warehouseIds[1]}));
			Ext.getStore('productlist').filter(Ext.create('Ext.util.Filter',{property:"status",value:true}));
			Ext.getStore('productlist').filter(Ext.create('Ext.util.Filter',{property:"status_buy",value:1}));
			Ext.getStore('productlist').filter(Ext.create('Ext.util.Filter',{property:"finished",value:1}));
			Ext.getStore('productlist').load({
				callback: function(records) {
					Ext.Array.each(records, function (record,index) {
						testresults[index] = record.get('label');
						if (record.get('label') == 'connectortest') {
							productIds[i] = record.get('product_id');
							productBarcodes[i] = record.get('barcode');
							productRefs[i++] = record.get('ref');
						}
					});
					flag = true;
				}
    		}); 
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults).toContain('connectortest');	
		});
	});
	
	it("read product 1 by Id", function() {
	
		runs(function() {
			flag = false;
			Ext.getStore('product').clearFilter();
			Ext.getStore('product').filter(Ext.create('Ext.util.Filter',{property:"warehouse_id",value:warehouseIds[1]}));
			Ext.getStore('product').filter(Ext.create('Ext.util.Filter',{property:"multiprices_index",value:priceIndex}));
			Ext.getStore('product').filter(Ext.create('Ext.util.Filter',{property:"id",value:productIds[0]}));
			Ext.getStore('product').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresult = record.get('ref');
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresult).toBe('CT0001');
		});
	});
	
	it("update product 1", function() {
		var record = Ext.getStore('product').find('ref','CT0001');
		
		runs(function() {
			flag = false;
			Ext.getStore('product').getAt(record).set('label','connectortested');
			Ext.getStore('product').getAt(record).set('correct_stock_nbpiece',5);
			Ext.getStore('product').getAt(record).set('correct_stock_label','move');
			Ext.getStore('product').getAt(record).set('correct_stock_price','15');
			Ext.getStore('product').getAt(record).set('correct_stock_dest_warehouseid',warehouseIds[2]);
			Ext.getStore('product').sync();
			Ext.getStore('product').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresults.push(record.get('label'));
						testresults.push(record.get('stock_reel'));
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(record).toBe(0);
			expect(testresults).toContain('connectortested');
			expect(testresults).toContain(5);
		});
	});
	
	
	
	it("read product 2 by ref", function() {
		
		runs(function() {
			flag = false;
			Ext.getStore('product').clearFilter();
			Ext.getStore('product').filter(Ext.create('Ext.util.Filter',{property:"warehouse_id",value:warehouseIds[1]}));
			Ext.getStore('product').filter(Ext.create('Ext.util.Filter',{property:"multiprices_index",value:priceIndex}));
			Ext.getStore('product').filter(Ext.create('Ext.util.Filter',{property:"ref",value:productRefs[1]}));
			Ext.getStore('product').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresult = record.get('ref');
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresult).toBe('CT0002');
		});
	});
	
	it("read product 3 by barcode", function() {
		
		runs(function() {
			flag = false;
			Ext.getStore('product').clearFilter();
			Ext.getStore('product').filter(Ext.create('Ext.util.Filter',{property:"warehouse_id",value:warehouseIds[1]}));
			Ext.getStore('product').filter(Ext.create('Ext.util.Filter',{property:"multiprices_index",value:priceIndex}));
			Ext.getStore('product').filter(Ext.create('Ext.util.Filter',{property:"barcode",value:productBarcodes[2]}));
			Ext.getStore('product').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresult = record.get('ref');
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresult).toBe('CT0003');
		});
	});
});

describe("order", function () {
	var flag = false,			
		testresults = [],
		testresult = null,
		orderRef = null,
		orderstatusIds = [],
		orderLineIds = [];
		
	beforeEach(function() {
		testresults = [];
		testresult = null;
	});
	
	it("read orderstatuslist", function() {
		
		runs(function() {
			var i=0;
			
			flag = false;
			Ext.getStore('orderstatus').load({
				callback: function(records) {
					Ext.Array.each(records, function (record,index) {
						testresults[index] = record.getId();
						orderstatusIds[i++] = record.getId();
					});
					flag = true;
				}
    		}); 
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults).toContain(-1);	
			expect(testresults).toContain(-0);	
			expect(testresults).toContain(1);	
			expect(testresults).toContain(2);	
			expect(testresults).toContain(3);	
		});
	});
		
	it("create order", function() {
		runs(function() {
			// add 2 products
			var orderData,order;

			flag = false;
			orderData = {
				ref_int: 'CT0001',
				note_private: 'connectortest private',
				note_public: 'connectortest public',
				ref_customer: 'connectortest',
				customer_id: 1,
				orderstatus_id: orderstatusIds[1],
				user_id: 1
			};
			order = Ext.create('ConnectorTest.model.Order',orderData);
			Ext.getStore('order').add(order);					
			Ext.getStore('order').sync();
			Ext.getStore('order').clearFilter();
			Ext.getStore('order').filter(Ext.create('Ext.util.Filter',{property:"ref_int",value:'CT0001'}));
			Ext.getStore('order').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresult = record.get('ref_customer');
					});
					delete order;
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresult).toBe('connectortest');
		});
	});
	
	it("read orderlist", function() {
	
		runs(function() {
			flag = false;
			Ext.getStore('orderlist').clearFilter();
			Ext.getStore('orderlist').filter(Ext.create('Ext.util.Filter',{property:"orderstatus_id",value:orderstatusIds[1]}));
			Ext.getStore('orderlist').load({
				callback: function(records) {
					Ext.Array.each(records, function (record,index) {
						testresults[index] = record.get('ref');
						if (record.get('ref_int') == 'CT0001') {
							orderRef = record.get('ref');
							orderId = record.getId();
						}
					});
					flag = true;
				}
    		}); 
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults).toContain(orderRef);	
		});
	});
	
	it("create orderlines", function() {
		runs(function() {
			// add 2 products
			var orderData,orderLine,orderLines = [];

			flag = false;
			orderData = {
				origin_id: orderId,
				description: 'connectortest',
				qty_asked: 2,
				product_id: null,
				product_price: 10,
				product_tax: 21
			};
			Ext.Array.each(productIds, function(productId){
				orderData.product_id = productId;
				orderLine = Ext.create('ConnectorTest.model.OrderLine',orderData);
				orderLines.push(orderLine);
			});
			
			Ext.getStore('orderline').add(orderLines);					
			Ext.getStore('orderline').sync();
			Ext.getStore('orderline').clearFilter();
			Ext.getStore('orderline').filter(Ext.create('Ext.util.Filter',{property:"order_id",value:orderId}));
			Ext.getStore('orderline').load({
				callback: function (records) {
					Ext.Array.each(records,function (record,index) {
						testresults[index] = record.get('description');
						orderLineIds[index] = record.get('origin_line_id');
					});
					delete orderLines;
					flag = true;
				}
			});			
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			Ext.Array.each(testresults, function(testresult) {
				expect(testresult).toBe('connectortest');
			});
		});
	});
	
	it("read order by Id", function() {
	
		runs(function() {
			flag = false;
			Ext.getStore('order').clearFilter();
			Ext.getStore('order').filter(Ext.create('Ext.util.Filter',{property:"id",value:orderId}));
			Ext.getStore('order').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresult = record.get('ref');
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresult).toBe(orderRef);
		});
	});
	
	it("update order", function() {
		var record = Ext.getStore('order').find('ref',orderRef);
		
		runs(function() {
			flag = false;
			Ext.getStore('order').getAt(record).set('customer_ref','connectortested');
			Ext.getStore('order').sync();
			Ext.getStore('order').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresult = record.get('customer_ref');
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(record).toBe(0);
			expect(testresult).toBe('connectortested');
		});
	});
	
	it("read order by ref", function() {
		
		runs(function() {
			flag = false;
			Ext.getStore('order').clearFilter();
			Ext.getStore('order').filter(Ext.create('Ext.util.Filter',{property:"ref",value:orderRef}));
			Ext.getStore('order').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresult = record.get('ref');
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresult).toBe(orderRef);
		});
	});	
	
	it("read orderline by Id", function() {
		var stock=0,
			asked=0;
		
		runs(function() {
			flag = false;
			Ext.getStore('orderline').clearFilter();
			Ext.getStore('orderline').filter(Ext.create('Ext.util.Filter',{property:"order_id",value:orderId}));
			Ext.getStore('orderline').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresults.push(record.get('warehouse_id'));
						stock+=record.get('qty_stock');
						asked+=record.get('qty_asked');
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults).toContain(1);
			expect(testresults).toContain(2);
			expect(testresults.length).toBe(4);
			expect(stock).toBe(30);
			expect(asked).toBe(8);
		});
	});
	
	it("update orderline", function() {
		var updateRecord = null;
		
		runs(function() {
			flag = false;
			updateRecord = Ext.getStore('orderline').findRecord('origin_line_id',orderLineIds[0]);
			updateRecord.set('description','connectortest update');
			updateRecord.set('qty_asked',4);
			Ext.getStore('orderline').sync();
			Ext.getStore('orderline').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresult+=record.get('qty_asked');
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresult).toBe(12);
		});
	});
	
	it("read orderline by Id and warehouse_id", function() {
		var stock=0;
		
		runs(function() {
			flag = false;
			Ext.getStore('orderline').clearFilter();
			Ext.getStore('orderline').filter(Ext.create('Ext.util.Filter',{property:"order_id",value:orderId}));
			Ext.getStore('orderline').filter(Ext.create('Ext.util.Filter',{property:"warehouse_id",value:warehouseIds[1]}));
			Ext.getStore('orderline').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresults.push(record.get('warehouse_id'));
						stock+=record.get('qty_stock');
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults).toContain(1);
			expect(testresults).not.toContain(2);
			expect(testresults.length).toBe(3);
			expect(stock).toBe(25);
		});
	});
});

describe("shipment", function () {
	var flag = false,			
		testresults = [],
		testresult = null,
		shipmentRef = null,
		shipmentLineIds = [];
		
	beforeEach(function() {
		testresults = [];
		testresult = null;
	});
				
	it("create shipment", function() {
		runs(function() {
			// add 2 products
			var shipmentData,shipment;

			flag = false;
			shipmentData = {
				ref_int: 'CT0001',
				origin: 'commande',
				origin_id: orderId,
				ref_customer: 'connectortest',
				customer_id: 1,
				weight_units:0,
				weight:10,
				size_units:0,
				trueDepth:.2,
				trueWidth:.2,
				trueHeight:.2
			};
			shipment = Ext.create('ConnectorTest.model.Order',shipmentData);
			Ext.getStore('shipment').add(shipment);					
			Ext.getStore('shipment').sync();
			Ext.getStore('shipment').clearFilter();
			Ext.getStore('shipment').filter(Ext.create('Ext.util.Filter',{property:"ref_int",value:'CT0001'}));
			Ext.getStore('shipment').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresult = record.get('ref_customer');
						shipmentId = record.get('id');
						shipmentRef = record.get('ref');
					});
					delete order;
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresult).toBe('connectortest');
		});
	});
	
	it("create shipmentlines", function() {
		runs(function() {
			// add 2 products
			var shipmentData,shipmentLine,shipmentLines = [];

			flag = false;
			Ext.getStore('orderline').each(function(orderLine) {
				shipmentData = {
					origin_id: shipmentId,
					warehouse_id:orderLine.get('warehouse_id'),
					origin_line_id:orderLine.get('origin_line_id'),
					qty_toship:2
				};
				shipmentLine = Ext.create('ConnectorTest.model.OrderLine',shipmentData);
				shipmentLines.push(shipmentLine);
			});
			Ext.getStore('shipmentline').add(shipmentLines);					
			Ext.getStore('shipmentline').sync();
			Ext.getStore('shipmentline').clearFilter();
			Ext.getStore('shipmentline').filter(Ext.create('Ext.util.Filter',{property:"origin_id",value:shipmentId}));
			Ext.getStore('shipmentline').load({
				callback: function (records) {
					Ext.Array.each(records,function (record,index) {
						testresults[index] = record.get('description');
						shipmentLineIds[index] = record.get('origin_line_id');
					});
					delete shipmentLines;
					flag = true;
				}
			});			
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			Ext.Array.each(testresults, function(testresult) {
				expect(testresult).toBe('connectortest');
			});
		});
	});
	
	it("read shipment by Id", function() {
	
		runs(function() {
			flag = false;
			Ext.getStore('shipment').clearFilter();
			Ext.getStore('shipment').filter(Ext.create('Ext.util.Filter',{property:"id",value:shipmentId}));
			Ext.getStore('shipment').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresult = record.get('ref');
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresult).toBe(shipmentRef);
		});
	});
	
	it("update shipment", function() {
		var record = Ext.getStore('shipment').find('ref',shipmentRef);
		
		runs(function() {
			flag = false;
			Ext.getStore('shipment').getAt(record).set('orderstatus_id',1);
			Ext.getStore('shipment').sync();
			Ext.getStore('shipment').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresult = record.get('orderstatus_id');
						shipmentRef = record.get('ref');
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(record).toBe(0);
			expect(testresult).toBe(1);
		});
	});
	
	it("read shipment by ref", function() {
		
		runs(function() {
			flag = false;
			Ext.getStore('shipment').clearFilter();
			Ext.getStore('shipment').filter(Ext.create('Ext.util.Filter',{property:"ref",value:shipmentRef}));
			Ext.getStore('shipment').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresult = record.get('ref');
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresult).toBe(shipmentRef);
		});
	});	
	
	it("read shipmentline by origin Id", function() {
		var shipped=0,
			asked=0;
		
		runs(function() {
			flag = false;
			Ext.getStore('shipmentline').clearFilter();
			Ext.getStore('shipmentline').filter(Ext.create('Ext.util.Filter',{property:"origin_id",value:shipmentId}));
			Ext.getStore('shipmentline').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresults.push(record.get('warehouse_id'));
						shipped+=record.get('qty_shipped');
						asked+=record.get('qty_asked');
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults).toContain(1);
			expect(testresults.length).toBe(3);
			expect(shipped).toBe(6);
			expect(asked).toBe(6);
		});
	});
});

describe("delete test records", function () {
	var flag = false,			
		testresult = null;
		
	beforeEach(function() {
		testresults = [];
		testresult = null;
	});
	
	it("destroy orderLines", function() {
		Ext.getStore('orderline').setDestroyRemovedRecords(true);
		Ext.getStore('orderline').setSyncRemovedRecords(true);
		runs(function() {
			flag = false;
			Ext.getStore('orderline').clearFilter();
			Ext.getStore('orderline').filter(Ext.create('Ext.util.Filter',{property:"order_id",value:orderId}));
			Ext.getStore('orderline').load({
				callback: function (records) {
					Ext.getStore('orderline').remove(records);
					Ext.getStore('orderline').sync();
					Ext.getStore('orderline').load({
						callback: function (records) {
							testresult = records.length;
							flag = true;
						}
					});
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresult).toBe(0);
		});
	});	
	
	it("destroy shipment", function() {
		var record = Ext.getStore('shipment').find('id',shipmentId);
		
		Ext.getStore('shipment').setDestroyRemovedRecords(true);
		Ext.getStore('shipment').setSyncRemovedRecords(true);
		runs(function() {
			flag = false;
			Ext.getStore('shipment').removeAt(record);
			Ext.getStore('shipment').sync();
			Ext.getStore('shipment').load({
				callback: function (records,operation,success) {
					testresult = Ext.getStore('shipment').find('id',shipmentId);
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(record).toBe(0);
			expect(testresult).toBe(-1);
		});
	});
	
	it("destroy order", function() {
		var record = Ext.getStore('order').find('id',orderId);
		
		Ext.getStore('order').setDestroyRemovedRecords(true);
		Ext.getStore('order').setSyncRemovedRecords(true);
		runs(function() {
			flag = false;
			Ext.getStore('order').removeAt(record);
			Ext.getStore('order').sync();
			Ext.getStore('order').load({
				callback: function (records,operation,success) {
					testresult = Ext.getStore('order').find('id',orderId);
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(record).toBe(0);
			expect(testresult).toBe(-1);
		});
	});
	
	it("destroy product 1", function() {
		Ext.getStore('product').setDestroyRemovedRecords(true);
		Ext.getStore('product').setSyncRemovedRecords(true);
		runs(function() {
			flag = false;
			Ext.getStore('product').clearFilter();
			Ext.getStore('product').filter(Ext.create('Ext.util.Filter',{property:"ref",value:'CT0001'}));
			Ext.getStore('product').load({
				callback: function (records) {
					Ext.getStore('product').remove(records);
					Ext.getStore('product').sync();
					Ext.getStore('product').load({
						callback: function (records) {
							testresult = Ext.getStore('product').find('ref','CT0001');
							flag = true;
						}
					});
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresult).toBe(-1);
		});
	});
	
	it("destroy product 2", function() {
		Ext.getStore('product').setDestroyRemovedRecords(true);
		Ext.getStore('product').setSyncRemovedRecords(true);
		runs(function() {
			flag = false;
			Ext.getStore('product').clearFilter();
			Ext.getStore('product').filter(Ext.create('Ext.util.Filter',{property:"ref",value:'CT0002'}));
			Ext.getStore('product').load({
				callback: function (records) {
					Ext.getStore('product').remove(records);
					Ext.getStore('product').sync();
					Ext.getStore('product').load({
						callback: function (records) {
							testresult = Ext.getStore('product').find('ref','CT0002');
							flag = true;
						}
					});
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresult).toBe(-1);
		});
	});
	
	it("destroy product 3", function() {
		Ext.getStore('product').setDestroyRemovedRecords(true);
		Ext.getStore('product').setSyncRemovedRecords(true);
		runs(function() {
			flag = false;
			Ext.getStore('product').clearFilter();
			Ext.getStore('product').filter(Ext.create('Ext.util.Filter',{property:"ref",value:'CT0003'}));
			Ext.getStore('product').load({
				callback: function (records) {
					Ext.getStore('product').remove(records);
					Ext.getStore('product').sync();
					Ext.getStore('product').load({
						callback: function (records) {
							testresult = Ext.getStore('product').find('ref','CT0003');
							flag = true;
						}
					});
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresult).toBe(-1);
		});
	});
});

xdescribe("destroy connection", function () {
	var flag = false,
		testresult = null;
		
	it("destroy connection", function() {
		runs(function() {
			flag = false;
			ExtDirectConnect.destroyUuid(appUuid, function (result,e) {
				testresult = result;
				flag = true;
    		}); 
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresult).not.toBeLessThan(0);
			expect(testresult).not.toBe(null);
			expect(testresult.id).toBeDefined();
			expect(testresult.id).toBeGreaterThan(0);
		});
	});
});

describe("destroy Authentication", function () {
	var flag = false,
		testresult = null;
		
	it("destroy Authentication", function() {
		var record = Ext.getStore('authentication').find('app_id',appUuid);
		
		Ext.getStore('authentication').setDestroyRemovedRecords(true);
		Ext.getStore('authentication').setSyncRemovedRecords(true);
		runs(function() {
			flag = false;
			Ext.getStore('authentication').removeAt(record);
			Ext.getStore('authentication').sync();
			Ext.getStore('authentication').load({
				callback: function (records,operation,success) {
					testresult = Ext.getStore('authentication').find('app_id',appUuid);
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(record).toBe(0);
			expect(testresult).toBe(-1);
		});
	});
});
