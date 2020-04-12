<?php

namespace PixivTool;

use PixivTool\Traits\CurlProxy;
use PixivTool\Traits\PixivSession;

class Fanbox {

    use PixivSession, CurlProxy;

    public function getPosts(int $userId) {
        $nextUrl = null;
        $images = [];
        do {
            $req = $this->getReq();
            $req->header(['origin' => 'https://www.pixiv.net']);
            if(! $nextUrl) {
                $req->url("https://fanbox.pixiv.net/api/post.listCreator");
                $req->query([
                    'userId' => $userId,
                    'limit' => 100,
                ]);
            } else {
                $req->url($nextUrl);
            }
            $rs = $req->accept('json')->get();
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
                $req = $this->getReq($image['originalUrl']);
                file_put_contents($file, $req->get());
                ! $req->error()->ok() && $errors[] = $id . '-' . $index;
            }
        }
    }

    private function getReq(string $url = null) {
        $req = $this->getCurl();
        $req->url($url);
        $req->cookie(['PHPSESSID' => $this->sessionId]);
        return $req;
    }

}