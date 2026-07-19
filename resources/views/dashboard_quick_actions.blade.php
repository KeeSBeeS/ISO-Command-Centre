@extends('layouts.app')
@section('title','Edit Quick Actions | ISO Admin')
@section('page_title','Edit Quick Actions')
@section('content')
<style>
    .quick-editor{display:grid;gap:12px}.quick-editor-item{border:1px solid var(--line);background:linear-gradient(180deg,rgba(15,23,42,.065),rgba(15,23,42,.04));border-radius:18px;padding:14px;display:grid;grid-template-columns:auto 1fr;gap:12px;align-items:start;box-shadow:var(--shadow-soft)}.drag-handle{width:44px;height:44px;border-radius:15px;border:1px solid rgba(139,220,101,.22);display:grid;place-items:center;cursor:grab;background:linear-gradient(135deg,rgba(18,163,116,.24),rgba(139,220,101,.08));font-weight:900}.quick-editor-item.dragging{opacity:.55}.quick-editor-controls{display:grid;grid-template-columns:1fr 120px;gap:10px;align-items:center}.quick-title-line{display:flex;gap:10px;align-items:flex-start}.quick-title-icon{width:38px;height:38px;border-radius:14px;display:grid;place-items:center;background:rgba(15,23,42,.07);border:1px solid var(--line)}.quick-title h3{margin:0 0 4px}.quick-title p{margin:0}@media(max-width:760px){.quick-editor-item{grid-template-columns:1fr}.drag-handle{width:100%}.quick-editor-controls{grid-template-columns:1fr}}
</style>

<div class="card">
    <div class="page-head" style="margin-bottom:0">
        <div class="page-head-main">
            <div class="page-head-icon">⚡</div>
            <div>
                <h2>Quick Action Shortcuts</h2>
                <p>Select which shortcut buttons should show on your homepage Quick Actions widget. Available shortcuts are still limited by your permissions.</p>
            </div>
        </div>
    </div>
</div>
<div style="height:14px"></div>

<form method="post" action="{{ route('dashboard.quick_actions.update') }}" id="quick-actions-form">
    @csrf
    @method('PUT')
    <div class="quick-editor" id="quick-actions-list">
        @forelse($quickActions as $action)
            <div class="quick-editor-item" draggable="true" data-action-key="{{ $action['key'] }}">
                <div class="drag-handle" title="Drag to reorder">↕</div>
                <div>
                    <input type="hidden" class="sort-input" name="actions[{{ $action['key'] }}][sort_order]" value="{{ $action['sort_order'] }}">
                    <div class="quick-editor-controls">
                        <div class="quick-title">
                            <div class="quick-title-line">
                                <div class="quick-title-icon">{{ $action['icon'] }}</div>
                                <div>
                                    <h3>{{ $action['label'] }}</h3>
                                    <p class="muted small">{{ $action['description'] }}</p>
                                </div>
                            </div>
                        </div>
                        <label class="check"><input type="checkbox" name="actions[{{ $action['key'] }}][visible]" value="1" @checked($action['is_visible'])><span>Visible</span></label>
                    </div>
                </div>
            </div>
        @empty
            <div class="card"><p class="muted">No quick actions are available for your current permissions.</p></div>
        @endforelse
    </div>
    <div style="height:14px"></div>
    <div class="actions"><button class="btn primary" type="submit">💾 Save Shortcuts</button><a class="btn" href="{{ route('dashboard') }}">Cancel</a></div>
</form>

<script>
(function(){
    var list = document.getElementById('quick-actions-list');
    if (!list) return;
    var dragging = null;

    function updateSortInputs(){
        Array.prototype.forEach.call(list.querySelectorAll('.quick-editor-item'), function(item, index){
            var input = item.querySelector('.sort-input');
            if (input) input.value = (index + 1) * 10;
        });
    }

    function getAfterElement(y){
        var items = [].slice.call(list.querySelectorAll('.quick-editor-item:not(.dragging)'));
        return items.reduce(function(closest, child){
            var box = child.getBoundingClientRect();
            var offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) return { offset: offset, element: child };
            return closest;
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    list.addEventListener('dragstart', function(e){
        dragging = e.target.closest('.quick-editor-item');
        if (dragging) dragging.classList.add('dragging');
    });

    list.addEventListener('dragend', function(){
        if (dragging) dragging.classList.remove('dragging');
        dragging = null;
        updateSortInputs();
    });

    list.addEventListener('dragover', function(e){
        e.preventDefault();
        if (!dragging) return;
        var afterElement = getAfterElement(e.clientY);
        if (afterElement == null) list.appendChild(dragging);
        else list.insertBefore(dragging, afterElement);
    });
})();
</script>
@endsection
