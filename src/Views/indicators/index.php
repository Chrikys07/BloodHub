<?php $h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');require dirname(__DIR__).'/layouts/admin_start.php';?>
<section class="indicator-catalog">
<?php foreach($indicators as$i):$target=$i['target'];?>
 <article class="indicator-catalog-card">
  <div class="indicator-card-mark" aria-hidden="true">↗</div><div><span class="indicator-kicker">Indicador institucional</span><h2><?=$h($i['name'])?></h2><p><?=$h($i['objective']?:'Objetivo ainda não configurado.')?></p></div>
  <dl><div><dt>Periodicidade</dt><dd><?=$h($i['periodicity'])?></dd></div><div><dt>Meta vigente</dt><dd><?=$target?$h(\BloodHub\Services\IndicatorValueFormatter::target($i,$target)):'Não configurada'?></dd></div></dl>
  <a class="button" href="/indicators/<?=$h($i['slug'])?>">Abrir indicador</a>
 </article>
<?php endforeach;?>
</section>
<?php require dirname(__DIR__).'/layouts/admin_end.php';?>
