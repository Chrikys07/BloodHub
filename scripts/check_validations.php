<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Core/Database.php';

use BloodHub\Core\Database;

$pdo=Database::connection();
$required=['validations','validation_phases','validation_blood_components','validation_tests'];
foreach($required as$table){$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');$q->execute(['table'=>$table]);if(!(int)$q->fetchColumn())throw new RuntimeException("Tabela ausente: {$table}");}
$permissions=$pdo->query("SELECT COUNT(*) FROM permissions WHERE permission_key IN('validations.view','validations.create','validations.edit','validations.configure_tests','validations.enter_results','validations.complete') AND status='active'")->fetchColumn();
if((int)$permissions!==6)throw new RuntimeException('As seis permissoes de validacao nao estao ativas.');
$pdo->beginTransaction();
try{
    $pv='PV-CHECK-'.bin2hex(random_bytes(4));
    $pdo->prepare("INSERT INTO validations(pv_number,name,start_date,status) VALUES(:pv,'Teste transacional',CURDATE(),'planned')")->execute(['pv'=>$pv]);
    $validation=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO validation_phases(validation_id,name,sequence_order,status) VALUES(:id,'Etapa 1',1,'active')")->execute(['id'=>$validation]);
    $phase=(int)$pdo->lastInsertId();
    $pair=$pdo->query("SELECT tbc.blood_component_id,tbc.test_id FROM test_blood_components tbc JOIN tests t ON t.id=tbc.test_id AND t.status='active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if(!$pair)throw new RuntimeException('Catalogo nao possui teste compativel para validar o plano.');
    $pdo->prepare("INSERT INTO validation_blood_components(validation_id,blood_component_id,phase_id) VALUES(:v,:c,:p)")->execute(['v'=>$validation,'c'=>$pair['blood_component_id'],'p'=>$phase]);
    $pdo->prepare("INSERT INTO validation_tests(validation_id,phase_id,blood_component_id,test_id,is_required) VALUES(:v,:p,:c,:t,1)")->execute(['v'=>$validation,'p'=>$phase,'c'=>$pair['blood_component_id'],'t'=>$pair['test_id']]);
    $pdo->rollBack();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
echo "Validations smoke check: OK\n";
