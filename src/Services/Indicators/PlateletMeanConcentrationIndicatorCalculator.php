<?php
declare(strict_types=1);
namespace BloodHub\Services\Indicators;

use BloodHub\Core\Database;
use DomainException;
use PDO;
use BloodHub\Services\ClientScopeService;

final class PlateletMeanConcentrationIndicatorCalculator implements IndicatorCalculator
{
    public function __construct(private readonly int $testId){}
    public function calculate(int $year,int $month,array $scope=[]):array
    {
        if(!$this->testId)throw new DomainException('Configure o teste final Plaquetas/U para este indicador.');
        [$scopeSql,$scopeParams]=ClientScopeService::applyClientScope('COALESCE(s.client_id,u.client_id)',$scope,'pm');
        $q=Database::connection()->prepare("SELECT s.id,MONTH(s.production_date) month,s.origin_unit_id,COALESCE(u.name,'Origem não informada') processing,tr.result_value_numeric value
            FROM samples s JOIN blood_components bc ON bc.id=s.blood_component_id AND bc.code='CP'
            JOIN sample_tests st ON st.sample_id=s.id AND st.test_id=:test AND st.status='completed'
            JOIN test_results tr ON tr.id=(SELECT MAX(x.id) FROM test_results x WHERE x.sample_test_id=st.id)
            LEFT JOIN units u ON u.id=s.origin_unit_id
            WHERE s.purpose='quality_control' AND s.status='completed' AND YEAR(s.production_date)=:year AND tr.result_value_numeric IS NOT NULL AND $scopeSql");
        $q->execute(['test'=>$this->testId,'year'=>$year]+$scopeParams);$months=array_fill(1,12,['sum'=>0.0,'quantity'=>0,'value'=>null,'rows'=>[]]);
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){$m=(int)$r['month'];$unit=(int)($r['origin_unit_id']??0);$v=(float)$r['value'];$months[$m]['sum']+=$v;$months[$m]['quantity']++;$months[$m]['rows'][$unit]??=['processing'=>$r['processing'],'sum'=>0.0,'quantity'=>0,'value'=>null];$months[$m]['rows'][$unit]['sum']+=$v;$months[$m]['rows'][$unit]['quantity']++;}
        foreach($months as &$data){$data['value']=$data['quantity']?$data['sum']/$data['quantity']:null;foreach($data['rows'] as &$row)$row['value']=$row['quantity']?$row['sum']/$row['quantity']:null;unset($row);$data['rows']=array_values($data['rows']);usort($data['rows'],fn($a,$b)=>strnatcasecmp($a['processing'],$b['processing']));}unset($data);
        return ['months'=>$months,'month'=>$months[$month]];
    }
}
