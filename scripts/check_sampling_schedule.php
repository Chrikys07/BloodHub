<?php
declare(strict_types=1);
use BloodHub\Services\SamplingScheduleService as S;
spl_autoload_register(static function(string$class):void{$prefix='BloodHub\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require$file;});
$fail=[];$check=static function(bool$ok,string$message)use(&$fail):void{echo($ok?'OK  ':'ERRO ').$message.PHP_EOL;if(!$ok)$fail[]=$message;};
$general=['percentage'=>1,'minimum_units'=>10,'small_production_mode'=>'standard','small_production_limit'=>null];
$floor4=$general;$floor4['minimum_units']=4;$special=$general;$special['small_production_mode']='actual_up_to_limit';$special['small_production_limit']=10;
$check(S::calculateRequired(500,$general)===10,'CH 500 exige 10');
$check(S::calculateRequired(1500,$general)===15,'CH 1500 exige 15');
$check(S::calculateRequired(250,$floor4)===4,'PFC 250 exige 4');
$check(S::calculateRequired(700,$floor4)===7,'PFC 700 exige 7');
$check(S::calculateRequired(7,$special)===7,'CHL 7 exige produção real');
$check(S::calculateRequired(50,$special)===10,'CHL 50 aplica regra geral');
$check(S::calculateRequired(0,$general)===0,'produção zero exige zero');
$pdo=BloodHub\Core\Database::connection();
$check((int)$pdo->query("SELECT COUNT(*) FROM permissions WHERE permission_key IN('sampling_schedule.view','sampling_schedule.admin')") ->fetchColumn()===2,'permissões RBAC existem');
$check((int)$pdo->query("SELECT COUNT(*) FROM sampling_production_sources s JOIN blood_components t ON t.id=s.target_blood_component_id JOIN blood_components o ON o.id=s.source_blood_component_id WHERE (t.code='PF' AND o.code='PFC') OR (t.code='PF24' AND o.code='PFC24')")->fetchColumn()===2,'fontes PF/PF24 usam IDs');
$check((int)$pdo->query("SELECT COUNT(*) FROM sampling_schedule_days d JOIN blood_components b ON b.id=d.blood_component_id WHERE b.code='CH' AND d.weekday=1 AND d.active=1")->fetchColumn()===1,'CH elegível segunda');
$check((int)$pdo->query("SELECT COUNT(*) FROM sampling_schedule_days d JOIN blood_components b ON b.id=d.blood_component_id WHERE b.code='CH' AND d.weekday=2 AND d.active=1")->fetchColumn()===0,'CH não elegível terça');
$unit=(int)$pdo->query("SELECT id FROM units WHERE unit_type='processing' AND status='active' ORDER BY id LIMIT 1")->fetchColumn();
if($unit){$summary=S::buildMonthlySummary($unit,date('Y-m'));$check(count($summary)>0,'resumo mensal integra produção, regras e amostras');$pf=array_values(array_filter($summary,fn($r)=>$r['code']==='PF'));$pfc=array_values(array_filter($summary,fn($r)=>$r['code']==='PFC'));if($pf&&$pfc)$check($pf[0]['produced']===$pfc[0]['produced'],'PF herda produção de PFC');}
exit($fail?1:0);
