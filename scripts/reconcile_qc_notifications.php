<?php
declare(strict_types=1);

use BloodHub\Core\Database;
use BloodHub\Services\{QcNotificationService,SpecificationEvaluator};

if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
spl_autoload_register(static function(string$class):void{$prefix='BloodHub\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require$file;});

$apply=in_array('--apply',$argv,true);
$pdo=Database::connection();
$sql="SELECT tr.id result_id,tr.result_value_numeric,tr.result_value_text,tr.recorded_at,st.sample_id,st.test_id,e.conformity_status,n.id notification_id
      FROM test_results tr
      JOIN sample_tests st ON st.id=tr.sample_test_id AND st.status='completed'
      JOIN samples s ON s.id=st.sample_id AND s.purpose='quality_control' AND s.status='completed'
      JOIN tests t ON t.id=st.test_id AND t.code<>'BACTERIOLOGY'
      LEFT JOIN test_result_spec_evaluations e ON e.test_result_id=tr.id
      LEFT JOIN qc_notifications n ON n.result_id=tr.id
      WHERE tr.result_value_numeric IS NOT NULL OR NULLIF(TRIM(tr.result_value_text),'') IS NOT NULL
      ORDER BY tr.id";
$rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);$nonconforming=$existing=$missing=$created=0;
foreach($rows as$row){$status=$row['conformity_status'];if($status===null){$actual=$row['result_value_numeric']!==null?$row['result_value_numeric']:$row['result_value_text'];$evaluation=SpecificationEvaluator::evaluate((int)$row['sample_id'],(int)$row['test_id'],$actual,substr((string)$row['recorded_at'],0,10));$status=$evaluation['status'];}
    if($status!=='NONCONFORMING')continue;$nonconforming++;if($row['notification_id']){$existing++;continue;}$missing++;
    fwrite(STDOUT,sprintf("AUSENTE result_id=%d sample_id=%d test_id=%d\n",$row['result_id'],$row['sample_id'],$row['test_id']));
    if(!$apply)continue;
    try{$pdo->beginTransaction();if($row['conformity_status']===null)SpecificationEvaluator::persistForResult((int)$row['result_id'],substr((string)$row['recorded_at'],0,10));$id=QcNotificationService::createForResult((int)$row['result_id']);$pdo->commit();if($id)$created++;}
    catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,"FALHA result_id={$row['result_id']}: {$e->getMessage()}\n");}
}
fwrite(STDOUT,"\nModo: ".($apply?'APPLY':'DRY-RUN')."\n");
fwrite(STDOUT,"Foram analisados: ".count($rows)." resultados\n");
fwrite(STDOUT,"Não conformidades encontradas: {$nonconforming}\n");
fwrite(STDOUT,"Notificações existentes: {$existing}\n");
fwrite(STDOUT,"Notificações ausentes: {$missing}\n");
if($apply)fwrite(STDOUT,"Notificações criadas: {$created}\n");
