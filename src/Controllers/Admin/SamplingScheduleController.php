<?php
declare(strict_types=1);
namespace BloodHub\Controllers\Admin;

use BloodHub\Core\{AdminGuard,Auth,Csrf,Database,Flash};
use PDO;

final class SamplingScheduleController
{
    public static function index():void
    {
        AdminGuard::enforce('sampling_schedule.admin');$pdo=Database::connection();
        $components=$pdo->query("SELECT bc.id,bc.code,bc.name,sr.percentage,sr.minimum_units,sr.small_production_mode,sr.small_production_limit,sps.source_blood_component_id FROM blood_components bc JOIN sampling_rules sr ON sr.blood_component_id=bc.id AND sr.active=1 AND sr.effective_to IS NULL LEFT JOIN sampling_production_sources sps ON sps.target_blood_component_id=bc.id AND sps.active=1 AND sps.effective_to IS NULL WHERE bc.status='active' ORDER BY bc.code")->fetchAll(PDO::FETCH_ASSOC);
        $days=[];foreach($pdo->query('SELECT blood_component_id,weekday FROM sampling_schedule_days WHERE active=1 AND effective_to IS NULL')->fetchAll(PDO::FETCH_ASSOC)as$r)$days[(int)$r['blood_component_id']][]=(int)$r['weekday'];
        $sources=$pdo->query("SELECT id,code,name FROM blood_components WHERE status='active' ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);$pageTitle='Regras do Cronograma';$pageSubtitle='Configure dias, amostragem e fontes de produção com vigência histórica.';$flash=Flash::pull();$csrf=Csrf::token();$userAuth=Auth::user();require dirname(__DIR__,2).'/Views/admin/sampling_schedule/index.php';
    }
    public static function update():void
    {
        AdminGuard::enforce('sampling_schedule.admin');if(!Csrf::validate($_POST['_csrf']??null)){Flash::set('error','Sessão expirada.');self::redirect();}
        $effective=(string)($_POST['effective_from']??date('Y-m-d'));$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$effective);if(!$d||$d->format('Y-m-d')!==$effective){Flash::set('error','Data de vigência inválida.');self::redirect();}
        $pdo=Database::connection();$rules=(array)($_POST['rules']??[]);$postedDays=(array)($_POST['days']??[]);$pdo->beginTransaction();
        try{foreach($rules as$id=>$input){$id=(int)$id;$before=self::state($id);$percentage=max(0,(float)str_replace(',','.',(string)($input['percentage']??1)));$minimum=max(0,(int)($input['minimum_units']??0));$mode=($input['small_production_mode']??'standard')==='actual_up_to_limit'?'actual_up_to_limit':'standard';$limit=$mode==='actual_up_to_limit'?max(1,(int)($input['small_production_limit']??10)):null;
                $pdo->prepare('DELETE FROM sampling_rules WHERE blood_component_id=:id AND effective_from=:date AND effective_to IS NULL')->execute(['date'=>$effective,'id'=>$id]);$pdo->prepare('UPDATE sampling_rules SET effective_to=DATE_SUB(:date,INTERVAL 1 DAY) WHERE blood_component_id=:id AND active=1 AND effective_to IS NULL AND effective_from<:date2')->execute(['date'=>$effective,'date2'=>$effective,'id'=>$id]);$pdo->prepare('INSERT INTO sampling_rules(blood_component_id,percentage,minimum_units,small_production_mode,small_production_limit,effective_from) VALUES(:id,:percentage,:minimum,:mode,:limit,:date)')->execute(['id'=>$id,'percentage'=>$percentage,'minimum'=>$minimum,'mode'=>$mode,'limit'=>$limit,'date'=>$effective]);
                $pdo->prepare('DELETE FROM sampling_schedule_days WHERE blood_component_id=:id AND effective_from=:date AND effective_to IS NULL')->execute(['date'=>$effective,'id'=>$id]);$pdo->prepare('UPDATE sampling_schedule_days SET effective_to=DATE_SUB(:date,INTERVAL 1 DAY) WHERE blood_component_id=:id AND active=1 AND effective_to IS NULL AND effective_from<:date2')->execute(['date'=>$effective,'date2'=>$effective,'id'=>$id]);$ins=$pdo->prepare('INSERT INTO sampling_schedule_days(blood_component_id,weekday,effective_from) VALUES(:id,:weekday,:date)');foreach(array_keys((array)($postedDays[$id]??[]))as$weekday){$weekday=(int)$weekday;if($weekday>=0&&$weekday<=6)$ins->execute(['id'=>$id,'weekday'=>$weekday,'date'=>$effective]);}
                $pdo->prepare('DELETE FROM sampling_production_sources WHERE target_blood_component_id=:id AND effective_from=:date AND effective_to IS NULL')->execute(['date'=>$effective,'id'=>$id]);$pdo->prepare('UPDATE sampling_production_sources SET effective_to=DATE_SUB(:date,INTERVAL 1 DAY) WHERE target_blood_component_id=:id AND active=1 AND effective_to IS NULL AND effective_from<:date2')->execute(['date'=>$effective,'date2'=>$effective,'id'=>$id]);$source=(int)($input['source_id']??0);if($source&&$source!==$id)$pdo->prepare('INSERT INTO sampling_production_sources(target_blood_component_id,source_blood_component_id,effective_from) VALUES(:id,:source,:date)')->execute(['id'=>$id,'source'=>$source,'date'=>$effective]);
                Auth::registerAudit('SAMPLING_RULE_UPDATED','blood_components',$id,$before,self::state($id));Auth::registerAudit('SAMPLING_SCHEDULE_DAY_UPDATED','blood_components',$id,['days'=>$before['days']],['days'=>self::state($id)['days']]);}
            $pdo->commit();Flash::set('success','Regras do cronograma atualizadas com nova vigência.');}catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();Flash::set('error','Não foi possível atualizar as regras: '.$e->getMessage());}self::redirect();
    }
    private static function state(int$id):array{$pdo=Database::connection();$q=$pdo->prepare('SELECT percentage,minimum_units,small_production_mode,small_production_limit FROM sampling_rules WHERE blood_component_id=:id AND active=1 AND effective_to IS NULL ORDER BY id DESC LIMIT 1');$q->execute(['id'=>$id]);$s=$q->fetch(PDO::FETCH_ASSOC)?:[];$q=$pdo->prepare('SELECT weekday FROM sampling_schedule_days WHERE blood_component_id=:id AND active=1 AND effective_to IS NULL ORDER BY weekday');$q->execute(['id'=>$id]);$s['days']=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));return$s;}
    private static function redirect():never{header('Location: /admin/sampling-schedule');exit;}
}
