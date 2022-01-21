<?php

namespace App\Http\Controllers\AjaxController\Notification;

use App\Curl;
use App\Events\ReadNotification;
use App\Http\Controllers\Controller;
use App\Kernel\Dvor24\Notification\Mailings;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\Request;

use App\Kernel\Dvor24\Users;

class MailingController extends Controller
{
	public function addMailing(Request $request)
	{
		$user = new Users();
		$curl = new Curl();

		if (empty($user->booleanAccessCheck()) || (!$user->isAdmin() && !$user->isPartner())) return json_encode([
			"success" => "false",
			"code" => "403",
			"message" => "Ошибка доступа"
		]);

		$headers = [];
		$userApiKey = $user->getApiKey();
		$sessionToken = $user->getAuthToken();

		array_push($headers, 'X-Vsaas-Api-Key:' . $userApiKey);
		array_push($headers, "X-Vsaas-Session:" . $sessionToken);

		$date = $request->input('date');
		$nameTemplate = $request->input('nameTemplate');
		$previews = $request->input('previews');
		$messageText = $request->input('message');
		$typeAddition = $request->input('typeAddition');
		$typeUsers = $request->input('typeUsers');
		$massive = $request->input('massiveId');
		$notificationStatusId = $request->input('notificationStatusId');

		$massiveId = http_build_query($massive);

		$result = $curl->getCurl([
			'url' => Config::get('app.url') . "/api/v2/mailing/add",
			'method' => 'POST',
			'headers' => $headers,
			'parametrs' => [
				'date' => $date,
				'nameTemplate' => $nameTemplate,
				'previews' => $previews,
				'message' => $messageText,
				'typeAddition' => $typeAddition,
				'typeUsers' => $typeUsers,
				'notificationStatusId' => $notificationStatusId,
				'massiveId' => $massiveId
			]
		]);

		if ($result->success == 'false') {
			$message = '';
			switch ($result->error->message) {
				case 'Unauthorized':
					$message = 'Не авторизован';
					break;
				case 'Access error':
					$message = 'Ошибка доступа';
					break;
				case 'Text mailing is empty':
					$message = 'Содержание рассылки не заполнено';
					break;
				case 'Status mailing is empty':
					$message = 'Статус рассылки не выбран';
					break;
				case 'Type of addition is empty':
					$message = 'Тип не выбран';
					break;
				case 'Select users or objects':
					$message = 'Выберите пользователей или объекты';
					break;
				case 'Date is incorrect':
					$message = 'Время публикации не выбранно или некорректно';
					break;
				default:
					$result->error->code = '500';
					$message = 'Возникла техническая неисправность';
					break;
			}
			return json_encode([
				'success' => 'false',
				'code' => $result->error->code,
				'message' =>  $message
			]);
		}
		return json_encode([
			"success" => "true"
		]);
	}

	public function changeMailing(Request $request)
	{
		$user = new Users();
		$curl = new Curl();

		if (empty($user->booleanAccessCheck()) || (!$user->isAdmin() && !$user->isPartner())) return json_encode([
			"success" => "false",
			"code" => "403",
			"message" => "Ошибка доступа"
		]);

		$headers = [];
		$userApiKey = $user->getApiKey();
		$sessionToken = $user->getAuthToken();

		array_push($headers, 'X-Vsaas-Api-Key:' . $userApiKey);
		array_push($headers, "X-Vsaas-Session:" . $sessionToken);

		$mailingId = $request->input('id');
		$date = $request->input('date');
		$previews = $request->input('previews');
		$message = $request->input('message');
		$status = $request->input('notificationStatusId');

		$result = $curl->getCurl([
			'url' => Config::get('app.url') . "/api/v2/mailing/change",
			'method' => 'POST',
			'headers' => $headers,
			'parametrs' => [
				'id' => $mailingId,
				'date' => $date,
				'previews' => $previews,
				'message' => $message,
				'notificationStatusId' => $status
			]
		]);

		if ($result->success == 'false') {
			$message = '';
			switch ($result->error->message) {
				case 'Unauthorized':
					$message = 'Не авторизован';
					break;
				case 'Access error':
					$message = 'Ошибка доступа';
					break;
				case 'Changes cannot be applied':
					$message = 'Изменения не могут быть применены';
					break;
				case 'ID mailing not transmitted':
					$message = 'Рассылка не выбранна';
					break;
				case 'The mailing has no users to send':
					$message = 'Рассылка не имеет пользователей для публикации';
					break;
				case 'Date is incorrect':
					$message = 'Время публикации не выбранно или некорректно';
					break;
				default:
					$result->error->code = '500';
					$message = 'Возникла техническая неисправность';
					break;
			}
			return json_encode([
				'success' => 'false',
				'code' => $result->error->code,
				'message' => $message
			]);
		}
		return json_encode([
			"success" => "true"
		]);
	}

	public function massChangeMailing(Request $request)
	{
		$user = new Users();
		$curl = new Curl();

		if (empty($user->booleanAccessCheck()) || (!$user->isAdmin() && !$user->isPartner())) return json_encode([
			"success" => "false",
			"code" => "403",
			"message" => "Ошибка доступа"
		]);

		$headers = [];
		$userApiKey = $user->getApiKey();
		$sessionToken = $user->getAuthToken();

		array_push($headers, 'X-Vsaas-Api-Key:' . $userApiKey);
		array_push($headers, "X-Vsaas-Session:" . $sessionToken);

		$massive = [];
		$massive = $request->input('id');
		$massMailingId = http_build_query($massive);

		$result = $curl->getCurl([
			'url' => Config::get('app.url') . "/api/v2/mailing/change/mass",
			'method' => 'POST',
			'headers' => $headers,
			'parametrs' => [
				'id' => $massMailingId
			]
		]);

		if ($result->success == 'false') {
			$message = '';
			switch ($result->error->message) {
				case 'Unauthorized':
					$message = 'Не авторизован';
					break;
				case 'Access error':
					$message = 'Ошибка доступа';
					break;
				case 'Selected mailings cannot be removed from publication':
					$message = 'Изменения не могут быть применены';
					break;
				default:
					$result->error->code = '500';
					$message = 'Возникла техническая неисправность';
					break;
			}
			return json_encode([
				'success' => 'false',
				'code' => $result->error->code,
				'message' => $message
			]);
		}
		return json_encode([
			"success" => "true"
		]);
	}

	public function addUsers(Request $request)
	{
		$user = new Users();
		$curl = new Curl();

		if (empty($user->booleanAccessCheck())) {
			return json_encode([
				"success" => "false",
				"code" => "401",
				"message" => "Не авторизован"
			]);
		}

		if (!$user->isAdmin() && !$user->isPartner() && !$user->isSupport()) {
			return json_encode([
				'success' => 'false',
				'code' => '403',
				'message' => 'Ошибка доступа'
			]);
		}

		$headers = [];
		$userApiKey = $user->getApiKey();
		$sessionToken = $user->getAuthToken();

		array_push($headers, 'X-Vsaas-Api-Key:' . $userApiKey);
		array_push($headers, "X-Vsaas-Session:" . $sessionToken);

		$id = $request->input('id');
		$massive = $request->input('massiveId');
		$massiveId = http_build_query($massive);

		$result = $curl->getCurl([
			'url' => Config::get('app.url') . "/api/v2/mailing/users/add",
			'method' => 'POST',
			'headers' => $headers,
			'parametrs' => [
				'id' => $id,
				'typeUsers' => 'users',
				'massiveId' => $massiveId
			]
		]);

		if ($result->success == "false") {
			$message = '';
			switch ($result->error->message) {
				case 'Unauthorized':
					$message = 'Не авторизован';
					break;
				case 'Access error':
					$message = 'Ошибка доступа';
					break;
				case 'It is not possible to add users':
					$message = 'Невозможно добавить пользователей';
					break;
				case 'Users have already been added':
					$message = 'Данные пользователи уже добавлены';
					break;
				default:
					$result->error->code = '500';
					$message = 'Возникла техническая неисправность';
					break;
			}
			return json_encode([
				"success" => "false",
				"code" => $result->error->code,
				"message" => $message
			]);
		}

		return json_encode([
			"success" => "true"
		]);
	}

	public function deleteUsers(Request $request)
	{
		$user = new Users();
		$curl = new Curl();

		if (empty($user->booleanAccessCheck()) || (!$user->isAdmin() && !$user->isPartner())) return json_encode([
			"success" => "false",
			"code" => "403",
			"message" => "Ошибка доступа"
		]);

		$headers = [];
		$userApiKey = $user->getApiKey();
		$sessionToken = $user->getAuthToken();

		array_push($headers, 'X-Vsaas-Api-Key:' . $userApiKey);
		array_push($headers, "X-Vsaas-Session:" . $sessionToken);

		$id = $request->input('id');
		$massive = $request->input('massiveId');
		$massiveId = http_build_query($massive);

		$result = $curl->getCurl([
			'url' => Config::get('app.url') . "/api/v2/mailing/users/delete",
			'method' => 'POST',
			'headers' => $headers,
			'parametrs' => [
				'id' => $id,
				'massiveId' => $massiveId
			]
		]);

		if ($result->success == "false") {
			$message = '';
			switch ($result->error->message) {
				case 'Unauthorized':
					$message = 'Не авторизован';
					break;
				case 'Access error':
					$message = 'Ошибка доступа';
					break;
				case 'It is not delete to add users':
					$message = 'Невозможно удалить пользователей';
					break;
				default:
					$result->error->code = '500';
					$message = 'Возникла техническая неисправность';
					break;
			}
			return json_encode([
				"success" => "false",
				"code" => $result->error->code,
				"message" => $message
			]);
		}

		return json_encode([
			"success" => "true"
		]);
	}

	public function getMailings(Request $request)
	{
		$user = new Users();
		$curl = new Curl();

		if (empty($user->booleanAccessCheck())) return json_encode([
			"success" => "false",
			"code" => "403",
			"message" => "Ошибка доступа"
		]);

		$userId = $request->input('userId');
		$offset = $request->input('offset');
		$limit = $request->input('limit');
		$search = $request->input('search');

		$params = [];
		if (!empty($userId)) $params['userId'] = $userId;
		if (!empty($search)) $params['search'] = $search;
		if (!empty($offset)) $params['offset'] = $offset;
		if (!empty($limit)) $params['limit'] = $limit;

		$headers = [];
		$userApiKey = $user->getApiKey();
		$sessionToken = $user->getAuthToken();

		array_push($headers, 'X-Vsaas-Api-Key:' . $userApiKey);
		array_push($headers, "X-Vsaas-Session:" . $sessionToken);

		$url = http_build_query($params);

		$result = $curl->getCurl([
			"url" => Config::get('app.url') . '/api/v2/mailings?' . $url,
			'method' => 'GET',
			'headers' => $headers
		]);

		if ($result->success == "false") {
			$message = '';
			switch ($result->error->message) {
				case 'Access error':
					$message = 'Ошибка доступа';
					break;
				case 'Unauthorized':
					$message = 'Не авторизован';
					break;
				default:
					$result->error->code = '500';
					$message = 'Возникла техническая неисправность';
					break;
			}
			return json_encode([
				"success" => "false",
				"code" => $result->error->code,
				"message" => $message
			]);
		}

		return json_encode([
			"success" => "true",
			"result" => $result->result
		]);
	}

	public function getUsersMailing(Request $request)
	{
		$user = new Users();
		$curl = new Curl();

		if (empty($user->booleanAccessCheck()) || (!$user->isAdmin() && !$user->isPartner())) return json_encode([
			"success" => "false",
			"code" => "403",
			"message" => "Ошибка доступа"
		]);

		$params = [];
		$id = $request->input('id');
		$offset = $request->input('offset');
		$limit = $request->input('limit');
		$search = $request->input('search');

		if (!empty($id)) $params['id'] = $id;
		if (!empty($search)) $params['search'] = $search;
		if (!empty($offset)) $params['offset'] = $offset;
		if (!empty($limit)) $params['limit'] = $limit;

		$headers = [];
		$userApiKey = $user->getApiKey();
		$sessionToken = $user->getAuthToken();

		array_push($headers, 'X-Vsaas-Api-Key:' . $userApiKey);
		array_push($headers, "X-Vsaas-Session:" . $sessionToken);

		$url = http_build_query($params);

		$result = $curl->getCurl([
			"url" => Config::get('app.url') . '/api/v2/mailing/users?' . $url,
			'method' => 'GET',
			'headers' => $headers
		]);

		if ($result->success == "false") {
			$message = '';
			switch ($result->error->message) {
				case 'Access error':
					$message = 'Ошибка доступа';
					break;
				default:
					$result->error->code = '500';
					$message = 'Возникла техническая неисправность';
					break;
			}
			return json_encode([
				"success" => "false",
				"code" => $result->error->code,
				"message" => $message
			]);
		}

		return json_encode([
			"success" => "true",
			"result" => $result->result
		]);
	}

	public function userReadNotification(Request $request)
	{
		$mailing = new Mailings();
		$user = new Users();

		if (empty($user->booleanAccessCheck()) || (!$user->isAdmin() && !$user->isPartner() && !$user->isSupport() && !$user->isClient())) return json_encode([
			'success' => 'false',
			'code' => '403',
			'message' => 'Ошибка доступа'
		]);

		$mailingId = $request->input('id');

		if (!is_numeric($mailingId)) return json_encode([
			"success" => "false",
			"code" => "400",
			"message" => "Некорректный id уведомления"
		]);

		$result = $mailing->readNotificationMailing($mailingId, $user->getId());
		if (!$result) return json_encode([
			"success" => "false",
			"code" => "500",
			"message" => "Возникла техническая ошибка"
		]);

		try {
			$data = ['mailingId' => $mailingId, 'userId' => $user->getId()];
			event(new ReadNotification(json_encode($data)));
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

		return json_encode([
			"success" => "true"
		]);
	}
}
