/* UNDERSTOCK · Alertas MongoDB (sin librerías externas) */
(() => {
  'use strict';
  const root = document.querySelector('#alertas.understock-alerts, #alertas-page.understock-alerts');
  if (!root) return;

  const api = root.dataset.api || '../api/alerts';
  const isLocal = api.includes('alertas_local.php');
  const state = { alerts: [], filter: 'todas', busy: false };
  const $ = (s) => root.querySelector(s);
  const asText = (x) => String(x ?? '');
  const lower = (s) => asText(s).toLocaleLowerCase('es');
  const fmt = (x) => new Intl.NumberFormat('es-CO').format(Number(x || 0));

  function report(message, error = false) {
    const el = $('[data-ua-message]');
    if (el) { el.textContent = message; el.classList.toggle('is-error', error); }
  }

  async function request(method, path = '', payload = null) {
    const headers = { 'Accept': 'application/json' };
    if (!isLocal) {
      const token = localStorage.getItem('token');
      if (token) headers.Authorization = token;
    }
    if (method !== 'GET') headers['Content-Type'] = 'application/json';
    const response = await fetch(api + path, {
      method, headers, cache: 'no-store',
      ...(payload === null ? {} : { body: JSON.stringify(payload) })
    });
    let json;
    try { json = await response.json(); }
    catch { throw new Error(`El servidor devolvió una respuesta no válida (${response.status}).`); }
    if (!response.ok || json.status !== 'SUCCESS') {
      throw new Error(json.message || `Error HTTP ${response.status}.`);
    }
    return json.data;
  }

  function textCell(row, value, className = '') {
    const td = row.insertCell();
    if (className) td.className = className;
    td.textContent = asText(value);
    return td;
  }

  function badgeCell(row, label, variant) {
    const td = row.insertCell();
    const badge = document.createElement('span');
    badge.className = `ua-pill ua-pill-${variant}`;
    badge.textContent = label;
    td.appendChild(badge);
    return td;
  }

  function filtered() {
    return state.alerts.filter(a => {
      if (state.filter === 'criticas') return lower(a.prioridad) === 'critica' && a.estado !== 'resuelta';
      if (state.filter === 'pendientes') return a.estado === 'pendiente';
      return true;
    });
  }

  function render() {
    const alerts = state.alerts;
    const critical = alerts.filter(a => lower(a.prioridad) === 'critica' && a.estado !== 'resuelta').length;
    const pending = alerts.filter(a => a.estado === 'pendiente').length;
    const attended = alerts.filter(a => a.estado === 'atendida').length;
    const fields = [
      ['[data-ua-critical]', critical], ['[data-ua-pending]', pending],
      ['[data-ua-attended]', attended], ['[data-ua-all-count]', alerts.length],
      ['[data-ua-critical-count]', critical], ['[data-ua-pending-count]', pending]
    ];
    for (const [selector, val] of fields) { const el = $(selector); if (el) el.textContent = fmt(val); }
    root.querySelectorAll('[data-ua-filter]').forEach(button => {
      const selected = button.dataset.uaFilter === state.filter;
      button.classList.toggle('is-active', selected);
      button.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });

    const tbody = $('[data-ua-rows]');
    if (!tbody) return;
    tbody.replaceChildren();
    const visible = filtered();
    if (visible.length === 0) {
      const row = tbody.insertRow();
      const empty = textCell(row, alerts.length ? 'No hay alertas con este filtro.' : 'Todavía no hay alertas. Usa «Sincronizar inventario».', 'ua-empty');
      empty.colSpan = 7;
      return;
    }

    for (const a of visible) {
      const row = tbody.insertRow();
      const p = row.insertCell();
      const group = document.createElement('div');
      group.className = 'ua-product';
      const emoji = document.createElement('span');
      emoji.className = 'ua-product-icon';
      const name = lower(a.producto);
      emoji.textContent = name.includes('jabón') || name.includes('jabon') ? '🧼'
        : name.includes('patin') ? '🛼' : '📦';
      const title = document.createElement('span');
      title.className = 'ua-product-name';
      title.textContent = asText(a.producto);
      group.append(emoji, title);
      p.appendChild(group);

      textCell(row, fmt(a.stockActual), Number(a.stockActual) <= 0 ? 'ua-stock-zero' : '');
      textCell(row, fmt(a.stockMinimo));
      const pr = lower(a.prioridad);
      badgeCell(row, pr === 'critica' ? '⚠ Crítica' : pr === 'alta' ? '↑ Alta' : '▥ Media',
        pr === 'critica' ? 'danger' : 'warning');
      textCell(row, fmt(a.cantidadSugerida), 'ua-replace-number');
      badgeCell(row, a.estado === 'pendiente' ? '◷ Pendiente' : a.estado === 'atendida' ? '✓ Atendida' : '✓ Resuelta',
        a.estado === 'pendiente' ? 'warning' : a.estado === 'atendida' ? 'success' : 'neutral');

      const actions = row.insertCell();
      if (a.estado === 'pendiente') {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'ua-btn ua-btn-outline';
        button.textContent = '✓ Marcar como atendida';
        button.disabled = state.busy;
        button.addEventListener('click', () => attend(a.id));
        actions.appendChild(button);
      } else {
        actions.textContent = '—';
      }
    }
  }

  async function load() {
    const data = await request('GET');
    state.alerts = Array.isArray(data) ? data : [];
    render();
  }

  function busy(value) {
    state.busy = value;
    const button = $('[data-ua-sync]');
    if (button) { button.disabled = value; button.textContent = value ? 'Sincronizando…' : '↻ Sincronizar inventario'; }
  }

  async function sync() {
    if (state.busy) return;
    busy(true);
    report('Consultando productos de MongoDB…');
    try {
      const result = await request('POST', isLocal ? '' : '/sync');
      await load();
      report(`Sincronización completada · ${fmt(result.productosRevisados)} productos revisados · ${fmt(result.generadas)} nuevas · ${fmt(result.resueltas)} resueltas.`);
    } catch (e) {
      report(e.message, true);
    } finally { busy(false); }
  }

  async function attend(id) {
    if (state.busy) return;
    busy(true);
    report('Guardando atención en MongoDB…');
    try {
      await request('PATCH', '', { id });
      await load();
      report('Alerta marcada como atendida y registrada en el historial.');
    } catch (e) {
      report(e.message, true);
    } finally { busy(false); }
  }

  $('[data-ua-sync]')?.addEventListener('click', sync);
  root.querySelectorAll('[data-ua-filter]').forEach(b => b.addEventListener('click', () => {
    state.filter = b.dataset.uaFilter || 'todas';
    render();
  }));

  // Compatibilidad con showSection() del main.html actual (evita reescribir sus otras vistas).
  window.sincronizarAlertas = sync;
  window.cargarAlertas = async () => { try { await load(); } catch (e) { report(e.message, true); } };
  window.atenderAlerta = attend;
  if (root.id === 'alertas-page') sync();
})();
