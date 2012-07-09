<?php
App::uses('Model', 'Model');
App::uses('AppModel', 'Model');

class YasdPost extends CakeTestModel{

    public $name = 'YasdPost';
    public $actsAs = array('Yasd.SoftDeletable');

    public $hasOne = array(
        'YasdMemo' => array(
            'className' => 'YasdMemo',
            'foreignKey' => 'yasd_post_id',
            'dependent' => true,
        )
    );

    public $hasMany = array(
        'YasdComment' => array(
            'className' => 'YasdComment',
            'foreignKey' => 'yasd_post_id',
            'dependent' => true,
        )
    );
}

class YasdMemo extends CakeTestModel{

    public $name = 'YasdMemo';
    public $actsAs = array('Yasd.SoftDeletable');

    public $belongsTo = array(
        'YasdPost' => array(
            'className' => 'YasdPost',
            'foreignKey' => 'yasd_post_id',
        )
    );
}

class YasdComment extends CakeTestModel{

    public $name = 'YasdComment';
    public $actsAs = array('Yasd.SoftDeletable');

    public $belongsTo = array(
        'YasdPost' => array(
            'className' => 'YasdPost',
            'foreignKey' => 'yasd_post_id',
        )
    );
}

class SoftDeletableTestCase extends CakeTestCase{

    public $fixtures = array('plugin.Yasd.yasd_post',
                             'plugin.Yasd.yasd_memo',
                             'plugin.Yasd.yasd_comment');

    function setUp() {
        $this->YasdPost = new YasdPost();
        $this->YasdPostFixture = ClassRegistry::init('YasdPostFixture');
        $this->YasdPost->enableSoftDeletable();
        $this->YasdPost->YasdMemo->enableSoftDeletable();
        $this->YasdPost->YasdComment->enableSoftDeletable();
    }

    function tearDown() {
        unset($this->YasdPost);
        unset($this->YasdPostFixture);
    }

    /**
     * testFind
     *
     * jpn: 通常のfind
     *      delete_flgがnullでもfind可能なのがOriginalと大きく異なる点
     */
    public function testFind(){
        $result = $this->YasdPost->find('all');
        $this->assertIdentical(count($result), 2);
    }

    /**
     * testFindCount
     *
     * jpn: 通常のcount
     */
    public function testFindCount(){
        $result = $this->YasdPost->find('count');
        $this->assertIdentical($result, 2);
    }

    /**
     * testFindWithCondition
     *
     */
    public function testFindWithCondition(){
        $result = $this->YasdPost->find('all', array('conditions' => array('YasdPost.id' => 2)));
        $this->assertIdentical(count($result), 1);

        // jpn: 文字列指定も可
        $result = $this->YasdPost->find('all', array('conditions' => 'YasdPost.id = 2'));
        $this->assertIdentical(count($result), 1);
    }

    /**
     * testDependentHasOneFind
     *
     * jpn: hasOneで紐づいているModelについてもSoftDeletable属性をチェックする
     */
    public function testDependentHasOneFind(){
        $result = $this->YasdPost->findById(1);
        $this->assertIdentical((string)$result['YasdMemo']['id'], '1');

        $this->YasdPost->YasdMemo->delete(1);
        $result = $this->YasdPost->findById(1);
        $this->assertIdentical($result['YasdMemo']['id'], null);
    }

    /**
     * testDependentHasManyFind
     *
     * jpn: hasManyで紐づいているModelについてもSoftDeletable属性をチェックする
     */
    public function testDependentHasManyFind(){
        $result = $this->YasdPost->findById(1);
        $this->assertIdentical(count($result['YasdComment']), 2);

        $this->YasdPost->YasdComment->delete(1);
        $result = $this->YasdPost->findById(1);
        $this->assertIdentical(count($result['YasdComment']), 1);
    }

    /**
     * testSoftDeletableFind
     *
     */
    public function testSoftDeletableFind(){
        // jpn: SoftDeletableが有効の時にはModel::delete()がfalseになる
        $result = $this->YasdPost->delete(1);
        $this->assertFalse($result);

        $result = $this->YasdPost->find('all');
        $this->assertIdentical(count($result), 1);

        $this->YasdPost->disableSoftDeletable();
        $result = $this->YasdPost->find('all');
        $this->assertIdentical(count($result), 2);
    }

    /**
     * testDependentHasOneSoftDelete
     *
     * jpn: hasOne先のdependentされたModelのSoftDeletableが有効に発動する
     */
    public function testDependentHasOneSoftDelete(){
        $result = $this->YasdPost->YasdMemo->find('all');
        $this->assertIdentical(count($result), 2);

        $this->YasdPost->delete(1);

        $result = $this->YasdPost->YasdMemo->find('all');
        $this->assertIdentical(count($result), 1);

        $this->YasdPost->YasdMemo->disableSoftDeletable();
        $result = $this->YasdPost->YasdMemo->find('all');
        $this->assertIdentical(count($result), 2);
    }

    /**
     * testDependentHasManySoftDelete
     *
     * jpn: hasMany先のdependentされたModelのSoftDeletableが有効に発動する
     */
    public function testDependentHasManySoftDelete(){
        $result = $this->YasdPost->YasdComment->find('all');
        $this->assertIdentical(count($result), 2);

        $this->YasdPost->delete(1);

        $result = $this->YasdPost->YasdComment->find('all');
        $this->assertIdentical(count($result), 0);

        $this->YasdPost->YasdComment->disableSoftDeletable();
        $result = $this->YasdPost->YasdComment->find('all');
        $this->assertIdentical(count($result), 2);
    }

    /**
     * testHardDelete
     *
     * jpn: SoftDeletableを無効にして削除ができる
     */
    public function testHardDelete(){
        $this->YasdPost->disableSoftDeletable();
        $this->YasdPost->delete(1);
        $result = $this->YasdPost->find('all');
        $this->assertIdentical(count($result), 1);
    }
}
