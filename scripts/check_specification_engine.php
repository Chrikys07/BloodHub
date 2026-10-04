<?php
declare(strict_types=1);
use BloodHub\Services\SpecificationEvaluator;
spl_autoload_register(static function(string $c):void{$p='BloodHub\\';if(str_starts_with($c,$p)){require dirname(__DIR__).'/src/'.str_replace('\\','/',substr($c,strlen($p))).'.php';}});
$cases=[
 ['CH Hb limite',['rule_type'=>'GTE','min_value'=>45,'max_value'=>null],45,true],['CH Hb abaixo',['rule_type'=>'GTE','min_value'=>45,'max_value'=>null],44.99,false],
 ['CH Ht CPDA-1',['rule_type'=>'BETWEEN','min_value'=>65,'max_value'=>80],65,true],['CH Ht aditiva',['rule_type'=>'BETWEEN','min_value'=>50,'max_value'=>70],70,true],
 ['CH hemólise',['rule_type'=>'LT','min_value'=>null,'max_value'=>.8],.8,false],['CP volume',['rule_type'=>'BETWEEN','min_value'=>40,'max_value'=>70],40,true],
 ['CP plaquetas',['rule_type'=>'GTE','min_value'=>5.5e10,'max_value'=>null],5.5e10,true],['CP pH',['rule_type'=>'GT','min_value'=>6.4,'max_value'=>null],6.4,false],
 ['PFC volume',['rule_type'=>'GTE','min_value'=>150,'max_value'=>null],150,true],['PFC FVIII',['rule_type'=>'GTE','min_value'=>.7,'max_value'=>null],.7,true],
 ['CRIO volume',['rule_type'=>'BETWEEN','min_value'=>10,'max_value'=>40],40,true],['CRIO fibrinogênio',['rule_type'=>'GT','min_value'=>150,'max_value'=>null],150,false],
 ['Microbiológica',['rule_type'=>'EQUAL_TEXT','expected_text'=>'Negativa'],' negativa ',true],
];$fail=0;foreach($cases as [$name,$rule,$actual,$expected]){$got=SpecificationEvaluator::evaluateRule($rule,$actual);echo ($got===$expected?'OK':'FALHA')." {$name}\n";if($got!==$expected)$fail++;}exit($fail?1:0);
