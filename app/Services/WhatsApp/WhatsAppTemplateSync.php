<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppTemplate;

class WhatsAppTemplateSync
{
    public function __construct(private WhatsAppCloudApi $api)
    {
    }

    /**
     * Mirror the WABA's templates locally. Templates removed in Meta are deleted locally.
     */
    public function sync(): array
    {
        $remote = $this->api->fetchTemplates();
        $seen = [];

        foreach ($remote as $item) {
            if (empty($item['name']) || empty($item['language'])) {
                continue;
            }

            $template = WhatsAppTemplate::updateOrCreate(
                ['name' => $item['name'], 'language' => $item['language']],
                [
                    'remote_id' => $item['id'] ?? null,
                    'category' => $item['category'] ?? null,
                    'status' => strtoupper((string) ($item['status'] ?? 'UNKNOWN')),
                    'parameter_format' => $item['parameter_format'] ?? 'POSITIONAL',
                    'components' => $item['components'] ?? [],
                    'synced_at' => now(),
                ]
            );
            $seen[] = $template->id;
        }

        $removed = WhatsAppTemplate::whereNotIn('id', $seen ?: [0])->delete();

        return ['synced' => count($seen), 'removed' => $removed];
    }
}
