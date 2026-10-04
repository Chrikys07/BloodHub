<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\{Auth,Database,Permission};
use DomainException;
use PDO;

final class QcNotificationService
{
    public static function createForSample(int $sampleId):array
    {
        $q=Database::connection()->prepare("SELECT tr.id FROM test_results tr JOIN sample_tests st ON st.id=tr.sample_test_id JOIN test_result_spec_evaluations e ON e.test_result_id=tr.id WHERE st.sample_id=:sample AND st.status='completed' AND e.conformity_status='NONCONFORMING'");
        $q->execute(['sample'=>$sampleId]);$ids=[];
        foreach($q->fetchAll(PDO::FETCH_COLUMN) as $id){$created=self::createForResult((int)$id);if($created)$ids[]=$created;}
        return $ids;
    }

    public static function createForResult(int $resultId):?int
    {
        $pdo=Database::connection();
        $purpose=$pdo->prepare('SELECT s.purpose FROM test_results tr JOIN sample_tests st ON st.id=tr.sample_test_id JOIN samples s ON s.id=st.sample_id WHERE tr.id=:id');
        $purpose->execute(['id'=>$resultId]);
        if($purpose->fetchColumn()!=='quality_control')return null;
        $q=$pdo->prepare("SELECT tr.id result_id,tr.result_value_numeric,tr.result_value_text,tr.recorded_at,st.id sample_test_id,st.completed_at,st.executed_by,st.status test_status,s.id sample_id,s.status sample_status,s.client_id,s.origin_unit_id,s.blood_component_id,s.donation_number,s.lcqh_code,bc.code component_code,bc.name component_name,c.name client_name,u.name origin_name,t.id test_id,t.code test_code,t.name test_name,t.unit result_unit,e.specification_id,e.rule_type,e.expected_min,e.expected_max,e.expected_text,e.unit specification_unit,e.actual_value,e.specification_snapshot_text,e.conformity_status,sp.version_number,sp.effective_from FROM test_results tr JOIN sample_tests st ON st.id=tr.sample_test_id JOIN samples s ON s.id=st.sample_id LEFT JOIN blood_components bc ON bc.id=s.blood_component_id LEFT JOIN clients c ON c.id=s.client_id LEFT JOIN units u ON u.id=s.origin_unit_id LEFT JOIN tests t ON t.id=st.test_id JOIN test_result_spec_evaluations e ON e.test_result_id=tr.id LEFT JOIN blood_component_test_specifications sp ON sp.id=e.specification_id WHERE tr.id=:id");
        $q->execute(['id'=>$resultId]);$r=$q->fetch(PDO::FETCH_ASSOC);
        if(!$r||$r['test_status']!=='completed'||$r['conformity_status']!=='NONCONFORMING'||!$r['origin_unit_id'])return null;
        $actual=$r['result_value_numeric']!==null?$r['result_value_numeric']:$r['result_value_text'];
        $resultDisplay=MeasurementFormatter::formatResult((string)$r['test_code'],$actual,$r['result_unit']);
        $reference=MeasurementFormatter::formatSpecification((string)$r['test_code'],$r);
        $sql="INSERT IGNORE INTO qc_notifications(sample_id,sample_test_id,result_id,client_id,origin_unit_id,blood_component_id,test_id,specification_id,donation_number_snapshot,lcqh_code_snapshot,component_code_snapshot,component_name_snapshot,client_name_snapshot,origin_unit_name_snapshot,test_code_snapshot,test_name_snapshot,result_value_snapshot,result_display_snapshot,result_unit_snapshot,rule_snapshot,min_value_snapshot,max_value_snapshot,expected_text_snapshot,specification_unit_snapshot,reference_display_snapshot,specification_version_snapshot,specification_effective_from_snapshot,occurred_at,created_by) VALUES(:sample,:sample_test,:result,:client,:origin,:component,:test,:spec,:donation,:lcqh,:component_code,:component_name,:client_name,:origin_name,:test_code,:test_name,:actual,:result_display,:result_unit,:rule,:min,:max,:expected,:spec_unit,:reference,:version,:effective,:occurred,:created_by)";
        $pdo->prepare($sql)->execute(['sample'=>$r['sample_id'],'sample_test'=>$r['sample_test_id'],'result'=>$r['result_id'],'client'=>$r['client_id'],'origin'=>$r['origin_unit_id'],'component'=>$r['blood_component_id'],'test'=>$r['test_id'],'spec'=>$r['specification_id'],'donation'=>$r['donation_number'],'lcqh'=>$r['lcqh_code'],'component_code'=>$r['component_code'],'component_name'=>$r['component_name']?:'Não informado','client_name'=>$r['client_name'],'origin_name'=>$r['origin_name']?:'Não informada','test_code'=>$r['test_code'],'test_name'=>$r['test_name']?:'Teste','actual'=>(string)$actual,'result_display'=>$resultDisplay,'result_unit'=>$r['result_unit'],'rule'=>$r['rule_type'],'min'=>$r['expected_min'],'max'=>$r['expected_max'],'expected'=>$r['expected_text'],'spec_unit'=>$r['specification_unit'],'reference'=>$reference?:$r['specification_snapshot_text'],'version'=>$r['version_number'],'effective'=>$r['effective_from'],'occurred'=>$r['completed_at']?:$r['recorded_at'],'created_by'=>$r['executed_by']?:Auth::user()['id']??null]);
        if($pdo->lastInsertId()==='0')return null;$id=(int)$pdo->lastInsertId();$code='NCQ-'.date('Ym').'-'.str_pad((string)$id,6,'0',STR_PAD_LEFT);$pdo->prepare('UPDATE qc_notifications SET public_code=:code WHERE id=:id')->execute(['code'=>$code,'id'=>$id]);
        Auth::registerAudit('NOTIFICATION_CREATED','qc_notifications',$id,null,['public_code'=>$code,'result_id'=>$resultId,'origin_unit_id'=>(int)$r['origin_unit_id']]);return$id;
    }

    public static function deliverPendingForSample(int $sampleId):void
    {try{$q=Database::connection()->prepare('SELECT id FROM qc_notifications WHERE sample_id=:id');$q->execute(['id'=>$sampleId]);foreach($q->fetchAll(PDO::FETCH_COLUMN)as$id){try{self::deliver((int)$id);}catch(\Throwable$e){Auth::registerAudit('NOTIFICATION_EMAIL_FAILED','qc_notifications',(int)$id,null,['error'=>mb_substr($e->getMessage(),0,1000)]);}}}catch(\Throwable$e){/* A conclusão clínica nunca depende da infraestrutura de e-mail. */}}

    public static function deliver(int $id):void
    {
        $pdo=Database::connection();$q=$pdo->prepare('SELECT * FROM qc_notifications WHERE id=:id');$q->execute(['id'=>$id]);$n=$q->fetch(PDO::FETCH_ASSOC);if(!$n)return;
        $q=$pdo->prepare("SELECT email FROM units WHERE id=:unit UNION SELECT DISTINCT usr.email FROM users usr JOIN roles r ON r.id=usr.role_id JOIN role_permissions rp ON rp.role_id=r.id JOIN permissions p ON p.id=rp.permission_id AND p.permission_key='notifications.acknowledge' LEFT JOIN user_units uu ON uu.user_id=usr.id WHERE usr.status='active' AND (usr.primary_unit_id=:unit2 OR uu.unit_id=:unit3)");$q->execute(['unit'=>$n['origin_unit_id'],'unit2'=>$n['origin_unit_id'],'unit3'=>$n['origin_unit_id']]);
        $recipients=[];foreach($q->fetchAll(PDO::FETCH_COLUMN)as$email){$email=mb_strtolower(trim((string)$email));if(filter_var($email,FILTER_VALIDATE_EMAIL))$recipients[$email]=$email;}foreach($recipients as$email){$pdo->prepare("INSERT IGNORE INTO qc_notification_email_logs(notification_id,recipient,status) VALUES(:id,:email,'PENDING')")->execute(['id'=>$id,'email'=>$email]);$log=$pdo->prepare('SELECT id,status FROM qc_notification_email_logs WHERE notification_id=:id AND recipient=:email');$log->execute(['id'=>$id,'email'=>$email]);$row=$log->fetch(PDO::FETCH_ASSOC);if(!$row||$row['status']==='SENT')continue;
            $subject='BloodHub | Resultado de CQ fora da especificação - '.$n['component_name_snapshot'];$body="Foi identificado um resultado fora da especificação no Controle de Qualidade de Hemocomponentes.\n\nCódigo: {$n['public_code']}\nUnidade/processamento: {$n['origin_unit_name_snapshot']}\nNº da doação: {$n['donation_number_snapshot']}\nCódigo LCQH: {$n['lcqh_code_snapshot']}\nHemocomponente: {$n['component_name_snapshot']}\nTeste: {$n['test_name_snapshot']}\nResultado: {$n['result_display_snapshot']}\nValor de referência: {$n['reference_display_snapshot']}\nData da conclusão: {$n['occurred_at']}\n\nAcesse o BloodHub > Notificações para registrar a ciência e realizar a avaliação.";
            $html='<html><body><p>'.nl2br(htmlspecialchars($body,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')).'</p></body></html>';
            $error=null;try{(new Mailer())->send((string)$email,$subject,$html,$body);$sent=true;}catch(\Throwable$e){$sent=false;$error=$e->getMessage();}
            $pdo->prepare("UPDATE qc_notification_email_logs SET attempted_at=NOW(),status=:status,error_message=:error,sent_at=CASE WHEN :sent=1 THEN NOW() ELSE NULL END WHERE id=:id")->execute(['status'=>$sent?'SENT':'FAILED','error'=>$sent?null:mb_substr((string)$error,0,1000),'sent'=>$sent?1:0,'id'=>$row['id']]);Auth::registerAudit($sent?'NOTIFICATION_EMAIL_SENT':'NOTIFICATION_EMAIL_FAILED','qc_notifications',$id,null,['recipient'=>$email]+($sent?[]:['error'=>mb_substr((string)$error,0,1000)]));
        }
    }

    public static function scopeSql(string $alias='n'):array
    {if(Permission::can('notifications.view_all'))return['1=1',[]];$uid=(int)(Auth::user()['id']??0);return["EXISTS(SELECT 1 FROM users nu LEFT JOIN user_units nux ON nux.user_id=nu.id AND nux.unit_id={$alias}.origin_unit_id WHERE nu.id={$uid} AND (nu.primary_unit_id={$alias}.origin_unit_id OR nux.unit_id IS NOT NULL))",[]];}

    public static function analysisScopeSql(string $alias='a'):array
    {if(Permission::can('notifications.view_all'))return['1=1',[]];$uid=(int)(Auth::user()['id']??0);return["EXISTS(SELECT 1 FROM users au LEFT JOIN user_units aux ON aux.user_id=au.id AND aux.unit_id={$alias}.unit_id WHERE au.id={$uid} AND (au.primary_unit_id={$alias}.unit_id OR aux.unit_id IS NOT NULL))",[]];}

    public static function acknowledge(array $ids):int
    {if(!Permission::can('notifications.acknowledge'))throw new DomainException('Você não possui permissão para registrar ciência.');$ids=array_values(array_unique(array_filter(array_map('intval',$ids))));if(!$ids)return 0;[$scope,$params]=self::scopeSql('qc_notifications');$pdo=Database::connection();$count=0;$pdo->beginTransaction();try{foreach($ids as$id){$q=$pdo->prepare("UPDATE qc_notifications SET status='ACKNOWLEDGED',acknowledged_at=NOW(),acknowledged_by=:user WHERE id=:id AND status='PENDING_ACKNOWLEDGEMENT' AND {$scope}");$q->execute(['user'=>Auth::user()['id'],'id'=>$id]+$params);if($q->rowCount()){Auth::registerAudit('NOTIFICATION_ACKNOWLEDGED','qc_notifications',$id,['status'=>'PENDING_ACKNOWLEDGEMENT'],['status'=>'ACKNOWLEDGED']);$count++;}}$pdo->commit();return$count;}catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}}
}
