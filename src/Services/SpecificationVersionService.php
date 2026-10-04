<?php
declare(strict_types=1);

namespace BloodHub\Services;

use BloodHub\Core\{Auth, Database};
use DateTimeImmutable;
use DomainException;
use PDO;
use Throwable;

final class SpecificationVersionService
{
    private const USED_MESSAGE = 'Esta especificação já foi utilizada em resultados de controle de qualidade e não pode ser alterada. Crie uma nova especificação/versionamento para modificar os critérios.';
    private const DELETE_USED_MESSAGE = 'Esta especificação já foi utilizada em resultados de controle de qualidade e não pode ser excluída.';

    public static function save(array $input): array
    {
        $componentId = (int)($input['blood_component_id'] ?? 0);
        $testId = (int)($input['test_id'] ?? 0);
        $id = (int)($input['specification_id'] ?? 0);
        $rule = (string)($input['rule_type'] ?? '');
        $condition = trim((string)($input['condition_type'] ?? '')) ?: null;
        $allowed = ['GT','GTE','LT','LTE','BETWEEN','EQUAL_NUMERIC','EQUAL_TEXT','BOOLEAN'];
        $conditions = ['PRESERVATIVE','PROCESS_METHOD','STORAGE_DAY','LEUKOREDUCED','PATHOGEN_REDUCTION','PRE_STORAGE_LEUKOREDUCTION','POOL_TYPE'];
        $min = self::number($input['min_value'] ?? null);
        $max = self::number($input['max_value'] ?? null);
        $expected = trim((string)($input['expected_text'] ?? '')) ?: null;
        $preservativeId = (int)($input['preservative_id'] ?? 0) ?: null;
        $conditionValue = trim((string)($input['condition_value'] ?? '')) ?: null;

        if (in_array($rule, ['LT','LTE'], true)) { $max = $min; $min = null; }
        elseif (in_array($rule, ['GT','GTE','EQUAL_NUMERIC'], true)) $max = null;
        elseif (in_array($rule, ['EQUAL_TEXT','BOOLEAN'], true)) $min = $max = null;
        if ($rule === 'BOOLEAN') {
            $boolean = array_key_exists('expected_boolean', $input) ? $input['expected_boolean'] : $expected;
            $expected = self::boolean($boolean);
        }
        if ($condition === 'PRESERVATIVE') $conditionValue = null;
        elseif ($condition === 'STORAGE_DAY') { $conditionValue = 'LAST_DAY'; $preservativeId = null; }
        elseif (in_array($condition, ['LEUKOREDUCED','PATHOGEN_REDUCTION','PRE_STORAGE_LEUKOREDUCTION'], true)) { $conditionValue = 'TRUE'; $preservativeId = null; }
        else $preservativeId = null;

        $from = trim((string)($input['effective_from'] ?? '')) ?: date('Y-m-d');
        $to = trim((string)($input['effective_to'] ?? '')) ?: null;
        $active = (string)($input['active'] ?? '1');
        if (!$componentId || !$testId || !in_array($rule, $allowed, true) || ($condition !== null && !in_array($condition, $conditions, true)) || !self::date($from) || ($to !== null && !self::date($to)) || ($to !== null && $to < $from) || !in_array($active, ['0','1'], true)) throw new DomainException('Revise os dados da especificação.');
        if ($rule === 'BETWEEN' && ($min === null || $max === null || (float)$min > (float)$max)) throw new DomainException('Informe uma faixa mínima e máxima válida.');
        if (in_array($rule, ['GT','GTE','LT','LTE','EQUAL_NUMERIC'], true) && $min === null && $max === null) throw new DomainException('Informe o valor numérico da regra.');
        if (in_array($rule, ['EQUAL_TEXT','BOOLEAN'], true) && $expected === null) throw new DomainException('Informe o resultado esperado.');
        if ($condition === 'PRESERVATIVE' && !$preservativeId) throw new DomainException('Selecione o preservante da condição.');
        if (in_array($condition, ['PROCESS_METHOD','POOL_TYPE'], true) && !$conditionValue) throw new DomainException('Informe o valor da condição.');

        $data = ['c'=>$componentId,'t'=>$testId,'rule'=>$rule,'min'=>$min,'max'=>$max,'expected'=>$expected,'unit'=>trim((string)($input['unit']??''))?:null,'p'=>$preservativeId,'condition'=>$condition,'condition_value'=>$conditionValue,'from'=>$from,'to'=>$to,'source'=>trim((string)($input['source_name']??''))?:null,'reference'=>trim((string)($input['source_reference']??''))?:null,'notes'=>trim((string)($input['notes']??''))?:null,'sampling'=>trim((string)($input['sampling_requirement_notes']??''))?:null,'active'=>(int)$active,'user'=>Auth::user()['id']??null];
        $pdo = Database::connection();
        $blockedOld = null;
        try {
            $pdo->beginTransaction();
            if ($id) {
                $old = self::lock($pdo, $id, $componentId);
                if (!$old) throw new DomainException('Especificação não encontrada.');
                if (self::isUsed($id, $pdo)) {
                    $blockedOld = $old;
                    throw new DomainException(self::USED_MESSAGE);
                }
                self::overlap($pdo, $data, $id);
                $pdo->prepare('UPDATE blood_component_test_specifications SET test_id=:t,rule_type=:rule,min_value=:min,max_value=:max,expected_text=:expected,unit=:unit,preservative_id=:p,condition_type=:condition,condition_value=:condition_value,effective_from=:from,effective_to=:to,source_name=:source,source_reference=:reference,notes=:notes,sampling_requirement_notes=:sampling,active=:active,updated_by=:user WHERE id=:id AND blood_component_id=:c')->execute($data + ['id'=>$id]);
                Auth::registerAudit('SPECIFICATION_UPDATED', 'blood_component_test_specifications', $id, $old, $data);
                $result = ['message'=>'Especificação atualizada.','id'=>$id];
            } else {
                self::overlap($pdo, $data);
                self::insert($pdo, $data + ['supersedes'=>null,'version'=>1]);
                $id = (int)$pdo->lastInsertId();
                Auth::registerAudit('SPECIFICATION_CREATED', 'blood_component_test_specifications', $id, null, $data);
                $result = ['message'=>'Especificação criada.','id'=>$id];
            }
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($blockedOld !== null) Auth::registerAudit('SPECIFICATION_UPDATE_BLOCKED_USED', 'blood_component_test_specifications', $id, $blockedOld, null);
            throw $e;
        }
    }

    public static function delete(int $id, int $componentId): void
    {
        $pdo = Database::connection();
        $blockedOld = null;
        try {
            $pdo->beginTransaction();
            $old = self::lock($pdo, $id, $componentId);
            if (!$old) throw new DomainException('Especificação não encontrada.');
            if (self::isUsed($id, $pdo)) {
                $blockedOld = $old;
                throw new DomainException(self::DELETE_USED_MESSAGE);
            }
            Auth::registerAudit('SPECIFICATION_DELETED', 'blood_component_test_specifications', $id, $old, null);
            $pdo->prepare('DELETE FROM blood_component_test_specifications WHERE id=:id AND blood_component_id=:c')->execute(['id'=>$id,'c'=>$componentId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($blockedOld !== null) Auth::registerAudit('SPECIFICATION_DELETE_BLOCKED_USED', 'blood_component_test_specifications', $id, $blockedOld, null);
            throw $e;
        }
    }

    public static function isUsed(int $id, ?PDO $pdo = null): bool
    {
        $pdo ??= Database::connection();
        $query = $pdo->prepare("SELECT EXISTS(
            SELECT 1 FROM test_result_spec_evaluations e
            JOIN test_results tr ON tr.id=e.test_result_id
            JOIN sample_tests st ON st.id=tr.sample_test_id
            WHERE e.specification_id=:id AND st.status='completed'
        )");
        $query->execute(['id'=>$id]);
        if ((bool)$query->fetchColumn()) return true;
        foreach ([['factor_viii_pools','specification_id'],['factor_viii_sample_results','pool_specification_id'],['factor_viii_sample_results','individual_specification_id']] as [$table,$column]) {
            if (!self::tableExists($pdo, $table)) continue;
            $query = $pdo->prepare("SELECT EXISTS(SELECT 1 FROM {$table} WHERE {$column}=:id)");
            $query->execute(['id'=>$id]);
            if ((bool)$query->fetchColumn()) return true;
        }
        return false;
    }

    public static function toggle(int $id, int $componentId): void
    {
        $pdo=Database::connection();
        $old=self::lock($pdo,$id,$componentId);
        if(!$old) return;
        $active=$old['active']?0:1;
        $pdo->prepare('UPDATE blood_component_test_specifications SET active=:active,updated_by=:u WHERE id=:id')->execute(['active'=>$active,'u'=>Auth::user()['id']??null,'id'=>$id]);
        Auth::registerAudit($active?'SPECIFICATION_REACTIVATED':'SPECIFICATION_DEACTIVATED','blood_component_test_specifications',$id,$old,['active'=>$active]);
    }

    private static function lock(PDO $pdo, int $id, int $componentId): array|false
    {
        $query=$pdo->prepare('SELECT * FROM blood_component_test_specifications WHERE id=:id AND blood_component_id=:c FOR UPDATE');
        $query->execute(['id'=>$id,'c'=>$componentId]);
        return $query->fetch(PDO::FETCH_ASSOC);
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $query=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');
        $query->execute(['table'=>$table]);
        return (bool)$query->fetchColumn();
    }

    private static function insert(PDO $pdo,array $data):void
    {
        $data['created_by']=$data['user'];$data['updated_by']=$data['user'];unset($data['user']);
        $pdo->prepare('INSERT INTO blood_component_test_specifications(supersedes_id,version_number,blood_component_id,test_id,rule_type,min_value,max_value,expected_text,unit,preservative_id,condition_type,condition_value,effective_from,effective_to,source_name,source_reference,notes,sampling_requirement_notes,active,created_by,updated_by) VALUES(:supersedes,:version,:c,:t,:rule,:min,:max,:expected,:unit,:p,:condition,:condition_value,:from,:to,:source,:reference,:notes,:sampling,:active,:created_by,:updated_by)')->execute($data);
    }

    private static function overlap(PDO $pdo,array $data,int $exclude=0):void
    {
        $query=$pdo->prepare("SELECT id FROM blood_component_test_specifications WHERE blood_component_id=:c AND test_id=:t AND active=1 AND id<>:x AND condition_type <=> :condition AND preservative_id <=> :p AND (:is_preservative=1 OR condition_value <=> :condition_value) AND COALESCE(effective_from,'1000-01-01')<=COALESCE(:to_date,'9999-12-31') AND COALESCE(effective_to,'9999-12-31')>=COALESCE(:from_date,'1000-01-01') LIMIT 1 FOR UPDATE");
        $query->execute(['c'=>$data['c'],'t'=>$data['t'],'x'=>$exclude,'condition'=>$data['condition'],'p'=>$data['p'],'is_preservative'=>$data['condition']==='PRESERVATIVE'?1:0,'condition_value'=>$data['condition_value'],'from_date'=>$data['from'],'to_date'=>$data['to']]);
        if($query->fetchColumn()) throw new DomainException('Já existe uma especificação ativa para este teste e condição no período informado.');
    }

    private static function date(string $value):bool { $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);return $date&&$date->format('Y-m-d')===$value; }
    private static function boolean(mixed $value):?string { if($value===null||trim((string)$value)==='')return null;$value=mb_strtolower(trim((string)$value));if(in_array($value,['1','true','sim'],true))return 'Sim';if(in_array($value,['0','false','não','nao'],true))return 'Não';return null; }
    private static function number(mixed $value):?string
    {
        $raw=str_replace(',','.',trim((string)$value));if($raw===''||!preg_match('/^([+-]?)(\d+)(?:\.(\d*))?(?:[eE]([+-]?\d+))?$/',$raw,$match))return null;
        $sign=$match[1]==='-'?'-':'';$integer=$match[2];$fraction=$match[3]??'';$exponent=isset($match[4])?(int)$match[4]:0;$all=$integer.$fraction;$leading=strlen($all)-strlen(ltrim($all,'0'));$digits=substr($all,$leading);if($digits==='')return '0';$point=strlen($integer)+$exponent-$leading;if($point<=0)$normalized='0.'.str_repeat('0',-$point).$digits;elseif($point>=strlen($digits))$normalized=$digits.str_repeat('0',$point-strlen($digits));else $normalized=substr($digits,0,$point).'.'.substr($digits,$point);if(str_contains($normalized,'.'))$normalized=rtrim(rtrim($normalized,'0'),'.');return $normalized==='0'?'0':$sign.$normalized;
    }
}
