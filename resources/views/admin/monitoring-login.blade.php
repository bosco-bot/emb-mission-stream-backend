<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Accès monitoring - EMB Mission</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body{font-family:system-ui,sans-serif;background:#f1f5f9;color:#1e293b;min-height:100vh;display:flex;align-items:center;justify-content:center}
        .login-card{max-width:420px;width:100%;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:2rem}
    </style>
</head>
<body>
<div class="login-card shadow-sm">
    <h1 class="h4 mb-1">Monitoring système</h1>
    <p class="text-secondary mb-4">Entrez le mot de passe pour accéder au tableau de bord.</p>

    @if ($errors->any())
        <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('system-monitoring.login.post') }}">
        @csrf
        <div class="mb-3">
            <label for="password" class="form-label">Mot de passe</label>
            <input type="password" class="form-control" id="password" name="password" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary w-100">Se connecter</button>
    </form>
</div>
</body>
</html>
