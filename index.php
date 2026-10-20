<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
if(!is_file(__DIR__.'/config.php')) go('install.php');
require __DIR__.'/app/actions.php';
require __DIR__.'/app/activation.php';
require __DIR__.'/app/reports.php';
require __DIR__.'/app/views.php';
require __DIR__.'/app/import.php';
require __DIR__.'/app/feature-views.php';
require __DIR__.'/app/management-absence.php';
require __DIR__.'/app/geofence-views.php';
$user=current_user();
$home=$user && $user['role']!=='user'?'overview':'dashboard';
$page=is_string($_GET['page']??null)?$_GET['page']:$home;
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        check_csrf(); $action=text_input('action',40,true);
        if($action==='login') login_action();
        if(!$user) throw new DomainException('Inicia sesión para continuar.');
        if($action==='logout') { $_SESSION=[]; session_regenerate_id(true); go(); }
        if($user['must_change'] && $action!=='profile') throw new DomainException('Primero debes cambiar tu contraseña temporal en Mi perfil.');
        if($user['role']==='auditor' && $action!=='profile') throw new DomainException('El perfil Auditor sólo permite consultar y exportar.');
        if($action==='challenge') {
            require_role($user,['user']);
            $_SESSION['punch_challenge']=['token'=>bin2hex(random_bytes(32)),'issued'=>time()];
            header('Content-Type: application/json; charset=utf-8'); echo json_encode(['token'=>$_SESSION['punch_challenge']['token']]); exit;
        }
        match($action) {
            'geofence'=>geofence_action($user), 'geofence_assign'=>geofence_assign_action($user), 'coordinates'=>coordinates_action($user), 'punch'=>punch_action($user), 'correct'=>correction_action($user), 'profile'=>profile_action($user),
            'user'=>user_action($user), 'schedule'=>schedule_action($user), 'reset'=>reset_action($user),
            'activate_installation'=>activation_action($user), 'settings'=>settings_action($user), 'holiday'=>holiday_action($user), 'remove_holiday'=>holiday_action($user,true),
            'import_preview'=>import_preview_action($user), 'import_confirm'=>import_confirm_action($user),
            'management_absence'=>management_absence_action($user), 'absence_request'=>absence_request_action($user), 'absence_decision'=>absence_decision_action($user),
            default=>throw new DomainException('Acción no válida.')
        };
    } catch(DomainException $error) {
        if(($_POST['action']??'')==='challenge') { http_response_code(400); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['error'=>$error->getMessage()]); exit; }
        flash($error->getMessage(),'error');
    }
}
if(!$user) { login_view(); exit; }
if($user['role']!=='user' && in_array($page,['dashboard','history','absences'],true)) $page='overview';
if($user['must_change']) $page='profile';
$allowed=$user['role']==='user'?['dashboard','history','profile','absences','locations']:['profile'];
if(in_array($user['role'],['admin','analyst'],true)) $allowed=[...$allowed,'overview','reports','edit','requests'];
if($user['role']==='auditor') $allowed=[...$allowed,'overview','reports','edit','requests','audit'];
if($user['role']==='admin') $allowed=[...$allowed,'users','user','settings','audit','import','geofences'];
if(!in_array($page,$allowed,true)) { http_response_code(403); flash('No tienes acceso a esta sección.','error'); $page=$home; }
page_start($user,$page);
if($user['role']==='admin' && !geofences_ready()) echo '<div class="notice warning">Hay nuevas funciones disponibles. <a href="update.php">Actualizar sistema</a> para habilitar los cercos GPS, conservando los registros actuales.</div>';
try {
    match($page) {
        'dashboard'=>dashboard_view($user), 'history','reports'=>reports_view($user,$page), 'overview'=>overview_view(),
        'locations'=>user_locations_view($user), 'profile'=>profile_view($user), 'edit'=>edit_view($user), 'users'=>users_view(), 'user'=>user_view(),
        'geofences'=>geofences_view(), 'settings'=>(function(){settings_view();activation_view();})(), 'audit'=>audit_view(),
        'import'=>import_view($user),'absences'=>absences_view($user),'requests'=>absences_view($user,true),default=>null
    };
} catch(DomainException $error) { echo '<section class="panel"><h2>Revisa la solicitud</h2><p>'.e($error->getMessage()).'</p><a href="index.php">Volver al inicio</a></section>'; }
page_end();
