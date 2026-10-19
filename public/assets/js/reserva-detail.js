import { getReserva } from './api.js';
import { node, badge, actions, initActions } from './reserva-actions.js';

const root = document.getElementById('detail-actions');
const history = document.getElementById('detail-history');
const state = document.getElementById('detail-state');
const title = document.getElementById('reserva-title');
let sync;

function render(reserva, button) {
    const hadFocus = document.activeElement === button
        || (button.dataset.restoreFocus === 'true' && document.activeElement === document.body);
    state.replaceChildren(badge(reserva.estado));
    root.replaceChildren(actions(reserva));
    const list = node('ol', undefined, 'history');
    for (const evento of reserva.eventos) {
        const item = node('li');
        const labels = { creada: 'Reserva creada', confirmada: 'Reserva confirmada', cancelada: 'Reserva cancelada' };
        const [date, hour] = evento.fecha_creacion.split(' ');
        const time = node('time', date.split('-').reverse().join('/') + ' ' + hour.slice(0, 5));
        time.dateTime = evento.fecha_creacion.replace(' ', 'T');
        item.append(node('h3', labels[evento.tipo] || evento.tipo), node('p', evento.descripcion), time);
        list.append(item);
    }
    history.replaceChildren(reserva.eventos.length ? list : node('p', 'No hay eventos registrados.'));
    sync();
    if (hadFocus) title.focus();
}

sync = initActions(root, {
    updated: async (reserva, button) => render(reserva, button),
    reconcile: async (id, button) => {
        root.setAttribute('aria-busy', 'true');
        try { render(await getReserva(id), button); }
        catch (error) {
            if (error.status === 404) {
                root.replaceChildren();
                state.textContent = 'Reserva ya no disponible; los demás datos corresponden a la última consulta.';
                title.focus();
            } else throw error;
        } finally { root.setAttribute('aria-busy', 'false'); }
    },
});
