<?php

/**
 * Copyright (C) 2025       Francis Appels <francis.appels@z-application.com>
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
 * @file       ExtDirectInventory.class.php
 * @brief      Class for ExtDirect Inventory
 * @ingroup    Dolibarr
 *
 */

/** ExtDirectInventory class
 *  This class is used to manage inventory operations in Dolibarr using ExtDirect.

 */

require_once DOL_DOCUMENT_ROOT . '/product/inventory/class/inventory.class.php';
dol_include_once('/extdirect/class/extdirect.class.php');
dol_include_once('/extdirect/class/ExtDirectProduct.class.php');

class ExtDirectInventory extends Inventory
{
	private $_user;
	private $_inventoryConstants = array('INVENTORY_INCLUDE_SUB_WAREHOUSE');
	private $_enabled = false;

	/**
	 * end status to allow status itteration
	 */
	const STATUS_END = 20;

	/** Constructor
	 *
	 * @param string $login user name
	 */
	public function __construct($login)
	{
		global $langs, $user, $db, $conf, $mysoc;

		if (!empty($login)) {
			/** @var DoliDB $db */
			if ((is_object($login) && get_class($db) == get_class($login)) || $user->id > 0 || $user->fetch('', $login, '', 1) > 0) {
				$user->getrights();
				$this->_enabled = !empty($conf->stock->enabled) && isset($user->rights->stock->lire);
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
				$langs->load("products");
				$langs->load("stocks");
				$langs->load("productbatch");
				$langs->load("other");
				parent::__construct($db);
			}
		}
	}

	/**
	 *	Load inventory related constants
	 *
	 *	@param			stdClass	$params		filter with elements
	 *		                                    constant	name of specific constant
	 *
	 *	@return			stdClass result data with specific constant value
	 */
	public function readConstants(stdClass $params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->stock->lire)) return PERMISSIONERROR;

		$results = ExtDirect::readConstants($this->db, $params, $this->_user, $this->_inventoryConstants);

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
		if (!isset($this->_user->rights->stock->lire)) return PERMISSIONERROR;
		$results = array();
		$id = 0;
		$object = new Inventory($this->db);

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
		if (!isset($this->_user->rights->stock->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);
		$object = new Inventory($this->db);

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
		if (!isset($this->_user->rights->stock->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);
		$object = new Inventory($this->db);

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
		if (!isset($this->_user->rights->stock->creer)) return PERMISSIONERROR;
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
		if (!isset($this->_user->rights->stock->lire)) return PERMISSIONERROR;

		$results = array();
		$id = 0;
		$ref = '';
		$status_ids = array();
		$object = new Inventory($this->db);

		if (isset($params->filter)) {
			foreach ($params->filter as $filter) {
				if ($filter->property == 'id') $id = $filter->value;
				elseif ($filter->property == 'ref') $ref = $filter->value;
				elseif ($filter->property == 'status_id') array_push($status_ids, $filter->value);
			}
		}

		if (($id > 0) || ($ref != '')) {
			$result = $object->fetch($id, $ref);
			if ($result < 0) return ExtDirect::getDolError($result, $object->errors, $object->error);
			if ($result > 0) {
				$row = $this->getData($object);
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
		if (!isset($this->_user->rights->stock->creer)) return PERMISSIONERROR;

		$paramArray = ExtDirect::toArray($param);
		$object = new Inventory($this->db);

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
							// PDF generating (no inventory pdf for the moment)
							/*
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
							}*/
							break;
						case 2:
							$result = $object->setStatut(Inventory::STATUS_RECORDED);
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
		if (!isset($this->_user->rights->stock->creer)) return PERMISSIONERROR;

		$paramArray = ExtDirect::toArray($param);
		$object = new Inventory($this->db);

		foreach ($paramArray as &$params) {
			if ($params->id) {
				$id = $params->id;
				if ($id > 0) {
					$result = $object->fetch($id);
					if ($result < 0) return ExtDirect::getDolError($result, $object->errors, $object->error);
					if ($result > 0) {
						// delete
						if (($result = $object->delete($this->_user)) < 0) return ExtDirect::getDolError($result, $object->errors, $object->error);
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
	 * Ext.direct method to upload file for object
	 *
	 * @param unknown_type $params object or object array with uploaded file(s)
	 * @return Array    ExtDirect response message
	 */
	public function fileUpload($params)
	{
		global $conf;
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->stock->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);
		$dir = null;
		$object = new Inventory($this->db);

		foreach ($paramArray as &$param) {
			if (isset($param['extTID'])) {
				$id = $param['extTID'];
				if ($object->fetch($id)) {
					$object->fetch_thirdparty();
					$dir = $conf->stock->multidir_output[isset($object->entity) ? $object->entity : 1] . '/' . dol_sanitizeFileName($object->ref);
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
		if (!isset($this->_user->rights->stock->lire)) return PERMISSIONERROR;
		$result = new stdClass;
		$data = array();
		$rows = array();

		$statusFilterCount = 0;
		$ref = null;
		$status_id = array();
		$contentFilter = null;
		$sorterSize = 0;

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
				elseif ($filter->property == 'content') $contentFilter = $filter->value;
			}
		}

		$sqlFields = "SELECT inv.rowid, inv.ref, inv.title, inv.status,e.rowid as warehouse_id, e.ref as ref_warehouse, p.rowid as product_id, p.ref as ref_product, ea.activity_status, inv.date_inventory, inv.date_validation";
		$sqlFrom = " FROM " . MAIN_DB_PREFIX . "inventory as inv";
		$sqlFrom .= " LEFT JOIN " . MAIN_DB_PREFIX . "entrepot as e ON inv.fk_warehouse = e.rowid";
		$sqlFrom .= " LEFT JOIN " . MAIN_DB_PREFIX . "product as p ON inv.fk_product = p.rowid";
		$sqlFrom .= " LEFT JOIN ("; // get latest extdirect activity status for commande to check if locked
		$sqlFrom .= "   SELECT ma.activity_id, ma.maxrow AS rowid, ea.status as activity_status";
		$sqlFrom .= "   FROM (";
		$sqlFrom .= "    SELECT MAX( rowid ) AS maxrow, activity_id";
		$sqlFrom .= "    FROM " . MAIN_DB_PREFIX . "extdirect_activity";
		$sqlFrom .= "    GROUP BY activity_id";
		$sqlFrom .= "   ) AS ma, " . MAIN_DB_PREFIX . "extdirect_activity AS ea";
		$sqlFrom .= "   WHERE ma.maxrow = ea.rowid";
		$sqlFrom .= " ) AS ea ON inv.rowid = ea.activity_id";
		$sqlFromNotForTotal = "";
		$sqlWhere = " WHERE inv.entity IN (" . getEntity($this->element) . ')';
		$sqlWhereNotForTotal = "";

		if ($statusFilterCount > 0) {
			$sqlWhere .= " AND ( ";
			foreach ($status_id as $key => $status) {
				$sqlWhere  .= "inv.status = " . $status;
				if ($key < ($statusFilterCount - 1)) $sqlWhere  .= " OR ";
			}
			$sqlWhere .= ")";
		}
		if ($ref) {
			$sqlWhere .= " AND inv.ref = '" . $ref . "'";
		}

		if ($contentFilter) {
			$fields = array('inv.ref', 'p.ref', 'inv.title');
			$sqlWhere .= " AND ".natural_search($fields, $contentFilter, 0, 1);
		}

		$sqlOrder = " ORDER BY ";
		if (isset($params->sort)) {
			$sorterSize = count($params->sort);
			foreach ($params->sort as $key => $sort) {
				if (!empty($sort->property)) {
					if ($sort->property == 'status_id') {
						$sortfield = 'inv.status';
					} elseif ($sort->property == 'ref') {
						$sortfield = 'inv.ref';
					} elseif ($sort->property == 'ref_product') {
						$sortfield = 'p.ref';
					} elseif ($sort->property == 'ref_warehouse') {
						$sortfield = 'e.ref';
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
			$sqlOrder .= "inv.date_creation DESC";
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
			for ($i = 0; $i < $num; $i++) {
				$obj = $this->db->fetch_object($resql);
				$row = new stdClass;
				$row->id            = (int) $obj->rowid;
				$row->warehouse_id  = (int) $obj->warehouse_id;
				$row->ref_warehouse = $obj->ref_warehouse;
				$row->ref_product   = $obj->ref_product;
				$row->product_id    = (int) $obj->product_id;
				$row->ref           = $obj->ref;
				$row->description    = $obj->title;
				$row->status_id     = (int) $obj->status;
				$row->statusdisplay = html_entity_decode($this->LibStatut($row->status_id, 1));
				$row->status        = $obj->activity_status;
				$row->date_inventory= $this->db->jdate($obj->date_inventory);
				$row->date_validation= $this->db->jdate($obj->date_validation);
				$rows[$row->id] = $row;
			}
			foreach ($rows as $key => &$row) {
				array_push($data, $row);
			}
			$this->db->free($resql);
			if (!empty($params->sort)) $data = ExtDirect::resultSort($data, $params->sort);
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
	 * get data from object
	 *
	 * @param Inventory	$object	object
	 * @return stdClass object with data
	 */
	private function getData($object)
	{
		$data = new stdClass;
		$myUser = new User($this->db);
		$warehouse = new Entrepot($this->db);
		$product = new Product($this->db);

		foreach ($object->fields as $field => $info) {
			if ($field == 'rowid') {
				$data->id = (int) $object->id;
			} elseif ($field == 'fk_warehouse') {
				$data->warehouse_id = (int) $object->{$field};
				if ($warehouse->fetch($data->warehouse_id) > 0) {
					$data->ref_warehouse = $warehouse->ref;
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
			} elseif ($field == 'title') {
				$data->description = $object->{$field};
			}  elseif ($field == 'tms') {
				$data->date_modification = $object->{$field};
			} else {
				$data->{$field} = $object->{$field};
			}
		}

		return $data;
	}

	/**
	 * private method to inventory fields into dolibarr object
	 *
	 * @param stdclass $params object with fields
	 * @return null
	 */
	private function prepareFields($params)
	{
		foreach ($this->fields as $field => $info) {
			if ($field == 'fk_warehouse') {
				isset($params->warehouse_id) ? $this->{$field} = $params->warehouse_id : (isset($this->{$field}) ? null : $this->{$field} = null);
			} elseif ($field == 'fk_product') {
				isset($params->product_id) ? $this->{$field} = $params->product_id : (isset($this->{$field}) ? null : $this->{$field} = null);
			} elseif ($field == 'fk_user_creat') {
				isset($params->user_id) ? $this->{$field} = $params->user_id : (isset($this->{$field}) ? null : $this->{$field} = null);
			} elseif ($field == 'title') {
				isset($params->description) ? $this->{$field} = $params->description : (isset($this->{$field}) ? null : $this->{$field} = null);
			} else {
				isset($params->{$field}) ? $this->{$field} = $params->{$field} : (isset($this->{$field}) ? null : $this->{$field} = null);
			}
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
		if (!isset($this->_user->rights->stock->lire)) return PERMISSIONERROR;
		$result = new stdClass;
		$data = array();
		$rows = array();
		$product_id = 0;
		$photoSize = 'mini';
		$warehouse_id = 0;
		$object = new Inventory($this->db);

		$includeTotal = true;

		if (isset($params->limit)) {
			$limit = $params->limit;
			$start = $params->start;
		}
		if (isset($params->include_total)) {
			$includeTotal = $params->include_total;
		}

		if (isset($params->filter)) {
			foreach ($params->filter as $filter) {
				if ($filter->property == 'origin_id') $origin_id = $filter->value;
				if ($filter->property == 'product_id') $product_id = $filter->value;
				if ($filter->property == 'warehouse_id') $warehouse_id = $filter->value;
				if ($filter->property == 'photo_size' && !empty($filter->value)) $photoSize = $filter->value;
			}
		}

		if ($origin_id > 0) {
			$product = new ExtDirectProduct($this->_user->login);
			$object->fetch($origin_id);
			$sqlFields = 'SELECT id.rowid as id, id.datec, id.tms as date_modification, id.fk_inventory, id.fk_warehouse,';
			$sqlFields .= ' id.fk_product, id.batch, id.qty_stock, id.qty_view, id.qty_regulated, id.pmp_real, id.pmp_expected';
			$sqlFrom = ' FROM '.MAIN_DB_PREFIX.'inventorydet as id';
			$sqlWhere = ' WHERE id.fk_inventory = '.((int) $origin_id);
			$sqlOrder = ' ORDER BY id.rowid';
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
					$line = $this->db->fetch_object($resql);
					if ($warehouse_id > 0 && $warehouse_id != $line->fk_warehouse) continue;
					if ($product_id > 0 && $product_id != $line->fk_product) continue;
					$product->fetch($line->fk_product);
					$row = $this->getLineData($line, $object, $product, $photoSize);
					$rows[$row->id] = $row;
				}
			}
			foreach ($rows as &$row) {
				array_push($data, $row);
			}
			$this->db->free($resql);
			if (!empty($params->sort)) $data = ExtDirect::resultSort($data, $params->sort);
			if ($includeTotal) {
				$result->total = $total;
				$result->data = $data;
				return $result;
			} else {
				return $data;
			}
		}
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
		if (!isset($this->_user->rights->stock->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($param);
		$result = 0;

		$line = new InventoryLine($this->db);
		$line->datec = dol_now();
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
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->stock->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($param);
		$object = new Inventory($this->db);
		foreach ($paramArray as &$params) {
			// prepare fields
			if (($result = $object->fetch($params->origin_id)) < 0) {
				return ExtDirect::getDolError($result, $this->errors, $this->error);
			}
			// Add a protection to refuse updating if already produced
			if ($object->status == Inventory::STATUS_VALIDATED && $params->line_id > 0) {
				$line = new InventoryLine($this->db);
				$line->fetch($params->line_id);
				// prepare fields
				$diff = $this->prepareLineFields($params, $line);
				if ($diff) {
					$result = $line->update($this->_user);
					if ($result < 0) return ExtDirect::getDolError($result, $line->errors, $line->error);
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
		if (!isset($this->_user->rights->stock->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($param);
		$object = new Inventory($this->db);

		foreach ($paramArray as &$params) {
			// prepare fields
			$lineId = $params->id;
			$object->fetch($params->origin_id);
			$result = $object->deleteLine($this->_user, $lineId);
			if ($result < 0) return ExtDirect::getDolError($result, $object->errors, $object->error);
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
	 * @param InventoryLine $line line object with fields
	 * @return true if fields are different
	 * @return false if fields are the same
	 */
	private function prepareLineFields($params, InventoryLine &$line)
	{
		$diff = false;
		foreach ($line->fields as $field => $info) {
			if ($field == 'fk_product') {
				$diff = ExtDirect::prepareField($diff, $params, $line, 'product_id', $field);
			} elseif ($field == 'fk_warehouse') {
				$diff = ExtDirect::prepareField($diff, $params, $line, 'warehouse_id', $field);
			} elseif ($field == 'fk_inventory') {
				$diff = ExtDirect::prepareField($diff, $params, $line, 'origin_id', $field);
			} else {
				$diff = ExtDirect::prepareField($diff, $params, $line, $field, $field);
			}
		}

		return $diff;
	}

	/**
	 * get line data from object
	 *
	 * @param InventoryLine		$object		object
	 * @param Object			$inventory	Inventory line object
	 * @param ExtDirectProduct	$product	product object
	 * @param String			$photoSize	format size of photo 'mini', 'small' or 'full' to add to line
	 * @return stdClass object with data
	 */
	private function getLineData($object, Inventory $inventory, ExtDirectProduct $product, $photoSize = '')
	{
		$data = new stdClass;

		$inventoryLine = new InventoryLine($this->db);

		foreach ($inventoryLine->fields as $field => $info) {
			if ($field == 'rowid') {
				$data->line_id = (int) $object->id;
				$data->id = (int) $object->id;
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
					$data->unit_id = $product->fk_unit;
					$data->has_photo = 0;
					if (!empty($photoSize)) {
						$product->fetchPhoto($data, $photoSize);
					}
				}
			} else {
				$data->{$field} = $object->{$field};
			}
		}
		$data->origin_id = $inventory->id;

		return $data;
	}

}
