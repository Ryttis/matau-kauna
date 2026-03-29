<?php

namespace App\Services;

use App\Support\EmailBody;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TransactionalMail
{
    public static function send(string $to, string $subject, string $plainBody, ?string $actionUrl = null, ?string $actionLabel = null): void
    {
        $plainBody = self::stripStandaloneUrlLines($plainBody, $actionUrl);

        $contentHtml = self::bodyMarkdownToHtml($plainBody);
        $contentText = self::markdownHtmlToPlainEmail($contentHtml);

        Mail::send(
            ['html' => 'mail.transactional-html', 'text' => 'mail.transactional-text'],
            [
                'mailBrandName' => config('mail.brand_name'),
                'contentHtml' => $contentHtml,
                'contentText' => $contentText,
                'actionUrl' => $actionUrl,
                'actionLabel' => $actionLabel ?? 'Atidaryti',
            ],
            function ($message) use ($to, $subject): void {
                $message->from(config('mail.from.address'), config('mail.from.name'))
                    ->to($to)
                    ->subject($subject);
            }
        );
    }

    private static function bodyMarkdownToHtml(string $body): string
    {
        return trim(Str::markdown($body, EmailBody::markdownConverterOptions()));
    }

    private static function markdownHtmlToPlainEmail(string $html): string
    {
        $html = preg_replace_callback(
            '~<a\s[^>]*\bhref=(["\'])(.*?)\1[^>]*>(.*?)</a>~is',
            function (array $m): string {
                $href = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $inner = trim(html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($inner === '' || strcasecmp($inner, $href) === 0
                    || str_starts_with(strtolower($inner), 'http://') || str_starts_with(strtolower($inner), 'https://')) {
                    return self::linkLabelForUrl($href).': '.$href;
                }

                return $inner.': '.$href;
            },
            $html
        ) ?? $html;

        $html = preg_replace('~</p>\s*<p[^>]*>~i', "\n\n", $html);
        $html = preg_replace('~<br\s*/?>~i', "\n", $html);
        $html = preg_replace('~</p>~i', "\n\n", $html);
        $html = preg_replace('~<p[^>]*>~i', '', $html);

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/[\r\n]{3,}/", "\n\n", $text));
    }

    private static function linkLabelForUrl(string $url): string
    {
        $lower = Str::lower($url);
        if (Str::contains($lower, '/login') || Str::contains($lower, 'login?')) {
            return 'Prisijungti';
        }
        if (Str::contains($lower, '/register') || Str::contains($lower, 'invite')) {
            return 'Registracija';
        }

        return 'Problema';
    }

    private static function stripStandaloneUrlLines(string $body, ?string $url): string
    {
        if ($url === null || $url === '') {
            return $body;
        }

        $lines = preg_split("/\r\n|\n|\r/", $body);
        $out = [];
        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === trim($url)) {
                continue;
            }
            if (preg_match('/^Peržiūrėti:\s*/iu', $t)) {
                continue;
            }
            $out[] = $line;
        }

        return implode("\n", $out);
    }
}
