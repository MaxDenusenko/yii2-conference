<?php

namespace app\models;

use Faker\Provider\Image;
use Yii;
use yii\helpers\FileHelper;
use yii\helpers\Url;
use yii\web\UploadedFile;

/**
 * This is the model class for table "{{%archive}}".
 *
 * @property int $id
 * @property string $pdf_file
 * @property string $image
 * @property int $active
 */
class Archive extends \yii\db\ActiveRecord
{

    public $imageFile;
    public $pdfFile;
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%archive}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['pdf_file', 'image'], 'string', 'max' => 255],
            [['imageFile'], 'image', 'skipOnEmpty' => true, 'on' => 'create'],
            [['pdfFile'], 'file', 'extensions' => ['pdf'], 'skipOnEmpty' => true, 'on' => 'create'],
            [['active'], 'integer'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'pdf_file' => 'Pdf File',
            'image' => 'Image',
        ];
    }

    /**
     * Get full image for front
     * end
     * @return string
     */
    public function getImage()
    {
        $dir = str_replace('admin', '', Url::home(true)).'/archive/';
        return $dir.$this->image;
    }

    /**
     * Get small image
     * @return string
     */
    public function getSmallImage()
    {
        $dir = str_replace('admin', '', Url::home(true)).'/archive/';
        return $dir.'120x150/'.$this->image;
    }

    public function beforeValidate()
    {
        $this->imageFile = UploadedFile::getInstance($this, 'imageFile');
        $this->pdfFile = UploadedFile::getInstance($this, 'pdfFile');

        return parent::beforeValidate();
    }

    /**
     * Download pdf files and pictures
     * @param bool $insert
     * @return bool
     * @throws \yii\base\Exception
     */
    public function beforeSave($insert)
    {
        $dir = Yii::getAlias('@webroot').'/archive/';

        if (!file_exists($dir)) {

            try {
                FileHelper::createDirectory($dir);
            } catch (\Exception $exception) {
                return false;
            }
        }

        if ($imageFile = $this->imageFile) {


            if (!file_exists($dir.'120x150/')) {

                try {
                    FileHelper::createDirectory($dir);
                } catch (\Exception $exception) {
                    return false;
                }
            }

            if ($this->image && file_exists($dir.$this->image)){

                unlink($dir.$this->image);
            }
            if ($this->image && file_exists($dir.'120x150/'.$this->image)){

                unlink($dir.'120x150/'.$this->image);
            }
            $this->image = strtotime('now').'_'.Yii::$app->getSecurity()->generateRandomString(6).'.'.$imageFile->extension;
            $imageFile->saveAs($dir.$this->image);

            $image = Yii::$app->image->load($dir.$this->image);
            $image->background('#fff', 0);
            $image->resize('120', '150', \yii\image\drivers\Image::INVERSE);
            $image->crop('120', '150');
            $image->save($dir.'120x150/'.$this->image, 90);
        }

        if ($pdfFile = $this->pdfFile) {

            $dir = Yii::getAlias('@webroot').'/archive/pdf/';
            if (!file_exists($dir)) {

                try {
                    FileHelper::createDirectory($dir);
                } catch (\Exception $exception) {
                    return false;
                }
            }
            if ($this->pdf_file && file_exists($dir.$this->pdf_file)){

                unlink($dir.$this->pdf_file);
            }
            $this->pdf_file = strtotime('now').'_'.Yii::$app->getSecurity()->generateRandomString(6).'.'.$pdfFile->extension;
            $pdfFile->saveAs($dir.$this->pdf_file);
        }
        return parent::beforeSave($insert);
    }

    /**
     * @return bool
     */
    public function beforeDelete()
    {

        if ($this->image) {

            $file = Yii::getAlias('@webroot').'/archive/'.$this->image;
            $this->removeFile($file);

            $file = Yii::getAlias('@webroot').'/archive/120x150/'.$this->image;
            $this->removeFile($file);
        }

        if ($this->pdf_file) {

            $file = Yii::getAlias('@webroot').'/archive/pdf/'.$this->pdf_file;
            $this->removeFile($file);
        }

        return parent::beforeDelete();
    }

    /**
     * @param $file
     */
    public function removeFile($file) {

        if (file_exists($file)) {

            unlink($file);
        }
    }

    /**
     * @return query\ArchiveQuery|\yii\db\ActiveQuery
     */
    public static function find()
    {
        return new \app\models\query\ArchiveQuery(get_called_class());
    }

    /**
     * Create pdf file for archive
     * @param $model
     * @return bool
     */
    public static function createPdf($model)
    {

        $materials = Material::find()->where(['=', 'conference_id', $model->conference_id])->all();
        $conference = Conference::find()->where(['=', 'id', $model->conference_id])->one();

        $files = '';
        foreach ($materials as $material) {
            if ($material->pdf_file == '') {

                \Yii::$app->getSession()->setFlash('error', "Матеріал автора(ів) $material->author не містить pdf файла");
                return false;
            }
            $files .= '"'.\Yii::$app->getBasePath().\Yii::$app->params['PathToAttachments'].$material->dir.$material->pdf_file.'"'." ";
        }

        $dataDir = \Yii::$app->getBasePath().\Yii::$app->params['PathToAttachments'].'archive/';
        if (!file_exists($dataDir)) {

            try {
                FileHelper::createDirectory($dataDir);
            } catch (\Exception $exception) {
                \Yii::$app->getSession()->setFlash('error', "Не вдалося створити директорію ".$dataDir);
                return false;
            }
        }
        $conferenceName = str_replace(" ", "_", $conference->name);
        $outputName = $dataDir.$conferenceName;

        $cmd = "gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -sOutputFile=$outputName ";

        $cmd .= $files;

        try {
            shell_exec($cmd);
        } catch (\Exception $exception) {

            \Yii::$app->getSession()->setFlash('error', "Pdf файл не вдалося згенерувати");
            return false;
        }
        \Yii::$app->getSession()->setFlash('success', "Pdf файл $outputName успішно згенерований");
        return true;
    }

    /**
     * Searching creating files for archive
     * @return array|bool
     */
    public static function findFiles()
    {
        $dataDir = \Yii::$app->getBasePath().\Yii::$app->params['PathToAttachments'].'archive/';
        if (!file_exists($dataDir)) {

            try {
                FileHelper::createDirectory($dataDir);
            } catch (\Exception $exception) {
                \Yii::$app->getSession()->setFlash('error', "Не вдалося створити директорію ".$dataDir);
                return false;
            }
        }

        return FileHelper::findFiles(\Yii::$app->getBasePath().\Yii::$app->params['PathToAttachments'].'/archive/');
    }
}
