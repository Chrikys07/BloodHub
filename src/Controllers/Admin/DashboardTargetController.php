<?php
declare(strict_types=1);
namespace BloodHub\Controllers\Admin;

use BloodHub\Core\{AdminGuard,Auth,Csrf,Database,Flash};
use PDO;

final class DashboardTargetController
{
    public static function index():void
    {
        AdminGuard::enforce();$pdo=Database::connection();
        $components=$pdo->query("SELECT id,code,name FROM blood_components WHERE status='active' ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
        $tests=$pdo->query("SELECT t.id,t.name,t.code,GROUP_CONCAT(DISTINCT tbc.blood_component_id ORDER BY tbc.blood_component_id) component_ids FROM tests t JOIN test_blood_components tbc ON tbc.test_id=t.id WHERE t.status='active' AND t.is_final_result=1 GROUP BY t.id ORDER BY t.name")->fetchAll(PDO::FETCH_ASSOC);
        $targets=$pdo->query("SELECT d.*,bc.code component_code,bc.name component_name,t.name test_name FROM dashboard_conformity_targets d JOIN blood_components bc ON bc.id=d.blood_component_id JOIN tests t ON t.id=d.test_id ORDER BY d.effective_from DESC,bc.code,t.name")->fetchAll(PDO::FETCH_ASSOC);
        $pageTitle='Metas de Conformidade';$pageSubtitle='Metas agregadas versionadas usadas nas cores do Dashboard Global.';$flash=Flash::pull();$csrf=Csrf::token();$userAuth=Auth::user();
        require dirname(__DIR__,2).'/Views/admin/dashboard_targets/index.php';
    }

    public static function save():void
    {
        AdminGuard::enforce();if(!Csrf::validate($_POST['_csrf']??null)){Flash::set('error','Sessão expirada.');self::redirect();}
        $component=(int)($_POST['blood_component_id']??0);$test=(int)($_POST['test_id']??0);$raw=str_replace(',','.',trim((string)($_POST['minimum_percentage']??'')));$from=(string)($_POST['effective_from']??'');$active=($_POST['active']??'1')==='1'?1:0;
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$from);$percentage=filter_var($raw,FILTER_VALIDATE_FLOAT);
        $pdo=Database::connection();$eligible=$pdo->prepare("SELECT 1 FROM test_blood_components tbc JOIN tests t ON t.id=tbc.test_id AND t.status='active' AND t.is_final_result=1 WHERE tbc.blood_component_id=:component AND tbc.test_id=:test");$eligible->execute(['component'=>$component,'test'=>$test]);
        if(!$component||!$test||!$eligible->fetchColumn()||$percentage===false||$percentage<0||$percentage>100||!$date||$date->format('Y-m-d')!==$from){Flash::set('error','Informe hemocomponente, teste final, meta entre 0 e 100 e vigência válida.');self::redirect();}
        $pdo->beginTransaction();try{
            $before=$pdo->prepare('SELECT * FROM dashboard_conformity_targets WHERE blood_component_id=:component AND test_id=:test AND effective_from=:effective');$before->execute(['component'=>$component,'test'=>$test,'effective'=>$from]);$old=$before->fetch(PDO::FETCH_ASSOC)?:null;
            $next=$pdo->prepare('SELECT MIN(effective_from) FROM dashboard_conformity_targets WHERE blood_component_id=:component AND test_id=:test AND effective_from>:effective');$next->execute(['component'=>$component,'test'=>$test,'effective'=>$from]);$nextDate=$next->fetchColumn();$effectiveTo=$nextDate?(new \DateTimeImmutable((string)$nextDate))->modify('-1 day')->format('Y-m-d'):null;
            $pdo->prepare('UPDATE dashboard_conformity_targets SET effective_to=DATE_SUB(:effective,INTERVAL 1 DAY) WHERE blood_component_id=:component AND test_id=:test AND active=1 AND effective_from<:effective2 AND (effective_to IS NULL OR effective_to>=:effective3)')->execute(['component'=>$component,'test'=>$test,'effective'=>$from,'effective2'=>$from,'effective3'=>$from]);
            $pdo->prepare('INSERT INTO dashboard_conformity_targets(blood_component_id,test_id,minimum_percentage,effective_from,effective_to,active,created_by) VALUES(:component,:test,:percentage,:effective,:effective_to,:active,:user) ON DUPLICATE KEY UPDATE minimum_percentage=VALUES(minimum_percentage),effective_to=VALUES(effective_to),active=VALUES(active),created_by=VALUES(created_by)')->execute(['component'=>$component,'test'=>$test,'percentage'=>$percentage,'effective'=>$from,'effective_to'=>$effectiveTo,'active'=>$active,'user'=>Auth::user()['id']??null]);
            $id=(int)$pdo->lastInsertId();if(!$id){$q=$pdo->prepare('SELECT id FROM dashboard_conformity_targets WHERE blood_component_id=:component AND test_id=:test AND effective_from=:effective');$q->execute(['component'=>$component,'test'=>$test,'effective'=>$from]);$id=(int)$q->fetchColumn();}
            Auth::registerAudit('DASHBOARD_CONFORMITY_TARGET_SAVED','dashboard_conformity_targets',$id,$old,['blood_component_id'=>$component,'test_id'=>$test,'minimum_percentage'=>(float)$percentage,'effective_from'=>$from,'active'=>$active]);$pdo->commit();Flash::set('success','Meta de conformidade salva com vigência histórica.');
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();Flash::set('error','Não foi possível salvar a meta: '.$e->getMessage());}self::redirect();
    }

    private static function redirect():never{header('Location: /admin/dashboard-targets');exit;}
}
