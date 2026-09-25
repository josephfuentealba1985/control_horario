<?php
declare(strict_types=1);

// Read only selected XLSX XML parts; never extract files or execute formulas.
final class AttendanceXlsx {
    private string $data;
    private array $entries=[];
    private int $expanded=0;
    public function __construct(string $path) {
        if(!function_exists('simplexml_load_string')||!function_exists('gzinflate')) throw new DomainException('La importación requiere SimpleXML y zlib en PHP. Actívalos desde el panel del hosting.');
        $size=filesize($path); if($size===false||$size<22||$size>5*1024*1024) throw new DomainException('El archivo debe ser un Excel .xlsx de hasta 5 MB.');
        $this->data=(string)file_get_contents($path);
        $end=strrpos($this->data,"PK\x05\x06"); if($end===false||strlen($this->data)-$end<22) throw new DomainException('El archivo no es un .xlsx válido. Guarda el libro como Excel .xlsx, sin contraseña.');
        $e=unpack('vdisk/vstart/vdiskcount/vcount/Vsize/Voffset/vcomment',substr($this->data,$end+4,18));
        if($e['disk']||$e['start']||$e['count']!==$e['diskcount']||$e['count']>1000||$e['count']===65535||$e['offset']+$e['size']>$end||$end+22+$e['comment']!==strlen($this->data)) throw new DomainException('El contenedor Excel no es compatible o excede los límites.');
        $p=$e['offset'];
        for($i=0;$i<$e['count'];$i++) {
            if(substr($this->data,$p,4)!=="PK\x01\x02"||$p+46>$end) throw new DomainException('Archivo Excel dañado.');
            $v=unpack('vmade/vneed/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vsize/vname/vextra/vcomment/vdisk/vinternal/Vexternal/Voffset',substr($this->data,$p+4,42));
            $name=substr($this->data,$p+46,$v['name']);
            if(isset($this->entries[$name])||($v['flags']&1)||$v['disk']||$v['size']>8*1024*1024||$v['offset']>=strlen($this->data)) throw new DomainException('Excel protegido, duplicado o demasiado grande.');
            $this->entries[$name]=$v; $p+=46+$v['name']+$v['extra']+$v['comment'];
            if($p>$e['offset']+$e['size']) throw new DomainException('Archivo Excel dañado.');
        }
    }
    private function xml(string $name): SimpleXMLElement {
        if(!isset($this->entries[$name])) throw new DomainException('Falta una sección del Excel: '.$name.'.');
        $v=$this->entries[$name]; $p=$v['offset'];
        if(substr($this->data,$p,4)!=="PK\x03\x04") throw new DomainException('Archivo Excel dañado.');
        $header=unpack('vname/vextra',substr($this->data,$p+26,4)); $offset=$p+30+$header['name']+$header['extra'];
        if($offset+$v['compressed']>strlen($this->data)) throw new DomainException('Archivo Excel incompleto.');
        $bytes=substr($this->data,$offset,$v['compressed']);
        $xml=match($v['method']) {0=>$bytes,8=>@gzinflate($bytes,max(1,$v['size'])),default=>false};
        if($xml===false||strlen($xml)!==$v['size']||sprintf('%u',crc32($xml))!==sprintf('%u',$v['crc'])) throw new DomainException('No se pudo verificar el contenido del Excel.');
        $this->expanded+=strlen($xml); if($this->expanded>24*1024*1024) throw new DomainException('El Excel contiene demasiados datos.');
        if(preg_match('/<!\s*(DOCTYPE|ENTITY)/i',$xml)) throw new DomainException('El Excel contiene definiciones XML no permitidas.');
        $previous=libxml_use_internal_errors(true);
        try { $parsed=simplexml_load_string($xml,SimpleXMLElement::class,LIBXML_NONET|LIBXML_COMPACT); }
        finally { libxml_clear_errors();libxml_use_internal_errors($previous); }
        if(!$parsed) throw new DomainException('El Excel contiene XML no válido.');
        return $parsed;
    }
    public function read(): array {
        $book=$this->xml('xl/workbook.xml'); $rels=$this->xml('xl/_rels/workbook.xml.rels');
        $sheets=$book->xpath('//*[local-name()="sheets"]/*[local-name()="sheet"]');
        if(!$sheets) throw new DomainException('No se encontró una hoja en el Excel.');
        $sheet=$sheets[0];$attributes=$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');$id=(string)$attributes['id'];$target=null;
        foreach($rels->children() as $rel) if((string)$rel['Id']===$id && (string)$rel['TargetMode']!=='External') $target=(string)$rel['Target'];
        if($target===null) throw new DomainException('No se encontró la primera hoja.');
        $target=str_starts_with($target,'/')?ltrim($target,'/'):'xl/'.$target;
        $parts=[];foreach(explode('/',$target) as $part) {if($part==='..')array_pop($parts);elseif($part!=='.'&&$part!=='')$parts[]=$part;}
        $target=implode('/',$parts);if(!str_starts_with($target,'xl/worksheets/'))throw new DomainException('Referencia de hoja no válida.');
        $shared=[];
        if(isset($this->entries['xl/sharedStrings.xml'])) {
            foreach($this->xml('xl/sharedStrings.xml')->xpath('//*[local-name()="si"]') as $s) { $texts=$s->xpath('.//*[local-name()="t"]'); $shared[]=implode('',array_map('strval',$texts)); if(count($shared)>50000)throw new DomainException('Demasiadas cadenas de texto.'); }
        }
        $sheetXml=$this->xml($target);$result=[];$cells=0;
        foreach($sheetXml->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
            $line=[];$nonempty=false;
            foreach($row->children() as $cell) {
                if($cell->getName()!=='c') continue;
                if(++$cells>50000)throw new DomainException('El archivo contiene demasiadas celdas.');
                $ref=(string)$cell['r'];if(!preg_match('/^([A-Z]{1,3})[1-9][0-9]*$/',$ref,$match))throw new DomainException('Referencia de celda no válida.');
                $col=0;foreach(str_split($match[1]) as $letter)$col=$col*26+ord($letter)-64;$col--;
                if($col>63)continue;
                $type=(string)$cell['t'];$value=(string)$cell->v;
                if($type==='s'){$idx=filter_var($value,FILTER_VALIDATE_INT);if($idx===false||!isset($shared[$idx]))throw new DomainException('Texto compartido no válido.');$value=$shared[$idx];}
                if($type==='inlineStr')$value=implode('',array_map('strval',$cell->xpath('.//*[local-name()="t"]')));
                if(strlen($value)>2000)throw new DomainException('Una celda excede la longitud admitida.');
                $line[$col]=['value'=>$value,'formula'=>isset($cell->f),'type'=>$type];$nonempty=$nonempty||$value!==''||isset($cell->f);
            }
            if($nonempty)$result[]=['line'=>(int)$row['r'],'cells'=>$line];
            if(count($result)>2001)throw new DomainException('Importa hasta 2.000 filas por archivo.');
        }
        return ['name'=>(string)$sheet['name'],'date1904'=>in_array((string)$book->workbookPr['date1904'],['1','true'],true),'rows'=>$result];
    }
}
function import_date(string $value,bool $date1904): string {
    $value=trim($value);
    if(is_numeric($value)) {
        $n=(float)$value;if($n<1||$n>100000||floor($n)!==$n||(!$date1904&&(int)$n===60))throw new DomainException('Fecha Excel no válida.');
        $base=$date1904?'1904-01-01':((int)$n<60?'1899-12-31':'1899-12-30');
        return (new DateTimeImmutable($base,new DateTimeZone('UTC')))->modify('+'.(int)$n.' days')->format('Y-m-d');
    }
    if(preg_match('/^([0-9]{2})[\/-]([0-9]{2})[\/-]([0-9]{4})$/',$value,$m))$value=$m[3].'-'.$m[2].'-'.$m[1];
    if(!date_ok($value))throw new DomainException('Fecha no válida. Usa una fecha Excel, AAAA-MM-DD o DD/MM/AAAA.');return $value;
}
function import_clock(string $value): ?string {
    $value=trim($value);if($value==='')return null;
    if(is_numeric($value)) { $n=(float)$value;if($n<0||$n>=1)throw new DomainException('La hora Excel debe corresponder a una hora del día.');$seconds=(int)round($n*86400);if($seconds>=86400)throw new DomainException('La hora cruza medianoche.');return sprintf('%02d:%02d:%02d',intdiv($seconds,3600),intdiv($seconds%3600,60),$seconds%60); }
    if(!preg_match('/^([0-9]{1,2}):([0-9]{2})(?::([0-9]{2}))?$/',$value,$m)||(int)$m[1]>23||(int)$m[2]>59||(int)($m[3]??0)>59)throw new DomainException('Hora no válida. Usa HH:MM o HH:MM:SS.');
    return sprintf('%02d:%02d:%02d',(int)$m[1],(int)$m[2],(int)($m[3]??0));
}
function import_timestamp(string $date,string $time): string {
    $v=new DateTimeImmutable($date.' '.$time,zone());if($v->format('Y-m-d H:i:s')!==$date.' '.$time)throw new DomainException('Hora inexistente por cambio de hora local.');return $v->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}
function import_coordinates(string $lat,string $lng): array {
    if(trim($lat)===''&&trim($lng)==='')return [null,null];
    if(!is_numeric($lat)||!is_numeric($lng)||!is_finite((float)$lat)||!is_finite((float)$lng)||abs((float)$lat)>90||abs((float)$lng)>180)throw new DomainException('Las coordenadas deben formar un par latitud/longitud válido.');
    return [round((float)$lat,7),round((float)$lng,7)];
}
function parse_attendance_xlsx(string $path,array $person): array {
    $book=(new AttendanceXlsx($path))->read();$input=$book['rows'];if(count($input)<2)throw new DomainException('El Excel no contiene filas de asistencia.');
    $header=array_shift($input);$columns=[];
    foreach($header['cells'] as $index=>$cell) { $name=strtolower(trim($cell['value']));if(isset($columns[$name]))throw new DomainException('Hay encabezados duplicados.');$columns[$name]=$index; }
    foreach(['rut','fecha','ingreso','salida'] as $name)if(!isset($columns[$name]))throw new DomainException('Falta la columna '.$name.' en la primera hoja.');
    $rows=[];$errors=[];$skipped=[];$dates=[];
    foreach($input as $line) {
        try {
            $get=function(string $name)use($columns,$line):string { if(!isset($columns[$name]))return ''; $c=$line['cells'][$columns[$name]]??['value'=>'','formula'=>false];if($c['formula'])throw new DomainException('Las fórmulas no se importan. Pega sus resultados como valores.');return trim($c['value']); };
            $rut=normalize_rut($get('rut'));if(!rut_valid($rut)||$rut!==$person['rut'])throw new DomainException('El RUT no coincide con la persona seleccionada.');
            $date=import_date($get('fecha'),$book['date1904']);if(isset($dates[$date]))throw new DomainException('Fecha repetida en el archivo.');$dates[$date]=true;
            $in=import_clock($get('ingreso'));$out=import_clock($get('salida'));
            [$inLat,$inLng]=import_coordinates($get('lat_ingreso'),$get('long_ingreso'));[$outLat,$outLng]=import_coordinates($get('lat_salida'),$get('long_salida'));
            if(!$in&&!$out) {if($inLat!==null||$outLat!==null)throw new DomainException('Hay coordenadas sin marcado.');$skipped[]=['line'=>$line['line'],'date'=>$date,'why'=>'Sin horas: se conserva cualquier registro existente.'];continue;}
            if(!$in)throw new DomainException('Hay salida sin ingreso.');if(!$out&&$outLat!==null)throw new DomainException('Hay coordenadas de salida sin hora de salida.');
            if($date>today())throw new DomainException('No se admiten marcados futuros.');
            $start=import_timestamp($date,$in);$end=$out?import_timestamp($date,$out):null;
            if(($end && $end<=$start)||$start>utc_now()||($end && $end>utc_now()))throw new DomainException('Las horas deben ser pasadas, con salida posterior al ingreso y dentro del mismo día.');
            $rows[]=['line'=>$line['line'],'date'=>$date,'clock_in'=>$start,'clock_out'=>$end,'in_lat'=>$inLat,'in_lng'=>$inLng,'out_lat'=>$outLat,'out_lng'=>$outLng];
        } catch(DomainException $e) { $errors[]='Fila '.$line['line'].': '.$e->getMessage(); }
    }
    usort($rows,static fn($a,$b)=>strcmp($a['date'],$b['date']));
    return ['sheet'=>$book['name'],'rows'=>$rows,'errors'=>$errors,'skipped'=>$skipped];
}
