<?php

namespace App\Services;

use App\Models\AttendanceImport;
use Carbon\Carbon;

class AttendanceMailboxImporter
{
    public function __construct(private AttendanceCsvImporter $csvImporter)
    {
    }

    public function importUnread(): array
    {
        if (!function_exists('imap_open')) {
            return [
                'ok' => false,
                'message' => 'PHP IMAP extension is not enabled on this hosting package.',
                'imports' => [],
            ];
        }

        $mailbox = $this->mailboxString();
        $username = env('ATTENDANCE_MAIL_USERNAME', 'cc@isoadmin.co.za');
        $password = env('ATTENDANCE_MAIL_PASSWORD');

        if (!$password) {
            return [
                'ok' => false,
                'message' => 'ATTENDANCE_MAIL_PASSWORD is missing from .env.',
                'imports' => [],
            ];
        }

        $imap = @imap_open($mailbox, $username, $password);
        if (!$imap) {
            return [
                'ok' => false,
                'message' => 'Could not open attendance mailbox: ' . imap_last_error(),
                'imports' => [],
            ];
        }

        $emails = imap_search($imap, 'UNSEEN') ?: [];
        $imports = [];
        $processedMessages = [];

        foreach ($emails as $messageNumber) {
            $overview = imap_fetch_overview($imap, $messageNumber, 0)[0] ?? null;
            $structure = imap_fetchstructure($imap, $messageNumber);
            $attachments = $this->extractCsvAttachments($imap, $messageNumber, $structure);

            foreach ($attachments as $attachment) {
                /** @var AttendanceImport $import */
                $import = $this->csvImporter->importString($attachment['content'], [
                    'source' => 'email',
                    'source_identifier' => (string) ($overview->message_id ?? ('msg-' . $messageNumber)),
                    'filename' => $attachment['filename'],
                    'received_from' => (string) ($overview->from ?? null),
                    'received_subject' => isset($overview->subject) ? imap_utf8((string) $overview->subject) : null,
                    'received_at' => isset($overview->date) ? Carbon::parse($overview->date) : now(),
                    'imported_by' => null,
                ]);

                $imports[] = $import;
            }

            if (!empty($attachments)) {
                $processedMessages[] = $messageNumber;
            }
        }

        $deleteProcessed = filter_var(env('ATTENDANCE_DELETE_PROCESSED', true), FILTER_VALIDATE_BOOL);
        if ($deleteProcessed) {
            foreach ($processedMessages as $messageNumber) {
                imap_delete($imap, $messageNumber);
            }
            if (!empty($processedMessages)) {
                imap_expunge($imap);
            }
        } else {
            foreach ($processedMessages as $messageNumber) {
                imap_setflag_full($imap, (string) $messageNumber, '\\Seen');
            }
        }

        imap_close($imap);

        return [
            'ok' => true,
            'message' => count($imports) . ' CSV attachment(s) imported from mailbox.',
            'imports' => $imports,
        ];
    }

    private function mailboxString(): string
    {
        $host = env('ATTENDANCE_MAIL_HOST', 'mail.isoadmin.co.za');
        $port = env('ATTENDANCE_MAIL_PORT', 993);
        $encryption = strtolower((string) env('ATTENDANCE_MAIL_ENCRYPTION', 'ssl'));
        $folder = env('ATTENDANCE_MAILBOX', 'INBOX');

        $flags = '/imap';
        if ($encryption === 'ssl') {
            $flags .= '/ssl';
        } elseif ($encryption === 'tls') {
            $flags .= '/tls';
        } else {
            $flags .= '/notls';
        }

        return '{' . $host . ':' . $port . $flags . '}' . $folder;
    }

    private function extractCsvAttachments($imap, int $messageNumber, $structure): array
    {
        $attachments = [];
        $this->walkParts($imap, $messageNumber, $structure, '', $attachments);

        return $attachments;
    }

    private function walkParts($imap, int $messageNumber, $part, string $partNumber, array &$attachments): void
    {
        if (!$part) {
            return;
        }

        if (isset($part->parts) && is_array($part->parts)) {
            foreach ($part->parts as $index => $subPart) {
                $nextPartNumber = $partNumber === '' ? (string) ($index + 1) : $partNumber . '.' . ($index + 1);
                $this->walkParts($imap, $messageNumber, $subPart, $nextPartNumber, $attachments);
            }
            return;
        }

        $filename = $this->filenameFromPart($part);
        if (!$filename || strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'csv') {
            return;
        }

        $body = imap_fetchbody($imap, $messageNumber, $partNumber ?: '1');
        if ((int) ($part->encoding ?? 0) === 3) {
            $body = base64_decode($body);
        } elseif ((int) ($part->encoding ?? 0) === 4) {
            $body = quoted_printable_decode($body);
        }

        $attachments[] = [
            'filename' => $filename,
            'content' => $body,
        ];
    }

    private function filenameFromPart($part): ?string
    {
        foreach (['dparameters', 'parameters'] as $property) {
            if (!empty($part->{$property})) {
                foreach ($part->{$property} as $parameter) {
                    $attribute = strtolower((string) ($parameter->attribute ?? ''));
                    if (in_array($attribute, ['filename', 'name'], true)) {
                        return (string) ($parameter->value ?? '');
                    }
                }
            }
        }

        return null;
    }
}
