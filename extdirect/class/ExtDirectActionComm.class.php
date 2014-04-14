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
        $row = new stdClass();
        if (isset($params->filter)) {
            foreach ($params->filter as $key => $filter) {
                if ($filter->property == 'id') {
                    if (($result = $this->fetch($filter->value)) < 0)   return $result;
                    if ($result == 0) {
                        return array(); // no results
                    }
                    if (!$this->error) {
                        $row = null;
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
                        $row->usertodo_id       = (int) $this->usertodo->id;
                        $row->userdone_id       = (int) $this->userdone->id;
                        $row->company_id        = (int) $this->societe->id;
                        $row->contact_id        = (int) $this->contact->id;
                        $row->project_id        = (int) $this->fk_project;
                
                        array_push($results, $row);
                        return $results;
                    } else {
                        return $result;
                    }
                }
            }
        }
        return PARAMETERERROR;
    }

    /**
     *    Load action list from database into memory, keep properties of same kind together
     *
     *    @param    stdClass    $params     property filter with properties and values:
     *                                          id           Id of third party to load
     *                                          company_id       id of third party
     *                                          contact_id       id of contact
     *                                          content         filter on part of company name, label, firstnamet or lastname
     *                                      property sort with properties field names and directions:
     *                                      property limit for paging with sql LIMIT and START values
     *
     *    @return     stdClass result data or -1
     */
    function readActionList(stdClass $params)
    {
        global $conf,$langs;
    
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->societe->contact->lire)) return PERMISSIONERROR;
        $results = array();
        $row = new stdClass;
        $filterSize = 0;
    
        if (isset($params->limit)) {
            $limit = $params->limit;
            $start = $params->start;
        }
        if (isset($params->filter)) {
            $filterSize = count($params->filter);
        }
        if (ExtDirect::checkDolVersion() >= 3.4) {
            $sql = 'SELECT a.id, a.label, a.datep, a.datep2 as datef, a.percent as percentage, s.nom as companyname, c.lastname, c.firstname, s.rowid as company_id, c.rowid as contact_id';
        } else {
            $sql = 'SELECT a.id, a.label, a.datep, a.datep2 as datef, a.percent as percentage, s.nom as companyname, c.name as lastname, c.firstname, s.rowid as company_id, c.rowid as contact_id';
        }
        $sql .= ' FROM '.MAIN_DB_PREFIX.'actioncomm as a';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe as s ON a.fk_soc = s.rowid';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'socpeople as c ON a.fk_contact = c.rowid';
        if ($filterSize > 0) {
            // TODO improve sql command to allow random property type
            $sql .= ' WHERE (';
            foreach ($params->filter as $key => $filter) {
                if ($filter->property == 'id')
                    $sql .= 'a.id = '.$filter->value;
                else if ($filter->property == 'company_id')
                    $sql .= "(s.rowid = ".$filter->value." AND s.entity = ".$conf->entity.")";
                else if ($filter->property == 'contact_id')
                    $sql .= "(c.rowid = ".$filter->value.")";
                else if ($filter->property == 'user_id') 
                    $sql.= '(fk_user_action = '.$filter->value.' OR fk_user_done = '.$filter->value.')';
                else if ($filter->property == 'content') {
                    $contentValue = strtolower($filter->value);
                    $sql.= " (LOWER(c.lastname) like '%".$contentValue."%' OR LOWER(c.firstname) like '%".$contentValue."%'";
                    $sql.= " OR LOWER(s.name) like '%".$contentValue."%' OR LOWER(a.label) like '%".$contenValue."%')" ;
                } else break;
                if ($key < ($filterSize-1)) {
                    if($filter->property == $params->filter[$key+1]->property) $sql .= ' OR ';
                    else $sql .= ') AND (';
                }
            }
            $sql .= ')';
        }
        $sql .= " ORDER BY ";
        if (isset($params->sort)) {
            $sorterSize = count($params->sort);
            foreach($params->sort as $key => $sort) {
                $sql .= $sort->property. ' '.$sort->direction;
                if ($key < ($sorterSize-1)) {
                    $sql .= ",";
                }
            }
        } else {
            $sql .= "datep ASC";
        }
         
        if ($limit) {
            $sql .= $this->db->plimit($limit, $start);
        }
    
        dol_syslog(get_class($this)."::readActionList sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
    
        if ($resql) {
            $num=$this->db->num_rows($resql);
    
            for ($i = 0;$i < $num; $i++) {
                $obj = $this->db->fetch_object($resql);
    
                $row = null;
                $row->id            = (int) $obj->id;
                $row->percentage    = (int) $obj->percentage;
                $row->companyname   = $obj->companyname;
                $row->contactname    = ($obj->firstname != "") ? ($obj->firstname.' '.$obj->lastname) : ($obj->lastname);
                $row->datep         = $this->db->jdate($obj->datep);
                $row->datef         = $this->db->jdate($obj->datef);
                $row->company_id    = $obj->company_id;
                $row->contact_id    = $obj->contact_id;
                $row->label         = $obj->label;
                              
                array_push($results, $row);
            }
            $this->db->free($resql);
            return $results;
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
                     return $result;
                }  
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
        isset($params->userdone_id) ? $this->userdone->id = $params->userdone_id : null;
        isset($params->location) ? $this->location = $params->location : null;
        isset($params->company_id) ? $this->societe->id=$params->company_id : null;
        isset($params->contact_id) ? $this->contact->id=$params->contact_id : null;
        isset($params->durationp) ? $this->durationp=$params->durationp : null;
        isset($params->percentage) ? $this->percentage=$params->percentage : null;
        isset($params->code) ? $this->code=$params->code : null;
        isset($params->fulldayevent) ? $this->fulldayevent=$params->fulldayevent : null;
        isset($params->transparency) ? $this->transparency=$params->transparency : null;
        isset($params->project_id) ? $this->fk_project=$params->project_id : null;
    }
}
