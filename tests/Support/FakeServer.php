<?php

namespace Tests\Support;

use ZipArchive;

/**
 * A throwaway copy of the server layout: a private folder (ISO_HOME) with
 * releases, and a web folder. Releases are minimal stand-ins that carry the
 * real installer stubs.
 */
class FakeServer
{
    public readonly string $root;

    public readonly string $home;

    public readonly string $web;

    public function __construct()
    {
        $this->root = sys_get_temp_dir().'/iso-server-'.bin2hex(random_bytes(4));
        $this->home = $this->root.'/home/iso-command-centre';
        $this->web = $this->root.'/web';
        foreach ([$this->home.'/releases', $this->home.'/backups', $this->home.'/shared/storage/framework', $this->web] as $dir) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($this->home.'/shared/.env', "APP_KEY=test\n");
    }

    public function destroy(): void
    {
        exec('rm -rf '.escapeshellarg($this->root));
    }

    /**
     * The files of a stand-in release, by path inside the release.
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    public static function releaseFiles(string $version, bool $withUpdater = true, array $overrides = []): array
    {
        $files = [
            'VERSION' => $version,
            'bootstrap/app.php' => '<?php return null;',
            'vendor/autoload.php' => '<?php',
            "public/build/assets/app-$version.css" => 'body{}',
            'public/index.php' => '<?php // release copy',
        ];
        if ($withUpdater) {
            $files['app/Updates/Updater.php'] = '<?php';
        }
        foreach (glob(base_path('installer/stubs/*')) as $stub) {
            $files['installer/stubs/'.basename($stub)] = file_get_contents($stub);
        }

        return array_merge($files, $overrides);
    }

    /**
     * @param  array<string, string>  $overrides
     */
    public function addRelease(string $version, bool $withUpdater = true, array $overrides = []): void
    {
        foreach (self::releaseFiles($version, $withUpdater, $overrides) as $path => $contents) {
            $target = "{$this->home}/releases/$version/$path";
            is_dir(dirname($target)) || mkdir(dirname($target), 0755, true);
            file_put_contents($target, $contents);
        }
    }

    /**
     * Install $version as if the setup file had: front door, tools, current.php.
     */
    public function install(string $version): void
    {
        $this->addRelease($version);
        iso_setup_publish("{$this->home}/releases/$version", $this->home, $this->web);
        iso_switch($this->home, $version);
    }

    /**
     * A release zip as GitHub would serve it.
     *
     * @param  array<string, string>  $overrides
     */
    public function zip(string $version, array $overrides = []): string
    {
        $path = $this->root."/iso-command-centre-$version.zip";
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach (self::releaseFiles($version, true, $overrides) as $file => $contents) {
            $zip->addFromString("iso-command-centre-$version/$file", $contents);
        }
        $zip->close();

        return (string) file_get_contents($path);
    }

    public function current(): string
    {
        return iso_read_php($this->home.'/current.php');
    }

    public function pending(): ?array
    {
        return iso_read_php($this->home.'/update-pending.php');
    }

    /**
     * Run a PHP file from the server layout in a separate process, as Apache or cron would.
     *
     * @param  array<string, string>  $post
     * @return array{output: string, status: int}
     */
    public function run(string $file, array $post = []): array
    {
        $prelude = $post
            ? '$_SERVER["REQUEST_METHOD"] = "POST"; $_POST = '.var_export($post, true).';'
            : '$_SERVER["REQUEST_METHOD"] = "GET";';
        $command = 'php -d display_errors=stderr -r '.escapeshellarg($prelude.' require '.var_export($file, true).';').' 2>&1';
        exec($command, $output, $status);

        return ['output' => implode("\n", $output), 'status' => $status];
    }
}
