<?php

namespace PixivTool;

use PixivTool\Traits\CurlProxy;
use PixivTool\Traits\FileName;
use PixivTool\Traits\PixivSession;
use PixivTool\Traits\Restrict;

class Pixiv {

    use PixivSession, CurlProxy, Restrict, FileName;

    public function getAllPostIds($userId)
    {
        $req = $this->getReq();
        $req->url("https://www.pixiv.net/ajax/user/$userId/profile/all");
        $rs = $req->accept('json')->get();
        $postIds = array_merge(array_keys($rs['body']['illusts'] ?? []), array_keys($rs['body']['manga'] ?? []));
        usort($postIds, function($a, $b) {
            return $b - $a;
        });
        return $postIds;
    }

    public function getPostByIds($userId, $postIds)
    {
        $req = $this->getReq("https://www.pixiv.net/ajax/user/$userId/profile/illusts");
        $rs = $req->query([
            'ids' => $postIds,
            'work_category' => 'illust',
            'is_first_page' => 0,
        ])->accept('json')->get();
        $posts = array_values($rs['body']['works']);
        return $this->handlePost($posts);
    }

    public function downloadPosts($userId, $dir = './download')
    {
        $dir = rtrim($dir, DIRECTORY_SEPARATOR);
        $jsonDir = $dir . '/post-jsons';
        @mkdir($dir, 0777, true);
        @mkdir($jsonDir, 0777, true);
        $finish = 0;
        $errors = [];
        $postIds = $this->getAllPostIds($userId);
        $postIdChunks = array_chunk($postIds, 20);
        foreach ($postIdChunks as $postIdChunk) {
            $posts = $this->getPostByIds($userId, $postIdChunk);
            $pass = true;
            foreach ($posts as $post) {
                file_put_contents($jsonDir . '/' . $post['id'] . '.json', json_encode($post, JSON_UNESCAPED_UNICODE));
                $images = $this->allPageUrl($post['imageOriginUrl'], $post['count']);
                $prefix = $dir . '/' . $post['id'] . ' - ' . $this->trimFileName($post['title']);
                foreach ($images as $index => $image) {
                    $no = $index + 1;
                    $noStr = $post['count'] > 1 ? (' - ' . $no) : '';
                    $ext = pathinfo($image)['extension'];
                    $name = $prefix . $noStr . '.' . $ext;
                    if (file_exists($name)) {
                        echo '.';
                        continue;
                    }
                    $content = $this->getReq($image)->header(['referer' => 'https://www.pixiv.net/'])->get();
                    if (strlen($content) < 500) {
                        $pass = false;
                        $errors[] = $post['id'] . '-' . $no;
                        echo '!';
                        continue;
                    }
                    file_put_contents($name, $content);
                    echo '.';
                }
                $pass && $finish++;
            }
        }
        echo join(PHP_EOL, $errors);
        echo PHP_EOL, "Download $userId finish: $finish / " . count($postIds);
    }

    public function getWatches($userId, $hide = false, $page = 1, $limit = 20)
    {
        $req = $this->getReq("https://www.pixiv.net/ajax/user/$userId/following");
        $req->query([
            'rest' => $hide ? 'hide' : 'show',
            'offset' => ($page - 1) * $limit,
            'limit' => $limit,
        ]);
        $rs = $req->accept('json')->get();
        $count = $rs['body']['total'];
        $watches = $rs['body']['users'];
        $return = [];
        foreach($watches as $watch) {
            $w = [];
            $w['id'] = $watch['userId'];
            $w['name'] = $watch['userName'];
            $w['avatar'] = $watch['profileImageUrl'];
            $w['detail'] = $watch['userComment'];
            $w['posts'] = $this->handlePost($watch['illusts']);
            $return[] = $w;
        }
        return [
            'count' => $count,
            'watches' => $return,
        ];
    }

    public function getAllWatches($userId, $hide = false)
    {
        $return = [];
        $page = 1;
        $limit = 100;
        $info = $this->getWatches($userId, $hide, $page++, $limit);
        $return = array_merge($return, $info['watches']);
        $count = $info['count'];
        while($count > $limit * $page) {
            $return[] = $this->getWatches($userId, $hide, $page++, $limit)['watches'];
        }
        return $return;
    }

    private function getReq($url = null)
    {
        $req = $this->getCurl();
        $req->url($url);
        $req->cookie([
            'PHPSESSID' => $this->sessionId,
        ]);
        $req->header([
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/105.0.0.0 Safari/537.36',
        ]);
        return $req;
    }

    private function handlePost($works)
    {
        $return = [];
        foreach($works as $work) {
            if($work['xRestrict'] && ! $this->showRestrict()) {
                continue;
            }
            $w = [];
            $w['id'] = $work['id'];
            $w['title'] = $work['title'];
            $w['url'] = 'https://www.pixiv.net/artworks/' . $work['id'];
            $w['imageThumbUrl'] = $work['url'];
            $url = $this->thumbToUrl($work['url']);
            $w['imageMasterUrl'] = $url['master'];
            $w['imageOriginUrl'] = $url['origin'];
            $w['count'] = $work['pageCount'];
            $w['tags'] = $work['tags'];
            $w['gif'] = $work['illustType'] === 0 ? 0 : 1;
            $w['restrict'] = $work['xRestrict'];
            $return[] = $w;
        }
        return $return;
    }

    private function thumbToUrl($url)
    {
        $matches = [];
        $pattern = '~https://i.pximg.net/c/250x250_80_a2/img-master/img/(....)/(..)/(..)/(..)/(..)/(..)/(.*)_p0_square1200.(.*)~';
        preg_match($pattern, $url, $matches);
        if(empty($matches)) {
            $pattern = '~https://i.pximg.net/c/250x250_80_a2/custom-thumb/img/(....)/(..)/(..)/(..)/(..)/(..)/(.*)_p0_custom1200.(.*)~';
            preg_match($pattern, $url, $matches);
        }
        if(empty($matches)) {
            // 匹配到动图，暂时不处理
            $pattern = '~https://i.pximg.net/c/250x250_80_a2/img-master/img/(....)/(..)/(..)/(..)/(..)/(..)/(.*)_square1200.(.*)~';
            preg_match($pattern, $url, $matches);
            if($matches) {
                return [
                    'master' => $url,
                    'origin' => $url,
                ];
            }
        }
        if(empty($matches)) {
            return [
                'master' => '',
                'origin' => '',
            ];
        }
        $workId = $matches[7];
        $ext = $matches[8];
        $dateStr = implode('/', array_slice($matches, 1, count($matches) - 3));
        $masterUrl = "https://i.pximg.net/img-master/img/${dateStr}/${workId}_p0_master1200.$ext";
        $originUrl = "https://i.pximg.net/img-original/img/${dateStr}/${workId}_p0.$ext";
        return [
            'master' => $masterUrl,
            'origin' => $originUrl,
        ];
    }

    private function allPageUrl($url, $page)
    {
        $offset = strpos($url,  '_p');
        $rs = [];
        for ($p = 0; $p < $page; $p++) {
            $rs[] = substr_replace($url, '_p' . $p, $offset, 3);
        }
        return $rs;
    }

}