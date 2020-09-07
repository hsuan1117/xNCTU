<?php
session_start();
require_once('utils.php');
require_once('config.php');
require_once('database.php');
$db = new MyDB();

if (!isset($_GET['code'])) {
	fail('Redirecting...', 0);
}

$curl = curl_init('https://oauth2.googleapis.com/token');
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, [
	'grant_type' => 'authorization_code',
	'code' => $_GET['code'],
	'client_id' => '980594892712-ffhev6flnet47c83du107qsosjo9htrp.apps.googleusercontent.com',
	'client_secret' => 'R2u0eflv_kkBA-P-alZ13W5d',
	'redirect_uri' => "https://$DOMAIN/login-google",
]);
$data = curl_exec($curl);
curl_close($curl);
$data = json_decode($data, true);
if (isset($data['error']))
	fail($data['error'], 5);

if (!isset($data['access_token']))
	fail('No access token.', 5);

$token = $data['id_token'];

$curl = curl_init("https://oauth2.googleapis.com/tokeninfo?id_token=$token");
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$data = curl_exec($curl);
$data = json_decode($data, true);


if (!isset($data['name']))
	fail('No name from Google.', 5);

if (!isset($data['email']))
	fail('No email from Google.', 5);

echo json_encode($data, JSON_PRETTY_PRINT);


function fail(string $msg = '', int $time) {
	$url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
		'client_id' => '980594892712-ffhev6flnet47c83du107qsosjo9htrp.apps.googleusercontent.com',
		'redirect_uri' => 'https://x.nctu.app/login-google',
		'response_type' => 'code',
		'scope' => 'email profile'
	]);

	if ($time == 0)
		header("Location: $url");

	echo "<meta http-equiv='refresh' content='$time; url=$url' />";

	if (!empty($msg))
		echo "<h1>$msg</h1>";

	echo "Redirect in $time seconds. <a href='$url'>Click me</a>";
	exit;
}
