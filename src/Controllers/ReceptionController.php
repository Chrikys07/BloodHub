<?php
declare(strict_types=1);
namespace BloodHub\Controllers;

use BloodHub\Core\{AdminGuard,Auth,Csrf,Database,Flash,Permission,SamplePurpose};
use BloodHub\Services\{BacteriologyEligibility,ReceptionService,SampleTestSynchronizer};
use PDOException;
use BloodHub\Services\{ShelfLifeResolver,SampleUniquenessService};

final class ReceptionController
{
    public static function index():void
    {
        AdminGuard::enforce('reception.view');$filters=self::filters();
        self::view('index',['shipments'=>ReceptionService::queue($filters),'counters'=>ReceptionService::counters(),'origins'=>ReceptionService::origins(),'purposes'=>SamplePurpose::SHIPMENT_OPTIONS,'filters'=>$filters,'pageTitle'=>'Recebimento','pageSubtitle'=>'Confira e registre o recebimento das remessas encaminhadas ao LCQH.']);
    }

    public static function show():void
    {
        AdminGuard::enforce('reception.view');$shipment=self::requested();
        if(!$shipment){self::notFound();return;}
        if(in_array($shipment['status'],['received','rejected'],true)){self::receptionCompleted();return;}
        if(!in_array($shipment['status'],['awaiting_receipt','partially_received'],true)){self::notFound();return;}
        $brandStmt=Database::connection()->prepare('SELECT b.id,b.name,b.reference_number FROM bag_brands b WHERE (b.active=1 AND NOT EXISTS (SELECT 1 FROM bag_brands newer WHERE newer.active=1 AND LOWER(TRIM(newer.name))=LOWER(TRIM(b.name)) AND (newer.created_at>b.created_at OR (newer.created_at=b.created_at AND newer.id>b.id)))) OR b.id IN (SELECT bag_brand_id FROM samples WHERE sample_shipment_id=:shipment) ORDER BY b.name,b.reference_number');$brandStmt->execute(['shipment'=>$shipment['id']]);
        self::view('show',['shipment'=>$shipment,'samples'=>ReceptionService::samples((int)$shipment['id']),'boxes'=>ReceptionService::boxes((int)$shipment['id']),'components'=>ReceptionService::shipmentComponents((int)$shipment['id']),'brands'=>$brandStmt->fetchAll(),'pageTitle'=>'Remessa '.$shipment['shipment_code'],'pageSubtitle'=>'Conferência e registro de recebimento']);
    }

    public static function receive():void
    {
        AdminGuard::enforce('reception.receive');self::csrf();$id=self::postedId();$pdo=Database::connection();$pdo->beginTransaction();
        try{
            $shipment=ReceptionService::shipment($id,true);
            if(!$shipment||!in_array($shipment['status'],['awaiting_receipt','partially_received'],true))throw new \RuntimeException('A remessa foi alterada e não está mais disponível para recebimento.');
            $samples=ReceptionService::samples($id,true);$decisions=self::validateDecisions($samples);$boxes=ReceptionService::boxes($id);$boxData=self::validateBoxData($boxes,ReceptionService::shipmentComponents($id));$userId=(int)Auth::user()['id'];
            $receive=$pdo->prepare("UPDATE samples SET status='received',received_at=NOW(),received_by=:user,rejected_at=NULL,rejected_by=NULL,rejection_reason=NULL WHERE id=:id AND sample_shipment_id=:shipment AND status='awaiting_receipt'");
            $reject=$pdo->prepare("UPDATE samples SET status='rejected',rejected_at=NOW(),rejected_by=:user,rejection_reason=:reason,received_at=NULL,received_by=NULL WHERE id=:id AND sample_shipment_id=:shipment AND status='awaiting_receipt'");
            foreach($decisions as $sampleId=>$decision){if($decision['action']==='await')continue;$stmt=$decision['action']==='receive'?$receive:$reject;$params=['user'=>$userId,'id'=>$sampleId,'shipment'=>$id];if($decision['action']==='reject')$params['reason']=$decision['reason'];$stmt->execute($params);if($stmt->rowCount()!==1)throw new \RuntimeException('Uma amostra foi alterada por outro usuário. Atualize a página.');if($decision['action']==='receive'){if($shipment['purpose']==='transfusion_reaction'){SampleTestSynchronizer::syncTransfusionReaction((int)$sampleId);Auth::registerAudit('TRANSFUSION_REACTION_SAMPLE_RECEIVED','samples',(int)$sampleId,null,['shipment_id'=>$id]);}else{SampleTestSynchronizer::syncSample((int)$sampleId);BacteriologyEligibility::ensurePending((int)$sampleId);}}if(!empty($decision['automatic_expiration']))Auth::registerAudit('sample.automatic_expiration_rejection','samples',$sampleId,['status'=>'awaiting_receipt'],['status'=>'rejected','reason'=>$decision['reason'],'expiration_date'=>$decision['expiration_date'],'shelf_life_days'=>$decision['shelf_life_days']]);}
            $updateBox=$pdo->prepare('UPDATE sample_shipment_thermal_boxes SET sent_temperature=:sent,received_temperature=:received,received_at=NOW(),received_by=:user WHERE id=:box AND shipment_id=:shipment');
            foreach($boxData as $boxId=>$data){$updateBox->execute(['sent'=>$data['sent_temperature'],'received'=>$data['received_temperature'],'user'=>$userId,'box'=>$boxId,'shipment'=>$id]);if($updateBox->rowCount()!==1)throw new \RuntimeException('As caixas da remessa foram alteradas.');}
            $current=ReceptionService::samples($id,true);$newStatus=ReceptionService::calculateShipmentStatus($current);
            $pdo->prepare("UPDATE sample_shipments SET status=:status,received_at=IF(:received_at_status='received',NOW(),NULL),received_by=IF(:received_by_status='received',:received_by_user,NULL),rejected_at=IF(:rejected_at_status='rejected',NOW(),NULL),rejected_by=IF(:rejected_by_status='rejected',:rejected_by_user,NULL) WHERE id=:id")->execute(['status'=>$newStatus,'received_at_status'=>$newStatus,'received_by_status'=>$newStatus,'received_by_user'=>$userId,'rejected_at_status'=>$newStatus,'rejected_by_status'=>$newStatus,'rejected_by_user'=>$userId,'id'=>$id]);
            Auth::registerAudit('shipment.reception_register','sample_shipments',$id,['status'=>$shipment['status']],['status'=>$newStatus,'decisions'=>$decisions,'boxes'=>$boxData]);$pdo->commit();Flash::set('success',$newStatus==='partially_received'?'Conferência registrada. A remessa permanece em recebimento parcial.':'Conferência registrada com sucesso.');
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();self::handleFailure($e,'register',$id);}
        self::redirect('/reception');
    }

    public static function editSample():void
    {
        AdminGuard::enforce('reception.edit_sample_data');self::csrf();$sampleId=filter_var($_POST['sample_id']??null,FILTER_VALIDATE_INT);$pdo=Database::connection();$shipmentId=0;$returnTo='/reception';$pdo->beginTransaction();
        try{
            if(!$sampleId)throw new \RuntimeException('Amostra inválida ou não encontrada.');
            $s=$pdo->prepare('SELECT id,sample_shipment_id,donation_number,blood_component_id,production_date,bag_brand_id,status,preservative_id_snapshot,shelf_life_days_snapshot,expiration_date FROM samples WHERE id=:id FOR UPDATE');$s->execute(['id'=>$sampleId]);$before=$s->fetch();
            if(!$before)throw new \RuntimeException('Amostra não encontrada.');if($before['status']!=='awaiting_receipt')throw new \RuntimeException('Somente amostras aguardando recebimento podem ser corrigidas.');
            $shipmentId=(int)($before['sample_shipment_id']??0);
            if(!$shipmentId)throw new \RuntimeException('A amostra não está vinculada a uma remessa.');
            $shipment=ReceptionService::shipment($shipmentId,true);if(!$shipment)throw new \RuntimeException('Remessa não encontrada.');
            if(in_array($shipment['status'],['received','rejected'],true))throw new \RuntimeException('Esta remessa já teve o recebimento concluído.');
            if(!in_array($shipment['status'],['awaiting_receipt','partially_received'],true))throw new \RuntimeException('Esta remessa não está disponível na fila de recebimento.');
            $returnTo='/reception/view?id='.$shipmentId;
            $after=['donation_number'=>trim((string)($_POST['donation_number']??'')),'blood_component_id'=>(int)($_POST['blood_component_id']??0),'production_date'=>trim((string)($_POST['production_date']??'')),'bag_brand_id'=>(int)($_POST['bag_brand_id']??0)];self::validateSampleData($after);SampleUniquenessService::assertRowsAvailable([['id'=>$sampleId]+$after],true);
            $changes=[];$old=[];foreach($after as $field=>$value)if((string)$before[$field]!== (string)$value){$changes[$field]=$value;$old[$field]=$before[$field];}
            if($changes){$rule=ShelfLifeResolver::forBagBrand($after['blood_component_id'],$after['bag_brand_id'],$after['production_date'],true);$pdo->prepare('UPDATE samples SET donation_number=:donation_number,blood_component_id=:blood_component_id,production_date=:production_date,collection_date=:production_date2,bag_brand_id=:bag_brand_id,preservative_id_snapshot=:preservative,shelf_life_configuration_id_snapshot=:configuration,shelf_life_days_snapshot=:days,expiration_date=:expiration WHERE id=:id')->execute($after+['production_date2'=>$after['production_date'],'preservative'=>$rule['preservative_id'],'configuration'=>$rule['configuration_id'],'days'=>$rule['shelf_life_days'],'expiration'=>$rule['expiration_date'],'id'=>$sampleId]);$changes+=['preservative_id_snapshot'=>$rule['preservative_id'],'shelf_life_days_snapshot'=>$rule['shelf_life_days'],'expiration_date'=>$rule['expiration_date']];Auth::registerAudit('reception.sample_data_corrected','samples',$sampleId,$old,$changes);}
            $pdo->commit();Flash::set('success',$changes?'Dados da amostra corrigidos e auditados.':'Nenhuma alteração foi identificada.');
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();self::handleFailure($e,'edit_sample',$shipmentId);}
        self::redirect($returnTo);
    }

    private static function validateDecisions(array $samples):array
    {
        $posted=(array)($_POST['decisions']??[]);$out=[];
        foreach($samples as $sample){if($sample['status']!=='awaiting_receipt')continue;$id=(int)$sample['id'];$row=is_array($posted[$id]??null)?$posted[$id]:[];$action=(string)($row['action']??'await');$reason=trim((string)($row['reason']??''));if(ShelfLifeResolver::isExpired($sample['expiration_date']??null)){$action='reject';$reason='Hemocomponente vencido'.($reason!==''?' — '.mb_substr($reason,0,470):'');$out[$id]=['action'=>'reject','reason'=>$reason,'automatic_expiration'=>true,'expiration_date'=>$sample['expiration_date'],'shelf_life_days'=>$sample['shelf_life_days_snapshot']];continue;}if(!in_array($action,['receive','await','reject'],true))throw new \RuntimeException('Decisão inválida para uma amostra.');if($action==='reject'&&!Permission::can('reception.reject'))throw new \RuntimeException('Seu perfil não possui permissão para recusar amostras.');if($action==='reject'&&($reason===''||mb_strlen($reason)>500))throw new \RuntimeException('Informe o motivo da recusa de cada amostra recusada.');$out[$id]=['action'=>$action,'reason'=>$action==='reject'?$reason:null];}
        return $out;
    }
    private static function validateBoxData(array $boxes,array $allowedComponents):array
    {
        $posted=(array)($_POST['boxes']??[]);$allowed=array_map('intval',array_column($allowedComponents,'id'));$out=[];
        foreach($boxes as $box){$id=(int)$box['id'];$row=is_array($posted[$id]??null)?$posted[$id]:[];$sent=self::temperature($row['sent_temperature']??null,'envio');$received=self::temperature($row['received_temperature']??null,'recebimento');$components=array_values(array_unique(array_map('intval',array_filter(explode(',',(string)$box['component_ids'])))));if(!$components||array_diff($components,$allowed))throw new \RuntimeException('A caixa possui hemocomponentes inválidos para esta remessa.');$out[$id]=['sent_temperature'=>$sent,'received_temperature'=>$received,'components'=>$components];}return $out;
    }
    private static function temperature(mixed $value,string $label):string {$raw=str_replace(',','.',trim((string)$value));if($raw===''||!preg_match('/^-?\d{1,3}(?:\.\d{1,2})?$/',$raw)||(float)$raw < -99.99||(float)$raw>999.99)throw new \RuntimeException("Informe uma temperatura de {$label} válida para todas as caixas.");return number_format((float)$raw,2,'.','');}
    private static function validateSampleData(array $data):void {if($data['donation_number']===''||mb_strlen($data['donation_number'])>120)throw new \RuntimeException('Informe um número de doação válido.');$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$data['production_date']);if(!$d||$d->format('Y-m-d')!==$data['production_date'])throw new \RuntimeException('Informe uma data de produção válida.');$s=Database::connection()->prepare("SELECT 1 FROM blood_components WHERE id=:id AND status='active'");$s->execute(['id'=>$data['blood_component_id']]);if(!$s->fetchColumn())throw new \RuntimeException('Selecione um hemocomponente ativo.');$s=Database::connection()->prepare('SELECT 1 FROM bag_brands WHERE id=:id');$s->execute(['id'=>$data['bag_brand_id']]);if(!$s->fetchColumn())throw new \RuntimeException('Selecione uma marca de bolsa existente.');}
    private static function filters():array {$date=trim((string)($_GET['sent_date']??''));if($date!==''&&(!($d=\DateTimeImmutable::createFromFormat('!Y-m-d',$date))||$d->format('Y-m-d')!==$date))$date='';return ['origin_unit_id'=>(int)($_GET['origin_unit_id']??0),'purpose'=>trim((string)($_GET['purpose']??'')),'sent_date'=>$date,'shipment_code'=>mb_substr(trim((string)($_GET['shipment_code']??'')),0,80)];}
    private static function requested():?array {$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);return$id?ReceptionService::shipment((int)$id):null;}
    private static function postedId():int {$id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);if(!$id){Flash::set('error','Remessa inválida.');self::redirect('/reception');}return(int)$id;}
    private static function csrf():void {if(!Csrf::validate($_POST['_csrf']??null)){Flash::set('error','Sua sessão expirou.');self::redirect('/reception');}}
    private static function handleFailure(\Throwable $e,string $action,int $id):void {error_log(sprintf('[BloodHub][reception.%s] shipment_id=%d exception=%s code=%s message=%s file=%s line=%d',$action,$id,$e::class,(string)$e->getCode(),$e->getMessage(),$e->getFile(),$e->getLine()));Flash::set('error',$e instanceof PDOException?'Não foi possível processar a remessa. Tente novamente.':$e->getMessage());}
    private static function view(string $name,array $vars):void {extract($vars);$flash=Flash::pull();$csrf=Csrf::token();$userAuth=Auth::user();require dirname(__DIR__).'/Views/reception/'.$name.'.php';}
    private static function redirect(string $url):never {header('Location: '.$url);exit;}
    private static function notFound():void {http_response_code(404);echo'Remessa não encontrada ou não está mais na fila de recebimento.';}
    private static function receptionCompleted():void {http_response_code(409);echo'Esta remessa já teve o recebimento concluído.';}
}
