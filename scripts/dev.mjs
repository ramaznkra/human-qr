import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const hotFile = path.join(projectRoot, 'public', 'hot');

function removeHotFile() {
    try {
        fs.unlinkSync(hotFile);
    } catch {
        /* yoksa sorun değil */
    }
}

function localIpv4Addresses() {
    const addresses = [];
    for (const interfaces of Object.values(os.networkInterfaces())) {
        for (const iface of interfaces ?? []) {
            if (iface.family === 'IPv4' && !iface.internal) {
                addresses.push(iface.address);
            }
        }
    }

    return [...new Set(addresses)];
}

const ips = localIpv4Addresses();
console.log('\n  Human QR Menu — geliştirme sunucusu');
console.log('  ───────────────────────────────────');
console.log('  Bu PC:     http://127.0.0.1:8000');
if (ips.length) {
    for (const ip of ips) {
        console.log(`  Ağ (LAN):  http://${ip}:8000`);
    }
    console.log('\n  Telefon/tablet için .env → APP_URL ve REVERB_HOST = yukarıdaki IP');
    console.log('  IP değiştirdiyseniz: npm run build');
} else {
    console.log('  Ağ (LAN):  (Wi-Fi/Ethernet IP bulunamadı)');
}
console.log('  Durdurmak: Ctrl+C\n');

removeHotFile();

// PC (localhost) Vite hot dosyası her zaman 127.0.0.1 — LAN IP yazılırsa butonlar/JS kırılır.
// Telefon zaten AppServiceProvider ile public/build kullanır.
const child = spawn('npm', ['run', 'dev:stack:run'], {
    stdio: 'inherit',
    shell: true,
    cwd: projectRoot,
    env: {
        ...process.env,
        VITE_HMR_HOST: '127.0.0.1',
        VITE_DEV_SERVER_URL: 'http://127.0.0.1:5173',
    },
});

let interrupted = false;

process.on('SIGINT', () => {
    interrupted = true;
    child.kill('SIGINT');
});

process.on('SIGTERM', () => {
    interrupted = true;
    child.kill('SIGTERM');
});

child.on('exit', (code, signal) => {
    removeHotFile();

    if (interrupted || signal === 'SIGINT' || signal === 'SIGTERM') {
        console.log('\n  Geliştirme sunucusu durduruldu.\n');
        process.exit(0);
    }

    if (code && code !== 0) {
        console.error(
            `\n  Geliştirme sunucusu hata ile kapandı (kod ${code}).`,
        );
        console.error(
            '  Port meşgul olabilir (8000 / 8080 / 5173). KURULUM.md → Sorun Giderme.\n',
        );
        process.exit(code);
    }

    process.exit(0);
});
