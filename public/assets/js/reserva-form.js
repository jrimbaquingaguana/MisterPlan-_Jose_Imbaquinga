import { createReserva, ApiError } from './api.js';

const form = document.getElementById('reserva-form');
if (form) {
    const fields = ['nombre_huesped', 'email_huesped', 'nombre_alojamiento', 'fecha_entrada', 'fecha_salida', 'importe', 'notas'];
    const controls = Object.fromEntries(fields.map(name => [name, form.elements.namedItem(name)]));
    const submit = form.querySelector('[type="submit"]');
    const another = document.getElementById('create-another');
    const status = document.getElementById('form-status');
    let sending = false;
    let created = false;
    const trimPhp = value => value.replace(/^[\x00\x09-\x0d\x20]+|[\x00\x09-\x0d\x20]+$/g, '');

    function clearErrors() {
        status.replaceChildren();
        status.className = 'full-width';
        for (const name of fields) {
            controls[name].removeAttribute('aria-invalid');
            const error = document.getElementById(`error-${name}`);
            error.textContent = '';
            error.hidden = true;
        }
    }

    function showErrors(errors, message) {
        status.classList.add('error-summary');
        status.textContent = message;
        let first;
        for (const [name, message] of Object.entries(errors)) {
            const text = typeof message === 'string' ? message : 'Valor no válido.';
            if (Object.hasOwn(controls, name)) {
                const error = document.getElementById(`error-${name}`);
                error.textContent = text;
                error.hidden = false;
                controls[name].setAttribute('aria-invalid', 'true');
                first ??= controls[name];
            } else {
                const paragraph = document.createElement('p');
                paragraph.textContent = `${name}: ${text}`;
                status.append(paragraph);
            }
        }
        if (first) first.focus();
    }

    function validDate(value) {
        if (!/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/.test(value)) return false;
        const [year, month, day] = value.split('-').map(Number);
        const leap = year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0);
        return month >= 1 && month <= 12 && day >= 1
            && day <= [31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][month - 1];
    }

    function validate(data) {
        const errors = {};
        for (const name of fields.filter(name => name !== 'notas')) {
            if (data[name] === '') errors[name] = 'Este campo es obligatorio.';
        }
        for (const [name, limit] of Object.entries({ nombre_huesped: 150, email_huesped: 254, nombre_alojamiento: 150, notas: 5000 })) {
            // PHP counts Unicode code points, whereas HTML maxlength counts UTF-16 units.
            if (Array.from(controls[name].value).length > limit) errors[name] = `El texto supera el máximo de ${limit} caracteres.`;
        }
        if (data.email_huesped && controls.email_huesped.validity.typeMismatch) errors.email_huesped = 'El email no tiene un formato válido.';
        for (const name of ['fecha_entrada', 'fecha_salida']) {
            if (data[name] && !validDate(data[name])) errors[name] = 'Introduce una fecha real con año entre 1000 y 9999.';
        }
        if (validDate(data.fecha_entrada) && validDate(data.fecha_salida) && data.fecha_salida <= data.fecha_entrada) {
            errors.fecha_salida = 'La salida debe ser posterior a la entrada.';
        }
        if (data.importe && (!/^[0-9]+(?:\.[0-9]{1,2})?$/.test(data.importe)
            || data.importe.split('.')[0].replace(/^0+/, '').length > 8)) {
            errors.importe = 'Debe ser un importe entre 0 y 99999999.99, con punto y como máximo dos decimales.';
        }
        return errors;
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (sending || created) return;
        clearErrors();
        const data = Object.fromEntries(fields.map(name => [name,
            ['nombre_huesped', 'email_huesped', 'nombre_alojamiento'].includes(name) ? trimPhp(controls[name].value) : controls[name].value]));
        const errors = validate(data);
        if (Object.keys(errors).length) {
            showErrors(errors, 'Revisa los campos indicados.');
            return;
        }
        sending = true;
        submit.disabled = true;
        form.setAttribute('aria-busy', 'true');
        status.textContent = 'Creando reserva…';
        try {
            const reserva = await createReserva(form.action, data);
            created = true;
            status.classList.add('success-message');
            status.textContent = `Reserva creada correctamente. ID: ${reserva.id}. `;
            const link = document.createElement('a');
            link.href = form.dataset.detailBase + String(reserva.id);
            link.textContent = 'Ver detalle de la reserva';
            status.append(link);
            another.hidden = false;
        } catch (error) {
            showErrors(error instanceof ApiError && error.status === 422 ? error.fields : {},
                error instanceof ApiError ? error.message : 'No se pudo confirmar el resultado. Comprueba el listado antes de volver a enviar la reserva.');
        } finally {
            sending = false;
            form.removeAttribute('aria-busy');
            submit.disabled = created;
        }
    });
    another.addEventListener('click', () => {
        if (sending) return;
        form.reset();
        clearErrors();
        created = false;
        another.hidden = true;
        submit.disabled = false;
        controls.nombre_huesped.focus();
    });
    // Custom validation associates every error with its field and focuses the first.
    form.noValidate = true;
    for (const control of Object.values(controls)) control.removeAttribute('maxlength');
    submit.disabled = false;
}
