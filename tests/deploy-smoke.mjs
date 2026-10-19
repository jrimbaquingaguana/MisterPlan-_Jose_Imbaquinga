import assert from 'node:assert/strict';
import { createServer } from 'node:net';
import { spawn, execFileSync } from 'node:child_process';
import { resolve } from 'node:path';

const root = resolve(process.argv[2] || '.');
const env = { ...process.env, APP_ENV: 'production', APP_DEBUG: 'false', APP_PORT: '8080',
    DB_HOST: '127.0.0.1', DB_PORT: '1', DB_NAME: 'synthetic_deploy_test',
    DB_USER: 'synthetic_user', DB_PASSWORD: 'synthetic_password_not_real' };
const config = JSON.parse(execFileSync('php', ['-r',
    'require "vendor/autoload.php"; echo json_encode(require "config/config.php");'], { cwd: root, env, encoding: 'utf8' }));
assert.deepEqual(config.app, { env: 'production', debug: false, port: 8080 });
assert.deepEqual(config.db, { host: env.DB_HOST, port: 1, name: env.DB_NAME, user: env.DB_USER, password: env.DB_PASSWORD });

const reservation = createServer();
await new Promise(done => reservation.listen(0, '127.0.0.1', done));
const port = reservation.address().port;
await new Promise(done => reservation.close(done));
const server = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', 'public', 'public/index.php'],
    { cwd: root, env, windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
let log = '';
let launchError;
server.on('error', error => { launchError = error; });
server.stderr.on('data', data => { log += data; });
const origin = `http://127.0.0.1:${port}`;
try {
    let ready = false;
    for (let attempt = 0; attempt < 100; attempt++) {
        if (launchError) throw launchError;
        try { ready = (await fetch(origin + '/api/health')).ok; } catch {}
        if (ready) break;
        await new Promise(done => setTimeout(done, 50));
    }
    assert.ok(ready, 'Local PHP server started');
    const health = await fetch(origin + '/api/health');
    assert.deepEqual(await health.json(), { status: 'ok' });
    assert.equal((await fetch(origin, { redirect: 'manual' })).status, 302);
    const form = await fetch(origin + '/reservas/nueva');
    assert.equal(form.status, 200);
    assert.ok((await form.text()).includes('Nueva reserva'));
    for (const file of ['api.js', 'reserva-form.js', 'reserva-actions.js', 'reservas-list.js', 'reserva-detail.js']) {
        const asset = await fetch(origin + '/assets/js/' + file);
        assert.equal(asset.status, 200, file);
        assert.match(asset.headers.get('content-type'), /javascript/);
        assert.ok((await asset.text()).length > 100);
    }
    const css = await fetch(origin + '/assets/css/app.css');
    assert.equal(css.status, 200);
    assert.match(css.headers.get('content-type'), /text\/css/);
    for (const path of ['/.env', '/.git/config', '/vendor/autoload.php', '/config/config.php',
        '/src/Database/Connection.php', '/templates/base.html.twig', '/database/misterplan_reservas.sql', '/tests/deploy-smoke.mjs']) {
        assert.equal((await fetch(origin + path)).status, 404, path);
    }
    assert.equal((await fetch(origin + '/api/reservas/0')).status, 404);
    assert.equal((await fetch(origin + '/api/reservas?estado=invalido')).status, 400);
    const failure = await fetch(origin + '/api/reservas');
    assert.equal(failure.status, 500);
    assert.deepEqual(await failure.json(), { error: 'Error interno del servidor' });
    const htmlFailure = await fetch(origin + '/reservas');
    assert.equal(htmlFailure.status, 500);
    const html = await htmlFailure.text();
    assert.ok(html.includes('Error interno del servidor'));
    for (const secret of [env.DB_PASSWORD, env.DB_USER, env.DB_NAME, 'SQLSTATE']) {
        assert.ok(!html.includes(secret) && !log.includes(secret), 'Internal details absent from public output and application logs');
    }
    console.log('OK: environment priority, debug=false, HTTP health/form/assets, private paths, validation and generic 500 responses. No database writes.');
} finally {
    server.kill();
}
