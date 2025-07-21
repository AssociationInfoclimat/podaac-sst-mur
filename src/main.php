<?php

namespace Infoclimat\Data\Cartes\Ifremer\PodaacSstMur\Main;

require_once __DIR__ . '/podaac_sst_MUR.php';

use Exception;
use Infoclimat\Data\Cartes\Ifremer\PodaacSstMur\InMemoryJsonTilesRepository;
use Infoclimat\Data\Cartes\Ifremer\PodaacSstMur\MySQLJsonTilesRepository;

use function Infoclimat\Data\Cartes\Ifremer\PodaacSstMur\download_podaac_sst_mur;

/**
 * @throws Exception
 */
function execute(): void
{
    $tilesRepository = getenv('APP_ENV') === 'production'
        ? new MySQLJsonTilesRepository()
        : new InMemoryJsonTilesRepository();

    download_podaac_sst_mur($tilesRepository);
}

execute();
