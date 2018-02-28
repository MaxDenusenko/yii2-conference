<?php

namespace app\models;

use app\models\job\RemoveMaterialDirJob;
use Yii;

/**
 * This is the model class for table "{{%category}}".
 *
 * @property int $id
 * @property string $name
 *
 * @property Material[] $materials
 */
class Category extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%category}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name'], 'required'],
            [['name'], 'string', 'max' => 255],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getMaterials()
    {
        return $this->hasMany(Material::className(), ['category_id' => 'id']);
    }

    /**
     * @inheritdoc
     * @return \app\models\query\CategoryQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new \app\models\query\CategoryQuery(get_called_class());
    }

    public function beforeDelete()
    {
        $materials = $this->materials;

        \Yii::$app->queue->push(new RemoveMaterialDirJob([
            'materials' => $materials,
        ]));

        return parent::beforeDelete();
    }
}