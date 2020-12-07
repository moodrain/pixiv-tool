<?php

namespace PixivTool\Traits;

trait FileName {

    public function trimFileName($filename) {
        return str_replace(['[', '\\', '\/', ':', '*', '?', '<', '>', '|', ']', '"', '/'], '', $filename);
    }

}