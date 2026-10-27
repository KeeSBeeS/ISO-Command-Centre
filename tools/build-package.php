<?php

/**
 * Builds an ISO Command Centre update package (.isopkg) for the Package Manager.
 *
 * Usage:
 *   php tools/build-package.php --version=2.9.0 --base=v2.8.5 [--head=HEAD]
 *   php tools/build-package.php --version=2.9.0 --files=app/Foo.php,resources/views/foo.blade.php
 *
 * Options:
 *   --version=X.Y.Z   Version this package installs (required).
 *   --base=REF        Package every file changed between REF and --head (git). Deleted files are
 *                     removed on install.
 *   --head=REF        Git ref to read file contents from (default HEAD).
 *   --files=a,b       Extra files to include, read from the working tree.
 *   --release-dir=DIR Release notes / migrations folder (default package-releases/<version>).
 *   --out=DIR         Output folder (default dist).
 *   --ext=EXT         Output file extension: isopkg (default) or zip. Both upload to the Package Manager.
 *   --sign-key=KEY    Sign the package (default: PACKAGE_SIGNING_KEY environment variable).
 *
 * The release folder may contain:
 *   release.json      {"title", "description", "requires": {"min_version", "max_version", "php",
 *                      "extensions"}, "changelog": [...], "delete": [...]}
 *   migrations/*.php  return new class extends Migration { up() / down() } — run in name order.
 */

require __DIR__ . '/../app/Services/PackageManagerException.php';
require __DIR__ . '/../app/Services/PackageArchive.php';

use App\Services\PackageArchive;
use App\Services\PackageManagerException;

const EXCLUDED_PREFIXES = ['tools/', 'tests/', 'package-releases/', 'dist/', '.github/', '.claude/'];
const EXCLUDED_FILES = ['VERSION', 'phpunit.xml', '.editorconfig', '.gitattributes', '.gitignore'];

$root = dirname(__DIR__);
chdir($root);

$options = getopt('', ['version:', 'base:', 'head:', 'files:', 'release-dir:', 'out:', 'ext:', 'sign-key:', 'help']);

if (isset($options['help']) || empty($options['version'])) {
    fwrite(STDERR, "Usage: php tools/build-package.php --version=X.Y.Z (--base=REF | --files=a,b) [--head=REF] [--release-dir=DIR] [--out=DIR] [--ext=isopkg|zip] [--sign-key=KEY]\n");
    exit(isset($options['help']) ? 0 : 1);
}

function fail(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function git(array $args): string
{
    $command = 'git ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>/dev/null';
    $output = shell_exec($command);
    if ($output === null || $output === false) {
        fail('git command failed: ' . $command);
    }

    return $output;
}

function gitShow(string $ref, string $path): string
{
    $temporary = tempnam(sys_get_temp_dir(), 'isopkg');
    exec('git show ' . escapeshellarg($ref . ':' . $path) . ' > ' . escapeshellarg($temporary) . ' 2>/dev/null', $unused, $exitCode);
    $contents = file_get_contents($temporary);
    unlink($temporary);

    if ($exitCode !== 0 || $contents === false) {
        fail("Could not read {$path} at {$ref}.");
    }

    return $contents;
}

function isExcluded(string $path): bool
{
    if (in_array($path, EXCLUDED_FILES, true) || PackageArchive::isProtected($path)) {
        return true;
    }

    foreach (EXCLUDED_PREFIXES as $prefix) {
        if (str_starts_with($path, $prefix)) {
            return true;
        }
    }

    return false;
}

$version = (string) $options['version'];
if (!PackageArchive::isValidVersion($version)) {
    fail("Invalid version {$version}.");
}

$head = (string) ($options['head'] ?? 'HEAD');
$releaseDirectory = rtrim((string) ($options['release-dir'] ?? "package-releases/{$version}"), '/');
$outDirectory = rtrim((string) ($options['out'] ?? 'dist'), '/');
$extension = (string) ($options['ext'] ?? PackageArchive::EXTENSION);
if (!in_array($extension, [PackageArchive::EXTENSION, 'zip'], true)) {
    fail('--ext must be isopkg or zip.');
}
$signKey = (string) ($options['sign-key'] ?? getenv('PACKAGE_SIGNING_KEY') ?: '');

$release = [];
if (is_file("{$releaseDirectory}/release.json")) {
    $release = json_decode((string) file_get_contents("{$releaseDirectory}/release.json"), true);
    if (!is_array($release)) {
        fail("{$releaseDirectory}/release.json is not valid JSON.");
    }
}

// path => ['git' => ref] | ['disk' => path]
$sources = [];
$deletes = array_values((array) ($release['delete'] ?? []));

if (!empty($options['base'])) {
    $diff = git(['diff', '--name-status', '--no-renames', (string) $options['base'], $head]);
    foreach (array_filter(explode("\n", $diff)) as $line) {
        [$status, $path] = array_pad(explode("\t", $line, 2), 2, '');
        if ($path === '' || isExcluded($path)) {
            continue;
        }

        if ($status === 'D') {
            $deletes[] = $path;
        } else {
            $sources[$path] = ['git' => $head];
        }
    }
}

foreach (array_filter(explode(',', (string) ($options['files'] ?? ''))) as $path) {
    $path = trim($path);
    if (!is_file($path)) {
        fail("File {$path} does not exist.");
    }
    $sources[$path] = ['disk' => $path];
}

$migrations = glob("{$releaseDirectory}/migrations/*.php") ?: [];
sort($migrations);

if ($sources === [] && $deletes === [] && $migrations === []) {
    fail('Nothing to package. Pass --base and/or --files, or add migrations to the release folder.');
}

$zipPath = "{$outDirectory}/iso-command-centre-{$version}.{$extension}";
if (!is_dir($outDirectory)) {
    mkdir($outDirectory, 0755, true);
}

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fail("Could not create {$zipPath}.");
}

$manifest = [
    'format' => PackageArchive::FORMAT,
    'package' => PackageArchive::PACKAGE_NAME,
    'version' => $version,
    'title' => (string) ($release['title'] ?? "ISO Command Centre v{$version}"),
    'description' => (string) ($release['description'] ?? ''),
    'released_at' => date('Y-m-d'),
    'requires' => (array) ($release['requires'] ?? []),
    'changelog' => array_values((array) ($release['changelog'] ?? [])),
    'files' => [],
    'delete' => [],
    'migrations' => [],
];

ksort($sources);
foreach ($sources as $path => $source) {
    $contents = isset($source['git']) ? gitShow($source['git'], $path) : file_get_contents($source['disk']);
    $zip->addFromString(PackageArchive::FILES_PREFIX . $path, $contents);
    $manifest['files'][] = ['path' => $path, 'sha256' => hash('sha256', $contents), 'size' => strlen($contents)];
}

$manifest['delete'] = array_values(array_unique(array_diff($deletes, array_keys($sources))));

foreach ($migrations as $file) {
    $entry = PackageArchive::MIGRATIONS_PREFIX . basename($file);
    $zip->addFile($file, $entry);
    $manifest['migrations'][] = ['name' => basename($file, '.php'), 'path' => $entry, 'sha256' => hash_file('sha256', $file)];
}

try {
    $manifest = PackageArchive::validateManifest($manifest);
} catch (PackageManagerException $e) {
    $zip->close();
    @unlink($zipPath);
    fail($e->getMessage());
}

if ($signKey !== '') {
    $manifest['signature'] = PackageArchive::signManifest($manifest, $signKey);
}

$zip->addFromString(PackageArchive::MANIFEST, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
$zip->close();

$archive = PackageArchive::open($zipPath);
if ($problems = $archive->verifyContents()) {
    fail('Built package failed verification: ' . implode(' ', $problems));
}

printf(
    "Built %s\n  version %s · %d file(s) · %d delete(s) · %d migration(s) · %s\n",
    $zipPath,
    $version,
    count($manifest['files']),
    count($manifest['delete']),
    count($manifest['migrations']),
    $signKey !== '' ? 'signed' : 'unsigned'
);
