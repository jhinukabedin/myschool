@props(['icon' => 'info', 'title' => 'Nothing here yet', 'message' => null])

<div class="empty-state">
    <div class="empty-icon"><x-icon :name="$icon" :size="22" /></div>
    <div class="empty-title">{{ $title }}</div>
    @if ($message)<div class="empty-message">{{ $message }}</div>@endif
    @if (! $slot->isEmpty())
        <div style="margin-top:14px">{{ $slot }}</div>
    @endif
</div>
