<?php
declare(strict_types=1);

namespace BloodHub\Services;

use BloodHub\Core\Database;
use PDO;

/** Read-only comparison of rounds belonging to one validation. */
final class ValidationDashboardService
{
    private const STABLE_THRESHOLD_PP = 0.5;

    public static function build(int $validationId, int $phaseId = 0, int $testId = 0): array
    {
        $pdo = Database::connection();
        $phases = self::phases($pdo, $validationId);
        $phaseIds = array_map('intval', array_column($phases, 'id'));
        if (!$phaseId && $phases) {
            $phaseId = (int) end($phases)['id'];
        }
        if (!in_array($phaseId, $phaseIds, true)) {
            $phaseId = (int) ($phases[0]['id'] ?? 0);
        }

        $tests = self::tests($pdo, $validationId);
        if ($testId && !in_array($testId, array_map('intval', array_column($tests, 'id')), true)) {
            $testId = 0;
        }
        $aggregates = self::testAggregates($pdo, $validationId, $testId);
        $planned = self::plannedTests($pdo, $validationId);

        $byPhaseTest = [];
        foreach ($aggregates as $row) {
            $row['percent'] = (int) $row['assessable_count'] > 0
                ? round((int) $row['conforming_count'] * 100 / (int) $row['assessable_count'], 1)
                : null;
            $byPhaseTest[(int) $row['phase_id']][(int) $row['test_id']] = $row;
        }

        $previous = null;
        foreach ($phases as &$phase) {
            $phase['global_conformity'] = (int) $phase['assessable_count'] > 0
                ? round((int) $phase['conforming_count'] * 100 / (int) $phase['assessable_count'], 1)
                : null;
            $phase['variation_pp'] = null;
            $phase['trend'] = null;
            if ($phase['global_conformity'] !== null && $previous !== null) {
                $phase['variation_pp'] = round($phase['global_conformity'] - $previous, 1);
                $phase['trend'] = abs($phase['variation_pp']) <= self::STABLE_THRESHOLD_PP
                    ? 'stable'
                    : ($phase['variation_pp'] > 0 ? 'improved' : 'regressed');
            }
            $previous = $phase['global_conformity'];
        }
        unset($phase);

        $comparableCount = count(array_filter($phases, static fn(array $p): bool => $p['global_conformity'] !== null));
        $bestIds = $comparableCount >= 2 ? self::bestPhaseIds($phases) : [];
        foreach ($phases as &$phase) {
            $phase['best_performance'] = count($bestIds) === 1 && (int) $phase['id'] === $bestIds[0];
        }
        unset($phase);

        $comparison = [];
        foreach ($tests as $test) {
            if ($testId && (int) $test['id'] !== $testId) {
                continue;
            }
            $cells = [];
            foreach ($phases as $phase) {
                $pid = (int) $phase['id'];
                $tid = (int) $test['id'];
                $cells[$pid] = [
                    'planned' => isset($planned[$pid][$tid]),
                    'result' => $byPhaseTest[$pid][$tid] ?? null,
                ];
            }
            $comparison[] = $test + ['phases' => $cells];
        }

        $samples = array_sum(array_map(static fn(array $p): int => (int) $p['sample_count'], $phases));
        $completed = array_sum(array_map(static fn(array $p): int => (int) $p['completed_results'], $phases));
        $expected = array_sum(array_map(static fn(array $p): int => (int) $p['expected_results'], $phases));
        $assessable = array_sum(array_map(static fn(array $p): int => (int) $p['assessable_count'], $phases));
        $conforming = array_sum(array_map(static fn(array $p): int => (int) $p['conforming_count'], $phases));
        return [
            'phases' => $phases,
            'phaseId' => $phaseId,
            'tests' => $tests,
            'testId' => $testId,
            'comparison' => $comparison,
            'comparableCount' => $comparableCount,
            'stableThreshold' => self::STABLE_THRESHOLD_PP,
            'bestTie' => count($bestIds) > 1,
            'summary' => [
                'phases' => count($phases),
                'completed_phases' => count(array_filter($phases, static fn(array $p): bool => $p['status'] === 'completed')),
                'samples' => $samples,
                'planned_tests' => array_sum(array_map(static fn(array $p): int => (int) $p['planned_tests'], $phases)),
                'completed_results' => $completed,
                'expected_results' => $expected,
                'progress' => $expected ? round($completed * 100 / $expected, 1) : 0,
                'global_conformity' => $assessable ? round($conforming * 100 / $assessable, 1) : null,
            ],
        ];
    }

    private static function phases(PDO $pdo, int $validationId): array
    {
        $sql = "SELECT vp.*,
                    (SELECT COUNT(*) FROM samples s WHERE s.validation_id=:samples_validation AND s.validation_phase_id=vp.id) sample_count,
                    (SELECT COUNT(*) FROM validation_tests vt WHERE vt.validation_id=:tests_validation AND vt.phase_id=vp.id AND vt.status='active') planned_tests,
                    (SELECT COUNT(*) FROM samples s JOIN validation_tests vt ON vt.validation_id=s.validation_id AND vt.phase_id=s.validation_phase_id AND vt.blood_component_id=s.blood_component_id AND vt.status='active' JOIN tests pt ON pt.id=vt.test_id AND pt.is_final_result=1 WHERE s.validation_id=:expected_validation AND s.validation_phase_id=vp.id AND s.status<>'cancelled') expected_results,
                    (SELECT COUNT(*) FROM samples s JOIN sample_tests st ON st.sample_id=s.id AND st.status='completed' JOIN validation_tests cvt ON cvt.validation_id=s.validation_id AND cvt.phase_id=s.validation_phase_id AND cvt.blood_component_id=s.blood_component_id AND cvt.test_id=st.test_id AND cvt.status='active' JOIN tests ct ON ct.id=st.test_id AND ct.is_final_result=1 JOIN test_results tr ON tr.id=(SELECT MAX(tr2.id) FROM test_results tr2 WHERE tr2.sample_test_id=st.id) WHERE s.validation_id=:completed_validation AND s.validation_phase_id=vp.id AND s.status<>'cancelled' AND (tr.result_value_numeric IS NOT NULL OR NULLIF(TRIM(tr.result_value_text),'') IS NOT NULL)) completed_results,
                    (SELECT COUNT(*) FROM samples s JOIN sample_tests st ON st.sample_id=s.id AND st.status='completed' JOIN validation_tests avt ON avt.validation_id=s.validation_id AND avt.phase_id=s.validation_phase_id AND avt.blood_component_id=s.blood_component_id AND avt.test_id=st.test_id AND avt.status='active' JOIN tests at ON at.id=st.test_id AND at.is_final_result=1 JOIN test_results tr ON tr.id=(SELECT MAX(tr2.id) FROM test_results tr2 WHERE tr2.sample_test_id=st.id) JOIN test_result_spec_evaluations e ON e.test_result_id=tr.id AND e.conformity_status IN('CONFORMING','NONCONFORMING') WHERE s.validation_id=:assessable_validation AND s.validation_phase_id=vp.id AND s.status<>'cancelled') assessable_count,
                    (SELECT COUNT(*) FROM samples s JOIN sample_tests st ON st.sample_id=s.id AND st.status='completed' JOIN validation_tests fvt ON fvt.validation_id=s.validation_id AND fvt.phase_id=s.validation_phase_id AND fvt.blood_component_id=s.blood_component_id AND fvt.test_id=st.test_id AND fvt.status='active' JOIN tests ft ON ft.id=st.test_id AND ft.is_final_result=1 JOIN test_results tr ON tr.id=(SELECT MAX(tr2.id) FROM test_results tr2 WHERE tr2.sample_test_id=st.id) JOIN test_result_spec_evaluations e ON e.test_result_id=tr.id AND e.conformity_status='CONFORMING' WHERE s.validation_id=:conforming_validation AND s.validation_phase_id=vp.id AND s.status<>'cancelled') conforming_count
                FROM validation_phases vp
                WHERE vp.validation_id=:validation AND vp.status<>'inactive'
                ORDER BY vp.sequence_order, vp.id";
        $query = $pdo->prepare($sql);
        $query->execute([
            'samples_validation' => $validationId,
            'tests_validation' => $validationId,
            'expected_validation' => $validationId,
            'completed_validation' => $validationId,
            'assessable_validation' => $validationId,
            'conforming_validation' => $validationId,
            'validation' => $validationId,
        ]);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function tests(PDO $pdo, int $validationId): array
    {
        $query = $pdo->prepare("SELECT DISTINCT t.id,t.code,t.name,t.unit,t.result_type FROM validation_tests vt JOIN tests t ON t.id=vt.test_id AND t.is_final_result=1 WHERE vt.validation_id=:validation AND vt.status='active' ORDER BY t.name,t.id");
        $query->execute(['validation' => $validationId]);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function plannedTests(PDO $pdo, int $validationId): array
    {
        $query = $pdo->prepare("SELECT DISTINCT vt.phase_id,vt.test_id FROM validation_tests vt JOIN tests t ON t.id=vt.test_id AND t.is_final_result=1 WHERE vt.validation_id=:validation AND vt.status='active'");
        $query->execute(['validation' => $validationId]);
        $out = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int) $row['phase_id']][(int) $row['test_id']] = true;
        }
        return $out;
    }

    private static function testAggregates(PDO $pdo, int $validationId, int $testId): array
    {
        $filter = $testId ? ' AND t.id=:test' : '';
        $sql = "SELECT s.validation_phase_id phase_id,t.id test_id,
                    COUNT(*) result_count,
                    SUM(e.conformity_status IN('CONFORMING','NONCONFORMING')) assessable_count,
                    SUM(e.conformity_status='CONFORMING') conforming_count,
                    SUM(e.conformity_status='NONCONFORMING') nonconforming_count,
                    AVG(CASE WHEN t.result_type='numeric' AND e.conformity_status IN('CONFORMING','NONCONFORMING') THEN tr.result_value_numeric END) average_value
                FROM samples s
                JOIN sample_tests st ON st.sample_id=s.id AND st.status='completed'
                JOIN validation_tests vt ON vt.validation_id=s.validation_id AND vt.phase_id=s.validation_phase_id AND vt.blood_component_id=s.blood_component_id AND vt.test_id=st.test_id AND vt.status='active'
                JOIN tests t ON t.id=st.test_id AND t.is_final_result=1
                JOIN test_results tr ON tr.id=(SELECT MAX(tr2.id) FROM test_results tr2 WHERE tr2.sample_test_id=st.id)
                LEFT JOIN test_result_spec_evaluations e ON e.test_result_id=tr.id
                WHERE s.validation_id=:validation AND s.status<>'cancelled'{$filter}
                  AND (tr.result_value_numeric IS NOT NULL OR NULLIF(TRIM(tr.result_value_text),'') IS NOT NULL)
                GROUP BY s.validation_phase_id,t.id
                ORDER BY t.id,s.validation_phase_id";
        $query = $pdo->prepare($sql);
        $params = ['validation' => $validationId];
        if ($testId) {
            $params['test'] = $testId;
        }
        $query->execute($params);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function bestPhaseIds(array $phases): array
    {
        $values = array_filter(array_column($phases, 'global_conformity', 'id'), static fn($value): bool => $value !== null);
        if (!$values) {
            return [];
        }
        $best = max($values);
        return array_map('intval', array_keys(array_filter($values, static fn($value): bool => $value === $best)));
    }
}
