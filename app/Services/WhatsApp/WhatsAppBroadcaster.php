<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Collection;

class WhatsAppBroadcaster
{
    public const INLINE_BATCH = 25;

    public function __construct(private WhatsAppMessenger $messenger, private WhatsAppSettings $settings)
    {
    }

    /**
     * Contacts reachable for business-initiated messages in the given audience.
     *
     * @return Collection<int, WhatsAppContact>
     */
    public function audience(string $type, array $ids = []): Collection
    {
        $users = match ($type) {
            'department' => User::where('status', 'active')->whereHas('departments', fn ($query) => $query->whereIn('departments.id', $ids))->get(),
            'role' => User::where('status', 'active')->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $ids))->get(),
            'employees' => User::where('status', 'active')->get(),
            default => collect(),
        };

        $contacts = $type === 'contacts'
            ? WhatsAppContact::whereIn('id', $ids)->get()
            : $users->map(fn (User $user) => $this->messenger->contactForUser($user))->filter();

        $requireOptIn = $this->settings->bool('whatsapp_require_opt_in');

        return $contacts
            ->unique('id')
            ->reject(fn (WhatsAppContact $contact) => $contact->isOptedOut() || ($requireOptIn && !$contact->isOptedIn()))
            ->values();
    }

    public function start(WhatsAppTemplate $template, string $name, string $audienceType, array $audienceIds, array $bodyParams, User $creator): WhatsAppBroadcast
    {
        $contacts = $this->audience($audienceType, $audienceIds);

        $broadcast = WhatsAppBroadcast::create([
            'name' => $name,
            'template_name' => $template->name,
            'template_language' => $template->language,
            'template_params' => $bodyParams,
            'audience_type' => $audienceType,
            'audience_meta' => ['ids' => array_values($audienceIds)],
            'status' => $contacts->isEmpty() ? 'completed' : 'sending',
            'total_count' => $contacts->count(),
            'created_by' => $creator->id,
            'started_at' => now(),
            'completed_at' => $contacts->isEmpty() ? now() : null,
        ]);

        foreach ($contacts as $contact) {
            $params = array_map(fn ($value) => $this->personalise((string) $value, $contact), $bodyParams);

            $this->messenger->sendTemplate(
                $contact,
                $template->name,
                $params,
                $template->language,
                $creator,
                'broadcast',
                'broadcast:' . $broadcast->id . ':' . $contact->id,
                true,
                $broadcast->id,
            );
        }

        $this->messenger->dispatchQueued(self::INLINE_BATCH);
        $this->refreshStatus($broadcast);

        return $broadcast->refresh();
    }

    public function refreshStatus(WhatsAppBroadcast $broadcast): void
    {
        if ($broadcast->status !== 'sending') {
            return;
        }

        if (!$broadcast->messages()->where('status', 'queued')->exists()) {
            $broadcast->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
        }
    }

    public function refreshAll(): void
    {
        WhatsAppBroadcast::where('status', 'sending')->get()->each(fn (WhatsAppBroadcast $broadcast) => $this->refreshStatus($broadcast));
    }

    /**
     * Supports {name} and {first_name} merge fields inside broadcast parameter values.
     */
    private function personalise(string $value, WhatsAppContact $contact): string
    {
        $name = $contact->user?->name ?: $contact->display_name ?: $contact->profile_name ?: 'there';
        $first = strtok($name, ' ') ?: $name;

        return str_replace(['{name}', '{first_name}'], [$name, $first], $value);
    }
}
