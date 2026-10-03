<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Owner-only user account management (create, edit, deactivate, delete). */
class UserController extends Controller
{
    public function index()
    {
        $users = User::orderByDesc('is_active')->orderBy('role')->orderBy('name')->get();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        return view('users.form', ['user' => new User(['role' => 'staff', 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'role'     => ['required', Rule::in(['owner', 'staff'])],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        User::create($data + ['is_active' => true]);

        return redirect()->route('users.index')->with('status', 'Account created for ' . $data['name'] . '.');
    }

    public function edit(User $user)
    {
        return view('users.form', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role'     => ['required', Rule::in(['owner', 'staff'])],
            'is_active' => ['required', 'boolean'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        $isActive = (bool) $data['is_active'];

        // Nobody can lock themselves out, and there must always be an active Owner.
        if ($user->id === auth()->id() && ($data['role'] !== 'owner' || ! $isActive)) {
            return back()->withInput()->withErrors(['role' => 'You cannot remove your own Owner access or deactivate your own account.']);
        }
        if ($this->wouldRemoveLastOwner($user, $data['role'], $isActive)) {
            return back()->withInput()->withErrors(['role' => 'There must be at least one active Owner account.']);
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        $data['is_active'] = $isActive;

        $user->update($data);

        return redirect()->route('users.index')->with('status', 'Account of ' . $user->name . ' updated.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }
        if ($this->wouldRemoveLastOwner($user, 'staff', false)) {
            return back()->withErrors(['user' => 'There must be at least one active Owner account.']);
        }
        if (Sale::where('user_id', $user->id)->exists() || StockMovement::where('user_id', $user->id)->exists()) {
            return back()->withErrors(['user' => $user->name . ' already has recorded sales or stock changes, so the account cannot be deleted. Deactivate it instead.']);
        }

        $user->delete();

        return redirect()->route('users.index')->with('status', 'Account deleted.');
    }

    private function wouldRemoveLastOwner(User $user, string $newRole, bool $newActive): bool
    {
        $isActiveOwnerNow = $user->role === 'owner' && $user->is_active;
        $staysActiveOwner = $newRole === 'owner' && $newActive;

        if (! $isActiveOwnerNow || $staysActiveOwner) {
            return false;
        }

        return User::where('role', 'owner')->where('is_active', true)->where('id', '!=', $user->id)->doesntExist();
    }
}
