@extends('layouts.app')
@section('title','Edit Quick Actions | ISO Admin')
@section('page_title','Edit Quick Actions')
@section('content')
<style>
    .sort-editor{display:grid;gap:10px}.sort-item{display:grid;grid-template-columns:auto 1fr;gap:14px;align-items:center;padding:14px;border:1px solid var(--line);border-radius:var(--radius);background:#fff}.sort-item.dragging{opacity:.55}.sort-handle{display:flex;flex-direction:column;gap:4px}.sort-handle button{width:40px;height:36px;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--text-2);cursor:pointer;display:grid;place-items:center}.sort-handle button:hover{background:var(--panel2)}.sort-handle .grip{cursor:grab;color:var(--muted);text-align:center;font-size:12px}.sort-title{display:flex;gap:12px;align-items:flex-start;min-width:0}.sort-title h3{margin:0 0 2px;font-size:15px}.sort-title p{margin:0}.quick-editor-controls{display:grid;grid-template-columns:minmax(0,1fr) 130px;gap:12px;align-items:center}@media(max-width:760px){.quick-editor-controls{grid-template-columns:1fr}}
</style>

<div class="card">
    <div class="page-head" style="margin-bottom:0">
        <div class="page-head-main">
            <div class="page-head-icon"><x-icon name="arrow-right" /></div>
            <div>
                <h2>Quick action shortcuts</h2>
                <p>Select which shortcut buttons should show on your homepage Quick Actions widget. Available shortcuts are still limited by your permissions.</p>
            </div>
        </div>
    </div>
</div>
<div style="height:14px"></div>

<form method="post" action="{{ route('dashboard.quick_actions.update') }}" id="quick-actions-form">
    @csrf
    @method('PUT')
    <div class="sort-editor" id="quick-actions-list">
        @forelse($quickActions as $action)
            <div class="sort-item" draggable="true" data-action-key="{{ $action['key'] }}">
                <div class="sort-handle"><button type="button" class="move-up" aria-label="Move {{ $action['label'] }} up"><x-icon name="updates" size="16" /></button><button type="button" class="move-down" aria-label="Move {{ $action['label'] }} down" style="transform:rotate(180deg)"><x-icon name="updates" size="16" /></button></div>
                <div>
                    <input type="hidden" class="sort-input" name="actions[{{ $action['key'] }}][sort_order]" value="{{ $action['sort_order'] }}">
                    <div class="quick-editor-controls">
                        <div>
                            <div class="sort-title">
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
    <div class="actions"><button class="btn primary" type="submit">Save shortcuts</button><a class="btn" href="{{ route('dashboard') }}">Cancel</a></div>
</form>

<script>
(function(){
    var list = document.getElementById('quick-actions-list');
    if (!list) return;
    var dragging = null;

    function updateSortInputs(){
        Array.prototype.forEach.call(list.querySelectorAll('.sort-item'), function(item, index){
            var input = item.querySelector('.sort-input');
            if (input) input.value = (index + 1) * 10;
        });
    }

    function getAfterElement(y){
        var items = [].slice.call(list.querySelectorAll('.sort-item:not(.dragging)'));
        return items.reduce(function(closest, child){
            var box = child.getBoundingClientRect();
            var offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) return { offset: offset, element: child };
            return closest;
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    // Buttons for touch screens and keyboards, where dragging doesn't work.
    list.addEventListener('click', function(e){
        var button = e.target.closest('.move-up, .move-down');
        if (!button) return;
        var item = button.closest('.sort-item');
        if (button.classList.contains('move-up') && item.previousElementSibling) list.insertBefore(item, item.previousElementSibling);
        if (button.classList.contains('move-down') && item.nextElementSibling) list.insertBefore(item.nextElementSibling, item);
        updateSortInputs();
        button.focus();
    });

    list.addEventListener('dragstart', function(e){
        dragging = e.target.closest('.sort-item');
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
