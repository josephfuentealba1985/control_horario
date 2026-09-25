<?php
declare(strict_types=1);
function features_ready(): bool {
    static $ready;
    if($ready!==null) return $ready;
    try { return $ready=(bool)query('SELECT version FROM ch_schema_versions WHERE version=2')->fetchColumn(); }
    catch(PDOException $e) { if($e->getCode()==='42S02') return $ready=false; throw $e; }
}
function require_features(): void { if(!features_ready()) throw new DomainException('El administrador debe completar la actualización de la base de datos desde Actualizar sistema.'); }
function absence_categories(): array { return ['medical'=>'LICENCIA O PERMISO MÉDICO','legal'=>'FERIADO LEGAL','administrative'=>'ADMINISTRATIVO']; }
function absence_status(string $status): string { return ['pending'=>'Pendiente','approved'=>'Aprobada','rejected'=>'Rechazada','cancelled'=>'Cancelada','revoked'=>'Revocada'][$status]??$status; }
function approved_absence(int $user,string $date): ?array { return features_ready()?row("SELECT * FROM ch_absences WHERE user_id=? AND work_date=? AND status='approved'",[$user,$date]):null; }
function block_approved_absence(int $user,string $date): void {
    if(approved_absence($user,$date)) throw new DomainException('Existe una inasistencia aprobada para ese día. Un administrador debe revocarla antes de registrar asistencia.');
}
function absence_request_action(array $actor): void {
    require_features(); $from=text_input('from',10,true); $to=text_input('to',10)?:$from; $category=text_input('category',20,true); $note=text_input('note',1000);
    if(!isset(absence_categories()[$category])) throw new DomainException('Selecciona un motivo de la lista.');
    if(!date_ok($from)||!date_ok($to)||$from>$to||$to>today()||$from<$actor['start_date']||(new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days>89) throw new DomainException('Selecciona hasta 90 días, desde tu fecha de inicio y sin fechas futuras.');
    db()->beginTransaction();
    try {
        $lockedUser=row('SELECT * FROM ch_users WHERE id=? FOR UPDATE',[$actor['id']]);
        if($from<$lockedUser['start_date'])throw new DomainException('La fecha de inicio de tu cuenta cambió. Actualiza la página.');
        $count=0;
        foreach(new DatePeriod(new DateTimeImmutable($from),new DateInterval('P1D'),(new DateTimeImmutable($to))->modify('+1 day')) as $day) {
            $date=$day->format('Y-m-d');
            if(row('SELECT id FROM ch_attendance WHERE user_id=? AND work_date=? AND voided=0',[$actor['id'],$date])) throw new DomainException('Ya existe asistencia el '.date_label($date).'. Solicita la corrección del marcado si corresponde.');
            $before=row('SELECT * FROM ch_absences WHERE user_id=? AND work_date=? FOR UPDATE',[$actor['id'],$date]);
            if($before && in_array($before['status'],['pending','approved'],true)) throw new DomainException('Ya existe una solicitud pendiente o aprobada el '.date_label($date).'.');
            if($before) { $id=(int)$before['id']; query("UPDATE ch_absences SET category=?,note=?,status='pending',reviewed_by=NULL,review_note='',reviewed_at=NULL,revision=revision+1,updated_at=? WHERE id=?",[$category,$note,utc_now(),$id]); }
            else { query('INSERT INTO ch_absences(user_id,work_date,category,note,created_at,updated_at) VALUES(?,?,?,?,?,?)',[$actor['id'],$date,$category,$note,utc_now(),utc_now()]); $id=(int)db()->lastInsertId(); }
            audit((int)$actor['id'],'absence_request','absence',$id,$before,row('SELECT * FROM ch_absences WHERE id=?',[$id]),'Solicitud del titular: '.absence_categories()[$category]); $count++;
        }
        db()->commit();
    } catch(Throwable $e) { if(db()->inTransaction()) db()->rollBack(); throw $e; }
    flash($count.' solicitud(es) enviada(s) a la bandeja del analista y administrador.'); go('index.php?page=absences');
}
function absence_decision_action(array $actor): void {
    require_features(); $id=(int)($_POST['absence_id']??0); $decision=text_input('decision',20,true); $reason=text_input('reason',1000,true);
    if(!in_array($decision,['approved','rejected','cancelled','revoked'],true)) throw new DomainException('Decisión no válida.');
    if($decision!=='cancelled') require_role($actor,$decision==='revoked'?['admin']:['admin','analyst']);
    $ref=row('SELECT user_id FROM ch_absences WHERE id=?',[$id]); if(!$ref) throw new DomainException('Solicitud no encontrada.');
    db()->beginTransaction();
    try {
        row('SELECT id FROM ch_users WHERE id=? FOR UPDATE',[$ref['user_id']]);
        $before=row('SELECT * FROM ch_absences WHERE id=? FOR UPDATE',[$id]);
        if((int)$before['revision']!==(int)($_POST['revision']??0)) throw new DomainException('La solicitud cambió. Actualiza la página antes de resolverla.');
        if($decision==='cancelled') {
            if((int)$before['user_id']!==(int)$actor['id'] || $before['status']!=='pending') throw new DomainException('Sólo puedes cancelar tus solicitudes pendientes.');
        } elseif($decision==='revoked') {
            if($before['status']!=='approved') throw new DomainException('Sólo se puede revocar una solicitud aprobada.');
        } else {
            if((int)$before['user_id']===(int)$actor['id']) throw new DomainException('Otro analista o administrador debe revisar tu propia solicitud.');
            if($before['status']!=='pending') throw new DomainException('La solicitud ya fue resuelta.');
        }
        if($decision==='approved' && row('SELECT id FROM ch_attendance WHERE user_id=? AND work_date=? AND voided=0',[$before['user_id'],$before['work_date']])) throw new DomainException('Ya existe asistencia para esa fecha. Revisa el marcado antes de aprobar.');
        query('UPDATE ch_absences SET status=?,reviewed_by=?,review_note=?,reviewed_at=?,revision=revision+1,updated_at=? WHERE id=?',[$decision,$actor['id'],$reason,utc_now(),utc_now(),$id]);
        audit((int)$actor['id'],'absence_'.$decision,'absence',$id,$before,row('SELECT * FROM ch_absences WHERE id=?',[$id]),$reason); db()->commit();
    } catch(Throwable $e) { if(db()->inTransaction()) db()->rollBack(); throw $e; }
    flash('Solicitud '.strtolower(absence_status($decision)).'. La meta semanal se mantiene.'); go('index.php?page='.($decision==='cancelled'?'absences':'requests'));
}
function map_view(float $lat,float $lng,string $label): void {
    if(!is_finite($lat)||!is_finite($lng)||abs($lat)>90||abs($lng)>180) return;
    $latText=number_format($lat,7,'.',''); $lngText=number_format($lng,7,'.','');
    $bbox=implode(',',array_map(static fn($n)=>number_format($n,7,'.',''),[max(-180,$lng-.004),max(-90,$lat-.003),min(180,$lng+.004),min(90,$lat+.003)]));
    $url='https://www.openstreetmap.org/export/embed.html?'.http_build_query(['bbox'=>$bbox,'layer'=>'mapnik','marker'=>$latText.','.$lngText]);
    echo '<div class="location-map"><iframe title="'.e($label).'" src="'.e($url).'" loading="lazy" referrerpolicy="no-referrer"></iframe><small>Mapa: <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">© OpenStreetMap contributors</a></small></div>';
}
