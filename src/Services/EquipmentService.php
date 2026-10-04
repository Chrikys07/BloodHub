<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\Database;
use DomainException;
use PDO;

final class EquipmentService
{
    public static function catalogs():array
    {
        $pdo=Database::connection();
        return [
            'equipment'=>$pdo->query('SELECT e.*,u.name unit_name FROM laboratory_equipment e LEFT JOIN units u ON u.id=e.unit_id ORDER BY e.active DESC,e.name')->fetchAll(PDO::FETCH_ASSOC),
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
        if($id){$p['id']=$id;$pdo->prepare('UPDATE laboratory_equipment SET code=:code,name=:name,manufacturer=:manufacturer,model=:model,serial_number=:serial,unit_id=:unit,active=:active,effective_from=:from,effective_to=:to WHERE id=:id')->execute($p);return$id;}
        $pdo->prepare('INSERT INTO laboratory_equipment(code,name,manufacturer,model,serial_number,unit_id,active,effective_from,effective_to) VALUES(:code,:name,:manufacturer,:model,:serial,:unit,:active,:from,:to)')->execute($p);return(int)$pdo->lastInsertId();
    }

    public static function assign(array$d):int
    {
        $test=(int)($d['test_id']??0);$equipment=(int)($d['equipment_id']??0);$from=trim((string)($d['effective_from']??''));
        if(!$test||!$equipment||$from==='')throw new DomainException('Informe teste, equipamento e início da vigência.');
        $q=Database::connection()->prepare('INSERT INTO test_equipment_assignments(test_id,equipment_id,blood_component_id,effective_from,effective_to,active) VALUES(:test,:equipment,:component,:from,:to,:active)');
        $q->execute(['test'=>$test,'equipment'=>$equipment,'component'=>filter_var($d['blood_component_id']??null,FILTER_VALIDATE_INT)?:null,'from'=>$from,'to'=>trim((string)($d['effective_to']??''))?:null,'active'=>!empty($d['active'])?1:0]);return(int)Database::connection()->lastInsertId();
    }

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
