<?php

namespace App\Updates;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads releases from the private GitHub repository and downloads them.
 */
class GitHubReleases
{
    public function __construct(
        private readonly string $apiUrl,
        private readonly string $repository,
        private readonly ?string $token,
    ) {}

    public function hasToken(): bool
    {
        return filled($this->token);
    }

    /**
     * Check a token can read the repository. Returns a problem, or null if it works.
     */
    public function checkToken(string $token): ?string
    {
        $response = $this->client($token)->get("repos/{$this->repository}");

        return match (true) {
            $response->successful() => null,
            $response->status() === 401 => 'GitHub did not accept this token. Check you copied all of it.',
            $response->status() === 404 => "This token can't see {$this->repository}. Give it access to that repository.",
            default => 'GitHub answered with an error ('.$response->status().'). Try again in a minute.',
        };
    }

    /**
     * Published releases that have a release zip and checksum, newest first.
     *
     * @return list<Release>
     */
    public function all(): array
    {
        $response = $this->client()->get("repos/{$this->repository}/releases", ['per_page' => 20]);
        if (! $response->successful()) {
            throw new RuntimeException('Could not read releases from GitHub ('.$response->status().'). Check the GitHub token on this page.');
        }

        $releases = [];
        foreach ($response->json() as $item) {
            if (($item['draft'] ?? false) || ($item['prerelease'] ?? false) || ! iso_valid_version($item['tag_name'] ?? null)) {
                continue;
            }
            $assets = collect($item['assets'] ?? [])->keyBy('name');
            $zip = $assets->get("iso-command-centre-{$item['tag_name']}.zip");
            $checksum = $assets->get("iso-command-centre-{$item['tag_name']}.zip.sha256");
            if ($zip && $checksum) {
                $releases[] = new Release($item['tag_name'], (string) ($item['body'] ?? ''), $item['published_at'] ?? null, $zip['url'], $checksum['url']);
            }
        }
        usort($releases, fn (Release $a, Release $b) => version_compare(ltrim($b->version, 'v'), ltrim($a->version, 'v')));

        return $releases;
    }

    public function latest(): ?Release
    {
        return $this->all()[0] ?? null;
    }

    public function find(string $version): ?Release
    {
        return collect($this->all())->first(fn (Release $release) => $release->version === $version);
    }

    /**
     * Download a release's zip into $directory and verify it against its
     * checksum. Returns the zip's path.
     */
    public function download(Release $release, string $directory): string
    {
        is_dir($directory) || mkdir($directory, 0755, true);
        $zip = "$directory/iso-command-centre-{$release->version}.zip";

        $checksum = $this->asset()->get($release->checksumUrl)->throw();
        $this->asset()->timeout(300)->sink($zip)->get($release->zipUrl)->throw();
        $expected = strtok($checksum->body(), " \t\n");

        // GitHub answers with the file's description (JSON) unless asked for the file itself.
        $isZip = file_get_contents($zip, false, null, 0, 4) === "PK\x03\x04";
        if (! $isZip || ! preg_match('/^[0-9a-f]{64}$/', (string) $expected)) {
            @unlink($zip);
            throw new RuntimeException("GitHub sent something other than the release files for {$release->version}. Try again later.");
        }

        if (! hash_equals($expected, (string) hash_file('sha256', $zip))) {
            @unlink($zip);
            throw new RuntimeException("The download of {$release->version} is damaged (checksum mismatch). Try again.");
        }

        return $zip;
    }

    /**
     * A request to GitHub. The Accept header is set exactly once: GitHub
     * serves a release file's bytes only when it is the sole type asked for.
     */
    private function client(?string $token = null, string $accept = 'application/json'): PendingRequest
    {
        $token ??= $this->token;

        return Http::baseUrl($this->apiUrl)
            ->withHeaders(['Accept' => $accept, 'X-GitHub-Api-Version' => '2022-11-28', 'User-Agent' => 'ISO-Command-Centre-Updater'])
            ->when(filled($token), fn (PendingRequest $request) => $request->withToken($token))
            ->timeout(20);
    }

    private function asset(): PendingRequest
    {
        return $this->client(accept: 'application/octet-stream');
    }
}
