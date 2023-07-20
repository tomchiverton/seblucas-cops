<?php
/**
 * COPS (Calibre OPDS PHP Server) class file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Sébastien Lucas <sebastien@slucas.fr>
 */

namespace SebLucas\Cops\Pages;

use SebLucas\Cops\Calibre\Book;
use SebLucas\Cops\Calibre\Publisher;

class PagePublisherDetail extends Page
{
    public function InitializeContent()
    {
        $publisher = Publisher::getPublisherById($this->idGet, $this->getDatabaseId());
        $this->title = $publisher->name;
        [$this->entryArray, $this->totalNumber] = Book::getBooksByPublisher($this->idGet, $this->n, $this->getDatabaseId());
        $this->idPage = $publisher->getEntryId();
    }
}
