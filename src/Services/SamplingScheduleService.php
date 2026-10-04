<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\Database;
use DateTimeImmutable;
use PDO;

final class SamplingScheduleService
{
    /** Regra efetiva no período, compartilhada pelo cronograma e por relatórios. */
    public static function effectiveRule(int $componentId, DateTimeImmutable $period): ?array
    {
        $q=Database::connection()->prepare('SELECT sr.* FROM sampling_rules sr WHERE sr.blood_component_id=:component AND sr.active=1 AND sr.effective_from<=:end AND (sr.effective_to IS NULL OR sr.effective_to>=:start) ORDER BY sr.effective_from DESC,sr.id DESC LIMIT 1');
        $q->execute(['component'=>$componentId,'start'=>$period->format('Y-m-01'),'end'=>$period->format('Y-m-t')]);
        return $q->fetch(PDO::FETCH_ASSOC)?:null;
    }

    /** Fonte de produção efetiva (ex.: PF usa PFC), sem duplicar a regra no consumidor. */
    public static function productionSourceId(int $componentId, DateTimeImmutable $date): int
    {
        $q=Database::connection()->prepare('SELECT source_blood_component_id FROM sampling_production_sources WHERE target_blood_component_id=:component AND active=1 AND effective_from<=:date AND (effective_to IS NULL OR effective_to>=:date2) ORDER BY effective_from DESC,id DESC LIMIT 1');
        $q->execute(['component'=>$componentId,'date'=>$date->format('Y-m-d'),'date2'=>$date->format('Y-m-d')]);
        return (int)($q->fetchColumn()?:$componentId);
    }
    public static function buildMonthlySummary(int $unitId,string $month,?DateTimeImmutable $today=null):array
    {
        $today ??= new DateTimeImmutable('today');
        $start=DateTimeImmutable::createFromFormat('!Y-m-d',$month.'-01');
        if(!$start)throw new \InvalidArgumentException('Mês inválido.');
        $end=$start->modify('last day of this month');$asOf=$end<$today?$end:($start>$today?$start->modify('-1 day'):$today);
        $components=self::components($start);$production=self::getMonthlyProduction($unitId,$start,$end,$asOf);$sent=self::getMonthlySent($unitId,$start,$end);$rules=self::rules($start);$days=self::getEligibleWeekdays($start);
        $rows=[];
        foreach($components as$c){$id=(int)$c['id'];$source=(int)($c['source_id']?:$id);$produced=(int)($production[$source]??0);$rule=$rules[$id]??null;$required=self::calculateRequired($produced,$rule);$sentCount=(int)($sent[$id]??0);$pending=max(0,$required-$sentCount);$eligible=$days[$id]??[];
            $projectedProduction=self::projectProduction($produced,$start,$asOf,$today);$projectedRequired=self::calculateRequired($projectedProduction,$rule);
            $rows[]=$c+['produced'=>$produced,'required'=>$required,'sent'=>$sentCount,'pending'=>$pending,'rule'=>$rule,'eligible_weekdays'=>$eligible,'projected_production'=>$projectedProduction,'projected_required'=>$projectedRequired];
        }
        return $rows;
    }

    public static function getMonthlyProduction(int $unitId,DateTimeImmutable $start,DateTimeImmutable $end,DateTimeImmutable $asOf):array
    {
        if($asOf<$start)return[];$q=Database::connection()->prepare('SELECT blood_component_id,SUM(quantity) total FROM production_records WHERE unit_id=:unit AND production_date BETWEEN :start AND :end GROUP BY blood_component_id');
        $q->execute(['unit'=>$unitId,'start'=>$start->format('Y-m-d'),'end'=>min($end,$asOf)->format('Y-m-d')]);return self::mapTotals($q->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function getMonthlySent(int $unitId,DateTimeImmutable $start,DateTimeImmutable $end):array
    {
        $q=Database::connection()->prepare("SELECT blood_component_id,COUNT(DISTINCT id) total FROM samples WHERE origin_unit_id=:unit AND purpose='quality_control' AND status<>'cancelled' AND production_date BETWEEN :start AND :end AND blood_component_id IS NOT NULL GROUP BY blood_component_id");
        $q->execute(['unit'=>$unitId,'start'=>$start->format('Y-m-d'),'end'=>$end->format('Y-m-d')]);return self::mapTotals($q->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function calculateRequired(int $production,?array $rule):int
    {
        if($production<=0||!$rule)return 0;
        if(($rule['small_production_mode']??'standard')==='actual_up_to_limit'&&$production<=(int)($rule['small_production_limit']??0))return $production;
        return max((int)ceil($production*((float)$rule['percentage']/100)),(int)$rule['minimum_units']);
    }

    public static function getEligibleWeekdays(DateTimeImmutable $period):array
    {
        $q=Database::connection()->prepare('SELECT blood_component_id,weekday FROM sampling_schedule_days WHERE active=1 AND effective_from<=:end AND (effective_to IS NULL OR effective_to>=:start) ORDER BY weekday');
        $q->execute(['start'=>$period->format('Y-m-01'),'end'=>$period->format('Y-m-t')]);$out=[];foreach($q->fetchAll(PDO::FETCH_ASSOC)as$r)$out[(int)$r['blood_component_id']][]=(int)$r['weekday'];return$out;
    }

    public static function buildDailySuggestion(array $row,DateTimeImmutable $period,int $selectedWeekday,?DateTimeImmutable $today=null):array
    {
        $today??=new DateTimeImmutable('today');$isPast=$period->format('Y-m')<$today->format('Y-m');$isFuture=$period->format('Y-m')>$today->format('Y-m');$eligible=in_array($selectedWeekday,$row['eligible_weekdays'],true);
        if($isPast)return['amount'=>0,'status'=>'closed','eligible'=>$eligible];
        if($isFuture)return['amount'=>0,'status'=>'future','eligible'=>$eligible];
        if($row['produced']===0)return['amount'=>0,'status'=>'no_production','eligible'=>$eligible];
        if($row['pending']===0)return['amount'=>0,'status'=>'complete','eligible'=>$eligible];
        if(!$eligible)return['amount'=>0,'status'=>'not_scheduled','eligible'=>false];
        if($selectedWeekday!==(int)$today->format('w'))return['amount'=>0,'status'=>'await_day','eligible'=>true];
        $dates=self::eligibleDates($period,$row['eligible_weekdays']);$elapsed=array_values(array_filter($dates,fn($d)=>$d<=$today));$remaining=array_values(array_filter($dates,fn($d)=>$d>=$today));
        $target=(int)ceil($row['projected_required']*(count($elapsed)/max(1,count($dates))));$projectedPending=max(0,$row['projected_required']-$row['sent']);$amount=max(0,min((int)ceil($target-$row['sent']),$projectedPending));
        if(count($remaining)===1)$amount=$projectedPending;
        return['amount'=>$amount,'status'=>$amount>0?'send':'on_track','eligible'=>true];
    }

    public static function endOfMonthProjection(array $rows,DateTimeImmutable $period,?DateTimeImmutable $today=null):?array
    {
        $today??=new DateTimeImmutable('today');if($period->format('Y-m')!==$today->format('Y-m'))return null;$future=self::businessDays($today->modify('+1 day'),$period->modify('last day of this month'));if(count($future)>3)return null;
        $dates=array_slice(array_merge([$today],$future),0,3);$result=[];foreach($rows as$r){$missing=max(0,$r['projected_required']-$r['sent']);$parts=self::largestRemainder($missing,count($dates));$result[]=$r+['end_month_missing'=>$missing,'daily_distribution'=>array_pad($parts,3,0)];}return['dates'=>$dates,'rows'=>$result];
    }

    private static function components(DateTimeImmutable $period):array
    {$q=Database::connection()->prepare("SELECT DISTINCT bc.id,bc.code,bc.name,sps.source_blood_component_id source_id FROM blood_components bc JOIN sampling_rules sr ON sr.blood_component_id=bc.id AND sr.active=1 AND sr.effective_from<=:end AND (sr.effective_to IS NULL OR sr.effective_to>=:start) LEFT JOIN sampling_production_sources sps ON sps.target_blood_component_id=bc.id AND sps.active=1 AND sps.effective_from<=:end2 AND (sps.effective_to IS NULL OR sps.effective_to>=:start2) WHERE bc.status='active' ORDER BY bc.code");$q->execute(['start'=>$period->format('Y-m-01'),'end'=>$period->format('Y-m-t'),'start2'=>$period->format('Y-m-01'),'end2'=>$period->format('Y-m-t')]);return$q->fetchAll(PDO::FETCH_ASSOC);}
    private static function rules(DateTimeImmutable $period):array
    {$q=Database::connection()->prepare('SELECT sr.* FROM sampling_rules sr WHERE active=1 AND effective_from<=:end AND (effective_to IS NULL OR effective_to>=:start) ORDER BY effective_from DESC,id DESC');$q->execute(['start'=>$period->format('Y-m-01'),'end'=>$period->format('Y-m-t')]);$out=[];foreach($q->fetchAll(PDO::FETCH_ASSOC)as$r)$out[(int)$r['blood_component_id']]??=$r;return$out;}
    private static function projectProduction(int $produced,DateTimeImmutable $start,DateTimeImmutable $asOf,DateTimeImmutable $today):int
    {if($asOf<$start)return 0;if($start->format('Y-m')!==$today->format('Y-m'))return$produced;$elapsed=count(self::businessDays($start,$asOf));$total=count(self::businessDays($start,$start->modify('last day of this month')));return$elapsed? (int)ceil($produced/$elapsed*$total):0;}
    private static function businessDays(DateTimeImmutable $from,DateTimeImmutable $to):array
    {$out=[];for($d=$from;$d<=$to;$d=$d->modify('+1 day'))if((int)$d->format('N')<=5)$out[]=$d;return$out;}
    private static function eligibleDates(DateTimeImmutable $period,array $weekdays):array
    {$out=[];for($d=$period->modify('first day of this month');$d->format('Y-m')===$period->format('Y-m');$d=$d->modify('+1 day'))if(in_array((int)$d->format('w'),$weekdays,true))$out[]=$d;return$out;}
    private static function largestRemainder(int $total,int $slots):array
    {if($slots<=0)return[];$base=intdiv($total,$slots);$rest=$total%$slots;$out=array_fill(0,$slots,$base);for($i=0;$i<$rest;$i++)$out[$i]++;return$out;}
    private static function mapTotals(array $rows):array{$out=[];foreach($rows as$r)$out[(int)$r['blood_component_id']]=(int)$r['total'];return$out;}
}
