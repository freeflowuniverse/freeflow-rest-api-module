<?php
/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2018 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\rest\controllers\user;

use Yii;
use yii\web\HttpException;
use humhub\modules\rest\components\BaseController;
use humhub\modules\rest\definitions\UserDefinitions;
use humhub\modules\user\models\Password;
use humhub\modules\user\models\Profile;
use humhub\modules\user\models\User;
use humhub\modules\space\models\Space;
use humhub\modules\space\models\Membership;
use humhub\modules\rest\definitions\SpaceDefinitions;

/**
 * Class AccountController
 */
class UserController extends BaseController
{

    public function actionIndex()
    {
        $results = [];
        $q = Yii::$app->request -> get('q');

        if ($q == null){
            $query = User::find();
        } else{
            $query = User::find()
                ->where(['like', 'username', $q])
                ->orWhere(['like', 'email', $q])
                ->orWhere(['like', 'tags', $q]);
        }

        $pagination = $this->handlePagination($query);
        foreach ($query->all() as $user) {
            $results[] = UserDefinitions::getUser($user);
        }
        return $this->returnPagination($query, $pagination, $results);
    }


    public function actionView($id)
    {
        $user = User::findOne(['id' => $id]);
        if ($user === null) {
            return $this->returnError(404, 'User not found!');
        }

        return UserDefinitions::getUser($user);
    }

    public function actionViewByEmail($email)
    {
        $user = User::findOne(['email' => $email]);
        if ($user === null) {
            return $this->returnError(404, 'User not found!');
        }

        return UserDefinitions::getUser($user);
    }

    public function actionUpdate($id)
    {
        $user = User::findOne(['id' => $id]);
        if ($user === null) {
            return $this->returnError(404, 'User not found!');
        }

        $user->scenario = 'editAdmin';
        $userData = Yii::$app->request->getBodyParam("account", []);
        if (!empty($userData)) {
            $user->load($userData, '');
            $user->validate();
        }

        $profile = null;
        $profileData = Yii::$app->request->getBodyParam("profile", []);

        if (!empty($profileData)) {
            $profile = $user->profile;
            $profile->scenario = 'editAdmin';
            $profile->load($profileData, '');
            $profile->validate();
        }

        $password = null;
        $passwordData = Yii::$app->request->getBodyParam("password", []);
        if (!empty($passwordData)) {
            $password = new Password();
            $password->scenario = 'registration';
            $password->load($passwordData, '');
            $password->newPasswordConfirm = $password->newPassword;
            $password->validate();
        }

        if ((!empty($userData) && $user->hasErrors()) ||
            ($password !== null && $password->hasErrors()) ||
            ($profile !== null && $profile->hasErrors())
        ) {
            return $this->returnError(400, 'Validation failed', [
                'profile' => ($profile !== null) ? $profile->getErrors() : null,
                'account' => $user->getErrors(),
                'password' => ($password !== null) ? $password->getErrors() : null,
            ]);
        }

        if (!$user->save()) {
            return $this->returnError(500, 'Internal error while save user!');
        }

        if ($profile !== null && !$profile->save()) {
            return $this->returnError(500, 'Internal error while save profile!');

        }

        if ($password !== null) {
            $password->user_id = $user->id;
            $password->setPassword($password->newPassword);
            if (!$password->save()) {
                return $this->returnError(500, 'Internal error while save new password!');
            }
        }

        return $this->actionView($user->id);
    }


    /**
     *
     * @return array
     * @throws HttpException
     */
    public function actionCreate()
    {
        $user = new User();
        $user->scenario = 'editAdmin';
        $user->load(Yii::$app->request->getBodyParam("account", []), '');
        $user->validate();

        $profile = new Profile();
        $profile->scenario = 'editAdmin';
        $profile->load(Yii::$app->request->getBodyParam("profile", []), '');

        if ($user->hasErrors()) {
            return $this->returnError(412, 'Validation failed', [
                'account' => $user->getErrors(),
            ]);
        }

         $user -> save();
         $user = User::findOne(['email' => $user -> email]);


         $contentContainer = new ContentContainer();
         $contentContainer -> class = "humhub\\modules\\user\\models\\User";
         $contentContainer -> pk = $user -> id;
         $contentContainer -> owner_user_id = $user -> id;
         $contentContainer -> save();

         $groupuser = new GroupUser();
         $groupuser -> user_id = $user -> id;
         $groupuser -> group_id = 2; // users group
         $groupuser -> save();

         $newUSer = new Auth();
         $newUSer -> source_id = $user -> username;
         $newUSer -> source = '3bot';
         $newUSer -> user_id = $user -> id;
         $newUSer -> save();


        return $this->actionView($user->id);

    }


    public function actionDelete($id)
    {
        $user = User::findOne(['id' => $id]);
        if ($user === null) {
            return $this->returnError(404, 'User not found!');
        }

        if ($user->softDelete()) {
            return $this->returnSuccess('User successfully soft deleted!');
        }
        return $this->returnError(500, 'Internal error while soft delete user!');
    }

    public function actionViewByUsername($username)
    {
        $user = User::findOne(['username' => $username]);
        if ($user === null) {
            return $this->returnError(404, 'User not found!');
        }
        return UserDefinitions::getUser($user);
    }

    public function actionSpaces($id)
    {
        $result = [];

        $query = Membership::find()
            -> where(['user_id' => $id]);
        foreach($query -> all() as $item){
            $space = Space::findOne(["id" => $item -> space_id]);
            $result[] =  SpaceDefinitions::getSpace($space);
        }
        return $result;
    }


    public function actionHardDelete($id)
    {
        $user = User::findOne(['id' => $id]);
        if ($user === null) {
            return $this->returnError(404, 'User not found!');
        }

        if ($user->delete()) {
            return $this->returnSuccess('User successfully deleted!');
        }

        return $this->returnError(500, 'Internal error while soft delete user!');
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
