<?
use \Bitrix\Main\Loader;
use \Bitrix\Main\Localization\Loc;

Loc::loadLanguageFile(__FILE__);

class newUserTask
{
	public static $userID;
	public static $taskTitle;
	public static $taskDescription;
	public static $taskDeadline;
	public static $taskPriority = 2;
	public static $taskStatus = 2;
	public static $taskImage = "https://www.devex.pro/wp-content/themes/devex/images/bg-slaid.png";
	public static $taskID;

	public static function takeParams()
	{
		global $USER;

		self::$userID = $USER->GetID();
		self::$taskTitle = Loc::getMessage("TASK_TITLE");
		self::$taskDescription = Loc::getMessage("TASK_DESCRIPTION");
		self::$taskDeadline = date('d.m.Y', strtotime("+6 days"));
	}

	public static function createTask()
	{
		if (!Loader::includeModule("tasks") || !Loader::includeModule("intranet")) {
			return false;
		}

		self::takeParams();
		$userManager = self::getBitrixUserManager(self::$userID);

		$newTask = new \Bitrix\Tasks\Item\Task();
		$newTask->title = self::$taskTitle;
		$newTask->description = self::$taskDescription;
		$newTask->deadline = self::$taskDeadline;
		$newTask->priority = self::$taskPriority;
		$newTask->status = self::$taskStatus;
		$newTask->resposibleId = self::$userID;
		$newTask->createdBy = (empty($userManager) ? self::$userID : $userManager[key($userManager)]['ID']);

		$result = $newTask->save();
		if ($result->isSuccess()) {
			self::$taskID = $result->getInstance()->getId();
			self::addFileToTask(self::$taskID, false, self::$taskImage);
			self::addNotification(self::$taskID, self::$userID);
			self::taskReminder(self::$taskID, self::$userID);
		}
	}

	public static function addFileToTask($taskID, $fileID = false, $filePath = false)
	{
		if (!Loader::includeModule("tasks") || !Loader::includeModule("disk")) {
			return false;
		}

		if (!$fileID && !$filePath) {
			return false;
		}

		if (!$fileID && !empty($filePath)) {
			$storage = \Bitrix\Disk\Driver::getInstance()->getStorageByGroupId(1);
			if ($storage) {
				$folder = $storage->getRootObject();

				if ($folder) {
					$fileArray = \CFile::MakeFileArray($filePath);
					$file = $folder->getChild(
						array(
							'=NAME' => $fileArray["name"],
							'TYPE' => \Bitrix\Disk\Internals\FileTable::TYPE_FILE
						)
					);
					if ($file) {
						$fileID = $file->getId();
					} else {
						$file = $folder->uploadFile($fileArray, array(
							'CREATED_BY' => self::$userID
						));
						$fileID = $file->getId();
					}
				}
			}
		}

		$tasksFiles = new CTaskItem($taskID, self::$userID);
		$arFields = array(
			"UF_TASK_WEBDAV_FILES"   => array("n".$fileID)
		);

		try
		{
			if (!$tasksFiles->update($arFields)) {
				throw new TasksException();
			}
		}
		catch (TasksException $e)
		{
			return;
		}
	}

	public static function addNotification($taskID = false, $userID = false)
	{
		if (!Loader::includeModule("im")) {
			return false;
		}

		if (!$userID) {
			$userID = self::$userID;
		}

		if (!$taskID) {
			$taskID = self::$taskID;
		}

		$taskLink = $_SERVER['HTTP_HOST'].'/company/personal/user/' . $userID . '/tasks/task/view/' . $taskID . '/';

		$arMessageFields = array(
			"TO_USER_ID"     => $userID,
			"FROM_USER_ID"   => 0,
			"NOTIFY_TYPE"    => IM_NOTIFY_SYSTEM,
			"NOTIFY_MODULE"  => "tasks",
			"NOTIFY_MESSAGE" => sprintf(Loc::getMessage("TASK_LINK"), $taskLink),
		);

		\CIMNotify::Add($arMessageFields);
	}

	public static function taskReminder($taskID = false, $userID = false)
	{
		if (!Loader::includeModule("tasks")) {
			return false;
		}

		if (!$userID) {
			$userID = self::$userID;
		}

		if (!$taskID) {
			$taskID = self::$taskID;
		}

		$arFields = Array(
			"TASK_ID" => $taskID,
			"USER_ID" => $userID,
			"TYPE" => CTaskReminders::REMINDER_TYPE_DEADLINE,
			"TRANSPORT" => CTaskReminders::REMINDER_TRANSPORT_EMAIL
		);

		$obTaskReminders = new CTaskReminders;
		for ($i = 1; $i < 7; $i++) {
			$arFields["REMIND_DATE"] = date('d.m.Y', strtotime("+{$i} days"));
			$obTaskReminders->Add($arFields);
		}
	}

	public static function getBitrixUserManager($userID = false)
	{
		if (!$userID) {
			$userID = self::$userID;
		}

		return array_keys(
			CIntranetUtils::GetDepartmentManager(
				CIntranetUtils::GetUserDepartments($userID),
				$userID,
				true
			)
		);
	}
}