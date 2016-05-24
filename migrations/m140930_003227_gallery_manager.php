<?php

namespace aquy\gallery\migrations;

use yii\db\Schema;
use yii\db\Migration;

class m140930_003227_gallery_manager extends Migration
{
    public $tableName = '{{%gallery}}';

    public function up()
    {

        $this->createTable(
            $this->tableName,
            array(
                'id'            => $this->primaryKey(),
                'type'          => $this->string(300),
                'ownerId'       => $this->string(300)->notNull(),
                'src'           => $this->string(300),
                'sort'          => $this->integer()->notNull()->defaultValue(0),
                'name'          => $this->string(300),
                'description'   => $this->text()
            )
        );

        $this->createIndex('type', $this->tableName, 'type');
        $this->createIndex('ownerId', $this->tableName, 'ownerId');
        $this->createIndex('sort', $this->tableName, 'sort');
    }

    public function down()
    {
        $this->dropTable($this->tableName);
    }
}
