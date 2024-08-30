<?php

    require '../vendor/autoload.php';

    $obj = new \Coco\captcha\Captcha(4);
    echo $obj->getCode();