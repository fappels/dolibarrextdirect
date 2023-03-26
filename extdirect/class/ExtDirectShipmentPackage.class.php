<?PHP

/**
 * Copyright (C) 2022       Francis Appels <francis.appels@z-application.com>
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
 *  \file       htdocs/extdirect/class/ExtDirectShipmentPackage.class.php
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

/** ExtDirectShipmentPackage class
 * Class to access shipments with CRUD methods to connect to Extjs or sencha touch using Ext.direct connector
 */
class ExtDirectShipmentPackage extends ShipmentPackage
{
	private $_user;
	private $_shipmentPackageConstants = array();
	private $_enabled = false;

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
				$this->_enabled = !empty($conf->shipmentpackage->enabled) && isset($user->rights->shipmentpackage->shipmentpackage->read);
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
				$langs->load("shipmentpackage@shipmentpackage");
				parent::__construct($db);
			}
		}
	}

	/**
	 *	Load related constants
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
	 * public method to read optionals (extra fields) from database
	 *
	 *    @param    stdClass    $param  filter with elements:
	 *                                  id Id of shipmentpackage to load
	 *
	 *    @return     stdClass result data or -1
	 */
	public function readOptionals(stdClass $param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->shipmentpackage->shipmentpackage->read)) return PERMISSIONERROR;
		$results = array();
		$id = 0;
		$shipmentPackage = new ShipmentPackage($this->db);

		if (isset($param->filter)) {
			foreach ($param->filter as $key => $filter) {
				if ($filter->property == 'id') $id = $filter->value;
			}
		}

		if ($id > 0) {
			$extraFields = new ExtraFields($this->db);
			if (($result = $shipmentPackage->fetch($id)) < 0) return ExtDirect::getDolError($result, $shipmentPackage->errors, $shipmentPackage->error);
			if (!$shipmentPackage->error) {
				$extraFields->fetch_name_optionals_label($this->table_element);
				$index = 1;
				if (empty($shipmentPackage->array_options)) {
					// create empty optionals to be able to add optionals
					$optionsArray = (!empty($extraFields->attributes[$this->table_element]['label']) ? $extraFields->attributes[$this->table_element]['label'] : null);
					if (is_array($optionsArray) && count($optionsArray) > 0) {
						foreach ($optionsArray as $name => $label) {
							$row = new stdClass;
							$row->id = $index++;
							$row->name = $name;
							$row->value = '';
							$row->object_id = $shipmentPackage->id;
							$row->object_element = $this->element;
							$row->raw_value = null;
							$results[] = $row;
						}
					}
				} else {
					foreach ($shipmentPackage->array_options as $key => $value) {
						$row = new stdClass;
						$name = substr($key, 8); // strip options_
						$row->id = $index++; // ExtJs needs id to be able to destroy records
						$row->name = $name;
						$row->value = $extraFields->showOutputField($name, $value, '', $this->table_element);
						$row->object_id = $shipmentPackage->id;
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
		$shipmentPackage = new ShipmentPackage($this->db);

		foreach ($paramArray as &$param) {
			if ($shipmentPackage->id != $param->object_id && ($result = $shipmentPackage->fetch($param->object_id)) < 0) return ExtDirect::getDolError($result, $shipmentPackage->errors, $shipmentPackage->error);
			$shipmentPackage->array_options['options_' . $param->name] = $param->raw_value;
		}
		if (($result = $shipmentPackage->insertExtraFields()) < 0) return ExtDirect::getDolError($result, $shipmentPackage->errors, $shipmentPackage->error);
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
		$shipmentPackage = new ShipmentPackage($this->db);

		foreach ($paramArray as &$param) {
			if ($this->id != $param->object_id && ($result = $shipmentPackage->fetch($param->object_id)) < 0) return ExtDirect::getDolError($result, $shipmentPackage->errors, $shipmentPackage->error);
		}
		if (($result = $shipmentPackage->deleteExtraFields()) < 0) return ExtDirect::getDolError($result, $shipmentPackage->errors, $shipmentPackage->error);
		if (is_array($params)) {
			return $paramArray;
		} else {
			return $param;
		}
	}

	/**
	 * Ext.direct method to Create ShipmentPackage
	 *
	 * @param unknown_type $param object or object array with object record
	 * @return result data or -1
	 */
	public function extCreate($param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->shipmentpackage->shipmentpackage->write)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($param);
		$shipmentPackage = new ShipmentPackage($this->db);

		foreach ($paramArray as &$params) {
			// prepare fields
			$this->prepareFields($params);

			if (($result = $shipmentPackage->create($this->_user)) < 0) return ExtDirect::getDolError($result, $shipmentPackage->errors, $shipmentPackage->error);
			$params->id = $shipmentPackage->id;
		}

		if (is_array($param)) {
			return $paramArray;
		} else {
			return $params;
		}
	}

	/**
	 *    Load shipmentpackage from database into memory
	 *
	 *    @param    stdClass    $params     filter with elements:
	 *                                      id Id of object to load
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
		$shipmentPackage = new ShipmentPackage($this->db);

		if (isset($params->filter)) {
			foreach ($params->filter as $key => $filter) {
				if ($filter->property == 'id') $id = $filter->value;
				elseif ($filter->property == 'ref') $ref = $filter->value;
			}
		}

		if (($id > 0) || ($ref != '')) {
			$result = $shipmentPackage->fetch($id, $ref);
			if ($result < 0) return ExtDirect::getDolError($result, $shipmentPackage->errors, $shipmentPackage->error);
			if ($result > 0) {
				$row = $this->getShipmentPackageData($shipmentPackage);
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
	 * @param unknown_type $param object or object array with record
	 * @return result data or -1
	 */
	public function extUpdate($param)
	{
		global $conf, $langs;

		if (!isset($this->db)) return CONNECTERROR;

		$paramArray = ExtDirect::toArray($param);
		$shipmentPackage = new ShipmentPackage($this->db);

		foreach ($paramArray as &$params) {
			// prepare fields
			if ($params->id) {
				$id = $params->id;
				$result = $shipmentPackage->fetch($id);
				if ($result < 0) return ExtDirect::getDolError($result, $shipmentPackage->errors, $shipmentPackage->error);
				if ($result > 0) {
					$this->prepareFields($params);
					// update
					switch ($params->status_id) {
						case -1:
							break;
						case 0:
							break;
						case 1:
							$result = $shipmentPackage->validate($this->_user);
							// PDF generating
							if (($result >= 0) && empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE)) {
								$hidedetails = (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS) ? 1 : 0);
								$hidedesc = (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DESC) ? 1 : 0);
								$hideref = (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_REF) ? 1 : 0);
								$outputlangs = $langs;
								if ($conf->global->MAIN_MULTILANGS) {
									$shipmentPackage->fetch_thirdparty();
									$newlang = $shipmentPackage->thirdparty->default_lang;
									$outputlangs = new Translate("", $conf);
									$outputlangs->setDefaultLang($newlang);
								}
								$shipmentPackage->generateDocument($shipmentPackage->model_pdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
							}
							break;
						case 2:
							break;
						default:
							break;
					}
				}
				if ($result < 0) return ExtDirect::getDolError($result, $shipmentPackage->errors, $shipmentPackage->error);
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
	 * Ext.direct method to destroy shipmentpackage
	 *
	 * @param unknown_type $param object or object array with record
	 * @return result data or -1
	 */
	public function extDestroy($param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->shipmentpackage->shipmentpackage->delete)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($param);
		$shipmentPackage = new ShipmentPackage($this->db);

		foreach ($paramArray as &$params) {
			// prepare fields
			if ($params->id) {
				$shipmentPackage->id = $params->id;
				if (($result = $shipmentPackage->fetch($shipmentPackage->id)) < 0) return ExtDirect::getDolError($result, $shipmentPackage->errors, $shipmentPackage->error);
				// delete
				if (($result = $shipmentPackage->delete($this->_user)) < 0) return ExtDirect::getDolError($result, $shipmentPackage->errors, $shipmentPackage->error);
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
	 * Ext.direct method to upload file for shipmentpackage object
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
		$shipmentPackage = new ShipmentPackage($this->db);

		foreach ($paramArray as &$param) {
			if (isset($param['extTID'])) {
				$id = $param['extTID'];
				if ($shipmentPackage->fetch($id)) {
					$shipmentPackage->fetch_thirdparty();
					$dir = $conf->shipmentpackage->dir_output . "/shipmentpackage/" . dol_sanitizeFileName($shipmentPackage->ref);
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
		if (!isset($this->db)) return CONNECTERROR;
		if (!$this->_enabled) return NOTENABLEDERROR;
		if (!isset($this->_user->rights->shipmentpackage->shipmentpackage->read)) return PERMISSIONERROR;
		$result = new stdClass;
		$data = array();

		$statusFilterCount = 0;
		$ref = null;
		$contactTypeId = 0;
		$originId = 0;
		$status_id = array();
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
				if ($filter->property == 'status_id') $status_id[$statusFilterCount++] = $filter->value;
				elseif ($filter->property == 'ref') $ref = $filter->value;
				elseif ($filter->property == 'contacttype_id') $contactTypeId = $filter->value;
				elseif ($filter->property == 'contact_id') $contactId = $filter->value;
				elseif ($filter->property == 'origin_id') $originId = $filter->value;
				elseif ($filter->property == 'content') $contentFilter = $filter->value;
			}
		}

		$sqlFields = "SELECT s.nom, s.rowid AS socid, ep.rowid, ep.ref, ep.status, ep.ref_supplier, ea.activity_status, ep.date_creation";
		$sqlFrom = " FROM " . MAIN_DB_PREFIX . "societe as s, " . MAIN_DB_PREFIX . "expedition_package as ep";
		if ($contactTypeId > 0) $sqlFrom .= " LEFT JOIN " . MAIN_DB_PREFIX . "element_contact as ec ON ep.rowid = ec.element_id";
		if ($originId) {
			$sqlFrom .= " INNER JOIN " . MAIN_DB_PREFIX . "element_element as el ON el.fk_target = ep.rowid AND fk_source = " . $originId;
			$sqlFrom .= " AND el.sourcetype = 'shipmentpackage' AND el.targettype = '" . $this->db->escape($this->element) . "'";
		}
		$sqlFrom .= " LEFT JOIN ("; // get latest extdirect activity status for commande to check if locked
		$sqlFrom .= "   SELECT ma.activity_id, ma.maxrow AS rowid, ea.status as activity_status";
		$sqlFrom .= "   FROM (";
		$sqlFrom .= "    SELECT MAX( rowid ) AS maxrow, activity_id";
		$sqlFrom .= "    FROM " . MAIN_DB_PREFIX . "extdirect_activity";
		$sqlFrom .= "    GROUP BY activity_id";
		$sqlFrom .= "   ) AS ma, " . MAIN_DB_PREFIX . "extdirect_activity AS ea";
		$sqlFrom .= "   WHERE ma.maxrow = ea.rowid";
		$sqlFrom .= " ) AS ea ON ep.rowid = ea.activity_id";
		$sqlWhere = " WHERE ep.entity IN (" . getEntity($this->table_element) . ')';
		$sqlWhere .= " AND ep.fk_soc = s.rowid";

		if ($statusFilterCount > 0) {
			$sqlWhere .= " AND ( ";
			foreach ($status_id as $key => $status) {
				$sqlWhere  .= "ep.status = " . $status;
				if ($key < ($statusFilterCount - 1)) $sqlWhere  .= " OR ";
			}
			$sqlWhere .= ")";
		}
		if ($ref) {
			$sqlWhere .= " AND ep.ref = '" . $ref . "'";
		}
		if ($contactTypeId > 0) {
			$sqlWhere .= " AND ec.fk_c_type_contact = " . $contactTypeId;
			$sqlWhere .= " AND ec.fk_socpeople = " . $contactId;
		}

		if ($contentFilter) {
			$fields = array('ep.ref', 'ep.ref_supplier', 's.nom');
			$sqlWhere .= " AND ".natural_search($fields, $contentFilter, 0, 1);
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
	 * public method to read a list of statusses
	 *
	 * @return     stdClass result data or error number
	 */
	public function readStatus()
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
	 * get data from shipmentpackage object
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
				if ($data->user_id > 0 && $myUser->fetch($data->user_id) > 0) {
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
