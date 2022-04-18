<?php

namespace app\modules\googleDrive\controllers;

use app\modules\googleDrive\models\Files;
use app\modules\googleDrive\models\OrdersPartners;
use app\modules\googleDrive\models\Partners;
use Google_Client;
use Google_Exception;
use Google_Service_Drive;
use League\Csv\Reader;
use League\Csv\Exception;
use RuntimeException;
use Throwable;
use Yii;
use yii\console\Controller;
use yii\helpers\ArrayHelper;


class FilesController extends Controller
{
    /**
     * @var string
     */
    protected $filesPath;
    private $filesPathUpload;
    private $filesPathArchive;
    private $filesPathInvalid;

    /**
     * Функция для получения клиента Google
     *
     * @throws Google_Exception
     */
    protected function getClient()
    {
        $client = new Google_Client();
        $client->setApplicationName('Get Partners Files');
        $client->setScopes((array)Google_Service_Drive::DRIVE_READONLY);
        $client->setAuthConfig(Yii::$app->basePath . '\config\credentials.json');

        $client->useApplicationDefaultCredentials();

        return $client;
    }

    /**
     * Функция для проверки папок на диске
     *
     * @return void
     */
    protected function checkDirectories()
    {
        $this->filesPath = dirname(Yii::$app->basePath) . '/files';
        $this->filesPathUpload = $this->filesPath . '/upload';
        $this->filesPathArchive = $this->filesPath . '/archive';
        $this->filesPathInvalid = $this->filesPath . '/invalid';

        if (!is_dir($this->filesPathUpload) && !mkdir($this->filesPathUpload, 0777, true) && !is_dir($this->filesPathUpload)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $this->filesPathUpload));
        }
        if (!is_dir($this->filesPathArchive) && !mkdir($this->filesPathArchive, 0777, true) && !is_dir($this->filesPathArchive)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $this->filesPathArchive));
        }
        if (!is_dir($this->filesPathInvalid) && !mkdir($this->filesPathInvalid, 0777, true) && !is_dir($this->filesPathInvalid)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $this->filesPathInvalid));
        }
    }

    /**
     * @throws Google_Exception
     * @throws Exception
     */
    public function actionFileProcessing()
    {
        $this->checkDirectories();

        $partners = ArrayHelper::index(Partners::find()->all(), 'name');

        $optParams = array(
            'q' => "'" . json_decode(file_get_contents(Yii::$app->basePath . '\config\googleDrive.json'), false)->folder . "' in parents",
            'fields' => 'files(id, name)'
        );

        $service = new Google_Service_Drive($this->getClient());
        $results = $service->files->listFiles($optParams);

        // файлы на диске
        $googleDriveFile = array_flip(array_keys(ArrayHelper::index($results->getFiles(), static function ($item) {
            return $item[ 'name' ] . (!str_contains($item[ 'name' ], '.csv') ? '.csv' : '');
        })));

        // недостающие файлы на диске
        $needleFiles = array_diff_key($googleDriveFile,
            array_flip(array_diff(scandir($this->filesPathUpload), ['..', '.'])) +
            array_flip(array_diff(scandir($this->filesPathArchive), ['..', '.'])) +
            array_flip(array_diff(scandir($this->filesPathInvalid), ['..', '.']))
        );

        // файлы которые нужно скачать
        $filesDownload = array_filter($results->getFiles(), static function ($item) use ($needleFiles) {
            return isset($needleFiles[ $item[ 'name' ] . (!str_contains($item[ 'name' ], '.csv') ? '.csv' : '') ]);
        });

        // скачиваем новые файлы
        if (count($filesDownload) > 0) {
            foreach ($filesDownload as $file) {
                $fileBody = $service->files->export($file->getId(), 'text/csv', array('alt' => 'media'));
                $fileName = $file->getName() . (!str_contains($file->getName(), '.csv') ? '.csv' : '');
                file_put_contents($this->filesPathUpload . '/' . $fileName, $fileBody->getBody()->getContents());

                $partnerName = explode('_', $fileName)[ 0 ];
                $dateFile = str_replace('.csv', '', explode('_', $fileName)[ 1 ]);

                if (!isset($partners[ $partnerName ])) {
                    $partnerModel = new Partners();
                    $partnerModel->name = $partnerName;
                    $partnerModel->save();
                    $partners[ $partnerName ] = $partnerModel;
                } else {
                    $partnerModel = $partners[ $partnerName ];
                }

                $fileModel = new Files();
                $fileModel->partner_id = $partnerModel->id;
                $fileModel->date = date('Y-m-d', strtotime($dateFile));
                $fileModel->status = Files::STATUS_UPLOAD;
                $fileModel->save();
            }
        }

        $filesModel = ArrayHelper::index(Files::find()->with('partner')->all(), static function ($item) {
            return $item->partner->name . '_' . $item->date;
        });
        $uploadFiles = array_diff(scandir($this->filesPathUpload), ['..', '.']);

        // если есть необработанные файлы, то обрабатываем их
        if (!empty($uploadFiles)) {
            foreach ($uploadFiles as $fileName) {
                $errorFile = false;
                $reader = Reader::createFromPath($this->filesPathUpload . '/' . $fileName);
                $reader->setDelimiter(',');
                $reader->setHeaderOffset(0);
                $records = $reader->getRecords();

                // сохраняем данные файла
                $transaction = Yii::$app->db->beginTransaction();
                try {
                    foreach ($records as $record) {
                        $record = array_values($record);

                        // проверка на полученное количество значений
                        if (count($record) !== 9)
                            throw new RuntimeException();

                        $orderPartner = new OrdersPartners();
                        $orderPartner->attributes = [
                            'datetime' => date('Y-m-d H:i:s', strtotime($record[ 0 ])),
                            'name_client' => $record[ 1 ],
                            'name_product' => $record[ 2 ],
                            'quantity' => $record[ 3 ],
                            'unit_cost' => $record[ 4 ],
                            'delivery_type' => $record[ 5 ],
                            'delivery_city' => $record[ 6 ],
                            'delivery_cost' => $record[ 7 ],
                            'total_cost' => $record[ 8 ],
                            'file_id' => $filesModel[ explode('.csv', $fileName)[ 0 ] ]->id,
                        ];

                        if (!$orderPartner->save()) {
                            throw new RuntimeException(array_values(array_values($orderPartner->errors)[ 0 ])[ 0 ]);
                        }
                    }
                    $transaction->commit();
                } catch (Throwable $e) {
                    $transaction->rollBack();
                    $errorFile = true;
                }

                // перемещаем файлы в нужную директорию
                if (copy($this->filesPathUpload . '/' . $fileName, (!$errorFile ? $this->filesPathArchive : $this->filesPathInvalid) . '/' . $fileName)) {
                    unset($reader, $records);
                    unlink($this->filesPathUpload . '/' . $fileName);
                    if (isset($filesModel[ explode('.csv', $fileName)[ 0 ] ])) {
                        $filesModel[ explode('.csv', $fileName)[ 0 ] ]->status = !$errorFile ? Files::STATUS_ARCHIVE : Files::STATUS_INVALID;
                        $filesModel[ explode('.csv', $fileName)[ 0 ] ]->save();
                    }
                }
            }
        }

        return 0;
    }
}
