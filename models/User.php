<?php


namespace app\models;


use app\models\base\UserBase;
use Yii;
use yii\web\IdentityInterface;

class User extends UserBase implements IdentityInterface
{

    /**
     * Finds an identity by the given ID.
     * @param string|int $id the ID to be looked for
     * @return IdentityInterface the identity object that matches the given ID.
     * Null should be returned if such an identity cannot be found
     * or the identity is not in an active state (disabled, deleted, etc.)
     */
    public static function findIdentity($id)
    {
        return User::find()->where(['id' => $id])->one();
    }

    /**
     * Finds an identity by the given token.
     * @param mixed $token the token to be looked for
     * @param mixed $type the type of the token. The value of this parameter depends on the implementation.
     * For example, [[\yii\filters\auth\HttpBearerAuth]] will set this parameter to be `yii\filters\auth\HttpBearerAuth`.
     * @return IdentityInterface the identity object that matches the given token.
     * Null should be returned if such an identity cannot be found
     * or the identity is not in an active state (disabled, deleted, etc.)
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        return User::find()->where(['access_token' => $token, 'deactive' => '0'])->one();
    }

    /**
     * Finds user by username
     *
     * @param string $username
     * @return static|null
     */
    public static function findByUsername($username)
    {
        return User::find()->where(['username' => $username])->one();
    }

    /**
     * Returns an ID that can uniquely identify a user identity.
     * @return string|int an ID that uniquely identifies a user identity.
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Returns a key that can be used to check the validity of a given identity ID.
     *
     * The key should be unique for each individual user, and should be persistent
     * so that it can be used to check the validity of the user identity.
     *
     * The space of such keys should be big enough to defeat potential identity attacks.
     *
     * This is required if [[User::enableAutoLogin]] is enabled. The returned key will be stored on the
     * client side as a cookie and will be used to authenticate user even if PHP session has been expired.
     *
     * Make sure to invalidate earlier issued authKeys when you implement force user logout, password change and
     * other scenarios, that require forceful access revocation for old sessions.
     *
     * @return string a key that is used to check the validity of a given identity ID.
     * @see validateAuthKey()
     */
    public function getAuthKey()
    {
        return $this->aut_key;
    }

    /**
     * Validates the given auth key.
     *
     * This is required if [[User::enableAutoLogin]] is enabled.
     * @param string $authKey the given auth key
     * @return bool whether the given auth key is valid.
     * @see getAuthKey()
     */
    public function validateAuthKey($authKey)
    {
        return $this->aut_key === $authKey && $this->deactive===0;
    }

    /**
     * Validates password
     *
     * @param string $password password to validate
     * @return bool if password provided is valid for current user
     */
    public function validatePassword($password)
    {
        return $this->password === $password && $this->deactive===0;
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if ($this->created_by == '') {
            $this->created_by = Yii::$app->user->identity->getId();
        }
        $this->updated_by = Yii::$app->user->identity->getId();

        return true;
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        $this->setRoles();
    }

    public function beforeDelete()
    {
        // Revoke all roles
        $auth = Yii::$app->authManager;
        $auth->revokeAll($this->id);

        return parent::beforeDelete();
    }


    public function setRoles()
    {
        // Set roles
        $auth = Yii::$app->authManager;
        $auth->revokeAll($this->id);
        foreach ($this->getRolesArray() as $userRole) {
            if ($userRole != '') {
                foreach ($roles = $auth->getRoles() as $role) {
                    if ($role->name == $userRole) {
                        $auth->assign($auth->getRole($userRole), $this->id);
                    }
                }
            }
        }

        // Set roles field correctly
        $first = true;
        $this->roles = '';
        foreach ($auth->getRolesByUser($this->id) as $role) {
            if (!$first) {
                $this->roles .= ',';
            }
            $this->roles .= $role->name;
            $first = false;
        }
    }

    public function getRolesArray()
    {
        return explode(",", $this->roles);
    }

    public function getAvailableRoles()
    {
        $auth = Yii::$app->authManager;

        $availableRoles = '';
        $first = true;
        foreach ($roles = $auth->getRoles() as $role) {
            if (!$first) {
                $availableRoles .= ',';
            }
            $availableRoles .= $role->name;
            $first = false;
        }

        return $availableRoles;
    }
}