<?php

namespace PixivTool;

use PixivTool\Traits\CurlProxy;
use PixivTool\Traits\PixivSession;
use PixivTool\Traits\FileName;

class Fanbox {

    use PixivSession, CurlProxy, FileName;

    public function getPost($postId)
    {
        $req = $this->getReq('https://api.fanbox.cc/post.info?postId=' . $postId);
        $post = $req->accept('json')->header(['origin' => 'https://www.fanbox.cc'])->get();
        return $post['body'];
    }

    public function getPosts($userId, $limit = 10, $maxId = null)
    {
        $req = $this->getReq();
        $req->header(['origin' => 'https://www.fanbox.cc']);
        $req->url('https://api.fanbox.cc/post.listCreator');
        $query = [
            'creatorId' => $userId,
            'limit' => $limit,
        ];
        $maxId && $query['maxId'] = $maxId;
        $rs = $req->query($query)->accept('json')->get();
        return $rs['body']['items'] ?? [];
    }

    public function walkPosts($userId, $handler)
    {
        $posts = $this->getPosts($userId);
        $nextPostId = $posts[0]['id'] ?? null;
        while ($nextPostId) {
            $post = $this->getPost($nextPostId);
            $handler($post);
            $nextPostId = $post['prevPost']['id'] ?? null;
        }
    }

    public function downloadPosts($userId, $dir = './download')
    {
        $dir = rtrim($dir, DIRECTORY_SEPARATOR);
        $jsonDir = $dir . '/post-jsons';
        @mkdir($dir, 0777, true);
        @mkdir($jsonDir, 0777, true);
        $total = 0;
        $finish = 0;
        $errors = [];
        $handler = function($post) use ($dir, $jsonDir, &$errors, &$total, &$finish) {
            file_put_contents($jsonDir . '/' . $post['id'] . '.json', json_encode($post, JSON_UNESCAPED_UNICODE));
            $total++;
            $prefix = $dir . '/' . $post['id'] . ' - ' . $this->trimFileName($post['title']);
            $bodyImages = $post['body']['images'] ?? [];
            $bodyFiles = $post['body']['files'] ?? [];
            $bodyImageMap = array_values($post['body']['imageMap'] ?? []);
            $bodyFileMap = array_values($post['body']['fileMap'] ?? []);
            $items = array_merge($bodyImages, $bodyFiles, $bodyImageMap, $bodyFileMap);
            $count = count($items);
            $pass = true;
            foreach ($items as $index => $item) {
                $no = $index + 1;
                $file = $prefix;
                $count > 1 && $file .= (' - ' . $no);
                ! empty($item['name']) && $file .= (' - ' . $item['name']);
                $file .= ('.' . $item['extension']);
                if (file_exists($file)) {
                    echo '.';
                    continue;
                }
                $url = $item['originalUrl'] ?? $item['url'] ?? null;
                if (! $url) {
                    $pass = false;
                    $errors[] = compact('post', 'item', 'no');
                    echo '!';
                    continue;
                }
                $content = $this->getReq($url)->get();
                if (strlen($content) < 500) {
                    $pass = false;
                    $errors[] = compact('post', 'item', 'no');
                    echo '!';
                    continue;
                }
                file_put_contents($file, $content);
                echo '.';
            }
            $pass && $finish++;
        };
        $this->walkPosts($userId, $handler);
        foreach ($errors as $error) {
            $post = $error['post'];
            echo PHP_EOL, $post['id'], ' ', $post['title'], ' ', $error['no'], ' ', $error['item']['id'];
        }
        echo PHP_EOL, "Download $userId finish: $finish / $total";
    }

    private function getReq($url = null)
    {
        $req = $this->getCurl();
        $url && $req->url($url);
        $req->cookie(['FANBOXSESSID' => $this->sessionId])->timeout(600);
        return $req;
    }

}
