@auth
    @php $profileUser = auth()->user(); @endphp
    <div class="profile-menu" data-profile-menu>
        <button class="profile-toggle" type="button" data-profile-toggle
                aria-haspopup="menu" aria-expanded="false" aria-controls="account-menu"
                aria-label="Menu akun {{ $profileUser->name }}">
            @if ($profileUser->avatar)
                <img class="avatar" src="{{ $profileUser->avatar }}" alt="" width="32" height="32">
            @else
                <span class="avatar-fallback" aria-hidden="true">{{ strtoupper(mb_substr($profileUser->name, 0, 1)) }}</span>
            @endif
            <span class="nav-user profile-name">{{ $profileUser->name }}</span>
            <svg class="profile-caret" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m6 9 6 6 6-6"/>
            </svg>
        </button>

        <div class="profile-menu-panel" id="account-menu" role="menu" data-profile-panel hidden>
            <div class="profile-menu-head">
                <strong>{{ $profileUser->name }}</strong>
                <span>{{ $profileUser->email }}</span>
                <small>{{ $profileUser->role?->label() ?? 'Customer' }}</small>
            </div>
            <a href="{{ route('dashboard') }}" role="menuitem">Dashboard</a>
            <a href="{{ route('transactions.index') }}" role="menuitem">Riwayat Transaksi</a>
            <a href="{{ route('templates.index') }}" role="menuitem">Katalog Template</a>
            @if ($profileUser->isSuperAdmin())
                <div class="profile-menu-sep" role="separator"></div>
                <a href="{{ route('admin.dashboard') }}" role="menuitem">Panel Admin</a>
            @endif
            <div class="profile-menu-sep" role="separator"></div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" role="menuitem">Keluar</button>
            </form>
        </div>
    </div>
@endauth
