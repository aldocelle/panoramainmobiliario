<?php
declare(strict_types=1);
require dirname(__DIR__) . "/bootstrap.php";
(new Panorama\Database(Panorama\Config::read()))->migrate();
echo "Migraciones aplicadas.\n";
