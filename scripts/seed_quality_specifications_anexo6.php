<?php
declare(strict_types=1);
use BloodHub\Core\Database;
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
spl_autoload_register(static function(string $class):void{$p='BloodHub\\';if(str_starts_with($class,$p)){ $f=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($p))).'.php';if(is_file($f))require $f;}});
$apply=in_array('--apply',$argv,true);if(!$apply&&!in_array('--dry-run',$argv,true)){fwrite(STDERR,"Uso: php scripts/seed_quality_specifications_anexo6.php --dry-run|--apply\n");exit(1);}
$pdo=Database::connection();$source='Portaria GM/MS nº 11.685, de 2 de julho de 2026';$reference='Anexo 6 do Anexo IV-B — Especificações dos Componentes Sanguíneos — Controle de Qualidade';
$rules=[
 ['CH','HEMOGLOBIN_PER_UNIT','GTE','45',null,null,'g/U',null,null,''],
 ['CH','HEMOLYSIS_DEGREE','LT',null,'0.8',null,'%',null,null,'Avaliado no último dia de armazenamento.'],
 ['CH','HEMATOCRIT','BETWEEN','65','80',null,'%','PRESERVATIVE','CPDA-1',''],
];
$find=static function(string $table,string $code)use($pdo):array{$q=$pdo->prepare("SELECT id FROM {$table} WHERE code=:code");$q->execute(['code'=>$code]);return $q->fetchAll(PDO::FETCH_COLUMN);};$inserted=0;
foreach($rules as [$bc,$test,$type,$min,$max,$text,$unit,$condition,$conditionCode,$notes]){$components=$find('blood_components',$bc);$tests=$find('tests',$test);$preservatives=$conditionCode?$find('preservatives',$conditionCode):[null];if(count($components)!==1||count($tests)!==1||count($preservatives)!==1){echo "PULADO (mapeamento ausente/ambíguo): {$bc} + {$test}".PHP_EOL;continue;}$q=$pdo->prepare('SELECT 1 FROM test_blood_components WHERE blood_component_id=:c AND test_id=:t');$q->execute(['c'=>$components[0],'t'=>$tests[0]]);if(!$q->fetchColumn()){echo "PULADO (teste não associado): {$bc} + {$test}".PHP_EOL;continue;}$label="{$bc} + {$test} {$type}";if(!$apply){echo "DRY-RUN: inseriria {$label}".PHP_EOL;continue;}$q=$pdo->prepare('SELECT id FROM blood_component_test_specifications WHERE blood_component_id=:c AND test_id=:t AND rule_type=:r AND COALESCE(preservative_id,0)=:p AND effective_to IS NULL');$q->execute(['c'=>$components[0],'t'=>$tests[0],'r'=>$type,'p'=>$preservatives[0]?:0]);if($q->fetchColumn()){echo "JÁ EXISTE: {$label}".PHP_EOL;continue;}$pdo->prepare('INSERT INTO blood_component_test_specifications(blood_component_id,test_id,rule_type,min_value,max_value,expected_text,unit,preservative_id,condition_type,condition_value,source_name,source_reference,notes,active) VALUES(:c,:t,:r,:min,:max,:text,:unit,:p,:condition,:cv,:source,:reference,:notes,1)')->execute(['c'=>$components[0],'t'=>$tests[0],'r'=>$type,'min'=>$min,'max'=>$max,'text'=>$text,'unit'=>$unit,'p'=>$preservatives[0],'condition'=>$condition,'cv'=>$conditionCode,'source'=>$source,'reference'=>$reference,'notes'=>$notes?:null]);echo "INSERIDO: {$label}".PHP_EOL;$inserted++;}
echo ($apply?"Total inserido: {$inserted}":'Nenhuma alteração realizada. Use --apply após revisão.').PHP_EOL;
echo "Não vinculados automaticamente: CP (tipo normativo ambíguo), PFC sem testes associados, Bacteriológico versus Microbiológica e todos os componentes/testes ausentes do catálogo.".PHP_EOL;
