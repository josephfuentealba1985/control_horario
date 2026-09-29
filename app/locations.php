<?php
declare(strict_types=1);

// Corrections are immutable audit events; original device and file evidence is retained.
function attendance_locations(int $id): array {
    $locations=['in'=>['lat'=>null,'lng'=>null],'out'=>['lat'=>null,'lng'=>null]];
    if(features_ready()) {
        $item=row('SELECT * FROM ch_import_items WHERE attendance_id=? ORDER BY id DESC LIMIT 1',[$id]);
        if($item) foreach(['in','out'] as $kind) $locations[$kind]=['lat'=>$item[$kind.'_lat'],'lng'=>$item[$kind.'_lng']];
    }
    foreach(rows('SELECT kind,latitude,longitude FROM ch_evidence WHERE attendance_id=?',[$id]) as $item)
        $locations[$item['kind']]=['lat'=>$item['latitude'],'lng'=>$item['longitude']];
    $correction=row("SELECT after_json FROM ch_audit WHERE entity='attendance' AND entity_id=? AND action='coordinates' ORDER BY id DESC LIMIT 1",[(string)$id]);
    if($correction) $locations=json_decode($correction['after_json'],true,512,JSON_THROW_ON_ERROR);
    return $locations;
}

function coordinates_action(array $actor): void {
    require_role($actor,['admin']);
    $id=(int)($_POST['attendance_id']??0);
    $reason=text_input('reason',1000,true);
    $locations=[];
    foreach(['in','out'] as $kind) {
        $lat=text_input($kind.'_lat',30); $lng=text_input($kind.'_lng',30);
        if(($lat==='')!==($lng==='')) throw new DomainException('Completa latitud y longitud juntas, o deja ambas vacías.');
        if($lat!=='' && (!is_numeric($lat)||!is_numeric($lng)||!is_finite((float)$lat)||!is_finite((float)$lng)||abs((float)$lat)>90||abs((float)$lng)>180))
            throw new DomainException('Coordenadas inválidas: latitud entre -90 y 90; longitud entre -180 y 180. Utiliza punto decimal.');
        $locations[$kind]=['lat'=>$lat===''?null:number_format((float)$lat,7,'.',''),'lng'=>$lng===''?null:number_format((float)$lng,7,'.','')];
    }
    db()->beginTransaction();
    try {
        $record=row('SELECT * FROM ch_attendance WHERE id=? FOR UPDATE',[$id]);
        if(!$record) throw new DomainException('Registro no encontrado.');
        if((int)$record['revision']!==(int)($_POST['revision']??0)) throw new DomainException('Otra persona modificó este registro. Vuelve a abrirlo antes de guardar.');
        $before=attendance_locations($id);
        if($before!=$locations) {
            audit((int)$actor['id'],'coordinates','attendance',$id,$before,$locations,$reason);
            query('UPDATE ch_attendance SET revision=revision+1,updated_at=? WHERE id=?',[utc_now(),$id]);
        }
        db()->commit();
    } catch(Throwable $error) { if(db()->inTransaction()) db()->rollBack(); throw $error; }
    flash('Coordenadas guardadas. La modificación quedó registrada en la trazabilidad.');
    go('index.php?page=edit&user_id='.(int)$record['user_id'].'&date='.$record['work_date']);
}

function locations_view(array $actor,?array $record): void {
    echo '<h2>Ubicaciones del registro</h2>';
    if(!$record) { echo '<p class="empty">Guarda el registro para añadir sus coordenadas.</p>'; return; }
    $locations=attendance_locations((int)$record['id']);
    foreach(['in'=>'Ingreso','out'=>'Salida'] as $kind=>$label) {
        $point=$locations[$kind];
        echo '<article class="evidence"><h3>'.e($label).' · '.e(local_time($record['clock_'.$kind],'d/m/Y H:i:s')).'</h3>';
        if($point['lat']===null) echo '<p class="small muted">Sin coordenadas registradas.</p>';
        else {
            echo '<p class="small">'.e($point['lat'].', '.$point['lng']).'</p>';
            map_view((float)$point['lat'],(float)$point['lng'],'Ubicación de '.$label);
            $url='https://www.openstreetmap.org/?'.http_build_query(['mlat'=>$point['lat'],'mlon'=>$point['lng']]).'#map=16/'.$point['lat'].'/'.$point['lng'];
            echo '<a href="'.e($url).'" target="_blank" rel="noopener noreferrer">Abrir ubicación en mapa ↗</a>';
        }
        echo '</article>';
    }
    punch_context_view((int)$record['id']);
    if($actor['role']!=='admin') return;
    echo '<hr><h3>Modificar coordenadas</h3><p class="small muted">Los cambios requieren un motivo y quedan en el historial. Para quitar una ubicación, deja ambos campos vacíos.</p><form method="post" class="stack">'.csrf_field().'<input type="hidden" name="action" value="coordinates"><input type="hidden" name="attendance_id" value="'.(int)$record['id'].'"><input type="hidden" name="revision" value="'.(int)$record['revision'].'">';
    foreach(['in'=>'Ingreso','out'=>'Salida'] as $kind=>$label) {
        echo '<fieldset class="coordinate-fields"><legend>'.e($label).'</legend><div class="form-grid">';
        foreach(['lat'=>['Latitud',90],'lng'=>['Longitud',180]] as $field=>[$title,$max])
            echo '<label>'.e($title).'<input type="number" step="0.0000001" min="-'.$max.'" max="'.$max.'" name="'.$kind.'_'.$field.'" value="'.e($locations[$kind][$field]??'').'"></label>';
        echo '</div></fieldset>';
    }
    echo '<label>Motivo del cambio de coordenadas<textarea name="reason" maxlength="1000" rows="3" required></textarea></label><button class="button primary">Guardar coordenadas</button></form>';
}

function user_locations_view(array $actor,?string $date=null): void {
    require_role($actor,['user']);
    $date=$date??(is_string($_GET['date']??null)?$_GET['date']:today());
    if(!date_ok($date) || $date>today()) throw new DomainException('Selecciona una fecha válida que no sea futura.');
    // Always scope to the signed-in user, never to a supplied person or record ID.
    $record=row('SELECT * FROM ch_attendance WHERE user_id=? AND work_date=?',[$actor['id'],$date]);
    echo '<section class="panel user-locations"><h2>Mis ubicaciones · '.e(date_label($date)).'</h2>';
    if(!$record) { echo '<p class="small muted">Todavía no tienes un marcado registrado para este día.</p></section>'; return; }
    if($record['voided']) echo '<p class="notice warning">Este registro está anulado.</p>';
    $locations=attendance_locations((int)$record['id']);
    echo '<p class="small muted">Consulta las coordenadas guardadas de cada marcado. Esta consulta no solicita tu ubicación actual.</p><div class="location-grid">';
    foreach(['in'=>'Entrada','out'=>'Salida'] as $kind=>$label) {
        $point=$locations[$kind]; $time=$record['clock_'.$kind];
        echo '<details><summary>'.e($label.' · '.local_time($time,'H:i:s')).'</summary>';
        if(!$time) echo '<p class="small muted">Aún no has registrado la '.strtolower($label).'.</p>';
        elseif($point['lat']===null || $point['lng']===null) echo '<p class="small muted">Sin coordenadas registradas.</p>';
        else {
            echo '<p class="small">Latitud: '.e($point['lat']).'<br>Longitud: '.e($point['lng']).'</p>';
            map_view((float)$point['lat'],(float)$point['lng'],'Mi ubicación de '.$label);
            $url='https://www.openstreetmap.org/?'.http_build_query(['mlat'=>$point['lat'],'mlon'=>$point['lng']]).'#map=16/'.$point['lat'].'/'.$point['lng'];
            echo '<a href="'.e($url).'" target="_blank" rel="noopener noreferrer">Abrir ubicación en mapa ↗</a>';
        }
        echo '</details>';
    }
    echo '</div></section>';
}

function attendance_comment(?array $record): string {
    $comment=(string)($record['comment']??'');
    return !empty($record['imported']) && str_starts_with($comment,'Importado desde Excel: ')?'':$comment;
}

function audit_display_json(?string $json): ?string {
    if(!$json) return $json;
    $data=json_decode($json,true,512,JSON_THROW_ON_ERROR);
    if(!is_array($data)) return $json;
    if(isset($data['comment']) && str_starts_with((string)$data['comment'],'Importado desde Excel: ')) $data['comment']='';
    if(isset($data['import'])) {
        $data['coordinates']=array_intersect_key($data['import'],array_flip(['in_lat','in_lng','out_lat','out_lng']));
        unset($data['import']);
    }
    unset($data['filename'],$data['sha256']);
    return json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
}
