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
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
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
		'STOCK_SHOW_VIRTUAL_STOCK_IN_PRODUCTS_COMBO',
		'STOCK_ALLOW_NEGATIVE_TRANSFER',
		'STOCK_ALLOW_ADD_LIMIT_STOCK_BY_WAREHOUSE',
		'MAIN_MODULE_RECEPTION');
	private $_enabled = false;

	/**
	 * @var int Date when delivery received
	 *
	 * not used for the moment
	 */
	public $date_delivered;

	/** @var string $table_element_reception_line table of order reception line */
	public $table_element_reception_line = 'receptiondet_batch';

	/** @var string $key_ship_line_order key of linked order to ship/receive line */
	public $key_ship_line_order = 'fk_element';

	/** @var string $key_ship_line_order_line key of linked order line to ship/receive line */
	public $key_ship_line_order_line = 'fk_elementdet';

	/**
	 * end status to allow status itteration
	 */
	const STATUS_END = 10;

	/**
	 * Constructor
	 *
	 * @param string $login user name
	 */
	public function __construct($login)
	{
		global $langs, $db, $user, $conf, $mysoc;

		if (!empty($login)) {
			if ((is_object($login) && get_class($db) == get_class($login)) || $user->id > 0 || $user->fetch('', $login, '', 1) > 0) {
				$user->getrights();
				$this->_enabled = !empty($conf->fournisseur->enabled) && isset($user->rights->fournisseur->commande->lire);
				$this->_user = $user;  //commande.class uses global user
				if (ExtDirect::checkDolVersion(0, '', '19.0')) {
					$this->table_element_reception_line = 'commande_fournisseur_dispatch';
					$this->key_ship_line_order = 'fk_commande';
					$this->key_ship_line_order_line = 'fk_commandefourndet';
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
				$langs->load("errors");
				$langs->load("orders");
				parent::__construct($db);
			}
		}
	}

	/**
	 * Load order related constants
	 *
	 * @param   stdClass    $params filter with elements
	 *                              constant    name of specific constant
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
	 *                                      id  Id of order to load
	 *                                      ref ref
	 *
	 *    @return     stdClass result data or error number
	 */
	public function readOrder(stdClass $params)
	{
		global $conf, $mysoc;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->fournisseur->commande->lire)) return PERMISSIONERROR;
		$myUser = new User($this->db);
		$thirdparty = new Societe($this->db);
		$results = array();
		$row = new stdClass;
		$id = 0;
		$ref = '';
		$orderstatus_ids = array();

		if (isset($params->filter)) {
			foreach ($params->filter as $key => $filter) {
				if ($filter->property == 'id') $id=$filter->value;
				elseif ($filter->property == 'ref') $ref=$filter->value;
				elseif ($filter->property == 'orderstatus_id') array_push($orderstatus_ids, $filter->value);
			}
		}

		if (($id > 0) || ($ref != '')) {
			if (($result = $this->fetch($id, $ref)) < 0)   return $result;
			if (!$this->error) {
				$row->id = $this->id ;
				//! Ref
				$row->ref= $this->ref;
				$row->ref_supplier= $this->ref_supplier;
				$row->supplier_id = $this->socid;
				if ($thirdparty->fetch($this->socid)>0) {
					$row->supplier_name = $thirdparty->name;
				}
				//! -1 for cancelled, 0 for draft, 1 for validated, 2 for send, 3 for closed
				$row->orderstatus_id = $this->statut;
				$row->orderstatus = html_entity_decode($this->getLibStatut(1));
				$row->note_private = $this->note_private;
				$row->note_public = $this->note_public;
				$row->user_id = $this->user_author_id;
				if ($myUser->fetch($this->user_author_id)>0) {
					$row->user_name = $myUser->firstname . ' ' . $myUser->lastname;
				}
				$row->user_approve_id = $this->user_approve_id;
				$row->create_date = $this->date;
				$row->valid_date = $this->date_valid;
				$row->approve_date = $this->date_approve;
				$row->order_date = $this->date_commande;
				$row->deliver_date = $this->delivery_date;
				$row->order_method_id = $this->methode_commande_id;
				$row->order_method = $this->methode_commande;
				$row->reduction_percent = $this->remise_percent;
				$row->reduction = 0;
				foreach ($this->lines as $line) {
					if ($line->remise_percent > 0) {
						$localtaxes_array = getLocalTaxesFromRate($line->tva_tx, 0, $thirdparty, $mysoc);
						$tabprice = calcul_price_total($line->qty, $line->subprice, 0, $line->tva_tx, $line->total_localtax1, $line->total_localtax2, 0, 'HT', $line->info_bits, $line->product_type, $mysoc, $localtaxes_array);
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
		if (!isset($this->_user->rights->fournisseur->commande->lire)) return PERMISSIONERROR;
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
		if (!isset($this->_user->rights->fournisseur->commande->creer)) return PERMISSIONERROR;
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
		if (!isset($this->_user->rights->fournisseur->commande->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);

		foreach ($paramArray as &$param) {
			if (($this->id != $param->object_id && $result = $this->fetch($param->object_id)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
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
						elseif ($currentStatus == 2) $newstatus=0;	// Approved->Draft
						elseif ($currentStatus == 5) $newstatus=4;	// Received->Received partially
						elseif ($currentStatus == 6) $newstatus=2;	// Canceled->Approved
						elseif ($currentStatus == 7) $newstatus=3;	// Canceled->Process running
						elseif ($currentStatus == 9) $newstatus=1;	// Refused->Validated

						$result = $this->setStatus($this->_user, $newstatus);
						break;
					case 1:
						$result = $this->valid($this->_user);
						break;
					case 2:
						$result = $this->approve($this->_user, $params->warehouse_id);
						break;
					case 3:
						$result = $this->commande($this->_user, $this->date_commande, $this->methode_commande_id, $params->comment);
						break;
					case 4:
						$result = $this->Livraison($this->_user, $this->date_delivered, 'par', $params->comment);
						break;
					case 5:
						$result = $this->Livraison($this->_user, $this->date_delivered, 'tot', $params->comment);
						break;
					case 6:
						$result = $this->Cancel($this->_user);
						break;
					case 7:
						$result = $this->Livraison($this->_user, $this->date_delivered, 'nev', $params->comment);
						break;
					case 9:
						$result = $this->refuse($this->_user);
						break;
					default:
						break;
				}
				if ($result < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				if (function_exists('setDeliveryDate')) {
					if (($result = $this->setDeliveryDate($this->_user, $this->delivery_date)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				}
				if (isset($this->cond_reglement_id) &&
					($result = $this->setPaymentTerms($this->cond_reglement_id)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				if (isset($this->mode_reglement_id) &&
					($result = $this->setPaymentMethods($this->mode_reglement_id)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				if (isset($this->remise_percent) &&
					($result = $this->set_remise($this->_user, $this->remise_percent)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				if (isset($this->ref_supplier) &&
					($result = $this->setValueFrom('ref_supplier', $this->ref_supplier, '', null, 'text', '', $this->_user, 'ORDER_SUPPLIER_MODIFY')) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
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
	 * Ext.direct method to upload file for supplier order object
	 *
	 * @param unknown_type $params object or object array with uploaded file(s)
	 * @return Array    ExtDirect response message
	 */
	public function fileUpload($params)
	{
		global $conf;
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->fournisseur->commande->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);
		$dir = null;

		foreach ($paramArray as &$param) {
			if (isset($param['extTID'])) {
				$id = $param['extTID'];
				if ($this->fetch($id)) {
					$this->fetch_thirdparty();
					$dir = $conf->fournisseur->commande->dir_output.'/'.dol_sanitizeFileName($this->ref);
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
	 * private method to copy order fields into dolibarr object
	 *
	 * @param stdclass $params object with fields
	 * @return null
	 */
	private function prepareOrderFields($params)
	{
		isset($params->ref) ? ( $this->ref = $params->ref ) : ( isset($this->ref) ? null : ( $this->ref = null));
		isset($params->ref_supplier) ? ( $this->ref_supplier = $params->ref_supplier) : ( isset($this->ref_supplier) ? null : ( $this->ref_supplier = null));
		isset($params->supplier_id) ? ( $this->socid = $params->supplier_id) : ( isset($this->socid) ? null : ( $this->socid = null));
		isset($params->orderstatus_id) ? ( $this->statut = $params->orderstatus_id) : ( isset($this->statut) ? null : ($this->statut  = 0));
		isset($params->note_private) ? ( $this->note_private =$params->note_private) : ( isset($this->note_private) ? null : ( $this->note_private= null));
		isset($params->note_public) ? ( $this->note_public = $params->note_public ) : ( isset($this->note_public) ? null : ($this->note_public = null));
		isset($params->user_id) ? ( $this->user_author_id = $params->user_id) : ( isset($this->user_author_id) ? null : ($this->user_author_id = null));
		isset($params->order_date) ? ( $this->date_commande =$params->order_date) : ( isset($this->date_commande) ? null : ($this->date_commande = null));
		isset($params->date_delivered) ? ( $this->date_delivered =$params->date_delivered) : ( isset($this->date_delivered) ? null : ($this->date_delivered = null));
		isset($params->deliver_date) ? ( $this->delivery_date = $params->deliver_date) : ( isset($this->delivery_date) ? null : ($this->delivery_date = null));
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
		global $langs;

		if (!isset($this->db)) return CONNECTERROR;
		if (!$this->_enabled) return NOTENABLEDERROR;
		if (!isset($this->_user->rights->fournisseur->commande->lire)) return PERMISSIONERROR;
		$result = new stdClass;
		$data = array();

		$statusFilterCount = 0;
		$ref = null;
		$contactTypeId = 0;
		$barcode = null;
		$productId = null;
		$supplierId = null;
		$contentFilter = null;

		$includeTotal = true;

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
				elseif ($filter->property == 'product_id') $productId = $filter->value;
				elseif ($filter->property == 'supplier_id') $supplierId = $filter->value;
				elseif ($filter->property == 'content') $contentFilter = $filter->value;
			}
		}

		$sqlFields = "SELECT s.nom, s.rowid AS socid, c.rowid, c.ref, c.ref_supplier, c.fk_statut, ea.status, cim.libelle as mode_label, cim.code as mode_code, c.fk_user_author, c.total_ttc, c.date_commande, c.date_livraison as delivery_date, u.firstname, u.lastname";
		$sqlFrom = " FROM ".MAIN_DB_PREFIX."commande_fournisseur as c";
		$sqlFrom .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON c.fk_soc = s.rowid";
		$sqlFrom .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON c.fk_user_author = u.rowid";
		if ($barcode || $productId || $contentFilter) {
			$sqlFrom .= " LEFT JOIN ".MAIN_DB_PREFIX."commande_fournisseurdet as cd ON c.rowid = cd.fk_commande";
		}
		if ($barcode || $contentFilter) {
			$sqlFrom .= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = cd.fk_product";
			if (ExtDirect::checkDolVersion(0, '13.0', '')) $sqlFrom .= " LEFT JOIN ".MAIN_DB_PREFIX."product_fournisseur_price as pfp ON pfp.fk_product = cd.fk_product";
		}
		if ($contactTypeId > 0) $sqlFrom .= " LEFT JOIN ".MAIN_DB_PREFIX."element_contact as ec ON c.rowid = ec.element_id";
		$sqlFrom .= " LEFT JOIN ".MAIN_DB_PREFIX."c_input_method as cim ON c.fk_input_method = cim.rowid";
		$sqlFrom .= " LEFT JOIN ("; // get latest extdirect activity status for commande to check if locked
		$sqlFrom .= "   SELECT ma.activity_id, ma.maxrow AS rowid, ea.status";
		$sqlFrom .= "   FROM (";
		$sqlFrom .= "    SELECT MAX( rowid ) AS maxrow, activity_id";
		$sqlFrom .= "    FROM ".MAIN_DB_PREFIX."extdirect_activity";
		$sqlFrom .= "    GROUP BY activity_id";
		$sqlFrom .= "   ) AS ma, ".MAIN_DB_PREFIX."extdirect_activity AS ea";
		$sqlFrom .= "   WHERE ma.maxrow = ea.rowid";
		$sqlFrom .= " ) AS ea ON c.rowid = ea.activity_id";
		$sqlWhere = " WHERE c.entity IN (".getEntity('order_supplier', 1).')';
		$sqlWhere .= " AND c.fk_soc = s.rowid";

		if ($statusFilterCount>0) {
			$sqlWhere .= " AND ( ";
			foreach ($orderstatus_id as $key => $fk_statut) {
				$sqlWhere  .= "c.fk_statut = ".$fk_statut;
				if ($key < ($statusFilterCount-1)) $sqlWhere  .= " OR ";
			}
			$sqlWhere .= ")";
		}
		if ($ref) {
			$sqlWhere .= " AND c.ref = '".$this->db->escape($ref)."'";
		}
		if ($contactTypeId > 0) {
			$sqlWhere .= " AND ec.fk_c_type_contact = ".$contactTypeId;
			$sqlWhere .= " AND ec.fk_socpeople = ".$contactId;
		}
		if ($barcode) {
			$sqlWhere .= " AND (p.barcode LIKE '%".$this->db->escape($barcode)."%' OR c.ref = '".$this->db->escape($barcode)."' OR c.ref_supplier = '".$this->db->escape($barcode)."'";
			if (ExtDirect::checkDolVersion(0, '13.0', '')) $sqlWhere .= " OR pfp.barcode LIKE '%".$this->db->escape($barcode)."%'";
			if ($supplierId) {
				if (ExtDirect::checkDolVersion(0, '13.0', '')) $sqlWhere .= " AND pfp.fk_soc = ".$supplierId;
			}
			$sqlWhere .= ")";
		}
		if ($productId) {
			$sqlWhere .= " AND cd.fk_product = ".$productId;
		}
		if ($supplierId) {
			$sqlWhere .= " AND c.fk_soc = ".$supplierId;
		}

		if ($contentFilter) {
			$fields = array('c.ref', 'c.ref_supplier', 's.nom', 'u.firstname', 'u.lastname', 'p.ref');
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
					} elseif ($sort->property == 'deliver_date') {
						$sortfield = 'c.date_livraison';
					} elseif ($sort->property == 'ref') {
						$sortfield = 'c.ref';
					} elseif ($sort->property == 'supplier') {
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
				$row->supplier      = $obj->nom;
				$row->supplier_id   = (int) $obj->socid;
				$row->ref           = $obj->ref;
				$row->ref_supplier  = $obj->ref_supplier;
				$row->orderstatus_id= (int) $obj->fk_statut;
				$row->orderstatus   = html_entity_decode($this->LibStatut($obj->fk_statut, 1));
				$row->status        = $obj->status;
				if ($obj->mode_code && $langs->transnoentitiesnoconv($obj->mode_code)) {
					$row->mode      = $langs->transnoentitiesnoconv($obj->mode_code);
				} else {
					$row->mode      = $obj->mode_label;
				}
				$row->user_id       = $obj->fk_user_author;
				$row->user_name     = $obj->firstname . ' ' . $obj->lastname;
				$row->total_inc     = $obj->total_ttc;
				$row->order_date    = $this->db->jdate($obj->date_commande);
				$row->deliver_date  = $this->db->jdate($obj->delivery_date);
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

		$this->LibStatut(0); // set $this->labelStatus
		for ($statut = 0; $statut < self::STATUS_END; $statut++) {
			if (!isset($this->labelStatus) || array_key_exists($statut, $this->labelStatus)) {
				$result = $this->LibStatut($statut, 1);
				$row = new stdClass;
				$row->id = $statut;
				$row->status = html_entity_decode($result);
				array_push($results, $row);
			}
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
			$row = new stdClass;
			$row->id = $id;
			$row->label = html_entity_decode($label);
			array_push($results, $row);
		}
		return $results;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	// TODO push to dolibar core, into common_object with ifelse on trigger name
	/**
	 * 	Applique une remise relative
	 *
	 * 	@param     	User		$user		User qui positionne la remise
	 * 	@param     	float		$remise		Discount (percent)
	 * 	@param     	int			$notrigger	1=Does not execute triggers, 0= execute triggers
	 *	@return		int 					<0 if KO, >0 if OK
		*/
	private function set_remise($user, $remise, $notrigger = 0)
	{
		// phpcs:enable
		$remise=trim($remise)?trim($remise):0;

		if ($user->rights->commande->creer) {
			$error=0;

			$this->db->begin();

			$remise=price2num($remise);

			$sql = 'UPDATE '.MAIN_DB_PREFIX.$this->table_element;
			$sql.= ' SET remise_percent = '.$remise;
			$sql.= ' WHERE rowid = '.$this->id.' AND fk_statut = '.self::STATUS_DRAFT.' ;';

			dol_syslog(__METHOD__, LOG_DEBUG);
			$resql=$this->db->query($sql);
			if (!$resql) {
				$this->errors[]=$this->db->error();
				$error++;
			}

			if (! $error) {
				$this->oldcopy= clone $this;
				$this->remise_percent = $remise;
				$this->update_price(1);
			}

			if (! $notrigger && empty($error)) {
				// Call trigger
				$result=$this->call_trigger('ORDER_SUPPLIER_MODIFY', $user);
				if ($result < 0) $error++;
				// End call triggers
			}

			if (! $error) {
				$this->db->commit();
				return 1;
			} else {
				foreach ($this->errors as $errmsg) {
					dol_syslog(__METHOD__.' Error: '.$errmsg, LOG_ERR);
					$this->error.=($this->error?', '.$errmsg:$errmsg);
				}
				$this->db->rollback();
				return -1*$error;
			}
		}
	}

	/**
	 *    Load orderlines from database into memory
	 *
	 *    @param    stdClass    $params     filter with elements:
	 *                                      Id of order to load lines from
	 *                                      warehouse_id
	 *                                      warehouse_id x to get stock of
	 *                                      warehouse_id -1 will get total stock
	 *                                      no warehouse_id will split lines in stock by warehouse
	 *                                      photo_size string with foto size 'mini' or 'small'
	 *    @return     stdClass result data or error number
	 */
	public function readOrderLine(stdClass $params)
	{
		global $conf;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->fournisseur->commande->lire)) return PERMISSIONERROR;
		dol_include_once('/extdirect/class/ExtDirectProduct.class.php');

		$results = array();
		$res = 0;
		$order_id = 0;
		$photoSize = '';
		$includePhoto = false;
		$batch = '';
		$supplierProduct = null;

		if (isset($params->filter)) {
			foreach ($params->filter as $key => $filter) {
				if ($filter->property == 'id') $id=$filter->value;
				if ($filter->property == 'order_id') $order_id=$filter->value;
				if ($filter->property == 'warehouse_id') $warehouse_id=$filter->value;
				if ($filter->property == 'photo_size' && !empty($filter->value)) $photoSize = $filter->value;
				if ($filter->property == 'batch_id') $batchId=$filter->value;
				if ($filter->property == 'batch') $batch=$filter->value;
			}
		}

		if ($order_id > 0) {
			$this->id=$order_id;
			if (($result = $this->fetch($this->id)) < 0)  return $result;
			if (!$this->error) {
				foreach ($this->lines as $line) {
					if (!isset($id) || ($id == $line->id)) {
						if ($line->fk_product) {
							$isFreeLine = false;
							$myprod = new ExtDirectProduct($this->_user->login);
							$result = $myprod->fetch($line->fk_product);
							if ($result < 0) return ExtDirect::getDolError($result, $myprod->errors, $myprod->error);
							if (!empty($conf->global->STOCK_SHOW_VIRTUAL_STOCK_IN_PRODUCTS_COMBO)) {
								$result = $myprod->load_stock('warehouseopen');
								if ($result < 0) return ExtDirect::getDolError($result, $myprod->errors, $myprod->error);
							} else {
								$result = $myprod->load_stock('novirtual, warehouseopen');
								if ($result < 0) return ExtDirect::getDolError($result, $myprod->errors, $myprod->error);
							}
							// supplier product for supplier barcode
							$supplierProduct = new ProductFournisseur($this->db);
							$supplierProducts = $supplierProduct->list_product_fournisseur_price($line->fk_product);
							foreach ($supplierProducts as $prodsupplier) {
								if ($prodsupplier->ref_supplier == $line->ref_supplier) {
									$supplierProduct = $prodsupplier;
								}
							}
							$myprod->fetch_barcode();
						} else {
							$isFreeLine = true;
						}
						if ($line->product_type == 1) {
							$isService = true;
						} else {
							$isService = false;
						}
						if ($isService || $isFreeLine || !empty($warehouse_id) || ($myprod->stock_reel == 0)) {
							if (($warehouse_id == -1 || $isService || $isFreeLine )) {
								// get orderline with complete stock
								$row = new stdClass;
								$row->id = $line->id;
								$row->is_virtual_stock = false;
								$row->origin_id = $this->id;
								$row->origin_line_id = $line->id;
								$row->label = $line->product_label;
								$row->description = $line->description;
								$row->product_id = $line->fk_product;
								$row->ref = $line->product_ref;
								$row->product_label = $line->product_label;
								$row->product_desc = $line->product_desc;
								$row->product_type = $line->product_type;
								if (isset($supplierProduct) && !empty($supplierProduct->supplier_barcode)) {
									$row->barcode = $supplierProduct->supplier_barcode;
									$row->barcode_type = $supplierProduct->supplier_fk_barcode_type?$supplierProduct->supplier_fk_barcode_type:0;
									$row->barcode_with_checksum = $myprod->fetchBarcodeWithChecksum($supplierProduct);
								} else {
									$row->barcode = $myprod->barcode?$myprod->barcode:'';
									$row->barcode_type = $myprod->barcode_type?$myprod->barcode_type:0;
									$row->barcode_with_checksum = $myprod->barcode?$myprod->fetchBarcodeWithChecksum($myprod):'';
								}
								$row->qty_asked = $line->qty;
								if (isset($line->rang)) {
									$row->rang = $line->rang;
								} else {
									$row->rang = $line->id;
								}
								if (!empty($this->_user->rights->fournisseur->lire)) {
									$row->tax_tx = $line->tva_tx;
									$row->localtax1_tx = $line->localtax1_tx;
									$row->localtax2_tx = $line->localtax2_tx;
									$row->total_net = $line->total_ht;
									$row->total_inc = $line->total_ttc;
									$row->total_tax = $line->total_tva;
									$row->total_localtax1 = $line->total_localtax1;
									$row->total_localtax2 = $line->total_localtax2;
									$row->subprice = $line->pu_ht;
									$row->cost_price = $line->subprice;
									if (!empty($line->remise_percent) && empty($conf->global->STOCK_EXCLUDE_DISCOUNT_FOR_PMP)) {
										$row->cost_price = price2num($line->subprice * (100 - $line->remise_percent) / 100, 'MU');
									}
									$row->price = $row->cost_price; // deprecated
									$row->reduction_percent = $line->remise_percent;
								}
								$row->ref_supplier = $line->ref_supplier;
								if (!empty($line->fk_fournprice)) {
									$row->ref_supplier_id = $line->fk_fournprice;
								} elseif (isset($supplierProduct) && !empty($supplierProduct->product_fourn_price_id)) {
									$row->ref_supplier_id = $supplierProduct->product_fourn_price_id;
								}
								$row->date_start = $line->date_start;
								$row->date_end = $line->date_end;
								// qty shipped for product line
								$row->qty_shipped = $this->getDispatched($line->id, $line->fk_product);
								$row->qty_toreceive = $row->qty_asked - $row->qty_shipped;
								if (!empty($conf->global->STOCK_SHOW_VIRTUAL_STOCK_IN_PRODUCTS_COMBO)) {
									$row->is_virtual_stock = true;
									$row->stock = $myprod->stock_theorique;
								} else {
									$row->stock = $myprod->stock_reel;
								}
								$row->total_stock = $row->stock;
								$row->desiredstock = $myprod->desiredstock;
								if ($isService) {
									$row->warehouse_id = -1; // service is not stocked
								} elseif ($isFreeLine) {
									$row->warehouse_id = 0; // freeline is not in a specific stock location
								} else {
									$row->warehouse_id = $warehouse_id;
								}
								$row->default_warehouse_id = $myprod->fk_default_warehouse;
								if (! empty($conf->productbatch->enabled)) $row->has_batch = $myprod->status_batch;
								$row->has_photo = 0;
								if (!$isFreeLine && !empty($photoSize)) {
									$myprod->fetchPhoto($row, $photoSize);
								}
								$row->unit_id = $line->fk_unit;
								array_push($results, $row);
							} else {
								// get orderline with stock of warehouse
								$row = new stdClass;
								$row->id = $line->id;
								$row->is_virtual_stock = false;
								$row->origin_id = $this->id;
								$row->origin_line_id = $line->id;
								$row->label = $line->product_label;
								$row->description = $line->description;
								$row->product_id = $line->fk_product;
								$row->ref = $line->product_ref;
								$row->product_label = $line->product_label;
								$row->product_desc = $line->product_desc;
								$row->product_type = $line->product_type;
								if (isset($supplierProduct) && !empty($supplierProduct->supplier_barcode)) {
									$row->barcode = $supplierProduct->supplier_barcode;
									$row->barcode_type = $supplierProduct->supplier_fk_barcode_type?$supplierProduct->supplier_fk_barcode_type:0;
									$row->barcode_with_checksum = $myprod->fetchBarcodeWithChecksum($supplierProduct);
								} else {
									$row->barcode = $myprod->barcode?$myprod->barcode:'';
									$row->barcode_type = $myprod->barcode_type?$myprod->barcode_type:0;
									$row->barcode_with_checksum = $myprod->barcode?$myprod->fetchBarcodeWithChecksum($myprod):'';
								}
								$row->qty_asked = $line->qty;
								if (isset($line->rang)) {
									$row->rang = $line->rang;
								} else {
									$row->rang = $line->id;
								}
								if (!empty($this->_user->rights->fournisseur->lire)) {
									$row->tax_tx = $line->tva_tx;
									$row->localtax1_tx = $line->localtax1_tx;
									$row->localtax2_tx = $line->localtax2_tx;
									$row->total_net = $line->total_ht;
									$row->total_inc = $line->total_ttc;
									$row->total_tax = $line->total_tva;
									$row->total_localtax1 = $line->total_localtax1;
									$row->total_localtax2 = $line->total_localtax2;
									$row->subprice = $line->pu_ht;
									$row->cost_price = $line->subprice;
									if (!empty($line->remise_percent) && empty($conf->global->STOCK_EXCLUDE_DISCOUNT_FOR_PMP)) {
										$row->cost_price = price2num($line->subprice * (100 - $line->remise_percent) / 100, 'MU');
									}
									$row->price = $row->cost_price; // deprecated
									$row->reduction_percent = $line->remise_percent;
								}
								$row->ref_supplier = $line->ref_supplier;
								if (!empty($line->fk_fournprice)) {
									$row->ref_supplier_id = $line->fk_fournprice;
								} elseif (isset($supplierProduct) && !empty($supplierProduct->product_fourn_price_id)) {
									$row->ref_supplier_id = $supplierProduct->product_fourn_price_id;
								}
								$row->date_start = $line->date_start;
								$row->date_end = $line->date_end;
								// qty shipped for product line
								$row->qty_shipped = $this->getDispatched($line->id, $line->fk_product);
								$row->qty_toreceive = $row->qty_asked - $row->qty_shipped;
								if (!empty($conf->global->STOCK_SHOW_VIRTUAL_STOCK_IN_PRODUCTS_COMBO)) {
									if ($warehouse_id) {
										$row->stock = (float) $myprod->stock_warehouse[$warehouse_id]->real;
									} else {
										$row->is_virtual_stock = true;
										$row->stock = $myprod->stock_theorique;
									}
									$row->total_stock = $myprod->stock_theorique;
								} else {
									$warehouse_id ? $row->stock = (float) $myprod->stock_warehouse[$warehouse_id]->real : $row->stock = $myprod->stock_reel;
									$row->total_stock = $myprod->stock_reel;
								}
								$row->desiredstock = $myprod->desiredstock;
								$row->warehouse_id = $warehouse_id;
								$row->default_warehouse_id = $myprod->fk_default_warehouse;
								if (! empty($conf->productbatch->enabled)) $row->has_batch = $myprod->status_batch;
								$row->has_photo = 0;
								if (!empty($photoSize)) {
									$myprod->fetchPhoto($row, $photoSize);
								}
								$row->unit_id = $line->fk_unit;
								if (empty($batchId)) {
									if (empty($batch)) {
										if ($myprod->status_batch == 2) {
											// if unique batch type always receive one by one.
											$row->qty_toreceive = 1;
										}
										array_push($results, $row);
									} else {
										if (($res = $myprod->fetchBatches($results, $row, $line->id, $warehouse_id, $myprod->stock_warehouse[$warehouse_id]->id, false, $batchId, $batch)) < 0) return $res;
									}
								} else {
									if (($res = $myprod->fetchBatches($results, $row, $line->id, $warehouse_id, $myprod->stock_warehouse[$warehouse_id]->id, false, $batchId)) < 0) return $res;
								}
							}
						} else {
							// read list of oderlines split by warehouse stock (to show stock available in all warehouse)
							$warehouseObject = new Entrepot($this->db);
							$warehouseList = $warehouseObject->list_array();
							foreach ($warehouseList as $warehouse=>$warehouseLabel) {
								if (!empty($myprod->stock_warehouse[$warehouse]->real)) {
									$row = new stdClass;
									$row->id = $line->id.'_'.$warehouse;
									$row->is_virtual_stock = false;
									$row->origin_id = $this->id;
									$row->origin_line_id = $line->id;
									$row->label = $line->product_label;
									$row->description = $line->description;
									$row->product_id = $line->fk_product;
									$row->ref = $line->product_ref;
									$row->product_label = $line->product_label;
									$row->product_desc = $line->product_desc;
									$row->product_type = $line->product_type;
									if (isset($supplierProduct) && !empty($supplierProduct->supplier_barcode)) {
										$row->barcode = $supplierProduct->supplier_barcode;
										$row->barcode_type = $supplierProduct->supplier_fk_barcode_type?$supplierProduct->supplier_fk_barcode_type:0;
										$row->barcode_with_checksum = $myprod->fetchBarcodeWithChecksum($supplierProduct);
									} else {
										$row->barcode = $myprod->barcode?$myprod->barcode:'';
										$row->barcode_type = $myprod->barcode_type?$myprod->barcode_type:0;
										$row->barcode_with_checksum = $myprod->barcode?$myprod->fetchBarcodeWithChecksum($myprod):'';
									}
									$row->qty_asked = $line->qty;
									if (!empty($this->_user->rights->fournisseur->lire)) {
										$row->tax_tx = $line->tva_tx;
										$row->localtax1_tx = $line->localtax1_tx;
										$row->localtax2_tx = $line->localtax2_tx;
										$row->total_net = $line->total_ht;
										$row->total_inc = $line->total_ttc;
										$row->total_tax = $line->total_tva;
										$row->total_localtax1 = $line->total_localtax1;
										$row->total_localtax2 = $line->total_localtax2;
										$row->subprice = $line->pu_ht;
										$row->cost_price = $line->subprice;
										if (!empty($line->remise_percent) && empty($conf->global->STOCK_EXCLUDE_DISCOUNT_FOR_PMP)) {
											$row->cost_price = price2num($line->subprice * (100 - $line->remise_percent) / 100, 'MU');
										}
										$row->price = $row->cost_price; // deprecated
										$row->reduction_percent = $line->remise_percent;
									}
									if (isset($line->rang)) {
										$row->rang = $line->rang;
									} else {
										$row->rang = $line->id;
									}
									$row->ref_supplier = $line->ref_supplier;
									if (!empty($line->fk_fournprice)) {
										$row->ref_supplier_id = $line->fk_fournprice;
									} elseif (isset($supplierProduct) && !empty($supplierProduct->product_fourn_price_id)) {
										$row->ref_supplier_id = $supplierProduct->product_fourn_price_id;
									}
									$row->date_start = $line->date_start;
									$row->date_end = $line->date_end;
									// qty shipped for each product line limited to qty asked, if > qty_asked and more lines of same product move to next orderline of same product
									$row->qty_shipped = $this->getDispatched($line->id, $line->fk_product, $warehouse);
									$row->qty_toreceive = $row->qty_asked - $row->qty_shipped;
									$row->stock = (float) $myprod->stock_warehouse[$warehouse]->real;
									if (!empty($conf->global->STOCK_SHOW_VIRTUAL_STOCK_IN_PRODUCTS_COMBO)) {
										$row->is_virtual_stock = true;
										$row->total_stock = $myprod->stock_theorique;
									} else {
										$row->total_stock = $myprod->stock_reel;
									}
									$row->desiredstock = $myprod->desiredstock;
									$row->warehouse_id = $warehouse;
									$row->default_warehouse_id = $myprod->fk_default_warehouse;
									if (! empty($conf->productbatch->enabled)) $row->has_batch = $myprod->status_batch;
									$row->has_photo = 0;
									if (!empty($photoSize)) {
										$myprod->fetchPhoto($row, $photoSize);
									}
									$row->unit_id = $line->fk_unit;
									if (!empty($myprod->stock_warehouse[$warehouse]->id) || $row->qty_shipped > 0) {
										if (empty($batchId)) {
											if (empty($batch)) {
												array_push($results, $row);
											} else {
												if (($res = $myprod->fetchBatches($results, $row, $line->id, $warehouse_id, $myprod->stock_warehouse[$warehouse_id]->id, false, $batchId, $batch)) < 0) return $res;
												if ($res == 0) {
													array_push($results, $row);
												}
											}
										} else {
											if (($res = $myprod->fetchBatches($results, $row, $line->id.'_'.$warehouse, $warehouse, $myprod->stock_warehouse[$warehouse]->id, false, $batchId)) < 0) return $res;
										}
									}
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

		$orderLine = new CommandeFournisseurLigne($this->db);

		return ExtDirect::readOptionalModel($orderLine);
	}

	/**
	 * public method to read order line optionals (extra fields) from database
	 *
	 *    @param    stdClass    $param  filter with elements:
	 *                                  id Id of order to load
	 *
	 *    @return     stdClass result data or -1
	 */
	public function readLineOptionals(stdClass $param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->fournisseur->commande->lire)) return PERMISSIONERROR;
		$results = array();
		$line_id = 0;

		if (isset($param->filter)) {
			foreach ($param->filter as $key => $filter) {
				if ($filter->property == 'line_id') $line_id=$filter->value;
			}
		}

		if ($line_id > 0) {
			$extraFields = new ExtraFields($this->db);
			$orderLine = new CommandeFournisseurLigne($this->db);
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
	 *    @param    unknown_type    $params  optionals
	 *
	 *    @return     Ambigous <multitype:, unknown_type>|unknown
	 */
	public function updateLineOptionals($params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->fournisseur->commande->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);

		$orderLine = new CommandeFournisseurLigne($this->db);
		foreach ($paramArray as &$param) {
			if ($orderLine->id != $param->object_id) {
				$orderLine->id = $param->object_id;
				if (($result = $orderLine->fetch_optionals()) < 0) return ExtDirect::getDolError($result, $orderLine->errors, $orderLine->error);
			}
			$orderLine->array_options['options_'.$param->name] = $param->raw_value;
		}
		if (($result = $orderLine->insertExtraFields()) < 0) return ExtDirect::getDolError($result, $orderLine->errors, $orderLine->error);
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
		if (!isset($this->_user->rights->fournisseur->commande->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);

		$orderLine = new CommandeFournisseurLigne($this->db);
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
	 *
	 * @return Ambigous <multitype:, unknown_type>|unknown
	 */
	public function createOrderLine($param)
	{
		global $conf, $mysoc;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->fournisseur->commande->creer)) return PERMISSIONERROR;
		$orderLine = new CommandeFournisseurLigne($this->db);
		$result = 0;

		$notrigger=0;
		$paramArray = ExtDirect::toArray($param);

		foreach ($paramArray as &$params) {
			// prepare fields
			$this->prepareOrderLineFields($params, $orderLine);
			$this->id = $params->origin_id;
			if (($result = $this->fetch($this->id)) < 0)   return $result;
			$this->fetch_thirdparty();
			$tva_tx = get_default_tva($this->thirdparty, $mysoc, $orderLine->fk_product, $params->ref_supplier_id);
			$tva_npr = get_default_npr($this->thirdparty, $mysoc, $orderLine->fk_product, $params->ref_supplier_id);
			if (empty($tva_tx)) $tva_npr=0;
			$localtax1_tx = get_localtax($tva_tx, 1, $mysoc, $this->thirdparty, $tva_npr);
			$localtax2_tx = get_localtax($tva_tx, 2, $mysoc, $this->thirdparty, $tva_npr);
			if (($result = $this->addline(
				$orderLine->desc,
				$orderLine->subprice,
				$orderLine->qty,
				$tva_tx,
				$localtax1_tx,
				$localtax2_tx,
				$orderLine->fk_product,
				$params->ref_supplier_id,
				$orderLine->ref_supplier,
				$orderLine->remise_percent,
				$params->price_base_type,
				0,
				$orderLine->product_type,
				0,
				false,
				$orderLine->date_start,
				$orderLine->date_end,
				0,
				$orderLine->fk_unit
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
	 * private method to copy order fields into reception object
	 *
	 * @param Object $reception reception object
	 * @param stdclass $params object with app fields
	 *
	 * @return null
	 */
	private function prepareReceptionFields(&$reception, $params)
	{
		isset($reception->ref_supplier) ? null : ($reception->ref_supplier = $this->ref_supplier);
		isset($reception->socid) ? null : ($reception->socid = $this->socid);
		isset($reception->note_private) ? null : ($reception->note_private = $this->note_private);
		isset($reception->note_public) ? null : ($reception->note_public = $this->note_public);
		isset($reception->date_reception) ? null : ($reception->date_reception = dol_now());
		isset($reception->date_delivery) ? null : ($reception->date_delivery = $this->date_delivery);
		isset($reception->shipping_method_id) ? null : ($reception->shipping_method_id = $this->shipping_method_id);
		isset($reception->tracking_number) ? null : ($reception->tracking_number = $params->tracking_number);
		isset($reception->weight) ? null : $reception->weight = 0;
		isset($reception->sizeS) ? null : $reception->sizeS = 0;
		isset($reception->sizeW) ? null : $reception->sizeW = 0;
		isset($reception->sizeH) ? null : $reception->sizeH = 0;
	}

	/**
	 * Ext.direct method to update orderlines
	 *
	 * @param unknown_type $param object or object array with order model(s)
	 *
	 * @return Ambigous <multitype:, unknown_type>|unknown
	 */
	public function updateOrderLine($param)
	{
		global $conf, $mysoc;

		if (!isset($this->db)) return CONNECTERROR;
		if (empty($conf->reception->enabled)) {
			if (!isset($this->_user->rights->fournisseur->commande->receptionner)) return PERMISSIONERROR;
		} else {
			if (!isset($this->_user->rights->reception->creer)) return PERMISSIONERROR;
		}
		dol_include_once('/extdirect/class/ExtDirectProduct.class.php');
		$orderlineUpdated = false;

		$paramArray = ExtDirect::toArray($param);

		foreach ($paramArray as &$params) {
			if (($this->id=$params->origin_id) > 0) {
				// get old orderline
				if (($result = $this->fetch($this->id)) < 0)    return $result;
				$this->fetch_thirdparty();
				if (!$this->error) {
					foreach ($this->lines as $orderLine) {
						if ($orderLine->id == $params->origin_line_id) {
							if (($updated = $this->prepareOrderLineFields($params, $orderLine)) && isset($this->_user->rights->fournisseur->commande->creer) && !empty($this->_user->rights->fournisseur->lire)) {
								if ($this->statut == 0) {
									// update fields
									$tva_tx = get_default_tva($this->thirdparty, $mysoc, $orderLine->fk_product, $params->ref_supplier_id);
									$tva_npr = get_default_npr($this->thirdparty, $mysoc, $orderLine->fk_product, $params->ref_supplier_id);
									if (empty($tva_tx)) $tva_npr=0;
									$localtax1_tx = get_localtax($tva_tx, 1, $mysoc, $this->thirdparty, $tva_npr);
									$localtax2_tx = get_localtax($tva_tx, 2, $mysoc, $this->thirdparty, $tva_npr);
									if (($result = $this->updateline(
										$orderLine->id,
										$orderLine->desc,
										$orderLine->subprice,
										$orderLine->qty,
										$orderLine->remise_percent,
										$tva_tx,
										$localtax1_tx,
										$localtax2_tx,
										$params->price_base_type,
										$orderLine->info_bits,
										$orderLine->product_type,
										false,
										$orderLine->date_start,
										$orderLine->date_end,
										0,
										$orderLine->fk_unit,
										$orderLine->multicurrency_subprice,
										$orderLine->ref_supplier
									)) < 0)  return ExtDirect::getDolError($result, $this->errors, $this->error);
								}
							}
							// get product
							$product = new ExtDirectProduct($this->_user->login);
							if (($result = $product->fetch($orderLine->fk_product)) <0) return ExtDirect::getDolError($result, $product->errors, $product->error);
							// get supplier product
							$supplierProduct = new ProductFournisseur($this->db);
							$supplierProducts = $supplierProduct->list_product_fournisseur_price($product->id);
							if (is_array($supplierProducts)) {
								foreach ($supplierProducts as $prodsupplier) {
									if ($prodsupplier->fourn_ref == $params->ref_supplier) {
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
							$productBarcode = $product->barcode;
							if (($updated = $this->prepareProductFields($params, $product)) && isset($this->_user->rights->produit->creer)) {
								// update barcode, only update if supplier barcode is same as product barcode
								if ($updated && (empty($supplierProduct->supplier_barcode) || ($supplierProduct->supplier_barcode == $productBarcode))) {
									$product->setValueFrom('barcode', $product->barcode);
									$product->setValueFrom('fk_barcode_type', $product->barcode_type);
								}
							}
							// add photo
							$photo = new stdClass;
							$product->fetchPhoto($photo);
							if (isset($param->has_photo) && $param->has_photo > $photo->has_photo && !empty($params->photo) && isset($this->_user->rights->produit->creer)) {
								if (($result = $product->addBase64Jpeg($params->photo, $param->has_photo)) < 0) return ExtDirect::getDolError($result, $product->errors, $product->error);
							}

							// update unit price
							if (!empty($this->_user->rights->fournisseur->lire) && !empty($supplierProduct->fourn_unitprice) && !empty($supplierProduct->product_fourn_price_id)) {
								$supplier = new Societe($this->db);
								if (($result = $supplier->fetch($supplierProduct->fourn_id)) < 0) return $result;
								if (($updated = $this->prepareProdSupplierFields($params, $supplierProduct)) && isset($this->_user->rights->produit->creer)) {
									if (($result = $supplierProduct->update_buyprice(
													$supplierProduct->fourn_qty,
													$supplierProduct->fourn_unitprice * $supplierProduct->fourn_qty,
													$this->_user,
													$params->price_base_type,
													$supplier,
													$supplierProduct->fk_availability,
													$supplierProduct->ref_supplier,
													$supplierProduct->fourn_tva_tx,
													$supplierProduct->fourn_charges,
													$supplierProduct->fourn_remise_percent,
													$supplierProduct->fourn_remise,
													$supplierProduct->fourn_tva_npr,
													$supplierProduct->delivery_time_days,
													$supplierProduct->supplier_reputation,
													array(),
													$supplierProduct->default_vat_code,
													$supplierProduct->fourn_multicurrency_price,
													'HT',
													$supplierProduct->fourn_multicurrency_tx,
													$supplierProduct->fourn_multicurrency_code,
													$supplierProduct->desc_supplier,
													$supplierProduct->supplier_barcode ? $supplierProduct->supplier_barcode : $supplierProduct->fourn_barcode,
													$supplierProduct->supplier_fk_barcode_type ? $supplierProduct->supplier_fk_barcode_type : $supplierProduct->fourn_fk_barcode_type
									)) < 0) return ExtDirect::getDolError($result, $supplierProduct->errors, $supplierProduct->error);
								}
							}
							// dispatch
							if (($this->statut == 3 || $this->statut == 4 || $this->statut == 5) && ($params->qty_shipped > 0)) {
								if (!empty($this->_user->rights->fournisseur->lire) && isset($params->cost_price)) {
									// updated in client
									$cost_price = $params->cost_price;
								} elseif (!empty($this->_user->rights->fournisseur->lire) && isset($params->price)) {
									// updated in client deprecated
									$cost_price = $params->price;
								} else {
									// take ordeline price
									$cost_price = $orderLine->subprice;
									if (!empty($orderLine->remise_percent) && empty($conf->global->STOCK_EXCLUDE_DISCOUNT_FOR_PMP)) {
										$cost_price = price2num($cost_price * (100 - $orderLine->remise_percent) / 100, 'MU');
									}
								}
								if (!empty($conf->reception->enabled)) {
									// use reception mode
									require_once DOL_DOCUMENT_ROOT.'/reception/class/reception.class.php';
									require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.dispatch.class.php';

									$reception = null;
									// check if draft reception exist for order
									$this->fetchObjectLinked(null, 'order_supplier');
									if (!empty($this->linkedObjects)) {
										foreach ($this->linkedObjects['reception'] as $element) {
											if ($element->statut == Reception::STATUS_DRAFT) $reception = $element;
										}
									}
									if (!isset($reception)) {
										// create reception
										$reception = new Reception($this->db);
										$this->prepareReceptionFields($reception, $params);
										$result = $reception->create($this->_user);
										if ($result < 0) return ExtDirect::getDolError($result, $reception->errors, $reception->error);
										$reception->add_object_linked('order_supplier', $this->id);
									} else {
										// update reception
										$this->prepareReceptionFields($reception, $params);
										$result = $reception->update($this->_user);
										if ($result < 0) return ExtDirect::getDolError($result, $reception->errors, $reception->error);
									}
									// reception addline
									$lineIndex = $reception->addline(
										$params->warehouse_id,
										$orderLine->id,
										$params->qty_shipped,
										$orderLine->array_options,
										$params->comment,
										isset($params->eatby) ? ExtDirect::dateTimeToDate($params->eatby) : '',
										isset($params->sellby) ? ExtDirect::dateTimeToDate($params->sellby): '',
										$params->batch,
										$cost_price
									);
									if ($lineIndex < 0) return ExtDirect::getDolError($lineIndex, $reception->errors, $reception->error);
									// create dispatch from line created by addline
									$result = $reception->lines[$lineIndex]->create($this->_user);
									if ($result < 0) return ExtDirect::getDolError($result, $reception->lines[$lineIndex]->errors, $reception->lines[$lineIndex]->error);
								} else {
									// use dispatch mode
									if (($result = $this->DispatchProduct(
										$this->_user,
										$orderLine->fk_product,
										$params->qty_shipped,
										$params->warehouse_id,
										$cost_price,
										$params->comment,
										isset($params->eatby) ? ExtDirect::dateTimeToDate($params->eatby) : '',
										isset($params->sellby) ? ExtDirect::dateTimeToDate($params->sellby): '',
										$params->batch,
										$orderLine->id
									)) < 0)  return ExtDirect::getDolError($result, $this->errors, $this->error);
								}
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
		if (!isset($this->_user->rights->fournisseur->commande->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($param);

		foreach ($paramArray as &$params) {
			// prepare fields
			if (empty($params->origin_line_id)) {
				$lineId = $params->id;
			} else {
				$lineId = $params->origin_line_id;
			}

			$orderLine = new CommandeFournisseurLigne($this->db);
			$orderLine->fetch($lineId);
			$this->id = $orderLine->fk_commande;
			if ($lineId) {
				// delete dispatched
				if (ExtDirect::checkDolVersion(0, '', '19.0')) {
					dol_include_once('/fourn/class/fournisseur.commande.dispatch.class.php');
					$dispatch = new CommandeFournisseurDispatch($this->db);
				} else {
					dol_include_once('/reception/class/receptionlinebatch.class.php');
					$dispatch = new ReceptionLineBatch($this->db);
				}
				$dispatched = $dispatch->fetchAll('', '', 0, 0, array($this->key_ship_line_order_line => $lineId));
				if ($dispatched > 0) {
					foreach ($dispatch->lines as $line) {
						/** @var CommandeFournisseurDispatch $line */
						;
						if (($result = $line->delete($this->_user)) < 0) return ExtDirect::getDolError($result, $line->errors, $line->error);
					}
				}
				// delete line
				if (($result = $this->deleteline($lineId)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
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
	 * @param int $warehouseId warehouse id
	 *
	 * @return int qty
	 */
	private function getDispatched($lineId, $productId, $warehouseId = 0)
	{
		$dispatched = 0;
		$qtyShipped = 0;

		if ($productId) {
			$sql = "SELECT sum(cfd.qty) as qty";
			$sql.= " FROM ".MAIN_DB_PREFIX.$this->table_element_reception_line." as cfd";
			$sql.= " WHERE cfd.".$this->key_ship_line_order." = ".$this->id;
			$sql.= " AND cfd.fk_product = ".$productId;
			$sql.= " AND cfd.".$this->key_ship_line_order_line." = ".$lineId;
			if ($warehouseId > 0) {
				$sql.= " AND cfd.fk_entrepot = ".$warehouseId;
			}
			$resql=$this->db->query($sql);
			if ($resql) {
				$obj = $this->db->fetch_object($resql);
				if (!empty($obj->qty)) {
					$dispatched = price2num($obj->qty, 5);
				}
				$this->db->free($resql);
			}

			$qtyShipped = $dispatched;
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
	private function prepareOrderLineFields($params, $orderLine)
	{
		$diff = false;
		$diff = ExtDirect::prepareField($diff, $params, $orderLine, 'origin_line_id', 'id');
		$diff = ExtDirect::prepareField($diff, $params, $orderLine, 'product_id', 'fk_product');
		$diff = ExtDirect::prepareField($diff, $params, $orderLine, 'subprice', 'subprice');
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
		$diff = ExtDirect::prepareField($diff, $params, $orderLine, 'ref_supplier', 'ref_supplier');
		$diff = ExtDirect::prepareField($diff, $params, $orderLine, 'unit_id', 'fk_unit');
		return $diff;
	}

	/**
	 * private method to copy orderline fields into dolibarr supplier product object
	 *
	 * @param stdclass $params object with fields
	 * @param stdclass $prodSupplier object
	 * @return null
	 */
	private function prepareProdSupplierFields($params, $prodSupplier)
	{
		$diff = false;
		$diff = ExtDirect::prepareField($diff, $params, $prodSupplier, 'product_price', 'fourn_unitprice');
		$diff = ExtDirect::prepareField($diff, $params, $prodSupplier, 'ref_supplier', 'ref_supplier');
		$diff = ExtDirect::prepareField($diff, $params, $prodSupplier, 'ref_supplier_id', 'product_fourn_price_id');
		$diff = ExtDirect::prepareField($diff, $params, $prodSupplier, 'product_id', 'fk_product');
		$diff = ExtDirect::prepareField($diff, $params, $prodSupplier, 'barcode', 'supplier_barcode');
		$diff = ExtDirect::prepareField($diff, $params, $prodSupplier, 'barcode_type', 'supplier_fk_barcode_type');
		return $diff;
	}

	/**
	 * private method to copy orderline fields into dolibarr product object
	 *
	 * @param stdclass $params object with fields
	 * @param stdclass $product object
	 * @return null
	 */
	private function prepareProductFields($params, $product)
	{
		$diff = false;
		$diff = ExtDirect::prepareField($diff, $params, $product, 'product_id', 'id');
		$diff = ExtDirect::prepareField($diff, $params, $product, 'barcode', 'barcode');
		$diff = ExtDirect::prepareField($diff, $params, $product, 'barcode_type', 'barcode_type');
		return $diff;
	}
}
