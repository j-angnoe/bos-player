<?php

namespace BOS\Player;

class EnvironmentNullPartition extends EnvironmentPartition {
    function __construct($id, $environment) {
        $this->id = $id;
        $this->environment = $environment;
        $this->data = [];
        $this->modules = $this->environment->modules;
    }

    function url($path = '') {
        return $this->environment->url($path);
    }
}