@props(['title'])

<!DOCTYPE html>
@php($theme = \App\Theme::current())
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if ($theme !== \App\Theme::System) data-theme="{{ $theme->value }}" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · TutorPay</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            <a href="{{ route('calendar.show') }}" class="brand">TutorPay</a>
            @auth
                <nav class="nav">
                    <a href="{{ route('calendar.show') }}" @class(['active' => request()->routeIs('calendar.*')])>Calendar</a>
                    <a href="{{ route('schedule.index') }}" @class(['active' => request()->routeIs('schedule.*')])>Schedule</a>
                    <a href="{{ route('payments.index') }}" @class(['active' => request()->routeIs('payments.*')])>Payments</a>
                    <a href="{{ route('settings.edit') }}" @class(['active' => request()->routeIs('settings.*')])>Settings</a>
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
