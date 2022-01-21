<?php

namespace App\Console\Commands;

use App\Curl;
use App\Kernel\Bitrix;
use App\Kernel\Dvor24\Directory\TelegramChanels;
use App\Kernel\Dvor24\Streamers;
use Illuminate\Console\Command;

class checkServers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:checkServer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check server partner';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $streamers = new Streamers();
        $bitrix = new Bitrix();
        $curl = new Curl();
        $telegramChanels = new TelegramChanels();
        $streamersData = $streamers->streamers();
        foreach ($streamersData as $streamer) {
            if ($streamer->monitoring == 1) {
                if ($streamer->ssl == 1) {
                    $ssl = 'https';
                    $port = $streamer->https_port;
                } else {
                    $ssl = 'http';
                    $port = $streamer->http_port;
                }
                $address = '';
                if (empty($streamer->address) == false) {
                    $address = 'Адрес: ' . $streamer->address;
                }
                $headers = [];
                $apiKey = $streamer->api_key;
                array_push($headers, 'X-Vsaas-Api-Key: ' . $apiKey);
                $serverStatus = $curl->getCurl([
                    'url'       => $ssl . '://' . $streamer->hostname . ':' . $port . '/vsaas/api/server',
                    'method'    => 'GET',
                    'headers'   => $headers,
                ]);

                $partnerServer = $streamers->isPartnerServer($streamer->id);
                if (empty($serverStatus) == true) {
                    if (!empty($partnerServer)) {
                        $dataTelegramChanel = $telegramChanels->getTelegramChannels(['partnerId' => $partnerServer->user_id]);
                        if ($dataTelegramChanel['count'] > 0) {
                            $message = "Внимание!\nОт сервера нет ответа!\nНазвание сервера: " . $streamer->title . "\nИмя хоста: " . $streamer->hostname . "\n" . $address;
                            $event = $telegramChanels->checkRolesPartner($partnerServer->user_id, "serverUnavailability", $message);
                            $telegramChanels->sendEventTelegram($event);
                        } else {
                            $messageTelegtam['token'] = '';
                            $messageTelegtam['chat_id'] = '-
                            $messageTelegtam['text'] = "Внимание!\nОт сервера нет ответа!\nНазвание сервера: " . $streamer->title . "\nИмя хоста: " . $streamer->hostname . "\n" . $address;
                            $bitrix->messageTelegram($messageTelegtam);
                        }
                    } else {
                        $messageTelegtam['token'] = '';
                        $messageTelegtam['chat_id'] = '-
                        $messageTelegtam['text'] = "Внимание!\nОт сервера нет ответа!\nНазвание сервера: " . $streamer->title . "\nИмя хоста: " . $streamer->hostname . "\n" . $address;
                        $bitrix->messageTelegram($messageTelegtam);
                    }
                } else {
                    $tmpFolderUsed = ($serverStatus->system->tmpFolder->use * 100) / $serverStatus->system->tmpFolder->total;
                    if ($tmpFolderUsed > 90) {
                        if (!empty($partnerServer)) {
                            $dataTelegramChanel = $telegramChanels->getTelegramChannels($partnerServer->user_id);
                            if ($dataTelegramChanel['count'] > 0) {
                                $message = "Внимание! \nВременная папка на сервере почти заполнена! (" . number_format($tmpFolderUsed, 1, '.', '') . "%)\nНазвание сервера: " . $streamer->title . "\nИмя хоста: " . $streamer->hostname . "\n" . $address;
                                $event = $telegramChanels->checkRolesPartner($partnerServer->user_id, "errorsMSService", $message);
                                $telegramChanels->sendEventTelegram($event);
                            } else {
                                $messageTelegtam['token'] = 'Dqo';
                                $messageTelegtam['chat_id'] = '';
                                $messageTelegtam['text'] = "Внимание!\nВременная папка на сервере почти заполнена! (" . number_format($tmpFolderUsed, 1, '.', '') . "%)\nНазвание сервера: " . $streamer->title . "\nИмя хоста: " . $streamer->hostname . "\n" . $address;
                                $bitrix->messageTelegram($messageTelegtam);
                            }
                        } else {
                            $messageTelegtam['token'] = '';
                            $messageTelegtam['chat_id'] = '';
                            $messageTelegtam['text'] = "Внимание!\nВременная папка на сервере почти заполнена! (" . number_format($tmpFolderUsed, 1, '.', '') . "%)\nНазвание сервера: " . $streamer->title . "\nИмя хоста: " . $streamer->hostname . "\n" . $address;
                            $bitrix->messageTelegram($messageTelegtam);
                        }
                    }

                    foreach ($serverStatus->system->proc as $statProc) {
                        if ($statProc > 80) {
                            if (!empty($partnerServer)) {
                                $dataTelegramChanel = $telegramChanels->getTelegramChannels($partnerServer->user_id);
                                if ($dataTelegramChanel['count'] > 0) {
                                    $message = "Внимание!\nВысокое потребление ресурсов CPU! (" . number_format($statProc, 1, '.', '') . "%)\nНазвание сервера: " . $streamer->title . "\nИмя хоста: " . $streamer->hostname . "\n" . $address;
                                    $event = $telegramChanels->checkRolesPartner($partnerServer->user_id, "exceedingCPUThreshold", $message);
                                    $telegramChanels->sendEventTelegram($event);
                                } else {
                                    $messageTelegtam['token'] = '';
                                    $messageTelegtam['chat_id'] = '';
                                    $messageTelegtam['text'] = "Внимание!\nВысокое потребление ресурсов CPU! (" . number_format($statProc, 1, '.', '') . "%)\nНазвание сервера: " . $streamer->title . "\nИмя хоста: " . $streamer->hostname . "\n" . $address;
                                    $bitrix->messageTelegram($messageTelegtam);
                                }
                            } else {
                                $messageTelegtam['token'] = '';
                                $messageTelegtam['chat_id'] = '';
                                $messageTelegtam['text'] = "Внимание!\nВысокое потребление ресурсов CPU! (" . number_format($statProc, 1, '.', '') . "%)\nНазвание сервера: " . $streamer->title . "\nИмя хоста: " . $streamer->hostname . "\n" . $address;
                                $bitrix->messageTelegram($messageTelegtam);
                            }
                        }
                    }

                    $memoryUsed = (($serverStatus->system->memory->total - $serverStatus->system->memory->available) * 100) / $serverStatus->system->memory->total;
                    if ($memoryUsed > 90) {
                        if (!empty($partnerServer)) {
                            $dataTelegramChanel = $telegramChanels->getTelegramChannels($partnerServer->user_id);
                            if ($dataTelegramChanel['count'] > 0) {
                                $message = "Внимание!\nВысокое потребление ресурсов RAM! (" . number_format($memoryUsed, 1, '.', '') . "%)\nНазвание сервера: " . $streamer->title . "\nИмя хоста: " . $streamer->hostname . "\n" . $address;
                                $event = $telegramChanels->checkRolesPartner($partnerServer->user_id, "exceedingRAMThreshold ", $message);
                                $telegramChanels->sendEventTelegram($event);
                            } else {
                                $messageTelegtam['token'] = '';
                                $messageTelegtam['chat_id'] = '-
                                $messageTelegtam['text'] = "Внимание!\nВысокое потребление ресурсов RAM! (" . number_format($memoryUsed, 1, '.', '') . "%)\nНазвание сервера: " . $streamer->title . "\nИмя хоста: " . $streamer->hostname . "\n" . $address;
                                $bitrix->messageTelegram($messageTelegtam);
                            }
                        } else {
                            $messageTelegtam['token'] = '';
                            $messageTelegtam['chat_id'] = '';
                            $messageTelegtam['text'] = "Внимание!\nВысокое потребление ресурсов RAM! (" . number_format($memoryUsed, 1, '.', '') . "%)\nНазвание сервера: " . $streamer->title . "\nИмя хоста: " . $streamer->hostname . "\n" . $address;
                            $bitrix->messageTelegram($messageTelegtam);
                        }
                    }
                }
            }
        }
    }
}
