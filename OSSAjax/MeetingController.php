<?php

namespace App\Http\Controllers\AjaxController\Object\OSS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Kernel\Dvor24\Partner;
use App\Kernel\Dvor24\Users;
use App\Kernel\Dvor24\Meetings;
use App\Kernel\Dvor24\Support;

class MeetingController extends Controller
{
	public function addMeeting(Request $request)
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

		$data = [
			'object' => $request->input('object'),
			'numberOfCameras' => $request->input('numberOfCameras'),
			'titleYK' => $request->input('titleYK'),
			'headYKIm' => $request->input('headYKIm'),
			'headYKRod' => $request->input('headYKRod'),
			'headYKDat' => $request->input('headYKDat'),
			'addressYK' => $request->input('addressYK'),
			'postCodeYK' => $request->input('postCodeYK'),
			'fioInitiator' => $request->input('fioInitiator'),
			'fioInitiatorRod' => $request->input('fioInitiatorRod'),
			'fioInitiatorDat' => $request->input('fioInitiatorDat'),
			'phoneInitiator' => $request->input('phoneInitiator'),
			'apartmentNumberInitiator' => $request->input('apartmentNumberInitiator'),
			'timeStart' => $request->input('timeStart'),
			'dateStart' => $request->input('dateStart'),
			'timeEnd' => $request->input('timeEnd'),
			'dateEnd' => $request->input('dateEnd'),
			'approach' => $request->input('approach'),
			'partnerId' => $user->getId(),
			'montlypayment' => $request->input('montlypayment'),
			'genderYK' => $request->input('gender')
		];

		foreach ($data as $key => $value) {
			if ($key != 'postCodeYK' || $key != 'genderYK') {
				if (empty($value)) return json_encode([
					"success" => "false",
					"code" => "400",
					"message" => "Заполните все поля"
				]);
			}
		}

		$result = $meeting->addMeeting([
			"object_id" => $data['object'],
			"date_start" => strtotime($data['dateStart']),
			"date_end" => strtotime($data['dateEnd']),
			"number_of_cameras" => $data['numberOfCameras'],
			"creation_date" => strtotime(gmdate('Y-m-d H:i:s')),
			"user_id" => $data['partnerId'],
			"title_yk" => $data['titleYK'],
			"head_yk" => $data['headYKIm'],
			"head_yk_rod" => $data['headYKRod'],
			"head_yk_dat" => $data['headYKDat'],
			"address_yk" => $data['addressYK'],
			"fio_initiator" => $data['fioInitiator'],
			"fio_initiator_rod" => $data['fioInitiatorRod'],
			"fio_initiator_dat" => $data['fioInitiatorDat'],
			"phone_initiator" => $data['phoneInitiator'],
			"apartment_number_initiator" => $data['apartmentNumberInitiator'],
			"time_start" => strtotime($data['timeStart']),
			"time_end" => strtotime($data['timeEnd']),
			"approach" => $data['approach'],
			"postal_code_yk" => $data['postCodeYK'],
			"montlypayment" => $data['montlypayment'],
			"gender_yk" => $data['genderYK']
		]);

		if ($result) return json_encode([
			"success" => "true",
			"result" => $result
		]);

		return json_encode([
			"success" => "false",
			"code" => "500",
			"message" => "Возникла техническая неиправность"
		]);
	}

	public function updateMeeting($meetingId, Request $request)
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

		$data = [
			'object' => $request->input('object'),
			'numberOfCameras' => $request->input('numberOfCameras'),
			'titleYK' => $request->input('titleYK'),
			'headYKIm' => $request->input('headYKIm'),
			'headYKRod' => $request->input('headYKRod'),
			'headYKDat' => $request->input('headYKDat'),
			'addressYK' => $request->input('addressYK'),
			'postCodeYK' => $request->input('postCodeYK'),
			'fioInitiator' => $request->input('fioInitiator'),
			'fioInitiatorRod' => $request->input('fioInitiatorRod'),
			'fioInitiatorDat' => $request->input('fioInitiatorDat'),
			'phoneInitiator' => $request->input('phoneInitiator'),
			'apartmentNumberInitiator' => $request->input('apartmentNumberInitiator'),
			'timeStart' => $request->input('timeStart'),
			'dateStart' => $request->input('dateStart'),
			'timeEnd' => $request->input('timeEnd'),
			'dateEnd' => $request->input('dateEnd'),
			'approach' => $request->input('approach'),
			'partnerId' => $user->getId(),
			'montlypayment' =>  $request->input('montlypayment')
		];

		foreach ($data as $key => $value) {
			if ($key != 'postCodeYK') {
				if (empty($value)) return json_encode([
					"success" => "false",
					"code" => "400",
					"message" => "Заполните все поля"
				]);
			}
		}

		$result = $meeting->updateMeeting($meetingId, [
			"date_start" => strtotime($data['dateStart']),
			"date_end" => strtotime($data['dateEnd']),
			"number_of_cameras" => $data['numberOfCameras'],
			"creation_date" => strtotime(gmdate('Y-m-d H:i:s')),
			"object_id" => $data['object'],
			"partner_id" => $data['partnerId'],
			"title_yk" => $data['titleYK'],
			"head_yk" => $data['headYKIm'],
			"head_yk_rod" => $data['headYKRod'],
			"head_yk_dat" => $data['headYKDat'],
			"address_yk" => $data['addressYK'],
			"fio_initiator" => $data['fioInitiator'],
			"fio_initiator_rod" => $data['fioInitiatorRod'],
			"fio_initiator_dat" => $data['fioInitiatorDat'],
			"phone_initiator" => $data['phoneInitiator'],
			"apartment_number_initiator" => $data['apartmentNumberInitiator'],
			"time_start" => strtotime($data['timeStart']),
			"time_end" => strtotime($data['timeEnd']),
			"approach" => $data['approach'],
			"montlypayment" => $data['montlypayment']
		]);

		if ($result) return json_encode([
			"success" => "true"
		]);

		return json_encode([
			"success" => "false",
			"code" => "500",
			"message" => "Возникла техническая неисправность"
		]);
	}

	public function meetingShow(Request $request)
	{
		$user = new Users();
		$meeting = new Meetings();

		if (empty($user->booleanAccessCheck()) || !$user->isPartner()) return json_encode([
			"success" => "false",
			"code" => "403",
			"message" => "Ошибка доступа"
		]);

		$limit = $request->input('limit');
		$offset = $request->input('offset');
		$search = $request->input('search');

		$params = [];
		$params['userId'] = $user->getId();

		if (!empty($limit)) $params['limit'] = $limit;
		if (!empty($offset)) $params['offset'] = $offset;
		if (!empty($search)) $params['search'] = $search;

		$result = $meeting->getMeeting($params);

		return json_encode([
			"success" => "true",
			"result" => $result
		]);
	}

	public function deleteMeetings(Request $request)
	{
		$user = new Users();
		$partner = new Partner();
		$support = new Support();
		$meeting = new Meetings();

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

		$meetingIds = $request->input('meetingIds');

		if (empty($meetingIds)) return json_encode([
			"success" => "false",
			"code" => "400",
			"message" => "Id is incorrect"
		]);

		foreach ($meetingIds as $key => $value) {
			if (!is_numeric($value)) return json_encode([
				"success" => "false",
				"code" => "400",
				"message" => "Id is incorrect"
			]);
		}

		$params = [];
		$params['meetingIds'] = $meetingIds;

		if ($user->getSID() != 'admin') {
			$partnerId = $user->getId();

			if ($user->getSID() == 'support') {
				$partnerId = $support->getPartnerId($partnerId);
			}

			$params['partnerId'] = $partnerId;
		}


		$result = $meeting->deleteMeeting($params);

		if (!$result) {
			return json_encode([
				"success" => "false",
				"code" => "500",
				"message" => "Возникла техническая неисправность"
			]);
		}

		return json_encode([
			'success' => 'true',
		]);
	}
}