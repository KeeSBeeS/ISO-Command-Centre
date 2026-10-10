<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SignatureLogoStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SignatureBuilderController extends Controller
{
    public function __construct(private SignatureLogoStore $logos)
    {
    }

    public function index()
    {
        $logos = array_map(fn (array $logo) => [
            'id' => $logo['id'],
            'name' => $logo['name'],
            'link' => $logo['link_url'] ?: 'https://iiso.co.za/',
            'src' => $this->logos->imageSrc($logo),
        ], $this->logos->active());

        $hasProfiles = Schema::hasTable('employee_profiles');

        $employees = User::query()
            ->when(Schema::hasColumn('users', 'status'), fn ($query) => $query->where('status', 'active'))
            ->when($hasProfiles, fn ($query) => $query->with('profile'))
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($hasProfiles) {
                $profile = $hasProfiles ? $user->profile : null;

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'title' => $profile?->job_title ?: ($user->position ?? ''),
                    'mobile' => $profile?->mobile ?: ($user->phone ?: ($profile?->phone ?? '')),
                    'email' => $user->email,
                ];
            })
            ->values();

        return view('signature_builder.index', compact('logos', 'employees'));
    }

    public function logos()
    {
        $logos = array_map(fn (array $logo) => $logo + ['src' => $this->logos->imageSrc($logo)], $this->logos->all());

        return view('signature_builder.logos', compact('logos'));
    }

    public function storeLogo(Request $request)
    {
        $data = $this->validateLogo($request);

        $this->logos->create(
            $data['name'],
            $data['link_url'] ?? null,
            $request->file('image'),
            $data['image_url'] ?? null,
            $request->boolean('active')
        );

        return redirect()->route('signature_builder.logos')->with('success', 'Logo "' . $data['name'] . '" added to the signature builder.');
    }

    public function updateLogo(Request $request, string $logo)
    {
        abort_unless($this->logos->find($logo), 404);
        $data = $this->validateLogo($request);

        $this->logos->update(
            $logo,
            $data['name'],
            $data['link_url'] ?? null,
            $request->file('image'),
            $data['image_url'] ?? null,
            $request->boolean('remove_image'),
            $request->boolean('active')
        );

        return redirect()->route('signature_builder.logos')->with('success', 'Logo "' . $data['name'] . '" updated.');
    }

    public function moveLogo(Request $request, string $logo)
    {
        abort_unless($this->logos->find($logo), 404);
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        $this->logos->move($logo, $direction);

        return redirect()->route('signature_builder.logos');
    }

    public function destroyLogo(string $logo)
    {
        $record = $this->logos->find($logo);
        abort_unless($record, 404);

        $this->logos->delete($logo);

        return redirect()->route('signature_builder.logos')->with('success', 'Logo "' . $record['name'] . '" deleted.');
    }

    private function validateLogo(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'link_url' => ['nullable', 'url:https,http', 'max:500'],
            'image' => ['nullable', 'file', 'mimes:png,jpg,jpeg,gif,webp', 'max:2048'],
            'image_url' => ['nullable', 'url:https', 'max:500', 'regex:/\.(png|jpe?g|gif|webp)(\?.*)?$/i'],
        ], [
            'image.mimes' => 'Logo images must be PNG, JPG, GIF or WebP files.',
            'image.max' => 'Logo images may not be larger than 2 MB.',
            'image_url.url' => 'The image URL must be a full https:// address.',
            'image_url.regex' => 'The image URL must point directly to a PNG, JPG, GIF or WebP image.',
        ]);

        $data['name'] = trim($data['name']);
        $data['link_url'] = isset($data['link_url']) ? trim($data['link_url']) : null;
        $data['image_url'] = isset($data['image_url']) ? trim($data['image_url']) : null;

        return $data;
    }
}
