<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * File-based storage for the email signature brand logos, company logo and background.
 *
 * Logo records are kept in storage/app/signature-builder/logos.json, the company logo and
 * custom background in storage/app/signature-builder/settings.json, and uploaded images are
 * saved to public/uploads/signature-logos so that email clients can load them from a public
 * URL. No database tables or migrations are required.
 *
 * Copied signatures embed absolute URLs to the uploaded images, and emails already sent keep
 * loading them. Replacing an image therefore keeps the superseded file on the server; only an
 * explicit Remove / Delete / Reset deletes a file, and only after the JSON record no longer
 * points to it.
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

        $logos = array_values(array_filter($this->readJson($path), 'is_array'));

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

            $removedFile = null;
            if ($image) {
                // replacing: the superseded file stays, so emails already sent keep their image
                $logo['image_file'] = $this->storeImage($image, $name);
                $logo['image_url'] = null;
            } elseif ($removeImage) {
                $removedFile = $logo['image_file'] ?? null;
                $logo['image_file'] = null;
                $logo['image_url'] = null;
            } elseif ($imageUrl) {
                $logo['image_file'] = null;
                $logo['image_url'] = $imageUrl;
            }

            $logo['updated_at'] = now()->toIso8601String();
            $this->save($logos);
            $this->deleteImage($removedFile);

            return true;
        }

        return false;
    }

    public function delete(string $id): bool
    {
        $logos = $this->all();
        $remaining = [];
        $deleted = false;
        $removedFile = null;

        foreach ($logos as $logo) {
            if (($logo['id'] ?? null) === $id) {
                $removedFile = $logo['image_file'] ?? null;
                $deleted = true;
                continue;
            }
            $remaining[] = $logo;
        }

        if ($deleted) {
            $this->save($this->renumber($remaining));
            $this->deleteImage($removedFile);
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

    /**
     * Upload or replace the company logo. A replaced file is kept: emails already sent still load it.
     */
    public function setCompanyLogo(UploadedFile $image): void
    {
        $file = $this->storeImage($image, 'company-logo');
        $settings = $this->settings();
        $settings['company_logo_file'] = $file;
        $this->saveSettings($settings);
    }

    public function removeCompanyLogo(): void
    {
        $settings = $this->settings();
        $removedFile = $settings['company_logo_file'] ?? null;
        $settings['company_logo_file'] = null;
        $this->saveSettings($settings);
        $this->deleteImage($removedFile);
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

    /**
     * Upload or replace the custom background. A replaced file is kept: emails already sent still load it.
     */
    public function setBackground(UploadedFile $image): void
    {
        $file = $this->storeImage($image, 'signature-background');
        $settings = $this->settings();
        $settings['background_file'] = $file;
        $this->saveSettings($settings);
    }

    public function resetBackground(): void
    {
        $settings = $this->settings();
        $removedFile = $settings['background_file'] ?? null;
        $settings['background_file'] = null;
        $this->saveSettings($settings);
        $this->deleteImage($removedFile);
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

        return File::exists($path) ? $this->readJson($path) : [];
    }

    private function saveSettings(array $settings): void
    {
        $this->writeJson($this->settingsPath(), $settings);
    }

    /**
     * Decode a JSON data file. An empty file counts as no data, but a file that has content and does not
     * decode to an array is refused: returning [] would let the next save overwrite every record in it.
     */
    private function readJson(string $path): array
    {
        // no read lock needed: writeJson() renames a complete file into place, so a reader always gets a whole file
        $contents = (string) File::get($path);
        if (trim($contents) === '') {
            return [];
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('The signature builder data file ' . $path . ' could not be read (invalid JSON). Restore it from a backup, or delete it to start again with the default logos.');
        }

        return $decoded;
    }

    /**
     * Write a JSON data file atomically: a temporary file in the same folder is renamed over the old one, so
     * a reader never sees a half-written file and a failed write leaves the previous version in place.
     */
    private function writeJson(string $path, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $directory = dirname($path);
        File::ensureDirectoryExists($directory, 0755);

        $temp = tempnam($directory, basename($path) . '.');
        $written = $temp !== false && file_put_contents($temp, $json) === strlen($json);
        if ($written) {
            @chmod($temp, 0644);   // tempnam() creates the file as 0600
        }
        if (!$written || !@rename($temp, $path)) {
            if ($temp !== false && File::exists($temp)) {
                File::delete($temp);
            }
            throw new RuntimeException('Could not save ' . $path . '. Check that storage/app is writable.');
        }
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
        $this->writeJson($this->dataPath(), array_values($logos));
    }

    private function dataPath(): string
    {
        return storage_path('app/signature-builder/logos.json');
    }
}
