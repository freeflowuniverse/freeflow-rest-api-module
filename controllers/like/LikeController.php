<?php
/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2018 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\rest\controllers\like;

use humhub\modules\rest\components\BaseController;
use humhub\modules\rest\definitions\LikeDefinitions;
use humhub\modules\like\models\Like;

use Yii;


class LikeController extends BaseController
{

    function resolveModel($model){

        if ($model == 'post'){
            return 'humhub\modules\post\models\Post';
        } else if ($model == 'comment'){
            return 'humhub\modules\comment\models\Comment';
        } else if ($model == 'wikipage'){
            return 'humhub\modules\wiki\models\WikiPage';
        }

    }

    public function actionList()
    {
        $results = [];
        $query = Like::find();

        $param = Yii::$app->request->get('model');

        if(!empty($param)){
           $query->andWhere(['object_model' => $this -> resolveModel(Yii::$app->request->get('model')), 'object_id' => (int)Yii::$app->request->get('pk')]);
        }

        $query->orderBy(['created_at' => SORT_DESC]);

        $pagination = $this->handlePagination($query);

        foreach ($query->all() as $like) {
            $results[] = LikeDefinitions::getLike($like);
        }

        return $this->returnPagination($query, $pagination, $results);
    }


    public function actionView($id)
    {
        $like = Like::findOne(['id' => $id]);
        if ($like === null) {
            return $this->returnError(404, 'Like not found!');
        }

        return LikeDefinitions::getLike($like);
    }

    public function actionDelete($id)
    {
        $like = Like::findOne(['id' => $id]);
        if ($like === null) {
            return $this->returnError(404, 'Like not found!');
        }

        if ($like->delete()) {
            return $this->returnSuccess('Like successfully deleted!');
        }
        return $this->returnError(500, 'Internal error while delete like!');
    }


    public function actionCreate($model, $id)
    {
        $object_model =  $this -> resolveModel($model);
        
        $record = $object_model::findOne(['id' => $id]);
        
        if ($record === null) {
            return $this->returnError(404, 'ID not found!');
        }
        
        $like = Like::findOne(['object_model' => $object_model, 'object_id' => $id]);
        if ($like === null) {
            $like = new Like;
            $like -> object_id = $id;
            $like -> object_model = $object_model;
            $like -> validate();

            if ($like->hasErrors())
             {
                return $this->returnError(400, 'Validation failed', [
                    'message' => ($post !== null) ? $like->getErrors() : null,
                ]);
            }

            if (!$like->save()) {
                return $this->returnError(500, 'Internal error while save user!');
            }
        }
        return $this->actionView($like->id);
    }


}
