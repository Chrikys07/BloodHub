<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\{Auth,Database,Permission};
use DateTimeImmutable;
use PDO;

/**
 * Agregação somente-leitura do Dashboard Global.
 * Não avalia limites laboratoriais: consome snapshots já persistidos pelo motor.
 */
final class GlobalDashboardService
{
    private const MONTHS=[1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];

    public static function getFilters(array $input=[]):array
    {
        $pdo=Database::connection();
        $components=$pdo->query("SELECT DISTINCT bc.id,bc.code,bc.name FROM blood_components bc LEFT JOIN test_blood_components tbc ON tbc.blood_component_id=bc.id LEFT JOIN samples s ON s.blood_component_id=bc.id AND s.purpose='quality_control' WHERE bc.status='active' AND (tbc.test_id IS NOT NULL OR s.id IS NOT NULL) ORDER BY bc.code,bc.name")->fetchAll(PDO::FETCH_ASSOC);
        $componentId=(int)($input['blood_component_id']??0);
        if(!in_array($componentId,array_map(fn($r)=>(int)$r['id'],$components),true)){
            $preferred=array_values(array_filter($components,fn($r)=>strtoupper((string)$r['code'])==='CH'));
            $componentId=(int)(($preferred[0]??$components[0]??[])['id']??0);
        }
        $years=$pdo->query("SELECT y FROM (SELECT DISTINCT YEAR(production_date) y FROM production_records UNION SELECT DISTINCT YEAR(production_date) y FROM samples WHERE purpose='quality_control') z WHERE y IS NOT NULL ORDER BY y DESC")->fetchAll(PDO::FETCH_COLUMN);
        $year=array_key_exists('year',$input)&&$input['year']!==''?(int)$input['year']:(int)date('Y');
        $month=array_key_exists('month',$input)&&$input['month']!==''?(int)$input['month']:(int)date('n');
        if($month<1||$month>12)$month=0;
        $units=self::accessibleUnits();$unitId=(int)($input['unit_id']??0);
        if(!in_array($unitId,array_map(fn($r)=>(int)$r['id'],$units),true))$unitId=0;
        return compact('components','componentId','years','year','month','units','unitId')+['months'=>self::MONTHS];
    }

    public static function dashboard(array $filters,int $page=1,int $perPage=10):array
    {
        $componentId=(int)$filters['componentId'];$unitId=(int)$filters['unitId'];$year=(int)$filters['year'];$month=(int)$filters['month'];
        [$from,$to]=self::range($year,$month);$allowedIds=array_map(fn($u)=>(int)$u['id'],$filters['units']);
        if($unitId)$allowedIds=array_values(array_intersect($allowedIds,[$unitId]));
        $component=current(array_filter($filters['components'],fn($c)=>(int)$c['id']===$componentId))?:['id'=>$componentId,'code'=>'','name'=>''];
        $production=self::production($componentId,$allowedIds,$from,$to);
        $samples=self::samples($componentId,$allowedIds,$from,$to);
        $tests=self::testConformity($componentId,$allowedIds,$from,$to);
        $rows=self::rows($filters['units'],$componentId,$production,$samples,$tests,$year,$month);
        $summary=self::getSummary($rows,$samples,$tests);
        $series=self::getSeries($rows,$production,$samples,$year,$month,$unitId);
        $attention=self::getAttentionItems($rows,$samples,$tests);
        usort($rows,static function(array $a,array $b):int{$period=[$b['year'],$b['month']]<=>[$a['year'],$a['month']];return $period?:strnatcasecmp($a['unit_name'],$b['unit_name']);});
        $total=count($rows);$pages=max(1,(int)ceil($total/$perPage));$page=max(1,min($page,$pages));
        return compact('component','summary','series','attention','total','pages','page','perPage')+[
            'testConformity'=>$tests['totals'],'testCatalog'=>$tests['catalog'],'unitStatus'=>self::unitStatus($rows),
            'globalConformity'=>$tests['global'],'rows'=>array_slice($rows,($page-1)*$perPage,$perPage),
            'period'=>self::periodLabel($year,$month),'hasData'=>($summary['production_records']+$samples['all_count'])>0,
        ];
    }

    public static function getSummary(array $rows,array $samples,array $tests):array
    {
        $produced=0;$productionRecords=0;$tested=0;$minimum=0;$met=0;$eligible=0;
        foreach($rows as$r){if($r['produced']!==null){$produced+=$r['produced'];$productionRecords+=$r['production_records'];}$tested+=$r['tested'];if($r['minimum']!==null){$minimum+=$r['minimum'];$eligible++;if($r['sampling_met'])$met++;}}
        return ['produced'=>$productionRecords?$produced:null,'production_records'=>$productionRecords,'tested'=>$tested,
            'sampling_percent'=>$productionRecords&&$produced>0?($tested/$produced*100):null,'minimum'=>$productionRecords?$minimum:null,
            'units_met'=>$met,'units_eligible'=>$eligible,'missing'=>array_sum(array_column($rows,'missing')),
            'sampling_met'=>$eligible>0&&$met===$eligible,'in_progress'=>$samples['in_progress'],
            'global_percent'=>$tests['global']['evaluated']?($tests['global']['conforming']/$tests['global']['evaluated']*100):null];
    }

    public static function getSamplingStats(array $rows):array{return self::unitStatus($rows);}
    public static function getTestConformity(array $dashboard):array{return $dashboard['testConformity']??[];}
    public static function getGlobalConformity(array $dashboard):array{return $dashboard['globalConformity']??[];}
    public static function getUnitBreakdown(array $dashboard):array{return $dashboard['rows']??[];}

    public static function getSeries(array $rows,array $production,array $samples,int $year,int $month,int $unitId):array
    {
        if($year&&$month&&$unitId){
            $days=(int)date('t',strtotime(sprintf('%04d-%02d-01',$year,$month)));$labels=[];$values=[];
            for($d=1;$d<=$days;$d++){ $key=sprintf('%04d-%02d-%02d',$year,$month,$d);$p=$production['daily'][$unitId][$key]??null;$t=$samples['daily_completed'][$unitId][$key]??0;$labels[]=(string)$d;$values[]=$p!==null&&$p>0?round($t/$p*100,2):null; }
            return compact('labels','values')+['mode'=>'Dias do mês'];
        }
        if($year&&$month&&!$unitId){$labels=[];$values=[];foreach($rows as$r){$labels[]=$r['unit_name'];$values[]=$r['sampling_percent']===null?null:round($r['sampling_percent'],2);}return compact('labels','values')+['mode'=>'Processamentos'];}
        $map=[];foreach($rows as$r){$key=sprintf('%04d-%02d',$r['year'],$r['month']);$map[$key]??=['p'=>0,'t'=>0,'has'=>false];if($r['produced']!==null){$map[$key]['p']+=$r['produced'];$map[$key]['has']=true;}$map[$key]['t']+=$r['tested'];}
        if($year&&!$month)for($m=1;$m<=12;$m++)$map[sprintf('%04d-%02d',$year,$m)]??=['p'=>0,'t'=>0,'has'=>false];ksort($map);$labels=array_keys($map);$values=array_map(fn($v)=>$v['has']&&$v['p']>0?round($v['t']/$v['p']*100,2):null,array_values($map));return compact('labels','values')+['mode'=>'Mês de produção'];
    }

    public static function getAttentionItems(array $rows,array $samples,array $tests):array
    {
        $out=[];$pending=array_values(array_filter($rows,fn($r)=>$r['minimum']!==null&&!$r['sampling_met']));
        if($pending)$out[]=['type'=>'warning','text'=>count($pending).' unidade(s)/período abaixo da amostragem mínima.'];
        foreach($tests['totals'] as$t)if($t['nonconforming']>0)$out[]=['type'=>'danger','text'=>$t['name'].': '.$t['nonconforming'].' resultado(s) não conforme(s).'];
        if($samples['in_progress']>0)$out[]=['type'=>'warning','text'=>$samples['in_progress'].' análise(s) de CQ ainda em andamento.'];
        $missingProduction=count(array_filter($rows,fn($r)=>$r['produced']===null&&$r['tested']>0));if($missingProduction)$out[]=['type'=>'warning','text'=>$missingProduction.' unidade(s)/período com produção não informada e amostras registradas.'];
        $withoutSamples=count(array_filter($rows,fn($r)=>$r['produced']!==null&&$r['produced']>0&&$r['tested']===0));if($withoutSamples)$out[]=['type'=>'warning','text'=>$withoutSamples.' unidade(s)/período com produção e nenhuma análise concluída.'];
        return $out;
    }

    private static function accessibleUnits():array
    {
        $pdo=Database::connection();$user=Auth::user()??[];$global=Auth::isAdministrator()||Permission::can('samples.scope.global')||in_array($user['role_slug']??'', ['gestao','lcqh'],true);
        if($global)return $pdo->query("SELECT id,name,code FROM units WHERE status='active' AND unit_type='processing' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $q=$pdo->prepare("SELECT DISTINCT u.id,u.name,u.code FROM units u JOIN users usr ON usr.id=:user LEFT JOIN user_units uu ON uu.user_id=usr.id AND uu.unit_id=u.id WHERE u.status='active' AND u.unit_type='processing' AND (u.id=usr.primary_unit_id OR uu.unit_id IS NOT NULL) ORDER BY u.name");$q->execute(['user'=>$user['id']??0]);return$q->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function production(int $componentId,array $unitIds,string $from,string $to):array
    {
        $out=['monthly'=>[],'daily'=>[]];if(!$unitIds)return$out;$pdo=Database::connection();
        $sources=$pdo->prepare('SELECT DISTINCT source_blood_component_id FROM sampling_production_sources WHERE target_blood_component_id=:id');$sources->execute(['id'=>$componentId]);$componentIds=array_values(array_unique(array_merge([$componentId],array_map('intval',$sources->fetchAll(PDO::FETCH_COLUMN)))));
        $q=$pdo->prepare('SELECT unit_id,production_date,blood_component_id,quantity FROM production_records WHERE unit_id IN ('.implode(',',array_fill(0,count($unitIds),'?')).') AND blood_component_id IN ('.implode(',',array_fill(0,count($componentIds),'?')).') AND production_date BETWEEN ? AND ?');$q->execute([...$unitIds,...$componentIds,$from,$to]);
        foreach($q->fetchAll(PDO::FETCH_ASSOC)as$r){$date=new DateTimeImmutable($r['production_date']);if(SamplingScheduleService::productionSourceId($componentId,$date)!==(int)$r['blood_component_id'])continue;$u=(int)$r['unit_id'];$ym=$date->format('Y-m');$out['monthly'][$u][$ym]??=['quantity'=>0,'records'=>0];$out['monthly'][$u][$ym]['quantity']+=(int)$r['quantity'];$out['monthly'][$u][$ym]['records']++;$out['daily'][$u][$r['production_date']]=($out['daily'][$u][$r['production_date']]??0)+(int)$r['quantity'];}
        return$out;
    }

    private static function samples(int $componentId,array $unitIds,string $from,string $to):array
    {
        $out=['monthly'=>[],'daily_completed'=>[],'in_progress'=>0,'all_count'=>0,'ids'=>[]];if(!$unitIds)return$out;
        $q=Database::connection()->prepare("SELECT id,origin_unit_id,production_date,status FROM samples WHERE purpose='quality_control' AND status<>'cancelled' AND blood_component_id=? AND origin_unit_id IN (".implode(',',array_fill(0,count($unitIds),'?')).") AND production_date BETWEEN ? AND ?");$q->execute([$componentId,...$unitIds,$from,$to]);
        foreach($q->fetchAll(PDO::FETCH_ASSOC)as$r){$out['all_count']++;$u=(int)$r['origin_unit_id'];$ym=substr($r['production_date'],0,7);$out['monthly'][$u][$ym]??=['completed'=>0,'in_progress'=>0,'sample_ids'=>[]];if($r['status']==='completed'){$out['monthly'][$u][$ym]['completed']++;$out['monthly'][$u][$ym]['sample_ids'][]=(int)$r['id'];$out['daily_completed'][$u][$r['production_date']]=($out['daily_completed'][$u][$r['production_date']]??0)+1;$out['ids'][]=(int)$r['id'];}else{$out['monthly'][$u][$ym]['in_progress']++;$out['in_progress']++;}}
        return$out;
    }

    private static function testConformity(int $componentId,array $unitIds,string $from,string $to):array
    {
        $pdo=Database::connection();$catalog=[];$totals=[];$byUnitMonth=[];$sampleStates=[];
        $c=$pdo->prepare("SELECT DISTINCT t.id,t.code,t.name,t.unit,COALESCE(tbc.is_required,1) is_required FROM tests t LEFT JOIN test_blood_components tbc ON tbc.test_id=t.id AND tbc.blood_component_id=:component WHERE t.status='active' AND t.is_final_result=1 AND (tbc.blood_component_id IS NOT NULL OR EXISTS(SELECT 1 FROM sample_tests st JOIN samples s ON s.id=st.sample_id WHERE st.test_id=t.id AND s.blood_component_id=:component2 AND s.purpose='quality_control')) ORDER BY t.name");$c->execute(['component'=>$componentId,'component2'=>$componentId]);foreach($c->fetchAll(PDO::FETCH_ASSOC)as$r)$catalog[(int)$r['id']]=$r;
        if($unitIds){$sql="SELECT s.id sample_id,s.origin_unit_id,DATE_FORMAT(s.production_date,'%Y-%m') ym,s.status sample_status,st.id sample_test_id,st.test_id,st.status test_status,COALESCE(tbc.is_required,1) is_required,e.conformity_status,e.specification_snapshot_text FROM samples s JOIN sample_tests st ON st.sample_id=s.id LEFT JOIN test_blood_components tbc ON tbc.test_id=st.test_id AND tbc.blood_component_id=s.blood_component_id LEFT JOIN (SELECT tr1.* FROM test_results tr1 JOIN (SELECT sample_test_id,MAX(id) id FROM test_results GROUP BY sample_test_id) latest ON latest.id=tr1.id) tr ON tr.sample_test_id=st.id LEFT JOIN test_result_spec_evaluations e ON e.test_result_id=tr.id WHERE s.purpose='quality_control' AND s.status<>'cancelled' AND s.blood_component_id=? AND s.origin_unit_id IN (".implode(',',array_fill(0,count($unitIds),'?')).") AND s.production_date BETWEEN ? AND ? AND st.status<>'cancelled'";$q=$pdo->prepare($sql);$q->execute([$componentId,...$unitIds,$from,$to]);
            foreach($q->fetchAll(PDO::FETCH_ASSOC)as$r){$sid=(int)$r['sample_id'];$key=(int)$r['origin_unit_id'].'|'.$r['ym'];$sampleStates[$sid]??=['status'=>$r['sample_status'],'required'=>[],'key'=>$key];$status=$r['test_status']==='completed'?($r['conformity_status']??'NOT_EVALUATED'):'NOT_EVALUATED';$tid=(int)$r['test_id'];if(!isset($catalog[$tid]))continue;if((int)$r['is_required'])$sampleStates[$sid]['required'][$tid]=$status;if(strtoupper((string)$catalog[$tid]['code'])==='BACTERIOLOGY'||mb_stripos((string)$catalog[$tid]['name'],'bacteriol')!==false)continue;if($r['test_status']!=='completed'||!in_array($status,['CONFORMING','NONCONFORMING'],true))continue;$byUnitMonth[$key]??=[];self::addTest($totals,$tid,$catalog[$tid],$status,$r['specification_snapshot_text']);self::addTest($byUnitMonth[$key],$tid,$catalog[$tid],$status,$r['specification_snapshot_text']);}
            self::mergeBacteriology($componentId,$unitIds,$from,$to,$catalog,$totals,$byUnitMonth,$sampleStates);
        }
        foreach($catalog as$tid=>$test)$totals[$tid]??=['id'=>$tid,'code'=>$test['code'],'name'=>$test['name'],'unit'=>$test['unit'],'conforming'=>0,'nonconforming'=>0,'evaluated'=>0,'percent'=>null,'criteria'=>[]];$targets=self::targets($componentId,array_keys($catalog),$to);foreach($totals as&$t){$t['percent']=$t['evaluated']?$t['conforming']/$t['evaluated']*100:null;$t['target']=$targets[$t['id']]??null;$t['target_status']=self::targetStatus($t['percent'],$t['target']);}unset($t);foreach($byUnitMonth as&$set)foreach($set as&$t){$t['percent']=$t['evaluated']?$t['conforming']/$t['evaluated']*100:null;$t['target']=$targets[$t['id']]??null;$t['target_status']=self::targetStatus($t['percent'],$t['target']);}
        $global=['conforming'=>0,'nonconforming'=>0,'evaluated'=>0];$globalByUnitMonth=[];foreach($sampleStates as$s){$statuses=array_values($s['required']);if($s['status']!=='completed'||!$statuses||array_diff($statuses,['CONFORMING','NONCONFORMING']))continue;$global['evaluated']++;$globalByUnitMonth[$s['key']]??=['conforming'=>0,'nonconforming'=>0,'evaluated'=>0];$globalByUnitMonth[$s['key']]['evaluated']++;if(!in_array('NONCONFORMING',$statuses,true)){$global['conforming']++;$globalByUnitMonth[$s['key']]['conforming']++;}else{$global['nonconforming']++;$globalByUnitMonth[$s['key']]['nonconforming']++;}}
        return ['catalog'=>array_values($catalog),'totals'=>array_values($totals),'by_unit_month'=>$byUnitMonth,'global'=>$global,'global_by_unit_month'=>$globalByUnitMonth];
    }

    private static function mergeBacteriology(int $componentId,array $unitIds,string $from,string $to,array &$catalog,array &$totals,array &$byUnitMonth,array &$sampleStates):void
    {
        $testId=0;foreach($catalog as$id=>$t)if(strtoupper((string)$t['code'])==='BACTERIOLOGY'||mb_stripos((string)$t['name'],'bacteriol')!==false){$testId=$id;break;}if(!$testId)return;
        $q=Database::connection()->prepare("SELECT s.id sample_id,s.origin_unit_id,DATE_FORMAT(s.production_date,'%Y-%m') ym,s.status sample_status,br.bacteriological_conformity FROM samples s JOIN bacteriology_results br ON br.id=(SELECT br2.id FROM bacteriology_results br2 WHERE br2.sample_id=s.id AND br2.is_final=1 ORDER BY br2.performed_at DESC,br2.id DESC LIMIT 1) WHERE s.purpose='quality_control' AND s.status<>'cancelled' AND s.blood_component_id=? AND s.origin_unit_id IN (".implode(',',array_fill(0,count($unitIds),'?')).") AND s.production_date BETWEEN ? AND ?");$q->execute([$componentId,...$unitIds,$from,$to]);
        foreach($q->fetchAll(PDO::FETCH_ASSOC)as$r){$status=$r['bacteriological_conformity']==='conforming'?'CONFORMING':($r['bacteriological_conformity']==='nonconforming'?'NONCONFORMING':'NOT_EVALUATED');if(!in_array($status,['CONFORMING','NONCONFORMING'],true))continue;$sid=(int)$r['sample_id'];$key=(int)$r['origin_unit_id'].'|'.$r['ym'];$sampleStates[$sid]??=['status'=>$r['sample_status'],'required'=>[],'key'=>$key];if((int)$catalog[$testId]['is_required'])$sampleStates[$sid]['required'][$testId]=$status;$byUnitMonth[$key]??=[];self::addTest($totals,$testId,$catalog[$testId],$status,'Resultado bacteriológico final');self::addTest($byUnitMonth[$key],$testId,$catalog[$testId],$status,'Resultado bacteriológico final');}
    }

    private static function addTest(array &$set,int $id,array $test,string $status,?string $criterion):void{$set[$id]??=['id'=>$id,'code'=>$test['code'],'name'=>$test['name'],'unit'=>$test['unit'],'conforming'=>0,'nonconforming'=>0,'evaluated'=>0,'percent'=>null,'criteria'=>[]];$set[$id]['evaluated']++;$set[$id][$status==='CONFORMING'?'conforming':'nonconforming']++;if($criterion&&!in_array($criterion,$set[$id]['criteria'],true))$set[$id]['criteria'][]=$criterion;}

    private static function targets(int $componentId,array $testIds,string $onDate):array
    {
        if(!$testIds)return[];$marks=implode(',',array_fill(0,count($testIds),'?'));$q=Database::connection()->prepare("SELECT d.test_id,d.minimum_percentage FROM dashboard_conformity_targets d JOIN (SELECT test_id,MAX(effective_from) effective_from FROM dashboard_conformity_targets WHERE blood_component_id=? AND active=1 AND effective_from<=? AND (effective_to IS NULL OR effective_to>=?) AND test_id IN ($marks) GROUP BY test_id) current ON current.test_id=d.test_id AND current.effective_from=d.effective_from WHERE d.blood_component_id=? AND d.active=1");$q->execute([$componentId,$onDate,$onDate,...$testIds,$componentId]);$out=[];foreach($q->fetchAll(PDO::FETCH_ASSOC)as$r)$out[(int)$r['test_id']]=(float)$r['minimum_percentage'];return$out;
    }
    private static function targetStatus(?float $percent,?float $target):string{if($percent===null)return'no_results';if($target===null)return'no_target';return$percent+0.00001>=$target?'met':'below';}

    private static function rows(array $units,int $componentId,array $production,array $samples,array $tests,int $year,int $month):array
    {
        $unitMap=[];foreach($units as$u)$unitMap[(int)$u['id']]=$u['name'];$keys=[];foreach($production['monthly']as$u=>$months)foreach($months as$ym=>$v)$keys[$u.'|'.$ym]=true;foreach($samples['monthly']as$u=>$months)foreach($months as$ym=>$v)$keys[$u.'|'.$ym]=true;
        if($year&&$month)foreach($unitMap as$u=>$name)$keys[$u.'|'.sprintf('%04d-%02d',$year,$month)]=true;
        $rows=[];foreach(array_keys($keys)as$key){[$u,$ym]=explode('|',$key);$u=(int)$u;if(!isset($unitMap[$u]))continue;[$y,$m]=array_map('intval',explode('-',$ym));$p=$production['monthly'][$u][$ym]??null;$s=$samples['monthly'][$u][$ym]??['completed'=>0,'in_progress'=>0];$produced=$p===null?null:(int)$p['quantity'];$rule=SamplingScheduleService::effectiveRule($componentId,new DateTimeImmutable($ym.'-01'));$minimum=$produced===null?null:SamplingScheduleService::calculateRequired($produced,$rule);$tested=(int)$s['completed'];$percent=$produced!==null&&$produced>0?$tested/$produced*100:null;$met=$minimum!==null&&$tested>=$minimum;$rows[]=['year'=>$y,'month'=>$m,'month_name'=>self::MONTHS[$m],'unit_id'=>$u,'unit_name'=>$unitMap[$u],'produced'=>$produced,'production_records'=>(int)($p['records']??0),'tested'=>$tested,'in_progress'=>(int)$s['in_progress'],'sampling_percent'=>$percent,'minimum'=>$minimum,'sampling_met'=>$met,'missing'=>$minimum===null?0:max(0,$minimum-$tested),'tests'=>array_values($tests['by_unit_month'][$key]??[]),'global_percent'=>self::rowGlobal($s['sample_ids']??[],[] )];}
        // O percentual global por linha é obtido de resultados avaliados (ponderado), sem média entre unidades.
        foreach($rows as&$r){$g=$tests['global_by_unit_month'][$r['unit_id'].'|'.sprintf('%04d-%02d',$r['year'],$r['month'])]??null;$r['global_percent']=$g&&$g['evaluated']?$g['conforming']/$g['evaluated']*100:null;}unset($r);return$rows;
    }
    private static function rowGlobal(array $ids,array $states):?float{return null;}
    private static function unitStatus(array $rows):array{$map=[];foreach($rows as$r){$u=$r['unit_id'];$map[$u]??=['unit_id'=>$u,'unit_name'=>$r['unit_name'],'met'=>0,'total'=>0,'missing'=>0];if($r['minimum']!==null){$map[$u]['total']++;if($r['sampling_met'])$map[$u]['met']++;$map[$u]['missing']+=$r['missing'];}}return array_values($map);}
    private static function range(int $year,int $month):array{if($year&&$month){$from=sprintf('%04d-%02d-01',$year,$month);return[$from,date('Y-m-d',strtotime($from.' +1 month -1 day'))];}if($year)return[sprintf('%04d-01-01',$year),sprintf('%04d-12-31',$year)];return['1900-01-01','2100-12-31'];}
    private static function periodLabel(int $year,int $month):string{if($year&&$month)return self::MONTHS[$month].' de '.$year;if($year)return'Ano de '.$year;return'Todos os anos';}
}
