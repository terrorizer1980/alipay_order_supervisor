<?php
include 'vendor/autoload.php';
$session_id = 'ALIPAYJSESSIONID=YOUR_COOKIE';

$ql = QL\QueryList::getInstance();
$ql->bind('cURL', function ($url, $cookie) {
	Requests::register_autoloader();
	$headers = array(
		'Accept-Encoding' => 'gzip, deflate, br',
		'Accept-Language' => 'ja-JP,ja;q=0.9,en-US;q=0.8,en;q=0.7,zh-CN;q=0.6,zh;q=0.5,zh-TW;q=0.4',
		'Upgrade-Insecure-Requests' => '1',
		'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.3202.89 Safari/537.36',
		'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
		'Referer' => 'https://consumeprod.alipay.com/record/standard.htm',
		'Connection' => 'keep-alive',
		'Cache-Control' => 'max-age=0',
		'Cookie' => $cookie,
	);
	$response = Requests::get($url, $headers);
	$this->setHtml($response->body);
	return $this;
});
while (true) {
	$old_files = file_get_contents('diff.json');
	$keep_alive = $ql->cURL('https://my.alipay.com/portal/i.htm', $session_id);
	$result = $ql->cURL('https://consumeprod.alipay.com/record/standard.htm', $session_id)->encoding('UTF-8')->rules([
		'order_id' => ['.J-tradeNo-copy.J-tradeNo', 'title'],
		'time' => ['td.time > p.text-muted', 'text'],
		'amount' => ['.amount-pay', 'text'],
		'info' => ['.consume-title', 'text'],
		'status' => ['td[class="status"]', 'text'],
		'username' => ['td.name > p.name.p-inline.ft-gray', 'text'],
	])->query()->getData()->all();
	if (!$result) {
		echo ("Alipay Session Expired" . PHP_EOL);
		continue;
	}

	$order_time = $ql->cURL('https://lab.alipay.com/consume/record/items.htm', $session_id)->encoding('UTF-8')->rules([
		'time' => ['td.time', 'text'],
	])->query()->getData()->all();
	$history = [];
	// print_r($result);
	foreach ($result as $key => $value) {
		$history[$key]['order_id'] = $value['order_id'];
		$history[$key]['order_time'] = strtotime($order_time[$key]['time']);
		//$history[$key]['order_time'] = strtotime(substr($value['order_id'], 0, 4) . "-" . substr($value['order_id'], 4, 2) . "-" . substr($value['order_id'], 6, 2) . " " . $value['time'] . ":" . "00");
		$history[$key]['order_info'] = $value['info'];
		$history[$key]['amount'] = substr($value['amount'], 2);
		$history[$key]['status'] = trim($value['status']);
		if (substr($value['amount'], 0, 1) == "+") {
			$history[$key]['type'] = "income";
		} else {
			$history[$key]['type'] = "outcome";
		}
		$history[$key]['username'] = unicode2utf8(substr(trim($value['username']), 0, -2));
	}
	if (json_encode($history) !== $old_files) {
		print_r($history[0]);
		file_put_contents('diff.json', json_encode($history));
	} else {
		echo "No Diff" . PHP_EOL;
	}
	sleep(3);
}
function unicode2utf8($str) {
	if (!$str) {
		return $str;
	}

	$decode = json_decode($str);
	if ($decode) {
		return $decode;
	}

	$str = '["' . $str . '"]';
	$decode = json_decode($str);
	if (count($decode) == 1) {
		return $decode[0];
	}
	return $str;
}