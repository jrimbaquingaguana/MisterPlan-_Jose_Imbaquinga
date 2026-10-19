// Standalone browser checks with intercepted API responses; no database writes.
// Run: node tests/ui-phase10.mjs (Chrome installed, no npm dependencies).
import { createServer } from 'node:http';
import { execFileSync, spawn } from 'node:child_process';
import { readFileSync, mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import assert from 'node:assert/strict';

const reserva = {
    id: 12, nombre_huesped: '<b>Ana</b>', email_huesped: 'test@example.com',
    nombre_alojamiento: 'Casa', fecha_entrada: '2027-01-01', fecha_salida: '2027-01-02',
    importe: '1234.50', estado: 'pendiente', notas: null, fecha_creacion: '2026-10-19 09:15:00',
    eventos: [{ tipo: 'creada', descripcion: '<b>Creada</b>', fecha_creacion: '2026-10-19 09:15:00' }],
    detail_url: '/reservas/12?page=1&per_page=2',
};
function twig(template, context) {
    return execFileSync('php', ['-r',
        'require "vendor/autoload.php"; $v=json_decode(stream_get_contents(STDIN),true); App\\Http\\HtmlResponse::create()->render($v["template"],$v["context"]);'],
    { input: JSON.stringify({ template, context }), encoding: 'utf8' });
}
const list = twig('reservas/list.html.twig', {
    values: { estado: '', desde: '', hasta: '', q: '', page: '1', per_page: '2' }, errors: [],
    per_page_options: [2, 10, 25, 50, 100], reservas: [reserva],
    meta: { page: 1, per_page: 2, total: 3, total_pages: 2 },
    previous_url: null, next_url: '/reservas?page=2&per_page=2', valid_page_url: null,
});
const detail = twig('reservas/show.html.twig', { reserva, back_url: '/reservas?page=1&per_page=2' });
const creation = twig('reservas/new.html.twig', { api_url: '/api/reservas', detail_base_url: '/reservas/' });
const errorPage = twig('error.html.twig', { status: 404, message: 'Reserva no encontrada' });
const server = createServer((req, res) => {
    if (req.url.startsWith('/api/')) { res.writeHead(500); res.end('Unexpected real API access'); return; }
    if (req.url.startsWith('/assets/')) {
        const path = req.url.split('?')[0];
        if (!/^\/assets\/(js|css)\/[a-z.-]+$/.test(path)) { res.writeHead(404).end(); return; }
        res.setHeader('Content-Type', path.endsWith('.js') ? 'text/javascript' : 'text/css');
        res.end(readFileSync(resolve('public' + path))); return;
    }
    res.setHeader('Content-Type', 'text/html; charset=utf-8');
    res.end(req.url === '/error' ? errorPage : req.url.startsWith('/reservas/nueva') ? creation : /^\/reservas\/12/.test(req.url) ? detail : list);
});
await new Promise(done => server.listen(0, '127.0.0.1', done));
const origin = `http://127.0.0.1:${server.address().port}`;
const profile = resolve('.phpunit.cache/phase10-chrome');
mkdirSync(profile, { recursive: true });
const chrome = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const browser = spawn(chrome, ['--headless=new', '--disable-gpu', '--no-first-run',
    '--disable-background-timer-throttling', '--disable-renderer-backgrounding',
    '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], { windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
let socket;
try {
    const endpoint = await new Promise((done, reject) => {
        let output = '';
        const timeout = setTimeout(() => reject(new Error('Chrome startup timed out')), 15000);
        browser.once('error', reject);
        browser.stderr.on('data', chunk => {
            output += chunk;
            const match = output.match(/DevTools listening on (ws:\/\/[^\s]+)/);
            if (match) { clearTimeout(timeout); done(match[1]); }
        });
    });
    const target = await fetch(endpoint.replace('ws:', 'http:').replace(/\/devtools\/browser\/.*/, '/json/list')).then(r => r.json());
    socket = new WebSocket(target.find(item => item.type === 'page').webSocketDebuggerUrl);
    await new Promise(done => socket.addEventListener('open', done, { once: true }));
    let sequence = 0;
    const pending = new Map();
    socket.addEventListener('message', event => {
        const message = JSON.parse(event.data);
        if (pending.has(message.id)) { pending.get(message.id)(message); pending.delete(message.id); }
    });
    const consoleErrors = [];
    socket.addEventListener('message', event => {
        const message = JSON.parse(event.data);
        if (message.method === 'Runtime.exceptionThrown'
            || (message.method === 'Runtime.consoleAPICalled' && message.params.type === 'error')) consoleErrors.push(message.params);
    });
    async function cdp(method, params = {}) {
        const id = ++sequence;
        const answer = new Promise((done, reject) => {
            const timeout = setTimeout(() => { pending.delete(id); reject(new Error('CDP timed out: ' + method)); }, 20000);
            pending.set(id, value => { clearTimeout(timeout); done(value); });
        });
        socket.send(JSON.stringify({ id, method, params }));
        const result = await answer;
        if (result.error) throw new Error(JSON.stringify(result.error));
        return result.result;
    }
    async function evaluate(expression) {
        const result = await cdp('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
        if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
        return result.result.value;
    }
    async function navigate(path) {
        await cdp('Page.navigate', { url: origin + path });
        for (let i = 0; i < 100; i++) {
            await new Promise(done => setTimeout(done, 50));
            if (await evaluate(`document.readyState === 'complete' && location.pathname === ${JSON.stringify(path.split('?')[0])}`)) break;
        }
        await new Promise(done => setTimeout(done, 100));
    }
    const setup = `
        window.testReserva = ${JSON.stringify(reserva)};
        window.calls = []; window.mode = ''; window.readFailure = false; window.rejectConfirm = false;
        window.confirm = () => !window.rejectConfirm;
        window.fetch = async (input, options = {}) => {
            const url = new URL(input, location.href);
            const method = options.method || 'GET';
            calls.push({ method, query: url.search, path: url.pathname });
            if (method === 'PATCH') {
                await new Promise(done => setTimeout(done, 80));
                if (mode === 'network') throw new TypeError('Simulated network failure');
                if (mode === 'conflict') { testReserva.estado = 'cancelada'; return new Response('{}', { status: 409 }); }
                testReserva.estado = JSON.parse(options.body).estado;
                testReserva.eventos.push({ tipo: testReserva.estado, descripcion: '<i>Cambio</i>', fecha_creacion: '2026-10-19 10:00:00' });
                if (mode === 'refreshFailure') readFailure = true;
                return Response.json({ data: testReserva });
            }
            if (method === 'POST') {
                if (mode === 'validation') return Response.json({ error: 'Datos inválidos', errors: { email_huesped: 'Email no válido.' } }, { status: 422 });
                return Response.json({ data: { ...testReserva, estado: 'pendiente' } }, { status: 201 });
            }
            if (/\\/api\\/reservas\\/12$/.test(url.pathname)) return Response.json({ data: testReserva });
            const q = url.searchParams.get('q') || '';
            // Deliberately ignore AbortSignal to exercise the generation guard too.
            await new Promise(done => setTimeout(done, q === 'slow' ? 650 : q === 'medium' ? 600 : 20));
            if (readFailure) return new Response('{}', { status: 500 });
            const estado = url.searchParams.get('estado');
            const total = estado && estado !== testReserva.estado ? 0 : 3;
            const per_page = Number(url.searchParams.get('per_page') || 2);
            const page = Number(url.searchParams.get('page') || 1);
            return Response.json({ data: total && page <= Math.ceil(total / per_page)
                ? [{ ...testReserva, nombre_huesped: q || testReserva.nombre_huesped }] : [],
                meta: { page, per_page, total, total_pages: Math.ceil(total / per_page) } });
        };
        window.wait = ms => new Promise(done => setTimeout(done, ms));
        window.check = (condition, label) => { if (!condition) throw new Error(label); };
        window.change = (name, value, type = 'change') => {
            const control = document.querySelector('[name="' + name + '"]');
            control.value = value; control.dispatchEvent(new Event(type, { bubbles: true }));
        };
    `;
    console.log('Chrome connected; opening fixture');
    await cdp('Runtime.enable');
    await navigate('/reservas');
    console.log('Fixture loaded');
    await evaluate(setup);
    console.log('API interception installed');
    const listChecks = await evaluate(`(async () => {
        check(calls.length === 0, 'No initial duplicate query');
        change('estado', 'pendiente'); change('desde', '2027-01-01'); change('hasta', '2027-01-02');
        await wait(100);
        check(location.search.includes('estado=pendiente') && location.search.includes('hasta=2027-01-02'), 'Combined filters and URL');
        document.querySelector('.pagination a[rel=next]').click(); await wait(100);
        check(location.search.includes('page=2'), 'Pagination');
        document.querySelector('[data-clear-filters]').click(); await wait(100);
        check(document.querySelector('[name=estado]').value === '' && location.search.includes('page=1'), 'Clear filters');
        history.back(); await wait(150);
        check(document.querySelector('[name=estado]').value === 'pendiente' && location.search.includes('page=2'), 'Back restores filters');
        history.forward(); await wait(150);
        check(document.querySelector('[name=estado]').value === '', 'Forward restores filters');
        change('q', 'slow', 'input'); await wait(320);
        change('q', 'medium', 'input'); await wait(320);
        await wait(350);
        check(document.querySelector('#reservation-results').getAttribute('aria-busy') === 'true', 'Old response cannot stop current loader');
        await wait(300);
        check(document.querySelector('tbody th').textContent === 'medium', 'Old response cannot replace newer query');
        change('q', 'slow', 'input'); await wait(320);
        change('desde', '2027-02-01'); change('hasta', '2027-01-01'); await wait(700);
        check(document.querySelector('tbody th').textContent === 'medium' && document.querySelector('#list-status').textContent.includes('Revisa'), 'Invalid criteria invalidate reads');
        document.querySelector('[data-clear-filters]').click(); await wait(100);
        check(document.querySelector('tbody th').textContent === '<b>Ana</b>' && !document.querySelector('tbody th b'), 'Safe text');
        rejectConfirm = true; document.querySelector('[data-state=confirmada]').click(); await wait(100);
        check(!calls.some(call => call.method === 'PATCH'), 'Rejected confirmation sends no PATCH');
        rejectConfirm = false;
        const button = document.querySelector('[data-state=confirmada]'); button.click(); button.click();
        check([...document.querySelectorAll('[data-state]')].every(button => button.disabled), 'All actions locked');
        await wait(180);
        check(calls.filter(call => call.method === 'PATCH').length === 1, 'Double click writes once');
        change('estado', 'confirmada'); await wait(100);
        change('per_page', '2'); await wait(100);
        document.querySelector('.pagination a[rel=next]').click(); await wait(100);
        document.querySelector('[data-state=cancelada]').click(); await wait(200);
        check(!document.querySelector('tbody') && document.querySelector('#reservation-results').textContent.includes('0 resultados') && location.search.includes('page=1'), 'Cancelled last row resets out-of-range page');
        testReserva.estado = 'pendiente'; document.querySelector('[data-clear-filters]').click(); await wait(100);
        document.querySelector('[data-state=confirmada]').click();
        change('per_page', '2'); await wait(40);
        check(document.querySelectorAll('[data-state]').length > 0 && [...document.querySelectorAll('[data-state]')].every(button => button.disabled), 'Lock survives table render');
        change('estado', 'confirmada');
        await wait(160);
        check(location.search.includes('estado=confirmada') && document.querySelector('.estado').textContent === 'Confirmada', 'PATCH refresh uses current criteria');
        testReserva.estado = 'pendiente'; document.querySelector('[data-clear-filters]').click(); await wait(100);
        mode = 'refreshFailure'; document.querySelector('[data-state=confirmada]').click(); await wait(200);
        check(document.querySelector('#action-status').textContent.includes('Estado actualizado; no se pudo refrescar'), 'Refresh failure preserves write success');
        readFailure = false; mode = ''; testReserva.estado = 'pendiente';
        document.querySelector('[data-clear-filters]').click(); await wait(100);
        mode = 'conflict'; document.querySelector('[data-state=confirmada]').click(); await wait(200);
        check(document.querySelector('.estado').textContent === 'Cancelada' && document.querySelector('#action-status').textContent.includes('transición'), '409 reconciles');
        testReserva.estado = 'pendiente'; mode = ''; document.querySelector('[data-clear-filters]').click(); await wait(100);
        mode = 'network'; const writes = calls.filter(call => call.method === 'PATCH').length;
        document.querySelector('[data-state=confirmada]').click(); await wait(250);
        check(calls.filter(call => call.method === 'PATCH').length === writes + 1 && document.querySelector('#action-status').textContent.includes('No se pudo confirmar'), 'Network failure never retries PATCH');
        readFailure = true; change('per_page', '2'); await wait(100);
        check(document.querySelector('tbody') && document.querySelector('#list-status').textContent.includes('consulta anterior'), 'Failed read preserves table with stale notice');
        return 'List: filters, pagination, history, races, validation, safe text, confirmation, locking, refresh failure, conflict, network passed';
    })()`);
    console.log(listChecks);
    await navigate('/reservas/12'); await evaluate(setup);
    assert.equal(await evaluate(`(async () => {
        document.querySelector('[data-state=confirmada]').focus(); document.querySelector('[data-state=confirmada]').click(); await wait(200);
        check(document.querySelectorAll('#detail-history li').length === 2 && !document.querySelector('#detail-history i'), 'History replaced with safe text');
        check(document.activeElement.id === 'reserva-title', 'Focus restored');
        document.querySelector('[data-state=cancelada]').click(); await wait(200);
        check(document.querySelectorAll('#detail-history li').length === 3 && !document.querySelector('[data-state]'), 'History has no duplicates and actions updated');
        return true;
    })()`), true);
    console.log('Detail: state, history, safe text, focus passed');
    await navigate('/reservas/nueva'); await evaluate(setup);
    assert.equal(await evaluate(`(async () => {
        for (const [name, value] of Object.entries({ nombre_huesped: 'Test', email_huesped: 'test@example.com', nombre_alojamiento: 'Casa', fecha_entrada: '2027-01-01', fecha_salida: '2027-01-02', importe: '12.50' })) document.querySelector('[name=' + name + ']').value = value;
        mode = 'validation'; document.querySelector('#reserva-form').requestSubmit(); await wait(100);
        check(document.activeElement.name === 'email_huesped' && document.activeElement.getAttribute('aria-invalid') === 'true'
            && document.querySelector('[name=nombre_huesped]').value === 'Test', 'Server errors preserve data and focus field');
        mode = ''; document.querySelector('#reserva-form').requestSubmit(); document.querySelector('#reserva-form').requestSubmit(); await wait(150);
        check(calls.filter(call => call.method === 'POST').length === 2 && document.querySelector('#form-status').textContent.includes('Reserva creada correctamente'), 'Creation prevents double submission');
        check(!document.querySelector('[name=email_huesped]').hasAttribute('aria-invalid'), 'Creation clears previous errors');
        return true;
    })()`), true);
    console.log('Phase 9 creation: passed (intercepted POST)');
    // Phase 11: behavior checks in the existing dependency-free browser harness.
    for (const width of [320, 375, 768, 1280]) {
        await cdp('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false });
        for (const path of ['/reservas', '/reservas/nueva', '/reservas/12', '/error']) {
            await navigate(path);
            assert.equal(await evaluate(`(() => {
                const long = 'Texto'.repeat(30);
                for (const el of document.querySelectorAll('tbody th, tbody td:nth-child(2), .details div:nth-child(2) dd, .details div:nth-child(3) dd, .details div:nth-child(4) dd, .history p')) el.textContent = long;
                const notes = document.querySelector('.notes');
                if (notes) notes.textContent = long + '\\n' + long;
                return document.documentElement.scrollWidth <= innerWidth;
            })()`), true, `No document overflow: ${path} at ${width}`);
            const shot = await cdp('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
            writeFileSync(resolve(`.phpunit.cache/phase11-${width}-${path.split('/').filter(Boolean).join('-')}.png`), Buffer.from(shot.data, 'base64'));
        }
    }
    await navigate('/reservas'); await evaluate(setup);
    assert.equal(await evaluate(`(async () => {
        const q = document.querySelector('[name=q]'); q.focus();
        change('q', 'Ana', 'input'); await wait(400);
        check(document.activeElement === q, 'Search preserves focus');
        change('desde', '2027-02-01'); change('hasta', '2027-01-01');
        const end = document.querySelector('[name=hasta]');
        check(end.getAttribute('aria-invalid') === 'true' && document.getElementById(end.getAttribute('aria-describedby')).textContent.includes('Hasta'), 'Filter error associated');
        document.querySelector('[data-clear-filters]').click(); await wait(100);
        check(!end.hasAttribute('aria-invalid') && !end.hasAttribute('aria-describedby'), 'Filter error cleared');
        const button = document.querySelector('[data-state=confirmada]');
        check(button.getAttribute('aria-label').includes('12'), 'Action identifies reservation');
        button.focus(); button.click();
        check(document.activeElement.id === 'action-status', 'Pending action has available focus');
        await wait(200);
        check(document.activeElement.id === 'resultados-title', 'List action restores focus');
        return true;
    })()`), true);
    await navigate('/reservas/nueva'); await evaluate(setup);
    assert.equal(await evaluate(`(() => {
        document.querySelector('form').requestSubmit();
        const first = document.querySelector('[name=nombre_huesped]');
        return document.activeElement === first && first.getAttribute('aria-invalid') === 'true'
            && !document.getElementById('error-nombre_huesped').hidden && calls.length === 0;
    })()`), true);
    await navigate('/reservas');
    await cdp('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
    await cdp('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
    assert.equal(await evaluate(`document.activeElement.classList.contains('skip-link') && document.activeElement.getBoundingClientRect().width > 1`), true);
    await cdp('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13 });
    await cdp('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13 });
    assert.equal(await evaluate(`document.activeElement.id === 'contenido'`), true);
    assert.deepEqual(consoleErrors, [], 'No JavaScript exceptions or console errors');
    console.log('Phase 11: four viewport widths with long text, search focus, associated/cleared filter errors, action focus, invalid creation passed');
    await cdp('Emulation.setScriptExecutionDisabled', { value: true });
    await navigate('/reservas');
    assert.equal(await evaluate(`document.querySelector('form').method === 'get'
        && document.querySelector('.pagination a').getAttribute('href').includes('page=2')
        && document.querySelector('[data-reservation-id]').hidden
        && document.querySelector('tbody a').getAttribute('href').startsWith('/reservas/12?')`), true);
    await evaluate(`document.querySelector('.pagination a').click()`);
    await new Promise(done => setTimeout(done, 150));
    assert.equal(await evaluate(`location.search.includes('page=2')`), true);
    await navigate('/reservas/12');
    assert.equal(await evaluate(`document.querySelector('.history').textContent.includes('<b>Creada</b>') && document.querySelector('[data-reservation-id]').hidden`), true);
    console.log('Without JavaScript: GET form, pagination navigation, detail and hidden actions passed (Twig fixtures)');
    await navigate('/reservas/nueva');
    assert.equal(await evaluate(`document.querySelector('noscript').textContent.includes('requiere JavaScript') && document.querySelector('[type=submit]').disabled`), true);
} finally {
    socket?.close(); browser.kill(); server.closeAllConnections(); server.close();
}
