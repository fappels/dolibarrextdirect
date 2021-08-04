<?php
/* Copyright (C) 2005-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2011 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2012      Francis Appels       <francis.appels@z-application.com>
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
 *  \file       htdocs/extdirect/core/triggers/interface_70_modExtDirect_ExtDirectUserSynchro.class.php
 *  \ingroup    extdirect
 *  \brief      Fichier de demo de personalisation des actions du workflow
 *  \remarks    Son propre fichier d'actions peut etre cree par recopie de celui-ci:
 *              - Le nom du fichier doit etre: interface_99_modMymodule_Mytrigger.class.php
 *                                         ou: interface_99_all_Mytrigger.class.php
 *              - Le fichier doit rester stocke dans core/triggers
 *              - Le nom de la classe doit etre InterfaceMytrigger
 *              - Le nom de la propriete name doit etre Mytrigger
 */

/**
 *  Class of triggers for demo module
 */
class InterfaceExtDirectUserSynchro extends DolibarrTriggers
{
	/**
	 * @var DoliDB Database handler
	 */
	protected $db;

	/**
	 *   Constructor
	 *
	 *   @param     DoliDB      $db      Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "module";
		$this->description = "Triggers of this module synchronise user modifaction with the extdirect_user table.";
		$this->version = '1.0.2';            // 'development', 'experimental', 'dolibarr' or version
		$this->picto = 'technic';
	}

	/**
	 *   Return name of trigger file
	 *
	 *   @return     string      Name of trigger file
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 *   Return description of trigger file
	 *
	 *   @return     string      Description of trigger file
	 */
	public function getDesc()
	{
		return $this->description;
	}

	/**
	 *   Return version of trigger file
	 *
	 *   @return     string      Version of trigger file
	 */
	public function getVersion()
	{
		global $langs;
		$langs->load("admin");

		if ($this->version == 'development') return $langs->trans("Development");
		elseif ($this->version == 'experimental') return $langs->trans("Experimental");
		elseif ($this->version == 'dolibarr') return DOL_VERSION;
		elseif ($this->version) return $this->version;
		else return $langs->trans("Unknown");
	}

	/**
	 *      Function called when a Dolibarrr business event is done.
	 *      All functions "run_trigger" are triggered if file is inside directory htdocs/core/triggers
	 *
	 *      @param  string      $action     Event action code
	 *      @param  Object      $object     Object
	 *      @param  User        $user       Object user
	 *      @param  Translate   $langs      Object langs
	 *      @param  conf        $conf       Object conf
	 *      @return int                     <0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		// Put here code you want to execute when a Dolibarr business events occurs.
		// Data and type of action are stored into $object and $action

		if ($action == 'USER_DELETE') {
			dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
			dol_include_once("/extdirect/class/extdirect.class.php");

			$extDirect = new ExtDirect($this->db);
			$extDirect->fetchList("fk_user = '" . $object->id . "'");
			foreach ($extDirect->dataset as $extDirectUser) {
				$extDirect->id=$extDirectUser['rowid'];
				if ($extDirect->delete($user)<0) {
					dol_syslog("Trigger failed'".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				}
			}
		}
		return 0;
	}
}
