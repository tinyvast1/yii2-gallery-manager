<?php

namespace aquy\gallery;

use Yii;
use yii\db\Query;
use yii\imagine\Image;
use yii\base\Behavior;
use yii\base\Exception;
use yii\db\ActiveRecord;

/**
 * Behavior for adding gallery to any model.
 *
 * @author Bogdan Savluk <savluk.bogdan@gmail.com>
 *
 * @property string $galleryId
 */
class GalleryBehavior extends Behavior
{
    /**
     * Glue used to implode composite primary keys
     * @var string
     */
    public $pkGlue = '_';
    /**
     * @var string Type name assigned to model in image attachment action
     * @see     GalleryManagerAction::$types
     * @example $type = 'Post' where 'Post' is the model name
     */
    public $type;
    /**
     * @var ActiveRecord the owner of this behavior
     * @example $owner = Post where Post is the ActiveRecord with GalleryBehavior attached under public function behaviors()
     */
    public $owner;
    /**
     * Path to directory where to save uploaded images
     * @var string
     */
    public $directory;
    /**
     * Directory Url, without trailing slash
     * @var string
     */
    public $url;

    /**
     * name of query param for modification time hash
     * to avoid using outdated version from cache - set it to false
     * @var string
     */
    public $ownerTimeHash;

    /**
     * Used by GalleryManager
     * @var bool
     * @see GalleryManager::run
     */
    public $hasName = true;
    /**
     * Used by GalleryManager
     * @var bool
     * @see GalleryManager::run
     */
    public $hasDescription = true;

    /**
     * name of model for modification first src file in model
     * @var string
     */
    public $ownerFirstSrc;

    /**
     * @var string Table name for saving gallery images meta information
     */
    public $tableName = '{{%gallery_image}}';

    /**
     * allowed MIME-types of uploaded images
     * @var array
     */
    public $allowedMimeType;

    /**
     * max size of uploaded images
     * @var integer
     */
    public $maxSize;

    protected $_galleryId;

    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
        ];
    }

    public function beforeDelete()
    {
        $images = $this->getImages();
        foreach ($images as $image) {
            $this->deleteImage($image->id);
        }
        $dirPath = $this->directory . '/' . $this->getGalleryId();
        @rmdir($dirPath);
    }

    protected $_images = null;

    /**
     * @return GalleryImage
     */
    public function getImage()
    {
        if ($this->_images === null) {
            $query = new Query();
            $imageData = $query
                ->select(['id', 'name', 'description', 'sort', 'src'])
                ->from($this->tableName)
                ->where(['type' => $this->type, 'ownerId' => $this->getGalleryId()])
                ->orderBy(['sort' => SORT_ASC])
                ->one();
            $this->_images = [];
            if ($imageData) {
                $this->_images[] = new GalleryImage($this, $imageData);
            } else {
                $this->_images[] = false;
            }
        }
        return $this->_images[0];
    }

    /**
     * @return GalleryImage[]
     */
    public function getImages()
    {
        if ($this->_images === null) {
            $query = new Query();

            $imagesData = $query
                ->select(['id', 'name', 'description', 'sort', 'src'])
                ->from($this->tableName)
                ->where(['type' => $this->type, 'ownerId' => $this->getGalleryId()])
                ->orderBy(['sort' => SORT_ASC])
                ->all();

            $this->_images = [];
            foreach ($imagesData as $imageData) {
                $this->_images[] = new GalleryImage($this, $imageData);
            }
        }

        return $this->_images;
    }

    public function getUrl($imageName)
    {
        $path = $this->getFilePath($imageName);

        if (!file_exists($path)) {
            return null;
        }

        return $this->url . '/' . $this->galleryId . '/' . $imageName;
    }

    public function getFilePath($fileName)
    {
        return $this->directory . DIRECTORY_SEPARATOR . $this->galleryId . DIRECTORY_SEPARATOR . $fileName;
    }

    private function removeFile($fileName)
    {
        $file = $this->getFilePath($fileName);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * Get Gallery Id
     *
     * @return mixed as string or integer
     * @throws Exception
     */
    public function getGalleryId()
    {
        if ($this->_galleryId) {
            return $this->_galleryId;
        }
        $pk = $this->owner->getPrimaryKey();
        if (is_array($pk)) {
            return implode($this->pkGlue, $pk);
        } else {
            return $pk;
        }
    }

    public function createFolders()
    {
        $targetPath = $this->directory . '/' . $this->galleryId;
        $path = realpath($targetPath);
        if (!$path) {
            mkdir($targetPath, 0755, true);
            $path = $targetPath;
        }
        return $path;
    }

    public function deleteImage($imageId)
    {
        $imageQuery = (new Query())
            ->select(['id', 'name', 'description', 'sort', 'src'])
            ->from($this->tableName)
            ->where(['id' => $imageId])
            ->one();
        $this->removeFile($imageQuery['src']);
        $db = Yii::$app->db;
        $db->createCommand()
            ->delete(
                $this->tableName,
                ['id' => $imageId]
            )->execute();
        $this->updateFirstSrc();
    }

    public function rotateImage($imageId)
    {
        $imageQuery = (new Query())
            ->select(['id', 'name', 'description', 'sort', 'src'])
            ->from($this->tableName)
            ->where(['id' => $imageId])
            ->one();
        Image::frame(
            $this->getFilePath($imageQuery['src']),
            0
        )->rotate(90)->save($this->getFilePath($imageQuery['src']));
    }

    public function deleteImages($imageIds)
    {
        foreach ($imageIds as $imageId) {
            $this->deleteImage($imageId);
        }
        if ($this->_images !== null) {
            $removed = array_combine($imageIds, $imageIds);
            $this->_images = array_filter(
                $this->_images,
                function ($image) use (&$removed) {
                    return !isset($removed[$image->id]);
                }
            );
        }
    }

    public function addImage($imageFile)
    {
        $db = Yii::$app->db;
        $db->createCommand()
            ->insert(
                $this->tableName,
                [
                    'type' => $this->type,
                    'ownerId' => $this->getGalleryId()
                ]
            )->execute();

        $this->createFolders();

        do {
            $fileName = uniqid() . '.' . $imageFile->extension;
        } while (file_exists($this->getFilePath($fileName)));
        $imageFile->saveAs($this->getFilePath($fileName));

        $id = $db->getLastInsertID('gallery_image_id_seq');
        $db->createCommand()
            ->update(
                $this->tableName,
                [
                    'sort' => $id,
                    'src' => $fileName
                ],
                ['id' => $id]
            )->execute();
        $this->updateFirstSrc();

        $galleryImage = new GalleryImage($this, [
            'id' => $id,
            'src' => $fileName
        ]);

        if ($this->_images !== null) {
            $this->_images[] = $galleryImage;
        }

        return $galleryImage;
    }


    public function arrange($order)
    {
        $orders = [];
        $i = 0;
        foreach ($order as $k => $v) {
            if (!$v) {
                $order[$k] = $k;
            }
            $orders[] = $order[$k];
            $i++;
        }
        sort($orders);
        $i = 0;
        $res = [];
        foreach ($order as $k => $v) {
            $res[$k] = $orders[$i];

            Yii::$app->db->createCommand()
                ->update(
                    $this->tableName,
                    ['sort' => $orders[$i]],
                    ['id' => $k]
                )->execute();

            $i++;
        }
        $this->updateFirstSrc();
        return $order;
    }

    /**
     * @param array $imagesData
     *
     * @return GalleryImage[]
     */
    public function updateImagesData($imagesData)
    {
        $imageIds = array_keys($imagesData);
        $imagesToUpdate = [];

        if ($this->_images !== null) {
            $selected = array_combine($imageIds, $imageIds);
            foreach ($this->_images as $img) {
                if (isset($selected[$img->id])) {
                    $imagesToUpdate[] = $selected[$img->id];
                }
            }
        } else {
            $rawImages = (new Query())
                ->select(['id', 'name', 'description', 'sort', 'src'])
                ->from($this->tableName)
                ->where(['type' => $this->type, 'ownerId' => $this->getGalleryId()])
                ->andWhere(['in', 'id', $imageIds])
                ->orderBy(['sort' => SORT_ASC])
                ->all();
            foreach ($rawImages as $image) {
                $imagesToUpdate[] = new GalleryImage($this, $image);
            }
        }


        foreach ($imagesToUpdate as $image) {
            if (isset($imagesData[$image->id]['name'])) {
                $image->name = $imagesData[$image->id]['name'];
            }
            if (isset($imagesData[$image->id]['description'])) {
                $image->description = $imagesData[$image->id]['description'];
            }
            Yii::$app->db->createCommand()
                ->update(
                    $this->tableName,
                    ['name' => $image->name, 'description' => $image->description],
                    ['id' => $image->id]
                )->execute();
        }

        return $imagesToUpdate;
    }

    private function updateFirstSrc()
    {
        if (!$this->ownerFirstSrc) {
            return false;
        }
        $firstImageGalleryQuery = (new Query())
            ->select(['src'])
            ->from($this->tableName)
            ->where([
                'type' => $this->type,
                'ownerId' => $this->getGalleryId()
            ])
            ->orderBy(['sort' => SORT_ASC])
            ->one();
        $columns = [];
        if ($firstImageGalleryQuery) {
            $columns[$this->ownerFirstSrc] = $firstImageGalleryQuery['src'];
        } else {
            $columns[$this->ownerFirstSrc] = '';
        }
        if ($this->ownerTimeHash) {
            $columns[$this->ownerTimeHash] = time();
        }
        Yii::$app->db->createCommand()
            ->update(
                $this->owner->tableName(),
                $columns,
                ['id' => $this->owner->id]
            )->execute();
        return true;
    }

}
