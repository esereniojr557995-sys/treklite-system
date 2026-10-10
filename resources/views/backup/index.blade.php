@extends('layouts.app')

@section('title', 'Database Backup and Restore')

@section('content')
    <h3 class="mb-3"></h3>

    <div class="row g-3">
        <div class="col-lg-7">
            {{-- Create --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header fw-bold"><i class="bi bi-cloud-arrow-down"></i> Create a backup</div>
                <div class="card-body">
                    <p class="mb-2">A backup saves all users, products, variants, stock history and sales in one file.
                        Create one at the end of each business day, then <strong>download it and keep a copy on an external drive</strong>.</p>
                    <form method="POST" action="{{ route('backup.store') }}">
                        @csrf
                        <button class="btn btn-success"><i class="bi bi-database-add"></i> Create backup now</button>
                    </form>
                    <div class="small text-muted mt-2">
                        Product and payment photos are saved separately and are not inside the backup file.
                        Copies stored on the server may be removed when the system is redeployed, so always download one.
                    </div>
                </div>
            </div>

            {{-- Saved backups --}}
            <div class="card shadow-sm">
                <div class="card-header fw-bold">Saved backups on the server</div>
                <table class="table mb-0 align-middle">
                    <thead><tr><th>File</th><th>Created</th><th class="text-end">Size</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($backups as $b)
                            <tr>
                                <td class="small">{{ $b['name'] }}</td>
                                <td class="small">{{ $b['modified']->format('M d, Y h:i A') }}</td>
                                <td class="text-end small">{{ number_format($b['size'] / 1024, 1) }} KB</td>
                                <td class="text-end"><a href="{{ route('backup.download', $b['name']) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download"></i> Download</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No backups yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Restore --}}
        <div class="col-lg-5">
            <div class="card shadow-sm border-danger">
                <div class="card-header bg-danger-subtle fw-bold"><i class="bi bi-exclamation-triangle"></i> Restore from a backup</div>
                <div class="card-body">
                    <div class="alert alert-warning small">
                        Restoring <strong>replaces all current data</strong> with the contents of the backup file.
                        A safety backup of the current data is made first. You will be signed out afterwards.
                    </div>
                    <form method="POST" action="{{ route('backup.restore') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label small mb-1">Backup file (.json)</label>
                            <input type="file" name="backup_file" accept=".json,application/json" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-1">Your password</label>
                            <input type="password" name="password" class="form-control form-control-sm" required autocomplete="current-password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small mb-1">Type <strong>RESTORE</strong> to confirm</label>
                            <input type="text" name="confirmation" class="form-control form-control-sm" required autocomplete="off">
                        </div>
                        <button class="btn btn-danger w-100"
                                onclick="return confirm('Replace ALL current data with this backup?');">Restore database</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
