<?php
/*
 * Copyright (C) 2013-2014  Francis Appels <francis.appels@z-application.com>
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
 *  \file       htdocs/extdirec/class/ExtDirectAuthenticate.class.php
 *  \brief      Sencha Ext.Direct remoting class with CRUD methods to connect to Dolibarr
 */

require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
dol_include_once('/extdirect/class/extdirect.class.php');
dol_include_once('/extdirect/class/extdirectactivity.class.php');

/** ExtDirectConnect class
 *
 * Class to with methods to register app activities on the server
 */
class ExtDirectActivities extends ExtDirectActivity
{
	private $_user;

	/** Constructor
	 *
	 * @param string $login user name
	 */
	public function __construct($login)
	{
		global $conf, $langs, $user, $db;

		if (!empty($login)) {
			if ((is_object($login) && get_class($db) == get_class($login)) || $user->id > 0 || $user->fetch('', $login, '', 1) > 0) {
				$user->getrights();
				$this->_user = $user;  //commande.class uses global user
				if (isset($this->_user->conf->MAIN_LANG_DEFAULT)) {
					$langs->setDefaultLang($this->_user->conf->MAIN_LANG_DEFAULT);
				} else {
					$langs->setDefaultLang(empty($conf->global->MAIN_LANG_DEFAULT) ? 'auto' : $conf->global->MAIN_LANG_DEFAULT);
				}
				$langs->load("main");
				$langs->load("dict");
				$langs->load("errors");
				$langs->load("extdirect@extdirect");
				parent::__construct($db);
			}
		}
	}

	/**
	 * Ext.direct method to store an app activity in dolibarr system.
	 *
	 * @param unknown_type $params  object or object array with with 'activity_name' name of app activity to register
	 *                              'activity_id' related dolibarr item id (ex product or customer order)
	 *                              'datec' datetime of activity
	 *                              'status' current status of activity (ex BUSY, DONE, CANCEL, VALIDATE)
	 *
	 * @return return mixed stdClass  or int <0 if error
	 */
	public function createActivity($params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		$paramArray = ExtDirect::toArray($params);
		foreach ($paramArray as &$param) {
			$this->prepareActivityFields($param);
			// check if already acknowledged, return -1 if so
			if (($resql = $this->create($this->_user, 0)) < 0) return $resql;
			$param->id= (int) $this->id;
		}

		if (is_array($params)) {
			return $paramArray;
		} else {
			return $param;
		}
	}

	/**
	 * Ext.direct method to get application activities.
	 *
	 * @param   stdClass    $param  filter with elements:
	 *                              app_id          app_id of application to get authentication info from
	 *                              activity_name   name of app activity to register
	 *                              activity_id     related dolibarr item id (ex product or customer order)
	 * @return return mixed stdClass if success or int <0 if error
	 */
	public function readActivities(stdClass $param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		$results = array();

		$listfilter = "";
		if (isset($param->filter)) {
			foreach ($param->filter as $key => $filter) {
				if ($filter->property == 'app_id') {
					$listfilter .= " AND ea.`app_id` = '".$filter->value."'";
				} elseif ($filter->property == 'activity_name') {
					$listfilter .= " AND `activity_name` = '".$filter->value."'";
				} elseif (($filter->property == 'activity_id') && is_numeric($filter->value)) {
					$listfilter .= " AND `activity_id` = ".$filter->value;
				}
			}
		}

		// check if server user is set, if not return empty result
		if (($resql = $this->fetchList($listfilter, 'rowid')) < 0) return $resql;
		if (! empty($this->dataset)) {
			foreach ($this->dataset as $data) {
				$row = new stdClass;
				$row->id = (int) $data['rowid'];
				$row->app_id = (int) $data['app_id'];
				$row->app_version = $data['app_version'];
				$row->activity_name = $data['activity_name'];
				$row->activity_id = $data['activity_id'];
				$row->datec = $this->db->jdate($data['datec']);
				$row->status  = $data['status'];
				$row->requestid  = $data['requestid'];
				array_push($results, $row);
			}
			return $results;
		}
	}

	/**
	 * Ext.direct method to update authorisation details, update not possible.
	 *
	 * @param unknown_type $params parameter
	 *
	 * @return return  int PARAMETERERROR
	 */
	public function updateActivity($params)
	{
		return PARAMETERERROR;// no update possible
	}

	/**
	 * Ext.direct method to delete application uuid entry.
	 *
	 * @param unknown_type $params na
	 * @return return int PARAMETERERROR
	 */
	public function destroyActivity($params)
	{
		return PARAMETERERROR;// no update possible
	}

	/**
	 * private method to copy order fields into dolibarr object
	 *
	 * @param stdclass $params na
	 * @return null
	 */
	private function prepareActivityFields($params)
	{
		isset($params->app_id) ? ( $this->app_id = $params->app_id ) : ( $this->app_id = null);
		isset($params->app_version) ? ( $this->app_version = $params->app_version) : ( $this->app_version = null);
		isset($params->activity_name) ? ( $this->activity_name = $params->activity_name) : ( $this->activity_name = null);
		isset($params->activity_id) ? ( $this->activity_id = $params->activity_id) : ( $this->activity_id = null);
		isset($params->status) ? ( $this->status = $params->status) : ($this->status  = null);
		isset($params->datec) ? ( $this->datec = $params->datec) : ($this->datec = null);
	}
}
