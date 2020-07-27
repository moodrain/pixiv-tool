<?php

namespace PixivTool;

use PixivTool\Traits\CurlProxy;
use PixivTool\Traits\PixivSession;

class Fanbox {

    use PixivSession, CurlProxy;

    public function getPosts($userId) {
        $nextUrl = null;
        $images = [];
        do {
            $req = $this->getReq();
            $req->header(['origin' => 'https://www.fanbox.cc']);
            if(! $nextUrl) {
                $req->url("https://api.fanbox.cc/post.listCreator");
                $req->query([
                    'creatorId' => $userId,
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

    public function savePostsJson($userId) {
        file_put_contents($userId . '.json', json_encode($this->getPosts($userId)), JSON_UNESCAPED_UNICODE);
    }

    public function downloadPosts(array $posts, $dir = null) {
        $finish = 1;
        $errors = [];
        $dir = $dir ?? 'download';
        @mkdir($dir, 0777, true);
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
                $file = $dir . '/' . $id . '-' . $title . '-' . ($index + 1) . '.' . $ext;
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
        $req->cookie(['FANBOXSESSID' => $this->sessionId]);
        return $req;
    }

}