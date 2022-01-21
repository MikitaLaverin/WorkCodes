<?php

namespace App\Console\Commands;

use App\Kernel\Dvor24\Billing\HistoryPayments;
use App\Kernel\Dvor24\Billing\PartnerMarketplaces;
use App\Kernel\Dvor24\Billing\PaymentOrders;
use App\Kernel\Dvor24\Billing\ReccuringPayments;
use App\Kernel\Dvor24\Directory\TelegramChanels;
use App\Kernel\Dvor24\Users;
use App\Kernel\TinkoffMerchantAPI;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class recurringPayments extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'billing:recurring';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Scheduler that debits money for recurring payments';

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
	 * Log 
	 * 
	 * @var array
	 */
	protected $log;

	/**
	 * List payments 
	 * 
	 * @var array
	 */
	protected $reccuringPayments;

	/**
	 * Execute the console command.
	 *
	 * @return int
	 */
	public function handle()
	{
		$reccuring = new ReccuringPayments();

		// return;
		$time = time();

		$this->reccuringPayments = $reccuring->getReccuringPayment(["system" => true]);
		$this->log = json_decode(json_encode($reccuring->getLogRecurring()), true);

		foreach ($this->reccuringPayments as $payment) {
			if (empty($payment->rebillId)) continue;
			else {
				$timeCancellation = $this->correctTimeZone($payment->offset_minutes, strtotime($payment->day . "." . date('m.Y', $time). " 10:00:00"), "-");
				$checkEndMonth = $this->paymentEndMonthly($payment->day, $time);
				if ($time >= $timeCancellation || $checkEndMonth) {
					if (!array_key_exists($payment->user_id, $this->log)) {
						$this->paying($payment, $time);
					} else {
						$logMonth = date('M', $this->log[$payment->user_id][0]['date']);
						if (strtolower($logMonth) != strtolower(date('M', $time))) {
							$this->paying($payment, $time);
						}
					}
				}
			}
		}
	}

	protected function correctTimeZone($offset, $date, $sign)
	{
		$zone = $offset / 60;
		$timeZone = $sign . $zone . " hours";
		return strtotime($timeZone, $date);
	}

	protected function paymentEndMonthly($dayPayment, $time)
	{
		$lastDay = explode("-", date('Y-m-t', $time))[2];
		if ($dayPayment == 29 || $dayPayment == 30 || $dayPayment == 31) {
			if ($dayPayment > $lastDay) return true;
		}
		return false;
	}

	protected function paying($payment, $time)
	{
		$telegram = new TelegramChanels();
		$user = new Users();
		$recurringLog = new ReccuringPayments();
		$historyPayment = new HistoryPayments();
		$tinkoff = new TinkoffMerchantAPI();
		$marketplace = new PartnerMarketplaces();
		$paymentOrder = new PaymentOrders();

		DB::beginTransaction();
		try {
			$shopCode = $marketplace->getShopCodePartner('client', $payment->user_id);
			if (!$shopCode) {
				DB::rollBack();
				$data['message'] = 'shopCode партнера не найден. ID пользователя: ' . $payment->user_id;
				$data['token'] = '';
				$data['chatId'] = '-';

				try {
					$telegram->sendMessageTelegramChanel($data);
				} catch (\Throwable $th) {
					file_put_contents(storage_path() . '/tmp/billingRecurrentLog.txt', "Сообщение об ошибке не отправлено в телеграм: " . date('d.m.Y H:i:s') . "\n", FILE_APPEND);
				}
				return;
			}

			$getRequisitesPartner = $marketplace->getShopCodePartner('client', $payment->user_id, true);

			$orderId = $paymentOrder->add([
				'user_id' => $payment->user_id,
				'date' => time(),
				'cost' => $payment->sum,
				'type' => "reccuringRobot",
				'payment' => false
			]);

			if (!$orderId) {
				DB::rollBack();
				$data['message'] = 'Заказ не создан. ID пользователя: ' . $payment->user_id;
				$data['token'] = '';
				$data['chatId'] = '';

				try {
					$telegram->sendMessageTelegramChanel($data);
				} catch (\Throwable $th) {
					file_put_contents(storage_path() . '/tmp/billingRecurrentLog.txt', "Сообщение об ошибке не отправлено в телеграм: " . date('d.m.Y H:i:s') . "\n", FILE_APPEND);
				}
				return;
			}

			$params = [
				"Amount" => $payment->sum * 100,
				"OrderId" => $orderId,
				"Description" => "Оказание услуг по видеонаблюдению",
				"Currency" => "643",
				"CustomerKey" => $payment->user_id,
				"PayType" => "O",
				"Language" => "ru",
				"NotificationURL" => Config::get('app.url') . "/api/v2/payment/tinkoff/notification",
				"Shops" => [
					[
						"ShopCode" => $shopCode,
						"Amount" => $payment->sum * 100,
						"Name" => "Оказание услуг по видеонаблюдению"
					]
				],
				"Receipt" => [
					"Items" => [
						[
							"AgentData" => [
								"AgentSign" => "paying_agent",
								"OperationName" => "Позиция чека",
								"Phones" => ["+71183032"],
								"ReceiverPhones" => 
							],
							"SupplierInfo" => [
								"Phones" => [$getRequisitesPartner->phones],
								"Name" => $getRequisitesPartner->name,
								"Inn" => $getRequisitesPartner->inn
							],
							"Name" => "Оказание услуг по видеонаблюдению",
							"Price" => $payment->sum * 100,
							"Quantity" => 1,
							"Amount" => $payment->sum * 100,
							"PaymentObject" => "service",
							"ShopCode" => $shopCode,
							"Tax" => "none"
						]
					],
					"Email" => $payment->email,
					"Taxation" => "usn_income",
				]
			];


			$tinkoff->init($params);
			$queryInit = json_decode($tinkoff->response);
			if ($queryInit->Status == "NEW") {
				$paymentOrder->update($orderId, [
					'paymentId' => $queryInit->PaymentId
				]);
				$paramsChange = [
					"PaymentId" => $queryInit->PaymentId,
					"RebillId" => $payment->rebillId,
					"SendEmail" => "true",
					"InfoEmail" => $payment->email
				];
				$tinkoff->charge($paramsChange);
				if ($tinkoff->status == "CONFIRMED") {
					$result = $user->manageBalanceUser([0 => $payment->user_id], $payment->sum, 'refill');
					if (!$result) {
						DB::rollBack();
						$data['message'] = 'Изменение баланса пользователя в роботе автоплатежей завершилось с ошибкой.';
						$data['token'] = '';
						$data['chatId'] = '';

						try {
							$telegram->sendMessageTelegramChanel($data);
						} catch (\Throwable $th) {
							file_put_contents(storage_path() . '/tmp/billingRecurrentLog.txt', "Сообщение об ошибке не отправлено в телеграм: " . date('d.m.Y H:i:s') . "\n", FILE_APPEND);
						}
						return;
					}

					// $result = $historyPayment->addHistory([
					// 	'sum' => $payment->sum,
					// 	'user_id' => $payment->user_id,
					// 	'date' => $time,
					// 	'comment' => "Пополнение баланса (автоплатеж)",
					// 	'type_history_payment_id' => 2,
					// 	'author' => 'robot',
					// 	'created_at' => date('Y-M-d H:i:s'),
					// 	'updated_at' => date('Y-M-d H:i:s')
					// ]);

					// if (!$result) {
					// 	DB::rollBack();
					// 	$data['message'] = 'Добавление в историю платежа закончилось ошибкой (автоплатеж).';
					// 	$data['token'] = '';
					// 	$data['chatId'] = '';

					// 	try {
					// 		$telegram->sendMessageTelegramChanel($data);
					// 	} catch (\Throwable $th) {
					// 		file_put_contents(storage_path() . '/tmp/billingRecurrentLog.txt', "Сообщение об ошибке не отправлено в телеграм: " . date('d.m.Y H:i:s') . "\n", FILE_APPEND);
					// 	}
					// 	return;
					// }

					$result = $recurringLog->addLogRecurring([
						'recurring_payment_id' => $payment->id,
						'user_id' => $payment->user_id,
						'date' => $time
					]);

					if (!$result) {
						DB::rollBack();
						$data['message'] = 'Запись в логи не добавлена (автоплатеж).';
						$data['token'] = '';
						$data['chatId'] = '';

						try {
							$telegram->sendMessageTelegramChanel($data);
						} catch (\Throwable $th) {
							file_put_contents(storage_path() . '/tmp/billingRecurrentLog.txt', "Сообщение об ошибке не отправлено в телеграм: " . date('d.m.Y H:i:s') . "\n", FILE_APPEND);
						}
						return;
					}
				} else {
					DB::commit();
					$data['message'] = 'Ошибка при попытке списать деньги по автоплатежу. Ошибка: '.$tinkoff->response;
					$data['token'] = '2034317087:AAHvejk2mFwnBLCwKaZbBC6mjTcaYnmRGO4';
					$data['chatId'] = '-663596196';

					try {
						$telegram->sendMessageTelegramChanel($data);
					} catch (\Throwable $th) {
						file_put_contents(storage_path() . '/tmp/billingRecurrentLog.txt', "Сообщение об ошибке не отправлено в телеграм: " . date('d.m.Y H:i:s') . "\n", FILE_APPEND);
					}
					return;
				}
			}
		} catch (\Throwable $th) {
			DB::rollBack();
			$data['message'] = 'Место возникновения ошибки робот списывания. ' . $th->getMessage().
			' Строчка: '. $th->getLine(). 'Код: '.$th->getCode().' Трассировка: '. $th->getTraceAsString();
			$data['token'] = '';
			$data['chatId'] = '';

			try {
				$telegram->sendMessageTelegramChanel($data);
			} catch (\Throwable $th) {
				file_put_contents(storage_path() . '/tmp/billingRecurrentLog.txt', "Сообщение об ошибке не отправлено в телеграм: " . date('d.m.Y H:i:s') . "\n", FILE_APPEND);
			}

			file_put_contents(storage_path() . '/tmp/billingRecurrentLog.txt', "Произошла техническая ошибка в роботе (автоплатежей): " . date('d.m.Y H:i:s') . "\n", FILE_APPEND);
			return;
		}
		DB::commit();
	}
}
