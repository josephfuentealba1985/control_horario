<?php
declare(strict_types=1);
require_once __DIR__.'/xlsx-reader.php';
function import_person_fingerprint(array $person): string {
    return hash('sha256',json_encode([$person['rut'],$person['start_date'],rows('SELECT effective_from,days_json FROM ch_schedules WHERE user_id=? ORDER BY effective_from',[$person['id']])],JSON_THROW_ON_ERROR));
}
function import_plan(array $parsed,array $person,string $mode): array {
    if(!in_array($mode,['insert','update'],true))throw new DomainException('Selecciona un modo de importación válido.');
    foreach($parsed['rows'] as &$r) {
        $before=row('SELECT * FROM ch_attendance WHERE user_id=? AND work_date=?',[$person['id'],$r['date']]);
        $r['before']=$before; $r['action']=$before?($mode==='insert'?'existing':'update'):'insert';
        if($r['action']==='existing')continue;
        if($before && $before['voided']) {$parsed['errors'][]='Fila '.$r['line'].': el registro está anulado. Revísalo antes de importar.';continue;}
        if(approved_absence((int)$person['id'],$r['date'])){$parsed['errors'][]='Fila '.$r['line'].': existe una inasistencia aprobada. Debe revocarse antes de importar asistencia.';continue;}
        if($before && $before['clock_in']===$r['clock_in'] && $before['clock_out']===$r['clock_out']) {
            $previous=row('SELECT in_lat,in_lng,out_lat,out_lng FROM ch_import_items WHERE attendance_id=? ORDER BY id DESC LIMIT 1',[$before['id']]);
            $same=true;foreach(['in_lat','in_lng','out_lat','out_lng'] as $key)if(($previous[$key]??null)!=$r[$key] || (($previous[$key]??null)===null)!==($r[$key]===null))$same=false;
            if($same)$r['action']='unchanged';
        }
    }unset($r);
    $changed=array_values(array_filter($parsed['rows'],static fn($r)=>in_array($r['action'],['insert','update'],true)));
    $parsed['extend_from']=$changed && $changed[0]['date']<$person['start_date']?$changed[0]['date']:null;
    $parsed['count']=count($changed);return $parsed;
}
function import_preview_action(array $actor): void {
    require_role($actor,['admin']);require_features();unset($_SESSION['attendance_import']);
    $uid=(int)($_POST['user_id']??0);$person=row('SELECT * FROM ch_users WHERE id=?',[$uid]);if(!$person)throw new DomainException('Persona no encontrada.');
    $reason=text_input('reason',800,true);$mode=text_input('mode',20,true);
    $file=$_FILES['attendance_file']??null;
    if(!$file || is_array($file['error']) || $file['error']!==UPLOAD_ERR_OK)throw new DomainException('No se recibió el archivo completo. Selecciona un .xlsx de hasta 5 MB y revisa el límite de subida del hosting.');
    if(!is_uploaded_file($file['tmp_name'])||strtolower(pathinfo($file['name'],PATHINFO_EXTENSION))!=='xlsx')throw new DomainException('Selecciona un archivo Excel .xlsx.');
    $name=basename(str_replace('\\','/',$file['name']));if(strlen($name)>180)throw new DomainException('El nombre del archivo es demasiado largo.');
    $parsed=import_plan(parse_attendance_xlsx($file['tmp_name'],$person),$person,$mode);
    $_SESSION['attendance_import']=$parsed+['user_id'=>$uid,'actor_id'=>(int)$actor['id'],'person_hash'=>import_person_fingerprint($person),'mode'=>$mode,'reason'=>$reason,'filename'=>$name,'file_hash'=>hash_file('sha256',$file['tmp_name']),'created'=>time(),'token'=>bin2hex(random_bytes(24))];
    go('index.php?page=import&user_id='.$uid);
}
function commit_import(array $actor,array $preview,bool $extend): array {
    require_role($actor,['admin']);require_features();
    if((int)$preview['actor_id']!==(int)$actor['id']||time()-$preview['created']>1800||$preview['errors']||!$preview['count'])throw new DomainException('La vista previa caducó, tiene errores o no contiene cambios. Analiza el archivo nuevamente.');
    if($preview['extend_from'] && !$extend)throw new DomainException('Confirma la ampliación de la fecha de inicio y el horario histórico para importar las fechas anteriores.');
    db()->beginTransaction();
    try {
        $person=row('SELECT * FROM ch_users WHERE id=? FOR UPDATE',[$preview['user_id']]);
        if(!$person||!hash_equals($preview['person_hash'],import_person_fingerprint($person)))throw new DomainException('El RUT, la fecha de inicio o el horario cambiaron. Genera otra vista previa.');
        query('INSERT INTO ch_import_batches(user_id,actor_id,filename,file_hash,mode,reason,created_at) VALUES(?,?,?,?,?,?,?)',[$person['id'],$actor['id'],$preview['filename'],$preview['file_hash'],$preview['mode'],$preview['reason'],utc_now()]);$batch=(int)db()->lastInsertId();
        if($preview['extend_from']) {
            $from=$preview['extend_from'];$first=row('SELECT * FROM ch_schedules WHERE user_id=? ORDER BY effective_from LIMIT 1',[$person['id']]);
            query('UPDATE ch_users SET start_date=? WHERE id=?',[$from,$person['id']]);
            audit((int)$actor['id'],'import_start_date','user',$person['id'],['start_date'=>$person['start_date']],['start_date'=>$from],$preview['reason']);
            if(!$first||$first['effective_from']>$from) {
                $days=$first['days_json']??'[]';query('INSERT INTO ch_schedules(user_id,effective_from,days_json,created_by,created_at) VALUES(?,?,?,?,?)',[$person['id'],$from,$days,$actor['id'],utc_now()]);
                audit((int)$actor['id'],'schedule','schedule',$person['id'],null,['effective_from'=>$from,'days'=>json_decode($days,true)],'Horario inicial aplicado al período histórico confirmado en importación #'.$batch);
            }
        }
        $counts=['insert'=>0,'update'=>0];
        foreach($preview['rows'] as $r) {
            if(!in_array($r['action'],['insert','update'],true))continue;
            block_approved_absence((int)$person['id'],$r['date']);
            $before=row('SELECT * FROM ch_attendance WHERE user_id=? AND work_date=? FOR UPDATE',[$person['id'],$r['date']]);
            if(($before['id']??null)!==($r['before']['id']??null)||($before['revision']??null)!==($r['before']['revision']??null))throw new DomainException('Cambió el registro del '.date_label($r['date']).'. No se importó ninguna fila. Genera una nueva vista previa.');
            if($before) { $id=(int)$before['id'];query('UPDATE ch_attendance SET clock_in=?,clock_out=?,revision=revision+1,updated_at=? WHERE id=?',[$r['clock_in'],$r['clock_out'],utc_now(),$id]); }
            else {query("INSERT INTO ch_attendance(user_id,work_date,clock_in,clock_out,comment,source,created_at,updated_at) VALUES(?,?,?,?,?,'manual',?,?)",[$person['id'],$r['date'],$r['clock_in'],$r['clock_out'],'',utc_now(),utc_now()]);$id=(int)db()->lastInsertId();}
            query('INSERT INTO ch_import_items(batch_id,attendance_id,excel_row,clock_in,clock_out,in_lat,in_lng,out_lat,out_lng,action) VALUES(?,?,?,?,?,?,?,?,?,?)',[$batch,$id,$r['line'],$r['clock_in'],$r['clock_out'],$r['in_lat'],$r['in_lng'],$r['out_lat'],$r['out_lng'],$r['action']]);
            $after=row('SELECT * FROM ch_attendance WHERE id=?',[$id]);$after['import']=['batch'=>$batch,'file_hash'=>$preview['file_hash'],'row'=>$r['line'],'in_lat'=>$r['in_lat'],'in_lng'=>$r['in_lng'],'out_lat'=>$r['out_lat'],'out_lng'=>$r['out_lng']];
            audit((int)$actor['id'],'import_'.$r['action'],'attendance',$id,$before,$after,$preview['reason']);$counts[$r['action']]++;
        }
        audit((int)$actor['id'],'import_batch','import',$batch,null,$counts+['user_id'=>$person['id'],'filename'=>$preview['filename'],'sha256'=>$preview['file_hash']],$preview['reason']);db()->commit();return $counts;
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function import_confirm_action(array $actor): void {
    require_role($actor,['admin']);$preview=$_SESSION['attendance_import']??null;
    if(!$preview||!hash_equals($preview['token'],text_input('preview_token',48,true)))throw new DomainException('No hay una vista previa vigente. Selecciona nuevamente el archivo.');
    $counts=commit_import($actor,$preview,isset($_POST['extend_history']));unset($_SESSION['attendance_import']);
    flash('Importación completada: '.$counts['insert'].' nuevos y '.$counts['update'].' actualizados.');go('index.php?page=import&user_id='.$preview['user_id']);
}
