<?php

namespace App\Kernel\Dvor24\Notification;

use App\Models\NotificationMailing;
use App\Models\NotificationNew;

class Notifications
{
	public function addNotification($data)
	{
		$result = NotificationMailing::insert($data);
	
		if ($result) return true;
		return false;
	}
	
	public function deleteNotification($id, $massive)
	{
		$result = new NotificationMailing();
		$result = $result->where('mailing_id', $id)
			->whereIn('user_id', $massive)
			->delete();
		
		if ($result) return true;
		return false;
	}

	public function updateNotificationStatus($type, $sid, $notificationId)
	{
		if ($type == 'mailings') {
			$result = NotificationMailing::where([
				['sid', $sid],
				['id', $notificationId]
			])
				->update([
					'status' => true,
					'updated_at' => date('Y-M-d H:i:s')
				]);
			if ($result) return true;
			return false;
		} elseif ($type == 'news') {
			$result = NotificationNew::where([
				['sid', $sid],
				['id', $notificationId]
			])
				->update([
					'status' => true,
					'updated_at' => date('Y-M-d H:i:s')
				]);
			if ($result) return true;
			return false;
		} else return false;
	}
}
