<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Role;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppBroadcaster;
use App\Services\WhatsApp\WhatsAppSettings;
use Illuminate\Http\Request;
use Throwable;

class WhatsAppBroadcastController extends Controller
{
    public function index(WhatsAppSettings $settings, WhatsAppBroadcaster $broadcaster)
    {
        abort_unless($settings->installed(), 404, 'Run the v2.9.0 update first.');
        $broadcaster->refreshAll();

        $broadcasts = WhatsAppBroadcast::with('creator')
            ->withCount([
                'messages as sent_count' => fn ($query) => $query->whereIn('status', ['sent', 'delivered', 'read']),
                'messages as delivered_count' => fn ($query) => $query->whereIn('status', ['delivered', 'read']),
                'messages as read_count' => fn ($query) => $query->where('status', 'read'),
                'messages as failed_count' => fn ($query) => $query->where('status', 'failed'),
                'messages as queued_count' => fn ($query) => $query->whereIn('status', ['queued', 'sending']),
            ])
            ->latest('id')
            ->paginate(20);

        return view('whatsapp.broadcasts.index', compact('broadcasts'));
    }

    public function create(WhatsAppSettings $settings)
    {
        abort_unless($settings->installed(), 404, 'Run the v2.9.0 update first.');

        return view('whatsapp.broadcasts.create', [
            // Broadcasts collect body parameters only, so templates with header variables are excluded.
            'templates' => WhatsAppTemplate::approved()->orderBy('name')->get()
                ->filter(fn (WhatsAppTemplate $template) => $template->isSendable() && $template->headerPlaceholders() === [])
                ->values(),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'roles' => Role::orderByDesc('level')->get(['id', 'name']),
            'ready' => $settings->ready(),
            'requireOptIn' => $settings->bool('whatsapp_require_opt_in'),
        ]);
    }

    public function preview(Request $request, WhatsAppBroadcaster $broadcaster)
    {
        $data = $this->validated($request);
        $contacts = $broadcaster->audience($data['audience_type'], $this->audienceIds($data));

        return response()->json([
            'count' => $contacts->count(),
            'names' => $contacts->take(12)->map->name_label->values(),
        ]);
    }

    public function store(Request $request, WhatsAppBroadcaster $broadcaster, WhatsAppSettings $settings)
    {
        abort_unless($settings->ready(), 422, 'WhatsApp Business is disabled or not configured.');

        $data = $this->validated($request) + $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'template_id' => ['required', 'integer', 'exists:whatsapp_templates,id'],
            'params' => ['nullable', 'array'],
            'params.*' => ['nullable', 'string', 'max:1024'],
        ]);

        $template = WhatsAppTemplate::findOrFail($data['template_id']);
        abort_unless($template->isSendable() && $template->headerPlaceholders() === [], 422, 'This template cannot be broadcast from the platform.');

        try {
            $broadcast = $broadcaster->start($template, $data['name'], $data['audience_type'], $this->audienceIds($data), array_values($data['params'] ?? []), $request->user());
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['whatsapp' => $exception->getMessage()]);
        }

        if ($broadcast->total_count === 0) {
            return redirect()->route('whatsapp.broadcasts.show', $broadcast)->with('warning', 'No opted-in contacts matched this audience, so nothing was sent.');
        }

        return redirect()->route('whatsapp.broadcasts.show', $broadcast)->with('success', 'Broadcast started for ' . $broadcast->total_count . ' contacts. Remaining messages are sent by the WhatsApp cron job.');
    }

    public function show(WhatsAppBroadcast $broadcast, WhatsAppBroadcaster $broadcaster)
    {
        $broadcaster->refreshStatus($broadcast);

        $messages = $broadcast->messages()->with('contact.user')->orderBy('id')->paginate(50);
        $counts = $broadcast->messages()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('whatsapp.broadcasts.show', ['broadcast' => $broadcast->refresh(), 'messages' => $messages, 'counts' => $counts]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'audience_type' => ['required', 'in:employees,department,role'],
            'department_ids' => ['nullable', 'array', 'required_if:audience_type,department'],
            'department_ids.*' => ['integer'],
            'role_ids' => ['nullable', 'array', 'required_if:audience_type,role'],
            'role_ids.*' => ['integer'],
        ]);
    }

    private function audienceIds(array $data): array
    {
        return array_map('intval', match ($data['audience_type']) {
            'department' => $data['department_ids'] ?? [],
            'role' => $data['role_ids'] ?? [],
            default => [],
        });
    }
}
