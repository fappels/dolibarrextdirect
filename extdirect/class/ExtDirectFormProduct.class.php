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
                if (ExtDirect::checkDolVersion() >= 3.3) {
                    parent::__construct($db);
                } else {
                    $this->db = $db;
                }
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
        $row = new stdClass;
        $fkProduct = 0;
        if (isset($params->filter)) {
            foreach ($params->filter as $key => $filter) {
                if ($filter->property == 'productid') $fkProduct=$filter->value;
            }
        }
        if (($result = $this->loadWarehouses($fkProduct)) < 0) return $result;
        
        $row->id = self::ALLWAREHOUSE_ID;
        $row->label= $langs->trans(self::ALLWAREHOUSE_LABEL);
        
        array_push($results, clone $row);
        
        if (!$this->error) {
            foreach ($this->cache_warehouses as $warehouseId => $warehouse) {
                $row->id = $warehouseId;
                isset($warehouse['label'])?$row->label= $warehouse['label']:$row->label='';
                array_push($results, clone $row);
            }
        } else {
            die ($this->error);
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
                array_push($results, clone $row);
                for ($i = 0;$i < $num; $i++) {
                    $obj = $this->db->fetch_object($resql);
                    if ($obj->coder != '0') {
                        $row->id    = $obj->rowid;
                        $row->code  = $obj->code;
                        $row->label = $obj->label;
                        $row->coder = $obj->coder;
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
}