```
$session = 'YourPHPSessionId';
$pixivId = 'PixivUserId';
$fanboxId = 'FanboxUserId';
$socks5 = '127.0.0.1:1080';

$pixiv = new Pixiv;
$pixiv->socks5($socks5);
$pixiv->sessionId($session);
$works = $pixiv->getWorks($pixivId);
var_dump($works);

$fanbox = new Fanbox;
$fanbox->sessionId($session);
$fanbox->socks5($socks5);
$posts = $fanbox->getPosts($fanboxId);
$fanbox->downloadPosts($fanboxId, 'path/to/save');
```