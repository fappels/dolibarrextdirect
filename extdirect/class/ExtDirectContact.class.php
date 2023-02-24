<?PHP
/*
 * Copyright (C) 2012-2014       Francis Appels <francis.appels@z-application.com>
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
 *  \file       htdocs/extdirect/class/ExtDirectContact.class.php
 *  \brief      Sencha Ext.Direct contacts remoting class
 */

require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
dol_include_once('/extdirect/class/extdirect.class.php');

/** ExtDirectContact class
 *
 * Class to access contacts with CRUD methods to connect to Extjs or sencha touch using Ext.direct connector
 */
class ExtDirectContact extends Contact
{
	private $_user;
	private $_enabled = false;

	/**
	 * constructor
	 *
	 * @param string $login user name
	 * @return number
	 */
	public function __construct($login)
	{
		global $user, $conf, $db, $langs;

		if (!empty($login)) {
			if ((is_object($login) && get_class($db) == get_class($login)) || $user->id > 0 || $user->fetch('', $login, '', 1) > 0) {
				$user->getrights();
				$this->_enabled = !empty($conf->societe->enabled) && isset($user->rights->societe->contact->lire);
				$this->_user = $user;  //commande.class uses global user
				if (isset($this->_user->conf->MAIN_LANG_DEFAULT)) {
					$langs->setDefaultLang($this->_user->conf->MAIN_LANG_DEFAULT);
				} else {
					$langs->setDefaultLang(empty($conf->global->MAIN_LANG_DEFAULT) ? 'auto' : $conf->global->MAIN_LANG_DEFAULT);
				}
				$langs->load("main");
				$langs->load("dict");
				$langs->load("errors");
				$langs->load("companies");
				parent::__construct($db);
			}
		}
	}

	/**
	 *    Load contact from database into memory
	 *
	 *    @param    stdClass    $params filter[]->property->id  Id's of contacts to load
	 *    @return     stdClass result data or error string
	 */
	public function readContact(stdClass $params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->societe->contact->lire)) return PERMISSIONERROR;

		$results = array();
		$result = 0;
		if (isset($params->filter)) {
			foreach ($params->filter as $key => $filter) {
				if ($filter->property == 'id' && $filter->value > 0) {
					if (($result = $this->fetch($filter->value)) < 0)   return ExtDirect::getDolError($result, $this->errors, $this->error);;
				} elseif ($filter->property == 'company_id') {
					// fetch first contact
					$sql = 'SELECT rowid as id';
					$sql .= ' FROM '.MAIN_DB_PREFIX.'socpeople';
					$sql .= ' WHERE fk_soc = '.$filter->value;
					$sql .= ' ORDER BY rowid';
					$sql .= $this->db->plimit(1, 0);

					$resql=$this->db->query($sql);
					if ($resql) {
						$num=$this->db->num_rows($resql);
						if ($num) {
							$obj = $this->db->fetch_object($resql);
							if (($result = $this->fetch($obj->id)) < 0)   return ExtDirect::getDolError($result, $this->errors, $this->error);;
						} else {
							return array(); // no results
						}
					} else {
						$error="Error ".$this->db->lasterror();
						dol_syslog(get_class($this)."::readContactList ".$error, LOG_ERR);
						return SQLERROR;
					}
				}
				if ($result > 0) {
					if (!isset($this->_user->rights->societe->contact->lire)) return PERMISSIONERROR;
					$row = new stdClass;
					$row->id                = (int) $this->id;
					$row->civility_id       = $this->civility_id;
					$row->lastname          = $this->lastname;
					$row->firstname         = $this->firstname;
					$row->address           = $this->address;
					$row->zip               = $this->zip;
					$row->town              = $this->town;
					$row->state             = $this->state;
					$row->state_id          = $this->state_id;
					$row->country           = $this->country;
					$row->country_id        = $this->country_id;
					$row->company_id        = (int) $this->socid;
					$row->companyname       = $this->socname;
					$row->poste             = $this->poste;
					$row->phone_pro         = $this->phone_pro;
					$row->fax               = $this->fax;
					$row->phone_perso       = $this->phone_perso;
					$row->phone_mobile      = $this->phone_mobile;
					$row->skype             = $this->skype;
					$row->email             = $this->email;
					$row->jabberid          = $this->jabberid;
					$row->priv              = (int) $this->priv;
					$row->birthday          = $this->birthday;
					$row->birthday_alert    = $this->birthday_alert;
					$row->note              = $this->note;
					$row->default_lang      = $this->default_lang;
					$row->user_id           = (int) $this->user_id;
					$row->user_login        = $this->user_login;
					$row->canvas            = $this->canvas;

					array_push($results, $row);
				}
			}
		}
		return $results;
	}

	/**
	 * public method to read available contact optionals (extra fields)
	 *
	 * @return stdClass result data or ERROR
	 */
	public function readOptionalModel()
	{
		if (!isset($this->db)) return CONNECTERROR;

		return ExtDirect::readOptionalModel($this);
	}

	/**
	 * public method to read contact (extra fields) from database
	 *
	 *    @param    stdClass    $param  filter with elements:
	 *                                  id  Id of contact to load
	 *
	 *    @return     stdClass result data or -1
	 */
	public function readOptionals(stdClass $param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->societe->contact->lire)) return PERMISSIONERROR;
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
		if (!isset($this->_user->rights->societe->contact->creer)) return PERMISSIONERROR;
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
		if (!isset($this->_user->rights->societe->contact->creer)) return PERMISSIONERROR;
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
	 *    Load contact parties list from database into memory, keep properties of same kind together
	 *
	 *    @param    stdClass    $params     property filter with properties and values:
	 *                                      id          Id of third party to load
	 *                                      company_id  commercial status of third party
	 *                                      town        Town of third party
	 *                                      content     filter on part of name, label or town value
	 *                                      property sort with properties field names and directions:
	 *                                      property limit for paging with sql LIMIT and START values
	 *
	 *    @return     stdClass result data or -1
	 */
	public function readContactList(stdClass $params)
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
		$sqlFields = 'SELECT c.rowid as id, s.rowid as company_id, s.nom as companyname, c.lastname, c.firstname,c.zip as zip, c.town as town, c.statut';
		$sqlFrom = ' FROM '.MAIN_DB_PREFIX.'socpeople as c';
		$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe as s ON c.fk_soc = s.rowid';
		if ($filterSize > 0) {
			// TODO improve sql command to allow random property type
			$sqlWhere = ' WHERE (';
			foreach ($params->filter as $key => $filter) {
				if ($filter->property == 'id')
					$sqlWhere .= 'c.rowid = '.$filter->value;
				elseif ($filter->property == 'company_id')
					$sqlWhere .= '(s.rowid = '.$filter->value.' AND s.entity IN ('.getEntity('societe', 1).'))';
				elseif ($filter->property == 'town') {
					$sqlWhere .= "c.town = '".$this->db->escape($filter->value)."'";
				} elseif ($filter->property == 'content') {
					$fields = array('c.firstname', 'c.lastname', 'c.town', 'c.zip');
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
			$sqlOrder .= "lastname ASC";
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
				dol_syslog(get_class($this)."::readContactList ".$error, LOG_ERR);
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
				$row->name          = ($obj->firstname != "") ? ($obj->firstname.' '.$obj->lastname) : ($obj->lastname);
				$row->company_id    = (int) $obj->company_id;
				$row->companyname   = $obj->companyname;
				$row->zip           = $obj->zip;
				$row->town          = $obj->town;
				if (isset($obj->statut)) {
					$row->enabled    = $obj->statut;
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
			$error="Error ".$this->db->lasterror();
			dol_syslog(get_class($this)."::readContactList ".$error, LOG_ERR);
			return -1;
		}
	}


	/**
	 * Ext.direct create method
	 *
	 * @param unknown_type $params  object or object array with contact model(s)
	 * @return Ambigous <multitype:, unknown_type>|unknown
	 */
	public function createContact($params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->societe->contact->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);

		foreach ($paramArray as &$param) {
			// prepare fields
			$this->prepareFields($param);
			// create
			if (($result = $this->create($this->_user)) < 0)    return $result;
			$param->id=$this->id;
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
	 * @param unknown_type $params object or object array with contact model(s)
	 * @return Ambigous <multitype:, unknown_type>|unknown
	 */
	public function updateContact($params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->societe->contact->creer)) return PERMISSIONERROR;

		// dolibarr update settings
		$call_trigger=1;

		$paramArray = ExtDirect::toArray($params);

		foreach ($paramArray as &$param) {
			if ($param->id) {
				$id = $param->id;
				// prepare fields
				if (($result = $this->fetch($id)) < 0)  return $result;

				$this->prepareFields($param);
				// update
				if (($result = $this->update($id, $this->_user, $call_trigger)) < 0)    return $result;
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
	 * Ext.direct method to destroy data
	 *
	 * @param unknown_type $params   object or object array with contact model(s)
	 * @return Ambigous <multitype:, unknown_type>|unknown
	 */
	public function destroyContact($params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->societe->contact->supprimer)) return PERMISSIONERROR;

		// dolibarr delete settings
		$notrigger=0;
		$paramArray = ExtDirect::toArray($params);

		foreach ($paramArray as &$param) {
			// prepare fields
			if ($param->id) {
				$this->id = $param->id;
				$this->prepareFields($param);
			}

			// delete
			if (($result = $this->delete($notrigger)) < 0)  return $result;
		}
		if (is_array($params)) {
			return $paramArray;
		} else {
			return $param;
		}
	}

	/**
	 * Ext.direct method to upload image file for contact object
	 *
	 * @param unknown_type $params object or object array with uploaded file(s)
	 * @return Array    ExtDirect response message
	 */
	public function fileUpload($params)
	{
		global $conf;
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->societe->contact->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);
		$dir = null;

		foreach ($paramArray as &$param) {
			if (isset($param['extTID'])) {
				$id = $param['extTID'];
				if ($this->fetch($id)) {
					$dir = $conf->societe->multidir_output[$this->entity].'/contact/'.dol_sanitizeFileName($this->ref);
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
		($params->civility_id) ? ($this->civilite_id = $params->civility_id) : null;
		($params->lastname) ? ($this->lastname = $params->lastname) : null;
		($params->firstname) ? ($this->firstname = $params->firstname) : null;
		($params->address) ? ($this->address = $params->address) : null;
		($params->zip) ? ($this->zip = $params->zip) : null;
		($params->town) ? ($this->town = $params->town) : null;
		($params->fax) ? ($this->fax = $params->fax) : null;
		($params->phone_perso) ? ($this->phone_perso = $params->phone_perso) : null;
		($params->skype) ? ($this->skype = $params->skype) : null;
		($params->email) ? ($this->email = $params->email) : null;
		($params->state) ? ($this->state=$params->state) : null;
		($params->state_id) ? ($this->state_id=$params->state_id) : null;
		($params->country) ? ($this->country=$params->country) : null;
		($params->country_id) ? ($this->country_id=$params->country_id) : null;
		($params->company_id) ? ($this->socid=$params->company_id) : null;
		($params->companyname) ? ($this->socname=$params->companyname) :  null;
		($params->poste) ? ($this->poste=$params->poste) : null;
		($params->phone_pro) ? ($this->phone_pro=$params->phone_pro) : null;
		($params->phone_mobile) ? ($this->phone_mobile=$params->phone_mobile) : null;
		($params->jabberid) ? ($this->jabberid=$params->jabberid) : null;
		($params->priv) ? ($this->priv=$params->priv) : null;
		($params->birthday) ? ($this->birthday=$params->birthday) : null;
		($params->birthday_alert) ? ($this->birthday_alert=$params->birthday_alert) : null;
		($params->note) ? ($this->note=$params->note) : null;
		($params->default_lang) ? ($this->default_lang=$params->default_lang) : null;
		($params->user_id) ? ($this->user_id=$params->user_id) : null;
		($params->user_login) ? ($this->user_login=$params->user_login) : null;
		($params->canvas) ? ($this->canvas=$params->canvas) : null;
	}
}
