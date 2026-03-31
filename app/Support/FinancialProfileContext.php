<?php

namespace App\Support;

/**
 * Renders stored financial fields for the model (future: filled by tools / imports).
 */
final class FinancialProfileContext
{
    /**
     * @param  array<string, mixed>  $profile
     */
    public static function promptSection(array $profile): string
    {
        $lines = [];
        foreach ($profile as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            if (is_string($value) || is_int($value) || is_float($value)) {
                $str = trim((string) $value);
                if ($str !== '') {
                    $label = str_replace('_', ' ', $key);
                    $lines[] = "- **{$label}:** {$str}";
                }
            }
        }

        if ($lines === []) {
            return <<<'TEXT'


## Saved financial profile (app)
**No structured fields on file yet.** Do **not** make up numbers. Treat **this entire chat transcript** (user + assistant messages below) as your working client notes: if they already stated income, loans, or card payments, **remember and refer to those facts**—do not ask again unless you need a clarification. Still sound like a **personal advisor** reviewing their file with them, not a blank chatbot.

TEXT;
        }

        return "\n\n## Saved financial profile (from app — accurate until they correct you)\n".implode("\n", $lines)."\n";
    }
}
