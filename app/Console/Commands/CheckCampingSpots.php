<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Console\Command;
use Maknz\Slack\Facades\Slack;
use PHPHtmlParser\Dom;
use Sunra\PhpSimple\HtmlDomParser;

class CheckCampingSpots extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'camping:check {date} {nights=2} camping:check {region=CoastRegion} {equipment="Single Tent"} ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Temp';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $client = new Client([
            // Base URI is used with relative requests
            'base_uri' => 'https://secure.camis.com/',
            // You can set any number of default request options.
            'timeout' => 2.0,
        ]);
        $jar = new CookieJar;
        $cook = new SetCookie([
            'Name' => 'testcookie',
            'Value' => 'cookie',
            'Domain' => 'secure.camis.com',
        ]);

        //First Request to establish a cookie
        $client->get("DiscoverCamping/BritishColumbiaParks/CoastRegion?List", [
            'cookies' => $jar,
            'headers' => [
                'Host' => 'secure.camis.com',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:53.0) Gecko/20100101 Firefox/53.0',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'no-cache',
            ]

        ]);

        $res = $jar->setCookie($cook);
        //Second Request to set parameters
        $response = $client->post("DiscoverCamping/ResInfo.ashx", [
            'cookies' => $jar,
            'form_params' => [
                'resType' => 'Campsite',
                'partySize' => '',
                'equipment' => '',
                'equipmentSub' => 'Single Tent',
                'tentPads' => '',
                'ReservableOnline_incl' => 'on',
                'arrDate' => '2017-07-1',
                'nights' => '2',
                'apId' => null,
                'rceId' => '',
            ],
            'headers' => [
                'Accept' => '*/*',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept-Language' => 'en-US,en;q=0.8,fr;q=0.6',
                'Connection' => 'keep-alive',
         //       'Content-Length' => '136',
                'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                //'Cookie' => 'ASP.NET_SessionId=h1hkpwwfoyfr3df1fzkgkcnu; testcookie=cookie',
                'Host' => 'secure.camis.com',
                'Origin' => 'https://secure.camis.com',
                'Referer' => 'https://secure.camis.com/DiscoverCamping/BritishColumbiaParks/CoastRegion?List',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36',
            ]
        ]);

        //Third request to get availability
        $response = $client->get("DiscoverCamping/BritishColumbiaParks/CoastRegion?List", ['cookies' => $jar]);

        $body = (string)$response->getBody();

        //Make the Dom request
        $pos = strpos($body, 'linkBCOverview');
        if ($pos !== false) {
            $body = substr_replace($body, rand(0,200), $pos, strlen('linkBCOverview'));
        }

        $body = str_replace('linkBCOverview', rand(0,200), $body);

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();

        $dom->loadHTML($body);

        $domXPath = new \DOMXPath($dom);

        $list = $domXPath->query('//*[@id="viewPort"]/ul[2]/li');
        $this->info('Campgrounds available for 2 nights starting July 01, 2017');
        foreach ($list as $camps){
            $status = $camps->childNodes[0]->getAttribute('title');
            $name = ($camps->childNodes[1]->nodeValue);
            if($status == 'Available'){
                $this->info($status . ': ' .$name);
                Slack::send($status . ': ' .$name);
            }
        }
    }
}
