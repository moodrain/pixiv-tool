<?php

namespace PixivTool;

use PixivTool\Traits\CurlProxy;
use PixivTool\Traits\PixivSession;
use PixivTool\Traits\Restrict;

class Pixiv {

    use PixivSession, CurlProxy, Restrict;

    public function getAllWorkIds(int $userId) {
        $req = $this->getReq();
        $req->url("https://www.pixiv.net/ajax/user/$userId/profile/all");
        $rs = $req->accept('json')->get();
        $workIds = array_merge(array_keys($rs['body']['illusts']), array_keys($rs['body']['manga']));
        usort($workIds, function($a, $b) {
            return $b - $a;
        });
        return $workIds;
    }

    public function getWorkIds(int $userId, int $page = 1, int $limit = 20) {
        $workIds = $this->getAllWorkIds($userId);
        return array_slice($workIds, ($page - 1) * $limit, $limit);
    }

    public function getWorkByIds(int $userId, array $workIds) {
        $req = $this->getReq("https://www.pixiv.net/ajax/user/$userId/profile/illusts");
        $rs = $req->query([
            'ids' => $workIds,
            'work_category' => 'illust',
            'is_first_page' => 0,
        ])->accept('json')->get();
        $works = array_values($rs['body']['works']);
        return $this->handleWorks($works);
    }

    public function getWorks(int $userId, int $page = 1, int $limit = 20) {
        $workIds = $this->getWorkIds($userId, $page, $limit);
        return $this->getWorkByIds($userId, $workIds);
    }
    
    public function getWatches(int $userId, bool $hide = false, int $page = 1, int $limit = 20) {
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
            $w['works'] = $this->handleWorks($watch['illusts']);
            $return[] = $w;
        }
        return [
            'count' => $count,
            'watches' => $return,
        ];
    }

    public function getAllWatches(int $userId, bool $hide = false) {
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

    private function getReq(string $url = null) {
        $req = $this->getCurl();
        $req->url($url);
        $req->cookie(['PHPSESSID' => $this->sessionId]);
        return $req;
    }

    private function handleWorks(array $works) {
        $return = [];
        foreach($works as $work) {
            if($work['xRestrict'] && ! $this->showRestrict()) {
                continue;
            }
            $w = [];
            $w['id'] = $work['id'];
            $w['title'] = $work['title'];
            $w['url'] = 'https://www.pixiv.net/artworks/' . $work['illustId'];
            $w['imageThumbUrl'] = $work['url'];
            $url = $this->thumbToUrl($work['url']);
            $w['imageMasterUrl'] = $url['master'];
            $w['imageOriginUrl'] = $url['origin'];
            $w['count'] = $work['pageCount'];
            $w['tags'] = $work['tags'];
            $w['gif'] = $work['illustType'] === 0 ? 0 : 1;
            $return[] = $w;
        }
        return $return;
    }

    private function thumbToUrl(string $url, int $page = 0) {
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
        $masterUrl = "https://i.pximg.net/img-master/img/${dateStr}/${workId}_p${page}_master1200.$ext";
        $originUrl = "https://i.pximg.net/img-original/img/${dateStr}/${workId}_p${page}.$ext";
        return [
            'master' => $masterUrl,
            'origin' => $originUrl,
        ];
    }

}