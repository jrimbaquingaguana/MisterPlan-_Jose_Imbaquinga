export class ApiError extends Error {
    constructor(message, status = 0, fields = {}) {
        super(message);
        this.status = status;
        this.fields = fields;
    }
}

const uncertain = 'No se pudo confirmar el resultado; el servidor podría haber guardado la reserva. Comprueba el listado antes de volver a enviarla.';

export async function requestJson(url, { method = 'GET', data } = {}) {
    let response;
    try {
        response = await fetch(url, {
            method,
            headers: { Accept: 'application/json', ...(data === undefined ? {} : { 'Content-Type': 'application/json' }) },
            ...(data === undefined ? {} : { body: JSON.stringify(data) }),
        });
    } catch {
        throw new ApiError(`Error de red. ${uncertain}`);
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
            500: `Error interno del servidor. ${uncertain}`,
        };
        const fields = body && typeof body.errors === 'object' && !Array.isArray(body.errors) && body.errors !== null
            ? body.errors : {};
        throw new ApiError(messages[response.status] || `Error HTTP ${response.status}. ${uncertain}`, response.status, fields);
    }
    return { status: response.status, body };
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
