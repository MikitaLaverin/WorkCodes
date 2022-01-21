<?php

namespace App\Kernel\Dvor24\Notification;

use App\Models\Mailing;
use App\Models\NotificationMailing;
use App\Models\NotificationMailingsClient;

class Mailings
{
	/**
	 * Базывые функции для рассылок
	 */

	public function addMailing($data)
	{
		$result = Mailing::create($data);
		if (!empty($result)) return $result->id;
		return false;
	}
	public function changeMailing($id, $data)
	{
		$result = new Mailing();
		$result = $result->where('id', $id)
			->update($data);
		if ($result) return true;
		return false;
	}
	public function masseChangeMailing($id, $data)
	{
		$result = new Mailing();
		$result = $result->whereIn('id', $id)
			->update($data);
		if ($result) return true;
		return false;
	}

	public function getMailing($id)
	{
		$result = new Mailing();
		$result = $result->where('mailings.id', '=', $id)
			->limit(1)
			->get();

		if (count($result) == 1) return $result[0];
		return [];
	}

	public function getStatusMailing($id)
	{
		$result = Mailing::select('notification_status_id')
			->where("id", $id)
			->limit(1)
			->get();

		if (count($result) == 1) return $result[0]->notification_status_id;
		return [];
	}

	public function getMailingsClient($data, $type = [])
	{
		$result = new NotificationMailingsClient();
		$result = $result->select('mailings.id', 'notification_mailing_clients.id as user_id', 'notification_mailing_clients.status as read', 'mailings.previews', 'mailings.message', 'mailings.date', 'mailings.author', 'mailings.type', 'mailings.count', 'mailings.notification_status_id');
		$result = $result->leftjoin('mailings', function ($join) {
			$join->on('notification_mailing_clients.mailing_id', '=', 'mailings.id');
		});

		$result = $result->where([
			['notification_mailing_clients.user_id', '=', $data['userId']],
			['mailings.notification_status_id', '=', '2']
		]);

		if (array_key_exists("limit", $data)) $result = $result->limit($data["limit"]);
		if (array_key_exists("offset", $data)) $result = $result->offset($data["offset"]);
		if (array_key_exists('count', $data)) return count($result->get());

		$result = $result->orderBy('mailings.id', 'desc')
			->get();

		return $result;
	}

	public function mailings($data)
	{
		$result = new Mailing();

		if (array_key_exists('system', $data)) $result = $result->selectRaw("mailings.id, mailings.previews, mailings.message, mailings.date, json_build_object('userIds', ARRAY(select notification_mailings.user_id from notification_mailings where notification_mailings.mailing_id = mailings.id)) as userIds");
		else $result = $result->select('mailings.*');
		if(array_key_exists('timeZone', $data)) {
			$result = $result->join('users', 'mailings.user_id', '=', 'users.id')
				->join('cities', 'cities.id', '=', 'users.city_id')
				->join('regions', 'cities.region_id', '=', 'regions.id')
				->join('time_zones', 'regions.time_zone_id', '=', 'time_zones.id')
				->addSelect('time_zones.offset_minutes');
		}
		if (array_key_exists('userIds', $data)) $result = $result->whereIn('mailings.user_id', $data['userIds']);
		if (array_key_exists('mailingIds', $data)) $result = $result->whereIn('mailings.id', $data['mailingIds']);
		if (array_key_exists('type', $data)) $result = $result->where('notification_status_id', $data['type']);
		if (array_key_exists("limit", $data)) $result = $result->limit($data["limit"]);
		if (array_key_exists("offset", $data)) $result = $result->offset($data["offset"]);
		if (array_key_exists('search', $data)) {
			$result = $result->where(function ($search) use ($data) {
				$search->where('message', 'ilike', '%' . $data['search'] . '%')
					->orWhere('previews', 'ilike', '%' . $data['search'] . '%');
			});
		}
		if (array_key_exists('count', $data)) return count($result->get());

		$result = $result->orderBy('mailings.id', 'desc')
			->get();

		return $result;
	}

	public function readNotificationMailing($mailingId, $userId)
	{
		$result = new NotificationMailing();
		$result = $result->where([
			['mailing_id', '=', $mailingId],
			['user_id', '=', $userId]
		])
			->update(['status' => true]);

		if ($result) return true;
		return false;
	}

	/**
	 * Функции для работы websockets
	 */

	public function getNotificationClient($id, $data = [])
	{
		$result = NotificationMailing::where('mailing_id', '=', $id);
		$result = $result->select('notification_mailings.id', 'notification_mailings.user_id', 'notification_mailings.status', 'notification_mailings.type');

		if (array_key_exists("info", $data)) {
			$result = $result->join('users', 'notification_mailings.user_id', '=', 'users.id');
			$result = $result->addSelect('users.login');
		}
		if (array_key_exists("limit", $data)) $result = $result->limit($data["limit"]);
		if (array_key_exists("offset", $data)) $result = $result->offset($data["offset"]);
		if (array_key_exists('search', $data)) {
			$result = $result->where(function ($search) use ($data) {
				$search->where('users.login', 'ilike', '%' . $data['search'] . '%');
			});
		}
		if (array_key_exists('count', $data)) return count($result->get());

		return $result->get();
	}

	public function getIdUserNotification($id)
	{
		$result = NotificationMailing::selectRaw("json_build_object('userId', ARRAY(select user_id from notification_mailings where mailing_id=$id)) as userIds")
			->limit(1)
			->get();

		if (count($result) == 1) return json_decode($result[0]->userids)->userId;
		return [];
	}

	public function checkMailingPartner($partnerId, $mailingId)
	{
		$result = Mailing::where([
			['user_id', '=', $partnerId],
			['id', '=', $mailingId]
		])
			->limit(1)
			->get();

		if (count($result) == 1) return true;
		return false;
	}
}
