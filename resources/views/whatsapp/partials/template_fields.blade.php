{{-- Template picker; parameter inputs are generated from the selected template's placeholders. --}}
<div class="form-row">
    <label>Template</label>
    <select name="template_id" class="wa-template-select" {{ ($required ?? true) ? 'required' : '' }}>
        <option value="">{{ ($required ?? true) ? 'Select an approved template' : 'No template — just open the conversation' }}</option>
        @foreach($templates as $template)
            <option value="{{ $template->id }}"
                    data-body="{{ $template->body_text }}"
                    data-body-placeholders='@json($template->bodyPlaceholders())'
                    data-header-placeholders='@json($template->headerPlaceholders())'
                    @selected((string) old('template_id') === (string) $template->id)>{{ $template->name }} ({{ $template->language }} · {{ strtolower((string) $template->category) }})</option>
        @endforeach
    </select>
    @if($templates->isEmpty())
        <p class="muted small">No approved templates yet. Sync them under Templates.</p>
    @endif
</div>
<div class="wa-template-preview muted small" style="white-space:pre-wrap;margin-bottom:10px"></div>
<div class="wa-template-params"></div>
