@extends('layouts.app')

@php
    $editing = $user->exists;
    $isSelf  = $editing && $user->id === auth()->id();
@endphp

@section('title', $editing ? 'Edit User' : 'Add User')

@section('content')
    <h3 class="mb-3">{{ $editing ? 'Edit User' : 'Add User' }}</h3>

    <div class="card shadow-sm" style="max-width: 560px;">
        <div class="card-body">
            <form method="POST" action="{{ $editing ? route('users.update', $user) : route('users.store') }}">
                @csrf
                @if ($editing) @method('PUT') @endif

                <div class="mb-3">
                    <label class="form-label">Full name</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email (used to log in)</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Role</label>
                    @if ($isSelf)
                        <input type="hidden" name="role" value="owner">
                        <input type="text" class="form-control" value="Owner" disabled>
                    @else
                        <select name="role" class="form-select">
                            <option value="staff" @selected(old('role', $user->role) === 'staff')>Staff &mdash; sales and stock check only</option>
                            <option value="owner" @selected(old('role', $user->role) === 'owner')>Owner &mdash; full access</option>
                        </select>
                    @endif
                </div>

                @if ($editing)
                    <div class="mb-3">
                        <label class="form-label d-block">Status</label>
                        @if ($isSelf)
                            <input type="hidden" name="is_active" value="1">
                            <span class="badge bg-success">Active</span>
                            <span class="small text-muted">You cannot deactivate your own account.</span>
                        @else
                            <input type="hidden" name="is_active" value="0">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active"
                                       @checked(old('is_active', $user->is_active ? 1 : 0))>
                                <label class="form-check-label" for="is_active">Active (can log in)</label>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="mb-3">
                    <label class="form-label">{{ $editing ? 'New password' : 'Password' }}</label>
                    <input type="password" name="password" class="form-control" {{ $editing ? '' : 'required' }} autocomplete="new-password">
                    <div class="form-text">{{ $editing ? 'Leave blank to keep the current password. ' : '' }}At least 8 characters.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Confirm password</label>
                    <input type="password" name="password_confirmation" class="form-control" {{ $editing ? '' : 'required' }} autocomplete="new-password">
                </div>

                <button class="btn btn-success">{{ $editing ? 'Save changes' : 'Create account' }}</button>
                <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
