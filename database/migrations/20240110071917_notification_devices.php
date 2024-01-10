<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class NotificationDevices extends AbstractMigration
{
    public function up(): void
    {
        $this->table('notification_devices')
            ->addColumn('notification_id', 'integer')
            ->addColumn('device_id','integer')
            ->addColumn('status', 'integer', ['default' => 0]) // 0- Queued, 1- In-Progress, 2-Sent, 3- Failed
            ->addForeignKey('device_id', 'devices', 'id', ['delete'=> 'NO_ACTION', 'update'=> 'NO_ACTION'])
            ->create();
    }

    public function down(): void
    {
        $this->table('notification_devices')
            ->drop();
    }
}
