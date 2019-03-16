<?php
/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2018 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\rest\controllers\post;

use humhub\modules\content\components\ContentActiveRecord;
use humhub\modules\post\models\Post;
use humhub\modules\content\models\ContentContainer;
use humhub\modules\content\models\Content;
use humhub\modules\rest\components\BaseContentController;
use humhub\modules\rest\definitions\PostDefinitions;
use Yii;

class PostController extends BaseContentController
{
    /**
     * {@inheritdoc}
     */
    public function getContentActiveRecordClass()
    {
        return Post::class;
    }

    /**
     * {@inheritdoc}
     */
    public function returnContentDefinition(ContentActiveRecord $contentRecord)
    {
        /** @var Post $contentRecord */
        return PostDefinitions::getPost($contentRecord);
    }

    public function actionUpdate($id)
    {
        $post = Post::findOne(['id' => $id]);
        if ($post === null) {
            return $this->returnError(404, 'Post not found!');
        }

        $postData = Yii::$app->request->getBodyParam("post", []);
        if (!empty($postData)) {
            $post->load($postData, '');
            $post->validate();
        }

        if ((!empty($postData) && $post->hasErrors())
        ) {
            return $this->returnError(400, 'Validation failed', [
                'message' => ($post !== null) ? $post->getErrors() : null,
            ]);
        }

        if (!$post->save()) {
            return $this->returnError(500, 'Internal error while save user!');
        }


        return $this->actionView($post->id);
    }

    public function actionCreate($containerId)
    {
        $contentcontainer = ContentContainer::findOne(['id' => $containerId]);
        if ($contentcontainer === null) {
            return $this->returnError(404, 'Container not found!');
        }

        $post = new Post($contentcontainer);
        $postData = Yii::$app->request->getBodyParams();
        if (!empty($postData)) {
            $post->load($postData, '');
            $post->content->contentcontainer_id = $containerId;
            $post->content->visibility = Content::VISIBILITY_PUBLIC; // optional
            $post->validate();
        }

        if ((!empty($postData) && $post->hasErrors())
        ) {
            return $this->returnError(400, 'Validation failed', [
                'message' => ($post !== null) ? $post->getErrors() : null,
            ]);
        }

        if (!$post->save()) {
            return $this->returnError(500, 'Internal error while add post!');
        }


        return $this->actionView($post->id);
    }
}