<?PHP

/**
 * Copyright (C) 2013-2016  Francis Appels <francis.appels@z-application.com>
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
 *  \file       htdocs/extdirect/class/ExtDirectCommandeFournisseur.class.php
 *  \brief      Sencha Ext.Direct supplier orders remoting class
 */

require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.product.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';
dol_include_once('/extdirect/class/extdirect.class.php');

/** 
 * ExtDirectCommandeFournisseur class
 * 
 * Orders Class to with CRUD methods to connect to Extjs or sencha touch using Ext.direct connector
 * 
 * @category External_Module
 * @package  Extdirect
 * @author   Francis Appels <francis.appels@z-application.com>
 * @license  http://www.gnu.org/licenses/ GPLV3
 * @version  Release: 1.0
 * @link     https://github.com/fappels/dolibarrextdirect/blob/master/extdirect/class/ExtDirectCommandeFournisseur.class.php
 */
class ExtDirectCommandeFournisseur extends CommandeFournisseur
{
    private $_user;
    private $_orderConstants = array('STOCK_CALCULATE_ON_SUPPLIER_BILL',
        'STOCK_CALCULATE_ON_SUPPLIER_VALIDATE_ORDER',
        'STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER', 
        'SUPPLIER_ORDER_USE_DISPATCH_STATUS',
        'STOCK_USE_VIRTUAL_STOCK',
        'STOCK_ALLOW_NEGATIVE_TRANSFER',
        'STOCK_ALLOW_ADD_LIMIT_STOCK_BY_WAREHOUSE');

    /** 
     * Constructor     
     * 
     * @param string $login user name
     */
    public function __construct($login) 
    {
        global $langs,$db,$user;
        
        if (!empty($login)) {
            if ($user->fetch('', $login)>0) {
                $user->getrights();
                $this->_user = $user;  //commande.class uses global user
                if (isset($this->_user->conf->MAIN_LANG_DEFAULT) && ($this->_user->conf->MAIN_LANG_DEFAULT != 'auto')) {
                    $langs->setDefaultLang($this->_user->conf->MAIN_LANG_DEFAULT);
                }
                $langs->load("orders");
                parent::__construct($db);
            }
        }
    }
    
    /**
     * Load order related constants
     * 
     * @param   stdClass    $params filter with elements
     *                      constant	name of specific constant
     *
     * @return  stdClass result data with specific constant value
     */
    public function readConstants(stdClass $params)
    {
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->fournisseur->commande->lire)) return PERMISSIONERROR;

        $results = ExtDirect::readConstants($this->db, $params, $this->_user, $this->_orderConstants);

        return $results;
    }
    
    /**
     *    Load order from database into memory
     *
     *    @param    stdClass    $params     filter with elements:
     *      id                  Id of order to load
     *      ref                 ref, ref_int
     *      
     *    @return     stdClass result data or error number
     */
    public function readOrder(stdClass $params)
    {
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->fournisseur->commande->lire)) return PERMISSIONERROR;
        $myUser = new User($this->db);
        $mySociete = new Societe($this->db);
        $results = array();
        $row = new stdClass;
        $id = 0;
        $ref = '';
        $ref_ext = '';
        $ref_int = '';
        $orderstatus_ids = array();
        
        if (isset($params->filter)) {
            foreach ($params->filter as $key => $filter) {
                if ($filter->property == 'id') $id=$filter->value;
                else if ($filter->property == 'ref') $ref=$filter->value;
                else if ($filter->property == 'ref_int') $ref_int=$filter->value;
                else if ($filter->property == 'orderstatus_id') array_push($orderstatus_ids,$filter->value);
            }
        }
        
        if (($id > 0) || ($ref != '')) {
            if (($result = $this->fetch($id, $ref)) < 0)   return $result;
            if (!$this->error) {
                $row->id = $this->id ;
                //! Ref
                $row->ref= $this->ref;
                $row->ref_supplier= $this->ref_client;
                $row->supplier_id = $this->socid;
                if ($mySociete->fetch($this->socid)>0) {
                    $row->supplier_name = $mySociete->name;
                }
                //! -1 for cancelled, 0 for draft, 1 for validated, 2 for send, 3 for closed
                $row->orderstatus_id = $this->statut;
                $row->orderstatus = $this->getLibStatut(1);
                $row->note_private = $this->note_private;
                $row->note_public = $this->note_public;
                $row->user_id = $this->user_author_id;
                if ($myUser->fetch($this->user_author_id)>0) {
                    $row->user_name = $myUser->firstname . ' ' . $myUser->lastname;
                }
                $row->user_valid_id = $this->user_valid_id;
                $row->user_approve_id = $this->user_approve_id;
                $row->create_date = $this->date;
                $row->valid_date = $this->date_valid;
                $row->approve_date = $this->date_approve;
                $row->order_date = $this->date_commande;
                $row->deliver_date= $this->date_livraison;
                $row->order_method_id = $this->methode_commande_id;
                $row->order_method = $this->methode_commande;
                $row->reduction_percent = $this->remise_percent;
                $row->reduction = 0;
                foreach ($this->lines as $line) {
                    if ($line->remise_percent > 0) {
                        $tabprice = calcul_price_total($line->qty, $line->subprice, 0, $line->tva_tx, $line->total_localtax1, $line->total_localtax2, 0, 'HT', $line->info_bits, $line->product_type);	
                        $noDiscountHT = $tabprice[0];
                        $row->reduction += round($noDiscountHT - $line->total_ht, 2);
                    }
                }
                $row->payment_condition_id = $this->cond_reglement_id;
                $row->payment_type_id = $this->mode_reglement_id;
                $row->total_net = $this->total_ht;
                $row->total_tax = $this->total_tva;
                $row->total_inc = $this->total_ttc;
                $row->total_localtax1 = $this->total_localtax1;
                $row->total_localtax2 = $this->total_localtax2;
                if (empty($orderstatus_ids)) {
                    array_push($results, $row);
                } else {
                    // filter on order status
                    foreach ($orderstatus_ids as $orderstatus_id) {
                        if ($orderstatus_id == $row->orderstatus_id) {
                            array_push($results, $row);
                        }
                    }
                }
            }
        }
        
        return $results;
    }


    /**
     * Ext.direct method to Create Order
     * 
     * @param unknown_type $param object or object array with product model(s)
     * @return Ambigous <multitype:, unknown_type>|unknown
     */
    public function createOrder($param) 
    {
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->fournisseur->commande->creer)) return PERMISSIONERROR;
        $notrigger=0;
        $paramArray = ExtDirect::toArray($param);
        
        foreach ($paramArray as &$params) {
            // prepare fields
            $this->prepareOrderFields($params);
            if (($result = $this->create($this->_user, $notrigger)) < 0) return $result;
            
            $params->id=$this->id;
        }

        if (is_array($param)) {
            return $paramArray;
        } else {
            return $params;
        }
    }

    /**
     * Ext.direct method to update order
     * 
     * @param unknown_type $param object or object array with order model(s)
     * @return Ambigous <multitype:, unknown_type>|unknown
     */
    public function updateOrder($param) 
    {
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->fournisseur->commande->creer)) return PERMISSIONERROR;
        $paramArray = ExtDirect::toArray($param);
        $currentStatus = 0;
        $newstatus = 0;
        foreach ($paramArray as &$params) {
            // prepare fields
            if ($params->id) {
                $this->id = $params->id;
                if (($result = $this->fetch($this->id)) < 0)   return $result;
                $currentStatus = $this->statut;
                $this->prepareOrderFields($params);
                // update
                switch ($this->statut) {
                    
                    case 0: //
                        if ($currentStatus == 1) $newstatus=0;	// Validated->Draft
                        else if ($currentStatus == 2) $newstatus=0;	// Approved->Draft
                        else if ($currentStatus == 5) $newstatus=4;	// Received->Received partially
                        else if ($currentStatus == 6) $newstatus=2;	// Canceled->Approved
                        else if ($currentStatus == 7) $newstatus=3;	// Canceled->Process running
                        else if ($currentStatus == 9) $newstatus=1;	// Refused->Validated

                        $result = $this->setStatus($user, $newstatus);
                        break;
                    case 1:
                        $result = $this->valid($this->_user);
                        break;
                    case 2:
                        $result = $this->approve($this->_user,$params->warehouse_id);
                        break;                    
                    case 3:
                        $result = $this->commande($this->_user, $this->date_commande, $this->methode_commande_id, $params->comment);
                        break;
                    case 4:
                        $result = $this->Livraison($this->_user, $this->date_livraison, 'par', $params->comment);
                        break;
                    case 5:
                        $result = $this->Livraison($this->_user, $this->date_livraison, 'tot', $params->comment);
                        break;
                    case 6:
                        $result = $this->Cancel($this->_user);
                        break;
                    case 7:
                        $result = $this->Livraison($this->_user, $this->date_livraison, 'nev', $params->comment);
                        break;
                    case 9:
                        $result = $this->refuse($this->_user);
                        break;
                    default:
                        break;   
                }
                if ($result < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
                if (($result = $this->set_date_livraison($this->_user, $this->date_livraison)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
                if (isset($this->cond_reglement_id) &&
                    ($result = $this->setPaymentTerms($this->cond_reglement_id)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
                if (isset($this->mode_reglement_id) &&
                    ($result = $this->setPaymentMethods($this->mode_reglement_id)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
                
            } else {
                return PARAMETERERROR;
            }
        }
        if (is_array($param)) {
            return $paramArray;
        } else {
            return $params;
        }
    }

    /**
     * Ext.direct method to destroy order
     * 
     * @param unknown_type $param object or object array with order model(s)
     * @return Ambigous <multitype:, unknown_type>|unknown
     */
    public function destroyOrder($param) 
    {
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->fournisseur->commande->supprimer)) return PERMISSIONERROR;
        $paramArray = ExtDirect::toArray($param);

        foreach ($paramArray as &$params) {
            // prepare fields
            if ($params->id) {
                $this->id = $params->id;
                // delete 
                if (($result = $this->delete($this->_user)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
            } else {
                return PARAMETERERROR;
            }
        }

        if (is_array($param)) {
            return $paramArray;
        } else {
            return $params;
        }
    }
        
    /**
     * private method to copy order fields into dolibarr object
     * 
     * @param stdclass $params object with fields
     * @return null
     */
    private function prepareOrderFields($params) 
    {
        isset($params->ref) ? ( $this->ref = $params->ref ) : ( $this->ref = null);
        isset($params->ref_supplier) ? ( $this->ref_supplier = $params->ref_supplier) : ( $this->ref_supplier = null);
        isset($params->supplier_id) ? ( $this->socid = $params->supplier_id) : ( $this->socid = null);
        isset($params->orderstatus_id) ? ( $this->statut = $params->orderstatus_id) : ($this->statut  = 0);
        isset($params->note_private) ? ( $this->note_private =$params->note_private) : ( $this->note_private= null);
        isset($params->note_public) ? ( $this->note_public = $params->note_public ) : ($this->note_public = null);      
        isset($params->user_id) ? ( $this->user_author_id = $params->user_id) : ($this->user_author_id = null); 
        isset($params->order_date) ? ( $this->date_commande =$params->order_date) : ($this->date_commande = null);
        isset($params->deliver_date) ? ( $this->date_livraison =$params->deliver_date) : ($this->date_livraison = null);
        isset($params->reduction_percent) ? ($this->remise_percent = $params->reduction_percent) : null;
        isset($params->payment_condition_id) ? ($this->cond_reglement_id = $params->payment_condition_id) : null;
        isset($params->payment_type_id) ? ($this->mode_reglement_id = $params->payment_type_id) : null;
        isset($params->order_date) ? ($this->date_commande = $params->order_date) : null;
        isset($params->order_method_id) ? ($this->methode_commande_id = $params->order_method_id) : null;
        
    } 
    
    /**
     * Public method to read a list of orders
     *
     * @param stdClass $params to filter on order status and ref
     *
     * @return stdClass result data or error number
     */
    public function readOrderList(stdClass $params) 
    {
        global $conf, $langs;
        
        if (!isset($this->db)) return CONNECTERROR;
        $results = array();
        $row = new stdClass;
        $statusFilterCount = 0;
        $ref = null;
        $contactTypeId = 0;
        $barcode = null;
        $productId = null;
        $supplierId = null;

        if (isset($params->filter)) {
            foreach ($params->filter as $key => $filter) {
                if ($filter->property == 'orderstatus_id') $orderstatus_id[$statusFilterCount++]=$filter->value;
                if ($filter->property == 'ref') $ref=$filter->value;
                if ($filter->property == 'contacttype_id') $contactTypeId = $filter->value;
                if ($filter->property == 'contact_id') $contactId = $filter->value;
                if ($filter->property == 'barcode') $barcode = $filter->value;
                if ($filter->property == 'product_id') $productId = $filter->value;
                if ($filter->property == 'supplier_id') $supplierId = $filter->value;
            }
        }
        
        $sql = "SELECT DISTINCT s.nom, s.rowid AS socid, c.rowid, c.ref, c.ref_supplier, c.fk_statut, ea.status, cim.libelle as mode_label, cim.code as mode_code";
        $sql.= " FROM ".MAIN_DB_PREFIX."commande_fournisseur as c";
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON c.fk_soc = s.rowid";
        if ($barcode || $productId) {
            $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."commande_fournisseurdet as cd ON c.rowid = cd.fk_commande";
        }
        if ($barcode) {
            $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = cd.fk_product";
        }
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."element_contact as ec ON c.rowid = ec.element_id";
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."c_input_method as cim ON c.fk_input_method = cim.rowid";
        $sql.= " LEFT JOIN ("; // get latest extdirect activity status for commande to check if locked
        $sql.= "   SELECT ma.activity_id, ma.maxrow AS rowid, ea.status";
        $sql.= "   FROM (";
        $sql.= "    SELECT MAX( rowid ) AS maxrow, activity_id";
        $sql.= "    FROM ".MAIN_DB_PREFIX."extdirect_activity";
        $sql.= "    GROUP BY activity_id";
        $sql.= "   ) AS ma, ".MAIN_DB_PREFIX."extdirect_activity AS ea";
        $sql.= "   WHERE ma.maxrow = ea.rowid";
        $sql.= " ) AS ea ON c.rowid = ea.activity_id";
        $sql.= " WHERE c.entity IN (".getEntity('order_supplier', 1).')';
        $sql.= " AND c.fk_soc = s.rowid";
        
        
        if ($statusFilterCount>0) {
            $sql.= " AND ( ";
            foreach ($orderstatus_id as $key => $fk_statut) {
                $sql .= "c.fk_statut = ".$fk_statut;
                if ($key < ($statusFilterCount-1)) $sql .= " OR ";
            }
            $sql.= ")";
        }
        if ($ref) {
            $sql.= " AND c.ref = '".$this->db->escape($ref)."'";
        }
        if ($contactTypeId > 0) {
            $sql.= " AND ec.fk_c_type_contact = ".$contactTypeId;
            $sql.= " AND ec.fk_socpeople = ".$contactId;
        }
        if ($barcode) {
            $sql.= " AND (p.barcode = '".$this->db->escape($barcode)."' OR c.ref = '".$this->db->escape($barcode)."' OR c.ref_supplier = '".$this->db->escape($barcode)."')";
        }
        if ($productId) {
            $sql.= " AND cd.fk_product = ".$productId;
        }
        if ($supplierId) {
            $sql.= " AND c.fk_soc = ".$supplierId;
        }
        $sql .= " ORDER BY c.date_commande DESC";
        
        dol_syslog(get_class($this)."::readOrderList sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        
        if ($resql) {
            $num=$this->db->num_rows($resql);
            for ($i = 0;$i < $num; $i++) {
                $obj = $this->db->fetch_object($resql);
                $row = null;
                $row->id            = (int) $obj->rowid;
                $row->supplier      = $obj->nom;
                $row->supplier_id   = (int) $obj->socid;
                $row->ref           = $obj->ref;
                $row->ref_supplier  = $obj->ref_supplier;
                $row->orderstatus_id= (int) $obj->fk_statut;
                $row->orderstatus   = $this->LibStatut($obj->fk_statut, false, 1);
                $row->status        = $obj->status;
                if ($langs->transnoentitiesnoconv($obj->mode_code)) {
                    $row->mode      = $langs->transnoentitiesnoconv($obj->mode_code);
                } else {
                    $row->mode      = $obj->mode_label;
                }
                
                array_push($results, $row);
            }
            $this->db->free($resql);
            return $results;
        } else {
            $error="Error ".$this->db->lasterror();
            dol_syslog(get_class($this)."::readOrderList ".$error, LOG_ERR);
            return SQLERROR;
        }   
    }
    
    /**
     * public method to read a list of orderstatusses
     *
     * @return     stdClass result data or error number
     */
    public function readOrderStatus() 
    {
        if (!isset($this->db)) return CONNECTERROR;
        $results = array();
        $row = new stdClass;
        $statut;
        for ($statut = 0; $statut < 10; $statut++) {
            $result = $this->LibStatut($statut, 1);
            $row = null;
            $row->id = $statut;
            $row->status = $result;
            array_push($results, $row);
        }
        return $results;
    }
    
    /**
     * public method to read a list of contac types
     *
     * @return     stdClass result data or error number
     */
    public function readContactTypes()
    {
        if (!isset($this->db)) return CONNECTERROR;
        $results = array();
        $row = new stdClass;
        if (! is_array($result = $this->liste_type_contact())) return $result;
        // add empty type
        $row->id = 0;
        $row->label = '';
        array_push($results, $row);
        foreach ($result as $id => $label) {
            $row = null;
            $row->id = $id;
            $row->label = html_entity_decode($label);
            array_push($results, $row);
        }
        return $results;
    }
    
    /**
     *    Load orderlines from database into memory
     *
     *    @param    stdClass    $params     filter with elements:
     *                          Id of order to load lines from
     *                          warehouse_id 
     *                              warehouse_id x to get qty_stock of 
     *                              warehouse_id -1 will get total qty_stock
     *                              no warehouse_id will split lines in qty_stock by warehouse
     *                          photo_size string with foto size 'mini' or 'small'
     *    @return     stdClass result data or error number
     */
    public function readOrderLine(stdClass $params)
    {
        global $conf;
        
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->fournisseur->commande->lire)) return PERMISSIONERROR;
        dol_include_once('/extdirect/class/ExtDirectProduct.class.php');
            
        $results = array();
        $row = new stdClass;
        $order_id = 0;
        $productAskedQty = array();
        $photoSize = '';
        $includePhoto = false;
    
        if (isset($params->filter)) {
            foreach ($params->filter as $key => $filter) {
                if ($filter->property == 'id') $id=$filter->value;
                if ($filter->property == 'order_id') $order_id=$filter->value;
                if ($filter->property == 'warehouse_id') $warehouse_id=$filter->value;
                if ($filter->property == 'photo_size' && !empty($filter->value)) $photoSize = $filter->value;
                if ($filter->property == 'batch_id') $batchId=$filter->value;
            }
        }
    
        if ($order_id > 0) {
            $this->id=$order_id;
            if (($result = $this->fetch($this->id)) < 0)  return $result;
            if (!$this->error) {
                if (ExtDirect::checkDolVersion(0,'','3.6')) {
                    foreach ($this->lines as $line) {
                        if (!array_key_exists($line->fk_product, $productAskedQty)) {
                            $productAskedQty[$line->fk_product] = $line->qty;
                        } else {
                            $productAskedQty[$line->fk_product] += $line->qty;
                        }
                    }
                }
                
                foreach ($this->lines as $line) {   
                    if ((!isset($id) || ($id == $line->id)) && ($line->fk_product > 0)) {
                        $myprod = new ExtDirectProduct($this->_user->login);
                        if (($result = $myprod->fetch($line->fk_product)) < 0) return $result;
                        if (ExtDirect::checkDolVersion() >= 3.5) {
                            if (($result = $myprod->load_stock('warehouseopen')) < 0) return $result;
                        }
                        
                        if (!empty($warehouse_id) || ($myprod->stock_reel == 0)) {
                            // get orderline with stock of warehouse 
                            $row = null;
                            $row->id = $line->id;
                            $row->origin_id = $this->id;
                            $row->origin_line_id = $line->id;
                            $row->label = $line->product_label;
                            $row->description = $line->description;
                            $row->product_id = $line->fk_product;
                            $row->ref = $line->product_ref;
                            $row->product_label = $line->product_label;
                            $row->product_desc = $line->product_desc;
                            $row->product_type = $line->product->type;
                            $row->barcode = $myprod->barcode?$myprod->barcode:'';
                            $row->barcode_type = $myprod->barcode_type?$myprod->barcode_type:0;
                            if (ExtDirect::checkDolVersion(0,'','3.6')) {
                                //  total qty asked for all same products (in < 3.7 there is no line_id in dispatched table)
                                $row->qty_asked = $productAskedQty[$line->fk_product];
                            } else {
                                $row->qty_asked = $line->qty;
                            }                            
                            $row->tax_tx = $line->tva_tx;
                            $row->localtax1_tx = $line->localtax1_tx;
                            $row->localtax2_tx = $line->localtax2_tx;
                            $row->total_net = $line->total_ht;
                            $row->total_inc = $line->total_ttc;
                            $row->total_tax = $line->total_tva;
                            $row->total_localtax1 = $line->total_localtax1;
                            $row->total_localtax2 = $line->total_localtax2;
                            $row->product_price = $line->pu_ht;
                            $row->rang = $line->id;
                            $row->price = $line->pu_ht-((float) $line->pu_ht * ($line->remise_percent/100));
                            $row->reduction_percent = $line->remise_percent;
                            $row->ref_supplier = $line->ref_supplier;
                            $row->date_start = $line->date_start;
                            $row->date_end = $line->date_end;
                            // qty shipped for product line
                            $row->qty_shipped = $this->getDispatched($line->id, $line->fk_product, $line->qty);
                            $warehouse_id ? $row->stock = (float) $myprod->stock_warehouse[$warehouse_id]->real : $row->stock = $myprod->stock_reel;
                            $row->total_stock = $myprod->stock_reel;
                            $row->desiredstock = $myprod->desiredstock;
                            $row->warehouse_id = $warehouse_id;
                            $row->has_batch = $myprod->status_batch;
                            $row->has_photo = 0;
                            if (!empty($photoSize)) {
                                $myprod->fetchPhoto($row, $photoSize);
                            }
                        	if (empty($batchId)) {
                                array_push($results, $row);
                            } else {
                                if (($res = $myprod->fetchBatches($results, $row, $line->id, $warehouse_id, $myprod->stock_warehouse[$warehouse_id]->id, false, $batchId)) < 0) return $res;
                            }
                        } else {
                        	// read list of oderlines split by warehouse stock (to show stock available in all warehouse)
                            foreach ($myprod->stock_warehouse as $warehouse=>$stock_warehouse) {
                                $row = null;
                                $row->id = $line->id.'_'.$warehouse;
                                $row->origin_id = $this->id;
                                $row->origin_line_id = $line->id;
                                $row->label = $line->product_label;
                                $row->description = $line->description;
                                $row->product_id = $line->fk_product;
                                $row->ref = $line->product_ref;
                                $row->product_label = $line->product_label;
                                $row->product_desc = $line->product_desc;
                                $row->product_type = $line->product->type;
                                $row->barcode = $myprod->barcode?$myprod->barcode:'';
                                $row->barcode_type = $myprod->barcode_type?$myprod->barcode_type:0;
                                $row->qty_asked = $line->qty;
                                $row->tax_tx = $line->tva_tx;
                                $row->localtax1_tx = $line->localtax1_tx;
                                $row->localtax2_tx = $line->localtax2_tx;
                                $row->total_net = $line->total_ht;
                                $row->total_inc = $line->total_ttc;
                                $row->total_tax = $line->total_tva;
                                $row->total_localtax1 = $line->total_localtax1;
                                $row->total_localtax2 = $line->total_localtax2;
                                $row->product_price = $line->pu_ht;
                                $row->rang = $line->id;
                                $row->price = $line->pu_ht-((float) $line->pu_ht * ($line->remise_percent/100));
                                $row->reduction_percent = $line->remise_percent;
                                $row->ref_supplier = $line->ref_supplier;
                                $row->date_start = $line->date_start;
                                $row->date_end = $line->date_end;
                                // qty shipped for each product line limited to qty asked, if > qty_asked and more lines of same product move to next orderline of same product
                                $row->qty_shipped = $this->getDispatched($line->id, $line->fk_product, $line->qty, $warehouse);
                                $row->stock = (float) $stock_warehouse->real;
                                $row->total_stock = $myprod->stock_reel;
                                $row->desiredstock = $myprod->desiredstock;
                                $row->warehouse_id = $warehouse;
                                $row->has_batch = $myprod->status_batch;
                                $row->has_photo = 0;
                                if (!empty($photoSize)) {
                                    $myprod->fetchPhoto($row, $photoSize);
                                }
                                if (empty($batchId)) {
                                    array_push($results, $row);
                                } else {
                                    if (($res = $myprod->fetchBatches($results, $row, $line->id.'_'.$warehouse, $warehouse, $stock_warehouse->id, false, $batchId)) < 0) return $res;
                                }
                            }
                        }
                    }
                }
            } else {
                return 0;
            }
        }
        return $results;
    }
    
    /**
     * Ext.direct method to Create Orderlines
     *
     * @param unknown_type $param object or object array with product model(s)
     * @return Ambigous <multitype:, unknown_type>|unknown
     */
    public function createOrderLine($param) 
    {
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->fournisseur->commande->creer)) return PERMISSIONERROR;
        $orderLine = new CommandeFournisseurLigne($this->db);
        
        $notrigger=0;
        $paramArray = ExtDirect::toArray($param);
    
        foreach ($paramArray as &$params) {
            // prepare fields
            $this->prepareOrderLineFields($params, $orderLine);
            $this->id = $params->origin_id;
            if (($result = $this->fetch($this->id)) < 0)   return $result; //fetch multicurrency data
            if (($result = $this->addline(
                $orderLine->desc,
                $orderLine->subprice,
                $orderLine->qty,
                $orderLine->tva_tx,
                $orderLine->localtax1_tx,
                $orderLine->localtax2_tx,
                $orderLine->fk_product,
                $params->ref_supplier_id,
                $orderLine->ref_fourn,           
                $orderLine->remise_percent,
                'HT',
                0,
                $orderLine->product_type,
                0,
                false,
                $orderLine->date_start,
                $orderLine->date_end
            )) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);   
            $params->id = $result;         
        }
    
        if (is_array($param)) {
            return $paramArray;
        } else {
            return $params;
        }
    }
    
    /**
     * Ext.direct method to update orderlines
     *
     * @param unknown_type $param object or object array with order model(s)
     * @return Ambigous <multitype:, unknown_type>|unknown
     */
    public function updateOrderLine($param) 
    {
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->fournisseur->commande->receptionner)) return PERMISSIONERROR;
        dol_include_once('/extdirect/class/ExtDirectProduct.class.php');
        $orderlineUpdated = false;
        $paramArray = ExtDirect::toArray($param);
    
        foreach ($paramArray as &$params) {
            
            if (($this->id=$params->origin_id) > 0) {
                // get old orderline
                if (($result = $this->fetch($this->id)) < 0)    return $result;
                if (!$this->error) {
                    
                    foreach ($this->lines as $orderLine) {
                        if ($orderLine->id == $params->origin_line_id) {
                        	if (($updated = $this->prepareOrderLineFields($params, $orderLine)) && isset($this->_user->rights->fournisseur->commande->creer)) {
	                            if ($this->statut == 0) {
	                                // update fields
	                                if (($result = $this->updateline(
	                                                $orderLine->id,
	                                                $orderLine->desc,
	                                                $orderLine->subprice,
	                                                $orderLine->qty,
	                                                $orderLine->remise_percent,
	                                                $orderLine->tva_tx,
	                                                $orderLine->total_localtax1,
	                                                $orderLine->total_localtax2,
	                                                'HT',
	                                                $orderLine->info_bits,
	                                                $orderLine->product_type,
	                                                false,
	                                                $orderLine->date_start,
	                                                $orderLine->date_end
	                                )) < 0)  return ExtDirect::getDolError($result, $this->errors, $this->error);
	                            }
                        	}
                        	
                            $product = new ExtDirectProduct($this->_user->login);
                            if (($result = $product->fetch($orderLine->fk_product)) <0) return ExtDirect::getDolError($result, $product->errors, $product->error);
                            if (($updated = $this->prepareProductFields($params, $product)) && isset($this->_user->rights->produit->creer)) {
	                            // update barcode
	                            if ($updated) {
	                                $product->setValueFrom('barcode', $product->barcode);
	                                $product->setValueFrom('fk_barcode_type', $product->barcode_type);
	                            }
	                            
                            }
                        	// add photo
                            if (!empty($params->has_photo) && !empty($params->photo) && isset($this->_user->rights->produit->creer)) {
                                if (($result = $product->addBase64Jpeg($params->photo)) < 0) return ExtDirect::getDolError($result, $product->errors, $product->error);
                            }
                            
                            // update unit price
                        	$supplierProduct = new ProductFournisseur($this->db);
                        	$supplierProducts = $supplierProduct->list_product_fournisseur_price($product->id);
				            if (is_array($supplierProducts)) {
					            foreach ($supplierProducts as $prodsupplier) {
					                if ($prodsupplier->fourn_ref == $params->ref_supplier){
					                    $supplierProduct->product_fourn_price_id = $prodsupplier->product_fourn_price_id;
					                   	$supplierProduct->fourn_id = $prodsupplier->fourn_id;
					                	$supplierProduct->fourn_qty = $prodsupplier->fourn_qty;
				                        if (isset($prodsupplier->fourn_tva_tx)) { // workaround
				                            $supplierProduct->fourn_tva_tx = $prodsupplier->fourn_tva_tx;
				                        } else {
				                            $supplierProduct->fourn_tva_tx = $prodsupplier->tva_tx;
				                        } 
				                        $supplierProduct->fetch_product_fournisseur_price($supplierProduct->product_fourn_price_id);
					                }
					            }
				            }
		                    if (!empty($supplierProduct->fourn_unitprice) && !empty($supplierProduct->product_fourn_price_id)) {
			                    $supplier = new Societe($this->db);
			                    if (($result = $supplier->fetch($supplierProduct->fourn_id)) < 0) return $result;	
	                    		if (($updated = $this->prepareProdSupplierFields($params, $supplierProduct)) && isset($this->_user->rights->produit->creer)) {
			                    	if (($result = $supplierProduct->update_buyprice(
				                                    $supplierProduct->fourn_qty, 
				                                    $supplierProduct->fourn_unitprice * $supplierProduct->fourn_qty,
				                                    $this->_user, 
				                                    'HT', 
				                                    $supplier, 
				                                    0, 
				                                    $supplierProduct->ref_supplier, 
				                                    $supplierProduct->fourn_tva_tx
				                    )) < 0) return ExtDirect::getDolError($result, $supplierProduct->errors, $supplierProduct->error);
			                    }
                          	}
                          	// dispatch
	                        if (($this->statut == 3 || $this->statut == 4 || $this->statut == 5) && ($params->qty_shipped > 0)) {
		                        if (($result = $this->DispatchProduct(
		                                        $this->_user,
		                                        $orderLine->fk_product,
		                                        $params->qty_shipped,
		                                        $params->warehouse_id,
		                                        $params->price, // must be ordered unitprice with discount
		                                        $params->comment,
		                                        ExtDirect::dateTimeToDate($params->eatby),
		                                        ExtDirect::dateTimeToDate($params->sellby),
		                                        $params->batch,
		                                        $orderLine->id
		                        )) < 0)  return ExtDirect::getDolError($result, $this->errors, $this->error);
		                    }
                        }
                    }
                }           
            } else {
                return PARAMETERERROR;
            }
        }
        if (is_array($param)) {
            return $paramArray;
        } else {
            return $params;
        }
    }
    
    /**
     * Ext.direct method to destroy orderlines
     *
     * @param unknown_type $param object or object array with order model(s)
     * @return Ambigous <multitype:, unknown_type>|unknown
     */
    public function destroyOrderLine($param) 
    {
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->fournisseur->commande->supprimer)) return PERMISSIONERROR;
        $paramArray = ExtDirect::toArray($param);
    
        foreach ($paramArray as &$params) {
            // prepare fields
            if ($params->origin_line_id) {
                // delete 
                $this->id = $params->origin_id;
                if (($result = $this->deleteline($params->origin_line_id)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
            } else {
                return PARAMETERERROR;
            }
        }
    
        if (is_array($param)) {
            return $paramArray;
        } else {
            return $params;
        }
    }
    
    /**
     * private method the get qty dispatched for product
     * 
     * @param int $lineId supplier order line id
     * @param int $productId product to get qty ditpatched
     * @param int $qtyAsked ordered line qty
     * @param int $warehouseId warehouse id
     * 
     * @return int qty
     */
    private function getDispatched($lineId, $productId, $qtyAsked, $warehouseId = 0) {
        static $dispatchedProducts = array();
        static $totalDispatchedProducts = array(); // in case of orderlines of same product
        
        $dispatched = 0;
        $qtyShipped = 0;
        
        if ($productId) {
            $sql = "SELECT sum(cfd.qty) as qty";
            $sql.= " FROM ".MAIN_DB_PREFIX."commande_fournisseur_dispatch as cfd";
            $sql.= " WHERE cfd.fk_commande = ".$this->id;
            $sql.= " AND cfd.fk_product = ".$productId;
            if (!empty($lineId) && ExtDirect::checkDolVersion(0,'3.7','')) {
                $sql.= " AND cfd.fk_commandefourndet = ".$lineId;
            }
            if ($warehouseId > 0) {
                $sql.= " AND cfd.fk_entrepot = ".$warehouseId;
            }
            $resql=$this->db->query($sql);
            if ($resql)
            {
                $obj = $this->db->fetch_object($resql);
                if (!empty($obj->qty)) {
                    $dispatched = price2num($obj->qty, 5);
                }
                $this->db->free($resql);
            }
            
            if (!empty($lineId) && ExtDirect::checkDolVersion(0,'3.7','')) {
                $qtyShipped = $dispatched;
            } else {
                // assemble qtyshipped from products for Dolibarr < 3.7
                // accept lines with same product for orderline list
                if ($warehouseId > 0) {
                    if (!array_key_exists($productId, $dispatchedProducts[$warehouseId])) {
                        $qtyShipped = $dispatched;
                        if ($qtyShipped > $qtyAsked) {
                            $dispatchedProducts[$warehouseId][$productId]=$qtyShipped - $qtyAsked;
                             
                            $qtyShipped = $qtyAsked;
                        } else {
                            $dispatchedProducts[$warehouseId][$productId]=0;
                        }
                
                    } else {
                        if ($dispatchedProducts[$warehouseId][$productId]) {
                            $qtyShipped = $dispatchedProducts[$warehouseId][$productId];
                            if ($qtyShipped > $qtyAsked) {
                                $qtyShipped = $qtyAsked;
                            }
                            if ($totalDispatchedProducts[$warehouseId][$productId] > $qtyShipped) {
                                $qtyShipped = $dispatched - $totalDispatchedProducts[$warehouseId][$productId];
                            }
                        }
                    }
                } else {
                    $qtyShipped = $dispatched;
                }
                $totalDispatchedProducts[$warehouseId][$productId] += $qtyShipped;
            }            
        }   
        
        return $qtyShipped;
    }
    
    /**
     * private method to copy orderline fields into dolibarr orderline object
     *
     * @param stdclass $params object with fields
     * @param stdclass $orderLine object
     * @return null
     */
    private function prepareOrderLineFields($params,$orderLine) 
    {
        $diff = false;
        $diff = ExtDirect::prepareField($diff, $params, $orderLine, 'origin_line_id', 'id');
        $diff = ExtDirect::prepareField($diff, $params, $orderLine, 'product_id', 'fk_product');
        $diff = ExtDirect::prepareField($diff, $params, $orderLine, 'product_price', 'subprice');
        $diff = ExtDirect::prepareField($diff, $params, $orderLine, 'product_tax', 'tva_tx');
        $diff = ExtDirect::prepareField($diff, $params, $orderLine, 'description', 'desc');
        $diff = ExtDirect::prepareField($diff, $params, $orderLine, 'qty_asked', 'qty');
        $diff = ExtDirect::prepareField($diff, $params, $orderLine, 'reduction_percent', 'remise_percent');
        $diff = ExtDirect::prepareField($diff, $params, $orderLine, 'product_type', 'product_type');
        $diff = ExtDirect::prepareField($diff, $params, $orderLine, 'total_localtax1', 'total_localtax1');
        $diff = ExtDirect::prepareField($diff, $params, $orderLine, 'total_localtax2', 'total_localtax2');
        $diff = ExtDirect::prepareField($diff, $params, $orderLine, 'date_start', 'date_start');
        $diff = ExtDirect::prepareField($diff, $params, $orderLine, 'date_end', 'date_end');
        $diff = ExtDirect::prepareField($diff, $params, $orderLine, 'ref_supplier', 'ref_fourn');
        return $diff;
    }
    
	/**
     * private method to copy orderline fields into dolibarr supplier product object
     *
     * @param stdclass $params object with fields
     * @param stdclass $prodSupplier object
     * @return null
     */
    private function prepareProdSupplierFields($params,$prodSupplier) 
    {
        $diff = false;
        $diff = ExtDirect::prepareField($diff, $params, $prodSupplier, 'product_price', 'fourn_unitprice');
        $diff = ExtDirect::prepareField($diff, $params, $prodSupplier, 'ref_supplier', 'ref_supplier');
        $diff = ExtDirect::prepareField($diff, $params, $prodSupplier, 'ref_supplier_id', 'product_fourn_price_id');
        return $diff;
    }
    
	/**
     * private method to copy orderline fields into dolibarr product object
     *
     * @param stdclass $params object with fields
     * @param stdclass $product object
     * @return null
     */
    private function prepareProductFields($params,$product) 
    {
        $diff = false;
        $diff = ExtDirect::prepareField($diff, $params, $product, 'product_id', 'id');
        $diff = ExtDirect::prepareField($diff, $params, $product, 'barcode', 'barcode');
        $diff = ExtDirect::prepareField($diff, $params, $product, 'barcode_type', 'barcode_type');
        return $diff;
    }
}
