(() => {
  // ==========================================================
  // BloodHub • Estoque módulo (robusto)
  // - Delegação de eventos (não trava em Produção)
  // - Inserção funciona mesmo com botão dentro de <form>
  // - Busca sempre mostra editor + placeholder
  // ==========================================================

  const $ = (s) => document.querySelector(s);
  const $$ = (s) => Array.from(document.querySelectorAll(s));

  // ===== Cabeçalho (oculta botões antigos) =====
  const headActions = document.querySelector(".head-actions");
  if (headActions) headActions.style.display = "none";

  // ===== Regras base =====
  const TIPOS_PRODUZIVEIS = ["CP", "CH", "PFC", "CRIO"];
  const ABO = ["A", "B", "AB", "O"];
  const RH = ["+", "-"];

  const _CONV_PARES = [
    ["CP", "CPAF"], ["CP", "CPBC"], ["CP", "CPBL"],
    ["PFC", "PFC24"], ["PFC", "PF"],
    ["CRIO", "CRIOA"], ["CRIO", "CRIOP"],
    ["CH", "CHF"], ["CH", "CHL"], ["CH", "CHAF"]
  ];

  const MAP_TRANSF = (() => {
    const m = {};
    _CONV_PARES.forEach(([o, d]) => { (m[o] ||= new Set()).add(d); });
    return m;
  })();

  function optList(arr, selected = "") {
    return arr.map(v => `<option value="${v}" ${v === selected ? "selected" : ""}>${v}</option>`).join("");
  }

  // ===== Persistência local =====
  const KEY = "bloodhub_mov_v1";

  // cache em memória (evita perder alterações por não chamar storeSet)
  let STORE = null;
  function storeLoad(){ STORE = storeGet(); return STORE; }
  function storeSave(){ try{ storeSet(STORE || {}); }catch(e){ console.warn(e); } }


  function getUserLocal() { return "LOCAL"; }

  function storeGet() {
    try { return JSON.parse(localStorage.getItem(KEY) || "{}"); }
    catch { return {}; }
  }
  function storeSet(obj) { localStorage.setItem(KEY, JSON.stringify(obj)); }

  // carrega o storage uma vez
  storeLoad();

  function bucket(dateISO) {
    const local = getUserLocal();
    const s = (STORE ||= storeGet());
    s[local] ||= {};
    s[local][dateISO] ||= { producao: [], liberacao: [], transformacoes: [], desprezo: [], inventario: [] };
    storeSave();
    return s[local][dateISO];
  }

  function todayISO() {
    const d = new Date();
    const z = new Date(d.getTime() - d.getTimezoneOffset() * 60000);
    return z.toISOString().slice(0, 10);
  }

  function pill(el, msg, kind = "info") {
    if (!el) return;
    el.style.display = "";
    el.className = "pill " + (kind || "");
    el.textContent = msg;
  }
  function hidePill(el) {
    if (!el) return;
    el.style.display = "none";
    el.textContent = "";
  }

  // ===== Normalização de nomes (evita travar por plural/singular/typo) =====
  const SUBS = ["producao", "liberacao", "transformacoes", "desprezo", "inventario"];

  function normSub(x) {
    x = (x || "").toString().trim().toLowerCase();
    if (x === "transformacao") return "transformacoes";
    if (x === "despesa" || x === "despesas") return "desprezo";
    return x;
  }

  // ===== Localiza seção mesmo com IDs diferentes =====
  function getSection(sub) {
    sub = normSub(sub);

    // padrões aceitos:
    // #estoque-producao / #sec-producao / #secProducao / data-estoque="producao"
    return (
      $(`#estoque-${sub}`) ||
      $(`#sec-${sub}`) ||
      $(`#sec${sub.charAt(0).toUpperCase() + sub.slice(1)}`) ||
      document.querySelector(`[data-estoque-section="${sub}"]`) ||
      // fallback singular transformacao
      (sub === "transformacoes" ? (
        $(`#estoque-transformacao`) ||
        $(`#sec-transformacao`) ||
        $(`#secTransformacao`) ||
        document.querySelector(`[data-estoque-section="transformacao"]`)
      ) : null)
    );
  }

  function showSub(sub) {
    sub = normSub(sub);
    if (!sub) return;

    SUBS.forEach(s => {
      const sec = getSection(s);
      if (sec) sec.style.display = (normSub(s) === sub) ? "" : "none";
    });

    // marca ativo em ambos padrões de botão
    $$(".subnav-item, .nav-sub-item").forEach(b => {
      const bSub = normSub(
        b.dataset.sub || b.dataset.subview || b.getAttribute("data-sub") || b.getAttribute("data-subview") || ""
      );
      b.classList.toggle("active", bSub === sub);
    });
  }

  // ===== Submenu abre/fecha =====
  function bindMenuToggle() {
    const btn = $("#navEstoque");
    const sub = $("#subEstoque");
    const caret = $("#caretEstoque") || btn?.querySelector(".nav-caret");
    if (!btn || !sub) return;

    btn.addEventListener("click", (e) => {
      e.preventDefault();
      const open = sub.classList.toggle("open");
      if (caret) caret.textContent = open ? "▾" : "▸";
    });
  }

  // ===== Sempre mostrar editor depois do Buscar =====
  function forceEditorVisible(editorId, vazioId, listaId, msg) {
    const editor = $(editorId);
    const lista  = $(listaId);
    const vazio  = $(vazioId); // opcional

    if (!editor || !lista) {
      console.warn("[BloodHub] Falta editor/lista:", { editorId, listaId });
      return;
    }

    editor.style.display = "";
    if (vazio) vazio.style.display = "none";

    if (!lista.children.length) {
      lista.innerHTML = `<div class="empty-row">${msg}</div>`;
    }
  }

  // ==========================================================
  // RENDERERS
  // ==========================================================
  function renderProd(dateISO) {
    const b = bucket(dateISO);
    const lista = $("#listaProd");
    const editor = $("#editorProd");
    if (!lista || !editor) return;

    lista.innerHTML = "";

    b.producao.forEach((r, idx) => {
      const card = document.createElement("div");
      card.className = "card-row";
      card.innerHTML = `
        <div class="grid-fields">
          <label class="field"><span>Tipo</span>
            <select class="input" data-f="tipo">${optList(TIPOS_PRODUZIVEIS, r.tipo)}</select>
          </label>
          <label class="field"><span>ABO</span>
            <select class="input" data-f="abo">${optList(ABO, r.abo)}</select>
          </label>
          <label class="field"><span>RH</span>
            <select class="input" data-f="rh">${optList(RH, r.rh)}</select>
          </label>
          <label class="field"><span>Qtd</span>
            <input class="input" type="number" min="0" data-f="qtd" value="${r.qtd ?? 0}">
          </label>
          <div></div>
          <button type="button" class="btn-secondary" data-act="del">Remover</button>
        </div>
      `;

      card.querySelector('[data-act="del"]').addEventListener("click", (e) => {
        e.preventDefault();
        b.producao.splice(idx, 1);
        storeSave();
        renderProd(dateISO);
      });

      card.querySelectorAll("[data-f]").forEach(inp => {
        inp.addEventListener("change", () => {
          const f = inp.dataset.f;
          r[f] = (f === "qtd") ? Number(inp.value || 0) : inp.value;
          storeSave();
        });
      });

      lista.appendChild(card);
    });
  }

  function renderLib(dateISO) {
    const b = bucket(dateISO);
    const lista = $("#listaLib");
    if (!lista) return;

    lista.innerHTML = "";

    b.liberacao.forEach((r, idx) => {
      const card = document.createElement("div");
      card.className = "card-row";
      card.innerHTML = `
        <div class="grid-fields">
          <label class="field"><span>Tipo</span>
            <select class="input" data-f="tipo">${optList(TIPOS_PRODUZIVEIS, r.tipo)}</select>
          </label>
          <label class="field"><span>ABO</span>
            <select class="input" data-f="abo">${optList(ABO, r.abo)}</select>
          </label>
          <label class="field"><span>RH</span>
            <select class="input" data-f="rh">${optList(RH, r.rh)}</select>
          </label>
          <label class="field"><span>Qtd</span>
            <input class="input" type="number" min="0" data-f="qtd" value="${r.qtd ?? 0}">
          </label>
          <label class="field"><span>Data produção</span>
            <input class="input" type="date" data-f="dataProd" value="${r.dataProd || dateISO}">
          </label>
          <button type="button" class="btn-secondary" data-act="del">Remover</button>
        </div>
      `;

      card.querySelector('[data-act="del"]').addEventListener("click", (e) => {
        e.preventDefault();
        b.liberacao.splice(idx, 1);
        storeSave();
        renderLib(dateISO);
      });

      card.querySelectorAll("[data-f]").forEach(inp => {
        inp.addEventListener("change", () => {
          const f = inp.dataset.f;
          r[f] = (f === "qtd") ? Number(inp.value || 0) : inp.value;
          storeSave();
        });
      });

      lista.appendChild(card);
    });
  }

  function renderTransf(dateISO) {
    const b = bucket(dateISO);
    const lista = $("#listaTransf");
    if (!lista) return;

    lista.innerHTML = "";

    b.transformacoes.forEach((t, idx) => {
      const allowed = Array.from(MAP_TRANSF[t.origemTipo] || []);
      const card = document.createElement("div");
      card.className = "card-row";
      card.innerHTML = `
        <div class="grid-fields">
          <label class="field"><span>Origem</span>
            <select class="input" data-f="origemTipo">${optList(Object.keys(MAP_TRANSF), t.origemTipo)}</select>
          </label>
          <label class="field"><span>Destino</span>
            <select class="input" data-f="destTipo">
              ${(allowed.length ? allowed : [""]).map(v => `<option value="${v}" ${v===t.destTipo?"selected":""}>${v}</option>`).join("")}
            </select>
          </label>
          <label class="field"><span>ABO</span>
            <select class="input" data-f="abo">${optList(ABO, t.abo)}</select>
          </label>
          <label class="field"><span>RH</span>
            <select class="input" data-f="rh">${optList(RH, t.rh)}</select>
          </label>
          <label class="field"><span>Qtd origem</span>
            <input class="input" type="number" min="0" data-f="qtdOrigem" value="${t.qtdOrigem ?? 0}">
          </label>
          <label class="field"><span>Qtd destino</span>
            <input class="input" type="number" min="0" data-f="qtdDestino" value="${t.qtdDestino ?? 0}">
          </label>
        </div>
        <div class="grid-fields">
          <label class="field"><span>Obs</span>
            <input class="input" type="text" data-f="obs" value="${t.obs ?? ""}">
          </label>
          <div></div><div></div><div></div><div></div>
          <button type="button" class="btn-secondary" data-act="del">Remover</button>
        </div>
      `;

      card.querySelector('[data-act="del"]').addEventListener("click", (e) => {
        e.preventDefault();
        b.transformacoes.splice(idx, 1);
        storeSave();
        renderTransf(dateISO);
      });

      card.querySelectorAll("[data-f]").forEach(inp => {
        inp.addEventListener("change", () => {
          const f = inp.dataset.f;

          if (f === "origemTipo") {
            t.origemTipo = inp.value;
            t.destTipo = Array.from(MAP_TRANSF[t.origemTipo] || [])[0] || "";
            renderTransf(dateISO);
            return;
          }

          t[f] = (f === "qtdOrigem" || f === "qtdDestino") ? Number(inp.value || 0) : inp.value;
        });
      });

      lista.appendChild(card);
    });
  }

  function renderDesp(dateISO) {
    const b = bucket(dateISO);
    const lista = $("#listaDesp");
    if (!lista) return;

    lista.innerHTML = "";

    b.desprezo.forEach((r, idx) => {
      const card = document.createElement("div");
      card.className = "card-row";
      card.innerHTML = `
        <div class="grid-fields">
          <label class="field"><span>Tipo</span>
            <input class="input" type="text" data-f="tipo" value="${r.tipo || "CH"}">
          </label>
          <label class="field"><span>ABO</span>
            <select class="input" data-f="abo">${optList(ABO, r.abo)}</select>
          </label>
          <label class="field"><span>RH</span>
            <select class="input" data-f="rh">${optList(RH, r.rh)}</select>
          </label>
          <label class="field"><span>Qtd</span>
            <input class="input" type="number" min="0" data-f="qtd" value="${r.qtd ?? 0}">
          </label>
          <label class="field"><span>Motivo</span>
            <input class="input" type="text" data-f="motivo" value="${r.motivo ?? ""}">
          </label>
          <button type="button" class="btn-secondary" data-act="del">Remover</button>
        </div>
      `;

      card.querySelector('[data-act="del"]').addEventListener("click", (e) => {
        e.preventDefault();
        b.desprezo.splice(idx, 1);
        storeSave();
        renderDesp(dateISO);
      });

      card.querySelectorAll("[data-f]").forEach(inp => {
        inp.addEventListener("change", () => {
          const f = inp.dataset.f;
          r[f] = (f === "qtd") ? Number(inp.value || 0) : inp.value;
          storeSave();
        });
      });

      lista.appendChild(card);
    });
  }

  function renderInv(dateISO) {
    const b = bucket(dateISO);
    const lista = $("#listaInv");
    if (!lista) return;

    lista.innerHTML = "";

    b.inventario.forEach((r, idx) => {
      const card = document.createElement("div");
      card.className = "card-row";
      card.innerHTML = `
        <div class="grid-fields">
          <label class="field"><span>Tipo</span>
            <input class="input" type="text" data-f="tipo" value="${r.tipo || "CH"}">
          </label>
          <label class="field"><span>ABO</span>
            <select class="input" data-f="abo">${optList(ABO, r.abo)}</select>
          </label>
          <label class="field"><span>RH</span>
            <select class="input" data-f="rh">${optList(RH, r.rh)}</select>
          </label>
          <label class="field"><span>Delta</span>
            <input class="input" type="number" data-f="delta" value="${r.delta ?? 0}">
          </label>
          <label class="field"><span>Obs</span>
            <input class="input" type="text" data-f="obs" value="${r.obs ?? ""}">
          </label>
          <button type="button" class="btn-secondary" data-act="del">Remover</button>
        </div>
      `;

      card.querySelector('[data-act="del"]').addEventListener("click", (e) => {
        e.preventDefault();
        b.inventario.splice(idx, 1);
        storeSave();
        renderInv(dateISO);
      });

      card.querySelectorAll("[data-f]").forEach(inp => {
        inp.addEventListener("change", () => {
          const f = inp.dataset.f;
          r[f] = (f === "delta") ? Number(inp.value || 0) : inp.value;
        });
      });

      lista.appendChild(card);
    });
  }

  // ==========================================================
  // AÇÕES (Add)
  // ==========================================================
  function addProd(dateISO) {
    const b = bucket(dateISO);
    b.producao.push({ tipo: "CH", abo: "O", rh: "+", qtd: 0 });
    storeSave();
    renderProd(dateISO);
  }
  function addLib(dateISO) {
    const b = bucket(dateISO);
    b.liberacao.push({ tipo: "CH", abo: "O", rh: "+", qtd: 0, dataProd: dateISO });
    storeSave();
    renderLib(dateISO);
  }
  function addTransf(dateISO) {
    const firstOrig = Object.keys(MAP_TRANSF)[0];
    const firstDest = Array.from(MAP_TRANSF[firstOrig] || [])[0] || "";
    const b = bucket(dateISO);
    b.transformacoes.push({ origemTipo: firstOrig, destTipo: firstDest, abo: "O", rh: "+", qtdOrigem: 0, qtdDestino: 0, obs: "" });
    storeSave();
    renderTransf(dateISO);
  }
  function addDesp(dateISO) {
    const b = bucket(dateISO);
    b.desprezo.push({ tipo: "CH", abo: "O", rh: "+", qtd: 0, motivo: "" });
    storeSave();
    renderDesp(dateISO);
  }
  function addInv(dateISO) {
    const b = bucket(dateISO);
    b.inventario.push({ tipo: "CH", abo: "O", rh: "+", delta: 0, obs: "" });
    storeSave();
    renderInv(dateISO);
  }

  // ==========================================================
  // Delegação de eventos (resolve travas e submit)
  // ==========================================================
  function wireDefaults() {
    $("#dtProducao") && ($("#dtProducao").value = todayISO());
    $("#dtLiberacao") && ($("#dtLiberacao").value = todayISO());
    $("#dtTransf") && ($("#dtTransf").value = todayISO());
    $("#dtDesp") && ($("#dtDesp").value = todayISO());
    $("#dtInv") && ($("#dtInv").value = todayISO());
  }

  function wireDelegation() {
    document.addEventListener("click", (e) => {
      const t = e.target;

      // ---- submenu troca seção (aceita .subnav-item ou .nav-sub-item)
      const subBtn = t.closest(".subnav-item, .nav-sub-item");
      if (subBtn) {
        e.preventDefault();
        e.stopPropagation();
        const sub = normSub(
          subBtn.dataset.sub || subBtn.dataset.subview || subBtn.getAttribute("data-sub") || subBtn.getAttribute("data-subview") || ""
        );
        if (sub) showSub(sub);
        return;
      }

      // ---- PRODUÇÃO
      if (t.closest("#btnBuscarProd")) {
        e.preventDefault();
        const dt = $("#dtProducao")?.value || todayISO();
        renderProd(dt);
        forceEditorVisible("#editorProd", "#vazioProd", "#listaProd", "Nenhum lançamento. Clique em “Inserir hemocomponente produzido”.");
        pill($("#statusProd"), "Produção carregada.", "info");
        setTimeout(() => hidePill($("#statusProd")), 900);
        return;
      }

      if (t.closest("#btnSalvarProd")) {
        e.preventDefault();
        storeSave();
        pill($("#statusProd"), "Produção salva.", "ok");
        setTimeout(() => hidePill($("#statusProd")), 1200);
        return;
      }
      if (t.closest("#btnAddProd")) {
        e.preventDefault();
        const dt = $("#dtProducao")?.value || todayISO();
        addProd(dt);
        forceEditorVisible("#editorProd", "#vazioProd", "#listaProd", ""); // garante visível
        return;
      }

      // ---- LIBERAÇÃO
      if (t.closest("#btnBuscarLib")) {
        e.preventDefault();
        const dt = $("#dtLiberacao")?.value || todayISO();
        renderLib(dt);
        forceEditorVisible("#editorLib", "#vazioLib", "#listaLib", "Nenhum lançamento. Clique em “Inserir liberação”.");
        pill($("#statusLib"), "Liberação carregada.", "info");
        setTimeout(() => hidePill($("#statusLib")), 900);
        return;
      }

      if (t.closest("#btnSalvarLib")) {
        e.preventDefault();
        storeSave();
        pill($("#statusLib"), "Liberação salva.", "ok");
        setTimeout(() => hidePill($("#statusLib")), 1200);
        return;
      }
      if (t.closest("#btnAddLib")) {
        e.preventDefault();
        const dt = $("#dtLiberacao")?.value || todayISO();
        addLib(dt);
        forceEditorVisible("#editorLib", "#vazioLib", "#listaLib", "");
        return;
      }

      // ---- TRANSFORMAÇÕES
      if (t.closest("#btnBuscarTransf")) {
        e.preventDefault();
        const dt = $("#dtTransf")?.value || todayISO();
        renderTransf(dt);
        forceEditorVisible("#editorTransf", "#vazioTransf", "#listaTransf", "Nenhum lançamento. Clique em “Inserir transformação”.");
        pill($("#statusTransf"), "Transformações carregadas.", "info");
        setTimeout(() => hidePill($("#statusTransf")), 900);
        return;
      }

      if (t.closest("#btnSalvarTransf")) {
        e.preventDefault();
        storeSave();
        pill($("#statusTransf"), "Transformações salvas.", "ok");
        setTimeout(() => hidePill($("#statusTransf")), 1200);
        return;
      }
      if (t.closest("#btnAddTransf")) {
        e.preventDefault();
        const dt = $("#dtTransf")?.value || todayISO();
        addTransf(dt);
        forceEditorVisible("#editorTransf", "#vazioTransf", "#listaTransf", "");
        return;
      }

      // ---- DESPREZO
      if (t.closest("#btnBuscarDesp")) {
        e.preventDefault();
        const dt = $("#dtDesp")?.value || todayISO();
        renderDesp(dt);
        forceEditorVisible("#editorDesp", "#vazioDesp", "#listaDesp", "Nenhum lançamento. Clique em “Inserir desprezo”.");
        pill($("#statusDesp"), "Desprezo carregado.", "info");
        setTimeout(() => hidePill($("#statusDesp")), 900);
        return;
      }

      if (t.closest("#btnSalvarDesp")) {
        e.preventDefault();
        storeSave();
        pill($("#statusDesp"), "Desprezo salvo.", "ok");
        setTimeout(() => hidePill($("#statusDesp")), 1200);
        return;
      }
      if (t.closest("#btnAddDesp")) {
        e.preventDefault();
        const dt = $("#dtDesp")?.value || todayISO();
        addDesp(dt);
        forceEditorVisible("#editorDesp", "#vazioDesp", "#listaDesp", "");
        return;
      }

      // ---- INVENTÁRIO
      if (t.closest("#btnBuscarInv")) {
        e.preventDefault();
        const dt = $("#dtInv")?.value || todayISO();
        renderInv(dt);
        forceEditorVisible("#editorInv", "#vazioInv", "#listaInv", "Nenhum lançamento. Clique em “Inserir inventário”.");
        pill($("#statusInv"), "Inventário carregado.", "info");
        setTimeout(() => hidePill($("#statusInv")), 900);
        return;
      }

      if (t.closest("#btnSalvarInv")) {
        e.preventDefault();
        storeSave();
        pill($("#statusInv"), "Inventário salvo.", "ok");
        setTimeout(() => hidePill($("#statusInv")), 1200);
        return;
      }
      if (t.closest("#btnAddInv")) {
        e.preventDefault();
        const dt = $("#dtInv")?.value || todayISO();
        addInv(dt);
        forceEditorVisible("#editorInv", "#vazioInv", "#listaInv", "");
        return;
      }
    }, true); // capture=true (pega antes de submit)
  }

  function boot() {
    bindMenuToggle();
    wireDefaults();
    wireDelegation();

    // Inicia em Produção (e força abrir editor via "buscar")
    showSub("producao");
    $("#btnBuscarProd")?.click();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
