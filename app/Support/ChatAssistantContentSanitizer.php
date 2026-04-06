<?php

namespace App\Support;

/**
 * Some models leak pseudo-tool markup into the assistant message body (e.g. XML-like tool tags)
 * instead of using structured tool_calls. Strip that before sending text to the client.
 */
final class ChatAssistantContentSanitizer
{
    public static function stripInlineToolMarkup(string $content): string
    {
        $stripped = preg_replace('/<function=[a-zA-Z0-9_]+>\s*[\s\S]*?<\/function>/i', '', $content) ?? $content;
        $stripped = preg_replace("/\n{3,}/", "\n\n", $stripped) ?? $stripped;

        return trim($stripped);
    }
}
