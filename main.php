<?php

require_once __DIR__ . '/vendor/autoload.php';


$json_scheme = TGBotApi\Generator::toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
echo $json_scheme;
