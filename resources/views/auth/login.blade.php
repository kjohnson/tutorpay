<x-layout title="Log in">
    <section class="auth-card card">
        <h1>Log in</h1>
        <form method="POST" action="{{ route('login') }}" class="stack-form">
            @csrf
            <label class="field">
                <span>Email</span>
                <input type="email" name="email" required autofocus autocomplete="username" value="{{ old('email') }}">
            </label>
            <label class="field">
                <span>Password</span>
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <label class="checkbox">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                <span>Keep me logged in</span>
            </label>
            @if ($errors->any())
                <ul class="errors">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
            <div>
                <button type="submit" class="button">Log in</button>
            </div>
        </form>
    </section>
</x-layout>
