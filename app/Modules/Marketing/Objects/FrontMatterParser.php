<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Objects;

use Illuminate\Support\Str;
use League\CommonMark\Extension\FrontMatter\Data\FrontMatterDataParserInterface;
use League\CommonMark\Extension\FrontMatter\Exception\InvalidFrontMatterException;

/**
 * Reads the front matter of the docs and legal Markdown
 * files, which is a flat list of `key: value` lines whose
 * values are quoted strings or booleans. It stands in for
 * a YAML parser, which the application does not ship with
 * in production.
 */
class FrontMatterParser implements FrontMatterDataParserInterface
{
    /**
     * Parse the front matter into its keys and values.
     *
     * @return array<string, mixed>
     */
    public function parse(string $frontMatter): array
    {
        $data = [];

        foreach (preg_split('/\R/', $frontMatter) ?: [] as $line) {
            // Blank lines and the --- fences around the
            // block carry nothing.
            if (in_array(Str::trim($line), ['', '---'], true)) {
                continue;
            }

            // Every other line is a key and its value.
            if (preg_match('/^([A-Za-z_][\w-]*):\s*(.*)$/', $line, $matches) !== 1) {
                throw new InvalidFrontMatterException("Invalid front matter line: {$line}");
            }

            // A quoted string or a boolean reads as JSON;
            // anything else is kept as the bare text.
            $value = Str::trim($matches[2]);
            $decoded = json_decode($value, true);

            $data[$matches[1]] = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        return $data;
    }
}
