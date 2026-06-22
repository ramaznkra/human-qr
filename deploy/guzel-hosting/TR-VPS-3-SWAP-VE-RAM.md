# TR-VPS-3 — Swap sorunu ve swapsız RAM ayarı

Güzel Hosting **Public Cloud (OpenVZ)** VPS'lerde disk üzerinde `/swapfile` oluşturmak bazen **Operation not permitted** veya `fallocate` hatası verir — **buna rağmen swap zaten aktif olabilir** (SolusVM / hypervisor tarafından tanımlı).

Önce kontrol edin; swap görünüyorsa **ek swap oluşturmayın**:

```bash
free -h
swapon --show
```

`Swap` satırında 512M–2G ve `swapon --show` çıktısı varsa **tamamdır**; manual `/swapfile` adımlarını atlayın. Normal TR-VPS-3 profili: FPM `pm.max_children=12`, MySQL `innodb_buffer_pool_size=256M`.

Swap **gerçekten yoksa** (Swap: 0B) aşağıdaki yöntemlere geçin.

## 1. Teşhis (SSH root)

```bash
free -h
swapon --show
df -h /
whoami   # root olmalı
```

Swap satırı yoksa ve `swapon` hata veriyorsa aşağıdaki sırayı deneyin.

## 2. Yöntem A — `dd` ile swap (fallocate çalışmazsa)

```bash
swapoff -a 2>/dev/null
rm -f /swapfile

dd if=/dev/zero of=/swapfile bs=1M count=2048 status=progress
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
swapon --show
```

Kalıcı:

```bash
grep -q '/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
```

## 3. Yöntem B — SolusVM panel (Güzel Hosting)

1. [guzel.net.tr](https://www.guzel.net.tr) → Müşteri paneli → **Public Cloud / VPS**
2. TR-VPS-3 → **Yönetim / SolusVM**
3. **Swap** veya **Resources** bölümünde swap ekleme varsa oradan açın
4. Yoksa destek bileti: *"TR-VPS-3 sunucumda swap açmak istiyorum"*

## 4. Yöntem C — zram (swap dosyası yasaksa, sıkıştırılmış RAM)

Ubuntu 22.04:

```bash
apt update && apt install -y zram-tools
systemctl enable --now zramswap
# veya servis adı: zram-config
free -h
```

`Swap` satırında ~1–2 GB görünmeli (RAM'den ayrılan sıkıştırılmış alan).

## 5. Hiçbiri olmuyorsa — swapsız profil (Human QR Menü)

Swap **yoksa** RAM'i agresif tutun; aksi halde MySQL veya PHP OOM ile düşer.

### PHP 8.3 FPM (aaPanel → PHP → FPM)

| Ayar | Swapsız TR-VPS-3 |
|------|------------------|
| `pm` | dynamic |
| `pm.max_children` | **8** |
| `pm.start_servers` | **2** |
| `pm.min_spare_servers` | **1** |
| `pm.max_spare_servers` | **3** |
| `pm.max_requests` | 500 |

### MySQL (aaPanel → MySQL → Config)

```ini
max_connections = 80
innodb_buffer_pool_size = 128M
performance_schema = OFF
```

Değişiklikten sonra: `systemctl restart mysqld` (veya aaPanel MySQL restart).

### aaPanel / servis sadeleştirme

- Kullanmıyorsanız **FTP**, **Mail** sunucusu kurmayın
- phpMyAdmin yerine gerekince aaPanel DB aracı yeter
- Aynı VPS'te başka site barındırmayın

### Supervisor (Reverb + queue)

Mutlaka çalışsın; polling yükü artmasın. Tek worker yeter:

- `human-reverb` — 1 process
- `human-queue` — `numprocs=1`

### İzleme

```bash
free -h
# Yoğun akşam:
watch -n 5 free -h
```

**available** sürekli 100 MB altına iniyorsa → TR-VPS-4'e yükseltin veya zram/panel swap deneyin.

## 6. Özet

| Durum | Ne yapın |
|-------|----------|
| `dd` + swapon çalıştı | 2 GB swap tamam, normal TR-VPS-3 profili (max_children 12) |
| Sadece zram çalıştı | max_children **10**, MySQL 128M |
| Swap imkansız | max_children **8**, MySQL 128M, izleme şart |
| Akşam çökmeler | TR-VPS-4 upgrade |
