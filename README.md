```

<?php

require __DIR__ . '/Fanbox.php';

$fanbox = new Fanbox;

// set your session id
$session = 'PHPSESSID';

// set the fanbox author id
$userId = 'fanboxAuthorId';

// set your socks5 proxy (for cn users)
$fanbox->socks5('127.0.0.1:2333');

$fanbox->sessionId($session);
$posts = $fanbox->getPosts($userId);
$fanbox->downloadPosts($posts);

// images will be storaged in download directory, named with the format: ${postId}-${title}-${order}.${ext}

```