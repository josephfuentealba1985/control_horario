<?php
declare(strict_types=1);

// Minimal standards-based ZIP container (stored entries, UTF-8 names, CRC32).
// This keeps XLSX exports independent of extensions or package managers.
function zip_store(array $files): string {
    $body=''; $directory=''; $offset=0;
    foreach($files as $name=>$content) {
        $size=strlen($content); $crc=crc32($content); $nameLength=strlen($name);
        $local=pack('VvvvvvVVVvv',0x04034b50,20,0x0800,0,0,33,$crc,$size,$size,$nameLength,0).$name.$content;
        $directory.=pack('VvvvvvvVVVvvvvvVV',0x02014b50,20,20,0x0800,0,0,33,$crc,$size,$size,$nameLength,0,0,0,0,0,$offset).$name;
        $body.=$local; $offset+=strlen($local);
    }
    return $body.$directory.pack('VvvvvVVv',0x06054b50,0,0,count($files),count($files),strlen($directory),strlen($body),0);
}
function xml_text(string $value): string { $value=preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u','',$value)??''; return htmlspecialchars($value,ENT_XML1|ENT_QUOTES,'UTF-8'); }
function excel_column(int $i): string { $s=''; for($i++;$i>0;$i=intdiv($i-1,26)) $s=chr(65+($i-1)%26).$s; return $s; }
function xlsx_bytes(array $sheets): string {
    $types='<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    $book='<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
    $rels='<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    $files=['_rels/.rels'=>'<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>'];
    $n=0;
    foreach($sheets as $name=>$data) {
        $n++; $types.='<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $book.='<sheet name="'.xml_text($name).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
        $rels.='<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
        $last=excel_column(count($data[0])-1).count($data);
        $sheet='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:'.$last.'"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>';
        foreach($data[0] as $i=>$heading) $sheet.='<col min="'.($i+1).'" max="'.($i+1).'" width="'.($i===0?28:22).'" customWidth="1"/>';
        $sheet.='</cols><sheetData>';
        foreach($data as $r=>$values) {
            $sheet.='<row r="'.($r+1).'">';
            foreach($values as $c=>$value) {
                $ref=excel_column($c).($r+1); $style=$r===0?'1':'0';
                if(is_int($value) || is_float($value)) $sheet.='<c r="'.$ref.'" s="'.$style.'"><v>'.$value.'</v></c>';
                else $sheet.='<c r="'.$ref.'" t="inlineStr" s="'.$style.'"><is><t xml:space="preserve">'.xml_text((string)$value).'</t></is></c>';
            }
            $sheet.='</row>';
        }
        $sheet.='</sheetData><autoFilter ref="A1:'.$last.'"/></worksheet>';
        $files['xl/worksheets/sheet'.$n.'.xml']=$sheet;
    }
    $rels.='<Relationship Id="rId'.($n+1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    $files['[Content_Types].xml']=$types.'</Types>'; $files['xl/workbook.xml']=$book.'</sheets></workbook>'; $files['xl/_rels/workbook.xml.rels']=$rels;
    $files['xl/styles.xml']='<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF342B4E"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFill="1" applyFont="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    return zip_store($files);
}

function pdf_string(string $text): string {
    $text=str_replace(['−','—','–',"\r","\n","\t"],['-','-','-',' ',' ',' '],$text);
    $converted=iconv('UTF-8','Windows-1252//TRANSLIT',$text); $text=$converted===false?'':$converted;
    $text=preg_replace('/[\x00-\x1F\x7F]/','',$text)??'';
    return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$text);
}
function pdf_text(float $x,float $y,string $text,int $size=9,bool $bold=false,string $color='0.16 0.18 0.24'): string {
    return 'BT /'.($bold?'F2':'F1').' '.$size.' Tf '.$color.' rg 1 0 0 1 '.$x.' '.$y.' Tm ('.pdf_string($text).") Tj ET\n";
}
function pdf_wrap(string $text,int $limit): array {
    $text=preg_replace('/\s+/u',' ',$text)??''; $chars=preg_split('//u',$text,-1,PREG_SPLIT_NO_EMPTY)?:[]; $lines=[];
    while(count($chars)>$limit) { $break=$limit; for($i=$limit;$i>max(0,$limit-15);$i--) if($chars[$i]===' ') {$break=$i;break;} $lines[]=implode('',array_splice($chars,0,$break)); if(($chars[0]??'')===' ') array_shift($chars); }
    $lines[]=implode('',$chars); return $lines;
}
function pdf_bytes(string $company,string $period,array $sections): string {
    $streams=[]; $stream=''; $page=0; $y=0;
    $newPage=function(string $title,array $columns) use (&$streams,&$stream,&$page,&$y,$company,$period): void {
        if($stream!=='') $streams[]=$stream; $page++; $stream="0.10 0.12 0.17 rg 0 502 842 93 re f\n";
        $stream.=pdf_text(34,558,'CONTROL HORARIO',17,true,'1 1 1');
        $companyLines=pdf_wrap($company,120);
        foreach($companyLines as $i=>$line) $stream.=pdf_text(34,540-$i*12,$line,9,false,'0.85 0.85 0.92');
        $stream.=pdf_text(34,509,$period,9,false,'0.85 0.85 0.92');
        $stream.=pdf_text(34,478,$title,12,true); $stream.="0.92 0.92 0.95 rg 34 444 774 24 re f\n";
        foreach($columns as $column) $stream.=pdf_text($column[0],452,$column[1],9,true);
        $stream.=pdf_text(34,22,'Colación incluida. Registros abiertos no suman horas. Fechas y horas locales.',8);
        $stream.=pdf_text(644,22,'LOOPERS · Página '.$page,8); $y=428;
    };
    foreach($sections as $section) {
        $newPage($section['title'],$section['columns']);
        foreach($section['rows'] as $values) {
            $wrapped=[]; $lineCount=1;
            foreach($values as $i=>$value) { $wrapped[$i]=pdf_wrap((string)$value,$section['columns'][$i][2]); $lineCount=max($lineCount,count($wrapped[$i])); }
            $height=$lineCount*12+12;
            if($y-$height<46) $newPage($section['title'].' (continuación)',$section['columns']);
            foreach($wrapped as $i=>$lines) foreach($lines as $j=>$line) $stream.=pdf_text($section['columns'][$i][0],$y-$j*12,$line,8);
            $y-=$height; $stream.='0.86 0.87 0.90 RG 0.3 w 34 '.($y+8).' m 808 '.($y+8)." l S\n";
        }
        if(!$section['rows']) $stream.=pdf_text(34,$y,'No hay datos para el período seleccionado.',10);
    }
    if($stream!=='') $streams[]=$stream;
    $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',2=>'',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',4=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>']; $kids=[];
    foreach($streams as $s) { $id=count($objects)+1; $contentId=$id+1; $kids[]=$id.' 0 R'; $objects[$id]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$contentId.' 0 R >>'; $objects[$contentId]='<< /Length '.strlen($s).">>\nstream\n".$s.'endstream'; }
    $objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($kids).' >>';
    $pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"; $offsets=[0]; foreach($objects as $id=>$object) { $offsets[$id]=strlen($pdf); $pdf.=$id." 0 obj\n".$object."\nendobj\n"; }
    $xref=strlen($pdf); $pdf.='xref' . "\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
    for($i=1;$i<=count($objects);$i++) $pdf.=sprintf('%010d 00000 n ',$offsets[$i])."\n";
    return $pdf.'trailer << /Size '.(count($objects)+1).' /Root 1 0 R >>' . "\nstartxref\n".$xref."\n%%EOF";
}
