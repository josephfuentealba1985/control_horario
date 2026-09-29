<?php
declare(strict_types=1);

function login_action(): void {
    $rut=normalize_rut(text_input('rut',30,true)); $password=$_POST['password']??'';
    if(!is_string($password) || $password==='' || strlen($password)>72) throw new DomainException('RUT o contraseña incorrectos.');
    // The account bucket and the IP bucket limit both targeted and distributed guessing.
    $buckets=[hash('sha256','rut:'.$rut)=>8,hash('sha256','ip:'.($_SERVER['REMOTE_ADDR']??''))=>40]; ksort($buckets);
    db()->beginTransaction();
    try {
        $blocked=false;
        foreach($buckets as $key=>$max) {
            query('INSERT IGNORE INTO ch_login_limits(bucket,attempts,window_start) VALUES(?,0,?)',[$key,utc_now()]);
            $limit=row('SELECT * FROM ch_login_limits WHERE bucket=? FOR UPDATE',[$key]);
            if(strtotime($limit['window_start'].' UTC')<time()-900) { query('UPDATE ch_login_limits SET attempts=0,window_start=? WHERE bucket=?',[utc_now(),$key]); $limit['attempts']=0; }
            if((int)$limit['attempts']>=$max) $blocked=true;
            else query('UPDATE ch_login_limits SET attempts=attempts+1 WHERE bucket=?',[$key]);
        }
        db()->commit();
    } catch(Throwable $e) { if(db()->inTransaction()) db()->rollBack(); throw $e; }
    if($blocked) throw new DomainException('Demasiados intentos. Espera 15 minutos antes de volver a intentar.');
    $user=row('SELECT * FROM ch_users WHERE rut=?',[$rut]);
    $valid=password_verify($password,$user['password_hash']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
    if(!$user || !$user['active'] || !$valid) throw new DomainException('RUT o contraseña incorrectos.');
    session_regenerate_id(true); $_SESSION=['uid'=>(int)$user['id'],'sv'=>(int)$user['session_version'],'started'=>time(),'last_seen'=>time(),'csrf'=>bin2hex(random_bytes(32))];
    query('DELETE FROM ch_login_limits WHERE bucket=?',[hash('sha256','rut:'.$rut)]);
    go($user['must_change']?'index.php?page=profile':'index.php');
}

function punch_action(array $user): void {
    require_role($user,['user']);
    $kind=text_input('kind',3,true);
    if(!in_array($kind,['in','out'],true)) throw new DomainException('Marcado no válido.');
    $coords=[];
    foreach(['latitude'=>[-90,90],'longitude'=>[-180,180],'accuracy'=>[0,9999999]] as $key=>[$min,$max]) {
        $v=$_POST[$key]??null;
        if(!is_scalar($v) || !is_numeric($v) || !is_finite((float)$v) || (float)$v<$min || (float)$v>$max) throw new DomainException('Se necesita una ubicación válida para registrar el marcado. Activa la ubicación e intenta nuevamente.');
        $coords[$key]=(float)$v;
    }
    $comment=text_input('comment',1000);
    // A short-lived server challenge binds a location attempt to this session.
    $challenge=$_SESSION['punch_challenge']??null;
    if(!$challenge || time()-$challenge['issued']>120 || !hash_equals($challenge['token'],text_input('challenge',64,true))) throw new DomainException('La solicitud de ubicación caducó. Vuelve a intentar el marcado.');
    db()->beginTransaction();
    try {
        row('SELECT id FROM ch_users WHERE id=? FOR UPDATE',[$user['id']]);
        $now=utc_now(); $date=local_time($now,'Y-m-d');
        if($date<$user['start_date']) throw new DomainException('Tu fecha de inicio todavía no ha llegado.');
        block_approved_absence((int)$user['id'],$date);
        $a=row('SELECT * FROM ch_attendance WHERE user_id=? AND work_date=? FOR UPDATE',[$user['id'],$date]);
        if($a && $a['voided']) throw new DomainException('El registro de hoy está anulado. Solicita su revisión al administrador.');
        if($kind==='in') {
            if($a) throw new DomainException('Ya tienes un ingreso registrado hoy.');
            query('INSERT INTO ch_attendance(user_id,work_date,clock_in,comment,created_at,updated_at) VALUES(?,?,?,?,?,?)',[$user['id'],$date,$now,$comment,$now,$now]);
            $id=(int)db()->lastInsertId();
        } else {
            if(!$a) throw new DomainException('No tienes un ingreso de hoy. Las jornadas anteriores deben ser corregidas por un analista.');
            if($a['clock_out']) throw new DomainException('Ya tienes una salida registrada hoy.');
            if($now<=$a['clock_in']) throw new DomainException('La salida debe ser posterior al ingreso.');
            $id=(int)$a['id'];
            query('UPDATE ch_attendance SET clock_out=?,updated_at=?,revision=revision+1 WHERE id=?',[$now,$now,$id]);
        }
        query('INSERT INTO ch_evidence(attendance_id,kind,recorded_at,latitude,longitude,accuracy,comment) VALUES(?,?,?,?,?,?,?)',[$id,$kind,$now,$coords['latitude'],$coords['longitude'],$coords['accuracy'],$comment]);
        audit((int)$user['id'],'punch_'.$kind,'attendance',$id,$a,row('SELECT * FROM ch_attendance WHERE id=?',[$id]),'Marcado en línea con ubicación');
        db()->commit(); unset($_SESSION['punch_challenge']);
    } catch(Throwable $e) { if(db()->inTransaction()) db()->rollBack(); throw $e; }
    flash($kind==='in'?'Tu ingreso quedó registrado.':'Tu salida quedó registrada.'); go();
}

function correction_action(array $actor): void {
    require_role($actor,['admin','analyst']);
    $uid=(int)($_POST['user_id']??0); $date=text_input('work_date',10,true);
    $reason=text_input('reason',1000,true); $in=text_input('clock_in',5,true); $out=text_input('clock_out',5);
    if(!date_ok($date) || $date>today()) throw new DomainException('Selecciona una fecha válida que no sea futura.');
    $start=local_to_utc($date,$in); $end=$out!==''?local_to_utc($date,$out):null;
    if(($end!==null && $end<=$start) || $start>utc_now() || ($end!==null && $end>utc_now())) throw new DomainException('Revisa las horas: deben ser del mismo día, no futuras, y la salida posterior al ingreso.');
    $void=(int)isset($_POST['voided']); if($void) require_role($actor,['admin']);
    db()->beginTransaction();
    try {
        $target=row('SELECT * FROM ch_users WHERE id=? FOR UPDATE',[$uid]);
        if(!$target || $date<$target['start_date']) throw new DomainException('La persona o su fecha de inicio no permiten este registro.');
        if(!$void) block_approved_absence($uid,$date);
        $before=row('SELECT * FROM ch_attendance WHERE user_id=? AND work_date=? FOR UPDATE',[$uid,$date]);
        if(($before && (int)$before['revision']!==(int)($_POST['revision']??0)) || (!$before && (int)($_POST['revision']??0)!==0)) throw new DomainException('Otra persona modificó este registro. Vuelve a abrirlo antes de guardar.');
        if($before && $before['voided'] && $actor['role']!=='admin') throw new DomainException('Sólo un administrador puede restablecer un registro anulado.');
        if($before) {
            $id=(int)$before['id'];
            query('UPDATE ch_attendance SET clock_in=?,clock_out=?,voided=?,revision=revision+1,updated_at=? WHERE id=?',[$start,$end,$void,utc_now(),$id]);
        } else {
            if($void) throw new DomainException('No puedes anular un registro inexistente.');
            query("INSERT INTO ch_attendance(user_id,work_date,clock_in,clock_out,source,created_at,updated_at) VALUES(?,?,?,?,'manual',?,?)",[$uid,$date,$start,$end,utc_now(),utc_now()]);
            $id=(int)db()->lastInsertId();
        }
        audit((int)$actor['id'],$void?'void':'correct','attendance',$id,$before,row('SELECT * FROM ch_attendance WHERE id=?',[$id]),$reason);
        db()->commit();
    } catch(Throwable $e) { if(db()->inTransaction()) db()->rollBack(); throw $e; }
    flash('Registro actualizado. El motivo y los valores anteriores quedaron en la trazabilidad.'); go('index.php?page=edit&user_id='.$uid.'&date='.$date);
}

function profile_action(array $user): void {
    $email=text_input('email',180); if($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new DomainException('Ingresa un correo válido.');
    $phone=text_input('phone',40); $address=text_input('address',250);
    $password=null;
    if(($_POST['new_password']??'')!=='' || $user['must_change']) {
        $password=pass_input('new_password');
        if(!password_verify((string)($_POST['current_password']??''),$user['password_hash'])) throw new DomainException('La contraseña actual no es correcta.');
        if(!hash_equals($password,(string)($_POST['password_confirm']??''))) throw new DomainException('Las nuevas contraseñas no coinciden.');
        if(password_verify($password,$user['password_hash'])) throw new DomainException('Elige una contraseña diferente de la actual.');
    }
    db()->beginTransaction();
    try {
        $before=row('SELECT * FROM ch_users WHERE id=? FOR UPDATE',[$user['id']]);
        if((int)$before['session_version']!==(int)$user['session_version']) throw new DomainException('Tu cuenta cambió. Vuelve a iniciar sesión.');
        query('UPDATE ch_users SET email=?,phone=?,address=? WHERE id=?',[$email,$phone,$address,$user['id']]);
        if($password!==null) query('UPDATE ch_users SET password_hash=?,must_change=0,session_version=session_version+1 WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$user['id']]);
        $after=row('SELECT * FROM ch_users WHERE id=?',[$user['id']]);
        audit((int)$user['id'],$password?'profile_password':'profile','user',$user['id'],clean_user($before),clean_user($after),'Actualización por el titular');
        db()->commit(); $_SESSION['sv']=(int)$after['session_version']; session_regenerate_id(true);
    } catch(Throwable $e) { if(db()->inTransaction()) db()->rollBack(); throw $e; }
    flash('Perfil actualizado.'); go('index.php?page=profile');
}

function user_action(array $actor): void {
    require_role($actor,['admin']);
    $id=(int)($_POST['user_id']??0); $rut=normalize_rut(text_input('rut',30,true));
    if(!rut_valid($rut)) throw new DomainException('El RUT no es válido. Revisa su dígito verificador.');
    $name=text_input('name',150,true); $role=text_input('role',10,true); $active=isset($_POST['active'])?1:0;
    if(!in_array($role,['admin','analyst','user','auditor'],true)) throw new DomainException('Perfil no válido.');
    if($role==='auditor' && !security_ready()) throw new DomainException('Actualiza la base de datos antes de crear un Auditor.');
    $email=text_input('email',180); if($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new DomainException('El correo no es válido.');
    $phone=text_input('phone',40); $address=text_input('address',250); $institution=text_input('institution',180); $position=text_input('position',150); $department=text_input('department',150);
    $start=text_input('start_date',10,true); if(!date_ok($start)) throw new DomainException('Fecha de inicio no válida.');
    $reason=text_input('reason',1000,$id>0); $pass=$id===0?pass_input():null;
    $days=$id===0?schedule_input():null;
    db()->beginTransaction();
    try {
        // Serialize role changes to guarantee at least one active administrator.
        row('SELECT id FROM ch_settings WHERE id=1 FOR UPDATE');
        $before=$id?row('SELECT * FROM ch_users WHERE id=? FOR UPDATE',[$id]):null;
        if($id && !$before) throw new DomainException('La persona no existe.');
        if($id===(int)$actor['id'] && (!$active || $role!=='admin')) throw new DomainException('No puedes quitarte tu propio acceso de administrador.');
        if($before && $before['role']==='admin' && $before['active'] && (!$active || $role!=='admin')) {
            if((int)query("SELECT COUNT(*) FROM ch_users WHERE role='admin' AND active=1")->fetchColumn()<=1) throw new DomainException('Debe existir al menos un administrador activo.');
        }
        if(row('SELECT id FROM ch_users WHERE rut=? AND id<>?',[$rut,$id])) throw new DomainException('El RUT ya está registrado.');
        if($before && row('SELECT id FROM ch_attendance WHERE user_id=? AND work_date<? LIMIT 1',[$id,$start])) throw new DomainException('La fecha de inicio no puede dejar registros anteriores fuera del historial.');
        if($before && features_ready() && row('SELECT id FROM ch_absences WHERE user_id=? AND work_date<? LIMIT 1',[$id,$start])) throw new DomainException('La fecha de inicio no puede dejar solicitudes anteriores fuera del historial.');
        $params=[$rut,$name,$email,$phone,$address,$institution,$position,$department,$role,$active,$start];
        if($before) {
            query('UPDATE ch_users SET rut=?,name=?,email=?,phone=?,address=?,institution=?,position=?,department=?,role=?,active=?,start_date=?,session_version=session_version+1 WHERE id=?',[...$params,$id]);
        } else {
            query('INSERT INTO ch_users(rut,name,email,phone,address,institution,position,department,role,active,start_date,password_hash,must_change,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,1,?)',[...$params,password_hash($pass,PASSWORD_DEFAULT),utc_now()]);
            $id=(int)db()->lastInsertId();
            query('INSERT INTO ch_schedules(user_id,effective_from,days_json,created_by,created_at) VALUES(?,?,?,?,?)',[$id,$start,json_encode($days),$actor['id'],utc_now()]);
            audit((int)$actor['id'],'schedule','schedule',$id,null,['effective_from'=>$start,'days'=>$days],'Horario inicial');
        }
        $after=row('SELECT * FROM ch_users WHERE id=?',[$id]);
        audit((int)$actor['id'],$before?'update_user':'create_user','user',$id,$before?clean_user($before):null,clean_user($after),$reason?:'Creación de cuenta');
        db()->commit(); if($id===(int)$actor['id']) $_SESSION['sv']=(int)$after['session_version'];
    } catch(Throwable $e) { if(db()->inTransaction()) db()->rollBack(); throw $e; }
    flash('Persona guardada. Las cuentas nuevas deben cambiar su contraseña al ingresar.'); go('index.php?page=user&user_id='.$id);
}

function schedule_action(array $actor): void {
    require_role($actor,['admin']); $id=(int)($_POST['user_id']??0); $from=text_input('effective_from',10,true); $reason=text_input('reason',1000,true); $days=schedule_input();
    if(!date_ok($from)) throw new DomainException('Vigencia no válida.');
    db()->beginTransaction();
    try {
        $target=row('SELECT * FROM ch_users WHERE id=? FOR UPDATE',[$id]);
        if(!$target || $from<$target['start_date']) throw new DomainException('La vigencia debe comenzar desde la fecha de inicio de la persona.');
        $old=row('SELECT * FROM ch_schedules WHERE user_id=? AND effective_from=?',[$id,$from]);
        query('INSERT INTO ch_schedules(user_id,effective_from,days_json,created_by,created_at) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE days_json=VALUES(days_json),created_by=VALUES(created_by),created_at=VALUES(created_at)',[$id,$from,json_encode($days),$actor['id'],utc_now()]);
        audit((int)$actor['id'],'schedule','schedule',$id,$old,['effective_from'=>$from,'days'=>$days],$reason); db()->commit();
    } catch(Throwable $e) { if(db()->inTransaction()) db()->rollBack(); throw $e; }
    flash('Horario guardado con su fecha de vigencia.'); go('index.php?page=user&user_id='.$id);
}

function reset_action(array $actor): void {
    require_role($actor,['admin']); $id=(int)($_POST['user_id']??0); $p=pass_input(); $reason=text_input('reason',1000,true);
    if($id===(int)$actor['id']) throw new DomainException('Para cambiar tu propia contraseña utiliza Mi perfil.');
    db()->beginTransaction();
    try {
        if(!row('SELECT id FROM ch_users WHERE id=? FOR UPDATE',[$id])) throw new DomainException('La persona no existe.');
        query('UPDATE ch_users SET password_hash=?,must_change=1,session_version=session_version+1 WHERE id=?',[password_hash($p,PASSWORD_DEFAULT),$id]);
        audit((int)$actor['id'],'reset_password','user',$id,null,['must_change'=>true],$reason); db()->commit();
    } catch(Throwable $e) { if(db()->inTransaction()) db()->rollBack(); throw $e; }
    flash('Contraseña restablecida. Se cerraron las sesiones anteriores y se exigirá cambiarla al ingresar.'); go('index.php?page=user&user_id='.$id);
}

function settings_action(array $actor): void {
    require_role($actor,['admin']); $company=text_input('company',180,true); $rut=text_input('company_rut',20); $address=text_input('address',250); $reason=text_input('reason',1000,true);
    $hours=filter_var($_POST['weekly_hours']??null,FILTER_VALIDATE_FLOAT);
    if($hours===false || $hours<=0 || $hours>168) throw new DomainException('La referencia semanal debe ser mayor que 0 y no superar 168 horas.');
    db()->beginTransaction();
    try {
        $before=row('SELECT * FROM ch_settings WHERE id=1 FOR UPDATE');
        query('UPDATE ch_settings SET company=?,company_rut=?,address=?,weekly_hours=? WHERE id=1',[$company,$rut,$address,$hours]);
        audit((int)$actor['id'],'settings','settings',1,$before,row('SELECT * FROM ch_settings WHERE id=1'),$reason); db()->commit();
    } catch(Throwable $e) { if(db()->inTransaction()) db()->rollBack(); throw $e; }
    flash('Configuración actualizada.'); go('index.php?page=settings');
}

function holiday_action(array $actor,bool $remove=false): void {
    require_role($actor,['admin']); $date=text_input('date',10,true); $name=$remove?'':text_input('name',150,true); $reason=text_input('reason',1000,true);
    if(!date_ok($date)) throw new DomainException('Fecha no válida.');
    db()->beginTransaction();
    try {
        row('SELECT id FROM ch_settings WHERE id=1 FOR UPDATE');
        $before=row('SELECT * FROM ch_holidays WHERE date=?',[$date]);
        if($remove) query('DELETE FROM ch_holidays WHERE date=?',[$date]);
        else query('INSERT INTO ch_holidays(date,name) VALUES(?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)',[$date,$name]);
        audit((int)$actor['id'],$remove?'remove_holiday':'holiday','holiday',$date,$before,$remove?null:['date'=>$date,'name'=>$name],$reason); db()->commit();
    } catch(Throwable $e) { if(db()->inTransaction()) db()->rollBack(); throw $e; }
    flash('Calendario actualizado. Las metas de los períodos afectados se recalcularán.'); go('index.php?page=settings');
}
