(function(global){
    const superscript=value=>String(value).replace(/[-0-9]/g,character=>({
        '-':'⁻','0':'⁰','1':'¹','2':'²','3':'³','4':'⁴','5':'⁵','6':'⁶','7':'⁷','8':'⁸','9':'⁹'
    })[character]);

    const formatScientificPtBr=(value,maxDecimals=2)=>{
        const number=Number(value);
        if(!Number.isFinite(number))return '';
        if(number===0)return '0';
        let exponent=Math.floor(Math.log10(Math.abs(number)));
        let mantissa=Number((number/10**exponent).toFixed(Math.max(0,maxDecimals)));
        if(Math.abs(mantissa)>=10){mantissa/=10;exponent++;}
        return mantissa.toLocaleString('pt-BR',{maximumFractionDigits:Math.max(0,maxDecimals)})+' × 10'+superscript(exponent);
    };

    const formatDecimalMeasurementPtBr=(value,decimals)=>{
        const number=Number(value);
        return Number.isFinite(number)?number.toLocaleString('pt-BR',{minimumFractionDigits:decimals,maximumFractionDigits:decimals}):'';
    };

    global.BloodHubMeasurementFormatter=Object.freeze({formatScientificPtBr,formatDecimalMeasurementPtBr});
})(window);
