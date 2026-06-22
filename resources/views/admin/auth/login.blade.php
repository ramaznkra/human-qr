<!DOCTYPE html>

<html lang="tr" data-staff-theme="{{ \App\Support\StaffUi::theme() }}">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    @include('partials.site-icons')

    <title>Giriş — {{ $settings['venue_name'] }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    @if(\App\Support\StaffUi::isPremium())

    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&display=swap" rel="stylesheet">

    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>

<body class="admin-login-page flex min-h-screen items-center justify-center bg-[#121110] font-sans antialiased">

    <div class="admin-login-card w-full max-w-md rounded-2xl border border-white/5 bg-[#262220] p-10 shadow-2xl">

        <h1 class="text-center text-2xl font-bold uppercase tracking-[0.2em] text-gray-100">{{ $settings['venue_name'] }}</h1>

        <p class="mt-1 text-center text-xs tracking-widest text-[#E67E22]">Personel Girişi</p>

        @if(session('error'))
        <div class="alert alert-error mt-6">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.login') }}" class="mt-8 space-y-4">

            @csrf

            <div>

                <label class="form-label text-[#D4C5B9]">E-posta</label>

                <input type="email" name="email" value="{{ old('email') }}" required autocomplete="username"

                    placeholder="ornek@human.com"

                    class="form-input max-w-none border-white/10 bg-[#121110] text-gray-100 focus:border-[#E67E22]/40">

            </div>

            <div>

                <label class="form-label text-[#D4C5B9]">Şifre</label>

                <input type="password" name="password" required

                    class="form-input max-w-none border-white/10 bg-[#121110] text-gray-100 focus:border-[#E67E22]/40">

            </div>

            <button type="submit" class="btn btn-primary mt-2 w-full py-3">Giriş Yap</button>

        </form>

    </div>

</body>

</html>

