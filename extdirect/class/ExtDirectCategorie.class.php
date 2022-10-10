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
 *  \file       htdocs/extdirect/class/ExtDirectCategorie.class.php
 *  \brief      Sencha Ext.Direct categories remoting class
 */

require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
dol_include_once('/extdirect/class/extdirect.class.php');

/** ExtDirectCategorie class
 *
 * Class to access categories with CRUD(L) methods to connect to Extjs or sencha touch using Ext.direct connector
 *
 * @category External_Module
 * @package  Extdirect
 * @author   Francis Appels <francis.appels@z-application.com>
 * @license  http://www.gnu.org/licenses/ GPLV3
 * @version  Release: 1.0
 * @link     https://github.com/fappels/dolibarrextdirect/blob/master/extdirect/class/ExtDirectCategorie.class.php
 */
class ExtDirectCategorie extends Categorie
{
	private $_user;

	/** Constructor
	 *
	 * @param string $login user name
	 */
	public function __construct($login)
	{
		global $conf, $langs, $db, $user;

		if (!empty($login)) {
			if ((is_object($login) && get_class($db) == get_class($login)) || $user->id > 0 || $user->fetch('', $login, '', 1) > 0) {
				$user->getrights();
				$this->_user = $user;  //product.class uses global user
				if (isset($this->_user->conf->MAIN_LANG_DEFAULT)) {
					$langs->setDefaultLang($this->_user->conf->MAIN_LANG_DEFAULT);
				} else {
					$langs->setDefaultLang(empty($conf->global->MAIN_LANG_DEFAULT) ? 'auto' : $conf->global->MAIN_LANG_DEFAULT);
				}
				$langs->load("main");
				$langs->load("dict");
				$langs->load("errors");
				$langs->load("categories");
				parent::__construct($db);
			}
		}
	}

	/**
	 *    Load categories from database into memory
	 *
	 *    @param    stdClass    $param  filter with elements:
	 *                                  id      Id of product to load
	 *                                  label   Reference of product, name
	 *
	 *    @return     stdClass result data or -1
	 */
	public function readCategorie(stdClass $param)
	{
		global $conf,$langs;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->categorie->lire)) return PERMISSIONERROR;
		$results = array();
		$row = new stdClass;
		$id = 0;
		$label = '';

		if (isset($param->filter)) {
			foreach ($param->filter as $key => $filter) {
				if ($filter->property == 'id') $id=$filter->value;
				elseif ($filter->property == 'label') $label=$filter->value;
			}
		}

		if (($id > 0) || ($label != '')) {
			if (($result = $this->fetch($id, $label)) < 0)    return $result;
			if (!$this->error) {
				$row->id           = $this->id ;
				$row->fk_parent    = $this->fk_parent;
				$row->label        = $this->label;
				$row->description  = $this->description?$this->description:'';
				$row->company_id   = $this->socid;
				// 0=Product, 1=Supplier, 2=Customer/Prospect, 3=Member
				$row->type= $this->type;
				$row->entity= $this->entity;
				array_push($results, $row);
			} else {
				return 0;
			}
		}

		return $results;
	}


	/**
	 * Ext.direct method to Create categorie
	 *
	 * @param unknown_type $params object or object array with categorie model(s)
	 * @return Ambigous <multitype:, unknown_type>|unknown
	 */
	public function createCategorie($params)
	{

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->categorie->creer)) return PERMISSIONERROR;
		$notrigger=0;
		$paramArray = ExtDirect::toArray($params);

		foreach ($paramArray as &$param) {
			// prepare fields
			$this->prepareFields($param);
			if (($result = $this->create($this->_user)) < 0) return $result;

			$param->id=$this->id;
		}

		if (is_array($params)) {
			return $paramArray;
		} else {
			return $param;
		}
	}

	/**
	 * Ext.direct method to update categorie
	 *
	 * @param unknown_type $params object or object array with categorie model(s)
	 * @return Ambigous <multitype:, unknown_type>|unknown
	 */
	public function updateCategorie($params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->categorie->creer)) return PERMISSIONERROR;
		// dolibarr update settings

		$paramArray = ExtDirect::toArray($params);
		foreach ($paramArray as &$param) {
			// prepare fields
			if ($param->id) {
				$id = $param->id;
				$this->id = $id;
				if (($result = $this->fetch($id, '')) < 0)    return $result;
				$this->prepareFields($param);
				// update
				if (($result = $this->update($this->_user)) < 0)   return $result;
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
	 * Ext.direct method to destroy categorie
	 *
	 * @param unknown_type $params object or object array with categorie model(s)
	 * @return Ambigous <multitype:, unknown_type>|unknown
	 */
	public function destroyCategorie($params)
	{
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->categorie->supprimer)) return PERMISSIONERROR;
		$paramArray = ExtDirect::toArray($params);

		foreach ($paramArray as &$param) {
			// prepare fields
			if ($param->id) {
				$id = $param->id;
				$this->id = $id;
				$this->fk_parent = 0; // bug in categorie.class.php introduced in 3.5.4
				// delete product
				if (($result = $this->delete($this->_user)) <= 0)    return $result;
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
	 * public method to read a list of categories
	 *
	 * @param stdClass $param to    filter on type
	 *
	 * @return     stdClass result data or -1
	 */
	public function readCategorieList(stdClass $param)
	{
		global $conf, $langs;
		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->produit->lire)) return PERMISSIONERROR;
		$results = array();
		$cats = array();
		$row = new stdClass;
		$type = 0;

		if (isset($param->filter)) {
			foreach ($param->filter as $key => $filter) {
				if ($filter->property == 'type') $type=$filter->value;
			}
		}

		if (($cats = $this->get_all_categories($type, false)) < 0) return $cats;
		// id 0 is not categorised
		$row->id = 0;
		$row->categorie = ($langs->trans('NotCategorized') ? $langs->trans('NotCategorized') : 'Without category');
		array_push($results, $row);

		foreach ($cats as $cat) {
			$row = new stdClass;
			$row->id = $cat->id;
			$row->categorie = $cat->label;
			array_push($results, $row);
		}
		return $results;
	}

	/**
	 * private method to copy fields into dolibarr object
	 *
	 * @param stdclass $param object with fields
	 * @return null
	 */
	private function prepareFields($param)
	{
		isset($param->id) ? ( $this->id = $param->id ) : null;
		isset($param->label) ? ( $this->label = $param->label) : null;
		isset($param->fk_parent) ? ( $this->fk_parent =$param->fk_parent) : null;
		isset($param->description) ? ( $this->description = $param->description) : null;
		// 0=Product, 1=Supplier, 2=Customer/Prospect, 3=Member
		isset($param->type) ? ( $this->type = $param->type) : null;
		isset($param->company_id) ? ( $this->socid = $param->company_id) : null;
		isset($param->entity) ? ( $this->entity = $param->entity ) : null;
	}
}
