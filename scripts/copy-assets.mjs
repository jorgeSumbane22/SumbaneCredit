// Copia as bibliotecas de frontend (instaladas via npm) para public/vendor,
// para que a aplicação funcione sem CDN e sem bundler.
import { cpSync, mkdirSync, existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const nm = (p) => resolve(root, 'node_modules', p);
const out = (p) => resolve(root, 'public/vendor', p);

const copies = [
    ['bootstrap/dist/css/bootstrap.min.css', 'bootstrap/bootstrap.min.css'],
    ['bootstrap/dist/css/bootstrap.min.css.map', 'bootstrap/bootstrap.min.css.map'],
    ['bootstrap/dist/js/bootstrap.bundle.min.js', 'bootstrap/bootstrap.bundle.min.js'],
    ['bootstrap/dist/js/bootstrap.bundle.min.js.map', 'bootstrap/bootstrap.bundle.min.js.map'],
    ['bootstrap-icons/font/bootstrap-icons.min.css', 'bootstrap-icons/bootstrap-icons.min.css'],
    ['bootstrap-icons/font/fonts', 'bootstrap-icons/fonts'],
    ['chart.js/dist/chart.umd.js', 'chartjs/chart.umd.js'],
    ['sweetalert2/dist/sweetalert2.all.min.js', 'sweetalert2/sweetalert2.all.min.js'],
];

for (const [from, to] of copies) {
    if (!existsSync(nm(from))) {
        console.warn(`Ignorado (não encontrado): ${from}`);
        continue;
    }
    mkdirSync(dirname(out(to)), { recursive: true });
    cpSync(nm(from), out(to), { recursive: true });
    console.log(`Copiado: ${from} -> public/vendor/${to}`);
}
