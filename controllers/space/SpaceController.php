<?php
/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2018 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\rest\controllers\space;

use yii\db\conditions\OrCondition;
use yii;
use humhub\modules\rest\definitions\SpaceDefinitions;
use humhub\modules\rest\controllers\content\ContainerController;
use humhub\modules\rest\definitions\WikiDefinitions;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use humhub\modules\space\models\Membership;

class SpaceController extends ContainerController
{
    /**
     * {@inheritdoc}
     */
    public function getContentContainerActiveRecordClass()
    {
        return Space::class;
    }

    /**
     * {@inheritdoc}
     */
    public function returnContentContainerDefinition($contentcontainerIds, $searchQueryParam)
    {
        $result = [];
        $query = Space::find()
            -> where(['contentcontainer_id' => $contentcontainerIds]);

        if ($searchQueryParam != null){
             $query -> andwhere(new OrCondition([
               ['like', 'name', $searchQueryParam],
               ['like', 'description', $searchQueryParam],
               ['like', 'tags', $searchQueryParam],
            ]));

        }

        foreach($query -> all() as $item){
            $result[] =  SpaceDefinitions::getSpace($item);
        }
        return $result;
    }

    
    public function actionSubscribe($userId)
    {

        $user = User::findOne(['id' => $userId]);
        if ($user === null) {
            return $this->returnError(404, 'User not found!');
        }
        $spacesIds = Yii::$app->request->getBodyParam("spacesIds", []);
        if (!empty($spacesIds)) {
            foreach ($spacesIds as $spaceId){
                $space = Space::findOne(['id' => $spaceId]);
                if ($space === null) {
                    return $this->returnError(404, 'Space not found!');
                }
            }
            foreach ($spacesIds as $spaceId){
                $membership = Membership::findOne(['space_id' => $spaceId, 'user_id' => $userId]);
                if ($membership === null){
                    $membership = new Membership();
                    $membership -> space_id = $spaceId;
                    $membership -> user_id = $userId;
                    $membership -> status = Membership::STATUS_MEMBER;
                    $membership -> validate();
                    if (!$membership -> save()) {
                        return $this -> returnError(500, 'Internal error while adding user to space!');
                    }
                }
            }
        }else{
            return $this -> returnError(400, 'spacesIds is empty!');
        }
        return $this -> returnSuccess('User subscribed successfully!');

    }

}
