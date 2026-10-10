<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\{Auth,Database,Permission};
use DateTimeImmutable;
use PDO;

/** Read-only projection of the persisted, current QC results and evaluations. */
final class QualityControlReportService
{
    public const PERMISSION='reports.quality_control.view';

    public static function getFilters(array $input=[]):array
    {
        $today=new DateTimeImmutable('today');$units=self::accessibleUnits();
        $components=Database::connection()->query("SELECT DISTINCT bc.id,bc.code,bc.name FROM blood_components bc JOIN test_blood_components tbc ON tbc.blood_component_id=bc.id JOIN tests t ON t.id=tbc.test_id AND t.status='active' AND t.is_final_result=1 WHERE bc.status='active' ORDER BY bc.code,bc.name")->fetchAll(PDO::FETCH_ASSOC);
        $allowedUnits=array_map('intval',array_column($units,'id'));$unit=(int)($input['unit_id']??0);if($unit&&!in_array($unit,$allowedUnits,true))$unit=-1;
        $component=(int)($input['blood_component_id']??0);if($component&&!in_array($component,array_map('intval',array_column($components,'id')),true))$component=0;
        $from=self::date((string)($input['date_from']??''))?:$today->modify('first day of this month')->format('Y-m-d');
        $to=self::date((string)($input['date_to']??''))?:$today->format('Y-m-d');if($from>$to)[$from,$to]=[$to,$from];
        $conformity=in_array(($input['conformity']??''),['conforming','nonconforming'],true)?$input['conformity']:'';
        $requestedPerPage=(int)($input['per_page']??25);$perPage=in_array($requestedPerPage,[25,50,100],true)?$requestedPerPage:25;
        $sort=in_array(($input['sort']??''),['date','origin','donation'],true)?$input['sort']:'date';
        return compact('from','to','unit','component','conformity','perPage','sort','units','components')+['donation'=>trim((string)($input['donation']??'')),'lcqh'=>trim((string)($input['lcqh']??''))];
    }

    public static function search(array $filters,int $page=1,bool $all=false):array
    {
        [$where,$params]=self::where($filters);$pdo=Database::connection();
        $count=$pdo->prepare('SELECT COUNT(*) FROM samples s WHERE '.implode(' AND ',$where));$count->execute($params);$total=(int)$count->fetchColumn();
        $order=match($filters['sort']){'origin'=>'u.name,s.received_at DESC','donation'=>'s.donation_number,s.received_at DESC',default=>'s.received_at DESC,s.id DESC'};
        $limit=$all?'':' LIMIT '.($page-1)*$filters['perPage'].','.$filters['perPage'];
        $sql="SELECT s.id,s.donation_number,s.lcqh_code,s.production_date,s.received_at,s.blood_component_id,s.origin_unit_id,bc.code component_code,bc.name component_name,u.name origin_name,b.name bag_brand,COALESCE(ps.name,p.name,bp.name) preservative_name FROM samples s JOIN blood_components bc ON bc.id=s.blood_component_id LEFT JOIN units u ON u.id=s.origin_unit_id LEFT JOIN bag_brands b ON b.id=s.bag_brand_id LEFT JOIN preservatives p ON p.id=s.preservative_id LEFT JOIN preservatives ps ON ps.id=s.preservative_id_snapshot LEFT JOIN preservatives bp ON bp.id=b.preservative_id WHERE ".implode(' AND ',$where)." ORDER BY $order$limit";
        $q=$pdo->prepare($sql);$q->execute($params);$samples=$q->fetchAll(PDO::FETCH_ASSOC);$ids=array_map('intval',array_column($samples,'id'));
        $results=self::results($ids);$columns=self::buildDynamicColumns(array_values(array_unique(array_map('intval',array_column($samples,'blood_component_id')))));
        foreach($samples as&$sample){$sample['results']=$results[(int)$sample['id']]??[];$sample['has_nonconformity']=(bool)array_filter($sample['results'],fn($r)=>$r['status']==='NONCONFORMING');}unset($sample);
        $groups=[];foreach($samples as$s){$cid=(int)$s['blood_component_id'];$groups[$cid]??=['component_id'=>$cid,'component_code'=>$s['component_code'],'component_name'=>$s['component_name'],'columns'=>$columns[$cid]??[],'samples'=>[]];$groups[$cid]['samples'][]=$s;}
        return ['groups'=>array_values($groups),'samples'=>$samples,'total'=>$total,'page'=>$page,'per_page'=>$filters['perPage'],'pages'=>max(1,(int)ceil($total/$filters['perPage']))];
    }

    public static function getSummary(array $filters):array
    {
        [$where,$params]=self::where($filters);$sql='SELECT COUNT(*) records,COUNT(DISTINCT s.origin_unit_id) units,SUM(CASE WHEN '.self::nonconformitySql().' THEN 1 ELSE 0 END) nonconforming FROM samples s WHERE '.implode(' AND ',$where);
        $q=Database::connection()->prepare($sql);$q->execute($params);$r=$q->fetch(PDO::FETCH_ASSOC)?:[];$records=(int)($r['records']??0);return ['records'=>$records,'conforming'=>max(0,$records-(int)($r['nonconforming']??0)),'nonconforming'=>(int)($r['nonconforming']??0),'units'=>(int)($r['units']??0)];
    }

    public static function getNonconformities(array $filters):array
    {
        $f=$filters;$f['conformity']='nonconforming';$data=self::search($f,1,true);$out=[];foreach($data['samples'] as$s)foreach($s['results'] as$r)if($r['status']==='NONCONFORMING')$out[]=['donation'=>$s['donation_number'],'component'=>$s['component_code'],'origin'=>$s['origin_name'],'test'=>$r['name'],'result'=>$r['display'],'reference'=>$r['reference'],'date'=>$s['received_at']];return$out;
    }

    public static function getTestSummary(array $filters):array
    {
        $data=self::search($filters,1,true);$summary=[];foreach($data['samples']as$s)foreach($s['results']as$r){$key=(string)$r['test_id'];$summary[$key]??=['test'=>$r['name'],'evaluated'=>0,'conforming'=>0,'nonconforming'=>0];if(in_array($r['status'],['CONFORMING','NONCONFORMING'],true)){$summary[$key]['evaluated']++;$summary[$key][$r['status']==='CONFORMING'?'conforming':'nonconforming']++;}}usort($summary,fn($a,$b)=>strcmp($a['test'],$b['test']));return$summary;
    }

    public static function find(int $id):?array
    {
        $f=self::getFilters(['date_from'=>'1900-01-01','date_to'=>'2100-12-31']);[$where,$params]=self::where($f);$where[]='s.id=:detail_id';$params['detail_id']=$id;
        $q=Database::connection()->prepare("SELECT s.*,bc.code component_code,bc.name component_name,u.name origin_name,b.name bag_brand,COALESCE(ps.name,p.name,bp.name) preservative_name FROM samples s JOIN blood_components bc ON bc.id=s.blood_component_id LEFT JOIN units u ON u.id=s.origin_unit_id LEFT JOIN bag_brands b ON b.id=s.bag_brand_id LEFT JOIN preservatives p ON p.id=s.preservative_id LEFT JOIN preservatives ps ON ps.id=s.preservative_id_snapshot LEFT JOIN preservatives bp ON bp.id=b.preservative_id WHERE ".implode(' AND ',$where).' LIMIT 1');$q->execute($params);$sample=$q->fetch(PDO::FETCH_ASSOC);if(!$sample)return null;$sample['results']=self::results([$id])[$id]??[];return$sample;
    }

    public static function buildDynamicColumns(array $componentIds):array
    {
        if(!$componentIds)return[];$marks=implode(',',array_fill(0,count($componentIds),'?'));
        $q=Database::connection()->prepare("SELECT DISTINCT tbc.blood_component_id,t.id,t.code,t.name,t.unit FROM test_blood_components tbc JOIN tests t ON t.id=tbc.test_id WHERE tbc.blood_component_id IN ($marks) AND t.status='active' AND t.is_final_result=1 ORDER BY tbc.blood_component_id,t.name");$q->execute($componentIds);$out=[];foreach($q->fetchAll(PDO::FETCH_ASSOC)as$r)$out[(int)$r['blood_component_id']][]=$r;return$out;
    }

    private static function results(array $sampleIds):array
    {
        if(!$sampleIds)return[];$pdo=Database::connection();$marks=implode(',',array_fill(0,count($sampleIds),'?'));
        $sql="SELECT st.sample_id,t.id test_id,t.code,t.name,t.unit,tr.result_value_numeric,tr.result_value_text,tr.recorded_at,tr.recorded_by,u.name recorded_by_name,e.conformity_status,e.specification_snapshot_text,e.rule_type,e.expected_min,e.expected_max,e.expected_text,e.unit specification_unit,n.id notification_id,n.public_code FROM sample_tests st JOIN tests t ON t.id=st.test_id AND t.status='active' AND t.is_final_result=1 JOIN test_results tr ON tr.id=(SELECT MAX(x.id) FROM test_results x WHERE x.sample_test_id=st.id) LEFT JOIN test_result_spec_evaluations e ON e.test_result_id=tr.id LEFT JOIN users u ON u.id=tr.recorded_by LEFT JOIN qc_notifications n ON n.result_id=tr.id AND n.status<>'CANCELLED' WHERE st.sample_id IN ($marks) AND st.status<>'cancelled'";
        $q=$pdo->prepare($sql);$q->execute($sampleIds);$out=[];foreach($q->fetchAll(PDO::FETCH_ASSOC)as$r){if(strtoupper((string)$r['code'])==='BACTERIOLOGY')continue;$value=$r['result_value_numeric']!==null?$r['result_value_numeric']:$r['result_value_text'];$out[(int)$r['sample_id']][(int)$r['test_id']]=self::resultRow($r,$value);}
        $b=$pdo->prepare("SELECT s.id sample_id,t.id test_id,t.code,t.name,t.unit,br.result,br.bacteriological_conformity,br.performed_at recorded_at,u.name recorded_by_name FROM samples s JOIN tests t ON t.code='BACTERIOLOGY' JOIN bacteriology_results br ON br.id=(SELECT br2.id FROM bacteriology_results br2 WHERE br2.sample_id=s.id AND br2.is_final=1 ORDER BY br2.performed_at DESC,br2.id DESC LIMIT 1) LEFT JOIN users u ON u.id=br.performed_by WHERE s.id IN ($marks)");$b->execute($sampleIds);foreach($b->fetchAll(PDO::FETCH_ASSOC)as$r){$r['conformity_status']=$r['bacteriological_conformity']==='conforming'?'CONFORMING':($r['bacteriological_conformity']==='nonconforming'?'NONCONFORMING':'NOT_EVALUATED');$r['specification_snapshot_text']='Resultado bacteriológico final';$r['notification_id']=null;$r['public_code']=null;$value=$r['result']==='negative'?'Negativo':'Positivo confirmado';$out[(int)$r['sample_id']][(int)$r['test_id']]=self::resultRow($r,$value);}
        return$out;
    }

    private static function resultRow(array $r,mixed $value):array
    {
        $display=is_numeric($value)?MeasurementFormatter::formatResult((string)$r['code'],$value,$r['unit']??null):trim((string)$value.' '.($r['unit']??''));
        return ['test_id'=>(int)$r['test_id'],'code'=>$r['code'],'name'=>$r['name'],'display'=>$display?:'—','raw'=>$value,'status'=>$r['conformity_status']??'NOT_EVALUATED','reference'=>$r['specification_snapshot_text']?:'Sem especificação aplicável','recorded_at'=>$r['recorded_at']??null,'recorded_by'=>$r['recorded_by_name']??null,'notification_id'=>(int)($r['notification_id']??0),'notification_code'=>$r['public_code']??null];
    }

    private static function where(array $f,bool $applyConformity=true):array
    {
        $unitIds=array_map('intval',array_column(self::accessibleUnits(),'id'));$where=["s.purpose='quality_control'","s.status<>'cancelled'",'s.received_at IS NOT NULL','DATE(s.received_at) BETWEEN :date_from AND :date_to'];$params=['date_from'=>$f['from'],'date_to'=>$f['to']];
        if(!$unitIds)$where[]='1=0';else $where[]='s.origin_unit_id IN ('.implode(',',$unitIds).')';
        if($f['unit']){$where[]='s.origin_unit_id=:unit_id';$params['unit_id']=$f['unit'];}if($f['component']){$where[]='s.blood_component_id=:component_id';$params['component_id']=$f['component'];}
        foreach(['donation'=>'donation_number','lcqh'=>'lcqh_code']as$k=>$column)if($f[$k]!==''){$where[]="s.$column LIKE :$k";$params[$k]='%'.$f[$k].'%';}
        if($applyConformity&&$f['conformity']==='nonconforming')$where[]=self::nonconformitySql();if($applyConformity&&$f['conformity']==='conforming')$where[]='NOT '.self::nonconformitySql();return[$where,$params];
    }

    private static function nonconformitySql():string
    {return "(EXISTS(SELECT 1 FROM sample_tests nst JOIN tests nt ON nt.id=nst.test_id AND nt.is_final_result=1 JOIN test_results ntr ON ntr.id=(SELECT MAX(nx.id) FROM test_results nx WHERE nx.sample_test_id=nst.id) JOIN test_result_spec_evaluations ne ON ne.test_result_id=ntr.id AND ne.conformity_status='NONCONFORMING' WHERE nst.sample_id=s.id AND nst.status<>'cancelled') OR EXISTS(SELECT 1 FROM bacteriology_results nbr WHERE nbr.sample_id=s.id AND nbr.is_final=1 AND nbr.bacteriological_conformity='nonconforming'))";}

    private static function accessibleUnits():array
    {
        $pdo=Database::connection();$user=Auth::user()??[];$global=Auth::isAdministrator()||Permission::can('samples.scope.global')||in_array($user['role_slug']??'', ['gestao','lcqh'],true);
        if($global)return$pdo->query("SELECT id,name,code FROM units WHERE status='active' AND unit_type='processing' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $q=$pdo->prepare("SELECT DISTINCT u.id,u.name,u.code FROM units u JOIN users usr ON usr.id=:user LEFT JOIN user_units uu ON uu.user_id=usr.id AND uu.unit_id=u.id WHERE u.status='active' AND u.unit_type='processing' AND (u.id=usr.primary_unit_id OR uu.unit_id IS NOT NULL) ORDER BY u.name");$q->execute(['user'=>$user['id']??0]);return$q->fetchAll(PDO::FETCH_ASSOC);
    }
    private static function date(string $date):?string{return preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)&&strtotime($date)!==false?$date:null;}
}
