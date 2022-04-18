<?php

namespace app\modules\googleDrive\models;

use app\modules\googleDrive\models\Files;
use Yii;

/**
 * This is the model class for table "orders_partners".
 *
 * @property int $id
 * @property string $datetime Дата и время заказа
 * @property string $name_client Имя клиента
 * @property string $name_product Имя продукта
 * @property int $quantity Количество
 * @property int $unit_cost Стоимость за единицу
 * @property string $delivery_type Тип доставки
 * @property string $delivery_city Город доставки
 * @property int $delivery_cost Стоимость доставки курьером
 * @property int $total_cost Итого стоимость
 * @property int $file_id
 *
 * @property Files $file
 */
class OrdersPartners extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'orders_partners';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['datetime', 'name_client', 'name_product', 'quantity', 'unit_cost', 'delivery_type', 'delivery_city', 'delivery_cost', 'total_cost', 'file_id'], 'required'],
            [['datetime'], 'safe'],
            [['quantity', 'unit_cost', 'delivery_cost', 'total_cost', 'file_id'], 'integer'],
            [['name_client', 'name_product'], 'string', 'max' => 255],
            [['delivery_type'], 'string', 'max' => 50],
            [['delivery_city'], 'string', 'max' => 100],
            [['file_id'], 'exist', 'skipOnError' => true, 'targetClass' => Files::className(), 'targetAttribute' => ['file_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'datetime' => 'Datetime',
            'name_client' => 'Name Client',
            'name_product' => 'Name Product',
            'quantity' => 'Quantity',
            'unit_cost' => 'Unit Cost',
            'delivery_type' => 'Delivery Type',
            'delivery_city' => 'Delivery City',
            'delivery_cost' => 'Delivery Cost',
            'total_cost' => 'Total Cost',
            'file_id' => 'File ID',
        ];
    }

    /**
     * Gets query for [[File]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getFile()
    {
        return $this->hasOne(Files::className(), ['id' => 'file_id']);
    }
}