<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Sign In') — {{ \App\Models\AppSetting::appName() }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root{
            --forest:#1a3a2a;--leaf:#2d6a4f;--sage:#52b788;--mist:#d8f3dc;
            --cream:#f7f5f0;--rust:#c0392b;--border:#e5e7eb;--white:#fff;
            --ink:#1c1c1e;--mid:#6b7280;--r:12px;--r-sm:8px;
        }
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        html{font-size:15px;-webkit-font-smoothing:antialiased;}
        body{font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--ink);
             min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}

        .auth-wrap{width:100%;max-width:440px;}

        /* Brand mark */
        .brand{text-align:center;margin-bottom:32px;}
        .brand-icon{width:56px;height:56px;border-radius:14px;background:var(--forest);
            color:var(--mist);display:flex;align-items:center;justify-content:center;
            font-family:'DM Serif Display',serif;font-size:1.3rem;margin:0 auto 12px;}
        .brand h1{font-family:'DM Serif Display',serif;font-size:1.5rem;color:var(--forest);}
        .brand p{font-size:.875rem;color:var(--mid);margin-top:3px;}

        /* Card */
        .auth-card{background:var(--white);border-radius:var(--r);border:1px solid var(--border);
            box-shadow:0 1px 3px rgba(0,0,0,.06),0 8px 24px rgba(0,0,0,.05);padding:28px 32px;}

        /* Form elements */
        .form-group{margin-bottom:18px;}
        .form-label{display:block;font-size:.78rem;font-weight:600;color:var(--mid);
            text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;}
        .form-control{width:100%;padding:11px 14px;border:1.5px solid var(--border);
            border-radius:var(--r-sm);font-size:.9rem;font-family:inherit;
            background:var(--white);color:var(--ink);transition:border-color .15s,box-shadow .15s;}
        .form-control:focus{outline:none;border-color:var(--sage);box-shadow:0 0 0 3px rgba(82,183,136,.15);}
        .form-error{font-size:.78rem;color:var(--rust);margin-top:5px;}

        /* Buttons */
        .btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;
            padding:11px 18px;border-radius:var(--r-sm);font-size:.9rem;font-weight:600;
            cursor:pointer;border:none;text-decoration:none;transition:all .15s;font-family:inherit;}
        .btn-primary{background:var(--forest);color:var(--white);margin-bottom:12px;}
        .btn-primary:hover{background:var(--leaf);}
        .btn-google{background:var(--white);color:var(--ink);border:1.5px solid var(--border);}
        .btn-google:hover{border-color:var(--mid);background:#fafafa;}

        /* Divider */
        .divider{display:flex;align-items:center;gap:12px;margin:20px 0;color:var(--mid);font-size:.8rem;}
        .divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--border);}

        /* Alerts */
        .alert{padding:11px 14px;border-radius:var(--r-sm);font-size:.855rem;margin-bottom:18px;border-left:3px solid;}
        .alert-error{background:#fee2e2;color:#991b1b;border-color:var(--rust);}
        .alert-info{background:#dbeafe;color:#1e40af;border-color:#3b82f6;}
        .alert-success{background:var(--mist);color:var(--forest);border-color:var(--sage);}

        /* Footer link */
        .auth-footer{text-align:center;margin-top:20px;font-size:.855rem;color:var(--mid);}
        .auth-footer a{color:var(--leaf);text-decoration:none;font-weight:500;}
        .auth-footer a:hover{text-decoration:underline;}

        /* Checkbox */
        .check-label{display:flex;align-items:center;gap:8px;font-size:.875rem;color:var(--mid);cursor:pointer;}
        .check-label input{width:16px;height:16px;accent-color:var(--leaf);}

        /* Google icon */
        .google-icon{width:18px;height:18px;flex-shrink:0;}
    </style>
</head>
<body>
<div class="auth-wrap">
    <div class="brand">
        <div class="brand-icon">{{ substr(\App\Models\AppSetting::appName(), 0, 1) }}</div>
        <h1>{{ \App\Models\AppSetting::appName() }}</h1>
        <p>{{ \App\Models\AppSetting::appSubtitle() }}</p>
    </div>

    @if(session('info'))
        <div class="alert alert-info">{{ session('info') }}</div>
    @endif
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="auth-card">
        @yield('content')
    </div>

    <div class="auth-footer">@yield('footer')</div>
</div>
</body>
</html>
