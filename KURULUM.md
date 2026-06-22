# Local Kurulum

Bu dosya projeyi Windows PowerShell ile local ortamda çalıştırmak için hazırlanmıştır. Burada uzak ortam kurulum adımı yoktur.

## Gereksinimler

- PHP 8.3 veya üzeri
- Composer
- Node.js LTS ve npm
- SQLite PHP eklentisi
- Git

Sürüm kontrolü:

```powershell
php -v
composer -V
node -v
npm -v
```

## Projeyi indirme

```powershell
git clone <repo-url> human-social-menu
cd human-social-menu
```

Zip olarak aldıysan klasörü açıp proje dizinine girmen yeterli.

## Composer bağımlılıkları

```powershell
composer install
```

## NPM bağımlılıkları

```powershell
npm install
```

## .env oluşturma

```powershell
Copy-Item .env.example .env
```

`.env` dosyası local ortam içindir ve repoya eklenmez.

## SQLite veritabanı oluşturma

```powershell
New-Item database/database.sqlite -ItemType File -Force
```

## APP_KEY üretme

```powershell
php artisan key:generate
```

## Migration ve seed

```powershell
php artisan migrate --seed
```

Local veriyi tamamen sıfırlamak istersen:

```powershell
php artisan migrate:fresh --seed
```

Bu komut local SQLite verisini siler. Paylaşılan veya önemli veri bulunan bir ortamda dikkatli kullanılmalıdır.

## Storage link

```powershell
php artisan storage:link
```

## Tek komutla çalıştırma

```powershell
composer run dev
```

Bu komut Laravel, queue, Reverb ve Vite süreçlerini birlikte başlatır.

Adresler:

```text
Laravel: http://127.0.0.1:8080
Vite:    http://127.0.0.1:5173
```

## Manuel çalıştırma

Tek komut çalışmazsa ayrı PowerShell terminallerinde:

```powershell
php artisan serve --host=127.0.0.1 --port=8080
```

```powershell
npm run dev -- --host 127.0.0.1
```

```powershell
php artisan queue:work
```

```powershell
php artisan reverb:start
```

## Test çalıştırma

```powershell
php artisan test
```

## Build alma

```powershell
npm run build
```

## Sık karşılaşılan local hatalar

### database.sqlite bulunamadı

```powershell
New-Item database/database.sqlite -ItemType File -Force
php artisan migrate --seed
```

### APP_KEY eksik

```powershell
php artisan key:generate
```

### Vite CORS hatası

Uygulamayı `http://127.0.0.1:8080` adresinden aç. Vite `http://127.0.0.1:5173` üzerinde çalışır.

### Storage görselleri görünmüyor

```powershell
php artisan storage:link
```

### composer run dev çalışmıyor

```powershell
composer install
npm install
```

Sonra manuel çalıştırma komutlarını ayrı terminallerde dene.

### Türkçe karakter bozulması

Dosyaların UTF-8 olarak kaydedildiğinden emin ol. Türkçe metinler bozulursa önce dosya encoding ayarını kontrol et.

## POS notu

Bu projede gerçek POS entegrasyonu aktif değildir. POS sürücüsü local geliştirme ortamında varsayılan olarak disabled durumdadır. Nakit ve manuel kart ödeme akışları local test için kullanılabilir. Gerçek cihaz/SDK olmadan POS bağlantısı aktif edilmemelidir.

## Geliştirme notu

Bu proje aktif olarak geliştirilmektedir. Öncelik local ortamda stabil çalışan QR menü, kasa ve garson operasyonu oluşturmaktır.
