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
class ExtDirectProduct extends Product
{
    
    private $_user;
    
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
                $this->_user = $user;  //product.class uses global user
                if (isset($this->_user->conf->MAIN_LANG_DEFAULT) && ($this->_user->conf->MAIN_LANG_DEFAULT != 'auto')) {
                    $langs->setDefaultLang($this->_user->conf->MAIN_LANG_DEFAULT);
                }
                // set global $mysoc required for price calculation
                $mysoc = new Societe($db);
                $mysoc->setMysoc($conf);
                $langs->load("products");
                $langs->load("stocks");
                $langs->load("productbatch");
                if (! empty($conf->productbatch->enabled)) $langs->load("productbatch");
                parent::__construct($db);
            }
        }
    }

    /**
     *    Load products from database into memory
     *
     *    @param    stdClass    $param  filter with elements:
     *      id                  Id of product to load
     *      ref                 Reference of product, name
     *      warehouse_id        filter product on a warehouse
     *      multiprices_index   filter product on a multiprice index
     *      barcode             barcode of product 
     *      batch               batch code of product
     *      batch_id            batch rowid of product
     *      ref_supplier        supplier reference code
     *      photo_size          string with foto size 'mini', 'small' or 'full'
     *    @return     stdClass result data or -1
     */
    public function readProduct(stdClass $param)
    {
        global $conf, $mysoc;

        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->produit->lire)) return PERMISSIONERROR;
        if (! empty($conf->productbatch->enabled)) require_once DOL_DOCUMENT_ROOT.'/product/class/productbatch.class.php';
        $results = array();
        $row = new stdClass;
        $id = 0;
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
                else if ($filter->property == 'ref') $ref=$filter->value;
                else if ($filter->property == 'warehouse_id') $warehouse=$filter->value;
                else if ($filter->property == 'multiprices_index' ) $multiprices_index=$filter->value;
                else if ($filter->property == 'barcode' ) $id = $this->fetchIdFromBarcode($filter->value);
                else if ($filter->property == 'batch') $batch = $filter->value;
                else if ($filter->property == 'batch_id') $batchId = $filter->value;
                else if ($filter->property == 'ref_supplier') $refSupplier = $filter->value;
                else if ($filter->property == 'ref_supplier_id') $refSupplierId = $filter->value;
                else if ($filter->property == 'photo_size' && !empty($filter->value)) $photoSize = $filter->value;
                else if ($filter->property == 'customer_id' && !empty($filter->value)) $socid = $filter->value;
            }
        }
        
        if (($id > 0) || ($ref != '')) {
            if (($result = $this->fetch($id, $ref, $ref_ext)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
            if ($this->id > 0) {
                $row->id = $this->id ;
                //! Ref
                $row->ref= $this->ref;
                $row->label= $this->label;
                $row->description= $this->description?$this->description:'';
                //! Type 0 for regular product, 1 for service (Advanced feature: 2 for assembly kit, 3 for stock kit)
                $row->type= $this->type;
                $row->note= $this->note;
                //! Selling price
                $row->price= $this->price?$this->price:'';              // Price net
                $row->price_ttc= $this->price_ttc?$this->price_ttc:'';          // Price with tax
                $row->tva_tx = '';
                //! Base price ('TTC' for price including tax or 'HT' for net price)
                $row->price_base_type= $this->price_base_type;
                if (! empty($conf->global->PRODUIT_MULTIPRICES) && isset($multiprices_index)) {
                    //! Arrays for multiprices
                    $row->price=$this->multiprices[$multiprices_index]?$this->multiprices[$multiprices_index]:'';
                    $row->price_ttc=$this->multiprices_ttc[$multiprices_index]?$this->multiprices_ttc[$multiprices_index]:'';
                    if (! empty($conf->global->PRODUIT_MULTIPRICES_USE_VAT_PER_LEVEL) || empty($socid))  // using this option is a bug. kept for backward compatibility
                    {
                        $row->tva_tx=$this->multiprices_tva_tx[$multiprices_index]?$this->multiprices_tva_tx[$multiprices_index]:'';
                    } else {
                        $customer = new Societe($this->db);
                        if (($result = $customer->fetch($socid)) < 0) ExtDirect::getDolError($result, $customer->errors, $customer->error);
                        if ($result > 0 && $mysoc->id > 0) {
                            $row->tva_tx = get_default_tva($mysoc, $customer, $this->id);
                        }
                    }
                    $row->price_base_type=$this->multiprices_base_type[$multiprices_index];
                    $row->multiprices_index=$multiprices_index;
                } else if (! empty($conf->global->PRODUIT_CUSTOMER_PRICES) && ! empty($socid)) { // Price by customer

                    require_once DOL_DOCUMENT_ROOT . '/product/class/productcustomerprice.class.php';

                    $prodcustprice = new Productcustomerprice($this->db);

                    if (($result = $prodcustprice->fetch_all('', '', 0, 0, array('t.fk_product' => $this->id,'t.fk_soc' => $socid))) <= 0) ExtDirect::getDolError($result, $prodcustprice->errors, $prodcustprice->error);
                    if ($result) {
                        if (count($prodcustprice->lines) > 0) {
                            $row->price = price($prodcustprice->lines [0]->price);
                            $row->price_ttc = price($prodcustprice->lines [0]->price_ttc);
                            $row->price_base_type = $prodcustprice->lines [0]->price_base_type;
                            $row->tva_tx = $prodcustprice->lines [0]->tva_tx;
                        }
                    }
                }
                //! Default VAT rate of product, make sure vat is set if multi/customer vat is not set.
                if ($row->tva_tx === '') $row->tva_tx = ($this->tva_tx) ? $this->tva_tx : '';
                $row->price_min= $this->price_min;         // Minimum price net
                $row->price_min_ttc= $this->price_min_ttc;     // Minimum price with tax
                //! French VAT NPR (0 or 1)
                $row->tva_npr= $this->tva_npr;
                //! local taxes
                $row->localtax1_tx = $this->localtax1_tx;
                $row->localtax2_tx = $this->localtax2_tx;
                
                // batch managed product
                if (!empty($conf->productbatch->enabled)) $row->has_batch = $this->status_batch;
                    
                //! Stock
                if (isset($warehouse) && $warehouse != ExtDirectFormProduct::ALLWAREHOUSE_ID) {
                    if (ExtDirect::checkDolVersion() >= 3.5) {
                        $this->load_stock('novirtual, warehouseopen, warehouseinternal');
                    } 
                    if (ExtDirect::checkDolVersion() >= 3.8) {
                        $row->pmp = $this->pmp;
                    } else {
                        $row->pmp = $this->stock_warehouse[$warehouse]->pmp;
                    }
                    
                    if (!empty($conf->productbatch->enabled) && (!empty($batch) || isset($batchId))) {
                        $productBatch = new Productbatch($this->db);
                        if (!empty($batchId)) {
                            $productBatch->fetch($batchId);
                        } else {
                            if (!empty($batch) && !empty($this->stock_warehouse[$warehouse]->id)) {
                                $productBatch->find($this->stock_warehouse[$warehouse]->id,'','',$batch);
                            }                            
                        }    
                        if (!isset($productBatch->id)) {
                            $row->batch_id = 0; // for adding new batch when batch not found
                            $batchesQty = 0;
                            $stockQty = $this->stock_warehouse[$warehouse]->real;
                            if ((! empty($this->stock_warehouse[$warehouse]->id)) && (($batchesQty = $this->fetchBatchesQty($this->stock_warehouse[$warehouse]->id)) < 0 )) return $batchesQty;
                            dol_syslog(get_class($this)."::batchesQty=".$batchesQty." stockQty=".$stockQty);
                            $row->stock_reel = price2num($stockQty - $batchesQty, 5);
                        } else {
                            $row->batch_id = $productBatch->id;
                            $row->sellby = $productBatch->sellby;
                            $row->eatby = $productBatch->eatby;
                            $row->batch = $productBatch->batch;
                            $row->batch_info = $productBatch->import_key;
                            $row->stock_reel = (float) $productBatch->qty;
                        }                        
                    } else {
                        $row->stock_reel= (float) $this->stock_warehouse[$warehouse]->real;
                    }                    
                } else {                    
                    //! Average price value for product entry into stock (PMP)
                    $row->pmp= $this->pmp;
                    if (! empty($conf->productbatch->enabled) && ! empty($batch)) {
                        // fetch qty and warehouse of first batch found
                        $formProduct = new FormProduct($this->db);                        
                        if (ExtDirect::checkDolVersion() >= 3.5) {
                            if (!empty($conf->global->STOCK_SHOW_VIRTUAL_STOCK_IN_PRODUCTS_COMBO)) {
                                $this->load_stock('warehouseopen, warehouseinternal');
                            } else {
                                $this->load_stock('novirtual, warehouseopen, warehouseinternal');
                            }
                        }
                        $warehouses = $formProduct->loadWarehouses($this->id, '', 'warehouseopen, warehouseinternal');
                        foreach ($formProduct->cache_warehouses as $warehouseId => $wh) {
                            if (! empty($this->stock_warehouse[$warehouseId]->id)) {
                                $productBatch = new Productbatch($this->db);
                                $productBatch->find($this->stock_warehouse[$warehouseId]->id,'','',$batch);
                                if (isset($productBatch->id)) {
                                    $row->batch_id = $productBatch->id;
                                    $row->sellby = $productBatch->sellby;
                                    $row->eatby = $productBatch->eatby;
                                    $row->batch = $productBatch->batch;
                                    $row->batch_info = $productBatch->import_key;
                                    $row->warehouse_id = $warehouseId;
                                    $row->stock_reel = $productBatch->qty;
                                    break;
                                }
                            }
                        }
                    } else {
                        if (!empty($conf->global->STOCK_SHOW_VIRTUAL_STOCK_IN_PRODUCTS_COMBO)) {
                            $this->load_stock();
                            $row->stock_reel = (float) $this->stock_theorique;
                        } else {
                            $row->stock_reel = (float) $this->stock_reel;
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
                if (empty($this->barcode_type) && ! empty($conf->global->PRODUIT_DEFAULT_BARCODE_TYPE)) {
                    $row->barcode_type= (int) $conf->global->PRODUIT_DEFAULT_BARCODE_TYPE;
                } else {
                    $row->barcode_type= (int) $this->barcode_type;
                }
                // get barcode with checksum included, same when scanned
                $row->barcode_with_checksum = $this->fetchBarcodeWithChecksum();
                                   
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
                } else if ($refSupplierId > 0) {
                    if (ExtDirect::checkDolVersion(0, '4.0', '')) {
                        $supplierProduct->fetch_product_fournisseur_price($refSupplierId);
                    } else {
                        $supplierProducts = $supplierProduct->list_product_fournisseur_price($this->id);
                        foreach ($supplierProducts as $prodsupplier) {
                            if ($prodsupplier->product_fourn_price_id == $refSupplierId) {
                                $supplierProduct = $prodsupplier;
                            }
                        }
                    }
                } else {
                    $supplierProducts = $supplierProduct->list_product_fournisseur_price($this->id);
                    foreach ($supplierProducts as $prodsupplier) {
                        if ($prodsupplier->fourn_ref == $refSupplier) {
                            $supplierProduct = $prodsupplier;
                        }
                    }
                }
                $row->ref_supplier = $supplierProduct->fourn_ref;
                $row->ref_supplier_id = $supplierProduct->product_fourn_price_id;
                $row->price_supplier = $supplierProduct->fourn_price;
                $row->qty_supplier = $supplierProduct->fourn_qty;
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
    public function readOptionalModel(stdClass $param) 
    {
        if (!isset($this->db)) return CONNECTERROR;
        
        return ExtDirect::readOptionalModel($this);
    }

    /**
     * public method to read product or lot optionals (extra fields) from database
     *
     *    @param    stdClass    $param  filter with elements:
     *      id                  Id of product to load
     *      batch               batch code of product for lot attributes
     *
     *    @return     stdClass result data or -1
     */
    public function readOptionals(stdClass $param)
    {
        global $conf;
        
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->produit->lire)) return PERMISSIONERROR;
        if (! empty($conf->productbatch->enabled) && ExtDirect::checkDolVersion(0, '4.0', '')) require_once DOL_DOCUMENT_ROOT.'/product/stock/class/productlot.class.php';
        $results = array();
        $id = 0;
        $batch = '';
        
        if (isset($param->filter)) {
            foreach ($param->filter as $key => $filter) {
                if ($filter->property == 'id') $id=$filter->value;
                else if ($filter->property == 'batch') $batch = $filter->value;
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
                            $name = substr($key,8); // strip options_
                            $row->id = $index++; // ExtJs needs id to be able to destroy records
                            $row->name = $name;
                            $row->value = $extraFields->showOutputField($name,$value); // display value
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
                            $name = substr($key,8); // strip options_
                            $row->id = $index++;
                            $row->name = $name;
                            $row->value = $extraFields->showOutputField($name,$value);
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
        if (! empty($conf->productbatch->enabled) && ExtDirect::checkDolVersion(0, '4.0', '')) require_once DOL_DOCUMENT_ROOT.'/product/stock/class/productlot.class.php';
        $paramArray = ExtDirect::toArray($params);

        foreach ($paramArray as &$param) {
            if ($param->element == 'productlot') {
                $productLot = new Productlot($this->db);
                if ($productLot->id != $param->object_id && ($result = $productLot->fetch($param->object_id)) < 0) return ExtDirect::getDolError($result, $productLot->errors, $productLot->error);
                $productLot->array_options['options_'.$param->name] = $param->raw_value;
            } else {
                if ($this->id != $param->object_id && ($result = $this->fetch($param->object_id)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
                $this->array_options['options_'.$param->name] = $param->raw_value;
            }
        }
        if (isset($productLot)) {
            if (($result = $productLot->insertExtraFields()) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
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
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->produit->creer)) return PERMISSIONERROR;
        $paramArray = ExtDirect::toArray($params);

        foreach ($paramArray as &$param) {
            if ($param->element == 'productlot') {
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
     *      id                  Id of product to load
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
        $notrigger=0;
        $paramArray = ExtDirect::toArray($params);
        $supplier = new Societe($this->db);
        
        foreach ($paramArray as &$param) {
            // prepare fields
            $this->prepareFields($param);
            if (!empty($this->barcode)) {
            	$this->fetch_barcode();
            }
            // extrafields defaults
            $extraFields = ExtDirect::readOptionalModel($this);
            if (count($extraFields) > 0) {
                foreach ($extraFields as $extraField) {
                    if (!empty ($extraField->default)) {
                        $this->array_options['options_'.$extraField->name] = $extraField->default;
                    }
                }
            }
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
                                    $param->inventorycode
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
                                    $param->inventorycode
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
            if (($result = $this->updatePrice($newSellPrice, $this->price_base_type, $this->_user, $this->tva_tx, $this->price_min, $param->multiprices_index, $this->tva_npr)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
	            
            // supplier fields
            if (!empty($this->fourn_price)) {
                $supplierProduct = new ProductFournisseur($this->db);
                $supplier = new Societe($this->db);
                if (($result = $supplier->fetch($this->fourn_id)) < 0) return ExtDirect::getDolError($result, $supplier->errors, $supplier->error);
                $supplierProduct->id = $this->id;
                if (($result = $this->add_fournisseur($this->_user, $this->fourn_id, $this->fourn_ref, $this->fourn_qty)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
                $supplierProduct->product_fourn_price_id = $this->product_fourn_price_id; // 3.3 comptibility
                if (($result = $supplierProduct->update_buyprice(
                                $this->fourn_qty, 
                                $this->fourn_price, 
                                $this->_user, 
                                $param->price_base_type_supplier, 
                                $supplier, 
                                0, 
                                $this->fourn_ref, 
                                $this->fourn_tva_tx,
                                0,
                                $this->fourn_remise_percent,
                                0,
                                0,
                                0,
                                $this->supplier_reputation
                )) < 0) return ExtDirect::getDolError($result, $supplierProduct->errors, $supplierProduct->error);
            }
            // add photo
            if (!empty($param->has_photo) && !empty($param->photo)) {
                if (($result = $this->addBase64Jpeg($param->photo)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
            }
            $param->id=$this->id;
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
        global $conf;
        
        if (!isset($this->db)) return CONNECTERROR;
        // dolibarr update settings
        $allowmodcodeclient=0;
        $notrigger=false;
        $supplierProducts = array();
        
        $paramArray = ExtDirect::toArray($params);
        foreach ($paramArray as &$param) {
            // prepare fields
            if ($param->id) {
                $id = $param->id;
                if (($result = $this->fetch($id, '', '')) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
                // supplier fields
                $supplierProduct = new ProductFournisseur($this->db);
                if (($supplierProducts = $supplierProduct->list_product_fournisseur_price($this->id)) < 0) return ExtDirect::getDolError($supplierProducts, $supplierProduct->errors, $supplierProduct->error);            
                foreach ($supplierProducts as $prodsupplier) {
                    if ($prodsupplier->product_fourn_price_id == $param->ref_supplier_id){
                        $this->fourn_ref = $prodsupplier->fourn_ref;
                        $this->product_fourn_price_id = $prodsupplier->product_fourn_price_id;
                        $this->fourn_price = $prodsupplier->fourn_price;
                        $this->fourn_qty = $prodsupplier->fourn_qty;
                        $this->fourn_remise_percent = $prodsupplier->fourn_remise_percent;
                        $this->fourn_remise = $prodsupplier->fourn_remise;
                        $this->fourn_unitprice = $prodsupplier->fourn_unitprice;
                        $this->fourn_id = $prodsupplier->fourn_id;
                        if (isset($prodsupplier->fourn_tva_tx)) { // workaround
                            $this->fourn_tva_tx = $prodsupplier->fourn_tva_tx;
                        } else {
                            $this->fourn_tva_tx = $prodsupplier->tva_tx;
                        }
                    }
                }
                $updated = $this->prepareFields($param);
                if (!empty($this->barcode)) {
                    $this->fetch_barcode();
                }
                if ($updated && (!isset($this->_user->rights->produit->creer))) return PERMISSIONERROR;
                if (!empty($param->correct_stock_nbpiece) && !isset($this->_user->rights->stock->mouvement->creer)) return PERMISSIONERROR;
                // verify
                if ($updated && (ExtDirect::checkDolVersion() >= 3.6)) {
                    if (($result = $this->verify()) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
                }                
                // update
                if ($updated) {
                    if (($result = $this->update($id, $this->_user, $notrigger)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
                }                
                // check batch or non batch
                if (! empty($conf->productbatch->enabled) && !empty($param->batch)) {
                    //! Stock
                    $this->load_stock('novirtual, warehouseopen, warehouseinternal');
                    $originalQty = $param->stock_reel;
                    $stockQty = $this->stock_warehouse[$param->warehouse_id]->real;
                    $createNewBatchFromZeroStock = false;
                    $productBatch = new Productbatch($this->db);
                    
                    if (($originalQty < 0) && ($param->batch_id > 0)) {
                        // if negative batch qty, remove negative batch and move remaining positive qty
                        $productBatch->id = $param->batch_id;
                        if (($res = $productBatch->delete($this->_user)) < 0) return ExtDirect::getDolError($res, $productBatch->errors, $productBatch->error);
                        $param->correct_stock_nbpiece = $param->correct_stock_nbpiece + $originalQty;
                    } else if (($param->correct_stock_movement == 1) && ($param->batch_id === 0)) {
                        // correct stock - batch stock diff, only update batch qty not stock qty
                        $batchCorrectQty = $param->correct_stock_nbpiece;
                        $param->correct_stock_nbpiece = 0;
                    } else if ($stockQty > 0) {
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
                                if (($res = $productBatch->create($this->_user,1)) < 0) return ExtDirect::getDolError($res, $productBatch->errors, $productBatch->error);
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
                                            $param->inventorycode
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
                                            $param->inventorycode
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
                                            $param->inventorycode
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
                                            $param->inventorycode
                            );
                        }
                        if ($result < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
                    }
                } else if (!empty($param->correct_stock_nbpiece)) {
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
                                        $param->inventorycode
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
                                        $param->inventorycode
                        );
                    }
                    if ($result < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
                }
                // barcode
                if ($updated && !empty($this->barcode)) {
                    $this->setValueFrom('barcode', $this->barcode);
                    $this->setValueFrom('fk_barcode_type', $this->barcode_type);
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
                                $productBatch->find($this->stock_warehouse[$param->warehouse_id]->id,ExtDirect::dateTimeToDate($param->eatby),ExtDirect::dateTimeToDate($param->sellby),$param->batch);
                            }
                        }                        
                    } else if (!empty($dest)){
                        // update destination batch
                        if (isset($this->stock_warehouse[$dest]->id)) {
                            $productBatch->find($this->stock_warehouse[$dest]->id,ExtDirect::dateTimeToDate($param->eatby),ExtDirect::dateTimeToDate($param->sellby),$param->batch);
                        }
                    }  
                    if (isset($productBatch->id)) {
                        isset($param->batch) ? $productBatch->batch = $param->batch : null;
                        isset($param->sellby) ? $productBatch->sellby = ExtDirect::dateTimeToDate($param->sellby) : null;
                        isset($param->eatby) ? $productBatch->eatby = ExtDirect::dateTimeToDate($param->eatby) : null;
                        isset($param->batch_info) ? $productBatch->import_key = $param->batch_info : null;
                        !empty($batchCorrectQty) ? $productBatch->qty = $productBatch->qty - $batchCorrectQty : null;
                        if (($result = $productBatch->update($this->_user)) < 0) return ExtDirect::getDolError($result, $productBatch->errors, $productBatch->error);
                    }               
                }
                if ($updated) {
                    // price
                    if ($this->price_base_type == 'TTC') {
                        $newSellPrice = $this->price_ttc;
                    } else {
                        $newSellPrice = $this->price;
                    }
                    if (($result = $this->updatePrice($newSellPrice, $this->price_base_type, $this->_user, $this->tva_tx, $this->price_min, $param->multiprices_index, $this->tva_npr)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
                    // supplier fields
                    if (!empty($this->fourn_price) && !empty($this->fourn_ref) && !empty($this->fourn_id)) {
                        $supplierProduct = new ProductFournisseur($this->db);
                        $supplier = new Societe($this->db);
                        if (($result = $supplier->fetch($this->fourn_id)) < 0) return ExtDirect::getDolError($result, $supplier->errors, $supplier->error);
                        $supplierProduct->id = $this->id;
                        if (empty($this->product_fourn_price_id)) {
                            if (($result = $this->add_fournisseur($this->_user, $this->fourn_id, $this->fourn_ref, $this->fourn_qty)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error); 
                        }
                        $supplierProduct->product_fourn_price_id = $this->product_fourn_price_id; // 3.3 comptibility
                        if (($result = $supplierProduct->update_buyprice(
                                        $this->fourn_qty, 
                                        $this->fourn_price, 
                                        $this->_user, 
                                        $param->price_base_type_supplier, 
                                        $supplier, 
                                        0, 
                                        $this->fourn_ref, 
                                        $this->fourn_tva_tx,
                                        0,
                                        $this->fourn_remise_percent,
                                        0,
                                        0,
                                        0,
                                        $this->supplier_reputation
                        )) < 0) return ExtDirect::getDolError($result, $supplierProduct->errors, $supplierProduct->error);
                    }
                }
                
                // add photo
                $photo = new stdClass;
                $this->fetchPhoto($photo);
                if ($param->has_photo > $photo->has_photo && !empty($param->photo) && isset($this->_user->rights->produit->creer)) {
                    if (($result = $this->addBase64Jpeg($param->photo, $param->has_photo)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
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

        foreach ($paramArray as &$param) {
            // prepare fields
            if ($param->id) {
                $id = $param->id;
                $this->id = $id;
                $this->ref = $param->ref;
                // delete product
                if (ExtDirect::checkDolVersion(0, '6.0', '')) {
                    if (($result = $this->delete($this->_user)) <= 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
                } else {
                    if (($result = $this->delete($id)) <= 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
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
     * @param stdClass $param 
     *       property filter to filter on:
     *              warehouse_id
     *              tosell
     *              tobuy
     *              finished
     *              type
     *              categorie_id
     *              content of ref, label and barcode
     *       property sort with properties field names and directions:
     *       property limit for paging with sql LIMIT and START values
     *              
     * @return stdClass result data or -1
     */
    public function readProductList(stdClass $param) 
    {
        global $conf;
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->produit->lire)) return PERMISSIONERROR;
        $result = new stdClass;
        $data = array();

        $filterSize = 0;
        $limit=null;
        $start=0;
        $photoSize = '';
        $multiPriceLevel=1;
        $categorieFilter = false;
        $socid = null;
        $includeTotal = false;
        
        if (isset($param->limit)) {
            $limit = $param->limit;
            $start = $param->start;
        }
        if (isset($param->filter)) {
            $filterSize = count($param->filter);
        }
        if (isset($param->include_total)) {
            $includeTotal = $param->include_total;
        }
        foreach ($param->filter as $key => $filter) {
            if (($filter->property == 'multiprices_index') && ! empty($conf->global->PRODUIT_MULTIPRICES)) $multiPriceLevel=$filter->value;
            elseif (($filter->property == 'customer_id') && ! empty($conf->global->PRODUIT_CUSTOMER_PRICES)) $socid=$filter->value;
            elseif (($filter->property == 'categorie_id')) $categorieFilter=true;
            elseif (($filter->property == 'supplier_id')) $supplierFilter=true;
            elseif (($filter->property == 'warehouse_id')) $warehouseFilter=true;
        }
        
        $sqlFields = 'SELECT p.rowid as id, p.ref, p.label, p.barcode, p.entity, p.seuil_stock_alerte, p.stock as total_stock, p.price, p.price_ttc';
        if ($warehouseFilter) $sqlFields .= ', ps.fk_entrepot, ps.reel as stock';
        if ($supplierFilter) {
            $sqlFields .= ', sp.unitprice as price_supplier, sp.ref_fourn as ref_supplier, sp.rowid as ref_supplier_id, sp.quantity as qty_supplier, sp.remise_percent as reduction_percent_supplier';
            if (ExtDirect::checkDolVersion(0, '5.0', '')) $sqlFields .= ', sp.supplier_reputation';
            $sqlFields .= ', (SELECT SUM(cfdet.qty) FROM '.MAIN_DB_PREFIX.'commande_fournisseurdet as cfdet WHERE cfdet.fk_product = p.rowid) as ordered';
            $sqlFields .= ', (SELECT SUM(cfdis.qty) FROM '.MAIN_DB_PREFIX.'commande_fournisseur_dispatch as cfdis WHERE cfdis.fk_product = p.rowid) as dispatched';
        } else {
            if (! empty($conf->global->PRODUIT_MULTIPRICES)) {
                $sqlFields .= ', pp.price as multi_price, pp.price_ttc as multi_price_ttc';
            }
            if (! empty($conf->global->PRODUIT_CUSTOMER_PRICES) && ! empty($socid)) {
                $sqlFields .= ', pcp.price as customer_price, pcp.price_ttc as customer_price_ttc';
            }
        }
        $sqlFrom = ' FROM '.MAIN_DB_PREFIX.'product as p';
        if ($warehouseFilter) $sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'product_stock as ps ON p.rowid = ps.fk_product';
        if ($categorieFilter) {
            $sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie_product as cp ON p.rowid = cp.fk_product';
            $sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie as c ON c.rowid = cp.fk_categorie';
        }
        if ($supplierFilter) {
            $sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'product_fournisseur_price as sp ON p.rowid = sp.fk_product';
        }
        
        if ($warehouseFilter) {
            $sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'entrepot as e on ps.fk_entrepot = e.rowid';
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
                    $sqlWhere .= '1';                    
                } else {
                    if ($filter->property == 'warehouse_id') {
                        $sqlWhere .= 'ps.fk_entrepot = '.$value;
                    } else if ($filter->property == 'tosell') {
                        $sqlWhere .= "p.tosell = ".$value;
                    } else if ($filter->property == 'tobuy') {
                        $sqlWhere .= "p.tobuy = ".$value;
                    } else if ($filter->property == 'status') { // backward comp
                        $sqlWhere .= "p.tosell = ".$value;
                    } else if ($filter->property == 'status_buy') {  // backward comp
                        $sqlWhere .= "p.tobuy = ".$value;
                    } else if ($filter->property == 'finished') {
                        $sqlWhere .= "p.finished = ".$value;
                    } else if ($filter->property == 'type') {
                        $sqlWhere .= "p.fk_product_type = ".$value;
                    } else if ($filter->property == 'categorie_id') {
                        $sqlWhere .= "c.rowid = ".$value;
                    } else if ($filter->property == 'supplier_id') {
                        if ($value > 0) {
                            $sqlWhere .= "sp.fk_soc = ".$value;
                        } else {
                            $sqlWhere .= "sp.rowid IS NOT NULL";
                        }
                    } else if ($filter->property == 'content') {
                        $contentValue = strtolower($value);
                        $sqlWhere.= " (LOWER(p.ref) like '%".$contentValue."%' OR LOWER(p.label) like '%".$contentValue."%'";
                        $sqlWhere.= " OR LOWER(p.barcode) like '%".$contentValue."%')" ;
                    } else if ($filter->property == 'photo_size' && !empty($value)) {
                        $sqlWhere .= '1';
                        $photoSize = $value;
                    } else {
                        $sqlWhere .= '1';
                    }
                }    
                if ($key < ($filterSize-1)) {
                    if($filter->property == $param->filter[$key+1]->property) $sqlWhere .= ' OR ';
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
                    $sqlOrder .= $sort->property. ' '.$sort->direction;
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
                if (empty($obj->stock)) {
                    $row->stock = 0;
                } else {
                    $row->stock = (float) $obj->stock;
                }
                $row->total_stock = (float) $obj->total_stock;
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
                    $row->id        = $obj->id.'_'.$obj->fk_entrepot.'_'.$obj->ref_supplier_id;
                    $row->price     = $obj->price_supplier;
                    if (ExtDirect::checkDolVersion(0, '5.0', '')) $row->supplier_reputation = $obj->supplier_reputation;
                } else {
                    $row->id        = $obj->id.'_'.$obj->fk_entrepot;
                    $row->price_ttc = $obj->price_ttc;
                    $row->price     = $obj->price;
                    if (! empty($conf->global->PRODUIT_MULTIPRICES) && ! empty($obj->multi_price)) {
                        $row->price_ttc = $obj->multi_price_ttc;
                        $row->price     = $obj->multi_price;
                    } else if (! empty($conf->global->PRODUIT_CUSTOMER_PRICES) && ! empty($obj->customer_price)) {
                        $row->price_ttc = $obj->customer_price_ttc;
                        $row->price     = $obj->customer_price;
                    }
                }
                $row->qty_ordered = $obj->ordered - $obj->dispatched;
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
     * @param stdClass $param
     *       required property filter to filter on:
     *              warehouse_id
     *              product_id
     *              
     *       property sort with properties field names and directions:
     *       property limit for paging with sql LIMIT and START values
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
        $filterSize = 0;
        $id = null;
        $warehouseId = null;
        $includeNoBatch = false;
        $limit=null;
        $start=0;
    
        if (isset($param->limit)) {
            $limit = $param->limit;
            $start = $param->start;
        }
        if (isset($param->filter)) {
            $filterSize = count($param->filter);
            foreach ($param->filter as $key => $filter) {
                if ($filter->property == 'product_id') $id=$filter->value;
                else if ($filter->property == 'warehouse_id') $warehouseId=$filter->value;
                else if ($filter->property == 'include_no_batch') $includeNoBatch=$filter->value;
            }
        }
        
        if (empty($conf->productbatch->enabled) || empty($id) || !isset($warehouseId)) return PARAMETERERROR;
        
        $this->id = $id;
        if (($res = $this->load_stock('novirtual, warehouseopen, warehouseinternal')) < 0) return $res; 
               
        if ($warehouseId == ExtDirectFormProduct::ALLWAREHOUSE_ID) {
            require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
            $formProduct = new FormProduct($this->db);
            $formProduct->loadWarehouses($id, '', 'warehouseopen, warehouseinternal');
            foreach ($formProduct->cache_warehouses as $warehouseId => $warehouse) {
                if ($includeNoBatch) {
                    $row = new stdClass;
                    $row->id = 'X_'.$warehouseId;
                    $row->product_id = $id;
                    $row->batch_id = 0;
                    $row->batch = $langs->transnoentities('BatchDefaultNumber');
                    $row->stock_reel = (float) $this->stock_warehouse[$warehouseId]->real;
                    $row->warehouse_id = $warehouseId;
                }
                if (($res = $this->fetchBatches($results, $row, $this->id, $warehouseId, $this->stock_warehouse[$warehouseId]->id, $includeNoBatch)) < 0) return $res;
            }
        } else {
            if ($includeNoBatch) {
                $row->id = 'X_'.$warehouseId;
                $row->product_id = $id;
                $row->batch_id = 0;
                $row->batch = $langs->transnoentities('BatchDefaultNumber');
                $row->stock_reel = (float) $this->stock_warehouse[$warehouseId]->real;
                $row->warehouse_id = $warehouseId;
            }
            if (($res = $this->fetchBatches($results, $row, $this->id, $warehouseId, $this->stock_warehouse[$warehouseId]->id, $includeNoBatch)) < 0) return $res;
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
        if (ExtDirect::checkDolVersion() >= 3.8) {
            $diff = ExtDirect::prepareField($diff, $param, $this, 'label', 'label'); 
        } else {
            $diff = ExtDirect::prepareField($diff, $param, $this, 'label', 'libelle'); 
        }        
        $diff = ExtDirect::prepareField($diff, $param, $this, 'description', 'description');
        //! Type 0 for regular product, 1 for service (Advanced feature: 2 for assembly kit, 3 for stock kit)
        $diff = ExtDirect::prepareField($diff, $param, $this, 'type', 'type'); 
        $diff = ExtDirect::prepareField($diff, $param, $this, 'note', 'note');
        (isset($this->note) ? null : ($this->note = '')); // create new product, set note to ''
        //! Selling price
        
        $diff = ExtDirect::prepareField($diff, $param, $this, 'price', 'price');
        $diff = ExtDirect::prepareField($diff, $param, $this, 'price_ttc', 'price_ttc');
        //! Default VAT rate of product
        $diff = ExtDirect::prepareField($diff, $param, $this, 'tva_tx', 'tva_tx');
        //! Base price ('TTC' for price including tax or 'HT' for net price)
        $diff = ExtDirect::prepareField($diff, $param, $this, 'price_base_type', 'price_base_type');
            
        $diff = ExtDirect::prepareField($diff, $param, $this, 'price_min', 'price_min');
        $diff = ExtDirect::prepareField($diff, $param, $this, 'price_min_ttc', 'price_min_ttc');
        
        //! French VAT NPR (0 or 1)
        $diff = ExtDirect::prepareField($diff, $param, $this, 'tva_npr', 'tva_npr');
        //! local taxes
        $diff = ExtDirect::prepareField($diff, $param, $this, 'localtax1_tx', 'localtax1_tx');
        $diff = ExtDirect::prepareField($diff, $param, $this, 'localtax2_tx', 'localtax2_tx');
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
        
        //! barcode
        $diff = ExtDirect::prepareField($diff, $param, $this, 'barcode', 'barcode');
        $diff = ExtDirect::prepareField($diff, $param, $this, 'barcode_type', 'barcode_type');
        
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
        //ExtDirect::prepareField($diff, $param, $this, 'productinfo', 'array_options['options_productinfo']');
        $diff = ExtDirect::prepareField($diff, $param, $this, 'ref_supplier', 'fourn_ref');
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
        $diff = ExtDirect::prepareField($diff, $param, $this, 'default_warehouse_id', 'fk_default_warehouse');
        return $diff;
    }
    
    /**
     * private method to fetch id from given barcode, search in barcode and ref field
     *
     * @param string $barcode barcode to fetch id from
     * @return integer $id rowid of product
     */
    private function fetchIdFromBarcode($barcode) 
    {
        $id =0;
        dol_syslog(get_class($this)."::fetch id from barcode=".$barcode);
        $couldBeEAN = false;
        if (strlen($barcode) == 13) {
            $this->barcode_type = 2;
            $couldBeEAN = true;
        } elseif (strlen($barcode) == 12) {
            $this->barcode_type = 3;
            $couldBeEAN = true;
        } elseif (strlen($barcode) == 8) {
            $this->barcode_type = 1;
            $couldBeEAN = true;
        }
        if ($couldBeEAN) {
            $this->barcode = substr($barcode, 0, -1);
            if ($this->fetchBarcodeWithChecksum() == $barcode) {
                $couldBeEAN = true;
            } else {
                $couldBeEAN = false;
            }
        }
        
        if ($couldBeEAN) {
            $sql = "SELECT rowid, fk_barcode_type FROM ".MAIN_DB_PREFIX."product WHERE barcode ='".$barcode."' OR barcode ='".substr($barcode, 0, -1)."' OR ref = '".$barcode."'";
        } else {
            $sql = "SELECT rowid, fk_barcode_type FROM ".MAIN_DB_PREFIX."product WHERE barcode ='".$barcode."' OR ref = '".$barcode."'";
        }
        $resql = $this->db->query($sql);
        if ( $resql ) {
            if ($this->db->num_rows($resql) > 0) {
                $obj = $this->db->fetch_object($resql);
                if (($obj->fk_barcode_type == 2) || ($obj->fk_barcode_type == 1) || !$couldBeEAN) { // EAN13 || EAN8 || for shure not EAN
                    $id = (int) $obj->rowid;
                } else if ($couldBeEAN) {
                    // re-search if len of EAN but not EAN
                    $sql = "SELECT rowid, fk_barcode_type FROM ".MAIN_DB_PREFIX."product WHERE barcode ='".$barcode."'";
                    $resql2 = $this->db->query($sql);
                    if ( $resql2 ) {
                        if ($this->db->num_rows($resql2) > 0) {
                        	$obj = $this->db->fetch_object($resql2);
                            $id = (int) $obj->rowid;
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
     * @param array &$results array to store batches
     * @param object $row object with product data to add to results
     * @param int $id object id
     * @param int $warehouseId warehouse id
     * @param int $productStockId produc stock id
     * @param bool $includeNoBatch include empty batch with missing qty
     * @param int $batchId only get a specific batch
     * 
     * @return int < 0 if error > 0 if OK
     */
    public function fetchBatches(&$results,$row,$id,$warehouseId,$productStockId,$includeNoBatch = false, $batchId = null, $batchValue = '', $photoFormat = '') {
        require_once DOL_DOCUMENT_ROOT.'/product/class/productbatch.class.php';
        $batches = array();
        $batchesQty = 0;
        $stockQty = $row->stock_reel;
        $product_id = $row->product_id;
        $undefinedBatch = clone $row;
        $num = 0;
        
        if (!empty($productStockId) && ($batches = Productbatch::findAll($this->db, $productStockId, 1, $product_id)) < 0 ) return $batches;
        
        if (!empty($batches)) {
            foreach ($batches as $batch) {
                $row->product_id = $this->id;
                $row->warehouse_id = $warehouseId;
                $row->batch_id = $batch->id;
                $row->stock_id = $batch->fk_product_stock;
                $row->sellby = $batch->sellby;
                $row->eatby = $batch->eatby;
                $row->batch = $batch->batch;
                $row->stock_reel= (float) $batch->qty;
                $row->qty_stock = (int) $batch->qty; //deprecated
                $row->stock = (float) $batch->qty;
                $row->batch_info = $batch->import_key;
                if (empty($batchId)) {
                    if (empty($batchValue)) {
                        $row->id = $id.'_'.$batch->id;
                        $num++;
                        array_push($results, clone $row);
                    } else if (($batchValue == $batch->batch)) {
                        $row->id = $id;
                        $num++;
                        array_push($results, clone $row);
                    }
                } else if ($batchId == $batch->id) {
                	$row->id = $id;
                    $num++;
                    array_push($results, clone $row);
                }
                $batchesQty += $batch->qty;
            }
        } else if(isset($row->id) && !empty($productStockId)) {
            // no batch
            $num++;
            $row->is_sub_product = false;
            array_push($results, clone $row);
            $this->fetchSubProducts($results, $row, $photoFormat);
            $this->fetch($product_id);
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
    private function fetchBatchesQty($fk_product_stock) {
        require_once DOL_DOCUMENT_ROOT.'/product/class/productbatch.class.php';
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
     * @param object &$row object with product data to add to results
     * @param string $format size of foto 'mini', 'small' or 'full'
     * @param int $num num of photo to return
     * @param object $productObj product object
     * @return void
     */
    public function fetchPhoto(&$row,$format='',$num=0, $productObj=null) {
        // get photo
        global $conf;
        
        $maxNum = 0;
        if (empty($productObj)) $productObj=$this;
        if (! empty($conf->global->PRODUCT_USE_OLD_PATH_FOR_PHOTO) || (ExtDirect::checkDolVersion(0,'','3.6')))
        {
            if (ExtDirect::checkDolVersion(0,'','3.7')) {
                $pdir = get_exdir($productObj->id, 2) . $productObj->id ."/photos/";
            } else {
                $pdir = get_exdir($productObj->id, 2, 0, 0, null, '') . $productObj->id ."/photos/";
            }
        }
        else
        {
            $pdir = $productObj->ref.'/';
        }
        $dir = $conf->product->multidir_output[(int) $productObj->entity] . '/'. $pdir;
        
        $photos = $this->liste_photos($dir,$maxNum);
        
        if (is_array($photos) && !empty($photos)) {
            $row->has_photo = count($photos);
            $row->photo_size = $format;
            $photoFile = $photos[$num]['photo'];
            $photo_parts = pathinfo($photoFile);
            if ($format == 'mini') {
                if (ExtDirect::checkDolVersion() <= 3.6) {
                    $filename=$dir.'thumbs/'.$photo_parts['filename'].'_small.'.$photo_parts['extension'];
                } else {
                    $filename=$dir.'thumbs/'.$photo_parts['filename'].'_mini.'.$photo_parts['extension'];
                }
            } else if ($format == 'small') {
                $filename=$dir.'thumbs/'.$photo_parts['filename'].'_small.'.$photo_parts['extension'];
                if (!file_exists($filename)) {
                    // no small thumb available, return original size for small pics (< 20KB) else return mini size
                    if (dol_filesize($dir.$photoFile) > 20480) {
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
     * @param string $base64JpegUrl base64 encoded jpeg data
     * 
     * @return > 0 photo accepted < 0 photo not accepted
     */
    public function addBase64Jpeg($base64JpegUrl, $index = 1) {
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
        
        if (! empty($conf->global->PRODUCT_USE_OLD_PATH_FOR_PHOTO) || (ExtDirect::checkDolVersion() <= 3.6)) $dir .= '/'. get_exdir($this->id,2) . $this->id ."/photos";
        else $dir .= '/'.dol_sanitizeFileName($this->ref);
        
        dol_mkdir($tdir);
        dol_mkdir($dir);
        $base64 = explode(',', $base64JpegUrl);
        $imgdata = base64_decode($base64[1]);
        
        if (substr($imgdata,0,3)=="\xff\xd8\xff") { // only jpeg
            $filename = 'ExtDirectUpload'. $this->id . '_' . $index . '.jpg';    
            if (is_dir($tdir) && (file_put_contents($tdir.$filename, $imgdata, LOCK_EX) > 0)) {
                if (is_dir($dir)) {
                    dol_move($tdir.$filename, $dir.'/'.$filename);
                    if (file_exists(dol_osencode($dir.'/'.$filename)))
                    {
                        // Cree fichier en taille vignette
                        if (ExtDirect::checkDolVersion() <= 3.9) {
                        	$this->add_thumb($dir.'/'.$filename);
                        } else {
                        	$this->addThumbs($dir.'/'.$filename);
                        }                        
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
     * @return string barcode with checksum
     */
    public function fetchBarcodeWithChecksum() 
    {
        $barcodeType = '';
        if ($this->barcode_type == '1') { // EAN8
            $barcodeType = 'EAN8'; 
        } else if ($this->barcode_type == '2') { // EAN13
            $barcodeType = 'EAN13';
        } else if ($this->barcode_type == '3') { // UPC
            $barcodeType = 'UPCA';
        }

        if (!empty($barcodeType) && !empty($this->barcode)) {
            include_once TCPDF_PATH.'tcpdf_barcodes_1d.php';
            $barcodeObj = new TCPDFBarcode($this->barcode, $barcodeType);
            $barcode = $barcodeObj->getBarcodeArray();
            return $barcode['code'];
        } else {
            return $this->barcode;
        }
    }

    /**
     * Add subproducts to results
     *
     * @param array &$results array to store batches
     * @param object $row object with product data to add to results
     *
     * @return void
     */
    public function fetchSubProducts(&$results, $row, $photoFormat = '') {
        global $conf;

        if (! empty($conf->global->PRODUIT_SOUSPRODUITS)) {
            $product_id = $this->id;
            $this->get_sousproduits_arbo();
            if (isset($this->sousprods)) {
                $prods_arbo = $this->get_arbo_each_prod($row->qty_asked);
                if (count($prods_arbo) > 0) {
                    $rowId = $row->id;
                    $rowLabel = $row->label;
                    foreach($prods_arbo as $key => $value) {
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