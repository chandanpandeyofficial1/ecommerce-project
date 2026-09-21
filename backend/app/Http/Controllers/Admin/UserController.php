<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    // Paginated list of customers with search by name or email.
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
        ]);

        // Escape LIKE wildcards so they are searched as plain characters.
        $search = addcslashes((string) $request->input('search'), '%_\\');

        $users = User::withCount(['tokens', 'orders'])
            ->where('role', 'customer')
            ->when($request->filled('search'), function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', ['users' => $users]);
    }

    // Revoke every access token for the given customer, signing them out everywhere.
    public function logoutEverywhere(User $user)
    {
        if ($user->role !== 'customer') {
            return redirect()->route('admin.users.index')->with('error', 'That action is only available for customer accounts.');
        }

        $user->tokens()->delete();

        return back()->with('success', "{$user->name} has been logged out on every device.");
    }

    // Disable or re-enable a customer account. Disabling also revokes all tokens.
    public function toggleActive(User $user)
    {
        if ($user->role !== 'customer') {
            return redirect()->route('admin.users.index')->with('error', 'That action is only available for customer accounts.');
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        if (! $user->is_active) {
            $user->tokens()->delete();

            return back()->with('success', "{$user->name} has been disabled and logged out.");
        }

        return back()->with('success', "{$user->name} has been enabled.");
    }

    // Delete a customer that has no orders. Customers with orders must be disabled instead.
    public function destroy(User $user)
    {
        if ($user->role !== 'customer') {
            return redirect()->route('admin.users.index')->with('error', 'That action is only available for customer accounts.');
        }

        if ($user->orders()->exists()) {
            return back()->with('error', 'Cannot delete a customer with existing orders. Disable the account instead.');
        }

        $user->cartItems()->delete();
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', "{$user->name} has been deleted.");
    }
}
