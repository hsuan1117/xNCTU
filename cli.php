<?php
/* Only Command-line Execution Allowed */
if (!isset($argv[1]))
	exit;

require('utils.php');
require('database.php');
require_once('telegram-bot/class.php');
$db = new MyDB();
$TG = new Telegram();


switch ($argv[1]) {
case 'dump':
	$data = [];
	$posts = [];

	$tables = ['posts', 'votes', 'users', 'tg_msg'];
	foreach ($tables as $table) {
		$sql = "SELECT * FROM $table ORDER BY created_at DESC";
		$db->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
		$stmt = $db->pdo->prepare($sql);
		$stmt->execute();
		$data[$table] = [];
		while ($item = $stmt->fetch()) {
			if (isset($item['stuid']))
				$item['stuid'] = idToDep($item['stuid']) . ' ' . $item['stuid'];

			if ($table == 'posts')
				$posts[ $item['uid'] ] = $item;

			if ($table == 'votes') {
				$item['uid'] .= ' ' . mb_substr($posts[ $item['uid'] ]['body'], 0, 20) . '..';

				$item['vote'] = ($item['vote'] == '1' ? '✅ 通過' : '❌ 駁回');
			}

			$data[$table][] = $item;
		}
	}

	echo json_encode($data, JSON_PRETTY_PRINT);
	break;

case 'reject':
	$posts = $db->getSubmissions(0);
	foreach ($posts as $post) {
		/* Prevent reject demo post */
		if ($post['status'] != 3)
			continue;

		/*
		 * $dt: Time after submit in minute
		 *
		 * If submitted at 04:20 and voted 6:3, it will be racing condition at 05:20,
		 * so we delete it when checker run at 05:19.
		 */
		$now = floor(time()/60);
		$created = floor(strtotime($post['created_at'])/60);
		$dt = $now - $created;
		if ($created % 5 == 0)
			$dt += 1;

		if (strpos($post['author_name'], '境外') === false) {
			/* Before 1 hour */
			if ($dt <  1*60 && $post['rejects'] < 5)
				continue;

			/* 1 hour - 12 hour */
			if ($dt < 12*60 && $post['rejects'] < 3)
				continue;
		} else {
			/* Before 1 hour */
			if ($dt <  1*60 && $post['rejects'] < 2)
				continue;
		}

		$uid = $post['uid'];
		$db->deleteSubmission($uid, -2, '已駁回');

		/* Remove vote keyboard in Telegram */
		$msgs = $db->getTgMsgsByUid($uid);
		foreach ($msgs as $item) {
			$TG->deleteMsg($item['chat_id'], $item['msg_id']);
			$db->deleteTgMsg($uid, $item['chat_id']);
		}
	}

	/* Unconfirmed submissions */
	$sql = "SELECT * FROM posts WHERE status = 0";
	$stmt = $db->pdo->prepare($sql);
	$stmt->execute();
	while ($post = $stmt->fetch()) {
		$dt = (int) (floor(time() / 60) - floor(strtotime($post['created_at']) / 60));

		/* Only send notify when 10 min */
		if ($dt != 10)
			continue;

		$uid = $post['uid'];

		$msg = "<未確認投稿>\n\n";
		$msg .= $post['body'];

		$keyboard = [
			'inline_keyboard' => [
				[
					[
						'text' => '✅ 確認投稿',
						'callback_data' => "confirm_$uid"
					],
					[
						'text' => '❌ 刪除投稿',
						'callback_data' => "delete_$uid"
					]
				],
				[
					[
						'text' => '開啟審核頁面',
						'login_url' => [
							'url' => 'https://' . DOMAIN . "/login-tg?r=%2Freview%2F$uid"
						]
					]
				]
			]
		];

		if (!$post['has_img'])
			$TG->sendMsg([
				'chat_id' => LOG_GROUP,
				'text' => $msg,
				'reply_markup' => $keyboard,
			]);
		else
			$TG->sendPhoto([
				'chat_id' => LOG_GROUP,
				'photo' => 'https://' . DOMAIN . "/img/$uid.jpg",
				'caption' => $msg,
				'reply_markup' => $keyboard,
			]);
	}

	break;

case 'update_likes':
	$curl = curl_init();
	curl_setopt_array($curl, [
		CURLOPT_RETURNTRANSFER => true,
	]);

	$last = $db->getLastPostId();
	$begin = $argv[2] ?? ($last - 100);

	for ($id=$last; $id>=$begin; $id--) {
		if (in_array($id, [581, 1597, 2211, 3849, 3870, 3975, 4275, 4575]))
			continue; // API error but post exists

		$post = $db->getPostById($id);
		if (!$post || $post['status'] != 5)
			continue;

		if ($post['facebook_id'] < 10)
			continue;

		$URL = 'https://graph.facebook.com/v7.0/' . FB_PAGES_ID . '_' . $post['facebook_id'] . '/reactions?fields=type,name,profile_type&limit=100000&access_token=' . FB_ACCESS_TOKEN;

		curl_setopt_array($curl, [
			CURLOPT_URL => $URL,
		]);
		$result = curl_exec($curl);
		$result = json_decode($result, true);

		if (isset($result['error'])) {
			echo "Error 1: $id\n";
			var_dump($result);

			if (($result['error']['error_user_title'] ?? '') == "Exceeded asset access limit") {
				$URL = str_replace("/reactions?", "/likes?", $URL);
				curl_setopt_array($curl, [
					CURLOPT_URL => $URL,
				]);
				$result = curl_exec($curl);
				$result = json_decode($result, true);
			}
		}

		if (!isset($result['data'])) {
			echo "Error 2: $id\n";
			var_dump($result);
			$json = json_encode($result, JSON_PRETTY_PRINT);
			file_put_contents(__DIR__ . "/backup/fb-stat/error-$id", $json);
			sleep(5);
			continue;
		}

		$result = $result['data'];
		$json = json_encode($result, JSON_PRETTY_PRINT);
		file_put_contents(__DIR__ . "/backup/fb-stat/reactions-$id", $json);

		$likes = count($result);
		$sql = "UPDATE posts SET fb_likes = :likes WHERE id = :id";
		$stmt = $db->pdo->prepare($sql);
		$stmt->execute([
			':likes' => $likes,
			':id' => $id,
		]);
	}
	break;

default:
	echo "Unknown argument: {$argv[1]}";
	break;
}
