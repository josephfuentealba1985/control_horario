<?php
declare(strict_types=1);

function report_filters(array $actor): array {
    $now=new DateTimeImmutable(today());
    $period=is_string($_GET['period']??null)?$_GET['period']:'week';
    $start=$period==='month'?$now->format('Y-m-01'):$now->modify('monday this week')->format('Y-m-d');
    $end=$period==='month'?$now->format('Y-m-t'):$now->modify('sunday this week')->format('Y-m-d');
    if(isset($_GET['from']) && is_string($_GET['from']) && $_GET['from']!=='') $start=$_GET['from'];
    if(isset($_GET['to']) && is_string($_GET['to']) && $_GET['to']!=='') $end=$_GET['to'];
    if(!date_ok($start) || !date_ok($end) || $start>$end || (new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days>365) throw new DomainException('Selecciona un rango de hasta 366 días, con inicio anterior al término.');
    $uid=$actor['role']==='user' || ($_GET['page']??'')==='history' ? (int)$actor['id'] : max(0,(int)($_GET['user_id']??0));
    return ['from'=>$start,'to'=>$end,'user_id'=>$uid,'period'=>$period];
}

function report_data(array $filters): array {
    $users=rows('SELECT id,rut,name,active,start_date,institution,position FROM ch_users '.($filters['user_id']?'WHERE id=? ':'').'ORDER BY name',$filters['user_id']?[$filters['user_id']]:[]);
    $holidays=array_column(rows('SELECT * FROM ch_holidays WHERE date BETWEEN ? AND ?',[$filters['from'],$filters['to']]),'name','date');
    $origin=features_ready()?'EXISTS(SELECT 1 FROM ch_import_items i WHERE i.attendance_id=a.id)':'0';
    $records=rows('SELECT a.*,'.$origin.' AS imported FROM ch_attendance a WHERE work_date BETWEEN ? AND ?'.($filters['user_id']?' AND user_id=?':''),$filters['user_id']?[$filters['from'],$filters['to'],$filters['user_id']]:[$filters['from'],$filters['to']]);
    $byDate=[]; foreach($records as $record) $byDate[$record['user_id']][$record['work_date']]=$record;
    $absenceDates=[];
    if(features_ready()) foreach(rows('SELECT * FROM ch_absences WHERE work_date BETWEEN ? AND ?'.($filters['user_id']?' AND user_id=?':''),$filters['user_id']?[$filters['from'],$filters['to'],$filters['user_id']]:[$filters['from'],$filters['to']]) as $a) $absenceDates[$a['user_id']][$a['work_date']]=$a;
    $versions=rows('SELECT user_id,effective_from,days_json FROM ch_schedules WHERE effective_from<=?'.($filters['user_id']?' AND user_id=?':'').' ORDER BY user_id,effective_from',$filters['user_id']?[$filters['to'],$filters['user_id']]:[$filters['to']]);
    $bySchedule=[]; foreach($versions as $version) $bySchedule[$version['user_id']][]=['from'=>$version['effective_from'],'days'=>json_decode($version['days_json'],true)];
    $details=[]; $summaries=[]; $weeks=[]; $today=today();
    foreach($users as $user) {
        $total=0; $expected=0; $pending=0; $absent=0; $noSchedule=false;
        foreach(new DatePeriod(new DateTimeImmutable($filters['from']),new DateInterval('P1D'),(new DateTimeImmutable($filters['to']))->modify('+1 day')) as $day) {
            $date=$day->format('Y-m-d'); if($date<$user['start_date']) continue;
            $days=[]; foreach($bySchedule[$user['id']]??[] as $v) { if($v['from']>$date) break; $days=$v['days']; }
            $scheduled=$days[$day->format('N')]??null; $holiday=$holidays[$date]??null;
            $planned=$scheduled && !$holiday?clock_minutes($scheduled[1])-clock_minutes($scheduled[0]):0;
            $record=$byDate[$user['id']][$date]??null; $valid=$record && !$record['voided'];
            $absence=$absenceDates[$user['id']][$date]??null;
            $minutes=$valid?duration_minutes($record['clock_in'],$record['clock_out']):0;
            $state=$record && $record['voided']?'Anulado':($valid?($record['clock_out']?'Completo':($date===$today?'En jornada':'Sin salida')):($holiday?'Feriado':($scheduled?($date>$today?'Próximo':($date===$today?'Sin ingreso':'Sin marcado')):($days?'Descanso':'Sin horario'))));
            $late=$valid && $scheduled && !$holiday?max(0,clock_minutes(local_time($record['clock_in']))-clock_minutes($scheduled[0])):0;
            if(!$valid && $absence && $absence['status']==='approved')$state='Justificada';
            elseif(!$valid && $absence && $absence['status']==='pending')$state='Justificación pendiente';
            $item=['user_id'=>$user['id'],'name'=>$user['name'],'rut'=>$user['rut'],'date'=>$date,'scheduled'=>$scheduled?implode(' – ',$scheduled):($days?'Descanso':'Sin horario'),'expected'=>$planned,'minutes'=>$minutes,'late'=>$late,'status'=>$state,'holiday'=>$holiday,'record'=>$record,'absence'=>$absence];
            $details[]=$item; $total+=$minutes; $expected+=$planned; $pending+=(int)($valid && !$record['clock_out']); $absent+=(int)($state==='Sin marcado'); $noSchedule=$noSchedule || !$days;
            $week=$day->modify('monday this week')->format('Y-m-d'); $key=$user['id'].'_'.$week;
            if(!isset($weeks[$key])) $weeks[$key]=['name'=>$user['name'],'rut'=>$user['rut'],'week'=>$week,'minutes'=>0,'expected'=>0,'pending'=>0,'unscheduled'=>false,'partial'=>max($filters['from'],$user['start_date'])>$week || $filters['to']<$day->modify('sunday this week')->format('Y-m-d')];
            $weeks[$key]['minutes']+=$minutes; $weeks[$key]['expected']+=$planned; $weeks[$key]['pending']+=(int)($valid && !$record['clock_out']); $weeks[$key]['unscheduled']=$weeks[$key]['unscheduled'] || !$days;
        }
        $summaries[]=['user'=>$user,'minutes'=>$total,'expected'=>$expected,'pending'=>$pending,'absent'=>$absent,'unscheduled'=>$noSchedule];
    }
    return ['details'=>$details,'summaries'=>$summaries,'weeks'=>array_values($weeks)];
}
