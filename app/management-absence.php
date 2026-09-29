<?php
declare(strict_types=1);

function management_absence_action(array $actor): void {
    require_role($actor,['admin','analyst']); require_features();
    $uid=(int)($_POST['user_id']??0); $date=text_input('work_date',10,true);
    $category=text_input('category',20,true); $reason=text_input('reason',1000,true);
    if(!date_ok($date)||$date>today()) throw new DomainException('Selecciona una fecha válida que no sea futura.');
    if(!isset(absence_categories()[$category])) throw new DomainException('Selecciona un motivo de la lista.');
    db()->beginTransaction();
    try {
        $person=row('SELECT * FROM ch_users WHERE id=? FOR UPDATE',[$uid]);
        if(!$person || $person['role']!=='user' || $date<$person['start_date']) throw new DomainException('Selecciona una persona con perfil Usuario y una fecha desde su inicio de seguimiento.');
        if(row('SELECT id FROM ch_attendance WHERE user_id=? AND work_date=? AND voided=0',[$uid,$date])) throw new DomainException('Ya existe asistencia para ese día. Revisa el marcado antes de justificar una ausencia.');
        $before=row('SELECT * FROM ch_absences WHERE user_id=? AND work_date=? FOR UPDATE',[$uid,$date]);
        if((int)($before['revision']??0)!==(int)($_POST['absence_revision']??0)) throw new DomainException('La justificación cambió. Vuelve a abrir la revisión antes de guardar.');
        if($before) {
            $id=(int)$before['id'];
            query("UPDATE ch_absences SET category=?,status='approved',reviewed_by=?,review_note=?,reviewed_at=?,revision=revision+1,updated_at=? WHERE id=?",[$category,$actor['id'],$reason,utc_now(),utc_now(),$id]);
        } else {
            query("INSERT INTO ch_absences(user_id,work_date,category,note,status,reviewed_by,review_note,reviewed_at,created_at,updated_at) VALUES(?,?,?,?,'approved',?,?,?,?,?)",[$uid,$date,$category,'Justificación registrada por jefatura.',$actor['id'],$reason,utc_now(),utc_now(),utc_now()]);
            $id=(int)db()->lastInsertId();
        }
        audit((int)$actor['id'],'absence_management','absence',$id,$before,row('SELECT * FROM ch_absences WHERE id=?',[$id]),$reason);
        db()->commit();
    } catch(Throwable $error) { if(db()->inTransaction()) db()->rollBack(); throw $error; }
    flash('Justificación aprobada por jefatura. Las horas programadas del día se contabilizan como justificadas.');
    go('index.php?page=edit&user_id='.$uid.'&date='.$date);
}

function management_absence_view(array $actor,array $person,string $date,?array $record): void {
    if(!features_ready() || $person['role']!=='user' || $date>today() || $date<$person['start_date'] || ($record && !$record['voided'])) return;
    $absence=row('SELECT * FROM ch_absences WHERE user_id=? AND work_date=?',[$person['id'],$date]);
    $days=schedule_days((int)$person['id'],$date); $day=$days[(new DateTimeImmutable($date))->format('N')]??null;
    $minutes=$day && !row('SELECT date FROM ch_holidays WHERE date=?',[$date])?clock_minutes($day[1])-clock_minutes($day[0]):0;
    ?><h2>Justificación de ausencia</h2><p class="small muted">Registra el justificativo por jefatura sin crear un ingreso o una salida. Quedará aprobado de inmediato.</p>
    <?php if($absence): ?><div class="notice info"><?= status_badge(absence_status($absence['status'])) ?> <?= e(absence_categories()[$absence['category']]) ?><?php if($absence['review_note']): ?><p><?= e($absence['review_note']) ?></p><?php endif; ?></div><?php endif; ?>
    <p>Horas que cubre este día: <strong><?= e(hours($minutes)) ?></strong>.</p><p class="small muted">Se usa el horario vigente de la persona. Los descansos, feriados del calendario y días sin horario no suman horas justificadas.</p>
    <details class="management-absence"><summary class="button secondary"><?= $absence?'Registrar o corregir justificación por jefatura':'Justificar ausencia por jefatura' ?></summary>
    <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="management_absence"><input type="hidden" name="user_id" value="<?= (int)$person['id'] ?>"><input type="hidden" name="work_date" value="<?= e($date) ?>"><input type="hidden" name="absence_revision" value="<?= (int)($absence['revision']??0) ?>">
    <label>Tipo de justificación<select name="category" required><?php foreach(absence_categories() as $key=>$label): ?><option value="<?= e($key) ?>" <?= ($absence['category']??'')===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <label>Motivo de jefatura<textarea name="reason" rows="3" maxlength="1000" required placeholder="Indica los antecedentes que justifican la ausencia o su corrección."></textarea></label><button class="button primary">Guardar justificación aprobada</button></form></details>
    <?php
}

function review_audit_entries(?array $record,int $uid,string $date): array {
    $entries=$record?rows("SELECT a.*,u.name actor FROM ch_audit a LEFT JOIN ch_users u ON u.id=a.actor_id WHERE a.entity='attendance' AND a.entity_id=?",[(string)$record['id']]):[];
    if(features_ready()) {
        $absence=row('SELECT id FROM ch_absences WHERE user_id=? AND work_date=?',[$uid,$date]);
        if($absence) $entries=array_merge($entries,rows("SELECT a.*,u.name actor FROM ch_audit a LEFT JOIN ch_users u ON u.id=a.actor_id WHERE a.entity='absence' AND a.entity_id=?",[(string)$absence['id']]));
    }
    usort($entries,static fn($a,$b)=>(int)$b['id']<=>(int)$a['id']); return $entries;
}
