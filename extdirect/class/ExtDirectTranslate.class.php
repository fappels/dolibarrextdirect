<?PHP

/*
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
 *  \file       htdocs/extdirect/class/ExtDirectTranslate.class.php
 *  \brief      Sencha Ext.Direct access .lang files remoting class
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/translate.class.php';

/**
 * ExtDirectTranslate class
 *
 * Class to load .lang files into Extjs or sencha touch data models
 * using Ext.direct connector
 */
class ExtDirectTranslate
{
	private $_translate;
	private $_user;

	/**
	 * Constructor
	 *
	 * @param string $login user name
	 *
	 * @return number
	 */
	public function __construct($login)
	{
		global $user;

		if (!empty($login)) {
			if ($user->fetch('', $login, '', 1)>0) {
				$this->_user = $user;
				$this->_translate = true;
			}
		}
	}

	/**
	 *    Load language file
	 *
	 *    @param stdClass $param optional parameter (filter,...)
	 *
	 *    @return stdClass result data or <0 if KO, 0 if already loaded, >0 if OK
	 */
	public function load(stdClass $param)
	{
		global $conf,$langs;

		if (!isset($this->_translate)) return CONNECTERROR;

		$results = array();

		$domain = '';
		$dir = '';

		if (isset($param->filter)) {
			foreach ($param->filter as $key => $filter) {
				if ($filter->property == 'domain') {
					$domain=$filter->value;
				} elseif ($filter->property == 'dir') {
					$dir=$filter->value;
				}
			}
		}

		if (($dir != '') && ($domain != '')) {
			if (! is_dir($conf->file->dol_document_root['main'].'/'.$dir)) {
				$dir = 'custom/'.$dir;
			}
			$this->_translate = new Translate($conf->file->dol_document_root['main'].'/'.$dir, $conf);
			if (isset($this->_user->conf->MAIN_LANG_DEFAULT)
				&& ($this->_user->conf->MAIN_LANG_DEFAULT != 'auto')
			) {
				$this->_translate->setDefaultLang($this->_user->conf->MAIN_LANG_DEFAULT);
			} else {
				$this->_translate->setDefaultLang($langs->getDefaultLang());
			}
		} else {
			return PARAMETERERROR;
		}

		if (($result = $this->_translate->load($domain)) < 0) {
			$error="Error loading language file, error nr: " .$result;
			dol_syslog(get_class($this)."::load ".$error, LOG_ERR);
			return -1;
		} else {
			foreach ($this->_translate->tab_translate as $key => $value) {
				$row = new stdClass;
				$row->name=$key;
				if ($value != null) {
					$row->value=$value;
				} else {
					$row->value="";
				}
				$results[] = $row;
			}
			return $results;
		}
	}
}
