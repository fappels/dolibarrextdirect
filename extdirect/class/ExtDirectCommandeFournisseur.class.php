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
 *  \file       htdocs/extdirect/class/ExtDirectCommandeFournisseur.class.php
 *  \brief      Sencha Ext.Direct supplier orders remoting class
 */

require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/extdirect/class/extdirect.class.php');

/** ExtDirectCommandeFournisseur class
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
                if (ExtDirect::checkDolVersion() >= 3.3) {
                    parent::__construct($db);
                } else {
                    $this->db = $db;
                }
            }
        }
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
                    foreach($orderstatus_ids as $orderstatus_id) {
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
                        if (isset($this->_user->rights->fournisseur->commande->valider)) {
                            $result = $this->valid($this->_user);
                        } else {
                            return PERMISSIONERROR;
                        }
                        break;
                    case 2:
                        if (isset($this->_user->rights->fournisseur->commande->approuver)) {
                            $result = $this->approve($this->_user,$params->warehouse_id);
                        } else {
                            return PERMISSIONERROR;
                        }
                        break;                    
                    case 3:
                        if (isset($this->_user->rights->fournisseur->commande->commander)) {
                            $result = $this->commande($this->_user, $this->date_commande, $this->methode_commande_id, $params->comment);
                        } else {
                            return PERMISSIONERROR;
                        }
                        break;
                    case 4:
                        if (isset($this->_user->rights->fournisseur->commande->receptionner)) {
                            $result = $this->Livraison($this->_user, $this->date_livraison, 'par', $params->comment);
                        } else {
                            return PERMISSIONERROR;
                        }
                        break;
                    case 5:
                        if (isset($this->_user->rights->fournisseur->commande->receptionner)) {
                            $result = $this->Livraison($this->_user, $this->date_livraison, 'tot', $params->comment);
                        } else {
                            return PERMISSIONERROR;
                        }
                        break;
                    case 6:
                        if (isset($this->_user->rights->fournisseur->commande->commander)) {
                            $result = $this->Cancel($this->_user);
                        } else {
                            return PERMISSIONERROR;
                        }
                        break;
                    case 7:
                        if (isset($this->_user->rights->fournisseur->commande->receptionner)) {
                            $result = $this->Livraison($this->_user, $this->date_livraison, 'nev', $params->comment);
                        } else {
                            return PERMISSIONERROR;
                        }
                        break;
                    case 9:
                        if (isset($this->_user->rights->fournisseur->commande->approuver)) {
                            $result = $this->refuse($this->_user);
                        } else {
                            return PERMISSIONERROR;
                        }
                        break;
                    default:
                        break;   
                }
                if ($result < 0) return $result;
                if (($result = $this->set_date_livraison($this->_user, $this->date_livraison)) < 0) return $result;
                if (isset($this->cond_reglement_id) &&
                    ($result = $this->setPaymentTerms($this->cond_reglement_id)) < 0) return $result;
                if (isset($this->mode_reglement_id) &&
                    ($result = $this->setPaymentMethods($this->mode_reglement_id)) < 0) return $result;
                
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
                if (($result = $this->delete($this->_user)) < 0)    return $result;
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
     * public method to read a list of orders
     *
     * @param stdClass $params to filter on order status and ref
     * @return     stdClass result data or error number
     */
    public function readOrderList(stdClass $params) 
    {
        global $conf;
        
        if (!isset($this->db)) return CONNECTERROR;
        $results = array();
        $row = new stdClass;
        $statusFilterCount = 0;
        $ref = null;
        $contactTypeId = 0;
        $barcode = null;
        if (isset($params->filter)) {
            foreach ($params->filter as $key => $filter) {
                if ($filter->property == 'orderstatus_id') $orderstatus_id[$statusFilterCount++]=$filter->value;
                if ($filter->property == 'ref') $ref=$filter->value;
                if ($filter->property == 'contacttype_id') $contactTypeId = $filter->value;
                if ($filter->property == 'contact_id') $contactId = $filter->value;
                if ($filter->property == 'barcode') $barcode = $filter->value;
            }
        }
        
        $sql = "SELECT DISTINCT s.nom, s.rowid AS socid, c.rowid, c.ref, c.ref_supplier, c.fk_statut, ea.status";
        $sql.= " FROM ".MAIN_DB_PREFIX."societe as s, ".MAIN_DB_PREFIX."commande_fournisseur as c";
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."commande_fournisseurdet as cd ON c.rowid = cd.fk_commande";
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = cd.fk_product";
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."element_contact as ec ON c.rowid = ec.element_id";
        $sql.= " LEFT JOIN ("; // get latest extdirect activity status for commande to check if locked
        $sql.= "   SELECT ma.activity_id, ma.maxrow AS rowid, ea.status";
        $sql.= "   FROM (";
        $sql.= "    SELECT MAX( rowid ) AS maxrow, activity_id";
        $sql.= "    FROM ".MAIN_DB_PREFIX."extdirect_activity";
        $sql.= "    GROUP BY activity_id";
        $sql.= "   ) AS ma, ".MAIN_DB_PREFIX."extdirect_activity AS ea";
        $sql.= "   WHERE ma.maxrow = ea.rowid";
        $sql.= " ) AS ea ON c.rowid = ea.activity_id";
        $sql.= " WHERE c.entity = ".$conf->entity;
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
        $dispatchedProducts = array();
    
        if (isset($params->filter)) {
            foreach ($params->filter as $key => $filter) {
                if ($filter->property == 'id') $id=$filter->value;
                if ($filter->property == 'order_id') $order_id=$filter->value;
                if ($filter->property == 'warehouse_id') $warehouse_id=$filter->value;
            }
        }
    
        if ($order_id > 0) {
            $this->id=$order_id;
            if (($result = $this->fetch($this->id)) < 0)  return $result;
            if (!$this->error) {
                foreach ($this->lines as $line) {
                    // accept lines with same product
                    if (!array_key_exists($line->fk_product, $dispatchedProducts)) {
                        $qtyShipped = $this->getDispatched($line->fk_product);
                        if ($qtyShipped > $line->qty) {
                            $dispatchedProducts[$line->fk_product]=$qtyShipped - $line->qty;
                            $qtyShipped = $line->qty;
                        } else {
                            $dispatchedProducts[$line->fk_product]=0;
                        }
                    } else {
                        if ($dispatchedProducts[$line->fk_product]) {
                            $qtyShipped = $dispatchedProducts[$line->fk_product];
                        }
                    }
                    if ((!isset($id) || ($id == $line->id)) && ($line->fk_product > 0)) {
                        $myprod = new ExtDirectProduct($this->_user->login);
                        if (($result = $myprod->fetch($line->fk_product)) < 0) return $result;
                        if (ExtDirect::checkDolVersion() >= 3.5) {
                            if (($result = $myprod->load_stock()) < 0) return $result;
                        }
                        if (!empty($warehouse_id) || ($myprod->stock_reel == 0)) {
                            if ($warehouse_id == -1) {
                                // get orderline with complete stock
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
                                $row->qty_shipped = $qtyShipped;
                                $row->stock = (int) $myprod->stock_reel;
                                $row->warehouse_id = $warehouse_id;
                                $row->has_batch = $myprod->status_batch;
                                array_push($results, $row);
                            } else {
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
                                $row->qty_shipped = $qtyShipped;
                                $row->stock = (int) $myprod->stock_warehouse[$warehouse_id]->real;
                                $row->warehouse_id = $warehouse_id;
                                $row->has_batch = $myprod->status_batch;
                                array_push($results, $row);
                            }
                        } else {
                            $qtyToAsk=$line->qty;
                            foreach ($myprod->stock_warehouse as $warehouse=>$stock_warehouse) {
                                if (($stockReal = (int) $stock_warehouse->real > 0)) {
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
                                    // limit qty asked to stock qty
                                    if ($qtyToAsk > $stockReal) {
                                        $row->qty_asked = $stockReal;
                                        $qtyToAsk = $line->qty - $stockReal;
                                    } else {
                                        $row->qty_asked = $qtyToAsk;
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
                                    $row->qty_shipped = $qtyShipped;
                                    $row->stock = $stock_warehouse->real;
                                    $row->warehouse_id = $warehouse;
                                    $row->has_batch = $myprod->status_batch;
                                    array_push($results, $row);
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
            $this->id = $orderLine->fk_commande;
            if (($result = $this->addline(
                $orderLine->desc,
                $orderLine->subprice,
                $orderLine->qty,
                $orderLine->tva_tx,
                $orderLine->localtax1_tx,
                $orderLine->localtax2_tx,
                $orderLine->fk_product,
                $orderLine->ref_fourn_id,
                $orderLine->ref_fourn,           
                $orderLine->remise_percent,
                'HT',
                0,
                $orderLine->product_type,
                0,
                false,
                $orderLine->date_start,
                $orderLine->date_end
            )) < 0) return $result;            
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
        if (!isset($this->_user->rights->fournisseur->commande->creer)) return PERMISSIONERROR;
        $orderlineUpdated = false;
        $paramArray = ExtDirect::toArray($param);
    
        foreach ($paramArray as &$params) {
            
            if (($this->id=$params->origin_id) > 0) {
                // get old orderline
                if (($result = $this->fetch($this->id)) < 0)    return $result;
                if (!$this->error) {
                    if ($params->qty_shipped > 0) {
                        if (($result = $this->DispatchProduct(
                                        $this->_user,
                                        $params->product_id,
                                        $params->qty_shipped,
                                        $params->warehouse_id,
                                        $params->product_price,
                                        $params->comment,
                                        $params->eatby,
                                        $params->sellby,
                                        $params->batch
                        )) < 0)  return $result;
                    } else {
                        foreach ($this->lines as $orderLine) {
                            if ($orderLine->id == $params->origin_line_id) {
                                // update fields
                                $this->prepareOrderLineFields($params, $orderLine);
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
                                )) < 0)  return $result;
                                $orderlineUpdated = true;
                            }
                        }
                        if (!$orderlineUpdated) return UPTADEERROR;
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
                if (($result = $this->deleteline($params->origin_line_id)) < 0)   return $result;
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
     * @param int $productId product to get qty ditpatched
     * @return int qty
     */
    private function getDispatched($productId) {
        $dispatched = 0;
        if ($productId) {
            $sql = "SELECT sum(cfd.qty) as qty";
            $sql.= " FROM ".MAIN_DB_PREFIX."commande_fournisseur_dispatch as cfd";
            $sql.= " WHERE cfd.fk_commande = ".$this->id;
            $sql.= " AND cfd.fk_product = ".$productId;
            $resql=$this->db->query($sql);
            if ($resql)
            {
                $obj = $this->db->fetch_object($resql);
                if (!empty($obj->qty)) {
                    $dispatched = $obj->qty;
                }
                $this->db->free($resql);
            }
        }       
        return $dispatched;
    }
    
    /**
     * private method to copy order fields into dolibarr object
     *
     * @param stdclass $params object with fields
     * @param stdclass $orderLine object
     * @return null
     */
    private function prepareOrderLineFields($params,$orderLine) 
    {
        isset($params->origin_line_id) ? ( $orderLine->id= $params->origin_line_id) : null;
        isset($params->origin_id) ? ( $orderLine->fk_commande= $params->origin_id) : null;
        isset($params->product_id) ? ( $orderLine->fk_product = $params->product_id) : null;
        isset($params->product_price) ? ( $orderLine->subprice = $params->product_price) : null;
        isset($params->product_tax) ? ( $orderLine->tva_tx = $params->product_tax) : null;
        isset($params->description) ? ( $orderLine->desc = $params->description) : null;
        isset($params->qty_asked) ? ( $orderLine->qty = $params->qty_asked) : null;
        isset($params->reduction_percent) ? ($orderLine->remise_percent = $params->reduction_percent) : $orderLine->remise_percent = 0;
        isset($params->product_type) ? ($orderLine->product_type = $params->product_type) : null;
        isset($params->total_localtax1) ? ($orderLine->total_localtax1 = $params->total_localtax1) : null;
        isset($params->total_localtax2) ? ($orderLine->total_localtax2 = $params->total_localtax2) : null;
        isset($params->date_start) ? ($orderLine->date_start = $params->date_start) : null;
        isset($params->date_end) ? ($orderLine->date_end = $params->date_end) : null; 
        isset($params->ref_supplier) ? ($orderLine->ref_fourn = $params->ref_supplier) : null;
        isset($params->ref_supplier_id) ? ($orderLine->ref_fourn_id = $params->ref_supplier_id) : null;
    }
}
