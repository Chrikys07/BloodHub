(function(global){
    const decimalPtBr=value=>{
        let normalized=String(value??'').trim();
        if(normalized==='')return null;
        if(normalized.includes(','))normalized=normalized.replaceAll('.','').replace(',','.');
        const number=Number(normalized);
        return Number.isFinite(number)?number:NaN;
    };
    const calculate=(value,formula)=>{const count=decimalPtBr(value);return count===null?null:(Number.isFinite(count)?formula(count):NaN);};
    // FC0538: Nº Plaquetas × 5 × 10 × 20 × 1000.
    const calculatePfPlateletsPerMl=value=>calculate(value,count=>count*5*10*20*1000);
    // FC0538: (Nº Leucócitos × 1000 × 2) / 50.
    const calculatePfLeukocytesPerMl=value=>calculate(value,count=>(count*1000*2)/50);
    // FC0538: Nº Hemácias × 5 × 10 × 1000.
    const calculatePfRedCellsPerMl=value=>calculate(value,count=>count*5*10*1000);
    const formulas=Object.freeze({
        platelets:calculatePfPlateletsPerMl,
        leukocytes:calculatePfLeukocytesPerMl,
        'red-cells':calculatePfRedCellsPerMl
    });

    const updateResult=(card,group,formatScientific,preserveExisting=false)=>{
        const count=card.querySelector('[data-pf-count="'+group+'"]');
        const output=card.querySelector('[data-pf-result="'+group+'"]');
        if(!count||!output||!formulas[group])return;
        if(preserveExisting&&String(count.value??'').trim()==='')return;
        const result=formulas[group](count.value);
        output.value=result===null?'':formatScientific(result);
    };

    const bindRealtime=(root,formatScientific)=>{
        const selector=[
            '[data-sample-form][data-component="FRESH_PLASMA"]',
            '[data-laboratory-card][data-component="PF"]',
            '[data-laboratory-card][data-component="PF24"]',
            '[data-laboratory-card][data-component="FRESH_PLASMA"]'
        ].join(',');
        const handle=event=>{
            const count=event.target.closest?.('[data-pf-count]');
            const card=count?.closest(selector);
            if(card)updateResult(card,count.dataset.pfCount,formatScientific);
        };
        root.addEventListener('input',handle);
        root.addEventListener('change',handle);
        root.querySelectorAll(selector).forEach(card=>{
            Object.keys(formulas).forEach(group=>updateResult(card,group,formatScientific,true));
        });
    };

    global.BloodHubFreshPlasmaCalculator=Object.freeze({decimalPtBr,calculatePfPlateletsPerMl,calculatePfLeukocytesPerMl,calculatePfRedCellsPerMl,bindRealtime});
})(window);
