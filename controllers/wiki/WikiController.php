<?php
/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2018 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\rest\controllers\wiki;

use Yii;
use humhub\modules\content\components\ContentActiveRecord;
use humhub\modules\rest\components\BaseContentController;
use humhub\modules\rest\definitions\WikiDefinitions;
use humhub\modules\wiki\models\WikiPage;
use humhub\modules\content\models\ContentContainer;
use humhub\modules\content\models\Content;

class WikiController extends BaseContentController
{
    /**
     * {@inheritdoc}
     */
    public function getContentActiveRecordClass()
    {
        return WikiPage::class;
    }

    /**
     * {@inheritdoc}
     */
    public function returnContentDefinition(ContentActiveRecord $contentRecord)
    {
        /** @var WikiPage $contentRecord */
        return WikiDefinitions::getWikiPage($contentRecord);
    }

    /**
     * Creates a content in a content container
     *
     * @param $containerId
     * @return array
     * @throws \yii\db\IntegrityException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionCreate($containerId)
    {
        $containerRecord = ContentContainer::findOne(['id' => $containerId]);
        if ($containerRecord === null) {
            return $this->returnError(404, 'Content container not found!');
        }

        /** @var ContentContainerActiveRecord $container */
        $container = $containerRecord->getPolymorphicRelation();

        /** @var ContentActiveRecord $contentRecord */
        $contentRecord = Yii::createObject(['class' => $this->getContentActiveRecordClass()]);

        $contentRecord->content->container = $container;
        $contentRecord->load(Yii::$app->request->getBodyParams(), '');

        if (!$contentRecord->save()) {
            return $this->returnError(400, 'Validation failed', ['wikipage' => $contentRecord->getErrors()]);
        }


        $rev = $contentRecord->createRevision();
        $rev->load(Yii::$app->request->getBodyParams(), '');
        $rev -> wiki_page_id = $contentRecord -> id;


        if (!$rev->save()) {
            return $this->returnError(400, 'Validation failed', ['wikipage' => $rev->getErrors()]);
        }

        return $this->returnContentDefinition($contentRecord);
    }

    public function actionUpdate($id)
    {
        $class = $this->getContentActiveRecordClass();

        $contentRecord = $class::findOne(['id' => $id]);
        if ($contentRecord === null) {
            return $this->returnError(404, 'Request object not found!');
        }

        if (!$contentRecord->load(Yii::$app->request->getBodyParams(), '') || !$contentRecord->save()) {
            return $this->returnError(400, 'Validation failed', ['wikipage' => $contentRecord->getErrors()]);
        }

        $content = Yii::$app->request->getBodyParam('content', null);
        if ($content != null){
            $rev = $contentRecord->createRevision();
            $rev -> content = $content;
            $rev -> wiki_page_id = $contentRecord -> id;

            if (!$rev->save()) {
                return $this->returnError(400, 'Validation failed', ['wikipage' => $rev->getErrors()]);
            }
        }

        return $this->returnContentDefinition($contentRecord);
    }

    public function actionMigrate($fromContainerId, $toContainerId){

        $fromContainer = ContentContainer::findOne(['id' => $fromContainerId]);
        if ($fromContainer === null) {
            return $this->returnError(404, 'Source Content container not found!');
        }

        $toContainer = ContentContainer::findOne(['id' => $toContainerId]);
        if ($toContainer === null) {
            return $this->returnError(404, 'Target Content container not found!');
        }

        Yii::$app->db->createCommand("UPDATE content SET contentcontainer_id=:toContainerId WHERE object_model=:object_model and contentcontainer_id=:fromContainerId")
            ->bindValue(':toContainerId', $toContainerId)
            ->bindValue(':fromContainerId', $fromContainerId)
            ->bindValue(':object_model', WikiPage::class)
            ->execute();

        return $this->returnSuccess('Successfully migrated!');
    }
}