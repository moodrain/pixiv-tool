<?php

namespace PixivTool\Traits;

trait PixivSession {

    private $sessionId = null;

    public function sessionId($sessionId = null)
    {
        if ($sessionId === null) {
            return $this->sessionId;
        }
        $this->sessionId = $sessionId;
    }

}