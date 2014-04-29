<?php
/* Copyright (C) 2007-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2012-2013 Francis Appels       <francis.appels@z-application.com>
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
 */

/**
 *  \defgroup   Module Dolibarr ExtDirect
 *  \brief      Module description and activation of DirectConnect module, 
 *              a module wich enable external connections to applications using Ext.direct proxy 
 *  \file       htdocs/extdirect/core/modules/modExtDirect.class.php
 */
include_once(DOL_DOCUMENT_ROOT ."/core/modules/DolibarrModules.class.php");


/**
 *  Description and activation class for module ExtDirect
 */
class modExtDirect extends DolibarrModules
{
    /**
     *   Constructor. Define names, constants, directories, boxes, permissions
     *
     *   @param      DoliDB     $db      Database handler
     */
    function modExtDirect($db)
    {
        global $langs,$conf;

        $this->db = $db;

        // Id for module (must be unique).
        // Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
        $this->numero = 605002;
        // Key text used to identify module (for permissions, menus, etc...)
        $this->rights_class = 'extdirect';

        // Family can be 'crm','financial','hr','projects','products','ecm','technic','other'
        // It is used to group modules in module setup page
        $this->family = "technic";
        // Module label (no space allowed), used if translation string 'ModuleXXXName' 
        // not found (where XXX is value of numeric property 'numero' of module)
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        // Module description, used if translation string 'ModuleXXXDesc' 
        // not found (where XXX is value of numeric property 'numero' of module)
        $this->description = "Connect to external applications which use Sencha Ext.direct rpc communication technology";
        // Possible values for version are: 'development', 'experimental', 'dolibarr' or version
        $this->version = '1.0.11';
        // Key used in llx_const table to save module status enabled/disabled 
        // (where MYMODULE is value of property name of module in uppercase)
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        // Where to store the module in setup page (0=common,1=interface,2=others,3=very specific)
        $this->special = 1;
        // Name of image file used for this module.
        // If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
        // If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
        $this->picto='technic';

        // Defined all module parts (triggers, login, substitutions, menus, css, etc...)
        // for default path (eg: /mymodule/core/xxxxx) (0=disable, 1=enable)
        // for specific path of parts (eg: /mymodule/core/modules/barcode)
        // for specific css file (eg: /mymodule/css/mymodule.css.php)
        $this->module_parts = array(
            'triggers' => 1,                         // Set this to 1 if module has its own trigger directory
            'login' => 0,                            // Set this to 1 if module has its own login method directory
            'substitutions' => 0,                    // Set this to 1 if module has its own substitution function file
            'menus' => 0,                            // Set this to 1 if module has its own menus handler directory
            'barcode' => 0,                          // Set this to 1 if module has its own barcode directory
            'models' => 0//,                         // Set this to 1 if module has its own models directory
        //  'css' => '/mymodule/css/mymodule.css.php',// Set this to relative path of css if module has its own css file
        //  'hooks' => array('hookcontext1','hookcontext2')// Set here all hooks context managed by module
        //  'workflow' => array('order' => array('WORKFLOW_ORDER_AUTOCREATE_INVOICE')) // Set here all workflow context managed by module
        );
        //$this->module_parts = array();

        // Data directories to create when module is enabled.
        // Example: this->dirs = array("/mymodule/temp");
        $this->dirs = array();

        // Config pages. Put here list of php page, stored into mymodule/admin directory, to use to setup module.
        $this->config_page_url = array("extdirect.php@extdirect");

        // Dependencies
        $this->depends = array();       // List of modules id that must be enabled if this module is enabled
        $this->requiredby = array();    // List of modules id to disable if this one is disabled
        $this->phpmin = array(5,0);                 // Minimum version of PHP required by module
        $this->need_dolibarr_version = array(3,2);  // Minimum version of Dolibarr required by module
        $this->langfiles = array("extdirect@extdirect");

        // Constants
        // List of particular constants to add when module is enabled
        // (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
        $this->const=array(0=>array('DIRECTCONNECT_AUTO_ASIGN','yesno',0,'Automatic user asignment to application id',1),
                                     1=>array('DIRECTCONNECT_AUTO_USER','chaine','','Automatic asigned user id',1)
        );

        // Array to add new pages in new tabs
        // Example: $this->tabs = array('objecttype:+tabname1:Title1:langfile@mymodule:$user->rights->mymodule->read:/mymodule/mynewtab1.php?id=__ID__',  // To add a new tab identified by code tabname1
        //                              'objecttype:+tabname2:Title2:langfile@mymodule:$user->rights->othermodule->read:/mymodule/mynewtab2.php?id=__ID__',  // To add another new tab identified by code tabname2
        //                              'objecttype:-tabname');                                                     // To remove an existing tab identified by code tabname
        // where objecttype can be
        // 'thirdparty'       to add a tab in third party view
        // 'intervention'     to add a tab in intervention view
        // 'order_supplier'   to add a tab in supplier order view
        // 'invoice_supplier' to add a tab in supplier invoice view
        // 'invoice'          to add a tab in customer invoice view
        // 'order'            to add a tab in customer order view
        // 'product'          to add a tab in product view
        // 'stock'            to add a tab in stock view
        // 'propal'           to add a tab in propal view
        // 'member'           to add a tab in fundation member view
        // 'contract'         to add a tab in contract view
        // 'user'             to add a tab in user view
        // 'group'            to add a tab in group view
        // 'contact'          to add a tab in contact view
        // 'categories_x'     to add a tab in category view (replace 'x' by type of category (0=product, 1=supplier, 2=customer, 3=member)
        $this->tabs = array();

        // Dictionnaries
        if (! isset($conf->mymodule->enabled)) $conf->mymodule->enabled=0;
        $this->dictionnaries=array();
        /* Example:
        if (! isset($conf->mymodule->enabled)) $conf->mymodule->enabled=0;  // This is to avoid warnings
        $this->dictionnaries=array(
            'langs'=>'mymodule@mymodule',
            'tabname'=>array(MAIN_DB_PREFIX."table1",MAIN_DB_PREFIX."table2",MAIN_DB_PREFIX."table3"),      // List of tables we want to see into dictonnary editor
            'tablib'=>array("Table1","Table2","Table3"),                                                    // Label of tables
            'tabsql'=>array('SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table1 as f','SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table2 as f','SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table3 as f'),   // Request to select fields
            'tabsqlsort'=>array("label ASC","label ASC","label ASC"),                                                                                   // Sort order
            'tabfield'=>array("code,label","code,label","code,label"),                                                                                  // List of fields (result of select to show dictionnary)
            'tabfieldvalue'=>array("code,label","code,label","code,label"),                                                                             // List of fields (list of fields to edit a record)
            'tabfieldinsert'=>array("code,label","code,label","code,label"),                                                                            // List of fields (list of fields for insert)
            'tabrowid'=>array("rowid","rowid","rowid"),                                                                                                 // Name of columns with primary key (try to always name it 'rowid')
            'tabcond'=>array($conf->mymodule->enabled,$conf->mymodule->enabled,$conf->mymodule->enabled)                                                // Condition to show each dictionnary
        );
        */

        // Boxes
        // Add here list of php file(s) stored in core/boxes that contains class to show a box.
        $this->boxes = array();         // List of boxes
        $r=0;
        // Example:
        /*
        $this->boxes[$r][1] = "myboxa.php";
        $r++;
        $this->boxes[$r][1] = "myboxb.php";
        $r++;
        */

        // Permissions
        $this->rights = array();        // Permission array used by this module
        $r=0;

        // Add here list of permission defined by an id, a label, a boolean and two constant strings.
        // Example:
        // $this->rights[$r][0] = 2000;                 // Permission id (must not be already used)
        // $this->rights[$r][1] = 'Permision label';    // Permission label
        // $this->rights[$r][3] = 1;                    // Permission by default for new user (0/1)
        // $this->rights[$r][4] = 'level1';             // In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
        // $this->rights[$r][5] = 'level2';             // In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
        // $r++;
    }

    /**
     *      Function called when module is enabled.
     *      The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
     *      It also creates data directories
     *
     *      @param      string  $options    Options when enabling module ('', 'noboxes')
     *      @return     int                 1 if OK, 0 if KO
     */
    function init($options='')
    {
        $sql = array();

        $result=$this->load_tables();

        return $this->_init($sql, $options);
    }

    /**
     *      Function called when module is disabled.
     *      Remove from database constants, boxes and permissions from Dolibarr database.
     *      Data directories are not deleted
     *
     *      @param      string  $options    Options when enabling module ('', 'noboxes')
     *      @return     int                 1 if OK, 0 if KO
     */
    function remove($options='')
    {
        $sql = array();

        return $this->_remove($sql, $options);
    }


    /**
     *      Create tables, keys and data required by module
     *      Files llx_table1.sql, llx_table1.key.sql llx_data.sql with create table, create keys
     *      and create data commands must be stored in directory /mymodule/sql/
     *      This function is called by this->init
     *
     *      @return     int     <=0 if KO, >0 if OK
     */
    function load_tables()
    {
        return $this->_load_tables('/extdirect/sql/');
    }
}