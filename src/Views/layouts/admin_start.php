<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — BloodHub</title>
<link rel="stylesheet" href="/assets/css/app.css">
<link rel="stylesheet" href="/assets/css/tests.css">
<link rel="stylesheet" href="/assets/css/bacteriology.css">
<link rel="stylesheet" href="/assets/css/validations.css">
<link rel="stylesheet" href="/assets/css/validation-plan.css">
<link rel="stylesheet" href="/assets/css/validation-shipments.css">
<link rel="stylesheet" href="/assets/css/admin-corrections.css">
<link rel="stylesheet" href="/assets/css/sampling-schedule.css">
<link rel="stylesheet" href="/assets/css/global-dashboard.css">
<link rel="stylesheet" href="/assets/css/quality-control-report.css">
<link rel="stylesheet" href="/assets/css/indicators.css">
<link rel="stylesheet" href="/assets/css/monthly-closures.css">
<link rel="stylesheet" href="/assets/css/billing.css">
<link rel="stylesheet" href="/assets/css/billing-refinement.css">
<link rel="stylesheet" href="/assets/css/laboratory-reports.css">
<link rel="stylesheet" href="/assets/css/laboratory-reports-refinement.css">
<link rel="stylesheet" href="/assets/css/transfusion-reaction-consultation.css">
</head>
<body>
<div class="app-shell">
<?php require __DIR__.'/sidebar.php'; ?>
<div class="workspace">
<header class="topbar"><?php require __DIR__.'/topbar.php'; ?></header>
<main class="main-content">
<div class="page-heading"><div><span class="page-eyebrow">BloodHub</span><h1><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1><?php if (!empty($pageSubtitle)): ?><p><?= htmlspecialchars($pageSubtitle, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?></div></div>
<?php foreach (($flash ?? []) as $type => $message): ?><div class="alert alert-<?= $type === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?>
