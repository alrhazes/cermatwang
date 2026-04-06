<?php

namespace Tests\Unit;

use App\Support\ChatAssistantContentSanitizer;
use PHPUnit\Framework\TestCase;

class ChatAssistantContentSanitizerTest extends TestCase
{
    public function test_strips_function_xml_blocks(): void
    {
        $raw = <<<'TEXT'
Here is your plan.

<function=upsert_commitment>
{"amount_cents":60000,"cadence":"monthly","category":"Other","currency":"MYR","due_day":null,"is_active":true,"name":"General Monthly expenses"}
</function>

Thanks!
TEXT;

        $clean = ChatAssistantContentSanitizer::stripInlineToolMarkup($raw);

        $this->assertStringNotContainsString('<function=', $clean);
        $this->assertStringNotContainsString('amount_cents', $clean);
        $this->assertStringContainsString('Here is your plan.', $clean);
        $this->assertStringContainsString('Thanks!', $clean);
    }
}
