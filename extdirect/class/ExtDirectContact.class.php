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
    
    /**
     * constructor
     * 
     * @param string $login user name
     * @return number
     */
    function __construct($login) 
    {
        global $user,$db,$langs;
        
        if (!empty($login)) {
            if ($user->fetch('', $login)>0) {
                $user->getrights();
                $this->_user = $user;  //commande.class uses global user
                if (isset($this->_user->conf->MAIN_LANG_DEFAULT) && ($this->_user->conf->MAIN_LANG_DEFAULT != 'auto')) {
                    $langs->setDefaultLang($this->_user->conf->MAIN_LANG_DEFAULT);
                }
                $langs->load("companies");
                if (ExtDirect::checkDolVersion() >= 3.3) {
                    parent::__construct($db);
                } else {
                    $this->db = $db;
                }
            }
        }   
    }
    
    /**
     *    Load contact from database into memory
     *    
     *    @param    stdClass    $params filter[]->property->id  Id's of contacts to load
     *    @return     stdClass result data or error string 
     */
    
    function readContact(stdClass $params)
    {
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->societe->contact->lire)) return PERMISSIONERROR;
        
        $results = array();
        $row = new stdClass;
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
                        $row->civility_id       = $this->civilite_id;
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
     *    Load contact parties list from database into memory, keep properties of same kind together
     *
     *    @param    stdClass    $params     property filter with properties and values:
     *                                          id           Id of third party to load
     *                                          company_id       commercial status of third party
     *                                          town            Town of third party
     *                                          content         filter on part of name, label or town value
     *                                      property sort with properties field names and directions:
     *                                      property limit for paging with sql LIMIT and START values
     *
     *    @return     stdClass result data or -1
     */
    function readContactList(stdClass $params)
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
            if (ExtDirect::checkDolVersion() >= 3.5) {
                $sql = 'SELECT c.rowid as id, s.rowid as company_id, s.nom as companyname, c.lastname, c.firstname,c.zip as zip, c.town as town, c.statut';
            } else {
                $sql = 'SELECT c.rowid as id, s.rowid as company_id, s.nom as companyname, c.lastname, c.firstname,c.zip as zip, c.town as town';
            }            
        } else {
            $sql = 'SELECT c.rowid as id, s.rowid as company_id, s.nom as companyname, c.name as lastname, c.firstname,c.cp as zip, c.ville as town';
        }
        $sql .= ' FROM '.MAIN_DB_PREFIX.'socpeople as c';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe as s ON c.fk_soc = s.rowid';
        if ($filterSize > 0) {
            // TODO improve sql command to allow random property type
            $sql .= ' WHERE (';
            foreach ($params->filter as $key => $filter) {
                if ($filter->property == 'id')
                    $sql .= 'c.rowid = '.$filter->value;
                else if ($filter->property == 'company_id')
                    $sql .= "(s.rowid = ".$filter->value." AND s.entity = ".$conf->entity.")";
                else if ($filter->property == 'town') {
                    if (ExtDirect::checkDolVersion() >= 3.4) {
                        $sql .= "(c.town = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
                    } else {
                        $sql .= "(c.ville = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
                    }      
                }              
                else if ($filter->property == 'content') {
                    $contentValue = strtolower($filter->value);
                    if (ExtDirect::checkDolVersion() >= 3.4) {
                        $sql.= " (LOWER(c.lastname) like '%".$contentValue."%' OR LOWER(c.firstname) like '%".$contentValue."%'";
                        $sql.= " OR LOWER(c.town) like '%".$contentValue."%')" ;
                    } else {
                        $sql.= " (LOWER(c.name) like '%".$contentValue."%' OR LOWER(c.firstname) like '%".$contentValue."%'";
                        $sql.= " OR LOWER(c.ville) like '%".$contentValue."%')" ;
                    }
                    
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
            $sql .= "lastname ASC";
        }
         
        if ($limit) {
            $sql .= $this->db->plimit($limit, $start);
        }
    
        dol_syslog(get_class($this)."::readContactList sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
    
        if ($resql) {
            $num=$this->db->num_rows($resql);
    
            for ($i = 0;$i < $num; $i++) {
                $obj = $this->db->fetch_object($resql);
    
                $row = null;
                $row->id            = (int) $obj->id;
                $row->name          = ($obj->firstname != "") ? ($obj->firstname.' '.$obj->lastname) : ($obj->lastname);
                $row->company_id    = (int) $obj->company_id;
                $row->companyname   = $obj->companyname;
                $row->zip           = $obj->zip;
                $row->town          = $obj->town;
                if (isset($obj->statut)) {
                    $row->enabled    = $obj->statut;
                }                
    
                array_push($results, $row);
            }
            $this->db->free($resql);
            return $results;
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
    function createContact($params) 
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
    function updateContact($params) 
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
    function destroyContact($params) 
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
     * private method to copy fields into dolibarr object
     *
     * @param stdclass $params object with fields
     * @return null
     */
    private function prepareFields($params) 
    {
        ($params->civility_id) ? ($this->civilite_id = $params->civility_id) : null;
        if (ExtDirect::checkDolVersion() >= 3.4) {
            ($params->lastname) ? ($this->lastname = $params->lastname) : null;
        } else {
            ($params->lastname) ? ($this->name = $params->lastname) : null;
        }
        ($params->firstname) ? ($this->firstname = $params->firstname) : null;
        ($params->address) ? ($this->address = $params->address) : null;
        if (ExtDirect::checkDolVersion() >= 3.4) {
            ($params->zip) ? ($this->zip = $params->zip) : null;
        } else {
            ($params->zip) ? ($this->cp = $params->zip) : null;
        }
        if (ExtDirect::checkDolVersion() >= 3.4) {
            ($params->town) ? ($this->town = $params->town) : null;
        } else {
            ($params->town) ? ($this->ville = $params->town) : null;
        }
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
