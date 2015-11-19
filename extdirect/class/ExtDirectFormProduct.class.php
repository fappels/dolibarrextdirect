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
dol_include_once('/extdirect/class/extdirect.class.php');

/** ExtDirectFormProduct class
 * Class with product helpers methods to connect to Extjs or sencha touch using Ext.direct connector
 */
class ExtDirectFormProduct extends FormProduct
{
    private $_user;
    
    const ALLWAREHOUSE_ID = 0;
    const ALLWAREHOUSE_LABEL = 'All';
    const ALLWAREHOUSE_DESCRIPTION = 'AllLocations';
    
    /** Constructor
     *
     * @param string $login user name
     * @return number
     *
     */
    public function __construct($login) 
    {
        global $langs,$user,$db;
        
        if (!empty($login)) {
            if ($user->fetch('', $login)>0) {
                $this->_user = $user;  //commande.class uses global user
                if (isset($this->_user->conf->MAIN_LANG_DEFAULT) && ($this->_user->conf->MAIN_LANG_DEFAULT != 'auto')) {
                    $langs->setDefaultLang($this->_user->conf->MAIN_LANG_DEFAULT);
                }
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
     *      productid           load warehouses of one product
     *    
     *    @return     stdClass result data or -1
     */
    public function readWarehouses(stdClass $params)
    {
        global $conf,$langs;

        if (!isset($this->db)) return CONNECTERROR;
        
        $results = array();
        
        $fkProduct = 0;
        $fkBatch = 0;
        $batch = '';
        $sumStock = true;
        $limit = 0;
        $start = 0;
        $contentValue = '';
        
        if (isset($params->limit)) {
            $limit = $params->limit;
            $start = $params->start;
        }
                
        if (isset($params->filter)) {
            foreach ($params->filter as $key => $filter) {
                if ($filter->property == 'product_id') $fkProduct=$filter->value;
                if ($filter->property == 'batch_id') $fkBatch=$filter->value;
                if ($filter->property == 'batch') $batch=$this->db->escape($filter->value);
                if ($filter->property == 'sumstock') $sumStock=$filter->value;
                if ($filter->property == 'content') $contentValue = strtolower($this->db->escape($filter->value));  
            }
        }
        if (($result = $this->_loadWarehouses($fkProduct, $fkBatch, $batch, $contentValue, $sumStock, $limit, $start)) < 0) return $result;
        
        if ($start == 0) {
            // create allwarehouse record with total warehouse stock, only for first page
            $row = new stdClass;
            $row->id = self::ALLWAREHOUSE_ID;
            $row->label= $langs->trans(self::ALLWAREHOUSE_LABEL);
            $row->description= $langs->trans(self::ALLWAREHOUSE_DESCRIPTION);
            $sql = "SELECT sum(ps.reel) as stock FROM ".MAIN_DB_PREFIX."product_stock as ps";
            if (!empty($fkProduct)) $sql.= " WHERE ps.fk_product = ".$fkProduct;
            $resql = $this->db->query($sql);
            if ($resql)
            {
                $obj = $this->db->fetch_object($resql);
                if ($obj) {
                    $row->stock = $obj->stock;
                }
                $this->db->free($resql);
            }
            else
            {
                return SQLERROR;
            }
            array_push($results, $row);
        }

        if (!$this->error) {
            foreach ($this->cache_warehouses as $warehouseId => $warehouse) {
                $row = new stdClass;
                $row->id = $warehouseId;
                isset($warehouse['label']) ? $row->label = $warehouse['label'] : $row->label='';
                isset($warehouse['stock']) ? $row->stock = $warehouse['stock'] : $row->stock=null;
                isset($warehouse['description']) ? $row->description = $warehouse['description'] : $row->description=null;
                array_push($results, $row);
            }
        } else {
            ExtDirect::getDolError($result, $this->errors, $this->error);
        }
        
        return $results;
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
        $row = new stdClass;
        if (! empty($conf->product->enabled)) {
            $row->id = 0;
            $row->label = $langs->trans("Product") ? $langs->trans("Product") : "Product";//lang
            array_push($results, clone $row);
        }
        if (! empty($conf->service->enabled)) {
            $row->id = 1;
            $row->label = $langs->trans("Service") ? $langs->trans("Service") : "Service";//lang
            array_push($results, clone $row);
        }
        
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
                    if ($obj->coder != '0') {
                        $row->id    = $obj->rowid;
                        $row->code  = $obj->code;
                        $row->label = $obj->label;
                        $row->coder = $obj->coder;
                        $row->product_default = false;
                        $row->company_default = false;
                        if ($row->id == $conf->global->PRODUIT_DEFAULT_BARCODE_TYPE) {
                            $row->product_default = true;
                        } else if ($row->id == $conf->global->GENBARCODE_BARCODETYPE_THIRDPARTY) {
                            $row->company_default = true;
                        }
                        array_push($results, clone $row);
                    }
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
     * @param   string  $contentValue   content search string
     * @param	boolean	$sumStock		sum total stock of a warehouse, default true
     * @param   int     $limit          paging limit
     * @param   int     $start          paging start
     * @return  int  		    		Nb of loaded lines, 0 if already loaded, <0 if KO
     */
    private function _loadWarehouses($fk_product=0, $fk_batch=0, $batch = '', $contentValue = '', $sumStock = true, $limit = 0, $start = 0)
    {
        dol_syslog(get_class($this).'::loadWarehouses fk_product='.$fk_product.'fk_batch='.$fk_batch.'batch='.$batch.'contentValue='.$contentValue.'sumStock='.$sumStock.'limit='.$limit.'start='.$start, LOG_DEBUG);
        $sql = "SELECT e.rowid, e.label, e.description";
        if (!empty($fk_product)) 
        {
            if (!empty($fk_batch) || !empty($batch)) 
            {
                $sql.= ", pb.qty as stock";
            }
            else
            {
                $sql.= ", ps.reel as stock";
            }
        }
        else if ($sumStock)
        {
            $sql.= ", sum(ps.reel) as stock";
        }
        $sql.= " FROM ".MAIN_DB_PREFIX."entrepot as e";
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product_stock as ps on ps.fk_entrepot = e.rowid";
        if (!empty($fk_product))
        {
            $sql.= " AND ps.fk_product = '".$fk_product."'";
            if (!empty($batch)) 
            {
                $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product_batch as pb on pb.fk_product_stock = ps.rowid";
                $sql.= " AND pb.batch = '".$batch."'";
            } else if (!empty($fk_batch))
            {
                $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product_batch as pb on pb.fk_product_stock = ps.rowid";
                $sql.= " AND pb.rowid = ".$fk_batch;
            }
        }
        
        $sql.= " WHERE e.entity IN (".getEntity('stock', 1).")";
        $sql.= " AND e.statut = 1";
        if (!empty($contentValue)) {
            $sql.= " AND (LOWER(e.label) like '%".$contentValue."%' OR LOWER(e.description) like '%".$contentValue."%')";
        }
        
        if ($sumStock && empty($fk_product)) $sql.= " GROUP BY e.rowid, e.label, e.description";
        $sql.= " ORDER BY e.label";        
        if (!empty($limit)) {
            $sql .= $this->db->plimit($limit, $start);
        }
        
        $resql = $this->db->query($sql);
        if ($resql)
        {
            $num = $this->db->num_rows($resql);
            $i = 0;
            while ($i < $num)
            {
                $obj = $this->db->fetch_object($resql);
                if ($sumStock) $obj->stock = price2num($obj->stock,5);
                $this->cache_warehouses[$obj->rowid]['id'] = $obj->rowid;
                $this->cache_warehouses[$obj->rowid]['label'] = $obj->label;
                $this->cache_warehouses[$obj->rowid]['description'] = $obj->description;
                $this->cache_warehouses[$obj->rowid]['stock'] = $obj->stock;
                $i++;
            }
            return $num;
        }
        else
        {
            dol_print_error($this->db);
            return -1;
        }
    }
}