<?php

namespace Tests\Unit;

use App\Services\ChatToolRunner;
use PHPUnit\Framework\TestCase;

class ChatToolRunnerTest extends TestCase
{
    public function test_filter_database_mutation_tool_calls_keeps_only_profile_tools(): void
    {
        $input = [
            ['type' => 'function', 'function' => ['name' => 'upsert_commitment', 'arguments' => '{}']],
            ['type' => 'function', 'function' => ['name' => 'unknown_tool', 'arguments' => '{}']],
            ['not' => 'valid'],
        ];

        $filtered = ChatToolRunner::filterDatabaseMutationToolCalls($input);

        $this->assertCount(1, $filtered);
        $this->assertSame('upsert_commitment', data_get($filtered[0], 'function.name'));
    }

    public function test_database_mutation_tool_names_includes_all_run_match_tools(): void
    {
        $names = ChatToolRunner::databaseMutationToolNames();

        $this->assertContains('upsert_income_source', $names);
        $this->assertContains('delete_debt', $names);
        $this->assertCount(6, $names);
    }
}
