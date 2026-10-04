<?php
declare(strict_types=1);
namespace BloodHub\Controllers;

use BloodHub\Core\{AdminGuard,Auth,Database,Flash};
use BloodHub\Services\{TransfusionReactionConsultationService as Consultation,UnitAccessService};
use PDO;

final class TransfusionReactionConsultationController
{
    public static function index():void
    {
        AdminGuard::enforce(Consultation::PERMISSION);
        try{$result=Consultation::search($_GET);$units=Consultation::getVisibleUnitsForUser();$global=UnitAccessService::isGlobal();$clients=$global?Database::connection()->query("SELECT id,name,client_type FROM clients WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC):[];
            extract($result);$filters=['from'=>$from,'to'=>$to,'unit_id'=>(string)($_GET['unit_id']??''),'client_id'=>(string)($_GET['client_id']??''),'client_type'=>(string)($_GET['client_type']??''),'sample'=>trim((string)($_GET['sample']??'')),'status'=>(string)($_GET['status']??'')];
            $pageTitle='Consulta de Reações Transfusionais';$pageSubtitle='Acompanhe o andamento e os resultados das amostras enviadas ao Laboratório de Controle de Qualidade.';$flash=Flash::pull();$userAuth=Auth::user();require dirname(__DIR__).'/Views/transfusion_reaction_consultation/index.php';
        }catch(\DomainException$e){http_response_code(403);echo htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8');}
    }
}
