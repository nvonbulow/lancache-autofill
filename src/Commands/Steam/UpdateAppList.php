<?php

namespace Zeropingheroes\LancacheAutofill\Commands\Steam;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Database\Capsule\Manager as Capsule;

class UpdateAppList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'steam:update-app-list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get the latest list of apps from Steam';

    /**
     * The URL to get the list of Steam apps from.
     *
     * @var string
     */
    const STEAM_APP_LIST_URL = 'https://api.steampowered.com/ISteamApps/GetAppList/v2';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Clearing apps from database');
        Capsule::table('steam_apps')->truncate();

        $this->info('Downloading app list from Steam Web API');
        $client = new Client();
        $result = $client->request('GET', self::STEAM_APP_LIST_URL);

        if ($result->getStatusCode() != 200) {
            $this->error('Steam Web API unreachable');
            die();
        }

        $response = json_decode($result->getBody(), true);

        $apps = $response['applist']['apps'];

        $apps = array_map(function($app) {
            return [
                'name' => $app['name'],
                'id' => $app['appid']
            ];
        }, $apps);

        // Laravel's SQLite driver can only insert a maximum of 500 records
        // at a time in one compound INSERT statements, so we chunk the list
        // of ~50,000 apps into chunks of 500
        $appsChunked = array_chunk($apps, 500);

        $bar = $this->output->createProgressBar(count($appsChunked));
        $bar->setFormat("%bar% %percent%%");

        $this->info('Inserting records into database');
        foreach ($appsChunked as $appChunk) {
            Capsule::table('steam_apps')->insert($appChunk);
            $bar->advance();
        }
        $bar->finish();

        $this->info(PHP_EOL.'Done');
    }
}