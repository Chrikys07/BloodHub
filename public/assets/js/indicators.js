(() => {
    const data = window.indicatorChartData;
    const canvas = document.querySelector('#indicatorAnnualChart');
    const empty = document.querySelector('#indicatorChartEmpty');
    const tooltip = document.querySelector('#indicatorChartTooltip');

    if (data && canvas && Array.isArray(data.months)) {
        const hasValues = data.months.some(month => month.value !== null);
        canvas.hidden = !hasValues;
        if (empty) empty.hidden = hasValues;

        if (hasValues) {
            const format = value => {
                if (value === null) return '—';
                if (!data.scientific) return Number(value).toLocaleString('pt-BR', {minimumFractionDigits: 1, maximumFractionDigits: 1}) + '%';
                if (Number(value) === 0) return `0 ${data.unit || ''}`.trim();
                const exponent = Math.floor(Math.log10(Math.abs(Number(value))));
                const coefficient = Number(value) / (10 ** exponent);
                const superscript = String(exponent).replace(/[-\d]/g, character => character === '-' ? '⁻' : '⁰¹²³⁴⁵⁶⁷⁸⁹'[character]);
                return `${coefficient.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})} × 10${superscript} ${data.unit || ''}`.trim();
            };
            const statusLabel = status => ({met: 'Meta atingida', below: data.isWithin ? 'Abaixo da meta' : 'Meta não atingida', no_target: 'Meta não configurada'}[status] || 'Sem dados');

            const draw = () => {
                const ratio = Math.max(1, window.devicePixelRatio || 1);
                const width = canvas.clientWidth || 700;
                const height = Number(canvas.getAttribute('height')) || 280;
                canvas.width = width * ratio;
                canvas.height = height * ratio;
                const context = canvas.getContext('2d');
                context.scale(ratio, ratio);
                const padding = {left: 68, right: 18, top: 18, bottom: 42};
                const chartWidth = width - padding.left - padding.right;
                const chartHeight = height - padding.top - padding.bottom;
                const scaleValues = data.months.flatMap(month => [month.value, month.target]).filter(value => value !== null);
                const max = data.scientific ? Math.max(...scaleValues, 1) * 1.15 : 100;
                const step = chartWidth / 12;

                context.font = '11px system-ui';
                context.lineWidth = 1;
                for (let index = 0; index <= 10; index++) {
                    const value = max * (10 - index) / 10;
                    const y = padding.top + chartHeight * index / 10;
                    context.strokeStyle = '#e4e8ee';
                    context.beginPath();
                    context.moveTo(padding.left, y);
                    context.lineTo(width - padding.right, y);
                    context.stroke();
                    context.fillStyle = '#687386';
                    context.textAlign = 'right';
                    context.fillText(data.scientific ? value.toExponential(1).replace('.', ',') : `${Math.round(value)}%`, padding.left - 8, y + 4);
                }

                data.labels.forEach((label, index) => {
                    const month = data.months[index];
                    context.fillStyle = '#687386';
                    context.textAlign = 'center';
                    context.fillText(label, padding.left + step * (index + .5), height - padding.bottom + 22);
                    if (month.value !== null) {
                        const barHeight = Math.max(0, Math.min(month.value / max, 1)) * chartHeight;
                        context.fillStyle = month.status === 'met' ? '#2f8a62' : month.status === 'below' ? '#bb3d49' : '#9ba7b6';
                        context.fillRect(padding.left + step * index + step * .22, padding.top + chartHeight - barHeight, step * .56, barHeight);
                    }
                });

                context.strokeStyle = '#8a2c42';
                context.setLineDash([6, 5]);
                context.lineWidth = 2;
                context.beginPath();
                let segmentStarted = false;
                data.months.forEach((month, index) => {
                    if (month.target === null) { segmentStarted = false; return; }
                    const x = padding.left + step * (index + .5);
                    const y = padding.top + chartHeight - Math.min(month.target / max, 1) * chartHeight;
                    if (!segmentStarted) context.moveTo(x, y); else context.lineTo(x, y);
                    segmentStarted = true;
                });
                context.stroke();
                context.setLineDash([]);

                canvas.onmousemove = event => {
                    const rect = canvas.getBoundingClientRect();
                    const index = Math.floor((event.clientX - rect.left - padding.left) / step);
                    if (index < 0 || index > 11) { if (tooltip) tooltip.hidden = true; return; }
                    const month = data.months[index];
                    if (!tooltip) return;
                    const metric = data.isWithin ? 'Resultado:' : 'Média:';
                    tooltip.innerHTML = `<b>${data.monthNames[index]}/${data.year}</b><span>${metric}</span><strong>${format(month.value)}</strong><span>Meta:</span><strong>${format(month.target)}</strong><span>Status:</span><strong>${statusLabel(month.status)}</strong>`;
                    tooltip.hidden = false;
                    tooltip.style.left = `${Math.max(8, Math.min(event.clientX - rect.left + 14, rect.width - tooltip.offsetWidth - 8))}px`;
                    tooltip.style.top = `${Math.max(8, event.clientY - rect.top - tooltip.offsetHeight - 12)}px`;
                };
                canvas.onmouseleave = () => { if (tooltip) tooltip.hidden = true; };
            };

            draw();
            let resizeTimer;
            addEventListener('resize', () => { clearTimeout(resizeTimer); resizeTimer = setTimeout(draw, 150); });
        }
    }

    const analysis = document.querySelector('#analysisDialog');
    document.querySelector('#openAnalysis')?.addEventListener('click', () => analysis?.showModal());
    document.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', () => button.closest('dialog')?.close()));
    document.querySelectorAll('[data-pending]').forEach(button => button.addEventListener('click', () => document.querySelector(`#pending-${button.dataset.pending}`)?.showModal()));
    const editors = document.querySelector('#actionEditors');
    const template = document.querySelector('#actionTemplate');
    let index = editors?.children.length || 0;
    document.querySelector('#addAction')?.addEventListener('click', () => {
        if (editors && template) editors.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index++)));
    });
})();
