<?php
declare(strict_types=1);
namespace BloodHub\Controllers\Admin;

use BloodHub\Core\{AdminGuard,Auth,Csrf,Database,Flash};
use BloodHub\Services\IndicatorService;
use DateTimeImmutable;
use DomainException;
use PDO;

final class IndicatorConfigController
{
    public static function index(): void
    {
        AdminGuard::enforce('indicators.config.manage');
        $indicators = IndicatorService::all(true);
        $selected = IndicatorService::getIndicator((int)($_GET['id'] ?? ($indicators[0]['id'] ?? 0)));
        $pdo = Database::connection();
        $targets = [];
        if ($selected) {
            $q = $pdo->prepare('SELECT it.*, u.name created_by_name, du.name deleted_by_name FROM indicator_targets it LEFT JOIN users u ON u.id=it.created_by LEFT JOIN users du ON du.id=it.deleted_by WHERE it.indicator_id=:id ORDER BY it.effective_from DESC,it.id DESC');
            $q->execute(['id' => $selected['id']]);
            $targets = $q->fetchAll(PDO::FETCH_ASSOC);
        }
        $users = $pdo->query("SELECT id,name,email FROM users WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $tests = $pdo->query("SELECT id,code,name,unit FROM tests WHERE status='active' AND is_final_result=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $pageTitle = 'Configuração de Indicadores';
        $pageSubtitle = 'Metadados, metas versionadas, teste de origem e responsáveis.';
        $flash = Flash::pull();
        $csrf = Csrf::token();
        $userAuth = Auth::user();
        require dirname(__DIR__, 2).'/Views/admin/indicators/index.php';
    }

    public static function saveMetadata(): void
    {
        self::guard();
        $id = (int)($_POST['indicator_id'] ?? 0);
        $pdo = Database::connection();
        $q = $pdo->prepare('SELECT * FROM indicators WHERE id=:id');
        $q->execute(['id' => $id]);
        $old = $q->fetch(PDO::FETCH_ASSOC);
        if (!$old) { Flash::set('error', 'Indicador não encontrado.'); self::back($id); }
        try {
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') throw new DomainException('Informe o nome do indicador.');
            $type = in_array($_POST['value_type'] ?? '', ['percentage','scientific','number'], true) ? $_POST['value_type'] : 'number';
            $pdo->prepare('UPDATE indicators SET name=:name,objective=:objective,sector=:sector,regional=:regional,category=:category,subcategory=:subcategory,periodicity=:periodicity,value_type=:type,value_unit=:unit,configured_test_id=:test,active=:active WHERE id=:id')->execute([
                'name'=>$name,'objective'=>self::nullable('objective'),'sector'=>self::nullable('sector'),'regional'=>self::nullable('regional'),'category'=>self::nullable('category'),'subcategory'=>self::nullable('subcategory'),'periodicity'=>self::nullable('periodicity') ?: 'Mensal','type'=>$type,'unit'=>self::nullable('value_unit'),'test'=>(int)($_POST['configured_test_id'] ?? 0) ?: null,'active'=>isset($_POST['active']) ? 1 : 0,'id'=>$id,
            ]);
            Auth::registerAudit('INDICATOR_CONFIGURATION_UPDATED', 'indicators', $id, $old, $_POST);
            Flash::set('success', 'Indicador atualizado.');
        } catch (\Throwable $e) { Flash::set('error', $e->getMessage()); }
        self::back($id);
    }

    public static function saveTarget(): void
    {
        self::adminGuard();
        $id = (int)($_POST['indicator_id'] ?? 0);
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            [$op, $raw, $from] = self::targetInput();
            self::assertIndicator($id);
            $existing = $pdo->prepare('SELECT * FROM indicator_targets WHERE indicator_id=:id AND effective_from=:effective FOR UPDATE');
            $existing->execute(['id'=>$id,'effective'=>$from]);
            $old = $existing->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($old && $old['deleted_at'] !== null) throw new DomainException('Existe uma meta excluída nesta data. Edite a vigência ou restaure-a por auditoria.');
            $pdo->prepare('INSERT INTO indicator_targets(indicator_id,target_operator,target_value,effective_from,active,created_by) VALUES(:id,:op,:value,:effective,1,:user) ON DUPLICATE KEY UPDATE target_operator=VALUES(target_operator),target_value=VALUES(target_value),active=1,created_by=VALUES(created_by)')->execute(['id'=>$id,'op'=>$op,'value'=>$raw,'effective'=>$from,'user'=>Auth::user()['id'] ?? null]);
            $targetId = (int)$pdo->lastInsertId();
            if (!$targetId) { $targetId = (int)$old['id']; }
            self::recomposeTargets($pdo, $id);
            Auth::registerAudit('INDICATOR_TARGET_CHANGED', 'indicator_targets', $targetId, $old, ['indicator_id'=>$id,'target_operator'=>$op,'target_value'=>(float)$raw,'effective_from'=>$from]);
            $pdo->commit();
            Flash::set('success', 'Nova versão de meta salva sem apagar o histórico.');
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); Flash::set('error', $e->getMessage()); }
        self::back($id);
    }

    public static function addResponsible(): void
    {
        self::adminGuard();
        $indicatorId = (int)($_POST['indicator_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            self::assertIndicator($indicatorId);
            $user = $pdo->prepare("SELECT id,name,email FROM users WHERE id=:id AND status='active' FOR UPDATE");
            $user->execute(['id'=>$userId]);
            $userRow = $user->fetch(PDO::FETCH_ASSOC);
            if (!$userRow) throw new DomainException('Selecione um usuário ativo.');
            $current = $pdo->prepare('SELECT active FROM indicator_responsibles WHERE indicator_id=:indicator AND user_id=:user FOR UPDATE');
            $current->execute(['indicator'=>$indicatorId,'user'=>$userId]);
            if ((int)$current->fetchColumn() === 1) throw new DomainException('Este usuário já é responsável pelo indicador.');
            $pdo->prepare('INSERT INTO indicator_responsibles(indicator_id,user_id,active) VALUES(:indicator,:user,1) ON DUPLICATE KEY UPDATE active=1')->execute(['indicator'=>$indicatorId,'user'=>$userId]);
            Auth::registerAudit('INDICATOR_RESPONSIBLE_ADDED', 'indicator_responsibles', $indicatorId, null, ['indicator_id'=>$indicatorId,'user_id'=>$userId,'responsible'=>$userRow]);
            $pdo->commit();
            Flash::set('success', 'Responsável adicionado com sucesso.');
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); Flash::set('error', $e->getMessage()); }
        self::back($indicatorId);
    }

    public static function removeResponsible(): void
    {
        self::adminGuard();
        $indicatorId = (int)($_POST['indicator_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $q = $pdo->prepare('SELECT ir.*,u.name,u.email FROM indicator_responsibles ir JOIN users u ON u.id=ir.user_id WHERE ir.indicator_id=:indicator AND ir.user_id=:user AND ir.active=1 FOR UPDATE');
            $q->execute(['indicator'=>$indicatorId,'user'=>$userId]);
            $old = $q->fetch(PDO::FETCH_ASSOC);
            if (!$old) throw new DomainException('Responsável ativo não encontrado.');
            $pdo->prepare('UPDATE indicator_responsibles SET active=0 WHERE indicator_id=:indicator AND user_id=:user')->execute(['indicator'=>$indicatorId,'user'=>$userId]);
            Auth::registerAudit('INDICATOR_RESPONSIBLE_REMOVED', 'indicator_responsibles', $indicatorId, $old, ['indicator_id'=>$indicatorId,'user_id'=>$userId,'active'=>0]);
            $pdo->commit();
            Flash::set('success', 'Responsável removido com sucesso.');
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); Flash::set('error', $e->getMessage()); }
        self::back($indicatorId);
    }

    public static function updateTarget(): void
    {
        self::adminGuard();
        $targetId = (int)($_POST['target_id'] ?? 0);
        $indicatorId = (int)($_POST['indicator_id'] ?? 0);
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            [$op, $raw, $from] = self::targetInput();
            $q = $pdo->prepare('SELECT * FROM indicator_targets WHERE id=:target AND indicator_id=:indicator AND deleted_at IS NULL FOR UPDATE');
            $q->execute(['target'=>$targetId,'indicator'=>$indicatorId]);
            $old = $q->fetch(PDO::FETCH_ASSOC);
            if (!$old) throw new DomainException('Meta ativa não encontrada.');
            $duplicate = $pdo->prepare('SELECT 1 FROM indicator_targets WHERE indicator_id=:indicator AND effective_from=:effective AND id<>:target');
            $duplicate->execute(['indicator'=>$indicatorId,'effective'=>$from,'target'=>$targetId]);
            if ($duplicate->fetchColumn()) throw new DomainException('Já existe uma versão de meta nesta data.');
            $pdo->prepare('UPDATE indicator_targets SET target_operator=:op,target_value=:value,effective_from=:effective WHERE id=:target')->execute(['op'=>$op,'value'=>$raw,'effective'=>$from,'target'=>$targetId]);
            self::recomposeTargets($pdo, $indicatorId);
            Auth::registerAudit('INDICATOR_TARGET_UPDATED', 'indicator_targets', $targetId, ['old_operator'=>$old['target_operator'],'old_value'=>$old['target_value'],'old_effective_from'=>$old['effective_from']], ['new_operator'=>$op,'new_value'=>(float)$raw,'new_effective_from'=>$from,'indicator_id'=>$indicatorId]);
            $pdo->commit();
            Flash::set('success', 'Meta atualizada com sucesso.');
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); Flash::set('error', $e->getMessage()); }
        self::back($indicatorId);
    }

    public static function deleteTarget(): void
    {
        self::adminGuard();
        $targetId = (int)($_POST['target_id'] ?? 0);
        $indicatorId = (int)($_POST['indicator_id'] ?? 0);
        $reason = trim((string)($_POST['deletion_reason'] ?? ''));
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            if ($reason === '') throw new DomainException('Informe o motivo da exclusão.');
            $q = $pdo->prepare('SELECT * FROM indicator_targets WHERE id=:target AND indicator_id=:indicator AND deleted_at IS NULL FOR UPDATE');
            $q->execute(['target'=>$targetId,'indicator'=>$indicatorId]);
            $old = $q->fetch(PDO::FETCH_ASSOC);
            if (!$old) throw new DomainException('Meta ativa não encontrada.');
            $pdo->prepare('UPDATE indicator_targets SET active=0,deleted_at=NOW(),deleted_by=:user,deletion_reason=:reason,effective_to=NULL WHERE id=:target')->execute(['user'=>Auth::user()['id'] ?? null,'reason'=>$reason,'target'=>$targetId]);
            self::recomposeTargets($pdo, $indicatorId);
            Auth::registerAudit('INDICATOR_TARGET_DELETED', 'indicator_targets', $targetId, $old, ['indicator_id'=>$indicatorId,'deleted_by'=>Auth::user()['id'] ?? null,'deletion_reason'=>$reason]);
            $pdo->commit();
            Flash::set('success', 'Meta excluída administrativamente.');
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); Flash::set('error', $e->getMessage()); }
        self::back($indicatorId);
    }

    private static function recomposeTargets(PDO $pdo, int $indicatorId): void
    {
        $q = $pdo->prepare('SELECT id,effective_from FROM indicator_targets WHERE indicator_id=:indicator AND deleted_at IS NULL AND active=1 ORDER BY effective_from,id FOR UPDATE');
        $q->execute(['indicator'=>$indicatorId]);
        $targets = $q->fetchAll(PDO::FETCH_ASSOC);
        $update = $pdo->prepare('UPDATE indicator_targets SET effective_to=:effective_to WHERE id=:id');
        foreach ($targets as $index => $target) {
            $next = $targets[$index + 1]['effective_from'] ?? null;
            $to = $next ? (new DateTimeImmutable($next))->modify('-1 day')->format('Y-m-d') : null;
            $update->execute(['effective_to'=>$to,'id'=>$target['id']]);
        }
    }

    private static function targetInput(): array
    {
        $op = in_array($_POST['target_operator'] ?? '', ['GT','GTE','LT','LTE','EQ'], true) ? $_POST['target_operator'] : null;
        $raw = str_replace(',', '.', trim((string)($_POST['target_value'] ?? '')));
        $from = (string)($_POST['effective_from'] ?? '');
        if (!$op || !is_numeric($raw) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !checkdate((int)substr($from,5,2),(int)substr($from,8,2),(int)substr($from,0,4))) throw new DomainException('Informe operador, valor e vigência válidos.');
        return [$op, $raw, $from];
    }

    private static function assertIndicator(int $id): void
    {
        $q = Database::connection()->prepare('SELECT 1 FROM indicators WHERE id=:id');
        $q->execute(['id'=>$id]);
        if (!$q->fetchColumn()) throw new DomainException('Indicador não encontrado.');
    }

    private static function guard(): void
    {
        AdminGuard::enforce('indicators.config.manage');
        self::csrf();
    }

    private static function adminGuard(): void
    {
        AdminGuard::enforce('indicators.config.manage');
        if (!Auth::isAdministrator()) { http_response_code(403); echo 'Acesso negado.'; exit; }
        self::csrf();
    }

    private static function csrf(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) { Flash::set('error', 'Sessão expirada.'); self::back((int)($_POST['indicator_id'] ?? 0)); }
    }

    private static function nullable(string $key): ?string { $v=trim((string)($_POST[$key] ?? '')); return $v === '' ? null : $v; }
    private static function back(int $id): never { header('Location: /admin/indicators?'.http_build_query(['id'=>$id])); exit; }
}
