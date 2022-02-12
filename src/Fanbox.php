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
        return $post;
    }

    public function getPosts($userId)
    {
        $nextUrl = null;
        $posts = [];
        do {
            $req = $this->getReq();
            $req->header(['origin' => 'https://www.fanbox.cc']);
            if (! $nextUrl) {
                $req->url("https://api.fanbox.cc/post.listCreator");
                $req->query([
                    'creatorId' => $userId,
                    'limit' => 100,
                ]);
            } else {
                $req->url($nextUrl);
            }
            $rs = $req->accept('json')->get();
            $posts = array_merge($posts, $rs['body']['items']);
            $nextUrl = $rs['body']['nextUrl'];
        } while($nextUrl);
        return $posts;
    }

    public function walkPosts($userId, $handler, $batch = 20)
    {
        $nextUrl = null;
        while (true)
        {
            $req = $this->getReq();
            $req->header(['origin' => 'https://www.fanbox.cc'])->accept('json');
            if (! $nextUrl) {
                $req->url("https://api.fanbox.cc/post.listCreator");
                $req->query([
                    'creatorId' => $userId,
                    'limit' => $batch,
                ]);
            } else {
                $req->url($nextUrl);
            }
            $rs = $req->get();
            $posts = $rs['body']['items'];
            foreach ($posts as &$post) {
                unset($post['commentList'], $post['nextPost'], $post['prevPost']);
            }
            array_map($handler, $posts);
            $nextUrl = $rs['body']['nextUrl'];
            if (! $nextUrl) {
                break;
            }
        }
    }

    public function countByPrice($userId)
    {
        $priceList = [];
        $priceCounts = [];
        $this->walkPosts($userId, function($post) use (&$priceList, &$priceCounts) {
            $price = $post['feeRequired'];
            if (! array_key_exists($price, $priceCounts)) {
                $priceList[] = $price;
                $priceCounts[$price] = 1;
            } else {
                $priceCounts[$price]++;
            }
        }, 100);
        sort($priceList);
        $rs = [];
        foreach ($priceList as $price) {
            $rs[$price] = $priceCounts[$price];
        }
        return $rs;
    }

    public function downloadPosts($userId, $dir = './download')
    {
        $jsonDir = $dir . '/post-jsons';
        @mkdir($dir, 0777, true);
        @mkdir($jsonDir, 0777, true);
        $total = 0;
        $finish = 0;
        $errors = [];
        $handler = function($post) use ($dir, $jsonDir, &$errors, &$total, &$finish) {
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
                    continue;
                }
                $content = $this->getReq($url)->get();
                if (strlen($content) < 500) {
                    $pass = false;
                    $errors[] = compact('post', 'item', 'no');
                    continue;
                }
                file_put_contents($file, $content);
                file_put_contents($jsonDir . '/' . $post['id'] . '.json', json_encode($post, JSON_UNESCAPED_UNICODE));
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
