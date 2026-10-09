import { resolve } from 'node:path';

export const e2eDatabasePath = resolve(process.cwd(), 'database/e2e.sqlite');

export const e2eDatabaseEnvironment = {
    ...process.env,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: e2eDatabasePath,
};
