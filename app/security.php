<?php
declare(strict_types=1);
const APP_VERSION='1.7.0';

function security_ready(): bool {
    // Do not cache: the browser updater may enable this within the same request.
    try { return (bool)query('SELECT version FROM ch_schema_versions WHERE version=3')->fetchColumn(); }
    catch(PDOException $error) { if($error->getCode()==='42S02') return false; throw $error; }
}
function request_header_text(string $key,int $limit): string {
    $value=$_SERVER[$key]??'';
    if(!is_string($value)) return '';
    // Strip controls and invalid bytes. Header values remain untrusted and are escaped when shown.
    $value=iconv('UTF-8','UTF-8//IGNORE',$value)?:'';
    $value=preg_replace('/[\x00-\x1F\x7F]/u',' ',$value)??'';
    $chars=preg_split('//u',$value,-1,PREG_SPLIT_NO_EMPTY)?:[];
    return implode('',array_slice($chars,0,$limit));
}
function record_request_context(int $auditId): void {
    if(!security_ready()) return;
    $peer=$_SERVER['REMOTE_ADDR']??null;
    $ip=is_string($peer)&&filter_var($peer,FILTER_VALIDATE_IP)?$peer:null;
    // Forwarded headers are not trusted: the hosting must normalize the client IP for a trusted proxy.
    query('INSERT INTO ch_request_context(audit_id,ip_address,user_agent,platform,mobile) VALUES(?,?,?,?,?)',[$auditId,$ip,request_header_text('HTTP_USER_AGENT',1024),request_header_text('HTTP_SEC_CH_UA_PLATFORM',128),request_header_text('HTTP_SEC_CH_UA_MOBILE',16)]);
}
function context_view(?array $context): void {
    if(!$context) { echo '<p class="small muted">IP y dispositivo no disponibles para este evento.</p>'; return; }
    echo '<dl class="profile-data request-context"><div><dt>IP de conexión</dt><dd>'.e($context['ip_address']??'No disponible').'</dd></div><div><dt>Navegador / dispositivo informado</dt><dd>'.e($context['user_agent']?:'No disponible').'</dd></div>';
    if($context['platform']!=='') echo '<div><dt>Plataforma informada</dt><dd>'.e($context['platform']).'</dd></div>';
    if(in_array($context['mobile'],['?0','?1'],true)) echo '<div><dt>Navegador móvil</dt><dd>'.($context['mobile']==='?1'?'Sí':'No').'</dd></div>';
    echo '</dl>';
}
function punch_context_view(int $attendance): void {
    echo '<h3>Conexión y dispositivo del marcado</h3><p class="small muted">Datos de la solicitud original. El navegador los informa y no constituyen una identificación única del equipo.</p>';
    $contexts=security_ready()?rows("SELECT c.*,a.action,a.created_at FROM ch_request_context c JOIN ch_audit a ON a.id=c.audit_id WHERE a.entity='attendance' AND a.entity_id=? AND a.action IN ('punch_in','punch_out') ORDER BY a.id",[(string)$attendance]):[];
    if(!$contexts) { echo '<p class="small muted">Sin datos de conexión registrados. Se recopilan en los nuevos marcados después de actualizar la base de datos.</p>'; return; }
    foreach($contexts as $context) { echo '<details><summary>'.($context['action']==='punch_in'?'Ingreso':'Salida').' · '.e(local_time($context['created_at'],'d/m/Y H:i:s')).'</summary>'; context_view($context); echo '</details>'; }
}

function auditor_review_view(array $actor,array $person,string $date,?array $record): void {
    require_role($actor,['auditor']);
    ?><a class="back-link" href="index.php?page=reports&amp;user_id=<?= (int)$person['id'] ?>">← Reportes de esta persona</a><div class="two-col"><section class="panel"><h2><?= e($person['name']) ?></h2><p><?= e(rut_label($person['rut']).' · '.date_label($date)) ?></p><p class="small muted">Consulta de auditoría · Sólo lectura</p><form method="get" class="inline-form"><input type="hidden" name="page" value="edit"><input type="hidden" name="user_id" value="<?= (int)$person['id'] ?>"><label>Fecha del registro<input type="date" name="date" value="<?= e($date) ?>" max="<?= e(today()) ?>" required></label><button class="button secondary">Consultar</button></form><dl class="profile-data"><div><dt>Ingreso</dt><dd><?= e(local_time($record['clock_in']??null,'H:i:s')) ?></dd></div><div><dt>Salida</dt><dd><?= e(local_time($record['clock_out']??null,'H:i:s')) ?></dd></div></dl>
    <?php if(features_ready()) { $absence=row('SELECT * FROM ch_absences WHERE user_id=? AND work_date=?',[$person['id'],$date]); if($absence) echo '<p>'.e(absence_categories()[$absence['category']].' · '.absence_status($absence['status'])).'</p>'; } ?>
    </section><section class="panel"><?php locations_view($actor,$record); ?></section></div><section class="panel"><h2>Historial de cambios</h2><?php audit_table(review_audit_entries($record,(int)$person['id'],$date)); ?></section><?php
}
