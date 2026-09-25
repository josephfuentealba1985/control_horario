<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
if(!is_file(__DIR__.'/config.php')) go('install.php');
require __DIR__.'/app/views.php';
$user=current_user();
if(!$user || $user['role']!=='admin' || $user['must_change']) { http_response_code(403); exit('Sólo un administrador con sesión vigente puede actualizar el sistema.'); }
$error=null;
if($_SERVER['REQUEST_METHOD']==='POST') {
    $locked=false; $lock='ch_update_'.substr(hash('sha256',(string)query('SELECT DATABASE()')->fetchColumn()),0,32);
    try {
        check_csrf(); $locked=(int)query('SELECT GET_LOCK(?,10)',[$lock])->fetchColumn()===1;
        if(!$locked) throw new DomainException('Otra actualización está en curso. Intenta nuevamente.');
        foreach(require __DIR__.'/app/feature-schema.php' as $sql) db()->exec($sql);
        audit((int)$user['id'],'schema_update','system',2,null,['version'=>2],'Actualización de importación e inasistencias');
        flash('Sistema actualizado. Ya puedes importar Excel y gestionar inasistencias.'); go();
    } catch(Throwable $e) {
        error_log('Actualización control horario: '.$e->getMessage());
        $error=$e instanceof DomainException?$e->getMessage():'No se completó la actualización. Revisa que el usuario MySQL tenga permiso para crear tablas. Puedes volver a intentarlo sin reinstalar ni borrar datos.';
    } finally { if($locked) query('SELECT RELEASE_LOCK(?)',[$lock]); }
}
head('Actualizar sistema'); ?>
<main class="install-shell"><section class="panel"><div class="eyebrow">ACTUALIZACIÓN 1.1</div><h1>Historial y justificaciones</h1><p>Esta actualización agrega las tablas de importaciones y solicitudes. Conserva personas, contraseñas, horarios y marcados.</p><p>Antes de continuar, conserva un respaldo de la base desde el panel de tu hosting.</p><?php if($error) echo '<div class="notice error">'.e($error).'</div>'; ?><form method="post"><?= csrf_field() ?><button class="button primary">Actualizar base de datos</button></form><p><a href="index.php">Volver al sistema</a></p></section></main><?php footer(); ?>
