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
dol_include_once('/extdirect/class/extdirect.class.php');


/** ExtDirectSociete class
 *
 * Class to access third parties with CRUD methods to connect to Extjs or sencha touch using Ext.direct connector
 */
class ExtDirectSociete extends Societe
{
	private $_user;
	private $_constants = array('SOCIETE_CODECLIENT_ADDON');
	private $_enabled = false;

	/** Constructor
	 *
	 * @param string $login user name
	 * @return number
	 *
	 */
	public function __construct($login)
	{
		global $conf, $langs ,$user, $db, $mysoc;

		if (!empty($login)) {
			if ((is_object($login) && get_class($db) == get_class($login)) || $user->id > 0 || $user->fetch('', $login, '', 1) > 0) {
				$user->getrights();
				$this->_enabled = !empty($conf->societe->enabled) && isset($user->rights->societe->lire);
				$this->_user = $user;
				if (isset($this->_user->conf->MAIN_LANG_DEFAULT)) {
					$langs->setDefaultLang($user->conf->MAIN_LANG_DEFAULT);
				} else {
					$langs->setDefaultLang(empty($conf->global->MAIN_LANG_DEFAULT) ? 'auto' : $conf->global->MAIN_LANG_DEFAULT);
				}
				// set global $mysoc required for verify on create
				$mysoc = new Societe($db);
				$mysoc->setMysoc($conf);
				$langs->load("main");
				$langs->load("dict");
				$langs->load("errors");
				$langs->load("companies");
				$langs->load("bills");
				$langs->load("dict");
				parent::__construct($db);
			}
		}
	}

	/**
	 *	Load order related constants
	 *
	 *  @param          stdClass    $params     filter with elements
	 *                                          constant	name of specific constant
	 *
	 *	@return         stdClass result data with specific constant value
	 */
	public function readConstants(stdClass $params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->societe->lire)) return PERMISSIONERROR;

		$results = ExtDirect::readConstants($this->db, $params, $this->_user, $this->_constants);

		return $results;
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

		$sql = 'SELECT st.id , st.code, st.libelle';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'c_stcomm as st';
		$sql .= ' WHERE st.active = 1';

		$resql=$this->db->query($sql);

		if ($resql) {
			$num=$this->db->num_rows($resql);
			for ($i = 0;$i < $num; $i++) {
				$obj = $this->db->fetch_object($resql);
				$row = new stdClass;

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
		$sql = "SELECT rowid, code, label";
		$sql.= " FROM ".MAIN_DB_PREFIX."c_country";
		$sql.= " WHERE active = 1";

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
		$row->id 		    = 0;
		$row->code     	    = '';
		$row->label		    = '';
		$row->country_id    = null;
		array_push($results, $row);

		$sql = "SELECT d.rowid, d.code_departement as code , d.nom as label, p.rowid as country_id FROM";
		$sql .= " ".MAIN_DB_PREFIX ."c_departements as d, ".MAIN_DB_PREFIX."c_regions as r,".MAIN_DB_PREFIX."c_country as p";
		$sql .= " WHERE d.fk_region=r.code_region and r.fk_pays=p.rowid";
		$sql .= " AND d.active = 1 AND r.active = 1 AND p.active = 1";
		if ($country_id) $sql .= " AND p.rowid = ".$country_id;
		$sql .= " ORDER BY p.code, d.code_departement";

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

		$sql = 'SELECT cp.code , cp.label';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'c_prospectlevel as cp';
		$sql .= ' WHERE cp.active = 1';
		$sql .= ' ORDER BY cp.sortorder';

		$resql=$this->db->query($sql);

		if ($resql) {
			$num=$this->db->num_rows($resql);

			for ($i = 0;$i < $num; $i++) {
				$obj = $this->db->fetch_object($resql);
				$row = new stdClass;

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
		global $langs;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->societe->lire)) return PERMISSIONERROR;

		$results = array();

		$sql = "SELECT rowid, code, libelle";
		$sql.= " FROM ".MAIN_DB_PREFIX.'c_payment_term';
		$sql.= " WHERE active=1";
		$sql.= " ORDER BY sortorder";
		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			while ($i < $num) {
				$obj = $this->db->fetch_object($resql);
				$row = new stdClass;
				$row->id = $obj->rowid;
				$row->code = $obj->code;
				$label=($langs->transnoentities("PaymentConditionShort".$obj->code)!=("PaymentConditionShort".$obj->code)?$langs->transnoentities("PaymentConditionShort".$obj->code):($obj->libelle!='-'?$obj->libelle:''));
				$row->label = $label;
				array_push($results, $row);
				$i++;
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
		global $langs;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->societe->lire)) return PERMISSIONERROR;

		$results = array();

		$sql = "SELECT id, code, libelle, type";
		$sql.= " FROM ".MAIN_DB_PREFIX."c_paiement";
		$sql.= " WHERE active > 0";
		$sql.= " ORDER BY id";
		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			while ($i < $num) {
				$obj = $this->db->fetch_object($resql);
				$row = new stdClass;
				$row->id = $obj->id;
				$row->code = $obj->code;
				$label=($langs->transnoentities("PaymentTypeShort".$obj->code)!=("PaymentTypeShort".$obj->code)?$langs->transnoentities("PaymentTypeShort".$obj->code):($obj->libelle!='-'?$obj->libelle:''));
				$row->label = $label;
				$row->type = $obj->type;
				array_push($results, $row);
				$i++;
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
	 *                                      id              Id of third party to load
	 *                                      ref             Reference of third party, name
	 *                                      client          company type 0 = none, 1 = customer, 2 = prospect, 3 = both
	 *                                      stcomm_id       commercial status of third party
	 *                                      town            Town of third party
	 *                                      categorie_id    Categorie id of third party
	 *                                      content         filter on part of name, category or town value
	 *                                      property sort with properties field names and directions:
	 *                                      property limit for paging with sql LIMIT and START values
	 *
	 *    @return     stdClass result data or -1
	 */
	public function readSocieteList(stdClass $params)
	{
		global $conf,$langs;

		if (!isset($this->db)) return CONNECTERROR;
		if (!$this->_enabled) return NOTENABLEDERROR;
		if (!isset($this->_user->rights->societe->lire)) return PERMISSIONERROR;
		$result = new stdClass;
		$data = array();
		$filterSize = 0;
		$limit=null;
		$start=0;
		$includeTotal = true;

		if (isset($params->limit)) {
			$limit = $params->limit;
			$start = $params->start;
		}
		if (isset($params->filter)) {
			$filterSize = count($params->filter);
		}
		if (isset($params->include_total)) {
			$includeTotal = $params->include_total;
		}

		$sqlFields = 'SELECT s.rowid, s.nom as name, s.ref_ext, s.zip, s.town, s.fk_prospectlevel, s.logo, s.entity, code_client, code_fournisseur';
		$sqlFields .= ', st.libelle as commercial_status';
		$sqlFields .= ', c.rowid as categorie_id, c.label as categorie, s.fk_stcomm';
		$sqlFrom = ' FROM '.MAIN_DB_PREFIX.'societe as s';
		$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_stcomm as st ON s.fk_stcomm = st.id';
		$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie_societe as cs ON s.rowid = cs.fk_soc';
		$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie as c ON c.rowid = cs.fk_categorie';
		if (!isset($this->_user->rights->societe->client->voir) && $this->_user->id > 0) {
			$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe_commerciaux as sc ON s.rowid = sc.fk_soc';
		}
		$sqlWhere = ' WHERE s.entity IN ('.getEntity('societe', 1).')';
		if (!isset($this->_user->rights->societe->client->voir) && $this->_user->id > 0) {
			$sqlWhere .= ' AND sc.fk_user = '.$this->_user->id;
		}
		if ($filterSize > 0) {
			// TODO improve sql command to allow random property type
			$sqlWhere .= ' AND (';
			foreach ($params->filter as $key => $filter) {
				$value = $this->db->escape($filter->value);
				if (empty($value)) {
					$sqlWhere .= '1 = 1';
				} else {
					if ($filter->property == 'id')
						$sqlWhere .= 's.rowid = '.$value;
					elseif ($filter->property == 'ref')
						$sqlWhere .= "s.nom = '".$this->db->escape($value)."'";
					elseif ($filter->property == 'client')
						$sqlWhere .= "s.client = ".$value;
					elseif ($filter->property == 'supplier')
						$sqlWhere .= "s.fournisseur = ".$value;
					elseif ($filter->property == 'town')
						$sqlWhere .= "s.town = '".$this->db->escape($value)."'";
					elseif ($filter->property == 'stcomm_id')
						$sqlWhere .= "s.fk_stcomm = ".$value;
					elseif ($filter->property == 'categorie_id') {
						//allow filtering on non categorized societe
						if ($value == 0) {
							$sqlWhere .= "c.rowid IS NULL";
						} else {
							$sqlWhere .= "c.rowid = ".$value;
						}
					} elseif ($filter->property == 'content') {
						$fields = array('s.nom', 'c.label', 's.code_client', 's.code_fournisseur', 's.town', 's.zip');
						$sqlWhere .= natural_search($fields, $value, 0, 1);
					} else {
						$sqlWhere .= '1 = 1';
					}
				}
				if ($key < ($filterSize-1)) {
					if ($filter->property == $params->filter[$key+1]->property) $sqlWhere .= ' OR ';
					else $sqlWhere .= ') AND (';
				}
			}
			$sqlWhere .= ')';
		}
		$sqlOrder = " ORDER BY ";
		if (isset($params->sort)) {
			$sorterSize = count($params->sort);
			foreach ($params->sort as $key => $sort) {
				if (!empty($sort->property)) {
					if ($sort->property == 'code_supplier') $sort->property = 'code_fournisseur';
					$sqlOrder .= $sort->property. ' '.$sort->direction;
					if ($key < ($sorterSize-1)) {
						$sqlOrder .= ",";
					}
				}
			}
		} else {
			 $sqlOrder .= "name ASC";
		}

		if ($limit) {
			$sqlLimit = $this->db->plimit($limit, $start);
		}

		if ($includeTotal) {
			$sqlTotal = 'SELECT COUNT(*) as total'.$sqlFrom.$sqlWhere;
			$resql=$this->db->query($sqlTotal);

			if ($resql) {
				$obj = $this->db->fetch_object($resql);
				$total = $obj->total;
				$this->db->free($resql);
			} else {
				$error="Error ".$this->db->lasterror();
				dol_syslog(get_class($this)."::readSocieteList ".$error, LOG_ERR);
				return SQLERROR;
			}
		}

		$sql = $sqlFields.$sqlFrom.$sqlWhere.$sqlOrder.$sqlLimit;

		$resql=$this->db->query($sql);

		if ($resql) {
			$num=$this->db->num_rows($resql);

			for ($i = 0;$i < $num; $i++) {
				$obj = $this->db->fetch_object($resql);

				$row = new stdClass;
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
				$row->code_client   = $obj->code_client;
				$row->code_supplier = $obj->code_fournisseur;
				if (!empty($obj->logo)) {
					$dir = $conf->societe->multidir_output[(int) $obj->entity]."/".$obj->rowid."/logos/thumbs";
					$logo_parts = pathinfo($obj->logo);
					$filename=$dir.'/'.$logo_parts['filename'].'_mini.'.$logo_parts['extension'];
					if (dol_is_file($filename)) {
						// Read image path, convert to base64 encoding
						$imgData = base64_encode(file_get_contents($filename));
						// Format the image SRC:  data:{mime};base64,{data};
						if ($imgData) {
							$row->logo_small = 'data: '.dol_mimetype($filename).';base64,'.$imgData;
						}
					}
				}
				array_push($data, $row);
			}
			$this->db->free($resql);
			if ($includeTotal) {
				$result->total = $total;
				$result->data = $data;
				return $result;
			} else {
				return $data;
			}
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
	 *                                  rowid       Id of third party to load
	 *                                  ref         Reference of third party, name
	 *                                  ref_ext     External reference of third party
	 *                                  (Warning, this information is a free field not provided by Dolibarr)
	 *                                  ref_int
	 *                                  idprof1     Prof id 1 of third party
	 *                                  idprof2     Prof id 2 of third party
	 *                                  idprof3     Prof id 3 of third party
	 *                                  idprof4     Prof id 4 of third party
	 *
	 *    @return     stdClass result data or -1
	 */
	public function readSociete(stdClass $param)
	{
		global $conf;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->societe->lire)) return PERMISSIONERROR;
		$results = array();

		$id=null;
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
				elseif ($filter->property == 'ref') $ref=$filter->value;
				elseif ($filter->property == 'ref_ext') $ref_ext=$filter->value;
				elseif ($filter->property == 'ref_int') $ref_int=$filter->value;
				elseif ($filter->property == 'idprof1') $idprof1=$filter->value;
				elseif ($filter->property == 'idprof2') $idprof2=$filter->value;
				elseif ($filter->property == 'idprof3') $idprof3=$filter->value;
				elseif ($filter->property == 'idprof4') $idprof4=$filter->value;
				elseif ($filter->property == 'barcode') $id = $this->fetchIdFromBarcode($filter->value);
			}
		}
		if (!empty($id) || !empty($ref) || !empty($ref_ext) || !empty($ref_int)) {
			if (($result = $this->fetch($id, $ref, $ref_ext, $ref_int, $idprof1, $idprof2, $idprof3, $idprof4)) < 0) {
				if ($result == -2) {
					return array(); // no results
				} else {
					return ExtDirect::getDolError($result, $this->errors, $this->error);
				}
			}

			if (!$this->error) {
				$row = new stdClass;
				$row->id            = (int) $this->id;
				$row->entity        = (int) $this->entity;

				$row->ref           = $this->ref;
				$row->name          = $this->name;
				$row->ref_ext       = $this->ref_ext;

				$row->date_create   = $this->date_creation;
				$row->date_update   = $this->date_modification;
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
				if (ExtDirect::checkDolVersion(0, '11.0')) {
					$row->commercial_status = $this->status_prospect_label;
				} else {
					$row->commercial_status = $this->statut_commercial;
				}
				$row->email         = $this->email;
				$row->url           = $this->url;
				$row->phone         = $this->phone;
				$row->fax           = $this->fax;
				$row->skype         = $this->skype;
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
				$row->note_private   = $this->note_private;
				$row->note_public    = $this->note_public;
				$row->default_lang   = $this->default_lang;
				if (!empty($this->logo)) {
					$dir = $conf->societe->multidir_output[(int) $this->entity]."/".$this->id."/logos/thumbs";
					$logo_parts = pathinfo($this->logo);
					$filename=$dir.'/'.$logo_parts['filename'].'_small.'.$logo_parts['extension'];
					// Read image path, convert to base64 encoding
					$imgData = base64_encode(file_get_contents($filename));
					// Format the image SRC:  data:{mime};base64,{data};
					if ($imgData) {
						$row->logo = 'data: '.dol_mimetype($filename).';base64,'.$imgData;
					}
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
	 * public method to read available company optionals (extra fields)
	 *
	 * @return stdClass result data or ERROR
	 */
	public function readOptionalModel()
	{
		if (!isset($this->db)) return CONNECTERROR;

		return ExtDirect::readOptionalModel($this);
	}

	/**
	 * public method to read company (extra fields) from database
	 *
	 *    @param    stdClass    $param  filter with elements:
	 *                                  id Id of company to load
	 *
	 *    @return     stdClass result data or -1
	 */
	public function readOptionals(stdClass $param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->societe->lire)) return PERMISSIONERROR;
		$results = array();
		$id = 0;

		if (isset($param->filter)) {
			foreach ($param->filter as $key => $filter) {
				if ($filter->property == 'id') $id=$filter->value;
			}
		}

		if ($id > 0) {
			$extraFields = new ExtraFields($this->db);
			if (($result = $this->fetch($id)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
			if (! $this->error) {
				$extraFields->fetch_name_optionals_label($this->table_element);
				$index = 1;
				if (empty($this->array_options)) {
					// create empty optionals to be able to add optionals
					$optionsArray = (!empty($extraFields->attributes[$this->table_element]['label']) ? $extraFields->attributes[$this->table_element]['label'] : null);
					if (is_array($optionsArray) && count($optionsArray) > 0) {
						foreach ($optionsArray as $name => $label) {
							$row = new stdClass;
							$row->id = $index++;
							$row->name = $name;
							$row->value = '';
							$row->object_id = $this->id;
							$row->object_element = $this->element;
							$row->raw_value = null;
							$results[] = $row;
						}
					}
				} else {
					foreach ($this->array_options as $key => $value) {
						$row = new stdClass;
						$name = substr($key, 8); // strip options_
						$row->id = $index++; // ExtJs needs id to be able to destroy records
						$row->name = $name;
						$row->value = $extraFields->showOutputField($name, $value, '', $this->table_element);
						$row->object_id = $this->id;
						$row->object_element = $this->element;
						$row->raw_value = $value;
						$results[] = $row;
					}
				}
			}
		}
		return $results;
	}

	/**
	 * public method to update optionals (extra fields) into database
	 *
	 *    @param    unknown_type    $params  optionals
	 *
	 *    @return     Ambigous <multitype:, unknown_type>|unknown
	 */
	public function updateOptionals($params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->societe->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);

		foreach ($paramArray as &$param) {
			if ($this->id != $param->object_id && ($result = $this->fetch($param->object_id)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
			$this->array_options['options_'.$param->name] = $param->raw_value;
		}
		if (($result = $this->insertExtraFields()) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
		if (is_array($params)) {
			return $paramArray;
		} else {
			return $param;
		}
	}

	/**
	 * public method to add optionals (extra fields) into database
	 *
	 *    @param    unknown_type    $params  optionals
	 *
	 *
	 *    @return     Ambigous <multitype:, unknown_type>|unknown
	 */
	public function createOptionals($params)
	{
		return $this->updateOptionals($params);
	}

	/**
	 * public method to delete optionals (extra fields) into database
	 *
	 *    @param    unknown_type    $params  optionals
	 *
	 *    @return    Ambigous <multitype:, unknown_type>|unknown
	 */
	public function destroyOptionals($params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->societe->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);

		foreach ($paramArray as &$param) {
			if ($this->id != $param->object_id && ($result = $this->fetch($param->object_id)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
		}
		if (($result = $this->deleteExtraFields()) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
		if (is_array($params)) {
			return $paramArray;
		} else {
			return $param;
		}
	}

	/**
	 *    Load used towns from available societes
	 *
	 *    @param    stdClass    $params     filter with elements:
	 *                                      rowid       Id of third party to load
	 *                                      ref         Reference of third party, name
	 *                                      ref_ext     External reference of third party
	 *                                      (Warning, this information is a free field not provided by Dolibarr)
	 *                                      idprof1     Prof id 1 of third party
	 *                                      idprof2     Prof id 2 of third party
	 *                                      idprof3     Prof id 3 of third party
	 *                                      idprof4     Prof id 4 of third party
	 *    @return     stdClass result data or error string
	 */
	public function getTowns(stdClass $params)
	{
		global $conf;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->societe->lire)) return PERMISSIONERROR;
		$error=0;
		$results = array();
		$sql = 'SELECT distinct s.town, s.zip';

		$sql .= ' FROM '.MAIN_DB_PREFIX.'societe as s';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_effectif as e ON s.fk_effectif = e.id';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_country as p ON s.fk_pays = p.rowid';
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
				elseif ($filter->property == 'ref')
					$sql .= "(s.nom = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
				elseif ($filter->property == 'ref_ext')
					$sql .= "(s.ref_ext = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
				elseif ($filter->property == 'idprof1')
					$sql .= "(s.siren = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
				elseif ($filter->property == 'idprof2')
					$sql .= "(s.siret = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
				elseif ($filter->property == 'idprof3')
					$sql .= "(s.ape = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
				elseif ($filter->property == 'idprof4')
					$sql .= "(s.idprof4 = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
				elseif ($filter->property == 'content') {
					$fields = array('s.town', 's.zip');
					$sql .= natural_search($fields, $filter->value, 0, 1);
				} else break;
				if ($key < ($filterSize-1)) {
					if ($filter->property == $params->filter[$key+1]->property) $sql .= ' OR ';
					else $sql .= ') AND (';
				}
			}
			$sql .= ')';
		}
		$sql .= ' ORDER BY town';

		$resql=$this->db->query($sql);

		if ($resql) {
			$num=$this->db->num_rows($resql);

			for ($i = 0;$i < $num; $i++) {
				$obj = $this->db->fetch_object($resql);
				$row = new stdClass;

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
	 *                                      rowid       Id of third party to load
	 *                                      ref         Reference of third party, name
	 *                                      idprof1     Prof id 1 of third party
	 *                                      idprof2     Prof id 2 of third party
	 *                                      idprof3     Prof id 3 of third party
	 *                                      idprof4     Prof id 4 of third party
	 *                                      town        town of third party
	 *    @return     stdClass result data or error string
	 */
	public function getCategories(stdClass $params)
	{
		global $conf, $langs;

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
				elseif ($filter->property == 'ref')
					$sql .= "(s.nom = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
				elseif ($filter->property == 'ref_ext')
					$sql .= "(s.ref_ext = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
				elseif ($filter->property == 'idprof1')
					$sql .= "(s.siren = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
				elseif ($filter->property == 'idprof2')
					$sql .= "(s.siret = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
				elseif ($filter->property == 'idprof3')
					$sql .= "(s.ape = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
				elseif ($filter->property == 'idprof4')
					$sql .= "(s.idprof4 = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
				elseif ($filter->property == 'town') {
					$sql .= "(s.town = '".$this->db->escape($filter->value)."' AND s.entity = ".$conf->entity.")";
				} else break;
				if ($key < ($filterSize-1)) {
					if ($filter->property == $params->filter[$key+1]->property) $sql .= ' OR ';
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
		$resql=$this->db->query($sql);
		if ($resql) {
			$num=$this->db->num_rows($resql);

			for ($i = 0;$i < $num; $i++) {
				$obj = $this->db->fetch_object($resql);
				$row = new stdClass;
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
	public function createSociete($params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->societe->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);

		foreach ($paramArray as &$param) {
			// prepare fields
			$this->prepareFields($param);
			// create

			if (($result = $this->create($this->_user)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);

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
	public function updateSociete($params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->societe->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);
		$result = 0;
		// dolibarr update settings
		$allowmodcodeclient=1;
		$call_trigger=1;
		$allowmodcodefournisseur=1;
		foreach ($paramArray as &$param) {
			// prepare fields
			if ($param->id) {
				$id = $param->id;
				$this->id = $id;
				if (($result = $this->fetch($id)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);

				$this->prepareFields($param);

				// update
				if (($result = $this->update($id, $this->_user, $call_trigger, $allowmodcodeclient, $allowmodcodefournisseur)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				if ($param->stcomm_id || $param->fk_prospectlevel) {
					if ($this->updateProspectStatLevel($id, $param->stcomm_id, $param->fk_prospectlevel) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
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
	public function destroySociete($params)
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
				if (($result = $this->delete($id)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
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
	 * Ext.direct method to upload image file for societe object
	 *
	 * @param unknown_type $params object or object array with uploaded file(s)
	 * @return Array    ExtDirect response message
	 */
	public function fileUpload($params)
	{
		global $conf;
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->societe->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);
		$dir = null;

		foreach ($paramArray as &$param) {
			if (isset($param['extTID'])) {
				$id = $param['extTID'];
				if ($id) {
					$dir = $conf->societe->multidir_output[$conf->entity]."/".$id."/";
				} else {
					$response = PARAMETERERROR;
					break;
				}
			} elseif (isset($param['file']) && isset($dir)) {
				$response = ExtDirect::fileUpload($param, $dir);
			} else {
				$response = PARAMETERERROR;
				break;
			}
		}
		return $response;
	}

	/**
	 * private method to update stcomm_id and fk_prospectlevel field in societe table
	 *
	 * @param number $id            societe id
	 * @param number $stcomm_id     statut commercial id
	 * @param string $prospectlevel prospectlevel foreign key
	 * @return number
	 */
	private function updateProspectStatLevel($id, $stcomm_id, $prospectlevel)
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
			dol_syslog(get_class($this)."::Update fails", LOG_ERR);
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 * private method to fetch id from given barcode, also search in code_client and code_fournisseur
	 *
	 * @param string $barcode barcode to fetch id from
	 * @return integer $id rowid of element
	 */
	private function fetchIdFromBarcode($barcode)
	{
		$id =0;
		dol_syslog(__METHOD__.' : '.$barcode);
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE barcode ='".$this->db->escape($barcode)."' OR code_client = '".$this->db->escape($barcode)."' OR code_fournisseur = '".$this->db->escape($barcode)."'";
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
		isset($params->phone) ? ($this->phone = $params->phone) : null;
		isset($params->skype) ? ($this->skype = $params->skype) : null;
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
		isset($params->note_public) ? ($this->note_public = $params->note_public) : null;
		isset($params->note_private) ? ($this->note_private = $params->note_private) : null;
		isset($params->typent_id) ? ($this->typent_id = $params->typent_id) : null;
		/*	$img = str_replace('data:image/png;base64,', '', $params->logo);
			$img = str_replace(' ', '+', $img);
			$data = base64_decode($img);*/
	}
}
