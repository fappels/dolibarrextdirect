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
 *  \file       htdocs/extdirect/class/ExtDirectExpedition.class.php
 *  \brief      Sencha Ext.Direct shipments remoting class
 */

require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
dol_include_once('/extdirect/class/extdirect.class.php');

/** ExtDirectExpedition class
 * Class to access shipments with CRUD methods to connect to Extjs or sencha touch using Ext.direct connector
 */
class ExtDirectExpedition extends Expedition
{
    private $_user;
    
    /** Constructor
     *
     * @param string $login user name
     */
    public function __construct($login) 
    {
        global $langs,$user,$db;
        
        if (!empty($login)) {
            if ($user->fetch('', $login)>0) {
                $user->getrights();
                $this->_user = $user;  //commande.class uses global user
                if (isset($this->_user->conf->MAIN_LANG_DEFAULT) && ($this->_user->conf->MAIN_LANG_DEFAULT != 'auto')) {
                    $langs->setDefaultLang($this->_user->conf->MAIN_LANG_DEFAULT);
                }
                $langs->load("sendings");
                $this->db = $db;
            }
        }
    }
    
    
    /**
     *    Load shipment from database into memory
     *
     *    @param    stdClass    $params     filter with elements:
     *      id                  Id of order to load
     *    @return     stdClass result data or -1
     */
    public function readShipment(stdClass $params)
    {
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->commande->lire)) return PERMISSIONERROR;
        $myUser = new User($this->db);
        $mySociete = new Societe($this->db);
        $results = array();
        $row = new stdClass;
        $id = 0;
        $ref = '';
        $ref_ext = '';
        $ref_int = '';

        if (isset($params->filter)) {
            foreach ($params->filter as $key => $filter) {
                if ($filter->property == 'id') $id=$filter->value;
                else if ($filter->property == 'ref') $ref=$filter->value;
                else if ($filter->property == 'ref_int') $ref_int=$filter->value;
            }
        }
        
        if (($id > 0) || ($ref != '') || ($ref_int != '')) {
            if (($result = $this->fetch($id, $ref, $ref_ext, $ref_int)) < 0) {
                if ($result = -2) {
                    return 0;// return 0 whem not found
                } else {
                    return $result;
                }
            }
            if (!$this->error) {
                $row->id = $this->id ;
                //! Ref
                $row->ref= $this->ref;
                $row->ref_customer= $this->ref_customer;
                $row->customer_id = $this->socid;
                if ($mySociete->fetch($this->socid)>0) {
                    $row->customer_name = $mySociete->name;
                }
                //! -1 for cancelled, 0 for draft, 1 for validated, 2 for processed
                $row->orderstatus_id = (int) $this->statut;
                $row->orderstatus = $this->getLibStatut(1);
                $row->note_private = '';
                $row->note_public = '';
                $row->user_id = $this->user_author_id;
                if ($myUser->fetch($this->user_author_id)>0) {
                    $row->user_name = $myUser->firstname . ' ' . $myUser->lastname;
                }
                $row->order_date = $this->date_expedition;
                $row->deliver_date= $this->date_delivery;
                $row->origin_id = $this->origin_id;
                $row->origin = $this->origin;
                $row->weight_units = $this->weight_units;
                $row->weight = $this->weight;
                $row->size_units = $this->size_units;
                $row->trueDepth = $this->trueDepth;
                $row->trueWidth = $this->trueWidth;
                $row->trueHeight = $this->trueHeight;
                
                array_push($results, $row);
            } else {
                return 0;
            }
        }
        
        return $results;
    }


    /**
     * Ext.direct method to Create Shipment
     * 
     * @param unknown_type $param object or object array with shipment record
     * @return result data or -1
     */
    public function createShipment($param) 
    {
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->commande->creer)) return PERMISSIONERROR;
        $paramArray = ExtDirect::toArray($param);
        
        foreach ($paramArray as &$params) {
            // prepare fields
            $this->prepareFields($params);
            
            if (($result = $this->create($this->_user)) < 0)    return $result;
            
            $params->id=$this->id;
        }

        if (is_array($param)) {
            return $paramArray;
        } else {
            return $params;
        }
    }

    /**
     * Ext.direct method to update shipment
     * 
     * @param unknown_type $param object or object array with shipment record
     * @return result data or -1
     */
    public function updateShipment($param) 
    {
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->commande->valider)) return PERMISSIONERROR;
        $paramArray = ExtDirect::toArray($param);

        foreach ($paramArray as &$params) {
            // prepare fields
            if ($params->id) {
                $id = $params->id;
                if (($result = $this->fetch($id)) < 0)  return $result;
                $this->prepareFields($params);
                // update
                switch ($params->orderstatus_id) {
                    case -1:
                        
                        break;
                    case 0:
                        
                        break;
                    case 1:
                        $result = $this->valid($this->_user);
                        break;
                    case 2:
                        
                        break;
                    default:
                        break;   
                }
                if ($result < 0) return $result;
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
     * Ext.direct method to destroy shipment
     * 
     * @param unknown_type $param object or object array with shipment record
     * @return result data or -1
     */
    public function destroyShipment($param) 
    {
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->commande->supprimer)) return PERMISSIONERROR;
        $paramArray = ExtDirect::toArray($param);

        foreach ($paramArray as &$params) {
            // prepare fields
            if ($params->id) {
                $this->id = $params->id;
                if (($result = $this->fetch($this->id)) < 0)    return $result;
                // delete 
                if (($result = $this->delete()) < 0)    return $result;
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
     * private method to copy fields into dolibarr object
     * 
     * @param stdclass $params object with fields
     * @return null
     */
    private function prepareFields($params) 
    {
        isset($params->origin_id) ? ( $this->origin_id = $params->origin_id) : isset($this->origin_id)?null:$this->origin_id=null;
        isset($params->origin) ? ( $this->origin = $params->origin) : isset($this->origin)?null:( $this->origin = null);
        isset($params->ref_int) ? ( $this->ref_int = $params->ref_int) : isset($this->ref_int)?null:( $this->ref_int = null);
        isset($params->ref_customer) ? ( $this->ref_customer = $params->ref_customer) : isset($this->ref_customer)?null:( $this->ref_customer = null);
        isset($params->customer_id) ? ( $this->socid = $params->customer_id) : isset($this->socid)?null:( $this->socid = null);
        isset($params->deliver_date) ? ( $this->date_delivery =$params->deliver_date) : isset($this->date_delivery)?null:($this->date_delivery = null);
        isset($params->weight_units) ? ( $this->weight_units = $params->weight_units) : isset($this->weight_units)?null:($this->weight_units = 0); 
        isset($params->weight) ? ( $this->weight = $params->weight) : isset($this->weight)?null:($this->weight = 0);
        isset($params->size_units) ? ( $this->size_units = $params->size_units) : isset($this->size_units)?null:($this->size_units = 0);
        isset($params->trueDepth) ? ( $this->sizeS = $params->trueDepth) : isset($this->sizeS)?null:($this->sizeS = 0);
        isset($params->trueWidth) ? ( $this->sizeW = $params->trueWidth) : isset($this->sizeW)?null:($this->sizeW = 0);
        isset($params->trueHeight) ? ( $this->sizeH = $params->trueHeight) : isset($this->sizeH)?null:($this->sizeH = 0);       
    } 
    
    /**
     *    Load shipmentline from database into memory
     *
     *    @param    stdClass    $params     filter with elements:
     *                          origin_id   Id of shipment to load lines from
     *    @return     stdClass result data or -1
     */
    public function readShipmentLine(stdClass $params)
    {
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->commande->lire)) return PERMISSIONERROR;
        $results = array();
        $row = new stdClass;
        $origin_id = 0;
    
        if (isset($params->filter)) {
            foreach ($params->filter as $key => $filter) {
                if ($filter->property == 'origin_id') $origin_id=$filter->value;
            }
        }
    
        if ($origin_id > 0) {
            $this->id=$origin_id;
            if (($result = $this->fetch_lines()) < 0)   return $result;
            if (!$this->error) {
                foreach ($this->lines as $line) {
                    $row->id = $line->rowid ;
                    $row->description = $line->description;
                    $row->product_id = $line->fk_product;
                    $row->product_ref = $line->product_ref;
                    $row->product_label = $line->product_label;
                    $row->product_desc = '';
                    $row->origin_id = $origin_id;
                    $row->qty_asked = $line->qty_asked;
                    $row->qty_shipped = $line->qty_shipped;
                    $row->warehouse_id = $line->entrepot_id;
                    
                    array_push($results, $row);
                }
            } else {
                return SQLERROR;
            }
        }
        return $results;
    }
    
    
    /**
     * Ext.direct method to Create shipmentline
     *
     * @param unknown_type $param object or object array with shipmentline record
     * @return result data or -1
     */
    public function createShipmentLine($param) 
    {
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->commande->creer)) return PERMISSIONERROR;
        $notrigger=0;
        $paramArray = ExtDirect::toArray($param);
        
        foreach ($paramArray as &$params) {
            $this->id=$params->origin_id;
            dol_syslog(get_class($this)."::shipment id=".$this->id, LOG_DEBUG);
            if ($params->origin_id > 0) {
                if (($result = $this->create_line($params->warehouse_id, $params->origin_line_id, $params->qty_toship)) < 0)  return $result;
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
     * Ext.direct method to update shipment line
     *
     * @param unknown_type $param object or object array with shipment record
     * @return result data or -1
     */
    public function updateShipmentLine($param) 
    {
        return PARAMETERERROR;// no update possible
    }
    
    /**
     * Ext.direct method to destroy shipment line
     *
     * @param unknown_type $param object or object array with shipment record
     * @return result data or -1
     */
    public function destroyShipmentLine($param) 
    {
        return PARAMETERERROR;// no destroy possible, will be destroyed when shipment is destroyed
    }
}