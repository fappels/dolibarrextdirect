<?php
/*
 * Forked from sencha examples to connect to dolibarr extdirect classes
 */
if (!defined('NOREQUIRESOC'))       define('NOREQUIRESOC', '1');
if (!defined('NOTOKENRENEWAL'))     define('NOTOKENRENEWAL', '1');
if (!defined('NOCSRFCHECK'))        define('NOCSRFCHECK', '1');
if (!defined('NOREQUIREMENU'))      define('NOREQUIREMENU', '1');    // If there is no menu to show
if (!defined('NOREQUIREHTML'))      define('NOREQUIREHTML', '1');    // If we don't need to load the html.form.class.php
if (!defined('NOREQUIREAJAX'))      define('NOREQUIREAJAX', '1');
if (!defined('NOLOGIN'))            define('NOLOGIN', '1');          // If this page is public (can be called outside logged session)
if (!defined('NOREQUIRETRAN'))      define('NOREQUIRETRAN', '1');    // no load of main translations, because we do not know user lang yet

// Change this following line to use the correct relative path (../, ../../, etc)
$res=0;
if (! $res && file_exists("../main.inc.php")) $res=@include "../main.inc.php";
if (! $res && file_exists("../../main.inc.php")) $res=@include "../../main.inc.php" ;
if (! $res && file_exists("../../../main.inc.php")) $res=@include "../../../main.inc.php";
if (! $res) die("Include of main fails");
dol_include_once("/core/class/translate.class.php");
dol_include_once("/extdirect/class/extdirect.class.php");
require 'config.php';
$debugData = '[]';
$langs = new Translate('', $conf); // Needed because 'NOREQUIRETRAN' defined

// a non CSRF cookie should be created but cookie needs to be secured
if (version_compare(phpversion(), '7.3', '>=')) {
	browserHasNoSamesite() ? $site_cookie_samesite = ini_get('session.cookie_samesite') : $site_cookie_samesite = ini_set('session.cookie_samesite', 'None');
	$site_cookie_secure = ini_get('session.cookie_secure'); // site cookie info can be removed for production
	session_abort();
	browserHasNoSamesite() ? $sessionParam = array('samesite' => null) : $sessionParam = array('samesite' => 'None');
	requestIsHTTPS() ? $sessionParam['secure'] = 1 : $sessionParam['secure'] = 0;
	session_set_cookie_params($sessionParam);
	session_start();
} else {
	$site_cookie_samesite = 'NA';
	$site_cookie_secure = ini_get('session.cookie_secure');
	session_abort();
	session_set_cookie_params(0, (browserHasNoSamesite() ? '/' : '/; samesite=None'), null, (requestIsHTTPS() ? true : false), true);
	session_start();
}

/** Action class
 * class to execute extdirect functions
 */
class BogusAction
{
	public $action;
	public $method;
	public $data;
	public $tid;
}

$isForm = false;
$isUpload = false;

$rawData = file_get_contents("php://input");
if (!empty($rawData)) {
	header('Content-Type: text/javascript');
	$data = json_decode($rawData);
}
if (empty($rawData) || empty($data)) {
	if (isset($_POST['extAction'])) {
		$isForm = true;
		$isUpload = $_POST['extUpload'] == 'true';
		$data = new BogusAction();
		$data->action = $_POST['extAction'];
		$data->method = $_POST['extMethod'];
		$data->tid = isset($_POST['extTID']) ? $_POST['extTID'] : null; // not set for upload
		$data->data = array($_POST, $_FILES);
	} elseif (isset($debugData)) {
		$data = json_decode($debugData);
	} else {
		echo json_encode('Invalid request.');
	}
}

/**
 * doRpc execute remote call
 *
 * @param Object $cdata remote call and it's data
 *
 * @return Array result array
 */
function doRpc($cdata)
{
	global $API;

	try {
		if (!isset($API[$cdata->action])) {
			throw new Exception('Call to undefined action: ' . $cdata->action);
		}

		$action = $cdata->action;
		$a = $API[$action];

		doAroundCalls($a['before'], $cdata);

		$method = $cdata->method;
		$mdef = $a['methods'][$method];
		if (!$mdef) {
			throw new Exception("Call to undefined method: $method on action $action");
		}
		doAroundCalls($mdef['before'], $cdata);

		$r = array(
			'type'=>'rpc',
			'tid'=>$cdata->tid,
			'action'=>$action,
			'method'=>$method
		);

		$actionAvailable = true;
		if (ExtDirect::checkDolVersion(0, '', '10') && $action == 'ExtDirectMo') $actionAvailable = false; // skip non existing classes
		if ($actionAvailable) {
			error_reporting(0); // comment for debugging or change 0 to E_ALL
			dol_include_once("/extdirect/class/$action.class.php");
			$o = new $action(isset($_SESSION['dol_login']) ? $_SESSION['dol_login'] : null);
			if (isset($mdef['len'])) {
				$params = isset($cdata->data) && is_array($cdata->data) ? $cdata->data : array();
			} else {
				$params = array($cdata->data);
			}
			if (!object_analyse_sql_and_script($params, 0)) {
				$result = VULNERABILITYERROR;
			} else {
				if (ExtDirect::checkDolVersion() < 0) {
					$result = COMPATIBILITYERROR;
				} else {
					dol_syslog(get_class($o) . '::' . $method, LOG_DEBUG);
					$result = call_user_func_array(array($o, $method), $params);
				}
			}
		} else {
			$result = NOTENABLEDERROR;
		}
		$error = new stdClass;
		if (is_int($result) && ($result < 0)) {
			if ($result > CONNECTERROR) {
				$error->message = "Error $result from dolibarr: $method on action $action";
			} else {
				switch ($result) {
					case CONNECTERROR:
						$error->message = "Connect Error: $method on action $action";
						break;
					case PERMISSIONERROR:
						$error->message = "Permission Error: $method on action $action";
						break;
					case SQLERROR:
						$error->message = "SQL Error: $method on action $action";
						break;
					case UPTADEERROR:
						$error->message = "Update Error: $method on action $action";
						break;
					case PARAMETERERROR:
						$error->message = "Parameter Error: $method on action $action";
						break;
					case VULNERABILITYERROR:
						$error->message = "Vulnerability Error: $method on action $action";
						break;
					case COMPATIBILITYERROR:
						$error->message = "Compatibility Error: $method on action $action";
						break;
					case DUPLICATEERROR:
						$error->message = "Duplicate Error: $method on action $action";
						break;
					case NOTENABLEDERROR:
						$error->message = "Not enabled Error: $method on action $action";
						break;
					default:
						$error->message = "Error $result from dolibarr: $method on action $action";
						break;
				}
			}
			$r['result'] = $result;
			throw new Exception($error->message);
		} elseif (is_string($result)) {
			$error->message = "Dolibarr: $result";
			$r['result'] = $result;
			throw new Exception($error->message);
		} else {
			$r['result'] = $result;
		}

		doAroundCalls($mdef['after'], $cdata, $r);
		doAroundCalls($a['after'], $cdata, $r);
	} catch (Exception $e) {
		$r['type'] = 'exception';
		$r['message'] = $e->getMessage();
		$r['where'] = $e->getTraceAsString();
	}
	return $r;
}
/**
 * loop through methods
 *
 * @param pointer $fns method(s)
 * @param pointer $cdata parameters
 * @param pointer $returnData return parameter
 * @return void
 */
function doAroundCalls(&$fns, &$cdata, &$returnData = null)
{
	if (!$fns) {
		return;
	}
	if (is_array($fns)) {
		foreach ($fns as $f) {
			$f($cdata, $returnData);
		}
	} else {
		$fns($cdata, $returnData);
	}
}

/**
 * Security: Return true if OK, false otherwise.
 *
 * @param       unknown_type        $var       Object/Array to check
 * @param       int     $type       1=GET, 0=POST, 2=PHP_SELF
 * @return      boolean                 false if ther is an injection
 */
function object_analyse_sql_and_script(&$var, $type)
{
	if (is_array($var) || is_object($var)) {
		foreach ($var as $key => $value) {
			if (object_analyse_sql_and_script($value, $type)) {
				if (is_array($var)) {
					$var[$key] = $value;
				} else {
					$var->$key = $value;
				}
			} else {
				return false;
			}
		}
		return true;
	} else {
		if (function_exists('test_sql_and_script_inject')) {
			return (test_sql_and_script_inject($var, $type) <= 0);
		} else {
			return (testSqlAndScriptInject($var, $type) <= 0);
		}
	}
}

/**
 * Return if we are using a HTTPS connexion
 * Check HTTPS (no way to be modified by user but may be empty or wrong if user is using a proxy)
 * Take HTTP_X_FORWARDED_PROTO (defined when using proxy)
 * Then HTTP_X_FORWARDED_SSL
 *
 * @return	boolean		True if user is using HTTPS
 */
function requestIsHTTPS()
{
	$isSecure = false;
	if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
		$isSecure = true;
	} elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
		$isSecure = true;
	}
	return $isSecure;
}

/**
 * Return if an old browser not compatible with samesite cookies is connecting
 * For the moment only check for android chrome.
 *
 * @return	boolean		True if browser not compatible
 */
function browserHasNoSamesite()
{
	global $conf;

	$noSamesite = false;

	if (!empty($conf->browser->version)) {
		if ($conf->browser->name == 'chrome' && version_compare($conf->browser->version, '80.0.0.0', '<')) {
			$noSamesite = true;
		}
	}

	return $noSamesite;
}

$response = null;
if (is_array($data)) {
	$response = array();
	foreach ($data as $d) {
		$response[] = doRpc($d);
	}
} else {
	$response = doRpc($data);
}
if ($isForm && $isUpload) {
	if ($response['type'] == 'exception') {
		echo json_encode($response['message'], JSON_FORCE_OBJECT);
	} else {
		echo json_encode($response['result'], JSON_FORCE_OBJECT);
	}
} else {
	echo json_encode($response);
}
