@if ($paginator->hasPages() || $paginator->total() > 0)
    <div class="pagination-wrap">
        <div class="pagination-info">
            Showing {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }}
        </div>
        @if ($paginator->hasPages())
            <nav class="pagination" aria-label="Pagination">
                @if ($paginator->onFirstPage())
                    <span class="page-link disabled" aria-disabled="true"><x-icon name="chevron-left" :size="13" /></span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" class="page-link" rel="prev" aria-label="Previous page"><x-icon name="chevron-left" :size="13" /></a>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="page-link disabled">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="page-link active" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="page-link">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" class="page-link" rel="next" aria-label="Next page"><x-icon name="chevron-right" :size="13" /></a>
                @else
                    <span class="page-link disabled" aria-disabled="true"><x-icon name="chevron-right" :size="13" /></span>
                @endif
            </nav>
        @endif
    </div>
@endif
