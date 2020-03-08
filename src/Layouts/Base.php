<?php

namespace BOS\Player\Layouts;

class Base {
    function __construct($module, $request) {
        $this->module = $module;
        $this->request = $request;
    }

    function splitContent($content) {
        preg_match('~(<head>(?<head>.+?)</head>)(?<body>.+)~is', $content, $matches);

        if ($matches) {
            $head = $matches['head'];
            $body = str_replace(['<body','</body>','</html>'], ['<div','</div>',''], $matches['body']);
        } else {
            $head = '';
            $body = $content;
        }
        
        return [$head, $body];
    }
    function render($content) {
        
        $module = $this->module;
        $request = $this->request;

        list($head, $body) = $this->splitContent($content);
        
        $BASE_HREF = '<base href="'.$request->requestBase.'">';

        $mainNav = $this->renderNav($request,$module);

        $parseTime = round(1000 * (microtime(true) - BOS_PLAYER_REQUEST_START), 2) . 'ms';
        $peakMemory = round(memory_get_peak_usage() / 1024**2,2).'MB';

        $pageTitle = $module->definition['name'] ?? 'Unnamed';

        return <<<TEMPLATE
    <!DOCTYPE html>
    <html>
    <head>
        <title>{$pageTitle}</title>
        {$BASE_HREF}

        <style>
        html, body { font-family: Sans; padding: 0; margin: 0; }
        body { 
            padding: 10px;
        }
        header {
            padding: 10px;
            border-bottom:1px solid #ccc;
            margin: -10px;
            margin-bottom: 10px;
            line-height: 25px;
        }

        header nav, header nav ul { 
            display: flex;
            padding: 0;
            margin: 0;
        }
        header nav .partition-selector {
            margin-right: 10px;
        }

        header nav li {
            list-style-type: none;
            margin-right: 20px;
        }

        footer.stats {
            position: fixed;
            bottom: 0;
            right: 0;
            background:white;
            font-size: 80%;
        }
        </style>
        <!-- module head -->
        {$head}
        <!-- /module head -->
    </head>
    <body>
        {$mainNav}

        <!-- module body -->
        {$body}
        <!-- /module body -->

        <footer class="stats">
        In $parseTime, consuming $peakMemory
        </footer>
    </body>
    </html>
TEMPLATE;
    }

    function renderPartitionSelector() {
        $request = $this->request;

        $partition = $request->partition;
        $environment = $partition->environment;
        $partitions = [];
        foreach ($environment->listPartitions() as $key=>$part){ 
            $part['path'] = "/$key";
            if ($partition->id === $key) {
                $part['selected'] = 'selected'  ;
            } else {
                $part['selected'] = '';
            }
            $partitions[] = $part;
        }

        if (count($partitions) <= 1) {
            return '';
        }

        return "<div class=\"partition-selector\">
                <select onchange=\"location.href=this.options[this.selectedIndex].value\">
                ".join('', array_map(function($partition) {
                    
                    return '<option '.$partition['selected'].' value="'.$partition['path'].'">'.$partition['name'].'</option>';
                }, $partitions))."
                </select>
            </div>
        ";
    }

    function renderNav() {
        $request = $this->request;

        $menuItems = $request->getMenuItemsForUser();
        
        $partitionSelector = $this->renderPartitionSelector();
        $user = \BOS\Player\User::getUser();
        if ($user) {
            $currentUser = '<div style="float:right;">' . ($user['name'] ?? $user['username'] ?? $user['email'] ?? 'logged in.') . '</div>';
        } else {
            $currentUser = '';
        }

        return "<header>
            {$currentUser}
            <nav>
                {$partitionSelector}
                <ul>" . join('', array_map(function($menuItem) {
                    return '<li><a href="' . $menuItem->url .'">' . $menuItem->title . '</a></li>';
                }, $menuItems))."</ul>
            </nav>
        </header>
        ";
    }
    
}