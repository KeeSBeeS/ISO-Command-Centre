<?php

namespace App\Services\WhatsApp;

use RuntimeException;

/**
 * Adds the WhatsApp module to the live bootstrap/providers.php and layout, and removes it again.
 *
 * The live copies of these files are newer than the repo, so they are patched in place (with a
 * .bak- copy) instead of being shipped. Inserted blocks are wrapped in markers so remove() can take
 * out exactly what add() put in. The provider entry is guarded by class_exists(), so the site still
 * boots if the module's files are removed without running remove() (for example by the rescue script).
 */
class WhatsAppActivation
{
    public const PROVIDER_START = '// whatsapp-module:start';
    public const PROVIDER_END = '// whatsapp-module:end';
    public const MENU_START = '{{-- whatsapp-module:start --}}';
    public const MENU_END = '{{-- whatsapp-module:end --}}';
    public const ICON_MARKER = '// whatsapp-module:icon';

    private const PROVIDER_BLOCK = "    " . self::PROVIDER_START . "\n"
        . "    ...(class_exists(App\\Providers\\WhatsAppServiceProvider::class) ? [App\\Providers\\WhatsAppServiceProvider::class] : []),\n"
        . "    " . self::PROVIDER_END . "\n";

    private const MENU_BLOCK = "                " . self::MENU_START . "\n"
        . "                @if(\\Illuminate\\Support\\Facades\\Route::has('whatsapp.inbox') && \$currentUser->hasPermission('whatsapp.view') && \\Illuminate\\Support\\Facades\\Schema::hasTable('whatsapp_contacts'))\n"
        . "                    @php(\$whatsappUnread = (int) \\App\\Models\\WhatsAppContact::sum('unread_count'))\n"
        . "                    <a href=\"{{ route('whatsapp.inbox') }}\" class=\"{{ request()->routeIs('whatsapp.*') ? 'active' : '' }}\"><span class=\"nav-icon\">💬</span><span class=\"nav-text\"><strong>WhatsApp{{ \$whatsappUnread > 0 ? ' (' . \$whatsappUnread . ')' : '' }}</strong><span>Business inbox</span></span></a>\n"
        . "                @endif\n"
        . "                " . self::MENU_END . "\n";

    private const ICON_ANCHOR = "str_starts_with(\$routeName, 'cron_jobs') => '⚙️',";

    private const LEGACY_PROVIDER_LINE = "/^[ \\t]*App\\\\Providers\\\\WhatsAppServiceProvider::class,?[ \\t]*\\r?\\n/m";

    public function __construct(private string $root)
    {
        $this->root = rtrim($root, '/');
    }

    /**
     * @return array<int, string> what was done, for the install log
     */
    public function add(): array
    {
        return array_merge($this->addProvider(), $this->addMenu());
    }

    /**
     * @return array<int, string>
     */
    public function remove(): array
    {
        $notes = [];

        $providers = $this->read('bootstrap/providers.php');
        $updated = $this->stripBlock($providers, self::PROVIDER_START, self::PROVIDER_END);
        if ($updated !== $providers) {
            $this->write('bootstrap/providers.php', $updated);
            $notes[] = 'Removed WhatsApp from bootstrap/providers.php.';
        }

        $layoutPath = 'resources/views/layouts/app.blade.php';
        if (is_file($this->root . '/' . $layoutPath)) {
            $layout = $this->read($layoutPath);
            $updated = $this->stripBlock($layout, self::MENU_START, self::MENU_END);
            $updated = preg_replace('/^[^\n]*' . preg_quote(self::ICON_MARKER, '/') . '[^\n]*\n/m', '', $updated);
            if ($updated !== $layout) {
                $this->write($layoutPath, $updated);
                $notes[] = 'Removed the WhatsApp menu link from the layout.';
            }
        }

        return $notes;
    }

    private function addProvider(): array
    {
        $path = 'bootstrap/providers.php';
        $providers = $this->read($path);

        if (str_contains($providers, self::PROVIDER_START)) {
            return ['bootstrap/providers.php already registers WhatsApp.'];
        }

        // An unguarded line from the earlier activation script is replaced by the guarded block.
        $providers = preg_replace(self::LEGACY_PROVIDER_LINE, '', $providers);

        $closing = strrpos($providers, '];');
        if ($closing === false || !preg_match('/return\s*\[/', $providers)) {
            throw new RuntimeException('bootstrap/providers.php does not have the expected "return [ ... ];" structure.');
        }

        $before = rtrim(substr($providers, 0, $closing));
        $separator = str_ends_with($before, ',') || str_ends_with($before, '[') ? '' : ',';
        $this->write($path, $before . $separator . "\n" . self::PROVIDER_BLOCK . substr($providers, $closing));

        return ['Registered WhatsApp in bootstrap/providers.php.'];
    }

    private function addMenu(): array
    {
        $path = 'resources/views/layouts/app.blade.php';
        if (!is_file($this->root . '/' . $path)) {
            return ['Layout not found; open /whatsapp directly.'];
        }

        $layout = $this->read($path);
        $original = $layout;
        $notes = [];

        if (str_contains($layout, self::MENU_START) || str_contains($layout, "route('whatsapp.inbox')")) {
            $notes[] = 'The WhatsApp menu link is already in the layout.';
        } else {
            $inserted = false;
            foreach (['<div class="nav-section-label">Admin</div>', '<form class="logout-form"'] as $anchor) {
                $position = strpos($layout, $anchor);
                if ($position === false) {
                    continue;
                }

                $lineStart = strrpos(substr($layout, 0, $position), "\n");
                $lineStart = $lineStart === false ? 0 : $lineStart + 1;
                $layout = substr($layout, 0, $lineStart) . self::MENU_BLOCK . substr($layout, $lineStart);
                $inserted = true;
                break;
            }

            $notes[] = $inserted
                ? 'Added the WhatsApp menu link to the layout.'
                : 'Menu anchor not found in the layout; the WhatsApp link was not added. Open /whatsapp directly.';
        }

        if (!str_contains($layout, "str_starts_with(\$routeName, 'whatsapp')") && str_contains($layout, self::ICON_ANCHOR)) {
            $layout = str_replace(self::ICON_ANCHOR, self::ICON_ANCHOR . "\n            str_starts_with(\$routeName, 'whatsapp') => '💬', " . self::ICON_MARKER, $layout);
        }

        if ($layout !== $original) {
            $this->write($path, $layout);
        }

        return $notes;
    }

    private function stripBlock(string $contents, string $start, string $end): string
    {
        return (string) preg_replace('/^[^\n]*' . preg_quote($start, '/') . '.*?' . preg_quote($end, '/') . '[^\n]*\n/ms', '', $contents);
    }

    private function read(string $relative): string
    {
        $contents = @file_get_contents($this->root . '/' . $relative);
        if ($contents === false) {
            throw new RuntimeException("Could not read {$relative}.");
        }

        return $contents;
    }

    private function write(string $relative, string $contents): void
    {
        $path = $this->root . '/' . $relative;

        if (!@copy($path, $path . '.bak-' . date('Ymd-His'))) {
            throw new RuntimeException("Could not back up {$relative}.");
        }

        $temporary = $path . '.tmp-' . bin2hex(random_bytes(4));
        if (@file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new RuntimeException("Could not write {$relative}.");
        }

        @chmod($temporary, fileperms($path) & 0777);
        if (!@rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException("Could not replace {$relative}.");
        }

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }
}
