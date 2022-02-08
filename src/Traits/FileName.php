<?php

namespace PixivTool\Traits;

trait FileName {

    public function trimFileName($name)
    {
        $name =  str_replace(['[', '\\', '\/', ':', '*', '?', '<', '>', '|', ']', '"', '/'], '', $name);
        while (substr($name, -1, 1) == '.') {
            $name = substr($name, 0, -1);
        }
        return $name;
    }

}