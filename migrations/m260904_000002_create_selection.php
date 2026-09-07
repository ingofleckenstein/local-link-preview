<?php

use yii\db\Migration;

class m260904_000002_create_selection extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%local_link_preview_selection}}', [
            'id' => $this->primaryKey(),
            'post_id' => $this->integer()->notNull()->unique(),
            'url' => $this->text()->notNull(),
            'hide_image' => $this->boolean()->notNull()->defaultValue(false),
        ]);
        $this->addForeignKey('fk-llp-selection-post', '{{%local_link_preview_selection}}', 'post_id', '{{%post}}', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('{{%local_link_preview_selection}}');
    }
}
