<?php

namespace App\Http\Controllers\AjaxController\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Kernel\Dvor24\Notification\TemplateMailings;
use App\Kernel\Dvor24\Users;

class NotificationTemplateController extends Controller
{
	public function addTemplates(Request $request)
	{
		$user = new Users();
		$mailings = new TemplateMailings();

		if (empty($user->booleanAccessCheck()) || (!$user->isAdmin() && !$user->isPartner())) return json_encode([
			"success" => "false",
			"code" => "403",
			"message" => "Ошибка доступа"
		]);

		$userId = $user->getId();
		$nameTemplates = $request->input('nameTemplates');
		$previews = $request->input('previews');
		$message = $request->input('message');

		$result = $mailings->addTemplates([
			'user_id' => $userId,
			'name_templates' => $nameTemplates,
			'previews' => $previews,
			'message' => $message
		]);

		if (!$result) return json_encode([
			"success" => "false",
			"code" => "500",
			"message" => "Возникла техническая ошибка"
		]);

		return json_encode([
			"success" => "true"
		]);
	}

	public function changeTemplates(Request $request)
	{
		$user = new Users();
		$mailings = new TemplateMailings();

		if (empty($user->booleanAccessCheck()) || (!$user->isAdmin() && !$user->isPartner())) return json_encode([
			"success" => "false",
			"code" => "403",
			"message" => "Ошибка доступа"
		]);

		$templateId = $request->input('id');
		$nameTemplates = $request->input('nameTemplates');
		$previews = $request->input('previews');
		$message = $request->input('message');

		if (!$user->isAdmin() && !$mailings->isTemplatePartner($user->getId(), $templateId)) return json_encode([
			"success" => "false",
			"code" => "403",
			"message" => "Ошибка доступа"
		]);

		if (!is_numeric($templateId) || empty($templateId)) return json_encode([
			"success" => "false",
			"code" => "400",
			"message" => "Некорректный id шаблона"
		]);

		$params = [];
		if (!empty($nameTemplates)) $params['name_templates'] = $nameTemplates;
		if (!empty($previews)) $params['previews'] = $previews;
		if (!empty($message)) $params['message'] = $message;

		$result = $mailings->changeTemplates($templateId, $params);

		if (!$result) return json_encode([
			"success" => "false",
			"code" => "500",
			"message" => "Возникла техническая ошибка"
		]);

		return json_encode([
			"success" => "true"
		]);
	}

	public function deleteTemplates(Request $request)
	{
		$user = new Users();
		$mailings = new TemplateMailings();

		if (empty($user->booleanAccessCheck()) || (!$user->isAdmin() && !$user->isPartner())) return json_encode([
			"success" => "false",
			"code" => "403",
			"message" => "Ошибка доступа"
		]);

		$templateIds = $request->input('templateIds');

		if (!is_array($templateIds) || empty($templateIds)) return json_encode([
			"success" => "false",
			"code" => "400",
			"message" => "Некорректный id шаблона"
		]);

		$result = $mailings->deleteTemplates($user->getId(), $templateIds);

		if (!$result) return json_encode([
			"success" => "false",
			"code" => "500",
			"message" => "Возникла техническая ошибка"
		]);

		return json_encode([
			"success" => "true"
		]);
	}

	public function getTemplates(Request $request)
	{
		$user = new Users();
		$mailing = new TemplateMailings();

		if (empty($user->booleanAccessCheck()) || (!$user->isAdmin() && !$user->isPartner())) return json_encode([
			"success" => "false",
			"code" => "403",
			"message" => "Ошибка доступа"
		]);

		$search = $request->input('search');
		$offset = $request->input('offset');
		$limit = $request->input('limit');
		
		$params = [];
		if(!$user->isAdmin()) $params['partnerId'] = $user->getId();
		elseif($request->has('partnerId')) $params['partnerId'] = $request->input('partnerId');

		if(!empty($search)) $params['search'] = $search;
		if(!empty($offset)) $params['offset'] = $offset;
		if(!empty($limit)) $params['limit'] = $limit;
		
		$result = $mailing->getTemplates($params);

		unset($params["offset"]);
		unset($params["limit"]);
		$params["count"] = true;
		$count = $mailing->getTemplates($params);

		return json_encode([
			"success" => "true",
			"result" => [
				"result" => $result,
				"count" => $count
			]
		]);
	}
}
