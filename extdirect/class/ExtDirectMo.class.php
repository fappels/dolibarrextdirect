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
require_once DOL_DOCUMENT_ROOT . '/core/class/commonobjectline.class.php';
require_once DOL_DOCUMENT_ROOT . '/mrp/class/mo.class.php';
require_once DOL_DOCUMENT_ROOT . '/bom/class/bom.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/stock/class/mouvementstock.class.php';
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
	private $_moConstants = array('STOCK_ALLOW_NEGATIVE_TRANSFER');
	private $_enabled = false;
	private $_productstock_cache = array();

	/**
	 * Fully producible status of validated order
	 */
	const STATUS_VALIDATED_FULLY_PRODUCIBLE = 20;

	/**
	 * Fully producible status of onprocess order
	 */
	const STATUS_INPROGRESS_FULLY_PRODUCIBLE = 21;

	/**
	 * partly producible status of validated order
	 */
	const STATUS_VALIDATED_PARTLY_PRODUCIBLE = 22;

	/**
	 * partly producible status of on process order
	 */
	const STATUS_INPROGRESS_PARTLY_PRODUCIBLE = 23;

	/**
	 * Not producible status of validate order
	 */
	const STATUS_VALIDATED_NOT_PRODUCIBLE = 24;

	/**
	 * Not producible status of on process order
	 */
	const STATUS_INPROGRESS_NOT_PRODUCIBLE = 25;

	/**
	 * end status to allow status itteration
	 */
	const STATUS_END = 26;

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
				$this->_enabled = !empty($conf->mrp->enabled) && isset($user->rights->mrp->read);
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
				$langs->load("ticket");
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
		$status_ids = array();
		$customStatus = false;
		$object = new Mo($this->db);

		if (isset($params->filter)) {
			foreach ($params->filter as $key => $filter) {
				if ($filter->property == 'id') $id = $filter->value;
				elseif ($filter->property == 'ref') $ref = $filter->value;
				elseif ($filter->property == 'status_id') array_push($status_ids, $filter->value);
			}
		}

		if (in_array(self::STATUS_VALIDATED_FULLY_PRODUCIBLE, $status_ids)
			|| in_array(self::STATUS_VALIDATED_NOT_PRODUCIBLE, $status_ids)
			|| in_array(self::STATUS_VALIDATED_PARTLY_PRODUCIBLE, $status_ids)
			|| in_array(self::STATUS_INPROGRESS_FULLY_PRODUCIBLE, $status_ids)
			|| in_array(self::STATUS_INPROGRESS_NOT_PRODUCIBLE, $status_ids)
			|| in_array(self::STATUS_INPROGRESS_PARTLY_PRODUCIBLE, $status_ids)
		) {
			$customStatus = true;
		}

		if (($id > 0) || ($ref != '')) {
			$result = $object->fetch($id, $ref);
			if ($result < 0) return ExtDirect::getDolError($result, $object->errors, $object->error);
			if ($result > 0) {
				$row = $this->getData($object);
				if ($customStatus) {
					$this->getProducible($row, $object->qty);
					$row->statusdisplay = html_entity_decode($this->LibStatut($row->status_id, false, 1));
				}
				array_push($results, $row);
			} else {
				return 0;
			}
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
							$result = $object->setStatut(Mo::STATUS_INPROGRESS, 0, '', 'MRP_MO_PRODUCED');
							if ($result < 0) {
								return ExtDirect::getDolError($result, $object->errors, $object->error);
							}
							break;
						case 3:
							$result = $object->setStatut(Mo::STATUS_PRODUCED, 0, '', 'MRP_MO_PRODUCED');
							if ($result < 0) {
								return ExtDirect::getDolError($result, $object->errors, $object->error);
							}
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
					$dir = $conf->mrp->dir_output . '/' . dol_sanitizeFileName($object->ref);
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
		if (!$this->_enabled) return NOTENABLEDERROR;
		if (!isset($this->_user->rights->mrp->read)) return PERMISSIONERROR;
		$result = new stdClass;
		$data = array();
		$rows = array();

		$statusFilterCount = 0;
		$ref = null;
		$contactTypeId = 0;
		$originId = 0;
		$status_id = array();
		$contentFilter = null;
		$customStatus = false;

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

		if (in_array(self::STATUS_VALIDATED_FULLY_PRODUCIBLE, $status_id)
			|| in_array(self::STATUS_VALIDATED_NOT_PRODUCIBLE, $status_id)
			|| in_array(self::STATUS_VALIDATED_PARTLY_PRODUCIBLE, $status_id)
			|| in_array(self::STATUS_INPROGRESS_FULLY_PRODUCIBLE, $status_id)
			|| in_array(self::STATUS_INPROGRESS_NOT_PRODUCIBLE, $status_id)
			|| in_array(self::STATUS_INPROGRESS_PARTLY_PRODUCIBLE, $status_id)
		) {
			$customStatus = true;
			// always load page from start to be able to sort on complete result
			$limit += $start;
			$start = 0;
		}

		$sqlFields = "SELECT s.nom, s.rowid AS socid, mo.rowid, mo.ref, mo.status, bom.ref as ref_bom, p.ref as ref_product, ea.activity_status, mo.date_start_planned, mol.role, mol.qty, mo.qty as product_qty";
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
		$sqlFromNotForTotal = " LEFT JOIN " . MAIN_DB_PREFIX . "mrp_production as mol ON mol.fk_mo = mo.rowid";
		$sqlWhere = " WHERE mo.entity IN (" . getEntity($this->table_element) . ')';
		$sqlWhereNotForTotal = " AND mol.role IN ('toproduce','produced')";

		if ($statusFilterCount > 0) {
			$sqlWhere .= " AND ( ";
			foreach ($status_id as $key => $status) {
				if ($status === self::STATUS_VALIDATED_FULLY_PRODUCIBLE || $status === self::STATUS_VALIDATED_NOT_PRODUCIBLE || $status === self::STATUS_VALIDATED_PARTLY_PRODUCIBLE) $status = self::STATUS_VALIDATED;
				if ($status === self::STATUS_INPROGRESS_FULLY_PRODUCIBLE || $status === self::STATUS_INPROGRESS_NOT_PRODUCIBLE || $status === self::STATUS_INPROGRESS_PARTLY_PRODUCIBLE) $status = self::STATUS_INPROGRESS;
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
			$fields = array('mo.ref', 'p.ref', 's.nom');
			$sqlWhere .= " AND ".natural_search($fields, $contentFilter, 0, 1);
		}

		$sqlOrder = " ORDER BY ";
		if (isset($params->sort)) {
			$sorterSize = count($params->sort);
			foreach ($params->sort as $key => $sort) {
				if (!empty($sort->property)) {
					if ($sort->property == 'status_id') {
						$sortfield = 'mo.status';
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

		$sql = $sqlFields . $sqlFrom . $sqlFromNotForTotal . $sqlWhere . $sqlWhereNotForTotal . $sqlOrder . $sqlLimit;

		$resql = $this->db->query($sql);

		if ($resql) {
			$num = $this->db->num_rows($resql);
			$qty_toproduce = array();
			$qty_produced = array();
			for ($i = 0; $i < $num; $i++) {
				$obj = $this->db->fetch_object($resql);
				$row = new stdClass;
				$row->id            = (int) $obj->rowid;
				$row->customer      = $obj->nom;
				$row->customer_id   = (int) $obj->socid;
				$row->ref           = $obj->ref;
				$row->ref_bom       = $obj->ref_bom;
				$row->status_id = (int) $obj->status;
				if ($customStatus && $obj->status > self::STATUS_DRAFT && $obj->fk_status < self::STATUS_PRODUCED) {
					$this->getProducible($row, $obj->product_qty);
				}
				$row->statusdisplay = html_entity_decode($this->LibStatut($row->status_id, 1));
				$row->status        = $obj->activity_status;
				$row->ref_product   = $obj->ref_product;
				$row->date_start_planned  = $this->db->jdate($obj->date_start_planned);
				if ($obj->role == 'toproduce') $qty_toproduce[$row->id] = $obj->qty;
				if ($obj->role == 'produced') $qty_produced[$row->id] = $obj->qty;
				$rows[$row->id] = $row;
			}
			foreach ($rows as $key => &$row) {
				$row->qty_toproduce = $qty_toproduce[$key];
				$row->qty_produced = $qty_produced[$key];
				if ($customStatus) {
					if (in_array($row->status_id, $status_id)) {
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
			return SQLERROR;
		}
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *	Return label of status
	 *
	 *	@param		int		$status      	  Id status
	 *  @param      int		$mode        	  1=Short label
	 *  @return     string					  Label of status
	 */
	public function LibStatut($status, $mode = 0)
	{
		// phpcs:enable
		global $langs, $conf;

		if ($status == self::STATUS_CANCELED) {
			$labelStatusShort = $langs->transnoentitiesnoconv('Canceled');
		} elseif ($status == self::STATUS_DRAFT) {
			$labelStatusShort = $langs->transnoentitiesnoconv('Draft');
		} elseif ($status == self::STATUS_VALIDATED) {
			$labelStatusShort = $langs->transnoentitiesnoconv('Validated');
		} elseif ($status == self::STATUS_INPROGRESS) {
			$labelStatusShort = $langs->transnoentitiesnoconv('InProgress');
		} elseif ($status == self::STATUS_PRODUCED) {
			$labelStatusShort = $langs->transnoentitiesnoconv('StatusMOProduced');
		} elseif ($status == self::STATUS_VALIDATED_FULLY_PRODUCIBLE) {
			$labelStatusShort = $langs->transnoentitiesnoconv('StatusMoValidatedFullyProducibleShort');
		} elseif ($status == self::STATUS_VALIDATED_NOT_PRODUCIBLE) {
			$labelStatusShort = $langs->transnoentitiesnoconv('StatusMoValidatedNotProducibleShort');
		} elseif ($status == self::STATUS_VALIDATED_PARTLY_PRODUCIBLE) {
			$labelStatusShort = $langs->transnoentitiesnoconv('StatusMoValidatedPartlyProducibleShort');
		} elseif ($status == self::STATUS_INPROGRESS_FULLY_PRODUCIBLE) {
			$labelStatusShort = $langs->transnoentitiesnoconv('StatusMoInprogessFullyProducibleShort');
		} elseif ($status == self::STATUS_INPROGRESS_NOT_PRODUCIBLE) {
			$labelStatusShort = $langs->transnoentitiesnoconv('StatusMoInprogessNotProducibleShort');
		} elseif ($status == self::STATUS_INPROGRESS_PARTLY_PRODUCIBLE) {
			$labelStatusShort = $langs->transnoentitiesnoconv('StatusMoInprogessPartlyProducibleShort');
		} else {
			$labelStatusShort = '';
		}

		return $labelStatusShort;
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
			$result = $this->LibStatut($statut);
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
	private function getData($object)
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
			} elseif ($field == 'qty') {
				$data->qty_toproduce = $object->{$field};
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

	/**
	 * Get producible status
	 *
	 * @param object $row row of resultset
	 * @param float $productQty if only 1 item to produce we use virtual stock for partial producible
	 * @return void
	 */
	public function getProducible(&$row, $productQty)
	{
		dol_include_once('/extdirect/class/ExtDirectProduct.class.php');
		$product = new ExtDirectProduct($this->db);
		$notproducable = 0;
		$notfullyproducable = 0;
		$qtyToProduce = 0;
		$qtyProduced = 0;
		$productToConsume = array();
		$this->id = $row->id;
		if (ExtDirect::checkDolVersion(0, '16.0')) {
			$this->getLinesArray();
		} else {
			$this->getPatchedLinesArray();
		}
		foreach ($this->lines as $line) {
			if ($line->fk_product > 0) {
				// store to consume and consumed product
				if ($line->role == 'toconsume' || $line->role == 'consumed') {
					$isConsumeLine = true;
				} else {
					$isConsumeLine = false;
				}
				if (!isset($qtyToConsume[$line->id])) $qtyToConsume[$line->id] = 0;
				if (!isset($qtyConsumed[$line->fk_mrp_production])) $qtyConsumed[$line->fk_mrp_production] = 0;
				if (!isset($productToConsume[$line->id]) && $isConsumeLine) $productToConsume[$line->id] = $line->fk_product;
				if ($line->role == 'toconsume') {
					$qtyToConsume[$line->id] += $line->qty;
				} elseif ($line->role == 'consumed') {
					$qtyConsumed[$line->fk_mrp_production] += $line->qty;
				}
				// store toproduce and produced
				if ($line->role == 'toproduce') {
					$qtyToProduce += $line->qty;
				} elseif ($line->role == 'produced') {
					$qtyProduced += $line->qty;
				}
				// Get product stock and store it
				if (empty($this->_productstock_cache[$line->fk_product])) {
					$product->fetch($line->fk_product);
					$product->load_stock();
					$this->_productstock_cache[$line->fk_product]['stock_reel'] = $product->stock_reel;
					$this->_productstock_cache[$line->fk_product]['stock_virtual'] = $product->stock_theorique;
					$this->_productstock_cache[$line->fk_product]['type'] = $product->type;
				}
			}
		}
		$qtyToComplete = $qtyToProduce - $qtyProduced;
		if ($qtyToComplete > 0) {
			foreach ($productToConsume as $line_id => $fk_product) {
				if ($this->_productstock_cache[$fk_product]['type'] == 0) {
					$qtyToProduce = $qtyToConsume[$line_id] - $qtyConsumed[$line_id];
					if ($qtyToProduce > 0) {
						if (!isset($qtyStockAvailableToProduce[$fk_product])) {
							$qtyStockAvailableToProduce[$fk_product] = $this->_productstock_cache[$fk_product]['stock_reel'];
						} else {
							$qtyStockAvailableToProduce[$fk_product] -= $qtyToProduce / $qtyToComplete;
						}
						if ($productQty > 1) {
							// situation for more item to produce (if stock enough for one item we can start production)
							if ($qtyToProduce / $qtyToComplete > $qtyStockAvailableToProduce[$fk_product]) {
								$notproducable++;
							} elseif ($qtyToProduce > $qtyStockAvailableToProduce[$fk_product] && $qtyToProduce / $qtyToComplete <= $qtyStockAvailableToProduce[$fk_product]) {
								$notfullyproducable++;
							}
						} else {
							// situation for 1 item to produce (if stock is soon expected we can start production)
							if (!isset($qtyVirtualStockAvailableToProduce[$fk_product])) {
								$qtyVirtualStockAvailableToProduce[$fk_product] = $this->_productstock_cache[$fk_product]['stock_virtual'];
							} else {
								$qtyVirtualStockAvailableToProduce[$fk_product] -= $qtyToProduce / $qtyToComplete;
							}
							if ($qtyToProduce > $qtyStockAvailableToProduce[$fk_product]) {
								$notfullyproducable++;
							}
							if ($qtyToProduce > $qtyVirtualStockAvailableToProduce[$fk_product]) {
								$notproducable++;
							}
						}
					}
				}
			}
		}
		if ($notproducable > 0) {
			if ($row->status_id == self::STATUS_VALIDATED) $row->status_id = self::STATUS_VALIDATED_NOT_PRODUCIBLE;
			elseif ($row->status_id == self::STATUS_INPROGRESS) $row->status_id = self::STATUS_INPROGRESS_NOT_PRODUCIBLE;
		} elseif ($notfullyproducable > 0) {
			if ($row->status_id == self::STATUS_VALIDATED) $row->status_id = self::STATUS_VALIDATED_PARTLY_PRODUCIBLE;
			elseif ($row->status_id == self::STATUS_INPROGRESS) $row->status_id = self::STATUS_INPROGRESS_PARTLY_PRODUCIBLE;
		} else {
			if ($row->status_id == self::STATUS_VALIDATED) $row->status_id = self::STATUS_VALIDATED_FULLY_PRODUCIBLE;
			elseif ($row->status_id == self::STATUS_INPROGRESS) $row->status_id = self::STATUS_INPROGRESS_FULLY_PRODUCIBLE;
		}
	}

	/**
	 * 	Create an array of lines, patch on limit. Set to value because on dolibarr < 16 no lines fetched when limit 0.
	 * 	@param string $rolefilter string lines role filter
	 * 	@return array|int		array of lines if OK, <0 if KO
	 */
	public function getPatchedLinesArray($rolefilter = '')
	{
		$this->lines = array();

		$objectline = new MoLine($this->db);

		$TFilters = array('customsql'=>'fk_mo = '.((int) $this->id));
		if (!empty($rolefilter)) $TFilters['role'] = $rolefilter;
		$result = $objectline->fetchAll('ASC', 'position', 10000, 0, $TFilters);

		if (is_numeric($result)) {
			$this->error = $objectline->error;
			$this->errors = $objectline->errors;
			return $result;
		} else {
			$this->lines = $result;
			return $this->lines;
		}
	}

	/**
	 *    Load lines from object
	 *
	 *    @param    stdClass    $params     filter with elements:
	 *                                      origin_id   Id of object to load lines from
	 *    @return     stdClass result data or -1
	 */
	public function extReadLines(stdClass $params)
	{
		global $conf;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->mrp->read)) return PERMISSIONERROR;
		$results = array();
		$row = new stdClass;
		$origin_id = 0;
		$photoSize = 'mini';
		$roleFilterCount = 0;
		$roles = array();
		$object = new Mo($this->db);

		if (isset($params->filter)) {
			foreach ($params->filter as $key => $filter) {
				if ($filter->property == 'origin_id') $origin_id = $filter->value;
				if ($filter->property == 'role') $roles[$roleFilterCount++] = $filter->value;
				if ($filter->property == 'warehouse_id') $warehouse_id = $filter->value;
				if ($filter->property == 'photo_size' && !empty($filter->value)) $photoSize = $filter->value;
			}
		}

		if (empty($roles)) $roles = array('toconsume', 'consumed');

		if ($origin_id > 0) {
			$product = new ExtDirectProduct($this->_user->login);
			$result = $object->fetch($origin_id);
			if ($result < 0) return ExtDirect::getDolError($result, $object->errors, $object->error);

			if (is_array($object->lines) && count($object->lines) > 0) {
				foreach ($object->lines as $key => $line) {
					if (in_array($line->role, $roles)) {
						$result = $product->fetch($line->fk_product);
						if ($result > 0) {
							$product->fetch_barcode();
							$row = $this->getLineData($line, $object, $product, $photoSize);
							$result = $product->load_stock('novirtual, warehouseopen, warehouseinternal');
							if ($result < 0) return ExtDirect::getDolError($result, $product->errors, $product->error);
							if ($warehouse_id > 0) {
								$row->warehouse_id = $warehouse_id;
								$row->id = $row->line_id . '_' . $warehouse_id;
								$row->stock = (float) $product->stock_warehouse[$warehouse_id]->real;
								$row->total_stock = $product->stock_reel;
								if (empty($conf->productbatch->enabled)) {
									array_push($results, $row);
								} else {
									$row->batch_id = 0;
									$row->has_batch = $product->status_batch;
									if ($row->has_batch > 0) {
										if (($res = $product->fetchBatches($results, $row, $row->id, $warehouse_id, $product->stock_warehouse[$warehouse_id]->id, false, null, $line->batch, $photoSize)) < 0) return $res;
									} else {
										array_push($results, $row);
									}
								}
							} elseif ($warehouse_id < 0) {
								// no warehouse and batch info needed
								$row->warehouse_id = 0;
								$row->id = $row->line_id . '_' . $row->warehouse_id;
								$row->stock = $product->stock_reel;
								$row->total_stock = $product->stock_reel;
								array_push($results, $row);
							} else {
								if (count($product->stock_warehouse) > 0) {
									foreach ($product->stock_warehouse as $warehouse=>$stock_warehouse) {
										$row = $this->getLineData($line, $object, $product, $photoSize);
										$row->warehouse_id = $warehouse;
										$row->id = $row->line_id . '_' . $warehouse;
										$row->stock = (float) $stock_warehouse->real;
										$row->total_stock = $product->stock_reel;
										if (empty($conf->productbatch->enabled)) {
											array_push($results, $row);
										} else {
											$row->batch_id = 0;
											$row->has_batch = $product->status_batch;
											if ($row->has_batch > 0) {
												if (($res = $product->fetchBatches($results, $row, $row->id, $warehouse, $stock_warehouse->id, false, null, $line->batch, $photoSize)) < 0) return $res;
											} else {
												array_push($results, $row);
											}
										}
									}
								} else {
									$row->warehouse_id = 0;
									$row->id = $row->line_id . '_' . 0;
									$row->stock = (float) $product->stock_reel;
									$row->total_stock = $product->stock_reel;
									array_push($results, $row);
								}
							}
						} elseif ($result < 0) {
							return ExtDirect::getDolError($result, $object->errors, $object->error);
						} else {
							// free line
							$row->warehouse_id = 0;
							$row->id = $row->line_id . '_' . 0;
							$row->stock = 0;
							$row->total_stock = 0;
							$row = $this->getLineData($line, $object);
							array_push($results, $row);
						}
					}
				}
			}
		}
		return $results;
	}

	/**
	 * Ext.direct method to Create lines
	 *
	 * !!deliver $param sorted by origin_line_id
	 *
	 * @param unknown_type $param object or object array with line record
	 * @return result data or -1
	 */
	public function extCreateLines($param)
	{
		global $conf;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->mrp->write)) return PERMISSIONERROR;
		$notrigger = 0;
		$paramArray = ExtDirect::toArray($param);
		$res = 0;
		$result = 0;

		$line = new MoLine($this->db);
		foreach ($paramArray as &$params) {
			// prepare fields
			$this->prepareLineFields($params, $line);
			if ($params->origin_id > 0) {
				if (($result = $line->create($this->_user)) < 0) return ExtDirect::getDolError($result, $line->errors, $line->error);
				$params->line_id = $line->id;
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
	 * Ext.direct method to update lines
	 *
	 * @param unknown_type $param object or object array with shipment record
	 * @return result data or -1
	 */
	public function extUpdateLines($param)
	{
		global $langs;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->mrp->write)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($param);
		$pos = 0;
		$object = new Mo($this->db);
		foreach ($paramArray as &$params) {
			// prepare fields
			if (($result = $object->fetch($params->origin_id)) < 0) {
				return ExtDirect::getDolError($result, $this->errors, $this->error);
			}
			$qtytoprocess = $params->qty_toprocess;
			// Add a protection to refuse updating if already produced
			if ($object->statut < Mo::STATUS_PRODUCED && $params->line_id > 0) {
				$line = new MoLine($this->db);
				$stockmove = new MouvementStock($this->db);
				if (ExtDirect::checkDolVersion(0, '', '14.0')) {
					$stockmove->origin = $object;
				} else {
					$stockmove->setOrigin($object->element, $object->id);
				}
				if (($result = $line->fetch($params->line_id)) < 0) {
					return ExtDirect::getDolError($result, $line->errors, $line->error);
				}
				// prepare fields
				$this->prepareLineFields($params, $line);
				if ($params->origin_id > 0) {
					$result = $object->fetch($params->origin_id);
					if ($result < 0) return ExtDirect::getDolError($result, $object->errors, $object->error);
					if ($result > 0) {
						if (!empty($qtytoprocess) && $params->warehouse_id > 0) {
							$inventorylabel = ($params->inventorylabel ? $params->inventorylabel : $langs->trans("ProductionForRef", $object->ref));
							$inventorycode = ($params->inventorycode ? $params->inventorycode : $langs->trans("ProductionForRef", $object->ref));
							if ($qtytoprocess > 0) {
								$idstockmove = $stockmove->livraison($this->_user, $line->fk_product, $params->warehouse_id, $qtytoprocess, 0, $inventorylabel, dol_now(), $params->eatby, $params->sellby, $params->batch, $params->batch_id, $inventorycode);
							}
							if ($qtytoprocess < 0) {
								$idstockmove = $stockmove->reception($this->_user, $line->fk_product, $params->warehouse_id, $qtytoprocess, 0, $inventorylabel, dol_now(), $params->eatby, $params->sellby, $params->batch, $params->batch_id, $inventorycode);
							}
							if ($idstockmove > 0) {
								// Record consumption
								$moline = new MoLine($this->db);
								$moline->fk_mo = $object->id;
								$moline->position = $pos;
								$moline->fk_product = $line->fk_product;
								$moline->fk_warehouse = $line->fk_warehouse;
								$moline->qty = $qtytoprocess;
								$moline->batch = $line->batch;
								$moline->role = 'consumed';
								$moline->fk_mrp_production = $line->id;
								$moline->fk_stock_movement = $idstockmove;
								$line->fk_user_creat = $this->_user->id;

								$resultmoline = $moline->create($this->_user);
								if ($resultmoline <= 0) {
									return ExtDirect::getDolError($resultmoline, $moline->errors, $moline->error);
								}

								$pos++;
							} else {
								return ExtDirect::getDolError($idstockmove, $stockmove->errors, $stockmove->error);
							}
						} elseif (empty($qtytoprocess)) {
							$resultmoline = $line->update($this->_user);
							if ($resultmoline <= 0) {
								return ExtDirect::getDolError($resultmoline, $line->errors, $line->error);
							}
						} else {
							return PARAMETERERROR;
						}
					} else {
						return PARAMETERERROR;
					}
				} else {
					return PARAMETERERROR;
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
	 * Ext.direct method to destroy lines
	 *
	 * @param unknown_type $param object or object array with shipment record
	 * @return result data or -1
	 */
	public function extDestroyLines($param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->mrp->write)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($param);
		$object = new Mo($this->db);

		foreach ($paramArray as &$params) {
			// prepare fields
			$idArray = explode('_', $params->id);
			$lineId = $idArray[0];
			$line = new MoLine($this->db);
			$line->fetch($lineId);
			if (($result = $object->fetch($line->fk_mo)) < 0) {
				return ExtDirect::getDolError($result, $this->errors, $this->error);
			}
			// Add a protection to refuse deleting if object in produced status
			if ($object->status < self::STATUS_PRODUCED && $lineId) {
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
	 * private method to copy package fields into dolibarr object
	 *
	 * @param stdclass $params object with fields
	 * @param MoLine $line line object with fields
	 * @return null
	 */
	private function prepareLineFields($params, MoLine &$line)
	{
		foreach ($line->fields as $field => $info) {
			if ($field == 'fk_product') {
				isset($params->product_id) ? $line->{$field} = $params->product_id : (isset($line->{$field}) ? null : $line->{$field} = null);
			} elseif ($field == 'fk_user_modif') {
				$line->{$field} = $this->_user->id;
			} elseif ($field == 'origin_id') {
				isset($params->origin_line_id) ? $line->{$field} = $params->origin_line_id : (isset($line->{$field}) ? null : $line->{$field} = null);
			} elseif ($field == 'fk_mo') {
				isset($params->origin_id) ? $line->{$field} = $params->origin_id : (isset($line->{$field}) ? null : $line->{$field} = null);
			} elseif ($field == 'fk_warehouse') {
				isset($params->warehouse_id) ? $line->{$field} = $params->warehouse_id : (isset($line->{$field}) ? null : $line->{$field} = null);
			} elseif ($field == 'origin_type') {
				isset($params->origin_line_type) ? $line->{$field} = $params->origin_line_type : (isset($line->{$field}) ? null : $line->{$field} = null);
			} elseif ($field == 'qty') {
				!empty($params->qty_asked) ? $line->{$field} = $params->qty_asked : (isset($line->{$field}) ? null : $line->{$field} = null);
			} else {
				isset($params->{$field}) ? $line->{$field} = $params->{$field} : (isset($line->{$field}) ? null : $line->{$field} = null);
			}
			/*$line->id = $params->line_id;
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
			}*/
		}
	}

	/**
	 * get line data from object
	 *
	 * @param MoLine	$object		object
	 * @param Mo				$mo			Manufacturing order object
	 * @param ExtDirectProduct	$product	product object
	 * @param String			$photoSize	format size of photo 'mini', 'small' or 'full' to add to line
	 * @return stdClass object with data
	 */
	private function getLineData(MoLine $object, Mo $mo, ExtDirectProduct $product = null, $photoSize = '')
	{
		$data = new stdClass;
		$myUser = new User($this->db);
		$originObject = null;

		foreach ($object->fields as $field => $info) {
			if ($field == 'rowid') {
				$data->line_id = (int) $object->id;
			} elseif ($field == 'fk_warehouse') {
				$data->warehouse_id = (int) $object->{$field};
			} elseif ($field == 'fk_product') {
				$data->product_id = (int) $object->{$field};
				if ($product) {
					$data->ref_product = $product->ref;
					$data->product_label = $product->label;
					$data->product_desc = $product->description;
					$data->product_type = $product->type;
					$data->barcode = $product->barcode ? $product->barcode : '';
					$data->barcode_type = $product->barcode_type ? $product->barcode_type : 0;
					$data->barcode_with_checksum = $product->barcode ? $product->fetchBarcodeWithChecksum($product) : '';
					$data->has_photo = 0;
					if (!empty($photoSize)) {
						$product->fetchPhoto($data, $photoSize);
					}
				}
			} elseif ($field == 'fk_user_creat') {
				$data->user_id = (int) $object->{$field};
				if ($data->user_id > 0 && $myUser->fetch($data->user_id) > 0) {
					$data->user_name = $myUser->firstname . ' ' . $myUser->lastname;
				}
			} elseif ($field == 'tms') {
				$data->date_modification = $object->{$field};
			} elseif ($field == 'qty') {
				$data->qty_consumed = 0;
				$data->qty_produced = 0;
				if ($object->role == 'toconsume') {
					$data->qty_asked = $object->qty;
					$consumedLines = $mo->fetchLinesLinked('consumed', $object->id);
					foreach ($consumedLines as $line2) {
						$data->qty_consumed += $line2['qty'];
					}
				} elseif ($object->role == 'toproduce') {
					$data->qty_asked = $object->qty;
					$consumedLines = $mo->fetchLinesLinked('produced', $object->id);
					foreach ($consumedLines as $line2) {
						$data->qty_produced += $line2['qty'];
					}
				} elseif ($object->role == 'consumed') {
					$toConsumeLine = new MoLine($this->db);
					$toConsumeLine->fetch($object->fk_mrp_production);
					$data->qty_asked = $toConsumeLine->qty;
					$data->qty_consumed = $object->qty;
				} elseif ($object->role == 'produced') {
					$toConsumeLine = new MoLine($this->db);
					$toConsumeLine->fetch($object->fk_mrp_production);
					$data->qty_asked = $toConsumeLine->qty;
					$data->qty_produced = $object->qty;
				}
			} elseif ($field == 'origin_type') {
				$data->origin_line_type = $object->{$field};
			} elseif ($field == 'tms') {
				$data->date_modification = $object->{$field};
			} else {
				$data->{$field} = $object->{$field};
			}
		}
		$data->origin_id = $mo->id;
		$data->origin_line_id = $object->id;
		$data->origin_line = $object->origin;
		if (!empty($data->origin_line) && !empty($data->origin_line_id)) {
			if ($data->origin_line == 'bom') {
				$originObject = new BOMLine($this->db);
				if ($originObject->fetch($data->origin_line_id) > 0) {
					$data->description = $originObject->description;
				}
			}
		}

		return $data;
	}
}
