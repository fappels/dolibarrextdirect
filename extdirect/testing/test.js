/**
 * jasmine unit tests for extdirect connector
 */

var appUuid = null,
	app_id = null,
	warehouseIds = [],
	priceIndex = null,
	orderId = null,
	shipmentId = null,
	productIds = [],
	companyIds = [],
	contactId = 0,
	customerId = null,
	actionId,
	multiPrices = true;

var TIMEOUT = 8000;

	
describe("Authentication", function () {
	// enable autoasignment of superadmin in connector and set correct provider url and customer id in test below
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
			customerId = 1;
			Ext.Direct.getProvider("dolibarr_connector").setUrl("../router.php");
			Ext.getStore('authentication').setData([authentication]);
			Ext.getStore('authentication').sync();
			Ext.getStore('authentication').clearFilter();
			Ext.getStore('authentication').filter([Ext.create('Ext.util.Filter',{property:"app_id",value:appUuid})]);
			Ext.getStore('authentication').load({
				callback: function(records) {
					Ext.Array.each(records,function (record) {
						testresult = record;
						acknowledgeId = testresult.get('ack_id');
						app_id = testresult.getId();
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
			Ext.getStore('authentication').filter([Ext.create('Ext.util.Filter',{property:"app_id",value:acknowledgeId})]);
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
			Ext.getStore('authentication').filter([Ext.create('Ext.util.Filter',{property:"ack_id",value:appUuid})]);
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
			Ext.getStore('authentication').filter([Ext.create('Ext.util.Filter',{property:"ack_id",value:acknowledgeId})]);
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
				if (multiPrices) {
					expect(priceIndex).toBe(1);
				} else {
					expect(priceIndex).toBe(0);
				}
			});
			
		});
	});
});

describe("Activities", function () {
	var flag = false,
		testresults = [],
		testresult = null;
	
	it("create startup activity", function() {
		runs(function() {
			// add some activities
			var activityData,
				date = new Date();
			
			flag = false;
			activityData = {
				app_id:appUuid,
				activity_name:'APP_Start',
				activity_id:0,
				status:'DONE',
				datec:Ext.Date.format(date,'U')
			};
			Ext.getStore('activities').add(activityData);
			Ext.getStore('activities').sync();
			Ext.getStore('activities').load({
				callback: function (records) {
					Ext.Array.each(records,function(record){
						testresults.push(record.get('status'));
						testresult = records.length;
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults).toContain('DONE');	
			expect(testresult).toBeGreaterThan(0);	
		});
	});
});

describe("companies", function ()
{
    var flag = false,
		testresults = [],
		testresult = null;

    beforeEach(function ()
    {
        testresults = [];
        testresult = null;
    });

    it("create companies", function ()
    {
        runs(function ()
        {
            // add 3 companies
            var companyData, i, companies = [];

            companyData = {
                name: 'Company1', 							// company name
                ref_ext: 'connectortest',
                adress: '21 jump street',
                zip: '99999',
                town: 'MyTown',
                state_id: 1,
                state_code: 'AA',
                state: 'MyState',
                country_id: 1,
                country_code: 'FR',
                email: 'company1@specimen.com',
                url: 'http://www.specimen.com',

                phone: '0909090901',
                fax: '0909090909',
                capital: 10000,
                client: 1,
                prospect: 1,
                fournisseur: 1,
                tva_assuj: 1,
                tva_intra: 'EU1234567',
                note_public: 'This is a comment (public)',
                note_private: 'This is a comment (private)',

                idprof1: 'idprof1',
                idprof2: 'idprof2',
                idprof3: 'idprof3',
                idprof4: 'idprof4',
                idprof5: 'idprof5',
                idprof6: 'idprof6'
            };
            for (i = 0; i < 3; i++)
            {
                switch (i)
                {
                    case 1:
                        companyData.name = 'Company2';
                        companyData.email = 'company2@specimen.com';
                        break;

                    case 2:
                        companyData.name = 'Company3';
                        companyData.email = 'company3@specimen.com';
                        break;

                    default:
                        break;
                }
                companies[i] = Ext.create('ConnectorTest.model.Company', companyData);
            }
            Ext.getStore('companies').add(companies);
            Ext.getStore('companies').sync();
            Ext.getStore('companies').clearFilter();
            Ext.getStore('companies').filter([Ext.create('Ext.util.Filter', { property: "ref", value: 'Company1' })]);
            Ext.getStore('companies').load({
                callback: function (records)
                {
                    Ext.Array.each(records, function (record)
                    {
                        testresults[0] = record.get('ref_ext');
                    });
                }
            });
            Ext.getStore('companies').clearFilter();
            Ext.getStore('companies').filter([Ext.create('Ext.util.Filter', { property: "ref", value: 'Company2' })]);
            Ext.getStore('companies').load({
                callback: function (records)
                {
                    Ext.Array.each(records, function (record)
                    {
                        testresults[1] = record.get('ref_ext');
                    });
                }
            });
            Ext.getStore('companies').clearFilter();
            Ext.getStore('companies').filter([Ext.create('Ext.util.Filter', { property: "ref", value: 'Company3' })]);
            Ext.getStore('companies').load({
                callback: function (records)
                {
                    Ext.Array.each(records, function (record)
                    {
                        testresults[2] = record.get('ref_ext');
                    });
                    delete companies;
                    flag = true;
                }
            });
        });

        waitsFor(function () { return flag; }, "extdirect timeout", TIMEOUT * 2);

        runs(function ()
        {
            Ext.Array.each(testresults, function (result)
            {
                expect(result).toBe('connectortest');
            });
        });
    });

    it("read companylist", function ()
    {

        runs(function ()
        {

            flag = false;
            Ext.getStore('companylist').clearFilter();
            Ext.getStore('companylist').filter([Ext.create('Ext.util.Filter', { property: "town", value: 'MyTown' }),
                                                Ext.create('Ext.util.Filter', { property: "stcomm_id", value: 0 })]);
            Ext.getStore('companylist').load({
                callback: function (records)
                {
                    Ext.Array.each(records, function (record, index)
                    {
                        testresults[index] = record.get('ref_ext');
                        if (record.get('ref_ext') == 'connectortest')
                        {
                            companyIds[index] = record.get('company_id');
                        }
                    });
                    flag = true;
                }
            });
        });

        waitsFor(function () { return flag; }, "extdirect timeout", TIMEOUT);

        runs(function ()
        {
            expect(testresults).toContain('connectortest');
        });
    });

    it("create contact", function ()
    {
        runs(function ()
        {
            flag = false;
            var contacts = Ext.getStore('contacts'),
            	contactList = Ext.getStore('contactlist'),
            	contactData;

            contactData = {
                lastname: 'Contact', 							// company name
                firstname: 'connectortest',
                adress: '22 jump street',
                zip: '99999',
                town: 'MyTown',
                state: 'MyState',
                country: 'MyCountry',
                mail: 'company1@specimen.com',
                skype: 'MySkype',
                poste: 'myPoste',
                phone_pro: '0909090901',
                fax: '0909090909',
                note: 'This is a comment',
                company_id: companyIds[0]
            };

            contacts.add(Ext.create('ConnectorTest.model.Contact', contactData));
            contacts.sync();
            
            
        	contactList.clearFilter();
        	contactList.filter([Ext.create('Ext.util.Filter', { property: "town", value: 'MyTown' }),
                                Ext.create('Ext.util.Filter', { property: "company_id", value: companyIds[0] })]);
            contactList.load({
                callback: function ()
                {
                    testresult = contactList.first().get('name');
                    contactId = contactList.first().getId();
                    flag = true;
                }
            });
        });

        waitsFor(function () { return flag; }, "extdirect timeout", TIMEOUT);

        runs(function ()
        {
            expect(testresult).toBe('connectortest Contact');
        });
    });
    
    it("update contact", function () //TODO doesn't sync, don't know why
    {
        var record;

        runs(function ()
        {
        	var contacts = Ext.getStore('contacts');
        	
        	flag = false;
        	contacts.clearFilter();
        	contacts.filter([Ext.create('Ext.util.Filter', { property: "id", value: contactId })]);
        	contacts.load({
                callback: function (records)
                {
                    records[0].set('firstname', 'connectortested');
                    contacts.sync();
                    contacts.load({
                        callback: function (records)
                        {
                            Ext.Array.each(records, function (record)
                            {
                                testresults.push(record.get('firstname'));
                            });
                            flag = true;
                        }
                    });
                }
            });
        });

        waitsFor(function () { return flag; }, "extdirect timeout", TIMEOUT);

        runs(function ()
        {
            expect(testresults).toContain('connectortested');
        });
    });

    it("read towns", function ()
    {

        runs(function ()
        {

            flag = false;

            Ext.getStore('towns').load({
                callback: function (records)
                {
                    testresults[0] = records[0].get('id');
                    testresults[1] = records[0].get('town');
                    flag = true;
                }
            });
        });

        waitsFor(function () { return flag; }, "extdirect timeout", TIMEOUT);

        runs(function ()
        {
            expect(testresults[0]).toMatch(testresults[1]);
        });
    });

    xit("read categories", function ()
    {

        runs(function ()
        {

            flag = false;

            Ext.getStore('categories').load({
                callback: function (records)
                {
                    testresult = records.length;
                    flag = true;
                }
            });
        });

        waitsFor(function () { return flag; }, "extdirect timeout", TIMEOUT);

        runs(function ()
        {
            expect(testresult).toBeGreaterThan(0);
        });
    });

    it("read commercialstatus", function ()
    {

        runs(function ()
        {

            flag = false;

            Ext.getStore('commercialstatus').load({
                callback: function (records)
                {
                    Ext.Array.each(records, function (record, index)
                    {
                        testresults[index] = record.get('stcomm_code');
                    });
                    flag = true;
                }
            });
        });

        waitsFor(function () { return flag; }, "extdirect timeout", TIMEOUT);

        runs(function ()
        {
            expect(testresults).toContain('ST_NO');
        });
    });

    it("read prospectlevel", function ()
    {

        runs(function ()
        {

            flag = false;

            Ext.getStore('prospectlevel').load({
                callback: function (records)
                {
                    Ext.Array.each(records, function (record, index)
                    {
                        testresults[index] = record.get('prospectlevel_code');
                    });
                    flag = true;
                }
            });
        });

        waitsFor(function () { return flag; }, "extdirect timeout", TIMEOUT);

        runs(function ()
        {
            expect(testresults).toContain('PL_NONE');
        });
    });

    it("read Company 1 by Id", function ()
    {

        runs(function ()
        {
            flag = false;
            Ext.getStore('companies').clearFilter();
            Ext.getStore('companies').filter([Ext.create('Ext.util.Filter', { property: "id", value: companyIds[0] })]);
            Ext.getStore('companies').load({
                callback: function (records)
                {
                    Ext.Array.each(records, function (record)
                    {
                        testresult = record.get('name');
                    });
                    flag = true;
                }
            });
        });

        waitsFor(function () { return flag; }, "extdirect timeout", TIMEOUT);

        runs(function ()
        {
            expect(testresult).toBe('Company1');
        });
    });

    it("update company 1", function ()
    {
        var record;

        runs(function ()
        {
            flag = false;
            if ((record = Ext.getStore('companies').find('name', 'Company1')) >= 0)
            {
                Ext.getStore('companies').getAt(record).set('ref_ext', 'connectortested');
                Ext.getStore('companies').sync();
                Ext.getStore('companies').load({
                    callback: function (records)
                    {
                        Ext.Array.each(records, function (record)
                        {
                            testresults.push(record.get('ref_ext'));
                        });
                        flag = true;
                    }
                });
            } else
            {
                flag = true;
            }

        });

        waitsFor(function () { return flag; }, "extdirect timeout", TIMEOUT);

        runs(function ()
        {
            expect(testresults).toContain('connectortested');
        });
    });
});

describe("actions", function () {
	var flag = false,			
		testresults = [],
		testresult = null,
		userIds = [];
		
	beforeEach(function() {
		testresults = [];
		testresult = null;
	});
		
	it("read all users", function ()
    {
        runs(function ()
        {

            flag = false;

            Ext.getStore('users').load({
                callback: function (records)
                {
                    Ext.Array.each(records, function(record,index) {
                    	userIds[index] = record.get('id');
                    });
                    
                	testresult = records.length;
                    flag = true;
                }
            });
        });

        waitsFor(function () { return flag; }, "extdirect timeout", TIMEOUT);

        runs(function ()
        {
            expect(testresult).toBeGreaterThan(0);
        });
    });
	
	it("create action", function() {
		runs(function() {
			
			var actions = Ext.getStore('actions'),
				actionList = Ext.getStore('actionlist'),
				actionData;

			flag = false;
			actionData = {
				datep: Ext.Date.format(new Date(),'U'),
				datef: Ext.Date.format(new Date(),'U'),
				label: 'myAction',
				note: 'note',
				usertodo_id: userIds[1],
				userdone_id: userIds[0],
				location: 'connectortest',
				company_id:companyIds[0],
				contact_id:contactId,
				durationp: 10
			};
			actions.add(Ext.create('ConnectorTest.model.Action', actionData));
			actions.sync();
			
			actionList.clearFilter();
        	actionList.filter([Ext.create('Ext.util.Filter', { property: "company_id", value: companyIds[0] })]);
        	actionList.load({
                callback: function (records)
                {
                	
                	Ext.Array.each(records, function (record, index)
                    {
                    	if (record.get('label') === 'myAction') {
                    		actionId = actions.first().getId();
                    	}
                    	
                		testresults[index] = record.get('companyname');
                    });
                    flag = true;
                }
            });
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
            expect(testresults).toContain('Company1');
        });
	});
	
	it("update action", function ()
    {
        var actions = Ext.getStore('actions');

        runs(function ()
        {
            flag = false;
            
        	actions.clearFilter();
        	actions.filter([Ext.create('Ext.util.Filter', { property: "id", value: actionId })]);
        	actions.load({
                callback: function () {
                	actions.first().set('location','connectortested');
                	actions.sync();
                	actions.load({
                        callback: function (records) {
                		Ext.Array.each(records, function (record)
                        {
                            testresults.push(record.get('location'));
                        });
                        flag = true;
                        }
                    });
                }
            });
        });

        waitsFor(function () { return flag; }, "extdirect timeout", TIMEOUT);

        runs(function ()
        {
            expect(testresults).toContain('connectortested');
        });
    });
});

describe("categories", function () {
	var flag = false,			
		testresults = [],
		testresult = null;
		
	beforeEach(function() {
		testresults = [];
		testresult = null;
	});
		
	
	
	it("create categorie", function() {
		runs(function() {
			// add 2 categories
			var categorieData;

			flag = false;
			categorieData = {
				label: 'Categorie1',
				description: 'connectortest',
				type: 0
			};
			Ext.getStore('categories').add(Ext.create('ConnectorTest.model.Categorie', categorieData));
            categorieData = {
				label: 'Categorie2',
				description: 'connectortest',
				type: 1
			};
			Ext.getStore('categories').add(Ext.create('ConnectorTest.model.Categorie', categorieData));
			Ext.getStore('categories').sync();
            Ext.getStore('categories').clearFilter();
            Ext.getStore('categories').filter([Ext.create('Ext.util.Filter', { property: "label", value: 'Categorie1'})]);
            Ext.getStore('categories').load({
                callback: function (records)
                {
                    Ext.Array.each(records, function (record)
                    {
                        testresults[0] = record.get('description');
                    });
                    Ext.getStore('categories').clearFilter();
                    Ext.getStore('categories').filter([Ext.create('Ext.util.Filter', { property: "label", value: 'Categorie2'})]);
                    Ext.getStore('categories').load({
                        callback: function (records)
                        {
                            Ext.Array.each(records, function (record)
                            {
                                testresults[1] = record.get('description');
                            });
                            flag = true;
                        }
                    });
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
	
	it("read categorielist", function ()
    {
        runs(function ()
        {

            flag = false;
            Ext.getStore('categorielist').clearFilter();
            Ext.getStore('categorielist').filter([Ext.create('Ext.util.Filter', { property: "type", value: 0})]);
            Ext.getStore('categorielist').load({
                callback: function (records)
                {
                    Ext.Array.each(records, function(record,index) {
                    	testresult = record.get('categorie');
                    });
                    flag = true;
                }
            });
        });

        waitsFor(function () { return flag; }, "extdirect timeout", TIMEOUT);

        runs(function ()
        {
            expect(testresult).toBe('Categorie1');
        });
    });
	
	it("update categorie", function ()
    {
        var record;

        runs(function ()
        {
            flag = false;
            if ((record = Ext.getStore('categories').find('label', 'Categorie2')) !== undefined)
            {
                Ext.getStore('categories').getAt(record).set('description', 'connectortested');
                Ext.getStore('categories').sync();
                Ext.getStore('categories').load({
                    callback: function (records)
                    {
                        Ext.Array.each(records, function (record)
                        {
                            testresults.push(record.get('description'));
                        });
                        flag = true;
                    }
                });
            } else
            {
                flag = true;
            }

        });

        waitsFor(function () { return flag; }, "extdirect timeout", TIMEOUT);

        runs(function ()
        {
            expect(testresults).toContain('connectortested');
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
			var productData,i,products = [],productStore;

			flag = false;
			productData = {
				ref: 'CT0001',								// company name
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
				barcode: '*12345*',							// product barcode
				barcode_type: 5								// barcode type 1 = EAN8, 2 = EAN13, 3 = UPC, 4 = ISBN, 5 = C39, 6 = C128
			};
			for (i=0;i<3;i++) {
				switch (i) {
					case 1:
						productData.ref = 'CT0002';
						productData.barcode = '*123456*';
						break;
						
					case 2:
						productData.ref = 'CT0003';
						productData.barcode = '*1234567*';
						break;
	
					default:
						break;
				}
				products[i] = Ext.create('ConnectorTest.model.Product',productData);
			}
			productStore = Ext.getStore('product');
			productStore.add(products);					
			productStore.sync();
			productStore.clearFilter();
			productStore.filter([Ext.create('Ext.util.Filter',{property:"ref",value:'CT0001'})]);
			productStore.load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresults[0] = record.get('label');
					});
				}
			});
			productStore.clearFilter();
			productStore.filter([Ext.create('Ext.util.Filter',{property:"ref",value:'CT0002'})]);
			productStore.load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresults[1] = record.get('label');
					});
				}
			});
			productStore.clearFilter();
			productStore.filter([Ext.create('Ext.util.Filter',{property:"ref",value:'CT0003'})]);
			productStore.load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresults[2] = record.get('label');
					});
					delete products;
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT*2);
		
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
			Ext.getStore('productlist').filter([Ext.create('Ext.util.Filter',{property:"warehouse_id",value:warehouseIds[1]}),
			                                    Ext.create('Ext.util.Filter',{property:"status",value:true}),
			                                    Ext.create('Ext.util.Filter',{property:"status_buy",value:1}),
			                                    Ext.create('Ext.util.Filter',{property:"finished",value:1})]);
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
			Ext.getStore('product').filter([Ext.create('Ext.util.Filter',{property:"warehouse_id",value:warehouseIds[1]}),
											Ext.create('Ext.util.Filter',{property:"multiprices_index",value:priceIndex}),
											Ext.create('Ext.util.Filter',{property:"id",value:productIds[0]})]);
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
			Ext.getStore('product').getAt(record).set('correct_stock_movement',1);	
			Ext.getStore('product').getAt(record).set('correct_stock_label','move');
			Ext.getStore('product').getAt(record).set('correct_stock_price','15');
			Ext.getStore('product').getAt(record).set('correct_stock_dest_warehouseid',warehouseIds[2]);
			Ext.getStore('product').sync();
            Ext.getStore('product').clearFilter();
			Ext.getStore('product').filter([Ext.create('Ext.util.Filter',{property:"warehouse_id",value:warehouseIds[1]}),
			                                Ext.create('Ext.util.Filter',{property:"multiprices_index",value:priceIndex}),
			                                Ext.create('Ext.util.Filter',{property:"id",value:productIds[0]})]);
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
			Ext.getStore('product').filter([Ext.create('Ext.util.Filter',{property:"warehouse_id",value:warehouseIds[1]}),
			                                Ext.create('Ext.util.Filter',{property:"multiprices_index",value:priceIndex}),
			                                Ext.create('Ext.util.Filter',{property:"ref",value:productRefs[1]})]);
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
			Ext.getStore('product').filter([Ext.create('Ext.util.Filter',{property:"warehouse_id",value:warehouseIds[1]}),
			                                Ext.create('Ext.util.Filter',{property:"multiprices_index",value:priceIndex}),
			                                Ext.create('Ext.util.Filter',{property:"barcode",value:productBarcodes[2]})]);
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
			var orderData,order,orderStore;

			flag = false;
			orderData = {
				ref_int: 'CT0001',
				note_private: 'connectortest private',
				note_public: 'connectortest public',
				ref_customer: 'connectortest',
				customer_id: customerId,
				orderstatus_id: orderstatusIds[1],
				user_id: 1,
				order_date: Ext.Date.format(new Date(),'U')
			};
			order = Ext.create('ConnectorTest.model.Order',orderData);
			orderStore = Ext.getStore('order');
			orderStore.add(order);					
			orderStore.sync();
			orderStore.clearFilter();
			orderStore.filter([Ext.create('Ext.util.Filter',{property:"ref_int",value:'CT0001'})]);
			orderStore.load({
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
			Ext.getStore('orderlist').filter([Ext.create('Ext.util.Filter',{property:"orderstatus_id",value:orderstatusIds[1]})]);
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
			Ext.getStore('orderline').filter([Ext.create('Ext.util.Filter',{property:"order_id",value:orderId})]);
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
			Ext.getStore('order').filter([Ext.create('Ext.util.Filter',{property:"id",value:orderId})]);
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
			Ext.getStore('order').filter([Ext.create('Ext.util.Filter',{property:"ref",value:orderRef})]);
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
			Ext.getStore('orderline').filter([Ext.create('Ext.util.Filter',{property:"order_id",value:orderId})]);
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
			Ext.getStore('orderline').filter([Ext.create('Ext.util.Filter',{property:"order_id",value:orderId}),
			                                  Ext.create('Ext.util.Filter',{property:"warehouse_id",value:warehouseIds[1]})]);
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
				customer_id: customerId,
				weight_units:0,
				weight:10,
				size_units:0,
				trueDepth:.2,
				trueWidth:.2,
				trueHeight:.2,
				deliver_date: Ext.Date.format(new Date(),'U')
			};
			shipment = Ext.create('ConnectorTest.model.Order',shipmentData);
			Ext.getStore('shipment').add(shipment);					
			Ext.getStore('shipment').sync();
			Ext.getStore('shipment').clearFilter();
			Ext.getStore('shipment').filter([Ext.create('Ext.util.Filter',{property:"ref_int",value:'CT0001'})]);
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
			Ext.getStore('shipmentline').filter([Ext.create('Ext.util.Filter',{property:"origin_id",value:shipmentId})]);
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
			Ext.getStore('shipment').filter([Ext.create('Ext.util.Filter',{property:"id",value:shipmentId})]);
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
			Ext.getStore('shipment').filter([Ext.create('Ext.util.Filter',{property:"ref",value:shipmentRef})]);
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
			Ext.getStore('shipmentline').filter([Ext.create('Ext.util.Filter',{property:"origin_id",value:shipmentId})]);
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

describe("delete shipments and orders", function () {
	var flag = false,			
		testresult = null,
		testresults = [];
		
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
			Ext.getStore('orderline').filter([Ext.create('Ext.util.Filter',{property:"order_id",value:orderId})]);
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
});

describe("delete products", function () {
	var flag = false,			
		testresult = null,
		testresults = [];
		
	beforeEach(function() {
		testresults = [];
		testresult = null;
	});
	
	it("destroy product 1", function() {
		Ext.getStore('product').setDestroyRemovedRecords(true);
		Ext.getStore('product').setSyncRemovedRecords(true);
		runs(function() {
			flag = false;
			Ext.getStore('product').clearFilter();
			Ext.getStore('product').filter([Ext.create('Ext.util.Filter',{property:"ref",value:'CT0001'})]);
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
			Ext.getStore('product').filter([Ext.create('Ext.util.Filter',{property:"ref",value:'CT0002'})]);
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
			Ext.getStore('product').filter([Ext.create('Ext.util.Filter',{property:"ref",value:'CT0003'})]);
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

describe("delete categories and actions", function () {
	var flag = false,			
		testresult = null,
		testresults = [];
		
	beforeEach(function() {
		testresults = [];
		testresult = null;
	});
	
	it("destroy Categorie1", function() {
		Ext.getStore('categories').setDestroyRemovedRecords(true);
		Ext.getStore('categories').setSyncRemovedRecords(true);
		runs(function() {
			flag = false;
			
			Ext.getStore('categories').clearFilter();
			Ext.getStore('categories').filter([Ext.create('Ext.util.Filter',{property:"label",value:'Categorie1'})]);
			Ext.getStore('categories').load({
				callback: function (records) {
					Ext.getStore('categories').remove(records);
					Ext.getStore('categories').sync();
					Ext.getStore('categories').load({
						callback: function (records) {
							testresults.push(Ext.getStore('categories').find('ref','Categorie1'));
							flag = true;
						}
					});
				}
			});
			
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults).toContain(-1);
		});
	});
	
	it("destroy Categorie2", function() {
		Ext.getStore('categories').setDestroyRemovedRecords(true);
		Ext.getStore('categories').setSyncRemovedRecords(true);
		runs(function() {
			flag = false;
			
			Ext.getStore('categories').clearFilter();
			Ext.getStore('categories').filter([Ext.create('Ext.util.Filter',{property:"label",value:'Categorie2'})]);
			Ext.getStore('categories').load({
				callback: function (records) {
					Ext.getStore('categories').remove(records);
					Ext.getStore('categories').sync();
					Ext.getStore('categories').load({
						callback: function (records) {
							testresults.push(Ext.getStore('categories').find('ref','Categorie2'));
							flag = true;
						}
					});
				}
			});
			
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults).toContain(-1);
		});
	});

	it("destroy action", function() {
		Ext.getStore('actions').setDestroyRemovedRecords(true);
		Ext.getStore('actions').setSyncRemovedRecords(true);
		runs(function() {
			flag = false;
			
			Ext.getStore('actions').clearFilter();
			Ext.getStore('actions').filter([Ext.create('Ext.util.Filter',{property:"id",value:actionId})]);
			Ext.getStore('actions').load({
				callback: function (records) {
					Ext.getStore('actions').remove(records);
					Ext.getStore('actions').sync();
					Ext.getStore('actions').load({
						callback: function (records) {
							testresults.push(Ext.getStore('actions').find('ref','myAction'));
							flag = true;
						}
					});
				}
			});
			
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults).toContain(-1);
		});
	});
});

describe("delete contacts and companies", function () {
	var flag = false,			
		testresult = null,
		testresults = [];
		
	beforeEach(function() {
		testresults = [];
		testresult = null;
	});
	
	it("destroy contact", function() {
		Ext.getStore('contacts').setDestroyRemovedRecords(true);
		Ext.getStore('contacts').setSyncRemovedRecords(true);
		runs(function() {
			flag = false;
			
			Ext.getStore('contacts').clearFilter();
			Ext.getStore('contacts').filter([Ext.create('Ext.util.Filter',{property:"id", value:contactId})]);
			Ext.getStore('contacts').load({
				callback: function (records) {
					Ext.getStore('contacts').remove(records);
					Ext.getStore('contacts').sync();
					Ext.getStore('contacts').load({
						callback: function (records) {
							testresults.push(Ext.getStore('contacts').find('name','Contact'));
							flag = true;
						}
					});
				}
			});
			
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults).toContain(-1);
		});
	});

	// TODO destroy 2 categories
	// destroy action
	// destroy contact

	it("destroy Company1", function() {
		Ext.getStore('companies').setDestroyRemovedRecords(true);
		Ext.getStore('companies').setSyncRemovedRecords(true);
		runs(function() {
			flag = false;
			
			Ext.getStore('companies').clearFilter();
			Ext.getStore('companies').filter([Ext.create('Ext.util.Filter',{property:"ref",value:'Company1'})]);
			Ext.getStore('companies').load({
				callback: function (records) {
					Ext.getStore('companies').remove(records);
					Ext.getStore('companies').sync();
					Ext.getStore('companies').load({
						callback: function (records) {
							testresults.push(Ext.getStore('companies').find('ref','Company1'));
							flag = true;
						}
					});
				}
			});
			
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults).toContain(-1);
		});
	});
	
	it("destroy Company2", function() {
		Ext.getStore('companies').setDestroyRemovedRecords(true);
		Ext.getStore('companies').setSyncRemovedRecords(true);
		runs(function() {
			flag = false;
			
			Ext.getStore('companies').clearFilter();
			Ext.getStore('companies').filter([Ext.create('Ext.util.Filter',{property:"ref",value:'Company2'})]);
			Ext.getStore('companies').load({
				callback: function (records) {
					Ext.getStore('companies').remove(records);
					Ext.getStore('companies').sync();
					Ext.getStore('companies').load({
						callback: function (records) {
							testresults.push(Ext.getStore('companies').find('ref','Company2'));
							flag = true;
						}
					});
				}
			});
			
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults).toContain(-1);
		});
	});
	
	it("destroy Company3", function() {
		Ext.getStore('companies').setDestroyRemovedRecords(true);
		Ext.getStore('companies').setSyncRemovedRecords(true);
		runs(function() {
			flag = false;
			
			Ext.getStore('companies').clearFilter();
			Ext.getStore('companies').filter([Ext.create('Ext.util.Filter',{property:"ref",value:'Company3'})]);
			Ext.getStore('companies').load({
				callback: function (records) {
					Ext.getStore('companies').remove(records);
					Ext.getStore('companies').sync();
					Ext.getStore('companies').load({
						callback: function (records) {
							testresults.push(Ext.getStore('companies').find('ref','Company2'));
							flag = true;
						}
					});
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults).toContain(-1);
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
