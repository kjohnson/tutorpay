@props(['title'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · TutorPay</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            <a href="{{ route('pay-period.show') }}" class="brand">TutorPay</a>
            @auth
                <nav class="nav">
                    <a href="{{ route('pay-period.show') }}" @class(['active' => request()->routeIs('pay-period.*')])>Calendar</a>
                    <a href="{{ route('schedule.index') }}" @class(['active' => request()->routeIs('schedule.*')])>Schedule</a>
                    <a href="{{ route('settings.edit') }}" @class(['active' => request()->routeIs('settings.*')])>Settings</a>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="nav-button" title="Logged in as {{ auth()->user()->email }}">Log out</button>
                    </form>
                </nav>
            @endauth
        </div>
    </header>

    <main class="container">
        @if (session('status'))
            <div class="flash" role="status">{{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="flash error" role="alert">{{ session('error') }}</div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
