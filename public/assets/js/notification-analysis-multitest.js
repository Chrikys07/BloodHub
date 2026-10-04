(()=>{
  const dialog=document.querySelector('#occurrences-dialog');
  const filter=document.querySelector('[data-candidate-test]');
  filter?.addEventListener('change',()=>{
    dialog.querySelectorAll('[data-candidate-test-name]').forEach(row=>{
      row.hidden=Boolean(filter.value)&&row.dataset.candidateTestName!==filter.value;
      if(row.hidden) row.querySelector('input').checked=false;
    });
  });
})();
