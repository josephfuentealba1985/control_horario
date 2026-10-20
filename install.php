<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
if(is_file(__DIR__.'/config.php')) go('index.php');
require __DIR__.'/app/views.php';
require_once __DIR__.'/app/activation.php';
$checks=['PHP 8.2 o superior'=>version_compare(PHP_VERSION,'8.2.0','>='),'Conexión MySQL (PDO)'=>extension_loaded('pdo_mysql'),'Conversión de texto (iconv)'=>extension_loaded('iconv'),'Escritura de configuración'=>is_writable(__DIR__),'Conexión segura de activación'=>extension_loaded('curl') && extension_loaded('openssl')];
$ok=!in_array(false,$checks,true); $error=null;
$missing=array_keys(array_filter($checks,static fn(bool $passed): bool=>!$passed));
$fixes=[
    'PHP 8.2 o superior'=>'En el selector PHP del hosting, asigna PHP 8.2 o superior a este dominio. Versión detectada: '.PHP_VERSION.'.',
    'Conexión MySQL (PDO)'=>'Activa la extensión pdo_mysql para la versión PHP que utiliza este dominio. Crear la base de datos no activa esta extensión.',
    'Conversión de texto (iconv)'=>'Activa la extensión iconv en la configuración PHP del dominio. Se utiliza para generar los reportes PDF.',
    'Escritura de configuración'=>'PHP no tiene permiso para escribir en la carpeta donde está install.php. Revisa propietario y permisos con tu proveedor para permitir que PHP cree config.php. No es necesario conceder permisos 777.',
    'Conexión segura de activación'=>'Activa las extensiones cURL y OpenSSL en la configuración PHP del hosting.'
];
function install_old(string $name,string $default=''): string {
    $value=$_POST[$name]??$default;
    return e(is_string($value)?$value:$default);
}
function install_requirements_notice(array $missing,array $fixes): void {
    if(!$missing) return;
    echo '<section id="requisitos-pendientes" class="notice warning" role="alert"><strong>Antes de instalar, resuelve estos requisitos del hosting:</strong><ul>';
    foreach($missing as $label) echo '<li><strong>'.e($label).':</strong> '.e($fixes[$label]).'</li>';
    echo '</ul><p>Después de corregirlos, pulsa «Instalar y crear administrador» para volver a comprobarlos. Los datos de administrador y MySQL no cambian estas comprobaciones.</p></section>';
}
if($_SERVER['REQUEST_METHOD']==='POST') {
    $lock=null; $connection=null;
    try {
        check_csrf();
        if(!$ok) throw new DomainException('La instalación no comenzó. Falta resolver: '.implode(', ',$missing).'. Sigue las instrucciones de los requisitos pendientes.');
        $activationKey=text_input('install_key',100,true);
        $company=text_input('company',180,true); $name=text_input('name',150,true); $rut=normalize_rut(text_input('rut',30,true)); $password=pass_input();
        if(!rut_valid($rut)) throw new DomainException('El RUT del administrador no es válido.');
        if($password!==($_POST['password_confirm']??null)) throw new DomainException('Las contraseñas no coinciden.');
        $timezone=text_input('timezone',64,true); if(!in_array($timezone,DateTimeZone::listIdentifiers(),true)) throw new DomainException('Zona horaria no válida.');
        $host=text_input('host',200,true); $database=text_input('database',100,true); $username=text_input('username',100,true); $dbPassword=$_POST['db_password']??'';
        if(!is_string($dbPassword) || strlen($dbPassword)>1000 || !preg_match('/^[a-zA-Z0-9_.:-]+$/',$host) || !preg_match('/^[a-zA-Z0-9_$-]+$/',$database)) throw new DomainException('Revisa el servidor y el nombre de la base de datos.');
        $port=filter_var($_POST['port']??3306,FILTER_VALIDATE_INT); if(!$port || $port<1 || $port>65535) throw new DomainException('Puerto no válido.');
        // Lock the existing activation module: no publicly readable temporary config or lock files.
        $lock=fopen(__DIR__.'/app/activation.php','r'); if(!$lock || !flock($lock,LOCK_EX)) throw new RuntimeException('No se pudo bloquear el instalador.');
        clearstatcache(true,__DIR__.'/config.php'); if(is_file(__DIR__.'/config.php')) go('index.php');
        $connection=new PDO('mysql:host='.$host.';port='.$port.';dbname='.$database.';charset=utf8mb4',$username,$dbPassword,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $existing=$connection->query("SHOW TABLES LIKE 'ch_settings'")->fetchColumn();
        if($existing && $connection->query('SELECT COUNT(*) FROM ch_settings')->fetchColumn()>0) throw new DomainException('Esta base ya contiene una instalación. Restaura su config.php o utiliza una base vacía. No se sobrescribieron los datos.');
        foreach(require __DIR__.'/app/schema.php' as $sql) $connection->exec($sql);
        activation_register($activationKey);
        $connection->beginTransaction();
        $now=utc_now(); $start=(new DateTimeImmutable('now',new DateTimeZone($timezone)))->format('Y-m-d');
        $statement=$connection->prepare('INSERT INTO ch_settings(id,company,timezone,installed_at) VALUES(1,?,?,?)'); $statement->execute([$company,$timezone,$now]);
        $statement=$connection->prepare("INSERT INTO ch_users(rut,name,institution,role,start_date,password_hash,must_change,created_at) VALUES(?,?,?,'admin',?,?,0,?)"); $statement->execute([$rut,$name,$company,$start,password_hash($password,PASSWORD_DEFAULT),$now]); $admin=(int)$connection->lastInsertId();
        $statement=$connection->prepare('INSERT INTO ch_schedules(user_id,effective_from,days_json,created_by,created_at) VALUES(?,?,?,?,?)'); $statement->execute([$admin,$start,json_encode(default_days()),$admin,$now]);
        $statement=$connection->prepare("INSERT INTO ch_audit(actor_id,action,entity,entity_id,after_json,reason,created_at) VALUES(?,'install','settings','1',?,'Instalación inicial',?)"); $statement->execute([$admin,json_encode(['company'=>$company,'timezone'=>$timezone]),$now]);
        $config=['host'=>$host,'port'=>$port,'database'=>$database,'username'=>$username,'password'=>$dbPassword];
        $content="<?php\ndeclare(strict_types=1);\n// Credenciales privadas: conservar fuera del control de versiones.\nreturn ".var_export($config,true).";\n";
        $file=@fopen(__DIR__.'/config.php','x'); if(!$file) throw new DomainException('PHP no pudo crear config.php. Revisa con el hosting los permisos y el propietario de la carpeta de la aplicación; luego vuelve a intentar.');
        try { if(fwrite($file,$content)!==strlen($content)) throw new RuntimeException('No se pudo guardar toda la configuración.'); } finally { fclose($file); }
        try { $connection->commit(); } catch(Throwable $failure) { unlink(__DIR__.'/config.php'); throw $failure; }
        @chmod(__DIR__.'/config.php',0600);
        $_SESSION=[]; session_regenerate_id(true); flash('Instalación completada. Ingresa con la cuenta de administrador que acabas de crear.'); go('index.php');
    } catch(Throwable $failure) {
        if($connection && $connection->inTransaction()) { $connection->rollBack(); if(is_file(__DIR__.'/config.php')) unlink(__DIR__.'/config.php'); }
        $error=$failure instanceof DomainException?$failure->getMessage():'No se pudo completar la instalación. Revisa los datos de MySQL, los permisos de creación de tablas y de escritura. El detalle está en el registro de errores del hosting.';
        if(!$failure instanceof DomainException) error_log('Instalación control horario: '.$failure->getMessage());
    } finally { if(is_resource($lock)) { flock($lock,LOCK_UN); fclose($lock); } }
}
head('Instalación'); ?>
<main class="install-shell"><div class="brand"><span class="brand-icon"><?= icon('clock') ?></span>control<span class="brand-light">horario</span></div><div class="eyebrow">PUESTA EN MARCHA</div><h1>Tu equipo, a tiempo.</h1><p class="muted">Completa estos datos una sola vez. No necesitas utilizar la consola.</p><div class="requirements"><?php foreach($checks as $label=>$passed) echo '<span class="badge '.($passed?'green':'orange').'">'.($passed?'✓ ':'✕ ').e($label).'</span>'; ?></div><?php install_requirements_notice($missing,$fixes); if($error) echo '<div class="notice error" role="alert">'.e($error).'</div>'; ?><form method="post" class="stack"><section class="panel stack"><?= csrf_field() ?><h2>1. Autoriza la instalación</h2><label>Clave de instalación<input type="password" name="install_key" required autocomplete="off"></label><p class="small muted">Obtén una clave gratuita en <a href="https://loopers.cl/proyectos/registro.php" target="_blank" rel="noopener">Proyectos abiertos de LOOPERS</a>. Una clave corresponde a una instalación. Se necesita conexión con LOOPERS sólo para activar; el uso posterior no depende de ese servicio.</p></section><section class="panel stack"><h2>2. Conecta tu base de datos</h2><p class="small muted">Primero crea una base vacía y su usuario en el panel del hosting. Asigna a ese usuario permisos sobre esa base.</p><div class="form-grid"><label>Servidor<input name="host" value="<?= install_old('host','localhost') ?>" required></label><label>Puerto<input type="number" name="port" value="<?= install_old('port','3306') ?>" min="1" max="65535" required></label><label>Nombre de la base de datos<input name="database" value="<?= install_old('database') ?>" required autocomplete="off"></label><label>Usuario de la base de datos<input name="username" value="<?= install_old('username') ?>" required autocomplete="off"></label></div><label>Contraseña de la base de datos<input type="password" name="db_password" autocomplete="new-password"></label></section><section class="panel stack"><h2>3. Empresa y administrador</h2><label>Nombre de la empresa o institución<input name="company" value="<?= install_old('company') ?>" maxlength="180" required></label><label>Zona horaria<select name="timezone"><option value="America/Santiago" <?= install_old('timezone','America/Santiago')==='America/Santiago'?'selected':'' ?>>Chile continental · America/Santiago</option><option value="America/Punta_Arenas" <?= install_old('timezone','America/Santiago')==='America/Punta_Arenas'?'selected':'' ?>>Magallanes · America/Punta_Arenas</option><option value="Pacific/Easter" <?= install_old('timezone','America/Santiago')==='Pacific/Easter'?'selected':'' ?>>Isla de Pascua · Pacific/Easter</option></select></label><div class="form-grid"><label>Nombre del administrador<input name="name" value="<?= install_old('name') ?>" maxlength="150" required></label><label>RUT del administrador<input name="rut" value="<?= install_old('rut') ?>" placeholder="12.345.678-5" maxlength="30" required autocomplete="username"></label><label>Contraseña (mínimo 12 caracteres)<input type="password" name="password" minlength="12" maxlength="72" required autocomplete="new-password"></label><label>Repetir contraseña<input type="password" name="password_confirm" minlength="12" maxlength="72" required autocomplete="new-password"></label></div><p class="small muted">Horario inicial: lunes a jueves de 08:00 a 17:00; viernes de 08:00 a 16:00. Colación incluida. Puedes configurar horarios individuales y feriados después de instalar.</p><?php if(!$ok): ?><div class="notice warning"><strong>La instalación necesita una corrección del hosting.</strong><p>Requisitos pendientes: <?= e(implode(', ',$missing)) ?>.</p><a href="#requisitos-pendientes">Ver cómo resolverlos ↑</a></div><?php endif; ?><button type="submit" class="button primary wide">Instalar y crear administrador</button></section></form></main><?php footer(); echo '</body></html>'; ?>
