<?php

if (!file_exists($autoload = dirname(__DIR__) . '/vendor/autoload.php')) {
    echo <<<EOF

    You must set up the project dependencies, run the following commands:

    $ curl -sS https://getcomposer.org/installer | php
    $ php composer.phar install

EOF;
    exit(1);
}
require $autoload;
