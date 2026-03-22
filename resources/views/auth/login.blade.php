@extends('layouts.auth')
@section('title', 'Sign In')

@section('content')
<h2 style="font-family:'DM Serif Display',serif;font-size:1.2rem;color:var(--forest);margin-bottom:22px;">Sign in to your account</h2>

@if($errors->any())
<div class="alert alert-error">
    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
</div>
@endif

{{-- Google Sign-In --}}
{{-- @if(config('services.google.client_id'))
<a href="{{ route('auth.google') }}" class="btn btn-google">
    <svg class="google-icon" viewBox="0 0 24 24">
        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
    </svg>
    Continue with Google
</a>
<div class="divider">or sign in with email</div>
@endif --}}

<form method="POST" action="{{ route('auth.login.post') }}">
    @csrf
    <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control"
               value="{{ old('email') }}" placeholder="you@example.com"
               autofocus autocomplete="email" required>
    </div>
    <div class="form-group">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control"
               placeholder="••••••••" autocomplete="current-password" required>
    </div>
    <div class="form-group" style="display:flex;align-items:center;justify-content:space-between;">
        <label class="check-label">
            <input type="checkbox" name="remember"> Remember me
        </label>
    </div>
    <button type="submit" class="btn btn-primary">Sign In</button>
</form>
@endsection

@section('footer')
    @if(\App\Models\User::count() === 0)
        No account yet? <a href="{{ route('auth.register') }}">Create the first account</a>
    @endif
@endsection
