<?php

class Request {

    private $url;
    private $headers = [];
    private $query = [];
    private $socks5;

    public function url(string $url = null) {
        if($url === null) {
            return $this->url;
        }
        $this->url = $url;
    }

    public function headers(array $headers = null) {
        if($headers === null) {
            return $this->headers;
        }
        $this->headers = $headers;
    }

    public function query(array $query = null) {
        if($query === null) {
            return $this->query;
        }
        $this->query = $query;
    }

    public function socks5(string $socks5 = null) {
        if($socks5 === null) {
            return $this->socks5;
        }
        $this->socks5 = $socks5;
    }

    public function get() {
        $headers = [];
        foreach($this->headers as $header => $content) {
            $headers[] = $header . ': ' . $content;
        }
        $url = $this->url;
        $this->query && $url .= '?';
        foreach($this->query as $key => $val) {
            $url .= ($key . '=' . $val . '&');
        }
        $this->query && $url = substr($url, 0, -1);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        if($this->socks5) {
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
            curl_setopt($ch, CURLOPT_PROXY, $this->socks5);
        }
        $result = curl_exec($ch);
        $errno =  curl_errno($ch);
        curl_close($ch);
        if($errno !== 0) {
            throw new Exception('request error: ' . $errno, $errno);
        }
        return $result;
    }

}