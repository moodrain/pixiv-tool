<?php

use PixivTool\Fanbox;
use PixivTool\Pixiv;

require './vendor/autoload.php';

$session = 'YourPHPSessionId';
$pixivId = 'PixivUserId';
$fanboxId = 'FanboxUserId';

$pixiv = new Pixiv;
$pixiv->socks5('127.0.0.1:2333');
$pixiv->sessionId($session);
$works = $pixiv->getWorks($pixivId);
var_dump($works);

$fanbox = new Fanbox;
$fanbox->sessionId($session);
$fanbox->socks5('127.0.0.1:2333');
$posts = $fanbox->getPosts($fanboxId);
$fanbox->downloadPosts($fanboxId, 'path/to/save');