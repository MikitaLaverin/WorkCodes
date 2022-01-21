<?php

namespace App\Http\Controllers\AjaxController\Object\OSS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Kernel\Dvor24\Partner;
use App\Kernel\Dvor24\Users;
use App\Kernel\Dvor24\Meetings;
use App\Kernel\Dvor24\Support;

class QuestionController extends Controller
{
	public function questionShow(Request $request)
	{
		$user = new Users();
		$meeting = new Meetings();

		if (empty($user->booleanAccessCheck()) || !$user->isPartner()) {
			return json_encode([
				"success" => "false",
				"code" => "403",
				"message" => "Ошибка доступа"
			]);
		}

		$search = $request->input('search');
		$offset = $request->input('offset');
		$limit = $request->input('limit');

		$params = [];
		$params['userId'] = $user->getId();

		if (!empty($search)) $params["search"] = $search;
		if (!empty($offset)) $params["offset"] = $offset;
		if (!empty($limit)) $params["limit"] = $limit;

		$result = $meeting->getQuestion($params);

		if (!empty($search)) unset($params["search"]);
		if (!empty($offset)) unset($params["offset"]);

		$params['count'] = true;
		$count = $meeting->getQuestion($params);

		return json_encode([
			"success" => "true",
			"result" => [
				'result' => $result,
				'count' => $count
			]
		]);
	}

	public function questionShowId($id, Request $request)
	{
		$user = new Users();
		$meeting = new Meetings();

		if (empty($user->booleanAccessCheck()) || !$user->isPartner()) {
			return json_encode([
				"success" => "false",
				"code" => "403",
				"message" => "Ошибка доступа"
			]);
		}

		if (empty($id) || !is_numeric($id)) return json_encode([
			"success" => "false",
			"code" => "400",
			"message" => "Некорректный id вопроса"
		]);

		$isAccess = $meeting->isAccessQuestion($id, $user->getId());
		if ($isAccess) {
			$result = $meeting->getQuestionId($id);
			return json_encode([
				"success" => "true",
				"result" => $result
			]);
		}

		return json_encode([
			"success" => "false",
			"code" => "403",
			"message" => "Ошибка доступа"
		]);
	}

	public function updateQuestion($id, Request $request)
	{
		$user = new Users();
		$meeting = new Meetings();

		if (empty($user->booleanAccessCheck()) || !$user->isPartner()) {
			return json_encode([
				"success" => "false",
				"code" => "403",
				"message" => "Ошибка доступа"
			]);
		}

		if (empty($id) || !is_numeric($id)) return json_encode([
			"success" => "false",
			"code" => "400",
			"message" => "Некорректный id вопроса"
		]);

		$text = $request->input('text');

		$isAccess = $meeting->isAccessQuestion($id, $user->getId());
		if ($isAccess) {
			if (empty($text)) return json_encode([
				"success" => "false",
				"code" => "400",
				"message" => "Заполните поле текст"
			]);

			$result = $meeting->updateQuestion($id, [
				"description" => $text
			]);
			if ($result) return json_encode([
				"success" => "true"
			]);
		}

		return json_encode([
			"success" => "false",
			"code" => "500",
			"message" => "Возникла техническая неисправность"
		]);
	}

	public function addQuestion(Request $request)
	{
		$user = new Users();
		$meeting = new Meetings();

		if (empty($user->booleanAccessCheck()) || !$user->isPartner()) {
			return json_encode([
				"success" => "false",
				"code" => "403",
				"message" => "Ошибка доступа"
			]);
		}

		$text = $request->input('text');

		if (empty($text)) return json_encode([
			"success" => "false",
			"code" => "400",
			"message" => "Заполните поле текст"
		]);

		$question = $meeting->addQuestion([
			"description" => $text,
			"user_id" => $user->getId(),
			"access" => 'admin'
		]);

		if (is_numeric($question)) return json_encode([
			"success" => "true",
		]);

		return json_encode([
			"success" => "false",
			"code" => "500",
			"message" => "Возникла техническая неисправность"
		]);
	}

	public function addQuestionId($id, Request $request)
	{
		$user = new Users();
		$meeting = new Meetings();

		if (empty($user->booleanAccessCheck()) || !$user->isPartner()) {
			return json_encode([
				"success" => "false",
				"code" => "403",
				"message" => "Ошибка доступа"
			]);
		}

		if (empty($id) || !is_numeric($id)) return json_encode([
			"success" => "false",
			"code" => "400",
			"message" => "Некорректный id вопроса"
		]);

		$text = $request->input('text');

		if (empty($text)) return json_encode([
			"success" => "false",
			"code" => "400",
			"message" => "Заполните поле текст"
		]);

		$lastQuestionsNum = $meeting->getQuestionMeeting([
			"end" => true,
			"userId" => $user->getId(),
			"meetingId" => $id
		]);

		$questionId = $meeting->addQuestion([
			"description" => $text,
			"user_id" => $user->getId(),
			"access" => "user",
			"sort_num" => $lastQuestionsNum->sort_num + 1
		]);

		$question_meeting = $meeting->addQuestionsMeeting($id, $questionId);

		if (is_numeric($questionId) && $question_meeting) return json_encode([
			"success" => "true",
		]);

		return json_encode([
			"success" => "false",
			"code" => "500",
			"message" => "Возникла техническая неисправность"
		]);
	}

	public function sortNumberQuestions(Request $request)
	{
		$user = new Users();
		$meeting = new Meetings();

		if (empty($user->booleanAccessCheck()) || !$user->isPartner()) {
			return json_encode([
				"success" => "false",
				"code" => "403",
				"message" => "Ошибка доступа"
			]);
		}

		$questions = $request->input('questions');

		foreach ($questions as $question) {
			$resultSortNumQuestions = $meeting->updateQuestion($question['id'], [
				"sort_num" => $question['sort_num'],
			]);
			if ($resultSortNumQuestions != true) return json_encode([
				"success" => "false",
				"code" => "500",
				"message" => "Возникла техническая неисправность"
			]);
		}

		return json_encode([
			"success" => "true",
		]);
	}

	public function getMeetingQuestion($id, Request $request)
	{
		$user = new Users();
		$meeting = new Meetings();

		if (empty($user->booleanAccessCheck()) || !$user->isPartner()) {
			return json_encode([
				"success" => "false",
				"code" => "403",
				"message" => "Ошибка доступа"
			]);
		}

		if (empty($id) || !is_numeric($id)) return json_encode([
			"success" => "false",
			"code" => "400",
			"message" => "Некорректный id собрания"
		]);

		$search = $request->input('search');
		$offset = $request->input('offset');
		$limit = $request->input('limit');

		$params = [];

		$params['userId'] = $user->getId();
		$params['meetingId'] = $id;

		if (!empty($search)) $params['search'] = $search;
		if (!empty($offset)) $params['offset'] = $offset;
		if (!empty($limit)) $params['limit'] = $limit;

		$isAccess = $meeting->isAccessMeeting($id, $user->getId());
		if ($isAccess) {
			$result = $meeting->getQuestionMeeting($params);
			return json_encode([
				"success" => "true",
				"result" => $result,
			], JSON_UNESCAPED_UNICODE);
		}

		return json_encode([
			"success" => "false",
			"code" => "403",
			"message" => "Ошибка доступа"
		]);
	}

	public function MeetingQuestionDeleteEverywhere($id)
	{
		$user = new Users();
		$meeting = new Meetings();

		if (empty($user->booleanAccessCheck()) || !$user->isPartner()) {
			return json_encode([
				"success" => "false",
				"code" => "403",
				"message" => "Ошибка доступа"
			]);
		}

		if (empty($id) || !is_numeric($id)) return json_encode([
			"success" => "false",
			"code" => "400",
			"message" => "Некорректный id вопроса"
		]);

		$result = $meeting->deleteQuestionMeetingEverywhere($id);

		if ($result) return json_encode([
			"success" => "true"
		]);

		return json_encode([
			"success" => "false",
			"code" => "500",
			"message" => "Возникла техническая неисправность"
		]);
	}

	public function massDeleteQuestionInMeeting(Request $request)
	{
		$user = new Users();
		$meeting = new Meetings();
		$partner = new Partner();
		$support = new Support();

		if (empty($user->booleanAccessCheck()) || !$user->isPartner()) {
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
					"code" => "400",
					"message" => "Неверный секретный ключ"
				]);
			}
		}

		$questionIds = $request->input('questionIds');

		if (empty($questionIds)) return json_encode([
			"success" => "false",
			"code" => "400",
			"message" => "Некорректный id"

		]);

		foreach ($questionIds as $key => $value) {
			if (!is_numeric($value)) return json_encode([
				"success" => "false",
				"code" => "400",
				"message" => "Некорректный id"
			]);
		}

		$params = [];
		$params['questionIds'] = $questionIds;

		if (!$user->isAdmin()) {
			$partnerId = $user->getId();

			if ($user->isSupport()) {
				$partnerId = $support->getPartnerId($partnerId);
			}

			$params['partnerId'] = $partnerId;
		}

		$result = $meeting->deleteQuestionMass($params);

		if (!$result) return json_encode([
			"success" => "false",
			"code" => "500",
			"message" => "Возникла техническая неисправность"
		]);

		return json_encode([
			'success' => 'true',
		]);
	}

	public function questionDeleteMeeting($id)
	{
		$user = new Users();
		$meeting = new Meetings();

		if (empty($user->booleanAccessCheck()) || !$user->isPartner()) {
			return json_encode([
				"success" => "false",
				"code" => "401",
				"message" => "Ошибка доступа"
			]);
		}

		if (empty($id) || !is_numeric($id)) return json_encode([
			"success" => "false",
			"code" => "400",
			"message" => "Некорректный id собрания"
		]);

		$result = $meeting->deleteQuestionMeeting($id);

		if ($result == false) return json_encode([
			"success" => "false",
			"code" => "500",
			"message" => "Возникла техническая ошибка"
		]);

		return json_encode([
			"success" => "true"
		]);
	}
}