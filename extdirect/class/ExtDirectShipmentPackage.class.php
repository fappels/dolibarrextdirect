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
dol_include_once('/shipmentpackage/class/shipmentpackage.class.php');
dol_include_once('/extdirect/class/extdirect.class.php');
dol_include_once('/extdirect/class/ExtDirectFormProduct.class.php');
dol_include_once('/extdirect/class/ExtDirectProduct.class.php');

/** ExtDirectExpedition class
 * Class to access shipments with CRUD methods to connect to Extjs or sencha touch using Ext.direct connector
 */
class ExtDirectShipmentPackage extends ShipmentPackage
{
	private $_user;
	private $_shipmentPackageConstants = array();

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
				$this->_user = $user;  //commande.class uses global user
				if (isset($this->_user->conf->MAIN_LANG_DEFAULT) && ($this->_user->conf->MAIN_LANG_DEFAULT != 'auto')) {
					$langs->setDefaultLang($this->_user->conf->MAIN_LANG_DEFAULT);
				}
				// set global $mysoc required for price calculation
				$mysoc = new Societe($db);
				$mysoc->setMysoc($conf);
				$langs->load("sendings");
				$langs->load("products");
				$langs->load("stocks");
				$langs->load("productbatch");
				$langs->load("shipmentpackage@shipmentpackage");
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
		if (!isset($this->_user->rights->shipmentpackage->shipmentpackage->read)) return PERMISSIONERROR;

		$results = ExtDirect::readConstants($this->db, $params, $this->_user, $this->_shipmentPackageConstants);

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
		if (!isset($this->_user->rights->shipmentpackage->shipmentpackage->read)) return PERMISSIONERROR;
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
						$row->value = $extraFields->showOutputField($name, $value);
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
		if (!isset($this->_user->rights->shipmentpackage->shipmentpackage->write)) return PERMISSIONERROR;
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
		if (!isset($this->_user->rights->shipmentpackage->shipmentpackage->write)) return PERMISSIONERROR;
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
	 * Ext.direct method to Create ShipmentPackage
	 *
	 * @param unknown_type $param object or object array with shipment record
	 * @return result data or -1
	 */
	public function extCreate($param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->shipmentpackage->shipmentpackage->write)) return PERMISSIONERROR;
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
	 *    Load shipment package from database into memory
	 *
	 *    @param    stdClass    $params     filter with elements:
	 *                                      id Id of shipment to load
	 *    @return     stdClass result data or -1
	 */
	public function extRead(stdClass $params)
	{
		global $conf;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->shipmentpackage->shipmentpackage->read)) return PERMISSIONERROR;

		$results = array();
		$row = new stdClass;
		$id = 0;
		$ref = '';

		if (isset($params->filter)) {
			foreach ($params->filter as $key => $filter) {
				if ($filter->property == 'id') $id = $filter->value;
				elseif ($filter->property == 'ref') $ref = $filter->value;
			}
		}

		if (($id > 0) || ($ref != '')) {
			$result = $this->fetch($id, $ref);
			if ($result < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
			if ($result > 0) {
				$row = $this->getShipmentPackageData($this);
				array_push($results, $row);
			} else {
				return 0;
			}
		} else {
			return PARAMETERERROR;
		}

		return $results;
	}

	/**
	 * Ext.direct method to update
	 *
	 * @param unknown_type $param object or object array with shipment record
	 * @return result data or -1
	 */
	public function extUpdate($param)
	{
		global $conf, $langs;

		if (!isset($this->db)) return CONNECTERROR;

		$paramArray = ExtDirect::toArray($param);

		foreach ($paramArray as &$params) {
			// prepare fields
			if ($params->id) {
				$id = $params->id;
				$result = $this->fetch($id);
				if ($result < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				if ($result > 0) {
					$this->prepareFields($params);
					// update
					switch ($params->status_id) {
						case -1:
							break;
						case 0:
							break;
						case 1:
							$result = $this->validate($this->_user);
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
								$this->generateDocument($this->model_pdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
							}
							break;
						case 2:
							break;
						default:
							break;
					}
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
	 * Ext.direct method to destroy shipment package
	 *
	 * @param unknown_type $param object or object array with shipment record
	 * @return result data or -1
	 */
	public function extDestroy($param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->shipmentpackage->shipmentpackage->delete)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($param);

		foreach ($paramArray as &$params) {
			// prepare fields
			if ($params->id) {
				$this->id = $params->id;
				if (($result = $this->fetch($this->id)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
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
	 * Ext.direct method to upload file for shipment object
	 *
	 * @param unknown_type $params object or object array with uploaded file(s)
	 * @return Array    ExtDirect response message
	 */
	public function fileUpload($params)
	{
		global $conf;
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->shipmentpackage->shipmentpackage->write)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);
		$dir = null;

		foreach ($paramArray as &$param) {
			if (isset($param['extTID'])) {
				$id = $param['extTID'];
				if ($this->fetch($id)) {
					$this->fetch_thirdparty();
					$dir = $conf->shipmentpackage->dir_output . "/shipmentpackage/" . dol_sanitizeFileName($this->ref);
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
	 * public method to read a list of shipments
	 *
	 * @param stdClass $params to filter on order status and ref
	 * @return     stdClass result data or error number
	 */
	public function extList(stdClass $params)
	{
		global $conf;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->shipmentpackage->shipmentpackage->read)) return PERMISSIONERROR;
		$result = new stdClass;
		$data = array();

		$statusFilterCount = 0;
		$ref = null;
		$contactTypeId = 0;
		$originId = 0;
		$status_id = array();

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
				if ($filter->property == 'status_id') $status_id[$statusFilterCount++] = $filter->value;
				elseif ($filter->property == 'ref') $ref = $filter->value;
				elseif ($filter->property == 'contacttype_id') $contactTypeId = $filter->value;
				elseif ($filter->property == 'contact_id') $contactId = $filter->value;
				elseif ($filter->property == 'origin_id') $originId = $filter->value;
			}
		}

		$sqlFields = "SELECT s.nom, s.rowid AS socid, ep.rowid, ep.ref, ep.status, ep.ref_supplier, ea.activity_status, ep.date_creation";
		$sqlFrom = " FROM " . MAIN_DB_PREFIX . "societe as s, " . MAIN_DB_PREFIX . "expedition_package as ep";
		if ($contactTypeId > 0) $sqlFrom .= " LEFT JOIN " . MAIN_DB_PREFIX . "element_contact as ec ON e.rowid = ec.element_id";
		if ($originId) {
			$sqlFrom .= " INNER JOIN " . MAIN_DB_PREFIX . "element_element as el ON el.fk_target = e.rowid AND fk_source = " . $originId;
			$sqlFrom .= " AND el.sourcetype = 'shipping' AND el.targettype = '" . $this->db->escape($this->element) . "'";
		}
		$sqlFrom .= " LEFT JOIN ("; // get latest extdirect activity status for commande to check if locked
		$sqlFrom .= "   SELECT ma.activity_id, ma.maxrow AS rowid, ea.status as activity_status";
		$sqlFrom .= "   FROM (";
		$sqlFrom .= "    SELECT MAX( rowid ) AS maxrow, activity_id";
		$sqlFrom .= "    FROM " . MAIN_DB_PREFIX . "extdirect_activity";
		$sqlFrom .= "    GROUP BY activity_id";
		$sqlFrom .= "   ) AS ma, " . MAIN_DB_PREFIX . "extdirect_activity AS ea";
		$sqlFrom .= "   WHERE ma.maxrow = ea.rowid";
		$sqlFrom .= " ) AS ea ON e.rowid = ea.activity_id";
		$sqlWhere = " WHERE e.entity IN (" . getEntity($this->table_element) . ')';
		$sqlWhere .= " AND e.fk_soc = s.rowid";

		if ($statusFilterCount > 0) {
			$sqlWhere .= " AND ( ";
			foreach ($status_id as $key => $status) {
				$sqlWhere  .= "ep.status = " . $status;
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

		$sqlOrder = " ORDER BY ";
		if (isset($params->sort)) {
			$sorterSize = count($params->sort);
			foreach ($params->sort as $key => $sort) {
				if (!empty($sort->property)) {
					if ($sort->property == 'status_id') {
						$sortfield = 'ep.status';
					} elseif ($sort->property == 'order_date') {
						$sortfield = 'ep.date_creation';
					} elseif ($sort->property == 'ref') {
						$sortfield = 'ep.ref';
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
			$sqlOrder .= "ep.date_creation DESC";
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
				$row->status_id = (int) $obj->status;
				$row->statusdisplay = html_entity_decode($this->LibStatut($obj->status, 1));
				$row->status        = $obj->activity_status;
				$row->mode          = $obj->mode;
				$row->date_creation  = $this->db->jdate($obj->date_creation);
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
	public function readStatus()
	{
		if (!isset($this->db)) return CONNECTERROR;
		$results = array();
		$statut = 0;
		$row = new stdClass;
		while (($result = $this->LibStatut($statut, 1)) !== null) {
			if ($row->status == html_entity_decode($result)) break; // avoid infinite loop
			$row = new stdClass;
			$row->id = $statut;
			$row->status = html_entity_decode($result);
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
	 * get data from shipmentpackage opject
	 *
	 * @param Object	$object	shipmentpackage object
	 * @return stdClass object with data
	 */
	private function getShipmentPackageData($object)
	{
		$data = new stdClass;
		$myUser = new User($this->db);
		$mySociete = new Societe($this->db);
		$originObject = null;

		foreach ($object->fields as $field => $info) {
			if ($field == 'rowid') {
				$data->id = (int) $object->id;
			} elseif ($field == 'fk_soc') {
				$data->customer_id = (int) $object->{$field};
				if ($mySociete->fetch($data->customer_id) > 0) {
					$data->customer_name = $mySociete->name;
				}
			} elseif ($field == 'fk_supplier') {
				$data->supplier_id = (int) $object->{$field};
				if ($mySociete->fetch($data->supplier_id) > 0) {
					$data->supplier_name = $mySociete->name;
				}
			} elseif ($field == 'fk_user_creat') {
				$data->user_id = (int) $object->{$field};
				if ($myUser->fetch($data->user_id) > 0) {
					$data->user_name = $myUser->firstname . ' ' . $myUser->lastname;
				}
			} elseif ($field == 'status') {
				$data->status_id = (int) $object->{$field};
				$data->statusdisplay = $object->getLibStatut(1);
			} elseif ($field == 'tms') {
				$data->date_modification = $object->{$field};
			} else {
				$data->{$field} = $object->{$field};
			}
		}
		$data->origin_id = $object->origin_id;
		$data->origin = $object->origin;
		if (!empty($data->origin) && !empty($data->origin_id)) {
			if ($data->origin == 'shipping') {
				$originObject = new Expedition($this->db);
				if ($originObject->fetch($data->origin_id) > 0) {
					$data->origin_ref = $originObject->ref;
				}
			}
		}

		return $data;
	}

	/**
	 * private method to copy package fields into dolibarr object
	 *
	 * @param stdclass $params object with fields
	 * @return null
	 */
	private function prepareFields($params)
	{
		foreach ($this->fields as $field => $info) {
			if ($field == 'fk_soc') {
				isset($params->customer_id) ? $this->{$field} = $params->customer_id : (isset($this->{$field}) ? null : $this->{$field} = null);
			} elseif ($field == 'fk_supplier') {
				isset($params->supplier_id) ? $this->{$field} = $params->supplier_id : (isset($this->{$field}) ? null : $this->{$field} = null);
			} elseif ($field == 'fk_user_creat') {
				isset($params->user_id) ? $this->{$field} = $params->user_id : (isset($this->{$field}) ? null : $this->{$field} = null);
			} else {
				isset($params->{$field}) ? $this->{$field} = $params->{$field} : (isset($this->{$field}) ? null : $this->{$field} = null);
			}
		}
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
			require_once DOL_DOCUMENT_ROOT . '/expedition/class/expeditionbatch.class.php';
			if (($lotArray = ExpeditionLineBatch::fetchAll($this->db, $this->id)) < 0) {
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

			if (ExtDirect::checkDolVersion() >= 4.0) {
				// fetch from product_lot
				require_once DOL_DOCUMENT_ROOT . '/product/stock/class/productlot.class.php';
				$lot = new Productlot($this->db);
				if ($lot->fetch(0, $this->fk_product, $batch) < 0) {
					$this->errors[] = $lot->errors;
					return -3;
				}
			} else {
				// fetch from product batch
				require_once DOL_DOCUMENT_ROOT . '/product/class/productbatch.class.php';
				$lot = new Productbatch($this->db);
				if ($lot->fetch($batch_id) < 0) {
					$this->errors[] = $lot->error;
					return -3;
				}
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