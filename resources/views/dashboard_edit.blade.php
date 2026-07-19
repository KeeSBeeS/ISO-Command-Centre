@extends('layouts.app')
@section('title','Edit Dashboard | ISO Admin')
@section('page_title','Edit My Dashboard')
@section('content')
<style>
    .dash-editor{display:grid;gap:12px}.dash-editor-item{border:1px solid var(--line);background:linear-gradient(180deg,rgba(15,23,42,.065),rgba(15,23,42,.04));border-radius:18px;padding:14px;display:grid;grid-template-columns:auto 1fr;gap:12px;align-items:start;box-shadow:var(--shadow-soft)}.drag-handle{width:44px;height:44px;border-radius:15px;border:1px solid rgba(139,220,101,.22);display:grid;place-items:center;cursor:grab;background:linear-gradient(135deg,rgba(18,163,116,.24),rgba(139,220,101,.08));font-weight:900}.dash-editor-item.dragging{opacity:.55}.dash-editor-controls{display:grid;grid-template-columns:1fr 180px 120px;gap:10px;align-items:end}.dash-editor-controls label{margin:0}.dash-editor-title h3{margin:0 0 4px}.dash-editor-title p{margin:0}.dash-size-note{font-size:12px;color:var(--muted);margin-top:8px}.widget-title-line{display:flex;gap:10px;align-items:flex-start}.widget-title-icon{width:38px;height:38px;border-radius:14px;display:grid;place-items:center;background:rgba(15,23,42,.07);border:1px solid var(--line)}@media(max-width:760px){.dash-editor-item{grid-template-columns:1fr}.drag-handle{width:100%}.dash-editor-controls{grid-template-columns:1fr}}
</style>

<div class="card">
    <div class="page-head" style="margin-bottom:0">
        <div class="page-head-main">
            <div class="page-head-icon">⚙️</div>
            <div>
                <h2>Dashboard Widgets</h2>
                <p>Drag widgets to change order. Resize each widget to small, medium or large. Untick visible to hide a widget from your dashboard.</p>
                <p class="dash-size-note"><strong>Small</strong> shows only the key number. <strong>Medium</strong> shows a short summary. <strong>Large</strong> shows deeper dashboard detail.</p>
            </div>
        </div>
    </div>
</div>
<div style="height:14px"></div>

<form method="post" action="{{ route('dashboard.update') }}" id="dashboard-editor-form">
    @csrf
    @method('PUT')
    <div class="dash-editor" id="dashboard-editor-list">
        @foreach($dashboardWidgets as $widget)
            @php
                $widgetIcon = match($widget['key']) {
                    'company_summary' => '🏢',
                    'quick_actions' => '⚡',
                    'attendance_today' => '⏱️',
                    'employee_documents' => '📄',
                    'vehicles' => '🚗',
                    'fuel_this_month' => '⛽',
                    'vehicle_reminders' => '📎',
                    'vehicle_service_reminders' => '🔧',
                    'access_model' => '🛡️',
                    default => '▣',
                };
            @endphp
            <div class="dash-editor-item" draggable="true" data-widget-key="{{ $widget['key'] }}">
                <div class="drag-handle" title="Drag to reorder">↕</div>
                <div>
                    <input type="hidden" class="sort-input" name="widgets[{{ $widget['key'] }}][sort_order]" value="{{ $widget['sort_order'] }}">
                    <div class="dash-editor-controls">
                        <div class="dash-editor-title">
                            <div class="widget-title-line">
                                <div class="widget-title-icon">{{ $widgetIcon }}</div>
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
    <div class="actions"><button class="btn primary" type="submit">💾 Save Dashboard</button>@if(($quickActionPreferencesReady ?? false) && auth()->user()->hasPermission('dashboard.quick_actions.manage'))<a class="btn" href="{{ route('dashboard.quick_actions.edit') }}">⚡ Edit Shortcuts</a>@endif<a class="btn" href="{{ route('dashboard') }}">Cancel</a></div>
</form>

<script>
(function(){
    var list = document.getElementById('dashboard-editor-list');
    if (!list) return;
    var dragging = null;

    function updateSortInputs(){
        Array.prototype.forEach.call(list.querySelectorAll('.dash-editor-item'), function(item, index){
            var input = item.querySelector('.sort-input');
            if (input) input.value = (index + 1) * 10;
        });
    }

    function getAfterElement(y){
        var items = [].slice.call(list.querySelectorAll('.dash-editor-item:not(.dragging)'));
        return items.reduce(function(closest, child){
            var box = child.getBoundingClientRect();
            var offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            }
            return closest;
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    list.addEventListener('dragstart', function(e){
        dragging = e.target.closest('.dash-editor-item');
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
