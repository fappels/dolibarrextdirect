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
 *  \file       htdocs/extdirect/class/ExtDirectCommande.class.php
 *  \brief      Sencha Ext.Direct orders remoting class
 */

require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';
dol_include_once('/extdirect/class/extdirect.class.php');

/**
 * ExtDirectCommande class
 *
 * Orders Class to with CRUD methods to connect to Extjs or sencha touch using Ext.direct connector
 */
class ExtDirectCommande extends Commande
{
	private $_user;
	private $_orderConstants = array('STOCK_MUST_BE_ENOUGH_FOR_ORDER',
		'STOCK_CALCULATE_ON_VALIDATE_ORDER',
		'STOCK_SHOW_VIRTUAL_STOCK_IN_PRODUCTS_COMBO');

		/**
	 * Fully shippable status of validated order
	 */
	const STATUS_VALIDATED_FULLY_SHIPPABLE = 20;

	/**
	 * partly shippable status of validated order
	 */
	const STATUS_VALIDATED_PARTLY_SHIPPABLE = 21;

	/**
	 * Fully shippable status of validated order
	 */
	const STATUS_ONPROCESS_FULLY_SHIPPABLE = 22;

	/**
	 * partly shippable status of validated order
	 */
	const STATUS_ONPROCESS_PARTLY_SHIPPABLE = 23;

	/**
	 * end status to allow status itteration
	 */
	const STATUS_END = 24;

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
				$this->_user = $user;  //commande.class uses global user
				if (isset($this->_user->conf->MAIN_LANG_DEFAULT) && ($this->_user->conf->MAIN_LANG_DEFAULT != 'auto')) {
					$langs->setDefaultLang($this->_user->conf->MAIN_LANG_DEFAULT);
				}
				// set global $mysoc required for price calculation
				$mysoc = new Societe($db);
				$mysoc->setMysoc($conf);
				$langs->load("orders");
				$langs->load("sendings"); // for shipment methods
				$langs->load("extdirect@extdirect"); // for custom order status
				parent::__construct($db);
			}
		}
	}

	/**
	 *	Load order related constants
	 *
	 *	@param			stdClass    $params		filter with elements
	 *                                          constant    name of specific constant
	 *
	 *	@return			stdClass result data with specific constant value
	 */
	public function readConstants(stdClass $params)
	{
		global $conf;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->commande->lire)) return PERMISSIONERROR;

		if (!empty($this->_user->rights->fournisseur->lire) && !empty($conf->margin->enabled) && $this->_user->rights->margins->liretous) {
			$this->_orderConstants[] = 'MARGIN_TYPE';
		}
		$results = ExtDirect::readConstants($this->db, $params, $this->_user, $this->_orderConstants);

		return $results;
	}

	/**
	 *    Load order from database into memory
	 *
	 *    @param    stdClass    $params     filter with elements:
	 *                                      id  Id of order to load
	 *                                      ref ref, ref_int
	 *
	 *    @return     stdClass result data or error number
	 */
	public function readOrder(stdClass $params)
	{
		global $mysoc;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->commande->lire)) return PERMISSIONERROR;
		$myUser = new User($this->db);
		$thirdparty = new Societe($this->db);

		$results = array();
		$row = new stdClass;
		$id = 0;
		$ref = '';
		$ref_ext = '';
		$ref_int = '';
		$orderstatus_ids = array();
		$customStatus = false;

		if (isset($params->filter)) {
			foreach ($params->filter as $key => $filter) {
				if ($filter->property == 'id') $id=$filter->value;
				elseif ($filter->property == 'ref') $ref=$filter->value;
				elseif ($filter->property == 'ref_int') $ref_int=$filter->value;
				elseif ($filter->property == 'ref_ext') $ref_ext=$filter->value;
				elseif ($filter->property == 'orderstatus_id') array_push($orderstatus_ids, $filter->value);
			}
		}

		if (in_array(self::STATUS_VALIDATED_FULLY_SHIPPABLE, $orderstatus_ids)
			|| in_array(self::STATUS_VALIDATED_PARTLY_SHIPPABLE, $orderstatus_ids)
			|| in_array(self::STATUS_ONPROCESS_FULLY_SHIPPABLE, $orderstatus_ids)
			|| in_array(self::STATUS_ONPROCESS_PARTLY_SHIPPABLE, $orderstatus_ids)
		) {
			$customStatus = true;
		}

		if (($id > 0) || ($ref != '') || ($ref_int != '') || ($ref_ext != '')) {
			if (($result = $this->fetch($id, $ref, $ref_ext, $ref_int)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
			if (!$this->error) {
				$row->id = $this->id ;
				//! Ref
				$row->ref= $this->ref;
				$row->ref_customer= $this->ref_client;
				$row->ref_ext= $this->ref_ext;
				$row->customer_id = $this->socid;
				if ($thirdparty->fetch($this->socid)>0) {
					$row->customer_name = $thirdparty->name;
				}
				//! -1 for cancelled, 0 for draft, 1 for validated, 2 for send, 3 for closed
				$row->orderstatus_id = $this->statut;
				if ($customStatus) {
					$this->getShippable($row, 'longlabel');
					$row->orderstatus = html_entity_decode($this->LibStatut($row->orderstatus_id, false, 1));
				} else {
					$row->orderstatus = $this->getLibStatut(1);
				}
				$row->note_private = $this->note_private;
				$row->note_public = $this->note_public;
				$row->user_id = $this->user_author_id;
				if ($this->user_author_id > 0 && $myUser->fetch($this->user_author_id) > 0) {
					$row->user_name = $myUser->firstname . ' ' . $myUser->lastname;
				}
				$row->order_date = $this->date;
				$row->deliver_date= $this->date_livraison;
				$row->deliver_date= $this->delivery_date;
				$row->availability_id = $this->availability_id;
				$row->availability_code = $this->availability_code;
				$row->reduction_percent = $this->remise_percent;
				$row->payment_condition_id = $this->cond_reglement_id;
				$row->payment_type_id = $this->mode_reglement_id;
				$row->total_net = $this->total_ht;
				$row->total_tax = $this->total_tva;
				$row->total_inc = $this->total_ttc;
				$row->shipping_method_id = $this->shipping_method_id;
				$row->incoterms_id = $this->fk_incoterms;
				$row->location_incoterms = $this->location_incoterms;
				$row->customer_type = $thirdparty->typent_code;
				//$row->has_signature = 0; not yet implemented
				if ($this->remise == 0) {
					$row->reduction = 0;
					foreach ($this->lines as $line) {
						if ($line->remise_percent > 0) {
							$localtaxes_array = getLocalTaxesFromRate($line->tva_tx, 0, $thirdparty, $mysoc);
							$tabprice = calcul_price_total($line->qty, $line->subprice, 0, $line->tva_tx, $line->total_localtax1, $line->total_localtax2, 0, 'HT', $line->info_bits, $line->product_type, $mysoc, $localtaxes_array);
							$noDiscountHT = $tabprice[0];
							$noDiscountTTC = $tabprice[2];
							if ($row->customer_type == 'TE_PRIVATE') {
								$row->reduction += round($noDiscountTTC - $line->total_ttc, 2);
							} else {
								$row->reduction += round($noDiscountHT - $line->total_ht, 2);
							}
						}
					}
				} else {
					$row->reduction = $this->remise;
				}

				if (empty($orderstatus_ids)) {
					array_push($results, $row);
				} else {
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
	* public method to read available optionals (extra fields)
	*
	* @return stdClass result data or ERROR
	*/
	public function readOptionalModel()
	{
		if (!isset($this->db)) return CONNECTERROR;

		return ExtDirect::readOptionalModel($this);
	}

	/**
	 * public method to read order optionals (extra fields) from database
	 *
	 *    @param    stdClass    $param  filter with elements:
	 *                                  id  Id of order to load
	 *
	 *    @return     stdClass result data or -1
	 */
	public function readOptionals(stdClass $param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->commande->lire)) return PERMISSIONERROR;
		$results = array();
		$id = 0;

		if (isset($param->filter)) {
			foreach ($param->filter as $key => $filter) {
				if ($filter->property == 'id') $id=$filter->value;
			}
		}

		if ($id > 0) {
			$extraFields = new ExtraFields($this->db);
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
						$row->value = $extraFields->showOutputField($name, $value, '', $this->table_element);
						$row->object_id = $this->id;
						$row->object_element = $this->element;
						$row->raw_value = $value;
						$results[] = $row;
					}
				}
			}
		}
		return $results;
	}

	/**
	 * public method to update optionals (extra fields) into database
	 *
	 *    @param    unknown_type    $params  optionals
	 *
	 *    @return     Ambigous <multitype:, unknown_type>|unknown
	 */
	public function updateOptionals($params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->commande->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);

		foreach ($paramArray as &$param) {
			if ($this->id != $param->object_id && ($result = $this->fetch($param->object_id)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
			$this->array_options['options_'.$param->name] = $param->raw_value;
		}
		if (($result = $this->insertExtraFields()) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
		if (is_array($params)) {
			return $paramArray;
		} else {
			return $param;
		}
	}

	/**
	 * public method to add optionals (extra fields) into database
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
	 * public method to delete optionals (extra fields) into database
	 *
	 *    @param    unknown_type    $params  optionals
	 *
	 *    @return    Ambigous <multitype:, unknown_type>|unknown
	 */
	public function destroyOptionals($params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->commande->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);

		foreach ($paramArray as &$param) {
			if ($this->id != $param->object_id && ($result = $this->fetch($param->object_id)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
		}
		if (($result = $this->deleteExtraFields()) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
		if (is_array($params)) {
			return $paramArray;
		} else {
			return $param;
		}
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
		if (!isset($this->_user->rights->commande->creer)) return PERMISSIONERROR;
		$notrigger=0;
		$paramArray = ExtDirect::toArray($param);

		foreach ($paramArray as &$params) {
			// prepare fields
			$this->prepareOrderFields($params);
			if (($result = $this->create($this->_user, $notrigger)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);

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
		global $conf, $langs;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->commande->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($param);

		foreach ($paramArray as &$params) {
			// prepare fields
			if ($params->id) {
				if (($result = $this->fetch($params->id)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				$this->prepareOrderFields($params);
				// update
				switch ($params->orderstatus_id) {
					case -1:
						$result = $this->cancel();
						break;
					case 0:
						if (ExtDirect::checkDolVersion(0, '10.0', '')) {
							$result = $this->setDraft($this->_user);
						} else {
							$result = $this->set_draft($this->_user);
						}
						break;
					case 1:
						if ($params->warehouse_id > 0) {
							$warehouseId = $params->warehouse_id;
						} else {
							$warehouseId = 0;
						}
						$result = $this->valid($this->_user, $warehouseId);
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
					case 3:
						$result = $this->cloture($this->_user);
						break;
					default:
						break;
				}

				if ($result < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				if (($result = $this->set_date($this->_user, $this->date)) < 0) return $result;
				if (function_exists('setDeliveryDate')) {
					if (($result = $this->setDeliveryDate($this->_user, $this->delivery_date)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				} else {
					if (($result = $this->set_date_livraison($this->_user, $this->date_livraison)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				}
				if (ExtDirect::checkDolVersion(0, '', '4.0') && ($this->availability_id > 0) &&
					($result = $this->set_availability($this->_user, $this->availability_id)) < 0)  return ExtDirect::getDolError($result, $this->errors, $this->error);
				if (ExtDirect::checkDolVersion(0, '5.0', '') && ($this->availability_id > 0) &&
					($result = $this->availability($this->availability_id)) < 0)  return ExtDirect::getDolError($result, $this->errors, $this->error);
				if (isset($this->remise_percent) &&
					($result = $this->set_remise($this->_user, $this->remise_percent)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				if (isset($this->cond_reglement_id) &&
					($result = $this->setPaymentTerms($this->cond_reglement_id)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				if (isset($this->mode_reglement_id) &&
					($result = $this->setPaymentMethods($this->mode_reglement_id)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				if (isset($this->shipping_method_id) &&
					($result = $this->setShippingMethod($this->shipping_method_id)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				if (isset($this->fk_incoterms) &&
					($result = $this->setIncoterms($this->fk_incoterms, $this->location_incoterms)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				if (isset($this->ref_client) &&
					($result = $this->set_ref_client($this->_user, $this->ref_client)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
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
		if (!isset($this->_user->rights->commande->supprimer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($param);

		foreach ($paramArray as &$params) {
			// prepare fields
			if ($params->id) {
				$this->id = $params->id;
				// delete
				if (($result = $this->delete($this->_user)) < 0)    return ExtDirect::getDolError($result, $this->errors, $this->error);
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
		isset($params->ref) ? ( $this->ref = $params->ref ) : ( isset($this->ref) ? null : ( $this->ref = null));
		isset($params->ref_int) ? ( $this->ref_int = $params->ref_int ) : ( isset($this->ref_int) ? null : ( $this->ref_int = null));
		isset($params->ref_ext) ? ( $this->ref_ext = $params->ref_ext ) : ( isset($this->ref_ext) ? null : ( $this->ref_ext = null));
		isset($params->ref_customer) ? ( $this->ref_client = $params->ref_customer) : ( isset($this->ref_client) ? null : ( $this->ref_client = null));
		isset($params->customer_id) ? ( $this->socid = $params->customer_id) : ( isset($this->socid) ? null : ( $this->socid = null));
		//isset($params->orderstatus_id) ? ( $this->statut = $params->orderstatus_id) : ( isset($this->statut) ? null : ($this->statut  = 0));
		isset($params->note_private) ? ( $this->note_private =$params->note_private) : ( isset($this->note_private) ? null : ( $this->note_private = null));
		isset($params->note_public) ? ( $this->note_public = $params->note_public ) : ( isset($this->note_public) ? null : ($this->note_public = null));
		isset($params->user_id) ? ( $this->user_author_id = $params->user_id) : ( isset($this->user_author_id) ? null : ($this->user_author_id = null));
		isset($params->order_date) ? ( $this->date =$params->order_date) : ( isset($this->date) ? null : ($this->date = null));
		isset($params->deliver_date) ? ( $this->date_livraison = $params->deliver_date) : ( isset($this->date_livraison) ? null : ($this->date_livraison = null));
		isset($params->deliver_date) ? ( $this->delivery_date = $params->deliver_date) : ( isset($this->delivery_date) ? null : ($this->delivery_date = null));
		isset($params->availability_id) ? ( $this->availability_id =$params->availability_id) : ( isset($this->availability_id) ? null : ($this->availability_id = null));
		isset($params->availability_code) ? ( $this->availability_code =$params->availability_code) : ( isset($this->availability_code) ? null : ($this->availability_code = null));
		isset($params->reduction_percent) ? ($this->remise_percent = $params->reduction_percent) : null;
		isset($params->payment_condition_id) ? ($this->cond_reglement_id = $params->payment_condition_id) : null;
		isset($params->payment_type_id) ? ($this->mode_reglement_id = $params->payment_type_id) : null;
		isset($params->shipping_method_id) ? ($this->shipping_method_id = $params->shipping_method_id) : null;
		isset($params->incoterms_id) ? ($this->fk_incoterms = $params->incoterms_id) : null;
		isset($params->location_incoterms) ? ($this->location_incoterms = $params->location_incoterms) : null;
	}

	/**
	 * public method to read a list of orders
	 *
	 * @param stdClass $params to filter on order status and ref
	 * @return     stdClass result data or error number
	 */
	public function readOrderList(stdClass $params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->commande->lire)) return PERMISSIONERROR;
		$result = new stdClass;
		$data = array();

		$statusFilterCount = 0;
		$ref = null;
		$contactTypeId = 0;
		$barcode = null;
		$contentFilter = null;

		$includeTotal = true;

		$orderstatus_id = array();
		$sorterSize = 0;
		$customStatus = false;

		if (isset($params->limit)) {
			$limit = $params->limit;
			$start = $params->start;
		}
		if (isset($params->allow_paging)) { // for backwards compatibility (actual app already sends include_total but is not ready for order paging)
			if (isset($params->include_total)) {
				$includeTotal = $params->include_total;
			}
		} else {
			$limit = 0;
		}

		if (isset($params->filter)) {
			foreach ($params->filter as $key => $filter) {
				if ($filter->property == 'orderstatus_id') $orderstatus_id[$statusFilterCount++]=$filter->value; // add id config in client filter for ExtJs
				elseif ($filter->property == 'ref') $ref=$filter->value;
				elseif ($filter->property == 'contacttype_id') $contactTypeId = $filter->value;
				elseif ($filter->property == 'contact_id') $contactId = $filter->value;
				elseif ($filter->property == 'barcode') $barcode = $filter->value;
				elseif ($filter->property == 'content') $contentFilter = $filter->value;
			}
		}

		if (in_array(self::STATUS_VALIDATED_FULLY_SHIPPABLE, $orderstatus_id)
			|| in_array(self::STATUS_VALIDATED_PARTLY_SHIPPABLE, $orderstatus_id)
			|| in_array(self::STATUS_ONPROCESS_FULLY_SHIPPABLE, $orderstatus_id)
			|| in_array(self::STATUS_ONPROCESS_PARTLY_SHIPPABLE, $orderstatus_id)
		) {
			$customStatus = true;
			// always load page from start to be able to sort on complete result
			$limit += $start;
			$start = 0;
		}

		$sqlFields = "SELECT s.nom, s.rowid AS socid, c.rowid, c.ref, c.fk_statut, c.ref_ext, c.fk_availability, ea.status, s.price_level, c.ref_client, c.fk_user_author, c.total_ttc, c.date_livraison, c.date_commande, u.firstname, u.lastname";
		$sqlFrom = " FROM ".MAIN_DB_PREFIX."commande as c";
		$sqlFrom .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON c.fk_soc = s.rowid";
		$sqlFrom .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON c.fk_user_author = u.rowid";
		if ($barcode) {
			$sqlFrom .= " LEFT JOIN ".MAIN_DB_PREFIX."commandedet as cd ON c.rowid = cd.fk_commande";
			$sqlFrom .= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = cd.fk_product";
			$sqlFrom .= " LEFT JOIN ".MAIN_DB_PREFIX."product_lot as pl ON pl.fk_product = cd.fk_product AND pl.batch = '".$this->db->escape($barcode)."'";
		}
		if ($contactTypeId > 0) $sqlFrom .= " LEFT JOIN ".MAIN_DB_PREFIX."element_contact as ec ON c.rowid = ec.element_id";
		$sqlFrom .= " LEFT JOIN ("; // get latest extdirect activity status for commande to check if locked
		$sqlFrom.= "   SELECT ma.activity_id, ma.maxrow AS rowid, ea.status";
		$sqlFrom.= "   FROM (";
		$sqlFrom.= "    SELECT MAX( rowid ) AS maxrow, activity_id";
		$sqlFrom.= "    FROM ".MAIN_DB_PREFIX."extdirect_activity";
		$sqlFrom.= "    GROUP BY activity_id";
		$sqlFrom.= "   ) AS ma, ".MAIN_DB_PREFIX."extdirect_activity AS ea";
		$sqlFrom.= "   WHERE ma.maxrow = ea.rowid";
		$sqlFrom.= " ) AS ea ON c.rowid = ea.activity_id";
		$sqlWhere = " WHERE c.entity IN (".getEntity('commande', 1).')';
		$sqlWhere .= " AND c.fk_soc = s.rowid";

		if ($statusFilterCount>0) {
			$sqlWhere .= " AND ( ";
			foreach ($orderstatus_id as $key => $fk_statut) {
				if ($fk_statut === self::STATUS_VALIDATED_FULLY_SHIPPABLE || $fk_statut === self::STATUS_VALIDATED_PARTLY_SHIPPABLE) $fk_statut = self::STATUS_VALIDATED;
				if ($fk_statut === self::STATUS_ONPROCESS_FULLY_SHIPPABLE || $fk_statut === self::STATUS_ONPROCESS_PARTLY_SHIPPABLE) $fk_statut = self::STATUS_SHIPMENTONPROCESS;
				$sqlWhere .= "c.fk_statut = ".$fk_statut;
				if ($key < ($statusFilterCount-1)) $sqlWhere .= " OR ";
			}
			$sqlWhere.= ")";
		}
		if ($ref) {
			$sqlWhere .= " AND c.ref = '".$ref."'";
		}
		if ($contactTypeId > 0) {
			$sqlWhere .= " AND ec.fk_c_type_contact = ".$contactTypeId;
			$sqlWhere .= " AND ec.fk_socpeople = ".$contactId;
		}
		if ($barcode) {
			$sqlWhere .= " AND (p.barcode LIKE '%".$this->db->escape($barcode)."%' OR c.ref = '".$this->db->escape($barcode)."' OR c.ref_client = '".$this->db->escape($barcode)."'";
			$sqlWhere .= " OR pl.batch = '".$this->db->escape($barcode)."'";
			$sqlWhere .= ")";
		}

		if ($contentFilter) {
			$fields = array('c.ref', 'c.ref_client', 's.nom', 'u.firstname', 'u.lastname');
			$sqlWhere .= " AND ".natural_search($fields, $contentFilter, 0, 1);
		}

		$sqlOrder = " ORDER BY ";
		if (isset($params->sort)) {
			$sorterSize = count($params->sort);
			foreach ($params->sort as $key => $sort) {
				if (!empty($sort->property)) {
					if ($sort->property == 'orderstatus_id') {
						$sortfield = 'c.fk_statut';
					} elseif ($sort->property == 'order_date') {
						$sortfield = 'c.date_commande';
					} elseif ($sort->property == 'ref') {
						$sortfield = 'c.ref';
					} elseif ($sort->property == 'deliver_date') {
						$sortfield = 'c.date_livraison';
					} elseif ($sort->property == 'ref_customer') {
						$sortfield = 'c.ref_client';
					} elseif ($sort->property == 'customer') {
						$sortfield = 's.nom';
					} elseif ($sort->property == 'user_name') {
						$sortfield = 'u.lastname, u.firstname';
					} else {
						$sortfield = $sort->property;
					}
					$sqlOrder .= $sortfield. ' '.$sort->direction;
					if ($key < ($sorterSize-1)) {
						$sqlOrder .= ",";
					}
				}
			}
		} else {
			$sqlOrder .= "c.date_commande DESC";
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
				dol_syslog(get_class($this)."::readOrderList ".$error, LOG_ERR);
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
				$row->id            = (int) $obj->rowid;
				$row->customer      = $obj->nom;
				$row->customer_id   = (int) $obj->socid;
				$row->ref           = $obj->ref;
				$row->ref_ext       = $obj->ref_ext;
				$row->orderstatus_id= (int) $obj->fk_statut;
				if ($customStatus && $obj->fk_statut > self::STATUS_DRAFT && $obj->fk_statut < self::STATUS_CLOSED) {
					$this->getShippable($row);
				}
				$row->orderstatus   = html_entity_decode($this->LibStatut($row->orderstatus_id, false, 1));
				$row->availability_id = $obj->fk_availability;
				$row->status        = $obj->status;
				$row->customer_price_level = ($obj->price_level) ? (int) $obj->price_level : 1;
				$row->ref_customer  = $obj->ref_client;
				$row->user_id       = $obj->fk_user_author;
				$row->user_name     = $obj->firstname . ' ' . $obj->lastname;
				$row->total_inc     = $obj->total_ttc;
				$row->deliver_date  = $this->db->jdate($obj->date_livraison);
				$row->order_date    = $this->db->jdate($obj->date_commande);
				if ($customStatus) {
					if (in_array($row->orderstatus_id, $orderstatus_id)) {
						array_push($data, $row);
					}
				} else {
					array_push($data, $row);
				}
			}
			$this->db->free($resql);
			if ($customStatus && $sorterSize > 0) $data = ExtDirect::resultSort($data, $params->sort);
			if ($includeTotal) {
				$result->total = $total;
				$result->data = $data;
				return $result;
			} else {
				return $data;
			}
		} else {
			$error="Error ".$this->db->lasterror();
			dol_syslog(get_class($this)."::readOrderList ".$error, LOG_ERR);
			return SQLERROR;
		}
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *	Return label of status
	 *
	 *	@param		int		$status      	  Id status
	 *  @param      int		$billed    		  If invoiced
	 *	@param      int		$mode        	  1=Short label
	 *  @param      int     $donotshowbilled  Do not show billed status after order status
	 *  @return     string					  Label of status
	 */
	public function LibStatut($status, $billed, $mode, $donotshowbilled = 0)
	{
		// phpcs:enable
		global $langs, $conf;

		$billedtext = '';
		if (empty($donotshowbilled)) $billedtext .= ($billed ? ' - '.$langs->transnoentitiesnoconv("Billed") : '');

		if ($status == self::STATUS_CANCELED) {
			$labelStatusShort = $langs->transnoentitiesnoconv('StatusOrderCanceledShort');
		} elseif ($status == self::STATUS_DRAFT) {
			$labelStatusShort = $langs->transnoentitiesnoconv('StatusOrderDraftShort');
		} elseif ($status == self::STATUS_VALIDATED) {
			$labelStatusShort = $langs->transnoentitiesnoconv('StatusOrderValidatedShort').$billedtext;
		} elseif ($status == self::STATUS_VALIDATED_FULLY_SHIPPABLE) {
			$labelStatusShort = $langs->transnoentitiesnoconv('StatusOrderValidatedFullyShippableShort').$billedtext;
		} elseif ($status == self::STATUS_VALIDATED_PARTLY_SHIPPABLE) {
			$labelStatusShort = $langs->transnoentitiesnoconv('StatusOrderValidatedPartlyShippableShort').$billedtext;
		} elseif ($status == self::STATUS_ONPROCESS_FULLY_SHIPPABLE) {
			$labelStatusShort = $langs->transnoentitiesnoconv('StatusOrderOnprocessFullyShippableShort').$billedtext;
		} elseif ($status == self::STATUS_ONPROCESS_PARTLY_SHIPPABLE) {
			$labelStatusShort = $langs->transnoentitiesnoconv('StatusOrderOnprocessPartlyShippableShort').$billedtext;
		} elseif ($status == self::STATUS_SHIPMENTONPROCESS) {
			$labelStatusShort = $langs->transnoentitiesnoconv('StatusOrderSentShort').$billedtext;
		} elseif ($status == self::STATUS_CLOSED && (!$billed && empty($conf->global->WORKFLOW_BILL_ON_SHIPMENT))) {
			$labelStatusShort = $langs->transnoentitiesnoconv('StatusOrderToBillShort');
		} elseif ($status == self::STATUS_CLOSED && ($billed && empty($conf->global->WORKFLOW_BILL_ON_SHIPMENT))) {
			$labelStatusShort = $langs->transnoentitiesnoconv('StatusOrderProcessedShort').$billedtext;
		} elseif ($status == self::STATUS_CLOSED && (!empty($conf->global->WORKFLOW_BILL_ON_SHIPMENT))) {
			$labelStatusShort = $langs->transnoentitiesnoconv('StatusOrderDeliveredShort');
		} else {
			$labelStatusShort = '';
		}

		return $labelStatusShort;
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
		$statut = -1;
		$row = new stdClass;
		while ($statut < self::STATUS_END) {
			$result = $this->LibStatut($statut, false, 1);
			if (!empty($result)) {
				$row = new stdClass;
				$row->id = $statut;
				$row->status = html_entity_decode($result);
				array_push($results, $row);
			}
			$statut++;
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
		if (! is_array($result = $this->liste_type_contact())) return ExtDirect::getDolError($result, $this->errors, $this->error);
		// add empty type
		$row = new stdClass;
		$row->id = 0;
		$row->label = '';
		array_push($results, $row);
		foreach ($result as $id => $label) {
			$row = new stdClass;
			$row->id = $id;
			$row->label = html_entity_decode($label);
			array_push($results, $row);
		}
		return $results;
	}

	/**
	 * public method to read a list of availability codes
	 *
	 * @return     stdClass result data or error number
	 */
	public function readAvailabilityCodes()
	{
		global $langs;

		if (!isset($this->db)) return CONNECTERROR;
		$results = array();

		$sql = 'SELECT ca.rowid, ca.code , ca.label';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'c_availability as ca';
		$sql .= ' WHERE ca.active = 1';
		$sql .= ' ORDER BY ca.rowid';

		$resql=$this->db->query($sql);

		if ($resql) {
			$num=$this->db->num_rows($resql);

			for ($i = 0;$i < $num; $i++) {
				$obj = $this->db->fetch_object($resql);
				$row = new stdClass;

				$row->id = $obj->rowid;
				$transcode=$langs->transnoentities($obj->code);
				$label=($transcode!=null?$transcode:$obj->label);
				$row->code = $obj->code;
				$row->label = $label;
				array_push($results, $row);
			}

			$this->db->free($resql);
			return $results;
		} else {
			$error="Error ".$this->db->lasterror();
			dol_syslog(get_class($this)."::readAvailabilityCodes ".$error, LOG_ERR);
			return SQLERROR;
		}
	}

	/**
	 * public method to read a list of shipping modes
	 *
	 * @return     stdClass result data or error number
	 */
	public function readShipmentModes()
	{
		global $langs;

		if (!isset($this->db)) return CONNECTERROR;
		$results = array();

		$sql = 'SELECT csm.rowid, csm.code , csm.libelle as label';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'c_shipment_mode as csm';
		$sql .= ' WHERE csm.active > 0';
		$sql .= ' ORDER BY csm.rowid';

		$resql=$this->db->query($sql);

		if ($resql) {
			$num=$this->db->num_rows($resql);

			for ($i = 0;$i < $num; $i++) {
				$obj = $this->db->fetch_object($resql);
				$row = new stdClass;

				$row->id = $obj->rowid;
				$transcode=$langs->transnoentities("SendingMethod".strtoupper($obj->code));
				$label=($transcode!=null?$transcode:$obj->label);
				$row->code = $obj->code;
				$row->label = $label;
				array_push($results, $row);
			}

			$this->db->free($resql);
			return $results;
		} else {
			$error="Error ".$this->db->lasterror();
			dol_syslog(get_class($this)."::readShipmentModes ".$error, LOG_ERR);
			return SQLERROR;
		}
	}

	/**
	 * public method to read a list of incoterm codes
	 *
	 * @return     stdClass result data or error number
	 */
	public function readIncotermCodes()
	{
		if (!isset($this->db)) return CONNECTERROR;
		$results = array();

		$sql = 'SELECT ci.rowid, ci.code';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'c_incoterms as ci';
		$sql .= ' WHERE ci.active > 0';
		$sql .= ' ORDER BY ci.rowid';

		$resql=$this->db->query($sql);

		if ($resql) {
			$num=$this->db->num_rows($resql);

			for ($i = 0;$i < $num; $i++) {
				$obj = $this->db->fetch_object($resql);
				$row = new stdClass;

				$row->id = $obj->rowid;
				$row->code = $obj->code;
				array_push($results, $row);
			}

			$this->db->free($resql);
			return $results;
		} else {
			$error="Error ".$this->db->lasterror();
			dol_syslog(get_class($this)."::readIncotermCodes ".$error, LOG_ERR);
			return SQLERROR;
		}
	}

	/**
	 * Ext.direct method to upload file for order object
	 *
	 * @param unknown_type $params object or object array with uploaded file(s)
	 * @return Array    ExtDirect response message
	 */
	public function fileUpload($params)
	{
		global $conf;
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->commande->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);
		$dir = null;

		foreach ($paramArray as &$param) {
			if (isset($param['extTID'])) {
				$id = $param['extTID'];
				if ($this->fetch($id)) {
					$this->fetch_thirdparty();
					$dir = $conf->commande->dir_output . "/" . dol_sanitizeFileName($this->ref);
				} else {
					$response = PARAMETERERROR;
					break;
				}
			} elseif (isset($param['file']) && isset($dir)) {
				$response = ExtDirect::fileUpload($param, $dir);
			} else {
				$response = PARAMETERERROR;
				break;
			}
		}
		return $response;
	}

	/**
	 * Get shippable status
	 *
	 * @param object $row row of resultset
	 * @param string $mode 'shortlabel' (default) for list else long translated label.
	 * @return void
	 */
	private function getShippable(&$row, $mode = 'shortlabel')
	{
		global $langs;

		dol_include_once('/extdirect/class/ExtDirectProduct.class.php');
		$generic_product = new ExtDirectProduct($this->db);
		$productstat_cache = array();
		$notshippable = 0;
		$notshippableQty = 0;
		$shippableQty = 0;
		$this->id = $row->id;
		$this->getLinesArray(); // This set ->lines
		if ($row->orderstatus_id == self::STATUS_SHIPMENTONPROCESS) $this->loadExpeditions(); // get already shipped
		$nbprod = 0;
		$numlines = count($this->lines); // Loop on each line of order
		for ($lig = 0; $lig < $numlines; $lig++) {
			if ($this->lines[$lig]->product_type == 0 && $this->lines[$lig]->fk_product > 0) {
				// If line is a product and not a service
				$nbprod++; // order contains real products
				$generic_product->id = $this->lines[$lig]->fk_product;
				$qtyToShip = $this->lines[$lig]->qty - $this->expeditions[$this->lines[$lig]->id];

				// Get local and virtual stock and store it into cache
				if (empty($productstat_cache[$this->lines[$lig]->fk_product])) {
					$generic_product->load_stock('nobatch, novirtual');
					$productstat_cache[$this->lines[$lig]->fk_product]['stock_reel'] = $generic_product->stock_reel;
				} else {
					$generic_product->stock_reel = $productstat_cache[$this->lines[$lig]->fk_product]['stock_reel'];
				}

				if ($this->lines[$lig]->qty > $generic_product->stock_reel) {
					$notshippable++;
					$notshippableQty += $qtyToShip - $generic_product->stock_reel;
				} else {
					$shippableQty += $qtyToShip;
				}
			}
		}
		if ($notshippable == 0) {
			if ($row->orderstatus_id == self::STATUS_VALIDATED) $row->orderstatus_id = self::STATUS_VALIDATED_FULLY_SHIPPABLE;
			elseif ($row->orderstatus_id == self::STATUS_SHIPMENTONPROCESS) $row->orderstatus_id = self::STATUS_ONPROCESS_FULLY_SHIPPABLE;
		} else {
			if ($row->orderstatus_id == self::STATUS_VALIDATED) $row->orderstatus_id = self::STATUS_VALIDATED_PARTLY_SHIPPABLE;
			elseif ($row->orderstatus_id == self::STATUS_SHIPMENTONPROCESS) $row->orderstatus_id = self::STATUS_ONPROCESS_PARTLY_SHIPPABLE;
		}
		if ($mode != 'shortlabel') {
			$row->shippable_qty = $langs->trans('ShippableQtyInfo', $shippableQty, $notshippableQty);
		} else {
			$row->shippable_qty = $shippableQty . ' - ' . $notshippableQty;
		}
	}

	/**
	 *    Load orderlines from database into memory
	 *
	 *    @param    stdClass    $params     filter with elements:
	 *                                      order_id Id of order to load lines from
	 *                                      warehouse_id
	 *                                      warehouse_id x to get stock of
	 *                                      warehouse_id -1 will get total stock
	 *                                      no warehouse_id will split lines in stock by warehouse
	 *                                      photo_size string with foto size 'mini' or 'small'
	 *                                      multiprices_index index for multiprice
	 *                                      only_product only lines with physical product
	 *                                      is_sub_product include sub products
	 *
	 *    @return     stdClass result data or error number
	 */
	public function readOrderLine(stdClass $params)
	{
		global $conf;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->commande->lire)) return PERMISSIONERROR;
		dol_include_once('/extdirect/class/ExtDirectProduct.class.php');

		$results = array();
		$order_id = 0;
		$photoSize = '';
		$onlyProduct = 1;
		$hasSubProductFilter = false;

		if (isset($params->filter)) {
			foreach ($params->filter as $key => $filter) {
				if ($filter->property == 'id') $order_id=$filter->value; // deprecated
				if ($filter->property == 'order_id') $order_id=$filter->value;
				if ($filter->property == 'warehouse_id') $warehouse_id=$filter->value;
				if ($filter->property == 'photo_size' && !empty($filter->value)) $photoSize = $filter->value;
				if ($filter->property == 'multiprices_index'  && !empty($filter->value)) $multiprices_index = $filter->value;
				if ($filter->property == 'only_product') $onlyProduct=$filter->value;
				if ($filter->property == 'is_sub_product') {
					$hasSubProductFilter = true;
					if ($filter->value == false) {
						// do not show sub products
						$conf->global->PRODUIT_SOUSPRODUITS = false;
					}
				}
			}
		}

		if (! $hasSubProductFilter) {
			// do not show sub products
			$conf->global->PRODUIT_SOUSPRODUITS = false;
		}

		if ($order_id > 0) {
			$this->id=$order_id;
			$this->loadExpeditions();
			if (!empty($conf->global->WAREHOUSE_ASK_WAREHOUSE_DURING_ORDER)) {
				if (($result = $this->fetch($order_id)) < 0)  return ExtDirect::getDolError($result, $this->errors, $this->error);
			}
			if (($result = $this->fetch_lines($onlyProduct)) < 0)  return ExtDirect::getDolError($result, $this->errors, $this->error);
			if (!$this->error) {
				foreach ($this->lines as $line) {
					$isService = false;
					(!$line->fk_product) ? $isFreeLine = true : $isFreeLine = false;
					$myprod = new ExtDirectProduct($this->_user->login);
					if (!$isFreeLine) {
						$result = $myprod->fetch($line->fk_product);
						if ($result < 0) return ExtDirect::getDolError($result, $myprod->errors, $myprod->error);
						if (!empty($conf->global->STOCK_SHOW_VIRTUAL_STOCK_IN_PRODUCTS_COMBO)) {
							$result = $myprod->load_stock('warehouseopen');
							if ($result < 0) return ExtDirect::getDolError($result, $myprod->errors, $myprod->error);
						} else {
							$result = $myprod->load_stock('novirtual, warehouseopen');
							if ($result < 0) return ExtDirect::getDolError($result, $myprod->errors, $myprod->error);
						}
						if (!empty($conf->global->PRODUIT_SOUSPRODUITS)) {
							$myprod->get_sousproduits_arbo();
						}
						if (empty($myprod->barcode_type) && !empty($conf->global->PRODUIT_DEFAULT_BARCODE_TYPE)) {
							$myprod->barcode_type = (int) $conf->global->PRODUIT_DEFAULT_BARCODE_TYPE;
						}
					}
					if ($line->product_type == 1) {
						$isService = true;
					}
					if ($isService || $isFreeLine || isset($warehouse_id) || $myprod->stock_reel == 0) {
						if ($isService || $isFreeLine || $warehouse_id == -1) {
							// get orderline with complete stock
							$row = new stdClass;
							$row->id = $line->id;
							$row->is_virtual_stock = false;
							$row->origin_id = $line->fk_commande;
							$row->origin_line_id = $line->id;
							if (empty($line->label)) {
								if ($isFreeLine) {
									$row->label = $line->desc;
								} else {
									$row->label = $line->product_label;
								}
							} else {
								$row->label = $line->label;
							}
							$row->description = $line->desc;
							$row->product_id = $line->fk_product;
							$row->ref = $line->product_ref;
							$row->product_label = $line->product_label;
							$row->product_desc = $line->product_desc;
							$row->product_type = $line->product_type;
							$row->barcode= $myprod->barcode?$myprod->barcode:'';
							$row->barcode_type = $myprod->barcode_type?$myprod->barcode_type:0;
							$row->barcode_with_checksum = $myprod->barcode?$myprod->fetchBarcodeWithChecksum($myprod):'';
							$row->qty_asked = $line->qty;
							$row->tax_tx = $line->tva_tx;
							$row->localtax1_tx = $line->localtax1_tx;
							$row->localtax2_tx = $line->localtax2_tx;
							$row->total_net = $line->total_ht;
							$row->total_inc = $line->total_ttc;
							$row->total_tax = $line->total_tva;
							$row->total_localtax1 = $line->total_localtax1;
							$row->total_localtax2 = $line->total_localtax2;
							if (! empty($conf->global->PRODUIT_MULTIPRICES) && isset($multiprices_index)) {
								//! Arrays for multiprices
								$row->product_price=$myprod->multiprices[$multiprices_index];
								$row->product_price_ttc=$myprod->multiprices_ttc[$multiprices_index];
								$row->price_base_type=$myprod->multiprices_base_type[$multiprices_index];
							} else {
								$row->product_price = $myprod->price;
								$row->product_price_ttc = $myprod->price_ttc;
								$row->price_base_type = $myprod->price_base_type;
							}
							$row->rang = $line->rang;
							$row->price = $line->price;
							$row->subprice = $line->subprice;
							$row->reduction_percent = $line->remise_percent;
							$this->expeditions[$line->id]?$row->qty_shipped = $this->expeditions[$line->id]:$row->qty_shipped = 0;
							if (!empty($conf->global->STOCK_SHOW_VIRTUAL_STOCK_IN_PRODUCTS_COMBO)) {
								$row->is_virtual_stock = true;
								$row->stock = $myprod->stock_theorique;
							} else {
								$row->stock = $myprod->stock_reel;
							}
							$row->total_stock = $row->stock;
							$row->has_photo = 0;
							if (!$isFreeLine && !empty($photoSize)) {
								$myprod->fetchPhoto($row, $photoSize);
							}
							$row->unit_id = $line->fk_unit;
							(!empty($this->_user->rights->fournisseur->lire)) ? $row->cost_price = $line->pa_ht : $row->cost_price = 0;
							$row->is_sub_product = false;
							if ($isService) {
								$row->warehouse_id = -1; // service is not stocked
								array_push($results, $row);
							} elseif ($isFreeLine) {
								$row->warehouse_id = 0; // freeline is not in a specific stock location
								array_push($results, $row);
							} else {
								$row->warehouse_id = $warehouse_id;
								array_push($results, $row);
								$myprod->fetchSubProducts($results, clone $row, $photoSize);
							}
							if ($this->warehouse_id > 0) {
								$row->default_warehouse_id = $this->warehouse_id;
							} else {
								$row->default_warehouse_id = $myprod->fk_default_warehouse;
							}
						} else {
							// get orderline with stock of warehouse
							if (!isset($warehouse_id)) {
								$line_warehouse_id = 0; // no warehouse, stock is warehouse 0 (all warehouses)
							} else {
								$line_warehouse_id = $warehouse_id;
							}
							$row = new stdClass;
							$row->id = $line->id.'_'.$line_warehouse_id;
							$row->is_virtual_stock = false;
							$row->origin_id = $line->fk_commande;
							$row->origin_line_id = $line->id;
							if (empty($line->label)) {
								$row->label = $line->product_label;
							} else {
								$row->label = $line->label;
							}
							$row->description = $line->desc;
							$row->product_id = $line->fk_product;
							$row->ref = $line->product_ref;
							$row->product_label = $line->product_label;
							$row->product_desc = $line->product_desc;
							$row->product_type = $line->product_type;
							$row->barcode= $myprod->barcode?$myprod->barcode:'';
							$row->barcode_type = $myprod->barcode_type?$myprod->barcode_type:0;
							$row->barcode_with_checksum = $myprod->barcode?$myprod->fetchBarcodeWithChecksum($myprod):'';
							$row->qty_asked = $line->qty;
							$row->tax_tx = $line->tva_tx;
							$row->localtax1_tx = $line->localtax1_tx;
							$row->localtax2_tx = $line->localtax2_tx;
							$row->total_net = $line->total_ht;
							$row->total_inc = $line->total_ttc;
							$row->total_tax = $line->total_tva;
							$row->total_localtax1 = $line->total_localtax1;
							$row->total_localtax2 = $line->total_localtax2;
							if (! empty($conf->global->PRODUIT_MULTIPRICES) && isset($multiprices_index)) {
								//! Arrays for multiprices
								$row->product_price=$myprod->multiprices[$multiprices_index];
								$row->product_price_ttc=$myprod->multiprices_ttc[$multiprices_index];
								$row->price_base_type=$myprod->multiprices_base_type[$multiprices_index];
							} else {
								$row->product_price = $myprod->price;
								$row->product_price_ttc = $myprod->price_ttc;
								$row->price_base_type = $myprod->price_base_type;
							}
							$row->rang = $line->rang;
							$row->price = $line->price;
							$row->subprice = $line->subprice;
							$row->reduction_percent = $line->remise_percent;
							$this->expeditions[$line->id]?$row->qty_shipped = $this->expeditions[$line->id]:$row->qty_shipped = 0;
							if (!empty($conf->global->STOCK_SHOW_VIRTUAL_STOCK_IN_PRODUCTS_COMBO)) {
								if (!empty($line_warehouse_id)) {
									$row->stock = (float) $myprod->stock_warehouse[$line_warehouse_id]->real;
								} else {
									$row->is_virtual_stock = true;
									$row->stock = $myprod->stock_theorique;
								}
								$row->total_stock = $myprod->stock_theorique;
							} else {
								!empty($line_warehouse_id) ? $row->stock = (float) $myprod->stock_warehouse[$line_warehouse_id]->real : $row->stock = $myprod->stock_reel;
								$row->total_stock = $myprod->stock_reel;
							}
							$row->warehouse_id = $line_warehouse_id;
							if ($this->warehouse_id > 0) {
								$row->default_warehouse_id = $this->warehouse_id;
							} else {
								$row->default_warehouse_id = $myprod->fk_default_warehouse;
							}
							$row->has_photo = 0;
							if (!$isFreeLine && !empty($photoSize)) {
								$myprod->fetchPhoto($row, $photoSize);
							}
							$row->unit_id = $line->fk_unit;
							(!empty($this->_user->rights->fournisseur->lire)) ? $row->cost_price = $line->pa_ht : $row->cost_price = 0;
							// split orderlines by batch
							if (! empty($conf->productbatch->enabled)) $row->has_batch = $myprod->status_batch;
							$row->is_sub_product = false;
							if (empty($conf->productbatch->enabled) || empty($line_warehouse_id)) {
								array_push($results, $row);
								$myprod->fetchSubProducts($results, clone $row, $photoSize);
							} else {
								if (($res = $myprod->fetchBatches($results, $row, $line->id, $line_warehouse_id, $myprod->stock_warehouse[$line_warehouse_id]->id, false, null, '', $photoSize)) < 0) return $res;
							}
						}
					} else {
						foreach ($myprod->stock_warehouse as $warehouse=>$stock_warehouse) {
							if (empty($conf->global->STOCK_MUST_BE_ENOUGH_FOR_SHIPMENT) || ($stock_warehouse->real > 0)) {
								$row = new stdClass;
								$row->id = $line->id.'_'.$warehouse;
								$row->is_virtual_stock = false;
								$row->origin_id = $line->fk_commande;
								$row->origin_line_id = $line->id;
								if (empty($line->label)) {
									$row->label = $line->product_label;
								} else {
									$row->label = $line->label;
								}
								$row->description = $line->desc;
								$row->product_id = $line->fk_product;
								$row->ref = $line->product_ref;
								$row->product_label = $line->product_label;
								$row->product_desc = $line->product_desc;
								$row->barcode= $myprod->barcode?$myprod->barcode:'';
								$row->barcode_type = $myprod->barcode_type?$myprod->barcode_type:0;
								$row->barcode_with_checksum = $myprod->barcode?$myprod->fetchBarcodeWithChecksum($myprod):'';
								$row->product_type = $line->product_type;
								$row->qty_asked = $line->qty;
								$row->tax_tx = $line->tva_tx;
								$row->localtax1_tx = $line->localtax1_tx;
								$row->localtax2_tx = $line->localtax2_tx;
								$row->total_net = $line->total_ht;
								$row->total_inc = $line->total_ttc;
								$row->total_tax = $line->total_tva;
								$row->total_localtax1 = $line->total_localtax1;
								$row->total_localtax2 = $line->total_localtax2;
								if (! empty($conf->global->PRODUIT_MULTIPRICES) && isset($multiprices_index)) {
									//! Arrays for multiprices
									$row->product_price=$myprod->multiprices[$multiprices_index];
									$row->product_price_ttc=$myprod->multiprices_ttc[$multiprices_index];
									$row->price_base_type=$myprod->multiprices_base_type[$multiprices_index];
								} else {
									$row->product_price = $myprod->price;
									$row->product_price_ttc = $myprod->price_ttc;
									$row->price_base_type = $myprod->price_base_type;
								}
								$row->rang = $line->rang;
								$row->price = $line->price;
								$row->subprice = $line->subprice;
								$row->reduction_percent = $line->remise_percent;
								$this->expeditions[$line->id]?$row->qty_shipped = $this->expeditions[$line->id]:$row->qty_shipped = 0;
								$row->stock = (float) $stock_warehouse->real;
								$row->total_stock = $myprod->stock_reel;
								$row->warehouse_id = $warehouse;
								if ($this->warehouse_id > 0) {
									$row->default_warehouse_id = $this->warehouse_id;
								} else {
									$row->default_warehouse_id = $myprod->fk_default_warehouse;
								}
								$row->has_photo = 0;
								if (!empty($photoSize)) {
									$myprod->fetchPhoto($row, $photoSize);
								}
								$row->unit_id = $line->fk_unit;
								(!empty($this->_user->rights->fournisseur->lire)) ? $row->cost_price = $line->pa_ht : $row->cost_price = 0;
								// split orderlines by batch
								if (! empty($conf->productbatch->enabled)) $row->has_batch = $myprod->status_batch;
								$row->is_sub_product = false;
								if (empty($conf->productbatch->enabled)) {
									array_push($results, $row);
									$myprod->fetchSubProducts($results, clone $row, $photoSize);
									$myprod->fetch($line->fk_product);
								} else {
									if (($res = $myprod->fetchBatches($results, $row, $line->id, $warehouse, $stock_warehouse->id, false, null, '', $photoSize)) < 0) return $res;
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
	* public method to read available line optionals (extra fields)
	*
	* @return stdClass result data or ERROR
	*/
	public function readLineOptionalModel()
	{
		if (!isset($this->db)) return CONNECTERROR;

		$orderLine = new OrderLine($this->db);

		return ExtDirect::readOptionalModel($orderLine);
	}

	/**
	 * public method to read order line optionals (extra fields) from database
	 *
	 *    @param    stdClass    $param  filter with elements:
	 *                                  id  Id of order to load
	 *
	 *    @return     stdClass result data or -1
	 */
	public function readLineOptionals(stdClass $param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->commande->lire)) return PERMISSIONERROR;
		$results = array();
		$line_id = 0;

		if (isset($param->filter)) {
			foreach ($param->filter as $key => $filter) {
				if ($filter->property == 'line_id') $line_id=$filter->value;
			}
		}

		if ($line_id > 0) {
			$extraFields = new ExtraFields($this->db);
			$orderLine = new OrderLine($this->db);
			$orderLine->id = $line_id;
			if (($result = $orderLine->fetch_optionals()) < 0) return ExtDirect::getDolError($result, $orderLine->errors, $orderLine->error);
			if (! $orderLine->error) {
				$extraFields->fetch_name_optionals_label($orderLine->table_element);
				$index = 1;
				if (empty($orderLine->array_options)) {
					// create empty optionals to be able to add optionals
					$optionsArray = (!empty($extraFields->attributes[$orderLine->table_element]['label']) ? $extraFields->attributes[$orderLine->table_element]['label'] : null);
					if (is_array($optionsArray) && count($optionsArray) > 0) {
						foreach ($optionsArray as $name => $label) {
							$row = new stdClass;
							$row->id = $index++;
							$row->name = $name;
							$row->value = '';
							$row->object_id = $orderLine->id;
							$row->object_element = $orderLine->element;
							$row->raw_value = null;
							$results[] = $row;
						}
					}
				} else {
					foreach ($orderLine->array_options as $key => $value) {
						if (!empty($value)) {
							$row = new stdClass;
							$name = substr($key, 8); // strip options_
							$row->id = $index++; // ExtJs needs id to be able to destroy records
							$row->name = $name;
							$row->value = $extraFields->showOutputField($name, $value, '', $orderLine->table_element);
							$row->object_id = $orderLine->id;
							$row->object_element = $orderLine->element;
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
	 * public method to update optionals (extra fields) into database
	 *
	 *    @param     unknown_type    $params  optionals
	 *
	 *    @return     Ambigous <multitype:, unknown_type>|unknown
	 */
	public function updateLineOptionals($params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->commande->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);

		$orderLine = new OrderLine($this->db);
		foreach ($paramArray as &$param) {
			if ($orderLine->id != $param->object_id) {
				$orderLine->id = $param->object_id;
				if (($result = $orderLine->fetch_optionals()) < 0) return ExtDirect::getDolError($result, $orderLine->errors, $orderLine->error);
			}
			$orderLine->array_options['options_'.$param->name] = $param->raw_value;
		}
		if (($result = $orderLine->insertExtraFields()) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
		if (is_array($params)) {
			return $paramArray;
		} else {
			return $param;
		}
	}

	/**
	 * public method to add optionals (extra fields) into database
	 *
	 *    @param    unknown_type    $params  optionals
	 *
	 *    @return     Ambigous <multitype:, unknown_type>|unknown
	 */
	public function createLineOptionals($params)
	{
		return $this->updateLineOptionals($params);
	}

	/**
	 * public method to delete optionals (extra fields) into database
	 *
	 *    @param     unknown_type    $params  optionals
	 *
	 *    @return    Ambigous <multitype:, unknown_type>|unknown
	 */
	public function destroyLineOptionals($params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->commande->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);

		$orderLine = new OrderLine($this->db);
		foreach ($paramArray as &$param) {
			if ($orderLine->id != $param->object_id) {
				$orderLine->id = $param->object_id;
				if (($result = $orderLine->fetch_optionals()) < 0) return ExtDirect::getDolError($result, $orderLine->errors, $orderLine->error);
			}
		}
		if (($result = $orderLine->deleteExtraFields()) < 0) return ExtDirect::getDolError($result, $orderLine->errors, $orderLine->error);
		if (is_array($params)) {
			return $paramArray;
		} else {
			return $param;
		}
	}

	/**
	 * Ext.direct method to Create Orderlines
	 *
	 * @param unknown_type $param object or object array with product model(s)
	 * @return Ambigous <multitype:, unknown_type>|unknown
	 */
	public function createOrderLine($param)
	{
		global $conf, $mysoc;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->commande->creer)) return PERMISSIONERROR;
		$orderLine = new OrderLine($this->db);

		$paramArray = ExtDirect::toArray($param);
		$result = 0;

		foreach ($paramArray as &$params) {
			// prepare fields
			$this->prepareOrderLineFields($params, $orderLine);
			if (($result = $this->fetch($orderLine->fk_commande)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
			$this->fetch_thirdparty();
			if (ExtDirect::checkDolVersion(0, '10.0')) {
				if (empty($orderLine->fk_product)) {
					$tva_tx = $orderLine->tva_tx;
					$tva_npr = 0;
				} else {
					dol_include_once('/extdirect/class/ExtDirectProduct.class.php');
					$myprod = new ExtDirectProduct($this->_user->login);
					$result = $myprod->fetch($orderLine->fk_product);
					if ($result < 0) return ExtDirect::getDolError($result, $myprod->errors, $myprod->error);
					$priceArray = $myprod->getSellPrice($mysoc, $this->thirdparty);
					$tva_tx = $priceArray['tva_tx'];
					$tva_npr = $priceArray['tva_npr'];
				}
			} else {
				if ((! empty($conf->global->PRODUIT_MULTIPRICES) && ! empty($this->thirdparty->price_level)) || ! empty($conf->global->PRODUIT_CUSTOMER_PRICES)) {
					if (!empty($this->thirdparty->tva_assuj)) {
						$tva_tx = $orderLine->tva_tx;
					} else {
						$tva_tx = 0;
					}
					$tva_npr = 0;
				} else {
					$tva_tx = get_default_tva($mysoc, $this->thirdparty, $orderLine->fk_product);
					$tva_npr = get_default_npr($mysoc, $this->thirdparty, $orderLine->fk_product);
				}
			}

			// Local Taxes
			$localtax1_tx = get_localtax($tva_tx, 1, $this->thirdparty);
			$localtax2_tx = get_localtax($tva_tx, 2, $this->thirdparty);
			$localtaxes_array = getLocalTaxesFromRate($tva_tx, 0, $this->thirdparty, $mysoc);

			$info_bits = 0;
			if ($tva_npr) $info_bits |= 0x01;
			if (!empty($params->product_price) || !empty($params->product_price_ttc)) {
				// when product_price is available, use product price for calculating unit price
				if ($orderLine->price_base_type == 'TTC') {
					$tabprice = calcul_price_total($orderLine->qty, $params->product_price_ttc, $orderLine->remise_percent, $tva_tx, $localtax1_tx, $localtax2_tx, 0, $orderLine->price_base_type, $info_bits, $orderLine->product_type, $mysoc, $localtaxes_array);
					$pu_ht = $tabprice[3];
					$pu_ttc = $tabprice[5];
				} else {
					$tabprice = calcul_price_total($orderLine->qty, $params->product_price, $orderLine->remise_percent, $tva_tx, $localtax1_tx, $localtax2_tx, 0, $orderLine->price_base_type, $info_bits, $orderLine->product_type, $mysoc, $localtaxes_array);
					$pu_ht = $tabprice[3];
					$pu_ttc = $tabprice[5];
				}
			} else {
				$tabprice = calcul_price_total($orderLine->qty, $orderLine->subprice, $orderLine->remise_percent, $tva_tx, $localtax1_tx, $localtax2_tx, 0, 'HT', $info_bits, $orderLine->product_type, $mysoc, $localtaxes_array);
				$pu_ht = $tabprice[3];
				$pu_ttc = $tabprice[5];
			}

			$this->id = $orderLine->fk_commande;
			if (($result = $this->addline(
				$orderLine->desc,
				$pu_ht,
				$orderLine->qty,
				$tva_tx,
				$localtax1_tx,
				$localtax2_tx,
				$orderLine->fk_product,
				$orderLine->remise_percent,
				$info_bits,
				$orderLine->fk_remise_except,
				$orderLine->price_base_type,
				$pu_ttc,
				$orderLine->date_start,
				$orderLine->date_end,
				$orderLine->product_type,
				$orderLine->rang,
				$orderLine->special_code,
				$orderLine->fk_parent_line,
				$orderLine->fk_fournprice,
				$orderLine->pa_ht,
				$orderLine->label,
				0,
				$orderLine->fk_unit
			)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
			$params->id=$result;
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
		global $conf, $mysoc;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->commande->creer)) return PERMISSIONERROR;
		$orderlineUpdated = false;

		$paramArray = ExtDirect::toArray($param);

		foreach ($paramArray as &$params) {
			if (($this->id=$params->origin_id) > 0) {
				// get old orderline
				if (($result = $this->fetch($this->id)) < 0)  return ExtDirect::getDolError($result, $this->errors, $this->error);
				if (($result = $this->fetch_lines()) < 0)  return ExtDirect::getDolError($result, $this->errors, $this->error);
				$this->fetch_thirdparty();

				if (!$this->error) {
					foreach ($this->lines as $orderLine) {
						if ($orderLine->id == $params->origin_line_id) {
							// update fields
							$this->prepareOrderLineFields($params, $orderLine);
							if (ExtDirect::checkDolVersion(0, '10.0')) {
								if (empty($orderLine->fk_product)) {
									$tva_tx = $orderLine->tva_tx;
									$tva_npr = 0;
								} else {
									dol_include_once('/extdirect/class/ExtDirectProduct.class.php');
									$myprod = new ExtDirectProduct($this->_user->login);
									$result = $myprod->fetch($orderLine->fk_product);
									if ($result < 0) return ExtDirect::getDolError($result, $myprod->errors, $myprod->error);
									$priceArray = $myprod->getSellPrice($mysoc, $this->thirdparty);
									$tva_tx = $priceArray['tva_tx'];
									$tva_npr = $priceArray['tva_npr'];
								}
							} else {
								if ((! empty($conf->global->PRODUIT_MULTIPRICES) && ! empty($this->thirdparty->price_level)) || ! empty($conf->global->PRODUIT_CUSTOMER_PRICES)) {
									if (!empty($this->thirdparty->tva_assuj)) {
										$tva_tx = $orderLine->tva_tx;
									} else {
										$tva_tx = 0;
									}
									$tva_npr = 0;
								} else {
									$tva_tx = get_default_tva($mysoc, $this->thirdparty, $orderLine->fk_product);
									$tva_npr = get_default_npr($mysoc, $this->thirdparty, $orderLine->fk_product);
								}
							}

							// Local Taxes
							$localtax1_tx = get_localtax($tva_tx, 1, $this->thirdparty);
							$localtax2_tx = get_localtax($tva_tx, 2, $this->thirdparty);

							$info_bits = 0;
							if ($tva_npr) {
								$info_bits |= 0x01;
							} else {
								$info_bits = $orderLine->info_bits;
							}

							if (($result = $this->updateline(
								$orderLine->id,
								$orderLine->desc,
								$orderLine->subprice,
								$orderLine->qty,
								$orderLine->remise_percent,
								$tva_tx,
								$localtax1_tx,
								$localtax2_tx,
								'HT',
								$info_bits,
								$orderLine->date_start,
								$orderLine->date_end,
								$orderLine->product_type,
								$orderLine->fk_parent_line,
								$orderLine->skip_update_total,
								$orderLine->fk_fournprice,
								$orderLine->pa_ht,
								$orderLine->label,
								$orderLine->special_code,
								0,
								$orderLine->fk_unit
							)
							) < 0)  return ExtDirect::getDolError($result, $this->errors, $this->error);
							$orderlineUpdated = true;
						}
					}
					if (!$orderlineUpdated) return UPTADEERROR;
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
		if (!isset($this->_user->rights->commande->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($param);

		foreach ($paramArray as &$params) {
			// prepare fields
			if (empty($params->origin_line_id)) {
				$lineId = $params->id;
			} else {
				$lineId = $params->origin_line_id;
			}
			if (empty($params->origin_id)) {
				$orderLine = new OrderLine($this->db);
				$orderLine->fetch($lineId);
				$this->id = $orderLine->fk_commande;
			} else {
				$this->id = $params->origin_id;
			}
			if ($lineId && $this->id) {
				// delete
				if (ExtDirect::checkDolVersion(0, '5.0', '')) {
					if (($result = $this->deleteline($this->_user, $lineId)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				} else {
					if (($result = $this->deleteline($lineId)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
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
	 * private method to copy order fields into dolibarr object
	 *
	 * @param stdclass $params object with fields
	 * @param stdclass $orderLine object
	 * @return null
	 */
	private function prepareOrderLineFields($params, $orderLine)
	{
		isset($params->origin_line_id) ? ( $orderLine->rowid= $params->origin_line_id) : null;
		isset($params->origin_id) ? ( $orderLine->fk_commande= $params->origin_id) : null;
		isset($params->product_id) ? ( $orderLine->fk_product = $params->product_id) : null;
		isset($params->subprice) ? ( $orderLine->subprice = $params->subprice) : null;
		isset($params->product_tax) ? ( $orderLine->tva_tx = $params->product_tax) : (isset($params->tax_tx) ? ( $orderLine->tva_tx = $params->tax_tx) : null);
		isset($params->description) ? ( $orderLine->desc = $params->description) : null;
		isset($params->qty_asked) ? ( $orderLine->qty = $params->qty_asked) : null;
		isset($params->reduction_percent) ? ($orderLine->remise_percent = $params->reduction_percent) : null;
		isset($params->localtax1_tx) ? ($orderLine->localtax1_tx = $params->localtax1_tx) : null;
		isset($params->localtax2_tx) ? ($orderLine->localtax2_tx = $params->localtax2_tx) : null;
		isset($params->product_type) ? ($orderLine->product_type = $params->product_type) : null;
		isset($params->rang) ? ($orderLine->rang = $params->rang) : null;
		isset($params->label) ? ($orderLine->label = $params->label) : null;
		isset($params->price) ? ($orderLine->price = $params->price) : ($orderLine->price ? null : $orderLine->price = 0);
		isset($params->price_base_type) ? ($orderLine->price_base_type = $params->price_base_type) : $orderLine->price_base_type = 'HT';
		isset($params->unit_id) ? ($orderLine->fk_unit = $params->unit_id) : null;
	}
}
