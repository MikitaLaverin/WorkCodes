<?php

namespace App\Kernel\Dvor24\Notification;

use App\Models\MailingTemplate;

class TemplateMailings
{
	/**
	 * Шаблоны рассылок
	 */

	public function addTemplates($data)
	{
		$result = MailingTemplate::create($data);
		if (!empty($result)) return true;
		return false;
	}

	public function changeTemplates($templateId, $data)
	{
		$result = MailingTemplate::where('id', $templateId)
			->update($data);

		if ($result) return true;
		return false;
	}

	public function deleteTemplates($userId, $templateIds)
	{
		$result = new MailingTemplate();
		$result = $result->where('user_id', $userId)
			->whereIn('id', $templateIds)
			->delete();
		if ($result) return true;
		return false;
	}

	public function getTemplates($data)
	{
		$result = new MailingTemplate();
		if (array_key_exists("partnerId", $data)) $result = $result->where('user_id', $data["partnerId"]);
		if (array_key_exists("limit", $data)) $result = $result->limit($data["limit"]);
		if (array_key_exists("offset", $data)) $result = $result->offset($data["offset"]);
		if (array_key_exists("search", $data)) $result = $result->where(function ($search) use ($data) {
			$search->where('previews', 'ilike', '%' . $data['search'] . '%')
				->orwhere('name_templates', 'ilike', '%' . $data['search'] . '%');
		});
		if (array_key_exists('count', $data)) return count($result->get());

		return $result->get();
	}

	public function isTemplatePartner($partnerId, $mailingId)
	{
		$result = MailingTemplate::where([
			['user_id', '=', $partnerId],
			['id', '=', $mailingId]
		])
			->limit(1)
			->get();

		if(count($result) == 1) return true;
		return false;
	}
}