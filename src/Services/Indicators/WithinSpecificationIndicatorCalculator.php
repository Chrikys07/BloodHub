<?php
declare(strict_types=1);
namespace BloodHub\Services\Indicators;

use BloodHub\Core\Database;
use PDO;
use BloodHub\Services\ClientScopeService;

final class WithinSpecificationIndicatorCalculator implements IndicatorCalculator
{
    public function calculate(int $year,int $month,array $scope=[]):array
    {
        $pdo=Database::connection();$states=[];$catalog=$pdo->query("SELECT DISTINCT bc.id component_id,bc.code,bc.name FROM blood_components bc JOIN test_blood_components tbc ON tbc.blood_component_id=bc.id JOIN tests t ON t.id=tbc.test_id AND t.status='active' AND t.is_final_result=1 WHERE bc.status='active' ORDER BY bc.code,bc.name")->fetchAll(PDO::FETCH_ASSOC);
        [$scopeSql,$scopeParams]=ClientScopeService::applyClientScope('COALESCE(s.client_id,ou.client_id)',$scope,'wi');
        $q=$pdo->prepare("SELECT s.id,s.blood_component_id,MONTH(s.production_date) month,bc.code,bc.name,e.conformity_status
            FROM samples s JOIN blood_components bc ON bc.id=s.blood_component_id AND bc.status='active'
            LEFT JOIN units ou ON ou.id=s.origin_unit_id
            JOIN sample_tests st ON st.sample_id=s.id AND st.status='completed'
            JOIN tests t ON t.id=st.test_id AND t.status='active' AND t.is_final_result=1 AND t.code<>'BACTERIOLOGY'
            JOIN test_results tr ON tr.id=(SELECT MAX(x.id) FROM test_results x WHERE x.sample_test_id=st.id)
            JOIN test_result_spec_evaluations e ON e.test_result_id=tr.id AND e.conformity_status IN ('CONFORMING','NONCONFORMING')
            WHERE s.purpose='quality_control' AND s.status='completed' AND YEAR(s.production_date)=:year AND $scopeSql");
        $q->execute(['year'=>$year]+$scopeParams);
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$this->merge($states,$r,(string)$r['conformity_status']);
        $b=$pdo->prepare("SELECT s.id,s.blood_component_id,MONTH(s.production_date) month,bc.code,bc.name,br.bacteriological_conformity
            FROM samples s JOIN blood_components bc ON bc.id=s.blood_component_id AND bc.status='active'
            LEFT JOIN units ou ON ou.id=s.origin_unit_id
            JOIN bacteriology_results br ON br.id=(SELECT br2.id FROM bacteriology_results br2 WHERE br2.sample_id=s.id AND br2.is_final=1 ORDER BY br2.performed_at DESC,br2.id DESC LIMIT 1)
            WHERE s.purpose='quality_control' AND s.status='completed' AND YEAR(s.production_date)=:year
              AND br.bacteriological_conformity IN ('conforming','nonconforming') AND $scopeSql");
        $b->execute(['year'=>$year]+$scopeParams);
        foreach($b->fetchAll(PDO::FETCH_ASSOC) as $r)$this->merge($states,$r,$r['bacteriological_conformity']==='conforming'?'CONFORMING':'NONCONFORMING');
        $months=array_fill(1,12,['inside'=>0,'outside'=>0,'value'=>null,'quantity'=>0,'rows'=>[]]);foreach($months as &$monthData)foreach($catalog as$c)$monthData['rows'][(int)$c['component_id']]=['component_id'=>(int)$c['component_id'],'code'=>$c['code'],'name'=>$c['name'],'inside'=>0,'outside'=>0,'value'=>null];unset($monthData);
        foreach($states as $s){$m=$s['month'];$key=$s['component_id'];$months[$m]['rows'][$key]??=['component_id'=>$key,'code'=>$s['code'],'name'=>$s['name'],'inside'=>0,'outside'=>0,'value'=>null];$bucket=$s['nonconforming']?'outside':'inside';$months[$m][$bucket]++;$months[$m]['rows'][$key][$bucket]++;}
        foreach($months as &$data){$data['quantity']=$data['inside']+$data['outside'];$data['value']=$data['quantity']?($data['inside']/$data['quantity']*100):null;foreach($data['rows'] as &$row){$n=$row['inside']+$row['outside'];$row['value']=$n?$row['inside']/$n*100:null;}unset($row);$data['rows']=array_values($data['rows']);usort($data['rows'],fn($a,$b)=>strnatcasecmp($a['code'],$b['code']));}unset($data);
        return ['months'=>$months,'month'=>$months[$month]];
    }
    private function merge(array &$states,array $row,string $status):void
    {$id=(int)$row['id'];$states[$id]??=['month'=>(int)$row['month'],'component_id'=>(int)$row['blood_component_id'],'code'=>$row['code'],'name'=>$row['name'],'nonconforming'=>false];if($status==='NONCONFORMING')$states[$id]['nonconforming']=true;}
}
