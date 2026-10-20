<?php
declare(strict_types=1);
function geofence_assign_view(array $u): void {
    if($u['role']!=='user' || !geofences_ready()) return;
    $assigned=array_map('intval',array_column(geo_assigned((int)$u['id']),'id'));
    ?><section class="panel"><h2>Cercos GPS</h2><p>Selecciona las zonas autorizadas para entrada y salida. Sin selección, puede marcar desde cualquier lugar.</p><form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="geofence_assign"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="assignment_revision" value="<?= e(hash('sha256',json_encode($assigned))) ?>"><?php
    foreach(rows('SELECT * FROM ch_geofences WHERE archived=0 ORDER BY name') as $f) echo '<label class="check"><input type="checkbox" name="fences[]" value="'.(int)$f['id'].'" '.(in_array((int)$f['id'],$assigned,true)?'checked':'').'> '.e($f['name']).'</label>';
    ?><label>Motivo de la asignación<textarea name="reason" maxlength="1000" required></textarea></label><button class="button primary">Guardar cercos asignados</button></form><p><a href="index.php?page=geofences">Crear o modificar cercos GPS</a></p></section><?php
}
function geofences_view(): void {
    if(!geofences_ready()) { echo '<section class="panel"><p>Actualiza la base para habilitar los cercos.</p><a href="update.php">Actualizar sistema</a></section>';return; }
    $id=(int)($_GET['fence_id']??0);$f=$id?row('SELECT * FROM ch_geofences WHERE id=? AND archived=0',[$id]):null;
    if($id && !$f) throw new DomainException('Cerco no disponible.');
    ?><a class="back-link" href="index.php?page=settings">← Configuración</a><section class="panel"><h2>Cercos GPS</h2><p>Crea zonas dibujando su contorno. Después asígnalas en Personas → Gestión de persona → Cercos GPS.</p><div class="table-wrap"><table><thead><tr><th>Nombre</th><th>Personas asignadas</th><th>Precisión máxima</th><th></th></tr></thead><tbody><?php foreach(rows('SELECT g.*, (SELECT COUNT(*) FROM ch_user_geofences u WHERE u.fence_id=g.id) AS assigned FROM ch_geofences g WHERE archived=0 ORDER BY name') as $item): ?><tr><td><?= e($item['name']) ?></td><td><?= (int)$item['assigned'] ?></td><td><?= (int)$item['max_accuracy'] ?> m</td><td><a href="index.php?page=geofences&amp;fence_id=<?= (int)$item['id'] ?>">Editar</a></td></tr><?php endforeach; ?></tbody></table></div><p><a class="button secondary" href="index.php?page=geofences">Crear nuevo cerco</a></p></section>
    <section class="panel"><h2><?= $f?'Editar cerco':'Nuevo cerco' ?></h2><p>Acerca el mapa y pulsa los puntos del perímetro en orden. Puedes arrastrar los vértices y deshacer el último. Usa «Terminar dibujo» para revisar antes de guardar.</p>
    <form method="post" class="stack" data-geofence-editor><?= csrf_field() ?><input type="hidden" name="action" value="geofence"><input type="hidden" name="fence_id" value="<?= $id ?>"><input type="hidden" name="revision" value="<?= (int)($f['revision']??0) ?>"><input type="hidden" name="polygon" value="<?= e($f['polygon_json']??'[]') ?>">
    <div class="form-grid"><label>Nombre del cerco<input name="name" maxlength="150" value="<?= e($f['name']??'') ?>" required></label><label>Precisión máxima admitida (metros)<input type="number" name="max_accuracy" min="5" max="1000" value="<?= (int)($f['max_accuracy']??100) ?>" required></label></div>
    <p class="small muted">El punto GPS debe estar dentro del contorno. Esta precisión limita el margen de error informado por el dispositivo; no amplía el cerco.</p>
    <div class="form-grid"><label>Centrar en latitud<input type="number" step="any" min="-85" max="85" data-center-lat value="-33.4489"></label><label>Centrar en longitud<input type="number" step="any" min="-180" max="180" data-center-lng value="-70.6693"></label></div><button type="button" class="button secondary" data-center-map>Centrar mapa</button>
    <div class="fence-map" data-fence-map aria-label="Mapa para dibujar el cerco"></div><div class="actions"><button type="button" class="button secondary" data-draw>Continuar dibujo</button><button type="button" class="button secondary" data-finish>Terminar dibujo</button><button type="button" class="button secondary" data-undo>Deshacer punto</button><button type="button" class="button secondary" data-clear>Limpiar dibujo</button></div>
    <p class="small muted" data-map-status role="status"></p><noscript>Activa JavaScript para dibujar el cerco.</noscript>
    <label>Motivo del cambio<textarea name="reason" maxlength="1000" required></textarea></label><button class="button primary" data-save-fence>Guardar cerco GPS</button></form>
    <?php if($f): ?><details><summary>Archivar cerco</summary><p>Debe estar sin asignaciones. El historial se conserva.</p><form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="geofence"><input type="hidden" name="fence_id" value="<?= $id ?>"><input type="hidden" name="revision" value="<?= (int)$f['revision'] ?>"><input type="hidden" name="archive" value="1"><label>Motivo<input name="reason" maxlength="1000" required></label><button class="button danger">Archivar cerco</button></form></details><?php endif; ?></section><?php
}
function geofence_user_notice(int $uid): void {
    $fences=geo_assigned($uid);
    echo '<p class="small muted">'.($fences?'Marcado permitido dentro de: '.e(implode(', ',array_column($fences,'name'))).'.':'Puedes marcar desde cualquier lugar con ubicación.').'</p>';
}
