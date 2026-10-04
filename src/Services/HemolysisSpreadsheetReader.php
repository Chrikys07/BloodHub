<?php
declare(strict_types=1);
namespace BloodHub\Services;
use DomainException;
use ZipArchive;

final class HemolysisSpreadsheetReader
{
    private const REQUIRED=['sample name','abs(370nm)','abs(415nm)','abs(510nm)','abs(577nm)','abs(600nm)'];
    public static function read(string $path,string $extension):array
    {
        $rows=$extension==='xlsx'?self::xlsx($path):self::xls($path);$header=null;$map=[];foreach($rows as $i=>$row){$normalized=array_map(fn($v)=>mb_strtolower(trim((string)$v)), $row);if(!array_diff(self::REQUIRED,$normalized)){$header=$i;foreach($normalized as $c=>$name)$map[$name]=$c;break;}}if($header===null)throw new DomainException('Cabeçalho do espectrofotômetro não encontrado no arquivo.');
        $out=[];$ignored=0;$errors=[];for($i=$header+1,$n=count($rows);$i<$n;$i++){$row=$rows[$i];$get=fn(string $name)=>trim((string)($row[$map[$name]??-1]??''));$name=$get('sample name');$status=mb_strtolower($get('status'));if($name===''||$status==='unmeasured'||preg_match('/^[\pL]{1,6}$/u',$name)){$ignored++;continue;}$item=['sample_name'=>$name,'source_row'=>$i+1];$valid=true;foreach(['370','415','510','577','600'] as $nm){try{$item['abs_'.$nm]=HemolysisCalculator::decimal($get("abs({$nm}nm)"));}catch(DomainException){$valid=false;}}if(!$valid){$ignored++;$errors[]='Linha '.($i+1).': absorbâncias incompletas ou inválidas.';continue;}$out[]=$item;}
        return ['rows'=>$out,'ignored'=>$ignored,'errors'=>$errors];
    }
    private static function xlsx(string $path):array
    {
        $zip=new ZipArchive();if($zip->open($path)!==true)throw new DomainException('O conteúdo não é um arquivo XLSX legível.');$shared=[];$xml=$zip->getFromName('xl/sharedStrings.xml');if($xml!==false){$doc=new \DOMDocument();$doc->loadXML($xml);$xp=new \DOMXPath($doc);foreach($xp->query('//*[local-name()="si"]') as $si){$text='';foreach($xp->query('.//*[local-name()="t"]',$si) as $t)$text.=$t->textContent;$shared[]=$text;}}$sheet=$zip->getFromName('xl/worksheets/sheet1.xml');if($sheet===false){$zip->close();throw new DomainException('A primeira planilha não pôde ser lida.');}$doc=new \DOMDocument();if(!$doc->loadXML($sheet)){throw new DomainException('XML interno da planilha inválido.');}$xp=new \DOMXPath($doc);$rows=[];foreach($xp->query('//*[local-name()="sheetData"]/*[local-name()="row"]') as $r){$line=[];foreach($xp->query('./*[local-name()="c"]',$r) as $c){$ref=$c->getAttribute('r');preg_match('/^[A-Z]+/',$ref,$m);$col=self::columnIndex($m[0]??'A');$type=$c->getAttribute('t');$nodes=$xp->query('.//*[local-name()="t"]',$c);$value=$nodes->length?$nodes->item(0)->textContent:'';if($type!=='inlineStr'){$v=$xp->query('./*[local-name()="v"]',$c);$value=$v->length?$v->item(0)->textContent:'';}if($type==='s')$value=$shared[(int)$value]??'';$line[$col]=$value;}$rows[]=$line;}$zip->close();return $rows;
    }
    private static function xls(string $path):array
    {
        $raw=file_get_contents($path);if($raw===false)throw new DomainException('Falha ao ler o arquivo XLS.');if(str_starts_with($raw,"\xD0\xCF\x11\xE0"))throw new DomainException('XLS binário detectado. Salve o arquivo do equipamento como XLSX para importação segura neste servidor.');
        libxml_use_internal_errors(true);$doc=simplexml_load_string($raw);if($doc){$doc->registerXPathNamespace('ss','urn:schemas-microsoft-com:office:spreadsheet');$result=[];foreach($doc->xpath('//ss:Worksheet[1]/ss:Table/ss:Row')?:[] as $r){$line=[];$col=0;foreach($r->xpath('./ss:Cell')?:[] as $cell){$attrs=$cell->attributes('urn:schemas-microsoft-com:office:spreadsheet');if(isset($attrs['Index']))$col=(int)$attrs['Index']-1;$data=$cell->xpath('./ss:Data');$line[$col++]=isset($data[0])?(string)$data[0]:'';}$result[]=$line;}if($result)return $result;}throw new DomainException('O conteúdo não é uma planilha XLS/XML legível.');
    }
    private static function nodeText($node):string{$text='';foreach($node->xpath('.//t')?:[] as $t)$text.=(string)$t;return $text;}
    private static function columnIndex(string $letters):int{$n=0;foreach(str_split($letters) as $c)$n=$n*26+ord($c)-64;return $n-1;}
}
