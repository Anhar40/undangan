@if ($paginator->hasPages())
    <nav class="admin-pager" aria-label="Navigasi halaman">
        <ul class="admin-pager-list">
            @if ($paginator->onFirstPage())
                <li><span class="admin-pager-link is-disabled" aria-disabled="true" aria-label="Halaman sebelumnya">‹</span></li>
            @else
                <li><a class="admin-pager-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Halaman sebelumnya">‹</a></li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="admin-pager-dots" aria-hidden="true">{{ $element }}</span></li>
                @elseif (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <li><span class="admin-pager-link is-active" aria-current="page">{{ $page }}</span></li>
                        @else
                            <li><a class="admin-pager-link" href="{{ $url }}" aria-label="Halaman {{ $page }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li><a class="admin-pager-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Halaman berikutnya">›</a></li>
            @else
                <li><span class="admin-pager-link is-disabled" aria-disabled="true" aria-label="Halaman berikutnya">›</span></li>
            @endif
        </ul>
    </nav>
@endif
