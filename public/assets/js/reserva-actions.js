import { changeReservaStatus } from './api.js';

export function node(tag, text, className) {
    const element = document.createElement(tag);
    if (text !== undefined) element.textContent = text;
    if (className) element.className = className;
    return element;
}

export function badge(estado) {
    return node('span', estado[0].toUpperCase() + estado.slice(1), 'estado estado-' + estado);
}

export function actions(reserva) {
    const group = node('span', undefined, 'reservation-actions');
    group.dataset.reservationId = reserva.id;
    const states = reserva.estado === 'pendiente' ? ['confirmada', 'cancelada']
        : reserva.estado === 'confirmada' ? ['cancelada'] : [];
    for (const state of states) {
        const button = node('button', state === 'confirmada' ? 'Confirmar' : 'Cancelar');
        button.type = 'button';
        button.dataset.state = state;
        button.setAttribute('aria-label', `${button.textContent} reserva ${reserva.id} de ${reserva.nombre_huesped}`);
        group.append(button);
    }
    return group;
}

export function initActions(root, { updated, reconcile }) {
    const pending = new Set();
    const status = document.getElementById('action-status');
    function sync() {
        root.querySelectorAll('[data-reservation-id]').forEach(group => {
            group.hidden = false;
            const busy = pending.has(group.dataset.reservationId);
            group.setAttribute('aria-busy', String(busy));
            group.querySelectorAll('button').forEach(button => { button.disabled = busy; });
        });
    }
    root.addEventListener('click', async event => {
        const button = event.target.closest('button[data-state]');
        if (!button || !root.contains(button)) return;
        const id = button.closest('[data-reservation-id]').dataset.reservationId;
        if (pending.has(id) || !window.confirm(`¿${button.textContent} la reserva ${id}?`)) return;
        button.dataset.restoreFocus = String(document.activeElement === button);
        if (button.dataset.restoreFocus === 'true') {
            status.tabIndex = -1;
            status.focus();
        }
        pending.add(id);
        sync();
        status.textContent = `Actualizando reserva ${id}…`;
        let changed = false;
        try {
            const reserva = await changeReservaStatus(id, button.dataset.state);
            changed = true;
            status.textContent = `Estado de la reserva ${id} actualizado.`;
            await updated(reserva, button);
        } catch (error) {
            if (changed) {
                status.textContent = 'Estado actualizado; no se pudo refrescar el listado o la vista. Los datos visibles pueden estar desactualizados.';
            } else {
                status.textContent = error.status === 422
                    ? Object.values(error.fields).filter(value => typeof value === 'string').join(' ') || error.message
                    : error.message || 'No se pudo confirmar el resultado del cambio. Comprueba el estado antes de repetirlo.';
                if ([0, 404, 409].includes(error.status ?? 0)) {
                    try { await reconcile(id, button); }
                    catch { status.textContent += ' No se pudo consultar el estado actual; los datos visibles pueden estar desactualizados.'; }
                }
            }
        } finally {
            pending.delete(id);
            sync();
            if (button.isConnected && button.dataset.restoreFocus === 'true' && document.activeElement === status) button.focus();
        }
    });
    sync();
    return sync;
}
