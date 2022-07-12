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
 *  \file       htdocs/extdirect/class/ExtDirectMo.class.php
 *  \brief      Sencha Ext.Direct manufacturing order remoting class
 */

require_once DOL_DOCUMENT_ROOT . '/mrp/class/mo.class.php';
require_once DOL_DOCUMENT_ROOT . '/bom/class/bom.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
dol_include_once('/extdirect/class/extdirect.class.php');
dol_include_once('/extdirect/class/ExtDirectFormProduct.class.php');
dol_include_once('/extdirect/class/ExtDirectProduct.class.php');

/** ExtDirectMo class
 * Class to access manufacturing orders with CRUD methods to connect to Extjs or sencha touch using Ext.direct connector
 */
class ExtDirectMo extends Mo
{
	private $_user;
	private $_moConstants = array();

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
				$langs->load("mrp");
				$langs->load("products");
				$langs->load("stocks");
				$langs->load("productbatch");
				$langs->load("other");
				parent::__construct($db);
			}
		}
	}

	/**
	 *	Load mo related constants
	 *
	 *	@param			stdClass	$params		filter with elements
	 *		                                    constant	name of specific constant
	 *
	 *	@return			stdClass result data with specific constant value
	 */
	public function readConstants(stdClass $params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->mrp->read)) return PERMISSIONERROR;

		$results = ExtDirect::readConstants($this->db, $params, $this->_user, $this->_moConstants);

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
	 *                                  id Id of object to load
	 *
	 *    @return     stdClass result data or -1
	 */
	public function readOptionals(stdClass $param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->mrp->read)) return PERMISSIONERROR;
		$results = array();
		$id = 0;
		$object = new Mo($this->db);

		if (isset($param->filter)) {
			foreach ($param->filter as $key => $filter) {
				if ($filter->property == 'id') $id = $filter->value;
			}
		}

		if ($id > 0) {
			$extraFields = new ExtraFields($this->db);
			if (($result = $object->fetch($id)) < 0) return ExtDirect::getDolError($result, $object->errors, $object->error);
			if (!$object->error) {
				$extraFields->fetch_name_optionals_label($this->table_element);
				$index = 1;
				if (empty($object->array_options)) {
					// create empty optionals to be able to add optionals
					$optionsArray = (!empty($extraFields->attributes[$this->table_element]['label']) ? $extraFields->attributes[$this->table_element]['label'] : null);
					if (is_array($optionsArray) && count($optionsArray) > 0) {
						foreach ($optionsArray as $name => $label) {
							$row = new stdClass;
							$row->id = $index++;
							$row->name = $name;
							$row->value = '';
							$row->object_id = $object->id;
							$row->object_element = $this->element;
							$row->raw_value = null;
							$results[] = $row;
						}
					}
				} else {
					foreach ($object->array_options as $key => $value) {
						$row = new stdClass;
						$name = substr($key, 8); // strip options_
						$row->id = $index++; // ExtJs needs id to be able to destroy records
						$row->name = $name;
						$row->value = $extraFields->showOutputField($name, $value, '', $this->table_element);
						$row->object_id = $object->id;
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
		if (!isset($this->_user->rights->mrp->write)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);
		$object = new Mo($this->db);

		foreach ($paramArray as &$param) {
			if ($object->id != $param->object_id && ($result = $object->fetch($param->object_id)) < 0) return ExtDirect::getDolError($result, $object->errors, $object->error);
			$object->array_options['options_' . $param->name] = $param->raw_value;
		}
		if (($result = $object->insertExtraFields()) < 0) return ExtDirect::getDolError($result, $object->errors, $object->error);
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
		if (!isset($this->_user->rights->mrp->mrp->write)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);
		$object = new Mo($this->db);

		foreach ($paramArray as &$param) {
			if ($this->id != $param->object_id && ($result = $object->fetch($param->object_id)) < 0) return ExtDirect::getDolError($result, $object->errors, $object->error);
		}
		if (($result = $object->deleteExtraFields()) < 0) return ExtDirect::getDolError($result, $object->errors, $object->error);
		if (is_array($params)) {
			return $paramArray;
		} else {
			return $param;
		}
	}

	/**
	 * Ext.direct method to Create object
	 *
	 * @param unknown_type $param object or object array with record
	 * @return result data or -1
	 */
	public function extCreate($param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->mrp->write)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($param);
		$object = new Mo($this->db);

		foreach ($paramArray as &$params) {
			// prepare fields
			$this->prepareFields($params);

			if (($result = $object->create($this->_user)) < 0) return ExtDirect::getDolError($result, $object->errors, $object->error);
			$params->id = $object->id;
		}

		if (is_array($param)) {
			return $paramArray;
		} else {
			return $params;
		}
	}

	/**
	 *    Load object from database into memory
	 *
	 *    @param    stdClass    $params     filter with elements:
	 *                                      id Id of object to load
	 *    @return     stdClass result data or -1
	 */
	public function extRead(stdClass $params)
	{
		global $conf;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->mrp->read)) return PERMISSIONERROR;

		$results = array();
		$row = new stdClass;
		$id = 0;
		$ref = '';
		$object = new Mo($this->db);

		if (isset($params->filter)) {
			foreach ($params->filter as $key => $filter) {
				if ($filter->property == 'id') $id = $filter->value;
				elseif ($filter->property == 'ref') $ref = $filter->value;
			}
		}

		if (($id > 0) || ($ref != '')) {
			$result = $object->fetch($id, $ref);
			if ($result < 0) return ExtDirect::getDolError($result, $object->errors, $object->error);
			if ($result > 0) {
				$row = $this->getMoData($object);
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
		if (!isset($this->_user->rights->mrp->write)) return PERMISSIONERROR;

		$paramArray = ExtDirect::toArray($param);
		$object = new Mo($this->db);

		foreach ($paramArray as &$params) {
			// prepare fields
			if ($params->id) {
				$id = $params->id;
				$result = $object->fetch($id);
				if ($result < 0) return ExtDirect::getDolError($result, $object->errors, $object->error);
				if ($result > 0) {
					$this->prepareFields($params);
					// update
					switch ($params->status_id) {
						case -1:
							break;
						case 0:
							break;
						case 1:
							$result = $object->validate($this->_user);
							// PDF generating
							if (($result >= 0) && empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE)) {
								$hidedetails = (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS) ? 1 : 0);
								$hidedesc = (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DESC) ? 1 : 0);
								$hideref = (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_REF) ? 1 : 0);
								$outputlangs = $langs;
								if ($conf->global->MAIN_MULTILANGS) {
									$object->fetch_thirdparty();
									$newlang = $object->thirdparty->default_lang;
									$outputlangs = new Translate("", $conf);
									$outputlangs->setDefaultLang($newlang);
								}
								$object->generateDocument($object->model_pdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
							}
							break;
						case 2:
							break;
						default:
							break;
					}
				}
				if ($result < 0) return ExtDirect::getDolError($result, $object->errors, $object->error);
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
	 * Ext.direct method to destroy object
	 *
	 * @param unknown_type $param object or object array with record
	 * @return result data or -1
	 */
	public function extDestroy($param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->mrp->delete)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($param);
		$object = new Mo($this->db);

		foreach ($paramArray as &$params) {
			// prepare fields
			if ($params->id) {
				$object->id = $params->id;
				if (($result = $object->fetch($object->id)) < 0) return ExtDirect::getDolError($result, $object->errors, $object->error);
				// delete
				if (($result = $object->delete($this->_user)) < 0) return ExtDirect::getDolError($result, $object->errors, $object->error);
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
	 * Ext.direct method to upload file for object
	 *
	 * @param unknown_type $params object or object array with uploaded file(s)
	 * @return Array    ExtDirect response message
	 */
	public function fileUpload($params)
	{
		global $conf;
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->mrp->write)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);
		$dir = null;
		$object = new Mo($this->db);

		foreach ($paramArray as &$param) {
			if (isset($param['extTID'])) {
				$id = $param['extTID'];
				if ($object->fetch($id)) {
					$object->fetch_thirdparty();
					$dir = $conf->mrp->dir_output . dol_sanitizeFileName($object->ref);
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
	 * public method to read a list of objects
	 *
	 * @param stdClass $params to filter on order status and ref
	 * @return     stdClass result data or error number
	 */
	public function extList(stdClass $params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->mrp->read)) return PERMISSIONERROR;
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

		$sqlFields = "SELECT s.nom, s.rowid AS socid, mo.rowid, mo.ref, mo.status, bom.ref as ref_bom, p.ref as ref_product, ea.activity_status, mo.date_creation";
		$sqlFrom = " FROM " . MAIN_DB_PREFIX . "mrp_mo as mo";
		$sqlFrom .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON mo.fk_soc = s.rowid";
		$sqlFrom .= " LEFT JOIN " . MAIN_DB_PREFIX . "bom_bom as bom ON mo.fk_bom = bom.rowid";
		$sqlFrom .= " LEFT JOIN " . MAIN_DB_PREFIX . "product as p ON mo.fk_product = p.rowid";
		if ($contactTypeId > 0) $sqlFrom .= " LEFT JOIN " . MAIN_DB_PREFIX . "element_contact as ec ON mo.rowid = ec.element_id";
		if ($originId) {
			$sqlFrom .= " INNER JOIN " . MAIN_DB_PREFIX . "element_element as el ON el.fk_target = mo.rowid AND fk_source = " . $originId;
			$sqlFrom .= " AND el.sourcetype = 'commande' AND el.targettype = '" . $this->db->escape($this->element) . "'";
		}
		$sqlFrom .= " LEFT JOIN ("; // get latest extdirect activity status for commande to check if locked
		$sqlFrom .= "   SELECT ma.activity_id, ma.maxrow AS rowid, ea.status as activity_status";
		$sqlFrom .= "   FROM (";
		$sqlFrom .= "    SELECT MAX( rowid ) AS maxrow, activity_id";
		$sqlFrom .= "    FROM " . MAIN_DB_PREFIX . "extdirect_activity";
		$sqlFrom .= "    GROUP BY activity_id";
		$sqlFrom .= "   ) AS ma, " . MAIN_DB_PREFIX . "extdirect_activity AS ea";
		$sqlFrom .= "   WHERE ma.maxrow = ea.rowid";
		$sqlFrom .= " ) AS ea ON mo.rowid = ea.activity_id";
		$sqlWhere = " WHERE mo.entity IN (" . getEntity($this->table_element) . ')';

		if ($statusFilterCount > 0) {
			$sqlWhere .= " AND ( ";
			foreach ($status_id as $key => $status) {
				$sqlWhere  .= "mo.status = " . $status;
				if ($key < ($statusFilterCount - 1)) $sqlWhere  .= " OR ";
			}
			$sqlWhere .= ")";
		}
		if ($ref) {
			$sqlWhere .= " AND mo.ref = '" . $ref . "'";
		}
		if ($contactTypeId > 0) {
			$sqlWhere .= " AND ec.fk_c_type_contact = " . $contactTypeId;
			$sqlWhere .= " AND ec.fk_socpeople = " . $contactId;
		}

		if ($contentFilter) {
			$fields = array('mo.ref', 'mo.ref_supplier', 's.nom');
			$sqlWhere .= " AND ".natural_search($fields, $contentFilter, 0, 1);
		}

		$sqlOrder = " ORDER BY ";
		if (isset($params->sort)) {
			$sorterSize = count($params->sort);
			foreach ($params->sort as $key => $sort) {
				if (!empty($sort->property)) {
					if ($sort->property == 'status_id') {
						$sortfield = 'mo.status';
					} elseif ($sort->property == 'order_date') {
						$sortfield = 'mo.date_creation';
					} elseif ($sort->property == 'ref') {
						$sortfield = 'mo.ref';
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
			$sqlOrder .= "mo.date_creation DESC";
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
				$row->ref_bom       = $obj->ref_bom;
				$row->status_id = (int) $obj->status;
				$row->statusdisplay = html_entity_decode($this->LibStatut($obj->status, 1));
				$row->status        = $obj->activity_status;
				$row->ref_product   = $obj->ref_product;
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
		$objectTypes = array();
		$orderTypes = array();
		$row = new stdClass;
		if (!is_array($objectTypes = $this->liste_type_contact())) return ExtDirect::getDolError($objectTypes, $this->errors, $this->error);
		// also get from order
		$order = new Commande($this->db);
		if (!is_array($orderTypes = $order->liste_type_contact())) return ExtDirect::getDolError($orderTypes, $this->errors, $this->error);
		// add empty type
		$row->id = 0;
		$row->label = '';
		array_push($results, $row);
		foreach ($objectTypes as $id => $label) {
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
	 * get data from object
	 *
	 * @param Object	$object	object
	 * @return stdClass object with data
	 */
	private function getMoData($object)
	{
		$data = new stdClass;
		$myUser = new User($this->db);
		$mySociete = new Societe($this->db);
		$bom = new Bom($this->db);
		$product = new Product($this->db);
		$originObject = null;

		foreach ($object->fields as $field => $info) {
			if ($field == 'rowid') {
				$data->id = (int) $object->id;
			} elseif ($field == 'fk_soc') {
				$data->customer_id = (int) $object->{$field};
				if ($mySociete->fetch($data->customer_id) > 0) {
					$data->customer_name = $mySociete->name;
				}
			} elseif ($field == 'fk_bom') {
				$data->bom_id = (int) $object->{$field};
				if ($bom->fetch($data->bom_id) > 0) {
					$data->ref_bom = $bom->ref;
				}
			} elseif ($field == 'fk_product') {
				$data->product_id = (int) $object->{$field};
				if ($product->fetch($data->product_id) > 0) {
					$data->ref_product = $product->ref;
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
			if ($data->origin == 'commande') {
				$originObject = new Commande($this->db);
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
			} elseif ($field == 'fk_user_creat') {
				isset($params->user_id) ? $this->{$field} = $params->user_id : (isset($this->{$field}) ? null : $this->{$field} = null);
			} else {
				isset($params->{$field}) ? $this->{$field} = $params->{$field} : (isset($this->{$field}) ? null : $this->{$field} = null);
			}
		}
	}
}
