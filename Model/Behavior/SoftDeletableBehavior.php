<?php
/**
 * SoftDeletableBehavior
 *
 */
App::uses('YasdException', 'Yasd.Error');
/**
 * Original: SoftDeletable Behavior class file.
 *
 * @filesource
 * @author Mariano Iglesias
 * @link http://cake-syrup.sourceforge.net/ingredients/soft-deletable-behavior/
 * @version     $Revision: 2265 $
 * @license     http://www.opensource.org/licenses/mit-license.php The MIT License
 * @package app
 * @subpackage app.models.behaviors
 */
class SoftDeletableBehavior extends ModelBehavior {

    public $settings = array();
    public $bindingConditions = array();

    public function setUp(Model $model, $settings = array()) {
        $defaults = array(
            'field' => 'delete_flg',
            'field_date' => 'deleted',
            'enable' => true,
        );
        // Default settings
        $this->settings[$model->alias] = Set::merge($defaults, $settings);
        $this->settings[$model->alias]['hasField'] = $model->hasField($this->settings[$model->alias]['field']);
        $this->settings[$model->alias]['hasFieldDate'] = $model->hasField($this->settings[$model->alias]['field_date']);
    }

    /**
     * beforeFind
     *
     * @param Model $model, $queryData
     */
    public function beforeFind(Model $model, $queryData){
        if (!$this->enabled($model)) {
            return $queryData;
        }
        if ($this->settings[$model->alias]['hasField'] && $this->settings[$model->alias]['field']) {
            return $this->buildConditionWithFieldOption($model, $queryData);
        } else if ($this->settings[$model->alias]['hasFieldDate'] && $this->settings[$model->alias]['field_date']) {
            return $this->buildConditionWithFieldDateOption($model, $queryData);
        } else {
            throw new YasdException();
        }
    }

    public function beforeDelete(Model $model, $cascade = true) {
        if ($this->enabled($model)) {
            $this->softDelete($model, $model->id, $cascade);
            return false;
        }
        return true;
    }

    /**
     * afterFind
     *
     */
    public function afterFind(Model $model, $results, $primary = false) {
        foreach(array('hasOne', 'hasMany', 'hasAndBelongsToMany') as $binding) {
            if (empty($model->{$binding})) {
                continue;
            }
            foreach ($model->{$binding} as $assoc => $value) {
                if (!$model->{$assoc}->hasField($this->settings[$model->alias]['field'])) {
                    continue;
                }
                if (!$model->{$assoc}->hasField($this->settings[$model->alias]['field_date'])) {
                    continue;
                }
                $model->{$binding}[$assoc]['conditions'] = $this->bindingConditions[$binding][$assoc];
            }
        }
        return $results;
    }

    public function softDelete(Model $model, $id, $cascade = false) {
        if (!$this->enabled($model)) {
            throw new YasdException();
        }
        $attributes = $this->settings[$model->alias];

        $data = array($model->alias => array());
        if ($attributes['field']) {
            $data[$model->alias][$attributes['field']] = 1;
        };

        if ($attributes['field_date']) {
            $data[$model->alias][$attributes['field_date']] = date('Y-m-d H:i:s');;
        };

        foreach(array_merge(array_keys($data[$model->alias]), array('field', 'field_date', 'find', 'delete')) as $field) {
            unset($attributes[$field]);
        }

        if (!empty($attributes)) {
            $data[$model->alias] = array_merge($data[$model->alias], $attributes);
        }

        $model->id = $id;
        $deleted = $model->save($data, false, array_keys($data[$model->alias]));

        if ($deleted && $cascade) {
            foreach(array('hasOne', 'hasMany') as $binding) {
                if (empty($model->$binding)) {
                    continue;
                }
                foreach ($model->$binding as $assoc => $data) {
                    if (!array_key_exists('dependent', $data)) {
                        $model->{$binding}[$assoc]['dependent'] = false;
                    }
                }
            }
            $this->_deleteDependent($model, $id, $cascade);
            $this->_deleteLinks($model, $id);
        }

        return !empty($deleted);
    }

    /**
     * enableSoftDeletable
     *
     * @param Model $model
     */
    public function enableSoftDeletable(Model $model){
        $this->settings[$model->alias]['enable'] = true;
    }

    /**
     * disableSoftDeletable
     *
     * @param Model $model
     */
    public function disableSoftDeletable(Model $model){
        $this->settings[$model->alias]['enable'] = false;
    }

    /**
     * softDeleteAll
     *
     * @param Model $model, $conditions
     */
    public function softDeleteAll(Model $model, $conditions = true){
        if (!$this->enabled($model)) {
            throw new YasdException();
        }
        $attributes = $this->settings[$model->alias];
        $fields = array();
        if ($attributes['field']) {
            $fields[$attributes['field']] = 1;
        }
        if ($attributes['field_date']) {
            $fields[$attributes['field_date']] = "'" . date('Y-m-d H:i:s') . "'";
        }
        return $model->updateAll($fields, $conditions);
    }

    /**
     * Cascades model deletes through associated hasMany and hasOne child records.
     *
     * @see CakePHP 2.1.3 lib/Cake/Model/Model.php
     * @param string $id ID of record that was deleted
     * @param boolean $cascade Set to true to delete records that depend on this record
     * @return void
     */
    protected function _deleteDependent(Model $model, $id, $cascade) {
        if (!empty($model->__backAssociation)) {
            $savedAssociatons = $model->__backAssociation;
            $model->__backAssociation = array();
        }
        if ($cascade === true) {
            foreach (array_merge($model->hasMany, $model->hasOne) as $assoc => $data) {
                if ($data['dependent'] === true) {

                    $dependentModel = $model->{$assoc};

                    if ($data['foreignKey'] === false && $data['conditions'] && in_array($model->name, $dependentModel->getAssociated('belongsTo'))) {
                        $dependentModel->recursive = 0;
                        $conditions = array($model->escapeField(null, $model->name) => $id);
                    } else {
                        $dependentModel->recursive = -1;
                        $conditions = array($dependentModel->escapeField($data['foreignKey']) => $id);
                        if ($data['conditions']) {
                            $conditions = array_merge((array)$data['conditions'], $conditions);
                        }
                    }

                    if (isset($data['exclusive']) && $data['exclusive']) {
                        $dependentModel->deleteAll($conditions);
                    } else {
                        $records = $dependentModel->find('all', array(
                            'conditions' => $conditions, 'fields' => $dependentModel->primaryKey
                        ));
                        if (!empty($records)) {
                            foreach ($records as $record) {
                                $dependentModel->delete($record[$dependentModel->alias][$dependentModel->primaryKey]);
                            }
                        }
                    }
                }
            }
        }
        if (isset($savedAssociatons)) {
            $model->__backAssociation = $savedAssociatons;
        }
    }

    /**
     * Cascades model deletes through HABTM join keys.
     *
     * @see CakePHP 2.1.3 lib/Cake/Model/Model.php
     * @param string $id ID of record that was deleted
     * @return void
     */
    protected function _deleteLinks(Model $model, $id) {
        foreach ($model->hasAndBelongsToMany as $assoc => $data) {
            list($plugin, $joinModel) = pluginSplit($data['with']);
            $records = $model->{$joinModel}->find('all', array(
                'conditions' => array($model->{$joinModel}->escapeField($data['foreignKey']) => $id),
                'fields' => $model->{$joinModel}->primaryKey,
                'recursive' => -1,
                'callbacks' => false
            ));
            if (!empty($records)) {
                foreach ($records as $record) {
                    $model->{$joinModel}->delete($record[$model->{$joinModel}->alias][$model->{$joinModel}->primaryKey]);
                }
            }
        }
    }

    /**
     * enabled
     *
     */
    private function enabled(Model $model){
        if (!$this->settings[$model->alias]['enable']) {
            return false;
        }

        if (!$this->settings[$model->alias]['hasField']
            && $this->settings[$model->alias]['field']
        ) {
            return false;
        }

        if (!$this->settings[$model->alias]['hasFieldDate']
            && $this->settings[$model->alias]['field_date']
        ) {
            return false;
        }

        return true;
    }

    /**
     * buildConditionWithFieldOption
     *
     */
    private function buildConditionWithFieldOption(Model $model, $queryData) {
        $Db = ConnectionManager::getDataSource($model->useDbConfig);
        $include = false;

        if (!$this->settings[$model->alias]['hasField'] || !$this->settings[$model->alias]['field']) {
            throw new YasdException();
        }
        $checkFieldName = $this->settings[$model->alias]['field'];

        if (!empty($queryData['conditions']) && is_string($queryData['conditions'])) {
            // string condition
            $include = true;

            $fields = array(
                $Db->name($model->alias) . '.' . $Db->name($checkFieldName),
                $Db->name($checkFieldName),
                $model->alias . '.' . $checkFieldName,
                $checkFieldName
            );

            foreach($fields as $field) {
                if (preg_match('/^' . preg_quote($field) . '[\s=!]+/i', $queryData['conditions']) || preg_match('/\\x20+' . preg_quote($field) . '[\s=!]+/i', $queryData['conditions'])) {
                    $include = false;
                    break;
                }
            }
        } else if (empty($queryData['conditions'])) {
            $include = true;
        }  else if (
            !in_array($checkFieldName, array_keys($queryData['conditions']), true)
            && !in_array($model->alias . '.' . $checkFieldName, array_keys($queryData['conditions']), true)
        ) {
            $include = true;
        }

        if ($include) {
            if (empty($queryData['conditions'])) {
                $queryData['conditions'] = array();
            }

            if (is_string($queryData['conditions'])) {
                $queryData['conditions'] = '(' . $Db->name($model->alias) . '.' . $Db->name($checkFieldName) . ' IS NULL OR ' . $Db->name($model->alias) . '.' . $Db->name($checkFieldName) . '!= 1) AND ' . $queryData['conditions'];
            } else {
                $queryData['conditions'][] = array('OR' => array(array($model->alias . '.' . $checkFieldName => '0'),
                                                                 array($model->alias . '.' . $checkFieldName => null)));
            }
        }

        foreach(array('hasOne', 'hasMany', 'hasAndBelongsToMany') as $binding) {
            if (empty($model->{$binding})) {
                continue;
            }
            $this->bindingConditions[$binding] = array();
            foreach ($model->{$binding} as $assoc => $value) {
                if (!$model->{$assoc}->hasField($checkFieldName)) {
                    continue;
                }
                $this->bindingConditions[$binding][$assoc] = $model->{$binding}[$assoc]['conditions'];
                if (empty($model->{$binding}[$assoc]['conditions'])) {
                    $model->{$binding}[$assoc]['conditions'] = array('OR' => array(array($assoc . '.' . $checkFieldName => '0'),
                                                                                   array($assoc . '.' . $checkFieldName => null)));
                } else if(is_string($model->{$binding}[$assoc]['conditions'])) {
                    $model->{$binding}[$assoc]['conditions'] = '(' . $Db->name($assoc) . '.' . $Db->name($checkFieldName) . ' IS NULL OR ' . $Db->name($assoc) . '.' . $Db->name($checkFieldName) . '!= 1) AND ' . $model->{$binding}[$assoc]['conditions'];
                } else {
                    $model->{$binding}[$assoc]['conditions'][] = array('OR' => array(array($assoc . '.' . $checkFieldName => '0'),
                                                                                     array($assoc . '.' . $checkFieldName => null)));
                }
            }
        }
        return $queryData;
    }

    /**
     * buildConditionWithFieldDateOption
     *
     */
    private function buildConditionWithFieldDateOption(Model $model, $queryData) {
        $Db = ConnectionManager::getDataSource($model->useDbConfig);
        $include = false;

        if (!$this->settings[$model->alias]['hasFieldDate'] || !$this->settings[$model->alias]['field_date']) {
            throw new YasdException();
        }

        $checkFieldName = $this->settings[$model->alias]['field_date'];

        if (!empty($queryData['conditions']) && is_string($queryData['conditions'])) {
            // string condition
            $include = true;

            $fields = array(
                $Db->name($model->alias) . '.' . $Db->name($checkFieldName),
                $Db->name($checkFieldName),
                $model->alias . '.' . $checkFieldName,
                $checkFieldName
            );

            foreach($fields as $field) {
                if (preg_match('/^' . preg_quote($field) . '[\s=!]+/i', $queryData['conditions']) || preg_match('/\\x20+' . preg_quote($field) . '[\s=!]+/i', $queryData['conditions'])) {
                    $include = false;
                    break;
                }
            }
        } else if (empty($queryData['conditions'])) {
            $include = true;
        }  else if (
            !in_array($checkFieldName, array_keys($queryData['conditions']), true)
            && !in_array($model->alias . '.' . $checkFieldName, array_keys($queryData['conditions']), true)
        ) {
            $include = true;
        }

        if ($include) {
            if (empty($queryData['conditions'])) {
                $queryData['conditions'] = array();
            }

            if (is_string($queryData['conditions'])) {
                $queryData['conditions'] = '(' . $Db->name($model->alias) . '.' . $Db->name($checkFieldName) . ' IS NULL) AND ' . $queryData['conditions'];
            } else {
                $queryData['conditions'][] = array(array($model->alias . '.' . $checkFieldName => null));
            }
        }

        foreach(array('hasOne', 'hasMany', 'hasAndBelongsToMany') as $binding) {
            if (empty($model->{$binding})) {
                continue;
            }
            $this->bindingConditions[$binding] = array();
            foreach ($model->{$binding} as $assoc => $value) {
                if (!$model->{$assoc}->hasField($checkFieldName)) {
                    continue;
                }
                $this->bindingConditions[$binding][$assoc] = $model->{$binding}[$assoc]['conditions'];
                if (empty($model->{$binding}[$assoc]['conditions'])) {
                    $model->{$binding}[$assoc]['conditions'] = array(array($assoc . '.' . $checkFieldName => null));
                } else if(is_string($model->{$binding}[$assoc]['conditions'])) {
                    $model->{$binding}[$assoc]['conditions'] = '(' . $Db->name($assoc) . '.' . $Db->name($checkFieldName) . ' IS NULL) AND ' . $model->{$binding}[$assoc]['conditions'];
                } else {
                    $model->{$binding}[$assoc]['conditions'][] = array(array($assoc . '.' . $checkFieldName => null));
                }
            }
        }
        return $queryData;
    }
}
