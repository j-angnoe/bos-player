<?php

namespace BOS\Player\Response;

use BOS\Player\EnvironmentPartition;

class SelectModule { 
    function __construct(\Bos\Player\Exceptions\SelectModule $exception) {
        $this->partition = $exception->partition;
        $this->request = $exception->request;
    }

    function render() {
        $items = $this->request->getMenuItemsForUser();
        $partition = $this->partition;

        if (count($items) === 1) {
            header('Location: ' . $items[0]->url);            
        }

        $str = 'Select a module:<ul>';

        foreach ($items as $mod) {
            $str .= '<li><a href="'.$mod->url.'">'.$mod->title.'</a></li>'; 
        }
        $str .= '</ul>';

        return $str;
    }

    function __toString() {
        return $this->render();
    }
}
