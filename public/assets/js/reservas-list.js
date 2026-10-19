import { listReservas } from './api.js';
import { node, badge, actions, initActions } from './reserva-actions.js';

const form = document.getElementById('reserva-filters');
const results = document.getElementById('reservation-results');
const status = document.getElementById('list-status');
const names = ['estado', 'desde', 'hasta', 'q', 'per_page'];
let page = new URLSearchParams(location.search).get('page') || '1';
let controller;
let generation = 0;
let timer;
let syncActions;

function criteria() {
    const params = new URLSearchParams();
    names.forEach(name => params.set(name, form.elements.namedItem(name).value));
    params.set('q', params.get('q').trim());
    params.set('page', page);
    return params;
}

function invalidate() {
    clearTimeout(timer);
    generation++;
    controller?.abort();
    results.setAttribute('aria-busy', 'false');
}

function clearErrors() {
    document.querySelectorAll('[data-filter-errors], .filters .field-error').forEach(element => element.remove());
    form.querySelectorAll('[aria-invalid]').forEach(element => {
        element.removeAttribute('aria-invalid');
        element.removeAttribute('aria-describedby');
    });
}

function valid() {
    clearErrors();
    const desde = form.elements.desde;
    const hasta = form.elements.hasta;
    if (!form.checkValidity() || (desde.value && hasta.value && desde.value > hasta.value)) {
        if (desde.value && hasta.value && desde.value > hasta.value) hasta.setAttribute('aria-invalid', 'true');
        status.textContent = 'Revisa los filtros: hasta debe ser igual o posterior a desde y las fechas deben ser válidas. Los resultados anteriores no corresponden a estos filtros.';
        return false;
    }
    return true;
}

function url(params) { return '/reservas?' + params; }
function stayDate(value) { return value.split('-').reverse().join('/'); }

function render(data, params, focus) {
    const { meta } = data;
    const title = node('h2', 'Resultados');
    title.id = 'resultados-title';
    title.tabIndex = -1;
    const content = [title, node('p', `${meta.total} resultados · Página ${meta.page} de ${Math.max(1, meta.total_pages)}`)];
    if (!data.data.length) content.push(node('p', 'No hay reservas que coincidan con los filtros.', 'empty-state'));
    else {
        const scroll = node('div', undefined, 'table-scroll');
        scroll.tabIndex = 0;
        scroll.setAttribute('role', 'region');
        scroll.setAttribute('aria-label', 'Reservas encontradas');
        const table = node('table');
        table.append(node('caption', 'Reservas encontradas', 'visually-hidden'));
        const head = node('thead');
        const headings = node('tr');
        ['Huésped', 'Alojamiento', 'Entrada', 'Salida', 'Estado', 'Importe', 'Detalle'].forEach(text => {
            const th = node('th', text); th.scope = 'col'; headings.append(th);
        });
        head.append(headings);
        const body = node('tbody');
        for (const reserva of data.data) {
            const row = node('tr');
            const name = node('th', reserva.nombre_huesped); name.scope = 'row'; row.append(name);
            row.append(node('td', reserva.nombre_alojamiento));
            for (const key of ['fecha_entrada', 'fecha_salida']) {
                const cell = node('td'); const time = node('time', stayDate(reserva[key]));
                time.dateTime = reserva[key]; cell.append(time); row.append(cell);
            }
            const state = node('td'); state.append(badge(reserva.estado)); row.append(state);
            row.append(node('td', new Intl.NumberFormat('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(reserva.importe)) + ' €', 'amount'));
            const cell = node('td'); const link = node('a', 'Ver detalle');
            link.href = '/reservas/' + encodeURIComponent(reserva.id) + '?' + params;
            link.setAttribute('aria-label', `Ver reserva ${reserva.id} de ${reserva.nombre_huesped}`);
            cell.append(link, actions(reserva)); row.append(cell); body.append(row);
        }
        table.append(head, body); scroll.append(table); content.push(scroll);
    }
    const nav = node('nav', undefined, 'pagination');
    nav.setAttribute('aria-label', 'Paginación de reservas');
    for (const [target, label, rel] of [[meta.page - 1, 'Anterior', 'prev'], [meta.page + 1, 'Siguiente', 'next']]) {
        if (target < 1 || target > meta.total_pages) continue;
        const query = new URLSearchParams(params); query.set('page', target);
        const link = node('a', label); link.href = url(query); link.rel = rel; nav.append(link);
    }
    content.push(nav);
    const lostFocus = results.contains(document.activeElement);
    results.replaceChildren(...content);
    syncActions();
    if (focus || lostFocus) title.focus();
}

async function load(mode = 'push', focus = false, propagate = false) {
    invalidate();
    if (!valid()) {
        if (propagate) throw new Error('Los filtros vigentes no son válidos.');
        return;
    }
    const current = generation;
    controller = new AbortController();
    const params = criteria();
    results.setAttribute('aria-busy', 'true');
    status.textContent = 'Actualizando resultados… Los resultados visibles aún corresponden a la consulta anterior.';
    try {
        let data = await listReservas(params, controller.signal);
        if (current !== generation) return;
        // The server may return an empty page after a reservation leaves the filter.
        while (data.meta.page > Math.max(1, data.meta.total_pages)) {
            params.set('page', Math.max(1, data.meta.total_pages));
            data = await listReservas(params, controller.signal);
            if (current !== generation) return;
        }
        page = String(data.meta.page);
        params.set('page', page);
        params.set('per_page', data.meta.per_page);
        render(data, params, focus);
        if (mode !== 'none') history[mode === 'push' ? 'pushState' : 'replaceState'](null, '', url(params));
        status.textContent = 'Resultados actualizados.';
    } catch (error) {
        if (current !== generation || error.name === 'AbortError') return;
        for (const [name, message] of Object.entries(error.fields || {})) {
            const control = form.elements.namedItem(name);
            if (control) control.setAttribute('aria-invalid', 'true');
            if (typeof message === 'string') {
                const text = node('p', message, 'field-error'); text.dataset.filterErrors = ''; form.append(text);
            }
        }
        status.textContent = 'No se pudieron actualizar los resultados. Los datos visibles corresponden a la consulta anterior. ' + (error.status === 500 ? 'Error interno del servidor.' : error.message);
        if (propagate) throw error;
    } finally {
        if (current === generation) results.setAttribute('aria-busy', 'false');
    }
}

function changed(mode, debounce = false) {
    invalidate();
    page = '1';
    if (!valid()) return;
    status.textContent = 'Filtros modificados; los resultados visibles están pendientes de actualizar.';
    if (debounce) timer = setTimeout(() => load(mode), 300);
    else void load(mode);
}

form.addEventListener('submit', event => { event.preventDefault(); changed('push'); });
form.elements.q.addEventListener('input', () => changed('replace', true));
names.filter(name => name !== 'q').forEach(name => form.elements.namedItem(name).addEventListener('change', () => changed('push')));
document.querySelector('[data-clear-filters]').addEventListener('click', event => {
    event.preventDefault();
    names.forEach(name => { form.elements.namedItem(name).value = name === 'per_page' ? '10' : ''; });
    changed('push');
});
results.addEventListener('click', event => {
    const link = event.target.closest('.pagination a, .empty-state a');
    if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
    event.preventDefault(); page = new URL(link.href).searchParams.get('page') || '1'; void load('push', true);
});
window.addEventListener('popstate', () => {
    invalidate();
    const params = new URLSearchParams(location.search);
    names.forEach(name => {
        const control = form.elements.namedItem(name);
        const value = params.get(name) || (name === 'per_page' ? '10' : '');
        if (name === 'per_page' && /^\d+$/.test(value) && Number(value) >= 1 && Number(value) <= 100
            && !Array.from(control.options).some(option => option.value === value)) control.add(new Option(value, value));
        control.value = value;
    });
    page = params.get('page') || '1'; void load('none');
});
syncActions = initActions(results, {
    updated: async (reserva, button) => {
        invalidate();
        const hadFocus = results.contains(document.activeElement)
            || (button.dataset.restoreFocus === 'true' && document.activeElement === document.body);
        const title = node('h2', 'Resultados'); title.id = 'resultados-title'; title.tabIndex = -1;
        results.replaceChildren(title, node('p', 'Estado actualizado. Consultando resultados vigentes…'));
        if (hadFocus) title.focus();
        await load('replace', false, true);
    },
    reconcile: async () => { invalidate(); await load('replace', false, true); },
});
// Twig already rendered the initial query; no initial fetch is necessary.
form.noValidate = true;
