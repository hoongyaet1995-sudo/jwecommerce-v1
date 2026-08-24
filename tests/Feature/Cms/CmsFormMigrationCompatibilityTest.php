<?php

namespace Tests\Feature\Cms;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CmsFormMigrationCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_table_index_names_fit_the_mysql_identifier_limit(): void
    {
        foreach (['cms_contact_submissions', 'newsletter_subscriptions'] as $table) {
            foreach (Schema::getIndexes($table) as $index) {
                $this->assertLessThanOrEqual(
                    64,
                    strlen($index['name']),
                    "{$table}.{$index['name']} exceeds MySQL's 64-character identifier limit.",
                );
            }
        }
    }
}
