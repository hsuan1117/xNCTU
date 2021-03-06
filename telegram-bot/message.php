<?php
require(__DIR__.'/../utils.php');
require(__DIR__.'/../database.php');
$db = new MyDB();

$text = $TG->data['message']['text'] ?? '';

if ($TG->ChatID < 0) {
	if ($TG->ChatID != LOG_GROUP)
		$TG->sendMsg([
			'text' => '目前尚未支援群組功能',
			'reply_markup' => [
				'inline_keyboard' => [
					[
						[
							'text' => "📢 $SITENAME 頻道",
							'url' => 'https://t.me/xNCTU'
						]
					]
				]
			]
		]);

	if (substr($text, 0, 1) != '/')
		exit;
}

$USER = $db->getUserByTg($TG->FromID);
if (!$USER) {
	$msg = "您尚未綁定任何交大身份\n\n";
	$msg .= "請先至網站登入後，再點擊下方按鈕綁定帳號";
	$TG->sendMsg([
		'text' => $msg,
		'reply_markup' => [
			'inline_keyboard' => [
				[
					[
						'text' => "綁定$SITENAME 帳號",
						'login_url' => [
							'url' => "https://$DOMAIN/login-tg"
						]
					]
				]
			]
		]
	]);
	exit;
}

if (substr($text, 0, 1) == '/') {
	$text = substr($text, 1);
	[$cmd, $arg] = explode(' ', $text, 2);
	$cmd = explode('@', $cmd, 2)[0];

	switch($cmd) {
		case 'start':
			$msg = "歡迎使用$SITENAME 機器人\n\n";
			$msg .= "使用 /help 顯示指令清單";

			$TG->sendMsg([
				'text' => $msg,
				'reply_markup' => [
					'inline_keyboard' => [
						[
							[
								'text' => "登入$SITENAME",
								'login_url' => [
									'url' => "https://$DOMAIN/login-tg"
								]
							]
						]
					]
				]
			]);
			break;

		case 'help':
			$msg = "目前支援的指令：\n\n";
			$msg .= "/name 更改網站上的暱稱\n";
			$msg .= "/unlink 解除 Telegram 綁定\n";
			$msg .= "/delete 刪除貼文\n";
			$msg .= "/help 顯示此訊息\n";
			$msg .= "\nℹ️ 由 @SeanChannel 提供";

			$TG->sendMsg([
				'text' => $msg
			]);
			break;

		case 'name':
			$arg = $TG->enHTML(trim($arg));
			if (mb_strlen($arg) < 1 || mb_strlen($arg) > 10) {
				$TG->sendMsg([
					'text' => "使用方式：`/name 新暱稱`\n\n字數上限：10 個字",
					'parse_mode' => 'Markdown'
				]);
				break;
			}

			$db->updateUserNameTg($TG->FromID, $arg);

			$TG->sendMsg([
				'text' => '修改成功！',
				'reply_markup' => [
					'inline_keyboard' => [
						[
							[
								'text' => '開啟網站',
								'login_url' => [
									'url' => "https://$DOMAIN/login-tg"
								]
							]
						]
					]
				]
			]);
			break;

		case 'unlink':
			$db->unlinkUserTg($TG->FromID);
			$TG->sendMsg([
				'text' => "已取消連結，請點擊下方按鈕連結新的 NCTU OAuth 帳號",
				'reply_markup' => [
					'inline_keyboard' => [
						[
							[
								'text' => "綁定$SITENAME 網站",
								'login_url' => [
									'url' => "https://$DOMAIN/login-tg"
								]
							]
						]
					]
				]
			]);
			break;

		case 'update':
			if ($TG->ChatID !== LOG_GROUP) {
				$TG->sendMsg([
					'text' => "此功能僅限核心維護群組使用"
				]);
				exit;
			}

			[$column, $new] = explode(' ', $arg, 2);

			if ($column == 'name') {
				[$stuid, $name] = explode(' ', $new, 2);
				$db->updateUserNameStuid($stuid, $name);

				$TG->sendMsg([
					'text' => "Done."
				]);
				break;
			}

			if (!preg_match('/^#投稿(\w{4})/um', $TG->data['message']['reply_to_message']['text'] ?? $TG->data['message']['reply_to_message']['caption'] ?? '', $matches)) {
					$TG->sendMsg([
						'text' => 'Please reply to submission message.'
					]);
					exit;
			}
			$uid = $matches[1];

			switch ($column) {
				case 'body':
					$db->updatePostBody($uid, $new);
					break;

				case 'status':
					$db->updatePostStatus($uid, $new);
					break;

				default:
					$TG->sendMsg([
						'text' => "Column '$column' unsupported."
					]);
					exit;
					break;
			}

			$TG->sendMsg([
				'text' => "Done."
			]);

			break;

		case 'delete':
			if ($TG->ChatID !== LOG_GROUP) {
				$TG->sendMsg([
					'text' => "此功能僅限核心維護群組使用\n\n" .
						"如果您有興趣為$SITENAME 盡一份心力的話，歡迎聯絡開發團隊 🙃"
				]);
				exit;
			}

			[$uid, $status, $reason] = explode(' ', $arg, 3);

			if (mb_strlen($reason) == 0) {
				$TG->sendMsg([
					'text' => "Usage: /delete <uid> <status> <reason>\n\n" .
						"-2 rejected\n" .
						"-3 deleted by author (hidden)\n" .
						"-4 deleted by admin\n" .
						"-11 deleted and hidden by admin"
				]);
				exit;
			}

			$msgs = $db->getTgMsgsByUid($uid);
			foreach ($msgs as $item) {
				$TG->deleteMsg($item['chat_id'], $item['msg_id']);
				$db->deleteTgMsg($uid, $item['chat_id']);
			}
			$db->deleteSubmission($uid, $status, $reason);

			$TG->sendMsg([
				'text' => "Done."
			]);
			break;

		case 'adduser':
			if ($TG->ChatID !== LOG_GROUP) {
				$TG->sendMsg([
					'text' => "此功能僅限核心維護群組使用"
				]);
				exit;
			}

			$args = explode(' ', $arg);
			if (count($args) != 2) {
				$TG->sendMsg([
					'text' => "使用方式：/adduser <Student ID> <TG ID>",
				]);
				exit;
			}

			$stuid = $args[0];
			$tg_id = $args[1];

			$db->insertUserStuTg($stuid, $tg_id);

			$result = $TG->sendMsg([
				'chat_id' => $tg_id,
				'text' => "🎉 驗證成功！\n\n請點擊以下按鈕登入$SITENAME 網站",
				'reply_markup' => [
					'inline_keyboard' => [
						[
							[
								'text' => "登入$SITENAME",
								'login_url' => [
									'url' => "https://$DOMAIN/login-tg?r=%2Freview"
								]
							]
						]
					]
				]
			]);

			if ($result['ok'])
				$TG->sendMsg([
					'text' => "Done.\n"
				]);
			else
				$TG->sendMsg([
					'text' => "Failed.\n\n" . json_encode($result, JSON_PRETTY_PRINT)
				]);
			break;

		case 'migrate':
			if ($TG->ChatID !== LOG_GROUP) {
				$TG->sendMsg([
					'text' => "此功能僅限核心維護群組使用"
				]);
				exit;
			}

			if ($arg == '') {
				$TG->sendMsg([
					'text' => "使用方式：/migrate <old stuid> [new stuid]",
				]);
				exit;
			}
			$args = explode(' ', $arg);

			$stuid_old = $args[0];
			$stuid_new = $args[1] ?? '';

			$user_old = $db->getUserByStuid($stuid_old);
			$user_new = $db->getUserByStuid($stuid_new);

			if ($stuid_new == '') {
				$posts = $db->getPostsByStuid($stuid_old);
				$votes = $db->getVotesByStuid($stuid_old);

				$text = "舊使用者資訊：\n";
				$text .= "暱稱：{$user_old['name']}\n";
				if (count($posts)) $text .= "投稿數：" . count($posts) . " 篇\n";
				if (count($votes)) $text .= "投票數：" . count($votes) . " 篇\n";

				$TG->sendMsg([
					'text' => $text
				]);
				break;
			}

			if (isset($user_new)) {
				$TG->sendMsg([
					'text' => "新帳號 {$user_new['name']} 已註冊"
				]);
				break;
			}

			$sql = "UPDATE posts SET author_id = :new WHERE author_id = :old";

			$TG->sendMsg([
				'text' => "SQL: $sql"
			]);
			$stmt = $db->pdo->prepare($sql);
			$stmt->execute([':old' => $stuid_old, ':new' => $stuid_new]);

			$sql = "UPDATE votes SET stuid = :new WHERE stuid = :old";
			$stmt = $db->pdo->prepare($sql);
			$stmt->execute([':old' => $stuid_old, ':new' => $stuid_new]);

			$sql = "UPDATE users SET stuid = :new WHERE stuid = :old";
			$stmt = $db->pdo->prepare($sql);
			$stmt->execute([':old' => $stuid_old, ':new' => $stuid_new]);

			$TG->sendMsg([
				'text' => 'Done.'
			]);

			break;

		default:
			$TG->sendMsg([
				'text' => "未知的指令\n\n如需查看使用說明請使用 /help 功能"
			]);
			break;
	}

	exit;
}

if (preg_match('#^\[(approve|reject)/([a-zA-Z0-9]+)\]#', $TG->data['message']['reply_to_message']['text'] ?? '', $matches)) {
	$vote = $matches[1] == 'approve' ? 1 : -1;
	$uid = $matches[2];
	$reason = $text;

	$type = $vote == 1 ? '✅ 通過' : '❌ 駁回';

	if (mb_strlen($reason) < 1 || mb_strlen($reason) > 100) {
		$TG->sendMsg([
			'text' => '請輸入 1 - 100 字投票附註'
		]);

		exit;
	}

	try {
		$result = $db->voteSubmissions($uid, $USER['stuid'], $vote, $reason);
		if (!$result['ok'])
			$msg = $result['msg'];
		else {
			$msg = "您成功為 #投稿$uid 投下了 $type\n\n";
			$msg .= "目前通過 {$result['approvals']} 票、駁回 {$result['rejects']} 票";

			system("php " . __DIR__ . "/../jobs.php vote $uid {$USER['stuid']} > /dev/null &");
		}
	} catch (Exception $e) {
		$msg = 'Error ' . $e->getCode() . ': ' .$e->getMessage() . "\n";
	}

	$TG->sendMsg([
		'text' => $msg,
	]);

	$TG->getTelegram('deleteMessage', [
		'chat_id' => $TG->ChatID,
		'message_id' => $TG->data['message']['reply_to_message']['message_id'],
	]);


	exit;
}
