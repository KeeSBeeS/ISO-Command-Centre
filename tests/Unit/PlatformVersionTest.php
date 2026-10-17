<?php

namespace Tests\Unit;

use App\Support\PlatformVersion;
use Tests\TestCase;

class PlatformVersionTest extends TestCase
{
    public function test_version_is_read_from_the_version_file(): void
    {
        $this->assertSame(trim(file_get_contents(base_path('VERSION'))), PlatformVersion::current());
    }

    public function test_version_is_semantic(): void
    {
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', PlatformVersion::current());
    }

    public function test_changelog_has_an_entry_for_the_current_version(): void
    {
        preg_match('/^## v(\d+\.\d+\.\d+)\b/m', file_get_contents(base_path('CHANGELOG.md')), $matches);

        $this->assertSame(PlatformVersion::current(), $matches[1] ?? null, 'The newest CHANGELOG.md entry must match the VERSION file.');
    }
}
