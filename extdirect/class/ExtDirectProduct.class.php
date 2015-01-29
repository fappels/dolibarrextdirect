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
        global $langs,$db,$user,$conf;
        
        if (!empty($login)) {
            if ($user->fetch('', $login)>0) {
                $user->getrights();
                $this->_user = $user;  //product.class uses global user
                if (isset($this->_user->conf->MAIN_LANG_DEFAULT) && ($this->_user->conf->MAIN_LANG_DEFAULT != 'auto')) {
                    $langs->setDefaultLang($this->_user->conf->MAIN_LANG_DEFAULT);
                }
                $langs->load("products");
                if (! empty($conf->productbatch->enabled)) $langs->load("productbatch");
                if (ExtDirect::checkDolVersion() >= 3.3) {
                    parent::__construct($db);
                } else {
                    $this->db = $db;
                }
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
     *    @return     stdClass result data or -1
     */
    public function readProduct(stdClass $param)
    {
        global $conf;

        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->produit->lire)) return PERMISSIONERROR;
        if (! empty($conf->productbatch->enabled)) require_once DOL_DOCUMENT_ROOT.'/product/class/productbatch.class.php';
        $results = array();
        $row = new stdClass;
        $id = 0;
        $ref = '';
        $ref_ext = '';
        $batch = '';

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
            }
        }
        
        if (($id > 0) || ($ref != '')) {
            if (($result = $this->fetch($id, $ref, $ref_ext)) < 0)    return $result;
            if (!$this->error) {
                $row->id = $this->id ;
                //! Ref
                $row->ref= $this->ref;
                $row->label= $this->label;
                $row->description= $this->description?$this->description:'';
                //! Type 0 for regular product, 1 for service (Advanced feature: 2 for assembly kit, 3 for stock kit)
                $row->type= $this->type;
                $row->note= $this->note;
                //! Selling price
                if (! empty($conf->global->PRODUIT_MULTIPRICES) && isset($multiprices_index)) {
                    //! Arrays for multiprices
                    $row->price=$this->multiprices[$multiprices_index]?$this->multiprices[$multiprices_index]:'';
                    $row->price_ttc=$this->multiprices_ttc[$multiprices_index]?$this->multiprices_ttc[$multiprices_index]:'';
                    $row->tva_tx=$this->multiprices_tva_tx[$multiprices_index]?$this->multiprices_tva_tx[$multiprices_index]:'';
                    $row->price_base_type=$this->multiprices_base_type[$multiprices_index];
                    $row->multiprices_index=$multiprices_index;
                } else {
                    $row->price= $this->price?$this->price:'';              // Price net
                    $row->price_ttc= $this->price_ttc?$this->price_ttc:'';          // Price with tax
                    //! Default VAT rate of product
                    $row->tva_tx= $this->tva_tx?$this->tva_tx:'';
                    //! Base price ('TTC' for price including tax or 'HT' for net price)
                    $row->price_base_type= $this->price_base_type;
                }
                $row->price_min= $this->price_min;         // Minimum price net
                $row->price_min_ttc= $this->price_min_ttc;     // Minimum price with tax
                //! French VAT NPR (0 or 1)
                $row->tva_npr= $this->tva_npr;
                //! Spanish local taxes
                $row->localtax1_tx= $this->localtax1_tx;
                $row->localtax2_tx= $this->localtax2_tx;
                
                // batch managed product
                $row->has_batch = $this->status_batch;
                    
                //! Stock
                if (isset($warehouse) && $warehouse != ExtDirectFormProduct::ALLWAREHOUSE_ID) {
                    if (ExtDirect::checkDolVersion() >= 3.5) {
                        $this->load_stock();
                    } 
                    
                    $row->pmp= $this->stock_warehouse[$warehouse]->pmp;
                    if (!empty($conf->productbatch->enabled) && (!empty($batch) || isset($batchId))) {
                        $productBatch = new Productbatch($this->db);
                        if (isset($batchId)) {
                            $productBatch->fetch($batchId);
                        } else {
                            $productBatch->find($this->stock_warehouse[$warehouse]->id,'','',$batch);
                        }    
                        if (!isset($productBatch->id)) {
                            $row->batch_id = 0; // for adding new batch when batch not found
                            $batchesQty = 0;
                            $stockQty = $this->stock_warehouse[$warehouse]->real;
                            if (($batchesQty = $this->fetchBatchesQty($this->stock_warehouse[$warehouse]->id)) < 0 ) return $batchesQty;
                            dol_syslog(get_class($this)."::batchesQty=".$batchesQty." stockQty=".$stockQty);
                            $row->stock_reel = $stockQty - $batchesQty;
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
                    $row->stock_reel= (float) $this->stock_reel;
                    //! Average price value for product entry into stock (PMP)
                    $row->pmp= $this->pmp;
                }
                    
                //! Stock alert
                $row->seuil_stock_alerte= $this->seuil_stock_alerte;
                $row->warehouse_id=$warehouse;
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
                    
                //! Unites de mesure
                $row->weight= $this->weight;
                $row->weight_units= $this->weight_units;
                $row->length= $this->length;
                $row->length_units= $this->length_units;
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
                                   
                // no links to offers in this version
                // no multilangs in this version
                    
                //! Canevas a utiliser si le produit n'est pas un produit generique
                $row->canvas= $this->canvas;
                $row->entity= $this->entity;
                $row->import_key= $this->import_key;
                $row->date_creation= $this->date_creation;
                $row->date_modification= $this->date_modification;
                
                // supplier fields
                $supplierProduct = new ProductFournisseur($this->db);
                if (! isset($refSupplier)) {
                    $supplierProduct->find_min_price_product_fournisseur($this->id);
                } else {
                    $supplierProducts = $supplierProduct->list_product_fournisseur_price($this->id);
                    foreach ($supplierProducts as $prodsupplier) {
                        if ($prodsupplier->fourn_ref == $refSupplier){
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
                $row->vat_supplier = $supplierProduct->tva_tx;

                if (!empty($batch)) {
                    if (isset($row->batch_id)) {
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
            if (($result = $this->create($this->_user, $notrigger)) < 0) return $result;
            if (! empty($conf->productbatch->enabled) && !empty($param->batch)) {
                $correctStockFunction = 'correct_stock_batch';
                
            } else {
                $correctStockFunction = 'correct_stock';
            }
            //! Stock
            if (!empty($param->correct_stock_nbpiece)) {
                $result = $this->$correctStockFunction(
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
                    // sellBy date
                    $param->sellby,
                    // eatBy date
                    $param->eatby,
                    // batch number
                    $param->batch
                );
                if ($result < 0) return $result;
            }   
            // barcode
            if (!empty($this->barcode)) {
                $this->setValueFrom('barcode', $this->barcode);
                $this->setValueFrom('fk_barcode_type', $this->fk_barcode_type);
            }
            
            // supplier fields
            if (!empty($this->fourn_price)) {
                $supplierProduct = new ProductFournisseur($this->db);
                $supplier = new Societe($this->db);
                if (($result = $supplier->fetch($this->fourn_id)) < 0) return $result;
                $supplierProduct->id = $this->id;
                if (($result = $supplierProduct->update_buyprice(
                                $this->fourn_qty, 
                                $this->fourn_price, 
                                $this->_user, 
                                'HT', 
                                $supplier, 
                                0, 
                                $this->fourn_ref, 
                                $this->fourn_tva_tx
                )) < 0) return $result;
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
        if (!isset($this->_user->rights->produit->creer)) return PERMISSIONERROR;
        // dolibarr update settings
        $allowmodcodeclient=0;
        $notrigger=false;
        $supplierProducts = array();
        
        $paramArray = ExtDirect::toArray($params);
        foreach ($paramArray as &$param) {
            // prepare fields
            if ($param->id) {
                $id = $param->id;
                if (($result = $this->fetch($id, '', '')) < 0)    return $result;
                // supplier fields
                $supplierProduct = new ProductFournisseur($this->db);
                if (! empty($params->ref_supplier_id)) {
                    if (($result = $supplierProduct->fetch_product_fournisseur_price($params->ref_supplier_id)) < 0) return $result;
                    $supplierProducts[] = $supplierProduct;
                } else {
                    $supplierProducts = $supplierProduct->list_product_fournisseur_price($this->id);
                }                
                foreach ($supplierProducts as $prodsupplier) {
                    if ($prodsupplier->fourn_ref == $param->ref_supplier){
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
                $this->prepareFields($param);
                // update
                if (($result = $this->update($id, $this->_user, $notrigger)) < 0)   return $result;
                // check batch or non batch
                if (! empty($conf->productbatch->enabled) && !empty($param->batch)) {
                    //! Stock
                    $this->load_stock();
                    
                    $stockQty = $this->stock_warehouse[$param->warehouse_id]->real;
                    $batchesQty = 0;
                    if (($batchesQty = $this->fetchBatchesQty($this->stock_warehouse[$param->warehouse_id]->id)) < 0 ) return $batchesQty;
                    
                    if (($param->correct_stock_movement == 0) && ($param->correct_stock_nbpiece > 0) && (($batchesQty + $param->correct_stock_nbpiece) <= $stockQty)) {
                        // only create batch when non batched stock available
                        $productBatch = new Productbatch($this->db);
                        $productBatch->batch = $param->batch;
                        $productBatch->sellby = $param->sellby;
                        $productBatch->eatby = $param->eatby;
                        $productBatch->qty = $param->correct_stock_nbpiece;
                        $productBatch->fk_product_stock = $this->stock_warehouse[$param->warehouse_id]->id;
                        if (($res = $productBatch->create($this->_user,1)) < 0) return $res;
                        // don't move stock of this new batch
                        $param->correct_stock_nbpiece = 0;
                    }
                    $correctStockFunction = 'correct_stock_batch';
                } else {
                    $correctStockFunction = 'correct_stock';
                }
                if (!empty($param->correct_stock_dest_warehouseid)) {
                    // transfer stock
                    if (!empty($param->correct_stock_nbpiece)) {
                        $movement = 1;
                        $result = $this->$correctStockFunction(
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
                            // sellBy date
                            $param->eatby,
                            // eatBy date
                            $param->sellby,
                            // batch number
                            $param->batch                            
                        );
                        if ($result < 0) return $result;
                        $movement = 0;
                        $result = $this->$correctStockFunction(
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
                            // sellBy date
                            $param->eatby,
                            // eatBy date
                            $param->sellby,
                            // batch number
                            $param->batch
                        );
                        if ($result < 0) return $result;
                    }
                } else if (!empty($param->correct_stock_nbpiece)) {
                    // change stock
                    $result = $this->$correctStockFunction(
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
                        // sellBy date
                        $param->eatby,
                        // eatBy date
                        $param->sellby,
                        // batch number
                        $param->batch
                    );
                    if ($result < 0) return $result;
                }
                // barcode
                if (!empty($this->barcode)) {
                    $this->setValueFrom('barcode', $this->barcode);
                    $this->setValueFrom('fk_barcode_type', $this->fk_barcode_type);
                }
                // update product batch 
                if (!empty($conf->productbatch->enabled) && (!empty($param->batch) || !empty($param->batch_id))) {
                    if (!(($param->correct_stock_movement == 1) && ($param->stock_reel - $param->correct_stock_nbpiece) == 0)) {
                        // update batch if not removed
                        $productBatch = new Productbatch($this->db);
                        if (!empty($param->batch_id)) {
                            $productBatch->fetch($param->batch_id);
                        } else {                            
                            if (isset($this->stock_warehouse[$param->warehouse_id]->id)) {
                                $productBatch->find($this->stock_warehouse[$param->warehouse_id]->id,$param->eatby,$param->sellby,$param->batch);
                            }
                        }
                        if (isset($productBatch->id)) {
                            isset($param->batch) ? $productBatch->batch = $param->batch : null;
                            isset($param->sellby) ? $productBatch->sellby = $param->sellby : null;
                            isset($param->eatby) ? $productBatch->eatby = $param->eatby : null;
                            isset($param->batch_info) ? $productBatch->import_key = $param->batch_info : null;
                            if (($result = $productBatch->update($this->_user)) < 0) return $result;
                        }        
                    }                    
                }
                // supplier fields
                if (!empty($this->fourn_price) && !empty($this->fourn_ref)) {
                    $supplierProduct = new ProductFournisseur($this->db);
                    $supplier = new Societe($this->db);
                    if (($result = $supplier->fetch($this->fourn_id)) < 0) return $result;
                    $supplierProduct->id = $this->id;
                    $supplierProduct->product_fourn_price_id = $this->product_fourn_price_id; // 3.3 comptibility
                    if (($result = $supplierProduct->update_buyprice(
                                    $this->fourn_qty, 
                                    $this->fourn_price, 
                                    $this->_user, 
                                    'HT', 
                                    $supplier, 
                                    0, 
                                    $this->fourn_ref, 
                                    $this->fourn_tva_tx
                    )) < 0) return $result;
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
                if (($result = $this->delete($id)) <= 0)    return $result;
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
     * public method to read a list of products
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
     * @return     stdClass result data or -1
     */
    public function readProductList(stdClass $param) 
    {
        global $conf;
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->produit->lire)) return PERMISSIONERROR;
        $results = array();
        $row = new stdClass;
        $filterSize = 0;
        $limit=null;
        $start=0;
        $dataComplete=false;
        $dataNotComplete=false;        
        
        if (isset($param->limit)) {
            $limit = $param->limit;
            $start = $param->start;
        }
        if (isset($param->filter)) {
            $filterSize = count($param->filter);
        }
        
        $sql = 'SELECT p.rowid, p.ref, p.label, p.barcode, ps.fk_entrepot, ps.reel';
        $sql .= ' FROM '.MAIN_DB_PREFIX.'product as p';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'product_stock as ps ON p.rowid = ps.fk_product';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie_product as cp ON p.rowid = cp.fk_product';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie as c ON c.rowid = cp.fk_categorie';
        if ($filterSize > 0) {
            // TODO improve sql command to allow random property type
            $sql .= ' WHERE (';
            foreach ($param->filter as $key => $filter) {
                $value = $this->db->escape($filter->value);
                if (empty($value)) {
                    if ($filter->property == 'categorie_id') {
                        $sql .= 'c.rowid IS NULL';
                    } else {
                        $sql .= '1';
                    }                    
                } else {
                    if ($filter->property == 'warehouse_id') {
                        $sql .= 'ps.fk_entrepot = '.$value;
                    } else if ($filter->property == 'tosell') {
                        $sql .= "p.tosell = ".$value;
                    } else if ($filter->property == 'tobuy') {
                        $sql .= "p.tobuy = ".$value;
                    } else if ($filter->property == 'status') { // backward comp
                        $sql .= "p.tosell = ".$value;
                    } else if ($filter->property == 'status_buy') {  // backward comp
                        $sql .= "p.tobuy = ".$value;
                    } else if ($filter->property == 'finished') {
                        $sql .= "p.finished = ".$value;
                    } else if ($filter->property == 'type') {
                        $sql .= "p.fk_product_type = ".$value;
                    } else if ($filter->property == 'categorie_id') {
                        $sql .= "c.rowid = ".$value;
                    } else if ($filter->property == 'content') {
                        $contentValue = strtolower($value);
                        $sql.= " (LOWER(p.ref) like '%".$contentValue."%' OR LOWER(p.label) like '%".$contentValue."%'";
                        $sql.= " OR LOWER(p.barcode) like '%".$contentValue."%')" ;
                    } else if ($filter->property == 'complete' && !empty($value)) {
                        $dataComplete = true;
                        if (! empty($conf->productbatch->enabled)) {
                            $sql .= " p.tobatch = 0 OR ps.rowid NOT IN (";
                            $sql .= " SELECT fk_product_stock FROM ".MAIN_DB_PREFIX."product_batch";
                            $sql .= " WHERE ((batch = '' ) OR (batch IS NULL) OR (import_key = '' ) OR (import_key IS NULL)))";
                            $sql .= " AND ps.rowid IN (";
                            $sql .= " SELECT fk_product_stock FROM ".MAIN_DB_PREFIX."product_batch )";
                            $sql .= " AND (p.barcode <> '' OR p.barcode IS NOT NULL)";
                        } else {
                            $sql .= "p.barcode <> '' OR p.barcode IS NOT NULL";
                        }                        
                    } else if ($filter->property == 'notcomplete' && !empty($value)) {
                        $dataNotComplete = true;
                        if (! empty($conf->productbatch->enabled)) {
                            $sql .= " (p.tobatch = 0 AND (p.barcode = '' OR p.barcode IS NULL)) OR ps.rowid IN (";
                            $sql .= " SELECT fk_product_stock FROM ".MAIN_DB_PREFIX."product_batch";
                            $sql .= " WHERE ((batch = '' ) OR (batch IS NULL) OR (import_key = '' ) OR (import_key IS NULL)))";
                            $sql .= " OR p.barcode = '' OR p.barcode IS NULL";
                        } else {
                            $sql .= "p.barcode = '' OR p.barcode IS NULL";
                        } 
                    }
                }    
                if ($key < ($filterSize-1)) {
                    if($filter->property == $param->filter[$key+1]->property) $sql .= ' OR ';
                    else $sql .= ') AND (';
                }            
            }
            $sql .= ')';
        }
        $sql .= " ORDER BY ";
        if (isset($param->sort)) {
            $sorterSize = count($param->sort);
            foreach($param->sort as $key => $sort) {
                $sql .= $sort->property. ' '.$sort->direction;
                if ($key < ($sorterSize-1)) {
                    $sql .= ",";
                }
            }
        } else {
            $sql .= "p.ref ASC";
        }

        if ($limit) {
            $sql .= $this->db->plimit($limit, $start);
        }
    
        dol_syslog(get_class($this)."::readProductList sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        
        if ($resql) {
            $num=$this->db->num_rows($resql);
            for ($i = 0;$i < $num; $i++) {
                $obj = $this->db->fetch_object($resql);
                $row = null;
                $row->id        = $obj->rowid.'_'.$obj->fk_entrepot;
                $row->product_id= (int) $obj->rowid;
                $row->ref       = $obj->ref;
                $row->label     = $obj->label;
                $row->barcode   = $obj->barcode;
                if (empty($obj->fk_entrepot)) {
                     $row->warehouse_id = ExtDirectFormProduct::ALLWAREHOUSE_ID;
                } else {
                    $row->warehouse_id = $obj->fk_entrepot;
                }
                if (empty($obj->reel)) {
                    $row->stock = 0;
                } else {
                    $row->stock = (float) $obj->reel;
                }
                $row->stock     = (float) $obj->reel;
                array_push($results, $row);
            }
            $this->db->free($resql);
            return $results;
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
     * @return     stdClass result data or -1
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
        if (($res = $this->load_stock()) < 0) return $res; 
               
        if ($warehouseId == ExtDirectFormProduct::ALLWAREHOUSE_ID) {
            require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
            $formProduct = new FormProduct($this->db);
            $formProduct->loadWarehouses($id);
            foreach ($formProduct->cache_warehouses as $warehouseId => $warehouse) {
                if ($includeNoBatch && $this->stock_warehouse[$warehouseId]->real > 0) {
                    $row->id = 'X';
                    $row->product_id = $id;
                    $row->batch_id = 0;
                    $row->batch = $langs->trans('BatchDefaultNumber');
                    $row->stock_reel = (float) $this->stock_warehouse[$warehouseId]->real;
                    $row->warehouse_id = $warehouseId;
                }
                if (($res = $this->fetchBatches($results, $row, $this->id, $warehouseId, $this->stock_warehouse[$warehouseId]->id)) < 0) return $res;
            }
        } else {
            if ($includeNoBatch && $this->stock_warehouse[$warehouseId]->real > 0) {
                $row->id = 'X';
                $row->product_id = $id;
                $row->batch_id = 0;
                $row->batch = $langs->trans('BatchDefaultNumber');
                $row->stock_reel = (float) $this->stock_warehouse[$warehouseId]->real;
                $row->warehouse_id = $warehouseId;
            }
            if (($res = $this->fetchBatches($results, $row, $this->id, $warehouseId, $this->stock_warehouse[$warehouseId]->id)) < 0) return $res;
        }

        return $results;
    }
        
    /**
     * private method to copy fields into dolibarr object
     * 
     * @param stdclass $param object with fields
     * @return null
     */
    private function prepareFields($param) 
    {
        isset($param->ref) ? ( $this->ref = $param->ref ) : null;
        isset($param->label) ? ( $this->libelle = $param->label) : null;
        isset($param->description) ? ( $this->description = $param->description) : null;
        //! Type 0 for regular product, 1 for service (Advanced feature: 2 for assembly kit, 3 for stock kit)
        isset($param->type) ? ( $this->type = $param->type) : null;
        isset($param->note) ? ( $this->note = $param->note) : (isset($this->note) ? null : ($this->note = ''));
        //! Selling price
        
        if (! empty($conf->global->PRODUIT_MULTIPRICES) && !empty($param->multiprices_index)) {
            isset($param->price) ? ( $this->multiprices[$param->multiprices_index] =$param->price   ) : null;
            isset($param->price_ttc) ? ( $this->multiprices_ttc[$param->multiprices_index] = $param->price_ttc) : null;
            //! Default VAT rate of product
            isset($param->tva_tx) ? ( $this->multiprices_tva_tx[$param->multiprices_index] =$param->tva_tx) : null;
            //! Base price ('TTC' for price including tax or 'HT' for net price)
            isset($param->price_base_type) ? ( $this->multiprices_base_type[$param->multiprices_index] = $param->price_base_type) : null;
        } else {
            isset($param->price) ? ( $this->price =$param->price    ) : null;
            isset($param->price_ttc) ? ( $this->price_ttc = $param->price_ttc) : null;
            //! Default VAT rate of product
            isset($param->tva_tx) ? ( $this->tva_tx =$param->tva_tx) : null;
            //! Base price ('TTC' for price including tax or 'HT' for net price)
            isset($param->price_base_type) ? ( $this->price_base_type = $param->price_base_type) : null;
        }
            
        isset($param->price_min) ? ( $this->price_min = $param->price_min ) : null;
        isset($param->price_min_ttc) ? ( $this->price_min_ttc = $param->price_min_ttc) : null;
        
        //! French VAT NPR (0 or 1)
        isset($param->tva_npr) ? ( $this->tva_npr =$param->tva_npr) : null;
        //! Spanish local taxes
        isset($param->localtax1_tx) ? ( $this->localtax1_tx =$param->localtax1_tx) : null;
        isset($param->localtax2_tx) ? ( $this->localtax2_tx =$param->localtax2_tx) : null;
        //! Stock alert
        isset($param->seuil_stock_alerte) ? ( $this->seuil_stock_alerte =$param->seuil_stock_alerte) : null;
        
        //! Duree de validite du service
        isset($param->duration_value) ? ( $this->duration_value =$param->duration_value) : null;
        //! Unite de duree
        isset($param->duration_unit) ? ( $this->duration_unit =$param->duration_unit) : null;
        // Statut indique si le produit est en vente '1' ou non '0'
        isset($param->tosell) ? ( $this->status =$param->tosell) : null;
        // Status indicate whether the product is available for purchase '1' or not '0'
        isset($param->tobuy) ? ( $this->status_buy =$param->tobuy) : null;
        // Statut indique si le produit est un produit fini '1' ou une matiere premiere '0'
        isset($param->finished) ? ( $this->finished =$param->finished) : null;
        
        isset($param->customcode) ? ( $this->customcode =  $param->customcode ) : null;
        isset($param->country_id) ? ( $this->country_id =  $param->country_id ) : null;
        isset($param->country_code) ? ( $this->country_code =$param->country_code  ) : null;
        
        //! Unites de mesure
        isset($param->weight) ? ( $this->weight =$param->weight) : null;
        isset($param->weight_units) ? ( $this->weight_units =$param->weight_units) : null;
        isset($param->length) ? ( $this->length =$param->length) : null;
        isset($param->length_units) ? ( $this->length_units =$param->length_units) : null;
        isset($param->surface) ? ( $this->surface =$param->surface) : null;
        isset($param->surface_units) ? ( $this->surface_units =$param->surface_units) : null;
        isset($param->volume) ? ( $this->volume =$param->volume) : null;
        isset($param->volume_units) ? ( $this->volume_units =$param->volume_units) : null;
        
        isset($param->accountancy_code_buy) ? ( $this->accountancy_code_buy =$param->accountancy_code_buy) : null;
        isset($param->accountancy_code_sell) ? ( $this->accountancy_code_sell =$param->accountancy_code_sell) : null;
        
        //! barcode
        isset($param->barcode) ? ( $this->barcode = $param->barcode ) : null;
        isset($param->barcode_type) ? ( $this->fk_barcode_type = $param->barcode_type   ) : $this->fk_barcode_type=0;
        
        // no links to offers in this version
        // no multilangs in this version
        
        //! Canevas a utiliser si le produit n'est pas un produit generique
        isset($param->canvas) ? ( $this->canvas =$param->canvas) : null;
        isset($param->entity) ? ( $this->entity =$param->entity) : null;
        isset($param->import_key) ? ( $this->import_key =$param->import_key) : null;
        isset($param->date_creation) ? ( $this->date_creation =$param->date_creation) : null;
        isset($param->date_modification) ? ( $this->date_modification =$param->date_modification) : null;
        // has batch
        isset($param->has_batch) ? ( $this->status_batch = $param->has_batch ) : null;
        //isset($param->productinfo) ? ( $this->array_options['options_productinfo'] = $param->productinfo) : null;
        isset($param->ref_supplier) ? ( $this->fourn_ref = $param->ref_supplier) : null;
        isset($param->ref_supplier_id) ? ( $this->product_fourn_price_id = $param->ref_supplier_id) : null;
        isset($param->price_supplier) ? ( $this->fourn_price = $param->price_supplier) : null;
        isset($param->qty_supplier) ? ( $this->fourn_qty = $param->qty_supplier) : null;
        isset($param->reduction_percent_supplier) ? ( $this->fourn_remise_percent = $param->reduction_percent_supplier) : null;
        isset($param->reduction_supplier) ? ( $this->fourn_remise = $param->reduction_supplier) : null;
        isset($param->pu_supplier) ? ( $this->fourn_unitprice = $param->pu_supplier) : null;
        isset($param->vat_supplier) ? ( $this->fourn_tva_tx = $param->vat_supplier) : null;
        isset($param->supplier_id) ? ( $this->fourn_id = $param->supplier_id) : null;
    }
    
    /**
     * private method to fetch id from given barcode
     *
     * @param string $barcode barcode to fetch id from
     * @return integer $id rowid of product
     */
    private function fetchIdFromBarcode($barcode) 
    {
        $id =0;
        dol_syslog(get_class($this)."::fetch id from barcode=".$barcode);
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."product WHERE barcode ='".$barcode."'";
        $resql = $this->db->query($sql);
        if ( $resql ) {
            if ($this->db->num_rows($resql) > 0) {
                $obj = $this->db->fetch_object($resql);
                $id = (int) $obj->rowid;
            }
        }
        return $id;
    }
    
    /**
     * public method to fetch batch results
     * 
     * @param array &$results array to store batches
     * @param object $row object with product data to add to results
     * @param int $id product id
     * @param int $warehouseId warehouse id
     * @param int $productStockId produc stock id
     * @return int < 0 if error > 0 if OK
     */
    public function fetchBatches(&$results,$row,$id,$warehouseId,$productStockId) {
        require_once DOL_DOCUMENT_ROOT.'/product/class/productbatch.class.php';
        $batches = array();
        $batchesQty = 0;
        $stockQty = $row->stock_reel;
        $undefinedBatch = clone $row;
        
        if (!empty($productStockId) && ($batches = Productbatch::findAll($this->db, $productStockId)) < 0 ) return $batches;
        
        if (!empty($batches)) {
            foreach ($batches as $batch) {
                $row->id = $id.'_'.$batch->id;
                $row->product_id = $this->id;
                $row->warehouse_id = $warehouseId;
                $row->batch_id = $batch->id;
                $row->stock_id = $batch->fk_product_stock;
                $row->sellby = $batch->sellby;
                $row->eatby = $batch->eatby;
                $row->batch = $batch->batch;
                $row->stock_reel= (float) $batch->qty;
                $row->qty_stock = (int) $batch->qty;
                $row->batch_info = $batch->import_key;
                array_push($results, clone $row);
                $batchesQty += $batch->qty;
            }
        } else if(isset($row->id)) {
            // no batch
            array_push($results, $row);
        } 
        
        if (!empty($stockQty) && isset($row->id) && isset($row->batch_id)) {
            // add undefined batch for adding batches
            $undefinedBatch->stock_reel = $stockQty - $batchesQty;
            array_push($results, $undefinedBatch);
        }       
        return 1;
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
}