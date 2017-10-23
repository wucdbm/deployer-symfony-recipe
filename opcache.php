<?php
$bool = opcache_reset();
if ($bool) {
    exit('Success');
}
exit('Failure');