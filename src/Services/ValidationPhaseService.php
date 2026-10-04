<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\{Auth,Database};
use PDO;

final class ValidationPhaseService
{
    public const RETRY_OUTCOMES=['unsatisfactory','inconclusive'];
    public const OUTCOMES=['satisfactory','unsatisfactory','inconclusive'];

    public static function phasesWithStats(int $validationId):array
    {
        $sql="SELECT vp.*,
          (SELECT COUNT(*) FROM samples s WHERE s.validation_phase_id=vp.id) sample_count,
          (SELECT COUNT(*) FROM samples s WHERE s.validation_phase_id=vp.id AND s.status='completed') completed_count,
          (SELECT COUNT(*) FROM samples s WHERE s.validation_phase_id=vp.id AND s.status<>'completed') pending_count,
          (SELECT COUNT(*) FROM validation_tests vt JOIN samples s ON s.validation_id=vt.validation_id AND s.validation_phase_id=vt.phase_id AND s.blood_component_id=vt.blood_component_id WHERE vt.phase_id=vp.id AND vt.status='active') expected_results,
          (SELECT COUNT(*) FROM test_results tr JOIN sample_tests st ON st.id=tr.sample_test_id JOIN samples s ON s.id=st.sample_id WHERE s.validation_phase_id=vp.id AND (tr.result_value_numeric IS NOT NULL OR NULLIF(TRIM(tr.result_value_text),'') IS NOT NULL)) filled_results
        FROM validation_phases vp WHERE vp.validation_id=:validation ORDER BY vp.sequence_order";
        $q=Database::connection()->prepare($sql);$q->execute(['validation'=>$validationId]);return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function complete(int $validationId,int $phaseId,string $outcome,string $conclusion):void
    {
        if(!in_array($outcome,self::OUTCOMES,true))throw new \DomainException('Selecione um resultado válido para a etapa.');
        $conclusion=trim($conclusion);if($conclusion==='')throw new \DomainException('Informe a conclusão da etapa.');
        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            $q=$pdo->prepare("SELECT * FROM validation_phases WHERE id=:id AND validation_id=:validation FOR UPDATE");$q->execute(['id'=>$phaseId,'validation'=>$validationId]);$phase=$q->fetch(PDO::FETCH_ASSOC);
            if(!$phase||$phase['status']!=='active')throw new \DomainException('A etapa selecionada não está em andamento.');
            $plan=$pdo->prepare("SELECT COUNT(*) FROM validation_tests WHERE phase_id=:phase AND status='active'");$plan->execute(['phase'=>$phaseId]);
            $samples=$pdo->prepare("SELECT COUNT(*) total,SUM(status<>'completed') pending FROM samples WHERE validation_phase_id=:phase");$samples->execute(['phase'=>$phaseId]);$counts=$samples->fetch(PDO::FETCH_ASSOC);
            $missing=$pdo->prepare("SELECT COUNT(*) FROM samples s JOIN validation_tests vt ON vt.phase_id=s.validation_phase_id AND vt.blood_component_id=s.blood_component_id AND vt.status='active' AND vt.is_required=1 LEFT JOIN sample_tests st ON st.sample_id=s.id AND st.test_id=vt.test_id LEFT JOIN test_results tr ON tr.sample_test_id=st.id WHERE s.validation_phase_id=:phase AND (tr.id IS NULL OR (tr.result_value_numeric IS NULL AND NULLIF(TRIM(tr.result_value_text),'') IS NULL))");$missing->execute(['phase'=>$phaseId]);
            if(!(int)$plan->fetchColumn()||!(int)$counts['total']||(int)$counts['pending']||(int)$missing->fetchColumn())throw new \DomainException('Esta etapa ainda possui amostras ou resultados obrigatórios pendentes.');
            $pdo->prepare("UPDATE validation_phases SET status='completed',outcome=:outcome,conclusion=:conclusion,completed_at=NOW(),started_at=COALESCE(started_at,created_at) WHERE id=:id")->execute(['outcome'=>$outcome,'conclusion'=>$conclusion,'id'=>$phaseId]);
            Auth::registerAudit('VALIDATION_PHASE_COMPLETED','validation_phases',$phaseId,$phase,['outcome'=>$outcome,'conclusion'=>$conclusion,'status'=>'completed']);
            $pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function createNext(int $validationId,string $name,string $startDate,bool $copyPlan):int
    {
        if(!self::date($startDate))throw new \DomainException('Informe uma data de início válida.');
        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            $open=$pdo->prepare("SELECT COUNT(*) FROM validation_phases WHERE validation_id=:validation AND status='active' FOR UPDATE");$open->execute(['validation'=>$validationId]);if((int)$open->fetchColumn())throw new \DomainException('Conclua a etapa em andamento antes de criar a próxima etapa.');
            $last=$pdo->prepare("SELECT * FROM validation_phases WHERE validation_id=:validation ORDER BY sequence_order DESC LIMIT 1 FOR UPDATE");$last->execute(['validation'=>$validationId]);$previous=$last->fetch(PDO::FETCH_ASSOC);
            if(!$previous||$previous['status']!=='completed'||!in_array($previous['outcome'],self::RETRY_OUTCOMES,true))throw new \DomainException('A última etapa não indica necessidade de uma nova avaliação.');
            $sequence=(int)$previous['sequence_order']+1;$name=trim($name)?:'Etapa '.$sequence;
            $pdo->prepare("INSERT INTO validation_phases(validation_id,name,sequence_order,status,started_at,created_by) VALUES(:validation,:name,:sequence,'active',:started,:user)")->execute(['validation'=>$validationId,'name'=>mb_substr($name,0,180),'sequence'=>$sequence,'started'=>$startDate.' 00:00:00','user'=>Auth::user()['id']]);$id=(int)$pdo->lastInsertId();
            Auth::registerAudit('VALIDATION_PHASE_CREATED','validation_phases',$id,null,['validation_id'=>$validationId,'name'=>$name,'sequence_order'=>$sequence,'started_at'=>$startDate]);
            Auth::registerAudit('VALIDATION_PHASE_STARTED','validation_phases',$id,null,['validation_id'=>$validationId,'started_at'=>$startDate]);
            if($copyPlan){
                $pdo->prepare("INSERT INTO validation_blood_components(validation_id,blood_component_id,phase_id,status) SELECT validation_id,blood_component_id,:new_phase,status FROM validation_blood_components WHERE phase_id=:old_phase")->execute(['new_phase'=>$id,'old_phase'=>$previous['id']]);
                $pdo->prepare("INSERT INTO validation_tests(validation_id,phase_id,blood_component_id,test_id,is_required,status) SELECT validation_id,:new_phase,blood_component_id,test_id,is_required,status FROM validation_tests WHERE phase_id=:old_phase")->execute(['new_phase'=>$id,'old_phase'=>$previous['id']]);
                Auth::registerAudit('VALIDATION_PHASE_PLAN_COPIED','validation_phases',$id,null,['source_phase_id'=>(int)$previous['id']]);
            }
            $pdo->commit();return $id;
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function completionBlocker(int $validationId):?string
    {
        $q=Database::connection()->prepare("SELECT * FROM validation_phases WHERE validation_id=:validation AND status='completed' ORDER BY sequence_order DESC LIMIT 1");$q->execute(['validation'=>$validationId]);$last=$q->fetch(PDO::FETCH_ASSOC);
        if(!$last)return 'A validação não possui etapas.';
        $open=Database::connection()->prepare("SELECT COUNT(*) FROM validation_phases WHERE validation_id=:validation AND status NOT IN('completed','cancelled')");$open->execute(['validation'=>$validationId]);if((int)$open->fetchColumn())return 'Conclua a etapa em andamento antes de concluir a validação.';
        if($last['outcome']!=='satisfactory')return 'A última etapa indica necessidade de nova avaliação.';
        return null;
    }

    private static function date(string $value):bool{$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);return $d&&$d->format('Y-m-d')===$value;}
}
