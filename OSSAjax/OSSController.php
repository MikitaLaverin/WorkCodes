<?php

namespace App\Http\Controllers\AjaxController\Object\OSS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Curl;

use App\Kernel\Dvor24\Partner;
use App\Kernel\Dvor24\Users;
use App\Kernel\Ddata;
use App\Kernel\Dvor24\Meetings;
use App\Kernel\Number2String;
use App\Kernel\Dvor24\Support;
use App\Kernel\Dvor24\Facilities;

class OSSController extends Controller
{
	public function autoFillFormQuestions($id, Request $request)
	{
		$user = new Users();
		$number2String = new Number2String();
		$partner = new Partner();
		$facility = new Facilities();
		$meeting = new Meetings();
		$question = new QuestionController();

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

		$isAccess = $meeting->isAccessMeeting($id, $user->getId());

		if (!$isAccess) return json_encode([
			"success" => "false",
			"code" => "403",
			"message" => "Ошибка доступа"
		]);

		$resultMeeting = $meeting->getMeetingId($id);

		if (empty($resultMeeting)) return json_encode([
			"success" => "false",
			"code" => "500",
			"message" => "Возникла техническая неисправность"
		]);

		$delete = $question->questionDeleteMeeting($id);

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
			'approach' => $request->input('approach')
		];


		$partnerTitle = $partner->getCompanyData($user->getId());
		$montlyPaymentString = $number2String->number2string($resultMeeting->montlypayment);
		$facilityResult = $facility->get(['number' => $resultMeeting->number]);
		$regionTitle = explode(" ", $facilityResult->region_title);
		$towerInitiator = $facilityResult->cities_title;
		$massAddress = explode(",", $facilityResult->address);
		$house = $massAddress[1];
		$street = $massAddress[0];
		$towerAndStreet = $towerInitiator . " " . $massAddress[0];

		$resRegionTitle = $meeting->prepositionalSingular($regionTitle[0]);
		$resPrintRegionTitle = $meeting->mb_ucfirst(mb_strtolower($resRegionTitle . ' области', 'utf8'));

		$whatToReplaceItWith = [
			$data['fioInitiatorDat'],
			$data['titleYK'],
			$data['headYKDat'],
			$data['addressYK'],
			$data['fioInitiatorRod'],
			$resultMeeting->address,
			$data['apartmentNumberInitiator'],
			$data['phoneInitiator'],
			$data['fioInitiator'],
			date('d.m.Y', strtotime(gmdate("M-d-Y H:i:s"))),
			date('d.m.Y', strtotime($data['dateStart'])),
			date('d.m.Y', strtotime($data['dateEnd'])),
			$partnerTitle->title,
			$data['numberOfCameras'],
			$resultMeeting->montlypayment,
			$montlyPaymentString,
			$data['timeStart'],
			$data['timeEnd'],
			$data['approach'],
			$house,
			$street,
			$resPrintRegionTitle,
			$towerInitiator,
			$towerAndStreet,
			$meeting->dateStyleString($data['dateStart'])[0],
			$meeting->dateStyleString($data['dateStart'])[1],
			$meeting->dateStyleString($data['dateStart'])[2],
			$meeting->dateStyleString($data['dateEnd'])[0],
			$meeting->dateStyleString($data['dateEnd'])[1],
			$meeting->dateStyleString($data['dateEnd'])[2],
			$meeting->dateStyleString(gmdate("M-d-Y H:i:s"))[0],
			$meeting->dateStyleString(gmdate("M-d-Y H:i:s"))[1],
			$meeting->dateStyleString(gmdate("M-d-Y H:i:s"))[2],
			$data['postCodeYK']
		];

		$questions = $meeting->getQuestion(['userId' => $user->getId()]);

		$counterQuestions = 1;
		foreach ($questions as $quest) {
			if ($quest->access == 'admin') {
				$descriptionDefault = $quest->description;
				$newDescriptionDefault = str_replace($meeting->getLibraryTag(), $whatToReplaceItWith, $descriptionDefault);
				$questionId = $meeting->addQuestion([
					"description" => $newDescriptionDefault,
					"user_id" => $user->getId(),
					"access" => "user",
					"sort_num" => $counterQuestions,
				]);
				$counterQuestions++;
				$question_meeting = $meeting->addQuestionsMeeting($id, $questionId);
			}
		}

		if (is_numeric($questionId) && $question_meeting) {
			return json_encode([
				"success" => "true"
			]);
		}

		return json_encode([
			"success" => "false",
			"code" => "500",
			"message" => "Возникла техническая неисправность"
		]);
	}

	public function infoPartnerHead()
	{
		$user = new Users();
		$partner = new Partner();
		$ddata = new Ddata();

		if (empty($user->booleanAccessCheck()) || !$user->isPartner()) {
			return json_encode([
				"success" => "false",
				"code" => "403",
				"message" => "Ошибка доступа"
			]);
		}

		$partnerCompanyData = $partner->getCompanyData($user->getId());

		if ($partnerCompanyData == false) {
			return json_encode([
				"success" => "false",
				"code" => "500",
				"message" => "Возникла техническая неисправность"
			]);
		}

		$inn = $partnerCompanyData->inn;
		$data_inn = $ddata->getCompanyData($inn);
		if (empty($data_inn)) {
			return json_encode([
				"success" => "false",
				"code" => "404",
				"message" => "По вашему ИНН данных не найдено"
			]);
		}

		return json_encode([
			"success" => "true",
			"result" => $data_inn
		]);
	}

	public function infoYKHead(Request $request)
	{
		$user = new Users();
		$ddata = new Ddata();
		$curl = new Curl();

		if (empty($user->booleanAccessCheck()) || !$user->isPartner()) {
			return json_encode([
				"success" => "false",
				"code" => "403",
				"message" => "Ошибка доступа"
			]);
		}

		$inn = $request->input('inn');

		if (empty($inn)) return json_encode([
			"success" => "false",
			"code" => "400",
			"message" => "Поле ИНН в объекте не заполнено"
		]);

		$data_inn = $ddata->getCompanyData($inn);

		if (empty($data_inn)) {
			return json_encode([
				"success" => "false",
				"code" => "404",
				"message" => "Данных по данному ИНН не найдено"
			]);
		}

		return json_encode([
			"success" => "true",
			"result" => $data_inn
		]);
	}

	//switch case  from completion document
	public function meetingDocumentPrint($printDocumentMeeting, Request $request)
	{
		$user = new Users();
		$meeting = new Meetings();
		$number2String = new Number2String();
		$partner = new Partner();
		$facility = new Facilities();

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
			'userId' => $user->getId(),
			'meetingId' => $request->input('meetingId')
		];

		$meetingId = $request->input('meetingId');
		$dataObject = (array)$request->input('contentInfoDirectorName');
		$utc = $request->input('UTC');

		date_default_timezone_set("UTC");
		$time = time();
		$time += $utc * 3600;

		$partnerTitle = $partner->getCompanyData($user->getId());
		$resultMeeting = $meeting->getMeetingId($data['meetingId']);
		$data['number'] = $resultMeeting->title;
		$facilityResult = $facility->get(['id' => $data['object']]);
		$regionTitle = explode(" ", $facilityResult->region_title);
		$towerInitiator = $facilityResult->cities_title;
		$massAddress = explode(",", $facilityResult->address);
		$house = $massAddress[1];
		$street = $massAddress[0];
		$towerAndStreet = $towerInitiator . " " . $massAddress[0];
		$genderYK = $resultMeeting->gender_yk;
		$endingFloor = 'ый (-ая)';
		if (!empty($genderYK)) {
			if ($genderYK == 'male') $endingFloor = 'ый';
			else if ($genderYK == 'female') $endingFloor = 'ая';
		}

		$data['userCount'] = true;
		if ($meeting->getQuestionMeeting($data) != 0) {
			unset($data['userCount']);
			$resRegionTitle = $meeting->prepositionalSingular($regionTitle[0]);
			$resPrintRegionTitle = $meeting->mb_ucfirst(mb_strtolower($resRegionTitle . ' области', 'utf8'));

			switch ($printDocumentMeeting) {
				case "zajavlenieNaPolychenieReestraYK":
					$pathToFile = storage_path() . '/doc/' . $printDocumentMeeting . '.docx';
					$pathToFileName = $resultMeeting->address . "_" . "01" . "_" . "Заявление на получение реестра в УК" . "_" . date('Y_m_d_H_i', $time); //strtotime(gmdate("M-d-Y H:i:s")));
					$path = storage_path() . '/tmp/doc/' . $pathToFileName;
					$templateProcessor = new TemplateProcessor($pathToFile);
					$templateProcessor->setValue('nameCompanyPrint', $data['titleYK']);
					$templateProcessor->setValue('nameDirectorPrint', $data['headYKDat']);
					$templateProcessor->setValue('addressCompanyPrint', $data['addressYK']);
					$templateProcessor->setValue('fioInitiatorRod', $data['fioInitiatorRod']);
					$templateProcessor->setValue('addressInitiator', $resultMeeting->address);
					$templateProcessor->setValue('apartmentNumberInitiator', $data['apartmentNumberInitiator']);
					$templateProcessor->setValue('phoneInitiator', $data['phoneInitiator']);
					$templateProcessor->setValue('fioInitiatorIm', $data['fioInitiator']);
					$templateProcessor->setValue('creationDate', date('d.m.Y', strtotime(gmdate("M-d-Y H:i:s"))));
					break;
				case "notificationOfTheCriminalCodeAboutTheMeeting":
					$pathToFile = storage_path() . '/doc/' . $printDocumentMeeting . '.docx';
					$pathToFileName = $resultMeeting->address . "_" . "02" . "_" . "Уведомление УК о проведении собрания" . "_" . date('Y_m_d_H_i', $time);
					$path = storage_path() . '/tmp/doc/' . $pathToFileName;
					$templateProcessor = new TemplateProcessor($pathToFile);
					$templateProcessor->setValue('nameCompanyPrint', $data['titleYK']);
					$templateProcessor->setValue('nameDirectorPrint', $data['headYKDat']);
					$templateProcessor->setValue('addressCompanyPrint', $data['addressYK']);
					$templateProcessor->setValue('fioInitiatorRod', $data['fioInitiatorRod']);
					$templateProcessor->setValue('addressInitiator', $resultMeeting->address);
					$templateProcessor->setValue('apartmentNumberInitiator', $data['apartmentNumberInitiator']);
					$templateProcessor->setValue('phoneInitiator', $data['phoneInitiator']);
					$templateProcessor->setValue('fioInitiatorIm', $data['fioInitiator']);
					$templateProcessor->setValue('creationDate', date('d.m.Y', strtotime(gmdate("M-d-Y H:i:s"))));
					$templateProcessor->setValue('dateStart', date('d.m.Y', strtotime($data['dateStart'])));
					$templateProcessor->setValue('dateEnd', date('d.m.Y', strtotime($data['dateEnd'])));
					$templateProcessor->setValue('partnerTitle', $partnerTitle->title);
					$templateProcessor->setValue('numberOfCameras', $data['numberOfCameras']);
					$templateProcessor->setValue('montlyPayment', $resultMeeting->montlypayment);
					$montlyPaymentString = $number2String->number2string($resultMeeting->montlypayment);
					$templateProcessor->setValue('montlyPaymentString', $montlyPaymentString);
					//Question
					$questions = $meeting->getQuestionMeeting($data)['result'];
					$templateProcessor->cloneBlock('block_question', $questions->count(), true, true);
					$i = 1;
					foreach ($questions as $quest) {
						$templateProcessor->setValue('${number#' . $i . '}', $i);
						$templateProcessor->setValue('${question#' . $i . '}', $quest->description);
						$i++;
					}
					//QuestionEnd
					break;
				case "notificationOfTheOwnersOfTheMeeting":
					$pathToFile = storage_path() . '/doc/' . $printDocumentMeeting . '.docx';
					$pathToFileName = $resultMeeting->address . "_" . "03" . "_" . "Уведомление собственников о проведении собрания" . "_" . date('Y_m_d_H_i', $time);
					$path = storage_path() . '/tmp/doc/' . $pathToFileName;
					$templateProcessor = new TemplateProcessor($pathToFile);
					$templateProcessor->setValue('fioInitiatorRod', $data['fioInitiatorRod']);
					$templateProcessor->setValue('fioInitiatorIm', $data['fioInitiator']);
					$templateProcessor->setValue('addressInitiator', $resultMeeting->address);
					$templateProcessor->setValue('apartmentNumberInitiator', $data['apartmentNumberInitiator']);
					$templateProcessor->setValue('phoneInitiator', $data['phoneInitiator']);
					$templateProcessor->setValue('creationDate', date('d.m.Y', strtotime(gmdate("M-d-Y H:i:s"))));
					$templateProcessor->setValue('dateStart', date('d.m.Y', strtotime($data['dateStart'])));
					$templateProcessor->setValue('dateEnd', date('d.m.Y', strtotime($data['dateEnd'])));
					$templateProcessor->setValue('partnerTitle', $partnerTitle->title);
					$templateProcessor->setValue('numberOfCameras', $data['numberOfCameras']);
					$templateProcessor->setValue('montlyPayment', $resultMeeting->montlypayment);
					$montlyPaymentString = $number2String->number2string($resultMeeting->montlypayment);
					$templateProcessor->setValue('montlyPaymentString', $montlyPaymentString);
					$templateProcessor->setValue('timeStart', $data['timeStart']);
					$templateProcessor->setValue('timeEnd', $data['timeEnd']);
					$templateProcessor->setValue('approach', $data['approach']);
					$templateProcessor->setValue('numberHouse', str_replace('д', '', $house));
					$templateProcessor->setValue('street', $street);
					$templateProcessor->setValue('regionTitle', $resPrintRegionTitle);
					$templateProcessor->setValue('towerInitiator', $towerInitiator);
					//Question
					$questions = $meeting->getQuestionMeeting($data)['result'];
					$templateProcessor->cloneBlock('block_question', $questions->count(), true, true);
					$i = 1;
					foreach ($questions as $quest) {
						$templateProcessor->setValue('${number#' . $i . '}', $i);
						$templateProcessor->setValue('${question#' . $i . '}', $quest->description);
						$i++;
					}
					//QuestionEnd
					break;
				case "actOnThePlacementOfTheOwnersNotification":
					$pathToFile = storage_path() . '/doc/' . $printDocumentMeeting . '.docx';
					$pathToFileName = $resultMeeting->address . "_" . "04" . "_" . "Акт о размещении уведомления собственников" . "_" . date('Y_m_d_H_i', $time);
					$path = storage_path() . '/tmp/doc/' . $pathToFileName;
					$templateProcessor = new TemplateProcessor($pathToFile);
					$templateProcessor->setValue('fioInitiatorIm', $data['fioInitiator']);
					$templateProcessor->setValue('addressInitiator', $resultMeeting->address);
					$templateProcessor->setValue('apartmentNumberInitiator', $data['apartmentNumberInitiator']);
					$templateProcessor->setValue('dateStart', date('d.m.Y', strtotime($data['dateStart'])));
					$templateProcessor->setValue('dateEnd', date('d.m.Y', strtotime($data['dateEnd'])));
					$templateProcessor->setValue('timeEnd', $data['timeEnd']);
					$templateProcessor->setValue('approach', $data['approach']);
					$templateProcessor->setValue('numberHouse', str_replace('д', '', $house));
					$templateProcessor->setValue('towerInitiator', $towerInitiator);
					$templateProcessor->setValue('towerAndStreet', $towerAndStreet);
					break;
				case "actOnThePlacementOfTheVotingBox":
					$pathToFile = storage_path() . '/doc/' . $printDocumentMeeting . '.docx';
					$pathToFileName = $resultMeeting->address . "_" . "05" . "_" . "Акт о размещении ящика для голосования" . "_" . date('Y_m_d_H_i', $time);
					$path = storage_path() . '/tmp/doc/' . $pathToFileName;
					$templateProcessor = new TemplateProcessor($pathToFile);
					$templateProcessor->setValue('fioInitiatorIm', $data['fioInitiator']);
					$templateProcessor->setValue('addressInitiator', $resultMeeting->address);
					$templateProcessor->setValue('apartmentNumberInitiator', $data['apartmentNumberInitiator']);
					$templateProcessor->setValue('dateStart', date('d.m.Y', strtotime($data['dateStart'])));
					$templateProcessor->setValue('dateEnd', date('d.m.Y', strtotime($data['dateEnd'])));
					$templateProcessor->setValue('timeEnd', $data['timeEnd']);
					$templateProcessor->setValue('approach', $data['approach']);
					$templateProcessor->setValue('numberHouse', str_replace('д', '', $house));
					$templateProcessor->setValue('towerInitiator', $towerInitiator);
					$templateProcessor->setValue('towerAndStreet', $towerAndStreet);
					break;
				case "ownerDecisionFormOnOSSIssues":
					$pathToFile = storage_path() . '/doc/' . $printDocumentMeeting . '.docx';
					$pathToFileName = $resultMeeting->address . "_" . "06" . "_" . "Бланк решения собственника по вопросам ОСС" . "_" . date('Y_m_d_H_i', $time);
					$path = storage_path() . '/tmp/doc/' . $pathToFileName;
					$templateProcessor = new TemplateProcessor($pathToFile);
					$templateProcessor->setValue('fioInitiatorRod', $data['fioInitiatorRod']);
					$templateProcessor->setValue('addressInitiator', $resultMeeting->address);
					$templateProcessor->setValue('apartmentNumberInitiator', $data['apartmentNumberInitiator']);
					$templateProcessor->setValue('numberOfCameras', $data['numberOfCameras']);
					$templateProcessor->setValue('partnerTitle', $partnerTitle->title);
					$templateProcessor->setValue('montlyPayment', $resultMeeting->montlypayment);
					$montlyPaymentString = $number2String->number2string($resultMeeting->montlypayment);
					$templateProcessor->setValue('montlyPaymentString', $montlyPaymentString);
					$templateProcessor->setValue('timeEnd', $data['timeEnd']);
					$templateProcessor->setValue('dateStartDay', $meeting->dateStyleString($data['dateStart'])[0]);
					$templateProcessor->setValue('dateStartMonth', $meeting->dateStyleString($data['dateStart'])[1]);
					$templateProcessor->setValue('dateStartYear', $meeting->dateStyleString($data['dateStart'])[2]);
					$templateProcessor->setValue('dateEndDay', $meeting->dateStyleString($data['dateEnd'])[0]);
					$templateProcessor->setValue('dateEndMonth', $meeting->dateStyleString($data['dateEnd'])[1]);
					$templateProcessor->setValue('dateEndYear', $meeting->dateStyleString($data['dateEnd'])[2]);
					//Question
					$questions = $meeting->getQuestionMeeting($data)['result'];
					$templateProcessor->cloneRow('number', $questions->count());
					$i = 1;
					foreach ($questions as $quest) {
						$templateProcessor->setValue('${number#' . $i . '}', $i);
						$templateProcessor->setValue('${question#' . $i . '}', $quest->description);
						$i++;
					}
					//QuestionEnd
					break;
				case "listOfRegistrationOfThosePresentAtTheFull-timePartOfTheOSS":
					$pathToFile = storage_path() . '/doc/' . $printDocumentMeeting . '.docx';
					$pathToFileName = $resultMeeting->address . "_" . "07" . "_" . "Лист регистрации присутствующих на очной части ОСС" . "_" . date('Y_m_d_H_i', $time);
					$path = storage_path() . '/tmp/doc/' . $pathToFileName;
					$templateProcessor = new TemplateProcessor($pathToFile);
					$ret = explode(" ", $house);
					$templateProcessor->setValue('numberHouse', $ret[2]);
					$templateProcessor->setValue('street', $street);
					$templateProcessor->setValue('towerInitiator', $towerInitiator);
					$templateProcessor->setValue('dateStartDay', $meeting->dateStyleString($data['dateStart'])[0]);
					$templateProcessor->setValue('dateStartMonth', $meeting->dateStyleString($data['dateStart'])[1]);
					$templateProcessor->setValue('dateStartYear', $meeting->dateStyleString($data['dateStart'])[2]);
					break;
				case "occProtocol":
					$pathToFile = storage_path() . '/doc/' . $printDocumentMeeting . '.docx';
					$pathToFileName = $resultMeeting->address . "_" . "08" . "_" . "Протокол ОСС" . "_" . date('Y_m_d_H_i', $time);
					$path = storage_path() . '/tmp/doc/' . $pathToFileName;
					$templateProcessor = new TemplateProcessor($pathToFile);
					$templateProcessor->setValue('fioInitiatorRod', $data['fioInitiatorRod']);
					$templateProcessor->setValue('fioInitiatorIm', $data['fioInitiator']);
					$templateProcessor->setValue('addressInitiator', $resultMeeting->address);
					$templateProcessor->setValue('apartmentNumberInitiator', $data['apartmentNumberInitiator']);
					$templateProcessor->setValue('numberOfCameras', $data['numberOfCameras']);
					$templateProcessor->setValue('partnerTitle', $partnerTitle->title);
					$templateProcessor->setValue('montlyPayment', $resultMeeting->montlypayment);
					$montlyPaymentString = $number2String->number2string($resultMeeting->montlypayment);
					$templateProcessor->setValue('montlyPaymentString', $montlyPaymentString);
					$templateProcessor->setValue('towerInitiator', $towerInitiator);
					$templateProcessor->setValue('dateCreationDay', $meeting->dateStyleString(gmdate("M-d-Y H:i:s"))[0]);
					$templateProcessor->setValue('dateCreationMonth', $meeting->dateStyleString(gmdate("M-d-Y H:i:s"))[1]);
					$templateProcessor->setValue('dateCreationYear', $meeting->dateStyleString(gmdate("M-d-Y H:i:s"))[2]);
					$templateProcessor->setValue('dateStartDay', $meeting->dateStyleString($data['dateStart'])[0]);
					$templateProcessor->setValue('dateStartMonth', $meeting->dateStyleString($data['dateStart'])[1]);
					$templateProcessor->setValue('dateStartYear', $meeting->dateStyleString($data['dateStart'])[2]);
					$templateProcessor->setValue('dateEndDay', $meeting->dateStyleString($data['dateEnd'])[0]);
					$templateProcessor->setValue('dateEndMonth', $meeting->dateStyleString($data['dateEnd'])[1]);
					$templateProcessor->setValue('dateEndYear', $meeting->dateStyleString($data['dateEnd'])[2]);
					//Question
					$questions = $meeting->getQuestionMeeting($data)['result'];
					$templateProcessor->cloneBlock('block_question1', $questions->count(), true, true);
					$templateProcessor->cloneBlock('block_question2', $questions->count(), true, true);
					$i = 1;
					foreach ($questions as $quest) {
						$templateProcessor->setValue('${number#' . $i . '}', $i);
						$templateProcessor->setValue('${question#' . $i . '}', $quest->description);
						$i++;
					}
					//QuestionEnd
					break;
				case "reportOnTheResultsOfTheOSS":
					$pathToFile = storage_path() . '/doc/' . $printDocumentMeeting . '.docx';
					$pathToFileName = $resultMeeting->address . "_" . "09" . "_" . "Сообщение о результатах проведенного ОСС" . "_" . date('Y_m_d_H_i', $time);
					$path = storage_path() . '/tmp/doc/' . $pathToFileName;
					$templateProcessor = new TemplateProcessor($pathToFile);
					$templateProcessor->setValue('addressInitiator', $resultMeeting->address);
					//Question
					$questions = $meeting->getQuestionMeeting($data)['result'];
					$templateProcessor->cloneBlock('block_question', $questions->count(), true, true);
					$i = 1;
					foreach ($questions as $quest) {
						$templateProcessor->setValue('${number#' . $i . '}', $i);
						$defaultQuestion = $quest->description;
						if ($defaultQuestion[strlen($defaultQuestion) - 1] == '.') {
							$templateProcessor->setValue('${question#' . $i . '}', substr($defaultQuestion, 0, -1));
						} else {
							$templateProcessor->setValue('${question#' . $i . '}', $quest->description);
						}
						$i++;
					}
					//QuestionEnd
					break;
				case "theActOfPostingAMessageWithTheResultsOfTheOSS":
					$pathToFile = storage_path() . '/doc/' . $printDocumentMeeting . '.docx';
					$pathToFileName = $resultMeeting->address . "_" . "10" . "_" . "Акт о размещении сообщения с результатами ОСС" . "_" . date('Y_m_d_H_i', $time);
					$path = storage_path() . '/tmp/doc/' . $pathToFileName;
					$templateProcessor = new TemplateProcessor($pathToFile);
					$templateProcessor->setValue('fioInitiatorIm', $data['fioInitiator']);
					$templateProcessor->setValue('addressInitiator', $resultMeeting->address);
					$templateProcessor->setValue('apartmentNumberInitiator', $data['apartmentNumberInitiator']);
					$templateProcessor->setValue('towerInitiator', $towerInitiator);
					$templateProcessor->setValue('street', $street);
					$templateProcessor->setValue('dateStartDay', $meeting->dateStyleString($data['dateStart'])[0]);
					$templateProcessor->setValue('dateStartMonth', $meeting->dateStyleString($data['dateStart'])[1]);
					$templateProcessor->setValue('dateStartYear', $meeting->dateStyleString($data['dateStart'])[2]);
					$templateProcessor->setValue('dateEndDay', $meeting->dateStyleString($data['dateEnd'])[0]);
					$templateProcessor->setValue('dateEndMonth', $meeting->dateStyleString($data['dateEnd'])[1]);
					$templateProcessor->setValue('dateEndYear', $meeting->dateStyleString($data['dateEnd'])[2]);
					$templateProcessor->setValue('numberHouse', $house);
					break;
				case "coverLetterToTheCriminalCodeAboutTheTransferOfDocuments":
					$pathToFile = storage_path() . '/doc/' . $printDocumentMeeting . '.docx';
					$pathToFileName = $resultMeeting->address . "_" . "11" . "_" . "Сопроводительное письмо в УК о передаче документов" . "_" . date('Y_m_d_H_i', $time);
					$path = storage_path() . '/tmp/doc/' . $pathToFileName;
					$templateProcessor = new TemplateProcessor($pathToFile);
					$templateProcessor->setValue('fioInitiatorIm', $data['fioInitiator']);
					$templateProcessor->setValue('fioInitiatorRod', $data['fioInitiatorRod']);
					$templateProcessor->setValue('apartmentNumberInitiator', $data['apartmentNumberInitiator']);
					$templateProcessor->setValue('numberHouseD', $house);
					$templateProcessor->setValue('numberHouse', str_replace('д', '', $house));
					$templateProcessor->setValue('towerInitiator', $towerInitiator);
					$templateProcessor->setValue('dateStartDay', $meeting->dateStyleString($data['dateStart'])[0]);
					$templateProcessor->setValue('dateStartMonth', $meeting->dateStyleString($data['dateStart'])[1]);
					$templateProcessor->setValue('dateStartYear', $meeting->dateStyleString($data['dateStart'])[2]);
					$templateProcessor->setValue('dateEndDay', $meeting->dateStyleString($data['dateEnd'])[0]);
					$templateProcessor->setValue('dateEndMonth', $meeting->dateStyleString($data['dateEnd'])[1]);
					$templateProcessor->setValue('dateEndYear', $meeting->dateStyleString($data['dateEnd'])[2]);
					$templateProcessor->setValue('street', $street);
					$templateProcessor->setValue('nameCompanyPrint', $data['titleYK']);
					$templateProcessor->setValue('nameDirectorPrint', $data['headYKDat']);
					$templateProcessor->setValue('addressCompanyPrint', $data['addressYK']);
					$templateProcessor->setValue('nameDirectorPrintIm', $data['headYKIm']);
					$templateProcessor->setValue('postalCode', $data['postCodeYK']);
					$templateProcessor->setValue('endingFloor', $endingFloor);
					break;
				case "coverForOSSDocuments":
					$pathToFile = storage_path() . '/doc/' . $printDocumentMeeting . '.docx';
					$pathToFileName = $resultMeeting->address . "_" . "12" . "_" . "Обложка на документы ОСС" . "_" . date('Y_m_d_H_i', $time);
					$path = storage_path() . '/tmp/doc/' . $pathToFileName;
					$templateProcessor = new TemplateProcessor($pathToFile);
					$templateProcessor->setValue('addressInitiator', $resultMeeting->address);
					break;
				case "stickerForFlashingOSSDocuments":
					$pathToFile = storage_path() . '/doc/' . $printDocumentMeeting . '.docx';
					$pathToFileName = $resultMeeting->address . "_" . "13" . "_" . "Наклейка для прошивки документов ОСС" . "_" . date('Y_m_d_H_i', $time);
					$path = storage_path() . '/tmp/doc/' . $pathToFileName;
					$templateProcessor = new TemplateProcessor($pathToFile);
					break;
				case "contractForTheProvisionOfVideoSurveillanceServices":
					$pathToFile = storage_path() . '/doc/' . $printDocumentMeeting . '.docx';
					$pathToFileName = $resultMeeting->address . "_" . "14" . "_" . "Договор об оказании услуг по видеонаблюдению" . "_" . date('Y_m_d_H_i', $time);
					$path = storage_path() . '/tmp/doc/' . $pathToFileName;
					$templateProcessor = new TemplateProcessor($pathToFile);
					$templateProcessor->setValue('fioInitiatorIm', $data['fioInitiator']);
					$templateProcessor->setValue('addressInitiator', $resultMeeting->address);
					$templateProcessor->setValue('towerInitiator', $towerInitiator);
					$templateProcessor->setValue('apartmentNumberInitiator', $data['apartmentNumberInitiator']);
					$templateProcessor->setValue('street', $street);
					$templateProcessor->setValue('numberHouse', str_replace('д', '', $house));
					$templateProcessor->setValue('partnerTitle', $partnerTitle->title);
					$templateProcessor->setValue('montlyPayment', $resultMeeting->montlypayment);
					$montlyPaymentString = $number2String->number2string($resultMeeting->montlypayment);
					$templateProcessor->setValue('montlyPaymentString', $montlyPaymentString);
					$templateProcessor->setValue('numberOfCameras', $data['numberOfCameras']);
					$numberOfCamerasString = $number2String->number2string($data['numberOfCameras']);
					$templateProcessor->setValue('numberOfCamerasString', $numberOfCamerasString);
					$templateProcessor->setValue('phoneInitiator', $data['phoneInitiator']);
					if (!empty($dataObject['fio'])) {
						$templateProcessor->setValue('directorNameRod', $dataObject['fioRod']);
						$templateProcessor->setValue('partnerPhone', $partnerTitle->phone);
						$templateProcessor->setValue('partnerAddress', $dataObject['address']);
						$directorNameTemp = explode(" ", $dataObject['fio']);
						$directorNameReduction = $directorNameTemp[0] . " " . mb_substr($directorNameTemp[1], 0, 1) . "." . mb_substr($directorNameTemp[2], 0, 1) . ".";
						$templateProcessor->setValue('directorNameReduction', $directorNameReduction);
					} else {
						$templateProcessor->setValue('directorNameRod', '______________________');
						$templateProcessor->setValue('partnerPhone', '________________________');
						$templateProcessor->setValue('partnerAddress', '________________________');
						$templateProcessor->setValue('directorNameReduction', '_______________________');
					}

					break;
				case "letterToTheCriminalCodeForTheProvisionOfMCDDocumentation":
					$pathToFile = storage_path() . '/doc/' . $printDocumentMeeting . '.docx';
					$pathToFileName = $resultMeeting->address . "_" . "15" . "_" . "Письмо в УК о предоставлении документации МКД" . "_" . date('Y_m_d_H_i', $time);
					$path = storage_path() . '/tmp/doc/' . $pathToFileName;
					$templateProcessor = new TemplateProcessor($pathToFile);
					$templateProcessor->setValue('partnerTitle', $partnerTitle->title);
					$templateProcessor->setValue('addressInitiator', $resultMeeting->address);
					$templateProcessor->setValue('dateCreationDay', $meeting->dateStyleString(gmdate("M-d-Y H:i:s"))[0]);
					$templateProcessor->setValue('endingFloor', $endingFloor);
					$templateProcessor->setValue('dateCreationMonth', $meeting->dateStyleString(gmdate("M-d-Y H:i:s"))[1]);
					$templateProcessor->setValue('dateCreationYear', $meeting->dateStyleString(gmdate("M-d-Y H:i:s"))[2]);
					$templateProcessor->setValue('towerInitiator', $towerInitiator);
					$templateProcessor->setValue('nameDirectorPrintDat', $data['headYKDat']);
					$templateProcessor->setValue('nameDirectorPrint', $data['headYKIm']);
					$templateProcessor->setValue('nameCompanyPrint', $data['titleYK']);
					$templateProcessor->setValue('OGRN', $partnerTitle->ogrn);
					$templateProcessor->setValue('INN', $partnerTitle->inn);
					$templateProcessor->setValue('KPP', $partnerTitle->kpp);
					if (!empty($dataObject['fio'])) {
						$templateProcessor->setValue('partnerPhone', $partnerTitle->phone);
						$templateProcessor->setValue('partnerAddress', $dataObject['address']);
						$directorNameTemp = explode(" ", $dataObject['fio']);
						$directorNameReduction = $directorNameTemp[0] . " " . mb_substr($directorNameTemp[1], 0, 1) . "." . mb_substr($directorNameTemp[2], 0, 1) . ".";
						$templateProcessor->setValue('directorNameReduction', $directorNameReduction);
					} else {
						$templateProcessor->setValue('directorNameRod', '______________________');
						$templateProcessor->setValue('partnerPhone', '________________________');
						$templateProcessor->setValue('partnerAddress', '________________________');
						$templateProcessor->setValue('directorNameReduction', '_______________________');
					}
					break;
				case "letterToTheCriminalCodeOnGrantingAccessToTheMCD":
					$pathToFile = storage_path() . '/doc/' . $printDocumentMeeting . '.docx';
					$pathToFileName = $resultMeeting->address . "_" . "16" . "_" . "Письмо в УК о предоставлении доступа в МКД" . "_" . date('Y_m_d_H_i', $time);
					$path = storage_path() . '/tmp/doc/' . $pathToFileName;
					$templateProcessor = new TemplateProcessor($pathToFile);
					$templateProcessor->setValue('partnerTitle', $partnerTitle->title);
					$templateProcessor->setValue('addressInitiator', $resultMeeting->address);
					$templateProcessor->setValue('dateCreationDay', $meeting->dateStyleString(gmdate("M-d-Y H:i:s"))[0]);
					$templateProcessor->setValue('dateCreationMonth', $meeting->dateStyleString(gmdate("M-d-Y H:i:s"))[1]);
					$templateProcessor->setValue('dateCreationYear', $meeting->dateStyleString(gmdate("M-d-Y H:i:s"))[2]);
					$templateProcessor->setValue('towerInitiator', $towerInitiator);
					$templateProcessor->setValue('nameDirectorPrintDat', $data['headYKDat']);
					$templateProcessor->setValue('nameDirectorPrint', $data['headYKIm']);
					$templateProcessor->setValue('nameCompanyPrint', $data['titleYK']);
					$templateProcessor->setValue('endingFloor', $endingFloor);
					$templateProcessor->setValue('OGRN', $partnerTitle->ogrn);
					$templateProcessor->setValue('INN', $partnerTitle->inn);
					$templateProcessor->setValue('KPP', $partnerTitle->kpp);
					if (!empty($dataObject['fio'])) {
						$templateProcessor->setValue('partnerPhone', $partnerTitle->phone);
						$templateProcessor->setValue('partnerAddress', $dataObject['address']);
						$directorNameTemp = explode(" ", $dataObject['fio']);
						$directorNameReduction = $directorNameTemp[0] . " " . mb_substr($directorNameTemp[1], 0, 1) . "." . mb_substr($directorNameTemp[2], 0, 1) . ".";
						$templateProcessor->setValue('directorNameReduction', $directorNameReduction);
					} else {
						$templateProcessor->setValue('directorNameRod', '______________________');
						$templateProcessor->setValue('partnerPhone', '________________________');
						$templateProcessor->setValue('partnerAddress', '________________________');
						$templateProcessor->setValue('directorNameReduction', '_______________________');
					}
					$support = new Support();
					$paramMeeting['partnerId'] = $user->getId();
					$supports = $support->supports($paramMeeting);
					$templateProcessor->cloneBlock('block_name', $supports->count(), true, true);
					$i = 1;
					foreach ($supports as $supp) {
						$templateProcessor->setValue('${number#' . $i . '}', $i);
						$templateProcessor->setValue('${fio#' . $i . '}', $supp->surname . " " . $supp->name . " " . $supp->middle_name);
						$i++;
					}
					break;
			}

			$fileNames = $printDocumentMeeting . '_' . time();
			$path = storage_path() . '/tmp/doc/' . $fileNames;
			$i = 1;
			if (is_file($path . '.' . 'docx')) {
				while (is_file($path . '_(' . $i . ')' . '.' . 'docx')) {
					$i++;
				}
				$path = $path . '_(' . $i . ')' . '.' . 'docx';
				$pathToFileName =  $pathToFileName . '_(' . $i . ')' . '.' . 'docx';
				$fileNames =  $fileNames . '_(' . $i . ')' . '.' . 'docx';
			} else {
				$path = $path . '.' . 'docx';
				$pathToFileName =  $pathToFileName . '.' . 'docx';
				$fileNames =  $fileNames . '.' . 'docx';
			}
			$templateProcessor->saveAs($path);

			return json_encode([
				"success" => "true",
				"path" => $fileNames,
				"pathUser" => $pathToFileName
			]);
		} else {
			return json_encode([
				"success" => "false",
				"code" => "400",
				"message" => "Добавьте не менее одного вопроса"
			]);
		}

		return json_encode([
			"success" => "false",
			"code" => "500",
			"message" => "Возникла техническая ошибка"
		]);
	}
	//end switch case  from completion document
	//end documentation OSS
}
