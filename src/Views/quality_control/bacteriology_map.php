<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Mapa de Pools — Teste Bacteriológico</title>
<style>
@page{size:A4 landscape;margin:10mm 12mm 14mm}
*{box-sizing:border-box}
html,body{width:100%;max-width:none;margin:0;color:#17212b;font:10px Arial,sans-serif}
.source{width:100%;max-width:none}
.head{display:flex;align-items:center;border-bottom:3px solid #8b1e3f;padding-bottom:7px;margin-bottom:8px}
.head img{width:4.7cm}
.head h1{flex:1;text-align:center;font-size:16px;letter-spacing:.05em;margin:0;padding-right:4.7cm}
.meta{display:grid;grid-template-columns:repeat(6,1fr);gap:5px;margin-bottom:8px}
.meta div{min-width:0;border:1px solid #d4dae3;padding:5px;overflow-wrap:anywhere}
.meta b{display:block;font-size:8px;text-transform:uppercase;color:#667085}
table{width:100%;max-width:none;border-collapse:collapse;table-layout:fixed}
col:nth-child(1){width:9.75%}
col:nth-child(2),col:nth-child(3),col:nth-child(4),col:nth-child(5){width:12.635%}
col:nth-child(6){width:21.66%}
col:nth-child(7){width:18.05%}
th,td{border:1px solid #7c8796;padding:4px;text-align:center;vertical-align:middle;overflow-wrap:anywhere}
thead{display:table-header-group}
th{background:#eef1f5;font-size:8px;line-height:1.2;text-transform:uppercase}
.pool{font-weight:700;color:#8b1e3f}
.unit{font-size:9px}
.unit small{display:block;color:#667085}
tbody tr,tbody td{height:37mm}
.foot{display:flex;justify-content:space-between;gap:12px;border-top:1px solid #ccd3dc;margin-top:7px;padding-top:5px;color:#596577}
.foot .number{margin-left:auto}
.print{position:fixed;right:10px;top:10px}
.pages{display:none}
@media print{
  html,body{width:auto;max-width:none;height:auto;overflow:visible}
  .source,.print{display:none!important}
  .pages{display:block!important}
  .pages,.page,.page .head,.page .meta,.page .foot,.page table,.page th,.page td{box-sizing:border-box}
  .pages{width:100%;max-width:100%;margin:0;padding:0}
  .page{display:flex;flex-direction:column;width:100%;max-width:100%;height:185mm;margin:0;padding:0;overflow:hidden;break-after:page;page-break-after:always}
  .page:last-child{break-after:auto;page-break-after:auto}
  .page .head,.page .meta,.page .foot{width:100%;max-width:100%}
  .page table{width:100%;max-width:100%;table-layout:fixed;border-collapse:collapse}
  .page col:nth-child(1){width:23mm}
  .page col:nth-child(2),.page col:nth-child(3),.page col:nth-child(4),.page col:nth-child(5){width:35mm}
  .page col:nth-child(6){width:60mm}
  .page col:nth-child(7){width:50mm}
  .page th:last-child,.page td:last-child{border-right:1px solid #7c8796}
  .page thead{display:table-header-group}
  .page tbody{display:table-row-group}
  .page tr,.page tbody td{break-inside:avoid;page-break-inside:avoid}
  .page .foot{margin-top:auto;break-inside:avoid;page-break-inside:avoid}
}
</style>
</head>
<body>
<button class="print" onclick="window.print()">Imprimir</button>
<?php
$dates=array_filter(array_column($pools,'created_at'));
$origins=array_unique(array_column($pools,'origin_name'));
$clients=array_filter(array_unique(array_column($pools,'client_name')));
?>
<main class="source">
  <header class="head"><img src="/assets/images/bloodhub-logo-horizontal.png" alt="BloodHub"><h1>MAPA DE POOLS — TESTE BACTERIOLÓGICO</h1></header>
  <section class="meta">
    <div><b>Período</b><?=$dates?date('d/m/Y',strtotime(min($dates))).' a '.date('d/m/Y',strtotime(max($dates))):'—'?></div>
    <div><b>Hemocomponente</b>Concentrado de Plaquetas</div>
    <div><b>Processamento / origem</b><?=htmlspecialchars(implode(', ',$origins)?:'—')?></div>
    <div><b>Cliente</b><?=htmlspecialchars(implode(', ',$clients)?:'—')?></div>
    <div><b>Data de geração</b><?=date('d/m/Y H:i')?></div>
    <div><b>Gerado por</b><?=htmlspecialchars(\BloodHub\Core\Auth::user()['name']??'—')?></div>
  </section>
  <table>
    <colgroup><col><col><col><col><col><col><col></colgroup>
    <thead><tr><th>Pool</th><th>CP 1</th><th>CP 2</th><th>CP 3</th><th>CP 4</th><th>Etiqueta do pool</th><th>Etiqueta do frasco — Teste bacteriológico</th></tr></thead>
    <tbody><?php foreach($pools as$p):?><tr><td class="pool"><?=htmlspecialchars($p['code'])?></td><?php for($i=0;$i<4;$i++):$m=$p['members'][$i]??null;?><td class="unit"><?=$m?htmlspecialchars($m['donation_number']?:$m['sample_code']):''?><?php if($m&&!empty($m['lcqh_code'])):?><small>LCQH <?=htmlspecialchars($m['lcqh_code'])?></small><?php endif;?></td><?php endfor;?><td></td><td></td></tr><?php endforeach;?></tbody>
  </table>
  <footer class="foot"><span>BloodHub — Controle de Qualidade</span><span>Impresso em: <?=date('d/m/Y H:i:s')?></span></footer>
</main>
<div class="pages" aria-hidden="true"></div>
<script>
(()=>{
  const source=document.querySelector('.source');
  const pages=document.querySelector('.pages');
  const rows=[...source.querySelectorAll('tbody tr')];
  const rowsPerPage=3;

  function buildPages(){
    pages.replaceChildren();
    const total=Math.max(1,Math.ceil(rows.length/rowsPerPage));
    for(let index=0;index<total;index++){
      const page=document.createElement('section');
      page.className='page';
      page.append(source.querySelector('.head').cloneNode(true));
      page.append(source.querySelector('.meta').cloneNode(true));

      const table=document.createElement('table');
      table.append(source.querySelector('colgroup').cloneNode(true));
      table.append(source.querySelector('thead').cloneNode(true));
      const body=document.createElement('tbody');
      rows.slice(index*rowsPerPage,(index+1)*rowsPerPage).forEach(row=>body.append(row.cloneNode(true)));
      table.append(body);
      page.append(table);

      const footer=source.querySelector('.foot').cloneNode(true);
      const number=document.createElement('span');
      number.className='number';
      number.textContent=`Página ${index+1} de ${total}`;
      footer.insertBefore(number,footer.lastElementChild);
      page.append(footer);
      pages.append(page);
    }
  }

  buildPages();
  addEventListener('beforeprint',buildPages);
})();
</script>
</body>
</html>
