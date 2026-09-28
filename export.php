<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
if(!is_file(__DIR__.'/config.php')) go('install.php');
require __DIR__.'/app/reports.php';
require __DIR__.'/app/exporters.php';
$user=current_user();
if(!$user || !in_array($user['role'],['admin','analyst'],true) || $user['must_change']) { http_response_code(403); exit('No tienes permiso para exportar reportes.'); }
try {
    $f=report_filters($user); $data=report_data($f); $format=$_GET['format']??'xlsx';
    if(!in_array($format,['xlsx','pdf'],true)) throw new DomainException('Formato no válido.');
    $period=date_label($f['from']).' al '.date_label($f['to']).' · '.settings()['timezone'];
    if($format==='xlsx') {
        $info=[['Concepto','Valor'],['Empresa',settings()['company']],['Período',$period],['Generado',local_time(utc_now(),'d/m/Y H:i:s')],['Cálculo','Horas:minutos. Minutos completos por jornada. Colación incluida.'],['Metas','Horario individual vigente menos feriados. Los rangos parciales sólo suman los días filtrados.'],['Sin horario','No se calcula meta. Referencia general: '.settings()['weekly_hours'].' horas.'],['Sin salida','No suma horas hasta ser corregido.'],['Justificaciones','Las aprobadas mantienen la meta semanal y no generan horas trabajadas.'],['Diferencia','Indicador informativo; no es cálculo de remuneraciones.']];
        $weekly=[['Persona','RUT','Semana desde','Completadas (h:mm)','Meta (h:mm)','Diferencia (h:mm)','Minutos trabajados','Registros abiertos','Período parcial']];
        foreach($data['weeks'] as $w) $weekly[]=[$w['name'],rut_label($w['rut']),$w['week'],hours($w['minutes']),$w['unscheduled']?'Sin horario':hours($w['expected']),$w['unscheduled']?'':hours($w['minutes']-$w['expected']),$w['minutes'],$w['pending'],$w['partial']?'Sí':'No'];
        $detail=[['Persona','RUT','Fecha','Ingreso','Salida','Horas (h:mm)','Minutos trabajados','Horario','Meta (minutos)','Atraso (minutos)','Estado','Feriado','Comentario','Origen','Justificación','Estado justificación']];
        foreach($data['details'] as $d) { $a=$d['record']; $detail[]=[$d['name'],rut_label($d['rut']),$d['date'],$a?local_time($a['clock_in']):'',$a && $a['clock_out']?local_time($a['clock_out']):'',$a && $a['clock_out'] && !$a['voided']?hours($d['minutes']):'',$d['minutes'],$d['scheduled'],$d['expected'],$d['late'],$d['status'],$d['holiday']??'',attendance_comment($a),$a?'Registro de asistencia':'',isset($d['absence']['category'])?absence_categories()[$d['absence']['category']]:'',isset($d['absence']['status'])?absence_status($d['absence']['status']):'']; }
        $bytes=xlsx_bytes(['Información'=>$info,'Cómputo semanal'=>$weekly,'Detalle diario'=>$detail]);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    } else {
        $weekly=[]; foreach($data['weeks'] as $w) $weekly[]=[$w['name'],date_label($w['week']).($w['partial']?' (parcial)':''),hours($w['minutes']),$w['unscheduled']?'Sin horario':hours($w['expected']),$w['unscheduled']?'-':hours($w['minutes']-$w['expected']),(string)$w['pending']];
        $detail=[]; foreach($data['details'] as $d) { $a=$d['record']; $detail[]=[$d['name'].' · '.rut_label($d['rut']),date_label($d['date']),$a?local_time($a['clock_in']):'-',$a?local_time($a['clock_out']):'-',$a && $a['clock_out'] && !$a['voided']?hours($d['minutes']):'-',$d['status'],implode(' · ',array_filter([$d['holiday'],attendance_comment($a),isset($d['absence']['category'])?absence_categories()[$d['absence']['category']].' ('.absence_status($d['absence']['status']).')':'']))]; }
        $bytes=pdf_bytes(settings()['company'],$period,[['title'=>'Cómputo semanal · Meta según horario menos feriados','columns'=>[[40,'Persona',35],[250,'Semana desde',25],[395,'Horas',15],[490,'Meta',15],[590,'Diferencia',15],[710,'Abiertos',12]],'rows'=>$weekly],['title'=>'Detalle diario','columns'=>[[40,'Persona / RUT',31],[216,'Fecha',12],[292,'Ingreso',8],[347,'Salida',8],[400,'Horas',8],[452,'Estado',17],[563,'Observación',48]],'rows'=>$detail]]);
        header('Content-Type: application/pdf');
    }
    header('Content-Disposition: attachment; filename="asistencia-'.$f['from'].'-'.$f['to'].'.'.$format.'"');
    header('Content-Length: '.strlen($bytes)); echo $bytes;
} catch(DomainException $error) { http_response_code(400); header('Content-Type: text/plain; charset=utf-8'); echo $error->getMessage(); }
