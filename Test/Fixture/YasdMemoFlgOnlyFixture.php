<?php
class YasdMemoFlgOnlyFixture extends CakeTestFixture {
    public $name = 'YasdMemoFlgOnly';

    public $fields = array(
        'id' => array('type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 20, 'key' => 'primary'),
        'yasd_post_flg_only_id' => array('type' => 'integer', 'null' => true, 'default' => NULL),
        'memo' => array('type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'utf8_general_ci', 'charset' => 'utf8'),
        'created' => array('type' => 'datetime', 'null' => true, 'default' => NULL),
        'modified' => array('type' => 'datetime', 'null' => true, 'default' => NULL),
        'delete_flg' => array('type' => 'integer', 'null' => true, 'default' => NULL),
    );

    public $records = array(
        array(
            'yasd_post_flg_only_id' => '1',
            'memo' => 'Memo',
            'created' => '2012-07-05 00:00:00',
            'modified' => '2012-07-05 00:00:00',
            'delete_flg' => null
        ),
        array(
            'yasd_post_flg_only_id' => '2',
            'memo' => 'Memo2',
            'created' => '2012-07-05 00:00:00',
            'modified' => '2012-07-05 00:00:00',
            'delete_flg' => null
        ),
    );
}
