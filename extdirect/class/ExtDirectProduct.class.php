<?PHP

/*
 * Copyright (C) 2013-2014  Francis Appels <francis.appels@z-application.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * or see http://www.gnu.org/
 */

/**
 *  \file       htdocs/extdirect/class/ExtDirectProduct.class.php
 *  \brief      Sencha Ext.Direct products remoting class
 */

require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.product.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
dol_include_once('/extdirect/class/extdirect.class.php');
dol_include_once('/extdirect/class/ExtDirectFormProduct.class.php');

/** ExtDirectProduct class
 *
 * Class to access products with CRUD(L) methods to connect to Extjs or sencha touch using Ext.direct connector
 *
 * @category External_Module
 * @package  Extdirect
 * @author   Francis Appels <francis.appels@z-application.com>
 * @license  http://www.gnu.org/licenses/ GPLV3
 * @version  Release: 1.0
 * @link     https://github.com/fappels/dolibarrextdirect/blob/master/extdirect/class/ExtDirectProduct.class.php
 */
class ExtDirectProduct extends ProductFournisseur
{
	private $_user;
	private $_enabled = false;

	/**
	 * parameters received from client
	 *
	 * @var stdClass
	 */
	public $extParam;

	/** @var string $table_element_reception_line table of order reception line */
	public $table_element_reception_line = 'receptiondet_batch';

	/** Constructor
	 *
	 * @param string $login user name
	 */
	public function __construct($login)
	{
		global $langs, $db, $user, $conf, $mysoc;

		if (!empty($login)) {
			if ((is_object($login) && get_class($db) == get_class($login)) || $user->id > 0 || $user->fetch('', $login, '', 1) > 0) {
				$user->getrights();
				$this->_enabled = !empty($conf->product->enabled) && isset($user->rights->produit->lire);
				$this->_user = $user;  //product.class uses global user
				if (ExtDirect::checkDolVersion(0, '', '19.0')) {
					$this->table_element_reception_line = 'commande_fournisseur_dispatch';
				}
				if (isset($this->_user->conf->MAIN_LANG_DEFAULT)) {
					$langs->setDefaultLang($this->_user->conf->MAIN_LANG_DEFAULT);
				} else {
					$langs->setDefaultLang(empty($conf->global->MAIN_LANG_DEFAULT) ? 'auto' : $conf->global->MAIN_LANG_DEFAULT);
				}
				// set global $mysoc required for price calculation
				$mysoc = new Societe($db);
				$mysoc->setMysoc($conf);
				$langs->load("main");
				$langs->load("dict");
				$langs->load("products");
				$langs->load("stocks");
				$langs->load("errors");
				$langs->load("extdirect@extdirect");
				if (! empty($conf->productbatch->enabled)) {
					$langs->load("productbatch");
					require_once DOL_DOCUMENT_ROOT.'/product/class/productbatch.class.php';
					require_once DOL_DOCUMENT_ROOT.'/product/stock/class/productlot.class.php';
				}
				parent::__construct($db);
			}
		}
	}

	/**
	 *    Load products from database into memory
	 *
	 *    @param    stdClass    $param  filter with elements:
	 *                                  id                  Id of product to load
	 *                                  ref                 Reference of product, name
	 *                                  warehouse_id        filter product on a warehouse
	 *                                  multiprices_index   filter product on a multiprice index
	 *                                  barcode             barcode of product
	 *                                  batch               batch code of product
	 *                                  batch_id            batch rowid of product
	 *                                  ref_supplier        supplier reference code
	 *                                  photo_size          string with foto size 'mini', 'small' or 'full'
	 *    @return     stdClass result data or -1
	 */
	public function readProduct(stdClass $param)
	{
		global $conf, $mysoc;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->produit->lire)) return PERMISSIONERROR;
		$results = array();
		$row = new stdClass;
		$id = 0;
		$idArray = array();
		$ref = '';
		$ref_ext = '';
		$batch = '';
		$photoSize = '';
		$refSupplier = null;
		$refSupplierId = null;
		$warehouse = null;
		$socid = null;
		$multiprices_index = 1;

		if (isset($param->filter)) {
			foreach ($param->filter as $key => $filter) {
				if ($filter->property == 'id') $id=$filter->value;
				elseif ($filter->property == 'ref') $ref=$filter->value;
				elseif ($filter->property == 'warehouse_id') $warehouse=$filter->value;
				elseif ($filter->property == 'multiprices_index' ) $multiprices_index=$filter->value;
				elseif ($filter->property == 'barcode' ) {
					$idArray = $this->fetchIdFromBarcode($filter->value);
					if ($idArray['product'] > 0) {
						$id = $idArray['product'];
					} elseif (ExtDirect::checkDolVersion(0, '13.0', '')) {
						$idArray = $this->fetchIdFromBarcode($filter->value, 'product_fournisseur_price');
						$id = $idArray['product'];
					}
				} elseif ($filter->property == 'batch') $batch = $filter->value;
				elseif ($filter->property == 'batch_id') $batchId = $filter->value;
				elseif ($filter->property == 'ref_supplier') $refSupplier = $filter->value;
				elseif ($filter->property == 'ref_supplier_id') $refSupplierId = $filter->value;
				elseif ($filter->property == 'photo_size' && !empty($filter->value)) $photoSize = $filter->value;
				elseif ($filter->property == 'customer_id' && !empty($filter->value)) $socid = $filter->value;
			}
		}

		if (!$refSupplierId && isset($idArray['supplier_product'])) {
			$refSupplierId = $idArray['supplier_product'];
		}

		if (($id > 0) || ($ref != '')) {
			if ($socid > 0) {
				$customer = new Societe($this->db);
				if (($result = $customer->fetch($socid)) < 0) return ExtDirect::getDolError($result, $customer->errors, $customer->error);
			}
			if (($result = $this->fetch($id, $ref, $ref_ext)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
			if ($this->id > 0) {
				$this->fetch_barcode();
				$row->id = $this->id ;
				$row->is_virtual_stock = false;
				//! Ref
				$row->ref= $this->ref;
				$row->label= $this->label;
				$row->description= $this->description?html_entity_decode($this->description):'';
				//! Type 0 for regular product, 1 for service (Advanced feature: 2 for assembly kit, 3 for stock kit)
				$row->type= $this->type;
				$row->note= $this->note;
				//! Selling price
				$row->price= $this->price?$this->price:'';              // Price net
				$row->price_ttc= $this->price_ttc?$this->price_ttc:'';          // Price with tax
				$row->tva_tx = '';
				$row->price_min= $this->price_min;         // Minimum price net
				$row->price_min_ttc= $this->price_min_ttc;     // Minimum price with tax
				//! French VAT NPR (0 or 1)
				$row->tva_npr= $this->tva_npr;
				//! local taxes
				$row->localtax1_tx = $this->localtax1_tx;
				$row->localtax2_tx = $this->localtax2_tx;
				//! Base price ('TTC' for price including tax or 'HT' for net price)
				$row->price_base_type= $this->price_base_type;
				if (! empty($conf->global->PRODUIT_MULTIPRICES) && isset($multiprices_index)) {
					// from given price level
					$row->price=$this->multiprices[$multiprices_index]?$this->multiprices[$multiprices_index]:'';
					$row->price_ttc=$this->multiprices_ttc[$multiprices_index]?$this->multiprices_ttc[$multiprices_index]:'';
					$row->price_min=$this->multiprices_min[$multiprices_index]?$this->multiprices_min[$multiprices_index]:'';
					if (! empty($conf->global->PRODUIT_MULTIPRICES_USE_VAT_PER_LEVEL) || empty($socid)) {
						// using this option is a bug. kept for backward compatibility
						$row->tva_tx=$this->multiprices_tva_tx[$multiprices_index]?$this->multiprices_tva_tx[$multiprices_index]:'';
					} elseif (!empty($socid)) {
						$row->tva_tx = get_default_tva($mysoc, $customer, $this->id);
					}
					$row->price_base_type=$this->multiprices_base_type[$multiprices_index];
					$row->multiprices_index=$multiprices_index;
				} elseif (ExtDirect::checkDolVersion(0, '10.0') && !empty($socid)) {
					$priceArray = $this->getSellPrice($mysoc, $customer);
					$row->price=$priceArray['pu_ht'];
					$row->price_ttc=$priceArray['pu_ttc'];
					$row->price_min= $priceArray['price_min'];
					$row->tva_tx = $priceArray['tva_tx'];
					$row->tva_npr= $priceArray['tva_npr'];
					$row->price_base_type=$priceArray['price_base_type'];
				} elseif (!empty($conf->global->PRODUIT_MULTIPRICES) && !empty($socid)) {
					// from customer price level
					$row->multiprices_index=$customer->price_level;
					$row->price=$this->multiprices[$customer->price_level]?$this->multiprices[$customer->price_level]:'';
					$row->price_ttc=$this->multiprices_ttc[$customer->price_level]?$this->multiprices_ttc[$customer->price_level]:'';
					$row->price_min=$this->multiprices_min[$customer->price_level]?$this->multiprices_min[$customer->price_level]:'';
					$row->tva_tx = get_default_tva($mysoc, $customer, $this->id);
					$row->price_base_type=$this->multiprices_base_type[$customer->price_level];
				} elseif (! empty($conf->global->PRODUIT_CUSTOMER_PRICES) && ! empty($socid)) {
					// Price by customer
					require_once DOL_DOCUMENT_ROOT . '/product/class/productcustomerprice.class.php';

					$prodcustprice = new Productcustomerprice($this->db);

					if (ExtDirect::checkDolVersion(0, '17.0')) {
						$result = $prodcustprice->fetchAll('', '', 0, 0, array('t.fk_product' => $this->id,'t.fk_soc' => $socid));
					} else {
						$result = $prodcustprice->fetch_all('', '', 0, 0, array('t.fk_product' => $this->id,'t.fk_soc' => $socid));
					}
					if ($result > 0) {
						if (count($prodcustprice->lines) > 0) {
							$row->price = $prodcustprice->lines [0]->price;
							$row->price_ttc = $prodcustprice->lines [0]->price_ttc;
							$row->price_base_type = $prodcustprice->lines [0]->price_base_type;
							$row->tva_tx = $prodcustprice->lines [0]->tva_tx;
						}
					} elseif ($result < 0) {
						return ExtDirect::getDolError($result, $prodcustprice->errors, $prodcustprice->error);
					}
				}
				//! Default VAT rate of product, make sure vat is set if multi/customer vat is not set.
				if ($row->tva_tx === '') $row->tva_tx = ($this->tva_tx) ? $this->tva_tx : '';

				// batch managed product
				if (!empty($conf->productbatch->enabled)) $row->has_batch = $this->status_batch;

				//! Stock
				if (isset($warehouse) && $warehouse != ExtDirectFormProduct::ALLWAREHOUSE_ID) {
					$this->load_stock('novirtual, warehouseopen, warehouseinternal');
					$row->pmp = $this->pmp;
					if (!empty($conf->productbatch->enabled) && (!empty($batch) || isset($batchId))) {
						// TODO if warehouse is a parent warehouse get all batches from childs
						$productBatch = new Productbatch($this->db);
						if (!empty($batchId)) {
							$productBatch->fetch($batchId);
						} else {
							if (!empty($batch) && !empty($this->stock_warehouse[$warehouse]->id)) {
								$productBatch->find($this->stock_warehouse[$warehouse]->id, '', '', $batch);
							}
						}
						if (empty($productBatch->id)) {
							$row->batch_id = 0; // for adding new batch when batch not found
							$batchesQty = 0;
							$stockQty = $this->stock_warehouse[$warehouse]->real;
							if ((! empty($this->stock_warehouse[$warehouse]->id)) && (($batchesQty = $this->fetchBatchesQty($this->stock_warehouse[$warehouse]->id)) < 0 )) return $batchesQty;
							dol_syslog(get_class($this)."::batchesQty=".$batchesQty." stockQty=".$stockQty);
							$row->stock_reel = price2num($stockQty - $batchesQty, 5);
						} else {
							$row->batch_id = $productBatch->id;
							$row->batch = $productBatch->batch;
							$row->batch_info = $productBatch->import_key;
							$row->stock_reel = (float) $productBatch->qty;
							// fetch lot data
							$productLot = new Productlot($this->db);
							$result = $productLot->fetch(0, $this->id, $productBatch->batch);
							if ($result < 0) return ExtDirect::getDolError($result, $productLot->errors, $productLot->error);
							if ($result > 0) {
								$row->sellby = $productLot->sellby;
								$row->eatby = $productLot->eatby;
							}
						}
					} else {
						// TODO if warehouse is a parent warehouse (with no stock) and only in one child get child stock
						$row->stock_reel= (float) $this->stock_warehouse[$warehouse]->real;
					}
				} else {
					//! Average price value for product entry into stock (PMP)
					$row->pmp= $this->pmp;
					if (! empty($conf->productbatch->enabled) && ! empty($batch)) {
						// fetch qty and warehouse of first batch found
						$formProduct = new FormProduct($this->db);
						if (!empty($conf->global->STOCK_SHOW_VIRTUAL_STOCK_IN_PRODUCTS_COMBO)) {
							$this->load_stock('warehouseopen, warehouseinternal');
						} else {
							$this->load_stock('novirtual, warehouseopen, warehouseinternal');
						}
						$nbrWarehouses = $formProduct->loadWarehouses($this->id, '', 'warehouseopen, warehouseinternal');
						if ($nbrWarehouses > 0) {
							foreach ($formProduct->cache_warehouses as $warehouseId => $wh) {
								if (! empty($this->stock_warehouse[$warehouseId]->id)) {
									$productBatch = new Productbatch($this->db);
									$productBatch->find($this->stock_warehouse[$warehouseId]->id, '', '', $batch);
									if (isset($productBatch->id)) {
										$row->batch_id = $productBatch->id;
										// fetch lot data
										$productLot = new Productlot($this->db);
										$result = $productLot->fetch(0, $this->id, $productBatch->batch);
										if ($result < 0) return ExtDirect::getDolError($result, $productLot->errors, $productLot->error);
										if ($result > 0) {
											$row->sellby = $productLot->sellby;
											$row->eatby = $productLot->eatby;
										}
										$row->batch = $productBatch->batch;
										$row->batch_info = $productBatch->import_key;
										$row->warehouse_id = $warehouseId;
										$row->stock_reel = $productBatch->qty;
										break;
									}
								}
							}
						}
					} else {
						if (!empty($conf->global->STOCK_SHOW_VIRTUAL_STOCK_IN_PRODUCTS_COMBO)) {
							$this->load_stock('warehouseopen, warehouseinternal');
							$row->is_virtual_stock = true;
							$row->stock_reel = (float) $this->stock_theorique;
						} else {
							$this->load_stock('novirtual, warehouseopen, warehouseinternal');
							if (count($this->stock_warehouse) == 1) {
								// only in one warehouse
								foreach ($this->stock_warehouse as $warehouseId => $stock_warehouse) {
									$row->stock_reel = (float) $stock_warehouse->real;
									$row->warehouse_id = $warehouseId;
								}
							} else {
								$row->stock_reel = (float) $this->stock_reel;
							}
						}
					}
				}
				// add compatibility with orderline model
				$row->stock = $row->stock_reel;
				if (!empty($conf->global->STOCK_SHOW_VIRTUAL_STOCK_IN_PRODUCTS_COMBO)) {
					$row->total_stock = (float) $this->stock_theorique;
				} else {
					$row->total_stock = (float) $this->stock_reel;
				}
				//! Stock alert
				$row->seuil_stock_alerte= $this->seuil_stock_alerte;
				$row->desiredstock= $this->desiredstock;
				// warehouse
				if (empty($row->warehouse_id)) $row->warehouse_id = $warehouse;
				$row->default_warehouse_id = $this->fk_default_warehouse;
				//! Duree de validite du service
				$row->duration_value= $this->duration_value;
				//! Unite de duree
				$row->duration_unit= $this->duration_unit;
				// Statut indique si le produit est en vente '1' ou non '0'
				$row->tosell= $this->status;
				// Status indicate whether the product is available for purchase '1' or not '0'
				$row->tobuy= $this->status_buy;
				// Statut indique si le produit est un produit fini '1' ou une matiere premiere '0'
				$row->finished= $this->finished;
				$row->customcode= $this->customcode;       // Customs code
				$row->country_id= $this->country_id;       // Country origin id
				$row->country_code= $this->country_code;     // Country origin code (US, FR, ...)
				//measurements and units
				$row->weight= $this->weight;
				$row->weight_units= $this->weight_units;
				$row->product_length= $this->length; // length is ext direct reader reserved object element.
				$row->length_units= $this->length_units;
				$row->width= $this->width;
				$row->width_units= $this->width_units;
				$row->height= $this->height;
				$row->height_units= $this->height_units;
				$row->surface= $this->surface;
				$row->surface_units= $this->surface_units;
				$row->volume= $this->volume;
				$row->volume_units= $this->volume_units;
				$row->accountancy_code_buy= $this->accountancy_code_buy;
				$row->accountancy_code_sell= $this->accountancy_code_sell;
				//$row->productinfo= $this->array_options['options_productinfo'];
				//! barcode
				$row->barcode= $this->barcode?$this->barcode:'';               // value
				$row->barcode_type= (int) $this->barcode_type;
				// get barcode with checksum included, same when scanned
				$row->barcode_with_checksum = $this->fetchBarcodeWithChecksum($this);
				// no links to offers in this version
				// no multilangs in this version
				//! Canevas a utiliser si le produit n'est pas un produit generique
				$row->canvas= $this->canvas;
				$row->entity= $this->entity;
				$row->import_key= $this->import_key;
				$row->date_creation= $this->date_creation;
				$row->date_modification= $this->date_modification;
				// product units (pcs, kg, ...)
				$row->unit_id = $this->fk_unit;
				// supplier fields
				$supplierProduct = new ProductFournisseur($this->db);
				if (empty($refSupplier) && empty($refSupplierId)) {
					$supplierProduct->find_min_price_product_fournisseur($this->id);
				} elseif ($refSupplierId > 0) {
					$supplierProduct->fetch_product_fournisseur_price($refSupplierId);
				} else {
					$supplierProducts = $supplierProduct->list_product_fournisseur_price($this->id);
					foreach ($supplierProducts as $prodsupplier) {
						if ($prodsupplier->ref_supplier == $refSupplier) {
							$supplierProduct = $prodsupplier;
						}
					}
				}
				$row->ref_supplier = $supplierProduct->ref_supplier;
				$row->ref_supplier_id = $supplierProduct->product_fourn_price_id;
				if (!empty($supplierProduct->fourn_qty)) {
					$row->qty_supplier = $supplierProduct->fourn_qty;
				} else {
					$row->qty_supplier = 1; //default
				}
				if (!empty($this->_user->rights->fournisseur->lire)) {
					$row->price_supplier = $supplierProduct->fourn_price;
					$row->reduction_percent_supplier = $supplierProduct->fourn_remise_percent;
					$row->reduction_supplier = $supplierProduct->fourn_remise;
					$row->pu_supplier = $supplierProduct->fourn_unitprice;
					$row->supplier_id = $supplierProduct->fourn_id;
					if (isset($supplierProduct->fourn_tva_tx)) { // workaround
						$row->vat_supplier = $supplierProduct->fourn_tva_tx;
					} else {
						$row->vat_supplier = $supplierProduct->tva_tx;
					}
					$row->price_base_type_supplier = 'HT';
					$row->supplier_reputation = $supplierProduct->supplier_reputation;
					$row->cost_price = $this->cost_price;
				}
				if (($refSupplierId > 0 || $refSupplier) && !empty($supplierProduct->supplier_barcode)) {
					//! barcode is supplier barcode
					$row->barcode = $supplierProduct->supplier_barcode;
					if (empty($supplierProduct->supplier_fk_barcode_type) && ! empty($conf->global->PRODUIT_DEFAULT_BARCODE_TYPE)) {
						$row->barcode_type= (int) $conf->global->PRODUIT_DEFAULT_BARCODE_TYPE;
					} else {
						$row->barcode_type= (int) $supplierProduct->supplier_fk_barcode_type;
					}
					// get barcode with checksum included, same when scanned
					$row->barcode_with_checksum = $this->fetchBarcodeWithChecksum($supplierProduct);
				}
				$row->has_photo = 0;
				if (!empty($photoSize)) {
					$this->fetchPhoto($row, $photoSize);
				}

				if (!empty($batch)) {
					if (!empty($row->batch_id)) {
						array_push($results, $row);
					}
				} else {
					array_push($results, $row);
				}
			}
		}

		return $results;
	}

	/**
	 * public method to read available product optionals (extra fields)
	 *
	 * @return stdClass result data or ERROR
	 */
	public function readOptionalModel()
	{
		if (!isset($this->db)) return CONNECTERROR;

		return ExtDirect::readOptionalModel($this);
	}

	/**
	 * public method to read product or lot optionals (extra fields) from database
	 *
	 *    @param    stdClass    $param  filter with elements:
	 *                                  id                  Id of product to load
	 *                                  batch               batch code of product for lot attributes
	 *
	 *    @return     stdClass result data or -1
	 */
	public function readOptionals(stdClass $param)
	{
		global $conf;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->produit->lire)) return PERMISSIONERROR;
		$results = array();
		$id = 0;
		$batch = '';

		if (isset($param->filter)) {
			foreach ($param->filter as $key => $filter) {
				if ($filter->property == 'id') $id=$filter->value;
				elseif ($filter->property == 'batch') $batch = $filter->value;
			}
		}

		if ($id > 0) {
			$extraFields = new ExtraFields($this->db);
			if (empty($batch)) {
				if (($result = $this->fetch($id)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				if (! $this->error) {
					$extraFields->fetch_name_optionals_label($this->table_element);
					$index = 1;
					if (empty($this->array_options)) {
						// create empty optionals to be able to add optionals
						$optionsArray = (!empty($extraFields->attributes[$this->table_element]['label']) ? $extraFields->attributes[$this->table_element]['label'] : null);
						if (is_array($optionsArray) && count($optionsArray) > 0) {
							foreach ($optionsArray as $name => $label) {
								$row = new stdClass;
								$row->id = $index++;
								$row->name = $name;
								$row->value = '';
								$row->object_id = $this->id;
								$row->object_element = $this->element;
								$row->raw_value = null;
								$results[] = $row;
							}
						}
					} else {
						foreach ($this->array_options as $key => $value) {
							$row = new stdClass;
							$name = substr($key, 8); // strip options_
							$row->id = $index++; // ExtJs needs id to be able to destroy records
							$row->name = $name;
							$row->value = $extraFields->showOutputField($name, $value, '', $this->table_element); // display value
							$row->object_id = $this->id;
							$row->object_element = $this->element;
							$row->raw_value = $value;
							$results[] = $row;
						}
					}
				}
			} else {
				$productLot = new Productlot($this->db);
				if (($result = $productLot->fetch(0, $id, $batch)) < 0) return ExtDirect::getDolError($result, $productLot->errors, $productLot->error);
				if (! $productLot->error) {
					$extraFields->fetch_name_optionals_label($productLot->table_element);
					$index = 1;
					if (empty($productLot->array_options)) {
						// create empty optionals to be able to add optionals
						$optionsArray = (!empty($extraFields->attributes[$productLot->table_element]['label']) ? $extraFields->attributes[$productLot->table_element]['label'] : null);
						if (is_array($optionsArray) && count($optionsArray) > 0) {
							foreach ($optionsArray as $name => $label) {
								$row = new stdClass;
								$row->id = $index++;
								$row->name = $name;
								$row->value = '';
								$row->object_id = $productLot->id;
								$row->object_element = $productLot->element;
								$row->raw_value = null;
								$results[] = $row;
							}
						}
					} else {
						foreach ($productLot->array_options as $key => $value) {
							$row = new stdClass;
							$name = substr($key, 8); // strip options_
							$row->id = $index++;
							$row->name = $name;
							$row->value = $extraFields->showOutputField($name, $value, '', $productLot->table_element);
							$row->object_id = $productLot->id;
							$row->object_element = $productLot->element;
							$row->raw_value = $value;
							$results[] = $row;
						}
					}
				}
			}
		}
		return $results;
	}

	 /**
	 * public method to update product or lot optionals (extra fields) into database
	 *
	 *    @param    unknown_type    $params  optionals
	 *
	 *    @return     Ambigous <multitype:, unknown_type>|unknown
	 */
	public function updateOptionals($params)
	{
		global $conf;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->produit->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);

		foreach ($paramArray as &$param) {
			if (isset($param->element) && $param->element == 'productlot') {
				$productLot = new Productlot($this->db);
				if ($productLot->id != $param->object_id && ($result = $productLot->fetch($param->object_id)) < 0) return ExtDirect::getDolError($result, $productLot->errors, $productLot->error);
				$productLot->array_options['options_'.$param->name] = $param->raw_value;
			} else {
				if ($this->id != $param->object_id && ($result = $this->fetch($param->object_id)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				$this->array_options['options_'.$param->name] = $param->raw_value;
			}
		}
		if (isset($productLot)) {
			if (($result = $productLot->insertExtraFields()) < 0) return ExtDirect::getDolError($result, $productLot->errors, $productLot->error);
		} else {
			if (($result = $this->insertExtraFields()) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
		}
		if (is_array($params)) {
			return $paramArray;
		} else {
			return $param;
		}
	}

	/**
	 * public method to add product or lot optionals (extra fields) into database
	 *
	 *    @param    unknown_type    $params  optionals
	 *
	 *
	 *    @return     Ambigous <multitype:, unknown_type>|unknown
	 */
	public function createOptionals($params)
	{
		return $this->updateOptionals($params);
	}

	/**
	 * public method to delete product or lot optionals (extra fields) into database
	 *
	 *    @param    unknown_type    $params  optionals
	 *
	 *    @return    Ambigous <multitype:, unknown_type>|unknown
	 */
	public function destroyOptionals($params)
	{
		global $conf;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->produit->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);

		foreach ($paramArray as &$param) {
			if (isset($param->element) && $param->element == 'productlot') {
				$productLot = new Productlot($this->db);
				if (($result = $productLot->fetch($param->object_id)) < 0) return ExtDirect::getDolError($result, $productLot->errors, $productLot->error);
			} else {
				if ($this->id != $param->object_id && ($result = $this->fetch($param->object_id)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
			}
		}
		if (isset($productLot)) {
			if ($productLot->id != $param->object_id && ($result = $productLot->deleteExtraFields()) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
		} else {
			if (($result = $this->deleteExtraFields()) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
		}
		if (is_array($params)) {
			return $paramArray;
		} else {
			return $param;
		}
	}

	/**
	 * public method to read product attributes from database
	 *
	 *    @param    stdClass    $param  filter with elements:
	 *                                  id                  Id of product to load
	 *
	 *    @return     stdClass result data or -1
	 */
	public function readAttributes(stdClass $param)
	{
		global $conf, $langs;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->produit->lire)) return PERMISSIONERROR;
		$results = array();
		$id = 0;

		if (isset($param->filter)) {
			foreach ($param->filter as $key => $filter) {
				if ($filter->property == 'id') $id=$filter->value;
			}
		}

		if ($conf->variants->enabled && $id > 0) {
			require_once DOL_DOCUMENT_ROOT.'/variants/class/ProductCombination.class.php';
			require_once DOL_DOCUMENT_ROOT.'/variants/class/ProductCombination2ValuePair.class.php';
			require_once DOL_DOCUMENT_ROOT.'/variants/class/ProductAttributeValue.class.php';
			require_once DOL_DOCUMENT_ROOT.'/variants/class/ProductAttribute.class.php';
			$productCombination = new ProductCombination($this->db);
			if ($productCombination->fetchByFkProductChild($id) > 0) {
				$row = new stdClass;
				$row->name = $langs->trans('PriceImpact');
				$row->value = $productCombination->variation_price;
				$results[] = $row;
				$row = new stdClass;
				$row->name = $langs->trans('WeightImpact');
				$row->value = $productCombination->variation_weight;
				$results[] = $row;
				$productCombination2ValuePair = new ProductCombination2ValuePair($this->db);
				$attributes = $productCombination2ValuePair->fetchByFkCombination($productCombination->id);
				if (is_array($attributes)) {
					foreach ($attributes as $key => $value) {
						require_once DOL_DOCUMENT_ROOT.'/variants/class/ProductAttributeValue.class.php';
						require_once DOL_DOCUMENT_ROOT.'/variants/class/ProductAttribute.class.php';
						$prodattr = new ProductAttribute($this->db);
						$prodattrval = new ProductAttributeValue($this->db);

						$row = new stdClass;
						$prodattr->fetch($value->fk_prod_attr);
						$prodattrval->fetch($value->fk_prod_attr_val);

						$row->name = $prodattr->label;
						$row->value = $prodattrval->value;
						$results[] = $row;
					}
				}
			}
		}
		return $results;
	}

	/**
	 * Ext.direct method to Create product
	 *
	 * @param unknown_type $params object or object array with product model(s)
	 * @return Ambigous <multitype:, unknown_type>|unknown
	 */
	public function createProduct($params)
	{
		global $conf;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->produit->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);
		$supplier = new Societe($this->db);

		foreach ($paramArray as &$param) {
			$notrigger=0;
			$origin_element = '';
			$origin_id = null;
			$disablestockchangeforsubproduct = 0;
			if (isset($param->notrigger)) $notrigger = $param->notrigger;
			// prepare fields
			$this->prepareFields($param);
			$this->prepareFieldsBarcode($param);
			$this->prepareFieldsSellPrice($param);
			if (!empty($this->_user->rights->fournisseur->lire)) {
				$this->prepareFieldsBuyPrice($param);
			}
			if (!empty($this->barcode)) {
				$this->fetch_barcode();
			}
			// extrafields defaults
			$extraFields = ExtDirect::readOptionalModel($this);
			if (count($extraFields) > 0) {
				foreach ($extraFields as $extraField) {
					if (!empty($extraField->default)) {
						$this->array_options['options_'.$extraField->name] = $extraField->default;
					}
				}
			}
			if (!$notrigger) {
				// Call trigger
				$this->extParam = &$param; // pass client parameters by reference to trigger
				$result = $this->call_trigger('EXTDIRECTPRODUCT_PRE_CREATE', $this->_user);
				if ($result < 0) {
					return ExtDirect::getDolError($result, $this->errors, $this->error);
				}
				// End call triggers
			}

			if (!empty($param->origin_element)) $origin_element = $param->origin_element;
			if (!empty($param->origin_id)) $origin_id = $param->origin_id;
			if (!empty($param->disablestockchangeforsubproduct)) $disablestockchangeforsubproduct = $param->disablestockchangeforsubproduct;

			if (($result = $this->create($this->_user, $notrigger)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
			//! Stock
			if (!empty($param->correct_stock_nbpiece)) {
				if (! empty($conf->productbatch->enabled) && !empty($param->batch)) {
					$result = $this->correct_stock_batch(
									$this->_user,
									$param->warehouse_id,
									// nb of units
									$param->correct_stock_nbpiece,
									// 0 = add, 1 = remove
									$param->correct_stock_movement,
									// Label of stock movement
									$param->correct_stock_label,
									// Price to use for stock eval
									$param->correct_stock_price,
									// eatBy date
									ExtDirect::dateTimeToDate($param->eatby),
									// selltBy date
									ExtDirect::dateTimeToDate($param->sellby),
									// batch number
									$param->batch,
									// inventorycode
									$param->inventorycode,
									// Origin element type
									$origin_element,
									// Origin id of element
									$origin_id,
									// Disable stock change for sub-products of kit (usefull only if product is a subproduct)
									$disablestockchangeforsubproduct
					);
				} else {
					$result = $this->correct_stock(
									$this->_user,
									$param->warehouse_id,
									// nb of units
									$param->correct_stock_nbpiece,
									// 0 = add, 1 = remove
									$param->correct_stock_movement,
									// Label of stock movement
									$param->correct_stock_label,
									// Price to use for stock eval
									$param->correct_stock_price,
									// inventorycode
									$param->inventorycode,
									// Origin element type
									$origin_element,
									// Origin id of element
									$origin_id,
									// Disable stock change for sub-products of kit (usefull only if product is a subproduct)
									$disablestockchangeforsubproduct
					);
				}
				if ($result < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
			}
			// barcode
			if (!empty($this->barcode)) {
				$this->setValueFrom('barcode', $this->barcode);
				$this->setValueFrom('fk_barcode_type', $this->barcode_type);
			}

			// price
			if ($this->price_base_type == 'TTC') {
				$newSellPrice = $this->price_ttc;
			} else {
				$newSellPrice = $this->price;
			}
			if (!empty($newSellPrice) && ($result = $this->updatePrice($newSellPrice, $this->price_base_type, $this->_user, $this->tva_tx, $this->price_min, $param->multiprices_index, $this->tva_npr)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);

			// supplier fields
			if (!empty($this->fourn_price) && !empty($this->fourn_id)) {
				$supplierProduct = new ProductFournisseur($this->db);
				$supplier = new Societe($this->db);
				if (($result = $supplier->fetch($this->fourn_id)) < 0) return ExtDirect::getDolError($result, $supplier->errors, $supplier->error);
				$supplierProduct->id = $this->id;
				if (($result = $this->add_fournisseur($this->_user, $this->fourn_id, $this->ref_supplier, $this->fourn_qty)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				$supplierProduct->product_fourn_price_id = $this->product_fourn_price_id; // 3.3 comptibility
				if (($result = $supplierProduct->update_buyprice(
								$this->fourn_qty,
								$this->fourn_price,
								$this->_user,
								$param->price_base_type_supplier ? $param->price_base_type_supplier : 'HT',
								$supplier,
								0,
								$this->ref_supplier,
								$this->fourn_tva_tx ? $this->fourn_tva_tx : 0,
								0,
								$this->fourn_remise_percent,
								0,
								0,
								0,
								$this->supplier_reputation,
								array(),
								'',
								0,
								'HT',
								1,
								'',
								'',
								$this->barcode,
								$this->barcode_type
				)) < 0) return ExtDirect::getDolError($result, $supplierProduct->errors, $supplierProduct->error);
			}
			// add photo
			if (!empty($param->has_photo) && !empty($param->photo)) {
				if (($result = $this->addBase64Jpeg($param->photo)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
			}
			$param->id=$this->id;
			if (!$notrigger) {
				// Call trigger
				$this->extParam = &$param; // pass client parameters by reference to trigger
				$result = $this->call_trigger('EXTDIRECTPRODUCT_POST_CREATE', $this->_user);
				if ($result < 0) {
					return ExtDirect::getDolError($result, $this->errors, $this->error);
				}
				// End call triggers
			}
		}

		if (is_array($params)) {
			return $paramArray;
		} else {
			return $param;
		}
	}

	/**
	 * Ext.direct method to update product
	 *
	 * @param unknown_type $params object or object array with product model(s)
	 * @return Ambigous <multitype:, unknown_type>|unknown
	 */
	public function updateProduct($params)
	{
		global $conf, $langs;

		if (!isset($this->db)) return CONNECTERROR;
		// dolibarr update settings
		$supplierProducts = array();

		$paramArray = ExtDirect::toArray($params);
		foreach ($paramArray as &$param) {
			// prepare fields
			if ($param->id) {
				$notrigger=false;
				$origin_element = '';
				$origin_id = null;
				$disablestockchangeforsubproduct = 0;
				if (isset($param->notrigger)) $notrigger = $param->notrigger;
				$id = $param->id;
				if (($result = $this->fetch($id, '', '')) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				// supplier fields
				$supplierProduct = new ProductFournisseur($this->db);
				if (($supplierProducts = $supplierProduct->list_product_fournisseur_price($this->id)) < 0) return ExtDirect::getDolError($supplierProducts, $supplierProduct->errors, $supplierProduct->error);
				foreach ($supplierProducts as $prodsupplier) {
					if ($prodsupplier->product_fourn_price_id == $param->ref_supplier_id) {
						$this->ref_supplier = $prodsupplier->ref_supplier;
						$this->product_fourn_price_id = $prodsupplier->product_fourn_price_id;
						$this->fourn_price = $prodsupplier->fourn_price;
						$this->fourn_qty = $prodsupplier->fourn_qty;
						$this->fourn_remise_percent = $prodsupplier->fourn_remise_percent;
						$this->fourn_remise = $prodsupplier->fourn_remise;
						$this->fourn_unitprice = $prodsupplier->fourn_unitprice;
						$this->fourn_id = $prodsupplier->fourn_id;
						$this->fk_availability = $prodsupplier->fk_availability;
						if (isset($prodsupplier->fourn_tva_tx)) { // workaround
							$this->fourn_tva_tx = $prodsupplier->fourn_tva_tx;
						} else {
							$this->fourn_tva_tx = $prodsupplier->tva_tx;
						}
						$this->fourn_charges = $prodsupplier->fourn_charges;
						$this->fourn_tva_npr = $prodsupplier->fourn_tva_npr;
						$this->delivery_time_days = $prodsupplier->delivery_time_days;
						$this->supplier_reputation = $prodsupplier->supplier_reputation;
						$this->fourn_multicurrency_price = $prodsupplier->fourn_multicurrency_price;
						$this->fourn_multicurrency_tx = $prodsupplier->fourn_multicurrency_tx;
						$this->fourn_multicurrency_code = $prodsupplier->fourn_multicurrency_code;
						$this->desc_supplier = $prodsupplier->desc_supplier;
					}
				}
				$updated = $this->prepareFields($param);
				$updatedBarcode = $this->prepareFieldsBarcode($param);
				if (!empty($conf->global->PRODUIT_MULTIPRICES) && $param->multiprices_index > 0) {
					$updatedSellPrice = $this->prepareFieldsSellPrice($param, $param->multiprices_index);
				} else {
					$updatedSellPrice = $this->prepareFieldsSellPrice($param);
				}
				if ($this->_user->rights->fournisseur->lire) {
					$updatedBuyPrice = $this->prepareFieldsBuyPrice($param);
				} else {
					$updatedBuyPrice = false;
				}
				if (!empty($this->barcode)) {
					$this->fetch_barcode();
				}
				if (!$notrigger) {
					// Call trigger
					$this->extParam = &$param; // pass client parameters by reference to trigger
					$result = $this->call_trigger('EXTDIRECTPRODUCT_PRE_MODIFY', $this->_user);
					if ($result < 0) {
						return ExtDirect::getDolError($result, $this->errors, $this->error);
					}
					// End call triggers
				}

				if (!empty($param->origin_element)) $origin_element = $param->origin_element;
				if (!empty($param->origin_id)) $origin_id = $param->origin_id;
				if (!empty($param->disablestockchangeforsubproduct)) $disablestockchangeforsubproduct = $param->disablestockchangeforsubproduct;

				if (($updated || $updatedBarcode || $updatedSellPrice || $updatedBuyPrice) && (!isset($this->_user->rights->produit->creer))) return PERMISSIONERROR;
				if (!empty($param->correct_stock_nbpiece) && !isset($this->_user->rights->stock->mouvement->creer)) return PERMISSIONERROR;
				// verify
				if (($result = $this->verify()) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				// update
				if ($updated) {
					if (($result = $this->update($id, $this->_user, $notrigger)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				}
				// check batch or non batch
				$createNewBatchFromZeroStock = false;
				if (! empty($conf->productbatch->enabled) && !empty($param->batch)) {
					//! Stock
					$this->load_stock('novirtual, warehouseopen, warehouseinternal');
					$originalQty = $param->stock_reel;
					$stockQty = $this->stock_warehouse[$param->warehouse_id]->real;
					$productBatch = new Productbatch($this->db);

					if (($originalQty < 0) && ($param->batch_id > 0)) {
						// if negative batch qty, remove negative batch and move remaining positive qty
						$productBatch->id = $param->batch_id;
						if (($res = $productBatch->delete($this->_user)) < 0) return ExtDirect::getDolError($res, $productBatch->errors, $productBatch->error);
						$param->correct_stock_nbpiece = $param->correct_stock_nbpiece + $originalQty;
					} elseif (($param->correct_stock_movement == 1) && ($param->batch_id === 0)) {
						// correct stock - batch stock diff, only update batch qty not stock qty
						$batchCorrectQty = $param->correct_stock_nbpiece;
						$param->correct_stock_nbpiece = 0;
					} elseif ($stockQty > 0) {
						$batchesQty = 0;
						if (($batchesQty = $this->fetchBatchesQty($this->stock_warehouse[$param->warehouse_id]->id)) < 0 ) return $batchesQty;
						if ($param->batch_id === 0) {
							if (($param->correct_stock_movement == 0) && ($param->correct_stock_nbpiece > 0) && (($batchesQty + $param->correct_stock_nbpiece) <= $stockQty)) {
								// only create batch when non batched stock available
								$productBatch->batch = $param->batch;
								$productBatch->sellby = ExtDirect::dateTimeToDate($param->sellby);
								$productBatch->eatby = ExtDirect::dateTimeToDate($param->eatby);
								$productBatch->qty = $param->correct_stock_nbpiece;
								$productBatch->fk_product_stock = $this->stock_warehouse[$param->warehouse_id]->id;
								if (($res = $productBatch->create($this->_user, 1)) < 0) return ExtDirect::getDolError($res, $productBatch->errors, $productBatch->error);
								// don't move stock of this new batch
								$param->correct_stock_nbpiece = 0;
							}
						}
					} else {
						$createNewBatchFromZeroStock = true;
					}
					$correctStockFunction = 'correct_stock_batch';
				} else {
					$correctStockFunction = 'correct_stock';
				}
				if (!empty($param->correct_stock_dest_warehouseid)) {
					// transfer stock
					if (!empty($param->correct_stock_nbpiece)) {
						$movement = 1;
						if ($correctStockFunction == 'correct_stock_batch') {
							$result = $this->correct_stock_batch(
											$this->_user,
											$param->warehouse_id,
											// nb of units
											$param->correct_stock_nbpiece,
											// 0 = add, 1 = remove
											$movement,
											// Label of stock movement
											$param->correct_stock_label,
											// Price to use for stock eval
											$param->correct_stock_price,
											// eatBy date
											ExtDirect::dateTimeToDate($param->eatby),
											// sellBy date
											ExtDirect::dateTimeToDate($param->sellby),
											// batch number
											$param->batch,
											// inventorycode
											$param->inventorycode,
											// Origin element type
											$origin_element,
											// Origin id of element
											$origin_id,
											// Disable stock change for sub-products of kit (usefull only if product is a subproduct)
											$disablestockchangeforsubproduct
							);
						} else {
							$result = $this->correct_stock(
											$this->_user,
											$param->warehouse_id,
											// nb of units
											$param->correct_stock_nbpiece,
											// 0 = add, 1 = remove
											$movement,
											// Label of stock movement
											$param->correct_stock_label,
											// Price to use for stock eval
											$param->correct_stock_price,
											// inventorycode
											$param->inventorycode,
											// Origin element type
											$origin_element,
											// Origin id of element
											$origin_id,
											// Disable stock change for sub-products of kit (usefull only if product is a subproduct)
											$disablestockchangeforsubproduct
							);
						}
						if ($result < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
						$movement = 0;
						if ($correctStockFunction == 'correct_stock_batch') {
							$result = $this->correct_stock_batch(
											$this->_user,
											$param->correct_stock_dest_warehouseid,
											// nb of units
											$param->correct_stock_nbpiece,
											// 0 = add, 1 = remove
											$movement,
											// Label of stock movement
											$param->correct_stock_label,
											// Price to use for stock eval
											$param->correct_stock_price,
											// eatBy date
											ExtDirect::dateTimeToDate($param->eatby),
											// sellBy date
											ExtDirect::dateTimeToDate($param->sellby),
											// batch number
											$param->batch,
											// inventorycode
											$param->inventorycode,
											// Origin element type
											$origin_element,
											// Origin id of element
											$origin_id,
											// Disable stock change for sub-products of kit (usefull only if product is a subproduct)
											$disablestockchangeforsubproduct
							);
						} else {
							$result = $this->correct_stock(
											$this->_user,
											$param->correct_stock_dest_warehouseid,
											// nb of units
											$param->correct_stock_nbpiece,
											// 0 = add, 1 = remove
											$movement,
											// Label of stock movement
											$param->correct_stock_label,
											// Price to use for stock eval
											$param->correct_stock_price,
											// inventorycode
											$param->inventorycode,
											// Origin element type
											$origin_element,
											// Origin id of element
											$origin_id,
											// Disable stock change for sub-products of kit (usefull only if product is a subproduct)
											$disablestockchangeforsubproduct
							);
						}
						if ($result < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
					}
				} elseif (!empty($param->correct_stock_nbpiece)) {
					// change stock
					if ($correctStockFunction == 'correct_stock_batch') {
						$result = $this->correct_stock_batch(
										$this->_user,
										$param->warehouse_id,
										// nb of units
										$param->correct_stock_nbpiece,
										// 0 = add, 1 = remove
										$param->correct_stock_movement,
										// Label of stock movement
										$param->correct_stock_label,
										// Price to use for stock eval
										$param->correct_stock_price,
										// eatBy date
										ExtDirect::dateTimeToDate($param->eatby),
										// sellBy date
										ExtDirect::dateTimeToDate($param->sellby),
										// batch number
										$param->batch,
										// inventorycode
										$param->inventorycode,
										// Origin element type
										$origin_element,
										// Origin id of element
										$origin_id,
										// Disable stock change for sub-products of kit (usefull only if product is a subproduct)
										$disablestockchangeforsubproduct
						);
					} else {
						$result = $this->correct_stock(
										$this->_user,
										$param->warehouse_id,
										// nb of units
										$param->correct_stock_nbpiece,
										// 0 = add, 1 = remove
										$param->correct_stock_movement,
										// Label of stock movement
										$param->correct_stock_label,
										// Price to use for stock eval
										$param->correct_stock_price,
										// inventorycode
										$param->inventorycode,
										// Origin element type
										$origin_element,
										// Origin id of element
										$origin_id,
										// Disable stock change for sub-products of kit (usefull only if product is a subproduct)
										$disablestockchangeforsubproduct
						);
					}
					if ($result < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				}
				// barcode
				if ($updatedBarcode && !empty($this->barcode)) {
					if (($result = $this->setValueFrom('fk_barcode_type', $this->barcode_type)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
					if (($result = $this->setValueFrom('barcode', $this->barcode, '', null, '', '', null, 'BARCODE_MODIFY')) < 0) {
						$this->error = $langs->trans("Error")." : ".$langs->trans("ErrorProductBarCodeAlreadyExists", $this->barcode);
						return ExtDirect::getDolError($result, $this->errors, $this->error);
					}
				}
				// update product batch
				if (!empty($conf->productbatch->enabled) && (!empty($param->batch) || !empty($param->batch_id) || $createNewBatchFromZeroStock)) {
					$productBatch = new Productbatch($this->db);
					$dest = $param->correct_stock_dest_warehouseid;
					if (empty($dest) && (!(($param->correct_stock_movement == 1) && ($param->stock_reel - $param->correct_stock_nbpiece) == 0) || !empty($batchCorrectQty))) {
						// update batch if not removed
						if (!empty($param->batch_id)) {
							$productBatch->fetch($param->batch_id);
						} else {
							if ($createNewBatchFromZeroStock) {
								$this->load_stock('novirtual');
							}
							if (isset($this->stock_warehouse[$param->warehouse_id]->id)) {
								$productBatch->find($this->stock_warehouse[$param->warehouse_id]->id, ExtDirect::dateTimeToDate($param->eatby), ExtDirect::dateTimeToDate($param->sellby), $param->batch);
							}
						}
					} elseif (!empty($dest)) {
						// update destination batch
						if (isset($this->stock_warehouse[$dest]->id)) {
							$productBatch->find($this->stock_warehouse[$dest]->id, ExtDirect::dateTimeToDate($param->eatby), ExtDirect::dateTimeToDate($param->sellby), $param->batch);
						}
					}
					if (!empty($productBatch->id)) {
						// fetch lot data
						$productLot = new Productlot($this->db);
						if (($result = $productLot->fetch(0, $this->id, $productBatch->batch)) < 0) return ExtDirect::getDolError($result, $productLot->errors, $productLot->error);
						if ($productLot->id > 0) {
							!empty($batchCorrectQty) ? $param->batch_qty = $productBatch->qty - $batchCorrectQty : $param->batch_qty = null;
							isset($param->sellby) ? $param->date_sellby = ExtDirect::dateTimeToDate($param->sellby) : $param->date_sellby = null;
							isset($param->eatby) ? $param->date_eatby = ExtDirect::dateTimeToDate($param->eatby) : $param->date_eatby = null;
							$updatedBatch = $this->prepareFieldsBatch($param, $productLot, $productBatch);
							if ($updatedBatch) {
								if (($result = $productBatch->update($this->_user)) < 0) return ExtDirect::getDolError($result, $productBatch->errors, $productBatch->error);
								if (($result = $productLot->update($this->_user)) < 0) return ExtDirect::getDolError($result, $productLot->errors, $productLot->error);
							}
						}
					}
				}
				if ($updatedSellPrice && (isset($param->price) || isset($param->price_ttc))) {
					// price
					if ($param->price_base_type == 'TTC') {
						$newSellPrice = $param->price_ttc;
					} else {
						$newSellPrice = $param->price;
					}
					if (($result = $this->updatePrice($newSellPrice, $this->price_base_type, $this->_user, $this->tva_tx, $this->price_min, $param->multiprices_index, $this->tva_npr)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				}
				// supplier fields
				if ($updatedBuyPrice && isset($this->fourn_price) && !empty($this->ref_supplier) && !empty($this->fourn_id) && !empty($this->fourn_qty)) {
					$supplierProduct = new ProductFournisseur($this->db);
					$supplier = new Societe($this->db);
					if (($result = $supplier->fetch($this->fourn_id)) < 0) return ExtDirect::getDolError($result, $supplier->errors, $supplier->error);
					$supplierProduct->id = $this->id;
					if (empty($this->product_fourn_price_id)) {
						if (($result = $this->add_fournisseur($this->_user, $this->fourn_id, $this->ref_supplier, $this->fourn_qty)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
					}
					$supplierProduct->product_fourn_price_id = $this->product_fourn_price_id;
					if (($result = $supplierProduct->update_buyprice(
									$this->fourn_qty,
									$this->fourn_price,
									$this->_user,
									$param->price_base_type_supplier ? $param->price_base_type_supplier : 'HT',
									$supplier,
									$this->fk_availability,
									$this->ref_supplier,
									$this->fourn_tva_tx ? $this->fourn_tva_tx : 0,
									$this->fourn_charges,
									$this->fourn_remise_percent,
									$this->fourn_remise,
									$this->fourn_tva_npr,
									$this->delivery_time_days,
									$this->supplier_reputation,
									array(),
									'',
									$this->fourn_multicurrency_price,
									'HT',
									$this->fourn_multicurrency_tx,
									$this->fourn_multicurrency_code,
									$this->desc_supplier,
									$this->barcode,
									$this->barcode_type
					)) < 0) return ExtDirect::getDolError($result, $supplierProduct->errors, $supplierProduct->error);
				}

				// add photo
				$photo = new stdClass;
				$this->fetchPhoto($photo);
				if (isset($param->has_photo) && $param->has_photo > $photo->has_photo && !empty($param->photo) && isset($this->_user->rights->produit->creer)) {
					if (($result = $this->addBase64Jpeg($param->photo, $param->has_photo)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				}
				if (!$notrigger) {
					// Call trigger
					$this->extParam = &$param; // pass client parameters by reference to trigger
					$result = $this->call_trigger('EXTDIRECTPRODUCT_POST_MODIFY', $this->_user);
					if ($result < 0) {
						return ExtDirect::getDolError($result, $this->errors, $this->error);
					}
					// End call triggers
				}
			} else {
				return PARAMETERERROR;
			}
		}
		if (is_array($params)) {
			return $paramArray;
		} else {
			return $param;
		}
	}

	/**
	 * Ext.direct method to destroy product
	 *
	 * @param unknown_type $params object or object array with product model(s)
	 * @return Ambigous <multitype:, unknown_type>|unknown
	 */
	public function destroyProduct($params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->produit->supprimer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);
		$notrigger = 0;

		foreach ($paramArray as &$param) {
			// prepare fields
			if ($param->id) {
				if (isset($param->notrigger)) $notrigger = $param->notrigger;
				$id = $param->id;
				$this->id = $id;
				$this->ref = $param->ref;
				if (!$notrigger) {
					// Call trigger
					$this->extParam = &$param; // pass client parameters by reference to trigger
					$result = $this->call_trigger('EXTDIRECTPRODUCT_PRE_DELETE', $this->_user);
					if ($result < 0) {
						return ExtDirect::getDolError($result, $this->errors, $this->error);
					}
					// End call triggers
				}
				// delete product
				if (ExtDirect::checkDolVersion(0, '6.0', '')) {
					if (($result = $this->delete($this->_user, $notrigger)) <= 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				} else {
					if (($result = $this->delete($id)) <= 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				}
				if (!$notrigger) {
					// Call trigger
					$this->extParam = &$param; // pass client parameters by reference to trigger
					$result = $this->call_trigger('EXTDIRECTPRODUCT_POST_DELETE', $this->_user);
					if ($result < 0) {
						return ExtDirect::getDolError($result, $this->errors, $this->error);
					}
					// End call triggers
				}
			} else {
				return PARAMETERERROR;
			}
		}

		if (is_array($params)) {
			return $paramArray;
		} else {
			return $param;
		}
	}

	/**
	 * Public method to read a list of products
	 *
	 * @param stdClass $param   property filter to filter on:
	 *                          warehouse_id
	 *                          tosell
	 *                          tobuy
	 *                          finished
	 *                          type
	 *                          categorie_id
	 *                          content of ref, label and barcode
	 *                          property sort with properties field names and directions:
	 *                          property limit for paging with sql LIMIT and START values
	 *
	 * @return stdClass result data or -1
	 */
	public function readProductList(stdClass $param)
	{
		global $conf, $langs;
		if (!isset($this->db)) return CONNECTERROR;
		if (!$this->_enabled) return NOTENABLEDERROR;
		if (!isset($this->_user->rights->produit->lire)) return PERMISSIONERROR;
		$result = new stdClass;
		$data = array();

		$filterSize = 0;
		$limit=null;
		$start=0;
		$photoSize = '';
		$multiPriceLevel=1;
		$categorieFilter = false;
		$supplierFilter = false;
		$warehouseFilter = false;
		$socid = null;
		$includeTotal = true;
		$warehouseIds = array();
		$checkWarehouseIds = array();

		if (isset($param->limit)) {
			$limit = $param->limit;
			$start = $param->start;
		}
		if (isset($param->filter)) {
			$filterSize = count($param->filter);
			foreach ($param->filter as $key => $filter) {
				if ($filter->property == 'multiprices_index' && ! empty($conf->global->PRODUIT_MULTIPRICES)) $multiPriceLevel=$filter->value;
				elseif ($filter->property == 'customer_id' && ! empty($conf->global->PRODUIT_CUSTOMER_PRICES)) $socid=$filter->value;
				elseif ($filter->property == 'categorie_id') $categorieFilter=true;
				elseif ($filter->property == 'supplier_id' && !empty($this->_user->rights->fournisseur->lire)) $supplierFilter=true;
				elseif ($filter->property == 'warehouse_id') {
					$warehouseFilter = true;
					if ($filter->value > 0) $warehouseIds[] = $filter->value;
					$checkWarehouseIds[] = $filter->value;
				}
			}
		}
		if (isset($param->include_total)) {
			$includeTotal = $param->include_total;
		}

		if (isset($param->sort)) {
			$sorterSize = count($param->sort);
			foreach ($param->sort as $key => $sort) {
				if ($sort->property == 'warehouse_id') $warehouseFilter=true;
				if ($sort->property == 'categorie') $categorieFilter=true;
			}
		}

		$sqlFields = 'SELECT p.rowid as id, p.ref, p.label, p.barcode, p.entity, p.seuil_stock_alerte, p.stock as total_stock, p.price, p.price_ttc';
		if ($warehouseFilter) {
			$sqlFields .= ', ps.fk_entrepot, ps.reel as stock';
			if (ExtDirect::checkDolVersion(0, '7.0', '')) {
				$sqlFields .= ", COALESCE(e.ref,'|0| Stock') as warehouse";
			} else {
				$sqlFields .= ", COALESCE(e.label,'|0| Stock') as warehouse";
			}
		} else {
			$sqlFields .= ", '". $langs->trans(ExtDirectFormProduct::ALLWAREHOUSE_LABEL). "' as warehouse";
		}
		if ($supplierFilter) {
			$sqlFields .= ', sp.unitprice as price_supplier, sp.ref_fourn as ref_supplier, sp.rowid as ref_supplier_id, sp.quantity as qty_supplier, sp.remise_percent as reduction_percent_supplier';
			if (ExtDirect::checkDolVersion(0, '5.0', '')) $sqlFields .= ', sp.supplier_reputation';
			if (ExtDirect::checkDolVersion(0, '13.0', '')) $sqlFields .= ', sp.barcode as supplier_barcode';
			$sqlFields .= ', (SELECT SUM(cfdet.qty) FROM '.MAIN_DB_PREFIX.'commande_fournisseurdet as cfdet WHERE cfdet.fk_product = p.rowid) as ordered';
			$sqlFields .= ', (SELECT SUM(cfdis.qty) FROM '.MAIN_DB_PREFIX.$this->table_element_reception_line.' as cfdis WHERE cfdis.fk_product = p.rowid) as dispatched';
		} else {
			if (! empty($conf->global->PRODUIT_MULTIPRICES)) {
				$sqlFields .= ', pp.price as multi_price, pp.price_ttc as multi_price_ttc';
			}
			if (! empty($conf->global->PRODUIT_CUSTOMER_PRICES) && ! empty($socid)) {
				$sqlFields .= ', pcp.price as customer_price, pcp.price_ttc as customer_price_ttc';
			}
		}
		$sqlFrom = ' FROM '.MAIN_DB_PREFIX.'product as p';
		if ($warehouseFilter || !empty($conf->multicompany->enabled)) {
			if (in_array(0, $checkWarehouseIds) || empty($checkWarehouseIds)) {
				$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'product_stock as ps ON p.rowid = ps.fk_product';
			} else {
				$sqlFrom .= ' INNER JOIN '.MAIN_DB_PREFIX.'product_stock as ps ON p.rowid = ps.fk_product';
			}
			if (count($warehouseIds) > 0) {
				$sqlFrom .= ' AND ps.fk_entrepot IN ('.implode(',', $warehouseIds).')';
			}
			if (!empty($conf->multicompany->enabled)) {
				$sqlFrom .= ' AND ps.fk_entrepot IN (SELECT rowid FROM '.MAIN_DB_PREFIX.'entrepot WHERE entity IN ('.getEntity('stock', 1).'))';
			}
		}
		if ($categorieFilter) {
			$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie_product as cp ON p.rowid = cp.fk_product';
			$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie as c ON c.rowid = cp.fk_categorie';
		}
		if ($supplierFilter) {
			$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'product_fournisseur_price as sp ON p.rowid = sp.fk_product';
		}

		if ($warehouseFilter) {
			$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'entrepot as e on ps.fk_entrepot = e.rowid';
			if (count($warehouseIds) > 0) {
				$sqlFrom .= ' AND ps.fk_entrepot IN ('.implode(',', $warehouseIds).')';
			}
			if (! empty($conf->global->ENTREPOT_EXTRA_STATUS)) {
				$sqlFrom.= ' AND e.statut IN ('.Entrepot::STATUS_OPEN_ALL.','.Entrepot::STATUS_OPEN_INTERNAL.')';
			}
		}
		if (! empty($conf->global->PRODUIT_MULTIPRICES)) {
			$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'product_price as pp ON pp.rowid = ';
			$sqlFrom .= "(SELECT rowid";
			$sqlFrom .= " FROM " . MAIN_DB_PREFIX . "product_price ";
			$sqlFrom .= " WHERE fk_product = p.rowid";
			$sqlFrom .= " AND entity IN (" . getEntity('productprice') . ")";
			$sqlFrom .= " AND price_level=" . $multiPriceLevel;
			$sqlFrom .= " ORDER BY date_price";
			$sqlFrom .= " DESC LIMIT 1)";
		}
		if (! empty($conf->global->PRODUIT_CUSTOMER_PRICES) && ! empty($socid)) {
			$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'product_customer_price as pcp ON p.rowid = pcp.fk_product AND pcp.fk_soc = '.$socid;
		}
		$sqlWhere = ' WHERE p.entity IN ('.getEntity('product', 1).')';
		if ($filterSize > 0) {
			// TODO improve sql command to allow random property type
			$sqlWhere .= ' AND (';
			foreach ($param->filter as $key => $filter) {
				$value = $this->db->escape($filter->value);
				if (empty($value) && ($filter->property != 'type') && ($filter->property != 'supplier_id')) {
					$sqlWhere .= '1 = 1';
				} else {
					if ($filter->property == 'tosell') {
						$sqlWhere .= "p.tosell = ".$value;
					} elseif ($filter->property == 'tobuy') {
						$sqlWhere .= "p.tobuy = ".$value;
					} elseif ($filter->property == 'status') { // backward comp
						$sqlWhere .= "p.tosell = ".$value;
					} elseif ($filter->property == 'status_buy') {  // backward comp
						$sqlWhere .= "p.tobuy = ".$value;
					} elseif ($filter->property == 'finished') {
						$sqlWhere .= "p.finished = ".$value;
					} elseif ($filter->property == 'type') {
						$sqlWhere .= "p.fk_product_type = ".$value;
					} elseif ($filter->property == 'categorie_id') {
						$sqlWhere .= "c.rowid = ".$value;
					} elseif ($filter->property == 'supplier_id' && !empty($this->_user->rights->fournisseur->lire)) {
						if ($value > 0) {
							$sqlWhere .= "sp.fk_soc = ".$value;
						} else {
							$sqlWhere .= "sp.rowid IS NOT NULL";
						}
					} elseif ($filter->property == 'content') {
						$fields = array('p.ref', 'p.label', 'p.barcode');
						if (ExtDirect::checkDolVersion(0, '13.0', '') && $supplierFilter) $fields[] = 'sp.barcode';
						$sqlWhere .= natural_search($fields, $filter->value, 0, 1);
					} elseif ($filter->property == 'photo_size' && !empty($value)) {
						$sqlWhere .= '1 = 1';
						$photoSize = $value;
					} else {
						$sqlWhere .= '1 = 1';
					}
				}
				if ($key < ($filterSize-1)) {
					if ($filter->property == $param->filter[$key+1]->property) $sqlWhere .= ' OR ';
					else $sqlWhere .= ') AND (';
				}
			}
			$sqlWhere .= ')';
		}
		$sqlOrder = " ORDER BY ";
		if (isset($param->sort)) {
			$sorterSize = count($param->sort);
			foreach ($param->sort as $key => $sort) {
				if (!empty($sort->property)) {
					if ($sort->property == 'warehouse_id') {
						$sortfield = 'ps.fk_entrepot';
					} elseif ($sort->property == 'categorie') {
						$sortfield = 'c.label';
					} else {
						$sortfield = $sort->property;
					}
					$sqlOrder .= $sortfield . ' ' . $sort->direction;
					if ($key < ($sorterSize-1)) {
						$sqlOrder .= ",";
					}
				}
			}
		} else {
			$sqlOrder .= "p.ref ASC";
		}

		if ($limit) {
			$sqlLimit = $this->db->plimit($limit, $start);
		}

		if ($includeTotal) {
			$sqlTotal = 'SELECT COUNT(*) as total'.$sqlFrom.$sqlWhere;
			$resql=$this->db->query($sqlTotal);

			if ($resql) {
				$obj = $this->db->fetch_object($resql);
				$total = $obj->total;
				$this->db->free($resql);
			} else {
				$error="Error ".$this->db->lasterror();
				dol_syslog(get_class($this)."::readProductList ".$error, LOG_ERR);
				return SQLERROR;
			}
		}

		$sql = $sqlFields.$sqlFrom.$sqlWhere.$sqlOrder.$sqlLimit;

		$resql=$this->db->query($sql);

		if ($resql) {
			$num=$this->db->num_rows($resql);
			for ($i = 0;$i < $num; $i++) {
				$obj = $this->db->fetch_object($resql);
				$row = new stdClass;

				$row->product_id= (int) $obj->id;
				$row->ref       = $obj->ref;
				$row->label     = $obj->label;
				$row->barcode   = $obj->barcode;
				if (empty($obj->fk_entrepot)) {
					 $row->warehouse_id = ExtDirectFormProduct::ALLWAREHOUSE_ID;
				} else {
					$row->warehouse_id = $obj->fk_entrepot;
				}
				$row->warehouse = $obj->warehouse;
				if (empty($obj->stock)) {
					$row->stock = 0;
				} else {
					$row->stock = (float) $obj->stock;
				}
				if (!empty($conf->global->STOCK_SHOW_VIRTUAL_STOCK_IN_PRODUCTS_COMBO) && !$warehouseFilter) {
					$product = new Product($this->db);
					$product->fetch($row->product_id);
					$product->load_stock('warehouseopen, warehouseinternal');
					$row->is_virtual_stock = true;
					$row->total_stock = (float) $product->stock_theorique;
				} else {
					$row->is_virtual_stock = false;
					$row->total_stock = (float) $obj->total_stock;
				}
				$row->seuil_stock_alerte = $obj->seuil_stock_alerte;
				$row->has_photo = 0;
				if (!empty($photoSize)) {
					$this->fetchPhoto($row, $photoSize, 0, $obj);
				}

				if ($supplierFilter) {
					$row->ref_supplier = $obj->ref_supplier;
					$row->ref_supplier_id = $obj->ref_supplier_id;
					$row->qty_supplier = $obj->qty_supplier;
					$row->reduction_percent_supplier = $obj->reduction_percent_supplier;
					if ($warehouseFilter) {
						$row->id = $obj->id.'_'.$obj->fk_entrepot.'_'.$obj->ref_supplier_id;
					} else {
						$row->id = $obj->id.'_'.$obj->ref_supplier_id;
					}
					$row->price = $obj->price_supplier;
					if (ExtDirect::checkDolVersion(0, '5.0', '')) $row->supplier_reputation = $obj->supplier_reputation;
					if (ExtDirect::checkDolVersion(0, '13.0', '') && !empty($obj->supplier_barcode)) $row->barcode = $obj->supplier_barcode;
					$row->qty_ordered = $obj->ordered - $obj->dispatched;
				} else {
					if ($warehouseFilter) {
						$row->id    = $obj->id.'_'.$obj->fk_entrepot;
					} else {
						$row->id    = $obj->id;
					}
					$row->price_ttc = $obj->price_ttc;
					$row->price     = $obj->price;
					if (! empty($conf->global->PRODUIT_MULTIPRICES) && ! empty($obj->multi_price)) {
						$row->price_ttc = $obj->multi_price_ttc;
						$row->price     = $obj->multi_price;
					} elseif (! empty($conf->global->PRODUIT_CUSTOMER_PRICES) && ! empty($obj->customer_price)) {
						$row->price_ttc = $obj->customer_price_ttc;
						$row->price     = $obj->customer_price;
					}
					$row->qty_ordered = 0;
				}
				array_push($data, $row);
			}
			$this->db->free($resql);
			if ($includeTotal) {
				$result->total = $total;
				$result->data = $data;
				return $result;
			} else {
				return $data;
			}
		} else {
			$error="Error ".$this->db->lasterror();
			dol_syslog(get_class($this)."::readProductList ".$error, LOG_ERR);
			return SQLERROR;
		}
	}

	/**
	 * public method to read a list of productbatches of a product
	 *
	 * @param stdClass $param   required property filter to filter on:
	 *                          warehouse_id
	 *                          product_id
	 *
	 *                          property sort with properties field names and directions:
	 *                          property limit for paging with sql LIMIT and START values
	 *
	 * @return     stdClass result data or ERROR
	 */
	public function readProductBatchList(stdClass $param)
	{
		global $conf,$langs;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->produit->lire)) return PERMISSIONERROR;

		$results = array();
		$row = new stdClass;
		$id = null;
		$warehouseId = null;
		$includeNoBatch = false;

		if (isset($param->filter)) {
			foreach ($param->filter as $filter) {
				if ($filter->property == 'product_id') $id=$filter->value;
				elseif ($filter->property == 'warehouse_id') $warehouseId=$filter->value;
				elseif ($filter->property == 'include_no_batch') $includeNoBatch=$filter->value;
			}
		}

		if (empty($conf->productbatch->enabled) || empty($id) || !isset($warehouseId)) return PARAMETERERROR;

		$this->id = $id;
		$res = $this->load_stock('novirtual, warehouseopen, warehouseinternal, nobatch');
		if ($res < 0) return ExtDirect::getDolError($res, $this->errors, $this->error);

		if ($warehouseId == ExtDirectFormProduct::ALLWAREHOUSE_ID) {
			require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
			$formProduct = new FormProduct($this->db);
			$formProduct->loadWarehouses($id, '', 'warehouseopen, warehouseinternal', true, array(), 0);
			if (count($formProduct->cache_warehouses) > 0) {
				foreach ($formProduct->cache_warehouses as $warehouseId => $warehouse) {
					if ($includeNoBatch) {
						$row = new stdClass;
						$row->id = $id.'_'.sprintf("%09d", $warehouseId);
						$row->product_id = $id;
						$row->batch_id = 0;
						$row->batch = $langs->transnoentities('BatchDefaultNumber');
						if (!empty($this->stock_warehouse[$warehouseId]->real)) {
							$defaultStock = (float) $this->stock_warehouse[$warehouseId]->real;
						} elseif (empty($warehouse['parent_id'])) {
							$defaultStock = 1; // only allow add first batch to parent warehouse to avoid too many choises
						} else {
							$defaultStock = 0;
						}
						$row->stock_reel =$defaultStock;
						$row->warehouse_id = $warehouseId;
					}
					$res = $this->fetchBatches($results, $row, $this->id, $warehouseId, $this->stock_warehouse[$warehouseId]->id, $includeNoBatch);
					if ($res < 0) return $res;
				}
			} elseif ($includeNoBatch) {
				$formProduct = new ExtDirectFormProduct($this->db);
				$warehouseId = (!empty($conf->global->MAIN_DEFAULT_WAREHOUSE) ? $conf->global->MAIN_DEFAULT_WAREHOUSE : $formProduct->getFirstWarehouseId());
				$row = new stdClass;
				$row->id = $id.'_'.sprintf("%09d", $warehouseId);
				$row->product_id = $id;
				$row->batch_id = 0;
				$row->batch = $langs->transnoentities('BatchDefaultNumber');
				$row->stock_reel = 1;
				$row->warehouse_id = $warehouseId;
				array_push($results, $row);
			}
		} else {
			if ($includeNoBatch) {
				$row->id = $id.'_'.sprintf("%09d", $warehouseId);
				$row->product_id = $id;
				$row->batch_id = 0;
				$row->batch = $langs->transnoentities('BatchDefaultNumber');
				$row->stock_reel = !empty($this->stock_warehouse[$warehouseId]->real) ? (float) $this->stock_warehouse[$warehouseId]->real : 1;
				$row->warehouse_id = $warehouseId;
			}
			$res = $this->fetchBatches($results, $row, $this->id, $warehouseId, $this->stock_warehouse[$warehouseId]->id, $includeNoBatch);
			if ($res < 0) return $res;
		}
		if (isset($param->sort)) {
			// remove technical id from sort
			foreach ($param->sort as $key => $sort) {
				if ($sort->property == 'id') unset($param->sort[$key]);
			}
			$results = ExtDirect::resultSort($results, $param->sort);
		}
		return $results;
	}

	/**
	 * private method to copy fields into dolibarr object
	 *
	 * @param stdclass $param object with fields
	 * @return boolean $diff true if changed
	 */
	private function prepareFields($param)
	{
		$diff = false; // difference flag, set to true if a param element diff detected
		$diff = ExtDirect::prepareField($diff, $param, $this, 'ref', 'ref');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'label', 'label');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'description', 'description');
		//! Type 0 for regular product, 1 for service (Advanced feature: 2 for assembly kit, 3 for stock kit)
		$diff = ExtDirect::prepareField($diff, $param, $this, 'type', 'type');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'note', 'note');
		(isset($this->note) ? null : ($this->note = '')); // create new product, set note to ''
		//! Stock alert
		$diff = ExtDirect::prepareField($diff, $param, $this, 'seuil_stock_alerte', 'seuil_stock_alerte');
		//! Duree de validite du service
		$diff = ExtDirect::prepareField($diff, $param, $this, 'duration_value', 'duration_value');
		//! Unite de duree
		$diff = ExtDirect::prepareField($diff, $param, $this, 'duration_unit', 'duration_unit');
		// Statut indique si le produit est en vente '1' ou non '0'
		$diff = ExtDirect::prepareField($diff, $param, $this, 'tosell', 'status');
		// Status indicate whether the product is available for purchase '1' or not '0'
		$diff = ExtDirect::prepareField($diff, $param, $this, 'tobuy', 'status_buy');
		// Statut indique si le produit est un produit fini '1' ou une matiere premiere '0'
		$diff = ExtDirect::prepareField($diff, $param, $this, 'finished', 'finished');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'customcode', 'customcode');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'country_id', 'country_id');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'country_code', 'country_code');
		//! Unites de mesure
		$diff = ExtDirect::prepareField($diff, $param, $this, 'weight', 'weight');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'weight_units', 'weight_units');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'product_length', 'length');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'length_units', 'length_units');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'width', 'width');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'width_units', 'width_units');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'height', 'height');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'height_units', 'height_units');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'surface', 'surface');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'surface_units', 'surface_units');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'volume', 'volume');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'volume_units', 'volume_units');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'accountancy_code_buy', 'accountancy_code_buy');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'accountancy_code_sell', 'accountancy_code_sell');
		// no links to offers in this version
		// no multilangs in this version
		//! Canevas a utiliser si le produit n'est pas un produit generique
		$diff = ExtDirect::prepareField($diff, $param, $this, 'canvas', 'canvas');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'entity', 'entity');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'import_key', 'import_key');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'date_creation', 'date_creation');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'date_modification', 'date_modification');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'unit_id', 'fk_unit');
		// has batch
		$diff = ExtDirect::prepareField($diff, $param, $this, 'has_batch', 'status_batch');
		// cost price is set with product update
		if (!empty($this->_user->rights->fournisseur->lire)) {
			$diff = ExtDirect::prepareField($diff, $param, $this, 'cost_price', 'cost_price');
		}
		$diff = ExtDirect::prepareField($diff, $param, $this, 'default_warehouse_id', 'fk_default_warehouse');
		return $diff;
	}

	/**
	 * private method to copy fields into dolibarr object
	 *
	 * @param stdclass $param object with fields
	 * @return boolean $diff true if changed
	 */
	private function prepareFieldsBarcode($param)
	{
		$diff = false; // difference flag, set to true if a param element diff detected
		//! barcode
		$diff = ExtDirect::prepareField($diff, $param, $this, 'barcode', 'barcode');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'barcode_type', 'barcode_type');
		return $diff;
	}

	/**
	 * private method to copy fields into dolibarr object
	 *
	 * @param stdclass $param object with fields
	 * @param int $multiprices_index multiprice level
	 * @return boolean $diff true if changed
	 */
	private function prepareFieldsSellPrice($param, $multiprices_index = null)
	{
		$diff = false; // difference flag, set to true if a param element diff detected
		if ($multiprices_index > 0) {
			//! Selling price
			$diff = ExtDirect::prepareField($diff, $param, $this, 'price', 'multiprices', null, null, $multiprices_index);
			$diff = ExtDirect::prepareField($diff, $param, $this, 'price_ttc', 'multiprices_ttc', null, null, $multiprices_index);
			//! Default VAT rate of product
			$diff = ExtDirect::prepareField($diff, $param, $this, 'tva_tx', 'multiprices_tva_tx', null, null, $multiprices_index);
			//! Base price ('TTC' for price including tax or 'HT' for net price)
			$diff = ExtDirect::prepareField($diff, $param, $this, 'price_base_type', 'multiprices_base_type', null, null, $multiprices_index);
			$diff = ExtDirect::prepareField($diff, $param, $this, 'price_min', 'multiprices_min', null, null, $multiprices_index);
			$diff = ExtDirect::prepareField($diff, $param, $this, 'price_min_ttc', 'multiprices_min_ttc', null, null, $multiprices_index);
		} else {
			//! Selling price
			$diff = ExtDirect::prepareField($diff, $param, $this, 'price', 'price');
			$diff = ExtDirect::prepareField($diff, $param, $this, 'price_ttc', 'price_ttc');
			//! Default VAT rate of product
			$diff = ExtDirect::prepareField($diff, $param, $this, 'tva_tx', 'tva_tx');
			//! Base price ('TTC' for price including tax or 'HT' for net price)
			$diff = ExtDirect::prepareField($diff, $param, $this, 'price_base_type', 'price_base_type');
			$diff = ExtDirect::prepareField($diff, $param, $this, 'price_min', 'price_min');
			$diff = ExtDirect::prepareField($diff, $param, $this, 'price_min_ttc', 'price_min_ttc');
		}
		//! French VAT NPR (0 or 1)
		$diff = ExtDirect::prepareField($diff, $param, $this, 'tva_npr', 'tva_npr');
		//! local taxes
		$diff = ExtDirect::prepareField($diff, $param, $this, 'localtax1_tx', 'localtax1_tx');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'localtax2_tx', 'localtax2_tx');
		return $diff;
	}

	/**
	 * private method to copy fields into dolibarr object
	 *
	 * @param stdclass $param object with fields
	 * @return boolean $diff true if changed
	 */
	private function prepareFieldsBuyPrice($param)
	{
		$diff = false; // difference flag, set to true if a param element diff detected
		$diff = ExtDirect::prepareField($diff, $param, $this, 'ref_supplier', 'ref_supplier');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'ref_supplier_id', 'product_fourn_price_id');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'price_supplier', 'fourn_price');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'qty_supplier', 'fourn_qty');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'reduction_percent_supplier', 'fourn_remise_percent');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'reduction_supplier', 'fourn_remise');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'pu_supplier', 'fourn_unitprice');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'vat_supplier', 'fourn_tva_tx');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'supplier_id', 'fourn_id');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'desiredstock', 'desiredstock');
		$diff = ExtDirect::prepareField($diff, $param, $this, 'supplier_reputation', 'supplier_reputation');
		return $diff;
	}

	/**
	 * private method to copy batch fields into dolibarr object
	 *
	 * @param stdclass		$param object with fields
	 * @param Productlot 	$productLot Productlot object
	 * @param Productbatch 	$productBatch Productbatch object
	 * @return boolean $diff true if changed
	 */
	private function prepareFieldsBatch($param, $productLot, $productBatch)
	{
		$diff = false; // difference flag, set to true if a param element diff detected
		$diff = ExtDirect::prepareField($diff, $param, $productBatch, 'batch', 'batch');
		$diff = ExtDirect::prepareField($diff, $param, $productBatch, 'batch_info', 'import_key');
		$diff = ExtDirect::prepareField($diff, $param, $productBatch, 'batch_qty', 'qty');
		$diff = ExtDirect::prepareField($diff, $param, $productLot, 'batch', 'batch');
		$diff = ExtDirect::prepareField($diff, $param, $productLot, 'date_sellby', 'sellby');
		$diff = ExtDirect::prepareField($diff, $param, $productLot, 'date_eatby', 'eatby');
		return $diff;
	}

	/**
	 * private method to fetch id from given barcode, search in barcode and ref field
	 *
	 * @param string $barcode barcode to fetch id from
	 * @param string $table table to search 'product' or 'product_fournisseur_price'
	 * @return array $id rowid of product and rowid of supplier product (supplier product only for dolibarr 10+)
	 */
	public function fetchIdFromBarcode($barcode, $table = 'product')
	{
		global $conf;

		$id = array('product'=>0, 'supplier_product'=>0);
		dol_syslog(get_class($this)."::fetch ".$table." id from barcode=".$barcode);
		$formProduct = new ExtDirectFormProduct($this->db);
		$barcodeTypeData = $formProduct->readBarcodeType(new stdClass);
		foreach ($barcodeTypeData as $barcodeType) {
			$barcodeTypes[$barcodeType->code] = $barcodeType->id;
		}
		if ($table == 'product_fournisseur_price') {
			$fkProductField = ', fk_product';
			$refField = 'ref_fourn';
		} else {
			$fkProductField = '';
			$refField = 'ref';
		}
		$couldBeEAN = false;
		if (!empty($conf->global->PRODUIT_DEFAULT_BARCODE_TYPE)) {
			$fk_barcode_type = $conf->global->PRODUIT_DEFAULT_BARCODE_TYPE;
		} else {
			$fk_barcode_type = null;
		}
		if (strlen($barcode) == 13) {
			$this->barcode_type = $barcodeTypes['EAN13'];
			$couldBeEAN = true;
		} elseif (strlen($barcode) == 12) {
			$this->barcode_type = $barcodeTypes['UPC'];
			$couldBeEAN = true;
		} elseif (strlen($barcode) == 8) {
			$this->barcode_type = $barcodeTypes['EAN8'];
			$couldBeEAN = true;
		}
		if ($couldBeEAN) {
			$this->barcode = substr($barcode, 0, -1);
			if ($this->fetchBarcodeWithChecksum($this) == $barcode) {
				$couldBeEAN = true;
			} else {
				$couldBeEAN = false;
			}
		}

		if ($couldBeEAN) {
			$sql = "SELECT rowid, fk_barcode_type".$fkProductField." FROM ".MAIN_DB_PREFIX.$table." WHERE barcode ='".$this->db->escape($barcode)."' OR barcode ='".$this->db->escape(substr($barcode, 0, -1))."' OR " . $refField . " = '".$this->db->escape($barcode)."'";
		} else {
			$sql = "SELECT rowid, fk_barcode_type".$fkProductField." FROM ".MAIN_DB_PREFIX.$table." WHERE barcode ='".$this->db->escape($barcode)."' OR " . $refField . " = '".$this->db->escape($barcode)."'";
		}
		$resql = $this->db->query($sql);
		if ( $resql ) {
			if ($this->db->num_rows($resql) > 0) {
				$obj = $this->db->fetch_object($resql);
				if ($obj->fk_barcode_type) {
					$fk_barcode_type = $obj->fk_barcode_type;
				}
				if ($fk_barcode_type == $barcodeTypes['EAN8'] || $fk_barcode_type == $barcodeTypes['EAN13'] || $fk_barcode_type == $barcodeTypes['UPC'] || !$couldBeEAN) { // EAN13 || EAN8 || UPC || for shure not EAN
					if ($fkProductField) {
						$id['product'] = (int) $obj->fk_product;
						$id['supplier_product'] = (int) $obj->rowid;
					} else {
						$id['product'] = (int) $obj->rowid;
					}
				} elseif ($couldBeEAN) {
					// re-search if len of EAN/UPC but not EAN/UPC
					$sql = "SELECT rowid".$fkProductField." FROM ".MAIN_DB_PREFIX.$table." WHERE barcode ='".$barcode."'";
					$resql2 = $this->db->query($sql);
					if ( $resql2 ) {
						if ($this->db->num_rows($resql2) > 0) {
							$obj = $this->db->fetch_object($resql2);
							if ($fkProductField) {
								$id['product'] = (int) $obj->fk_product;
								$id['supplier_product'] = (int) $obj->rowid;
							} else {
								$id['product'] = (int) $obj->rowid;
							}
						}
						$this->db->free($resql2);
					}
				}
			}
			$this->db->free($resql);
		}
		return $id;
	}

	/**
	 * public method to fetch batch results
	 *
	 * @param array $results array to store batches
	 * @param object $row object with product data to add to results
	 * @param int $id object id
	 * @param int $warehouseId warehouse id
	 * @param int $productStockId produc stock id
	 * @param bool $includeNoBatch include empty batch with missing qty
	 * @param int $batchId only get a specific batch
	 * @param string $batchValue value of field batch
	 * @param string $photoFormat photo format for sub product
	 *
	 * @return int < 0 if error > 0 if OK
	 */
	public function fetchBatches(&$results, $row, $id, $warehouseId, $productStockId, $includeNoBatch = false, $batchId = null, $batchValue = '', $photoFormat = '')
	{
		$batches = array();
		$batchesQty = 0;
		$stockQty = isset($row->stock_reel) ? $row->stock_reel : 0;
		$product_id = isset($row->product_id) ? $row->product_id : $this->id;
		$undefinedBatch = clone $row;
		$num = 0;

		if (!empty($productStockId) && ($batches = Productbatch::findAll($this->db, $productStockId, 1, $product_id)) < 0 ) return $batches;

		if (!empty($batches)) {
			foreach ($batches as $batch) {
				/** @var Productbatch $batch */
				$row->product_id = $this->id;
				$row->warehouse_id = $warehouseId;
				$row->batch_id = $batch->id;
				$row->stock_id = $batch->fk_product_stock;
				$productLot = new Productlot($this->db);
				$productLot->fetch(0, $product_id, $batch->batch);
				$row->sellby = $productLot->sellby;
				$row->eatby = $productLot->eatby;
				$row->batch = $batch->batch;
				$row->stock_reel= (float) $batch->qty;
				$row->stock = (float) $batch->qty;
				$row->batch_info = $batch->import_key;
				if (empty($batchId)) {
					if (empty($batchValue)) {
						$row->id = $id.'_'.sprintf("%09d", $batch->id);
						$num++;
						array_push($results, clone $row);
					} elseif (($batchValue == $batch->batch)) {
						$row->id = $id;
						$num++;
						array_push($results, clone $row);
						break;
					}
				} elseif ($batchId == $batch->id) {
					$row->id = $id;
					$num++;
					array_push($results, clone $row);
					break;
				}
				$batchesQty += $batch->qty;
			}
			if (empty($batchId) && !empty($batchValue) && $this->status_batch == 2) {
				// new serial number
				$row->id = $id;
				$row->batch_id = 0;
				$row->stock_id = 0;
				$row->sellby = null;
				$row->eatby = null;
				$row->batch = $batchValue;
				$row->stock_reel= 0;
				$row->stock = 0;
				$row->batch_info = null;
				$row->qty_toreceive = 1;
				$num++;
				array_push($results, clone $row);
			}
		} elseif (isset($row->id) && !empty($productStockId)) {
			// no batch
			$num++;
			$row->is_sub_product = false;
			array_push($results, clone $row);
			$this->fetchSubProducts($results, $row, $photoFormat);
			$this->fetch($product_id);
		} elseif (empty($batchId) && !empty($batchValue) && $this->status_batch == 2) {
			// new first serial number
			$row->id = $id;
			$row->batch_id = 0;
			$row->stock_id = 0;
			$row->sellby = null;
			$row->eatby = null;
			$row->batch = $batchValue;
			$row->stock_reel= 0;
			$row->stock = 0;
			$row->batch_info = null;
			$row->qty_toreceive = 1;
			$num++;
			array_push($results, clone $row);
		}

		if ($includeNoBatch && (!empty($stockQty) || !empty($productStockId)) && isset($row->id) && isset($row->batch_id)) {
			// add undefined batch with non batched stock for adding batches
			$undefinedBatch->stock_reel = price2num($stockQty - $batchesQty, 5);
			$num++;
			array_push($results, $undefinedBatch);
		}
		return $num;
	}

	/**
	 * private method to fetch total qty of all batches from given product stock
	 *
	 * @param integer $fk_product_stock product stock id
	 * @return integer $batchesQty batchse qty
	 */
	private function fetchBatchesQty($fk_product_stock)
	{
		$batches = array();
		$batchesQty = 0;
		if (($batches = Productbatch::findAll($this->db, $fk_product_stock)) < 0 ) return $batches;
		if (!empty($batches)) {
			foreach ($batches as $batch) {
				$batchesQty += $batch->qty;
			}
		}
		return $batchesQty;
	}

	/**
	 * public method to fetch product photos
	 *
	 * @param object $row object with product data to add to results
	 * @param string $format size of foto 'mini', 'small' or 'full'
	 * @param int $num num of photo to return
	 * @param object $productObj product object
	 * @return void
	 */
	public function fetchPhoto(&$row, $format = '', $num = 0, $productObj = null)
	{
		// get photo
		global $conf;

		$row->has_photo = 0;
		$row->photo_size = '';
		if (empty($productObj)) $productObj=$this;
		if (! empty($conf->global->PRODUCT_USE_OLD_PATH_FOR_PHOTO)) {
			$pdir = get_exdir($productObj->id, 2, 0, 0, $productObj, 'product') . $productObj->id ."/photos/";
		} else {
			$pdir = $productObj->ref.'/';
		}
		$dir = $conf->product->multidir_output[(int) $productObj->entity] . '/'. $pdir;
		$relativedir = '';
		if ($dir) {
			$relativedir = preg_replace('/^'.preg_quote(DOL_DATA_ROOT, '/').'/', '', $dir);
			$relativedir = preg_replace('/^[\\/]/', '', $relativedir);
			$relativedir = preg_replace('/[\\/]$/', '', $relativedir);
		}

		$photos = dol_dir_list($dir, "files", 0, '', '(\.meta|_preview.*\.png)$', 'position_name', SORT_ASC, 1);

		if (is_array($photos) && !empty($photos)) {
			if (function_exists('completeFileArrayWithDatabaseInfo')) {
				completeFileArrayWithDatabaseInfo($photos, $relativedir);
				$photos = dol_sort_array($photos, 'position_name');
			}
			$row->has_photo = count($photos);
			$row->photo_size = $format;
			$photoFile = $photos[$num]['name'];
			$photo_parts = pathinfo($photoFile);
			if ($format == 'mini') {
				$filename=$dir.'thumbs/'.$photo_parts['filename'].'_mini.'.$photo_parts['extension'];
			} elseif ($format == 'small') {
				$filename=$dir.'thumbs/'.$photo_parts['filename'].'_small.'.$photo_parts['extension'];
				if (!file_exists($filename)) {
					// no small thumb available, return original size for small pics (< 20KB) else return mini size
					if ($photos[$num]['size'] > 20480) {
						$filename=$dir.'thumbs/'.$photo_parts['filename'].'_mini.'.$photo_parts['extension'];
						$row->photo_size = 'mini';
					} else {
						$filename=$dir.$photoFile;
						$row->photo_size = '';
					}
				}
			} else {
				$filename=$dir.$photoFile;
			}
			if (file_exists($filename)) {
				$imgData = base64_encode(file_get_contents($filename));
				$row->photo = 'data: '.dol_mimetype($filename).';base64,'.$imgData;
			} else {
				$row->has_photo = 0;
				$row->photo_size = '';
			}
		}
	}

	/**
	 * public method to add base64 jpeg photo
	 *
	 * @param string    $base64JpegUrl  base64 encoded jpeg data
	 * @param int       $index          index number for multiple photo
	 *
	 * @return > 0 photo accepted < 0 photo not accepted
	 */
	public function addBase64Jpeg($base64JpegUrl, $index = 1)
	{
		// get photo
		global $conf, $maxwidthsmall, $maxheightsmall, $maxwidthmini, $maxheightmini, $quality;

		$maxwidthsmall = 270;
		$maxheightsmall = 150;
		$maxwidthmini = 128;
		$maxheightmini = 72;
		$quality = 80;

		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
		// TODO add file upload permission to Dolibarr
		if (empty($conf->global->MAIN_UPLOAD_DOC)) {
			$this->error="ErrorForbidden";
			return -4;
		}

		if (empty($conf->product->multidir_output[(int) $this->entity])) {
			$dir = DOL_DATA_ROOT.'/produit'; // for unit testing
		} else {
			$dir = $conf->product->multidir_output[(int) $this->entity];
		}

		$tdir = $dir. '/temp';

		if (! empty($conf->global->PRODUCT_USE_OLD_PATH_FOR_PHOTO)) $dir .= '/'. get_exdir($this->id, 2, 0, 0, $this, 'product') . $this->id ."/photos";
		else $dir .= '/'.dol_sanitizeFileName($this->ref);

		dol_mkdir($tdir);
		dol_mkdir($dir);
		$base64 = explode(',', $base64JpegUrl);
		$imgdata = base64_decode($base64[1]);

		if (substr($imgdata, 0, 3)=="\xff\xd8\xff") { // only jpeg
			$filename = 'ExtDirectUpload'. $this->id . '_' . $index . '.jpg';
			if (is_dir($tdir) && (file_put_contents($tdir.$filename, $imgdata, LOCK_EX) > 0)) {
				if (is_dir($dir)) {
					dol_move($tdir.$filename, $dir.'/'.$filename);
					if (file_exists(dol_osencode($dir.'/'.$filename))) {
						// Cree fichier en taille vignette
						$this->addThumbs($dir.'/'.$filename);
						return 1;
						@rmdir($tdir);
					} else {
						$this->error="ErrorFailToCreateFile";
						return -5;
					}
				} else {
					$this->error="ErrorFailToCreateDir";
					return -1;
				}
			} else {
				$this->error="ErrorFailToCreateFile";
				return -2;
			}
		} else {
			$this->error="ErrorBadImageFormat";
			return -3;
		}
	}

	/**
	 * public method to fetch barcode with checksum from dolibarr generated barcodes, which are stored without checksum
	 *
	 * @param Object $object object containing barcode values
	 *
	 * @return string barcode with checksum
	 */
	public function fetchBarcodeWithChecksum($object)
	{
		$barcodeType = '';
		$barcode = '';

		$formProduct = new ExtDirectFormProduct($this->db);
		$barcodeTypeData = $formProduct->readBarcodeType(new stdClass);
		foreach ($barcodeTypeData as $barcodeType) {
			$barcodeTypes[$barcodeType->id] = $barcodeType->code;
		}

		if (!empty($object->supplier_barcode)) {
			$barcode = $object->supplier_barcode;
			$barcodeType = $barcodeTypes[$object->supplier_fk_barcode_type];
		} else {
			$barcode = $object->barcode;
			$barcodeType = $barcodeTypes[$object->barcode_type];
		}

		if ($barcodeType == 'UPC') {
			// dolibarr UPC is UPCA
			$barcodeType = 'UPCA';
		}

		// if barcode is full ean13 and first char in '0', we strip 0 and return stripped value,
		// because barcode readers interprete ean13 with leading 0 as a UPC code.
		if (substr($barcode, 0, 1) === '0' && $barcodeType == 'EAN13' && strlen($barcode) == 13) {
			$barcodeType = '';
			$barcode = substr($barcode, 1);
		}

		if (in_array($barcodeType, array('EAN8', 'EAN13', 'UPCA')) && !empty($barcode)) {
			include_once TCPDF_PATH.'tcpdf_barcodes_1d.php';
			$barcodeObj = new TCPDFBarcode($barcode, $barcodeType);
			$barcode = $barcodeObj->getBarcodeArray();
			return $barcode['code'];
		} else {
			return $barcode;
		}
	}

	/**
	 * Add subproducts to results
	 *
	 * @param array $results array to store batches
	 * @param object $row object with product data to add to results
	 * @param string $photoFormat photo format for sub product
	 *
	 * @return void
	 */
	public function fetchSubProducts(&$results, $row, $photoFormat = '')
	{
		global $conf;

		if (! empty($conf->global->PRODUIT_SOUSPRODUITS)) {
			$product_id = $this->id;
			$this->get_sousproduits_arbo();
			if (isset($this->sousprods)) {
				$prods_arbo = $this->get_arbo_each_prod($row->qty_asked);
				if (count($prods_arbo) > 0) {
					$rowId = $row->id;
					$rowLabel = $row->label;
					foreach ($prods_arbo as $key => $value) {
						$row->id = $rowId.'_'.$value['id'];
						$row->is_sub_product = true;
						$row->sub_product_parent_id = $product_id;
						$row->product_id = $value['id'];
						$row->ref = $value['ref'];
						$row->product_label = $value['label'];
						$row->label = $rowLabel.' -> '.$value['fullpath'];
						$row->qty_asked = $value['nb_total'];
						$row->stock = $value['stock'];
						$row->has_photo = 0;
						$subProduct = new Product($this->db);
						$subProduct->fetch($value['id']);
						$this->fetchPhoto($row, $photoFormat, 0, $subProduct);
						array_push($results, clone $row);
					}
				}
			}
		}
	}
}
