```
$session = 'YourPHPSessionId';
$pixivId = 'PixivUserId';
$fanboxId = 'FanboxUserId';
$socks5 = '127.0.0.1:1080';

$pixiv = new Pixiv();
$pixiv->socks5($socks5);
$pixiv->sessionId($session);
$postIds = $pixiv->getAllPostIds($pixivId);
$posts = $pixiv->getPostByIds($pixivId, array_slice($postIds, 0, 20));
$pixiv->downloadPosts($pixivId, 'path/to/save');

$fanbox = new Fanbox();
$fanbox->sessionId($session);
$fanbox->socks5($socks5);
$posts = $fanbox->getPosts($fanboxId, 10);
$fanbox->downloadPosts($fanboxId, 'path/to/save');
```
