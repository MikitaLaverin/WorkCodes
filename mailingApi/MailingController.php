<?php

namespace App\Http\Controllers\ApiController\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Events\FollowMailing;

use App\Kernel\Dvor24\Notification\Notifications;
use App\Kernel\Dvor24\Directory\TelegramChanels;
use App\Kernel\Dvor24\Notification\Mailings;
use App\Kernel\Dvor24\Facilities;
use App\Kernel\Dvor24\Support;
use App\Kernel\Dvor24\Users;

class MailingController extends Controller
{
	public function addMailing(Request $request)
	{
		$user = new Users();
		$mailing = new Mailings();

		$headers = $request->headers;
		$userApiKey = "";
		$sessionToken = "";

		foreach ($headers as $key => $value) {
			if ($key == "x-vsaas-api-key") {
				$userApiKey = $value[0];
				continue;
			}
			if ($key == "x-vsaas-session") {
				$sessionToken = $value[0];
				continue;
			}
		}

		if (empty($userApiKey) || empty($sessionToken)) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "401",
					"message" => "Unauthorized"
				]
			]);
		}

		$authData = $user->checkAuthHeader($userApiKey, $sessionToken);

		if (empty($authData)) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "401",
					"message" => "Unauthorized"
				]
			]);
		}
		if ($authData->sid != "admin" && $authData->sid != "partner") return json_encode([
			"success" => "false",
			"error" => [
				"code" => "403",
				"message" => "Access error"
			]
		]);

		$author = $authData->login;
		$userId = $authData->user_id;
		$previews = $request->input('previews');
		$message = $request->input('message');
		$notificationStatus = $request->input('notificationStatusId');

		if (empty($previews) || empty($message)) return json_encode([
			"success" => "false",
			"error" => [
				"code" => "400",
				"message" => "Text mailing is empty"
			]
		]);

		if (empty($notificationStatus)) return json_encode([
			"success" => "false",
			"error" => [
				"code" => "400",
				"message" => "Status mailing is empty"
			]
		]);

		$massiveIds = $request->input('massiveId');
		$date = time();

		DB::beginTransaction();

		switch ($notificationStatus) {
			case '2':
				if (empty($massiveIds)) return json_encode([
					"success" => "false",
					"error" => [
						"code" => "400",
						"message" => "Select users or objects"
					]
				]);
				break;
			case '4':
				$date = $request->input('date');
				break;
		}

		try {
			$mailingId = $mailing->addMailing([
				'author' => $author,
				'user_id' => $userId,
				'date' => $date,
				'previews' => $previews,
				'message' => $message,
				'notification_status_id' => $notificationStatus,
				'type' => 'Not selected',
				'count' => 0,
				'end_date_publication' => null
			]);

			if (!is_numeric($mailingId)) {
				DB::rollBack();
				return json_encode([
					"success" => "false",
					"error" => [
						"code" => "500",
						"message" => "Interval server error"
					]
				]);
			}

			if (!empty($massiveIds)) {
				$request->merge(["id" => $mailingId, 'system' => true]);
				$result = $this->addUsers($request);

				if (json_decode($result)->success != "true") {
					DB::rollBack();
					return $result;
				}
			}
		} catch (\Throwable $th) {
			DB::rollBack();
			// $parametr['message'] = 'Возникла техническая неисправность, при создание рассылки. Ошибка: ' . $th->getMessage();
			// $parametr['token'] = '';
			// $parametr['chatId'] = '';

			// try {
			// 	$telegram->sendMessageTelegramChanel($parametr);
			// } catch (\Throwable $th) {
			// 	file_put_contents(storage_path() . '/logs/telegramError.txt', "Сломался телеграм канал в добавление рассылки", FILE_APPEND);
			// }
		}

		DB::commit();

		return json_encode([
			"success" => "true"
		]);
	}

	public function addUsers(Request $request)
	{
		$user = new Users();
		$mailing = new Mailings();
		$telegram = new TelegramChanels();
		$notification = new Notifications();
		$facilities = new Facilities();

		$headers = $request->headers;
		$userApiKey = "";
		$sessionToken = "";

		foreach ($headers as $key => $value) {
			if ($key == "x-vsaas-api-key") {
				$userApiKey = $value[0];
				continue;
			}
			if ($key == "x-vsaas-session") {
				$sessionToken = $value[0];
				continue;
			}
		}

		if (empty($userApiKey) || empty($sessionToken)) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "401",
					"message" => "Unauthorized"
				]
			]);
		}

		$authData = $user->checkAuthHeader($userApiKey, $sessionToken);

		if (empty($authData)) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "401",
					"message" => "Unauthorized"
				]
			]);
		}
		if ($authData->sid != "admin" && $authData->sid != "partner") return json_encode([
			"success" => "false",
			"error" => [
				"code" => "403",
				"message" => "Access error"
			]
		]);

		$mailingId = $request->input('id');

		if ($authData->sid != "admin" && !$mailing->checkMailingPartner($authData->user_id, $mailingId)) return json_encode([
			"success" => "false",
			"error" => [
				"code" => "403",
				"message" => "Access error"
			]
		]);

		$system = $request->boolean('system', false);
		$typeUser = $request->input('typeUsers');
		$massiveId = $request->input('massiveId');
		parse_str($massiveId, $massiveId);

		$notificationStatus = $mailing->getStatusMailing($mailingId);

		if ($notificationStatus != 4 && $notificationStatus != 1 && !$system) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "400",
					"message" => "It is not possible to add users"
				]
			]);
		}

		$usersMailing = $mailing->getIdUserNotification($mailingId);
		$count = count($usersMailing);

		$massiveId = array_diff($massiveId, $usersMailing);

		if (empty($massiveId)) return json_encode([
			"success" => "false",
			"error" => [
				"code" => "400",
				"message" => "Users have already been added"
			]
		]);

		$updateMailing = $mailing->changeMailing($mailingId, [
			'count' => $count + count($massiveId),
			'type' => $typeUser
		]);

		if (!$updateMailing) return json_encode([
			"success" => "false",
			"error" => [
				"code" => "500",
				"message" => "Interval server error"
			]
		]);

		$createdAt = date('Y-M-d H:i:s');
		$updatedAt = date('Y-M-d H:i:s');
		$listUsers = [];

		switch ($typeUser) {
			case 'users':
				$usersData = $user->getSidUsers($massiveId);

				foreach ($usersData as $userData) {
					array_push($listUsers, [
						"type" => 'mailings',
						"user_id" => $userData->id,
						"status" => false,
						"created_at" => $createdAt,
						"updated_at" =>  $updatedAt,
						"sid" => $userData->sid,
						"mailing_id" => $mailingId
					]);
				}
				break;
			case 'objects':
				$objectData = $facilities->objects(['objectId' => $massiveId, 'prefix' => true]);

				foreach ($objectData as $object) {
					$userIds = $user->getAllUserForObject($object->country_prefix, $object->region_prefix, $object->id, true);

					foreach ($userIds as $userId) {
						array_push($listUsers, [
							"type" => 'mailings',
							"user_id" => $userId,
							"status" => false,
							"created_at" => $createdAt,
							"updated_at" =>  $updatedAt,
							"sid" =>  'client',
							"mailing_id" => $mailingId
						]);
					}
				}
				break;
			default:
				return json_encode([
					"success" => "false",
					"error" => [
						"code" => "400",
						"message" => "Type of users is empty"
					]
				]);
				break;
		}

		$addNotificationUsers = $notification->addNotification($listUsers);
		if (!$addNotificationUsers) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "500",
					"message" => "Interval server error"
				]
			]);
		}

		if ($notificationStatus == '2') {
			$data = [];
			$data = $mailing->mailings(['system' => true, 'mailingIds' => [$mailingId]]);
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

		return json_encode([
			"success" => "true"
		]);
	}

	public function deleteUsers(Request $request)
	{
		$user = new Users();
		$mailing = new Mailings();
		$notification = new Notifications();

		$headers = $request->headers;
		$userApiKey = "";
		$sessionToken = "";

		foreach ($headers as $key => $value) {
			if ($key == "x-vsaas-api-key") {
				$userApiKey = $value[0];
				continue;
			}
			if ($key == "x-vsaas-session") {
				$sessionToken = $value[0];
				continue;
			}
		}

		if (empty($userApiKey) || empty($sessionToken)) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "401",
					"message" => "Unauthorized"
				]
			]);
		}

		$authData = $user->checkAuthHeader($userApiKey, $sessionToken);

		if (empty($authData)) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "401",
					"message" => "Unauthorized"
				]
			]);
		}
		if ($authData->sid != "admin" && $authData->sid != "partner") return json_encode([
			"success" => "false",
			"error" => [
				"code" => "403",
				"message" => "Access error"
			]
		]);

		$id = $request->input('id');

		if ($authData->sid != "admin" && !$mailing->checkMailingPartner($authData->user_id, $id)) return json_encode([
			"success" => "false",
			"error" => [
				"code" => "403",
				"message" => "Access error"
			]
		]);

		$massiveId = $request->input('massiveId');
		parse_str($massiveId, $massiveId);

		if (empty($massiveId)) return json_encode([
			"success" => "false",
			"error" => [
				"code" => "400",
				"message" => "Users not seleted"
			]
		]);

		$statusMailing = $mailing->getStatusMailing($id);

		if (empty($statusMailing) || !is_numeric($statusMailing)) return json_encode([
			"success" => "false",
			"error" => [
				"code" => "500",
				"message" => "Interval server error"
			]
		]);

		if ($statusMailing != 1 && $statusMailing != 4) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "403",
					"message" => "It is not possible to delete users"
				]
			]);
		}

		$count = count($mailing->getIdUserNotification($id));
		$updateMailing = $mailing->changeMailing($id, [
			'count' => $count - count($massiveId),
			'type' => 'users'
		]);

		if (!$updateMailing) return json_encode([
			"success" => "false",
			"error" => [
				"code" => "500",
				"message" => "Interval server error"
			]
		]);

		$result = $notification->deleteNotification($id, $massiveId);

		if (!$result) return json_encode([
			"success" => "false",
			"error" => [
				"code" => "500",
				"message" => "Interval server error"
			]
		]);

		return json_encode([
			"success" => "true"
		]);
	}

	public function changeMailing(Request $request)
	{
		$user = new Users();
		$mailing = new Mailings();

		$headers = $request->headers;
		$userApiKey = "";
		$sessionToken = "";

		foreach ($headers as $key => $value) {
			if ($key == "x-vsaas-api-key") {
				$userApiKey = $value[0];
				continue;
			}
			if ($key == "x-vsaas-session") {
				$sessionToken = $value[0];
				continue;
			}
		}

		if (empty($userApiKey) || empty($sessionToken)) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "401",
					"message" => "Unauthorized"
				]
			]);
		}

		$authData = $user->checkAuthHeader($userApiKey, $sessionToken);

		if (empty($authData)) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "401",
					"message" => "Unauthorized"
				]
			]);
		}
		if ($authData->sid != "admin" && $authData->sid != "partner") return json_encode([
			"success" => "false",
			"error" => [
				"code" => "403",
				"message" => "Access error"
			]
		]);

		$id = $request->input('id');
		if (empty($id)) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "400",
					"message" => "ID mailing not transmitted"
				]
			]);
		}

		if ($authData->sid != "admin" && !$mailing->checkMailingPartner($authData->user_id, $id)) return json_encode([
			"success" => "false",
			"error" => [
				"code" => "403",
				"message" => "Access error"
			]
		]);

		$statusMailing = $mailing->getStatusMailing($id);

		if (empty($statusMailing) || !is_numeric($statusMailing)) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "500",
					"message" => "Interval server error"
				]
			]);
		}

		$notificationStatusId = $request->input('notificationStatusId');
		$previews = $request->input('previews');
		$message = $request->input('message');

		$massiveUsers = $mailing->getIdUserNotification($id);

		$params = [];
		if (!empty($previews)) $params['previews'] = $previews;
		if (!empty($message)) $params['message'] = $message;
		if (!empty($notificationStatusId)) $params['notification_status_id'] = $notificationStatusId;
		else return json_encode([
			"success" => "false",
			"error" => [
				"code" => "400",
				"message" => "Status not transferred"
			]
		]);

		$params['date'] = time();

		switch ($statusMailing) {
			case 1: //Не опубликовано
				switch ($notificationStatusId) {
					case '2':
						if (count($massiveUsers) == 0) return json_encode([
							"success" => "false",
							"error" => [
								"code" => "400",
								"message" => "The mailing has no users to send"
							]
						]);
						break;
					case '4':
						$date = $request->input('date');
						if (!empty($date)) $params['date'] = $date;
						else return json_encode([
							"success" => "false",
							"error" => [
								"code" => "400",
								"message" => "Date is incorrect"
							]
						]);
						break;
				}
				break;
			case 2: //Oпубликовано
				if ($notificationStatusId == "3") {
					$params = [];
					$params['notification_status_id'] = $notificationStatusId;
					$params['end_date_publication'] = time();
				} else return json_encode([
					"success" => "false",
					"error" => [
						"code" => "403",
						"message" => "Changes cannot be applied"
					]
				]);
				break;
			case 3: //Снято
				return json_encode([
					"success" => "false",
					"error" => [
						"code" => "400",
						"message" => "Changes cannot be applied"
					]
				]);
				break;
			case 4: //Заплaнированно
				switch ($notificationStatusId) {
					case '2':
						if (count($massiveUsers) == 0) {
							return json_encode([
								"success" => "false",
								"error" => [
									"code" => "401",
									"message" => "The mailing has no users to send"
								]
							]);
						}
						break;
					case '4':
						$date = $request->input('date');
						if (!empty($date)) $params['date'] = $date;
						else return json_encode([
							"success" => "false",
							"error" => [
								"code" => "400",
								"message" => "Date is incorrect"
							]
						]);
						break;
				}
				break;
		}

		$updateMailing = $mailing->changeMailing($id, $params);
		if (!$updateMailing) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "500",
					"message" => "Interval server error"
				]
			]);
		}

		if (!empty($massiveUsers)) {
			$data = [];
			$data = $mailing->mailings(['system' => true, 'mailingIds' => [$id]]);
			if (($statusMailing == 1 || $statusMailing == 4)  && $notificationStatusId == "2") {
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
			} elseif ($statusMailing == 2 && $notificationStatusId == "3") {
				try {
					event(new FollowMailing(json_encode($data), 'delete'));
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

		return json_encode([
			"success" => "true"
		]);
	}

	public function massChangeStatusMailing(Request $request)
	{
		$user = new Users();
		$mailing = new Mailings();

		$headers = $request->headers;
		$userApiKey = "";
		$sessionToken = "";

		foreach ($headers as $key => $value) {
			if ($key == 'x-vsaas-api-key') {
				$userApiKey = $value[0];
				continue;
			}
			if ($key == 'x-vsaas-session') {
				$sessionToken = $value[0];
				continue;
			}
		}

		if (empty($userApiKey) || empty($sessionToken)) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "401",
					"message" => "Unauthorized"
				]
			]);
		}

		$authData = $user->checkAuthHeader($userApiKey, $sessionToken);

		if (empty($authData)) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "401",
					"message" => "Unauthorized"
				]
			]);
		}

		if ($authData->sid != "admin" && $authData->sid != "partner") {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "403",
					"message" => "Access error"
				]
			]);
		}

		$ids = $request->input('id');
		parse_str($ids, $ids);

		$mailings = $mailing->mailings(['mailingIds' => $ids]);
		foreach ($mailings as $mail) {
			if ($mail->notification_status_id != 2) return json_encode([
				"success" => "false",
				"error" => [
					"code" => "400",
					"message" => "Selected mailings cannot be removed from publication"
				]
			]);
			if ($authData->sid != "admin" && $mail->user_id != $authData->user_id) return json_encode([
				"success" => "false",
				"error" => [
					"code" => "403",
					"message" => "Access error"
				]
			]);
		}

		$result = $mailing->masseChangeMailing($ids, [
			'notification_status_id' => '3',
			'end_date_publication' => time()
		]);

		if (!$result) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "500",
					"message" => "Interval server error"
				]
			]);
		}

		$data = [];
		$data = $mailing->mailings([
			'system' => true,
			'mailingIds' => $ids
		]);

		try {

			event(new FollowMailing(json_encode($data), 'delete'));
		} catch (\Throwable $th) {
			// $parametr['message'] = 'Возникла техническая неисправность, возможно сокет не запущен. Ошибка: ' . $th->getMessage();
			// $parametr['token'] = '';
			// $parametr['chatId'] = '

			// try {
			// 	$telegram->sendMessageTelegramChanel($parametr);
			// } catch (\Throwable $th) {
			// 	file_put_contents(storage_path() . '/logs/telegramError.txt', "Сломался телеграм канал в добавление рассылки", FILE_APPEND);
			// }
		}

		return json_encode([
			"success" => "true"
		]);
	}

	public function getUsersMailing(Request $request)
	{
		$user = new Users();
		$mailing = new Mailings();

		$headers = $request->headers;
		$userApiKey = "";
		$sessionToken = "";

		foreach ($headers as $key => $value) {
			if ($key == 'x-vsaas-api-key') {
				$userApiKey = $value[0];
				continue;
			}
			if ($key == 'x-vsaas-session') {
				$sessionToken = $value[0];
				continue;
			}
		}

		if (empty($userApiKey) || empty($sessionToken)) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "401",
					"message" => "Unauthorized"
				]
			]);
		}

		$authData = $user->checkAuthHeader($userApiKey, $sessionToken);

		if ($authData->sid != "admin" && $authData->sid != "partner") {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "403",
					"message" => "Access error"
				]
			]);
		}

		$mailingId = $request->input('id');
		$offset = $request->input('offset');
		$limit = $request->input('limit');
		$search = $request->input('search');

		if ($authData->sid == "partner" && !$mailing->checkMailingPartner($authData->user_id, $mailingId)) return json_encode([
			"success" => "false",
			"error" => [
				"code" => "403",
				"message" => "Access error"
			]
		]);

		if (!empty($offset)) $params["offset"] = $offset;
		if (!empty($limit)) $params["limit"] = $limit;
		if (!empty($search)) $params["search"] = $search;

		$params["info"] = true;
		$result = $mailing->getNotificationClient($mailingId, $params);

		unset($params["offset"]);
		unset($params["limit"]);
		$params["count"] = true;
		$count = $mailing->getNotificationClient($mailingId, $params);

		return json_encode([
			"success" => "true",
			"result" => [
				"result" => $result,
				"count" => $count
			]
		]);
	}

	public function getMailings(Request $request)
	{
		$user = new Users();
		$mailing = new Mailings();
		$support = new Support();

		$headers = $request->headers;
		$userApiKey = "";
		$sessionToken = "";

		foreach ($headers as $key => $value) {
			if ($key == 'x-vsaas-api-key') {
				$userApiKey = $value[0];
				continue;
			}
			if ($key == 'x-vsaas-session') {
				$sessionToken = $value[0];
				continue;
			}
		}

		if (empty($userApiKey) || empty($sessionToken)) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "401",
					"message" => "Unauthorized"
				]
			]);
		}

		$authData = $user->checkAuthHeader($userApiKey, $sessionToken);

		$offset = $request->input('offset');
		$limit = $request->input('limit');
		$search = $request->input('search');

		if (!empty($offset)) $params["offset"] = $offset;
		if (!empty($limit)) $params["limit"] = $limit;
		if (!empty($search)) $params["search"] = $search;

		switch ($authData->sid) {
			case 'client':
				$params['userId'] = $authData->user_id;
				$result = $mailing->getMailingsClient($params);
				unset($params["offset"]);
				unset($params["limit"]);
				$params["count"] = true;
				$count = $mailing->getMailingsClient($params);
				return json_encode([
					"success" => "true",
					"result" => [
						"result" => $result,
						"count" => $count
					]
				]);
				break;
			case 'admin':
				if (!empty($request->input('userId'))) $params['userIds'] = $request->input('userId');
				break;
			case 'partner':
				$params['userIds'] = [$authData->user_id];
				break;
			default:
				return json_encode([
					"success" => "false",
					"error" => [
						"code" => "500",
						"message" => "Interval server error"
					]
				]);
				break;
		}

		$result = $mailing->mailings($params);
		unset($params["offset"]);
		unset($params["limit"]);
		$params["count"] = true;
		$count = $mailing->mailings($params);

		return json_encode([
			"success" => "true",
			"result" => [
				"result" => $result,
				"count" => $count
			]
		]);
	}
}
