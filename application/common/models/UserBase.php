<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "user".
 *
 * @property int $id
 * @property string $employee_id
 * @property string $first_name
 * @property string $last_name
 * @property string $idp_username
 * @property string $email
 * @property string $created
 * @property string|null $access_token
 * @property string|null $access_token_expiration
 * @property string|null $auth_type
 * @property string $hide
 * @property string|null $uuid
 * @property string|null $display_name
 *
 * @property Reset $reset
 */
class UserBase extends \yii\db\ActiveRecord
{

    /**
     * ENUM field values
     */
    const AUTH_TYPE_LOGIN = 'login';
    const AUTH_TYPE_RESET = 'reset';
    const HIDE_NO = 'no';
    const HIDE_YES = 'yes';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['access_token', 'access_token_expiration', 'auth_type', 'uuid', 'display_name'], 'default', 'value' => null],
            [['employee_id', 'first_name', 'last_name', 'idp_username', 'email', 'created', 'hide'], 'required'],
            [['created', 'access_token_expiration'], 'safe'],
            [['auth_type', 'hide'], 'string'],
            [['employee_id', 'first_name', 'last_name', 'idp_username', 'email', 'display_name'], 'string', 'max' => 255],
            [['access_token', 'uuid'], 'string', 'max' => 64],
            ['auth_type', 'in', 'range' => array_keys(self::optsAuthType())],
            ['hide', 'in', 'range' => array_keys(self::optsHide())],
            [['employee_id'], 'unique'],
            [['email'], 'unique'],
            [['access_token'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('model', 'ID'),
            'employee_id' => Yii::t('model', 'Employee ID'),
            'first_name' => Yii::t('model', 'First Name'),
            'last_name' => Yii::t('model', 'Last Name'),
            'idp_username' => Yii::t('model', 'Idp Username'),
            'email' => Yii::t('model', 'Email'),
            'created' => Yii::t('model', 'Created'),
            'access_token' => Yii::t('model', 'Access Token'),
            'access_token_expiration' => Yii::t('model', 'Access Token Expiration'),
            'auth_type' => Yii::t('model', 'Auth Type'),
            'hide' => Yii::t('model', 'Hide'),
            'uuid' => Yii::t('model', 'Uuid'),
            'display_name' => Yii::t('model', 'Display Name'),
        ];
    }

    /**
     * Gets query for [[Reset]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getReset()
    {
        return $this->hasOne(Reset::class, ['user_id' => 'id']);
    }


    /**
     * column auth_type ENUM value labels
     * @return string[]
     */
    public static function optsAuthType()
    {
        return [
            self::AUTH_TYPE_LOGIN => Yii::t('model', 'login'),
            self::AUTH_TYPE_RESET => Yii::t('model', 'reset'),
        ];
    }

    /**
     * column hide ENUM value labels
     * @return string[]
     */
    public static function optsHide()
    {
        return [
            self::HIDE_NO => Yii::t('model', 'no'),
            self::HIDE_YES => Yii::t('model', 'yes'),
        ];
    }

    /**
     * @return string
     */
    public function displayAuthType()
    {
        return self::optsAuthType()[$this->auth_type];
    }

    /**
     * @return bool
     */
    public function isAuthTypeLogin()
    {
        return $this->auth_type === self::AUTH_TYPE_LOGIN;
    }

    public function setAuthTypeToLogin()
    {
        $this->auth_type = self::AUTH_TYPE_LOGIN;
    }

    /**
     * @return bool
     */
    public function isAuthTypeReset()
    {
        return $this->auth_type === self::AUTH_TYPE_RESET;
    }

    public function setAuthTypeToReset()
    {
        $this->auth_type = self::AUTH_TYPE_RESET;
    }

    /**
     * @return string
     */
    public function displayHide()
    {
        return self::optsHide()[$this->hide];
    }

    /**
     * @return bool
     */
    public function isHideNo()
    {
        return $this->hide === self::HIDE_NO;
    }

    public function setHideToNo()
    {
        $this->hide = self::HIDE_NO;
    }

    /**
     * @return bool
     */
    public function isHideYes()
    {
        return $this->hide === self::HIDE_YES;
    }

    public function setHideToYes()
    {
        $this->hide = self::HIDE_YES;
    }
}
