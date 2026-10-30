<?php
declare(strict_types=1);

function mail_settings(): array {
    $file=dirname(__DIR__).'/mail-config.php';
    return is_file($file)?require $file:['enabled'=>false,'host'=>'','port'=>587,'security'=>'tls','username'=>'','password'=>'','from'=>'','name'=>'Control horario'];
}
function mail_public_settings(array $c): array { unset($c['password']);return $c; }
function mail_validate(array $c): void {
    if(!preg_match('/^[a-zA-Z0-9.-]+$/',$c['host']) || strlen($c['host'])>200 || $c['port']<1 || $c['port']>65535) throw new DomainException('Revisa el servidor SMTP y su puerto.');
    if(!in_array($c['security'],['tls','ssl'],true)) throw new DomainException('Selecciona STARTTLS o TLS implícito.');
    if(!filter_var($c['from'],FILTER_VALIDATE_EMAIL)) throw new DomainException('El correo remitente no es válido.');
    if($c['username']==='' || $c['password']==='') throw new DomainException('Completa el usuario y la contraseña del servidor de correo.');
    if(!extension_loaded('openssl')) throw new DomainException('Activa OpenSSL en PHP para utilizar correo seguro.');
}
function mail_send(array $c,string $recipient,string $subject,string $body): void {
    mail_validate($c);
    if(!filter_var($recipient,FILTER_VALIDATE_EMAIL)) throw new DomainException('La persona no tiene un correo válido en su perfil.');
    require_once __DIR__.'/vendor/phpmailer/Exception.php';
    require_once __DIR__.'/vendor/phpmailer/PHPMailer.php';
    require_once __DIR__.'/vendor/phpmailer/SMTP.php';
    $m=new PHPMailer\PHPMailer\PHPMailer(true);
    $m->isSMTP();$m->Host=$c['host'];$m->Port=$c['port'];$m->SMTPSecure=$c['security'];
    $m->SMTPAuth=true;$m->Username=$c['username'];$m->Password=$c['password'];
    $m->Timeout=8;$m->Timelimit=10;$m->SMTPDebug=0;$m->CharSet='UTF-8';
    $m->setFrom($c['from'],$c['name']);$m->addAddress($recipient);
    $m->Subject=$subject;$m->Body=$body;$m->send();
}
function mail_receipt_body(array $user,string $kind,string $now,int $attendance): string {
    return 'Hola '.$user['name'].",\n\nTu ".($kind==='in'?'entrada':'salida').' quedó registrada en '.settings()['company'].".\n\nFecha y hora: ".local_time($now,'d/m/Y H:i:s')."\nZona horaria: ".settings()['timezone']."\nReferencia de asistencia: #".$attendance."\n\nPuedes consultar el registro en Mi historial.\n\nControl horario · LOOPERS";
}
function mail_receipt(array $user,string $kind,string $now,int $attendance): string {
    // Called only AFTER the attendance transaction commits. Never throw to the punch flow.
    $status='failed';
    try {
        $c=mail_settings();if(empty($c['enabled']))return 'disabled';
        if(!filter_var($user['email']??'',FILTER_VALIDATE_EMAIL)) $status='missing_email';
        else {mail_send($c,$user['email'],'Confirmación de '.($kind==='in'?'entrada':'salida').' · '.local_time($now,'d/m/Y'),mail_receipt_body($user,$kind,$now,$attendance));$status='accepted';}
    } catch(Throwable $error) { error_log('Control horario SMTP: receipt failed; attendance='.$attendance.'; kind='.$kind); }
    try {audit((int)$user['id'],'mail_receipt','attendance',$attendance,null,['kind'=>$kind,'status'=>$status],'Notificación de marcado por correo');}
    catch(Throwable $error){error_log('Control horario SMTP: unable to log receipt result; attendance='.$attendance);}
    return $status;
}
function mail_settings_action(array $actor): void {
    require_role($actor,['admin']);
    $lock=fopen(__FILE__,'r');if(!$lock || !flock($lock,LOCK_EX)) throw new DomainException('No se pudo bloquear la configuración de correo. Intenta nuevamente.');
    $temp=null;
    try {
        $old=mail_settings();
        $password=$_POST['smtp_password']??'';
        if(!is_string($password) || strlen($password)>1000)throw new DomainException('Contraseña SMTP no válida.');
        $c=['enabled'=>isset($_POST['mail_enabled']),'host'=>text_input('smtp_host',200),'port'=>(int)text_input('smtp_port',5),'security'=>text_input('smtp_security',10),'username'=>text_input('smtp_username',200),'password'=>$password!==''?$password:$old['password'],'from'=>text_input('smtp_from',190),'name'=>text_input('smtp_name',150)];
        if($c['enabled'])mail_validate($c);
        if($c['name']==='')$c['name']='Control horario';
        $temp=dirname(__DIR__).'/mail-config.'.bin2hex(random_bytes(8)).'.php';
        $content="<?php\n// Private SMTP settings. Never publish or include in release archives.\nreturn ".var_export($c,true).";\n";
        $f=@fopen($temp,'x');if(!$f)throw new DomainException('PHP necesita permiso de escritura en la carpeta de la aplicación para guardar el correo.');
        try {@chmod($temp,0600);if(fwrite($f,$content)!==strlen($content) || !fflush($f))throw new RuntimeException('SMTP configuration write failed');}finally{fclose($f);}
        // Record only non-secret settings; never include passwords in the audit trail.
        audit((int)$actor['id'],'mail_settings','settings',1,mail_public_settings($old),mail_public_settings($c),'Configuración del correo saliente');
        if(!@rename($temp,dirname(__DIR__).'/mail-config.php'))throw new DomainException('No se pudo reemplazar mail-config.php. Revisa sus permisos con el hosting.');
    } finally {if($temp && is_file($temp))unlink($temp);flock($lock,LOCK_UN);fclose($lock);}
    flash('Configuración de correo guardada. Puedes enviar un correo de prueba.');go('index.php?page=settings');
}
function mail_test_action(array $actor): void {
    require_role($actor,['admin']);$recipient=text_input('test_recipient',190,true);
    if(!filter_var($recipient,FILTER_VALIDATE_EMAIL))throw new DomainException('Ingresa un destinatario válido para la prueba.');
    if(time()-($_SESSION['mail_test_at']??0)<30)throw new DomainException('Espera 30 segundos antes de enviar otra prueba.');
    $_SESSION['mail_test_at']=time();$status='failed';
    try {mail_send(mail_settings(),$recipient,'Prueba de correo · Control horario','El servidor SMTP recibió esta prueba de Control horario.');$status='accepted';}
    catch(Throwable $error){error_log('Control horario SMTP: test failed');}
    audit((int)$actor['id'],'mail_test','settings',1,null,['status'=>$status],'Prueba de correo saliente');
    if($status!=='accepted')throw new DomainException('No se pudo enviar la prueba. Revisa servidor, puerto, cifrado, usuario, contraseña y remitente autorizado. Comprueba también que el hosting permita conexiones SMTP salientes y tenga certificados de confianza actualizados.');
    flash('El servidor de correo aceptó la prueba. Revisa la bandeja de entrada y correo no deseado.');go('index.php?page=settings');
}
function mail_settings_view(): void { $c=mail_settings(); ?>
<section class="panel stack"><h2>Correo de confirmación de marcados</h2><p>Envía una confirmación al correo del perfil de cada usuario cuando marca entrada o salida. Los ajustes manuales y las importaciones no generan estos mensajes.</p>
<form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="mail_settings"><label class="check"><input type="checkbox" name="mail_enabled" <?= $c['enabled']?'checked':'' ?>> Activar confirmaciones por correo</label>
<div class="form-grid"><label>Servidor SMTP<input name="smtp_host" maxlength="200" value="<?= e($c['host']) ?>" placeholder="mail.tudominio.cl"></label><label>Puerto<input type="number" name="smtp_port" min="1" max="65535" value="<?= (int)$c['port'] ?>"></label><label>Cifrado<select name="smtp_security"><option value="tls" <?= $c['security']==='tls'?'selected':'' ?>>STARTTLS (habitualmente 587)</option><option value="ssl" <?= $c['security']==='ssl'?'selected':'' ?>>TLS implícito (habitualmente 465)</option></select></label><label>Usuario SMTP<input name="smtp_username" maxlength="200" value="<?= e($c['username']) ?>" autocomplete="off"></label><label>Contraseña SMTP<input type="password" name="smtp_password" maxlength="1000" autocomplete="new-password"><small>Déjala vacía para conservar la contraseña guardada.</small></label><label>Correo remitente<input type="email" name="smtp_from" maxlength="190" value="<?= e($c['from']) ?>"></label><label>Nombre del remitente<input name="smtp_name" maxlength="150" value="<?= e($c['name']) ?>"></label></div><button class="button primary">Guardar correo saliente</button></form>
<p class="small muted">El marcado se guarda aunque falle el correo. Se registra el resultado en Trazabilidad. La aceptación por el servidor SMTP no garantiza la entrega en la bandeja del destinatario.</p>
<form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="mail_test"><label>Destinatario de prueba<input type="email" name="test_recipient" maxlength="190" required></label><button class="button secondary">Enviar prueba con la configuración guardada</button></form></section>
<?php }
