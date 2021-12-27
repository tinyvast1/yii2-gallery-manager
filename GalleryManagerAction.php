<?php

namespace aquy\gallery;

use Yii;
use yii\base\Action;
use yii\helpers\Json;
use yii\db\ActiveRecord;
use yii\web\UploadedFile;
use yii\web\HttpException;

/**
 * Backend controller for GalleryManager widget.
 * Provides following features:
 *  - Image removal
 *  - Image upload/Multiple upload
 *  - Arrange images in gallery
 *  - Changing name/description associated with image
 *
 * @author Bogdan Savluk <savluk.bogdan@gmail.com>
 */
class GalleryManagerAction extends Action
{
    /**
     * Glue used to implode composite primary keys
     * @var string
     */
    public $pkGlue = '_';

    /**
     * $types to be defined at Controller::actions()
     * @var array Mapping between types and model class names
     * @example 'post'=>'common\models\Post'
     * @see     GalleryManagerAction::run
     */
    public $types = [];


    protected $type;
    protected $behaviorName;
    protected $galleryId;

    /** @var  ActiveRecord */
    protected $owner;
    /** @var  GalleryBehavior */
    protected $behavior;


    public function run($action)
    {
        $this->type = Yii::$app->request->get('type');
        $this->behaviorName = Yii::$app->request->get('behaviorName');
        $this->galleryId = Yii::$app->request->get('galleryId');
        $pkNames = call_user_func([$this->types[$this->type], 'primaryKey']);
        $pkValues = explode($this->pkGlue, $this->galleryId);

        $pk = array_combine($pkNames, $pkValues);

        $this->owner = call_user_func([$this->types[$this->type], 'findOne'], $pk);
        $this->behavior = $this->owner->getBehavior($this->behaviorName);

        switch ($action) {
            case 'delete':
                return $this->actionDelete(Yii::$app->request->post('id'));
                break;
            case 'rotate':
                return $this->actionRotate(Yii::$app->request->post('id'));
                break;
            case 'ajaxUpload':
                return $this->actionAjaxUpload();
                break;
            case 'changeData':
                return $this->actionChangeData(Yii::$app->request->post('photo'));
                break;
            case 'order':
                return $this->actionOrder(Yii::$app->request->post('order'));
                break;
            default:
                throw new HttpException(400, 'Action do not exists');
                break;
        }
    }

    /**
     * Removes image with ids specified in post request.
     * On success returns 'OK'
     *
     * @param $ids
     *
     * @throws HttpException
     * @return string
     */
    protected function actionDelete($ids)
    {

        $this->behavior->deleteImages($ids);

        return 'OK';
    }

    protected function actionRotate($id)
    {
        $this->behavior->rotateImage($id);

        return 'OK';
    }

    /**
     * Method to handle file upload thought XHR2
     * On success returns JSON object with image info.
     *
     * @return string
     * @throws HttpException
     */
    public function actionAjaxUpload()
    {

        $imageFile = UploadedFile::getInstanceByName('image');

        if (isset($this->behavior->allowedExt) && is_array($this->behavior->allowedExt) && count($this->behavior->allowedExt) > 0){
            array_walk($this->behavior->allowedExt, function (&$item, $key) {
                $item = strtolower($item);
            });
            if (!in_array(strtolower($imageFile->extension), $this->behavior->allowedExt)) {
                Yii::$app->response->statusCode = 500;
                Yii::$app->response->statusText = 'Internal Server Error';
                Yii::$app->response->headers->set('Content-Type', 'text/html');
                return Json::encode(
                    array(
                        'error' => 'Not allowed extension. Allowed extensions: ' . implode(', ', $this->behavior->allowedExt),
                    )
                );
            }
        }

        if (isset($this->behavior->maxSize) && $imageFile->size > $this->behavior->maxSize){
            Yii::$app->response->statusCode = 500;
            Yii::$app->response->statusText = 'Internal Server Error';
            Yii::$app->response->headers->set('Content-Type', 'text/html');
            return Json::encode(
                array(
                    'error' => 'Image is too large. Max size of image: ' . $this->behavior->maxSize . ' bytes',
                )
            );
        }

        if (isset($this->behavior->allowedMimeType) && is_array($this->behavior->allowedMimeType) && count($this->behavior->allowedMimeType) > 0){
            if (!in_array($imageFile->type, $this->behavior->allowedMimeType)) {
                Yii::$app->response->statusCode = 500;
                Yii::$app->response->statusText = 'Internal Server Error';
                Yii::$app->response->headers->set('Content-Type', 'text/html');
                return Json::encode(
                    array(
                        'error' => 'Not allowed MIME-type. Allowed MIME-types: ' . implode(', ', $this->behavior->allowedMimeType),
                    )
                );
            }
        }

        if (isset($this->behavior->maxWidth) || isset($this->behavior->maxHeight)) {
            $info = getimagesize($imageFile->tempName);
            if (!isset($info[0]) || ($info[0] > $this->behavior->maxWidth)) {
                Yii::$app->response->statusCode = 500;
                Yii::$app->response->statusText = 'Internal Server Error';
                Yii::$app->response->headers->set('Content-Type', 'text/html');
                return Json::encode(
                    array(
                        'error' => 'Maximum allowed width of an uploaded image is ' . $this->behavior->maxWidth,
                    )
                );
            }
        }

        if (isset($this->behavior->maxWidth) || isset($this->behavior->maxHeight) || isset($this->behavior->minHeight) || isset($this->behavior->minWidth)) {
            $info = getimagesize($imageFile->tempName);
            $error = [];
            if (isset($info[0]) && $info[1]) {
                $width = $info[0];
                $height = $info[1];
                if (isset($this->behavior->maxWidth) && $width > $this->behavior->maxWidth) {
                    $error[] = 'Maximum allowed width of an uploaded image is ' . $this->behavior->maxWidth;
                }
                if (isset($this->behavior->maxHeight) && $height > $this->behavior->maxHeight) {
                    $error[] = 'Maximum allowed height of an uploaded image is ' . $this->behavior->maxHeight;
                }
                if (isset($this->behavior->minWidth) && $width < $this->behavior->minWidth) {
                    $error[] = 'Minimum allowed height of an uploaded image is ' . $this->behavior->minWidth;
                }
                if (isset($this->behavior->minHeight) && $width < $this->behavior->minHeight) {
                    $error[] = 'Minimum allowed height of an uploaded image is ' . $this->behavior->minHeight;
                }
            } else {
                $error[] = 'Error';
            }
            if (count($error) > 0) {
                Yii::$app->response->statusCode = 500;
                Yii::$app->response->statusText = 'Internal Server Error';
                Yii::$app->response->headers->set('Content-Type', 'text/html');
                return Json::encode(
                    array(
                        'error' => implode('||', $error)
                    )
                );
            }
        }

        $image = $this->behavior->addImage($imageFile);

        // not "application/json", because  IE8 trying to save response as a file

        Yii::$app->response->headers->set('Content-Type', 'text/html');

        return Json::encode(
            array(
                'id' => $image->id,
                'sort' => $image->sort,
                'src' => $image->src,
                'name' => (string)$image->name,
                'description' => (string)$image->description,
                'preview' => $image->getUrl($image->src),
            )
        );
    }

    /**
     * Saves images order according to request.
     *
     * @param array $order new arrange of image ids, to be saved
     *
     * @return string
     * @throws HttpException
     */
    public function actionOrder($order)
    {
        if (count($order) == 0) {
            throw new HttpException(400, 'No data, to save');
        }
        $res = $this->behavior->arrange($order);

        return Json::encode($res);

    }

    /**
     * Method to update images name/description via AJAX.
     * On success returns JSON array of objects with new image info.
     *
     * @param $imagesData
     *
     * @throws HttpException
     * @return string
     */
    public function actionChangeData($imagesData)
    {
        if (count($imagesData) == 0) {
            throw new HttpException(400, 'Nothing to save');
        }
        $images = $this->behavior->updateImagesData($imagesData);
        $resp = array();
        foreach ($images as $model) {
            $resp[] = array(
                'id' => $model->id,
                'sort' => $model->sort,
                'src' => $model->getUrl($model->src),
                'name' => (string)$model->name,
                'description' => (string)$model->description,
                'preview' => $model->getUrl($model->src),
            );
        }

        return Json::encode($resp);
    }
}
