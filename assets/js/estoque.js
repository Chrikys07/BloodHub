(() => {
  const KEY = "bloodhub_estoque_v1";

  const $ = (sel) => document.querySelector(sel);
  const $$ = (sel) => Array.from(document.querySelectorAll(sel));

  const tabs = $$(".tab");
  const inputs = $$("#tblEstoque input[data-k]");
  const btnSalvar = $("#btnSalvar");
  const btnLimpar = $("#btnLimpar");
  const obs = $("#obs");

  const panelTitle = $("#panelTitle");
  const kpiTotal = $("#kpiTotal");
  const kpiComp = $("#kpiComp");
  const lastSaved = $("#lastSaved");

  let compAtual = "CH";

  const TITULOS = {
    CH: "Concentrado de Hemácias (CH)",
    CP: "Concentrado de Plaquetas (CP)",
    PFC: "Plasma Fresco Congelado (PFC)",
    CRIO: "Crioprecipitado (CRIO)",
  };

  function getStore() {
    try {
      return JSON.parse(localStorage.getItem(KEY) || "{}");
    } catch {
      return {};
    }
  }

  function setStore(data) {
    localStorage.setItem(KEY, JSON.stringify(data));
  }

  function calcTotal() {
    let total = 0;
    inputs.forEach(i => {
      const n = parseInt(i.value || "0", 10);
      if (!Number.isNaN(n)) total += n;
    });
    kpiTotal.textContent = String(total);
  }

  function carregar(comp) {
    const store = getStore();
    const bloco = store[comp] || { valores: {}, obs: "", lastSaved: null };

    inputs.forEach(i => {
      const k = i.dataset.k;
      i.value = (bloco.valores?.[k] ?? "");
    });

    obs.value = bloco.obs ?? "";

    if (bloco.lastSaved) {
      lastSaved.textContent = `Último salvamento: ${bloco.lastSaved}`;
    } else {
      lastSaved.textContent = "Ainda não salvo";
    }

    calcTotal();
  }

  function salvar() {
    const store = getStore();

    const valores = {};
    inputs.forEach(i => {
      const k = i.dataset.k;
      const v = i.value.trim();
      valores[k] = v === "" ? "" : String(Math.max(0, parseInt(v, 10) || 0));
      i.value = valores[k];
    });

    const now = new Date();
    const stamp = now.toLocaleString("pt-BR");

    store[compAtual] = {
      valores,
      obs: obs.value.trim(),
      lastSaved: stamp
    };

    setStore(store);
    lastSaved.textContent = `Último salvamento: ${stamp}`;
  }

  function limpar() {
    inputs.forEach(i => (i.value = ""));
    obs.value = "";
    calcTotal();
  }

  // Tabs
  tabs.forEach(t => {
    t.addEventListener("click", () => {
      tabs.forEach(x => x.classList.remove("active"));
      t.classList.add("active");

      compAtual = t.dataset.tab;
      panelTitle.textContent = TITULOS[compAtual] || compAtual;
      kpiComp.textContent = compAtual;

      carregar(compAtual);
    });
  });

  // Eventos
  inputs.forEach(i => i.addEventListener("input", calcTotal));
  obs.addEventListener("input", () => { /* nada, só mantém */ });

  btnSalvar.addEventListener("click", () => {
    salvar();
    // feedback simples
    btnSalvar.textContent = "Salvo ✓";
    setTimeout(() => (btnSalvar.textContent = "Salvar"), 900);
  });

  btnLimpar.addEventListener("click", () => {
    limpar();
  });

  // Inicial
  panelTitle.textContent = TITULOS[compAtual];
  kpiComp.textContent = compAtual;
  carregar(compAtual);
})();
