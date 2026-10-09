import { rmSync } from 'node:fs';
import { e2eDatabasePath } from './database';

export default function globalTeardown() {
    rmSync(e2eDatabasePath, { force: true });
}
