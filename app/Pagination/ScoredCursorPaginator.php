<?php

namespace App\Pagination;

use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;

/**
 * Cursor paginator for in-memory scored result sets (e.g. discovery).
 *
 * A plain CursorPaginator derives its next cursor from the last item's
 * query-order attributes — meaningless once rows are re-sorted by a
 * PHP-computed score (it produced empty cursors, so clients looped page 1
 * forever). This subclass carries an explicit next-page cursor built by
 * the service (pool-resume position + unscored leftovers), keeping the
 * exact same JSON shape (data/links/meta) for API clients.
 */
class ScoredCursorPaginator extends CursorPaginator
{
    protected ?Cursor $nextPageCursor = null;

    public function __construct($items, $perPage, $cursor = null, array $options = [], ?Cursor $nextPageCursor = null)
    {
        parent::__construct($items, $perPage, $cursor, $options);

        $this->nextPageCursor = $nextPageCursor;
    }

    public function nextCursor(): ?Cursor
    {
        if ($this->nextPageCursor === null) {
            return null;
        }

        if ($this->items->isEmpty()) {
            return null;
        }

        return $this->nextPageCursor;
    }

    public function hasMorePages(): bool
    {
        return $this->nextPageCursor !== null && $this->items->isNotEmpty();
    }
}
