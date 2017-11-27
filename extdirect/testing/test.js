/**
 * jasmine unit tests for extdirect connector
 */

var appUuid = null,
	warehouseIds = [],
	priceIndex = null,
	orderId = null,
	shipmentId = null,
	purchaseOrderId = 0,
	productIds = [],
	companyIds = [],
	contactId = 0,
	customerId = null,
	actionId,
	multiPrices = true,
	dolibarrVersion = null,
	sellby = Ext.Date.format(new Date(2020,5,30),'U'),
	eatby = Ext.Date.format(new Date(2020,11,31),'U');

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
						dolibarrVersion = record.get('dolibarr_version');
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
			Ext.Array.each(testresults, function (testresult) {
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
			Ext.Array.each(testresults, function (testresult) {
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

describe("producttype", function () {
	var flag = false,
		testresults = [];
		
	it("load producttypes", function() {
		runs(function() {
			flag = false;
			Ext.getStore("ProductTypeList").load({
				callback: function(records) {
					Ext.Array.each(records, function (record,index) {
						testresults[index] = record.get('label');
					});
					flag = true;
				}
    		}); 
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			Ext.Array.each(testresults, function (testresult) {
				// label must contain 1 or more characters
				expect(testresult).toMatch(/^Produit|Product|Service$/);
			});
			
		});
	});
});

describe("barcodetypes", function () {
	var flag = false,
		testresults = [];
		
	it("load barcodetypes", function() {
		runs(function() {
			flag = false;
			Ext.getStore("BarcodeTypes").load({
				callback: function(records) {
					Ext.Array.each(records, function (record,index) {
						testresults[index] = record.get('label');
					});
					flag = true;
				}
    		}); 
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults).toContain('EAN13');		
		});
	});
});

describe("availability codes", function () {
	var flag = false,
		testresults = [];
		
	it("load availability codes", function() {
		runs(function() {
			flag = false;
			Ext.getStore("availability").load({
				callback: function(records) {
					Ext.Array.each(records, function (record,index) {
						testresults[index] = record.get('code');
					});
					flag = true;
				}
    		}); 
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			Ext.Array.each(testresults, function (testresult) {
				// label must contain 1 or more characters
				expect(testresult).toMatch(/^AV_.+/);
			});
			
		});
	});
});

describe("shipment modes", function () {
	var flag = false,
		testresults = [];
	
	it("load shipment modes", function() {
		if (dolibarrVersion >= 3.7)	{	
			runs(function() {
				flag = false;
				Ext.getStore("ShipmentModes").load({
					callback: function(records) {
						Ext.Array.each(records, function (record,index) {
							testresults[index] = record.get('code');
						});
						flag = true;
					}
	    		}); 
			});
			
			waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
			
			runs(function () {
				expect(testresults).toContain('CATCH');				
			});
		}
	});
});

describe("incoterms codes", function () {
	var flag = false,
		testresults = [];
	
	it("load incoterms codes", function() {
		if (dolibarrVersion >= 3.8)	{		
			runs(function() {
				flag = false;
				Ext.getStore("IncotermsCodes").load({
					callback: function(records) {
						Ext.Array.each(records, function (record,index) {
							testresults[index] = record.get('code');
						});
						flag = true;
					}
	    		}); 
			});
			
			waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
			
			runs(function () {
				expect(testresults).toContain('EXW');
			});
		}
	});
});

describe("price_base_type modes", function () {
	var flag = false,
		testresults = [];
	
	it("load price_base_type modes", function() {
		runs(function() {
			flag = false;
			Ext.getStore("PriceBaseTypes").load({
				callback: function(records) {
					Ext.Array.each(records, function (record,index) {
						testresults[index] = record.get('code');
					});
					flag = true;
				}
    		}); 
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults).toContain('HT');
			expect(testresults).toContain('TTC');
		});
	});
});

describe("Supplier Reputations", function () {
	var flag = false,
		testresults = [];
	
	it("load Supplier Reputations", function() {
		if (dolibarrVersion >= 5.0)
		runs(function() {
			flag = false;
			Ext.getStore("SupplierReputations").load({
				callback: function(records) {
					Ext.Array.each(records, function (record,index) {
						testresults[index] = record.get('code');
					});
					flag = true;
				}
    		}); 
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults).toContain('FAVORITE');
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
			Ext.getStore('activities').filter([Ext.create('Ext.util.Filter', { property: "app_id", value: appUuid })]);
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
                    	if (dolibarrVersion >= 3.4) {
                    		testresults[0] = record.get('ref_ext');
                    	} else {
                    		testresults[0] = record.get('town');
                    	}                   	
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
                    	if (dolibarrVersion >= 3.4) {
                    		testresults[0] = record.get('ref_ext');
                    	} else {
                    		testresults[0] = record.get('town');
                    	} 
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
                    	if (dolibarrVersion >= 3.4) {
                    		testresults[0] = record.get('ref_ext');
                    	} else {
                    		testresults[0] = record.get('town');
                    	} 
                    });
                    flag = true;
                }
            });
        });

        waitsFor(function () { return flag; }, "extdirect timeout", TIMEOUT * 2);

        runs(function ()
        {
            Ext.Array.each(testresults, function (result)
            {
            	if (dolibarrVersion >= 3.4) {
            		expect(result).toBe('connectortest');
            	} else {
            		expect(result).toBe('MyTown');
            	}           	
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
                    	if (dolibarrVersion >= 3.4) {
                    		testresults[index] = record.get('ref_ext');
                        	
							if (record.get('ref_ext') == 'connectortest')
							{
							    companyIds[index] = record.get('company_id');
							}
                    	} else {
                    		testresults[index] = record.get('town');
                        	
							if (record.get('town') == 'MyTown')
							{
							    companyIds[index] = record.get('company_id');
							}
                    	}
                    	
                    });
                    flag = true;
                }
            });
        });

        waitsFor(function () { return flag; }, "extdirect timeout", TIMEOUT);

        runs(function ()
        {
        	if (dolibarrVersion >= 3.4) {
        		expect(testresults).toContain('connectortest');
        	} else {
        		expect(testresults).toContain('MyTown');
        	}     	
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
                    Ext.Array.each(records, function(record,index) {
                    	testresults[index] =record.get('town');
                    });                	
                    flag = true;
                }
            });
        });

        waitsFor(function () { return flag; }, "extdirect timeout", TIMEOUT);

        runs(function ()
        {
            expect(testresults).toContain('MyTown');
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
    
    it("read payment types", function ()
    {

        runs(function ()
        {

            flag = false;

            Ext.getStore('PaymentTypes').load({
                callback: function (records)
                {
                    Ext.Array.each(records, function (record, index)
                    {
                        testresults[index] = record.get('code');
                    });
                    flag = true;
                }
            });
        });

        waitsFor(function () { return flag; }, "extdirect timeout", TIMEOUT);

        runs(function ()
        {
            expect(testresults).toContain('LIQ');
        });
    });
    
    it("read payment terms", function ()
    {

        runs(function ()
        {

            flag = false;

            Ext.getStore('PaymentConditions').load({
                callback: function (records)
                {
                    Ext.Array.each(records, function (record, index)
                    {
                        testresults[index] = record.get('code');
                    });
                    flag = true;
                }
            });
        });

        waitsFor(function () { return flag; }, "extdirect timeout", TIMEOUT);

        runs(function ()
        {
            expect(testresults).toContain('RECEP');
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
        var record, testField = 'ref_ext';
        
        if (dolibarrVersion < 3.4) {
        	testField = 'town';		
        }

        runs(function ()
        {
            flag = false;
            if ((record = Ext.getStore('companies').find('name', 'Company1')) >= 0)
            {
            	Ext.getStore('companies').getAt(record).set(testField, 'connectortested');
                Ext.getStore('companies').sync();
                Ext.getStore('companies').load({
                    callback: function (records)
                    {
                        Ext.Array.each(records, function (record)
                        {
                            testresults.push(record.get(testField));
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
		testresults = [];
		
	beforeEach(function() {
		testresults = [];
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
                    	testresults[index] = record.get('categorie');
                    });
                    flag = true;
                }
            });
        });

        waitsFor(function () { return flag; }, "extdirect timeout", TIMEOUT);

        runs(function ()
        {
            expect(testresults).toContain('Categorie1');
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
		productBarcodes = [],
		supplierRefs = [],
		productStore,
		optionalModel = [];
		
	beforeEach(function() {
		testresults = [];
		testresult = null;
        productStore = Ext.getStore('product');
	});
		
	it("read Optional Model", function() {
		runs(function() {
			var optional = {};
			flag = false;

			Ext.getStore('ProductOptionalModel').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						optional.name = record.get('name');
						optional.label = record.get('label');
						optionalModel.push(optional);
					});
					flag = true;
				}
			});
		});

		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			Ext.Array.each(optionalModel,function (optional) {
				expect(optional.label).toBe('Test');
			});
		});
	});

	it("create products", function() {
		runs(function() {
			// add 3 products
			var productData,i,products = [];

			flag = false;
			productData = {
				ref: 'CT0001',								// company name
				label: 'connectortest',						// product name
				type: 0,									// product type (0 = product, 1 = service)
				description: 'connectortest test product',	// product detailed description
				warehouse_id: warehouseIds[1],				// product location
				tosell: 1,									// product to sell or not
				tobuy: 1,									// make or buy product, 0 is make, 1 is buy
				finished: 1,								// product finished (1) or raw material (0)
				correct_stock_nbpiece: 10,					// product stock amount
				correct_stock_movement: 0,					// add (0) or remove(1) from stock 
				correct_stock_label: 'new test product',	// stock movement reason
				correct_stock_price: '10',					// stock buy price
				barcode: '12345',							// product barcode
				barcode_type: 5,							// barcode type 1 = EAN8, 2 = EAN13, 3 = UPC, 4 = ISBN, 5 = C39, 6 = C128
				ref_supplier: 'SCT0001',
				price_supplier: '8',
				qty_supplier: 1,
				supplier_id: 1,
				vat_supplier: 0,
				price_base_type_supplier: 'HT',
				has_photo: 0,
				photo: null,
				price: 10,
				price_base_type: 'HT',
				tva_tx: 20,
				multiprices_index: priceIndex,
				desiredstock: 20,
				unit_id: 0
			};
			for (i=0;i<3;i++) {
				switch (i) {
					case 1:
						productData.ref = 'CT0002';
						productData.ref_supplier= 'SCT0002';
						productData.barcode = '123456';
						productData.has_photo = 1;
						productData.photo = "data: image\/jpeg;base64,\/9j\/4AAQSkZJRgABAQAAAQABAAD\/\/gA7Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2ODApLCBxdWFsaXR5ID0gODAK\/9sAQwAGBAUGBQQGBgUGBwcGCAoQCgoJCQoUDg8MEBcUGBgXFBYWGh0lHxobIxwWFiAsICMmJykqKRkfLTAtKDAlKCko\/9sAQwEHBwcKCAoTCgoTKBoWGigoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgo\/8AAEQgAlgCWAwEiAAIRAQMRAf\/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC\/\/EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29\/j5+v\/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC\/\/EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp6vLz9PX29\/j5+v\/aAAwDAQACEQMRAD8A+qaKKKACjNFIaAFzRSUooAKKDQKAEPSmb13bc8+lV9Sl8m3DFWYb1BCjJ6ivmf8AaA8U6lYeP7aDTZbe3dYFKpcyTQM7HptdGUfmRWEq3LNQKULq59RilzUFq7vBE0i7ZGQFl9DjpU1bki0ZpKKAFzRSUooAKKKKACiiigAooooAKQ0tNPWgB1FItLQAUUUUAU9SbFuPeRB\/48K+RP2rJFX4jWLHAbCBT6AAZ\/8AQhX1rrkhjtYyNvMyD5jgDnrXx5+1hdJL4+tmiMc2yAHdGcgdPTPpXBUTddL+tmax0i2faMR3Ip9qfiq2myedZW8o6PGrfmKtV3IyYmKUUUUwA0CiigAooooAKKKKACiiigANNpxph60xDlpaRaWkMKKKKAOA+L6xzaLpltNGkkcl\/HlXGQcKx6fhXhXiTRNNNzdkWFqDk9Il\/wAK9w+K7F5\/D0A6tdO+PZYz\/jXlGuRf6ZcqR\/Ea+MzvEThi7RbVkup6WFinHVH0H4Pk83wro7+tpF\/6AK2K5n4by+d4H0Vs9LcL+XH9K6Wvr6MuanGXdI8+StJoXNGaSlFaEhRSZpRQAUUUUAFFFFABRRRQAU3FOpByaBAKWjFFAwooooA838cSi58aafADkWlq7ke7kD+S159rEYk1G5\/3zXY3c32vxlrdxnKoyQL9FXn9TXJ3\/N7cH\/bP86\/Os4r+0ryku7\/DT\/M9bDRtFHpXwfuN3hhrQnm1ndR9GO8fqx\/Ku5ryr4S3Yh1m+tCflniWQfVTg\/8AoX6V6rX2eT1vbYOEvK33HBiY8tRoXNGaSivSMBc0UYooAKKKKACiiigAooooAKQdaWkHWgQtFFFAwNRXEixRSSN0RSxqU1g+NLr7J4bvpAcEpsH1PFZVqnsoSm+ib+4cY8zsebeH2MsFzcufmnmd\/rk1hXHzXEp9WJ\/Wug04eRpkQXYHIyAzAVhzqoncAnOSTyDX5XXk5xTPcpqzZP4TvP7P8X6ZKThHkMTfRhj+ZFe7AV85Xu+JRMgIaMhwfcHNfQum3K3lhb3KHKyxq4\/EV9jwxV5qEqb6P80cGOjaakWzQKDSV9QcIYpRRRQAUUUUAFFFFABRRRQAUUUUAFFFFAAa4r4pztFoMSD7skwB\/Dmu1NcP8V0L6Hb47TD+Vedm3+51fRm1D+JH1OATWHihCeUhwMBhwazVkYnnB+tQy5XIPaokfHWvzKzase4kkT3h3IRxj2r2j4dytL4M00sckRlc+wJxXhl1N8pr234Yn\/iidO\/3T\/M19Rwumqs\/T9Thx\/wxOqpaSlFfbHliClpKUUAFFFFABRRRQAUUUUAFFFFABRRRQAGsLxNp8WpRW1vcbvLaQk7Tg8KTW6az9SIWS3ZjgBzkn\/dNc+JhGpTcJq6f+aKi2ndHEXngrTwCRPcD8Qf6Vz974WtYSSJ5yPoP8K9EvsfOdpPToOvb+tcvqBmwQUVhgjIB7815E8qwielNfj\/mdCxNTuc\/D4Vsrn5WluMdD0H9K9P8IWcen6HFaQFjFESq7jk46\/1rjrFf36AoxIP3tuB0rutC\/wCPNv8Af\/oK9DB4SjQV6cUjGpVnN+8zTooorvMwooooAKKKKACiiigAooooAKKKKACiiigANcJ8Y9Yu9A8D3eqaeU+02zxsoddykb1DAj0IJH413ZrgvjZY3Gp\/D7UbGz8vzpwqqXbA4YH+lYYiSjTcm7JGlPWaRyPhf41eFtetkS\/ul0i\/Iw8F0Tsz32yY2kfXB9qxNR+IrvrSz2+nxyaegIDlyJGH970GOeP1FeJXWi+KdGsRDd6XA0EfSRpon45\/vE46+grIczup2G2WdxuKfZ4SFcds7cYI\/AVxqUcQlKnNW8rM6nCFPpe59Ha\/8V\/CugWzPJqcd7cFcpbWvzuxPYkcL75Irv8A4Ka\/c+J\/h9aazfKqTXU9w2xTkKomdVX3wABnvivjay8O+KdTgeG20y2jjlGwyfaokwMY6Kwzx7GvsT4F2d1p3w40+wv3ie6ty6OY2BAG4lRnA6KQPwrpp16Tn7OMk32OaUbK56CKWkFLXUZBRRR3oAKKKKACiiigAooooAKYCS1PooAKKKKAA1z\/AI40SXxB4avdPtZhBcyp+6lJICt2Jx2roKMVM4qacZK6Y07Hw\/4h+A\/xIjdgmmW2qDeT5kV7GOuOcOVPaueHwN+JO4A+FpuP+nmDB\/8AH6\/QDAoxihRSVkLzPiLw5+z\/APESaZTcWFlpiHgme7RuPpGWr63+HnhpvCnhSy0qacXM8S\/vZgCPMc9Tz+X0FdNiinZbgGKMUtFMAo70maUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAIKDRRQAUooooAQUGiigBTQKKKACiiigAooooA\/\/2Q==";
						productData.unit_id = 6; // kg
						break;
						
					case 2:
						productData.ref = 'CT0003';
						productData.ref_supplier= 'SCT0003';
						productData.barcode = '1234567';
						productData.has_photo = 0;
						productData.photo = null;
						//productData.productinfo = 'connectortest';
						if (dolibarrVersion >= 3.7) {
							productData.sellby = sellby;
							productData.eatby = eatby;
							productData.batch = 'batch1';
							productData.has_batch = 1;
						}						
						productData.correct_stock_nbpiece = 5;	
						break;
	
					default:
						break;
				}
				products[i] = Ext.create('ConnectorTest.model.Product',productData);
			}
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
						//testresults[3] = record.get('productinfo');
					});
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
		var photo = '';
		
		runs(function() {
			var i=0;				
			
			flag = false;
			Ext.getStore('productlist').clearFilter();
			Ext.getStore('productlist').filter([Ext.create('Ext.util.Filter',{property:"warehouse_id",value:warehouseIds[1]}),
			                                    Ext.create('Ext.util.Filter',{property:"status",value:1}),
			                                    Ext.create('Ext.util.Filter',{property:"status_buy",value:1}),
			                                    Ext.create('Ext.util.Filter',{property:"finished",value:1}),
												Ext.create('Ext.util.Filter',{property:"supplier_id",value:0}), // add supplier info to list
			                                    Ext.create('Ext.util.Filter',{property:"photo_size",value:'mini'})]);
			Ext.getStore('productlist').load({
				callback: function(records) {
					Ext.Array.each(records, function (record,index) {
						testresults[index] = record.get('label');
						if (record.get('label') == 'connectortest') {
							productIds[i] = record.get('product_id');
							productBarcodes[i] = record.get('barcode');
							productRefs[i] = record.get('ref');
							supplierRefs[i++] = record.get('ref_supplier') 
							if (record.get('has_photo')) {
								photo = record.get('photo');
							}							
						}
					});
					flag = true;
				}
    		}); 
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT*2);
		
		runs(function () {
			expect(testresults).toContain('connectortest');	
			expect(photo).toMatch('jpeg');
			expect(supplierRefs).toContain('SCT0001');
		});
	});
	
	it("read product 1 by Id", function() {
	
		runs(function() {
			flag = false;
			productStore.clearFilter();
			productStore.filter([Ext.create('Ext.util.Filter',{property:"warehouse_id",value:warehouseIds[1]}),
											Ext.create('Ext.util.Filter',{property:"multiprices_index",value:priceIndex}),
											Ext.create('Ext.util.Filter',{property:"id",value:productIds[0]})]);
			productStore.load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresults.push(record.get('ref'));
						testresults.push(record.get('ref_supplier'));
						testresults.push(record.get('price_ttc'))
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults).toContain('CT0001');
			expect(testresults).toContain('SCT0001');
			expect(testresults).toContain(12);
		});
	});

	it("read product 1 attributes", function() {
		var optionalStore = Ext.getStore('ProductOptionals');

		runs(function() {
			flag = false;
			optionalStore.clearFilter();
			optionalStore.filter([Ext.create('Ext.util.Filter',{property:"id",value:productIds[0]})]);
			optionalStore.load({
				callback: function () {
					Ext.Array.each(optionalModel,function (optional) {
						testresult = optionalStore.findRecord('name',optional.name);
					});
					flag = true;
				}
			});
		});

		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresult.get('value')).toBe('test');
		});
	});
	
	it("update product 1", function() {
		var recordIndex = productStore.find('ref','CT0001'),
			record = productStore.getAt(recordIndex);
		
		runs(function() {
			flag = false;
			record.set('label','connectortested');
			record.set('correct_stock_nbpiece',5);
			record.set('correct_stock_movement',1);	
			record.set('correct_stock_label','move');
			record.set('correct_stock_price','15');
			record.set('correct_stock_dest_warehouseid',warehouseIds[2]);
			record.set('price', 20);
			productStore.sync();
			productStore.clearFilter();
			productStore.filter([Ext.create('Ext.util.Filter',{property:"warehouse_id",value:warehouseIds[1]}),
			                     Ext.create('Ext.util.Filter',{property:"multiprices_index",value:priceIndex}),
			                     Ext.create('Ext.util.Filter',{property:"id",value:productIds[0]})]);
			productStore.load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresults.push(record.get('label'));
						testresults.push(record.get('stock_reel'));
						testresults.push(record.get('pmp'));
						testresults.push(record.get('price'));
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(recordIndex).toBe(0);
			expect(testresults).toContain('connectortested');
			expect(testresults).toContain(5);//stock
			expect(testresults).toContain(20);
			if (dolibarrVersion >= 3.8) {
				expect(testresults).toContain(12.5);//3.8 has pmp calculated in product table
			} else {
				expect(testresults).toContain(10);//pmp
			}	
		});
	});
	
	
	
	it("read product 2 by ref", function() {
		var photo = '', unitId;
		
		runs(function() {
			flag = false;
			productStore.clearFilter();
			productStore.filter([Ext.create('Ext.util.Filter',{property:"warehouse_id",value:warehouseIds[1]}),
			                     Ext.create('Ext.util.Filter',{property:"multiprices_index",value:priceIndex}),
			                     Ext.create('Ext.util.Filter',{property:"ref",value:productRefs[1]}),
                                 Ext.create('Ext.util.Filter',{property:"photo_size",value:'small'})]);
			productStore.load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresult = record.get('ref');
						unitId = record.get('unit_id');
						if (record.get('has_photo')) {
							photo = record.get('photo');
						}
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresult).toBe('CT0002');
			expect(photo).toMatch('jpeg');
			if (dolibarrVersion >= 3.8) {
				expect(unitId).toMatch(6);
			}
		});
	});
	
	it("read product 3 by barcode", function() {
		
		runs(function() {
			flag = false;
			productStore.clearFilter();
			productStore.filter([Ext.create('Ext.util.Filter',{property:"warehouse_id",value:warehouseIds[1]}),
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
	
	it("update product 3 and add new batch", function() {		
		var recordIndex = Ext.getStore('product').find('ref','CT0003'),
			record = productStore.getAt(recordIndex);
		
		if (dolibarrVersion >= 3.7) {
			runs(function() {
				flag = false;
				record.set('label','connectortested');
				record.set('correct_stock_nbpiece',5);
				record.set('correct_stock_movement',0);	
				record.set('correct_stock_label','batch');
				record.set('correct_stock_price','15');
				record.set('sellby',sellby);
				record.set('eatby',eatby);
				record.set('batch','batch2');		
				record.set('batch_info','batch2 info');
				productStore.sync();
				productStore.clearFilter();
				productStore.filter([Ext.create('Ext.util.Filter',{property:"warehouse_id",value:warehouseIds[1]}),
				                     Ext.create('Ext.util.Filter',{property:"multiprices_index",value:priceIndex}),
	                                 Ext.create('Ext.util.Filter',{property:"batch",value:'batch2'}),
				                     Ext.create('Ext.util.Filter',{property:"ref",value:'CT0003'})]);
				productStore.load({
					callback: function (records) {
						Ext.Array.each(records,function (record) {
							testresults.push(record.get('label'));
							testresults.push(record.get('stock_reel'));
							testresults.push(record.get('pmp'));
							testresults.push(record.get('desiredstock'));
						});
						flag = true;
					}
				});
			});
			waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
			
			runs(function () {
				expect(recordIndex).toBe(0);
				expect(testresults).toContain('connectortested');
				expect(testresults).toContain(5);//stock
				expect(testresults).toContain(12.5);//pmp 50 + 75 / 10
				if (dolibarrVersion >= 5.0) expect(testresults).toContain(20);// desiredstock
			});
		}		
	});
	
	it("move batch 2 from product 3", function() {		
		var recordIndex = Ext.getStore('product').find('ref','CT0003'),
			record = productStore.getAt(recordIndex);
		
		if (dolibarrVersion >= 3.7) {
			runs(function() {
				flag = false;
				record.set('label','movetested');
				record.set('correct_stock_nbpiece',2);
				record.set('correct_stock_movement',1);	
				record.set('correct_stock_label','move');
				record.set('correct_stock_price','15');
				record.set('correct_stock_dest_warehouseid',warehouseIds[2]);
				record.set('batch','batch2');		
				productStore.sync();
				productStore.clearFilter();
				productStore.filter([Ext.create('Ext.util.Filter',{property:"warehouse_id",value:warehouseIds[2]}),
				                     Ext.create('Ext.util.Filter',{property:"multiprices_index",value:priceIndex}),
	                                 Ext.create('Ext.util.Filter',{property:"batch",value:'batch2'}),
				                     Ext.create('Ext.util.Filter',{property:"ref",value:'CT0003'})]);
				productStore.load({
					callback: function (records) {
						Ext.Array.each(records,function (record) {
							testresults.push(record.get('label'));
							testresults.push(record.get('stock_reel'));
							testresults.push(record.get('pmp'));
						});
						flag = true;
					}
				});
			});
			waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
			
			runs(function () {
				expect(recordIndex).toBe(0);
				expect(testresults).toContain('movetested');
				expect(testresults).toContain(2);//stock
				if (dolibarrVersion >= 3.8) {
					expect(testresults).toContain(13);//3.8 has pmp calculated in product table TODO check where 13 comes from
				} else {
					expect(testresults).toContain(15);//pmp
				}	
			});
		}		
	});
	
	it("correct batch 2 qty from product 3", function() {		
		var recordIndex = Ext.getStore('product').find('ref','CT0003'),
			record = productStore.getAt(recordIndex);
		
		if (dolibarrVersion >= 3.7) {
			runs(function() {
				flag = false;
				record.set('label','correcttested');
				record.set('correct_stock_nbpiece',3);
				record.set('correct_stock_movement',0);	
				record.set('correct_stock_label','correct');
				record.set('correct_stock_price','15');
				record.set('batch','batch2');		
				productStore.sync();
				productStore.clearFilter();
				productStore.filter([Ext.create('Ext.util.Filter',{property:"warehouse_id",value:warehouseIds[2]}),
				                     Ext.create('Ext.util.Filter',{property:"multiprices_index",value:priceIndex}),
	                                 Ext.create('Ext.util.Filter',{property:"batch",value:'batch2'}),
				                     Ext.create('Ext.util.Filter',{property:"ref",value:'CT0003'})]);
				productStore.load({
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
				expect(recordIndex).toBe(0);
				expect(testresults).toContain('correcttested');
				expect(testresults).toContain(5);
			});
		}		
	});
	
	it("read productbatchlist for product 3", function() {
		if (dolibarrVersion >= 3.7) {
			runs(function() {
				var recordIndex = productStore.find('ref','CT0003');
				
				flag = false;
				Ext.getStore('productbatchlist').clearFilter();
				Ext.getStore('productbatchlist').filter([Ext.create('Ext.util.Filter',{property:"warehouse_id",value:warehouseIds[1]}),
				                                    Ext.create('Ext.util.Filter',{property:"product_id",value:productStore.getAt(recordIndex).getId()})]);
				Ext.getStore('productbatchlist').load({
					callback: function(records) {
						Ext.Array.each(records, function (record,index) {
							testresults[index] = record.get('batch');
						});
						flag = true;
					}
				}); 
			});
			
			waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
			
			runs(function () {
				expect(testresults).toContain('batch1');	
				expect(testresults).toContain('batch2');
			});
		}
	});
});

describe("order", function () {
	var flag = false,			
		testresults = [],
		testresult = null,
		orderRef = null,
		orderstatusIds = [],
		orderLineIds = [],
        orderLineBatchIds = [];
		
	beforeEach(function() {
		testresults = [];
		testresult = null;
	});
	
	it("read orderconstants", function() {
		
		runs(function() {
			flag = false;
			Ext.getStore('OrderConstants').load({
				callback: function(records) {
					Ext.Array.each(records, function (record,index) {
						testresults[index] = record.get('constant');
					});
					flag = true;
				}
    		}); 
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults).toContain('STOCK_MUST_BE_ENOUGH_FOR_ORDER');
		});
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
			if (dolibarrVersion >= 3.7)	{
				order.set('shipping_method_id', 1);
			}
			if (dolibarrVersion >= 3.8)	{
				order.set('incoterms_id', 2);
				order.set('location_incoterms', 'location incoterms')
			}
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
			// add 3 products
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
						testresults.push(record.get('ref'));
						if (dolibarrVersion >= 3.7)	{
							testresults.push(record.get('shipping_method_id'));
						}
						if (dolibarrVersion >= 3.8)	{
							testresults.push(record.get('incoterms_id'));
							testresults.push(record.get('location_incoterms'));
						}
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults).toContain(orderRef);
			if (dolibarrVersion >= 3.7)	{
				expect(testresults).toContain(1);
			}
			if (dolibarrVersion >= 3.8)	{
				expect(testresults).toContain(2);
				expect(testresults).toContain('location incoterms');
			}
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
			asked=0,
			photo='',
			unitIds = [];
		
		runs(function() {
			flag = false;
			Ext.getStore('orderline').clearFilter();
			Ext.getStore('orderline').filter([Ext.create('Ext.util.Filter',{property:"order_id",value:orderId}),
			                                  Ext.create('Ext.util.Filter',{property:"photo_size",value:'mini'})]);
			Ext.getStore('orderline').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresults.push(record.get('warehouse_id'));
						stock+=record.get('stock');
						asked+=record.get('qty_asked');
                        if (record.get('batch_id')) {
                            orderLineBatchIds.push(record.get('batch_id'));
                        }
                        if (record.get('has_photo')) {
                        	photo=record.get('photo');
						}
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults).toContain(warehouseIds[1]);
			expect(testresults).toContain(warehouseIds[2]);
			if (dolibarrVersion >= 3.7) {
				expect(testresults.length).toBe(6);
				expect(stock).toBe(33);
				expect(asked).toBe(12); // 6 * 2 asked
			} else {
				expect(testresults.length).toBe(4);
				expect(stock).toBe(25);
				expect(asked).toBe(8); // 4 * 2 asked
			}		
			expect(photo).toMatch('jpeg');
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
			if (dolibarrVersion >= 3.7) {
				expect(testresult).toBe(16); //4 * 2 asked + 2 * 4 asked
			} else {
				expect(testresult).toBe(12); //2 * 2 asked + 2 * 4 asked
			}			
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
						stock+=record.get('stock');
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults).toContain(warehouseIds[1]);
			expect(testresults).not.toContain(warehouseIds[2]);
			
			if (dolibarrVersion >= 3.7) {
				expect(testresults.length).toBe(4);
				expect(stock).toBe(23);
			} else {
				expect(testresults.length).toBe(3);
				expect(stock).toBe(20);
			}
		});
	});
});

describe("shipment", function ()
{
    var flag = false,
		testresults = [],
		testresult = null,
		shipmentRef = null,
		shipmentLineIds = [],
		orderstatusIds = [],
        sellbys = [],
        batches = [];

    beforeEach(function ()
    {
        testresults = [];
        testresult = null;
    });
    
    it("read shipmentstatuslist", function() {
		
		runs(function() {
			var i=0;
			
			flag = false;
			Ext.getStore('ShipmentStatus').load({
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
			expect(testresults).toContain(-0);	
			expect(testresults).toContain(1);	
			expect(testresults).toContain(2);
		});
	});

    it("create shipment", function ()
    {
        runs(function ()
        {
            var shipmentData, shipment;

            flag = false;
            shipmentData = {
                ref_int: 'CT0001',
                origin: 'commande',
                origin_id: orderId,
                ref_customer: 'connectortest',
                customer_id: customerId,
                weight_units: 0,
                weight: 10,
                size_units: 0,
                trueDepth: .2,
                trueWidth: .2,
                trueHeight: .2,
                note_private: 'connectortest private',
                note_public: 'connectortest public',
                tracking_number: 'connectortest tracking',
                deliver_date: Ext.Date.format(new Date(), 'U')
            };
            shipment = Ext.create('ConnectorTest.model.Order', shipmentData);
            if (dolibarrVersion >= 3.7)	{
            	shipment.set('shipping_method_id', 1);
			}
			if (dolibarrVersion >= 3.8)	{
				shipment.set('incoterms_id', 2);
				shipment.set('location_incoterms', 'location incoterms')
			}
            Ext.getStore('shipment').add(shipment);
            Ext.getStore('shipment').sync();
            Ext.getStore('shipment').clearFilter();
            Ext.getStore('shipment').filter([Ext.create('Ext.util.Filter', { property: "ref_int", value: 'CT0001' })]);
            Ext.getStore('shipment').load({
                callback: function (records)
                {
                    Ext.Array.each(records, function (record)
                    {
                        testresult = record.get('ref_customer');
                        shipmentId = record.get('id');
                        shipmentRef = record.get('ref');
                    });
                    flag = true;
                }
            });
        });

        waitsFor(function () { return flag; }, "extdirect timeout", TIMEOUT);

        runs(function ()
        {
            expect(testresult).toBe('connectortest');
        });
    });
    
    it("read shipmentlist", function() {
	
		runs(function() {
			flag = false;
			Ext.getStore('ShipmentList').clearFilter();
			Ext.getStore('ShipmentList').filter([Ext.create('Ext.util.Filter',{property:"orderstatus_id",value:orderstatusIds[0]}),
				Ext.create('Ext.util.Filter',{property:"origin_id",value:orderId})]);
			Ext.getStore('ShipmentList').load({
				callback: function(records) {
					Ext.Array.each(records, function (record,index) {
						testresult = records.length;
						testresults[index] = record.get('ref');
						if (record.get('ref_int') == 'CT0001') {
							shipmentRef = record.get('ref');
							shipmentId = record.getId();
						}
					});
					flag = true;
				}
    		}); 
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults).toContain(shipmentRef);
			expect(testresult).toBe(1);
		});
	});

    it("create shipmentlines", function ()
    {
        runs(function ()
        {
            // create shipment lines from orderlines
            var shipmentData, shipmentLine, shipmentLines = [],
                shipmentLineStore = Ext.getStore('shipmentline'),
                orderLineStore = Ext.getStore('orderline');

            flag = false;
            orderLineStore.each(function (orderLine)
            {
                shipmentData = {
                    origin_id: shipmentId,
                    warehouse_id: orderLine.get('warehouse_id'),
                    origin_line_id: orderLine.get('origin_line_id'),
                    sellby: orderLine.get('sellby'),
                    eatby: orderLine.get('eatby'),
                    batch: orderLine.get('batch'),
                    batch_id: orderLine.get('batch_id'),
                    qty_toship: 2
                };
                if (orderLine.get('batch_id') > 0)
                {
                    // ship 1 of each batch
                    shipmentData.qty_toship = 1;
                }
                shipmentLine = Ext.create('ConnectorTest.model.OrderLine', shipmentData);
                shipmentLines.push(shipmentLine);
            });
            shipmentLineStore.add(shipmentLines);
            shipmentLineStore.sync();
            shipmentLineStore.clearFilter();
            shipmentLineStore.filter([Ext.create('Ext.util.Filter', { property: "origin_id", value: shipmentId })]);
            shipmentLineStore.load({
                callback: function (records)
                {
                    Ext.Array.each(records, function (record, index)
                    {
                        testresults[index] = record.get('description');
                        shipmentLineIds[index] = record.get('line_id');
                        sellbys[index] = record.get('sellby');
                        batches[index] = record.get('batch');
                    });
                    flag = true;
                }
            });
        });

        waitsFor(function () { return flag; }, "extdirect timeout", TIMEOUT);

        runs(function ()
        {
            Ext.Array.each(testresults, function (testresult, index)
            {
                if (index == 0)
                {
                    expect(testresult).toBe('connectortest update');
                } else
                {
                    expect(testresult).toBe('connectortest');
                }
                if (index == 3)
                {
                	expect(Ext.Date.format(sellbys[index],'U')).toBe(sellby);
                	expect(batches[index]).toBe('batch2');
                }                
            });
        });
    });

    it("read shipment by Id", function ()
    {
        runs(function ()
        {
            flag = false;
            Ext.getStore('shipment').clearFilter();
            Ext.getStore('shipment').filter([Ext.create('Ext.util.Filter', { property: "id", value: shipmentId })]);
            Ext.getStore('shipment').load({
                callback: function (records)
                {
                    Ext.Array.each(records, function (record)
                    {
                    	testresults.push(record.get('ref'));
                    	if (dolibarrVersion >= 3.4)	{
							testresults.push(record.get('note_public'));
							testresults.push(record.get('note_private'));
							testresults.push(record.get('tracking_number'));
						}
                    	if (dolibarrVersion >= 3.7)	{
							testresults.push(record.get('shipping_method_id'));
						}
						if (dolibarrVersion >= 3.8)	{
							testresults.push(record.get('incoterms_id'));
							testresults.push(record.get('location_incoterms'));
						}
                    });
                    flag = true;
                }
            });
        });

        waitsFor(function () { return flag; }, "extdirect timeout", TIMEOUT);

        runs(function ()
        {
            expect(testresults).toContain(shipmentRef);
            if (dolibarrVersion >= 3.4)	{
            	expect(testresults).toContain('connectortest public');
            	expect(testresults).toContain('connectortest private');
            	expect(testresults).toContain('connectortest tracking');
            }            
			if (dolibarrVersion >= 3.7)	{
				expect(testresults).toContain(1);
			}
			if (dolibarrVersion >= 3.8)	{
				expect(testresults).toContain(2);
				expect(testresults).toContain('location incoterms');
			}
        });
    });
    
    it("update shipmentline", function() {
		var updateRecord = null;
		
		runs(function() {
			flag = false;
			updateRecord = Ext.getStore('shipmentline').findRecord('line_id',shipmentLineIds[0]);
			updateRecord.set('qty_toship',1);
			Ext.getStore('shipmentline').sync();
			Ext.getStore('shipmentline').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresult+=record.get('qty_shipped');
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresult).toBe(5);
		});
	});
    
	it("update shipmentline remove batch1", function() {
		var updateRecord = null;
		
		runs(function() {
			flag = false;
			updateRecord = Ext.getStore('shipmentline').findRecord('line_id',shipmentLineIds[2]);
			updateRecord.set('qty_toship',0);
			updateRecord.set('batch','batch1');
			Ext.getStore('shipmentline').sync();
			Ext.getStore('shipmentline').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresult+=record.get('qty_shipped');
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresult).toBe(4);
		});
	});
	
	it("update shipmentline update batch2 qty", function() {
		var updateRecord = null;
		
		runs(function() {
			flag = false;
			updateRecord = Ext.getStore('shipmentline').findRecord('line_id',shipmentLineIds[3]);
			updateRecord.set('qty_toship',2);
			updateRecord.set('batch','batch2');
			Ext.getStore('shipmentline').sync();
			Ext.getStore('shipmentline').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresult+=record.get('qty_shipped');
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresult).toBe(5);
		});
	});
    
    it("destroy shipmentLine", function() {
		if (dolibarrVersion >= 3.6) {	
			Ext.getStore('shipmentline').setDestroyRemovedRecords(true);
			Ext.getStore('shipmentline').setSyncRemovedRecords(true);
			runs(function() {
				flag = false;
				Ext.getStore('shipmentline').clearFilter();
				Ext.getStore('shipmentline').filter([Ext.create('Ext.util.Filter',{property:"origin_id",value:shipmentId})]);
				Ext.getStore('shipmentline').load({
					callback: function (records) {
						Ext.getStore('shipmentline').remove(records[0]); // remove first line
						Ext.getStore('shipmentline').sync();
						Ext.getStore('shipmentline').load({
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
				expect(testresult).toBe(2);
			});
		}
	});
    
	
    it("update shipment", function ()
    {
        var record = Ext.getStore('shipment').find('ref', shipmentRef);

        runs(function ()
        {
            flag = false;
            Ext.getStore('shipment').getAt(record).set('orderstatus_id', orderstatusIds[1]);
            Ext.getStore('shipment').sync();
            Ext.getStore('shipment').load({
                callback: function (records)
                {
                    Ext.Array.each(records, function (record)
                    {
                        testresult = record.get('orderstatus_id');
                        shipmentRef = record.get('ref');
                    });
                    flag = true;
                }
            });
        });

        waitsFor(function () { return flag; }, "extdirect timeout", TIMEOUT);

        runs(function ()
        {
            expect(record).toBe(0);
            expect(testresult).toBe(orderstatusIds[1]);
        });
    });

    it("read shipment by ref", function ()
    {

        runs(function ()
        {
            flag = false;
            Ext.getStore('shipment').clearFilter();
            Ext.getStore('shipment').filter([Ext.create('Ext.util.Filter', { property: "ref", value: shipmentRef })]);
            Ext.getStore('shipment').load({
                callback: function (records)
                {
                    Ext.Array.each(records, function (record)
                    {
                        testresult = record.get('ref');
                    });
                    flag = true;
                }
            });
        });

        waitsFor(function () { return flag; }, "extdirect timeout", TIMEOUT);

        runs(function ()
        {
            expect(testresult).toBe(shipmentRef);
        });
    });
    
    it("check stock after shipment validation", function ()
    {
        if (dolibarrVersion >= 3.7)
        {
            var productStore = Ext.getStore('product'),
                productStock = 0,
                batchStock = 0,
				productId;

            runs(function ()
            {
                flag = false;
                productStore.clearFilter();
                productStore.filter([Ext.create('Ext.util.Filter', { property: "warehouse_id", value: warehouseIds[1] }),
				                     Ext.create('Ext.util.Filter', { property: "ref", value: 'CT0003' })]);
                productStore.load({
                    callback: function (records)
                    {
                        Ext.Array.each(records, function (record)
                        {
                            productStock = record.get('stock_reel');
                            productId = record.getId();
                        });
                        Ext.getStore('productbatchlist').clearFilter();
                        Ext.getStore('productbatchlist').filter([Ext.create('Ext.util.Filter', { property: "warehouse_id", value: warehouseIds[1] }),
				                                            Ext.create('Ext.util.Filter', { property: "product_id", value: productId })]);
                        Ext.getStore('productbatchlist').load({
                            callback: function (records)
                            {
                                Ext.Array.each(records, function (record, index)
                                {
                                    testresults[index] = record.get('batch');
                                    batchStock += record.get('stock_reel');
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
                expect(testresults).toContain('batch1');
                expect(testresults).toContain('batch2');
                expect(batchStock).toBe(productStock);
            });
        }
    });

    it("read shipmentline by origin Id", function ()
    {
        var shipped = 0,
			asked = 0;

        runs(function ()
        {
            flag = false;
            Ext.getStore('shipmentline').clearFilter();
            Ext.getStore('shipmentline').filter([Ext.create('Ext.util.Filter', { property: "origin_id", value: shipmentId })]);
            Ext.getStore('shipmentline').load({
                callback: function (records)
                {
                    Ext.Array.each(records, function (record)
                    {
                        testresults.push(record.get('warehouse_id'));
                        shipped += record.get('qty_shipped');
                        asked += record.get('qty_asked');
                        if (record.get('batch_id') > 0)
                        {
                            testresult = record.get('batch');
                        }
                    });
                    flag = true;
                }
            });
        });

        waitsFor(function () { return flag; }, "extdirect timeout", TIMEOUT);

        runs(function ()
        {
            expect(testresults).toContain(warehouseIds[1]);
            expect(shipped).toBe(4);
            expect(testresults.length).toBe(2);
            expect(asked).toBe(4);
            if (dolibarrVersion >= 3.7) {
                expect(testresult).toBe('batch2');
            } else {
                expect(testresult).toBe(null);
            }           
        });
    });
});

describe("Purchase Order", function () {
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
	
	it("read PurchaseConstants", function() {
		
		runs(function() {
			flag = false;
			Ext.getStore('PurchaseConstants').load({
				callback: function(records) {
					Ext.Array.each(records, function (record,index) {
						testresults[index] = record.get('constant');
					});
					flag = true;
				}
    		}); 
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults).toContain('STOCK_CALCULATE_ON_SUPPLIER_VALIDATE_ORDER');
		});
	});

	it("read orderstatuslist", function() {
		
		runs(function() {
			var i=0;
			
			flag = false;
			Ext.getStore('PurchaseOrderStatus').load({
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
			expect(testresults).toContain(0);	
			expect(testresults).toContain(1);	
			expect(testresults).toContain(2);	
			expect(testresults).toContain(3);	
			expect(testresults).toContain(4);
			expect(testresults).toContain(5);	
			expect(testresults).toContain(6);	
			expect(testresults).toContain(9);	
		});
	});
	
	it("read ContactLinkTypeList", function() {
		
		runs(function() {
			flag = false;
			Ext.getStore('ContactLinkTypeList').load({
				callback: function(records) {
					Ext.Array.each(records, function (record,index) {
						testresults[index] = record.getId();
						flag = true;
					});
				}
    		}); 
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults).toContain(140);	
			expect(testresults).toContain(141);			
		});
	});
		
	it("create order", function() {
		runs(function() {
			// add 2 products
			var orderData,order,orderStore;

			flag = false;
			orderData = {
				note_private: 'connectortest private',
				note_public: 'connectortest public',
				ref_supplier: 'connectortest',
				supplier_id: customerId,
				orderstatus_id: orderstatusIds[0],
				user_id: 1
			};
			order = Ext.create('ConnectorTest.model.Order',orderData);
			orderStore = Ext.getStore('PurchaseOrder');
			orderStore.add(order);					
			orderStore.sync();
			orderStore.load({
				callback: function () {					
					testresults[0] = order.get('ref_supplier');
					testresults[1] = order.get('note_private');
					testresults[2] = order.get('note_public');
					testresults[3] = order.get('supplier_id');
					testresults[4] = order.get('orderstatus_id');
					orderRef = order.get('ref');
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults[0]).toBe('connectortest');
			expect(testresults[1]).toBe('connectortest private');
			expect(testresults[2]).toBe('connectortest public');
			expect(testresults[3]).toBe(customerId);
			expect(testresults[4]).toBe(orderstatusIds[0]);
		});
	});
	
	it("read orderlist", function() {
	
		runs(function() {
			flag = false;
			Ext.getStore('PurchaseOrderList').clearFilter();
			Ext.getStore('PurchaseOrderList').filter([Ext.create('Ext.util.Filter',{property:"orderstatus_id",value:orderstatusIds[0]})]);
			Ext.getStore('PurchaseOrderList').load({
				callback: function(records) {
					Ext.Array.each(records, function (record,index) {
						testresults[index] = record.get('ref');
						if (dolibarrVersion >= 3.4) {
							if (record.get('ref_supplier') == 'connectortest') {
								orderRef = record.get('ref');
								purchaseOrderId = record.getId();
							}
						} else {
							if (record.get('supplier_id') == customerId) {
								orderRef = record.get('ref');
								purchaseOrderId = record.getId();
							}
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
		var orderLines = [];
		
		runs(function() {
			// add 3 products
			var orderData,orderLine;

			flag = false;
			
			orderData = {
				origin_id: purchaseOrderId,
				description: 'connectortest',
				qty_asked: 2,
				product_id: null,
				product_price: 10,
				product_tax: 21
			};
			Ext.Array.each(productIds, function(productId, index){
				Ext.getStore('product').clearFilter();
				Ext.getStore('product').filter([Ext.create('Ext.util.Filter',{property:"id",value:productId})]);
				Ext.getStore('product').load({
					callback: function(records) {
						orderData.product_id = productId;
						orderData.ref_supplier = records[0].get('ref_supplier');
						orderData.ref_supplier_id = records[0].get('ref_supplier_id');
						orderData.unit_id = records[0].get('unit_id');
						orderLine = Ext.create('ConnectorTest.model.OrderLine',orderData);
						orderLines.push(orderLine);
						if (index == (productIds.length -1)) {
							flag = true;
						}						
					}
				});
			});
						
		});
			
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
			
		runs(function() {
			flag = false;
			Ext.getStore('PurchaseOrderLine').add(orderLines);					
			Ext.getStore('PurchaseOrderLine').sync();
			Ext.getStore('PurchaseOrderLine').clearFilter();
			Ext.getStore('PurchaseOrderLine').filter([Ext.create('Ext.util.Filter',{property:"order_id",value:purchaseOrderId})]);
			Ext.getStore('PurchaseOrderLine').load({
				callback: function (records) {
					Ext.Array.each(records,function (record,index) {
						testresults[index] = record.get('description');
						orderLineIds[index] = record.get('origin_line_id');
					});
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
	
	it("update orderline", function() {
		var updateRecord = null;
		
		runs(function() {
			flag = false;
			updateRecord = Ext.getStore('PurchaseOrderLine').findRecord('origin_line_id',orderLineIds[0]);
			updateRecord.set('description','connectortest update');
			updateRecord.set('qty_asked',4);
			updateRecord.set('has_photo', 1);
			updateRecord.set('photo', "data: image\/jpeg;base64,\/9j\/4AAQSkZJRgABAQAAAQABAAD\/\/gA7Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2ODApLCBxdWFsaXR5ID0gODAK\/9sAQwAGBAUGBQQGBgUGBwcGCAoQCgoJCQoUDg8MEBcUGBgXFBYWGh0lHxobIxwWFiAsICMmJykqKRkfLTAtKDAlKCko\/9sAQwEHBwcKCAoTCgoTKBoWGigoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgo\/8AAEQgAlgCWAwEiAAIRAQMRAf\/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC\/\/EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29\/j5+v\/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC\/\/EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp6vLz9PX29\/j5+v\/aAAwDAQACEQMRAD8A+qaKKKACjNFIaAFzRSUooAKKDQKAEPSmb13bc8+lV9Sl8m3DFWYb1BCjJ6ivmf8AaA8U6lYeP7aDTZbe3dYFKpcyTQM7HptdGUfmRWEq3LNQKULq59RilzUFq7vBE0i7ZGQFl9DjpU1bki0ZpKKAFzRSUooAKKKKACiiigAooooAKQ0tNPWgB1FItLQAUUUUAU9SbFuPeRB\/48K+RP2rJFX4jWLHAbCBT6AAZ\/8AQhX1rrkhjtYyNvMyD5jgDnrXx5+1hdJL4+tmiMc2yAHdGcgdPTPpXBUTddL+tmax0i2faMR3Ip9qfiq2myedZW8o6PGrfmKtV3IyYmKUUUUwA0CiigAooooAKKKKACiiigANNpxph60xDlpaRaWkMKKKKAOA+L6xzaLpltNGkkcl\/HlXGQcKx6fhXhXiTRNNNzdkWFqDk9Il\/wAK9w+K7F5\/D0A6tdO+PZYz\/jXlGuRf6ZcqR\/Ea+MzvEThi7RbVkup6WFinHVH0H4Pk83wro7+tpF\/6AK2K5n4by+d4H0Vs9LcL+XH9K6Wvr6MuanGXdI8+StJoXNGaSlFaEhRSZpRQAUUUUAFFFFABRRRQAU3FOpByaBAKWjFFAwooooA838cSi58aafADkWlq7ke7kD+S159rEYk1G5\/3zXY3c32vxlrdxnKoyQL9FXn9TXJ3\/N7cH\/bP86\/Os4r+0ryku7\/DT\/M9bDRtFHpXwfuN3hhrQnm1ndR9GO8fqx\/Ku5ryr4S3Yh1m+tCflniWQfVTg\/8AoX6V6rX2eT1vbYOEvK33HBiY8tRoXNGaSivSMBc0UYooAKKKKACiiigAooooAKQdaWkHWgQtFFFAwNRXEixRSSN0RSxqU1g+NLr7J4bvpAcEpsH1PFZVqnsoSm+ib+4cY8zsebeH2MsFzcufmnmd\/rk1hXHzXEp9WJ\/Wug04eRpkQXYHIyAzAVhzqoncAnOSTyDX5XXk5xTPcpqzZP4TvP7P8X6ZKThHkMTfRhj+ZFe7AV85Xu+JRMgIaMhwfcHNfQum3K3lhb3KHKyxq4\/EV9jwxV5qEqb6P80cGOjaakWzQKDSV9QcIYpRRRQAUUUUAFFFFABRRRQAUUUUAFFFFAAa4r4pztFoMSD7skwB\/Dmu1NcP8V0L6Hb47TD+Vedm3+51fRm1D+JH1OATWHihCeUhwMBhwazVkYnnB+tQy5XIPaokfHWvzKzase4kkT3h3IRxj2r2j4dytL4M00sckRlc+wJxXhl1N8pr234Yn\/iidO\/3T\/M19Rwumqs\/T9Thx\/wxOqpaSlFfbHliClpKUUAFFFFABRRRQAUUUUAFFFFABRRRQAGsLxNp8WpRW1vcbvLaQk7Tg8KTW6az9SIWS3ZjgBzkn\/dNc+JhGpTcJq6f+aKi2ndHEXngrTwCRPcD8Qf6Vz974WtYSSJ5yPoP8K9EvsfOdpPToOvb+tcvqBmwQUVhgjIB7815E8qwielNfj\/mdCxNTuc\/D4Vsrn5WluMdD0H9K9P8IWcen6HFaQFjFESq7jk46\/1rjrFf36AoxIP3tuB0rutC\/wCPNv8Af\/oK9DB4SjQV6cUjGpVnN+8zTooorvMwooooAKKKKACiiigAooooAKKKKACiiigANcJ8Y9Yu9A8D3eqaeU+02zxsoddykb1DAj0IJH413ZrgvjZY3Gp\/D7UbGz8vzpwqqXbA4YH+lYYiSjTcm7JGlPWaRyPhf41eFtetkS\/ul0i\/Iw8F0Tsz32yY2kfXB9qxNR+IrvrSz2+nxyaegIDlyJGH970GOeP1FeJXWi+KdGsRDd6XA0EfSRpon45\/vE46+grIczup2G2WdxuKfZ4SFcds7cYI\/AVxqUcQlKnNW8rM6nCFPpe59Ha\/8V\/CugWzPJqcd7cFcpbWvzuxPYkcL75Irv8A4Ka\/c+J\/h9aazfKqTXU9w2xTkKomdVX3wABnvivjay8O+KdTgeG20y2jjlGwyfaokwMY6Kwzx7GvsT4F2d1p3w40+wv3ie6ty6OY2BAG4lRnA6KQPwrpp16Tn7OMk32OaUbK56CKWkFLXUZBRRR3oAKKKKACiiigAooooAKYCS1PooAKKKKAA1z\/AI40SXxB4avdPtZhBcyp+6lJICt2Jx2roKMVM4qacZK6Y07Hw\/4h+A\/xIjdgmmW2qDeT5kV7GOuOcOVPaueHwN+JO4A+FpuP+nmDB\/8AH6\/QDAoxihRSVkLzPiLw5+z\/APESaZTcWFlpiHgme7RuPpGWr63+HnhpvCnhSy0qacXM8S\/vZgCPMc9Tz+X0FdNiinZbgGKMUtFMAo70maUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAIKDRRQAUooooAQUGiigBTQKKKACiiigAooooA\/\/2Q==");
			Ext.getStore('PurchaseOrderLine').sync();
			Ext.getStore('PurchaseOrderLine').load({
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
			if (dolibarrVersion >= 3.7) {
				expect(testresult).toBe(14); 
			} else {
				expect(testresult).toBe(12);
			}			
		});
	});
	
	it("read order by Id", function() {
	
		runs(function() {
			flag = false;
			Ext.getStore('PurchaseOrder').clearFilter();
			Ext.getStore('PurchaseOrder').filter([Ext.create('Ext.util.Filter',{property:"id",value:purchaseOrderId})]);
			Ext.getStore('PurchaseOrder').load({
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
	
	it("validate order", function() {
		var record = Ext.getStore('PurchaseOrder').find('ref',orderRef);
		
		runs(function() {
			flag = false;
			Ext.getStore('PurchaseOrder').getAt(record).set('orderstatus_id',orderstatusIds[1]);
			Ext.getStore('PurchaseOrder').sync();
			Ext.getStore('PurchaseOrder').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresult = record.get('orderstatus_id');
						orderRef = record.get('ref');
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(record).toBe(0);
			expect(testresult).toBe(orderstatusIds[1]);
		});
	});
	
	it("approve order", function() {
		var record = Ext.getStore('PurchaseOrder').find('ref',orderRef);
		
		runs(function() {
			flag = false;
			Ext.getStore('PurchaseOrder').getAt(record).set('orderstatus_id',orderstatusIds[2]);
			Ext.getStore('PurchaseOrder').sync();
			Ext.getStore('PurchaseOrder').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresult = record.get('orderstatus_id');
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(record).toBe(0);
			expect(testresult).toBe(orderstatusIds[2]);
		});
	});
	
	it("order order", function() {
		var record = Ext.getStore('PurchaseOrder').find('ref',orderRef);
		
		runs(function() {
			flag = false;
			Ext.getStore('PurchaseOrder').getAt(record).set('orderstatus_id',orderstatusIds[3]);
			Ext.getStore('PurchaseOrder').getAt(record).set('comment','ordered');
			Ext.getStore('PurchaseOrder').getAt(record).set('order_date',Ext.Date.format(new Date(),'U'));
			Ext.getStore('PurchaseOrder').sync();
			Ext.getStore('PurchaseOrder').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresult = record.get('orderstatus_id');
					});
					flag = true;
				}
			});
		});		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(record).toBe(0);
			expect(testresult).toBe(orderstatusIds[3]);
		});
	});
	
	it("receive order", function() {
		var record = Ext.getStore('PurchaseOrder').find('ref',orderRef);
		
		runs(function() {
			flag = false;
			Ext.getStore('PurchaseOrder').getAt(record).set('orderstatus_id',orderstatusIds[5]);
			Ext.getStore('PurchaseOrder').getAt(record).set('comment','received');
			Ext.getStore('PurchaseOrder').getAt(record).set('deliver_date',Ext.Date.format(new Date(),'U'));
			Ext.getStore('PurchaseOrder').sync();
			Ext.getStore('PurchaseOrder').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresult = record.get('orderstatus_id');
					});
					flag = true;
				}
			});
		});		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		runs(function () {
			expect(record).toBe(0);
			expect(testresult).toBe(orderstatusIds[5]);
		});
	});
	
	it("read order by ref", function() {
		
		runs(function() {
			flag = false;
			Ext.getStore('PurchaseOrder').clearFilter();
			Ext.getStore('PurchaseOrder').filter([Ext.create('Ext.util.Filter',{property:"ref",value:orderRef})]);
			Ext.getStore('PurchaseOrder').load({
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
			asked=0,
			photo='',
			unitIds = [];
		
		runs(function() {
			flag = false;
			Ext.getStore('PurchaseOrderLine').clearFilter();
			Ext.getStore('PurchaseOrderLine').filter([Ext.create('Ext.util.Filter',{property:"order_id",value:purchaseOrderId}),
			                                          Ext.create('Ext.util.Filter',{property:"photo_size",value:'mini'})]);
			Ext.getStore('PurchaseOrderLine').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresults.push(record.get('warehouse_id'));
						stock+=record.get('stock');
						asked+=record.get('qty_asked');
						if (record.get('has_photo')) {
							photo=record.get('photo');
						}
						unitIds.push(record.get('unit_id'));
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults).toContain(warehouseIds[1]);
			expect(testresults).toContain(warehouseIds[2]);
			if (dolibarrVersion >= 3.7) {
				expect(testresults.length).toBe(5);
				expect(stock).toBe(29);
				expect(asked).toBe(14);
				if (dolibarrVersion >= 3.8) {
					expect(unitIds).toContain(6);
				}
			} else {
				expect(testresults.length).toBe(4);
				expect(stock).toBe(21);
				expect(asked).toBe(12);
			}	
			expect(photo).toMatch('jpeg');
		});
	});
	
	
	
	it("read orderline by Id and warehouse_id", function() {
		var stock=0,
			desiredStock=0;
		
		runs(function() {
			flag = false;
			Ext.getStore('PurchaseOrderLine').clearFilter();
			Ext.getStore('PurchaseOrderLine').filter([Ext.create('Ext.util.Filter',{property:"order_id",value:purchaseOrderId}),
			                                  Ext.create('Ext.util.Filter',{property:"warehouse_id",value:warehouseIds[1]})]);
			Ext.getStore('PurchaseOrderLine').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresults.push(record.get('warehouse_id'));
						stock+=record.get('stock');
						desiredStock+=record.get('desiredstock');
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			expect(testresults).toContain(warehouseIds[1]);
			expect(testresults).not.toContain(warehouseIds[2]);
			expect(testresults.length).toBe(3);
			if (dolibarrVersion >= 3.7) {
				expect(stock).toBe(19);
			} if (dolibarrVersion >= 5.0) {
				expect(stock).toBe(19);
				expect(desiredStock).toBe(60);
			} else {
				expect(stock).toBe(16);
			}
		});
	});
	
	it("dispatch orderline", function() {
		var updateRecord = null;
		
		runs(function() {
			flag = false;
			updateRecord = Ext.getStore('PurchaseOrderLine').findRecord('origin_line_id',orderLineIds[0]);
			updateRecord.set('qty_shipped',2);
			Ext.getStore('PurchaseOrderLine').sync();
			Ext.getStore('PurchaseOrderLine').load({
				callback: function (records) {
					Ext.Array.each(records,function (record) {
						testresult+=record.get('qty_shipped');
					});
					flag = true;
				}
			});
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			if (dolibarrVersion >= 3.7) {
				expect(testresult).toBe(2); 
			} else {
				expect(testresult).toBe(2); 
			}			
		});
	});
});

describe("warehouse stock", function () {
	var flag = false,
		testresults = [],
		stock = [];
		
	it("load warehouse", function() {
		runs(function() {
			flag = false;
			Ext.getStore("warehouse").load({
				callback: function(records) {
					Ext.Array.each(records, function (record,index) {
						testresults[index] = record.get('label');
						warehouseIds[index] = record.getId();
						stock[index] = record.get('stock');
					});
					flag = true;
				}
    		}); 
		});
		
		waitsFor(function() {return flag;},"extdirect timeout",TIMEOUT);
		
		runs(function () {
			Ext.Array.each(testresults, function (testresult) {
				// label must contain 1 or more characters
				expect(testresult).toMatch(/^.*$/);
				expect(stock[1]).toBeGreaterThan(0);
				expect(stock[2]).toBeGreaterThan(0);
			});			
		});
	});
});

describe("delete Purchase orders", function () {
	var flag = false,			
		testresult = null;
		
	beforeEach(function() {
		testresult = null;
	});
	
	it("destroy orderLines", function() {
		Ext.getStore('PurchaseOrderLine').setDestroyRemovedRecords(true);
		Ext.getStore('PurchaseOrderLine').setSyncRemovedRecords(true);
		runs(function() {
			flag = false;
			Ext.getStore('PurchaseOrderLine').clearFilter();
			Ext.getStore('PurchaseOrderLine').filter([Ext.create('Ext.util.Filter',{property:"order_id",value:purchaseOrderId}), Ext.create('Ext.util.Filter',{property:"warehouse_id",value:-1})]);
			Ext.getStore('PurchaseOrderLine').load({
				callback: function (records) {
					Ext.getStore('PurchaseOrderLine').remove(records);
					Ext.getStore('PurchaseOrderLine').sync();
					Ext.getStore('PurchaseOrderLine').load({
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
	
	it("destroy order", function() {
		var record = Ext.getStore('PurchaseOrder').find('id',purchaseOrderId);
		
		Ext.getStore('PurchaseOrder').setDestroyRemovedRecords(true);
		Ext.getStore('PurchaseOrder').setSyncRemovedRecords(true);
		runs(function() {
			flag = false;
			Ext.getStore('PurchaseOrder').removeAt(record);
			Ext.getStore('PurchaseOrder').sync();
			Ext.getStore('PurchaseOrder').load({
				callback: function () {
					testresult = Ext.getStore('PurchaseOrder').find('id',purchaseOrderId);
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

describe("delete shipments and orders", function () {
	var flag = false,			
		testresult = null;
		
	beforeEach(function() {
		testresult = null;
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
				callback: function () {
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
	
	it("destroy order", function() {
		var record = Ext.getStore('order').find('id',orderId);
		
		Ext.getStore('order').setDestroyRemovedRecords(true);
		Ext.getStore('order').setSyncRemovedRecords(true);
		runs(function() {
			flag = false;
			Ext.getStore('order').removeAt(record);
			Ext.getStore('order').sync();
			Ext.getStore('order').load({
				callback: function () {
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
		testresult = null;
		
	beforeEach(function() {
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
						callback: function () {
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
						callback: function () {
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
		testresults = [];
		
	beforeEach(function() {
		testresults = [];
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
						callback: function () {
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
						callback: function () {
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
						callback: function () {
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
		testresults = [];
		
	beforeEach(function() {
		testresults = [];
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
						callback: function () {
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
						callback: function () {
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
						callback: function () {
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
						callback: function () {
							testresults.push(Ext.getStore('companies').find('ref','Company3'));
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
				callback: function () {
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
