<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * File-based storage for the email signature brand logos, company logo and background.
 *
 * Logo records are kept in storage/app/signature-builder/logos.json, the company logo and
 * custom background in storage/app/signature-builder/settings.json, and uploaded images are
 * saved to public/uploads/signature-logos so that email clients can load them from a public
 * URL. No database tables or migrations are required.
 */
class SignatureLogoStore
{
    public const UPLOAD_DIRECTORY = 'uploads/signature-logos';

    public const DEFAULT_BACKGROUND = 'images/signature/iiso-signature-bg.jpg';

    private const DEFAULT_LOGOS = [
        ['name' => 'PPL', 'link_url' => 'https://iiso.co.za/'],
        ['name' => 'BioMax', 'link_url' => 'https://iiso.co.za/'],
        ['name' => 'Royal Purple', 'link_url' => 'https://iiso.co.za/royal-purple/'],
        ['name' => 'Bel-Ray', 'link_url' => 'https://iiso.co.za/product-category/bel-ray/'],
        ['name' => 'Calumet', 'link_url' => 'https://iiso.co.za/'],
        ['name' => 'Yateks', 'link_url' => 'https://iiso.co.za/oil-quality-sensors/'],
        ['name' => 'Filter Focus', 'link_url' => 'https://iiso.co.za/filter-focus/'],
        ['name' => 'Seal Saver', 'link_url' => 'https://iiso.co.za/seal-saver/'],
    ];

    public function all(): array
    {
        $path = $this->dataPath();

        if (!File::exists($path)) {
            $logos = [];
            foreach (self::DEFAULT_LOGOS as $index => $logo) {
                $logos[] = $this->makeRecord($logo['name'], $logo['link_url'], null, null, true, $index + 1);
            }
            $this->save($logos);

            return $logos;
        }

        $decoded = json_decode((string) File::get($path), true);
        $logos = is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];

        usort($logos, fn (array $a, array $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

        return $logos;
    }

    public function active(): array
    {
        return array_values(array_filter($this->all(), fn (array $logo) => !empty($logo['active'])));
    }

    public function find(string $id): ?array
    {
        foreach ($this->all() as $logo) {
            if (($logo['id'] ?? null) === $id) {
                return $logo;
            }
        }

        return null;
    }

    public function create(string $name, ?string $linkUrl, ?UploadedFile $image, ?string $imageUrl, bool $active): array
    {
        $logos = $this->all();
        $nextOrder = empty($logos) ? 1 : max(array_map(fn ($logo) => (int) ($logo['sort_order'] ?? 0), $logos)) + 1;

        $record = $this->makeRecord($name, $linkUrl, $image ? $this->storeImage($image, $name) : null, $image ? null : $imageUrl, $active, $nextOrder);
        $logos[] = $record;
        $this->save($logos);

        return $record;
    }

    public function update(string $id, string $name, ?string $linkUrl, ?UploadedFile $image, ?string $imageUrl, bool $removeImage, bool $active): bool
    {
        $logos = $this->all();

        foreach ($logos as &$logo) {
            if (($logo['id'] ?? null) !== $id) {
                continue;
            }

            $logo['name'] = $name;
            $logo['link_url'] = $linkUrl;
            $logo['active'] = $active;

            if ($image) {
                $this->deleteImage($logo['image_file'] ?? null);
                $logo['image_file'] = $this->storeImage($image, $name);
                $logo['image_url'] = null;
            } elseif ($removeImage) {
                $this->deleteImage($logo['image_file'] ?? null);
                $logo['image_file'] = null;
                $logo['image_url'] = null;
            } elseif ($imageUrl) {
                $this->deleteImage($logo['image_file'] ?? null);
                $logo['image_file'] = null;
                $logo['image_url'] = $imageUrl;
            }

            $logo['updated_at'] = now()->toIso8601String();
            $this->save($logos);

            return true;
        }

        return false;
    }

    public function delete(string $id): bool
    {
        $logos = $this->all();
        $remaining = [];
        $deleted = false;

        foreach ($logos as $logo) {
            if (($logo['id'] ?? null) === $id) {
                $this->deleteImage($logo['image_file'] ?? null);
                $deleted = true;
                continue;
            }
            $remaining[] = $logo;
        }

        if ($deleted) {
            $this->save($this->renumber($remaining));
        }

        return $deleted;
    }

    public function move(string $id, string $direction): void
    {
        $logos = $this->all();
        $index = array_search($id, array_column($logos, 'id'), true);

        if ($index === false) {
            return;
        }

        $target = $direction === 'up' ? $index - 1 : $index + 1;
        if ($target < 0 || $target >= count($logos)) {
            return;
        }

        [$logos[$index], $logos[$target]] = [$logos[$target], $logos[$index]];
        $this->save($this->renumber($logos));
    }

    /**
     * Absolute public URL for a logo image, or null when the logo has no image.
     */
    public function imageSrc(array $logo): ?string
    {
        return $this->uploadedSrc($logo['image_file'] ?? null) ?? (!empty($logo['image_url']) ? $logo['image_url'] : null);
    }

    /**
     * Absolute public URL of the uploaded official company logo, or null when none is uploaded.
     */
    public function companyLogoSrc(): ?string
    {
        return $this->uploadedSrc($this->settings()['company_logo_file'] ?? null);
    }

    public function setCompanyLogo(UploadedFile $image): void
    {
        $settings = $this->settings();
        $this->deleteImage($settings['company_logo_file'] ?? null);
        $settings['company_logo_file'] = $this->storeImage($image, 'company-logo');
        $this->saveSettings($settings);
    }

    public function removeCompanyLogo(): void
    {
        $settings = $this->settings();
        $this->deleteImage($settings['company_logo_file'] ?? null);
        $settings['company_logo_file'] = null;
        $this->saveSettings($settings);
    }

    /**
     * Absolute public URL of the signature background: the uploaded custom background, or the default artwork.
     */
    public function backgroundSrc(): string
    {
        return $this->uploadedSrc($this->settings()['background_file'] ?? null) ?? asset(self::DEFAULT_BACKGROUND);
    }

    public function hasCustomBackground(): bool
    {
        return $this->uploadedSrc($this->settings()['background_file'] ?? null) !== null;
    }

    public function setBackground(UploadedFile $image): void
    {
        $settings = $this->settings();
        $this->deleteImage($settings['background_file'] ?? null);
        $settings['background_file'] = $this->storeImage($image, 'signature-background');
        $this->saveSettings($settings);
    }

    public function resetBackground(): void
    {
        $settings = $this->settings();
        $this->deleteImage($settings['background_file'] ?? null);
        $settings['background_file'] = null;
        $this->saveSettings($settings);
    }

    private function uploadedSrc(?string $fileName): ?string
    {
        if (!$fileName || basename($fileName) !== $fileName || !File::exists(public_path(self::UPLOAD_DIRECTORY . '/' . $fileName))) {
            return null;
        }

        return asset(self::UPLOAD_DIRECTORY . '/' . rawurlencode($fileName));
    }

    private function settings(): array
    {
        $path = $this->settingsPath();
        $decoded = File::exists($path) ? json_decode((string) File::get($path), true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    private function saveSettings(array $settings): void
    {
        $path = $this->settingsPath();
        File::ensureDirectoryExists(dirname($path), 0755);
        File::put($path, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), true);
    }

    private function settingsPath(): string
    {
        return storage_path('app/signature-builder/settings.json');
    }

    private function makeRecord(string $name, ?string $linkUrl, ?string $imageFile, ?string $imageUrl, bool $active, int $sortOrder): array
    {
        return [
            'id' => Str::lower(Str::random(12)),
            'name' => $name,
            'link_url' => $linkUrl,
            'image_file' => $imageFile,
            'image_url' => $imageUrl,
            'active' => $active,
            'sort_order' => $sortOrder,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    private function storeImage(UploadedFile $image, string $name): string
    {
        $directory = public_path(self::UPLOAD_DIRECTORY);
        File::ensureDirectoryExists($directory, 0755);

        $extension = strtolower($image->guessExtension() ?: $image->getClientOriginalExtension() ?: 'png');
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }

        $fileName = (Str::slug($name) ?: 'logo') . '-' . Str::lower(Str::random(8)) . '.' . $extension;
        $image->move($directory, $fileName);

        return $fileName;
    }

    private function deleteImage(?string $fileName): void
    {
        if (!$fileName || basename($fileName) !== $fileName) {
            return;
        }

        $path = public_path(self::UPLOAD_DIRECTORY . '/' . $fileName);
        if (File::exists($path)) {
            File::delete($path);
        }
    }

    private function renumber(array $logos): array
    {
        $logos = array_values($logos);
        foreach ($logos as $index => $logo) {
            $logos[$index]['sort_order'] = $index + 1;
        }

        return $logos;
    }

    private function save(array $logos): void
    {
        $path = $this->dataPath();
        File::ensureDirectoryExists(dirname($path), 0755);
        File::put($path, json_encode(array_values($logos), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), true);
    }

    private function dataPath(): string
    {
        return storage_path('app/signature-builder/logos.json');
    }
}
