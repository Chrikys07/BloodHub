(()=>{
 const action=document.querySelector('#action-dialog'),complete=document.querySelector('#complete-dialog'),occurrences=document.querySelector('#occurrences-dialog'),followUp=document.querySelector('#follow-up-dialog'),removeDialog=document.querySelector('#remove-occurrence-dialog'),analysisForm=document.querySelector('#analysis-form');
 const analysisFields=['analysis_text','cause_classification','cause_other','no_action_required','no_action_justification'];
 const syncAnalysis=form=>{if(!form||!analysisForm||analysisForm.elements.analysis_text?.disabled)return;analysisFields.forEach(name=>{form.querySelectorAll(`[data-analysis-copy="${name}"]`).forEach(node=>node.remove());const source=analysisForm.elements[name],input=document.createElement('input');input.type='hidden';input.name=name;input.dataset.analysisCopy=name;input.value=source?.type==='checkbox'?(source.checked?'1':''):source?.value||'';form.appendChild(input);});};
 document.querySelector('[data-add-occurrences]')?.addEventListener('click',()=>occurrences.showModal());
 document.querySelector('[data-add-action]')?.addEventListener('click',()=>{action.querySelector('form').reset();action.querySelector('[name=action_id]').value='';action.querySelector('h3').textContent='Adicionar ação';action.showModal();});
 document.querySelectorAll('[data-edit-action]').forEach(button=>button.addEventListener('click',()=>{const data=JSON.parse(button.dataset.editAction),form=action.querySelector('form');form.querySelector('[name=action_id]').value=data.id;['action_description','responsible_user_id','responsible_name','due_date','status','completion_notes'].forEach(key=>{if(form.elements[key])form.elements[key].value=data[key]||'';});action.querySelector('h3').textContent='Editar ação';action.showModal();}));
 action?.querySelector('form')?.addEventListener('submit',event=>syncAnalysis(event.currentTarget));
 complete?.querySelector('form')?.addEventListener('submit',event=>syncAnalysis(event.currentTarget));
 followUp?.querySelector('form')?.addEventListener('submit',event=>syncAnalysis(event.currentTarget));
 document.querySelector('[data-open-complete]')?.addEventListener('click',()=>complete.showModal());
 const noAction=analysisForm?.elements.no_action_required,reason=analysisForm?.elements.no_action_justification,primary=document.querySelector('[data-start-follow-up]');
 const toggle=()=>{if(reason){reason.disabled=!noAction?.checked;reason.required=!!noAction?.checked;}document.querySelector('[data-add-action]')?.toggleAttribute('hidden',!!noAction?.checked);if(primary)primary.textContent=noAction?.checked?'Concluir análise':'Finalizar análise e iniciar acompanhamento';};
 noAction?.addEventListener('change',toggle);toggle();
 primary?.addEventListener('click',()=>noAction?.checked?complete.showModal():followUp.showModal());
 let pendingRemoval=null;document.querySelectorAll('[data-remove-occurrence]').forEach(form=>form.addEventListener('submit',event=>{event.preventDefault();pendingRemoval=form;removeDialog.showModal();}));
 document.querySelector('[data-confirm-remove]')?.addEventListener('click',()=>pendingRemoval?.submit());
 document.querySelectorAll('.dialog-close,[data-cancel-dialog]').forEach(button=>button.addEventListener('click',()=>button.closest('dialog').close()));
})();
