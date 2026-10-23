<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppTemplate extends Model
{
    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'remote_id',
        'name',
        'language',
        'category',
        'status',
        'parameter_format',
        'components',
        'synced_at',
    ];

    protected $casts = [
        'components' => 'array',
        'synced_at' => 'datetime',
    ];

    public function scopeApproved($query)
    {
        return $query->where('status', 'APPROVED');
    }

    public function component(string $type): ?array
    {
        foreach ((array) $this->components as $component) {
            if (strtoupper((string) ($component['type'] ?? '')) === strtoupper($type)) {
                return $component;
            }
        }

        return null;
    }

    public function getBodyTextAttribute(): string
    {
        return (string) ($this->component('BODY')['text'] ?? '');
    }

    public function getHeaderFormatAttribute(): ?string
    {
        $header = $this->component('HEADER');

        return $header ? strtoupper((string) ($header['format'] ?? 'TEXT')) : null;
    }

    /**
     * Placeholder names in the body, in order of first appearance: ['1', '2'] or ['customer_name'].
     */
    public function bodyPlaceholders(): array
    {
        return self::placeholdersIn($this->body_text);
    }

    public function headerPlaceholders(): array
    {
        return $this->header_format === 'TEXT'
            ? self::placeholdersIn((string) ($this->component('HEADER')['text'] ?? ''))
            : [];
    }

    /**
     * Templates with media headers or dynamic URL buttons need extra assets the platform does not collect.
     */
    public function isSendable(): bool
    {
        if ($this->status !== 'APPROVED') {
            return false;
        }

        if (in_array($this->header_format, ['IMAGE', 'VIDEO', 'DOCUMENT', 'LOCATION'], true)) {
            return false;
        }

        foreach ((array) ($this->component('BUTTONS')['buttons'] ?? []) as $button) {
            if (strtoupper((string) ($button['type'] ?? '')) === 'URL' && str_contains((string) ($button['url'] ?? ''), '{{')) {
                return false;
            }
        }

        return true;
    }

    public function render(array $params): string
    {
        $text = $this->body_text;
        foreach ($this->bodyPlaceholders() as $index => $name) {
            $value = $params[$index] ?? ('{{' . $name . '}}');
            $text = preg_replace('/\{\{\s*' . preg_quote($name, '/') . '\s*\}\}/', str_replace(['\\', '$'], ['\\\\', '\\$'], (string) $value), $text);
        }

        return $text;
    }

    public static function placeholdersIn(string $text): array
    {
        preg_match_all('/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/', $text, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }
}
