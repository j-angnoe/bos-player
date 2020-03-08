<?php

namespace BOS\Player\Response;

class SelectPartition {
    var $environment;
    var $mayRedirect;

    function __construct($environment, $mayRedirect = true) {
        $this->environment = $environment;
        $this->mayRedirect = $mayRedirect;
    }

    function render() {
        $partitions = $this->environment->listPartitions();
        
        if (count($partitions) === 1 && $this->mayRedirect) {
            $partitionKey = key($partitions);
            header('Location: ' . $this->environment->url($partitionKey));
        } else {
            header('HTTP/1.1 404 Not found');
        }

        $str = 'Select a partition<ul>';
        foreach ($partitions as $partitionId => $partition) {
            $str .= '<li><a href="/'.$partitionId.'">'.$partition['name'].'</a></li>';        
        }
        $str .= '</ul>';

        return $str;
    }


    function __toString() {
        return $this->render();
    }

}
