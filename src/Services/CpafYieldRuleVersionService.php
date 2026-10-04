<?php
declare(strict_types=1);
namespace BloodHub\Services;

use BloodHub\Core\{Auth,Database};
use DateTimeImmutable;
use DomainException;
use PDO;

final class CpafYieldRuleVersionService
{
    public static function save(array $input):array
    {
        $component=(int)($input['blood_component_id']??0);
        $id=(int)($input['rule_id']??0);
        $simple=self::number($input['simple_min_platelets']??null);
        $double=self::number($input['double_min_platelets']??null);
        $from=trim((string)($input['effective_from']??''))?:null;
        $to=trim((string)($input['effective_to']??''))?:null;
        $active=(int)($input['active']??1);
        if(!$component||$simple===null||$double===null||(float)$simple<=0||(float)$double<=(float)$simple)throw new DomainException('Informe limites positivos; o limite de dupla deve ser maior que o de simples.');
        if(($from&&!self::date($from))||($to&&!self::date($to))||($from&&$to&&$from>$to))throw new DomainException('Informe uma vigência válida.');
        if(!in_array($active,[0,1],true))throw new DomainException('Status inválido.');

        $pdo=Database::connection();
        $pdo->beginTransaction();
        try{
            // A linha do hemocomponente é o mutex da classificação CPAF. Assim, numeração
            // e troca da versão ativa ficam serializadas mesmo entre administradores.
            $q=$pdo->prepare('SELECT code FROM blood_components WHERE id=:id FOR UPDATE');
            $q->execute(['id'=>$component]);
            if($q->fetchColumn()!=='CPAF')throw new DomainException('A classificação por rendimento é exclusiva do CPAF.');
            $user=Auth::user()['id']??null;
            $data=['component'=>$component,'simple'=>$simple,'double'=>$double,'from'=>$from,'to'=>$to,'source'=>trim((string)($input['source_name']??''))?:null,'reference'=>trim((string)($input['source_reference']??''))?:null,'notes'=>trim((string)($input['notes']??''))?:null,'active'=>$active,'user'=>$user];

            if($id){
                $q=$pdo->prepare('SELECT * FROM cpaf_yield_classification_rules WHERE id=:id AND blood_component_id=:component FOR UPDATE');
                $q->execute(['id'=>$id,'component'=>$component]);
                $old=$q->fetch(PDO::FETCH_ASSOC);
                if(!$old)throw new DomainException('Versão não encontrada.');
                if(self::isUsed($pdo,$id))throw new DomainException('Esta versão já foi utilizada. Crie uma nova versão para alterar os critérios.');
                if($active)self::deactivateOthers($pdo,$component,$id,$user,'CPAF_YIELD_RULE_ACTIVE_REPLACED');
                $pdo->prepare('UPDATE cpaf_yield_classification_rules SET simple_min_platelets=:simple,double_min_platelets=:double,effective_from=:from,effective_to=:to,source_name=:source,source_reference=:reference,notes=:notes,active=:active,updated_by=:user WHERE id=:id AND blood_component_id=:component')->execute($data+['id'=>$id]);
                $after=$data+['id'=>$id,'version_number'=>(int)$old['version_number']];
                Auth::registerAudit('CPAF_YIELD_RULE_UPDATED','cpaf_yield_classification_rules',$id,$old,$after);
                $new=$id;$message='Versão v'.(int)$old['version_number'].' atualizada.';
            }else{
                $q=$pdo->prepare('SELECT id,version_number FROM cpaf_yield_classification_rules WHERE blood_component_id=:component ORDER BY version_number DESC,id DESC LIMIT 1');$q->execute(['component'=>$component]);$latest=$q->fetch(PDO::FETCH_ASSOC);
                $version=(int)($latest['version_number']??0)+1;
                if($active)self::deactivateOthers($pdo,$component,0,$user,'CPAF_YIELD_RULE_ACTIVE_REPLACED');
                self::insert($pdo,$data,$version,$latest?(int)$latest['id']:null);
                $new=(int)$pdo->lastInsertId();
                Auth::registerAudit('CPAF_YIELD_RULE_CREATED','cpaf_yield_classification_rules',$new,null,$data+['version_number'=>$version]);
                $message='Versão v'.$version.' criada.';
            }
            $pdo->commit();
            return ['id'=>$new,'message'=>$message];
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function setActive(int $id,int $component,bool $active):array
    {
        if(!$id||!$component)throw new DomainException('Versão inválida.');
        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT code FROM blood_components WHERE id=:id FOR UPDATE');$q->execute(['id'=>$component]);
            if($q->fetchColumn()!=='CPAF')throw new DomainException('A classificação por rendimento é exclusiva do CPAF.');
            $q=$pdo->prepare('SELECT * FROM cpaf_yield_classification_rules WHERE id=:id AND blood_component_id=:component FOR UPDATE');$q->execute(['id'=>$id,'component'=>$component]);$old=$q->fetch(PDO::FETCH_ASSOC);
            if(!$old)throw new DomainException('Versão não encontrada.');
            $user=Auth::user()['id']??null;
            if($active)self::deactivateOthers($pdo,$component,$id,$user,'CPAF_YIELD_RULE_ACTIVE_REPLACED');
            $pdo->prepare('UPDATE cpaf_yield_classification_rules SET active=:active,updated_by=:user WHERE id=:id')->execute(['active'=>$active?1:0,'user'=>$user,'id'=>$id]);
            Auth::registerAudit($active?'CPAF_YIELD_RULE_ACTIVATED':'CPAF_YIELD_RULE_DEACTIVATED','cpaf_yield_classification_rules',$id,$old,['active'=>$active?1:0,'updated_by'=>$user]);
            $pdo->commit();
            return ['message'=>'Versão v'.(int)$old['version_number'].($active?' ativada.':' inativada.')];
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    private static function isUsed(PDO $pdo,int $id):bool{$q=$pdo->prepare('SELECT 1 FROM cpaf_yield_classifications WHERE rule_id=:id LIMIT 1');$q->execute(['id'=>$id]);return (bool)$q->fetchColumn();}
    private static function deactivateOthers(PDO $pdo,int $component,int $except,?int $user,string $auditAction):void
    {
        $q=$pdo->prepare('SELECT * FROM cpaf_yield_classification_rules WHERE blood_component_id=:component AND active=1 AND id<>:except FOR UPDATE');$q->execute(['component'=>$component,'except'=>$except]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
        $update=$pdo->prepare('UPDATE cpaf_yield_classification_rules SET active=0,updated_by=:user WHERE id=:id');
        foreach($rows as $row){$update->execute(['user'=>$user,'id'=>$row['id']]);Auth::registerAudit($auditAction,'cpaf_yield_classification_rules',(int)$row['id'],$row,['active'=>0,'replaced_by_rule_id'=>$except?:null,'updated_by'=>$user]);}
    }
    private static function insert(PDO $pdo,array $d,int $version,?int $supersedes):void{$pdo->prepare("INSERT INTO cpaf_yield_classification_rules(supersedes_id,version_number,blood_component_id,simple_min_platelets,double_min_platelets,effective_from,effective_to,source_name,source_reference,notes,active,created_by,updated_by) VALUES(:supersedes,:version,:component,:simple,:double,:from,:to,:source,:reference,:notes,:active,:user,:user)")->execute($d+['version'=>$version,'supersedes'=>$supersedes]);}
    private static function number(mixed $v):?string{$v=str_replace(',','.',trim((string)$v));return $v!==''&&is_numeric($v)?rtrim(rtrim(number_format((float)$v,10,'.',''),'0'),'.'):null;}
    private static function date(string $v):bool{$d=DateTimeImmutable::createFromFormat('!Y-m-d',$v);return $d&&$d->format('Y-m-d')===$v;}
}
