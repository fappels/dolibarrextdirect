<?PHP

/*
 * Copyright (C) 2012-2014      Francis Appels <francis.appels@z-application.com>
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
 *  \file       htdocs/extdirect/class/ExtDirectSociete.class.php
 *  \brief      Sencha Ext.Direct third party remoting class
 */
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/extdirect/class/extdirect.class.php');


/** ExtDirectSociete class
 * 
 * Class to access third parties with CRUD methods to connect to Extjs or sencha touch using Ext.direct connector
 */
class ExtDirectSociete extends Societe
{
    private $_user;
        
    /** Constructor
     *
     * @param string $login user name
     * @return number
     *
     */
    function __construct($login) 
    {
        global $langs,$user,$db;
        
        if (!empty($login)) {
            if ($user->fetch('', $login)>0) {
                $user->getrights();
                $this->_user = $user;
                if (isset($user->conf->MAIN_LANG_DEFAULT) && ($user->conf->MAIN_LANG_DEFAULT != 'auto')) {
                    $langs->setDefaultLang($user->conf->MAIN_LANG_DEFAULT);
                }
                $langs->load("companies");
                $langs->load("bills");
                $langs->load("dict");
                if (ExtDirect::checkDolVersion() >= 3.3) {
                    parent::__construct($db);
                } else {
                    $this->db = $db;
                }
            }
        }
    }
    

    /**
     *    Load third parties Status constants
     *
     *    @return   stdClass                result data or -1
     */
    public function readStComm() 
    {
        global $langs;
        
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->societe->lire)) return PERMISSIONERROR;
        $results = array();
        $row = new stdClass;

        $sql = 'SELECT st.id , st.code, st.libelle';
        $sql .= ' FROM '.MAIN_DB_PREFIX.'c_stcomm as st';
        $sql .= ' WHERE st.active = 1';

        dol_syslog(get_class($this)."::readStComm sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        
        if ($resql) {
            $num=$this->db->num_rows($resql);
            for ($i = 0;$i < $num; $i++) {
                $obj = $this->db->fetch_object($resql);
                $row = null;

                $transcode=$langs->transnoentities('StatusProspect'.$obj->id);
                $libelle=($transcode!='StatusProspect'.$obj->id?$transcode:$obj->libelle);
                $row->id = (int) $obj->id;     // id statut commercial
                $row->commercial_status = htmlspecialchars_decode($libelle, ENT_QUOTES);    // libelle statut commercial
                $row->stcomm_code       = $obj->code;
                array_push($results, $row);
            }
            $this->db->free($resql);
            return $results;
        } else {
            $error="Error ".$this->db->lasterror();
            dol_syslog(get_class($this)."::readStComm ".$error, LOG_ERR);
            return -1;
        }
    }
    
    /**
     *    Load country constants
     *
     *    @return   stdClass                result data or -1
     */
    public function readCountryConstants()
    {
        global $langs;
    
        if (!isset($this->db)) return CONNECTERROR;
        $results = array();        
    
        $sql = "SELECT rowid, code, libelle as label";
        if (ExtDirect::checkDolVersion() >= 3.7) {
            $sql = "SELECT rowid, code, label";
            $sql.= " FROM ".MAIN_DB_PREFIX."c_country";
        } else {
            $sql = "SELECT rowid, code, libelle as label";
            $sql.= " FROM ".MAIN_DB_PREFIX."c_pays";
        }
        $sql.= " WHERE active = 1";
    
        dol_syslog(__METHOD__." sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
    
        if ($resql) {
            $num=$this->db->num_rows($resql);
            for ($i = 0;$i < $num; $i++) {
                $obj = $this->db->fetch_object($resql);
                $row = new stdClass;
                $row->id 		= $obj->rowid;
                $row->code     	= $obj->code;
                $row->label		= ($obj->code && $langs->transnoentitiesnoconv("Country".$obj->code)!="Country".$obj->code?$langs->transnoentitiesnoconv("Country".$obj->code):($obj->label!='-'?$obj->label:''));
                array_push($results, $row);
            }
            $this->db->free($resql);
            return $results;
        } else {
            $error="Error ".$this->db->lasterror();
            dol_syslog(__METHOD__.$error, LOG_ERR);
            return -1;
        }
    }
    
    /**
     *    Load state constants
     *
     *    @param    stdClass    $param  filter with country_id
     *    @return   stdClass                result data or -1
     */
    public function readStateConstants(stdClass $param)
    {
        global $langs;
    
        if (!isset($this->db)) return CONNECTERROR;
        $results = array();
        
        if (isset($param->filter)) {
            foreach ($param->filter as $key => $filter) {
                if ($filter->property == 'country_id') $country_id=$filter->value;
            }
        }

        $row = new stdClass;
        $row->id 		    = null;
        $row->code     	    = '';
        $row->label		    = '';
        $row->country_id    = null;
        array_push($results, $row);
    
        $sql = "SELECT d.rowid, d.code_departement as code , d.nom as label, p.rowid as country_id FROM";
        if (ExtDirect::checkDolVersion() >= 3.7) {
            $sql .= " ".MAIN_DB_PREFIX ."c_departements as d, ".MAIN_DB_PREFIX."c_regions as r,".MAIN_DB_PREFIX."c_country as p";
        } else {
            $sql .= " ".MAIN_DB_PREFIX ."c_departements as d, ".MAIN_DB_PREFIX."c_regions as r,".MAIN_DB_PREFIX."c_pays as p";
        }		
		$sql .= " WHERE d.fk_region=r.code_region and r.fk_pays=p.rowid";
		$sql .= " AND d.active = 1 AND r.active = 1 AND p.active = 1";
		if ($country_id) $sql .= " AND p.rowid = ".$country_id;
		$sql .= " ORDER BY p.code, d.code_departement";
    
        dol_syslog(__METHOD__." sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        
        if ($resql) {
            $num=$this->db->num_rows($resql);
            for ($i = 0;$i < $num; $i++) {
                $obj = $this->db->fetch_object($resql);
                $row = new stdClass;
                $row->id 		    = $obj->rowid;
                $row->code     	    = $obj->code;
                $row->label		    = $obj->code.' - '.$obj->label;
                $row->country_id    = $obj->country_id;
                array_push($results, $row);
            }
            $this->db->free($resql);
            return $results;
        } else {
            $error="Error ".$this->db->lasterror();
            dol_syslog(__METHOD__.$error, LOG_ERR);
            return -1;
        }
    }

    /**
     *    Load third parties prospect level constants
     *
     *    @return     stdClass result data or -1
     */
    public function readProspectLevel()
    {
        global $langs;

        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->societe->lire)) return PERMISSIONERROR;
        $results = array();
        $row = new stdClass;

        $sql = 'SELECT cp.code , cp.label';
        $sql .= ' FROM '.MAIN_DB_PREFIX.'c_prospectlevel as cp';
        $sql .= ' WHERE cp.active = 1';
        $sql .= ' ORDER BY cp.sortorder';
        
        dol_syslog(get_class($this)."::readProspectLevel sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        
        if ($resql) {
            $num=$this->db->num_rows($resql);

            for ($i = 0;$i < $num; $i++) {
                $obj = $this->db->fetch_object($resql);
                $row = null;

                $transcode=$langs->transnoentities($obj->code);
                $label=($transcode!=null?$transcode:$obj->label);
                $row->prospectlevel_code = $obj->code;
                $row->prospectlevel_label = htmlspecialchars_decode($label, ENT_QUOTES);
                array_push($results, $row);
            }

            $this->db->free($resql);
            return $results;
        } else {
            $error="Error ".$this->db->lasterror();
            dol_syslog(get_class($this)."::readProspectLevel ".$error, LOG_ERR);
            return -1;
        }
    }
    
    /**
     *    Load the available paiment condition constants
     *
     *    @return     stdClass result data or -1
     */
    public function readPaymentConditions()
    {
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->societe->lire)) return PERMISSIONERROR;

        require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

        $results = array();
        $row = new stdClass;
        $formHelpers = new Form($this->db);

        if ($formHelpers->load_cache_conditions_paiements() > 0) {
            foreach ($formHelpers->cache_conditions_paiements as $id => $values) {
                $row = null;
                $row->id = $id;
                $row->code = $values['code'];
                $row->label = htmlspecialchars_decode($values['label'], ENT_QUOTES);
                array_push($results, $row);
            }
            return $results;
        } else {
            $error="Error ".$this->db->lasterror();
            dol_syslog(get_class($this)."::readPaymentConditions ".$error, LOG_ERR);
            return -1;
        }
    }
    
    /**
     *    Load the available paiment type constants
     *
     *    @return     stdClass result data or -1
     */
    public function readPaymentTypes()
    {
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->societe->lire)) return PERMISSIONERROR;
    
        require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
    
        $results = array();
        $row = new stdClass;
        $formHelpers = new Form($this->db);
    
        if ($formHelpers->load_cache_types_paiements() > 0) {
            foreach ($formHelpers->cache_types_paiements as $id => $values) {
                $row = null;
                $row->id = $id;
                $row->code = $values['code'];
                $row->label = htmlspecialchars_decode($values['label'], ENT_QUOTES);
                $row->type = $values['type'];
                array_push($results, $row);
            }
            return $results;
        } else {
            $error="Error ".$this->db->lasterror();
            dol_syslog(get_class($this)."::readPaymentTypes ".$error, LOG_ERR);
            return -1;
        }
    }
    
    /**
     *    Load third parties list from database into memory, keep properties of same kind together
     *
     *    @param    stdClass    $params     property filter with properties and values:
     *                                          id              Id of third party to load
     *                                          ref             Reference of third party, name
     *                                          client          company type 0 = none, 1 = customer, 2 = prospect, 3 = both
     *                                          stcomm_id       commercial status of third party
     *                                          town            Town of third party
     *                                          categorie_id    Categorie id of third party
     *                                          content         filter on part of name, category or town value
     *                                      property sort with properties field names and directions:
     *                                      property limit for paging with sql LIMIT and START values
     *                                          
     *    @return     stdClass result data or -1
     */
    public function readSocieteList(stdClass $params)
    {
        global $conf,$langs;
    
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->societe->lire)) return PERMISSIONERROR;
        $results = array();
        $row = new stdClass;
        $filterSize = 0;
        $limit=null;
        $start=0;
        
        if (isset($params->limit)) {
            $limit = $params->limit;
            $start = $params->start;
        }
        if (isset($params->filter)) {
            $filterSize = count($params->filter);
        }
        if (ExtDirect::checkDolVersion() >= 3.4) {
            $sql = 'SELECT s.rowid, s.nom as name, s.ref_ext, s.zip, s.town, s.fk_prospectlevel, s.logo, s.entity';
        } else {
            $sql = 'SELECT s.rowid, s.nom as name, s.ref_ext, s.cp as zip, s.ville as town, s.fk_prospectlevel, s.logo, s.entity';
        }
        
        $sql .= ', st.libelle as commercial_status';
        $sql .= ', c.rowid as categorie_id, c.label as categorie, s.fk_stcomm';
        $sql .= ' FROM '.MAIN_DB_PREFIX.'societe as s';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_stcomm as st ON s.fk_stcomm = st.id';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie_societe as cs ON s.rowid = cs.fk_societe';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie as c ON c.rowid = cs.fk_categorie';
        if ($filterSize > 0) {
            // TODO improve sql command to allow random property type
            $sql .= ' WHERE (';
            foreach ($params->filter as $key => $filter) {
                if ($filter->property == 'id') 
                    $sql .= 's.rowid = '.$filter->value;
                else if ($filter->property == 'ref') 
                    $sql .= "(s.nom = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
                else if ($filter->property == 'client') 
                    $sql .= "(s.client = ".$filter->value." AND s.entity = ".$conf->entity.")";
                else if ($filter->property == 'town') {
                    if (ExtDirect::checkDolVersion() >= 3.4) {
                        $sql .= "(s.town = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
                    } else {
                        $sql .= "(s.ville = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
                    }
                } 
                else if ($filter->property == 'stcomm_id') 
                    $sql .= "(s.fk_stcomm = ".$filter->value." AND s.entity = ".$conf->entity.")";
                else if ($filter->property == 'categorie_id') {
                    //allow filtering on non categorized societe
                    if ($filter->value == 0) {
                        $sql .= "(c.rowid IS NULL AND s.entity = ".$conf->entity.")";
                    } else {
                        $sql .= "(c.rowid = ".$filter->value." AND s.entity = ".$conf->entity.")";
                    }
                } else if ($filter->property == 'content') {
                    $contentValue = strtolower($filter->value);
                    $sql.= " (LOWER(s.nom) like '%".$contentValue."%' OR LOWER(c.label) like '%".$contentValue."%'";
                    if (ExtDirect::checkDolVersion() >= 3.4) {
                        $sql.= " OR LOWER(s.town) like '%".$contentValue."%')" ;
                    } else {
                       $sql.= " OR LOWER(s.ville) like '%".$contentValue."%')" ;
                    }
                }
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
             $sql .= "name ASC";
        }
       
        if ($limit) {
            $sql .= $this->db->plimit($limit, $start);
        }
    
        dol_syslog(get_class($this)."::readSocieteList sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
    
        if ($resql) {
            $num=$this->db->num_rows($resql);
    
            for ($i = 0;$i < $num; $i++) {
                $obj = $this->db->fetch_object($resql);
        
                $row = null;
                $row->id            = $obj->rowid.'_'.$obj->categorie_id;
                $row->company_id    = (int) $obj->rowid;
                $row->name          = $obj->name;
                $row->ref_ext       = $obj->ref_ext;
                $row->zip           = $obj->zip;
                $row->town          = $obj->town;
                $transcode          =$langs->transnoentities('StatusProspect'.$obj->fk_stcomm);
                $libelle            =($transcode!='StatusProspect'.$obj->fk_stcomm?$transcode:$obj->commercial_status);
                $row->stcomm_id     = (int) $obj->fk_stcomm;     // id statut commercial
                $row->commercial_status = $libelle;    // libelle statut commercial
                $row->fk_prospectlevel = $obj->fk_prospectlevel;
                $row->categorie     = $obj->categorie;
                $row->categorie_id  = $obj->categorie_id;
                if (!empty($obj->logo)) {
                    $dir = $conf->societe->multidir_output[(int) $obj->entity]."/".$obj->rowid."/logos/thumbs";
                    $logo_parts = pathinfo($obj->logo);
                    $filename=$dir.'/'.$logo_parts['filename'].'_mini.'.$logo_parts['extension'];
                    // Read image path, convert to base64 encoding
                    $imgData = base64_encode(file_get_contents($filename));
                    // Format the image SRC:  data:{mime};base64,{data};
                    $row->logo_small = 'data: '.dol_mimetype($filename).';base64,'.$imgData;
                }
                
                array_push($results, $row);
            }
            $this->db->free($resql);
            return $results;
        } else {
            $error="Error ".$this->db->lasterror();
            dol_syslog(get_class($this)."::readSocieteList ".$error, LOG_ERR);
            return -1;
        }
    }
    
    /**
     *    Load third parties from database into memory
     *
     *    @param    stdClass    $param  filter with elements:
     *      rowid       Id of third party to load
     *      ref         Reference of third party, name
     *      ref_ext     External reference of third party 
     *                  (Warning, this information is a free field not provided by Dolibarr)
     *      ref_int
     *      idprof1     Prof id 1 of third party
     *      idprof2     Prof id 2 of third party
     *      idprof3     Prof id 3 of third party
     *      idprof4     Prof id 4 of third party
     *      
     *    @return     stdClass result data or -1
     */
    public function readSociete(stdClass $param)
    {
        global $conf;
        
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->societe->lire)) return PERMISSIONERROR;
        $results = array();
        $row = new stdClass;
        
        $rowid=null;
        $ref=null;
        $ref_ext=null;
        $ref_int=null;
        $idprof1=null;
        $idprof2=null;
        $idprof3=null;
        $idprof4=null;
        $barcode=null;
        
        if (isset($param->filter)) {
            foreach ($param->filter as $key => $filter) {
                if ($filter->property == 'id') $id=$filter->value;
                else if ($filter->property == 'ref') $ref=$filter->value;
                else if ($filter->property == 'ref_ext') $ref_ext=$filter->value;
                else if ($filter->property == 'ref_int') $ref_int=$filter->value;
                else if ($filter->property == 'idprof1') $idprof1=$filter->value;
                else if ($filter->property == 'idprof2') $idprof2=$filter->value;
                else if ($filter->property == 'idprof3') $idprof3=$filter->value;
                else if ($filter->property == 'idprof4') $idprof4=$filter->value;
                else if ($filter->property == 'barcode') $id = $this->fetchIdFromBarcode($filter->value);
            }
        }
        if (!empty($id) || !empty($ref) || !empty($ref_ext) || !empty($ref_int)) {
            if (($result = $this->fetch($id, $ref, $ref_ext, $ref_int, $idprof1, $idprof2, $idprof3, $idprof4)) < 0) {
                if ($result == -2) {
                    return array(); // no results
                } else {
                    return $result;
                }
            }
            
            if (!$this->error) {
                $row = null;
                $row->id            = (int) $this->id;
                $row->entity        = (int) $this->entity;
            
                $row->ref           = $this->ref;
                $row->name          = $this->name;
                $row->ref_ext       = $this->ref_ext;
            
                $row->date_create   = $this->datec;
                $row->date_update   = $this->date_update;
            
                $row->address       = $this->address;
                $row->zip           = $this->zip;
                $row->town          = $this->town;
            
                $row->country_id    = $this->country_id;
                $row->country_code  = $this->country_code;
                $row->country       = $this->country;
            
                $row->state_id      = $this->state_id;
                $row->state_code    = $this->state_code;
                $row->state         = $this->state;
            
                $row->stcomm_id     = (int) $this->stcomm_id;     // id statut commercial
                $row->commercial_status = $this->statut_commercial;    // libelle statut commercial
            
                $row->email         = $this->email;
                $row->url           = $this->url;
                $row->phone         = $this->phone;
                $row->fax           = $this->fax;
            
                $row->parent        = $this->parent;
            
                $row->idprof1       = $this->idprof1;
                $row->idprof2       = $this->idprof2;
                $row->idprof3       = $this->idprof3;
                $row->idprof4       = $this->idprof4;
                $row->idprof5       = $this->idprof5;
                $row->idprof6       = $this->idprof6;
            
                $row->capital       = $this->capital;
            
                $row->code_client   = $this->code_client;
                $row->code_supplier = $this->code_fournisseur;
            
                $row->code_compta   = $this->code_compta;
                $row->code_compta_supplier = $this->code_compta_fournisseur;
            
                $row->barcode       = $this->barcode;
            
                $row->tva_assuj     = $this->tva_assuj;
                $row->tva_intra     = $this->tva_intra;
            
                $row->status = $this->status;
            
                // Local Taxes
                $row->localtax1_assuj= $this->localtax1_assuj;
                $row->localtax2_assuj= $this->localtax2_assuj;
            
            
                $row->typent_id      = $this->typent_id;
                $row->typent_code    = $this->typent_code;
            
                $row->effectif_id    = $this->effectif_id;
                $row->effectif       = $this->effectif_id?$this->effectif:'';
            
                $row->legal_form_code= $this->forme_juridique_code;
                $row->legal_form= $this->forme_juridique;
            
                $row->fk_prospectlevel= $this->fk_prospectlevel;
            
                $row->prefix_comm    = $this->prefix_comm;
            
                $row->reduction_percent = $this->remise_percent;
                $row->payment_condition_id = $this->cond_reglement_id;
                $row->payment_type_id = $this->mode_reglement_id;
            
                $row->client         = (int) $this->client;
                $row->supplier    = (int) $this->fournisseur;
                if (ExtDirect::checkDolVersion() >= 3.4) {
                    $row->note_private   = $this->note_private;
                    $row->note_public    = $this->note_public;
                } else {
                    $row->note_public    = $this->note;
                }
            
                $row->default_lang   = $this->default_lang;
                if (!empty($this->logo)) {
                    $dir = $conf->societe->multidir_output[(int) $this->entity]."/".$this->id."/logos/thumbs";
                    $logo_parts = pathinfo($this->logo);
                    $filename=$dir.'/'.$logo_parts['filename'].'_small.'.$logo_parts['extension'];
                    // Read image path, convert to base64 encoding
                    $imgData = base64_encode(file_get_contents($filename));
                    // Format the image SRC:  data:{mime};base64,{data};
                    $row->logo = 'data: '.dol_mimetype($filename).';base64,'.$imgData;
                }
            
                // multiprix
                $row->price_level    = $this->price_level;
            
                $row->import_key     = $this->import_key;
                
                array_push($results, $row);
            } else {
                return SQLERROR;
            }
        }
        return $results;
    }

    /**
     *    Load used towns from available societes
     *
     *    @param    stdClass    $params     filter with elements:
     *      rowid       Id of third party to load
     *      ref         Reference of third party, name
     *      ref_ext     External reference of third party 
     *                  (Warning, this information is a free field not provided by Dolibarr)
     *      idprof1     Prof id 1 of third party
     *      idprof2     Prof id 2 of third party
     *      idprof3     Prof id 3 of third party
     *      idprof4     Prof id 4 of third party
     *    @return     stdClass result data or error string
     */
    function getTowns(stdClass $params)
    {
        global $conf;
        
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->societe->lire)) return PERMISSIONERROR;
        $error=0;
        $results = array();
        $row = new stdClass;
        if (ExtDirect::checkDolVersion() >= 3.4) {
            $sql = 'SELECT distinct s.town, s.zip';
        } else {
            $sql = 'SELECT distinct s.ville as town, s.cp as zip';
        }
        
        $sql .= ' FROM '.MAIN_DB_PREFIX.'societe as s';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_effectif as e ON s.fk_effectif = e.id';
        if (ExtDirect::checkDolVersion() >= 3.7) {
            $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_country as p ON s.fk_pays = p.rowid';
        } else {
            $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_pays as p ON s.fk_pays = p.rowid';
        }        
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_stcomm as st ON s.fk_stcomm = st.id';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_forme_juridique as fj ON s.fk_forme_juridique = fj.code';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_departements as d ON s.fk_departement = d.rowid';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_typent as te ON s.fk_typent = te.id';
        if (isset($params->filter)) {
            $sql .= ' WHERE (';
            $filterSize = count($params->filter);
            foreach ($params->filter as $key => $filter) {
                if ($filter->property == 'rowid') 
                    $sql .= 's.rowid = '.$filter->value;
                else if ($filter->property == 'ref') 
                    $sql .= "(s.nom = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
                else if ($filter->property == 'ref_ext') 
                    $sql .= "(s.ref_ext = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
                else if ($filter->property == 'idprof1') 
                    $sql .= "(s.siren = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
                else if ($filter->property == 'idprof2') 
                    $sql .= "(s.siret = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
                else if ($filter->property == 'idprof3') 
                    $sql .= "(s.ape = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
                else if ($filter->property == 'idprof4') 
                    $sql .= "(s.idprof4 = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
                else break;
                if ($key < ($filterSize-1)) {
                    if($filter->property == $params->filter[$key+1]->property) $sql .= ' OR ';
                    else $sql .= ') AND (';
                }
            }
            $sql .= ')';
        }
        $sql .= ' ORDER BY town';

        dol_syslog(get_class($this)."::getTowns sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        
        if ($resql) {
            $num=$this->db->num_rows($resql);

            for ($i = 0;$i < $num; $i++) {
                $obj = $this->db->fetch_object($resql);
                $row = null;

                $row->town      = $obj->town;
                $row->zip       = $obj->zip;
                $row->id        = $obj->zip.$obj->town;
                if ($row->id != null) {
                    array_push($results, $row);
                }
            }
            $this->db->free($resql);
            return $results;
        } else {
            $error="Error ".$this->db->lasterror();
            dol_syslog(get_class($this)."::getTowns ".$error, LOG_ERR);
            return -1;
        }   
    }

    /**
     *    Load used categories from available societes
     *    DEPRECATED use getCategorieList form categorie class
     *
     *    @param    stdClass    $params     filter with elements:
     *      rowid       Id of third party to load
     *      ref         Reference of third party, name
     *      ref_ext     External reference of third party 
     *                  (Warning, this information is a free field not provided by Dolibarr)
     *      idprof1     Prof id 1 of third party
     *      idprof2     Prof id 2 of third party
     *      idprof3     Prof id 3 of third party
     *      idprof4     Prof id 4 of third party
     *      town        town of third party
     *    @return     stdClass result data or error string
     */
    function getCategories(stdClass $params)
    {
        global $langs;

        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->societe->lire)) return PERMISSIONERROR;
        $results = array();
        $row = new stdClass;
            
        $sql = 'SELECT distinct c.rowid, c.label as categorie';
        $sql .= ' FROM '.MAIN_DB_PREFIX.'societe as s';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_effectif as e ON s.fk_effectif = e.id';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_pays as p ON s.fk_pays = p.rowid';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_stcomm as st ON s.fk_stcomm = st.id';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_forme_juridique as fj ON s.fk_forme_juridique = fj.code';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_departements as d ON s.fk_departement = d.rowid';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_typent as te ON s.fk_typent = te.id';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie_societe as cs ON s.rowid = cs.fk_societe';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie as c ON c.rowid = cs.fk_categorie';
        if (isset($params->filter)) {
            $sql .= ' WHERE (';
            $filterSize = count($params->filter);
            foreach ($params->filter as $key => $filter) {
                if ($filter->property == 'rowid') 
                    $sql .= 's.rowid = '.$filter->value;
                else if ($filter->property == 'ref') 
                    $sql .= "(s.nom = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
                else if ($filter->property == 'ref_ext') 
                    $sql .= "(s.ref_ext = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
                else if ($filter->property == 'idprof1') 
                    $sql .= "(s.siren = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
                else if ($filter->property == 'idprof2') 
                    $sql .= "(s.siret = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
                else if ($filter->property == 'idprof3') 
                    $sql .= "(s.ape = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
                else if ($filter->property == 'idprof4') 
                    $sql .= "(s.idprof4 = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
                else if ($filter->property == 'town') {
                     if (ExtDirect::checkDolVersion() >= 3.4) {
                         $sql .= "(s.town = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
                     } else {
                         $sql .= "(s.ville = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
                     }
                }
                    
                else break;
                if ($key < ($filterSize-1)) {
                    if($filter->property == $params->filter[$key+1]->property) $sql .= ' OR ';
                    else $sql .= ') AND (';
                }
            }
            $sql .= ')';
        }
        $sql .= ' ORDER BY c.label';
        //add 'none' categorie to provide filtering on non categorized prospects
        $langs->trans('DiscountNone') ? $row->categorie = $langs->trans('DiscountNone'):$row->categorie = 'None';
        $row->id                = 0;
        array_push($results, $row);
        dol_syslog(get_class($this)."::getCategories sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql) {
            $num=$this->db->num_rows($resql);

            for ($i = 0;$i < $num; $i++) {
                $obj = $this->db->fetch_object($resql);
                $row = null;
                    
                $row->categorie         = $obj->categorie;
                $row->id                = $obj->rowid;

                if ($row->id != null) {
                    array_push($results, $row);
                }
            }
            $this->db->free($resql);
            return $results;
        } else {
            $error="Error ".$this->db->lasterror();
            dol_syslog(get_class($this)."::getCategories ".$error, LOG_ERR);
            return -1;
        }
    }

    /**
     * Ext.direct method to Create societe
     * 
     * @param unknown_type $params object or object array with societe model(s)
     * @return Ambigous <multitype:, unknown_type>|unknown
     */
    function createSociete($params) 
    {
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->societe->creer)) return PERMISSIONERROR;
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
     * Ext.direct method to update societe
     * 
     * @param unknown_type $params object or object array with societe model(s)
     * @return Ambigous <multitype:, unknown_type>|unknown
     */
    function updateSociete($params) 
    {   
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->societe->creer)) return PERMISSIONERROR;
        $paramArray = ExtDirect::toArray($params);
        // dolibarr update settings
        $allowmodcodeclient=0;
        $call_trigger=1;
        $allowmodcodefournisseur=0;
        foreach ($paramArray as &$param) {
            // prepare fields
            if ($param->id) {
                $id = $param->id;
                $this->id = $id;
                if (($result = $this->fetch($id)) < 0)  return $result;
                
                $this->prepareFields($param);
                
                // update
                if (($result = $this->update($id, $this->_user, $call_trigger, $allowmodcodeclient, $allowmodcodefournisseur)) < 0) return $result;
                if ($param->stcomm_id || $param->fk_prospectlevel) {
                    if ($this->updateProspectStatLevel($id, $param->stcomm_id, $param->fk_prospectlevel) < 0) die ($this->error);
                }
                if (isset($param->reduction_percent)) {
                    $this->set_remise_client($param->reduction_percent, 'Mobilid', $this->_user);
                }
                if (isset($param->payment_condition_id)) {
                    $this->setPaymentTerms($param->payment_condition_id);
                }
                if (isset($param->payment_type_id)) {
                    $this->setPaymentMethods($param->payment_type_id);
                }
                
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
     * Ext.direct method to detroy societe
     * 
     * @param unknown_type $params object or object array with societe model(s)
     * @return Ambigous <multitype:, unknown_type>|unknown
     */
    function destroySociete($params) 
    {
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->societe->supprimer)) return PERMISSIONERROR;
        $paramArray = ExtDirect::toArray($params);
        

        foreach ($paramArray as &$param) {
            // prepare fields
            if ($param->id) {
                $id = $param->id;
                $this->id = $id;
                // delete societe
                if (($result = $this->delete($id)) < 0) return $result;
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
     * private method to update stcomm_id and fk_prospectlevel field in societe table
     * 
     * @param number $id            societe id
     * @param number $stcomm_id     statut commercial id
     * @param string $prospectlevel prospectlevel foreign key
     * @return number
     */
    private function updateProspectStatLevel($id,$stcomm_id,$prospectlevel) 
    {
        $this->db->begin();
        $sql = "UPDATE ".MAIN_DB_PREFIX."societe";
        $sql.= " SET fk_stcomm = ".$stcomm_id;
        $sql.= " ,fk_prospectlevel ='".$prospectlevel."'";
        $sql .= " WHERE rowid = '" . $id ."'";
        dol_syslog(get_class($this)."::Update sql=".$sql);
        $resql=$this->db->query($sql);
        if ($resql) {
            dol_syslog(get_class($this)."::Update success");
            $this->db->commit();
            return 1;
        } else {
            $this->db->error = $langs->trans("Error sql=".$sql);
            dol_syslog(get_class($this)."::Update fails update sql=".$sql, LOG_ERR);
            $this->db->rollback();
            return -1;
        }
    }
    
    /**
     * private method to fetch id from given barcode
     *
     * @param string $barcode barcode to fetch id from
     * @return integer $id rowid of element
     */
    private function fetchIdFromBarcode($barcode)
    {
        $id =0;
        dol_syslog(__METHOD__.' : '.$barcode);
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE barcode ='".$barcode."'";
        $resql = $this->db->query($sql);
        if ( $resql ) {
            if ($this->db->num_rows($resql) > 0) {
                $obj = $this->db->fetch_object($resql);
                $id = (int) $obj->rowid;
            }
        }
        return $id;
    }
    
    /**
     * private method to copy fields into dolibarr object
     *
     * @param stdclass $params object with fields
     * @return null
     */
    private function prepareFields($params) 
    {
        isset($params->name) ? ($this->name = $params->name) : null;
        isset($params->client) ? ($this->client = $params->client) : null;
        isset($params->supplier) ? ($this->fournisseur = $params->supplier) : null;
        isset($params->address) ? ($this->address = $params->address) : null;
        isset($params->zip) ? ($this->zip = $params->zip) : null;
        isset($params->town) ? ($this->town = $params->town) : null;
        isset($params->fax) ? ($this->fax = $params->fax) : null;
        isset($params->phone) ? ($this->tel = $params->phone) : null;
        isset($params->email) ? ($this->email = $params->email) : null;
        isset($params->ref) ? ($this->ref=$params->ref) : null;
        isset($params->ref_ext) ? ($this->ref_ext=$params->ref_ext) : null;
        isset($params->idprof1) ? ($this->idprof1=$params->idprof1) : null;
        isset($params->idprof2) ? ($this->idprof2=$params->idprof2) : null;
        isset($params->idprof3) ? ($this->idprof3=$params->idprof3) : null;
        isset($params->idprof4) ? ($this->idprof4=$params->idprof4) : null;
        isset($params->idprof5) ? ($this->idprof5=$params->idprof5) : null;
        isset($params->idprof6) ? ($this->idprof6=$params->idprof6) : null;
        isset($params->legal_form_code) ? ($this->forme_juridique_code=$params->legal_form_code) : null;
        isset($params->prefix_comm) ? ($this->prefix_comm=$params->prefix_comm) : null;
        isset($params->default_lang) ? ($this->default_lang=$params->default_lang) : null;
        isset($params->state_id) ? ($this->state_id = $params->state_id) : null;
        isset($params->country_id) ? ($this->country_id = $params->country_id) : null;
        isset($params->url) ? ($this->url = $params->url) : null;
        isset($params->tva_assuj) ? ($this->tva_assuj = $params->tva_assuj) : null;
        isset($params->tva_intra) ? ($this->tva_intra = $params->tva_intra) : null;
        isset($params->status) ? ($this->status = $params->status) : null;
        isset($params->localtax1_assuj) ? ($this->localtax1_assuj = $params->localtax1_assuj) : null;
        isset($params->localtax2_assuj) ? ($this->localtax2_assuj = $params->localtax2_assuj) : null;
        isset($params->capital) ? ($this->capital = $params->capital) : null;
        isset($params->effectif_id) ? ($this->effectif_id = $params->effectif_id) : null;
        isset($params->legal_form_code) ? ($this->forme_juridique_code = $params->legal_form_code) : null;
        isset($params->barcode) ? ($this->barcode = $params->barcode) : null;
        isset($params->code_compta) ? ($this->code_compta = $params->code_compta) : null;
        isset($params->code_compta_supplier) ? ($this->code_compta_fournisseur = $params->code_compta_supplier) : null;
        isset($params->code_client) ? ($this->code_client = $params->code_client) : null;
        isset($params->code_supplier) ? ($this->code_fournisseur = $params->code_supplier) : null;
        isset($params->reduction_percent) ? ($this->remise_percent = $params->reduction_percent) : null;
        isset($params->payment_condition_id) ? ($this->cond_reglement_id = $params->payment_condition_id) : null;
        isset($params->payment_type_id) ? ($this->mode_reglement_id = $params->payment_type_id) : null;
        if (ExtDirect::checkDolVersion() >= 3.4) {
           isset($params->note_public) ? ($this->note_public = $params->note_public) : null;
           isset($params->note_private) ? ($this->note_private = $params->note_private) : null;
        } else {
           isset($params->note_public) ? ($this->note = $params->note_public) : null;
        }
        /*	$img = str_replace('data:image/png;base64,', '', $params->logo);
	        $img = str_replace(' ', '+', $img);
	        $data = base64_decode($img);*/
    }
}

