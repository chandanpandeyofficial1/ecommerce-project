<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Mini Inventory</title>
    <script>
        try { var t = localStorage.getItem('admin-theme'); if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-bs-theme', t); } catch (e) {}
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/admin.css') }}" rel="stylesheet">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="text-center mb-4">
            <span class="brand-icon mx-auto mb-2" style="width:52px;height:52px;font-size:1.5rem"><i class="bi bi-box-seam-fill" aria-hidden="true"></i></span>
            <h1 class="h4 mb-0">Mini Inventory</h1>
            <div class="text-muted small">Sign in to the admin panel</div>
        </div>
        <div class="card"><div class="card-body p-4">
            @if ($errors->any())
                <div class="alert alert-danger py-2" role="alert"><i class="bi bi-exclamation-octagon-fill me-2" aria-hidden="true"></i>{{ $errors->first() }}</div>
            @endif
            <h2 class="h5 mb-3">Admin login</h2>
            <form method="POST" action="{{ route('admin.login.submit') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required autofocus>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button class="btn btn-primary w-100"><i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>Log in</button>
            </form>
        </div></div>
    </div>
</div>
</body>
</html>
