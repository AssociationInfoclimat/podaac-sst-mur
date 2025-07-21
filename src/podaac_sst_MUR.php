<?php

namespace Infoclimat\Data\Cartes\Ifremer\PodaacSstMur;

use Exception;
use PDO;

const TILES_PATH = '/media/datastore/tempsreel.infoclimat.net/tiles';

/**
 * @throws Exception
 */
function load_pdo_ip(): string|array
{
    $ip = getenv('DB_HOST');
    if (empty($ip)) {
        throw new Exception('Missing DB_HOST in environment variables');
    }
    return $ip;
}

/**
 * @throws Exception
 */
function load_pdo_username(): string|array
{
    $username = getenv('DB_USER');
    if (empty($username)) {
        throw new Exception('Missing DB_USER in environment variables');
    }
    return $username;
}

/**
 * @throws Exception
 */
function load_pdo_password(): string|array
{
    $password = getenv('DB_PASSWORD');
    if (empty($password)) {
        throw new Exception('Missing DB_PASSWORD in environment variables');
    }
    return $password;
}

/**
 * @throws Exception
 */
function load_pdo_config(): array
{
    $ip = load_pdo_ip();
    $username = load_pdo_username();
    $password = load_pdo_password();
    return [$ip, $username, $password];
}

/**
 * @throws Exception
 */
function connexionSQL(string $db): PDO
{
    [$ip, $username, $password] = load_pdo_config();
    return new PDO(
        "mysql:host={$ip};dbname={$db}",
        $username,
        $password
    );
}

/**
 * @throws Exception
 */
function jsontiles_put(string $key, array $value): void
{
    $lnk = connexionSQL('V5');
    $req = $lnk->prepare(
        <<<SQL
            INSERT INTO V5.cartes_tuiles(nom,  donnees)
            VALUES                      (:nom, :donnees)
            ON DUPLICATE KEY UPDATE donnees = VALUES(donnees)
            SQL
    );
    $req->execute([
        'nom'     => $key,
        'donnees' => json_encode($value),
    ]);
}

/**
 * Interface for JSON Tiles Repository
 */
interface JsonTilesRepository
{
    public function put(string $key, array $value): void;
}

/**
 * SQL implementation of JsonTilesRepository
 */
class MySQLJsonTilesRepository implements JsonTilesRepository
{
    /**
     * @throws Exception
     */
    public function put(string $key, array $value): void
    {
        jsontiles_put($key, $value);
    }
}

class InMemoryJsonTilesRepository implements JsonTilesRepository
{
    private array $data = [];

    public function put(string $key, array $value): void
    {
        $this->data[$key] = $value;
    }

    public function get(string $key): array|null
    {
        return $this->data[$key] ?? null;
    }
}

/*
https://nasa.github.io/cumulus-distribution-api/#temporary-s3-credentials
*/
// require __ROOT__ . '/include/composer/vendor/autoload.php';

/*use Aws\S3\S3Client;
use Aws\Exception\AwsException;*/

/**
 * @throws Exception
 */
function download_podaac_sst_mur(JsonTilesRepository $tilesRepository): void
{
    $client_password = getenv('CLIENT_PASSWORD');
    if (empty($client_password)) {
        throw new Exception('Missing CLIENT_PASSWORD in environment variables');
    }
    $idents = base64_encode($client_password);
    $cookies = tempnam('/tmp', 'curl_podaac_cookies.txt');

    /* get NASA OAuth URI */
    /*
    $ch = curl_init("https://archive.podaac.earthdata.nasa.gov/s3credentials");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = explode("\n", substr($response, 0, $header_size));
    $body = substr($response, $header_size);

    // get "Location:" http header
    foreach ($header as $hed) {
        if (strtolower(substr($hed, 0, 10)) == 'location: ') {
            $url_to_target = substr(trim($hed), 10);
        }
    }
    */
    $client_id = getenv('CLIENT_ID');
    if (empty($client_id)) {
        throw new Exception('Missing CLIENT_ID in environment variables');
    }
    $url_to_target = "https://urs.earthdata.nasa.gov/oauth/authorize?client_id={$client_id}&response_type=code&redirect_uri=https://archive.podaac.earthdata.nasa.gov/login&state=%2Fpodaac-ops-cumulus-protected%2FMUR-JPL-L4-GLOB-v4.1%2F20211220090000-JPL-L4_GHRSST-SSTfnd-MUR-GLOB-v02.0-fv04.1.nc&app_type=401";
    echo "Requesting: {$url_to_target}\n";

    /* Authenticate to API */
    $ch = curl_init($url_to_target);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_USERPWD, $client_password);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookies);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Origin: https://archive.podaac.earthdata.nasa.gov',
    ]);
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = explode("\n", substr($response, 0, $header_size));
    $body = substr($response, $header_size);

    // get "location:" http header
    foreach ($header as $hed) {
        if (strtolower(substr($hed, 0, 10)) == 'location: ') {
            $url_to_target = substr(trim($hed), 10);
        }
    }
    echo "Requesting: {$url_to_target}\n";

    /* get and save cookies */
    $ch = curl_init($url_to_target);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Origin: https://archive.podaac.earthdata.nasa.gov',
    ]);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookies);
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = explode("\n", substr($response, 0, $header_size));
    $body = substr($response, $header_size);

    /* get S3 credentials */
    /*
    $ch = curl_init("https://archive.podaac.earthdata.nasa.gov/s3credentials");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookies);
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = explode("\n", substr($response, 0, $header_size));
    $body = substr($response, $header_size);

    $AWS_credentials = json_decode($body, true);

    $s3Client = new Aws\S3\S3Client([
        'version'     => 'latest',
        'region'      => 'us-west-2',
        'credentials' => [
            'key'    => $AWS_credentials['accessKeyId'],
            'secret' => $AWS_credentials['secretAccessKey'],
            'token'  => $AWS_credentials['sessionToken'],
        ],
    ]);

    // NOT WORKING
    // s3://podaac-ops-cumulus-protected/MUR-JPL-L4-GLOB-v4.1/20211220090000-JPL-L4_GHRSST-SSTfnd-MUR-GLOB-v02.0-fv04.1.nc
    $result = $s3Client->getObject([
        'Bucket' => 'podaac-ops-cumulus-public',
        'Key'    => 'MUR-JPL-L4-GLOB-v4.1/20211220090000-JPL-L4_GHRSST-SSTfnd-MUR-GLOB-v02.0-fv04.1.nc',
    ]);
    */

    /* get file */
    // https://archive.podaac.earthdata.nasa.gov/podaac-ops-cumulus-protected/MUR-JPL-L4-GLOB-v4.1/20211220090000-JPL-L4_GHRSST-SSTfnd-MUR-GLOB-v02.0-fv04.1.nc

    $t = time() - 24 * 60 * 60;

    do {
        $year = gmdate('Y', $t);
        $month = gmdate('m', $t);
        $day = gmdate('d', $t);

        $ch = curl_init("https://archive.podaac.earthdata.nasa.gov/podaac-ops-cumulus-protected/MUR-JPL-L4-GLOB-v4.1/{$year}{$month}{$day}090000-JPL-L4_GHRSST-SSTfnd-MUR-GLOB-v02.0-fv04.1.nc");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookies);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Origin: https://archive.podaac.earthdata.nasa.gov',
        ]);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        $response = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = explode("\n", substr($response, 0, $header_size));
        $body = substr($response, $header_size);
        if (trim($header[0]) == 'HTTP/2 404') {
            echo "Error requesting file...\n";

            // try yesterday
            $t -= 24 * 60 * 60;
            continue;
        }
        // get "location:" http header
        $in = "/tmp/mur-jpl-sst.nc";
        foreach ($header as $hed) {
            if (strtolower(substr($hed, 0, 10)) == 'location: ') {
                $url_to_target = substr(trim($hed), 10);
            }
        }
        echo "Requesting: {$url_to_target}\n";
        $ch = curl_init($url_to_target);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookies);
        curl_setopt($ch, CURLOPT_FILE, fopen($in, "w+"));
        $response = curl_exec($ch);

        $tmp_values = '/tmp/ghrsstl4_sst_val.tif';
        // extrait le layer sst du NetCDF
        $cmd = "gdal_translate -unscale -ot Float32 'NETCDF:{$in}:analysed_sst' '{$tmp_values}'";
        passthru($cmd);

        // transforme les coordonnées
        $cmd = "gdal_edit.py -a_srs 'EPSG:4326' -a_ullr -180 90 180 -90 {$tmp_values}";
        passthru($cmd);

        // colorise
        $DIR = __DIR__;
        $tmp_color = '/tmp/ghrsstl4_sst_clr.tif';
        // original temperature_fuse.cpt path was /data/cartes/temp_cpt
        $cmd = "gdaldem color-relief {$tmp_values} {$DIR}/temperature_fuse.cpt {$tmp_color} -nearest_color_entry -co 'COMPRESS=LZW' -co 'PREDICTOR=2' -co 'TILED=YES'";
        passthru($cmd);

        // ajout overviews (pour rapidité de génération des tuiles)
        $cmd = "gdaladdo -r bilinear {$tmp_color}";
        passthru($cmd);

        // sauvegarde
        $dest_dir = TILES_PATH . "/{$year}/{$month}/{$day}";
        if (!is_dir($dest_dir)) {
            mkdir($dest_dir, 0777, true);
        }
        passthru("cp {$tmp_color} '{$dest_dir}/GHRSSTL4sstrgb_00_v00.tif'");
        unlink($tmp_color);
        unlink($tmp_values);

        // update date
        echo "CORRECT\n";
        $tilesRepository->put('GHRSSTL4sstrgb', [
            'year'   => $year,
            'month'  => $month,
            'day'    => $day,
            'hour'   => '00',
            'minute' => '00',
        ]);
        break;
    } while (true);
}
