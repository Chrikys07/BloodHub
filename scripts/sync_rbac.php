<?php
declare(strict_types=1);

use BloodHub\Core\Database;

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Este script so pode ser executado via CLI.\n"); }
spl_autoload_register(static function(string $class):void { $prefix='BloodHub\\'; if(!str_starts_with($class,$prefix))return; $file=dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php'; if(is_file($file))require $file; });

$mapping = [
    'administrador' => ['dashboard.global.view','production.view','production.create','production.edit','production.admin','admin.users.manage','admin.roles.manage','admin.permissions.manage','admin.clients.manage','admin.units.manage','admin.blood_components.manage','admin.bag_brands.manage','admin.tests.manage','admin.supplies.manage','admin.preservatives.manage','samples.scope.global','shipments.view','shipments.create','shipments.edit','shipments.send','shipments.cancel','shipments.print','reception.view','reception.receive','reception.reject','reception.edit_sample_data','quality_results.view','quality_results.edit','quality_results.complete','quality_results.hemolysis','quality_results.hemolysis_replace','quality.bacteriology.view','quality.bacteriology.edit','quality.bacteriology.pool','quality_control.factor_viii_pool.view','quality_control.factor_viii_pool.create','quality_control.factor_viii_pool.result','quality_control.factor_viii_pool.cancel'],
    'gestao' => ['dashboard.global.view'],
    'processamento' => ['dashboard.global.view','dashboard.operational.view','production.view','production.create','production.edit','samples.view','shipments.view','shipments.create','shipments.edit','shipments.send','shipments.cancel','shipments.print','notifications.view','notifications.acknowledge','notifications.analyze','notifications.action_plan','notifications.close'],
    'lcqh' => ['dashboard.global.view','dashboard.operational.view','samples.view','samples.scope.global','shipments.view','shipments.print','reception.view','reception.receive','reception.reject','quality_control.results.manage','quality_results.view','quality_results.edit','quality_results.complete','quality_results.hemolysis','quality.bacteriology.view','quality.bacteriology.edit','quality.bacteriology.pool','quality_control.factor_viii_pool.view','quality_control.factor_viii_pool.create','quality_control.factor_viii_pool.result','quality_control.factor_viii_pool.cancel','validations.manage','transfusion_reactions.manage','notifications.view','notifications.view_all'],
    'agencia-transfusional' => ['dashboard.global.view','samples.view','shipments.view','shipments.create','shipments.edit','shipments.send','shipments.cancel','shipments.print','transfusion_reaction_consultation.view','reports.release.view'],
];
$mapping['administrador']=array_merge($mapping['administrador'],['admin_corrections.manage','notifications.view','notifications.view_all','notifications.acknowledge','notifications.analyze','notifications.action_plan','notifications.close','notifications.manage']);
$mapping['administrador']=array_merge($mapping['administrador'],['sampling_schedule.view','sampling_schedule.admin']);
$mapping['processamento'][]='sampling_schedule.view';
$mapping['administrador']=array_merge($mapping['administrador'],['monthly_closure.view','monthly_closure.close','monthly_closure.reopen']);
$mapping['gestao'][]='monthly_closure.view';
$mapping['processamento'][]='monthly_closure.view';
$mapping['lcqh'][]='monthly_closure.view';
$mapping['administrador']=array_merge($mapping['administrador'],['dashboard.operational.view','samples.view','samples.create','samples.edit','samples.send','quality_control.results.manage','validations.view','validations.create','validations.edit','validations.configure_tests','validations.enter_results','validations.complete','validations.manage','transfusion_reactions.view','transfusion_reactions.edit','transfusion_reactions.manage','reports.quality_control.view','indicators.view','indicators.analysis.manage','indicators.config.manage']);
$mapping['gestao']=array_merge($mapping['gestao'],['reports.quality_control.view','indicators.view']);
$mapping['lcqh']=array_merge($mapping['lcqh'],['validations.view','validations.create','validations.edit','validations.configure_tests','validations.enter_results','validations.complete','transfusion_reactions.view','transfusion_reactions.edit','reports.quality_control.view','indicators.view','indicators.analysis.manage']);
$mapping['administrador']=array_merge($mapping['administrador'],['transfusion_reaction_consultation.view','reports.release.view']);
$mapping['lcqh']=array_merge($mapping['lcqh'],['transfusion_reaction_consultation.view','reports.release.view']);
$mapping['gestao']=array_merge($mapping['gestao'],['transfusion_reaction_consultation.view','reports.release.view']);
$mapping['processamento']=array_merge($mapping['processamento'],['samples.create','samples.edit','samples.send']);
foreach ($mapping as &$rolePermissions) {
    $rolePermissions = array_values(array_unique(array_merge($rolePermissions, ['chat.view','chat.send','chat.private','chat.group'])));
}
unset($rolePermissions);

try {
    $pdo=Database::connection();
    $pdo->beginTransaction();
    $ensure=$pdo->prepare('INSERT INTO permissions(permission_key,name,module,status) VALUES(:permission_key,:name,:module,"active") ON DUPLICATE KEY UPDATE name=VALUES(name),module=VALUES(module),status="active"');
    $definitions = [
      'monthly_closure.view'=>['Visualizar fechamento mensal de CQ','monthly_closure'],'monthly_closure.close'=>['Fechar mês de CQ','monthly_closure'],'monthly_closure.reopen'=>['Reabrir mês de CQ','monthly_closure'],
      'sampling_schedule.view'=>['Visualizar cronograma de envio','sampling_schedule'],'sampling_schedule.admin'=>['Administrar regras do cronograma','sampling_schedule'],
      'production.view'=>['Visualizar produção','production'],'production.create'=>['Registrar produção','production'],'production.edit'=>['Editar produção','production'],'production.admin'=>['Administrar produção','production'],
      'notifications.analyze'=>['Criar e editar análises de notificações','notifications'],'notifications.action_plan'=>['Gerenciar planos de ação','notifications'],'notifications.close'=>['Encerrar análises de notificações','notifications'],'notifications.manage'=>['Gerenciar excepcionalmente notificações e análises','notifications'],
      'notifications.view'=>['Visualizar notificações da própria unidade','notifications'],'notifications.view_all'=>['Visualizar notificações de todas as unidades','notifications'],'notifications.acknowledge'=>['Registrar ciência de notificações','notifications'],
      'samples.view'=>['Visualizar amostras','samples'],'samples.create'=>['Cadastrar amostras','samples'],'samples.edit'=>['Editar amostras cadastradas','samples'],'samples.send'=>['Enviar amostras ao LCQH','samples'],'samples.scope.global'=>['Acessar amostras de todas as unidades','samples'],
      'reception.view'=>['Visualizar fila de recebimento','reception'],'reception.receive'=>['Receber amostras','reception'],'reception.reject'=>['Recusar amostras','reception'],'reception.edit_sample_data'=>['Corrigir dados de amostras no recebimento','reception'],
      'shipments.view'=>['Visualizar remessas','shipments'],'shipments.create'=>['Criar remessas','shipments'],'shipments.edit'=>['Editar remessas antes do recebimento','shipments'],'shipments.send'=>['Finalizar e enviar remessas','shipments'],'shipments.cancel'=>['Cancelar remessas','shipments'],'shipments.print'=>['Imprimir relatório de remessa','shipments'],'admin.bag_brands.manage'=>['Gerenciar marcas de bolsa','admin'],
      'admin.clients.manage'=>['Gerenciar clientes','admin'],'admin.units.manage'=>['Gerenciar unidades','admin'],'admin.blood_components.manage'=>['Gerenciar hemocomponentes','admin'],'admin.tests.manage'=>['Gerenciar testes','admin'],'admin.supplies.manage'=>['Gerenciar insumos e lotes','admin'],
      'chat.view'=>['Visualizar HubChat','chat'],'chat.send'=>['Enviar mensagens no HubChat','chat'],'chat.private'=>['Iniciar conversa privada','chat'],'chat.group'=>['Participar de grupos do HubChat','chat'],
      'quality_results.view'=>['Visualizar resultados de Controle de Qualidade','quality_results'],'quality_results.edit'=>['Editar resultados de Controle de Qualidade','quality_results'],'quality_results.complete'=>['Concluir análises de Controle de Qualidade','quality_results'],'quality_results.hemolysis'=>['Acessar ensaio de Grau de Hemólise','quality_results'],'quality_results.hemolysis_replace'=>['Substituir resultado não concluído de Grau de Hemólise','quality_results'],'quality.bacteriology.view'=>['Visualizar Bacteriológico','quality_results'],'quality.bacteriology.edit'=>['Registrar resultados bacteriológicos','quality_results'],'quality.bacteriology.pool'=>['Gerenciar pools bacteriológicos','quality_results'],'quality_control.factor_viii_pool.view'=>['Visualizar pools de Fator VIII','quality_results'],'quality_control.factor_viii_pool.create'=>['Criar pools de Fator VIII','quality_results'],'quality_control.factor_viii_pool.result'=>['Registrar resultados de pools de Fator VIII','quality_results'],'quality_control.factor_viii_pool.cancel'=>['Cancelar pools de Fator VIII','quality_results'],'admin.preservatives.manage'=>['Gerenciar preservantes','admin']
      ,'transfusion_reaction_consultation.view'=>['Consultar reações transfusionais','transfusion_reaction_consultation'],'reports.release.view'=>['Visualizar laudos','reports']
    ];
    foreach($definitions as $key=>$definition) $ensure->execute(['permission_key'=>$key,'name'=>$definition[0],'module'=>$definition[1]]);
    $pdo->exec("UPDATE permissions SET status='inactive' WHERE permission_key IN ('samples.receive','samples.reject')");
    $all=$pdo->query('SELECT id,permission_key FROM permissions')->fetchAll();
    $byKey=[]; foreach($all as $permission)$byKey[$permission['permission_key']]=(int)$permission['id'];
    $roleStmt=$pdo->prepare('SELECT id,name FROM roles WHERE slug=:slug');
    $delete=$pdo->prepare('DELETE FROM role_permissions WHERE role_id=:id');
    $insert=$pdo->prepare('INSERT IGNORE INTO role_permissions(role_id,permission_id) VALUES(:role_id,:permission_id)');
    foreach($mapping as $slug=>$keys){
        $roleStmt->execute(['slug'=>$slug]);$role=$roleStmt->fetch();
        if(!$role){fwrite(STDOUT,"Aviso: perfil {$slug} nao encontrado; ignorado.\n");continue;}
        $ids=$keys==='*'?array_values($byKey):array_map(static function(string $key)use($byKey):int{if(!isset($byKey[$key]))throw new RuntimeException("Permissao nao encontrada: {$key}");return $byKey[$key];},$keys);
        $delete->execute(['id'=>$role['id']]);foreach($ids as $permissionId)$insert->execute(['role_id'=>$role['id'],'permission_id'=>$permissionId]);
        fwrite(STDOUT,"{$role['name']}: ".count($ids)." permissoes sincronizadas.\n");
    }
    $pdo->commit();fwrite(STDOUT,"Sincronizacao RBAC concluida com sucesso.\n");
} catch(Throwable $e) { if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,'Erro: '.$e->getMessage().PHP_EOL);exit(1); }
