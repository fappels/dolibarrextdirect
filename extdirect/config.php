<?php

/**
 * Tell Ext what php methods are available
 *
 */

$API = array(
	'ExtDirectSociete' => array(
		'methods' => array(
			'readSociete' => array(
				'len' => 1
			),
			'createSociete' => array(
				'len' => 1
			),
			'updateSociete' => array(
				'len' => 1
			),
			'destroySociete' => array(
				'len' => 1
			),
			'readSocieteList' => array(
				'len' => 1
			),
			'getTowns' => array(
				'len' => 1
			),
			'getCategories' => array(
				'len' => 1
			),
			'readStComm' => array(
				'len' => 1
			),
			'readProspectLevel' => array(
				'len' => 1
			),
			'readPaymentConditions' => array(
				'len' => 1
			),
			'readPaymentTypes' => array(
				'len' => 1
			),
			'readCountryConstants' => array(
				'len' => 1
			),
			'readStateConstants' => array(
				'len' => 1
			),
			'readConstants' => array(
				'len' => 1
			),
			'readOptionalModel' => array(
				'len' => 1
			),
			'readOptionals' => array(
				'len' => 1
			),
			'createOptionals' => array(
				'len' => 1
			),
			'updateOptionals' => array(
				'len' => 1
			),
			'destroyOptionals' => array(
				'len' => 1
			),
			'fileUpload' => array(
				'formHandler' => true
			)
		)
	),
	'ExtDirectContact' => array(
		'methods' => array(
			'readContact' => array(
				'len' => 1
			),
			'readContactList' => array(
				'len' => 1
			),
			'createContact' => array(
				'len' => 1
			),
			'updateContact' => array(
				'len' => 1
			),
			'destroyContact' => array(
				'len' => 1
			),
			'readOptionalModel' => array(
				'len' => 1
			),
			'readOptionals' => array(
				'len' => 1
			),
			'createOptionals' => array(
				'len' => 1
			),
			'updateOptionals' => array(
				'len' => 1
			),
			'destroyOptionals' => array(
				'len' => 1
			),
			'fileUpload' => array(
				'formHandler' => true
			)
		)
	),
	'ExtDirectActionComm' => array(
		'methods' => array(
			'readAction' => array(
				'len' => 1
			),
			'readActionList' => array(
				'len' => 1
			),
			'createAction' => array(
				'len' => 1
			),
			'updateAction' => array(
				'len' => 1
			),
			'destroyAction' => array(
				'len' => 1
			),
			'getAllUsers' => array(
				'len' => 1
			),
			'readOptionalModel' => array(
				'len' => 1
			),
			'readOptionals' => array(
				'len' => 1
			),
			'createOptionals' => array(
				'len' => 1
			),
			'updateOptionals' => array(
				'len' => 1
			),
			'destroyOptionals' => array(
				'len' => 1
			),
			'fileUpload' => array(
				'formHandler' => true
			)
		)
	),
	'ExtDirectCategorie' => array(
		'methods' => array(
			'readCategorie' => array(
				'len' => 1
			),
			'createCategorie' => array(
				'len' => 1
			),
			'updateCategorie' => array(
				'len' => 1
			),
			'destroyCategorie' => array(
				'len' => 1
			),
			'readCategorieList' => array(
				'len' => 1
			)
		)
	),
	'ExtDirectProduct' => array(
		'methods' => array(
			'readProduct' => array(
				'len' => 1
			),
			'createProduct' => array(
				'len' => 1
			),
			'updateProduct' => array(
				'len' => 1
			),
			'destroyProduct' => array(
				'len' => 1
			),
			'readProductList' => array(
				'len' => 1
			),
			'readProductBatchList' => array(
				'len' => 1
			),
			'readOptionalModel' => array(
				'len' => 1
			),
			'readOptionals' => array(
				'len' => 1
			),
			'createOptionals' => array(
				'len' => 1
			),
			'updateOptionals' => array(
				'len' => 1
			),
			'destroyOptionals' => array(
				'len' => 1
			),
			'readAttributes' => array(
				'len' => 1
			)
		)
	),
	'ExtDirectTranslate' => array(
		'methods' => array(
			'setDomain' => array(
				'len' => 2
			),
			'load' => array(
				'len' => 1
			)
		)
	),
	'ExtDirectFormProduct' => array(
		'methods' => array(
			'readWarehouses' => array(
				'len' => 1
			),
			'readPriceIndex' => array(
				'len' => 1
			),
			'readProductType' => array(
				'len' => 1
			),
			'readPriceBaseType' => array(
				'len' => 1
			),
			'readBarcodeType' => array(
				'len' => 1
			),
			'readSupplierReputations' => array(
				'len' => 1
			),
			'readProductUnits' => array(
				'len' => 1
			),
			'readProductLotType' => array(
				'len' => 1
			)
		)
	),
	'ExtDirectAuthenticate' => array(
		'methods' => array(
			'createAuthentication' => array(
				'len' => 1
			),
			'readAuthentication' => array(
				'len' => 1
			),
			'updateAuthentication' => array(
				'len' => 1
			),
			'destroyAuthentication' => array(
				'len' => 1
			)
		)
	),
	'ExtDirectActivities' => array(
		'methods' => array(
			'createActivity' => array(
				'len' => 1
			),
			'readActivities' => array(
				'len' => 1
			),
			'updateActivity' => array(
				'len' => 1
			),
			'destroyActivity' => array(
				'len' => 1
			)
		)
	),
	'ExtDirectCommande' => array(
		'methods' => array(
			'readOrder' => array(
				'len' => 1
			),
			'createOrder' => array(
				'len' => 1
			),
			'updateOrder' => array(
				'len' => 1
			),
			'destroyOrder' => array(
				'len' => 1
			),
			'readOrdelList' => array( // deprecated
				'len' => 1
			),
			'readOrderList' => array(
				'len' => 1
			),
			'readOrderStatus' => array(
				'len' => 1
			),
			'readContactTypes' => array(
				'len' => 1
			),
			'readOrderLine' => array(
				'len' => 1
			),
			'createOrderLine' => array(
				'len' => 1
			),
			'updateOrderLine' => array(
				'len' => 1
			),
			'destroyOrderLine' => array(
				'len' => 1
			),
			'readAvailabilityCodes' => array(
				'len' => 1
			),
			'readShipmentModes' => array(
				'len' => 1
			),
			'readIncotermCodes' => array(
				'len' => 1
			),
			'readConstants' => array(
				'len' => 1
			),
			'readOptionalModel' => array(
				'len' => 1
			),
			'readOptionals' => array(
				'len' => 1
			),
			'createOptionals' => array(
				'len' => 1
			),
			'updateOptionals' => array(
				'len' => 1
			),
			'destroyOptionals' => array(
				'len' => 1
			),
			'readLineOptionalModel' => array(
				'len' => 1
			),
			'readLineOptionals' => array(
				'len' => 1
			),
			'createLineOptionals' => array(
				'len' => 1
			),
			'updateLineOptionals' => array(
				'len' => 1
			),
			'destroyLineOptionals' => array(
				'len' => 1
			),
			'fileUpload' => array(
				'formHandler' => true
			)
		)
	),
	'ExtDirectCommandeFournisseur' => array(
		'methods' => array(
			'readOrder' => array(
				'len' => 1
			),
			'createOrder' => array(
				'len' => 1
			),
			'updateOrder' => array(
				'len' => 1
			),
			'destroyOrder' => array(
				'len' => 1
			),
			'readContactTypes' => array(
				'len' => 1
			),
			'readOrderList' => array(
				'len' => 1
			),
			'readOrderStatus' => array(
				'len' => 1
			),
			'readOrderLine' => array(
				'len' => 1
			),
			'createOrderLine' => array(
				'len' => 1
			),
			'updateOrderLine' => array(
				'len' => 1
			),
			'destroyOrderLine' => array(
				'len' => 1
			),
			'readConstants' => array(
				'len' => 1
			),
			'readOptionalModel' => array(
				'len' => 1
			),
			'readOptionals' => array(
				'len' => 1
			),
			'createOptionals' => array(
				'len' => 1
			),
			'updateOptionals' => array(
				'len' => 1
			),
			'destroyOptionals' => array(
				'len' => 1
			),
			'readLineOptionalModel' => array(
				'len' => 1
			),
			'readLineOptionals' => array(
				'len' => 1
			),
			'createLineOptionals' => array(
				'len' => 1
			),
			'updateLineOptionals' => array(
				'len' => 1
			),
			'destroyLineOptionals' => array(
				'len' => 1
			),
			'fileUpload' => array(
				'formHandler' => true
			)
		)
	),
	'ExtDirectExpedition' => array(
		'methods' => array(
			'readShipment' => array(
				'len' => 1
			),
			'createShipment' => array(
				'len' => 1
			),
			'updateShipment' => array(
				'len' => 1
			),
			'destroyShipment' => array(
				'len' => 1
			),
			'readShipmentList' => array(
				'len' => 1
			),
			'readShipmentStatus' => array(
				'len' => 1
			),
			'readContactTypes' => array(
				'len' => 1
			),
			'readShipmentLine' => array(
				'len' => 1
			),
			'createShipmentLine' => array(
				'len' => 1
			),
			'updateShipmentLine' => array(
				'len' => 1
			),
			'destroyShipmentLine' => array(
				'len' => 1
			),
			'readConstants' => array(
				'len' => 1
			),
			'readOptionalModel' => array(
				'len' => 1
			),
			'readOptionals' => array(
				'len' => 1
			),
			'createOptionals' => array(
				'len' => 1
			),
			'updateOptionals' => array(
				'len' => 1
			),
			'destroyOptionals' => array(
				'len' => 1
			),
			'readLineOptionalModel' => array(
				'len' => 1
			),
			'readLineOptionals' => array(
				'len' => 1
			),
			'createLineOptionals' => array(
				'len' => 1
			),
			'updateLineOptionals' => array(
				'len' => 1
			),
			'destroyLineOptionals' => array(
				'len' => 1
			),
			'fileUpload' => array(
				'formHandler' => true
			)
		)
	),
	'ExtDirectFichinter' => array(
		'methods' => array(
			'readIntervention' => array(
				'len' => 1
			),
			'createIntervention' => array(
				'len' => 1
			),
			'updateIntervention' => array(
				'len' => 1
			),
			'destroyIntervention' => array(
				'len' => 1
			),
			'readList' => array(
				'len' => 1
			),
			'readStatus' => array(
				'len' => 1
			),
			'readContactTypes' => array(
				'len' => 1
			),
			'readInterventionLine' => array(
				'len' => 1
			),
			'createInterventionLine' => array(
				'len' => 1
			),
			'updateInterventionLine' => array(
				'len' => 1
			),
			'destroyInterventionLine' => array(
				'len' => 1
			),
			'readConstants' => array(
				'len' => 1
			),
			'readOptionalModel' => array(
				'len' => 1
			),
			'readOptionals' => array(
				'len' => 1
			),
			'createOptionals' => array(
				'len' => 1
			),
			'updateOptionals' => array(
				'len' => 1
			),
			'destroyOptionals' => array(
				'len' => 1
			),
			'readLineOptionalModel' => array(
				'len' => 1
			),
			'readLineOptionals' => array(
				'len' => 1
			),
			'createLineOptionals' => array(
				'len' => 1
			),
			'updateLineOptionals' => array(
				'len' => 1
			),
			'destroyLineOptionals' => array(
				'len' => 1
			),
			'fileUpload' => array(
				'formHandler' => true
			)
		)
	),
	'ExtDirectShipmentPackage' => array(
		'methods' => array(
			'extRead' => array(
				'len' => 1
			),
			'extCreate' => array(
				'len' => 1
			),
			'extUpdate' => array(
				'len' => 1
			),
			'extDestroy' => array(
				'len' => 1
			),
			'extList' => array(
				'len' => 1
			),
			'readStatus' => array(
				'len' => 1
			),
			'readContactTypes' => array(
				'len' => 1
			),
			'readConstants' => array(
				'len' => 1
			),
			'readOptionalModel' => array(
				'len' => 1
			),
			'readOptionals' => array(
				'len' => 1
			),
			'createOptionals' => array(
				'len' => 1
			),
			'updateOptionals' => array(
				'len' => 1
			),
			'destroyOptionals' => array(
				'len' => 1
			),
			'fileUpload' => array(
				'formHandler' => true
			)
		)
	),
	'ExtDirectMo' => array(
		'methods' => array(
			'extRead' => array(
				'len' => 1
			),
			'extCreate' => array(
				'len' => 1
			),
			'extUpdate' => array(
				'len' => 1
			),
			'extDestroy' => array(
				'len' => 1
			),
			'extList' => array(
				'len' => 1
			),
			'readStatus' => array(
				'len' => 1
			),
			'readContactTypes' => array(
				'len' => 1
			),
			'readConstants' => array(
				'len' => 1
			),
			'readOptionalModel' => array(
				'len' => 1
			),
			'readOptionals' => array(
				'len' => 1
			),
			'createOptionals' => array(
				'len' => 1
			),
			'updateOptionals' => array(
				'len' => 1
			),
			'destroyOptionals' => array(
				'len' => 1
			),
			'fileUpload' => array(
				'formHandler' => true
			),
			'extReadLines' => array(
				'len' => 1
			),
			'extCreateLines' => array(
				'len' => 1
			),
			'extUpdateLines' => array(
				'len' => 1
			),
			'extDestroyLines' => array(
				'len' => 1
			)
		)
	)
);
