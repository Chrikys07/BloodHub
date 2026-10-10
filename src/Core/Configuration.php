<?php
declare(strict_types=1);

namespace BloodHub\Core;

final class Configuration
{
    /** Central catalog: visual order and matching backend permission. */
    private const MODULES = [
        ['title'=>'Equipamentos','description'=>'Cadastre equipamentos laboratoriais e configure vínculos vigentes com testes.','url'=>'/admin/equipment','icon'=>'tests','permission'=>'laboratory_equipment.manage'],
        ['title'=>'Laudos','description'=>'Configure a elegibilidade institucional para emissão de laudos.','url'=>'/admin/reports','icon'=>'tests','permission'=>'reports.release.manage'],
        ['title'=>'Faturamento','description'=>'Configure centros de custo, serviços institucionais e associações de resultados finais.','url'=>'/admin/billing','icon'=>'tests','permission'=>'billing.config.manage'],
        ['title'=>'Indicadores','description'=>'Configure metadados, metas vigentes e responsáveis pelos indicadores institucionais.','url'=>'/admin/indicators','icon'=>'tests','permission'=>'indicators.config.manage'],
        ['title'=>'Metas de Conformidade','description'=>'Configure metas agregadas do Dashboard por hemocomponente e teste, com vigência histórica.','url'=>'/admin/dashboard-targets','icon'=>'tests','permission'=>'indicators.config.manage'],
        ['title'=>'Regras do Cronograma','description'=>'Configure dias de envio, amostragem e fontes de produção com vigência histórica.','url'=>'/admin/sampling-schedule','icon'=>'tests','permission'=>'sampling_schedule.admin'],
        ['title'=>'Correções administrativas','description'=>'Localize amostras, retifique dados e consulte a rastreabilidade completa.','url'=>'/admin/corrections','icon'=>'corrections','permission'=>'admin_corrections.manage'],
        ['title'=>'Usuários','description'=>'Gerencie contas, vínculos institucionais, status e perfis de acesso.','url'=>'/admin/users','icon'=>'users','permission'=>'admin.users.manage'],
        ['title'=>'Perfis','description'=>'Organize os papéis e responsabilidades disponíveis no sistema.','url'=>'/admin/roles','icon'=>'roles','permission'=>'admin.roles.manage'],
        ['title'=>'Permissões','description'=>'Defina com precisão o acesso de cada perfil aos módulos.','url'=>'/admin/permissions','icon'=>'permissions','permission'=>'admin.permissions.manage'],
        ['title'=>'Clientes','description'=>'Mantenha os clientes internos e externos centralizados.','url'=>'/admin/clients','icon'=>'clients','permission'=>'admin.clients.manage'],
        ['title'=>'Unidades','description'=>'Configure unidades, tipos operacionais e vínculos com clientes.','url'=>'/admin/units','icon'=>'units','permission'=>'admin.units.manage'],
        ['title'=>'Hemocomponentes','description'=>'Administre o catálogo utilizado nos fluxos de qualidade.','url'=>'/admin/blood-components','icon'=>'blood','permission'=>'admin.blood_components.manage'],
        ['title'=>'Marcas de Bolsa','description'=>'Padronize as marcas de bolsas utilizadas nas remessas.','url'=>'/admin/bag-brands','icon'=>'bag','permission'=>'admin.bag_brands.manage'],
        ['title'=>'Testes','description'=>'Configure ensaios, resultados e insumos necessários.','url'=>'/admin/tests','icon'=>'tests','permission'=>'admin.tests.manage'],
        ['title'=>'Insumos e lotes','description'=>'Controle cadastros, lotes, quantidades e datas de validade.','url'=>'/admin/supplies','icon'=>'supplies','permission'=>'admin.supplies.manage'],
        ['title'=>'Preservantes','description'=>'Configure códigos e soluções preservantes das referências de bolsa.','url'=>'/admin/preservatives','icon'=>'tests','permission'=>'admin.preservatives.manage'],
    ];

    public static function modules(?callable $can = null): array
    {
        $can ??= [Permission::class, 'can'];
        return array_values(array_filter(self::MODULES, static fn(array $module): bool => $can($module['permission'])));
    }

    public static function hasAnyPermission(?callable $can = null): bool
    {
        return self::modules($can) !== [];
    }
}
