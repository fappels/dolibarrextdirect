<?PHP

/*
 * Copyright (C) 2013       Francis Appels <francis.appels@z-application.com>
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

require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
dol_include_once('/extdirect/class/extdirect.class.php');
dol_include_once('/extdirect/class/ExtDirectFormProduct.class.php');

/** ExtDirectProduct class
 * 
 * Class to access products with CRUD(L) methods to connect to Extjs or sencha touch using Ext.direct connector
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
        global $langs,$db,$user;
        
        if (!empty($login)) {
            if ($user->fetch('', $login)>0) {
                $user->getrights();
                $this->_user = $user;  //product.class uses global user
                if (isset($this->_user->conf->MAIN_LANG_DEFAULT) && ($this->_user->conf->MAIN_LANG_DEFAULT != 'auto')) {
                    $langs->setDefaultLang($this->_user->conf->MAIN_LANG_DEFAULT);
                }
                $langs->load("products");
                $this->db = $db;
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
        global $conf,$langs;

        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->produit->lire)) return PERMISSIONERROR;
        $results = array();
        $row = new stdClass;
        $id = 0;
        $ref = '';
        $ref_ext = '';

        if (isset($param->filter)) {
            foreach ($param->filter as $key => $filter) {
                if ($filter->property == 'id') $id=$filter->value;
                else if ($filter->property == 'ref') $ref=$filter->value;
                else if ($filter->property == 'warehouse_id') $warehouse=$filter->value;
                else if ($filter->property == 'multiprices_index' ) $multiprices_index=$filter->value;
                else if ($filter->property == 'barcode' ) $id = $this->fetchIdFromBarcode($filter->value);
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
                    
                //! Stock
                if (isset($warehouse) && $warehouse != ExtDirectFormProduct::ALLWAREHOUSE_ID) {
                    $row->stock_reel= (float) $this->stock_warehouse[$warehouse]->real;
                    $row->pmp= $this->stock_warehouse[$warehouse]->pmp;
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
                    
                //! barcode
                $row->barcode= $this->barcode?$this->barcode:'';               // value
                $row->barcode_type= (int) $this->barcode_type;          // id
                    
                // no links to offers in this version
                // no multilangs in this version
                    
                //! Canevas a utiliser si le produit n'est pas un produit generique
                $row->canvas= $this->canvas;
                $row->entity= $this->entity;
                $row->import_key= $this->import_key;
                $row->date_creation= $this->date_creation;
                $row->date_modification= $this->date_modification;

                array_push($results, $row);
            } else {
                return 0;
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

        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->produit->creer)) return PERMISSIONERROR;
        $notrigger=0;
        $paramArray = ExtDirect::toArray($params);
        
        foreach ($paramArray as &$param) {
            // prepare fields
            $this->prepareFields($param);
            if (($result = $this->create($this->_user, $notrigger)) < 0) return $result;
            
            //! Stock
            if (!empty($param->correct_stock_nbpiece)) {
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
                    $param->correct_stock_price
                );
            if ($result < 0) return $result;
            }   
            // barcode
            if (!empty($this->barcode)) {
                $this->setValueFrom('barcode', $this->barcode);
                $this->setValueFrom('fk_barcode_type', $this->fk_barcode_type);
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
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->produit->creer)) return PERMISSIONERROR;
        // dolibarr update settings
        $allowmodcodeclient=0;
        $notrigger=false;
        $allowmodcodefournisseur=0;

        $paramArray = ExtDirect::toArray($params);
        foreach ($paramArray as &$param) {
            // prepare fields
            if ($param->id) {
                $id = $param->id;
                if (($result = $this->fetch($id, '', '')) < 0)    return $result;
                $this->prepareFields($param);
                // update
                if (($result = $this->update($id, $this->_user, $notrigger)) < 0)   return $result;
                //! Stock
                if (!empty($param->correct_stock_dest_warehouseid)) {
                    // transfer stock
                    if (!empty($param->correct_stock_nbpiece)) {
                        $movement = 1;
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
                            $param->correct_stock_price
                        );
                        if ($result < 0) return $result;
                        $movement = 0;
                        $wharehouseid=$param->correct_stock_dest_warehouseid;
                        $result = $this->correct_stock(
                            $this->_user,
                            $wharehouseid,
                            // nb of units
                            $param->correct_stock_nbpiece,
                            // 0 = add, 1 = remove
                            $movement,
                            // Label of stock movement
                            $param->correct_stock_label,
                            // Price to use for stock eval
                            $param->correct_stock_price
                        );
                        if ($result < 0) return $result;
                    }
                } else if (!empty($param->correct_stock_nbpiece)) {
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
                        $param->correct_stock_price
                    );
                    if ($result < 0) return $result;
                }
                // barcode
                if (!empty($this->barcode)) {
                    $this->setValueFrom('barcode', $this->barcode);
                    $this->setValueFrom('fk_barcode_type', $this->fk_barcode_type);
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
     * @param stdClass $param to filter on order status
     * @return     stdClass result data or -1
     */
    public function readProductList(stdClass $param) 
    {
        global $conf;
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->produit->lire)) return PERMISSIONERROR;
        $results = array();
        $row = new stdClass;
        $warehouseid=null;
        $tosell=null;
        $tobuy=null;
        $finished=null;
        $limit=null;
        $start=0;
        $contentFilter=null;
        
        if (isset($param->limit)) {
            $limit = $param->limit;
            $start = $param->start;
        }
        if (isset($param->filter)) {
            foreach ($param->filter as $key => $filter) {
                if ($filter->property == 'warehouse_id') $warehouseid=$filter->value;
                if ($filter->property == 'tosell') $tosell=$filter->value;
                if ($filter->property == 'tobuy') $tobuy=$filter->value;
                if ($filter->property == 'finished') $finished=$filter->value;      
                if ($filter->property == 'content') {
                    $contentValue = strtolower($filter->value);
                    $contentFilter = " AND (LOWER(p.ref) like '%".$contentValue."%' 
                         OR LOWER(p.label) like '%".$contentValue."%' 
                         OR LOWER(p.barcode) like '%".$contentValue."%')" ;
                }
            }
        }       
    
        $sql = "SELECT p.rowid, p.ref, p.label, p.barcode, ps.fk_entrepot, ps.reel";
        $sql.= " FROM ".MAIN_DB_PREFIX."product as p, ".MAIN_DB_PREFIX."product_stock as ps";
        $sql.= " WHERE p.entity = ".$conf->entity;
        $sql.= " AND p.rowid = ps.fk_product";
        if ($warehouseid  && $warehouseid != ExtDirectFormProduct::ALLWAREHOUSE_ID) {
            $sql.= " AND ps.fk_entrepot = ".$warehouseid;
        }
        if ($tosell) {
            $sql.= " AND p.tosell = ".$tosell;
        }
        if ($tobuy) {
            $sql.= " AND p.tobuy = ".$tobuy;
        }
        if ($finished) {
            $sql.= " AND p.finished = ".$finished;
        }
        if ($contentFilter) {
            $sql.= $contentFilter;
        }
        $sql .= " ORDER BY p.ref ASC";
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
                $row->warehouse_id  = $obj->fk_entrepot;
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
        isset($param->note) ? ( $this->note =$param->note) : null;
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
}