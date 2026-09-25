<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
if(!is_file(__DIR__.'/config.php')) go('install.php');
require __DIR__.'/app/actions.php';
require __DIR__.'/app/reports.php';
require __DIR__.'/app/views.php';
$user=current_user();
$page=is_string($_GET['page']??null)?$_GET['page']:'dashboard';
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        check_csrf(); $action=text_input('action',40,true);
        if($action==='login') login_action();
        if(!$user) throw new DomainException('Inicia sesión para continuar.');
        if($action==='logout') { $_SESSION=[]; session_regenerate_id(true); go(); }
        if($user['must_change'] && $action!=='profile') throw new DomainException('Primero debes cambiar tu contraseña temporal en Mi perfil.');
        if($action==='challenge') {
            $_SESSION['punch_challenge']=['token'=>bin2hex(random_bytes(32)),'issued'=>time()];
            header('Content-Type: application/json; charset=utf-8'); echo json_encode(['token'=>$_SESSION['punch_challenge']['token']]); exit;
        }
        match($action) {
            'punch'=>punch_action($user), 'correct'=>correction_action($user), 'profile'=>profile_action($user),
            'user'=>user_action($user), 'schedule'=>schedule_action($user), 'reset'=>reset_action($user),
            'settings'=>settings_action($user), 'holiday'=>holiday_action($user), 'remove_holiday'=>holiday_action($user,true),
            default=>throw new DomainException('Acción no válida.')
        };
    } catch(DomainException $error) {
        if(($_POST['action']??'')==='challenge') { http_response_code(400); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['error'=>$error->getMessage()]); exit; }
        flash($error->getMessage(),'error');
    }
}
if(!$user) { login_view(); exit; }
if($user['must_change']) $page='profile';
$allowed=['dashboard','history','profile'];
if(in_array($user['role'],['admin','analyst'],true)) $allowed=[...$allowed,'overview','reports','edit'];
if($user['role']==='admin') $allowed=[...$allowed,'users','user','settings','audit'];
if(!in_array($page,$allowed,true)) { http_response_code(403); flash('No tienes acceso a esta sección.','error'); $page='dashboard'; }
page_start($user,$page);
try {
    match($page) {
        'dashboard'=>dashboard_view($user), 'history','reports'=>reports_view($user,$page), 'overview'=>overview_view(),
        'profile'=>profile_view($user), 'edit'=>edit_view($user), 'users'=>users_view(), 'user'=>user_view(),
        'settings'=>settings_view(), 'audit'=>audit_view(), default=>null
    };
} catch(DomainException $error) { echo '<section class="panel"><h2>Revisa la solicitud</h2><p>'.e($error->getMessage()).'</p><a href="index.php">Volver al inicio</a></section>'; }
page_end();
