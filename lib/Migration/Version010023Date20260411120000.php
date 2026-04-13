<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010023Date20260411120000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('custom_projects')) {
            return $schema;
        }

        $table = $schema->getTable('custom_projects');
        if (!$table->hasColumn('talk_conversation_token')) {
            $table->addColumn('talk_conversation_token', Types::STRING, [
                'notnull' => false,
                'length' => 64,
                'default' => null,
            ]);
        }

        if (!$table->hasIndex('custom_projects_talk_token_idx')) {
            $table->addIndex(['talk_conversation_token'], 'custom_projects_talk_token_idx');
        }

        return $schema;
    }
}
