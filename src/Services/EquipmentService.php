<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\Database;
use DomainException;
use PDO;
use BloodHub\Core\Auth;

final class EquipmentService
{
    public static function catalogs():array
    {
        $pdo=Database::connection();
        return [
            'equipment'=>$pdo->query('SELECT e.*,u.name unit_name,(SELECT COUNT(*) FROM test_result_equipment tre WHERE tre.equipment_id=e.id) usage_count FROM laboratory_equipment e LEFT JOIN units u ON u.id=e.unit_id ORDER BY e.active DESC,e.name')->fetchAll(PDO::FETCH_ASSOC),
            'assignments'=>$pdo->query('SELECT a.*,t.name test_name,t.code test_code,e.name equipment_name,bc.code component_code FROM test_equipment_assignments a JOIN tests t ON t.id=a.test_id JOIN laboratory_equipment e ON e.id=a.equipment_id LEFT JOIN blood_components bc ON bc.id=a.blood_component_id ORDER BY a.active DESC,t.name,a.effective_from DESC')->fetchAll(PDO::FETCH_ASSOC),
            'tests'=>$pdo->query("SELECT id,code,name,equipment_required FROM tests WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC),
            'components'=>$pdo->query("SELECT id,code,name FROM blood_components WHERE status='active' ORDER BY code")->fetchAll(PDO::FETCH_ASSOC),
            'units'=>$pdo->query("SELECT id,code,name FROM units WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public static function saveEquipment(array$d):int
    {
        foreach(['code','name','manufacturer','model','effective_from']as$f)if(trim((string)($d[$f]??''))==='')throw new DomainException('Preencha todos os campos obrigatórios do equipamento.');
        $p=['code'=>trim($d['code']),'name'=>trim($d['name']),'manufacturer'=>trim($d['manufacturer']),'model'=>trim($d['model']),'serial'=>trim((string)($d['serial_number']??''))?:null,'unit'=>filter_var($d['unit_id']??null,FILTER_VALIDATE_INT)?:null,'active'=>!empty($d['active'])?1:0,'from'=>$d['effective_from'],'to'=>trim((string)($d['effective_to']??''))?:null];
        $id=(int)($d['id']??0);$pdo=Database::connection();
        if($id){$q=$pdo->prepare('SELECT * FROM laboratory_equipment WHERE id=:id');$q->execute(['id'=>$id]);$old=$q->fetch(PDO::FETCH_ASSOC);if(!$old)throw new DomainException('Equipamento não encontrado.');$p['id']=$id;$pdo->prepare('UPDATE laboratory_equipment SET code=:code,name=:name,manufacturer=:manufacturer,model=:model,serial_number=:serial,unit_id=:unit,active=:active,effective_from=:from,effective_to=:to WHERE id=:id')->execute($p);Auth::registerAudit('LABORATORY_EQUIPMENT_UPDATED','laboratory_equipment',$id,$old,$p);return$id;}
        $pdo->prepare('INSERT INTO laboratory_equipment(code,name,manufacturer,model,serial_number,unit_id,active,effective_from,effective_to) VALUES(:code,:name,:manufacturer,:model,:serial,:unit,:active,:from,:to)')->execute($p);return(int)$pdo->lastInsertId();
    }

    public static function assign(array$d):int
    {
        $test=(int)($d['test_id']??0);$equipment=(int)($d['equipment_id']??0);$from=trim((string)($d['effective_from']??''));
        if(!$test||!$equipment||$from==='')throw new DomainException('Informe teste, equipamento e início da vigência.');
        $pdo=Database::connection();$data=['test'=>$test,'equipment'=>$equipment,'component'=>filter_var($d['blood_component_id']??null,FILTER_VALIDATE_INT)?:null,'from'=>$from,'to'=>trim((string)($d['effective_to']??''))?:null,'active'=>!empty($d['active'])?1:0];$id=(int)($d['id']??0);
        if($id){$q=$pdo->prepare('SELECT * FROM test_equipment_assignments WHERE id=:id');$q->execute(['id'=>$id]);$old=$q->fetch(PDO::FETCH_ASSOC);if(!$old)throw new DomainException('Vínculo não encontrado.');if(self::assignmentUsed($old))throw new DomainException('Este vínculo já foi usado em resultado histórico. Inative-o e crie um novo vínculo.');$pdo->prepare('UPDATE test_equipment_assignments SET test_id=:test,equipment_id=:equipment,blood_component_id=:component,effective_from=:from,effective_to=:to,active=:active WHERE id=:id')->execute($data+['id'=>$id]);Auth::registerAudit('TEST_EQUIPMENT_ASSIGNMENT_UPDATED','test_equipment_assignments',$id,$old,$data);return$id;}
        $q=$pdo->prepare('INSERT INTO test_equipment_assignments(test_id,equipment_id,blood_component_id,effective_from,effective_to,active) VALUES(:test,:equipment,:component,:from,:to,:active)');$q->execute($data);return(int)$pdo->lastInsertId();
    }

    public static function toggleEquipment(int$id,bool$active):void{$pdo=Database::connection();$q=$pdo->prepare('SELECT * FROM laboratory_equipment WHERE id=:id');$q->execute(['id'=>$id]);$old=$q->fetch(PDO::FETCH_ASSOC);if(!$old)throw new DomainException('Equipamento não encontrado.');$pdo->beginTransaction();try{$pdo->prepare('UPDATE laboratory_equipment SET active=:active WHERE id=:id')->execute(['active'=>$active?1:0,'id'=>$id]);if(!$active)$pdo->prepare('UPDATE test_equipment_assignments SET active=0,effective_to=COALESCE(effective_to,CURDATE()) WHERE equipment_id=:id AND active=1')->execute(['id'=>$id]);Auth::registerAudit($active?'LABORATORY_EQUIPMENT_ACTIVATED':'LABORATORY_EQUIPMENT_DEACTIVATED','laboratory_equipment',$id,$old,['active'=>$active]);$pdo->commit();}catch(\Throwable$e){$pdo->rollBack();throw$e;}}
    public static function deleteEquipment(int$id):void{$pdo=Database::connection();$q=$pdo->prepare('SELECT * FROM laboratory_equipment WHERE id=:id');$q->execute(['id'=>$id]);$old=$q->fetch(PDO::FETCH_ASSOC);if(!$old)throw new DomainException('Equipamento não encontrado.');$u=$pdo->prepare('SELECT COUNT(*) FROM test_result_equipment WHERE equipment_id=:id');$u->execute(['id'=>$id]);if((int)$u->fetchColumn()>0)throw new DomainException('Este equipamento possui registros históricos e não pode ser excluído. Inative-o para impedir novos usos.');$pdo->beginTransaction();try{$pdo->prepare('DELETE FROM test_equipment_assignments WHERE equipment_id=:id')->execute(['id'=>$id]);$pdo->prepare('DELETE FROM laboratory_equipment WHERE id=:id')->execute(['id'=>$id]);Auth::registerAudit('LABORATORY_EQUIPMENT_DELETED','laboratory_equipment',$id,$old,null);$pdo->commit();}catch(\PDOException$e){$pdo->rollBack();throw new DomainException('Este equipamento possui vínculos e não pode ser excluído. Inative-o para impedir novos usos.');}}
    public static function toggleAssignment(int$id,bool$active):void{$pdo=Database::connection();$q=$pdo->prepare('SELECT * FROM test_equipment_assignments WHERE id=:id');$q->execute(['id'=>$id]);$old=$q->fetch(PDO::FETCH_ASSOC);if(!$old)throw new DomainException('Vínculo não encontrado.');if($active){$e=$pdo->prepare('SELECT active FROM laboratory_equipment WHERE id=:id');$e->execute(['id'=>$old['equipment_id']]);if(!(int)$e->fetchColumn())throw new DomainException('Ative o equipamento antes de reativar o vínculo.');}$pdo->prepare('UPDATE test_equipment_assignments SET active=:active WHERE id=:id')->execute(['active'=>$active?1:0,'id'=>$id]);Auth::registerAudit($active?'TEST_EQUIPMENT_ASSIGNMENT_ACTIVATED':'TEST_EQUIPMENT_ASSIGNMENT_DEACTIVATED','test_equipment_assignments',$id,$old,['active'=>$active]);}
    public static function deleteAssignment(int$id):void{$pdo=Database::connection();$q=$pdo->prepare('SELECT * FROM test_equipment_assignments WHERE id=:id');$q->execute(['id'=>$id]);$old=$q->fetch(PDO::FETCH_ASSOC);if(!$old)throw new DomainException('Vínculo não encontrado.');if(self::assignmentUsed($old))throw new DomainException('Este vínculo possui uso histórico e não pode ser excluído. Inative-o.');$pdo->prepare('DELETE FROM test_equipment_assignments WHERE id=:id')->execute(['id'=>$id]);Auth::registerAudit('TEST_EQUIPMENT_ASSIGNMENT_DELETED','test_equipment_assignments',$id,$old,null);}
    private static function assignmentUsed(array$a):bool{$q=Database::connection()->prepare('SELECT 1 FROM test_result_equipment tre JOIN test_results tr ON tr.id=tre.test_result_id JOIN sample_tests st ON st.id=tr.sample_test_id JOIN samples s ON s.id=st.sample_id WHERE tre.equipment_id=:equipment AND st.test_id=:test AND (:component IS NULL OR s.blood_component_id=:component2) AND DATE(tre.used_at)>=:from AND (:to IS NULL OR DATE(tre.used_at)<=:to2) LIMIT 1');$q->execute(['equipment'=>$a['equipment_id'],'test'=>$a['test_id'],'component'=>$a['blood_component_id'],'component2'=>$a['blood_component_id'],'from'=>$a['effective_from'],'to'=>$a['effective_to'],'to2'=>$a['effective_to']]);return(bool)$q->fetchColumn();}

    public static function eligibleForResult(int$resultId):array
    {
        $q=Database::connection()->prepare("SELECT e.*,t.method_name FROM test_results tr JOIN sample_tests st ON st.id=tr.sample_test_id JOIN samples s ON s.id=st.sample_id JOIN tests t ON t.id=st.test_id JOIN test_equipment_assignments a ON a.test_id=t.id AND (a.blood_component_id IS NULL OR a.blood_component_id=s.blood_component_id) AND a.active=1 AND a.effective_from<=DATE(tr.recorded_at) AND (a.effective_to IS NULL OR a.effective_to>=DATE(tr.recorded_at)) JOIN laboratory_equipment e ON e.id=a.equipment_id AND e.active=1 WHERE tr.id=:id ORDER BY a.blood_component_id DESC,a.effective_from DESC");$q->execute(['id'=>$resultId]);return$q->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function attach(int$resultId,?int$equipmentId=null):void
    {
        $pdo=Database::connection();$check=$pdo->prepare('SELECT 1 FROM test_result_equipment WHERE test_result_id=:id');$check->execute(['id'=>$resultId]);if($check->fetchColumn())return;
        $eligible=self::eligibleForResult($resultId);if(!$eligible)return;
        $equipment=$equipmentId?array_values(array_filter($eligible,fn($e)=>(int)$e['id']===$equipmentId))[0]??null:($eligible[0]??null);if(!$equipment)throw new DomainException('Equipamento selecionado não está autorizado para este teste.');
        $pdo->prepare('INSERT INTO test_result_equipment(test_result_id,equipment_id,equipment_name_snapshot,manufacturer_snapshot,model_snapshot,serial_snapshot,method_snapshot) VALUES(:result,:equipment,:name,:manufacturer,:model,:serial,:method)')->execute(['result'=>$resultId,'equipment'=>$equipment['id'],'name'=>$equipment['name'],'manufacturer'=>$equipment['manufacturer'],'model'=>$equipment['model'],'serial'=>$equipment['serial_number'],'method'=>$equipment['method_name']]);
    }
}
