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

/**
 * ExtDirectActionComm Class
 * 
 * Calendar Class with CRUD methods to connect to Extjs or sencha touch using Ext.direct connector
 */
class ExtDirectActionComm extends ActionComm
{
    private $_user;
    private $_societe;
    
    /**
     * constructor
     *   
     * @param string $login user name
     * @return number
     */
    function __construct($login) 
    {
        global $langs,$db,$user;
        
        if (!empty($login)) {
            if ($user->fetch('', $login) > 0) {
                $user->getrights();
                $this->_user = $user;
                if (isset($this->_user->conf->MAIN_LANG_DEFAULT) && ($this->_user->conf->MAIN_LANG_DEFAULT != 'auto')) {
                    $langs->setDefaultLang($this->_user->conf->MAIN_LANG_DEFAULT);
                }
                $this->db = $db;
                $this->_societe = new Societe($db);
            }
        }
    }
    
    /**
     *    Load actions of societe
     *    
     *    @param    stdClass    $params ->filter[]->property->societe_id: filter on societe rowid
     *                          $params ->filter[]->property->type_code: filter on action type
     *                          $params ->filter[]->property->user_id: filter on user_id
     *    @return     stdClass result data or error string 
     */
    function readAction(stdClass $params)
    {
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->agenda->myactions->read) 
            || !isset($this->_user->rights->agenda->allactions->read)) return PERMISSIONERROR;
        $results = array();
        $actions = array();
        $row = new stdClass();
        $societeId=0;
        $userFilter = '';
        $type_code = 'AC_OTH';
        if (isset($params->filter)) {   
            foreach ($params->filter as $key => $filter) {
                if ($filter->property == 'company_id') 
                    $societeId = $filter->value;
                else if ($filter->property == 'type_code') 
                    $type_code = $filter->value;
                else if ($filter->property == 'user_id') 
                    $userFilter = ' AND (fk_user_action = '.$filter->value.' OR fk_user_done = '.$filter->value.')';
            }
        }
        if (($actions = $this->getActions($this->db, $societeId, 0, '', $userFilter)) < 0) return $actions;
        if (isset($actions[0])) {
            foreach ($actions as $action) {
                $row = null;
                $row->id                = (int) $action->id;
                $row->datep             = $action->datep;
                $row->datef             = $action->datef;
                $row->type_code         = $action->type_code;
                $row->type              = $action->type;
                $row->label             = $action->label;
                $row->note              = $action->note;
                $row->usertodo_id       = (int) $action->usertodo->id;
                $row->userdone_id       = (int) $action->userdone->id;
                $row->location          = $action->location;
                $row->company_id        = (int) $action->societe->id;
                $row->contact_id        = (int) $action->contact->id;
                $row->durationp         = $action->durationp;
                
                // filter on type code
                if (isset($type_code)) {
                    if ($row->type_code == $type_code){
                        array_push($results, $row);
                    }
                }
            }
        }
        return $results;
    }
    
    
    /**
     * Ext.direct create method
     * 
     *    @param    stdClass    $params record to create
     *    @return     stdClass result data or error number 
     */
    function createAction($params) 
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
            if (($result = $this->add($this->_user, $notrigger)) < 0)    return $result;
           
            $param->id=$this->id;
            $this->_societe->id=$this->societe->id;
            $this->_societe->add_commercial($this->_user, $this->usertodo->id);
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
    function updateAction($params) 
    {
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->agenda->myactions->create) 
            || !isset($this->_user->rights->agenda->allactions->create)) return PERMISSIONERROR;
        // dolibarr update settings 
        $notrigger=1;
        
        $paramArray = ExtDirect::toArray($params);
        foreach ($paramArray as &$param) {
            // prepare fields
            if ($param->id) {
                $this->id = $param->id;
                if (($result = $this->fetch($this->id)) < 0) return $result;
                $this->prepareFields($param);
                // update
                if (($result = $this->update($this->_user, $notrigger)) < 0)    return $result;
                $this->_societe->id=$this->societe->id;
                $this->_societe->add_commercial($this->_user, $this->usertodo->id);
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
    function destroyAction($params) 
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
                if ($param->usertodo_id) $this->usertodo->id = $param->usertodo_id;
                if ($param->company_id) $this->societe->id=$param->company_id;
                // delete
                if (($result = $this->delete($notrigger)) < 0) return $result;
                $this->_societe->id=$this->societe->id;
                $this->_societe->del_commercial($this->_user, $this->usertodo->id);
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
     * Ext.directfn for getting all users who have a sales role
     * 
     * @return stdClass array or error number
     */
    function getAllUsers() 
    {
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->user->user->lire)) return PERMISSIONERROR;
        
        $results = array();
        $row = new stdClass;
        $row->id = 0;
        $row->name = '';
        array_push($results, $row);
        
        $sql = "SELECT u.rowid, u.firstname,";
        if (ExtDirect::checkDolVersion() >= 3.3) {
            $sql.= " u.lastname";
        } else {
            $sql.= " u.name as lastname";
        }
        $sql .= " FROM ".MAIN_DB_PREFIX."user as u";
        if (ExtDirect::checkDolVersion() >= 3.3) {
            $sql .= " ORDER BY u.lastname ASC ";
        } else {
            $sql .= " ORDER BY u.name ASC ";
        }
                
        dol_syslog(get_class($this)."::getAllUsers sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        
        if ($resql) {
            $num=$this->db->num_rows($resql);
            for ($i = 0;$i < $num; $i++) {
                $obj = $this->db->fetch_object($resql);
                $row = null;
                $row->id       = (int) $obj->rowid;
                $row->name      = $obj->firstname.' '.$obj->lastname;
                array_push($results, $row);
            }
            $this->db->free($resql);
            return $results;
        } else {
            $error="Error ".$this->db->lasterror();
            dol_syslog(get_class($this)."::getAllUsers ".$error, LOG_ERR);
            return -1;
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
        isset($params->usertodo_id) ? $this->usertodo->id = $params->usertodo_id : null;
        isset($params->userdone_id) ? $this->userdone->id = $this->_user->id : null;
        isset($params->location) ? $this->location = $params->location : null;
        isset($params->company_id) ? $this->societe->id=$params->company_id : null;
        isset($params->contact_id) ? $this->contact->id=$params->contact_id : null;
        isset($params->durationp) ? $this->durationp=$params->durationp : null;
        isset($params->percentage) ? $this->percentage=$params->percentage : $this->percentage = 100;
    }
}
