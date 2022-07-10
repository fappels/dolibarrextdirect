<?php
/* Copyright (C) 2007-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2012      Francis Appels       <francis.appels@yahoo.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *  \file       extdirect/class/extdirect.class.php
 *  \ingroup    extdirect
 *  \brief      CRUD class file (Create/Read/Update/Delete) for table extdirect_user
 *              and some common functions
 *              Initialy built by build_class_from_table on 2012-12-29 16:55
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php'; // required for showing product units in pdf's from version 4.0
require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';

/**
 * Constant to return when there is a database connection error
 */
if (!defined("CONNECTERROR"))       define("CONNECTERROR", -1000);
/**
 * Constant to return when the dolibarr user has not the correct permissions
 */
if (!defined("PERMISSIONERROR"))    define("PERMISSIONERROR", -1001);
/**
 * Constant to return when there is a database sql statement error
 */
if (!defined("SQLERROR"))           define("SQLERROR", -1002);
/**
 * Constant to return when there was an error on updating data in the database
 */
if (!defined("UPDATEERROR"))        define("UPTADEERROR", -1003);
/**
 * Constant to return when there are missing or invalid parameters
 */
if (!defined("PARAMETERERROR"))     define("PARAMETERERROR", -1004);
/**
 * Constant to return when there is a vulnerability in the data
 */
if (!defined("VULNERABILITYERROR")) define("VULNERABILITYERROR", -1005);
/**
 * Constant to return when there a dolibarr version conflict
 */
if (!defined("COMPATIBILITYERROR")) define("COMPATIBILITYERROR", -1006);
/**
 * Constant to return when there are missing or invalid parameters
 */
if (!defined("DUPLICATEERROR"))     define("DUPLICATEERROR", -1007);

/**
 *  ExtDirect table CRUD and some common static functions
 */
class ExtDirect
{
	public $db;                         //!< To store db handler
	public $error;                          //!< To return error code (or message)
	public $errors=array();             //!< To return several error codes (or messages)

	public $id;

	public $fk_user;
	public $app_id;
	public $app_name;
	public $ack_id;
	public $requestid;
	public $datec='';
	public $date_last_connect='';
	public $dev_platform;
	public $dev_type;
	public $webview_name;
	public $webview_version;
	public $identify;
	// array with multiple records
	public $dataset=array();

	/**
	 *  Constructor
	 *
	 *  @param  DoliDb      $db      Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		return 1;
	}

	/**
	 *  Create object into database
	 *
	 *  @param  User    $user        User that create
	 *  @param  int     $notrigger   0=launch triggers after, 1=disable triggers
	 *  @return int                  <0 if KO, Id of created object if OK
	 */
	public function create($user, $notrigger = 0)
	{
		global $conf, $langs;
		$error=0;

		if ($conf->global->DIRECTCONNECT_AUTO_ASIGN) {
			$this->fk_user = $conf->global->DIRECTCONNECT_AUTO_USER;
			$this->ack_id = uniqid('llx', true);
		}

		// Clean parameters

		if (isset($this->fk_user)) $this->fk_user=trim($this->fk_user);
		if (isset($this->app_id)) $this->app_id=trim($this->app_id);
		if (isset($this->app_name)) $this->app_name=trim($this->app_name);
		if (isset($this->ack_id)) $this->ack_id=trim($this->ack_id);
		if (isset($this->requestid)) $this->requestid=trim($this->requestid);
		if (isset($this->dev_platform)) $this->dev_platform=trim($this->dev_platform);
		if (isset($this->dev_type)) $this->dev_type=trim($this->dev_type);
		if (isset($this->webview_name)) $this->dev_type=trim($this->webview_name);
		if (isset($this->webview_version)) $this->dev_type=trim($this->webview_version);

		// Check parameters
		// Put here code to add control on parameters values

		// Insert request
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."extdirect_user(";
		$sql.= "fk_user,";
		$sql.= "app_id,";
		$sql.= "app_name,";
		$sql.= "ack_id,";
		$sql.= "requestid,";
		$sql.= "datec,";
		$sql.= "date_last_connect,";
		$sql.= "dev_platform,";
		$sql.= "dev_type,";
		$sql.= "webview_name,";
		$sql.= "webview_version,";
		$sql.= "identify";
		$sql.= ") VALUES (";
		$sql.= " ".(! isset($this->fk_user)?'NULL':"'".(int) $this->fk_user."'").",";
		$sql.= " ".(! isset($this->app_id)?'NULL':"'".$this->db->escape($this->app_id)."'").",";
		$sql.= " ".(! isset($this->app_name)?'NULL':"'".$this->db->escape($this->app_name)."'").",";
		$sql.= " ".(! isset($this->ack_id)?'NULL':"'".$this->db->escape($this->ack_id)."'").",";
		$sql.= " ".(! isset($this->requestid)?'NULL':"'".$this->db->escape($this->requestid)."'").",";
		$sql.= " '".$this->db->idate(dol_now())."',";
		$sql.= " '".$this->db->idate(dol_now())."',";
		$sql.= " ".(! isset($this->dev_platform)?'NULL':"'".$this->db->escape($this->dev_platform)."'").",";
		$sql.= " ".(! isset($this->dev_type)?'NULL':"'".$this->db->escape($this->dev_type)."'").",";
		$sql.= " ".(! isset($this->webview_name)?'NULL':"'".$this->db->escape($this->webview_name)."'").",";
		$sql.= " ".(! isset($this->webview_version)?'NULL':"'".$this->db->escape($this->webview_version)."'").",";
		$sql.= " ".(! isset($this->identify)?'NULL':"'".(int) $this->identify."'")."";
		$sql.= ")";

		$this->db->begin();

		$resql=$this->db->query($sql);
		if (! $resql) {
			$error++; $this->errors[]="Error ".$this->db->lasterror();
		}

		if (! $error) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."extdirect_user");

			if (! $notrigger) {
				// Uncomment this and change MYOBJECT to your own tag if you
				// want this action call a trigger.

				//// Call triggers
				//include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
				//$interface=new Interfaces($this->db);
				//$result=$interface->run_triggers('MYOBJECT_CREATE',$this,$user,$langs,$conf);
				//if ($result < 0) { $error++; $this->errors=$interface->errors; }
				//// End call triggers
			}
		}

		// Commit or rollback
		if ($error) {
			foreach ($this->errors as $errmsg) {
				dol_syslog(get_class($this)."::create ".$errmsg, LOG_ERR);
				$this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		} else {
			$this->db->commit();
			return $this->id;
		}
	}

	/**
	 *  Load all objects in memory from database
	 *
	 *  @param  string      $filter     where clause string
	 *  @param  string      $orderBy    order by string
	 *  @return int             <0 if KO, >0 if OK
	 */
	public function fetchList($filter = '', $orderBy = '')
	{
		global $langs;

		$sql = "SELECT";
		$sql.= " t.rowid,";

		$sql.= " t.fk_user,";
		$sql.= " t.app_id,";
		$sql.= " t.app_name,";
		$sql.= " t.ack_id,";
		$sql.= " t.requestid,";
		$sql.= " t.datec,";
		$sql.= " t.date_last_connect,";
		$sql.= " t.dev_platform,";
		$sql.= " t.dev_type,";
		$sql.= " t.webview_name,";
		$sql.= " t.webview_version,";
		$sql.= " t.identify";

		$sql.= " FROM ".MAIN_DB_PREFIX."extdirect_user as t";
		if (!empty($filter)) {
			$sql.= " WHERE ".$filter;
		}
		if (!empty($orderBy)) {
			$sql.= " ORDER BY ".$orderBy;
		}

		$resql=$this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			$this->dataset=null;
			while ($i < $num) {
				$obj = $this->db->fetch_object($resql);
				$this->dataset[$i]['rowid']     = $obj->rowid;
				$this->dataset[$i]['fk_user']   = $obj->fk_user;
				$this->dataset[$i]['app_id']    = $obj->app_id;
				$this->dataset[$i]['app_name']  = $obj->app_name;
				$this->dataset[$i]['ack_id']    = $obj->ack_id;
				$this->dataset[$i]['requestid']     = $obj->requestid;
				$this->dataset[$i]['datec']     = $obj->datec;
				$this->dataset[$i]['date_last_connect'] = $obj->date_last_connect;
				$this->dataset[$i]['dev_platform']  = $obj->dev_platform;
				$this->dataset[$i]['dev_type']  = $obj->dev_type;
				$this->dataset[$i]['webview_name']  = $obj->webview_name;
				$this->dataset[$i]['webview_version']  = $obj->webview_version;
				$this->dataset[$i]['identify']  = $obj->identify;
				$i++;
			}
			$this->db->free($resql);

			return 1;
		} else {
			$this->error="Error ".$this->db->lasterror();
			dol_syslog(get_class($this)."::read ".$this->error, LOG_ERR);
			return -1;
		}
	}

	/**
	 *  Load object in memory from database
	 *
	 *  @param  int     $id    rowid object
	 *  @param  string  $app_id    application id
	 *  @param  string  $ack_id    acknowledge id
	 *  @return int             <0 if KO, >0 if OK
	 */
	public function fetch($id = 0, $app_id = '', $ack_id = '')
	{
		global $langs;
		$sql = "SELECT";
		$sql.= " t.rowid,";
		$sql.= " t.fk_user,";
		$sql.= " t.app_id,";
		$sql.= " t.app_name,";
		$sql.= " t.ack_id,";
		$sql.= " t.requestid,";
		$sql.= " t.datec,";
		$sql.= " t.date_last_connect,";
		$sql.= " t.dev_platform,";
		$sql.= " t.dev_type,";
		$sql.= " t.webview_name,";
		$sql.= " t.webview_version,";
		$sql.= " t.identify";

		$sql.= " FROM ".MAIN_DB_PREFIX."extdirect_user as t";
		if ($id) {
			$sql.= " WHERE t.rowid = ".$id;
		} elseif (!empty($app_id)) {
			$sql.= " WHERE t.app_id = '".$app_id."'";
		} elseif (!empty($ack_id)) {
			$sql.= " WHERE t.ack_id = '".$ack_id."'";
		} elseif (!empty($requestid)) {
			$sql.= " WHERE t.requestid = '".$requestid."'";
		} else {
			return PARAMETERERROR;
		}

		$resql=$this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql)) {
				$obj = $this->db->fetch_object($resql);

				$this->id    = $obj->rowid;
				$this->fk_user = $obj->fk_user;
				$this->app_id = $obj->app_id;
				$this->app_name = $obj->app_name;
				$this->ack_id = $obj->ack_id;
				$this->requestid = $obj->requestid;
				$this->datec = $this->db->jdate($obj->datec);
				$this->date_last_connect = $this->db->jdate($obj->date_last_connect);
				$this->dev_platform = $obj->dev_platform;
				$this->dev_type = $obj->dev_type;
				$this->webview_name = $obj->webview_name;
				$this->webview_version = $obj->webview_version;
				$this->identify = $obj->identify;

				return 1;
			} else {
				return 0;
			}

			$this->db->free($resql);
		} else {
			$this->error="Error ".$this->db->lasterror();
			dol_syslog(get_class($this)."::fetch ".$this->error, LOG_ERR);
			return -1;
		}
	}

	/**
	 *  Update object into database
	 *
	 *  @param  User    $user        User that modify
	 *  @param  int     $notrigger   0=launch triggers after, 1=disable triggers
	 *  @return int                  <0 if KO, >0 if OK
	 */
	public function update($user = 0, $notrigger = 0)
	{
		global $conf, $langs;
		$error=0;

		// Clean parameters
		if (isset($this->fk_user)) $this->fk_user=trim($this->fk_user);
		if (isset($this->app_id)) $this->app_id=trim($this->app_id);
		if (isset($this->app_name)) $this->app_name=trim($this->app_name);
		if (isset($this->ack_id)) $this->ack_id=trim($this->ack_id);
		if (isset($this->requestid)) $this->requestid=trim($this->requestid);
		if (isset($this->dev_platform)) $this->dev_platform=trim($this->dev_platform);
		if (isset($this->dev_type)) $this->dev_type=trim($this->dev_type);
		if (isset($this->webview_name)) $this->webview_name=trim($this->webview_name);
		if (isset($this->webview_version)) $this->webview_version=trim($this->webview_version);

		// Check parameters
		// Put here code to add control on parameters values

		// Update request
		$sql = "UPDATE ".MAIN_DB_PREFIX."extdirect_user SET";
		$sql.= " fk_user=".(isset($this->fk_user)?(int) $this->fk_user:"null").",";
		$sql.= " app_id=".(isset($this->app_id)?"'".$this->db->escape($this->app_id)."'":"null").",";
		$sql.= " app_name=".(isset($this->app_name)?"'".$this->db->escape($this->app_name)."'":"null").",";
		$sql.= " ack_id=".(isset($this->ack_id)?"'".$this->db->escape($this->ack_id)."'":"null").",";
		$sql.= " requestid=".(isset($this->requestid)?"'".$this->db->escape($this->requestid)."'":"null").",";
		$sql.= " datec=".(dol_strlen($this->datec)!=0 ? "'".$this->db->idate($this->datec)."'" : 'null').",";
		$sql.= " date_last_connect=".(dol_strlen($this->date_last_connect)!=0 ? "'".$this->db->idate($this->date_last_connect)."'" : 'null').",";
		$sql.= " dev_platform=".(isset($this->dev_platform)?"'".$this->db->escape($this->dev_platform)."'":"null").",";
		$sql.= " dev_type=".(isset($this->dev_type)?"'".$this->db->escape($this->dev_type)."'":"null").",";
		$sql.= " webview_name=".(isset($this->webview_name)?"'".$this->db->escape($this->webview_name)."'":"null").",";
		$sql.= " webview_version=".(isset($this->webview_version)?"'".$this->db->escape($this->webview_version)."'":"null").",";
		$sql.= " identify=".(isset($this->identify)?"'".(int) $this->identify."'":"null")."";

		$sql.= " WHERE rowid=".$this->id;

		$this->db->begin();

		$resql = $this->db->query($sql);
		if (! $resql) {
			$error++; $this->errors[]="Error ".$this->db->lasterror();
		}

		if (! $error) {
			if (! $notrigger) {
				// Uncomment this and change MYOBJECT to your own tag if you
				// want this action call a trigger.

				//// Call triggers
				//include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
				//$interface=new Interfaces($this->db);
				//$result=$interface->run_triggers('MYOBJECT_MODIFY',$this,$user,$langs,$conf);
				//if ($result < 0) { $error++; $this->errors=$interface->errors; }
				//// End call triggers
			}
		}

		// Commit or rollback
		if ($error) {
			foreach ($this->errors as $errmsg) {
				dol_syslog(get_class($this)."::update ".$errmsg, LOG_ERR);
				$this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		} else {
			$this->db->commit();
			return 1;
		}
	}


	/**
	 *  Delete object in database
	 *
	 *  @param  User    $user        User that delete
	 *  @param  int     $notrigger   0=launch triggers after, 1=disable triggers
	 *  @return int                  <0 if KO, >0 if OK
	 */
	public function delete($user, $notrigger = 0)
	{
		global $conf, $langs;
		$error=0;

		$this->db->begin();

		if (! $error) {
			if (! $notrigger) {
				// Uncomment this and change MYOBJECT to your own tag if you
				// want this action call a trigger.

				//// Call triggers
				//include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
				//$interface=new Interfaces($this->db);
				//$result=$interface->run_triggers('MYOBJECT_DELETE',$this,$user,$langs,$conf);
				//if ($result < 0) { $error++; $this->errors=$interface->errors; }
				//// End call triggers
			}
		}

		if (! $error) {
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."extdirect_user";
			$sql.= " WHERE rowid=".$this->id;

			$resql = $this->db->query($sql);
			if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }
		}

		// Commit or rollback
		if ($error) {
			foreach ($this->errors as $errmsg) {
				dol_syslog(get_class($this)."::delete ".$errmsg, LOG_ERR);
				$this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		} else {
			$this->db->commit();
			return 1;
		}
	}

	/**
	 *  Create a tooltip with connection details
	 *
	 *	@param	ExtDirect	$object						ExtDirect object
	 *	@param	string		$option						No options available
	 *  @param	int  		$notooltip					1=Disable tooltip
	 *  @param  string  	$morecss            		Add more css on link
	 *	@return	string								String with URL
	 */
	public function getNomUrl(ExtDirect $object, $option = '', $notooltip = 0, $morecss = '')
	{
		global $conf, $langs, $hookmanager;

		if (! empty($conf->dol_no_mouse_hover)) $notooltip=1;   // Force disable tooltips

		$result = '';

		$label = '<u>' . $langs->trans("ConnectionDetails") . '</u>';
		$label.= '<br>';
		$label.= '<b>' . $langs->trans('DevPlatform') . ':</b> ' . $object->dev_platform;
		$label.= '<br>';
		$label.= '<b>' . $langs->trans('DevType') . ':</b> ' . $object->dev_type;
		$label.= '<br>';
		$label.= '<b>' . $langs->trans('WebviewName') . ':</b> ' . $object->webview_name;
		$label.= '<br>';
		$label.= '<b>' . $langs->trans('WebviewVersion') . ':</b> ' . $object->webview_version;

		$linkclose='';
		if (empty($notooltip)) {
			if (! empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER)) {
				$label=$langs->trans("ConnectionDetails");
				$linkclose.=' alt="'.dol_escape_htmltag($label, 1).'"';
			}
			$linkclose.=' title="'.dol_escape_htmltag($label, 1).'"';
			$linkclose.=' class="classfortooltip'.($morecss?' '.$morecss:'').'"';
		} else $linkclose = ($morecss?' class="'.$morecss.'"':'');

		$linkstart = '<a' . $linkclose . '>';
		$linkend='</a>';

		$result .= $linkstart;
		$result .= $object->requestid;

		$result .= $linkend;

		global $action;
		$hookmanager->initHooks(array('extdirectdao'));
		$parameters=array('id'=>$this->id, 'getnomurl'=>$result);
		$reshook=$hookmanager->executeHooks('getNomUrl', $parameters, $this, $action);    // Note that $action and $object may have been modified by some hooks
		if ($reshook > 0) $result = $hookmanager->resPrint;
		else $result .= $hookmanager->resPrint;

		return $result;
	}

	/**
	 * method to convert extdirect parameters to array of stdclass
	 *
	 * @param unknown_type $params can be array of stdclass or stdclass
	 *
	 * @return return array of stdClass
	 */
	public static function toArray($params)
	{

		if (is_object($params)) {
			$paramArray[0]=$params;
		} else {
			$paramArray=$params;
		}
		return $paramArray;
	}

	/**
	 * method to check dolibarr compatibility
	 *
	 * @param Number $validate 0 = return version, 1 = return validation
	 * @param string $minVersion >=
	 * @param string $maxVersion <=
	 *
	 * @return return validation 0 (not valid) or 1 (valid) or string with major.minor version
	 */
	public static function checkDolVersion($validate = 0, $minVersion = '', $maxVersion = '')
	{
		$dolVersion = versiondolibarrarray();
		$dolMajorMinorVersion = $dolVersion[0].'.'.$dolVersion[1];

		if ($validate) {
			$minVersion = '4.0';
			$maxVersion = '16.0'; // tested version
		}
		if (empty($minVersion) && empty($maxVersion)) {
			return $dolMajorMinorVersion;
		} else {
			if (empty($minVersion)) $minVersion = '4.0';
			if (empty($maxVersion)) $maxVersion = '16.0'; // debugging version
			if (version_compare($minVersion, $dolMajorMinorVersion, '<=') && version_compare($maxVersion, $dolMajorMinorVersion, '>=')) {
				return 1;
			} else {
				return 0;
			}
		}
	}

	/**
	 * method to get dolibarr error detail info
	 *
	 * @param int $errorCode dolibarr error code
	 * @param Array $errors dolibarr errors array
	 * @param String $error dolibarr error string
	 *
	 * @return return String translated errorstring
	 */
	public static function getDolError($errorCode, $errors = null, $error = null)
	{
		global $langs;
		$errorText = '';

		$langs->load("errors");
		$langs->load("main");
		$langs->load("orders");
		$langs->load("products");
		$langs->load("stocks");
		$langs->load("suppliers");
		$langs->load("users");
		$langs->load("commercial");
		$langs->load("companies");
		$langs->load("deliveries");
		$langs->load("categories");
		$langs->load("productbatch");
		$langs->load("sendings");

		if (is_array($errors) && (count($errors) > 0)) {
			foreach ($errors as $error) {
				$transError = $langs->trans($error);
				$errorText = $errorText . ' ' . $transError ? $transError : $error;
			}
		} elseif (is_string($error)) {
			$transError = $langs->trans($error);
			$errorText = $transError ? $transError : $error;
		} else {
			$errorText = strval($errorCode);
		}

		return $errorText;
	}

	/**
	 * static method to convert timestamp containing date and time to timestamp containing date only
	 *
	 * @param int $timestamp timestamp with time
	 *
	 * @return return int timestamp without time
	 */
	public static function dateTimeToDate($timestamp)
	{
		if ($timestamp > 0) {
			$getDate = dol_getdate($timestamp);
			return dol_mktime(0, 0, 0, $getDate['mon'], $getDate['mday'], $getDate['year']);
		} else {
			return $timestamp;
		}
	}

	/**
	 * static method to copy field into dolibarr object element and check if changed
	 *
	 * @param boolean $diff diff status of param elements
	 * @param unknown_type $param object with fields
	 * @param unknown_type $object dolibarr object
	 * @param string $paramName param object field name, if null $param is value
	 * @param string $propertyName object property name, if null $object is value
	 * @param unknown_type $default default value
	 * @param unknown_type $paramIndex array index if paramName is array
	 * @param unknown_type $propertyIndex array index if propertyName is array
	 *
	 * @return boolean true if param $diff true or true on param element change
	 */
	public static function prepareField($diff, $param, $object, $paramName = null, $propertyName = null, $default = null, $paramIndex = null, $propertyIndex = null)
	{
		$epsilon = 0.00001;
		if (!empty($propertyName)) {
			$propertyIndex ? $propertyValue = $object->{$propertyName}[$propertyIndex] : $propertyValue = $object->$propertyName;
		} else {
			$propertyValue = $object;
		}

		if (!empty($paramName)) {
			$paramIndex ? $paramValue = $param->{$paramName}[$paramIndex] : $paramValue = $param->$paramName;
		} else {
			$paramValue = $param;
		}

		$propertySet = isset($propertyValue);
		if (is_numeric($paramValue) && (is_numeric($propertyValue) || (!isset($propertyValue)))) {
			$equal = (abs($propertyValue - $paramValue) < $epsilon);
			$paramSet = isset($paramValue);
		} else {
			$equal = ($paramValue == $propertyValue);
			$paramSet = !empty($paramValue);
		}
		if (!$paramSet && !$propertySet && isset($default)) {
			$propertyIndex ? $object->{$propertyName}[$propertyIndex] = $default : $object->$propertyName = $default;
		} elseif ($paramSet && (!$equal || !$propertySet)) {
			$propertyIndex ? $object->{$propertyName}[$propertyIndex] = $paramValue : $object->$propertyName = $paramValue;
			return true;
		}
		if ($diff) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 *	Load Dolibarr constants
	 *
	 *	@param			DoliDb		$db					Database handle
	 *	@param			stdClass	$params				filter with elements
	 *		                                            constant	name of specific constant
	 *  @param			user		$user				user
	 *  @param			array		$moduleConstants 	default module constants
	 *
	 *	@return			array of stdClass result data with specific constant value or module constants
	 */
	public static function readConstants(DoliDb $db, stdClass $params, user $user, $moduleConstants = array())
	{
		$constants =  array();
		$results = array();
		$filter = $params->filter;
		$entity = ($user->entity > 0) ? $user->entity : 1;
		if (!empty($moduleConstants)) {
			$constants += $moduleConstants;
		}

		if (isset($filter)) {
			if ($filter->property == 'constant') {
				$row = new stdClass;
				$row->constant = $filter->value;
				$row->value = dolibarr_get_const($db, $filter->value, $entity);
				array_push($results, $row);
			}
		} else {
			foreach ($constants as $constant) {
				$row = new stdClass;
				$row->constant = $constant;
				$row->value = dolibarr_get_const($db, $constant, $entity);
				array_push($results, $row);
			}
		}
		return $results;
	}

	/**
	 * Load available object Optionals (extra fields)
	 *
	 * @param   Object  $object to read model from
	 *
	 * @return array array result data
	 */
	public static function readOptionalModel($object)
	{
		$results = array();

		$extraFields = new ExtraFields($object->db);
		$optionalLabel = $extraFields->fetch_name_optionals_label($object->table_element);
		if (count($optionalLabel) > 0) {
			foreach ($optionalLabel as $name => $label) {
				$row = new stdClass;
				$row->name = $name;
				$row->label = $label;
				$row->type = $extraFields->attributes[$object->table_element]['type'][$name];
				$row->default = $extraFields->attributes[$object->table_element]['default'][$name];
				$results[] = $row;
			}
		}
		return $results;
	}

	/**
	 * Upload file to ECM
	 *
	 * @param Array     $param ExtDirect uploaded item
	 * @param String    $dir   destination folder
	 *
	 * @return Array    ExtDirect response message
	 */
	public static function fileUpload($param, $dir)
	{
		global $conf, $langs, $maxwidthsmall, $maxheightsmall, $maxwidthmini, $maxheightmini, $quality;

		$langs->load("errors");
		$response = array(
			'success' => false,
			'message' => 'File: ' . $param['file']['name'] . ' not uploaded.'
		);

		if (empty($conf->global->MAIN_UPLOAD_DOC)) {
			// block upload
			return $response;
		}

		if (is_array($param['file']) && is_uploaded_file($param['file']['tmp_name'])) {
			dol_mkdir($dir);
			if (@is_dir($dir)) {
				$newfile=$dir.'/'.dol_sanitizeFileName($param['file']['name']);
				$result = dol_move_uploaded_file($param['file']['tmp_name'], $newfile, 0, 0, $param['file']['error']);

				if (is_string($result)) {
					$errors[] = $result;
					$response = ExtDirect::getDolError($result, $errors, $result);
				} else {
					if (image_format_supported($newfile) > 0) {
						// Create thumbs
						$file_osencoded=dol_osencode($newfile);
						if (file_exists($file_osencoded)) {
							// Create small thumbs (Ratio is near 16/9)
							// Used on logon for example
							vignette($file_osencoded, $maxwidthsmall, $maxheightsmall, '_small', $quality);

							// Create mini thumbs (Ratio is near 16/9)
							// Used on menu or for setup page for example
							vignette($file_osencoded, $maxwidthmini, $maxheightmini, '_mini', $quality);
						}
					}
					$response = array(
						'success' => true,
						'message' => 'Successful upload: ' . $param['file']['name']
					);
				}
			}
		} elseif ($param['file']['error'] == 1 || $param['file']['error'] == 2) {
			$response['message'] = 'File: ' . $param['file']['name'] . ' ' . $langs->trans("ErrorFileSizeTooLarge");
		}
		return $response;
	}

	/**
	 * resultSort, sort result array on sort properties
	 *
	 * @param array $data result object array
	 * @param array $sorters array of sort properties
	 * @return array sorted result data
	 */
	public static function resultSort(array $data, array $sorters)
	{
		// convert object array to array of associative array
		foreach ($data as &$value) {
			$value = (array) $value;
		}
		// create args for php array_multisort
		$multisortArgs = array();
		foreach ($sorters as $sort) {
			if (!empty($sort->property)) {
				$tmp = array();
				foreach ($data as $key => $values) {
					$tmp[$key] = $values[$sort->property];
				}
				$multisortArgs[] = $tmp;
				if ($sort->direction == 'DESC') {
					$multisortArgs[] = SORT_DESC;
				} else {
					$multisortArgs[] = SORT_ASC;
				}
			}
		}
		$multisortArgs[] = &$data;
		// call php array_multisort and get sorted data
		call_user_func_array('array_multisort', $multisortArgs);
		$data = array_pop($multisortArgs);
		// convert array of associative array to object array
		foreach ($data as &$value) {
			$value = (object) $value;
		}
		return $data;
	}
}
