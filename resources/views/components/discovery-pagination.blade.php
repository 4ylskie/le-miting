@if ($paginator->hasPages())
    <nav class="lm-divider lm-actions justify-between pt-(--lm-space-lg) mt-(--lm-space-lg)" aria-label="Menu pagination">
        @if ($paginator->onFirstPage())
            <span class="lm-meta lm-muted">Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="lm-button lm-button--secondary">Previous</a>
        @endif
        <p class="lm-meta order-last w-full text-center sm:order-none sm:w-auto">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</p>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="lm-button lm-button--secondary">Next</a>
        @else
            <span class="lm-meta lm-muted">Next</span>
        @endif
        <div class="lm-actions w-full justify-center" aria-label="Pages">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="lm-meta" aria-hidden="true">{{ $element }}</span>
                @else
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span class="lm-button underline" aria-current="page" aria-label="Page {{ $page }}">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="lm-button lm-button--secondary" aria-label="Go to page {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </div>
    </nav>
@endif
