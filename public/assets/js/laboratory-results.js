(() => {
  'use strict';
  const number = value => {
    let raw = String(value ?? '').trim();
    if (!raw) return NaN;
    if (raw.includes(',')) raw = raw.replaceAll('.', '').replace(',', '.');
    return Number(raw);
  };
  const format = (value, decimals = 2) => Number.isFinite(value)
    ? value.toLocaleString('pt-BR', {minimumFractionDigits: decimals, maximumFractionDigits: decimals}) : '—';

  document.querySelectorAll('[data-laboratory-card]').forEach(card => {
    const calculate = () => {
      const gross = number(card.querySelector('[data-gross]')?.value);
      const tare = number(card.dataset.tare);
      const density = number(card.dataset.density);
      const volume = gross > tare && density > 0 ? (gross - tare) / density : NaN;
      const hemoglobin = number(card.querySelector('[data-hb]')?.value);
      const volumeOutput = card.querySelector('[data-volume]');
      if (volumeOutput) volumeOutput.textContent = format(volume, 1);
      const hbUnit = card.querySelector('[data-hb-unit]');
      if (hbUnit) hbUnit.textContent = format(hemoglobin / 100 * volume);

      const plateletMethod = card.querySelector('[name$="[platelet_method]"]')?.value;
      const plateletCount = number(card.querySelector('[data-platelet-count]')?.value);
      const leukocyteMethod = card.querySelector('[name$="[leukocyte_method]"]')?.value;
      const leukocyteCount = number(card.querySelector('[data-leukocyte-count]')?.value);
      let platelets = NaN, leukocytes = NaN;
      if (Number.isFinite(volume) && Number.isFinite(plateletCount)) {
        if (plateletMethod === 'Sysmex') platelets = plateletCount * 5e6 * volume;
        if (plateletMethod === 'Neubauer') platelets = plateletCount * 5 * 10 * 200 * 1000 * volume;
      }
      if (Number.isFinite(volume) && Number.isFinite(leukocyteCount)) {
        if (leukocyteMethod === 'Sysmex') leukocytes = leukocyteCount * 1e6 * volume;
        if (leukocyteMethod === 'Neubauer') leukocytes = (leukocyteCount * 10 * 20 * 1000 * volume) / 4;
        if (leukocyteMethod === 'Nageotte') leukocytes = (leukocyteCount * 10 * 1000 * volume) / 50;
      }
      const plateletOutput = card.querySelector('[data-platelets-unit]');
      const leukocyteOutput = card.querySelector('[data-leukocytes-unit]');
      if (plateletOutput) plateletOutput.textContent = Number.isFinite(platelets) ? platelets.toExponential(2).replace('.', ',') : '—';
      if (leukocyteOutput) leukocyteOutput.textContent = Number.isFinite(leukocytes) ? leukocytes.toExponential(2).replace('.', ',') : '—';
    };
    card.querySelectorAll('input,select').forEach(element => element.addEventListener('input', calculate));
    calculate();
  });
  const resultsForm=document.getElementById('validation-results-form');
  if(resultsForm&&window.BloodHubFreshPlasmaCalculator&&window.BloodHubMeasurementFormatter){
    window.BloodHubFreshPlasmaCalculator.bindRealtime(resultsForm,window.BloodHubMeasurementFormatter.formatScientificPtBr);
  }
  window.BloodHubHemolysisCalculator?.bindRealtime(resultsForm||document);

  const modal = document.querySelector('[data-validation-complete-modal]');
  const list = modal?.querySelector('[data-validation-spec-list]');
  const form = document.getElementById('validation-results-form');
  document.querySelectorAll('[data-open-validation-complete]').forEach(button => button.addEventListener('click', () => {
    const rows = window.validationOutside || [];
    if (list) list.innerHTML = rows.length
      ? rows.map(row => `<h3>${row.sample}</h3>${row.items.map(item => `<p><b>${item.test_name}</b> · Resultado: ${item.result_display} · Referência: ${item.reference_display}</p>`).join('')}`).join('')
      : '<p>Nenhum resultado fora da especificação entre as amostras visíveis.</p>';
    modal.hidden = false;
  }));
  document.querySelector('[data-close-validation-complete]')?.addEventListener('click', () => { modal.hidden = true; });
  document.querySelector('[data-confirm-validation-complete]')?.addEventListener('click', () => {
    const ids = Array.from(document.querySelectorAll('[data-laboratory-card]')).map(card => {
      const field = card.querySelector('input[name^="samples["]');
      return field?.name.match(/samples\[(\d+)\]/)?.[1];
    }).filter(Boolean).join(',');
    [['visible_ids', ids], ['specification_acknowledged', '1']].forEach(([name, value]) => {
      let input = form.querySelector(`input[name="${name}"]`);
      if (!input) { input = document.createElement('input'); input.type = 'hidden'; input.name = name; form.append(input); }
      input.value = value;
    });
    form.action = '/validations/results/complete';
    form.submit();
  });
})();
