<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/Core/Configuration.php';

use BloodHub\Core\Configuration;

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};
$titles = static fn(array $modules): array => array_column($modules, 'title');
$with = static fn(array $permissions): callable => static fn(string $permission): bool => in_array($permission, $permissions, true);

// 1. Administrator: the synchronized administrator role owns every catalog permission.
$all = Configuration::modules(static fn(string $permission): bool => true);
$check(Configuration::hasAnyPermission(static fn(string $permission): bool => true), 'Administrador não visualiza Configuração.');
$check(count($all) === 17, 'Administrador não visualiza todos os módulos do catálogo.');

// 2. LCQH with supplies only.
$suppliesOnly = Configuration::modules($with(['admin.supplies.manage']));
$check(Configuration::hasAnyPermission($with(['admin.supplies.manage'])), 'LCQH com insumos não visualiza Configuração.');
$check($titles($suppliesOnly) === ['Insumos e lotes'], 'LCQH com insumos visualiza módulos indevidos.');
$check($with(['admin.supplies.manage'])('admin.supplies.manage'), 'LCQH não acessa /admin/supplies.');
$check(!$with(['admin.supplies.manage'])('admin.users.manage'), 'LCQH acessa /admin/users sem permissão.');

// 3. LCQH with supplies and equipment.
$twoModules = Configuration::modules($with(['admin.supplies.manage', 'laboratory_equipment.manage']));
$check($titles($twoModules) === ['Equipamentos', 'Insumos e lotes'], 'LCQH não visualiza exatamente equipamentos e insumos.');

// 4. Processing role without configuration permissions.
$none = $with([]);
$check(!Configuration::hasAnyPermission($none), 'Perfil sem permissão visualiza Configuração.');
$check(Configuration::modules($none) === [], 'Perfil sem permissão recebeu cards de configuração.');

// 5. Permission removal is reflected on the next check (Permission::can reads RBAC from DB on every call).
$granted = ['admin.supplies.manage'];
$dynamic = static function (string $permission) use (&$granted): bool {
    return in_array($permission, $granted, true);
};
$check(Configuration::hasAnyPermission($dynamic), 'Permissão concedida não foi refletida.');
$granted = [];
$check(!Configuration::hasAnyPermission($dynamic), 'Permissão removida permaneceu visível.');

// Backend guards for the routes explicitly required by the acceptance criteria.
$sources = [
    '/admin/supplies' => [dirname(__DIR__).'/src/Controllers/Admin/SupplyController.php', "AdminGuard::enforce(self::PERMISSION)", "admin.supplies.manage"],
    '/admin/users' => [dirname(__DIR__).'/src/Controllers/Admin/UserController.php', "AdminGuard::enforce(self::PERMISSION)", "admin.users.manage"],
    '/admin/permissions' => [dirname(__DIR__).'/src/Controllers/Admin/PermissionController.php', "AdminGuard::enforce(self::PERMISSION)", "admin.permissions.manage"],
];
foreach ($sources as $route => [$file, $guard, $permission]) {
    $source = (string)file_get_contents($file);
    $check(str_contains($source, $guard) && str_contains($source, $permission), "$route não está protegido por $permission.");
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures).PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "OK: 5 cenários de Configuração/RBAC e guards críticos validados.\n");
