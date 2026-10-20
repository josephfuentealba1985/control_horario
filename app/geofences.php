<?php
declare(strict_types=1);
function geofences_ready(): bool {
    try { return (bool)query('SELECT version FROM ch_schema_versions WHERE version=4')->fetchColumn(); }
    catch(PDOException $e) { if($e->getCode()==='42S02') return false; throw $e; }
}
function geo_cross(array $a,array $b,array $c): float {
    return ($b[0]-$a[0])*($c[1]-$a[1])-($b[1]-$a[1])*($c[0]-$a[0]);
}
function geo_on_segment(array $a,array $b,array $p): bool {
    return abs(geo_cross($a,$b,$p))<1e-12 && $p[0]>=min($a[0],$b[0])-1e-12 && $p[0]<=max($a[0],$b[0])+1e-12 && $p[1]>=min($a[1],$b[1])-1e-12 && $p[1]<=max($a[1],$b[1])+1e-12;
}
function geo_intersects(array $a,array $b,array $c,array $d): bool {
    $x=geo_cross($a,$b,$c);$y=geo_cross($a,$b,$d);$z=geo_cross($c,$d,$a);$w=geo_cross($c,$d,$b);
    return (($x>0 && $y<0 || $x<0 && $y>0) && ($z>0 && $w<0 || $z<0 && $w>0))
        || geo_on_segment($a,$b,$c) || geo_on_segment($a,$b,$d) || geo_on_segment($c,$d,$a) || geo_on_segment($c,$d,$b);
}
function geo_polygon(string $json): array {
    try { $p=json_decode($json,true,32,JSON_THROW_ON_ERROR); } catch(JsonException $e) { throw new DomainException('Dibuja un cerco válido en el mapa.'); }
    if(!is_array($p) || !array_is_list($p) || count($p)<3 || count($p)>200) throw new DomainException('El cerco necesita entre 3 y 200 vértices.');
    foreach($p as &$v) {
        if(!is_array($v) || !array_is_list($v) || count($v)!==2 || !is_numeric($v[0]) || !is_numeric($v[1]) || !is_finite((float)$v[0]) || !is_finite((float)$v[1]) || abs((float)$v[0])>85 || abs((float)$v[1])>180) throw new DomainException('Las coordenadas del cerco no son válidas.');
        $v=[round((float)$v[0],7),round((float)$v[1],7)];
    } unset($v);
    $n=count($p);$area=0;
    if(max(array_column($p,1))-min(array_column($p,1))>180) throw new DomainException('El cerco no puede cruzar el meridiano de cambio de fecha.');
    for($i=0;$i<$n;$i++) {
        if($p[$i]===$p[($i+1)%$n]) throw new DomainException('El cerco tiene vértices repetidos.');
        $a=[$p[$i][0]-$p[0][0],$p[$i][1]-$p[0][1]];$b=[$p[($i+1)%$n][0]-$p[0][0],$p[($i+1)%$n][1]-$p[0][1]];
        $area+=$a[0]*$b[1]-$b[0]*$a[1];
        for($j=$i+1;$j<$n;$j++) {
            if($j===$i+1 || $i===0 && $j===$n-1) continue;
            if(geo_intersects($p[$i],$p[($i+1)%$n],$p[$j],$p[($j+1)%$n])) throw new DomainException('Los lados del cerco no pueden cruzarse ni tocarse entre sí.');
        }
    }
    if(abs($area)<1e-12) throw new DomainException('El cerco debe encerrar una superficie.');
    return $p;
}
function geo_contains(array $polygon,float $lat,float $lng): bool {
    $inside=false;$point=[$lat,$lng];$n=count($polygon);
    for($i=0,$j=$n-1;$i<$n;$j=$i++) {
        $a=$polygon[$i];$b=$polygon[$j];
        if(geo_on_segment($a,$b,$point)) return true;
        if(($a[1]>$lng)!==($b[1]>$lng) && $lat<($b[0]-$a[0])*($lng-$a[1])/($b[1]-$a[1])+$a[0]) $inside=!$inside;
    }
    return $inside;
}
function geo_assigned(int $uid): array {
    return geofences_ready()?rows('SELECT g.* FROM ch_geofences g JOIN ch_user_geofences u ON u.fence_id=g.id WHERE u.user_id=? ORDER BY g.id',[$uid]):[];
}
function geo_validate_punch(int $uid,array $coords): array {
    $fences=geo_assigned($uid);
    if(!$fences) return ['mode'=>'anywhere'];
    $inside=false;
    foreach($fences as $fence) {
        if($fence['archived']) continue;
        if(geo_contains(json_decode($fence['polygon_json'],true,512,JSON_THROW_ON_ERROR),$coords['latitude'],$coords['longitude'])) {
            $inside=true;
            if($coords['accuracy']<=$fence['max_accuracy']) return ['mode'=>'fence','id'=>(int)$fence['id'],'name'=>$fence['name'],'revision'=>(int)$fence['revision'],'max_accuracy'=>(int)$fence['max_accuracy'],'accuracy'=>$coords['accuracy']];
        }
    }
    throw new DomainException($inside?'La ubicación no tiene suficiente precisión para este cerco. Acércate a un lugar despejado y vuelve a intentar.':'Estás fuera de los cercos GPS autorizados. Acércate a una zona asignada o contacta al analista.');
}
function geofence_action(array $actor): void {
    require_role($actor,['admin']);
    if(!geofences_ready()) throw new DomainException('Actualiza la base de datos para habilitar los cercos GPS.');
    $id=(int)($_POST['fence_id']??0);$reason=text_input('reason',1000,true);$archive=isset($_POST['archive']);
    $name=$archive?'':text_input('name',150,true);
    $polygon=$archive?[]:geo_polygon(text_input('polygon',16000,true));
    $accuracy=filter_var($_POST['max_accuracy']??100,FILTER_VALIDATE_INT);
    if(!$archive && ($accuracy===false || $accuracy<5 || $accuracy>1000)) throw new DomainException('La precisión máxima debe estar entre 5 y 1000 metros.');
    db()->beginTransaction();
    try {
        row('SELECT id FROM ch_settings WHERE id=1 FOR UPDATE');
        $before=$id?row('SELECT * FROM ch_geofences WHERE id=? FOR UPDATE',[$id]):null;
        if($id && (!$before || $before['archived'])) throw new DomainException('Cerco no disponible.');
        if($before && (int)$before['revision']!==(int)($_POST['revision']??0)) throw new DomainException('Otra persona cambió el cerco. Vuelve a abrirlo.');
        if($archive) {
            if(!$before) throw new DomainException('Selecciona un cerco existente.');
            if(row('SELECT user_id FROM ch_user_geofences WHERE fence_id=? LIMIT 1',[$id])) throw new DomainException('No puedes archivar un cerco asignado. Revisa primero las asignaciones de las personas.');
            query('UPDATE ch_geofences SET archived=1,revision=revision+1,updated_at=? WHERE id=?',[utc_now(),$id]);
        } elseif($before) query('UPDATE ch_geofences SET name=?,polygon_json=?,max_accuracy=?,revision=revision+1,updated_at=? WHERE id=?',[$name,json_encode($polygon),$accuracy,utc_now(),$id]);
        else {
            query('INSERT INTO ch_geofences(name,polygon_json,max_accuracy,created_at,updated_at) VALUES(?,?,?,?,?)',[$name,json_encode($polygon),$accuracy,utc_now(),utc_now()]);
            $id=(int)db()->lastInsertId();
        }
        audit((int)$actor['id'],'geofence','geofence',$id,$before,row('SELECT * FROM ch_geofences WHERE id=?',[$id]),$reason);
        db()->commit();
    } catch(Throwable $e) { if(db()->inTransaction()) db()->rollBack();throw $e; }
    flash('Cerco GPS guardado.');go('index.php?page=geofences');
}
function geofence_assign_action(array $actor): void {
    require_role($actor,['admin']);
    if(!geofences_ready()) throw new DomainException('Actualiza la base de datos para habilitar los cercos GPS.');
    $uid=(int)($_POST['user_id']??0);$reason=text_input('reason',1000,true);$raw=$_POST['fences']??[];
    if(!is_array($raw) || count($raw)>500) throw new DomainException('Asignación no válida.');
    $ids=[];
    foreach($raw as $v) { if(!is_scalar($v) || !ctype_digit((string)$v) || (int)$v<1) throw new DomainException('Cerco no válido.');$ids[]=(int)$v; }
    $ids=array_values(array_unique($ids));sort($ids);
    db()->beginTransaction();
    try {
        row('SELECT id FROM ch_settings WHERE id=1 FOR UPDATE');
        $u=row('SELECT id,role FROM ch_users WHERE id=? FOR UPDATE',[$uid]);
        if(!$u || $u['role']!=='user') throw new DomainException('Sólo se asignan cercos a cuentas con perfil Usuario.');
        $before=array_map('intval',array_column(geo_assigned($uid),'id'));
        if(!hash_equals(hash('sha256',json_encode($before)),text_input('assignment_revision',64,true))) throw new DomainException('La asignación cambió. Vuelve a abrir la persona.');
        foreach($ids as $id) if(!row('SELECT id FROM ch_geofences WHERE id=? AND archived=0',[$id])) throw new DomainException('Uno de los cercos ya no está disponible.');
        query('DELETE FROM ch_user_geofences WHERE user_id=?',[$uid]);
        foreach($ids as $id) query('INSERT INTO ch_user_geofences(user_id,fence_id) VALUES(?,?)',[$uid,$id]);
        audit((int)$actor['id'],'geofence_assignment','user',$uid,['fences'=>$before],['fences'=>$ids],$reason);
        db()->commit();
    } catch(Throwable $e) { if(db()->inTransaction()) db()->rollBack();throw $e; }
    flash($ids?'Cercos asignados. Se validarán en ingreso y salida.':'La persona puede marcar desde cualquier lugar, con ubicación obligatoria.');
    go('index.php?page=user&user_id='.$uid);
}
