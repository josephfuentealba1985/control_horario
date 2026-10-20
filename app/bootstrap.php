<?php
declare(strict_types=1);
require_once __DIR__.'/core.php';
require_once __DIR__.'/features.php';
require_once __DIR__.'/locations.php';
require_once __DIR__.'/geofences.php';
ini_set('display_errors','0');
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
$secure=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off');
$host=explode(':',$_SERVER['HTTP_HOST']??'')[0];
if(PHP_SAPI!=='cli' && !$secure && !in_array($host,['localhost','127.0.0.1','[::1]'],true)) {
    http_response_code(403); exit('Abre esta aplicación mediante HTTPS.');
}
session_name('ch_'.substr(hash('sha256',dirname(__DIR__)),0,12));
session_set_cookie_params(['lifetime'=>0,'path'=>rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'/')),'/').'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_start();
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
header('Permissions-Policy: geolocation=(self), camera=(), microphone=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: https://tile.openstreetmap.org; connect-src 'self'; font-src 'self'; frame-src https://www.openstreetmap.org; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
if($secure) header('Strict-Transport-Security: max-age=31536000');
if(isset($_SESSION['uid']) && (time()-($_SESSION['last_seen']??0)>1800 || time()-($_SESSION['started']??0)>43200)) { $_SESSION=[]; session_regenerate_id(true); flash('Tu sesión caducó. Inicia sesión nuevamente.','info'); }
$_SESSION['last_seen']=time();
set_exception_handler(function(Throwable $error): void {
    error_log('Control horario: '.$error->getMessage());
    if(isset($GLOBALS['pdo']) && $GLOBALS['pdo']->inTransaction()) $GLOBALS['pdo']->rollBack();
    http_response_code(500);
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Servicio no disponible</title><link rel="stylesheet" href="assets/style.css"><main class="login-shell"><section class="panel"><h1>No pudimos completar la solicitud</h1><p>Intenta nuevamente. Si estabas marcando, revisa tu registro antes de repetir la operación.</p><p>El administrador puede revisar la conexión y el registro de errores del hosting.</p><a href="index.php">Volver al inicio</a></section></main></html>';
});
