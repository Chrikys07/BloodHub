<?php
declare(strict_types=1);

use BloodHub\Core\Database;
use BloodHub\Services\FactorViiiPoolService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'BloodHub\\';
    if (!str_starts_with($class, $prefix)) return;
    $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) require $file;
});

$pdo = Database::connection();
$eligibleIds = array_fill_keys(array_map('intval', array_column(FactorViiiPoolService::getEligibleSamples(), 'id')), true);
$sql = <<<'SQL'
SELECT s.id, s.donation_number, s.lcqh_code, s.status, s.purpose, s.received_at,
       s.sample_shipment_id AS shipment_id, s.origin_unit_id, u.name AS origin_name,
       s.blood_component_id, bc.code AS component_code, bc.name AS component_name,
       sh.status AS shipment_status,
       GROUP_CONCAT(DISTINCT CONCAT(t.code, ':', st.status) ORDER BY t.code SEPARATOR ', ') AS sample_tests,
       EXISTS(
           SELECT 1 FROM test_blood_components configured
           JOIN tests configured_test ON configured_test.id=configured.test_id
             AND configured_test.code='FACTOR_VIII' AND configured_test.status='active'
           WHERE configured.blood_component_id=s.blood_component_id
       ) AS factor_configured,
       EXISTS(
           SELECT 1 FROM factor_viii_pool_samples member
           JOIN factor_viii_pools pool ON pool.id=member.pool_id
           WHERE member.sample_id=s.id AND pool.status IN ('open','completed_conforming','completed_nonconforming')
       ) AS valid_pool,
       EXISTS(
           SELECT 1 FROM sample_tests done_st
           JOIN tests done_t ON done_t.id=done_st.test_id AND done_t.code='FACTOR_VIII'
           JOIN test_results done_tr ON done_tr.sample_test_id=done_st.id
           WHERE done_st.sample_id=s.id AND done_st.status='completed'
             AND (done_tr.result_value_numeric IS NOT NULL OR NULLIF(TRIM(done_tr.result_value_text),'') IS NOT NULL)
       ) AS completed_test_result,
       fr.analysis_mode, fr.individual_required, fr.individual_result, fr.effective_result, fr.current_pool_id
FROM samples s
JOIN blood_components bc ON bc.id=s.blood_component_id
LEFT JOIN units u ON u.id=s.origin_unit_id
LEFT JOIN sample_shipments sh ON sh.id=s.sample_shipment_id
LEFT JOIN sample_tests st ON st.sample_id=s.id
LEFT JOIN tests t ON t.id=st.test_id
LEFT JOIN factor_viii_sample_results fr ON fr.sample_id=s.id
WHERE bc.code IN ('PFC','PFC24')
GROUP BY s.id
ORDER BY s.id
SQL;

$reason = static function (array $row): string {
    if ($row['purpose'] !== 'quality_control') return 'finalidade diferente de Controle de Qualidade';
    if ($row['received_at'] === null || !in_array($row['status'], ['received','in_analysis','partial_results'], true)) return 'não recebida ou status analítico inválido';
    if ($row['origin_unit_id'] === null) return 'origem não informada';
    if (!(int)$row['factor_configured']) return 'Fator VIII não configurado para o hemocomponente';
    if ((int)$row['valid_pool']) return 'já pertence a pool ativo ou concluído';
    if ($row['effective_result'] !== null || $row['individual_result'] !== null || (int)($row['completed_test_result'] ?? 0)) return 'resultado de Fator VIII concluído';
    if ($row['analysis_mode'] === 'individual' || (int)($row['individual_required'] ?? 0) === 1) return 'definida para análise individual';
    return 'não elegível pela regra central';
};

printf("%-5s | %-16s | %-6s | %-16s | %-16s | %-19s | %-18s | %-8s | %s\n", 'ID', 'DOAÇÃO', 'COMP.', 'STATUS', 'FINALIDADE', 'RECEBIDO EM', 'ORIGEM', 'ELEGÍVEL', 'MOTIVO');
foreach ($pdo->query($sql, PDO::FETCH_ASSOC) as $row) {
    $eligible = isset($eligibleIds[(int)$row['id']]);
    printf("%-5d | %-16s | %-6s | %-16s | %-16s | %-19s | %-18s | %-8s | %s\n",
        $row['id'], $row['donation_number'] ?: '-', $row['component_code'], $row['status'], $row['purpose'],
        $row['received_at'] ?: '-', $row['origin_name'] ?: '-', $eligible ? 'SIM' : 'NÃO', $eligible ? '-' : $reason($row)
    );
    printf("      LCQH=%s; shipment_id=%s (%s); sample_tests=%s; mode=%s; current_pool=%s\n",
        $row['lcqh_code'] ?: '-', $row['shipment_id'] ?: '-', $row['shipment_status'] ?: '-', $row['sample_tests'] ?: '-',
        $row['analysis_mode'] ?: '-', $row['current_pool_id'] ?: '-'
    );
}
