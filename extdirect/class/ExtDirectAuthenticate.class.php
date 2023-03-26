<?php
/*
 * Copyright (C) 2013-2023  Francis Appels <francis.appels@z-application.com>
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
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('/extdirect/class/extdirect.class.php');
dol_include_once('/extdirect/core/modules/modExtDirect.class.php');

/** ExtDirectConnect class
 *
 * Class to with methods to connect to Extjs or sencha touch using Ext.direct connector
 */
class ExtDirectAuthenticate extends ExtDirect
{
	private $_user;

	/** Constructor
	 *
	 * @param string $login user name
	 */
	public function __construct($login)
	{
		global $db, $conf, $mysoc;
		// clear session
		$_SESSION['dol_login'] = null;
		$this->_user = new User($db);
		// set global $mysoc required for price calculation
		$mysoc = new Societe($db);
		$mysoc->setMysoc($conf);
		parent::__construct($db);
	}

	/**
	 * Ext.direct method to create app generated uuid and name in dolibarr system. System
	 * will have to asign user and acknowledge id (access key) to the application, which can be read afterwards
	 *
	 * @param unknown_type $params object or object array with with 'requestid' connection requestor identifcation
	 *                             'app_id' app uuid
	 *                             'app_name' app name
	 *                             'dev_platform' device platform
	 *                             'dev_type' device version
	 *
	 * @return return mixed stdClass  or int <0 if error
	 */
	public function createAuthentication($params)
	{
		$paramArray = ExtDirect::toArray($params);
		foreach ($paramArray as &$param) {
			if (!empty($param->ack_id)) return PARAMETERERROR;
			$this->prepareAuthenticationFields($param);
			// check if already acknowledged, return DUPLICATEERROR if so
			if (($res = $this->fetch(0, $this->app_id)) < 0) return ExtDirect::getDolError($res, $this->errors, $this->error);
			if (!empty($this->ack_id)) return DUPLICATEERROR;
			if (empty($this->id)) {
				// create user app record
				$this->fk_user=null;
				if (($res = $this->create($this->_user)) < 0) return ExtDirect::getDolError($res, $this->errors, $this->error);
				$param->id= (int) $this->id;
			}
		}

		if (is_array($params)) {
			return $paramArray;
		} else {
			return $param;
		}
	}

	/**
	 * Ext.direct method to get application uuid and name to dolibarr system with user asigned.
	 *
	 * @param   stdClass    $param  filter with elements:
	 *                              app_id  app_id of application to get authentication info from
	 *                              ack_id  access key to get authentication info and start a login session
	 * @return return mixed stdClass if success or int <0 if error
	 */
	public function readAuthentication(stdClass $param)
	{
		global $conf, $mysoc, $site_cookie_samesite, $site_cookie_secure;

		if (!isset($this->db)) return CONNECTERROR;

		$result = new stdClass;
		$ack_id = '';
		$app_id = '';
		$app_version = '';

		$moduleInfo = new modExtDirect($this->db);

		if (isset($param->filter)) {
			foreach ($param->filter as $key => $filter) {
				if ($filter->property == 'ack_id') $ack_id=$filter->value;
				elseif ($filter->property == 'app_id') $app_id=$filter->value;
				elseif ($filter->property == 'app_version') $app_version=$filter->value;
			}
		}
		// check if server user is set, if not return empty result
		if (($res = $this->fetch(0, $app_id, $ack_id)) < 0) return ExtDirect::getDolError($res, $this->errors, $this->error);
		if (empty($this->fk_user) || ($this->fk_user < 0)) {
			return $result; //empty result
		} else {
			if (empty($this->ack_id)) {
				// user set by admin, but not auto acknowledged, generate access key
				$this->ack_id = uniqid('llx', true);
			}
		}
		$this->_user->fetch($this->fk_user);
		// update last connect date only for old app version
		$this->date_last_connect=dol_now();
		if (empty($app_version)) {
			$res = $this->update($this->_user);
		} else {
			$res = 0;
		}
		if ($res < 0) {
			return ExtDirect::getDolError($res, $this->errors, $this->error);
		} else {
			// only login with valid access key
			if ($ack_id == $this->ack_id) {
				$_SESSION['dol_login'] = $this->_user->login;
			}
			$tmpEntity = $conf->entity;
			if (isset($this->entity) && ($this->entity > 0)) {
				$_SESSION['dol_entity'] = $this->entity;
				$conf->entity = $this->entity;
			} elseif (isset($this->_user->entity) && ($this->_user->entity > 0)) {
				// backward compatiblity
				$_SESSION['dol_entity'] = $this->_user->entity;
				$conf->entity = $this->_user->entity;
			} else {
				$_SESSION['dol_entity'] = 1;
				$conf->entity = 1;
			}
			if ($tmpEntity != $conf->entity) {
				$conf->setValues($this->db);
				$mysoc->setMysoc($conf); // get company name of entity
			}
			$result->id = (int) $this->id;
			$result->ack_id = $this->ack_id;
			$result->app_id = $this->app_id;
			$result->fk_user = $this->fk_user;
			$result->app_name = $this->app_name;
			$result->requestid = $this->requestid;
			$result->datec = $this->datec;
			$result->date_last_connect = $this->date_last_connect;
			$result->dev_platform = $this->dev_platform;
			$result->dev_type = $this->dev_type;
			$result->username = $this->_user->firstname.($this->_user->firstname?($this->_user->lastname?' ':''):'').$this->_user->lastname;
			$result->connector_id = $moduleInfo->numero;
			$result->connector_name = $moduleInfo->name;
			$result->connector_description = $moduleInfo->description;
			$result->connector_version = $moduleInfo->version;
			$result->connector_compatibility = ExtDirect::checkDolVersion(1);
			$result->dolibarr_version = ExtDirect::checkDolVersion();
			$result->home_country_id = $mysoc->country_id;
			$result->home_state_id = $mysoc->state_id;
			$result->home_name = $mysoc->name;
			$result->home_localtax1_assuj = $mysoc->localtax1_assuj;
			$result->home_localtax2_assuj = $mysoc->localtax2_assuj;
			$result->timezone_offset = getServerTimeZoneInt('now');
			$result->timezone = getServerTimeZoneString();
			$result->webview_name = $this->webview_name;
			$result->webview_version = $this->webview_version;
			$result->identify = $this->identify;
			// debug info can be removed for production
			$result->site_cookie_samesite = $site_cookie_samesite;
			$result->site_cookie_secure = $site_cookie_secure;
			$cookieParams = session_get_cookie_params();
			$result->session_cookie_samesite = $cookieParams['samesite'];
			$result->session_cookie_secure =  $cookieParams['secure'];
			!empty($conf->multicurrency->enabled) ? $result->multicurrency_enabled = 1 : $result->multicurrency_enabled = 0;
			return $result;
		}
	}

	/**
	 * Ext.direct method to update authorisation details.
	 *
	 * @param unknown_type $param parameter
	 *
	 * @return return  int PARAMETERERROR
	 */
	public function updateAuthentication($param)
	{
		global $conf;

		if (!isset($this->db)) return CONNECTERROR;
		// dolibarr update settings

		$paramArray = ExtDirect::toArray($param);
		foreach ($paramArray as &$param) {
			// prepare fields
			if ($param->id && !empty($param->ack_id)) {
				$id = $param->id;
				if (($res = $this->fetch($id)) < 0) return ExtDirect::getDolError($res, $this->errors, $this->error);
				// check if new app_id already used, return DUPLICATEERROR if so
				if (($res = $this->fetch(0, $param->app_id)) < 0) return ExtDirect::getDolError($res, $this->errors, $this->error);
				if ($this->id != $id) return DUPLICATEERROR;
				if ($this->prepareAuthenticationFields($param)) {
					// update
					$this->date_last_connect=dol_now();
					if (($res = $this->update($this->_user)) < 0) return ExtDirect::getDolError($res, $this->errors, $this->error);
				};
				// only login with valid access key
				$this->_user->fetch($this->fk_user);
				if ($param->ack_id == $this->ack_id) {
					$_SESSION['dol_login'] = $this->_user->login;
					$_SESSION['dol_tz'] = $param->local_timezone_offset;
					$_SESSION['dol_tz_string'] = $param->local_timezone;
					empty($param->local_dst) ? $_SESSION['dol_dst'] = 0 : $_SESSION['dol_dst'] = 1;
				}
				if (isset($this->entity) && ($this->entity > 0)) {
					$_SESSION['dol_entity'] = $this->entity;
					$conf->entity = $this->entity;
				} elseif (isset($this->_user->entity) && ($this->_user->entity > 0)) {
					// backward compatiblity
					$_SESSION['dol_entity'] = $this->_user->entity;
					$conf->entity = $this->_user->entity;
				} else {
					$_SESSION['dol_entity'] = 1;
					$conf->entity = 1;
				}
			} else {
				return PARAMETERERROR;
			}
		}
		if (is_array($param)) {
			return $paramArray;
		} else {
			return $param;
		}
	}

	/**
	 * Ext.direct method to delete application uuid entry.
	 *
	 * @param unknown_type $params with app id
	 * @return return mixed stdClass or int <0 if error
	 */
	public function destroyAuthentication($params)
	{
		$paramArray = ExtDirect::toArray($params);
		foreach ($paramArray as &$param) {
			// fetch id
			if (($res = $this->fetch($param->id, $param->app_id)) < 0) return ExtDirect::getDolError($res, $this->errors, $this->error);
			// if found delete
			if ($this->id) {
				$this->_user->fetch($this->fk_user);
				// delete id, if not deleted return error
				if (($res = $this->delete($this->_user)) < 0) return ExtDirect::getDolError($res, $this->errors, $this->error);
			}
		}

		if (is_array($params)) {
			return $paramArray;
		} else {
			return $param;
		}
	}

	/**
	 * private method to copy order fields into dolibarr object
	 *
	 * @param stdclass $param object with fields
	 * @return boolean true if there is an update
	 */
	private function prepareAuthenticationFields($param)
	{
		$diff = false; // difference flag, set to true if a param element diff detected
		$diff = self::prepareField($diff, $param, $this, 'requestid', 'requestid');
		$diff = self::prepareField($diff, $param, $this, 'app_id', 'app_id');
		$diff = self::prepareField($diff, $param, $this, 'app_name', 'app_name');
		$diff = self::prepareField($diff, $param, $this, 'date_last_connect', 'date_last_connect');
		$diff = self::prepareField($diff, $param, $this, 'dev_platform', 'dev_platform');
		$diff = self::prepareField($diff, $param, $this, 'dev_type', 'dev_type');
		$diff = self::prepareField($diff, $param, $this, 'webview_name', 'webview_name');
		$diff = self::prepareField($diff, $param, $this, 'webview_version', 'webview_version');
		$diff = self::prepareField($diff, $param, $this, 'identify', 'identify');

		return $diff;
	}
}
