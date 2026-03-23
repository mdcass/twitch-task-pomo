<?php

namespace App\Support\Mail;

use Illuminate\Mail\Markdown;
use Illuminate\Support\HtmlString;

class MarkdownSlot
{
    public static function toHtml(?string $content): HtmlString
    {
        return Markdown::parse(static::normalize($content));
    }

    public static function toText(?string $content): string
    {
        return strip_tags(static::normalize($content));
    }

    public static function normalize(?string $content): string
    {
        if ($content === null || $content === '') {
            return '';
        }

        $lines = preg_split("/\r\n|\n|\r/", $content);

        if ($lines === false || $lines === []) {
            return $content;
        }

        $normalizedLines = [];
        $block = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                array_push($normalizedLines, ...static::normalizeBlock($block));
                $normalizedLines[] = '';
                $block = [];

                continue;
            }

            $block[] = $line;
        }

        array_push($normalizedLines, ...static::normalizeBlock($block));

        return implode("\n", $normalizedLines);
    }

    /**
     * @param  array<int, string>  $block
     * @return array<int, string>
     */
    private static function normalizeBlock(array $block): array
    {
        if ($block === []) {
            return [];
        }

        $minimumIndent = null;

        foreach ($block as $line) {
            if (str_starts_with(ltrim($line), '<')) {
                continue;
            }

            preg_match('/^[\t ]*/', $line, $matches);

            $indent = strlen($matches[0] ?? '');
            $minimumIndent = $minimumIndent === null ? $indent : min($minimumIndent, $indent);
        }

        return array_map(
            static function (string $line) use ($minimumIndent): string {
                if (str_starts_with(ltrim($line), '<')) {
                    return ltrim($line);
                }

                if (($minimumIndent ?? 0) === 0) {
                    return $line;
                }

                return (string) preg_replace('/^[\t ]{0,'.$minimumIndent.'}/', '', $line, 1);
            },
            $block
        );
    }
}
