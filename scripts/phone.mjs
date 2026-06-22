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
const lanIp = ips[0] || '127.0.0.1';

console.log('\n  Human QR Menu — TELEFON / TABLET modu');
console.log('  ─────────────────────────────────────');
console.log('  Vite kapalı → CSS/JS public/build/ üzerinden (telefon uyumlu).');
if (ips.length) {
    for (const ip of ips) {
        console.log(`  Telefon:   http://${ip}:8000`);
    }
} else {
    console.log('  Telefon:   Wi-Fi IP bulunamadı — aynı ağda olduğunuzdan emin olun.');
}
console.log('  .env APP_URL ve REVERB_HOST bu IP ile eşleşmeli.');
console.log('  Durdurmak: Ctrl+C\n');

removeHotFile();

const child = spawn('npm', ['run', 'phone:stack:run'], {
    stdio: 'inherit',
    shell: true,
    cwd: projectRoot,
    env: process.env,
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
        console.log('\n  Telefon modu durduruldu.\n');
        process.exit(0);
    }

    if (code && code !== 0) {
        console.error(`\n  Sunucu hata ile kapandı (kod ${code}).\n`);
        process.exit(code);
    }

    process.exit(0);
});
