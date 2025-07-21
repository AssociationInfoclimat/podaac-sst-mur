<?php

namespace Infoclimat\Data\Cartes\Ifremer\PodaacSstMur\Test;

require_once __DIR__ . '/podaac_sst_MUR.php';

use Exception;
use Infoclimat\Data\Cartes\Ifremer\PodaacSstMur\InMemoryJsonTilesRepository;

use function Infoclimat\Data\Cartes\Ifremer\PodaacSstMur\download_podaac_sst_mur;

/**
 * @throws Exception
 */
function execute(): void
{
    $tilesRepository = new InMemoryJsonTilesRepository();
    download_podaac_sst_mur($tilesRepository);
    var_dump($tilesRepository);
}

execute();
