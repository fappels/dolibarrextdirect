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
        global $db;
        // clear session
        $_SESSION['dol_login'] = null;
        $this->_user = new User($db);
        parent::__construct($db);
    }

    /**
     * Ext.direct method to create app generated uuid and name in dolibarr system. System
     * will have to asign user and acknowledge id (access key) to the application, which can be read afterwards
     *
     * @param unknown_type $params object or object array with with 'requestid' connection requestor identifcation
     *                              'app_id' app uuid
     *                              'app_name' app name
     *                              'dev_platform' device platform
     *                              'dev_type' device version
     * 
     * @return return mixed stdClass  or int <0 if error
     */
    public function createAuthentication($params) 
    {
        $paramArray = ExtDirect::toArray($params);
        foreach ($paramArray as &$param) {
            $this->prepareAuthenticationFields($param);
            dol_syslog(get_class($this)."::create webview name= ". $param->webview_name ." webview version= ". $param->webview_version, LOG_DEBUG);
            // check if already acknowledged, return PARAMETERERROR if so
            if (($resql = $this->fetch(0, $this->app_id)) < 0) return $resql;
            if (!empty($this->ack_id)) return PARAMETERERROR;
            if (empty($this->id)) {
                // create user app record
                $this->fk_user=null;
                if (($resql = $this->create($this->_user)) < 0) return $resql;
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
     *      app_id                  app_id of application to get authentication info from
     *      ack_id                  access key to get authentication info and start a login session
     * @return return mixed stdClass if success or int <0 if error
     */
    public function readAuthentication(stdClass $param) 
    {
        global $conf;
        
        if (!isset($this->db)) return CONNECTERROR;
        
        $result = new stdClass;
        $ack_id = '';
        $app_id = '';
        
        $moduleInfo = new modExtDirect($this->db);
                
        if (isset($param->filter)) {
            foreach ($param->filter as $key => $filter) {
                if ($filter->property == 'ack_id') $ack_id=$filter->value;
                else if ($filter->property == 'app_id') $app_id=$filter->value;
            }
        }
        // check if server user is set, if not return empty result
        if (($resql = $this->fetch(0, $app_id, $ack_id)) < 0) return $resql;
        if (empty($this->fk_user) || ($this->fk_user < 0)) {
            return $result; //empty result
        } else {
            if (empty($this->ack_id)) {
                // user set by admin, but not auto acknowledged, generate access key
                $this->ack_id = uniqid('llx', true);
            }
        }
        // update last connect date
        $this->date_last_connect=dol_now();
        $this->_user->fetch($this->fk_user);
        if (($resql = $this->update($this->_user)) < 0) {
            return $resql;
        } else {
            // only login with valid access key
            if ($ack_id == $this->ack_id) {
                $_SESSION['dol_login'] = $this->_user->login;
            }
            if (isset($this->_user->entity) && ($this->_user->entity > 0)) {
                $_SESSION['dol_entity'] = $this->_user->entity;
                $conf->entity = $this->_user->entity;           
            } else {
                $_SESSION['dol_entity'] = 1;
                $conf->entity = 1;
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
            $result->dolibarr_version = ExtDirect::checkDolVersion();
            if (ExtDirect::checkDolVersion() >= 3.4) {
                $mysoc = new Societe($this->db);
                $conf->setValues($this->db);//update $conf globals
                $mysoc->setMysoc($conf);
                $result->home_country_id = $mysoc->country_id;
                $result->home_state_id = $mysoc->state_id;
                $result->home_name = $mysoc->name;
            } 
            $result->timezone_offset = getServerTimeZoneInt('now');   
            $result->timezone = getServerTimeZoneString();        
            return $result;
        }
    }
    
    /**
     * Ext.direct method to update authorisation details, update not possible.
     *
     * @param unknown_type $param parameter
     * 
     * @return return  int PARAMETERERROR
     */
    public function updateAuthentication($param) 
    {
        return PARAMETERERROR;// no update possible
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
            if (($resql = $this->fetch(0, $param->app_id)) < 0) return $resql;
            // if found delete
            if ($this->id) {
                $this->_user->fetch($this->fk_user);
                // delete id, if not deleted return error
                if (($resql = $this->delete($this->_user)) < 0) return $resql;
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
     * @param stdclass $params object with fields
     * @return null
     */
    private function prepareAuthenticationFields($params) 
    {
        isset($params->requestid) ? ( $this->requestid = $params->requestid ) : ( $this->requestid = null);
        isset($params->app_id) ? ( $this->app_id = $params->app_id ) : ( $this->app_id = null);
        isset($params->app_name) ? ( $this->app_name = $params->app_name) : ( $this->app_name = null);
        isset($params->dev_platform) ? ( $this->dev_platform = $params->dev_platform) : ( $this->dev_platform = null);
        isset($params->dev_type) ? ( $this->dev_type = $params->dev_type) : ($this->dev_type  = 0);
        isset($params->fk_user) ? ( $this->fk_user =$params->fk_user) : ( $this->fk_user= null);
        isset($params->ack_id) ? ( $this->ack_id = $params->ack_id ) : ($this->ack_id = null);
        isset($params->datec) ? ( $this->datec = $params->datec) : ($this->datec = null);
        isset($params->date_last_connect) ? ( $this->date_last_connect =$params->date_last_connect) : ($this->date_last_connect = null);
    }
}
