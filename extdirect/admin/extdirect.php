<?php
/* 
 * Copyright (C) 2012-2014  Francis Appels       <francis.appels@z-application.com>
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
 *  \file       htdocs/extdirect/admin/extdirect.php
 *  \brief      Administration Page/configuration for module dolibarrExtDirect
 */

require("../../main.inc.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php");
dol_include_once("/extdirect/class/extdirect.class.php");
dol_include_once("/extdirect/class/extdirectactivity.class.php");

$langs->load("admin");
$langs->load("extdirect@extdirect");

$error=0;

// Security check
if (! $user->admin) accessforbidden();
$authentication = new stdClass;
$activities = new stdClass;
$authentication->mode = 'authentication';
$authentication->title = $langs->trans('Authentication')?$langs->trans('Authentication'):'Authentication';
$activities->mode = 'activities';
$activities->title = $langs->trans('Activities')?$langs->trans('Activities'):'Activities';
$mode=GETPOST('mode', 'alpha')?GETPOST('mode', 'alpha'):$authentication->mode;

$action = GETPOST('action', 'alpha');
$value = GETPOST('value', 'alpha');
$refresh = GETPOST('refresh', 'alpha');

if ($mode == $activities->mode) {
    $userId = GETPOST('userid', 'int');
    if ($userId > 0) {
        $activityFilter = ' AND ea.fk_user = ' . $userId;
    } else {
        $activityFilter = '';
        $userId = -1;
    }
    $extDirect= new ExtDirectActivity($db);
    if ($extDirect->fetchList($activityFilter, 'rowid ASC') < 0) $error++;
    if ($extDirect->getDurations() < 0) $error++;
} else {
    $extDirect= new ExtDirect($db);
    if ($extDirect->fetchList('', 'date_last_connect ASC') < 0) $error++;
}

/*
 * Actions
 */
if (!$error) {
    if ($action == 'autoasign') {
        $autoAsign = GETPOST('auto_asign', 'alpha'); 
        $res = dolibarr_set_const($db, "DIRECTCONNECT_AUTO_ASIGN", $autoAsign, 'yesno', 0, '', $conf->entity);
    } else if ($action == 'autouser') {
        $userId = GETPOST('userid', 'alpha');    
        $res = dolibarr_set_const($db, "DIRECTCONNECT_AUTO_USER", $userId, 'chaine', 0, '', $conf->entity);
    } else if ($action == "save" && empty($refresh)) {
        $i=0;
        
        if (! empty($extDirect->dataset)) {
            $db->begin();
            foreach ($extDirect->dataset as $user_app) {
                $extDirect->id=$user_app['rowid'];  
                    
                $param='REMOVE_'.$user_app['app_id'].$i;
                //print "param=".$param." - ".$_POST[$param];
                if (GETPOST($param, 'alpha')) {
                    //delete
                    $res = $extDirect->delete($user);
                } else {
                    //update
                    $extDirect->fk_user = GETPOST('userid'.$i, 'alpha');
                    $extDirect->app_id = $user_app['app_id'];
                    $extDirect->ack_id = $user_app['ack_id'];
                    $extDirect->requestid = $user_app['requestid'];
                    $extDirect->app_name = $user_app['app_name'];
                    $extDirect->date_last_connect = $db->jdate($user_app['date_last_connect']);
                    $extDirect->datec = $db->jdate($user_app['datec']);
                    $extDirect->dev_platform = $user_app['dev_platform'];
                    $extDirect->dev_type = $user_app['dev_type'];
                    $res = $extDirect->update($user, 1);
                }
                $i++;
                if (! $res > 0) $error++;
            }
        }
        $extDirect->fetchList('', 'date_last_connect ASC');  
    } elseif ($action == 'clear' && empty($refresh)) {
        if (! empty($extDirect->dataset)) {
            $db->begin();
            foreach ($extDirect->dataset as $data) {
                $extDirect->id=$data['rowid'];
                //delete
                $res = $extDirect->delete($user);
                if (! $res > 0) $error++;
            }
        }
        if ($extDirect->fetchList('', 'rowid ASC') < 0) $error++;
    } elseif (!ExtDirect::checkDolVersion(1) && empty($refresh)) {
        // validate if dolibarr version is in compatibility range
        if (($mesgText = $langs->trans("DolibarrCompatibilityError")) && ($mesgText != "DolibarrCompatibilityError")) {
            setEventMessage($mesgText, 'warnings');
        } else {
            setEventMessage('Dolibarr version not yet tested for compatibility<br>Please contact <a href="mailto:info@z-application.com">Z-Application</a>', 'warnings');
        }
    }
} else {
    setEventMessage($extDirect->error, 'errors');
}


if ($action && !$refresh && !(($action == 'selectall') || ($action == 'selectnone'))) {
    if (! $res > 0) $error++;

    if (! $error) {
        $db->commit();  
        setEventMessage($langs->trans("SetupSaved"));
    } else {
        $db->rollback();
        setEventMessage($extDirect->error, 'errors');
    }
}

/*
 * View
 */

// init headers en tabs
$title = $langs->trans('DirectConnectSetup');
$tabsTitle = $langs->trans('DirectConnect');
$tabs = array('tab1' => $authentication,'tab2' => $activities);
$head = extdirect_admin_prepare_head($tabs);

llxHeader('', $title);
$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($title, $linkback, 'setup');
$form=new Form($db);
if ($mode == $tabs['tab1']->mode) {
    //tab1
    dol_fiche_head($head, 'tab1', $tabsTitle, 0);
    
    $var=true;
    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre">';
    print '<td>'.$langs->trans("Parameters").'</td>'."\n";
    print '<td align="right" width="60">'.$langs->trans("Value").'</td>'."\n";
    print '<td width="80">&nbsp;</td></tr>'."\n";
    
    
    // autoasign activation/desactivation
    $var=!$var;
    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print '<input type="hidden" name="action" value="autoasign">';
    print '<tr '.$bc[$var].'>';
    print '<td>'.$langs->trans("AutoAsignAbility").'</td>';
    print '<td width="60" align="right">';
    print $form->selectyesno("auto_asign", $conf->global->DIRECTCONNECT_AUTO_ASIGN, 1);
    print '</td><td align="right">';
    print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
    print '</td>';
    print '</tr>';
    print '</form>';
    
    $userExclude[0]=0; //exclude admin
    
    if ($conf->global->DIRECTCONNECT_AUTO_ASIGN) {
        // select auto asigned user
    
        $var=!$var;
        print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
        print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
        print '<input type="hidden" name="action" value="autouser">';
        print '<tr '.$bc[$var].'>';
        print '<td>'.$langs->trans("AutoUser").'</td>';
        print '<td align="right">';
        print $form->select_users($conf->global->DIRECTCONNECT_AUTO_USER, 'userid', 1, $userExclude, 0, '', '');
        print '</td><td align="right"><input type="submit" class="button" value="'.$langs->trans("Modify").'"></td>';
        print '</tr>';
        print '</form>';
    }
    // asign users to app uuid
    
    print '<form action="'.$_SERVER['PHP_SELF'].'" method="POST">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print '<input type="hidden" name="action" value="save">';
    
    $var=true;
    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre">';
    print '<td>'.$langs->trans("RequestId").'</td>';
    print '<td>'.$langs->trans("AppName").'</td>';
    print '<td>'.$langs->trans("DateC").'</td>';
    print '<td>'.$langs->trans("DateLastConnect").'</td>';
    print '<td>'.$langs->trans("DevPlatform").'</td>';
    print '<td>'.$langs->trans("DevType").'</td>';
    print '<td>'.$langs->trans("User").'</td>';
    print '<td>'.$langs->trans("Ack").'</td>';
    print '<td><a href="'.$_SERVER['PHP_SELF'].'?action=selectall">'.$langs->trans("removeAll");
    print '</a>/<a href="'.$_SERVER['PHP_SELF'].'?action=selectnone">'.$langs->trans("None").'</a>';
    print '</tr>'."\n";
    if (! empty($extDirect->dataset)) {
        $i=0;
        foreach ($extDirect->dataset as $user_app) {
            $var=!$var;
            $userId = ($user_app['fk_user']?$user_app['fk_user']:-1);
            print '<tr '.$bc[$var].'>';
            print '<td>'.$user_app['requestid'].'</td>';
            print '<td>'.$user_app['app_name'].'</td>';
            print '<td>'.$user_app['datec'].'</td>';
            print '<td>'.$user_app['date_last_connect'].'</td>';
            print '<td>'.$user_app['dev_platform'].'</td>';
            print '<td>'.$user_app['dev_type'].'</td>';
            print '<td align="right" width="60">';
            print $form->select_users($userId, 'userid'.$i, 1, $userExclude, 0, '', '');
            print '</td>';
            print '<td align="right" width="40">';
            print '<input '.$bc[$var].' type="checkbox" name="ACK" value="1"';
            print ((!empty($user_app['ack_id']))?' checked="checked"':'').' disabled="disabled">';
            print '</td>';
            print '<td align="right" width="40">';
            $key='REMOVE_'.$user_app['app_id'].$i;
            print '<input '.$bc[$var].' type="checkbox" name="'.$key.'" value="1"';
            print ((($action=='selectall') && $action!="selectnone")?' checked="checked"':'').'>';
            print '</td></tr>'."\n";
            $i++;
        }
    }
    print '</table>';
    
    print '<br><center>';
    print '<input type="submit" name="save" class="button" value="'.$langs->trans("Save").'">';
    print ' &nbsp; &nbsp; ';
    print '<input type="submit" name="refresh" class="button" value="'.$langs->trans("Refresh").'">';
    print "</center>";
    
    print "</form>\n";
} else if ($mode == $tabs['tab2']->mode) {
    //tab1
    dol_fiche_head($head, 'tab2', $tabsTitle, 0);
    print '<form action="'.$_SERVER['PHP_SELF'].'?mode=activities" method="POST">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print '<input type="hidden" name="action" value="clear">';
    $var=true;
    print '<table class="noborder" width="100%">';
    // parameters
    print '<tr class="liste_titre">';
    print '<td>'.$langs->trans("Parameters").'</td>'."\n";
    print '<td>'.$langs->trans("Value").'</td>'."\n";
    print '<td></td><td></td><td></td><td></td><td></td><td></td></tr>'."\n";
    // user refresh or clear
    $var=!$var;
    print '<tr '.$bc[$var].'>';
    print '<td>'.$langs->trans("ActivitiesFromUser").'</td>';
    print '<td>';
    print $form->select_users($userId, 'userid', 1, $userExclude, 0, '', '');
    print '</td><td></td><td></td><td></td><td></td><td>';
    print '<input type="submit" name="refresh" class="button" value="'.$langs->trans("Refresh").'">';
    print '</td>';
    print '</td><td>';
    print '<input type="submit" name="clear" class="button" value="'.$langs->trans("Clear").'">';
    print '</td></tr>';
    print '</tr>'."\n";
    // activies list
    print '<tr class="liste_titre">';
    print '<td>'.$langs->trans("RequestId").'</td>';
    print '<td>'.$langs->trans("AppName").'</td>';
    print '<td>'.$langs->trans("AppVersion").'</td>';
    print '<td>'.$langs->trans("DateC").'</td>';
    print '<td>'.$langs->trans("ActivityName").'</td>';
    print '<td>'.$langs->trans("Status").'</td>';
    print '<td>'.$langs->trans("Duration").'</td>';
    print '<td>'.$langs->trans("User").'</td>';
    print '</tr>'."\n";
    if (! empty($extDirect->dataset)) {
        $i=0;
        foreach ($extDirect->dataset as $data) {
            $var=!$var;
            print '<tr '.$bc[$var].'>';
            print '<td>'.$data['requestid'].'</td>';
            print '<td>'.$data['app_name'].'</td>';
            print '<td>'.$data['app_version'].'</td>';
            print '<td>'.$data['datec'].'</td>';
            print '<td>'.$data['activity_name'].'</td>';
            print '<td>'.$data['status'].'</td>';
            print '<td>'.$data['duration'].'</td>';
            print '<td>'.$data['firstname'].$data['lastname'].'</td>';
            print '</tr>'."\n";
            $i++;
        }
    }
    print '</table>';
    print "</form>\n";
}

llxFooter();

$db->close();

/**
 *  Return array head with list of tabs to view object informations.
 *
 *  @param  Array   $tabs       tab names
 *  @return array               head array with tabs
 */
function extdirect_admin_prepare_head($tabs)
{
    $h = 0;
    $head = array();
    
    foreach ($tabs as $key => $value) {
        $head[$h][0] = DOL_URL_ROOT."/extdirect/admin/extdirect.php?mode=".$value->mode;
        $head[$h][1] = $value->title;
        $head[$h][2] = $key;
        $h++;
    }

    return $head;
}