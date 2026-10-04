<label class="qc-readonly"><span>Preservante</span><input class="<?= empty($s['resolved_preservative_id'])?'tare-warning':'' ?>" readonly value="<?= htmlspecialchars($s['preservative_code']?:($s['preservative_name']?:'Não configurado')) ?>"></label>
<label><span>Peso bruto (g)</span><input type="number" step="0.001" min="0.001" name="<?= $n ?>[gross_weight]" value="<?= $fmt($s['gross_weight'],3) ?>" <?= $locked?'disabled':'' ?> data-gross></label>
<label class="qc-readonly"><span>Volume (mL)</span><input readonly value="<?= $display::formatDecimalMeasurement($s['volume_ml'],1) ?>" data-volume data-volume-raw="<?= htmlspecialchars((string)($s['volume_ml']??'')) ?>"></label>
<label><span>Ht (%) <small>técnico</small></span><input type="number" step="0.01" name="<?= $n ?>[hematocrit]" value="<?= $fmt($s['hematocrit'],2) ?>" <?= $locked?'disabled':'' ?>></label>
<label><span>Hb (g/dL)</span><input type="number" step="0.01" name="<?= $n ?>[hemoglobin]" value="<?= $fmt($s['hemoglobin'],2) ?>" <?= $locked?'disabled':'' ?> data-hb></label>
<label class="qc-readonly"><span>Hb (g/U)</span><input readonly value="<?= $fmt($s['hemoglobin_per_unit'],2) ?>" data-hb-unit></label>
<label class="qc-readonly qc-hemolysis-field"><span>Grau de Hemólise (%)</span><input readonly value="<?= $s['hemolysis']['ready']?$fmt($s['hemolysis']['value'],2):'—' ?>"></label>
