<?php
declare(strict_types=1);
// Activation is used only by setup and an explicit administrator request.
// Attendance and authentication never depend on LOOPERS availability.
function activation_service(): string {
    $file=dirname(__DIR__).'/activation-service.php';
    if(is_file($file)) {
        $c=require $file;
        $url=$c['url']??'';
        $p=parse_url($url);
        if(($c['development']??false) && in_array($p['host']??'',['127.0.0.1','localhost'],true)
            && in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)) return $url;
    }
    return 'https://loopers.cl/proyectos/api.php';
}
function activation_url(): string {
    $host=strtolower($_SERVER['HTTP_HOST']??'');
    if(!preg_match('/^[a-z0-9.-]+(?::[0-9]{1,5})?$/',$host)) throw new DomainException('Dominio de instalación no válido.');
    $secure=!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off';
    if(!$secure && !str_starts_with(activation_service(),'http://127.0.0.1:') && !str_starts_with(activation_service(),'http://localhost:')) throw new DomainException('La activación requiere HTTPS.');
    if($secure) $host=preg_replace('/:443$/','',$host);
    $path=trim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'/')),'/.');
    return ($secure?'https':'http').'://'.$host.'/'.($path!==''?$path.'/':'');
}
function activation_read(): array {
    $path=dirname(__DIR__).'/activation-state.php';
    return is_file($path)?require $path:[];
}
function activation_write(array $state): void {
    $path=dirname(__DIR__).'/activation-state.php';
    $temp=dirname(__DIR__).'/activation-state.'.bin2hex(random_bytes(8)).'.php';
    $content="<?php\n// Private installation identity. Back up with config.php; never publish.\nreturn ".var_export($state,true).";\n";
    $f=fopen($temp,'x');
    if(!$f) throw new DomainException('No se pudo guardar la identidad de instalación. Revisa permisos de escritura.');
    try {
        @chmod($temp,0600);
        if(fwrite($f,$content)!==strlen($content) || !fflush($f)) throw new RuntimeException('Activation state write failed');
    } finally {fclose($f);}
    if(!rename($temp,$path)) {unlink($temp);throw new RuntimeException('Activation state rename failed');}
}
function activation_http(string $url,?array $data=null): array {
    if(!extension_loaded('curl') || !extension_loaded('openssl')) throw new DomainException('Activa las extensiones cURL y OpenSSL en el hosting.');
    $ch=curl_init($url);$body='';
    curl_setopt_array($ch,[CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25,CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_PROTOCOLS=>str_starts_with($url,'https://')?CURLPROTO_HTTPS:CURLPROTO_HTTP,
        CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk) use(&$body): int {if(strlen($body)+strlen($chunk)>32768)return 0;$body.=$chunk;return strlen($chunk);},
        CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: application/json']]);
    if($data!==null) curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($data,JSON_THROW_ON_ERROR)]);
    $ok=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    if($ok===false) throw new DomainException('No se pudo conectar con LOOPERS. Vuelve a intentar con la misma clave; se conservará tu identidad de instalación.');
    $result=json_decode($body,true);
    if(!is_array($result)) throw new DomainException('LOOPERS no entregó una respuesta válida. Intenta más tarde.');
    if($status!==200) throw new DomainException(is_string($result['error']??null)?$result['error']:'No se pudo activar la instalación.');
    return $result;
}
function activation_verify(array $receipt,string $publicKey,array $identity): array {
    $payload=$receipt['payload']??null;$signature=$receipt['signature']??null;
    if(!is_string($payload)||!is_string($signature) || ($decoded=base64_decode($signature,true))===false
        || openssl_verify($payload,$decoded,$publicKey,OPENSSL_ALGO_SHA256)!==1) throw new DomainException('La respuesta de activación no tiene una firma válida.');
    $data=json_decode(base64_decode($payload,true)?:'',true);
    if(!is_array($data) || ($data['product']??'')!=='control-horario' || ($data['installation_id']??'')!==$identity['installation_id']
        || ($data['site_url']??'')!==$identity['site_url'] || ($data['perpetual']??false)!==true) throw new DomainException('La activación no corresponde a esta instalación.');
    return $data;
}
function activation_register(string $key): array {
    $key=strtoupper(trim($key));
    if(!preg_match('/^CH-[A-F0-9]{48}$/',$key)) throw new DomainException('Ingresa la clave gratuita generada en Proyectos abiertos de LOOPERS.');
    $state=activation_read();$url=activation_url();$keyHash=hash('sha256',$key);
    // Persist before contacting LOOPERS, so a lost response can be safely retried.
    if(isset($state['pending'])) {
        $previous=$state['pending'];
        $state['attempts'][hash('sha256',$previous['key_hash'].'|'.$previous['site_url'])]=$previous;
    }
    $attempt=hash('sha256',$keyHash.'|'.$url);
    if(($state['pending']['key_hash']??'')!==$keyHash || ($state['pending']['site_url']??'')!==$url) {
        $state['pending']=$state['attempts'][$attempt]??['installation_id'=>bin2hex(random_bytes(32)),'proof'=>bin2hex(random_bytes(32)),'site_url'=>$url,'key_hash'=>$keyHash];
        $state['attempts'][$attempt]=$state['pending'];
        activation_write($state);
    }
    $identity=$state['pending'];$service=activation_service();
    $pub=activation_http($service.'?action=public-key')['key']??'';
    if(!is_string($pub) || !openssl_pkey_get_public($pub)) throw new DomainException('No se pudo validar la identidad del servicio de activación.');
    $receipt=activation_http($service,['key'=>$key,'installation_id'=>$identity['installation_id'],'proof'=>$identity['proof'],'site_url'=>$url])['receipt']??[];
    $details=activation_verify($receipt,$pub,$identity);
    $state['active']=['receipt'=>$receipt,'public_key'=>$pub,'site_url'=>$url,'license_id'=>$details['license_id'],'activated_at'=>$details['issued_at']];
    activation_write($state);return $state['active'];
}
function activation_action(array $actor): void {
    require_role($actor,['admin']);
    $key=text_input('activation_key',100,true);
    $lock=fopen(__FILE__,'r');
    if(!$lock || !flock($lock,LOCK_EX)) throw new DomainException('No se pudo bloquear la activación. Intenta nuevamente.');
    try {
        $before=activation_read()['active']??null;
        $after=activation_register($key);
        audit((int)$actor['id'],'activation','settings',1,
            $before?['site_url'=>$before['site_url'],'license_id'=>$before['license_id']]:null,
            ['site_url'=>$after['site_url'],'license_id'=>$after['license_id']],'Activación o traslado solicitado por administrador');
    } finally {flock($lock,LOCK_UN);fclose($lock);}
    flash('Instalación vinculada a LOOPERS. El uso sigue siendo gratuito.');go('index.php?page=settings');
}
function activation_view(): void {
    $a=activation_read()['active']??null; ?>
    <section class="panel stack"><h2>Registro de instalación · LOOPERS</h2>
    <p><?= $a?'Instalación registrada en '.e($a['site_url']):'Instalación existente. Puedes seguir utilizando el sistema sin cambios.' ?></p>
    <p class="muted small">Uso gratuito, sin suscripción. Para registrar esta instalación o trasladarla, genera una clave en <a href="https://loopers.cl/proyectos/cuenta.php" target="_blank" rel="noopener">Mis instalaciones de LOOPERS</a>. Guarda una copia de la base de datos, config.php y activation-state.php antes de trasladar el sistema.</p>
    <details><summary>Registrar o actualizar la vinculación</summary><form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="activate_installation"><label>Clave gratuita de LOOPERS<input type="password" name="activation_key" required autocomplete="off" maxlength="100"></label><button class="button primary">Vincular instalación</button></form></details></section>
<?php }
