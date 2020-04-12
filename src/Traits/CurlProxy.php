<?php

namespace PixivTool\Traits;

trait CurlProxy {

    private $socks5 = null;

    public function socks5(string $socks5 = null) {
        if($socks5 === null) {
            return $this->socks5;
        }
        $this->socks5 = $socks5;
    }

    public function getCurl(\Muyu\Curl $curl = null) {
        $curl = $curl ?? new \Muyu\Curl;
        $this->socks5 && $curl->proxy([
            'type' => 'socks5',
            'host' => $this->socks5,
        ]);
        return $curl;
    }

}