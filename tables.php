<?php
/**
 * COPS (Calibre OPDS PHP Server) endpoint for datatables (TODO)
 * URL format: tables.php
 *
 * @license    GPL v2 or later (http://www.gnu.org/licenses/gpl.html)
 * @author     Sébastien Lucas <sebastien@slucas.fr>
 * @author     mikespub
 */

use SebLucas\Cops\Framework;
use SebLucas\Cops\Input\Route;
use SebLucas\Cops\Output\Format;
use SebLucas\Cops\Output\Response;
use SebLucas\Cops\Output\RestApi;

require_once __DIR__ . '/config/config.php';

Framework::init();
$data = ['link' => Route::link(RestApi::$handler)];
$data['thead'] = '<tr><th>Route</th><th>Description</th></tr>';
$data['tbody'] = '';
foreach (Route::getRoutes() as $route => $queryParams) {
    if (str_contains($route, '{')) {
        continue;
    }
    $data['tbody'] .= '<tr><td><a href="#" class="route">' . $route . '</a></td><td></td></tr>';
}
$data['tfoot'] = $data['thead'];
$template = __DIR__ . '/templates/tables.html';

$response = new Response('text/html;charset=utf-8');
$response->setContent(Format::template($data, $template));
$response->send();
