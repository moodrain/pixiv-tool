<?php

namespace PixivTool\Traits;

trait Restrict {

    private $showRestrict = false;

    public function showRestrict($showRestrict = null) {
        if($showRestrict === null) {
            return $this->showRestrict;
        }
        $this->showRestrict = $showRestrict;
    }

}