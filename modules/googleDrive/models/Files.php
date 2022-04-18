<?php

namespace app\modules\googleDrive\models;

use Yii;

/**
 * This is the model class for table "files".
 *
 * @property int $id
 * @property int $partner_id
 * @property string $date
 * @property int $status
 *
 * @property OrdersPartners[] $ordersPartners
 * @property Partners $partner
 */
class Files extends \yii\db\ActiveRecord
{
    const STATUS_UPLOAD = 1;
    const STATUS_ARCHIVE = 2;
    const STATUS_INVALID = 3;

    public static $statuses = [
        self::STATUS_UPLOAD => 'Загружен',
        self::STATUS_ARCHIVE => 'Обработан',
        self::STATUS_INVALID => 'Повреждён',
    ];
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'files';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['partner_id', 'date', 'status'], 'required'],
            [['partner_id', 'status'], 'integer'],
            [['date'], 'safe'],
            [['partner_id'], 'exist', 'skipOnError' => true, 'targetClass' => Partners::className(), 'targetAttribute' => ['partner_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'partner_id' => 'Partner ID',
            'date' => 'Date',
            'status' => 'Status',
        ];
    }

    /**
     * Gets query for [[OrdersPartners]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getOrdersPartners()
    {
        return $this->hasMany(OrdersPartners::className(), ['file_id' => 'id']);
    }

    /**
     * Gets query for [[Partner]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPartner()
    {
        return $this->hasOne(Partners::className(), ['id' => 'partner_id']);
    }

    public function afterFind()
    {
        $this->date = date('d.m.Y', strtotime($this->date));
        parent::afterFind();
    }
}
