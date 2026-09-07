<?php

use yii\db\Migration;

class m260904_000001_create_local_link_preview extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%local_link_preview}}', [
            'id' => $this->primaryKey(),
            'url_hash' => $this->string(64)->notNull()->unique(),
            'url' => $this->text()->notNull(),
            'title' => $this->string(512),
            'description' => $this->text(),
            'site_name' => $this->string(255),
            'image_file' => $this->string(255),
            'image_mime' => $this->string(100),
            'fetched_at' => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx-local-link-preview-fetched', '{{%local_link_preview}}', 'fetched_at');
    }

    public function safeDown()
    {
        $this->dropTable('{{%local_link_preview}}');
    }
}
