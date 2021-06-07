<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();

$app->addErrorMiddleware(true, true, true);

$app->get('/seed_with_test_data', function (Request $request, Response $response) {
    $collection = (new MongoDB\Client('mongodb://root:example@mongo/'))->logs->logs;
    $collection->drop();
    $fixtures = array ( 0 => array ( 'client_id' => 'user15', 'User-Agent' => 'Firefox 59', 'document_location' => 'https://shop.com/products/?id=2', 'document_referer' => 'https://yandex.ru/search/?q=купить+котика,', 'date' => '2018-04-03T07:59:13.286000Z', ), 1 => array ( 'client_id' => 'user15', 'User-Agent' => 'Firefox 59', 'document_location' => 'https://shop.com/checkout', 'document_referer' => 'https://shop.com/products/?id=2', 'date' => '2018-04-04T08:59:16.222000Z', ), 2 => array ( 'client_id' => 'user15', 'User-Agent' => 'Firefox 59', 'document_location' => 'https://shop.com/products/?id=2', 'document_referer' => 'https://referal.ours.com/?ref=123hexcode', 'date' => '2018-04-04T08:30:14.104000Z', ), 3 => array ( 'client_id' => 'user15', 'User-Agent' => 'Firefox 59', 'document_location' => 'https://shop.com/products/?id=2', 'document_referer' => 'https://ad.theirs1.com/?src=q1w2e3r4', 'date' => '2018-04-04T08:45:14.384000Z', ), 4 => array ( 'client_id' => 'user15', 'User-Agent' => 'Firefox 59', 'document_location' => 'https://shop.com/products/?id=2', 'document_referer' => 'https://referal.ours.com/?ref=88887777', 'date' => '2018-04-04T08:50:24.104000Z', ), 5 => array ( 'client_id' => 'user15', 'User-Agent' => 'Firefox 59', 'document_location' => 'https://shop.com/products/?id=2', 'document_referer' => 'https://yandex.ru/search/?q=665556665,', 'date' => '2018-04-04T08:51:13.286000Z', ), 6 => array ( 'client_id' => 'user17', 'User-Agent' => 'Firefox 51', 'document_location' => 'https://shop.com/checkout', 'document_referer' => 'https://shop.com/products/?id=3', 'date' => '2018-04-05T18:59:16.222000Z', ), 7 => array ( 'client_id' => 'user17', 'User-Agent' => 'Firefox 51', 'document_location' => 'https://shop.com/checkout', 'document_referer' => 'https://shop.com/products/?id=33', 'date' => '2018-04-06T17:59:16.222000Z', ), );
    $insertManyResult = $collection->insertMany($fixtures);
    $response->getBody()->write("Db reinitiated with " . count($fixtures) , " records");
    return $response;
});

$app->get('/referrals', function (Request $request, Response $response) {

    $client = (new MongoDB\Client('mongodb://root:example@mongo/'));

    $map = new MongoDB\BSON\Javascript(<<<'JS'
    function() {
        emit(this.client_id, this);
    }
    JS);    
    $reduce = new MongoDB\BSON\Javascript(<<<'JS'
        function(client_id, visits) {
            var visit;
            var output = [];
            visits.sort((a, b) => a.date > b.date);
            var current_referrer = '';
            var client_last_referrer_visit;
            for (var k in visits) {
                visit = visits[k];
                var referrer_domain = /https?:\/\/(.+?)\/.*/.exec(visit.document_referer)[1];
                var is_checkout = visit.document_location == "https://shop.com/checkout";
                if (!is_checkout) {
                    if (referrer_domain != 'yandex.ru') {
                        client_last_referrer_visit = visit;
                        client_last_referrer_visit.referrer_domain = referrer_domain;
                    }            
                } else {
                    output.push({
                        client_id: visit.client_id,
                        document_referer: client_last_referrer_visit ? client_last_referrer_visit.document_referer : "",
                        referrer_domain: client_last_referrer_visit ? client_last_referrer_visit.referrer_domain : "",
                        is_checkout: is_checkout,
                        date: visit.date,
                        ts: Date.parse(visit.date)
                    });
                }
            }    
            return output;
        }    
    JS);

    $populations = $client->logs->logs->mapReduce($map, $reduce, 'users_checkouts', ['replace' => '1']);

    $client->logs->users_checkouts->aggregate(
            [
                ['$unwind' => '$value'],
                ['$project' => [
                    "_id" => 0,
                    "client_id" => '$value.client_id', 
                    "document_referer"=> '$value.document_referer', 
                    "referrer_domain"=> '$value.referrer_domain',
                    "date"=> '$value.date',
                ]],
                ['$out' => 'checkouts']
            ]
    );

    $cursor = $client->logs->checkouts->aggregate([
        ['$match' => ['referrer_domain' => 'referal.ours.com']],
        ['$group' => ['_id' => '$client_id', 'urls' => ['$addToSet' => '$document_referer']]],
    ]);

    $response->getBody()->write('<ul>');
    foreach ($cursor as $row) {
        $response->getBody()->write('<li>');
        $response->getBody()->write($row['_id']);
        $response->getBody()->write('<ul>');
        foreach ($row['urls'] as $url) {
            $response->getBody()->write('<li>' . strval($url) . '</li>');
        }
        $response->getBody()->write('<ul>');
        $response->getBody()->write('</li>');
    }
    $response->getBody()->write('</ul>');
    
    return $response;
});


$app->run();