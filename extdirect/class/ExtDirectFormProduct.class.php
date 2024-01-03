<?PHP

/**
 * Copyright (C) 2012-2014  Francis Appels <francis.appels@z-application.com>
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
 *  \file       htdocs/extdirect/class/ExtDirectProduct.class.php
 *  \brief      Sencha Ext.Direct product helpers remoting class
 */
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
dol_include_once('/extdirect/class/extdirect.class.php');

/** ExtDirectFormProduct class
 * Class with product helpers methods to connect to Extjs or sencha touch using Ext.direct connector
 */
class ExtDirectFormProduct extends FormProduct
{
	private $_user;

	const ALLWAREHOUSE_ID = 0;
	const ALLWAREHOUSE_LABEL = 'AllLocationsLabel';
	const ALLWAREHOUSE_DESCRIPTION = 'AllLocationsDesc';

	public $errors = array();

	/** Constructor
	 *
	 * @param string $login user name
	 * @return number
	 *
	 */
	public function __construct($login)
	{
		global $conf, $langs, $user, $db;

		if (!empty($login)) {
			if ((is_object($login) && get_class($db) == get_class($login)) || $user->id > 0 || $user->fetch('', $login, '', 1) > 0) {
				$user->getrights();
				$this->_user = $user;  //commande.class uses global user
				if (isset($this->_user->conf->MAIN_LANG_DEFAULT)) {
					$langs->setDefaultLang($this->_user->conf->MAIN_LANG_DEFAULT);
				} else {
					$langs->setDefaultLang(empty($conf->global->MAIN_LANG_DEFAULT) ? 'auto' : $conf->global->MAIN_LANG_DEFAULT);
				}
				$langs->load("main");
				$langs->load("dict");
				$langs->load("errors");
				$langs->load("products");
				$langs->load("extdirect@extdirect");
				parent::__construct($db);
			}
		}
	}


	/**
	 *    Load Warehouses from database into memory
	 *
	 *    @param    stdClass    $params     filter with elements:
	 *                                      productid load warehouses of one product
	 *
	 *    @return     stdClass result data or -1
	 */
	public function readWarehouses(stdClass $params)
	{
		global $conf,$langs;

		if (!isset($this->db)) return CONNECTERROR;

		$result = new stdClass;
		$data = array();

		$fkProduct = 0;
		$fkBatch = 0;
		$batch = '';
		$sumStock = true;
		$limit = 0;
		$start = 0;
		$contentValue = '';
		if (ExtDirect::checkDolVersion(0, '5.0')) {
			$statusFilter = 'warehouseopen, warehouseinternal';
		} else {
			$statusFilter = '';
		}

		$includeTotal = false; // keep default false for mobilid full warehouse list store

		if (isset($params->limit)) {
			$limit = $params->limit;
			$start = $params->start;
		}

		if (isset($params->allow_paging)) { // for backwards compatibility (actual app already sends include_total but is not ready for order paging)
			if (isset($params->include_total)) {
				$includeTotal = $params->include_total;
			}
		} else {
			$limit = 0;
		}

		if (isset($params->filter)) {
			foreach ($params->filter as $key => $filter) {
				if ($filter->property == 'product_id') $fkProduct=$filter->value;
				if ($filter->property == 'batch_id') $fkBatch=$filter->value;
				if ($filter->property == 'batch') $batch=$this->db->escape($filter->value);
				if ($filter->property == 'sumstock') $sumStock=$filter->value;
				if ($filter->property == 'content') $contentValue = strtolower($this->db->escape($filter->value));
				if ($filter->property == 'statusfilter') $statusFilter = $this->db->escape($filter->value);
			}
		}

		if ($includeTotal) {
			$res = $this->_loadWarehouses($fkProduct, $fkBatch, $batch, $statusFilter, $contentValue, $sumStock);
			$total = $res;
		} else {
			$res = $this->_loadWarehouses($fkProduct, $fkBatch, $batch, $statusFilter, $contentValue, $sumStock, $limit, $start);
		}

		$this->_makeNumericLabelSortable();

		if ($start == 0) {
			// create allwarehouse record with total warehouse stock, only for first page
			$row = new stdClass;
			$row->id = self::ALLWAREHOUSE_ID;
			$row->label= $langs->trans(self::ALLWAREHOUSE_LABEL);
			$row->description= $langs->trans(self::ALLWAREHOUSE_DESCRIPTION);
			$row->is_virtual_stock = false;
			if (!empty($fkProduct) && !empty($conf->global->STOCK_SHOW_VIRTUAL_STOCK_IN_PRODUCTS_COMBO)) {
				$product = new Product($this->db);
				$product->fetch($fkProduct);
				$product->load_stock();
				$row->is_virtual_stock = true;
				$row->stock = price2num($product->stock_theorique, 5);
				array_push($data, $row);
			} elseif (!empty($fkProduct) || $sumStock) {
				$sql = "SELECT sum(ps.reel) as stock FROM ".MAIN_DB_PREFIX."product_stock as ps";
				if (!empty($fkProduct)) $sql.= " WHERE ps.fk_product = ".$fkProduct;
				$resql = $this->db->query($sql);
				if ($resql) {
					$obj = $this->db->fetch_object($resql);
					if ($obj) {
						$row->stock = price2num($obj->stock, 5);
					}
					array_push($data, $row);
					$this->db->free($resql);
				} else {
					$res = SQLERROR;
				}
			} else {
				array_push($data, $row);
			}
		}

		if ($res > 0) {
			$recordNbr = 1;
			foreach ($this->cache_warehouses as $warehouseId => $warehouse) {
				if ($limit == 0 || ($recordNbr > $start && $recordNbr <= $start + $limit)) {
					$row = new stdClass;
					$row->id = $warehouseId;
					$row->is_virtual_stock = false;
					isset($warehouse['label']) ? $row->label = $warehouse['label'] : $row->label='';
					isset($warehouse['stock']) ? $row->stock = $warehouse['stock'] : $row->stock=null;
					if (!empty($warehouse['barcode']) && !empty($warehouse['label']) && empty($warehouse['description']) && $warehouse['label'] != $warehouse['barcode']) {
						// temp solution until warehouse barcodes supported by client
						$row->description = $warehouse['barcode'];
					} else {
						isset($warehouse['description']) ? $row->description = $warehouse['description'] : $row->description=null;
					}
					isset($warehouse['status']) ? $row->status = $warehouse['status'] : $row->status=null;
					isset($warehouse['parent_id']) ? $row->parent_id = $warehouse['parent_id'] : $row->parent_id=null;
					isset($warehouse['barcode']) ? $row->barcode = $warehouse['barcode'] : $row->barcode=null;
					isset($warehouse['fk_barcode_type']) ? $row->barcode_type = $warehouse['fk_barcode_type'] : $row->barcode_type=null;
					array_push($data, $row);
				}
				$recordNbr++;
			}
		} elseif ($res < 0) {
			return ExtDirect::getDolError($res, $this->errors, $this->error);
		}

		if ($includeTotal) {
			$result->total = $total;
			$result->data = $data;
			return $result;
		} else {
			return $data;
		}
	}

	/**
	 *    Load available priceindexes from database into memory
	 *
	 *    @param    stdClass    $params     not used
	 *
	 *    @return     stdClass result data
	 */
	public function readPriceIndex(stdClass $params)
	{
		global $conf,$langs;

		if (!isset($this->db)) return CONNECTERROR;
		$results = array();
		$row = new stdClass;

		if (! empty($conf->global->PRODUIT_MULTIPRICES)) {
			for ($i=1; $i <= $conf->global->PRODUIT_MULTIPRICES_LIMIT; $i++) {
				$row->id = $i;
				$row->name = "Level ".$i;//lang
				array_push($results, clone $row);
			}
		} else {
			$row->id = 0;
			$row->name = "No multiprices";//lang
			array_push($results, clone $row);
		}
		return $results;
	}

	/**
	 *    Load available producttypes
	 *
	 *    @param    stdClass    $params     not used
	 *
	 *    @return     stdClass result data
	 */
	public function readProductType(stdClass $params)
	{
		global $conf,$langs;

		if (!isset($this->db)) return CONNECTERROR;
		$results = array();

		if (! empty($conf->product->enabled)) {
			$row = new stdClass;
			$row->id = 0;
			$row->label = $langs->trans("Product") ? $langs->transnoentities("Product") : "Product";
			array_push($results, $row);
		}
		if (! empty($conf->service->enabled)) {
			$row = new stdClass;
			$row->id = 1;
			$row->label = $langs->trans("Service") ? $langs->transnoentities("Service") : "Service";
			array_push($results, $row);
		}

		return $results;
	}

	/**
	 *    Load available lot types
	 *
	 *    @param    stdClass    $params     not used
	 *
	 *    @return     stdClass result data
	 */
	public function readProductLotType(stdClass $params)
	{
		global $conf, $langs;

		if (!isset($this->db)) return CONNECTERROR;
		$results = array();

		if (! empty($conf->productbatch->enabled)) {
			$langs->load("productbatch");
			$statutarray = array(0 => $langs->trans("ProductStatusNotOnBatch"), 1 => $langs->trans("ProductStatusOnBatch"));
			if (ExtDirect::checkDolVersion(0, '14.0', '')) {
				$statutarray[2] = $langs->trans("ProductStatusOnSerial");
			}
			foreach ($statutarray as $key => $value) {
				$row = new stdClass;
				$row->id = $key;
				$row->label = $value;
				array_push($results, $row);
			}
		}

		return $results;
	}

	/**
	 *    Load available price_base_types
	 *
	 *    @param    stdClass    $params     not used
	 *
	 *    @return     stdClass result data
	 */
	public function readPriceBaseType(stdClass $params)
	{
		global $langs;

		if (!isset($this->db)) return CONNECTERROR;
		$results = array();
		$row = new stdClass;
		$row->code = 'HT';
		$row->label = $langs->transnoentities("HT") ? $langs->trans("HT") : "Net of tax";
		array_push($results, $row);
		$row = new stdClass;
		$row->code = 'TTC';
		$row->label = $langs->transnoentities("TTC") ? $langs->trans("TTC") : "Inc. tax";
		array_push($results, $row);
		// workaround for pick last item in list issue, add dummy last item
		$row = new stdClass;
		$row->code = 'TTC';
		$row->label = '';
		array_push($results, $row);

		return $results;
	}

	/**
	 *    Load available barcodetypes
	 *
	 *    @param    stdClass    $params     not used
	 *
	 *    @return     stdClass result data
	 */
	public function readBarcodeType(stdClass $params)
	{
		global $conf;

		if (!isset($this->db)) return CONNECTERROR;
		$results = array();
		$row = new stdClass;
		if (! empty($conf->barcode->enabled)) {
			$sql = "SELECT rowid, code, libelle as label, coder";
			$sql.= " FROM ".MAIN_DB_PREFIX."c_barcode_type";
			dol_syslog(get_class($this).'::readBarcodeType', LOG_DEBUG);
			$resql=$this->db->query($sql);

			if ($resql) {
				$num=$this->db->num_rows($resql);
				$row->id    = 0;
				$row->code  = 'NONE';
				$row->label = '';
				$row->coder = '0';
				$row->product_default = false;
				$row->company_default = false;
				array_push($results, clone $row);
				for ($i = 0;$i < $num; $i++) {
					$obj = $this->db->fetch_object($resql);
					$row->id    = $obj->rowid;
					$row->code  = $obj->code;
					$row->label = $obj->label;
					$row->coder = $obj->coder;
					$row->product_default = false;
					$row->company_default = false;
					if ($row->id == $conf->global->PRODUIT_DEFAULT_BARCODE_TYPE) {
						$row->product_default = true;
					} elseif ($row->id == $conf->global->GENBARCODE_BARCODETYPE_THIRDPARTY) {
						$row->company_default = true;
					}
					array_push($results, clone $row);
				}
			} else {
				$error="Error ".$this->db->lasterror();
				dol_syslog(get_class($this)."::readBarcodeType ".$error, LOG_ERR);
				return SQLERROR;
			}
		}
		return $results;
	}

	/**
	 * OVERRIDE from dolibarr loadWarehouses, can be removed when patched in Dolibarr
	 * TODO add units
	 *
	 * Load in cache array list of warehouses
	 * If fk_product is not 0, we do not use cache
	 *
	 * @param	int		$fk_product		Add quantity of stock in label for product with id fk_product. Nothing if 0.
	 * @param	int		$fk_batch		Add quantity of batch stock in label for product with batch id fk_batch. Nothing if 0.
	 * @param   int     $batch          Add quantity of batch stock in label for product with batch name batch. Nothing if '' batch name precedes batch_id.
	 * @param   string  $statusFilter   warehouse status filter, following comma separated filter options can be used
	 *									'warehouseopen' = select products from open warehouses,
	 *									'warehouseclosed' = select products from closed warehouses,
	 *									'warehouseinternal' = select products from warehouses for internal correct/transfer only
	 * @param   string  $contentValue   content search string
	 * @param	boolean	$sumStock		sum total stock of a warehouse, default true
	 * @param   int     $limit          paging limit
	 * @param   int     $start          paging start
	 * @return  int  		    		Nb of loaded lines, 0 if already loaded, <0 if KO
	 */
	private function _loadWarehouses($fk_product = 0, $fk_batch = 0, $batch = '', $statusFilter = '', $contentValue = '', $sumStock = true, $limit = 0, $start = 0)
	{
		dol_syslog(get_class($this).'::loadWarehouses fk_product='.$fk_product.'fk_batch='.$fk_batch.'batch='.$batch.'statusFilter='.$statusFilter.'contentValue='.$contentValue.'sumStock='.$sumStock.'limit='.$limit.'start='.$start, LOG_DEBUG);

		$warehouseStatus = array();

		$barcodeTypeData = $this->readBarcodeType(new stdClass);
		foreach ($barcodeTypeData as $barcodeType) {
			$barcodeTypes[$barcodeType->code] = $barcodeType->id;
		}

		if (preg_match('/warehouseclosed/', $statusFilter)) {
			$warehouseStatus[] = Entrepot::STATUS_CLOSED;
		}
		if (preg_match('/warehouseopen/', $statusFilter)) {
			$warehouseStatus[] = Entrepot::STATUS_OPEN_ALL;
		}
		if (preg_match('/warehouseinternal/', $statusFilter)) {
			$warehouseStatus[] = Entrepot::STATUS_OPEN_INTERNAL;
		}
		if (ExtDirect::checkDolVersion(0, '16.0', '')) {
			$sql = "SELECT e.rowid, e.ref as label, e.description, e.statut, e.fk_parent, e.barcode, e.fk_barcode_type";
		} elseif (ExtDirect::checkDolVersion(0, '7.0', '')) {
			$sql = "SELECT e.rowid, e.ref as label, e.description, e.statut, e.fk_parent";
		} else {
			$sql = "SELECT e.rowid, e.label, e.description, e.statut";
		}
		if (!empty($fk_product)) {
			if (!empty($fk_batch) || !empty($batch)) {
				$sql.= ", pb.qty as stock";
			} else {
				$sql.= ", ps.reel as stock";
			}
		} elseif ($sumStock) {
			$sql.= ", sum(ps.reel) as stock";
		}
		$sql.= " FROM ".MAIN_DB_PREFIX."entrepot as e";
		if ($sumStock || $fk_product > 0) $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product_stock as ps on ps.fk_entrepot = e.rowid";
		if (!empty($fk_product)) {
			$sql.= " AND ps.fk_product = '".$fk_product."'";
			if (!empty($batch)) {
				$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product_batch as pb on pb.fk_product_stock = ps.rowid";
				$sql.= " AND pb.batch = '".$batch."'";
			} elseif (!empty($fk_batch)) {
				$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product_batch as pb on pb.fk_product_stock = ps.rowid";
				$sql.= " AND pb.rowid = ".$fk_batch;
			}
		}

		$sql.= " WHERE e.entity IN (".getEntity('stock', 1).")";
		if (count($warehouseStatus)) {
			$sql.= " AND e.statut IN (".implode(',', $warehouseStatus).")";
		} else {
			$sql.= " AND e.statut > 0";
		}
		if (!empty($contentValue)) {
			if (ExtDirect::checkDolVersion(0, '7.0', '')) {
				$fields = array('e.ref', 'e.description');
			} else {
				$fields = array('e.label', 'e.description');
			}
			$sql .= natural_search($fields, $contentValue);
		}
		if ($sumStock && empty($fk_product)) {
			if (ExtDirect::checkDolVersion(0, '7.0', '')) {
				$sql.= " GROUP BY e.rowid, e.ref, e.description, e.statut, e.fk_parent";
			} else {
				$sql.= " GROUP BY e.rowid, e.label, e.description, e.statut";
			}
		}
		if (ExtDirect::checkDolVersion(0, '7.0', '')) {
			$sql.= " ORDER BY e.ref";
		} else {
			$sql.= " ORDER BY e.label";
		}
		if (!empty($limit)) {
			$sql .= $this->db->plimit($limit, $start);
		}
		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			while ($i < $num) {
				$obj = $this->db->fetch_object($resql);
				if ($sumStock) $obj->stock = price2num($obj->stock, 5);
				$this->cache_warehouses[$obj->rowid]['id'] = $obj->rowid;
				$this->cache_warehouses[$obj->rowid]['label'] = $obj->label;
				$this->cache_warehouses[$obj->rowid]['description'] = $obj->description;
				if (empty($obj->barcode)) {
					$this->cache_warehouses[$obj->rowid]['barcode'] = $obj->label;
				} else {
					$this->cache_warehouses[$obj->rowid]['barcode'] = $obj->barcode;
				}
				if (empty($obj->fk_barcode_type)) {
					$this->cache_warehouses[$obj->rowid]['fk_barcode_type'] = $barcodeTypes['C128'];
				} else {
					$this->cache_warehouses[$obj->rowid]['fk_barcode_type'] = $obj->fk_barcode_type;
				}
				$this->cache_warehouses[$obj->rowid]['stock'] = $obj->stock;
				$this->cache_warehouses[$obj->rowid]['status'] = $obj->statut;
				$this->cache_warehouses[$obj->rowid]['parent_id'] = $obj->fk_parent;
				$i++;
			}
			return $num;
		} else {
			dol_print_error($this->db);
			return -1;
		}
	}

	/**
	 * _makeNumericLabelSortable
	 *
	 * @return void
	 */
	private function _makeNumericLabelSortable()
	{
		$numericLabel = array();
		$alphaLabel = array();
		$alphaLabelEnd = array();
		$orderLabel = array();
		$maxLabel = 0;
		$maxLabelLen = 0;

		foreach ($this->cache_warehouses as $warehouse) {
			if (preg_match('/(^[0-9]*)([a-z_A-Z-0-9\s\.]*)/', $warehouse['label'], $matches)) {
				if (empty($matches[1])) {
					if (preg_match('/(^[a-z_A-Z\s\.]*)([0-9]*)(.*)/', $warehouse['label'], $matches)) {
						if (!empty($matches[2])) {
							if (strlen($matches[2]) > $maxLabelLen) $maxLabelLen = strlen($matches[2]);
							$numericLabel[$warehouse['id']] = (int) $matches[2];
							$alphaLabel[$warehouse['id']] = $matches[1];
							$alphaLabelEnd[$warehouse['id']] = $matches[3];
							$orderLabel[$warehouse['id']] = 'alfa-num';
						}
					}
				} else {
					if (strlen($matches[1]) > $maxLabelLen) $maxLabelLen = strlen($matches[1]);
					$numericLabel[$warehouse['id']] = (int) $matches[1];
					$alphaLabel[$warehouse['id']] = $matches[2];
					$orderLabel[$warehouse['id']] = 'num-alfa';
				}
				if (isset($numericLabel[$warehouse['id']]) && $numericLabel[$warehouse['id']] > $maxLabel) $maxLabel = $numericLabel[$warehouse['id']];
			}
		}
		if (strlen((string) $maxLabel) > $maxLabelLen) {
			$digits = '%0'.strlen((string) $maxLabel).'d';
		} else {
			$digits = '%0'.$maxLabelLen.'d';
		}
		foreach ($this->cache_warehouses as &$warehouse) {
			$numericPart = (isset($numericLabel[$warehouse['id']]) && $numericLabel[$warehouse['id']] > 0) ? sprintf($digits, $numericLabel[$warehouse['id']]) : '';
			if (isset($orderLabel[$warehouse['id']]) && $orderLabel[$warehouse['id']] == 'num-alfa') {
				$warehouse['label'] =  $numericPart . $alphaLabel[$warehouse['id']];
			} elseif (isset($orderLabel[$warehouse['id']]) && $orderLabel[$warehouse['id']] == 'alfa-num') {
				$warehouse['label'] = $alphaLabel[$warehouse['id']] . $numericPart . $alphaLabelEnd[$warehouse['id']];
			}
		}
	}

	/**
	 * Public method to read a list of supplier reputations
	 *
	 * @return stdClass result data or error number
	 */
	public function readSupplierReputations()
	{
		if (!isset($this->db)) {
			return CONNECTERROR;
		}
		$results = array();

		if (ExtDirect::checkDolVersion(0, '5.0', '')) {
			dol_include_once('/fourn/class/fournisseur.product.class.php');

			$supplierProduct = new ProductFournisseur($this->db);
			if (! is_array($result = $supplierProduct->reputations)) {
				return ExtDirect::getDolError($result, $this->errors, $this->error);
			}
			// add empty type
			$id = 0;
			foreach ($result as $code => $label) {
				$row = new stdClass;
				$row->id = $id++;
				$row->code = $code;
				$row->label = html_entity_decode($label);
				$results[]= $row;
			}
		}

		return $results;
	}

	/**
	 *    Load the unit labels
	 *
	 *    @return     stdClass result data or -1
	 */
	public function readProductUnits()
	{
		global $langs;

		if (!isset($this->db)) return CONNECTERROR;
		if (!isset($this->_user->rights->produit->lire)) return PERMISSIONERROR;

		$results = array();
		if (ExtDirect::checkDolVersion(0, '10.0', '')) {
			$sql = "SELECT rowid, label, code, short_label, scale, unit_type";
		} else {
			$sql = "SELECT rowid, label, code, short_label";
		}
		$sql.= " FROM ".MAIN_DB_PREFIX."c_units";
		$sql.= " WHERE active > 0";
		$sql.= " ORDER BY rowid";
		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			while ($i < $num) {
				$obj = $this->db->fetch_object($resql);
				$row = new stdClass;
				$row->id = $obj->rowid;
				$row->label = ($langs->transnoentities('unit'.$obj->code)!=$obj->label?$langs->transnoentities('unit'.$obj->code):$obj->label);
				$row->short_label = ($langs->transnoentities($obj->short_label)!=$obj->short_label?$langs->transnoentities($obj->short_label):$obj->short_label);
				if (ExtDirect::checkDolVersion(0, '10.0', '')) {
					$row->scale = $obj->scale;
					$row->unit_type = $obj->unit_type;
				}
				array_push($results, $row);
				$i++;
			}
			return $results;
		} else {
			$error="Error ".$this->db->lasterror();
			dol_syslog(get_class($this)."::readProductUnits ".$error, LOG_ERR);
			return SQLERROR;
		}
	}

	/**
	 *    get id of first warehouse in database to have some non configured default value.
	 *
	 *    @return     int warehouse id or -1 if error
	 */
	public function getFirstWarehouseId()
	{
		$warehouse = new Entrepot($this->db);
		$sql = "SELECT rowid";
		$sql.= " FROM ".MAIN_DB_PREFIX.$warehouse->table_element;
		$sql.= " ORDER BY rowid";
		$sql.= $this->db->plimit(1, 0);
		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			return $obj->rowid;
		} else {
			$this->error="Error ".$this->db->lasterror();
			return -1;
		}
	}
}
