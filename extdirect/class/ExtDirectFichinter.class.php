<?PHP

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
 *  \file       htdocs/extdirect/class/ExtDirectFichinter.class.php
 *  \brief      Sencha Ext.Direct interventions remoting class
 */

require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';
dol_include_once('/extdirect/class/extdirect.class.php');

/**
 * ExtDirectFichinter class
 *
 * Interventions Class to with CRUD methods to connect to Extjs or sencha touch using Ext.direct connector
 */
class ExtDirectFichinter extends Fichinter
{
	private $_user;
	private $_constants = array(
		'FICHINTER_PRINT_PRODUCTS',
		'FICHINTER_USE_SERVICE_DURATION',
		'FICHINTER_WITHOUT_DURATION',
		'FICHINTER_DATE_WITHOUT_HOUR'
	);
	private $_enabled = false;

	/**
	 * end status to allow status itteration
	 */
	const STATUS_END = 4;

	/**
	 * Constructor
	 *
	 * @param string $login user name
	 */
	public function __construct($login)
	{
		global $langs, $db, $user, $conf, $mysoc;

		if (!empty($login)) {
			if ((is_object($login) && get_class($db) == get_class($login)) || $user->id > 0 || $user->fetch('', $login, '', 1) > 0) {
				$user->getrights();
				$this->_enabled = !empty($conf->ficheinter->enabled) && isset($user->rights->ficheinter->lire);
				$this->_user = $user;  //commande.class uses global user
				if (isset($this->_user->conf->MAIN_LANG_DEFAULT)) {
					$langs->setDefaultLang($this->_user->conf->MAIN_LANG_DEFAULT);
				} else {
					$langs->setDefaultLang(empty($conf->global->MAIN_LANG_DEFAULT) ? 'auto' : $conf->global->MAIN_LANG_DEFAULT);
				}
				// set global $mysoc required for price calculation
				$mysoc = new Societe($db);
				$mysoc->setMysoc($conf);
				$langs->load("main");
				$langs->load("dict");
				$langs->load("errors");
				$langs->load("interventions");
				parent::__construct($db);
			}
		}
	}

	/**
	 *	Load intervention related constants
	 *
	 *	@param			stdClass	$params		filter with elements
	 *		                                    constant	name of specific constant
	 *
	 *	@return			stdClass result data with specific constant value
	 */
	public function readConstants(stdClass $params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->ficheinter->lire)) return PERMISSIONERROR;

		$results = ExtDirect::readConstants($this->db, $params, $this->_user, $this->_constants);

		return $results;
	}

	/**
	 *    Load intervention from database into memory
	 *
	 *    @param    stdClass    $params     filter with elements:
	 *                                      id  Id of intervention to load
	 *                                      ref ref, ref_int
	 *
	 *    @return     stdClass result data or error number
	 */
	public function readIntervention(stdClass $params)
	{
		global $conf;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->ficheinter->lire)) return PERMISSIONERROR;
		$myUser = new User($this->db);
		$mySociete = new Societe($this->db);
		if (! empty($conf->projet->enabled)) {
			require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
			$project = new Project($this->db);
		}
		if ($conf->contrat->enabled) {
			require_once DOL_DOCUMENT_ROOT."/contrat/class/contrat.class.php";
			$contrat = new Contrat($this->db);
		}

		$results = array();
		$row = new stdClass;
		$id = 0;
		$ref = '';
		$status_ids = array();

		if (isset($params->filter)) {
			foreach ($params->filter as $key => $filter) {
				if ($filter->property == 'id') $id=$filter->value;
				elseif ($filter->property == 'ref') $ref=$filter->value;
				elseif ($filter->property == 'status_id') array_push($status_ids, $filter->value);
			}
		}

		if (($id > 0) || ($ref != '')) {
			if (($result = $this->fetch($id, $ref)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
			if ($this->id) {
				$row->id = $this->id ;
				//! Ref
				$row->ref= $this->ref;
				$row->customer_id = $this->socid;
				if ($mySociete->fetch($this->socid)>0) {
					$row->customer_name = $mySociete->name;
				}
				//! -1 for cancelled, 0 for draft, 1 for validated, 2 for send, 3 for closed
				$row->status_id = $this->statut;
				$row->status = $this->getLibStatut(1);
				$row->description = $this->description;
				$row->note_private = $this->note_private;
				$row->note_public = $this->note_public;
				$row->model_pdf = $this->modelpdf;
				$row->user_id = $this->user_creation;
				if ($this->user_creation > 0 && $myUser->fetch($this->user_creation)>0) {
					$row->user_name = $myUser->firstname . ' ' . $myUser->lastname;
				}
				$row->date_creation = $this->datec;
				$row->valid_date= $this->datev;
				if (isset($project) && $this->fk_project > 0) {
					$project->fetch($this->fk_project);
					$row->project_id = $this->fk_project;
					$row->project_ref = $project->ref;
				}
				if (isset($contrat) && $this->fk_contrat > 0) {
					$contrat->fetch($this->fk_contrat);
					$row->contract_id = $this->fk_contrat;
					$row->contract_ref = $contrat->ref;
				}

				$row->duration = $this->duration;
				$row->has_signature = 0;
				if (empty($status_ids)) {
					array_push($results, $row);
				} else {
					foreach ($status_ids as $status_id) {
						if ($status_id == $row->status_id) {
							array_push($results, $row);
						}
					}
				}
			}
		}

		return $results;
	}

	/**
	 * public method to read available optionals (extra fields)
	 *
	 * @return stdClass result data or ERROR
	 */
	public function readOptionalModel()
	{
		if (!isset($this->db)) return CONNECTERROR;

		return ExtDirect::readOptionalModel($this);
	}

	/**
	 * public method to read intervention optionals (extra fields) from database
	 *
	 *    @param    stdClass    $param  filter with elements:
	 *                                  id Id of intervention to load
	 *
	 *    @return     stdClass result data or -1
	 */
	public function readOptionals(stdClass $param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->ficheinter->lire)) return PERMISSIONERROR;
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
		if (!isset($this->_user->rights->ficheinter->creer)) return PERMISSIONERROR;
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
		if (!isset($this->_user->rights->ficheinter->creer)) return PERMISSIONERROR;
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
	 * Ext.direct method to Create intervention
	 *
	 * @param unknown_type $param object or object array with intervention model(s)
	 * @return Ambigous <multitype:, unknown_type>|unknown
	 */
	public function createIntervention($param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->ficheinter->creer)) return PERMISSIONERROR;
		$notrigger=0;
		$paramArray = ExtDirect::toArray($param);

		foreach ($paramArray as &$params) {
			// prepare fields
			$this->prepareInterventionFields($params);
			if (($result = $this->create($this->_user, $notrigger)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);

			$params->id=$this->id;
		}

		if (is_array($param)) {
			return $paramArray;
		} else {
			return $params;
		}
	}

	/**
	 * Ext.direct method to update intervention
	 *
	 * @param unknown_type $param object or object array with intervention model(s)
	 * @return Ambigous <multitype:, unknown_type>|unknown
	 */
	public function updateIntervention($param)
	{
		global $conf, $langs;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->ficheinter->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($param);
		$notrigger=0;

		foreach ($paramArray as &$params) {
			// prepare fields
			if ($params->id) {
				if (($result = $this->fetch($params->id)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				$this->prepareInterventionFields($params);
				// update
				switch ($params->status_id) {
					case self::STATUS_DRAFT:
						$result = $this->setDraft($this->_user);
						break;
					case self::STATUS_VALIDATED:
						$result = $this->setValid($this->_user, $notrigger);
						// PDF generating
						if (($result >= 0) && empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE)) {
							$hidedetails = (! empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS) ? 1 : 0);
							$hidedesc = (! empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DESC) ? 1 : 0);
							$hideref = (! empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_REF) ? 1 : 0);
							$outputlangs = $langs;
							if ($conf->global->MAIN_MULTILANGS)	{
								$this->fetch_thirdparty();
								$newlang = $this->thirdparty->default_lang;
								$outputlangs = new Translate("", $conf);
								$outputlangs->setDefaultLang($newlang);
							}
							$this->generateDocument($this->modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
						}
						break;
					case self::STATUS_CLOSED:
						$result = $this->setStatut(self::STATUS_CLOSED);;
						break;
					case self::STATUS_BILLED:
						$result = $this->setStatut(self::STATUS_BILLED);;
						break;
					default:
						break;
				}

				if ($result < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				if ($this->fk_contrat > 0) {
					if (($result = $this->set_contrat($this->_user, $this->fk_contrat)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				}
				if (($result = $this->update($this->_user, $notrigger)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
			} else {
				return PARAMETERERROR;
			}
		}
		if (is_array($param)) {
			return $paramArray;
		} else {
			return $params;
		}
	}

	/**
	 * Ext.direct method to destroy intervention
	 *
	 * @param unknown_type $param object or object array with order model(s)
	 * @return Ambigous <multitype:, unknown_type>|unknown
	 */
	public function destroyIntervention($param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->ficheinter->supprimer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($param);
		$notrigger=0;

		foreach ($paramArray as &$params) {
			// prepare fields
			if ($params->id) {
				$this->id = $params->id;
				// delete
				if (($result = $this->delete($this->_user, $notrigger)) < 0)    return ExtDirect::getDolError($result, $this->errors, $this->error);
			} else {
				return PARAMETERERROR;
			}
		}

		if (is_array($param)) {
			return $paramArray;
		} else {
			return $params;
		}
	}

	/**
	 * Ext.direct method to upload file for intervention object
	 *
	 * @param unknown_type $params object or object array with uploaded file(s)
	 * @return Array    ExtDirect response message
	 */
	public function fileUpload($params)
	{
		global $conf;
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->ficheinter->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);
		$dir = null;

		foreach ($paramArray as &$param) {
			if (isset($param['extTID'])) {
				$id = $param['extTID'];
				if ($this->fetch($id)) {
					$dir = $conf->ficheinter->dir_output.'/'.dol_sanitizeFileName($this->ref);
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
	 * private method to copy order fields into dolibarr object
	 *
	 * @param stdclass $params object with fields
	 * @return null
	 */
	private function prepareInterventionFields($params)
	{
		isset($params->ref) ? ( $this->ref = $params->ref ) : null;
		isset($params->customer_id) ? ( $this->socid = $params->customer_id) : null;
		isset($params->note_private) ? ( $this->note_private =$params->note_private) : null;
		isset($params->note_public) ? ( $this->note_public = $params->note_public ) : null;
		isset($params->description) ? ( $this->description =$params->description) : null;
		isset($params->duration) ? ( $this->duration =$params->duration) : null;
		isset($params->project_id) ? ( $this->fk_projet =$params->project_id) : null;
		isset($params->contract_id) ? ( $this->fk_contrat =$params->contract_id) :null;
	}

	/**
	 * public method to read a list of interventions
	 *
	 * @param stdClass $params to filter on order status and ref
	 * @return     stdClass result data or error number
	 */
	public function readList(stdClass $params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!$this->_enabled) return NOTENABLEDERROR;
		if (!isset($this->_user->rights->ficheinter->lire)) return PERMISSIONERROR;
		$result = new stdClass;
		$data = array();

		$statusFilterCount = 0;
		$ref = null;
		$contactTypeId = 0;
		$barcode = null;
		$contentFilter = null;

		$includeTotal = true;

		if (isset($params->limit)) {
			$limit = $params->limit;
			$start = $params->start;
		}
		if (isset($params->include_total)) {
			$includeTotal = $params->include_total;
		}

		if (isset($params->filter)) {
			foreach ($params->filter as $key => $filter) {
				if ($filter->property == 'status_id') $orderstatus_id[$statusFilterCount++]=$filter->value;
				elseif ($filter->property == 'ref') $ref=$filter->value;
				elseif ($filter->property == 'contacttype_id') $contactTypeId = $filter->value;
				elseif ($filter->property == 'contact_id') $contactId = $filter->value;
				elseif ($filter->property == 'barcode') $barcode = $filter->value;
				elseif ($filter->property == 'content') $contentFilter = $filter->value;
			}
		}

		$sqlFields = "SELECT s.nom, s.rowid AS socid, i.rowid, i.ref, i.description, i.fk_statut, ea.status, s.price_level, i.fk_user_author, i.datec, u.firstname, u.lastname";
		$sqlFrom = " FROM ".MAIN_DB_PREFIX."fichinter as i";
		$sqlFrom .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON i.fk_soc = s.rowid";
		$sqlFrom .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON i.fk_user_author = u.rowid";
		if ($contactTypeId > 0) $sqlFrom .= " LEFT JOIN ".MAIN_DB_PREFIX."element_contact as ec ON i.rowid = ec.element_id";
		$sqlFrom .= " LEFT JOIN ("; // get latest extdirect activity status for commande to check if locked
		$sqlFrom .= "   SELECT ma.activity_id, ma.maxrow AS rowid, ea.status";
		$sqlFrom .= "   FROM (";
		$sqlFrom .= "    SELECT MAX( rowid ) AS maxrow, activity_id";
		$sqlFrom .= "    FROM ".MAIN_DB_PREFIX."extdirect_activity";
		$sqlFrom .= "    GROUP BY activity_id";
		$sqlFrom .= "   ) AS ma, ".MAIN_DB_PREFIX."extdirect_activity AS ea";
		$sqlFrom .= "   WHERE ma.maxrow = ea.rowid";
		$sqlFrom .= " ) AS ea ON i.rowid = ea.activity_id";
		$sqlWhere = " WHERE i.entity IN (".getEntity('fichinter', 1).')';

		if ($statusFilterCount>0) {
			$sqlWhere .= " AND ( ";
			foreach ($orderstatus_id as $key => $fk_statut) {
				$sqlWhere  .= "i.fk_statut = ".$fk_statut;
				if ($key < ($statusFilterCount-1)) $sqlWhere  .= " OR ";
			}
			$sqlWhere .= ")";
		}
		if ($ref) {
			$sqlWhere .= " AND i.ref = '".$ref."'";
		}
		if ($contactTypeId > 0) {
			$sqlWhere .= " AND ec.fk_c_type_contact = ".$contactTypeId;
			$sqlWhere .= " AND ec.fk_socpeople = ".$contactId;
		}
		if ($barcode) {
			$sqlWhere .= " AND (i.ref = '".$this->db->escape($barcode)."' OR i.ref_ext = '".$this->db->escape($barcode)."')";
		}

		if ($contentFilter) {
			$fields = array('i.ref', 'i.description', 's.nom', 'u.firstname', 'u.lastname');
			$sqlWhere .= " AND ".natural_search($fields, $contentFilter, 0, 1);
		}

		$sqlOrder = " ORDER BY i.datec DESC";

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
				dol_syslog(get_class($this)."::readInterventionList ".$error, LOG_ERR);
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
				$row->id            = (int) $obj->rowid;
				$row->customer      = $obj->nom;
				$row->customer_id   = (int) $obj->socid;
				$row->ref           = $obj->ref;
				$row->description   = $obj->description;
				$row->status_id= (int) $obj->fk_statut;
				$row->status   = html_entity_decode($this->LibStatut($obj->fk_statut, false, 1));
				$row->user_id 		= $obj->fk_user_author;
				$row->user_name     = $obj->firstname . ' ' . $obj->lastname;
				$row->date_creation  = $this->db->jdate($obj->datec);
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
			dol_syslog(get_class($this)."::readInterventionList ".$error, LOG_ERR);
			return SQLERROR;
		}
	}

	/**
	 * public method to read a list of interventionstatusses
	 *
	 * @return     stdClass result data or error number
	 */
	public function readStatus()
	{
		if (!isset($this->db)) return CONNECTERROR;
		$results = array();
		$statut = 0;
		$row = new stdClass;
		while ($statut < self::STATUS_END) {
			$result = $this->LibStatut($statut, 1);
			if (!empty($result)) {
				$row = new stdClass;
				$row->id = $statut;
				$row->status = html_entity_decode($result);
				array_push($results, $row);
			}
			$statut++;
		}
		return $results;
	}

	/**
	 * public method to read a list of contac types
	 *
	 * @return     stdClass result data or error number
	 */
	public function readContactTypes()
	{
		if (!isset($this->db)) return CONNECTERROR;
		$results = array();
		if (! is_array($result = $this->liste_type_contact())) return ExtDirect::getDolError($result, $this->errors, $this->error);
		// add empty type
		$row = new stdClass;
		$row->id = 0;
		$row->label = '';
		array_push($results, $row);
		foreach ($result as $id => $label) {
			$row = new stdClass;
			$row->id = $id;
			$row->label = html_entity_decode($label);
			array_push($results, $row);
		}
		return $results;
	}

	/**
	 *    Load intervention lines from database into memory
	 *
	 *    @param    stdClass    $params     filter with elements:
	 *                                      intervention_id Id of intervention to load lines
	 *
	 *    @return     stdClass result data or error number
	 */
	public function readInterventionLine(stdClass $params)
	{
		global $conf;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->ficheinter->lire)) return PERMISSIONERROR;

		$results = array();
		$intervention_id = 0;

		if (isset($params->filter)) {
			foreach ($params->filter as $key => $filter) {
				if ($filter->property == 'intervention_id') $intervention_id=$filter->value;
			}
		}

		if ($intervention_id > 0) {
			$this->id=$intervention_id;
			if (($result = $this->fetch_lines()) < 0)  return ExtDirect::getDolError($result, $this->errors, $this->error);
			if (!$this->error) {
				foreach ($this->lines as $line) {
					// get orderline with complete stock
					$row = new stdClass;
					$row->id = $line->id;
					$row->origin_id = $intervention_id;
					$row->line_id = $line->id;
					$row->description = $line->desc;
					$row->qty = $line->qty;
					$row->duration = $line->duration;
					$row->date = $line->date;
					$row->rang = $line->rang;
					$row->product_type = $line->product_type;
					array_push($results, $row);
				}
			} else {
				return 0;
			}
		}
		return $results;
	}

	/**
	 * public method to read available line optionals (extra fields)
	 *
	 * @return stdClass result data or ERROR
	 */
	public function readLineOptionalModel()
	{
		if (!isset($this->db)) return CONNECTERROR;

		$line = new FichinterLigne($this->db);

		return ExtDirect::readOptionalModel($line);
	}

	/**
	 * public method to read intervention line optionals (extra fields) from database
	 *
	 *    @param    stdClass    $param  filter with elements:
	 *                                  id Id of intervention line to load
	 *
	 *    @return     stdClass result data or -1
	 */
	public function readLineOptionals(stdClass $param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->ficheinter->lire)) return PERMISSIONERROR;
		$results = array();
		$line_id = 0;

		if (isset($param->filter)) {
			foreach ($param->filter as $key => $filter) {
				if ($filter->property == 'line_id') $line_id=$filter->value;
			}
		}

		if ($line_id > 0) {
			$extraFields = new ExtraFields($this->db);
			$line = new FichinterLigne($this->db);
			$line->id = $line_id;
			if (($result = $line->fetch_optionals()) < 0) return ExtDirect::getDolError($result, $line->errors, $line->error);
			if (! $line->error) {
				$extraFields->fetch_name_optionals_label($line->table_element);
				$index = 1;
				if (empty($line->array_options)) {
					// create empty optionals to be able to add optionals
					$optionsArray = (!empty($extraFields->attributes[$line->table_element]['label']) ? $extraFields->attributes[$line->table_element]['label'] : null);
					if (is_array($optionsArray) && count($optionsArray) > 0) {
						foreach ($optionsArray as $name => $label) {
							$row = new stdClass;
							$row->id = $index++;
							$row->name = $name;
							$row->value = '';
							$row->object_id = $line->id;
							$row->object_element = $line->element;
							$row->raw_value = null;
							$results[] = $row;
						}
					}
				} else {
					foreach ($line->array_options as $key => $value) {
						if (!empty($value)) {
							$row = new stdClass;
							$name = substr($key, 8); // strip options_
							$row->id = $index++; // ExtJs needs id to be able to destroy records
							$row->name = $name;
							$row->value = $extraFields->showOutputField($name, $value, '', $line->table_element);
							$row->object_id = $line->id;
							$row->object_element = $line->element;
							$row->raw_value = $value;
							$results[] = $row;
						}
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
	public function updateLineOptionals($params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->ficheinter->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);

		$line = new FichinterLigne($this->db);
		foreach ($paramArray as &$param) {
			if ($line->id != $param->object_id) {
				$line->id = $param->object_id;
				if (($result = $line->fetch_optionals()) < 0) return ExtDirect::getDolError($result, $line->errors, $line->error);
			}
			$line->array_options['options_'.$param->name] = $param->raw_value;
		}
		if (($result = $line->insertExtraFields()) < 0) return ExtDirect::getDolError($result, $line->errors, $line->error);
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
	public function createLineOptionals($params)
	{
		return $this->updateLineOptionals($params);
	}

	/**
	 * public method to delete optionals (extra fields) into database
	 *
	 *    @param    unknown_type    $params  optionals
	 *
	 *    @return    Ambigous <multitype:, unknown_type>|unknown
	 */
	public function destroyLineOptionals($params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->ficheinter->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);

		$line = new FichinterLigne($this->db);
		foreach ($paramArray as &$param) {
			if ($line->id != $param->object_id) {
				$line->id = $param->object_id;
				if (($result = $line->fetch_optionals()) < 0) return ExtDirect::getDolError($result, $line->errors, $line->error);
			}
		}
		if (($result = $line->deleteExtraFields()) < 0) return ExtDirect::getDolError($result, $line->errors, $line->error);
		if (is_array($params)) {
			return $paramArray;
		} else {
			return $param;
		}
	}

	/**
	 * Ext.direct method to Create intervention lines
	 *
	 * @param unknown_type $param object or object array with product model(s)
	 * @return Ambigous <multitype:, unknown_type>|unknown
	 */
	public function createInterventionLine($param)
	{
		global $conf, $mysoc;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->ficheinter->creer)) return PERMISSIONERROR;
		$line = new FichinterLigne($this->db);
		$result = 0;

		$notrigger=0;
		$paramArray = ExtDirect::toArray($param);

		foreach ($paramArray as &$params) {
			if ($params->origin_id > 0 && !empty($params->date) && !empty($params->duration)) {
				// prepare fields
				$this->prepareInterventionLineFields($params, $line);
				if (($result = $this->fetch($line->fk_fichinter)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);

				if (($result = $this->addline(
					$this->_user,
					$line->fk_fichinter,
					$line->desc,
					$line->datei,
					$line->duration
				)) < 0) return ExtDirect::getDolError($result, $this->errors, $this->error);
				$params->id=$result;
			} else {
				return PARAMETERERROR;
			}
		}

		if (is_array($param)) {
			return $paramArray;
		} else {
			return $params;
		}
	}

	/**
	 * Ext.direct method to update interventionlines
	 *
	 * @param unknown_type $param object or object array with order model(s)
	 * @return Ambigous <multitype:, unknown_type>|unknown
	 */
	public function updateInterventionLine($param)
	{
		global $conf, $mysoc;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->ficheinter->creer)) return PERMISSIONERROR;
		$line = new FichinterLigne($this->db);
		$paramArray = ExtDirect::toArray($param);
		$notrigger=0;

		foreach ($paramArray as &$params) {
			if ($params->line_id > 0) {
				// get old line
				if (($result = $line->fetch($params->line_id)) < 0)  return ExtDirect::getDolError($result, $line->errors, $line->error);

				if (!$this->error) {
					$this->prepareInterventionLineFields($params, $line);
					if (($result = $line->update($this->_user, $notrigger)) < 0)  return ExtDirect::getDolError($result, $line->errors, $line->error);
				}
			} else {
				return PARAMETERERROR;
			}
		}
		if (is_array($param)) {
			return $paramArray;
		} else {
			return $params;
		}
	}

	/**
	 * Ext.direct method to destroy orderlines
	 *
	 * @param unknown_type $param object or object array with order model(s)
	 * @return Ambigous <multitype:, unknown_type>|unknown
	 */
	public function destroyInterventionLine($param)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->ficheinter->creer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($param);
		$line = new FichinterLigne($this->db);
		$notrigger=0;

		foreach ($paramArray as &$params) {
			if ($params->id) {
				// prepare fields
				$line->fetch($params->id);
				$this->id = $line->fk_fichinter;
				// delete
				if (($result = $line->deleteline($this->_user, $notrigger)) < 0) return ExtDirect::getDolError($result, $line->errors, $line->error);
			} else {
				return PARAMETERERROR;
			}
		}

		if (is_array($param)) {
			return $paramArray;
		} else {
			return $params;
		}
	}

	/**
	 * private method to copy intervention fields into dolibarr object
	 *
	 * @param stdclass $params  object with fields
	 * @param stdclass $line    object
	 * @return null
	 */
	private function prepareInterventionLineFields($params, &$line)
	{
		isset($params->line_id) ? ( $line->rowid= $params->line_id) : null;
		isset($params->line_id) ? ( $line->id= $params->line_id) : null;
		isset($params->origin_id) ? ( $line->fk_fichinter= $params->origin_id) : null;
		isset($params->date) ? ( $line->datei = $params->date) : null;
		isset($params->duration) ? ( $line->duration = $params->duration) : null;
		isset($params->description) ? ( $line->desc = $params->description) : null;
		isset($params->rang) ? ($line->rang = $params->rang) : null;
	}
}
