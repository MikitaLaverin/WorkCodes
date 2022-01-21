<?php

namespace App\Http\Controllers\ApiController\Directory;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Exception;

use App\Kernel\Dvor24\Users;
use App\Kernel\Dvor24\Directory\TelegramChanels;
use App\Kernel\Dvor24\Partner;
use App\Kernel\Dvor24\Support;
use Illuminate\Support\Facades\DB;

class TelegramController extends Controller
{
	public function addTelegramBot(Request $request)
	{
		$user = new Users();
		$telegramChanels = new TelegramChanels();
		$support = new Support();

		$userApiKey = "";
		$sessionToken = "";
		$headers = $request->headers;

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

		if ($authData->sid != 'partner' && $authData->sid != 'admin' && $authData->sid != 'support') {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "403",
					"message" => "Access error"
				]
			]);
		}

		$title = $request->input('title');
		$token = $request->input('token');
		$chatId = $request->input('chatId');
		$partnerId = $request->input('partnerId');

		if ($authData->sid == 'admin') {
			if ($partnerId == 0 || !is_numeric($partnerId)) return json_encode([
				"success" => "false",
				"code" => "400",
				"message" => "Partner id is empty"
			]);
		} elseif ($authData->sid == "partner") {
			$partnerId = $authData->user_id;
		} else {
			$partnerId = $support->getPartnerId($partnerId);
		}
		$countTelegramChannel = $telegramChanels->getTelegramChannels(["partnerId" => $partnerId, "count" => true]);
		if ($countTelegramChannel > 10) return json_encode([
			"success" => "false",
			"error" => [
				"code" => "400",
				"message" => "Exceeded the total number of telegram channels."
			]
		]);

		$message = 'Проверка бота';
		try {
			$telegramChanels->sendMessageTelegramChanel([
				'token' => $token,
				'chatId' => $chatId,
				'message' => $message
			]);
		} catch (Exception $e) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "400",
					"message" => "Couldn't find the telegram bot. Check that the data you entered is correct."
				]
			]);
		}

		$result = $telegramChanels->addTelegramChannels([
			"user_id" => $partnerId,
			"title" => $title,
			"chat_id" => $chatId,
			"token" => $token
		]);

		if (!$result) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "500",
					"message" => "Internal server error"
				]
			]);
		}

		return json_encode([
			'success' => 'true',
		]);
	}

	public function manageTelegramBot($id, Request $request)
	{
		$user = new Users();
		$telegramChanels = new TelegramChanels();
		$support = new Support();

		$userApiKey = "";
		$sessionToken = "";
		$headers = $request->headers;

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

		if ($authData->sid != 'partner' && $authData->sid != 'admin' && $authData->sid != 'support') {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "403",
					"message" => "Access error"
				]
			]);
		}

		$title = $request->input('title');
		$token = $request->input('token');
		$chatId = $request->input('chatId');
		$partnerId = $request->input('partnerId');

		$params = [];

		if (!empty($title)) $params['title'] = $title;
		if (!empty($token)) $params['token'] = $token;
		if (!empty($chatId) && is_numeric($chatId)) $params['chat_id'] = $chatId;

		if ($authData->sid == 'admin') {
			if ($partnerId == 0 || !is_numeric($partnerId)) return json_encode([
				"success" => "false",
				"code" => "400",
				"message" => "Partner id is empty"
			]);
		} elseif ($authData->sid == "partner") {
			$partnerId = $authData->user_id;
		} else {
			$partnerId = $support->getPartnerId($partnerId);
		}

		$isLinkChannel = $telegramChanels->isLinkTelegramChannel($id, $partnerId);
		if (!$isLinkChannel) {
			return json_encode([
				'success' => 'false',
				'error' => [
					'code' => '401',
					'message' => 'Access error'
				]
			]);
		}

		$result = $telegramChanels->manageTelegramChannel($id, $params);

		if ($result == false) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "500",
					"message" => "Internal server error"
				]
			]);
		}

		return json_encode([
			"success" => "true",
		]);
	}

	public function deleteTelegramBot(Request $request)
	{
		$user = new Users();
		$telegramChanels = new TelegramChanels();
		$support = new Support();
		$partner = new Partner();

		$userApiKey = "";
		$sessionToken = "";
		$headers = $request->headers;

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

		if ($authData->sid != 'partner' && $authData->sid != 'admin' && $authData->sid != 'support') {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "403",
					"message" => "Access error"
				]
			]);
		}

		$telegramIds = $request->input('telegramIds');
		parse_str($telegramIds, $telegramIds);

		if (empty($telegramIds)) return json_encode([
			"success" => "false",
			"error" => [
				"code" => "400",
				"message" => "Id is incorrect"
			]
		]);

		foreach ($telegramIds as $key => $value) {
			if (!is_numeric($value)) return json_encode([
				"success" => "false",
				"error" => [
					"code" => "400",
					"message" => "Id is incorrect"
				]
			]);
		}

		$params = [];
		$params['telegramIds'] = $telegramIds;

		if ($authData->sid != 'admin') {
			$partnerId = $authData->user_id;

			if ($authData->sid == 'support') {
				$partnerId = $support->getPartnerId($partnerId);
			}

			$params['partnerId'] = $partnerId;
		}

		$result = $telegramChanels->deleteTelegramChannel($params);

		if (!$result) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "500",
					"message" => "Internal server error"
				]
			]);
		}

		return json_encode([
			'success' => 'true',
		]);
	}

	public function getTelegramChannels(Request $request)
	{
		$user = new Users();
		$telegramChanels = new TelegramChanels();
		$support = new Support();

		$userApiKey = "";
		$sessionToken = "";

		$headers = $request->headers;

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

		if ($authData->sid != 'partner' && $authData->sid != 'admin' && $authData->sid != 'support') {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "403",
					"message" => "Access error"
				]
			]);
		}

		$data = [];

		$search = $request->input('search');
		$offset = $request->input('offset');
		$limit = $request->input('limit');
		$telegramId = $request->input('telegramId');

		if (!empty($limit)) $data["limit"] = $limit;
		if (!empty($offset)) $data["offset"] = $offset;
		if (!empty($search)) $data["search"] = $search;
		if (is_numeric($telegramId)) $data["telegramId"] = $telegramId;

		if ($authData->sid == "partner") {
			$data['partnerId'] = $authData->user_id;
		} elseif ($authData->sid == "support") {
			$data['partnerId'] = $support->getPartnerId($authData->user_id);
		}

		$result = $telegramChanels->getTelegramChannels($data);

		return json_encode([
			"success" => "true",
			"result" => $result
		]);
	}

	public function getTelegramChannel($id, Request $request)
	{
		$user = new Users();
		$telegramChanels = new TelegramChanels();
		$support = new Support();

		$userApiKey = "";
		$sessionToken = "";

		$headers = $request->headers;

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

		if ($authData->sid != 'partner' && $authData->sid != 'admin' && $authData->sid != 'support') {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "403",
					"message" => "Access error"
				]
			]);
		}

		if (!is_numeric($id)) return json_encode([
			"success" => "false",
			"error" => [
				"code" => "400",
				"message" => "Incorrect is id"
			]
		]);

		if ($authData->sid != "admin") {
			if ($authData->sid == "partner") {
				$data['partnerId'] = $authData->user_id;
			} elseif ($authData->sid == "support") {
				$data['partnerId'] = $support->getPartnerId($authData->user_id);
			}

			$isLinkChannel = $telegramChanels->isLinkTelegramChannel($id, $authData->user_id);
			if (!$isLinkChannel) {
				return json_encode([
					'success' => 'false',
					'error' => [
						'code' => '403',
						'message' => 'Access error'
					]
				]);
			}
		}

		$result = $telegramChanels->getTelegramChannel($id);

		return json_encode([
			"success" => "true",
			"result" => $result
		]);
	}

	public function addRoleTelegram(Request $request)
	{
		$user = new Users();
		$telegramChanels = new TelegramChanels();
		$support = new Support();

		$userApiKey = "";
		$sessionToken = "";
		$headers = $request->headers;

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

		if ($authData->sid != 'partner' && $authData->sid != 'support') {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "403",
					"message" => "Access error"
				]
			]);
		}

		$params = [];
		$params['title'] = $request->input('title');

		if (empty($request->input('title'))) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "403",
					"message" => "Title role is empty"
				]
			]);
		}

		if ($authData->sid == "partner") {
			$params['user_id'] = $authData->user_id;
		} elseif ($authData->sid == "support") {
			$params['user_id'] = $support->getPartnerId($authData->user_id);
		}

		if ($request->has('cameraMalfunctionsRegistration')) $params['camera_malfunctions_registration'] = $request->boolean('cameraMalfunctionsRegistration');
		if ($request->has('cameraMalfunctionsProcessing')) $params['camera_malfunctions_processing'] = $request->boolean('cameraMalfunctionsProcessing');
		if ($request->has('userRequestsRegistration')) $params['user_requests_registration'] = $request->boolean('userRequestsRegistration');
		if ($request->has('userRequestsProcessing')) $params['user_requests_processing'] = $request->boolean('userRequestsProcessing');
		if ($request->has('userActionsCameras')) $params['user_actions_cameras'] = $request->boolean('userActionsCameras'); //(adding, deleting, changing)
		if ($request->has('userActionsObjects')) $params['user_actions_objects'] = $request->boolean('userActionsObjects'); //(adding, deleting, changing)
		if ($request->has('financialNotifications')) $params['financial_notifications'] = $request->boolean('financialNotifications');
		if ($request->has('dailyStatistics')) $params['daily_statistics'] = $request->boolean('dailyStatistics');
		if ($request->has('serverUnavailability')) $params['server_unavailability'] = $request->boolean('serverUnavailability');
		if ($request->has('exceedingCPUThreshold')) $params['exceeding_CPU_threshold'] = $request->boolean('exceedingCPUThreshold');
		if ($request->has('exceedingRAMThreshold')) $params['exceeding_RAM_threshold'] = $request->boolean('exceedingRAMThreshold');
		if ($request->has('errorsMSService')) $params['errors_MS_service'] = $request->boolean('errorsMSService');
		if ($request->has('errorsMedia')) $params['errors_media'] = $request->boolean('errorsMedia');

		DB::beginTransaction();
		$roleId = $telegramChanels->addRoleTelegram($params);

		if (empty($roleId) || !is_numeric($roleId)) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "400",
					"message" => "Internal server error"
				]
			]);
		}
		$data['role_id'] = $roleId;
		$data['telegram_id'] = $request->input('telegramId');
		if (!empty($data['telegram_id'])) {
			if (is_numeric($data['telegram_id'])) {
				$isLinkChannel = $telegramChanels->isLinkTelegramChannel($data['telegram_id'], $params['user_id']);
				if ($isLinkChannel) {
					$checkTelegram = $telegramChanels->getRoleIntermediateTelegram(["telegramId" => $data['telegram_id']]);
					if (count($checkTelegram) == 1) {
						DB::rollBack();
						return json_encode([
							"success" => "false",
							"error" => [
								"code" => "400",
								"message" => "Telegram bot is already linked to the role"
							]
						]);
					}
					$addIntermediateTable = $telegramChanels->addRoleIntermediateTelegram($data);
					if (!$addIntermediateTable) {
						DB::rollBack();
						return json_encode([
							"success" => "false",
							"error" => [
								"code" => "400",
								"message" => "Internal server error"
							]
						]);
					}
				} else {
					DB::rollBack();
					return json_encode([
						'success' => 'false',
						'error' => [
							'code' => '400',
							'message' => 'Access error'
						]
					]);
				}
			}
		}
		DB::commit();

		return json_encode([
			"success" => "true"
		]);
	}

	public function changeRoleTelegram(Request $request)
	{
		$user = new Users();
		$telegramChanels = new TelegramChanels();
		$support = new Support();

		$userApiKey = "";
		$sessionToken = "";
		$headers = $request->headers;

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

		if ($authData->sid != 'partner' && $authData->sid != 'admin' && $authData->sid != 'support') {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "403",
					"message" => "Access error"
				]
			]);
		}
		$roleId = $request->input('roleId');
		if (empty($roleId) && !is_numeric($roleId)) return json_encode([
			"success" => "false",
			"error" => [
				"code" => "403",
				"message" => "Id role incorrect or empty"
			]
		]);

		if ($authData->sid == "partner") {
			$partnerId = $authData->user_id;
		} elseif ($authData->sid == "support") {
			$partnerId = $support->getPartnerId($authData->user_id);
		}

		$checkRole = $telegramChanels->getRoleTelegram(["roleId" => $roleId]);

		if ($checkRole[0]->user_id != $partnerId) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "400",
					"message" => "Role not found"
				]
			]);
		}

		$params = [];
		if (!empty($request->input('title'))) $params['title'] = $request->input('title');
		if ($request->has('cameraMalfunctionsRegistration')) $params['camera_malfunctions_registration'] = $request->boolean('cameraMalfunctionsRegistration');
		if ($request->has('cameraMalfunctionsProcessing')) $params['camera_malfunctions_processing'] = $request->boolean('cameraMalfunctionsProcessing');
		if ($request->has('userRequestsRegistration')) $params['user_requests_registration'] = $request->boolean('userRequestsRegistration');
		if ($request->has('userRequestsProcessing')) $params['user_requests_processing'] = $request->boolean('userRequestsProcessing');
		if ($request->has('userActionsCameras')) $params['user_actions_cameras'] = $request->boolean('userActionsCameras'); //(adding, deleting, changing)
		if ($request->has('userActionsObjects')) $params['user_actions_objects'] = $request->boolean('userActionsObjects'); //(adding, deleting, changing)
		if ($request->has('financialNotifications')) $params['financial_notifications'] = $request->boolean('financialNotifications');
		if ($request->has('dailyStatistics')) $params['daily_statistics'] = $request->boolean('dailyStatistics');
		if ($request->has('serverUnavailability')) $params['server_unavailability'] = $request->boolean('serverUnavailability');
		if ($request->has('exceedingCPUThreshold')) $params['exceeding_CPU_threshold'] = $request->boolean('exceedingCPUThreshold');
		if ($request->has('exceedingRAMThreshold')) $params['exceeding_RAM_threshold'] = $request->boolean('exceedingRAMThreshold');
		if ($request->has('errorsMSService')) $params['errors_MS_service'] = $request->boolean('errorsMSService');
		if ($request->has('errorsMedia')) $params['errors_media'] = $request->boolean('errorsMedia');

		DB::beginTransaction();

		$result = $telegramChanels->changeRoleTelegram($roleId, $params);
		$data['telegram_id'] = $request->input('telegramId');

		if (empty($request->input('telegramId'))) {
			$deleteIntermediateTable = $telegramChanels->deleteRoleIntermediateTelegram(["roleId" => [$roleId]]);
			if (!$deleteIntermediateTable) {
				DB::rollBack();
				return json_encode([
					"success" => "false",
					"error" => [
						"code" => "500",
						"message" => "Internal server error"
					]
				]);
			}
		} elseif (!empty($data['telegram_id'])) {
			if (is_numeric($data['telegram_id'])) {
				$isLinkChannel = $telegramChanels->isLinkTelegramChannel($data['telegram_id'], $partnerId);
				if ($isLinkChannel) {
					$checkTelegram = $telegramChanels->getRoleIntermediateTelegram(["telegramId" => $data['telegram_id']]);
					if (count($checkTelegram) == 0) {
						$addIntermediateTable = $telegramChanels->addRoleIntermediateTelegram(["role_id" => $roleId, "telegram_id" => $data['telegram_id']]);
						if (!$addIntermediateTable) {
							DB::rollBack();
							return json_encode([
								"success" => "false",
								"error" => [
									"code" => "500",
									"message" => "Internal server error"
								]
							]);
						}
					} elseif (count($checkTelegram) == 1) {
						$changeIntermediateTable = $telegramChanels->changeRoleIntermediateTelegram($roleId, $data);
						if (!$changeIntermediateTable) {
							DB::rollBack();
							return json_encode([
								"success" => "false",
								"error" => [
									"code" => "500",
									"message" => "Internal server error"
								]
							]);
						}
					}
				} else {
					DB::rollBack();
					return json_encode([
						'success' => 'false',
						'error' => [
							'code' => '400',
							'message' => 'Access error'
						]
					]);
				}
			}
		}
		if ($result == false) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "500",
					"message" => "Internal server error"
				]
			]);
		}
		DB::commit();

		return json_encode([
			"success" => "true"
		]);
	}

	public function deleteRoleTelegram(Request $request)
	{
		$user = new Users();
		$role = new TelegramChanels();

		$userApiKey = "";
		$sessionToken = "";
		$headers = $request->headers;

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

		if ($authData->sid != 'partner' && $authData->sid != 'admin' && $authData->sid != 'support') {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "403",
					"message" => "Access error"
				]
			]);
		}

		$roleIds = $request->input('roleIds');
		parse_str($roleIds, $roleIds);

		foreach ($roleIds as $key => $value) {
			if (!is_numeric($value)) return json_encode([
				"success" => "false",
				"error" => [
					"code" => "400",
					"message" => "Id`s is incorrect"
				]
			]);
		}

		DB::beginTransaction();
		$deleteRole = $role->deleteRoleTelegram($roleIds);

		if (!$deleteRole) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "500",
					"message" => "Internal server error"
				]
			]);
		}
		DB::commit();
		return json_encode([
			"success" => "true"
		]);
	}

	public function getRolesTelegram(Request $request)
	{
		$user = new Users();
		$role = new TelegramChanels();
		$support = new Support();

		$userApiKey = "";
		$sessionToken = "";

		$headers = $request->headers;
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

		if ($authData->sid != 'partner' && $authData->sid != 'admin' && $authData->sid != 'support') {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "403",
					"message" => "Access error"
				]
			]);
		}

		$data = [];

		$search = $request->input('search');
		$offset = $request->input('offset');
		$limit = $request->input('limit');

		if (empty($limit) == false) $data["limit"] = $limit;
		if (empty($offset) == false) $data["offset"] = $offset;
		if (empty($search) == false) $data["search"] = $search;

		if ($authData->sid == "partner") {
			$data['userId'] = $authData->user_id;
		} elseif ($authData->sid == "support") {
			$data['userId'] = $support->getPartnerId($authData->user_id);
		}

		$result = $role->getRolesTelegram($data);

		unset($data["limit"]);
		unset($data["search"]);
		$data['count'] = true;

		$count = $role->getRolesTelegram($data);

		return json_encode([
			"success" => "true",
			"result" => [
				"result" => $result,
				"count" => $count
			]
		]);
	}

	public function getRoleTelegram($id, Request $request)
	{
		$user = new Users();
		$telegramChanels = new TelegramChanels();
		$support = new Support();

		$userApiKey = "";
		$sessionToken = "";

		$headers = $request->headers;

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

		if ($authData->sid != 'partner' && $authData->sid != 'support') {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "403",
					"message" => "Access error"
				]
			]);
		}

		if (!is_numeric($id)) return json_encode([
			"success" => "false",
			"error" => [
				"code" => "400",
				"message" => "Incorrect is id"
			]
		]);


		if (empty($id) && is_numeric($id)) {
			return json_encode([
				"success" => "false",
				"error" => [
					"code" => "403",
					"message" => "Id role incorrect or empty"
				]
			]);
		}
		$result = $telegramChanels->getRoleTelegram(["roleId" => $id, "intermediate" => true]);

		if (!$result) {
			return json_encode([
				'success' => 'false',
				'error' => [
					'code' => '403',
					'message' => 'Access error'
				]
			]);
		}
		return json_encode([
			"success" => "true",
			"result" => $result
		]);
	}
}
