/* ============================================================
 * BloodHub • App Shell (UI + navegação + Produção)
 * - Layout inspirado no modelo de referência (corporativo, limpo)
 * - Nesta fase: persistência LOCAL (localStorage) para simulação
 * - Preparado para futura troca por back-end/banco na nuvem
 * ============================================================ */

(() => {
  const $ = (s, el=document) => el.querySelector(s);
  const $$ = (s, el=document) => Array.from(el.querySelectorAll(s));

  // -----------------------------
  // Contexto do usuário (para escopo do storage)
  // -----------------------------
  const shell = document.querySelector('.shell');
  const username = shell?.dataset.username || 'user';
  const LOCAL = 'LOCAL'; // placeholder para futura leitura (ex.: unidade/local de trabalho)

  // -----------------------------
  // Menu do usuário (top-right)
  // -----------------------------
  const btnUserMenu = $('#btnUserMenu');
  const userMenu = $('#userMenu');
  const userChip = $('#userChip');

  function closeUserMenu(){ if(userMenu) userMenu.hidden = true; }
  function toggleUserMenu(){ if(userMenu) userMenu.hidden = !userMenu.hidden; }

  if(btnUserMenu && userMenu){
    btnUserMenu.addEventListener('click', (e) => {
      e.stopPropagation();
      toggleUserMenu();
    });

    document.addEventListener('click', (e) => {
      // fecha quando clicar fora
      if (!userMenu.hidden) {
        const inside = userMenu.contains(e.target) || userChip?.contains(e.target);
        if (!inside) closeUserMenu();
      }
    });
  }

  // -----------------------------
  // Sidebar (grupo Estoque)
  // -----------------------------
  const navEstoque = $('#navEstoque');
  const subEstoque = $('#subEstoque');
  if(navEstoque && subEstoque){
    navEstoque.addEventListener('click', () => {
      const open = subEstoque.classList.toggle('open');
      navEstoque.setAttribute('aria-expanded', String(open));
      const caret = navEstoque.querySelector('.nav-caret');
      if (caret) caret.textContent = open ? '▾' : '▸';
    });
  }

  // -----------------------------
  // Navegação interna (home ↔ produção)
  // -----------------------------
  const viewTitle = $('#viewTitle');
  const viewSub = $('#viewSub');
  const viewHome = $('#view-home');
  const viewProducao = $('#view-producao');

  function setActiveRoute(route){
    // Reset views
    if(viewHome) viewHome.hidden = true;
    if(viewProducao) viewProducao.hidden = true;

    // Reset subnav active
    $$('.nav-sub-item').forEach(b => b.classList.remove('active'));

    if(route === 'producao'){
      if(viewProducao) viewProducao.hidden = false;
      const btn = document.querySelector('.nav-sub-item[data-route="producao"]');
      if(btn) btn.classList.add('active');

      if(viewTitle) viewTitle.textContent = 'Estoque • Produção';
      if(viewSub) viewSub.textContent = 'Registro de produção diária (simulação local nesta fase).';

      resetProducaoUI();
      return;
    }

    // default: home
    if(viewHome) viewHome.hidden = false;
    if(viewTitle) viewTitle.textContent = 'Início';
    if(viewSub) viewSub.textContent = 'Selecione uma opção no menu para começar.';
    resetProducaoUI();
  }

  // Delegação: clique nos itens
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-route]');
    if(!btn) return;
    const route = btn.dataset.route;
    if(btn.disabled) return;
    setActiveRoute(route);
  });

  // Atalho do card inicial
  document.addEventListener('click', (e) => {
    const sc = e.target.closest('[data-shortcut]');
    if(!sc) return;
    const route = sc.dataset.shortcut;
    setActiveRoute(route);
  });

  // -----------------------------
  // Produção (persistência local + fluxo Buscar/Confirmar)
  // -----------------------------
  const PROD_COMPONENTES = [
    { key: 'CH', label: 'CH' },
    { key: 'CH_FEN', label: 'CH-Fen' },
    { key: 'CH_PV', label: 'CH-PV Peq.Vol' },
    { key: 'CH_F', label: 'CH-F Filtrado' },
    { key: 'CP', label: 'CP' },
    { key: 'CPA', label: 'CPA Aférese' },
    { key: 'PFC', label: 'PFC' },
    { key: 'PFC24', label: 'PFC24' },
    { key: 'PP', label: 'PP Plasma Preservado' },
    { key: 'PIC', label: 'PIC Plasma Isento de Crio' },
    { key: 'PFC_TR', label: 'PFC-TR Plasma Tralli' },
    { key: 'CRIO', label: 'CRIO Crioprecipitado' },
  ];

  // Storage: preparado para futuro (pode trocar por API sem mudar UI)
  const STORAGE_KEY = 'bloodhub_producao_v1';
  const storage = {
    getAll(){
      try{ return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}'); }
      catch{ return {}; }
    },
    setAll(obj){ localStorage.setItem(STORAGE_KEY, JSON.stringify(obj || {})); },
    getByDate(dateISO){
      const all = storage.getAll();
      return all?.[LOCAL]?.[username]?.[dateISO] || null;
    },
    setByDate(dateISO, data){
      const all = storage.getAll();
      all[LOCAL] ||= {};
      all[LOCAL][username] ||= {};
      all[LOCAL][username][dateISO] = data;
      storage.setAll(all);
    },
    deleteByDate(dateISO){
      const all = storage.getAll();
      if(all?.[LOCAL]?.[username]?.[dateISO]){
        delete all[LOCAL][username][dateISO];
        storage.setAll(all);
      }
    }
  };

  // UI refs
  const prodDate = $('#prodDate');
  const btnProdBuscar = $('#btnProdBuscar');
  const prodFormWrap = $('#prodFormWrap');
  const prodTbody = $('#prodTbody');
  const btnProdSalvar = $('#btnProdSalvar');
  const btnProdLimpar = $('#btnProdLimpar');
  const prodStatus = $('#prodStatus');
  const prodBadge = $('#prodBadge');

  // Modal refs
  const confirmModal = $('#confirmModal');
  const confirmMsg = $('#confirmMsg');
  const confirmYes = $('#confirmYes');
  const confirmNo = $('#confirmNo');

  let pendingDate = null;

  function todayISO(){
    const d = new Date();
    const z = new Date(d.getTime() - d.getTimezoneOffset() * 60000);
    return z.toISOString().slice(0,10);
  }

  function showPill(msg, kind='info'){
    if(!prodStatus) return;
    prodStatus.style.display = '';
    prodStatus.className = 'pill ' + kind;
    prodStatus.textContent = msg;
  }
  function hidePill(){
    if(!prodStatus) return;
    prodStatus.style.display = 'none';
    prodStatus.textContent = '';
  }

  function openConfirm(message){
    if(!confirmModal) return;
    if(confirmMsg) confirmMsg.textContent = message;
    confirmModal.hidden = false;
  }
  function closeConfirm(){
    if(!confirmModal) return;
    confirmModal.hidden = true;
    pendingDate = null;
  }

  if(confirmModal){
    confirmModal.addEventListener('click', (e) => {
      const close = e.target?.dataset?.close;
      if(close) closeConfirm();
    });
    document.addEventListener('keydown', (e) => {
      if(e.key === 'Escape' && !confirmModal.hidden) closeConfirm();
    });
  }

  function renderProdTable(values){
    if(!prodTbody) return;
    prodTbody.innerHTML = '';

    PROD_COMPONENTES.forEach((c) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="rowtitle">${c.label}</td>
        <td>
          <input
            class="input qty"
            type="number"
            min="0"
            step="1"
            inputmode="numeric"
            data-key="${c.key}"
            value="${Number(values?.[c.key] ?? 0)}"
            aria-label="Quantidade ${c.label}"
          />
        </td>
      `;
      prodTbody.appendChild(tr);
    });
  }

  function readProdTable(){
    const out = {};
    $$('#prodTbody input[data-key]').forEach((inp) => {
      const key = inp.dataset.key;
      out[key] = Math.max(0, Number(inp.value || 0));
    });
    return out;
  }

  function setBadge(dateISO, hasData){
    if(!prodBadge) return;
    if(!dateISO){ prodBadge.textContent = '—'; return; }
    prodBadge.textContent = hasData ? `Data: ${dateISO} • Encontrado` : `Data: ${dateISO} • Novo`;
  }

  function resetProducaoUI(){
    // regra: ao sair e voltar, precisa informar data novamente
    if(prodDate) prodDate.value = '';
    if(prodFormWrap) prodFormWrap.style.display = 'none';
    if(prodTbody) prodTbody.innerHTML = '';
    hidePill();
    setBadge('', false);
  }

  // default date suggestion (não salva nada)
  if(prodDate) prodDate.value = '';

  function buscar(dateISO){
    const found = storage.getByDate(dateISO);
    if(found){
      renderProdTable(found);
      if(prodFormWrap) prodFormWrap.style.display = '';
      setBadge(dateISO, true);
      showPill('Dados carregados (local).', 'ok');
      return;
    }

    // não encontrou: confirma inserção
    pendingDate = dateISO;
    setBadge(dateISO, false);
    openConfirm('Não existem dados de produção para esta data. Deseja inserir agora?');
  }

  if(btnProdBuscar){
    btnProdBuscar.addEventListener('click', () => {
      hidePill();
      const dateISO = (prodDate?.value || '').trim();
      if(!dateISO){
        showPill('Informe uma data para buscar.', 'warn');
        setBadge('', false);
        if(prodFormWrap) prodFormWrap.style.display = 'none';
        return;
      }
      buscar(dateISO);
    });
  }

  if(confirmYes){
    confirmYes.addEventListener('click', () => {
      if(!pendingDate){ closeConfirm(); return; }
      renderProdTable(null);
      if(prodFormWrap) prodFormWrap.style.display = '';
      showPill('Insira as quantidades e clique em Salvar.', 'info');
      closeConfirm();
    });
  }
  if(confirmNo){
    confirmNo.addEventListener('click', () => {
      // mantém apenas o filtro
      if(prodFormWrap) prodFormWrap.style.display = 'none';
      if(prodTbody) prodTbody.innerHTML = '';
      showPill('Sem dados para a data selecionada.', 'muted');
      closeConfirm();
    });
  }

  if(btnProdSalvar){
    btnProdSalvar.addEventListener('click', () => {
      const dateISO = (prodDate?.value || '').trim();
      if(!dateISO){
        showPill('Informe a data antes de salvar.', 'warn');
        return;
      }

      const payload = readProdTable();
      storage.setByDate(dateISO, payload);
      setBadge(dateISO, true);
      showPill('Salvo com sucesso (local).', 'ok');
    });
  }

  if(btnProdLimpar){
    btnProdLimpar.addEventListener('click', () => {
      renderProdTable(null);
      showPill('Campos limpos. Clique em Salvar para gravar as alterações.', 'info');
    });
  }

  // Inicializa na home
  setActiveRoute('home');

})();
