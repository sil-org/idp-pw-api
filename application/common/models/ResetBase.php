<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "reset".
 *
 * @property int $id
 * @property string $uid
 * @property int $user_id
 * @property string $type
 * @property string|null $code
 * @property int $attempts
 * @property string $expires
 * @property string|null $disable_until
 * @property string $created
 * @property string|null $email
 *
 * @property User $user
 */
class ResetBase extends \yii\db\ActiveRecord
{

    /**
     * ENUM field values
     */
    const TYPE_PRIMARY = 'primary';
    const TYPE_METHOD = 'method';
    const TYPE_SUPERVISOR = 'supervisor';
    const TYPE_SPOUSE = 'spouse';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'reset';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['code', 'disable_until', 'email'], 'default', 'value' => null],
            [['attempts'], 'default', 'value' => 0],
            [['uid', 'user_id', 'type', 'expires', 'created'], 'required'],
            [['user_id', 'attempts'], 'integer'],
            [['type'], 'string'],
            [['expires', 'disable_until', 'created'], 'safe'],
            [['uid'], 'string', 'max' => 32],
            [['code'], 'string', 'max' => 64],
            [['email'], 'string', 'max' => 255],
            ['type', 'in', 'range' => array_keys(self::optsType())],
            [['uid'], 'unique'],
            [['user_id'], 'unique'],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('model', 'ID'),
            'uid' => Yii::t('model', 'Uid'),
            'user_id' => Yii::t('model', 'User ID'),
            'type' => Yii::t('model', 'Type'),
            'code' => Yii::t('model', 'Code'),
            'attempts' => Yii::t('model', 'Attempts'),
            'expires' => Yii::t('model', 'Expires'),
            'disable_until' => Yii::t('model', 'Disable Until'),
            'created' => Yii::t('model', 'Created'),
            'email' => Yii::t('model', 'Email'),
        ];
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }


    /**
     * column type ENUM value labels
     * @return string[]
     */
    public static function optsType()
    {
        return [
            self::TYPE_PRIMARY => Yii::t('model', 'primary'),
            self::TYPE_METHOD => Yii::t('model', 'method'),
            self::TYPE_SUPERVISOR => Yii::t('model', 'supervisor'),
            self::TYPE_SPOUSE => Yii::t('model', 'spouse'),
        ];
    }

    /**
     * @return string
     */
    public function displayType()
    {
        return self::optsType()[$this->type];
    }

    /**
     * @return bool
     */
    public function isTypePrimary()
    {
        return $this->type === self::TYPE_PRIMARY;
    }

    public function setTypeToPrimary()
    {
        $this->type = self::TYPE_PRIMARY;
    }

    /**
     * @return bool
     */
    public function isTypeMethod()
    {
        return $this->type === self::TYPE_METHOD;
    }

    public function setTypeToMethod()
    {
        $this->type = self::TYPE_METHOD;
    }

    /**
     * @return bool
     */
    public function isTypeSupervisor()
    {
        return $this->type === self::TYPE_SUPERVISOR;
    }

    public function setTypeToSupervisor()
    {
        $this->type = self::TYPE_SUPERVISOR;
    }

    /**
     * @return bool
     */
    public function isTypeSpouse()
    {
        return $this->type === self::TYPE_SPOUSE;
    }

    public function setTypeToSpouse()
    {
        $this->type = self::TYPE_SPOUSE;
    }
}
