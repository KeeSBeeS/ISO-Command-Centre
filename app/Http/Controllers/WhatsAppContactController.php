<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WhatsAppContact;
use App\Services\WhatsApp\PhoneNumber;
use App\Services\WhatsApp\WhatsAppMessenger;
use App\Services\WhatsApp\WhatsAppSettings;
use Illuminate\Http\Request;

/**
 * Employee WhatsApp directory: which employees are reachable and who has opted in.
 */
class WhatsAppContactController extends Controller
{
    public function index(Request $request, WhatsAppSettings $settings)
    {
        abort_unless($settings->installed(), 404, 'Install the v2.9.1 WhatsApp package first.');

        $search = trim((string) $request->query('q', ''));
        $countryCode = (string) $settings->get('whatsapp_default_country_code', '27');

        $users = User::with('profile')
            ->where('status', 'active')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%' . $search . '%'))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        $linked = WhatsAppContact::whereIn('user_id', $users->pluck('id'))->get()->keyBy('user_id');

        $rows = $users->getCollection()->map(function (User $user) use ($linked, $countryCode) {
            $contact = $linked->get($user->id);
            $number = $contact?->wa_id;

            if (!$number) {
                foreach ([$user->profile?->mobile, $user->phone, $user->profile?->phone] as $candidate) {
                    if ($number = PhoneNumber::normalize($candidate, $countryCode)) {
                        break;
                    }
                }
            }

            return ['user' => $user, 'contact' => $contact, 'number' => $number];
        });

        return view('whatsapp.contacts', [
            'users' => $users,
            'rows' => $rows,
            'search' => $search,
            'optedInCount' => WhatsAppContact::whereNotNull('user_id')->whereNotNull('opted_in_at')->whereNull('opted_out_at')->count(),
        ]);
    }

    public function consent(Request $request, WhatsAppMessenger $messenger)
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'action' => ['required', 'in:opt_in,opt_out'],
        ]);

        $updated = 0;
        $missing = [];

        foreach (User::with('profile')->whereIn('id', $data['user_ids'])->get() as $user) {
            $contact = $messenger->contactForUser($user);
            if (!$contact) {
                $missing[] = $user->name;
                continue;
            }

            $contact->fill($data['action'] === 'opt_in'
                ? ['opted_in_at' => now(), 'opted_out_at' => null, 'opt_in_source' => 'Recorded by ' . $request->user()->name]
                : ['opted_out_at' => now()])->save();
            $updated++;
        }

        $redirect = redirect()->route('whatsapp.contacts')->with('success', $updated . ' employee WhatsApp consent record(s) updated.');

        return $missing ? $redirect->with('warning', 'No valid phone number for: ' . implode(', ', $missing) . '.') : $redirect;
    }
}
