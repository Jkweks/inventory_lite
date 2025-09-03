<?php
function parse_job_materials_xlsx(string $path): array {
    $requiredSheets = [
        'Accessories',
        'Accessories (2)',
        'Accessories (3)',
        'Stock Length',
        'Stock Length (2)',
        'Stock Length (3)'
    ];
    if (!class_exists('ZipArchive')) {
        throw new Exception('ZipArchive extension not installed');
    }
    $zip = new ZipArchive();
    if($zip->open($path)!==true){
        throw new Exception('Unable to open spreadsheet');
    }
    $shared = [];
    if(($xml=$zip->getFromName('xl/sharedStrings.xml'))!==false){
        $sx=simplexml_load_string($xml);
        if($sx && isset($sx->si)){
            foreach($sx->si as $si){
                if(isset($si->t)){
                    $shared[] = (string)$si->t;
                }else{
                    $text='';
                    foreach($si->r as $r){ $text.=(string)$r->t; }
                    $shared[] = $text;
                }
            }
        }
    }
    $sheets = [];
    $workbook = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
    $rels = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
    $relMap=[];
    if($rels){
        foreach($rels->Relationship as $rel){
            $relMap[(string)$rel['Id']] = (string)$rel['Target'];
        }
    }
    if($workbook && isset($workbook->sheets)){
        foreach($workbook->sheets->sheet as $sheet){
            $name=(string)$sheet['name'];
            $rid=(string)$sheet->attributes('r',true)['id'];
            if(isset($relMap[$rid])){
                $sheets[$name]='xl/'.ltrim($relMap[$rid],'/');
            }
        }
    }
    $rows=[];
    foreach($requiredSheets as $sheetName){
        if(!isset($sheets[$sheetName])) continue;
        $sheetXml=$zip->getFromName($sheets[$sheetName]);
        if($sheetXml===false) continue;
        $sx=simplexml_load_string($sheetXml);
        if(!$sx || !isset($sx->sheetData)) continue;
        foreach($sx->sheetData->row as $row){
            $r=(int)$row['r'];
            if($r<11 || $r>46) continue;
            $vals=['A'=>null,'B'=>null,'C'=>null];
            foreach($row->c as $c){
                $ref=(string)$c['r'];
                $col=preg_replace('/\d+/','',$ref);
                if(!isset($vals[$col])) continue;
                $v=(string)$c->v;
                if((string)$c['t']==='s'){
                    $v=$shared[(int)$v] ?? '';
                }
                $vals[$col]=$v;
            }
            $rows[]=[ $vals['A'],$vals['B'],$vals['C'] ];
        }
    }
    $zip->close();
    return $rows;
}
