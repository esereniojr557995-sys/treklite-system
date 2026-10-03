<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in &mdash; Treklite Outdoor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #1e5b31; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body>
    <div class="card shadow border-0" style="width: 100%; max-width: 380px;">
        <div class="card-body p-4">
            <div class="text-center mb-3">
                <h4 class="mb-0">Treklite Outdoor</h4>
                <div class="text-muted small">Sales &amp; Inventory System</div>
            </div>

            @if (session('status'))
                <div class="alert alert-success small">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required autofocus>
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember">
                    <label class="form-check-label small" for="remember">Keep me signed in</label>
                </div>

                <button class="btn btn-success w-100">Log in</button>
            </form>
        </div>
    </div>
</body>
</html>
