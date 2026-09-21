@extends('layouts.layout')
@section('title', 'Users')
@section('crumb', 'Users')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Registered customers and their active sessions.</p>
</div>

<div class="card mb-3"><div class="card-body">
    <form method="GET" class="row g-2">
        <div class="col-md-6"><input type="text" name="search" maxlength="100" value="{{ request('search') }}" class="form-control" placeholder="Search by name or email" aria-label="Search by name or email"></div>
        <div class="col-auto"><button class="btn btn-primary"><i class="bi bi-search me-1" aria-hidden="true"></i>Search</button></div>
        <div class="col-auto"><a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Reset</a></div>
    </form>
</div></div>

<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Registered</th><th>Status</th><th>Active sessions</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse ($users as $user)
            <tr>
                <td class="num text-muted">{{ $user->id }}</td>
                <td class="fw-medium">{{ $user->name }}</td>
                <td class="text-muted">{{ $user->email }}</td>
                <td class="text-muted">{{ $user->phone ?: '—' }}</td>
                <td class="text-muted">{{ $user->created_at->format('d M Y') }}</td>
                <td>
                    @if ($user->is_active)
                        <span class="status-badge status-delivered"><i class="bi bi-check2-circle" aria-hidden="true"></i>Active</span>
                    @else
                        <span class="status-badge status-cancelled"><i class="bi bi-slash-circle" aria-hidden="true"></i>Disabled</span>
                    @endif
                </td>
                <td>{{ $user->tokens_count }}</td>
                <td class="text-end text-nowrap">
                    @if ($user->tokens_count > 0)
                        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#logoutModal{{ $user->id }}">
                            <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Log out everywhere
                        </button>

                        <div class="modal fade" id="logoutModal{{ $user->id }}" tabindex="-1" aria-labelledby="logoutModalLabel{{ $user->id }}" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="logoutModalLabel{{ $user->id }}">Log out everywhere</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        Log out {{ $user->name }} on every device? They will need to log in again.
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <form method="POST" action="{{ route('admin.users.logout-everywhere', $user) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-danger">Log out everywhere</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <span class="text-muted">No active sessions</span>
                    @endif

                    @if ($user->is_active)
                        <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#disableModal{{ $user->id }}">
                            <i class="bi bi-slash-circle me-1" aria-hidden="true"></i>Disable
                        </button>

                        <div class="modal fade" id="disableModal{{ $user->id }}" tabindex="-1" aria-labelledby="disableModalLabel{{ $user->id }}" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="disableModalLabel{{ $user->id }}">Disable account</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        Disable this account? They will be logged out immediately and unable to log in until re-enabled.
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <form method="POST" action="{{ route('admin.users.toggle-active', $user) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-warning">Disable</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <form method="POST" action="{{ route('admin.users.toggle-active', $user) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-success btn-sm">
                                <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Enable
                            </button>
                        </form>
                    @endif

                    @if ($user->orders_count > 0)
                        <span class="text-muted ms-1">Has orders</span>
                    @else
                        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal{{ $user->id }}">
                            <i class="bi bi-trash me-1" aria-hidden="true"></i>Delete
                        </button>

                        <div class="modal fade" id="deleteModal{{ $user->id }}" tabindex="-1" aria-labelledby="deleteModalLabel{{ $user->id }}" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="deleteModalLabel{{ $user->id }}">Delete customer</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        Delete this customer? This cannot be undone.
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8"><div class="empty"><i class="bi bi-people" aria-hidden="true"></i>No customers found.</div></td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $users->links('pagination::bootstrap-5') }}</div>
@endsection
