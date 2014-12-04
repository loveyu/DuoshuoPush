<?php
/**
 * 简单的多说回调通知接口
 * @author loveyu
 * @Link http://www.loveyu.org/3762.html
 */

/**
 * 定义密钥字符串
 * 比如: 58fe2e10744dc89adf6945b23438166aa
 */
define( 'D_SECRET', '{Your SECRET}' );

/**
 * 自己定义的二级域名
 * 比如: loveyu
 */
define( 'D_SHORT_NAME', '{Your short name}' );

/**
 * PushBullet 推送KEY
 * 比如: zGOfdsfUITvgbdsgBggfdgspg8sSnqWYQwH7ffd
 */
define( 'P_SECRET', '{Your SECRET}' );

/**
 * 通知的标题
 */
define( 'D_NOTICE_TITLE', '你的多说有了新评论' );

/**
 * @var array $_IGNORE_EMAIL 需要忽略的邮件用户
 */
$_IGNORE_EMAIL = [];

if ( $_SERVER['REQUEST_METHOD'] != "POST" ) {
	header( "HTTP/1.1 403 Forbidden" );
	die( "Only accept post request." );
}

if ( ! isset( $_POST['action'] ) || ! isset( $_POST['signature'] ) ) {
	header( "HTTP/1.1 403 Forbidden" );
	die( "POST request parameters error." );
}

/**
 * 当前操作类型
 */
define( 'D_ACTION', $_POST['action'] );

/**
 * 当前签名
 */
define( 'D_SIGNATURE', $_POST['signature'] );


if ( ! check_signature( $_POST ) ) {
	header( "HTTP/1.1 403 Forbidden" );
	die( "Signature check error." );
}

$response = get_list( "http://api.duoshuo.com/log/list.json", [ 'short_name' => D_SHORT_NAME, 'secret' => D_SECRET, 'limit' => 1, 'order' => 'desc' ] );

if ( ! isset( $response['response'] ) ) {
	echo json_encode( [ 'status' => false, 'msg' => 'no response', 'time' => time() ] );
} else {
	$counter = 0;
	foreach ( $response['response'] as $log ) {
		switch ( $log['action'] ) {
			case 'create':
				//这条评论是刚创建的
				if ( ! in_array( $log['meta']['author_email'], $_IGNORE_EMAIL ) ) {
					notice( $log['meta'] );
					++ $counter;
				}
				break;
			case 'approve':
				//这条评论是通过的评论
				break;
			case 'spam':
				//这条评论是标记垃圾的评论
				break;
			case 'delete':
				//这条评论是删除的评论
				break;
			case 'delete-forever':
				//彻底删除的评论
				break;
			default:
				break;
		}
	}
	echo json_encode( [ 'status' => true, 'action' => D_ACTION, 'record' => count( $response['response'] ), 'counter' => $counter, 'time' => time() ] );
}

/**
 * 通知
 *
 * @param $meta
 */
function notice( $meta ) {
	$post_info = get_list( "http://api.duoshuo.com/threads/listPosts.json", [ 'order' => 'asc', 'short_name' => D_SHORT_NAME, 'limit' => 1, 'thread_key' => $meta['thread_key'] ] );
	$title = $post_info['thread']['title'];
	$url = $post_info['thread']['url'];
	$content = "";
	if(!empty($title)){
		$content.="标题 : {$title}\n";
	}
	if(!empty($url)){
		$content.="地址 : {$url} \n\n";
	}
	$time = date("Y-m-d H:i:s",strtotime($meta['created_at']));
	$content .= <<<HTML
评论内容 :\n
　　{$meta['message']}

时间 : {$time}

作者 : {$meta['author_name']}
邮箱 : {$meta['author_email']}\n
HTML;
	if ( ! empty( $meta['author_url'] ) ) {
		$content .= <<<HTML
主页 : {$meta['author_url']}\n
HTML;
	}
	$content .= <<<HTML
状态 : {$meta['status']}\n
浏览器 : {$meta['agent']}\n
HTML;

	/**
	 * 修改该段的实现，可以实现邮件的通知
	 */
	_push( null, "note", D_NOTICE_TITLE, $content );
}

/**
 * 获取多说的参数
 *
 * @param $url
 * @param $param
 *
 * @return mixed
 */
function get_list( $url, $param ) {
	if ( ! empty( $param ) ) {
		$param = array_map( 'urlencode', array_map( 'trim', $param ) );
		$list = [ ];
		foreach ( $param as $key => $value ) {
			$list[] = $key . "=" . urlencode( $value );
		}
		$url .= "?" . implode( "&", $list );
	}
	$rt = file_get_contents( $url );
	if ( empty( $rt ) ) {
		header( "HTTP/1.1 403 Forbidden" );
		die( "Get error!" );
	}

	return json_decode( $rt, true );
}


/**
 * 签名检测
 *
 * @param $input $_POST参数，一般传递
 *
 * @return bool
 */
function check_signature( $input ) {
	$signature = $input['signature'];
	unset( $input['signature'] );
	ksort( $input );
	$baseString = http_build_query( $input, null, '&' );
	$expectSignature = base64_encode( hmacsha1( $baseString, D_SECRET ) );
	if ( $signature !== $expectSignature ) {
		return false;
	}

	return true;
}


/**
 * @param $data
 * @param $key
 * from: http://www.php.net/manual/en/function.sha1.php#39492
 * Calculate HMAC-SHA1 according to RFC2104
 * http://www.ietf.org/rfc/rfc2104.txt
 *
 * @return string
 */
function hmacsha1( $data, $key ) {
	if ( function_exists( 'hash_hmac' ) ) {
		return hash_hmac( 'sha1', $data, $key, true );
	}

	$blocksize = 64;
	if ( strlen( $key ) > $blocksize ) {
		$key = pack( 'H*', sha1( $key ) );
	}
	$key = str_pad( $key, $blocksize, chr( 0x00 ) );
	$ipad = str_repeat( chr( 0x36 ), $blocksize );
	$opad = str_repeat( chr( 0x5c ), $blocksize );
	$hmac = pack( 'H*', sha1( ( $key ^ $opad ) . pack( 'H*', sha1( ( $key ^ $ipad ) . $data ) ) ) );

	return $hmac;
}

/**
 * Send a push.
 *
 * @param string $recipient Recipient of the push.
 * @param mixed $type Type of the push notification.
 * @param mixed $arg1 Property of the push notification.
 * @param mixed $arg2 Property of the push notification.
 * @param mixed $arg3 Property of the push notification.
 *
 * @return object Response.
 */
function _push( $recipient, $type, $arg1, $arg2 = null, $arg3 = null ) {
	$PUSH = "https://api.pushbullet.com/v2/pushes";
	$queryData = array();
	if ( ! empty( $recipient ) ) {
		if ( filter_var( $recipient, FILTER_VALIDATE_EMAIL ) !== false ) {
			$queryData['email'] = $recipient;
		} else {
			if ( substr( $recipient, 0, 1 ) == "#" ) {
				$queryData['channel_tag'] = substr( $recipient, 1 );
			} else {
				$queryData['device_iden'] = $recipient;
			}
		}
	}
	$queryData['type'] = $type;
	switch ( $type ) {
		case 'note':
			$queryData['title'] = $arg1;
			$queryData['body'] = $arg2;
			break;
		case 'link':
			$queryData['title'] = $arg1;
			$queryData['url'] = $arg2;
			if ( $arg3 !== null ) {
				$queryData['body'] = $arg3;
			}
			break;
		case 'address':
			$queryData['name'] = $arg1;
			$queryData['address'] = $arg2;
			break;
		case 'list':
			$queryData['title'] = $arg1;
			$queryData['items'] = $arg2;
			break;
		default:
			return false;
	}

	return _curlRequest( $PUSH, 'POST', $queryData );
}

/**
 * Send a request to a remote server using cURL.
 *
 * @param string $url URL to send the request to.
 * @param string $method HTTP method.
 * @param array $data Query data.
 * @param bool $sendAsJSON Send the request as JSON.
 * @param bool $auth Use the API key to authenticate
 *
 * @return object Response.
 */
function _curlRequest( $url, $method, $data = null, $sendAsJSON = true, $auth = true ) {
	$curl = curl_init();
	if ( $method == 'GET' && $data !== null ) {
		$url .= '?' . http_build_query( $data );
	}
	curl_setopt( $curl, CURLOPT_URL, $url );
	if ( $auth ) {
		curl_setopt( $curl, CURLOPT_USERPWD, P_SECRET );
	}
	curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, $method );
	if ( $method == 'POST' && $data !== null ) {
		if ( $sendAsJSON ) {
			$data = json_encode( $data );
			curl_setopt( $curl, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json', 'Content-Length: ' . strlen( $data ) ) );
		}
		curl_setopt( $curl, CURLOPT_POSTFIELDS, $data );
	}
	curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $curl, CURLOPT_HEADER, false );
	curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );
	curl_setopt( $curl, CURLOPT_SSL_VERIFYHOST, false );
	$response = curl_exec( $curl );
	if ( $response === false ) {
		echo curl_error( $curl );
		curl_close( $curl );

		return false;
	}
	$httpCode = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
	if ( $httpCode >= 400 ) {
		echo curl_error( $curl );
		curl_close( $curl );

		return false;
	}
	curl_close( $curl );

	return json_decode( $response, true );
}