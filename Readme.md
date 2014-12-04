## 多说Pushbullet通知工具

通过Pushbullet来通知新的用户评论

## 使用说明

分别修改以下几个变量

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

## 反馈

[http://www.loveyu.org/3762.html](http://www.loveyu.org/3762.html)