<?php

/**
 * Copyright (C) 2026       Francis Appels <francis.appels@z-application.com>
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
 *  \file       htdocs/extdirect/class/ExtDirectReception.class.php
 *  \brief      Sencha Ext.Direct reception remoting class
 */

require_once DOL_DOCUMENT_ROOT . '/reception/class/reception.class.php';
require_once DOL_DOCUMENT_ROOT . '/reception/class/receptionlinebatch.class.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.commande.dispatch.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
dol_include_once('/extdirect/class/extdirect.class.php');
dol_include_once('/extdirect/class/ExtDirectFormProduct.class.php');
dol_include_once('/extdirect/class/ExtDirectProduct.class.php');

/** ExtDirectReception class
 * Class to access receptions with CRUD methods to connect to Extjs or sencha touch using Ext.direct connector
 */
class ExtDirectReception extends Reception
{
	/** @var User|null Dolibarr user object */
	private $_user;
	/** @var array<string> used constants */
	private $_receptionConstants = array('STOCK_CALCULATE_ON_RECEPTION', 'STOCK_CALCULATE_ON_RECEPTION_CLOSE');
	/** @var bool true if reception module is enabled and user has read rights */
	private $_enabled = false;

	/**
	 * end status to allow status iteration
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
				if (ExtDirect::checkDolVersion(0, '', '19.0')) {
					$user->getrights();
				} else {
					$user->loadRights();
				}
				$this->_enabled = !empty($conf->reception->enabled) && isset($user->rights->reception->lire);
				$this->_user = $user;
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
				$langs->load("other");
				parent::__construct($db);
			}
		}
	}

	/**
	 *	Load reception related constants
	 *
	 *	@param			stdClass	$params		filter with elements
	 *		                                    constant	name of specific constant
	 *
	 *	@return			array<stdClass>|stdClass|int|string result data with specific constant value or error number/message
	 */
	public function readConstants(stdClass $params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->reception->lire)) return PERMISSIONERROR;

		$results = ExtDirect::readConstants($this->db, $params, $this->_user, $this->_receptionConstants);

		return $results;
	}

	/**
	 * public method to read available optionals (extra fields)
	 *
	 * @return array<stdClass>|stdClass|int|string result data or error number/message
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
	 *                                  id Id of reception to load
	 *
	 *    @return     array<stdClass>|stdClass|int|string result data or error number/message
	 */
	public function readOptionals(stdClass $param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->reception->lire)) return PERMISSIONERROR;
		$results = array();
		$id = 0;
		$object = new Reception($this->db);

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
	 *    @param    array<stdClass>|stdClass    $params  optionals
	 *
	 *    @return     array<stdClass>|stdClass|int|string result data or error number/message
	 */
	public function updateOptionals($params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->reception->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);
		$object = new Reception($this->db);

		foreach ($paramArray as &$param) {
			if ($object->id != $param->object_id && ($result = $object->fetch($param->object_id)) < 0) return ExtDirect::getDolError($result, $object->errors, $object->error);
			$object->array_options['options_' . $param->name] = $param->raw_value;
		}
		if (($result = $object->insertExtraFields()) < 0) return ExtDirect::getDolError($result, $object->errors, $object->error);
		if (is_array($params)) {
			return $paramArray;
		} else {
			return $paramArray[0];
		}
	}

	/**
	 * public method to add optionals (extra fields) into database
	 *
	 *    @param    array<stdClass>|stdClass    $params  optionals
	 *
	 *    @return     array<stdClass>|stdClass|int|string result data or error number/message
	 */
	public function createOptionals($params)
	{
		return $this->updateOptionals($params);
	}

	/**
	 * public method to delete optionals (extra fields) into database
	 *
	 *    @param    array<stdClass>|stdClass    $params  optionals
	 *
	 *    @return    array<stdClass>|stdClass|int|string result data or error number/message
	 */
	public function destroyOptionals($params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->reception->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);
		$object = new Reception($this->db);

		foreach ($paramArray as &$param) {
			if ($object->id != $param->object_id && ($result = $object->fetch($param->object_id)) < 0) return ExtDirect::getDolError($result, $object->errors, $object->error);
		}
		if (($result = $object->deleteExtraFields()) < 0) return ExtDirect::getDolError($result, $object->errors, $object->error);
		if (is_array($params)) {
			return $paramArray;
		} else {
			return $paramArray[0];
		}
	}

	/**
	 * Ext.direct method to Create reception
	 *
	 * @param array<stdClass>|stdClass $param object or object array with reception record
	 * @return array<stdClass>|stdClass|int|string result data or error number/message
	 */
	public function extCreate($param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->reception->creer)) return PERMISSIONERROR;
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
			return $paramArray[0];
		}
	}

	/**
	 *    Load reception from database into memory
	 *
	 *    @param    stdClass    $params     filter with elements:
	 *                                      id  Id of reception to load
	 *                                      ref Ref of reception to load
	 *    @return     array<stdClass>|stdClass|int|string result data or error number/message
	 */
	public function extRead(stdClass $params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->reception->lire)) return PERMISSIONERROR;

		$results = array();
		$id = 0;
		$ref = '';
		$object = new Reception($this->db);

		if (isset($params->filter)) {
			foreach ($params->filter as $filter) {
				if ($filter->property == 'id') $id = $filter->value;
				elseif ($filter->property == 'ref') $ref = $filter->value;
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
	 * Ext.direct method to update reception
	 *
	 * @param array<stdClass>|stdClass $param object or object array with reception record
	 * @return array<stdClass>|stdClass|int|string result data or error number/message
	 */
	public function extUpdate($param)
	{
		global $conf, $langs;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->reception->creer)) return PERMISSIONERROR;

		$paramArray = ExtDirect::toArray($param);
		$object = new Reception($this->db);

		foreach ($paramArray as &$params) {
			if ($params->id) {
				$id = $params->id;
				$result = $object->fetch($id);
				if ($result < 0) return ExtDirect::getDolError($result, $object->errors, $object->error);
				if ($result > 0) {
					$this->prepareFields($params);
					// update fields
					if (($result = $object->update($this->_user)) < 0) return ExtDirect::getDolError($result, $object->errors, $object->error);
					// handle status transitions
					switch ($params->status_id) {
						case -1:
							break;
						case Reception::STATUS_DRAFT:
							if ($object->status != Reception::STATUS_DRAFT) {
								$result = $object->setDraft($this->_user);
							}
							break;
						case Reception::STATUS_VALIDATED:
							if ($object->status == Reception::STATUS_DRAFT) {
								$result = $object->valid($this->_user);
								if ($result >= 0 && empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE)) {
									$hidedetails = (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS) ? 1 : 0);
									$hidedesc = (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DESC) ? 1 : 0);
									$hideref = (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_REF) ? 1 : 0);
									$object->generateDocument($object->model_pdf, $langs, $hidedetails, $hidedesc, $hideref);
								}
							}
							break;
						case Reception::STATUS_CLOSED:
							if ($object->status == Reception::STATUS_VALIDATED) {
								$result = $object->setClosed();
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
			return $paramArray[0];
		}
	}

	/**
	 * Ext.direct method to destroy reception
	 *
	 * @param array<stdClass>|stdClass $param object or object array with reception record
	 * @return array<stdClass>|stdClass|int|string result data or error number/message
	 */
	public function extDestroy($param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->reception->supprimer)) return PERMISSIONERROR;

		$paramArray = ExtDirect::toArray($param);
		$object = new Reception($this->db);

		foreach ($paramArray as &$params) {
			if ($params->id) {
				$id = $params->id;
				if ($id > 0) {
					$result = $object->fetch($id);
					if ($result < 0) return ExtDirect::getDolError($result, $object->errors, $object->error);
					if ($result > 0) {
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
			return $paramArray[0];
		}
	}

	/**
	 * Ext.direct method to upload file for reception object
	 *
	 * @param array<stdClass>|stdClass $params object or object array with uploaded file(s)
	 * @return array<stdClass>|stdClass|int|string result data or error number/message
	 */
	public function fileUpload($params)
	{
		global $conf;
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->reception->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);
		$dir = null;
		$object = new Reception($this->db);
		$response = null;

		foreach ($paramArray as &$param) {
			if (isset($param['extTID'])) {
				$id = $param['extTID'];
				if ($object->fetch($id)) {
					$dir = $conf->reception->dir_output . '/' . dol_sanitizeFileName($object->ref);
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
	 * public method to read a list of receptions
	 *
	 * @param stdClass $params filter on status, ref, socid, etc.
	 * @return     array<stdClass>|stdClass|int|string result data or error number/message
	 */
	public function extList(stdClass $params)
	{
		global $hookmanager;

		if (!isset($this->db)) return CONNECTERROR;
		if (!$this->_enabled) return NOTENABLEDERROR;
		if (!isset($this->_user->rights->reception->lire)) return PERMISSIONERROR;
		$result = new stdClass;
		$data = array();
		$rows = array();
		$hookmanager->initHooks(array('extdirectreceptionextlist'));
		$parameters = array('filter' => $params->filter);
		$action = 'extList';

		$statusFilterCount = 0;
		$ref = null;
		$status_id = array();
		$contentFilter = null;
		$sorterSize = 0;
		$barcode = null;
		$socid = null;
		$origin_id = null;
		$limit = null;
		$start = null;

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
				elseif ($filter->property == 'socid') $socid = $filter->value;
				elseif ($filter->property == 'origin_id') $origin_id = $filter->value;
				elseif ($filter->property == 'barcode') $barcode = $filter->value;
				elseif ($filter->property == 'content') $contentFilter = $filter->value;
			}
		}

		$sqlFields = "SELECT r.rowid, r.ref, r.ref_supplier, r.fk_soc, s.nom as company_name, r.fk_statut as status_id, r.date_creation, r.date_reception, r.date_delivery, r.tracking_number, ea.activity_status";
		$reshook = $hookmanager->executeHooks('printFieldListSelect', $parameters, $this, $action); // Note that $action and $object may have been modified by hook
		if ($reshook < 0) {
			return ExtDirect::getDolError($reshook, $hookmanager->errors, $hookmanager->error);
		} else {
			$sqlFields .= $hookmanager->resPrint;
		}
		$sqlFrom = " FROM " . MAIN_DB_PREFIX . "reception as r";
		$sqlFrom .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON r.fk_soc = s.rowid";
		$sqlFrom .= " LEFT JOIN ("; // get latest extdirect activity status to check if locked
		$sqlFrom .= "   SELECT ma.activity_id, ma.maxrow AS rowid, ea.status as activity_status";
		$sqlFrom .= "   FROM (";
		$sqlFrom .= "    SELECT MAX( rowid ) AS maxrow, activity_id";
		$sqlFrom .= "    FROM " . MAIN_DB_PREFIX . "extdirect_activity";
		$sqlFrom .= "    GROUP BY activity_id";
		$sqlFrom .= "   ) AS ma, " . MAIN_DB_PREFIX . "extdirect_activity AS ea";
		$sqlFrom .= "   WHERE ma.maxrow = ea.rowid";
		$sqlFrom .= " ) AS ea ON r.rowid = ea.activity_id";
		if ($origin_id) {
			$sqlFrom .= " INNER JOIN " . MAIN_DB_PREFIX . "element_element as el ON el.fk_target = r.rowid AND el.fk_source = " . ((int) $origin_id);
			$sqlFrom .= " AND el.sourcetype = 'order_supplier' AND el.targettype = '" . $this->db->escape($this->element) . "'";
		}
		$reshook = $hookmanager->executeHooks('printFieldListFrom', $parameters, $this, $action); // Note that $action and $object may have been modified by hook
		if ($reshook < 0) {
			return ExtDirect::getDolError($reshook, $hookmanager->errors, $hookmanager->error);
		} else {
			$sqlFrom .= $hookmanager->resPrint;
		}
		$sqlFromNotForTotal = "";
		$sqlWhere = " WHERE r.entity IN (" . getEntity($this->element) . ')';
		$sqlWhereNotForTotal = "";

		if ($statusFilterCount > 0) {
			$sqlWhere .= " AND ( ";
			foreach ($status_id as $key => $status) {
				$sqlWhere .= "r.fk_statut = " . (int) $status;
				if ($key < ($statusFilterCount - 1)) $sqlWhere .= " OR ";
			}
			$sqlWhere .= ")";
		}
		if ($ref) {
			$sqlWhere .= " AND r.ref = '" . $this->db->escape($ref) . "'";
		}
		if ($socid) {
			$sqlWhere .= " AND r.fk_soc = " . (int) $socid;
		}
		if ($barcode) {
			$sqlWhere .= " AND s.barcode LIKE '%" . $this->db->escape($barcode) . "%'";
		}
		if ($contentFilter) {
			$fields = array('r.ref', 'r.ref_supplier', 's.nom');
			$sqlWhere .= " AND " . natural_search($fields, $contentFilter, 0, 1);
		}

		$reshook = $hookmanager->executeHooks('printFieldListWhere', $parameters, $this, $action); // Note that $action and $object may have been modified by hook
		if ($reshook < 0) {
			return ExtDirect::getDolError($reshook, $hookmanager->errors, $hookmanager->error);
		} else {
			$sqlWhere .= $hookmanager->resPrint;
		}

		$sqlOrder = " ORDER BY ";
		if (isset($params->sort)) {
			$sorterSize = count($params->sort);
			foreach ($params->sort as $key => $sort) {
				if (!empty($sort->property)) {
					if ($sort->property == 'status_id') {
						$sortfield = 'r.fk_statut';
					} elseif ($sort->property == 'ref') {
						$sortfield = 'r.ref';
					} elseif ($sort->property == 'ref_supplier') {
						$sortfield = 'r.ref_supplier';
					} elseif ($sort->property == 'company_name') {
						$sortfield = 's.nom';
					} elseif ($sort->property == 'date_reception') {
						$sortfield = 'r.date_reception';
					} elseif ($sort->property == 'date_delivery') {
						$sortfield = 'r.date_delivery';
					} else {
						$sortfield = $sort->property;
					}
					$sqlOrder .= $sortfield . ' ' . $sort->direction;
					if ($key < ($sorterSize - 1)) {
						$sqlOrder .= ",";
					}
				}
			}
		} else {
			$sqlOrder .= "r.date_creation DESC";
		}

		if ($limit) {
			$sqlLimit = $this->db->plimit($limit, $start);
		} else {
			$sqlLimit = '';
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
				$row->id             = (int) $obj->rowid;
				$row->ref            = $obj->ref;
				$row->ref_supplier   = $obj->ref_supplier;
				$row->supplier_id    = (int) $obj->fk_soc;
				$row->company_name   = $obj->company_name;
				$row->status_id      = (int) $obj->status_id;
				$row->statusdisplay  = html_entity_decode($this->LibStatut($row->status_id, 1));
				$row->status         = $obj->activity_status;
				$row->date_reception = $this->db->jdate($obj->date_reception);
				$row->date_delivery  = $this->db->jdate($obj->date_delivery);
				$row->tracking_number = $obj->tracking_number;
				$rows[$row->id] = $row;
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
		} else {
			return SQLERROR;
		}
	}

	/**
	 * public method to read a list of statuses
	 *
	 * @return     array<stdClass>|stdClass|int|string result data or error number/message
	 */
	public function readStatus()
	{
		if (!isset($this->db)) return CONNECTERROR;
		$results = array();
		$statusId = 0;

		while ($statusId < self::STATUS_END) {
			$result = $this->LibStatut($statusId, 1);
			if (!empty($result)) {
				$row = new stdClass;
				$row->id = $statusId;
				$row->status = html_entity_decode($result);
				array_push($results, $row);
			}
			$statusId++;
		}
		return $results;
	}

	/**
	 * get data from reception object
	 *
	 * @param Reception $object reception object
	 * @return stdClass object with data
	 */
	private function getData($object)
	{
		$data = new stdClass;

		$data->id             = (int) $object->id;
		$data->ref            = $object->ref;
		$data->ref_supplier   = $object->ref_supplier;
		$data->supplier_id    = (int) $object->socid;
		$data->origin         = $object->origin_type;
		$data->origin_id      = (int) $object->origin_id;
		$data->date_creation  = $object->date_creation;
		$data->date_reception = $object->date_reception;
		$data->deliver_date  = $object->date_delivery;
		$data->valid_date     = $object->date_valid;
		$data->tracking_number = $object->tracking_number;
		$data->shipping_method_id = (int) $object->shipping_method_id;
		$data->note_public    = $object->note_public;
		$data->note_private   = $object->note_private;
		$data->status_id      = (int) $object->status;
		$data->statusdisplay  = $object->getLibStatut(1);

		if ($object->thirdparty) {
			$data->company_name = $object->thirdparty->name;
		}

		return $data;
	}

	/**
	 * private method to copy reception fields into dolibarr object
	 *
	 * @param stdclass $params object with fields
	 * @return void
	 */
	private function prepareFields($params)
	{
		isset($params->ref)              ? $this->ref              = $params->ref              : null;
		isset($params->ref_supplier)     ? $this->ref_supplier     = $params->ref_supplier     : null;
		isset($params->supplier_id)      ? $this->socid            = $params->supplier_id      : null;
		isset($params->origin)           ? $this->origin_type       = $params->origin           : null;
		isset($params->origin_id)        ? $this->origin_id        = $params->origin_id        : null;
		isset($params->date_reception)   ? $this->date_reception   = $params->date_reception   : null;
		isset($params->date_delivery)    ? $this->date_delivery    = $params->date_delivery    : null;
		isset($params->tracking_number)  ? $this->tracking_number  = $params->tracking_number  : null;
		isset($params->shipping_method_id) ? $this->shipping_method_id = $params->shipping_method_id : null;
		isset($params->note_public)      ? $this->note_public      = $params->note_public      : null;
		isset($params->note_private)     ? $this->note_private     = $params->note_private     : null;
	}

	/**
	 *    Load lines from reception
	 *
	 *    @param    stdClass    $params     filter with elements:
	 *                                      origin_id   Id of reception to load lines from
	 *    @return     array<stdClass>|stdClass|int|string|null result data, error number/message, or null
	 */
	public function extReadLines(stdClass $params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->reception->lire)) return PERMISSIONERROR;
		$result = new stdClass;
		$data = array();
		$rows = array();
		$origin_id = 0;
		$product_id = null;
		$photoSize = 'mini';
		$contentfilter = null;
		$object = new Reception($this->db);
		$product = new ExtDirectProduct($this->_user->login);

		$includeTotal = true;
		$limit = null;
		$start = null;

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
				elseif ($filter->property == 'product_id') $product_id = $filter->value;
				elseif ($filter->property == 'barcode') {
					$idArray = $product->fetchIdFromBarcode($filter->value);
					if ($idArray['product'] > 0) {
						$product_id = $idArray['product'];
					} elseif (ExtDirect::checkDolVersion(0, '13.0', '')) {
						$idArray = $product->fetchIdFromBarcode($filter->value, 'product_fournisseur_price');
						$product_id = $idArray['product'];
					}
				} elseif ($filter->property == 'photo_size' && !empty($filter->value)) $photoSize = $filter->value;
				elseif ($filter->property == 'content' && !empty($filter->value)) $contentfilter = $filter->value;
			}
		}

		if ($origin_id > 0) {
			$object->fetch($origin_id);
			$sqlFields = 'SELECT rd.rowid as id, rd.datec, rd.tms as date_modification, rd.fk_reception, rd.fk_element as fk_commande,';
			$sqlFields .= ' rd.fk_elementdet as fk_commandefourndet, rd.fk_product, rd.fk_entrepot, rd.qty, rd.batch,';
			$sqlFields .= ' rd.eatby, rd.sellby, rd.cost_price, rd.comment, rd.rang, rd.status';
			$sqlFrom = ' FROM ' . MAIN_DB_PREFIX . 'receptiondet_batch as rd';
			if ($contentfilter) {
				$sqlFrom .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'product as p ON rd.fk_product = p.rowid';
			}
			$sqlWhere = ' WHERE rd.fk_reception = ' . ((int) $origin_id);
			if (isset($product_id)) {
				$sqlWhere .= ' AND rd.fk_product = ' . ((int) $product_id);
			}
			if ($contentfilter) {
				$fields = array('p.ref', 'p.label', 'rd.batch', 'p.barcode');
				$sqlWhere .= " AND " . natural_search($fields, $contentfilter, 0, 1);
			}

			$sqlOrder = ' ORDER BY rd.rang, rd.rowid';
			if ($limit) {
				$sqlLimit = $this->db->plimit($limit, $start);
			} else {
				$sqlLimit = '';
			}
			$total = 0;
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
	 * Ext.direct method to Create reception lines
	 *
	 * @param array<stdClass>|stdClass $param object or object array with reception line record
	 * @return array<stdClass>|stdClass|int|string result data or error number/message
	 */
	public function extCreateLines($param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->reception->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($param);

		foreach ($paramArray as &$params) {
			if ($params->origin_id > 0) {
				$line = new ReceptionLineBatch($this->db);
				$this->prepareLineFields($params, $line);
				$line->fk_reception = $params->origin_id;
				if (($result = $line->create($this->_user)) < 0) return ExtDirect::getDolError($result, $line->errors, $line->error);
				$params->line_id = $line->id;
				$params->id = $line->id;
			} else {
				return PARAMETERERROR;
			}
		}

		if (is_array($param)) {
			return $paramArray;
		} else {
			return $paramArray[0];
		}
	}

	/**
	 * Ext.direct method to update reception lines
	 *
	 * @param array<stdClass>|stdClass $param object or object array with reception line record
	 * @return array<stdClass>|stdClass|int|string result data or error number/message
	 */
	public function extUpdateLines($param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->reception->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($param);

		foreach ($paramArray as &$params) {
			if ($params->line_id > 0) {
				$line = new ReceptionLineBatch($this->db);
				if (($result = $line->fetch($params->line_id)) < 0) return ExtDirect::getDolError($result, $line->errors, $line->error);
				$diff = $this->prepareLineFields($params, $line);
				if ($diff) {
					if (($result = $line->update($this->_user)) < 0) return ExtDirect::getDolError($result, $line->errors, $line->error);
				}
			} else {
				return PARAMETERERROR;
			}
		}

		if (is_array($param)) {
			return $paramArray;
		} else {
			return $paramArray[0];
		}
	}

	/**
	 * Ext.direct method to destroy reception lines
	 *
	 * @param array<stdClass>|stdClass $param object or object array with reception line record
	 * @return array<stdClass>|stdClass|int|string result data or error number/message
	 */
	public function extDestroyLines($param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->reception->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($param);

		foreach ($paramArray as &$params) {
			$lineId = isset($params->line_id) ? $params->line_id : $params->id;
			if ($lineId > 0) {
				$line = new ReceptionLineBatch($this->db);
				if (($result = $line->delete($this->_user)) < 0) return ExtDirect::getDolError($result, $line->errors, $line->error);
				// delete by id requires fetch first or direct SQL
				$sql = 'DELETE FROM ' . MAIN_DB_PREFIX . 'receptiondet_batch WHERE rowid = ' . ((int) $lineId);
				$resql = $this->db->query($sql);
				if (!$resql) return SQLERROR;
			} else {
				return PARAMETERERROR;
			}
		}

		if (is_array($param)) {
			return $paramArray;
		} else {
			return $paramArray[0];
		}
	}

	/**
	 * private method to copy reception line fields into dolibarr line object
	 *
	 * @param stdclass $params object with fields
	 * @param ReceptionLineBatch $line line object with fields
	 * @return bool true if fields differ from current line values
	 */
	private function prepareLineFields($params, ReceptionLineBatch &$line)
	{
		$diff = false;
		$diff = ExtDirect::prepareField($diff, $params, $line, 'product_id', 'fk_product');
		$diff = ExtDirect::prepareField($diff, $params, $line, 'warehouse_id', 'fk_entrepot');
		$diff = ExtDirect::prepareField($diff, $params, $line, 'origin_line_id', 'fk_elementdet');
		$diff = ExtDirect::prepareField($diff, $params, $line, 'qty', 'qty');
		$diff = ExtDirect::prepareField($diff, $params, $line, 'batch', 'batch', '');
		$diff = ExtDirect::prepareField($diff, $params, $line, 'eatby', 'eatby');
		$diff = ExtDirect::prepareField($diff, $params, $line, 'sellby', 'sellby');
		$diff = ExtDirect::prepareField($diff, $params, $line, 'cost_price', 'cost_price');
		$diff = ExtDirect::prepareField($diff, $params, $line, 'comment', 'comment');
		return $diff;
	}

	/**
	 * public method to read available line optionals (extra fields)
	 *
	 * @return array<stdClass>|int result data or error number
	 */
	public function readLineOptionalModel()
	{
		if (!isset($this->db)) return CONNECTERROR;

		$receptionLine = new ReceptionLineBatch($this->db);

		return ExtDirect::readOptionalModel($receptionLine);
	}

	/**
	 * public method to read reception line optionals (extra fields) from database
	 *
	 *    @param    stdClass    $param  filter with elements:
	 *                                  line_id Id of reception line to load
	 *
	 *    @return     array<stdClass>|int|string result data or error number/message
	 */
	public function readLineOptionals(stdClass $param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->reception->lire)) return PERMISSIONERROR;
		$results = array();
		$line_id = 0;

		if (isset($param->filter)) {
			foreach ($param->filter as $key => $filter) {
				if ($filter->property == 'line_id') $line_id = $filter->value;
			}
		}

		if ($line_id > 0) {
			$extraFields = new ExtraFields($this->db);
			$receptionLine = new ReceptionLineBatch($this->db);
			$receptionLine->id = $line_id;
			if (($result = $receptionLine->fetch_optionals()) < 0) return ExtDirect::getDolError($result, $receptionLine->errors, $receptionLine->error);
			if (!$receptionLine->error) {
				$extraFields->fetch_name_optionals_label($receptionLine->table_element);
				$index = 1;
				if (empty($receptionLine->array_options)) {
					// create empty optionals to be able to add optionals
					$optionsArray = (!empty($extraFields->attributes[$receptionLine->table_element]['label']) ? $extraFields->attributes[$receptionLine->table_element]['label'] : null);
					if (is_array($optionsArray) && count($optionsArray) > 0) {
						foreach ($optionsArray as $name => $_label) {
							$row = new stdClass;
							$row->id = $index++;
							$row->name = $name;
							$row->value = '';
							$row->object_id = $receptionLine->id;
							$row->object_element = $receptionLine->element;
							$row->raw_value = null;
							$results[] = $row;
						}
					}
				} else {
					foreach ($receptionLine->array_options as $key => $value) {
						$row = new stdClass;
						$name = substr($key, 8); // strip options_
						$row->id = $index++; // ExtJs needs id to be able to destroy records
						$row->name = $name;
						$row->value = $extraFields->showOutputField($name, $value, '', $receptionLine->table_element);
						$row->object_id = $receptionLine->id;
						$row->object_element = $receptionLine->element;
						$row->raw_value = $value;
						$results[] = $row;
					}
				}
			}
		}
		return $results;
	}

	/**
	 * public method to update line optionals (extra fields) into database
	 *
	 *    @param    array<stdClass>|stdClass    $params  optionals
	 *
	 *    @return    array<stdClass>|stdClass|int|string result data or error number/message
	 */
	public function updateLineOptionals($params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->reception->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);

		$receptionLine = new ReceptionLineBatch($this->db);
		foreach ($paramArray as &$param) {
			if ($receptionLine->id != $param->object_id) {
				$receptionLine->id = $param->object_id;
				if (($result = $receptionLine->fetch_optionals()) < 0) return ExtDirect::getDolError($result, $receptionLine->errors, $receptionLine->error);
			}
			$receptionLine->array_options['options_' . $param->name] = $param->raw_value;
		}
		if (($result = $receptionLine->insertExtraFields()) < 0) return ExtDirect::getDolError($result, $receptionLine->errors, $receptionLine->error);
		if (is_array($params)) {
			return $paramArray;
		} else {
			return $paramArray[0];
		}
	}

	/**
	 * public method to add line optionals (extra fields) into database
	 *
	 *    @param    array<stdClass>|stdClass    $params  optionals
	 *
	 *    @return     array<stdClass>|stdClass|int|string result data or error number/message
	 */
	public function createLineOptionals($params)
	{
		return $this->updateLineOptionals($params);
	}

	/**
	 * public method to delete line optionals (extra fields) into database
	 *
	 *    @param    array<stdClass>|stdClass    $params  optionals
	 *
	 *    @return    array<stdClass>|stdClass|int|string result data or error number/message
	 */
	public function destroyLineOptionals($params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->reception->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);

		$receptionLine = new ReceptionLineBatch($this->db);
		foreach ($paramArray as &$param) {
			if ($receptionLine->id != $param->object_id) {
				$receptionLine->id = $param->object_id;
				if (($result = $receptionLine->fetch_optionals()) < 0) return ExtDirect::getDolError($result, $receptionLine->errors, $receptionLine->error);
			}
		}
		if (($result = $receptionLine->deleteExtraFields()) < 0) return ExtDirect::getDolError($result, $receptionLine->errors, $receptionLine->error);
		if (is_array($params)) {
			return $paramArray;
		} else {
			return $paramArray[0];
		}
	}

	/**
	 * get line data from reception line object
	 *
	 * @param stdClass          $object    raw DB row for reception line
	 * @param Reception         $reception Reception object
	 * @param ExtDirectProduct  $product   product object
	 * @param string            $photoSize format size of photo 'mini', 'small' or 'full'
	 * @return stdClass object with data
	 */
	private function getLineData($object, Reception $reception, ExtDirectProduct $product, $photoSize = '')
	{
		$data = new stdClass;

		$data->id               = (int) $object->id;
		$data->line_id          = (int) $object->id;
		$data->origin_id        = (int) $reception->id;
		$data->origin_line_id   = (int) $object->fk_commandefourndet;
		$data->product_id       = (int) $object->fk_product;
		$data->warehouse_id     = (int) $object->fk_entrepot;
		$data->qty              = (float) $object->qty;
		$data->batch            = $object->batch;
		$data->eatby            = $this->db->jdate($object->eatby);
		$data->sellby           = $this->db->jdate($object->sellby);
		$data->cost_price       = (float) $object->cost_price;
		$data->comment          = $object->comment;
		$data->rang             = (int) $object->rang;
		$data->date_creation    = $this->db->jdate($object->datec);
		$data->date_modification = $this->db->jdate($object->date_modification);

		if ($product && $product->id > 0) {
			$data->ref_product    = $product->ref;
			$data->product_label  = $product->label;
			$data->product_desc   = $product->description;
			$data->product_type   = $product->type;
			$data->barcode        = $product->barcode ? $product->barcode : '';
			$data->barcode_type   = $product->barcode_type ? $product->barcode_type : 0;
			$data->barcode_with_checksum = $product->barcode ? $product->fetchBarcodeWithChecksum($product) : '';
			$data->unit_id        = $product->fk_unit;
			$data->has_photo      = 0;
			if (!empty($photoSize)) {
				$product->fetchPhoto($data, $photoSize);
			}
		}

		return $data;
	}
}
