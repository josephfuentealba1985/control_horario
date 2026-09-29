<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
if(!is_file(__DIR__.'/config.php')) go('install.php');
require __DIR__.'/app/reports.php';
require __DIR__.'/app/exporters.php';
$user=current_user();
if(!$user || !in_array($user['role'],['admin','analyst','auditor'],true) || $user['must_change']) { http_response_code(403); exit('No tienes permiso para exportar reportes.'); }
try {
    $f=report_filters($user); $data=report_data($f); $format=$_GET['format']??'xlsx';
    if(!in_array($format,['xlsx','pdf'],true)) throw new DomainException('Formato no válido.');
    $period=date_label($f['from']).' al '.date_label($f['to']).' · '.settings()['timezone'];
    if($format==='xlsx') {
        $connections=[];
        if(security_ready()) foreach(rows("SELECT a.id,c.*,l.action FROM ch_attendance a JOIN ch_audit l ON l.entity='attendance' AND l.entity_id=CAST(a.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci JOIN ch_request_context c ON c.audit_id=l.id WHERE a.work_date BETWEEN ? AND ? AND l.action IN ('punch_in','punch_out')".($f['user_id']?' AND a.user_id=?':''),$f['user_id']?[$f['from'],$f['to'],$f['user_id']]:[$f['from'],$f['to']]) as $context) $connections[$context['id']][$context['action']]=$context;

        $info=[['Concepto','Valor'],['Empresa',settings()['company']],['Período',$period],['Generado',local_time(utc_now(),'d/m/Y H:i:s')],['Cálculo','Horas:minutos. Minutos completos por jornada. Colación incluida.'],['Metas','Horario individual vigente menos feriados. Los rangos parciales sólo suman los días filtrados.'],['Sin horario','No se calcula meta. Referencia general: '.settings()['weekly_hours'].' horas.'],['Sin salida','No suma horas hasta ser corregido.'],['Justificaciones','Las aprobadas cubren el horario como horas justificadas. Computadas = trabajadas + justificadas. La meta no cambia.'],['Diferencia','Indicador informativo; no es cálculo de remuneraciones.']];
        $weekly=[['Persona','RUT','Semana desde','Trabajadas (h:mm)','Justificadas (h:mm)','Computadas (h:mm)','Meta (h:mm)','Diferencia (h:mm)','Minutos trabajados','Registros abiertos','Período parcial']];
        foreach($data['weeks'] as $w) $weekly[]=[$w['name'],rut_label($w['rut']),$w['week'],hours($w['minutes']),hours($w['justified']),hours($w['credited']),$w['unscheduled']?'Sin horario':hours($w['expected']),$w['unscheduled']?'':hours($w['credited']-$w['expected']),$w['minutes'],$w['pending'],$w['partial']?'Sí':'No'];
        $detail=[['Persona','RUT','Fecha','Ingreso','Salida','Trabajadas (h:mm)','Justificadas (h:mm)','Computadas (h:mm)','Minutos trabajados','Minutos justificados','Horario','Meta (minutos)','Atraso (minutos)','Estado','Feriado','Comentario','Origen','Justificación','Estado justificación','IP ingreso','Navegador ingreso','Plataforma ingreso','IP salida','Navegador salida','Plataforma salida']];
        foreach($data['details'] as $d) { $a=$d['record']; $detail[]=[$d['name'],rut_label($d['rut']),$d['date'],$a?local_time($a['clock_in']):'',$a && $a['clock_out']?local_time($a['clock_out']):'',$a && $a['clock_out'] && !$a['voided']?hours($d['minutes']):'',hours($d['justified']),hours($d['credited']),$d['minutes'],$d['justified'],$d['scheduled'],$d['expected'],$d['late'],$d['status'],$d['holiday']??'',attendance_comment($a),$a?'Registro de asistencia':'',isset($d['absence']['category'])?absence_categories()[$d['absence']['category']]:'',isset($d['absence']['status'])?absence_status($d['absence']['status']):'',...array_map(static fn($key)=>$connections[$a['id']??0][$key[0]][$key[1]]??'',[['punch_in','ip_address'],['punch_in','user_agent'],['punch_in','platform'],['punch_out','ip_address'],['punch_out','user_agent'],['punch_out','platform']])]; }
        $bytes=xlsx_bytes(['Información'=>$info,'Cómputo semanal'=>$weekly,'Detalle diario'=>$detail]);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    } else {
        $weekly=[]; foreach($data['weeks'] as $w) $weekly[]=[$w['name'],date_label($w['week']).($w['partial']?' (parcial)':''),hours($w['minutes']),hours($w['justified']),hours($w['credited']),$w['unscheduled']?'Sin horario':hours($w['expected']),$w['unscheduled']?'-':hours($w['credited']-$w['expected']),(string)$w['pending']];
        $detail=[]; foreach($data['details'] as $d) { $a=$d['record']; $detail[]=[$d['name'].' · '.rut_label($d['rut']),date_label($d['date']),$a?local_time($a['clock_in']):'-',$a?local_time($a['clock_out']):'-',$a && $a['clock_out'] && !$a['voided']?hours($d['minutes']):'-',hours($d['justified']),$d['status'],implode(' · ',array_filter([$d['holiday'],attendance_comment($a),isset($d['absence']['category'])?absence_categories()[$d['absence']['category']].' ('.absence_status($d['absence']['status']).')':'']))]; }
        $bytes=pdf_bytes(settings()['company'],$period,[['title'=>'Cómputo semanal · Computadas = trabajadas + justificadas','columns'=>[[40,'Persona',26],[195,'Semana desde',18],[300,'Trabajadas',12],[385,'Justificadas',12],[470,'Computadas',12],[555,'Meta',10],[630,'Diferencia',12],[720,'Abiertos',8]],'rows'=>$weekly],['title'=>'Detalle diario','columns'=>[[40,'Persona / RUT',26],[190,'Fecha',12],[264,'Ingreso',8],[315,'Salida',8],[365,'Trabajadas',10],[435,'Justificadas',10],[508,'Estado',15],[600,'Observación',35]],'rows'=>$detail]]);
        header('Content-Type: application/pdf');
    }
    header('Content-Disposition: attachment; filename="asistencia-'.$f['from'].'-'.$f['to'].'.'.$format.'"');
    header('Content-Length: '.strlen($bytes)); echo $bytes;
} catch(DomainException $error) { http_response_code(400); header('Content-Type: text/plain; charset=utf-8'); echo $error->getMessage(); }
