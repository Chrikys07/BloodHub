(function(global){
    const decimalPtBr=value=>{
        const normalized=String(value??'').trim().replace(',','.');
        if(!/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/.test(normalized))return NaN;
        return Number(normalized);
    };

    const calculateFreeHemoglobin=values=>{
        const [a370,a415,a510,a577,a600]=values.map(decimalPtBr);
        if([a370,a415,a510,a577,a600].some(value=>!Number.isFinite(value)))return NaN;
        if(a415<=0.9){
            const x=a510+(a370-a510)*0.68;
            return ((a415-x)/113.22*16125)/1000;
        }
        const x=a600+(a510-a600)*0.26;
        return ((a577-x)/13.64*16125)/1000;
    };

    const calculateHemolysisDegree=(freeHemoglobin,hematocrit,hemoglobin)=>{
        const free=Number(freeHemoglobin),hct=decimalPtBr(hematocrit),hb=decimalPtBr(hemoglobin);
        return Number.isFinite(free)&&Number.isFinite(hct)&&Number.isFinite(hb)&&hb!==0?(free/hb)*(100-hct):NaN;
    };

    const bindRealtime=(root=document)=>{
        root.querySelectorAll('[data-hemolysis-calculator]').forEach(container=>{
            const inputs=[...container.querySelectorAll('[data-hemolysis-abs]')];
            const freeOutput=container.querySelector('[data-free-hb]');
            const card=container.closest('[data-laboratory-card]');
            const degreeOutput=card?.querySelector('[data-hemolysis-degree]');
            const calculate=()=>{
                const free=calculateFreeHemoglobin(inputs.map(input=>input.value));
                if(freeOutput)freeOutput.textContent=Number.isFinite(free)?free.toFixed(6).replace('.',','):'—';
                if(degreeOutput){
                    const degree=calculateHemolysisDegree(free,card.querySelector('[data-hct]')?.value,card.querySelector('[data-hb]')?.value);
                    degreeOutput.textContent=Number.isFinite(degree)?degree.toFixed(3).replace('.',','):'—';
                }
            };
            [...inputs,card?.querySelector('[data-hct]'),card?.querySelector('[data-hb]')].filter(Boolean).forEach(input=>{
                input.addEventListener('input',calculate);
                input.addEventListener('change',calculate);
            });
            calculate();
        });
    };

    global.BloodHubHemolysisCalculator=Object.freeze({decimalPtBr,calculateFreeHemoglobin,calculateHemolysisDegree,bindRealtime});
})(window);
