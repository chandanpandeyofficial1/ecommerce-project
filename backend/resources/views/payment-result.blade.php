<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/admin.css') }}" rel="stylesheet">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="card"><div class="card-body p-4 text-center">
            <i class="bi {{ $icon }} fs-1" aria-hidden="true"></i>
            <h1 class="h5 mt-2">{{ $title }}</h1>
            <p class="text-muted">{{ $message }}</p>
            @isset($appLink)
                <a href="{{ $appLink }}" class="btn btn-primary w-100">Open the app</a>
            @endisset
        </div></div>
    </div>
</div>
@isset($appLink)
<script>
    // Try to switch back to the app on its own; the button covers browsers that block this.
    setTimeout(function () { window.location.href = @json($appLink); }, 800);
</script>
@endisset
</body>
</html>
