<?php
declare(strict_types=1);
require_once __DIR__.'/security.php';

function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function utc_now(): string { return gmdate('Y-m-d H:i:s'); }
function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $c = require dirname(__DIR__) . '/config.php';
        $pdo = new PDO('mysql:host='.$c['host'].';port='.$c['port'].';dbname='.$c['database'].';charset=utf8mb4', $c['username'], $c['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}
function query(string $sql, array $params=[]): PDOStatement { $s=db()->prepare($sql); $s->execute($params); return $s; }
function row(string $sql, array $params=[]): ?array { return query($sql,$params)->fetch() ?: null; }
function rows(string $sql, array $params=[]): array { return query($sql,$params)->fetchAll(); }
function settings(): array { static $s; return $s ??= row('SELECT * FROM ch_settings WHERE id=1') ?? throw new RuntimeException('Instalación incompleta.'); }
function zone(): DateTimeZone { return new DateTimeZone(settings()['timezone']); }
function today(): string { return (new DateTimeImmutable('now',zone()))->format('Y-m-d'); }
function local_time(?string $utc, string $format='H:i'): string { return $utc ? (new DateTimeImmutable($utc,new DateTimeZone('UTC')))->setTimezone(zone())->format($format) : '—'; }
function date_ok(string $d): bool { $v=DateTimeImmutable::createFromFormat('!Y-m-d',$d); return $v && $v->format('Y-m-d')===$d; }
function date_label(string $d): string { return date_ok($d) ? substr($d,8,2).'/'.substr($d,5,2).'/'.substr($d,0,4) : $d; }
function normalize_rut(string $r): string { return strtoupper(preg_replace('/[.\s-]/','',$r) ?? ''); }
function rut_valid(string $rut): bool {
    $r=normalize_rut($rut);
    if (!preg_match('/^[1-9][0-9]{5,7}[0-9K]$/',$r)) return false;
    $body=substr($r,0,-1); $sum=0; $factor=2;
    for($i=strlen($body)-1;$i>=0;$i--) { $sum+=(int)$body[$i]*$factor; $factor=$factor===7?2:$factor+1; }
    $v=11-($sum%11); return substr($r,-1)===($v===11?'0':($v===10?'K':(string)$v));
}
function rut_label(string $r): string { return number_format((int)substr($r,0,-1),0,',','.').'-'.substr($r,-1); }
function text_input(string $key, int $max, bool $required=false): string {
    $v=$_POST[$key]??''; if(!is_string($v)) throw new DomainException('Dato no válido.'); $v=trim($v);
    $labels=['reason'=>'motivo','name'=>'nombre','rut'=>'RUT','company'=>'empresa','clock_in'=>'ingreso','work_date'=>'fecha','effective_from'=>'vigencia','start_date'=>'fecha de inicio'];
    if (($required && $v==='') || strlen($v)>$max) throw new DomainException('Revisa el campo '.($labels[$key]??str_replace('_',' ',$key)).'.');
    return $v;
}
function pass_input(string $key='password'): string {
    $p=$_POST[$key]??'';
    if(!is_string($p) || !preg_match('//u',$p) || preg_match_all('/./us',$p)<12 || strlen($p)>72) throw new DomainException('La contraseña debe tener al menos 12 caracteres y ocupar como máximo 72 bytes.');
    return $p;
}
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.e(csrf()).'">'; }
function check_csrf(): void { if(!is_string($_POST['csrf']??null) || !hash_equals(csrf(),$_POST['csrf'])) throw new DomainException('La sesión del formulario caducó. Actualiza la página e inténtalo nuevamente.'); }
function flash(string $message, string $type='success'): void { $_SESSION['flash']=['message'=>$message,'type'=>$type]; }
function go(string $url='index.php'): never { header('Location: '.$url, true,303); exit; }
function current_user(): ?array {
    if(empty($_SESSION['uid'])) return null;
    $u=row('SELECT * FROM ch_users WHERE id=?',[(int)$_SESSION['uid']]);
    if(!$u || !$u['active'] || (int)$u['session_version']!==($_SESSION['sv']??0)) { unset($_SESSION['uid'],$_SESSION['sv']); return null; }
    return $u;
}
function require_role(array $user, array $roles): void { if(!in_array($user['role'],$roles,true)) throw new DomainException('No tienes permiso para realizar esta acción.'); }
function clean_user(array $u): array { unset($u['password_hash'],$u['session_version']); return $u; }
function audit(?int $actor,string $action,string $entity,string|int $id, mixed $before,mixed $after,string $reason): void {
    query('INSERT INTO ch_audit(actor_id,action,entity,entity_id,before_json,after_json,reason,created_at) VALUES(?,?,?,?,?,?,?,?)',[$actor,$action,$entity,(string)$id,$before===null?null:json_encode($before,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$after===null?null:json_encode($after,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$reason,utc_now()]);
    record_request_context((int)db()->lastInsertId());
}
function default_days(): array { return ['1'=>['08:00','17:00'],'2'=>['08:00','17:00'],'3'=>['08:00','17:00'],'4'=>['08:00','17:00'],'5'=>['08:00','16:00']]; }
function duration_minutes(?string $in,?string $out): int { return $in && $out ? max(0,(int)floor((strtotime($out.' UTC')-strtotime($in.' UTC'))/60)) : 0; }
function hours(int $minutes): string { return ($minutes<0?'−':'').intdiv(abs($minutes),60).':'.str_pad((string)(abs($minutes)%60),2,'0',STR_PAD_LEFT); }
function clock_minutes(string $v): int { return (int)substr($v,0,2)*60+(int)substr($v,3,2); }
function schedule_days(int $user,string $date): array { $s=row('SELECT days_json FROM ch_schedules WHERE user_id=? AND effective_from<=? ORDER BY effective_from DESC LIMIT 1',[$user,$date]); return $s ? json_decode($s['days_json'],true,512,JSON_THROW_ON_ERROR) : []; }
function schedule_input(): array {
    if(($_POST['schedule_mode']??'')==='none') return [];
    $days=[];
    for($i=1;$i<=7;$i++) {
        $a=text_input('start_'.$i,5); $b=text_input('end_'.$i,5);
        if($a==='' && $b==='') continue;
        if(!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/',$a) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/',$b) || $a>=$b) throw new DomainException('Cada día debe tener ingreso y salida válidos; la salida debe ser posterior, dentro del mismo día.');
        $days[(string)$i]=[$a,$b];
    }
    if(!$days) throw new DomainException('Define al menos un día o selecciona «Sin horario definido».');
    return $days;
}
function local_to_utc(string $date,string $time): string {
    if(!date_ok($date) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/',$time)) throw new DomainException('Fecha u hora no válida.');
    $dt=new DateTimeImmutable($date.' '.$time,zone());
    if($dt->format('Y-m-d H:i')!==$date.' '.$time) throw new DomainException('La hora no existe en la zona horaria seleccionada.');
    return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}
