# human-social-menu

## Proje açıklaması

Bu projede QR menüden başlayıp kafe içi sipariş, kasa ve garson görev akışını local ortamda geliştirebileceğim bir Laravel uygulaması hazırladım. Amacım tek bir geliştirme ortamında QR siparişten ödeme kaydına kadar ana operasyonu takip edebilmekti.

Proje paylaşım için local geliştirme odaklı tutuldu. Bu README içinde uzak ortam kurulum adımı yoktur.

Bu repo local geliştirme ortamı için hazırlanmıştır. Gerçek POS entegrasyonu aktif değildir. POS sürücüsü varsayılan olarak disabled durumdadır.

## Mevcut özellikler

- QR menü üzerinden masa bazlı sipariş alma
- Kasa ekranında siparişleri onaylama ve takip etme
- Ürün kalemleri için hazırlık durumlarını yönetme
- Garson PWA ekranında teslim görevlerini görme
- Nakit ve manuel kart ödeme kaydı oluşturma
- Ödeme kayıtlarını `payments` tablosunda tutma
- Tam ödeme sonrası sipariş kapanışını yönetme
- SQLite ile local geliştirme
- Reverb ve queue ile anlık bildirim altyapısı

## Sistem akışı

Kısa akış şu şekilde:

1. Kullanıcı QR menüden sipariş verir.
2. Kasa siparişi onaylar.
3. Kasa hazırlık durumunu yönetir.
4. Hazır ürün garson ekranına görev olarak düşer.
5. Garson ürünü teslim eder.
6. Ödeme sadece kasadan alınır.
7. Tam ödeme sonrası sipariş kapanır.

Operasyonun hedef akışı:

```text
QR siparişi
→ Kasa onayı
→ Hazırlanıyor
→ Hazır
→ Garson PWA'ya teslim görevi
→ Garson teslim etti
→ Hesap talebi
→ Ödeme yalnızca kasadan
→ Sipariş kapandı
```

## Kullanılan teknolojiler

- Laravel
- PHP
- Blade
- JavaScript
- Vite
- SQLite
- Laravel Reverb
- Laravel Queue
- PHPUnit / Laravel test altyapısı

## Roller

- **Admin:** yönetim ve kasa işlemlerini yapabilir.
- **Cashier:** kasa merkezli sipariş ve ödeme işlemlerini yönetebilir.
- **Waiter:** garson ekranından görev alabilir ve teslim sürecini tamamlayabilir.

Garson rolü ödeme alamaz, siparişi kapatamaz, fiyat değiştiremez ve POS işlemi başlatamaz.

## Ödeme yapısı

Bu projede nakit ve manuel kart ödeme akışları local test için kullanılabilir. Ödeme kayıtları `payments` tablosunda tutulur. Eski `orders.payment_method` alanı geriye uyumluluk için korunur.

Sipariş yalnızca başarılı ödeme toplamı sipariş toplamına ulaştığında kapanır. Kısmi ödeme varsa sipariş açık kalır.

## POS entegrasyonu notu

Bu projede gerçek POS entegrasyonu aktif değildir. POS sürücüsü local geliştirme ortamında varsayılan olarak disabled durumdadır. Nakit ve manuel kart ödeme akışları local test için kullanılabilir. Gerçek cihaz/SDK olmadan POS bağlantısı aktif edilmemelidir.

## Local kurulum

Komutları Windows PowerShell üzerinden çalıştırıyorum:

```powershell
composer install
npm install
Copy-Item .env.example .env
php artisan key:generate
New-Item database/database.sqlite -ItemType File -Force
php artisan migrate --seed
php artisan storage:link
composer run dev
```

Uygulama localde şu adreste açılır:

```text
http://127.0.0.1:8080
```

Vite geliştirme servisi:

```text
http://127.0.0.1:5173
```

## SQLite kurulumu

Local geliştirme için SQLite kullanıyorum. `.env.example` dosyasında varsayılan bağlantı şu şekildedir:

```env
DB_CONNECTION=sqlite
DB_DATABASE=
```

SQLite dosyasını oluşturmak için:

```powershell
New-Item database/database.sqlite -ItemType File -Force
php artisan migrate --seed
```

`database/database.sqlite` local dosyadır ve repoya eklenmez.

## Tek komutla çalıştırma

Local geliştirme için ana komut:

```powershell
composer run dev
```

Bu komut aynı anda Laravel servisini, queue sürecini, Reverb sürecini ve Vite geliştirme servisini başlatır.

## Manuel çalıştırma

Tek komut çalışmazsa ayrı PowerShell terminallerinde şu komutlar kullanılabilir:

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

## Test ve build komutları

Test:

```powershell
php artisan test
```

Build:

```powershell
npm run build
```

## Sık karşılaşılan local hatalar

### database.sqlite bulunamadı

SQLite dosyası yoksa oluştur:

```powershell
New-Item database/database.sqlite -ItemType File -Force
php artisan migrate --seed
```

### APP_KEY eksik

`.env` içinde `APP_KEY` boşsa:

```powershell
php artisan key:generate
```

### Vite CORS hatası

Uygulamayı `http://127.0.0.1:8080` üzerinden aç. Vite `http://127.0.0.1:5173` üzerinde çalışır. Sorun devam ederse `composer run dev` komutunu kapatıp yeniden başlat.

### Storage görselleri görünmüyor

Storage link eksik olabilir:

```powershell
php artisan storage:link
```

### composer run dev çalışmıyor

Önce bağımlılıkları tekrar kur:

```powershell
composer install
npm install
```

Sonra manuel çalıştırma komutlarını ayrı terminallerde dene.

### Türkçe karakter bozulması

Dosyaları UTF-8 olarak kaydetmek gerekir. Türkçe metinlerde bozulma görürsen önce dosyanın encoding ayarını kontrol et.

## Geliştirme notları

Bu proje local geliştirme için hazırlanmış bir kafe otomasyonu çalışmasıdır. Öncelik QR menü, kasa ve garson operasyonunun stabil ilerlemesidir. Daha sonra gerçek POS entegrasyonu, audit log, iade/iptal ve raporlama gibi alanlar geliştirilebilir.
