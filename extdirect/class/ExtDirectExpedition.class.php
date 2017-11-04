<?PHP

/**
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
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
dol_include_once('/extdirect/class/extdirect.class.php');

/** ExtDirectExpedition class
 * Class to access shipments with CRUD methods to connect to Extjs or sencha touch using Ext.direct connector
 */
class ExtDirectExpedition extends Expedition
{
    private $_user;
    private $_shipmentConstants = array('STOCK_MUST_BE_ENOUGH_FOR_SHIPMENT','STOCK_CALCULATE_ON_SHIPMENT');
    
    const STATUS_DRAFT = 0;
	const STATUS_VALIDATED = 1;
	const STATUS_CLOSED = 2;
    
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
                $langs->load("products");
                $langs->load("stocks");
                $langs->load("productbatch");
                parent::__construct($db);
            }
        }
    }
    
/**
     *	Load shipping related constants
     * 
     *	@param			stdClass	$params		filter with elements
     *		constant	name of specific constant
     *
     *	@return			stdClass result data with specific constant value
     */
    public function readConstants(stdClass $params)
    {
    	if (!isset($this->db)) return CONNECTERROR;
    	if (!isset($this->_user->rights->expedition->lire)) return PERMISSIONERROR;
    	
    	$results = ExtDirect::readConstants($this->db, $params, $this->_user, $this->_shipmentConstants);
    	
    	return $results;
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
        if (!isset($this->_user->rights->expedition->lire)) return PERMISSIONERROR;
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
                    return ExtDirect::getDolError($result, $this->errors, $this->error);
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
                $row->note_private = $this->note_private;
                $row->note_public = $this->note_public;
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
                $row->shipping_method_id = $this->shipping_method_id;
				$row->incoterms_id = $this->fk_incoterms;
				$row->location_incoterms = $this->location_incoterms;
				$row->tracking_number = $this->tracking_number;
				$row->model_pdf = $this->model_pdf;
				$row->create_date = $this->date_creation;
				$row->delivery_address_id = $this->fk_delivery_address;
				$row->ref_int = $this->ref_int;
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
        if (!isset($this->_user->rights->expedition->creer)) return PERMISSIONERROR;
        $paramArray = ExtDirect::toArray($param);
        
        foreach ($paramArray as &$params) {
            // prepare fields
            $this->prepareFields($params);
            
            if (($result = $this->create($this->_user)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
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
        global $conf, $langs, $mysoc;
    	
    	if (!isset($this->db)) return CONNECTERROR;
        
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
                    	// set global $mysoc required to set pdf sender
                    	$mysoc = new Societe($this->db);
		                $mysoc->setMysoc($conf);
                        $result = $this->valid($this->_user);
                		// PDF generating
                        if (($result >= 0) && empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE)) {
                        	$hidedetails = (! empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS) ? 1 : 0);
							$hidedesc = (! empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DESC) ? 1 : 0);
							$hideref = (! empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_REF) ? 1 : 0);
                        	$outputlangs = $langs;
							if ($conf->global->MAIN_MULTILANGS)	{
								$this->fetch_thirdparty();
								$newlang = $this->thirdparty->default_lang;
								$outputlangs = new Translate("", $conf);
								$outputlangs->setDefaultLang($newlang);
							}
							$this->generateDocument($this->modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
                        }
                        break;
                    case 2:
                        
                        break;
                    default:
                        break;   
                }
                if ($result < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
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
        if (!isset($this->_user->rights->expedition->supprimer)) return PERMISSIONERROR;
        $paramArray = ExtDirect::toArray($param);
        
        foreach ($paramArray as &$params) {
            // prepare fields
            if ($params->id) {
                $this->id = $params->id;
                if (($result = $this->fetch($this->id)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
                // delete 
                if (($result = $this->delete()) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
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
        // sizes for create
        isset($params->trueDepth) ? ( $this->sizeS = $params->trueDepth) : isset($this->sizeS)?null:($this->sizeS = 0);
        isset($params->trueWidth) ? ( $this->sizeW = $params->trueWidth) : isset($this->sizeW)?null:($this->sizeW = 0);
        isset($params->trueHeight) ? ( $this->sizeH = $params->trueHeight) : isset($this->sizeH)?null:($this->sizeH = 0);   
        // sizes for update
        isset($params->trueDepth) ? ( $this->trueDepth = $params->trueDepth) : isset($this->trueDepth)?null:($this->trueDepth = 0);
        isset($params->trueWidth) ? ( $this->trueWidth = $params->trueWidth) : isset($this->trueWidth)?null:($this->trueWidth = 0);
        isset($params->trueHeight) ? ( $this->trueHeight = $params->trueHeight) : isset($this->trueHeight)?null:($this->trueHeight = 0);   
        isset($params->shipping_method_id) ? ($this->shipping_method_id = $params->shipping_method_id) : null;
        isset($params->incoterms_id) ? ($this->fk_incoterms = $params->incoterms_id) : null;
        isset($params->location_incoterms) ? ($this->location_incoterms = $params->location_incoterms) : null;    
        isset($params->tracking_number) ? $this->tracking_number = $params->tracking_number : null;
		isset($params->model_pdf) ? $this->model_pdf = $params->model_pdf : null;
		isset($params->note_public) ? $this->note_public = $params->note_public : null;
		isset($params->note_private) ? $this->note_private = $params->note_private : null;
		isset($params->delivery_address_id) ? $this->fk_delivery_address = $params->delivery_address_id : null;
    } 
    
/**
     * public method to read a list of shipments
     *
     * @param stdClass $params to filter on order status and ref
     * @return     stdClass result data or error number
     */
    public function readShipmentList(stdClass $params) 
    {
        global $conf;
        
        if (!isset($this->db)) return CONNECTERROR;
        $results = array();
        $row = new stdClass;
        $statusFilterCount = 0;
        $ref = null;
        $contactTypeId = 0;
        $originId = 0;
        if (isset($params->filter)) {
            foreach ($params->filter as $key => $filter) {
                if ($filter->property == 'orderstatus_id') $orderstatus_id[$statusFilterCount++]=$filter->value;
                if ($filter->property == 'ref') $ref=$filter->value;
                if ($filter->property == 'contacttype_id') $contactTypeId = $filter->value;
                if ($filter->property == 'contact_id') $contactId = $filter->value;
                if ($filter->property == 'origin_id') $originId = $filter->value;
            }
        }
        
        $sql = "SELECT s.nom, s.rowid AS socid, e.rowid, e.ref, e.fk_statut, e.ref_int, ea.status, csm.libelle as mode";
        $sql.= " FROM ".MAIN_DB_PREFIX."societe as s, ".MAIN_DB_PREFIX."expedition as e";
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."element_contact as ec ON e.rowid = ec.element_id";
        if ($originId) {
            $sql.= " INNER JOIN ".MAIN_DB_PREFIX."element_element as el ON el.fk_target = e.rowid AND fk_source = " . $originId;
            $sql.= " AND el.sourcetype = 'commande' AND el.targettype = '".$this->db->escape($this->element)."'";
        }
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."c_shipment_mode as csm ON e.fk_shipping_method = csm.rowid";
        $sql.= " LEFT JOIN ("; // get latest extdirect activity status for commande to check if locked
        $sql.= "   SELECT ma.activity_id, ma.maxrow AS rowid, ea.status";
        $sql.= "   FROM (";
        $sql.= "    SELECT MAX( rowid ) AS maxrow, activity_id";
        $sql.= "    FROM ".MAIN_DB_PREFIX."extdirect_activity";
        $sql.= "    GROUP BY activity_id";
        $sql.= "   ) AS ma, ".MAIN_DB_PREFIX."extdirect_activity AS ea";
        $sql.= "   WHERE ma.maxrow = ea.rowid";
        $sql.= " ) AS ea ON e.rowid = ea.activity_id";
        $sql.= " WHERE e.entity IN (".getEntity('shipping', 1).')';
        $sql.= " AND e.fk_soc = s.rowid";
        
        
        if ($statusFilterCount>0) {
            $sql.= " AND ( ";
            foreach ($orderstatus_id as $key => $fk_statut) {
                $sql .= "e.fk_statut = ".$fk_statut;
                if ($key < ($statusFilterCount-1)) $sql .= " OR ";
            }
            $sql.= ")";
        }
        if ($ref) {
            $sql.= " AND e.ref = '".$ref."'";
        }
        if ($contactTypeId > 0) {
            $sql.= " AND ec.fk_c_type_contact = ".$contactTypeId;
            $sql.= " AND ec.fk_socpeople = ".$contactId;
        }
        $sql .= " ORDER BY e.date_creation DESC";
        
        $resql=$this->db->query($sql);
        
        if ($resql) {
            $num=$this->db->num_rows($resql);
            for ($i = 0;$i < $num; $i++) {
                $obj = $this->db->fetch_object($resql);
                $row = null;
                $row->id            = (int) $obj->rowid;
                $row->customer      = $obj->nom;
                $row->customer_id   = (int) $obj->socid;
                $row->ref           = $obj->ref;
                $row->ref_int           = $obj->ref_int;
                $row->orderstatus_id= (int) $obj->fk_statut;
                $row->orderstatus   = $this->LibStatut($obj->fk_statut, 1);
                $row->status        = $obj->status;
                $row->mode			= $obj->mode;
                array_push($results, $row);
            }
            $this->db->free($resql);
            return $results;
        } else {
            $error="Error ".$this->db->lasterror();
            dol_syslog(get_class($this)."::readOrdelList ".$error, LOG_ERR);
            return SQLERROR;
        }   
    }
    
	/**
     * public method to read a list of shipment statusses
     *
     * @return     stdClass result data or error number
     */
    public function readShipmentStatus() 
    {
        if (!isset($this->db)) return CONNECTERROR;
        $results = array();
        $row = new stdClass;
        $statut = 0;
        while (($result = $this->LibStatut($statut, 1)) !== null) {
            $row = null;
            $row->id = $statut;
            $row->status = $result;
            $statut++;
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
     *    Load shipmentline from database into memory
     *
     *    @param    stdClass    $params     filter with elements:
     *                          origin_id   Id of shipment to load lines from
     *    @return     stdClass result data or -1
     */
    public function readShipmentLine(stdClass $params)
    {
        global $conf;
        
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->expedition->lire)) return PERMISSIONERROR;
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
                foreach ($this->lines as $key => $line) {
                	if (ExtDirect::checkDolVersion() < 3.6) {
			    		$row->id = $key; // no line id available
                        $row->line_id = $key; 
			    	} else {
			    		$row->id = $line->line_id;
                        $row->line_id = $line->line_id;
			    	}
                    $row->origin_line_id = $line->fk_origin_line;
                    $row->description = $line->description;
                    $row->product_id = $line->fk_product;
                    $row->product_ref = $line->product_ref; // deprecated
                    $row->ref = $line->product_ref;
                    $row->product_label = $line->product_label;
                    $row->product_desc = '';
                    $row->origin_id = $origin_id;
                    
                    $row->qty_asked = $line->qty_asked;
                    $row->qty_shipped = $line->qty_shipped;
                    $row->warehouse_id = $line->entrepot_id;
                    // read related batch info
                    if (empty($conf->productbatch->enabled)) {
                        array_push($results, clone $row);
                    } else {                        
                        if (($res = $this->fetchBatches($results, $row, $line->line_id)) < 0) return $res;
                    }
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
     * !!deliver $param sorted by origin_line_id
     *
     * @param unknown_type $param object or object array with shipmentline record
     * @return result data or -1
     */
    public function createShipmentLine($param) 
    {
        global $conf;

        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->expedition->creer)) return PERMISSIONERROR;
        $notrigger=0;
        $paramArray = ExtDirect::toArray($param);
        $batches = array();
        $qtyShipped = 0;
        foreach ($paramArray as &$params) {
            // TODO make prepare fields function for shipment lines, will create a 'detail_batch object array'
            // TODO rewrite with to develop ExpeditionLigne::create function, this function will create a line included batch lines when detail_batch array available
            $this->id=$params->origin_id;
            dol_syslog(get_class($this).'::'.__FUNCTION__." line id=".$params->origin_line_id, LOG_DEBUG);
            if ($params->origin_id > 0) {
                if (!empty($conf->productbatch->enabled) && !empty($params->batch_id)) {
                    if (count($batches) > 0) {
                        $finishBatch = false;
                        foreach ($batches as $batch) {
                            if ($batch->origin_line_id != $params->origin_line_id) {
                                $finishBatch = true;
                            }
                        }
                        if ($finishBatch) {
                            if (($res = $this->finishBatches($batches)) < 0) return $res;
                            $params->line_id=$res;
                            unset($batches);
                            $batches = array();
                            $qtyShipped = $params->qty_toship;
                            array_push($batches, $params);
                        } else {
                            $qtyShipped += $params->qty_toship;
                            array_push($batches, $params);
                        }
                    } else {
                        $qtyShipped = $params->qty_toship;
                        array_push($batches, $params);
                    }                    
                } else {
                    // no batch
                    if (($result = $this->create_line($params->warehouse_id, $params->origin_line_id,  $params->qty_toship)) < 0)  return $result;
                    $params->line_id=$result;
                }                
            } else {
                return PARAMETERERROR;
            }            
        }

        if (!empty($conf->productbatch->enabled) && !empty($batches)) {
            if (($res = $this->finishBatches($batches)) < 0) return $res;
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
		if (ExtDirect::checkDolVersion() < 3.6) {
			return PARAMETERERROR;// no update possible, no line id available
		}
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->expedition->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($param);

		foreach ($paramArray as &$params) {
			// prepare fields
			if (($result = $this->fetch($params->origin_id)) < 0) {
					return ExtDirect::getDolError($result, $this->errors, $this->error);
			}
			// Add a protection to refuse deleting if shipment is not in draft status
			if (($this->statut == self::STATUS_DRAFT) && ($params->line_id)) {
				if (ExtDirect::checkDolVersion(0, '7.0', '')) {
					$line = new ExpeditionLigne($this->db);
				} else {
					$line = new ExtDirectExpeditionLine($this->db);
				}
				$idArray = explode('_', $params->id);
				$line->id = $params->line_id;
				$line->entrepot_id = $params->warehouse_id;
				$line->fk_product = $params->product_id;
				$line->qty = $params->qty_toship;
				$line->detail_batch->id = $idArray[1];
				$line->detail_batch->batch = $params->batch;
				$line->detail_batch->entrepot_id = $params->warehouse_id;
				$line->detail_batch->dluo_qty = $params->qty_toship;
				$line->detail_batch->fk_origin_stock = $params->batch_id;
				if (($result = $line->update()) < 0) return ExtDirect::getDolError($result, $line->errors, $line->error);
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
     * private method to create batch shipment lines
     *
     * @param array $batches array with batch objects
     *
     * @return line_id > 0 OK < 0 KO
     * 
     */

    private function finishBatches($batches)
    {
        // write related batch info
        require_once DOL_DOCUMENT_ROOT.'/expedition/class/expeditionbatch.class.php';
        
        $stockLocationQty = array(); // associated array with batch qty in stock location
        $stockLocationOriginLineId = array(); // associated array with OriginLineId's
        $shipmentLineId = 0;
    	foreach ($batches as $batch)
		{
			if ($batch->warehouse_id)
			{
				$stockLocationQty[$batch->warehouse_id] += $batch->qty_toship;
				$stockLocationOriginLineId[$batch->warehouse_id] = $batch->origin_line_id;				
			}
		}
		foreach ($stockLocationQty as $stockLocation => $qty) {
			
			if (($result = $this->create_line($stockLocation, $stockLocationOriginLineId[$stockLocation], $qty)) < 0)  {
				return $result;
			} else {
				// create shipment batch lines for stockLocation
                if (ExtDirect::checkDolVersion(0, '4.0', '')) {
                    $shipmentLineId = $result;
                } else {
                    $shipmentLineId = $this->db->last_insert_id(MAIN_DB_PREFIX."expeditiondet");
                }
                dol_syslog(get_class($this).'::'.__FUNCTION__." stock location = ".$stockLocation." qty = ".$qty." shipmentLineId = ".$shipmentLineId, LOG_DEBUG);
		        // store colleted batches
		        foreach ($batches as $batch) {
		        	if ($batch->warehouse_id == $stockLocation) {
		        		if (ExtDirect::checkDolVersion(0, '3.8', '')) {
			                $expeditionLineBatch = new ExpeditionLineBatch($this->db);
			            } else {
			                $expeditionLineBatch = new ExpeditionLigneBatch($this->db);
			            }            
			            $expeditionLineBatch->sellby = $batch->sellby;
			            $expeditionLineBatch->eatby = $batch->eatby;
			            $expeditionLineBatch->batch = $batch->batch;
			            $expeditionLineBatch->dluo_qty = $batch->qty_toship;
			            $expeditionLineBatch->fk_origin_stock = $batch->batch_id;
			            $expeditionLineBatch->create($shipmentLineId);
		        	}		            
		        }
			}
		}
		return shipmentLineId;
    }

	/**
	 * Ext.direct method to destroy shipment line
	 *
	 * @param unknown_type $param object or object array with shipment record
	 * @return result data or -1
	 */
	public function destroyShipmentLine($param) 
	{
		if (ExtDirect::checkDolVersion() < 3.6) {
			return PARAMETERERROR;// no destroy possible, no line id available
		}
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->expedition->supprimer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($param);

		foreach ($paramArray as &$params) {
			// prepare fields
			if (($result = $this->fetch($params->origin_id)) < 0) {
				 return ExtDirect::getDolError($result, $this->errors, $this->error);
			}
			// Add a protection to refuse deleting if shipment is not in draft status
			if (($this->statut == self::STATUS_DRAFT) && ($params->line_id)) {
				if (ExtDirect::checkDolVersion(0, '7.0', '')) {
					$line = new ExpeditionLigne($this->db);
				} else {
					$line = new ExtDirectExpeditionLine($this->db);
				}
				$line->id = $params->line_id;
				if (($result = $line->delete()) < 0) return ExtDirect::getDolError($result, $line->errors, $line->error);
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
     * public method to fetch batch results
     *
     * @param array &$results array to store batches
     * @param object $row object with line data to add to results
     * @param int $lineId expedition line id
     * @return int < 0 if error > 0 if OK
     */
    private function fetchBatches(&$results,$row,$lineId) {
        require_once DOL_DOCUMENT_ROOT.'/expedition/class/expeditionbatch.class.php';
        $batches = array();
        
        if (ExtDirect::checkDolVersion() >= 3.8) {
            if (($batches = ExpeditionLineBatch::FetchAll($this->db, $lineId)) < 0 ) return $batches;
        } else {
            if (($batches = ExpeditionLigneBatch::FetchAll($this->db, $lineId)) < 0 ) return $batches;
        }
        
        if (!empty($batches)) {
             foreach ($batches as $batch) {
                $row->id = $lineId.'_'.$batch->id;
                $row->line_id = $lineId;
                $row->batch_id = $batch->fk_origin_stock;
                $row->sellby = $batch->sellby;
                $row->eatby = $batch->eatby;
                $row->batch = $batch->batch;
                $row->qty_shipped = (float) $batch->dluo_qty;
                array_push($results, clone $row);
            }
        } else {
            // no batch
            array_push($results, clone $row);
        }
       
        return 1;
    }
}
/** ExtDirectExpeditionLine class
 * Class to access shipments lines with CRUD methods 
 */
class ExtDirectExpeditionLine extends ExpeditionLigne
{
	/**
	 * Id of warehouse
	 * @var int
	 */
	var $entrepot_id;
	
	/**
	 * 	Delete shipment line.
	 *  
	 *  @param      int		$lineid		Id of line to delete
	 * 	@return	int		>0 if OK, <0 if KO
	 */
	function delete()
	{
		global $conf;

		$this->db->begin();
				
		// delete batch expedition line
		if ($conf->productbatch->enabled)
		{
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."expeditiondet_batch";
			$sql.= " WHERE fk_expeditiondet = ".$this->id;

			if (!$this->db->query($sql))
			{
				$this->errors[]=$this->db->lasterror()." - sql=$sql";
				$this->db->rollback();
				return -2;
			}
		}
		
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."expeditiondet";
		$sql.= " WHERE rowid = ".$this->id;

		if ( $this->db->query($sql))
		{
			// Remove extrafields
			if (empty($conf->global->MAIN_EXTRAFIELDS_DISABLED)) // For avoid conflicts if trigger used
			{
				$result=$this->deleteExtraFields();
				if ($result < 0)
				{
					$this->errors[]=$this->error;
					$this->db->rollback();
					return -4;
				}
				else
				{
					$this->db->commit();
					return 1;
				}
			} 
			else 
			{
				$this->db->commit();
				return 1;
			}
		}
		else
		{
			$this->errors[]=$this->db->lasterror()." - sql=$sql";
			$this->db->rollback();
			return -3;
		}
	}
	
	/**
	 *  Update a line in database
	 *
	 *  @return		int					< 0 if KO, > 0 if OK
	 */
	function update()
	{
		global $conf;

		dol_syslog(get_class($this)."::update id=$this->id, entrepot_id=$this->entrepot_id, product_id=$this->fk_product, qty=$this->qty");


		$this->db->begin();

		// Clean parameters
		if (empty($this->qty)) $this->qty=0;
		$qty=price2num($this->qty);
		$remainingQty = 0;
		$batch = null;
		$batch_id = null;
		$expedition_batch_id = null;

		if (is_array($this->detail_batch)) 
		{
			if (count($this->detail_batch) > 1) 
			{
				dol_syslog(get_class($this).'::update only possible for one batch', LOG_ERR);
				$this->errors[]='ErrorBadParameters';
				return -7;
			}
			else
			{
				$batch = $this->detail_batch[0]->batch;
				$batch_id = $this->detail_batch[0]->fk_origin_stock;
				$expedition_batch_id = $this->detail_batch[0]->id;
				if ($this->entrepot_id != $this->detail_batch[0]->entrepot_id)
				{
					dol_syslog(get_class($this).'::update only possible for batch of same warehouse', LOG_ERR);
					$this->errors[]='ErrorBadParameters';
					$error++;
				}
				$qty = price2num($this->detail_batch[0]->dluo_qty);
			}
		}
		else
		{
			$batch = $this->detail_batch->batch;
			$batch_id = $this->detail_batch->fk_origin_stock;
			$expedition_batch_id = $this->detail_batch->id;
			if ($this->entrepot_id != $this->detail_batch->entrepot_id)
			{
				dol_syslog(get_class($this).'::update only possible for batch of same warehouse', LOG_ERR);
				$this->errors[]='ErrorBadParameters';
				$error++;
			}
			$qty = price2num($this->detail_batch->dluo_qty);
		}
		if (! isset($this->id) || ! isset($this->entrepot_id))
		{
			dol_syslog(get_class($this).'::update missing line id and/or warehouse id', LOG_ERR);
			$this->errors[]='ErrorBadParameters';
			return -1;
		}

		// update lot

		if (!empty($batch) && $conf->productbatch->enabled)
		{
			if (empty($batch_id) || empty($this->fk_product)) {
				dol_syslog(get_class($this).'::update missing fk_origin_stock (batch_id) and/or fk_product', LOG_ERR);
        		$this->errors[]='ErrorBadParameters';
				return -8;
			}
			
			// fetch remaining lot qty
			require_once DOL_DOCUMENT_ROOT.'/expedition/class/expeditionbatch.class.php';
			if (($lotArray = ExpeditionLineBatch::fetchAll($this->db, $this->id)) < 0)
			{
				$this->errors[]=$this->db->lasterror()." - ExpeditionLineBatch::fetchAll";
				$this->db->rollback();
				return -4;
			}	
			foreach ($lotArray as $lot) 
			{
				if ($batch != $lot->batch) 
				{
					$remainingQty += $lot->dluo_qty;
				}
			}
			$qty += $remainingQty;
			//fetch lot details
			
			if (ExtDirect::checkDolVersion() >= 4.0) 
			{
				// fetch from product_lot
				require_once DOL_DOCUMENT_ROOT.'/product/stock/class/productlot.class.php';
				$lot = new Productlot($this->db);
				if ($lot->fetch(0,$this->fk_product,$batch) < 0) 
				{
					$this->errors[] = $lot->errors;
					return -3;
				}
			} 
			else 
			{
				// fetch from product batch
				require_once DOL_DOCUMENT_ROOT.'/product/class/productbatch.class.php';
				$lot = new Productbatch($this->db);
				if ($lot->fetch($batch_id) < 0) 
				{
					$this->errors[] = $lot->error;
					return -3;
				}
			}
			if (! empty($expedition_batch_id))
			{
				// delete lot expedition line
				$sql = "DELETE FROM ".MAIN_DB_PREFIX."expeditiondet_batch";
				$sql.= " WHERE fk_expeditiondet = ".$this->id;
				$sql.= " AND rowid = ".$expedition_batch_id;
				if (!$this->db->query($sql))
				{
					$this->errors[]=$this->db->lasterror()." - sql=$sql";
					$this->db->rollback();
					return -2;
				}
			}
			if ($this->detail_batch->dluo_qty > 0) {
				if (isset($lot->id)) 
				{
					$shipmentLot = new ExpeditionLineBatch($this->db);
					$shipmentLot->batch = $lot->batch;
					$shipmentLot->eatby = $lot->eatby;
					$shipmentLot->sellby = $lot->sellby;
					$shipmentLot->entrepot_id = $this->entrepot_id;
					$shipmentLot->dluo_qty = $this->detail_batch->dluo_qty;
					$shipmentLot->fk_origin_stock = $batch_id;
					if ($shipmentLot->create($this->id) < 0) 
					{
						$this->errors[]=$shipmentLot->errors;
						$this->db->rollback();
						return -6;
					}
				}
			}
		}

		// update line
		$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET";
		$sql.= " fk_entrepot = ".$this->entrepot_id;
		$sql.= " , qty = ".$qty;
		$sql.= " WHERE rowid = ".$this->id;
		
		if (!$this->db->query($sql)) 
		{
			$this->errors[]=$this->db->lasterror()." - sql=$sql";
			$this->db->rollback();
			return -5;
		}
		
		if (empty($conf->global->MAIN_EXTRAFIELDS_DISABLED)) // For avoid conflicts if trigger used
		{
			$this->id=$this->rowid;
			$result=$this->insertExtraFields();
			if ($result < 0)
			{
				$this->errors[]=$this->error;
				$this->db->rollback();
				return -4;
			}
			else
			{
				$this->db->commit();
				return 1;
			}
		} 
		else 
		{
			$this->db->commit();
			return 1;
		}
	}
}