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

require_once DOL_DOCUMENT_ROOT . '/expedition/class/expedition.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
dol_include_once('/extdirect/class/extdirect.class.php');
dol_include_once('/extdirect/class/ExtDirectFormProduct.class.php');
dol_include_once('/extdirect/class/ExtDirectProduct.class.php');

/** ExtDirectExpedition class
 * Class to access shipments with CRUD methods to connect to Extjs or sencha touch using Ext.direct connector
 */
class ExtDirectExpedition extends Expedition
{
	private $_user;
	private $_shipmentConstants = array('STOCK_MUST_BE_ENOUGH_FOR_SHIPMENT', 'STOCK_CALCULATE_ON_SHIPMENT');
	private $_enabled = false;

	/** @var string $key_ship_line_order key of linked order to ship line */
	public $key_ship_line_order = 'fk_element';

	/** @var string $key_ship_line_order_line key of linked order line to ship line */
	public $key_ship_line_order_line = 'fk_origin_line';

	const STATUS_DRAFT = 0;
	const STATUS_VALIDATED = 1;
	const STATUS_CLOSED = 2;

	/**
	 * end status to allow status itteration
	 */
	const STATUS_END = 3;

	/** Constructor
	 *
	 * @param string $login user name
	 */
	public function __construct($login)
	{
		global $langs, $user, $db, $conf, $mysoc;

		if (!empty($login)) {
			if ((is_object($login) && get_class($db) == get_class($login)) || $user->id > 0 || $user->fetch('', $login, '', 1) > 0) {
				$user->getrights();
				$this->_enabled = !empty($conf->expedition->enabled) && isset($user->rights->expedition->lire);
				$this->_user = $user;  //commande.class uses global user
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
				$langs->load("errors");
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
	 *		                                    constant	name of specific constant
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
	 *                                      id Id of shipment to load
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
		$originObject = null;

		if (isset($params->filter)) {
			foreach ($params->filter as $key => $filter) {
				if ($filter->property == 'id') $id = $filter->value;
				elseif ($filter->property == 'ref') $ref = $filter->value;
				elseif ($filter->property == 'ref_ext') $ref_ext = $filter->value;
			}
		}

		if (($id > 0) || ($ref != '') || ($ref_ext != '')) {
			if (($result = $this->fetch($id, $ref, $ref_ext)) < 0) {
				if ($result = -2) {
					return 0; // return 0 whem not found
				} else {
					return ExtDirect::getDolError($result, $this->errors, $this->error);
				}
			}
			if (!$this->error) {
				$row->id = $this->id;
				//! Ref
				$row->ref = $this->ref;
				$row->ref_customer = $this->ref_customer;
				$row->customer_id = $this->socid;
				if ($mySociete->fetch($this->socid) > 0) {
					$row->customer_name = $mySociete->name;
				}
				//! -1 for cancelled, 0 for draft, 1 for validated, 2 for processed
				$row->orderstatus_id = (int) $this->statut; // deprecated
				$row->orderstatus = $this->getLibStatut(1); // deprecated
				$row->shipmentstatus_id = (int) $this->statut;
				$row->shipmentstatus = $this->getLibStatut(1);
				$row->note_private = $this->note_private;
				$row->note_public = $this->note_public;
				$row->user_id = $this->user_author_id;
				if ($this->user_author_id > 0 && $myUser->fetch($this->user_author_id) > 0) {
					$row->user_name = $myUser->firstname . ' ' . $myUser->lastname;
				}
				$row->shipment_date = $this->date_shipping;
				$row->deliver_date = $this->date_delivery;
				$row->origin_id = $this->origin_id;
				$row->origin = $this->origin;
				if (!empty($this->origin) && !empty($this->origin_id)) {
					if ($this->origin == 'commande') {
						$originObject = new Commande($this->db);
						if ($originObject->fetch($this->origin_id) > 0) {
							$row->origin_ref = $originObject->ref;
						}
					}
				}
				$row->weight_units = $this->weight_units;
				$row->weight = $this->trueWeight;
				$row->size_units = $this->size_units;
				$row->trueDepth = $this->trueDepth; // deprecated
				$row->trueWidth = $this->trueWidth; // deprecated
				$row->trueHeight = $this->trueHeight; // deprecated
				$row->length = $this->trueDepth;
				$row->width = $this->trueWidth;
				$row->height = $this->trueHeight;
				$row->shipping_method_id = $this->shipping_method_id;
				$row->incoterms_id = $this->fk_incoterms;
				$row->location_incoterms = $this->location_incoterms;
				$row->tracking_number = $this->tracking_number;
				$row->model_pdf = $this->model_pdf;
				$row->date_creation = $this->date_creation;
				$row->delivery_address_id = $this->fk_delivery_address;
				$row->ref_ext = $this->ref_ext;
				array_push($results, $row);
			} else {
				return 0;
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
	 * public method to read shipment optionals (extra fields) from database
	 *
	 *    @param    stdClass    $param  filter with elements:
	 *                                  id Id of shipment to load
	 *
	 *    @return     stdClass result data or -1
	 */
	public function readOptionals(stdClass $param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->expedition->lire)) return PERMISSIONERROR;
		$results = array();
		$id = 0;

		if (isset($param->filter)) {
			foreach ($param->filter as $key => $filter) {
				if ($filter->property == 'id') $id = $filter->value;
			}
		}

		if ($id > 0) {
			$extraFields = new ExtraFields($this->db);
			if (($result = $this->fetch($id)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
			if (!$this->error) {
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
		if (!isset($this->_user->rights->expedition->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);

		foreach ($paramArray as &$param) {
			if ($this->id != $param->object_id && ($result = $this->fetch($param->object_id)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
			$this->array_options['options_' . $param->name] = $param->raw_value;
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
		if (!isset($this->_user->rights->expedition->creer)) return PERMISSIONERROR;
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
			$params->id = $this->id;
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
		global $conf, $langs;

		if (!isset($this->db)) return CONNECTERROR;

		$paramArray = ExtDirect::toArray($param);

		foreach ($paramArray as &$params) {
			// prepare fields
			if ($params->id) {
				$id = $params->id;
				if (($result = $this->fetch($id)) < 0)  return $result;
				$this->prepareFields($params);
				if (isset($params->shipmentstatus_id)) {
					$status = $params->shipmentstatus_id;
				} else {
					$status = $params->orderstatus_id; // deprecated
				}
				// update
				switch ($status) {
					case -1:
						break;
					case 0:
						break;
					case 1:
						$result = $this->valid($this->_user);
						// PDF generating
						if (($result >= 0) && empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE)) {
							$hidedetails = (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS) ? 1 : 0);
							$hidedesc = (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DESC) ? 1 : 0);
							$hideref = (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_REF) ? 1 : 0);
							$outputlangs = $langs;
							if ($conf->global->MAIN_MULTILANGS) {
								$this->fetch_thirdparty();
								$newlang = $this->thirdparty->default_lang;
								$outputlangs = new Translate("", $conf);
								$outputlangs->setDefaultLang($newlang);
							}
							(property_exists($this, 'model_pdf')) ? $model_pdf = $this->model_pdf : $model_pdf = $this->modelpdf; // For backward compatibility
							$this->generateDocument($model_pdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
						}
						break;
					case 2:
						$result = $this->setClosed();
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
	 * Ext.direct method to upload file for shipment object
	 *
	 * @param unknown_type $params object or object array with uploaded file(s)
	 * @return Array    ExtDirect response message
	 */
	public function fileUpload($params)
	{
		global $conf;
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->expedition->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);
		$dir = null;

		foreach ($paramArray as &$param) {
			if (isset($param['extTID'])) {
				$id = $param['extTID'];
				if ($this->fetch($id)) {
					$this->fetch_thirdparty();
					$dir = $conf->expedition->dir_output . "/sending/" . dol_sanitizeFileName($this->ref);
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
	 * private method to copy fields into dolibarr object
	 *
	 * @param stdclass $params object with fields
	 * @return null
	 */
	private function prepareFields($params)
	{
		isset($params->origin_id) ? $this->origin_id = $params->origin_id : (isset($this->origin_id) ? null : $this->origin_id = null);
		isset($params->origin) ? $this->origin = $params->origin : (isset($this->origin) ? null :  $this->origin = null);
		isset($params->ref_ext) ? $this->ref_ext = $params->ref_ext : (isset($this->ref_ext) ? null : $this->ref_ext = null);
		isset($params->ref_customer) ? $this->ref_customer = $params->ref_customer : (isset($this->ref_customer) ? null : $this->ref_customer = null);
		isset($params->customer_id) ? $this->socid = $params->customer_id : (isset($this->socid) ? null : $this->socid = null);
		isset($params->deliver_date) ? $this->date_delivery = $params->deliver_date : (isset($this->date_delivery) ? null : $this->date_delivery = null);
		isset($params->shipment_date) ? $this->date_shipping = $params->shipment_date : (isset($this->date_shipping) ? null : $this->date_shipping = null);
		isset($params->weight_units) ? $this->weight_units = $params->weight_units : (isset($this->weight_units) ? null : $this->weight_units = 0);
		isset($params->weight) ? $this->trueWeight = $params->weight : (isset($this->trueWeight) ? null : $this->trueWeight = 0); // for update
		isset($params->weight) ? $this->weight = $params->weight : (isset($this->weight) ? null : $this->weight = 0); // for create
		isset($params->size_units) ? $this->size_units = $params->size_units : (isset($this->size_units) ? null : $this->size_units = 0);
		// deprecated sizes for create
		isset($params->trueDepth) ? $this->sizeS = $params->trueDepth : (isset($this->sizeS) ? null : $this->sizeS = 0);
		isset($params->trueWidth) ? $this->sizeW = $params->trueWidth : (isset($this->sizeW) ? null : $this->sizeW = 0);
		isset($params->trueHeight) ? $this->sizeH = $params->trueHeight : (isset($this->sizeH) ? null : $this->sizeH = 0);
		// deprecated sizes for update
		isset($params->trueDepth) ? $this->trueDepth = $params->trueDepth : (isset($this->trueDepth) ? null : $this->trueDepth = 0);
		isset($params->trueWidth) ? $this->trueWidth = $params->trueWidth : (isset($this->trueWidth) ? null : $this->trueWidth = 0);
		isset($params->trueHeight) ? $this->trueHeight = $params->trueHeight : (isset($this->trueHeight) ? null : $this->trueHeight = 0);
		// sizes for create
		isset($params->length) ? $this->sizeS = $params->length : (isset($this->sizeS) ? null : $this->sizeS = 0);
		isset($params->width) ? $this->sizeW = $params->width : (isset($this->sizeW) ? null : $this->sizeW = 0);
		isset($params->height) ? $this->sizeH = $params->height : (isset($this->sizeH) ? null : $this->sizeH = 0);
		// sizes for update
		isset($params->length) ? $this->trueDepth = $params->length : (isset($this->trueDepth) ? null : $this->trueDepth = 0);
		isset($params->width) ? $this->trueWidth = $params->width : (isset($this->trueWidth) ? null : $this->trueWidth = 0);
		isset($params->height) ? $this->trueHeight = $params->height : (isset($this->trueHeight) ? null : $this->trueHeight = 0);
		isset($params->shipping_method_id) ? $this->shipping_method_id = $params->shipping_method_id : null;
		isset($params->incoterms_id) ? $this->fk_incoterms = $params->incoterms_id : null;
		isset($params->location_incoterms) ? $this->location_incoterms = $params->location_incoterms : null;
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
		if (!$this->_enabled) return NOTENABLEDERROR;
		if (!isset($this->_user->rights->expedition->lire)) return PERMISSIONERROR;
		$result = new stdClass;
		$data = array();

		$statusFilterCount = 0;
		$ref = null;
		$contactTypeId = 0;
		$originId = 0;
		$barcode = null;
		$contentFilter = null;

		$includeTotal = true;

		if (isset($params->limit)) {
			$limit = $params->limit;
			$start = $params->start;
		}
		if (isset($params->include_total)) {
			$includeTotal = $params->include_total;
		}

		if (isset($params->filter)) {
			foreach ($params->filter as $key => $filter) {
				if ($filter->property == 'shipmentstatus_id') $shipmentstatus_id[$statusFilterCount++] = $filter->value;
				elseif ($filter->property == 'orderstatus_id') $shipmentstatus_id[$statusFilterCount++] = $filter->value; // deprecated
				elseif ($filter->property == 'ref') $ref = $filter->value;
				elseif ($filter->property == 'contacttype_id') $contactTypeId = $filter->value;
				elseif ($filter->property == 'contact_id') $contactId = $filter->value;
				elseif ($filter->property == 'origin_id') $originId = $filter->value;
				elseif ($filter->property == 'barcode') $barcode = $filter->value;
				elseif ($filter->property == 'content') $contentFilter = $filter->value;
			}
		}

		if ($barcode && !empty($conf->shipmentpackage->enabled)) {
			$extProduct = new ExtDirectProduct($this->db);
			$fk_product = null;
			$batch = null;
			$idArray = $extProduct->fetchIdFromBarcode($barcode);
			if ($idArray['product'] > 0) {
				$fk_product = $idArray['product'];
			} else {
				$batch = $barcode;
			}
		}

		$sqlFields = "SELECT s.nom, s.rowid AS socid, e.rowid, e.ref, e.fk_statut, e.ref_ext, ea.status, csm.libelle as mode, e.date_delivery";
		$sqlFrom = " FROM " . MAIN_DB_PREFIX . "societe as s, " . MAIN_DB_PREFIX . "expedition as e";
		if ($contactTypeId > 0) $sqlFrom .= " LEFT JOIN " . MAIN_DB_PREFIX . "element_contact as ec ON e.rowid = ec.element_id";
		if ($originId) {
			$sqlFrom .= " INNER JOIN " . MAIN_DB_PREFIX . "element_element as el ON el.fk_target = e.rowid AND fk_source = " . $originId;
			$sqlFrom .= " AND el.sourcetype = 'commande' AND el.targettype = '" . $this->db->escape($this->element) . "'";
		}
		if ($barcode) {
			$sqlFrom .= " LEFT JOIN ".MAIN_DB_PREFIX."expeditiondet as ed ON e.rowid = ed.fk_expedition";
			$sqlFrom .= " LEFT JOIN ".MAIN_DB_PREFIX."commandedet as cd ON cd.rowid = ed.".$this->key_ship_line_order_line;
			$sqlFrom .= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = cd.fk_product";
			$sqlFrom .= " LEFT JOIN ".MAIN_DB_PREFIX."product_lot as pl ON pl.fk_product = cd.fk_product AND pl.batch = '".$this->db->escape($barcode)."'";
		}
		$sqlFrom .= " LEFT JOIN " . MAIN_DB_PREFIX . "c_shipment_mode as csm ON e.fk_shipping_method = csm.rowid";
		$sqlFrom .= " LEFT JOIN ("; // get latest extdirect activity status for commande to check if locked
		$sqlFrom .= "   SELECT ma.activity_id, ma.maxrow AS rowid, ea.status";
		$sqlFrom .= "   FROM (";
		$sqlFrom .= "    SELECT MAX( rowid ) AS maxrow, activity_id";
		$sqlFrom .= "    FROM " . MAIN_DB_PREFIX . "extdirect_activity";
		$sqlFrom .= "    GROUP BY activity_id";
		$sqlFrom .= "   ) AS ma, " . MAIN_DB_PREFIX . "extdirect_activity AS ea";
		$sqlFrom .= "   WHERE ma.maxrow = ea.rowid";
		$sqlFrom .= " ) AS ea ON e.rowid = ea.activity_id";
		$sqlWhere = " WHERE e.entity IN (" . getEntity('shipping', 1) . ')';
		$sqlWhere .= " AND e.fk_soc = s.rowid";

		if ($statusFilterCount > 0) {
			$sqlWhere .= " AND ( ";
			foreach ($shipmentstatus_id as $key => $fk_statut) {
				$sqlWhere  .= "e.fk_statut = " . $fk_statut;
				if ($key < ($statusFilterCount - 1)) $sqlWhere  .= " OR ";
			}
			$sqlWhere .= ")";
		}
		if ($ref) {
			$sqlWhere .= " AND e.ref = '" . $ref . "'";
		}
		if ($contactTypeId > 0) {
			$sqlWhere .= " AND ec.fk_c_type_contact = " . $contactTypeId;
			$sqlWhere .= " AND ec.fk_socpeople = " . $contactId;
		}
		if ($barcode) {
			$sqlWhere .= " AND (p.barcode LIKE '%".$this->db->escape($barcode)."%' OR e.ref = '".$this->db->escape($barcode)."' OR e.ref_customer = '".$this->db->escape($barcode)."'";
			$sqlWhere .= " OR pl.batch = '".$this->db->escape($barcode)."'";
			$sqlWhere .= ")";
		}

		if ($contentFilter) {
			$fields = array('e.ref', 'e.ref_ext', 's.nom');
			$sqlWhere .= " AND ".natural_search($fields, $contentFilter, 0, 1);
		}

		$sqlOrder = " ORDER BY ";
		if (isset($params->sort)) {
			$sorterSize = count($params->sort);
			foreach ($params->sort as $key => $sort) {
				if (!empty($sort->property)) {
					if ($sort->property == 'shipmentstatus_id') {
						$sortfield = 'e.fk_statut';
					} elseif ($sort->property == 'order_date') {
						$sortfield = 'e.date_creation';
					} elseif ($sort->property == 'ref') {
						$sortfield = 'e.ref';
					} elseif ($sort->property == 'deliver_date') {
						$sortfield = 'e.date_delivery';
					} elseif ($sort->property == 'customer') {
						$sortfield = 's.nom';
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
			$sqlOrder .= "e.date_creation DESC";
		}

		if ($limit) {
			$sqlLimit = $this->db->plimit($limit, $start);
		}

		if ($includeTotal) {
			$sqlTotal = 'SELECT COUNT(*) as total' . $sqlFrom . $sqlWhere;
			$resql = $this->db->query($sqlTotal);

			if ($resql) {
				$obj = $this->db->fetch_object($resql);
				$total = $obj->total;
				$this->db->free($resql);
			} else {
				return SQLERROR;
			}
		}

		$sql = $sqlFields . $sqlFrom . $sqlWhere . $sqlOrder . $sqlLimit;

		$resql = $this->db->query($sql);

		if ($resql) {
			$num = $this->db->num_rows($resql);
			for ($i = 0; $i < $num; $i++) {
				$obj = $this->db->fetch_object($resql);
				$row = new stdClass;
				$row->id            = (int) $obj->rowid;
				$row->customer      = $obj->nom;
				$row->customer_id   = (int) $obj->socid;
				$row->ref           = $obj->ref;
				$row->ref_ext       = $obj->ref_ext;
				$row->orderstatus_id = (int) $obj->fk_statut; // deprecated
				$row->orderstatus = html_entity_decode($this->LibStatut($obj->fk_statut, 1)); // deprecated
				$row->shipmentstatus_id = (int) $obj->fk_statut;
				$row->shipmentstatus = html_entity_decode($this->LibStatut($obj->fk_statut, 1));
				$row->status        = $obj->status;
				$row->mode          = $obj->mode;
				$row->deliver_date  = $this->db->jdate($obj->date_delivery);
				if ($barcode && !empty($conf->shipmentpackage->enabled)) {
					dol_include_once('/shipmentpackage/class/shipmentpackage.class.php');
					$shipmentPackage = new ShipmentPackage($this->db);
					if (method_exists($shipmentPackage, 'getQtyPackaged')) { // backwards compatible
						$row->qty_packaged = $shipmentPackage->getQtyPackaged($row->id, $fk_product, $batch);
						$row->qty_toship = $shipmentPackage->getQtyToShip($row->id, $fk_product, $batch);
					}
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
		$statut = 0;
		$row = new stdClass;
		while ($statut < self::STATUS_END) {
			$result = $this->LibStatut($statut, 1);
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
		$shipmentTypes = array();
		$orderTypes = array();
		$row = new stdClass;
		if (!is_array($shipmentTypes = $this->liste_type_contact())) return ExtDirect::getDolError($shipmentTypes, $this->errors, $this->error);
		// also get from order
		$order = new Commande($this->db);
		if (!is_array($orderTypes = $order->liste_type_contact())) return ExtDirect::getDolError($orderTypes, $this->errors, $this->error);
		// add empty type
		$row->id = 0;
		$row->label = '';
		array_push($results, $row);
		foreach ($shipmentTypes as $id => $label) {
			$row = new stdClass;
			$row->id = $id;
			$row->label = html_entity_decode($label);
			array_push($results, $row);
		}
		foreach ($orderTypes as $id => $label) {
			$row = new stdClass;
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
	 *                                      origin_id   Id of shipment to load lines from
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
		$hasSubProductFilter = false;
		$photoSize = '';

		if (isset($params->filter)) {
			foreach ($params->filter as $key => $filter) {
				if ($filter->property == 'origin_id') $origin_id = $filter->value;
				if ($filter->property == 'warehouse_id') $warehouse_id = $filter->value;
				if ($filter->property == 'photo_size' && !empty($filter->value)) $photoSize = $filter->value;
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

		if ($origin_id > 0) {
			$this->id = $origin_id;
			if (($result = $this->fetch_lines()) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
			if (!$this->error) {
				foreach ($this->lines as $key => $line) {
					if (!empty($conf->shipmentpackage->enabled)) {
						dol_include_once('/shipmentpackage/class/shipmentpackage.class.php');
						$packageLine = new ShipmentPackageLine($this->db);
						$packagedQty = $packageLine->getQtyPackaged($line->line_id);
					} else {
						$packagedQty = 0;
					}
					if ($line->fk_product > 0) {
						$myprod = new ExtDirectProduct($this->_user->login);
						if (($result = $myprod->fetch($line->fk_product)) < 0) return $result;
						$myprod->fetch_barcode();
						$row->barcode= $myprod->barcode?$myprod->barcode:'';
						$row->barcode_type = $myprod->barcode_type?$myprod->barcode_type:0;
						$row->barcode_with_checksum = $myprod->barcode?$myprod->fetchBarcodeWithChecksum($myprod):'';
						if (!empty($conf->global->PRODUIT_SOUSPRODUITS)) $myprod->get_sousproduits_arbo();
					}
					$row->id = $line->line_id;
					$row->line_id = $line->line_id;
					$row->origin_line_id = $line->fk_origin_line;
					$row->description = $line->description;
					$row->product_id = $line->fk_product;
					$row->product_ref = $line->product_ref; // deprecated
					$row->ref = $line->product_ref;
					$row->product_label = $line->product_label;
					$row->product_desc = '';
					if (empty($line->label)) {
						if (empty($line->product_label)) {
							$row->label = $line->description;
						} else {
							$row->label = $line->product_label;
						}
					} else {
						$row->label = $line->label;
					}
					$row->origin_id = $origin_id;
					$row->qty_asked = $line->qty_asked;
					$row->qty_shipped = $line->qty_shipped;
					$row->has_photo = 0;
					if ($myprod && !empty($photoSize)) {
						$myprod->fetchPhoto($row, $photoSize);
					}
					if (isset($warehouse_id) && count($line->details_entrepot) > 1) { // line from from multi warehouse
						if ($warehouse_id > 0) {
							foreach ($line->details_entrepot as $details_entrepot) {
								// only return line for warehouse
								if ($warehouse_id == $details_entrepot->entrepot_id) {
									$row->warehouse_id = $details_entrepot->entrepot_id;
									$row->qty_shipped = $details_entrepot->qty_shipped;
									$row->id = $details_entrepot->line_id;
									$row->line_id =  $details_entrepot->line_id;
									// return line for each warehouse
									if (empty($conf->productbatch->enabled)) {
										$row->qty_toship = $packagedQty;
										array_push($results, clone $row);
										if ($myprod) $myprod->fetchSubProducts($results, clone $row, $photoSize);
									} else {
										if (($res = $this->fetchBatches($results, $row, $details_entrepot->line_id, $line->fk_product, $myprod, $photoSize, $packageLine)) < 0) return $res;
									}
								}
							}
						} elseif ($warehouse_id == ExtDirectFormProduct::ALLWAREHOUSE_ID) {
							foreach ($line->details_entrepot as $details_entrepot) {
								// return line for each warehouse
								$row->warehouse_id = $details_entrepot->entrepot_id;
								$row->qty_shipped = $details_entrepot->qty_shipped;
								$row->id = $details_entrepot->line_id.'_'.$details_entrepot->entrepot_id;
								$row->line_id =  $details_entrepot->line_id;
								// return line for each warehouse
								if (empty($conf->productbatch->enabled)) {
									if ($packagedQty <= $row->qty_shipped) {
										$row->qty_toship = $packagedQty;
										$packagedQty = 0;
									} else {
										$row->qty_toship = $row->qty_shipped;
										$packagedQty -= $row->qty_shipped;
									}
									array_push($results, clone $row);
									if ($myprod) $myprod->fetchSubProducts($results, clone $row, $photoSize);
								} else {
									if (($res = $this->fetchBatches($results, $row, $details_entrepot->line_id, $line->fk_product, $myprod, $photoSize, $packageLine)) < 0) return $res;
								}
							}
						}
					} else {
						// return line from single warehouse
						$row->warehouse_id = $line->entrepot_id;
						if (empty($conf->productbatch->enabled)) {
							$row->qty_toship = $packagedQty;
							array_push($results, clone $row);
							if ($myprod) $myprod->fetchSubProducts($results, clone $row, $photoSize);
						} else {
							if (($res = $this->fetchBatches($results, $row, $line->line_id, $line->fk_product, $myprod, $photoSize, $packageLine)) < 0) return $res;
						}
					}
				}
			} else {
				return SQLERROR;
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

		if (ExtDirect::checkDolVersion(0, '9.0', '')) {
			$line = new ExpeditionLigne($this->db);
		} else {
			$line = new ExtDirectExpeditionLine($this->db);
		}

		return ExtDirect::readOptionalModel($line);
	}

	/**
	 * public method to read shipment line optionals (extra fields) from database
	 *
	 *    @param    stdClass    $param  filter with elements:
	 *                                  id Id of shipment to load
	 *
	 *    @return     stdClass result data or -1
	 */
	public function readLineOptionals(stdClass $param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->expedition->lire)) return PERMISSIONERROR;
		$results = array();
		$line_id = 0;

		if (isset($param->filter)) {
			foreach ($param->filter as $key => $filter) {
				if ($filter->property == 'line_id') $line_id = $filter->value;
			}
		}

		if ($line_id > 0) {
			$extraFields = new ExtraFields($this->db);
			if (ExtDirect::checkDolVersion(0, '9.0', '')) {
				$line = new ExpeditionLigne($this->db);
			} else {
				$line = new ExtDirectExpeditionLine($this->db);
			}
			$line->id = $line_id;
			if (($result = $line->fetch_optionals()) < 0) return ExtDirect::getDolError($result, $line->errors, $line->error);
			if (!$line->error) {
				$extraFields->fetch_name_optionals_label($line->table_element);
				$index = 1;
				if (empty($line->array_options)) {
					// create empty optionals to be able to add optionals
					$optionsArray = (!empty($extraFields->attributes[$line->table_element]['label']) ? $extraFields->attributes[$line->table_element]['label'] : null);
					if (is_array($optionsArray) && count($optionsArray) > 0) {
						foreach ($optionsArray as $name => $label) {
							$row = new stdClass;
							$row->id = $index++;
							$row->name = $name;
							$row->value = '';
							$row->object_id = $line->id;
							$row->object_element = $line->element;
							$row->raw_value = null;
							$results[] = $row;
						}
					}
				} else {
					foreach ($line->array_options as $key => $value) {
						if (!empty($value)) {
							$row = new stdClass;
							$name = substr($key, 8); // strip options_
							$row->id = $index++; // ExtJs needs id to be able to destroy records
							$row->name = $name;
							$row->value = $extraFields->showOutputField($name, $value, '', $line->table_element);
							$row->object_id = $line->id;
							$row->object_element = $line->element;
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
	 *    @param    unknown_type    $params  optionals
	 *
	 *    @return     Ambigous <multitype:, unknown_type>|unknown
	 */
	public function updateLineOptionals($params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->expedition->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);

		if (ExtDirect::checkDolVersion(0, '9.0', '')) {
			$line = new ExpeditionLigne($this->db);
		} else {
			$line = new ExtDirectExpeditionLine($this->db);
		}
		foreach ($paramArray as &$param) {
			if ($line->id != $param->object_id) {
				$line->id = $param->object_id;
				if (($result = $line->fetch_optionals()) < 0) return ExtDirect::getDolError($result, $line->errors, $line->error);
			}
			$line->array_options['options_' . $param->name] = $param->raw_value;
		}
		if (($result = $line->insertExtraFields()) < 0) return ExtDirect::getDolError($result, $line->errors, $line->error);
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
	public function createLineOptionals($params)
	{
		return $this->updateLineOptionals($params);
	}

	/**
	 * public method to delete optionals (extra fields) into database
	 *
	 *    @param    unknown_type    $params  optionals
	 *
	 *    @return    Ambigous <multitype:, unknown_type>|unknown
	 */
	public function destroyLineOptionals($params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->expedition->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);

		if (ExtDirect::checkDolVersion(0, '9.0', '')) {
			$line = new ExpeditionLigne($this->db);
		} else {
			$line = new ExtDirectExpeditionLine($this->db);
		}
		foreach ($paramArray as &$param) {
			if ($line->id != $param->object_id) {
				$line->id = $param->object_id;
				if (($result = $line->fetch_optionals()) < 0) return ExtDirect::getDolError($result, $line->errors, $line->error);
			}
		}
		if (($result = $line->deleteExtraFields()) < 0) return ExtDirect::getDolError($result, $line->errors, $line->error);
		if (is_array($params)) {
			return $paramArray;
		} else {
			return $param;
		}
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
		$notrigger = 0;
		$paramArray = ExtDirect::toArray($param);
		$res = 0;
		$result = 0;
		$batches = array();
		$qtyShipped = 0;
		foreach ($paramArray as &$params) {
			// TODO make prepare fields function for shipment lines, will create a 'detail_batch object array'
			// TODO rewrite with to develop ExpeditionLigne::create function, this function will create a line included batch lines when detail_batch array available
			$this->id = $params->origin_id;
			dol_syslog(get_class($this) . '::' . __FUNCTION__ . " line id=" . $params->origin_line_id, LOG_DEBUG);
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
							$params->line_id = $res;
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
					if (($result = $this->create_line($params->warehouse_id, $params->origin_line_id,  $params->qty_toship, 0)) < 0)  return ExtDirect::getDolError($result, $this->errors, $this->error);
					$params->line_id = $result;
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
		global $conf;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->expedition->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($param);
		$package = null;
		$batch_id = null;

		foreach ($paramArray as &$params) {
			// prepare fields
			if (($result = $this->fetch($params->origin_id)) < 0) {
				return ExtDirect::getDolError($result, $this->errors, $this->error);
			}
			$idArray = explode('_', $params->id);
			if (isset($idArray[1]) && $idArray[1] > 0) $batch_id = $idArray[1];
			// Add a protection to refuse deleting if shipment is not in draft status
			if (($this->statut == self::STATUS_DRAFT) && ($params->line_id)) {
				if (ExtDirect::checkDolVersion(0, '9.0', '')) {
					$line = new ExpeditionLigne($this->db);
				} else {
					$line = new ExtDirectExpeditionLine($this->db);
				}
				if (($result = $line->fetch($params->line_id)) < 0) {
					return ExtDirect::getDolError($result, $line->errors, $line->error);
				}
				$line->id = $params->line_id;
				$line->entrepot_id = $params->warehouse_id;
				$line->fk_product = $params->product_id;
				$line->qty = $params->qty_toship;
				if (!empty($params->batch)) {
					$line->detail_batch = new stdClass;
					$line->detail_batch->id = $batch_id;
					$line->detail_batch->batch = $params->batch;
					$line->detail_batch->entrepot_id = $params->warehouse_id;
					$line->detail_batch->dluo_qty = $params->qty_toship; // deprecated for 9.0
					$line->detail_batch->qty = $params->qty_toship;
					$line->detail_batch->fk_origin_stock = $params->batch_id;
				}
				if (($result = $line->update($this->_user)) < 0) return ExtDirect::getDolError($result, $line->errors, $line->error);
			} elseif ($params->qty_package > 0) {
				// add to package
				if (!empty($conf->shipmentpackage->enabled)) {
					dol_include_once('/shipmentpackage/class/shipmentpackage.class.php');
					if (!isset($this->_user->rights->shipmentpackage->shipmentpackage->write)) return PERMISSIONERROR;
					// make package
					if (!isset($package)) {
						$packageid = 0;
						$this->fetch_optionals();
						$this->fetchObjectLinked($this->id, 'shipping', null, 'shipmentpackage');
						$shipmentPackages = $this->linkedObjects['shipmentpackage'];
						// get first draft package for shipment
						if (count($shipmentPackages) > 0) {
							foreach ($shipmentPackages as $shipmentPackage) {
								if ($shipmentPackage->id > 0 && $shipmentPackage->status == ShipmentPackage::STATUS_DRAFT) {
									$package = $shipmentPackage;
								}
							}
						}
						if (!isset($package)) {
							$package = new ShipmentPackage($this->db);
							$package->fk_soc = $this->socid;
							$package->array_options = $this->array_options;
							$package->note_private = $this->getDefaultCreateValueFor('note_private', (!empty($this->note_private) ? $this->note_private : null));
							$package->note_public = $this->getDefaultCreateValueFor('note_public', (!empty($this->note_public) ? $this->note_public : null));
							$packageid = $package->create($this->_user);
							if ($packageid > 0) {
								$object_module = $package->module;
								$package->module = null; //avoid to have add module name to element, because module name is same as element name
								$result = $package->add_object_linked('shipping', $this->id, $this->_user);
								if ($result < 0) {
									return ExtDirect::getDolError($result, $package->errors, $package->error);
								} else {
									$package->module = $object_module;
									// fetch auto created fields
									$package->fetch($packageid);
								}
							} else {
								return ExtDirect::getDolError($packageid, $package->errors, $package->error);
							}
						}
					}
					$packageLine = new ShipmentPackageLine($this->db);
					$packagedQty = $params->qty_package - $packageLine->getQtyPackaged($params->line_id, $batch_id);
					// add line
					if ($packagedQty > 0) {
						$result = $package->addLine($this->_user, $packagedQty, $params->product_id, $params->line_id, $params->batch, $batch_id);
					}
					if ($result < 0) {
						return ExtDirect::getDolError($result, $package->errors, $package->error);
					}
					$params->shipment_package_id = $packageid;
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
		if (ExtDirect::checkDolVersion(0, '15.0', '')) {
			require_once DOL_DOCUMENT_ROOT . '/expedition/class/expeditionlinebatch.class.php';
		} else {
			require_once DOL_DOCUMENT_ROOT . '/expedition/class/expeditionbatch.class.php';
		}

		$stockLocationQty = array(); // associated array with batch qty in stock location
		$stockLocationOriginLineId = array(); // associated array with OriginLineId's
		$shipmentLineId = 0;
		foreach ($batches as $batch) {
			if ($batch->warehouse_id) {
				if (!isset($stockLocationQty[$batch->warehouse_id])) $stockLocationQty[$batch->warehouse_id] = 0;
				$stockLocationQty[$batch->warehouse_id] += $batch->qty_toship;
				$stockLocationOriginLineId[$batch->warehouse_id] = $batch->origin_line_id;
			}
		}
		foreach ($stockLocationQty as $stockLocation => $qty) {
			if (($result = $this->create_line($stockLocation, $stockLocationOriginLineId[$stockLocation], $qty, 0)) < 0) {
				return ExtDirect::getDolError($result, $this->errors, $this->error);
			} else {
				// create shipment batch lines for stockLocation
				$shipmentLineId = $result;
				dol_syslog(get_class($this) . '::' . __FUNCTION__ . " stock location = " . $stockLocation . " qty = " . $qty . " shipmentLineId = " . $shipmentLineId, LOG_DEBUG);
				// store colleted batches
				foreach ($batches as $batch) {
					if ($batch->warehouse_id == $stockLocation) {
						$expeditionLineBatch = new ExpeditionLineBatch($this->db);
						$expeditionLineBatch->sellby = $batch->sellby;
						$expeditionLineBatch->eatby = $batch->eatby;
						$expeditionLineBatch->batch = $batch->batch;
						$expeditionLineBatch->dluo_qty = $batch->qty_toship;
						$expeditionLineBatch->qty = $batch->qty_toship; // deprecated for 9.0
						$expeditionLineBatch->fk_origin_stock = $batch->batch_id;
						$expeditionLineBatch->create($shipmentLineId);
					}
				}
			}
		}
		return $shipmentLineId;
	}

	/**
	 * Ext.direct method to destroy shipment line
	 *
	 * @param unknown_type $param object or object array with shipment record
	 * @return result data or -1
	 */
	public function destroyShipmentLine($param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->expedition->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($param);

		foreach ($paramArray as &$params) {
			// prepare fields
			if (empty($params->line_id)) {
				$lineId = $params->id;
			} else {
				$lineId = $params->line_id;
			}
			if (empty($params->origin_id)) {
				$orderLine = new OrderLine($this->db);
				$orderLine->fetch($lineId);
				$this->id = $orderLine->fk_commande;
			} else {
				$this->id = $params->origin_id;
			}
			if (($result = $this->fetch($this->id)) < 0) {
				return ExtDirect::getDolError($result, $this->errors, $this->error);
			}
			// Add a protection to refuse deleting if shipment is not in draft status
			if ($this->statut == self::STATUS_DRAFT && $lineId) {
				if (ExtDirect::checkDolVersion(0, '7.0', '')) {
					$line = new ExpeditionLigne($this->db);
				} else {
					$line = new ExtDirectExpeditionLine($this->db);
				}
				$line->id = $lineId;
				if (($result = $line->delete($this->_user)) < 0) return ExtDirect::getDolError($result, $line->errors, $line->error);
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
	 * @param array $results array to store batches
	 * @param object $row object with line data to add to results
	 * @param int $lineId expedition line id
	 * @param int $fk_product product id
	 * @param object $myprod product object
	 * @param string $photoFormat small or mini
	 * @param Object $packageLine already packaged line
	 * @return int < 0 if error > 0 if OK
	 */
	private function fetchBatches(&$results, $row, $lineId, $fk_product, $myprod, $photoFormat, $packageLine)
	{
		global $conf;

		$batches = array();

		if (ExtDirect::checkDolVersion(0, '15.0', '')) {
			require_once DOL_DOCUMENT_ROOT . '/expedition/class/expeditionlinebatch.class.php';
			$shipmentLineBatch = new ExpeditionLineBatch($this->db);
			$batches = $shipmentLineBatch->fetchAll($lineId, $fk_product);
		} else {
			require_once DOL_DOCUMENT_ROOT . '/expedition/class/expeditionbatch.class.php';
			$shipmentLineBatch = new ExpeditionLineBatch($this->db);
			$batches = $shipmentLineBatch->fetchAll($this->db, $lineId, $fk_product);
		}
		if ($batches < 0) return $batches;

		if (is_array($batches) && count($batches) > 0) {
			foreach ($batches as $batch) {
				if (isset($packageLine)) $packagedQty = $packageLine->getQtyPackaged($lineId, $batch->id);
				$row->id = $lineId . '_' . $batch->id;
				$row->line_id = $lineId;
				$row->has_batch = 1;
				$row->batch_id = $batch->fk_origin_stock;
				$row->sellby = $batch->sellby;
				$row->eatby = $batch->eatby;
				$row->batch = $batch->batch;
				if (!isset($batch->qty)) {
					$row->qty_shipped = (float) $batch->dluo_qty; // deprecated for 9.0
				} else {
					$row->qty_shipped = (float) $batch->qty;
				}
				$row->qty_toship = $packagedQty;
				array_push($results, clone $row);
			}
		} else {
			// no batch
			if (isset($packageLine)) $packagedQty = $packageLine->getQtyPackaged($lineId);
			$row->qty_toship = $packagedQty;
			array_push($results, clone $row);
			if ($myprod) $myprod->fetchSubProducts($results, $row, $photoFormat);
		}

		return 1;
	}
}
/** ExtDirectExpeditionLine class
 * Class to access shipments lines with CRUD methods
 *
 * deprecated for 9.0
 */
class ExtDirectExpeditionLine extends ExpeditionLigne
{
	/**
	 * Id of warehouse
	 * @var int
	 */
	public $entrepot_id;

	/**
	 * 	Delete shipment line.
	 *
	 *  @param      Object	$user	    Id of line to delete
	 *  @param      int		$notrigger	no run trigger
	 * 	@return	int		>0 if OK, <0 if KO
	 */
	public function delete($user = null, $notrigger = 0)
	{
		global $conf;

		$this->db->begin();

		// delete batch expedition line
		if ($conf->productbatch->enabled) {
			$sql = "DELETE FROM " . MAIN_DB_PREFIX . "expeditiondet_batch";
			$sql .= " WHERE fk_expeditiondet = " . $this->id;

			if (!$this->db->query($sql)) {
				$this->errors[] = $this->db->lasterror() . " - sql=$sql";
				$this->db->rollback();
				return -2;
			}
		}

		$sql = "DELETE FROM " . MAIN_DB_PREFIX . "expeditiondet";
		$sql .= " WHERE rowid = " . $this->id;

		if ($this->db->query($sql)) {
			// Remove extrafields
			if (empty($conf->global->MAIN_EXTRAFIELDS_DISABLED)) {
				// For avoid conflicts if trigger used
				$result = $this->deleteExtraFields();
				if ($result < 0) {
					$this->errors[] = $this->error;
					$this->db->rollback();
					return -4;
				} else {
					$this->db->commit();
					return 1;
				}
			} else {
				$this->db->commit();
				return 1;
			}
		} else {
			$this->errors[] = $this->db->lasterror() . " - sql=$sql";
			$this->db->rollback();
			return -3;
		}
	}

	/**
	 *  Update a line in database
	 *
	 *  @param      Object	$user	    Id of line to delete
	 *  @param      int		$notrigger	no run trigger
	 *
	 *  @return		int					< 0 if KO, > 0 if OK
	 */
	public function update($user = null, $notrigger = 0)
	{
		global $conf;

		dol_syslog(get_class($this) . "::update id=$this->id, entrepot_id=$this->entrepot_id, product_id=$this->fk_product, qty=$this->qty");


		$this->db->begin();

		// Clean parameters
		if (empty($this->qty)) $this->qty = 0;
		$qty = price2num($this->qty);
		$remainingQty = 0;
		$batch = null;
		$batch_id = null;
		$expedition_batch_id = null;

		if (is_array($this->detail_batch)) {
			if (count($this->detail_batch) > 1) {
				dol_syslog(get_class($this) . '::update only possible for one batch', LOG_ERR);
				$this->errors[] = 'ErrorBadParameters';
				return -7;
			} else {
				$batch = $this->detail_batch[0]->batch;
				$batch_id = $this->detail_batch[0]->fk_origin_stock;
				$expedition_batch_id = $this->detail_batch[0]->id;
				if ($this->entrepot_id != $this->detail_batch[0]->entrepot_id) {
					dol_syslog(get_class($this) . '::update only possible for batch of same warehouse', LOG_ERR);
					$this->errors[] = 'ErrorBadParameters';
					return -10;
				}
				$qty = price2num($this->detail_batch[0]->dluo_qty);
			}
		} else {
			$batch = $this->detail_batch->batch;
			$batch_id = $this->detail_batch->fk_origin_stock;
			$expedition_batch_id = $this->detail_batch->id;
			if ($this->entrepot_id != $this->detail_batch->entrepot_id) {
				dol_syslog(get_class($this) . '::update only possible for batch of same warehouse', LOG_ERR);
				$this->errors[] = 'ErrorBadParameters';
				return -9;
			}
			$qty = price2num($this->detail_batch->dluo_qty);
		}
		if (!isset($this->id) || !isset($this->entrepot_id)) {
			dol_syslog(get_class($this) . '::update missing line id and/or warehouse id', LOG_ERR);
			$this->errors[] = 'ErrorBadParameters';
			return -1;
		}

		// update lot

		if (!empty($batch) && $conf->productbatch->enabled) {
			if (empty($batch_id) || empty($this->fk_product)) {
				dol_syslog(get_class($this) . '::update missing fk_origin_stock (batch_id) and/or fk_product', LOG_ERR);
				$this->errors[] = 'ErrorBadParameters';
				return -8;
			}

			// fetch remaining lot qty
			if (ExtDirect::checkDolVersion(0, '15.0', '')) {
				require_once DOL_DOCUMENT_ROOT . '/expedition/class/expeditionlinebatch.class.php';
				$expeditionLineBatch = new ExpeditionLineBatch($this->db);
				$lotArray = $expeditionLineBatch->fetchAll($this->id);
			} else {
				require_once DOL_DOCUMENT_ROOT . '/expedition/class/expeditionbatch.class.php';
				$expeditionLineBatch = new ExpeditionLineBatch($this->db);
				$lotArray = $expeditionLineBatch->fetchAll($this->db, $this->id);
			}
			if ($lotArray < 0) {
				$this->errors[] = $this->db->lasterror() . " - ExpeditionLineBatch::fetchAll";
				$this->db->rollback();
				return -4;
			}
			foreach ($lotArray as $lot) {
				if ($batch != $lot->batch) {
					$remainingQty += $lot->dluo_qty;
				}
			}
			$qty += $remainingQty;
			//fetch lot details

			// fetch from product_lot
			require_once DOL_DOCUMENT_ROOT . '/product/stock/class/productlot.class.php';
			$lot = new Productlot($this->db);
			if ($lot->fetch(0, $this->fk_product, $batch) < 0) {
				$this->errors[] = $lot->errors;
				return -3;
			}
			if (!empty($expedition_batch_id)) {
				// delete lot expedition line
				$sql = "DELETE FROM " . MAIN_DB_PREFIX . "expeditiondet_batch";
				$sql .= " WHERE fk_expeditiondet = " . $this->id;
				$sql .= " AND rowid = " . $expedition_batch_id;
				if (!$this->db->query($sql)) {
					$this->errors[] = $this->db->lasterror() . " - sql=$sql";
					$this->db->rollback();
					return -2;
				}
			}
			if ($this->detail_batch->dluo_qty > 0) {
				if (isset($lot->id)) {
					$shipmentLot = new ExpeditionLineBatch($this->db);
					$shipmentLot->batch = $lot->batch;
					$shipmentLot->eatby = $lot->eatby;
					$shipmentLot->sellby = $lot->sellby;
					$shipmentLot->entrepot_id = $this->entrepot_id;
					$shipmentLot->dluo_qty = $this->detail_batch->dluo_qty;
					$shipmentLot->fk_origin_stock = $batch_id;
					if ($shipmentLot->create($this->id) < 0) {
						$this->errors[] = $shipmentLot->errors;
						$this->db->rollback();
						return -6;
					}
				}
			}
		}

		// update line
		$sql = "UPDATE " . MAIN_DB_PREFIX . $this->table_element . " SET";
		$sql .= " fk_entrepot = " . $this->entrepot_id;
		$sql .= " , qty = " . $qty;
		$sql .= " WHERE rowid = " . $this->id;

		if (!$this->db->query($sql)) {
			$this->errors[] = $this->db->lasterror() . " - sql=$sql";
			$this->db->rollback();
			return -5;
		}

		if (empty($conf->global->MAIN_EXTRAFIELDS_DISABLED)) {
			// For avoid conflicts if trigger used
			$result = $this->insertExtraFields();
			if ($result < 0) {
				$this->errors[] = $this->error;
				$this->db->rollback();
				return -4;
			} else {
				$this->db->commit();
				return 1;
			}
		} else {
			$this->db->commit();
			return 1;
		}
	}
}
