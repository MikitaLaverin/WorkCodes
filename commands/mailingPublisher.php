<?php

namespace App\Console\Commands;

use App\Events\FollowMailing;
use App\Kernel\Dvor24\Notification\Mailings;
use Illuminate\Console\Command;

class mailingPublisher extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'mailing:publish';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'A scheduler that publishes scheduled mailings';

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
		$mailing = new Mailings();
		$currentTime = time();

		$mailingData = $mailing->mailings([
			'system' => true,
			'type' => 4,
			'timeZone' => true
		]);

		$mailingIds = [];
		$mailingIdsNoUser = [];

		if (count($mailingData) != 0) {
			foreach ($mailingData as $mail) {
				$time = $this->correctTimeZone($mail->offset_minutes, $mail->date, '-');
				if ($currentTime >= $time) {
					$userIds = json_decode($mail->userids)->userIds;
					if (count($userIds) > 0) array_push($mailingIds, $mail->id);
					else array_push($mailingIdsNoUser, $mail->id);
				}
			}

			$mailing->masseChangeMailing($mailingIds, ['notification_status_id' => 2]);
			$mailing->masseChangeMailing($mailingIdsNoUser, ['notification_status_id' => 2]);

			$data = [];
			$data = $mailing->mailings([
				'system' => true,
				'mailingIds' => $mailingIds
			]);

			try {
				event(new FollowMailing(json_encode($data), 'add'));
			} catch (\Throwable $th) {
				// $parametr['message'] = 'Возникла техническая неисправность, возможно сокет не запущен. Ошибка: ' . $th->getMessage();
				// $parametr['token'] = '';
				// $parametr['chatId'] = '';

				// try {
				// 	$telegram->sendMessageTelegramChanel($parametr);
				// } catch (\Throwable $th) {
				// 	file_put_contents(storage_path() . '/logs/telegramError.txt', "Сломался телеграм канал в добавление рассылки", FILE_APPEND);
				// }
			}
		}
	}

	public function correctTimeZone($offset, $date, $sign)
	{
		$zone = $offset / 60;
		$timeZone = $sign . $zone . " hours";
		return strtotime($timeZone, $date);
	}
}
