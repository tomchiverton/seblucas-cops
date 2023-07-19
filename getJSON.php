<?php
/**
 * COPS (Calibre OPDS PHP Server) HTML main script
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Sébastien Lucas <sebastien@slucas.fr>
 *
 */
use SebLucas\Cops\Output\JSONRenderer;

require_once dirname(__FILE__) . '/config.php';
/** @var array $config */

header('Content-Type:application/json;charset=utf-8');

echo json_encode(JSONRenderer::getJson());
