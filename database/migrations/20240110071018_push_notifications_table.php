<?php
declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class PushNotificationsTable extends AbstractMigration
{
    public function up(): void
    {
        $this->table('push_notifications')
            ->addColumn('title', 'string', ['limit' => 50])
            ->addColumn('message', 'text')
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->create();
    }

    public function down(): void
    {
        $this->table('push_notifications')
            ->drop();
    }
}
