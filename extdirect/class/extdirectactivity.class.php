<?php
/* Copyright (C) 2007-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2013-2014 Francis Appels <francis.appels@yahoo.com>
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
 */

/**
 *  \file       extdirect/class/extdirectactivity.class.php
 *  \ingroup    extdirect
 *  \brief      CRUD class file (Create/Read/Update/Delete) for table extdirect_activity
 *              Initialy built by build_class_from_table on 2013-10-18 16:15
 */

// Put here all includes required by your class file
require_once DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php";
dol_include_once('/extdirect/class/extdirect.class.php');

/**
 *  Put here description of your class
 */
class ExtDirectActivity extends CommonObject
{
	public $db;                            //!< To store db handler
	public $error;                         //!< To return error code (or message)
	public $errors=array();                //!< To return several error codes (or messages)
	//var $element='extdirectactivity';         //!< Id that identify managed objects
	//var $table_element='extdirectactivity';       //!< Name of table without prefix where object is stored

	public $id;

	public $tms='';
	public $fk_user;
	public $app_id;
	public $app_version;
	public $app_name;
	public $activity_name;
	public $activity_id;
	public $datec='';
	public $status;

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
	 *  @param  User    $user        User that creates
	 *  @param  int     $notrigger   0=launch triggers after, 1=disable triggers
	 *  @return int                  <0 if KO, Id of created object if OK
	 */
	public function create($user, $notrigger = 0)
	{
		global $conf, $langs;
		$error=0;
		$this->fk_user = $user->id;
		// Clean parameters

		if (isset($this->fk_user)) $this->fk_user=$this->fk_user;
		if (isset($this->app_id)) $this->app_id=$this->app_id;
		if (isset($this->app_version)) $this->app_version=trim($this->app_version);
		if (isset($this->activity_name)) $this->activity_name=trim($this->activity_name);
		if (isset($this->activity_id)) $this->activity_id=trim($this->activity_id);
		if (isset($this->status)) $this->status=trim($this->status);

		// Check parameters
		// Put here code to add control on parameters values

		// Insert request
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."extdirect_activity(";
		$sql.= "fk_user,";
		$sql.= "app_id,";
		$sql.= "app_version,";
		$sql.= "activity_name,";
		$sql.= "activity_id,";
		$sql.= "datec,";
		$sql.= "status";
		$sql.= ") VALUES (";
		$sql.= " ".(! isset($this->fk_user)?'NULL':"'".$this->fk_user."'").",";
		$sql.= " ".(! isset($this->app_id)?'NULL':"'".$this->app_id."'").",";
		$sql.= " ".(! isset($this->app_version)?'NULL':"'".$this->db->escape($this->app_version)."'").",";
		$sql.= " ".(! isset($this->activity_name)?'NULL':"'".$this->db->escape($this->activity_name)."'").",";
		$sql.= " ".(! isset($this->activity_id)? 0 :"'".$this->activity_id."'").",";
		$sql.= " ".(! isset($this->datec) || dol_strlen($this->datec)==0?'NULL':"'".$this->db->idate($this->datec)."'").",";
		$sql.= " ".(! isset($this->status)?'NULL':"'".$this->db->escape($this->status)."'")."";
		$sql.= ")";
		$this->db->begin();
		$resql=$this->db->query($sql);
		if (! $resql) {
			$error++; $this->errors[]="Error ".$this->db->lasterror();
		}

		if (! $error) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."extdirect_activity");

			if (! $notrigger) {
				// Uncomment this and change MYOBJECT to your own tag if you
				// want this action calls a trigger.

				//// Call triggers
				//include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
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
	 *  Load all object in memory from the database
	 *
	 *  @param  string      $filter     where clause string
	 *  @param  string      $orderBy    order by string
	 *  @return int             <0 if KO, >0 if OK
	 */
	public function fetchList($filter = '', $orderBy = '')
	{
		global $langs;
		$sql = "SELECT";
		$sql.= " ea.rowid,";
		$sql.= " ea.tms,";
		$sql.= " ea.fk_user,";
		$sql.= " ea.app_id,";
		$sql.= " ea.app_version,";
		$sql.= " ea.activity_name,";
		$sql.= " ea.activity_id,";
		$sql.= " ea.datec,";
		$sql.= " ea.status,";
		$sql.= " eu.requestid,";
		$sql.= " eu.app_name,";
		$sql.= " u.firstname,";
		$sql.= " u.lastname";
		$sql.= " FROM ".MAIN_DB_PREFIX."extdirect_activity as ea, ";
		$sql.= MAIN_DB_PREFIX."extdirect_user as eu, ".MAIN_DB_PREFIX."user as u";
		$sql.= " WHERE ea.app_id = eu.app_id AND ea.fk_user = u.rowid";
		if (!empty($filter)) {
			$sql.= $filter;
		}
		if (!empty($orderBy)) {
			$sql.= " ORDER BY ".$orderBy;
		}

		$resql=$this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			$this->dataset=array();
			while ($i < $num) {
				$obj = $this->db->fetch_object($resql);
				$this->dataset[$i]['rowid']     = $obj->rowid;
				$this->dataset[$i]['tms'] = $this->db->jdate($obj->tms);
				$this->dataset[$i]['fk_user'] = $obj->fk_user;
				$this->dataset[$i]['app_id'] = $obj->app_id;
				$this->dataset[$i]['app_version']  = $obj->app_version;
				$this->dataset[$i]['activity_name'] = $obj->activity_name;
				$this->dataset[$i]['activity_id'] = $obj->activity_id;
				$this->dataset[$i]['datec'] = $obj->datec;
				$this->dataset[$i]['status'] = $obj->status;
				$this->dataset[$i]['requestid'] = $obj->requestid;
				$this->dataset[$i]['app_name'] = $obj->app_name;
				$this->dataset[$i]['firstname'] = $obj->firstname;
				$this->dataset[$i++]['lastname'] = $obj->lastname;
			}
			$this->db->free($resql);

			return 1;
		} else {
			$this->error="Error ".$this->db->lasterror();
			dol_syslog(get_class($this)."::fetch ".$this->error, LOG_ERR);
			return -1;
		}
	}

	/**
	 *  get duration of activity and add to dataset
	 *
	 *  @return int             <0 if KO, >0 if OK
	 */
	public function getDurations()
	{
		$startTime = array();
		$stopTime = array();
		$activityId = array();

		if (is_array($this->dataset) && count($this->dataset) > 0) {
			// get available activity names and init start-stop time
			$sql = "SELECT DISTINCT activity_name FROM ".MAIN_DB_PREFIX."extdirect_activity";
			$resql=$this->db->query($sql);
			if ($resql) {
				$num = $this->db->num_rows($resql);
				$i = 0;
				while ($i < $num) {
					$obj = $this->db->fetch_object($resql);
					$startTime[$obj->activity_name] = 0;
					$stopTime[$obj->activity_name] = -1;
					$activityId[$obj->activity_name] = 0;
					$i++;
				}
				$this->db->free($resql);
			} else {
				$this->error="Error ".$this->db->lasterror();
				dol_syslog(get_class($this)."::getDurations ".$this->error, LOG_ERR);
				return -1;
			}

			// calculate activity durations
			foreach ($this->dataset as &$data) {
				$data['duration'] = '';

				if ($data['status'] === 'START') {
					$startTime[$data['activity_name']] = $this->db->jdate($data['datec']);
					$stopTime[$data['activity_name']] = 0;
					$activityId[$data['activity_name']] = $data['activity_id'];
				}
				if (($data['status'] === 'VALIDATE' || $data['status'] === 'ERROR' || $data['status'] === 'CANCEL' || $data['status'] === 'DONE' || $data['status'] === 'DRAFT')
								&& $stopTime[$data['activity_name']] === 0 && $activityId[$data['activity_name']] === $data['activity_id']) {
					$stopTime[$data['activity_name']] = $this->db->jdate($data['datec']);
					$data['duration'] = $stopTime[$data['activity_name']] - $startTime[$data['activity_name']] . ' s';
					$startTime[$data['activity_name']] = 0;
					$activityId[$data['activity_name']] = 0;
				}
			}
		}
		return 1;
	}

	/**
	 *  Load object in memory from the database
	 *
	 *  @param  int     $id    Id object
	 *  @return int             <0 if KO, >0 if OK
	 */
	public function fetch($id)
	{
		global $langs;

		$sql = "SELECT";
		$sql.= " t.rowid,";
		$sql.= " t.tms,";
		$sql.= " t.fk_user,";
		$sql.= " t.app_id,";
		$sql.= " t.app_version,";
		$sql.= " t.activity_name,";
		$sql.= " t.activity_id,";
		$sql.= " t.datec,";
		$sql.= " t.status";
		$sql.= " FROM ".MAIN_DB_PREFIX."extdirect_activity as t";
		$sql.= " WHERE t.rowid = ".$id;

		$resql=$this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql)) {
				$obj = $this->db->fetch_object($resql);

				$this->id    = $obj->rowid;
				$this->tms = $this->db->jdate($obj->tms);
				$this->fk_user = $obj->fk_user;
				$this->app_id = $obj->app_id;
				$this->app_version = $obj->app_version;
				$this->activity_name = $obj->activity_name;
				$this->activity_id = $obj->activity_id;
				$this->datec = $this->db->jdate($obj->datec);
				$this->status = $obj->status;
			}
			$this->db->free($resql);

			return 1;
		} else {
			$this->error="Error ".$this->db->lasterror();
			dol_syslog(get_class($this)."::fetch ".$this->error, LOG_ERR);
			return -1;
		}
	}

	/**
	 *  Update object into database
	 *
	 *  @param  User    $user        User that modifies
	 *  @param  int     $notrigger   0=launch triggers after, 1=disable triggers
	 *  @return int                  <0 if KO, >0 if OK
	 */
	public function update($user = 0, $notrigger = 0)
	{
		global $conf, $langs;
		$error=0;
		$this->fk_user = $user->id;
		// Clean parameters

		if (isset($this->fk_user)) $this->fk_user=$this->fk_user;
		if (isset($this->app_id)) $this->app_id=$this->app_id;
		if (isset($this->app_version)) $this->app_name=trim($this->app_version);
		if (isset($this->activity_name)) $this->activity_name=trim($this->activity_name);
		if (isset($this->activity_id)) $this->activity_id=trim($this->activity_id);
		if (isset($this->status)) $this->status=trim($this->status);

		// Check parameters
		// Put here code to add a control on parameters values

		// Update request
		$sql = "UPDATE ".MAIN_DB_PREFIX."extdirect_activity SET";
		$sql.= " tms=".(dol_strlen($this->tms)!=0 ? "'".$this->db->idate($this->tms)."'" : 'null').",";
		$sql.= " fk_user=".(isset($this->fk_user)?$this->fk_user:"null").",";
		$sql.= " app_id=".(isset($this->app_id)?$this->app_id:"null").",";
		$sql.= " app_version=".(isset($this->app_version)?"'".$this->db->escape($this->app_version)."'":"null").",";
		$sql.= " activity_name=".(isset($this->activity_name)?"'".$this->db->escape($this->activity_name)."'":"null").",";
		$sql.= " activity_id=".(isset($this->activity_id)?$this->activity_id:"null").",";
		$sql.= " datec=".(dol_strlen($this->datec)!=0 ? "'".$this->db->idate($this->datec)."'" : 'null').",";
		$sql.= " status=".(isset($this->status)?"'".$this->db->escape($this->status)."'":"null")."";
		$sql.= " WHERE rowid=".$this->id;

		$this->db->begin();

		$resql = $this->db->query($sql);
		if (! $resql) {
			$error++; $this->errors[]="Error ".$this->db->lasterror();
		}

		if (! $error) {
			if (! $notrigger) {
				// Uncomment this and change MYOBJECT to your own tag if you
				// want this action calls a trigger.

				//// Call triggers
				//include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
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
	 *  @param  User    $user        User that deletes
	 *  @param  int     $notrigger   0=launch triggers after, 1=disable triggers
	 *  @return int                  <0 if KO, >0 if OK
	 */
	public function delete($user, $notrigger = 0)
	{
		global $conf, $langs;
		$error=0;
		$this->fk_user = $user->id;
		$this->db->begin();

		if (! $error) {
			if (! $notrigger) {
				// Uncomment this and change MYOBJECT to your own tag if you
				// want this action calls a trigger.

				//// Call triggers
				//include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
				//$interface=new Interfaces($this->db);
				//$result=$interface->run_triggers('MYOBJECT_DELETE',$this,$user,$langs,$conf);
				//if ($result < 0) { $error++; $this->errors=$interface->errors; }
				//// End call triggers
			}
		}

		if (! $error) {
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."extdirect_activity";
			$sql.= " WHERE rowid=".$this->id;

			dol_syslog(get_class($this)."::delete sql=".$sql);
			$resql = $this->db->query($sql);
			if (! $resql) {
				$error++; $this->errors[]="Error ".$this->db->lasterror();
			}
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
	* Return Url link of activity origin object
	*
	* @param int $fk_origin  Id origin
	* @param int $origintype Type origin
	*
	* @return string
	*/
	public function getActivityOrigin($fk_origin, $origintype)
	{
		$origin='';
		switch ($origintype) {
			case 'Order':
				require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
				$origin = new Commande($this->db);
				break;
			case 'Picking':
				require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
				$origin = new Commande($this->db);
				break;
			case 'Shipping':
				require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
				$origin = new Expedition($this->db);
				break;
			case 'Dispatch':
				require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
				$origin = new CommandeFournisseur($this->db);
				break;
			case 'Purchase':
				require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
				$origin = new CommandeFournisseur($this->db);
				break;
			case 'Inventory':
				require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
				$origin = new Product($this->db);
				break;
			case 'OrderProduct':
				require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
				$origin = new Product($this->db);
				break;
			case 'Remove':
				require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
				$origin = new Product($this->db);
				break;
			case 'PurchaseProducts':
				require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
				$origin = new Product($this->db);
				break;

			default:
				break;
		}

		if (empty($origin) || ! is_object($origin)) return '';

		if ($origin->fetch($fk_origin) > 0) {
			return $origin->getNomUrl(1);
		}

		return '';
	}
}
