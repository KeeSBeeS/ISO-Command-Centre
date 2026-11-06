@extends('layouts.app')
@section('title','Edit Dashboard | ISO Admin')
@section('page_title','Edit My Dashboard')
@section('content')
<style>
    .sort-editor{display:grid;gap:10px}.sort-item{display:grid;grid-template-columns:auto 1fr;gap:14px;align-items:center;padding:14px;border:1px solid var(--line);border-radius:var(--radius);background:#fff}.sort-item.dragging{opacity:.55}.sort-handle{display:flex;flex-direction:column;gap:4px}.sort-handle button{width:40px;height:36px;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--text-2);cursor:pointer;display:grid;place-items:center}.sort-handle button:hover{background:var(--panel2)}.sort-handle .grip{cursor:grab;color:var(--muted);text-align:center;font-size:12px}.sort-title{display:flex;gap:12px;align-items:flex-start;min-width:0}.sort-title h3{margin:0 0 2px;font-size:15px}.sort-title p{margin:0}.dash-editor-controls{display:grid;grid-template-columns:minmax(0,1fr) 180px 130px;gap:12px;align-items:center}.dash-editor-controls label{margin:0}.dash-size-note{font-size:13px;color:var(--muted);margin-top:8px}@media(max-width:760px){.dash-editor-controls{grid-template-columns:1fr}}
</style>

<div class="card">
    <div class="page-head" style="margin-bottom:0">
        <div class="page-head-main">
            <div class="page-head-icon"><x-icon name="edit" /></div>
            <div>
                <h2>Dashboard widgets</h2>
                <p>Use the arrows (or drag) to change the order. Choose a size for each widget, and untick Visible to hide one.</p>
                <p class="dash-size-note"><strong>Small</strong> shows only the key number. <strong>Medium</strong> shows a short summary. <strong>Large</strong> shows deeper dashboard detail.</p>
            </div>
        </div>
    </div>
</div>
<div style="height:14px"></div>

<form method="post" action="{{ route('dashboard.update') }}" id="dashboard-editor-form">
    @csrf
    @method('PUT')
    <div class="sort-editor" id="dashboard-editor-list">
        @foreach($dashboardWidgets as $widget)
            <div class="sort-item" draggable="true" data-widget-key="{{ $widget['key'] }}">
                <div class="sort-handle"><button type="button" class="move-up" aria-label="Move {{ $widget['title'] }} up"><x-icon name="updates" size="16" /></button><button type="button" class="move-down" aria-label="Move {{ $widget['title'] }} down" style="transform:rotate(180deg)"><x-icon name="updates" size="16" /></button></div>
                <div>
                    <input type="hidden" class="sort-input" name="widgets[{{ $widget['key'] }}][sort_order]" value="{{ $widget['sort_order'] }}">
                    <div class="dash-editor-controls">
                        <div>
                            <div class="sort-title">
                                <div>
                                    <h3>{{ $widget['title'] }}</h3>
                                    <p class="muted small">{{ $widget['description'] }}</p>
                                </div>
                            </div>
                        </div>
                        <label>Size
                            <select name="widgets[{{ $widget['key'] }}][size]">
                                @foreach($sizes as $value => $label)
                                    <option value="{{ $value }}" @selected($widget['size'] === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="check"><input type="checkbox" name="widgets[{{ $widget['key'] }}][visible]" value="1" @checked($widget['is_visible'])><span>Visible</span></label>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <div style="height:14px"></div>
    <div class="actions"><button class="btn primary" type="submit">Save dashboard</button>@if(($quickActionPreferencesReady ?? false) && auth()->user()->hasPermission('dashboard.quick_actions.manage'))<a class="btn" href="{{ route('dashboard.quick_actions.edit') }}">Edit shortcuts</a>@endif<a class="btn" href="{{ route('dashboard') }}">Cancel</a></div>
</form>

<script>
(function(){
    var list = document.getElementById('dashboard-editor-list');
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
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            }
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
