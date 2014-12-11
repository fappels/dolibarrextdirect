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
    public function create($user, $notrigger=0)
    {
        global $conf, $langs;
        $error=0;
        
        if ($conf->global->DIRECTCONNECT_AUTO_ASIGN) {
            $this->fk_user = $conf->global->DIRECTCONNECT_AUTO_USER;
            $this->ack_id = uniqid('llx',true);     
        }
        
        // Clean parameters
        
        if (isset($this->fk_user)) $this->fk_user=trim($this->fk_user);
        if (isset($this->app_id)) $this->app_id=trim($this->app_id);
        if (isset($this->app_name)) $this->app_name=trim($this->app_name);
        if (isset($this->ack_id)) $this->ack_id=trim($this->ack_id);
        if (isset($this->requestid)) $this->requestid=trim($this->requestid);
        if (isset($this->dev_platform)) $this->dev_platform=trim($this->dev_platform);
        if (isset($this->dev_type)) $this->dev_type=trim($this->dev_type);

        

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
        $sql.= "dev_type";

        
        $sql.= ") VALUES (";
        
        $sql.= " ".(! isset($this->fk_user)?'NULL':"'".$this->fk_user."'").",";
        $sql.= " ".(! isset($this->app_id)?'NULL':"'".$this->db->escape($this->app_id)."'").",";
        $sql.= " ".(! isset($this->app_name)?'NULL':"'".$this->db->escape($this->app_name)."'").",";
        $sql.= " ".(! isset($this->ack_id)?'NULL':"'".$this->db->escape($this->ack_id)."'").",";
        $sql.= " ".(! isset($this->requestid)?'NULL':"'".$this->db->escape($this->requestid)."'").",";
        $sql.= " '".$this->db->idate(dol_now())."',";
        $sql.= " '".$this->db->idate(dol_now())."',";
        $sql.= " ".(! isset($this->dev_platform)?'NULL':"'".$this->db->escape($this->dev_platform)."'").",";
        $sql.= " ".(! isset($this->dev_type)?'NULL':"'".$this->db->escape($this->dev_type)."'")."";

        
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
    public function fetchList($filter = '',$orderBy = '')
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
        $sql.= " t.dev_type";
    
    
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
    public function fetch($id=0, $app_id='', $ack_id='')
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
        $sql.= " t.dev_type";

        
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
     *  @param  User    $user        User that modify
     *  @param  int     $notrigger   0=launch triggers after, 1=disable triggers
     *  @return int                  <0 if KO, >0 if OK
     */
    public function update($user=0, $notrigger=0)
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

        

        // Check parameters
        // Put here code to add control on parameters values

        // Update request
        $sql = "UPDATE ".MAIN_DB_PREFIX."extdirect_user SET";
        
        $sql.= " fk_user=".(isset($this->fk_user)?$this->fk_user:"null").",";
        $sql.= " app_id=".(isset($this->app_id)?"'".$this->db->escape($this->app_id)."'":"null").",";
        $sql.= " app_name=".(isset($this->app_name)?"'".$this->db->escape($this->app_name)."'":"null").",";
        $sql.= " ack_id=".(isset($this->ack_id)?"'".$this->db->escape($this->ack_id)."'":"null").",";
        $sql.= " requestid=".(isset($this->requestid)?"'".$this->db->escape($this->requestid)."'":"null").",";
        $sql.= " datec=".(dol_strlen($this->datec)!=0 ? "'".$this->db->idate($this->datec)."'" : 'null').",";
        $sql.= " date_last_connect=".(dol_strlen($this->date_last_connect)!=0 ? "'".$this->db->idate($this->date_last_connect)."'" : 'null').",";
        $sql.= " dev_platform=".(isset($this->dev_platform)?"'".$this->db->escape($this->dev_platform)."'":"null").",";
        $sql.= " dev_type=".(isset($this->dev_type)?"'".$this->db->escape($this->dev_type)."'":"null")."";

        
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
    public function delete($user, $notrigger=0)
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
     *
     * @return return validation 0 (not valid) or 1 (valid) or string with major.minor version
     */
    
    public static function checkDolVersion($validate = 0) 
    {
        $dolVersion = versiondolibarrarray();
        $dolMajorMinorVersion = $dolVersion[0].'.'.$dolVersion[1];
        
        if($validate) 
        {
            if (($dolMajorMinorVersion >= 3.2) && ($dolMajorMinorVersion < 3.8))
            {
                return 1;
            }
            else
            {
                return 0;
            }
        } 
        else 
        {
            return $dolMajorMinorVersion;
        }
    }   
}