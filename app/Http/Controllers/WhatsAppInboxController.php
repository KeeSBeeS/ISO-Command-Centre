<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppCloudApi;
use App\Services\WhatsApp\WhatsAppMessenger;
use App\Services\WhatsApp\WhatsAppSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class WhatsAppInboxController extends Controller
{
    public function __construct(private WhatsAppSettings $settings)
    {
    }

    public function index(Request $request)
    {
        $this->ensureInstalled();

        return view('whatsapp.inbox', $this->sidebarData($request) + [
            'contact' => null,
            'ready' => $this->settings->ready(),
        ]);
    }

    public function show(Request $request, WhatsAppContact $contact, WhatsAppMessenger $messenger)
    {
        $this->ensureInstalled();

        if ($contact->unread_count > 0) {
            $messenger->markConversationRead($contact);
        }

        $messages = $contact->messages()->with('sender')->latest('id')->limit(150)->get()->reverse()->values();

        return view('whatsapp.inbox', $this->sidebarData($request) + [
            'contact' => $contact->load('user'),
            'messages' => $messages,
            'templates' => WhatsAppTemplate::approved()->orderBy('name')->get()->filter->isSendable()->values(),
            'employees' => $request->user()->hasPermission('whatsapp.contacts.manage') ? User::where('status', 'active')->orderBy('name')->get(['id', 'name']) : collect(),
            'ready' => $this->settings->ready(),
        ]);
    }

    /**
     * Polled by the open conversation for new messages and outbound status changes.
     */
    public function poll(Request $request, WhatsAppContact $contact, WhatsAppMessenger $messenger)
    {
        $this->ensureInstalled();

        $after = (int) $request->query('after', 0);
        $new = $contact->messages()->with('sender')->where('id', '>', $after)->orderBy('id')->limit(100)->get();

        if ($new->contains(fn (WhatsAppMessage $message) => $message->isInbound())) {
            $messenger->markConversationRead($contact);
        }

        $statuses = $contact->messages()
            ->where('direction', 'outbound')
            ->latest('id')
            ->limit(60)
            ->get(['id', 'status', 'error_message'])
            ->mapWithKeys(fn (WhatsAppMessage $message) => [$message->id => [
                'status' => $message->status,
                'icon' => $message->status_icon,
                'error' => $message->error_message,
            ]]);

        return response()->json([
            'html' => $new->map(fn (WhatsAppMessage $message) => view('whatsapp.partials.message', ['message' => $message])->render())->implode(''),
            'last_id' => $new->max('id') ?? $after,
            'statuses' => $statuses,
            'window_open' => $contact->refresh()->isServiceWindowOpen(),
        ]);
    }

    public function sendText(Request $request, WhatsAppContact $contact, WhatsAppMessenger $messenger)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4096'],
            'reply_to' => ['nullable', 'string', 'max:191'],
        ]);

        return $this->attempt($contact, fn () => $messenger->sendText($contact, $data['body'], $request->user(), $data['reply_to'] ?? null));
    }

    public function sendTemplate(Request $request, WhatsAppContact $contact, WhatsAppMessenger $messenger)
    {
        $data = $request->validate([
            'template_id' => ['required', 'integer', 'exists:whatsapp_templates,id'],
            'params' => ['nullable', 'array'],
            'params.*' => ['nullable', 'string', 'max:1024'],
            'header_params' => ['nullable', 'array'],
            'header_params.*' => ['nullable', 'string', 'max:60'],
        ]);

        $template = WhatsAppTemplate::findOrFail($data['template_id']);
        abort_unless($template->isSendable(), 422, 'This template cannot be sent from the platform.');

        return $this->attempt($contact, fn () => $messenger->sendTemplate(
            $contact,
            $template->name,
            array_values($data['params'] ?? []),
            $template->language,
            $request->user(),
            'manual',
            null,
            false,
            null,
            array_values($data['header_params'] ?? []),
        ));
    }

    public function sendMedia(Request $request, WhatsAppContact $contact, WhatsAppMessenger $messenger)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:16384', 'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,mp4,3gp,mp3,ogg,aac,amr,m4a'],
            'caption' => ['nullable', 'string', 'max:1024'],
        ]);

        return $this->attempt($contact, fn () => $messenger->sendMedia($contact, $data['file'], $data['caption'] ?? null, $request->user()));
    }

    public function start(Request $request, WhatsAppMessenger $messenger)
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'phone' => ['nullable', 'required_without:user_id', 'string', 'max:30'],
            'name' => ['nullable', 'string', 'max:255'],
            'template_id' => ['nullable', 'integer', 'exists:whatsapp_templates,id'],
            'params' => ['nullable', 'array'],
            'params.*' => ['nullable', 'string', 'max:1024'],
        ]);

        try {
            if (!empty($data['user_id'])) {
                $user = User::findOrFail($data['user_id']);
                $contact = $messenger->contactForUser($user);
                if (!$contact) {
                    return back()->withInput()->withErrors(['phone' => $user->name . ' has no mobile or phone number on their profile.']);
                }
            } else {
                $contact = $messenger->contactForNumber($data['phone'], $data['name'] ?? null);
            }
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['phone' => $exception->getMessage()]);
        }

        if (empty($data['template_id'])) {
            return redirect()->route('whatsapp.inbox.show', $contact);
        }

        $template = WhatsAppTemplate::findOrFail($data['template_id']);

        return $this->attempt($contact, fn () => $messenger->sendTemplate($contact, $template->name, array_values($data['params'] ?? []), $template->language, $request->user()));
    }

    public function retry(WhatsAppMessage $message, WhatsAppMessenger $messenger)
    {
        return $this->attempt($message->contact, fn () => $messenger->retry($message));
    }

    public function updateContact(Request $request, WhatsAppContact $contact)
    {
        $data = $request->validate([
            'display_name' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'consent' => ['nullable', 'in:keep,opt_in,opt_out'],
        ]);

        $contact->fill([
            'display_name' => $data['display_name'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        match ($data['consent'] ?? 'keep') {
            'opt_in' => $contact->fill(['opted_in_at' => now(), 'opted_out_at' => null, 'opt_in_source' => 'Recorded by ' . $request->user()->name]),
            'opt_out' => $contact->fill(['opted_out_at' => now()]),
            default => null,
        };

        $contact->save();

        return redirect()->route('whatsapp.inbox.show', $contact)->with('success', 'Contact updated.');
    }

    public function media(WhatsAppMessage $message, WhatsAppCloudApi $api)
    {
        abort_unless($message->hasMedia(), 404);
        $disk = Storage::disk('local');

        if (!$message->media_path || !$disk->exists($message->media_path)) {
            abort_unless($message->media_id, 404);

            try {
                $download = $api->downloadMedia($message->media_id);
            } catch (Throwable $exception) {
                abort(502, 'Media could not be downloaded from WhatsApp: ' . $exception->getMessage());
            }

            $extension = $this->extensionFor((string) ($download['mime_type'] ?? $message->media_mime));
            $path = 'whatsapp/inbound/' . now()->format('Y/m') . '/' . Str::uuid() . '.' . $extension;
            $disk->put($path, $download['contents']);
            $message->forceFill(['media_path' => $path, 'media_mime' => $download['mime_type'] ?? $message->media_mime])->save();
        }

        $mime = (string) ($message->media_mime ?: 'application/octet-stream');
        $inline = preg_match('#^(image/(jpeg|png|webp)|audio/|video/)#', $mime) === 1;
        $filename = $message->media_filename ?: ('whatsapp-' . $message->id . '.' . pathinfo((string) $message->media_path, PATHINFO_EXTENSION));

        return response()->file($disk->path($message->media_path), [
            'Content-Type' => $mime,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', $filename) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function attempt(WhatsAppContact $contact, callable $send)
    {
        try {
            $message = $send();
        } catch (Throwable $exception) {
            return redirect()->route('whatsapp.inbox.show', $contact)->withInput()->withErrors(['whatsapp' => $exception->getMessage()]);
        }

        $redirect = redirect()->route('whatsapp.inbox.show', $contact);

        return match ($message?->status) {
            'failed' => $redirect->withErrors(['whatsapp' => 'WhatsApp rejected the message: ' . $message->error_message]),
            'queued' => $redirect->with('warning', 'WhatsApp is temporarily unavailable. The message is queued and the cron job will retry it. (' . $message->error_message . ')'),
            null => $redirect->with('warning', 'Message skipped: the contact has opted out or has no recorded opt-in.'),
            default => $redirect->with('success', 'Message sent.'),
        };
    }

    private function sidebarData(Request $request): array
    {
        $search = trim((string) $request->query('q', ''));
        $filter = (string) $request->query('filter', 'all');

        $contacts = WhatsAppContact::with('user')
            ->when($search !== '', function ($query) use ($search) {
                $digits = preg_replace('/\D+/', '', $search);
                $query->where(function ($query) use ($search, $digits) {
                    $query->where('display_name', 'like', '%' . $search . '%')
                        ->orWhere('profile_name', 'like', '%' . $search . '%')
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', '%' . $search . '%'));
                    if ($digits !== '') {
                        $query->orWhere('wa_id', 'like', '%' . $digits . '%');
                    }
                });
            })
            ->when($filter === 'unread', fn ($query) => $query->where('unread_count', '>', 0))
            ->when($filter === 'open', fn ($query) => $query->where('last_inbound_at', '>', now()->subHours(24)))
            ->when($filter === 'employees', fn ($query) => $query->whereNotNull('user_id'))
            ->whereNotNull('last_message_at')
            ->orderByDesc('last_message_at')
            ->paginate(40)
            ->withQueryString();

        return [
            'contacts' => $contacts,
            'search' => $search,
            'filter' => $filter,
            'unreadTotal' => (int) WhatsAppContact::sum('unread_count'),
            'startTemplates' => WhatsAppTemplate::approved()->orderBy('name')->get()->filter->isSendable()->values(),
            'startEmployees' => User::where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function extensionFor(string $mime): string
    {
        return match (strtok($mime, ';')) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/aac' => 'aac',
            'audio/amr' => 'amr',
            'audio/mp4' => 'm4a',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            default => 'bin',
        };
    }

    private function ensureInstalled(): void
    {
        abort_unless($this->settings->installed(), 404, 'Run the v2.9.0 update first.');
    }
}
