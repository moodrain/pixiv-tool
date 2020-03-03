<?php

require __DIR__ . '/Request.php';

class Fanbox {

    private $sessionId = null;
    private $socks5 = null;

    public function sessionId(string $sessionId = null) {
        if($sessionId === null) {
            return $this->sessionId;
        }
        $this->sessionId = $sessionId;
    }

    public function socks5(string $socks5 = null) {
        if($socks5 === null) {
            return $this->socks5;
        }
        $this->socks5 = $socks5;
    }

    public function getPosts(int $userId) {
        $nextUrl = null;
        $images = [];
        do {
            $req = new Request;
            $req->headers([
                'origin' => 'https://www.pixiv.net',
                'cookie' => 'PHPSESSID=' . $this->sessionId,
            ]);
            $this->socks5 && $req->socks5($this->socks5);
            if(! $nextUrl) {
                $req->url("https://fanbox.pixiv.net/api/post.listCreator");
                $req->query([
                    'userId' => $userId,
                    'limit' => 100,
                ]);
            } else {
                $req->url($nextUrl);
            }
            $rs = $req->get();
            $rs = json_decode($rs, true);
            $images = array_merge($images, $rs['body']['items']);
            $nextUrl = $rs['body']['nextUrl'];
        } while($nextUrl);
        return $images;
    }

    public function savePostsJson(int $userId) {
        file_put_contents($userId . '.json', json_encode($this->getPosts($userId)));
    }

    public function downloadPosts(array $posts) {
        $finish = 1;
        $errors = [];
        @mkdir('download');
        foreach($posts as $post) {
            echo PHP_EOL . $finish++ . '/' . count($posts) . ' ';
            if($post['type'] != 'image') {
                continue;
            }
            $title = $post['title'];
            $id = $post['id'];
            $images = $post['body']['images'] ?? [];
            !$images && $errors[] = $id;
            foreach($images as $index => $image) {
                $ext = pathinfo($image['originalUrl'])['extension'];
                $file = 'download/' . $id . '-' . $title . '-' . ($index + 1) . '.' . $ext;
                if(file_exists($file)) {
                    continue;
                }
                echo '.';
                $req = new Request;
                $this->socks5 && $req->socks5($this->socks5);
                $req->url($image['originalUrl']);
                $req->headers([
                    'cookie' => 'PHPSESSID=' . $this->sessionId,
                ]);
                try {
                    file_put_contents($file, $req->get());
                } catch (Exception $e) {
                    $errors[] = $id . '-' . $index;
                }
            }
        }
    }
}