<?PHP

/**
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
 *  \file       htdocs/extdirect/class/ExtDirectProduct.class.php
 *  \brief      Sencha Ext.Direct product helpers remoting class
 */
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';

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
                $this->db = $db;
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
        
        array_push($results, $row);
        
        if (!$this->error) {
            foreach ($this->cache_warehouses as $warehouseId => $warehouse) {
                unset($row);
                $row->id = $warehouseId;
                isset($warehouse['label'])?$row->label= $warehouse['label']:$row->label='';
                array_push($results, $row);
            }
        } else {
            die ($this->error);
        }
        
        return $results;
    }
    
    /**
     *    Load available priceindexes from database into memory
     *
     *    @param    stdClass    $params     filter with elements:
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
                $row=null;
                $row->id = $i;
                $row->name = "Level ".$i;//lang
                array_push($results, $row);
            }
        } else {
            $row->id = 0;
            $row->name = "No multiprices";//lang
            array_push($results, $row);
        }
        return $results;
    }    
}