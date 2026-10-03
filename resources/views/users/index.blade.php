@extends('layouts.app')

@section('title', 'User Accounts')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">User Accounts</h3>
        <a href="{{ route('users.create') }}" class="btn btn-success">+ Add User</a>
    </div>

    <div class="card shadow-sm">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th class="text-center">Status</th>
                    <th>Created</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $u)
                    <tr class="{{ $u->is_active ? '' : 'text-muted' }}">
                        <td class="fw-semibold">
                            {{ $u->name }}
                            @if ($u->id === auth()->id()) <span class="badge bg-light text-dark border">You</span> @endif
                        </td>
                        <td>{{ $u->email }}</td>
                        <td><span class="badge {{ $u->role === 'owner' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($u->role) }}</span></td>
                        <td class="text-center">
                            @if ($u->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-danger">Deactivated</span>
                            @endif
                        </td>
                        <td class="small">{{ $u->created_at->format('M d, Y') }}</td>
                        <td class="text-end">
                            <a href="{{ route('users.edit', $u) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            @if ($u->id !== auth()->id())
                                <form method="POST" action="{{ route('users.destroy', $u) }}" class="d-inline"
                                      onsubmit="return confirm('Delete the account of {{ addslashes($u->name) }}? This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="small text-muted mt-2">
        A user who already has recorded sales cannot be deleted. Deactivate the account instead (Edit &rarr; Status) so the person can no longer log in.
    </div>
@endsection
