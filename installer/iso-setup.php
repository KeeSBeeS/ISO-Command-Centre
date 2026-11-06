<?php

/*
 * ISO Command Centre setup.
 *
 * Upload this file and an iso-command-centre-vX.Y.Z.zip release (plus its
 * .sha256 file) into the web folder of isoadmin.co.za (public_html), then
 * open https://isoadmin.co.za/iso-setup.php. Don't extract the zip.
 *
 * It works in one of three ways, chosen by what it finds:
 *
 * - Move an existing site (once, from v2.8.x or older): the old Laravel
 *   files in the web folder become the release "v<old version>" in a private
 *   folder outside the web folder. .env and uploaded files move to the
 *   private folder's shared/ folder, the new version is unpacked next to the
 *   old one and switched on, and ISO Command Centre finishes the update when
 *   a System Administrator logs in. If that fails, it switches back to the
 *   old version by itself.
 * - Upgrade: the site already uses the release layout (for example the
 *   Update Manager can't run). The new version is added and switched on.
 * - First install: nothing is installed yet. It asks for the database,
 *   writes the settings file and hands over to the web installer at /install.
 *
 * This is plain PHP on purpose: it runs before the new version exists on the
 * server. The Update Manager also loads it (with ISO_SETUP_LIBRARY defined)
 * to reuse iso_setup_unpack() and iso_setup_publish().
 */

declare(strict_types=1);

const ISO_SETUP_MIN_PHP = '8.2.0';
const ISO_SETUP_RELEASE_PATTERN = '/^v[0-9A-Za-z.\-]+$/';
const ISO_SETUP_ZIP_PREFIX = 'iso-command-centre-';

/**
 * What an old (pre-v3.0.0) installation keeps in the web folder. Only these
 * are moved; anything else in the web folder is left where it is.
 */
const ISO_SETUP_LEGACY_ENTRIES = [
    'app', 'bootstrap', 'config', 'database', 'lang', 'public', 'resources', 'routes', 'storage', 'tests', 'vendor',
    'node_modules', 'cache', 'artisan', 'composer.json', 'composer.lock', 'composer.phar', 'package.json', 'package-lock.json',
    'phpunit.xml', 'vite.config.js', 'tailwind.config.js', 'postcss.config.js', '.env', '.env.example', '.env.backup',
    '.editorconfig', '.gitattributes', '.gitignore', '.htaccess', '.styleci.yml', 'VERSION', 'CHANGELOG.md', 'CLAUDE.md',
    'SOURCE_MANIFEST.md', 'FILES_INCLUDED.txt', 'index.html_', 'error_log',
];

/**
 * Release zips found in the web folder.
 *
 * @return list<string>
 */
function iso_setup_find_zips(string $webDir): array
{
    return glob($webDir.'/'.ISO_SETUP_ZIP_PREFIX.'v*.zip') ?: [];
}

/**
 * Suggest a private folder: "iso-command-centre" in the hosting account's home folder.
 */
function iso_setup_default_home(string $webDir): string
{
    $home = null;
    if (str_contains($webDir, '/domains/')) {
        // DirectAdmin: /home/<user>/domains/<domain>/public_html/...
        $home = substr($webDir, 0, strpos($webDir, '/domains/'));
    }
    if (! $home && function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
        $home = posix_getpwuid(posix_geteuid())['dir'] ?? null;
    }
    if (! $home && getenv('HOME')) {
        $home = getenv('HOME');
    }

    return rtrim($home ?: dirname($webDir, 2), '/').'/iso-command-centre';
}

/**
 * Why this private folder can't be used, or null if it can.
 */
function iso_setup_check_home(string $home, string $webDir): ?string
{
    if (! str_starts_with($home, '/') || str_contains($home, '..')) {
        return 'Enter a full folder path starting with "/".';
    }
    $home = rtrim($home, '/');
    if (str_starts_with($home.'/', rtrim($webDir, '/').'/')
        || preg_match('#/(public_html|private_html|www|htdocs)(/|$)#', $home)) {
        return 'That folder can be reached from the internet. Choose one outside your web folders.';
    }
    if (file_exists($home.'/current.php')) {
        return 'ISO Command Centre is already installed in that folder.';
    }

    return null;
}

/**
 * The private folder of an existing installation, read from its front door.
 */
function iso_setup_existing_home(string $webDir): ?string
{
    $frontDoor = is_file($webDir.'/index.php') ? file_get_contents($webDir.'/index.php') : '';
    if ($frontDoor && preg_match("/define\\('ISO_HOME', '([^']+)'\\);/", $frontDoor, $match)
        && is_file($match[1].'/current.php')) {
        return $match[1];
    }

    return null;
}

/**
 * The version of an old installation kept directly in the web folder, or
 * null if there is none.
 */
function iso_setup_legacy_version(string $webDir): ?string
{
    if (! is_file("$webDir/artisan") || ! is_file("$webDir/bootstrap/app.php") || ! is_file("$webDir/vendor/autoload.php")) {
        return null;
    }
    $version = is_file("$webDir/VERSION") ? 'v'.ltrim(trim((string) file_get_contents("$webDir/VERSION")), 'v') : '';

    return preg_match(ISO_SETUP_RELEASE_PATTERN, $version) && $version !== 'v' ? $version : 'v0.0.0';
}

/**
 * Check the zip and unpack it into $home/releases/<version>. Returns the version.
 *
 * @throws RuntimeException with a message meant for the person installing
 */
function iso_setup_unpack(string $zipPath, string $home): string
{
    if (! class_exists(ZipArchive::class)) {
        throw new RuntimeException('The PHP "zip" extension is not enabled. Turn it on in DirectAdmin\'s PHP settings.');
    }

    // If the checksum file is present, make sure the zip is complete and unchanged.
    if (is_file($zipPath.'.sha256')) {
        $expected = strtok((string) file_get_contents($zipPath.'.sha256'), " \t\n");
        if (! hash_equals((string) $expected, hash_file('sha256', $zipPath))) {
            throw new RuntimeException('The zip does not match its checksum: it is damaged or incomplete. Download and upload it again.');
        }
    }

    $zip = new ZipArchive;
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('Could not open '.basename($zipPath).'. Download and upload it again.');
    }

    // Every entry must sit inside one "iso-command-centre-vX.Y.Z/" folder, with no tricks like "../".
    $root = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        $first = explode('/', $name)[0];
        $root ??= $first;
        if ($first !== $root || str_starts_with($name, '/') || str_contains($name, '\\')
            || in_array('..', explode('/', $name), true)) {
            $zip->close();
            throw new RuntimeException('The zip contains unexpected files. Use a release downloaded from GitHub.');
        }
    }
    $version = str_starts_with((string) $root, ISO_SETUP_ZIP_PREFIX) ? substr((string) $root, strlen(ISO_SETUP_ZIP_PREFIX)) : '';
    if (! preg_match(ISO_SETUP_RELEASE_PATTERN, $version)) {
        $zip->close();
        throw new RuntimeException('This does not look like an ISO Command Centre release zip.');
    }

    foreach (['releases', 'backups', 'shared/storage/app/public', 'shared/storage/app/private', 'shared/storage/framework/cache/data',
        'shared/storage/framework/sessions', 'shared/storage/framework/views', 'shared/storage/logs'] as $dir) {
        if (! is_dir("$home/$dir") && ! mkdir("$home/$dir", 0755, true)) {
            $zip->close();
            throw new RuntimeException("Could not create the folder $home/$dir. Check the permissions in DirectAdmin's File Manager.");
        }
    }

    $release = "$home/releases/$version";
    if (file_exists($release)) {
        $zip->close();
        throw new RuntimeException("$release already exists. Remove it in File Manager, then try again.");
    }
    $unpack = "$home/releases/.unpack-".bin2hex(random_bytes(4));
    if (! $zip->extractTo($unpack)) {
        $zip->close();
        throw new RuntimeException('Unpacking the zip failed. Check there is enough disk space.');
    }
    $zip->close();
    rename("$unpack/$root", $release);
    rmdir($unpack);

    return $version;
}

/**
 * Write the server files from a release's stubs: tools.php and cron.php in
 * the private folder; the front door, .htaccess, recovery.php and public
 * assets in the web folder. The stubs work with any release, so the newest
 * ones are always used.
 */
function iso_setup_publish(string $releaseDir, string $home, string $webDir): void
{
    $stubs = "$releaseDir/installer/stubs";
    $exportedHome = var_export($home, true);

    copy("$stubs/tools.php.stub", "$home/tools.php");
    copy("$stubs/cron.php.stub", "$home/cron.php");
    foreach (['index.php' => 'index.php.stub', 'recovery.php' => 'recovery.php.stub'] as $target => $stub) {
        $contents = str_replace('{{ISO_HOME}}', $exportedHome, (string) file_get_contents("$stubs/$stub"));
        file_put_contents("$webDir/$target.tmp", $contents);
        rename("$webDir/$target.tmp", "$webDir/$target");
    }
    copy("$stubs/htaccess.stub", "$webDir/.htaccess");
    iso_setup_copy_public("$releaseDir/public", $webDir);

    foreach (["$home/tools.php", "$home/cron.php", "$webDir/index.php", "$webDir/recovery.php"] as $file) {
        clearstatcache(true, $file);
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
    }
}

/**
 * Quote a .env value so Laravel reads it back exactly (as App\Support\EnvFile does).
 */
function iso_setup_env_value(string $value): string
{
    if (str_contains($value, "\n") || str_contains($value, "\r")) {
        throw new RuntimeException('Settings cannot contain line breaks.');
    }

    return ! str_contains($value, "'")
        ? "'".$value."'"
        : '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
}

/**
 * First install of the release in $zipPath. Returns the installed version
 * and the installer key for /install.
 *
 * @param  array{host: string, port: string, database: string, username: string, password: string}  $db
 * @return array{version: string, installer_key: string}
 *
 * @throws RuntimeException with a message meant for the person installing
 */
function iso_setup_install(string $webDir, string $home, string $zipPath, string $appUrl, array $db): array
{
    if (version_compare(PHP_VERSION, ISO_SETUP_MIN_PHP, '<')) {
        throw new RuntimeException('ISO Command Centre needs PHP '.ISO_SETUP_MIN_PHP.' or newer; this site runs PHP '.PHP_VERSION.'. Change it in DirectAdmin.');
    }
    if ($error = iso_setup_check_home($home, $webDir)) {
        throw new RuntimeException($error);
    }
    $home = rtrim($home, '/');

    try {
        new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']),
            $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
    } catch (Throwable $e) {
        throw new RuntimeException('Could not connect to the database with these details: '.$e->getMessage());
    }

    $version = iso_setup_unpack($zipPath, $home);
    $release = "$home/releases/$version";
    require_once "$release/installer/stubs/tools.php.stub";

    // Settings file with a fresh encryption key; the web installer does the rest.
    $installerKey = bin2hex(random_bytes(12));
    $env = "$home/shared/.env";
    if (! is_file($env)) {
        $lines = [
            'APP_NAME='.iso_setup_env_value('ISO Admin'),
            'APP_ENV=production',
            'APP_KEY=base64:'.base64_encode(random_bytes(32)),
            'APP_DEBUG=false',
            'APP_URL='.iso_setup_env_value($appUrl),
            'LOG_CHANNEL=stack',
            'LOG_STACK=daily',
            'LOG_LEVEL=info',
            'DB_CONNECTION=mysql',
            'DB_HOST='.iso_setup_env_value($db['host']),
            'DB_PORT='.iso_setup_env_value($db['port']),
            'DB_DATABASE='.iso_setup_env_value($db['database']),
            'DB_USERNAME='.iso_setup_env_value($db['username']),
            'DB_PASSWORD='.iso_setup_env_value($db['password']),
            'SESSION_DRIVER=file',
            'CACHE_STORE=file',
            'QUEUE_CONNECTION=sync',
            'INSTALLER_KEY='.iso_setup_env_value($installerKey),
        ];
        file_put_contents($env, implode("\n", $lines)."\n");
        @chmod($env, 0600);
    }

    iso_setup_publish($release, $home, $webDir);
    iso_switch($home, $version);

    iso_remove($zipPath, $zipPath.'.sha256');

    return ['version' => $version, 'installer_key' => $installerKey];
}

/**
 * Upgrade an existing installation to the release in $zipPath, keeping all
 * data. ISO Command Centre finishes the update (database changes, health
 * check) at /settings/updates/finish/<token> once a System Administrator logs in.
 *
 * @return array{from: string, to: string, token: string}
 *
 * @throws RuntimeException with a message meant for the person installing
 */
function iso_setup_upgrade(string $webDir, string $home, string $zipPath): array
{
    if (version_compare(PHP_VERSION, ISO_SETUP_MIN_PHP, '<')) {
        throw new RuntimeException('ISO Command Centre needs PHP '.ISO_SETUP_MIN_PHP.' or newer; this site runs PHP '.PHP_VERSION.'. Change it in DirectAdmin.');
    }
    $from = require "$home/current.php";
    if (is_file("$home/update-pending.php")) {
        throw new RuntimeException('Another update is still being finished. Log in and open System Settings → Updates.');
    }

    $version = iso_setup_unpack($zipPath, $home);
    if (version_compare(ltrim($version, 'v'), ltrim((string) $from, 'v'), '<=')) {
        iso_setup_delete_tree("$home/releases/$version");
        throw new RuntimeException("This zip is $version, but $from is already running. Use a newer release.");
    }
    $release = "$home/releases/$version";
    require_once "$release/installer/stubs/tools.php.stub";

    $token = bin2hex(random_bytes(20));
    iso_setup_publish($release, $home, $webDir);
    iso_write_php("$home/update-pending.php", [
        'from' => $from,
        'to' => $version,
        'token' => $token,
        'backup' => null,
        'migrated' => false,
        // Time to log in and finish; cron.php switches back after this.
        'until' => time() + 86400,
    ]);
    iso_switch($home, $version);

    iso_remove($zipPath, $zipPath.'.sha256');

    return ['from' => (string) $from, 'to' => $version, 'token' => $token];
}

/**
 * Move an old installation (Laravel directly in the web folder) to the
 * release layout and switch to the release in $zipPath, keeping all data.
 *
 * The old files become releases/<old version>, so the front door, cron.php
 * and recovery.php can switch back to it. .env is copied, and uploaded files
 * (storage/app) move, to shared/; the old release's storage/app points there.
 *
 * @return array{from: string, to: string, token: string, left: list<string>}
 *
 * @throws RuntimeException with a message meant for the person installing
 */
function iso_setup_migrate_legacy(string $webDir, string $home, string $zipPath): array
{
    if (version_compare(PHP_VERSION, ISO_SETUP_MIN_PHP, '<')) {
        throw new RuntimeException('ISO Command Centre needs PHP '.ISO_SETUP_MIN_PHP.' or newer; this site runs PHP '.PHP_VERSION.'. Change it in DirectAdmin.');
    }
    $from = iso_setup_legacy_version($webDir) ?? throw new RuntimeException('No existing installation found in this folder.');
    if (! is_file("$webDir/.env")) {
        throw new RuntimeException('The existing installation has no .env file, so its settings cannot be kept.');
    }
    if ($error = iso_setup_check_home($home, $webDir)) {
        throw new RuntimeException($error);
    }
    $home = rtrim($home, '/');
    $old = "$home/releases/$from";
    if (file_exists($old)) {
        throw new RuntimeException("$old already exists. Remove it in File Manager, then try again.");
    }

    $version = iso_setup_unpack($zipPath, $home);
    $release = "$home/releases/$version";
    if (version_compare(ltrim($version, 'v'), ltrim($from, 'v'), '<=')) {
        iso_setup_delete_tree($release);
        throw new RuntimeException("This zip is $version, but $from is already installed. Use a newer release.");
    }
    require_once "$release/installer/stubs/tools.php.stub";

    // Move the old files out of the web folder. Undone if anything fails.
    mkdir($old, 0755, true);
    $moved = [];
    $undo = function () use (&$moved, $webDir, $old): void {
        foreach (array_reverse($moved) as $entry) {
            @rename("$old/$entry", "$webDir/$entry");
        }
        foreach (['index.php', 'recovery.php'] as $file) {
            if (is_file("$webDir/$file") && str_contains((string) file_get_contents("$webDir/$file"), "define('ISO_HOME'")) {
                unlink("$webDir/$file");
            }
        }
    };

    try {
        // Release notes from the old "changed files" packages describe the
        // site's setup (cron keys, paths), so they leave the web folder too.
        $entries = array_merge(ISO_SETUP_LEGACY_ENTRIES, array_map('basename', glob("$webDir/README*.md") ?: []));
        foreach ($entries as $entry) {
            if (file_exists("$webDir/$entry") || is_link("$webDir/$entry")) {
                if (! rename("$webDir/$entry", "$old/$entry")) {
                    throw new RuntimeException("Could not move $entry out of the web folder. Check its permissions in File Manager.");
                }
                $moved[] = $entry;
            }
        }

        // Whatever is left was never part of ISO Command Centre.
        $left = array_values(array_diff(scandir($webDir) ?: [], ['.', '..', basename(__FILE__), basename($zipPath), basename($zipPath).'.sha256']));

        // The old version now runs from the site's root instead of /public, and
        // its cached settings and routes hold the old folder paths.
        foreach (["$old/.env"] as $envFile) {
            $contents = (string) file_get_contents($envFile);
            $contents = (string) preg_replace('#^(APP_URL=["\']?[^"\'\n]*?)/public/?(["\']?)$#m', '$1$2', $contents);
            file_put_contents($envFile, $contents);
        }
        foreach (glob("$old/bootstrap/cache/{config,routes,routes-v7,events}.php", GLOB_BRACE) ?: [] as $cached) {
            unlink($cached);
        }

        // Settings and uploaded files are shared by every version from now on.
        if (! is_file("$home/shared/.env")) {
            copy("$old/.env", "$home/shared/.env");
            @chmod("$home/shared/.env", 0600);
        }
        if (is_dir("$old/storage/app")) {
            iso_setup_delete_tree("$home/shared/storage/app");
            if (! rename("$old/storage/app", "$home/shared/storage/app")) {
                throw new RuntimeException('Could not move the uploaded files (storage/app) to the private folder.');
            }
            if (! @symlink("$home/shared/storage/app", "$old/storage/app")) {
                // No symbolic links on this host: give the old version its own copy.
                iso_setup_copy_tree("$home/shared/storage/app", "$old/storage/app");
            }
        }
        foreach (['app/public', 'app/private'] as $dir) {
            is_dir("$home/shared/storage/$dir") || mkdir("$home/shared/storage/$dir", 0755, true);
        }

        iso_setup_publish($release, $home, $webDir);
        $token = bin2hex(random_bytes(20));
        iso_write_php("$home/update-pending.php", [
            'from' => $from,
            'to' => $version,
            'token' => $token,
            'backup' => null,
            'migrated' => false,
            // Time to log in and finish; cron.php switches back after this.
            'until' => time() + 86400,
        ]);
        iso_switch($home, $version);
    } catch (Throwable $e) {
        iso_remove("$home/update-pending.php", "$home/current.php");
        if (is_dir("$home/shared/storage/app") && is_link("$old/storage/app")) {
            unlink("$old/storage/app");
            rename("$home/shared/storage/app", "$old/storage/app");
        }
        $undo();
        @rmdir($old);
        iso_setup_delete_tree($release);
        throw $e;
    }

    iso_remove($zipPath, $zipPath.'.sha256');

    return ['from' => $from, 'to' => $version, 'token' => $token, 'left' => $left];
}

/**
 * Copy a release's public files (favicon, robots.txt, ...) into the web folder.
 */
function iso_setup_copy_public(string $from, string $to): void
{
    if (! is_dir($from)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );
    foreach ($items as $item) {
        $relative = substr($item->getPathname(), strlen($from) + 1);
        if (in_array($relative, ['index.php', '.htaccess'], true)) {
            continue;
        }
        $target = "$to/$relative";
        if ($item->isDir()) {
            is_dir($target) || mkdir($target, 0755, true);
        } else {
            copy($item->getPathname(), $target);
        }
    }
}

function iso_setup_copy_tree(string $from, string $to): void
{
    is_dir($to) || mkdir($to, 0755, true);
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );
    foreach ($items as $item) {
        $target = $to.'/'.substr($item->getPathname(), strlen($from) + 1);
        if ($item->isDir()) {
            is_dir($target) || mkdir($target, 0755, true);
        } else {
            copy($item->getPathname(), $target);
        }
    }
}

function iso_setup_delete_tree(string $path): void
{
    if (is_link($path) || is_file($path)) {
        unlink($path);

        return;
    }
    if (! is_dir($path)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($items as $item) {
        $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

function iso_setup_page(string $body): void
{
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">',
    '<meta name="robots" content="noindex"><title>ISO Command Centre setup</title><style>',
    'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#061017;color:#edf7f3;font:16px/1.5 system-ui,sans-serif;padding:16px;box-sizing:border-box}',
    'main{width:100%;max-width:32rem;background:#0d1b24;border:1px solid rgba(139,220,101,.25);border-radius:16px;padding:24px}',
    'h1{margin:0 0 16px;color:#8bdc65;letter-spacing:.08em;font-size:22px;text-align:center}',
    'label{display:block;font-size:14px;margin:12px 0 4px}input{width:100%;box-sizing:border-box;padding:8px;border-radius:8px;border:1px solid #2a4250;background:#061017;color:#edf7f3;font:14px monospace}',
    'button,a.button{display:block;width:100%;margin-top:16px;padding:10px;border:0;border-radius:8px;background:#12a374;color:#061017;font-weight:700;text-align:center;text-decoration:none;cursor:pointer;font-size:16px}',
    '.error{background:rgba(229,72,77,.12);border:1px solid rgba(229,72,77,.35);color:#fecaca;padding:8px 12px;border-radius:8px}',
    'small{color:#a9bbb6}code{color:#8bdc65;word-break:break-all}ul{padding-left:20px}</style></head><body><main><h1>ISO Command Centre</h1>',
    $body, '</main></body></html>';
}

if (defined('ISO_SETUP_LIBRARY')) {
    return;
}

header('Cache-Control: no-store');
header('X-Frame-Options: DENY');

$webDir = __DIR__;
$zips = iso_setup_find_zips($webDir);
$e = fn (string $value): string => htmlspecialchars($value, ENT_QUOTES);
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

if (count($zips) !== 1) {
    iso_setup_page('<p class="error">'.(count($zips) === 0
        ? 'No release zip found. Upload <code>'.ISO_SETUP_ZIP_PREFIX.'vX.Y.Z.zip</code> into this folder next to this file, then reload.'
        : 'More than one release zip is in this folder. Delete all but the one you want to install, then reload.').'</p>');

    return;
}

$finishLink = fn (string $token): string => '<a class="button" href="/settings/updates/finish/'.$e($token).'">Log in and finish the update</a>';
$existingHome = iso_setup_existing_home($webDir);
$legacyVersion = $existingHome ? null : iso_setup_legacy_version($webDir);
$home = $existingHome ?? (string) ($_POST['home'] ?? iso_setup_default_home($webDir));
$error = null;

// Upgrade: the release layout is already in place.
if ($existingHome) {
    if ($isPost) {
        try {
            $upgrade = iso_setup_upgrade($webDir, $existingHome, $zips[0]);
            @unlink(__FILE__);
            iso_setup_page('<p>'.$e($upgrade['to']).' is unpacked next to '.$e($upgrade['from']).' and switched on. '
                .'This setup file and the zip have been deleted.</p><p>Log in as a System Administrator to finish the update: '
                .'the database is backed up, changes are applied and the site checks itself. If anything is wrong, it switches back to '.$e($upgrade['from']).'.</p>'
                .$finishLink($upgrade['token']));

            return;
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }

    $current = (string) (require $existingHome.'/current.php');
    iso_setup_page(
        '<p>ISO Command Centre '.$e($current).' is installed in <code>'.$e($existingHome).'</code>. '
        .'This will upgrade it with <code>'.$e(basename($zips[0])).'</code>, keeping all your data and accounts.</p>'
        .($error ? '<p class="error">'.$e($error).'</p>' : '')
        .'<form method="post"><button>Upgrade</button></form>'
    );

    return;
}

// Move an old installation (Laravel directly in the web folder) to the release layout.
if ($legacyVersion) {
    if ($isPost) {
        try {
            $moved = iso_setup_migrate_legacy($webDir, $home, $zips[0]);
            @unlink(__FILE__);
            $left = $moved['left']
                ? '<p><small>These were in the web folder but are not part of ISO Command Centre, so they were left where they are:</small></p><ul><li><code>'
                    .implode('</code></li><li><code>', array_map($e, $moved['left'])).'</code></li></ul>'
                : '';
            iso_setup_page('<p>Done. The old version '.$e($moved['from']).' was moved to <code>'.$e(rtrim($home, '/').'/releases/'.$moved['from']).'</code>, '
                .'your settings and uploaded files to <code>'.$e(rtrim($home, '/').'/shared').'</code>, and '.$e($moved['to']).' is switched on. '
                .'This setup file and the zip have been deleted.</p>'
                .'<p>Log in as a System Administrator to finish the update: the database is backed up, changes are applied and the site checks itself. '
                .'If anything is wrong, it switches back to '.$e($moved['from']).'.</p>'
                .'<p><small>The site is now at <code>https://'.$e((string) ($_SERVER['HTTP_HOST'] ?? 'isoadmin.co.za')).'/</code> (no more <code>/public</code>; old links redirect). '
                .'Next, add the cron job shown under System Settings → Updates.</small></p>'
                .$left.$finishLink($moved['token']));

            return;
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }

    iso_setup_page(
        '<p>ISO Command Centre '.$e($legacyVersion).' is installed directly in this web folder. '
        .'This moves it to a private folder outside the web folder and installs <code>'.$e(basename($zips[0])).'</code> next to it, '
        .'keeping all your data, accounts and uploaded documents. From then on, updates are installed from System Settings → Updates.</p>'
        .'<p><small>Take a backup of the site and the database in DirectAdmin first.</small></p>'
        .($error ? '<p class="error">'.$e($error).'</p>' : '')
        .'<form method="post"><label for="home">Private folder</label>'
        .'<input id="home" name="home" value="'.$e($home).'" required>'
        .'<p><small>The code, settings and uploaded documents go here, where they can never be downloaded. The suggestion is usually right.</small></p>'
        .'<button>Move and upgrade</button></form>'
    );

    return;
}

// First install.
$db = [
    'host' => (string) ($_POST['db_host'] ?? 'localhost'),
    'port' => (string) ($_POST['db_port'] ?? '3306'),
    'database' => (string) ($_POST['db_database'] ?? ''),
    'username' => (string) ($_POST['db_username'] ?? ''),
    'password' => (string) ($_POST['db_password'] ?? ''),
];
if ($isPost) {
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    try {
        if (! preg_match('/^[A-Za-z0-9.\-]+(:\d+)?$/', $host)) {
            throw new RuntimeException('Could not tell this site\'s address.');
        }
        $installed = iso_setup_install($webDir, $home, $zips[0], 'https://'.$host, $db);
        @unlink(__FILE__);
        iso_setup_page('<p>'.$e($installed['version']).' is unpacked into <code>'.$e(rtrim($home, '/')).'</code>. '
            .'This setup file and the zip have been deleted.</p>'
            .'<p>Next, the installer creates the database tables and your System Administrator account. It asks for this installer key:</p>'
            .'<p><code>'.$e($installed['installer_key']).'</code></p>'
            .'<a class="button" href="/install">Continue to the installer</a>');

        return;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$field = fn (string $name, string $label, string $value, string $type = 'text'): string => '<label for="'.$name.'">'.$label.'</label>'
    .'<input id="'.$name.'" name="'.$name.'" type="'.$type.'" value="'.$e($value).'"'.($name === 'db_password' ? '' : ' required').'>';

iso_setup_page(
    '<p>This will install <code>'.$e(basename($zips[0])).'</code>.</p>'
    .($error ? '<p class="error">'.$e($error).'</p>' : '')
    .'<form method="post" autocomplete="off">'
    .$field('home', 'Private folder', $home)
    .'<p><small>The code, settings and uploaded documents go here, outside the web folder. The suggestion is usually right.</small></p>'
    .$field('db_host', 'Database host', $db['host'])
    .$field('db_port', 'Database port', $db['port'])
    .$field('db_database', 'Database name', $db['database'])
    .$field('db_username', 'Database user', $db['username'])
    .$field('db_password', 'Database password', '', 'password')
    .'<p><small>Create the database and user in DirectAdmin → MySQL Management first.</small></p>'
    .'<button>Install</button></form>'
);
