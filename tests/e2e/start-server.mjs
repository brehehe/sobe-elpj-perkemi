import { spawn, spawnSync } from 'node:child_process';
import { closeSync, openSync, rmSync } from 'node:fs';
import { resolve } from 'node:path';

const projectDirectory = process.cwd();
const databasePath = resolve(projectDirectory, 'database/e2e.sqlite');
const environment = {
    ...process.env,
    CACHE_STORE: 'array',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: databasePath,
    QUEUE_CONNECTION: 'database',
    SESSION_DRIVER: 'database',
};

rmSync(databasePath, { force: true });
closeSync(openSync(databasePath, 'w'));

const migration = spawnSync('php', ['artisan', 'migrate:fresh', '--seed', '--no-interaction'], {
    cwd: projectDirectory,
    env: environment,
    stdio: 'inherit',
});

if (migration.status !== 0) {
    process.exit(migration.status ?? 1);
}

const server = spawn('php', ['artisan', 'serve', '--host=127.0.0.1', '--port=8765'], {
    cwd: projectDirectory,
    env: environment,
    stdio: 'inherit',
});
const queue = spawn('php', ['artisan', 'queue:work', '--queue=ai,broadcasts,default', '--sleep=1', '--tries=4', '--timeout=300'], {
    cwd: projectDirectory,
    env: environment,
    stdio: 'inherit',
});
const reverb = spawn('php', ['artisan', 'reverb:start', '--host=127.0.0.1', '--port=8080'], {
    cwd: projectDirectory,
    env: environment,
    stdio: 'inherit',
});
const processes = [server, queue, reverb];
let shuttingDown = false;

const stopProcesses = (signal) => {
    shuttingDown = true;
    processes.forEach((process) => {
        if (!process.killed) {
            process.kill(signal);
        }
    });
};

for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => stopProcesses(signal));
}

server.on('exit', (code) => {
    stopProcesses('SIGTERM');
    process.exit(code ?? 0);
});

for (const processName of [[queue, 'queue worker'], [reverb, 'Reverb server']]) {
    const [child, name] = processName;
    child.on('exit', (code) => {
        if (shuttingDown) {
            return;
        }

        console.error(`${name} exited unexpectedly with code ${code ?? 1}.`);
        stopProcesses('SIGTERM');
        process.exit(code ?? 1);
    });
}
