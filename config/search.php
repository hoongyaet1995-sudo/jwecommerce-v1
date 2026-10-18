<?php
return ['require_review'=>true,'node_binary'=>env('SEARCH_NODE_BINARY',PHP_OS_FAMILY==='Windows'?'node':(is_executable('/opt/alt/alt-nodejs22/root/usr/bin/node')?'/opt/alt/alt-nodejs22/root/usr/bin/node':'node'))];
