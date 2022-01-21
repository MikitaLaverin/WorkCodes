<?php

namespace App\Http\Controllers\AjaxController\Directory;

use Illuminate\Support\Facades\Config;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Curl;
use App\Kernel\Dvor24\Directory\TelegramChanels;
use App\Kernel\Dvor24\Partner;
use App\Kernel\Dvor24\Users;
use Illuminate\Support\Facades\Session;

class TelegramController extends Controller
{
	public function addTelegramBot(Request $request)
	{
		$user = new Users();
		$curl = new Curl();

		if (empty($user->booleanAccessCheck()) || ($user->isAdmin() == false && $user->isPartner() == false && $user->isSupport() == false)) {
			return json_encode([
				"success" => "false",
				"code" => "403",
				"message" => "Ошибка доступа"
			]);
		}

		$title = $request->input('title');
		$token = $request->input('token');
		$chatId = $request->input('chatId');
		$partnerId = $request->input('partnerId');

		if ($user->isAdmin() && (!is_numeric($partnerId) || $partnerId == 0 || empty($partnerId))) return json_encode([
			"success" => "false",
			"code" => "400",
			"message" => "Некорректный партнер id"
		]);

		if (empty($title)) {
			return json_encode([
				"success" => "false",
				"code" => "400",
				"message" => "Введите название"
			]);
		}

		if (empty($token)) {
			return json_encode([
				"success" => "false",
				"code" => "400",
				"message" => "Введите токен"
			]);
		}

		if (!is_numeric($chatId) || empty($chatId)) {
			return json_encode([
				"success" => "false",
				"code" => "400",
				"message" => 'Некорректный "Ид чата"'
			]);
		}

		$headers = [];
		$userApiKey = $user->getApiKey();
		$sessionToken = $user->getAuthToken();

		array_push($headers, 'X-Vsaas-Api-Key: ' . $userApiKey);
		array_push($headers, "X-Vsaas-Session: " . $sessionToken);

		$result = $curl->getCurl([
			'url' => Config::get('app.url') . "/api/v2/directory/telegram-channels/add",
			'method' => 'POST',
			'headers' => $headers,
			'parametrs' => [
				'title' => $title,
				'token' => $token,
				'chatId' => $chatId,
				'partnerId' => $partnerId
			]
		]);

		if ($result->success == "false") {
			$message = '';
			switch ($result->error->message) {
				case "Couldn't find the telegram bot. Check that the data you entered is correct.":
					$message = "Не удалось найти телеграмv-бот. Проверьте правильность введенных данных.";
					break;
				case "Exceeded the total number of telegram channels.":
					$message = "Превышено общее количество телеграvм-ботов. Максимум 10.";
					break;
				default:
					$message = 'Возникла техническая неисправность';
					$result->error->code = '500';
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
			'result' => $result
		]);
	}

	public function manageTelegramBot($id, Request $request)
	{
		$user = new Users();
		$curl = new Curl();

		if (empty($user->booleanAccessCheck()) || ($user->isAdmin() == false && $user->isPartner() == false && $user->isSupport() == false)) {
			return json_encode([
				"success" => "false",
				"code" => "403",
				"message" => "Ошибка доступа"
			]);
		}

		$title = $request->input('title');
		$token = $request->input('token');
		$chatId = $request->input('chatId');
		$partnerId = $request->input('partnerId');

		if ($user->isAdmin() && (!is_numeric($partnerId) || $partnerId == 0 || empty($partnerId))) return json_encode([
			"success" => "false",
			"code" => "400",
			"message" => "Некорректный партнер id"
		]);

		if (!is_numeric($id) || empty($id)) return json_encode([
			"success" => "false",
			"code" => "400",
			"message" => "Некорректный id"
		]);

		if (empty($title)) {
			return json_encode([
				"success" => "false",
				"code" => "400",
				"message" => "Введите название"
			]);
		}

		if (empty($token)) {
			return json_encode([
				"success" => "false",
				"code" => "400",
				"message" => "Введите токен"
			]);
		}

		if (!is_numeric($chatId) || empty($chatId)) {
			return json_encode([
				"success" => "false",
				"code" => "400",
				"message" => 'Некорректный "Ид чата"'
			]);
		}

		$headers = [];
		$userApiKey = $user->getApiKey();
		$sessionToken = $user->getAuthToken();

		array_push($headers, 'X-Vsaas-Api-Key: ' . $userApiKey);
		array_push($headers, "X-Vsaas-Session: " . $sessionToken);

		$result = $curl->getCurl([
			'url' => Config::get('app.url') . "/api/v2/directory/telegram-channels/" . $id . "/manage",
			'method' => 'POST',
			'headers' => $headers,
			'parametrs' => [
				'title' => $title,
				'token' => $token,
				'chatId' => $chatId,
				'partnerId' => $partnerId
			]
		]);

		if ($result->success == "false") {
			return json_encode([
				"success" => "false",
				"code" => "500",
				"message" => "Возникла техническая ошибка"
			]);
		}

		return json_encode([
			"success" => "true",
		]);
	}

	public function deleteTelegramBot(Request $request)
	{
		$user = new Users();
		$curl = new Curl();
		$partner = new Partner();

		if (empty($user->booleanAccessCheck()) || !$user->isAdmin() && !$user->isPartner() && !$user->isSupport()) {
			return json_encode([
				"success" => "false",
				"code" => "403",
				"message" => "Ошибка доступа"
			]);
		}

		if ($user->getSID() == "partner" && $partner->issetSecretKey($user->getId())) {
			$secretKey = $request->input('secretKey');
			$partnerData = $partner->getPartnerData([
				'partnerId' => $user->getId()
			]);

			if ($partnerData->secret_key != $secretKey) {
				return json_encode([
					"success" => "false",
					"code" => "401",
					"message" => "Неверный секретный ключ"
				]);
			}
		}

		$telegramIds = http_build_query($request->input('telegramIds'));

		$headers = [];

		$userApiKey = $user->getApiKey();
		$sessionToken = $user->getAuthToken();

		array_push($headers, 'x-vsaas-api-key: ' . $userApiKey);
		array_push($headers, 'x-vsaas-session: ' . $sessionToken);

		$result = $curl->getCurl([
			"url" => Config::get('app.url') . "/api/v2/directory/telegram-channels/delete",
			'method' => 'POST',
			'headers' => $headers,
			'parametrs' => [
				'telegramIds' => $telegramIds
			]
		]);

		if ($result->success == "false") {
			$message = '';
			switch ($result->error->message) {
				case 'Access error':
					$message = 'Ошибка доступа';
					break;
				case 'Id is incorrect':
					$message = 'Некорректный id телеграм-бота';
					break;
				default:
					$message = 'Возникла техническая неисправность';
					$result->error->code = '500';
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

	public function getTelegramChannels(Request $request)
	{
		$user = new Users();
		$curl = new Curl();

		if (empty($user->booleanAccessCheck()) || !$user->isAdmin() && !$user->isPartner() && !$user->isSupport()) {
			return json_encode([
				"success" => "false",
				"code" => "403",
				"message" => "Ошибка доступа"
			]);
		}

		$search = $request->input('search');
		$offset = $request->input('offset');
		$limit = $request->input('limit');
		$telegramId = $request->input('id');

		$headers = [];

		$userApiKey = $user->getApiKey();
		$sessionToken = $user->getAuthToken();

		array_push($headers, 'x-vsaas-api-key: ' . $userApiKey);
		array_push($headers, 'x-vsaas-session: ' . $sessionToken);

		$urlParams = http_build_query([
			'search' => $search,
			'offset' => $offset,
			'limit' => $limit,
			'telegramId' => $telegramId
		]);

		$result = $curl->getCurl([
			"url" => Config::get('app.url') . "/api/v2/directory/telegram-channels?" . $urlParams,
			'method' => 'GET',
			'headers' => $headers,
		]);

		if ($result->success == "false") {
			return json_encode([
				"success" => "false",
				"code" => "500",
				"message" => "Возникла техническая неисправность"
			]);
		}

		return json_encode([
			"success" => "true",
			"result" => $result->result,
		]);
	}

	public function getTelegramChannel($id, Request $request)
	{
		$user = new Users();
		$curl = new Curl();

		if (empty($user->booleanAccessCheck()) || !$user->isAdmin() && !$user->isPartner() && !$user->isSupport()) {
			return json_encode([
				"success" => "false",
				"code" => "403",
				"message" => "Ошибка доступа"
			]);
		}

		if (!is_numeric($id)) return json_encode([
			"success" => "false",
			"code" => "400",
			"message" => "Некорректный id"
		]);

		$headers = [];

		$userApiKey = $user->getApiKey();
		$sessionToken = $user->getAuthToken();

		array_push($headers, 'x-vsaas-api-key: ' . $userApiKey);
		array_push($headers, 'x-vsaas-session: ' . $sessionToken);

		$result = $curl->getCurl([
			"url" => Config::get('app.url') . "/api/v2/directory/telegram-channel/" . $id,
			'method' => 'GET',
			'headers' => $headers,
		]);

		if ($result->success == "false") {
			return json_encode([
				"success" => "false",
				"code" => "500",
				"message" => "Возникла техническая неисправность"
			]);
		}

		return json_encode([
			'success' => 'true',
			'result' => $result
		]);
	}

	public function addRoleTelegram(Request $request)
	{
		$user = new Users();
		$curl = new Curl();

		if (empty($user->booleanAccessCheck()) || !$user->isAdmin() && !$user->isPartner() && !$user->isSupport()) {
			return json_encode([
				"success" => "false",
				"code" => "403",
				"message" => "Ошибка доступа"
			]);
		}

		$title = $request->input('title');
		$telegramId = $request->input('telegramId');
		$cameraMalfunctionsRegistration = $request->boolean('cameraMalfunctionsRegistration');
		$cameraMalfunctionsProcessing = $request->boolean('cameraMalfunctionsProcessing');
		$userRequestsRegistration = $request->boolean('userRequestsRegistration');
		$userRequestsProcessing = $request->boolean('userRequestsProcessing');
		$userActionsCameras = $request->boolean('userActionsCameras');
		$userActionsObjects = $request->boolean('userActionsObjects');
		$financialNotifications = $request->boolean('financialNotifications');
		$dailyStatistics = $request->boolean('dailyStatistics');
		$serverUnavailability = $request->boolean('serverUnavailability');
		$exceedingCPUThreshold = $request->boolean('exceedingCPUThreshold');
		$exceedingRAMThreshold = $request->boolean('exceedingRAMThreshold');
		$errorsMSService = $request->boolean('errorsMSService');
		$errorsMedia = $request->boolean('errorsMedia');

		$headers = [];
		$userApiKey = $user->getApiKey();
		$sessionToken = $user->getAuthToken();

		array_push($headers, 'x-vsaas-api-key: ' . $userApiKey);
		array_push($headers, "x-vsaas-session: " . $sessionToken);

		$result = $curl->getCurl([
			"url" => Config::get('app.url') . "/api/v2/directory/role-telegram/add",
			'method' => 'POST',
			'headers' => $headers,
			'parametrs' => [
				'title' => $title,
				'telegramId' => $telegramId,
				'cameraMalfunctionsRegistration' => $cameraMalfunctionsRegistration,
				'cameraMalfunctionsProcessing' => $cameraMalfunctionsProcessing,
				'userRequestsRegistration' => $userRequestsRegistration,
				'userRequestsProcessing' => $userRequestsProcessing,
				'userActionsObjects' => $userActionsObjects,
				'financialNotifications' => $financialNotifications,
				'dailyStatistics' => $dailyStatistics,
				'serverUnavailability' => $serverUnavailability,
				'exceedingCPUThreshold' => $exceedingCPUThreshold,
				'userActionsCameras' => $userActionsCameras,
				'exceedingRAMThreshold' => $exceedingRAMThreshold,
				'errorsMSService' => $errorsMSService,
				'errorsMedia' => $errorsMedia
			]
		]);

		if ($result->success == "false") {
			$message = '';
			switch ($result->error->message) {
				case 'Access error':
					$message = 'Ошибка доступа';
					break;
				case 'Unauthorized':
					$message = 'Ошибка доступа';
					break;
				case 'Title role is empty':
					$message = 'Укажите название роли';
					break;
				case 'Telegram bot not found':
					$message = 'Телеграм бот не найден';
					break;
				case 'Incorrect is telegram Id':
					$message = 'Некорректный индификатор телеграм бота';
					break;
				case 'Telegram bot is already linked to the role':
					$message = 'Телеграмм бот уже привязан к роле';
					break;
				case 'Id is incorrect':
					$message = 'Некорректный id телеграм-бота';
					break;
				default:
					$message = 'Возникла техническая неисправность';
					$result->error->code = '500';
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

	public function changeRoleTelegram(Request $request)
	{
		$user = new Users();
		$curl = new Curl();

		if (empty($user->booleanAccessCheck()) || !$user->isAdmin() && !$user->isPartner() && !$user->isSupport()) {
			return json_encode([
				"success" => "false",
				"code" => "403",
				"message" => "Ошибка доступа"
			]);
		}

		$roleId = $request->input('roleId');
		$title = $request->input('titleRole');
		$telegramId = $request->input('telegramId');
		$cameraMalfunctionsRegistration = $request->boolean('cameraMalfunctionsRegistration');
		$cameraMalfunctionsProcessing = $request->boolean('cameraMalfunctionsProcessing');
		$userRequestsRegistration = $request->boolean('userRequestsRegistration');
		$userRequestsProcessing = $request->boolean('userRequestsProcessing');
		$userActionsCameras = $request->boolean('userActionsCameras');
		$userActionsObjects = $request->boolean('userActionsObjects');
		$financialNotifications = $request->boolean('financialNotifications');
		$dailyStatistics = $request->boolean('dailyStatistics');
		$serverUnavailability = $request->boolean('serverUnavailability');
		$exceedingCPUThreshold = $request->boolean('exceedingCPUThreshold');
		$exceedingRAMThreshold = $request->boolean('exceedingRAMThreshold');
		$errorsMSService = $request->boolean('errorsMSService');
		$errorsMedia = $request->boolean('errorsMedia');

		$headers = [];
		$userApiKey = $user->getApiKey();
		$sessionToken = $user->getAuthToken();

		array_push($headers, 'X-Vsaas-Api-Key: ' . $userApiKey);
		array_push($headers, "X-Vsaas-Session: " . $sessionToken);

		$result = $curl->getCurl([
			'url' => Config::get('app.url') . "/api/v2/directory/role-telegram/change",
			'method' => 'POST',
			'headers' => $headers,
			'parametrs' => [
				'title' => $title,
				'telegramId' => $telegramId,
				'roleId' => $roleId,
				'cameraMalfunctionsRegistration' => $cameraMalfunctionsRegistration,
				'cameraMalfunctionsProcessing' => $cameraMalfunctionsProcessing,
				'userRequestsRegistration' => $userRequestsRegistration,
				'userRequestsProcessing' => $userRequestsProcessing,
				'userActionsObjects' => $userActionsObjects,
				'financialNotifications' => $financialNotifications,
				'dailyStatistics' => $dailyStatistics,
				'serverUnavailability' => $serverUnavailability,
				'exceedingCPUThreshold' => $exceedingCPUThreshold,
				'userActionsCameras' => $userActionsCameras,
				'exceedingRAMThreshold' => $exceedingRAMThreshold,
				'errorsMSService' => $errorsMSService,
				'errorsMedia' => $errorsMedia
			]
		]);

		if ($result->success == "false") {
			$message = '';
			switch ($result->error->message) {
				case 'Access error':
					$message = 'Ошибка доступа';
					break;
				case 'Unauthorized':
					$message = 'Ошибка доступа';
					break;
				case "Role not found":
					$message = "Роль не найдена";
					break;
				case "Id role incorrect or empty":
					$message = "Индификатор роли пустой или некорректный";
					break;
				case "Title role is empty":
					$message = "Укажите название роли";
					break;
				case "Select telegram":
					$message = "Выберете телеграм канал";
					break;
				default:
					$message = 'Возникла техническая неисправность';
					$result->error->code = '500';
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
			'result' => $result
		]);
	}

	public function deleteRoleTelegram(Request $request)
	{
		$user = new Users();
		$curl = new Curl();

		if (empty($user->booleanAccessCheck()) || !$user->isAdmin() && !$user->isPartner() && !$user->isSupport()) {
			return json_encode([
				"success" => "false",
				"code" => "403",
				"message" => "Ошибка доступа"
			]);
		}
		$massiveRoleIds = $request->input('roleIds');
		$roleIds = http_build_query($massiveRoleIds);

		$headers = [];
		$userApiKey = $user->getApiKey();
		$sessionToken = $user->getAuthToken();

		array_push($headers, 'X-Vsaas-Api-Key: ' . $userApiKey);
		array_push($headers, "X-Vsaas-Session: " . $sessionToken);

		$result = $curl->getCurl([
			"url" => Config::get('app.url') . '/api/v2/directory/role-telegram/delete',
			"method" => "POST",
			"headers" => $headers,
			"parametrs" => [
				"roleIds" => $roleIds
			]
		]);

		// заполнить ошибки
		if ($result->success == "false") {
			$message = '';
			switch ($result->error->message) {
				case 'Access error':
					$message = 'Ошибка доступа';
					break;
				case 'Unauthorized':
					$message = 'Ошибка доступа';
					break;
				case "Id`s is incorrect":
					$message = "Индификаторы ролей некорректны";
					break;
				case "Id`s is incorrect":
					$message = "Индификаторы ролей некорректны";
					break;
				default:
					$message = 'Возникла техническая неисправность';
					$result->error->code = '500';
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

	public function getRolesTelegram(Request $request)
	{
		$user = new Users();
		$curl = new Curl();

		if (empty($user->booleanAccessCheck()) || !$user->isAdmin() && !$user->isPartner() && !$user->isSupport()) {
			return json_encode([
				"success" => "false",
				"code" => "403",
				"message" => "Ошибка доступа"
			]);
		}

		$search = $request->input('search');
		$offset = $request->input('offset');
		$limit = $request->input('limit');

		$headers = [];

		$userApiKey = $user->getApiKey();
		$sessionToken = $user->getAuthToken();

		array_push($headers, 'x-vsaas-api-key: ' . $userApiKey);
		array_push($headers, 'x-vsaas-session: ' . $sessionToken);

		$urlParams = http_build_query([
			'search' => $search,
			'offset' => $offset,
			'limit' => $limit,
		]);

		$result = $curl->getCurl([
			"url" => Config::get('app.url') . "/api/v2/directory/roles-telegram?" . $urlParams,
			'method' => 'GET',
			'headers' => $headers,
		]);

		if ($result->success == "false") {
			return json_encode([
				"success" => "false",
				"code" => "500",
				"message" => "Возникла техническая неисправность"
			]);
		}
		if ($result->success == "false") {
			$message = '';
			switch ($result->error->message) {
				case 'Access error':
					$message = 'Ошибка доступа';
					break;
				case 'Unauthorized':
					$message = 'Ошибка доступа';
					break;
				default:
					$message = 'Возникла техническая неисправность';
					$result->error->code = '500';
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
			"result" => $result->result,
		]);
	}

	public function getRoleTelegram($id, Request $request)
	{
		$user = new Users();
		$curl = new Curl();

		if (empty($user->booleanAccessCheck()) || !$user->isAdmin() && !$user->isPartner() && !$user->isSupport()) {
			return json_encode([
				"success" => "false",
				"code" => "403",
				"message" => "Ошибка доступа"
			]);
		}

		if (!is_numeric($id)) return json_encode([
			"success" => "false",
			"code" => "400",
			"message" => "Некорректный id"
		]);

		$headers = [];

		$userApiKey = $user->getApiKey();
		$sessionToken = $user->getAuthToken();

		array_push($headers, 'x-vsaas-api-key: ' . $userApiKey);
		array_push($headers, 'x-vsaas-session: ' . $sessionToken);

		$result = $curl->getCurl([
			"url" => Config::get('app.url') . "/api/v2/directory/role-telegram/" . $id,
			'method' => 'GET',
			'headers' => $headers,
		]);
 
		if ($result->success == "false") {
			$message = '';
			switch ($result->error->message) {
				case 'Access error':
					$message = 'Ошибка доступа';
					break;
				case 'Unauthorized':
					$message = 'Ошибка доступа';
					break;
				case "Id role incorrect or empty":
					$message = "Индификаторы роль некорректен или пустой";
					break;
				default:
					$message = 'Возникла техническая неисправность';
					$result->error->code = '500';
					break;
			}
			return json_encode([
				"success" => "false",
				"code" => $result->error->code,
				"message" => $message
			]);
		}
		return json_encode([
			'success' => 'true',
			'result' => $result
		]);
	}
}
