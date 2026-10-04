<?php
declare(strict_types=1);
namespace BloodHub\Services;

use RuntimeException;

/** Small dependency-free A4 PDF writer used for immutable report artifacts. */
final class SimplePdf
{
    private array $pages=[];
    private array $ops=[];
    private array $images=[];
    private float $y=744;

    public function __construct(private string $title='Laudo')
    {
        $root=dirname(__DIR__,2).'/public/assets/images/';
        $this->registerPng('BloodHub',$root.'bloodhub-logo-horizontal.png');
        $this->registerPng('Colsan',$root.'colsan.png');
        $this->page();
    }

    private function esc(string $text):string
    {
        $text=str_replace(['–','—','‑'],['-','-','-'],$text);
        $encoded=iconv('UTF-8','windows-1252//TRANSLIT//IGNORE',$text);
        if($encoded===false)$encoded=preg_replace('/[^\x20-\x7E]/','?',$text)??'';
        return str_replace(['\\','(',')'],['\\\\','\(','\)'],$encoded);
    }

    private function page():void
    {
        if($this->ops)$this->pages[]=$this->ops;
        $this->ops=[];
        $this->y=744;
        $this->drawImageOrText('BloodHub',42,778,132,44,'BloodHub',10,false);
        $this->text(182,817,'LABORATÓRIO DE CONTROLE DE QUALIDADE',9,true);
        $this->text(226,801,'Laudo de Hemocomponente',10,true);
        $this->drawImageOrText('Colsan',504,763,49,75,'COLSAN',10,true);
        $this->strokeColor(.76,.79,.83);
        $this->line(42,754,553,754);
        $this->strokeColor(.08,.15,.25);
    }

    private function room(float $need):bool
    {
        if($this->y-$need>=55)return false;
        $this->page();
        return true;
    }

    public function text(float $x,float $y,string $text,float $size=9,bool $bold=false):void
    {
        $font=$bold?'F2':'F1';
        $this->ops[]="BT /$font $size Tf $x $y Td (".$this->esc($text).') Tj ET';
    }

    public function line(float $x1,float $y1,float $x2,float $y2):void{$this->ops[]="$x1 $y1 m $x2 $y2 l S";}
    private function strokeColor(float $r,float $g,float $b):void{$this->ops[]="$r $g $b RG";}
    private function fillColor(float $r,float $g,float $b):void{$this->ops[]="$r $g $b rg";}
    private function rectangle(float $x,float $y,float $w,float $h,bool $fill=true):void{$this->ops[]="$x $y $w $h re ".($fill?'f':'S');}

    public function heading(string $text):void
    {
        $this->room(25);$this->y-=12;$this->text(42,$this->y,$text,10,true);
        $this->line(42,$this->y-4,553,$this->y-4);$this->y-=14;
    }

    public function row(string $label,string $value):void
    {
        $this->room(15);$this->text(42,$this->y,$label,8,true);$this->text(165,$this->y,$value,9);$this->y-=12;
    }

    public function paragraph(string $text):void
    {
        foreach($this->wrap($text,92)as$line){$this->room(14);$this->text(42,$this->y,$line,9);$this->y-=13;}
    }

    public function results(array $rows):void
    {
        $this->room(32);
        $this->resultsHeader();
        foreach($rows as$row){
            $columns=[
                $this->wrap((string)$row['parameter'],21),
                $this->wrap((string)$row['result'],15),
                $this->wrap((string)$row['reference'],20),
                $this->wrap((string)$row['method_equipment'],24),
            ];
            $lineCount=max(array_map('count',$columns));
            $rowHeight=6+($lineCount*11)+6;
            if($this->room($rowHeight))$this->resultsHeader();
            $rowTop=$this->y;
            $firstBaseline=$rowTop-14;
            for($i=0;$i<$lineCount;$i++){
                $baseline=$firstBaseline-($i*11);
                $this->text(42,$baseline,$columns[0][$i]??'',8,$i===0);
                $this->text(175,$baseline,$columns[1][$i]??'',8);
                $this->text(275,$baseline,$columns[2][$i]??'',7);
                $this->text(405,$baseline,$columns[3][$i]??'',7);
            }
            $rowBottom=$rowTop-$rowHeight;
            $this->strokeColor(.86,.88,.91);
            $this->line(42,$rowBottom,553,$rowBottom);
            $this->strokeColor(.08,.15,.25);
            $this->y=$rowBottom;
        }
    }

    private function resultsHeader():void
    {
        $height=26;
        $top=$this->y;
        $bottom=$top-$height;
        $this->fillColor(.95,.96,.98);
        $this->rectangle(42,$bottom,511,$height);
        $this->fillColor(0,0,0);
        $baseline=$bottom+9;
        $this->text(48,$baseline,'PARÂMETRO',7,true);
        $this->text(175,$baseline,'RESULTADO',7,true);
        $this->text(275,$baseline,'REFERÊNCIA',7,true);
        $this->text(405,$baseline,'MÉTODO / EQUIPAMENTO',7,true);
        $this->strokeColor(.08,.15,.25);
        $this->line(42,$bottom,553,$bottom);
        $this->y=$bottom;
    }

    private function wrap(string $text,int $limit):array
    {
        $words=preg_split('/\s+/u',trim($text))?:[];$out=[];$line='';
        foreach($words as$word){$candidate=$line.($line?' ':'').$word;if(mb_strlen($candidate)>$limit){if($line!=='')$out[]=$line;$line=$word;}else$line=$candidate;}
        if($line!==''||!$out)$out[]=$line;
        return$out;
    }

    private function drawImageOrText(string $name,float $x,float $y,float $w,float $h,string $fallback,float $fontSize,bool $right):void
    {
        if(isset($this->images[$name])){$this->ops[]="q $w 0 0 $h $x $y cm /Im$name Do Q";return;}
        $this->text($right?$x+$w-42:$x,$y+$h/2,$fallback,$fontSize,true);
    }

    private function registerPng(string $name,string $path):void
    {
        if(!is_file($path))return;
        try{$this->images[$name]=$this->decodePng($path);}catch(\Throwable){/* Text fallback keeps PDF generation available. */}
    }

    /** Decode non-interlaced 8-bit RGB/RGBA PNGs into PDF image streams. */
    private function decodePng(string $path):array
    {
        $png=file_get_contents($path);
        if($png===false||substr($png,0,8)!=="\x89PNG\r\n\x1a\n")throw new RuntimeException('PNG inválido.');
        $offset=8;$width=$height=$color=null;$compressed='';
        while($offset<strlen($png)){
            $length=unpack('N',substr($png,$offset,4))[1];$type=substr($png,$offset+4,4);$data=substr($png,$offset+8,$length);$offset+=12+$length;
            if($type==='IHDR'){[$width,$height,$depth,$color,,,$interlace]=array_values(unpack('Nwidth/Nheight/Cdepth/Ccolor/Ccompression/Cfilter/Cinterlace',$data));if($depth!==8||!in_array($color,[2,6],true)||$interlace!==0)throw new RuntimeException('Formato PNG não suportado.');}
            elseif($type==='IDAT')$compressed.=$data;
            elseif($type==='IEND')break;
        }
        if(!$width||!$height)throw new RuntimeException('PNG sem dimensões.');
        $raw=gzuncompress($compressed);if($raw===false)throw new RuntimeException('PNG corrompido.');
        $channels=$color===6?4:3;$stride=$width*$channels;$previous=array_fill(0,$stride,0);$rgb='';$alpha='';$position=0;
        for($row=0;$row<$height;$row++){
            $filter=ord($raw[$position++]);$scan=array_values(unpack('C*',substr($raw,$position,$stride)));$position+=$stride;$decoded=[];
            for($i=0;$i<$stride;$i++){
                $left=$i>=$channels?$decoded[$i-$channels]:0;$up=$previous[$i]??0;$upLeft=$i>=$channels?($previous[$i-$channels]??0):0;
                $predictor=match($filter){0=>0,1=>$left,2=>$up,3=>intdiv($left+$up,2),4=>$this->paeth($left,$up,$upLeft),default=>throw new RuntimeException('Filtro PNG não suportado.')};
                $decoded[$i]=($scan[$i]+$predictor)&255;
            }
            for($i=0;$i<$stride;$i+=$channels){$rgb.=chr($decoded[$i]).chr($decoded[$i+1]).chr($decoded[$i+2]);if($channels===4)$alpha.=chr($decoded[$i+3]);}
            $previous=$decoded;
        }
        return['width'=>$width,'height'=>$height,'data'=>gzcompress($rgb,9),'alpha'=>$channels===4?gzcompress($alpha,9):null];
    }

    private function paeth(int $a,int $b,int $c):int{$p=$a+$b-$c;$pa=abs($p-$a);$pb=abs($p-$b);$pc=abs($p-$c);return$pa<=$pb&&$pa<=$pc?$a:($pb<=$pc?$b:$c);}

    public function output():string
    {
        $this->pages[]=$this->ops;$objects=[];$objects[1]='<< /Type /Catalog /Pages 2 0 R >>';
        $pageIds=[];$contentIds=[];$next=5;foreach($this->pages as$_){$pageIds[]=$next++;$contentIds[]=$next++;}
        $imageIds=[];foreach($this->images as$name=>$image){$imageIds[$name]=['image'=>$next++,'mask'=>$image['alpha']!==null?$next++:null];}
        $kids=implode(' ',array_map(fn($id)=>$id.' 0 R',$pageIds));$objects[2]='<< /Type /Pages /Kids ['.$kids.'] /Count '.count($pageIds).' >>';
        $objects[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';$objects[4]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $xObjects='';foreach($imageIds as$name=>$ids)$xObjects.='/Im'.$name.' '.$ids['image'].' 0 R ';
        foreach($this->pages as$i=>$ops){
            $footer=$this->esc('BloodHub - Laudo eletrônico | Página '.($i+1).' de '.count($this->pages));
            $stream="0.7 w 0.08 0.15 0.25 RG\n".implode("\n",$ops)."\nBT /F1 7 Tf 42 28 Td ($footer) Tj ET";
            $resources='<< /Font << /F1 3 0 R /F2 4 0 R >>'.($xObjects!==''?' /XObject << '.$xObjects.'>>':'').' >>';
            $objects[$pageIds[$i]]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources '.$resources.' /Contents '.$contentIds[$i].' 0 R >>';
            $objects[$contentIds[$i]]='<< /Length '.strlen($stream).">>\nstream\n$stream\nendstream";
        }
        foreach($this->images as$name=>$image){$ids=$imageIds[$name];$mask=$ids['mask']?' /SMask '.$ids['mask'].' 0 R':'';$data=$image['data'];$objects[$ids['image']]='<< /Type /XObject /Subtype /Image /Width '.$image['width'].' /Height '.$image['height'].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode'.$mask.' /Length '.strlen($data).">>\nstream\n$data\nendstream";if($ids['mask']){$alpha=$image['alpha'];$objects[$ids['mask']]='<< /Type /XObject /Subtype /Image /Width '.$image['width'].' /Height '.$image['height'].' /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length '.strlen($alpha).">>\nstream\n$alpha\nendstream";}}
        ksort($objects);$pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offset=[0];foreach($objects as$id=>$object){$offset[$id]=strlen($pdf);$pdf.="$id 0 obj\n$object\nendobj\n";}
        $xref=strlen($pdf);$size=max(array_keys($objects))+1;$pdf.="xref\n0 $size\n0000000000 65535 f \n";for($i=1;$i<$size;$i++)$pdf.=sprintf('%010d 00000 n ',$offset[$i])."\n";
        return$pdf.'trailer << /Size '.$size." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    }
}
