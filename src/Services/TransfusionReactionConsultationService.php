<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\Database;
use DomainException;
use PDO;

final class TransfusionReactionConsultationService
{
    public const PERMISSION='transfusion_reaction_consultation.view',PER_PAGE=10;

    public static function getVisibleUnitsForUser(?int$clientId=null):array{return UnitAccessService::visibleUnits($clientId);}
    public static function authorizeUnitAccess(int$unitId):void{UnitAccessService::authorize($unitId,'Reação transfusional não encontrada.');}

    public static function search(array$filters):array
    {
        [$scopeSql,$params]=UnitAccessService::sqlScope('s.origin_unit_id','rtc');
        $where=["s.purpose='transfusion_reaction'","s.status<>'cancelled'",'s.origin_unit_id IS NOT NULL',$scopeSql];
        $from=self::date($filters['from']??null)??date('Y-m-d',strtotime('-60 days'));$to=self::date($filters['to']??null)??date('Y-m-d');
        $where[]='DATE(COALESCE(s.received_at,s.sent_at,s.registered_at)) BETWEEN :from AND :to';$params+=['from'=>$from,'to'=>$to];
        $unit=self::positiveInt($filters['unit_id']??null);if($unit){self::authorizeUnitAccess($unit);$where[]='s.origin_unit_id=:unit';$params['unit']=$unit;}
        $client=self::positiveInt($filters['client_id']??null);if($client&&UnitAccessService::isGlobal()){$where[]='s.client_id=:client';$params['client']=$client;}
        $type=(string)($filters['client_type']??'');if(UnitAccessService::isGlobal()&&in_array($type,['internal','external'],true)){$where[]='c.client_type=:client_type';$params['client_type']=$type;}
        $number=trim((string)($filters['sample']??''));if($number!==''){$where[]='(s.donation_number LIKE :sample OR s.sample_code LIKE :sample OR s.lcqh_code LIKE :sample)';$params['sample']='%'.$number.'%';}
        $status=in_array(($filters['status']??''),['in_progress','completed'],true)?$filters['status']:'';
        if($status==='completed')$where[]='final_result.id IS NOT NULL';elseif($status==='in_progress')$where[]='final_result.id IS NULL';
        $join=" FROM samples s JOIN units u ON u.id=s.origin_unit_id LEFT JOIN clients c ON c.id=s.client_id LEFT JOIN blood_components bc ON bc.id=s.blood_component_id LEFT JOIN bacteriology_results final_result ON final_result.id=(SELECT br.id FROM bacteriology_results br WHERE br.sample_id=s.id AND br.is_final=1 ORDER BY br.id DESC LIMIT 1) LEFT JOIN sample_tests bact_test ON bact_test.id=(SELECT st.id FROM sample_tests st JOIN tests t ON t.id=st.test_id AND t.code='BACTERIOLOGY' WHERE st.sample_id=s.id ORDER BY st.id DESC LIMIT 1) LEFT JOIN laboratory_reports report ON report.id=(SELECT lr.id FROM laboratory_reports lr WHERE lr.sample_id=s.id ORDER BY lr.version DESC,lr.id DESC LIMIT 1)";
        $base=' WHERE '.implode(' AND ',$where);$pdo=Database::connection();
        $count=$pdo->prepare('SELECT COUNT(*)'.$join.$base);$count->execute($params);$total=(int)$count->fetchColumn();
        $page=max(1,(int)($filters['page']??1));$pages=max(1,(int)ceil($total/self::PER_PAGE));$page=min($page,$pages);$offset=($page-1)*self::PER_PAGE;
        $sql="SELECT s.id,s.sample_code,s.donation_number,s.lcqh_code,s.received_at,s.sent_at,s.registered_at,s.origin_unit_id,s.client_id,u.code unit_code,u.name unit_name,c.name client_name,c.client_type,bc.code component_code,bc.name component_name,final_result.result final_result,final_result.identified_bacteria,final_result.notes,final_result.performed_at,bact_test.completed_at,report.id report_id,report.status report_status,report.released_at".$join.$base.' ORDER BY COALESCE(s.received_at,s.sent_at,s.registered_at) DESC,s.id DESC LIMIT '.self::PER_PAGE.' OFFSET '.$offset;
        $q=$pdo->prepare($sql);$q->execute($params);$rows=$q->fetchAll(PDO::FETCH_ASSOC);foreach($rows as&$row)$row+=self::getFinalResult($row);
        $kpis=['total'=>$total,'in_progress'=>0,'completed'=>0];$kq=$pdo->prepare("SELECT SUM(final_result.id IS NULL),SUM(final_result.id IS NOT NULL)".$join.$base);$kq->execute($params);$counts=$kq->fetch(PDO::FETCH_NUM)?:[0,0];$kpis['in_progress']=(int)$counts[0];$kpis['completed']=(int)$counts[1];
        return compact('rows','total','page','pages','kpis','from','to');
    }

    public static function getReactionStatus(array$row):array{return empty($row['final_result'])?['key'=>'in_progress','label'=>'Em andamento']:['key'=>'completed','label'=>'Concluído'];}
    public static function getFinalResult(array$row):array
    {
        $status=self::getReactionStatus($row);$result=$row['final_result']??null;
        return['consultation_status'=>$status['key'],'status_label'=>$status['label'],'result_label'=>$result==='negative'?'Negativo':($result==='positive'?'Positivo confirmado':'Aguardando resultado'),'identification_label'=>$result==='negative'?'Não se aplica':($result==='positive'?($row['identified_bacteria']?:'—'):'—'),'conclusion_at'=>$result?($row['completed_at']?:$row['performed_at']):null,'released_report'=>self::getReleasedReport($row)];
    }
    public static function getReleasedReport(array$row):?array{return !empty($row['report_id'])&&in_array($row['report_status'],['RELEASED','REVISED'],true)?['id'=>(int)$row['report_id'],'status'=>$row['report_status'],'released_at'=>$row['released_at']]:null;}
    private static function date(mixed$v):?string{$v=trim((string)$v);return preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)?$v:null;}
    private static function positiveInt(mixed$v):?int{$v=filter_var($v,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);return$v?(int)$v:null;}
}
