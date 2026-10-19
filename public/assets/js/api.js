export class ApiError extends Error {
    constructor(message, status = 0, fields = {}) {
        super(message);
        this.status = status;
        this.fields = fields;
    }
}

const uncertain = 'No se pudo confirmar el resultado; el servidor podría haber guardado la reserva. Comprueba el listado antes de volver a enviarla.';

export async function requestJson(url, { method = 'GET', data, signal } = {}) {
    let response;
    try {
        response = await fetch(url, {
            method,
            signal,
            headers: { Accept: 'application/json', ...(data === undefined ? {} : { 'Content-Type': 'application/json' }) },
            ...(data === undefined ? {} : { body: JSON.stringify(data) }),
        });
    } catch (error) {
        if (error.name === 'AbortError') throw error;
        throw new ApiError(method === 'PATCH'
            ? 'Error de red. No se pudo confirmar el resultado del cambio. Comprueba el estado antes de repetirlo.'
            : method === 'GET' ? 'Error de red al consultar los datos.' : `Error de red. ${uncertain}`);
    }
    let body;
    try {
        body = await response.json();
    } catch {
        throw new ApiError(`La respuesta no contiene JSON válido. ${uncertain}`, response.status);
    }
    if (!response.ok) {
        const messages = {
            400: 'La petición no es válida. Revisa los datos.',
            413: 'La petición es demasiado grande. Reduce el contenido.',
            415: 'El servidor no admite el formato de la petición.',
            422: 'Revisa los campos indicados.',
            404: 'La reserva ya no está disponible.',
            409: 'El estado ha cambiado o la transición no es válida. Se consultará el estado actual.',
            500: `Error interno del servidor. ${uncertain}`,
        };
        const fields = body && typeof body.errors === 'object' && !Array.isArray(body.errors) && body.errors !== null
            ? body.errors : {};
        throw new ApiError(messages[response.status] || `Error HTTP ${response.status}. ${uncertain}`, response.status, fields);
    }
    return { status: response.status, body };
}

function reservation(data) {
    if (!data || !Number.isSafeInteger(data.id) || data.id < 1
        || !['pendiente', 'confirmada', 'cancelada'].includes(data.estado)
        || typeof data.importe !== 'string') throw new ApiError('Respuesta de reserva no válida. No se pudo confirmar el resultado.');
    return data;
}

export async function listReservas(params, signal) {
    const { body } = await requestJson('/api/reservas?' + new URLSearchParams(params), { signal });
    if (!Array.isArray(body?.data) || !body.meta
        || !['page', 'per_page', 'total', 'total_pages'].every(key => Number.isSafeInteger(body.meta[key]))
        || body.meta.page < 1 || body.meta.per_page < 1 || body.meta.total < 0 || body.meta.total_pages < 0) {
        throw new ApiError('Respuesta del listado no válida.');
    }
    body.data.forEach(reservation);
    return body;
}

export async function getReserva(id, signal) {
    const { body } = await requestJson('/api/reservas/' + encodeURIComponent(id), { signal });
    return detail(body?.data);
}

function detail(data) {
    reservation(data);
    if (!Array.isArray(data.eventos)) throw new ApiError('No se pudo confirmar el resultado: historial no válido.');
    return data;
}

export async function changeReservaStatus(id, estado) {
    const { body } = await requestJson('/api/reservas/' + encodeURIComponent(id) + '/estado', {
        method: 'PATCH', data: { estado },
    });
    return detail(body?.data);
}

export async function createReserva(url, data) {
    const result = await requestJson(url, { method: 'POST', data });
    const reserva = result.body?.data;
    if (result.status !== 201 || !reserva || !Number.isSafeInteger(reserva.id)
        || reserva.id < 1 || reserva.id > 4294967295 || reserva.estado !== 'pendiente'
        || typeof reserva.importe !== 'string' || !/^(?:0|[1-9][0-9]{0,7})\.[0-9]{2}$/.test(reserva.importe)) {
        throw new ApiError(`La respuesta de creación tiene una estructura inválida. ${uncertain}`, result.status);
    }
    return reserva;
}
