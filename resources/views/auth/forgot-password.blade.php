@extends('layouts.auth')
@section('title', 'Reset Password')

@section('content')
<h2 style="font-family:'DM Serif Display',serif;font-size:1.2rem;color:var(--forest);margin-bottom:6px;">Reset your password</h2>
<p style="font-size:.855rem;color:var(--mid);margin-bottom:22px;">Enter your registered email and choose a new password.</p>

@if($errors->any())
<div class="alert alert-error">
    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
</div>
@endif

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

<form method="POST" action="{{ route('auth.password.reset.post') }}">
    @csrf
    <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control"
               value="{{ old('email') }}" placeholder="you@example.com"
               autofocus autocomplete="email" required>
        @error('email')<div class="form-error">{{ $message }}</div>@enderror
    </div>
    <div class="form-group">
        <label class="form-label">New Password</label>
        <input type="password" name="password" class="form-control"
               placeholder="At least 8 characters" autocomplete="new-password" required>
        @error('password')<div class="form-error">{{ $message }}</div>@enderror
    </div>
    <div class="form-group">
        <label class="form-label">Confirm New Password</label>
        <input type="password" name="password_confirmation" class="form-control"
               placeholder="Repeat new password" required>
    </div>
    <button type="submit" class="btn btn-primary">Update Password</button>
</form>
@endsection

@section('footer')
    Remembered it? <a href="{{ route('auth.login') }}">Back to sign in</a>
@endsection
