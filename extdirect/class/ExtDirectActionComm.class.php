<?PHP
/*
 * Copyright (C) 2012       Francis Appels <francis.appels@z-application.com>
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
 *  \file       htdocs/extdirect/class/ExtDirectActioncomm.class.php
 *  \brief      Sencha Ext.Direct actioncomm remoting class
 */
require_once DOL_DOCUMENT_ROOT."/comm/action/class/actioncomm.class.php";
require_once DOL_DOCUMENT_ROOT."/societe/class/societe.class.php";// for add and get societe_commercial
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
dol_include_once('/extdirect/class/extdirect.class.php');

/**
 * ExtDirectActionComm Class
 *
 * Calendar Class with CRUD methods to connect to Extjs or sencha touch using Ext.direct connector
 */
class ExtDirectActionComm extends ActionComm
{
	private $_user;
	private $_societe;
	private $_enabled = false;

	/**
	 * constructor
	 *
	 * @param string $login user name
	 * @return number
	 */
	public function __construct($login)
	{
		global $conf, $langs, $db, $user;

		if (!empty($login)) {
			if ((is_object($login) && get_class($db) == get_class($login)) || $user->id > 0 || $user->fetch('', $login, '', 1) > 0) {
				$user->getrights();
				$this->_enabled = !empty($conf->agenda->enabled) && isset($user->rights->agenda->allactions->read);
				$this->_user = $user;
				if (isset($this->_user->conf->MAIN_LANG_DEFAULT)) {
					$langs->setDefaultLang($this->_user->conf->MAIN_LANG_DEFAULT);
				} else {
					$langs->setDefaultLang(empty($conf->global->MAIN_LANG_DEFAULT) ? 'auto' : $conf->global->MAIN_LANG_DEFAULT);
				}
				$langs->load("main");
				$langs->load("dict");
				$langs->load("errors");
				parent::__construct($db);
				$this->_societe = new Societe($db);
			}
		}
	}

	/**
	 *    Load actions of societe
	 *
	 *    @param    stdClass    $params     ->filter[]->property->societe_id: filter on societe rowid
	 *                                      ->filter[]->property->type_code: filter on action type
	 *                                      ->filter[]->property->user_id: filter on user_id
	 *    @return   stdClass result data or error string
	 */
	public function readAction(stdClass $params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->agenda->myactions->read)
			|| !isset($this->_user->rights->agenda->allactions->read)) return PERMISSIONERROR;
		$results = array();
		$result = 0;

		if (isset($params->filter)) {
			foreach ($params->filter as $key => $filter) {
				if ($filter->property == 'id' && !empty($filter->value)) {
					if (($result = $this->fetch($filter->value)) < 0)   return ExtDirect::getDolError($result, $this->errors, $this->error);
					if ($result > 0) {
						$row = new stdClass;
						$row->id                = (int) $this->id;
						$row->code              = $this->code;
						$row->label             = $this->label;
						$row->datep             = $this->datep;
						$row->datef             = $this->datef;
						$row->durationp         = (int) ($this->datef - $this->datep);
						$row->fulldayevent      = (int) $this->fulldayevent;
						$row->percentage        = (int) $this->percentage;
						$row->location          = $this->location;
						$row->transparency      = (int) $this->transparency;
						$row->priority          = $this->priority;
						$row->note              = $this->note;
						$row->usertodo_id   = (int) $this->userownerid;
						$row->userdone_id   = (int) $this->userdoneid;
						$row->company_id    = (int) $this->socid;
						$row->contact_id    = (int) $this->contact_id;
						$row->project_id        = (int) $this->fk_project;

						array_push($results, $row);
					}
				}
			}
		}
		return $results;
	}

	/**
	* public method to read available actioncomm optionals (extra fields)
	*
	* @return stdClass result data or ERROR
	*/
	public function readOptionalModel()
	{
		if (!isset($this->db)) return CONNECTERROR;

		return ExtDirect::readOptionalModel($this);
	}

	/**
	 * public method to read actioncomm (extra fields) from database
	 *
	 *    @param    stdClass    $param  filter with elements:
	 *                                  id      Id of product to load
	 *
	 *    @return     stdClass result data or -1
	 */
	public function readOptionals(stdClass $param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->agenda->myactions->read)
			|| !isset($this->_user->rights->agenda->allactions->read)) return PERMISSIONERROR;
		$results = array();
		$id = 0;

		if (isset($param->filter)) {
			foreach ($param->filter as $key => $filter) {
				if ($filter->property == 'id') $id=$filter->value;
			}
		}

		if ($id > 0) {
			$extraFields = new ExtraFields($this->db);
			if (ExtDirect::checkDolVersion(0, '', '5.0')) {
				if (($result = $this->fetch($id)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
			} else {
				$this->id = $id;
			}
			if (! $this->error || $this->id) {
				$extraLabels = $extraFields->fetch_name_optionals_label($this->table_element);
				if (ExtDirect::checkDolVersion(0, '', '5.0')) {
					$this->fetch_optionals($id, $extraLabels);
				} else {
					$this->fetch_optionals();
				}
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
		if (!isset($this->_user->rights->agenda->myactions->create)
			|| !isset($this->_user->rights->agenda->allactions->create)) return PERMISSIONERROR;
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
		if (!isset($this->_user->rights->agenda->myactions->create)
			|| !isset($this->_user->rights->agenda->allactions->create)) return PERMISSIONERROR;
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
	 *    Load action list from database into memory, keep properties of same kind together
	 *
	 *    @param    stdClass    $params     property filter with properties and values:
	 *                                      id           Id of third party to load
	 *                                      company_id       id of third party
	 *                                      contact_id       id of contact
	 *                                      content         filter on part of company name, label, firstnamet or lastname
	 *                                      property sort with properties field names and directions:
	 *                                      property limit for paging with sql LIMIT and START values
	 *
	 *    @return     stdClass result data or -1
	 */
	public function readActionList(stdClass $params)
	{
		global $conf,$langs;

		if (!isset($this->db)) return CONNECTERROR;
		if (!$this->_enabled) return NOTENABLEDERROR;
		if (!isset($this->_user->rights->societe->contact->lire)) return PERMISSIONERROR;
		$result = new stdClass;
		$data = array();

		$filterSize = 0;
		$includeTotal = true;

		if (isset($params->limit)) {
			$limit = $params->limit;
			$start = $params->start;
		}
		if (isset($params->filter)) {
			$filterSize = count($params->filter);
		}
		if (isset($params->include_total)) {
			$includeTotal = $params->include_total;
		}

		$sqlFields = 'SELECT a.id, a.label, a.datep, a.datep2 as datef, a.percent as percentage, s.nom as companyname, c.lastname, c.firstname, s.rowid as company_id, c.rowid as contact_id';
		$sqlFrom = ' FROM '.MAIN_DB_PREFIX.'actioncomm as a';
		$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe as s ON a.fk_soc = s.rowid';
		$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'socpeople as c ON a.fk_contact = c.rowid';
		$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_actioncomm as ac ON a.fk_action = ac.id';
		if ($filterSize > 0) {
			// TODO improve sql command to allow random property type
			$sqlWhere = ' WHERE (';
			foreach ($params->filter as $key => $filter) {
				if ($filter->property == 'id')
					$sqlWhere .= 'a.id = '.$filter->value;
				elseif ($filter->property == 'company_id')
					$sqlWhere .= '(s.rowid = '.$filter->value.' AND s.entity IN ('.getEntity('societe', 1).'))';
				elseif ($filter->property == 'contact_id')
					$sqlWhere .= "(c.rowid = ".$filter->value.")";
				elseif ($filter->property == 'user_id')
					$sqlWhere.= '(fk_user_action = '.$filter->value.' OR fk_user_done = '.$filter->value.')';
				elseif ($filter->property == 'type')
					$sqlWhere.= "(ac.type = '".$this->db->escape($filter->value)."')";
				elseif ($filter->property == 'content') {
					$fields = array('c.firstname', 'c.lastname', 's.nom', 'a.label');
					$sqlWhere .= natural_search($fields, $filter->value, 0, 1);
				} else break;
				if ($key < ($filterSize-1)) {
					if ($filter->property == $params->filter[$key+1]->property) $sqlWhere .= ' OR ';
					else $sqlWhere .= ') AND (';
				}
			}
			$sqlWhere .= ')';
		}
		$sqlOrder = " ORDER BY ";
		if (isset($params->sort)) {
			$sorterSize = count($params->sort);
			foreach ($params->sort as $key => $sort) {
				if (!empty($sort->property)) {
					$sqlOrder .= $sort->property. ' '.$sort->direction;
					if ($key < ($sorterSize-1)) {
						$sqlOrder .= ",";
					}
				}
			}
		} else {
			$sqlOrder .= "datep ASC";
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
				dol_syslog(get_class($this)."::readProductList ".$error, LOG_ERR);
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
				$row->id            = (int) $obj->id;
				$row->percentage    = (int) $obj->percentage;
				$row->companyname   = $obj->companyname;
				$row->contactname    = ($obj->firstname != "") ? ($obj->firstname.' '.$obj->lastname) : ($obj->lastname);
				$row->datep         = $this->db->jdate($obj->datep);
				$row->datef         = $this->db->jdate($obj->datef);
				$row->company_id    = $obj->company_id;
				$row->contact_id    = $obj->contact_id;
				$row->label         = $obj->label;

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
			dol_syslog(get_class($this)."::readActionList ".$error, LOG_ERR);
			return -1;
		}
	}



	/**
	 * Ext.direct create method
	 *
	 *    @param    stdClass    $params record to create
	 *    @return   stdClass    result data or error number
	 */
	public function createAction($params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->agenda->myactions->create)
			|| !isset($this->_user->rights->agenda->allactions->create)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);
		// parent class settings
		$notrigger = 1;

		foreach ($paramArray as &$param) {
			// prepare fields
			$this->prepareFields($param);
			// create
			if (ExtDirect::checkDolVersion(0, '', '10.0')) {
				if (($result = $this->add($this->_user, $notrigger)) < 0)    return ExtDirect::getDolError($result, $this->errors, $this->error);
			} else {
				if (($result = $this->create($this->_user, $notrigger)) < 0)    return ExtDirect::getDolError($result, $this->errors, $this->error);
			}

			$param->id=$this->id;
			$this->_societe->id=$this->socid;
			$this->_societe->add_commercial($this->_user, $this->userownerid);
		}

		if (is_array($params)) {
			return $paramArray;
		} else {
			return $param;
		}
	}

	/**
	 * Ext.direct update method
	 *
	 *    @param        stdClass    $params record to update
	 *    @return       stdClass    result data or error number
	 */
	public function updateAction($params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->agenda->myactions->create)
			|| !isset($this->_user->rights->agenda->allactions->create)) return PERMISSIONERROR;
		// dolibarr update settings
		$notrigger=0;

		$paramArray = ExtDirect::toArray($params);
		foreach ($paramArray as &$param) {
			// prepare fields
			if ($param->id) {
				$this->id = $param->id;
				if (($result = $this->fetch($this->id)) < 0) {
					return $result;
				}
				$this->prepareFields($param);
				// update
				if (($result = $this->update($this->_user, $notrigger)) < 0) {
					 return ExtDirect::getDolError($result, $this->errors, $this->error);
				}
				$this->_societe->id=$this->socid;
				$this->_societe->add_commercial($this->_user, $this->userdoneid);
			} else {
				return PARAMETERERROR;
			}
		}

		if (is_array($params)) {
			return $paramArray;
		} else {
			return $param;
		}
	}

	/**
	 * Ext.direct destroy method
	 *
	 *    @param        stdClass    $params record to destroy
	 *    @return       stdClass    result data or error number
	 */
	public function destroyAction($params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->agenda->myactions->delete)
			|| !isset($this->_user->rights->agenda->allactions->delete)) return PERMISSIONERROR;
		// dolibarr delete settings
		$notrigger=0;
		$paramArray = ExtDirect::toArray($params);

		foreach ($paramArray as &$param) {
			// prepare fields
			if ($param->id) {
				$this->id = $param->id;
				// delete
				if (($result = $this->delete($notrigger)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
			} else {
				return PARAMETERERROR;
			}
		}
		if (is_array($params)) {
			return $paramArray;
		} else {
			return $param;
		}
	}

	/**
	 * Ext.direct method to upload file for actioncomm object
	 *
	 * @param unknown_type $params object or object array with uploaded file(s)
	 * @return Array    ExtDirect response message
	 */
	public function fileUpload($params)
	{
		global $conf;
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->agenda->myactions->create)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);
		$dir = null;

		foreach ($paramArray as &$param) {
			if (isset($param['extTID'])) {
				$id = $param['extTID'];
				if ($this->fetch($id)) {
					$dir = $conf->agenda->dir_output.'/'.dol_sanitizeFileName($this->ref);
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
	 * Ext.directfn for getting all users who have a sales role
	 *
	 * @return stdClass array or error number
	 */
	public function getAllUsers()
	{
		if (!isset($this->db)) return CONNECTERROR;

		$results = array();
		$row = new stdClass;
		$row->id = 0;
		$row->name = '';
		array_push($results, $row);

		$sql = "SELECT u.rowid, u.firstname,";
		$sql.= " u.lastname";
		$sql .= " FROM ".MAIN_DB_PREFIX."user as u";
		$sql .= " ORDER BY u.lastname ASC ";

		$resql=$this->db->query($sql);

		if ($resql) {
			$num=$this->db->num_rows($resql);
			for ($i = 0;$i < $num; $i++) {
				$obj = $this->db->fetch_object($resql);
				$row = new stdClass;
				$row->id       = (int) $obj->rowid;
				$row->name      = $obj->firstname.' '.$obj->lastname;
				array_push($results, $row);
			}
			$this->db->free($resql);
			return $results;
		} else {
			$this->error="Error ".$this->db->lasterror();
			dol_syslog(get_class($this)."::getAllUsers ".$this->error, LOG_ERR);
			return ExtDirect::getDolError(-1, $this->errors, $this->error);
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
		isset($params->datep) ? $this->datep = $params->datep : null;
		isset($params->datef) ? $this->datef = $params->datef : null;
		isset($params->type_code) ? $this->type_code = $params->type_code : $this->type_code = 'AC_OTH';
		isset($params->label) ? $this->label = $params->label : null;
		isset($params->note) ? $this->note = $params->note : null;
		isset($params->usertodo_id) ? $this->userownerid = $params->usertodo_id : null;
		isset($params->userdone_id) ? $this->userdoneid = $params->userdone_id : null;
		isset($params->location) ? $this->location = $params->location : null;
		isset($params->company_id) ? $this->socid=$params->company_id : null;
		isset($params->contact_id) ? $this->contact_id=$params->contact_id : null;
		isset($params->durationp) ? $this->durationp=$params->durationp : null;
		isset($params->percentage) ? $this->percentage=$params->percentage : null;
		isset($params->code) ? $this->code=$params->code : null;
		isset($params->fulldayevent) ? $this->fulldayevent=$params->fulldayevent : null;
		isset($params->transparency) ? $this->transparency=$params->transparency : null;
		isset($params->project_id) ? $this->fk_project=$params->project_id : null;
	}
}
